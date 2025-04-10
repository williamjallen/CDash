<?php

namespace App\Console;

use App\Jobs\NotifyExpiringAuthTokens;
use App\Jobs\PruneAuthTokens;
use App\Jobs\PruneJobs;
use App\Jobs\PruneUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
        $cdash_app_dir = realpath(app_path($cdash_directory_name));
        $output_filename = $cdash_app_dir . '/AuditReport.log';

        $schedule->command('dependencies:audit')
            ->everySixHours()
            ->sendOutputTo($output_filename);

        $schedule->job(new PruneJobs())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneAuthTokens())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneUploads())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new NotifyExpiringAuthTokens())
            ->daily()
            ->withoutOverlapping();

        if (env('CDASH_AUTHENTICATION_PROVIDER') === 'ldap') {
            $schedule->command('ldap:sync_projects')
                ->everyFiveMinutes();
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
