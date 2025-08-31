<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserRelationType;
use App\Models\Enums\UserType;
use App\Models\User;

/**
 * A beküldéskori telemetria szerver-oldali összerakása (raw + digest + feature-ök).
 * Később ide kerülhet az AI-értékelés (telemetry_ai) is.
 */
class TelemetryService
{
    /**
     * @param array|null $clientTelemetry  A kliens JS által küldött telemetry_raw (vagy null, ha nincs/hibás)
     * @param object     $assessment       AssessmentService::getCurrentAssessment() visszatérési értéke (App\Models\Assessment)
     * @param User       $user             A rater (aki értékel)
     * @param User       $target           A cél (akit értékelnek)
     * @param Collection $questions        A target kérdései (CompetencyQuestion-ok gyűjteménye)
     * @param array      $answers          A request->input('answers', []) tömb
     * @return array                      A mentendő telemetry_raw (JSON-olható tömb)
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

        // 2) Feature-ök (gyors jelzők)
        $features = self::deriveFeatures($questions, $answers, $clientTelemetry);

        // 3) History digest (korábbi AI-kimenetekből, azonos org, ugyanettől a usertől)
        $historyDigest = self::buildHistoryDigest($assessment->organization_id, $user->id, $target->id);

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
            'client'          => $clientBlock,     // kliens mért adatai (ha nagy/hibás: rövid jelzés)
            'server_context'  => $serverContext,   // szerver oldalról ismert tények
            'features'        => $features,        // gyors mintajelzők
            'history_digest'  => $historyDigest,   // korábbi AI-eredmények kivonata (max 20)
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
        $vals = array_map(static fn($a) => (int)($a['value'] ?? 0), $answers);
        $allSame = count($vals) ? (count(array_unique($vals)) === 1) : false;

        // szélsőértékek csak akkor, ha minden válasz min vagy max a SAJÁT kérdésén
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

        $countMismatch = isset($clientTelemetry['items_count'])
            ? ((int)$clientTelemetry['items_count'] !== (int)$questions->count())
            : false;

        // dinamikus "gyorsaság" küszöb
        $thresholdMs = max(8000, $questions->count() * 800);
        $tooFast = isset($clientTelemetry['total_ms'])
            ? ((int)$clientTelemetry['total_ms'] < $thresholdMs)
            : false;

        return [
            'all_same_value' => $allSame,
            'extremes_only'  => $extremesOnly,
            'count_mismatch' => $countMismatch,
            'too_fast_total' => $tooFast,
        ];
    }

    /**
     * History-digest építése a user korábbi AI-értékeléseiből (azonos szervezet).
     * Visszaad: max 20 rekordból sűrített, a promptoláshoz alkalmas összefoglaló.
     */
    protected static function buildHistoryDigest(int $organizationId, int $userId, int $currentTargetId): array
    {
        // user_competency_submit + assessment (org szűrés), csak ahol VAN telemetry_ai
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
            $entries[] = [
                'trust_score'       => $ai['trust_score'] ?? null,
                'flags'             => array_values(array_filter($ai['flags'] ?? [], fn($f)=>is_string($f))),
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
            $relCount[$e['relation_type'] ?? 'unknown'] = ($relCount[$e['relation_type'] ?? 'unknown'] ?? 0) + 1;
            if ($e['device_type'] !== null) {
                $devCount[$e['device_type']] = ($devCount[$e['device_type']] ?? 0) + 1;
            }
            if (is_numeric($e['trust_score'])) $trust[] = $e['trust_score'];
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
                    $vals[] = $e['features_snapshot'][$key];
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
        $row = DB::table('user_competency_submit')
            ->where('assessment_id', $assessmentId)
            ->where('user_id', $userId)
            ->where('target_id', $targetId)
            ->first(['telemetry_raw', 'submitted_at']);

        if (!$row || !$row->telemetry_raw) {
            return null;
        }

        $telemetryRaw = json_decode($row->telemetry_raw, true);
        if (!is_array($telemetryRaw)) {
            return null;
        }

        // 1) Kompakt AI payload építése
        $compact = self::buildCompactPayloadForAI($telemetryRaw);

        // 2) Prompt felépítése + JSON schema a kimenetre
        [$prompt, $jsonSchema] = self::buildPromptAndSchema($compact);

        // 3) OpenAI hívás
        $ai = self::callOpenAI($prompt, $jsonSchema, $compact['meta']);

        if ($ai) {
            // 4) Visszaírás a DB-be
            DB::table('user_competency_submit')
                ->where('assessment_id', $assessmentId)
                ->where('user_id', $userId)
                ->where('target_id', $targetId)
                ->update([
                    'telemetry_ai' => json_encode($ai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }

        return $ai;
    }

    /**
     * A telemetry_raw-ból épít egy könnyű, AI-barát inputot:
     *  - rövidített client.items (csak a fontos mezők),
     *  - server_context, features, history_digest változatlanul,
     *  - meta (org, relation, items_count, model guidance).
     */
    protected static function buildCompactPayloadForAI(array $raw): array
    {
        $client = $raw['client'] ?? null;
        $server = $raw['server_context'] ?? [];
        $features = $raw['features'] ?? [];
        $history = $raw['history_digest'] ?? null;

        // Rövidített items: csak a lényeges mezők; value_path nem kell végig
        $items = [];
        if (is_array($client) && isset($client['items']) && is_array($client['items'])) {
            foreach ($client['items'] as $it) {
                $items[] = [
                    'question_id'          => $it['question_id'] ?? null,
                    'scale'                => $it['scale'] ?? null,
                    'first_seen_ms'        => $it['first_seen_ms'] ?? null,
                    'first_interaction_ms' => $it['first_interaction_ms'] ?? null,
                    'last_value'           => $it['last_value'] ?? null,
                    'changes_count'        => $it['changes_count'] ?? 0,
                    'focus_ms'             => $it['focus_ms'] ?? null,
                ];
            }
        }

        // Aggregált, könnyen számolható jellemzők (ha van client)
        $agg = null;
        if (is_array($client)) {
            $itemsCount = (int)($client['items_count'] ?? 0);
            $totalMs    = (int)($client['total_ms'] ?? 0);
            $visibleMs  = (int)($client['visible_ms'] ?? 0);
            $activeMs   = (int)($client['active_ms'] ?? 0);

            // egyszerű metrikák
            $avgMsPerItem = ($itemsCount > 0 && $totalMs > 0) ? (int) round($totalMs / $itemsCount) : null;

            // uniform_ratio: leggyakoribb last_value aránya
            $uniformRatio = null;
            if ($itemsCount > 0 && $items) {
                $vals = array_map(fn($i)=>$i['last_value'], $items);
                $vals = array_filter($vals, fn($v)=>$v !== null);
                if ($vals) {
                    $counts = array_count_values($vals);
                    rsort($counts);
                    $uniformRatio = round(($counts[0] / max(1, count($vals))), 3);
                }
            }

            // fast_pass_rate: 1.5s alatt történt első interakció aránya
            $fastPassRate = null;
            if ($itemsCount > 0 && $items) {
                $fast = 0; $total=0;
                foreach ($items as $i) {
                    if ($i['first_interaction_ms'] !== null) {
                        $total++;
                        if ($i['first_interaction_ms'] <= 1500) $fast++;
                    }
                }
                if ($total > 0) $fastPassRate = round($fast/$total, 3);
            }

            // durva zigzag_index: egymást követő kérdések last_value különbségeinek előjelváltási aránya
            $zigzagIndex = null;
            if (count($items) >= 3) {
                $vals = array_map(fn($i)=>$i['last_value'], $items);
                $vals = array_values(array_filter($vals, fn($v)=>$v !== null));
                if (count($vals) >= 3) {
                    $dirs=[]; for($k=1;$k<count($vals);$k++){ $dirs[] = $vals[$k] <=> $vals[$k-1]; }
                    $changes=0; for($k=1;$k<count($dirs);$k++){ if ($dirs[$k] !== 0 && $dirs[$k-1] !== 0 && $dirs[$k] !== $dirs[$k-1]) $changes++; }
                    $zigzagIndex = round($changes / max(1, count($dirs)-1), 3);
                }
            }

            $agg = [
                'items_count'      => $itemsCount,
                'total_ms'         => $totalMs,
                'visible_ms'       => $visibleMs,
                'active_ms'        => $activeMs,
                'avg_ms_per_item'  => $avgMsPerItem,
                'uniform_ratio'    => $uniformRatio,
                'fast_pass_rate'   => $fastPassRate,
                'zigzag_index'     => $zigzagIndex,
                'device_type'      => $client['device']['type'] ?? null,
            ];
        }

        return [
            'current' => [
                'server_context' => $server,
                'features'       => $features,
                'agg'            => $agg,
                'items'          => $items,   // rövidített
            ],
            'history_digest' => $history,
            'meta' => [
                'org_id'        => $server['org_id'] ?? null,
                'relation_type' => $server['relation_type'] ?? null,
                'target_id'     => $server['target_id'] ?? null,
                'measurement_uuid' => $server['measurement_uuid'] ?? null,
                'tier'          => $history['tier'] ?? 'cold_start',
                'guidance'      => $history['guidance'] ?? 'be_kind',
            ],
        ];
    }

    /**
     * Prompt + JSON Schema felépítése.
     * Responses API-hoz "json_schema" response_format-tal kérjük a kimenetet.
     */
    protected static function buildPromptAndSchema(array $compact): array
    {
        $meta = $compact['meta'];
        $guidance = $meta['guidance'] ?? 'be_kind';

        // SYSTEM / USER üzenetet egyetlen "input" szövegbe illesztjük (Responses API)
        $prompt = <<<PROMPT
You are scoring the reliability of a single 360° assessment submission.

Rules:
- Output STRICT JSON that conforms to the provided JSON Schema. No extra text.
- Base your decision ONLY on the provided telemetry and history.
- Calibrate severity by the guidance level:
  - "be_kind": favor higher trust unless multiple strong risk signals appear.
  - "balanced": weigh signals evenly.
  - "strict": penalize suspicious signals more strongly.

Target trust score scale: 0–20 (integer). 0 = unusable / likely bad-faith, 20 = highly reliable.

Context:
- Organization ID: {$meta['org_id']}
- Relation type: {$meta['relation_type']}
- Target user ID: {$meta['target_id']}
- Guidance: {$guidance}

History digest (summarized, up to 20 past AI-scored submissions):
{$this->jsonPretty($compact['history_digest'])}

Current submission (aggregates, features, shortened per-item telemetry):
{$this->jsonPretty($compact['current'])}
PROMPT;

        // JSON Schema a kimenetre (strukturált output; strict mode)
        $jsonSchema = [
            'name'   => 'TelemetryAi',
            'schema' => [
                'type'       => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'trust_score' => ['type'=>'integer','minimum'=>0,'maximum'=>20],
                    'flags'       => [
                        'type'=>'array',
                        'items'=>[
                            'type'=>'string',
                            'enum'=>[
                                'too_fast','too_uniform','extremes_only','count_mismatch',
                                'low_variability','low_focus','low_visibility','incomplete_scroll',
                                'suspicious_pattern','global_severity','global_leniency',
                                'suspicious_target_bias','low_confidence'
                            ]
                        ]
                    ],
                    'rationale'   => ['type'=>'string','maxLength'=>800],
                    'relation_type' => ['type'=>['string','null']],
                    'target_id'     => ['type'=>['integer','null']],
                    'ai_timestamp'  => ['type'=>'string'],
                    'features_snapshot' => [
                        'type'=>'object',
                        'additionalProperties'=>false,
                        'properties'=>[
                            'avg_ms_per_item' => ['type'=>['number','null']],
                            'uniform_ratio'   => ['type'=>['number','null']],
                            'entropy'         => ['type'=>['number','null']],
                            'zigzag_index'    => ['type'=>['number','null']],
                            'fast_pass_rate'  => ['type'=>['number','null']],
                            'device_type'     => ['type'=>['string','null']],
                        ]
                    ],
                ],
                'required' => ['trust_score','flags','rationale','relation_type','ai_timestamp','features_snapshot']
            ],
            'strict' => true
        ];

        return [$prompt, $jsonSchema];
    }

    /**
     * OpenAI Responses API hívása strukturált (json_schema) válasszal.
     * Laravel HTTP klienssel hívunk. response_format: json_schema (strict).
     */
    protected static function callOpenAI(string $prompt, array $jsonSchema, array $meta): ?array
    {
        $apiKey = (string) config('services.openai.key', env('OPENAI_API_KEY'));
        $model  = (string) env('OPENAI_MODEL', 'gpt-4.1-mini');
        $timeout = (int) env('OPENAI_TIMEOUT', 12);

        if (!$apiKey) {
            return null;
        }

        $idempotencyKey = 'telemetry:' . ($meta['measurement_uuid'] ?? Str::uuid()->toString());

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type'  => 'application/json',
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    // Responses API — egyszerű input szöveg
                    'input' => $prompt,
                    // Strukturált JSON kimenet
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => $jsonSchema,
                    ],
                ]);

            if (!$resp->ok()) {
                // ide tehetsz logolást, ha szükséges
                return null;
            }

            $data = $resp->json();

            // A structured JSON a "output[0].content[0].text" vagy "output[0].content[0].json" alatt érkezhet
            // Responses API változhat — a hivatalos doksi szerint structured outputot ad. :contentReference[oaicite:1]{index=1}
            $structured = null;

            // 1) új Responses API "output" tömb (általános minta)
            if (isset($data['output']) && is_array($data['output'])) {
                $first = $data['output'][0]['content'][0] ?? null;
                if ($first) {
                    // próbáljuk json-ként olvasni
                    if (isset($first['text'])) {
                        $maybe = json_decode($first['text'], true);
                        if (is_array($maybe)) $structured = $maybe;
                    }
                    if (!$structured && isset($first['json'])) {
                        $structured = $first['json'];
                    }
                }
            }

            // 2) fallback: egyes válaszoknál "response" / "choices" struktúra is előfordulhat (átmeneti kompat)
            if (!$structured && isset($data['choices'][0]['message']['content'])) {
                $maybe = json_decode($data['choices'][0]['message']['content'], true);
                if (is_array($maybe)) $structured = $maybe;
            }

            if (!is_array($structured)) {
                return null;
            }

            // standardizáljuk a plusz meta mezőket
            $structured['relation_type'] = $structured['relation_type'] ?? ($meta['relation_type'] ?? null);
            $structured['target_id']     = $structured['target_id']     ?? ($meta['target_id'] ?? null);
            $structured['ai_timestamp']  = $structured['ai_timestamp']  ?? now()->toIso8601String();

            // ha a features_snapshot-ban hiányzik, töltsük fel a kompaktban lévő aggregált értékekkel
            $structured['features_snapshot'] = $structured['features_snapshot'] ?? [];
            foreach (['avg_ms_per_item','uniform_ratio','zigzag_index','fast_pass_rate','device_type'] as $k) {
                if (!array_key_exists($k, $structured['features_snapshot'])) {
                    $structured['features_snapshot'][$k] = $compactAgg = $meta['__fill_'.$k] ?? null;
                }
            }

            return $structured;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Kis segéd a promptban szépített JSON-hoz (nem kötelező). */
    protected function jsonPretty($v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
