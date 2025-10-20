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
     * Visszatér: total (0..100), selfTotal (0..100),
     *            colleague(s)Total (0..150), bossTotal (0..150), ceoTotal (0..100), sum (0..500)
     */
    public static function calculateUserPoints(Assessment $assessment, User $user): \stdClass
    {
        $d = self::calculateUserPointsDetailedByIds($assessment->id, $user->id);

        // Normalizálás 150 -> 100 a megjelenítéshez
        $colleague100 = (int) max(0, min(100, round($d['colleague_points'] * 100 / 150)));
        $boss100      = (int) max(0, min(100, round($d['boss_points']      * 100 / 150)));

        return (object) [
            'total'            => $d['final_0_100'],
            'selfTotal'        => $d['self_points'],
            'colleagueTotal'   => $colleague100,
            'colleaguesTotal'  => $d['colleague_points'], // 0..150
            'managersTotal'    => $boss100,
            'bossTotal'        => $d['boss_points'],      // 0..150
            'ceoTotal'         => $d['ceo_points'],       // 0..100
            'sum'              => $d['sum_0_500'],
            'peersTotal'       => $colleague100,
            'leadersTotal'     => $boss100,
            'leaderTotal'      => $boss100,
            'rankTotal'        => $d['ceo_points'],
            'complete'         => $d['complete'] ?? true,
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
     * - NINCS organization_user join → múltbeli újraszámítás stabil marad.
     * - telemetry_ai ha VAN → súlyozás, ha NINCS → súly=1.0,
     * - 4 komponens; ha CEO hiányzik → üres eredmény (complete=false),
     * - vissza: komponensek + végső 0..100.
     *
     * Ha NINCS snapshot → NEM dobunk hibát, hanem emptyResult(false).
     *
     * @return array{
     *   final_0_100:int, sum_0_500:int, self_points:int, colleague_points:int, boss_points:int, ceo_points:int, complete:bool
     * }
     */
    public static function calculateUserPointsDetailedByIds(int $assessmentId, int $userId): array
    {
        // 1) Snapshot betöltése – kötelező a stabil történelmi számításhoz
        $snap = self::loadAssessmentSnapshot($assessmentId);
        if (!$snap) {
            // Kifejezett kérés: nincs snapshot → ne 500, hanem nullák
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

        // 3) CEO rank (kötelező komponens) – szűrés SNAPSHOT CEO-kra
        $snapCeoIds = array_keys($snap['_index']['ceo_ids'] ?? []);
        if (empty($snapCeoIds)) {
            return self::emptyResult(false);
        }

        $ceoRank = DB::table('user_ceo_rank as r')
            ->where('r.assessment_id', $assessmentId)
            ->where('r.user_id', $userId)
            ->whereIn('r.ceo_id', $snapCeoIds)
            ->avg('r.value');

        if ($ceoRank === null) {
            // nincs CEO rank → nincs teljes pont
            return self::emptyResult(false);
        }
        $ceoRank = (float) $ceoRank; // 0..100

        // 4) SELF (0..100) – ha nincs, fallback = CEO rank
        $snapUserIds = array_keys($snap['_index']['user_ids'] ?? []);
        $selfAvg = DB::table('competency_submit as cs')
            ->where('cs.assessment_id', $assessmentId)
            ->where('cs.target_id', $userId)
            ->where('cs.user_id', $userId)
            ->whereIn('cs.user_id', $snapUserIds)
            ->whereIn('cs.target_id', $snapUserIds)
            ->avg('cs.value');

        $selfPoints = (int) round(($selfAvg !== null ? (float)$selfAvg : $ceoRank)); // 0..100

        // 5) KOLLÉGA (0..150) – trust-súlyozott átlag (telemetry_ai)
        $colleaguePoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: [
                'colleague' => 1.0, 
                'peer' => 1.0,
                'superior' => 1.0  // NEW: Superior evaluations count as peer feedback
            ],
            fallbackFromCeoRank: $ceoRank,
            maxPoints: 150,
            wMin: 0.5,
            wMax: 1.5,
            snapshot: $snap
        );

        // 6) FELETTES(ek) (0..150) – rugalmas típusok, trust-súlyozott átlag
        $bossPoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: ['subordinate' => 1.0, 'ceo' => 0.5], // CEO kérdőív fél súllyal
            fallbackFromCeoRank: $ceoRank,
            maxPoints: 150,
            wMin: 0.5,
            wMax: 1.5,
            snapshot: $snap
        );

        // 7) CEO komponens (0..100)
        $rankPoints = (int) round($ceoRank);

        // 8) Összeg (0..500) → /5 = végső (0..100)
        $sum500 = $selfPoints + $colleaguePoints + $bossPoints + $rankPoints;
        $final  = (int) round($sum500 / 5);
        if ($final < 0)   $final = 0;
        if ($final > 100) $final = 100;

        return [
            'final_0_100'      => $final,
            'sum_0_500'        => $sum500,
            'self_points'      => $selfPoints,
            'colleague_points' => $colleaguePoints,
            'boss_points'      => $bossPoints,
            'ceo_points'       => $rankPoints,
            'complete'         => true,
        ];
    }

    /** Üres/inkomplett eredmény formailag kompatibilis tömbként. */
    private static function emptyResult(bool $complete = false): array
    {
        return [
            'final_0_100'      => 0,
            'sum_0_500'        => 0,
            'self_points'      => 0,
            'colleague_points' => 0,
            'boss_points'      => 0,
            'ceo_points'       => 0,
            'complete'         => $complete,
        ];
    }

    /**
     * Kategória pontok (0..$maxPoints): kérdőívenkénti átlag + telemetry_ai trust súly.
     * Ha nincs érvényes adat → fallback = CEO rank arányos pont.
     * SNAPSHOT alapú szűréssel (nincs organization_user join).
     */
    private static function weightedCategoryPoints(
        int $assessmentId,
        int $orgId,
        int $targetId,
        array $typesOrWeights,       // pl. ['colleague'] VAGY ['subordinate'=>1.0,'ceo'=>0.5]
        float $fallbackFromCeoRank,  // 0..100
        int $maxPoints = 150,
        float $wMin = 0.5,
        float $wMax = 1.5,
        ?array $snapshot = null
    ): int {
        // 0) Típuslista + szorzók előkészítése
        $types = [];
        $typeWeights = [];
        foreach ($typesOrWeights as $k => $v) {
            if (is_string($k)) { $types[] = $k; $typeWeights[$k] = (float)$v; }
            else               { $types[] = $v; $typeWeights[$v] = 1.0; }
        }

        // Ha nincs snapshot → nincs stabil történelmi nézet → fallback
        if (!$snapshot) {
            return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
        }

        $snapUserIds = array_keys($snapshot['_index']['user_ids'] ?? []);
        if (empty($snapUserIds) || empty($snapshot['_index']['user_ids'][(int)$targetId])) {
            return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
        }

        $q = DB::table('competency_submit as cs')
            ->join('user_competency_submit as ucs', function ($join) {
                $join->on('ucs.assessment_id', '=', 'cs.assessment_id')
                     ->on('ucs.user_id',       '=', 'cs.user_id')
                     ->on('ucs.target_id',     '=', 'cs.target_id');
            })
            ->where('cs.assessment_id', $assessmentId)
            ->where('cs.target_id', $targetId)
            ->whereIn('cs.type', $types)
            // SNAPSHOT stabilitás: csak az assessment-kori org user készletből
            ->whereIn('cs.user_id', $snapUserIds)
            ->whereIn('cs.target_id', $snapUserIds)
            ->groupBy('cs.assessment_id', 'cs.user_id', 'cs.target_id', 'ucs.telemetry_ai', 'cs.type')
            ->selectRaw('AVG(cs.value) as questionnaire_avg')
            ->addSelect('ucs.telemetry_ai', 'cs.type', 'cs.user_id', 'cs.target_id');

        $rows = $q->get();
        if ($rows->isEmpty()) {
            return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
        }

        // 1) (opcionális, de ajánlott) reláció validáció a SNAPSHOT szerint (rater-target-type)
        $filtered = [];
        foreach ($rows as $row) {
            $raterId = (int)$row->user_id;
            $targId  = (int)$row->target_id;
            $type    = (string)$row->type;
            if (self::relationAllowed($snapshot, $raterId, $targId, $type)) {
                $filtered[] = $row;
            }
        }

        if (empty($filtered)) {
            return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
        }

        // 2) Súlyok (telemetria × type-szorzó)
        $items = [];
        $sumW  = 0.0;
        foreach ($filtered as $row) {
            $avg = (float) $row->questionnaire_avg; // 0..100
            $w   = self::trustWeightFromTelemetry($row->telemetry_ai, $wMin, $wMax);
            $tw  = $typeWeights[$row->type] ?? 1.0;
            $w  *= $tw;
            if (!is_finite($w) || $w <= 0) $w = 1.0;
            $items[] = ['avg' => $avg, 'w' => $w];
            $sumW   += $w;
        }

        // 3) Átlag-normalizálás (tw-vel együtt): mean(w) = 1.0
        $meanW = $sumW > 0 ? ($sumW / count($items)) : 1.0;
        if (!is_finite($meanW) || $meanW <= 0) $meanW = 1.0;

        $num = 0.0; $den = 0.0;
        foreach ($items as $it) {
            $w = $it['w'] / $meanW;
            $num += $it['avg'] * $w;
            $den += $w;
        }

        if ($den <= 0.0) {
            return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
        }

        $weightedAvg = $num / $den; // 0..100
        return (int) round($weightedAvg * $maxPoints / 100.0);
    }

    /**
     * telemetry_ai JSON → trust súly (0.5 .. 1.5).
     * - Ha NINCS telemetry_ai → 1.0 (neutrális).
     * - trust_score 0..20 és lineáris leképezés wMin..wMax (alap 0.5..1.5),
     *   ts=10 → 1.0.
     */
    private static function trustWeightFromTelemetry(?string $telemetryJson, float $wMin = 0.5, float $wMax = 1.5): float
    {
        if (!$telemetryJson) return 1.0;

        $data = json_decode($telemetryJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return 1.0;
        }

        if (!array_key_exists('trust_score', $data)) {
            return 1.0;
        }

        $ts = (float) $data['trust_score'];           // elvárt tartomány: 0..20
        if (!is_finite($ts)) $ts = 10.0;
        $ts = max(0.0, min(20.0, $ts));

        // Lineáris, szimmetrikus leképezés: ts=10 -> 1.0
        // Feltétel legyen: (wMin + wMax) / 2 == 1.0  → pl. 0.5 és 1.5
        $w = $wMin + ($ts / 20.0) * ($wMax - $wMin);

        if (!is_finite($w)) $w = 1.0;
        return max(0.0, min($wMax, $w));
    }

    /**
     * Havi Bonus/Malus karbantartás (változatlanul hagyva).
     * FIGYELEM: lehet benne null-access kockázat, de most nem módosítom kérésed szerint.
     */
    public static function handleNewMonthLevels(){
        User::whereNot('type', UserType::ADMIN)->get()->each(function($user){
            $bonusMalus = $user->bonusMalus()->first();
            if($bonusMalus->month >= date('Y-m-01')){
                return;
            }

            if($user->has_auto_level_up == 1 && $bonusMalus->level < 15){
                $bonusMalus->level++;
            }

            UserBonusMalus::create([
                "user_id" => $user->id,
                "month"   => date('Y-m-01'),
                "level"   => $bonusMalus->level
            ]);
        });
    }

    /* =======================
       SNAPSHOT SEGÉDEK (ÚJ)
       ======================= */

    /**
     * Assessment.org_snapshot betöltése és gyors indexek építése.
     * Ha nincs (vagy hibás), null-t ad vissza (felsőbb szint kezeli).
     */
    private static function loadAssessmentSnapshot(int $assessmentId): ?array
    {
        $row = DB::table('assessment')->where('id', $assessmentId)->select('org_snapshot')->first();
        if (!$row || empty($row->org_snapshot)) return null;

        $snap = json_decode($row->org_snapshot, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($snap)) return null;

        // Indexek
        $userIds = [];
        $ceoIds  = [];
        if (!empty($snap['users'])) {
            foreach ($snap['users'] as $u) {
                $uid = (int)($u['id'] ?? 0);
                if ($uid > 0) {
                    $userIds[$uid] = true;
                    if (!empty($u['is_ceo'])) $ceoIds[$uid] = true;
                }
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
            'total'            => $result['total'],
            'selfTotal'        => $result['self'],
            'colleagueTotal'   => $result['colleague'],
            'colleaguesTotal'  => $result['colleagues_raw'],
            'managersTotal'    => $result['manager'],
            'bossTotal'        => $result['managers_raw'],
            'ceoTotal'         => $result['ceo'],
            'sum'              => $result['sum'],
            'complete'         => $result['complete'] ?? true,
            
            // Aliases for compatibility (some views use these)
            'peersTotal'       => $result['colleague'],
            'leadersTotal'     => $result['manager'],
            'leaderTotal'      => $result['manager'],
            'rankTotal'        => $result['ceo'],
        ];
    }
}
