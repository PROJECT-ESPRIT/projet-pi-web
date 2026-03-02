<?php

namespace App\Controller\Forum;

use App\Entity\Forum;
use App\Entity\ForumReponse;
use App\Form\Forum\ForumReponseType;
use App\Repository\ForumReponseRepository;
use App\Service\OpenAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum/reponse')]
final class ForumReponseController extends AbstractController
{
    #[Route(name: 'app_forum_reponse_index', methods: ['GET'])]
    public function index(Request $request, ForumReponseRepository $forumReponseRepository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'dateReponse');
        $order = $request->query->get('order', 'DESC');

        $allowedSortFields = [
            'dateReponse' => 'dateReponse',
        ];

        $sortBy = $allowedSortFields[$sortBy] ?? 'dateReponse';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $queryBuilder = $forumReponseRepository->createQueryBuilder('fr')
            ->leftJoin('fr.forum', 'f')
            ->leftJoin('fr.auteur', 'a');
        
        if ($search) {
            $queryBuilder->where('fr.contenu LIKE :search OR f.sujet LIKE :search OR a.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $queryBuilder->orderBy('fr.' . $sortBy, $order);
        
        $forumReponses = $queryBuilder->getQuery()->getResult();
        
        return $this->render('forum_reponse/index.html.twig', [
            'forum_reponses' => $forumReponses,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/new', name: 'app_forum_reponse_new', methods: ['GET'])]
    public function new(): Response
    {
        // Rediriger vers la page du forum car les réponses se créent directement depuis là
        return $this->redirectToRoute('app_forum_index');
    }

    #[Route('/create', name: 'app_forum_reponse_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, OpenAIService $openAIService): Response
    {
        $forumId = $request->request->get('forum_id');
        $contenu = $request->request->get('contenu');
        $voiceData = $request->request->get('voice_data');
        $uploadedFile = $request->files->get('voice_message');
        $transcribeAudio = $request->request->get('transcribe_audio') === 'true';
        
        // Vérifier si c'est une requête AJAX
        $isAjax = $request->isXmlHttpRequest();
        
        if (!$forumId || (!$contenu && !$voiceData && !$uploadedFile)) {
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Veuillez écrire un message ou enregistrer un message vocal.'
                ], 400);
            }
            $this->addFlash('error', 'Veuillez écrire un message ou enregistrer un message vocal.');
            return $this->redirectToRoute('app_forum_index');
        }

        // Récupérer le forum
        $forum = $entityManager->getRepository(Forum::class)->find($forumId);
        if (!$forum) {
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Post non trouvé.'
                ], 404);
            }
            $this->addFlash('error', 'Post non trouvé.');
            return $this->redirectToRoute('app_forum_index');
        }

        // Créer la réponse
        $forumReponse = new ForumReponse();
        $forumReponse->setForum($forum);
        $forumReponse->setDateReponse(new \DateTimeImmutable());
        
        // Handle voice message and transcription
        $voiceFileName = null;
        $transcriptionText = null;
        
        if ($uploadedFile) {
            // Handle uploaded file
            $voiceFileName = 'voice_' . uniqid() . '.' . $uploadedFile->guessExtension();
            try {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/voices';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $uploadedFile->move($uploadDir, $voiceFileName);
                $forumReponse->setVoiceMessage('/uploads/voices/' . $voiceFileName);
                
                // Transcribe audio if requested
                if ($transcribeAudio) {
                    $transcriptionResult = $openAIService->transcribeAudio($uploadedFile);
                    if ($transcriptionResult['success']) {
                        $transcriptionText = $transcriptionResult['text'];
                    }
                }
            } catch (\Exception $e) {
                error_log('Error uploading voice file: ' . $e->getMessage());
            }
        } elseif ($voiceData && str_starts_with($voiceData, 'data:audio/')) {
            // Handle base64 encoded audio
            $voiceFileName = 'voice_' . uniqid() . '.wav';
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/voices';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Decode base64 and save
            $audioData = base_decode(substr($voiceData, strpos($voiceData, ',') + 1));
            file_put_contents($uploadDir . '/' . $voiceFileName, $audioData);
            $forumReponse->setVoiceMessage('/uploads/voices/' . $voiceFileName);
            
            // Create temporary file for transcription
            if ($transcribeAudio) {
                $tempFile = new \Symfony\Component\HttpFoundation\File\File($uploadDir . '/' . $voiceFileName);
                $transcriptionResult = $openAIService->transcribeAudio($tempFile);
                if ($transcriptionResult['success']) {
                    $transcriptionText = $transcriptionResult['text'];
                }
            }
        }
        
        // Set content: use transcription if available, otherwise use original content or placeholder
        if ($transcriptionText) {
            $forumReponse->setContenu($transcriptionText);
        } elseif ($contenu) {
            $forumReponse->setContenu($contenu);
        } else {
            $forumReponse->setContenu('[Message vocal]');
        }
        
        // Associer l'utilisateur connecté
        $user = $this->getUser();
        if ($user) {
            $forumReponse->setAuteur($user);
        }

        $entityManager->persist($forumReponse);
        $entityManager->flush();

        // Envoyer l'email au posteur original
        $to = $forum->getEmail();
        $from = $_ENV['MAILER_FROM'] ?? 'no-reply@example.com';

        if (is_string($to) && $to !== '' && $to !== $user?->getEmail()) {
            try {
                $messageContent = $transcriptionText ?: $contenu;
                if ($voiceFileName) {
                    $messageContent .= "\n\n[Message vocal joint: " . $voiceFileName . "]";
                }
                
                $email = (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject('Réponse à votre message : ' . $forum->getSujet())
                    ->text($messageContent . "\n\nRéponse de : " . ($user ? $user->getNom() . ' ' . $user->getPrenom() : 'Anonyme'));

                $mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                // Ignorer l'erreur d'email pour AJAX
            }
        }

        // Si c'est une requête AJAX, retourner JSON
        if ($isAjax) {
            // Calculer le nouveau nombre de commentaires
            $newCount = $forum->getReponses()->count();
            
            return $this->json([
                'success' => true,
                'message' => 'Commentaire publié avec succès !',
                'comment' => [
                    'id' => $forumReponse->getId(),
                    'contenu' => $forumReponse->getContenu(),
                    'date' => $forumReponse->getDateReponse()->format('d/m/Y'),
                    'voiceMessage' => $forumReponse->getVoiceMessage(),
                    'auteur' => [
                        'id' => $user?->getId(),
                        'nom' => $user?->getNom() ?? 'Anonyme',
                        'prenom' => $user?->getPrenom() ?? ''
                    ]
                ],
                'newCount' => $newCount
            ]);
        }

        // Pour les requêtes non-AJAX, utiliser les messages flash et rediriger
        $this->addFlash('success', 'Réponse publiée avec succès !');
        
        // Rediriger vers la page du post
        return $this->redirectToRoute('app_forum_show', ['id' => $forumId]);
    }

    #[Route('/{id}', name: 'app_forum_reponse_show', methods: ['GET'])]
    public function show(ForumReponse $forumReponse): Response
    {
        return $this->render('forum_reponse/show.html.twig', [
            'forum_reponse' => $forumReponse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_forum_reponse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumReponse $forumReponse, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ForumReponseType::class, $forumReponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Ajouter un message flash
            $this->addFlash('success', 'Réponse modifiée avec succès !');

            // Rediriger vers la page du post parent
            $forumId = $forumReponse->getForum()->getId();
            return $this->redirectToRoute('app_forum_show', ['id' => $forumId]);
        }

        return $this->render('forum_reponse/edit.html.twig', [
            'forum_reponse' => $forumReponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_forum_reponse_delete', methods: ['POST'])]
    public function delete(Request $request, ForumReponse $forumReponse, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$forumReponse->getId(), $request->getPayload()->getString('_token'))) {
            $forumId = $forumReponse->getForum()->getId();
            $entityManager->remove($forumReponse);
            $entityManager->flush();
            
            // Ajouter un message flash
            $this->addFlash('success', 'Réponse supprimée avec succès !');
            
            // Rediriger vers la page du post parent
            return $this->redirectToRoute('app_forum_show', ['id' => $forumId]);
        }

        return $this->redirectToRoute('app_forum_index');
    }
}
