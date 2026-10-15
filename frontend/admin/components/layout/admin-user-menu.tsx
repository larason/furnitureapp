"use client";

import ExpandMore from "@mui/icons-material/ExpandMore";
import LogoutOutlined from "@mui/icons-material/LogoutOutlined";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import { useClerk } from "@clerk/nextjs";
import { useState } from "react";
import { operationalDisplayName } from "@/lib/auth/identity-display";
import type { OperationalIdentity } from "@/lib/auth/types";
import { AdminUserIdentity } from "./admin-user-identity";
import { USER_MENU_BUTTON_ID, USER_MENU_ID } from "./shell-layout";

export function AdminUserMenu({ identity }: Readonly<{ identity: OperationalIdentity }>) {
  const { signOut } = useClerk();
  const [anchor, setAnchor] = useState<HTMLElement | null>(null);
  const open = Boolean(anchor);

  const close = () => setAnchor(null);
  const handleSignOut = () => {
    close();
    void signOut({ redirectUrl: "/sign-in" });
  };

  return (
    <>
      <Button
        id={USER_MENU_BUTTON_ID}
        onClick={(event) => setAnchor(event.currentTarget)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? USER_MENU_ID : undefined}
        endIcon={<ExpandMore aria-hidden />}
        sx={{ minWidth: 0, maxWidth: "var(--content-width-form)", color: "text.primary", borderColor: "var(--border-default)" }}
      >
        <Box component="span" sx={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
          {operationalDisplayName(identity)}
        </Box>
      </Button>
      <Menu
        id={USER_MENU_ID}
        anchorEl={anchor}
        open={open}
        onClose={close}
        slotProps={{ list: { "aria-labelledby": USER_MENU_BUTTON_ID, sx: { minWidth: "var(--content-width-form)", py: 0 } } }}
      >
        <Box sx={{ px: 2, py: "var(--space-3)" }}>
          <AdminUserIdentity identity={identity} />
        </Box>
        <Divider />
        <MenuItem onClick={handleSignOut}>
          <ListItemIcon>
            <LogoutOutlined aria-hidden fontSize="small" />
          </ListItemIcon>
          <ListItemText primary="Sign out" slotProps={{ primary: { variant: "body2" } }} />
        </MenuItem>
      </Menu>
    </>
  );
}
