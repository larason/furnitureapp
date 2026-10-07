import Breadcrumbs from "@mui/material/Breadcrumbs";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { notFound } from "next/navigation";
import { ProductGrid } from "@/components/catalog/product-grid";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { ApiError } from "@/lib/api/client";
import { getCategoryCatalog } from "@/lib/category/catalog";
import { CATEGORY_PRODUCT_IMAGE_SIZES, EDITORIAL_IMAGE_SIZES } from "@/lib/homepage/image-sizes";

type CategoryPageProps = Readonly<{
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string | readonly string[] }>;
}>;

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { slug } = await params;
  const { page } = await searchParams;
  const catalog = await loadCategoryCatalog(slug, page);

  return (
    <>
      <SiteSection aria-label="Category introduction" surface="paper">
        <Stack spacing={5} sx={{ maxWidth: "var(--content-width-lead)" }}>
          <Breadcrumbs aria-label="Breadcrumb" separator={<span aria-hidden="true">/</span>}>
            <NavLink href="/">Home</NavLink>
            <Typography color="text.primary">{catalog.category.name}</Typography>
          </Breadcrumbs>
          <Typography component="h1" variant="h2">{catalog.category.name}</Typography>
          {catalog.category.description ? <Typography variant="body1">{catalog.category.description}</Typography> : null}
          {catalog.category.image ? (
            <Box sx={{ position: "relative", aspectRatio: "var(--media-editorial)" }}>
              <Image src={catalog.category.image.url} alt="" fill sizes={EDITORIAL_IMAGE_SIZES} style={{ objectFit: "cover" }} />
            </Box>
          ) : null}
        </Stack>
      </SiteSection>

      <SiteSection aria-label={`${catalog.category.name} furniture`}>
        <Stack spacing={7}>
          <Typography component="h2" variant="h3">Furniture</Typography>
          {catalog.products.length ? (
            <ProductGrid products={catalog.products} sizes={CATEGORY_PRODUCT_IMAGE_SIZES} />
          ) : (
            <Typography>There are no pieces listed in this category yet.</Typography>
          )}
          {catalog.pagination?.has_previous || catalog.pagination?.has_next ? (
            <Box component="nav" aria-label="Category product pages" sx={{ display: "flex", gap: 5 }}>
              {catalog.pagination.has_previous ? <NavLink href={categoryPageHref(slug, catalog.pagination.current_page - 1)}>Previous page</NavLink> : null}
              {catalog.pagination.has_next ? <NavLink href={categoryPageHref(slug, catalog.pagination.current_page + 1)}>Next page</NavLink> : null}
            </Box>
          ) : null}
        </Stack>
      </SiteSection>
    </>
  );
}

async function loadCategoryCatalog(slug: string, page: string | readonly string[] | undefined) {
  try {
    const catalog = await getCategoryCatalog(slug, parsePage(page));
    if (!catalog) {
      notFound();
    }
    return catalog;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      notFound();
    }
    throw error;
  }
}

function parsePage(value: string | readonly string[] | undefined): number {
  const page = Array.isArray(value) ? value[0] : value;
  return page && /^[1-9]\d*$/.test(page) && Number.isSafeInteger(Number(page)) ? Number(page) : 1;
}

function categoryPageHref(slug: string, page: number): string {
  return page === 1 ? `/categories/${slug}` : `/categories/${slug}?page=${page}`;
}
