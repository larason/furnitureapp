<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->string('warehouse_location');

            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);

            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'warehouse_location'],
                'product_stock_variant_location_unique'
            );
        });

        $this->addReservedWithinQuantityConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }

    private function addReservedWithinQuantityConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE product_stocks
                    ADD CONSTRAINT chk_reserved_within_quantity
                    CHECK (reserved_quantity <= quantity)
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN (
                NEW.reserved_quantity > NEW.quantity
                OR NEW.reserved_quantity < 0
                OR NEW.quantity < 0
            )
            BEGIN
                SELECT RAISE(ABORT, 'reserved_quantity must not exceed quantity and quantities must not be negative');
            END
        SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_product_stocks_reserved_insert
            BEFORE INSERT ON product_stocks
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_product_stocks_reserved_update
            BEFORE UPDATE ON product_stocks
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
