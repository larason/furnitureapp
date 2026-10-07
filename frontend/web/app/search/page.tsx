import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { ProductCollectionControls } from "@/components/catalog/product-collection-controls";
import { ProductCollectionPagination } from "@/components/catalog/product-collection-pagination";
import { ProductGrid } from "@/components/catalog/product-grid";
import { SiteSection } from "@/components/layout/site-section";
import { getCategoryFilterOptions } from "@/lib/category/options";
import { isMeaningfulSearch, isSearchValidationError, parseProductCollectionQuery, singleValue, SEARCH_QUERY_MAX_LENGTH, type ProductCollectionSearchParams } from "@/lib/catalog/filters";
import type { CategorySummary } from "@/lib/catalog/types";
import { PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductCatalog, type ProductCatalog } from "@/lib/products/catalog";
import { buildSearchMetadata } from "@/lib/seo/catalog-metadata";

const TOO_LONG_SEARCH_MESSAGE = `Your search term is too long. Please use ${SEARCH_QUERY_MAX_LENGTH} characters or fewer.`;

type SearchPageProps = Readonly<{ searchParams: Promise<ProductCollectionSearchParams> }>;

export async function generateMetadata({ searchParams }: SearchPageProps): Promise<Metadata> {
  return buildSearchMetadata(parseProductCollectionQuery(await searchParams));
}

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const query = parseProductCollectionQuery(await searchParams);
  const search = singleValue(query.search) ?? "";
  const hasSearch = isMeaningfulSearch(query);
  const outcome = hasSearch ? await loadSearchOutcome(query) : { status: "idle" } as const;
  const total = outcome.status === "ready" ? outcome.catalog.pagination?.total : undefined;

  return (
    <SiteSection aria-label="Furniture search" surface="paper">
      <Stack spacing={7}>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}><Typography component="h1" variant="h2">{hasSearch ? "Search results" : "Search furniture"}</Typography><Typography color="text.secondary">Find furniture by name or other catalog information.</Typography></Stack>
        <Box component="form" action="/search" method="get" role="search" aria-label="Furniture search" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 3, maxWidth: "var(--content-width-form)" }}><TextField type="search" label="Search furniture" defaultValue={search} slotProps={{ htmlInput: { name: "search", maxLength: SEARCH_QUERY_MAX_LENGTH } }} fullWidth /><Button type="submit" variant="contained" sx={{ minHeight: 44, flexShrink: 0 }}>Search</Button></Box>
        {outcome.status === "ready" ? <SearchResults search={search} catalog={outcome.catalog} categories={outcome.categories} query={query} total={total} /> : null}
        {outcome.status === "invalid" ? <Typography role="alert">{TOO_LONG_SEARCH_MESSAGE}</Typography> : null}
        {outcome.status === "idle" ? <Typography>Enter a search term to find furniture in the catalog.</Typography> : null}
      </Stack>
    </SiteSection>
  );
}

type SearchOutcome =
  | Readonly<{ status: "ready"; catalog: ProductCatalog; categories: readonly CategorySummary[] }>
  | Readonly<{ status: "invalid" }>
  | Readonly<{ status: "idle" }>;

async function loadSearchOutcome(query: ReturnType<typeof parseProductCollectionQuery>): Promise<SearchOutcome> {
  try {
    const [catalog, categories] = await Promise.all([getProductCatalog(query), getCategoryFilterOptions()]);
    return { status: "ready", catalog, categories };
  } catch (error) {
    if (!isSearchValidationError(error)) {
      throw error;
    }
    return { status: "invalid" };
  }
}

function SearchResults({ search, catalog, categories, query, total }: Readonly<{ search: string; catalog: NonNullable<Awaited<ReturnType<typeof getProductCatalog>>>; categories: Awaited<ReturnType<typeof getCategoryFilterOptions>>; query: ReturnType<typeof parseProductCollectionQuery>; total: number | undefined }>) {
  return <Stack spacing={5}><Stack spacing={1}><Typography component="h2" variant="h3">&quot;{search}&quot;</Typography>{total !== undefined ? <Typography variant="body2" color="text.secondary">{total} matching {total === 1 ? "piece" : "pieces"}</Typography> : null}{catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}</Stack><ProductCollectionControls action="/search" categories={categories} query={query} />{catalog.products.length ? <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} /> : <Typography>No furniture matches these filters.</Typography>}{catalog.pagination ? <ProductCollectionPagination pathname="/search" ariaLabel="Search result pages" query={query} currentPage={catalog.pagination.current_page} lastPage={catalog.pagination.last_page} hasPrevious={catalog.pagination.has_previous} hasNext={catalog.pagination.has_next} /> : null}</Stack>;
}
