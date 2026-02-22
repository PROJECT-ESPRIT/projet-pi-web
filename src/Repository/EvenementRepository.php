<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\User;
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

    public function getStatsOverview(): array
    {
        $now = new \DateTime();
        $all = $this->findAll();
        $total = count($all);
        $upcoming = 0;
        $ongoing = 0;
        $past = 0;
        $full = 0;
        $totalPlaces = 0;
        $totalTaken = 0;
        $totalRevenuePotential = 0.0;
        $totalRevenueActual = 0.0;

        foreach ($all as $ev) {
            $places = $ev->getNbPlaces();
            $taken = $ev->getReservations()->count();
            $totalPlaces += $places;
            $totalTaken += $taken;
            $totalRevenuePotential += ($ev->getPrix() ?? 0) * $places;
            $totalRevenueActual += ($ev->getPrix() ?? 0) * $taken;

            if ($ev->getDateFin() < $now) {
                $past++;
            } elseif ($ev->getDateDebut() <= $now && $ev->getDateFin() >= $now) {
                $ongoing++;
            } else {
                $upcoming++;
            }
            if ($taken >= $places) {
                $full++;
            }
        }

        $occupancy = $totalPlaces > 0 ? round($totalTaken / $totalPlaces * 100, 1) : 0;

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'ongoing' => $ongoing,
            'past' => $past,
            'full' => $full,
            'totalPlaces' => $totalPlaces,
            'totalTaken' => $totalTaken,
            'occupancy' => $occupancy,
            'revenuePotential' => $totalRevenuePotential,
            'revenueActual' => $totalRevenueActual,
        ];
    }

    public function getTopEvents(int $limit = 5): array
    {
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r')
            ->addSelect('COUNT(r.id) as resCount')
            ->groupBy('e.id')
            ->orderBy('resCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($events as $row) {
            $result[] = ['event' => $row[0], 'reservations' => (int) ($row['resCount'] ?? 0)];
        }
        return $result;
    }

    public function getMonthlyEvents(int $months = 6): array
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

        $events = $this->createQueryBuilder('e')
            ->select('e.createdAt')
            ->where('e.createdAt >= :start')
            ->setParameter('start', $start)
            ->getQuery()
            ->getResult();

        foreach ($events as $row) {
            $key = $row['createdAt']->format('Y-m');
            if (isset($results[$key])) {
                $results[$key]['count']++;
            }
        }

        return array_values($results);
    }

    public function getArtistStatsOverview(User $artist): array
    {
        $now = new \DateTime();
        $events = $this->findBy(['organisateur' => $artist]);
        $total = count($events);
        $upcoming = 0;
        $past = 0;
        $full = 0;
        $totalReservations = 0;

        foreach ($events as $event) {
            $reservationsCount = $event->getReservations()->count();
            $totalReservations += $reservationsCount;

            if ($event->getDateDebut() !== null && $event->getDateDebut() >= $now) {
                $upcoming++;
            } else {
                $past++;
            }

            if (($event->getNbPlaces() ?? 0) > 0 && $reservationsCount >= $event->getNbPlaces()) {
                $full++;
            }
        }

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'past' => $past,
            'full' => $full,
            'totalReservations' => $totalReservations,
        ];
    }

    public function getTopEventsForArtist(User $artist, int $limit = 5): array
    {
        $events = $this->createQueryBuilder('e')
            ->leftJoin('e.reservations', 'r')
            ->addSelect('COUNT(r.id) as resCount')
            ->andWhere('e.organisateur = :artist')
            ->setParameter('artist', $artist)
            ->groupBy('e.id')
            ->orderBy('resCount', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($events as $row) {
            $result[] = ['event' => $row[0], 'reservations' => (int) ($row['resCount'] ?? 0)];
        }

        return $result;
    }
}
