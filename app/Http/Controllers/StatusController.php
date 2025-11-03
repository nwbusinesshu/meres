<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ServiceHealthCheckService;

class StatusController extends Controller
{
    /**
     * Show the status page (accessible to everyone)
     */
    public function index(ServiceHealthCheckService $healthCheck)
    {
        $latest = $healthCheck->getLatestStatus();
        
        return view('status', [
            'latest' => $latest,
        ]);
    }

    /**
     * Get current status as JSON (for auto-refresh)
     */
    public function data(ServiceHealthCheckService $healthCheck)
    {
        $latest = $healthCheck->getLatestStatus();
        
        $services = [];
        foreach ($latest as $serviceName => $check) {
            $services[] = [
                'name' => $serviceName,
                'status' => $check->status ?? 'unknown',
                'response_time' => $check->response_time_ms ?? null,
            ];
        }
        
        return response()->json(['services' => $services]);
    }

    /**
     * Get 24h history for timeline
     */
    public function history(ServiceHealthCheckService $healthCheck)
    {
        $services = ['openai', 'barion', 'billingo', 'app_api', 'application'];
        $timeline = [];
        
        foreach ($services as $service) {
            $history = $healthCheck->getServiceHistory($service, 24);
            $timeline[$service] = $history;
        }
        
        return response()->json(['timeline' => $timeline]);
    }
}