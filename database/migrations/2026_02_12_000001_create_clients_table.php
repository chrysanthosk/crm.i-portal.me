<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Business registration date (keep explicit like your schema)
            $table->dateTime('registration_date')->useCurrent();

            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // DOB: in real life you might want nullable, but keeping your schema
            $table->date('dob');

            $table->string('mobile', 20);
            $table->string('email', 150);

            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();

            // SQLite doesn't support enum natively; use string with validation
            $table->string('gender', 10); // Male/Female/Other

            $table->text('notes')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            // Keep your uniqueness rule (works in SQLite/MySQL)
            $table->unique(['first_name', 'last_name', 'dob', 'mobile'], 'uniq_client_person');

            // Helpful indexes for searching
            $table->index(['last_name', 'first_name'], 'idx_clients_name');
            $table->index('email', 'idx_clients_email');
            $table->index('mobile', 'idx_clients_mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
