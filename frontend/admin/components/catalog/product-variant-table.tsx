"use client";

import { DataTable } from "@/components/ui/data-table";
import type { DataTableColumn } from "@/components/ui/data-table";
import { availabilityLabel, formatProductPrice, stockIndicatorLabel } from "@/lib/catalog/product-presentation";
import type { ProductVariantSummary } from "@/lib/catalog/product-resource";

const COLUMNS: readonly DataTableColumn<ProductVariantSummary>[] = [
  { key: "sku", label: "SKU", render: (variant) => variant.sku },
  { key: "name", label: "Variant", render: (variant) => variant.name },
  { key: "price", label: "Price", render: (variant) => formatProductPrice(variant.price) },
  { key: "availability", label: "Availability", render: (variant) => availabilityLabel(variant.availability) },
  { key: "stock", label: "Stock", render: (variant) => stockIndicatorLabel(variant.stockIndicator) },
];

export function ProductVariantTable({ variants }: Readonly<{ variants: readonly ProductVariantSummary[] }>) {
  return <DataTable columns={COLUMNS} rows={variants} getRowKey={(variant) => variant.id} />;
}
