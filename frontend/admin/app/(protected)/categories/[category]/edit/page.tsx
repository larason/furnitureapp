import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { CategoryFailureState } from "@/components/catalog/category-failure-state";
import { CategoryForm } from "@/components/catalog/category-form";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptCategoryRead, fetchCategory } from "@/lib/catalog/categories-api";
import { categoryFormValuesFromCategory } from "@/lib/catalog/category-form";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_CATEGORY_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.categories);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function EditCategoryPage({ params }: Readonly<{ params: Promise<{ category: string }> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const { category: identifier } = await params;
  const category = await attemptCategoryRead(() => fetchCategory(identifier));
  if (!category.ok && category.failure === "not-found") notFound();

  if (!category.ok) {
    return (
      <PageContainer aria-labelledby="page-title">
        <PageHeader title="Edit category" description="The category record could not be loaded, so editing is not available." actions={<Button href={ADMIN_CATEGORY_PATHS.list}>Back to categories</Button>} />
        <CategoryFailureState failure={category.failure} retryAfterSeconds={category.retryAfterSeconds} />
      </PageContainer>
    );
  }

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={`Edit ${category.value.name}`}
        description="Loaded from the API. Only the fields you change are submitted, and Laravel returns the authoritative record after the update."
        actions={<Button href={ADMIN_CATEGORY_PATHS.detail(category.value.id)}>Cancel</Button>}
      />
      <CategoryForm mode="edit" categoryId={category.value.id} values={categoryFormValuesFromCategory(category.value)} />
    </PageContainer>
  );
}
