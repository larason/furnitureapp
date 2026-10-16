import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { ProductFailureState } from "@/components/catalog/product-failure-state";
import { ProductListFilters } from "@/components/catalog/product-list-filters";
import { ProductListPagination } from "@/components/catalog/product-list-pagination";
import { ProductTable } from "@/components/catalog/product-table";
import { EmptyState } from "@/components/states/empty-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptProductRead, fetchCategoryOptions, fetchOperationalProductPage } from "@/lib/catalog/products-api";
import type { ProductReadOutcome } from "@/lib/catalog/products-api";
import { countProductFilters, parseProductListQuery, type ProductListQuery, type ProductListSearchParams } from "@/lib/catalog/product-query";
import type { OperationalProductPage } from "@/lib/catalog/product-resource";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function ProductsPage({ searchParams }: Readonly<{ searchParams: Promise<ProductListSearchParams> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const query = parseProductListQuery(await searchParams);
  const [page, categories] = await Promise.all([
    attemptProductRead(() => fetchOperationalProductPage(query)),
    attemptProductRead(() => fetchCategoryOptions()),
  ]);

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title="Products"
        description="Catalog product records exposed by the operational API. Every value is returned by Laravel; variant creation and media management arrive with their own backend phases."
        actions={<Button href={ADMIN_PRODUCT_PATHS.create} variant="contained">Create product</Button>}
      />
      <ProductListFilters
        query={query}
        categories={categories.ok ? categories.value.options : []}
        categoriesUnavailable={!categories.ok}
      />
      <ProductListBody page={page} query={query} />
    </PageContainer>
  );
}

function ProductListBody({ page, query }: Readonly<{ page: ProductReadOutcome<OperationalProductPage>; query: ProductListQuery }>) {
  if (!page.ok) return <ProductFailureState failure={page.failure} retryAfterSeconds={page.retryAfterSeconds} />;
  if (page.value.products.length === 0) {
    return <EmptyState title="No products found" description={emptyDescription(countProductFilters(query))} />;
  }

  return (
    <>
      <ProductTable products={page.value.products} />
      <ProductListPagination pagination={page.value.pagination} query={query} />
    </>
  );
}

function emptyDescription(filterCount: number): string {
  return filterCount > 0
    ? "No product records match the current filters. Clear or change the filters to see other products."
    : "The operational API returned no product records for this account.";
}
