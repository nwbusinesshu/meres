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

        $showBonusMalus = \App\Services\OrgConfigService::getBool($orgId, 'show_bonus_malus', true);

        // Aktuális user stat - GET FROM CACHE
        $cached = UserService::getUserResultsFromSnapshot($assessment->id, $user->id);
        if ($cached) {
            $user['stats'] = UserService::snapshotResultToStdClass($cached);
            $user['bonusMalus'] = $cached['bonus_malus_level'];
            $user['change'] = $cached['change'];
        } else {
            // No cached data
            $user['stats'] = null;
            $user['bonusMalus'] = null;
            $user['change'] = 'none';
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
            // Get cached results - FAST!
            $cached = UserService::getUserResultsFromSnapshot($a->id, $user->id);
            
            if ($cached) {
                // Use cached data
                $total        = (float)$cached['total'];
                $selfVal      = (float)$cached['self'];
                $employeesVal = (float)$cached['colleague'];
                $leadersVal   = (float)$cached['manager'];
            } else {
                // No cached data - skip this assessment
                continue;
            }

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
    'history','currentIdx','minVal','maxVal','showBonusMalus'
        ));

    }
}
