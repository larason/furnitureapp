import AssignmentOutlined from "@mui/icons-material/AssignmentOutlined";
import CategoryOutlined from "@mui/icons-material/CategoryOutlined";
import DashboardOutlined from "@mui/icons-material/DashboardOutlined";
import HistoryOutlined from "@mui/icons-material/HistoryOutlined";
import Inventory2Outlined from "@mui/icons-material/Inventory2Outlined";
import MailOutlineOutlined from "@mui/icons-material/MailOutlineOutlined";
import NotificationsNoneOutlined from "@mui/icons-material/NotificationsNoneOutlined";
import PeopleAltOutlined from "@mui/icons-material/PeopleAltOutlined";
import WarehouseOutlined from "@mui/icons-material/WarehouseOutlined";
import type { OverridableComponent } from "@mui/material/OverridableComponent";
import type { SvgIconTypeMap } from "@mui/material/SvgIcon";
import { hasPermission } from "@/lib/auth/operational-identity";
import { AdminPermissionName } from "@/lib/auth/permission-names";
import type { OperationalIdentity, OperationalRole } from "@/lib/auth/types";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * Single source of truth for the admin route tree, its navigation presentation,
 * and the exact Laravel requirement each protected route repeats server-side.
 * It carries no business data and grants nothing: Laravel stays authoritative.
 */
export type AdminNavigationIcon = OverridableComponent<SvgIconTypeMap>;

export type AdminNavigationGroupId = "workspace" | "operations" | "catalog" | "administration";

export interface AdminRoute {
  readonly id: string;
  readonly href: string;
  readonly label: string;
  readonly description: string;
  readonly icon: AdminNavigationIcon;
  readonly group: AdminNavigationGroupId;
  /** Exact Laravel permission identifier, when the route is permission-controlled. */
  readonly permission?: string;
  /** Approved role restriction mirroring the backend route middleware. */
  readonly role?: OperationalRole;
  /** True while this destination's business workflow has not shipped. */
  readonly placeholder?: boolean;
  /**
   * Roadmap phase owning this destination's workflow, only when the approved
   * roadmap actually names one. A placeholder without it still states that the
   * workflow is unimplemented, but never implies an approved milestone.
   */
  readonly plannedFor?: string;
}

export interface AdminNavigationGroup {
  readonly id: AdminNavigationGroupId;
  readonly label: string;
  readonly items: readonly AdminRoute[];
}

export interface AdminBreadcrumb {
  readonly label: string;
  readonly href?: string;
}

export const ADMIN_OVERVIEW_HREF = ADMIN_ROUTE_PATHS.overview;
export const ADMIN_APPLICATION_NAME = "SL Furnitures Admin";

const NOT_YET_IMPLEMENTED = "Not yet implemented";

const ADMIN_NAVIGATION_GROUP_LABELS: Readonly<Record<AdminNavigationGroupId, string>> = {
  workspace: "Workspace",
  operations: "Operations",
  catalog: "Catalog",
  administration: "Administration",
};

const GROUP_ORDER: readonly AdminNavigationGroupId[] = ["workspace", "operations", "catalog", "administration"];

export const ADMIN_ROUTES: readonly AdminRoute[] = [
  {
    id: "overview",
    href: ADMIN_OVERVIEW_HREF,
    label: "Overview",
    description: "Entry point to the operational areas this account is authorized to use.",
    icon: DashboardOutlined,
    group: "workspace",
  },
  {
    id: "requests",
    href: ADMIN_ROUTE_PATHS.requests,
    label: "Made-to-order requests",
    description: "Customer furniture requests awaiting review and follow-up.",
    icon: AssignmentOutlined,
    group: "operations",
    permission: AdminPermissionName.REQUESTS_VIEW,
    placeholder: true,
    plannedFor: "Phase 19.10",
  },
  {
    id: "enquiries",
    href: ADMIN_ROUTE_PATHS.enquiries,
    label: "Enquiries",
    description: "General customer enquiries received from the website and mobile app.",
    icon: MailOutlineOutlined,
    group: "operations",
    permission: AdminPermissionName.ENQUIRIES_VIEW,
    placeholder: true,
    plannedFor: "Phase 19.11",
  },
  {
    id: "notifications",
    href: ADMIN_ROUTE_PATHS.notifications,
    label: "Notifications",
    description: "Operational notification set for this account.",
    icon: NotificationsNoneOutlined,
    group: "operations",
    permission: AdminPermissionName.NOTIFICATIONS_READ_OPERATIONAL,
    placeholder: true,
  },
  {
    id: "products",
    href: ADMIN_ROUTE_PATHS.products,
    label: "Products",
    description: "Catalog product records, media and variant information.",
    icon: Inventory2Outlined,
    group: "catalog",
    permission: AdminPermissionName.PRODUCTS_MANAGE,
  },
  {
    id: "categories",
    href: ADMIN_ROUTE_PATHS.categories,
    label: "Categories",
    description: "Top-level catalog categories published to the storefront.",
    icon: CategoryOutlined,
    group: "catalog",
    permission: AdminPermissionName.PRODUCTS_MANAGE,
  },
  {
    id: "inventory",
    href: ADMIN_ROUTE_PATHS.inventory,
    label: "Inventory",
    description: "Stock levels and audited inventory adjustments.",
    icon: WarehouseOutlined,
    group: "catalog",
    permission: AdminPermissionName.INVENTORY_VIEW,
    placeholder: true,
    plannedFor: "Phase 19.9",
  },
  {
    id: "customers",
    href: ADMIN_ROUTE_PATHS.customers,
    label: "Customers",
    description: "Authorized customer directory for account support.",
    icon: PeopleAltOutlined,
    group: "administration",
    permission: AdminPermissionName.USERS_MANAGE_AUTHORIZED,
    role: "ADMIN",
    placeholder: true,
    plannedFor: "Phase 19.12",
  },
  {
    id: "audit-logs",
    href: ADMIN_ROUTE_PATHS.auditLogs,
    label: "Audit logs",
    description: "Recorded administrative and operational actions.",
    icon: HistoryOutlined,
    group: "administration",
    permission: AdminPermissionName.AUDIT_VIEW,
    role: "ADMIN",
    placeholder: true,
    plannedFor: "Phase 19.14",
  },
];

/**
 * Restrained roadmap status for a destination whose workflow has not shipped.
 * Names a phase only when the approved roadmap does, so no status can imply a
 * milestone that was never approved.
 */
export function adminRouteStatus(route: AdminRoute): string | null {
  if (!route.placeholder) {
    return null;
  }

  return route.plannedFor ? `${NOT_YET_IMPLEMENTED} — ${route.plannedFor}` : NOT_YET_IMPLEMENTED;
}

function isRoutePermitted(route: AdminRoute, identity: OperationalIdentity): boolean {
  if (route.permission && !hasPermission(identity, route.permission)) return false;
  return !route.role || route.role === identity.role;
}

export function findAdminRoute(href: string): AdminRoute | undefined {
  return ADMIN_ROUTES.find((route) => route.href === href);
}

/** Resolves the configured route for a page, failing loudly on an unconfigured href. */
export function adminRoute(href: string): AdminRoute {
  const route = findAdminRoute(href);
  if (!route) throw new Error(`No admin route is configured for ${href}.`);
  return route;
}

export function adminRouteMetadata(href: string): { title: string } {
  return { title: adminRoute(href).label };
}

export function resolveAdminNavigation(identity: OperationalIdentity): readonly AdminNavigationGroup[] {
  if (!Array.isArray(identity.permissions)) return [];
  return GROUP_ORDER.map((groupId) => ({
    id: groupId,
    label: ADMIN_NAVIGATION_GROUP_LABELS[groupId],
    items: ADMIN_ROUTES.filter((route) => route.group === groupId && isRoutePermitted(route, identity)),
  })).filter((group) => group.items.length > 0);
}

/**
 * The permission-filtered registry for the overview page, without the overview's
 * own entry, so the landing page never links users to the page they are on.
 */
export function resolveOverviewNavigation(identity: OperationalIdentity): readonly AdminNavigationGroup[] {
  return resolveAdminNavigation(identity)
    .map((group) => ({ ...group, items: group.items.filter((item) => item.href !== ADMIN_OVERVIEW_HREF) }))
    .filter((group) => group.items.length > 0);
}

export function isNavigationHrefActive(href: string, pathname: string): boolean {
  if (href === ADMIN_OVERVIEW_HREF) return pathname === ADMIN_OVERVIEW_HREF;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function findActiveNavigation(
  groups: readonly AdminNavigationGroup[],
  pathname: string,
): { readonly group: AdminNavigationGroup; readonly item: AdminRoute } | null {
  const matches = groups.flatMap((group) => group.items.filter((item) => isNavigationHrefActive(item.href, pathname)).map((item) => ({ group, item })));
  return matches.toSorted((first, second) => second.item.href.length - first.item.href.length)[0] ?? null;
}

export function buildBreadcrumbs(active: { readonly item: AdminRoute } | null): readonly AdminBreadcrumb[] {
  if (!active) return [{ label: ADMIN_APPLICATION_NAME }];
  if (active.item.href === ADMIN_OVERVIEW_HREF) return [{ label: active.item.label }];
  return [{ label: adminRoute(ADMIN_OVERVIEW_HREF).label, href: ADMIN_OVERVIEW_HREF }, { label: active.item.label }];
}
