<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // nullable user_id
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            $table->string('mobile', 20)->nullable();
            $table->date('dob')->nullable();

            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete()->cascadeOnUpdate();

            $table->char('color', 7)->default('#000000');
            $table->boolean('show_in_calendar')->default(true);
            $table->integer('position')->default(0);
            $table->decimal('annual_leave_days', 4, 1)->default(0.0);

            $table->timestamps();

            // Optional helpful indexes
            $table->index('role_id', 'idx_staff_role');
            $table->index('user_id', 'idx_staff_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
