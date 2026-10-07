"use client";

import Close from "@mui/icons-material/Close";
import Menu from "@mui/icons-material/Menu";
import Box from "@mui/material/Box";
import Divider from "@mui/material/Divider";
import Drawer from "@mui/material/Drawer";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import useMediaQuery from "@mui/material/useMediaQuery";
import { useTheme } from "@mui/material/styles";
import { startTransition, useEffect, useState } from "react";
import { BrandMark } from "./brand-mark";
import { CATEGORY_NAVIGATION_FIXTURE } from "./category-navigation.fixture";
import { NavLink } from "./nav-link";
import { SITE_SERVICE_LINKS } from "./site-navigation";

const mobileItemSx = {
  display: "flex",
  alignItems: "center",
  minHeight: 44,
  px: 2,
  color: "text.primary",
  typography: "body1",
};

export function MobileNavigation() {
  const [open, setOpen] = useState(false);
  const theme = useTheme();
  const desktopNavigation = useMediaQuery(theme.breakpoints.up("md"));
  const close = () => setOpen(false);

  useEffect(() => {
    if (desktopNavigation && open) {
      startTransition(() => setOpen(false));
    }
  }, [desktopNavigation, open]);

  return (
    <Box sx={{ display: { xs: "inline-flex", md: "none" } }}>
      <Tooltip title="Open navigation menu" describeChild>
        <IconButton
          size="large"
          aria-label="Open navigation menu"
          aria-haspopup="dialog"
          aria-expanded={open}
          onClick={() => setOpen(true)}
        >
          <Menu aria-hidden />
        </IconButton>
      </Tooltip>
      <Drawer
        anchor="left"
        open={open && !desktopNavigation}
        onClose={close}
        slotProps={{
          paper: {
            id: "mobile-navigation",
            "aria-label": "Navigation menu",
            sx: {
              width: "min(calc(100% - var(--space-6)), var(--content-width-form))",
              backgroundColor: "var(--surface-canvas)",
            },
          },
        }}
      >
        <Box
          sx={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            px: 2,
            py: "var(--space-3)",
          }}
        >
          <BrandMark />
          <Tooltip title="Close navigation menu" describeChild>
            <IconButton
              size="large"
              aria-label="Close navigation menu"
              onClick={close}
            >
              <Close aria-hidden />
            </IconButton>
          </Tooltip>
        </Box>
        <Divider />
        <Box
          component="nav"
          aria-label="Mobile navigation"
          sx={{ px: 1, py: 2, overflowY: "auto" }}
        >
          <Box component="ul" sx={{ m: 0, p: 0, listStyle: "none" }}>
            {CATEGORY_NAVIGATION_FIXTURE.map((category) => (
              <Box component="li" key={category.slug}>
                <NavLink
                  href={`/categories/${category.slug}`}
                  inactive
                  sx={mobileItemSx}
                >
                  {category.name}
                </NavLink>
              </Box>
            ))}
          </Box>
          <Divider sx={{ my: 2 }} />
          <Box component="ul" sx={{ m: 0, p: 0, listStyle: "none" }}>
            {SITE_SERVICE_LINKS.map((link) => (
              <Box component="li" key={link.href}>
                <NavLink
                  href={link.href}
                  sx={mobileItemSx}
                >
                  {link.label}
                </NavLink>
              </Box>
            ))}
          </Box>
        </Box>
      </Drawer>
    </Box>
  );
}
