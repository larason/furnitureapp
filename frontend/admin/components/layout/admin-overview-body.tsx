"use client";

import Box from "@mui/material/Box";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import NextLink from "next/link";
import { SectionHeader } from "@/components/ui/section-header";
import { resolveAdminNavigation } from "@/lib/navigation/admin-navigation";
import type { AdminNavigationGroup } from "@/lib/navigation/admin-navigation";
import type { OperationalIdentity } from "@/lib/auth/types";
import { AdminUserIdentity } from "./admin-user-identity";

/**
 * Roadmap status only, never operational data. Each entry states the phase that
 * owns it so the shell cannot be mistaken for a functioning module.
 */
const SCHEDULED_AREAS: readonly { readonly label: string; readonly phase: string }[] = [
  { label: "Made-to-order request handling", phase: "Phase 19.10" },
  { label: "Enquiry handling", phase: "Phase 19.11" },
  { label: "Product, category and media management", phase: "Phases 19.6 to 19.8" },
  { label: "Inventory management", phase: "Phase 19.9" },
  { label: "Customer directory", phase: "Phase 19.12" },
  { label: "Staff management", phase: "Phase 19.13, after the backend staff endpoints are implemented" },
  { label: "Audit logs", phase: "Phase 19.14" },
  { label: "Account and settings", phase: "Phase 19.15" },
  { label: "Notification inbox", phase: "its approved UI phase; only the recipient-scoped notification endpoints exist" },
];

export function AdminOverviewBody({ identity }: Readonly<{ identity: OperationalIdentity }>) {
  const groups = resolveAdminNavigation(identity);

  return (
    <Stack spacing={6}>
      <Paper component="section" variant="outlined" aria-labelledby="overview-account-title" sx={{ p: 3, maxWidth: "var(--content-width-lead)" }}>
        <Stack spacing={2}>
          <Typography component="h2" variant="h4" id="overview-account-title">
            Your access
          </Typography>
          <AdminUserIdentity identity={identity} />
          <Typography color="text.secondary">
            Laravel resolves this identity on every request. Navigation shows only the areas authorized for this account, and each protected page checks its own requirement again before rendering.
          </Typography>
        </Stack>
      </Paper>

      <Box component="section" aria-labelledby="overview-areas-title">
        <SectionHeader
          title="Operational areas"
          titleId="overview-areas-title"
          description="Areas authorized for this account. Each opens a protected placeholder until the phase that implements it is delivered."
        />
        <Box sx={{ pt: 3 }}>
          {groups.length === 0 ? (
            <Typography color="text.secondary">No operational areas are available for this account.</Typography>
          ) : (
            <OverviewShortcuts groups={groups} />
          )}
        </Box>
      </Box>

      <Box component="section" aria-labelledby="overview-scheduled-title">
        <SectionHeader
          title="Not yet available"
          titleId="overview-scheduled-title"
          description="Scheduled work and deliberately deferred commerce administration. Nothing in this list is functional in this build."
        />
        <List aria-labelledby="overview-scheduled-title" sx={{ pt: 3 }}>
          {SCHEDULED_AREAS.map((area) => (
            <ListItem key={area.label} disablePadding sx={{ py: "var(--space-1)" }}>
              <ListItemText primary={area.label} secondary={area.phase} slotProps={{ primary: { variant: "body2" }, secondary: { variant: "body2" } }} />
            </ListItem>
          ))}
          <ListItem disablePadding sx={{ py: "var(--space-1)" }}>
            <ListItemText
              primary="Orders, payments, checkout and delivery administration"
              secondary="Deferred while the release is request-only"
              slotProps={{ primary: { variant: "body2" }, secondary: { variant: "body2" } }}
            />
          </ListItem>
        </List>
        <Typography color="text.secondary" sx={{ pt: 2, maxWidth: "var(--content-width-lead)" }}>
          No operational metrics are displayed: this release has no approved reporting endpoint, so the dashboard shows navigation and roadmap status only.
        </Typography>
      </Box>
    </Stack>
  );
}

function OverviewShortcuts({ groups }: Readonly<{ groups: readonly AdminNavigationGroup[] }>) {
  return (
    <Stack spacing={4}>
      {groups.map((group) => (
        <Box key={group.id}>
          <Typography component="h3" variant="body2" color="text.secondary" id={`overview-group-${group.id}`} sx={{ pb: 1 }}>
            {group.label}
          </Typography>
          <List aria-labelledby={`overview-group-${group.id}`} sx={{ py: 0 }}>
            {group.items.map((item) => {
              const Icon = item.icon;
              return (
                <ListItem key={item.id} disablePadding sx={{ pb: "var(--space-1)" }}>
                  <ListItemButton
                    component={NextLink}
                    href={item.href}
                    sx={{ display: "flex", alignItems: "flex-start", gap: 1.5, minHeight: 44, px: 1.5, py: "var(--space-2)", borderRadius: "var(--radius-sm)", "&.Mui-focusVisible": { boxShadow: "var(--focus-ring)" } }}
                  >
                    <ListItemIcon sx={{ minWidth: 0, mt: "var(--space-1)", color: "text.secondary" }}>
                      <Icon aria-hidden fontSize="small" />
                    </ListItemIcon>
                    <ListItemText primary={item.label} secondary={item.description} slotProps={{ primary: { variant: "body2" }, secondary: { variant: "body2" } }} />
                  </ListItemButton>
                </ListItem>
              );
            })}
          </List>
        </Box>
      ))}
    </Stack>
  );
}
