<?php

namespace App\Console\Commands;

use App\Jobs\SendAssessmentInvitationsJob;
use App\Models\OrganizationAssessment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledAssessments extends Command
{
    protected $signature = 'assessments:process-scheduled';


    protected $description = 'Process organization assessments that are due to be sent (send_at).';

    public function handle(): int
    {
        $now = Carbon::now();


        $due = OrganizationAssessment::whereNotNull('send_at')
            ->where('send_at', '<=', $now)
            ->with(['members'])
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled assessments to process.');
            return 0;
        }

        foreach ($due as $assessment) {
            try {

                $pending = $assessment->members()->whereNull('organization_assessment_member.notified_at')->exists();
                if (! $pending) {

                    continue;
                }


                dispatch(new SendAssessmentInvitationsJob($assessment->id));
                Log::info('[ProcessScheduledAssessments] dispatched job', ['assessment_id' => $assessment->id]);
                $this->info("Dispatched assessment {$assessment->id}");
            } catch (\Throwable $e) {
                Log::error('[ProcessScheduledAssessments] failed to dispatch', ['assessment_id' => $assessment->id, 'error' => $e->getMessage()]);
                $this->error("Failed to dispatch assessment {$assessment->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
