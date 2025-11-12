<?php

namespace App\Services;

use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentCalculationService
{
    protected string $dolphinPath;
    protected string $pythonPath;
    protected string $dolphinDbConnection;

    public function __construct()
    {
        
        $this->dolphinPath = base_path('../dolphin-project-main');
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->dolphinDbConnection = 'dolphin_clean';
    }

    
    public function calculateResults(int $userId, int $attemptId, ?int $organizationAssessmentId = null): ?AssessmentResult
    {
        try {
            Log::info('Starting assessment calculation', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'assessment_id' => $organizationAssessmentId
            ]);

            
            $responses = AssessmentResponse::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->get();

            if ($responses->isEmpty()) {
                throw new \Exception("No responses found for user {$userId}, attempt {$attemptId}");
            }

            
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

            
            $selectedWords = $this->extractSelectedWords($responses);

            Log::info('Extracted words', [
                'self_words_count' => count($selectedWords['self_words']),
                'concept_words_count' => count($selectedWords['concept_words'])
            ]);

            
            $user = User::findOrFail($userId);
            $email = $user->email;

            
            $this->prepareInputData($email, $selectedWords);

            
            $result = $this->runDolphinAlgorithm($email);

            
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

            
            return null;
        }
    }

    
    protected function extractSelectedWords(Collection $responses): array
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

            
            
            
            if ($response->assessment_id == 1) {
                $selfWords = array_merge($selfWords, $options);
            } elseif ($response->assessment_id == 2) {
                $conceptWords = array_merge($conceptWords, $options);
            } else {
                
                
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

    
    
    protected function prepareInputData(string $email, array $selectedWords): void
    {
        try {
            
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

    
    protected function runDolphinAlgorithm(string $email): object
    {
        
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

        
        $result = DB::connection($this->dolphinDbConnection)
            ->table('results')
            ->where('email', $email)
            ->first();

        if (!$result) {
            throw new \Exception("No results generated for {$email}. Algorithm may have failed silently.");
        }

        return $result;
    }

    
    
    protected function storeResults(int $userId, int $attemptId, ?int $organizationAssessmentId, object $result, array $selectedWords): AssessmentResult
    {
        
        $existingResults = AssessmentResult::where('user_id', $userId)->count();
        $type = $existingResults > 0 ? 'adjust' : 'original';

        
        $wordCategories = $this->categorizeWords($selectedWords);

        $assessmentResult = AssessmentResult::create([
            'organization_assessment_id' => $organizationAssessmentId,
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
            'adj_total_count' => $result->adj_total_words ?? 0,
            
            'self_total_words' => $wordCategories['self_words'],
            'conc_total_words' => $wordCategories['concept_words'],
            'adj_total_words' => $wordCategories['adj_words'],
            
            'task_a' => 0,
            'task_b' => 0,
            'task_c' => 0,
            'task_d' => 0,
            'task_avg' => 0,
            
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

    
    
    protected function categorizeWords(array $selectedWords): array
    {
        
        
        return [
            'self_words' => $selectedWords['self_words'],
            'concept_words' => $selectedWords['concept_words'],
            'adj_words' => []
        ];
    }

    
    
    
    

    
    public function isDolphinExecutableAvailable(): bool
    {
        $executablePath = $this->dolphinPath . '/dolphin';
        return file_exists($executablePath) && is_executable($executablePath);
    }

    
    public function buildDolphinExecutable(): bool
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
