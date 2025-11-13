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
        $user = $this->option('user');
        $attempt = $this->option('attempt');
        $rebuild = (bool) $this->option('rebuild');

        $query = AssessmentResponse::query();
        if ($user) {
            $query->where('user_id', (int) $user);
        }
        if ($attempt) {
            $query->where('attempt_id', (int) $attempt);
        }

        $groups = $query->select('user_id', 'attempt_id')
            ->groupBy('user_id', 'attempt_id')
            ->orderBy('user_id')
            ->orderBy('attempt_id')
            ->get();

        $this->info('Recomputing for '.$groups->count().' attempt groups...');

        $success = 0;
        $errors = 0;

        foreach ($groups as $group) {
            try {
                if ($rebuild) {
                    AssessmentResult::where('user_id', $group->user_id)
                        ->where('attempt_id', $group->attempt_id)
                        ->delete();
                }

                $results = $service->ensureDualResults((int) $group->user_id, (int) $group->attempt_id);

                if (!empty($results)) {
                    foreach ($results as $res) {
                        $success++;
                        $this->line(sprintf(
                            'OK user=%d attempt=%d aid=%s type=%s -> result=%d',
                            $group->user_id,
                            $group->attempt_id,
                            $res->organization_assessment_id ?? 'null',
                            $res->type ?? 'unknown',
                            $res->id
                        ));
                    }
                } else {
                    $errors++;
                    $this->warn("FAILED user={$group->user_id} attempt={$group->attempt_id}");
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Recompute failed', [
                    'user_id' => $group->user_id,
                    'attempt_id' => $group->attempt_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("ERROR user={$group->user_id} attempt={$group->attempt_id} :: {$e->getMessage()}");
            }
        }

        $this->info("Done. Success={$success}, Errors={$errors}");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
