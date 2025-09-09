<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ThresholdService
{
    /**
     * Visszaadja a szervezet összes releváns configját egy assoc tömbként.
     * pl.: ['threshold_mode' => 'fixed', 'normal_level_up' => '85', ...]
     */
    public function getOrgConfigMap(int $orgId): array
    {
        $rows = DB::table('organization_config')
            ->where('organization_id', $orgId)
            ->pluck('value', 'name');

        // Biztonságos alapértékek (visszafelé kompatibilitás a config táblával)
        $defaults = [
            // Klasszikus
            'threshold_mode'      => 'fixed',
            'normal_level_up'     => '85',
            'normal_level_down'   => '70',
            'monthly_level_down'  => '70',

            // HYBRID/DYNAMIC alapok
            'threshold_min_abs_up'=> '70',
            'threshold_top_pct'   => '15',
            'threshold_bottom_pct'=> '20',

            // HYBRID finomhangolás
            'threshold_grace_points' => '0',
            'threshold_gap_min'      => '5',

            // AI / telemetria toggle
            'strict_anonymous_mode' => '0',
            'ai_telemetry_enabled'  => '1',

            // SUGGESTED policy (0..1 float, de itt stringben tárolódik → majd floatval-lal használod)
            'target_promo_rate_max'         => '0.30',
            'target_demotion_rate_max'      => '0.30',
            'never_below_abs_min_for_promo' => null,
            'use_telemetry_trust'           => '1',
            'no_forced_demotion_if_high_cohesion' => '1',
        ];

        // Merge: org config felülírja a defaultokat
        return array_merge($defaults, $rows->toArray());
    }

    // ---------- FIXED ----------
    public function thresholdsForFixed(array $cfg): array
    {
        foreach (['normal_level_up','normal_level_down','monthly_level_down'] as $k) {
            if (!isset($cfg[$k]) || $cfg[$k] === '' ) {
                throw ValidationException::withMessages([
                    'config' => "FIXED: hiányzik a(z) {$k} beállítás.",
                ]);
            }
        }
        $up   = (int)$cfg['normal_level_up'];
        $down = (int)$cfg['normal_level_down'];
        $mon  = (int)$cfg['monthly_level_down'];

        if (!($up > $down)) {
            throw ValidationException::withMessages([
                'config' => 'FIXED: normal_level_up <= normal_level_down.',
            ]);
        }

        return [
            'normal_level_up'    => $this->clamp($up),
            'normal_level_down'  => $this->clamp($down),
            'monthly_level_down' => $this->clamp($mon),
        ];
    }

    // ---------- HYBRID ----------
    public function thresholdsForHybrid(array $cfg, array $scores): array
    {
        foreach (['threshold_min_abs_up','threshold_top_pct','threshold_gap_min','monthly_level_down'] as $k) {
            if (!isset($cfg[$k]) || $cfg[$k] === '') {
                throw ValidationException::withMessages([
                    'config' => "HYBRID: hiányzik a(z) {$k} beállítás.",
                ]);
            }
        }

        $down    = (int)$cfg['threshold_min_abs_up'];           // fix alsó ponthatár
        $topPct  = (float)$cfg['threshold_top_pct'];            // %
        $gapMin  = (int)$cfg['threshold_gap_min'];              // pont
        $mon     = (int)$cfg['monthly_level_down'];

        if ($topPct <= 0.0 || $topPct >= 100.0) {
            throw ValidationException::withMessages([
                'config' => 'HYBRID: threshold_top_pct érvénytelen.',
            ]);
        }

        $upRaw = $this->topPercentileScore($scores, $topPct);   // relatív top X% határ
        // GAP alkalmazása: ha upRaw túl alacsony, toljuk fel minimum down + gapMin-ig
        $up = max($upRaw, $down + $gapMin);
        $up = $this->clamp($up);

        if (!($up > $down)) {
            // elvileg gap miatt mindig > lesz; ha nem, hiba
            throw ValidationException::withMessages([
                'thresholds' => 'HYBRID: upper <= lower küszöb ütközés.',
            ]);
        }

        return [
            'normal_level_up'    => (int)$up,
            'normal_level_down'  => (int)$down,
            'monthly_level_down' => (int)$mon,
            // extra: visszaadhatjuk az upRaw-t, hogy a controller a grace döntéshez felhasználja
            '_hybrid_up_raw'     => (int)$upRaw,
        ];
    }

    // ---------- DYNAMIC ----------
    public function thresholdsForDynamic(array $cfg, array $scores): array
    {
        foreach (['threshold_top_pct','threshold_bottom_pct','monthly_level_down'] as $k) {
            if (!isset($cfg[$k]) || $cfg[$k] === '') {
                throw ValidationException::withMessages([
                    'config' => "DYNAMIC: hiányzik a(z) {$k} beállítás.",
                ]);
            }
        }

        $topPct    = (float)$cfg['threshold_top_pct'];
        $bottomPct = (float)$cfg['threshold_bottom_pct'];
        $mon       = (int)$cfg['monthly_level_down'];

        if ($topPct <= 0.0 || $topPct >= 100.0) {
            throw ValidationException::withMessages([
                'config' => 'DYNAMIC: threshold_top_pct érvénytelen.',
            ]);
        }
        if ($bottomPct <= 0.0 || $bottomPct >= 100.0) {
            throw ValidationException::withMessages([
                'config' => 'DYNAMIC: threshold_bottom_pct érvénytelen.',
            ]);
        }
        if (($topPct + $bottomPct) >= 100.0) {
            throw ValidationException::withMessages([
                'config' => 'DYNAMIC: top+bottom százalék >= 100%.',
            ]);
        }

        $up   = $this->topPercentileScore($scores,    $topPct);
        $down = $this->bottomPercentileScore($scores, $bottomPct);

        if (!($up > $down)) {
            // a te pontosításod szerint ez nem fordulhat elő; ha mégis, ne zárjunk
            throw ValidationException::withMessages([
                'thresholds' => 'DYNAMIC: upper <= lower (nem megengedett).',
            ]);
        }

        return [
            'normal_level_up'    => (int)$this->clamp($up),
            'normal_level_down'  => (int)$this->clamp($down),
            'monthly_level_down' => (int)$mon,
        ];
    }

    // ---------- SUGGESTED (AI eredményből) ----------
    public function thresholdsFromSuggested(array $cfg, array $ai): array
    {
        $up   = (int)($ai['thresholds']['normal_level_up']   ?? -1);
        $down = (int)($ai['thresholds']['normal_level_down'] ?? -1);
        $mon  = (int)($cfg['monthly_level_down'] ?? 70);

        if ($up < 0 || $down < 0) {
            throw ValidationException::withMessages([
                'ai' => 'SUGGESTED: AI küszöb értéktelen.',
            ]);
        }

        // policy: never_below_abs_min_for_promo
        $neverBelow = isset($cfg['never_below_abs_min_for_promo']) && (int)$cfg['never_below_abs_min_for_promo'] === 1;
        if ($neverBelow) {
            if (!isset($cfg['threshold_min_abs_up']) || $cfg['threshold_min_abs_up'] === '') {
                throw ValidationException::withMessages([
                    'config' => 'SUGGESTED: hiányzik threshold_min_abs_up, pedig never_below_abs_min_for_promo=1.',
                ]);
            }
            $minAbs = (int)$cfg['threshold_min_abs_up'];
            if ($up < $minAbs) {
                // nem csendben javítjuk; inkább hiba, ahogy kérted (nincs silent fallback)
                throw ValidationException::withMessages([
                    'ai' => "SUGGESTED: AI által javasolt up ({$up}) kisebb, mint abszolút minimum ({$minAbs}).",
                ]);
            }
        }

        if (!($up > $down)) {
            throw ValidationException::withMessages([
                'ai' => 'SUGGESTED: upper <= lower küszöb (érvénytelen AI javaslat).',
            ]);
        }

        return [
            'normal_level_up'    => (int)$this->clamp($up),
            'normal_level_down'  => (int)$this->clamp($down),
            'monthly_level_down' => (int)$mon,
        ];
    }

    // ---------- Segédek ----------
    public function topPercentileScore(array $scores, float $pct): int
    {
        // csökkenő rendezés, az utolsó a top pct sáv határ
        $n = count($scores);
        rsort($scores, SORT_NUMERIC);
        $k = (int) ceil(($pct / 100.0) * $n);
        $k = max(1, min($n, $k));
        $idx = $k - 1;
        return (int) round($scores[$idx]);
    }

    public function bottomPercentileScore(array $scores, float $pct): int
    {
        // növekvő rendezés, az utolsó a bottom pct sáv felső határ
        $n = count($scores);
        sort($scores, SORT_NUMERIC);
        $k = (int) ceil(($pct / 100.0) * $n);
        $k = max(1, min($n, $k));
        $idx = $k - 1;
        return (int) round($scores[$idx]);
    }

    private function clamp(int|float $v): int
    {
        return (int) max(0, min(100, (int) round($v)));
    }

    public function buildInitialThresholdsForStart(int $orgId): array
{
    $cfg  = $this->getOrgConfigMap($orgId);
    $mode = strtolower((string) $cfg['threshold_mode']); // fixed|hybrid|dynamic|suggested

    switch ($mode) {
        case 'fixed':
            return [
                'threshold_method'    => 'fixed',
                'normal_level_up'     => (int) $cfg['normal_level_up'],
                'normal_level_down'   => (int) $cfg['normal_level_down'],
                'monthly_level_down'  => (int) $cfg['monthly_level_down'],
            ];

        case 'hybrid':
            $minAbs = (int) $cfg['threshold_min_abs_up'];
            return [
                'threshold_method'    => 'hybrid',
                'normal_level_down'   => $minAbs,
                'normal_level_up'     => null,
                'monthly_level_down'  => (int) $cfg['monthly_level_down'],
            ];

        case 'dynamic':
            return [
                'threshold_method'    => 'dynamic',
                'normal_level_up'     => null,
                'normal_level_down'   => null,
                'monthly_level_down'  => (int) $cfg['monthly_level_down'],
            ];

        case 'suggested':
            return [
                'threshold_method'    => 'suggested',
                'normal_level_up'     => null,
                'normal_level_down'   => null,
                'monthly_level_down'  => (int) $cfg['monthly_level_down'],
            ];

        default:
            return [
                'threshold_method'    => 'fixed',
                'normal_level_up'     => (int) $cfg['normal_level_up'],
                'normal_level_down'   => (int) $cfg['normal_level_down'],
                'monthly_level_down'  => (int) $cfg['monthly_level_down'],
            ];
    }
}

}
