<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Filtrage + tri dynamique
     */
    public function findByFilters(
        ?string $nom,
        ?float $prixMin,
        ?float $prixMax,
        ?int $stockMin,
        ?int $stockMax,
        ?string $sortField = 'p.id',
        ?string $sortDirection = 'DESC'
    ): Query {

        $qb = $this->createQueryBuilder('p');

        // 🔎 Filtre par nom
        if (!empty($nom)) {
            $qb->andWhere('p.nom LIKE :nom')
               ->setParameter('nom', '%' . $nom . '%');
        }

        // 💰 Prix minimum
        if (!empty($prixMin)) {
            $qb->andWhere('p.prix >= :prixMin')
               ->setParameter('prixMin', $prixMin);
        }

        // 💰 Prix maximum
        if (!empty($prixMax)) {
            $qb->andWhere('p.prix <= :prixMax')
               ->setParameter('prixMax', $prixMax);
        }

        // 📦 Stock minimum
        if (!empty($stockMin)) {
            $qb->andWhere('p.stock >= :stockMin')
               ->setParameter('stockMin', $stockMin);
        }

        // 📦 Stock maximum
        if (!empty($stockMax)) {
            $qb->andWhere('p.stock <= :stockMax')
               ->setParameter('stockMax', $stockMax);
        }

        // 🔄 Sécurisation du tri (évite injection SQL)
        $allowedSortFields = ['p.id', 'p.nom', 'p.prix', 'p.stock'];

        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'p.id';
        }

        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortField, $sortDirection);

        return $qb->getQuery();
    }
}
