<?php

namespace App\Services\AssessmentEngine;

/**
 * ScoreCalculator
 *
 * Stateless helper that turns a list of normalized words into category ratios.
 * Each dictionary (self | concept | adjusted) maps word -> { cat: A|B|C|D, w: weight }.
 * We sum weights per category, then divide by total capacity of that category to get a 0..1 ratio.
 */
class ScoreCalculator
{
    public function __construct(protected WeightRepository $weights)
    {
    }

    /**
     * Compute category ratios for a set of selected words under a given dictionary key:
     * - dictKey: 'self' | 'concept' | 'adjusted'
     * Returns [ 'A' => float, 'B' => float, 'C' => float, 'D' => float, 'avg' => float ]
     */
    /**
     * Compute category ratios for a set of selected words under a given dictionary key.
     * Returns: [ 'A' => float, 'B' => float, 'C' => float, 'D' => float, 'avg' => float ]
     */
    public function scores(array $selectedWords, string $dictKey): array
    {
        $dicts = $this->weights->all();
        $dict = $dicts[$dictKey] ?? [];
        $capacitiesPerDict = $this->weights->capacities($dicts);
        $capacities = $capacitiesPerDict[$dictKey] ?? ['A' => 1, 'B' => 1, 'C' => 1, 'D' => 1];

        $sum = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        foreach ($selectedWords as $w) {
            $k = WordNormalizer::normalize((string) $w);
            if (isset($dict[$k])) {
                $entry = $dict[$k];
                $cat = $entry['cat'];
                $sum[$cat] += $entry['w'];
            }
        }

        $ratio = [];
        foreach (['A', 'B', 'C', 'D'] as $cat) {
            $capacity = $capacities[$cat] ?? 0;
            $ratio[$cat] = $capacity > 0 ? (float) $sum[$cat] / $capacity : 0.0;
        }

        $ratio['avg'] = ($ratio['A'] + $ratio['B'] + $ratio['C'] + $ratio['D']) / 4.0;
        return $ratio;
    }

    /**
     * Heuristic decision approach metric. Adjustable.
     */
    /**
     * Simple heuristic decision approach metric: blend mean + dispersion.
     */
    public function decisionApproach(float $selfAvg, float $concAvg): float
    {
        $mean = ($selfAvg + $concAvg) / 2.0;
        $dispersion = abs($selfAvg - $concAvg);
        $value = $mean * 0.8 + $dispersion * 0.2;
        return (float) max(0.0, min(1.0, $value));
    }
}
