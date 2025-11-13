<?php

namespace App\Http\Controllers;

use App\Models\AssessmentResult;
use App\Services\AssessmentCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * AssessmentResultController
 *
 * Lightweight endpoints to:
 * - Calculate results for a given attempt (on-demand)
 * - List a user's results
 * - Show a specific result
 * - Compare original vs adjusted across attempts
 * - Check system availability (always true with native engine)
 */
class AssessmentResultController extends Controller
{
    protected $calculationService;

    public function __construct(AssessmentCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    
    /**
     * POST /api/assessment-results/calculate
     * Body: { attempt_id: int }
     * Returns newly created or existing result for the requested attempt.
     * The type ('original' or 'adjust') is determined by the attempt number.
     */
    public function calculate(Request $request): JsonResponse
    {
        // Auth guard
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'attempt_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $attemptId = $validated['attempt_id'];

        try {
            $userId = Auth::id();

            // The calculation service now handles all logic internally.
            // We just need to provide the user and attempt ID.
            $result = $this->calculationService->calculateResults($userId, $attemptId);

            if (!$result) {
                // The service logs the specific error, so we return a generic message.
                return response()->json([
                    'error' => 'Failed to calculate results. Please check the logs for details.'
                ], 500);
            }

            return response()->json([
                'message' => 'Result calculated successfully',
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to calculate assessment results', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'attempt_id' => $attemptId
            ]);

            return response()->json([
                'error' => 'An unexpected error occurred. Please try again or contact support.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * GET /api/assessment-results/user
     * Query: assessment_id? (organization_assessment_id filter), type? ('original'|'adjust')
     * Returns current user's results with relations for client display.
     */
    public function getUserResults(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $assessmentId = $request->query('assessment_id');
            $type = $request->query('type'); 

            $query = AssessmentResult::where('user_id', $userId)
                ->with(['organizationAssessment', 'user']);

            if ($assessmentId) {
                $query->where('organization_assessment_id', $assessmentId);
            }

            if ($type) {
                $query->where('type', $type);
            }

            $results = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'results' => $results,
                'count' => $results->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user assessment results', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve results'
            ], 500);
        }
    }

    
    /**
     * GET /api/assessment-results/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }

            $result = AssessmentResult::where('id', $id)
                ->where('user_id', $userId)
                ->with(['organizationAssessment', 'user'])
                ->firstOrFail();

            return response()->json([
                'result' => $result
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Result not found or you do not have permission to view it'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assessment result', [
                'result_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve result'
            ], 500);
        }
    }

    
    /**
     * GET /api/assessment-results/compare
     * Optional: assessment_id to scope
     * Returns first 'original' and all 'adjust' in chronological order.
     */
    public function compareResults(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            if (empty($userId)) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $assessmentId = $request->query('assessment_id');

            $query = AssessmentResult::where('user_id', $userId);

            if ($assessmentId) {
                $query->where('organization_assessment_id', $assessmentId);
            }

            $results = $query->orderBy('created_at', 'asc')->get();

            $original = $results->where('type', 'original')->first();
            $adjustments = $results->where('type', 'adjust')->values();

            return response()->json([
                'original' => $original,
                'adjustments' => $adjustments,
                'total_attempts' => $results->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to compare assessment results', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to compare results'
            ], 500);
        }
    }

    
    /**
     * GET /api/assessment-system/status
     */
    public function checkSystemStatus(): JsonResponse
    {
        try {
            $isAvailable = $this->calculationService->isDolphinExecutableAvailable();

            return response()->json([
                'available' => $isAvailable,
                'message' => $isAvailable
                    ? 'Assessment calculation system is ready'
                    : 'Assessment calculation system is not available'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
