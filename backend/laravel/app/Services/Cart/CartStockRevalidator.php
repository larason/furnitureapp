<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Queries\ProductCatalogQuery;
use App\Support\ProductType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Cart-wide live stock revalidation (Phase 6.7). Eligibility is resolved first
 * (Product/Variant rules), then live inventory is batched only for the variants
 * that actually need it: eligible line variants get their own stock, and
 * Product-level buckets for rejected lines use the catalog aggregate instead of
 * sibling-variant stock. Rejected lines never trigger their variant's stock.
 *
 * Read-only by design: no Cart/CartItem/ProductStock mutation, no ProductStock
 * lock, no reservation. The result is a point-in-time observation, not a
 * checkout guarantee — Group G still revalidates and reserves transactionally.
 */
final class CartStockRevalidator
{
    public function revalidate(Cart $cart): CartStockRevalidationResult
    {
        $this->loadRequirements($cart);
        $this->primeVariantProducts($cart);

        [$eligibleVariants, $rejectedProducts] = $this->classify($cart);

        $this->primeVariantStocks($eligibleVariants);
        $this->primeProductAvailability($rejectedProducts);

        $results = [];

        foreach ($cart->items as $item) {
            $results[$item->getKey()] = CartItemEligibility::evaluate(
                $item->product,
                $item->variant,
                $item->quantity,
            );
        }

        return new CartStockRevalidationResult($results);
    }

    private function loadRequirements(Cart $cart): void
    {
        $cart->loadMissing([
            'items.product' => fn ($query) => $query->withTrashed(),
            'items.product.category',
            'items.product.variants',
            'items.variant',
        ]);
    }

    private function primeVariantProducts(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            if ($item->product !== null && $item->variant !== null) {
                $item->variant->setRelation('product', $item->product);
            }
        }
    }

    /**
     * @return array{0: array<int, ProductVariant>, 1: array<int, Product>}
     */
    private function classify(Cart $cart): array
    {
        $variants = [];
        $products = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if ($product === null) {
                continue;
            }

            if ($variant !== null && CartItemEligibility::requirementReason($product, $variant) === null) {
                $variants[$variant->getKey()] = $variant;
            } elseif ($product->product_type !== ProductType::MADE_TO_ORDER) {
                $products[$product->getKey()] = $product;
            }
        }

        return [$variants, $products];
    }

    /** @param  array<int, ProductVariant>  $variants */
    private function primeVariantStocks(array $variants): void
    {
        if ($variants === []) {
            return;
        }

        $stocks = ProductStock::query()
            ->whereIn('product_variant_id', array_keys($variants))
            ->get()
            ->groupBy('product_variant_id');

        foreach ($variants as $id => $variant) {
            $variant->setRelation('stocks', $stocks->get($id, new EloquentCollection));
        }
    }

    /** @param  array<int, Product>  $products */
    private function primeProductAvailability(array $products): void
    {
        if ($products === []) {
            return;
        }

        $summaries = (new ProductCatalogQuery)
            ->addSummaryAggregates(Product::withTrashed()->select('products.*')->whereIn('id', array_keys($products)))
            ->get()
            ->keyBy('id');

        foreach ($products as $id => $product) {
            $product->setAttribute('summary_available_quantity', $summaries->get($id)?->getAttribute('summary_available_quantity'));
        }
    }
}
