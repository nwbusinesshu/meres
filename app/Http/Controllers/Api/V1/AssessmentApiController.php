<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentApiController extends BaseApiController
{
    /**
     * Get list of assessments
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        $query = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->select(
                'id',
                'name',
                'description',
                'status',
                'period_start',
                'period_end',
                'assessment_type',
                'threshold_method',
                'threshold_value',
                'created_at',
                'updated_at',
                'closed_at'
            );

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('assessment_type')) {
            $query->where('assessment_type', $request->assessment_type);
        }

        if ($request->has('year')) {
            $query->whereYear('period_start', $request->year);
        }

        if ($request->has('from_date')) {
            $query->where('period_start', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('period_end', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name', 'period_start', 'period_end', 'created_at', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get single assessment details
     */
    public function show(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found', 404);
        }

        // Get participation statistics
        $stats = $this->getAssessmentStats($id);

        // Get weight configuration
        $weights = json_decode($assessment->weight_config, true);

        // Get participants count
        $participants = DB::table('assessment_user')
            ->where('assessment_id', $id)
            ->count();

        // Get competencies used in this assessment
        $competencies = DB::table('assessment_competency')
            ->join('organization_competency', 'assessment_competency.competency_id', '=', 'organization_competency.id')
            ->where('assessment_competency.assessment_id', $id)
            ->select(
                'organization_competency.id',
                'organization_competency.name',
                'organization_competency.description',
                'assessment_competency.weight'
            )
            ->get();

        return $this->successResponse([
            'assessment' => $assessment,
            'statistics' => $stats,
            'weights' => $weights,
            'participants_count' => $participants,
            'competencies' => $competencies
        ]);
    }

    /**
     * Get assessment participants
     */
    public function participants(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization
        $assessmentExists = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->exists();

        if (!$assessmentExists) {
            return $this->errorResponse('Assessment not found', 404);
        }

        $query = DB::table('assessment_user')
            ->join('user', 'assessment_user.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_user.assessment_id', $id)
            ->select(
                'assessment_user.user_id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.name as department_name',
                'assessment_user.self_evaluation_complete',
                'assessment_user.peer_evaluation_complete',
                'assessment_user.manager_evaluation_complete',
                'assessment_user.final_score',
                'assessment_user.manager_score',
                'assessment_user.ceo_ranking',
                'assessment_user.status'
            );

        // Filters
        if ($request->has('department_id')) {
            $query->where('organization_user.department_id', $request->department_id);
        }

        if ($request->has('status')) {
            $query->where('assessment_user.status', $request->status);
        }

        if ($request->has('complete_only') && $request->boolean('complete_only')) {
            $query->where('assessment_user.self_evaluation_complete', 1)
                  ->where('assessment_user.peer_evaluation_complete', 1);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'user.name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['user.name', 'organization_departments.name', 'assessment_user.final_score', 'assessment_user.ceo_ranking'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get assessment results summary
     */
    public function results(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization and is closed
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->where('status', 'closed')
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found or not yet closed', 404);
        }

        // Get overall statistics
        $stats = DB::table('assessment_user')
            ->where('assessment_id', $id)
            ->selectRaw('
                AVG(final_score) as average_score,
                MIN(final_score) as min_score,
                MAX(final_score) as max_score,
                COUNT(*) as total_participants,
                SUM(CASE WHEN final_score >= ? THEN 1 ELSE 0 END) as above_threshold
            ', [$assessment->threshold_value])
            ->first();

        // Get department breakdown
        $departmentStats = DB::table('assessment_user')
            ->join('organization_user', 'assessment_user.user_id', '=', 'organization_user.user_id')
            ->join('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_user.assessment_id', $id)
            ->where('organization_user.organization_id', $orgId)
            ->groupBy('organization_departments.id', 'organization_departments.name')
            ->selectRaw('
                organization_departments.id,
                organization_departments.name,
                AVG(assessment_user.final_score) as avg_score,
                COUNT(*) as participant_count
            ')
            ->orderBy('avg_score', 'desc')
            ->get();

        // Get top performers
        $topPerformers = DB::table('assessment_user')
            ->join('user', 'assessment_user.user_id', '=', 'user.id')
            ->where('assessment_user.assessment_id', $id)
            ->orderBy('final_score', 'desc')
            ->limit(10)
            ->select(
                'user.id',
                'user.name',
                'assessment_user.final_score',
                'assessment_user.ceo_ranking'
            )
            ->get();

        // Get score distribution
        $distribution = DB::table('assessment_user')
            ->where('assessment_id', $id)
            ->selectRaw('
                CASE 
                    WHEN final_score < 20 THEN "0-20"
                    WHEN final_score < 40 THEN "20-40"
                    WHEN final_score < 60 THEN "40-60"
                    WHEN final_score < 80 THEN "60-80"
                    ELSE "80-100"
                END as score_range,
                COUNT(*) as count
            ')
            ->groupBy('score_range')
            ->get();

        return $this->successResponse([
            'assessment_id' => $id,
            'overall_statistics' => $stats,
            'department_breakdown' => $departmentStats,
            'top_performers' => $topPerformers,
            'score_distribution' => $distribution,
            'threshold' => $assessment->threshold_value
        ]);
    }

    /**
     * Get assessment statistics
     */
    private function getAssessmentStats($assessmentId)
    {
        $stats = DB::table('assessment_user')
            ->where('assessment_id', $assessmentId)
            ->selectRaw('
                COUNT(*) as total_participants,
                SUM(self_evaluation_complete) as self_evaluations_complete,
                SUM(peer_evaluation_complete) as peer_evaluations_complete,
                SUM(manager_evaluation_complete) as manager_evaluations_complete,
                AVG(CASE WHEN final_score > 0 THEN final_score END) as average_score
            ')
            ->first();

        return [
            'total_participants' => $stats->total_participants ?? 0,
            'self_completion_rate' => $stats->total_participants > 0 
                ? round(($stats->self_evaluations_complete / $stats->total_participants) * 100, 1)
                : 0,
            'peer_completion_rate' => $stats->total_participants > 0
                ? round(($stats->peer_evaluations_complete / $stats->total_participants) * 100, 1)
                : 0,
            'manager_completion_rate' => $stats->total_participants > 0
                ? round(($stats->manager_evaluations_complete / $stats->total_participants) * 100, 1)
                : 0,
            'average_score' => round($stats->average_score ?? 0, 2)
        ];
    }
}