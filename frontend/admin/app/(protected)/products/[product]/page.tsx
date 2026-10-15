import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { ProductDetailView } from "@/components/catalog/product-detail-view";
import { ProductFailureState } from "@/components/catalog/product-failure-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { attemptProductRead, fetchOperationalProduct } from "@/lib/catalog/products-api";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function ProductDetailPage({ params }: Readonly<{ params: Promise<{ product: string }> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const { product: identifier } = await params;
  const product = await attemptProductRead(() => fetchOperationalProduct(identifier));
  if (!product.ok && product.failure === "not-found") notFound();

  if (!product.ok) {
    return (
      <PageContainer aria-labelledby="page-title">
        <PageHeader title="Product" description="The requested product record could not be loaded." actions={<Button href={ADMIN_PRODUCT_PATHS.list}>Back to products</Button>} />
        <ProductFailureState failure={product.failure} retryAfterSeconds={product.retryAfterSeconds} />
      </PageContainer>
    );
  }

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={product.value.name}
        description="Authoritative product record returned by the operational API. Values are read-only here; editing uses the approved update request."
        actions={
          <>
            <Button href={ADMIN_PRODUCT_PATHS.edit(product.value.id)} variant="contained">Edit product</Button>
            <Button href={ADMIN_PRODUCT_PATHS.list}>Back to products</Button>
          </>
        }
      />
      <ProductDetailView product={product.value} />
    </PageContainer>
  );
}
