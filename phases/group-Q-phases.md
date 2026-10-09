# Phase 17.2 — Flutter Categories

**Project:** SL Furnitures — Flutter Android Customer App  
**Group:** Q — Customer Features  
**Prerequisites:** Group P complete; Phase 17.1 must be verified  
**Implementation status:** READY  
**Scope:** Public category discovery and category-specific furniture browsing

## 1. Objective

Implement a polished, accessible category-browsing experience that helps customers discover furniture by room or category.

Deliver:

1. A functional categories index screen.
2. Category summary models and repository.
3. Category detail/landing screen.
4. Category-specific product listings.
5. Integration with the existing catalog feature.
6. Appropriate category photography and image fallbacks.
7. Loading, empty, error, refresh, and pagination behavior.
8. Development fixture support where already approved.
9. Navigation and deep-link handling.
10. Comprehensive automated tests and documentation.

The experience must remain public, request-first, and consistent with the SL Furnitures design system.

**Do not implement product details, search/filter UI, or made-to-order request submission in this phase.**

---

## 2. Mandatory repository inspection

Read before editing:

- Root and Flutter `AGENTS.md`.
- Group Q Phase 17.2 specification.
- Phase 17.1 implementation and completion record.
- Flutter README.
- `lib/features/README.md`.
- `docs/decisions.md`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/tokens.css`.
- Flutter Material 3 token mappings.
- `docs/api/openapi.yaml`.
- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- Existing category endpoints and Laravel tests.
- Existing web category-page implementation and development fixtures.
- Flutter routing, catalog repository, product card, image handling, async state, and diagnostics.

### Critical prerequisite

The previous conversation proposed a Flutter development fixture mode, but its implementation has not been confirmed here.

Inspect the repository and determine whether Phase 17.1 actually implemented:

- `CatalogRepository`.
- `ApiCatalogRepository`.
- `FixtureCatalogRepository`.
- `CATALOG_DATA_SOURCE`.
- Product summary models.
- Catalog pagination.
- Product cards.
- Catalog screen.
- Home screen.

Do not assume these classes or settings exist.

If Phase 17.1 is incomplete, report the missing prerequisites rather than silently implementing its entire scope inside Phase 17.2.

---

## 3. Frozen API contract

### CAT-003 — Category collection

```http
GET /api/v1/categories
```

This endpoint is public.

It returns active storefront categories directly beneath the structural `Furnitures Root`.

It does not return:

- The structural root itself.
- Inactive categories.
- Every deeper descendant.
- A complete recursive category tree.

The response uses the standard paginated collection envelope:

```json
{
  "data": [
    {
      "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9",
      "name": "Living Room",
      "slug": "living-room",
      "image": {
        "url": "https://cdn.furniture.co.tz/categories/living-room.webp"
      }
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 1,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

The JSON above illustrates the documented response structure; use the actual frozen contract for nullability, accepted query parameters, and validation.

### CAT-004 — Category detail

```http
GET /api/v1/categories/{category}
```

`{category}` may be the canonical category slug or stable opaque ID.

The response contains the category's public detail representation, including its name, slug, description, image, and documented metadata.

A missing or inactive category returns `RESOURCE_NOT_FOUND` (404).

### CAT-001 — Products within a category

```http
GET /api/v1/products?category=living-room&product_type=MADE_TO_ORDER
```

This is the authoritative category-product query.

**Do not create or consume:**

```http
GET /api/v1/categories/{category}/products
```

That nested route is explicitly rejected by the frozen API decision.

### Contract rules

- Do not invent endpoints.
- Do not invent category query parameters.
- Do not add a `children` field that the API does not provide.
- Do not assume the collection is a recursive tree.
- Do not change backend response shapes.
- Do not expose inactive categories.
- Do not derive category slugs from display names.
- Do not create a separate mobile-only category API.

---

## 4. Category hierarchy

The backend category taxonomy has a structural root and multiple category levels.

The public category collection, however, returns only active top-level storefront categories.

### Required behavior

The categories index must display the categories actually returned by CAT-003.

The category landing page must display the selected category's CAT-004 details and associated products through CAT-001.

Do not invent subcategory navigation.

Do not create fake child categories from static arrays.

Do not assume a category has children simply because the database taxonomy supports descendants.

### Future expansion

If the frozen public API later exposes an approved hierarchy representation, the UI may expand under a separate phase or contract-compatible implementation.

Phase 17.2 must not alter the frozen API to expose deeper levels.

---

## 5. Feature architecture

Follow the existing feature-first conventions.

Suggested structure:

```text
lib/
  features/
    categories/
      data/
        category_summary.dart
        category_detail.dart
        category_repository.dart
        api_category_repository.dart
        fixture_category_repository.dart
      presentation/
        categories_controller.dart
        category_detail_controller.dart
        categories_screen.dart
        category_detail_screen.dart
        widgets/
          category_tile.dart
          category_header.dart
```

This structure is illustrative.

Create only necessary files.

### Reuse existing foundations

- `FeatureDependencies`.
- `ApiClient`.
- `AuthSession`.
- `AppDiagnostics`.
- `AsyncViewState<T>`.
- `AppLoadingView`.
- `AppEmptyView`.
- `AppErrorView`.
- `AppInlineError`.
- `ErrorPresentationMapper`.
- Existing product summary model.
- Existing product card.
- Existing catalog pagination behavior.
- Existing image presentation.
- Existing `go_router`.
- Existing Material 3 theme.

### Avoid duplication

Do not create:

- A second ApiClient.
- A second product model.
- A second product card.
- A separate category-specific product API parser.
- A second global state manager.
- A new router.
- A new design system.

Where Phase 17.1's catalog repository does not yet support a category parameter, extend its typed query interface minimally.

---

## 6. Category models

Create typed representations for:

### CategorySummary

- Stable opaque ID.
- API-returned name.
- API-returned slug.
- Image representation.

### CategoryDetail

- Stable opaque ID.
- API-returned name.
- API-returned slug.
- Description.
- Image.
- Other fields explicitly documented by CAT-004.

Do not invent:

- Product counts.
- Subcategory counts.
- Popularity scores.
- Display rankings.
- Promotional copy.
- Category-specific discounts.
- Availability flags absent from the API.

### Parsing

- Validate required fields.
- Respect documented nullability.
- Reject malformed identifiers.
- Do not synthesize missing images.
- Preserve server-provided slugs.
- Handle optional descriptions safely.
- Use existing typed API failure conventions.

---

## 7. Category repository

Create one category repository abstraction.

Suggested operations:

```dart
abstract interface class CategoryRepository {
  Future<CategoryPage> getCategories({
    required int page,
  });

  Future<CategoryDetail> getCategory(String category);
}
```

Adapt to the existing cancellation and request conventions.

### Requirements

- Public requests only.
- No Clerk bearer token.
- Use existing ApiClient.
- Parse the canonical response envelope.
- Preserve pagination metadata.
- Propagate existing typed errors.
- Support cancellation.
- No automatic retry.
- No duplicate HTTP transport.
- No backend modification.

---

## 8. Categories index screen

Replace the categories placeholder with a functional category-discovery screen.

### Required UI

- Clear page heading.
- Short editorial introduction.
- Category imagery.
- Category names.
- Accessible category navigation.
- Loading state.
- Empty state.
- Error state.
- Refresh.
- Pagination when needed.

### Design direction

Treat categories as furniture-room stories, not administrative taxonomy records.

For example, a Living Room category may use a large room-context photograph with the category name clearly separated from the image.

Do not place low-contrast text over busy photography.

### Layout

On narrow Android screens:

- Prefer large, readable category tiles.
- Use generous whitespace.
- Maintain strong photography hierarchy.
- Avoid overly dense multi-column grids.

On wider screens:

- Adapt column count using existing responsive tokens.
- Preserve image quality and text readability.

Do not create decorative category icon tiles.

---

## 9. Category detail screen

Implement a public category landing screen.

### Required elements

1. Category name.
2. Category description, when available.
3. Category image, when available.
4. Furniture product listing.
5. Loading and empty states.
6. Recoverable error handling.
7. Pagination.
8. Navigation back to categories.

### Data loading

Fetch CAT-004 to establish category identity and details.

Fetch category-filtered CAT-001 products using the canonical API-returned slug.

Use:

```text
category=<canonical slug>
product_type=MADE_TO_ORDER
```

Do not rely on locally inferred category relationships.

### Independent failures

Handle category-detail and product-list failures coherently.

If the category exists but product loading fails, preserve valid category information and show a recoverable product-list error.

If the category itself returns 404, show a clear unavailable-category state.

Do not display an empty product state when the actual category request failed.

---

## 10. Product listing integration

Reuse Phase 17.1's product listing components and state conventions.

### Requirements

- Display only made-to-order furniture.
- Preserve server ordering.
- Preserve server pagination.
- Respect `has_next`.
- Prevent duplicate page requests.
- Cancel obsolete requests.
- Preserve existing products on pagination failure.
- Allow explicit safe retry.
- Avoid stale results after changing category.
- Use stable product IDs as widget keys.

### Navigation

Product cards should navigate to the existing product-detail route.

Phase 17.3 will implement the complete detail experience.

Do not implement the product gallery or variant selector now.

---

## 11. Development fixtures

If Phase 17.1 implemented an approved `CATALOG_DATA_SOURCE=fixtures` mode, extend it to category browsing.

### Required behavior

In local fixture mode:

- Category index uses fixtures.
- Category detail uses fixtures.
- Category product listings use the same fixture catalog.
- Category IDs and slugs remain consistent.
- Product-category relationships are coherent.
- Category imagery is available.
- Pagination remains realistic.

### Source of fixtures

Prefer existing approved Next.js category fixtures and images.

Do not invent a separate contradictory taxonomy.

Do not use random image URLs.

If bundled assets are used, declare them through the existing Flutter asset configuration.

### Environment restrictions

- Fixtures only in approved local debug mode.
- Staging uses the real API.
- Production uses the real API.
- No fallback to fixtures after an API error.
- No fixture-only production routes.
- No separate category-source switch unless required by the existing architecture.

If Phase 17.1 did not implement fixture mode, do not create an inconsistent category-only fixture system. Record the dependency and follow the project's approved development-data decision.

---

## 12. Category imagery

Use real category image URLs or approved fixture assets.

### Requirements

- Preserve aspect ratio.
- Avoid distortion.
- Use a stable image container.
- Provide loading feedback.
- Provide a neutral fallback.
- Use meaningful semantics.
- Do not log image URLs.
- Avoid uncontrolled image prefetch.
- Avoid unnecessary caching dependencies.

Use the existing image-handling approach from Phase 17.1.

Do not create a second image loader solely for categories.

---

## 13. Navigation and deep links

Reuse Phase 16.6's central `go_router`.

### Required routes

Use the already-approved categories index and category detail routes.

Do not invent route names or paths.

### Route parameter handling

- Accept validated identifiers according to the existing route contract.
- Prefer the API-returned slug for canonical navigation.
- Do not derive a slug from the category name.
- Reject malformed parameters.
- Handle missing categories.
- Preserve back navigation.
- Avoid unsafe redirects.
- Do not introduce authentication guards for public categories.

### Navigation flow

```text
Home
  |
  v
Categories
  |
  v
Category Detail
  |
  v
Product Detail (Phase 17.3)
```

The category landing screen must also support returning to the categories index.

### Deep links

Verify direct navigation to a valid category route.

Verify invalid identifiers and unknown categories.

Do not modify Android App Links configuration without the approved production domain.

---

## 14. Design system

Follow `DESIGN.md` and canonical `tokens.css`.

### Brand character

- Calm.
- Crafted.
- Architectural.
- Warm.
- Editorial.
- Accessible.

### Typography

- Young Serif for editorial category headings.
- Existing platform sans for UI controls and metadata.
- Approved type scale only.

### Color

Use generated semantic tokens for:

- Warm canvas.
- Paper surfaces.
- Editorial surfaces.
- Primary and secondary text.
- Charcoal primary actions.
- Selective brand accent.

Do not hard-code color values in feature widgets.

### Photography

Prefer room-context furniture imagery.

Images should explain the character of each category.

Do not use generic stock imagery unrelated to the category.

### Prohibited patterns

- Dashboard-like category grids.
- Emoji category icons.
- Glassmorphism.
- Gradients.
- Decorative blobs.
- Excessive shadows.
- Badge-heavy layouts.
- Random accent colors.
- Arbitrary typography.
- Over-rounded tiles.
- Generic AI-generated marketing sections.

---

## 15. Accessibility

Verify:

- Semantic category headings.
- Meaningful image alternatives.
- Accessible category-card tap targets.
- Visible keyboard focus.
- Logical traversal order.
- 2× text scaling.
- Narrow-screen reflow.
- Landscape usability.
- Correct contrast.
- No clipped category names.
- Screen-reader-friendly loading.
- Clear empty states.
- Clear error recovery.
- Reduced-motion behavior.

Avoid redundant semantics when a category card already has one accessible label.

---

## 16. Error handling

Reuse Phase 16.8.

### Categories loading

Show a shared loading view.

### Empty category collection

Show a successful empty state.

Do not fabricate categories.

### Category collection failure

Show a safe mapped error with an explicit retry callback.

### Missing category

Handle `RESOURCE_NOT_FOUND` 404 as an unavailable category.

### Category with no products

Show a category-specific empty-product message.

Do not imply that the category itself is missing.

### Product pagination failure

Preserve already loaded products.

Show an inline recoverable error.

### Cancellation

Do not display cancellation as an error.

---

## 17. Diagnostics

Use the existing AppDiagnostics interface.

Record only safe events.

Examples:

- Category collection load failure.
- Category detail failure.
- Category product listing failure.
- Invalid category response.

Do not log:

- Full URLs.
- Query strings.
- Raw exception messages.
- Customer information.
- Raw API payloads.
- Arbitrary resource identifiers.

Preserve request-ID correlation where supported.

Avoid duplicate events for failures already recorded by ApiClient.

---

## 18. Performance

Requirements:

- Lazy category rendering.
- Paginated category collection.
- Lazy category product rendering.
- No duplicate requests during rebuilds.
- No unnecessary Clerk calls.
- Cancel obsolete requests.
- Avoid repeated image decoding.
- Stable widget keys.
- No unbounded prefetch.
- No unnecessary additional dependencies.

Measure scrolling behavior and request counts where practical.

Do not claim performance improvements without evidence.

---

## 19. Automated tests

### A. Models

Test:

1. Valid category summary.
2. Valid category detail.
3. Missing required fields.
4. Invalid slug.
5. Optional image behavior.
6. Optional description behavior.
7. Malformed image data.
8. Opaque category ID handling.

### B. Repository

Test:

9. Public GET without bearer token.
10. CAT-003 response decoding.
11. CAT-004 response decoding.
12. Collection pagination.
13. Empty collection.
14. Category 404.
15. API error preservation.
16. Transport error preservation.
17. Cancellation.
18. No automatic retries.

### C. Category product integration

Test:

19. Canonical category query parameter.
20. `product_type=MADE_TO_ORDER`.
21. No nested category-products endpoint.
22. Server pagination.
23. Product listing reuse.
24. Empty category product list.
25. Pagination failure.
26. Stale request suppression.

### D. Widgets

Test:

27. Categories screen loading.
28. Categories screen success.
29. Categories screen empty.
30. Categories screen error.
31. Category card navigation.
32. Category detail loading.
33. Category detail success.
34. Category detail 404.
35. Category with no products.
36. Image fallback.
37. 2× text scaling.
38. Narrow-screen layout.
39. Accessible semantics.

### E. Routing

Test:

40. Public categories route.
41. Valid category deep link.
42. Invalid category parameter.
43. Unknown category.
44. Back navigation.
45. Product detail navigation.
46. No authentication requirement.

### F. Fixtures

Where fixture mode exists, test:

47. Fixture category collection.
48. Fixture category detail.
49. Fixture category-product relationships.
50. Missing fixture category.
51. Production fixture rejection.
52. Consistent repository source selection.

### G. Regression

Verify:

- All previous tests pass.
- Phase 17.1 home/catalog remains functional.
- No Clerk regression.
- No ApiClient regression.
- No router regression.
- No design-token drift.
- No deferred commerce routes.
- No backend/API contract modifications.

This is a coverage matrix, not a requirement to create exactly 52 test methods.

---

## 20. Live Laravel verification

When the local Laravel backend is available, verify:

```http
GET /api/v1/categories
GET /api/v1/categories/{category}
GET /api/v1/products?category={slug}&product_type=MADE_TO_ORDER
```

Use actual API-returned category slugs.

Verify:

- Public access.
- Category collection.
- Category detail.
- Missing-category 404.
- Category-filtered products.
- Empty product listing.
- Pagination.
- Image loading.

Do not modify production data.

Do not run destructive migrations.

If local data is empty, report that accurately and use deterministic fixtures for UI testing.

---

## 21. Physical Android verification

If Marionette and a physical device are available:

1. Launch the app.
2. Open categories.
3. Scroll category cards.
4. Open a category.
5. Verify category header.
6. Verify filtered products.
7. Open a product card.
8. Navigate back.
9. Verify empty/error presentation where reproducible.
10. Inspect text scaling and layout.

Do not add a production-only test menu.

If physical testing is unavailable, report the limitation.

Do not claim device verification from widget tests.

---

## 22. Documentation

Update:

- Flutter README.
- Feature architecture guide.
- Phase 17.2 completion record.
- `docs/decisions.md` only for durable new decisions.

Document:

- CAT-003 usage.
- CAT-004 usage.
- CAT-001 category filtering.
- Category model contracts.
- Repository architecture.
- Category navigation.
- Fixture integration.
- Request-only enforcement.
- Pagination.
- Error states.
- Image handling.
- Accessibility.
- Automated tests.
- Live API verification.
- Physical-device verification.
- Remaining limitations.

---

## 23. Verification commands

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

Report the actual results.

If intentional Markdown hard-break spaces remain in unrelated documentation, distinguish those from newly introduced whitespace problems.

Do not silently modify unrelated staged files.

---

## 24. Completion report

Provide:

1. Repository inspection findings.
2. Phase 17.1 prerequisite status.
3. Exact category API fields used.
4. Category index implementation.
5. Category detail implementation.
6. Category-product filtering.
7. Repository and model architecture.
8. Fixture integration status.
9. Navigation and deep links.
10. Design-system compliance.
11. Accessibility results.
12. Error/loading behavior.
13. Automated test count and results.
14. Live Laravel verification.
15. Physical Android verification.
16. Files added and modified.
17. Documentation changes.
18. Remaining blockers.
19. Final Phase 17.2 PASS/FAIL.

## 25. Definition of Done

Phase 17.2 passes when:

- Public categories index works.
- CAT-003 data is decoded correctly.
- CAT-004 category detail works.
- Category product listings use CAT-001.
- Made-to-order filtering is enforced.
- Existing product cards are reused.
- Pagination is correct.
- Loading, empty, error, and refresh states work.
- Images load or fail gracefully.
- Navigation uses the existing router.
- Category deep links are validated.
- Fixture behavior is consistent with Phase 17.1.
- No backend or frozen API changes occur.
- No commerce features are introduced.
- Accessibility is tested.
- Existing and new tests pass.
- Debug APK builds.
- Token synchronization passes.
- Documentation is complete.

**Final instruction:** Build a refined category-discovery experience using the actual public Laravel category contract and existing Flutter foundations. Preserve the request-only launch, reuse Phase 17.1 catalog components, and stop at Phase 17.2.