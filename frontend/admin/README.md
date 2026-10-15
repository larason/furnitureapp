# SL Furnitures Admin Dashboard

Independent Next.js 16 + React + TypeScript + MUI operational dashboard for SL Furnitures. The application is intentionally separate from `frontend/web` and is request-only until later phases enable additional business workflows.

## Local setup

```bash
cp .env.example .env.local
npm install
npm run dev
```

Open `http://localhost:3001`. The port is explicit so the admin and customer applications cannot accidentally share an origin. Without `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY`, the application renders a safe configuration state and never assumes an authenticated administrator.

## Scripts

| Script | Purpose |
| --- | --- |
| `npm run dev` | Start the admin app on port 3001 |
| `npm run build` | Create the production build |
| `npm run start` | Start the production build on port 3001 |
| `npm run lint` | Run Next.js ESLint rules |
| `npm run typecheck` | Run strict TypeScript checking |
| `npm run test` | Run transport, admission, navigation, route-guard, shell and primitive tests |

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
| `components/layout/admin-overview-body.tsx` | Overview landing content: account panel, permission-filtered shortcuts and roadmap status. |
| `components/layout/shell-layout.ts` | Shell identifiers and token-derived geometry (`ADMIN_SIDEBAR_WIDTH`, drawer width, `main` id). |

Only the shell entry point uses hooks. Sub-components are presentational so they can be rendered and asserted directly in `node:test`.

## Navigation configuration

`lib/navigation/admin-route-paths.ts` owns the route paths and the proxy matcher patterns; `lib/navigation/admin-navigation.ts` owns the single typed route registry: identifier, path, label, description, icon, group, exact permission and any approved role restriction. Per-group labels are `Workspace`, `Operations`, `Catalog` and `Administration`.

Navigation is always produced by `resolveAdminNavigation(identity)`, which filters the registry against the Laravel-resolved effective permissions. An `ADMIN` role never reveals a link for a permission the identity does not hold, and missing permission data fails closed.

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

**Staff management is deliberately absent.** `ADM-001`…`ADM-006` are routed but return `501`, and Phase 19.3 of the Group S roadmap gates the screen on backend completion. No navigation entry is published for an unimplemented endpoint.

Cart, checkout, payment, order and delivery administration are deferred while the release is request-only and must not be added to the navigation.

## Adding a navigation entry

1. Add the path to `lib/navigation/admin-route-paths.ts` (this also covers the proxy's early redirect).
2. Add the entry to `ADMIN_ROUTES` in `lib/navigation/admin-navigation.ts` with an existing Laravel permission and, only if the backend enforces one, an approved role restriction.
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
- Placeholders state the phase that will implement them and must not retrieve data or render fabricated records.

## Design and accessibility rules

`frontend/design-system/tokens.css` is the canonical token authority. `design-tokens.json` is its synchronized build-time representation; this application does not copy or redefine tokens. The MUI theme supplies the runtime variables that semantic token references such as `var(--surface-canvas)` depend on. Shell geometry derives from the spacing scale (`ADMIN_SIDEBAR_WIDTH` is `--space-9 × 4`); no ad-hoc widths, colors or shadows are introduced. Use MUI and `@mui/icons-material`; do not add another component or token system.

Shell accessibility expectations: a keyboard skip link to `#admin-main-content`, one `main` landmark, one labelled `nav` per navigation group, `aria-current="page"` on the active destination (never colour alone), named icon-only controls, visible `--focus-ring` focus, MUI modal drawer focus handling and Escape dismissal, responsive reflow without horizontal overflow, and reduced-motion support (`app/globals.css`). Loading, empty, error and permission-safe states reuse `components/states` and `components/auth/admission-state`.

## Phase exclusions

This phase does not implement request, enquiry, catalog, inventory, notification, customer, staff or audit workflows; every operational destination is an explicit, permission-protected placeholder. It also does not add Reverb subscriptions, notification preferences, dashboard metric endpoints, charts, new Laravel routes, migrations, roles or permissions.
