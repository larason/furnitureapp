# Feature Modules

Features are organized by customer capability, not by technical layer:

```text
lib/features/
  catalog/
  categories/
  product_detail/
  search/
  furniture_requests/
  enquiries/
  account/
```

Only create a feature directory when its owning Group Q phase begins. Do not
create placeholder repositories, models, controllers, or screens just to fill
this structure.

## Catalog (Phase 17.1)

`catalog/` owns typed CAT-001 product summaries, API and development-fixture
repositories, pagination, the reusable `ProductGridSliver` listing, the shared
`CatalogImage`, and the product card. `home/` composes the first catalog page
into the request-only storefront. Both use the public `MADE_TO_ORDER` filter,
`AsyncViewState`, shared error presentation, and the existing router.

## Categories (Phase 17.2)

`categories/` owns the public category reads and the two screens built on them:

- `CategorySummary` / `CategoryDetail` model only documented CAT-003 and CAT-004
  fields. There is deliberately no `children` field: the collection is the flat
  list of active storefront categories beneath the structural root, not a
  recursive taxonomy tree.
- `CategoryRepository` reads `/categories` and `/categories/{category}` publicly,
  with no bearer token. `CategoryDetailController` runs those reads
  independently from the product listing so one failure never discards the other.
- Category products reuse `CatalogController` and `ProductGridSliver` through the
  optional `categorySlug` on `CatalogRepository.fetchProducts`, which maps to the
  canonical `GET /products?category={slug}&product_type=MADE_TO_ORDER`. The
  rejected nested `/categories/{category}/products` route is never requested.
- Category photography reuses `CatalogImage`; no second image loader exists.

## Product Detail (Phase 17.3)

`product_detail/` owns the public CAT-002 read and the single product page:

- `ProductDetail` extends the shared `ProductSummary` rather than re-parsing it.
  **Model ownership:** `catalog/data/product_summary.dart` owns `Money`,
  `ProductCategorySummary`, `ProductImageSummary`, and `ProductSummary`, and
  stays the only place those JSON rules live. Product detail composes them and
  adds only what CAT-002 adds on top, mirroring the `CategoryDetail` /
  `CategorySummary` split. There is no generic `models/` directory and no second
  product parser.
- `ProductDetailRepository` reads `/products/{product}` publicly, with no bearer
  token, exactly once per load. `CAT-005` is never called: the embedded variant
  summaries are the only variant data the page uses.
- `ProductDetailController` owns loading, cancellation, stale-response
  suppression, the 404 unavailable state, and transient variant selection.
  Selection is presentation state only — nothing is reserved, ordered, or
  submitted — and a new route identifier rebuilds the controller so a previous
  product cannot survive.
- `ProductGallery` reuses `CatalogImage`, so there is still exactly one catalog
  image loader. It preserves the backend's deterministic order, opens on the
  `is_primary` image, and uses a native `PageView` for swipe navigation.
- `ProductVariantSelector` shows only contract fields. No fabric, colour,
  dimension, material, or finish is derived from a variant name or SKU, and no
  inventory quantity is displayed.
- `ProductRequestAction` **enables** the "Request this furniture" button and
  opens the Phase 17.10 REQ-001 flow. No cart, checkout, payment, deposit,
  reservation, or "buy now" control exists in this feature.
- `FixtureProductDetailRepository` reuses `catalog/data/fixture_products.dart`,
  so a fixture catalog card and its detail page always agree. The synthetic
  gallery and option values it adds are documented development-only content.

## Search and Filtering (Phase 17.4)

`search/` owns the public CAT-001 search screen, while catalog data ownership
remains in `catalog/`:

- `CatalogQuery` is the typed allow-list for search text, server category slug,
  the four supported sort choices, page, and page size. It always emits
  `product_type=MADE_TO_ORDER` and never emits unsupported facets.
- `SearchController` applies one result set at a time, debounces typing for 350
  ms, submits keyboard/button searches immediately, preserves criteria while
  paging, and resets pagination for every criteria change.
- Category options reuse the Phase 17.2 `CategoryRepository` and load
  independently, so a category failure does not disable search, sorting, or
  browsing all furniture.
- Results reuse `CatalogController` and `ProductGridSliver`, including backend
  totals, explicit load-more/retry behavior, cancellation, stale-response
  suppression, stable product keys, and product-detail navigation.
- Fixture search intentionally matches name, slug, and category name only;
  Laravel also searches description, variant SKU, and variant attributes, which
  are not present in summary fixtures. This limitation is local-only.
- Search controls use Material 3 semantics and generated theme tokens. The
  submit icon explicitly uses the theme `onPrimary` foreground against the
  `primary` action surface for readable contrast.

## Furniture Requests (Phase 17.10)

`furniture_requests/` owns the REQ-001 customer intake flow. It uses the shared
`ApiClient`, optional `AuthSession`, and central router; it never stores contact
data or Clerk tokens. The form supports anonymous custom and catalog-linked
MADE_TO_ORDER requests, validates the frozen request fields for usability, and
leaves Laravel authoritative. An optional JPEG, PNG, WebP, or PDF is sent in the
same private multipart REQ-001 submission. Timeout or connection failures are
uncertain outcomes and are never retried automatically.

## Enquiries (Phase 17.11)

`enquiries/` owns the ENQ-001 general enquiry flow on the public `/contact`
route. It is a separate domain from `furniture_requests/` and never creates a
furniture request, order, quotation, payment, or reservation; the contact page
only links to the Phase 17.10 route.

- `EnquiryDraft` carries only the ENQ-001 allow-list: `subject`, `message`,
  `name`, `phone`, `email`, and an optional `product_id`. It holds no user
  identity — `user_id` is server-derived and is never sent, so Laravel's strict
  unknown-field rejection is never tripped by this client.
- `category` and `order_id` are deliberately not modelled. `category` is
  optional and §27.5 lets staff triage from `subject`/`message`; `order_id`
  belongs to the deferred order phases and anonymous callers may not send it.
- `validateEnquiry` mirrors the frozen bounds (subject 5–200, message 10–5000,
  name 120, phone 30, email 255) and the actor-aware contact rule: anonymous
  visitors need a name plus a reachable phone or email, an authenticated
  customer does not. Advisory only; Laravel stays authoritative.
- `ApiEnquiryRepository` posts JSON, or one inline `multipart/form-data` file
  under the canonical `attachment` field. `ENQ-007` separate upload is not used.
- `EnquiryController` owns the explicit `editing → submitting → success |
  uncertain` lifecycle. A signed-in session that cannot produce a token stops
  the submission instead of degrading to an anonymous enquiry. Timeouts and
  connection failures are uncertain outcomes — never auto-retried, never
  reported as "not submitted" — and the warning survives editing until the
  customer deliberately resubmits. Disposal cancels an in-flight request
  without notifying a disposed controller.
- Product context is internal support only: the router builds it from an
  already-loaded `ProductDetail` passed as route `extra`. No product-detail
  button was added, no identifier is derived from a name, and browsing history
  never attaches a product implicitly.
- The confirmation view shows only the returned `enquiry_status` and the opaque
  reference. It claims no email/SMS delivery, promises no response time, and
  never navigates to a private enquiry endpoint.

`core/attachments/pending_attachment.dart` owns the frozen V1 attachment limits,
the shared rejection wording, and the content-type hint. The enquiry picker
checks the reported size *before* reading the bytes, so an oversized file is
never loaded into device memory; Laravel still applies the authoritative limit to
whatever is uploaded. Phase 17.10 keeps its
own equivalent because this phase must leave Furniture Requests unchanged;
consolidating them is left to a later phase.

Account, cart, checkout, payment, order, and tracking surfaces remain outside
the implemented phases.

## Module Convention

Each implemented feature owns its presentation and feature-specific state. It
may add `data/` for API DTOs/repositories and `domain/` only when a domain model
adds meaning beyond the Laravel response. Small features may keep these files
at the feature root instead of creating every layer.

Feature composition receives `FeatureDependencies` through explicit
constructors. It may use the shared `ApiClient`, optional `AuthSession`, and
`AppDiagnostics`; it
must not construct another HTTP client, initialize Clerk, read secure storage,
read compile-time environment values, or implement Laravel authorization.

The central router in `lib/navigation/` remains authoritative. A feature may
provide route builders when implemented, but it must not create a second router,
`MaterialApp`, route registry, or protected-route policy.

## Dependency Direction

```text
feature presentation -> feature state -> feature repository -> core ApiClient
```

Shared foundations never import feature code. Cross-feature imports are avoided;
models shared by multiple features require an explicit ownership decision rather
than placement in a generic `models/` directory.

The one deliberate cross-feature dependency is `product_detail/ -> catalog/`:
the catalog feature owns the product models and the shared `CatalogImage`, and
product detail composes them instead of duplicating them. Any feature that needs
the same product money, image, or catalog media must reuse those owners too.

The application composition root owns `ApiClient`, its transport, and the Clerk
adapter. `FeatureDependencies` does not dispose them. Feature repositories and
controllers own only subscriptions/resources they create and must dispose those
resources with their feature lifecycle.

## Group Q Workflow

For a future catalog implementation:

1. Add catalog-local DTOs from the frozen API contract.
2. Add a catalog repository that receives the existing `ApiClient`.
3. Add feature-local state using Flutter primitives unless complexity proves a package necessary.
4. Build the screen with the existing Material 3 theme and token mappings.
5. Connect the screen through the existing `AppRouter`.
6. Add repository, state, and widget tests under matching `test/features/catalog/` paths.

Catalog, categories, product detail, search, furniture requests, and enquiries
are implemented feature families. Account is a future family. Cart, checkout,
payments, orders, order tracking, and favorites remain absent under request-only
production scope.

## Shared Async Presentation

Reusable async presentation lives in `lib/core/presentation/`; feature DTOs and
business models do not belong there. `AsyncViewState<T>` uses sealed Dart 3
states: `AsyncInitial`, `AsyncLoading`, `AsyncContent`, `AsyncEmpty`,
`AsyncFailure`, `AsyncRefreshing`, and `AsyncSubmitting`. Features choose only
the states their operation needs. `AsyncRefreshing` and `AsyncFailure` can keep
typed prior data visible; a refresh failure must not erase valid content by
default.

Use `AppLoadingView` for full-content or inline progress, `AppEmptyView` for a
successful empty result, and `AppErrorView`/`AppInlineError` for mapped expected
failures. Actions are supplied by the owning feature. No retry button is shown
without a real callback, and shared widgets never retry automatically.

`ErrorPresentationMapper` consumes `ApiError` and `ApiTransportException` after
the existing `ApiClient` has parsed them. It preserves HTTP semantics, field
paths, and request IDs while using safe user-facing messages. Cancellation maps
to no presentation by default. It never parses raw response bodies, error text,
or credentials. A 401 does not start a second auth flow; a 403 does not sign the
user out; a 429 does not invent a deadline.

Loading is not authentication state, empty data is not failure, and unexpected
exceptions use a generic safe fallback. Loading/error presentation is owned by
Phase 16.8; logging and diagnostics are available through
`FeatureDependencies.diagnostics`.

## Diagnostics Usage

Features record safe machine-readable failures through `AppDiagnostics`; they do
not call `print()`/`debugPrint()`, serialize exceptions, or pass API bodies,
headers, URLs, user identity, or user-entered text. Use the closed diagnostic
categories and codes with only typed allow-listed context. Cancellation is not an
error event unless a feature has a specific operational reason to record it.

A feature must not re-record a failure its `ApiClient` call already owns. Phase
17.3 is the worked example: every CAT-002 failure it can surface is already
recorded by `ApiClient`, so product detail adds no diagnostic event at all and
does not widen the closed `DiagnosticCode` vocabulary for a single screen.
