<?php

namespace App\Console\Commands;

use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Services\AssessmentCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecomputeAssessmentResults extends Command
{
    protected $signature = 'assessments:recompute {--user=} {--attempt=} {--rebuild}';
    protected $description = 'Recompute assessment_results natively without external engine.';

    public function handle(AssessmentCalculationService $service): int
    {
        $userOpt = $this->option('user');
        $attemptOpt = $this->option('attempt');
        $rebuild = (bool) $this->option('rebuild');

        $query = AssessmentResponse::query();
        if ($userOpt) {
            $query->where('user_id', (int)$userOpt);
        }
        if ($attemptOpt) {
            $query->where('attempt_id', (int)$attemptOpt);
        }
        $grouped = $query->select('user_id', 'attempt_id')
            ->groupBy('user_id', 'attempt_id')
            ->orderBy('user_id')
            ->orderBy('attempt_id')
            ->get();

        $this->info('Recomputing for '.$grouped->count().' attempt groups...');

        $count = 0; $errors = 0;
        foreach ($grouped as $g) {
            try {
                if ($rebuild) {
                    AssessmentResult::where('user_id', $g->user_id)
                        ->where('attempt_id', $g->attempt_id)
                        ->delete();
                }
                // Ensure separate results for assessment_id 1 and 2 when present
                $results = $service->ensureDualResults($g->user_id, $g->attempt_id);
                if (count($results) > 0) {
                    foreach ($results as $res) {
                        $count++;
                        $this->line("OK user={$g->user_id} attempt={$g->attempt_id} aid={$res->organization_assessment_id} type={$res->type} -> result={$res->id}");
                    }
                } else {
                    $errors++;
                    $this->warn("FAILED user={$g->user_id} attempt={$g->attempt_id}");
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Recompute failed', ['user_id' => $g->user_id, 'attempt_id' => $g->attempt_id, 'error' => $e->getMessage()]);
                $this->error("ERROR user={$g->user_id} attempt={$g->attempt_id} :: {$e->getMessage()}");
            }
        }

        $this->info("Done. Success={$count}, Errors={$errors}");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
