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
}
