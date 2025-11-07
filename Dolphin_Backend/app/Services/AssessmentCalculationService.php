<?php

namespace App\Services;

use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentCalculationService
{
    protected $dolphinPath;
    protected $pythonPath;
    protected $dolphinDbConnection;

    public function __construct()
    {
        // Path to your dolphin-project-main folder
        $this->dolphinPath = base_path('../dolphin-project-main');
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->dolphinDbConnection = 'dolphin_clean';
    }

    /**
     * Calculate assessment results using C++ algorithm
     * 
     * @param int $userId
     * @param int $attemptId
     * @param int|null $organizationAssessmentId
     * @return object|null
     * @throws \Exception
     */
    public function calculateResults($userId, $attemptId, $organizationAssessmentId = null)
    {
        try {
            Log::info('Starting assessment calculation', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'assessment_id' => $organizationAssessmentId
            ]);

            // Get user's selected words from assessment_responses
            $responses = AssessmentResponse::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->get();

            if ($responses->isEmpty()) {
                throw new \Exception("No responses found for user {$userId}, attempt {$attemptId}");
            }

            // Check if result already exists for this attempt
            $existingResult = AssessmentResult::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->first();

            if ($existingResult) {
                Log::info('Result already exists for this attempt', [
                    'result_id' => $existingResult->id,
                    'attempt_id' => $attemptId
                ]);
                return $existingResult;
            }

            // Extract selected words from responses
            $selectedWords = $this->extractSelectedWords($responses);

            Log::info('Extracted words', [
                'self_words_count' => count($selectedWords['self_words']),
                'concept_words_count' => count($selectedWords['concept_words'])
            ]);

            // Get user email
            $user = User::findOrFail($userId);
            $email = $user->email;

            // Write to database input table for C++ program
            $this->prepareInputData($email, $selectedWords);

            // Run C++ algorithm
            $result = $this->runDolphinAlgorithm($email);

            // Store results in assessment_results table
            $assessmentResult = $this->storeResults($userId, $attemptId, $organizationAssessmentId, $result, $selectedWords);

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
            
            // Don't throw exception - return null to allow response saving to continue
            return null;
        }
    }

    /**
     * Extract selected words from assessment responses
     * 
     * @param \Illuminate\Support\Collection $responses
     * @return array
     */
    protected function extractSelectedWords($responses)
    {
        $selfWords = [];
        $conceptWords = [];

        foreach ($responses as $response) {
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

            // Determine if this is self or concept words based on assessment_id
            // Assessment ID 1 = Self words, Assessment ID 2 = Concept words
            // Adjust these IDs based on your actual assessment structure
            if ($response->assessment_id == 1) {
                $selfWords = array_merge($selfWords, $options);
            } elseif ($response->assessment_id == 2) {
                $conceptWords = array_merge($conceptWords, $options);
            } else {
                // If you have different assessment IDs, adjust this logic
                // For now, alternate between self and concept
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
     * Prepare input data in MySQL for C++ program
     * 
     * @param string $email
     * @param array $selectedWords
     * @return void
     */
    protected function prepareInputData($email, $selectedWords)
    {
        try {
            // Insert into 'input' table that C++ program reads from
            DB::connection($this->dolphinDbConnection)->table('input')->updateOrInsert(
                ['email' => $email],
                [
                    'self_words' => json_encode($selectedWords['self_words']),
                    'concept_words' => json_encode($selectedWords['concept_words'])
                ]
            );

            Log::info('Input data prepared for C++ algorithm', [
                'email' => $email,
                'self_words_count' => count($selectedWords['self_words']),
                'concept_words_count' => count($selectedWords['concept_words'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to prepare input data', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to prepare input data: " . $e->getMessage());
        }
    }

    /**
     * Run the C++ dolphin algorithm
     * 
     * @param string $email
     * @return object
     * @throws \Exception
     */
    protected function runDolphinAlgorithm($email)
    {
        // Change to dolphin directory and run the algorithm
        $command = sprintf(
            "cd %s && ./dolphin '%s' 2>&1",
            escapeshellarg($this->dolphinPath),
            escapeshellarg($email)
        );

        Log::info('Running dolphin algorithm', [
            'command' => $command,
            'email' => $email
        ]);

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        $outputString = implode("\n", $output);

        if ($returnVar !== 0) {
            Log::error('Dolphin algorithm execution failed', [
                'return_code' => $returnVar,
                'output' => $outputString
            ]);
            throw new \Exception("Dolphin algorithm failed with code {$returnVar}: {$outputString}");
        }

        Log::info('Dolphin algorithm executed successfully', [
            'email' => $email,
            'output_lines' => count($output)
        ]);

        // Read results from MySQL 'results' table
        $result = DB::connection($this->dolphinDbConnection)
            ->table('results')
            ->where('email', $email)
            ->first();

        if (!$result) {
            throw new \Exception("No results generated for {$email}. Algorithm may have failed silently.");
        }

        return $result;
    }

    /**
     * Store results in assessment_results table
     * 
     * @param int $userId
     * @param int $attemptId
     * @param int|null $organizationAssessmentId
     * @param object $result
     * @param array $selectedWords
     * @return AssessmentResult
     */
    protected function storeResults($userId, $attemptId, $organizationAssessmentId, $result, $selectedWords)
    {
        // Determine type: 'original' for first test, 'adjust' for retakes
        $existingResults = AssessmentResult::where('user_id', $userId)->count();
        $type = $existingResults > 0 ? 'adjust' : 'original';

        // Parse word arrays from the output file or use the input words categorized
        $wordCategories = $this->categorizeWords($selectedWords);

        $assessmentResult = AssessmentResult::create([
            'organization_assessment_id' => $organizationAssessmentId,
            'user_id' => $userId,
            'attempt_id' => $attemptId,
            'type' => $type,
            // Self scores
            'self_a' => $result->self_a ?? 0,
            'self_b' => $result->self_b ?? 0,
            'self_c' => $result->self_c ?? 0,
            'self_d' => $result->self_d ?? 0,
            'self_avg' => $result->self_avg ?? 0,
            // Concept scores
            'conc_a' => $result->conc_a ?? 0,
            'conc_b' => $result->conc_b ?? 0,
            'conc_c' => $result->conc_c ?? 0,
            'conc_d' => $result->conc_d ?? 0,
            'conc_avg' => $result->conc_avg ?? 0,
            // Decision approach
            'dec_approach' => $result->dec_approach ?? 0,
            // Counts
            'self_total_count' => $result->self_total_words ?? 0,
            'conc_total_count' => $result->conc_total_words ?? 0,
            'adj_total_count' => $result->adj_total_words ?? 0,
            // Word arrays
            'self_total_words' => $wordCategories['self_words'],
            'conc_total_words' => $wordCategories['concept_words'],
            'adj_total_words' => $wordCategories['adj_words'] ?? [],
            // Task scores (if available)
            'task_a' => 0,
            'task_b' => 0,
            'task_c' => 0,
            'task_d' => 0,
            'task_avg' => 0,
            // Adjusted scores (if available)
            'adj_a' => 0,
            'adj_b' => 0,
            'adj_c' => 0,
            'adj_d' => 0,
            'adj_avg' => 0,
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
     * Categorize words (placeholder - can be enhanced to read from output file)
     * 
     * @param array $selectedWords
     * @return array
     */
    protected function categorizeWords($selectedWords)
    {
        // For now, return the input words
        // You can enhance this to parse the output file and categorize words into A, B, C, D
        return [
            'self_words' => $selectedWords['self_words'],
            'concept_words' => $selectedWords['concept_words'],
            'adj_words' => []
        ];
    }

    /**
     * Parse words from the output text file (optional enhancement)
     * 
     * @param string $filePath
     * @param string $section
     * @return array
     */
    protected function parseWordsFromOutputFile($filePath, $section = 'self')
    {
        if (!file_exists($filePath)) {
            Log::warning('Output file not found', ['path' => $filePath]);
            return [];
        }

        $content = file_get_contents($filePath);
        $words = [];

        // Parse based on section markers in the output file
        // Example: "---------- Self A words ----------"
        $pattern = '/---------- ' . ucfirst($section) . ' \w words ----------\n(.*?)\n---------------------------------/s';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $match) {
                $sectionWords = array_filter(array_map('trim', explode("\n", $match)));
                $words = array_merge($words, $sectionWords);
            }
        }

        return array_unique($words);
    }

    /**
     * Check if the C++ executable exists
     * 
     * @return bool
     */
    public function isDolphinExecutableAvailable()
    {
        $executablePath = $this->dolphinPath . '/dolphin';
        return file_exists($executablePath) && is_executable($executablePath);
    }

    /**
     * Build the C++ executable if not present
     * 
     * @return bool
     */
    public function buildDolphinExecutable()
    {
        if ($this->isDolphinExecutableAvailable()) {
            return true;
        }

        $command = sprintf(
            "cd %s && bash make.sh 2>&1",
            escapeshellarg($this->dolphinPath)
        );

        Log::info('Building dolphin executable', ['command' => $command]);

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('Failed to build dolphin executable', [
                'return_code' => $returnVar,
                'output' => implode("\n", $output)
            ]);
            return false;
        }

        return $this->isDolphinExecutableAvailable();
    }
}
