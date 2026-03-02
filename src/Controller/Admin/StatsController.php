<?php

namespace App\Controller\Admin;

use App\Repository\CommandeRepository;
use App\Repository\CharityRepository;
use App\Repository\DonationRepository;
use App\Repository\EvenementRepository;
use App\Repository\ForumRepository;
use App\Repository\ForumReponseRepository;
use App\Repository\ProduitRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StatsController extends AbstractController
{
    #[Route('/admin/statistiques', name: 'admin_stats')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        ReservationRepository $reservationRepository,
        UserRepository $userRepository,
        EvenementRepository $evenementRepository,
        CommandeRepository $commandeRepository,
        DonationRepository $donationRepository,
        CharityRepository $charityRepository,
        ProduitRepository $produitRepository,
        ForumRepository $forumRepository,
        ForumReponseRepository $forumReponseRepository,
    ): Response {
        $reservationsByStatus = $reservationRepository->countByStatus();
        $totalReservations = $reservationRepository->count([]);
        $reservationsThisMonth = $reservationRepository->countThisMonth();
        $monthlyReservations = $reservationRepository->getMonthlyReservations(6);

        $usersByRole = $userRepository->getUsersByRole();
        $totalUsers = $userRepository->count([]);
        $newUsersThisMonth = $userRepository->countNewThisMonth();
        $monthlyRegistrations = $userRepository->getMonthlyRegistrations(6);

        $eventStats = $evenementRepository->getStatsOverview();
        $topEvents = $evenementRepository->getTopEvents(5);
        $monthlyEvents = $evenementRepository->getMonthlyEvents(6);

        $totalOrders = $commandeRepository->count([]);
        $totalDonations = $donationRepository->count([]);
        $totalCharities = $charityRepository->count([]);
        $totalProducts = $produitRepository->count([]);
        $totalForumPosts = $forumRepository->count([]);

        $orderRevenue = $commandeRepository->getTotalRevenue();
        $ordersByStatus = $commandeRepository->countByStatus();
        $monthlyOrders = $commandeRepository->getMonthlyOrders(6);
        $monthlyRevenue = $commandeRepository->getMonthlyRevenue(6);

        $donationsThisMonth = $donationRepository->countThisMonth(true);
        $monthlyDonations = $donationRepository->getMonthlyDonations(6, true);
        $donationsByType = $donationRepository->countByType(true);

        $charityRows = $charityRepository->findAllWithDonationCounts(true);
        $charityStats = array_map(static function (array $row): array {
            $charity = $row['charity'];
            $count = $row['donationsCount'];
            $amount = $row['donationsAmount'];
            $goal = $charity->getGoalAmount();

            return [
                'charity' => $charity,
                'donationsCount' => $count,
                'donationsAmount' => $amount,
                'progressPercent' => $goal ? round(($amount / $goal) * 100, 1) : 0,
                'hasGoal' => $goal !== null,
            ];
        }, $charityRows);
        usort($charityStats, static fn (array $a, array $b) => $b['donationsCount'] <=> $a['donationsCount']);

        $lowStockProducts = $produitRepository->countLowStock(5);
        $stockValue = $produitRepository->getTotalStockValue();

        $totalForumReplies = $forumReponseRepository->count([]);
        $monthlyForumPosts = $forumRepository->getMonthlyPosts(6);

        return $this->render('admin/stats.html.twig', [
            'totalReservations' => $totalReservations,
            'reservationsThisMonth' => $reservationsThisMonth,
            'reservationsByStatus' => $reservationsByStatus,
            'monthlyReservations' => $monthlyReservations,
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'usersByRole' => $usersByRole,
            'monthlyRegistrations' => $monthlyRegistrations,
            'eventStats' => $eventStats,
            'topEvents' => $topEvents,
            'monthlyEvents' => $monthlyEvents,
            'totalOrders' => $totalOrders,
            'totalDonations' => $totalDonations,
            'totalCharities' => $totalCharities,
            'totalProducts' => $totalProducts,
            'totalForumPosts' => $totalForumPosts,
            'orderRevenue' => $orderRevenue,
            'ordersByStatus' => $ordersByStatus,
            'monthlyOrders' => $monthlyOrders,
            'monthlyRevenue' => $monthlyRevenue,
            'donationsThisMonth' => $donationsThisMonth,
            'monthlyDonations' => $monthlyDonations,
            'donationsByType' => $donationsByType,
            'charityStats' => $charityStats,
            'lowStockProducts' => $lowStockProducts,
            'stockValue' => $stockValue,
            'totalForumReplies' => $totalForumReplies,
            'monthlyForumPosts' => $monthlyForumPosts,
        ]);
    }
}
