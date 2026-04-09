<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\DashboardSetting;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentReminderSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(private readonly int $appointmentId) {}

    public function handle(SmsService $sms): void
    {
        $appt = Appointment::with('client:id,mobile,first_name,last_name')->find($this->appointmentId);

        if (!$appt) {
            return; // appointment deleted since dispatch
        }

        // Skip if already sent or no longer eligible
        if ($appt->sms_sent_at || !$appt->reminder_at || $appt->start_at?->isPast()) {
            return;
        }

        $to = trim((string) ($appt->client?->mobile ?: $appt->client_phone));
        if ($to === '') {
            return;
        }

        $settings = DashboardSetting::query()->first();
        $body = $this->buildMessage($settings, $appt);
        $body = $this->limit165($body);

        try {
            $result = $sms->send($to, $body);

            $appt->sms_attempts    = (int) ($appt->sms_attempts ?? 0) + 1;
            $appt->sms_sent_success = true;
            $appt->sms_send_failed  = false;
            $appt->sms_sent_at      = now();
            $appt->sms_failed_at    = null;
            $appt->sms_provider     = $result['provider'] ?? null;
            $appt->sms_last_error   = null;
            $appt->save();

            if ($settings) {
                $settings->increment('sms_sent_appointments_count');
            }
        } catch (\Throwable $e) {
            $appt->sms_attempts    = (int) ($appt->sms_attempts ?? 0) + 1;
            $appt->sms_sent_success = false;
            $appt->sms_send_failed  = true;
            $appt->sms_failed_at    = now();
            $appt->sms_last_error   = $e->getMessage() ?: get_class($e);
            $appt->save();

            throw $e;
        }
    }

    private function buildMessage(?DashboardSetting $settings, Appointment $appt): string
    {
        $tpl = (string) ($settings?->sms_appointments_message ?? '');
        if (trim($tpl) === '') {
            $tpl = 'Reminder: you have an appointment tomorrow at {time}.';
        }

        return strtr($tpl, [
            '{date}'         => optional($appt->start_at)->format('Y-m-d') ?? '',
            '{time}'         => optional($appt->start_at)->format('H:i') ?? '',
            '{company_name}' => (string) ($settings?->company_name ?? ''),
        ]);
    }

    private function limit165(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > 165 ? mb_substr($s, 0, 165) : $s;
    }
}
