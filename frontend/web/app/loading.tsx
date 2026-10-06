import Box from "@mui/material/Box";
import { SiteSection } from "@/components/layout/site-section";

const skeletonLineWidths = ["100%", "85%"] as const;

export default function Loading() {
  return (
    <SiteSection>
      <Box
        sx={{
          maxWidth: "var(--content-width-lead)",
          minHeight: "100dvh",
          display: "flex",
          flexDirection: "column",
          gap: 2,
        }}
      >
        <Box
          component="p"
          role="status"
          sx={{ m: 0, typography: "overline", color: "text.secondary" }}
        >
          Loading…
        </Box>
        <Box
          aria-hidden
          sx={{
            height: "var(--space-8)",
            width: "60%",
            borderRadius: "var(--radius-sm)",
            backgroundColor: "var(--surface-editorial)",
          }}
        />
        {skeletonLineWidths.map((width) => (
          <Box
            key={width}
            aria-hidden
            sx={{
              height: "var(--space-4)",
              width,
              borderRadius: "var(--radius-sm)",
              backgroundColor: "var(--surface-editorial)",
            }}
          />
        ))}
      </Box>
    </SiteSection>
  );
}
