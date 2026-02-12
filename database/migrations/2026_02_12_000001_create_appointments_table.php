<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Core time window (Option B)
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Who is assigned
            $table->unsignedBigInteger('staff_id');
            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();

            // Existing client OR ad-hoc client
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();

            $table->string('client_name', 200)->nullable();
            $table->string('client_phone', 20)->nullable();

            // Service booked
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();

            // Status / notes
            $table->enum('status', ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'])
                ->default('scheduled');

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // SMS / reminders
            $table->boolean('send_sms')->default(false);
            $table->dateTime('reminder_at')->nullable();

            $table->unsignedInteger('sms_attempts')->default(0);
            $table->boolean('sms_sent_success')->default(false);
            $table->boolean('sms_send_failed')->default(false);
            $table->dateTime('sms_sent_at')->nullable();
            $table->dateTime('sms_failed_at')->nullable();
            $table->string('sms_provider', 50)->nullable();
            $table->string('sms_provider_message_id', 100)->nullable();
            $table->text('sms_last_error')->nullable();

            // Auditing (optional)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Indexes for performance + overlap checks
            $table->index(['staff_id', 'start_at', 'end_at'], 'idx_appt_staff_time');
            $table->index(['client_id'], 'idx_appt_client');
            $table->index(['service_id'], 'idx_appt_service');
            $table->index(['status', 'start_at'], 'idx_appt_status_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
