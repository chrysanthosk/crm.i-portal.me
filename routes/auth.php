<?php

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

const TRUSTED_2FA_COOKIE = 'tfd'; // trusted factor device
const TRUSTED_2FA_DAYS = 30;

function trustedDeviceCookie(Request $request, string $value, int $days)
{
    $minutes = $days * 24 * 60;

    // secure cookie on https only; httpOnly always
    return cookie(
        TRUSTED_2FA_COOKIE,
        $value,
        $minutes,
        '/',           // path
        null,          // domain
        $request->isSecure(),
        true,          // httpOnly
        false,         // raw
        'Lax'          // sameSite
    );
}

function parseTrustedCookie(?string $cookieValue): ?array
{
    if (!$cookieValue) return null;
    $parts = explode('|', $cookieValue, 2);
    if (count($parts) !== 2) return null;

    $id = (int)$parts[0];
    $token = trim($parts[1]);

    if ($id <= 0 || $token === '') return null;
    return [$id, $token];
}

function isTrustedDeviceForUser(Request $request, User $user): bool
{
    $parsed = parseTrustedCookie($request->cookie(TRUSTED_2FA_COOKIE));
    if (!$parsed) return false;

    [$deviceId, $token] = $parsed;

    $device = TwoFactorTrustedDevice::query()
        ->where('id', $deviceId)
        ->where('user_id', $user->id)
        ->first();

    if (!$device) return false;

    if ($device->expires_at && now()->greaterThan($device->expires_at)) {
        return false;
    }

    $tokenHash = hash('sha256', $token);
    if (!hash_equals($device->token_hash, $tokenHash)) {
        return false;
    }

    // Optional binding to same browser (user agent)
    if (!empty($device->user_agent_hash)) {
        $uaHash = hash('sha256', (string)$request->userAgent());
        if (!hash_equals($device->user_agent_hash, $uaHash)) {
            return false;
        }
    }

    // Update last_used_at
    $device->last_used_at = now();
    $device->ip_address = $request->ip();
    $device->save();

    return true;
}

Route::middleware('guest')->group(function () {

    // Login page
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    // Handle login (Remember Me + enforce 2FA unless trusted device)
    Route::post('/login', function (Request $request) {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool)$request->boolean('remember');

        // Attempt login WITHOUT remember first
        if (!Auth::attempt($request->only('email', 'password'), false)) {
            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // If user has 2FA enabled, check trusted device cookie
        if ($user && method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {

            // If trusted device -> skip challenge
            if (isTrustedDeviceForUser($request, $user)) {

                // Apply remember ONLY now (after we "passed" 2FA via trust)
                if ($remember) {
                    Auth::login($user, true);
                }

                Audit::log('auth', '2fa.trusted_device.pass', 'user', $user->id);

                return redirect()->intended('/dashboard');
            }

            // Not trusted -> enforce challenge
            $intended = $request->session()->get('url.intended', url('/dashboard'));
            $request->session()->put('2fa_intended', $intended);

            $request->session()->put('2fa_pending', [
                'user_id' => $user->id,
                'remember' => $remember,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            Auth::logout();

            Audit::log('auth', '2fa.challenge.start', 'user', $user->id);

            return redirect()->route('2fa.challenge');
        }

        // No 2FA: apply remember now if selected
        if ($user && $remember) {
            Auth::login($user, true);
        }

        Audit::log('auth', 'login', 'user', $user?->id);

        return redirect()->intended('/dashboard');
    })->name('login.store');

    // 2FA Challenge page
    Route::get('/two-factor-challenge', function (Request $request) {
        $pending = $request->session()->get('2fa_pending');

        if (!$pending || empty($pending['user_id'])) {
            return redirect()->route('login');
        }

        if (!empty($pending['expires_at']) && now()->timestamp > (int)$pending['expires_at']) {
            $request->session()->forget(['2fa_pending', '2fa_intended']);
            return redirect()->route('login')->withErrors(['code' => '2FA session expired. Please login again.']);
        }

        return view('auth.two-factor-challenge');
    })->name('2fa.challenge');

    // Verify 2FA Challenge
    Route::post('/two-factor-challenge', function (Request $request) {
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
            'remember_device' => ['nullable'],
        ]);

        $code = trim((string)($data['code'] ?? ''));
        $recovery = trim((string)($data['recovery_code'] ?? ''));

        if ($code === '' && $recovery === '') {
            return back()->withErrors(['code' => 'Please enter a 2FA code or a recovery code.']);
        }

        /** @var \App\Models\User|null $user */
        $user = User::find((int)$pending['user_id']);
        if (!$user || !method_exists($user, 'hasTwoFactorEnabled') || !$user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa_pending', '2fa_intended']);
            return redirect()->route('login')->withErrors(['code' => '2FA could not be verified. Please login again.']);
        }

        $ok = false;

        // Verify TOTP
        if ($code !== '') {
            $secret = method_exists($user, 'getTwoFactorSecretPlain') ? $user->getTwoFactorSecretPlain() : null;

            if (!$secret) {
                return back()->withErrors(['code' => '2FA secret missing on account.']);
            }

            $ok = Totp::verifyCode($secret, $code);
            if (!$ok) {
                return back()->withErrors(['code' => 'Invalid authentication code.']);
            }
        }

        // Verify recovery code (consume it)
        if (!$ok && $recovery !== '') {
            $needle = strtoupper($recovery);

            $codes = $user->two_factor_recovery_codes ?? [];
            $upper = array_map('strtoupper', $codes);

            $idx = array_search($needle, $upper, true);
            if ($idx === false) {
                return back()->withErrors(['recovery_code' => 'Invalid recovery code.']);
            }

            unset($codes[$idx]);
            $user->two_factor_recovery_codes = array_values($codes);
            $user->save();

            $ok = true;
        }

        if (!$ok) {
            return back()->withErrors(['code' => '2FA verification failed.']);
        }

        // Complete login (remember only after 2FA)
        $remember = !empty($pending['remember']);
        Auth::loginUsingId($user->id, $remember);
        $request->session()->regenerate();

        $request->session()->forget(['2fa_pending', '2fa_intended']);

        Audit::log('auth', '2fa.challenge.pass', 'user', $user->id);

        // Remember device (skip 2FA next time)
        $rememberDevice = !empty($data['remember_device']);
        $cookie = null;

        if ($rememberDevice) {
            $token = Str::random(64);
            $tokenHash = hash('sha256', $token);

            $device = TwoFactorTrustedDevice::create([
                'user_id' => $user->id,
                'token_hash' => $tokenHash,
                'user_agent_hash' => hash('sha256', (string)$request->userAgent()),
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
                'expires_at' => now()->addDays(TRUSTED_2FA_DAYS),
            ]);

            // cookie value: "deviceId|token"
            $cookieValue = $device->id . '|' . $token;
            $cookie = trustedDeviceCookie($request, $cookieValue, TRUSTED_2FA_DAYS);

            Audit::log('auth', '2fa.trusted_device.create', 'user', $user->id, ['device_id' => $device->id]);
        }

        $intended = $request->session()->pull('2fa_intended', url('/dashboard'));
        $response = redirect()->to($intended);

        if ($cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    })->name('2fa.verify');

    // Cancel 2FA
    Route::post('/two-factor-challenge/cancel', function (Request $request) {
        $request->session()->forget(['2fa_pending', '2fa_intended']);
        return redirect()->route('login');
    })->name('2fa.cancel');

    // Forgot/reset password routes can stay here — they will work once you add those views.
    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');

    Route::post('/forgot-password', function (Request $request) {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.email');

    Route::get('/reset-password/{token}', function (string $token) {
        return view('auth.reset-password', ['token' => $token]);
    })->name('password.reset');

    Route::post('/reset-password', function (Request $request) {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function (Request $request) {
        Audit::log('auth', 'logout', 'user', Auth::id());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // NOTE: we do NOT clear the trusted-device cookie on logout.
        // "Remember device" is meant to persist across logouts.
        return redirect('/login');
    })->name('logout');
});
