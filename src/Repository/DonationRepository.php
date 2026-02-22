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
            'charity' => 'charity.title',
            'amount' => 'd.amount',
        ];
        $sortField = $sortMap[$sort] ?? $sortMap['dateDon'];

        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'donateur')
            ->leftJoin('d.type', 'type')
            ->leftJoin('d.charity', 'charity')
            ->addSelect('donateur', 'type', 'charity')
            ->orderBy($sortField, $direction);

        $search = trim((string) $search);
        if ($search !== '') {
            $qb->andWhere('LOWER(d.description) LIKE :search
                OR LOWER(donateur.nom) LIKE :search
                OR LOWER(donateur.prenom) LIKE :search
                OR LOWER(donateur.email) LIKE :search
                OR LOWER(type.libelle) LIKE :search
                OR LOWER(charity.title) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<int, int> $charityIds
     * @return array<int, array<int, Donation>>
     */
    public function findRecentByCharityIds(array $charityIds, int $perCharity = 5): array
    {
        $charityIds = array_values(array_unique(array_filter($charityIds, static fn ($id) => (int) $id > 0)));
        if ($charityIds === []) {
            return [];
        }

        $donations = $this->createQueryBuilder('d')
            ->leftJoin('d.donateur', 'u')
            ->addSelect('u')
            ->leftJoin('d.type', 't')
            ->addSelect('t')
            ->leftJoin('d.charity', 'c')
            ->addSelect('c')
            ->andWhere('d.charity IN (:charityIds)')
            ->setParameter('charityIds', $charityIds)
            ->orderBy('d.dateDon', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($donations as $donation) {
            $charity = $donation->getCharity();
            if ($charity === null || $charity->getId() === null) {
                continue;
            }

            $charityId = $charity->getId();
            if (!isset($grouped[$charityId])) {
                $grouped[$charityId] = [];
            }

            if (count($grouped[$charityId]) < $perCharity) {
                $grouped[$charityId][] = $donation;
            }
        }

        return $grouped;
    }
}
