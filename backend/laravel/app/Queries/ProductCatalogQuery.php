<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Support\CategoryIdentifier;
use Illuminate\Database\Eloquent\Builder;

final class ProductCatalogQuery
{
    public function build(array $filters): Builder
    {
        $query = Product::query()
            ->select('products.*')
            ->where('products.is_active', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
            ->with([
                'category:id,name,slug',
                'primaryImage:id,product_id,file_path,alt_text',
            ])
            ->selectSub($this->minimumPriceSubquery(), 'summary_price_amount')
            ->selectSub($this->minimumPriceSubquery('price_currency'), 'summary_price_currency')
            ->selectSub($this->availableStockScalar(), 'summary_has_available_stock');

        $this->applyFilters($query, $filters);

        return $this->applySort($query, $filters);
    }

    private function minimumPriceSubquery(string $column = 'price_amount'): Builder
    {
        return ProductVariant::query()
            ->select('product_variants.'.$column)
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.is_active', true)
            ->orderBy('product_variants.price_amount')
            ->orderBy('product_variants.id')
            ->limit(1);
    }

    private function availableStockScalar(): Builder
    {
        return ProductStock::query()
            ->selectRaw('1')
            ->join('product_variants', 'product_variants.id', '=', 'product_stocks.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.is_active', true)
            ->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) > 0')
            ->limit(1);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.description', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn (Builder $variant) => $variant->where('sku', 'like', "%{$search}%"));
            });
        }

        if (isset($filters['category'])) {
            $category = CategoryIdentifier::decode((string) $filters['category'])
                ?? (ctype_digit((string) $filters['category']) ? (int) $filters['category'] : null);
            $query->whereHas('category', function (Builder $categoryQuery) use ($filters, $category): void {
                $categoryQuery->where('is_active', true)->where($category === null ? 'slug' : 'id', $category ?? $filters['category']);
            });
        }

        if (isset($filters['availability'])) {
            $available = $filters['availability'] === 'available';
            if ($available) {
                $query->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)->whereHas('stocks', fn (Builder $stock) => $stock->whereRaw('(quantity - reserved_quantity) > 0')));
            } else {
                $query->whereDoesntHave('variants', fn (Builder $variant) => $variant->where('is_active', true)->whereHas('stocks', fn (Builder $stock) => $stock->whereRaw('(quantity - reserved_quantity) > 0')));
            }
        }

        foreach (['min_price' => '>=', 'max_price' => '<='] as $field => $operator) {
            if (isset($filters[$field])) {
                $minimumPrice = $this->minimumPriceSubquery();
                $query->whereRaw(
                    '('.$minimumPrice->toSql().') '.$operator.' ?',
                    [...$minimumPrice->getBindings(), $filters[$field]],
                );
            }
        }
    }

    private function applySort(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['sort_direction'] ?? ($sort === 'created_at' ? 'desc' : 'asc');

        if ($sort === 'price') {
            $query->orderBy($this->minimumPriceSubquery(), $direction);
        } else {
            $query->orderBy('products.'.$sort, $direction);
        }

        return $query->orderBy('products.id');
    }
}
