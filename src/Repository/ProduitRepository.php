<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 *
 * @method Produit|null find($id, $lockMode = null, $lockVersion = null)
 * @method Produit|null findOneBy(array $criteria, array $orderBy = null)
 * @method Produit[]    findAll()
 * @method Produit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

<<<<<<< HEAD
    public function getTotalStockValue(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.prix * p.stock), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLowStock(int $threshold = 5): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.stock <= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
=======
    /**
     * @return Produit[]
     */
    public function findBySearchAndSort(?string $search, string $sort, string $direction): array
    {
        $allowedSorts = [
            'id' => 'p.id',
            'nom' => 'p.nom',
            'prix' => 'p.prix',
            'stock' => 'p.stock',
        ];

        $qb = $this->createQueryBuilder('p');

        if ($search !== null && $search !== '') {
            $qb
                ->andWhere('LOWER(p.nom) LIKE :search OR LOWER(COALESCE(p.description, \'\')) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $qb->orderBy($allowedSorts[$sort] ?? 'p.id', $direction);

        return $qb->getQuery()->getResult();
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
    }
}
