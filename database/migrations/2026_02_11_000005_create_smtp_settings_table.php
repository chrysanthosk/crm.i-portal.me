<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption', 10)->nullable(); // tls|ssl|null
            $table->string('username')->nullable();
            $table->text('password_enc')->nullable(); // encrypted
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }
};
