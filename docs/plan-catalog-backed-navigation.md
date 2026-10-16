# Plan — Catalog-Backed Category Navigation

**Created:** 2026-10-15
**Status:** PROPOSED — §9.1 decided by the owner (2026-10-16); §9.2–§9.5 remain open. Nothing here is scheduled or implemented yet; the work is deferred to the Group S closure pass.
**Application:** `frontend/web` only
**Register entries:** F14 (primary navigation is a hardcoded fixture), F15 (homepage room tiles are capped at four)
**Companion closure item:** F10 (user-facing roadmap jargon, `frontend/admin`) — different app, no shared files, verified in the same Group S closure pass.

This plan is deliberately a proposal. It contains one real architectural trade-off (site-wide rendering
mode, §6) and one editorial decision (which categories belong in the navigation, §9) that the project owner
should make rather than an agent.

---

## 1. Objective

Make the customer-facing category navigation read the same authoritative catalog the rest of the storefront
reads, instead of a hand-maintained copy of the database taxonomy.

Concretely: header navigation, mobile drawer and footer stop importing
`components/layout/category-navigation.fixture.ts` and start rendering the categories published by
Laravel's public `GET /api/v1/categories`.

## 2. Verified current state

Every claim below was read from the repository on 2026-10-15.

### 2.1 The three hardcoded surfaces

| Surface | File | Line | Mode |
| --- | --- | --- | --- |
| Header navigation | `frontend/web/components/layout/primary-category-navigation.tsx` | 30 | server component, no props |
| Mobile drawer | `frontend/web/components/layout/mobile-navigation.tsx` | 97 | `"use client"` |
| Footer | `frontend/web/components/layout/site-footer.tsx` | 48 | server component, no props |

All three map over the same constant:

- `frontend/web/components/layout/category-navigation.fixture.ts:10-17` — six items
  (`living-room`, `bedroom`, `dining-room-kitchen`, `home-office-corporate-workspaces`,
  `outdoor-patio`, `entryway-accent`) with the header comment
  *"Slugs mirror Laravel's CategorySeeder taxonomy until catalog-backed global navigation is introduced."*
- It is **unconditional** — unlike the data-layer fixture, nothing switches it off.
- No test renders any of the three surfaces (`grep` for `Primary navigation`, `Mobile navigation`,
  `PrimaryCategoryNavigation`, `SiteFooter`, `SiteShell` in `**/*.test.ts*` returns nothing), so the
  hardcoded list is currently unguarded in both directions.

### 2.2 Composition

```
app/layout.tsx  (root layout — sync, no data fetch, no `dynamic` export)
  └── SiteShell                    components/layout/site-shell.tsx:54,63   props: { children } only
        ├── SiteHeader             components/layout/site-header.tsx       server
        │     └── MobileNavigation components/layout/mobile-navigation.tsx  "use client"
        ├── PrimaryCategoryNavigation
        └── SiteFooter
```

`SiteShell` takes `children` and nothing else. No catalog data reaches the shell today.

### 2.3 The API-backed siblings

The taxonomy is already read from the API by every other consumer:

| Consumer | File | Freshness |
| --- | --- | --- |
| Filter options (`/products`, `/search`) | `frontend/web/lib/category/options.ts:6-21` | `cache: "no-store"`, pages through all results |
| Homepage room tiles | `frontend/web/lib/homepage/catalog.ts:22-24` | `cache: "no-store"`, then `.slice(0, 4)` |
| Sitemap | `frontend/web/lib/seo/crawl.ts:33,37,80-81` | via `getCategoryFilterOptions` |
| Category detail page | `frontend/web/lib/category/catalog.ts` | `cache: "no-store"` |
| Catalog detail preflight (404 masking) | `frontend/web/proxy.ts:12-25` | per-request |

The fixture's own stated precondition ("until catalog-backed global navigation is introduced") is
therefore satisfied in practice: the fixture is now the only taxonomy consumer that does not read the API.

### 2.4 Rendering modes today

- `app/page.tsx:17` — `force-dynamic`
- `app/sitemap.ts:4`, `app/robots.ts:4` — `force-dynamic`
- `categories/[slug]`, `products`, `products/[slug]`, `search`, `furniture-requests` — already dynamic
  (they fetch with `cache: "no-store"`)
- `about`, `contact`, `privacy-policy`, `terms-of-service`, `sign-in`, `sign-up` — **no catalog fetch;
  these are the routes whose rendering mode a shell-level read would change**

## 3. Why this is a defect rather than a preference

1. **Operator-created categories are invisible in the three primary discovery surfaces.** An operator can
   create a category in `/categories` and it appears in the filter dropdown and the sitemap — but never in
   the header, drawer or footer. The storefront contradicts itself.
2. **Renames and re-slugs break navigation silently.** Editing a category's slug in the admin leaves the
   fixture pointing at the old slug, so the header link becomes `/categories/{stale-slug}` → 404. Nothing
   detects this: no build step, no test, no runtime check. `frontend/web/AGENTS.md` requires that
   *"Product and category link slugs always come from Laravel, never from local slugification or machine IDs"* —
   the fixture is a local copy of Laravel slugs.
3. **The facade is hidden.** The header advertises six categories whether the catalog has six or six
   hundred, with no indication that the list is not live.
4. It is the exact pattern `frontend/web/AGENTS.md` prohibits: *"do not duplicate the Laravel taxonomy into
   frontend-only production navigation."*

## 4. Target design

### 4.1 One new read

Add `getNavigationCategories()` to `frontend/web/lib/category/` (same module family as
`getCategoryFilterOptions`), honouring the house rules:

- reads the public `GET /categories` through `lib/api/client.ts` — no raw `fetch`, no second client;
- **follows the endpoint's pagination** (`meta.pagination`, with `page`/`per_page` per the contract) and collects the **complete active top-level category collection** before returning. A single un-paginated read would silently truncate navigation to the first page, which is the same class of bug as the stale fixture this design removes;
- filters to active, top-level categories only, so a hidden or nested category never becomes a header link;
- wrapped in React `cache()` for request-local deduplication;
- reuses the existing `HOMEPAGE_DATA_SOURCE` switch so preview mode stays coherent — it reads the switch
  only through `lib/catalog/data-source.ts` (`resolveCatalogSource()`, §4.7) and returns
  `HOMEPAGE_CATEGORY_FIXTURES` when that resolves to `fixtures`;
- returns a plain `readonly CategorySummary[]` once every page has been collected.

Fetch policy is §6.

### 4.2 Injection, not client fetching

```
app/layout.tsx (unchanged, sync)
  └── SiteShell                     → becomes `async`, awaits getNavigationCategories()
        ├── SiteHeader          categories={…}
        │     └── MobileNavigation  categories={…}   ← props, no client fetch
        ├── PrimaryCategoryNavigation categories={…}
        └── SiteFooter              categories={…}
```

`MobileNavigation` stays the only client boundary and merely receives data as props. This preserves
*"Keep the shell server-first… do not move the whole header/footer client-side"*. No client-side fetch,
no token, no `useEffect` data load, no cache library.

### 4.3 Failure policy — the shell must never throw

The shell wraps **every** route. An unhandled rejection in `getNavigationCategories()` would take down the
entire site, including the legal pages that share the same layout. Therefore:

- `getNavigationCategories()` catches and never propagates; on failure it returns `[]` and records a
  server-side log entry with the request id;
- each surface renders a deliberate degraded state when the list is empty (omit the category row; keep a
  single canonical link to `/products` so the layout does not collapse). The footer and drawer already
  tolerate an empty list structurally — this must be asserted, not assumed;
- this is navigation chrome, not a content surface: omitting it must never render a page-level error
  boundary, and must never be collapsed into the generic `app/error.tsx` path
  (`frontend/web/AGENTS.md` — failure classes stay distinct).

### 4.4 Desktop overflow policy

`primary-category-navigation.tsx` lays its items out with `flexWrap: "nowrap"`. It currently fits six
items. Rendering an unbounded list would overflow the row and break the layout at every breakpoint.
The desktop row therefore needs an explicit rule before it can consume a live list — for example
render the first *N* categories and append a canonical "All furniture" affordance, or move surplus
categories behind a menu. **N and the affordance are an owner decision (§9);** the mobile drawer and
footer can list all returned categories, since both already scroll or stack.

### 4.5 Homepage room-tile policy (F15)

`lib/homepage/catalog.ts:24` currently returns `categories.data.slice(0, 4)`. That is an undocumented
editorial rule: "the first four categories in `display_order`". Since a newly created category is appended
last (`CreateCategory.php:36` — `max(display_order) + 1`), it can never appear on the homepage while four
categories exist.

Resolve it deliberately, in one of two ways:

- **Curated top-N** — keep four tiles, but make the selection rule explicit and documented (and, if the
  business wants real curation, stop deriving it from insertion order); or
- **Live list** — render all categories, with a "browse all" affordance.

Either way the current behaviour ("first four by insertion order, silently") should not survive as an
unstated policy. This is the same class of decision as §4.4.

### 4.6 Fixture retirement

Delete `components/layout/category-navigation.fixture.ts`. Keep `lib/homepage/fixtures.ts`'s
`HOMEPAGE_CATEGORY_FIXTURES` — it is the sanctioned `HOMEPAGE_DATA_SOURCE=fixtures` preview source and is
used by existing tests. In preview mode the navigation renders those same fixture categories through
`getNavigationCategories()`, so exactly one fixture survives and the whole preview stays coherent.

The switch is **retained as a development-only preview affordance** and is not removed here. The one
fixture module and the one import rule that make it safe are fixed in §4.7, shared verbatim with
`docs/plan-remove-hardcoded-demo-fixtures.md` Phase 5.1 so the two plans cannot drift.

### 4.7 The preview switch — one module, one import rule

Source location and import strategy, agreed with `docs/plan-remove-hardcoded-demo-fixtures.md` Phase 5.1:

- **One fixture module:** `frontend/web/lib/homepage/fixtures.ts` is the only fixture module (eleven
  importers today — seven runtime, four test). There is **no** `lib/test-utils/catalog-fixtures.ts` and no
  second copy; the existing path is kept to avoid churn.
- **One switch reader:** `frontend/web/lib/catalog/data-source.ts` is the **only** module that reads
  `process.env.HOMEPAGE_DATA_SOURCE`. It exposes `resolveCatalogSource(): "api" | "fixtures"` and
  `catalogFixturesEnabled(): boolean`, and throws on an unrecognised value.
- **Production exclusion is fail-closed:** `resolveCatalogSource()` returns `"api"` unconditionally when
  `process.env.NODE_ENV === "production"`, so a deployed environment with the variable mistakenly set still
  cannot serve fixtures. This excludes fixtures from production **routes** behaviourally; it is not a
  bundle split, and bundle-level tree-shaking is out of scope.
- **Import rule:** only the data readers that need fixture *data* — `lib/homepage/catalog.ts`,
  `lib/category/options.ts`, `lib/category/catalog.ts`, `lib/products/catalog.ts`, `lib/products/detail.ts`,
  `lib/seo/crawl.ts` — import `lib/homepage/fixtures.ts`, and only behind the `resolveCatalogSource()`
  branch. `proxy.ts` imports only `catalogFixturesEnabled()`, never the fixtures, so fixture payloads never
  enter the edge/middleware bundle.
- **Ownership:** the accessor is a `frontend/web` code change delivered by the fixture-removal plan's
  Phase 5.1. This plan only depends on the contract; `getNavigationCategories()` calls
  `resolveCatalogSource()` and never imports the fixtures directly.

## 5. Constraints this plan honours

- **Frozen contract:** only the existing public `GET /categories` is consumed. No new endpoint, field,
  permission or enum; no change under `docs/api/`.
- **Admin scoping:** F14/F15 need no change in `frontend/admin`. F10 remains admin-only.
- **No new dependencies.**
- **Preview switch:** `HOMEPAGE_DATA_SOURCE=fixtures` is retained as a development-only preview affordance
  and is excluded from production by the fail-closed accessor in §4.7 — no removal, and no second fixture
  module.
- **Design system:** no new token, colour, spacing, radius or breakpoint is required.
- **Transport:** `lib/api/client.ts` already accepts `next: { revalidate, tags }` and applies it only on
  the server (`client.ts:51-54`, `:233-235`), so §6 needs **no** transport change.

## 6. The one architectural trade-off (needs a decision)

The root layout is currently static. Adding a catalog read to the shell makes that read part of every
route's render. Two options:

**Option A — uncached (`cache: "no-store"`).**
Always fresh; the navigation reflects an admin edit immediately.
Cost: `about`, `contact`, `privacy-policy`, `terms-of-service`, `sign-in` and `sign-up` move from static to
dynamic rendering. (The catalog routes are already dynamic, so they are unaffected.) This is the simplest
option to reason about and the least likely to surprise an operator.

**Option B — cached read (`next: { revalidate: N }`).**
Keeps the currently-static routes static (ISR), ahead of the SEO/performance principles and the shell's
"server-first" guidance.
Cost: the navigation is stale for up to `N` seconds. `N` must **not** be invented —
`frontend/web/AGENTS.md` requires an *"approved catalog-freshness policy"* before any revalidation
duration is chosen, and the API's own advertised freshness is the only published anchor available
(`Cache-Control: public, max-age=300, s-maxage=600`, `CDN-Cache-Control: public, max-age=600`).

**Additional limitation for Option B:** the admin is a separate Next application, so it cannot invalidate
the website's cache. There is no revalidation channel between `frontend/admin` and `frontend/web` today.
Until one exists, Option B means a category edit is invisible in the navigation for up to `N` seconds —
which, for the surface that caused F14 in the first place, partially reintroduces the same complaint in a
milder form.

**Recommendation:** default to **Option A** for this release. The affected routes are legal and
informational, the storefront is not traffic-shaping yet, and freshness is what the operator will
actually expect after creating a category. Choose Option B only if the owner wants static legal pages
badly enough to accept a stale navigation window, and only after recording the approved `N`.

## 7. Tests

No existing test renders the navigation, so the work is mostly additive. Required:

1. The three surfaces render exactly the categories returned by the injected data source.
2. `getNavigationCategories()` never throws — a failed, malformed or empty API response yields `[]` and
   the shell still renders the page (assert the fallback, not just the happy path).
3. Preview mode (`HOMEPAGE_DATA_SOURCE=fixtures`) renders `HOMEPAGE_CATEGORY_FIXTURES`.
4. Slug fidelity: rendered `href`s are exactly the API slugs, with no local slugification.
5. Empty-list behaviour for each surface (drawer, footer, desktop row) renders the degraded state and not
   an error boundary.
6. A guard that `category-navigation.fixture.ts` no longer exists / is no longer imported, so the
   duplication cannot quietly return.
7. Desktop row respects whatever cap §4.4 chooses.

Existing tests to re-run for regressions: `lib/catalog/internal-links.test.ts`,
`lib/category/category.test.ts`, `lib/seo/*`, `lib/homepage/homepage.test.ts`.

## 8. Risks

| Risk | Mitigation |
| --- | --- |
| A shell-level failure takes down the whole site | §4.3 — the read never throws; degrade to omission |
| Site-wide dynamic rendering surprises the owner | §6 — decide explicitly; Option A is the default and is stated |
| Desktop row overflows with a live list | §4.4 — cap and affordance decided before the list is wired in |
| Navigation flickers or shifts while data loads | Static/ISR rendering keeps the list in the initial HTML; no client fetch (Option A also avoids CLS) |
| Editorially surprising list (all 76 categories in the header) | §9 — which categories belong in global navigation is a business decision |

## 9. Decisions (owner)

1. **Which categories belong in global navigation — DECIDED (2026-10-16).** Whatever the authoritative
   Laravel backend API supplies: all active top-level categories returned by `GET /api/v1/categories`.
   Not a hand-maintained six, and not a curated subset. To be reconciled at the end of Phase Group S.
   (This is the launch decision recorded against F1/F2/F3 and F14 in the findings register.)
2. Which of §6's options: A (always fresh, site-wide dynamic) or B (cached, needs an approved `N`)? — **OPEN**; the plan recommends A.
3. If Option B: what is the approved catalog-freshness window? — **OPEN**.
4. §4.4: the desktop cap and the surplus affordance. — **OPEN** (the set is now API-supplied, so an
   explicit cap is still required before a live list can reach `flexWrap: "nowrap"`).
5. §4.5: curated top-N or live list for the homepage tiles. — **OPEN**.

## 10. Out of scope

- Any hierarchy-aware listing. Navigation can only ever show what the read returns — today that is the
  active top-level categories. Deeper navigation follows **F1** (`/api/v2/categories`), and this plan must
  not be used as a reason to widen the frozen `/api/v1/categories`.
- Removing the `HOMEPAGE_DATA_SOURCE=fixtures` preview switch. The switch is **retained** as a
  development-only affordance and excluded from production by the fail-closed guard in §4.7; its latent
  risk (a deployed environment rendering fixtures across the storefront) is closed by that guard, not by
  deleting the variable. The seven readers that must route through the accessor are listed in §4.7.
- F10 jargon, F1 hierarchy, F3 sitemap coverage.

## 11. Exit condition

The plan is complete when:

- the three surfaces render API-sourced categories and `category-navigation.fixture.ts` is gone;
- a failed catalog read degrades the navigation without failing any route;
- the four rendering/freshness decisions in §9 are recorded, and the chosen behaviour is asserted by tests;
- the six routes listed in §2.4 have their rendering mode deliberately confirmed (not accidentally changed);
- `frontend/web` gates pass: typecheck, lint, tests, build.

## 12. Rollback

The change is confined to `frontend/web/components/layout/*`, one new lib module, and tests. Nothing is
persisted, no contract changes, no migration. Reverting the commit restores the fixture behaviour exactly.
