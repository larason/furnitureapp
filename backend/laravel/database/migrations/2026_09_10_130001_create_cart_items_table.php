<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->unsignedBigInteger('identity_guard')->nullable()->storedAs('CASE WHEN `variant_id` IS NULL THEN `cart_id` * 1000000 + `product_id` ELSE `cart_id` * 1000000 + `variant_id` END');

            $table->timestamps();

            $table->unique('identity_guard', 'cart_items_unique_identity_per_cart');

            $table->index(['cart_id', 'product_id']);
            $table->index(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
