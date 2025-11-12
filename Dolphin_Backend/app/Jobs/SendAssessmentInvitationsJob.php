<?php
namespace App\Jobs;
use App\Models\OrganizationAssessment;
use App\Models\User;
use App\Notifications\AssessmentInvitation;
use App\Services\UrlBuilder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class SendAssessmentInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $assessmentId;
    public function __construct(int $assessmentId)
    {
        $this->assessmentId = $assessmentId;
    }
    public function handle(): void
    {
        $assessment = OrganizationAssessment::with(['members'])->find($this->assessmentId);
        if (!$assessment) {
            Log::warning('[SendAssessmentInvitationsJob] Assessment not found', ['id' => $this->assessmentId]);
            return;
        }
        $link = UrlBuilder::assessmentsUrl($assessment->id);
        $notif = new AssessmentInvitation($link, $assessment->name, $assessment->id);
        $now = Carbon::now();
        $memberIds = $assessment->members()->pluck('users.id');
        if ($memberIds->isEmpty()) {
            Log::info('[SendAssessmentInvitationsJob] No members to notify', ['assessment_id' => $assessment->id]);
            return;
        }
        DB::transaction(function () use ($memberIds, $notif, $assessment, $now) {
            User::whereIn('id', $memberIds)->get()->each(function (User $user) use ($notif, $assessment, $now) {
                try {
                    $user->notify(clone $notif);
                    $assessment->members()->updateExistingPivot($user->id, ['notified_at' => $now]);
                } catch (\Throwable $e) {
                    Log::error('[SendAssessmentInvitationsJob] Failed notifying user', [
                        'user_id' => $user->id,
                        'assessment_id' => $assessment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        });
    }
}
