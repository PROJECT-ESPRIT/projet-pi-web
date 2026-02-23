<?php

namespace App\Repository;

use App\Entity\ForumPostScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPostScore>
 */
class ForumPostScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPostScore::class);
    }

    /**
     * Calculate and update scores for all posts
     */
    public function updateAllScores(): void
    {
        $sql = "
            UPDATE forum_post_score fps
            JOIN forum f ON fps.forum_id = f.id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as likes_count
                FROM forum_like fl
                GROUP BY forum_id
            ) likes ON f.id = likes.forum_id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as dislikes_count
                FROM forum_dislike fd
                GROUP BY forum_id
            ) dislikes ON f.id = dislikes.forum_id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as comments_count
                FROM forum_reponse fr
                GROUP BY forum_id
            ) comments ON f.id = comments.forum_id
            SET 
                fps.likes_count = COALESCE(likes.likes_count, 0),
                fps.dislikes_count = COALESCE(dislikes.dislikes_count, 0),
                fps.comments_count = COALESCE(comments.comments_count, 0),
                fps.calculated_score = (
                    (COALESCE(likes.likes_count, 0) * 2.0) + 
                    (COALESCE(dislikes.dislikes_count, 0) * -1.0) + 
                    (COALESCE(comments.comments_count, 0) * 3.0) + 
                    (fps.views_count * 0.1)
                ) * EXP(-TIMESTAMPDIFF(HOUR, f.date_creation, NOW()) * 0.01) + fps.base_score,
                fps.last_calculated_at = NOW()
        ";

        $this->getEntityManager()->getConnection()->executeStatement($sql);
    }

    /**
     * Get posts ordered by score with pagination
     */
    public function findPostsByScore(int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                f.*,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count,
                TIMESTAMPDIFF(HOUR, f.date_creation, NOW()) as age_in_hours
            FROM forum f
            JOIN forum_post_score fps ON f.id = fps.forum_id
            ORDER BY fps.calculated_score DESC, f.date_creation DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get trending posts (high score, recent activity)
     */
    public function findTrendingPosts(int $limit = 10): array
    {
        $sql = "
            SELECT 
                f.*,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count,
                TIMESTAMPDIFF(HOUR, fps.last_activity_at, NOW()) as hours_since_activity
            FROM forum f
            JOIN forum_post_score fps ON f.id = fps.forum_id
            WHERE fps.last_activity_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY fps.calculated_score DESC, fps.last_activity_at DESC
            LIMIT :limit
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Update single post score
     */
    public function updatePostScore(int $forumId): void
    {
        $sql = "
            UPDATE forum_post_score fps
            JOIN forum f ON fps.forum_id = f.id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as likes_count
                FROM forum_like fl
                WHERE fl.forum_id = :forumId
                GROUP BY forum_id
            ) likes ON f.id = likes.forum_id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as dislikes_count
                FROM forum_dislike fd
                WHERE fd.forum_id = :forumId
                GROUP BY forum_id
            ) dislikes ON f.id = dislikes.forum_id
            LEFT JOIN (
                SELECT 
                    forum_id,
                    COUNT(*) as comments_count
                FROM forum_reponse fr
                WHERE fr.forum_id = :forumId
                GROUP BY forum_id
            ) comments ON f.id = comments.forum_id
            WHERE fps.forum_id = :forumId
            SET 
                fps.likes_count = COALESCE(likes.likes_count, 0),
                fps.dislikes_count = COALESCE(dislikes.dislikes_count, 0),
                fps.comments_count = COALESCE(comments.comments_count, 0),
                fps.calculated_score = (
                    (COALESCE(likes.likes_count, 0) * 2.0) + 
                    (COALESCE(dislikes.dislikes_count, 0) * -1.0) + 
                    (COALESCE(comments.comments_count, 0) * 3.0) + 
                    (fps.views_count * 0.1)
                ) * EXP(-TIMESTAMPDIFF(HOUR, f.date_creation, NOW()) * 0.01) + fps.base_score,
                fps.last_calculated_at = NOW()
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('forumId', $forumId, \PDO::PARAM_INT);
        $stmt->executeStatement();
    }
}
