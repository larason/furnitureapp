import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { ProductCollectionControls } from "@/components/catalog/product-collection-controls";
import { ProductCollectionPagination } from "@/components/catalog/product-collection-pagination";
import { ProductGrid } from "@/components/catalog/product-grid";
import { SiteSection } from "@/components/layout/site-section";
import { getCategoryFilterOptions } from "@/lib/category/options";
import { isMeaningfulSearch, parseProductCollectionQuery, singleValue, type ProductCollectionSearchParams } from "@/lib/catalog/filters";
import { PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductCatalog } from "@/lib/products/catalog";

type SearchPageProps = Readonly<{ searchParams: Promise<ProductCollectionSearchParams> }>;

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const query = parseProductCollectionQuery(await searchParams);
  const search = singleValue(query.search) ?? "";
  const hasSearch = isMeaningfulSearch(query);
  const [catalog, categories] = hasSearch ? await Promise.all([getProductCatalog(query), getCategoryFilterOptions()]) : [null, []];
  const total = catalog?.pagination?.total;

  return (
    <SiteSection aria-label="Furniture search" surface="paper">
      <Stack spacing={7}>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}><Typography component="h1" variant="h2">{hasSearch ? "Search results" : "Search furniture"}</Typography><Typography color="text.secondary">Find furniture by name or other catalog information.</Typography></Stack>
        <Box component="form" action="/search" method="get" role="search" aria-label="Furniture search" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 3, maxWidth: "var(--content-width-form)" }}><TextField type="search" label="Search furniture" defaultValue={search} slotProps={{ htmlInput: { name: "search" } }} fullWidth /><Button type="submit" variant="contained" sx={{ minHeight: 44, flexShrink: 0 }}>Search</Button></Box>
        {catalog ? <SearchResults search={search} catalog={catalog} categories={categories} query={query} total={total} /> : <Typography>Enter a search term to find furniture in the catalog.</Typography>}
      </Stack>
    </SiteSection>
  );
}

function SearchResults({ search, catalog, categories, query, total }: Readonly<{ search: string; catalog: NonNullable<Awaited<ReturnType<typeof getProductCatalog>>>; categories: Awaited<ReturnType<typeof getCategoryFilterOptions>>; query: ReturnType<typeof parseProductCollectionQuery>; total: number | undefined }>) {
  return <Stack spacing={5}><Stack spacing={1}><Typography component="h2" variant="h3">&quot;{search}&quot;</Typography>{total !== undefined ? <Typography variant="body2" color="text.secondary">{total} matching {total === 1 ? "piece" : "pieces"}</Typography> : null}{catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}</Stack><ProductCollectionControls action="/search" categories={categories} query={query} />{catalog.products.length ? <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} /> : <Typography>No furniture matches these filters.</Typography>}{catalog.pagination ? <ProductCollectionPagination pathname="/search" ariaLabel="Search result pages" query={query} currentPage={catalog.pagination.current_page} lastPage={catalog.pagination.last_page} hasPrevious={catalog.pagination.has_previous} hasNext={catalog.pagination.has_next} /> : null}</Stack>;
}
