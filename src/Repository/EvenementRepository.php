<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Evenement>
 *
 * @method Evenement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evenement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evenement[]    findAll()
 * @method Evenement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function save(Evenement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Evenement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Evenement[]
     */
    public function searchAndSort(array $filters, int $page, int $perPage): Paginator
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.organisateur', 'o')
            ->addSelect('o');

        if (!empty($filters['q'])) {
            $query = '%' . strtolower($filters['q']) . '%';
            $qb->andWhere(
                'LOWER(e.titre) LIKE :q OR LOWER(e.description) LIKE :q OR LOWER(e.lieu) LIKE :q OR LOWER(o.nom) LIKE :q OR LOWER(o.prenom) LIKE :q'
            )
                ->setParameter('q', $query);
        }

        if (!empty($filters['lieu'])) {
            $lieu = '%' . strtolower($filters['lieu']) . '%';
            $qb->andWhere('LOWER(e.lieu) LIKE :lieu')
                ->setParameter('lieu', $lieu);
        }

        if (!empty($filters['date_start'])) {
            $qb->andWhere('e.dateDebut >= :dateStart')
                ->setParameter('dateStart', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $qb->andWhere('e.dateDebut <= :dateEnd')
                ->setParameter('dateEnd', $filters['date_end']);
        }

        if ($filters['prix_min'] !== null) {
            $qb->andWhere('COALESCE(e.prix, 0) >= :prixMin')
                ->setParameter('prixMin', $filters['prix_min']);
        }

        if ($filters['prix_max'] !== null) {
            $qb->andWhere('COALESCE(e.prix, 0) <= :prixMax')
                ->setParameter('prixMax', $filters['prix_max']);
        }

        $sortMap = [
            'date_asc' => ['e.dateDebut', 'ASC'],
            'date_desc' => ['e.dateDebut', 'DESC'],
            'prix_asc' => ['e.prix', 'ASC', 'NULLS FIRST'],
            'prix_desc' => ['e.prix', 'DESC', 'NULLS LAST'],
            'titre_asc' => ['e.titre', 'ASC'],
            'titre_desc' => ['e.titre', 'DESC'],
            'created_desc' => ['e.createdAt', 'DESC'],
        ];

        $sortKey = $filters['sort'] ?? 'date_asc';
        if (!isset($sortMap[$sortKey])) {
            $sortKey = 'date_asc';
        }

        [$sortField, $sortDir, $nulls] = array_pad($sortMap[$sortKey], 3, null);
        
        // For databases that support NULLS FIRST/LAST
        if ($nulls !== null) {
            $qb->addOrderBy("$sortField IS NULL");
            $qb->addOrderBy($sortField, $sortDir);
        } else {
            $qb->addOrderBy($sortField, $sortDir);
        }
        
        $qb->addOrderBy('e.id', 'DESC');

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query);
    }

//    /**
//     * @return Evenement[] Returns an array of Evenement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
}
