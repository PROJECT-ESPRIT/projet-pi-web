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

    /**
     * @return TypeDon[]
     */
    public function findBySearchAndSort(?string $search, string $sort, string $direction): array
    {
        $allowedSorts = [
            'id' => 't.id',
            'libelle' => 't.libelle',
        ];

        $qb = $this->createQueryBuilder('t');

        if ($search !== null && $search !== '') {
            $qb
                ->andWhere('LOWER(t.libelle) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $qb->orderBy($allowedSorts[$sort] ?? 't.id', $direction);

        return $qb->getQuery()->getResult();
    }
}
