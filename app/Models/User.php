<?php

namespace App\Models;

use App\Models\Role;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'pending_email',
        'pending_email_token',
        'pending_email_requested_at',
        'role',
        'email_verified_at',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_enabled',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'pending_email_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'pending_email_requested_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    /**
     * Keep recovery codes stored as JSON string in DB.
     * Accessor returns array always.
     */
    public function getTwoFactorRecoveryCodesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

        // If it was somehow stored as an array already, return it
        if (is_array($value)) {
            return $value;
        }

        try {
            $decoded = json_decode((string)$value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Mutator: accept array and store JSON.
     */
    public function setTwoFactorRecoveryCodesAttribute($value): void
    {
        $this->attributes['two_factor_recovery_codes'] = json_encode($value ?? [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Return decrypted two_factor_secret (stored encrypted in DB).
     * If older value was stored plain, fall back to raw.
     */
    public function getTwoFactorSecretPlain(): ?string
    {
        if (empty($this->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            return $this->two_factor_secret;
        }
    }

    /**
     * Store two_factor_secret encrypted (or null).
     */
    public function setTwoFactorSecretPlain(?string $secret): void
    {
        $this->two_factor_secret = $secret ? Crypt::encryptString($secret) : null;
    }

    /**
     * True only when enabled flag + secret + confirmed_at are present.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled
            && !empty($this->two_factor_secret)
            && !empty($this->two_factor_confirmed_at);
    }

    /**
     * Permission check:
     * - role === 'admin' => full access
     * - else, resolve Role by role_key and check relationship
     */
    public function hasPermission(string $permissionKey): bool
    {
        if (($this->role ?? null) === 'admin') {
            return true;
        }

        $roleKey = $this->role ?? null;
        if (!$roleKey) {
            return false;
        }

        $role = Role::query()->where('role_key', $roleKey)->first();
        if (!$role) {
            return false;
        }

        return $role->permissions()
            ->where('permission_key', $permissionKey)
            ->exists();
    }

    /**
     * Change password with current-password verification.
     */
    public function updatePasswordChecked(string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $this->password)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $this->password = Hash::make($newPassword);
        $this->save();
    }

    /**
     * Override Laravel default reset password notification
     * so branding matches your system name.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
