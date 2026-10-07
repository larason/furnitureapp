import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { ProductGrid } from "@/components/catalog/product-grid";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductCatalog } from "@/lib/products/catalog";

type SearchPageProps = Readonly<{
  searchParams: Promise<{ search?: string | readonly string[]; page?: string | readonly string[] }>;
}>;

const paginationLinkSx = {
  display: "inline-flex",
  alignItems: "center",
  minHeight: 44,
  px: 3,
  typography: "button",
};

export default async function SearchPage({ searchParams }: SearchPageProps) {
  const params = await searchParams;
  const search = parseSearch(params.search);
  const page = parsePage(params.page);
  const catalog = search ? await getProductCatalog(page, undefined, { search }) : null;
  const total = catalog?.pagination?.total;

  return (
    <SiteSection aria-label="Furniture search" surface="paper">
      <Stack spacing={7}>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}>
          <Typography component="h1" variant="h2">{search ? "Search results" : "Search furniture"}</Typography>
          <Typography color="text.secondary">Find furniture by name or other catalog information.</Typography>
        </Stack>
        <Box component="form" action="/search" method="get" role="search" aria-label="Furniture search" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 3, maxWidth: "var(--content-width-form)" }}>
          <TextField type="search" label="Search furniture" defaultValue={search} slotProps={{ htmlInput: { name: "search" } }} fullWidth />
          <Button type="submit" variant="contained" sx={{ minHeight: 44, flexShrink: 0 }}>Search</Button>
        </Box>
        {catalog ? <SearchResults search={search} catalog={catalog} total={total} /> : <Typography>Enter a search term to find furniture in the catalog.</Typography>}
      </Stack>
    </SiteSection>
  );
}

function SearchResults({ search, catalog, total }: Readonly<{ search: string; catalog: NonNullable<Awaited<ReturnType<typeof getProductCatalog>>>; total: number | undefined }>) {
  if (!catalog.products.length) {
    return <Stack spacing={1}>{catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}<Typography>No furniture found for &quot;{search}&quot;. Try another search.</Typography></Stack>;
  }

  return (
    <Stack spacing={5}>
      <Stack spacing={1}>
        <Typography component="h2" variant="h3">&quot;{search}&quot;</Typography>
        {total !== undefined ? <Typography variant="body2" color="text.secondary">{total} matching {total === 1 ? "piece" : "pieces"}</Typography> : null}
        {catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}
      </Stack>
      <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} />
      {catalog.pagination ? <SearchPagination search={search} currentPage={catalog.pagination.current_page} lastPage={catalog.pagination.last_page} hasPrevious={catalog.pagination.has_previous} hasNext={catalog.pagination.has_next} /> : null}
    </Stack>
  );
}

function SearchPagination({ search, currentPage, lastPage, hasPrevious, hasNext }: Readonly<{ search: string; currentPage: number; lastPage: number; hasPrevious: boolean; hasNext: boolean }>) {
  if (!hasPrevious && !hasNext) {
    return null;
  }

  return (
    <Box component="nav" aria-label="Search result pages" sx={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 3 }}>
      {hasPrevious ? <NavLink href={searchPageHref(search, currentPage - 1)} aria-label="Previous search result page" sx={paginationLinkSx}>Previous page</NavLink> : null}
      <Typography aria-current="page" variant="body2">Page {currentPage} of {lastPage}</Typography>
      {hasNext ? <NavLink href={searchPageHref(search, currentPage + 1)} aria-label="Next search result page" sx={paginationLinkSx}>Next page</NavLink> : null}
    </Box>
  );
}

function parseSearch(value: string | readonly string[] | undefined): string {
  const search = Array.isArray(value) ? value[0] : value;
  return search?.trim() ?? "";
}

function parsePage(value: string | readonly string[] | undefined): number {
  const page = Array.isArray(value) ? value[0] : value;
  return page && /^[1-9]\d*$/.test(page) && Number.isSafeInteger(Number(page)) ? Number(page) : 1;
}

function searchPageHref(search: string, page: number): string {
  const params = new URLSearchParams({ search });
  if (page > 1) {
    params.set("page", String(page));
  }
  return `/search?${params.toString()}`;
}
