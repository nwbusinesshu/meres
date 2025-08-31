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

        // Rövidített items
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

        // Aggregált jellemzők
        $agg = null;
        if (is_array($client)) {
            $itemsCount = (int)($client['items_count'] ?? 0);
            $totalMs    = (int)($client['total_ms'] ?? 0);
            $visibleMs  = (int)($client['visible_ms'] ?? 0);
            $activeMs   = (int)($client['active_ms'] ?? 0);

            $avgMsPerItem = ($itemsCount > 0 && $totalMs > 0) ? (int) round($totalMs / $itemsCount) : null;

            $uniformRatio = null;
            if ($itemsCount > 0 && $items) {
                $vals = array_map(fn($i)=>$i['last_value'], $items);
                $vals = array_values(array_filter($vals, fn($v)=>$v !== null));
                if ($vals) {
                    $counts = array_count_values($vals);
                    rsort($counts);
                    $uniformRatio = round(($counts[0] / max(1, count($vals))), 3);
                }
            }

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

        // meta + __fill_* backfill
        $meta = [
            'org_id'           => $server['org_id'] ?? null,
            'relation_type'    => $server['relation_type'] ?? null,
            'target_id'        => $server['target_id'] ?? null,
            'measurement_uuid' => $server['measurement_uuid'] ?? null,
            'tier'             => $history['tier'] ?? 'cold_start',
            'guidance'         => $history['guidance'] ?? 'be_kind',
        ];

        if ($agg) {
            $meta['__fill_avg_ms_per_item'] = $agg['avg_ms_per_item'] ?? null;
            $meta['__fill_uniform_ratio']   = $agg['uniform_ratio'] ?? null;
            $meta['__fill_zigzag_index']    = $agg['zigzag_index'] ?? null;
            $meta['__fill_fast_pass_rate']  = $agg['fast_pass_rate'] ?? null;
            $meta['__fill_device_type']     = $agg['device_type'] ?? null;
        }

        return [
            'current' => [
                'server_context' => $server,
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

        // Alap prompt (heredoc) – csak statikus szöveg
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
PROMPT;

        // Dinamikus blokkokat külön fűzzük hozzá
        $prompt .= "\n" . self::jsonPretty($compact['history_digest']) . "\n\n";
        $prompt .= "Current submission (aggregates, features, shortened per-item telemetry):\n";
        $prompt .= self::jsonPretty($compact['current']);

        // JSON Schema a kimenetre (strukturált output; strict mode)
// JSON Schema a kimenetre (strukturált output; strict mode)
$jsonSchema = [
    'name'   => 'TelemetryAi',
    'schema' => [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'trust_score' => ['type'=>'integer','minimum'=>0,'maximum'=>20],
            'flags' => [
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
            'rationale' => ['type'=>'string','maxLength'=>800],
            'relation_type' => ['type'=>['string','null']],
            'target_id' => ['type'=>['integer','null']],
            'ai_timestamp' => ['type'=>'string'],
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
                ],
                // Kötelező: MINDEN kulcs legyen felsorolva
                'required' => [
                    'avg_ms_per_item',
                    'uniform_ratio',
                    'entropy',
                    'zigzag_index',
                    'fast_pass_rate',
                    'device_type',
                ],
            ],
        ],
        // Kötelező: a ROOT szinten is legyen felsorolva MINDEN property
        'required' => [
            'trust_score',
            'flags',
            'rationale',
            'relation_type',
            'target_id',
            'ai_timestamp',
            'features_snapshot',
        ],
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
            // ...
            $resp = Http::withHeaders([
                'Authorization'   => 'Bearer '.$apiKey,
                'Content-Type'    => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => $prompt, // Responses API egyszálas input
                'text' => [
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

            // A structured JSON a "output[0].content[0].text" vagy "output[0].content[0].json" alatt érkezhet
            $structured = null;

            // 1) Responses API "output" tömb
            if (isset($data['output']) && is_array($data['output'])) {
                $first = $data['output'][0]['content'][0] ?? null;
                if ($first) {
                    if (isset($first['text'])) {
                        $maybe = json_decode($first['text'], true);
                        if (is_array($maybe)) $structured = $maybe;
                    }
                    if (!$structured && isset($first['json']) && is_array($first['json'])) {
                        $structured = $first['json'];
                    }
                }
            }

            // 2) fallback: choices
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

            // meta standardizálás
            $structured['relation_type'] = $structured['relation_type'] ?? ($meta['relation_type'] ?? null);
            $structured['target_id']     = $structured['target_id']     ?? ($meta['target_id'] ?? null);
            $structured['ai_timestamp']  = $structured['ai_timestamp']  ?? now()->toIso8601String();

            // features_snapshot backfill
            $structured['features_snapshot'] = $structured['features_snapshot'] ?? [];
            foreach (['avg_ms_per_item','uniform_ratio','zigzag_index','fast_pass_rate','device_type'] as $k) {
                if (!array_key_exists($k, $structured['features_snapshot'])) {
                    $structured['features_snapshot'][$k] = $meta['__fill_'.$k] ?? null;
                }
            }

            Log::info('[AI] callOpenAI ok', [
                'trust_score' => $structured['trust_score'] ?? null,
                'flags' => $structured['flags'] ?? null,
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
