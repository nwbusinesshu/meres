<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use App\Models\Enums\UserType;
use Closure;
use Illuminate\Http\Request;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     * 
     * Allow superadmins to bypass maintenance mode.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if user is logged in as superadmin
        if (session('utype') === UserType::SUPERADMIN) {
            // Superadmins can bypass maintenance mode
            return $next($request);
        }

        // For all other users, use default maintenance mode behavior
        return parent::handle($request, $next);
    }
}