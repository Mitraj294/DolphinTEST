<?php

namespace App\Services\AssessmentEngine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeightRepository
{
    /**
     * Load and cache weight dictionaries for self, concept, and adjusted.
     * Returns array: [ 'self' => [word => ['cat' => 'A'|'B'|'C'|'D', 'w' => int]], ... ]
     */
    public function all(): array
    {
        return Cache::remember('assessment_weights_v1', 3600, function () {
            return [
                'self'     => $this->loadFromFile(resource_path('assessment_weights/self_weight.txt')),
                'concept'  => $this->loadFromFile(resource_path('assessment_weights/concept_weight.txt')),
                'adjusted' => $this->loadFromFile(resource_path('assessment_weights/adjusted_weight.txt')),
            ];
        });
    }

    /**
     * Build category capacity sums per dictionary (sum of all weights for each category).
     * Returns: [ dict => [ 'A' => int, 'B' => int, 'C' => int, 'D' => int ] ]
     */
    public function capacities(array $dicts): array
    {
        $capacities = [];
        foreach ($dicts as $key => $map) {
            $caps = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
            foreach ($map as $entry) {
                $caps[$entry['cat']] += $entry['w'];
            }
            $capacities[$key] = $caps;
        }
        return $capacities;
    }

    protected function loadFromFile(string $path): array
    {
        $result = [];
        if (!file_exists($path)) {
            Log::warning('Weight file missing', ['path' => $path]);
            return $result;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '//')) {
                continue;
            }
            // Expect format: word,Category weight
            // Example: selfstarter,A 72
            $commaPos = strpos($line, ',');
            if ($commaPos === false) {
                continue;
            }
            $word = substr($line, 0, $commaPos);
            $rest = trim(substr($line, $commaPos + 1));
            $parts = preg_split('/\s+/', $rest);
            if (count($parts) < 2) {
                continue;
            }
            $cat = strtoupper(trim($parts[0]));
            $w   = (int) trim($parts[1]);
            $norm = WordNormalizer::normalize($word);
            if (!in_array($cat, ['A','B','C','D'], true)) {
                continue;
            }
            $result[$norm] = ['cat' => $cat, 'w' => $w];
        }
        return $result;
    }
}
