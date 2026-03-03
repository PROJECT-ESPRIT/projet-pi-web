<?php

namespace App\Service;

final class RegistrationPredictionService
{
    /**
     * @param array<int, array{month:string, count:int|float|string}> $monthlyRegistrations
     *
     * @return array{
     *     predictedCount:int,
     *     trend:string,
     *     confidence:string,
     *     confidenceScore:int,
     *     lowerBound:int,
     *     upperBound:int,
     *     seasonalityFactor:float,
     *     nextMonthLabel:string
     * }
     */
    public function predictNextMonth(array $monthlyRegistrations): array
    {
        $counts = array_map(
            static fn (array $row): float => max(0.0, (float) ($row['count'] ?? 0)),
            $monthlyRegistrations
        );

        $n = count($counts);
        if ($n === 0) {
            return [
                'predictedCount' => 0,
                'trend' => 'stable',
                'confidence' => 'low',
                'confidenceScore' => 0,
                'lowerBound' => 0,
                'upperBound' => 0,
                'seasonalityFactor' => 0.0,
                'nextMonthLabel' => (new \DateTimeImmutable('first day of next month'))->format('M Y'),
            ];
        }

        $regressionPrediction = $this->linearRegressionPrediction($counts);
        $movingAveragePrediction = $this->movingAveragePrediction($counts, 3);
        $expSmoothingPrediction = $this->exponentialSmoothingPrediction($counts, 0.45);
        $seasonalityFactor = $this->seasonalityFactor($counts);

        $prediction = (
            (0.45 * $regressionPrediction) +
            (0.25 * $movingAveragePrediction) +
            (0.30 * $expSmoothingPrediction)
        ) * (1.0 + $seasonalityFactor);
        $prediction = max(0.0, $prediction);

        $last = $counts[$n - 1];
        $trend = 'stable';
        $deltaThreshold = max(1.0, $last * 0.07);
        if ($prediction > $last + $deltaThreshold) {
            $trend = 'up';
        } elseif ($prediction < $last - $deltaThreshold) {
            $trend = 'down';
        }

        $confidenceScore = $this->confidenceScore($counts, $prediction);
        $confidenceBand = $this->confidenceBand($counts, $prediction, $confidenceScore);

        return [
            'predictedCount' => (int) round($prediction),
            'trend' => $trend,
            'confidence' => $this->estimateConfidenceFromScore($confidenceScore),
            'confidenceScore' => $confidenceScore,
            'lowerBound' => $confidenceBand['lower'],
            'upperBound' => $confidenceBand['upper'],
            'seasonalityFactor' => round($seasonalityFactor, 4),
            'nextMonthLabel' => (new \DateTimeImmutable('first day of next month'))->format('M Y'),
        ];
    }

    /**
     * @param float[] $values
     */
    private function linearRegressionPrediction(array $values): float
    {
        $n = count($values);
        if ($n <= 1) {
            return $values[0] ?? 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;

        foreach ($values as $i => $y) {
            $x = (float) $i;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if (abs($denominator) < 0.00001) {
            return $values[$n - 1];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return $intercept + ($slope * $n);
    }

    /**
     * @param float[] $values
     */
    private function movingAveragePrediction(array $values, int $window): float
    {
        $slice = array_slice($values, -max(1, $window));
        if ($slice === []) {
            return 0.0;
        }

        return array_sum($slice) / count($slice);
    }

    /**
     * @param float[] $values
     */
    private function exponentialSmoothingPrediction(array $values, float $alpha): float
    {
        if ($values === []) {
            return 0.0;
        }

        $level = $values[0];
        for ($i = 1, $n = count($values); $i < $n; $i++) {
            $level = ($alpha * $values[$i]) + ((1.0 - $alpha) * $level);
        }

        return $level;
    }

    /**
     * @param float[] $values
     */
    private function seasonalityFactor(array $values): float
    {
        $n = count($values);
        if ($n < 8) {
            return 0.0;
        }

        $recent = array_slice($values, -3);
        $older = array_slice($values, -6, 3);
        if (count($older) < 3) {
            return 0.0;
        }

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        if ($olderAvg <= 0.00001) {
            return 0.0;
        }

        $raw = ($recentAvg - $olderAvg) / $olderAvg;
        return max(-0.15, min(0.15, $raw * 0.35));
    }

    /**
     * @param float[] $values
     */
    private function confidenceScore(array $values, float $prediction): int
    {
        $n = count($values);
        if ($n < 3) {
            return 35;
        }

        $mean = array_sum($values) / $n;
        if ($mean <= 0.00001) {
            return 50;
        }

        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $variance /= $n;
        $stdDev = sqrt($variance);
        $coefVar = $stdDev / $mean;

        $volatilityScore = (1.0 - min(1.0, $coefVar)) * 55.0;

        $last = $values[$n - 1];
        $drift = $mean > 0 ? abs($prediction - $last) / max(1.0, $mean) : 1.0;
        $driftScore = (1.0 - min(1.0, $drift)) * 30.0;

        $sampleScore = min(15.0, ($n / 12.0) * 15.0);

        $score = (int) round($volatilityScore + $driftScore + $sampleScore);

        return max(0, min(100, $score));
    }

    private function estimateConfidenceFromScore(int $score): string
    {
        if ($score >= 75) {
            return 'high';
        }
        if ($score >= 50) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param float[] $values
     *
     * @return array{lower:int, upper:int}
     */
    private function confidenceBand(array $values, float $prediction, int $confidenceScore): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['lower' => 0, 'upper' => 0];
        }

        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $variance /= $n;
        $stdDev = sqrt($variance);

        $uncertaintyFactor = 1.35 - (($confidenceScore / 100) * 0.8);
        $margin = max(1.0, $stdDev * $uncertaintyFactor);

        $lower = (int) max(0, round($prediction - $margin));
        $upper = (int) max($lower, round($prediction + $margin));

        return ['lower' => $lower, 'upper' => $upper];
    }
}
