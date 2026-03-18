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

    protected ?Role $resolvedRoleModel = null;
    protected ?array $resolvedPermissionKeys = null;

    public function getTwoFactorRecoveryCodesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

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
            return $this->two_factor_secret;
        }
    }

    public function setTwoFactorSecretPlain(?string $secret): void
    {
        $this->two_factor_secret = $secret ? Crypt::encryptString($secret) : null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled
            && !empty($this->two_factor_secret)
            && !empty($this->two_factor_confirmed_at);
    }

    public function hasPermission(string $permissionKey): bool
    {
        if (in_array(($this->role ?? null), ['admin', 'owner'], true)) {
            return true;
        }

        $roleKey = $this->role ?? null;
        if (!$roleKey) {
            return false;
        }

        if ($this->resolvedPermissionKeys === null) {
            $this->resolvedRoleModel = Role::query()->where('role_key', $roleKey)->first();

            if (!$this->resolvedRoleModel) {
                $this->resolvedPermissionKeys = [];
                return false;
            }

            $this->resolvedPermissionKeys = $this->resolvedRoleModel
                ->permissions()
                ->pluck('permission_key')
                ->all();
        }

        return in_array($permissionKey, $this->resolvedPermissionKeys, true);
    }

    public function updatePasswordChecked(string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $this->password)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $this->password = Hash::make($newPassword);
        $this->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
