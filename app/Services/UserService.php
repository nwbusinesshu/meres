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
        // Végső pont (0..100)
        'total'            => $d['final_0_100'],

        // Self (0..100) – már eleve 0..100
        'selfTotal'        => $d['self_points'],

        // KOLLÉGA: megjelenítéshez 0..100
        'colleagueTotal'   => $colleague100,
        // (kompat: ha máshol a 0..150 kell, itt hagyjuk meg)
        'colleaguesTotal'  => $d['colleague_points'], // 0..150

        // FELETTES: megjelenítéshez 0..100
        'managersTotal'    => $boss100,
        // (kompat: régi név 0..150 skálán)
        'bossTotal'        => $d['boss_points'],      // 0..150

        // CEO rank komponens maga a rank (0..100)
        'ceoTotal'         => $d['ceo_points'],       // 0..100

        // Összeg 0..500 (self 100 + coll 150 + boss 150 + ceo 100)
        'sum'              => $d['sum_0_500'],

        // opcionális aliasok, ha bárhol így hivatkoztatok
        'peersTotal'       => $colleague100,          // 0..100 alias
        'leadersTotal'     => $boss100,               // 0..100 alias
        'leaderTotal'      => $boss100,               // 0..100 alias
        'rankTotal'        => $d['ceo_points'],       // 0..100 alias

        // ha később használnátok
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
     * ÚJ pontszámítási mag – org-szűrt, telemetria-súlyozott, CEO-rank fallback.
     * - csak az aktuális org-ban (rater és target is),
     * - telemetry_ai ha VAN → súlyozás, ha NINCS → súly=1.0,
     * - 4 komponens kötelező; hiányzó komponens fallback = CEO rank arányosítása,
     * - vissza: komponensek + végső 0..100.
     *
     * @return array{
     *   final_0_100:int, sum_0_500:int, self_points:int, colleague_points:int, boss_points:int, ceo_points:int, complete:bool
     * }
     */
    public static function calculateUserPointsDetailedByIds(int $assessmentId, int $userId): array
    {

        // Assessment → org_id
        $orgId = DB::table('assessment')->where('id', $assessmentId)->value('organization_id');
        if (!$orgId) {
            return self::emptyResult();
        }

        // target legyen org-tag
        $isTargetInOrg = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->exists();
        if (!$isTargetInOrg) {
            return self::emptyResult();
        }

        // CEO rank (kötelező komponens)
        $ceoRank = DB::table('user_ceo_rank as r')
          ->join('organization_user as ou_target', 'ou_target.user_id', '=', 'r.user_id')
          ->join('organization_user as ou_ceo', 'ou_ceo.user_id', '=', 'r.ceo_id') // NEW
          ->where('ou_target.organization_id', $orgId)
          ->where('ou_ceo.organization_id', $orgId) // NEW
          ->where('r.assessment_id', $assessmentId)
          ->where('r.user_id', $userId)
          ->avg('r.value');


        if ($ceoRank === null) {
            // nincs CEO rank → nincs teljes pont
            return self::emptyResult(false);
        }
        $ceoRank = (float) $ceoRank; // 0..100

        // SELF (0..100) – ha nincs, fallback = CEO rank
        $selfAvg = DB::table('competency_submit as cs')
            ->join('organization_user as ou_target', 'ou_target.user_id', '=', 'cs.target_id')
            ->join('organization_user as ou_rater',  'ou_rater.user_id',  '=', 'cs.user_id')
            ->where('ou_target.organization_id', $orgId)
            ->where('ou_rater.organization_id',  $orgId)
            ->where('cs.assessment_id', $assessmentId)
            ->where('cs.target_id', $userId)
            ->where('cs.user_id', $userId)
            ->avg('cs.value');

        $selfPoints = (int) round(($selfAvg !== null ? (float)$selfAvg : $ceoRank)); // 0..100

        // KOLLÉGA (0..150) – trust-súlyozott átlag (ha van telemetry_ai)
        $colleaguePoints = self::weightedCategoryPoints(
            assessmentId: $assessmentId,
            orgId: $orgId,
            targetId: $userId,
            typesOrWeights: ['colleague' => 1.0,'peer'=> 1.0],
            fallbackFromCeoRank: $ceoRank,
            maxPoints: 150
        );

        // FELETTES(ek) (0..150) – rugalmas típusok, trust-súlyozott átlag
        $bossPoints = self::weightedCategoryPoints(
          assessmentId: $assessmentId,
          orgId: $orgId,
          targetId: $userId,
          typesOrWeights: ['subordinate' => 1.0, 'ceo' => 0.5], // CEO kérdőív fél súllyal
          fallbackFromCeoRank: $ceoRank,
          maxPoints: 150
      );


        // CEO rank komponens (0..100)
        $rankPoints = (int) round($ceoRank);

        // Összeg (0..500) → /5 = végső (0..100)
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
     */
    private static function weightedCategoryPoints(
    int $assessmentId,
    int $orgId,
    int $targetId,
    array $typesOrWeights,       // lehet ['colleague'] VAGY ['subordinate'=>1.0,'ceo'=>0.5]
    float $fallbackFromCeoRank,  // 0..100
    int $maxPoints = 150,
    float $wMin = 0.5,
    float $wMax = 1.5
): int {
    // 0) Típuslista + szorzók előkészítése
    $types = [];
    $typeWeights = [];
    foreach ($typesOrWeights as $k => $v) {
        if (is_string($k)) { $types[] = $k; $typeWeights[$k] = (float)$v; }
        else               { $types[] = $v; $typeWeights[$v] = 1.0; }
    }

    $rows = DB::table('competency_submit as cs')
        ->join('user_competency_submit as ucs', function ($join) {
            $join->on('ucs.assessment_id', '=', 'cs.assessment_id')
                 ->on('ucs.user_id',       '=', 'cs.user_id')
                 ->on('ucs.target_id',     '=', 'cs.target_id');
        })
        ->join('organization_user as ou_target', 'ou_target.user_id', '=', 'cs.target_id')
        ->join('organization_user as ou_rater',  'ou_rater.user_id',  '=', 'cs.user_id')
        ->where('ou_target.organization_id', $orgId)
        ->where('ou_rater.organization_id',  $orgId)
        ->where('cs.assessment_id', $assessmentId)
        ->where('cs.target_id', $targetId)
        ->whereIn('cs.type', $types)
        ->groupBy('cs.assessment_id', 'cs.user_id', 'cs.target_id', 'ucs.telemetry_ai', 'cs.type')
        ->selectRaw('AVG(cs.value) as questionnaire_avg')
        ->addSelect('ucs.telemetry_ai', 'cs.type')
        ->get();

    if ($rows->isEmpty()) {
        return (int) round($fallbackFromCeoRank * $maxPoints / 100.0);
    }

    // 1) Súlyok (telemetria × type-szorzó)
    $items = [];
    $sumW  = 0.0;
    foreach ($rows as $row) {
        $avg = (float) $row->questionnaire_avg; // 0..100
        $w   = self::trustWeightFromTelemetry($row->telemetry_ai, $wMin, $wMax);
        $tw  = $typeWeights[$row->type] ?? 1.0;
        $w  *= $tw;
        if (!is_finite($w) || $w <= 0) $w = 1.0;
        $items[] = ['avg' => $avg, 'w' => $w];
        $sumW   += $w;
    }

    // 2) Átlag-normalizálás (a tw-vel együtt): mean(w) = 1.0
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
     * telemetry_ai JSON → trust súly (0..1).
     * - Ha NINCS telemetry_ai → 1.0 (histórikus pontok nem változnak).
     * - trust_score 0..20; ha <5 → 0.0; különben (ts/20)^gamma.
     *   (trust_index, ha van, 0..1: extra szorzóként bevehető; most nem kötelező.)
     */
    private static function trustWeightFromTelemetry(?string $telemetryJson, float $wMin = 0.5, float $wMax = 1.5): float
{
    // Nincs telemetria → neutrális 1.0
    if (!$telemetryJson) return 1.0;

    $data = json_decode($telemetryJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return 1.0;
    }

    // Csak a trust_score-t használjuk; a trust_index-et NEM (az számolódhat belőle később, de itt nincs rá szükség)
    if (!array_key_exists('trust_score', $data)) {
        return 1.0;
    }

    $ts = (float) $data['trust_score'];           // elvárt tartomány: 0..20
    if (!is_finite($ts)) $ts = 10.0;
    $ts = max(0.0, min(20.0, $ts));

    // Lineáris, szimmetrikus leképezés: ts=10 -> 1.0
    // Feltétel legyen: (wMin + wMax) / 2 == 1.0  → pl. 0.5 és 1.5
    $w = $wMin + ($ts / 20.0) * ($wMax - $wMin);

    // Végső clamp (védőkorlát)
    if (!is_finite($w)) $w = 1.0;
    return max(0.0, min($wMax, $w));
}

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
}
