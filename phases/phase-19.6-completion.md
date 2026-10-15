# Phase 19.6 — Product Management

## Status

**PASS**, with two explicitly reported backend-dependent limitations that do not block the phase (§14):

- **Variant creation is unavailable** because `POST /api/v1/products/{product}/variants` returns `501`. The UI shows variant data read-only and states the dependency; no control promises a working mutation.
- **Product media management is deferred to Phase 19.8** (`19.8 — Product Media Management - cloudflare R2`). The detail page reports the image-record count returned by the API; upload, ordering, deletion and preview are not implemented.

Both are limitations the phase instructions anticipated, not contract workarounds. A third limitation — category options — is workable (§9).

Nothing is staged or committed. The pre-existing uncommitted edits to `phases/group-S-phases.md`, `future.md` and root `AGENTS.md` are the owner's and were left untouched.

## 1. Files changed

**Modified**

- `frontend/admin/app/(protected)/products/page.tsx` — placeholder replaced by the operational list (filters, table, server pagination).
- `frontend/admin/lib/navigation/admin-route-paths.ts` — added `ADMIN_PRODUCT_PATHS` (nested routes for the `/products` destination, deliberately excluded from `ADMIN_PROTECTED_PATHS`/`ADMIN_PROTECTED_MATCHERS`).
- `frontend/admin/lib/navigation/admin-navigation.ts` — removed `placeholder`/`plannedFor` from the `products` entry; its permission (`products.manage`) and URL are unchanged.
- `frontend/admin/tests/navigation.test.ts` — split the "page repeats its requirement / loads no business data" test; placeholders still may not call business APIs, implemented routes must authorize before data access; the "every protected page belongs to a configured route" test now accepts nested pages under a configured destination.
- `frontend/admin/tests/overview.test.tsx` — an implemented route is no longer required to be a placeholder.
- `frontend/admin/package.json` — `test` script includes `tests/products.test.tsx`.
- `frontend/admin/README.md`, `frontend/admin/AGENTS.md` — Products module architecture, contract, permission and limitations.

**Created**

- `frontend/admin/app/(protected)/products/new/page.tsx` — create page.
- `frontend/admin/app/(protected)/products/[product]/page.tsx` — detail page.
- `frontend/admin/app/(protected)/products/[product]/edit/page.tsx` — edit page.
- `frontend/admin/lib/api/server-request.ts` — request-scoped authenticated/public transport helpers.
- `frontend/admin/lib/catalog/product-resource.ts` — operational product resource types + defensive parsers.
- `frontend/admin/lib/catalog/product-presentation.ts` — type/state/availability/stock labels, price and timestamp formatting.
- `frontend/admin/lib/catalog/product-query.ts` — filter vocabulary, URL/request query builders, pagination href.
- `frontend/admin/lib/catalog/product-form.ts` — form values, create/update payload builders, API-error field mapping, minor-unit conversion.
- `frontend/admin/lib/catalog/category-option.ts` — category option parser.
- `frontend/admin/lib/catalog/products-api.ts` — server-only reads/writes + failure taxonomy.
- `frontend/admin/lib/catalog/product-actions.ts` — `"use server"` create/update actions.
- `frontend/admin/components/catalog/{product-table,product-variant-table,product-detail-view,product-state-chips,product-failure-state,product-list-filters,product-list-pagination,product-form}.tsx`
- `frontend/admin/tests/products.test.tsx` — 40 tests.

## 2. Approved endpoints and verified contract shapes

Verified against `backend/laravel/routes/api.php`, `ProductController`, `OperationalProductIndexRequest`, `ProductFormRequest`, and the resources.

| Operation | Endpoint | Verified shape |
| --- | --- | --- |
| Operational list | `GET /api/v1/admin/products` | `{data:[OperationalProduct], meta:{pagination:{current_page,per_page,total,last_page,has_next,has_previous}}}`, `private, no-store`. Ordered `created_at desc, id asc`; **no sort parameter**. Unknown query params → `422`. |
| Operational detail | `GET /api/v1/admin/products/{product}` | `OperationalProduct` resource (`prd_…` id or slug resolved by Laravel). |
| Create | `POST /api/v1/products` | `201` + `OperationalProduct`; `throttle:operational-write`; `permission:products.manage`. |
| Update | `PATCH /api/v1/products/{product}` | `200` + `OperationalProduct`; partial semantics. |

**`OperationalProduct`** = `ProductDetailResource` (`id, name, slug, product_type, price{amount,currency}, category{id,slug,name,description?}, primary_image, availability, stock_indicator, description, images[], variants[], created_at, updated_at`) **plus** `is_active`, `is_published`, `inventory` (`null` unless `inventory.view`). The list request is filtered; the parser requires `id/name/slug/product_type/price/category` and rejects the response otherwise.

## 3. Product list, pagination, search and filters

- `search` (≤100), `category`, `product_type` (`IN_STOCK`/`MADE_TO_ORDER`), `is_active`, `is_published`, `availability` (`available`/`unavailable`), `page`, `per_page` (≤100). These are the only documented parameters; no alias and no sort control exists.
- The filter control is a native `GET` form, so the list stays a Server Component, filter state lives in the URL, and no request is issued per keystroke — which removes the stale-response race by construction (no concurrent client fetches).
- Pagination is rendered from `meta.pagination` and its links preserve the active filters.
- Justification: the API itself rejects an unapproved parameter, and the UI never submits one. Unsupported sorting is omitted and recorded here.

## 4. Create/edit fields and exact validation behavior

Allow-listed request fields, verified against `ProductFormRequest` (an unknown key is rejected by Laravel):

| Field | Rule | Client mirror |
| --- | --- | --- |
| `name` | required, string, 1–200 | required, ≤200 |
| `slug` | required, `^[a-z0-9]+(?:-[a-z0-9]+)*$`, ≤255 | same pattern |
| `description` | optional, string, ≤5000 | ≤5000; omitted when empty on create |
| `product_type` | required, `IN_STOCK`/`MADE_TO_ORDER` | same enum |
| `price.amount` | required, integer ≥0 (minor units) | entered as major TZS, converted exactly |
| `price.currency` | required, `TZS` | fixed `TZS` |
| `category_id` | required, string ≤100 | must be a returned option id |
| `is_active` / `is_published` | optional, strict boolean | checkboxes |

- **Update is partial**: the form diffs against a hidden `original_values` snapshot and sends only changed fields. A no-op is rejected client-side ("Change at least one field before saving.") so a meaningless `PATCH` never reaches Laravel.
- **Errors**: `422`/`409` `errors[]` are mapped to controls (`price.amount`/`price.currency`/`price` → `price_amount`; `slug`, `is_published`, … pass through), the server's own message is preserved, and unmapped messages surface in the form-level alert. User input is preserved on failure.
- **Server-authoritative**: client validation is advisory; success is only declared after Laravel returns the resource, and the redirect uses the returned `id`.

## 5. Product type and state handling

- **Product type** `IN_STOCK` / `MADE_TO_ORDER` — shown as `In stock` / `Made to order`. `MADE_TO_ORDER` is a valid, selectable type; it is never disabled.
- **Product state** `is_active` / `is_published` — shown as labelled chips (`Active`, `Inactive`, `Published`, `Unpublished`) with a `…: unknown` neutral fallback; state is never inferred from a missing value.
- **Availability** is the API's derived value; **inventory** is a separate authorised domain (Phase 19.9) and is not displayed. The resource's `inventory` object is not read.
- State changes use only `is_active`/`is_published` on the approved update request. No activate/publish/archive endpoint is invented and no transition rule exists in TypeScript.
- The request-only guard is honoured by returning Laravel's own `422 BUSINESS_RULE_VIOLATION` on `is_published` rather than predicting it.

## 6. Variant detail behaviour and CAT-010 status

- Variants are read from the detail resource (`ProductDetailResource.variants` via `ProductVariantSummaryResource`): `id`, `sku`, `name`, `price{amount,currency}`, `availability`, `stock_indicator`. The table shows exactly those columns; nothing is derived from product text, images or type.
- When no variants are returned, the UI states "No variants were returned for this product by the current API." — it does not invent an empty operational state or a default variant.
- **CAT-010 is `501`.** `ProductController::storeVariant()` returns `notImplemented()`. No create/edit/delete variant control exists, and no variant request is ever sent. The detail view carries a plain statement of the dependency.

## 7. Image upload status

**Out of scope**, deferred to Phase 19.8. No multipart request, no storage path and no media URL is constructed. The detail page reports the number of image records the API returned (`N image record(s) returned by the API`) and states that preview/upload/ordering/deletion arrive in Phase 19.8.

## 8. Authorization and route-guard verification

- `/products`, `/products/new`, `/products/{product}` and `/products/{product}/edit` each call `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.products))` as their first operation and render `access.denial` when not granted.
- `product-actions.ts` repeats the same exact requirement (`products.manage` via the registry entry) at the top of both actions, because Server Functions are directly POST-reachable.
- The registry requirement is **`products.manage`** — the existing verified rule — and was confirmed to be a real `PermissionName` identifier by `tests/navigation.test.ts`.
- **Discrepancy recorded, not acted on:** the phase instructions state `products.view` is absent from the Laravel catalog, but `products.view` **does** exist in `backend/laravel/app/Support/PermissionName.php` and is held by both STAFF and ADMIN in `PermissionCatalog`. The instruction also directs keeping the existing verified requirement, so `products.manage` was kept. A future read-only view should revisit the requirement explicitly rather than widening a frontend rule.

## 9. Category-option source and limitation

- There is no operational category-list endpoint. Options come from the public `GET /api/v1/categories?per_page=100`, which returns only **active top-level** categories with encoded `cat_…` ids — ids that `CreateProduct`/`UpdateProduct` accept as `category_id`.
- Limitation: a category outside the first 100, or an inactive/nested one, cannot be selected through this endpoint. When a product's current category is absent from the returned page, the control injects the product's own server-returned category as an extra option so an edit never silently reassigns it. No category id or name is hardcoded and no second category catalog is created.
- Category CRUD is not implemented.

## 10. Loading, empty, error, forbidden and not-found behaviour

| Situation | Behaviour |
| --- | --- |
| Loading | Route-level `app/(protected)/loading.tsx` is unchanged; no artificial skeleton. |
| Empty list | `EmptyState` — distinct from a failure, with different copy when filters are active. |
| Unreadable response | `ProductFailureState` generic error; the list is never silently rendered as empty. |
| `401` | `SessionInvalidState` (recoverable sign-out, no redirect loop). |
| `403` | `AccessDeniedState`. |
| Service error / network | `ServiceUnavailableState`. |
| `429` | Distinct "Too many requests" state that names the `Retry-After` seconds. |
| Detail not found | Framework `notFound()` — a missing product is not a generic error. |
| Failed edit load | The form is not rendered; a failure state with a back link is shown, so a stale product can never be edited. |

## 11. Accessibility and responsive design

- One `h1` per page (`PageHeader`'s `page-title`), `h2` sections, tables with `<th scope="col">`. The shell keeps the single `main`.
- The state chips expose `aria-label="Status: …"` so colour is never the only signal. Long names/slugs wrap with `overflowWrap: "anywhere"`.
- Every form control has a persistent label; `TextField` `error` + `helperText` associate the message via `aria-describedby`; `required` marks required fields; the submission-level error is an `Alert`; the submit button is `disabled` while pending and carries `aria-busy`.
- Row actions read `View {product name}`; pagination buttons have visible text.
- Responsive: the filter grid reflows 1→2→3 columns and the table container scrolls horizontally inside its own `overflowX: auto`. No global `overflow-x: hidden`, no fixed widths, no new breakpoints. Verified in the production build; live viewport verification was not executed (§13).

## 12. Automated tests

`tests/products.test.tsx` — **40 tests, all passing**, covering: route-guard repetition and authorization-before-data-access on all four pages; nested routes covered by the existing matcher; server-action re-authorization; no token/transport on the client side; `no-store`/request-scoped transport; only approved endpoint paths and no sort/alias; variant mutation absent; filter vocabulary and exact request keys; unknown/invalid filter fallback; URL state preservation; resource parsing and rejection of unusable responses; unknown state kept but rendered neutrally; server pagination verbatim; category options from returned ids only; label/format mapping; minor-unit conversion; create payload field set; update diff and no-op rejection; `422` field mapping; `FormData` reading; rendering of table/detail/variant/filters/pagination/failure states; distinct list empty/error states; and no second `main`.

Updated suites: `tests/navigation.test.ts` and `tests/overview.test.tsx` (see §1). All pre-existing suites re-ran green.

## 13. Quality gates (from `frontend/admin`)

| Check | Result |
| --- | --- |
| `npm run lint` | PASS — 0 problems |
| `npm run typecheck` | PASS |
| `npm run test` | PASS — 117 tests, 0 fail (77 pre-existing + 40 new) |
| `npm run build` | PASS — `/products`, `/products/new`, `/products/[product]`, `/products/[product]/edit` all dynamic (`ƒ`); no harness route |
| `git diff --check` | PASS for every file this phase authored or modified (`git diff --check -- frontend` clean). The unfiltered command flags only the owner-provided `phases/group-S-phases.md` Markdown hard breaks. |

**Live verification performed (unauthenticated):** with the Laravel API on `127.0.0.1:8000` (200) and the admin app on `localhost:3001`, all four product routes (`/products`, `/products/new`, `/products/{id}`, `/products/{id}/edit`) return `307 → /sign-in` while signed out, confirming the proxy matcher covers the nested routes.

**Not executed — authenticated render.** No approved live ADMIN/STAFF Clerk credential was available for this session, and the instructions forbid creating a staff identity by hand or mutating real catalog data. The authenticated list/detail/create/edit render, real `422` responses and real pagination therefore remain unverified live. They are covered by the automated suites with mocks; this is reported as a limitation, not a pass.

## 14. Unresolved blockers, discrepancies and confirmations

**Contract/runtime discrepancies (reported, not worked around; frozen docs untouched):**

1. The frozen API inventory still labels the operational list/detail (`CAT-013`/`CAT-014`) as `PROPOSED`, yet the routes are registered and their runtime schema matches the documented resource. The documented schema was used; nothing was invented.
2. `products.view` exists in Laravel (§8), contradicting the phase note. `products.manage` was retained as instructed.

**Backend-dependent limitations:** variant creation (`501`), product media (Phase 19.8), operational category listing (none).

**Confirmations:**

- No fake product, price, state, variant or metric is rendered anywhere; every displayed value comes from the API response or is a neutral fallback.
- No variant-creation request is issued and no enabled variant-creation control exists.
- No new endpoint, permission, role, enum, request field, migration or dependency was added; the frozen API contract is untouched.
- No category CRUD, inventory, request, enquiry, order, checkout, payment, delivery, customer, staff, audit or notification workflow was implemented.
- No authenticated product request is cached across users; `no-store` and request-scoped tokens are preserved.
- No bearer token, raw `/me` payload or private authorization data crosses the Server/Client boundary.

## 15. Handoff to Phase 19.7 (Categories Management)

1. `/categories` remains a placeholder with `products.manage` (no separate category permission exists). Replace its page body in place and drop `plannedFor` from the registry entry; the Overview status line disappears with it.
2. Operational category endpoints do **not** exist: only the public `GET /categories` / `GET /categories/{category}` reads and the `products.manage`-gated `POST /categories` / `PATCH /categories/{category}` writes. Phase 19.7 should verify the write contract (`StoreCategoryRequest`/`UpdateCategoryRequest`) before building CRUD, and must not assume a read-only operational listing endpoint.
3. Reuse the established pattern: `lib/catalog/*` modules, `lib/api/server-request.ts`, server actions that repeat the route requirement, a Server Component list with URL state, and `components/states` for empty/error/forbidden. Add one test suite and register it in `package.json`.
4. Once an operational category-list contract exists, the product form's category options (and the first-100/top-level limitation in §9) should be migrated to it.
