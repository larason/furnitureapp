# Phase 19.7 — Categories Management

## Status

**PARTIAL** — the screen is implemented, truthful and production-shaped, but it does **not** manage the complete category collection.

### The scope answer (decisive for the verdict)

`/categories` manages **only the active top-level categories returned by the public `GET /api/v1/categories` endpoint** — the active direct children of the structural root category (`furnitures-root`). It is **not** the complete category collection.

Full category administration is materially blocked by the missing read surface: **there is no operational/admin category listing endpoint.** The seeded taxonomy is at least three levels deep (`furnitures-root → Living Room → Seating → Sofas`) and `is_active = false` is supported by the schema, so the root category, every inactive category and every deeper sub-category are invisible to the only listing this application may call. Phase 19.7 therefore delivers create + edit of *discoverable* categories as honest, scoped functionality, labels that scope on every affected surface, and no fabricated "all categories" capability. Because the capability list in the phase instructions cannot be fully satisfied, the phase is PARTIAL, not PASS.

The phase instruction was explicit: a PASS is only honest if the screen manages the complete collection. It does not, so this record does not claim one.

Nothing is staged or committed. The pre-existing uncommitted edits to `phases/group-S-phases.md` (owner's) are untouched; no `.gitignore`/`.fallowrc.json` change was made.

## 1. Files changed

**Modified**

- `frontend/admin/app/(protected)/categories/page.tsx` — placeholder replaced by the scoped operational list (scope notice, table, server pagination, distinct loading/empty/failure states).
- `frontend/admin/lib/navigation/admin-route-paths.ts` — added `ADMIN_CATEGORY_PATHS` (nested routes for the `/categories` destination, deliberately excluded from `ADMIN_PROTECTED_PATHS`/`ADMIN_PROTECTED_MATCHERS` because the `/categories(.*)` matcher already covers them).
- `frontend/admin/lib/navigation/admin-navigation.ts` — removed `placeholder`/`plannedFor` from the `categories` entry, corrected its description to match what the screen actually shows; URL and permission (`products.manage`) unchanged.
- `frontend/admin/components/layout/admin-overview-body.tsx` — the available-build note now names both implemented modules (Products and Categories).
- `frontend/admin/tests/overview.test.tsx` — updated the implemented-build copy assertion and lowered the placeholder/status count from `>= 8` to `>= 7` now that categories is implemented.
- `frontend/admin/tests/navigation.test.ts` — added the category access functions (`attemptCategoryRead(`, `fetchCategoryPage(`, `fetchCategory(`) to the "implemented route authorizes before data access" list.
- `frontend/admin/package.json` — `test` script includes `tests/categories.test.tsx`.
- `frontend/admin/README.md`, `frontend/admin/AGENTS.md` — Categories module architecture, contract, permission, scope statement and limitations.

**Created**

- `frontend/admin/app/(protected)/categories/new/page.tsx` — create page.
- `frontend/admin/app/(protected)/categories/[category]/page.tsx` — detail page.
- `frontend/admin/app/(protected)/categories/[category]/edit/page.tsx` — edit page.
- `frontend/admin/lib/catalog/category-resource.ts` — category resource types + defensive parsers (`CategoryResourceError`).
- `frontend/admin/lib/catalog/category-query.ts` — pagination vocabulary, request query and shareable list href.
- `frontend/admin/lib/catalog/category-presentation.ts` — safe http(s) image URL and timestamp formatting.
- `frontend/admin/lib/catalog/category-form.ts` — form values, create/update payload builders, API-error field mapping, advisory validation.
- `frontend/admin/lib/catalog/categories-api.ts` — server-only reads/writes + failure taxonomy.
- `frontend/admin/lib/catalog/category-actions.ts` — `"use server"` create/update actions.
- `frontend/admin/components/catalog/{category-table,category-detail-view,category-form,category-list-pagination,category-scope-notice,category-failure-state}.tsx`
- `frontend/admin/tests/categories.test.tsx` — 29 tests.
- `phases/phase-19.7-completion.md` — this record.

## 2. Approved endpoints and verified contract shapes

Verified against `backend/laravel/routes/api.php`, `CategoryController`, `CategoryFormRequest`/`StoreCategoryRequest`/`UpdateCategoryRequest`, the `Services/Categories/*` actions and the frozen `docs/api/*`.

| Operation | Endpoint | Auth | Verified shape |
| --- | --- | --- | --- |
| List | `GET /api/v1/categories` | **public** | Active **direct children of `furnitures-root` only**, ordered; `{data:[{id,name,slug,image}], meta:{pagination:{…}}}`; `page`, `per_page` (≤100). |
| Detail | `GET /api/v1/categories/{category}` | **public** | Resolves by encoded `cat_…` id **or** slug; only `is_active = true` and non-root; `{data:{id,name,slug,description,image,created_at}}`. |
| Create | `POST /api/v1/categories` | `clerk.auth` + `operational` + `permission:products.manage` | `201`; allow-list `name` (required, ≤120), `slug` (required, kebab-case, ≤255), `description` (nullable, ≤1000), `image` (nullable, valid http(s) URL, ≤255). Forces `parent = root`, `is_active = true`. Slug collision → `409`. |
| Update | `PATCH /api/v1/categories/{category}` | same | `200`; same fields, all optional (partial). Blocks the root category (`403`). Resolves by id or slug. |

There is **no `GET /admin/categories`** and no `categories.*` permission. The write permission is `products.manage`, reused exactly as verified — no permission was invented.

## 3. List, pagination and scope labelling

- The list calls the public collection with only `page`/`per_page`; there is no search, sort, state or parent filter in the contract, so none is offered and none is inferred. Pagination is rendered verbatim from `meta.pagination` and its links preserve the page size.
- `CategoryScopeNotice` states, in plain language, that the screen lists the active top-level categories published to the storefront, that the root category / inactive categories / deeper sub-categories are not returned and cannot be viewed or managed here, and that creating always adds a new active top-level category. It renders on the list and create surfaces (the detail view carries its own equivalent note).
- The empty state explicitly says an empty result is **not** proof that the catalog has no categories, so a partial listing can never be mistaken for "the catalog is empty".

## 4. Create/edit fields and exact validation behavior

- Form fields map 1:1 to the request keys `name`, `slug`, `description`, `image`. `name` and `slug` are required and trimmed; `description` and `image` are optional. An empty optional on create is omitted; on update, clearing a nullable field submits `null` (not an empty string).
- Partial update: only changed fields are sent; a no-op update is rejected before any request with "Change at least one field before saving." (avoids a pointless request and an empty PATCH body).
- Advisory client validation mirrors the backend (name ≤120, kebab-case slug ≤255, description ≤1000, image a full http(s) URL ≤255) but never replaces Laravel: real `422`/`409` responses are mapped back onto the exact offending control, and any unmapped message is surfaced as a form-level error.
- No `parent_id`, `is_active`, `display_order` or delete control exists; those fields are not in the request contract, so the form cannot send them and states as much.

## 5. Authorization and route-guard verification

- All four pages call `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.categories))` as their first operation and return `access.denial` when not granted; on the nested pages authorization precedes `await params`/data access.
- `lib/catalog/category-actions.ts` holds the `"use server"` actions; both repeat the exact `products.manage` requirement first, because Server Functions are directly POST-reachable.
- The registry entry uses only the verified permission. Navigation visibility is presentation only; the server re-checks.

## 6. Data flow, transport and caching

- Reads go through `requestPublicApi` (the collection/detail are public); writes go through `requestOperationalApi` (bearer, `no-store`). Both use the shared `lib/api/server-request.ts` + `lib/api/client.ts`; no second client, no shared cache, no token in a Client Component prop.
- `category-resource.ts` parses defensively: a response missing a required display field or the pagination envelope raises `CategoryResourceError`, and the page renders a distinct failure rather than an empty list.

## 7. Failure behaviour

| Situation | Behaviour |
| --- | --- |
| Loading | Route-level `app/(protected)/loading.tsx` unchanged; no artificial skeleton. |
| Empty list | `EmptyState`; copy explicitly denies it means "no categories exist". |
| Unreadable response | `CategoryFailureState` generic error; never silently empty. |
| `401` | `SessionInvalidState` (recoverable sign-out, no redirect loop). |
| `403` | `AccessDeniedState`. |
| Service error / network | `ServiceUnavailableState`. |
| `429` | Distinct "Too many requests" state naming the `Retry-After` seconds. |
| Detail/edit not found | Framework `notFound()` — a missing category is not a generic error, and the edit form never renders against a failed load. |

## 8. Accessibility and responsive behaviour

- One `h1` per page (`PageHeader` `page-title`), `h2` sections via `SectionHeader`, a table with `<th scope="col">`. The shell keeps the single `main`; no page declares another.
- Row actions read `View {name}` / `Edit {name}`; pagination controls have visible labels and are `disabled` (not hidden) at the boundaries.
- Every form control has a persistent label; `error` + `helperText` associate messages via `aria-describedby`; `required` marks required fields; the form-level error is an `Alert`; the submit button is `disabled` and `aria-busy` while pending.
- Every control is controlled (`value={draft.…}`, no `defaultValue`), so React cannot silently reset an operator's edits when a `422` re-renders the form — the regression class already fixed for products in 19.6.
- Responsive: content reflows via MUI breakpoints; the table container scrolls inside its own `overflowX: auto`; no global `overflow-x: hidden`, no fixed widths, no new breakpoints or tokens.

## 9. Automated tests

`tests/categories.test.tsx` — **29 tests, all passing**, covering: route-guard repetition and authorization-before-data-access on all four pages; nested-route matcher coverage and path helpers; server-action re-authorization order; no token/transport on the client side; `no-store`/request-scoped transport; only approved endpoint paths and no search/sort; public reads justified by the absent operational listing; pagination vocabulary and URL state; resource parsing and rejection of unusable responses; accurate scope wording; create payload field set and omission of empty optionals; advisory validation; update diff, `null` clearing and no-op rejection; `422` field mapping; `FormData` and resource-to-form mapping; table/detail/pagination/failure rendering; unsafe image schemes never linked; shared `DataTable` reuse; distinct loading/empty/failure states; controlled-form preservation; and no unsupported delete/parent/state mutation.

Updated suites: `tests/navigation.test.ts`, `tests/overview.test.tsx` (see §1). All pre-existing suites re-ran green.

## 10. Quality gates (from `frontend/admin`)

| Check | Result |
| --- | --- |
| `npm run lint` | PASS — 0 problems |
| `npm run typecheck` | PASS |
| `npm run test` | PASS — 148 tests, 0 fail (119 pre-existing + 29 new) |
| `npm run build` | PASS — `/categories`, `/categories/new`, `/categories/[category]`, `/categories/[category]/edit` all dynamic (`ƒ`) |
| `git diff --check` | PASS for every file this phase authored or modified (`git diff --check -- frontend` clean). The unfiltered command flags only the owner-provided `phases/group-S-phases.md` Markdown hard breaks. |

**Not executed — authenticated render.** As in 19.6, no approved live ADMIN/STAFF Clerk credential was available and creating a staff identity or mutating real catalog data is forbidden, so the authenticated list/detail/create/edit render against the running API remains unverified live. It is covered by the automated suites with mocks; reported as a limitation, not a pass.

## 11. Unresolved blockers, discrepancies and confirmations

**Blocking capability gap (the reason for PARTIAL):**

1. No operational category listing endpoint exists. The complete collection (root, inactive, nested) cannot be listed, therefore cannot be administered. Reported, not worked around.

**Contract/doc discrepancies (reported; frozen docs untouched):**

2. The frozen `/categories` GET enumerates no query parameters, yet `page`/`per_page` are the approved global collection pagination vocabulary and are runtime-validated. Phase 19.6 already consumes `?per_page=100` from the same endpoint; this phase consumes `page`/`per_page` the same way. The doc gap is recorded here rather than "fixed" in a frozen file.

**Confirmations:**

- The screen is labelled as partial everywhere it matters; no surface claims to manage all categories.
- No fake category, count, hierarchy or state is rendered; every displayed value comes from the API response or is a neutral fallback.
- No new endpoint, permission, role, enum, request field, migration or dependency was added; the frozen API contract and database are untouched.
- No category delete, hierarchy change, activation toggle, reordering or media upload is offered or sent.
- No bearer token, raw `/me` payload or private authorization data crosses the Server/Client boundary; authenticated calls remain `no-store`.

## 12. Handoff to Phase 19.8

1. **Unblock full category administration first, if it is wanted:** add a Laravel operational category-list endpoint (root + inactive + nested, with a documented shape). Until then `/categories` stays scoped as above and the README/AGENTS scope statements must remain in place.
2. Once that listing exists, extend the module with parent selection, active/inactive state, ordering and safe deletion, and migrate the product form's category options (and the first-100/top-level limitation recorded in the 19.6 record) to it.
3. Category media upload belongs to Phase 19.8; the current screen shows the stored `image` URL only and must not grow an upload control before then.
