<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ServiceHealthCheckService;

class CheckServiceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'status:check 
                            {--service= : Check specific service only (openai, barion, billingo, app_api, application)}
                            {--clean : Clean old records (older than 7 days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check health status of all external services and application';

    /**
     * Execute the console command.
     */
    public function handle(ServiceHealthCheckService $healthCheck)
    {
        // Clean old records if requested
        if ($this->option('clean')) {
            $deleted = $healthCheck->cleanOldRecords();
            $this->info("Cleaned {$deleted} old status check records.");
            return 0;
        }

        $this->info('Starting service health checks...');
        $this->newLine();

        $specificService = $this->option('service');

        // Check specific service or all
        if ($specificService) {
            $result = $this->checkSingleService($healthCheck, $specificService);
            if (!$result) {
                $this->error("Unknown service: {$specificService}");
                return 1;
            }
            $results = [$result];
        } else {
            $results = $healthCheck->runAllChecks();
        }

        // Display results in table
        $tableData = [];
        foreach ($results as $result) {
            $statusIcon = $this->getStatusIcon($result['status']);
            $tableData[] = [
                $result['service_name'],
                $statusIcon . ' ' . strtoupper($result['status']),
                $result['response_time_ms'] . ' ms',
                $result['error_message'] ?? '-',
            ];
        }

        $this->table(
            ['Service', 'Status', 'Response Time', 'Error'],
            $tableData
        );

        // Summary
        $downCount = collect($results)->where('status', 'down')->count();
        $slowCount = collect($results)->where('status', 'very_slow')->count() + 
                     collect($results)->where('status', 'slow')->count();

        $this->newLine();
        if ($downCount > 0) {
            $this->error("⚠️  {$downCount} service(s) are DOWN!");
        } elseif ($slowCount > 0) {
            $this->warn("⚠️  {$slowCount} service(s) are experiencing slowness.");
        } else {
            $this->info("✓ All services are operational!");
        }

        return 0;
    }

    /**
     * Check a single service
     */
    private function checkSingleService(ServiceHealthCheckService $healthCheck, string $service): ?array
    {
        $method = 'check' . ucfirst(str_replace('_', '', $service));
        
        if (!method_exists($healthCheck, $method)) {
            return null;
        }

        $result = $healthCheck->$method();
        
        // Store result
        \DB::table('service_status_checks')->insert($result);
        
        return $result;
    }

    /**
     * Get status icon for display
     */
    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'ok' => '✓',
            'slow' => '⚠',
            'very_slow' => '⚠⚠',
            'down' => '✗',
            default => '?',
        };
    }
}