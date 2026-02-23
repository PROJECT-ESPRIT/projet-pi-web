<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumScoreOptimizedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * Get posts with scores using optimized query
     */
    public function findPostsWithScores(int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                f.id,
                f.sujet,
                f.nom,
                f.prenom,
                f.message,
                f.date_creation,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count,
                fps.last_activity_at,
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
     * Get trending posts with performance optimization
     */
    public function findTrendingPosts(int $limit = 10): array
    {
        $sql = "
            SELECT 
                f.id,
                f.sujet,
                f.nom,
                f.prenom,
                f.message,
                f.date_creation,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count,
                fps.last_activity_at,
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
     * Get posts by category with scores
     */
    public function findPostsByCategoryWithScores(string $category, int $limit = 20): array
    {
        $sql = "
            SELECT 
                f.id,
                f.sujet,
                f.nom,
                f.prenom,
                f.message,
                f.date_creation,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count
            FROM forum f
            JOIN forum_post_score fps ON f.id = fps.forum_id
            WHERE f.category = :category
            ORDER BY fps.calculated_score DESC, f.date_creation DESC
            LIMIT :limit
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('category', $category);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Search posts with score relevance
     */
    public function searchPostsWithScores(string $query, int $limit = 20): array
    {
        $sql = "
            SELECT 
                f.id,
                f.sujet,
                f.nom,
                f.prenom,
                f.message,
                f.date_creation,
                fps.calculated_score,
                fps.likes_count,
                fps.dislikes_count,
                fps.comments_count,
                fps.views_count,
                MATCH(f.sujet, f.message) AGAINST(:query IN NATURAL LANGUAGE MODE) as relevance_score
            FROM forum f
            JOIN forum_post_score fps ON f.id = fps.forum_id
            WHERE MATCH(f.sujet, f.message) AGAINST(:query IN NATURAL LANGUAGE MODE)
            ORDER BY (fps.calculated_score * 0.7 + relevance_score * 0.3) DESC
            LIMIT :limit
        ";

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('query', $query);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        
        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
