<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Middleware\Auth as AuthMiddleware;

class ResultsController extends Controller
{
    /**
     * Felhasználói eredmények – időszakváltó + history + trendek
     */
    public function index(Request $request, ?int $assessmentId = null)
    {
        $orgId = (int) session('org_id');
        // --- ⬇️ ÚJ: admin impersonation (peek) ---
        $effectiveUid = (int) session('uid');
        if (AuthMiddleware::isAuthorized(UserType::ADMIN)) {
            $as = (int) $request->query('as', 0);
            if ($as > 0) {
                // csak az aktuális org-on belüli userre engedjük
                $candidate = User::whereHas('organizations', function($q) use ($orgId) {
                        $q->where('organization_id', $orgId);
                    })
                    ->find($as);
                if ($candidate) {
                    $effectiveUid = $candidate->id;
                }
            }
        }
        // -----------------------------------------

        $user = User::find($effectiveUid);

        // Kiválasztott lezárt mérés
        $assessment = $assessmentId
            ? Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->find($assessmentId)
            : Assessment::where('organization_id', $orgId)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        if (!$assessment || !$user) {
            return view('results', [
                'assessment'     => null,
                'user'           => null,
                'prevAssessment' => null,
                'nextAssessment' => null,
                'history'        => collect(),
                'currentIdx'     => 0,
                'minVal'         => 0,
                'maxVal'         => 1,
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

        // Aktuális user stat
        $user['stats'] = UserService::calculateUserPoints($assessment, $user);

        // BonusMalus
        if (!in_array($user->type, [UserType::ADMIN, UserType::SUPERADMIN], true)) {
            $month = date('Y-m-01', strtotime($assessment->closed_at));
            $user['bonusMalus'] = optional($user->getBonusMalusInMonth($month))->level;
        } else {
            $user['bonusMalus'] = null;
        }

        // Szintváltozás
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

        /*
         |----------------------------------------------------------------------
         | History + trend számítás
         |----------------------------------------------------------------------
         */
        $allClosed = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->orderBy('closed_at')
            ->get();

        $history = collect();
        foreach ($allClosed as $a) {
            // HELYES sorrend: (Assessment, User)
            $s = UserService::calculateUserPoints($a, $user);
            if (!$s) continue;

            // minden komponens 0..100, nincs *2, /2
            $total        = isset($s->total)           && is_numeric($s->total)           ? (float)$s->total           : null;
            $selfVal      = isset($s->selfTotal)       && is_numeric($s->selfTotal)       ? (float)$s->selfTotal       : null;
            $employeesVal = isset($s->colleagueTotal)  && is_numeric($s->colleagueTotal)  ? (float)$s->colleagueTotal  : null;
            $leadersVal   = isset($s->managersTotal)   && is_numeric($s->managersTotal)   ? (float)$s->managersTotal   : null;

            if ($total !== null) {
                $history->push([
                    'id'        => $a->id,
                    'label'     => Carbon::parse($a->closed_at)->translatedFormat('Y. MMM'),
                    'closed_at' => $a->closed_at,
                    'total'     => round($total, 1),
                    'self'      => $selfVal      !== null ? round($selfVal, 1)      : null,
                    'employees' => $employeesVal !== null ? round($employeesVal, 1) : null,
                    'leaders'   => $leadersVal   !== null ? round($leadersVal, 1)   : null,
                ]);
            }
        }
        $history = $history->values();


        if ($history->isEmpty()) {
            $currentIdx = 0;
            $minVal = 0;
            $maxVal = 1;
            $user['trend'] = ['total'=>'flat','self'=>'flat','employees'=>'flat','leaders'=>'flat'];
            return view('results', compact('assessment','user','prevAssessment','nextAssessment','history','currentIdx','minVal','maxVal'));
        }

        $currentIdx = $history->search(fn ($h) => $h['id'] === $assessment->id);
        if ($currentIdx === false) $currentIdx = $history->count() - 1;
        $prevIdx = $currentIdx - 1;

        $minVal = $history->pluck('total')->min();
        $maxVal = $history->pluck('total')->max();
        if ($minVal === $maxVal) { $minVal = max(0, $minVal - 1); $maxVal = $maxVal + 1; }

        $trend = function (?float $curr, ?float $prev) {
            if ($curr === null || $prev === null) return 'flat';
            $eps = 0.05;
            if ($curr > $prev + $eps) return 'up';
            if ($curr < $prev - $eps) return 'down';
            return 'flat';
        };

        $currRow = $history->get($currentIdx);
        $prevRow = $prevIdx >= 0 ? $history->get($prevIdx) : null;

        $user['trend'] = [
            'total'     => $trend($currRow['total']     ?? null, $prevRow['total']     ?? null),
            'self'      => $trend($currRow['self']      ?? null, $prevRow['self']      ?? null),
            'employees' => $trend($currRow['employees'] ?? null, $prevRow['employees'] ?? null),
            'leaders'   => $trend($currRow['leaders']   ?? null, $prevRow['leaders']   ?? null),
        ];

        return view('results', compact(
            'assessment','user','prevAssessment','nextAssessment',
            'history','currentIdx','minVal','maxVal'
        ));
    }
}
