# Phase 17.4 — Flutter Search & Filtering

**Project:** SL Furnitures — Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Prerequisites:** Group P and Phases 17.1–17.3  
**Scope:** Public furniture search, filtering, sorting, and search-result navigation  
**Status:** READY FOR IMPLEMENTATION

## 1. Objective

Implement a production-quality furniture search and filtering experience using the existing Flutter catalog architecture and the frozen Laravel API.

Customers must be able to:

1. Search furniture by supported textual criteria.
2. Filter furniture by approved categories.
3. Apply other filters explicitly supported by CAT-001.
4. Sort results using documented options.
5. Combine search, filtering, and sorting.
6. Browse paginated results.
7. Open product details.
8. Clear search and filters.
9. Recover from network failures.
10. Use the experience without signing in.

The UI must be visually consistent with the SL Furnitures home, catalog, categories, and product-detail screens.

**Initial production remains REQUEST ONLY. Only MADE_TO_ORDER products may be displayed.**

Do not implement cart, checkout, payments, orders, favorites, or product recommendations.

---

## 2. Mandatory repository inspection

Before making changes, inspect:

- Root `AGENTS.md`.
- Flutter `AGENTS.md`, if present.
- Group Q Phase 17.4 requirements.
- Phase 17.1 completion report.
- Phase 17.2 completion report.
- Phase 17.3 completion report.
- Flutter README.
- `lib/features/README.md`.
- `docs/decisions.md`.
- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- Laravel CAT-001 controller, query handling, resources, and tests.
- Existing Next.js search/filter implementation, if available.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/tokens.css`.
- Flutter theme and generated token adapters.
- Existing `go_router` configuration.
- Existing catalog repository and typed product models.
- Existing category repository.
- Existing fixture repository.
- Existing `AsyncViewState<T>` implementation.
- Existing `AppDiagnostics` integration.

### Required inspection findings

Before implementation, establish:

1. Exact CAT-001 search parameter name.
2. Exact category filter parameter.
3. Exact accepted sort fields.
4. Exact sort direction syntax.
5. Whether price filtering is supported.
6. Whether availability filtering is supported.
7. Exact pagination parameters and limits.
8. Existing catalog repository query model.
9. Existing product card and product-list widgets.
10. Existing fixture catalog capabilities.
11. Existing search route.
12. Existing error, cancellation, and diagnostics behavior.

If a requested filter is unsupported by the frozen API, do not invent it.

If Phase 17.3 is incomplete, report the prerequisite gap instead of silently implementing product-detail work.

---

## 3. Frozen API integration

Use the existing public catalog endpoint:

```http
GET /api/v1/products
```

CAT-001 is the authoritative source for search, filtering, sorting, and pagination.

### Product-type restriction

Every search request must enforce:

```text
product_type=MADE_TO_ORDER
```

This restriction is not an optional user filter during the request-only launch.

Do not expose an IN_STOCK selection.

Do not allow a search reset to remove the made-to-order restriction.

### Search parameter

Use the exact documented CAT-001 text-search parameter.

For example, if the frozen API defines `search`, a request might resemble:

```http
GET /api/v1/products?search=sofa&product_type=MADE_TO_ORDER
```

This example is illustrative. Confirm the exact parameter name and accepted behavior before coding.

Do not create a separate `/search` endpoint.

### Category filtering

Use the existing category parameter:

```text
category=<canonical category slug or supported identifier>
```

Do not use:

```http
GET /api/v1/categories/{category}/products
```

### Sorting

Only expose sort fields and directions supported by the frozen contract.

Previously documented fields include:

- `created_at`
- `price`
- `name`

Verify the exact wire syntax for ascending and descending order.

Do not infer syntax from conventional Laravel patterns.

### Pagination

Use the existing canonical pagination envelope.

Respect:

- `current_page`
- `per_page`
- `total`
- `last_page`
- `has_next`
- `has_previous`

Do not invent cursor pagination.

Do not exceed the server's documented maximum page size.

---

## 4. Search architecture

Reuse the existing catalog repository.

Do not create a separate HTTP client or duplicate product parsing.

Suggested dependency flow:

```text
SearchScreen
     |
     v
SearchController
     |
     v
CatalogRepository
     |
     +---------------------+
     |                     |
     v                     v
ApiCatalogRepository   FixtureCatalogRepository
     |                     |
     v                     v
Laravel CAT-001       Approved local fixtures
```

### Shared query model

If the existing catalog repository lacks a typed query representation, introduce or extend a focused catalog query model.

It should represent only approved CAT-001 parameters:

- Search text.
- Category identifier.
- Supported sorting.
- Supported filters.
- Page number.
- Page size.
- Fixed made-to-order product type.

Avoid passing arbitrary query maps from presentation widgets.

Avoid duplicating query construction in the search screen.

### Query invariants

The query model must:

1. Preserve the fixed product-type restriction.
2. Validate sort selections.
3. Normalize search text according to the API contract.
4. Reset pagination when criteria change.
5. Preserve criteria when loading another page.
6. Generate deterministic query parameters.
7. Avoid unsupported filters.
8. Remain testable without Flutter widgets.

---

## 5. Search screen

Replace the existing search placeholder with a functional public screen.

### Main structure

```text
Search Furniture
      |
      v
Search Input
      |
      v
Filter / Sort Controls
      |
      v
Active Filter Summary
      |
      v
Results Count / Status
      |
      v
Product Listing
```

### Required UI

- Search field.
- Clear-search action.
- Category filter.
- Approved sorting controls.
- Active filter indication.
- Reset filters.
- Results area.
- Loading state.
- Empty state.
- Error state.
- Pagination.
- Product-detail navigation.

Do not create decorative controls that have no functionality.

### Initial state

Choose an intentional initial experience consistent with the existing catalog.

Acceptable approaches include:

- Displaying made-to-order furniture before a query is entered.
- Displaying a lightweight search prompt until the user enters criteria.

Inspect the existing design and choose the approach that provides the most consistent user experience.

Do not display fabricated trending searches, popularity rankings, or search history.

---

## 6. Search input behavior

Implement a responsive search field using existing Material 3 components and design tokens.

### Requirements

- Clear label.
- Search icon.
- Clear-text control.
- Keyboard search action.
- Appropriate text input configuration.
- Accessible semantics.
- Stable focus behavior.
- No automatic authentication.
- No raw query logging.

### Search triggering

Use a deliberate request strategy.

For example:

- Debounced search while typing.
- Immediate search when the keyboard search action is submitted.

If using debouncing:

- Choose a modest, documented interval.
- Cancel pending requests when text changes.
- Avoid issuing duplicate requests for identical normalized queries.
- Dispose of debounce resources correctly.

Do not add a third-party debouncing dependency merely for this feature.

### Query normalization

Trim surrounding whitespace.

Do not silently alter meaningful user input unless the API contract requires it.

Do not implement custom stemming, fuzzy matching, or relevance ranking in Flutter.

Laravel remains responsible for server-side search behavior.

---

## 7. Category filtering

Reuse the Phase 17.2 category repository.

### Requirements

- Load approved public categories.
- Display API-returned category names.
- Use API-returned identifiers or slugs.
- Support an unfiltered category state.
- Preserve the selected category while sorting or paginating.
- Clear category filtering without clearing unrelated criteria unless explicitly requested.

### UI presentation

Prefer a compact category filter control or a Material 3 modal bottom sheet.

Avoid displaying a large taxonomy dashboard above every search result.

### Hierarchy

Do not invent subcategory data.

The public category collection is not a complete recursive taxonomy.

Only present deeper category levels if the frozen API and existing approved data explicitly support them.

### Filter interaction

When a category changes:

1. Update the query state.
2. Reset to page one.
3. Cancel obsolete requests.
4. Fetch new results.
5. Update the active filter presentation.

---

## 8. Additional filtering

Inspect CAT-001 for all supported filters.

Potential examples include:

- Availability.
- Price range.
- Category.
- Product type.

Only implement filters that are both documented and meaningful for the request-only launch.

### Availability

If supported, distinguish the backend's coarse public availability from actual stock quantities.

Do not expose internal inventory values.

Do not treat MADE_TO_ORDER as a conventional warehouse stock condition.

### Price range

If the API supports price filtering:

- Use the documented query parameter names.
- Use integer minor units.
- Respect the TZS money representation.
- Validate minimum and maximum values.
- Avoid floating-point money calculations.
- Handle absent prices according to the frozen contract.
- Do not invent minimum or maximum product prices.

### Unsupported filters

Do not add filters for:

- Color.
- Material.
- Dimensions.
- Fabric.
- Style.
- Ratings.
- Discounts.
- Delivery speed.

unless the current frozen CAT-001 contract explicitly supports them.

Do not parse product names or variant labels to fabricate structured filters.

---

## 9. Sorting

Provide a concise sorting interface.

Only include API-supported choices.

Potential user-facing labels may include:

- Newest.
- Price: Low to High.
- Price: High to Low.
- Name: A to Z.

These are examples, not authorization to invent unsupported wire parameters.

### Sorting requirements

- Map UI options to approved API values.
- Preserve search and filters.
- Reset pagination.
- Cancel obsolete requests.
- Preserve server ordering.
- Avoid sorting only the currently loaded page.

Never perform client-side sorting of a partially paginated server result and present it as a globally sorted catalog.

---

## 10. Results presentation

Reuse Phase 17.1 product cards and listing widgets.

### Required behavior

- Correct product image.
- Product name.
- Informational price.
- Made-to-order status.
- Product-detail navigation.
- Stable keys.
- Lazy rendering.
- Responsive layout.

### Results count

Use the backend pagination total where available.

Do not calculate total results from the number of currently loaded products.

### Empty results

Show a helpful message such as:

"No furniture matches your search. Try another keyword or adjust your filters."

Provide a working reset or edit-search action.

Do not treat zero results as an API error.

### Error state

Use existing error presentation components.

Do not replace the result list with a generic exception message.

---

## 11. Pagination

Reuse the existing catalog pagination architecture.

### Requirements

- Initial page load.
- Additional page loading.
- Correct `has_next` handling.
- Duplicate request suppression.
- Stable result ordering.
- Explicit retry after page failure.
- No accidental duplicate product entries.
- No page mixing across different searches.
- Preserve loaded results on pagination failure.

### Criteria changes

Every new search/filter/sort combination is a new result set.

Reset pagination to the first page.

Discard stale results from the previous criteria after the new search state is established.

Use request generation identifiers or existing cancellation patterns to prevent out-of-order responses from corrupting the UI.

---

## 12. Fixture integration

The project already supports development-only fixture catalog data.

Reuse:

```text
CATALOG_DATA_SOURCE=fixtures
```

Do not create a separate `SEARCH_DATA_SOURCE` setting.

### Fixture search

Implement deterministic search behavior over the approved fixture dataset.

Match the relevant documented search semantics as closely as practical.

Do not claim exact equivalence with Laravel full-text search where the fixture implementation cannot reproduce it.

### Fixture filters

- Preserve category relationships.
- Preserve made-to-order restriction.
- Implement only supported filters.
- Respect selected sorting.
- Support pagination.
- Return coherent empty results.

### Fixture safety

- Local debug only.
- Staging uses Laravel.
- Production uses Laravel.
- No fallback after API failure.
- No production fixture leakage.

### Fixture limitations

Document any behavior that cannot be faithfully reproduced without the Laravel search implementation.

Do not introduce a separate contradictory fixture catalog.

---

## 13. Navigation

Reuse the existing central `go_router`.

### Required navigation

- Home to search, where an approved entry point exists.
- Catalog to search, where appropriate.
- Search results to product detail.
- Search back to the previous screen.
- Correct direct search-route navigation.

### Route state

If the existing router supports search query parameters, use its approved conventions.

Do not invent public route parameters without checking the route contract.

### Future navigation shell

Phase 17.15 is reserved for the complete global app bar and navigation drawer.

Do not implement that shell in Phase 17.4.

However, keep the search screen compatible with a future shared navigation shell:

- No independent global drawer.
- No second router.
- No duplicated app-wide navigation logic.
- No hard-coded global app bar architecture.
- Preserve correct back navigation.

---

## 14. Design system

Follow the canonical design system.

### Visual direction

- Calm.
- Architectural.
- Warm.
- Editorial.
- Photography-led.
- Minimal.

### Search field

Use a clean Material 3 input with restrained decoration.

### Filters

Use compact, accessible controls.

Avoid overly dense chip collections.

### Results

Allow furniture photography to remain visually dominant.

### Typography

- Young Serif for appropriate editorial headings.
- Existing UI sans for search fields, controls, and metadata.

### Tokens

Use generated semantic colors, spacing, typography, shape, and motion tokens.

Do not introduce hard-coded colors or arbitrary spacing.

### Prohibited patterns

- Marketplace-style promotional badges.
- Fake discount banners.
- Trending-search claims without data.
- Search-result ratings.
- Gradient filter panels.
- Glassmorphism.
- Decorative blobs.
- Excessive elevation.
- Unapproved iconography.
- Unnecessary animations.

---

## 15. Accessibility

Verify:

1. Search-field label.
2. Keyboard search action.
3. Clear-search semantics.
4. Category filter accessibility.
5. Sort control labels.
6. Selected filter announcements.
7. Focus management.
8. Screen-reader result announcements.
9. 2× text scaling.
10. Narrow-screen layout.
11. Landscape usability.
12. Adequate touch targets.
13. Contrast.
14. Reduced-motion behavior.
15. Accessible empty states.
16. Accessible error recovery.
17. No clipped product names or prices.

Do not rely solely on color to communicate active filters.

---

## 16. Error handling

Reuse Phase 16.8 error components.

### Search failure

Show a safe, mapped error with explicit retry.

### Category filter loading failure

Allow the search experience to remain usable when category options cannot be loaded, where the existing architecture permits it.

### Pagination failure

Preserve already loaded products.

### Invalid query

Handle documented validation errors without displaying raw API payloads.

### Cancellation

Do not show cancelled requests as failures.

### Offline state

Display the existing connection-failure presentation.

Do not fall back to fixture data.

---

## 17. Diagnostics

Reuse AppDiagnostics.

The recently identified transport-error diagnostic defect belongs to Phase 16.4/16.9 corrective maintenance.

Before implementing Phase 17.4, inspect whether that fix has actually been merged.

Do not assume it is complete merely because the fix was approved.

### Search diagnostics

Record only safe, meaningful failures through the existing vocabulary.

Do not log:

- Search text.
- Query strings.
- Product IDs.
- Customer identity.
- URLs.
- Raw response bodies.
- Raw exceptions.
- Authentication tokens.

Do not create duplicate transport diagnostics.

Do not modify core diagnostics unless a directly blocking defect remains.

---

## 18. Performance

Requirements:

- Debounce or deliberate submit-based search.
- Cancellation of obsolete requests.
- No duplicate queries from widget rebuilds.
- No unnecessary category reloads.
- Lazy result rendering.
- Existing image-loading reuse.
- No unbounded prefetch.
- Stable list keys.
- Minimal state rebuilds.
- No new global state-management dependency.

Do not implement a complex search caching layer without evidence of need.

---

## 19. Automated tests

Add deterministic tests covering:

### A. Query construction

1. Fixed `MADE_TO_ORDER` restriction.
2. Search parameter encoding.
3. Category parameter encoding.
4. Sort mapping.
5. Pagination.
6. Combined criteria.
7. Reset behavior.
8. Unsupported filter rejection.

### B. Search controller

9. Initial state.
10. Search submission.
11. Search clear.
12. Category change.
13. Sort change.
14. Pagination reset.
15. Debounce behavior, if implemented.
16. Cancellation.
17. Stale response suppression.
18. Empty results.
19. Error handling.
20. Retry.

### C. Repository

21. Public API request.
22. Correct CAT-001 path.
23. No bearer token.
24. Correct response parsing.
25. Pagination metadata.
26. API validation failure.
27. Transport failure.
28. No automatic retry.

### D. Fixtures

29. Text search.
30. Category filtering.
31. Combined search and category.
32. Sorting.
33. Pagination.
34. No matches.
35. Made-to-order restriction.
36. Environment restrictions.

### E. Widgets

37. Search input.
38. Clear action.
39. Filter selection.
40. Sort selection.
41. Active filters.
42. Results count.
43. Empty state.
44. Error state.
45. Product card navigation.
46. 2× text scaling.
47. Keyboard behavior.
48. Accessibility semantics.

### F. Regression

Verify:

- Home/catalog still works.
- Categories still work.
- Product detail still works.
- Existing router still works.
- Clerk remains unchanged.
- ApiClient remains unchanged.
- Diagnostics remain privacy-safe.
- Fixture mode remains restricted.
- No deferred commerce features appear.
- Token synchronization passes.

This is a test coverage checklist, not a requirement to create exactly 48 test methods.

---

## 20. Live Laravel verification

When Laravel is available, verify actual CAT-001 behavior using documented parameters.

Test:

- Empty search.
- Matching search.
- No-match search.
- Category filtering.
- Supported sorting.
- Combined criteria.
- Pagination.
- Invalid query validation.
- Anonymous access.

Use real API data.

Do not modify production data.

Do not run destructive migrations.

If Laravel is unavailable, report that clearly and rely on deterministic fixture-backed tests for UI verification.

---

## 21. Physical Android verification

Use Marionette MCP when available.

Verify on the real device:

1. Launch the app.
2. Open search.
3. Enter a search term.
4. Submit or wait for debounce.
5. Inspect results.
6. Apply category filter.
7. Change sorting.
8. Clear filters.
9. Open a product detail.
10. Navigate back.
11. Verify keyboard behavior.
12. Verify empty results.
13. Verify offline error presentation where reproducible.
14. Inspect narrow-screen and text-scale behavior.

Capture actual results in the completion report.

Do not claim physical-device verification if Marionette is unavailable.

---

## 22. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Phase 17.4 completion record.
- `docs/decisions.md` only for durable architectural decisions.

Document:

1. Search query model.
2. Supported filters.
3. Sort options.
4. API parameter mapping.
5. Fixed made-to-order restriction.
6. Fixture search behavior.
7. Pagination.
8. Navigation.
9. Accessibility.
10. Error handling.
11. Diagnostics.
12. Automated tests.
13. Live API verification.
14. Marionette verification.
15. Remaining limitations.

Do not rewrite earlier phase completion histories.

---

## 23. Verification commands

Run from the Flutter project directory:

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

Report actual results.

Do not modify unrelated staged files.

Do not commit credentials or environment secrets.

---

## 24. Completion report

Provide:

1. Repository inspection findings.
2. Exact CAT-001 search/filter/sort parameters.
3. Search architecture.
4. Query model.
5. Category filtering.
6. Sorting.
7. Pagination.
8. Fixture behavior.
9. Navigation.
10. Design-system compliance.
11. Accessibility.
12. Error/loading states.
13. Diagnostics behavior.
14. Automated test count and results.
15. Live Laravel verification.
16. Physical Android verification.
17. Files changed.
18. Documentation updated.
19. Remaining blockers.
20. Final Phase 17.4 PASS/FAIL.

---

## 25. Definition of Done

Phase 17.4 passes when:

- The existing search placeholder is replaced.
- Public search works through CAT-001.
- Only made-to-order products are returned.
- Supported category filtering works.
- Supported sorting works.
- Combined criteria work.
- Pagination works correctly.
- Obsolete requests cannot overwrite current results.
- Product cards navigate to product details.
- Empty, loading, and error states work.
- Fixture mode supports realistic search testing.
- Staging and production remain API-only.
- Search and filters are accessible.
- Existing tests remain green.
- New tests pass.
- Token synchronization passes.
- Debug APK builds.
- Documentation is complete.
- No backend/API changes occur.
- No deferred commerce features are introduced.

**Final instruction:** Implement a polished, maintainable search and filtering experience using the existing CAT-001 API, catalog models, category repository, fixture mode, design tokens, and Flutter routing. Do not implement the future Phase 17.15 global navigation shell. Stop after Phase 17.4.