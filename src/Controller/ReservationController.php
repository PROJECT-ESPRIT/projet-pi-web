<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/my-reservations', name: 'app_reservation_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $filterInput = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'date_start' => (string) $request->query->get('date_start', ''),
            'date_end' => (string) $request->query->get('date_end', ''),
            'sort' => (string) $request->query->get('sort', 'date_desc'),
        ];

        $filters = [
            'q' => $filterInput['q'],
            'status' => $filterInput['status'],
            'date_start' => $this->parseDate($filterInput['date_start']),
            'date_end' => $this->parseDate($filterInput['date_end'], true),
            'sort' => $filterInput['sort'],
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $paginator = $reservationRepository->searchAndSort($filters, $page, $perPage, $this->getUser(), $isAdmin);
        $total = count($paginator);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $paginator = $reservationRepository->searchAndSort($filters, $page, $perPage, $this->getUser(), $isAdmin);
        }

        return $this->render('reservation/my_reservations.html.twig', [
            'reservations' => iterator_to_array($paginator, false),
            'isAdmin' => $isAdmin,
            'pageTitle' => $isAdmin ? 'Gestion des Réservations' : 'Mes Réservations',
            'filter_input' => $filterInput,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/{id}/book', name: 'app_reservation_book', methods: ['POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function book(Evenement $evenement, EntityManagerInterface $entityManager, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();

        // Check if user already booked this event
        $existingReservation = $reservationRepository->findOneBy([
            'evenement' => $evenement,
            'participant' => $user
        ]);

        if ($existingReservation) {
            $this->addFlash('warning', 'Vous avez déjà réservé une place pour cet événement.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        // Vérifier si l'événement est déjà passé
        $now = new \DateTime();
        if ($evenement->getDateDebut() < $now) {
            $this->addFlash('danger', 'Impossible de réserver un événement déjà passé.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        // Vérifier la disponibilité des places
        if ($evenement->getReservations()->count() >= $evenement->getNbPlaces()) {
            $this->addFlash('danger', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setEvenement($evenement);
        $reservation->setParticipant($user);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée !');

        return $this->redirectToRoute('app_reservation_my');
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $currentUser = $this->getUser();
        
        // Allow admin to cancel any reservation, but regular users can only cancel their own
        if (!$isAdmin && $reservation->getParticipant() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits nécessaires pour effectuer cette action.');
        }

        // Store event title for flash message
        $eventTitle = $reservation->getEvenement()->getTitre();
        
        // If the event is in the past, don't allow cancellation
        if ($reservation->getEvenement()->getDateDebut() < new \DateTime() && !$isAdmin) {
            $this->addFlash('error', 'Impossible d\'annuler une réservation pour un événement déjà passé.');
            return $this->redirectToRoute('app_reservation_my');
        }

        $entityManager->remove($reservation);
        $entityManager->flush();

        if ($isAdmin && $reservation->getParticipant() !== $currentUser) {
            $this->addFlash('success', sprintf('La réservation pour l\'événement "%s" a été supprimée avec succès.', $eventTitle));
        } else {
            $this->addFlash('success', 'Votre réservation a été annulée avec succès.');
        }

        return $this->redirectToRoute('app_reservation_my');
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
}
