import type { Metadata } from "next";
import { AdminOverviewBody } from "@/components/layout/admin-overview-body";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { operationalDisplayName } from "@/lib/auth/identity-display";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const OVERVIEW_ROUTE = adminRoute(ADMIN_ROUTE_PATHS.overview);
export const metadata: Metadata = adminRouteMetadata(OVERVIEW_ROUTE.href);

export default async function AdminOverviewPage() {
  const access = await requireAdminRouteAccess(OVERVIEW_ROUTE);
  if (!access.granted) return access.denial;
  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={`Welcome back, ${operationalDisplayName(access.identity)}`}
        description="A single workspace for the operational areas this account is authorized to open. Each destination states what it supports in this build; business workflows arrive in scheduled phases."
      />
      <AdminOverviewBody identity={access.identity} />
    </PageContainer>
  );
}
