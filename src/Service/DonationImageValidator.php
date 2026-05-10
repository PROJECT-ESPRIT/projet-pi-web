<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DonationImageValidator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private string $apiKey;
    private string $model;

    public function __construct(
        private HttpClientInterface $httpClient,
        ?string $apiKey,
        ?string $model,
        private int $timeoutSeconds,
        private bool $allowSkip,
    ) {
        $this->apiKey = (string) $apiKey;
        $this->model = $model !== null && $model !== '' ? $model : 'gemini-2.5-flash';
    }

    /**
     * @return array{ok: bool, message?: string, label?: string, confidence?: float, duration_ms?: int, skipped?: bool}
     */
    public function validate(?string $imagePath, string $expectedLabel): array
    {
        if (!$imagePath) {
            return ['ok' => false, 'message' => 'Image manquante pour la validation.'];
        }
        if (trim($this->apiKey) === '') {
            return $this->skipOrFail('Clé API Gemini manquante.');
        }

        $projectDir = dirname(__DIR__, 2);
        $fullImagePath = $projectDir . '/public' . $imagePath;
        if (!is_file($fullImagePath)) {
            return ['ok' => false, 'message' => 'Image du don introuvable pour validation.'];
        }

        $bytes = @file_get_contents($fullImagePath);
        if ($bytes === false) {
            return ['ok' => false, 'message' => 'Image du don illisible.'];
        }
        $mime = $this->detectMime($fullImagePath, $bytes);

        $start = microtime(true);
        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf(self::ENDPOINT, $this->model),
                [
                    'timeout' => $this->timeoutSeconds,
                    'query' => ['key' => $this->apiKey],
                    'json' => $this->buildPayload($bytes, $mime, $expectedLabel),
                ]
            );
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            return $this->skipOrFail('Service Gemini indisponible: ' . $e->getMessage());
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if ($status >= 400) {
            return $this->skipOrFail(sprintf('Gemini a renvoyé le code %d.', $status), $durationMs);
        }

        $decoded = json_decode($body, true);
        $text = $this->extractText(is_array($decoded) ? $decoded : []);
        if ($text === null) {
            return $this->skipOrFail('Réponse Gemini illisible.', $durationMs);
        }

        $verdict = $this->parseVerdict($text);
        if ($verdict === null) {
            return $this->skipOrFail('Verdict Gemini illisible.', $durationMs);
        }

        return [
            'ok' => (bool) $verdict['ok'],
            'message' => $verdict['message'] ?? null,
            'label' => $verdict['label'] ?? null,
            'confidence' => isset($verdict['confidence']) ? (float) $verdict['confidence'] : null,
            'duration_ms' => $durationMs,
            'skipped' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $bytes, string $mime, string $expectedLabel): array
    {
        $expected = $expectedLabel !== '' ? $expectedLabel : 'unknown';
        $prompt = <<<PROMPT
You are a donation image validator. The donor claims this image shows a "{$expected}".
Look at the image and decide whether the claim is correct.

Reply with ONLY a JSON object on a single line, no prose, no code fences:
{"ok": <true|false>, "label": "<short label of what you see>", "confidence": <0..1>, "message": "<one short sentence in French>"}

Set "ok" to true only if the image clearly depicts a "{$expected}". Otherwise false.
PROMPT;

        return [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => [
                        'mime_type' => $mime,
                        'data' => base64_encode($bytes),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.0,
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractText(array $decoded): ?string
    {
        $parts = $decoded['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return null;
        }
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                return $part['text'];
            }
        }
        return null;
    }

    /**
     * @return array{ok: bool, label?: string, confidence?: float, message?: string}|null
     */
    private function parseVerdict(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
        }
        if (!is_array($decoded) || !array_key_exists('ok', $decoded)) {
            return null;
        }
        return [
            'ok' => (bool) $decoded['ok'],
            'label' => isset($decoded['label']) ? (string) $decoded['label'] : null,
            'confidence' => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
            'message' => isset($decoded['message']) ? (string) $decoded['message'] : null,
        ];
    }

    private function detectMime(string $path, string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    /**
     * @return array{ok: bool, message: string, skipped?: bool, duration_ms?: int}
     */
    private function skipOrFail(string $reason, ?int $durationMs = null): array
    {
        $base = $this->allowSkip
            ? ['ok' => true, 'message' => 'Validation ignorée (' . $reason . ')', 'skipped' => true]
            : ['ok' => false, 'message' => $reason];
        if ($durationMs !== null) {
            $base['duration_ms'] = $durationMs;
        }
        return $base;
    }
}
