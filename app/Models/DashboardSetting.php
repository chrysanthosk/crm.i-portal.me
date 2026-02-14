<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardSetting extends Model
{
    protected $table = 'dashboard_settings';

    protected $fillable = [
        'dashboard_name',
        'company_name',
        'company_vat_number',
        'company_phone_number',
        'company_address',
        'sms_appointments_enabled',
        'sms_appointments_message',
        'sms_birthdays_enabled',
        'sms_birthdays_message',
        'sms_sent_appointments_count',
        'sms_sent_birthdays_count',
    ];

    protected $casts = [
        'sms_appointments_enabled' => 'boolean',
        'sms_birthdays_enabled' => 'boolean',
        'sms_sent_appointments_count' => 'integer',
        'sms_sent_birthdays_count' => 'integer',
    ];
}
