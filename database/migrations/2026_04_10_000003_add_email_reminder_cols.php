<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('email_confirmation_sent_at')->nullable()->after('sms_last_error');
            $table->dateTime('email_reminder_sent_at')->nullable()->after('email_confirmation_sent_at');
        });

        Schema::table('dashboard_settings', function (Blueprint $table) {
            $table->boolean('email_appointments_enabled')->default(true)->after('sms_sent_birthdays_count');
            $table->text('email_appointments_reminder_message')->nullable()->after('email_appointments_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['email_confirmation_sent_at', 'email_reminder_sent_at']);
        });

        Schema::table('dashboard_settings', function (Blueprint $table) {
            $table->dropColumn(['email_appointments_enabled', 'email_appointments_reminder_message']);
        });
    }
};
