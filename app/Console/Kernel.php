<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Appointment SMS reminders: checked every minute
        $schedule->command('sms:send-appointment-reminders')
            ->everyMinute()
            ->withoutOverlapping();

        // Appointment email reminders: checked every minute
        $schedule->command('email:send-appointment-reminders')
            ->everyMinute()
            ->withoutOverlapping();

        // Birthday SMS: once daily
        $schedule->command('sms:send-birthday')
            ->dailyAt('09:00')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
