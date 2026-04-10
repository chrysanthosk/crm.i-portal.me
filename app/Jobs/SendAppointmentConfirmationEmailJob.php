<?php

namespace App\Jobs;

use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\DashboardSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentConfirmationEmailJob implements ShouldQueue
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
            return; // deleted since dispatch
        }

        // Only send if still confirmed
        if ($appt->status !== 'confirmed') {
            return;
        }

        // Already sent
        if ($appt->email_confirmation_sent_at) {
            return;
        }

        $email = trim((string) ($appt->client?->email ?? ''));
        if ($email === '') {
            return;
        }

        $settings = DashboardSetting::query()->first() ?? new DashboardSetting();

        Mail::to($email)->send(new AppointmentConfirmationMail($appt, $settings));

        $appt->email_confirmation_sent_at = now();
        $appt->save();
    }
}
