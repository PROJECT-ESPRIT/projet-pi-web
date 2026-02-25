<?php

namespace App\Repository;

use App\Entity\Donation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Donation>
 *
 * @method Donation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Donation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Donation[]    findAll()
 * @method Donation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DonationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Donation::class);
    }

    /**
     * @return Donation[]
     */
    public function findBySearchAndSort(?string $search, string $sort, string $direction): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortMap = [
            'dateDon' => 'd.dateDon',
            'donateur' => 'donateur.nom',
            'type' => 'type.libelle',
        ];
        $sortField = $sortMap[$sort] ?? $sortMap['dateDon'];

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'donateur')
            ->leftJoin('d.type', 'type')
            ->addSelect('donateur', 'type')
            ->orderBy($sortField, $direction);

        $search = trim((string) $search);
        if ($search !== '') {
            $qb->andWhere('LOWER(d.description) LIKE :search
                OR LOWER(donateur.nom) LIKE :search
                OR LOWER(donateur.prenom) LIKE :search
                OR LOWER(donateur.email) LIKE :search
                OR LOWER(type.libelle) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countThisMonth(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.dateDon >= :start')
            ->setParameter('start', new \DateTimeImmutable('first day of this month midnight'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyDonations(int $months = 6): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery("
            SELECT DATE_FORMAT(date_don, '%Y-%m') AS m, COUNT(*) AS c
            FROM donation
            WHERE date_don >= DATE_SUB(CURRENT_DATE, INTERVAL :months MONTH)
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

    public function countByType(): array
    {
        return $this->createQueryBuilder('d')
            ->select('t.libelle AS typeName, COUNT(d.id) AS count')
            ->leftJoin('d.type', 't')
            ->groupBy('t.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
