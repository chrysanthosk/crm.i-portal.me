<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('z_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('report_number')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('z_reports');
    }
};
