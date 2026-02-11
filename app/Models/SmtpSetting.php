<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $table = 'smtp_settings';

    protected $fillable = [
        'enabled',
        'host',
        'port',
        'encryption',
        'username',
        'password_enc',
        'from_address',
        'from_name',
        'last_tested_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_tested_at' => 'datetime',
    ];
}
