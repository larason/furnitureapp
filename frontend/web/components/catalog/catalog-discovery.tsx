import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { NavLink } from "@/components/layout/nav-link";
import { SiteSection } from "@/components/layout/site-section";
import type { HomepageCatalog } from "@/lib/homepage/catalog";
import { CATEGORY_IMAGE_SIZES, PRODUCT_IMAGE_SIZES } from "@/lib/homepage/image-sizes";
import { ProductGrid } from "./product-grid";

const categoryGrid = { display: "grid", gridTemplateColumns: { xs: "repeat(2, minmax(0, 1fr))", md: "repeat(4, minmax(0, 1fr))" }, gap: { xs: 4, sm: 6 }, p: 0, m: 0, listStyle: "none" };

export function CatalogDiscovery({ catalog }: Readonly<{ catalog: HomepageCatalog }>) {
  return (
    <>
      <SiteSection id="rooms" aria-label="Explore by room" surface="paper">
        <Stack spacing={7}>
          <Box sx={{ display: "flex", flexDirection: { xs: "column", md: "row" }, justifyContent: "space-between", alignItems: { md: "flex-end" }, gap: 4 }}>
            <Typography component="h2" variant="h3">Start with your space</Typography>
            <Typography variant="body2" sx={{ maxWidth: "var(--content-width-lead)" }}>Furniture for the everyday moments, room by room.</Typography>
          </Box>
          {catalog.categories.length ? (
            <Box component="ul" sx={categoryGrid}>
              {catalog.categories.map((category) => (
                <Box component="li" key={category.id} sx={{ minWidth: 0 }}>
                  <Box sx={{ position: "relative", aspectRatio: "var(--media-product-card)", bgcolor: "var(--surface-editorial)" }}>
                    {category.image ? (
                      <Image src={category.image.url} alt={`Room inspiration for ${category.name}`} fill sizes={CATEGORY_IMAGE_SIZES} style={{ objectFit: "cover" }} />
                    ) : (
                      <Box sx={{ display: "grid", placeItems: "center", height: "100%", p: 4 }}>
                        <Typography variant="body2">Room photography to follow</Typography>
                      </Box>
                    )}
                  </Box>
                  <Typography component="h3" variant="subtitle2" sx={{ mt: 4, overflowWrap: "anywhere" }}>
                    <NavLink href={`/categories/${category.slug}`}>{category.name}</NavLink>
                  </Typography>
                </Box>
              ))}
            </Box>
          ) : <Typography>No furniture categories are published yet.</Typography>}
        </Stack>
      </SiteSection>
      <SiteSection id="selected-furniture" aria-label="Selected furniture" surface="paper">
        <Stack spacing={7}>
          <Box sx={{ maxWidth: "var(--content-width-lead)" }}>
            <Typography component="h2" variant="h3" sx={{ mb: 4 }}>A closer look</Typography>
            <Typography variant="body2">Discover our latest made-to-order pieces. Display prices are a starting point; requirements are agreed through a furniture request.</Typography>
          </Box>
          {catalog.products.length ? (
            <Stack spacing={6}>
              <ProductGrid products={catalog.products} sizes={PRODUCT_IMAGE_SIZES} />
              <Box>
                <NavLink href="/products" sx={{ display: "inline-flex", alignItems: "center", minHeight: 44, typography: "button" }}>View all furniture</NavLink>
              </Box>
            </Stack>
          ) : <Typography>Made-to-order pieces will appear here as they are published.</Typography>}
        </Stack>
      </SiteSection>
    </>
  );
}
