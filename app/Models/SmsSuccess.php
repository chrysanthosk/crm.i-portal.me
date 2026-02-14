<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSuccess extends Model
{
    public $timestamps = false;

    protected $table = 'sms_success';

    protected $fillable = [
        'mobile',
        'provider',
        'success_code',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
