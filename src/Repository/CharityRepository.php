<?php

namespace App\Repository;

use App\Entity\Charity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function createActiveQueryBuilder(string $alias = 'c'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.status = :activeStatus', $alias))
            ->setParameter('activeStatus', Charity::STATUS_ACTIVE)
            ->orderBy(sprintf('%s.title', $alias), 'ASC');
    }

    /**
     * @return array<int, array{charity: Charity, donation_count: int, total_amount: float}>
     */
    public function findForListing(bool $includeRejected = false, int $page = 1, int $perPage = 9, ?User $excludeOwner = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('c.donations', 'd')
            ->addSelect('COUNT(d.id) AS donationCount')
            ->addSelect('COALESCE(SUM(d.amount), 0) AS totalAmount')
            ->groupBy('c.id')
            ->addGroupBy('u.id')
            ->orderBy('c.createdAt', 'DESC');

        if (!$includeRejected) {
            $qb->andWhere('c.status = :activeStatus')
                ->setParameter('activeStatus', Charity::STATUS_ACTIVE);
        }

        if ($excludeOwner instanceof User) {
            $qb->andWhere('c.createdBy != :excludeOwner')
                ->setParameter('excludeOwner', $excludeOwner);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $rows = $qb->getQuery()->getResult();

        return array_map(static function (array $row): array {
            return [
                'charity' => $row[0],
                'donation_count' => (int) $row['donationCount'],
                'total_amount' => (float) $row['totalAmount'],
            ];
        }, $rows);
    }

    public function countForListing(bool $includeRejected = false, ?User $excludeOwner = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if (!$includeRejected) {
            $qb->andWhere('c.status = :activeStatus')
                ->setParameter('activeStatus', Charity::STATUS_ACTIVE);
        }

        if ($excludeOwner instanceof User) {
            $qb->andWhere('c.createdBy != :excludeOwner')
                ->setParameter('excludeOwner', $excludeOwner);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<int, array{charity: Charity, donation_count: int, total_amount: float}>
     */
    public function findOwnedByUserWithDonationCount(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->leftJoin('c.donations', 'd')
            ->addSelect('COUNT(d.id) AS donationCount')
            ->addSelect('COALESCE(SUM(d.amount), 0) AS totalAmount')
            ->andWhere('c.createdBy = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static function (array $row): array {
            return [
                'charity' => $row[0],
                'donation_count' => (int) $row['donationCount'],
                'total_amount' => (float) $row['totalAmount'],
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{charity: Charity, donation_count: int, total_amount: float}>
     */
    public function findForAdminFilters(string $status = 'ALL', string $creator = '', ?int $minDonations = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'u')
            ->leftJoin('c.donations', 'd')
            ->addSelect('u')
            ->addSelect('COUNT(d.id) AS donationCount')
            ->addSelect('COALESCE(SUM(d.amount), 0) AS totalAmount')
            ->groupBy('c.id')
            ->addGroupBy('u.id')
            ->orderBy('c.createdAt', 'DESC');

        if ($status === Charity::STATUS_ACTIVE || $status === Charity::STATUS_REJECTED) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        $creator = trim($creator);
        if ($creator !== '') {
            $qb->andWhere('LOWER(u.nom) LIKE :creator OR LOWER(u.prenom) LIKE :creator OR LOWER(u.email) LIKE :creator')
                ->setParameter('creator', '%' . mb_strtolower($creator) . '%');
        }

        if ($minDonations !== null) {
            $qb->having('COUNT(d.id) >= :minDonations')
                ->setParameter('minDonations', $minDonations);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(static function (array $row): array {
            return [
                'charity' => $row[0],
                'donation_count' => (int) $row['donationCount'],
                'total_amount' => (float) $row['totalAmount'],
            ];
        }, $rows);
    }
}
