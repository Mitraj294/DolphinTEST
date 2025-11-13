<?php

namespace App\Services;

use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * AssessmentCalculationService
 *
 * Simplified pipeline to convert stored AssessmentResponse rows into a
 * single persisted AssessmentResult per attempt using the in-project
 * ScoreCalculator and WeightRepository.
 */
class AssessmentCalculationService
{
    /**
     * Calculate (or return existing) result row for a specific attempt.
     * - attempt > 1 => 'adjust', otherwise 'original'
     */
    public function calculateResults(int $userId, int $attemptId): ?AssessmentResult
    {
        try {
            $type = ($attemptId > 1) ? 'adjust' : 'original';

            Log::info('Starting assessment calculation', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'type' => $type
            ]);

            // If a result already exists for this attempt, return it.
            $existingResult = AssessmentResult::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->first();
            if ($existingResult) {
                Log::info('Result already exists for this attempt. Calculation skipped.', ['result_id' => $existingResult->id]);
                return $existingResult;
            }

            $responses = AssessmentResponse::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->get();

            if ($responses->isEmpty()) {
                throw new \RuntimeException("No responses found for user {$userId}, attempt {$attemptId}");
            }

            $selectedWords = $this->extractSelectedWords($responses);

            Log::info('Extracted words', [
                'self_words_count' => count($selectedWords['self_words'] ?? []),
                'concept_words_count' => count($selectedWords['concept_words'] ?? [])
            ]);

            $result = $this->computeResults($selectedWords);

            $assessmentResult = $this->storeResults($userId, $attemptId, $type, $result, $selectedWords);

            Log::info('Assessment calculation completed', ['result_id' => $assessmentResult->id]);

            return $assessmentResult;
        } catch (\Throwable $e) {
            Log::error('Assessment calculation failed', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Split selected words into 'self' and 'concept' buckets.
     * Returns: ['self_words' => string[], 'concept_words' => string[]]
     */
    private function extractSelectedWords(Collection $responses): array
    {
        $selfWords = [];
        $conceptWords = [];

        foreach ($responses as $response) {
            $options = is_array($response->selected_options)
                ? $response->selected_options
                : json_decode($response->selected_options, true);

            if (!is_array($options)) {
                Log::warning('Invalid selected_options format', ['response_id' => $response->id]);
                continue;
            }

            if ($response->assessment_id == 1) {
                $selfWords = array_merge($selfWords, $options);
            } elseif ($response->assessment_id == 2) {
                $conceptWords = array_merge($conceptWords, $options);
            } else {
                // Legacy fallback
                if (empty($selfWords)) {
                    $selfWords = $options;
                } else {
                    $conceptWords = $options;
                }
            }
        }

        return [
            'self_words' => array_values(array_unique($selfWords)),
            'concept_words' => array_values(array_unique($conceptWords))
        ];
    }

    /**
     * Compute ratios and averages for self and concept sets.
     */
    private function computeResults(array $selectedWords): object
    {
        $calculator = $this->getCalculator();

        $selfScores = $calculator->scores($selectedWords['self_words'] ?? [], 'self');
        $concScores = $calculator->scores($selectedWords['concept_words'] ?? [], 'concept');

        $res = new \stdClass();
        $res->self_a = $selfScores['A'];
        $res->self_b = $selfScores['B'];
        $res->self_c = $selfScores['C'];
        $res->self_d = $selfScores['D'];
        $res->self_avg = $selfScores['avg'];

        $res->conc_a = $concScores['A'];
        $res->conc_b = $concScores['B'];
        $res->conc_c = $concScores['C'];
        $res->conc_d = $concScores['D'];
        $res->conc_avg = $concScores['avg'];

        $res->dec_approach = $calculator->decisionApproach($res->self_avg, $res->conc_avg);

        $res->self_total_words = is_array($selectedWords['self_words'] ?? null) ? count($selectedWords['self_words']) : 0;
        $res->conc_total_words = is_array($selectedWords['concept_words'] ?? null) ? count($selectedWords['concept_words']) : 0;
        $res->adj_total_words = 0;

        return $res;
    }

    /**
     * Persist a single AssessmentResult row for the given attempt.
     */
    private function storeResults(int $userId, int $attemptId, string $type, object $result, array $selectedWords): AssessmentResult
    {
        $wordCategories = $this->categorizeWords($selectedWords);

        $adjA = $adjB = $adjC = $adjD = $adjAvg = 0.0;
        $adjCount = 0;
        $adjWordsForStorage = [];

        if ($type === 'adjust') {
            $calculator = $this->getCalculator();
            $wr = $this->getWeightRepository();
            $dicts = $wr->all();
            $adjustedDict = $dicts['adjusted'] ?? [];

            $selfWords = is_array($selectedWords['self_words'] ?? null) ? $selectedWords['self_words'] : [];
            $filteredSelfWords = [];
            foreach ($selfWords as $w) {
                $norm = \App\Services\AssessmentEngine\WordNormalizer::normalize((string) $w);
                if (isset($adjustedDict[$norm])) {
                    $filteredSelfWords[] = $w;
                }
            }

            $adjScores = $calculator->scores($filteredSelfWords, 'adjusted');
            $adjA = $adjScores['A'];
            $adjB = $adjScores['B'];
            $adjC = $adjScores['C'];
            $adjD = $adjScores['D'];
            $adjAvg = $adjScores['avg'];
            $adjCount = count($filteredSelfWords);
            $adjWordsForStorage = $filteredSelfWords;
        }

        $assessmentResult = AssessmentResult::create([
            'organization_assessment_id' => null,
            'user_id' => $userId,
            'attempt_id' => $attemptId,
            'type' => $type,

            'self_a' => $result->self_a ?? 0,
            'self_b' => $result->self_b ?? 0,
            'self_c' => $result->self_c ?? 0,
            'self_d' => $result->self_d ?? 0,
            'self_avg' => $result->self_avg ?? 0,

            'conc_a' => $result->conc_a ?? 0,
            'conc_b' => $result->conc_b ?? 0,
            'conc_c' => $result->conc_c ?? 0,
            'conc_d' => $result->conc_d ?? 0,
            'conc_avg' => $result->conc_avg ?? 0,

            'dec_approach' => $result->dec_approach ?? 0,

            'self_total_count' => $result->self_total_words ?? 0,
            'conc_total_count' => $result->conc_total_words ?? 0,
            'adj_total_count' => $adjCount,

            'self_total_words' => $wordCategories['self_words'],
            'conc_total_words' => $wordCategories['concept_words'],
            'adj_total_words' => $adjWordsForStorage,

            'task_a' => 0, 'task_b' => 0, 'task_c' => 0, 'task_d' => 0, 'task_avg' => 0,

            'adj_a' => $adjA,
            'adj_b' => $adjB,
            'adj_c' => $adjC,
            'adj_d' => $adjD,
            'adj_avg' => $adjAvg,
        ]);

        Log::info('Results stored', ['result_id' => $assessmentResult->id]);

        return $assessmentResult;
    }

    /**
     * Ensure there is one result record for the attempt and return it.
     */
    public function ensureDualResults(int $userId, int $attemptId): array
    {
        $result = $this->calculateResults($userId, $attemptId);
        return $result ? [$result] : [];
    }

    /**
     * Pass-through wrapper to store raw categorized words.
     */
    private function categorizeWords(array $selectedWords): array
    {
        return [
            'self_words' => $selectedWords['self_words'] ?? [],
            'concept_words' => $selectedWords['concept_words'] ?? [],
            'adj_words' => []
        ];
    }

    /**
     * Factory for ScoreCalculator (centralized to simplify future DI/testing).
     */
    private function getCalculator(): \App\Services\AssessmentEngine\ScoreCalculator
    {
        return new \App\Services\AssessmentEngine\ScoreCalculator($this->getWeightRepository());
    }

    /**
     * Factory for WeightRepository.
     */
    private function getWeightRepository(): \App\Services\AssessmentEngine\WeightRepository
    {
        return new \App\Services\AssessmentEngine\WeightRepository();
    }
}

