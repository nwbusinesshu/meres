<?php

namespace App\Console;

use App\Services\UserService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    
        // Clean up old user login records daily at 2 AM
        $schedule->command('user-logins:cleanup --days=30')
            ->dailyAt('02:00')
            ->onSuccess(function () {
                \Log::info('user_logins.cleanup.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('user_logins.cleanup.scheduled.failed');
            });
        
        // Clean up old webhook events daily at 3 AM
        $schedule->command('webhook:cleanup --days=30')
            ->dailyAt('03:00')
            ->onSuccess(function () {
                \Log::info('webhook.cleanup.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('webhook.cleanup.scheduled.failed');
            });

        // Clean up old API request logs daily at 4 AM
        $schedule->command('api-logs:cleanup --days=30')
            ->dailyAt('04:00')
            ->onSuccess(function () {
                \Log::info('api_logs.cleanup.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('api_logs.cleanup.scheduled.failed');
            });

        // Clean up expired login attempts daily
        $schedule->command('login-attempts:cleanup')->daily();

        //Retry failed AI telemetry processing every hour
        $schedule->command('telemetry:retry-failed --limit=100')
            ->hourly()
            ->withoutOverlapping(10) // Prevent overlapping runs, 10 min expiry
            ->onSuccess(function () {
                \Log::info('telemetry.retry.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('telemetry.retry.scheduled.failed');
            });

        //status check and cleanup schedules
            $schedule->command('status:check')->everyThirtyMinutes();
            $schedule->command('status:check --clean')->dailyAt('03:00');

        // Process pending invoices every 5 minutes
        $schedule->job(new \App\Jobs\ProcessPendingInvoices())
            ->everyFiveMinutes()
            ->withoutOverlapping(10) // Prevent overlapping runs, 10 min expiry
            ->onSuccess(function () {
                \Log::info('invoice.job.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('invoice.job.scheduled.failed');
            });

            // Daily assessment progress emails at 9:00 AM
    $schedule->command('assessment:daily-progress')
             ->dailyAt('09:00')
             ->withoutOverlapping()
             ->onOneServer();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}