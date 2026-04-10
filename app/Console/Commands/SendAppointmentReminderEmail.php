<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderEmailJob;
use App\Models\Appointment;
use App\Models\DashboardSetting;
use Illuminate\Console\Command;

class SendAppointmentReminderEmail extends Command
{
    protected $signature   = 'email:send-appointment-reminders {--dry-run}';
    protected $description = 'Send appointment reminder emails 24h before start_at (based on reminder_at).';

    public function handle(): int
    {
        $settings = DashboardSetting::query()->first();

        if ($settings && isset($settings->email_appointments_enabled) && !$settings->email_appointments_enabled) {
            $this->info('Appointment email reminders disabled in settings.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $q = Appointment::query()
            ->where('status', 'confirmed')
            ->whereNotNull('reminder_at')
            ->whereNull('email_reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('start_at', '>', now())
            ->whereHas('client', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->orderBy('reminder_at');

        $dispatched = 0;

        $q->chunkById(100, function ($rows) use ($dry, &$dispatched) {
            foreach ($rows as $appt) {
                if ($dry) {
                    $this->line("[DRY] Would dispatch email reminder for appointment #{$appt->id}");
                    $dispatched++;
                    continue;
                }

                SendAppointmentReminderEmailJob::dispatch($appt->id);
                $dispatched++;
            }
        });

        $this->info("Done. Dispatched: {$dispatched} email reminder job(s).");
        return self::SUCCESS;
    }
}
