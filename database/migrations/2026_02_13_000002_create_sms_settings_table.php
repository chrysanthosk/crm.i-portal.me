<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('provider_id')->unique();
            $table->text('api_key');
            $table->text('api_secret')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('provider_id')
                ->references('id')->on('sms_providers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_settings');
    }
};
