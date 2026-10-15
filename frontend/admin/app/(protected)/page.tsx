import type { Metadata } from "next";
import { AdminOverviewBody } from "@/components/layout/admin-overview-body";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const OVERVIEW_ROUTE = adminRoute(ADMIN_ROUTE_PATHS.overview);
export const metadata: Metadata = adminRouteMetadata(OVERVIEW_ROUTE.href);

export default async function AdminOverviewPage() {
  const access = await requireAdminRouteAccess(OVERVIEW_ROUTE);
  if (!access.granted) return access.denial;
  const name = access.identity.name?.trim();
  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={name ? `Welcome, ${name}` : "Welcome"}
        description="This is the SL Furnitures operational workspace. Use the navigation to open the areas this account is authorized to use."
      />
      <AdminOverviewBody identity={access.identity} />
    </PageContainer>
  );
}
