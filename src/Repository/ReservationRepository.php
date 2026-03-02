<?php

namespace App\Repository;

use App\Entity\User;
<<<<<<< HEAD
=======
use App\Entity\Evenement;
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
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

<<<<<<< HEAD
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

=======
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
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

<<<<<<< HEAD
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
=======
    public function countReservedPlacesForEvent(Evenement $evenement): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.quantite), 0)')
            ->where('r.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{total_reservations:int,total_places:int,revenue:float,family_reservations:int,total_discount:float,avg_places:float}
     */
    public function getAdminGlobalStats(): array
    {
        $sql = <<<SQL
SELECT
    COUNT(*) AS total_reservations,
    COALESCE(SUM(quantite), 0) AS total_places,
    COALESCE(SUM(montant_total), 0) AS revenue,
    COALESCE(SUM(CASE WHEN remise_rate > 0 THEN 1 ELSE 0 END), 0) AS family_reservations,
    COALESCE(SUM((prix_unitaire * quantite) - montant_total), 0) AS total_discount,
    COALESCE(AVG(quantite), 0) AS avg_places
FROM reservation
SQL;

        $row = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAssociative() ?: [];

        return [
            'total_reservations' => (int) ($row['total_reservations'] ?? 0),
            'total_places' => (int) ($row['total_places'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? 0),
            'family_reservations' => (int) ($row['family_reservations'] ?? 0),
            'total_discount' => (float) ($row['total_discount'] ?? 0),
            'avg_places' => (float) ($row['avg_places'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{label:string,total:int}>
     */
    public function getWeeklyReservations(int $weeks = 6): array
    {
        $weeks = max(1, min($weeks, 24));
        $start = (new \DateTimeImmutable('monday this week'))->modify('-'.($weeks - 1).' weeks');

        $sql = <<<SQL
SELECT DATE_FORMAT(date_reservation, '%x%v') AS yw, COUNT(*) AS total
FROM reservation
WHERE date_reservation >= :start
GROUP BY yw
ORDER BY yw ASC
SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['start' => $start->format('Y-m-d H:i:s')])
            ->fetchAllAssociative();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['yw']] = (int) $row['total'];
        }

        $result = [];
        for ($i = 0; $i < $weeks; $i++) {
            $date = $start->modify('+'.$i.' weeks');
            $key = $date->format('oW');
            $result[] = [
                'label' => 'S'.$date->format('W').' '.$date->format('o'),
                'total' => $indexed[$key] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{title:string,nb_places:int,reserved_places:int,fill_rate:float}>
     */
    public function getTopEventsByFillRate(int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $sql = <<<SQL
SELECT
    e.titre AS title,
    e.nb_places,
    COALESCE(SUM(r.quantite), 0) AS reserved_places,
    CASE
        WHEN e.nb_places <= 0 THEN 0
        ELSE ROUND((COALESCE(SUM(r.quantite), 0) / e.nb_places) * 100, 2)
    END AS fill_rate
FROM evenement e
LEFT JOIN reservation r ON r.evenement_id = e.id
GROUP BY e.id, e.titre, e.nb_places
ORDER BY fill_rate DESC, reserved_places DESC
LIMIT :lim
SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['lim' => $limit], ['lim' => \Doctrine\DBAL\ParameterType::INTEGER])
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'title' => (string) ($row['title'] ?? ''),
            'nb_places' => (int) ($row['nb_places'] ?? 0),
            'reserved_places' => (int) ($row['reserved_places'] ?? 0),
            'fill_rate' => (float) ($row['fill_rate'] ?? 0),
        ], $rows);
    }
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
}
