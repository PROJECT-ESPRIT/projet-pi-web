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
    public function myReservations(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/my_reservations.html.twig', [
            'reservations' => $reservationRepository->findBy(['participant' => $this->getUser()], ['dateReservation' => 'DESC']),
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

        // Check availability
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
        // Allow user to cancel their own reservation
        if ($reservation->getParticipant() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas annuler cette réservation.');
        }

        $entityManager->remove($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('app_reservation_my');
    }
}
