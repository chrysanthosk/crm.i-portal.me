<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\DashboardSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 30;

    public function __construct(private readonly int $appointmentId) {}

    public function handle(): void
    {
        $appt = Appointment::with([
            'client:id,first_name,last_name,email',
            'service:id,name',
            'staff.user:id,name',
        ])->find($this->appointmentId);

        if (!$appt) {
            return;
        }

        // Guard: already sent, appointment passed, or no longer confirmed
        if (
            $appt->email_reminder_sent_at ||
            !$appt->reminder_at ||
            $appt->start_at?->isPast()
        ) {
            return;
        }

        $email = trim((string) ($appt->client?->email ?? ''));
        if ($email === '') {
            return;
        }

        $settings = DashboardSetting::query()->first() ?? new DashboardSetting();

        Mail::to($email)->send(new AppointmentReminderMail($appt, $settings));

        $appt->email_reminder_sent_at = now();
        $appt->save();
    }
}
