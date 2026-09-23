<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Support\CategoryIdentifier;
use App\Support\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ProductCatalogQuery
{
    public function build(array $filters): Builder
    {
        $query = Product::query()
            ->select('products.*')
            ->public()
            ->with([
                'category:id,name,slug,description',
                'primaryImage:id,product_id,file_path,alt_text',
            ]);

        $this->addSummaryAggregates($query);

        $this->applyFilters($query, $filters);

        return $this->applySort($query, $filters);
    }

    public function addSummaryAggregates(Builder $query): Builder
    {
        return $query
            ->selectSub($this->minimumPriceSubquery(), 'summary_price_amount')
            ->selectSub($this->minimumPriceSubquery('price_currency'), 'summary_price_currency')
            ->selectSub($this->availableStockScalar(), 'summary_has_available_stock')
            ->selectSub($this->availableQuantityScalar(), 'summary_available_quantity');
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

    private function availableQuantityScalar(): Builder
    {
        return ProductStock::query()
            ->selectRaw('COALESCE(SUM(product_stocks.quantity - product_stocks.reserved_quantity), 0)')
            ->join('product_variants', 'product_variants.id', '=', 'product_stocks.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.is_active', true);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        if (isset($filters['category'])) {
            $category = CategoryIdentifier::decode((string) $filters['category'])
                ?? (ctype_digit((string) $filters['category']) ? (int) $filters['category'] : null);
            $query->whereHas('category', function (Builder $categoryQuery) use ($filters, $category): void {
                $categoryQuery->where('is_active', true)->where($category === null ? 'slug' : 'id', $category ?? $filters['category']);
            });
        }

        if (isset($filters['product_type'])) {
            $query->where('products.product_type', $filters['product_type']);
        }

        if (isset($filters['availability'])) {
            $positiveStock = fn (Builder $variant) => $variant->where('is_active', true)
                ->whereHas('stocks', fn (Builder $stock) => $stock->whereRaw('(quantity - reserved_quantity) > 0'));
            $query->when(
                $filters['availability'] === 'available',
                fn (Builder $available) => $available->where(function (Builder $availability): void {
                    $availability->where('products.product_type', ProductType::MADE_TO_ORDER->value)
                        ->orWhereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)->whereHas('stocks', fn (Builder $stock) => $stock->whereRaw('(quantity - reserved_quantity) > 0')));
                }),
                fn (Builder $unavailable) => $unavailable->where('products.product_type', ProductType::IN_STOCK->value)
                    ->whereDoesntHave('variants', $positiveStock),
            );
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

    private function applySearch(Builder $query, string $search): void
    {
        $driver = $this->driver();
        $escape = $this->likeEscapeClause($driver);

        $query->where(function (Builder $searchQuery) use ($search, $driver, $escape): void {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $searchQuery->whereFullText(['products.name', 'products.description'], $search);
            } else {
                $pattern = '%'.$this->escapeLike($search).'%';
                $searchQuery->whereRaw("products.name LIKE ? {$escape}", [$pattern])
                    ->orWhereRaw("products.description LIKE ? {$escape}", [$pattern]);
            }

            $searchQuery->orWhereHas('variants', function (Builder $variant) use ($search, $driver, $escape): void {
                $variant->where('is_active', true)->where(function (Builder $variantSearch) use ($search, $driver, $escape): void {
                    $variantSearch->whereRaw("sku LIKE ? {$escape}", [$this->escapeLike($search).'%']);

                    foreach ($this->searchableAttributeKeys() as $key) {
                        $pattern = '%'.$this->escapeLike($search).'%';
                        $expression = $driver === 'sqlite'
                            ? 'LOWER(json_extract(attributes, ?))'
                            : 'LOWER(JSON_UNQUOTE(JSON_EXTRACT(attributes, ?)))';

                        $variantSearch->orWhereRaw("{$expression} LIKE LOWER(?) {$escape}", ['$.'.$key, $pattern]);
                    }
                });
            });
        });
    }

    /** @return list<string> */
    private function searchableAttributeKeys(): array
    {
        return ['color', 'fabric', 'finish', 'size', 'configuration', 'leg_finish'];
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    private function likeEscapeClause(string $driver): string
    {
        // MySQL/MariaDB need a doubled backslash inside the SQL string literal.
        return $driver === 'sqlite' ? "ESCAPE '\\'" : "ESCAPE '\\\\'";
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
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
