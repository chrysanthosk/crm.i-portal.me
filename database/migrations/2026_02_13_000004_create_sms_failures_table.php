<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_failures', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20);
            $table->string('provider', 50);
            $table->text('error_message');
            $table->timestamp('failed_at')->useCurrent();

            $table->index('mobile');
            $table->index('provider');
            $table->index('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_failures');
    }
};
