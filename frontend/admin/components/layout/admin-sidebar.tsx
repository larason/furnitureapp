import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import type { AdminNavigationGroup } from "@/lib/navigation/admin-navigation";
import { AdminBrand } from "./admin-brand";
import { AdminNavigationList } from "./admin-navigation-list";
import { ADMIN_SIDEBAR_WIDTH } from "./shell-layout";

interface AdminSidebarProps {
  groups: readonly AdminNavigationGroup[];
  activeItemId: string | null;
}

export function AdminSidebar({ groups, activeItemId }: Readonly<AdminSidebarProps>) {
  return (
    <Drawer
      variant="permanent"
      open
      sx={{
        display: { xs: "none", md: "block" },
        flexShrink: 0,
        width: ADMIN_SIDEBAR_WIDTH,
        "& .MuiDrawer-paper": {
          width: ADMIN_SIDEBAR_WIDTH,
          boxSizing: "border-box",
          display: "flex",
          flexDirection: "column",
          overflowX: "hidden",
          backgroundColor: "var(--surface-paper)",
          borderRight: "var(--border-width) solid var(--border-subtle)",
        },
      }}
    >
      <Box sx={{ display: "flex", alignItems: "center", px: 2, py: "var(--space-4)" }}>
        <AdminBrand />
      </Box>
      <Box sx={{ flexGrow: 1, overflowY: "auto", px: 1, pb: "var(--space-4)" }}>
        <AdminNavigationList groups={groups} activeItemId={activeItemId} />
      </Box>
    </Drawer>
  );
}
