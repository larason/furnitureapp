<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Deterministic demo catalogue for local development (disposable data).
 *
 * Repeatable: products/variants/stocks are keyed by slug/SKU/location and
 * images are only created once per product. Requires the reference taxonomy
 * (DatabaseSeeder) to run first.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSofa();
        $this->seedDiningTable();
        $this->seedArmchair();
    }

    private function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }

    private function seedSofa(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'nordic-3-seater-sofa'],
            [
                'category_id' => $this->category('sofas')->id,
                'name' => 'Nordic 3-Seater Sofa',
                'brand' => 'Nordic Living',
                'room_type' => 'Living Room',
                'assembly_required' => 'partial',
                'primary_material' => 'Fabric',
                'short_description' => 'A comfortable grey fabric 3-seater for modern living rooms.',
                'is_active' => true,
                'is_featured' => true,
            ]
        );

        $grey = ProductVariant::firstOrCreate(
            ['sku' => 'NORDIC-SOFA-GREY'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Grey Fabric',
                'price_amount' => 125000000,
                'price_currency' => 'TZS',
                'compare_at_price_amount' => 140000000,
                'compare_at_price_currency' => 'TZS',
                'width_cm' => 210,
                'height_cm' => 85,
                'depth_cm' => 90,
                'weight_kg' => 42,
                'attributes' => ['color' => 'Grey', 'fabric' => 'Linen blend', 'seats' => 3],
                'is_default' => true,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        ProductVariant::firstOrCreate(
            ['sku' => 'NORDIC-SOFA-BEIGE'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Beige Fabric',
                'price_amount' => 129500000,
                'price_currency' => 'TZS',
                'attributes' => ['color' => 'Beige', 'fabric' => 'Linen blend', 'seats' => 3],
                'is_default' => false,
                'is_active' => true,
                'display_order' => 2,
            ]
        );

        ProductVariant::firstOrCreate(
            ['sku' => 'NORDIC-SOFA-VELVET'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Emerald Velvet (Archived)',
                'price_amount' => 150000000,
                'price_currency' => 'TZS',
                'is_default' => false,
                'is_active' => false,
                'display_order' => 3,
            ]
        );

        if (! $product->images()->exists()) {
            ProductImage::create([
                'product_id' => $product->id,
                'file_path' => 'products/dev/nordic-sofa/main.webp',
                'alt_text' => 'Nordic 3-seater sofa in grey fabric',
                'sort_order' => 0,
                'is_primary' => true,
            ]);
            ProductImage::create([
                'product_id' => $product->id,
                'file_path' => 'products/dev/nordic-sofa/side.webp',
                'alt_text' => 'Nordic sofa side profile',
                'sort_order' => 1,
                'is_primary' => false,
            ]);
            ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => $grey->id,
                'file_path' => 'products/dev/nordic-sofa/fabric-closeup.webp',
                'alt_text' => 'Grey fabric close-up',
                'sort_order' => 2,
                'is_primary' => false,
            ]);
        }

        ProductStock::firstOrCreate(
            ['product_variant_id' => $grey->id, 'warehouse_location' => 'main'],
            ['quantity' => 12, 'reserved_quantity' => 2]
        );
        ProductStock::firstOrCreate(
            ['product_variant_id' => $grey->id, 'warehouse_location' => 'dar-es-salaam'],
            ['quantity' => 5, 'reserved_quantity' => 0]
        );
    }

    private function seedDiningTable(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'rustic-oak-dining-table'],
            [
                'category_id' => $this->category('dining-tables')->id,
                'name' => 'Rustic Oak Dining Table',
                'brand' => 'Oak & Ember',
                'room_type' => 'Dining Room',
                'assembly_required' => 'full',
                'primary_material' => 'Oak',
                'short_description' => 'A solid oak six-seater dining table.',
                'is_active' => true,
                'is_featured' => false,
            ]
        );

        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'RUSTIC-TABLE-OAK'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Natural Oak',
                'price_amount' => 89000000,
                'price_currency' => 'TZS',
                'width_cm' => 180,
                'height_cm' => 75,
                'depth_cm' => 90,
                'weight_kg' => 38,
                'attributes' => ['color' => 'Natural', 'seats' => 6],
                'is_default' => true,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        if (! $product->images()->exists()) {
            ProductImage::create([
                'product_id' => $product->id,
                'file_path' => 'products/dev/rustic-table/main.webp',
                'alt_text' => 'Rustic oak dining table',
                'sort_order' => 0,
                'is_primary' => true,
            ]);
        }

        ProductStock::firstOrCreate(
            ['product_variant_id' => $variant->id, 'warehouse_location' => 'main'],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );
    }

    private function seedArmchair(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'vintage-leather-armchair'],
            [
                'category_id' => $this->category('armchairs')->id,
                'name' => 'Vintage Leather Armchair',
                'brand' => 'Oak & Ember',
                'room_type' => 'Living Room',
                'assembly_required' => 'none',
                'primary_material' => 'Leather',
                'short_description' => 'A discontinued vintage leather armchair kept for history.',
                'is_active' => false,
                'is_featured' => false,
            ]
        );

        ProductVariant::firstOrCreate(
            ['sku' => 'VINTAGE-CHAIR-BROWN'],
            [
                'product_id' => $product->id,
                'variant_name' => 'Brown Leather',
                'price_amount' => 45000000,
                'price_currency' => 'TZS',
                'is_default' => true,
                'is_active' => true,
                'display_order' => 1,
            ]
        );
    }
}
