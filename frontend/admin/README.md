# SL Furnitures Admin Dashboard

Independent Next.js 16 + React + TypeScript + MUI operational dashboard for SL Furnitures. The application is intentionally separate from `frontend/web` and is request-only until later phases enable additional business workflows.

## Local setup

```bash
cp .env.example .env.local
npm install
npm run dev
```

Open `http://localhost:3001`. The port is explicit so the admin and customer applications cannot accidentally share an origin. Without `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY`, the application renders a safe configuration state and never assumes an authenticated administrator.

Set `CATALOG_MEDIA_BASE_URL` to the HTTPS origin Laravel builds product image URLs from — the same value as the backend's `R2_PUBLIC_BASE_URL`. It is the only origin allow-listed for image previews; when it is missing or invalid, previews are disabled and the product record is unaffected. `next.config.ts` compiles the value into the `next/image` allow-list **when the server starts**, so set it before starting the server and **restart after changing it**: editing `.env.local` under a running server reloads the server's environment but not the compiled allow-list, which leaves every preview reporting that the image is unreachable even though the stored record and URL are valid.

## Scripts

| Script | Purpose |
| --- | --- |
| `npm run dev` | Start the admin app on port 3001 |
| `npm run build` | Create the production build |
| `npm run start` | Start the production build on port 3001 |
| `npm run lint` | Run Next.js ESLint rules |
| `npm run typecheck` | Run strict TypeScript checking |
| `npm run test` | Run transport, admission, navigation, route-guard, shell, primitive, overview, product, product-media and category tests |

## Authentication and admission

- `app/(auth)/sign-in` provides Clerk's existing sign-in flow; it is not an admin registration route. `withSignUp={false}` and `transferable={false}` disable the sign-up flow, so an unregistered email or OAuth identity dead-ends instead of entering registration.
- `app/(protected)` is a dynamic server-side admission boundary. It resolves the current Clerk session, obtains a request-scoped bearer token, and calls Laravel `GET /api/v1/me` before rendering protected content.
- `lib/auth/server-operational-identity.ts` performs that resolution once per request (React `cache()`), so the protected layout and the page's own guard share one admission decision.
- `lib/auth/operational-identity.ts` validates the narrow Laravel identity response and fails closed on missing, malformed, inactive, CUSTOMER, or unauthorized identities. A Laravel `401` under a still-active Clerk session yields a recoverable **Session not accepted** state with a sign-out action rather than a redirect to `/sign-in`.
- `proxy.ts` performs an early signed-out redirect for every protected path. It decides nothing about roles or permissions: the protected layout remains the authorization boundary.

Clerk authenticates users only. Laravel remains authoritative for local users, roles, account state, effective permissions and all business rules. A protected route requires a current Clerk session plus Laravel-confirmed `ACTIVE` `STAFF` or `ADMIN` identity. CUSTOMER, suspended, missing, malformed and ambiguous identities are denied.

No bearer token is stored, logged, passed to client components, or shared between requests.

## Dashboard shell

The shell is the shared frame that every protected operational screen renders inside.

| Component | Responsibility |
| --- | --- |
| `app/(protected)/layout.tsx` | Admission boundary; renders the shell only for an admitted identity, otherwise the four denial states. |
| `components/layout/admin-shell.tsx` | Client entry point of the frame: resolves the sanitized identity from context, filters navigation, computes the active destination, owns the mobile drawer state, renders the skip link, sidebar, drawer, topbar and the single `<main id="admin-main-content">`. |
| `components/layout/admin-sidebar.tsx` | Persistent desktop navigation (`md` and above). |
| `components/layout/admin-mobile-drawer.tsx` | MUI modal drawer for narrow screens, with a labelled close control and dismissal on selection. |
| `components/layout/admin-topbar.tsx` | Menu trigger, page context (breadcrumbs on `md`+, page title below) and the injected account control. |
| `components/layout/admin-breadcrumbs.tsx` | Current page context inside a labelled `nav`. |
| `components/layout/admin-user-menu.tsx` | Sanitized identity plus the single sign-out control (Clerk `signOut` → `/sign-in`). |
| `components/layout/admin-navigation-list.tsx` | Presentational, permission-filtered navigation list with `aria-current` on the active destination. |
| `components/layout/admin-route-placeholder.tsx` + `components/ui/feature-placeholder.tsx` | Explicit "not yet implemented" page body; loads no data and performs no operations. |
| `components/layout/admin-overview-body.tsx` | Overview landing content: access summary, permission-filtered shortcut grid with per-destination roadmap status, and the not-yet-available list. |
| `components/layout/shell-layout.ts` | Shell identifiers and token-derived geometry (`ADMIN_SIDEBAR_WIDTH`, drawer width, `main` id). |

Only the shell entry point uses hooks. Sub-components are presentational so they can be rendered and asserted directly in `node:test`.

## Navigation configuration

`lib/navigation/admin-route-paths.ts` owns the route paths and the proxy matcher patterns; `lib/navigation/admin-navigation.ts` owns the single typed route registry: identifier, path, label, description, icon, group, exact permission and any approved role restriction. Per-group labels are `Workspace`, `Operations`, `Catalog` and `Administration`.

Navigation is always produced by `resolveAdminNavigation(identity)`, which filters the registry against the Laravel-resolved effective permissions. An `ADMIN` role never reveals a link for a permission the identity does not hold, and missing permission data fails closed.

Each entry also carries `plannedFor`, the roadmap phase that owns the destination's business workflow while it remains a placeholder. It is the single source for a phase number: `adminRouteStatus()` renders the Overview wording (`Not yet implemented — {phase}`), and `AdminRoutePlaceholder` reads the same field, so pages never hardcode one. `resolveOverviewNavigation(identity)` derives the Overview's shortcut groups by removing the Overview's own entry from the filtered registry.

### Verified permission mapping

| Route | Required permission | Role restriction | Backend contract |
| --- | --- | --- | --- |
| `/` (Overview) | none (admission only) | — | `GET /api/v1/me` |
| `/requests` | `requests.view` | — | REQ-004/005 |
| `/enquiries` | `enquiries.view` | — | ENQ-004/005 |
| `/notifications` | `notifications.read_operational` | — | NOT-001/002 |
| `/products` | `products.manage` | — | CAT-007/008/013/014 |
| `/categories` | `products.manage` | — | CAT-011/012 (no separate category permission exists) |
| `/inventory` | `inventory.view` | — | INV-001/002 |
| `/customers` | `users.manage_authorized` | `ADMIN` | ADM-008/009 |
| `/audit-logs` | `audit.view` | `ADMIN` | ADM-007 |

Every identifier above is an existing `backend/laravel/app/Support/PermissionName.php` value; `lib/auth/permission-names.ts` only centralizes the strings the shell references and grants nothing. Role restrictions mirror Laravel's `admin` middleware for those paths.

**Staff management is deliberately absent.** `ADM-001`…`ADM-006` are routed but return `501`, and Phase 19.13 of the Group S roadmap gates the screen on backend completion. No navigation entry is published for an unimplemented endpoint.

Cart, checkout, payment, order and delivery administration are deferred while the release is request-only and must not be added to the navigation.

## Overview page

`app/(protected)/page.tsx` is a navigation-first landing page, not an analytics dashboard. Its content hierarchy is:

1. `Welcome back, {operationalDisplayName(identity)}` (approved helper and fallback) plus one sentence describing the workspace.
2. **Your access** — the sanitized name, email and local role label, and one sentence stating that Laravel resolves the identity per request while each protected page repeats its own requirement.
3. **Operational areas** — the permission-filtered shortcut grid produced by `resolveOverviewNavigation(identity)`, grouped as Operations, Catalog and Administration. Each destination is a whole-card link (`Card` rendered through `next/link`) whose accessible name is its label, and shows a restrained text status `Not yet implemented — {phase}` while its workflow is a placeholder. The Overview never links to itself.
4. **Not yet available** — the areas that have no navigation entry (staff management, blocked on the backend endpoints; account and settings; deferred commerce administration), and the statement that no operational summary is displayed because this release has no approved reporting contract.

The page makes zero business-data API calls beyond the admission flow's `GET /api/v1/me`, renders no counts, totals, charts, timelines or sample records, and passes only the sanitized identity across the Server/Client Component boundary.

## Products module (Phase 19.6)

`/products` is the first implemented operational workflow. It is a real Laravel-backed module, not a placeholder, and it exposes only operations the backend actually supports.

### Routes

| Route | Page | Purpose |
| --- | --- | --- |
| `/products` | `app/(protected)/products/page.tsx` | Operational list: filters, table, server pagination. |
| `/products/new` | `app/(protected)/products/new/page.tsx` | Create form. |
| `/products/[product]` | `app/(protected)/products/[product]/page.tsx` | Read-only authoritative detail. |
| `/products/[product]/edit` | `app/(protected)/products/[product]/edit/page.tsx` | Edit form. |

`ADMIN_PRODUCT_PATHS` in `lib/navigation/admin-route-paths.ts` owns the nested paths. They are deliberately excluded from `ADMIN_PROTECTED_PATHS`/`ADMIN_PROTECTED_MATCHERS` and from the navigation registry: the `/products` entry is the only destination, and its derived `/products(.*)` matcher already covers the nested routes. Every product page repeats `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.products))` as its first operation.

### Contract and permission

- List: `GET /api/v1/admin/products` — `search` (≤100), `category`, `product_type`, `is_active`, `is_published`, `availability`, `page`, `per_page` (≤100). Unknown query parameters are rejected by Laravel (`422`). There is **no sort parameter**; the API orders by `created_at` descending.
- Detail: `GET /api/v1/admin/products/{product}` (encoded `prd_…` id or slug).
- Create: `POST /api/v1/products`; update: `PATCH /api/v1/products/{product}`.
- Allow-listed write fields only: `name`, `slug`, `description`, `product_type`, `price{amount,currency}`, `category_id`, `is_active`, `is_published`. Any other field is rejected. Money is integer minor units.
- The permission is `products.manage` (the registry's verified requirement). The phase instructions state `products.view` does not exist; in fact `products.view` **is** present in `PermissionName` and held by STAFF/ADMIN, but it is not used here — kept on the approved existing rule.

### Data flow

- `lib/api/server-request.ts` obtains the Clerk session token per request and forwards it only as a bearer header; `lib/catalog/products-api.ts` performs every read/write through `lib/api/client.ts`, so authenticated requests are `no-store`.
- `lib/catalog/product-actions.ts` holds the `"use server"` create/update actions. Server Functions are directly POST-reachable, so both repeat the exact permission before touching product data.
- `lib/catalog/product-resource.ts` parses the API response defensively; `lib/catalog/product-presentation.ts` maps type/state/availability/stock to labels with a neutral fallback for unknown values; `lib/catalog/product-query.ts` owns the filter vocabulary, URL state and request query; `lib/catalog/product-form.ts` maps the form to the contract (partial updates send only changed fields).
- The list filter is a native `GET` form, so filter state lives in the URL, the list stays a Server Component, and no request is issued per keystroke. Components that build table/column `render` functions or hold form state are Client Components.

### Honest limitations

- **Variants are read-only.** The detail resource returns `id`, `sku`, `name`, `price`, `availability` and `stock_indicator`. Variant creation is unavailable because `POST /api/v1/products/{product}/variants` returns `501`; no create control is rendered and no request is made.
- **Product media upload and preview arrive in Phase 19.8A** (see below); ordering, alt-text editing, setting the primary image and deletion remain unimplemented **in this application**. The backend endpoints now exist (Phase 19.8B: CAT-015–CAT-018); wiring them into the admin gallery is the next media phase.
- **Category options come from the public `GET /api/v1/categories?per_page=100`**, which exposes only active top-level categories. There is no operational category-list endpoint and no separate `categories.*` permission. If the first 100 do not cover the catalog, the form is limited to those options; the current selection is always preserved.

## Product media upload and preview (Phase 19.8A)

Phase 19.8A adds product image upload and preview to the product detail page. It is **not** full product media management: deletion, ordering, alt-text editing and setting the primary image remain unimplemented and are not offered.

### Contract and permission

- Upload: `POST /api/v1/products/{product}/images` (CAT-009), gated by `products.manage`. The request is `multipart/form-data` carrying exactly one file field, `image`. Laravel rejects any non-file field and any file field other than `image`.
- Laravel accepts JPEG, PNG and WebP up to the configured `product_images.max_bytes` (5 MiB by default) and rejects embedded GPS metadata. An oversized body is `413 REQUEST_TOO_LARGE`; an invalid file is `422`; both are mapped to distinct messages.
- Images are read from the operational product resource: `{ id, url, alt_text, sort_order, is_primary }`. Ordering, alt text and primary status are displayed as authoritative values, never edited.

### Data flow

- `lib/api/server-request.ts` exposes `requestOperationalUpload`, an explicit multipart path over the same transport. `FormData` is forwarded unchanged, so the runtime sets the boundary and no JSON serializer touches the file bytes. Only the `image` field is forwarded.
- `lib/catalog/products-api.ts` adds `submitProductImage`; `lib/catalog/product-image-actions.ts` holds the `"use server"` upload action, which repeats the exact `products.manage` requirement before reading the file and revalidates the detail path on success.
- `components/catalog/product-image-gallery.tsx` (Server Component) renders the returned records; `components/catalog/product-image-preview.tsx` is the only Client Component in the media section, containing a load-failure fallback and a render boundary so an unreachable or rejected image can never break the detail page. `components/catalog/product-image-uploader.tsx` is the Client Component form; it disables its submit control while a request is in flight.
- `lib/catalog/media-origin.ts` resolves and validates the configured media origin. A malformed, insecure or credential-bearing value resolves to nothing, in which case the image allow-list is empty and previews render an explicit unavailable state instead of an arbitrary host.

### Effective size limits

| Limit | Value | Source |
| --- | --- | --- |
| Laravel product image limit | 5 MiB (`product_images.max_bytes`) | `backend/laravel/config/product_images.php` |
| PHP `upload_max_filesize` / `post_max_size` | 30 MiB | local PHP configuration |
| Next.js Server Action body | 6 MB | `serverActions.bodySizeLimit` in `next.config.ts` |

Next.js caps Server Action bodies at 1 MB by default, below the backend's own limit, so `next.config.ts` raises it to accept the backend limit plus multipart overhead. Laravel remains the authority: an image the backend rejects is still rejected. This value was chosen locally and the PHP and reverse-proxy limits must be re-verified per environment.

### Honest limitations

- **No mutation of existing images yet.** The endpoints now exist (Phase 19.8B: CAT-015–CAT-018 — delete, alt text, primary, reorder), but this application renders no control for them yet; wiring them is the next media phase.
- **Uploads can create duplicates.** Laravel accepts repeated uploads; nothing here deduplicates.
- **Live verification is pending.** The owner performs the authenticated smoke test; the automated coverage is transport, authorization and rendering assertions plus the pure origin/allow-list logic.
- **Category media upload is still deferred**; categories take an `image` URL string, so no category upload is added here.

## Categories module (Phase 19.7)

`/categories` is the second implemented operational workflow. It is a real Laravel-backed module, not a placeholder — but it is **not** full category administration: the complete scope statement is below.

### Routes

| Route | Page | Purpose |
| --- | --- | --- |
| `/categories` | `app/(protected)/categories/page.tsx` | List of the categories the API publishes, plus the scope notice. |
| `/categories/new` | `app/(protected)/categories/new/page.tsx` | Create form for a new top-level category. |
| `/categories/[category]` | `app/(protected)/categories/[category]/page.tsx` | Read-only authoritative detail. |
| `/categories/[category]/edit` | `app/(protected)/categories/[category]/edit/page.tsx` | Edit form. |

`ADMIN_CATEGORY_PATHS` in `lib/navigation/admin-route-paths.ts` owns the nested paths. Like the product paths, they are deliberately excluded from `ADMIN_PROTECTED_PATHS`/`ADMIN_PROTECTED_MATCHERS` and from the navigation registry: the `/categories` entry is the only destination, and its derived `/categories(.*)` matcher already covers the nested routes. Every category page repeats `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.categories))` as its first operation.

### Contract and permission

- List: `GET /api/v1/categories` — the **public** collection (`page`, `per_page` ≤100). No search, sort or state filter exists in the contract.
- Detail: `GET /api/v1/categories/{category}` — the **public** detail, resolved by encoded id or slug.
- Create: `POST /api/v1/categories`; update: `PATCH /api/v1/categories/{category}`.
- Allow-listed write fields only: `name` (≤120), `slug` (kebab-case, ≤255), `description` (nullable, ≤1000), `image` (nullable http(s) URL, ≤255). Any other field is rejected. A create always forces `parent = root` and `is_active = true`; the root category itself cannot be updated (`403`) and a slug collision is reported as `409`.
- The permission is `products.manage` — the same verified requirement already used by the Products module. There is no `categories.*` permission and none is invented.

### Data flow

- `lib/catalog/categories-api.ts` performs every read through the public catalog endpoints and every write through the authenticated transport, so authenticated requests are `no-store` and request-scoped.
- `lib/catalog/category-actions.ts` holds the `"use server"` create/update actions. Server Functions are directly POST-reachable, so both repeat the exact permission before touching category data.
- `lib/catalog/category-resource.ts` parses the API response defensively; `lib/catalog/category-query.ts` owns the pagination vocabulary, URL state and request query; `lib/catalog/category-form.ts` maps the form to the contract (partial updates send only changed fields, and a no-op update is never sent); `lib/catalog/category-presentation.ts` handles the safe image link and timestamp formatting.
- Every rendered failure class stays distinct (`CategoryFailureState`), and a missing category resolves through the framework `notFound()` rather than a generic error.

### Honest limitations (scope)

- **The screen manages only the active top-level categories returned by the public `GET /api/v1/categories`** — the categories directly beneath the structural root. It does **not** manage the complete category collection.
- The listing cannot show the root category, inactive (`is_active = false`) categories, or deeper sub-categories: no operational/admin category listing endpoint exists, so those are invisible to every listing available to this app.
- Hierarchy and active/inactive state are not part of the approved category request, so no parent, state or ordering control is rendered and no such field is ever sent.
- **Category media upload is deferred to Phase 19.8.** The form and detail view show the stored image URL only. (Phase 19.8A added product image upload; category images remain a URL string with no upload.)
- The screen states this scope on every list/create surface (`CategoryScopeNotice`), and its empty state explicitly says an empty result is not proof that the catalog has no categories.

## Adding a navigation entry

1. Add the path to `lib/navigation/admin-route-paths.ts` (this also covers the proxy's early redirect).
2. Add the entry to `ADMIN_ROUTES` in `lib/navigation/admin-navigation.ts` with an existing Laravel permission, only if the backend enforces one an approved role restriction, and the roadmap phase that will implement the destination (`plannedFor`) while it is a placeholder.
3. Create `app/(protected)/<path>/page.tsx` that calls `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.<key>))`, returns `access.denial` when not granted, and otherwise renders through `PageContainer`/`PageHeader`.
4. Add the route to the enabled list in `package.json`'s `test` script only if a new test file is added, and extend the completion record.

Do not add a route whose backend endpoint is unimplemented or whose permission is not in the Laravel catalog. Omit it and document the dependency instead.

## Implementing a protected page

```tsx
const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.requests);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function RequestsPage() {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;
  return <PageContainer aria-labelledby="page-title"><PageHeader title={ROUTE.label} description={ROUTE.description} /></PageContainer>;
}
```

- Hiding a navigation link is not authorization. Every protected page repeats its own exact requirement server-side.
- `lib/auth/route-guard.ts` holds the pure decision (`granted`, `forbidden`, `signed-out`, `session-invalid`, `unavailable`, `account-denied`); `lib/auth/server-route-guard.tsx` maps it to the existing denial states.
- The shell owns the only `<main>` landmark; pages must not declare another.
- Placeholders state the phase that will implement them and must not retrieve data or render fabricated records. A placeholder page renders `<AdminRoutePlaceholder route={ROUTE} />`; its phase comes from the registry's `plannedFor`, never from a page-local string.

## Design and accessibility rules

`frontend/design-system/tokens.css` is the canonical token authority. `design-tokens.json` is its synchronized build-time representation; this application does not copy or redefine tokens. The MUI theme supplies the runtime variables that semantic token references such as `var(--surface-canvas)` depend on. Shell geometry derives from the spacing scale (`ADMIN_SIDEBAR_WIDTH` is `--space-9 × 4`); no ad-hoc widths, colors or shadows are introduced. Use MUI and `@mui/icons-material`; do not add another component or token system.

Shell accessibility expectations: a keyboard skip link to `#admin-main-content`, one `main` landmark, one labelled `nav` per navigation group, `aria-current="page"` on the active destination (never colour alone), named icon-only controls, visible `--focus-ring` focus, MUI modal drawer focus handling and Escape dismissal, responsive reflow without horizontal overflow, and reduced-motion support (`app/globals.css`). Loading, empty, error and permission-safe states reuse `components/states` and `components/auth/admission-state`.

## Phase exclusions

Products (Phase 19.6), Categories (Phase 19.7) and product image upload/preview (Phase 19.8A) are implemented. The remaining operational destinations — requests, enquiries, notifications, inventory, customers and audit logs — are still explicit, permission-protected placeholders and load no data. This phase does not implement inventory adjustment, request, enquiry, notification, customer, staff, audit, order, checkout, payment or delivery workflows; it adds no product image deletion, ordering, alt-text editing or primary-image selection; and it does not add category hierarchy, category activation/deactivation, category deletion or category media management. It adds no dashboard metric endpoint, chart, Laravel route, migration, role or permission.
