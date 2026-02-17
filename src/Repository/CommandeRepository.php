<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 *
 * @method Commande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commande[]    findAll()
 * @method Commande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * @return Commande[]
     */
    public function findForAdminBySearchAndSort(?string $search, string $sort, string $direction): array
    {
        $allowedSorts = [
            'id' => 'c.id',
            'dateCommande' => 'c.dateCommande',
            'statut' => 'c.statut',
            'total' => 'c.total',
            'client' => 'u.nom',
        ];

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.ligneCommandes', 'lc')
            ->leftJoin('lc.produit', 'p')
            ->addSelect('u')
            ->addSelect('lc')
            ->addSelect('p')
            ->distinct();

        if ($search !== null && $search !== '') {
            $qb
                ->andWhere(
                    'LOWER(c.statut) LIKE :search
                    OR LOWER(u.nom) LIKE :search
                    OR LOWER(u.prenom) LIKE :search
                    OR LOWER(p.nom) LIKE :search'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');

            if (ctype_digit($search)) {
                $qb
                    ->orWhere('c.id = :searchId')
                    ->setParameter('searchId', (int) $search);
            }
        }

        $qb->orderBy($allowedSorts[$sort] ?? 'c.dateCommande', $direction);

        return $qb->getQuery()->getResult();
    }
}
