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

## Module Convention

Each implemented feature owns its presentation and feature-specific state. It
may add `data/` for API DTOs/repositories and `domain/` only when a domain model
adds meaning beyond the Laravel response. Small features may keep these files
at the feature root instead of creating every layer.

Feature composition receives `FeatureDependencies` through explicit
constructors. It may use the shared `ApiClient` and optional `AuthSession`; it
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

Catalog, categories, product detail, search, furniture requests, enquiries, and
account are future feature families. Cart, checkout, payments, orders, order
tracking, and favorites remain absent under request-only production scope.

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
Phase 16.8; logging and diagnostics belong to Phase 16.9.
