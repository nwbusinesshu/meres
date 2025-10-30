<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationApiController extends BaseApiController
{
    /**
     * Get organization details
     */
    public function show(Request $request)
    {
        // Get organization ID from request attributes (set by middleware)
        $orgId = $this->getOrganizationId($request);
        
        if (!$orgId) {
            return $this->errorResponse('Organization ID not found in request', 500);
        }

        $organization = DB::table('organization')
            ->where('id', $orgId)
            ->whereNull('removed_at')
            ->select(
                'id',
                'name',
                'slug',
                'created_at'
            )
            ->first();

        if (!$organization) {
            return $this->errorResponse('Organization not found', 404);
        }

        // Get organization statistics
        $stats = $this->getOrganizationStats($orgId);

        // Get configuration
        $config = $this->getOrganizationConfig($orgId);

        return $this->successResponse([
            'organization' => $organization,
            'statistics' => $stats,
            'configuration' => $config
        ]);
    }

    /**
     * Get organization statistics
     */
    public function statistics(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        $stats = $this->getOrganizationStats($orgId);
        return $this->successResponse($stats);
    }

    /**
     * Get organization configuration
     */
    public function configuration(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        $config = $this->getOrganizationConfig($orgId);
        return $this->successResponse($config);
    }

    /**
     * Get organization statistics - USING CORRECT TABLE NAMES
     */
    private function getOrganizationStats($organizationId)
    {
        $stats = [];

        // Total employees - using organization_user table
        $stats['total_employees'] = DB::table('user')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organizationId)
            ->where('user.is_active', 1)
            ->count();

        // Active assessments
        $stats['active_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->count();

        // Completed assessments
        $stats['completed_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->where('status', 'closed')
            ->count();

        // Total assessments
        $stats['total_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->count();

        // Active competencies
        $stats['active_competencies'] = DB::table('organization_competency')
            ->where('organization_id', $organizationId)
            ->where('is_active', 1)
            ->count();

        // Departments - using organization_departments table
        $stats['total_departments'] = DB::table('organization_departments')
            ->where('organization_id', $organizationId)
            ->count();

        // Managers and CEOs
        $stats['total_managers'] = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['manager', 'ceo'])
            ->count();

        return $stats;
    }

    /**
     * Get organization configuration
     */
    private function getOrganizationConfig($organizationId)
    {
        $configs = DB::table('organization_config')
            ->where('organization_id', $organizationId)
            ->whereIn('name', [
                'api_enabled',
                'api_rate_limit_per_minute',
                'assessment_frequency',
                'bonus_malus_enabled',
                'ai_features_enabled',
                'trust_algorithm_enabled',
                'language',
                'threshold_method'
            ])
            ->pluck('value', 'name');

        return $configs;
    }
}