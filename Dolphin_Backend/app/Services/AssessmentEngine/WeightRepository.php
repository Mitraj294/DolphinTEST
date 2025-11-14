<?php

namespace App\Services\AssessmentEngine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeightRepository
{
    public function all(): array
    {
        return Cache::remember('assessment_weights_v1', 3600, function () {
            $fromDb = $this->loadFromDb();
            if (!empty($fromDb)) {
                Log::info('Loaded assessment weight maps from DB algorithm row');
                return $fromDb;
            }

            Log::warning('No algorithm row found in DB; returning empty weight maps');
            return [
                'self' => [],
                'concept' => [],
                'adjusted' => [],
            ];
        });
    }

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

    private function loadFromDb(): array
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
                    if (empty($item['word'])) {
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
            Log::warning('Failed to load algorithms from DB', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
