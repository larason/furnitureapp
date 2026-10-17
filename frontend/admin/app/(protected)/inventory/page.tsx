import type { Metadata } from "next";
import { InventoryFailureState } from "@/components/catalog/inventory-failure-state";
import { InventoryListFilters } from "@/components/catalog/inventory-list-filters";
import { InventoryListPagination } from "@/components/catalog/inventory-list-pagination";
import { InventoryTable } from "@/components/catalog/inventory-table";
import { EmptyState } from "@/components/states/empty-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { hasPermission } from "@/lib/auth/operational-identity";
import { AdminPermissionName } from "@/lib/auth/permission-names";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { adjustInventoryAction, loadInventorySnapshotAction } from "@/lib/catalog/inventory-actions";
import { attemptInventoryRead, fetchInventoryPage, resolveInventoryLabels, type InventoryReadOutcome } from "@/lib/catalog/inventory-api";
import { uniqueInventoryProductIds } from "@/lib/catalog/inventory-label";
import type { InventoryPage } from "@/lib/catalog/inventory-resource";
import {
  countInventoryFilters,
  parseInventoryListQuery,
  type InventoryListQuery,
  type InventoryListSearchParams,
} from "@/lib/catalog/inventory-query";
import { inventoryRows, type InventoryRow } from "@/lib/catalog/inventory-presentation";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.inventory);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

const PAGE_DESCRIPTION = [
  "Stock rows Laravel currently holds, one per variant and warehouse location.",
  "Quantities are authoritative; the reserved figure stays zero while cart and checkout reservations are deferred.",
  "Product and variant names are resolved from the public catalog because the inventory resource deliberately carries only identifiers, so a name Laravel cannot supply is reported as unresolved rather than inferred.",
].join(" ");

/**
 * INV-001 list. The page repeats the route requirement before any data access,
 * reads only the documented query parameters, and resolves display-only product
 * and variant labels from the existing catalog endpoints without touching the
 * frozen inventory contract.
 */
export default async function InventoryPage({ searchParams }: Readonly<{ searchParams: Promise<InventoryListSearchParams> }>) {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  const query = parseInventoryListQuery(await searchParams);
  const page = await attemptInventoryRead(() => fetchInventoryPage(query));
  const rows = page.ok ? inventoryRows(page.value.records, await resolveInventoryLabels(uniqueInventoryProductIds(page.value.records))) : [];
  const canManage = hasPermission(access.identity, AdminPermissionName.INVENTORY_MANAGE);

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader title={ROUTE.label} description={PAGE_DESCRIPTION} />
      <InventoryListFilters query={query} />
      <InventoryListBody page={page} query={query} rows={rows} canManage={canManage} />
    </PageContainer>
  );
}

interface InventoryListBodyProps {
  readonly page: InventoryReadOutcome<InventoryPage>;
  readonly query: InventoryListQuery;
  readonly rows: readonly InventoryRow[];
  readonly canManage: boolean;
}

function InventoryListBody({ page, query, rows, canManage }: Readonly<InventoryListBodyProps>) {
  if (!page.ok) return <InventoryFailureState failure={page.failure} retryAfterSeconds={page.retryAfterSeconds} />;
  if (page.value.records.length === 0) {
    return <EmptyState title="No inventory records found" description={emptyDescription(countInventoryFilters(query))} />;
  }

  return (
    <>
      <InventoryTable
        rows={rows}
        canManage={canManage}
        actions={{ loadSnapshot: loadInventorySnapshotAction, adjust: adjustInventoryAction }}
      />
      <InventoryListPagination pagination={page.value.pagination} query={query} />
    </>
  );
}

function emptyDescription(filterCount: number): string {
  return filterCount > 0
    ? "No inventory records match the current filters. Clear or change the filters to see other records."
    : "The operational API returned no inventory records for this account. This is an empty result, not a failure.";
}
