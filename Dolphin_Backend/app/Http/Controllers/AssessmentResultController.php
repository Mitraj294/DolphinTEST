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
    private AssessmentCalculationService $calculationService;

    public function __construct(AssessmentCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    public function calculate(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate(['attempt_id' => 'required|integer']);
        $attemptId = $data['attempt_id'];

        $result = $this->calculationService->calculateResults(Auth::id(), $attemptId);
        if (!$result) {
            return response()->json(['error' => 'Failed to calculate results'], 500);
        }

        return response()->json(['message' => 'Result calculated', 'result' => $result]);
    }

    public function getUserResults(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userId = Auth::id();
        $assessmentId = $request->query('assessment_id');
        $type = $request->query('type');

        $query = AssessmentResult::where('user_id', $userId)->with(['organizationAssessment', 'user']);
        if ($assessmentId) {
            $query->where('organization_assessment_id', $assessmentId);
        }
        if ($type) {
            $query->where('type', $type);
        }

        $results = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['results' => $results, 'count' => $results->count()]);
    }

    public function show($id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        try {
            $result = AssessmentResult::where('id', $id)->where('user_id', Auth::id())
                ->with(['organizationAssessment', 'user'])->firstOrFail();
            return response()->json(['result' => $result]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Result not found or permission denied'], 404);
        }
    }

    public function compareResults(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userId = Auth::id();
        $assessmentId = $request->query('assessment_id');

        $query = AssessmentResult::where('user_id', $userId);
        if ($assessmentId) {
            $query->where('organization_assessment_id', $assessmentId);
        }

        $results = $query->orderBy('created_at', 'asc')->get();
        return response()->json([
            'original' => $results->where('type', 'original')->first(),
            'adjustments' => $results->where('type', 'adjust')->values(),
            'total_attempts' => $results->count(),
        ]);
    }

    public function checkSystemStatus(): JsonResponse
    {
        $available = false;
        try {
            $available = $this->calculationService->isDolphinExecutableAvailable();
        } catch (\Throwable $e) {
            Log::warning('checkSystemStatus failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'available' => $available,
            'message' => $available ? 'Assessment calculation system is ready' : 'Assessment calculation system is not available',
        ]);
    }
}
