import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import Image from "next/image";
import { CatalogDiscovery } from "@/components/catalog/catalog-discovery";
import { SiteSection } from "@/components/layout/site-section";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import { HOMEPAGE_MEDIA } from "@/lib/homepage/fixtures";
import { EDITORIAL_IMAGE_SIZES, HERO_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { getHomepageCatalog } from "@/lib/homepage/catalog";
import { buildHomeMetadata } from "@/lib/seo/catalog-metadata";

export const dynamic = "force-dynamic";

export const metadata: Metadata = buildHomeMetadata();

export default async function Home() {
  const catalog = await getHomepageCatalog();
  const preview = catalog.source === "fixtures";

  return (
    <>
      <SiteSection aria-label="Furniture for your home">
        <Typography variant="body2" sx={{ mb: 4 }}>
          {preview
            ? "Design preview — furniture, room images and prices are development fixtures, not a live catalog."
            : "Catalog data is loaded from the API. Hero and editorial imagery are development visual fixtures."}
        </Typography>
        <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", md: "1fr 2fr" }, alignItems: "center", gap: { xs: 6, md: 8 } }}>
          <Box sx={{ position: "relative", aspectRatio: "var(--media-product-hero)", gridColumn: { md: 2 }, gridRow: { md: 1 } }}>
            <Image src={HOMEPAGE_MEDIA.hero.url} alt={HOMEPAGE_MEDIA.hero.alt} fill sizes={HERO_IMAGE_SIZES} preload style={{ objectFit: "cover" }} />
          </Box>
          <Stack spacing={6} sx={{ gridColumn: { md: 1 }, gridRow: { md: 1 }, maxWidth: "var(--content-width-lead)" }}>
            <Typography component="h1" variant="h2" sx={{ fontSize: { xs: "var(--text-2xl)", sm: "var(--text-3xl)" } }}>
              Furniture for the way you live.
            </Typography>
            <Typography variant="body1">
              Places to gather. Space to unwind. Discover furniture for your home, with made-to-order pieces shaped around your needs.
            </Typography>
            <Button component="a" href="#rooms" variant="contained" sx={{ alignSelf: "flex-start", px: 6, py: 4 }}>
              Explore furniture
            </Button>
          </Stack>
        </Box>
      </SiteSection>

      <CatalogDiscovery catalog={catalog} />

      <SiteSection id="made-to-order" aria-label="Made to order" surface="editorial">
        <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", md: "2fr 1fr" }, alignItems: "center", gap: { xs: 6, md: 8 } }}>
          <Box sx={{ position: "relative", aspectRatio: "var(--media-editorial)" }}>
            <Image src={HOMEPAGE_MEDIA.editorial.url} alt={HOMEPAGE_MEDIA.editorial.alt} fill sizes={EDITORIAL_IMAGE_SIZES} style={{ objectFit: "cover" }} />
          </Box>
          <Stack spacing={6} sx={{ maxWidth: "var(--content-width-lead)" }}>
            <Typography component="h2" variant="h3">A piece begins with your space.</Typography>
            <Typography>Made to order means starting with what you need. Share your dimensions, preferred materials and ideas for the furniture you have in mind.</Typography>
            <Typography variant="body2">A furniture request starts a conversation. The details and commercial terms are agreed before an order is created.</Typography>
            {isSiteRouteImplemented("/furniture-requests") ? (
              <Button component="a" href="/furniture-requests" variant="contained" sx={{ alignSelf: "flex-start", px: 6, py: 4 }}>Request furniture</Button>
            ) : (
              <Typography variant="body2">Online furniture requests are coming soon.</Typography>
            )}
          </Stack>
        </Box>
      </SiteSection>
    </>
  );
}
