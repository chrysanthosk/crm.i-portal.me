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

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class)->orderByDesc('start_at');
    }

    public function loyalty()
    {
        return $this->hasOne(ClientLoyalty::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->orderByDesc('created_at');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
