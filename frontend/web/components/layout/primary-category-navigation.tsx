import Box from "@mui/material/Box";
import { CATEGORY_NAVIGATION_FIXTURE } from "./category-navigation.fixture";
import { NavLink } from "./nav-link";

const navItemSx = {
  display: "inline-flex",
  alignItems: "center",
  minHeight: 44,
  color: "text.primary",
  typography: "body2",
};

export function PrimaryCategoryNavigation() {
  return (
    <Box component="nav" aria-label="Primary navigation">
      <Box
        component="ul"
        sx={{
          display: "flex",
          alignItems: "center",
          flexWrap: "nowrap",
          gap: { md: 3, lg: 4 },
          m: 0,
          p: 0,
          listStyle: "none",
        }}
      >
        {CATEGORY_NAVIGATION_FIXTURE.map((category) => (
          <Box component="li" key={category.slug} sx={{ display: "flex" }}>
            <NavLink href={`/categories/${category.slug}`} inactive sx={navItemSx}>
              {category.name}
            </NavLink>
          </Box>
        ))}
        <Box
          component="li"
          sx={{
            display: "flex",
            alignItems: "center",
            ml: { md: 1, lg: 2 },
            pl: { md: 3, lg: 4 },
            borderLeft: "1px solid var(--border-subtle)",
          }}
        >
          <NavLink href="/furniture-requests" sx={navItemSx}>
            Made to Order
          </NavLink>
        </Box>
      </Box>
    </Box>
  );
}
