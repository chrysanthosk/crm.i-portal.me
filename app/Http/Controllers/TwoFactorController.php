<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $secret = null;
        $qrPngDataUri = null;

        if (!$user->hasTwoFactorEnabled()) {
            $secret = $request->session()->get('2fa_secret_pending');
            if ($secret) {
                $otpauth = Totp::otpauthUrl($user->email, config('app.name'), $secret);
                $qrPngDataUri = $this->makeQrDataUri($otpauth);
            }
        }

        return view('profile.2fa', compact('user', 'secret', 'qrPngDataUri'));
    }

    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.2fa.show');
        }

        $secret = Totp::generateSecret(20);

        $request->session()->put('2fa_secret_pending', $secret);

        Audit::log('profile', '2fa.generate', 'user', $user->id);

        return redirect()->route('profile.2fa.show');
    }

    public function confirm(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $secret = $request->session()->get('2fa_secret_pending');
        if (!$secret) {
            return back()->withErrors(['code' => 'Please generate a QR code first.']);
        }

        if (!Totp::verifyCode($secret, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user->setTwoFactorSecretPlain($secret);
        $user->two_factor_enabled = 1;
        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $this->generateRecoveryCodes();
        $user->save();

        $request->session()->forget('2fa_secret_pending');

        Audit::log('profile', '2fa.enabled', 'user', $user->id);

        return redirect()->route('profile.2fa.show')->with('status', '2FA enabled.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = [];
        $user->two_factor_confirmed_at = null;
        $user->two_factor_enabled = 0;
        $user->save();

        $request->session()->forget('2fa_secret_pending');

        Audit::log('profile', '2fa.disabled', 'user', $user->id);

        return redirect()->route('profile.2fa.show')->with('status', '2FA disabled.');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => '2FA is not enabled.']);
        }

        $user->two_factor_recovery_codes = $this->generateRecoveryCodes();
        $user->save();

        Audit::log('profile', '2fa.recovery.regenerate', 'user', $user->id);

        return back()->with('status', 'Recovery codes regenerated.');
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5))); // 10 chars
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    private function makeQrDataUri(string $text): ?string
    {
        // Optional dependency: chillerlan/php-qrcode
        if (class_exists('chillerlan\\QRCode\\QRCode') && class_exists('chillerlan\\QRCode\\QROptions')) {
            try {
                $options = new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'scale' => 8,
                    'imageBase64' => true,
                ]);

                $qrcode = (new \chillerlan\QRCode\QRCode($options))->render($text);
                // With imageBase64=true, render() returns a data URI
                return $qrcode;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
