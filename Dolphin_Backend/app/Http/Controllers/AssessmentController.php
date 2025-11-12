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
use Carbon\Carbon;

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

            
            try {
                $memberIds = DB::table('organization_assessment_member')
                    ->where('organization_assessment_id', $assessment->id)
                    ->pluck('user_id')
                    ->unique()
                    ->values();
            } catch (\Throwable $e) {
                Log::warning('[AssessmentController@summary] organization_assessment_member query failed', ['assessment_id' => $assessment->id, 'error' => $e->getMessage()]);
                $memberIds = collect();
            }

            
            
            
            
            
            
            if ($memberIds->isEmpty()) {
                $responses = collect();
            } else {
                try {
                    $responsesQuery = DB::table('assessment_responses as ar')
                        ->join('organization_assessment_member as oam', 'oam.user_id', '=', 'ar.user_id')
                        ->where('oam.organization_assessment_id', $assessment->id)
                        ->whereIn('ar.user_id', $memberIds)
                        ->select('ar.*', 'oam.created_at as assigned_at');
                } catch (\Throwable $e) {
                    Log::warning('[AssessmentController@summary] assessment_responses join query failed', ['assessment_id' => $assessment->id, 'error' => $e->getMessage()]);
                    $responses = collect();
                    
                }

                
                if (!isset($responses)) {
                    
                    $responsesQuery->whereColumn('ar.created_at', '>=', 'oam.created_at');

                    
                    if (!empty($assessment->date)) {
                        try {
                            
                            $scheduledAt = $assessment->time instanceof Carbon
                                ? Carbon::parse($assessment->date->toDateString() . ' ' . $assessment->time->format('H:i:s'))
                                : Carbon::parse($assessment->date->toDateString() . ' 00:00:00');
                            $responsesQuery->where('ar.created_at', '>=', $scheduledAt);
                        } catch (\Exception $e) {
                            
                        }
                    }

                    $responses = $responsesQuery->orderBy('ar.created_at', 'desc')->get();
                }
            }

            
            $userIds = $responses->pluck('user_id')->unique();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            
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
