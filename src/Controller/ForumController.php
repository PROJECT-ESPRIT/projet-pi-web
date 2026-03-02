<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Entity\ForumLike;
use App\Entity\ForumSignalement;
use App\Entity\ForumDislike;
use App\Form\ForumType;
use App\Repository\ForumRepository;
use App\Repository\ForumLikeRepository;
use App\Repository\ForumDislikeRepository;
use App\Repository\ForumSignalementRepository;
use App\Service\PdfService;
use App\Service\ForumScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum')]
final class ForumController extends AbstractController
{
    public function __construct(
        private ForumScoringService $scoringService
    ) {
    }
    #[Route(name: 'app_forum_index', methods: ['GET'])]
    public function index(Request $request, ForumRepository $forumRepository, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->getString('search', '');
        $sortBy = $request->query->getString('sort', 'dateCreation');
        $order = $request->query->getString('order', 'DESC');

        $forums = $forumRepository->findBySearchAndSort($search, $sortBy, $order);
        
        // Mettre à jour les scores pour tous les posts affichés
        foreach ($forums as $forum) {
            $this->scoringService->updateScore($forum);
        }
        $entityManager->flush();
        
        return $this->render('forum/index.html.twig', [
            'forums' => $forums,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/new', name: 'app_forum_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $forum = new Forum();
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($forum);
            $entityManager->flush();

            return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/new.html.twig', [
            'forum' => $forum,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'app_forum_show', methods: ['GET'])]
    public function show(Forum $forum): Response
    {
        return $this->render('forum/show.html.twig', [
            'forum' => $forum,
        ]);
    }

    #[Route('/{id<\\d+>}/edit', name: 'app_forum_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Récupérer les paramètres actuels pour rester sur la même page
            $search = $request->query->getString('search', '');
            $sortBy = $request->query->getString('sort', 'dateCreation');
            $order = $request->query->getString('order', 'DESC');
            
            $params = [];
            if ($search) $params['search'] = $search;
            if ($sortBy) $params['sort'] = $sortBy;
            if ($order) $params['order'] = $order;

            return $this->redirectToRoute('app_forum_index', $params, Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/edit.html.twig', [
            'forum' => $forum,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}/delete', name: 'app_forum_delete', methods: ['POST'])]
    public function delete(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$forum->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($forum);
            $entityManager->flush();
        }

        // Récupérer les paramètres actuels pour rester sur la même page
        $search = $request->query->getString('search', '');
        $sortBy = $request->query->getString('sort', 'dateCreation');
        $order = $request->query->getString('order', 'DESC');
        
        $params = [];
        if ($search) $params['search'] = $search;
        if ($sortBy) $params['sort'] = $sortBy;
        if ($order) $params['order'] = $order;

        return $this->redirectToRoute('app_forum_index', $params, Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id<\\d+>}/like', name: 'app_forum_like', methods: ['POST'])]
    public function like(Forum $forum, ForumLikeRepository $likeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté.'], 401);
        }

        $existingLike = $likeRepository->findByForumAndUser($forum->getId(), $user->getId());

        if ($existingLike) {
            // Unlike
            $entityManager->remove($existingLike);
            $entityManager->flush();
            $isLiked = false;
        } else {
            // Like
            $like = new ForumLike();
            $like->setForum($forum);
            $like->setUser($user);
            $entityManager->persist($like);
            $entityManager->flush();
            $isLiked = true;
        }

        // Mettre à jour le score du post
        $this->scoringService->updateScore($forum);
        $entityManager->flush();

        $likesCount = $likeRepository->countByForum($forum->getId());

        return new JsonResponse([
            'success' => true,
            'liked' => $isLiked,
            'likesCount' => $likesCount,
            'score' => $forum->getScore()
        ]);
    }

    #[Route('/{id<\\d+>}/report', name: 'app_forum_report', methods: ['POST'])]
    public function report(Forum $forum, ForumSignalementRepository $signalementRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté.'], 401);
        }

        $existingSignalement = $signalementRepository->findByForumAndUser($forum->getId(), $user->getId());

        if ($existingSignalement) {
            return new JsonResponse(['success' => false, 'message' => 'Vous avez déjà signalé cette publication.'], 400);
        }

        // Ajouter le signalement
        $signalement = new ForumSignalement();
        $signalement->setForum($forum);
        $signalement->setUser($user);
        $entityManager->persist($signalement);
        $entityManager->flush();

        $reportsCount = $signalementRepository->countByForum($forum->getId());
        $deleted = false;

        // Vérifier si le nombre de signalements atteint 3
        if ($reportsCount >= 3) {
            $entityManager->remove($forum);
            $entityManager->flush();
            $deleted = true;
        }

        return new JsonResponse([
            'success' => true,
            'reportsCount' => $reportsCount,
            'deleted' => $deleted
        ]);
    }

    #[Route('/{id<\\d+>}/save-pdf', name: 'app_forum_save_pdf', methods: ['GET'])]
    public function savePdf(Forum $forum, PdfService $pdfService): Response
    {
        try {
            // Log pour débogage
            error_log('Tentative de génération PDF pour forum ID: ' . $forum->getId());
            
            // Essayer d'abord avec le service PDF
            $filename = 'forum-post-' . $forum->getId() . '-' . date('Y-m-d') . '.pdf';
            $pdfContent = $pdfService->generateForumPostPdf($forum);
            
            // Vérifier que le contenu n'est pas vide
            if (empty($pdfContent)) {
                throw new \Exception('Le contenu PDF est vide');
            }
            
            error_log('PDF généré avec succès, taille: ' . strlen($pdfContent) . ' octets');
            
            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Content-Length' => strlen($pdfContent),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0'
                ]
            );
        } catch (\Exception $e) {
            // Log d'erreur détaillé
            error_log('Erreur PDF: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
            
            // Solution de secours : générer un fichier HTML/Texte
            return $this->generateFallbackFile($forum, $e);
        }
    }
    
    private function generateFallbackFile(Forum $forum, \Exception $error): Response
    {
        $date = $forum->getDateCreation() ? $forum->getDateCreation()->format('d/m/Y H:i') : 'N/A';
        $sujet = htmlspecialchars($forum->getSujet(), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($forum->getMessage(), ENT_QUOTES, 'UTF-8');
        
        $content = "
========================================
FORUM POST #{$forum->getId()}
========================================

Auteur: {$forum->getNom()} {$forum->getPrenom()}
Date de publication: {$date}
Email: {$forum->getEmail()}

SUJET:
{$sujet}

MESSAGE:
{$message}

========================================
Généré le: " . date('d/m/Y H:i:s') . "
Plateforme: Forum Communautaire
========================================

Note: Le PDF n'a pas pu être généré. Erreur: {$error->getMessage()}
";
        
        $filename = 'forum-post-' . $forum->getId() . '-' . date('Y-m-d') . '.txt';
        
        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($content)
            ]
        );
    }
}
