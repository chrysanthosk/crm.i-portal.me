<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderSmsJob;
use App\Models\Appointment;
use App\Models\DashboardSetting;
use Illuminate\Console\Command;

class SendAppointmentReminderSms extends Command
{
    protected $signature = 'sms:send-appointment-reminders {--dry-run}';
    protected $description = 'Send appointment reminder SMS exactly 24h before start_at (based on reminder_at).';

    public function handle(): int
    {
        $settings = DashboardSetting::query()->first();
        if (!$settings || !$settings->sms_appointments_enabled) {
            $this->info('Appointment SMS disabled.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $q = Appointment::query()
            ->where('send_sms', true)
            ->where('status', 'confirmed')
            ->whereNotNull('reminder_at')
            ->whereNull('sms_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('start_at', '>', now())
            ->orderBy('reminder_at');

        $dispatched = 0;

        $q->chunkById(100, function ($rows) use ($dry, &$dispatched) {
            foreach ($rows as $appt) {
                if ($dry) {
                    $this->line("[DRY] Would dispatch job for appointment #{$appt->id}");
                    $dispatched++;
                    continue;
                }

                SendAppointmentReminderSmsJob::dispatch($appt->id);
                $dispatched++;
            }
        });

        $this->info("Done. Dispatched: {$dispatched} reminder job(s).");
        return self::SUCCESS;
    }
}
