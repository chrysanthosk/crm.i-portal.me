<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('category_id');
            $table->string('name', 150);

            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->unsignedBigInteger('purchase_vat_type_id');

            $table->decimal('sell_price', 10, 2)->default(0);
            $table->unsignedBigInteger('sell_vat_type_id');

            $table->integer('quantity_stock')->default(0);
            $table->unsignedInteger('quantity_in_box')->default(1);

            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('purchase_vat_type_id');
            $table->index('sell_vat_type_id');

            // Optional but recommended to prevent duplicates inside a category
            $table->unique(['category_id', 'name'], 'uniq_product_category_name');

            $table->foreign('category_id')
                ->references('id')->on('product_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('purchase_vat_type_id')
                ->references('id')->on('vat_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('sell_vat_type_id')
                ->references('id')->on('vat_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
