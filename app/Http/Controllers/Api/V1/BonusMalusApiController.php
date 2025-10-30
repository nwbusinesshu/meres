<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusMalusApiController extends BaseApiController
{
    /**
     * Get bonus/malus current status for all users
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Get latest month for each user
        $query = DB::table('user_bonus_malus')
            ->join('user', 'user_bonus_malus.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->leftJoin('user_wages', function($join) use ($orgId) {
                $join->on('user.id', '=', 'user_wages.user_id')
                     ->where('user_wages.organization_id', '=', $orgId);
            })
            ->where('user_bonus_malus.organization_id', $orgId)
            ->whereNull('user.removed_at')
            ->select(
                'user.id as user_id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.department_name',
                'user_bonus_malus.level',
                'user_bonus_malus.month',
                'user_wages.net_wage',
                'user_wages.currency'
            );

        // Get only the latest month for each user
        $query->whereRaw('user_bonus_malus.month = (
            SELECT MAX(month) 
            FROM user_bonus_malus ubm2 
            WHERE ubm2.user_id = user_bonus_malus.user_id 
            AND ubm2.organization_id = user_bonus_malus.organization_id
        )');

        // Filters
        if ($request->has('department_id')) {
            $query->where('organization_user.department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->where('user_bonus_malus.level', $request->level);
        }

        if ($request->has('month')) {
            $query->where('user_bonus_malus.month', $request->month);
        }

        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('user.name', 'like', $search)
                  ->orWhere('user.email', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'level');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'level', 'month'])) {
            if ($sortBy === 'name') {
                $query->orderBy('user.name', $sortOrder);
            } else {
                $query->orderBy('user_bonus_malus.' . $sortBy, $sortOrder);
            }
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get bonus/malus configuration
     */
    public function configuration(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        $config = DB::table('bonus_malus_config')
            ->where('organization_id', $orgId)
            ->orderBy('level')
            ->select('level', 'multiplier')
            ->get();

        return $this->successResponse([
            'organization_id' => $orgId,
            'configuration' => $config
        ]);
    }

    /**
     * Get assessment bonuses for specific assessment
     */
    public function show(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->first();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found', 404);
        }

        // Get bonuses for this assessment
        $bonuses = DB::table('assessment_bonuses')
            ->join('user', 'assessment_bonuses.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_bonuses.assessment_id', $id)
            ->whereNull('user.removed_at')
            ->select(
                'assessment_bonuses.id',
                'user.id as user_id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.department_name',
                'assessment_bonuses.bonus_malus_level',
                'assessment_bonuses.net_wage',
                'assessment_bonuses.currency',
                'assessment_bonuses.multiplier',
                'assessment_bonuses.bonus_amount',
                'assessment_bonuses.is_paid',
                'assessment_bonuses.paid_at',
                'assessment_bonuses.created_at'
            )
            ->orderBy('assessment_bonuses.bonus_malus_level')
            ->get();

        // Calculate statistics
        $stats = [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('bonus_amount'),
            'paid_count' => $bonuses->where('is_paid', 1)->count(),
            'unpaid_count' => $bonuses->where('is_paid', 0)->count(),
            'paid_amount' => $bonuses->where('is_paid', 1)->sum('bonus_amount'),
            'unpaid_amount' => $bonuses->where('is_paid', 0)->sum('bonus_amount'),
        ];

        // Group by department
        $departmentStats = DB::table('assessment_bonuses')
            ->join('user', 'assessment_bonuses.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_bonuses.assessment_id', $id)
            ->whereNull('user.removed_at')
            ->groupBy('organization_departments.id', 'organization_departments.department_name')
            ->selectRaw('
                organization_departments.id,
                organization_departments.department_name,
                COUNT(*) as employee_count,
                SUM(assessment_bonuses.bonus_amount) as total_bonus,
                AVG(assessment_bonuses.bonus_amount) as avg_bonus,
                AVG(assessment_bonuses.bonus_malus_level) as avg_level
            ')
            ->get();

        return $this->successResponse([
            'assessment' => $assessment,
            'statistics' => $stats,
            'department_breakdown' => $departmentStats,
            'bonuses' => $bonuses
        ]);
    }

    /**
     * Get individual results for an assessment (alias for show with filtering)
     */
    public function results(Request $request, $id)
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

        $query = DB::table('assessment_bonuses')
            ->join('user', 'assessment_bonuses.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_bonuses.assessment_id', $id)
            ->whereNull('user.removed_at')
            ->select(
                'assessment_bonuses.id',
                'user.id as user_id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.department_name',
                'assessment_bonuses.bonus_malus_level',
                'assessment_bonuses.net_wage',
                'assessment_bonuses.currency',
                'assessment_bonuses.multiplier',
                'assessment_bonuses.bonus_amount',
                'assessment_bonuses.is_paid',
                'assessment_bonuses.paid_at'
            );

        // Filters
        if ($request->has('department_id')) {
            $query->where('organization_user.department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->where('assessment_bonuses.bonus_malus_level', $request->level);
        }

        if ($request->has('is_paid')) {
            $query->where('assessment_bonuses.is_paid', $request->boolean('is_paid'));
        }

        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('user.name', 'like', $search)
                  ->orWhere('user.email', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'bonus_malus_level');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'bonus_malus_level', 'bonus_amount'])) {
            if ($sortBy === 'name') {
                $query->orderBy('user.name', $sortOrder);
            } else {
                $query->orderBy('assessment_bonuses.' . $sortBy, $sortOrder);
            }
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get user's bonus/malus history
     */
    public function userHistory(Request $request, $userId)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify user belongs to organization
        $userExists = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->exists();

        if (!$userExists) {
            return $this->errorResponse('User not found', 404);
        }

        // Get bonus/malus history
        $history = DB::table('user_bonus_malus')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->orderBy('month', 'desc')
            ->select('level', 'month')
            ->limit(24) // Last 24 months
            ->get();

        // Get assessment bonuses history
        $assessmentBonuses = DB::table('assessment_bonuses')
            ->join('assessment', 'assessment_bonuses.assessment_id', '=', 'assessment.id')
            ->where('assessment_bonuses.user_id', $userId)
            ->where('assessment.organization_id', $orgId)
            ->orderBy('assessment.closed_at', 'desc')
            ->select(
                'assessment_bonuses.assessment_id',
                'assessment.closed_at',
                'assessment_bonuses.bonus_malus_level',
                'assessment_bonuses.bonus_amount',
                'assessment_bonuses.multiplier',
                'assessment_bonuses.is_paid',
                'assessment_bonuses.paid_at'
            )
            ->limit(10) // Last 10 assessments
            ->get();

        return $this->successResponse([
            'user_id' => $userId,
            'monthly_history' => $history,
            'assessment_history' => $assessmentBonuses
        ]);
    }
}