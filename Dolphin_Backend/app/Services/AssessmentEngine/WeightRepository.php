<?php

namespace App\Services\AssessmentEngine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * WeightRepository
 *
 * Loads and caches word weight dictionaries from flat files.
 * Each line format: word,Category Weight
 * Example: selfstarter,A 72
 * Produces: [ 'self' => [ 'selfstarter' => ['cat'=>'A','w'=>72], ... ], 'concept' => ..., 'adjusted' => ... ]
 */
class WeightRepository
{
    /**
     * Load and cache weight dictionaries for self, concept, and adjusted.
     * Returns array: [ 'self' => [word => ['cat' => 'A'|'B'|'C'|'D', 'w' => int]], ... ]
     */
    /**
     * Return all dictionaries (cached for 1 hour).
     */
    public function all(): array
    {
        return Cache::remember('assessment_weights_v1', 3600, function () {
            // Load algorithm definitions from the DB only. If unavailable, return empty maps.
            $fromDb = $this->loadFromDb();
            if (!empty($fromDb)) {
                return $fromDb;
            }

            Log::warning('No algorithm row found in DB; returning empty weight maps (DB-only mode)');
            return [
                'self' => [],
                'concept' => [],
                'adjusted' => [],
            ];
        });
    }

    /**
     * Build category capacity sums per dictionary (sum of all weights for each category).
     * Returns: [ dict => [ 'A' => int, 'B' => int, 'C' => int, 'D' => int ] ]
     */
    /**
     * Build total weight capacity per category for each dict.
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

    /**
     * Parse a weight file into an associative array.
     * Ignores blank lines, comment lines starting with //, malformed lines, and invalid categories.
     */
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

    /**
     * Attempt to load algorithm weights from the `algorithms` DB table.
     * Expects a single row with JSON arrays in `self_table`, `conc_table`, `adjust_table`.
     * Returns same shape as file loader: [ 'self' => [norm => ['cat'=>..., 'w'=>...]], ... ]
     */
    protected function loadFromDb(): array
    {
        try {
            $row = DB::table('algorithms')
                ->where('is_global', 1)
                ->orderByDesc('version')
                ->first();

            if (!$row) {
                return [];
            }

            $out = ['self' => [], 'concept' => [], 'adjusted' => []];

            $maps = [
                'self' => $row->self_table ?? null,
                'concept' => $row->conc_table ?? null,
                'adjusted' => $row->adjust_table ?? null,
            ];

            foreach ($maps as $key => $json) {
                if (empty($json)) {
                    continue;
                }
                $items = json_decode($json, true);
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (!isset($item['word'])) {
                        continue;
                    }
                    $word = (string) ($item['word'] ?? '');
                    $w = isset($item['weight']) ? (int) $item['weight'] : 0;
                    $cat = isset($item['category']) ? strtoupper(trim((string) $item['category'])) : null;
                    if (!in_array($cat, ['A','B','C','D'], true)) {
                        continue;
                    }
                    $norm = WordNormalizer::normalize($word);
                    $out[$key][$norm] = ['cat' => $cat, 'w' => $w];
                }
            }

            return $out;
        } catch (\Exception $e) {
            Log::warning('Failed to load algorithms from DB, falling back to files', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
