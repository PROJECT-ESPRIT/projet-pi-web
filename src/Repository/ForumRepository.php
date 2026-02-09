<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * @return Forum[]
     */
    public function findBySearchAndSort(string $search, string $sortBy, string $order): array
    {
        $qb = $this->createQueryBuilder('f');

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sortBy, $order);

        return $qb->getQuery()->getResult();
    }

    private function applySearch(QueryBuilder $qb, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $qb
            ->andWhere('f.sujet LIKE :search OR f.message LIKE :search OR f.nom LIKE :search OR f.prenom LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    private function applySort(QueryBuilder $qb, string $sortBy, string $order): void
    {
        $allowedSortFields = [
            'dateCreation' => 'f.dateCreation',
            'sujet' => 'f.sujet',
        ];

        $sortExpr = $allowedSortFields[$sortBy] ?? $allowedSortFields['dateCreation'];
        $direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortExpr, $direction);
    }

    //    /**
    //     * @return Forum[] Returns an array of Forum objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Forum
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
