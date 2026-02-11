<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    // CRITICAL: your value is JSON in DB; cast it to array automatically
    protected $casts = [
        'value' => 'array',
    ];
}
