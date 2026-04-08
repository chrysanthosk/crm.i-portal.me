<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip on SQLite (used in testing) — FK pragma enforcement varies
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('voided_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
        });
    }
};
