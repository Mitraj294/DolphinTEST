<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentScheduleRequest;
use App\Jobs\SendAssessmentInvitationsJob;
use App\Models\Group;
use App\Models\OrganizationAssessment;
use App\Models\User;
use App\Notifications\AssessmentInvitation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AssessmentScheduleController extends Controller
{
    public function store(StoreAssessmentScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $assessment = OrganizationAssessment::findOrFail($validated['assessment_id']);



            $tz = $validated['timezone'] ?? config('app.timezone');
            $sendAt = null;
            if (!empty($validated['send_at'])) {
                try {

                    $sendAt = Carbon::parse($validated['send_at'])->setTimezone($tz);
                } catch (\Throwable $e) {
                    $sendAt = null;
                }
            }


            if (empty($sendAt) && !empty($validated['date'])) {
                $timePart = !empty($validated['time']) ? $validated['time'] : '00:00:00';
                try {
                    $sendAt = Carbon::parse($validated['date'] . ' ' . $timePart, $tz)->setTimezone($tz);
                } catch (\Throwable $e) {
                    $sendAt = null;
                }
            }




            if ($sendAt) {
                $assessment->date = $sendAt->toDateString();
                $assessment->time = $sendAt->format('H:i:s');
                if (Schema::hasColumn('organization_assessments', 'send_at')) {



                    $assessment->send_at = $sendAt->clone()->setTimezone('UTC');
                }
            } else {
                $assessment->date = $validated['date'] ?? null;
                $assessment->time = $validated['time'] ?? null;
            }

            if (Schema::hasColumn('organization_assessments', 'timezone')) {
                $assessment->timezone = $tz;
            }

            DB::transaction(function () use ($assessment, $validated, $sendAt) {
                $assessment->save();


                if (!empty($validated['group_ids']) && is_array($validated['group_ids'])) {
                    $assessment->groups()->syncWithoutDetaching($validated['group_ids']);
                }


                $memberIds = collect($validated['member_ids'] ?? []);


                if (!empty($validated['group_ids']) && is_array($validated['group_ids'])) {
                    $groupUsers = Group::whereIn('id', $validated['group_ids'])
                        ->with('users:id')
                        ->get()
                        ->flatMap(fn ($g) => $g->users->pluck('id'));
                    $memberIds = $memberIds->merge($groupUsers);
                }

                $memberIds = $memberIds->unique()->filter()->values();

                if ($memberIds->isNotEmpty()) {

                    $attachData = [];
                    foreach ($memberIds as $uid) {
                        $attachData[$uid] = ['status' => 'Pending'];
                    }
                    $assessment->members()->syncWithoutDetaching($attachData);








                    Log::info('[AssessmentScheduleController] schedule persisted; will be processed by scheduler', [
                        'assessment_id' => $assessment->id,
                        'sendAt' => $sendAt ? $sendAt->toISOString() : null,
                    ]);
                }
            });


            $notifiedIds = [];
            if (Schema::hasColumn('organization_assessment_member', 'notified_at')) {
                $notifiedIds = DB::table('organization_assessment_member')
                    ->where('organization_assessment_id', $assessment->id)
                    ->whereNotNull('notified_at')
                    ->pluck('user_id')
                    ->toArray();
            }


            $assessment->loadMissing('groups', 'members');


            $schedulePayload = [
                'date' => (string) $assessment->date,
                'time' => (string) $assessment->time,
                'timezone' => $assessment->timezone ?? $tz,
                'group_ids' => $assessment->groups->pluck('id')->toArray(),
                'member_ids' => $assessment->members->pluck('id')->toArray(),
                'notified_member_ids' => $notifiedIds,
            ];


            if (Schema::hasColumn('organization_assessments', 'send_at') && $assessment->send_at) {
                try {
                    $schedulePayload['send_at'] = Carbon::parse($assessment->send_at)->toISOString();
                } catch (\Throwable $e) {
                    $schedulePayload['send_at'] = (string) $assessment->send_at;
                }
            }

            $resp = [
                'assessment' => [
                    'id' => $assessment->id,
                    'name' => $assessment->name ?? null,
                ],
                'schedule' => $schedulePayload,
            ];

            return response()->json($resp, 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Assessment not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('Failed to store assessment schedule', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to schedule assessment.'], 500);
        }
    }


    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'assessment_id' => 'required|integer|exists:organization_assessments,id',
        ]);

        try {

            $assessment = OrganizationAssessment::with(['groups.users', 'members'])
                ->findOrFail($request->query('assessment_id'));


            $notifiedIds = [];
            if (Schema::hasColumn('organization_assessment_member', 'notified_at')) {
                $notifiedIds = DB::table('organization_assessment_member')
                    ->where('organization_assessment_id', $assessment->id)
                    ->whereNotNull('notified_at')
                    ->pluck('user_id')
                    ->toArray();
            }


            $groupsWithMembers = [];
            foreach ($assessment->groups as $g) {
                $members = [];
                if ($g->relationLoaded('users')) {
                    foreach ($g->users as $u) {
                        $members[] = [
                            'id' => $u->id,
                            'email' => $u->email ?? null,
                            'first_name' => $u->first_name ?? null,
                            'last_name' => $u->last_name ?? null,

                            'member_role' => $u->pivot && isset($u->pivot->role) ? $u->pivot->role : null,
                        ];
                    }
                }

                $groupsWithMembers[] = [
                    'id' => $g->id,
                    'name' => $g->name ?? null,
                    'members' => $members,
                ];
            }


            $membersWithDetails = [];
            foreach ($assessment->members as $m) {
                $membersWithDetails[] = [
                    'id' => $m->id,
                    'email' => $m->email ?? null,
                    'first_name' => $m->first_name ?? null,
                    'last_name' => $m->last_name ?? null,

                    'member_roles' => [],
                ];
            }

            $payload = [
                'assessment_id' => $assessment->id,
                'date' => optional($assessment->date)->toDateString() ?? (string) $assessment->date,
                'time' => $assessment->time instanceof Carbon ? $assessment->time->format('H:i:s') : (string) $assessment->time,
                'timezone' => $assessment->timezone ?? null,
                'group_ids' => $assessment->groups->pluck('id')->toArray(),
                'member_ids' => $assessment->members->pluck('id')->toArray(),
                'notified_member_ids' => $notifiedIds,

                'groups_with_members' => $groupsWithMembers,
                'members_with_details' => $membersWithDetails,

                'emails' => [],
                'notifications' => [],
            ];

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::warning('[AssessmentScheduleController@show] failed', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }
}
