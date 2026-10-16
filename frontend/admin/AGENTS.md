# Admin Frontend Rules

- This is an independent Next.js application. Do not add admin routes to `frontend/web`.
- Use Next.js 16 App Router conventions and keep server components as the default.
- `frontend/design-system/tokens.css` is the sole token authority. Use the synchronized JSON only for deterministic MUI mapping; never create a competing token file.
- The admin may use denser operational composition than the storefront, but semantic colors, typography, spacing, breakpoints, focus treatment and accessibility behavior remain shared.
- Laravel is authoritative for roles, active account state, exact permissions, ownership and business state. Never trust Clerk metadata or a client `isAdmin` flag.
- Clerk authentication is not admin authorization. Protected routes must resolve `/api/v1/me` server-side and admit only `ACTIVE` local `STAFF`/`ADMIN` identities with a valid, deterministic effective-permission list. CUSTOMER, inactive, missing, malformed and ambiguous identities fail closed.
- The `permissions` array is a presentation hint from Laravel only. Never construct a frontend permission catalog, grant a permission locally, or bypass Laravel authorization.
- Use the request-scoped API transport in `lib/api/client.ts`; never persist, log, serialize or globally retain bearer tokens.
- The admin sign-in surface is sign-in only: keep Clerk's registration footer hidden, `signUpUrl` pinned, and `withSignUp={false}` with `transferable={false}` on `<SignIn />`. Hiding a link is not enough; an unregistered email or OAuth identity must dead-end with "couldn't find your account" rather than entering registration. Admin accounts are provisioned by the operator `admin:bootstrap` command, never by a browser flow.
- Every state that can be shown to a possibly-authenticated visitor — denied, account unavailable, session not accepted, and service unavailable — must offer a Clerk sign-out action. Never strand a signed-in user, including during a Laravel outage.
- Never redirect a still-authenticated Clerk session to `/sign-in`. `SignIn` cannot render for an already signed-in user, so a Laravel `401` under a live session must surface a recoverable state that offers a Clerk sign-out instead of a redirect loop.
- Keep private API calls `no-store`; preserve the frozen `/api/v1` envelopes, pagination and error vocabulary.
- Use MUI and `@mui/icons-material`; do not add Tailwind, another component library, or another authentication provider.

## Shell and navigation

- `app/(protected)/layout.tsx` is the only admission boundary. `proxy.ts` may pre-empt signed-out access for early UX only; it must never decide roles, permissions or business authorization, and must keep consuming `ADMIN_PROTECTED_MATCHERS`.
- The shell owns the single `<main id="admin-main-content">` landmark and the skip link. Pages must not declare another `main`.
- Define routes and navigation only in `lib/navigation/admin-route-paths.ts` and `lib/navigation/admin-navigation.ts`. Never hardcode a navigation link, route path or breadcrumb inside a component, and never duplicate the registry per surface.
- Filter navigation with `resolveAdminNavigation(identity)` from the Laravel-resolved permissions. Navigation visibility is presentation, never authorization; an `ADMIN` role alone must not reveal a link for a permission the identity lacks, and missing permission data fails closed.
- Every protected page repeats its own exact requirement server-side through `requireAdminRouteAccess(adminRoute(...))` and renders `access.denial` when not granted. A client-only gate is never sufficient.
- Only use permission identifiers that exist in `backend/laravel/app/Support/PermissionName.php`, and only add an approved role restriction where Laravel enforces one. Never invent a permission or broaden one in the frontend.
- Do not publish a navigation entry whose backend endpoint is unimplemented; omit it and document the dependency. Deferred commerce administration (orders, payments, checkout, delivery) must never appear in the navigation.
- The registry's `plannedFor` is the single source for a placeholder's roadmap phase. Placeholder pages must not hardcode a phase string, and any status the Overview displays must name a phase that exists in the approved roadmap.
- The Overview is navigation-first: never add counts, totals, charts, timelines or sample records, never fetch a business endpoint to populate it, and never show a status that could be mistaken for real operational state.
- Placeholders must state that the feature is not implemented, name the owning phase, load no data and render no fabricated records, counts, charts or metrics. The overview is a navigational landing page, not an analytics dashboard.
- Keep shell sub-components presentational and hook-free where practical; put routing, drawer and identity hooks only in the shell entry point so the shell stays render-testable.
- A component that passes a component reference (for example MUI `component={NextLink}`) must declare `"use client"`. A Server Component cannot serialize a function into a Client Component prop, which fails at runtime with "Functions cannot be passed directly to Client Components". `tests/server-boundary.test.ts` enforces this for every `.tsx` under `app/` and `components/`; keep it passing rather than relying on review.
- New or changed navigation, route protection or placeholder behavior requires corresponding tests: permission mapping against the Laravel catalog, active-route matching, denial behavior and rendered placeholder content.

## Product module

- Expose only operations Laravel actually supports. The approved product contract is `GET /admin/products`, `GET /admin/products/{product}`, `POST /products` and `PATCH /products/{product}`; the write allow-list is `name`, `slug`, `description`, `product_type`, `price`, `category_id`, `is_active`, `is_published`. Never add a field, enum, filter, sort or endpoint the contract does not define.
- Always read the operational resource (`/admin/products…`), never the public catalog resource, for staff views. Never reconstruct a product, price, state or variant from a partial response — an unreadable response is an error, not an empty list.
- Keep product type (`IN_STOCK`/`MADE_TO_ORDER`), product state (`is_active`/`is_published`), availability and inventory distinct. `MADE_TO_ORDER` is a valid product type, never invalid because checkout is deferred.
- Money is integer minor units; format with `Intl.NumberFormat` and never with floating-point arithmetic. Unknown enum values render a neutral fallback, never a confident state.
- Variants are read-only while `POST /products/{product}/variants` returns `501`; never render an enabled variant-creation control or send a request known to fail. Product media upload stays out of scope until its owning phase.
- Server actions live in `lib/catalog/product-actions.ts` and must repeat `requireAdminRouteAccess`'s exact permission first, because Server Functions are directly POST-reachable. Keep all authenticated calls on `lib/api/server-request.ts` + `lib/api/client.ts` (`no-store`); never put a bearer token in a Client Component prop or a shared cache.
- Category options come from the public `GET /categories` collection only, because no operational category endpoint exists; preserve the current selection when a category is absent from the first page, and never hardcode a category id.
- The list is a Server Component with a native `GET` filter form so filter state lives in the URL; do not fetch per keystroke, and never filter only the current server page for a global filter.

## Category module

- Expose only operations Laravel actually supports. The approved category contract is the public `GET /categories` and `GET /categories/{category}` reads, plus the `products.manage`-gated `POST /categories` and `PATCH /categories/{category}` writes. Never add a field, filter, sort or endpoint the contract does not define.
- The `GET /categories` collection returns **only the active top-level categories** (active direct children of the structural root). It is not the complete category collection: the root, inactive categories and deeper sub-categories have no listing endpoint and must never be claimed, guessed or reconstructed. Every list/create surface states this scope, and an empty result is never presented as "the catalog has no categories".
- Because no operational category endpoint exists, the category screens read the public catalog collection/detail while the writes stay operational and `products.manage`-gated. The permission is `products.manage`; there is no `categories.*` permission — never invent one.
- The category write allow-list is `name`, `slug`, `description`, `image` only. A create forces `parent = root` and `is_active = true`; hierarchy, active/inactive state and ordering are not request fields, so never render a parent, state or ordering control or send one.
- Category media upload stays out of scope until Phase 19.8; show the stored `image` URL only as an http(s) link, never a raw scheme, and never upload.
- Server actions live in `lib/catalog/category-actions.ts` and must repeat `requireAdminRouteAccess`'s exact permission first, because Server Functions are directly POST-reachable. Keep authenticated calls on `lib/api/server-request.ts` + `lib/api/client.ts` (`no-store`); a partial update sends only the changed fields and a no-op update is never sent.

- Every future screen needs accessible loading, empty, error, permission, keyboard, responsive and reduced-motion behavior.
- Run `npm run lint`, `npm run typecheck`, `npm run test`, `npm run build`, and `git diff --check` before completion.

<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->
