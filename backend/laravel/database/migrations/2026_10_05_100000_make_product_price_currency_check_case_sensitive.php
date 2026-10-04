<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'chk_products_price_currency';

    private const CURRENCY = 'TZS';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('products')->whereRaw('BINARY price_currency <> ?', [self::CURRENCY])->exists()) {
            throw new RuntimeException('Product price currency migration blocker. Reason: existing product price currency must be TZS.');
        }

        $this->replaceConstraint("price_amount >= 0 AND BINARY price_currency = '".self::CURRENCY."'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->replaceConstraint("price_amount >= 0 AND price_currency = '".self::CURRENCY."'");
    }

    private function replaceConstraint(string $definition): void
    {
        try {
            DB::statement('ALTER TABLE products DROP CHECK '.self::CONSTRAINT.', ADD CONSTRAINT '.self::CONSTRAINT." CHECK ({$definition})");
        } catch (QueryException) {
            DB::statement('ALTER TABLE products DROP CONSTRAINT '.self::CONSTRAINT.', ADD CONSTRAINT '.self::CONSTRAINT." CHECK ({$definition})");
        }
    }
};
