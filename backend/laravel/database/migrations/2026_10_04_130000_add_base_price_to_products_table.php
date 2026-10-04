<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CURRENCY = 'TZS';

    public function up(): void
    {
        $this->assertEveryProductHasCanonicalPrice();

        Schema::table('products', function ($table): void {
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->char('price_currency', 3)->nullable();
        });

        $this->backfillPrices();
        $this->assertNoMissingPrices();

        Schema::table('products', function ($table): void {
            $table->unsignedBigInteger('price_amount')->nullable(false)->change();
            $table->char('price_currency', 3)->nullable(false)->change();
        });

        $this->addPriceConstraints();
    }

    public function down(): void
    {
        if (DB::table('products')->exists()) {
            throw new RuntimeException('Product base price persistence cannot be rolled back while Products exist.');
        }

        $this->dropPriceConstraints();

        Schema::table('products', function ($table): void {
            $table->dropColumn(['price_amount', 'price_currency']);
        });
    }

    private function assertEveryProductHasCanonicalPrice(): void
    {
        DB::table('products')->select('id')->orderBy('id')->eachById(function (object $product): void {
            $price = DB::table('product_variants')
                ->select('price_amount', 'price_currency')
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('price_amount')
                ->orderBy('id')
                ->first();
            $identifier = 'prod_'.base_convert((string) $product->id, 10, 36);

            if ($price === null) {
                throw new RuntimeException("Product price migration blocker. Product: {$identifier}. Reason: no deterministic existing canonical price source.");
            }

            if ($price->price_amount === null || $price->price_amount < 0) {
                throw new RuntimeException("Product price migration blocker. Product: {$identifier}. Reason: selected Variant price amount is invalid.");
            }

            if ($price->price_currency !== self::CURRENCY) {
                throw new RuntimeException("Product price migration blocker. Product: {$identifier}. Reason: selected Variant price currency must be TZS.");
            }
        });
    }

    private function backfillPrices(): void
    {
        DB::table('products')->select('id')->orderBy('id')->eachById(function (object $product): void {
            $price = DB::table('product_variants')
                ->select('price_amount', 'price_currency')
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->orderBy('price_amount')
                ->orderBy('id')
                ->first();

            DB::table('products')->where('id', $product->id)->update([
                'price_amount' => $price->price_amount,
                'price_currency' => $price->price_currency,
            ]);
        });
    }

    private function assertNoMissingPrices(): void
    {
        if (DB::table('products')->whereNull('price_amount')->orWhereNull('price_currency')->exists()) {
            throw new RuntimeException('Product price migration blocker. Reason: Product base price backfill did not complete.');
        }
    }

    private function addPriceConstraints(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->createSqlitePriceTriggers();

            return;
        }

        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_price_currency CHECK (price_amount >= 0 AND price_currency = '".self::CURRENCY."')");
    }

    private function dropPriceConstraints(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_products_price_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_products_price_update');

            return;
        }

        try {
            DB::statement('ALTER TABLE products DROP CHECK chk_products_price_currency');
        } catch (QueryException) {
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_price_currency');
        }
    }

    private function createSqlitePriceTriggers(): void
    {
        $currency = self::CURRENCY;

        foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $event) {
            DB::statement(<<<SQL
                CREATE TRIGGER trg_products_price_{$suffix}
                BEFORE {$event} ON products
                FOR EACH ROW
                WHEN NEW.price_amount IS NULL
                    OR NEW.price_amount < 0
                    OR NEW.price_currency IS NULL
                    OR NEW.price_currency <> '{$currency}'
                BEGIN
                    SELECT RAISE(ABORT, 'invalid product base price');
                END
                SQL);
        }
    }
};
