<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class YoloDonationImageValidator
{
    /**
     * @param array<string, array<int, string>> $typeClassMap
     * @param array<string, array<int, string>> $typeAliases
     * @param array<int, string> $nonVisualTypes
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly string $pythonBinary,
        private readonly string $scriptPath,
        private readonly string $modelName,
        private readonly float $confidenceThreshold,
        private readonly float $fallbackConfidenceThreshold,
        private readonly int $timeoutSeconds,
        private readonly string $settingsDir,
        private readonly array $typeClassMap,
        private readonly array $typeAliases,
        private readonly array $nonVisualTypes
    ) {
    }

    /**
     * @return array{
     *   is_valid: bool,
     *   message: string,
     *   type_key: ?string,
     *   matched_class: ?string,
     *   confidence: ?float,
     *   detected_classes: array<int, string>,
     *   service_error: bool
     * }
     */
    public function validate(?string $imagePath, ?string $selectedTypeLabel): array
    {
        $normalizedType = $this->normalize((string) $selectedTypeLabel);
        if ($normalizedType === '') {
            return $this->invalid('Type de don manquant.', null);
        }

        $typeKey = $this->resolveTypeKey($normalizedType);
        if ($typeKey === null) {
            return $this->invalid(
                sprintf('Type de don "%s" non supporte par la validation IA.', (string) $selectedTypeLabel),
                null
            );
        }

        if ($this->isNonVisualType($typeKey)) {
            return $this->valid(
                sprintf('Validation IA ignoree pour le type "%s".', (string) $selectedTypeLabel),
                $typeKey
            );
        }

        if ($imagePath === null || trim($imagePath) === '') {
            return $this->invalid(
                sprintf('Une photo est obligatoire pour le type "%s".', (string) $selectedTypeLabel),
                $typeKey
            );
        }

        if (!is_file($imagePath)) {
            return $this->invalid('Fichier image introuvable.', $typeKey);
        }

        $allowedClasses = $this->normalizeList($this->typeClassMap[$typeKey] ?? []);
        if ($allowedClasses === []) {
            return $this->invalid(
                sprintf('Aucune classe IA configuree pour le type "%s".', (string) $selectedTypeLabel),
                $typeKey
            );
        }

        try {
            $detections = $this->scanImage($imagePath, $this->confidenceThreshold);
            if (
                $detections === []
                && $this->fallbackConfidenceThreshold > 0
                && $this->fallbackConfidenceThreshold < $this->confidenceThreshold
            ) {
                $detections = $this->scanImage($imagePath, $this->fallbackConfidenceThreshold);
            }
        } catch (\Throwable $exception) {
            return $this->invalid('Service IA indisponible: ' . $exception->getMessage(), $typeKey, true);
        }

        $detected = $this->extractDetectedClasses($detections);
        if ($detected === []) {
            return $this->invalid(
                sprintf('Aucun objet detecte sur la photo pour le type "%s".', (string) $selectedTypeLabel),
                $typeKey
            );
        }

        $matchedClass = null;
        $matchedConfidence = null;
        foreach ($allowedClasses as $allowedClass) {
            if (isset($detected[$allowedClass])) {
                $matchedClass = $allowedClass;
                $matchedConfidence = $detected[$allowedClass];
                break;
            }
        }

        if ($matchedClass !== null) {
            return [
                'is_valid' => true,
                'message' => sprintf(
                    'Photo valide pour "%s" (%s detecte, confiance %.2f).',
                    (string) $selectedTypeLabel,
                    $matchedClass,
                    $matchedConfidence
                ),
                'type_key' => $typeKey,
                'matched_class' => $matchedClass,
                'confidence' => $matchedConfidence,
                'detected_classes' => array_keys($detected),
                'service_error' => false,
            ];
        }

        if ($typeKey === 'fourniture') {
            foreach (array_keys($detected) as $detectedClass) {
                if (!in_array($detectedClass, ['person', 'tie', 'handbag', 'backpack', 'suitcase'], true)) {
                    return [
                        'is_valid' => true,
                        'message' => sprintf(
                            'Photo acceptee pour "%s" (%s detecte).',
                            (string) $selectedTypeLabel,
                            $detectedClass
                        ),
                        'type_key' => $typeKey,
                        'matched_class' => $detectedClass,
                        'confidence' => $detected[$detectedClass] ?? null,
                        'detected_classes' => array_keys($detected),
                        'service_error' => false,
                    ];
                }
            }
        }

        return [
            'is_valid' => false,
            'message' => sprintf(
                'Image non valide pour "%s". Objets detectes: %s.',
                (string) $selectedTypeLabel,
                implode(', ', array_keys($detected))
            ),
            'type_key' => $typeKey,
            'matched_class' => null,
            'confidence' => null,
            'detected_classes' => array_keys($detected),
            'service_error' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanImage(string $imagePath, float $confidence): array
    {
        if (!is_dir($this->settingsDir) && !mkdir($this->settingsDir, 0775, true) && !is_dir($this->settingsDir)) {
            throw new \RuntimeException('Impossible de preparer le dossier de configuration Ultralytics.');
        }

        $pythonBinary = $this->toAbsolutePath($this->pythonBinary);
        if (!is_file($pythonBinary)) {
            throw new \RuntimeException(
                sprintf('Binaire Python introuvable: %s. Lancez bin/setup-ai-validator.sh.', $pythonBinary)
            );
        }

        $process = new Process([
            $pythonBinary,
            $this->scriptPath,
            $imagePath,
            '--conf=' . (string) $confidence,
            '--model=' . $this->modelName,
        ]);
        $process->setWorkingDirectory($this->projectDir);
        $process->setEnv([
            'ULTRALYTICS_SETTINGS_DIR' => $this->settingsDir,
        ]);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        $output = trim($process->getOutput());
        $payload = $this->decodePayload($output);

        if (!$process->isSuccessful() && !is_array($payload)) {
            $errorText = trim($process->getErrorOutput());
            throw new \RuntimeException($errorText !== '' ? $errorText : 'Echec du script Python.');
        }

        if (!is_array($payload)) {
            $stderr = trim($process->getErrorOutput());
            throw new \RuntimeException($stderr !== '' ? $stderr : 'Sortie JSON invalide du script Python.');
        }

        if (($payload['success'] ?? false) !== true) {
            $error = trim((string) ($payload['error'] ?? 'Erreur inconnue du scanner IA.'));
            throw new \RuntimeException($error !== '' ? $error : 'Erreur inconnue du scanner IA.');
        }

        $detections = $payload['detections'] ?? [];
        if (!is_array($detections)) {
            return [];
        }

        return $detections;
    }

    /**
     * @param array<int, array<string, mixed>> $detections
     * @return array<string, float>
     */
    private function extractDetectedClasses(array $detections): array
    {
        $classes = [];
        foreach ($detections as $detection) {
            $class = $this->normalize((string) ($detection['class'] ?? ''));
            if ($class === '') {
                continue;
            }

            $confidence = (float) ($detection['confidence'] ?? 0.0);
            if (!isset($classes[$class]) || $confidence > $classes[$class]) {
                $classes[$class] = $confidence;
            }
        }

        return $classes;
    }

    private function resolveTypeKey(string $normalizedType): ?string
    {
        foreach ($this->typeAliases as $typeKey => $aliases) {
            $candidateKey = $this->normalize((string) $typeKey);
            $normalizedAliases = $this->normalizeList($aliases);

            if ($normalizedType === $candidateKey || in_array($normalizedType, $normalizedAliases, true)) {
                return $candidateKey;
            }

            foreach ($normalizedAliases as $alias) {
                if ($alias !== '' && str_contains($normalizedType, $alias)) {
                    return $candidateKey;
                }
            }
        }

        return null;
    }

    private function isNonVisualType(string $typeKey): bool
    {
        return in_array($typeKey, $this->normalizeList($this->nonVisualTypes), true);
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function normalizeList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $item = $this->normalize((string) $value);
            if ($item !== '' && !in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function toAbsolutePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return rtrim($this->projectDir, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $output): ?array
    {
        if ($output === '') {
            return null;
        }

        $lines = preg_split('/\R/', $output) ?: [];
        for ($index = count($lines) - 1; $index >= 0; --$index) {
            $line = trim($lines[$index]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   is_valid: bool,
     *   message: string,
     *   type_key: ?string,
     *   matched_class: ?string,
     *   confidence: ?float,
     *   detected_classes: array<int, string>,
     *   service_error: bool
     * }
     */
    private function valid(string $message, ?string $typeKey): array
    {
        return [
            'is_valid' => true,
            'message' => $message,
            'type_key' => $typeKey,
            'matched_class' => null,
            'confidence' => null,
            'detected_classes' => [],
            'service_error' => false,
        ];
    }

    /**
     * @return array{
     *   is_valid: bool,
     *   message: string,
     *   type_key: ?string,
     *   matched_class: ?string,
     *   confidence: ?float,
     *   detected_classes: array<int, string>,
     *   service_error: bool
     * }
     */
    private function invalid(string $message, ?string $typeKey, bool $serviceError = false): array
    {
        return [
            'is_valid' => false,
            'message' => $message,
            'type_key' => $typeKey,
            'matched_class' => null,
            'confidence' => null,
            'detected_classes' => [],
            'service_error' => $serviceError,
        ];
    }
}
