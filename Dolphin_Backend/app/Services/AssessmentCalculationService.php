<?php

namespace App\Services;

use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AssessmentCalculationService
 *
 * Clean, self-contained pipeline to turn stored AssessmentResponse rows
 * (selected words per user/attempt) into persisted AssessmentResult rows.
 *
 * High-level flow:
 *   1) Fetch responses for user + attempt
 *   2) Split selected words by assessment type (self/original vs concept/adjust)
 *   3) Compute category ratios (A,B,C,D) + averages via ScoreCalculator
 *   4) Store a normalized AssessmentResult row (one per type)
 *
 * Notes:
 * - This native calculator does not depend on external engines (C++/Python).
 * - Weight dictionaries live in resources/assessment_weights/*.txt
 * - WordNormalizer ensures synonyms/hyphenation map consistently to weights
 */
class AssessmentCalculationService
{
    // Deprecated external engine paths (kept for BC but unused)
    protected string $dolphinPath;
    protected string $pythonPath;
    protected string $dolphinDbConnection;

    public function __construct()
    {
        // Keep defaults but we'll not use them anymore
        $this->dolphinPath = base_path('../dolphin-project-main');
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->dolphinDbConnection = 'dolphin_clean';
    }

    
    /**
     * Calculate (or return existing) result row for a specific assessment_id within an attempt.
     * assessment_id=1 => original, assessment_id=2 => adjust.
     */
    /**
     * Calculate (or return existing) result row for a specific assessment within an attempt.
     *
     * Contract:
     * - Inputs: userId, attemptId, assessmentId (1=self/original, 2=concept/adjust)
     * - Output: AssessmentResult or null on failure
     * - Error modes: returns null and logs on missing responses or exceptions
     */
    public function calculateResults(int $userId, int $attemptId): ?AssessmentResult
    {
        try {
            // Determine the result type based on the attempt number.
            $type = ($attemptId > 1) ? 'adjust' : 'original';

            Log::info('Starting assessment calculation', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'type' => $type
            ]);

            // A single attempt should only have one result row. Check if it already exists.
            $existingResult = AssessmentResult::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->first();
            if ($existingResult) {
                Log::info('Result already exists for this attempt. Calculation skipped.', [
                    'result_id' => $existingResult->id,
                    'attempt_id' => $attemptId,
                    'type' => $existingResult->type
                ]);
                return $existingResult;
            }

            // Pull all responses for this user/attempt (for both assessment_id 1 and 2).
            $responses = AssessmentResponse::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->get();

            if ($responses->isEmpty()) {
                throw new \Exception("No responses found for user {$userId}, attempt {$attemptId}");
            }

            // Gather selected words, separating them into 'self' and 'concept' buckets.
            $selectedWords = $this->extractSelectedWords($responses);

            Log::info('Extracted words', [
                'self_words_count' => count($selectedWords['self_words']),
                'concept_words_count' => count($selectedWords['concept_words'])
            ]);

            // Compute scores for self and concept words.
            $result = $this->computeResults($selectedWords);

            // Store a single result for this attempt with the correct type.
            $assessmentResult = $this->storeResults($userId, $attemptId, $type, $result, $selectedWords);

            Log::info('Assessment calculation completed successfully', [
                'user_id' => $userId,
                'result_id' => $assessmentResult->id
            ]);

            return $assessmentResult;
        } catch (\Exception $e) {
            Log::error('Assessment calculation failed', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    
    /**
     * Split selected words into two buckets based on assessment_id
     * - assessment_id=1 => self words ("original")
     * - assessment_id=2 => concept words (inputs for adjustments)
     * Returns array: ['self_words' => string[], 'concept_words' => string[]]
     */
    protected function extractSelectedWords(Collection $responses): array
    {
        $selfWords = [];
        $conceptWords = [];

        foreach ($responses as $response) {
            // selected_options may be JSON string or array
            $options = is_array($response->selected_options)
                ? $response->selected_options
                : json_decode($response->selected_options, true);

            if (!is_array($options)) {
                Log::warning('Invalid selected_options format', [
                    'response_id' => $response->id,
                    'selected_options' => $response->selected_options
                ]);
                continue;
            }

            // assessment_id 1 => self, 2 => concept
            if ($response->assessment_id == 1) {
                $selfWords = array_merge($selfWords, $options);
            } elseif ($response->assessment_id == 2) {
                $conceptWords = array_merge($conceptWords, $options);
            } else {
                // Fallback for legacy/unknown values: first set goes to self, second to concept
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
     * New native calculator computing all metrics without external systems.
     */
    /**
     * Compute category ratios for self and concept dictionaries, plus a decision approach.
     * Returns a simple stdClass with fields matching AssessmentResult columns (self_*, conc_*...).
     */
    protected function computeResults(array $selectedWords): object
    {
        $calculator = new \App\Services\AssessmentEngine\ScoreCalculator(new \App\Services\AssessmentEngine\WeightRepository());

        $selfScores = $calculator->scores($selectedWords['self_words'] ?? [], 'self');
        $concScores = $calculator->scores($selectedWords['concept_words'] ?? [], 'concept');

        // Prepare a plain object with properties matching previous external 'results' row
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

        // Totals are counts of selected words
        $res->self_total_words = is_array($selectedWords['self_words'] ?? null) ? count($selectedWords['self_words']) : 0;
        $res->conc_total_words = is_array($selectedWords['concept_words'] ?? null) ? count($selectedWords['concept_words']) : 0;
        $res->adj_total_words  = 0; // will be set when storing if needed

        return $res;
    }

    
    
    /**
     * Persist a single AssessmentResult row for the given attempt.
     * The type ('original' or 'adjust') is passed in, determined by the attempt_id.
     */
    protected function storeResults(int $userId, int $attemptId, string $type, object $result, array $selectedWords): AssessmentResult
    {
        // Categorized words are stored as JSON arrays for transparency/debugging
        $wordCategories = $this->categorizeWords($selectedWords);

    // Compute adjusted scores only for 'adjust' type attempts.
        // Important: adjusted scores should be computed only from the subset of
        // user-selected self words that exist in the algorithm's adjust table.
        $adjA = 0; $adjB = 0; $adjC = 0; $adjD = 0; $adjAvg = 0; $adjCount = 0;
    // Prepare adj words storage (used later when persisting)
    $adjWordsForStorage = [];

    if ($type === 'adjust') {
            $calculator = new \App\Services\AssessmentEngine\ScoreCalculator(new \App\Services\AssessmentEngine\WeightRepository());

            // Load adjusted dictionary keys so we can intersect with user's self words.
            $wr = new \App\Services\AssessmentEngine\WeightRepository();
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

            // Use only filtered words for adjusted scoring and counts.
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
            // For 'adjust' type, adj_total_words should contain the words used for adj scores.
            'adj_total_words' => $adjWordsForStorage,

            'task_a' => 0, 'task_b' => 0, 'task_c' => 0, 'task_d' => 0, 'task_avg' => 0,
            
            'adj_a' => $adjA,
            'adj_b' => $adjB,
            'adj_c' => $adjC,
            'adj_d' => $adjD,
            'adj_avg' => $adjAvg,
        ]);

        Log::info('Results stored in assessment_results table', [
            'result_id' => $assessmentResult->id,
            'user_id' => $userId,
            'attempt_id' => $attemptId,
            'type' => $type
        ]);

        return $assessmentResult;
    }

    /**
     * This method now serves as the primary entry point for calculating a single result for an attempt.
     * It ensures one result record is created per attempt, with the type determined by the attempt number.
     */
    public function ensureDualResults(int $userId, int $attemptId): array
    {
        $result = $this->calculateResults($userId, $attemptId);
        return $result ? [$result] : [];
    }

    
    
    /**
     * Currently a pass-through wrapper to store raw word lists.
     * Kept for future expansion (e.g., by-category grouping or validation).
     */
    protected function categorizeWords(array $selectedWords): array
    {
        return [
            'self_words' => $selectedWords['self_words'],
            'concept_words' => $selectedWords['concept_words'],
            'adj_words' => []
        ];
    }

    
    
    
    

    
    /**
     * Back-compat hook: external engine no longer required.
     */
    public function isDolphinExecutableAvailable(): bool
    {
        // Always true: native calculator is built-in
        return true;
    }

    
    /**
     * Back-compat hook: external engine build no-op.
     */
    public function buildDolphinExecutable(): bool
    {
        // No-op: external build is deprecated
        return true;
    }
}
