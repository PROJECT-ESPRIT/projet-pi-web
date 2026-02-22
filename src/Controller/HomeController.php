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
    ): Response {
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
                $registeredEventIds = $reservationRepository->getEventIdsWithReservationFor($user);
                $totalRegistered = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => $registeredEventIds ?: [-1], 'exclude_event_ids' => null]));
                $totalOthers = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => null, 'exclude_event_ids' => $registeredEventIds]));
                $totalAll = $evenementRepository->countByFilters(array_merge($baseFilters, ['event_ids' => null, 'exclude_event_ids' => null]));
            }
        }

        $featuredEvents = $isArtist && count($myEvents) > 0
            ? array_slice($myEvents, 0, 4)
            : array_slice($allUpcoming, 0, 4);

        // Home events section: only 3 events; full list is on /events
        $latestThree = array_slice($allUpcoming, 0, 3);
        $myEventsThree = $isArtist ? array_values(array_filter($latestThree, fn ($ev) => $ev->getOrganisateur() && $ev->getOrganisateur()->getId() === $user->getId())) : [];
        $otherEventsThree = $isArtist ? array_values(array_filter($latestThree, fn ($ev) => !$ev->getOrganisateur() || $ev->getOrganisateur()->getId() !== $user->getId())) : [];

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'isArtist' => $isArtist,
            'latestEvents' => $latestThree,
            'featuredEvents' => $featuredEvents,
            'myEvents' => $myEventsThree,
            'otherEvents' => $otherEventsThree,
            'total_mine' => $totalMine,
            'total_others' => $totalOthers,
            'total_all' => $totalAll,
            'total_registered' => $totalRegistered,
            'registered_event_ids' => $registeredEventIds,
            'latestProduits' => $produitRepository->findBy([], ['id' => 'DESC'], 4),
            'latestForums' => $forumRepository->findBy([], ['dateCreation' => 'DESC'], 3),
        ]);
    }
}
