<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentBonus;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Services\OrgConfigService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Middleware\Auth as AuthMiddleware;
use Illuminate\Support\Facades\DB;

class ResultsController extends Controller
{
    /**
     * Felhasználói eredmények – időszakváltó + history + trendek + kompetencia breakdown
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
                'assessment'          => null,
                'user'                => null,
                'prevAssessment'      => null,
                'nextAssessment'      => null,
                'history'             => collect(),
                'currentIdx'          => 0,
                'minVal'              => 0,
                'maxVal'              => 1,
                'competencyScores'    => [],
                'employeesSeeBonuses' => false,
                'bonusData'           => null,
            ]);
        }

        $assessmentId = $assessment->id;

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

        $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);

        // ✅ NEW: Check if employees_see_bonuses is enabled
        $employeesSeeBonuses = OrgConfigService::getBool($orgId, 'employees_see_bonuses', false);
        
        // ✅ NEW: Fetch bonus data if setting is enabled
        $bonusData = null;
        if ($employeesSeeBonuses) {
            $bonusData = AssessmentBonus::where('assessment_id', $assessment->id)
                ->where('user_id', $effectiveUid)
                ->first();
        }

        // Aktuális user stat - GET FROM CACHE
        $cached = UserService::getUserResultsFromSnapshot($assessment->id, $user->id);
        if ($cached) {
            $user['stats'] = UserService::snapshotResultToStdClass($cached);
            $user['bonusMalus'] = $cached['bonus_malus_level'];
            $user['change'] = $cached['change'];
            // ✅ NEW: Pass metadata for component status
            $user['missingComponents'] = $cached['missing_components'] ?? [];
        } else {
            // No cached data
            $user['stats'] = null;
            $user['bonusMalus'] = null;
            $user['change'] = 'none';
            $user['missingComponents'] = [];
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
                // Use cached data - NOW INCLUDING DIRECT_REPORTS
                $total            = (float)$cached['total'];
                $selfVal          = (float)$cached['self'];
                $employeesVal     = (float)$cached['colleague'];
                $directReportsVal = (float)($cached['direct_reports'] ?? 0);
                $leadersVal       = (float)$cached['manager'];
            } else {
                // No cached data - skip this assessment
                continue;
            }

            if ($total !== null) {
                $history->push([
                    'id'             => $a->id,
                    'label'          => Carbon::parse($a->closed_at)->translatedFormat('Y. MMM'),
                    'closed_at'      => $a->closed_at,
                    'total'          => round($total, 1),
                    'self'           => $selfVal           !== null ? round($selfVal, 1)           : null,
                    'employees'      => $employeesVal      !== null ? round($employeesVal, 1)      : null,
                    'direct_reports' => $directReportsVal  !== null ? round($directReportsVal, 1)  : null,
                    'leaders'        => $leadersVal        !== null ? round($leadersVal, 1)        : null,
                ]);
            }
        }
        $history = $history->values();


        if ($history->isEmpty()) {
            $currentIdx = 0;
            $minVal = 0;
            $maxVal = 1;
            $user['trend'] = [
                'total'          => 'flat',
                'self'           => 'flat',
                'employees'      => 'flat',
                'direct_reports' => 'flat',
                'leaders'        => 'flat'
            ];
            $competencyScores = [];
        } else {
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

            // ✅ UPDATED: Include direct_reports trend
            $user['trend'] = [
                'total'          => $trend($currRow['total']          ?? null, $prevRow['total']          ?? null),
                'self'           => $trend($currRow['self']           ?? null, $prevRow['self']           ?? null),
                'employees'      => $trend($currRow['employees']      ?? null, $prevRow['employees']      ?? null),
                'direct_reports' => $trend($currRow['direct_reports'] ?? null, $prevRow['direct_reports'] ?? null),
                'leaders'        => $trend($currRow['leaders']        ?? null, $prevRow['leaders']        ?? null),
            ];

            // ✅ NEW: Calculate competency-based scores
            $competencyScores = $this->calculateCompetencyScores($assessment->id, $user->id);
        }

        $raterTelemetry = [];


        if (AuthMiddleware::isAuthorized(UserType::ADMIN) || session('utype') === UserType::SUPERADMIN) {
            $raterTelemetry = \DB::table('user_competency_submit')
                ->where('assessment_id', $assessment->id)
                ->where('user_id', $effectiveUid)
                ->whereNotNull('telemetry_ai')
                ->select('target_id', 'telemetry_ai')
                ->get()
                ->map(function($row) {
                    $telemetryData = json_decode($row->telemetry_ai, true);
                    return [
                        'target_id' => $row->target_id,
                        'flags' => $telemetryData['flags'] ?? [],
                        'trust_index' => $telemetryData['trust_index'] ?? null,
                        'trust_score' => $telemetryData['trust_score'] ?? null,
                    ];
                })
                ->toArray();
        }

        return view('results', compact(
            'assessment',
            'assessmentId',
            'user',
            'prevAssessment',
            'nextAssessment',
            'history',
            'currentIdx',
            'minVal',
            'maxVal',
            'showBonusMalus',
            'raterTelemetry',
            'competencyScores',
            'employeesSeeBonuses',
            'bonusData'
        ));
    }

    /**
     * ✅ NEW: Calculate average scores per competency for a user
     * 
     * @param int $assessmentId
     * @param int $userId
     * @return array [['competency_id' => X, 'name' => 'Name', 'avg_score' => 85.5], ...]
     */
    private function calculateCompetencyScores(int $assessmentId, int $userId): array
    {
        // Get competency averages from competency_submit
        $scores = DB::table('competency_submit as cs')
            ->select('cs.competency_id', DB::raw('AVG(cs.value) as avg_score'))
            ->where('cs.assessment_id', $assessmentId)
            ->where('cs.target_id', $userId)
            ->groupBy('cs.competency_id')
            ->get();

        if ($scores->isEmpty()) {
            return [];
        }

        // Get competency IDs
        $competencyIds = $scores->pluck('competency_id')->toArray();

        // Get competency details (names, translations)
        $competencies = DB::table('competency')
            ->whereIn('id', $competencyIds)
            ->whereNull('removed_at')
            ->get()
            ->keyBy('id');

        // Get current locale
        $currentLocale = app()->getLocale() ?? 'hu';

        // Build result array with localized names
        $result = [];
        foreach ($scores as $score) {
            $competency = $competencies->get($score->competency_id);
            if (!$competency) continue;

            // Get localized name
            $localizedName = $this->getLocalizedCompetencyName(
                $competency->name,
                $competency->name_json,
                $competency->original_language ?? 'hu',
                $currentLocale
            );

            $result[] = [
                'competency_id' => (int)$score->competency_id,
                'name'          => $localizedName,
                'avg_score'     => round((float)$score->avg_score, 1),
            ];
        }

        // Sort by competency name for consistent display
        usort($result, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * ✅ NEW: Get localized competency name with fallback
     * 
     * @param string $originalName
     * @param string|null $nameJson
     * @param string $originalLanguage
     * @param string $currentLocale
     * @return string
     */
    private function getLocalizedCompetencyName(
        string $originalName,
        ?string $nameJson,
        string $originalLanguage,
        string $currentLocale
    ): string {
        // If no translations or we're in the original language, return original
        if (empty($nameJson) || $currentLocale === $originalLanguage) {
            return $originalName;
        }

        $translations = json_decode($nameJson, true);
        if (!$translations || !is_array($translations)) {
            return $originalName;
        }

        // Check if translation exists for current locale
        if (isset($translations[$currentLocale]) && !empty(trim($translations[$currentLocale]))) {
            return $translations[$currentLocale];
        }

        // Fallback to original
        return $originalName;
    }
}