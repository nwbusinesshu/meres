<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    /**
     * Toggle maintenance mode on/off
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(Request $request)
    {
        try {
            $maintenanceFile = storage_path('framework/maintenance.php');
            $isCurrentlyDown = File::exists($maintenanceFile);

            if ($isCurrentlyDown) {
                // Disable maintenance mode
                Artisan::call('up');
                
                Log::info('Maintenance mode disabled', [
                    'user_id' => session('uid'),
                    'user_email' => auth()->user()?->email
                ]);

                return response()->json([
                    'ok' => true,
                    'status' => 'disabled',
                    'message' => __('maintenance.disabled-success')
                ]);
            } else {
                // Enable maintenance mode
                Artisan::call('down', [
                    '--render' => 'errors::503',
                    '--retry' => 60
                ]);
                
                Log::info('Maintenance mode enabled', [
                    'user_id' => session('uid'),
                    'user_email' => auth()->user()?->email
                ]);

                return response()->json([
                    'ok' => true,
                    'status' => 'enabled',
                    'message' => __('maintenance.enabled-success')
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Maintenance mode toggle failed', [
                'error' => $e->getMessage(),
                'user_id' => session('uid')
            ]);

            return response()->json([
                'ok' => false,
                'message' => __('maintenance.toggle-error')
            ], 500);
        }
    }

    /**
     * Get current maintenance mode status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $maintenanceFile = storage_path('framework/maintenance.php');
        $isDown = File::exists($maintenanceFile);

        return response()->json([
            'ok' => true,
            'is_down' => $isDown,
            'status' => $isDown ? 'enabled' : 'disabled'
        ]);
    }
}