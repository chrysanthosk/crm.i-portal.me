<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // SQLite: "after" is ignored, safe to include
            if (!Schema::hasColumn('permissions', 'permission_name')) {
                $table->string('permission_name', 190)->nullable();
            }

            if (!Schema::hasColumn('permissions', 'permission_group')) {
                $table->string('permission_group', 190)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'permission_name')) {
                $table->dropColumn('permission_name');
            }
            if (Schema::hasColumn('permissions', 'permission_group')) {
                $table->dropColumn('permission_group');
            }
        });
    }
};
