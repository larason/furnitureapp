# Phase 17.3 — Flutter Product Details

**Project:** SL Furnitures — Flutter Android Customer Application  
**Group:** Q — Flutter Customer Features  
**Prerequisites:** Group P complete; Phases 17.1 and 17.2 implemented  
**Scope:** Public furniture product details  
**Status:** READY FOR IMPLEMENTATION

## 1. Objective

Implement a production-quality product-detail experience for SL Furnitures.

Replace the existing product-detail placeholder with a fully functional screen that allows customers to:

1. View complete furniture information.
2. Browse product photographs.
3. Read the product description.
4. Understand made-to-order pricing.
5. Inspect available product variants.
6. Navigate to the product's category.
7. Understand how to request the furniture.
8. Return to the catalog or category.
9. Recover from loading and network failures.

The design must feel like a premium furniture showroom rather than a conventional checkout-driven marketplace.

**Do not implement furniture-request submission in this phase. Phase 17.10 owns that workflow.**

---

## 2. Mandatory repository inspection

Before editing, read:

- Root `AGENTS.md`.
- Flutter `AGENTS.md`.
- Group Q Phase 17.3 specification.
- Phase 17.1 and 17.2 completion records.
- Flutter README.
- `lib/features/README.md`.
- `docs/decisions.md`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/flutter-material3.md`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/openapi.yaml`.
- Existing Laravel CAT-002 implementation and tests.
- Existing Next.js product-detail implementation, if available.
- Existing Flutter catalog models, product cards, category models, routing, image widgets, async presentation, and diagnostics.

### Required inspection findings

Identify:

1. Existing product-detail route path and parameter rules.
2. Product summary model from Phase 17.1.
3. CatalogRepository interface.
4. Current API and fixture repository implementations.
5. Existing `CatalogImage` implementation.
6. Existing money formatting.
7. Existing Material 3 theme and generated tokens.
8. Actual CAT-002 response structure.
9. Product gallery image fields.
10. Embedded variant summary fields.
11. Existing fixture products and bundled image assets.
12. Existing test and verification conventions.

Do not guess class names or APIs.

Do not duplicate working infrastructure.

---

## 3. Frozen API contract

Use:

```http
GET /api/v1/products/{product}
```

This is `CAT-002`.

The endpoint is public and accepts either:

- Canonical product slug.
- Stable opaque product ID.

Prefer the API-returned slug for customer-facing navigation.

### Public access

The request must:

- Work without Clerk sign-in.
- Use the existing ApiClient.
- Use public authentication mode.
- Avoid bearer tokens.
- Avoid user provisioning.
- Remain available during Clerk restoration or temporary unavailability.

### Response

CAT-002 returns a full Product Detail representation.

Required contract fields include:

```text
id
name
slug
product_type
price
category
primary_image
availability
stock_indicator
description
images[]
variants[]
created_at
updated_at
```

The detail response extends the existing product summary.

Verify exact field types and nullability against OpenAPI and Laravel serialization.

Do not invent additional response fields.

### Error handling

An unknown, unpublished, inactive, or otherwise non-public product must be handled according to the actual API response.

`RESOURCE_NOT_FOUND` 404 should produce a clear unavailable-product state.

Do not reveal hidden product information.

---

## 4. Feature architecture

Follow the existing feature-first conventions.

Suggested structure:

```text
lib/
  features/
    product_detail/
      data/
        product_detail.dart
        product_image.dart
        product_variant_summary.dart
        product_detail_repository.dart
      presentation/
        product_detail_controller.dart
        product_detail_screen.dart
        widgets/
          product_gallery.dart
          product_information.dart
          product_variant_selector.dart
          product_request_action.dart
```

This is illustrative.

Use fewer files if appropriate.

### Required dependency direction

```text
ProductDetailScreen
        |
        v
ProductDetailController
        |
        v
ProductDetailRepository
        |
        v
Existing ApiClient
        |
        v
Laravel CAT-002
```

Use constructor injection through the existing `FeatureDependencies`.

Do not instantiate a new HTTP client.

Do not introduce a global state-management package.

Do not add unnecessary architectural layers.

---

## 5. Product detail models

Create a typed `ProductDetail` representation.

Reuse existing product summary concepts where appropriate.

Do not copy the same parsing logic into unrelated models.

### Product identity

Preserve:

- Opaque product ID.
- Canonical server-provided slug.
- Product name.
- Product type.

Do not derive slugs from product names.

### Description

Render the actual API-provided description.

Do not invent specifications or marketing copy.

Inspect whether the description is plain text or another documented format.

Do not introduce an HTML renderer without an actual contract requirement.

### Price

Use the existing money representation:

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```

Money amounts are integer minor units.

Do not use floating-point arithmetic for prices.

Reuse the formatter from Phase 17.1.

For made-to-order products, communicate that the displayed price is informational and that final requirements and pricing may depend on the request process.

Do not label the price as a confirmed quotation.

Do not fabricate discounts or installment options.

### Availability

Preserve the frozen values:

```text
availability:
  available
  unavailable

stock_indicator:
  IN_STOCK
  LOW_STOCK
  MADE_TO_ORDER
```

Do not expose physical or reserved inventory quantities.

Do not present `MADE_TO_ORDER` as a stock shortage.

---

## 6. Product gallery

Implement a responsive, accessible image gallery.

### Data contract

CAT-002 provides `images[]` with:

```text
id
url
alt_text
sort_order
is_primary
```

### Ordering

Preserve the backend's deterministic ordering:

```text
sort_order ASC
id ASC
```

Use the primary image as the initial displayed image where the documented contract identifies it.

Do not reorder images based on filename or URL.

### Required behavior

- Large primary image.
- Swipe navigation between images.
- Visible position indicator when multiple images exist.
- Thumbnail selection if useful on larger screens.
- Stable image dimensions.
- Image loading feedback.
- Neutral missing-image fallback.
- No crashes on image failures.
- Appropriate semantics.
- No forced autoplay.

### Image interactions

Support horizontal swipe using native Flutter primitives.

A full-screen image viewer is optional if it can be implemented cleanly without unnecessary dependencies.

If included:

- Preserve image order.
- Support dismissal/back navigation.
- Avoid disorienting transitions.
- Handle zoom accessibly.
- Respect reduced-motion preferences.

Do not create a complex custom gallery engine.

### Reuse

Extend `CatalogImage` or its underlying image-handling primitives rather than creating an entirely separate image-loading system.

---

## 7. Product information layout

Create a clear hierarchy:

1. Product photography.
2. Product name.
3. Price.
4. Made-to-order status.
5. Description.
6. Available variants.
7. Request-oriented action.
8. Category navigation.

Exact placement may vary according to the approved design system.

### Mobile composition

Suggested layout:

```text
┌────────────────────────────────┐
│ ← Back             SL Furnitures│
├────────────────────────────────┤
│                                │
│       PRODUCT PHOTOGRAPH       │
│                                │
│            ● ○ ○               │
├────────────────────────────────┤
│                                │
│  Living Room                   │
│                                │
│  Modern Walnut Sofa            │
│                                │
│  From TZS 1,250,000            │
│                                │
│  Made to order                 │
│                                │
│  Description                   │
│  Carefully crafted...          │
│                                │
│  Available options             │
│  [Variant A] [Variant B]       │
│                                │
├────────────────────────────────┤
│  [ Request this furniture ]    │
└────────────────────────────────┘
```

This is an information-architecture reference, not a fixed pixel specification.

Do not reproduce it literally if existing design tokens or accessibility requirements suggest a better arrangement.

---

## 8. Product variants

CAT-002 includes embedded variant summaries.

Each variant exposes:

```text
id
sku
name
price
availability
stock_indicator
```

### Required UI

Display available variant options in a clear, accessible selection interface.

For example:

- Variant name.
- Variant-specific price.
- Public availability where meaningful.

### Selection behavior

- Use stable variant IDs.
- Keep selection state local to the product-detail feature.
- Update the displayed variant price when a variant is selected.
- Clearly indicate the selected option.
- Avoid ambiguous selection controls.
- Reset selection when the product changes.
- Never assume the first variant is selected unless the UI deliberately establishes that default.

### Important restrictions

Do not invent variant attributes such as:

- Fabric.
- Color.
- Dimensions.
- Material.
- Finish.

Only display these as structured values if the actual API contract provides them.

Do not parse a variant's name or SKU to manufacture structured attributes.

### Availability

Use the backend-provided coarse availability values.

Do not fabricate inventory quantities.

Do not implement stock reservations.

### Selection and requests

The selected variant may be retained as transient presentation state.

Do not persist a draft request or submit a request to Laravel.

Phase 17.10 owns request form behavior and request payload mapping.

---

## 9. Made-to-order customer action

The launch is request-only.

The primary customer action is conceptually:

**Request this furniture**

It is not:

- Add to cart.
- Buy now.
- Checkout.
- Reserve stock.
- Pay deposit.
- Place order.

### Phase boundary

The actual furniture-request form belongs to Phase 17.10.

During Phase 17.3:

1. Inspect the existing request route.
2. Preserve its current placeholder contract.
3. Establish a safe navigation path only if it is supported by the current router.
4. Do not pretend the request submission workflow is implemented.
5. Do not silently create a request.
6. Do not add a new API mutation.
7. Do not create temporary local orders.

### Product context

If the current request route supports approved product context, pass the canonical identifier through validated navigation state.

Do not invent unsupported query parameters or request payload fields.

Do not rely on a global mutable selected-product singleton.

### If the request route is still a placeholder

The UI must communicate that the request flow is not yet available, rather than presenting a fully functional conversion journey.

Prefer a truthful disabled action or an explicitly labeled preview of the upcoming workflow over a button that appears to submit a request.

Document this temporary limitation.

---

## 10. Category navigation

The product detail includes an embedded category summary.

Use its server-provided slug to navigate to the existing Phase 17.2 category-detail route.

Requirements:

- No locally derived slug.
- No duplicate category repository.
- No new category routes.
- No authentication requirement.
- Correct back navigation.
- Preserve category context where practical.

Do not implement breadcrumb navigation that implies unsupported nested taxonomy levels.

---

## 11. Development fixture integration

The Flutter README confirms:

```text
CATALOG_DATA_SOURCE=fixtures
```

is implemented for local debug builds.

Phase 17.3 must extend that same source-selection policy.

### Required behavior

When fixture mode is active:

- Product cards open fixture product details.
- Fixture product IDs and slugs match catalog summaries.
- Fixture details use the same typed `ProductDetail` model.
- Fixture gallery images use approved bundled assets.
- Fixture variants follow the frozen API shape.
- Category links resolve correctly.
- Unknown fixture products return a controlled not-found result.

### Fixture source

Inspect the existing three web-derived fixture products.

Reuse their approved content and imagery.

Do not invent a new contradictory catalog.

If the existing fixture summaries lack description, gallery, or variant detail:

- Inspect approved Next.js fixture data first.
- Add only clearly identified development-only values needed to exercise the UI.
- Keep synthetic detail fields clearly documented.
- Do not represent fixture content as real production inventory.

### Environment safety

- Local debug may use fixtures.
- Staging always uses Laravel.
- Production always uses Laravel.
- No fallback to fixtures after API failure.
- No separate product-detail data-source setting.
- No release-mode fixture access.

Use the existing configuration validation.

---

## 12. Async state management

Reuse `AsyncViewState<T>`.

Support:

- Initial.
- Loading.
- Content.
- Failure.
- Refreshing.

Do not create a new async-state framework.

### Loading

Show an accessible loading state with stable layout.

### Success

Render the full product detail.

### Not found

Show a product-unavailable state with a meaningful navigation action.

### Network failure

Use `ErrorPresentationMapper` and `AppErrorView`.

Allow explicit manual retry for the safe public GET request.

### Refresh

If refresh is implemented, preserve existing content until the new response succeeds.

### Cancellation

- Cancel obsolete requests.
- Ignore stale responses.
- Do not display cancellation as an error.
- Do not dispose the shared ApiClient.

### Route changes

If a user navigates rapidly between products, previous product details must never appear as the newly selected product.

---

## 13. Navigation

Reuse the existing central `go_router`.

The README confirms that product detail is an approved public route but remains a placeholder.

Replace only that placeholder with the new screen.

### Requirements

- Valid slug.
- Valid opaque ID.
- Invalid parameter handling.
- Unknown product handling.
- Direct route navigation.
- Catalog-to-detail navigation.
- Category-to-detail navigation.
- Back navigation.
- Product change without stale data.
- Public access in all Clerk states.

Do not create a second router.

Do not modify `/account` authentication guards.

Do not add Android App Links before the approved production domain and verification work.

---

## 14. Material 3 and design-system enforcement

The canonical authority is:

```text
frontend/design-system/tokens.css
```

Use the existing generated Flutter theme.

### Visual identity

- Warm architectural surfaces.
- Strong product photography.
- Editorial headings.
- Restrained metadata.
- Clear hierarchy.
- Spacious composition.
- Minimal ornament.

### Typography

Use Young Serif for approved editorial/display headings.

Use the existing platform sans for utility text.

Do not bundle Helvetica Now Text without a license.

Do not add Google Fonts.

### Color

Use generated semantic tokens.

No hard-coded color literals.

### Spacing

Use existing spacing and section adapters.

Do not introduce arbitrary spacing values.

### Product photography

Prioritize large, clean product imagery.

Do not place essential text over photographs.

### Surfaces

Keep product information on calm, readable backgrounds.

Avoid decorative shadows and elevated cards.

### Motion

Use approved motion tokens.

Respect reduced-motion settings.

### Prohibited patterns

- Generic marketplace layout.
- Fake sale banners.
- Discount countdowns.
- Favorite hearts.
- Review stars.
- Glassmorphism.
- Decorative gradients.
- Floating emoji.
- Unapproved accent colors.
- Multiple competing primary actions.
- Arbitrary rounded cards.
- Excessive animations.

---

## 15. Accessibility

Verify:

1. Semantic product heading.
2. Meaningful image descriptions.
3. Gallery position announcements.
4. Accessible gallery controls.
5. Clear selected variant state.
6. Keyboard and screen-reader interaction.
7. Visible focus.
8. Adequate touch targets.
9. Sufficient contrast.
10. 2× text scaling.
11. Small-screen reflow.
12. Landscape usability.
13. No clipped prices.
14. No clipped variant names.
15. Reduced-motion behavior.
16. Clear error recovery.
17. Accessible disabled/request-placeholder action.

Avoid redundant image semantics.

Do not make purely decorative images interactive.

---

## 16. Diagnostics

Use the existing AppDiagnostics interface.

Safe diagnostic events may cover:

- Product detail fetch failure.
- Invalid product response.
- Product image presentation failure, where appropriate.
- Unexpected feature state.

Do not log:

- Product URLs.
- Query parameters.
- Raw response bodies.
- Raw exceptions.
- Customer information.
- Clerk tokens.
- Arbitrary product identifiers.

Use approved event categories and codes.

Do not expand the closed diagnostic vocabulary casually.

Avoid duplicate network events already owned by ApiClient.

---

## 17. Performance

Requirements:

- No duplicate CAT-002 requests caused by rebuilds.
- No unnecessary CAT-005 calls.
- No separate request for every embedded variant.
- Stable gallery layout.
- Lazy thumbnail loading.
- Appropriate image decoding.
- No unbounded image prefetch.
- Cancellation of obsolete requests.
- Minimal state rebuilds.
- No new state-management package.
- No unnecessary image-gallery dependency.

CAT-002 already contains embedded variant summaries.

Use them unless the frozen contract requires additional standalone variant data for a specifically approved interaction.

---

## 18. Automated tests

Add deterministic tests.

### A. Product detail parsing

Test:

1. Valid CAT-002 response.
2. Required product fields.
3. Product description.
4. Product type.
5. Price in minor units.
6. Category summary.
7. Availability.
8. Stock indicator.
9. Empty image gallery.
10. Multiple images.
11. Image ordering.
12. Primary image selection.
13. Embedded variants.
14. Empty variants.
15. Invalid variant fields.
16. Invalid enum values.
17. Malformed response.
18. Timestamps.

### B. Repository

Test:

19. Public request without bearer token.
20. Slug-based detail lookup.
21. Opaque ID lookup.
22. Correct CAT-002 path.
23. API success decoding.
24. API 404.
25. Transport failure.
26. Invalid response envelope.
27. Cancellation.
28. No automatic retry.
29. No unnecessary CAT-005 requests.

### C. Controller

Test:

30. Initial loading.
31. Successful detail load.
32. Not-found state.
33. Network error.
34. Manual retry.
35. Refresh.
36. Stale response suppression.
37. Route parameter changes.
38. Disposal.
39. Variant selection.
40. Variant price update.
41. Product change resets selection.

### D. Gallery

Test:

42. Single image.
43. Multiple images.
44. Swipe navigation.
45. Position indicator.
46. Missing image fallback.
47. Image failure fallback.
48. Accessibility semantics.
49. Stable layout.
50. Reduced-motion behavior.

### E. UI

Test:

51. Product name.
52. Product description.
53. Made-to-order label.
54. Price formatting.
55. Variant selection.
56. Category navigation.
57. Request-action placeholder behavior.
58. No cart controls.
59. No checkout controls.
60. No payment controls.
61. 2× text scaling.
62. Narrow-screen layout.
63. Landscape layout.
64. Error and empty states.

### F. Fixtures

Test:

65. Fixture summary/detail consistency.
66. Fixture gallery assets.
67. Fixture variants.
68. Unknown fixture product.
69. Local debug source selection.
70. Production fixture rejection.
71. No API-to-fixture fallback.

### G. Regression

Verify:

- All existing tests pass.
- Home/catalog still works.
- Categories still work.
- Router behavior remains correct.
- Clerk remains unchanged.
- ApiClient remains unchanged.
- Error/loading components remain reusable.
- Diagnostics remain privacy-safe.
- No commerce routes are registered.
- Token generation remains synchronized.

This is a coverage matrix, not a mandate to create 71 individual test methods.

---

## 19. Live Laravel verification

When Laravel is available, verify:

```http
GET /api/v1/products/{product}
```

Use a real published made-to-order product slug or ID.

Verify:

- Anonymous access.
- Correct product detail fields.
- Image gallery.
- Embedded variants.
- Category identity.
- Money representation.
- Unknown-product 404.
- No extra variant API requests.

Do not modify production data.

If the development database has no suitable products, report the limitation and rely on fixture-backed UI tests for populated-state verification.

Do not claim real API detail verification from fixtures.

---

## 20. Physical Android verification

The README documents Marionette MCP support for debug builds.

When the physical device and Marionette are available:

1. Launch the app.
2. Open the home screen.
3. Navigate to the catalog.
4. Tap a product.
5. Inspect product detail.
6. Swipe gallery images.
7. Select variants.
8. Verify price updates.
9. Navigate to category.
10. Navigate back.
11. Inspect loading/error states where reproducible.
12. Check small-screen and text-scale behavior.

If physical verification is unavailable, document it.

Do not claim physical verification from widget tests.

---

## 21. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Phase 17.3 completion record.
- `docs/decisions.md` only for genuinely new durable decisions.

Document:

1. CAT-002 integration.
2. Product detail model.
3. Gallery behavior.
4. Variant selection.
5. Price presentation.
6. Category navigation.
7. Request-only behavior.
8. Fixture integration.
9. Async state handling.
10. Accessibility.
11. Diagnostics.
12. Automated tests.
13. Live API verification.
14. Physical Android verification.
15. Remaining limitations.

Do not rewrite earlier phase histories.

---

## 22. Verification commands

Run from the Flutter project root:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug --dart-define-from-file=config/local.json
git diff --check
git diff --cached --check
```

Use the existing ignored local configuration.

Do not commit credentials.

### Additional verification

Inspect:

- Added dependencies.
- Generated token changes.
- Product-detail route integration.
- Fixture asset declarations.
- Accidental raw colors.
- Duplicate API clients.
- Duplicate model parsers.
- Unexpected authentication requirements.
- Deferred commerce controls.

Report staged and unstaged whitespace results separately.

---

## 23. Completion report

Provide:

1. Repository inspection findings.
2. Exact CAT-002 fields used.
3. Product-detail architecture.
4. Product gallery implementation.
5. Variant selection behavior.
6. Money formatting.
7. Made-to-order action behavior.
8. Category navigation.
9. Fixture integration.
10. Loading/error states.
11. Accessibility verification.
12. Diagnostics behavior.
13. Automated test results.
14. Live Laravel verification.
15. Physical Android verification.
16. Files added and modified.
17. Documentation updates.
18. Remaining blockers.
19. Final Phase 17.3 PASS/FAIL.

---

## 24. Definition of Done

Phase 17.3 passes when:

- Product-detail placeholder is replaced.
- CAT-002 is consumed through the existing ApiClient.
- Anonymous access works.
- Product detail models follow the frozen contract.
- The complete product gallery works.
- Image failures are handled gracefully.
- Product descriptions render correctly.
- Variant selection works.
- Variant-specific prices display correctly.
- Category navigation works.
- Request-only behavior is preserved.
- No request submission is implemented.
- No cart, checkout, or payment controls appear.
- Development fixtures support product details.
- Staging and production remain API-only.
- Loading, error, and not-found states work.
- Accessibility tests pass.
- Existing and new tests pass.
- Token synchronization passes.
- Debug APK builds.
- Documentation is updated.
- External verification limitations are reported accurately.

**Final instruction:** Implement the smallest maintainable, visually refined product-detail experience that uses real CAT-002 data, extends the existing fixture mode, respects the design tokens, and prepares for Phase 17.10 without implementing it. Stop after Phase 17.3.