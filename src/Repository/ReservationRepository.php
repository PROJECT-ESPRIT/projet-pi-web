<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 *
 * @method Reservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reservation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reservation[]    findAll()
 * @method Reservation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function searchAndSort(array $filters, int $page, int $perPage, ?User $participant, bool $isAdmin): Paginator
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.evenement', 'e')
            ->leftJoin('r.participant', 'p')
            ->addSelect('e')
            ->addSelect('p');

        if (!$isAdmin && $participant !== null) {
            $qb->andWhere('r.participant = :participant')
                ->setParameter('participant', $participant);
        }

        if (!empty($filters['q'])) {
            $query = '%' . strtolower($filters['q']) . '%';
            $qb->andWhere(
                'LOWER(e.titre) LIKE :q OR LOWER(e.lieu) LIKE :q OR LOWER(p.nom) LIKE :q OR LOWER(p.prenom) LIKE :q OR LOWER(p.email) LIKE :q'
            )
                ->setParameter('q', $query);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['date_start'])) {
            $qb->andWhere('r.dateReservation >= :dateStart')
                ->setParameter('dateStart', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $qb->andWhere('r.dateReservation <= :dateEnd')
                ->setParameter('dateEnd', $filters['date_end']);
        }

        $sortMap = [
            'date_desc' => ['r.dateReservation', 'DESC'],
            'date_asc' => ['r.dateReservation', 'ASC'],
            'event_date_desc' => ['e.dateDebut', 'DESC'],
            'event_date_asc' => ['e.dateDebut', 'ASC'],
        ];

        $sortKey = $filters['sort'] ?? 'date_desc';
        if (!isset($sortMap[$sortKey])) {
            $sortKey = 'date_desc';
        }

        [$sortField, $sortDir] = $sortMap[$sortKey];
        $qb->addOrderBy($sortField, $sortDir)
            ->addOrderBy('r.id', 'DESC');

        $query = $qb->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query);
    }
}
