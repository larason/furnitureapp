"use client";

import Box from "@mui/material/Box";
import { useTheme } from "@mui/material/styles";
import useMediaQuery from "@mui/material/useMediaQuery";
import { usePathname } from "next/navigation";
import { startTransition, useEffect, useMemo, useReducer } from "react";
import type { ReactNode } from "react";
import { useOperationalIdentity } from "@/components/auth/operational-identity-provider";
import { ADMIN_APPLICATION_NAME, buildBreadcrumbs, findActiveNavigation, resolveAdminNavigation } from "@/lib/navigation/admin-navigation";
import { AdminMobileDrawer } from "./admin-mobile-drawer";
import { AdminSidebar } from "./admin-sidebar";
import { AdminTopbar } from "./admin-topbar";
import { AdminUserMenu } from "./admin-user-menu";
import { AdminSkipLink } from "./admin-skip-link";
import { navigationDrawerReducer } from "./navigation-drawer-state";
import { MAIN_CONTENT_ID } from "./shell-layout";

export function AdminShell({ children }: Readonly<{ children: ReactNode }>) {
  const identity = useOperationalIdentity();
  const pathname = usePathname();
  const [navigationOpen, dispatchNavigation] = useReducer(navigationDrawerReducer, false);
  const theme = useTheme();
  const desktopNavigation = useMediaQuery(theme.breakpoints.up("md"));

  const groups = useMemo(() => resolveAdminNavigation(identity), [identity]);
  const active = useMemo(() => findActiveNavigation(groups, pathname), [groups, pathname]);
  const breadcrumbs = useMemo(() => buildBreadcrumbs(active), [active]);
  const activeItemId = active?.item.id ?? null;

  useEffect(() => {
    if (desktopNavigation && navigationOpen) startTransition(() => dispatchNavigation({ type: "desktop" }));
  }, [desktopNavigation, navigationOpen]);

  return (
    <Box sx={{ display: "flex", minHeight: "100dvh", backgroundColor: "var(--surface-canvas)" }}>
      <AdminSkipLink />
      <AdminSidebar groups={groups} activeItemId={activeItemId} />
      <AdminMobileDrawer
        open={navigationOpen}
        onClose={() => dispatchNavigation({ type: "dismiss" })}
        groups={groups}
        activeItemId={activeItemId}
      />
      <Box sx={{ display: "flex", flexDirection: "column", flexGrow: 1, minWidth: 0 }}>
        <AdminTopbar
          title={active?.item.label ?? ADMIN_APPLICATION_NAME}
          breadcrumbs={breadcrumbs}
          accountControl={<AdminUserMenu identity={identity} />}
          navigationOpen={navigationOpen}
          onOpenNavigation={() => dispatchNavigation({ type: "open" })}
        />
        <Box component="main" id={MAIN_CONTENT_ID} tabIndex={-1} sx={{ flexGrow: 1, minWidth: 0 }}>
          {children}
        </Box>
      </Box>
    </Box>
  );
}
