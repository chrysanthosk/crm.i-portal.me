<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);
            $table->string('type', 100);

            $table->string('mobile', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['name']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
