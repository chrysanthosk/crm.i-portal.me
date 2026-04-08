<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['client_id', 'start_at'], 'idx_appointments_client_start');
            $table->index(['staff_id', 'start_at'], 'idx_appointments_staff_start');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_client_start');
            $table->dropIndex('idx_appointments_staff_start');
        });
    }
};
