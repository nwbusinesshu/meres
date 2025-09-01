<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class AdminResultsController extends Controller
{
    public function index(Request $request, ?int $assessmentId = null)
    {
        $orgId = (int) session('org_id');

        // Aktuális lezárt mérés (adott ID vagy legutóbbi a szervezetben)
        $assessment = $assessmentId
            ? Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->find($assessmentId)
            : Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        if (!$assessment) {
            return view('admin.results', [
                'assessment'     => null,
                'users'          => collect(),
                'prevAssessment' => null,
                'nextAssessment' => null,
            ]);
        }

        // Előző/következő lezárt mérés
        $prevAssessment = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $assessment->closed_at)
            ->orderByDesc('closed_at')
            ->first();

        $nextAssessment = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>', $assessment->closed_at)
            ->orderBy('closed_at')
            ->first();

        // Résztvevő userek (admin/szuperadmin out), aztán szűrés arra, hogy VAN statjuk ebben a mérésben
        $users = User::whereHas('organizations', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($assessment) {
                $user['stats'] = UserService::calculateUserPoints($assessment, $user);

                $month = date('Y-m-01', strtotime($assessment->closed_at));
                $user['bonusMalus'] = optional($user->getBonusMalusInMonth($month))->level;

                $user['change'] = 'none';
                if (!is_null($user->stats)) {
                    if ((int)$user->has_auto_level_up === 1) {
                        if ($user->stats->total < $assessment->monthly_level_down) {
                            $user['change'] = 'down';
                        }
                    } else {
                        if ($user->stats->total < $assessment->normal_level_down) {
                            $user['change'] = 'down';
                        } elseif ($user->stats->total > $assessment->normal_level_up) {
                            $user['change'] = 'up';
                        }
                    }
                }

                return $user;
            })
            ->filter(function ($user) {
                return !is_null($user->stats);
            })
            ->values();

        return view('admin.results', [
            'assessment'     => $assessment,
            'users'          => $users,
            'prevAssessment' => $prevAssessment,
            'nextAssessment' => $nextAssessment,
        ]);
    }
}
