import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { ProductImageFallback, ProductImagePreview } from "@/components/catalog/product-image-preview";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { EmptyState } from "@/components/states/empty-state";
import { isRenderableMediaUrl, resolveCatalogMediaOrigin, type CatalogMediaOrigin } from "@/lib/catalog/media-origin";
import type { ProductImageDetail } from "@/lib/catalog/product-resource";

const PREVIEW_SIZES = "(min-width: 900px) 30vw, (min-width: 600px) 45vw, 90vw";

/**
 * Read-only view of the images Laravel returns for a product. Sort order, alt
 * text and primary status are authoritative values shown as information; there
 * is no media mutation API, so nothing here is editable.
 */
export function ProductImageGallery({ productName, images }: Readonly<{ productName: string; images: readonly ProductImageDetail[] }>) {
  const mediaOrigin = resolveCatalogMediaOrigin(process.env.CATALOG_MEDIA_BASE_URL);

  if (images.length === 0) {
    return (
      <EmptyState
        title="No images recorded"
        description={`Laravel returned no image records for ${productName}. An empty collection is not an API failure.`}
      />
    );
  }

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 3 }}>
      {mediaOrigin === null ? <MediaOriginNotice /> : null}
      <Box component="ul" sx={{ m: 0, p: 0, listStyle: "none", display: "grid", gap: 3, gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" } }}>
        {images.map((image, index) => (
          <Box component="li" key={image.id}>
            <GalleryItem image={image} index={index} productName={productName} mediaOrigin={mediaOrigin} />
          </Box>
        ))}
      </Box>
    </Box>
  );
}

function GalleryItem({ image, index, productName, mediaOrigin }: Readonly<{ image: ProductImageDetail; index: number; productName: string; mediaOrigin: CatalogMediaOrigin | null }>) {
  return (
    <Paper variant="outlined" sx={{ p: 2, height: "100%", display: "flex", flexDirection: "column", gap: 2 }}>
      <Box sx={{ position: "relative", aspectRatio: "var(--media-product-card)", overflow: "hidden", bgcolor: "action.hover", borderRadius: "var(--radius-sm)" }}>
        {isRenderableMediaUrl(image.url, mediaOrigin) ? (
          <ProductImagePreview src={image.url} alt={imageDescription(image, index, productName)} sizes={PREVIEW_SIZES} />
        ) : (
          <ProductImageFallback reason="origin" />
        )}
      </Box>
      <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
        <Typography variant="body2" color="text.secondary">
          {image.altText === null || image.altText.trim().length === 0 ? "No alt text is recorded for this image." : image.altText}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Sort order: {image.sortOrder}
        </Typography>
        <Box sx={{ display: "flex" }}>{image.isPrimary ? <StatusIndicator status="info" label="Primary image" /> : <Typography variant="body2" color="text.secondary">Not the primary image</Typography>}</Box>
      </Box>
    </Paper>
  );
}

function MediaOriginNotice() {
  return (
    <Paper component="div" role="status" variant="outlined" sx={{ p: 2 }}>
      <Typography variant="body2" color="text.secondary">
        Image previews are disabled because CATALOG_MEDIA_BASE_URL is missing or invalid. Set it to the HTTPS origin Laravel builds image URLs from to enable previews; the stored records are unaffected.
      </Typography>
    </Paper>
  );
}

/** Uses the stored alt text where present, otherwise a neutral accessible label. */
function imageDescription(image: ProductImageDetail, index: number, productName: string): string {
  const altText = image.altText?.trim();
  return altText !== undefined && altText.length > 0 ? altText : `${productName} — product image ${index + 1}`;
}
