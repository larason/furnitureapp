import Menu from "@mui/icons-material/Menu";
import AppBar from "@mui/material/AppBar";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Toolbar from "@mui/material/Toolbar";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";
import type { AdminBreadcrumb } from "@/lib/navigation/admin-navigation";
import { AdminBreadcrumbs } from "./admin-breadcrumbs";
import { NAVIGATION_DRAWER_ID, NAVIGATION_TRIGGER_ID } from "./shell-layout";

interface AdminTopbarProps {
  title: string;
  breadcrumbs: readonly AdminBreadcrumb[];
  accountControl: ReactNode;
  navigationOpen: boolean;
  onOpenNavigation: () => void;
}

export function AdminTopbar({ title, breadcrumbs, accountControl, navigationOpen, onOpenNavigation }: Readonly<AdminTopbarProps>) {
  return (
    <AppBar
      position="sticky"
      color="inherit"
      elevation={0}
      sx={{ backgroundColor: "var(--surface-paper)", color: "text.primary", borderBottom: "var(--border-width) solid var(--border-subtle)" }}
    >
      <Toolbar sx={{ gap: 1 }}>
        <Box sx={{ display: { xs: "inline-flex", md: "none" } }}>
          <Tooltip title="Open navigation menu" describeChild>
            <IconButton
              id={NAVIGATION_TRIGGER_ID}
              aria-label="Open navigation menu"
              aria-haspopup="dialog"
              aria-controls={NAVIGATION_DRAWER_ID}
              aria-expanded={navigationOpen}
              onClick={onOpenNavigation}
            >
              <Menu aria-hidden />
            </IconButton>
          </Tooltip>
        </Box>
        <Typography component="p" variant="body2" noWrap sx={{ display: { xs: "block", md: "none" }, fontWeight: "var(--font-weight-medium)" }}>
          {title}
        </Typography>
        <Box sx={{ display: { xs: "none", md: "flex" }, flexGrow: 1, minWidth: 0 }}>
          <AdminBreadcrumbs items={breadcrumbs} />
        </Box>
        <Box sx={{ display: "flex", justifyContent: "flex-end", flexGrow: { xs: 1, md: 0 }, minWidth: 0 }}>
          {accountControl}
        </Box>
      </Toolbar>
    </AppBar>
  );
}
