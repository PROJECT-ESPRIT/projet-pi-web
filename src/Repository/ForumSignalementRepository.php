<?php

namespace App\Repository;

use App\Entity\ForumSignalement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumSignalement>
 */
class ForumSignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumSignalement::class);
    }

    public function findByForumAndUser(int $forumId, int $userId): ?ForumSignalement
    {
        return $this->createQueryBuilder('fs')
            ->where('fs.forum = :forumId')
            ->andWhere('fs.user = :userId')
            ->setParameter('forumId', $forumId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByForum(int $forumId): int
    {
        return $this->createQueryBuilder('fs')
            ->select('COUNT(fs.id)')
            ->where('fs.forum = :forumId')
            ->setParameter('forumId', $forumId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findForumsWithThreeOrMoreReports(): array
    {
        return $this->createQueryBuilder('fs')
            ->select('f.id', 'COUNT(fs.id) as report_count')
            ->join('fs.forum', 'f')
            ->groupBy('f.id')
            ->having('COUNT(fs.id) >= 3')
            ->getQuery()
            ->getResult();
    }
}
