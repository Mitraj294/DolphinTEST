<?php

namespace App\Services\AssessmentEngine;

class ScoreCalculator
{
    public function __construct(
        protected WeightRepository $weights
    ) {}

    /**
     * Compute category ratios for a set of selected words under a given dictionary key:
     * - dictKey: 'self' | 'concept' | 'adjusted'
     * Returns [ 'A' => float, 'B' => float, 'C' => float, 'D' => float, 'avg' => float ]
     */
    public function scores(array $selectedWords, string $dictKey): array
    {
        $dicts = $this->weights->all();
        $dict = $dicts[$dictKey] ?? [];
        $capacities = $this->weights->capacities($dicts)[$dictKey] ?? ['A'=>1,'B'=>1,'C'=>1,'D'=>1];

        $sum = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($selectedWords as $w) {
            $k = WordNormalizer::normalize((string) $w);
            if (isset($dict[$k])) {
                $entry = $dict[$k];
                $sum[$entry['cat']] += $entry['w'];
            }
        }
        // Normalize against category capacity to produce independent ratios 0..1
        $ratio = [
            'A' => $capacities['A'] ? $sum['A'] / $capacities['A'] : 0.0,
            'B' => $capacities['B'] ? $sum['B'] / $capacities['B'] : 0.0,
            'C' => $capacities['C'] ? $sum['C'] / $capacities['C'] : 0.0,
            'D' => $capacities['D'] ? $sum['D'] / $capacities['D'] : 0.0,
        ];
        $avg = ($ratio['A'] + $ratio['B'] + $ratio['C'] + $ratio['D']) / 4.0;
        $ratio['avg'] = $avg;
        return $ratio;
    }

    /**
     * Heuristic decision approach metric. Adjustable.
     */
    public function decisionApproach(float $selfAvg, float $concAvg): float
    {
        $mean = ($selfAvg + $concAvg) / 2.0;
        $dispersion = abs($selfAvg - $concAvg);
        // Weighted combination
        return max(0.0, min(1.0, $mean * 0.8 + $dispersion * 0.2));
    }
}
