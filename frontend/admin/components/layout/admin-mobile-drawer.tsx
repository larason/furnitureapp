import Close from "@mui/icons-material/Close";
import Box from "@mui/material/Box";
import Divider from "@mui/material/Divider";
import Drawer from "@mui/material/Drawer";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import type { AdminNavigationGroup } from "@/lib/navigation/admin-navigation";
import { AdminBrand } from "./admin-brand";
import { AdminNavigationList } from "./admin-navigation-list";
import { ADMIN_DRAWER_WIDTH, NAVIGATION_DRAWER_ID } from "./shell-layout";

interface AdminMobileDrawerProps {
  open: boolean;
  onClose: () => void;
  groups: readonly AdminNavigationGroup[];
  activeItemId: string | null;
}

export function AdminMobileDrawer({ open, onClose, groups, activeItemId }: Readonly<AdminMobileDrawerProps>) {
  return (
    <Drawer
      id={NAVIGATION_DRAWER_ID}
      anchor="left"
      open={open}
      onClose={onClose}
      slotProps={{ paper: { "aria-label": "Operational navigation", sx: { width: ADMIN_DRAWER_WIDTH, backgroundColor: "var(--surface-canvas)" } } }}
    >
      <Box sx={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 1, px: 2, py: "var(--space-3)" }}>
        <AdminBrand />
        <Tooltip title="Close navigation menu" describeChild>
          <IconButton aria-label="Close navigation menu" onClick={onClose}>
            <Close aria-hidden />
          </IconButton>
        </Tooltip>
      </Box>
      <Divider />
      <Box sx={{ px: 1, py: "var(--space-4)", overflowY: "auto" }}>
        <AdminNavigationList groups={groups} activeItemId={activeItemId} onNavigate={onClose} />
      </Box>
    </Drawer>
  );
}
