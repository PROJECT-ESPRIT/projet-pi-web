<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
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
<<<<<<< HEAD
=======

    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUsersCreatedSince(DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUsersCreatedBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countArtistsByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :artistRole')
            ->andWhere('u.status = :status')
            ->setParameter('artistRole', '%"ROLE_ARTISTE"%')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    public function getWeeklyRegistrations(int $weeks = 6): array
    {
        $weeks = max(1, min($weeks, 24));
        $start = (new DateTimeImmutable('monday this week'))->modify('-'.($weeks - 1).' weeks');

        $sql = <<<SQL
SELECT DATE_FORMAT(created_at, '%x%v') AS yw, COUNT(*) AS total
FROM user
WHERE created_at >= :start
GROUP BY yw
ORDER BY yw ASC
SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['start' => $start->format('Y-m-d H:i:s')])
            ->fetchAllAssociative();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['yw']] = (int) $row['total'];
        }

        $result = [];
        for ($i = 0; $i < $weeks; $i++) {
            $date = $start->modify('+'.$i.' weeks');
            $key = $date->format('oW');
            $result[] = [
                'label' => 'S'.$date->format('W').' '.$date->format('o'),
                'total' => $indexed[$key] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @return array{loyal:int,inactive:int,at_risk:int,new:int}
     */
    public function getUserSegmentationStats(): array
    {
        $sql = <<<SQL
SELECT
    SUM(CASE WHEN seg.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_users,
    SUM(CASE WHEN seg.last_activity < DATE_SUB(NOW(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS inactive_users,
    SUM(CASE
            WHEN seg.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND seg.last_activity >= DATE_SUB(NOW(), INTERVAL 60 DAY)
             AND seg.last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)
            THEN 1 ELSE 0
        END) AS at_risk_users,
    SUM(CASE
            WHEN seg.activity_count >= 5
             AND seg.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            THEN 1 ELSE 0
        END) AS loyal_users
FROM (
    SELECT
        u.id,
        u.created_at,
        (
            COALESCE(d.cnt, 0) +
            COALESCE(r.cnt, 0) +
            COALESCE(c.cnt, 0) +
            COALESCE(fr.cnt, 0) +
            COALESCE(e.cnt, 0)
        ) AS activity_count,
        GREATEST(
            u.created_at,
            COALESCE(d.last_at, '1970-01-01 00:00:00'),
            COALESCE(r.last_at, '1970-01-01 00:00:00'),
            COALESCE(c.last_at, '1970-01-01 00:00:00'),
            COALESCE(fr.last_at, '1970-01-01 00:00:00'),
            COALESCE(e.last_at, '1970-01-01 00:00:00')
        ) AS last_activity
    FROM user u
    LEFT JOIN (
        SELECT donateur_id AS uid, COUNT(*) AS cnt, MAX(date_don) AS last_at
        FROM donation
        GROUP BY donateur_id
    ) d ON d.uid = u.id
    LEFT JOIN (
        SELECT participant_id AS uid, COUNT(*) AS cnt, MAX(date_reservation) AS last_at
        FROM reservation
        GROUP BY participant_id
    ) r ON r.uid = u.id
    LEFT JOIN (
        SELECT user_id AS uid, COUNT(*) AS cnt, MAX(date_commande) AS last_at
        FROM commande
        GROUP BY user_id
    ) c ON c.uid = u.id
    LEFT JOIN (
        SELECT auteur_id AS uid, COUNT(*) AS cnt, MAX(date_reponse) AS last_at
        FROM forum_reponse
        GROUP BY auteur_id
    ) fr ON fr.uid = u.id
    LEFT JOIN (
        SELECT organisateur_id AS uid, COUNT(*) AS cnt, MAX(created_at) AS last_at
        FROM evenement
        GROUP BY organisateur_id
    ) e ON e.uid = u.id
) seg
SQL;

        $row = $this->getEntityManager()->getConnection()
            ->executeQuery($sql)
            ->fetchAssociative();

        if (!is_array($row)) {
            return ['loyal' => 0, 'inactive' => 0, 'at_risk' => 0, 'new' => 0];
        }

        return [
            'loyal' => (int) ($row['loyal_users'] ?? 0),
            'inactive' => (int) ($row['inactive_users'] ?? 0),
            'at_risk' => (int) ($row['at_risk_users'] ?? 0),
            'new' => (int) ($row['new_users'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     email:string,
     *     nom:string,
     *     prenom:string,
     *     activity_count:int,
     *     last_activity: string|null
     * }>
     */
    public function getTopActiveUsers(int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $sql = <<<SQL
SELECT
    seg.id,
    seg.email,
    seg.nom,
    seg.prenom,
    seg.activity_count,
    seg.last_activity
FROM (
    SELECT
        u.id,
        u.email,
        u.nom,
        u.prenom,
        (
            COALESCE(d.cnt, 0) +
            COALESCE(r.cnt, 0) +
            COALESCE(c.cnt, 0) +
            COALESCE(fr.cnt, 0) +
            COALESCE(e.cnt, 0)
        ) AS activity_count,
        GREATEST(
            u.created_at,
            COALESCE(d.last_at, '1970-01-01 00:00:00'),
            COALESCE(r.last_at, '1970-01-01 00:00:00'),
            COALESCE(c.last_at, '1970-01-01 00:00:00'),
            COALESCE(fr.last_at, '1970-01-01 00:00:00'),
            COALESCE(e.last_at, '1970-01-01 00:00:00')
        ) AS last_activity
    FROM user u
    LEFT JOIN (
        SELECT donateur_id AS uid, COUNT(*) AS cnt, MAX(date_don) AS last_at
        FROM donation
        GROUP BY donateur_id
    ) d ON d.uid = u.id
    LEFT JOIN (
        SELECT participant_id AS uid, COUNT(*) AS cnt, MAX(date_reservation) AS last_at
        FROM reservation
        GROUP BY participant_id
    ) r ON r.uid = u.id
    LEFT JOIN (
        SELECT user_id AS uid, COUNT(*) AS cnt, MAX(date_commande) AS last_at
        FROM commande
        GROUP BY user_id
    ) c ON c.uid = u.id
    LEFT JOIN (
        SELECT auteur_id AS uid, COUNT(*) AS cnt, MAX(date_reponse) AS last_at
        FROM forum_reponse
        GROUP BY auteur_id
    ) fr ON fr.uid = u.id
    LEFT JOIN (
        SELECT organisateur_id AS uid, COUNT(*) AS cnt, MAX(created_at) AS last_at
        FROM evenement
        GROUP BY organisateur_id
    ) e ON e.uid = u.id
) seg
ORDER BY seg.activity_count DESC, seg.last_activity DESC
LIMIT :lim
SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['lim' => $limit], ['lim' => \Doctrine\DBAL\ParameterType::INTEGER])
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'email' => (string) ($row['email'] ?? ''),
            'nom' => (string) ($row['nom'] ?? ''),
            'prenom' => (string) ($row['prenom'] ?? ''),
            'activity_count' => (int) ($row['activity_count'] ?? 0),
            'last_activity' => isset($row['last_activity']) ? (string) $row['last_activity'] : null,
        ], $rows);
    }
>>>>>>> c4d1c44b0746a7387dc28bd3111400a167bda2d9
}
