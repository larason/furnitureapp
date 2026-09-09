<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('sku')->unique();
            $table->string('variant_name');

            $table->unsignedBigInteger('price_amount');
            $table->char('price_currency', 3)->default('TZS');

            $table->unsignedBigInteger('compare_at_price_amount')->nullable();
            $table->char('compare_at_price_currency', 3)->nullable();

            $table->unsignedBigInteger('cost_price_amount')->nullable();
            $table->char('cost_price_currency', 3)->nullable();

            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->decimal('depth_cm', 10, 2)->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();

            $table->json('attributes')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'is_active', 'display_order']);
            $table->index(['product_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
