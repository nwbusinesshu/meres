<?php

namespace App\Http\Controllers;

use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;
use App\Models\UserCompetency;
use App\Models\UserRelation;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AdminEmployeeController extends Controller
{
    public function __construct()
    {
        if (AssessmentService::isAssessmentRunning()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $orgId = session('org_id');

        $users = User::whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->whereNull('removed_at')
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->get()
            ->map(function ($user) {
                $user['bonusMalus'] = optional($user->bonusMalus()->first())->level;
                return $user;
            });

        return view('admin.employees', ['users' => $users]);
    }

    public function getEmployee(Request $request)
    {
        return User::where('id', $request->id)
            ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->whereNull('removed_at')
            ->whereHas('organizations', function ($q) {
                $q->where('organization_id', session('org_id'));
            })
            ->firstOrFail();
    }

    public function saveEmployee(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'type' => ['required', Rule::in([UserType::NORMAL, UserType::CELEBRITY])],
        ]);

        $orgId = session('org_id');

        if ($request->id) {
            $user = User::where('id', $request->id)
                ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
                ->whereHas('organizations', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->firstOrFail();
        } else {
            $user = new User();
        }

        $user->fill($validated);
        $user->save();

        // Frissítsük a kapcsolatot a pivot táblában, ha új a felhasználó
        if (!$request->id) {
            $user->organizations()->attach($orgId);
        }

        return $user;
    }

    public function deleteEmployee(Request $request)
    {
        $orgId = session('org_id');

        $user = User::where('id', $request->id)
            ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->firstOrFail();

        $user->removed_at = now();
        $user->save();

        return AjaxService::success();
    }

    public function getBonusMalus(Request $request)
    {
        return UserBonusMalus::where('user_id', $request->id)->latest()->take(4)->get();
    }

    public function getCompetencies(Request $request)
    {
        return UserCompetency::where('user_id', $request->id)->get();
    }

    public function getRelations(Request $request)
    {
        return UserRelation::where('assessor_id', $request->id)->get();
    }

    public function saveCompetencies(Request $request)
    {
        UserCompetency::where('user_id', $request->id)->delete();

        foreach ($request->competencies as $comp) {
            UserCompetency::create([
                "user_id" => $request->id,
                "competency_id" => $comp
            ]);
        }

        return AjaxService::success();
    }

    public function saveRelations(Request $request)
    {
        UserRelation::where('assessor_id', $request->id)->delete();

        foreach ($request->relations as $r) {
            UserRelation::create([
                "assessor_id" => $request->id,
                "assessee_id" => $r['assessee_id'],
                "type" => $r['type'] === 'above'
                    ? UserRelationType::ABOVE
                    : ($r['type'] === 'equal' ? UserRelationType::EQUAL : UserRelationType::UNDER),
            ]);
        }

        return AjaxService::success();
    }
}
