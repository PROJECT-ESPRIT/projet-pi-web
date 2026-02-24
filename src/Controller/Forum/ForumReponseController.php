<?php

namespace App\Controller\Forum;

use App\Entity\Forum;
use App\Entity\ForumReponse;
use App\Form\Forum\ForumReponseType;
use App\Repository\ForumReponseRepository;
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
    public function create(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $forumId = $request->request->get('forum_id');
        $contenu = $request->request->get('contenu');
        
        if (!$forumId || !$contenu) {
            $this->addFlash('error', 'Veuillez remplir tous les champs.');
            return $this->redirectToRoute('app_forum_index');
        }

        // Récupérer le forum
        $forum = $entityManager->getRepository(Forum::class)->find($forumId);
        if (!$forum) {
            $this->addFlash('error', 'Post non trouvé.');
            return $this->redirectToRoute('app_forum_index');
        }

        // Créer la réponse
        $forumReponse = new ForumReponse();
        $forumReponse->setContenu($contenu);
        $forumReponse->setForum($forum);
        $forumReponse->setDateReponse(new \DateTimeImmutable());
        
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
                $email = (new Email())
                    ->from($from)
                    ->to($to)
                    ->subject('Réponse à votre message : ' . $forum->getSujet())
                    ->text($contenu . "\n\nRéponse de : " . ($user ? $user->getNom() . ' ' . $user->getPrenom() : 'Anonyme'));

                $mailer->send($email);
                $this->addFlash('success', 'Réponse publiée avec succès !');
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('warning', 'Réponse publiée, mais l\'email de notification n\'a pas pu être envoyé.');
            }
        } else {
            $this->addFlash('success', 'Réponse publiée avec succès !');
        }

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
