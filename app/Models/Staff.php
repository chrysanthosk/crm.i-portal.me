<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'mobile',
        'dob',
        'role_id',
        'color',
        'show_in_calendar',
        'position',
        'annual_leave_days',
    ];

    protected $casts = [
        'dob' => 'date',
        'show_in_calendar' => 'boolean',
        'annual_leave_days' => 'decimal:1',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
