<?php

namespace App\Controller\Event;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe-webhook', name: 'app_stripe_webhook_', methods: ['POST'])]
class StripeWebhookController extends AbstractController
{
    private string $stripeWebhookSecret;

    public function __construct(
        ?string $stripeWebhookSecret,
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {
        $this->stripeWebhookSecret = $stripeWebhookSecret ?? '';
    }

    #[Route('', name: 'handle', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        $this->logger->info('Stripe webhook received', [
            'content_length' => \strlen($payload),
            'has_signature' => $sigHeader !== '',
            'webhook_secret_configured' => $this->stripeWebhookSecret !== '',
        ]);

        if ($payload === '') {
            $this->logger->warning('Stripe webhook: empty payload (raw body may have been consumed elsewhere)');
            return new Response('Empty payload', Response::HTTP_BAD_REQUEST);
        }

        if ($sigHeader === '') {
            $this->logger->warning('Stripe webhook: missing Stripe-Signature header');
            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        if ($this->stripeWebhookSecret === '') {
            $this->logger->error('Stripe webhook: STRIPE_WEBHOOK_SECRET is not set');
            return new Response('Webhook not configured', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException $e) {
            $this->logger->warning('Stripe webhook: invalid signature', ['message' => $e->getMessage()]);
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        } catch (\UnexpectedValueException $e) {
            $this->logger->warning('Stripe webhook: invalid payload', ['message' => $e->getMessage()]);
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Stripe webhook: event parsed', [
            'event_id' => $event->id ?? null,
            'event_type' => $event->type ?? null,
        ]);

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event->data->object);
        } else {
            $this->logger->info('Stripe webhook: event type ignored', ['event_type' => $event->type]);
        }

        return new Response('', Response::HTTP_OK);
    }

    private function handleCheckoutSessionCompleted(\Stripe\Checkout\Session $session): void
    {
        $metadata = $session->metadata ?? null;
        $reservationId = $metadata->reservation_id ?? null;
        $eventId = $metadata->event_id ?? null;
        $userId = $metadata->user_id ?? null;
        $seatLabel = trim((string) ($metadata->seat_label ?? ''));

        $this->logger->info('Stripe webhook: checkout.session.completed', [
            'session_id' => $session->id ?? null,
            'reservation_id_metadata' => $reservationId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'amount_total' => $session->amount_total ?? null,
        ]);

        $reservation = null;

        if ($reservationId) {
            $reservation = $this->reservationRepository->find((int) $reservationId);
            if ($reservation instanceof Reservation && $reservation->getStatus() === Reservation::STATUS_PENDING && $reservation->getStripeCheckoutSessionId() === null) {
                $amountTotal = $session->amount_total ?? 0;
                $reservation->setStatus(Reservation::STATUS_CONFIRMED);
                $reservation->setStripeCheckoutSessionId($session->id);
                $reservation->setAmountPaid((int) $amountTotal);
                $this->entityManager->flush();
                $this->logger->info('Stripe webhook: reservation confirmed (legacy)', ['reservation_id' => $reservationId]);
            } else {
                $reservation = null;
            }
        }

        if (!$reservation && $eventId && $userId) {
            $evenement = $this->entityManager->find(Evenement::class, (int) $eventId);
            $user = $this->entityManager->find(User::class, (int) $userId);
            if (!$evenement instanceof Evenement || !$user instanceof User) {
                $this->logger->warning('Stripe webhook: event or user not found', ['event_id' => $eventId, 'user_id' => $userId]);
                return;
            }
            $existing = $this->reservationRepository->findOneBy(['evenement' => $evenement, 'participant' => $user]);
            if ($existing) {
                $this->logger->info('Stripe webhook: reservation already exists for event+user', ['event_id' => $eventId, 'user_id' => $userId]);
                return;
            }
            if ($evenement->getLayoutType() && $seatLabel !== '') {
                $seatTaken = $this->reservationRepository->findOneBy(['evenement' => $evenement, 'seatLabel' => $seatLabel]);
                if ($seatTaken) {
                    $this->logger->warning('Stripe webhook: seat already taken', ['event_id' => $eventId, 'seat' => $seatLabel]);
                    return;
                }
            }
            $reservation = new Reservation();
            $reservation->setEvenement($evenement);
            $reservation->setParticipant($user);
            $reservation->setStatus(Reservation::STATUS_CONFIRMED);
            $reservation->setStripeCheckoutSessionId($session->id);
            $reservation->setAmountPaid((int) ($session->amount_total ?? 0));
            if ($seatLabel !== '') {
                $reservation->setSeatLabel($seatLabel);
            }
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();
            $this->logger->info('Stripe webhook: reservation created', ['reservation_id' => $reservation->getId(), 'event_id' => $eventId]);
        }

        if (!$reservation) {
            if (!$reservationId && !$eventId) {
                $this->logger->warning('Stripe webhook: no reservation_id or event_id in session metadata');
            }
            return;
        }

        try {
            $this->emailService->sendReservationConfirmationDetails($reservation);
        } catch (\Throwable $e) {
            $this->logger->error('Stripe webhook: failed to send confirmation email', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
        try {
            $this->emailService->sendReservationNotificationToOwner($reservation);
        } catch (\Throwable $e) {
            $this->logger->error('Stripe webhook: failed to send notification email to owner', [
                'reservation_id' => $reservation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
