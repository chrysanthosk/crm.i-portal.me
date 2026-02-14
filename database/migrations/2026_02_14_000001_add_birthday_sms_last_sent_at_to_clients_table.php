<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'birthday_sms_last_sent_at')) {
                $table->dateTime('birthday_sms_last_sent_at')->nullable()->after('date_of_birth');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'birthday_sms_last_sent_at')) {
                $table->dropColumn('birthday_sms_last_sent_at');
            }
        });
    }
};
