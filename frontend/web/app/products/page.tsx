import Breadcrumbs from "@mui/material/Breadcrumbs";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { ProductCollectionControls } from "@/components/catalog/product-collection-controls";
import { ProductCollectionPagination } from "@/components/catalog/product-collection-pagination";
import { ProductGrid } from "@/components/catalog/product-grid";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { getCategoryFilterOptions } from "@/lib/category/options";
import { parseProductCollectionQuery, type ProductCollectionSearchParams } from "@/lib/catalog/filters";
import { PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductCatalog } from "@/lib/products/catalog";

type ProductsPageProps = Readonly<{ searchParams: Promise<ProductCollectionSearchParams> }>;

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const query = parseProductCollectionQuery(await searchParams);
  const [catalog, categories] = await Promise.all([getProductCatalog(query), getCategoryFilterOptions()]);
  const total = catalog.pagination?.total;

  return (
    <SiteSection aria-label="Furniture collection" surface="paper">
      <Stack spacing={7}>
        <Breadcrumbs aria-label="Breadcrumb" separator={<span aria-hidden="true">/</span>}><NavLink href="/">Home</NavLink><Typography color="text.primary">Furniture</Typography></Breadcrumbs>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}><Typography component="h1" variant="h2">Furniture</Typography>{total !== undefined ? <Typography variant="body2" color="text.secondary">{total} {total === 1 ? "piece" : "pieces"}</Typography> : null}{catalog.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}</Stack>
        <ProductCollectionControls action="/products" categories={categories} query={query} />
        {catalog.products.length ? <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} /> : <Typography>No furniture matches these filters.</Typography>}
        {catalog.pagination ? <ProductCollectionPagination pathname="/products" ariaLabel="Product pages" query={query} {...catalog.pagination} currentPage={catalog.pagination.current_page} lastPage={catalog.pagination.last_page} hasPrevious={catalog.pagination.has_previous} hasNext={catalog.pagination.has_next} /> : null}
      </Stack>
    </SiteSection>
  );
}
