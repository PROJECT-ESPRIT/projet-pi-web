<?php

namespace App\Controller\Event;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\Event\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository, ReservationRepository $reservationRepository): Response
    {
        $filterInput = [
            'q' => trim((string) $request->query->get('q', '')),
            'lieu' => trim((string) $request->query->get('lieu', '')),
            'date_start' => (string) $request->query->get('date_start', ''),
            'date_end' => (string) $request->query->get('date_end', ''),
            'prix_min' => (string) $request->query->get('prix_min', ''),
            'prix_max' => (string) $request->query->get('prix_max', ''),
            'sort' => (string) $request->query->get('sort', 'date_asc'),
            'scope' => (string) $request->query->get('scope', ''),
        ];

        $filters = [
            'q' => $filterInput['q'],
            'lieu' => $filterInput['lieu'],
            'date_start' => $this->parseDate($filterInput['date_start']),
            'date_end' => $this->parseDate($filterInput['date_end'], true),
            'prix_min' => $this->parseFloat($filterInput['prix_min']),
            'prix_max' => $this->parseFloat($filterInput['prix_max']),
            'sort' => $filterInput['sort'],
        ];

        $user = $this->getUser();
        $isArtist = $user && \in_array('ROLE_ARTISTE', $user->getRoles(), true);
        $registeredEventIds = [];
        $totalMine = 0;
        $totalOthers = 0;
        $totalAll = 0;
        $totalRegistered = 0;

        if (!$this->isGranted('ROLE_ADMIN') && $user) {
            if ($isArtist) {
                $scope = \in_array($filterInput['scope'], ['mine', 'others', 'all'], true) ? $filterInput['scope'] : 'mine';
                $filterInput['scope'] = $scope;
                $filters['owner_id'] = ($scope === 'mine') ? $user->getId() : null;
                $filters['exclude_owner_id'] = ($scope === 'others') ? $user->getId() : null;
                $totalMine = $evenementRepository->countByFilters(array_merge($filters, ['owner_id' => $user->getId(), 'exclude_owner_id' => null]));
                $totalOthers = $evenementRepository->countByFilters(array_merge($filters, ['owner_id' => null, 'exclude_owner_id' => $user->getId()]));
                $totalAll = $evenementRepository->countByFilters(array_merge($filters, ['owner_id' => null, 'exclude_owner_id' => null]));
            } else {
                $registeredEventIds = $reservationRepository->getEventIdsWithReservationFor($user);
                $scope = \in_array($filterInput['scope'], ['others', 'all'], true) ? $filterInput['scope'] : 'all';
                $filterInput['scope'] = $scope;
                $filters['event_ids'] = null;
                $filters['exclude_event_ids'] = ($scope === 'others' && $registeredEventIds) ? $registeredEventIds : null;
                $totalOthers = $evenementRepository->countByFilters(array_merge($filters, ['event_ids' => null, 'exclude_event_ids' => $registeredEventIds]));
                $totalAll = $evenementRepository->countByFilters(array_merge($filters, ['event_ids' => null, 'exclude_event_ids' => null]));
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $paginator = $evenementRepository->searchAndSort($filters, $page, $perPage);
        $total = count($paginator);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
            $paginator = $evenementRepository->searchAndSort($filters, $page, $perPage);
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => iterator_to_array($paginator, false),
            'filters' => $filters,
            'filter_input' => $filterInput,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'is_artist' => $isArtist,
            'scope' => $filterInput['scope'] ?? 'all',
            'total_mine' => $totalMine,
            'total_others' => $totalOthers,
            'total_all' => $totalAll,
            'total_registered' => $totalRegistered,
            'registered_event_ids' => $registeredEventIds,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($evenement->getLayoutType() && $evenement->getLayoutRows() && $evenement->getLayoutCols()) {
                $evenement->setNbPlaces($evenement->getLayoutRows() * $evenement->getLayoutCols());
            }
            $evenement->setOrganisateur($this->getUser());
            $entityManager->persist($evenement);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès !');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement, ReservationRepository $reservationRepository): Response
    {
        $hasReserved = false;
        $userReservation = null;
        $user = $this->getUser();
        if ($user) {
            $userReservation = $reservationRepository->findOneBy([
                'evenement' => $evenement,
                'participant' => $user,
            ]);
            $hasReserved = $userReservation !== null;
        }

        $ageRestrictionMessage = $this->getAgeRestrictionMessage($evenement, $user);

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'hasReserved' => $hasReserved,
            'userReservation' => $userReservation,
            'age_restriction_message' => $ageRestrictionMessage,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($evenement->getOrganisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet événement.');
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($evenement->getLayoutType() && $evenement->getLayoutRows() && $evenement->getLayoutCols()) {
                $evenement->setNbPlaces($evenement->getLayoutRows() * $evenement->getLayoutCols());
            }
            $entityManager->flush();

            $this->addFlash('success', 'Événement modifié avec succès !');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_evenement_cancel', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function cancel(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        if ($evenement->getOrganisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à annuler cet événement.');
        }
        if ($evenement->isAnnule()) {
            $this->addFlash('warning', 'Cet événement est déjà annulé.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('cancel' . $evenement->getId(), $request->request->get('_token'))) {
            $reason = trim((string) $request->request->get('motif_annulation', ''));
            if ($reason === '') {
                $this->addFlash('danger', 'Veuillez indiquer le motif d\'annulation.');
                return $this->render('evenement/cancel.html.twig', ['evenement' => $evenement]);
            }

            $evenement->setAnnule(true);
            $evenement->setMotifAnnulation($reason);
            $evenement->setDateAnnulation(new \DateTimeImmutable());
            $entityManager->flush();

            $owner = $evenement->getOrganisateur();
            $eventTitle = $evenement->getTitre();
            $ownerEmail = $owner ? $owner->getEmail() : '';
            $ownerPhone = $owner ? $owner->getTelephone() : null;
            $isPaid = $evenement->getPrix() !== null && $evenement->getPrix() > 0;
            foreach ($evenement->getReservations() as $reservation) {
                $p = $reservation->getParticipant();
                if ($p && $p->getEmail()) {
                    try {
                        $emailService->sendEventCancelledByArtistToParticipant(
                            $p->getEmail(),
                            trim($p->getPrenom() . ' ' . $p->getNom()) ?: 'Participant',
                            $eventTitle,
                            $ownerEmail,
                            $ownerPhone,
                            $isPaid,
                            $reason
                        );
                    } catch (\Throwable $e) {
                        // continue
                    }
                }
            }

            $this->addFlash('success', 'L\'événement a été annulé. Les participants ont été notifiés par email.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/cancel.html.twig', ['evenement' => $evenement]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($evenement->getOrganisateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cet événement.');
        }

        $reservationsCount = $evenement->getReservations()->count();
        if ($reservationsCount > 0) {
            $this->addFlash('danger', 'Impossible de supprimer un événement qui possède au moins une réservation. Annulez l\'événement à la place (les participants seront notifiés).');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé avec succès !');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/artist/stats', name: 'app_artist_event_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function artistStats(EvenementRepository $evenementRepository, ReservationRepository $reservationRepository): Response
    {
        $artist = $this->getUser();
        $overview = $evenementRepository->getArtistStatsOverview($artist);
        $topEvents = $evenementRepository->getTopEventsForArtist($artist, 5);
        $events = $evenementRepository->findBy(['organisateur' => $artist], ['dateDebut' => 'ASC']);

        $now = new \DateTimeImmutable();
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $now->modify('last day of this month')->setTime(23, 59, 59);
        $lastMonthStart = $monthStart->modify('-1 month');
        $lastMonthEnd = $monthStart->modify('-1 second');

        $eventsThisMonth = 0;
        $eventsLastMonth = 0;
        $totalPlaces = 0;
        $totalRevenuePotential = 0.0;
        $totalRevenueActual = 0.0;
        $statusCounts = ['CONFIRMED' => 0, 'PENDING' => 0, 'CANCELLED' => 0];
        $freeEvents = 0;
        $paidEvents = 0;
        $dayOfWeekCounts = [0, 0, 0, 0, 0, 0, 0]; // Mon-Sun
        $dayOfWeekLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $locationCounts = [];
        $nextEvent = null;
        $eventFillData = [];
        $uniqueParticipants = [];
        $resThisMonth = 0;
        $resLastMonth = 0;

        $monthlyMap = [];
        $monthlyLabels = [];
        $monthlyCounts = [];
        $monthlyEventCounts = [];
        $cursor = (new \DateTimeImmutable('first day of this month'))->modify('-5 months');
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $monthlyLabels[] = $cursor->format('M');
            $monthlyCounts[] = 0;
            $monthlyEventCounts[] = 0;
            $monthlyMap[$key] = $i;
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($events as $event) {
            $createdAt = $event->getCreatedAt();
            if ($createdAt !== null) {
                if ($createdAt >= $monthStart && $createdAt <= $monthEnd) {
                    $eventsThisMonth++;
                }
                if ($createdAt >= $lastMonthStart && $createdAt <= $lastMonthEnd) {
                    $eventsLastMonth++;
                }
                $ck = $createdAt->format('Y-m');
                if (isset($monthlyMap[$ck])) {
                    $monthlyEventCounts[$monthlyMap[$ck]]++;
                }
            }

            $places = (int) ($event->getNbPlaces() ?? 0);
            $totalPlaces += $places;
            $prix = $event->getPrix() ?? 0;
            $totalRevenuePotential += $prix * $places;

            if ($prix > 0) {
                $paidEvents++;
            } else {
                $freeEvents++;
            }

            if ($event->getDateDebut() !== null) {
                $dow = ((int) $event->getDateDebut()->format('N')) - 1;
                $dayOfWeekCounts[$dow]++;
                if ($event->getDateDebut() >= $now && $nextEvent === null) {
                    $nextEvent = $event;
                }
            }

            $lieu = $event->getLieu();
            if ($lieu) {
                $lieuKey = mb_strtolower(trim($lieu));
                $locationCounts[$lieuKey] = ($locationCounts[$lieuKey] ?? 0) + 1;
            }

            $resCount = 0;
            foreach ($event->getReservations() as $res) {
                $status = $res->getStatus();
                if (!isset($statusCounts[$status])) {
                    $statusCounts[$status] = 0;
                }
                $statusCounts[$status]++;
                if ($status === 'CONFIRMED') {
                    $totalRevenueActual += $prix;
                    $resCount++;
                }
                $resDate = $res->getDateReservation();
                if ($resDate !== null) {
                    $resKey = $resDate->format('Y-m');
                    if (isset($monthlyMap[$resKey])) {
                        $monthlyCounts[$monthlyMap[$resKey]]++;
                    }
                    if ($resDate >= $monthStart && $resDate <= $monthEnd) {
                        $resThisMonth++;
                    }
                    if ($resDate >= $lastMonthStart && $resDate <= $lastMonthEnd) {
                        $resLastMonth++;
                    }
                }
                $participant = $res->getParticipant();
                if ($participant) {
                    $uniqueParticipants[$participant->getId()] = true;
                }
            }

            $eventFillData[] = [
                'titre' => $event->getTitre(),
                'id' => $event->getId(),
                'places' => $places,
                'reserved' => $event->getReservations()->count(),
                'pct' => $places > 0 ? round($event->getReservations()->count() / $places * 100) : 0,
                'prix' => $prix,
            ];
        }

        $fillRate = $totalPlaces > 0 ? round(($overview['totalReservations'] / $totalPlaces) * 100, 1) : 0.0;

        arsort($locationCounts);
        $topLocations = array_slice($locationCounts, 0, 5, true);

        usort($eventFillData, fn($a, $b) => $b['pct'] - $a['pct']);
        $eventFillData = array_slice($eventFillData, 0, 8);

        $resGrowth = $resLastMonth > 0 ? round(($resThisMonth - $resLastMonth) / $resLastMonth * 100, 1) : ($resThisMonth > 0 ? 100.0 : 0.0);
        $eventGrowth = $eventsLastMonth > 0 ? round(($eventsThisMonth - $eventsLastMonth) / $eventsLastMonth * 100, 1) : ($eventsThisMonth > 0 ? 100.0 : 0.0);

        return $this->render('evenement/artist_stats.html.twig', [
            'overview' => $overview,
            'events_this_month' => $eventsThisMonth,
            'events_last_month' => $eventsLastMonth,
            'event_growth' => $eventGrowth,
            'reservations_total' => $reservationRepository->countForOwnerEvents($artist),
            'res_this_month' => $resThisMonth,
            'res_last_month' => $resLastMonth,
            'res_growth' => $resGrowth,
            'top_events' => $topEvents,
            'total_places' => $totalPlaces,
            'fill_rate' => $fillRate,
            'revenue_potential' => $totalRevenuePotential,
            'revenue_actual' => $totalRevenueActual,
            'status_counts' => $statusCounts,
            'monthly_labels' => $monthlyLabels,
            'monthly_counts' => $monthlyCounts,
            'monthly_event_counts' => $monthlyEventCounts,
            'free_events' => $freeEvents,
            'paid_events' => $paidEvents,
            'day_of_week_counts' => $dayOfWeekCounts,
            'day_of_week_labels' => $dayOfWeekLabels,
            'top_locations' => $topLocations,
            'next_event' => $nextEvent,
            'event_fill_data' => $eventFillData,
            'unique_participants' => count($uniqueParticipants),
        ]);
    }

    private function parseDate(?string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        if ($endOfDay) {
            return $date->setTime(23, 59, 59);
        }

        return $date->setTime(0, 0, 0);
    }

    private function parseFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Returns a message if the user is not allowed to reserve due to age (event ageMin/ageMax).
     * Returns null if no restriction or user is allowed.
     */
    private function getAgeRestrictionMessage(Evenement $evenement, ?User $user): ?string
    {
        $ageMin = $evenement->getAgeMin();
        $ageMax = $evenement->getAgeMax();
        if ($ageMin === null && $ageMax === null) {
            return null;
        }
        if ($user === null) {
            return null;
        }
        $age = $user->getAge();
        if ($age === null) {
            return 'Pour réserver cet événement, veuillez renseigner votre date de naissance dans votre profil.';
        }
        if ($ageMin !== null && $age < $ageMin) {
            return sprintf('Cet événement est réservé aux personnes de %d ans et plus. Vous avez %d ans.', $ageMin, $age);
        }
        if ($ageMax !== null && $age > $ageMax) {
            return sprintf('Cet événement est réservé aux personnes de %d ans et moins. Vous avez %d ans.', $ageMax, $age);
        }
        return null;
    }
}
