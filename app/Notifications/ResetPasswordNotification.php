<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    protected function systemBrand(): array
    {
        // Default fallback if settings not present
        $fallback = [
            'header' => config('app.name', 'App'),
            'footer' => config('app.name', 'App'),
        ];

        try {
            $system = Setting::query()->where('key', 'system')->first();
            if (!$system) return $fallback;

            // Your DB stores JSON in "value" column (string), so decode safely
            $value = $system->value;

            if (is_string($value)) {
                $decoded = json_decode($value, true);
            } else {
                $decoded = $value; // if casted to array somewhere
            }

            if (!is_array($decoded)) return $fallback;

            return [
                'header' => $decoded['header_name'] ?? $fallback['header'],
                'footer' => $decoded['footer_name'] ?? $fallback['footer'],
            ];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public function toMail($notifiable): MailMessage
    {
        $brand = $this->systemBrand();

        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expire = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            // This sets the visible "From Name" for this email
            ->from(config('mail.from.address'), $brand['footer'])
            ->subject($brand['header'] . ' — Password Reset')
            ->greeting('Hello!')
            ->line('We received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line("This password reset link will expire in {$expire} minutes.")
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Regards,' . "\n" . $brand['footer']);
    }
}
