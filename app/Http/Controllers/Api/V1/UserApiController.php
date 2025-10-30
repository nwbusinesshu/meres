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
            ->where('user.is_active', 1)
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'user.phone',
                'organization_user.role',
                'organization_user.position',
                'organization_departments.name as department_name',
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
                  ->orWhere('user.email', 'like', $search)
                  ->orWhere('organization_user.position', 'like', $search);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'user.name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['user.name', 'user.email', 'organization_departments.name', 'organization_user.position'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $this->paginatedResponse($query, $request->get('per_page', 50));
    }

    /**
     * Get single user details
     */
    public function show(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        $user = DB::table('user')
            ->join('organization_user', 'user.id', '=', 'organization_user.user_id')
            ->leftJoin('organization_departments', 'organization_user.department_id', '=', 'organization_departments.id')
            ->where('organization_user.organization_id', $orgId)
            ->where('user.id', $id)
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'user.phone',
                'user.profile_image',
                'organization_user.role',
                'organization_user.position',
                'organization_departments.name as department_name',
                'organization_departments.id as department_id',
                'user.created_at',
                'user.updated_at'
            )
            ->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Get manager if user is in a department
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
            // Get users in departments this person manages
            $subordinates = DB::table('organization_department_managers')
                ->join('organization_user', function($join) {
                    $join->on('organization_department_managers.organization_id', '=', 'organization_user.organization_id')
                         ->on('organization_department_managers.department_id', '=', 'organization_user.department_id');
                })
                ->join('user', 'organization_user.user_id', '=', 'user.id')
                ->where('organization_department_managers.manager_id', $id)
                ->where('organization_department_managers.organization_id', $orgId)
                ->where('user.id', '!=', $id) // Exclude the manager themselves
                ->select('user.id', 'user.name', 'user.email', 'organization_user.position')
                ->get();
            
            $user->subordinates = $subordinates;
        } else {
            $user->subordinates = [];
        }

        // Get recent assessment participation
        $recentAssessments = DB::table('assessment_user')
            ->join('assessment', 'assessment_user.assessment_id', '=', 'assessment.id')
            ->where('assessment_user.user_id', $id)
            ->where('assessment.organization_id', $orgId)
            ->orderBy('assessment.created_at', 'desc')
            ->limit(5)
            ->select(
                'assessment.id',
                'assessment.name',
                'assessment.status',
                'assessment.period_start',
                'assessment.period_end',
                'assessment_user.final_score',
                'assessment_user.manager_score',
                'assessment_user.ceo_ranking'
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

        $competencies = DB::table('user_competency')
            ->join('organization_competency', 'user_competency.competency_id', '=', 'organization_competency.id')
            ->leftJoin('competency', 'organization_competency.global_competency_id', '=', 'competency.id')
            ->where('user_competency.user_id', $id)
            ->where('organization_competency.organization_id', $orgId)
            ->where('organization_competency.is_active', 1)
            ->select(
                'organization_competency.id',
                'organization_competency.name',
                'organization_competency.description',
                'competency.category',
                'user_competency.level',
                'user_competency.target_level',
                'user_competency.updated_at'
            )
            ->get();

        return $this->successResponse($competencies);
    }

    /**
     * Get user's managers hierarchy
     */
    public function hierarchy(Request $request, $id)
    {
        $orgId = $this->getOrganizationId($request);
        
        // Verify user belongs to organization and get their department
        $userData = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $id)
            ->first();

        if (!$userData) {
            return $this->errorResponse('User not found', 404);
        }

        $hierarchy = [];
        
        if ($userData->department_id) {
            // Get department managers
            $managers = DB::table('organization_department_managers')
                ->join('user', 'organization_department_managers.manager_id', '=', 'user.id')
                ->join('organization_user', function($join) use ($orgId) {
                    $join->on('user.id', '=', 'organization_user.user_id')
                         ->where('organization_user.organization_id', '=', $orgId);
                })
                ->where('organization_department_managers.organization_id', $orgId)
                ->where('organization_department_managers.department_id', $userData->department_id)
                ->select(
                    'user.id',
                    'user.name',
                    'user.email',
                    'organization_user.position',
                    'organization_user.role'
                )
                ->get();
            
            foreach ($managers as $index => $manager) {
                $hierarchy[] = [
                    'level' => $index + 1,
                    'user' => $manager
                ];
            }
        }

        return $this->successResponse([
            'user_id' => $id,
            'department_id' => $userData->department_id,
            'hierarchy' => $hierarchy
        ]);
    }
}