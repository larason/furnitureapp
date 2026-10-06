import Box from "@mui/material/Box";
import type { ElementType, ReactNode } from "react";
import { ContentContainer } from "./content-container";

export type SiteSectionSurface = "canvas" | "paper" | "editorial" | "inverse";
export type SiteSectionWidth = "contained" | "full";

const surfaceTokens: Record<SiteSectionSurface, string> = {
  canvas: "var(--surface-canvas)",
  paper: "var(--surface-paper)",
  editorial: "var(--surface-editorial)",
  inverse: "var(--surface-inverse)",
};

export type SiteSectionProps = {
  children: ReactNode;
  surface?: SiteSectionSurface;
  width?: SiteSectionWidth;
  component?: ElementType;
  id?: string;
  "aria-label"?: string;
};

export function SiteSection({
  children,
  surface = "canvas",
  width = "contained",
  component = "section",
  id,
  ...rest
}: Readonly<SiteSectionProps>) {
  return (
    <Box
      component={component}
      id={id}
      {...rest}
      sx={{
        backgroundColor: surfaceTokens[surface],
        color:
          surface === "inverse"
            ? "var(--text-inverse)"
            : "var(--text-primary)",
        py: {
          xs: "var(--section-y-phone)",
          sm: "var(--section-y-tablet)",
          lg: "var(--section-y-desktop)",
        },
      }}
    >
      {width === "contained" ? (
        <ContentContainer>{children}</ContentContainer>
      ) : (
        children
      )}
    </Box>
  );
}
