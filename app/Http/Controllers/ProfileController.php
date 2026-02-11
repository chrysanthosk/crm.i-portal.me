<?php

namespace App\Http\Controllers;

use App\Mail\PendingEmailConfirmationMail;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $user->first_name = $data['first_name'] ?? null;
        $user->last_name  = $data['last_name'] ?? null;
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $user->name = $name !== '' ? $name : $user->email;
        $user->save();

        Audit::log('profile', 'profile.update', 'user', $user->id);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        Audit::log('profile', 'password.change', 'user', $user->id);

        return back()->with('status', 'Password updated.');
    }

    public function requestEmailChange(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $newEmail = $data['email'];
        $tokenRaw = bin2hex(random_bytes(32)); // 64 chars
        $tokenHash = hash('sha256', $tokenRaw); // 64 chars

        $user->pending_email = $newEmail;
        $user->pending_email_token = $tokenHash;
        $user->pending_email_requested_at = now();
        $user->save();

        $confirmUrl = route('profile.email.confirm', ['token' => $tokenRaw]);

        try {
            Mail::to($newEmail)->send(new PendingEmailConfirmationMail($confirmUrl));
            Audit::log('profile', 'email.change.request', 'user', $user->id, ['to' => $newEmail]);

            return back()->with('status', 'Confirmation email sent to the new address.');
        } catch (\Throwable $e) {
            Audit::log('profile', 'email.change.request.fail', 'user', $user->id, ['to' => $newEmail, 'error' => $e->getMessage()]);
            return back()->withErrors(['email' => 'Could not send confirmation email: ' . $e->getMessage()]);
        }
    }

    public function confirmEmailChange(Request $request, string $token)
    {
        $user = $request->user();

        $tokenHash = hash('sha256', $token);
        if (empty($user->pending_email) || empty($user->pending_email_token) || !hash_equals($user->pending_email_token, $tokenHash)) {
            abort(403, 'Invalid or expired email change token.');
        }

        $old = $user->email;
        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->pending_email_token = null;
        $user->pending_email_requested_at = null;
        $user->email_verified_at = now();
        $user->save();

        Audit::log('profile', 'email.change.confirm', 'user', $user->id, ['from' => $old, 'to' => $user->email]);

        return redirect()->route('profile.edit')->with('status', 'Email updated successfully.');
    }
}
