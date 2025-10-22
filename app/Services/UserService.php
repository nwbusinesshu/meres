<?php

namespace App\Services;

use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Assessment;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use App\Models\UserBonusMalus;

class UserService
{
    const DEFAULT_BM = 5;

    /**
     * AKTÍV felhasználók az AKTUÁLIS szervezetből.
     * (Régi viselkedés – hagyjuk meg más helyekre.)
     */
    public static function getUsers() {
        $orgId = session('org_id');

        return User::whereNull('removed_at')
            ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->whereHas('organizations', function ($query) use ($orgId) {
                $query->where('organization_id', $orgId);
            })
            ->orderBy('name')
            ->get();
    }

    public static function getCurrentUser(){
        return User::findOrFail(session('uid'));
    }

    /**
     * ZÁRT assessment résztvevői SNAPSHOT alapján.
     * Akkor is visszaadja a résztvevőket, ha azóta eltávolították őket az orgból.
     */
    public static function getAssessmentParticipants(int $assessmentId): \Illuminate\Support\Collection
    {
        $snap = self::loadAssessmentSnapshot($assessmentId);
        if (!$snap) {
            return collect(); // nincs snapshot → üres lista, nem dobunk hibát
        }

        $snapUserMap = [];
        foreach ($snap['users'] ?? [] as $u) {
            $snapUserMap[(int)$u['id']] = $u;
        }

        $ids = array_keys($snap['_index']['user_ids'] ?? []);
        if (empty($ids)) return collect();

        // Megpróbálunk DB-ből is adatot, de ha már törölve van, fallback a snapshot.
        $dbUsers = \App\Models\User::withTrashed()
            ->whereIn('id', $ids)
            ->get(['id','name','email','type','removed_at'])
            ->keyBy('id');

        return collect($ids)->map(function ($id) use ($dbUsers, $snapUserMap) {
            $db   = $dbUsers->get($id);
            $srow = $snapUserMap[$id] ?? null;
            return (object)[
                'id'         => $id,
                'name'       => $db->name ?? ($srow['name'] ?? 'Ismeretlen felhasználó'),
                'email'      => $db->email ?? ($srow['email'] ?? null),
                'user_type'  => $db->type ?? ($srow['user_type'] ?? null),
                'removed'    => $db?->removed_at !== null,
                'is_ceo'     => (bool)($srow['is_ceo'] ?? false),
                'org_role'   => $srow['org_role'] ?? null,
                'department' => $srow['department_id'] ?? null,
            ];
        });
    }

    /**
     * EREDETI INTERFÉSZ: (Assessment, User) -> stdClass
     * 
     * NEW STRUCTURE (5 components, weighted average):
     * - Self (0-100, weight 0.5)
     * - Colleagues (0-100, weight 1.0)
     * - Direct Reports (0-100, weight 1.0) - NEW
     * - Managers (0-100, weight 1.0)
     * - CEO Rank (0-100, weight 1.0)
     */
    public static function calculateUserPoints(Assessment $assessment, User $user): \stdClass
    {
        $d = self::calculateUserPointsDetailedByIds($assessment->id, $user->id);

        return (object) [
            'total'            => $d['final_0_100'],
            'selfTotal'        => $d['self_points'] ?? 0,
            'colleagueTotal'   => $d['colleague_points'] ?? 0,
            'colleaguesTotal'  => $d['colleague_points'] ?? 0,
            'directReportsTotal' => $d['direct_reports_points'] ?? 0,  // NEW
            'managersTotal'    => $d['boss_points'] ?? 0,
            'bossTotal'        => $d['boss_points'] ?? 0,
            'ceoTotal'         => $d['ceo_points'] ?? 0,
            'sum'              => $d['weighted_sum'] ?? 0,
            'complete'         => $d['complete'] ?? false,
            
            // Legacy aliases for backward compatibility
            'peersTotal'       => $d['colleague_points'] ?? 0,
            'leadersTotal'     => $d['boss_points'] ?? 0,
            'leaderTotal'      => $d['boss_points'] ?? 0,
            'rankTotal'        => $d['ceo_points'] ?? 0,
        ];
    }

    /** Ha valahol csak a 0..100-as végső érték kell. */
    public static function calculateUserPointsValue($a, $b): int
    {
        if ($a instanceof Assessment && $b instanceof User) {
            return (int) self::calculateUserPoints($a, $b)->total;
        }
        if ($b instanceof Assessment && $a instanceof User) {
            return (int) self::calculateUserPoints($b, $a)->total;
        }
        if (is_numeric($a) && is_numeric($b)) {
            $assessment = Assessment::find((int)$a);
            $user = User::find((int)$b);
            if ($assessment && $user) {
                return (int) self::calculateUserPoints($assessment, $user)->total;
            }
        }
        return 0;
    }

    /**
     * ÚJ pontszámítási mag – SNAPSHOT alapú stabil szűrés.
     * 
     * NEW SCORING MODEL (5 components):
     * 1. Self (0-100, weight 0.5) - NO fallback
     * 2. Colleagues (0-100, weight 1.0) - types: 'colleague', 'peer' only
     * 3. Direct Reports (0-100, weight 1.0) - types: 'superior' only (NEW)
     * 4. Managers (0-100, weight 1.0) - types: 'subordinate' only
     * 5. CEO Rank (0-100, weight 1.0) - unchanged
     * 
     * Final score: weighted average of available components
     * 
     * @return array{
     *   final_0_100:int,
     *   weighted_sum:float,
     *   total_weight:float,
     *   self_points:int|null,
     *   colleague_points:int|null,
     *   direct_reports_points:int|null,
     *   boss_points:int|null,
     *   ceo_points:int|null,
     *   components_available:int,
     *   missing_components:array,
     *   complete:bool,
     *   is_ceo:bool
     * }
     */
    public static function calculateUserPointsDetailedByIds(int $assessmentId, int $userId): array
    {
        // 1) Snapshot betöltése – kötelező a stabil történelmi számításhoz
        $snap = self::loadAssessmentSnapshot($assessmentId);
        if (!$snap) {
            return self::emptyResult(false);
        }

        $orgId = (int)($snap['_index']['org_id'] ?? 0);
        if ($orgId <= 0) {
            return self::emptyResult(false);
        }

        // 2) Target jogosultság: az assessment indításakor az org része volt?
        if (empty($snap['_index']['user_ids'][$userId])) {
            return self::emptyResult(false);
        }

        // 3) Determine if user is CEO
        $isCeo = !empty($snap['_index']['ceo_ids'][$userId]);

        // 4) CEO rank - required for non-CEOs, optional for CEOs
        $snapCeoIds = array_keys($snap['_index']['ceo_ids'] ?? []);
        $ceoRank = null;
        $rankPoints = null;
        
        if (!empty($snapCeoIds)) {
            $ceoRankAvg = DB::table('user_ceo_rank as r')
                ->where('r.assessment_id', $assessmentId)
                ->where('r.user_id', $userId)
                ->whereIn('r.ceo_id', $snapCeoIds)
                ->avg('r.value');
            
            if ($ceoRankAvg !== null) {
                $ceoRank = (float) $ceoRankAvg; // 0..100
                $rankPoints = (int) round($ceoRank);
            }
        }

        // 5) SELF (0..100) – NO fallback, can be null
        $snapUserIds = array_keys($snap['_index']['user_ids'] ?? []);
        $selfAvg = DB::table('competency_submit as cs')
            ->where('cs.assessment_id', $assessmentId)
            ->where('cs.target_id', $userId)
            ->where('cs.user_id', $userId)
            ->whereIn('cs.user_id', $snapUserIds)
            ->whereIn('cs.target_id', $snapUserIds)
            ->avg('cs.value');

        $selfPoints = $selfAvg !== null ? (int) round((float)$selfAvg) : null;

        // 6) COLLEAGUES (0..100) – types: 'colleague', 'peer' only (removed 'superior')
        $colleaguePoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: [
                'colleague' => 1.0, 
                'peer' => 1.0
            ],
            fallbackFromCeoRank: $ceoRank ?? 0.0,
            maxPoints: 100,  // Changed from 150 to 100
            wMin: 0.5,
            wMax: 1.5,
            snapshot: $snap
        );

        // 7) DIRECT REPORTS (0..100) – NEW component for upward feedback
        $directReportsPoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: ['superior' => 1.0],
            fallbackFromCeoRank: $ceoRank ?? 0.0,
            maxPoints: 100,
            wMin: 0.5,
            wMax: 1.5,
            snapshot: $snap
        );

        // 8) MANAGERS (0..100) – types: 'subordinate' only (removed 'ceo')
        $bossPoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: ['subordinate' => 1.0],
            fallbackFromCeoRank: $ceoRank ?? 0.0,
            maxPoints: 100,  // Changed from 150 to 100
            wMin: 0.5,
            wMax: 1.5,
            snapshot: $snap
        );

        // 9) WEIGHTED AVERAGE calculation
        $weightedSum = 0;
        $totalWeight = 0;
        $components = [];
        
        // Self: weight 0.5
        if ($selfPoints !== null) {
            $weightedSum += $selfPoints * 0.5;
            $totalWeight += 0.5;
            $components['self'] = $selfPoints;
        }
        
        // Colleagues: weight 1.0
        if ($colleaguePoints !== null) {
            $weightedSum += $colleaguePoints * 1.0;
            $totalWeight += 1.0;
            $components['colleagues'] = $colleaguePoints;
        }
        
        // Direct Reports: weight 1.0
        if ($directReportsPoints !== null) {
            $weightedSum += $directReportsPoints * 1.0;
            $totalWeight += 1.0;
            $components['direct_reports'] = $directReportsPoints;
        }
        
        // Managers: weight 1.0
        if ($bossPoints !== null) {
            $weightedSum += $bossPoints * 1.0;
            $totalWeight += 1.0;
            $components['managers'] = $bossPoints;
        }
        
        // CEO Rank: weight 1.0
        if ($rankPoints !== null) {
            $weightedSum += $rankPoints * 1.0;
            $totalWeight += 1.0;
            $components['ceo_rank'] = $rankPoints;
        }
        
        // Calculate final score
        $final = $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : 0;
        
        // Clamp to 0-100
        if ($final < 0)   $final = 0;
        if ($final > 100) $final = 100;
        
        // 10) Determine completeness
        // Complete if:
        // - Has self-evaluation AND
        // - Has at least 2 components total AND
        // - CEOs must have direct_reports, non-CEOs must have ceo_rank
        $complete = (
            $selfPoints !== null &&
            count($components) >= 2 &&
            ($isCeo ? 
                ($directReportsPoints !== null) : 
                ($rankPoints !== null))
        );

        return [
            'final_0_100'          => $final,
            'weighted_sum'         => $weightedSum,
            'total_weight'         => $totalWeight,
            'self_points'          => $selfPoints,
            'colleague_points'     => $colleaguePoints,
            'direct_reports_points' => $directReportsPoints,  // NEW
            'boss_points'          => $bossPoints,
            'ceo_points'           => $rankPoints,
            'components_available' => count($components),
            'missing_components'   => array_diff(
                ['self', 'colleagues', 'direct_reports', 'managers', 'ceo_rank'],
                array_keys($components)
            ),
            'complete'             => $complete,
            'is_ceo'               => $isCeo,
        ];
    }

    /** Üres/inkomplett eredmény formailag kompatibilis tömbként. */
    private static function emptyResult(bool $complete = false): array
    {
        return [
            'final_0_100'          => 0,
            'weighted_sum'         => 0,
            'total_weight'         => 0,
            'self_points'          => null,
            'colleague_points'     => null,
            'direct_reports_points' => null,  // NEW
            'boss_points'          => null,
            'ceo_points'           => null,
            'components_available' => 0,
            'missing_components'   => ['self', 'colleagues', 'direct_reports', 'managers', 'ceo_rank'],
            'complete'             => $complete,
            'is_ceo'               => false,
        ];
    }

    /**
     * Kategória pontok (0..$maxPoints): kérdőívenkénti átlag + telemetry_ai trust súly.
     * Ha nincs érvényes adat → null (no fallback anymore for new model).
     * SNAPSHOT alapú szűréssel (nincs organization_user join).
     */
    private static function weightedCategoryPoints(
        int $assessmentId,
        int $orgId,
        int $targetId,
        array $typesOrWeights,       // pl. ['colleague'] VAGY ['subordinate'=>1.0]
        float $fallbackFromCeoRank,  // 0..100 (kept for backward compatibility but not used in new model)
        int $maxPoints = 100,
        float $wMin = 0.5,
        float $wMax = 1.5,
        ?array $snapshot = null
    ): ?int {
        // 0) Típuslista + szorzók előkészítése
        $types = [];
        $typeWeights = [];
        foreach ($typesOrWeights as $k => $v) {
            if (is_string($k)) { $types[] = $k; $typeWeights[$k] = (float)$v; }
            else               { $types[] = $v; $typeWeights[$v] = 1.0; }
        }

        // Ha nincs snapshot → null (no fallback)
        if (!$snapshot) {
            return null;
        }

        $snapUserIds = array_keys($snapshot['_index']['user_ids'] ?? []);
        if (empty($snapUserIds)) return null;

        // 1) A target kaphat-e egyáltalán ilyen típusú értékelést?
        $targetInSnap = !empty($snapshot['_index']['user_ids'][$targetId]);
        if (!$targetInSnap) return null;

        // 2) Kérdőívek gyűjtése
        $submissions = DB::table('user_competency_submit as ucs')
            ->where('ucs.assessment_id', $assessmentId)
            ->where('ucs.target_id', $targetId)
            ->whereIn('ucs.user_id', $snapUserIds)
            ->get(['user_id','target_id','submitted_at','telemetry_ai']);

        if ($submissions->isEmpty()) return null;

        // 3) Szűrjük a megfelelő relációkat + típusokat
        $validSubs = [];
        foreach ($submissions as $s) {
            $rId = (int)$s->user_id;
            $tId = (int)$s->target_id;

            // A submission-höz tartozó relation types a snapshot-ban:
            $relKey = $rId . ':' . $tId;
            $relTypes = $snapshot['_index']['rel_index'][$relKey] ?? [];

            // Van-e metszet a kívánt típusokkal?
            $matchedTypes = array_intersect($types, array_keys($relTypes));
            if (empty($matchedTypes)) continue;

            // típus-súlyok összege (ha több típus is illeszkedik)
            $wType = 0.0;
            foreach ($matchedTypes as $mt) {
                $wType += $typeWeights[$mt] ?? 1.0;
            }

            // telemetry_ai alapú trust_score (ha van)
            $telemetryAi = $s->telemetry_ai ? json_decode($s->telemetry_ai, true) : null;
            $trustScore = isset($telemetryAi['trust_score']) ? (int)$telemetryAi['trust_score'] : 10;

            // Trust súly: 0..20 → [wMin..wMax], pivot=10 → 1.0
            $wTrust = 1.0;
            if ($trustScore < 10) {
                $wTrust = $wMin + (1.0 - $wMin) * ($trustScore / 10.0);
            } elseif ($trustScore > 10) {
                $wTrust = 1.0 + ($wMax - 1.0) * (($trustScore - 10) / 10.0);
            }

            // Együttható = wType * wTrust
            $w = $wType * $wTrust;
            if ($w <= 0) continue;

            $validSubs[] = [
                'rater_id' => $rId,
                'weight'   => $w,
            ];
        }

        if (empty($validSubs)) return null;

        // 4) Minden rater-re átlagolás (a competency_submit-ból)
        $raterAvgs = [];
        foreach ($validSubs as $vs) {
            $rId = $vs['rater_id'];

            // Átlag számolása a competency_submit-ból
            $avg = DB::table('competency_submit as cs')
                ->where('cs.assessment_id', $assessmentId)
                ->where('cs.target_id', $targetId)
                ->where('cs.user_id', $rId)
                ->whereIn('cs.user_id', $snapUserIds)
                ->whereIn('cs.target_id', $snapUserIds)
                ->avg('cs.value');

            if ($avg !== null) {
                $raterAvgs[] = [
                    'avg'    => (float)$avg,
                    'weight' => $vs['weight'],
                ];
            }
        }

        if (empty($raterAvgs)) return null;

        // 5) Súlyozott átlag
        $sumWeighted = 0.0;
        $sumW = 0.0;
        foreach ($raterAvgs as $ra) {
            $sumWeighted += $ra['avg'] * $ra['weight'];
            $sumW += $ra['weight'];
        }

        if ($sumW <= 0) return null;

        $finalAvg = $sumWeighted / $sumW; // 0..100
        $scaled = $finalAvg * $maxPoints / 100.0;

        $result = (int) round($scaled);
        if ($result < 0) $result = 0;
        if ($result > $maxPoints) $result = $maxPoints;

        return $result;
    }

    /**
     * Snapshot betöltés és indexelés.
     */
    private static function loadAssessmentSnapshot(int $assessmentId): ?array
    {
        $row = DB::table('assessment')
            ->where('id', $assessmentId)
            ->first(['org_snapshot']);

        if (!$row || !$row->org_snapshot) return null;

        $snap = json_decode($row->org_snapshot, true);
        if (!is_array($snap)) return null;

        // Ha már van index, kész
        if (!empty($snap['_index'])) return $snap;

        // Különben most indexeljük
        $userIds = [];
        $ceoIds = [];
        foreach ($snap['users'] ?? [] as $u) {
            $uid = (int)($u['id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = true;
                if (!empty($u['is_ceo'])) $ceoIds[$uid] = true;
            }
        }

        // "rater:target" → {type => true}
        $relIndex = [];
        if (!empty($snap['relations'])) {
            foreach ($snap['relations'] as $r) {
                $ru = (int)($r['user_id']   ?? 0);
                $rt = (int)($r['target_id'] ?? 0);
                $tp = (string)($r['type']   ?? '');
                if ($ru > 0 && $rt > 0 && $tp !== '') {
                    $k = $ru . ':' . $rt;
                    $relIndex[$k] ??= [];
                    $relIndex[$k][$tp] = true;
                }
            }
        }

        $snap['_index'] = [
            'user_ids'  => $userIds,
            'ceo_ids'   => $ceoIds,
            'rel_index' => $relIndex,
            'org_id'    => (int)($snap['organization']['id'] ?? 0),
        ];

        return $snap;
    }

    /**
     * A snapshot relációk közti jogosultság ellenőrzése.
     */
    private static function relationAllowed(array $snapshot, int $raterId, int $targetId, string $type): bool
    {
        $rel = $snapshot['_index']['rel_index'] ?? [];
        $k = $raterId . ':' . $targetId;
        return !empty($rel[$k][$type]);
    }

    /**
     * Get user results from snapshot (cached).
     * Returns null if not found or assessment not closed.
     * 
     * @param int $assessmentId
     * @param int $userId
     * @return array|null
     */
    public static function getUserResultsFromSnapshot(int $assessmentId, int $userId): ?array
    {
        $assessment = Assessment::find($assessmentId);
        
        // Only use cached results for closed assessments
        if (!$assessment || !$assessment->closed_at || !$assessment->org_snapshot) {
            return null;
        }
        
        $snapshot = json_decode($assessment->org_snapshot, true);
        if (!isset($snapshot['user_results'][(string)$userId])) {
            return null;
        }
        
        return $snapshot['user_results'][(string)$userId];
    }

    /**
     * Convert snapshot result array to the stdClass format expected by controllers.
     * This ensures compatibility with existing code that uses calculateUserPoints.
     * 
     * @param array $result
     * @return \stdClass
     */
    public static function snapshotResultToStdClass(array $result): \stdClass
    {
        return (object) [
            // Main stats
            'total'            => $result['total'] ?? 0,
            'selfTotal'        => $result['self'] ?? 0,
            'colleagueTotal'   => $result['colleague'] ?? 0,
            'colleaguesTotal'  => $result['colleague'] ?? 0,
            'directReportsTotal' => $result['direct_reports'] ?? 0,  // NEW
            'managersTotal'    => $result['manager'] ?? 0,
            'bossTotal'        => $result['manager'] ?? 0,
            'ceoTotal'         => $result['ceo'] ?? 0,
            'sum'              => $result['sum'] ?? 0,
            'complete'         => $result['complete'] ?? true,
            
            // Aliases for compatibility (some views use these)
            'peersTotal'       => $result['colleague'] ?? 0,
            'leadersTotal'     => $result['manager'] ?? 0,
            'leaderTotal'      => $result['manager'] ?? 0,
            'rankTotal'        => $result['ceo'] ?? 0,
        ];
    }
}