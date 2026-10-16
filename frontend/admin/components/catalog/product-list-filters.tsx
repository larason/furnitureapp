"use client";

import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import { useState } from "react";
import type { CategoryOption } from "@/lib/catalog/category-option";
import { availabilityLabel, productTypeLabel } from "@/lib/catalog/product-presentation";
import { PRODUCT_AVAILABILITY_VALUES, PRODUCT_SEARCH_MAX_LENGTH, PRODUCT_TYPE_VALUES } from "@/lib/catalog/product-query";
import type { ProductListQuery } from "@/lib/catalog/product-query";
import { ADMIN_PRODUCT_PATHS } from "@/lib/navigation/admin-route-paths";

interface ProductListFiltersProps {
  readonly query: ProductListQuery;
  readonly categories: readonly CategoryOption[];
  readonly categoriesUnavailable: boolean;
}

/**
 * Server-side filtering through a native GET form, so the list stays a Server
 * Component, the URL remains the single source of filter state, and no request
 * is issued per keystroke. Only contract-approved parameters are submitted.
 */
export function ProductListFilters({ query, categories, categoriesUnavailable }: Readonly<ProductListFiltersProps>) {
  const [search, setSearch] = useState(query.search ?? "");
  const [category, setCategory] = useState(query.category ?? "");
  const [productType, setProductType] = useState(query.productType ?? "");
  const [availability, setAvailability] = useState(query.availability ?? "");
  const [isActive, setIsActive] = useState(booleanValue(query.isActive));
  const [isPublished, setIsPublished] = useState(booleanValue(query.isPublished));

  const categoryOptions = withCurrentCategory(categories, query.category);

  return (
    <Box
      component="form"
      method="get"
      action={ADMIN_PRODUCT_PATHS.list}
      aria-label="Filter products"
      sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", lg: "repeat(3, minmax(0, 1fr))" }, gap: 2, alignItems: "start", mb: 4 }}
    >
      <TextField
        name="search"
        label="Search"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        size="small"
        slotProps={{ htmlInput: { maxLength: PRODUCT_SEARCH_MAX_LENGTH } }}
        helperText={`Name or description, up to ${PRODUCT_SEARCH_MAX_LENGTH} characters.`}
      />
      <TextField
        select
        name="category"
        label="Category"
        value={category}
        onChange={(event) => setCategory(event.target.value)}
        size="small"
        helperText={categoriesUnavailable ? "Category options could not be loaded." : undefined}
      >
        <MenuItem value="">All categories</MenuItem>
        {categoryOptions.map((option) => <MenuItem key={option.id} value={option.id}>{option.name}</MenuItem>)}
      </TextField>
      <TextField select name="product_type" label="Product type" value={productType} onChange={(event) => setProductType(event.target.value)} size="small">
        <MenuItem value="">All types</MenuItem>
        {PRODUCT_TYPE_VALUES.map((value) => <MenuItem key={value} value={value}>{productTypeLabel(value)}</MenuItem>)}
      </TextField>
      <TextField select name="availability" label="Availability" value={availability} onChange={(event) => setAvailability(event.target.value)} size="small">
        <MenuItem value="">Any availability</MenuItem>
        {PRODUCT_AVAILABILITY_VALUES.map((value) => <MenuItem key={value} value={value}>{availabilityLabel(value)}</MenuItem>)}
      </TextField>
      <TextField select name="is_active" label="Active" value={isActive} onChange={(event) => setIsActive(event.target.value)} size="small">
        <MenuItem value="">Any</MenuItem>
        <MenuItem value="true">Active</MenuItem>
        <MenuItem value="false">Inactive</MenuItem>
      </TextField>
      <TextField select name="is_published" label="Published" value={isPublished} onChange={(event) => setIsPublished(event.target.value)} size="small">
        <MenuItem value="">Any</MenuItem>
        <MenuItem value="true">Published</MenuItem>
        <MenuItem value="false">Unpublished</MenuItem>
      </TextField>
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap", gridColumn: { xs: "auto", sm: "1 / -1" } }}>
        <Button type="submit" variant="contained">Apply filters</Button>
        <Button href={ADMIN_PRODUCT_PATHS.list}>Clear filters</Button>
      </Box>
    </Box>
  );
}

function booleanValue(value: boolean | null): string {
  return value === null ? "" : String(value);
}

function withCurrentCategory(categories: readonly CategoryOption[], currentId: string | null): readonly CategoryOption[] {
  if (currentId === null || categories.some((option) => option.id === currentId)) return categories;
  return [{ id: currentId, name: "Current filter", slug: "" }, ...categories];
}
