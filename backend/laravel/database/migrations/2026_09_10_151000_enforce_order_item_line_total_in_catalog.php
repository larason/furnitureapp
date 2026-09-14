<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoExistingViolations();
        $this->addLineTotalConstraint();
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->dropCheck('order_items', 'chk_order_item_line_total_consistent');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_order_items_line_total_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_order_items_line_total_update');
        }
    }

    private function dropCheck(string $table, string $name): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
        } catch (QueryException) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
    }

    private function assertNoExistingViolations(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        $exists = DB::table('order_items')
            ->whereRaw('line_total_amount <> unit_price_amount * quantity')
            ->exists();

        if ($exists) {
            throw new RuntimeException('Cannot enforce line-total invariant: existing order_items rows violate line_total = unit_price * quantity.');
        }
    }

    private function addLineTotalConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE order_items
                    ADD CONSTRAINT chk_order_item_line_total_consistent
                    CHECK (line_total_amount = unit_price_amount * quantity)
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN (NEW.line_total_amount <> NEW.unit_price_amount * NEW.quantity)
            BEGIN
                SELECT RAISE(ABORT, 'order item line total must equal unit price multiplied by quantity');
            END
            SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_order_items_line_total_insert
            BEFORE INSERT ON order_items
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_order_items_line_total_update
            BEFORE UPDATE ON order_items
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
