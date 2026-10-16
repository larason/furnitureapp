import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { CategoryDetailView } from "@/components/catalog/category-detail-view";
import { CategoryFailureState } from "@/components/catalog/category-failure-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptCategoryRead, fetchCategory } from "@/lib/catalog/categories-api";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_CATEGORY_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.categories);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function CategoryDetailPage({ params }: Readonly<{ params: Promise<{ category: string }> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const { category: identifier } = await params;
  const category = await attemptCategoryRead(() => fetchCategory(identifier));
  if (!category.ok && category.failure === "not-found") notFound();

  if (!category.ok) {
    return (
      <PageContainer aria-labelledby="page-title">
        <PageHeader title="Category" description="The requested category record could not be loaded." actions={<Button href={ADMIN_CATEGORY_PATHS.list}>Back to categories</Button>} />
        <CategoryFailureState failure={category.failure} retryAfterSeconds={category.retryAfterSeconds} />
      </PageContainer>
    );
  }

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={category.value.name}
        description="Category record returned by the API. Values are read-only here; editing uses the approved update request."
        actions={
          <>
            <Button href={ADMIN_CATEGORY_PATHS.edit(category.value.id)} variant="contained">Edit category</Button>
            <Button href={ADMIN_CATEGORY_PATHS.list}>Back to categories</Button>
          </>
        }
      />
      <CategoryDetailView category={category.value} />
    </PageContainer>
  );
}
