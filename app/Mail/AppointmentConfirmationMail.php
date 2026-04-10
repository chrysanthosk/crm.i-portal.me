<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\DashboardSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly DashboardSetting $settings,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->settings->company_name ?? config('app.name');
        return new Envelope(subject: "Appointment Confirmed — {$company}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.appointment_confirmation');
    }
}
