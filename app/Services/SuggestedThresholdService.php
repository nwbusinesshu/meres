<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\ThresholdService;
use App\Services\OrgConfigService;

class SuggestedThresholdService
{
    public function __construct(
        protected ThresholdService $thresholds
    ) {}

    /**
     * AI payload felépítése – a UserService::calculateUserPoints()
     * által visszaadott mezőkre támaszkodva.
     */
    public function buildAiPayload(Assessment $assessment, array $scores, array $userStats): array
{
    $orgId = (int) $assessment->organization_id;

    /** @var \App\Services\ThresholdService $T */
    $T   = app(\App\Services\ThresholdService::class);
    $cfg = $T->getOrgConfigMap($orgId);

    // ---- Lokális segédek (closure-ök), hogy a függvény önállóan működjön ----
    $toFloat = static function ($v): float {
        if ($v === null || $v === '') return 0.0;
        return (float) $v;
    };
    $toInt = static function ($v): int {
        if ($v === null || $v === '') return 0;
        return (int) $v;
    };
    $percentile = static function (array $arr, float $p): float {
        // p: 0..100
        if (empty($arr)) return 0.0;
        $p = max(0.0, min(100.0, $p));
        sort($arr, SORT_NUMERIC);
        $n = count($arr);
        if ($n === 1) return (float) $arr[0];
        $rank = ($p / 100.0) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        if ($lo === $hi) return (float) $arr[$lo];
        $w = $rank - $lo;
        return (1.0 - $w) * (float) $arr[$lo] + $w * (float) $arr[$hi];
    };
    $mean = static function (array $arr): float {
        $n = count($arr);
        if ($n === 0) return 0.0;
        return array_sum($arr) / $n;
    };
    $stddev = static function (array $arr) use ($mean): float {
        $n = count($arr);
        if ($n <= 1) return 0.0;
        $avg = $mean($arr);
        $sum = 0.0;
        foreach ($arr as $v) { $d = ((float)$v - $avg); $sum += $d * $d; }
        return sqrt($sum / ($n - 1)); // minta szórás
    };
    $histogram = static function (array $arr, int $bins = 10): array {
        // 0..100 skála feltételezve
        $bins = max(1, $bins);
        $counts = array_fill(0, $bins, 0);
        foreach ($arr as $v) {
            $x = max(0.0, min(100.0, (float)$v));
            $idx = (int) floor($x / (100.0 / $bins));
            if ($idx >= $bins) $idx = $bins - 1;
            $counts[$idx]++;
        }
        $ranges = [];
        $step = 100.0 / $bins;
        for ($i=0; $i<$bins; $i++) {
            $ranges[] = [
                'from'  => (int) round($i * $step),
                'to'    => (int) round(($i+1) * $step - ($i === $bins-1 ? 0 : 1)),
                'count' => $counts[$i],
            ];
        }
        return $ranges;
    };

    // ---- Alap statisztikák a scores tömbből ----
    $cleanScores = array_values(array_map(static fn($v) => (float)$v, $scores));
    $n           = count($cleanScores);

    // FIGYELEM: Ha itt üres, azt a controller már lekezeli hibaüzenettel; itt csak védjük a számolást
    $avg     = $mean($cleanScores);
    $med     = $percentile($cleanScores, 50);
    $p10     = $percentile($cleanScores, 10);
    $p25     = $percentile($cleanScores, 25);
    $p75     = $percentile($cleanScores, 75);
    $p90     = $percentile($cleanScores, 90);
    $sd      = $stddev($cleanScores);
    $minSc   = $n ? (float) min($cleanScores) : 0.0;
    $maxSc   = $n ? (float) max($cleanScores) : 0.0;
    $hist    = $histogram($cleanScores, 10);

    // ---- Korábbi lezárt értékelések küszöbei (history) ugyanabban az orgban ----
    $history = \DB::table('assessments')
        ->where('organization_id', $orgId)
        ->whereNotNull('closed_at')
        ->orderByDesc('closed_at')
        ->limit(4)
        ->get(['id','closed_at','normal_level_up','normal_level_down','monthly_level_down'])
        ->map(static function ($r) {
            return [
                'assessment_id' => (int) $r->id,
                'closed_at'     => (string) $r->closed_at,
                'up'            => (int) $r->normal_level_up,
                'down'          => (int) $r->normal_level_down,
                'mon'           => (int) $r->monthly_level_down,
            ];
        })
        ->toArray();

    // ---- Policy (org configból) – az AI számára lényeges szabályok/korlátok ----
    $policy = [
        'target_promo_rate_max'               => $toFloat($cfg['target_promo_rate_max'] ?? 0.30),
        'target_demotion_rate_max'            => $toFloat($cfg['target_demotion_rate_max'] ?? 0.30),
        'never_below_abs_min_for_promo'       => $toInt($cfg['never_below_abs_min_for_promo'] ?? 0),
        'threshold_min_abs_up'                => $toInt($cfg['threshold_min_abs_up'] ?? 70),
        'no_forced_demotion_if_high_cohesion' => $toInt($cfg['no_forced_demotion_if_high_cohesion'] ?? 1),
    ];

    // ---- User-szintű részletek az AI-nak (opcionális, de hasznos kontextus) ----
    // Csak a legszükségesebbeket küldjük: user_id + total. Ha kell, bővíthető komponensekkel.
    $users = [];
    foreach ($userStats as $userId => $stat) {
        // $stat várhatóan objektum, total, esetleg komponensek mezőkkel (selfTotal, ceoTotal, colleagueTotal, stb.)
        $row = [
            'user_id' => (int) $userId,
            'total'   => isset($stat->total) ? (float)$stat->total : 0.0,
        ];
        // ha vannak komponensek és szeretnéd átadni:
        if (isset($stat->selfTotal))     $row['self']      = (float)$stat->selfTotal;
        if (isset($stat->ceoTotal))      $row['ceo']       = (float)$stat->ceoTotal;
        if (isset($stat->colleagueTotal))$row['colleague'] = (float)$stat->colleagueTotal;
        if (isset($stat->managersTotal)) $row['manager']   = (float)$stat->managersTotal;
        $users[] = $row;
    }

    // ---- Végső payload az AI-nak ----
    return [
        'meta' => [
            'assessment_id' => (int) $assessment->id,
            'org_id'        => $orgId,
            'now'           => now()->toIso8601String(),
        ],
        'stats' => [
            'count'     => $n,
            'avg'       => $avg,
            'median'    => $med,
            'p10'       => $p10,
            'p25'       => $p25,
            'p75'       => $p75,
            'p90'       => $p90,
            'stdev'     => $sd,
            'min'       => $minSc,
            'max'       => $maxSc,
            'histogram' => $hist,
        ],
        'scores'  => $cleanScores,     // aggregált pontok (0..100)
        'users'   => $users,           // user szintű rövid összefoglaló
        'policy'  => $policy,          // szervezeti korlátok/szabályok
        'history_thresholds' => $history, // előző lezárások küszöbei (kontextus)
    ];
}

    /**
     * Tényleges OpenAI hívás (STRICT JSON kérés).
     * Hiba vagy érvénytelen válasz esetén: null.
     */
    public function callAiForSuggested(array $payload): ?array
    {
        $apiKey  = env('OPENAI_API_KEY');
        $model   = env('OPENAI_MODEL', 'gpt-4.1-mini');
        $timeout = (int) env('OPENAI_TIMEOUT', 30);

        if (!$apiKey) {
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
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                ],
            ]);

            $data    = json_decode((string) $resp->getBody(), true);
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                return null;
            }

            $json = json_decode($content, true);
            if (!is_array($json)) {
                return null;
            }

            return $this->validateAiResponse($json);
        } catch (GuzzleException $e) {
            \Log::warning('AI call failed (suggested thresholds): ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            \Log::warning('AI call decode/validate error: ' . $e->getMessage());
            return null;
        }
    }

    /* =========================
       HELPER FÜGGVÉNYEK
       ========================= */

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

    protected function aiUserPromptFromPayload(array $payload): string
    {
        $maxBytes = 1_500_000;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return json_encode([
                'error' => 'payload_json_encode_failed',
                'org_id' => $payload['org_id'] ?? null,
                'assessment' => $payload['assessment'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        }
        if (strlen($json) > $maxBytes && isset($payload['users']) && is_array($payload['users'])) {
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
     * Ha hiányos, térjünk vissza null-lal.
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

        $up   = max(0, min(100, (int) $up));
        $down = max(0, min(100, (int) $down));
        $json['thresholds']['normal_level_up']   = $up;
        $json['thresholds']['normal_level_down'] = $down;

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

        $zCut = 2.5;
        $zIds = [];
        if ($stdev > 0) {
            $totalsOriginalOrder = array_column($rows, 'total');
            foreach ($totalsOriginalOrder as $i => $t) {
                $z = ($t - $mean) / $stdev;
                if (abs($z) >= $zCut) {
                    $zIds[] = (int)$rows[$i]['user_id'];
                }
            }
        }

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
        // ThresholdService helyett az OrgConfigService adja a map-et
        $cfg = $this->thresholds->getOrgConfigMap($orgId);

        return [
            'target_promo_rate_max'               => isset($cfg['target_promo_rate_max']) ? (float)$cfg['target_promo_rate_max'] : 0.20,
            'target_demotion_rate_max'            => isset($cfg['target_demotion_rate_max']) ? (float)$cfg['target_demotion_rate_max'] : 0.10,
            'min_gap_for_stagnation'              => isset($cfg['threshold_gap_min']) ? (int)$cfg['threshold_gap_min'] : 2,
            'never_below_abs_min_for_promo'       => isset($cfg['never_below_abs_min_for_promo']) ? (int)$cfg['never_below_abs_min_for_promo'] : null,
            'use_telemetry_trust'                 => isset($cfg['use_telemetry_trust']) ? (bool)$cfg['use_telemetry_trust'] : true,
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

    /** Median Absolute Deviation (MAD) */
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
