import type { Metadata } from "next";
import Button from "@mui/material/Button";
import { RequestFailureState } from "@/components/requests/request-failure-state";
import { RequestListFilters } from "@/components/requests/request-list-filters";
import { RequestListPagination } from "@/components/requests/request-list-pagination";
import { RequestTable } from "@/components/requests/request-table";
import { EmptyState } from "@/components/states/empty-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";
import { attemptRequestRead, fetchRequestPage, type RequestReadOutcome } from "@/lib/requests/request-api";
import {
  countRequestFilters,
  parseRequestListQuery,
  requestListHref,
  type RequestListQuery,
  type RequestListSearchParams,
} from "@/lib/requests/request-query";
import type { RequestPage } from "@/lib/requests/request-resource";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.requests);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

const PAGE_DESCRIPTION = [
  "Made-to-order requests customers submitted, most recent first.",
  "Filtering, search and paging run server-side against the operational API, so the list shows exactly what Laravel returned and never a locally reordered or filtered page.",
  "Attachment bytes are never fetched here; open a request to inspect its attachment.",
].join(" ");

/**
 * REQ-004 inbox. The page repeats the route requirement before any data access,
 * reads only the documented query parameters, and never renders a failed read as
 * an empty list.
 */
export default async function RequestsPage({ searchParams }: Readonly<{ searchParams: Promise<RequestListSearchParams> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const query = parseRequestListQuery(await searchParams);
  const page = await attemptRequestRead(() => fetchRequestPage(query));

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader title={ROUTE.label} description={PAGE_DESCRIPTION} />
      <RequestListFilters query={query} />
      <RequestListBody page={page} query={query} />
    </PageContainer>
  );
}

function RequestListBody({ page, query }: Readonly<{ page: RequestReadOutcome<RequestPage>; query: RequestListQuery }>) {
  if (!page.ok) return <RequestFailureState failure={page.failure} retryAfterSeconds={page.retryAfterSeconds} />;
  if (page.value.requests.length === 0) {
    if (page.value.pagination.total > 0) {
      return (
        <>
          <EmptyState
            title="No requests on this page"
            description={`Page ${page.value.pagination.current_page} has no requests. There are ${page.value.pagination.total} matching requests on earlier pages.`}
            action={<Button href={requestListHref({ ...query, page: 1 })}>Go to first page</Button>}
          />
          <RequestListPagination pagination={page.value.pagination} query={query} />
        </>
      );
    }
    return emptyState(query);
  }

  return (
    <>
      <RequestTable requests={page.value.requests} />
      <RequestListPagination pagination={page.value.pagination} query={query} />
    </>
  );
}

function emptyState(query: RequestListQuery) {
  if (countRequestFilters(query) > 0) {
    return (
      <EmptyState
        title="No matching requests"
        description="No requests match these filters. Change or clear the filters to see other requests."
        action={<Button href={ADMIN_ROUTE_PATHS.requests}>Clear filters</Button>}
      />
    );
  }

  return <EmptyState title="No requests" description="No requests have been submitted." />;
}
