<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsProvider extends Model
{
    public $timestamps = false;

    protected $table = 'sms_providers';

    protected $fillable = [
        'name',
        'doc_url',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function setting()
    {
        return $this->hasOne(SmsSetting::class, 'provider_id');
    }
}
