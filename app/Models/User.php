<?php

namespace App\Models;

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

    public function getTwoFactorRecoveryCodesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setTwoFactorRecoveryCodesAttribute($value): void
    {
        $this->attributes['two_factor_recovery_codes'] = json_encode($value ?? [], JSON_UNESCAPED_SLASHES);
    }

    public function getTwoFactorSecretPlain(): ?string
    {
        if (empty($this->two_factor_secret)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            // if it was stored unencrypted
            return $this->two_factor_secret;
        }
    }

    public function setTwoFactorSecretPlain(?string $secret): void
    {
        $this->two_factor_secret = $secret ? Crypt::encryptString($secret) : null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool)$this->two_factor_enabled && !empty($this->two_factor_secret) && !empty($this->two_factor_confirmed_at);
    }

    public function hasPermission(string $permissionKey): bool
    {
        // Admin role has everything
        if ($this->role === 'admin') {
            return true;
        }

        // Find permissions for the user's role_key
        $role = Role::query()->where('role_key', $this->role)->first();
        if (!$role) {
            return false;
        }

        return $role->permissions()->where('permission_key', $permissionKey)->exists();
    }

    public function updatePasswordChecked(string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $this->password)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $this->password = Hash::make($newPassword);
        $this->save();
    }
}
