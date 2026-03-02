<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * @return Forum[]
     */
    public function findBySearchAndSort(string $search, string $sortBy, string $order): array
    {
        $qb = $this->createQueryBuilder('f');

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sortBy, $order);

        return $qb->getQuery()->getResult();
    }

    private function applySearch(QueryBuilder $qb, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $qb
            ->andWhere('f.sujet LIKE :search OR f.message LIKE :search OR f.nom LIKE :search OR f.prenom LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }

    private function applySort(QueryBuilder $qb, string $sortBy, string $order): void
    {
        $allowedSortFields = [
            'dateCreation' => 'f.dateCreation',
            'sujet' => 'f.sujet',
        ];

        $sortExpr = $allowedSortFields[$sortBy] ?? $allowedSortFields['dateCreation'];
        $direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortExpr, $direction);
    }

    public function getMonthlyPosts(int $months = 6): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery("
            SELECT DATE_FORMAT(date_creation, '%Y-%m') AS m, COUNT(*) AS c
            FROM forum
            WHERE date_creation >= DATE_SUB(CURRENT_DATE, INTERVAL :months MONTH)
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
}
