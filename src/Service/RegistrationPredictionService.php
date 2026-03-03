<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Delegates registration ML prediction to the Python script.
 * Full result: next_month + future_by_type (rest of year) from python/predict_registrations.py.
 */
final class RegistrationPredictionService
{
    private string $projectDir;
    private string $pythonScript;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
        $pythonDir = $this->projectDir . \DIRECTORY_SEPARATOR . 'python' . \DIRECTORY_SEPARATOR . 'predict_registrations.py';
        $scriptsDir = $this->projectDir . \DIRECTORY_SEPARATOR . 'scripts' . \DIRECTORY_SEPARATOR . 'predict_registrations.py';
        $this->pythonScript = is_file($pythonDir) && is_readable($pythonDir)
            ? $pythonDir
            : $scriptsDir;
    }

    /**
     * Get next-month prediction and future inscriptions by type from Python script (Ridge regression ML).
     *
     * @param array<int, array{month:string, count:int|float|string}> $monthlyRegistrations
     * @param array<int, array{month:string, ROLE_USER:int, ROLE_PARTICIPANT:int, ROLE_ARTISTE:int, ROLE_ADMIN:int}> $monthlyByRole
     *
     * @return array{
     *     next_month: array{predictedCount:int, trend:string, confidence:string, confidenceScore:int, lowerBound:int, upperBound:int, seasonalityFactor:float, nextMonthLabel:string},
     *     future_by_type: list<array{month:string, ROLE_USER:int, ROLE_PARTICIPANT:int, ROLE_ARTISTE:int, ROLE_ADMIN:int}>
     * }
     */
    public function getPredictionsFromPython(array $monthlyRegistrations, array $monthlyByRole): array
    {
        $input = [
            'monthly' => $monthlyRegistrations,
            'monthly_by_role' => $monthlyByRole,
        ];
        $jsonInput = json_encode($input, \JSON_THROW_ON_ERROR);

        $script = $this->pythonScript;
        if (!is_file($script) || !is_readable($script)) {
            return $this->fallbackPredictions();
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $proc = @proc_open(
            [$this->getPythonExecutable(), $script],
            $descriptorSpec,
            $pipes,
            $this->projectDir,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($proc)) {
            return $this->fallbackPredictions();
        }

        fwrite($pipes[0], $jsonInput);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($stdout === false || $stdout === '') {
            return $this->fallbackPredictions();
        }

        try {
            $decoded = json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->fallbackPredictions();
        }

        $nextMonth = $decoded['next_month'] ?? null;
        $futureByType = $decoded['future_by_type'] ?? [];

        if (!is_array($nextMonth)) {
            return $this->fallbackPredictions();
        }

        return [
            'next_month' => $this->normalizeNextMonth($nextMonth),
            'future_by_type' => is_array($futureByType) ? $futureByType : [],
        ];
    }

    /**
     * Returns only next_month (e.g. for backward compatibility).
     *
     * @param array<int, array{month:string, count:int|float|string}> $monthlyRegistrations
     *
     * @return array{predictedCount:int, trend:string, confidence:string, confidenceScore:int, lowerBound:int, upperBound:int, seasonalityFactor:float, nextMonthLabel:string}
     */
    public function predictNextMonth(array $monthlyRegistrations): array
    {
        $monthlyByRole = [];
        $result = $this->getPredictionsFromPython($monthlyRegistrations, $monthlyByRole);
        return $result['next_month'];
    }

    private function getPythonExecutable(): string
    {
        if (str_starts_with(\PHP_OS, 'WIN')) {
            return 'python';
        }
        return 'python3';
    }

    /**
     * @param array<string, mixed> $nextMonth
     *
     * @return array{predictedCount:int, trend:string, confidence:string, confidenceScore:int, lowerBound:int, upperBound:int, seasonalityFactor:float, nextMonthLabel:string}
     */
    private function normalizeNextMonth(array $nextMonth): array
    {
        $next = (new \DateTimeImmutable('first day of next month'));
        return [
            'predictedCount' => (int) ($nextMonth['predictedCount'] ?? 0),
            'trend' => (string) ($nextMonth['trend'] ?? 'stable'),
            'confidence' => (string) ($nextMonth['confidence'] ?? 'low'),
            'confidenceScore' => (int) ($nextMonth['confidenceScore'] ?? 0),
            'lowerBound' => (int) ($nextMonth['lowerBound'] ?? 0),
            'upperBound' => (int) ($nextMonth['upperBound'] ?? 0),
            'seasonalityFactor' => (float) ($nextMonth['seasonalityFactor'] ?? 0.0),
            'nextMonthLabel' => (string) ($nextMonth['nextMonthLabel'] ?? $next->format('M Y')),
        ];
    }

    /**
     * @return array{next_month: array{predictedCount:int, trend:string, confidence:string, confidenceScore:int, lowerBound:int, upperBound:int, seasonalityFactor:float, nextMonthLabel:string}, future_by_type: list<array>}
     */
    private function fallbackPredictions(): array
    {
        $next = (new \DateTimeImmutable('first day of next month'))->format('M Y');
        return [
            'next_month' => [
                'predictedCount' => 0,
                'trend' => 'stable',
                'confidence' => 'low',
                'confidenceScore' => 0,
                'lowerBound' => 0,
                'upperBound' => 0,
                'seasonalityFactor' => 0.0,
                'nextMonthLabel' => $next,
            ],
            'future_by_type' => [],
        ];
    }
}
