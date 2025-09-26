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

    // ÚJ – TRANSLATION LANGUAGES:
    public const TRANSLATION_LANGUAGES = 'translation_languages'; // JSON array of selected languages

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
     * JSON getter (array / object) - ENHANCED VERSION
     * This method now properly handles failed JSON decoding and ensures a valid array/object is always returned
     */
    public static function getJson(int $orgId, string $name, $default = [])
    {
        $val = self::get($orgId, $name);
        
        // If no value found in database, return default
        if ($val === null || $val === '') {
            return $default;
        }
        
        // ADDITIONAL SAFETY: If the value is clearly not JSON (e.g., just a simple string), return default
        $trimmedVal = trim($val);
        if (!str_starts_with($trimmedVal, '{') && !str_starts_with($trimmedVal, '[') && $trimmedVal !== 'null') {
            \Log::warning('OrgConfigService: Value does not appear to be valid JSON', [
                'org_id' => $orgId,
                'name' => $name,
                'value' => $val,
            ]);
            return $default;
        }
        
        // Attempt to decode JSON
        $decoded = json_decode($val, true);
        
        // Check if decoding was successful and returned the expected type
        if (json_last_error() === JSON_ERROR_NONE) {
            // If we successfully decoded null, return the default instead
            if ($decoded === null) {
                return $default;
            }
            
            // Additional type checking - ensure we return the expected type
            if (is_array($default) && !is_array($decoded)) {
                \Log::warning('OrgConfigService: Decoded JSON is not an array when array expected', [
                    'org_id' => $orgId,
                    'name' => $name,
                    'value' => $val,
                    'decoded_type' => gettype($decoded),
                ]);
                return $default;
            }
            
            return $decoded;
        }
        
        // If JSON decoding failed, log the error for debugging and return default
        \Log::warning('OrgConfigService: Failed to decode JSON for organization config', [
            'org_id' => $orgId,
            'name' => $name,
            'value' => $val,
            'json_error' => json_last_error_msg(),
        ]);
        
        return $default;
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

    /**
     * Get translation languages for organization - ENHANCED VERSION
     * This method ensures we always return a valid array of languages
     */
    public static function getTranslationLanguages(int $orgId): array
    {
        $languages = self::getJson($orgId, self::TRANSLATION_LANGUAGES, [config('app.locale', 'hu')]);
        
        // Additional safety check to ensure we always have an array
        if (!is_array($languages)) {
            \Log::warning('OrgConfigService: Translation languages is not an array, falling back to default', [
                'org_id' => $orgId,
                'languages' => $languages,
            ]);
            return [config('app.locale', 'hu')];
        }
        
        // Ensure we have at least one language
        if (empty($languages)) {
            return [config('app.locale', 'hu')];
        }
        
        return $languages;
    }

    /**
     * Set translation languages for organization
     */
    public static function setTranslationLanguages(int $orgId, array $languages): void
    {
        // Validate input is array and not empty
        if (!is_array($languages) || empty($languages)) {
            throw new \InvalidArgumentException('Languages must be a non-empty array');
        }
        
        self::setJson($orgId, self::TRANSLATION_LANGUAGES, $languages);
    }
}