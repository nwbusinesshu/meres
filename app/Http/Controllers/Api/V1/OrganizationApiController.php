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

        // Get organization basic info + profile details
        $organization = DB::table('organization')
            ->leftJoin('organization_profiles', 'organization.id', '=', 'organization_profiles.organization_id')
            ->where('organization.id', $orgId)
            ->whereNull('organization.removed_at')
            ->select(
                'organization.id',
                'organization.name',
                'organization.slug',
                'organization.created_at',
                'organization_profiles.tax_number',
                'organization_profiles.eu_vat_number',
                'organization_profiles.country_code',
                'organization_profiles.postal_code',
                'organization_profiles.region',
                'organization_profiles.city',
                'organization_profiles.street',
                'organization_profiles.house_number',
                'organization_profiles.phone',
                'organization_profiles.employee_limit',
                'organization_profiles.subscription_type'
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
     * Get organization statistics - CORRECTED
     */
    private function getOrganizationStats($organizationId)
    {
        $stats = [];

        // Total employees - FIXED: Check removed_at instead of is_active
        $stats['total_employees'] = DB::table('user')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organizationId)
            ->whereNull('user.removed_at')
            ->count();

        // Active assessments (not closed)
        $stats['active_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->whereNull('closed_at')
            ->count();

        // Completed assessments
        $stats['completed_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->whereNotNull('closed_at')
            ->count();

        // Total assessments
        $stats['total_assessments'] = DB::table('assessment')
            ->where('organization_id', $organizationId)
            ->count();

        // Active competencies - FIXED: competency table, check removed_at
        $stats['active_competencies'] = DB::table('competency')
            ->where(function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                  ->orWhereNull('organization_id'); // Include global competencies
            })
            ->whereNull('removed_at')
            ->count();

        // Departments - FIXED: Check removed_at
        $stats['total_departments'] = DB::table('organization_departments')
            ->where('organization_id', $organizationId)
            ->whereNull('removed_at')
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