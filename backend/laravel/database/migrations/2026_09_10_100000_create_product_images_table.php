<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->string('file_path');
            $table->string('alt_text', 500)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->unsignedBigInteger('is_primary_guard')->nullable()->storedAs('CASE WHEN `is_primary` = 1 THEN `product_id` ELSE NULL END');

            $table->timestamps();

            $table->unique('is_primary_guard', 'product_images_unique_primary_per_product');
            $table->index(['product_id', 'sort_order', 'id']);
            $table->index(['product_id', 'is_primary']);
            $table->index(['product_variant_id', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
