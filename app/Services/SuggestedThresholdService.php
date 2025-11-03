<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SuggestedThresholdService
{
    public function __construct(
        protected ThresholdService $thresholds,
        protected TelemetryService $telemetry
    ) {}

    /**
     * AI payload – Hybrid telemetria:
     *  - scores (0..100), users (csak pontok + komponensek, telemetria NINCS user-szinten)
     *  - telemetry_org: org-szintű telemetria összefoglaló az aktuális assessmenthez
     *  - history: utolsó 3 lezárt assessment statjai és küszöbei
     *  - policy: csak SUGGESTED-re releváns beállítások
     */
    public function buildAiPayload(Assessment $assessment, array $scores, array $userStats): array
    {
        $orgId = (int) $assessment->organization_id;
        $cfg   = $this->thresholds->getOrgConfigMap($orgId);

        // ---- segédek
        $toFloat = static fn($v) => ($v === null || $v === '') ? 0.0 : (float)$v;
        $toInt   = static fn($v) => ($v === null || $v === '') ? 0   : (int)$v;

        // ---- aktuális eloszlás a scores alapján
        $cleanScores = array_values(array_map(static fn($v) => (float)$v, $scores));
        sort($cleanScores, SORT_NUMERIC);
        $statsNow = $this->computeStatsFromScores($cleanScores);

        // ---- org-szintű telemetria összefoglaló (HYBRID stratégia)
        $telemetryOrg = $this->telemetry->aggregateOrgTelemetry((int)$assessment->id, $orgId);

        // ---- history (utolsó 3 lezárt) ugyanebben az orgban, újraszámolva a jelen motorral
        $history = $this->fetchHistoryStats($orgId, 3, $assessment->id);

        // ---- policy (SUGGESTED-hez releváns mezők)
        // NOTE: never_below_abs_min_for_promo itt konkrét pontszám vagy null!
        $policy = [
            'target_promo_rate_max'               => $toFloat($cfg['target_promo_rate_max'] ?? 0.30),
            'target_demotion_rate_max'            => $toFloat($cfg['target_demotion_rate_max'] ?? 0.30),
            'never_below_abs_min_for_promo'       => (isset($cfg['never_below_abs_min_for_promo']) && $cfg['never_below_abs_min_for_promo'] !== '')
                ? $toInt($cfg['never_below_abs_min_for_promo'])
                : null,
            'no_forced_demotion_if_high_cohesion' => $toInt($cfg['no_forced_demotion_if_high_cohesion'] ?? 1),
        ];

        // ---- users rövid lista (csak pontok + komponensek, telemetria nélkül)
        $users = [];
        foreach ($userStats as $userId => $stat) {
            $row = [
                'user_id' => (int) $userId,
                'total'   => isset($stat->total) ? (float)$stat->total : 0.0,
            ];
            if (isset($stat->selfTotal))       $row['self']      = (float)$stat->selfTotal;
            if (isset($stat->ceoTotal))        $row['ceo']       = (float)$stat->ceoTotal;
            if (isset($stat->colleagueTotal))  $row['colleague'] = (float)$stat->colleagueTotal;
            if (isset($stat->managersTotal))   $row['manager']   = (float)$stat->managersTotal;
            $users[] = $row;
        }

        // ---- végső payload
        return [
            'meta' => [
                'assessment_id' => (int) $assessment->id,
                'org_id'        => $orgId,
                'now'           => now()->toIso8601String(),
                'method'        => 'suggested',
            ],
            'stats'         => $statsNow,
            'scores'        => $cleanScores,
            'users'         => $users,           // komponensek OK, telemetria NINCS user-szinten
            'telemetry_org' => $telemetryOrg,    // org-level telemetria összkép
            'policy'        => $policy,
            'history'       => $history,
        ];
    }

     /** OpenAI hívás (STRICT JSON) - IMPROVED with better error handling
     * Returns null on error and logs details.
     * 
     * @param array $payload
     * @return array|null
     */
    public function callAiForSuggested(array $payload): ?array
    {
        $apiKey  = config('services.openai.key');
        $model   = config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 30);

        // ✅ NEW: Better validation
        if (!$apiKey) {
            \Log::error('AI call aborted: OPENAI_API_KEY not configured');
            throw new \RuntimeException(__('assessment.ai-key-missing'));
        }

        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout'  => $timeout,
            'connect_timeout' => 5, // ✅ NEW: Connection timeout
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
                    'model'           => $model,
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                ],
            ]);

            $data       = json_decode((string) $resp->getBody(), true);
            $rawContent = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$rawContent) {
                \Log::error('AI response empty', ['data' => $data]);
                return null;
            }

            $json = json_decode($rawContent, true);
            if (!is_array($json)) {
                \Log::error('AI response not valid JSON', ['raw' => $rawContent]);
                return null;
            }

            $validated = $this->validateAiResponse($json);
            if (!$validated) {
                \Log::error('AI response validation failed', ['json' => $json]);
                return null;
            }

            // --- LOG: assessment.suggested_decision (append, max 5 elem) ---
            try {
                $assessmentId = (int)($payload['meta']['assessment_id'] ?? 0);
                if ($assessmentId > 0) {
                    $record = [
                        'created_at' => now()->toIso8601String(),
                        'model'      => $model,
                        'request'    => [
                            'system'   => $systemPrompt,
                            'user'     => $userPrompt,
                            'payload'  => $payload,
                        ],
                        'response'   => [
                            'raw'       => $rawContent,
                            'validated' => $validated,
                        ],
                    ];
                    $prev = DB::table('assessment')->where('id', $assessmentId)->value('suggested_decision');
                    if ($prev) {
                        $prevArr = json_decode($prev, true);
                        if (is_array($prevArr)) {
                            // ha régi formátum 1 objektum: listává alakítjuk
                            if (isset($prevArr['request']) && isset($prevArr['response'])) {
                                $prevArr = [$prevArr];
                            }
                            $prevArr[] = $record;
                            // limit 5 elemre
                            $prevArr = array_slice($prevArr, -5);
                            DB::table('assessment')->where('id', $assessmentId)->update([
                                'suggested_decision' => json_encode($prevArr, JSON_UNESCAPED_UNICODE),
                            ]);
                        } else {
                            DB::table('assessment')->where('id', $assessmentId)->update([
                                'suggested_decision' => json_encode([$record], JSON_UNESCAPED_UNICODE),
                            ]);
                        }
                    } else {
                        DB::table('assessment')->where('id', $assessmentId)->update([
                            'suggested_decision' => json_encode([$record], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not persist suggested_decision log: '.$e->getMessage());
            }

            return $validated;

        // ✅ NEW: Better exception handling with specific error types
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \Log::error('AI API connection failed', [
                'error' => $e->getMessage(),
                'url' => 'https://api.openai.com/v1/chat/completions',
            ]);
            throw new \RuntimeException(__('assessment.ai-connection-failed'), 0, $e);

            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = $e->hasResponse() ? (string)$e->getResponse()->getBody() : '';
            
            \Log::error('AI API request failed', [
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response' => $responseBody,
            ]);
            
            if ($statusCode === 401) {
                tthrow new \RuntimeException(__('assessment.ai-auth-failed'), 0, $e);
            } elseif ($statusCode === 429) {
                throw new \RuntimeException(__('assessment.ai-rate-limit'), 0, $e);
            } elseif ($statusCode >= 500) {
                throw new \RuntimeException(__('assessment.ai-server-error'), 0, $e);
            } else {
                throw new \RuntimeException(__('assessment.ai-http-error', ['status' => $statusCode, 'message' => $e->getMessage()]), 0, $e);
            }
            
        } catch (GuzzleException $e) {
            \Log::error('AI call failed (suggested thresholds)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException(__('assessment.ai-call-failed', ['message' => $e->getMessage()]), 0, $e);
        }
    }

    /* =========================
       HELPER FÜGGVÉNYEK
       ========================= */

    /**
     * System prompt – egyértelmű, kódoddal egyező döntési szabály:
     *  promote if score >= up; demote if score < down; else stay.
     */
    protected function aiSystemPrompt(): string
    {
        return <<<SYS
You are a deterministic thresholds engine for a performance review cycle.

INPUT: A strict JSON payload with:
- current team scores (0..100),
- optional per-user components (self/colleagues/managers/ceo),
- ORGANIZATION-LEVEL telemetry summary (reliability of the whole measurement),
- team statistics (mean/median/stdev/percentiles, histogram),
- a short history of previous cycles with basic stats and thresholds,
- and policy caps and constraints.

TASK:
1) Propose integer thresholds:
   - normal_level_up (promotion)
   - normal_level_down (demotion)
2) Respect policy:
   - Keep promotion rate around policy.target_promo_rate_max; demotion around policy.target_demotion_rate_max.
   - If policy.never_below_abs_min_for_promo is a number, do not set 'normal_level_up' below that value.
   - If policy.no_forced_demotion_if_high_cohesion is true and team cohesion is high (e.g., low stdev/CV with high mean), it's acceptable to reduce demotions (raise 'down').
3) Use history to avoid unjustified drastic oscillations vs. previous cycles.
4) Treat telemetry as reliability hints at the organization level; DO NOT overfit to outliers.
5) Use this exact decision rule: promote if score >= normal_level_up; demote if score < normal_level_down; otherwise stay.
6) Output STRICT JSON only:

{
  "thresholds": {
    "normal_level_up": <int>,
    "normal_level_down": <int>,
    "rationale": "<short>"
  },
  "decisions": [
    { "user_id": <int>, "decision": "promote|stay|demote", "why": "<short>" }
  ],
  "summary_hu": "<Write a short (200-300 character) paragraph strictly in HUNGARIAN about your decision to let them know why those thresholds were selected. Keep it simple, pragmatic and user friendly. Do not leave room for any questions, be clear and sure. You want them to accept your thresholds.>" }
7) Your thresholds MUST satisfy:
- promotions_count / N <= policy.target_promo_rate_max
- demotions_count  / N <= policy.target_demotion_rate_max
If necessary, increase 'normal_level_up' (even above the policy minimum) and/or increase 'normal_level_down' to respect these caps.
Define high cohesion strictly as (stdev <= 8 and mean >= 80) or (CV <= 0.10). Otherwise, do not treat the team as high cohesion.
Return also:
"rates": { "promotion_rate": <float>, "promotion_count": <int>, "demotion_rate": <float>, "demotion_count": <int>, "n": <int> }.
}
8) If possible, do not create thresholds with exactly the same points as any user's total point to lighten feelings of losing and users being on thresholds as it is a "grey zone".  
SYS;
    }

    /**
     * Payload beágyazása (méretlimittel). Ha nagy, a users mezőt trimmeljük 200 főre.
     */
    protected function aiUserPromptFromPayload(array $payload): string
    {
        $maxBytes = 1_500_000;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return json_encode([
                'error'  => 'payload_json_encode_failed',
                'meta'   => $payload['meta']   ?? null,
                'policy' => $payload['policy'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        }

        if (strlen($json) > $maxBytes && isset($payload['users']) && is_array($payload['users'])) {
            $trimmed = [];
            $limit = 200;
            foreach ($payload['users'] as $u) {
                $trimmed[] = [
                    'user_id'   => $u['user_id'] ?? null,
                    'total'     => $u['total']   ?? null,
                    'self'      => $u['self']    ?? null,
                    'colleague' => $u['colleague'] ?? null,
                    'manager'   => $u['manager'] ?? null,
                    'ceo'       => $u['ceo']     ?? null,
                ];
                if (count($trimmed) >= $limit) break;
            }
            $payload['users'] = $trimmed;
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return "PAYLOAD_JSON:\n".$json;
    }

    /**
     * Minimális válasz-validáció és tisztítás.
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

        $up   = max(0, min(100, (int)$up));
        $down = max(0, min(100, (int)$down));
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

    /**
     * Histórikus statok és küszöbök: utolsó $limit lezárt assessment ugyanabban az orgban.
     * A résztvevők pontjait a jelenlegi motorral számoljuk újra.
     */
    protected function fetchHistoryStats(int $orgId, int $limit = 3, ?int $excludeAssessmentId = null): array
    {
        $q = DB::table('assessment') // NÁLATOK SINGULAR a tábla!
            ->where('organization_id', $orgId)
            ->whereNotNull('closed_at');

        if ($excludeAssessmentId) {
            $q->where('id', '!=', $excludeAssessmentId);
        }

        $past = $q->orderByDesc('closed_at')
                  ->limit($limit)
                  ->get(['id','closed_at','threshold_method','normal_level_up','normal_level_down']);

        if ($past->isEmpty()) return [];

        // résztvevők (org-scope, aktív, nem admin)
        $userIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $participants = User::query()
            ->whereIn('id', $userIds)
            ->whereNull('removed_at')
            ->where(function ($q2) {
                $q2->whereNull('type')->orWhere('type', '!=', 'admin');
            })
            ->get();

        $out = [];
        foreach ($past as $a) {
            $scores = [];
            $ass = Assessment::find($a->id);
            if (!$ass) continue;

            foreach ($participants as $u) {
                $stat = \App\Services\UserService::calculateUserPoints($ass, $u);
                if ($stat !== null) {
                    $scores[] = (float)$stat->total;
                }
            }

            sort($scores, SORT_NUMERIC);
            $stats = $this->computeStatsFromScores($scores);

            $out[] = [
                'assessment_id' => (int)$a->id,
                'closed_at'     => (string)$a->closed_at,
                'method'        => (string)($a->threshold_method ?? ''),
                'thresholds'    => [
                    'up'   => (int)$a->normal_level_up,
                    'down' => (int)$a->normal_level_down,
                ],
                'stats'         => $stats,
            ];
        }

        return $out;
    }

    /**
     * Statisztikák számítása 0..100 pontok tömbjéből (növekvő sor).
     */
    protected function computeStatsFromScores(array $scores): array
    {
        $n = count($scores);

        $mean = function(array $xs): float {
            $m = count($xs);
            return $m ? array_sum($xs)/$m : 0.0;
        };
        $percentile = function(array $arr, float $p): float {
            if (empty($arr)) return 0.0;
            $p = max(0.0, min(100.0, $p));
            $m = count($arr);
            if ($m === 1) return (float)$arr[0];
            $rank = ($p / 100.0) * ($m - 1);
            $lo = (int) floor($rank);
            $hi = (int) ceil($rank);
            if ($lo === $hi) return (float) $arr[$lo];
            $w = $rank - $lo;
            return (1.0 - $w) * (float) $arr[$lo] + $w * (float) $arr[$hi];
        };
        $stddev = function(array $arr) use ($mean): float {
            $m = count($arr);
            if ($m <= 1) return 0.0;
            $avg = $mean($arr);
            $sum = 0.0;
            foreach ($arr as $v) { $d = ((float)$v - $avg); $sum += $d * $d; }
            return sqrt($sum / ($m - 1));
        };
        $histogram = function (array $arr, int $bins = 10): array {
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

        $avg   = $mean($scores);
        $med   = $percentile($scores, 50);
        $p10   = $percentile($scores, 10);
        $p25   = $percentile($scores, 25);
        $p75   = $percentile($scores, 75);
        $p90   = $percentile($scores, 90);
        $sd    = $stddev($scores);
        $minSc = $n ? (float) min($scores) : 0.0;
        $maxSc = $n ? (float) max($scores) : 0.0;
        $hist  = $histogram($scores, 10);

        return [
            'count'     => $n,
            'avg'       => round($avg, 2),
            'median'    => round($med, 2),
            'p10'       => round($p10, 2),
            'p25'       => round($p25, 2),
            'p75'       => round($p75, 2),
            'p90'       => round($p90, 2),
            'stdev'     => round($sd, 2),
            'min'       => round($minSc, 2),
            'max'       => round($maxSc, 2),
            'histogram' => $hist,
        ];
    }
}
