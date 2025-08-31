<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * A beküldéskori telemetria szerver-oldali összerakása (raw + digest + feature-ök),
 * és az AI (telemetry_ai) meghívása / tárolása.
 */
class TelemetryService
{
    /**
     * @param array|null $clientTelemetry  A kliens JS által küldött telemetry_raw (vagy null, ha nincs/hibás)
     * @param object     $assessment       AssessmentService::getCurrentAssessment() (App\Models\Assessment)
     * @param User       $user             A rater (aki értékel)
     * @param User       $target           A cél (akit értékelnek)
     * @param Collection $questions        A target kérdései (CompetencyQuestion-ok gyűjteménye)
     * @param array      $answers          A request->input('answers', []) tömb
     * @return array                       A mentendő telemetry_raw (JSON-olható tömb)
     */
    public static function makeTelemetryRaw(?array $clientTelemetry, $assessment, User $user, User $target, Collection $questions, array $answers): array
    {
        // 1) Szerver-oldali kontextus
        $relationType = self::resolveRelationType($user, $target);

        $serverContext = [
            'org_id'             => $assessment->organization_id,
            'assessment_id'      => $assessment->id,
            'user_id'            => $user->id,
            'target_id'          => $target->id,
            'relation_type'      => $relationType,
            'answers_count'      => count($answers),
            'items_count_server' => $questions->count(),
            'items_count_client' => $clientTelemetry['items_count'] ?? null,
            'server_received_at' => now()->toIso8601String(),
            'client_started_at'  => $clientTelemetry['started_at'] ?? null,
            'client_finished_at' => $clientTelemetry['finished_at'] ?? null,
            'measurement_uuid'   => $clientTelemetry['measurement_uuid'] ?? null,
            'tz_offset_min'      => $clientTelemetry['tz_offset_min'] ?? null,
            'version'            => 't1.0',
        ];

        // --- ÚJ: tartalmi statok a beérkezett pontokból ---
        $contentStats = self::computeContentStats($questions, $answers);

        // 2) Feature-ök (gyors jelzők)
        $features = self::deriveFeatures($questions, $answers, $clientTelemetry);

        // 3) History digest (korábbi AI-kimenetekből, azonos org, ugyanettől a usertől)
        $historyDigest = self::buildHistoryDigest($assessment->organization_id, $user->id, $target->id);

        $baseline = self::buildBaseline(
        (int)$assessment->organization_id,
        (int)$assessment->id,
        (int)$target->id,
        (int)$user->id
        );

        // itt hozzáadjuk az aktuális rater 0–100 átlagát is a baseline-hoz
        $baseline['current_mean_100'] = self::currentRaterMean100((int)$assessment->id, (int)$user->id, (int)$target->id);
        if ($baseline['available'] && $baseline['mean_100'] !== null && $baseline['current_mean_100'] !== null) {
            $baseline['delta_mean'] = round($baseline['current_mean_100'] - $baseline['mean_100'], 2);
        } else {
            $baseline['delta_mean'] = null;
        }

        // 4) Opcionális méretkorlát a kliens-blokkra (védelem)
        $clientBlock = $clientTelemetry;
        if ($clientTelemetry) {
            $size = strlen(json_encode($clientTelemetry));
            if ($size > 800_000) { // ~800 KB felett vágunk
                $clientBlock = [
                    'oversize' => true,
                    'reason'   => 'client_telemetry_too_large',
                    'bytes'    => $size,
                ];
            }
        }

        return [
            'client'          => $clientBlock,
            'server_context'  => $serverContext,
            'content_stats'   => $contentStats, // <-- ÚJ
            'features'        => $features,
            'baseline'        => $baseline,     // <-- ÚJ
            'history_digest'  => $historyDigest,
        ];
    }

    /** Reláció-típus feloldása a jelenlegi rater–target párosra. */
    protected static function resolveRelationType(User $user, User $target): ?string
    {
        if ($user->id === $target->id) {
            return UserRelationType::SELF;
        }
        $rel = $user->relations()->where('target_id', $target->id)->first();
        $type = $rel?->type;

        // CEO override
        if ($type === UserRelationType::SUBORDINATE && session('utype') == UserType::CEO) {
            return UserType::CEO;
        }
        return $type;
    }

    /** Szerver-oldali jelzők: all_same, extremes_only, count_mismatch, too_fast_total stb. */
   protected static function deriveFeatures(Collection $questions, array $answers, ?array $clientTelemetry): array
{
    // --- Alap minták a válaszokból ---
    $vals = array_map(static fn($a) => (int)($a['value'] ?? 0), $answers);
    $allSame = count($vals) ? (count(array_unique($vals)) === 1) : false;

    // szélsőértékek (saját kérdés skáláján)
    $answersByQ = [];
    foreach ($answers as $a) { $answersByQ[$a['questionId']] = (int)$a['value']; }

    $extremesOnly = false;
    if (count($answersByQ)) {
        $extremesOnly = $questions->every(function($q) use ($answersByQ) {
            $v = $answersByQ[$q->id] ?? null;
            if ($v === null) return false;
            $min = 0;
            $max = (int)$q->max_value;
            return ($v === $min || $v === $max);
        });
    }

    // kliens-számok megléte
    $countMismatch = isset($clientTelemetry['items_count'])
        ? ((int)$clientTelemetry['items_count'] !== (int)$questions->count())
        : false;

    // dinamikus "gyorsaság" küszöb összidőre
    $thresholdMs = max(8000, $questions->count() * 800);
    $tooFastTotal = isset($clientTelemetry['total_ms'])
        ? ((int)$clientTelemetry['total_ms'] < $thresholdMs)
        : false;

    // --- Kliens-telemetriából mélyebb minták ---
    $fastClicksP1500 = null;   // inter-click <=1.5s arány
    $fastClicksP1000 = null;   // inter-click <=1.0s arány
    $paceCV = null;            // szórás/átlag a deltákon
    $paceMedian = null;        // ms
    $paceIqr = null;           // [q1,q3] ms
    $oneClickRate = null;      // changes_count==0 arány
    $oneClickAll  = null;      // minden kérdés egyklikkes
    $shortReadP1500 = null;    // (fi - seen) <=1.5s arány
    $activeRatio = null;       // active_ms / total_ms
    $incompleteScroll = null;  // max_index < items_count

    // kis segédek
    $percent = function(int $num, int $den) {
        return $den > 0 ? round($num / $den, 3) : null;
    };
    $stats = function(array $arr) {
        $n = count($arr);
        if ($n === 0) return [null,null,[null,null],null];
        sort($arr);
        $median = ($n % 2) ? $arr[intdiv($n,2)] : (($arr[$n/2-1] + $arr[$n/2]) / 2);
        $q1 = $arr[max(0, intdiv($n,4)-1)];
        $q3 = $arr[min($n-1, intdiv(3*$n,4))];
        $mean = array_sum($arr) / $n;
        $var = 0.0;
        if ($n > 0) {
            foreach ($arr as $v) { $var += ($v - $mean) * ($v - $mean); }
            $var /= $n; // populációs
        }
        $sd = sqrt($var);
        $cv = ($mean > 0) ? ($sd / $mean) : null;
        return [$median, $mean, [$q1,$q3], $cv];
    };

    if (is_array($clientTelemetry)) {
        // active_ratio
        if (isset($clientTelemetry['active_ms'], $clientTelemetry['total_ms'])) {
            $t = (int)$clientTelemetry['total_ms'];
            $a = (int)$clientTelemetry['active_ms'];
            $activeRatio = ($t > 0) ? round($a / $t, 3) : null;
        }

        // incomplete_scroll
        if (isset($clientTelemetry['scroll_sections_seen']['max_index'], $clientTelemetry['items_count'])) {
            $incompleteScroll = ((int)$clientTelemetry['scroll_sections_seen']['max_index'] < (int)$clientTelemetry['items_count']);
        }

        // items → inter-click delták, one-click rate, short_read
        $items = $clientTelemetry['items'] ?? null;
        if (is_array($items) && $items) {
            // rendezés index szerint
            usort($items, fn($a,$b)=> (int)($a['index']??0) <=> (int)($b['index']??0));

            $fi = []; $reads = []; $changes = [];
            foreach ($items as $it) {
                $fiVal = $it['first_interaction_ms'] ?? null;
                if ($fiVal !== null) $fi[] = (int)$fiVal;

                if (isset($it['first_seen_ms'], $it['first_interaction_ms'])) {
                    $d = (int)$it['first_interaction_ms'] - (int)$it['first_seen_ms'];
                    if ($d >= 0) $reads[] = $d;
                }

                $changes[] = (int)($it['changes_count'] ?? 0);
            }

            // inter-click delták
            $deltas = [];
            for ($i=1; $i<count($fi); $i++) {
                $d = $fi[$i] - $fi[$i-1];
                if ($d >= 0) $deltas[] = $d;
            }
            if ($deltas) {
                $fastClicksP1500 = $percent(count(array_filter($deltas, fn($d)=>$d<=1500)), count($deltas));
                $fastClicksP1000 = $percent(count(array_filter($deltas, fn($d)=>$d<=1000)), count($deltas));
                [$paceMedian, $paceMean, $iqr, $paceCV] = $stats($deltas);
                $paceIqr = $iqr;
            }

            // one-click
            $nItems = count($items);
            if ($nItems > 0) {
                $oneClickRate = $percent(count(array_filter($changes, fn($c)=>$c===0)), $nItems);
                $oneClickAll  = ($oneClickRate === 1.0);
            }

            // short read
            if ($reads) {
                $shortReadP1500 = $percent(count(array_filter($reads, fn($d)=>$d<=1500)), count($reads));
            }
        }
    }

    // Derivált flagek a „végigszaladós” mintára
    $tooFastBurst = ($fastClicksP1500 !== null && $fastClicksP1500 >= 0.7);
    $suspiciousMetronome = ($tooFastBurst && $paceCV !== null && $paceCV <= 0.25);
    $suspiciousOneClick  = ($oneClickAll === true) || (($oneClickRate !== null && $oneClickRate >= 0.9) && ($fastClicksP1500 !== null && $fastClicksP1500 >= 0.6));

    return [
        // eddigi jelzők
        'all_same_value'   => $allSame,
        'extremes_only'    => $extremesOnly,
        'count_mismatch'   => $countMismatch,
        'too_fast_total'   => $tooFastTotal,

        // új „gyors végigkattintás” jelek
        'fast_clicks_p1500'   => $fastClicksP1500,
        'fast_clicks_p1000'   => $fastClicksP1000,
        'pace_cv'             => $paceCV,
        'pace_median_ms'      => $paceMedian,
        'pace_iqr_ms'         => $paceIqr,
        'one_click_rate'      => $oneClickRate,
        'one_click_all'       => $oneClickAll,
        'short_read_p1500'    => $shortReadP1500,
        'active_ratio'        => $activeRatio,
        'incomplete_scroll'   => $incompleteScroll,

        // derivált flagek
        'too_fast_burst'         => $tooFastBurst,
        'suspicious_metronome'   => $suspiciousMetronome,
        'suspicious_one_click'   => $suspiciousOneClick,
    ];
}

// TelemetryService::computeContentStats
protected static function computeContentStats(Collection $questions, array $answers): array
{
    $n = count($answers);
    if ($n === 0) {
        return [
            'items_count'     => 0,
            'value_counts'    => [],
            'dominant_value'  => null,
            'dominant_share'  => null,
            'extremes_share'  => null,
            'all_same_value'  => null,
            'mean_percent'    => null,
            'stddev_percent'  => null,
        ];
    }

    // gyors elérés: kérdés -> max
    $qMax = [];
    foreach ($questions as $q) $qMax[$q->id] = (int)$q->max_value;

    $values = [];
    $percents = [];
    $extremes = 0;
    foreach ($answers as $a) {
        $qid = (int)($a['questionId'] ?? 0);
        $v   = (int)($a['value'] ?? 0);
        $values[] = $v;

        $max = max(1, (int)($qMax[$qid] ?? 1));
        $p = ($v / $max) * 100.0;
        $percents[] = $p;

        if ($v === 0 || $v === $max) $extremes++;
    }

    // value_counts + domináns
    $vc = array_count_values($values);
    arsort($vc);
    $dominantValue = null; $dominantShare = null;
    if ($vc) {
        $kv = array_key_first($vc);
        $dominantValue = (int)$kv;
        $dominantShare = round(($vc[$kv] / $n), 3);
    }

    // all_same
    $allSame = (count(array_unique($values)) === 1);

    // mean/stddev 0–100
    $mean = array_sum($percents) / $n;
    $var  = 0.0;
    foreach ($percents as $p) { $var += ($p - $mean) * ($p - $mean); }
    $var = $var / $n; // populációs
    $sd  = sqrt($var);

    return [
        'items_count'     => $n,
        'value_counts'    => $vc,
        'dominant_value'  => $dominantValue,
        'dominant_share'  => $dominantShare,
        'extremes_share'  => round($extremes / $n, 3),
        'all_same_value'  => $allSame,
        'mean_percent'    => round($mean, 2),
        'stddev_percent'  => round($sd, 2),
    ];
}

// rater jelen beküldésének 0–100 átlagpontja (CompetencySubmit-ből)
protected static function currentRaterMean100(int $assessmentId, int $userId, int $targetId): ?float
{
    $avg = DB::table('competency_submit')
        ->where('assessment_id', $assessmentId)
        ->where('user_id', $userId)
        ->where('target_id', $targetId)
        ->avg('value');
    return $avg !== null ? round((float)$avg, 2) : null;
}

// baseline építés (current -> prev assessments fallback)
protected static function buildBaseline(int $orgId, int $assessmentId, int $targetId, int $excludeUserId): array
{
    // helper: trust_score kinyerése user_competency_submit.telemetry_ai JSON-ból
    $trustByUser = function(array $ucsRows): array {
        $out = [];
        foreach ($ucsRows as $r) {
            $ai = json_decode($r->telemetry_ai ?? 'null', true);
            if (is_array($ai) && isset($ai['trust_score']) && is_numeric($ai['trust_score'])) {
                $ts = max(0.0, min(20.0, (float)$ai['trust_score']));
                // kis alsó korlát, hogy 0 ne nullázza le a ratert
                $out[(int)$r->user_id] = max(1.0, $ts);
            }
        }
        return $out;
    };
    // helper: trust-súlyozott átlag rater-átlagokból
    $weightedMean = function(array $avgByUser, array $wByUser): ?float {
        $num = 0.0; $den = 0.0;
        foreach ($avgByUser as $uid => $avg) {
            if (!is_numeric($avg)) continue;
            $w = (float)($wByUser[$uid] ?? null);
            if ($w === null) continue;
            $num += $w * (float)$avg;
            $den += $w;
        }
        if ($den <= 0) return null;
        return round($num / $den, 2);
    };

    // 1) current assessment
    $ucs = DB::table('user_competency_submit')
        ->where('assessment_id', $assessmentId)
        ->where('target_id', $targetId)
        ->where('user_id', '!=', $excludeUserId)
        ->whereNotNull('telemetry_ai')
        ->get(['user_id','telemetry_ai']);

    $avgRows = DB::table('competency_submit')
        ->select('user_id', DB::raw('AVG(value) as avg_value'))
        ->where('assessment_id', $assessmentId)
        ->where('target_id', $targetId)
        ->where('user_id', '!=', $excludeUserId)
        ->groupBy('user_id')
        ->get();

    $avgByUser = [];
    foreach ($avgRows as $r) $avgByUser[(int)$r->user_id] = (float)$r->avg_value;

    $wByUser  = $trustByUser($ucs->all());
    $meanW    = $weightedMean($avgByUser, $wByUser);
    $raters   = count($wByUser);

    if ($meanW !== null && $raters >= 5) {
        return [
            'available'       => true,
            'raters_total'    => $raters,
            'assessment_span' => 'current',
            'method'          => 'weighted_by_trust',
            'mean_100'        => $meanW,
        ];
    }

    // 2) fallback: legutóbbi 1–2 korábbi assessment ugyanabban az orgban
    $prevIds = DB::table('assessment')
        ->where('organization_id', $orgId)
        ->where('id', '!=', $assessmentId)
        ->orderByDesc('id') // vagy created_at, ha van
        ->limit(2)
        ->pluck('id')
        ->all();

    if (!$prevIds) {
        return [
            'available'       => false,
            'raters_total'    => 0,
            'assessment_span' => 'none',
            'method'          => 'n/a',
            'mean_100'        => null,
        ];
    }

    $ucsPrev = DB::table('user_competency_submit')
        ->whereIn('assessment_id', $prevIds)
        ->where('target_id', $targetId)
        ->where('user_id', '!=', $excludeUserId)
        ->whereNotNull('telemetry_ai')
        ->get(['user_id','telemetry_ai']);

    // A korábbi körökben raterenként egy vagy több rekord is lehet.
    // Most egyszerűen a legutóbbi AI-t használjuk súlynak (ha több lenne).
    $wPrev = $trustByUser($ucsPrev->all());

    $avgPrevRows = DB::table('competency_submit')
        ->select('user_id', DB::raw('AVG(value) as avg_value'))
        ->whereIn('assessment_id', $prevIds)
        ->where('target_id', $targetId)
        ->where('user_id', '!=', $excludeUserId)
        ->groupBy('user_id')
        ->get();

    $avgPrevByUser = [];
    foreach ($avgPrevRows as $r) $avgPrevByUser[(int)$r->user_id] = (float)$r->avg_value;

    $meanPrev = $weightedMean($avgPrevByUser, $wPrev);
    $ratersPrev = count($wPrev);

    if ($meanPrev !== null && $ratersPrev >= 5) {
        return [
            'available'       => true,
            'raters_total'    => $ratersPrev,
            'assessment_span' => 'previous',
            'method'          => 'weighted_by_trust',
            'mean_100'        => $meanPrev,
        ];
    }

    return [
        'available'       => false,
        'raters_total'    => max($raters, $ratersPrev),
        'assessment_span' => $raters ? 'current_insufficient' : 'previous_insufficient',
        'method'          => 'n/a',
        'mean_100'        => null,
    ];
}


    /**
     * History-digest építése a user korábbi AI-értékeléseiből (azonos szervezet).
     * Visszaad: max 20 rekordból sűrített, a promptoláshoz alkalmas összefoglaló.
     */
    protected static function buildHistoryDigest(int $organizationId, int $userId, int $currentTargetId): array
    {
        $rows = DB::table('user_competency_submit as ucs')
            ->join('assessment as a', 'a.id', '=', 'ucs.assessment_id')
            ->where('a.organization_id', $organizationId)
            ->where('ucs.user_id', $userId)
            ->whereNotNull('ucs.telemetry_ai')
            ->orderByDesc('ucs.submitted_at')
            ->limit(50) // előszűrés; később 20-ra vágjuk
            ->get(['ucs.telemetry_ai', 'ucs.target_id', 'ucs.submitted_at']);

        $entries = [];
        foreach ($rows as $r) {
            $ai = json_decode($r->telemetry_ai, true);
            if (!is_array($ai)) { continue; }
            $flags = $ai['flags'] ?? [];
            if (is_array($flags)) {
                $flags = array_values(array_filter($flags, fn($f)=>is_string($f)));
            } else {
                $flags = [];
            }
            $entries[] = [
                'trust_score'       => $ai['trust_score'] ?? null,
                'flags'             => $flags,
                'relation_type'     => $ai['relation_type'] ?? null,
                'target_id'         => $ai['target_id'] ?? $r->target_id,
                'ai_timestamp'      => $ai['ai_timestamp'] ?? ($r->submitted_at ? (string)$r->submitted_at : null),
                'device_type'       => $ai['features_snapshot']['device_type'] ?? null,
                'features_snapshot' => $ai['features_snapshot'] ?? null,
            ];
        }

        // legutóbbi 20
        $entries = array_slice($entries, 0, 20);
        $n = count($entries);

        // mintabőség → guidance
        $tier = 'cold_start';   // <5
        $guidance = 'be_kind';
        if ($n >= 5 && $n <= 15) { $tier = 'medium'; $guidance = 'balanced'; }
        if ($n >= 16)           { $tier = 'rich';   $guidance = 'strict';   }

        if ($n === 0) {
            return [
                'n'        => 0,
                'tier'     => $tier,
                'guidance' => $guidance,
                'message'  => 'Nincs korábbi AI-értékelés. Új felhasználó – legyünk kíméletesek.',
            ];
        }

        // időablak napokban
        $ts = array_values(array_filter(array_map(fn($e)=>strtotime($e['ai_timestamp'] ?? ''), $entries)));
        sort($ts);
        $window_days = (count($ts) >= 2) ? max(0, round(($ts[count($ts)-1]-$ts[0]) / 86400)) : 0;

        // reláció / eszköz mix
        $relCount = [];
        $devCount = [];
        $trust = [];
        $flagCount = [];
        foreach ($entries as $e) {
            $relKey = $e['relation_type'] ?? 'unknown';
            $relCount[$relKey] = ($relCount[$relKey] ?? 0) + 1;

            if ($e['device_type'] !== null) {
                $devCount[$e['device_type']] = ($devCount[$e['device_type']] ?? 0) + 1;
            }
            if (is_numeric($e['trust_score'])) $trust[] = (float)$e['trust_score'];
            foreach (($e['flags'] ?? []) as $f) { $flagCount[$f] = ($flagCount[$f] ?? 0) + 1; }
        }
        sort($trust);
        $median = function(array $arr) {
            $n = count($arr); if ($n===0) return null;
            $m = intdiv($n,2);
            return ($n%2) ? $arr[$m] : (($arr[$m-1]+$arr[$m])/2);
        };
        $q1 = ($trust) ? $trust[max(0, intdiv(count($trust),4)-1)] : null;
        $q3 = ($trust) ? $trust[min(count($trust)-1, intdiv(3*count($trust),4))] : null;

        $lowRate  = ($trust) ? round(count(array_filter($trust, fn($x)=>$x<=8)) / count($trust), 2) : null;
        $highRate = ($trust) ? round(count(array_filter($trust, fn($x)=>$x>=17)) / count($trust), 2) : null;

        // trend (felek mediánja)
        $half = intdiv($n,2);
        $firstHalf = array_slice($trust, 0, max(1,$half));
        $secondHalf = array_slice($trust, -max(1,$half));
        $trend = 'flat';
        if ($firstHalf && $secondHalf) {
            $diff = ($median($secondHalf) - $median($firstHalf));
            if ($diff >= 1) $trend = 'up';
            elseif ($diff <= -1) $trend = 'down';
        }

        // flags_top
        arsort($flagCount);
        $flags_top = [];
        foreach (array_slice($flagCount, 0, 3, true) as $f=>$c) {
            $flags_top[] = ['flag'=>$f, 'rate'=> round($c / $n, 2)];
        }

        // features_summary (median/IQR/coverage kulcsok) — snapshot kulcsokból
        $pick = function(string $key) use ($entries) {
            $vals = [];
            foreach ($entries as $e) {
                if (isset($e['features_snapshot'][$key]) && is_numeric($e['features_snapshot'][$key])) {
                    $vals[] = (float)$e['features_snapshot'][$key];
                }
            }
            sort($vals);
            if (!$vals) return ['median'=>null,'iqr'=>[null,null],'coverage'=>0];
            $n = count($vals);
            $median = ($n%2) ? $vals[intdiv($n,2)] : (($vals[$n/2-1]+$vals[$n/2])/2);
            $q1 = $vals[max(0, intdiv($n,4)-1)];
            $q3 = $vals[min($n-1, intdiv(3*$n,4))];
            return ['median'=>$median, 'iqr'=>[$q1,$q3], 'coverage'=>$n];
        };

        $features_summary = [
            'avg_ms_per_item_ms' => $pick('avg_ms_per_item'),
            'uniform_ratio'      => $pick('uniform_ratio'),
            'entropy'            => $pick('entropy'),
            'zigzag_index'       => $pick('zigzag_index'),
            'fast_pass_rate'     => $pick('fast_pass_rate'),
        ];

        // by_relation szelet
        $by_relation = [];
        $grouped = [];
        foreach ($entries as $e) {
            $k = $e['relation_type'] ?? 'unknown';
            $grouped[$k] = $grouped[$k] ?? [];
            $grouped[$k][] = $e;
        }
        foreach ($grouped as $k=>$arr) {
            $t = array_values(array_filter(array_map(fn($e)=>$e['trust_score'], $arr), fn($v)=>is_numeric($v)));
            sort($t);
            $m = $t ? ($t[count($t)%2 ? intdiv(count($t),2) : (count($t)/2-1)]) : null;
            $fc = [];
            foreach ($arr as $e) foreach (($e['flags'] ?? []) as $f) { $fc[$f] = ($fc[$f] ?? 0) + 1; }
            arsort($fc);
            $top = [];
            foreach (array_slice($fc, 0, 2, true) as $f=>$c) { $top[] = ['flag'=>$f, 'rate'=> round($c / max(1,count($arr)), 2)]; }
            $by_relation[$k] = ['n'=>count($arr), 'trust_median'=>$m, 'flags_top'=>$top];
        }

        // for_current_target (ha van >=3 minta ugyanarra a targetre)
        $sameTarget = array_values(array_filter($entries, fn($e)=>($e['target_id'] ?? null) === $currentTargetId));
        $for_current_target = null;
        if (count($sameTarget) >= 3) {
            $t = array_values(array_filter(array_map(fn($e)=>$e['trust_score'], $sameTarget), fn($v)=>is_numeric($v)));
            sort($t);
            $m = $t ? ($t[count($t)%2 ? intdiv(count($t),2) : (count($t)/2-1)]) : null;
            $fc = [];
            foreach ($sameTarget as $e) foreach (($e['flags'] ?? []) as $f) { $fc[$f] = ($fc[$f] ?? 0) + 1; }
            arsort($fc);
            $top = [];
            foreach (array_slice($fc, 0, 2, true) as $f=>$c) { $top[] = ['flag'=>$f, 'rate'=> round($c / count($sameTarget), 2)]; }
            $for_current_target = [
                'target_id'    => $currentTargetId,
                'n'            => count($sameTarget),
                'trust_median' => $m,
                'flags_top'    => $top,
            ];
        }

        return [
            'n'                  => $n,
            'window_days'        => $window_days,
            'tier'               => $tier,      // cold_start | medium | rich
            'guidance'           => $guidance,  // be_kind | balanced | strict
            'relation_mix'       => $relCount,
            'device_mix'         => $devCount,
            'trust_summary'      => [
                'median'    => $median($trust ?? []),
                'iqr'       => [$q1, $q3],
                'low_rate'  => $lowRate,
                'high_rate' => $highRate,
                'trend'     => $trend,
            ],
            'flags_top'          => $flags_top,
            'features_summary'   => $features_summary,
            'by_relation'        => $by_relation,
            'for_current_target' => $for_current_target,
        ];
    }

    public static function scoreAndStoreTelemetryAI(int $assessmentId, int $userId, int $targetId): ?array
    {
        Log::info('[AI] scoreAndStoreTelemetryAI about to call', compact('assessmentId','userId','targetId'));

        $row = DB::table('user_competency_submit')
            ->where('assessment_id', $assessmentId)
            ->where('user_id', $userId)
            ->where('target_id', $targetId)
            ->first(['telemetry_raw', 'submitted_at']);

        if (!$row || !$row->telemetry_raw) {
            Log::warning('[AI] scoreAndStoreTelemetryAI: missing telemetry_raw', compact('assessmentId','userId','targetId'));
            return null;
        }

        $telemetryRaw = self::normalizeJsonToArray($row->telemetry_raw);

        if (!is_array($telemetryRaw)) {
        Log::warning('[AI] scoreAndStoreTelemetryAI: telemetry_raw decode failed', [
        'assessmentId' => $assessmentId,
        'userId'       => $userId,
        'targetId'     => $targetId,
        'sample'       => substr((string)$row->telemetry_raw, 0, 300) // diagnosztika
        ]);
    return null;
}


        // 1) Kompakt AI payload építése
        $compact = self::buildCompactPayloadForAI($telemetryRaw);

        // 2) Prompt + JSON schema
        [$prompt, $jsonSchema] = self::buildPromptAndSchema($compact);

        // 3) OpenAI hívás
        $ai = self::callOpenAI($prompt, $jsonSchema, $compact['meta']);

        if ($ai) {
            $affected = DB::table('user_competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('user_id', $userId)
                ->where('target_id', $targetId)
                ->update([
                    'telemetry_ai' => json_encode($ai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

            $check = DB::table('user_competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('user_id', $userId)
                ->where('target_id', $targetId)
                ->value('telemetry_ai');

            Log::info('[AI] scoreAndStoreTelemetryAI stored', [
                'trust_score' => $ai['trust_score'] ?? null,
                'trust_index' => $ai['trust_index'] ?? null, // <-- új
                'affected'    => $affected,
                'non_empty'   => is_string($check) && strlen($check) > 2,
            ]);
        } else {
            Log::warning('[AI] scoreAndStoreTelemetryAI returned NULL', compact('assessmentId','userId','targetId'));
        }
        
        return $ai;
    }

    /**
     * A telemetry_raw-ból épít egy könnyű, AI-barát inputot.
     */
    protected static function buildCompactPayloadForAI(array $raw): array
{
    $client   = $raw['client'] ?? null;
    $server   = $raw['server_context'] ?? [];
    $features = $raw['features'] ?? [];
    $history  = $raw['history_digest'] ?? null;
    $content  = $raw['content_stats'] ?? null;   // ÚJ
    $baseline = $raw['baseline'] ?? null;        // ÚJ

    // Rövidített items (index + olvasási mezők)
    $items = [];
    if (is_array($client) && isset($client['items']) && is_array($client['items'])) {
        foreach ($client['items'] as $it) {
            $items[] = [
                'index'                   => $it['index'] ?? null,
                'question_id'             => $it['question_id'] ?? null,
                'scale'                   => $it['scale'] ?? null,
                'chars'                   => $it['chars'] ?? null,
                'first_seen_ms'           => $it['first_seen_ms'] ?? null,
                'first_interaction_ms'    => $it['first_interaction_ms'] ?? null,
                'view_read_ms'            => $it['view_read_ms'] ?? null,
                'view_read_ms_per_100ch'  => $it['view_read_ms_per_100ch'] ?? null,
                'seq_read_ms_raw'         => $it['seq_read_ms_raw'] ?? null,
                'seq_read_ms_active'      => $it['seq_read_ms_active'] ?? null,
                'seq_read_ms_per_100ch'   => $it['seq_read_ms_per_100ch'] ?? null,
                'last_value'              => $it['last_value'] ?? null,
                'changes_count'           => $it['changes_count'] ?? 0,
                'focus_ms'                => $it['focus_ms'] ?? null,
            ];
        }
        usort($items, fn($a,$b)=> (int)($a['index'] ?? 0) <=> (int)($b['index'] ?? 0));
    }

    // Aggregált jellemzők + karakterarányos olvasás
    $agg = null;
    if (is_array($client)) {
        $itemsCount = (int)($client['items_count'] ?? 0);
        $totalMs    = (int)($client['total_ms'] ?? 0);
        $visibleMs  = (int)($client['visible_ms'] ?? 0);
        $activeMs   = (int)($client['active_ms'] ?? 0);

        $avgMsPerItem = ($itemsCount > 0 && $totalMs > 0) ? (int) round($totalMs / $itemsCount) : null;

        // uniform_ratio a last_value-kból
        $uniformRatio = null;
        if ($itemsCount > 0 && $items) {
            $vals = array_values(array_filter(array_map(fn($i)=>$i['last_value'], $items), fn($v)=>$v !== null));
            if ($vals) {
                $counts = array_count_values($vals);
                rsort($counts);
                $uniformRatio = round(($counts[0] / max(1, count($vals))), 3);
            }
        }

        // inter-click delták (first_interaction sorozat)
        $fi = [];
        foreach ($items as $i) {
            if ($i['first_interaction_ms'] !== null) $fi[] = (int)$i['first_interaction_ms'];
        }
        $deltas = [];
        for ($k=1; $k<count($fi); $k++) {
            $d = $fi[$k] - $fi[$k-1];
            if ($d >= 0) $deltas[] = $d;
        }

        $fastPassRate = null; $paceCV = null; $paceMedian = null; $paceIqr = null;
        if ($deltas) {
            $fastPassRate = round(count(array_filter($deltas, fn($d)=>$d<=1500)) / count($deltas), 3);
            $arr = $deltas; sort($arr); $n = count($arr);
            $paceMedian = ($n%2) ? $arr[intdiv($n,2)] : (($arr[$n/2-1]+$arr[$n/2])/2);
            $q1 = $arr[max(0, intdiv($n,4)-1)];
            $q3 = $arr[min($n-1, intdiv(3*$n,4))];
            $paceIqr = [$q1,$q3];
            $mean = array_sum($arr)/$n;
            $var = 0.0; foreach ($arr as $v) { $var += ($v-$mean)*($v-$mean); } $var /= $n;
            $sd = sqrt($var);
            $paceCV = ($mean>0) ? round($sd/$mean, 3) : null;
        }

        // one-click
        $oneClickRate = null; $oneClickAll = null;
        if ($items) {
            $zero = 0; $total=0;
            foreach ($items as $i) { $total++; if ((int)($i['changes_count'] ?? 0) === 0) $zero++; }
            if ($total>0) {
                $oneClickRate = round($zero/$total, 3);
                $oneClickAll  = ($oneClickRate === 1.0);
            }
        }

        // short_read (first_seen → first_interaction ≤1.5s)
        $shortReadRate = null;
        if ($items) {
            $reads=[]; foreach ($items as $i) {
                if (isset($i['first_seen_ms'],$i['first_interaction_ms'])) {
                    $d=(int)$i['first_interaction_ms']-(int)$i['first_seen_ms']; if ($d>=0) $reads[]=$d;
                }
            }
            if ($reads) $shortReadRate = round(count(array_filter($reads, fn($d)=>$d<=1500)) / count($reads), 3);
        }

        $activeRatio = ($totalMs>0) ? round($activeMs/$totalMs, 3) : null;
        $incompleteScroll = (isset($client['scroll_sections_seen']['max_index'], $client['items_count']))
            ? ((int)$client['scroll_sections_seen']['max_index'] < (int)$client['items_count'])
            : null;

        // zigzag index
        $zigzagIndex = null;
        if (count($items) >= 3) {
            $vals = array_values(array_filter(array_map(fn($i)=>$i['last_value'], $items), fn($v)=>$v !== null));
            if (count($vals) >= 3) {
                $dirs=[]; for($k=1;$k<count($vals);$k++){ $dirs[] = $vals[$k] <=> $vals[$k-1]; }
                $changes=0; for($k=1;$k<count($dirs);$k++){ if ($dirs[$k] !== 0 && $dirs[$k-1] !== 0 && $dirs[$k] !== $dirs[$k-1]) $changes++; }
                $zigzagIndex = round($changes / max(1, count($dirs)-1), 3);
            }
        }

        // karakterarányos olvasás
        $seqP100 = []; $viewP100 = [];
        foreach ($items as $i) {
            if (isset($i['seq_read_ms_per_100ch']) && is_numeric($i['seq_read_ms_per_100ch'])) $seqP100[] = (float)$i['seq_read_ms_per_100ch'];
            if (isset($i['view_read_ms_per_100ch']) && is_numeric($i['view_read_ms_per_100ch'])) $viewP100[] = (float)$i['view_read_ms_per_100ch'];
        }
        $medianOf = function(array $arr){ if (!$arr) return null; sort($arr); $n=count($arr); return ($n%2)?$arr[intdiv($n,2)]:(($arr[$n/2-1]+$arr[$n/2])/2); };
        $readingMedian = $medianOf($seqP100) ?? $medianOf($viewP100);
        $fastReadRate = $seqP100 ? round(count(array_filter($seqP100, fn($x)=>$x <= 400.0)) / count($seqP100), 3) : null;

        $agg = [
            'items_count'                 => $itemsCount,
            'total_ms'                    => $totalMs,
            'visible_ms'                  => $visibleMs,
            'active_ms'                   => $activeMs,
            'avg_ms_per_item'             => $avgMsPerItem,
            'uniform_ratio'               => $uniformRatio,
            'fast_pass_rate'              => $fastPassRate,
            'pace_cv'                     => $paceCV,
            'pace_median_ms'              => $paceMedian,
            'pace_iqr_ms'                 => $paceIqr,
            'one_click_rate'              => $oneClickRate,
            'one_click_all'               => $oneClickAll,
            'short_read_rate'             => $shortReadRate,
            'active_ratio'                => $activeRatio,
            'incomplete_scroll'           => $incompleteScroll,
            'zigzag_index'                => $zigzagIndex,
            'reading_speed_median_100ch'  => $readingMedian,   // ÚJ
            'fast_read_rate_100ch'        => $fastReadRate,    // ÚJ
            'device_type'                 => $client['device']['type'] ?? null,
        ];
    }

    // meta + backfill kulcsok
    $meta = [
        'org_id'           => $server['org_id'] ?? null,
        'relation_type'    => $server['relation_type'] ?? null,
        'target_id'        => $server['target_id'] ?? null,
        'measurement_uuid' => $server['measurement_uuid'] ?? null,
        'tier'             => $history['tier'] ?? 'cold_start',
        'guidance'         => $history['guidance'] ?? 'be_kind',
    ];
    if ($agg) {
        foreach ([
            'avg_ms_per_item','uniform_ratio','fast_pass_rate','pace_cv','pace_median_ms','pace_iqr_ms',
            'one_click_rate','active_ratio','short_read_rate','zigzag_index','device_type',
            'reading_speed_median_100ch','fast_read_rate_100ch'
        ] as $k) {
            $meta['__fill_'.$k] = $agg[$k] ?? null;
        }
    }

    return [
        'current' => [
            'server_context' => $server,
            'content_stats'  => $content,   // ÚJ
            'baseline'       => $baseline,  // ÚJ
            'features'       => $features,
            'agg'            => $agg,
            'items'          => $items,
        ],
        'history_digest' => $history,
        'meta' => $meta,
    ];
}


    /**
     * Prompt + JSON Schema felépítése.
     */
    protected static function buildPromptAndSchema(array $compact): array
{
    $meta = $compact['meta'];
    $guidance = $meta['guidance'] ?? 'be_kind';

    $prompt = <<<PROMPT
You are scoring the reliability of a single 360° assessment submission.

Rules:
- Output STRICT JSON that conforms to the provided JSON Schema. No extra text.
- Base your decision ONLY on the provided telemetry, content stats, baseline (if available), and history.
- Consider baseline.delta_mean if baseline.available==true:
  * If |delta_mean| <= 15, treat as within normal variance (no penalty by itself).
  * If delta_mean >= 15, suspect positive inflation; combine with behavior (fast/one-click/fast-read).
  * If delta_mean <= -15, suspect negative deflation; combine with behavior likewise.
- Calibrate severity by "guidance": be_kind | balanced | strict.
- When comparing numbers, do not state ‘above/below’ incorrectly; quote both values and the correct relation.
- Set fast_read only if reading_speed_median_100ch <= 400 OR short_read_rate >= 0.60. Otherwise, do not set fast_read and do not use one_click_fast_read.
- Set trust_index = clamp(round(trust_score * 5), 0, 100). Only deviate by at most ±5 points if there is overwhelming evidence; justify the deviation.

Scales:
- trust_score: 0..20 (integer)
- trust_index: 0..100 (integer) – overall rollup; this is the primary downstream signal.

Context:
- Organization ID: {$meta['org_id']}
- Relation type: {$meta['relation_type']}
- Target user ID: {$meta['target_id']}
- Guidance: {$guidance}

HR-Expert Analytical Lens (evidence-bound)

You ARE allowed to reason like an HR / I-O psychology analyst. Draw conclusions about rating QUALITY and likely RATER BIASES — but ONLY from the provided structured inputs (telemetry.*, content_stats.*, baseline.*, history_digest.*). Do NOT infer job performance, demographics, or personality.

Use recognized constructs, each tied to concrete fields + thresholds:
- Leniency / Severity bias: Use baseline.available + baseline.delta_mean.
  - Inflation: delta_mean ≥ +15 → possible global leniency; deflation: delta_mean ≤ −15 → possible severity.
  - Strengthen (not create) this conclusion only when behavior also suggests low-effort/response-set patterns (e.g., one_click_rate, fast_pass_rate, fast_read signals).
- Central Tendency / Uniform responding:
  - Indicators: high content_stats.dominant_share (≥0.60), high uniform_ratio (≥0.60), low zigzag_index (≤0.15), low stddev_percent (≤8).
  - Interpret as risk of “safe middle” responding if combined with modest pace variability (pace_cv ≤ 0.25) and low changes_count (proxied by one_click_rate).
- Extremes-only pattern:
  - Indicators: content_stats.extremes_share ≥ 0.80 (and no mid-scale usage).
  - Treat as potential satisficing or polarized response set; weigh stronger if fast_pass_rate ≥ 0.70.
- Inconsistency / Randomness:
  - Indicators: zigzag_index ≥ 0.60 with relatively high pace_cv (≥0.50) and weak reading signals; treat as noisy/unstable evaluation. Map this to the flag "suspicious_pattern".
- Speeding / Low-effort:
  - Inter-click speed: fast_pass_rate ≥ 0.70 → “too_fast”.
  - One-click: one_click_rate ≥ 0.90 (especially one_click_all = true) → “one_click_fast_read” when combined with fast_read.
  - Fast reading: reading_speed_median_100ch ≤ 400 OR short_read_rate ≥ 0.60 → “fast_read”.
- Positive evidence of care:
  - Moderate pace variability (0.25 ≤ pace_cv ≤ 0.50), multiple changes (low one_click_rate), reasonable reading time per 100 chars (>400 ms), diverse values (lower dominant_share/uniform_ratio), complete scroll (incomplete_scroll = false).

Relation-type nuance (no stereotypes):
- You may consider history_digest.by_relation[relation_type] (e.g., trust_median, flags_top) to ADJUST confidence slightly.
- Do NOT assume a relation-type is inherently lenient/severe. Only use THIS user’s own history stats and trends.

History usage:
- If history_digest.trend shows “up/down” and the current behavior aligns (e.g., repeated one_click_fast_read), reflect that in flags and the rationale. Otherwise, prefer the current submission’s evidence.

Flag policy (only from the allowed list):
- too_fast: fast_pass_rate ≥ 0.70 OR avg_ms_per_item unusually low vs content length.
- fast_read: reading_speed_median_100ch ≤ 400 OR short_read_rate ≥ 0.60.
- one_click_fast_read: one_click_rate ≥ 0.90 AND (fast_read condition true).
- incomplete_scroll: incomplete_scroll = true.
- too_uniform / extremes_only: use the content_stats thresholds above.
- low_confidence: signals are weak/contradictory or sample size is thin (history tier=cold_start) → keep scores closer to neutral.
- suspicious_pattern: use the inconsistency/randomness rule above.

Scoring method (transparent and additive):
- Start from a neutral trust_score band 10–12.
- Apply −1 to −3 for each strong adverse indicator (too_fast, one_click_fast_read, fast_read, extremes_only, too_uniform, suspicious_pattern), calibrated by guidance (be_kind | balanced | strict).
- Apply +1 to +2 for strong positive evidence (careful reading, diversified response, multiple changes, consistent pace).
- Baseline adjustment: if |delta_mean| ≥ 15 AND low-effort/response-set indicators are present, apply an additional −2 to −4 (inflation/deflation). If |delta_mean| ≥ 15 WITHOUT low-effort evidence, note it but keep adjustment small (−1..0..+1).
- Clamp trust_score to 0..20.

Rationale requirements:
- Be HR-analytic but evidence-bound. Tie every claim to concrete fields with values, e.g.:
  - “one_click_rate=1.00, fast_pass_rate=0.90, reading_speed_median_100ch=380 → low-effort, speeding”
  - “dominant_share=0.67, uniform_ratio=0.67, zigzag_index=0.30 → central-tendency risk”
  - “baseline.delta_mean=+16 with low-effort signals → probable inflation”
- Avoid content/competency judgments; evaluate response QUALITY only. No demographic/protected-class inferences. 

History digest (summarized, up to 20 past AI-scored submissions):
PROMPT;

    $prompt .= "\n" . self::jsonPretty($compact['history_digest']) . "\n\n";
    $prompt .= "Current submission (content_stats, baseline, aggregates, features, items-short):\n";
    $prompt .= self::jsonPretty($compact['current']);

    // --- features_snapshot: properties + full required list (strict) ---
    $featureProps = [
        'avg_ms_per_item'            => ['type'=>['number','null']],
        'uniform_ratio'              => ['type'=>['number','null']],
        'entropy'                    => ['type'=>['number','null']], // nullable but required key
        'zigzag_index'               => ['type'=>['number','null']],
        'fast_pass_rate'             => ['type'=>['number','null']],
        'device_type'                => ['type'=>['string','null']],
        'pace_cv'                    => ['type'=>['number','null']],
        'pace_median_ms'             => ['type'=>['number','null']],
        'pace_iqr_ms'                => ['type'=>['array','null'],'items'=>['type'=>'number'],'minItems'=>2,'maxItems'=>2],
        'one_click_rate'             => ['type'=>['number','null']],
        'active_ratio'               => ['type'=>['number','null']],
        'short_read_rate'            => ['type'=>['number','null']],
        'incomplete_scroll'          => ['type'=>['boolean','null']],
        'reading_speed_median_100ch' => ['type'=>['number','null']],
        'fast_read_rate_100ch'       => ['type'=>['number','null']],
    ];
    $featureRequired = array_keys($featureProps);

    // --- baseline_echo: object OR null, with strict object branch ---
    $baselineEchoProps = [
        'available'       => ['type'=>'boolean'],
        'delta_mean'      => ['type'=>['number','null']],
        'used'            => ['type'=>['boolean','null']],
        'assessment_span' => ['type'=>['string','null']],
    ];
    $baselineEchoRequired = array_keys($baselineEchoProps);

    // --- top-level schema ---
    $topProps = [
        'trust_score'       => ['type'=>'integer','minimum'=>0,'maximum'=>20],
        // tolerate null to avoid 400s; we also compute a fallback server-side if needed
        'trust_index'       => ['type'=>['integer','null'],'minimum'=>0,'maximum'=>100],
        'flags'             => [
            'type'=>'array',
            'items'=>[
                'type'=>'string',
                'enum'=>[
                    'too_fast','too_uniform','extremes_only','count_mismatch',
                    'low_variability','low_focus','low_visibility','incomplete_scroll',
                    'suspicious_pattern','global_severity','global_leniency',
                    'suspicious_target_bias','low_confidence',
                    'fast_read','one_click_fast_read'
                ]
            ]
        ],
        'rationale'         => ['type'=>'string','maxLength'=>800],
        'relation_type'     => ['type'=>['string','null']],
        'target_id'         => ['type'=>['integer','null']],
        'ai_timestamp'      => ['type'=>'string'],
        'features_snapshot' => [
            'type'=>'object',
            'additionalProperties'=>false,
            'properties'=>$featureProps,
            'required'=>$featureRequired,
        ],
        'baseline_echo'     => [
            'anyOf' => [
                [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $baselineEchoProps,
                    'required' => $baselineEchoRequired,
                ],
                [ 'type' => 'null' ],
            ],
        ],
    ];
    // strict mode: require every top-level key
    $topRequired = array_keys($topProps);

    $jsonSchema = [
        'name'   => 'TelemetryAi',
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $topProps,
            'required'  => $topRequired,
        ],
        'strict' => true,
    ];

    return [$prompt, $jsonSchema];
}

    /**
     * OpenAI Responses API hívása strukturált (json_schema) válasszal.
     */
   protected static function callOpenAI(string $prompt, array $jsonSchema, array $meta): ?array
{
    $apiKey  = (string) config('services.openai.key', env('OPENAI_API_KEY'));
    $model   = (string) env('OPENAI_MODEL', 'gpt-4.1-mini');
    $timeout = (int) env('OPENAI_TIMEOUT', 12);

    if (!$apiKey) {
        Log::warning('[AI] callOpenAI: missing API key');
        return null;
    }

    $idempotencyKey = 'telemetry:' . ($meta['measurement_uuid'] ?? Str::uuid()->toString());

    Log::info('[AI] callOpenAI start', [
        'model' => $model,
        'timeout' => $timeout,
        'idempotency' => $idempotencyKey,
    ]);

    try {
        $resp = Http::withHeaders([
                'Authorization'   => 'Bearer '.$apiKey,
                'Content-Type'    => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
                // 'OpenAI-Beta'     => 'responses-2024-12-17',
            ])
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => $prompt,
                'text'  => [
                    'format' => [
                        'type'   => 'json_schema',
                        'name'   => $jsonSchema['name']   ?? 'TelemetryAi',
                        'schema' => $jsonSchema['schema'] ?? [],
                        'strict' => $jsonSchema['strict'] ?? true,
                    ],
                ],
            ]);

        $status = $resp->status();
        Log::info('[AI] callOpenAI http', ['status' => $status]);

        if (!$resp->ok()) {
            $bodyPreview = substr($resp->body(), 0, 4000);
            Log::warning('[AI] callOpenAI not ok', ['status' => $status, 'body' => $bodyPreview]);
            return null;
        }

        $data = $resp->json();

        // --- struktúra kinyerése ---
        $structured = null;

        if (isset($data['output_text']) && is_string($data['output_text'])) {
            $maybe = json_decode($data['output_text'], true);
            if (is_array($maybe)) $structured = $maybe;
        }

        if (!$structured && isset($data['output']) && is_array($data['output'])) {
            $first = $data['output'][0]['content'][0] ?? null;
            if ($first) {
                if (isset($first['text']) && is_string($first['text'])) {
                    $maybe = json_decode($first['text'], true);
                    if (is_array($maybe)) $structured = $maybe;
                }
                if (!$structured && isset($first['json']) && is_array($first['json'])) {
                    $structured = $first['json'];
                }
            }
        }

        if (!$structured && isset($data['choices'][0]['message']['content'])) {
            $maybe = json_decode($data['choices'][0]['message']['content'], true);
            if (is_array($maybe)) $structured = $maybe;
        }

        if (!is_array($structured)) {
            Log::warning('[AI] callOpenAI parse failed', [
                'data_preview' => substr(json_encode($data), 0, 4000)
            ]);
            return null;
        }

        // --- meta standardizálás + timestamp ---
        $structured['relation_type'] = $structured['relation_type'] ?? ($meta['relation_type'] ?? null);
        $structured['target_id']     = $structured['target_id']     ?? ($meta['target_id'] ?? null);
        $structured['ai_timestamp']  = $structured['ai_timestamp']  ?? now()->toIso8601String();

        // --- features_snapshot backfill ---
        $structured['features_snapshot'] = $structured['features_snapshot'] ?? [];
        $backfillKeys = [
            'avg_ms_per_item','uniform_ratio','zigzag_index','fast_pass_rate','device_type',
            'pace_cv','pace_median_ms','pace_iqr_ms','one_click_rate','active_ratio','short_read_rate',
            'incomplete_scroll','reading_speed_median_100ch','fast_read_rate_100ch',
            'entropy',
        ];
        foreach ($backfillKeys as $k) {
            if (!array_key_exists($k, $structured['features_snapshot'])) {
                $structured['features_snapshot'][$k] = $meta['__fill_'.$k] ?? null;
            }
        }

        // --- trust_index fallback ---
        if (!array_key_exists('trust_index', $structured) || $structured['trust_index'] === null) {
            $structured['trust_index'] = $structured['trust_score'] ?? null;
        }

        Log::info('[AI] callOpenAI ok', [
            'trust_score' => $structured['trust_score'] ?? null,
            'trust_index' => $structured['trust_index'] ?? null,
            'flags'       => $structured['flags'] ?? null,
        ]);

        return $structured;

    } catch (\Throwable $e) {
        Log::error('[AI] callOpenAI exception', [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
        return null;
    }
}


    /** Kis segéd a promptban szépített JSON-hoz. */
    protected static function jsonPretty($v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // a class végére (bárhol a classon belül), pl. jsonPretty alá/fölé
protected static function normalizeJsonToArray(?string $raw): ?array
{
    if ($raw === null) return null;

    // 1) első próbálkozás
    $v = json_decode($raw, true);
    if (is_array($v)) return $v;

    // 2) ha string jött ki (dupla-JSON tipikus esete): próbáld még egyszer
    if (is_string($v)) {
        $v2 = json_decode($v, true);
        if (is_array($v2)) return $v2;
    }

    // 3) gyakori: túl-escape-elt backslash-ek
    $raw2 = stripslashes($raw);
    if ($raw2 !== $raw) {
        $v3 = json_decode($raw2, true);
        if (is_array($v3)) return $v3;
    }

    // 4) safety: távolítsunk el BOM-ot, nem látható whitespace-t
    $raw3 = trim($raw, "\xEF\xBB\xBF \t\n\r\0\x0B");
    if ($raw3 !== $raw) {
        $v4 = json_decode($raw3, true);
        if (is_array($v4)) return $v4;

        if (is_string($v4)) {
            $v5 = json_decode($v4, true);
            if (is_array($v5)) return $v5;
        }
    }

    return null;
    }

}
