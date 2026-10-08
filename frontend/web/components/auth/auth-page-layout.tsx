import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import type { ReactNode } from "react";
import { SiteSection } from "@/components/layout/site-section";

const AUTH_HERO_SOURCE = "/furnitures/fixtures/editorial/signup-hero.jpg";
const AUTH_HERO_SIZES = "(min-width: 1440px) 876px, (min-width: 1024px) calc(100vw - 564px), (min-width: 640px) calc(100vw - 48px), calc(100vw - 32px)";

type AuthPageLayoutProps = Readonly<{
  children: ReactNode;
  title: string;
}>;

export function AuthPageLayout({ children, title }: AuthPageLayoutProps) {
  return (
    <SiteSection aria-label={title} surface="paper">
      <Box
        sx={{
          display: "grid",
          alignItems: "start",
          gridTemplateColumns: { xs: "minmax(0, 1fr)", lg: "minmax(0, var(--content-width-form)) minmax(0, 1fr)" },
          gridTemplateAreas: {
            xs: '"title" "form" "hero"',
            lg: '"title ." "form hero"',
          },
          gap: { xs: "var(--space-7)", lg: "var(--space-8)" },
        }}
      >
        <Typography component="h1" variant="h2" sx={{ gridArea: "title" }}>{title}</Typography>
        <Box sx={{ gridArea: "form", minWidth: 0 }}>
          {children}
        </Box>
        <Box sx={{ gridArea: "hero", position: "relative", aspectRatio: "var(--media-editorial)", minWidth: 0 }}>
          <Image
            src={AUTH_HERO_SOURCE}
            alt=""
            fill
            sizes={AUTH_HERO_SIZES}
            style={{ objectFit: "contain" }}
          />
        </Box>
      </Box>
    </SiteSection>
  );
}
