<?php

namespace App\Service;

use App\Entity\Forum;
use App\Entity\ForumLike;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ForumAntiSpamService
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private array $config;

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        array $config = []
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->config = array_merge([
            'max_likes_per_hour' => 10,
            'max_comments_per_hour' => 20,
            'max_posts_per_hour' => 5,
            'view_cooldown' => 300, // 5 minutes
            'like_cooldown' => 60, // 1 minute
            'ip_based_limiting' => true
        ], $config);
    }

    /**
     * Check if user can like a post
     */
    public function canLikePost(Forum $forum, User $user): array
    {
        // Check if already liked
        $existingLike = $this->entityManager->getRepository(ForumLike::class)
            ->findOneBy(['forum' => $forum, 'user' => $user]);

        if ($existingLike) {
            return ['allowed' => false, 'reason' => 'already_liked'];
        }

        // Check rate limiting
        $recentLikes = $this->entityManager->getRepository(ForumLike::class)
            ->createQueryBuilder('fl')
            ->where('fl.user = :user')
            ->andWhere('fl.createdAt >= :hourAgo')
            ->setParameter('user', $user)
            ->setParameter('hourAgo', new \DateTime('-1 hour'))
            ->getQuery()
            ->getResult();

        if (count($recentLikes) >= $this->config['max_likes_per_hour']) {
            return ['allowed' => false, 'reason' => 'rate_limit'];
        }

        // Check cooldown
        $sessionKey = "like_cooldown_{$forum->getId()}";
        $lastLike = $this->requestStack->getSession()->get($sessionKey);
        
        if ($lastLike && $lastLike > (time() - $this->config['like_cooldown'])) {
            return ['allowed' => false, 'reason' => 'cooldown'];
        }

        return ['allowed' => true];
    }

    /**
     * Record a like action
     */
    public function recordLike(Forum $forum, User $user): void
    {
        $sessionKey = "like_cooldown_{$forum->getId()}";
        $this->requestStack->getSession()->set($sessionKey, time());
    }

    /**
     * Check IP-based rate limiting
     */
    public function checkIpRateLimit(string $action, int $maxPerHour): bool
    {
        if (!$this->config['ip_based_limiting']) {
            return true;
        }

        $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp();
        if (!$clientIp) {
            return true;
        }

        $sessionKey = "ip_limit_{$action}_{$clientIp}";
        $count = $this->requestStack->getSession()->get($sessionKey, 0);
        $lastReset = $this->requestStack->getSession()->get("{$sessionKey}_reset", 0);

        // Reset counter if hour has passed
        if (time() - $lastReset > 3600) {
            $count = 0;
            $this->requestStack->getSession()->set("{$sessionKey}_reset", time());
        }

        if ($count >= $maxPerHour) {
            return false;
        }

        $this->requestStack->getSession()->set($sessionKey, $count + 1);
        return true;
    }

    /**
     * Detect suspicious voting patterns
     */
    public function detectSuspiciousPatterns(User $user): array
    {
        $patterns = [];

        // Check for rapid liking (like many posts in short time)
        $rapidLikes = $this->entityManager->getRepository(ForumLike::class)
            ->createQueryBuilder('fl')
            ->where('fl.user = :user')
            ->andWhere('fl.createdAt >= :tenMinutesAgo')
            ->setParameter('user', $user)
            ->setParameter('tenMinutesAgo', new \DateTime('-10 minutes'))
            ->getQuery()
            ->getResult();

        if (count($rapidLikes) > 20) {
            $patterns[] = 'rapid_liking';
        }

        // Check for like-unlike patterns
        $likeUnlikeRatio = $this->getLikeUnlikeRatio($user);
        if ($likeUnlikeRatio < 0.3) {
            $patterns[] = 'like_unlike_spam';
        }

        // Check for self-liking (if users can like their own posts)
        $selfLikes = $this->entityManager->getRepository(ForumLike::class)
            ->createQueryBuilder('fl')
            ->join('fl.forum', 'f')
            ->where('fl.user = :user')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        if (count($selfLikes) > 0) {
            $patterns[] = 'self_liking';
        }

        return $patterns;
    }

    /**
     * Calculate like-unlike ratio for a user
     */
    private function getLikeUnlikeRatio(User $user): float
    {
        // This would require tracking unlike actions
        // For now, return a safe default
        return 1.0;
    }

    /**
     * Get spam score for a user based on activity patterns
     */
    public function getUserSpamScore(User $user): float
    {
        $score = 0.0;
        $patterns = $this->detectSuspiciousPatterns($user);

        foreach ($patterns as $pattern) {
            switch ($pattern) {
                case 'rapid_liking':
                    $score += 0.3;
                    break;
                case 'like_unlike_spam':
                    $score += 0.4;
                    break;
                case 'self_liking':
                    $score += 0.5;
                    break;
            }
        }

        return min(1.0, $score);
    }

    /**
     * Check if user action should be blocked
     */
    public function shouldBlockAction(User $user, string $action): bool
    {
        $spamScore = $this->getUserSpamScore($user);
        
        // Block if spam score is too high
        if ($spamScore > 0.7) {
            return true;
        }

        // Check IP-based limits
        return match ($action) {
            'like' => !$this->checkIpRateLimit('like', $this->config['max_likes_per_hour']),
            'comment' => !$this->checkIpRateLimit('comment', $this->config['max_comments_per_hour']),
            'post' => !$this->checkIpRateLimit('post', $this->config['max_posts_per_hour']),
            default => false,
        };
    }
}
