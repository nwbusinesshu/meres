<?php
// app/Http/Middleware/ApiAuthentication.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_API_KEY',
                    'message' => 'API key is required'
                ]
            ], 401);
        }

        // Extract key parts
        if (!preg_match('/^(qa360_[a-z]+_)(.+)$/', $apiKey, $matches)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_API_KEY_FORMAT',
                    'message' => 'Invalid API key format'
                ]
            ], 401);
        }

        $keyHash = hash('sha256', $apiKey);
        
        // Check cache first (cache for 5 minutes)
        $cacheKey = 'api_key_' . $keyHash;
        $apiKeyData = Cache::remember($cacheKey, 300, function() use ($keyHash) {
            return DB::table('api_keys')
                ->join('organization', 'api_keys.organization_id', '=', 'organization.id')
                ->where('key_hash', $keyHash)
                ->whereNull('revoked_at')
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->select('api_keys.*', 'organization.name as org_name')
                ->first();
        });

        if (!$apiKeyData) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'Invalid or expired API key'
                ]
            ], 401);
        }

        // Check if API is enabled for this organization
        $apiEnabled = DB::table('organization_config')
            ->where('organization_id', $apiKeyData->organization_id)
            ->where('name', 'api_enabled')
            ->value('value');

        if ($apiEnabled !== '1') {
            return response()->json([
                'error' => [
                    'code' => 'API_DISABLED',
                    'message' => 'API access is disabled for this organization'
                ]
            ], 403);
        }

        // Check rate limit
        $rateLimit = $this->checkRateLimit($apiKeyData);
        if (!$rateLimit['allowed']) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => $rateLimit['retry_after']
                ]
            ], 429);
        }

        // Log the request (async)
        $this->logApiRequest($apiKeyData, $request);

        // Add API key data to request for use in controllers
        $request->attributes->set('api_key', $apiKeyData);
        $request->attributes->set('organization_id', $apiKeyData->organization_id);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $rateLimit['limit']);
        $response->headers->set('X-RateLimit-Remaining', $rateLimit['remaining']);
        $response->headers->set('X-RateLimit-Reset', $rateLimit['reset']);

        return $response;
    }

    private function checkRateLimit($apiKeyData)
    {
        $minuteBucket = now()->format('Y-m-d H:i:00');
        
        // Get rate limit setting
        $rateLimit = DB::table('organization_config')
            ->where('organization_id', $apiKeyData->organization_id)
            ->where('name', 'api_rate_limit_per_minute')
            ->value('value') ?? 60;

        // Get current count
        $currentCount = DB::table('api_rate_limits')
            ->where('api_key_id', $apiKeyData->id)
            ->where('minute_bucket', $minuteBucket)
            ->value('request_count') ?? 0;

        if ($currentCount >= $rateLimit) {
            return [
                'allowed' => false,
                'limit' => $rateLimit,
                'remaining' => 0,
                'reset' => strtotime($minuteBucket) + 60,
                'retry_after' => 60 - (time() - strtotime($minuteBucket))
            ];
        }

        // Increment counter
        DB::table('api_rate_limits')
            ->insertOrIgnore([
                'api_key_id' => $apiKeyData->id,
                'minute_bucket' => $minuteBucket,
                'request_count' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        DB::table('api_rate_limits')
            ->where('api_key_id', $apiKeyData->id)
            ->where('minute_bucket', $minuteBucket)
            ->increment('request_count');

        return [
            'allowed' => true,
            'limit' => $rateLimit,
            'remaining' => $rateLimit - $currentCount - 1,
            'reset' => strtotime($minuteBucket) + 60
        ];
    }

    private function logApiRequest($apiKeyData, Request $request)
    {
        try {
            DB::table('api_request_logs')->insert([
                'api_key_id' => $apiKeyData->id,
                'organization_id' => $apiKeyData->organization_id,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log API request: ' . $e->getMessage());
        }
    }
}