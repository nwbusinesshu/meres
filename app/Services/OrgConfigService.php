<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrgConfigService
{
    public const STRICT_ANON_KEY = 'strict_anonymous_mode';
    public const AI_TELEMETRY_KEY = 'ai_telemetry_enabled';

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
