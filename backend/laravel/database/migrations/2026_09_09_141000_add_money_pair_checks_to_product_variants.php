<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE product_variants
                    ADD CONSTRAINT chk_compare_at_price_null_together
                    CHECK (
                        (compare_at_price_amount IS NULL AND compare_at_price_currency IS NULL)
                        OR (compare_at_price_amount IS NOT NULL AND compare_at_price_currency IS NOT NULL)
                    ),
                    ADD CONSTRAINT chk_cost_price_null_together
                    CHECK (
                        (cost_price_amount IS NULL AND cost_price_currency IS NULL)
                        OR (cost_price_amount IS NOT NULL AND cost_price_currency IS NOT NULL)
                    )
                SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_product_variants_money_pair_insert
                BEFORE INSERT ON product_variants
                FOR EACH ROW
                WHEN NOT (
                    ((NEW.compare_at_price_amount IS NULL AND NEW.compare_at_price_currency IS NULL) OR (NEW.compare_at_price_amount IS NOT NULL AND NEW.compare_at_price_currency IS NOT NULL))
                    AND ((NEW.cost_price_amount IS NULL AND NEW.cost_price_currency IS NULL) OR (NEW.cost_price_amount IS NOT NULL AND NEW.cost_price_currency IS NOT NULL))
                )
                BEGIN
                    SELECT RAISE(ABORT, 'money pair must be both NULL or both NOT NULL');
                END
                SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_product_variants_money_pair_update
                BEFORE UPDATE ON product_variants
                FOR EACH ROW
                WHEN NOT (
                    ((NEW.compare_at_price_amount IS NULL AND NEW.compare_at_price_currency IS NULL) OR (NEW.compare_at_price_amount IS NOT NULL AND NEW.compare_at_price_currency IS NOT NULL))
                    AND ((NEW.cost_price_amount IS NULL AND NEW.cost_price_currency IS NULL) OR (NEW.cost_price_amount IS NOT NULL AND NEW.cost_price_currency IS NOT NULL))
                )
                BEGIN
                    SELECT RAISE(ABORT, 'money pair must be both NULL or both NOT NULL');
                END
                SQL);

            return;
        }

        // Fallback for other drivers (e.g., pgsql) – use CHECK constraints
        DB::statement(<<<'SQL'
            ALTER TABLE product_variants
                ADD CONSTRAINT chk_compare_at_price_null_together
                CHECK (
                    (compare_at_price_amount IS NULL AND compare_at_price_currency IS NULL)
                    OR (compare_at_price_amount IS NOT NULL AND compare_at_price_currency IS NOT NULL)
                )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE product_variants
                ADD CONSTRAINT chk_cost_price_null_together
                CHECK (
                    (cost_price_amount IS NULL AND cost_price_currency IS NULL)
                    OR (cost_price_amount IS NOT NULL AND cost_price_currency IS NOT NULL)
                )
            SQL);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE product_variants DROP CHECK chk_compare_at_price_null_together');
            DB::statement('ALTER TABLE product_variants DROP CHECK chk_cost_price_null_together');

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_product_variants_money_pair_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_product_variants_money_pair_update');

            return;
        }

        try {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT chk_compare_at_price_null_together');
        } catch (Throwable) {
        }
        try {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT chk_cost_price_null_together');
        } catch (Throwable) {
        }
    }
};
