import Breadcrumbs from "@mui/material/Breadcrumbs";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { ProductGrid } from "@/components/catalog/product-grid";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductCatalog } from "@/lib/products/catalog";

type ProductsPageProps = Readonly<{
  searchParams: Promise<{ page?: string | readonly string[] }>;
}>;

const paginationLinkSx = {
  display: "inline-flex",
  alignItems: "center",
  minHeight: 44,
  px: 3,
  typography: "button",
};

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const { page } = await searchParams;
  const catalog = await getProductCatalog(parsePage(page));
  const total = catalog.pagination?.total;

  return (
    <SiteSection aria-label="Furniture collection" surface="paper">
      <Stack spacing={7}>
        <Breadcrumbs aria-label="Breadcrumb" separator={<span aria-hidden="true">/</span>}>
          <NavLink href="/">Home</NavLink>
          <Typography color="text.primary">Furniture</Typography>
        </Breadcrumbs>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}>
          <Typography component="h1" variant="h2">Furniture</Typography>
          {total !== undefined ? <Typography variant="body2" color="text.secondary">{total} {total === 1 ? "piece" : "pieces"}</Typography> : null}
          {catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}
        </Stack>
        {catalog.products.length ? <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} /> : <Typography>Furniture will appear here as it is published.</Typography>}
        {catalog.pagination ? <ProductPagination currentPage={catalog.pagination.current_page} lastPage={catalog.pagination.last_page} hasPrevious={catalog.pagination.has_previous} hasNext={catalog.pagination.has_next} /> : null}
      </Stack>
    </SiteSection>
  );
}

function ProductPagination({ currentPage, lastPage, hasPrevious, hasNext }: Readonly<{ currentPage: number; lastPage: number; hasPrevious: boolean; hasNext: boolean }>) {
  if (!hasPrevious && !hasNext) {
    return null;
  }

  return (
    <Box component="nav" aria-label="Product pages" sx={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 3 }}>
      {hasPrevious ? <NavLink href={productPageHref(currentPage - 1)} aria-label="Previous product page" sx={paginationLinkSx}>Previous page</NavLink> : null}
      <Typography aria-current="page" variant="body2">Page {currentPage} of {lastPage}</Typography>
      {hasNext ? <NavLink href={productPageHref(currentPage + 1)} aria-label="Next product page" sx={paginationLinkSx}>Next page</NavLink> : null}
    </Box>
  );
}

function parsePage(value: string | readonly string[] | undefined): number {
  const page = Array.isArray(value) ? value[0] : value;
  return page && /^[1-9]\d*$/.test(page) && Number.isSafeInteger(Number(page)) ? Number(page) : 1;
}

function productPageHref(page: number): string {
  return page === 1 ? "/products" : `/products?page=${page}`;
}
