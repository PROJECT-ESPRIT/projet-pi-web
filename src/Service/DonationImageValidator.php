<?php

namespace App\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DonationImageValidator
{
    public function __construct(
        private string $pythonBin,
        private string $modelPath,
        private float $confidence,
        private int $timeoutSeconds,
        private string $settingsDir,
        private bool $allowSkip,
        private string $serviceUrl,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{ok: bool, message?: string, label?: string, confidence?: float, duration_ms?: int, skipped?: bool}
     */
    public function validate(?string $imagePath, string $expectedLabel): array
    {
        if (!$imagePath) {
            return ['ok' => false, 'message' => 'Image manquante pour la validation.'];
        }

        $projectDir = dirname(__DIR__, 2);
        $script = $projectDir . '/python/donation_image_validator.py';
        if (!is_file($script)) {
            return $this->allowSkip
                ? ['ok' => true, 'message' => 'Validation ignorée (script manquant).', 'skipped' => true]
                : ['ok' => false, 'message' => 'Validateur d\'image introuvable.'];
        }

        $fullImagePath = $projectDir . '/public' . $imagePath;
        if (!is_file($fullImagePath)) {
            return ['ok' => false, 'message' => 'Image du don introuvable pour validation.'];
        }

        $payload = $this->callLocalService($fullImagePath, $expectedLabel);
        if ($payload === null) {
            if (trim((string) $this->serviceUrl) !== '') {
                return $this->allowSkip
                    ? ['ok' => true, 'message' => 'Validation ignorée (service IA indisponible).', 'skipped' => true]
                    : ['ok' => false, 'message' => 'Service IA indisponible. Démarrez python/run_ai_service.sh'];
            }
            $payload = $this->runLocalProcess($projectDir, $script, $fullImagePath, $expectedLabel);
        }

        if (!is_array($payload) || !array_key_exists('ok', $payload)) {
            return $this->allowSkip
                ? ['ok' => true, 'message' => 'Validation ignorée (réponse IA invalide).', 'skipped' => true]
                : ['ok' => false, 'message' => 'Réponse invalide du validateur d\'image.'];
        }

        return [
            'ok' => (bool) $payload['ok'],
            'message' => $payload['message'] ?? null,
            'label' => $payload['label'] ?? null,
            'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            'duration_ms' => isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null,
            'skipped' => !empty($payload['skipped']),
        ];
    }

    private function callLocalService(string $fullImagePath, string $expectedLabel): ?array
    {
        $url = trim($this->serviceUrl);
        if ($url === '') {
            return null;
        }

        try {
            $content = file_get_contents($fullImagePath);
            if ($content === false) {
                return ['ok' => false, 'message' => 'Image du don introuvable pour validation.'];
            }
            $formData = new FormDataPart([
                'expected' => $expectedLabel,
                'image' => new DataPart($content, 'donation.jpg', 'image/jpeg'),
            ]);
            $response = $this->httpClient->request('POST', rtrim($url, '/') . '/predict', [
                'timeout' => min(5, max(2, $this->timeoutSeconds)),
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
            ]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $content = $response->getContent(false);
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            if ($this->allowSkip) {
                return ['ok' => true, 'message' => 'Validation ignorée (service IA indisponible).', 'skipped' => true];
            }
            return null;
        }
    }

    private function runLocalProcess(string $projectDir, string $script, string $fullImagePath, string $expectedLabel): ?array
    {
        $tmpDir = $projectDir . '/var/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $process = new Process([
            $this->pythonBin,
            $script,
            '--image',
            $fullImagePath,
            '--expected',
            $expectedLabel,
        ]);
        $process->setTimeout($this->timeoutSeconds);
        $process->setIdleTimeout($this->timeoutSeconds);
        $process->setEnv([
            'DONATION_AI_MODEL' => $this->modelPath,
            'DONATION_AI_CONFIDENCE' => (string) $this->confidence,
            'DONATION_AI_MARGIN' => (string) (getenv('DONATION_AI_MARGIN') ?: '0.0'),
            'ULTRALYTICS_SETTINGS_DIR' => $this->settingsDir,
            'YOLO_DEVICE' => 'cpu',
            'DONATION_AI_ALLOW_SKIP' => $this->allowSkip ? '1' : '0',
            'DONATION_AI_LABEL_GROUPS' => $projectDir . '/config/ai_label_groups.json',
            'TMPDIR' => $tmpDir,
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return ['ok' => false, 'message' => 'La vérification est trop lente. Réessayez avec une image plus légère.'];
        }

        if (!$process->isSuccessful()) {
            return $this->allowSkip
                ? ['ok' => true, 'message' => 'Validation ignorée (échec IA).', 'skipped' => true]
                : ['ok' => false, 'message' => 'La vérification automatique a échoué.'];
        }

        $payload = json_decode($process->getOutput(), true);
        return is_array($payload) ? $payload : null;
    }

}
