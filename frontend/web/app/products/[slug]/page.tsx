import Breadcrumbs from "@mui/material/Breadcrumbs";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { notFound } from "next/navigation";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { ApiError } from "@/lib/api/client";
import { formatMoney } from "@/lib/catalog/format-money";
import { getProductDetail } from "@/lib/products/detail";

type ProductPageProps = Readonly<{ params: Promise<{ slug: string }> }>;
const imageSizes = "(min-width: 960px) calc((100vw - 96px) / 2), calc(100vw - 32px)";

export default async function ProductPage({ params }: ProductPageProps) {
  const { slug } = await params;
  const detail = await loadProductDetail(slug);
  const { product } = detail;
  const leadImage = product.images.find((image) => image.is_primary) ?? product.images[0];
  const secondaryImages = product.images.filter((image) => image.id !== leadImage?.id);

  return (
    <>
      <SiteSection aria-label="Product detail" surface="paper">
        <Stack spacing={7}>
          <Breadcrumbs aria-label="Breadcrumb" separator={<span aria-hidden="true">/</span>}>
            <NavLink href="/">Home</NavLink><NavLink href="/products">Furniture</NavLink><NavLink href={`/categories/${product.category.slug}`}>{product.category.name}</NavLink><Typography color="text.primary">{product.name}</Typography>
          </Breadcrumbs>
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "minmax(0, 1fr)", md: "repeat(2, minmax(0, 1fr))" }, gap: { xs: 7, md: 8 } }}>
            <ProductMedia image={leadImage} productName={product.name} priority />
            <Stack spacing={4} sx={{ minWidth: 0, alignSelf: "center" }}>
              {detail.source === "fixtures" ? <Typography variant="body2" color="text.secondary">Design preview</Typography> : null}
              <Typography component="h1" variant="h2">{product.name}</Typography>
              <Typography variant="body1">{product.product_type === "MADE_TO_ORDER" ? "From " : ""}{formatMoney(product.price)}</Typography>
              <Typography variant="body2" color="text.secondary">{product.product_type === "MADE_TO_ORDER" ? "Made to order" : product.stock_indicator === "LOW_STOCK" ? "Low stock" : product.availability === "available" ? "In stock" : "Currently unavailable"}</Typography>
              {product.product_type === "MADE_TO_ORDER" ? <Typography variant="body2">Furniture requests will be available here soon.</Typography> : null}
            </Stack>
          </Box>
        </Stack>
      </SiteSection>
      {product.description ? <SiteSection aria-label="Product description"><Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}><Typography component="h2" variant="h3">About this piece</Typography><Typography>{product.description}</Typography></Stack></SiteSection> : null}
      {product.variants.length ? <SiteSection aria-label="Available variations" surface="paper"><Stack spacing={3}><Typography component="h2" variant="h3">Variations</Typography><Box component="dl" sx={{ display: "grid", gap: 3, m: 0 }}>{product.variants.map((variant) => <Box key={variant.id}><Typography component="dt" variant="subtitle2">{variant.name}</Typography><Typography component="dd" variant="body2" sx={{ m: 0 }}>{variant.sku} - {formatMoney(variant.price)} - {variant.stock_indicator === "LOW_STOCK" ? "Low stock" : variant.availability === "available" ? "Available" : "Currently unavailable"}</Typography></Box>)}</Box></Stack></SiteSection> : null}
      {secondaryImages.length ? <SiteSection aria-label="More product views"><Stack spacing={4}><Typography component="h2" variant="h3">More views</Typography><Box sx={{ display: "grid", gridTemplateColumns: { xs: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" }, gap: { xs: 4, md: 5 } }}>{secondaryImages.map((image) => <ProductMedia key={image.id} image={image} productName={product.name} />)}</Box></Stack></SiteSection> : null}
    </>
  );
}

function ProductMedia({ image, productName, priority = false }: Readonly<{ image: { url: string; alt_text: string | null } | undefined; productName: string; priority?: boolean }>) {
  return <Box sx={{ position: "relative", aspectRatio: "var(--media-product-hero)", bgcolor: "background.paper" }}>{image ? <Image src={image.url} alt={image.alt_text || productName} fill sizes={imageSizes} priority={priority} style={{ objectFit: "contain" }} /> : <Box sx={{ height: "100%", display: "grid", placeItems: "center", p: 4 }}><Typography variant="body2" color="text.secondary">Photograph to follow</Typography></Box>}</Box>;
}

async function loadProductDetail(slug: string) {
  try {
    const detail = await getProductDetail(slug);
    if (!detail) notFound();
    return detail;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
}
