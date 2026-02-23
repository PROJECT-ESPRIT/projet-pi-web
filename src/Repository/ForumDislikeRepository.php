<?php

namespace App\Repository;

use App\Entity\ForumDislike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumDislike>
 *
 * @method ForumDislike|null find($id, $lockMode = null, $lockVersion = null)
 * @method ForumDislike|null findOneBy(array $criteria, $lockMode = null, $lockVersion = null)
 * @method ForumDislike[]    findAll()
 * @method ForumDislike[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ForumDislikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumDislike::class);
    }

    public function countByForum(int $forumId): int
    {
        return $this->createQueryBuilder('fd')
            ->select('COUNT(fd.id)')
            ->where('fd.forum = :forumId')
            ->setParameter('forumId', $forumId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByForumAndUser(int $forumId, int $userId): ?ForumDislike
    {
        return $this->createQueryBuilder('fd')
            ->where('fd.forum = :forumId')
            ->andWhere('fd.user = :userId')
            ->setParameter('forumId', $forumId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNull();
    }
}
