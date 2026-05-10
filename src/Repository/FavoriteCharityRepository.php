<?php

namespace App\Repository;

use App\Entity\Charity;
use App\Entity\FavoriteCharity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteCharity>
 */
class FavoriteCharityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteCharity::class);
    }

    public function isFavorite(User $user, Charity $charity): bool
    {
        return $this->find(['user' => $user, 'charity' => $charity]) !== null;
    }

    public function addFavorite(User $user, Charity $charity): bool
    {
        if ($this->isFavorite($user, $charity)) {
            return false;
        }
        $em = $this->getEntityManager();
        $em->persist(new FavoriteCharity($user, $charity));
        $em->flush();
        return true;
    }

    public function removeFavorite(User $user, Charity $charity): bool
    {
        $fav = $this->find(['user' => $user, 'charity' => $charity]);
        if ($fav === null) {
            return false;
        }
        $em = $this->getEntityManager();
        $em->remove($fav);
        $em->flush();
        return true;
    }

    /**
     * @return Charity[]
     */
    public function findCharitiesByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('c')
            ->join('f.charity', 'c')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countForCharity(Charity $charity): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.user)')
            ->where('f.charity = :c')
            ->setParameter('c', $charity)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
