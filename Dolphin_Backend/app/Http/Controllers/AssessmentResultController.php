<?php

namespace App\Http\Controllers;

use App\Models\AssessmentResult;
use App\Services\AssessmentCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssessmentResultController extends Controller
{
    protected $calculationService;

    public function __construct(AssessmentCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    
    public function calculate(Request $request): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        $validator = Validator::make($request->all(), [
            'attempt_id' => 'required|integer',
            // This assessment_id refers to the question set (1=self/original, 2=concept/adjust), not organization_assessments
            'assessment_id' => 'nullable|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $attemptId = $validated['attempt_id'] ?? null;
        $responseData = [];
        $status = 200;

        try {
            $userId = Auth::id();

            
            if (!$this->calculationService->isDolphinExecutableAvailable()) {
                Log::warning('Dolphin executable not found, attempting to build');

                if (!$this->calculationService->buildDolphinExecutable()) {
                    
                    $responseData = [
                        'error' => 'Assessment calculation system is not available. Please contact support.'
                    ];
                    $status = 503;
                }
            }

            
            if ($status === 200) {
                if (!empty($validated['assessment_id'])) {
                    $result = $this->calculationService->calculateResults(
                        $userId,
                        $validated['attempt_id'],
                        (int)$validated['assessment_id']
                    );
                    $responseData = [
                        'message' => 'Result calculated successfully',
                        'result' => $result
                    ];
                } else {
                    $results = $this->calculationService->ensureDualResults(
                        $userId,
                        $validated['attempt_id']
                    );
                    $responseData = [
                        'message' => 'Results calculated successfully',
                        'results' => $results
                    ];
                }
                $status = 200;
            }
        } catch (\Exception $e) {
            Log::error('Failed to calculate assessment results', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'attempt_id' => $attemptId
            ]);

            $responseData = [
                'error' => 'Failed to calculate results. Please try again or contact support.',
                'message' => $e->getMessage()
            ];
            $status = 500;
        }

        return response()->json($responseData, $status);
    }

    
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
