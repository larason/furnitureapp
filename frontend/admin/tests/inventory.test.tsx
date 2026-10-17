import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { InventoryFailureState } from "../components/catalog/inventory-failure-state";
import { InventoryListFilters } from "../components/catalog/inventory-list-filters";
import { InventoryListPagination } from "../components/catalog/inventory-list-pagination";
import { InventoryTable } from "../components/catalog/inventory-table";
import { AdminPermissionName } from "../lib/auth/permission-names";
import { resolveAdminRouteAccess } from "../lib/auth/route-guard";
import type { AdmissionResult } from "../lib/auth/operational-identity";
import type { OperationalIdentity } from "../lib/auth/types";
import {
  buildInventoryLabels,
  catalogProductName,
  catalogVariantEntries,
  emptyInventoryLabels,
  inventoryProductSubject,
  inventoryVariantSubject,
  uniqueInventoryProductIds,
} from "../lib/catalog/inventory-label";
import {
  INVENTORY_ADJUSTMENT_CONFLICT_MESSAGE,
  INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE,
  INVENTORY_ADJUSTMENT_SUCCESS_MESSAGE,
  INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE,
  INVENTORY_DELTA_ZERO_MESSAGE,
  INVENTORY_BELOW_RESERVED_MESSAGE,
  INVENTORY_NEGATIVE_RESULT_MESSAGE,
  INVENTORY_QUANTITY_DELTA_FIELD,
  INVENTORY_REASON_FIELD,
  INVENTORY_REASON_LABELS,
  INVENTORY_REASON_VALUES,
  inventoryAdjustmentError,
  inventoryAdjustmentFailureOutcome,
  inventoryAdjustmentSeverity,
  inventoryAdjustmentShapeError,
  inventoryAdjustmentSignature,
  parseQuantityDelta,
  previewInventoryQuantity,
  type InventoryAdjustmentActions,
  type InventoryAdjustmentFailure,
} from "../lib/catalog/inventory-mutations";
import {
  formatInventoryDelta,
  formatInventoryQuantity,
  formatInventoryTimestamp,
  inventoryReasonHint,
  inventoryRows,
  inventorySubjectLabel,
  inventoryVariantLabel,
  INVENTORY_PRODUCT_UNAVAILABLE,
  INVENTORY_QUANTITY_UNAVAILABLE,
  INVENTORY_SKU_UNAVAILABLE,
  INVENTORY_TIMESTAMP_UNAVAILABLE,
  INVENTORY_VARIANT_UNAVAILABLE,
} from "../lib/catalog/inventory-presentation";
import {
  countInventoryFilters,
  INVENTORY_LIST_DEFAULT_PER_PAGE,
  inventoryListHref,
  inventoryListRequestQuery,
  parseInventoryListQuery,
} from "../lib/catalog/inventory-query";
import { InventoryResourceError, parseInventoryPage, parseInventoryRecord, type InventoryRecord } from "../lib/catalog/inventory-resource";
import { ADMIN_ROUTE_PATHS } from "../lib/navigation/admin-route-paths";
import { adminRoute, resolveAdminNavigation } from "../lib/navigation/admin-navigation";
import { theme } from "../theme";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const read = (relative: string): string => readFileSync(join(ADMIN_ROOT, relative), "utf8");

const PAGE = read("app/(protected)/inventory/page.tsx");
const ACTIONS = read("lib/catalog/inventory-actions.ts");
const API = read("lib/catalog/inventory-api.ts");
const QUERY = read("lib/catalog/inventory-query.ts");
const RESOURCE = read("lib/catalog/inventory-resource.ts");
const LABEL = read("lib/catalog/inventory-label.ts");
const TABLE = read("components/catalog/inventory-table.tsx");
const FILTERS = read("components/catalog/inventory-list-filters.tsx");
const DIALOG = read("components/catalog/inventory-adjustment-dialog.tsx");
const FAILURE = read("components/catalog/inventory-failure-state.tsx");
const SERVER_REQUEST = read("lib/api/server-request.ts");
const CLIENT = read("lib/api/client.ts");
const LARAVEL_PERMISSIONS = readFileSync(fileURLToPath(new URL("../../../backend/laravel/app/Support/PermissionName.php", import.meta.url)), "utf8");

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const text = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
const withoutImports = (source: string): string => source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");

const STAFF_IDENTITY: OperationalIdentity = { id: "7", name: "Operator", email: "operator@example.test", role: "STAFF", accountState: "ACTIVE", permissions: ["inventory.view"] };
const NOOP_ACTIONS: InventoryAdjustmentActions = {
  loadSnapshot: async () => ({ ok: false, message: "not used" }),
  adjust: async () => ({ status: "success", message: INVENTORY_ADJUSTMENT_SUCCESS_MESSAGE, retryAfterSeconds: null }),
};

function admitted(identity: OperationalIdentity): AdmissionResult {
  return { kind: "admitted", identity };
}

function rawInventory(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: "inv_7",
    product_id: "prd_3",
    variant_id: "var_9",
    warehouse_location: "main",
    quantity: 12,
    reserved_quantity: 0,
    available_quantity: 12,
    updated_at: "2026-09-30T08:00:00.000Z",
    ...overrides,
  };
}

function record(overrides: Partial<InventoryRecord> = {}): InventoryRecord {
  return {
    id: "inv_7",
    productId: "prd_3",
    variantId: "var_9",
    warehouseLocation: "main",
    quantity: 12,
    reservedQuantity: 0,
    availableQuantity: 12,
    updatedAt: "2026-09-30T08:00:00.000Z",
    ...overrides,
  };
}

function resolvedLabels() {
  return buildInventoryLabels([
    { productId: "prd_3", productName: "Oak Dining Chair", variants: [{ id: "var_9", name: "Natural", sku: "OAK-3-NAT" }] },
  ]);
}

// ---------------------------------------------------------------------------
// Authorization and route protection
// ---------------------------------------------------------------------------

test("the inventory page repeats the route requirement before any data access", () => {
  assert.match(PAGE, /requireAdminRouteAccess\(ROUTE\)/);
  assert.match(PAGE, /if \(!access\.granted\) return access\.denial;/);
  const body = withoutImports(PAGE);
  const guard = body.indexOf("await requireAdminRouteAccess(");
  assert.ok(guard > -1, "the page must repeat server-side authorization.");
  for (const access of ["await searchParams", "attemptInventoryRead(", "fetchInventoryPage(", "resolveInventoryLabels("]) {
    const index = body.indexOf(access);
    if (index > -1) assert.ok(guard < index, `authorization must precede ${access}.`);
  }
});

test("the inventory route keeps its URL and exact permission and is no longer a placeholder", () => {
  assert.equal(ADMIN_ROUTE_PATHS.inventory, "/inventory");
  const route = adminRoute(ADMIN_ROUTE_PATHS.inventory);
  assert.equal(route.href, "/inventory");
  assert.equal(route.permission, AdminPermissionName.INVENTORY_VIEW);
  assert.equal(route.permission, "inventory.view");
  assert.equal(route.placeholder, undefined, "the inventory workflow is implemented and is not a placeholder.");
  assert.equal(route.plannedFor, undefined, "an implemented route must not advertise a roadmap phase.");
});

test("inventory.view and inventory.manage are distinct Laravel-backed permissions", () => {
  assert.equal(AdminPermissionName.INVENTORY_VIEW, "inventory.view");
  assert.equal(AdminPermissionName.INVENTORY_MANAGE, "inventory.manage");
  const laravel = new Set([...LARAVEL_PERMISSIONS.matchAll(/case\s+[A-Z_]+\s*=\s*'([^']+)'/g)].map((match) => match[1]));
  assert.ok(laravel.has("inventory.view") && laravel.has("inventory.manage"), "both permissions must exist in the Laravel catalog.");
  assert.equal(/inventory\.edit|inventory\.write|inventory\.adjust/.test(LARAVEL_PERMISSIONS), false, "no permission may be invented.");
});

test("a view-only identity can open inventory but cannot adjust it, and ADMIN gets no implicit bypass", () => {
  const viewOnly: OperationalIdentity = { ...STAFF_IDENTITY, permissions: ["inventory.view"] };
  assert.equal(resolveAdminRouteAccess(admitted(viewOnly), { permission: "inventory.view" }).kind, "granted");
  assert.deepEqual(resolveAdminRouteAccess(admitted(viewOnly), { permission: "inventory.manage" }), { kind: "forbidden" });

  const manager: OperationalIdentity = { ...STAFF_IDENTITY, permissions: ["inventory.view", "inventory.manage"] };
  assert.equal(resolveAdminRouteAccess(admitted(manager), { permission: "inventory.manage" }).kind, "granted");

  const adminWithoutManage: OperationalIdentity = { ...STAFF_IDENTITY, role: "ADMIN", permissions: ["inventory.view"] };
  assert.deepEqual(resolveAdminRouteAccess(admitted(adminWithoutManage), { permission: "inventory.manage" }), { kind: "forbidden" });

  assert.deepEqual(resolveAdminRouteAccess(admitted({ ...viewOnly, permissions: [] }), { permission: "inventory.view" }), { kind: "forbidden" });
});

test("navigation exposes inventory only for an identity holding inventory.view", () => {
  const hrefs = (identity: OperationalIdentity) => resolveAdminNavigation(identity).flatMap((group) => group.items.map((item) => item.href));
  assert.ok(hrefs({ ...STAFF_IDENTITY, permissions: ["inventory.view"] }).includes("/inventory"));
  assert.equal(hrefs({ ...STAFF_IDENTITY, permissions: ["products.manage"] }).includes("/inventory"), false);
  assert.equal(hrefs({ ...STAFF_IDENTITY, role: "ADMIN", permissions: [] }).includes("/inventory"), false);
});

test("server actions re-verify the exact permission because they are directly reachable", () => {
  assert.match(ACTIONS, /^"use server";/);
  assert.equal((ACTIONS.match(/resolveAdminRouteAccess\(await requireOperationalIdentity\(\)/g) ?? []).length, 2, "both actions must re-authorize.");
  assert.match(ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{\s*permission: AdminPermissionName\.INVENTORY_MANAGE,?\s*\}\)/);
  const body = withoutImports(ACTIONS);
  for (const request of ["fetchInventoryRecord(", "submitInventoryAdjustment("]) {
    const index = body.indexOf(request);
    assert.ok(index > -1, `${request} must be present.`);
    assert.ok(body.indexOf("resolveAdminRouteAccess(await requireOperationalIdentity()") < index, `authorization must precede ${request}.`);
  }
  assert.match(ACTIONS, /if \(access\.kind !== "granted"\) return/, "a denied caller must return without touching the API.");
});

test("the adjustment action re-validates the payload shape instead of trusting the caller", () => {
  assert.match(ACTIONS, /inventoryAdjustmentShapeError\(\{ quantityDelta, reason \}\)/);
  assert.match(ACTIONS, /isIdempotencyKey\(idempotencyKey\)/);
  assert.match(ACTIONS, /INVENTORY_IDENTIFIER_PATTERN = \/\^inv_\[0-9a-z\]\+\$\//);
  assert.match(ACTIONS, /readInventoryIdentifier\(inventoryId\)/);
  assert.equal(/parseInt\(|Number\(.*quantityDelta/.test(ACTIONS), false, "a non-number delta must be rejected, never coerced.");
});

test("no bearer token or server transport crosses into a client component", () => {
  for (const [name, source] of [["table", TABLE], ["dialog", DIALOG], ["filters", FILTERS]] as const) {
    assert.match(source, /^"use client";/, `${name} must declare itself a client component.`);
    assert.equal(/server-only|server-request|lib\/api\/client|inventory-api|inventory-actions/.test(source), false, `${name} must not import the server transport.`);
    assert.equal(/Bearer|getToken|session_token|eyJ/.test(source), false, `${name} must not handle a token.`);
  }
  assert.match(PAGE, /from "@\/lib\/catalog\/inventory-actions"/, "the page supplies the Server Actions.");
  assert.match(PAGE, /actions=\{\{ loadSnapshot: loadInventorySnapshotAction, adjust: adjustInventoryAction \}\}/);
  assert.match(SERVER_REQUEST, /await auth\(\)/);
  assert.match(SERVER_REQUEST, /await session\.getToken\(\)/);
  assert.equal(/unstable_cache|force-cache/.test(SERVER_REQUEST), false);
  assert.match(CLIENT, /cache: token \? "no-store" : undefined/);
});

test("the page decides adjustment access from Laravel's effective permissions, never from the role", () => {
  assert.match(PAGE, /hasPermission\(access\.identity, AdminPermissionName\.INVENTORY_MANAGE\)/);
  assert.equal(/role\s*===|isAdmin|ADMIN"/.test(withoutImports(PAGE)), false, "a role must not decide inventory authorization.");
  assert.match(TABLE, /canManage: boolean/);
  assert.match(DIALOG, /canManage \? \(/, "the adjustment form is rendered only for an identity allowed to adjust.");
});

// ---------------------------------------------------------------------------
// Listing, filtering, ordering and pagination
// ---------------------------------------------------------------------------

test("the list uses the approved /api/v1/inventory endpoint and no admin alias", () => {
  assert.match(API, /const INVENTORY_PATH = "\/inventory";/);
  assert.equal(API.includes("/admin/inventory"), false, "the endpoint is /inventory, not an admin alias.");
  assert.match(API, /requestOperationalApi<unknown>\(\{ path: INVENTORY_PATH, query: inventoryListRequestQuery\(query\) \}\)/);
  assert.match(API, /`\$\{INVENTORY_PATH\}\/\$\{encodeURIComponent\(inventoryId\)\}`/);
});

test("the list query accepts only the documented parameters and values", () => {
  const parsed = parseInventoryListQuery({ product: "prd_3", variant: "var_9", warehouse_location: "main", page: "2", per_page: "50" });
  assert.deepEqual(parsed, { product: "prd_3", variant: "var_9", warehouseLocation: "main", page: 2, perPage: 50 });
});

test("unknown, malformed and out-of-range values degrade without inventing a filter", () => {
  assert.deepEqual(parseInventoryListQuery({ variant: "SKU-1", page: "0", per_page: "500", product: "x".repeat(101) }), {
    product: null,
    variant: null,
    warehouseLocation: null,
    page: 1,
    perPage: INVENTORY_LIST_DEFAULT_PER_PAGE,
  });
  assert.equal(parseInventoryListQuery({ per_page: "100" }).perPage, 100);
  assert.equal(parseInventoryListQuery({ variant: "var_9" }).variant, "var_9");
  assert.equal(parseInventoryListQuery({ warehouse_location: "x".repeat(256) }).warehouseLocation, null);
});

test("the request query uses the exact frozen parameter names and emits nothing else", () => {
  const query = parseInventoryListQuery({ product: "prd_3", variant: "var_9", warehouse_location: "main", page: "3", per_page: "25" });
  assert.deepEqual(
    Object.keys(inventoryListRequestQuery(query)).sort(),
    ["page", "per_page", "product", "variant", "warehouse_location"].sort(),
  );
  assert.deepEqual(inventoryListRequestQuery(parseInventoryListQuery({})), { page: 1, per_page: INVENTORY_LIST_DEFAULT_PER_PAGE });
  assert.equal(/["']sort["']|sort_by|sortBy|order_by|orderBy/.test(QUERY + API), false, "no unsupported sort parameter may be sent.");
  assert.equal(/["']search["']/.test(QUERY), false, "the inventory contract has no free-text search filter.");
});

test("pagination links preserve filters and totals come from server metadata", () => {
  const query = parseInventoryListQuery({ product: "prd_3", warehouse_location: "main", page: "2" });
  assert.equal(inventoryListHref({ ...query, page: 1 }), "/inventory?product=prd_3&warehouse_location=main");
  assert.equal(inventoryListHref({ ...query, page: 3 }), "/inventory?product=prd_3&warehouse_location=main&page=3");
  assert.equal(inventoryListHref({ ...query, page: 1, perPage: 50 }), "/inventory?product=prd_3&warehouse_location=main&per_page=50");
  assert.equal(inventoryListHref(parseInventoryListQuery({})), "/inventory", "defaults are omitted from a shareable URL.");
  assert.equal(countInventoryFilters(query), 2);
  assert.equal(countInventoryFilters(parseInventoryListQuery({})), 0);
});

test("the resource is read from the API response and never reconstructed", () => {
  const parsed = parseInventoryRecord(rawInventory({ quantity: 0, reserved_quantity: 0, available_quantity: 0 }));
  assert.equal(parsed.quantity, 0, "zero is a real returned quantity, not an unavailable value.");
  assert.equal(parsed.reservedQuantity, 0);
  assert.equal(parsed.availableQuantity, 0);
  assert.equal(parsed.warehouseLocation, "main");
  assert.equal(parsed.updatedAt, "2026-09-30T08:00:00.000Z");
});

test("an unusable inventory response is rejected rather than guessed", () => {
  assert.throws(() => parseInventoryRecord({}), InventoryResourceError);
  assert.throws(() => parseInventoryRecord(rawInventory({ warehouse_location: "" })), InventoryResourceError);
  assert.throws(() => parseInventoryRecord(rawInventory({ quantity: -1 })), InventoryResourceError);
  assert.throws(() => parseInventoryRecord(rawInventory({ quantity: 1.5 })), InventoryResourceError);
  assert.throws(() => parseInventoryRecord(rawInventory({ available_quantity: "12" })), InventoryResourceError);
  assert.throws(() => parseInventoryPage({ data: [] }), InventoryResourceError, "a response without meta is unreadable.");
  assert.throws(() => parseInventoryPage({ data: [], meta: { pagination: { current_page: 0, per_page: 20, total: 0, last_page: 1 } } }), InventoryResourceError);
});

test("a null updated_at is carried as an unavailable value instead of an error", () => {
  assert.equal(parseInventoryRecord(rawInventory({ updated_at: null })).updatedAt, null);
  const page = parseInventoryPage({ data: [rawInventory()], meta: { pagination: { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true } } });
  assert.deepEqual(page.pagination, { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true });
  assert.equal(formatInventoryTimestamp(null), null);
  assert.equal(INVENTORY_TIMESTAMP_UNAVAILABLE, "Not recorded");
});

test("the resource parser reads only the eight documented fields", () => {
  for (const field of ["id", "product_id", "variant_id", "warehouse_location", "quantity", "reserved_quantity", "available_quantity", "updated_at"]) {
    assert.ok(RESOURCE.includes(`value.${field}`), `${field} must be read from the response.`);
  }
  for (const invented of ["product_name", "variant_name", ".sku", "label", "productName"]) {
    assert.equal(RESOURCE.includes(invented), false, `the inventory resource must not expose ${invented}.`);
  }
});

test("the backend ordering is preserved and no unsupported sort is introduced", () => {
  assert.equal(/sort|orderBy|order_by/.test(API + PAGE + TABLE), false, "the API owns ordering (updated_at DESC, id ASC).");
  assert.equal(/slice\(0,|\.reverse\(\)/.test(PAGE), false, "the returned page must not be reordered or truncated.");
});

test("an API failure is never rendered as an empty inventory list", () => {
  const body = withoutImports(PAGE);
  assert.ok(body.indexOf("if (!page.ok) return <InventoryFailureState") < body.indexOf("<EmptyState"), "the failure branch must precede the empty branch.");
  assert.match(PAGE, /page\.value\.records\.length === 0/);
  assert.match(FAILURE, /if \(failure === "session"\) state = <SessionInvalidState \/>/);
  assert.match(FAILURE, /failure === "forbidden"\) state = <AccessDeniedState \/>/);
  assert.match(FAILURE, /failure === "unavailable"\) state = <ServiceUnavailableState \/>/);
  assert.match(FAILURE, /failure === "throttled"/);
  assert.match(API, /error instanceof InventoryResourceError/, "an unreadable response must be classified explicitly.");
});

// ---------------------------------------------------------------------------
// Product and variant labels
// ---------------------------------------------------------------------------

test("product names and variants are resolved from the documented catalog endpoints", () => {
  assert.match(API, /path: `\$\{CATALOG_PRODUCTS_PATH\}\/\$\{encodeURIComponent\(productId\)\}`/);
  assert.match(API, /path: `\$\{CATALOG_PRODUCTS_PATH\}\/\$\{encodeURIComponent\(productId\)\}\/variants`/);
  assert.match(API, /const CATALOG_PRODUCTS_PATH = "\/products";/);
  assert.equal(API.includes("/admin/products"), false, "inventory.view cannot read the operational product resource.");
  assert.equal(/new URLSearchParams|query:/.test(API.split("resolveInventoryLabels")[1] ?? ""), false, "no undocumented query parameter may be added to the catalog read.");
});

test("duplicate product ids result in deduplicated catalog requests", () => {
  const ids = uniqueInventoryProductIds([record({ id: "inv_1", productId: "prd_3" }), record({ id: "inv_2", productId: "prd_3" }), record({ id: "inv_3", productId: "prd_4" })]);
  assert.deepEqual(ids, ["prd_3", "prd_4"]);
  assert.match(PAGE, /resolveInventoryLabels\(uniqueInventoryProductIds\(page\.value\.records\)\)/);
  assert.match(API, /mapWithConcurrency\(productIds, LABEL_LOOKUP_CONCURRENCY/);
});

test("catalog lookups are bounded rather than one request per row", () => {
  assert.match(API, /const LABEL_LOOKUP_CONCURRENCY = 4;/);
  assert.match(API, /Math\.min\(limit, items\.length\)/);
  assert.equal(/Promise\.all\(productIds\.map/.test(API), false, "an unbounded fan-out is not allowed.");
});

test("the label mapping module stays pure so its behaviour is directly testable", () => {
  assert.match(LABEL, /export function uniqueInventoryProductIds/);
  assert.match(LABEL, /export function buildInventoryLabels/);
  assert.equal(/server-only|fetch\(|requestPublicApi|requestOperationalApi/.test(LABEL), false, "the mapping module must not reach the network or the server transport.");
});

test("a failed catalog lookup is not an inventory failure and never invents a label", () => {
  assert.equal(catalogProductName({ name: "  " }), null);
  assert.equal(catalogProductName({}), null);
  assert.equal(catalogProductName(null), null);
  assert.equal(catalogVariantEntries({ data: [] }), null);
  assert.deepEqual(catalogVariantEntries([{ id: "var_9", name: "Natural", sku: "OAK-3-NAT" }, { id: "var_x" }]), [{ id: "var_9", name: "Natural", sku: "OAK-3-NAT" }]);
  for (const read of ["readCatalogProductName", "readCatalogVariantEntries"]) {
    assert.match(API, new RegExp(`function ${read}[\\s\\S]*?catch \\{\\s*return null;`), `${read} must degrade to an unresolved label.`);
  }
  assert.match(
    PAGE,
    /const rows = page\.ok \? inventoryRows\(page\.value\.records, await resolveInventoryLabels\(uniqueInventoryProductIds\(page\.value\.records\)\)\) : \[\];/,
    "labels are resolved for a successful page only, so a label failure can never replace the inventory outcome.",
  );
});

test("fallback labels are honest about what could not be resolved", () => {
  const labels = resolvedLabels();
  assert.equal(inventorySubjectLabel(inventoryProductSubject(labels, "prd_3")), "Oak Dining Chair");
  assert.equal(inventoryVariantLabel(inventoryVariantSubject(labels, "var_9")), "Natural");
  assert.equal(inventoryVariantSubject(labels, "var_9").sku, "OAK-3-NAT");

  const empty = emptyInventoryLabels();
  assert.equal(inventorySubjectLabel(inventoryProductSubject(empty, "prd_3")), INVENTORY_PRODUCT_UNAVAILABLE);
  assert.equal(inventoryVariantLabel(inventoryVariantSubject(empty, "var_9")), INVENTORY_VARIANT_UNAVAILABLE);
  assert.equal(inventoryVariantSubject(empty, "var_9").sku, null);
  assert.equal(INVENTORY_PRODUCT_UNAVAILABLE, "Product details unavailable");
  assert.equal(INVENTORY_VARIANT_UNAVAILABLE, "Variant details unavailable");
  assert.equal(INVENTORY_SKU_UNAVAILABLE, "SKU unavailable");
});

test("a row keeps its opaque identifiers available for troubleshooting", () => {
  const rows = inventoryRows([record()], resolvedLabels());
  assert.equal(rows.length, 1);
  assert.equal(rows[0]?.product.identifier, "prd_3");
  assert.equal(rows[0]?.variant.identifier, "var_9");
  assert.equal(rows[0]?.record.id, "inv_7");

  const unresolved = inventoryRows([record()], emptyInventoryLabels());
  assert.equal(unresolved[0]?.product.name, null, "an unresolved label is null, never the opaque id posing as a name.");
  assert.equal(inventoryRows([record({ updatedAt: null })], emptyInventoryLabels())[0]?.record.updatedAt, null);
});

test("labels are carried to the client as plain data, not as a Map", () => {
  assert.match(API, /buildInventoryLabels\(lookups\)/);
  assert.match(PAGE, /inventoryRows\(page\.value\.records, await resolveInventoryLabels/);
  assert.match(TABLE, /readonly rows: readonly InventoryRow\[\]/);
  assert.equal(/ReadonlyMap|new Map\(/.test(TABLE + DIALOG), false, "a Map cannot cross the Server/Client boundary in a prop.");
});

// ---------------------------------------------------------------------------
// Adjustment vocabulary and validation
// ---------------------------------------------------------------------------

test("only the documented reason values are offered", () => {
  assert.deepEqual([...INVENTORY_REASON_VALUES], ["STOCK_RECEIPT", "CORRECTION", "DAMAGE", "RETURN", "AUDIT_ADJUSTMENT"]);
  assert.deepEqual(Object.keys(INVENTORY_REASON_LABELS).sort(), [...INVENTORY_REASON_VALUES].sort());
  assert.match(DIALOG, /INVENTORY_REASON_VALUES\.map/);
  assert.equal(/<MenuItem[^>]*value="(?!\{value\})/.test(DIALOG), false, "no reason may be hardcoded outside the contract list.");
});

test("the reason sign rules mirror the backend and reject an invalid combination", () => {
  assert.equal(INVENTORY_QUANTITY_DELTA_FIELD, "quantity_delta");
  assert.equal(INVENTORY_REASON_FIELD, "reason");

  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: 5, reason: "STOCK_RECEIPT" }), null);
  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: 5, reason: "RETURN" }), null);
  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: -5, reason: "DAMAGE" }), null);
  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: 5, reason: "CORRECTION" }), null);
  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: -5, reason: "AUDIT_ADJUSTMENT" }), null);

  assert.match(inventoryAdjustmentShapeError({ quantityDelta: -5, reason: "STOCK_RECEIPT" }) ?? "", /must increase stock/);
  assert.match(inventoryAdjustmentShapeError({ quantityDelta: -5, reason: "RETURN" }) ?? "", /must increase stock/);
  assert.match(inventoryAdjustmentShapeError({ quantityDelta: 5, reason: "DAMAGE" }) ?? "", /must reduce stock/);
  assert.match(inventoryAdjustmentShapeError({ quantityDelta: 5, reason: "NOT_A_REASON" }) ?? "", /Choose an adjustment reason/);
});

test("zero, non-integer, missing and out-of-range quantity changes are handled safely", () => {
  assert.equal(parseQuantityDelta("10"), 10);
  assert.equal(parseQuantityDelta("+10"), 10);
  assert.equal(parseQuantityDelta("-3"), -3);
  assert.equal(parseQuantityDelta(" 4 "), 4);
  assert.equal(parseQuantityDelta("0"), 0);
  assert.equal(parseQuantityDelta("1.5"), null);
  assert.equal(parseQuantityDelta("abc"), null);
  assert.equal(parseQuantityDelta(""), null);
  assert.equal(parseQuantityDelta("1e3"), null);
  assert.equal(parseQuantityDelta("99999999999999999999"), null, "a value beyond safe-integer arithmetic is rejected.");

  assert.equal(inventoryAdjustmentShapeError({ quantityDelta: 0, reason: "CORRECTION" }), INVENTORY_DELTA_ZERO_MESSAGE);
  assert.match(inventoryAdjustmentShapeError({ quantityDelta: Number.NaN, reason: "CORRECTION" }) ?? "", /whole-number/);
});

test("the resulting quantity is previewed and a result below reserved is blocked", () => {
  assert.equal(previewInventoryQuantity(12, 5), 17);
  assert.equal(previewInventoryQuantity(12, -12), 0);
  assert.equal(previewInventoryQuantity(12, -13), null, "a negative result cannot be previewed as a quantity.");
  assert.equal(formatInventoryDelta(10), "+10");
  assert.equal(formatInventoryDelta(-3), "-3");
  assert.equal(formatInventoryQuantity(0), "0");
  assert.equal(formatInventoryQuantity(12), "12");
  assert.equal(formatInventoryQuantity(-1), INVENTORY_QUANTITY_UNAVAILABLE);
  assert.equal(formatInventoryQuantity(1.5), INVENTORY_QUANTITY_UNAVAILABLE);

  assert.equal(inventoryAdjustmentError({ quantity: 12, reservedQuantity: 0, quantityDelta: -5, reason: "CORRECTION" }), null);
  assert.equal(inventoryAdjustmentError({ quantity: 12, reservedQuantity: 8, quantityDelta: -5, reason: "CORRECTION" }), INVENTORY_BELOW_RESERVED_MESSAGE);
  assert.equal(inventoryAdjustmentError({ quantity: 12, reservedQuantity: 0, quantityDelta: -13, reason: "CORRECTION" }), INVENTORY_NEGATIVE_RESULT_MESSAGE);
  assert.equal(inventoryAdjustmentError({ quantity: 12, reservedQuantity: 0, quantityDelta: -5, reason: "DAMAGE" }), null);
});

test("the reason guidance is deterministic and never silently changes the operator's sign", () => {
  assert.match(inventoryReasonHint("STOCK_RECEIPT") ?? "", /positive/);
  assert.match(inventoryReasonHint("DAMAGE") ?? "", /negative/);
  assert.match(inventoryReasonHint("CORRECTION") ?? "", /positive or negative/);
  assert.equal(inventoryReasonHint("NOT_A_REASON"), null);
  assert.match(DIALOG, /helperText=\{inventoryReasonHint\(reason\) \?\? DELTA_DEFAULT_HINT\}/);
  assert.equal(/Math\.abs\(/.test(DIALOG), false, "an entered sign must never be rewritten silently.");
});

test("the dialog blocks submission while the change is invalid or in flight", () => {
  assert.match(DIALOG, /const blocked = pending \|\| snapshotLoading \|\| snapshot === null \|\| advisoryError !== null;/);
  assert.match(DIALOG, /disabled=\{blocked\}/);
  assert.match(DIALOG, /aria-busy=\{pending\}/);
  assert.match(DIALOG, /if \(inFlight\.current \|\| snapshot === null \|\| !canManage\) return;/);
  assert.match(DIALOG, /inFlight\.current = true;/);
  assert.equal(/setTimeout|setInterval/.test(DIALOG), false, "no timer may resubmit an adjustment.");
});

test("the form is controlled so a failed submit keeps the operator's input", () => {
  assert.match(DIALOG, /const \[deltaDraft, setDeltaDraft\] = useState\(""\)/);
  assert.equal(/defaultValue=|defaultChecked=/.test(DIALOG), false, "an uncontrolled field would be reset by React after a failed submit.");
  assert.equal((DIALOG.match(/setDeltaDraft\(/g) ?? []).length, 1, "the draft is cleared in exactly one place.");
  assert.ok(DIALOG.indexOf("setDeltaDraft(", DIALOG.indexOf('if (result.status === "success")')) > 0, "only a confirmed success clears the draft.");
  assert.equal(/setDeltaDraft\(""\)[\s\S]{0,80}catch/.test(DIALOG), false);
});

// ---------------------------------------------------------------------------
// Idempotency lifecycle
// ---------------------------------------------------------------------------

test("the adjustment request carries the key as a transport option and not a body field", () => {
  const adjust = API.slice(API.indexOf("export async function submitInventoryAdjustment"), API.indexOf("export async function resolveInventoryLabels"));
  assert.match(adjust, /const body: Record<string, JsonValue> = \{/);
  assert.match(adjust, /\[INVENTORY_QUANTITY_DELTA_FIELD\]: quantityDelta/);
  assert.match(adjust, /\[INVENTORY_REASON_FIELD\]: reason/);
  assert.match(adjust, /^\s*idempotencyKey,$/m, "the key is a transport option beside the body.");
  const bodyLiteral = adjust.slice(adjust.indexOf("const body"), adjust.indexOf("};"));
  assert.deepEqual(
    [...bodyLiteral.matchAll(/\[([A-Z_]+)\]:/g)].map((match) => match[1]),
    ["INVENTORY_QUANTITY_DELTA_FIELD", "INVENTORY_REASON_FIELD"],
    "only the two documented request fields may be serialized.",
  );
  assert.equal(bodyLiteral.includes("idempotency"), false, "the key must not be a body field.");
  assert.match(API, /method: "POST"/);
});

test("one key belongs to one intent and a new intent gets a new key", () => {
  assert.equal(inventoryAdjustmentSignature({ quantityDelta: 10, reason: "STOCK_RECEIPT" }), "STOCK_RECEIPT:10");
  assert.notEqual(inventoryAdjustmentSignature({ quantityDelta: 10, reason: "STOCK_RECEIPT" }), inventoryAdjustmentSignature({ quantityDelta: 11, reason: "STOCK_RECEIPT" }));
  assert.notEqual(inventoryAdjustmentSignature({ quantityDelta: 10, reason: "STOCK_RECEIPT" }), inventoryAdjustmentSignature({ quantityDelta: 10, reason: "CORRECTION" }));

  assert.match(DIALOG, /const signature = `\$\{snapshot\.id\}:\$\{inventoryAdjustmentSignature\(\{ quantityDelta: quantity, reason \}\)\}`;/);
  assert.match(DIALOG, /intent !== null && intent\.signature === signature \? intent\.key : newIdempotencyKey\(\)/);
  assert.equal((DIALOG.match(/newIdempotencyKey\(\)/g) ?? []).length, 1, "a key is generated in exactly one place.");
});

test("a confirmed success clears the intent so a new adjustment receives a fresh key", () => {
  const success = DIALOG.indexOf('if (result.status === "success")');
  assert.ok(success > -1, "the dialog must branch on a confirmed success.");
  assert.ok(DIALOG.indexOf("onIntentChange(null);", success) > success, "the key is released only after Laravel confirms the change.");
  assert.ok(DIALOG.indexOf("setReloadToken((token) => token + 1);", success) > success, "authoritative data is re-read after a confirmed change.");
  assert.equal(/onIntentChange\(null\)[\s\S]{0,200}catch/.test(DIALOG), false);
});

test("the table owns each row's intent so close and reopen keeps an uncertain retry's key", () => {
  assert.match(TABLE, /useState<Readonly<Record<string, InventoryAdjustmentIntent \| null>>>\(\{\}\)/);
  assert.match(TABLE, /intent=\{intents\[selected\.record\.id\] \?\? null\}/);
  assert.match(TABLE, /onIntentChange=\{setSelectedIntent\}/);
  assert.match(TABLE, /key=\{selected\.record\.id\}/, "the dialog remounts per record so the draft resets while the table keeps the key.");
  assert.equal(/useState<AdjustmentIntent|const \[intent, setIntent\]/.test(DIALOG), false, "the key must outlive the dialog that submitted it.");
});

test("the dialog cannot be dismissed while a submission is in flight", () => {
  assert.match(DIALOG, /function requestClose\(\): void \{\n\s*if \(pending\) return;\n\s*onClose\(\);\n\s*\}/);
  assert.match(DIALOG, /<Dialog open onClose=\{requestClose\}/);
  assert.match(DIALOG, /<Button onClick=\{requestClose\} disabled=\{pending\}>Close<\/Button>/);
});

test("a rejected draft's message is dropped as soon as the draft or reason changes", () => {
  assert.match(DIALOG, /const rejectedStillApplies = rejectedDraft !== null && rejectedDraft\.deltaDraft === deltaDraft && rejectedDraft\.reason === reason;/);
  assert.match(DIALOG, /const visibleError = rejectedStillApplies \? rejectedDraft\.message : advisoryError;/);
  assert.match(DIALOG, /setRejectedDraft\(\{ message: rejection, deltaDraft, reason \}\);/);
});

test("an uncertain outcome keeps the original key and never generates a replacement", () => {
  assert.match(DIALOG, /async function settleAdjustment\(operation: \(\) => Promise<InventoryAdjustmentOutcome>\): Promise<InventoryAdjustmentOutcome>/);
  assert.match(DIALOG, /return \{ status: "uncertain", message: INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE, retryAfterSeconds: null \};/);
  assert.match(INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE, /identical change again to retry/);
  assert.equal(/catch[\s\S]{0,200}newIdempotencyKey/.test(DIALOG), false, "a failed request must never be retried with a new key.");
  const failure = inventoryAdjustmentFailureOutcome({ status: null, errors: [], transportKind: "network", retryAfterSeconds: null });
  assert.equal(failure.status, "uncertain");
  assert.equal(inventoryAdjustmentSeverity("uncertain"), "warning", "an uncertain outcome is not reported as an error or a success.");
});

test("the adjustment action never retries a stock mutation on its own", () => {
  const action = withoutImports(ACTIONS);
  assert.equal(/for \(|while \(|setTimeout|\.retry\(/.test(action), false, "nothing may re-issue a stock mutation automatically.");
  assert.equal((action.match(/await submitInventoryAdjustment\(/g) ?? []).length, 1, "exactly one submission per invocation.");
});

// ---------------------------------------------------------------------------
// Failure classification
// ---------------------------------------------------------------------------

test("every documented adjustment failure keeps its own safe outcome", () => {
  const base: Omit<InventoryAdjustmentFailure, "status"> = { errors: [], transportKind: null, retryAfterSeconds: null };
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 401 }).status, "session");
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 403 }).status, "forbidden");
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 404 }).status, "not-found");
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 409 }).status, "conflict");
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 429, retryAfterSeconds: 12 }).status, "rate-limited");
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 500 }).status, "unavailable");
  assert.match(inventoryAdjustmentFailureOutcome({ ...base, status: 404 }).message, /no longer available/);
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 404 }).message, INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE);
  assert.equal(inventoryAdjustmentFailureOutcome({ ...base, status: 409 }).message, INVENTORY_ADJUSTMENT_CONFLICT_MESSAGE);
  assert.match(inventoryAdjustmentFailureOutcome({ ...base, status: 429, retryAfterSeconds: 12 }).message, /12 seconds/);
});

test("a field validation error prefers Laravel's own message and success is never inferred", () => {
  const reported = inventoryAdjustmentFailureOutcome({
    status: 422,
    errors: [{ code: "INVALID_VALUE", message: "The quantity delta must not be zero.", field: "quantity_delta" }],
    transportKind: null,
    retryAfterSeconds: null,
  });
  assert.equal(reported.status, "invalid");
  assert.equal(reported.message, "The quantity delta must not be zero.");
  assert.equal(inventoryAdjustmentSeverity("invalid"), "error");
  assert.equal(inventoryAdjustmentSeverity("success"), "success");
  assert.match(DIALOG, /role=\{outcome\.status === "success" \? "status" : "alert"\}/);
  assert.equal(/console\.(log|warn|error)/.test(DIALOG + ACTIONS + API), false, "no internal detail may be logged from these paths.");
});

test("the snapshot the operator adjusts against is re-read and a failed re-read is not hidden", () => {
  assert.match(DIALOG, /actionsRef\.current\.loadSnapshot\(recordId\)/);
  assert.match(DIALOG, /setSnapshotMessage\(result\.ok \? null : result\.message\)/);
  assert.match(DIALOG, /INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE/);
  assert.match(ACTIONS, /attemptInventoryRead\(\(\) => fetchInventoryRecord\(identifier\)\)/);
  assert.match(ACTIONS, /SNAPSHOT_MESSAGES\[outcome\.failure\]/);
});

test("the dialog announces the in-flight read and reserves unavailable for a failed read", () => {
  assert.match(DIALOG, /const \[settledReloadToken, setSettledReloadToken\] = useState<number \| null>\(null\)/);
  assert.match(DIALOG, /const snapshotLoading = settledReloadToken !== reloadToken;/);
  assert.equal((DIALOG.match(/setSettledReloadToken\(reloadToken\)/g) ?? []).length, 2, "both an ok and a failed read settle the pending state.");
  assert.match(DIALOG, /<Typography role="status" variant="body2" color="text\.secondary">/, "the read is announced, not implied by placeholders.");
  assert.match(DIALOG, /Reading the current inventory record/);

  const loading = DIALOG.indexOf("snapshotLoading ? (");
  assert.ok(loading > -1, "the snapshot section branches on the loading state.");
  const settled = DIALOG.indexOf(") : (", loading);
  assert.ok(settled > loading, "the settled branch follows the pending branch.");

  const loadingBranch = DIALOG.slice(loading, settled);
  assert.equal(loadingBranch.includes("INVENTORY_QUANTITY_UNAVAILABLE"), false, "a pending read never renders an unavailable placeholder.");
  assert.ok(DIALOG.indexOf("INVENTORY_QUANTITY_UNAVAILABLE", settled) > settled, "unavailable is reserved for the settled, failed read.");
});

test("a successful adjustment revalidates the inventory route", () => {
  assert.match(ACTIONS, /revalidatePath\(ADMIN_ROUTE_PATHS\.inventory\)/);
  const body = withoutImports(ACTIONS);
  const revalidate = body.indexOf("revalidatePath(ADMIN_ROUTE_PATHS.inventory)");
  assert.ok(revalidate > body.indexOf("await submitInventoryAdjustment("), "revalidation follows the confirmed request.");
  assert.ok(revalidate > body.indexOf("catch (error)"), "a failed request must return before revalidation.");
  assert.match(ACTIONS, /return inventoryAdjustmentSuccess\(\);/);
});

// ---------------------------------------------------------------------------
// Scope boundaries
// ---------------------------------------------------------------------------

test("no new endpoint, sort, export, bulk or creation capability is introduced", () => {
  const sources = API + ACTIONS + TABLE + DIALOG + PAGE + FILTERS;
  for (const forbidden of ["/admin/inventory", "csv", "bulk", "variants/", "inventory/create", "method: \"PATCH\"", "method: \"DELETE\"", "sort_order"]) {
    assert.equal(sources.includes(forbidden), false, `unexpected ${forbidden} in the inventory module.`);
  }
  assert.equal((API.match(/method: "POST"/g) ?? []).length, 1, "adjust is the only mutation.");
  assert.equal(/createAdminApiClient|fetch\(/.test(ACTIONS + TABLE + DIALOG + PAGE), false, "no second fetch path may exist.");
  assert.equal(/audit-logs|auditLogs/.test(ACTIONS + PAGE + DIALOG), false, "the audit-log screen belongs to its own phase.");
});

test("the page keeps loading, empty and failure states distinct and fabricates nothing", () => {
  assert.match(PAGE, /<EmptyState title="No inventory records found"/);
  assert.match(PAGE, /description=\{emptyDescription\(countInventoryFilters\(query\)\)\}/);
  assert.equal(/revenue|sales|analytics|forecast|sample|dummy|fixture|mock/i.test(PAGE), false);
  assert.equal(/setInterval|setTimeout/.test(PAGE), false, "the page must not poll the API.");
  assert.equal(/component="main"/.test(PAGE), false, "the shell owns the single main landmark.");
  assert.match(PAGE, /<PageContainer aria-labelledby="page-title">/);
});

test("the customer catalog and Flutter application are untouched by this phase", () => {
  for (const source of [PAGE, ACTIONS, API, TABLE, DIALOG, FILTERS]) {
    assert.equal(/frontend\/web|frontend\/mobile|flutter/i.test(source), false);
  }
});

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

test("the inventory table renders only API-returned values with a named action", () => {
  const markup = render(<InventoryTable rows={inventoryRows([record()], resolvedLabels())} canManage actions={NOOP_ACTIONS} />);
  const visible = text(markup);
  for (const value of ["Oak Dining Chair", "prd_3", "Natural", "SKU OAK-3-NAT", "var_9", "main"]) {
    assert.ok(visible.includes(value), `the row must render ${value}.`);
  }
  assert.deepEqual([...markup.matchAll(/<th[^>]*>(.*?)<\/th>/g)].map((match) => text(match[1])), [
    "Product",
    "Variant",
    "Warehouse location",
    "Quantity",
    "Reserved",
    "Available",
    "Last updated",
    "Actions",
  ]);
  assert.match(markup, /aria-label="Inspect and adjust inventory inv_7 for Oak Dining Chair at main"/);
  const formattedTimestamp = formatInventoryTimestamp(record().updatedAt);
  assert.ok(formattedTimestamp, "the recorded timestamp must format for display.");
  assert.ok(visible.includes(formattedTimestamp), "the timestamp uses the established presentation.");
  assert.ok(visible.includes("Adjust stock"));
});

test("a zero quantity renders as zero and an unresolved label never becomes a name", () => {
  const zero = render(<InventoryTable rows={inventoryRows([record({ quantity: 0, reservedQuantity: 0, availableQuantity: 0 })], emptyInventoryLabels())} canManage={false} actions={NOOP_ACTIONS} />);
  const visible = text(zero);
  assert.ok(visible.includes("Product details unavailable"), "an unresolved product is stated, not disguised as an id.");
  assert.ok(visible.includes("Variant details unavailable"));
  assert.ok(visible.includes("SKU unavailable"));
  assert.match(zero, /aria-label="Inspect inventory inv_7 for Product details unavailable at main"/);
  assert.ok(/<td[^>]*>0<\/td>/.test(zero), "a reserved quantity of zero must render as zero, not as unavailable.");
  assert.equal(visible.includes("Adjust stock"), false, "a view-only operator gets no adjustment control.");
});

test("the filter form offers only supported filters and keeps state in the URL", () => {
  const markup = render(<InventoryListFilters query={parseInventoryListQuery({ product: "prd_3", per_page: "50" })} />);
  const visible = text(markup);
  for (const label of ["Product identifier", "Variant identifier", "Warehouse location", "Apply filters", "Clear filters"]) {
    assert.ok(visible.includes(label), `the filter form must expose ${label}.`);
  }
  assert.match(markup, /name="product"/);
  assert.match(markup, /name="variant"/);
  assert.match(markup, /name="warehouse_location"/);
  assert.match(markup, /name="per_page" value="50"/);
  assert.equal(/name="page"/.test(markup), false, "applying filters must reset pagination to the first page.");
  assert.equal(/name="search"|name="sort"/.test(markup), false, "no unsupported filter may exist.");
  assert.match(FILTERS, /method="get"/);
  assert.equal(/fetch\(|useEffect/.test(FILTERS), false, "filtering must not issue a request per keystroke.");
  assert.match(FILTERS, /not a product-name search/, "an exact-identifier filter must say so.");
  assert.match(FILTERS, /no approved endpoint enumerates the values/);
  assert.equal(/MenuItem|select\b/.test(FILTERS), false, "a selector must not imply a complete option set.");
});

test("pagination renders the server's totals and links", () => {
  const markup = render(<InventoryListPagination pagination={{ current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true }} query={parseInventoryListQuery({ product: "prd_3" })} />);
  assert.ok(text(markup).includes("Page 2 of 3 · 45 inventory records"));
  assert.match(markup, /href="\/inventory\?product=prd_3"/);
  assert.match(markup, /href="\/inventory\?product=prd_3&amp;page=3"/);
});

test("a rate-limited read renders a distinct, retry-aware message", () => {
  assert.match(text(render(<InventoryFailureState failure="throttled" retryAfterSeconds={30} />)), /Too many requests/);
  assert.match(text(render(<InventoryFailureState failure="throttled" retryAfterSeconds={30} />)), /30 seconds/);
  assert.match(text(render(<InventoryFailureState failure="invalid" retryAfterSeconds={null} />)), /could not read/);
});
