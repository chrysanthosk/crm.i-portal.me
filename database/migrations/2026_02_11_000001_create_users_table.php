<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('pending_email')->nullable();
            $table->string('pending_email_token', 64)->nullable();
            $table->timestamp('pending_email_requested_at')->nullable();
            $table->string('role', 20)->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false)->index();
            $table->string('theme', 10)->default('light');
            $table->rememberToken();
            $table->timestamps();

            $table->index('pending_email_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
