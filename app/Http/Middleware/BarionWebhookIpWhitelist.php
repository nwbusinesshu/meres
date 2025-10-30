<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BarionWebhookIpWhitelist
{
    /**
     * Handle an incoming webhook request from Barion.
     * 
     * Security: Only allow requests from Barion's official IP addresses.
     * Reference: https://docs.barion.com/Security
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get client IP (considering proxies)
        $clientIp = $request->ip();
        
        // Check if IP verification is enabled
        $ipCheckEnabled = config('security.barion_webhook.ip_check_enabled', true);
        
        if (!$ipCheckEnabled) {
            Log::warning('barion.webhook.ip_check_disabled', [
                'client_ip' => $clientIp,
                'warning' => 'IP whitelist check is disabled - this should only be temporary!'
            ]);
            return $next($request);
        }
        
        // Get whitelisted IPs from environment
        $allowedIps = $this->getAllowedIps();
        
        // Allow local development IPs if configured
        if ($this->isLocalDevelopment() && config('security.barion_webhook.allow_local', false)) {
            $allowedIps = array_merge($allowedIps, ['127.0.0.1', '::1', 'localhost']);
        }
        
        // Check if client IP is whitelisted
        if (!in_array($clientIp, $allowedIps, true)) {
            Log::warning('barion.webhook.ip_rejected', [
                'client_ip' => $clientIp,
                'allowed_ips' => $allowedIps,
                'headers' => $request->headers->all(),
                'request_uri' => $request->getRequestUri(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            
            // Return 403 Forbidden without revealing details
            return response('Forbidden', 403);
        }
        
        // IP is whitelisted - log success and continue
        Log::info('barion.webhook.ip_accepted', [
            'client_ip' => $clientIp,
            'request_uri' => $request->getRequestUri(),
        ]);
        
        return $next($request);
    }
    
    /**
     * Get list of allowed Barion IP addresses from environment.
     * 
     * @return array
     */
    private function getAllowedIps(): array
    {
        $productionIps = config('security.barion_webhook.ips_production', '');
        $sandboxIps = config('security.barion_webhook.ips_sandbox', '');
        
        // Combine both production and sandbox IPs
        $allIps = trim($productionIps . ',' . $sandboxIps, ',');
        
        if (empty($allIps)) {
            Log::error('barion.webhook.no_ips_configured', [
                'error' => 'BARION_WEBHOOK_IPS_PRODUCTION and BARION_WEBHOOK_IPS_SANDBOX are not configured!',
                'recommendation' => 'Add IPs to .env file immediately'
            ]);
            return [];
        }
        
        // Split by comma and clean up
        $ips = array_map('trim', explode(',', $allIps));
        
        // Remove empty values
        $ips = array_filter($ips, function($ip) {
            return !empty($ip);
        });
        
        return array_values($ips);
    }
    
    /**
     * Check if the application is running in local development environment.
     * 
     * @return bool
     */
    private function isLocalDevelopment(): bool
    {
        return app()->environment('local') || 
            config('app.env') === 'local' ||
            config('app.debug', false);
    }
}