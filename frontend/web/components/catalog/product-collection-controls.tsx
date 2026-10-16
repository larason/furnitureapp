import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import Stack from "@mui/material/Stack";
import { FilterSelect } from "@/components/catalog/filter-select";
import { PriceFilterInputs } from "@/components/catalog/price-filter-inputs";
import { SortFilterSelect } from "@/components/catalog/sort-filter-select";
import { NavLink } from "@/components/layout/nav-link";
import { productCollectionHref, singleValue, toFilterControlValues, type ProductCollectionQuery } from "@/lib/catalog/filters";
import type { CategorySummary } from "@/lib/catalog/types";

type ProductCollectionControlsProps = Readonly<{ action: "/products" | "/search"; categories: readonly CategorySummary[]; query: ProductCollectionQuery }>;

export function ProductCollectionControls({ action, categories, query }: ProductCollectionControlsProps) {
  const values = toFilterControlValues(query);
  const search = singleValue(query.search);
  const clearHref = productCollectionHref(action, search ? { search } : {});

  return (
    <Box component="form" action={action} method="get" aria-label="Filter furniture" sx={{ borderBlock: "1px solid var(--border-subtle)", py: 5 }}>
      {search ? <input type="hidden" name="search" value={search} /> : null}
      <Stack component="fieldset" spacing={4} sx={{ border: 0, p: 0, m: 0 }}>
        <Box component="legend" sx={{ typography: "subtitle1", p: 0 }}>Filter furniture</Box>
        <Box sx={{ display: "grid", gridTemplateColumns: { xs: "minmax(0, 1fr)", sm: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" }, gap: 3 }}>
          <FilterSelect label="Category" name="category" value={values.category}><MenuItem value="">All categories</MenuItem>{categories.map((category) => <MenuItem key={category.id} value={category.slug}>{category.name}</MenuItem>)}</FilterSelect>
          <FilterSelect label="Product type" name="product_type" value={values.productType}><MenuItem value="">All furniture</MenuItem><MenuItem value="IN_STOCK">In stock</MenuItem><MenuItem value="MADE_TO_ORDER">Made to order</MenuItem></FilterSelect>
          <FilterSelect label="Availability" name="availability" value={values.availability}><MenuItem value="">Any availability</MenuItem><MenuItem value="available">Available</MenuItem><MenuItem value="unavailable">Unavailable</MenuItem></FilterSelect>
          <PriceFilterInputs minPriceTzs={values.minPriceTzs} maxPriceTzs={values.maxPriceTzs} minPriceMinorUnits={singleValue(query.min_price)} maxPriceMinorUnits={singleValue(query.max_price)} />
          <SortFilterSelect sort={values.sort} sortDirection={values.sortDirection} />
        </Box>
        <Stack direction="row" spacing={3} useFlexGap sx={{ alignItems: "center", flexWrap: "wrap" }}><Button type="submit" variant="contained">Apply filters</Button><NavLink href={clearHref}>Clear filters</NavLink></Stack>
      </Stack>
    </Box>
  );
}
