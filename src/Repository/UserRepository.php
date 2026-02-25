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

    public function countNewThisMonth(): int
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getUsersByRole(): array
    {
        // Récupérer tous les utilisateurs avec leurs rôles
        $users = $this->createQueryBuilder('u')
            ->select('u.roles')
            ->getQuery()
            ->getResult();
        
        // Initialiser les compteurs pour chaque rôle
        $roleCounts = [
            'ROLE_ADMIN' => 0,
            'ROLE_ARTISTE' => 0,
            'ROLE_PARTICIPANT' => 0,
            'ROLE_USER' => 0
        ];
        
        // Compter les utilisateurs par rôle
        foreach ($users as $user) {
            $roles = $user['roles'];
            
            if (in_array('ROLE_ADMIN', $roles)) {
                $roleCounts['ROLE_ADMIN']++;
            } elseif (in_array('ROLE_ARTISTE', $roles)) {
                $roleCounts['ROLE_ARTISTE']++;
            } elseif (in_array('ROLE_PARTICIPANT', $roles)) {
                $roleCounts['ROLE_PARTICIPANT']++;
            } else {
                $roleCounts['ROLE_USER']++;
            }
        }
        
        // Formater les résultats
        $formattedResults = [];
        foreach ($roleCounts as $role => $count) {
            if ($count > 0) {
                $formattedResults[] = [
                    'role' => $role,
                    'count' => $count
                ];
            }
        }
        
        return $formattedResults;
    }

    public function getMonthlyRegistrations(int $months = 12): array
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-$months months");
        
        // Créer un tableau avec tous les mois de la période
        $period = new \DatePeriod(
            new \DateTime($startDate->format('Y-m-01')), // Premier jour du mois
            new \DateInterval('P1M'), // Intervalle d'un mois
            new \DateTime($endDate->format('Y-m-t')) // Dernier jour du mois
        );
        
        // Initialiser le tableau des résultats avec des zéros
        $results = [];
        foreach ($period as $date) {
            $monthKey = $date->format('Y-m');
            $results[$monthKey] = [
                'month' => $date->format('M'),
                'count' => 0
            ];
        }
        
        // Récupérer toutes les dates de création
        $users = $this->createQueryBuilder('u')
            ->select('u.createdAt')
            ->where('u.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getResult();
        
        // Compter les inscriptions par mois
        foreach ($users as $user) {
            $monthKey = $user['createdAt']->format('Y-m');
            if (isset($results[$monthKey])) {
                $results[$monthKey]['count']++;
            }
        }
        
        return array_values($results);
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
