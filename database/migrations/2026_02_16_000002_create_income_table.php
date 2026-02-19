<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('income', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('cash', 10, 2)->default(0);
            $table->decimal('revolut', 10, 2)->default(0);
            $table->decimal('visa', 10, 2)->default(0);
            $table->decimal('other', 10, 2)->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            // no created_at in your legacy schema; keep it simple & compatible
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income');
    }
};
