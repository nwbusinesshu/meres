<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserApiController extends BaseApiController
{
    /**
     * Get list of users in the organization
     */
    public function index(Request $request)
    {
        $orgId = $this->getOrganizationId($request);
        
        $query = DB::table('user')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('organization_user.organization_id', $orgId)
            ->whereNull('user.removed_at') // FIXED: Use removed_at instead of is_active
            ->select(
                'user.id',
                'user.name',
                'user.email',
                // REMOVED: user.phone - doesn't exist
                'organization_user.role',
                'organization_user.position',
                'organization_departments.department_name', // FIXED: department_name not name
                'organization_departments.id as department_id',
                'user.created_at',
                'user.updated_at'
            );

        // Filters
        if ($request->has('department_id')) {
            $query->where('organization_user.department_id', $request->department_id);
        }

        if ($request->has('role')) {
            $query->where('organization_user.role', $request->role);
        }

        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('user.name', 'like', $search)
                  ->orWhere('user.email', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'email', 'created_at', 'role'])) {
            $query->orderBy('user.' . $sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get single user details
     */
    public function show(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Get user with org details
        $user = DB::table('user')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('organization_user.organization_id', $orgId)
            ->where('user.id', $id)
            ->whereNull('user.removed_at')
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'user.locale',
                'user.created_at',
                'organization_user.role',
                'organization_user.position',
                'organization_departments.department_name',
                'organization_departments.id as department_id'
            )
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Get manager if user has a department
        if ($user->department_id) {
            $manager = DB::table('organization_department_managers')
                ->join('user', 'organization_department_managers.manager_id', '=', 'user.id')
                ->where('organization_department_managers.organization_id', $orgId)
                ->where('organization_department_managers.department_id', $user->department_id)
                ->select('user.id', 'user.name', 'user.email')
                ->first();
            
            $user->manager = $manager;
        } else {
            $user->manager = null;
        }

        // Get subordinates if user is a manager
        if ($user->role === 'manager' || $user->role === 'ceo') {
            $subordinates = DB::table('organization_department_managers')
                ->join('organization_user', function($join) {
                    $join->on('organization_department_managers.organization_id', '=', 'organization_user.organization_id')
                         ->on('organization_department_managers.department_id', '=', 'organization_user.department_id');
                })
                ->join('user', 'organization_user.user_id', '=', 'user.id')
                ->where('organization_department_managers.manager_id', $id)
                ->where('organization_department_managers.organization_id', $orgId)
                ->where('user.id', '!=', $id)
                ->whereNull('user.removed_at')
                ->select('user.id', 'user.name', 'user.email', 'organization_user.position')
                ->get();
            
            $user->subordinates = $subordinates;
        } else {
            $user->subordinates = [];
        }

        // Get recent assessment participation - FIXED: Use subquery to avoid cartesian product
        $recentAssessments = DB::table('assessment')
            ->whereIn('id', function($query) use ($id) {
                $query->select('assessment_id')
                    ->from('competency_submit')
                    ->where('target_id', $id)
                    ->distinct();
            })
            ->where('organization_id', $orgId)
            ->whereNotNull('closed_at') // Only closed assessments
            ->orderByDesc('closed_at')
            ->limit(5)
            ->select(
                'id',
                'started_at',
                'due_at',
                'closed_at',
                'threshold_method'
            )
            ->get();

        return $this->successResponse([
            'user' => $user,
            'recent_assessments' => $recentAssessments
        ]);
    }

    /**
     * Get user's competencies
     */
    public function competencies(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify user belongs to organization
        $userExists = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $id)
            ->exists();

        if (!$userExists) {
            return $this->errorResponse('User not found', 404);
        }

        // Get user's assigned competencies
        $competencies = DB::table('user_competency')
            ->join('competency', 'user_competency.competency_id', '=', 'competency.id')
            ->where('user_competency.user_id', $id)
            ->where('user_competency.organization_id', $orgId)
            ->whereNull('competency.removed_at')
            ->select(
                'competency.id',
                'competency.name',
                'competency.description',
                'competency.organization_id'
            )
            ->get();

        return $this->successResponse([
            'user_id' => $id,
            'competencies' => $competencies
        ]);
    }

    /**
     * Get user hierarchy (manager and colleagues)
     */
    public function hierarchy(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify user exists
        $user = DB::table('organization_user')
            ->join('user', 'organization_user.user_id', '=', 'user.id')
            ->where('organization_user.organization_id', $orgId)
            ->where('organization_user.user_id', $id)
            ->whereNull('user.removed_at')
            ->select('user.id', 'user.name', 'organization_user.department_id')
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $hierarchy = [
            'user_id' => $id,
            'manager' => null,
            'colleagues' => [],
            'subordinates' => []
        ];

        // Get manager from user_relation table
        $manager = DB::table('user_relation')
            ->join('user', 'user_relation.target_id', '=', 'user.id')
            ->where('user_relation.user_id', $id)
            ->where('user_relation.organization_id', $orgId)
            ->where('user_relation.type', 'manager')
            ->whereNull('user.removed_at')
            ->select('user.id', 'user.name', 'user.email')
            ->first();
        
        $hierarchy['manager'] = $manager;

        // Get colleagues from user_relation table
        $colleagues = DB::table('user_relation')
            ->join('user', 'user_relation.target_id', '=', 'user.id')
            ->where('user_relation.user_id', $id)
            ->where('user_relation.organization_id', $orgId)
            ->where('user_relation.type', 'colleague')
            ->whereNull('user.removed_at')
            ->select('user.id', 'user.name', 'user.email')
            ->get();
        
        $hierarchy['colleagues'] = $colleagues;

        // Get subordinates (people who have this user as manager)
        $subordinates = DB::table('user_relation')
            ->join('user', 'user_relation.user_id', '=', 'user.id')
            ->where('user_relation.target_id', $id)
            ->where('user_relation.organization_id', $orgId)
            ->where('user_relation.type', 'manager')
            ->whereNull('user.removed_at')
            ->select('user.id', 'user.name', 'user.email')
            ->get();
        
        $hierarchy['subordinates'] = $subordinates;

        return $this->successResponse($hierarchy);
    }
}