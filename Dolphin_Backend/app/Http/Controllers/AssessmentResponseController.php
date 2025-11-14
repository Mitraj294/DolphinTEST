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

        if (empty($attemptId)) {
            $maxAttempt = DB::table('assessment_responses')
                ->where('user_id', $userId)
                ->max('attempt_id') ?: 0;
            $attemptId = ((int) $maxAttempt) + 1;
        }

        DB::transaction(function () use ($responses, $userId, $attemptId) {
            foreach ($responses as $responseData) {
                $assessmentResponse = AssessmentResponse::create([
                    'user_id' => $userId,
                    'attempt_id' => $attemptId,
                    'assessment_id' => $responseData['assessment_id'],
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
