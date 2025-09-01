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
            'threshold_mode'      => 'fixed',
            'normal_level_up'     => '85',
            'normal_level_down'   => '70',
            'monthly_level_down'  => '70',
            'threshold_min_abs_up'=> '80',
            'threshold_top_pct'   => '15',
            'threshold_bottom_pct'=> '20',
        ];

        // Merge: org config felülírja a defaultokat
        return array_merge($defaults, $rows->toArray());
    }

    /**
     * Assessment indításához szükséges mezők előállítása.
     * - FIXED: mindhárom küszöb kitöltve az org configból
     * - HYBRID: method=hybrid, normal_level_down = threshold_min_abs_up, normal_level_up ideiglenes (min abs), monthly configból
     * - DYNAMIC/SUGGESTED: method beállítva, küszöbök ideiglenesen 0 (vagy null, ha engedi a DB)
     */
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
