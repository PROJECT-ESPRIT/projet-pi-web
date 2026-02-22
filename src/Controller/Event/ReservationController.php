<?php

namespace App\Controller\Event;

use App\Entity\Evenement;
use App\Service\EmailService;
use App\Service\StripeService;
use App\Service\TicketPdfService;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private EmailService $emailService,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $params,
    ) {
    }

    /**
     * Public scan endpoint: scan QR → mark ticket as scanned, redirect to event.
     * Token is required (HMAC of reservation id with app secret).
     */
    #[Route('/{id}/scan', name: 'app_reservation_scan', methods: ['GET'])]
    public function scan(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->query->get('token', '');
        $secret = $this->params->get('kernel.secret');
        $expected = hash_hmac('sha256', (string) $reservation->getId(), $secret);

        if (!hash_equals($expected, $token)) {
            $this->addFlash('danger', 'Lien de scan invalide ou expiré.');
            return $this->redirectToRoute('home');
        }

        if ($reservation->getScannedAt() === null) {
            $reservation->setScannedAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        $this->addFlash('success', 'Billet scanné avec succès.');
        return $this->redirectToRoute('app_evenement_show', ['id' => $reservation->getEvenement()->getId()]);
    }

    #[Route('/{id}/ticket', name: 'app_reservation_ticket', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadTicket(Reservation $reservation, TicketPdfService $ticketPdfService): Response
    {
        $user = $this->getUser();
        $isOwner = $reservation->getParticipant() === $user;
        $isEventOrganizer = $reservation->getEvenement()->getOrganisateur() === $user;
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isOwner && !$isEventOrganizer && !$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        $pdf = $ticketPdfService->generatePdf($reservation);
        $filename = 'ticket-artconnect-' . $reservation->getId() . '.pdf';

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    #[Route('/payment-success', name: 'app_reservation_payment_success', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function paymentSuccess(Request $request, ReservationRepository $reservationRepository): Response
    {
        $sessionId = trim((string) $request->query->get('session_id', ''));
        if ($sessionId !== '') {
            $reservation = $reservationRepository->findOneBy(['stripeCheckoutSessionId' => $sessionId]);
            if ($reservation instanceof Reservation) {
                $this->addFlash('success', 'Paiement validé. Votre réservation est confirmée et un email vous a été envoyé.');
                return $this->redirectToRoute('app_evenement_show', ['id' => $reservation->getEvenement()->getId()]);
            }
        }

        $this->addFlash('info', 'Paiement validé. Votre réservation sera confirmée dans quelques instants (webhook Stripe). Vous pouvez rafraîchir la page de l’événement ou consulter Mes réservations.');
        return $this->redirectToRoute('app_reservation_my');
    }

    #[Route('/payment-cancelled/{id}', name: 'app_reservation_payment_cancelled', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function paymentCancelled(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if ($reservation->getParticipant() !== $user) {
            throw $this->createAccessDeniedException('Cette réservation ne vous appartient pas.');
        }
        if ($reservation->getStatus() !== Reservation::STATUS_PENDING) {
            $this->addFlash('info', 'Cette réservation a déjà été traitée.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $reservation->getEvenement()->getId()]);
        }
        $eventId = $reservation->getEvenement()->getId();
        $entityManager->remove($reservation);
        $entityManager->flush();
        $this->addFlash('warning', 'Paiement annulé. Aucune réservation n’a été enregistrée. Vous pouvez réessayer si vous le souhaitez.');
        return $this->redirectToRoute('app_evenement_show', ['id' => $eventId]);
    }

    #[Route('/my-reservations', name: 'app_reservation_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin && $this->isGranted('ROLE_ARTISTE')) {
            $this->addFlash('info', 'La page "Mes Réservations" est réservée aux participants.');
            return $this->redirectToRoute('app_evenement_index');
        }

        $sortInput = trim((string) $request->query->get('sort', 'date_desc'));
        if ($sortInput === '') {
            $sortInput = 'date_desc';
        }

        $filterInput = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'date_start' => (string) $request->query->get('date_start', ''),
            'date_end' => (string) $request->query->get('date_end', ''),
            'sort' => $sortInput,
        ];

        $filters = [
            'q' => $filterInput['q'],
            'status' => $filterInput['status'],
            'date_start' => $this->parseDate($filterInput['date_start']),
            'date_end' => $this->parseDate($filterInput['date_end'], true),
            'sort' => $filterInput['sort'],
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10; // Paginate when reservations exceed 10
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

    #[Route('/artist-owner-reservations', name: 'app_reservation_artist_owner', methods: ['GET'])]
    #[IsGranted('ROLE_ARTISTE')]
    public function artistOwnerReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        $sortInput = trim((string) $request->query->get('sort', 'date_desc'));
        if ($sortInput === '') {
            $sortInput = 'date_desc';
        }

        $filterInput = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'date_start' => (string) $request->query->get('date_start', ''),
            'date_end' => (string) $request->query->get('date_end', ''),
            'sort' => $sortInput,
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
        $paginator = $reservationRepository->searchForOwnerEvents($filters, $page, $perPage, $this->getUser());
        $total = count($paginator);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $paginator = $reservationRepository->searchForOwnerEvents($filters, $page, $perPage, $this->getUser());
        }

        return $this->render('reservation/artist_owner_reservations.html.twig', [
            'reservations' => iterator_to_array($paginator, false),
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

        $ageMsg = $this->getAgeRestrictionMessage($evenement, $user);
        if ($ageMsg !== null) {
            $this->addFlash('warning', $ageMsg);
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $existingReservation = $reservationRepository->findOneBy([
            'evenement' => $evenement,
            'participant' => $user
        ]);

        if ($existingReservation) {
            $this->addFlash('warning', 'Vous avez déjà réservé une place pour cet événement.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $now = new \DateTime();
        if ($evenement->getDateDebut() < $now) {
            $this->addFlash('danger', 'Impossible de réserver un événement déjà passé.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($evenement->getReservations()->count() >= $evenement->getNbPlaces()) {
            $this->addFlash('danger', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $prix = $evenement->getPrix();
        $isPaid = $prix !== null && $prix > 0;

        if ($isPaid) {
            $reservation = new Reservation();
            $reservation->setEvenement($evenement);
            $reservation->setParticipant($user);
            $reservation->setStatus(Reservation::STATUS_PENDING);
            $reservation->setDateReservation(new \DateTimeImmutable());
            $entityManager->persist($reservation);
            $entityManager->flush();

            try {
                $successUrl = $this->urlGenerator->generate('app_reservation_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $cancelUrl = $this->urlGenerator->generate('app_reservation_payment_cancelled', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
                $checkoutUrl = $this->stripeService->createCheckoutSessionForEvent($evenement, $user, null, $successUrl, $cancelUrl, $reservation->getId());
                if ($checkoutUrl !== '') {
                    return $this->redirect($checkoutUrl);
                }
            } catch (\Throwable $e) {
                $entityManager->remove($reservation);
                $entityManager->flush();
                $this->addFlash('danger', 'Impossible d\'ouvrir la page de paiement. Vérifiez la configuration Stripe (clé secrète sk_ dans .env).');
                return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
            }
            $this->addFlash('danger', 'Paiement indisponible. Veuillez réessayer.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setEvenement($evenement);
        $reservation->setParticipant($user);
        $reservation->setStatus(Reservation::STATUS_CONFIRMED);
        $entityManager->persist($reservation);
        $entityManager->flush();
        $this->emailService->sendReservationConfirmationDetails($reservation);
        try {
            $this->emailService->sendReservationNotificationToOwner($reservation);
        } catch (\Throwable $e) {
            // log only; do not block user
        }
        $this->addFlash('success', 'Votre réservation a été confirmée ! Un email récapitulatif vous a été envoyé.');
        return $this->redirectToRoute('app_reservation_my');
    }

    #[Route('/{id}/book-seat', name: 'app_reservation_book_seat', methods: ['POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function bookSeat(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();
        $seat = trim((string) $request->request->get('seat', ''));

        $ageMsg = $this->getAgeRestrictionMessage($evenement, $user);
        if ($ageMsg !== null) {
            $this->addFlash('warning', $ageMsg);
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if (!$seat || !$evenement->getLayoutType()) {
            $this->addFlash('danger', 'Veuillez sélectionner une place sur le plan.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $existing = $reservationRepository->findOneBy([
            'evenement' => $evenement,
            'participant' => $user
        ]);
        if ($existing) {
            $this->addFlash('warning', 'Vous avez déjà réservé une place pour cet événement.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $seatTaken = $reservationRepository->findOneBy([
            'evenement' => $evenement,
            'seatLabel' => $seat
        ]);
        if ($seatTaken) {
            $this->addFlash('danger', 'Cette place est déjà prise. Veuillez en choisir une autre.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $now = new \DateTime();
        if ($evenement->getDateDebut() < $now) {
            $this->addFlash('danger', 'Impossible de réserver un événement déjà passé.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($evenement->getReservations()->count() >= $evenement->getNbPlaces()) {
            $this->addFlash('danger', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $prix = $evenement->getPrix();
        $isPaid = $prix !== null && $prix > 0;

        if ($isPaid) {
            $reservation = new Reservation();
            $reservation->setEvenement($evenement);
            $reservation->setParticipant($user);
            $reservation->setSeatLabel($seat);
            $reservation->setStatus(Reservation::STATUS_PENDING);
            $reservation->setDateReservation(new \DateTimeImmutable());
            $entityManager->persist($reservation);
            $entityManager->flush();

            try {
                $successUrl = $this->urlGenerator->generate('app_reservation_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $cancelUrl = $this->urlGenerator->generate('app_reservation_payment_cancelled', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
                $checkoutUrl = $this->stripeService->createCheckoutSessionForEvent($evenement, $user, $seat, $successUrl, $cancelUrl, $reservation->getId());
                if ($checkoutUrl !== '') {
                    return $this->redirect($checkoutUrl);
                }
            } catch (\Throwable $e) {
                $entityManager->remove($reservation);
                $entityManager->flush();
                $this->addFlash('danger', 'Impossible d\'ouvrir la page de paiement. Vérifiez la configuration Stripe (clé secrète sk_ dans .env).');
                return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
            }
            $this->addFlash('danger', 'Paiement indisponible. Veuillez réessayer.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setEvenement($evenement);
        $reservation->setParticipant($user);
        $reservation->setSeatLabel($seat);
        $reservation->setStatus(Reservation::STATUS_CONFIRMED);
        $entityManager->persist($reservation);
        $entityManager->flush();
        $this->emailService->sendReservationConfirmationDetails($reservation);
        try {
            $this->emailService->sendReservationNotificationToOwner($reservation);
        } catch (\Throwable $e) {
            // log only; do not block user
        }
        $this->addFlash('success', sprintf('Votre place %s a été réservée avec succès ! Un email récapitulatif vous a été envoyé.', $seat));
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/pay', name: 'app_reservation_pay', methods: ['POST'])]
    #[IsGranted('ROLE_PARTICIPANT')]
    public function pay(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if ($reservation->getParticipant() !== $user) {
            throw $this->createAccessDeniedException('Cette réservation ne vous appartient pas.');
        }
        if ($reservation->getStatus() !== Reservation::STATUS_PENDING) {
            $this->addFlash('info', 'Cette réservation n’est plus en attente de paiement.');
            return $this->redirectToRoute('app_reservation_my');
        }
        $evenement = $reservation->getEvenement();
        if ($evenement->getDateDebut() < new \DateTime()) {
            $this->addFlash('danger', 'Impossible de payer : l’événement est déjà passé.');
            return $this->redirectToRoute('app_reservation_my');
        }
        $prix = $evenement->getPrix();
        if ($prix === null || $prix <= 0) {
            $this->addFlash('danger', 'Cet événement est gratuit ; la réservation ne nécessite pas de paiement.');
            return $this->redirectToRoute('app_reservation_my');
        }

        try {
            $successUrl = $this->urlGenerator->generate('app_reservation_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $cancelUrl = $this->urlGenerator->generate('app_reservation_my', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $checkoutUrl = $this->stripeService->createCheckoutSessionForEvent(
                $evenement,
                $user,
                $reservation->getSeatLabel(),
                $successUrl,
                $cancelUrl,
                $reservation->getId()
            );
            if ($checkoutUrl !== '') {
                return $this->redirect($checkoutUrl);
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Impossible d\'ouvrir la page de paiement. Réessayez plus tard.');
            return $this->redirectToRoute('app_reservation_my');
        }

        return $this->redirectToRoute('app_reservation_my');
    }

    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $currentUser = $this->getUser();
        
        if (!$isAdmin && $reservation->getParticipant() !== $currentUser) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits nécessaires pour effectuer cette action.');
        }

        $eventTitle = $reservation->getEvenement()->getTitre();
        
        if ($reservation->getEvenement()->getDateDebut() < new \DateTime() && !$isAdmin) {
            $this->addFlash('error', 'Impossible d\'annuler une réservation pour un événement déjà passé.');
            return $this->redirectToRoute('app_reservation_my');
        }

        // Notify owner (participant X cancelled) and participant (confirmation + refund contact if paid)
        try {
            $this->emailService->sendReservationCancelledByUserToOwner($reservation);
            $this->emailService->sendReservationCancelledByUserToParticipant($reservation);
        } catch (\Throwable $e) {
            // Log but do not block cancellation
            $this->addFlash('warning', 'La réservation a été annulée mais l\'envoi d\'un email de notification a échoué.');
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

    /**
     * Returns a message if the user is not allowed to reserve due to event age limits; null if allowed.
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
