<?php

namespace App\Controller;

use App\Entity\ForumReponse;
use App\Entity\ForumReponseLike;
use App\Entity\ForumReponseSignalement;
use App\Repository\ForumReponseLikeRepository;
use App\Repository\ForumReponseSignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum-reponse')]
final class ForumReponseInteractionController extends AbstractController
{
    #[Route('/{id<\\d+>}/like', name: 'app_forum_reponse_like', methods: ['POST'])]
    public function like(ForumReponse $reponse, ForumReponseLikeRepository $likeRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté.'], 401);
        }

        $existingLike = $likeRepository->findByReponseAndUser($reponse->getId(), $user->getId());

        if ($existingLike) {
            // Unlike
            $entityManager->remove($existingLike);
            $entityManager->flush();
            $isLiked = false;
        } else {
            // Like
            $like = new ForumReponseLike();
            $like->setReponse($reponse);
            $like->setUser($user);
            $entityManager->persist($like);
            $entityManager->flush();
            $isLiked = true;
        }

        $likesCount = $likeRepository->countByReponse($reponse->getId());

        return new JsonResponse([
            'success' => true,
            'liked' => $isLiked,
            'likesCount' => $likesCount
        ]);
    }

    #[Route('/{id<\\d+>}/report', name: 'app_forum_reponse_report', methods: ['POST'])]
    public function report(Request $request, ForumReponse $reponse, ForumReponseSignalementRepository $signalementRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté.'], 401);
        }

        // Vérifier le token CSRF pour les requêtes non-AJAX
        if (!$request->isXmlHttpRequest()) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('report'.$reponse->getId(), $submittedToken)) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], 400);
            }
        }

        $existingSignalement = $signalementRepository->findByReponseAndUser($reponse->getId(), $user->getId());

        if ($existingSignalement) {
            return new JsonResponse(['success' => false, 'message' => 'Vous avez déjà signalé ce commentaire.'], 400);
        }

        // Ajouter le signalement
        $signalement = new ForumReponseSignalement();
        $signalement->setReponse($reponse);
        $signalement->setUser($user);
        $entityManager->persist($signalement);
        $entityManager->flush();

        $reportsCount = $signalementRepository->countByReponse($reponse->getId());
        $deleted = false;

        // Vérifier si le nombre de signalements atteint 3
        if ($reportsCount >= 3) {
            $entityManager->remove($reponse);
            $entityManager->flush();
            $deleted = true;
        }

        return new JsonResponse([
            'success' => true,
            'reportsCount' => $reportsCount,
            'deleted' => $deleted
        ]);
    }
}
