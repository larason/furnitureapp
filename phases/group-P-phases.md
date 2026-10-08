# Phase 16.7 — Flutter Feature/Module Structure

**Project:** SL Furnitures — Flutter Android Customer App  
**Group:** P — Flutter Application Foundation  
**Prerequisites:** Phases 16.1–16.6 complete  
**Status:** COMPLETED — 2026-10-08

## 1. Objective

Establish a clean, scalable, feature-first module architecture for the Flutter application.

The architecture must support future catalog, category, product detail, search, furniture request, enquiry, and account features while reusing the existing shared foundations.

The implementation must be proportional to the project's size.

Do not introduce enterprise-scale architecture patterns, excessive abstractions, or boilerplate solely for theoretical future requirements.

### Required outcomes

1. A clear feature/module directory convention.
2. Explicit dependency-direction rules.
3. Reusable integration with the existing ApiClient.
4. Compatibility with Clerk session management.
5. Compatibility with the existing go_router configuration.
6. Feature-local ownership of data, state, and presentation.
7. Testable module composition.
8. Documentation for implementing future Group Q features.
9. No premature customer feature implementation.

**Do not advance to Phase 16.8 or Group Q.**

---

## 2. Mandatory repository inspection

Read and inspect:

- Root `AGENTS.md`.
- Flutter-specific `AGENTS.md`.
- Group P Phase 16.7 requirements.
- Completed Phase 16.1–16.6 records.
- Flutter README.
- `docs/decisions.md`.
- Existing `lib/` structure.
- `lib/theme/`.
- Phase 16.3 configuration implementation.
- Phase 16.4 networking implementation.
- Phase 16.5 Clerk authentication implementation.
- Phase 16.6 router and route registry.
- Frozen API contracts.
- Approved request-only commerce decision.
- Group Q feature sequence.

Identify the actual code structure before proposing changes.

Do not reorganize completed foundations merely to match a suggested directory tree.

Report any architectural conflicts before implementing a competing pattern.

---

## 3. Architectural approach

Use **feature-first organization with lightweight internal layering**.

Avoid a global arrangement such as:

```text
lib/
  models/
  repositories/
  controllers/
  screens/
  services/
```

This organization makes ownership unclear as the application grows.

Prefer:

```text
lib/
  features/
    catalog/
    categories/
    product_detail/
    search/
    furniture_requests/
    enquiries/
    account/
```

Each feature owns its own presentation, data access, and feature-specific models when those elements are actually implemented.

### Layering

Use three conceptual layers where justified:

- **Presentation:** Flutter widgets and feature-specific state.
- **Data:** Repositories, API DTOs, and mappings.
- **Domain:** Business-facing models and transformations when a distinct domain representation is genuinely necessary.

Do not mandate all three directories for every small feature.

Do not create empty repositories, use cases, or interfaces simply to fill a template.

---

## 4. Preserve existing shared foundations

Retain the existing implementation locations for:

- Design system and theme.
- Environment configuration.
- Networking.
- Authentication.
- Routing.

Do not migrate `lib/theme/` into another directory.

Do not relocate the completed ApiClient, Clerk adapter, or router unless there is a demonstrated correctness problem.

Shared foundations must remain independently usable by future features.

### Ownership rule

Features consume shared foundations.

Shared foundations must not import feature-specific implementation code.

Avoid circular dependencies.

---

## 5. Recommended module layout

The following is an illustrative target architecture.

```text
lib/
  app.dart
  main.dart

  theme/
    ...

  config/
    ...

  core/
    network/
      ...
    auth/
      ...

  navigation/
    ...

  features/
    catalog/
      catalog_feature.dart

    categories/
      categories_feature.dart

    product_detail/
      product_detail_feature.dart

    search/
      search_feature.dart

    furniture_requests/
      furniture_requests_feature.dart

    enquiries/
      enquiries_feature.dart

    account/
      account_feature.dart
```

Do not create all illustrated files automatically.

The final structure must reflect the repository's existing conventions and actual module responsibilities.

### Future feature structure

When a feature is implemented in Group Q, it may expand into:

```text
features/
  catalog/
    data/
      catalog_repository.dart
      catalog_dto.dart
    domain/
      catalog_item.dart
    presentation/
      catalog_screen.dart
      catalog_controller.dart
```

This is an example of eventual organization, not an instruction to implement those files in Phase 16.7.

---

## 6. Feature module contract

Define a lightweight convention for feature ownership.

Every feature should have a clearly identifiable entry point or composition location.

That entry point may coordinate:

- Feature dependencies.
- Feature route builders.
- Feature-local repositories.
- Feature-specific state.
- Feature screen construction.

Do not require every feature to implement a common interface if that interface provides no actual benefit.

Do not introduce a plugin registry, reflection-based discovery, or runtime module loading.

The architecture should remain statically typed and straightforward.

### Feature dependencies

A future catalog module should be able to receive the existing ApiClient without constructing another HTTP client.

A future account module should be able to consume the existing Clerk authentication abstraction without importing Clerk SDK internals.

A future furniture request module should use the existing API contract without introducing its own HTTP transport.

---

## 7. Dependency direction

Enforce this direction:

```text
Feature presentation
        |
        v
Feature state/controller
        |
        v
Feature repository
        |
        v
Shared ApiClient
        |
        v
Laravel API
```

Use only the layers that the feature actually needs.

### Rules

- Presentation must not construct raw HTTP requests.
- Presentation must not parse Laravel error envelopes.
- Feature repositories must not create their own networking clients.
- Feature modules must not initialize Clerk.
- Features must not directly read `String.fromEnvironment`.
- Features must not access encrypted session storage.
- Feature code must not alter global design tokens.
- Backend authorization must not be duplicated in Flutter.
- Feature-specific data models must not be placed in unrelated shared modules.

Cross-feature imports should be minimized.

If two features genuinely share a model, place it in a narrowly scoped shared location with documented ownership.

Do not move every model into `core/`.

---

## 8. Module composition

Establish a lightweight mechanism for supplying shared dependencies to future features.

Preferred options include:

- Explicit constructor injection.
- A small composition root.
- Existing dependency injection conventions already present in the application.

Do not introduce Riverpod, Bloc, GetIt, Provider, or another dependency merely to satisfy this phase.

If a state-management choice is needed later, defer it until a concrete feature demonstrates the requirement.

### Required shared dependencies

The architecture must support access to:

- Existing ApiClient.
- Existing AuthRepository.
- Existing AppConfig where legitimately needed.
- Existing navigation interfaces.
- Existing theme through Flutter's Theme context.

Do not create multiple ApiClient instances for individual features without a justified lifecycle requirement.

### Ownership and disposal

Document who creates and disposes:

- HTTP transport.
- ApiClient.
- Clerk adapter.
- Authentication listeners.
- Feature repositories.
- Feature controllers.

Avoid double disposal and hidden global ownership.

---

## 9. Router integration

Preserve the Phase 16.6 `go_router` implementation.

Feature modules should eventually provide screen builders or equivalent integration points.

The central router must remain authoritative for:

- Route names.
- Route paths.
- Public/protected access.
- Deep-link parsing.
- Authentication redirects.
- Unknown-route behavior.

### Requirements

- No second router.
- No feature-local `MaterialApp`.
- No duplicate route registry.
- No bypass of Clerk route guards.
- No direct modification of protected-route policy by feature widgets.
- No feature-specific navigation logic inside shared networking code.

The existing approved route registry must remain intact.

### Placeholders

Retain Phase 16.6 placeholders where needed.

Do not replace them with fabricated product cards, fake account information, or unfinished commerce screens.

If route-builder extraction improves modularity, perform the smallest refactor and preserve all navigation behavior.

---

## 10. Feature scope and activation

Separate feature architecture from feature availability.

### Initial active feature families

Prepare for:

- Home/catalog.
- Categories.
- Product detail.
- Search/filtering.
- Furniture requests.
- Enquiries.
- Account/profile.

These are future implementation targets, not features to build now.

### Deferred commerce features

The frozen Group Q plan includes:

- Cart.
- Checkout.
- Payments.
- Orders.
- Order tracking.

These remain deferred under the current REQUEST ONLY production mode.

Do not register new routes for them.

Do not create empty production screens for them.

Do not enable them through a hidden debug switch.

Do not introduce speculative feature flags.

When the business authorizes purchase functionality later, those modules can be introduced through their owning phases.

### Favorites

Do not introduce a favorites module unless the approved mobile feature plan explicitly requires it.

Phase 16.6 intentionally omitted the favorites route.

Preserve that decision.

---

## 11. Data model ownership

Define where future DTOs and models will live.

### API DTOs

Feature-local data classes should represent Laravel's actual response structure.

Do not invent product or request fields.

Use the frozen OpenAPI schema when implementing future DTOs.

### Domain models

Create domain models only when they add value beyond raw API transport representations.

Do not duplicate the entire API schema into two identical class hierarchies.

### Shared models

A model belongs in shared code only when genuinely required by multiple features and has stable ownership.

Do not create a large generic `models/` directory.

### Money and identity

Preserve backend conventions:

- Money uses integer minor units and currency.
- IDs remain opaque.
- No client-generated Laravel identity.
- No client-controlled roles.
- No client-side replacement for backend authorization.

Do not implement these models in Phase 16.7 unless required for a minimal structural test.

---

## 12. State-management convention

Document a default approach for future feature state.

For small, localized state, prefer Flutter's built-in mechanisms.

For more complex asynchronous feature state, evaluate the existing project's conventions before selecting an additional package.

### Requirements

- State belongs to its owning feature.
- Avoid global mutable feature state.
- Avoid direct networking in widgets.
- Dispose controllers and subscriptions.
- Keep loading and error presentation consistent with the upcoming Phase 16.8.
- Do not implement a complete state-management framework now.

Do not create generic `BaseController`, `BaseRepository`, or `BaseViewModel` classes without a concrete use case.

---

## 13. Testing strategy

Define a test structure that mirrors feature ownership.

Suggested future layout:

```text
test/
  features/
    catalog/
    categories/
    product_detail/
    search/
    furniture_requests/
    enquiries/
    account/
```

### Required structural tests

Add focused tests where they verify real architecture behavior:

1. Existing app composition remains valid.
2. Feature entry points can be constructed using test dependencies where implemented.
3. Router integration remains functional.
4. Shared ApiClient is not recreated unexpectedly.
5. No feature requires real Clerk initialization for a pure unit test.
6. Existing public routes remain public.
7. Existing protected account route remains protected.
8. No deferred commerce routes are registered.
9. No second MaterialApp or router is introduced.
10. Existing theme integration remains intact.
11. Existing 116 tests continue to pass.

Do not create tests that assert only that a directory exists.

Do not write large tests for hypothetical future features.

### Architecture checks

If practical, add a small maintainable source-level dependency check enforcing the most important boundaries.

For example:

- `core/network` must not import `features`.
- `theme` must not import `features`.
- Feature presentation must not import the raw HTTP package directly.
- Features must not import Clerk SDK internals.

Avoid a fragile regex-based linter that falsely rejects valid Dart code.

Prefer analyzer-supported mechanisms or simple explicit tests with documented limitations.

---

## 14. Documentation and developer conventions

Update the Flutter README with a concise feature-module development guide.

Explain:

- Where to place a new feature.
- When to create `data/`, `domain/`, and `presentation/`.
- How to inject ApiClient.
- How to use AuthRepository.
- How to integrate routes.
- How to add feature-specific tests.
- How to handle feature-specific models.
- How to avoid cross-feature coupling.
- Which features remain deferred.
- Which responsibilities belong to Phase 16.8 and Phase 16.9.

### Example workflow

Document how Phase 17.1 will eventually implement catalog:

1. Create catalog feature-local models based on the frozen API.
2. Create a catalog repository using ApiClient.
3. Add feature-specific state handling.
4. Implement catalog presentation using approved Material 3 tokens.
5. Connect the screen to the existing router.
6. Add repository and widget tests.

This workflow is documentation only.

Do not implement it now.

---

## 15. Preserve earlier phases

The implementation must not regress:

**Phase 16.2**

- Generated design tokens.
- Young Serif display typography.
- Material 3 component themes.
- Token synchronization.

**Phase 16.3**

- Typed environment configuration.
- Android LOCAL/STAGING/PRODUCTION validation.

**Phase 16.4**

- Centralized ApiClient.
- Typed Laravel errors.
- Cancellation and timeouts.
- Request IDs and Retry-After.
- Redirect protection.

**Phase 16.5**

- Clerk SDK adapter.
- Encrypted session persistence.
- AuthTokenProvider.
- Authentication state.
- Sign-out and renewal.

**Phase 16.6**

- One go_router.
- Public routes.
- Protected `/account`.
- Safe intended-destination restoration.
- Deep-link validation.
- Android back behavior.

Do not refactor these systems merely for stylistic consistency.

---

## 16. Explicit exclusions

Do not implement:

- Real product catalog API consumption.
- Product/category DTOs without an immediate structural need.
- Catalog screens.
- Search behavior.
- Furniture request submission.
- Enquiry submission.
- Profile data loading.
- Clerk sign-in UI.
- Checkout/payment/order features.
- Favorites.
- New Laravel endpoints.
- New API error conventions.
- Shared loading/error UI — Phase 16.8.
- Logging infrastructure — Phase 16.9.
- Global state-management migration.
- A new design system.
- New platform targets.

Do not introduce backend or API contract changes.

---

## 17. Verification

After all edits, run:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug
git diff --check
```

Use the actual repository paths.

### Additional verification

- Existing 116 tests remain passing.
- Router tests remain passing.
- Authentication tests remain passing.
- Networking tests remain passing.
- No unused architectural dependency was added.
- No new commerce route exists.
- No duplicate app root exists.
- No theme-token drift exists.
- No frozen contract changed.
- No sensitive configuration entered source control.

### Existing staged whitespace issue

Phase 16.6 reported two pre-existing trailing Markdown spaces in the staged phase specification.

Inspect them, but do not silently modify unrelated staged content.

Report `git diff --check` and `git diff --cached --check` separately.

Do not claim the staged check passes if those pre-existing issues remain.

---

## 18. Physical Android verification

Because this phase is primarily architectural, do not invent UI just to demonstrate module structure.

Where available, verify:

- App launches.
- Public root route still renders.
- Material 3 theme remains intact.
- No startup crash.
- Existing router initialization works.
- No unexpected Clerk or Laravel calls are introduced.

Physical testing of new customer screens is not required because this phase must not implement them.

Do not claim device navigation coverage beyond what is actually tested.

---

## 19. Completion report

Provide:

1. Repository inspection findings.
2. Final feature/module directory structure.
3. Files added, changed, or moved.
4. Shared versus feature ownership rules.
5. Dependency injection/composition decisions.
6. Router integration decisions.
7. State-management conventions.
8. Future data-model ownership rules.
9. Active and deferred feature boundaries.
10. Automated tests and regression results.
11. Android verification results.
12. Documentation changes.
13. Existing staged-diff status.
14. Risks and deferred work.
15. Final Phase 16.7 status.

## 20. Definition of Done

Phase 16.7 is complete when:

- A documented feature-first architecture exists.
- Existing shared foundations remain intact.
- Feature dependency direction is explicit.
- Future feature composition is testable.
- The router remains centralized.
- No second networking or authentication system exists.
- No speculative domain implementations are introduced.
- Deferred commerce features remain absent.
- Existing 116 tests continue passing.
- Flutter analysis and debug build pass.
- Token synchronization passes.
- Documentation explains how Group Q features should be added.
- No later phase is implemented prematurely.

**Final instruction:** Inspect the repository first, introduce the smallest useful feature-module architecture, preserve completed foundations, document clear ownership boundaries, run every required verification check, and stop at Phase 16.7.

---

## Completion record — 2026-10-08

- Confirmed the existing structure: theme, config, networking, authentication,
  and routing remain shared foundations; no feature modules existed before this
  phase. Phase 16.5 provides `ClerkAuthAdapter`, not a separate
  `AuthRepository`, so the feature boundary uses a new narrow `AuthSession`
  interface rather than exposing Clerk SDK types.
- Added `FeatureDependencies` for explicit constructor injection of the shared
  `ApiClient` and optional `AuthSession`. It is a reference holder only; the
  application composition root retains lifecycle ownership.
- Added `lib/features/README.md` documenting capability-first directories,
  optional lightweight internal layers, dependency direction, model ownership,
  router integration, state conventions, and the future Group Q workflow.
- Added architecture tests proving injected service identity, public feature
  composition without auth, centralized router usage, and absent deferred
  commerce routes.
- No customer screens, repositories, DTOs, state framework, API clients,
  Clerk UI, backend changes, or deferred commerce routes were introduced.
- Physical verification remains limited to the Phase 16.6 root-route launch
  check because this phase introduces no customer-facing UI. Live Clerk sign-in
  remains blocked by the development sign-in workflow.
