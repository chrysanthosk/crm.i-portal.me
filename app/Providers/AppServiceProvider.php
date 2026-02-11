<?php

namespace App\Providers;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Dynamically apply SMTP settings from DB if enabled.
        // Safe-guarded so it won't crash during first migrations.
        try {
            if (Schema::hasTable('smtp_settings')) {
                $smtp = SmtpSetting::query()->first();
                if ($smtp && $smtp->enabled && $smtp->host && $smtp->port) {
                    $password = null;
                    if (!empty($smtp->password_enc)) {
                        try {
                            $password = Crypt::decryptString($smtp->password_enc);
                        } catch (\Throwable $e) {
                            $password = null;
                        }
                    }

                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp.host' => $smtp->host,
                        'mail.mailers.smtp.port' => (int)$smtp->port,
                        'mail.mailers.smtp.encryption' => $smtp->encryption ?: null,
                        'mail.mailers.smtp.username' => $smtp->username ?: null,
                        'mail.mailers.smtp.password' => $password,
                    ]);

                    if ($smtp->from_address) {
                        config([
                            'mail.from.address' => $smtp->from_address,
                            'mail.from.name' => $smtp->from_name ?: config('app.name'),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // never break the app because of settings
        }
    }
}
