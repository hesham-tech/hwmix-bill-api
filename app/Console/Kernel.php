<?php

namespace App\Console;

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
        $schedule->command('installments:notify-due')->daily();

        // Dynamic Backup Scheduling
        try {
            $frequency = \App\Models\BackupSetting::getVal('backup_frequency', 'daily');
            $time = \App\Models\BackupSetting::getVal('backup_time', '03:00');

            $backupTask = $schedule->command('backup:run')->onOneServer();

            switch ($frequency) {
                case 'daily':
                    $backupTask->dailyAt($time);
                    break;
                case 'weekly':
                    $backupTask->weeklyOn(5, $time); // Each Friday
                    break;
                case 'monthly':
                    $backupTask->monthlyOn(1, $time); // 1st of each month
                    break;
                default:
                    $backupTask->dailyAt($time);
            }

            // Cleanup old backups
            $schedule->command('backup:clean')->dailyAt('04:00');

        } catch (\Exception $e) {
            // Silently fail if table doesn't exist yet (migration phase)
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
