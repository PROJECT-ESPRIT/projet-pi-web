<?php

namespace App\Repository;

use App\Entity\ForumLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumLike>
 */
class ForumLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumLike::class);
    }

    public function findByForumAndUser(int $forumId, int $userId): ?ForumLike
    {
        return $this->createQueryBuilder('fl')
            ->where('fl.forum = :forumId')
            ->andWhere('fl.user = :userId')
            ->setParameter('forumId', $forumId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByForum(int $forumId): int
    {
        return $this->createQueryBuilder('fl')
            ->select('COUNT(fl.id)')
            ->where('fl.forum = :forumId')
            ->setParameter('forumId', $forumId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
