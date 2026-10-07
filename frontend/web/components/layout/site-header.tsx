import Box from "@mui/material/Box";
import { AuthNavigation } from "./auth-navigation";
import { BrandMark } from "./brand-mark";
import { ContentContainer } from "./content-container";
import { MobileNavigation } from "./mobile-navigation";
import { SearchAffordance } from "./search-affordance";

export function SiteHeader() {
  return (
    <Box
      component="header"
      sx={{
        backgroundColor: "var(--surface-canvas)",
        borderBottom: "1px solid var(--border-subtle)",
      }}
    >
      <ContentContainer>
        <Box
          sx={{
            display: "flex",
            alignItems: "center",
            gap: { xs: 1, md: 2 },
            py: { xs: "var(--space-3)", md: 2 },
          }}
        >
          <MobileNavigation />
          <BrandMark />
          <Box
            sx={{
              display: "flex",
              justifyContent: { xs: "flex-end", md: "center" },
              flex: { xs: "0 0 auto", md: "1 1 auto" },
              ml: { xs: "auto", md: 0 },
            }}
          >
            <SearchAffordance />
          </Box>
          <AuthNavigation />
        </Box>
      </ContentContainer>
    </Box>
  );
}
