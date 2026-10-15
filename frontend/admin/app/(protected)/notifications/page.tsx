import type { Metadata } from "next";
import { AdminRoutePlaceholder } from "@/components/layout/admin-route-placeholder";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.notifications);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function NotificationsPage() {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;
  return <AdminRoutePlaceholder route={ROUTE} phase="the approved notification inbox phase" />;
}
