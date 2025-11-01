<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentApiController extends BaseApiController
{
    /**
     * Get list of assessments
     * ✅ SECURITY: Only returns safe columns
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        $query = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->select(
                'id',
                'organization_id',
                'started_at',
                'due_at',
                'closed_at',
                'threshold_method',
                'normal_level_up',
                'normal_level_down',
                'monthly_level_down'
                // Excluded: org_snapshot, suggested_decision (sensitive data)
            );

        // Filters
        if ($request->has('status')) {
            if ($request->status === 'open') {
                $query->whereNull('closed_at');
            } elseif ($request->status === 'closed') {
                $query->whereNotNull('closed_at');
            }
        }

        if ($request->has('year')) {
            $query->whereYear('started_at', $request->year);
        }

        if ($request->has('from_date')) {
            $query->where('started_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('started_at', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'started_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['started_at', 'due_at', 'closed_at', 'id'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get single assessment details
     * ✅ SECURITY FIX: Explicitly select columns to avoid exposing org_snapshot
     */
    public function show(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // SECURITY: Explicitly select columns to avoid exposing org_snapshot
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->select(
                'id',
                'organization_id',
                'started_at',
                'due_at',
                'closed_at',
                'threshold_method',
                'normal_level_up',
                'normal_level_down',
                'monthly_level_down'
                // Excluded: org_snapshot, suggested_decision (sensitive data)
            )
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found', 404);
        }

        // Get participant count - target_id is who was rated
        $participantCount = DB::table('competency_submit')
            ->where('assessment_id', $id)
            ->distinct('target_id')
            ->count('target_id');

        // Get submission stats
        $stats = $this->getAssessmentStats($id);

        return $this->successResponse([
            'assessment' => $assessment,
            'participants' => $participantCount,
            'statistics' => $stats
        ]);
    }

    /**
     * Get assessment participants
     * Only for closed assessments
     */
    public function participants(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization and is closed
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->whereNotNull('closed_at')
            ->select('id', 'closed_at')
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found or not closed', 404);
        }

        // Get all participants (users who were rated) - target_id
        $participants = DB::table('competency_submit')
            ->join('user', 'competency_submit.target_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->where('competency_submit.assessment_id', $id)
            ->groupBy(
                'user.id',
                'user.name',
                'user.email',
                'organization_user.position'
            )
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'organization_user.position',
                DB::raw('COUNT(DISTINCT competency_submit.user_id) as rater_count')
            )
            ->get();

        return $this->successResponse([
            'assessment_id' => $id,
            'participants' => $participants,
            'total' => $participants->count()
        ]);
    }

    /**
     * Get assessment results
     * Only available for closed assessments
     * ✅ FIXED: Uses assessment.org_snapshot JSON, NOT user_result_snapshot table
     */
    public function results(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization and is closed
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->whereNotNull('closed_at')
            ->select(
                'id',
                'closed_at',
                'normal_level_up',
                'normal_level_down',
                'threshold_method',
                'org_snapshot'  // ✅ NEED this to get user results
            )
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found or not closed', 404);
        }

        // ✅ FIXED: Parse org_snapshot JSON to get user results
        $snapshot = json_decode($assessment->org_snapshot, true);
        if (!$snapshot || !isset($snapshot['user_results'])) {
            return $this->errorResponse('No results available for this assessment', 404);
        }

        $userResults = $snapshot['user_results'];

        // Get user info for each result
        $userIds = array_keys($userResults);
        $users = DB::table('user')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->whereIn('user.id', $userIds)
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_user.department_id',
                'organization_departments.department_name'
            )
            ->get()
            ->keyBy('id');

        // Build results array
        $results = collect();
        foreach ($userResults as $userId => $resultData) {
            $user = $users->get($userId);
            if (!$user) continue; // Skip if user not found

            // Apply filters
            if ($request->has('department_id') && $user->department_id != $request->department_id) {
                continue;
            }

            if ($request->has('min_score') && ($resultData['total'] ?? 0) < $request->min_score) {
                continue;
            }

            $results->push([
                'user_id' => $userId,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position,
                'department_name' => $user->department_name,
                'total_score' => $resultData['total'] ?? 0,
                'components' => [
                    'self' => $resultData['self'] ?? null,
                    'colleague' => $resultData['colleague'] ?? null,
                    'direct_reports' => $resultData['direct_reports'] ?? null,
                    'manager' => $resultData['manager'] ?? null,
                    'ceo' => $resultData['ceo'] ?? null
                ],
                'bonus_malus_level' => $resultData['bonus_malus_level'] ?? null,
                'trend' => $resultData['change'] ?? null
            ]);
        }

        // Manual pagination for collections
        $perPage = $request->get('per_page', 50);
        $currentPage = $request->get('page', 1);
        $total = $results->count();
        
        $paginatedResults = $results->forPage($currentPage, $perPage)->values();
        
        return $this->successResponse([
            'assessment_id' => $id,
            'results' => $paginatedResults,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }

    /**
     * ✅ FIXED: Get results for a specific user across all assessments
     * Uses assessment.org_snapshot JSON
     */
    public function userResults(Request $request, $userId)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify user belongs to organization
        $userExists = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->exists();

        if (!$userExists) {
            return $this->errorResponse('User not found in this organization', 404);
        }

        // Get user basic info
        $user = DB::table('user')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->where('user.id', $userId)
            ->whereNull('user.removed_at')
            ->select(
                'user.id', 
                'user.name', 
                'user.email', 
                'organization_user.position'
            )
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // ✅ FIXED: Get all closed assessments and parse org_snapshot for this user
        $assessments = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->select(
                'id',
                'closed_at',
                'normal_level_up',
                'normal_level_down',
                'org_snapshot'
            )
            ->orderBy('closed_at', 'desc')
            ->get();

        $results = collect();
        foreach ($assessments as $assessment) {
            $snapshot = json_decode($assessment->org_snapshot, true);
            if (!$snapshot || !isset($snapshot['user_results'][(string)$userId])) {
                continue; // User didn't participate in this assessment
            }

            $resultData = $snapshot['user_results'][(string)$userId];
            
            $results->push([
                'assessment_id' => $assessment->id,
                'assessment_date' => $assessment->closed_at,
                'total_score' => $resultData['total'] ?? 0,
                'components' => [
                    'self' => $resultData['self'] ?? null,
                    'colleague' => $resultData['colleague'] ?? null,
                    'direct_reports' => $resultData['direct_reports'] ?? null,
                    'manager' => $resultData['manager'] ?? null,
                    'ceo' => $resultData['ceo'] ?? null
                ],
                'bonus_malus_level' => $resultData['bonus_malus_level'] ?? null,
                'trend' => $resultData['change'] ?? null,
                'thresholds' => [
                    'upper' => $assessment->normal_level_up,
                    'lower' => $assessment->normal_level_down
                ]
            ]);
        }

        return $this->successResponse([
            'user' => $user,
            'results' => $results,
            'total_assessments' => $results->count()
        ]);
    }

    /**
     * Get assessment statistics
     */
    private function getAssessmentStats($assessmentId)
    {
        // Count total participants (unique target_ids)
        $totalParticipants = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->distinct('target_id')
            ->count('target_id');

        // Count self evaluations (type = 'self')
        $selfEvaluations = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->where('type', 'self')
            ->distinct('target_id')
            ->count('target_id');

        // Count completed evaluations
        $completedEvaluations = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('value')
            ->count();

        // Average score (only non-null values)
        $avgScore = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('value')
            ->avg('value');

        return [
            'total_participants' => $totalParticipants,
            'self_evaluations_complete' => $selfEvaluations,
            'total_evaluations' => $completedEvaluations,
            'average_score' => round($avgScore ?? 0, 2),
            'completion_rate' => $totalParticipants > 0
                ? round(($selfEvaluations / $totalParticipants) * 100, 1)
                : 0
        ];
    }
}