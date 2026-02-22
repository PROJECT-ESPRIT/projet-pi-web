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

    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.total), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.statut AS status, COUNT(c.id) AS count')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function getMonthlyOrders(int $months = 6): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery("
            SELECT DATE_FORMAT(date_commande, '%Y-%m') AS m, COUNT(*) AS c
            FROM commande
            WHERE date_commande >= DATE_SUB(CURRENT_DATE, INTERVAL :months MONTH)
            GROUP BY m ORDER BY m
        ", ['months' => $months])->fetchAllAssociative();

        $data = [];
        $period = new \DateTimeImmutable("-{$months} months");
        for ($i = 0; $i < $months; $i++) {
            $d = $period->modify("+{$i} months");
            $key = $d->format('Y-m');
            $data[$key] = ['month' => $d->format('M Y'), 'count' => 0];
        }
        foreach ($rows as $r) {
            if (isset($data[$r['m']])) {
                $data[$r['m']]['count'] = (int) $r['c'];
            }
        }

        return array_values($data);
    }

    public function getMonthlyRevenue(int $months = 6): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery("
            SELECT DATE_FORMAT(date_commande, '%Y-%m') AS m, COALESCE(SUM(total), 0) AS t
            FROM commande
            WHERE date_commande >= DATE_SUB(CURRENT_DATE, INTERVAL :months MONTH)
            GROUP BY m ORDER BY m
        ", ['months' => $months])->fetchAllAssociative();

        $data = [];
        $period = new \DateTimeImmutable("-{$months} months");
        for ($i = 0; $i < $months; $i++) {
            $d = $period->modify("+{$i} months");
            $key = $d->format('Y-m');
            $data[$key] = ['month' => $d->format('M Y'), 'total' => 0.0];
        }
        foreach ($rows as $r) {
            if (isset($data[$r['m']])) {
                $data[$r['m']]['total'] = round((float) $r['t'], 2);
            }
        }

        return array_values($data);
    }
}
