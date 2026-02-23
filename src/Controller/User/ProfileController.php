<?php

namespace App\Controller\User;

use App\Form\User\ParticipantProfileType;
use App\Repository\EvenementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    #[Route('/participant/profile', name: 'participant_profile')]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function participant(): Response
    {
        return $this->render('profile/participant.html.twig');
    }

    #[Route('/participant/profile/edit', name: 'participant_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function participantEdit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $originalBirthDate = $user->getDateNaissance();
        $birthDateLocked = $originalBirthDate !== null;

        $form = $this->createForm(ParticipantProfileType::class, $user, [
            'birthdate_locked' => $birthDateLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($birthDateLocked && $user->getDateNaissance() != $originalBirthDate) {
                $user->setDateNaissance($originalBirthDate);
                $this->addFlash('danger', 'La date de naissance ne peut etre modifiee qu une seule fois.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('participant_profile');
        }

        return $this->render('profile/participant_edit.html.twig', [
            'form' => $form,
            'profile_role_label' => 'Participant',
            'profile_back_route' => 'participant_profile',
        ]);
    }

    #[Route('/artist/profile', name: 'artist_profile')]
    #[IsGranted('ROLE_ARTISTE')]
    public function artist(EvenementRepository $evenementRepository, ReservationRepository $reservationRepository): Response
    {
        $artist = $this->getUser();
        $overview = $evenementRepository->getArtistStatsOverview($artist);
        $topEvents = $evenementRepository->getTopEventsForArtist($artist, 5);
        $reservationsTotal = $reservationRepository->countForOwnerEvents($artist);

        $events = $evenementRepository->findBy(['organisateur' => $artist]);
        $totalPlaces = 0;
        foreach ($events as $event) {
            $totalPlaces += (int) ($event->getNbPlaces() ?? 0);
        }
        $fillRate = $totalPlaces > 0 ? round(($overview['totalReservations'] / $totalPlaces) * 100, 1) : 0.0;

        $statusCounts = ['CONFIRMED' => 0, 'PENDING' => 0, 'CANCELLED' => 0];
        foreach ($events as $event) {
            foreach ($event->getReservations() as $reservation) {
                $status = $reservation->getStatus();
                if (!isset($statusCounts[$status])) {
                    $statusCounts[$status] = 0;
                }
                $statusCounts[$status]++;
            }
        }

        $months = [];
        $monthlyCounts = [];
        $monthlyMap = [];
        $cursor = (new \DateTimeImmutable('first day of this month'))->modify('-5 months');
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $months[] = $cursor->format('M');
            $monthlyCounts[] = 0;
            $monthlyMap[$key] = $i;
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($events as $event) {
            foreach ($event->getReservations() as $reservation) {
                $date = $reservation->getDateReservation();
                if ($date === null) {
                    continue;
                }
                $key = $date->format('Y-m');
                if (isset($monthlyMap[$key])) {
                    $monthlyCounts[$monthlyMap[$key]]++;
                }
            }
        }

        return $this->render('profile/artist.html.twig', [
            'overview' => $overview,
            'top_events' => $topEvents,
            'reservations_total' => $reservationsTotal,
            'total_places' => $totalPlaces,
            'fill_rate' => $fillRate,
            'status_counts' => $statusCounts,
            'monthly_labels' => $months,
            'monthly_counts' => $monthlyCounts,
        ]);
    }

    #[Route('/artist/profile/edit', name: 'artist_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function artistEdit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $originalBirthDate = $user->getDateNaissance();
        $birthDateLocked = $originalBirthDate !== null;

        $form = $this->createForm(ParticipantProfileType::class, $user, [
            'birthdate_locked' => $birthDateLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($birthDateLocked && $user->getDateNaissance() != $originalBirthDate) {
                $user->setDateNaissance($originalBirthDate);
                $this->addFlash('danger', 'La date de naissance ne peut etre modifiee qu une seule fois.');
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour.');

            return $this->redirectToRoute('artist_profile');
        }

        return $this->render('profile/participant_edit.html.twig', [
            'form' => $form,
            'profile_role_label' => 'Artiste',
            'profile_back_route' => 'artist_profile',
        ]);
    }
}
