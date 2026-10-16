"use client";

import Link from "next/link";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { DataTable } from "@/components/ui/data-table";
import type { DataTableColumn } from "@/components/ui/data-table";
import { ProductStateChips } from "@/components/catalog/product-state-chips";
import { availabilityLabel, formatProductPrice, productStateDescriptors, productTypeLabel } from "@/lib/catalog/product-presentation";
import type { OperationalProduct } from "@/lib/catalog/product-resource";
import { ADMIN_PRODUCT_PATHS } from "@/lib/navigation/admin-route-paths";

const COLUMNS: readonly DataTableColumn<OperationalProduct>[] = [
  {
    key: "product",
    label: "Product",
    render: (product) => (
      <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5, minWidth: 0 }}>
        <Typography component="span" sx={{ fontWeight: 600, overflowWrap: "anywhere" }}>{product.name}</Typography>
        <Typography component="span" variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>{product.slug}</Typography>
      </Box>
    ),
  },
  { key: "type", label: "Type", render: (product) => productTypeLabel(product.productType) },
  { key: "category", label: "Category", render: (product) => product.category.name },
  { key: "availability", label: "Availability", render: (product) => availabilityLabel(product.availability) },
  { key: "state", label: "State", render: (product) => <ProductStateChips descriptors={productStateDescriptors(product)} /> },
  { key: "price", label: "Price", render: (product) => formatProductPrice(product.price) },
  {
    key: "actions",
    label: "Actions",
    render: (product) => (
      <Link href={ADMIN_PRODUCT_PATHS.detail(product.id)} style={{ overflowWrap: "anywhere" }}>
        View {product.name}
      </Link>
    ),
  },
];

export function ProductTable({ products }: Readonly<{ products: readonly OperationalProduct[] }>) {
  return <DataTable columns={COLUMNS} rows={products} getRowKey={(product) => product.id} />;
}
