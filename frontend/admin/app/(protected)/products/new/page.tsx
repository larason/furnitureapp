import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { ProductForm } from "@/components/catalog/product-form";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptProductRead, fetchCategoryOptions } from "@/lib/catalog/products-api";
import { EMPTY_PRODUCT_FORM_VALUES } from "@/lib/catalog/product-form";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function NewProductPage() {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const categories = await attemptProductRead(() => fetchCategoryOptions());

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title="Create product"
        description="Create a catalog product through the approved request contract. Laravel validates the request and returns the authoritative record; nothing is shown as saved before the API confirms it."
        actions={<Button href={ADMIN_PRODUCT_PATHS.list}>Back to products</Button>}
      />
      <ProductForm
        mode="create"
        values={EMPTY_PRODUCT_FORM_VALUES}
        categories={categories.ok ? categories.value.options : []}
        currentCategory={null}
        categoriesUnavailable={!categories.ok}
      />
    </PageContainer>
  );
}
