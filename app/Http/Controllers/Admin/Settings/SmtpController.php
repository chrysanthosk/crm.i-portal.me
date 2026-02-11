<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Models\SmtpSetting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SmtpController extends Controller
{
    public function edit()
    {
        if (!Schema::hasTable('smtp_settings')) {
            return view('admin.settings.smtp', ['missingTable' => true]);
        }

        $smtp = SmtpSetting::query()->first();
        return view('admin.settings.smtp', compact('smtp'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled'      => ['required', 'boolean'],
            'host'         => ['nullable', 'string', 'max:255'],
            'port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption'   => ['nullable', 'in:tls,ssl,'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:255'],
        ]);

        $smtp = SmtpSetting::query()->first() ?? new SmtpSetting();

        $smtp->enabled = (bool)$data['enabled'];
        $smtp->host = $data['host'] ?? null;
        $smtp->port = $data['port'] ?? null;
        $smtp->encryption = $data['encryption'] ?? null;
        $smtp->username = $data['username'] ?? null;

        if (!empty($data['password'])) {
            $smtp->password_enc = Crypt::encryptString($data['password']);
        }

        $smtp->from_address = $data['from_address'] ?? null;
        $smtp->from_name = $data['from_name'] ?? null;

        $smtp->save();

        Audit::log('settings', 'smtp.update', 'smtp_settings', (string)$smtp->id, [
            'enabled' => $smtp->enabled,
            'host' => $smtp->host,
            'port' => $smtp->port,
            'encryption' => $smtp->encryption,
            'username' => $smtp->username ? '***' : null,
        ]);

        return redirect()->route('admin.settings.smtp.edit')->with('status', 'SMTP settings saved.');
    }

    public function test(Request $request)
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $smtp = SmtpSetting::query()->first();
        if (!$smtp || !$smtp->enabled) {
            return back()->withErrors(['smtp_test' => 'SMTP is not enabled or not configured.']);
        }

        try {
            Mail::to($data['test_email'])->send(new SmtpTestMail());
            $smtp->last_tested_at = now();
            $smtp->save();

            Audit::log('settings', 'smtp.test.success', 'smtp_settings', (string)$smtp->id, ['to' => $data['test_email']]);

            return redirect()->route('admin.settings.smtp.edit')->with('status', 'Test email sent successfully.');
        } catch (\Throwable $e) {
            Audit::log('settings', 'smtp.test.failure', 'smtp_settings', (string)($smtp->id ?? 0), [
                'to' => $data['test_email'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['smtp_test' => 'SMTP test failed: ' . $e->getMessage()]);
        }
    }
}
