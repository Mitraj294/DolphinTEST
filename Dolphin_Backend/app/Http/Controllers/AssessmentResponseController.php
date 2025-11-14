<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\AssessmentTime;
use App\Services\AssessmentCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssessmentResponseController extends Controller
{
    public function getAssessments(): JsonResponse
    {
        try {
            $assessments = Assessment::all(['id', 'title', 'description', 'form_definition']);

            return response()->json($assessments);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessments.', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not retrieve assessments.'], 500);
        }
    }


    public function store(Request $request, AssessmentCalculationService $calculationService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'responses' => 'required|array',
            'responses.*.assessment_id' => 'required|exists:assessment,id',
            'responses.*.selected_options' => 'present|array',
            'responses.*.start_time' => 'nullable|date',
            'responses.*.end_time' => 'nullable|date',
            'attempt_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $responses = $request->input('responses');
        $attemptId = $request->input('attempt_id');
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        $userId = $user->id;

        // Prevent multiple submissions for the same assessment by the same user.
        // Use the model helper to centralize the logic. If any assessment in the
        // payload has already been submitted by this user, refuse the entire
        // request with HTTP 409 and list the IDs.
        try {
            // Determine duplicate submissions with awareness of organization_assessment_id.
            // If an incoming response includes an organization_assessment_id (or a
            // top-level one was provided), only consider it a duplicate when the
            // user has already submitted for the same assessment AND the same
            // organization_assessment_id. If no org id is provided for the
            // incoming response, preserve the previous behavior (any prior
            // submission for that assessment blocks the request).
            $topOrgAssessmentId = $request->input('organization_assessment_id');

            $already = [];
            foreach ($responses as $r) {
                $aid = isset($r['assessment_id']) ? (int)$r['assessment_id'] : null;
                if (is_null($aid)) {
                    continue;
                }

                $orgId = null;
                if (isset($r['organization_assessment_id']) && $r['organization_assessment_id'] !== null) {
                    $orgId = (string)$r['organization_assessment_id'];
                } elseif (!empty($topOrgAssessmentId)) {
                    $orgId = (string)$topOrgAssessmentId;
                }

                if ($orgId !== null) {
                    // Only treat as duplicate if a prior response exists for the
                    // same user, assessment and organization_assessment_id.
                    $exists = AssessmentResponse::where('user_id', $userId)
                        ->where('assessment_id', $aid)
                        ->where('organization_assessment_id', $orgId)
                        ->exists();
                    if ($exists) {
                        $already[] = $aid;
                    }
                } else {
                    // No org-assessment context provided — use previous global
                    // behavior: any prior submission for this assessment blocks.
                    if (AssessmentResponse::hasUserSubmitted($userId, $aid)) {
                        $already[] = $aid;
                    }
                }
            }

            if (!empty($already)) {
                return response()->json([
                    'message' => 'You have already submitted response(s) for one or more assessments.',
                    'already_submitted' => array_values(array_unique($already)),
                ], 409);
            }
        } catch (\Throwable $e) {
            // If anything goes wrong checking duplicates, log and continue
            // gracefully — we don't want to block submissions on a non-critical
            // read error.
            Log::warning('Failed to verify prior submissions before storing.', ['error' => $e->getMessage()]);
        }
        if (empty($attemptId)) {
            $maxAttempt = DB::table('assessment_responses')
                ->where('user_id', $userId)
                ->max('attempt_id') ?: 0;
            $attemptId = ((int) $maxAttempt) + 1;
        }

        DB::transaction(function () use ($responses, $userId, $attemptId, $request) {
            $topOrgAssessmentId = $request->input('organization_assessment_id');
            foreach ($responses as $responseData) {
                $orgAssessmentId = $responseData['organization_assessment_id'] ?? $topOrgAssessmentId ?? null;
                $assessmentResponse = AssessmentResponse::create([
                    'user_id' => $userId,
                    'attempt_id' => $attemptId,
                    'assessment_id' => $responseData['assessment_id'],
                    'organization_assessment_id' => $orgAssessmentId,
                    'selected_options' => json_encode($responseData['selected_options']),
                ]);

                if (!empty($responseData['start_time']) && !empty($responseData['end_time'])) {
                    try {
                        $startTime = Carbon::parse($responseData['start_time']);
                        $endTime = Carbon::parse($responseData['end_time']);
                        $timeSpent = $endTime->diffInSeconds($startTime);

                        AssessmentTime::create([
                            'assessment_response_id' => $assessmentResponse->id,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'time_spent' => $timeSpent,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to store assessment timing', ['error' => $e->getMessage()]);
                    }
                }
            }
        });

        // Auto-calculate results for this attempt (best-effort)
        try {
            Log::info('Auto-calculating assessment result for attempt', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
            ]);

            $results = $calculationService->ensureDualResults($userId, $attemptId);
            foreach ($results as $res) {
                Log::info('Assessment result calculated successfully', [
                    'result_id' => $res->id,
                    'user_id' => $userId,
                    'attempt_id' => $attemptId,
                    'type' => $res->type
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to auto-calculate assessment results', [
                'user_id' => $userId,
                'attempt_id' => $attemptId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Assessment responses saved successfully',
            'attempt_id' => $attemptId,
        ], 201);
    }


    public function getUserResponses(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $attemptId = $request->query('attempt_id');

            $query = AssessmentResponse::where('user_id', $userId)
                ->with('assessment:id,title,description');

            if ($attemptId) {
                $query->where('attempt_id', $attemptId);
            }

            $responses = $query->get();

            $formattedResponses = $responses->map(function ($response) {
                return [
                    'id' => $response->id,
                    'assessment_id' => $response->assessment_id,
                    'assessment_title' => $response->assessment->title ?? null,
                    'attempt_id' => $response->attempt_id,
                    'selected_options' => $response->selected_options,
                    'created_at' => $response->created_at,
                ];
            });

            return response()->json($formattedResponses);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user assessment responses.', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Could not retrieve responses.'], 500);
        }
    }


    /**
     * Return a compact list of assessment_ids the authenticated user has submitted
     * along with a submitted_at timestamp for each. Used by frontend to detect
     * already-submitted assignments and show the thank-you page.
     */
    public function getSubmissionStatus(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            // Return submission rows grouped by assessment and organization_assessment
            // so frontend can determine which organization assignment was submitted.
            $rows = AssessmentResponse::where('user_id', $userId)
                ->select('assessment_id', 'organization_assessment_id', DB::raw('MIN(created_at) as submitted_at'))
                ->groupBy('assessment_id', 'organization_assessment_id')
                ->get()
                ->map(function ($r) {
                    return [
                        'assessment_id' => (int)$r->assessment_id,
                        'organization_assessment_id' => $r->organization_assessment_id !== null ? (int)$r->organization_assessment_id : null,
                        'submitted_at' => $r->submitted_at,
                    ];
                });

            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve submission status.', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not retrieve submission status.'], 500);
        }
    }


    public function getUserAttempts(): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            $attempts = AssessmentResponse::where('user_id', $userId)
                ->select('attempt_id', DB::raw('MIN(created_at) as created_at'))
                ->groupBy('attempt_id')
                ->orderBy('attempt_id', 'desc')
                ->get();

            return response()->json($attempts);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user attempts.', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Could not retrieve attempts.'], 500);
        }
    }


    public function getAssessmentTiming(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $attemptId = $request->query('attempt_id');

            $validator = Validator::make(['attempt_id' => $attemptId], [
                'attempt_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }


            $responses = AssessmentResponse::where('user_id', $userId)
                ->where('attempt_id', $attemptId)
                ->with(['assessment:id,title', 'assessmentTime'])
                ->get();

            $timingData = $responses->map(function ($response) {
                $timing = $response->assessmentTime;
                return [
                    'assessment_id' => $response->assessment_id,
                    'assessment_title' => $response->assessment->title ?? null,
                    'start_time' => $timing->start_time ?? null,
                    'end_time' => $timing->end_time ?? null,
                    'time_spent' => $timing->time_spent ?? null,
                    'time_spent_formatted' => $timing && $timing->time_spent
                        ? gmdate('H:i:s', $timing->time_spent)
                        : null,
                ];
            });

            return response()->json([
                'attempt_id' => $attemptId,
                'timing_data' => $timingData,
                'total_time_spent' => $responses->sum(function ($response) {
                    return $response->assessmentTime->time_spent ?? 0;
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessment timing.', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Could not retrieve timing data.'], 500);
        }
    }
}
