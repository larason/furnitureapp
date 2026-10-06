import Box from "@mui/material/Box";
import { BrandMark } from "./brand-mark";
import { ContentContainer } from "./content-container";
import { MobileNavigation } from "./mobile-navigation";
import { PrimaryCategoryNavigation } from "./primary-category-navigation";
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
            py: { xs: 1.5, md: 2 },
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
        </Box>
      </ContentContainer>
      <Box
        sx={{
          display: { xs: "none", md: "block" },
          borderTop: "1px solid var(--border-subtle)",
        }}
      >
        <ContentContainer>
          <PrimaryCategoryNavigation />
        </ContentContainer>
      </Box>
    </Box>
  );
}
