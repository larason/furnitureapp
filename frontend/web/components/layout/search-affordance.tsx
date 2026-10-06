import Search from "@mui/icons-material/Search";
import Box from "@mui/material/Box";
import { NavLink } from "./nav-link";

const hiddenOnMobileSx = {
  position: { xs: "absolute", md: "static" },
  width: { xs: "1px", md: "auto" },
  height: { xs: "1px", md: "auto" },
  m: { xs: "-1px", md: 0 },
  overflow: { xs: "hidden", md: "visible" },
  clip: { xs: "rect(0 0 0 0)", md: "auto" },
  whiteSpace: { xs: "nowrap", md: "normal" },
  border: 0,
};

export function SearchAffordance() {
  return (
    <NavLink
      href="/search"
      sx={{
        display: "inline-flex",
        alignItems: "center",
        justifyContent: { xs: "center", md: "flex-start" },
        gap: 1.5,
        minHeight: 44,
        width: { xs: 44, md: "100%" },
        px: { xs: 0, md: 2 },
        border: "1px solid var(--border-default)",
        borderRadius: "var(--radius-sm)",
        color: "text.secondary",
        typography: "body2",
      }}
    >
      <Search fontSize="small" aria-hidden />
      <Box component="span" sx={hiddenOnMobileSx}>
        Search furniture
      </Box>
    </NavLink>
  );
}
