<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $table = 'income';
    public $timestamps = false;

    protected $fillable = [
        'date',
        'cash',
        'revolut',
        'visa',
        'other',
    ];

    protected $casts = [
        'date' => 'date',
        'cash' => 'decimal:2',
        'revolut' => 'decimal:2',
        'visa' => 'decimal:2',
        'other' => 'decimal:2',
    ];
}
