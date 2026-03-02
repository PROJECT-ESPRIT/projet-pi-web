<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Repository\ForumRepository;
use App\Repository\ProduitRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        EvenementRepository $evenementRepository,
        ReservationRepository $reservationRepository,
        ProduitRepository $produitRepository,
        ForumRepository $forumRepository,
        \App\Service\EventRecommendationService $eventRecommendationService
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_stats');
        }
        $user = $this->getUser();
        $allUpcoming = $evenementRepository->findBy([], ['dateDebut' => 'ASC'], 12);

        $myEvents = [];
        $otherEvents = [];
        $isArtist = false;
        $totalMine = 0;
        $totalOthers = 0;
        $totalAll = 0;
        $totalRegistered = 0;
        $registeredEventIds = [];

        $baseFilters = [
            'q' => '',
            'lieu' => '',
            'date_start' => null,
            'date_end' => null,
            'prix_min' => null,
            'prix_max' => null,
        ];

        $aiRecommendations = null;
        $aiHasHistory      = false;

        if ($user && !$this->isGranted('ROLE_ADMIN')) {
            if (in_array('ROLE_ARTISTE', $user->getRoles(), true)) {
                $isArtist = true;
                $totalMine = $evenementRepository->countByFilters(array_merge($baseFilters, ['owner_id' => $user->getId(), 'exclude_owner_id' => null]));
                $totalOthers = $evenementRepository->countByFilters(array_merge($baseFilters, ['owner_id' => null, 'exclude_owner_id' => $user->getId()]));
                $totalAll = $evenementRepository->countByFilters(array_merge($baseFilters, ['owner_id' => null, 'exclude_owner_id' => null]));
                foreach ($allUpcoming as $ev) {
                    if ($ev->getOrganisateur() && $ev->getOrganisateur()->getId() === $user->getId()) {
                        $myEvents[] = $ev;
                    } else {
                        $otherEvents[] = $ev;
                    }
                }
            } else {
                // Participant — run AI recommender
                $registeredEventIds = $reservationRepository->getEventIdsWithReservationFor($user);
                $totalRegistered = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => $registeredEventIds ?: [-1], 'exclude_event_ids' => null]));
                $totalOthers = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => null, 'exclude_event_ids' => $registeredEventIds]));
                $totalAll = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => null, 'exclude_event_ids' => null]));

                $aiResult = $this->runRecommender((int) $user->getId());
                if (!empty($aiResult['success']) && !empty($aiResult['recommendations'])) {
                    $aiRecommendations = $aiResult['recommendations'];
                    $aiHasHistory      = $aiResult['has_history'] ?? false;
                }
            }
        }

        $featuredEvents = $isArtist && count($myEvents) > 0
            ? array_slice($myEvents, 0, 4)
            : array_slice($allUpcoming, 0, 4);

        $latestThree      = array_slice($allUpcoming, 0, 3);
        $myEventsThree    = $isArtist ? array_values(array_filter($latestThree, fn ($ev) => $ev->getOrganisateur() && $ev->getOrganisateur()->getId() === $user->getId())) : [];
        $otherEventsThree = $isArtist ? array_values(array_filter($latestThree, fn ($ev) => !$ev->getOrganisateur() || $ev->getOrganisateur()->getId() !== $user->getId())) : [];

        // AI Event Recommendations
        $recommendedEvents = [];
        if ($user) {
            $recommendedEvents = $eventRecommendationService->getHybridRecommendations($user, 4);
        } else {
            // For guests, we can show popular events
            $recommendedEvents = $eventRecommendationService->getPopularEvents(4);
        }

        return $this->render('home/index.html.twig', [
            'user'                 => $user,
            'isArtist'             => $isArtist,
            'latestEvents'         => $latestThree,
            'featuredEvents'       => $featuredEvents,
            'recommendedEvents' => $recommendedEvents,
            'myEvents'             => $myEventsThree,
            'otherEvents'          => $otherEventsThree,
            'total_mine'           => $totalMine,
            'total_others'         => $totalOthers,
            'total_all'            => $totalAll,
            'total_registered'     => $totalRegistered,
            'registered_event_ids' => $registeredEventIds,
            'ai_recommendations'   => $aiRecommendations,
            'ai_has_history'       => $aiHasHistory,
            'latestProduits'       => $produitRepository->findBy([], ['id' => 'DESC'], 4),
            'latestForums'         => $forumRepository->findBy([], ['dateCreation' => 'DESC'], 3),
        ]);
    }

    private function runRecommender(int $userId): array
    {
        $script = $this->getParameter('kernel.project_dir') . '/python/event_recommender.py';
        if (!file_exists($script)) {
            return ['success' => false, 'recommendations' => []];
        }
        $python = $this->detectPython();
        $dbUrl  = $this->buildDbUrl();
        $cmd    = implode(' ', array_map('escapeshellarg', [$python, $script, '--user_id', (string) $userId, '--limit', '6', '--db_url', $dbUrl]));
        $out = []; $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        $raw     = trim(implode("\n", $out));
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : ['success' => false, 'recommendations' => []];
    }

    private function detectPython(): string
    {
        foreach (['C:\\Python312\\python.exe', 'C:\\Python311\\python.exe', 'C:\\Python310\\python.exe'] as $p) {
            if (file_exists($p)) return $p;
        }
        $lookups = PHP_OS_FAMILY === 'Windows' ? ['python', 'py'] : ['python3', 'python'];
        foreach ($lookups as $c) {
            $out = []; $code = 0;
            exec((PHP_OS_FAMILY === 'Windows' ? 'where.exe ' : 'which ') . escapeshellarg($c) . ' 2>NUL', $out, $code);
            if ($code === 0 && !empty($out) && !str_contains(trim($out[0]), 'WindowsApps')) {
                return trim($out[0]);
            }
        }
        return 'python';
    }

    private function buildDbUrl(): string
    {
        $raw = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? '';
        if ($raw === '') return 'mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web';
        return preg_replace('/\?.*$/', '', preg_replace('#^mysql://#', 'mysql+pymysql://', $raw));
    }
}
