<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->decimal('vat_percent', 5, 2)->default(0); // e.g. 19.00
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_types');
    }
};
