import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { CategoryDetailView } from "../components/catalog/category-detail-view";
import { CategoryFailureState } from "../components/catalog/category-failure-state";
import { CategoryListPagination } from "../components/catalog/category-list-pagination";
import { CategoryScopeNotice } from "../components/catalog/category-scope-notice";
import { CategoryTable } from "../components/catalog/category-table";
import { AdminPermissionName } from "../lib/auth/permission-names";
import {
  EMPTY_CATEGORY_FORM_VALUES,
  buildCreateCategoryPayload,
  buildUpdateCategoryPayload,
  categoryFormValuesFromCategory,
  fieldErrorsFromApi,
  readCategoryFormValues,
  type CategoryFormValues,
} from "../lib/catalog/category-form";
import { formatCategoryTimestamp, safeImageUrl } from "../lib/catalog/category-presentation";
import { categoryListHref, categoryListRequestQuery, parseCategoryListQuery } from "../lib/catalog/category-query";
import { CategoryResourceError, parseCategoryDetail, parseCategoryPage, type CategorySummary } from "../lib/catalog/category-resource";
import { ADMIN_CATEGORY_PATHS, ADMIN_PROTECTED_MATCHERS, ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "../lib/navigation/admin-route-paths";
import { adminRoute, adminRouteStatus } from "../lib/navigation/admin-navigation";
import { theme } from "../theme";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const read = (relative: string): string => readFileSync(join(ADMIN_ROOT, relative), "utf8");

const LIST_PAGE = read("app/(protected)/categories/page.tsx");
const NEW_PAGE = read("app/(protected)/categories/new/page.tsx");
const DETAIL_PAGE = read("app/(protected)/categories/[category]/page.tsx");
const EDIT_PAGE = read("app/(protected)/categories/[category]/edit/page.tsx");
const ACTIONS = read("lib/catalog/category-actions.ts");
const CATEGORIES_API = read("lib/catalog/categories-api.ts");
const SERVER_REQUEST = read("lib/api/server-request.ts");
const FORM_COMPONENT = read("components/catalog/category-form.tsx");
const TABLE_COMPONENT = read("components/catalog/category-table.tsx");
const FAILURE_COMPONENT = read("components/catalog/category-failure-state.tsx");

const ALL_CATEGORY_PAGES = [LIST_PAGE, NEW_PAGE, DETAIL_PAGE, EDIT_PAGE];

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const text = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
const withoutImports = (source: string): string => source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");

function summary(overrides: Partial<CategorySummary> = {}): CategorySummary {
  return { id: "cat_1", name: "Living Room", slug: "living-room", image: null, ...overrides };
}

function validFormValues(overrides: Partial<CategoryFormValues> = {}): CategoryFormValues {
  return { name: "Living Room", slug: "living-room", description: "A place to relax.", image: "", ...overrides };
}

// ---------------------------------------------------------------------------
// Authorization and route protection
// ---------------------------------------------------------------------------

test("every category page repeats the route requirement before any data access", () => {
  for (const source of ALL_CATEGORY_PAGES) {
    assert.match(source, /requireAdminRouteAccess\(ROUTE\)/, "each page must repeat the route guard.");
    assert.match(source, /if \(!access\.granted\) return access\.denial;/, "each page must render the denial state.");
    const paramsIndex = source.indexOf("await params");
    if (paramsIndex !== -1) assert.ok(source.indexOf("requireAdminRouteAccess(") < paramsIndex, "authorization must precede resource resolution.");
  }
});

test("the categories route keeps its URL, registry entry and exact permission", () => {
  assert.equal(ADMIN_ROUTE_PATHS.categories, "/categories");
  const route = adminRoute(ADMIN_ROUTE_PATHS.categories);
  assert.equal(route.href, "/categories");
  assert.equal(route.permission, AdminPermissionName.PRODUCTS_MANAGE);
  assert.equal(route.permission, "products.manage", "the registry must reuse the verified Laravel write permission, never an invented categories.manage.");
  assert.equal(route.placeholder, undefined, "the categories workflow is implemented and is not a placeholder.");
  assert.equal(route.plannedFor, undefined, "an implemented route must not advertise a roadmap phase.");
  assert.equal(adminRouteStatus(route), null, "an implemented route must not render a not-implemented status.");
});

test("nested category routes stay covered by the existing protected matcher", () => {
  assert.ok(ADMIN_PROTECTED_MATCHERS.includes("/categories(.*)"), "the /categories matcher must cover nested routes.");
  assert.equal(ADMIN_CATEGORY_PATHS.list, ADMIN_ROUTE_PATHS.categories);
  assert.equal(ADMIN_CATEGORY_PATHS.create, "/categories/new");
  assert.equal(ADMIN_CATEGORY_PATHS.detail("cat_1"), "/categories/cat_1");
  assert.equal(ADMIN_CATEGORY_PATHS.edit("cat_1"), "/categories/cat_1/edit");
  assert.notEqual(ADMIN_CATEGORY_PATHS.list, ADMIN_PRODUCT_PATHS.list, "the category routes must not collide with the product routes.");
});

test("server actions re-verify the exact permission because they are directly reachable", () => {
  assert.match(ACTIONS, /^"use server";/);
  const guardCalls = ACTIONS.match(/await authorizationFailure\(\)/g) ?? [];
  assert.equal(guardCalls.length, 2, "both create and update must re-authorize.");
  assert.match(ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{ permission: CATEGORY_ROUTE\.permission, role: CATEGORY_ROUTE\.role \}\)/);
  const body = withoutImports(ACTIONS);
  assert.ok(body.indexOf("await authorizationFailure()") < body.indexOf("submitCategoryCreate"), "authorization must run before the create request.");
  assert.ok(body.indexOf("await authorizationFailure()") < body.indexOf("submitCategoryUpdate"), "authorization must run before the update request.");
});

test("no bearer token, raw session or token getter crosses into a client component", () => {
  for (const source of ALL_CATEGORY_PAGES) {
    assert.equal(/Bearer|getToken|session_token|eyJ/.test(source), false, "a page must not expose a token.");
  }
  assert.equal(/Bearer|getToken|token/.test(FORM_COMPONENT), false, "the form must not receive a token.");
  for (const relative of ["components/catalog/category-form.tsx", "components/catalog/category-table.tsx"]) {
    const source = read(relative);
    assert.match(source, /^"use client";/);
    assert.equal(/server-only|server-request|lib\/api\/client/.test(source), false, `${relative} must not import the server transport.`);
  }
});

test("authenticated category writes stay request-scoped and no-store", () => {
  assert.match(SERVER_REQUEST, /await auth\(\)/);
  assert.match(SERVER_REQUEST, /await session\.getToken\(\)/);
  for (const relative of ["lib/catalog/categories-api.ts", "lib/api/server-request.ts"]) {
    assert.equal(/unstable_cache|force-cache|next: \{ revalidate/.test(read(relative)), false, `${relative} must not add a shared cache.`);
  }
  assert.match(CATEGORIES_API, /requestOperationalApi/);
  assert.match(CATEGORIES_API, /requestPublicApi/);
  assert.match(read("lib/api/client.ts"), /cache: token \? "no-store" : undefined/);
});

// ---------------------------------------------------------------------------
// API contract and data handling
// ---------------------------------------------------------------------------

test("only the approved endpoints are contacted and no unapproved resource is introduced", () => {
  assert.match(CATEGORIES_API, /path: "\/categories"/);
  assert.match(CATEGORIES_API, /path: `\/categories\/\$\{encodeURIComponent\(identifier\)\}`/);
  assert.match(CATEGORIES_API, /method: "POST"/);
  assert.match(CATEGORIES_API, /method: "PATCH"/);
  for (const forbidden of ["/admin/categories", "/inventory", "/orders", "/requests", "/enquiries", "/customers", "/audit", "/images", "DELETE"]) {
    assert.equal(CATEGORIES_API.includes(forbidden), false, `the category API module must not call ${forbidden}.`);
  }
  assert.equal(/"sort"|sort_by|order_by|search\b/.test(CATEGORIES_API), false, "no search or sort parameter may be invented.");
});

test("the public catalog is used only because no operational listing exists, and writes stay operational", () => {
  assert.match(CATEGORIES_API, /requestPublicApi<unknown>\(\{ path: "\/categories"/);
  assert.match(CATEGORIES_API, /requestPublicApi<unknown>\(\{ path: `\/categories\/\$\{encodeURIComponent\(identifier\)\}` \}\)/);
  assert.match(CATEGORIES_API, /requestOperationalApi<unknown>\(\{ path: "\/categories", method: "POST"/);
  assert.match(CATEGORIES_API, /requestOperationalApi<unknown>\(\{ path: `\/categories\/\$\{encodeURIComponent\(identifier\)\}`, method: "PATCH"/);
  assert.equal(/\/admin\//.test(LIST_PAGE + DETAIL_PAGE + EDIT_PAGE), false, "no operational category endpoint may be invented in the pages.");
});

test("the list query accepts only the pagination parameters and nothing else", () => {
  assert.deepEqual(parseCategoryListQuery({ page: "2", per_page: "50" }), { page: 2, perPage: 50 });
  assert.deepEqual(parseCategoryListQuery({ page: "0", per_page: "500" }), { page: 1, perPage: 20 });
  assert.deepEqual(parseCategoryListQuery({ page: "abc", category: "living-room", search: "x" }), { page: 1, perPage: 20 });
  assert.deepEqual(Object.keys(categoryListRequestQuery(parseCategoryListQuery({}))).sort(), ["page", "per_page"].sort());
});

test("pagination links preserve the page size and totals come from server metadata", () => {
  assert.equal(categoryListHref(parseCategoryListQuery({})), "/categories");
  assert.equal(categoryListHref(parseCategoryListQuery({ page: "2" })), "/categories?page=2");
  assert.equal(categoryListHref({ page: 3, perPage: 50 }), "/categories?page=3&per_page=50");
  const markup = render(<CategoryListPagination pagination={{ current_page: 2, per_page: 20, total: 7, last_page: 2, has_next: false, has_previous: true }} query={parseCategoryListQuery({ page: "2" })} />);
  assert.ok(text(markup).includes("Page 2 of 2 · 7 categories"));
  assert.match(markup, /href="\/categories"/);
});

test("the category resource is read from the API, not reconstructed", () => {
  const page = parseCategoryPage({ data: [{ id: "cat_1", name: "Living Room", slug: "living-room", image: { url: "https://cdn.test/living.webp" } }], meta: { pagination: { current_page: 1, per_page: 20, total: 1, last_page: 1 } } });
  assert.deepEqual(page.categories, [{ id: "cat_1", name: "Living Room", slug: "living-room", image: { url: "https://cdn.test/living.webp" } }]);
  assert.deepEqual(page.pagination, { current_page: 1, per_page: 20, total: 1, last_page: 1, has_next: false, has_previous: false });

  const detail = parseCategoryDetail({ id: "cat_1", name: "Living Room", slug: "living-room", description: null, image: null, created_at: "2026-09-29T13:45:00.000Z" });
  assert.equal(detail.description, null);
  assert.equal(detail.image, null);
  assert.equal(detail.createdAt, "2026-09-29T13:45:00.000Z");
});

test("an unusable category response is rejected rather than guessed", () => {
  assert.throws(() => parseCategoryPage({ data: [] }), CategoryResourceError);
  assert.throws(() => parseCategoryPage({ data: [{ name: "No id" }], meta: { pagination: { current_page: 1, per_page: 20, total: 1, last_page: 1 } } }), CategoryResourceError);
  assert.throws(() => parseCategoryPage({ data: [], meta: { pagination: { current_page: 0, per_page: 20, total: 0, last_page: 1 } } }), CategoryResourceError);
  assert.throws(() => parseCategoryDetail({ name: "Living Room" }), CategoryResourceError);
  assert.throws(() => parseCategoryDetail({ id: "cat_1", name: "Living Room", slug: "" }), CategoryResourceError);
});

test("the partial listing scope is stated accurately and never claimed as complete", () => {
  const notice = text(render(<CategoryScopeNotice />));
  assert.ok(notice.includes("active top-level categories published to the storefront"));
  assert.ok(notice.includes("not returned by the category endpoint"));
  assert.ok(notice.includes("cannot be viewed or managed here"));
  assert.equal(/all categories/i.test(notice), false, "the notice must never call the result all categories.");
  assert.match(LIST_PAGE, /<CategoryScopeNotice \/>/);
  assert.match(LIST_PAGE, /not proof that the catalog has no categories/);
});

test("an unreadable response is classified as invalid data, not a service outage", () => {
  const start = CATEGORIES_API.indexOf("function describeCategoryApiFailure");
  assert.ok(start > -1, "the failure classifier must exist.");

  const unreadable = CATEGORIES_API.indexOf("error instanceof CategoryResourceError", start);
  const apiError = CATEGORIES_API.indexOf("error instanceof ApiError", start);
  assert.ok(unreadable > -1, "an unreadable response must be classified explicitly.");
  assert.ok(apiError > unreadable, "the resource error must be classified before the status fallback.");

  assert.equal(/failure === "invalid"/.test(FAILURE_COMPONENT), false, "\"invalid\" must fall through to the unreadable-response default.");
  assert.match(FAILURE_COMPONENT, /description="The category service returned a response this application could not read\."/);
  assert.match(FAILURE_COMPONENT, /failure === "unavailable"\) state = <ServiceUnavailableState \/>/);
});

// ---------------------------------------------------------------------------
// Create / edit payloads
// ---------------------------------------------------------------------------

test("the create payload contains exactly the approved request fields", () => {
  const { payload, fieldErrors } = buildCreateCategoryPayload(validFormValues({ image: "https://cdn.test/living.webp" }));
  assert.deepEqual(fieldErrors, {});
  assert.deepEqual(Object.keys(payload ?? {}).sort(), ["description", "image", "name", "slug"].sort());
  assert.equal(payload?.description, "A place to relax.");
  assert.equal(payload?.image, "https://cdn.test/living.webp");

  const minimal = buildCreateCategoryPayload(validFormValues({ description: "", image: "" }));
  assert.deepEqual(Object.keys(minimal.payload ?? {}).sort(), ["name", "slug"].sort());
  assert.equal("description" in (minimal.payload ?? {}), false, "an empty optional description is not sent.");
  assert.equal("image" in (minimal.payload ?? {}), false, "an empty optional image is not sent.");
});

test("client validation advisory mirrors the FormRequests and blocks an invalid payload", () => {
  assert.match(buildCreateCategoryPayload(validFormValues({ name: "" })).fieldErrors.name ?? "", /Enter a category name/);
  assert.match(buildCreateCategoryPayload(validFormValues({ name: "x".repeat(121) })).fieldErrors.name ?? "", /120 characters/);
  assert.match(buildCreateCategoryPayload(validFormValues({ slug: "Not A Slug" })).fieldErrors.slug ?? "", /lowercase/);
  assert.match(buildCreateCategoryPayload(validFormValues({ image: "not a url" })).fieldErrors.image ?? "", /http\(s\)/);
  assert.match(buildCreateCategoryPayload(validFormValues({ description: "x".repeat(1001) })).fieldErrors.description ?? "", /1000 characters/);
  assert.equal(buildCreateCategoryPayload(validFormValues({ image: "https://cdn.test/a.webp" })).payload?.image, "https://cdn.test/a.webp");
});

test("an update sends only the fields the operator changed and clears with null", () => {
  const original = validFormValues({ image: "https://cdn.test/a.webp" });
  const unchanged = buildUpdateCategoryPayload(original, original);
  assert.equal(unchanged.payload, null, "a no-op update must not reach the API.");
  assert.match(unchanged.fieldErrors.name ?? "", /Change at least one field/);

  const renamed = buildUpdateCategoryPayload({ ...original, name: "Lounge" }, original);
  assert.deepEqual(renamed.payload, { name: "Lounge" });

  const cleared = buildUpdateCategoryPayload({ ...original, description: "", image: "" }, original);
  assert.deepEqual(cleared.payload, { description: null, image: null }, "clearing a nullable field submits null, not an empty string.");
});

test("API validation errors map to their form controls", () => {
  const { fieldErrors, unmapped } = fieldErrorsFromApi([
    { code: "INVALID_VALUE", message: "The name field is required.", field: "name" },
    { code: "CONFLICT", message: "A category with this slug already exists.", field: "slug" },
    { code: "INVALID_VALUE", message: "This category field is not allowed.", field: "parent_id" },
    { code: "INVALID_VALUE", message: "Something else went wrong." },
  ]);
  assert.equal(fieldErrors.name, "The name field is required.");
  assert.equal(fieldErrors.slug, "A category with this slug already exists.");
  assert.equal(fieldErrors.parent_id, undefined, "an unknown field must not be forced onto a control.");
  assert.deepEqual(unmapped, ["This category field is not allowed.", "Something else went wrong."]);
});

test("form values are read from the submitted form data and from the loaded resource", () => {
  const formData = new FormData();
  formData.set("name", "Living Room");
  formData.set("slug", "living-room");
  formData.set("description", "A place to relax.");
  formData.set("image", "https://cdn.test/living.webp");
  const values = readCategoryFormValues(formData);
  assert.deepEqual(values, { name: "Living Room", slug: "living-room", description: "A place to relax.", image: "https://cdn.test/living.webp" });

  assert.deepEqual(categoryFormValuesFromCategory({ id: "cat_1", name: "Living Room", slug: "living-room", description: null, image: { url: "https://cdn.test/living.webp" }, createdAt: null }), { name: "Living Room", slug: "living-room", description: "", image: "https://cdn.test/living.webp" });
  assert.equal(EMPTY_CATEGORY_FORM_VALUES.name, "");
  assert.equal(EMPTY_CATEGORY_FORM_VALUES.image, "");
});

// ---------------------------------------------------------------------------
// Rendering and states
// ---------------------------------------------------------------------------

test("the category table renders only API-returned values with named links", () => {
  const markup = render(<CategoryTable categories={[summary()]} />);
  const visible = text(markup);
  for (const value of ["Living Room", "living-room", "Not set"]) {
    assert.ok(visible.includes(value), `the row must render ${value}.`);
  }
  assert.match(markup, /href="\/categories\/cat_1"/);
  assert.match(markup, /href="\/categories\/cat_1\/edit"/);
  assert.ok(visible.includes("View Living Room") && visible.includes("Edit Living Room"), "row actions need a meaningful accessible name.");
  assert.match(TABLE_COMPONENT, /<DataTable /, "the table must reuse the shared primitive, not invent a second one.");
});

test("only an http(s) image address becomes a link", () => {
  assert.equal(safeImageUrl("https://cdn.test/a.webp"), "https://cdn.test/a.webp");
  assert.equal(safeImageUrl("http://cdn.test/a.webp"), "http://cdn.test/a.webp");
  assert.equal(safeImageUrl("javascript:alert(1)"), null);
  assert.equal(safeImageUrl("not a url"), null);
  assert.equal(safeImageUrl(null), null);

  const safe = render(<CategoryTable categories={[summary({ image: { url: "https://cdn.test/a.webp" } })]} />);
  assert.match(safe, /href="https:\/\/cdn\.test\/a\.webp"/);
  const unsafe = render(<CategoryTable categories={[summary({ image: { url: "javascript:alert(1)" } })]} />);
  assert.equal(/javascript:/.test(unsafe), false, "an unsafe scheme must never be linked.");
  assert.ok(text(unsafe).includes("Not set"));
});

test("the detail view shows the authoritative category fields", () => {
  const markup = render(<CategoryDetailView category={{ id: "cat_1", name: "Living Room", slug: "living-room", description: "A place to relax.", image: { url: "https://cdn.test/a.webp" }, createdAt: "2026-09-29T13:45:00.000Z" }} />);
  const visible = text(markup);
  for (const value of ["living-room", "A place to relax.", "29 Sept 2026, 13:45"]) {
    assert.ok(visible.includes(value), `the detail view must render ${value}.`);
  }
  assert.equal(/parent|hierarchy state/i.test(visible), false, "no inferred hierarchy or state may be displayed.");

  const empty = text(render(<CategoryDetailView category={{ id: "cat_1", name: "Living Room", slug: "living-room", description: null, image: null, createdAt: null }} />));
  assert.ok(empty.includes("No description is recorded for this category."));
  assert.ok(empty.includes("No image is recorded for this category."));
  assert.equal(formatCategoryTimestamp("not-a-date"), null);
});

test("a rate-limited read renders a distinct, retry-aware message", () => {
  assert.match(text(render(<CategoryFailureState failure="throttled" retryAfterSeconds={30} />)), /Too many requests/);
  assert.match(text(render(<CategoryFailureState failure="throttled" retryAfterSeconds={30} />)), /30 seconds/);
});

test("expected read failures render the established distinct states and 404 is not-found", () => {
  assert.match(FAILURE_COMPONENT, /if \(failure === "session"\) state = <SessionInvalidState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "forbidden"\) state = <AccessDeniedState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "unavailable"\) state = <ServiceUnavailableState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "throttled"/);
  assert.match(DETAIL_PAGE, /failure === "not-found"\) notFound\(\)/, "a missing category resolves to not-found, not a generic error.");
  assert.match(EDIT_PAGE, /failure === "not-found"\) notFound\(\)/);
});

test("the list page keeps loading, empty and failure states distinct and does not fabricate data", () => {
  assert.match(LIST_PAGE, /if \(!page\.ok\) return <CategoryFailureState/);
  assert.match(LIST_PAGE, /page\.value\.categories\.length === 0/);
  assert.match(LIST_PAGE, /<EmptyState title="No categories were returned"/);
  assert.equal(/sample|dummy|fixture|mock|count|total/i.test(LIST_PAGE), false, "the list must not fabricate data or counts.");
});

test("the create and edit pages preserve the form contract and the edit page loads first", () => {
  assert.match(NEW_PAGE, /mode="create"/);
  assert.match(NEW_PAGE, /EMPTY_CATEGORY_FORM_VALUES/);
  assert.match(EDIT_PAGE, /mode="edit"/);
  assert.match(EDIT_PAGE, /categoryFormValuesFromCategory\(category\.value\)/);
  assert.match(EDIT_PAGE, /categoryId=\{category\.value\.id\}/);
  assert.ok(EDIT_PAGE.indexOf("attemptCategoryRead") < EDIT_PAGE.indexOf("<CategoryForm"), "the edit form must only render once the resource is loaded.");
});

test("the form associates every control with a label and its server error", () => {
  for (const name of ["CATEGORY_FORM_FIELDS.name", "CATEGORY_FORM_FIELDS.slug", "CATEGORY_FORM_FIELDS.image", "CATEGORY_FORM_FIELDS.description"]) {
    assert.ok(FORM_COMPONENT.includes(`name={${name}}`), `${name} must be a labelled control.`);
  }
  assert.match(FORM_COMPONENT, /error=\{Boolean\(fieldErrors\[CATEGORY_FORM_FIELDS\.name\]\)\}/);
  assert.match(FORM_COMPONENT, /helperText=\{fieldErrors\[CATEGORY_FORM_FIELDS\.name\]/);
  assert.match(FORM_COMPONENT, /<Alert severity="error">\{state\.formError\}<\/Alert>/);
  assert.match(FORM_COMPONENT, /disabled=\{pending\}/, "a submission in flight must be blocked.");
  assert.match(FORM_COMPONENT, /aria-busy=\{pending\}/);
});

test("a failed submit keeps the operator's edits because every control is controlled", () => {
  assert.match(FORM_COMPONENT, /const \[draft, setDraft\] = useState\(values\)/);
  assert.equal(/defaultValue=|defaultChecked=/.test(FORM_COMPONENT), false, "React resets uncontrolled fields after a form action, discarding the edits a 422 is reporting on.");
  for (const field of ["name", "slug", "image", "description"]) {
    assert.ok(FORM_COMPONENT.includes(`value={draft.${field}}`), `${field} must render from the controlled draft, so a failed submit cannot reset it.`);
  }
  assert.match(FORM_COMPONENT, /JSON\.stringify\(values\)/, "the partial-update diff must stay against the loaded category, not the draft.");
});

test("no unsupported delete, state or parent mutation is offered", () => {
  const combined = ACTIONS + FORM_COMPONENT + CATEGORIES_API + LIST_PAGE + DETAIL_PAGE + EDIT_PAGE;
  assert.equal(/DELETE|deleteCategory|archiveCategory|parent_id|is_active|is_published/.test(combined), false, "no unsupported hierarchy or state mutation may be introduced.");
  assert.equal(/Add sub-?category|Create child/i.test(combined), false);
});

test("the four category pages create no second main landmark", () => {
  for (const source of ALL_CATEGORY_PAGES) {
    assert.equal(source.includes('component="main"'), false, "the shell owns the single main landmark.");
    assert.match(source, /<PageContainer aria-labelledby="page-title">/);
  }
});
