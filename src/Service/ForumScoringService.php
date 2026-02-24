<?php

namespace App\Service;

use App\Entity\Forum;
use App\Entity\ForumPostScore;
use App\Repository\ForumPostScoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ForumScoringService
{
    private array $weights;
    private array $antiSpamConfig;
    private array $cachingConfig;
    private EntityManagerInterface $entityManager;
    private ForumPostScoreRepository $scoreRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ForumPostScoreRepository $scoreRepository,
        array $forumScoring = []
    ) {
        $this->entityManager = $entityManager;
        $this->scoreRepository = $scoreRepository;
        
        $this->weights = $forumScoring['weights'] ?? [
            'likes' => 2.0,
            'dislikes' => -1.0,
            'comments' => 3.0,
            'views' => 0.1,
            'time_decay_rate' => 0.01,
            'base_score' => 1.0
        ];
        
        $this->antiSpamConfig = $forumScoring['anti_spam'] ?? [
            'view_cooldown' => 300,
            'max_likes_per_hour' => 10,
            'max_comments_per_hour' => 20
        ];
        
        $this->cachingConfig = $forumScoring['caching'] ?? [
            'score_update_interval' => 300,
            'trending_cache_ttl' => 600
        ];
    }

    /**
     * Calculate score for a single post
     */
    public function calculateScore(Forum $forum, ForumPostScore $score): float
    {
        $ageInHours = $this->getAgeInHours($forum);
        $timeDecay = exp(-$ageInHours * $this->weights['time_decay_rate']);
        
        $engagementScore = 
            ($score->getLikesCount() * $this->weights['likes']) +
            ($score->getDislikesCount() * $this->weights['dislikes']) +
            ($score->getCommentsCount() * $this->weights['comments']) +
            ($score->getViewsCount() * $this->weights['views']);

        return ($engagementScore * $timeDecay) + $this->weights['base_score'];
    }

    /**
     * Update score for a single post
     */
    public function updatePostScore(Forum $forum): void
    {
        $score = $forum->getScore();
        if (!$score) {
            $score = new ForumPostScore();
            $score->setForum($forum);
            $score->setViewsCount(0); // Initialize with 0, will be updated separately
            $this->entityManager->persist($score);
        }

        // Update counts from relationships
        $score->setLikesCount($forum->getLikes()->count());
        $score->setDislikesCount($forum->getDislikes()->count());
        $score->setCommentsCount($forum->getReponses()->count());

        // Calculate new score
        $calculatedScore = $this->calculateScore($forum, $score);
        $score->setCalculatedScore(number_format($calculatedScore, 4));
        $score->setLastCalculatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Record a view for a post
     */
    public function recordView(Forum $forum, ?string $userIdentifier = null): void
    {
        // Check if already viewed recently (to prevent view spamming)
        $sessionKey = "forum_view_{$forum->getId()}";
        $recentlyViewed = $_SESSION[$sessionKey] ?? false;
        
        if (!$recentlyViewed || $recentlyViewed < (time() - $this->antiSpamConfig['view_cooldown'])) { // cooldown from config
            $score = $forum->getScore();
            if (!$score) {
                $score = new ForumPostScore();
                $score->setForum($forum);
                $score->setViewsCount(0);
                $this->entityManager->persist($score);
            }

            $score->incrementViews();
            $this->updatePostScore($forum);
            
            $_SESSION[$sessionKey] = time();
        }
    }

    /**
     * Get trending posts
     */
    public function getTrendingPosts(int $limit = 10): array
    {
        return $this->scoreRepository->findTrendingPosts($limit);
    }

    /**
     * Get posts by score with pagination
     */
    public function getPostsByScore(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        return $this->scoreRepository->findPostsByScore($limit, $offset);
    }

    /**
     * Batch update all scores (for cron job)
     */
    public function updateAllScores(): void
    {
        $this->scoreRepository->updateAllScores();
    }

    private function getAgeInHours(Forum $forum): float
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($forum->getDateCreation());
        
        return ($diff->days * 24) + ($diff->h) + ($diff->i / 60) + ($diff->s / 3600);
    }

    /**
     * Get Wilson score for statistical confidence
     */
    public function getWilsonScore(int $likes, int $dislikes, int $comments, float $confidence = 1.96): float
    {
        $n = $likes + $dislikes + $comments;
        
        if ($n === 0) {
            return 0;
        }

        $p = ($likes - $dislikes) / $n;
        $z = $confidence;
        $z2 = $z * $z;
        
        $numerator = $p + ($z2 / (2 * $n)) - $z * sqrt(
            ($p * (1 - $p) + ($z2 / (4 * $n))) / $n
        );
        
        $denominator = 1 + ($z2 / $n);
        
        return max(0, $numerator / $denominator);
    }
}
