<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_date',
        'first_name',
        'last_name',
        'dob',
        'mobile',
        'notes',
        'email',
        'address',
        'city',
        'gender',
        'comments',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'dob' => 'date',
    ];
}
