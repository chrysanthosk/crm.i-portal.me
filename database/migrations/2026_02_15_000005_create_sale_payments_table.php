<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->decimal('amount', 10, 2)->default(0);

            $table->timestamps();

            $table->index('sale_id');
            $table->index('payment_method_id');

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
