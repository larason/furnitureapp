import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { ProductStateChips } from "@/components/catalog/product-state-chips";
import { ProductVariantTable } from "@/components/catalog/product-variant-table";
import { SectionHeader } from "@/components/ui/section-header";
import { availabilityLabel, formatProductPrice, formatProductTimestamp, productStateDescriptors, productTypeLabel } from "@/lib/catalog/product-presentation";
import type { OperationalProduct } from "@/lib/catalog/product-resource";
import type { ReactNode } from "react";

/**
 * Authoritative product record. Every value comes from Laravel. The media section
 * is supplied by the page as a slot because its gallery needs the server-only
 * mutation actions, so this view stays free of them.
 */
export function ProductDetailView({ product, media }: Readonly<{ product: OperationalProduct; media?: ReactNode }>) {
  const created = formatProductTimestamp(product.createdAt);
  const updated = formatProductTimestamp(product.updatedAt);

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 6 }}>
      <Box component="section" aria-labelledby="product-details-heading">
        <SectionHeader title="Product details" titleId="product-details-heading" />
        <Paper variant="outlined" sx={{ p: 3, mt: 3 }}>
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))" }, gap: 3 }}>
            <DetailField label="Type" value={productTypeLabel(product.productType)} />
            <DetailField label="Public slug" value={product.slug} />
            <DetailField label="Category" value={product.category.name} />
            <DetailField label="Availability" value={availabilityLabel(product.availability)} />
            <DetailField label="Price" value={formatProductPrice(product.price)} />
            <Box sx={{ minWidth: 0 }}>
              <Typography variant="overline" color="text.secondary" component="p">State</Typography>
              <ProductStateChips descriptors={productStateDescriptors(product)} />
            </Box>
            {created === null ? null : <DetailField label="Created" value={created} />}
            {updated === null ? null : <DetailField label="Updated" value={updated} />}
          </Box>
          <Box sx={{ mt: 3, minWidth: 0 }}>
            <Typography variant="overline" color="text.secondary" component="p">Description</Typography>
            <Typography sx={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
              {product.description !== null && product.description.trim().length > 0 ? product.description : "No description is recorded for this product."}
            </Typography>
          </Box>
        </Paper>
      </Box>

      <Box component="section" aria-labelledby="product-media-heading">
        <SectionHeader
          title="Product media"
          titleId="product-media-heading"
          description="Images returned by the operational product resource. Deletion, ordering, alt text and primary selection are sent to Laravel, and the gallery renders only what Laravel returns."
        />
        <Box sx={{ mt: 3, display: "flex", flexDirection: "column", gap: 4 }}>{media}</Box>
      </Box>

      <Box component="section" aria-labelledby="product-variants-heading">
        <SectionHeader title="Variants" titleId="product-variants-heading" description="Active variants returned by the operational product resource." />
        <Box sx={{ mt: 3, display: "flex", flexDirection: "column", gap: 2 }}>
          {product.variants.length > 0 ? (
            <ProductVariantTable variants={product.variants} />
          ) : (
            <Paper variant="outlined" sx={{ p: 3 }}>
              <Typography color="text.secondary">No variants were returned for this product by the current API.</Typography>
            </Paper>
          )}
          <Typography color="text.secondary">
            Variant creation is not available in this release because the backend variant endpoint is not implemented, so no create control is shown. Variant editing and deletion are likewise unavailable.
          </Typography>
        </Box>
      </Box>
    </Box>
  );
}

function DetailField({ label, value }: Readonly<{ label: string; value: string }>) {
  return (
    <Box sx={{ minWidth: 0 }}>
      <Typography variant="overline" color="text.secondary" component="p">{label}</Typography>
      <Typography sx={{ overflowWrap: "anywhere" }}>{value}</Typography>
    </Box>
  );
}
