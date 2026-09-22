<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_inventory_allocations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('product_stock_id')
                ->constrained('product_stocks')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->unique(
                ['order_item_id', 'product_stock_id'],
                'order_item_stock_allocation_unique'
            );
            $table->index('product_stock_id', 'allocation_product_stock_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_inventory_allocations');
    }
};
