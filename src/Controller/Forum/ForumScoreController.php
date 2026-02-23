<?php

namespace App\Controller\Forum;

use App\Entity\Forum;
use App\Service\ForumScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/forum/score')]
class ForumScoreController extends AbstractController
{
    private ForumScoringService $scoringService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ForumScoringService $scoringService,
        EntityManagerInterface $entityManager
    ) {
        $this->scoringService = $scoringService;
        $this->entityManager = $entityManager;
    }

    /**
     * Get posts ordered by score (for homepage)
     */
    #[Route('/ranked', name: 'app_forum_score_ranked', methods: ['GET'])]
    public function getRankedPosts(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));

        $posts = $this->scoringService->getPostsByScore($page, $limit);
        
        return $this->json([
            'posts' => $posts,
            'page' => $page,
            'limit' => $limit,
            'has_more' => count($posts) === $limit
        ]);
    }

    /**
     * Get trending posts
     */
    #[Route('/trending', name: 'app_forum_score_trending', methods: ['GET'])]
    public function getTrendingPosts(Request $request): Response
    {
        $limit = min(20, max(1, $request->query->getInt('limit', 10)));
        $posts = $this->scoringService->getTrendingPosts($limit);

        return $this->json([
            'posts' => $posts,
            'limit' => $limit
        ]);
    }

    /**
     * Record a view for a post
     */
    #[Route('/{id<\d+>}/view', name: 'app_forum_score_view', methods: ['POST'])]
    public function recordView(Forum $forum, Request $request): Response
    {
        $userIdentifier = $this->getUser()?->getId();
        $this->scoringService->recordView($forum, $userIdentifier);

        return $this->json([
            'success' => true,
            'views_count' => $forum->getScore()?->getViewsCount() ?? 0
        ]);
    }

    /**
     * Update score after like/dislike
     */
    #[Route('/{id<\d+>}/update', name: 'app_forum_score_update', methods: ['POST'])]
    public function updateScore(Forum $forum): Response
    {
        $this->scoringService->updatePostScore($forum);

        return $this->json([
            'success' => true,
            'score' => $forum->getScore()?->getCalculatedScore() ?? 0,
            'likes_count' => $forum->getScore()?->getLikesCount() ?? 0,
            'dislikes_count' => $forum->getScore()?->getDislikesCount() ?? 0,
            'comments_count' => $forum->getScore()?->getCommentsCount() ?? 0,
            'views_count' => $forum->getScore()?->getViewsCount() ?? 0
        ]);
    }

    /**
     * Batch update all scores (admin only)
     */
    #[Route('/update-all', name: 'app_forum_score_update_all', methods: ['POST'])]
    public function updateAllScores(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $this->scoringService->updateAllScores();

        return $this->json([
            'success' => true,
            'message' => 'All scores updated successfully'
        ]);
    }

    /**
     * Get score details for a post
     */
    #[Route('/{id<\d+>}/details', name: 'app_forum_score_details', methods: ['GET'])]
    public function getScoreDetails(Forum $forum): Response
    {
        $score = $forum->getScore();
        
        if (!$score) {
            return $this->json(['error' => 'Score not found'], 404);
        }

        return $this->json([
            'post_id' => $forum->getId(),
            'calculated_score' => $score->getCalculatedScore(),
            'likes_count' => $score->getLikesCount(),
            'dislikes_count' => $score->getDislikesCount(),
            'comments_count' => $score->getCommentsCount(),
            'views_count' => $score->getViewsCount(),
            'base_score' => $score->getBaseScore(),
            'last_calculated_at' => $score->getLastCalculatedAt()?->format('Y-m-d H:i:s'),
            'last_activity_at' => $score->getLastActivityAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
