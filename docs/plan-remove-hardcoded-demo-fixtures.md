# Plan: Transition from Demo Fixtures to Laravel API Single Source of Truth

## 1. Context & Objectives

The Furniture Commerce platform is architected with a strict **API-first principle**:
- **Laravel API is the sole authority** for all business rules, validation, catalog taxonomy, products, inventory, and media metadata.
- **MySQL is the single source of persistent truth**.
- **Next.js Web (`frontend/web`) and Flutter App (`frontend/app`)** are client frontends that consume the shared API endpoints.

During earlier bootstrap and design phases, local fixtures and disposable demo datasets were created:
1. **Frontend Web Fixtures (`HOMEPAGE_DATA_SOURCE=fixtures`)**: In `frontend/web/lib/homepage/fixtures.ts`, providing mock products (`fixture-open-frame-armchair`, etc.), mock categories, and mock media paths (`/furnitures/fixtures/...`). This module is **retained** as a development-only preview source (Phase 5.1); only its runtime entry points are consolidated and guarded.
2. **Frontend App Fixtures (`CATALOG_DATA_SOURCE=fixtures`)**: In `frontend/app/lib/features/**/fixture_*.dart`, mirroring the web fixtures.
3. **Hardcoded Homepage Media**: Static banner images in `frontend/web/app/page.tsx` pointing to local fixture assets (`/furnitures/fixtures/hero-image/hero-image.jpg`).
4. **Backend Demo Seeders (`CatalogSeeder.php`, `CommerceDemoSeeder.php`)**: Populating mock products with dummy storage file paths (`products/dev/nordic-sofa/main.webp`) and mock orders/carts using the R2 public development URL (`https://pub-20ad593372b04704ae5e74f0c4bda810.r2.dev`, rate-limited and development-only).

This plan details how to systematically transition to genuine API-driven data and clean up disposable demo artifacts without breaking API contracts, test suites, or production readiness.

---

## 2. Core Constraints & Guarantees

1. **V1 API Contract Freeze (Phase 1.35)**: Do not alter public API endpoints (`GET /api/v1/products`, `GET /api/v1/categories`, `GET /api/v1/media`, etc.), query parameters, or response contracts documented in `docs/api/`.
2. **Database Protection Guard**: Never run destructive migration commands (`migrate:fresh`, `db:wipe`) on development or production databases. Production databases only run canonical reference seeders (`DatabaseSeeder`), never demo seeders.
3. **Test Integrity**: Unit and integration test suites that rely on static fixture shapes must continue to pass (or be repointed to the shared fixture module through the guarded accessor).
4. **Resilient Media Rendering**: The frontend must gracefully handle missing product images (render "Photograph to follow") and correctly permit production media domains (`CATALOG_MEDIA_BASE_URL` / Cloudflare R2 / S3 CDN) in `next.config.ts`.

---

## 3. Step-by-Step Transition Plan

```
Phase 1: Backend Storage & Media Alignment
    │
    ▼
Phase 2: Database Catalog Baseline (Admin/Staff Ingestion)
    │
    ▼
Phase 3: Next.js Frontend Decoupling & Hero Media API Migration
    │
    ▼
Phase 4: Flutter Mobile App Transition to Exclusive API Repositories
    │
    ▼
Phase 5: Fixture Consolidation, Guarding & Verification
```

---

### Phase 1: Backend Storage & Media Domain Alignment

Ensure the backend serves valid, real media URLs that clients are authorized to load.

1. **Align Environment Storage Configuration**:
   - In `backend/laravel/.env`:
     - If using local storage: configure `FILESYSTEM_DISK=public` and `APP_URL=http://127.0.0.1:8000`.
     - If using Cloudflare R2 / S3: verify `R2_PUBLIC_BASE_URL` points to the real public bucket CDN domain (e.g. `https://media.yourdomain.com`). The current value is Cloudflare R2's **public development URL** (`*.r2.dev`), which is development-only and **rate-limited**; it must be replaced with a real CDN/custom domain before production.
2. **Configure Next.js Remote Patterns (`next.config.ts`)**:
   - Update `frontend/web/next.config.ts` to allow both the local development API origin and the remote CDN origin:
     ```ts
     const catalogMediaBaseUrl = process.env.CATALOG_MEDIA_BASE_URL ?? process.env.API_BASE_URL;
     // Validate remotePatterns to accept valid URLs and prevent unconfigured host crashes
     ```
   - Set `CATALOG_MEDIA_BASE_URL` in `frontend/web/.env.local` to match the backend's real public media URL.

---

### Phase 2: Database Catalog Baseline & Admin Production Data

Replace synthetic disposable demo data with real business catalog entries in Laravel.

1. **Verify Canonical Category Taxonomy (`CategorySeeder.php`)**:
   - `CategorySeeder.php` is already canonical reference data (`DatabaseSeeder.php` calls it). Ensure all real top-level spaces and child categories (Living Room, Sofas, Beds, etc.) match business requirements.
2. **Deprecate `CatalogSeeder.php` & `DemoSeeder.php` in Production**:
   - Keep `CatalogSeeder.php` strictly isolated under `DemoSeeder.php` for local developer testing.
   - Guard `CatalogSeeder`/`DemoSeeder` with an **allow-list of environments**, not a production deny-list: `app()->environment(['local', 'testing'])`. A `APP_ENV !== 'production'` check would still permit demo seeding on `staging` (a real environment per `AGENTS.md §22`) and on any mistyped or preview environment. This matches the guard `DevelopmentUserSeeder` already uses, and follows the `AGENTS.md §11` rule that `APP_ENV` alone must never be used to infer a database is disposable.
3. **Seed or Ingest Real Products via Admin API**:
   - Ingest genuine furniture products using **only** the fields the admin product write contract accepts (`ProductFormRequest::ALLOWED_FIELDS`): `name`, `slug`, `description`, `product_type`, `price`, `category_id`, `is_active`, `is_published`. Note the field is **`name`, not `title`**; unknown fields are rejected, so a `title`, `dimensions` or `materials` payload would fail validation.
   - Ensure products specify their proper type (`MADE_TO_ORDER` or `IN_STOCK` per Initial Production Commerce Mode rules in `AGENTS.md`).
   - **Deferred to their owning phases — not part of this ingestion step:** product photography is uploaded separately through the media endpoints (Phase 19.8), and structured dimensions/materials are catalog attributes with no write contract in V1, so they must not be smuggled into the product payload.
4. **Purge only the identified seeded demand rows (exact list)** — **DONE (2026-10-16)**:
   - Rationale: after the 2026-10-16 account cleanup the demand tables held a **mix** of seeded demo rows and legitimate no-login submissions. `user_id IS NULL` is **not** a safe delete predicate, because the public API accepts anonymous enquiries, furniture requests and guest carts by design.
   - **Removed (2026-10-16):** `enquiries` ids `1, 2, 3, 4` and `furniture_requests` ids `1, 2, 3` (faker identities, all created `2026-10-13 15:15:54`). Deleted under the standard guard (`environment !== 'production'` **and** database `furnitureapp`) inside one transaction, with a signature assertion that every targeted id matched the seeded `created_at` + `user_id IS NULL` pattern and had no attachments. 7 rows removed (4 enquiries + 3 requests). Counts: `enquiries` 6 → 2, `furniture_requests` 7 → 4.
   - **Preserved (verified still present after the purge):** `furniture_requests` `4` (anonymous email submission, contact redacted), `5` (`David`), `6` (`donny`, owned by `user 4`), `7` (`Operational notification email test`); `enquiries` `5` (`David`, owned by `user 4`), `6` (`Phase 18.6 smoke test`).

---

### Phase 3: Next.js Frontend Web Decoupling

Shift `frontend/web` to consume 100% live API data.

1. **Enforce `HOMEPAGE_DATA_SOURCE=api`**:
   - Verify `frontend/web/.env.local` defaults to `HOMEPAGE_DATA_SOURCE=api`.
   - Production exclusion of fixtures is enforced by `resolveCatalogSource()` (Phase 5.1), not by deleting the variable — preview mode stays available.
   - Update `app/page.tsx` notice text to remove the "Design preview — development fixtures" text in standard operational mode.
2. **Migrate Homepage Hero & Editorial Media to Dynamic/Configurable Sources**:
   - Currently, `HOMEPAGE_MEDIA.hero` and `HOMEPAGE_MEDIA.editorial` in `app/page.tsx` are hardcoded file paths in `lib/homepage/fixtures.ts`.
   - **Step**:
     - Either allow editorial and hero banner assets to be served from real catalog featured items returned by `getHomepageCatalog()` — this uses only the already-approved public catalog contract (`CAT-001`/`CAT-002`, including the optional category banner image) and is the preferred option.
     - **Do not introduce a CMS/settings or homepage endpoint in this phase.** No such endpoint exists in `docs/api/openapi.yaml` or `routes/api.php`, and the V1 contract is frozen, so adding one would bypass the post-freeze contract-change process. If a dedicated settings resource is genuinely required, it must first go through that process and carry a compatible versioned API design; until then the options are the approved catalog data above or the static fallback below.
     - For production fallback, ensure static public images exist under `frontend/web/public/images/` without fixture dependencies.
3. **Decouple Search & Collection Pages from Fixture Fallbacks**:
   - In `frontend/web/app/search/page.tsx` and `app/products/page.tsx`:
     - When products array is empty, always display the empty state: `<Typography>No furniture matches these filters.</Typography>`.
     - Remove any design preview banners or fallback branches.

---

### Phase 4: Flutter Mobile App Transition to Exclusive API Repositories

Ensure the Flutter app only talks to the Laravel REST API.

1. **Deprecate `CATALOG_DATA_SOURCE=fixtures`**:
   - In `frontend/app/lib/app.dart`, ensure production builds exclusively construct `ApiCatalogRepository`, `ApiCategoryRepository`, and `ApiProductDetailRepository`.
   - Make `CATALOG_DATA_SOURCE=api` the required setting for non-debug builds.
2. **Dynamic Home Screen Hero**:
   - In `frontend/app/lib/features/home/presentation/home_screen.dart`, ensure `showFixtureHero` is disabled and categories/products stream directly from the API providers.
3. **Image Handling & Fallbacks**:
   - Verify network image components handle `404` or slow network responses smoothly by rendering placeholder icons or empty containers without runtime app crashes.

---

### Phase 5: Fixture Consolidation, Guarding & Clean-up

Safely guard, consolidate and decommission fixture code without breaking regression tests.

1. **Consolidate the fixture module behind one guarded accessor** (replaces the earlier "move to test directories"):
   - In `frontend/web`:
     - **Keep** `lib/homepage/fixtures.ts` at its current path as the single shared fixture module. Do **not** move it to `lib/test-utils/catalog-fixtures.ts`: `HOMEPAGE_DATA_SOURCE=fixtures` stays a supported **development-only preview** affordance, so the fixtures must remain reachable at runtime, not only from `node:test`. The module location and import strategy below are shared verbatim with `docs/plan-catalog-backed-navigation.md` §4.6–§4.7.
     - Add `lib/catalog/data-source.ts` as the **only** module that reads `process.env.HOMEPAGE_DATA_SOURCE`. It exposes `resolveCatalogSource(): "api" | "fixtures"` and `catalogFixturesEnabled(): boolean`, **fails closed** (returns `"api"` unconditionally when `process.env.NODE_ENV === "production"`, so a misconfigured deploy can never render fixtures), and throws on an unrecognised value. This guard — not deletion — is what excludes fixtures from production **routes**; it is behavioural, not a bundle split.
     - Repoint the six switch-reading data readers to the accessor, keeping their fixture imports behind its branch: `lib/homepage/catalog.ts`, `lib/category/options.ts`, `lib/category/catalog.ts`, `lib/products/catalog.ts`, `lib/products/detail.ts`, `lib/seo/crawl.ts`. `proxy.ts` imports only `catalogFixturesEnabled()` (never the fixtures), so fixture payloads stay out of the edge/middleware bundle.
     - Delete the preview branches and banners no approved route still consumes — `app/products/page.tsx` and `app/search/page.tsx` keep only the API path (their preview banners go in Phase 3.3). `app/page.tsx`'s `HOMEPAGE_MEDIA` use is removed by Phase 3.2, not here. `lib/products/catalog.ts` **keeps** its fixture branch behind the accessor, because the preview contract requires it; the earlier blanket "remove all runtime references" wording is superseded.
   - In `frontend/app`:
     - Move `fixture_*.dart` files under `test/features/**` or isolate behind conditional compilation / debug-only flags so they are never bundled into release APKs / IPAs.
2. **Remove Unused Fixture Assets**:
   - Once real media assets are uploaded to Cloudflare R2 / S3 storage:
     - Audit `frontend/web/public/furnitures/fixtures/` and remove stale fixture images (`*.jpg~`, `widearmchair.jpg~`).
3. **Verification & Quality Gate**:
   - **Backend**: Run `./vendor/bin/phpunit` across all feature and unit tests.
   - **Web**: Run `npm test` and `npm run build` in `frontend/web` to confirm zero static type errors, zero Turbopack build errors, and green SEO/performance tests.
   - **Mobile**: Run `flutter test` and `flutter analyze` in `frontend/app`.
   - **End-to-End Search Smoke Test**: Search for existing and non-existing products on the web frontend to confirm clean empty search results ("No furniture matches these filters") with zero unconfigured host image crashes.

---

## 4. Execution Order and Preconditions

### 4.1 Current state (verified 2026-10-16)

Laravel is **already** the sole *effective* runtime authority: no environment sets the fixture switch, so every client reads the API by default. The switch itself is retained as a development-only preview affordance and excluded from production by the fail-closed guard added in Phase 5.1:

| Switch | Default | Overridden in env? | State |
| :--- | :--- | :--- | :--- |
| `HOMEPAGE_DATA_SOURCE` (`frontend/web/lib/homepage/catalog.ts`) | `api` | No | API-only |
| `CATALOG_DATA_SOURCE` (`frontend/app/lib/config/app_config.dart`) | `api` | No | API-only |
| `frontend/admin` | no fixture references at all | — | API-only |

Phase 3 item 1 is therefore already satisfied in effect. What remains is removing demo **data**, routing every fixture read through the one guarded accessor, and deleting genuinely dead code — not switching sources.

Residual data: `products` 4 (3 `IN_STOCK`, 1 `MADE_TO_ORDER`), `product_images` 4 (all `products/dev/...` dummy paths), `carts` 1, `enquiries` 2, `furniture_requests` 4, `notifications` 5. `orders`/`order_items` are already 0. `R2_PUBLIC_BASE_URL` is now set to the Cloudflare R2 **public development URL** (`https://pub-20ad593372b04704ae5e74f0c4bda810.r2.dev`) — development-only and rate-limited, so Phase 1 must still confirm a real public bucket/CDN domain before production.

### 4.2 Hard orderings — these constrain the sequence

1. **The shared accessor must land before the readers are repointed; the fixture module itself is retained.** `lib/homepage/fixtures.ts` is no longer deleted (Phase 5.1), so the old deletion hazard is gone. The remaining constraint: add `lib/catalog/data-source.ts` first, then repoint readers and tests. Five test files set or pin the switch — `lib/homepage/homepage.test.ts`, `lib/search/search.test.ts`, `lib/seo/crawl.test.ts`, `lib/auth/auth.test.ts`, and `lib/category/category.test.ts` (which asserts `proxy.ts` literally contains `HOMEPAGE_DATA_SOURCE === "fixtures"`) — plus `lib/test-utils/frontend-test-helpers.ts`. That literal assertion must be updated when `proxy.ts` moves to `catalogFixturesEnabled()`, or the web suite breaks.
2. **Phase 1 media alignment must precede the final purge.** Every product image points at a dummy `products/dev/...` path, so the storefront has no usable photography; `R2_PUBLIC_BASE_URL` is now set but must be confirmed to resolve to the real public bucket/CDN domain. Purging demo data before real assets are uploaded leaves published products with broken images.
3. **Product ingestion cannot run through the admin until Phase 19.6.** Phase 2 item 3 assumes an "Admin API" surface; the admin product screen does not exist until 19.6 ships. Because 19.6 lands inside Group S, end-of-Group-S timing is safe — but ingestion must not be attempted through the admin earlier.
4. **The seeder allow-list guard (Phase 2 item 2) is a code change**, not documentation. `DemoSeeder` currently refuses only `production`, so it would still seed demo catalogue and commerce rows on `staging` or a mistyped `APP_ENV`. Tighten it to `app()->environment(['local', 'testing'])` — matching `DevelopmentUserSeeder` — before the demo seeders are relied upon as "local-only".

### 4.3 Open decision for the owner

**`IN_STOCK` products vs. the request-only release.** `AGENTS.md` states the first production release publishes only `MADE_TO_ORDER`, yet 3 of the 4 seeded products are `IN_STOCK` and published, which advertises purchasable items with no checkout behind them. Decide before launch whether to flip them to `MADE_TO_ORDER` or unpublish them. This is a business decision and is not resolved here.

### 4.4 Recommended sequence

```
Phase 2.2 (tighter DemoSeeder allow-list guard)
    -> Phase 2.3 (ingest real products; needs 19.6 for the admin surface)
    -> Phase 1   (real media uploaded, R2_PUBLIC_BASE_URL set)
    -> Phase 3.2 (hero/editorial media moved to approved catalog sources)
    -> Phase 4   (Flutter fixture repositories isolated)
    -> Phase 5.1 (add the fail-closed accessor; repoint readers and switch-setting tests)
    -> Phase 5.2 (delete the layout fixture + unused fixture assets, purge remaining demo rows)   <-- lib/homepage/fixtures.ts is retained
```

---

## 5. Summary of File Touches

| Component | Files Involved | Action |
| :--- | :--- | :--- |
| **Backend** | `backend/laravel/.env`, `CatalogSeeder.php`, `DatabaseSeeder.php` | Ensure real `R2_PUBLIC_BASE_URL`, protect demo seeders from non-dev execution. |
| **Web Config** | `frontend/web/.env.local`, `next.config.ts` | Configure `CATALOG_MEDIA_BASE_URL` and remote image patterns for real storage domains. |
| **Web Runtime** | `frontend/web/app/page.tsx`, `app/search/page.tsx`, `lib/catalog/data-source.ts`, `lib/products/catalog.ts` | Route every fixture read through the fail-closed accessor so production never serves fixtures; remove preview banners. |
| **Web Fixtures** | `frontend/web/lib/homepage/fixtures.ts` (single retained module) | Keep as the one shared preview source; no second copy, no move to `lib/test-utils/`. |
| **Web Tests** | `frontend/web/lib/{homepage,search,category,auth,seo}/*.test.ts`, `lib/test-utils/frontend-test-helpers.ts` | Repoint the switch-setting tests and the `proxy.ts` literal assertion to the accessor. |
| **Mobile App** | `frontend/app/lib/app.dart`, `home_screen.dart`, `app_config.dart` | Default and enforce API repositories; isolate fixture repositories to test suite. |
