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
        // Existing monthly user level handling
        $schedule->call(function () {
            UserService::handleNewMonthLevels();
        })->monthly();
        
        // NEW: Clean up old webhook events daily at 3 AM
        $schedule->command('webhook:cleanup --days=30')
            ->dailyAt('03:00')
            ->onSuccess(function () {
                \Log::info('webhook.cleanup.scheduled.success');
            })
            ->onFailure(function () {
                \Log::error('webhook.cleanup.scheduled.failed');
            });
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