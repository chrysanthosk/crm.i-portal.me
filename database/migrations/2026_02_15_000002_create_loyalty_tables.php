<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->integer('points_min')->default(0);
            $table->string('benefits', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('client_loyalty', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->primary();
            $table->integer('points_balance')->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->integer('change');
            $table->string('reason', 100);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('value', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('client_loyalty');
        Schema::dropIfExists('loyalty_tiers');
    }
};
