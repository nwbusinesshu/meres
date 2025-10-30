<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusMalusApiController extends BaseApiController
{
    /**
     * Get bonus/malus calculations list
     */
    public function index(Request $request)
    {
        $query = DB::table('bonus_malus_calculations')
            ->where('organization_id', $this->organizationId)
            ->select(
                'id',
                'calculation_date',
                'period_start',
                'period_end',
                'assessment_ids',
                'total_employees',
                'total_bonus_amount',
                'total_malus_amount',
                'status',
                'created_at',
                'finalized_at'
            );

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('year')) {
            $query->whereYear('calculation_date', $request->year);
        }

        if ($request->has('from_date')) {
            $query->where('period_start', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('period_end', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'calculation_date');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['calculation_date', 'period_start', 'total_bonus_amount', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $results = $query->paginate($request->get('per_page', 50));

        // Decode JSON fields
        foreach ($results->items() as $item) {
            $item->assessment_ids = json_decode($item->assessment_ids, true);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get single bonus/malus calculation details
     */
    public function show(Request $request, $id)
    {
        $calculation = DB::table('bonus_malus_calculations')
            ->where('organization_id', $this->organizationId)
            ->where('id', $id)
            ->first();

        if (!$calculation) {
            return $this->errorResponse('Calculation not found', 404);
        }

        // Decode JSON fields
        $calculation->assessment_ids = json_decode($calculation->assessment_ids, true);
        $calculation->config_snapshot = json_decode($calculation->config_snapshot, true);

        // Get summary statistics
        $stats = DB::table('bonus_malus_results')
            ->where('calculation_id', $id)
            ->selectRaw('
                COUNT(*) as total_employees,
                SUM(CASE WHEN bonus_amount > 0 THEN 1 ELSE 0 END) as employees_with_bonus,
                SUM(CASE WHEN malus_amount > 0 THEN 1 ELSE 0 END) as employees_with_malus,
                SUM(bonus_amount) as total_bonus,
                SUM(malus_amount) as total_malus,
                AVG(bonus_amount) as avg_bonus,
                AVG(malus_amount) as avg_malus,
                MAX(bonus_amount) as max_bonus,
                MAX(malus_amount) as max_malus
            ')
            ->first();

        // Get department breakdown
        $departmentStats = DB::table('bonus_malus_results')
            ->join('user_organization', 'bonus_malus_results.user_id', '=', 'user_organization.user_id')
            ->join('departments', 'user_organization.department_id', '=', 'departments.id')
            ->where('bonus_malus_results.calculation_id', $id)
            ->where('user_organization.organization_id', $this->organizationId)
            ->groupBy('departments.id', 'departments.name')
            ->selectRaw('
                departments.id,
                departments.name,
                COUNT(*) as employee_count,
                SUM(bonus_malus_results.bonus_amount) as total_bonus,
                SUM(bonus_malus_results.malus_amount) as total_malus,
                AVG(bonus_malus_results.bonus_percentage) as avg_bonus_percentage
            ')
            ->get();

        return $this->successResponse([
            'calculation' => $calculation,
            'statistics' => $stats,
            'department_breakdown' => $departmentStats
        ]);
    }

    /**
     * Get individual results for a calculation
     */
    public function results(Request $request, $id)
    {
        // Verify calculation belongs to organization
        $calculationExists = DB::table('bonus_malus_calculations')
            ->where('organization_id', $this->organizationId)
            ->where('id', $id)
            ->exists();

        if (!$calculationExists) {
            return $this->errorResponse('Calculation not found', 404);
        }

        $query = DB::table('bonus_malus_results')
            ->join('user', 'bonus_malus_results.user_id', '=', 'user.id')
            ->join('user_organization', function($join) {
                $join->on('user.id', '=', 'user_organization.user_id')
                     ->where('user_organization.organization_id', '=', $this->organizationId);
            })
            ->leftJoin('departments', 'user_organization.department_id', '=', 'departments.id')
            ->where('bonus_malus_results.calculation_id', $id)
            ->select(
                'bonus_malus_results.id',
                'user.id as user_id',
                'user.name',
                'user.email',
                'user_organization.position',
                'departments.name as department_name',
                'bonus_malus_results.average_score',
                'bonus_malus_results.performance_category',
                'bonus_malus_results.bonus_percentage',
                'bonus_malus_results.malus_percentage',
                'bonus_malus_results.base_salary',
                'bonus_malus_results.bonus_amount',
                'bonus_malus_results.malus_amount',
                'bonus_malus_results.net_amount'
            );

        // Filters
        if ($request->has('department_id')) {
            $query->where('user_organization.department_id', $request->department_id);
        }

        if ($request->has('performance_category')) {
            $query->where('bonus_malus_results.performance_category', $request->performance_category);
        }

        if ($request->has('has_bonus') && $request->boolean('has_bonus')) {
            $query->where('bonus_malus_results.bonus_amount', '>', 0);
        }

        if ($request->has('has_malus') && $request->boolean('has_malus')) {
            $query->where('bonus_malus_results.malus_amount', '>', 0);
        }

        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('user.name', 'like', $search)
                  ->orWhere('user.email', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'bonus_malus_results.net_amount');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['user.name', 'departments.name', 'bonus_malus_results.average_score', 
                               'bonus_malus_results.bonus_amount', 'bonus_malus_results.net_amount'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get user's bonus/malus history
     */
    public function userHistory(Request $request, $userId)
    {
        // Verify user belongs to organization
        $userExists = DB::table('user_organization')
            ->where('organization_id', $this->organizationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$userExists) {
            return $this->errorResponse('User not found', 404);
        }

        $history = DB::table('bonus_malus_results')
            ->join('bonus_malus_calculations', 'bonus_malus_results.calculation_id', '=', 'bonus_malus_calculations.id')
            ->where('bonus_malus_calculations.organization_id', $this->organizationId)
            ->where('bonus_malus_results.user_id', $userId)
            ->orderBy('bonus_malus_calculations.calculation_date', 'desc')
            ->select(
                'bonus_malus_calculations.id as calculation_id',
                'bonus_malus_calculations.calculation_date',
                'bonus_malus_calculations.period_start',
                'bonus_malus_calculations.period_end',
                'bonus_malus_results.average_score',
                'bonus_malus_results.performance_category',
                'bonus_malus_results.bonus_percentage',
                'bonus_malus_results.malus_percentage',
                'bonus_malus_results.base_salary',
                'bonus_malus_results.bonus_amount',
                'bonus_malus_results.malus_amount',
                'bonus_malus_results.net_amount'
            )
            ->get();

        // Calculate totals
        $totals = [
            'total_bonus' => $history->sum('bonus_amount'),
            'total_malus' => $history->sum('malus_amount'),
            'total_net' => $history->sum('net_amount'),
            'calculation_count' => $history->count(),
            'average_score' => $history->avg('average_score')
        ];

        return $this->successResponse([
            'user_id' => $userId,
            'history' => $history,
            'totals' => $totals
        ]);
    }

    /**
     * Get current bonus/malus configuration
     */
    public function configuration(Request $request)
    {
        $config = DB::table('organization_config')
            ->where('organization_id', $this->organizationId)
            ->whereIn('name', [
                'bonus_malus_enabled',
                'bonus_malus_frequency',
                'bonus_percentage_excellent',
                'bonus_percentage_good',
                'bonus_percentage_satisfactory',
                'malus_percentage_needs_improvement',
                'malus_percentage_poor',
                'bonus_malus_calculation_method',
                'bonus_malus_base_salary_source'
            ])
            ->pluck('value', 'name');

        // Get thresholds
        $thresholds = DB::table('organization_config')
            ->where('organization_id', $this->organizationId)
            ->whereIn('name', [
                'threshold_excellent',
                'threshold_good',
                'threshold_satisfactory',
                'threshold_needs_improvement'
            ])
            ->pluck('value', 'name');

        return $this->successResponse([
            'configuration' => $config,
            'thresholds' => $thresholds,
            'categories' => [
                'excellent' => [
                    'min_score' => $thresholds['threshold_excellent'] ?? 90,
                    'bonus_percentage' => $config['bonus_percentage_excellent'] ?? 10
                ],
                'good' => [
                    'min_score' => $thresholds['threshold_good'] ?? 75,
                    'bonus_percentage' => $config['bonus_percentage_good'] ?? 5
                ],
                'satisfactory' => [
                    'min_score' => $thresholds['threshold_satisfactory'] ?? 60,
                    'bonus_percentage' => $config['bonus_percentage_satisfactory'] ?? 2
                ],
                'needs_improvement' => [
                    'min_score' => $thresholds['threshold_needs_improvement'] ?? 40,
                    'malus_percentage' => $config['malus_percentage_needs_improvement'] ?? 5
                ],
                'poor' => [
                    'max_score' => ($thresholds['threshold_needs_improvement'] ?? 40) - 0.01,
                    'malus_percentage' => $config['malus_percentage_poor'] ?? 10
                ]
            ]
        ]);
    }
}