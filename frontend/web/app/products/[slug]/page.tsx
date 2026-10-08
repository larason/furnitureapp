import Breadcrumbs from "@mui/material/Breadcrumbs";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import Image from "next/image";
import { notFound } from "next/navigation";
import { cache } from "react";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { JsonLd } from "@/components/seo/json-ld";
import { ApiError } from "@/lib/api/client";
import { formatMoney } from "@/lib/catalog/format-money";
import { selectPrimaryImage } from "@/lib/catalog/media";
import { PRODUCT_DETAIL_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getProductDetail } from "@/lib/products/detail";
import { buildProductMetadata } from "@/lib/seo/catalog-metadata";
import { buildBreadcrumbStructuredData, buildProductStructuredData } from "@/lib/seo/structured-data";

type ProductPageProps = Readonly<{ params: Promise<{ slug: string }> }>;

const MISSING_PRODUCT_ERROR_CODES = new Set(["RESOURCE_NOT_FOUND", "PRODUCT_NOT_FOUND"]);

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
  const { slug } = await params;
  const detail = await loadProductDetailCached(slug);
  return buildProductMetadata(detail.product);
}

export default async function ProductPage({ params }: ProductPageProps) {
  const { slug } = await params;
  const detail = await loadProductDetailCached(slug);
  const { product } = detail;
  const leadImage = selectPrimaryImage(product.images);
  const secondaryImages = product.images.filter((image) => image.id !== leadImage?.id);
  const productAvailability = availabilityLabel(product.product_type, product.stock_indicator, product.availability);
  const productStructuredData = slug === product.slug ? buildProductStructuredData(product) : undefined;
  const breadcrumbStructuredData = buildBreadcrumbStructuredData([
    { name: "Home", path: "/" },
    { name: "Furniture", path: "/products" },
    { name: product.category.name, path: `/categories/${product.category.slug}` },
    { name: product.name, path: `/products/${product.slug}` },
  ]);

  return (
    <>
      {productStructuredData ? <JsonLd data={productStructuredData} /> : null}
      {breadcrumbStructuredData ? <JsonLd data={breadcrumbStructuredData} /> : null}
      <SiteSection aria-label="Product detail" surface="paper">
        <Stack spacing={7}>
          <Breadcrumbs aria-label="Breadcrumb" separator={<span aria-hidden="true">/</span>}>
            <NavLink href="/">Home</NavLink><NavLink href="/products">Furniture</NavLink><NavLink href={`/categories/${product.category.slug}`}>{product.category.name}</NavLink><Typography color="text.primary" aria-current="page">{product.name}</Typography>
          </Breadcrumbs>
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "minmax(0, 1fr)", md: "repeat(2, minmax(0, 1fr))" }, gap: { xs: 7, md: 8 } }}>
            <ProductMedia image={leadImage} productName={product.name} preload />
            <Stack spacing={4} sx={{ minWidth: 0, alignSelf: "center" }}>
              {detail.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}
              <Typography component="h1" variant="h2">{product.name}</Typography>
              <Typography variant="body1">{product.product_type === "MADE_TO_ORDER" ? "From " : ""}{formatMoney(product.price)}</Typography>
              <Typography variant="body2" color="text.secondary">{productAvailability}</Typography>
              {product.product_type === "MADE_TO_ORDER" ? <Button href={`/furniture-requests?product=${encodeURIComponent(product.slug)}`} variant="contained" sx={{ alignSelf: "flex-start", minHeight: 44 }}>Request this furniture</Button> : null}
            </Stack>
          </Box>
        </Stack>
      </SiteSection>
      {product.description ? <SiteSection aria-label="Product description"><Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}><Typography component="h2" variant="h3">About this piece</Typography><Typography>{product.description}</Typography></Stack></SiteSection> : null}
      {product.variants.length ? <SiteSection aria-label="Available variations" surface="paper"><Stack spacing={3}><Typography component="h2" variant="h3">Variations</Typography><Box component="dl" sx={{ display: "grid", gap: 3, m: 0 }}>{product.variants.map((variant) => <Box key={variant.id}><Typography component="dt" variant="subtitle2">{variant.name}</Typography><Typography component="dd" variant="body2" sx={{ m: 0 }}>{variant.sku} - {formatMoney(variant.price)} - {availabilityLabel(undefined, variant.stock_indicator, variant.availability)}</Typography></Box>)}</Box></Stack></SiteSection> : null}
      {secondaryImages.length ? <SiteSection aria-label="More product views"><Stack spacing={4}><Typography component="h2" variant="h3">More views</Typography><Box sx={{ display: "grid", gridTemplateColumns: { xs: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" }, gap: { xs: 4, md: 5 } }}>{secondaryImages.map((image) => <ProductMedia key={image.id} image={image} productName={product.name} />)}</Box></Stack></SiteSection> : null}
    </>
  );
}

function ProductMedia({ image, productName, preload = false }: Readonly<{ image: { url: string; alt_text: string | null } | undefined; productName: string; preload?: boolean }>) {
  return <Box sx={{ position: "relative", aspectRatio: "var(--media-product-hero)", bgcolor: "background.paper" }}>{image ? <Image src={image.url} alt={image.alt_text || productName} fill sizes={PRODUCT_DETAIL_IMAGE_SIZES} preload={preload} style={{ objectFit: "contain" }} /> : <Box sx={{ height: "100%", display: "grid", placeItems: "center", p: 4 }}><Typography variant="body2" color="text.secondary">Photograph to follow</Typography></Box>}</Box>;
}

function availabilityLabel(productType: string | undefined, stockIndicator: string, availability: string) {
  let label = "Currently unavailable";

  if (productType === "MADE_TO_ORDER") label = "Made to order";
  else if (stockIndicator === "MADE_TO_ORDER") label = "Made to order";
  else if (stockIndicator === "LOW_STOCK") label = "Low stock";
  else if (availability === "available") label = "In stock";

  return label;
}

async function loadProductDetail(slug: string) {
  try {
    const detail = await getProductDetail(slug);
    if (!detail) notFound();
    return detail;
  } catch (error) {
    if (isMissingProductError(error)) notFound();
    throw error;
  }
}

const loadProductDetailCached = cache(loadProductDetail);

function isMissingProductError(error: unknown): error is ApiError {
  return error instanceof ApiError
    && error.kind === "api"
    && error.status === 404
    && error.errors.some((item) => MISSING_PRODUCT_ERROR_CODES.has(item.code));
}
