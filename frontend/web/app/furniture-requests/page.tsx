import Alert from "@mui/material/Alert";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { FurnitureRequestForm, type FurnitureRequestProductContext } from "@/components/furniture-requests/furniture-request-form";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import { ApiError } from "@/lib/api/client";
import { getProductDetail } from "@/lib/products/detail";
import { selectPrimaryImage } from "@/lib/catalog/media";

export const metadata: Metadata = {
  title: "Furniture request",
  description: "Share the furniture you would like made and our team will follow up.",
  robots: { index: false, follow: true },
};

type FurnitureRequestPageProps = Readonly<{ searchParams: Promise<{ product?: string | string[] }> }>;

export default async function FurnitureRequestsPage({ searchParams }: FurnitureRequestPageProps) {
  const context = await resolveProductContext((await searchParams).product);

  return (
    <SiteSection aria-label="Furniture request" surface="paper">
      <Stack spacing={7}>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}>
          <Typography component="h1" variant="h2">Made for your space.</Typography>
          <Typography>Share your dimensions, materials, preferences, and contact details. We will review your request and follow up with you.</Typography>
          <Typography variant="body2" color="text.secondary">A furniture request is not an order, confirmed quotation, payment, or delivery commitment.</Typography>
          {context.status === "ready" ? <Typography variant="body2"><NavLink href={`/products/${context.product.slug}`}>Back to {context.product.name}</NavLink></Typography> : null}
        </Stack>
        {context.status === "unavailable" ? <Alert severity="info">That furniture item is not available to link to a request. You can still send a custom furniture request below.</Alert> : null}
        <FurnitureRequestForm product={context.status === "ready" ? context.product : undefined} />
      </Stack>
    </SiteSection>
  );
}

async function resolveProductContext(value: string | string[] | undefined): Promise<{ status: "ready"; product: FurnitureRequestProductContext } | { status: "unavailable" } | { status: "none" }> {
  if (!value || Array.isArray(value)) return { status: "none" };

  try {
    const detail = await getProductDetail(value);
    if (detail?.source !== "api" || detail.product.product_type !== "MADE_TO_ORDER") return { status: "unavailable" };
    const image = selectPrimaryImage(detail.product.images);
    return {
      status: "ready",
      product: {
        id: detail.product.id,
        name: detail.product.name,
        slug: detail.product.slug,
        image: image ? { url: image.url, alt: image.alt_text || detail.product.name } : null,
      },
    };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return { status: "unavailable" };
    throw error;
  }
}
