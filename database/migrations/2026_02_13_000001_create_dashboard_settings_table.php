<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_settings', function (Blueprint $table) {
            $table->id();

            $table->string('dashboard_name', 255)->default('');
            $table->string('company_name', 255)->default('');
            $table->string('company_vat_number', 50)->nullable();
            $table->string('company_phone_number', 50)->nullable();
            $table->text('company_address')->nullable();

            $table->boolean('sms_appointments_enabled')->default(false);
            $table->string('sms_appointments_message', 165)->nullable();

            $table->boolean('sms_birthdays_enabled')->default(false);
            $table->string('sms_birthdays_message', 165)->nullable();

            $table->unsignedInteger('sms_sent_appointments_count')->default(0);
            $table->unsignedInteger('sms_sent_birthdays_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_settings');
    }
};
