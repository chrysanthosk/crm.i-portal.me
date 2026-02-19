<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('sales', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            }
            if (!Schema::hasColumn('sales', 'void_reason')) {
                $table->string('void_reason', 255)->nullable()->after('voided_by');
            }

            // Optional FK (safe for sqlite dev; if it fails you can remove it)
            // $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'void_reason')) $table->dropColumn('void_reason');
            if (Schema::hasColumn('sales', 'voided_by'))  $table->dropColumn('voided_by');
            if (Schema::hasColumn('sales', 'voided_at'))  $table->dropColumn('voided_at');
        });
    }
};
