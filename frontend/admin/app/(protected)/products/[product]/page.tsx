import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { ProductDetailView } from "@/components/catalog/product-detail-view";
import { ProductFailureState } from "@/components/catalog/product-failure-state";
import { ProductImageGallery } from "@/components/catalog/product-image-gallery";
import { ProductImageUploader } from "@/components/catalog/product-image-uploader";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { resolveCatalogMediaOrigin } from "@/lib/catalog/media-origin";
import { deleteProductImageAction, reorderProductImagesAction, selectPrimaryProductImageAction, updateProductImageAltTextAction } from "@/lib/catalog/product-image-actions";
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

  const mediaOrigin = resolveCatalogMediaOrigin(process.env.CATALOG_MEDIA_BASE_URL);

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={product.value.name}
        description="Authoritative product record returned by the operational API. Product fields are read-only here; editing uses the approved update request."
        actions={
          <>
            <Button href={ADMIN_PRODUCT_PATHS.edit(product.value.id)} variant="contained">Edit product</Button>
            <Button href={ADMIN_PRODUCT_PATHS.list}>Back to products</Button>
          </>
        }
      />
      <ProductDetailView
        product={product.value}
        media={
          <>
            <ProductImageGallery
              productId={product.value.id}
              productName={product.value.name}
              images={product.value.images}
              mediaOrigin={mediaOrigin}
              actions={{
                deleteImage: deleteProductImageAction,
                updateAltText: updateProductImageAltTextAction,
                selectPrimary: selectPrimaryProductImageAction,
                reorderImages: reorderProductImagesAction,
              }}
            />
            <ProductImageUploader productId={product.value.id} />
          </>
        }
      />
    </PageContainer>
  );
}
