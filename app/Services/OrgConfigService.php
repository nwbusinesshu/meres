<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrgConfigService
{
    public const STRICT_ANON_KEY = 'strict_anonymous_mode';
    public const AI_TELEMETRY_KEY = 'ai_telemetry_enabled';
     // ÚJ – HYBRID finomhangolás:
    public const THRESHOLD_GRACE_POINTS = 'threshold_grace_points'; // int, 0..20 (ajánlott default 5)
    public const THRESHOLD_GAP_MIN      = 'threshold_gap_min';      // int, 0..10 (ajánlott default 2)

    // ÚJ – SUGGESTED (AI) policy:
    public const TARGET_PROMO_RATE_MAX      = 'target_promo_rate_max';      // float, 0..1 (pl. 0.20)
    public const TARGET_DEMOTION_RATE_MAX   = 'target_demotion_rate_max';   // float, 0..1 (pl. 0.10)
    public const NEVER_BELOW_ABS_MIN_PROMO  = 'never_below_abs_min_for_promo'; // int|null 0..100
    public const USE_TELEMETRY_TRUST        = 'use_telemetry_trust';        // bool
    public const NO_FORCED_DEMOTION_IF_HIGH = 'no_forced_demotion_if_high_cohesion'; // bool

    /**
     * Általános getter – stringet ad vissza.
     */
    public static function get(int $orgId, string $name, $default = null): ?string
    {
        $row = DB::table('organization_config')
            ->where('organization_id', $orgId)
            ->where('name', $name)
            ->value('value');

        return $row ?? $default;
    }

    
    /**
     * Boolean getter.
     */
    public static function getBool(int $orgId, string $name, bool $default = false): bool
    {
        $val = self::get($orgId, $name, $default ? '1' : '0');
        return in_array(strtolower((string)$val), ['1','true','on','yes'], true);
    }

    /**
     * Integer getter.
     */
    public static function getInt(int $orgId, string $name, int $default = 0): int
    {
        $val = self::get($orgId, $name, $default);
        return (int)$val;
    }

    /**
     * JSON getter (array / object).
     */
    public static function getJson(int $orgId, string $name, $default = [])
    {
        $val = self::get($orgId, $name);
        return $val !== null ? json_decode($val, true) : $default;
    }

    /**
     * Általános setter.
     */
    public static function set(int $orgId, string $name, $value): void
    {
        DB::table('organization_config')->updateOrInsert(
            ['organization_id' => $orgId, 'name' => $name],
            ['value' => (string)$value]
        );
    }

    public static function setBool(int $orgId, string $name, bool $value): void
    {
        self::set($orgId, $name, $value ? '1' : '0');
    }

    public static function setInt(int $orgId, string $name, int $value): void
    {
        self::set($orgId, $name, $value);
    }

    public static function setJson(int $orgId, string $name, $value): void
    {
        self::set($orgId, $name, json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
