<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request)
    {
        $pending = $request->session()->get('2fa_pending');

        if (!$pending || empty($pending['user_id'])) {
            return redirect()->route('login');
        }

        // Optional: expire the pending state
        if (!empty($pending['expires_at']) && now()->timestamp > (int)$pending['expires_at']) {
            $request->session()->forget(['2fa_pending', '2fa_intended']);
            return redirect()->route('login')->withErrors(['code' => '2FA session expired. Please login again.']);
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $pending = $request->session()->get('2fa_pending');

        if (!$pending || empty($pending['user_id'])) {
            return redirect()->route('login');
        }

        if (!empty($pending['expires_at']) && now()->timestamp > (int)$pending['expires_at']) {
            $request->session()->forget(['2fa_pending', '2fa_intended']);
            return redirect()->route('login')->withErrors(['code' => '2FA session expired. Please login again.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $code = trim((string)($data['code'] ?? ''));
        $recovery = trim((string)($data['recovery_code'] ?? ''));

        if ($code === '' && $recovery === '') {
            return back()->withErrors(['code' => 'Please enter a 2FA code or a recovery code.']);
        }

        $user = User::find((int)$pending['user_id']);
        if (!$user || !$user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa_pending', '2fa_intended']);
            return redirect()->route('login')->withErrors(['code' => '2FA could not be verified. Please login again.']);
        }

        $ok = false;

        // Verify TOTP
        if ($code !== '') {
            $secret = $user->getTwoFactorSecretPlain();
            if (!$secret) {
                return back()->withErrors(['code' => '2FA secret missing on account.']);
            }

            $ok = Totp::verifyCode($secret, $code);
            if (!$ok) {
                return back()->withErrors(['code' => 'Invalid authentication code.']);
            }
        }

        // Verify recovery code (and consume it)
        if (!$ok && $recovery !== '') {
            $needle = strtoupper($recovery);

            $codes = $user->two_factor_recovery_codes ?? [];
            $idx = array_search($needle, array_map('strtoupper', $codes), true);

            if ($idx === false) {
                return back()->withErrors(['recovery_code' => 'Invalid recovery code.']);
            }

            // Consume the code
            unset($codes[$idx]);
            $user->two_factor_recovery_codes = array_values($codes);
            $user->save();

            $ok = true;
        }

        if (!$ok) {
            return back()->withErrors(['code' => '2FA verification failed.']);
        }

        // Complete login (apply remember only after 2FA success)
        $remember = !empty($pending['remember']);

        Auth::loginUsingId($user->id, $remember);
        $request->session()->regenerate();

        $request->session()->forget(['2fa_pending', '2fa_intended']);

        Audit::log('auth', '2fa.challenge.pass', 'user', $user->id);

        $intended = $request->session()->pull('2fa_intended', route('dashboard'));
        return redirect()->to($intended);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(['2fa_pending', '2fa_intended']);
        return redirect()->route('login');
    }
}
