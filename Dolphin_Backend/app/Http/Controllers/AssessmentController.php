<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAssessmentRequest;
use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Organization;
use App\Models\OrganizationAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    public function index(IndexAssessmentRequest $request): JsonResponse
    {
        return $this->show($request);
    }
    public function show(IndexAssessmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $query = OrganizationAssessment::select('id', 'name', 'organization_id');

            if (isset($validated['organization_id'])) {
                $query->where('organization_id', $validated['organization_id']);
            } elseif ($request->user()) {
                $query->where('user_id', $request->user()->id);
            } elseif (isset($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            } else {
                return response()->json(['assessments' => []]);
            }

            $assessments = $query->get();
            Log::info('[AssessmentController@show] Assessments returned', ['count' => $assessments->count()]);

            return response()->json(['assessments' => $assessments]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessments.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve assessments.'], 500);
        }
    }
    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();


            Log::info('[AssessmentController@store] payload', ['validated' => $validated]);


            $user = $request->user();
            if (!$user) {
                Log::warning('[AssessmentController@store] unauthenticated request');
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $orgId = $this->resolveOrganizationId($request, $validated);

            $assessment = OrganizationAssessment::create([
                'name' => $validated['name'],
                'user_id' => $user->id,
                'organization_id' => $orgId,

                'date' => $validated['date'] ?? null,
                'time' => $validated['time'] ?? null,
            ]);


            return response()->json(['assessment' => $assessment], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create assessment.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create assessment.'], 500);
        }
    }
    public function summary($id): JsonResponse
    {
        try {
            $assessment = OrganizationAssessment::findOrFail($id);

            $assignedMembers = DB::table('organization_assessment_member as oam')
                ->join('users as u', 'u.id', '=', 'oam.user_id')
                ->where('oam.organization_assessment_id', $assessment->id)
                ->select(
                    'oam.user_id',
                    'oam.status',
                    'oam.created_at as assigned_at',
                    'oam.notified_at',
                    'u.first_name',
                    'u.last_name',
                    'u.email'
                )
                ->get();

            $memberIds = $assignedMembers->pluck('user_id')->unique()->values();

            $responses = $memberIds->isEmpty()
                ? collect()
                : DB::table('assessment_responses')
                    ->where('organization_assessment_id', $assessment->id)
                    ->whereIn('user_id', $memberIds)
                    ->orderBy('created_at', 'desc')
                    ->get();

            $responsesByUser = $responses->groupBy('user_id');

            $members = $assignedMembers->map(function ($memberRow) use ($responsesByUser) {
                $name = trim(($memberRow->first_name ?? '') . ' ' . ($memberRow->last_name ?? ''));
                if ($name === '') {
                    $name = $memberRow->email ?: 'Unknown';
                }

                $memberResponses = $responsesByUser->get($memberRow->user_id) ?? collect();
                $attempts = $memberResponses->groupBy('attempt_id')->map(function ($attemptRows, $attemptId) {
                    $latest = $attemptRows->sortByDesc('created_at')->first();
                    return [
                        'attempt_id' => $attemptId,
                        'submitted_at' => $latest->created_at ?? null,
                        'selected_options' => $attemptRows
                            ->map(function ($row) {
                                return $this->decodeSelectedOptions($row->selected_options);
                            })
                            ->filter()
                            ->values()
                            ->toArray(),
                    ];
                })->values();

                $lastSubmitted = $memberResponses->isNotEmpty()
                    ? $memberResponses->sortByDesc('created_at')->first()->created_at
                    : null;

                return [
                    'member_id' => $memberRow->user_id,
                    'user_id' => $memberRow->user_id,
                    'name' => $name,
                    'email' => $memberRow->email,
                    'assigned_at' => $memberRow->assigned_at,
                    'status' => $memberRow->status,
                    'notified_at' => $memberRow->notified_at,
                    'submitted' => $memberResponses->isNotEmpty(),
                    'last_submitted_at' => $lastSubmitted,
                    'attempts' => $attempts->toArray(),
                ];
            })->toArray();

            $totalSent = $assignedMembers->count();
            $submittedCount = collect($members)->where('submitted', true)->count();
            $pendingCount = max(0, $totalSent - $submittedCount);

            return response()->json([
                'organization_assessment' => [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                    'organization_id' => $assessment->organization_id,
                    'date' => $assessment->date ? $assessment->date->toDateString() : null,
                    'time' => $assessment->time,
                ],
                'summary' => [
                    'total_sent' => $totalSent,
                    'submitted' => $submittedCount,
                    'pending' => $pendingCount,
                ],
                'members' => $members,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Assessment not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to generate assessment summary.', ['assessment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to generate assessment summary.'], 500);
        }
    }
    public function assignedCount(): JsonResponse
    {
        try {
            $user = request()->user();
            if (!$user) {
                return response()->json(['count' => 0]);
            }

            $count = \Illuminate\Support\Facades\DB::table('organization_assessment_member')
                ->where('user_id', $user->id)
                ->count();

            return response()->json(['count' => (int)$count]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[AssessmentController@assignedCount] failed', ['error' => $e->getMessage()]);
            return response()->json(['count' => 0], 500);
        }
    }
    public function assignedList(): JsonResponse
    {
        try {
            $user = request()->user();
            if (!$user) {
                return response()->json(['assigned' => []]);
            }

            $rows = \Illuminate\Support\Facades\DB::table('organization_assessment_member as oam')
                ->join('organization_assessments as oa', 'oa.id', '=', 'oam.organization_assessment_id')
                ->where('oam.user_id', $user->id)
                ->select('oa.id', 'oa.name', 'oam.created_at as assigned_at')
                ->orderBy('oam.created_at', 'desc')
                ->get();

            return response()->json(['assigned' => $rows]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[AssessmentController@assignedList] failed', ['error' => $e->getMessage()]);
            return response()->json(['assigned' => []], 500);
        }
    }
    private function decodeSelectedOptions($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveOrganizationId(StoreAssessmentRequest $request, array $validated): ?int
    {
        if (isset($validated['organization_id'])) {
            return $validated['organization_id'];
        }

        if ($request->user()) {
            $organization = Organization::where('user_id', $request->user()->id)->first();
            return $organization ? $organization->id : null;
        }

        return null;
    }
}
