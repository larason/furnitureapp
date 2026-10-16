import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { ProductDetailView } from "../components/catalog/product-detail-view";
import { ProductFailureState } from "../components/catalog/product-failure-state";
import { ProductListFilters } from "../components/catalog/product-list-filters";
import { ProductListPagination } from "../components/catalog/product-list-pagination";
import { ProductStateChips } from "../components/catalog/product-state-chips";
import { ProductTable } from "../components/catalog/product-table";
import { ProductVariantTable } from "../components/catalog/product-variant-table";
import { AdminPermissionName } from "../lib/auth/permission-names";
import { parseCategoryOptions } from "../lib/catalog/category-option";
import {
  EMPTY_PRODUCT_FORM_VALUES,
  buildCreatePayload,
  buildUpdatePayload,
  fieldErrorsFromApi,
  formatMinorUnitsForInput,
  parseMajorUnitsToMinorUnits,
  readProductFormValues,
  type ProductFormValues,
} from "../lib/catalog/product-form";
import { availabilityLabel, formatProductPrice, formatProductTimestamp, productStateDescriptors, productTypeLabel, stockIndicatorLabel } from "../lib/catalog/product-presentation";
import { countProductFilters, parseProductListQuery, productListHref, productListRequestQuery } from "../lib/catalog/product-query";
import { ProductResourceError, parseOperationalProduct, parseProductPage, type OperationalProduct } from "../lib/catalog/product-resource";
import { ADMIN_PRODUCT_PATHS, ADMIN_PROTECTED_MATCHERS, ADMIN_ROUTE_PATHS } from "../lib/navigation/admin-route-paths";
import { adminRoute } from "../lib/navigation/admin-navigation";
import { theme } from "../theme";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const read = (relative: string): string => readFileSync(join(ADMIN_ROOT, relative), "utf8");

const LIST_PAGE = read("app/(protected)/products/page.tsx");
const NEW_PAGE = read("app/(protected)/products/new/page.tsx");
const DETAIL_PAGE = read("app/(protected)/products/[product]/page.tsx");
const EDIT_PAGE = read("app/(protected)/products/[product]/edit/page.tsx");
const ACTIONS = read("lib/catalog/product-actions.ts");
const PRODUCTS_API = read("lib/catalog/products-api.ts");
const SERVER_REQUEST = read("lib/api/server-request.ts");
const FORM_COMPONENT = read("components/catalog/product-form.tsx");
const DETAIL_VIEW = read("components/catalog/product-detail-view.tsx");
const FILTERS_COMPONENT = read("components/catalog/product-list-filters.tsx");
const FAILURE_COMPONENT = read("components/catalog/product-failure-state.tsx");

const ALL_PRODUCT_PAGES = [LIST_PAGE, NEW_PAGE, DETAIL_PAGE, EDIT_PAGE];

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const text = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
const withoutImports = (source: string): string => source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");

function rawProduct(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: "prd_1", name: "Oak Dining Chair", slug: "oak-dining-chair", product_type: "IN_STOCK",
    price: { amount: 150000, currency: "TZS" },
    category: { id: "cat_1", slug: "chairs", name: "Chairs" },
    availability: "available", stock_indicator: "IN_STOCK",
    ...overrides,
  };
}

function mockProduct(overrides: Partial<OperationalProduct> = {}): OperationalProduct {
  return {
    id: "prd_1",
    name: "Oak Dining Chair",
    slug: "oak-dining-chair",
    productType: "IN_STOCK",
    price: { amount: 150000, currency: "TZS" },
    category: { id: "cat_1", slug: "chairs", name: "Chairs", description: null },
    primaryImage: null,
    availability: "available",
    stockIndicator: "IN_STOCK",
    description: "Solid oak.",
    images: [],
    variants: [],
    createdAt: "2026-09-29T13:45:00.000Z",
    updatedAt: "2026-09-29T13:45:00.000Z",
    isActive: true,
    isPublished: true,
    ...overrides,
  };
}

function validFormValues(overrides: Partial<ProductFormValues> = {}): ProductFormValues {
  return { name: "Oak chair", slug: "oak-chair", description: "A chair.", productType: "IN_STOCK", priceAmount: "1500.00", categoryId: "cat_1", isActive: true, isPublished: false, ...overrides };
}

// ---------------------------------------------------------------------------
// Authorization and route protection
// ---------------------------------------------------------------------------

test("every product page repeats the route requirement before any data access", () => {
  for (const source of ALL_PRODUCT_PAGES) {
    assert.match(source, /requireAdminRouteAccess\(ROUTE\)/, "each page must repeat the route guard.");
    assert.match(source, /if \(!access\.granted\) return access\.denial;/, "each page must render the denial state.");
    const paramsIndex = source.indexOf("await params");
    if (paramsIndex !== -1) assert.ok(source.indexOf("requireAdminRouteAccess(") < paramsIndex, "authorization must precede resource resolution.");
  }
});

test("the products route keeps its URL, registry entry and exact permission", () => {
  assert.equal(ADMIN_ROUTE_PATHS.products, "/products");
  const route = adminRoute(ADMIN_ROUTE_PATHS.products);
  assert.equal(route.href, "/products");
  assert.equal(route.permission, AdminPermissionName.PRODUCTS_MANAGE);
  assert.equal(route.permission, "products.manage", "the registry must use the verified Laravel permission, never an invented products.view.");
  assert.equal(route.placeholder, undefined, "the products workflow is implemented and is not a placeholder.");
});

test("nested product routes stay covered by the existing protected matcher", () => {
  assert.ok(ADMIN_PROTECTED_MATCHERS.includes("/products(.*)"), "the /products matcher must cover nested routes.");
  assert.equal(ADMIN_PRODUCT_PATHS.list, ADMIN_ROUTE_PATHS.products);
  assert.equal(ADMIN_PRODUCT_PATHS.create, "/products/new");
  assert.equal(ADMIN_PRODUCT_PATHS.detail("prd_1"), "/products/prd_1");
  assert.equal(ADMIN_PRODUCT_PATHS.edit("prd_1"), "/products/prd_1/edit");
});

test("server actions re-verify the exact permission because they are directly reachable", () => {
  assert.match(ACTIONS, /^"use server";/);
  const guardCalls = ACTIONS.match(/await authorizationFailure\(\)/g) ?? [];
  assert.equal(guardCalls.length, 2, "both create and update must re-authorize.");
  assert.match(ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{ permission: PRODUCT_ROUTE\.permission, role: PRODUCT_ROUTE\.role \}\)/);
  const body = withoutImports(ACTIONS);
  assert.ok(body.indexOf("await authorizationFailure()") < body.indexOf("submitProductCreate"), "authorization must run before the create request.");
  assert.ok(body.indexOf("await authorizationFailure()") < body.indexOf("submitProductUpdate"), "authorization must run before the update request.");
});

test("no bearer token, raw session or token getter crosses into a client component", () => {
  for (const source of ALL_PRODUCT_PAGES) {
    assert.equal(/Bearer|getToken|session_token|eyJ/.test(source), false, "a page must not expose a token.");
  }
  assert.equal(/Bearer|getToken|token/.test(FORM_COMPONENT), false, "the form must not receive a token.");
  for (const relative of ["components/catalog/product-form.tsx", "components/catalog/product-table.tsx", "components/catalog/product-variant-table.tsx", "components/catalog/product-list-filters.tsx"]) {
    const source = read(relative);
    assert.match(source, /^"use client";/);
    assert.equal(/server-only|server-request|lib\/api\/client/.test(source), false, `${relative} must not import the server transport.`);
  }
});

test("authenticated product requests stay request-scoped and no-store", () => {
  assert.match(SERVER_REQUEST, /await auth\(\)/);
  assert.match(SERVER_REQUEST, /await session\.getToken\(\)/);
  assert.equal(/unstable_cache|force-cache|revalidate:/.test(SERVER_REQUEST), false, "the transport must not retain a shared cache.");
  for (const relative of ["lib/catalog/products-api.ts", "lib/api/server-request.ts"]) {
    assert.equal(/unstable_cache|force-cache|next: \{ revalidate/.test(read(relative)), false, `${relative} must not add a shared cache.`);
  }
  assert.match(PRODUCTS_API, /requestOperationalApi/);
  assert.match(PRODUCTS_API, /requestPublicApi/);
  assert.match(read("lib/api/client.ts"), /cache: token \? "no-store" : undefined/);
});

test("only the approved endpoints are contacted and no unapproved resource is introduced", () => {
  for (const path of ['"/admin/products"', "`/admin/products/${encodeURIComponent(identifier)}`", 'path: "/products"', 'path: `/products/${encodeURIComponent(identifier)}`', 'path: "/categories"']) {
    assert.ok(PRODUCTS_API.includes(path), `${path} must be the verified contract path.`);
  }
  for (const forbidden of ["/inventory", "/orders", "/requests", "/enquiries", "/customers", "/audit", "variants"]) {
    assert.equal(PRODUCTS_API.includes(forbidden), false, `the product API module must not call ${forbidden}.`);
  }
  assert.ok(PRODUCTS_API.includes("`/products/${encodeURIComponent(identifier)}/images`"), "the approved media collection must remain the base path for CAT-009 and CAT-018.");
  assert.match(PRODUCTS_API, /method: "DELETE"/, "CAT-015 must be the approved delete.");
  assert.match(PRODUCTS_API, /method: "PATCH"/, "CAT-016 must be the approved alt-text update.");
  assert.equal(/"sort"|sort_by|order_by/.test(PRODUCTS_API), false, "no sort parameter may be invented.");
});

test("variant creation is never presented as functional while CAT-010 returns 501", () => {
  assert.equal(/variants/.test(PRODUCTS_API), false, "no variant mutation may be wired.");
  assert.match(DETAIL_VIEW, /Variant creation is not available in this release/);
  assert.equal(/Add variant|Create variant/.test(DETAIL_VIEW + DETAIL_PAGE), false, "no enabled variant-creation control may exist.");
});

// ---------------------------------------------------------------------------
// Product list query contract
// ---------------------------------------------------------------------------

test("the list query accepts only the documented parameters and values", () => {
  const parsed = parseProductListQuery({ search: "chair", category: "cat_1", product_type: "MADE_TO_ORDER", is_active: "true", is_published: "false", availability: "available", page: "2", per_page: "50" });
  assert.deepEqual(parsed, { search: "chair", category: "cat_1", productType: "MADE_TO_ORDER", isActive: true, isPublished: false, availability: "available", page: 2, perPage: 50 });
});

test("unknown, malformed and out-of-range filter values degrade without inventing a filter", () => {
  const parsed = parseProductListQuery({ product_type: "BOGUS", availability: "maybe", is_active: "1", page: "0", per_page: "500" });
  assert.deepEqual(parsed, { search: null, category: null, productType: null, isActive: null, isPublished: null, availability: null, page: 1, perPage: 20 });
  assert.equal(parseProductListQuery({ per_page: "100" }).perPage, 100);
  assert.equal(parseProductListQuery({ search: "x".repeat(101) }).search, null, "search is capped at the contract maximum.");
});

test("the request query uses the exact frozen parameter names", () => {
  const query = parseProductListQuery({ search: "chair", category: "cat_1", product_type: "IN_STOCK", is_active: "true", is_published: "true", availability: "unavailable", page: "3", per_page: "25" });
  assert.deepEqual(Object.keys(productListRequestQuery(query)).sort(), ["availability", "category", "is_active", "is_published", "page", "per_page", "product_type", "search"].sort());
  assert.deepEqual(productListRequestQuery(parseProductListQuery({})), { page: 1, per_page: 20 });
});

test("pagination links preserve filters and totals come from server metadata", () => {
  const query = parseProductListQuery({ search: "chair", page: "2" });
  assert.equal(productListHref({ ...query, page: 1 }), "/products?search=chair");
  assert.equal(productListHref({ ...query, page: 3 }), "/products?search=chair&page=3");
  assert.equal(countProductFilters(query), 1);
  assert.equal(countProductFilters(parseProductListQuery({})), 0);
});

// ---------------------------------------------------------------------------
// Resource parsing
// ---------------------------------------------------------------------------

test("the operational product resource is read from the API, not reconstructed", () => {
  const product = parseOperationalProduct({
    id: "prd_1", name: "Oak chair", slug: "oak-chair", product_type: "IN_STOCK",
    price: { amount: 150000, currency: "TZS" },
    category: { id: "cat_1", slug: "chairs", name: "Chairs" },
    availability: "available", stock_indicator: "IN_STOCK",
    is_active: false, is_published: true, description: "Solid oak.",
    images: [{ id: "img_1", url: "https://cdn.test/1.jpg", alt_text: "Oak chair", sort_order: 0, is_primary: true }],
    variants: [{ id: "var_1", sku: "OAK-1", name: "Natural", price: { amount: 160000, currency: "TZS" }, availability: "available", stock_indicator: "LOW_STOCK" }],
    created_at: "2026-09-29T13:45:00.000Z", updated_at: "2026-09-30T08:00:00.000Z",
  });

  assert.equal(product.isActive, false);
  assert.equal(product.isPublished, true);
  assert.equal(product.variants.length, 1);
  assert.equal(product.variants[0]?.sku, "OAK-1");
  assert.equal(product.images[0]?.isPrimary, true);
  assert.equal(product.createdAt, "2026-09-29T13:45:00.000Z");
});

test("an unusable product response is rejected rather than guessed", () => {
  assert.throws(() => parseOperationalProduct({ id: "prd_1", name: "Oak chair" }), ProductResourceError);
  assert.throws(() => parseOperationalProduct(rawProduct({ price: { amount: "free", currency: "TZS" } })), ProductResourceError);
  assert.throws(() => parseProductPage({ data: [] }), ProductResourceError);
  assert.throws(() => parseProductPage({ data: [], meta: { pagination: { current_page: 0, per_page: 20, total: 0, last_page: 1 } } }), ProductResourceError);
});

test("missing optional relations are empty, never fabricated, and unknown states are preserved", () => {
  const product = parseOperationalProduct({ id: "prd_1", name: "Oak chair", slug: "oak-chair", product_type: "FUTURE_TYPE", price: { amount: 0, currency: "TZS" }, category: { id: "cat_1", slug: "chairs", name: "Chairs" }, availability: "future_state", stock_indicator: "future_stock" });
  assert.equal(product.isActive, null);
  assert.equal(product.isPublished, null);
  assert.deepEqual(product.variants, []);
  assert.deepEqual(product.images, []);
  assert.equal(product.availability, "future_state");
});

test("pagination metadata is used verbatim from the server response", () => {
  const page = parseProductPage({ data: [rawProduct()], meta: { pagination: { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true } } });
  assert.equal(page.products.length, 1);
  assert.deepEqual(page.pagination, { current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true });
});

test("category options come from the API-returned identifiers only", () => {
  const options = parseCategoryOptions([{ id: "cat_1", name: "Chairs", slug: "chairs" }, { id: "cat_2", name: "Tables", slug: "tables" }, { name: "No id" }]);
  assert.deepEqual(options, [{ id: "cat_1", name: "Chairs", slug: "chairs" }, { id: "cat_2", name: "Tables", slug: "tables" }]);
  assert.deepEqual(parseCategoryOptions({ data: [] }), [], "a non-collection is not a category catalog.");
});

// ---------------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------------

test("product type, availability, stock and state map through fixed labels with a safe fallback", () => {
  assert.equal(productTypeLabel("IN_STOCK"), "In stock");
  assert.equal(productTypeLabel("MADE_TO_ORDER"), "Made to order");
  assert.equal(productTypeLabel("FUTURE"), "Unrecognised value");
  assert.equal(availabilityLabel("available"), "Available");
  assert.equal(availabilityLabel("future"), "Availability unknown");
  assert.equal(stockIndicatorLabel("LOW_STOCK"), "Low stock");
  assert.equal(stockIndicatorLabel("future"), "Stock unknown");
});

test("state descriptors distinguish an unknown value from a confident state", () => {
  assert.deepEqual(productStateDescriptors({ isActive: true, isPublished: false }).map((descriptor) => descriptor.label), ["Active", "Unpublished"]);
  assert.deepEqual(productStateDescriptors({ isActive: null, isPublished: null }).map((descriptor) => descriptor.label), ["Active: unknown", "Published: unknown"]);
});

test("money and timestamps are formatted from authoritative integer minor units", () => {
  assert.match(formatProductPrice({ amount: 150000, currency: "TZS" }), /^TZS\s1,500\.00$/);
  assert.match(formatProductPrice({ amount: 0, currency: "TZS" }), /^TZS\s0\.00$/);
  assert.equal(formatProductPrice({ amount: 100, currency: "USD" }), "Price unavailable");
  assert.equal(formatProductTimestamp("2026-09-29T13:45:00.000Z"), "29 Sept 2026, 13:45");
  assert.equal(formatProductTimestamp(null), null);
  assert.equal(formatProductTimestamp("not-a-date"), null);
});

// ---------------------------------------------------------------------------
// Create / edit payloads
// ---------------------------------------------------------------------------

test("the create payload contains exactly the approved request fields", () => {
  const { payload, fieldErrors } = buildCreatePayload(validFormValues({ description: "", isActive: false, isPublished: true }));
  assert.deepEqual(fieldErrors, {});
  assert.deepEqual(Object.keys(payload ?? {}).sort(), ["category_id", "is_active", "is_published", "name", "price", "product_type", "slug"].sort());
  assert.deepEqual(payload?.price, { amount: 150000, currency: "TZS" });
  assert.equal(payload?.is_active, false);
  assert.equal(payload?.is_published, true);
  assert.equal(payload?.product_type, "IN_STOCK");
  assert.equal("description" in (payload ?? {}), false, "an empty optional description is not sent.");
});

test("client validation is advisory and blocks an invalid payload before the request", () => {
  assert.equal(buildCreatePayload(validFormValues({ name: "" })).payload, null);
  assert.match(buildCreatePayload(validFormValues({ slug: "Not A Slug" })).fieldErrors.slug ?? "", /lowercase/);
  assert.equal(buildCreatePayload(validFormValues({ categoryId: "" })).payload, null);
  assert.equal(buildCreatePayload(validFormValues({ priceAmount: "free" })).payload, null);
  assert.equal(buildCreatePayload(validFormValues({ productType: "BOGUS" })).payload, null);
});

test("an update sends only the fields the operator changed", () => {
  const original = validFormValues();
  const unchanged = buildUpdatePayload(original, original);
  assert.equal(unchanged.payload, null, "a no-op update must not reach the API.");
  assert.match(unchanged.fieldErrors.name ?? "", /Change at least one field/);

  const changed = buildUpdatePayload({ ...original, name: "Renamed chair", isPublished: true }, original);
  assert.deepEqual(Object.keys(changed.payload ?? {}).sort(), ["is_published", "name"].sort());
  assert.equal(changed.payload?.name, "Renamed chair");

  const priceChanged = buildUpdatePayload({ ...original, priceAmount: "1600.50" }, original);
  assert.deepEqual(priceChanged.payload, { price: { amount: 160050, currency: "TZS" } });
});

test("major/minor unit conversion is exact and reversible", () => {
  assert.equal(parseMajorUnitsToMinorUnits("1500.00"), 150000);
  assert.equal(parseMajorUnitsToMinorUnits("1,500.00"), 150000);
  assert.equal(parseMajorUnitsToMinorUnits("0.05"), 5);
  assert.equal(parseMajorUnitsToMinorUnits("12.3"), 1230);
  assert.equal(parseMajorUnitsToMinorUnits("1.234"), null);
  assert.equal(parseMajorUnitsToMinorUnits("-1"), null);
  assert.equal(formatMinorUnitsForInput(150000), "1500.00");
  assert.equal(formatMinorUnitsForInput(5), "0.05");
});

test("API validation errors map to their form controls", () => {
  const { fieldErrors, unmapped } = fieldErrorsFromApi([
    { code: "INVALID_VALUE", message: "The price amount must be an integer.", field: "price.amount" },
    { code: "INVALID_VALUE", message: "This slug is already in use.", field: "slug" },
    { code: "BUSINESS_RULE_VIOLATION", message: "Published in-stock products are not available in this release.", field: "is_published" },
    { code: "INVALID_VALUE", message: "Something else went wrong." },
  ]);
  assert.equal(fieldErrors.price_amount, "The price amount must be an integer.");
  assert.equal(fieldErrors.slug, "This slug is already in use.");
  assert.equal(fieldErrors.is_published, "Published in-stock products are not available in this release.", "the server's own message must be preserved, not replaced.");
  assert.deepEqual(unmapped, ["Something else went wrong."]);
});

test("form values are read from the submitted form data, including checkboxes", () => {
  const formData = new FormData();
  formData.set("name", "Oak chair");
  formData.set("slug", "oak-chair");
  formData.set("product_type", "MADE_TO_ORDER");
  formData.set("price_amount", "1500.00");
  formData.set("category_id", "cat_1");
  formData.set("is_active", "on");
  const values = readProductFormValues(formData);
  assert.equal(values.name, "Oak chair");
  assert.equal(values.productType, "MADE_TO_ORDER");
  assert.equal(values.isActive, true);
  assert.equal(values.isPublished, false);
  assert.equal(values.description, "");
});

test("the empty create defaults are not a saved product", () => {
  assert.equal(EMPTY_PRODUCT_FORM_VALUES.name, "");
  assert.equal(EMPTY_PRODUCT_FORM_VALUES.priceAmount, "");
  assert.equal(EMPTY_PRODUCT_FORM_VALUES.categoryId, "");
  assert.equal(EMPTY_PRODUCT_FORM_VALUES.isPublished, false);
});

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

test("the product table renders only API-returned values with a named detail link", () => {
  const markup = render(<ProductTable products={[mockProduct()]} />);
  const visible = text(markup);
  for (const value of ["Oak Dining Chair", "oak-dining-chair", "In stock", "Chairs", "Available", "TZS"]) {
    assert.ok(visible.includes(value), `the row must render ${value}.`);
  }
  assert.match(markup, /href="\/products\/prd_1"/);
  assert.ok(visible.includes("View Oak Dining Chair"), "the row action needs a meaningful accessible name.");
});

test("the state chips label real state without relying on colour alone", () => {
  const markup = render(<ProductStateChips descriptors={productStateDescriptors({ isActive: false, isPublished: true })} />);
  assert.match(markup, /aria-label="Status: Inactive"/);
  assert.match(markup, /aria-label="Status: Published"/);
});

test("the detail view shows authoritative product fields and honest variant data", () => {
  const withVariant = mockProduct({
    variants: [{ id: "var_1", sku: "OAK-1", name: "Natural", price: { amount: 160000, currency: "TZS" }, availability: "available", stockIndicator: "LOW_STOCK" }],
  });
  const markup = render(<ProductDetailView product={withVariant} media={<p>Supplied media block</p>} />);
  const visible = text(markup);
  assert.ok(visible.includes("OAK-1"));
  assert.ok(visible.includes("Low stock"));
  assert.ok(visible.includes("Supplied media block"), "the media section renders exactly the block the page supplies.");
  assert.ok(visible.includes("Variant creation is not available in this release"));

  const withoutVariant = render(<ProductDetailView product={mockProduct()} />);
  assert.ok(text(withoutVariant).includes("No variants were returned for this product by the current API."));
});

test("the variant table renders only the fields the resource returns", () => {
  const markup = render(<ProductVariantTable variants={[{ id: "var_1", sku: "OAK-1", name: "Natural", price: { amount: 160000, currency: "TZS" }, availability: "available", stockIndicator: "IN_STOCK" }]} />);
  const visible = text(markup);
  assert.deepEqual([...markup.matchAll(/<th[^>]*>(.*?)<\/th>/g)].map((match) => text(match[1])), ["SKU", "Variant", "Price", "Availability", "Stock"]);
  assert.ok(visible.includes("OAK-1") && visible.includes("Natural"));
});

test("the filter form offers only supported filters and keeps state in the URL", () => {
  const markup = render(<ProductListFilters query={parseProductListQuery({ search: "chair" })} categories={[{ id: "cat_1", name: "Chairs", slug: "chairs" }]} categoriesUnavailable={false} />);
  const visible = text(markup);
  for (const label of ["Search", "Category", "Product type", "Availability", "Active", "Published", "Apply filters", "Clear filters"]) {
    assert.ok(visible.includes(label), `the filter form must expose ${label}.`);
  }
  assert.match(markup, /name="search"/);
  assert.match(markup, /name="product_type"/);
  assert.equal(/name="sort"|name="order"/.test(markup), false, "no unsupported sort control may exist.");
  assert.match(FILTERS_COMPONENT, /method="get"/);
  assert.equal(/fetch\(|useEffect/.test(FILTERS_COMPONENT), false, "filtering must not issue a request per keystroke.");
});

test("pagination renders the server's totals and links", () => {
  const markup = render(<ProductListPagination pagination={{ current_page: 2, per_page: 20, total: 45, last_page: 3, has_next: true, has_previous: true }} query={parseProductListQuery({ search: "chair" })} />);
  assert.ok(text(markup).includes("Page 2 of 3 · 45 products"));
  assert.match(markup, /href="\/products\?search=chair"/);
  assert.match(markup, /href="\/products\?search=chair&amp;page=3"/);
});

test("a rate-limited read renders a distinct, retry-aware message", () => {
  assert.match(text(render(<ProductFailureState failure="throttled" retryAfterSeconds={30} />)), /Too many requests/);
  assert.match(text(render(<ProductFailureState failure="throttled" retryAfterSeconds={30} />)), /30 seconds/);
});

test("expected read failures render the established distinct states", () => {
  assert.match(FAILURE_COMPONENT, /if \(failure === "session"\) state = <SessionInvalidState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "forbidden"\) state = <AccessDeniedState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "unavailable"\) state = <ServiceUnavailableState \/>/);
  assert.match(FAILURE_COMPONENT, /failure === "throttled"/);
  assert.match(DETAIL_PAGE, /failure === "not-found"\) notFound\(\)/, "a missing product resolves to not-found, not a generic error.");
});

test("an unreadable response is classified as invalid data, not a service outage", () => {
  const start = PRODUCTS_API.indexOf("function describeProductApiFailure");
  assert.ok(start > -1, "the failure classifier must exist.");

  const unreadable = PRODUCTS_API.indexOf("error instanceof ProductResourceError", start);
  const apiError = PRODUCTS_API.indexOf("error instanceof ApiError", start);
  assert.ok(unreadable > -1, "an unreadable response must be classified explicitly.");
  assert.ok(apiError > unreadable, "the resource error must be classified before the status fallback.");

  assert.equal(/failure === "invalid"/.test(FAILURE_COMPONENT), false, "\"invalid\" must fall through to the unreadable-response default.");
  assert.match(FAILURE_COMPONENT, /description="The product service returned a response this application could not read\."/);
});

test("the list page keeps loading, empty and failure states distinct and does not fabricate data", () => {
  assert.match(LIST_PAGE, /if \(!page\.ok\) return <ProductFailureState/);
  assert.match(LIST_PAGE, /page\.value\.products\.length === 0/);
  assert.match(LIST_PAGE, /<EmptyState title="No products found"/);
  assert.match(LIST_PAGE, /countProductFilters\(query\)/);
  assert.equal(/revenue|sales|analytics|sample|dummy|fixture|mock/i.test(LIST_PAGE), false);
});

test("the create and edit pages preserve the form contract and the edit page loads first", () => {
  assert.match(NEW_PAGE, /mode="create"/);
  assert.match(NEW_PAGE, /value\.options : \[\]/);
  assert.match(EDIT_PAGE, /mode="edit"/);
  assert.match(EDIT_PAGE, /productFormValuesFromProduct\(product\.value\)/);
  assert.match(EDIT_PAGE, /productId=\{product\.value\.id\}/);
  assert.ok(EDIT_PAGE.indexOf("attemptProductRead") < EDIT_PAGE.indexOf("<ProductForm"), "the edit form must only render once the resource is loaded.");
});

test("the form associates every control with a label and its server error", () => {
  for (const name of ["PRODUCT_FORM_FIELDS.name", "PRODUCT_FORM_FIELDS.slug", "PRODUCT_FORM_FIELDS.priceAmount", "PRODUCT_FORM_FIELDS.categoryId", "PRODUCT_FORM_FIELDS.description", "PRODUCT_FORM_FIELDS.productType"]) {
    assert.ok(FORM_COMPONENT.includes(`name={${name}}`), `${name} must be a labelled control.`);
  }
  assert.match(FORM_COMPONENT, /error=\{Boolean\(fieldErrors\[PRODUCT_FORM_FIELDS\.name\]\)\}/);
  assert.match(FORM_COMPONENT, /helperText=\{fieldErrors\[PRODUCT_FORM_FIELDS\.name\]/);
  assert.match(FORM_COMPONENT, /<Alert severity="error">\{state\.formError\}<\/Alert>/);
  assert.match(FORM_COMPONENT, /disabled=\{pending \|\| categoryBlocked\}/, "a submission in flight must be blocked and announced.");
  assert.match(FORM_COMPONENT, /aria-busy=\{pending\}/);
});

test("a failed submit keeps the operator's edits because every control is controlled", () => {
  assert.match(FORM_COMPONENT, /const \[draft, setDraft\] = useState\(values\)/);
  assert.equal(/defaultValue=|defaultChecked=/.test(FORM_COMPONENT), false, "React resets uncontrolled fields after a form action, discarding the edits a 422 is reporting on.");
  for (const field of ["name", "slug", "productType", "priceAmount", "categoryId", "description"]) {
    assert.ok(FORM_COMPONENT.includes(`value={draft.${field}}`), `${field} must render from the controlled draft, so a failed submit cannot reset it.`);
  }
  assert.match(FORM_COMPONENT, /checked=\{draft\.isActive\}/);
  assert.match(FORM_COMPONENT, /checked=\{draft\.isPublished\}/);
  assert.match(FORM_COMPONENT, /JSON\.stringify\(values\)/, "the partial-update diff must stay against the loaded product, not the draft.");
});

test("the product form never assumes a made-to-order product is invalid", () => {
  assert.match(FORM_COMPONENT, /PRODUCT_TYPE_VALUES\.map/);
  assert.equal(/MADE_TO_ORDER.*disabled|disabled.*MADE_TO_ORDER/.test(FORM_COMPONENT), false);
  const { payload } = buildCreatePayload(validFormValues({ productType: "MADE_TO_ORDER" }));
  assert.equal(payload?.product_type, "MADE_TO_ORDER");
});

test("the four product pages load no data before admission and create no second main landmark", () => {
  for (const source of ALL_PRODUCT_PAGES) {
    assert.equal(source.includes('component="main"'), false, "the shell owns the single main landmark.");
    assert.match(source, /<PageContainer aria-labelledby="page-title">/);
  }
});
