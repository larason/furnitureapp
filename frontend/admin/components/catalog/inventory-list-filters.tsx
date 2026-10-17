"use client";

import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import TextField from "@mui/material/TextField";
import { useState } from "react";
import {
  INVENTORY_LIST_DEFAULT_PER_PAGE,
  INVENTORY_PRODUCT_FILTER_MAX_LENGTH,
  INVENTORY_VARIANT_FILTER_MAX_LENGTH,
  INVENTORY_WAREHOUSE_FILTER_MAX_LENGTH,
  type InventoryListQuery,
} from "@/lib/catalog/inventory-query";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * Server-side filtering through a native GET form, so the list stays a Server
 * Component, the URL remains the single source of filter state, and no request
 * is issued per keystroke.
 *
 * All three filters are exact-identifier values, not name searches: Laravel
 * resolves `product` as a `prod_...` ID, numeric ID or slug, `variant` as a
 * `var_...` ID only, and `warehouse_location` as an exact bounded string. No
 * option set is offered because no approved endpoint enumerates the values, and
 * the rows on the current page are not the complete set.
 */
export function InventoryListFilters({ query }: Readonly<{ query: InventoryListQuery }>) {
  const [product, setProduct] = useState(query.product ?? "");
  const [variant, setVariant] = useState(query.variant ?? "");
  const [warehouseLocation, setWarehouseLocation] = useState(query.warehouseLocation ?? "");

  return (
    <Box
      component="form"
      method="get"
      action={ADMIN_ROUTE_PATHS.inventory}
      aria-label="Filter inventory records"
      sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", lg: "repeat(3, minmax(0, 1fr))" }, gap: 2, alignItems: "start", mb: 4 }}
    >
      <TextField
        name="product"
        label="Product identifier"
        value={product}
        onChange={(event) => setProduct(event.target.value)}
        size="small"
        slotProps={{ htmlInput: { maxLength: INVENTORY_PRODUCT_FILTER_MAX_LENGTH } }}
        helperText="Exact Product ID (prod_…), numeric ID or slug. This is not a product-name search."
      />
      <TextField
        name="variant"
        label="Variant identifier"
        value={variant}
        onChange={(event) => setVariant(event.target.value)}
        size="small"
        slotProps={{ htmlInput: { maxLength: INVENTORY_VARIANT_FILTER_MAX_LENGTH } }}
        helperText="Exact Variant ID (var_…). A SKU is not accepted by this filter."
      />
      <TextField
        name="warehouse_location"
        label="Warehouse location"
        value={warehouseLocation}
        onChange={(event) => setWarehouseLocation(event.target.value)}
        size="small"
        slotProps={{ htmlInput: { maxLength: INVENTORY_WAREHOUSE_FILTER_MAX_LENGTH } }}
        helperText="Exact location identifier, for example main. There is no fuzzy matching."
      />
      {query.perPage === INVENTORY_LIST_DEFAULT_PER_PAGE ? null : <input type="hidden" name="per_page" value={query.perPage} />}
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap", gridColumn: { xs: "auto", sm: "1 / -1" } }}>
        <Button type="submit" variant="contained">Apply filters</Button>
        <Button href={ADMIN_ROUTE_PATHS.inventory}>Clear filters</Button>
      </Box>
    </Box>
  );
}
