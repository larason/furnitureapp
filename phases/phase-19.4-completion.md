# Phase 19.4 — Dashboard Shell & Navigation

## Status

**PASS for the authenticated ADMIN journey — the outstanding owner-run smoke test was executed against real Clerk and Laravel, and it exposed and fixed a blocking defect. One item remains genuinely unexecutable: STAFF permission-based denial, for the same reason as in 19.3.**

Everything listed under "remaining limitation" below was verified as far as the environment allowed; nothing was skipped inside the reachable scope, and no failure was worked around.

## 1. Files changed

**Added — route registry and authorization**

- `frontend/admin/lib/navigation/admin-route-paths.ts`
- `frontend/admin/lib/navigation/admin-navigation.ts`
- `frontend/admin/lib/auth/permission-names.ts`
- `frontend/admin/lib/auth/identity-display.ts`
- `frontend/admin/lib/auth/route-guard.ts`
- `frontend/admin/lib/auth/server-route-guard.tsx`

**Added — shell components**

- `frontend/admin/components/layout/{admin-shell,admin-sidebar,admin-mobile-drawer,admin-topbar,admin-breadcrumbs,admin-user-menu,admin-user-identity,admin-navigation-list,admin-route-placeholder,admin-overview-body,admin-brand,admin-skip-link}.tsx`
- `frontend/admin/components/layout/{shell-layout.ts,navigation-drawer-state.ts}`
- `frontend/admin/components/ui/feature-placeholder.tsx`

**Added — protected routes**

- `frontend/admin/app/(protected)/loading.tsx`
- `frontend/admin/app/(protected)/{requests,enquiries,notifications,products,categories,inventory,customers,audit-logs}/page.tsx`

**Added — tests**

- `frontend/admin/tests/navigation.test.ts` (16 tests)
- `frontend/admin/tests/route-guard.test.ts` (7 tests)
- `frontend/admin/tests/shell.test.tsx` (13 tests)
- `frontend/admin/tests/server-boundary.test.ts` (3 tests) — added after the authenticated smoke test, see §13.1

**Modified**

- `app/(protected)/layout.tsx` — admitted identities now render inside `AdminShell`.
- `app/(protected)/page.tsx` — Phase 19.3 confirmation page replaced by the overview landing page.
- `app/globals.css` — `prefers-reduced-motion` support (mirrors the storefront).
- `components/ui/section-header.tsx` — optional `titleId` so a section can be labelled by its heading.
- `lib/auth/operational-identity.ts` — `hasPermission()` fails closed when permission data is missing or malformed.
- `lib/auth/server-operational-identity.ts` — admission is resolved through React `cache()` once per request.
- `proxy.ts` — early signed-out redirect extended to every protected path via `ADMIN_PROTECTED_MATCHERS`; it still decides no role or permission.
- `package.json` — test script includes the three new test files.
- `README.md`, `AGENTS.md` — shell, navigation, permission, route-protection and accessibility documentation.

**Removed**

- `components/auth/sign-out-button.tsx` — superseded by the account menu, which is now the single sign-out control and still uses Clerk `signOut({ redirectUrl: "/sign-in" })`.

**Owner-provided asset used**

- `public/brandlogo.svg` (already present, untracked) for the restrained sidebar brand mark.

No Laravel, database, customer-website or business-workflow file was changed.

## 2. Shell architecture

```
app/(protected)/layout.tsx        admission boundary (unchanged Phase 19.3 decisions)
  └── OperationalIdentityProvider  sanitized identity, keyed by identity id
        └── AdminShell (client)    identity from context, navigation filtering,
              │                    active destination, drawer state, single <main>
              ├── AdminSkipLink    “Skip to main content” → #admin-main-content
              ├── AdminSidebar     permanent MUI Drawer, md and above
              ├── AdminMobileDrawer modal MUI Drawer below md, closes on select
              ├── AdminTopbar      menu trigger, breadcrumbs/title, account control
              │     ├── AdminBreadcrumbs
              │     └── AdminUserMenu → AdminUserIdentity
              └── <main id="admin-main-content">  page content
```

The layout renders the shell **only** after admission succeeds; the four denial states still render outside the shell with their own `main` landmark. Only the shell entry point uses hooks (`useOperationalIdentity`, `usePathname`, `useReducer`, `useMediaQuery`); sidebar, list, breadcrumbs, identity, placeholder and overview-body components stay presentational so they can be rendered and asserted in `node:test` without a DOM library.

## 3. Sidebar and topbar

- **Sidebar** — a permanent drawer at `ADMIN_SIDEBAR_WIDTH` (`calc(var(--space-9) * 4)` = 256px, derived from the canonical spacing scale because `tokens.css` defines no sidebar token). It holds the brand mark, one labelled `nav` per navigation group, 44px minimum item targets, hover and `--focus-ring` focus states, and a non-colour active treatment (editorial surface + brand left border + `aria-current="page"`). It is `display: none` below `md`.
- **Topbar** — sticky MUI `AppBar` (`color="inherit"`, flat) with the mobile menu trigger (labelled, `aria-haspopup="dialog"`, `aria-controls`, `aria-expanded`), page context (breadcrumbs from `md` up, page title below), and the account control. No global search, no notification bell, no badges, no fabricated profile data.
- **Account control** — a MUI `Button` showing `operationalDisplayName(identity)`, opening a menu with the sanitized name, email and role label plus a **Sign out** item that calls the existing Clerk flow. Falls back to "Operational user" / "No email on file"; never renders a token, permission list or internal API field.

## 4. Responsive drawer behaviour

- Below `md` (960px) the persistent sidebar is replaced by a MUI modal drawer at `min(calc(100% - var(--space-6)), var(--content-width-form))`.
- Opening is keyboard reachable through the labelled trigger; MUI supplies focus trapping, Escape dismissal and focus restoration to the trigger (verified in the browser: Escape closed the drawer, focus returned to the trigger, `aria-expanded` returned to `false`).
- Selecting a destination dispatches the `navigate` drawer action, which closes the drawer (verified in the browser with a captured link click).
- Switching to the desktop layout closes the drawer (`desktop` action, mirroring the storefront's media-query pattern).
- Reduced motion is honoured globally in `app/globals.css`.

## 5. Navigation groups and routes

| Group | Item | Route |
| --- | --- | --- |
| Workspace | Overview | `/` |
| Operations | Made-to-order requests | `/requests` |
| Operations | Enquiries | `/enquiries` |
| Operations | Notifications | `/notifications` |
| Catalog | Products | `/products` |
| Catalog | Categories | `/categories` |
| Catalog | Inventory | `/inventory` |
| Administration | Customers | `/customers` |
| Administration | Audit logs | `/audit-logs` |

Groups with no permitted item are dropped. One registry (`ADMIN_ROUTES`) supplies the label, description, icon, group, path, permission and role restriction used by the navigation, the page headers, the page metadata, the breadcrumbs and the route guards, so a route cannot drift between surfaces.

Within a group the most specific matching route wins, comparison is prefix-safe (`/requests/42` → **Made-to-order requests**; `/products-archive` → nothing), and the overview only matches `/`.

## 6. Verified permission mapping

| Route | Requirement | Backend authority |
| --- | --- | --- |
| `/` | admission only | `GET /api/v1/me` |
| `/requests` | `requests.view` | `routes/api.php` `permission:requests.view` |
| `/enquiries` | `enquiries.view` | `permission:enquiries.view` |
| `/notifications` | `notifications.read_operational` | `PermissionName::NOTIFICATIONS_READ_OPERATIONAL` |
| `/products` | `products.manage` | `permission:products.manage` |
| `/categories` | `products.manage` | category writes are gated by `permission:products.manage`; **no separate category permission exists**, so none was invented |
| `/inventory` | `inventory.view` | `permission:inventory.view` |
| `/customers` | `users.manage_authorized` + `ADMIN` | `permission:users.manage_authorized` inside the `admin` middleware |
| `/audit-logs` | `audit.view` + `ADMIN` | `permission:audit.view` inside the `admin` middleware |

A test parses `backend/laravel/app/Support/PermissionName.php` and fails if any configured or referenced identifier is not a real Laravel permission, so a fabricated permission cannot be introduced silently.

**Omitted and documented — Staff management.** `ADM-001`…`ADM-006` are routed but `AdminController` returns `501`, and the roadmap gates the screen (19.13) on backend completion. Per the phase rule, no navigation entry was published for an unimplemented endpoint; the omission is asserted by a test and stated in the README.

**Deferred and never navigable** — orders, payments, checkout and delivery administration, matching the request-only release.

## 7. Direct route authorization

- `resolveAdminRouteAccess(admission, requirement)` is the pure decision: `granted`, `forbidden`, `signed-out`, `session-invalid`, `unavailable`, or `account-denied`.
- `requireAdminRouteAccess(requirement)` runs server-side on every protected page, reusing the cached `/me` admission, and maps a denial to the existing Phase 19.3 states (`AccessDeniedState` for a missing permission or unapproved role, `AccountUnavailableState`, `SessionInvalidState`, `ServiceUnavailableState`).
- An `ADMIN` role is not a bypass: the exact permission is still required, and missing/malformed permission data fails closed. Role restrictions only mirror the backend `admin` middleware for `/customers` and `/audit-logs`.
- Live check: **every** protected path (`/`, `/requests`, `/requests/42`, `/enquiries`, `/products`, `/categories`, `/inventory`, `/notifications`, `/customers`, `/audit-logs`) returns `307` to `/sign-in` while signed out, and a real link click from the shell landed on the sign-in surface with no private content served.
- `proxy.ts` now performs that redirect early for the same paths but still evaluates no role or permission; a test asserts it uses the shared matcher list and contains no authorization vocabulary.

## 8. Overview page behaviour

The Phase 19.3 development confirmation page was replaced by a navigational landing page containing:

1. A welcome heading built from the sanitized display name (fallback "Welcome").
2. A "Your access" panel with the sanitized name, email and role label, plus the statement that Laravel resolves the identity per request and each page re-checks its own requirement.
3. An "Operational areas" section listing only the permission-filtered destinations with their descriptions.
4. A "Not yet available" section listing the scheduled areas with their owning phase and the deferred commerce administration, explicitly stating that nothing in the list is functional.
5. A closing statement that no operational metrics are displayed because no approved reporting endpoint exists.

No revenue, sales, order, conversion, customer, inventory, pending-request, notification or activity figure appears anywhere, and no API endpoint is called by the overview. Rendered-markup tests assert the absence of unauthorized links, currency/percentage text, fabricated counts and unread context.

## 9. Placeholder routes and deferred dependencies

Each enabled destination has a protected placeholder that renders its real label and description, an explicit **Not yet implemented** heading, the sentence that the feature is scheduled for its phase and retrieves no data, and a neutral "Planned for …" status chip: `/requests` → 19.10, `/enquiries` → 19.11, `/products` → 19.6, `/categories` → 19.7, `/inventory` → 19.9, `/customers` → 19.12, `/audit-logs` → 19.14, `/notifications` → its approved UI phase (the Group S roadmap contains no notification-screen phase, so the item is a labelled placeholder while its endpoints remain NOT-001/002).

No fake tables, inboxes, forms or records exist. A placeholder route is created only where a real backend contract already exists.

## 10. Accessibility and responsive test results

Verified in a real browser (Chromium) on the running application at **360, 390, 768, 1024 and 1440 px**:

| Check | Result |
| --- | --- |
| Horizontal overflow at every width | None (`scrollWidth === innerWidth`) |
| Sidebar/drawer switch | Hidden below 960px with the trigger visible; persistent 256px sidebar from 1024px with the trigger hidden |
| Trigger, groups and links in the accessibility tree | Skip link → brand link → four labelled `navigation` landmarks with lists → `banner` → `Breadcrumb` nav → single `main` |
| Modal drawer semantics | Exposed as `dialog "Operational navigation"`, named close control, focus inside the dialog |
| Escape dismissal / focus restoration | Drawer closed, no navigation, focus returned to the trigger, `aria-expanded="false"` |
| Selecting a destination | Drawer closed before navigation |
| Account menu at 360px | Constrained to the viewport (no document overflow), Sign out present |
| Long labels and long account name | Full label text rendered; the topbar truncates with ellipsis only, with no clipping or overflow |
| Active destination | Exactly one `aria-current="page"` per surface (navigation item + breadcrumb) |
| Focus treatment | Skip link focused at runtime with `box-shadow: rgb(39, 93, 197) 0px 0px 0px 3px` (`--focus-ring`); navigation items resolve to the same ring |
| Heading hierarchy | `h1` page title → `h2` section/placeholder, and `h3` group labels on the overview |
| Reduced motion | Global preference rule in `app/globals.css` |
| Console | Clean apart from Clerk's development-instance key advisory |

The visual/interaction pass used a temporary local harness route (synthetic identity, no admission bypass) which was deleted before completion; `git status`, the final build route table and a 404 probe confirm it is gone.

## 11. Authentication regression results

- The Phase 19.3 suites were re-run unchanged and pass: `tests/operational-identity.test.ts`, `tests/admission-states.test.ts`, `tests/api-client.test.ts` (17 tests), including active STAFF/ADMIN admission, CUSTOMER/inactive/malformed denial, the recoverable Laravel-401-under-live-session state, service outage, independent successive identity resolution, and every denial state offering sign-out.
- The protected layout still performs the admission decision before any shell content renders; the per-request cache changes only how often the same decision is computed.
- Live boundary integrity: signed-out requests to all protected paths redirect to `/sign-in`; `/sign-in` still renders the sign-in-only surface; `/sign-up` is still not a route (unchanged from 19.3).
- Sign-out path: the account menu calls Clerk `signOut({ redirectUrl: "/sign-in" })` (asserted from source), and the protected tree unmounts on navigating to `/sign-in`, so no stale identity is presented. No new session store, cookie or localStorage usage was introduced (asserted by tests).

## 12. Lint / typecheck / test / build results

Run from `frontend/admin`:

| Check | Result |
| --- | --- |
| `npm run lint` | PASS |
| `npm run typecheck` | PASS |
| `npm run test` | PASS — 56 tests (17 pre-existing + 39 new) |
| `npm run build` | PASS — all protected routes dynamic (`ƒ`), `/_not-found` and the root layout static; no static private output |
| `git diff --check` | PASS — clean |
| `curl` on port 3001 | App served; all protected paths `307 → /sign-in`; `/sign-in`, `/brandlogo.svg` 200 |

A latent defect found and fixed during verification: `next/image` rejects SVG through the optimizer (`/_next/image?url=%2Fbrandlogo.svg` → 400), so the brand mark is rendered with `unoptimized` and the raw SVG now loads (natural width 4667 rendered at 78px).

## 13. Authenticated live smoke test (executed) and the defect it found

The authenticated journey was executed with the existing bootstrapped Clerk `ADMIN` identity against the running Laravel API, in Chromium at 1440 px. It **failed first**, and the failure was real.

### 13.1 Blocking defect: overview crashed for every authenticated identity

Immediately after a successful Clerk sign-in the protected overview rendered `app/error.tsx` ("The admin application could not load"). The console showed a minified `React error #441`, and the server log gave the cause:

```
Functions cannot be passed directly to Client Components unless you explicitly
expose it by marking it with "use server".
  {component: function i, href: "/", sx: ..., children: ...}
```

`components/layout/admin-overview-body.tsx` has no `"use client"` directive, so `app/(protected)/page.tsx` (a Server Component) rendered it on the server. Its `OverviewShortcuts` passed `component={NextLink}` — a **function** — into MUI's client `ListItemButton`, which cannot be serialized across the RSC boundary. Every authenticated overview navigation threw, so the shell was effectively unusable behind a valid session.

This is precisely the gap that made the phase PARTIAL: the earlier visual pass used a temporary local harness that rendered the overview **inside the client shell**, where the same file is legal. Only the real server-rendered route exercises the failing path.

**Fix.** Every admin file that passes a component reference is now an explicit Client Component, which makes the reference legal regardless of the caller: `admin-overview-body`, `admin-brand`, `admin-breadcrumbs`, `admin-navigation-list` and `ui/data-table` (the last is the same latent trap — it also takes `render`/`getRowKey` function props that a Server Component could never supply).

**Regression guard.** `tests/server-boundary.test.ts` scans every `.tsx` under `app/` and `components/` and fails when a file uses `component={…}` without a `"use client"` directive. It was written first and initially failed listing all five files.

### 13.2 Verified journey after the fix

| Check | Result |
| --- | --- |
| Sign-in surface | Clerk email form renders; no "Sign up" link; `withSignUp`/`transferable` still disabled |
| Clerk sign-in as ADMIN | Succeeded; redirected to `/` |
| Laravel `/me` | Resolved the bootstrapped identity; overview shows "Role: Administrator" |
| Overview render | "Your access" (name/email/role), "Operational areas", "Not yet available"; no crash |
| Desktop shell at 1440 px | Skip link, brand link, four labelled `navigation` landmarks, `banner`, `Breadcrumb`, single `main` |
| Permission-filtered navigation | ADMIN sees all nine destinations, including the `ADMIN`-only Customers and Audit logs |
| Placeholder routes | `/requests` (19.10), `/enquiries` (19.11), `/notifications`, `/products` (19.6), `/categories` (19.7), `/inventory` (19.9), `/customers` (19.12), `/audit-logs` (19.14) — each shows its label, "Not yet implemented", its owning phase, and no fabricated records |
| Breadcrumb trail | Updates per destination (`Overview / Made-to-order requests`) |
| Account menu | Contains name, email, role and a **Sign out** item |
| Sign out | Ends the session; a subsequent `/requests` request redirected to `/sign-in` with no private content |
| Laravel outage (`/customers`, an `ADMIN`-only route) | Failed closed to "Operational access unavailable" with a Sign out action — proving admission is Laravel-gated, not client-assumed |
| Console across the whole sweep | Clean apart from Clerk's development-instance key advisory |

## 14. Known limitations

1. **STAFF permission denial remains unexecuted.** No approved active STAFF Clerk identity was available, and none was created. The `ADMIN`-only routes were proven to be Laravel-gated by the outage test rather than by a real STAFF denial, so the STAFF-specific presentation and its `Access denied` path are still covered only by unit tests over a synthetic STAFF permission set (19.3 left the equivalent Test C unexecuted for the same reason).
2. The authenticated pass was run at desktop width; the responsive drawer behaviour in §10 was verified through the temporary harness, not through a real session.
3. `notifications.read_operational` is not yet enforced by any runtime endpoint (NOT-001/002 are recipient-scoped); the navigation gate uses the catalog permission, and the inbox UI has no approved Group S phase yet.
4. The account name and the mobile page title truncate with ellipsis on the narrowest screens; this is deliberate reflow behaviour, not clipping, and the full values remain available in the page heading and account menu.
5. Production origins, CORS, CSP, Clerk allowed parties and Reverb WSS values remain deployment configuration, unchanged from 19.3.
6. The five development/transitive high-severity dependency findings from the 19.2 audit remain an open follow-up.

## 15. Phase 19.5 handoff

The shell is ready for the real operational modules. Phase 19.5 (Dashboard Overview) can refine the overview landing content, and 19.6–19.14 can replace each placeholder in place:

1. Keep the route, label, description and requirement in `ADMIN_ROUTES`; only the page body changes.
2. Keep `requireAdminRouteAccess(adminRoute(...))` as the first statement of every page and render through `PageContainer`/`PageHeader` inside the shell's single `main`.
3. Add domain/query functions and their cache policies in the owning phase; the transport stays in `lib/api/client.ts` with `no-store` authenticated calls.
4. Extend the navigation only together with its backend contract, its exact Laravel permission and its tests; keep the `package.json` test list and the completion record current.
5. If a read-only catalog view is later approved for `products.view` holders, revisit the Products entry requirement explicitly rather than widening the frontend rule.
