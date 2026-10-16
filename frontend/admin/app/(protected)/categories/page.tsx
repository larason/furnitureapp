import type { Metadata } from "next";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import { CategoryFailureState } from "@/components/catalog/category-failure-state";
import { CategoryListPagination } from "@/components/catalog/category-list-pagination";
import { CategoryScopeNotice } from "@/components/catalog/category-scope-notice";
import { CategoryTable } from "@/components/catalog/category-table";
import { EmptyState } from "@/components/states/empty-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptCategoryRead, fetchCategoryPage } from "@/lib/catalog/categories-api";
import type { CategoryReadOutcome } from "@/lib/catalog/categories-api";
import { parseCategoryListQuery, type CategoryListQuery, type CategoryListSearchParams } from "@/lib/catalog/category-query";
import type { CategoryPage } from "@/lib/catalog/category-resource";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_CATEGORY_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.categories);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function CategoriesPage({ searchParams }: Readonly<{ searchParams: Promise<CategoryListSearchParams> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const query = parseCategoryListQuery(await searchParams);
  const page = await attemptCategoryRead(() => fetchCategoryPage(query));

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title="Categories"
        description="Active top-level categories published to the storefront."
        actions={<Button href={ADMIN_CATEGORY_PATHS.create} variant="contained">Create category</Button>}
      />
      <Box sx={{ display: "flex", flexDirection: "column", gap: 3 }}>
        <CategoryScopeNotice />
        <CategoryListBody page={page} query={query} />
      </Box>
    </PageContainer>
  );
}

function CategoryListBody({ page, query }: Readonly<{ page: CategoryReadOutcome<CategoryPage>; query: CategoryListQuery }>) {
  if (!page.ok) return <CategoryFailureState failure={page.failure} retryAfterSeconds={page.retryAfterSeconds} />;
  if (page.value.categories.length === 0) {
    return <EmptyState title="No categories were returned" description="The category endpoint returned an empty result for this page. This screen can only show the active top-level categories the endpoint publishes, so an empty result is not proof that the catalog has no categories." />;
  }

  return (
    <>
      <CategoryTable categories={page.value.categories} />
      <CategoryListPagination pagination={page.value.pagination} query={query} />
    </>
  );
}
