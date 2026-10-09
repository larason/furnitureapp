# Phase 17.1 — Flutter Home & Catalog

> ## Phase 17.1 Amendment — Development Catalog Fixtures
Implement a development-only fixture data source for Flutter home/catalog, equivalent in purpose to the Next.js `HOMEPAGE_DATA_SOURCE=fixtures` workflow.
1. Inspect the existing Next.js homepage fixtures, product data, image assets, and fixture licensing/usage before implementing anything.
2. Introduce `CATALOG_DATA_SOURCE` with closed values `api` and `fixtures` through the existing compile-time configuration validation.
3. Permit `fixtures` only when `APP_ENV=local` and the app is running in a debug build. Reject it in staging, production, profile, and release builds. Default to `api`.
4. Implement `FixtureCatalogRepository` and `ApiCatalogRepository` behind the same repository interface. Use the same typed product summary and pagination models.
5. Reuse suitable existing Next.js fixture content and imagery. For local fixture mode, prefer bundled Flutter assets where appropriate so the homepage works without a running Laravel backend or external image host.
6. Preserve the canonical design system and realistic furniture imagery.
7. Fixture data must respect request-only commerce: `MADE_TO_ORDER` products only, with no cart, checkout, or payment controls.
8. Keep fixtures out of production runtime selection. No fallback from a failed real API request to fixtures.
9. Do not alter Laravel, the frozen API contract, or the Next.js fixture behavior.
10. Test source selection, invalid configuration, fixture image loading, pagination, empty states, and release-build restrictions.
11. Document how to launch Flutter in fixture mode and API mode, and clearly label fixture screenshots and verification results as development-only.
Example proposed local configuration:
```json
{
  "APP_ENV": "local",
  "API_BASE_URL": "http://10.0.2.2:8000",
  "CATALOG_DATA_SOURCE": "fixtures"
}
```
Preserve all existing required configuration fields and validation rules. This JSON is illustrative, not a replacement for the complete local config.
**Acceptance criterion:** A developer can launch the Flutter Android app in local fixture mode and see a complete, image-rich furniture homepage and catalog without populating the Laravel database, while staging and production always use real Laravel data.

**Project:** SL Furnitures — Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Prerequisites:** Group P (16.1–16.9) complete  
**Starting baseline:** 156 passing tests  
**Implementation mode:** One phase only; do not begin 17.2

## 1. Objective

Implement the first functional customer-facing experience in the Flutter app:

1. A polished, branded, responsive home screen.
2. Public, real-data furniture discovery.
3. A usable product catalog listing.
4. Reusable catalog summary models and product cards.
5. Correct loading, empty, failure, refresh, and pagination behavior.
6. Navigation through the existing route registry.
7. A request-first shopping experience with no purchasing controls.

The finished experience must feel like a curated furniture showroom, not an admin dashboard or a generic generated e-commerce template.

**The homepage must work with real Laravel catalog data, including when the catalog is empty.**

Do not fake product listings, category counts, discounts, reviews, or hero campaigns.

---

## 2. Mandatory repository inspection

Before writing code, read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/flutter-material3.md`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `docs/decisions.md`.
- `docs/api/api-contract.md`.
- `docs/api/api-conventions.md`.
- `docs/api/api-resources.md`.
- `docs/api/openapi.yaml`.
- `phases/group-Q-phases.md`, if present.
- Phase 17.1's detailed specification, if present.
- Flutter README and `lib/features/README.md`.
- Existing Flutter theme, routing, ApiClient, async presentation, and diagnostics implementations.
- Backend CAT-001 product list controller/resource and tests.
- Existing web catalog implementations, if available.

Use the **frozen OpenAPI contract and actual Laravel resource serialization** to establish field names, nullability, image shape, pricing, and query parameters.

The project guide lists Phase 17.1 only as "Home/catalog"; it does not prescribe exact homepage sections or a wire-level catalog DTO. Do not treat the illustrative UI composition in this prompt as a frozen API requirement.

If the detailed Phase 17.1 specification imposes additional constraints, follow it and report any conflicts before making incompatible changes.

### Required pre-implementation report

Briefly record:

- Existing route names and paths.
- Existing home and catalog placeholders.
- Actual CAT-001 response schema.
- Product summary fields.
- Image URL and alt-text fields.
- Product money representation.
- Pagination fields.
- Approved catalog query parameters.
- Existing Flutter design-token adapters.
- Available public image data.
- Whether local Laravel catalog data is currently populated.

Do not invent any missing contract detail.

---

## 3. Scope boundaries

### Implement now

- Home screen.
- Public product catalog list.
- Product summary DTO/model.
- Public catalog repository.
- Home/catalog state controller.
- Product summary card.
- Product image loading/fallback.
- Initial catalog pagination.
- Pull-to-refresh.
- Shared loading/error/empty integration.
- Existing route integration.
- Widget and repository tests.

### Defer

**17.2:** Full category browsing, hierarchy, category landing screens.

**17.3:** Product detail screen, image gallery, variants, detailed specifications.

**17.4:** Search input behavior, filtering controls, sorting UI.

**17.5–17.9:** Cart, checkout, payments, orders, tracking — disabled by the request-only launch decision.

**17.10:** Made-to-order request form and submission.

**17.11:** Enquiry form.

**17.12:** Customer account/profile.

Do not implement a future feature just to make the homepage look complete.

---

## 4. Architecture

Follow the Phase 16.7 feature-first convention.

Suggested structure:

```text
lib/
  features/
    catalog/
      data/
        catalog_repository.dart
        product_summary.dart
        catalog_page.dart
      presentation/
        catalog_controller.dart
        catalog_screen.dart
        product_card.dart
        product_image.dart
    home/
      presentation/
        home_screen.dart
        home_controller.dart
        widgets/
          home_intro.dart
          featured_products_section.dart
```

Names and placement may be adapted to existing conventions.

Do not create layers with no concrete responsibility.

### Dependency direction

```text
Home/Catalog Widgets
        |
        v
Feature State/Controller
        |
        v
Catalog Repository
        |
        v
Existing ApiClient
        |
        v
Laravel /api/v1/products
```

The feature must not instantiate a new HTTP client.

Use `FeatureDependencies` for constructor injection.

Use the existing `AppDiagnostics` boundary for safe diagnostics.

Use `AsyncViewState<T>` for asynchronous state.

Do not introduce Riverpod, Bloc, Provider, Redux, GetX, or another state-management framework.

---

## 5. Public catalog API

Use the frozen CAT-001 endpoint:

```http
GET /api/v1/products
```

The existing ApiClient already appends `/api/v1`; feature code must therefore pass the appropriate relative path only.

### Public access

- No sign-in required.
- No Clerk token required.
- No authenticated API mode.
- No user provisioning.
- No account-state check.
- No session restoration dependency.

Public browsing must continue working when Clerk is signed out, restoring, pending action, or unavailable.

### Request-first product selection

The initial production experience must present `MADE_TO_ORDER` products only.

Use the canonical backend query parameter:

```http
GET /api/v1/products?product_type=MADE_TO_ORDER
```

Do not fetch all products and then discard `IN_STOCK` results locally.

Do not alter backend product visibility rules.

Do not introduce a new request-only endpoint.

If the backend legitimately returns no made-to-order products, show an appropriate empty state.

### Pagination

CAT-001 uses:

- `page`, default 1.
- `per_page`, default 20.
- Maximum `per_page` of 100.
- `data` collection.
- `meta.pagination`.

Pagination fields:

```text
current_page
per_page
total
last_page
has_next
has_previous
```

Use the server's `has_next` rather than guessing from the number of products returned.

Do not create cursor-based pagination.

Do not implement custom server-side sorting.

Do not add unapproved query parameters.

---

## 6. Product summary models

Create typed models representing the actual CAT-001 product summary.

Use the frozen API field names and values.

Expected conceptual information includes:

- Opaque product ID.
- Product slug.
- Product name.
- Product type.
- Price or starting price.
- Category summary.
- Primary thumbnail.
- Public availability.
- Stock indicator.

**Do not assume these are all non-null or that the image and category fields have a particular nested shape. Verify OpenAPI and Laravel serialization first.**

### Product type

Use the closed values:

```text
IN_STOCK
MADE_TO_ORDER
```

Do not introduce extra values such as `CUSTOM`, `PREORDER`, or `AVAILABLE_ON_REQUEST`.

### Availability

Preserve the distinction:

```text
availability: available | unavailable
stock_indicator: IN_STOCK | LOW_STOCK | MADE_TO_ORDER
```

`MADE_TO_ORDER` is a valid product offering, not an out-of-stock error.

Do not expose internal inventory quantities.

### Money

The frozen API uses integer minor units with currency.

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```

That example represents TZS 1,250,000.00 under the project's canonical 100-minor-units-per-TZS convention.

Requirements:

- Parse amounts as integers.
- Do not use floating-point arithmetic for financial values.
- Format using the approved TZS presentation convention.
- Preserve the distinction between absent/null price and zero.
- Do not fabricate a starting price.
- Do not display "Free" when the price is null.
- Do not assume every product has a fixed quote.

If price is null, use a restrained, approved request-oriented message, such as "Price on request", only when consistent with the actual business representation.

### DTO validation

- Reject malformed required fields.
- Handle documented optional/null fields safely.
- Preserve opaque IDs as strings.
- Do not invent defaults for unknown enum values.
- Do not silently replace invalid monetary data with zero.
- Keep wire decoding outside widgets.

---

## 7. Catalog repository

Create one public catalog repository.

It should:

1. Receive the existing ApiClient.
2. Request the made-to-order catalog.
3. Decode typed product summaries.
4. Preserve pagination metadata.
5. Surface existing ApiError/ApiTransportException failures.
6. Support cancellation.
7. Avoid logging customer or request payloads.
8. Remain independent of Flutter widgets.

Do not create a second response-envelope parser.

Do not duplicate request-ID or Retry-After parsing.

Do not add automatic retries.

### Repository contract

Prefer an interface resembling:

```dart
abstract interface class CatalogRepository {
  Future<CatalogPage> fetchProducts({
    required int page,
    RequestCancellation? cancellation,
  });
}
```

Adapt the exact signature to existing networking APIs.

Use only the existing transport and model conventions.

---

## 8. State management

Reuse Phase 16.8's sealed `AsyncViewState<T>`.

Support:

- Initial load.
- Loading.
- Loaded content.
- Empty catalog.
- Recoverable failure.
- Refreshing existing content.
- Loading additional pages.

### Important

Do not create a global state manager.

Use a small feature-owned controller, notifier, or existing lightweight pattern.

### Cancellation

When the user leaves the screen or a newer request supersedes an older one:

- Cancel obsolete work where possible.
- Prevent stale results from overwriting newer state.
- Do not display cancellation as an error.
- Dispose feature-owned resources appropriately.
- Do not dispose the shared ApiClient.

### Pagination

- Load the next page only when needed.
- Prevent duplicate concurrent page requests.
- Stop when `has_next=false`.
- Deduplicate by stable product ID defensively.
- Preserve the server's ordering.
- Keep loaded content visible if a later page fails.
- Offer explicit recovery for a failed next-page request.
- Reset pagination on refresh.
- Do not silently skip a failed page.
- Avoid unbounded prefetching.

### Refresh

Pull-to-refresh should:

- Reload from page 1.
- Preserve a coherent UI while refreshing.
- Replace stale content on successful refresh.
- Keep previous content visible if refresh fails, with non-blocking feedback.
- Avoid overlapping refresh and pagination races.

---

## 9. Home screen design

Replace the Phase 16.6 home placeholder with a real branded storefront.

The home screen should convey:

**SL Furnitures is a curated furniture studio where customers discover designs and request furniture made for their spaces.**

Use the existing official logo asset if available and appropriately integrated.

Do not redraw the logo or replace it with an invented wordmark.

### Recommended mobile composition

```text
┌────────────────────────────────┐
│ SL Furnitures        [Menu]*   │
├────────────────────────────────┤
│                                │
│  Furniture for the way         │
│  you live.                     │
│                                │
│  Thoughtful pieces,            │
│  made for your space.          │
│                                │
│  [ Explore furniture ]         │
│                                │
├────────────────────────────────┤
│                                │
│  Made for your space           │
│  Discover our furniture        │
│  collection.                   │
│                                │
│  [Product image] [Product image]│
│  Product name    Product name  │
│  Price/request   Price/request │
│                                │
│  [ View all furniture ]        │
│                                │
├────────────────────────────────┤
│                                │
│  Made to order                 │
│  A simple introduction to      │
│  the request process           │
│                                │
└────────────────────────────────┘
```

This is an information-architecture sketch, not a pixel-perfect design specification.

`[Menu]*` is optional and must not be implemented as a nonfunctional button.

### Hero section

Use:

- Warm canvas or editorial surface.
- Strong editorial headline.
- Short supporting text.
- One prominent charcoal action.
- Generous whitespace.
- A restrained, premium composition.

Young Serif may be used for the main editorial heading.

Do not use a decorative gradient.

Do not create a generic promotional carousel.

Do not add autoplay animations.

Do not fabricate discounts or campaigns.

### Hero photography

Use an approved, genuine asset if one exists.

If no approved hero image is available, create a compelling typography-led hero using existing tokens.

Do not use an unrelated stock photograph or a remote random-image service.

Do not create an image-generation dependency.

### Featured products

Use real CAT-001 results.

A small curated-looking preview may be composed from the returned page, but do not label it "Bestsellers", "Popular", "Trending", or "Handpicked" unless the backend actually provides that curation.

Prefer a neutral section heading such as:

"Explore furniture"

or

"Made for your space"

Do not invent ranking or popularity.

### Request-oriented messaging

The homepage should explain that furniture can be requested, rather than directly purchased.

Do not imply that a request immediately creates an order.

Do not claim instant pricing, guaranteed delivery, or a manufacturing timeline without backend/business support.

---

## 10. Catalog listing screen

Replace the existing catalog placeholder with a functional listing.

### Required elements

- Clear page title.
- Brief optional supporting description.
- Product grid/list.
- Loading state.
- Empty state.
- Error state.
- Refresh.
- Pagination.
- Navigation back/home as appropriate.

### Mobile layout

Use the canonical breakpoint and spacing adapters.

For narrow phones, choose one or two columns based on real text/image readability and available width.

Do not force a two-column layout when it causes clipped names, prices, or accessibility issues.

Larger Android widths may use additional columns, but only through the approved responsive token system.

### Scrolling

Use Flutter-native lazy scrolling such as `CustomScrollView`, `SliverList`, or `SliverGrid` where appropriate.

Avoid rendering the entire catalog in a non-lazy `Column`.

Do not nest competing vertical scroll views.

Do not create a heavy custom scrolling framework.

---

## 11. Product cards

Implement one reusable product summary card.

### Card content

- Product image.
- Product name.
- Price/starting price if available.
- Small made-to-order label if needed.
- Clear navigation affordance.

Avoid:

- Star ratings.
- Review counts.
- Discount percentages.
- Add-to-cart buttons.
- Favorite hearts.
- Quantity selectors.
- Delivery promises.
- Payment badges.
- Multiple competing CTAs.
- Invented urgency or scarcity.

### Visual treatment

Use:

- Flat surfaces.
- Sharp product photography.
- Token-based spacing.
- Strong image hierarchy.
- Readable product names.
- Quiet metadata.
- Minimal borders.
- No decorative elevation.

Canonical product-card image ratio:

```text
4:3
```

Use the existing generated token mapping for that ratio.

Do not invent a different card ratio without an approved reason.

### Interaction

The whole card may be a single accessible navigation target.

Navigate using the existing product-detail route and a validated canonical identifier.

Phase 17.3 owns the complete product-detail implementation.

If the detail destination remains a placeholder, preserve that status honestly; do not implement the full detail page here.

---

## 12. Product images

Use the image URL returned by the public catalog.

Do not hard-code Cloudflare R2 URLs or construct storage keys in Flutter.

The backend controls public image delivery.

### Requirements

- Respect the server-provided URL.
- Use `Image.network` or a justified existing image component.
- Set `fit` appropriately for furniture imagery.
- Maintain a stable aspect ratio.
- Show a neutral loading placeholder.
- Show a tasteful missing-image fallback.
- Handle invalid or unavailable images without crashing.
- Preserve meaningful image semantics.
- Avoid exposing raw image URLs in diagnostics.

### Caching

Use Flutter's existing image caching behavior initially.

Do not add a disk image-cache dependency without evidence of a real performance need.

Do not create a custom Cloudflare R2 integration.

Do not bundle arbitrary furniture photos.

---

## 13. Navigation

Reuse the Phase 16.6 `go_router`.

### Existing destinations

The project already reserves routes for:

- Home.
- Catalog.
- Product detail.
- Categories.
- Search.
- Furniture requests.
- Contact.
- Authentication entry points.
- Account.

Use existing approved route names and paths.

Do not create a second router.

Do not change the protected `/account` contract.

### Active navigation

At minimum, the user must be able to:

- Open the home screen.
- Navigate from home to catalog.
- Return from catalog to home.
- Open the existing product-detail destination from a product card.

Do not add fake navigation controls.

If a future destination is still a placeholder, avoid presenting it as a fully implemented workflow.

### App shell

A small home/catalog navigation affordance may be introduced only if required for the approved Phase 17.1 experience.

Do not prematurely create a five-tab shell with Cart, Orders, Favorites, and Profile.

Do not duplicate app bars across nested routes.

---

## 14. Design-system enforcement

**Canonical authority:**

```text
frontend/design-system/tokens.css
```

Flutter must consume the existing generated Material 3 mapping.

### Approved visual language

- Architectural.
- Warm.
- Editorial.
- Calm.
- Photography-led.
- Accessible.
- Restrained.

### Key semantic colors

- Canvas: `--surface-canvas`.
- Paper: `--surface-paper`.
- Editorial: `--surface-editorial`.
- Primary text: `--text-primary`.
- Secondary text: `--text-secondary`.
- Primary action: `--action-primary`.
- Brand accent: `--accent-brand`.

Use the generated Dart mappings, not raw hex literals in feature widgets.

### Typography

- Young Serif for editorial headings.
- Existing platform-sans fallback for utility UI.
- Approved type scale only.
- No arbitrary font sizes.
- No new font dependencies.
- No unlicensed Helvetica Now Text assets.

### Spacing

Use the existing spacing and gutter adapters.

No one-off spacing scale.

### Shape

- Product images remain visually sharp.
- Ordinary product cards remain flat.
- Rounded controls use approved radius tokens.
- Avoid excessive pill-shaped containers.

### Motion

Use approved duration/easing tokens.

Respect reduced-motion settings.

Do not add bouncing, parallax, shimmer-heavy, or spring-driven effects.

### Explicit design prohibitions

No:

- Nike apparel identity.
- Generic AI storefront hero.
- Neon accents.
- Glassmorphism.
- Decorative gradients.
- Blurred translucent cards.
- Badge stacks.
- Dashboard tiles.
- Emoji-based feature sections.
- Random furniture icons.
- Unapproved shadows.
- Hard-coded colors.
- Hard-coded typography.
- Fabricated promotional content.

---

## 15. Error and loading integration

Reuse:

- `AppLoadingView`.
- `AppEmptyView`.
- `AppErrorView`.
- `AppInlineError`.
- `ErrorPresentationMapper`.
- `AsyncViewState<T>`.

### Initial loading

Show a stable, accessible loading view.

Do not display fake product cards.

### Empty catalog

Show a successful empty state.

Explain that furniture listings are not currently available.

Provide a meaningful navigation or refresh action when appropriate.

### Initial failure

Show the safe mapped error presentation.

Use explicit manual retry for safe GET requests.

### Pagination failure

Preserve already loaded products.

Show a localized inline error and retry control.

### Refresh failure

Keep previously loaded products visible.

Do not replace them with a full-screen failure unnecessarily.

### Cancellation

Do not show an error.

Do not automatically retry.

---

## 16. Diagnostics

Use `FeatureDependencies.diagnostics`.

Record only approved typed events.

Examples:

- Public catalog load failure.
- Pagination failure.
- Invalid catalog response.
- Unexpected state transition.

Do not log:

- Product URLs.
- Query strings.
- Raw response bodies.
- Raw exception messages.
- Clerk credentials.
- Customer information.
- Product descriptions.
- Arbitrary API payloads.

Use the existing safe request-ID correlation.

Avoid duplicate logging when ApiClient already owns the failure event.

---

## 17. Accessibility

Apply the project's WCAG 2.2 AA-oriented baseline.

Verify:

1. Logical screen headings.
2. Product card semantic labels.
3. Meaningful image alternatives.
4. Accessible navigation controls.
5. Visible focus.
6. Correct touch target sizes.
7. Sufficient contrast.
8. 2× text scaling.
9. Narrow-screen reflow.
10. Landscape usability.
11. Screen-reader-friendly loading state.
12. Clear error recovery.
13. No color-only availability communication.
14. Reduced-motion behavior.
15. No clipped product prices or names.

Avoid redundant screen-reader announcements for image and card labels.

Do not make decorative images focusable.

---

## 18. Performance

The home/catalog feature should remain lightweight.

### Required

- Lazy product rendering.
- Server-side pagination.
- No unnecessary authentication calls.
- No repeated requests during widget rebuilds.
- Cancellation of obsolete requests.
- Stable widget keys.
- Bounded product loading.
- Efficient image sizing.
- No unbounded background prefetch.
- No duplicate catalog requests caused by home/catalog composition.

### Measure

Where possible, inspect:

- Time to first meaningful catalog content.
- Initial network request count.
- Scrolling smoothness.
- Memory behavior with multiple pages.
- Image loading behavior.
- Rebuild frequency.

Do not claim performance improvements without measurements.

---

## 19. Automated tests

Add focused unit, widget, and navigation tests.

### A. DTO parsing

Test:

- Valid product summary.
- Missing required field.
- Optional null price.
- Integer money amount.
- Currency.
- Product type enum.
- Availability enum.
- Stock indicator enum.
- Category summary.
- Primary image.
- Missing image.
- Malformed image representation.
- Opaque product ID.
- Product slug.

### B. Repository

Test:

- Public GET uses no bearer token.
- `product_type=MADE_TO_ORDER` is sent.
- Pagination parameters are correct.
- Success envelope is decoded.
- Empty data is accepted.
- Invalid payload is rejected.
- API error is preserved.
- Transport error is preserved.
- Request cancellation is supported.
- No automatic retry occurs.

### C. State controller

Test:

- Initial loading.
- Successful first page.
- Empty first page.
- Initial error.
- Manual retry.
- Refresh.
- Refresh failure with preserved content.
- Load next page.
- Pagination failure.
- Pagination retry.
- No more pages.
- Duplicate concurrent pagination blocked.
- Stale response ignored.
- Disposed controller ignores late results.
- Product ID deduplication.

### D. Widgets

Test:

- Home hero.
- Home-to-catalog navigation.
- Catalog loading.
- Catalog empty state.
- Catalog error state.
- Product cards.
- Null price presentation.
- Made-to-order label.
- Image fallback.
- Product card navigation.
- No cart or checkout controls.
- 2× text scaling.
- Narrow-screen layout.
- Accessibility semantics.

### E. Regression

Verify:

- Existing 156 tests still pass.
- Existing Clerk integration is unchanged.
- Existing routing tests pass.
- Existing ApiClient tests pass.
- Existing async presentation tests pass.
- Existing diagnostics tests pass.
- Token freshness passes.
- No deferred commerce routes appear.
- No backend/API contract changes occur.

These are coverage targets, not a requirement for one test method per item.

Use deterministic fixtures based on the actual frozen OpenAPI response.

Do not invent a wire schema in tests.

---

## 20. Live API verification

The project has previously verified that the Flutter ApiClient can reach:

```http
GET /api/v1/products
```

Phase 17.1 should verify the actual feature integration.

### Local verification

1. Start the approved Laravel development backend.
2. Use a valid local Flutter config.
3. Confirm the public products endpoint responds.
4. Confirm the made-to-order filter works.
5. Confirm the app parses the actual response.
6. Confirm the home screen displays server data.
7. Confirm the catalog handles an empty dataset.
8. Confirm pagination behavior.
9. Confirm image loading or safe fallback.
10. Confirm no Clerk sign-in is required.

Do not modify real production data to make a demo look populated.

Do not seed the development database destructively.

If the catalog is empty, report the empty-state verification and use mocked widget tests for populated-state coverage.

### Physical Android

If Marionette and a physical Android device are available:

- Launch the app.
- Inspect the home screen.
- Tap Explore Furniture.
- Scroll the catalog.
- Open a product card's existing detail destination.
- Navigate back.
- Inspect loading and error behavior where reproducible.
- Check visual hierarchy and text scaling.
- Capture screenshots for review if the testing setup supports them.

Use the documented `adb reverse` workflow for local backend connectivity.

If physical testing is unavailable, report it explicitly.

Do not claim device verification based solely on widget tests.

---

## 21. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Phase 17.1 completion record.
- `docs/decisions.md` only for genuinely new durable decisions.

Document:

- Home/catalog architecture.
- CAT-001 endpoint usage.
- Product summary decoding.
- Request-only product filtering.
- Pagination behavior.
- State and error handling.
- Navigation integration.
- Image handling.
- Design-token compliance.
- Accessibility verification.
- Performance observations.
- Tests.
- Live backend verification.
- Remaining limitations.

Do not rewrite Group P history.

---

## 22. Verification commands

Run from the Flutter project directory:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug
git diff --check
git diff --cached --check
```

If local configuration is required for a build or launch, use the existing ignored local configuration file.

Do not commit secrets or machine-specific configuration.

### Whitespace handling

The previous phase documented two intentional Markdown hard-break spaces in the Group P phase header.

Report scoped and full diff checks accurately.

Do not silently rewrite unrelated phase records.

---

## 23. Completion report

Provide:

1. Repository and contract inspection findings.
2. Exact API fields used.
3. Home screen implementation.
4. Catalog screen implementation.
5. Feature architecture.
6. DTO and repository behavior.
7. State management and pagination.
8. Product image handling.
9. Navigation integration.
10. Request-only enforcement.
11. Design-token compliance.
12. Accessibility verification.
13. Performance observations.
14. Automated test results.
15. Live Laravel verification.
16. Physical Android verification.
17. Files added and modified.
18. Documentation updates.
19. Remaining blockers.
20. Final Phase 17.1 PASS/FAIL.

---

## 24. Definition of Done

Phase 17.1 passes when:

- The home placeholder is replaced with a real, branded screen.
- The catalog placeholder is replaced with a working product listing.
- Both consume real Laravel public catalog data.
- Anonymous browsing works.
- Initial release displays only made-to-order products.
- Product summaries follow the frozen API contract.
- Prices are represented safely.
- Product images load or fail gracefully.
- Pagination is server-driven and correct.
- Loading, empty, error, refresh, and pagination states work.
- Navigation uses the existing go_router.
- No purchasing controls are exposed.
- No future Group Q workflows are prematurely implemented.
- The design system is followed without arbitrary values.
- Accessibility is tested.
- All existing and new tests pass.
- The debug APK builds.
- Token synchronization passes.
- Documentation is updated.
- Live verification is completed or limitations are accurately recorded.

**Final instruction:** Build the first real SL Furnitures customer experience with production-quality architecture and editorial visual discipline. Prioritize authentic product data, strong photography presentation, responsive layouts, and accessible interaction. Preserve the request-only business decision and stop at Phase 17.1.