<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enums\UserType;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SuggestedThresholdService
{
    public function __construct(
        protected ThresholdService $thresholds
    ) {}

    /**
     * AI payload felépítése – a UserService::calculateUserPoints() által
     * visszaadott mezőkre támaszkodva (ld. total/selfTotal/colleagueTotal/managersTotal/ceoTotal/sum).
     */
    public function buildAiPayload(Assessment $assessment): array
    {
        $orgId = (int) $assessment->organization_id;

        // 1) Résztvevők: kizárjuk az admint/szuperadmint, removed_at != null usert
        $users = User::whereNull('removed_at')
            ->whereNotIn('type', [UserType::ADMIN, UserType::SUPERADMIN])
            ->get();

        $rows = [];
        $totals = [];              // total 0..100
        $idIndex = [];             // user_id → total index (több érték is lehet azonos, ezért listát tartunk)
        foreach ($users as $u) {
            $stat = \App\Services\UserService::calculateUserPoints($assessment, $u);
            // Skippeljük, ha nem vett részt / nincs CEO rank / nincs érvényes total a mostani mérésben
            if (!$stat || !isset($stat->total)) {
                continue;
            }

            // Mivel a calculateUserPoints már hozza a 0..100 és (külön) 0..150 skálákat is,
            // ezeket adjuk tovább az AI-nak, hogy mindkét nézetben lássa.
            $row = [
                'user_id' => (int) $u->id,
                'display' => $u->name ?? ('U'.$u->id),

                // Összesített végső pont és „nyers” összeg is:
                'total'   => (int) $stat->total,          // 0..100
                'sum500'  => (int) ($stat->sum ?? ($stat->sum_0_500 ?? 0)), // 0..500, ha elérhető

                // Bontások – 0..100-as nézetek:
                'self' => [
                    'mean100' => isset($stat->selfTotal) ? (int)$stat->selfTotal : null, // 0..100
                ],
                'colleagues' => [
                    'mean100' => isset($stat->colleagueTotal) ? (int)$stat->colleagueTotal : null, // 0..100 (normalizált)
                    'raw150'  => isset($stat->colleaguesTotal) ? (int)$stat->colleaguesTotal : null, // 0..150 (nyers)
                ],
                'managers' => [
                    'mean100' => isset($stat->managersTotal) ? (int)$stat->managersTotal : null, // 0..100 (normalizált)
                    'raw150'  => isset($stat->bossTotal) ? (int)$stat->bossTotal : null, // 0..150 (nyers)
                ],
                'ceo_rank' => [
                    'score100' => isset($stat->ceoTotal) ? (int)$stat->ceoTotal : null, // 0..100
                ],

                // (telemetria külön nincs a calculateUserPoints-ben kiexportálva → hagyjuk null-on)
                'telemetry_trust_index' => null,
                'telemetry_flags'       => [],
            ];

            $rows[] = $row;

            // total indexek az outlier-számításhoz és rangokhoz
            $totals[] = (int) $stat->total;
            $idIndex[] = (int) $u->id;
        }

        // nincs érvényes résztvevő → minimál payload
        if (empty($rows)) {
            return [
                'org_id'     => $orgId,
                'assessment' => ['id' => (int) $assessment->id],
                'method'     => 'suggested',
                'users'      => [],
                'team'       => ['n_participants' => 0],
                'history'    => [],
                'policy'     => $this->buildPolicy($orgId),
            ];
        }

        // 2) Rangsor/percentilis (total 0..100 alapján)
        $sortedAsc  = $totals; sort($sortedAsc);
        $sortedDesc = array_reverse($sortedAsc);
        $n          = count($sortedAsc);

        // total érték → „legjobb=1” rang (azonos pont azonos rangot kap)
        $rankMap = $this->buildRanks($sortedDesc);

        // százalékpont (hány % <= érték)
        foreach ($rows as &$r) {
            $t = (int)$r['total'];
            $r['rank']      = $rankMap[$t] ?? null;
            $r['percentile'] = $this->percentileOf($sortedAsc, $t);
        }
        unset($r);

        // 3) Csapatszintű statok (kvartilisek, percentilisek, CV, IQR/median)
        $stats = $this->teamStatsWithOutliers($sortedAsc, $rows, $idIndex);

        // 4) História (utolsó 3–6 lezárt assessment snapshot + korábbi küszöbök)
        $history       = $this->fetchHistory($orgId, 6);
        $lastThreshold = $this->lastThresholdSnapshot($orgId);

        // 5) Policy (org-configból + default)
        $policy = $this->buildPolicy($orgId);

        return [
            'org_id'     => $orgId,
            'assessment' => ['id' => (int)$assessment->id, 'date' => (string)($assessment->started_at ?? '')],
            'method'     => 'suggested',

            // Csapatszint: minden fontos stat + outlier user_id-k
            'team'       => array_merge($stats, ['last_thresholds' => $lastThreshold]),

            // Felhasználó szint: total + bontások (0..100 és nyers 0..150 skálák együtt)
            'users'      => $rows,

            // Rövid történelem
            'history'    => $history,

            // Döntési keretek
            'policy'     => $policy,
        ];
    }

    public function callAiForSuggested(array $payload): ?array
    {
        // TODO: ide jön majd a tényleges OpenAI hívás (STRICT JSON kéréssel)
        return null;
    }

    /* =========================
       HELPER FÜGGVÉNYEK
       ========================= */

    /** Kvartilisek/percentilisek + homogenitás + outlierek (Z-score, MAD) – total (0..100) alapján. */
    protected function teamStatsWithOutliers(array $sortedAscTotals, array $rows, array $idIndex): array
    {
        $n = count($sortedAscTotals);
        $mean   = $this->mean($sortedAscTotals);
        $median = $this->median($sortedAscTotals);
        $mode   = $this->mode($sortedAscTotals);
        $stdev  = $this->stdevSample($sortedAscTotals, $mean);
        $min    = $sortedAscTotals[0];
        $max    = $sortedAscTotals[$n - 1];
        $q1     = $this->percentile($sortedAscTotals, 25);
        $q3     = $this->percentile($sortedAscTotals, 75);
        $iqr    = $q3 - $q1;

        $cv  = ($mean > 0) ? ($stdev / $mean) : null;
        $iqrOverMedian = ($median > 0) ? ($iqr / $median) : null;

        // OUTLIERS – Z-score
        $zCut = 2.5;
        $zIds = [];
        if ($stdev > 0) {
            // total → user_ids mapping
            // Mivel lehetnek azonos értékek, végigmegyünk a konkrét listán, nem csak érték alapján.
            $totalsOriginalOrder = array_column($rows, 'total');
            foreach ($totalsOriginalOrder as $i => $t) {
                $z = ($t - $mean) / $stdev;
                if (abs($z) >= $zCut) {
                    $zIds[] = (int)$rows[$i]['user_id'];
                }
            }
        }

        // OUTLIERS – Modified Z (MAD)
        $madIds = [];
        $mad = $this->mad($sortedAscTotals, $median);
        if ($mad > 0) {
            $factor = 0.6745 / $mad;
            $totalsOriginalOrder = array_column($rows, 'total');
            foreach ($totalsOriginalOrder as $i => $t) {
                $mz = ($t - $median) * $factor;
                if (abs($mz) >= 3.5) {
                    $madIds[] = (int)$rows[$i]['user_id'];
                }
            }
        }

        return [
            'n_participants' => $n,
            'mean' => (int) round($mean),
            'median' => (int) round($median),
            'mode' => $mode,
            'stdev' => (int) round($stdev),
            'min' => (int) $min,
            'q1'  => (int) $q1,
            'q3'  => (int) $q3,
            'max' => (int) $max,
            'iqr' => (int) $iqr,
            'percentiles' => [
                'p10' => (int) $this->percentile($sortedAscTotals, 10),
                'p25' => (int) $q1,
                'p50' => (int) $median,
                'p75' => (int) $q3,
                'p90' => (int) $this->percentile($sortedAscTotals, 90),
            ],
            'homogeneity' => [
                'cv' => $cv !== null ? round($cv, 3) : null,
                'iqr_over_median' => $iqrOverMedian !== null ? round($iqrOverMedian, 3) : null,
            ],
            'outliers' => [
                'zscore' => ['user_ids' => array_values(array_unique($zIds))],
                'mad'    => ['user_ids' => array_values(array_unique($madIds))],
            ],
        ];
    }

    protected function fetchHistory(int $orgId, int $limit = 6): array
    {
        $assess = \App\Models\Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($assess as $a) {
            $out[] = [
                'id'   => (int) $a->id,
                'date' => (string) $a->closed_at,
                'n_participants' => null,
                'mean' => null, 'median' => null, 'stdev' => null,
                'q1' => null, 'q3' => null, 'min' => null, 'max' => null,
                'thresholds_used' => [
                    'method' => $a->threshold_method ?? null,
                    'up'     => $a->normal_level_up,
                    'down'   => $a->normal_level_down,
                ],
            ];
        }
        return $out;
    }

    protected function lastThresholdSnapshot(int $orgId): array
    {
        $a = \App\Models\Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->first();

        if (!$a) return [];

        return [
            'method' => $a->threshold_method ?? null,
            'up'     => $a->normal_level_up,
            'down'   => $a->normal_level_down,
            'date'   => (string) $a->closed_at,
        ];
    }

    protected function buildPolicy(int $orgId): array
    {
        $cfg = $this->thresholds->getOrgConfigMap($orgId);
        return [
            'target_promo_rate_max'          => isset($cfg['target_promo_rate_max']) ? (float)$cfg['target_promo_rate_max'] : 0.20,
            'target_demotion_rate_max'       => isset($cfg['target_demotion_rate_max']) ? (float)$cfg['target_demotion_rate_max'] : 0.10,
            'min_gap_for_stagnation'         => isset($cfg['threshold_gap_min']) ? (int)$cfg['threshold_gap_min'] : 2,
            'never_below_abs_min_for_promo'  => isset($cfg['never_below_abs_min_for_promo']) ? (int)$cfg['never_below_abs_min_for_promo'] : null,
            'use_telemetry_trust'            => isset($cfg['use_telemetry_trust']) ? (bool)$cfg['use_telemetry_trust'] : true,
            'no_forced_demotion_if_high_cohesion' => isset($cfg['no_forced_demotion_if_high_cohesion']) ? (bool)$cfg['no_forced_demotion_if_high_cohesion'] : true,
        ];
    }

    /* ===== stat utilok ===== */

    protected function mean(array $xs): float
    {
        $n = count($xs);
        return $n ? array_sum($xs) / $n : 0.0;
    }

    protected function median(array $xs): float
    {
        $n = count($xs);
        if ($n === 0) return 0.0;
        $mid = intdiv($n, 2);
        if ($n % 2 === 0) return ($xs[$mid - 1] + $xs[$mid]) / 2.0;
        return $xs[$mid];
    }

    protected function mode(array $xs): ?int
    {
        if (empty($xs)) return null;
        $freq = array_count_values($xs);
        arsort($freq);
        $max = max($freq);
        $cands = array_keys(array_filter($freq, fn($v) => $v === $max));
        sort($cands, SORT_NUMERIC);
        return (int) $cands[0];
    }

    protected function stdevSample(array $xs, float $mean = null): float
    {
        $n = count($xs);
        if ($n < 2) return 0.0;
        if ($mean === null) $mean = $this->mean($xs);
        $sum = 0.0;
        foreach ($xs as $x) { $d = $x - $mean; $sum += $d * $d; }
        return sqrt($sum / ($n - 1));
    }

    protected function percentile(array $sortedAsc, float $p): float
    {
        $n = count($sortedAsc);
        if ($n === 0) return 0.0;
        $p = max(0.0, min(100.0, $p));
        $rank = (int) ceil(($p / 100.0) * $n);
        if ($rank < 1) $rank = 1;
        if ($rank > $n) $rank = $n;
        return (float) $sortedAsc[$rank - 1];
    }

    protected function percentileOf(array $sortedAsc, int $value): int
    {
        $n = count($sortedAsc);
        if ($n === 0) return 0;
        $count = 0;
        foreach ($sortedAsc as $v) { if ($v <= $value) $count++; else break; }
        return (int) floor(100.0 * $count / $n);
    }

    /** total → „legjobb=1” rang (azonos pont azonos rangot kap) */
    protected function buildRanks(array $sortedDesc): array
    {
        $ranks = [];
        $rank = 1;
        $prev = null;
        foreach ($sortedDesc as $v) {
            if ($prev !== null && $v !== $prev) $rank++;
            if (!isset($ranks[$v])) $ranks[$v] = $rank;
            $prev = $v;
        }
        return $ranks;
    }

    /** Median Absolute Deviation (MAD): median(|x - median(x)|) */
    protected function mad(array $sortedAsc, float $median): float
    {
        $n = count($sortedAsc);
        if ($n === 0) return 0.0;
        $abs = [];
        foreach ($sortedAsc as $x) { $abs[] = abs($x - $median); }
        sort($abs);
        return $this->median($abs);
    }
}

public function callAiForSuggested(array $payload): ?array
{
    $apiKey   = env('OPENAI_API_KEY');
    $model    = env('OPENAI_MODEL', 'gpt-4.1-mini');
    $timeout  = (int) env('OPENAI_TIMEOUT', 30);

    if (!$apiKey) {
        // nincs kulcs → fallback
        return null;
    }

    $client = new Client([
        'base_uri' => 'https://api.openai.com/v1/',
        'timeout'  => $timeout,
    ]);

    $systemPrompt = $this->aiSystemPrompt();
    $userPrompt   = $this->aiUserPromptFromPayload($payload);

    try {
        $resp = $client->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => $model,
                // JSON-kényszerítés: a válasz tiszta JSON-objektum lesz
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ],
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!$content) {
            return null;
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            return null;
        }

        // minimális validáció + normalizálás
        $out = $this->validateAiResponse($json);
        return $out;
    } catch (GuzzleException $e) {
        \Log::warning('AI call failed (suggested thresholds): ' . $e->getMessage());
        return null;
    } catch (\Throwable $e) {
        \Log::warning('AI call decode/validate error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Szabályok + elvárt JSON séma – rövid, de feszes system prompt.
 */
protected function aiSystemPrompt(): string
{
    return <<<SYS
You are a fair, deterministic thresholds engine for performance reviews.
INPUT: a JSON payload with current team scores (0..100), breakdowns (self/colleagues/managers/ceo), team statistics (mean/median/stdev/quartiles/percentiles, CV, IQR/median), outliers, brief history, and policy caps.
TASK:
1) Suggest two integer thresholds for this assessment:
   - normal_level_up (promotion threshold)
   - normal_level_down (demotion threshold)
2) Ensure a stagnation gap exists: up - down >= policy.min_gap_for_stagnation (if possible).
3) Respect policy:
   - Cap promotion rate around policy.target_promo_rate_max; demotion around policy.target_demotion_rate_max.
   - If policy.never_below_abs_min_for_promo is set, do not put 'up' below that absolute minimum.
   - If policy.no_forced_demotion_if_high_cohesion is true AND team cohesion is high (e.g., CV < 0.08 and mean >= 80), prefer fewer demotions (raise 'down' if needed).
4) Use outliers conservatively: do not set thresholds based solely on extreme tails.
5) Decision rule (informative): promote if total > up; demote if total < down; otherwise stay.
6) Output must be STRICT JSON with this schema (no extra fields, no text outside JSON):
{
  "thresholds": {
    "normal_level_up": <int>,
    "normal_level_down": <int>,
    "rationale": "<short string>"
  },
  "decisions": [
    { "user_id": <int>, "decision": "promote|stay|demote", "why": "<short string>" }
  ],
  "stats_used": {
    "team": { "mean": <int>, "median": <int>, "stdev": <int>, "q1": <int>, "q3": <int> },
    "percentiles": { "p10": <int>, "p25": <int>, "p50": <int>, "p75": <int>, "p90": <int> }
  ]
}
If uncertain, still return valid JSON with best-effort thresholds. Never return prose outside JSON.
SYS;
}

/**
 * A payloadot egyszerűen JSON-ként átadjuk, de előtte limitáljuk a hosszát ésszerű keretek között.
 * (Ha extrém nagy lenne a users tömb, itt lehet trimmelni/top-N metrikákat küldeni.)
 */
protected function aiUserPromptFromPayload(array $payload): string
{
    // Biztonság kedvéért ne legyen túl hosszú (pl. 1.5 MB felett vágjuk meg a users-t)
    $maxBytes = 1_500_000;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        // ha valami miatt nem encode-olható, küldjünk minimált
        return json_encode([
            'error' => 'payload_json_encode_failed',
            'org_id' => $payload['org_id'] ?? null,
            'assessment' => $payload['assessment'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }
    if (strlen($json) > $maxBytes && isset($payload['users']) && is_array($payload['users'])) {
        // durva trimmelés: csak a legfontosabb mezők és max 200 user
        $trimmedUsers = [];
        $limit = 200;
        foreach ($payload['users'] as $u) {
            $trimmedUsers[] = [
                'user_id' => $u['user_id'] ?? null,
                'total'   => $u['total']   ?? null,
                'rank'    => $u['rank']    ?? null,
                'percentile' => $u['percentile'] ?? null,
                'self' => $u['self'] ?? null,
                'colleagues' => $u['colleagues'] ?? null,
                'managers' => $u['managers'] ?? null,
                'ceo_rank' => $u['ceo_rank'] ?? null,
            ];
            if (count($trimmedUsers) >= $limit) break;
        }
        $payload['users'] = $trimmedUsers;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    return "PAYLOAD_JSON:\n".$json;
}

/**
 * Minimális validáció: legyenek meg a küszöbök egész számmal.
 * Ha hiányos, térjünk vissza null-lal (→ fallback).
 */
protected function validateAiResponse(array $json): ?array
{
    if (!isset($json['thresholds']) || !is_array($json['thresholds'])) {
        return null;
    }
    $up   = $json['thresholds']['normal_level_up']   ?? null;
    $down = $json['thresholds']['normal_level_down'] ?? null;

    if (!is_numeric($up) || !is_numeric($down)) {
        return null;
    }

    // cast int-re, és clamp 0..100 közé biztos, ami biztos
    $up   = max(0, min(100, (int) $up));
    $down = max(0, min(100, (int) $down));
    $json['thresholds']['normal_level_up']   = $up;
    $json['thresholds']['normal_level_down'] = $down;

    // decisions opcionális – ha van, tisztítsuk a user_id-ket és a decision értéket
    if (isset($json['decisions']) && is_array($json['decisions'])) {
        $clean = [];
        foreach ($json['decisions'] as $d) {
            $uid = isset($d['user_id']) && is_numeric($d['user_id']) ? (int)$d['user_id'] : null;
            $dec = isset($d['decision']) ? strtolower((string)$d['decision']) : null;
            if ($uid !== null && in_array($dec, ['promote','stay','demote'], true)) {
                $clean[] = [
                    'user_id'  => $uid,
                    'decision' => $dec,
                    'why'      => isset($d['why']) ? (string)$d['why'] : '',
                ];
            }
        }
        $json['decisions'] = $clean;
    }

    return $json;
}