# Phase 19.5 — Dashboard Overview

## Status

**PASS.** The Overview is a polished, navigation-first landing page built entirely from the existing registry and the Laravel-resolved identity. Every automated gate passes, and the previously outstanding **live authenticated `/` render** has now been executed against a real Clerk ADMIN session (see §12).

One verification remains genuinely unexecutable and is reported as a limitation rather than a pass: **live STAFF presentation** (§10.3).

## 1. Files changed

**Modified**

- `frontend/admin/app/(protected)/page.tsx` — welcome heading from `operationalDisplayName(identity)` ("Welcome back, {name}"), tighter purpose sentence.
- `frontend/admin/components/layout/admin-overview-body.tsx` — rewritten content hierarchy (access summary, shortcut grid with per-destination roadmap status, not-yet-available list).
- `frontend/admin/lib/navigation/admin-navigation.ts` — `plannedFor` on every placeholder route, `adminRouteStatus()`, `resolveOverviewNavigation()`.
- `frontend/admin/components/layout/admin-route-placeholder.tsx` — reads the phase from the registry; the `phase` prop is gone.
- `frontend/admin/components/ui/feature-placeholder.tsx` — `phase` is optional so status has one source.
- `frontend/admin/components/ui/section-header.tsx` — description column constrained to `--content-width-lead` (line-length parity with `PageHeader`).
- `frontend/admin/app/(protected)/{requests,enquiries,notifications,products,categories,inventory,customers,audit-logs}/page.tsx` — dropped the hardcoded `phase="…"` literal.
- `frontend/admin/tests/shell.test.tsx` — placeholder render without the removed prop; fabricated-count regex tightened (see §8).
- `frontend/admin/tests/overview.test.tsx` — **new**, 16 tests.
- `frontend/admin/package.json` — test script includes the new suite.
- `frontend/admin/README.md`, `frontend/admin/AGENTS.md` — Overview structure, `plannedFor` single-source rule, no-analytics rule.

Nothing outside `frontend/admin` and `phases/` was touched; nothing is staged or committed. The pre-existing uncommitted edits to `phases/group-S-phases.md` and `future.md` are the owner's and were left untouched.

## 2. Overview layout and content hierarchy

```
app/(protected)/page.tsx   guard → PageContainer → PageHeader (h1)
  └── AdminOverviewBody (client, serializable identity prop only)
        ├── Your access (h2)          sanitized name/email/role + one-line Laravel boundary
        ├── Operational areas (h2)    permission-filtered shortcut grid
        │     ├── Operations (h3)     requests · enquiries · notifications
        │     ├── Catalog (h3)        products · categories · inventory
        │     └── Administration (h3) customers · audit logs
        └── Not yet available (h2)    no-entry areas + the no-reporting-contract statement
```

- Heading order is `h1 → h2 → h3 → h2` with no skipped level (asserted in tests and observed in the browser).
- Shortcuts are whole-card links (`Card` rendered through `next/link`): the accessible name is the route label, the icon is `aria-hidden`, the entire card is clickable, and there is no nested control or hover-only information.
- The grid reflows `1 → 2 → 3` columns at `xs → sm → xl`; cards use `minmax(0, 1fr)`, `overflowWrap: "anywhere"`, `minWidth: 0` and a 44px minimum target, with no fixed width or height and no `nowrap`.
- The Overview no longer links to itself: `resolveOverviewNavigation()` removes the overview entry and drops empty groups, so a permission-limited account gets a truthful "No operational areas are available for this account." instead of a self-link.
- Section descriptions and the page description are constrained to `--content-width-lead` for readable line lengths.

## 3. Navigation registry and permission-filtering reuse

- The single `ADMIN_ROUTES` registry remains the only navigation configuration; the Overview adds no second array of destinations, permissions or paths. `resolveOverviewNavigation(identity)` is derived from `resolveAdminNavigation(identity)`, which is the same permission/role filter the sidebar, drawer, breadcrumbs and route guards already use. A test asserts the body hardcodes no `href`.
- Shortcut visibility is unchanged in principle and re-verified for every permission shape: STAFF sees its permitted set only; ADMIN additionally sees Customers and Audit logs; an ADMIN without `users.manage_authorized` still does not; missing/malformed permission data fails closed to the empty-workspace message with no links.
- Each destination's label, description, icon, path, permission and role restriction still come from the registry, so a shortcut cannot drift from the route that enforces it. Test 17's guarantee ("destinations retain their own route guards") continues to be enforced by `tests/navigation.test.ts`, which re-ran green: every configured route's page calls `requireAdminRouteAccess(adminRoute(...))` and renders `access.denial`.

## 4. Implementation-status presentation and roadmap source

- `AdminRoute.plannedFor` now records the roadmap phase that owns each placeholder's workflow, with the values the placeholder pages previously hardcoded: requests 19.10, enquiries 19.11, products 19.6, categories 19.7, inventory 19.9, customers 19.12, audit logs 19.14, and notifications "the approved notification inbox phase" (the Group S roadmap contains no notification-screen phase, so no number is invented).
- A test parses the approved roadmap from root `AGENTS.md` (`### 19.x — Title` headings under Phase Group S) and fails if any displayed `Phase 19.x` status does not exist there, so the overview cannot advertise a phase the owner did not approve.
- Each shortcut shows a restrained text line `Not yet implemented — {phase}` through `adminRouteStatus()` — no coloured badge, and nothing that could read as operational state.
- "Not yet available" now lists only what has **no** navigation entry (Staff management → Phase 19.13, blocked on the `501` backend endpoints; Account and settings → Phase 19.15; orders/payments/checkout/delivery → deferred while the release is request-only), so roadmap status is no longer duplicated between two sections. Its copy states that the shell and navigation are available while the workflows behind them arrive in scheduled phases, and that a link does not mean the workflow is functional.

## 5. Identity display and authorization-boundary preservation

- The heading uses the existing sanitized helper: `Welcome back, ${operationalDisplayName(access.identity)}`, falling back to the approved "Operational user" when the name is absent. No name, email, role or image is invented.
- "Your access" keeps the sanitized `AdminUserIdentity` (name, email, local role label) plus one sentence on Laravel's per-request resolution; it shows no permission list, no token, no raw `/me` field. Tests fail if any permission identifier or `Bearer`/`eyJ`/`token` text appears in the rendered Overview.
- The route guard is untouched: `/` is still admission-only, `requireAdminRouteAccess(OVERVIEW_ROUTE)` still runs first and returns `access.denial`, the protected layout is still the only admission boundary, and `proxy.ts` still decides no role or permission. ADMIN still gets no permission bypass anywhere (`tests/route-guard.test.ts` re-run).
- Only the serializable `OperationalIdentity` crosses the Server/Client boundary; the body stays a Client Component (`tests/server-boundary.test.ts` re-run), so no function prop or bearer token can be serialized.

## 6. No aggregate API and no fabricated data

- The Overview makes **zero** business-data calls: no transport import, no `fetch`, no `/api/v1` string, no route handler, no cache. The only backend request remains the admission flow's `GET /api/v1/me`, which is unchanged. The browser network log for the rendered Overview shows no Laravel request at all.
- No revenue, sales, order, request, enquiry, customer, inventory, conversion, unread or activity figure exists anywhere in the page; no charts, tables, forms, timelines, mock records, `0`/`—`/`N/A` KPI placeholders, or "today/this week" summaries. Tests assert this over both the rendered text and the markup.
- No new endpoint, migration, permission, role, Reverb connection or notification subscription was added; the frozen API contract is untouched.

## 7. Automated test coverage (the phase's 26 checks)

`tests/overview.test.tsx` (16 tests) maps to the checklist: welcome heading + approved fallback; heading hierarchy; registry-generated shortcuts with no hardcoded path; permission-eligible visibility for every shape; usefulness on a small permission set; fail-closed on malformed permissions; shell-availability vs unfinished-workflow wording; roadmap metadata matched against `AGENTS.md`; no fabricated analytics; no new API call; the unchanged admission guard and denial render; serializable-only Server/Client props; sanitized identity with no internal payload; accessible names with no nested controls; responsive reflow without fixed geometry; visible keyboard focus. Checks 11–13, 17–21 are additionally enforced by the re-run 19.3/19.4 suites (`operational-identity`, `admission-states`, `route-guard`, `navigation`, `shell`, `server-boundary`).

**Two test-pattern defects were found and fixed, not weakened.** (a) The roadmap assertion prefixed "Phase " twice (`### Phase 19.10 —`), which never matched the roadmap headings; it now extracts the phase number. (b) The fabricated-count pattern `\b\d+\s*(requests|…)` read the adjacency of a phase number and a module name ("…Phase 19.10" next to the "Enquiries" card) as a fake count. It is now `(?<![\d.])\b\d+\s+(?:…)`, which still catches every genuine fabricated count ("10 requests", "1,200 customers", currency, percentages, revenue/sales/conversion/unread/last-updated wording) while ignoring roadmap numbers; the same correction was applied to the 19.4 pattern in `shell.test.tsx`.

## 8. Quality gates (from `frontend/admin`)

| Check | Result |
| --- | --- |
| `npm run lint` | PASS — 0 problems |
| `npm run typecheck` | PASS |
| `npm run test` | PASS — 72 tests, 0 fail (56 pre-existing incl. 19.3 auth suites + 16 new) |
| `npm run build` | PASS — `/` and all eight destination routes dynamic (`ƒ`), `/sign-in` dynamic, only `/_not-found` static; no harness route |
| `git diff --check` | PASS for every file this phase authored or modified. The unfiltered command flags only the owner-provided `phases/group-S-phases.md` (Markdown hard line breaks in its header block), which I deliberately left untouched |

## 9. Live authenticated verification (executed)

Run against the running Laravel API and `frontend/admin` on `http://localhost:3001` with a real Clerk ADMIN session, in Chromium. This closes limitation 1 and 2 from the previous revision of this report.

| Check | Result |
| --- | --- |
| Clerk sign-in | Succeeded; `/` rendered the Overview |
| Laravel `/me` | Resolved the bootstrapped ADMIN; "Your access" shows Role: Administrator |
| Welcome heading | `Welcome back, Operational user` — approved fallback used because the local record has no `name` |
| Shortcuts | All 8 ADMIN-eligible destinations with `Not yet implemented — Phase …`; the Overview does not link to itself |
| Roadmap accuracy | Every displayed phase (`19.6, 19.7, 19.9, 19.10–19.15`) exists in the approved Group S roadmap |
| "Not yet available" | Exactly the three no-entry areas |
| Heading order | `h1 → h2 → h2 → h3 ×3 → h2`; no skipped level, single `main` at every width |
| Nested interactive elements | None |
| Business API calls | **Zero** — the only browser requests were Clerk's own instance bootstrap; no `/api/v1` call from the Overview |
| Fabricated data | No currency, percentage, count, total, revenue, conversion, unread, `N/A` or time-based summary in the rendered text. The only regex hit was the word "recent activity" inside the disclaimer *"never counts, totals or recent activity"* — a negation, not data |
| Permission/internal leakage | No permission identifier, `Bearer`, `eyJ` or opaque `user_…` id in the rendered text |
| Console | Clean apart from Clerk's development-instance advisory |

### Responsive (authenticated, real rendering)

Measured with viewport emulation, not window resizing, so the two narrowest required widths were genuinely exercised:

| Width | Result |
| --- | --- |
| 360 px | 1 column (328 px cards), `scrollWidth === innerWidth`, zero overflowing elements, h1 not clipped, sidebar hidden, drawer trigger visible, single `main` |
| 390 px | 1 column (358 px cards), no overflow, min card height 120 px (≥ 44 px target) |
| 768 px | 2 columns, no overflow, drawer trigger visible, sidebar hidden |
| 1024 px | Persistent 256 px sidebar, trigger hidden, `main` starts at x = 256, 2 columns, no overflow |
| 1440 px | 3 columns (355 px cards), no overflow, full-page screenshot reviewed |

### Keyboard and drawer

- Tab reaches every shortcut in DOM order and renders the design-system ring: `box-shadow: rgba(39, 93, 197, 0.95) 0 0 0 2.84px` (i.e. `--focus-ring`, #275dc5, mid-transition). This resolves the previous limitation that the ring "could not be captured" — the earlier attempt used programmatic `.focus()`, which does not trigger `:focus-visible`; real `Tab` input does.
- The mobile drawer opens as `dialog "Operational navigation"` (modal, focus inside, named close control) and contains all four navigation groups.
- `Escape` closes it, `aria-expanded` returns to `false`, and focus returns to the trigger.

### Observed layout characteristic (not a defect, for owner awareness)

The lead text and headings are capped by `--content-width-lead` (`44ch` → 392 px) while the shortcut grid uses the full content width (1184 px at 1440 px). This is the deliberate line-length decision recorded in §2, but at desktop width it makes the `h1` wrap onto two lines ("Welcome back," / "Operational user") and leaves the right third of the page empty above the grid. It is consistent with `PageHeader` and with the storefront's measure, so it was **not** changed unilaterally. If a wider measure is preferred, `--content-width-lead` and `PageHeader` should be revisited together.

## 10. Signed-out boundary (unchanged)

Every protected path (`/`, `/requests`, `/requests/42`, `/enquiries`, `/products`, `/categories`, `/inventory`, `/notifications`, `/customers`, `/audit-logs`) returns `307 → /sign-in` while signed out; `/sign-in` returns 200 with no registration link; the deleted 19.4 harness route returns 404.

## 11. Known limitations

1. **Live STAFF presentation remains unexecuted** (inherited from 19.3/19.4). It is covered by synthetic STAFF permission sets in `tests/overview.test.tsx` and `tests/navigation.test.ts`. It is also **not closable today**: `ADM-001…ADM-006` return `501` and `staff_profiles` has no `staff_state` column, so no approved STAFF identity can be provisioned through any supported path. Creating one by hand would recreate the bypass that was removed during Phase 19.3. This needs the staff-lifecycle backend work before the UI can be verified live.
2. `notifications.read_operational` still has no runtime enforcement (NOT-001/002 are recipient-scoped), so its status line deliberately names no phase number.
3. Full-repo `git diff --check` is not clean because of the owner-provided `phases/group-S-phases.md` Markdown hard breaks (§8); all files this phase touched are clean.
4. The dependency findings open since 19.2 remain an open follow-up, unchanged.
5. Closed by this revision: the live authenticated render and the focus-ring capture. Both were re-run rather than assumed.

## 12. Handoff to Phase 19.6 (Product Management)

1. Replace the Products page body in place: keep the route, label, description, permission and `plannedFor` in `ADMIN_ROUTES`; delete `plannedFor` from the entry once the workflow is real, and the Overview status line disappears with it (no other file to update).
2. Keep `requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.products))` as the first statement and render through `PageContainer`/`PageHeader` inside the shell's single `main`.
3. Add domain/query functions and their cache policies in the owning phase; the transport stays `lib/api/client.ts` with `no-store` authenticated calls.
4. Give every screen accessible loading, empty, error, permission and reduced-motion states from `components/states`; the Overview's success criteria (no fabricated data, permission-filtered navigation, sanitized identity) apply unchanged to each module.
5. `products.view` (read-only catalog) does not exist in the Laravel catalog today; if a read-only view is later approved, revisit the Products entry requirement explicitly rather than widening the frontend rule.
