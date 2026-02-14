<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    public $timestamps = false;

    protected $table = 'sms_settings';

    protected $fillable = [
        'provider_id',
        'api_key',
        'api_secret',
        'sender_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(SmsProvider::class, 'provider_id');
    }
}
