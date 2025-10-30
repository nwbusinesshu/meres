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
                'started_at',
                'due_at',
                'closed_at',
                'threshold_method',
                'normal_level_up',
                'normal_level_down',
                'monthly_level_down'
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
            $query->where('due_at', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'started_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['started_at', 'due_at', 'closed_at'])) {
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

        // Get competencies used in this assessment
        $competencies = DB::table('competency_submit')
            ->join('competency', 'competency_submit.competency_id', '=', 'competency.id')
            ->where('competency_submit.assessment_id', $id)
            ->groupBy('competency.id', 'competency.name', 'competency.description')
            ->select(
                'competency.id',
                'competency.name',
                'competency.description',
                DB::raw('COUNT(DISTINCT competency_submit.target_id) as participants_count')
            )
            ->get();

        return $this->successResponse([
            'assessment' => $assessment,
            'statistics' => $stats,
            'competencies' => $competencies
        ]);
    }

    /**
     * Get assessment participants
     */
    public function participants(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment exists
        $assessmentExists = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->exists();

        if (!$assessmentExists) {
            return $this->errorResponse('Assessment not found', 404);
        }

        // Get unique participants (target_id from competency_submit)
        $participants = DB::table('competency_submit')
            ->join('user', 'competency_submit.target_id', '=', 'user.id')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('competency_submit.assessment_id', $id)
            ->where('organization_user.organization_id', $orgId)
            ->whereNull('user.removed_at')
            ->groupBy('user.id', 'user.name', 'user.email', 'organization_user.role', 'organization_user.position', 'organization_departments.department_name')
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'organization_user.role',
                'organization_user.position',
                'organization_departments.department_name',
                DB::raw('COUNT(DISTINCT competency_submit.competency_id) as competencies_evaluated'),
                DB::raw('AVG(competency_submit.value) as average_score')
            )
            ->get();

        return $this->successResponse([
            'assessment_id' => $id,
            'total_participants' => $participants->count(),
            'participants' => $participants
        ]);
    }

    /**
     * Get assessment results
     */
    public function results(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment exists and is closed
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found', 404);
        }

        if (!$assessment->closed_at) {
            return $this->errorResponse('Assessment is still open. Results are only available for closed assessments.', 400);
        }

        // Get results from competency_submit with aggregations
        $query = DB::table('competency_submit')
            ->join('user', 'competency_submit.target_id', '=', 'user.id')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('competency_submit.assessment_id', $id)
            ->where('organization_user.organization_id', $orgId)
            ->whereNull('user.removed_at')
            ->groupBy('user.id', 'user.name', 'user.email', 'organization_user.position', 'organization_departments.department_name')
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.department_name',
                DB::raw('AVG(CASE WHEN competency_submit.type = "self" THEN competency_submit.value END) as self_score'),
                DB::raw('AVG(CASE WHEN competency_submit.type = "colleague" THEN competency_submit.value END) as colleague_score'),
                DB::raw('AVG(CASE WHEN competency_submit.type = "subordinate" THEN competency_submit.value END) as subordinate_score'),
                DB::raw('AVG(CASE WHEN competency_submit.type = "manager" THEN competency_submit.value END) as manager_score'),
                DB::raw('AVG(competency_submit.value) as overall_average')
            );

        // Add CEO rankings from user_ceo_rank
        $results = $query->get()->map(function($user) use ($id) {
            // Get CEO rank if exists
            $ceoRank = DB::table('user_ceo_rank')
                ->where('assessment_id', $id)
                ->where('user_id', $user->id)
                ->value('value');
            
            $user->ceo_rank = $ceoRank;
            
            // Get bonus/malus level from assessment_bonuses if exists
            $bonus = DB::table('assessment_bonuses')
                ->where('assessment_id', $id)
                ->where('user_id', $user->id)
                ->first();
            
            if ($bonus) {
                $user->bonus_malus_level = $bonus->bonus_malus_level;
                $user->bonus_amount = $bonus->bonus_amount;
                $user->is_paid = $bonus->is_paid;
            }
            
            return $user;
        });

        // Filters
        if ($request->has('department_id')) {
            $results = $results->filter(function($user) use ($request) {
                return $user->department_id == $request->department_id;
            });
        }

        return $this->successResponse([
            'assessment_id' => $id,
            'closed_at' => $assessment->closed_at,
            'total_results' => $results->count(),
            'results' => $results->values()
        ]);
    }

    /**
     * Get assessment statistics
     */
    private function getAssessmentStats($assessmentId)
    {
        $stats = [];

        // Total participants
        $stats['total_participants'] = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->distinct('target_id')
            ->count('target_id');

        // Total evaluators (unique user_id who submitted)
        $stats['total_evaluators'] = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // Submissions by type
        $submissionsByType = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->groupBy('type')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'type');

        $stats['submissions_by_type'] = [
            'self' => $submissionsByType['self'] ?? 0,
            'colleague' => $submissionsByType['colleague'] ?? 0,
            'subordinate' => $submissionsByType['subordinate'] ?? 0,
            'manager' => $submissionsByType['manager'] ?? 0,
        ];

        // Total submissions
        $stats['total_submissions'] = DB::table('competency_submit')
            ->where('assessment_id', $assessmentId)
            ->count();

        // Completion rate (users who submitted their self-evaluation)
        $completedUsers = DB::table('user_competency_submit')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('submitted_at')
            ->distinct('user_id')
            ->count('user_id');

        $stats['completion_rate'] = $stats['total_participants'] > 0 
            ? round(($completedUsers / $stats['total_participants']) * 100, 2)
            : 0;

        return $stats;
    }
}