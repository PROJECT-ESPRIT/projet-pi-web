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

    public function countThisMonth(): int
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.dateReservation BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(): array
    {
        // Définir tous les statuts possibles
        $allStatuses = ['CONFIRMED', 'PENDING', 'CANCELLED'];
        
        // Initialiser le tableau de résultats avec des compteurs à zéro
        $statusCounts = [];
        foreach ($allStatuses as $status) {
            $statusCounts[$status] = 0;
        }
        
        // Récupérer les comptes par statut depuis la base de données
        $results = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();
            
        // Mettre à jour les compteurs avec les valeurs réelles
        foreach ($results as $result) {
            if (in_array($result['status'], $allStatuses)) {
                $statusCounts[$result['status']] = (int) $result['count'];
            }
        }
        
        // Formater les résultats dans le format attendu
        $formattedResults = [];
        foreach ($statusCounts as $status => $count) {
            $formattedResults[] = [
                'status' => $status,
                'count' => $count
            ];
        }
        
        return $formattedResults;
    }

    public function getMonthlyReservations(int $months = 6): array
    {
        $start = (new \DateTime())->modify("-{$months} months")->modify('first day of this month');
        $period = new \DatePeriod(
            new \DateTime($start->format('Y-m-01')),
            new \DateInterval('P1M'),
            new \DateTime((new \DateTime())->format('Y-m-t'))
        );

        $results = [];
        foreach ($period as $date) {
            $results[$date->format('Y-m')] = ['month' => $date->format('M Y'), 'count' => 0];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('r.dateReservation')
            ->where('r.dateReservation >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $key = $row['dateReservation']->format('Y-m');
            if (isset($results[$key])) {
                $results[$key]['count']++;
            }
        }

        return array_values($results);
    }

    /**
     * Event IDs for which the given user has at least one (non-cancelled) reservation.
     *
     * @return int[]
     */
    public function getEventIdsWithReservationFor(User $participant): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.evenement) as eventId')
            ->where('r.participant = :participant')
            ->andWhere('r.status != :cancelled')
            ->setParameter('participant', $participant)
            ->setParameter('cancelled', 'CANCELLED')
            ->groupBy('r.evenement')
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'eventId'));
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

    public function searchForOwnerEvents(array $filters, int $page, int $perPage, User $owner): Paginator
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.evenement', 'e')
            ->leftJoin('r.participant', 'p')
            ->addSelect('e')
            ->addSelect('p')
            ->andWhere('e.organisateur = :owner')
            ->setParameter('owner', $owner);

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

    public function countForOwnerEvents(User $owner): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->leftJoin('r.evenement', 'e')
            ->andWhere('e.organisateur = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
