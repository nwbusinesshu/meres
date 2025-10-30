<?php
// app/Services/ApiKeyService.php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ApiKeyService
{
    const KEY_PREFIX = 'qa360_live_';
    const KEY_LENGTH = 32;

    /**
     * Generate a new API key
     */
    public static function generateApiKey(int $organizationId, string $name, int $createdBy, ?array $permissions = null): array
    {
        // Generate a secure random key
        $rawKey = self::KEY_PREFIX . Str::random(self::KEY_LENGTH);
        $keyHash = hash('sha256', $rawKey);
        $lastChars = substr($rawKey, -8);

        // Default permissions if not specified
        if ($permissions === null) {
            $permissions = [
                'read:organization',
                'read:users',
                'read:assessments',
                'read:results',
                'read:bonus',
                'read:competencies'
            ];
        }

        // Insert into database
        $keyId = DB::table('api_keys')->insertGetId([
            'organization_id' => $organizationId,
            'name' => $name,
            'key_hash' => $keyHash,
            'key_prefix' => self::KEY_PREFIX,
            'last_chars' => $lastChars,
            'permissions' => json_encode($permissions),
            'created_by' => $createdBy,
            'expires_at' => now()->addYear(), // 1 year expiry by default
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return [
            'id' => $keyId,
            'key' => $rawKey, // Only returned once
            'last_chars' => $lastChars,
            'name' => $name,
            'expires_at' => now()->addYear()->toDateTimeString()
        ];
    }

    /**
     * Revoke an API key
     */
    public static function revokeApiKey(int $keyId, int $revokedBy): bool
    {
        $result = DB::table('api_keys')
            ->where('id', $keyId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $revokedBy,
                'updated_at' => now()
            ]);

        // Clear cache
        self::clearApiKeyCache($keyId);

        return $result > 0;
    }

    /**
     * Get all API keys for an organization
     */
    public static function getOrganizationApiKeys(int $organizationId): array
    {
        $keys = DB::table('api_keys as ak')
            ->leftJoin('user as u', 'ak.created_by', '=', 'u.id')
            ->leftJoin('user as ru', 'ak.revoked_by', '=', 'ru.id')
            ->where('ak.organization_id', $organizationId)
            ->select(
                'ak.id',
                'ak.name',
                'ak.last_chars',
                'ak.created_at',
                'ak.last_used_at',
                'ak.last_used_ip',
                'ak.expires_at',
                'ak.revoked_at',
                'u.name as created_by_name',
                'ru.name as revoked_by_name'
            )
            ->orderBy('ak.created_at', 'desc')
            ->get();

        // Add usage stats
        foreach ($keys as $key) {
            $key->is_active = is_null($key->revoked_at) && 
                             (is_null($key->expires_at) || $key->expires_at > now());
            
            // Get usage stats for last 24 hours
            $key->requests_24h = DB::table('api_request_logs')
                ->where('api_key_id', $key->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();
        }

        return $keys->toArray();
    }

    /**
     * Get API usage statistics
     */
    public static function getUsageStats(int $organizationId, int $days = 7): array
    {
        $stats = DB::table('api_request_logs')
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COUNT(DISTINCT api_key_id) as unique_keys')
            ->selectRaw('COUNT(DISTINCT endpoint) as unique_endpoints')
            ->selectRaw('AVG(response_time_ms) as avg_response_time')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get();

        return $stats->toArray();
    }

    /**
     * Clear API key cache
     */
    private static function clearApiKeyCache(int $keyId): void
    {
        $keyData = DB::table('api_keys')->where('id', $keyId)->first();
        if ($keyData) {
            Cache::forget('api_key_' . $keyData->key_hash);
        }
    }

    /**
     * Validate API key name
     */
    public static function isValidKeyName(string $name): bool
    {
        return strlen($name) >= 3 && strlen($name) <= 50 && 
               preg_match('/^[a-zA-Z0-9\s\-_]+$/', $name);
    }
}