<?php

namespace App\Repository;

use App\Entity\ForumReponseLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumReponseLike>
 */
class ForumReponseLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReponseLike::class);
    }

    public function findByReponseAndUser(int $reponseId, int $userId): ?ForumReponseLike
    {
        return $this->createQueryBuilder('frl')
            ->where('frl.reponse = :reponseId')
            ->andWhere('frl.user = :userId')
            ->setParameter('reponseId', $reponseId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByReponse(int $reponseId): int
    {
        return $this->createQueryBuilder('frl')
            ->select('COUNT(frl.id)')
            ->where('frl.reponse = :reponseId')
            ->setParameter('reponseId', $reponseId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
