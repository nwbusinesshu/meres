<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\Enums\OrgRole;  // ✅ ADDED
use App\Models\User;
use Illuminate\Support\Facades\DB;  // ✅ ADDED

class AssessmentService
{
    public static function getCurrentAssessment(){
        $orgId = session('org_id');
        return Assessment::where('organization_id', $orgId)
                     ->whereNull('closed_at')
                     ->first();
    }

    public static function isAssessmentRunning(){
        $orgId = session('org_id');
        return Assessment::where('organization_id', $orgId)
                     ->whereNull('closed_at')
                     ->exists();
    }

    /**
     * Calculate needed CEO ranks for current organization
     * 
     * ✅ FIXED: Now uses organization_user.role and filters by current org
     * 
     * Formula: (non_admin_users - 1) * ceo_count
     * 
     * @return int Number of CEO rankings needed
     */
    public static function calculateNeededCeoRanks(){
        $orgId = session('org_id');
        
        if (!$orgId) {
            return 0;
        }
        
        // ✅ FIXED: Count non-admin users in current organization
        $nonAdminCount = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', '!=', OrgRole::ADMIN)  // ✅ Use org_role, not user.type
            ->whereNull('u.removed_at')
            ->count();
        
        // ✅ FIXED: Count CEOs in current organization
        $ceoCount = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', OrgRole::CEO)  // ✅ Use org_role, not user.type
            ->whereNull('u.removed_at')
            ->count();
        
        // Formula: (non-admins - 1) * CEOs
        return ($nonAdminCount - 1) * $ceoCount;
    }
}