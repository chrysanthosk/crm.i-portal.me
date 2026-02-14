<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsFailure extends Model
{
    public $timestamps = false;

    protected $table = 'sms_failures';

    protected $fillable = [
        'mobile',
        'provider',
        'error_message',
        'failed_at',
    ];

    protected $casts = [
        'failed_at' => 'datetime',
    ];
}
