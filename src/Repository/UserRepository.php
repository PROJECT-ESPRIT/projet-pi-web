<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array{items: User[], total: int}
     */
    public function searchAndSortPaginated(
        ?string $query,
        string $sort,
        string $direction,
        int $page,
        int $perPage
    ): array
    {
        $allowedSorts = ['nom', 'prenom', 'email', 'id'];
        $sortField = in_array($sort, $allowedSorts, true) ? $sort : 'nom';
        $sortDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $qb = $this->createQueryBuilder('u');

        if ($query !== null && trim($query) !== '') {
            $q = '%' . mb_strtolower(trim($query)) . '%';
            $qRole = '%' . strtoupper(trim($query)) . '%';

            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(u.email) LIKE :q',
                    'LOWER(u.nom) LIKE :q',
                    'LOWER(u.prenom) LIKE :q',
                    'u.roles LIKE :qRole'
                )
            )
            ->setParameter('q', $q)
            ->setParameter('qRole', $qRole);
        }

        $qb->orderBy('u.' . $sortField, $sortDirection)
            ->addOrderBy('u.id', 'asc')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }
}
