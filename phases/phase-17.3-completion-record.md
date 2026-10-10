# Phase 17.3 — Flutter Product Details — Completion Record

**Project:** SL Furnitures — Flutter Android Customer Application
**Group:** Q — Flutter Customer Features
**Prerequisites:** Group P complete; Phases 17.1 and 17.2 implemented
**Status:** IMPLEMENTED AND VERIFIED

## 1. Repository inspection findings

| Finding | Result |
| --- | --- |
| Product-detail route | `/products/:productId`, registered in `lib/navigation/app_router.dart`; the phase replaced the `_resourcePlaceholder` builder only. |
| Product summary model (17.1) | `catalog/data/product_summary.dart` owns `Money`, `ProductCategorySummary`, `ProductImageSummary`, `ProductSummary`, and the private JSON helpers. |
| `CatalogRepository` | `catalog/data/catalog_repository.dart`; `ApiCatalogRepository` and `FixtureCatalogRepository`. |
| Fixture products | Were private to `FixtureCatalogRepository`; moved verbatim to `catalog/data/fixture_products.dart` so product detail can share them. |
| `CatalogImage` | `catalog/presentation/catalog_image.dart` — one shared loader with loading feedback, neutral fallback, and an optional `assetPath` for fixtures. |
| Money formatting | `formatTzs(Money)` in `catalog/presentation/product_card.dart` — integer minor units, no floating point. Reused unchanged. |
| Media / spacing tokens | `AppMedia.productHero` (`--media-product-hero`), `AppSpacing.gutterPhone/space*/breakpointTablet`. No new token was needed. |
| CAT-002 response | Confirmed against `docs/api/api-resources.md §1.1/§2a.2/§2b`, `docs/api/api-contract.md §21.3`, `openapi.yaml`, and the Laravel `ProductDetailResource` / `ProductSummaryResource` / `ProductImageResource` / `ProductVariantSummaryResource` chain. |
| Laravel image ordering | `Product::images()` relation orders `sort_order ASC, id ASC`; the app preserves the received order and never re-sorts. |
| Router guards | `RouteGuard` protects `/account` only; `/products/*` was already public and stays public. |

## 2. Exact CAT-002 fields used

`id`, `name`, `slug`, `product_type`, `price{amount,currency}`, `category{id,slug,name,description}`,
`primary_image{id,url,alt_text}` (inherited summary field), `images[]{id,url,alt_text,sort_order,is_primary}`,
`variants[]{id,sku,name,price,availability,stock_indicator}`, `availability`, `stock_indicator`,
`description`, `created_at`, `updated_at`.

No field outside that list is read, and no field inside it is invented.

## 3. Architecture

```text
ProductDetailScreen
        |
        v
ProductDetailController
        |
        v
ProductDetailRepository  ->  existing ApiClient  ->  Laravel CAT-002
```

`ApiProductDetailRepository` reuses the single `ApiClient` in public auth mode. No second HTTP
client, no global state package, no extra layer, and no new fixture setting.

## 4. Files added

```text
lib/features/catalog/data/fixture_products.dart                       (shared fixture summaries)
lib/features/product_detail/data/product_detail.dart                   (ProductDetail, ProductDetailCategory, ProductDetailImage, ProductVariantSummary)
lib/features/product_detail/data/product_detail_repository.dart        (interface + ApiProductDetailRepository)
lib/features/product_detail/data/fixture_product_detail_repository.dart
lib/features/product_detail/presentation/product_detail_controller.dart
lib/features/product_detail/presentation/product_detail_screen.dart
lib/features/product_detail/presentation/product_labels.dart
lib/features/product_detail/presentation/widgets/product_gallery.dart
lib/features/product_detail/presentation/widgets/product_information.dart
lib/features/product_detail/presentation/widgets/product_variant_selector.dart
lib/features/product_detail/presentation/widgets/product_request_action.dart
test/support/cat002_payload.dart
test/features/product_detail/product_detail_models_test.dart
test/features/product_detail/product_detail_repository_test.dart
test/features/product_detail/product_detail_controller_test.dart
test/features/product_detail/product_gallery_test.dart
test/features/product_detail/product_detail_screen_test.dart
test/features/product_detail/product_detail_fixtures_test.dart
```

## 5. Files modified

`lib/app.dart`, `lib/navigation/app_router.dart`,
`lib/features/catalog/data/fixture_catalog_repository.dart`,
`lib/features/catalog/presentation/product_grid.dart`,
`lib/features/home/presentation/home_screen.dart`, `README.md`,
`lib/features/README.md`, `test/app_test.dart`, `test/navigation/app_router_test.dart`,
`test/architecture/feature_architecture_test.dart`,
`test/features/catalog/catalog_data_test.dart` (formatting only).

## 6. Known deliberate limitations

1. **Request action was inert at phase close.** At Phase 17.3 close the
   "Request this furniture" button rendered disabled with "Requesting is not
   available in the app yet.", and no request route was registered. Phase 17.10
   replaced this: the button is enabled, the router registers
   `/furniture-requests` and `/furniture-requests/:productId`, and
   `ApiFurnitureRequestRepository` submits to Laravel `POST /requests`. The
   action still creates no cart, order, quotation, payment, or reservation.
2. **No gallery thumbnails or full-screen viewer.** Both are explicitly optional
   in the phase. Swipe navigation and a position indicator are implemented.
3. **Partly synthetic fixture detail.** The second gallery image and the two
   options per fixture product are development-only values added by this phase
   to exercise the gallery indicator and option selection locally. Documented in
   `README.md` and `lib/features/README.md`.
4. **No new diagnostic events.** Every CAT-002 failure is already recorded by
   `ApiClient`; duplicating it would widen the closed diagnostic vocabulary
   without a cross-feature need.
5. **Contract tolerance.** A `null`/blank `description` or category description
   is normalized to `null` (section omitted) and an unparseable timestamp is
   ignored, so an unused field can never fail a public page. This matches the
   Phase 17.2 `CategoryDetail` precedent; the live Laravel payload confirms
   `description` can be `null`.

## 7. Verification summary

| Check | Result |
| --- | --- |
| `flutter pub get` | OK |
| `dart format --set-exit-if-changed .` | OK (120 files, 0 changed) |
| `flutter analyze` | No issues found |
| `flutter test --concurrency=1` | 328 tests, all passed (run 3x, stable) |
| `dart run tool/generate_tokens.dart --check` | OK — generated tokens current |
| `flutter build apk --debug --dart-define-from-file=config/local.json` | Built `build/app/outputs/flutter-apk/app-debug.apk` |
| `git diff --check` (unstaged) | Clean for this work; pre-existing trailing spaces remain in `phases/group-Q-phases.md` |
| `git diff --cached --check` (staged) | Clean — nothing is staged |
| Raw colour literals outside `generated_tokens.dart` | None |
| Additional `ApiClient(` construction sites | Only `lib/main.dart` (the composition root) |
| Duplicate product parsers | None — `ProductDetail` composes the shared `ProductSummary` |
| Commerce controls in `lib/` | None — only documentation comments mention them |
| Commerce routes registered | None — guarded by a new architecture test |

## 8. Live Laravel verification (completed)

The local Laravel development database was empty, so the project's approved
development seeders were run (`DatabaseSeeder` for the taxonomy, then
`CatalogSeeder`), which are additive on an empty database. `php artisan serve`
was started on `127.0.0.1:8000`, verified, and then stopped.

Verified against `GET /api/v1/products/nordic-3-seater-sofa`:

- 200 with `Cache-Control: public, max-age=300, s-maxage=600` and an `X-Request-Id`.
- Anonymous: the request carried no `Authorization` header.
- Full detail representation: 3 images in `sort_order ASC, id ASC` with
  `is_primary` on the first, 2 embedded variants with independent minor-unit
  prices and coarse availability, category `{id, slug, name, description}`,
  `availability`/`stock_indicator`, and both timestamps.
- `description` was `null`; the shipped `ProductDetail` decoded the captured live
  payload unchanged and the screen renders no description section for it.
- `GET /api/v1/products/definitely-not-a-product` returned
  `{"errors":[{"code":"RESOURCE_NOT_FOUND", ...}],"meta":{"request_id":...}}`
  with HTTP 404.
- Only one request was issued: no `CAT-005` call and no per-variant request.
- The temporary probe and its captured-payload test were deleted afterwards.

## 9. Physical Android verification (performed)

Device: **Samsung SM-A135F, Android 12 (API 31), 1080x2408 @ 450dpi (411x917 dp logical)**,
USB debug, driven with Marionette MCP over the Dart VM service. Laravel was reached with
`adb reverse tcp:8000 tcp:8000`. `ENABLE_DIAGNOSTICS=true` was enabled so the privacy-safe
diagnostics boundary could be observed live.

### 9.1 Development fixture mode (`CATALOG_DATA_SOURCE=fixtures`)

| # | Check | Result |
| --- | --- | --- |
| 1 | Home renders (hero, Young Serif editorial, warm canvas) | Pass |
| 2 | Home → catalog → product navigation by server slug | Pass |
| 3 | Gallery renders at `--media-product-hero` with "Showing image 1 of 2" + dots | Pass |
| 4 | Horizontal swipe advances the gallery, indicator and dot move, second photograph loads | Pass |
| 5 | Option selection swaps the price, drops the `From` prefix, fills the radio, shows "Selected option: …" | Pass |
| 6 | Category link opens the Phase 17.2 category landing page | Pass |
| 7 | System back returns to the product and preserves the chosen option | Pass |
| 8 | The "Request this furniture" button was inert during this phase (no navigation, no dialog, no submission); Phase 17.10 later enabled it and registered the request route | Pass |
| 9 | 2x text scale + bold system text: no overflow, no clipped price, no clipped option name | Pass |

### 9.2 Live API mode against Laravel CAT-002

| # | Check | Result |
| --- | --- | --- |
| 10 | Real CAT-002 detail renders: 3 ordered images, 2 embedded Laravel variants (`var_1`, `var_2`), integer minor-unit prices | Pass |
| 11 | `description` is `null` server-side → the Description section is correctly omitted, not empty | Pass |
| 12 | Unreachable image URL → neutral `CatalogImage` fallback in a stable 16:9 frame, no crash | Pass |
| 13 | Real option selection → `TZS 1,295,000`, note switches to the selected-option wording | Pass |
| 14 | Product unpublished server-side (CAT-002 → 404) + pull-to-refresh → mapped inline 404, **existing product content preserved** | Pass |
| 15 | Catalog empty state after the product left the public collection | Pass |
| 16 | Laravel stopped → cold load shows "Connection problem / Check your connection and try again. / Try again" | Pass |
| 17 | Laravel restarted + "Try again" → the real product loads, no restart required | Pass |
| 18 | Whole session framework errors: 0 `RenderFlex` overflows, 0 failed assertions, 0 unhandled exceptions | Pass |

### 9.3 Defects found by device verification and fixed

| Defect | Fix | Regression test |
| --- | --- | --- |
| Fixture option price rendered `TZS 114,285.71` — integer division left stray cents | `_wholeShillings` rounds the uplift to a whole shilling | `fixture prices are whole shillings with unambiguous SKUs` |
| Fixture SKU rendered `FIXTURE-FIXTURE_ARMCHAIR-STD` (duplicated prefix) | SKU derives directly from the fixture product ID | same test |
| The note read "Indicative starting price" while a chosen option's specific price was displayed | `productPriceNote` takes `isVariantPrice` and says "Price for the selected option" | `updates the price when an option is selected` |

### 9.4 Not verified on device

- The full "This furniture is unavailable" cold-load screen. Reaching it needs navigation to an
  unpublished product, and the app has no App Links (deferred until a production domain exists).
  It is covered by the `reports a non-public product as unavailable` widget test, including the
  "Browse furniture" recovery action. The harder refresh-with-prior-content 404 path **was**
  verified on device (check 14).
- Landscape orientation on the physical device (covered by widget tests).

### 9.5 Observation outside Phase 17.3 scope

With Laravel stopped, the app correctly showed its connection-failure state, but **no
diagnostic event was recorded**. Verified with a throwaway test (since deleted): a transport
that throws yields `ApiTransportFailureKind.connection` with an **empty** diagnostics sink.

Cause: `HttpApiTransport.send` lets the raw `package:http`/`dart:io` exception propagate;
`ApiClient.request`'s final `catch (_)` constructs a *new* `ApiTransportException` and throws it
from inside the catch block, so it never reaches the sibling `on ApiTransportException` handler
that calls `_recordTransportError`. `ApiError` responses and cancellations are recorded
correctly; only connection failures are lost.

This is a pre-existing Phase 16.4/16.9 defect in `lib/core/network/api_client.dart`, not a
Phase 17.3 regression. It was **reported rather than fixed inside Phase 17.3**, because
transport classification belongs to the networking owner.

**Resolved by targeted corrective maintenance** after this phase closed. The fix routes
both the typed and the raw-to-typed transport failure paths through one gated recorder,
so a connection failure is recorded exactly once and cancellation stays silent. Ten
regression tests were added, and the original scenario was reproduced on the physical
device: with Laravel stopped the app still shows the unchanged "Connection problem /
Try again" state **and** now records exactly one `API_CONNECTION_FAILED` per failed
request, with no URL, host, token, or raw message in the event. See
`phases/group-P-phases.md §25`.

## 10. Final result

**Phase 17.3: PASS**, verified on a physical Android device against both the
development fixtures and the live Laravel CAT-002 endpoint, with the documented
optional gallery extras still outstanding. The request-action limitation recorded
above described this phase's original state and has since been replaced by
Phase 17.10.

### Environment changes made during verification

| Change | Status |
| --- | --- |
| Local development database seeded with the project's own `DatabaseSeeder` + `CatalogSeeder` (it was empty) | Left in place |
| `nordic-3-seater-sofa` set to `MADE_TO_ORDER` so the app's `product_type=MADE_TO_ORDER` catalog filter returns it | Left in place — revert with `php artisan tinker --execute='$p = \App\Models\Product::where("slug","nordic-3-seater-sofa")->firstOrFail(); $p->forceFill(["product_type" => "IN_STOCK"])->save();'` |
| `config/local-device.json` and `config/local-device-fixtures.json` created | Left in place; both are gitignored (`/config/*.json`) and contain no credentials |
| `adb reverse tcp:8000 tcp:8000` | Left in place; must be re-run after every replug |
| Temporary probe and captured-payload test | Deleted |
