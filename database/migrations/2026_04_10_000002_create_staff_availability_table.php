<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per staff per day-of-week (0=Monday … 6=Sunday)
        Schema::create('staff_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0=Mon, 1=Tue … 6=Sun
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('18:00:00');
            $table->boolean('is_day_off')->default(false);
            $table->timestamps();

            $table->unique(['staff_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_availability');
    }
};
