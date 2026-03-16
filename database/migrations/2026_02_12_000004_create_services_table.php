<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->unsignedBigInteger('category_id');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedBigInteger('vat_type_id');

            $table->unsignedInteger('duration')->default(0);
            $table->unsignedInteger('waiting')->default(0);

            // SQLite has no native ENUM; store as string + validate in app
            $table->string('gender', 10)->default('Both'); // Male|Female|Both

            $table->text('comment')->nullable();

            $table->timestamps();

            // Equivalent of your MySQL UNIQUE KEY (works in SQLite)
            $table->unique(['name', 'category_id', 'gender'], 'uniq_service_name_category_gender');

            // Foreign keys (SQLite supports them; must be enabled, Laravel does)
            $table->foreign('category_id')
                ->references('id')->on('service_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('vat_type_id')
                ->references('id')->on('vat_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
