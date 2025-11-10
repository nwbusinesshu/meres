<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function toggle(Request $request)
    {
        Log::info('=== MAINTENANCE TOGGLE START ===');
        
        try {
            $maintenanceFile = storage_path('framework/maintenance.php');
            Log::info('Maintenance file path: ' . $maintenanceFile);
            
            $isCurrentlyDown = File::exists($maintenanceFile);
            Log::info('Is currently down: ' . ($isCurrentlyDown ? 'YES' : 'NO'));

            if ($isCurrentlyDown) {
                // Disable maintenance mode
                Log::info('Attempting to disable maintenance mode...');
                
                $exitCode = Artisan::call('up');
                Log::info('Artisan up exit code: ' . $exitCode);
                
                $stillExists = File::exists($maintenanceFile);
                Log::info('File still exists after up: ' . ($stillExists ? 'YES' : 'NO'));
                
                return response()->json([
                    'ok' => true,
                    'status' => 'disabled',
                    'message' => 'Maintenance mode disabled',
                    'debug' => [
                        'exit_code' => $exitCode,
                        'file_existed' => $isCurrentlyDown,
                        'file_exists_now' => $stillExists
                    ]
                ]);
            } else {
                // Enable maintenance mode
                Log::info('Attempting to enable maintenance mode...');
                
                // Check if directory is writable
                $frameworkDir = storage_path('framework');
                $isWritable = is_writable($frameworkDir);
                Log::info('Framework directory writable: ' . ($isWritable ? 'YES' : 'NO'));
                Log::info('Framework directory permissions: ' . substr(sprintf('%o', fileperms($frameworkDir)), -4));
                
                $exitCode = Artisan::call('down', [
                    '--refresh' => 15,
                    '--retry' => 60,
                ]);
                
                Log::info('Artisan down exit code: ' . $exitCode);
                Log::info('Artisan output: ' . Artisan::output());
                
                // Check if file was created
                clearstatcache();
                $fileExists = File::exists($maintenanceFile);
                Log::info('File exists after down: ' . ($fileExists ? 'YES' : 'NO'));
                
                if ($fileExists) {
                    Log::info('File size: ' . filesize($maintenanceFile) . ' bytes');
                }
                
                return response()->json([
                    'ok' => true,
                    'status' => 'enabled',
                    'message' => 'Maintenance mode enabled',
                    'debug' => [
                        'exit_code' => $exitCode,
                        'directory_writable' => $isWritable,
                        'file_created' => $fileExists,
                        'artisan_output' => Artisan::output()
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('=== MAINTENANCE TOGGLE FAILED ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'ok' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        } finally {
            Log::info('=== MAINTENANCE TOGGLE END ===');
        }
    }

    public function status()
    {
        $maintenanceFile = storage_path('framework/maintenance.php');
        $isDown = File::exists($maintenanceFile);

        return response()->json([
            'ok' => true,
            'is_down' => $isDown,
            'status' => $isDown ? 'enabled' : 'disabled',
            'debug' => [
                'file_path' => $maintenanceFile,
                'file_exists' => $isDown
            ]
        ]);
    }
}