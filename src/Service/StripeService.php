<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\User;
use Stripe\StripeClient;

class StripeService
{
    /** Stripe currency (must be supported by your Stripe account, e.g. eur, usd). */
    private const CURRENCY = 'eur';
    /** EUR smallest unit: 1 EUR = 100 cents. */
    private const AMOUNT_MULTIPLIER = 100;

    public function __construct(
        private string $stripeSecretKey,
    ) {
    }

    /**
     * Ensures the configured key is the secret key (sk_...), not the publishable key (pk_).
     */
    private function assertSecretKey(): void
    {
        $key = $this->stripeSecretKey;
        if ($key === '' || str_starts_with($key, 'pk_')) {
            throw new \InvalidArgumentException(
                'Stripe secret key is missing or invalid. In .env set STRIPE_SECRET_KEY to your SECRET key (starts with sk_test_ or sk_live_), not the publishable key (pk_).'
            );
        }
    }

    /**
     * Creates a Stripe Checkout Session for a paid event.
     * When reservationId is provided, the webhook will confirm that existing PENDING reservation.
     * Returns the session URL to redirect the user to.
     */
    public function createCheckoutSessionForEvent(
        Evenement $evenement,
        User $user,
        ?string $seatLabel,
        string $successUrl,
        string $cancelUrl,
        ?int $reservationId = null,
    ): string {
        $this->assertSecretKey();
        $stripe = new StripeClient($this->stripeSecretKey);
        $prix = $evenement->getPrix();
        if ($prix === null || $prix <= 0) {
            throw new \InvalidArgumentException('Event must have a positive price for Stripe Checkout.');
        }

        $amount = (int) round($prix * self::AMOUNT_MULTIPLIER);
        if ($amount < 1) {
            $amount = 1;
        }

        $metadata = [
            'event_id' => (string) $evenement->getId(),
            'user_id' => (string) $user->getId(),
            'seat_label' => $seatLabel ?? '',
        ];
        if ($reservationId !== null) {
            $metadata['reservation_id'] = (string) $reservationId;
        }

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => self::CURRENCY,
                        'product_data' => [
                            'name' => $evenement->getTitre(),
                            'description' => sprintf(
                                'Réservation pour %s — %s',
                                $evenement->getTitre(),
                                $evenement->getDateDebut() ? $evenement->getDateDebut()->format('d/m/Y H:i') : ''
                            ),
                            'metadata' => [
                                'event_id' => (string) $evenement->getId(),
                            ],
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $this->appendCheckoutSessionIdPlaceholder($successUrl),
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);

        return $session->url;
    }

    private function appendCheckoutSessionIdPlaceholder(string $successUrl): string
    {
        $separator = str_contains($successUrl, '?') ? '&' : '?';
        return $successUrl . $separator . 'session_id={CHECKOUT_SESSION_ID}';
    }
}
