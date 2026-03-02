<?php

namespace App\Repository;

use App\Entity\Charity;
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
    public function findBySearchAndSort(?string $search, string $sort, string $direction, bool $includeHidden = false): array
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortMap = [
            'dateDon' => 'd.dateDon',
            'donateur' => 'donateur.nom',
            'type' => 'type.libelle',
            'charity' => 'charity.name',
        ];
        $sortField = $sortMap[$sort] ?? $sortMap['dateDon'];

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'donateur')
            ->leftJoin('d.type', 'type')
            ->leftJoin('d.charity', 'charity')
            ->addSelect('donateur', 'type', 'charity')
            ->orderBy($sortField, $direction);

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $qb->andWhere('LOWER(d.description) LIKE :search
                OR LOWER(donateur.nom) LIKE :search
                OR LOWER(donateur.prenom) LIKE :search
                OR LOWER(donateur.email) LIKE :search
                OR LOWER(type.libelle) LIKE :search
                OR LOWER(charity.name) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countThisMonth(bool $includeHidden = false): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.dateDon >= :start')
            ->setParameter('start', new \DateTimeImmutable('first day of this month midnight'));

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getMonthlyDonations(int $months = 6, bool $includeHidden = false): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $hiddenSql = $includeHidden ? '' : ' AND is_hidden = 0';
        $rows = $conn->executeQuery("
            SELECT DATE_FORMAT(date_don, '%Y-%m') AS m, COUNT(*) AS c
            FROM donation
            WHERE date_don >= DATE_SUB(CURRENT_DATE, INTERVAL :months MONTH){$hiddenSql}
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

    public function countByType(bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('t.libelle AS typeName, COUNT(d.id) AS count')
            ->leftJoin('d.type', 't')
            ->groupBy('t.id')
            ->orderBy('count', 'DESC');

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        return $qb->getQuery()->getResult();
    }

    public function sumAmountForCharity(Charity $charity, bool $includeHidden = false): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.amount), 0)')
            ->andWhere('d.charity = :charity')
            ->setParameter('charity', $charity);

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int[] $charityIds
     * @return array<int, Donation[]>
     */
    public function findRecentByCharityIds(array $charityIds, int $limitPerCharity = 5, bool $includeHidden = false): array
    {
        if (empty($charityIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'donateur')
            ->leftJoin('d.type', 'type')
            ->addSelect('donateur', 'type')
            ->andWhere('d.charity IN (:ids)')
            ->setParameter('ids', $charityIds)
            ->orderBy('d.dateDon', 'DESC');

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        $donations = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($donations as $donation) {
            $cid = $donation->getCharity()?->getId();
            if (!$cid) {
                continue;
            }
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [];
            }
            if (count($grouped[$cid]) < $limitPerCharity) {
                $grouped[$cid][] = $donation;
            }
        }

        return $grouped;
    }

    /**
     * @return Donation[]
     */
    public function findByCharity(Charity $charity, int $limit = 10, bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'donateur')
            ->leftJoin('d.type', 'type')
            ->addSelect('donateur', 'type')
            ->andWhere('d.charity = :charity')
            ->setParameter('charity', $charity)
            ->orderBy('d.dateDon', 'DESC')
            ->setMaxResults($limit);

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        return $qb->getQuery()->getResult();
    }

    public function countForCharity(Charity $charity, bool $includeHidden = false): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.charity = :charity')
            ->setParameter('charity', $charity);

        if (!$includeHidden) {
            $qb->andWhere('d.isHidden = 0');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Donation[]
     */
    public function findByDonateurVisible(\App\Entity\User $user): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.type', 'type')
            ->leftJoin('d.charity', 'charity')
            ->addSelect('type', 'charity')
            ->andWhere('d.donateur = :user')
            ->andWhere('d.isHidden = 0')
            ->setParameter('user', $user)
            ->orderBy('d.dateDon', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
