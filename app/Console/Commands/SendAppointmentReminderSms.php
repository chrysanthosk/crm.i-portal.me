<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\DashboardSetting;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SendAppointmentReminderSms extends Command
{
    protected $signature = 'sms:send-appointment-reminders {--dry-run}';
    protected $description = 'Send appointment reminder SMS exactly 24h before start_at (based on reminder_at).';

    public function handle(SmsService $sms): int
    {
        $settings = DashboardSetting::query()->first();
        if (!$settings || !$settings->sms_appointments_enabled) {
            $this->info('Appointment SMS disabled.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $q = Appointment::query()
            ->with('client:id,mobile,first_name,last_name')
            ->where('send_sms', true)
            ->where('status', 'confirmed')
            ->whereNotNull('reminder_at')
            ->whereNull('sms_sent_at')
            ->where('reminder_at', '<=', now())
            ->where('start_at', '>', now())
            ->orderBy('reminder_at');

        $sent = 0;

        $q->chunkById(100, function ($rows) use ($sms, $settings, $dry, &$sent) {
            foreach ($rows as $appt) {
                $to = trim((string)($appt->client?->mobile ?: $appt->client_phone));
                if ($to === '') {
                    continue;
                }

                $body = $this->buildMessage($settings, $appt);
                $body = $this->limit165($body);

                if ($dry) {
                    $this->line("[DRY] To={$to} :: {$body}");
                    $sent++;
                    continue;
                }

                try {
                    $result = $sms->send($to, $body);

                    $appt->sms_attempts = (int)($appt->sms_attempts ?? 0) + 1;
                    $appt->sms_sent_success = true;
                    $appt->sms_send_failed = false;
                    $appt->sms_sent_at = now();
                    $appt->sms_failed_at = null;
                    $appt->sms_provider = $result['provider'] ?? null;
                    $appt->sms_last_error = null;
                    $appt->save();

                    $settings->increment('sms_sent_appointments_count');
                    $sent++;
                } catch (\Throwable $e) {
                    $appt->sms_attempts = (int)($appt->sms_attempts ?? 0) + 1;
                    $appt->sms_sent_success = false;
                    $appt->sms_send_failed = true;
                    $appt->sms_failed_at = now();
                    $appt->sms_last_error = $e->getMessage() !== '' ? $e->getMessage() : get_class($e);
                    $appt->save();

                    $this->error("Failed sending to {$to}: " . ($e->getMessage() ?: get_class($e)));
                }
            }
        });

        $this->info("Done. Sent: {$sent}");
        return self::SUCCESS;
    }

    private function buildMessage(DashboardSetting $settings, Appointment $appt): string
    {
        $tpl = (string)($settings->sms_appointments_message ?? '');
        if (trim($tpl) === '') {
            $tpl = 'Reminder: you have an appointment tomorrow at {time}.';
        }

        $date = optional($appt->start_at)->format('Y-m-d') ?? '';
        $time = optional($appt->start_at)->format('H:i') ?? '';
        $company = (string)($settings->company_name ?? '');

        return strtr($tpl, [
            '{date}' => $date,
            '{time}' => $time,
            '{company_name}' => $company,
        ]);
    }

    private function limit165(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > 165 ? mb_substr($s, 0, 165) : $s;
    }
}
