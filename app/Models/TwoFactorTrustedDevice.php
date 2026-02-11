<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorTrustedDevice extends Model
{
    protected $table = 'two_factor_trusted_devices';

    protected $fillable = [
        'user_id',
        'token_hash',
        'user_agent_hash',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
