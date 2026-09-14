<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->constrainCategories();
        $this->constrainRecommendations();
        $this->constrainCarts();
        $this->constrainCartItems();
        $this->constrainFurnitureRequests();
        $this->constrainProductStocks();
        $this->constrainOrders();
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->dropCheck('categories', 'chk_categories_display_order_non_negative');
            $this->dropCheck('category_recommendations', 'chk_cat_rec_no_self_recommendation');
            $this->dropCheck('carts', 'chk_carts_status_closed');
            $this->dropCheck('cart_items', 'chk_cart_items_quantity_bounds');
            $this->dropCheck('furniture_requests', 'chk_furniture_requests_quantity_bounds');
            $this->dropCheck('product_stocks', 'chk_product_stocks_location_non_empty');
            $this->dropCheck('orders', 'chk_orders_fee_consistency');

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        foreach ($this->sqliteTriggers() as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function constrainCategories(): void
    {
        $this->addGuard(
            'categories',
            'chk_categories_display_order_non_negative',
            'display_order >= 0',
            'NEW.display_order < 0',
            'category display_order must not be negative'
        );
    }

    private function constrainRecommendations(): void
    {
        $this->addGuard(
            'category_recommendations',
            'chk_cat_rec_no_self_recommendation',
            'category_id <> recommended_category_id',
            'NEW.category_id = NEW.recommended_category_id',
            'a category cannot recommend itself'
        );
    }

    private function constrainCarts(): void
    {
        $this->addGuard(
            'carts',
            'chk_carts_status_closed',
            "status IN ('ACTIVE', 'INACTIVE')",
            "NEW.status NOT IN ('ACTIVE', 'INACTIVE')",
            'cart status must be ACTIVE or INACTIVE'
        );
    }

    private function constrainCartItems(): void
    {
        $this->addGuard(
            'cart_items',
            'chk_cart_items_quantity_bounds',
            'quantity >= 1 AND quantity <= 100',
            'NEW.quantity < 1 OR NEW.quantity > 100',
            'cart item quantity must be between 1 and 100'
        );
    }

    private function constrainFurnitureRequests(): void
    {
        $this->addGuard(
            'furniture_requests',
            'chk_furniture_requests_quantity_bounds',
            'quantity IS NULL OR (quantity >= 1 AND quantity <= 100)',
            'NEW.quantity IS NOT NULL AND (NEW.quantity < 1 OR NEW.quantity > 100)',
            'furniture request quantity must be null or between 1 and 100'
        );
    }

    private function constrainProductStocks(): void
    {
        $this->addGuard(
            'product_stocks',
            'chk_product_stocks_location_non_empty',
            'CHAR_LENGTH(TRIM(warehouse_location)) > 0',
            "NEW.warehouse_location IS NULL OR TRIM(NEW.warehouse_location) = ''",
            'warehouse location must not be empty'
        );
    }

    private function constrainOrders(): void
    {
        $combo = <<<'SQL'
            (fulfillment_type = 'PICKUP' AND delivery_fee_status = 'FINALIZED' AND delivery_fee_amount = 0 AND total_amount = subtotal_amount)
            OR (fulfillment_type = 'DELIVERY' AND delivery_fee_status = 'PENDING' AND delivery_fee_amount IS NULL AND total_amount = subtotal_amount)
            OR (fulfillment_type = 'DELIVERY' AND delivery_fee_status = 'FINALIZED' AND delivery_fee_amount >= 0 AND total_amount = subtotal_amount + delivery_fee_amount)
            SQL;

        $triggerWhen = <<<'SQL'
            NOT (
                (NEW.fulfillment_type = 'PICKUP' AND NEW.delivery_fee_status = 'FINALIZED' AND NEW.delivery_fee_amount = 0 AND NEW.total_amount = NEW.subtotal_amount)
                OR (NEW.fulfillment_type = 'DELIVERY' AND NEW.delivery_fee_status = 'PENDING' AND NEW.delivery_fee_amount IS NULL AND NEW.total_amount = NEW.subtotal_amount)
                OR (NEW.fulfillment_type = 'DELIVERY' AND NEW.delivery_fee_status = 'FINALIZED' AND NEW.delivery_fee_amount >= 0 AND NEW.total_amount = NEW.subtotal_amount + NEW.delivery_fee_amount)
            )
            SQL;

        $this->addGuard(
            'orders',
            'chk_orders_fee_consistency',
            $combo,
            $triggerWhen,
            'order fulfillment, delivery fee, and total combination is invalid'
        );
    }

    private function addGuard(string $table, string $name, string $mysqlCheck, string $sqliteWhen, string $message): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$mysqlCheck})");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::statement(<<<SQL
                CREATE TRIGGER trg_{$name}_{$operation}
                BEFORE {$operation} ON {$table}
                FOR EACH ROW
                WHEN ({$sqliteWhen})
                BEGIN
                    SELECT RAISE(ABORT, '{$message}');
                END
                SQL);
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

    /**
     * @return list<string>
     */
    private function sqliteTriggers(): array
    {
        $names = [
            'chk_categories_display_order_non_negative',
            'chk_cat_rec_no_self_recommendation',
            'chk_carts_status_closed',
            'chk_cart_items_quantity_bounds',
            'chk_furniture_requests_quantity_bounds',
            'chk_product_stocks_location_non_empty',
            'chk_orders_fee_consistency',
        ];
        $triggers = [];

        foreach ($names as $name) {
            $triggers[] = "trg_{$name}_insert";
            $triggers[] = "trg_{$name}_update";
        }

        return $triggers;
    }
};
