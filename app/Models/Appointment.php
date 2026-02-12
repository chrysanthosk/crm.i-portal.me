<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'start_at',
        'end_at',
        'staff_id',
        'client_id',
        'client_name',
        'client_phone',
        'service_id',
        'status',
        'notes',
        'internal_notes',
        'send_sms',
        'reminder_at',
        'sms_attempts',
        'sms_sent_success',
        'sms_send_failed',
        'sms_sent_at',
        'sms_failed_at',
        'sms_provider',
        'sms_provider_message_id',
        'sms_last_error',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reminder_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'sms_failed_at' => 'datetime',
        'send_sms' => 'boolean',
        'sms_sent_success' => 'boolean',
        'sms_send_failed' => 'boolean',
        'sms_attempts' => 'integer',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getClientDisplayNameAttribute(): string
    {
        if ($this->client) {
            return trim(($this->client->first_name ?? '') . ' ' . ($this->client->last_name ?? '')) ?: ($this->client->email ?? 'Client');
        }
        return $this->client_name ?: 'Client';
    }
}
