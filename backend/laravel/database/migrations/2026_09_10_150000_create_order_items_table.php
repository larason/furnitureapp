<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->string('sku');
            $table->string('name');
            $table->string('variant_name')->nullable();

            $table->unsignedBigInteger('unit_price_amount');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_amount');

            $table->timestamps();

            $table->index(['order_id', 'id']);
            $table->index('product_id');
            $table->index('variant_id');
        });

        $this->addPositiveQuantityConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }

    private function addPositiveQuantityConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE order_items
                    ADD CONSTRAINT chk_order_item_quantity_positive
                    CHECK (quantity > 0)
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN (NEW.quantity <= 0)
            BEGIN
                SELECT RAISE(ABORT, 'order item quantity must be greater than zero');
            END
            SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_order_items_quantity_insert
            BEFORE INSERT ON order_items
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_order_items_quantity_update
            BEFORE UPDATE ON order_items
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
