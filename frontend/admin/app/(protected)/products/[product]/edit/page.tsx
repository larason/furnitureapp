import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { ProductFailureState } from "@/components/catalog/product-failure-state";
import { ProductForm } from "@/components/catalog/product-form";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptProductRead, fetchCategoryOptions, fetchOperationalProduct } from "@/lib/catalog/products-api";
import { productFormValuesFromProduct } from "@/lib/catalog/product-form";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function EditProductPage({ params }: Readonly<{ params: Promise<{ product: string }> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const { product: identifier } = await params;
  const [product, categories] = await Promise.all([
    attemptProductRead(() => fetchOperationalProduct(identifier)),
    attemptProductRead(() => fetchCategoryOptions()),
  ]);
  if (!product.ok && product.failure === "not-found") notFound();

  if (!product.ok) {
    return (
      <PageContainer aria-labelledby="page-title">
        <PageHeader title="Edit product" description="The product record could not be loaded, so editing is not available." actions={<Button href={ADMIN_PRODUCT_PATHS.list}>Back to products</Button>} />
        <ProductFailureState failure={product.failure} retryAfterSeconds={product.retryAfterSeconds} />
      </PageContainer>
    );
  }

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={`Edit ${product.value.name}`}
        description="Loaded from the operational API. Only the fields you change are submitted, and Laravel returns the authoritative record after the update."
        actions={<Button href={ADMIN_PRODUCT_PATHS.detail(product.value.id)}>Cancel</Button>}
      />
      <ProductForm
        mode="edit"
        productId={product.value.id}
        values={productFormValuesFromProduct(product.value)}
        categories={categories.ok ? categories.value.options : []}
        currentCategory={{ id: product.value.category.id, name: product.value.category.name }}
        categoriesUnavailable={!categories.ok}
      />
    </PageContainer>
  );
}
