<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_identity_per_cart');
            $table->dropColumn('identity_guard');

            $table->unsignedBigInteger('null_variant_guard')->nullable()->storedAs('CASE WHEN `variant_id` IS NULL THEN `product_id` ELSE NULL END');

            $table->unique(['cart_id', 'product_id', 'variant_id'], 'cart_items_unique_identity');
            $table->unique(['cart_id', 'null_variant_guard'], 'cart_items_unique_null_variant_per_cart');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_identity');
            $table->dropUnique('cart_items_unique_null_variant_per_cart');
            $table->dropColumn('null_variant_guard');

            $table->unsignedBigInteger('identity_guard')->nullable()->storedAs('CASE WHEN `variant_id` IS NULL THEN `cart_id` * 1000000 + `product_id` ELSE `cart_id` * 1000000 + `variant_id` END');

            $table->unique('identity_guard', 'cart_items_unique_identity_per_cart');
        });
    }
};
