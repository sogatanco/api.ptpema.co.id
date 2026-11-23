<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('contract:reminder')->daily();
        // satu pesan WA notifikasi per menit
        $schedule->command('notifikasi:kirim-wa')->everyMinute()->withoutOverlapping();
        // pengingat untuk PIC Konsumsi 1 jam sebelum rapat yang meminta konsumsi
        $schedule->command('meeting:ingatkan-konsumsi')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('sppd:ingatkan-void')->dailyAt('08:00')->withoutOverlapping();
        $schedule->command('sppd:ingatkan-termin')->dailyAt('08:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
