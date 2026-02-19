<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('staff_id');

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);

            $table->timestamps();

            $table->index('sale_id');
            $table->index('service_id');
            $table->index('staff_id');

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->foreign('staff_id')->references('id')->on('staff')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_services');
    }
};
