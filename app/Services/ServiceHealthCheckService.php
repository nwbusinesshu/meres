<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ServiceHealthCheckService
{
    /**
     * Check OpenAI API health with minimal token usage
     * Uses the cheapest possible request: 1 input token, 1 output token
     * Estimated cost: ~$0.000003 per check
     */
    public function checkOpenAI(): array
    {
        $startTime = microtime(true);
        
        try {
            $apiKey = config('services.openai.key');
            
            if (!$apiKey) {
                return [
                    'service_name' => 'openai',
                    'status' => 'down',
                    'response_time_ms' => 0,
                    'error_message' => 'API key not configured',
                    'checked_at' => now(),
                ];
            }

            // Minimal request: 2-3 input tokens, 1 output token max
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'ok']
                ],
                'max_tokens' => 1,
                'temperature' => 0,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                return [
                    'service_name' => 'openai',
                    'status' => 'down',
                    'response_time_ms' => $responseTime,
                    'error_message' => 'HTTP ' . $response->status() . ': ' . $response->body(),
                    'checked_at' => now(),
                ];
            }

            $status = $this->determineStatus($responseTime);

            return [
                'service_name' => 'openai',
                'status' => $status,
                'response_time_ms' => $responseTime,
                'error_message' => null,
                'checked_at' => now(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'openai',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => 'Connection timeout: ' . $e->getMessage(),
                'checked_at' => now(),
            ];
        } catch (\Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'openai',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ];
        }
    }

    /**
     * Check Barion API health
     * Barion returns 200 even for errors with error structure in JSON
     */
    /**
 * Check Barion API health
 * Barion returns 400 for invalid requests but that proves API is responding
 */
public function checkBarion(): array
{
    $startTime = microtime(true);
    
    try {
        $posKey = config('services.barion.poskey');
        $base = rtrim(env('BARION_API_URL', 'https://api.test.barion.com'), '/');
        
        if (!$posKey) {
            return [
                'service_name' => 'barion',
                'status' => 'down',
                'response_time_ms' => 0,
                'error_message' => 'POSKey not configured',
                'checked_at' => now(),
            ];
        }

        // Use GetPaymentState with a dummy PaymentId
        // Barion will return 400 error but that proves the API is up and responding
        $response = Http::timeout(15)
            ->get($base . '/v2/Payment/GetPaymentState', [
                'PaymentId' => '00000000000000000000000000000000',
                'POSKey' => $posKey,
            ]);

        $responseTime = round((microtime(true) - $startTime) * 1000);

        $statusCode = $response->status();
        
        // Barion API is working if we get 200, 400, or any structured response
        // 400 = Bad request (expected with dummy data, but API is responding)
        // 200 = Valid response
        // 500+ = Server error (API is down)
        if ($statusCode === 200 || $statusCode === 400) {
            $status = $this->determineStatus($responseTime);
            
            return [
                'service_name' => 'barion',
                'status' => $status,
                'response_time_ms' => $responseTime,
                'error_message' => null,
                'checked_at' => now(),
            ];
        }

        // 401, 403, 500+ = real problems
        return [
            'service_name' => 'barion',
            'status' => 'down',
            'response_time_ms' => $responseTime,
            'error_message' => 'HTTP ' . $statusCode,
            'checked_at' => now(),
        ];

    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        $responseTime = round((microtime(true) - $startTime) * 1000);
        return [
            'service_name' => 'barion',
            'status' => 'down',
            'response_time_ms' => $responseTime,
            'error_message' => 'Connection timeout: ' . $e->getMessage(),
            'checked_at' => now(),
        ];
    } catch (\Throwable $e) {
        $responseTime = round((microtime(true) - $startTime) * 1000);
        return [
            'service_name' => 'barion',
            'status' => 'down',
            'response_time_ms' => $responseTime,
            'error_message' => $e->getMessage(),
            'checked_at' => now(),
        ];
    }
}
    /**
     * Check Billingo API health
     */
    public function checkBillingo(): array
    {
        $startTime = microtime(true);
        
        try {
            $apiKey = config('services.billingo.api_key');
            $base = rtrim(env('BILLINGO_API_URL', 'https://api.billingo.hu/v3'), '/');
            
            if (!$apiKey) {
                return [
                    'service_name' => 'billingo',
                    'status' => 'down',
                    'response_time_ms' => 0,
                    'error_message' => 'API key not configured',
                    'checked_at' => now(),
                ];
            }

            // Lightweight endpoint: get partners with limit 1
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(15)
            ->get($base . '/partners', [
                'page' => 1,
                'per_page' => 1,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if (!$response->successful()) {
                return [
                    'service_name' => 'billingo',
                    'status' => 'down',
                    'response_time_ms' => $responseTime,
                    'error_message' => 'HTTP ' . $response->status() . ': ' . $response->body(),
                    'checked_at' => now(),
                ];
            }

            $status = $this->determineStatus($responseTime);

            return [
                'service_name' => 'billingo',
                'status' => $status,
                'response_time_ms' => $responseTime,
                'error_message' => null,
                'checked_at' => now(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'billingo',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => 'Connection timeout: ' . $e->getMessage(),
                'checked_at' => now(),
            ];
        } catch (\Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'billingo',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ];
        }
    }

    /**
     * Check App API health - use internal check instead of HTTP call
     * Since the endpoint requires authentication
     */
    public function checkAppAPI(): array
    {
        $startTime = microtime(true);
        
        try {
            // Do an internal health check instead of HTTP call
            // This checks if the application can respond to requests
            
            // Test database query
            DB::select('SELECT 1 as api_check');
            
            // Test a simple config read
            $appName = config('app.name');
            
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if (empty($appName)) {
                return [
                    'service_name' => 'app_api',
                    'status' => 'down',
                    'response_time_ms' => $responseTime,
                    'error_message' => 'Config not loaded',
                    'checked_at' => now(),
                ];
            }

            $status = $this->determineStatus($responseTime);

            return [
                'service_name' => 'app_api',
                'status' => $status,
                'response_time_ms' => $responseTime,
                'error_message' => null,
                'checked_at' => now(),
            ];

        } catch (\Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'app_api',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ];
        }
    }

    /**
     * Check internal application health (database + cache)
     */
    public function checkApplication(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test database connection with simple query
            DB::select('SELECT 1 as health_check');
            
            // Test cache
            $cacheKey = 'health_check_' . time();
            Cache::put($cacheKey, 'ok', 10);
            $cacheTest = Cache::get($cacheKey);
            Cache::forget($cacheKey);
            
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($cacheTest !== 'ok') {
                return [
                    'service_name' => 'application',
                    'status' => 'down',
                    'response_time_ms' => $responseTime,
                    'error_message' => 'Cache test failed',
                    'checked_at' => now(),
                ];
            }

            $status = $this->determineStatus($responseTime);

            return [
                'service_name' => 'application',
                'status' => $status,
                'response_time_ms' => $responseTime,
                'error_message' => null,
                'checked_at' => now(),
            ];

        } catch (\Throwable $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);
            return [
                'service_name' => 'application',
                'status' => 'down',
                'response_time_ms' => $responseTime,
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ];
        }
    }

    /**
     * Determine status based on response time
     */
    private function determineStatus(int $responseTimeMs): string
    {
        if ($responseTimeMs < 5000) {
            return 'ok';
        } elseif ($responseTimeMs < 10000) {
            return 'slow';
        } elseif ($responseTimeMs < 15000) {
            return 'very_slow';
        } else {
            return 'down';
        }
    }

    /**
     * Run all health checks and store results
     */
    public function runAllChecks(): array
    {
        $results = [];

        // Check each service
        $results[] = $this->checkOpenAI();
        $results[] = $this->checkBarion();
        $results[] = $this->checkBillingo();
        $results[] = $this->checkAppAPI();
        $results[] = $this->checkApplication();

        // Store all results in database
        foreach ($results as $result) {
            DB::table('service_status_checks')->insert($result);
        }

        return $results;
    }

    /**
     * Get latest status for all services
     */
    public function getLatestStatus(): array
    {
        $services = ['openai', 'barion', 'billingo', 'app_api', 'application'];
        $latest = [];

        foreach ($services as $service) {
            $latest[$service] = DB::table('service_status_checks')
                ->where('service_name', $service)
                ->orderBy('checked_at', 'desc')
                ->first();
        }

        return $latest;
    }

    /**
     * Get status history for a service
     */
    public function getServiceHistory(string $serviceName, int $hours = 24): array
    {
        return DB::table('service_status_checks')
            ->where('service_name', $serviceName)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Clean old status check records (keep last 7 days)
     */
    public function cleanOldRecords(): int
    {
        return DB::table('service_status_checks')
            ->where('checked_at', '<', now()->subDays(7))
            ->delete();
    }
}