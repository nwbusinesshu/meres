<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrgConfigService
{
    public const STRICT_ANON_KEY = 'strict_anonymous_mode';
    public const AI_TELEMETRY_KEY = 'ai_telemetry_enabled';

    public static function getBool(int $orgId, string $name, bool $default = false): bool
    {
        $row = DB::table('organization_config')
            ->where('organization_id', $orgId)
            ->where('name', $name)
            ->value('value');

        if ($row === null) return $default;
        return in_array(strtolower((string)$row), ['1','true','on','yes'], true);
    }

    public static function setBool(int $orgId, string $name, bool $value): void
    {
        DB::table('organization_config')->updateOrInsert(
            ['organization_id' => $orgId, 'name' => $name],
            ['value' => $value ? '1' : '0']
        );
    }
}
