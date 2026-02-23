<?php

namespace App\Repository;

use App\Entity\ForumReponseSignalement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumReponseSignalement>
 */
class ForumReponseSignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReponseSignalement::class);
    }

    public function findByReponseAndUser(int $reponseId, int $userId): ?ForumReponseSignalement
    {
        return $this->createQueryBuilder('frs')
            ->where('frs.reponse = :reponseId')
            ->andWhere('frs.user = :userId')
            ->setParameter('reponseId', $reponseId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByReponse(int $reponseId): int
    {
        return $this->createQueryBuilder('frs')
            ->select('COUNT(frs.id)')
            ->where('frs.reponse = :reponseId')
            ->setParameter('reponseId', $reponseId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findReponsesWithThreeOrMoreReports(): array
    {
        return $this->createQueryBuilder('frs')
            ->select('r.id', 'COUNT(frs.id) as report_count')
            ->join('frs.reponse', 'r')
            ->groupBy('r.id')
            ->having('COUNT(frs.id) >= 3')
            ->getQuery()
            ->getResult();
    }
}
