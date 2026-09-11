<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use Database\Factories\OrderFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderItemSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertTrue(Schema::hasColumns('order_items', [
            'id',
            'order_id',
            'product_id',
            'variant_id',
            'sku',
            'name',
            'variant_name',
            'unit_price_amount',
            'quantity',
            'line_total_amount',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_migration_rejects_pre_existing_inconsistent_line_total(): void
    {
        $this->disableLineTotalConstraint();

        $orderId = $this->insertRawOrder();
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-PRE-EXISTING',
            'name' => 'Pre-existing',
            'unit_price_amount' => 500,
            'quantity' => 3,
            'line_total_amount' => 1499,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot enforce line-total invariant');

        $this->rerunLineTotalConstraint();
    }

    public function test_migration_allows_pre_existing_consistent_rows(): void
    {
        $this->disableLineTotalConstraint();

        $orderId = $this->insertRawOrder();
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-CONSISTENT',
            'name' => 'Consistent',
            'unit_price_amount' => 500,
            'quantity' => 3,
            'line_total_amount' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->rerunLineTotalConstraint();

        $this->assertTrue(Schema::hasTable('order_items'));
    }

    private function disableLineTotalConstraint(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_items DROP CHECK chk_order_item_line_total_consistent');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS trg_order_items_line_total_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_order_items_line_total_update');
        }

        DB::table('migrations')->where('migration', 'like', '%151000%')->delete();
    }

    private function rerunLineTotalConstraint(): void
    {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_09_10_151000_enforce_order_item_line_total_in_catalog.php',
            '--force' => true,
        ]);
    }

    private function insertRawOrder(): int
    {
        return DB::table('orders')->insertGetId([
            'customer_id' => User::factory()->create()->id,
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => 'TZS',
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => 0,
            'total_amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_order_item_belongs_to_order(): void
    {
        $order = $this->createOrder();
        $item = OrderItem::factory()->for($order)->create();

        $this->assertSame($order->id, $item->order_id);
        $this->assertTrue($item->order->is($order));
    }

    public function test_order_has_many_items(): void
    {
        $order = $this->createOrder();
        $first = OrderItem::factory()->for($order)->create();
        $second = OrderItem::factory()->for($order)->create();

        $items = $order->fresh()->items;

        $this->assertCount(2, $items);
        $this->assertTrue($items->contains($first));
        $this->assertTrue($items->contains($second));
    }

    public function test_order_item_belongs_to_product_and_variant(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();
        $item = OrderItem::factory()->forVariant($variant, $product)->create(['order_id' => $this->createOrder()->id]);

        $this->assertTrue($item->product->is($product));
        $this->assertTrue($item->variant->is($variant));
    }

    public function test_order_item_cannot_exist_without_order(): void
    {
        $this->expectException(QueryException::class);

        OrderItem::query()->create([
            'order_id' => 999999,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-ORPHAN',
            'name' => 'Orphan',
            'unit_price_amount' => 100,
            'quantity' => 1,
            'line_total_amount' => 100,
        ]);
    }

    public function test_deleting_order_cascades_to_items(): void
    {
        $order = $this->createOrder();
        OrderItem::factory()->for($order)->create();
        OrderItem::factory()->for($order)->create();

        $this->assertSame(2, OrderItem::count());

        $order->delete();

        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, DB::table('order_items')->count());
    }

    public function test_deleting_product_nulls_product_id_on_item(): void
    {
        $product = $this->createProduct();
        $item = OrderItem::factory()->create([
            'order_id' => $this->createOrder()->id,
            'product_id' => $product->id,
            'variant_id' => null,
        ]);

        $product->delete();

        $fresh = $item->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->product_id);
        $this->assertSame($item->sku, $fresh->sku);
    }

    public function test_deleting_variant_nulls_variant_id_on_item(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();
        $item = OrderItem::factory()->forVariant($variant, $product)->create(['order_id' => $this->createOrder()->id]);

        $variant->delete();

        $fresh = $item->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->variant_id);
        $this->assertSame($product->id, $fresh->product_id);
    }

    public function test_variant_without_product_is_rejected(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('An order item with a variant must also reference its product.');

        OrderItem::factory()->create([
            'order_id' => $this->createOrder()->id,
            'product_id' => null,
            'variant_id' => $variant->id,
        ]);
    }

    public function test_wrong_product_variant_combination_is_rejected(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $variantB = ProductVariant::factory()->for($productB)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order item variant must belong to the same product.');

        OrderItem::factory()->create([
            'order_id' => $this->createOrder()->id,
            'product_id' => $productA->id,
            'variant_id' => $variantB->id,
        ]);
    }

    public function test_quantity_must_be_positive(): void
    {
        $item = OrderItem::factory()->make(['quantity' => 0]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order item quantity must be greater than zero.');

        $item->save();
    }

    public function test_zero_quantity_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('order_items')->insert([
            'order_id' => $this->createOrder()->id,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-ZERO',
            'name' => 'Zero',
            'unit_price_amount' => 100,
            'quantity' => 0,
            'line_total_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_line_total_must_match_unit_price_times_quantity(): void
    {
        $item = OrderItem::factory()->make(['unit_price_amount' => 500, 'quantity' => 3, 'line_total_amount' => 1499]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order item line total must equal unit price multiplied by quantity.');

        $item->save();
    }

    public function test_inconsistent_line_total_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('order_items')->insert([
            'order_id' => $this->createOrder()->id,
            'product_id' => null,
            'variant_id' => null,
            'sku' => 'SKU-INCONSISTENT',
            'name' => 'Inconsistent',
            'unit_price_amount' => 500,
            'quantity' => 3,
            'line_total_amount' => 1499,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $item = OrderItem::factory()->make(['unit_price_amount' => -100, 'quantity' => 1, 'line_total_amount' => -100]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order item monetary amounts must not be negative.');

        $item->save();
    }

    public function test_money_is_integer_minor_units(): void
    {
        $item = OrderItem::factory()->withLineTotal(35000000, 2)->create(['order_id' => $this->createOrder()->id]);

        $this->assertSame(35000000, $item->unit_price_amount);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(70000000, $item->line_total_amount);
        $this->assertIsInt($item->unit_price_amount);
        $this->assertIsInt($item->quantity);
        $this->assertIsInt($item->line_total_amount);
    }

    public function test_representative_quantities_preserved(): void
    {
        $order = $this->createOrder();
        $single = OrderItem::factory()->withLineTotal(100, 1)->create(['order_id' => $order->id]);
        $multiple = OrderItem::factory()->withLineTotal(100, 5)->create(['order_id' => $order->id]);
        $max = OrderItem::factory()->withLineTotal(100, 100)->create(['order_id' => $order->id]);

        $this->assertSame(1, $single->quantity);
        $this->assertSame(5, $multiple->quantity);
        $this->assertSame(100, $max->quantity);
        $this->assertSame(10000, $max->line_total_amount);
    }

    public function test_historical_snapshot_survives_catalog_changes(): void
    {
        $product = $this->createProduct();
        $variant = ProductVariant::factory()->for($product)->create();
        $item = OrderItem::factory()->forVariant($variant, $product)->create(['order_id' => $this->createOrder()->id]);

        $item->fresh()->toArray();
        $original = [
            'sku' => $item->sku,
            'name' => $item->name,
            'variant_name' => $item->variant_name,
            'unit_price_amount' => $item->unit_price_amount,
            'quantity' => $item->quantity,
            'line_total_amount' => $item->line_total_amount,
        ];

        $product->update(['name' => 'Completely Different Sofa']);
        $variant->update(['sku' => 'SKU-CHANGED', 'variant_name' => 'Renamed Variant', 'price_amount' => 1]);

        $fresh = $item->fresh();

        $this->assertSame($original['sku'], $fresh->sku);
        $this->assertSame($original['name'], $fresh->name);
        $this->assertSame($original['variant_name'], $fresh->variant_name);
        $this->assertSame($original['unit_price_amount'], $fresh->unit_price_amount);
        $this->assertSame($original['quantity'], $fresh->quantity);
        $this->assertSame($original['line_total_amount'], $fresh->line_total_amount);
    }

    public function test_snapshot_is_not_synchronized_with_catalog(): void
    {
        $product = $this->createProduct();
        $item = OrderItem::factory()->create([
            'order_id' => $this->createOrder()->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'name' => 'Original Product Name',
        ]);

        $product->update(['name' => 'New Product Name']);

        $fresh = $item->fresh();
        $this->assertSame('Original Product Name', $fresh->name);
    }

    public function test_order_item_does_not_introduce_unapproved_columns(): void
    {
        $columns = Schema::getColumnListing('order_items');

        foreach (['currency', 'discount', 'tax', 'price_currency', 'deleted_at', 'product_name', 'subtotal'] as $col) {
            $this->assertNotContains($col, $columns);
        }
    }

    public function test_server_controlled_fields_are_fillable_only_for_persistence(): void
    {
        $model = new OrderItem;
        $fillable = $model->getFillable();

        $this->assertContains('order_id', $fillable);
        $this->assertContains('product_id', $fillable);
        $this->assertContains('sku', $fillable);
        $this->assertContains('unit_price_amount', $fillable);
    }

    public function test_items_factory_supports_multiple_items_per_order(): void
    {
        $order = $this->createOrder();
        OrderItem::factory()->count(3)->for($order)->create();

        $this->assertSame(3, $order->fresh()->items->count());
    }

    private function createOrder(): Order
    {
        return Order::factory()->create();
    }

    private function createProduct(): Product
    {
        return Product::factory()->for($this->createCategory())->create();
    }

    private function createCategory(array $attributes = []): Category
    {
        $category = new Category([
            'name' => $attributes['name'] ?? 'Category',
            'slug' => $attributes['slug'] ?? 'cat-'.strtolower(Str::random(6)),
            'space_type' => $attributes['space_type'] ?? 'home',
            'display_order' => $attributes['display_order'] ?? 0,
        ]);
        $category->save();

        return $category->fresh();
    }
}
