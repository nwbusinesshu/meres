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
            ->where('user_bonus_malus.organization_id', $orgId)
            ->whereNull('user.removed_at')
            ->select(
                'user.id as user_id',
                'user.name',
                'user.email',
                'organization_user.position',
                'organization_departments.department_name',
                'user_bonus_malus.level',
                'user_bonus_malus.month'
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
        $sortBy = $request->get('sort_by', 'user.name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['user.name', 'user.email', 'user_bonus_malus.level', 'user_bonus_malus.month'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get bonus/malus configuration (multipliers)
     */
    public function configuration(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        $config = DB::table('bonus_malus_config')
            ->where('organization_id', $orgId)
            ->select('level', 'multiplier')
            ->orderBy('level', 'asc')
            ->get();

        if ($config->isEmpty()) {
            return $this->errorResponse('No bonus/malus configuration found for this organization', 404);
        }

        return $this->successResponse([
            'organization_id' => $orgId,
            'levels' => $config
        ]);
    }

    /**
     * ✅ NEW: Get bonus/malus categories (all possible levels 1-15)
     * Returns the category codes and their multipliers
     */
    public function categories(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Get configured multipliers for this organization
        $config = DB::table('bonus_malus_config')
            ->where('organization_id', $orgId)
            ->select('level', 'multiplier')
            ->orderBy('level', 'asc')
            ->get()
            ->keyBy('level');

        // Define category codes (standard 1-15 levels)
        $categories = [];
        for ($level = 1; $level <= 15; $level++) {
            // Format level as category code (e.g., B01, B02, ..., B15)
            $code = 'B' . str_pad($level, 2, '0', STR_PAD_LEFT);
            
            $categories[] = [
                'level' => $level,
                'code' => $code,
                'multiplier' => $config->has($level) ? (float)$config[$level]->multiplier : 0.0,
                'configured' => $config->has($level)
            ];
        }

        return $this->successResponse([
            'organization_id' => $orgId,
            'categories' => $categories,
            'total_levels' => 15
        ]);
    }

    /**
     * Get bonuses for a specific assessment
     */
    public function show(Request $request, $id)
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

        // Get total statistics
        $stats = DB::table('assessment_bonuses')
            ->where('assessment_id', $id)
            ->select(
                DB::raw('COUNT(*) as total_employees'),
                DB::raw('SUM(bonus_amount) as total_bonus_amount'),
                DB::raw('AVG(bonus_amount) as average_bonus'),
                DB::raw('SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_count'),
                DB::raw('SUM(CASE WHEN is_paid = 1 THEN bonus_amount ELSE 0 END) as paid_amount')
            )
            ->first();

        // Get distribution by level
        $distribution = DB::table('assessment_bonuses')
            ->where('assessment_id', $id)
            ->select(
                'bonus_malus_level',
                DB::raw('COUNT(*) as employee_count'),
                DB::raw('SUM(bonus_amount) as total_amount'),
                DB::raw('AVG(bonus_amount) as average_amount')
            )
            ->groupBy('bonus_malus_level')
            ->orderBy('bonus_malus_level', 'asc')
            ->get();

        // Get department breakdown
        $byDepartment = DB::table('assessment_bonuses')
            ->join('organization_user', 'assessment_bonuses.user_id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_bonuses.assessment_id', $id)
            ->where('organization_user.organization_id', $orgId)
            ->select(
                'organization_departments.department_name',
                DB::raw('COUNT(*) as employee_count'),
                DB::raw('SUM(assessment_bonuses.bonus_amount) as total_amount'),
                DB::raw('AVG(assessment_bonuses.bonus_amount) as average_amount')
            )
            ->groupBy('organization_departments.id', 'organization_departments.department_name')
            ->get();

        return $this->successResponse([
            'assessment_id' => $id,
            'assessment_date' => $assessment->closed_at,
            'statistics' => $stats,
            'distribution_by_level' => $distribution,
            'by_department' => $byDepartment
        ]);
    }

    /**
     * Get bonus results for specific assessment
     */
    public function results(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify assessment belongs to organization and is closed
        $assessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->where('id', $id)
            ->whereNotNull('closed_at')
            ->exists();

        if (!$assessment) {
            return $this->errorResponse('Assessment not found or not closed', 404);
        }

        $query = DB::table('assessment_bonuses')
            ->join('user', 'assessment_bonuses.user_id', '=', 'user.id')
            ->join('organization_user', function($join) use ($orgId) {
                $join->on('user.id', '=', 'organization_user.user_id')
                     ->where('organization_user.organization_id', '=', $orgId);
            })
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('assessment_bonuses.assessment_id', $id)
            ->select(
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

        if ($request->has('is_paid')) {
            $query->where('assessment_bonuses.is_paid', $request->is_paid);
        }

        if ($request->has('level')) {
            $query->where('assessment_bonuses.bonus_malus_level', $request->level);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'user.name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['user.name', 'assessment_bonuses.bonus_amount', 'assessment_bonuses.bonus_malus_level'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get user's bonus/malus history
     * ✅ FIXED: Route changed from /users/{userId} to /user/{userId}
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

        // Get bonus/malus history (monthly levels)
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