<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('is_default_guard')->nullable()->storedAs('CASE WHEN `is_default` = 1 THEN `product_id` ELSE NULL END');
            $table->unique('is_default_guard', 'product_variants_unique_default_per_product');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_unique_default_per_product');
            $table->dropColumn('is_default_guard');
        });
    }
};
