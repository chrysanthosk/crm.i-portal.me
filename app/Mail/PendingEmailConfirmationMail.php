<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendingEmailConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $confirmUrl;

    public function __construct(string $confirmUrl)
    {
        $this->confirmUrl = $confirmUrl;
    }

    public function build()
    {
        return $this->subject('Confirm your new email address')
            ->view('emails.confirm_email_change')
            ->with(['confirmUrl' => $this->confirmUrl]);
    }
}
