<?php

namespace App\Repository;

use App\Entity\Charity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Charity>
 *
 * @method Charity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Charity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Charity[]    findAll()
 * @method Charity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CharityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Charity::class);
    }

    /**
     * @return array<int, array{charity: Charity, donationsCount: int, donationsAmount: int}>
     */
    public function findAllWithDonationCounts(
        bool $includeHidden = false,
        ?string $search = null,
        string $sort = 'name',
        string $direction = 'ASC'
    ): array
    {
        $qb = $this->createQueryBuilder('c');
        $this->applyDonationCountsBase($qb, $includeHidden);
        $this->applySearchAndSort($qb, $search, $sort, $direction);

        $rows = $qb->getQuery()->getResult();

        return array_map(static function (array $row): array {
            return [
                'charity' => $row[0],
                'donationsCount' => (int) ($row['donationsCount'] ?? 0),
                'donationsAmount' => (int) ($row['donationsAmount'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{charity: Charity, donationsCount: int, donationsAmount: int}>
     */
    public function findOwnedWithDonationCounts(
        User $owner,
        ?string $search = null,
        string $sort = 'name',
        string $direction = 'ASC'
    ): array
    {
        $qb = $this->createQueryBuilder('c');
        $this->applyDonationCountsBase($qb, false);
        $qb
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $owner);
        $this->applySearchAndSort($qb, $search, $sort, $direction);

        $rows = $qb->getQuery()->getResult();

        return array_map(static function (array $row): array {
            return [
                'charity' => $row[0],
                'donationsCount' => (int) ($row['donationsCount'] ?? 0),
                'donationsAmount' => (int) ($row['donationsAmount'] ?? 0),
            ];
        }, $rows);
    }

    private function applyDonationCountsBase($qb, bool $includeHidden): void
    {
        if ($includeHidden) {
            $qb->leftJoin('c.donations', 'd');
        } else {
            $qb->leftJoin('c.donations', 'd', 'WITH', 'd.isHidden = 0');
        }

        $qb
            ->addSelect('COUNT(d.id) AS donationsCount')
            ->addSelect('COALESCE(SUM(d.amount), 0) AS donationsAmount')
            ->addSelect('CASE WHEN c.goalAmount IS NULL OR c.goalAmount = 0 THEN 0 ELSE (COALESCE(SUM(d.amount), 0) / c.goalAmount) END AS progressRatio')
            ->groupBy('c.id');

        if (!$includeHidden) {
            $qb->andWhere('c.isHidden = 0');
        }
    }

    private function applySearchAndSort($qb, ?string $search, string $sort, string $direction): void
    {
        $query = trim((string) $search);
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.description) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sortKey = match ($sort) {
            'newest' => 'c.createdAt',
            'donations' => 'donationsCount',
            'amount' => 'donationsAmount',
            'goal' => 'c.goalAmount',
            'progress' => 'progressRatio',
            default => 'c.name',
        };

        $qb->orderBy($sortKey, $dir);
        if ($sortKey !== 'c.name') {
            $qb->addOrderBy('c.name', 'ASC');
        }
    }
}
