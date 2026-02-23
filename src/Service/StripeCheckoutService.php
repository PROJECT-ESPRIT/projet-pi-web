<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeCheckoutService
{
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $secretKey,
        private readonly string $currency
    ) {
    }

    /**
     * @param array<string, string|int|bool> $metadata
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(
        int $amountCents,
        string $successUrl,
        string $cancelUrl,
        string $description,
        array $metadata = []
    ): array {
        $this->assertConfigured();

        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Le montant Stripe doit être strictement positif.');
        }

        $body = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items[0][price_data][currency]' => strtolower($this->currency),
            'line_items[0][price_data][unit_amount]' => (string) $amountCents,
            'line_items[0][price_data][product_data][name]' => 'Donation',
            'line_items[0][price_data][product_data][description]' => $description,
            'line_items[0][quantity]' => '1',
        ];

        foreach ($metadata as $key => $value) {
            $body[sprintf('metadata[%s]', $key)] = (string) $value;
        }

        $payload = $this->request('POST', '/checkout/sessions', $body);

        $id = trim((string) ($payload['id'] ?? ''));
        $url = trim((string) ($payload['url'] ?? ''));
        if ($id === '' || $url === '') {
            throw new \RuntimeException('Réponse Stripe invalide: session incomplète.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCheckoutSession(string $sessionId): array
    {
        $this->assertConfigured();

        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new \InvalidArgumentException('session_id Stripe manquant.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId));

        return $payload;
    }

    private function assertConfigured(): void
    {
        if (trim($this->secretKey) === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY non configurée.');
        }
    }

    /**
     * @param array<string, string>|array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $response = $this->httpClient->request($method, self::STRIPE_API_BASE . $path, [
            'auth_bearer' => $this->secretKey,
            'body' => $body,
        ]);

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode >= 400) {
            $message = (string) ($payload['error']['message'] ?? 'Erreur Stripe.');
            throw new \RuntimeException($message);
        }

        if (!is_array($payload)) {
            throw new \RuntimeException('Réponse Stripe invalide.');
        }

        return $payload;
    }
}
