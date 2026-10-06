import Search from "@mui/icons-material/Search";
import Box from "@mui/material/Box";
import { NavLink } from "./nav-link";

export function SearchAffordance() {
  return (
    <NavLink
      href="/search"
      aria-label="Search furniture"
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
        transition:
          "border-color var(--motion-fast) var(--ease-standard), color var(--motion-fast) var(--ease-standard)",
        "&:hover": {
          borderColor: "var(--border-strong)",
          color: "text.primary",
        },
      }}
    >
      <Search fontSize="small" aria-hidden />
      <Box component="span" sx={{ display: { xs: "none", md: "inline" } }}>
        Search furniture
      </Box>
    </NavLink>
  );
}
