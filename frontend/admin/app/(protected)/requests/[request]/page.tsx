import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { notFound } from "next/navigation";
import { RequestDetailView } from "@/components/requests/request-detail-view";
import { RequestFailureState } from "@/components/requests/request-failure-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { hasPermission } from "@/lib/auth/operational-identity";
import { AdminPermissionName } from "@/lib/auth/permission-names";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_REQUEST_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";
import { updateRequestAction } from "@/lib/requests/request-actions";
import { attemptRequestRead, fetchRequest } from "@/lib/requests/request-api";
import {
  formatRequestTimestamp,
  requestReferenceDisplay,
  requestStatusDisplay,
  REQUEST_TIMESTAMP_UNAVAILABLE,
} from "@/lib/requests/request-presentation";
import type { OperationalRequest } from "@/lib/requests/request-resource";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.requests);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

/**
 * REQ-005 detail. The route requirement is repeated before data access, an
 * unknown identifier surfaces the framework 404 instead of a fabricated record,
 * and the operational controls are gated on `requests.manage`.
 */
export default async function RequestDetailPage({ params }: Readonly<{ params: Promise<{ request: string }> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const { request: identifier } = await params;
  const result = await attemptRequestRead(() => fetchRequest(identifier));
  if (!result.ok && result.failure === "not-found") notFound();

  if (!result.ok) {
    return (
      <PageContainer aria-labelledby="page-title">
        <PageHeader
          title="Request"
          description="The requested request record could not be loaded."
          actions={<Button href={ADMIN_REQUEST_PATHS.list}>Back to requests</Button>}
        />
        <RequestFailureState failure={result.failure} retryAfterSeconds={result.retryAfterSeconds} />
      </PageContainer>
    );
  }

  const request = result.value;
  const status = requestStatusDisplay(request.requestStatus);
  const canManage = hasPermission(access.identity, AdminPermissionName.REQUESTS_MANAGE);
  const canOpenProduct = hasPermission(access.identity, AdminPermissionName.PRODUCTS_MANAGE);
  const productHref = canOpenProduct && request.product !== null ? ADMIN_PRODUCT_PATHS.detail(request.product.id) : null;

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title={requestReferenceDisplay(request)}
        description={headerDescription(request)}
        actions={
          <>
            <StatusIndicator status={status.tone} label={status.label} />
            <Button href={ADMIN_REQUEST_PATHS.list}>Back to requests</Button>
          </>
        }
      />
      <RequestDetailView
        request={request}
        productHref={productHref}
        canManage={canManage}
        actions={{ update: updateRequestAction }}
      />
    </PageContainer>
  );
}

/** The reference is the title; timestamps and the opaque id are secondary context. */
function headerDescription(request: OperationalRequest): string {
  const submitted = formatRequestTimestamp(request.createdAt) ?? REQUEST_TIMESTAMP_UNAVAILABLE;
  const updated = formatRequestTimestamp(request.updatedAt) ?? REQUEST_TIMESTAMP_UNAVAILABLE;
  return `Submitted ${submitted} · Last updated ${updated} · Technical identifier ${request.id}`;
}
