<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Standard API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        
        // Webhook-specific rate limiter
        // Protects against webhook flooding/DDoS even from whitelisted IPs
        RateLimiter::for('webhook', function (Request $request) {
            $limit = (int) env('WEBHOOK_RATE_LIMIT', 100);
            
            return Limit::perMinute($limit)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    \Log::warning('webhook.rate_limit_exceeded', [
                        'ip' => $request->ip(),
                        'path' => $request->path(),
                        'headers' => $request->headers->all(),
                        'timestamp' => now()->toDateTimeString(),
                    ]);
                    
                    return response('Too Many Requests', 429, $headers);
                });
        });
    }
}