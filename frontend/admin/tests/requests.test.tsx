import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { RequestAttachmentPanel } from "../components/requests/request-attachment-panel";
import { RequestDetailView } from "../components/requests/request-detail-view";
import { RequestFailureState } from "../components/requests/request-failure-state";
import { RequestListFilters } from "../components/requests/request-list-filters";
import { RequestListPagination } from "../components/requests/request-list-pagination";
import { RequestOperationalForm } from "../components/requests/request-operational-form";
import { RequestTable } from "../components/requests/request-table";
import { AdminPermissionName } from "../lib/auth/permission-names";
import { resolveAdminRouteAccess } from "../lib/auth/route-guard";
import type { AdmissionResult } from "../lib/auth/operational-identity";
import type { OperationalIdentity } from "../lib/auth/types";
import { ADMIN_ROUTE_PATHS, ADMIN_REQUEST_PATHS } from "../lib/navigation/admin-route-paths";
import { adminRoute, resolveAdminNavigation } from "../lib/navigation/admin-navigation";
import {
  MAX_STAFF_NOTES,
  REQUEST_NOTES_TOO_LONG_MESSAGE,
  REQUEST_STATUS_LABELS,
  REQUEST_STATUS_NO_CHANGE,
  REQUEST_STATUS_VALUES,
  REQUEST_UPDATE_CONFLICT_MESSAGE,
  REQUEST_UPDATE_NOT_FOUND_MESSAGE,
  REQUEST_UPDATE_SESSION_MESSAGE,
  REQUEST_UPDATE_SUCCESS_MESSAGE,
  REQUEST_UPDATE_UNCERTAIN_MESSAGE,
  REQUEST_UPDATE_UNAVAILABLE_MESSAGE,
  REQUEST_UPDATE_UNAUTHORIZED_MESSAGE,
  REQUEST_VIEW_UNAUTHORIZED_MESSAGE,
  isRequestStatusValue,
  normalizeStaffNotes,
  requestNotesError,
  requestStatusLabel,
  requestStatusOptionError,
  requestStatusTransitions,
  requestUpdateFailureOutcome,
  requestUpdateFields,
  requestUpdateRejection,
  requestUpdateSeverity,
  type RequestUpdateActions,
  type RequestUpdateFailure,
} from "../lib/requests/request-mutations";
import {
  attachmentContentTypeLabel,
  formatAttachmentSize,
  formatRequestDimensions,
  formatRequestQuantity,
  formatRequestTimestamp,
  requestContactLines,
  requestFurnitureLabel,
  requestHasAttachment,
  requestIdentityLabel,
  requestNotesDisplay,
  requestReferenceDisplay,
  requestStatusDisplay,
  REQUEST_ANONYMOUS_SUBMISSION,
  REQUEST_ATTACHMENT_NONE,
  REQUEST_ATTACHMENT_TTL_NOTICE,
  REQUEST_ATTACHMENT_UNAVAILABLE,
  REQUEST_CUSTOM_FURNITURE,
  REQUEST_NOTES_EMPTY,
  REQUEST_NOTES_UNAUTHORIZED,
  REQUEST_NOT_PROVIDED,
  REQUEST_QUANTITY_UNSPECIFIED,
  REQUEST_TIMESTAMP_UNAVAILABLE,
} from "../lib/requests/request-presentation";
import {
  countRequestFilters,
  parseRequestListQuery,
  REQUEST_LIST_DEFAULT_PER_PAGE,
  requestListHref,
  requestListRequestQuery,
} from "../lib/requests/request-query";
import {
  parseOperationalRequest,
  parseRequestPage,
  RequestResourceError,
  type OperationalRequest,
} from "../lib/requests/request-resource";
import { theme } from "../theme";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const read = (relative: string): string => readFileSync(join(ADMIN_ROOT, relative), "utf8");

const PAGE = read("app/(protected)/requests/page.tsx");
const DETAIL_PAGE = read("app/(protected)/requests/[request]/page.tsx");
const ACTIONS = read("lib/requests/request-actions.ts");
const API = read("lib/requests/request-api.ts");
const QUERY = read("lib/requests/request-query.ts");
const RESOURCE = read("lib/requests/request-resource.ts");
const MUTATIONS = read("lib/requests/request-mutations.ts");
const PRESENTATION = read("lib/requests/request-presentation.ts");
const TABLE = read("components/requests/request-table.tsx");
const FILTERS = read("components/requests/request-list-filters.tsx");
const PAGINATION = read("components/requests/request-list-pagination.tsx");
const FAILURE = read("components/requests/request-failure-state.tsx");
const ATTACHMENT = read("components/requests/request-attachment-panel.tsx");
const FORM = read("components/requests/request-operational-form.tsx");
const DETAIL_VIEW = read("components/requests/request-detail-view.tsx");
const SERVER_REQUEST = read("lib/api/server-request.ts");
const CLIENT = read("lib/api/client.ts");
const MODULE = [PAGE, DETAIL_PAGE, ACTIONS, API, QUERY, RESOURCE, MUTATIONS, PRESENTATION, TABLE, FILTERS, PAGINATION, FAILURE, ATTACHMENT, FORM, DETAIL_VIEW].join("\n");
const LARAVEL_PERMISSIONS = readFileSync(fileURLToPath(new URL("../../../backend/laravel/app/Support/PermissionName.php", import.meta.url)), "utf8");

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const text = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
const withoutImports = (source: string): string => source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");

const STAFF_IDENTITY: OperationalIdentity = { id: "7", name: "Operator", email: "operator@example.test", role: "STAFF", accountState: "ACTIVE", permissions: ["requests.view"] };
const NOOP_ACTIONS: RequestUpdateActions = {
  update: async () => ({ status: "success", message: REQUEST_UPDATE_SUCCESS_MESSAGE, retryAfterSeconds: null, fieldErrors: [] }),
};

function admitted(identity: OperationalIdentity): AdmissionResult {
  return { kind: "admitted", identity };
}

function rawRequest(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: "req_7",
    product_id: "prd_3",
    product: { id: "prd_3", name: "Oak Dining Chair", slug: "oak-dining-chair" },
    quantity: 4,
    name: "Asha Mwinyi",
    phone: "+255700000000",
    email: "asha@example.test",
    dimensions: { length: 120, width: 60, height: 75, unit: "cm" },
    material: "Oak",
    color: "Natural",
    notes: "Please deliver in the morning.",
    request_status: "SUBMITTED",
    request_reference: "REQ-2026-0007",
    staff_internal_notes: "Call before delivery.",
    user_id: "user_9",
    attachments: [],
    created_at: "2026-10-01T09:00:00.000Z",
    updated_at: "2026-10-02T10:00:00.000Z",
    ...overrides,
  };
}

function request(overrides: Record<string, unknown> = {}): OperationalRequest {
  return parseOperationalRequest(rawRequest(overrides));
}

function failure(status: number | null, extra: Partial<RequestUpdateFailure> = {}): RequestUpdateFailure {
  return { status, errors: [], transportKind: null, retryAfterSeconds: null, ...extra };
}

// ---------------------------------------------------------------------------
// Authorization and route protection
// ---------------------------------------------------------------------------

test("the requests routes repeat the requirement before any data access", () => {
  for (const [name, source, accesses] of [
    ["inbox", PAGE, ["await searchParams", "attemptRequestRead(", "fetchRequestPage("]],
    ["detail", DETAIL_PAGE, ["await params", "attemptRequestRead(", "fetchRequest("]],
  ] as const) {
    assert.match(source, /requireAdminRouteAccess\(ROUTE\)/, `${name} must keep the route guard.`);
    assert.match(source, /if \(!access\.granted\) return access\.denial;/, `${name} must render the denial state.`);
    const body = withoutImports(source);
    const guard = body.indexOf("await requireAdminRouteAccess(ROUTE)");
    assert.ok(guard > -1, `${name} must repeat server-side authorization.`);
    for (const access of accesses) {
      const index = body.indexOf(access);
      if (index > -1) assert.ok(guard < index, `${name} must authorize before ${access}.`);
    }
  }
});

test("the requests route keeps its URL and exact permission and is no longer a placeholder", () => {
  assert.equal(ADMIN_ROUTE_PATHS.requests, "/requests");
  assert.equal(ADMIN_REQUEST_PATHS.list, "/requests");
  assert.equal(ADMIN_REQUEST_PATHS.detail("req_7"), "/requests/req_7");
  const route = adminRoute(ADMIN_ROUTE_PATHS.requests);
  assert.equal(route.href, "/requests");
  assert.equal(route.permission, AdminPermissionName.REQUESTS_VIEW);
  assert.equal(route.permission, "requests.view");
  assert.equal(route.placeholder, undefined, "the request workflow is implemented and is not a placeholder.");
  assert.equal(route.plannedFor, undefined, "an implemented route must not advertise a roadmap phase.");
});

test("requests.view and requests.manage are distinct Laravel-backed permissions", () => {
  assert.equal(AdminPermissionName.REQUESTS_VIEW, "requests.view");
  assert.equal(AdminPermissionName.REQUESTS_MANAGE, "requests.manage");
  const laravel = new Set([...LARAVEL_PERMISSIONS.matchAll(/case\s+[A-Z_]+\s*=\s*'([^']+)'/g)].map((match) => match[1]));
  assert.ok(laravel.has("requests.view") && laravel.has("requests.manage"), "both permissions must exist in the Laravel catalog.");
  assert.equal(/requests\.(edit|write|reply|delete|cancel)/.test(LARAVEL_PERMISSIONS), false, "no request permission may be invented.");
});

test("a view-only identity can open requests but cannot manage them, and ADMIN gets no implicit bypass", () => {
  const viewOnly: OperationalIdentity = { ...STAFF_IDENTITY, permissions: ["requests.view"] };
  assert.equal(resolveAdminRouteAccess(admitted(viewOnly), { permission: "requests.view" }).kind, "granted");
  assert.deepEqual(resolveAdminRouteAccess(admitted(viewOnly), { permission: "requests.manage" }), { kind: "forbidden" });

  const manager: OperationalIdentity = { ...STAFF_IDENTITY, permissions: ["requests.view", "requests.manage"] };
  assert.equal(resolveAdminRouteAccess(admitted(manager), { permission: "requests.manage" }).kind, "granted");

  const adminWithoutManage: OperationalIdentity = { ...STAFF_IDENTITY, role: "ADMIN", permissions: ["requests.view"] };
  assert.deepEqual(resolveAdminRouteAccess(admitted(adminWithoutManage), { permission: "requests.manage" }), { kind: "forbidden" });

  assert.deepEqual(resolveAdminRouteAccess(admitted({ ...viewOnly, permissions: [] }), { permission: "requests.view" }), { kind: "forbidden" });
});

test("navigation exposes requests only for an identity holding requests.view", () => {
  const hrefs = (identity: OperationalIdentity) => resolveAdminNavigation(identity).flatMap((group) => group.items.map((item) => item.href));
  assert.ok(hrefs({ ...STAFF_IDENTITY, permissions: ["requests.view"] }).includes("/requests"));
  assert.equal(hrefs({ ...STAFF_IDENTITY, permissions: ["products.manage"] }).includes("/requests"), false);
  assert.equal(hrefs({ ...STAFF_IDENTITY, role: "ADMIN", permissions: [] }).includes("/requests"), false);
});

test("the update action repeats the exact permission because it is directly reachable", () => {
  assert.match(ACTIONS, /^"use server";/);
  assert.match(ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{ permission: AdminPermissionName\.REQUESTS_MANAGE \}\)/);
  assert.match(ACTIONS, /if \(access\.kind !== "granted"\) return/, "a denied caller must return without touching the API.");
  const body = withoutImports(ACTIONS);
  const guard = body.indexOf("resolveAdminRouteAccess(await requireOperationalIdentity()");
  const call = body.indexOf("await submitRequestUpdate(");
  assert.ok(guard > -1 && call > -1 && guard < call, "authorization must precede the API call.");
});

test("the update action re-validates the payload shape instead of trusting the caller", () => {
  assert.match(ACTIONS, /REQUEST_IDENTIFIER_PATTERN = \/\^req_\[0-9a-z\]\+\$\//);
  assert.match(ACTIONS, /readRequestIdentifier\(record\.requestId\)/);
  assert.match(ACTIONS, /isRequestStatusValue\(value\[REQUEST_UPDATE_STATUS_KEY\]\)/);
  assert.match(ACTIONS, /keys\.some\(\(key\) => key !== REQUEST_UPDATE_STATUS_KEY && key !== REQUEST_UPDATE_NOTES_KEY\)/);
  assert.match(ACTIONS, /trimmed\.length > MAX_STAFF_NOTES/);
  assert.equal(/parseInt\(|Number\(.*requestStatus/.test(ACTIONS), false, "a malformed field must be rejected, never coerced.");
});

test("no bearer token or server transport crosses into a client component", () => {
  for (const [name, source] of [["table", TABLE], ["form", FORM]] as const) {
    assert.match(source, /^"use client";/, `${name} must declare itself a client component.`);
    assert.equal(/server-only|server-request|lib\/api\/client|request-api|request-actions/.test(source), false, `${name} must not import the server transport.`);
    assert.equal(/Bearer|getToken|session_token|eyJ/.test(source), false, `${name} must not handle a token.`);
  }
  for (const [name, source] of [["filters", FILTERS], ["pagination", PAGINATION], ["attachment", ATTACHMENT], ["detail view", DETAIL_VIEW]] as const) {
    assert.equal(/server-only|server-request|lib\/api\/client|request-api|request-actions/.test(source), false, `${name} must not import the server transport or the actions directly.`);
    assert.equal(/Bearer|getToken|session_token|eyJ/.test(source), false, `${name} must not handle a token.`);
  }
  assert.equal(/import "server-only"|server-request|lib\/api\/client|request-actions|requestOperationalApi|submitRequestUpdate/.test(FAILURE), false, "the failure state keeps the transport out of reach.");
  assert.match(FAILURE, /import type \{ RequestApiFailure \} from "@\/lib\/requests\/request-api";/, "the failure union is imported as a build-erased type only.");
  assert.match(DETAIL_PAGE, /from "@\/lib\/requests\/request-actions"/, "the detail page supplies the Server Action.");
  assert.match(DETAIL_PAGE, /actions=\{\{ update: updateRequestAction \}\}/);
  assert.match(SERVER_REQUEST, /await auth\(\)/);
  assert.match(SERVER_REQUEST, /await session\.getToken\(\)/);
  assert.equal(/unstable_cache|force-cache/.test(SERVER_REQUEST), false);
  assert.match(CLIENT, /cache: token \? "no-store" : undefined/);
});

test("management access is decided from Laravel's effective permissions, never from the role", () => {
  assert.match(DETAIL_PAGE, /hasPermission\(access\.identity, AdminPermissionName\.REQUESTS_MANAGE\)/);
  assert.match(DETAIL_PAGE, /hasPermission\(access\.identity, AdminPermissionName\.PRODUCTS_MANAGE\)/);
  assert.equal(/role\s*===|isAdmin|"ADMIN"/.test(withoutImports(DETAIL_PAGE)), false, "a role must not decide request authorization.");
  assert.match(FORM, /canManage: boolean/);
  assert.match(FORM, /canManage \? \(/, "the editing controls render only for an identity allowed to manage requests.");
});

// ---------------------------------------------------------------------------
// Listing, filtering, ordering and pagination
// ---------------------------------------------------------------------------

test("the module uses the approved /api/v1/requests endpoints and no admin alias", () => {
  assert.match(API, /const REQUESTS_PATH = "\/requests";/);
  assert.equal(API.includes("/admin/requests"), false, "the endpoint is /requests, not an admin alias.");
  assert.match(API, /requestOperationalApi<unknown>\(\{ path: REQUESTS_PATH, query: requestListRequestQuery\(query\) \}\)/);
  assert.match(API, /`\$\{REQUESTS_PATH\}\/\$\{encodeURIComponent\(requestId\)\}`/);
  assert.equal((API.match(/method: "PATCH"/g) ?? []).length, 1, "the update is the only mutation.");
  assert.equal(/method: "POST"|method: "PUT"|method: "DELETE"/.test(API), false);
});

test("the list query accepts only the documented parameters and values", () => {
  assert.deepEqual(
    parseRequestListQuery({ search: "oak", request_status: "IN_REVIEW", product_id: "prd_3", created_from: "2026-01-02", created_to: "2026-01-03", page: "2", per_page: "50" }),
    { search: "oak", requestStatus: "IN_REVIEW", productId: "prd_3", createdFrom: "2026-01-02", createdTo: "2026-01-03", page: 2, perPage: 50 },
  );
});

test("unknown, malformed and out-of-range values degrade without inventing a filter", () => {
  const parsed = parseRequestListQuery({ request_status: "PENDING", created_from: "2026-13-01", created_to: "2026-02-30", page: "0", per_page: "500", product_id: "x".repeat(101) });
  assert.deepEqual(parsed, { search: null, requestStatus: null, productId: null, createdFrom: null, createdTo: null, page: 1, perPage: REQUEST_LIST_DEFAULT_PER_PAGE });
  assert.equal(parseRequestListQuery({ per_page: "100" }).perPage, 100);
  assert.equal(parseRequestListQuery({ created_from: "2026-02-28" }).createdFrom, "2026-02-28");
  assert.equal(parseRequestListQuery({ search: "x".repeat(101) }).search, null);
});

test("the request query uses the exact frozen parameter names and converts date bounds to UTC", () => {
  const query = parseRequestListQuery({ search: "oak", request_status: "CLOSED", product_id: "prd_3", created_from: "2026-01-02", created_to: "2026-01-03", page: "3", per_page: "25" });
  assert.deepEqual(
    Object.keys(requestListRequestQuery(query)).sort(),
    ["created_from", "created_to", "page", "per_page", "product_id", "request_status", "search"].sort(),
  );
  assert.deepEqual(requestListRequestQuery(query), {
    search: "oak",
    request_status: "CLOSED",
    product_id: "prd_3",
    created_from: "2026-01-02T00:00:00Z",
    created_to: "2026-01-03T23:59:59Z",
    page: 3,
    per_page: 25,
  });
  assert.deepEqual(requestListRequestQuery(parseRequestListQuery({})), { page: 1, per_page: REQUEST_LIST_DEFAULT_PER_PAGE });
  assert.equal(/["']sort["']|sort_by|sortBy|order_by|orderBy/.test(QUERY + API), false, "the backend owns ordering (created_at DESC, id ASC).");
});

test("pagination links preserve filters, reset on filter change and come from server metadata", () => {
  const query = parseRequestListQuery({ search: "oak", request_status: "IN_REVIEW", page: "2", per_page: "50" });
  assert.equal(requestListHref({ ...query, page: 1 }), "/requests?search=oak&request_status=IN_REVIEW&per_page=50");
  assert.equal(requestListHref({ ...query, page: 3 }), "/requests?search=oak&request_status=IN_REVIEW&page=3&per_page=50");
  assert.equal(requestListHref(parseRequestListQuery({})), "/requests", "defaults are omitted from a shareable URL.");
  assert.equal(countRequestFilters(query), 2);
  assert.equal(countRequestFilters(parseRequestListQuery({})), 0);
  assert.equal(/name="page"/.test(render(<RequestListFilters query={query} />)), false, "applying filters must reset pagination to the first page.");
});

test("the resource is read from the API response and never reconstructed", () => {
  const parsed = request({ quantity: null, product_id: null, product: null, staff_internal_notes: null, user_id: null, dimensions: null, material: null, color: null, notes: null, phone: null, email: null });
  assert.equal(parsed.id, "req_7");
  assert.equal(parsed.requestReference, "REQ-2026-0007");
  assert.equal(parsed.requestStatus, "SUBMITTED");
  assert.equal(parsed.productId, null);
  assert.equal(parsed.product, null);
  assert.equal(parsed.quantity, null);
  assert.equal(parsed.dimensions, null);
  assert.equal(parsed.material, null);
  assert.equal(parsed.notes, null);
  assert.equal(parsed.staffInternalNotes, null);
  assert.equal(parsed.userId, null);
});

test("attachments are parsed from the response, including a null delivery url", () => {
  const parsed = request({
    attachments: [
      { id: "att_1", filename: "measurements.pdf", content_type: "application/pdf", size: 1536, url: "https://api.example.test/api/v1/requests/req_7/attachments/att_1?sig=x" },
      { id: "att_2", filename: "room.jpg", content_type: "image/jpeg", size: 2048, url: null },
    ],
  });
  assert.equal(parsed.attachments.length, 2);
  assert.equal(parsed.attachments[0].filename, "measurements.pdf");
  assert.equal(parsed.attachments[0].contentType, "application/pdf");
  assert.equal(parsed.attachments[0].size, 1536);
  assert.ok(parsed.attachments[0].url?.startsWith("https://"));
  assert.equal(parsed.attachments[1].url, null);
  assert.equal(requestHasAttachment(parsed), true);
  assert.equal(requestHasAttachment(request({ attachments: [] })), false);
});

test("an unusable request response is rejected rather than guessed", () => {
  assert.throws(() => parseOperationalRequest({}), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ request_reference: "" })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ quantity: -1 })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ quantity: 1.5 })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ attachments: {} })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ attachments: [{ id: "att_1", filename: "x", content_type: "application/pdf", size: "big", url: null }] })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ product_id: 5 })), RequestResourceError, "a malformed nullable discriminator is rejected, never read as absent.");
  assert.throws(() => parseOperationalRequest(rawRequest({ user_id: 5 })), RequestResourceError);
  assert.throws(() => parseOperationalRequest(rawRequest({ product: { id: "prd_3", name: "Chair" } })), RequestResourceError);
  assert.throws(() => parseRequestPage({ data: [] }), RequestResourceError, "a response without meta is unreadable.");
  assert.throws(() => parseRequestPage({ data: [], meta: { pagination: { current_page: 0, per_page: 20, total: 0, last_page: 1 } } }), RequestResourceError);
});

test("a readable page carries the documented pagination metadata", () => {
  const page = parseRequestPage({ data: [rawRequest()], meta: { pagination: { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true } } });
  assert.equal(page.requests.length, 1);
  assert.deepEqual(page.pagination, { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true });
});

test("the resource parser reads only the documented request fields", () => {
  for (const field of ["id", "product_id", "product", "quantity", "name", "phone", "email", "dimensions", "material", "color", "notes", "request_status", "request_reference", "staff_internal_notes", "user_id", "attachments", "created_at", "updated_at"]) {
    assert.ok(RESOURCE.includes(`value.${field}`), `${field} must be read from the response.`);
  }
  for (const invented of ["status_history", "timeline", "reply", "customer_profile", "converted_order"]) {
    assert.equal(RESOURCE.includes(invented), false, `the request resource must not expose ${invented}.`);
  }
});

test("an API failure is never rendered as an empty request list", () => {
  const body = withoutImports(PAGE);
  assert.ok(body.indexOf("if (!page.ok) return <RequestFailureState") < body.indexOf("<EmptyState"), "the failure branch must precede the empty branch.");
  assert.match(PAGE, /page\.value\.requests\.length === 0/);
  assert.match(FAILURE, /if \(failure === "session"\) state = <SessionInvalidState \/>/);
  assert.match(FAILURE, /failure === "forbidden"\) state = <AccessDeniedState \/>/);
});

test("empty data is distinguishable from a failed request", () => {
  assert.match(PAGE, /No requests have been submitted\./);
  assert.match(PAGE, /No requests match these filters\./);
  assert.match(PAGE, /countRequestFilters\(query\)/);
  assert.match(PAGE, /description="The request service returned a response this application could not read\."|RequestFailureState/);
});

// ---------------------------------------------------------------------------
// Detail rendering decisions
// ---------------------------------------------------------------------------

test("a 404 surfaces the framework not-found and unreadable payloads are not reconstructed", () => {
  assert.match(DETAIL_PAGE, /import \{ notFound \} from "next\/navigation";/);
  assert.match(DETAIL_PAGE, /if \(!result\.ok && result\.failure === "not-found"\) notFound\(\);/);
  assert.match(FAILURE, /could not read/, "an unreadable response keeps its own distinct treatment.");
  assert.equal(/failure === "(session|forbidden|unavailable|invalid|throttled)"[\s\S]*notFound\(\)/.test(DETAIL_PAGE), false, "only a 404 becomes notFound; other failures keep their state.");
});

test("null fields render as explicit statements rather than blanks or zeros", () => {
  assert.equal(formatRequestQuantity(null), REQUEST_QUANTITY_UNSPECIFIED);
  assert.equal(REQUEST_QUANTITY_UNSPECIFIED, "Not specified");
  assert.equal(formatRequestDimensions(null), "Not specified");
  assert.equal(formatRequestDimensions({ length: 120, width: null, height: 75, unit: "cm" }), "Length 120 cm · Width Unspecified · Height 75 cm");
  assert.deepEqual(requestContactLines({ name: "Asha", phone: null, email: null }), [
    { label: "Name", value: "Asha" },
    { label: "Phone", value: REQUEST_NOT_PROVIDED },
    { label: "Email", value: REQUEST_NOT_PROVIDED },
  ]);
  assert.equal(REQUEST_NOT_PROVIDED, "Not provided");
  assert.equal(requestReferenceDisplay({ requestReference: "" }), "Reference unavailable");
  assert.equal(formatRequestTimestamp("not-a-date"), null);
  assert.equal(REQUEST_TIMESTAMP_UNAVAILABLE, "Not recorded");
  assert.match(DETAIL_VIEW, /request\.material \?\? REQUEST_NOT_PROVIDED/);
  assert.match(DETAIL_VIEW, /request\.color \?\? REQUEST_NOT_PROVIDED/);
  assert.match(DETAIL_VIEW, /request\.notes \?\? REQUEST_NOT_PROVIDED/);
});

test("attachment metadata is formatted from the server values only", () => {
  assert.equal(formatAttachmentSize(400), "400 B");
  assert.equal(formatAttachmentSize(1536), "1.5 KiB");
  assert.equal(formatAttachmentSize(2 * 1024 * 1024), "2.0 MiB");
  assert.equal(attachmentContentTypeLabel("application/pdf"), "PDF document");
  assert.equal(attachmentContentTypeLabel("image/webp"), "WebP image");
  assert.equal(attachmentContentTypeLabel("application/zip"), "application/zip", "an unknown type renders the honest server value.");
});

test("a custom request and an anonymous submission become explicit statements", () => {
  assert.equal(requestFurnitureLabel({ productId: null, product: null }), REQUEST_CUSTOM_FURNITURE);
  assert.equal(requestFurnitureLabel({ productId: "prd_3", product: null }), "Linked product details unavailable");
  assert.equal(requestFurnitureLabel({ productId: "prd_3", product: { name: "Oak Dining Chair" } }), "Oak Dining Chair");
  assert.equal(requestIdentityLabel(null), REQUEST_ANONYMOUS_SUBMISSION);
  assert.equal(REQUEST_ANONYMOUS_SUBMISSION, "Anonymous submission");
  assert.equal(requestIdentityLabel("user_9"), "user_9");
  assert.equal(/href|Link/.test(requestIdentityLabel("user_9")), false, "a user id is never rendered as a link.");
  assert.match(DETAIL_VIEW, /requestIdentityLabel\(request\.userId\)/);
});

test("the product link renders only for an identity that can open it", () => {
  const linked = render(<RequestDetailView request={request()} productHref="/products/prd_3" canManage actions={NOOP_ACTIONS} />);
  assert.match(linked, /href="\/products\/prd_3"/);
  const plain = render(<RequestDetailView request={request()} productHref={null} canManage actions={NOOP_ACTIONS} />);
  assert.equal(/href="\/products\/prd_3"/.test(plain), false, "a request-only operator gets plain text.");
  assert.ok(text(plain).includes("Oak Dining Chair"));
  assert.match(DETAIL_PAGE, /canOpenProduct && request\.product !== null \? ADMIN_PRODUCT_PATHS\.detail\(request\.product\.id\) : null/);
});

test("the detail view never renders a fabricated status timeline", () => {
  const markup = render(<RequestDetailView request={request()} productHref={null} canManage actions={NOOP_ACTIONS} />);
  assert.equal(/timeline|status history|status_history/i.test(text(markup)), false);
  assert.equal(/status_history|timeline/i.test(MODULE), false);
});

// ---------------------------------------------------------------------------
// Lifecycle: status transitions, notes and submission
// ---------------------------------------------------------------------------

test("only the allowed transition targets are offered for each current status and CLOSED offers none", () => {
  assert.deepEqual(REQUEST_STATUS_VALUES, ["SUBMITTED", "IN_REVIEW", "CLOSED"]);
  assert.deepEqual(requestStatusTransitions("SUBMITTED"), ["IN_REVIEW", "CLOSED"]);
  assert.deepEqual(requestStatusTransitions("IN_REVIEW"), ["CLOSED"]);
  assert.deepEqual(requestStatusTransitions("CLOSED"), []);
  assert.deepEqual(requestStatusTransitions("UNKNOWN"), []);
  assert.equal(isRequestStatusValue("IN_REVIEW"), true);
  assert.equal(isRequestStatusValue("OPEN"), false);
  assert.equal(requestStatusLabel("CLOSED"), REQUEST_STATUS_LABELS.CLOSED);
  assert.equal(requestStatusLabel("OPEN"), "Unrecognised status");
  assert.equal(requestStatusDisplay("IN_REVIEW").tone, "warning");
  assert.equal(requestStatusOptionError("CLOSED", "IN_REVIEW"), "That status transition is not allowed from the current status.");
  assert.equal(requestStatusOptionError("SUBMITTED", "CLOSED"), null);
  assert.equal(requestStatusOptionError("SUBMITTED", REQUEST_STATUS_NO_CHANGE), null);
});

test("the closed state offers no transition and states it explicitly", () => {
  const markup = render(<RequestOperationalForm record={{ id: "req_7", requestStatus: "CLOSED", staffInternalNotes: null }} canManage actions={NOOP_ACTIONS} />);
  assert.ok(text(markup).includes("This request is closed."));
  assert.equal(/name="request_status"/.test(markup), false, "a closed request offers no further transition.");
  assert.match(markup, /name="staff_internal_notes"/, "the notes can still change.");
});

test("notes are sent only when changed and clearing sends null, not an empty string", () => {
  const baseline = { requestStatus: "SUBMITTED", staffInternalNotes: "Call before delivery." };
  assert.deepEqual(requestUpdateFields(baseline, "IN_REVIEW", "Call before delivery."), { requestStatus: "IN_REVIEW" });
  assert.deepEqual(requestUpdateFields(baseline, "", ""), { staffInternalNotes: null });
  assert.deepEqual(requestUpdateFields(baseline, "CLOSED", "Different note"), { requestStatus: "CLOSED", staffInternalNotes: "Different note" });
  assert.deepEqual(requestUpdateFields({ requestStatus: "SUBMITTED", staffInternalNotes: null }, "", "   "), null);
  assert.deepEqual(requestUpdateFields({ requestStatus: "SUBMITTED", staffInternalNotes: null }, "", "New note"), { staffInternalNotes: "New note" });
  assert.equal(normalizeStaffNotes("   "), null);
  assert.equal(normalizeStaffNotes("  trimmed  "), "trimmed");
});

test("a no-op submit is impossible", () => {
  const baseline = { requestStatus: "SUBMITTED", staffInternalNotes: "Call before delivery." };
  assert.equal(requestUpdateFields(baseline, "", "Call before delivery."), null);
  assert.equal(requestUpdateFields(baseline, "SUBMITTED", "  Call before delivery.  "), null, "trimmed equality is unchanged.");
  assert.match(FORM, /const blocked = pending \|\| fields === null \|\| error !== null;/);
  assert.match(FORM, /disabled=\{blocked\}/);
  assert.match(FORM, /if \(inFlight\.current \|\| fields === null \|\| !canManage\) return;/);
});

test("the notes limit mirrors Laravel and drives an accessible error", () => {
  assert.equal(MAX_STAFF_NOTES, 5000);
  assert.equal(requestNotesError("x".repeat(5000)), null);
  assert.equal(requestNotesError("x".repeat(5001)), REQUEST_NOTES_TOO_LONG_MESSAGE);
  assert.match(FORM, /maxLength: MAX_STAFF_NOTES/);
  assert.match(FORM, /label="Internal notes"/);
  assert.match(FORM, /role="alert"/);
});

test("the update body emits only the changed allow-listed fields and never an empty body", () => {
  assert.match(API, /body\[REQUEST_STATUS_FIELD\] = fields\.requestStatus;/);
  assert.match(API, /body\[STAFF_INTERNAL_NOTES_FIELD\] = fields\.staffInternalNotes;/);
  assert.match(API, /if \(fields\.requestStatus !== undefined\)/);
  assert.match(API, /if \(fields\.staffInternalNotes !== undefined\)/);
  assert.match(MUTATIONS, /REQUEST_STATUS_FIELD = "request_status"/);
  assert.match(MUTATIONS, /STAFF_INTERNAL_NOTES_FIELD = "staff_internal_notes"/);
  assert.equal((MUTATIONS.match(/_FIELD = "/g) ?? []).length, 2, "exactly two wire fields are defined.");
});

test("every documented update failure keeps its own safe outcome", () => {
  assert.equal(requestUpdateFailureOutcome(failure(409)).status, "conflict");
  assert.equal(requestUpdateFailureOutcome(failure(409)).message, REQUEST_UPDATE_CONFLICT_MESSAGE);
  assert.equal(requestUpdateFailureOutcome(failure(403)).status, "forbidden");
  assert.equal(requestUpdateFailureOutcome(failure(403)).message, REQUEST_UPDATE_UNAUTHORIZED_MESSAGE);
  assert.equal(requestUpdateFailureOutcome(failure(401)).status, "session");
  assert.equal(requestUpdateFailureOutcome(failure(401)).message, REQUEST_UPDATE_SESSION_MESSAGE);
  assert.equal(requestUpdateFailureOutcome(failure(404)).status, "not-found");
  assert.equal(requestUpdateFailureOutcome(failure(404)).message, REQUEST_UPDATE_NOT_FOUND_MESSAGE);
  assert.equal(requestUpdateFailureOutcome(failure(429, { retryAfterSeconds: 15 })).status, "rate-limited");
  assert.match(requestUpdateFailureOutcome(failure(429, { retryAfterSeconds: 15 })).message, /15 seconds/);
  assert.equal(requestUpdateFailureOutcome(failure(500)).status, "unavailable");
  assert.equal(requestUpdateFailureOutcome(failure(500)).message, REQUEST_UPDATE_UNAVAILABLE_MESSAGE);
  assert.equal(requestUpdateFailureOutcome(failure(null, { transportKind: "network" })).status, "uncertain");
  assert.equal(requestUpdateFailureOutcome(failure(null, { transportKind: "network" })).message, REQUEST_UPDATE_UNCERTAIN_MESSAGE);
});

test("a field validation error prefers Laravel's own message and success is never inferred", () => {
  const invalid = requestUpdateFailureOutcome(failure(422, { errors: [{ code: "INVALID_VALUE", message: "The selected request status transition is not allowed.", field: "request_status" }] }));
  assert.equal(invalid.status, "invalid");
  assert.equal(invalid.message, "The selected request status transition is not allowed.");
  assert.equal(invalid.fieldErrors.length, 1);
  assert.equal(requestUpdateFailureOutcome(failure(422)).status, "invalid");
  assert.equal(requestUpdateRejection(REQUEST_VIEW_UNAUTHORIZED_MESSAGE).status, "invalid");
  assert.equal(requestUpdateSeverity("success"), "success");
  assert.equal(requestUpdateSeverity("conflict"), "warning");
  assert.equal(requestUpdateSeverity("uncertain"), "warning");
  assert.equal(requestUpdateSeverity("rate-limited"), "warning");
  assert.equal(requestUpdateSeverity("forbidden"), "error");
});

test("the update action never retries on its own and keeps no idempotency key", () => {
  assert.equal(/setTimeout|setInterval/.test(FORM), false, "the form schedules no retry.");
  assert.equal(/setTimeout|setInterval|setImmediate/.test(ACTIONS), false, "the action performs exactly one attempt.");
  assert.equal(/Idempotency-Key|idempotencyKey|newIdempotencyKey/.test(ACTIONS + API + FORM), false, "REQ-006 is idempotent by value and must not invent a key.");
  assert.equal((FORM.match(/actions\.update\(/g) ?? []).length, 1, "exactly one submission path exists.");
  assert.match(FORM, /aria-busy=\{pending\}/);
});

test("a successful update revalidates the list and detail from authoritative state", () => {
  assert.match(ACTIONS, /revalidatePath\(ADMIN_ROUTE_PATHS\.requests\)/);
  assert.match(ACTIONS, /revalidatePath\(ADMIN_REQUEST_PATHS\.detail\(prepared\.requestId\)\)/);
  const body = withoutImports(ACTIONS);
  const revalidate = body.indexOf("revalidatePath(ADMIN_ROUTE_PATHS.requests)");
  assert.ok(revalidate > body.indexOf("await submitRequestUpdate("), "revalidation follows the confirmed request.");
  assert.ok(revalidate > body.indexOf("catch (error)"), "a failed request must return before revalidation.");
  assert.match(ACTIONS, /return requestUpdateSuccess\(\);/);
  assert.equal(/result\.(data|value|request_status)/.test(ACTIONS), false, "the mutation response shape is never echoed as truth.");
});

test("the operational form gates the editing controls on requests.manage", () => {
  const managing = render(<RequestOperationalForm record={{ id: "req_7", requestStatus: "SUBMITTED", staffInternalNotes: "Call before delivery." }} canManage actions={NOOP_ACTIONS} />);
  const managingText = text(managing);
  assert.ok(managingText.includes("Call before delivery."));
  assert.ok(managingText.includes("New status"));
  assert.match(managing, /name="request_status"/);
  assert.match(managing, /name="staff_internal_notes"/);
  assert.ok(managingText.includes("Keep current status"));
  assert.match(FORM, /transitions\.map\(/);
  assert.match(FORM, /REQUEST_STATUS_LABELS\[value\]/);
  assert.match(managing, /Save changes/);

  const viewOnly = render(<RequestOperationalForm record={{ id: "req_7", requestStatus: "SUBMITTED", staffInternalNotes: null }} canManage={false} actions={NOOP_ACTIONS} />);
  const viewOnlyText = text(viewOnly);
  assert.ok(viewOnlyText.includes(REQUEST_NOTES_UNAUTHORIZED));
  assert.ok(viewOnlyText.includes("view-only request access"));
  assert.equal(/name="request_status"|name="staff_internal_notes"/.test(viewOnly), false, "a view-only identity receives no usable control.");
  assert.equal(/Save changes/.test(viewOnly), false);
});

test("internal notes distinguish not-authorized from empty", () => {
  assert.equal(requestNotesDisplay({ staffInternalNotes: "Call before delivery." }, true), "Call before delivery.");
  assert.equal(requestNotesDisplay({ staffInternalNotes: null }, true), REQUEST_NOTES_EMPTY);
  assert.equal(requestNotesDisplay({ staffInternalNotes: null }, false), REQUEST_NOTES_UNAUTHORIZED);
  assert.equal(requestNotesDisplay({ staffInternalNotes: "Call before delivery." }, false), REQUEST_NOTES_UNAUTHORIZED);
  assert.equal(REQUEST_NOTES_EMPTY, "No internal notes recorded.");
  assert.equal(REQUEST_NOTES_UNAUTHORIZED, "Internal notes are not available with your permissions.");
  assert.match(FORM, /requestNotesDisplay\(\{ staffInternalNotes: currentNotes \}, canManage\)/);
});

// ---------------------------------------------------------------------------
// Attachment inspection
// ---------------------------------------------------------------------------

test("the attachment panel renders the documented zero, present and unavailable states", () => {
  const empty = render(<RequestAttachmentPanel attachments={[]} />);
  assert.ok(text(empty).includes(REQUEST_ATTACHMENT_NONE));
  assert.equal(/<a /.test(empty), false);

  const present = render(
    <RequestAttachmentPanel
      attachments={[{ id: "att_1", filename: "measurements.pdf", contentType: "application/pdf", size: 1536, url: "https://api.example.test/api/v1/requests/req_7/attachments/att_1?sig=x" }]}
    />,
  );
  const presentText = text(present);
  assert.ok(presentText.includes("measurements.pdf"));
  assert.ok(presentText.includes("PDF document"));
  assert.ok(presentText.includes("1.5 KiB"));
  assert.match(present, /href="https:\/\/api\.example\.test\/api\/v1\/requests\/req_7\/attachments\/att_1\?sig=x"/);
  assert.match(present, /target="_blank"/);
  assert.match(present, /rel="noopener noreferrer"/);
  assert.match(present, /aria-label="Open attachment measurements\.pdf"/);
  assert.ok(presentText.includes("Open attachment"));
  assert.ok(presentText.includes(REQUEST_ATTACHMENT_TTL_NOTICE));

  const unavailable = render(<RequestAttachmentPanel attachments={[{ id: "att_2", filename: "room.jpg", contentType: "image/jpeg", size: 2048, url: null }]} />);
  assert.ok(text(unavailable).includes(REQUEST_ATTACHMENT_UNAVAILABLE));
  assert.equal(/<a /.test(unavailable), false);
});

test("the module never constructs a download url, proxies bytes or renders storage internals", () => {
  assert.match(ATTACHMENT, /href=\{attachment\.url\}/);
  assert.equal(/encodeURIComponent\(attachment\.id\)|\/attachments\/\$\{|`?\/attachments\/`?\s*\+/.test(ATTACHMENT), false, "the delivery url is only ever the one Laravel returned.");
  assert.equal(/storage_key|storage_disk|file_path/.test(ATTACHMENT + MODULE), false, "storage internals are never named or rendered.");
  assert.equal(/createAdminApiClient|fetch\(|requestOperational\(/.test(ATTACHMENT), false, "the panel fetches no bytes.");
  assert.equal(/createAdminApiClient|fetch\(/.test(TABLE), false, "the list never fetches attachment bytes.");
  assert.equal(/next\/image|next\/link/.test(ATTACHMENT), false, "no remote image allow-list change and no client proxy.");
});

test("no admin proxy or route handler is introduced", () => {
  const requestsDir = join(ADMIN_ROOT, "app/(protected)/requests");
  assert.equal(existsSync(join(requestsDir, "route.ts")), false);
  assert.equal(existsSync(join(requestsDir, "[request]", "route.ts")), false);
  assert.deepEqual([...readdirSync(join(requestsDir, "[request]"))].sort(), ["page.tsx"]);
});

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

test("the request table renders only API-returned values with a named action", () => {
  const markup = render(<RequestTable requests={[request()]} />);
  const visible = text(markup);
  for (const value of ["REQ-2026-0007", "Asha Mwinyi", "Oak Dining Chair", "+255700000000", "asha@example.test"]) {
    assert.ok(visible.includes(value), `the row must render ${value}.`);
  }
  assert.deepEqual([...markup.matchAll(/<th[^>]*>(.*?)<\/th>/g)].map((match) => text(match[1])), [
    "Reference",
    "Submitted",
    "Customer",
    "Furniture",
    "Quantity",
    "Status",
    "Attachment",
    "Actions",
  ]);
  assert.match(markup, /href="\/requests\/req_7"/);
  assert.match(markup, /Open request REQ-2026-0007/);
  assert.equal(visible.includes("user_9"), false, "a user id is never rendered as a customer link.");
  const formattedTimestamp = formatRequestTimestamp(request().createdAt);
  assert.ok(formattedTimestamp && visible.includes(formattedTimestamp));
});

test("a custom request and an unspecified quantity render explicitly in the table", () => {
  const markup = render(<RequestTable requests={[request({ product_id: null, product: null, quantity: null, attachments: [] })]} />);
  const visible = text(markup);
  assert.ok(visible.includes(REQUEST_CUSTOM_FURNITURE));
  assert.ok(visible.includes(REQUEST_QUANTITY_UNSPECIFIED));
  assert.ok(visible.includes("No attachment"));
});

test("the filter form offers only supported filters and keeps state in the URL", () => {
  const query = parseRequestListQuery({ search: "oak", request_status: "IN_REVIEW", product_id: "prd_3", created_from: "2026-01-01", created_to: "2026-01-31", per_page: "50" });
  const markup = render(<RequestListFilters query={query} />);
  const visible = text(markup);
  for (const label of ["Search", "Status", "Product identifier", "Submitted from", "Submitted to", "Apply filters", "Clear filters"]) {
    assert.ok(visible.includes(label), `the filter form must expose ${label}.`);
  }
  assert.match(markup, /name="search"/);
  assert.match(markup, /name="request_status"/);
  assert.match(markup, /name="product_id"/);
  assert.match(markup, /name="created_from"/);
  assert.match(markup, /name="created_to"/);
  assert.match(markup, /name="per_page" value="50"/);
  assert.match(markup, /value="prd_3"/);
  assert.equal(/name="page"|name="sort"/.test(markup), false, "no unsupported filter may exist.");
  assert.match(FILTERS, /method="get"/);
  assert.match(FILTERS, /UTC/);
  assert.match(FILTERS, /not a product-name search/);
  assert.equal(/fetch\(|useEffect|onChange/.test(FILTERS), false, "filtering must not issue a request per keystroke.");
  for (const field of ["search", "status", "product", "from", "to"]) {
    assert.ok(FILTERS.includes("key={`" + field + ":"), `the ${field} control must reset per control.`);
  }
  const formLine = FILTERS.split("\n").find((line) => line.includes('component="form"'));
  assert.match(FILTERS, /action=\{ADMIN_ROUTE_PATHS\.requests\}/);
  assert.equal(formLine?.includes("key="), false, "a form-level key causes a hydration mismatch.");
});

test("pagination renders the server's totals and preserves filters", () => {
  const markup = render(
    <RequestListPagination pagination={{ current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true }} query={parseRequestListQuery({ search: "oak" })} />,
  );
  assert.ok(text(markup).includes("Page 2 of 3 · 45 requests"));
  assert.match(markup, /href="\/requests\?search=oak"/);
  assert.match(markup, /href="\/requests\?search=oak&amp;page=3"/);
});

test("a rate-limited read renders a distinct, retry-aware message", () => {
  assert.match(text(render(<RequestFailureState failure="throttled" retryAfterSeconds={30} />)), /Too many requests/);
  assert.match(text(render(<RequestFailureState failure="throttled" retryAfterSeconds={30} />)), /30 seconds/);
  assert.match(text(render(<RequestFailureState failure="invalid" retryAfterSeconds={null} />)), /could not read/);
  assert.match(text(render(<RequestFailureState failure="not-found" retryAfterSeconds={null} />)), /could not be found/);
});

test("the request detail view renders the customer, specification and attachment sections", () => {
  const markup = render(<RequestDetailView request={request()} productHref="/products/prd_3" canManage actions={NOOP_ACTIONS} />);
  const visible = text(markup);
  for (const value of ["Asha Mwinyi", "+255700000000", "asha@example.test", "Oak Dining Chair", "oak-dining-chair", "Length 120 cm", "Material", "Oak", "Color", "Natural", "Please deliver in the morning."]) {
    assert.ok(visible.includes(value), `the detail view must render ${value}.`);
  }
  assert.ok(visible.includes("No attachment was submitted with this request."));
  assert.equal(visible.includes("user_9"), true, "the opaque account reference is shown as plain text.");
  assert.equal(/href="\/products\/prd_3"[^>]*user_9/.test(markup), false);
});

// ---------------------------------------------------------------------------
// Scope boundaries
// ---------------------------------------------------------------------------

test("no endpoint, sort, export, bulk, reply or conversion capability is introduced", () => {
  for (const forbidden of ["/admin/requests", "csv", "bulk", "Convert to order", "Mark contacted", "Reply", "sendEmail", "send-email"]) {
    assert.equal(MODULE.includes(forbidden), false, `unexpected ${forbidden} in the requests module.`);
  }
  assert.equal(/sort_by|orderBy|order_by/.test(MODULE), false, "the API owns ordering; the client sends no sort.");
  assert.equal(/\.reverse\(\)|slice\(0,/.test(PAGE + TABLE), false, "the returned page must not be reordered or truncated.");
  assert.equal(/createAdminApiClient|fetch\(/.test(ACTIONS + TABLE + FORM + PAGE + DETAIL_PAGE), false, "no second fetch path may exist.");
});

test("the page keeps loading, empty and failure states distinct and fabricates nothing", () => {
  assert.match(PAGE, /<EmptyState title="No requests"/);
  assert.match(PAGE, /<EmptyState[\s\S]*?title="No matching requests"/);
  assert.equal(/revenue|sales|analytics|forecast|sample|dummy|fixture|mock/i.test(PAGE + DETAIL_PAGE), false);
  assert.equal(/setInterval|setTimeout/.test(PAGE + DETAIL_PAGE), false, "the routes must not poll the API.");
  assert.equal(/component="main"/.test(PAGE + DETAIL_PAGE), false, "the shell owns the single main landmark.");
  assert.match(PAGE, /<PageContainer aria-labelledby="page-title">/);
  assert.match(DETAIL_PAGE, /<PageContainer aria-labelledby="page-title">/);
});

test("the customer website, admin catalog modules and Flutter application are untouched", () => {
  for (const source of [PAGE, DETAIL_PAGE, ACTIONS, API, TABLE, FILTERS, FORM, ATTACHMENT, DETAIL_VIEW]) {
    assert.equal(/frontend\/web|frontend\/mobile|flutter|frontend\/design-system/i.test(source), false);
  }
  assert.equal(/lib\/catalog\//.test(MODULE), false, "the requests module must not reach into the catalog modules.");
});
