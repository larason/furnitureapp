<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('order_reference', 8)->unique();

            $table->string('status')->default('PENDING_PAYMENT');
            $table->string('fulfillment_type', 20);
            $table->string('delivery_fee_status', 20);

            $table->char('currency', 3)->default('TZS');

            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('delivery_fee_amount')->nullable();
            $table->unsignedBigInteger('total_amount')->nullable();

            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_phone', 50)->nullable();

            $table->json('delivery_address')->nullable();

            $table->timestamps();

            $table->index([
                'customer_id',
                'created_at',
            ]);

            $table->index([
                'status',
                'created_at',
            ]);

            $table->index([
                'fulfillment_type',
                'status',
            ]);

            $table->index([
                'delivery_fee_status',
                'status',
            ]);
        });

        $this->addNonNegativeAmountsConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }

    private function addNonNegativeAmountsConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE orders
                    ADD CONSTRAINT chk_order_amounts_non_negative
                    CHECK (
                        subtotal_amount >= 0
                        AND (delivery_fee_amount IS NULL OR delivery_fee_amount >= 0)
                        AND (total_amount IS NULL OR total_amount >= 0)
                    )
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN (
                NEW.subtotal_amount < 0
                OR (NEW.delivery_fee_amount IS NOT NULL AND NEW.delivery_fee_amount < 0)
                OR (NEW.total_amount IS NOT NULL AND NEW.total_amount < 0)
            )
            BEGIN
                SELECT RAISE(ABORT, 'order monetary amounts must not be negative');
            END
            SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_orders_amounts_insert
            BEFORE INSERT ON orders
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_orders_amounts_update
            BEFORE UPDATE ON orders
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
