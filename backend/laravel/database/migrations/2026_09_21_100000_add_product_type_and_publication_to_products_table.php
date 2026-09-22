<?php

use App\Support\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('product_type', array_column(ProductType::cases(), 'value'))->default(ProductType::IN_STOCK->value);
            $table->boolean('is_published')->default(false);
            $table->index(['is_active', 'is_published', 'product_type']);
        });

        DB::table('products')->update(['product_type' => ProductType::IN_STOCK->value]);
        DB::table('products')->where('is_active', true)->update(['is_published' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_is_active_is_published_product_type_index');
            $table->dropColumn(['product_type', 'is_published']);
        });
    }
};
