<?php

namespace App\Repository;

use App\Entity\TypeDon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeDon>
 *
 * @method TypeDon|null find($id, $lockMode = null, $lockVersion = null)
 * @method TypeDon|null findOneBy(array $criteria, array $orderBy = null)
 * @method TypeDon[]    findAll()
 * @method TypeDon[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeDonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeDon::class);
    }

    public function findDefaultForCommentDonation(): ?TypeDon
    {
        $monetary = $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.libelle) LIKE :money')
            ->setParameter('money', '%argent%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($monetary instanceof TypeDon) {
            return $monetary;
        }

        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
