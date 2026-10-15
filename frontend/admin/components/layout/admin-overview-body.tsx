"use client";

import Box from "@mui/material/Box";
import Card from "@mui/material/Card";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import NextLink from "next/link";
import { SectionHeader } from "@/components/ui/section-header";
import { adminRouteStatus, resolveOverviewNavigation } from "@/lib/navigation/admin-navigation";
import type { AdminNavigationGroup, AdminRoute } from "@/lib/navigation/admin-navigation";
import type { OperationalIdentity } from "@/lib/auth/types";
import { AdminUserIdentity } from "./admin-user-identity";

/**
 * Roadmap status only, never operational data: areas that have no navigation
 * entry because their backend contract is unfinished or deliberately deferred.
 */
const SCHEDULED_AREAS: readonly { readonly label: string; readonly phase: string }[] = [
  { label: "Staff management", phase: "Phase 19.13, after the backend staff endpoints are implemented" },
  { label: "Account and settings", phase: "Phase 19.15" },
  { label: "Orders, payments, checkout and delivery administration", phase: "Deferred while the release is request-only" },
];

const SHORTCUT_GRID_SX = {
  listStyle: "none",
  m: 0,
  p: 0,
  display: "grid",
  gap: "var(--space-3)",
  gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", xl: "repeat(3, minmax(0, 1fr))" },
} as const;

const SHORTCUT_SX = {
  display: "flex",
  flexDirection: "column",
  flexGrow: 1,
  minHeight: 44,
  minWidth: 0,
  p: "var(--space-4)",
  border: "var(--border-width) solid var(--border-subtle)",
  borderRadius: "var(--radius-sm)",
  backgroundColor: "var(--surface-paper)",
  color: "inherit",
  textDecoration: "none",
  "&:hover": { backgroundColor: "var(--surface-editorial)" },
  "&:focus-visible": { boxShadow: "var(--focus-ring)" },
} as const;

export function AdminOverviewBody({ identity }: Readonly<{ identity: OperationalIdentity }>) {
  const groups = resolveOverviewNavigation(identity);

  return (
    <Stack spacing={6}>
      <AccessSection identity={identity} />
      <ShortcutsSection groups={groups} />
      <ScheduledSection />
    </Stack>
  );
}

function AccessSection({ identity }: Readonly<{ identity: OperationalIdentity }>) {
  return (
    <Box component="section" aria-labelledby="overview-access-title">
      <SectionHeader
        title="Your access"
        titleId="overview-access-title"
        description="The Laravel-resolved account behind this session, shown so you know who you are operating as."
      />
      <Box sx={{ display: "flex", flexDirection: "column", gap: "var(--space-3)", pt: 3, maxWidth: "var(--content-width-lead)" }}>
        <AdminUserIdentity identity={identity} />
        <Typography color="text.secondary">
          Navigation lists only the areas this account is authorized to open, and every protected page checks its own requirement again before rendering.
        </Typography>
      </Box>
    </Box>
  );
}

function ShortcutsSection({ groups }: Readonly<{ groups: readonly AdminNavigationGroup[] }>) {
  return (
    <Box component="section" aria-labelledby="overview-areas-title">
      <SectionHeader
        title="Operational areas"
        titleId="overview-areas-title"
        description="Shortcuts to the areas you are authorized to open. Opening one shows its implementation status: a link here does not mean the workflow behind it is functional yet."
      />
      <Box sx={{ pt: 3 }}>
        {groups.length === 0 ? (
          <Typography color="text.secondary">No operational areas are available for this account.</Typography>
        ) : (
          <Stack spacing={4}>
            {groups.map((group) => (
              <ShortcutGroup key={group.id} group={group} />
            ))}
          </Stack>
        )}
      </Box>
    </Box>
  );
}

function ShortcutGroup({ group }: Readonly<{ group: AdminNavigationGroup }>) {
  const headingId = `overview-group-${group.id}`;
  return (
    <Box>
      <Typography component="h3" variant="body2" color="text.secondary" id={headingId} sx={{ pb: 1 }}>
        {group.label}
      </Typography>
      <Box component="nav" aria-labelledby={headingId}>
        <Box component="ul" sx={SHORTCUT_GRID_SX}>
          {group.items.map((route) => (
            <Box component="li" key={route.id} sx={{ display: "flex", minWidth: 0 }}>
              <ShortcutCard route={route} />
            </Box>
          ))}
        </Box>
      </Box>
    </Box>
  );
}

function ShortcutCard({ route }: Readonly<{ route: AdminRoute }>) {
  const Icon = route.icon;
  const status = adminRouteStatus(route);
  const labelId = `overview-shortcut-label-${route.id}`;
  const descriptionId = `overview-shortcut-description-${route.id}`;
  const statusId = status ? `overview-shortcut-status-${route.id}` : undefined;
  return (
    <Card
      component={NextLink}
      href={route.href}
      aria-labelledby={labelId}
      aria-describedby={statusId ? `${descriptionId} ${statusId}` : descriptionId}
      sx={SHORTCUT_SX}
    >
      <Box sx={{ display: "flex", alignItems: "flex-start", gap: 1.5, minWidth: 0 }}>
        <Icon aria-hidden="true" fontSize="small" sx={{ color: "text.secondary", mt: "var(--space-1)", flexShrink: 0 }} />
        <Box sx={{ display: "flex", flexDirection: "column", gap: "var(--space-1)", minWidth: 0, flexGrow: 1 }}>
          <Typography id={labelId} variant="body2" sx={{ fontWeight: "var(--font-weight-medium)", overflowWrap: "anywhere" }}>
            {route.label}
          </Typography>
          <Typography id={descriptionId} variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
            {route.description}
          </Typography>
          {status ? (
            <Typography id={statusId} variant="body2" color="text.secondary" sx={{ pt: "var(--space-1)", overflowWrap: "anywhere" }}>
              {status}
            </Typography>
          ) : null}
        </Box>
      </Box>
    </Card>
  );
}

function ScheduledSection() {
  return (
    <Box component="section" aria-labelledby="overview-scheduled-title">
      <SectionHeader
        title="Not yet available"
        titleId="overview-scheduled-title"
        description="The authenticated shell and navigation are available. Products is implemented and available in this build; the areas listed below remain scheduled. This reflects navigation availability, not operational state."
      />
      <Box sx={{ display: "flex", flexDirection: "column", gap: "var(--space-3)", pt: 3, maxWidth: "var(--content-width-lead)" }}>
        <Typography color="text.secondary">
          Nothing on this page performs business operations. This release has no approved reporting contract, so the dashboard shows navigation and roadmap status only — never counts, totals or recent activity.
        </Typography>
        <Box component="ul" sx={{ listStyle: "none", m: 0, p: 0, display: "flex", flexDirection: "column", gap: "var(--space-1)" }}>
          {SCHEDULED_AREAS.map((area) => (
            <Box component="li" key={area.label}>
              <Typography variant="body2">{area.label}</Typography>
              <Typography variant="body2" color="text.secondary">{area.phase}</Typography>
            </Box>
          ))}
        </Box>
      </Box>
    </Box>
  );
}
