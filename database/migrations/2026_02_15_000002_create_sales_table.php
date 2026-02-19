<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();

            $table->decimal('services_subtotal', 10, 2)->default(0);
            $table->decimal('services_vat', 10, 2)->default(0);

            $table->decimal('products_subtotal', 10, 2)->default(0);
            $table->decimal('products_vat', 10, 2)->default(0);

            $table->decimal('total_vat', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            $table->timestamps();

            $table->index('appointment_id');
            $table->index('client_id');

            // Optional FK constraints (safe if your tables exist)
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
