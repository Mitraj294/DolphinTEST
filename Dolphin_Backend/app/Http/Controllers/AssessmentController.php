<?php

namespace App\Http\Controllers;

use App\Models\OrganizationAssessment;
use App\Models\User;
use App\Models\Organization;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\IndexAssessmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{

    // Display a listing of the resource.
    // @param  IndexAssessmentRequest  $request
    // @return JsonResponse

    public function index(IndexAssessmentRequest $request): JsonResponse
    {
        return $this->show($request);
    }


    // Display the specified resource.
    // @param  IndexAssessmentRequest  $request
    // @return JsonResponse

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


    // Store a newly created resource in storage.
    // @param  StoreAssessmentRequest  $request
    // @return JsonResponse

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // log incoming validated payload to help diagnose 500s in dev
            Log::info('[AssessmentController@store] payload', ['validated' => $validated]);

            // Defensive check: ensure authenticated user is present
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
                // store optional scheduling information if provided
                'date' => $validated['date'] ?? null,
                'time' => $validated['time'] ?? null,
            ]);

            // Do not attach questions on assessment creation; assessments are standalone records
            return response()->json(['assessment' => $assessment], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create assessment.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create assessment.'], 500);
        }
    }

    // @param  int  $id
    // @return JsonResponse

    public function summary($id): JsonResponse
    {
        try {
            $assessment = OrganizationAssessment::findOrFail($id);

            // Get responses from assessment_responses table (not the old assessment_question_answers)
            $responses = DB::table('assessment_responses')
                ->where('assessment_id', $id)
                ->get();

            // Get unique users who responded
            $userIds = $responses->pluck('user_id')->unique();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            // Build members array from responses
            $members = [];
            foreach ($responses as $response) {
                $user = $users->get($response->user_id);
                $userId = $response->user_id;

                if (!isset($members[$userId])) {
                    $memberName = 'Unknown';
                    if ($user) {
                        $fullName = trim("{$user->first_name} {$user->last_name}");
                        $memberName = !empty($fullName) ? $fullName : $user->email;
                    }

                    $members[$userId] = [
                        'member_id' => $userId,
                        'user_id' => $userId,
                        'name' => $memberName,
                        'responses' => [],
                    ];
                }

                $members[$userId]['responses'][] = [
                    'attempt_id' => $response->attempt_id,
                    'selected_options' => $response->selected_options,
                    'created_at' => $response->created_at,
                ];
            }

            $summaryCounts = [
                'total_responses' => $responses->count(),
                'unique_users' => count($members),
            ];

            return response()->json([
                'assessment' => [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                ],
                'members' => array_values($members),
                'summary' => $summaryCounts,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Assessment not found.'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to generate assessment summary.', ['assessment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to generate assessment summary.'], 500);
        }
    }


    // Resolve the organization ID from the request.
    // @param  StoreAssessmentRequest  $request
    // @param  array  $validated
    // @return int|null

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
