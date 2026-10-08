# Phase 16.8 — Flutter Error & Loading States

**Project:** SL Furnitures — Android Customer Application  
**Group:** P — Flutter Application Foundation  
**Prerequisites:** Phases 16.1–16.7 complete  
**Status:** COMPLETED — 2026-10-08

## 1. Objective

Implement a small, reusable, accessible presentation foundation for asynchronous loading, empty results, expected failures, and safe recovery actions.

This phase must provide the components and state conventions that future Group Q feature screens can use without inventing their own inconsistent loading/error UI.

**Primary architectural decision:** Keep transport errors in the existing networking layer, feature-specific failures in their owning features, and reusable visual states in a narrow shared presentation layer.

Do not build a second networking/error framework.

Do not implement catalog, request, enquiry, or account business functionality.

---

## 2. Mandatory repository inspection

Before coding, read:

1. Root `AGENTS.md` and `frontend/AGENTS.md`.
2. Group P Phase 16.8 specification and earlier phase completion records.
3. `docs/decisions.md`, especially:
   - ADR/DESIGN-001 through ADR/DESIGN-006.
   - ADR/API-ERR-001 through ADR/API-ERR-007.
   - ADR/WEB-003 for conceptual alignment, not framework copying.
   - Flutter architecture decisions from Phases 16.2–16.7.
4. `frontend/app/README.md`.
5. `lib/core/network/` and existing error classes.
6. `lib/core/auth/` including `AuthSession`.
7. `lib/core/feature_dependencies.dart`.
8. `lib/navigation/` and the router's existing error/status pages.
9. `lib/theme/` including generated tokens, component themes and motion.
10. `lib/features/README.md`.
11. Existing tests and app composition.

Record the exact current names of networking exception types, constructors, and authentication states.

Do not guess APIs from these instructions.

Reuse existing mechanisms before adding abstractions.

---

## 3. Required state vocabulary

Use the following conceptual states:

| State | Meaning | Owner |
|---|---|---|
| Initial | No operation started | Feature |
| Loading | First load is in progress | Feature/shared presentation |
| Content | Valid data available | Feature |
| Empty | Successful operation returned no results | Feature/shared presentation |
| Expected failure | Recoverable API/domain/transport failure | Feature/shared presentation |
| Unexpected failure | Unanticipated defect or invalid assumption | Existing error handling + safe fallback |
| Refreshing | Existing content is being updated | Feature |
| Submitting | User-triggered mutation is in progress | Feature |

Not every future feature must implement all eight states.

Do not introduce a universal state machine with transitions that every screen must satisfy.

### Critical distinction

A successful API response containing `data: []` is not a transport error.

An API error is not an empty result.

A loading state is not an authentication state.

An unexpected programming exception is not automatically a retryable network failure.

Cancellation is not a user-facing failure by default.

---

## 4. Shared presentation location

Prefer a narrowly scoped structure such as:

```text
lib/
  core/
    presentation/
      async_view_state.dart
      app_loading_view.dart
      app_empty_view.dart
      app_error_view.dart
      app_inline_error.dart
      app_retry_action.dart
      error_presentation_mapper.dart
```

These names are illustrative.

Inspect existing conventions and create only files that have clear responsibilities.

Do not create duplicate classes or folders if equivalent shared components already exist.

Do not create a general-purpose widget library.

Keep reusable state components independent of feature DTOs and business models.

---

## 5. Async state representation

Evaluate whether a lightweight sealed state type materially simplifies future feature controllers.

If adopted, use Dart 3 sealed classes or another SDK-native type-safe approach.

Suggested conceptual shape:

```text
AsyncState<T>
  Initial<T>
  Loading<T>
  Data<T>
  Empty<T>
  Failure<T>
```

This is not a required literal API.

### Requirements

- Preserve type safety.
- Avoid `dynamic` payloads.
- Avoid contradictory flags such as `isLoading=true` and `hasError=true` without defined semantics.
- Support existing-content refresh without destroying visible content.
- Do not require a new state-management package.
- Do not introduce a global async-state singleton.
- Do not add speculative pagination or mutation frameworks.

If a shared state type adds more complexity than value, document the simpler approved convention and implement reusable presentation widgets instead.

### Refreshing behavior

Future screens should be able to keep existing data visible while a refresh occurs.

A refresh failure should not automatically erase previously valid content.

Do not implement catalog refresh or pagination now.

---

## 6. Loading presentation

Implement a reusable loading view using existing Material 3 primitives.

Support at least:

- Full-content loading.
- Inline loading.
- Accessible progress indication.

Prefer `CircularProgressIndicator` or other native Material widgets.

### Requirements

- Use the existing theme.
- Avoid hard-coded colors.
- Avoid arbitrary animation timing.
- Provide meaningful semantics.
- Do not animate purely for decoration.
- Respect reduced-motion settings where relevant.
- Do not trap focus.
- Do not use blocking dialogs for ordinary background loading.
- Avoid layout jumps where a stable content region can be maintained.

### Skeletons

Do not build a generic shimmer/skeleton framework.

Catalog and product-specific skeletons belong to their later feature phases.

A generic loading view is sufficient for Phase 16.8.

---

## 7. Empty-state presentation

Implement a reusable empty-state view.

Support:

- Required title.
- Optional description.
- Optional appropriate icon.
- Optional action supplied by the caller.

The component must not decide business meaning.

Examples for future use:

- No products match the current filters.
- No categories are available.
- No search results.
- No request history.

These are illustrative scenarios only.

Do not create actual catalog or history screens.

### Requirements

- Distinguish empty data from errors.
- Do not imply that a user must sign in when the feature is public.
- Do not fabricate product recommendations.
- Do not display fake inventory or promotional content.
- Do not show a retry action by default for a successful empty result.

---

## 8. Error presentation architecture

Separate error classification from error rendering.

Use a small pure mapper that converts existing error information into a safe presentation description.

For example, the presentation result may contain:

```text
ErrorPresentation
  title
  message
  recoveryAction
  requestId
  fieldErrors
```

The exact implementation is left to repository inspection.

### Do not duplicate the API contract

The existing ApiClient already parses Laravel's canonical error envelope.

The presentation layer must consume that parsed representation.

It must not:

- Decode raw HTTP response bodies.
- Parse JSON error envelopes again.
- Parse human-readable messages to identify error types.
- Define a new API error-code registry.
- Replace HTTP status semantics.
- Modify the frozen Laravel contract.

### Safe fallback

Unknown failures must display a generic, non-sensitive message.

Never show:

- Stack traces.
- Raw exception objects.
- Authorization headers.
- Clerk tokens.
- API origins.
- Internal filesystem paths.
- Database details.
- Provider response bodies.

---

## 9. API error classification

Preserve the existing distinction between HTTP status and machine-readable error code.

Implement presentation mapping for relevant categories.

| Failure | Expected UI behavior |
|---|---|
| No connection | Explain connection problem; allow safe retry |
| Timeout | Explain request did not complete; offer appropriate recovery |
| Cancelled request | Usually render nothing |
| HTTP 401 | Authentication required or session action; defer to existing auth boundary |
| HTTP 403 | Access denied; do not force sign-out |
| HTTP 404 | Resource unavailable where applicable |
| HTTP 409 | Conflict; require state refresh or reconciliation where applicable |
| HTTP 422 | Present validation/business errors, including field errors |
| HTTP 429 | Rate limited; respect existing Retry-After metadata |
| HTTP 5xx | Temporary service failure or safe generic error |
| Malformed/invalid envelope | Safe unexpected-response feedback |
| Unsupported content type | Safe unexpected-response feedback |

Use the actual existing exception types and API codes.

### HTTP 401

Do not independently refresh Clerk tokens or redirect from a generic error widget.

Authentication actions belong to the existing AuthSession and navigation architecture.

### HTTP 403

Do not treat forbidden access as an expired Clerk session.

### HTTP 404

Do not automatically turn every API 404 into a go_router unknown-route page.

Route matching and missing API resources are separate concerns.

### HTTP 422

Preserve all available field-level errors.

Use canonical dot paths supplied by the API.

Do not collapse multiple field errors into one opaque string.

### HTTP 429

Preserve the existing numeric Retry-After value.

Do not implement a countdown timer or automatic retry loop in this phase.

The UI may explain that the user should wait before retrying.

Do not fabricate an exact retry time when none was provided.

---

## 10. Retry and recovery behavior

A reusable error view may expose an optional callback supplied by its owning feature.

The shared widget must not decide whether an operation is safe to replay.

### Safe retry principles

- Public GET reads may normally offer manual retry.
- Cancelled requests should not be retried automatically.
- Mutations must not be blindly replayed.
- Ambiguous timeouts after submission may require reconciliation.
- HTTP 409 may require refreshing server state rather than repeating the same mutation.
- HTTP 429 must respect the existing server retry guidance.
- Authentication errors require the existing auth workflow.

### Button behavior

Do not show an enabled Retry button unless a real callback exists.

Do not place a fake retry button on placeholder routes.

Do not create automatic retries inside error widgets.

Do not introduce a second retry policy that conflicts with Phase 16.4.

---

## 11. Form and field errors

Provide a small presentation convention for future forms.

Support:

- General form-level error.
- Multiple field-level errors.
- Canonical API field paths.
- Accessible error descriptions.
- Clear association between fields and errors.
- Removal or update of stale errors after correction, controlled by the owning form.

### Scope

Do not implement actual furniture request or enquiry forms.

Do not build a generic dynamic form generator.

Do not create field-specific validation rules not present in the frozen API contract.

Laravel remains authoritative.

Client validation remains advisory.

---

## 12. Authentication and navigation states

Reuse existing Phase 16.5 and 16.6 behavior.

The router already distinguishes:

- Restoring session.
- Signed out.
- Authentication action required.
- Temporarily unavailable authentication.
- Authenticated.

Do not replace these states with a generic loading/error abstraction.

### Integration

Where beneficial, reuse shared visual primitives within existing neutral status screens.

Do not change route access policy.

Do not change the allow-listed `/account` return destination.

Do not introduce sign-in UI.

Do not create new Clerk session persistence.

Do not trigger API calls during router initialization.

---

## 13. Material 3 and design tokens

Use the existing Phase 16.2 theme.

Canonical authority remains:

`frontend/design-system/tokens.css`

Flutter consumes generated token mappings.

### Visual rules

- Warm canvas and white paper surfaces retain their existing semantics.
- Primary actions use the approved charcoal treatment.
- Error styling uses the approved semantic danger mapping.
- Status meaning must not depend on color alone.
- Use platform sans for interface messages.
- Reserve Young Serif for approved editorial/display roles.
- Use existing spacing and shape adapters.
- Use Material icons where appropriate.
- Avoid decorative shadows and excessive elevation.
- Do not introduce new colors, radii, typography scales, or motion durations.

Do not modify the generated token file manually.

If a required token is missing, document the gap rather than inventing a new brand value.

---

## 14. Accessibility requirements

Test all shared presentation states for:

1. Readable semantic labels.
2. Proper heading and message hierarchy.
3. Sufficient text contrast.
4. Visible keyboard focus.
5. Accessible action labels.
6. Appropriate minimum touch targets.
7. Large text scaling, including 2×.
8. Small-screen overflow handling.
9. Reduced-motion behavior.
10. Screen-reader-friendly progress indication.
11. Field-error discoverability.
12. Non-color-only error communication.

### Loading semantics

Avoid announcing every progress-frame update.

Use meaningful status text where necessary.

### Errors

Error actions must describe the recovery operation.

Do not use vague action labels when a more precise one is available.

### Empty states

Do not announce an empty state as an application failure.

---

## 15. Preview and verification harness

Create a development-only state preview if needed to validate the new components on Android.

It may demonstrate:

- Full-page loading.
- Inline loading.
- Empty state.
- Recoverable network error.
- Validation error.
- Rate-limited feedback.
- Unexpected-response fallback.

### Restrictions

- Keep the production root route unchanged.
- Do not register public production preview routes.
- Do not add a new navigation shell.
- Do not create fake commerce workflows.
- Do not perform real network requests.
- Do not ship debug controls in release builds.

Prefer widget tests or the existing development preview conventions when they provide sufficient coverage.

---

## 16. Tests

Add focused unit and widget tests.

### A. State behavior

1. Initial state is distinguishable from loading.
2. Loading state renders correctly.
3. Content state does not display an error.
4. Empty state is not treated as failure.
5. Refresh preserves existing content where supported.
6. Cancellation is handled without unwanted error UI.

### B. Error mapping

7. Connection failure.
8. Timeout.
9. Cancellation.
10. API 401.
11. API 403.
12. API 404.
13. API 409.
14. API 422.
15. API 429 with Retry-After.
16. API 429 without Retry-After.
17. API 500/503.
18. Invalid response envelope.
19. Unknown exception fallback.
20. Multiple field errors.
21. Request ID propagation.

### C. Recovery

22. Retry callback is invoked exactly once per activation.
23. No retry callback means no actionable retry control.
24. Mutation errors are not automatically replayed.
25. Rate-limit presentation does not invent a deadline.
26. Authentication error does not invoke a second auth flow.

### D. Widget accessibility

27. Loading progress has meaningful semantics.
28. Error title and message are readable.
29. Empty state supports optional actions.
30. Large text does not overflow.
31. Focus behavior is correct.
32. Touch targets are usable.
33. Reduced-motion behavior is respected.

### E. Regression

34. Existing router behavior remains intact.
35. Existing Clerk tests pass.
36. Existing ApiClient tests pass.
37. Existing theme tests pass.
38. Existing feature architecture tests pass.
39. No deferred commerce routes appear.
40. No new startup API request is introduced.

This is a coverage matrix, not a requirement to create exactly 40 test methods.

Use deterministic fakes.

No real Laravel or Clerk services should be required for automated tests.

---

## 17. Documentation

Update:

- Flutter README.
- Applicable Group P phase completion record.
- `docs/decisions.md` if a durable architectural decision is made.
- `lib/features/README.md` with guidance on consuming shared error/loading presentation.

Document:

1. State vocabulary.
2. Shared widget ownership.
3. Error mapping policy.
4. API machine-code handling.
5. Request ID presentation.
6. Field validation conventions.
7. Retry safety rules.
8. Authentication-state separation.
9. Accessibility behavior.
10. Examples for future Group Q integration.
11. Deferred feature-specific skeletons.
12. Known limitations.

Do not rewrite unrelated architecture decisions.

---

## 18. Verification commands

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

### Requirements

- Existing 119 tests remain passing.
- New error/loading tests pass.
- No new analyzer warnings.
- No generated token drift.
- Debug APK builds.
- No unapproved dependencies.
- No API contract changes.
- No backend modifications.
- No deferred commerce features.
- No new startup network calls.

### Whitespace checks

Phase 16.7 reported two intentional Markdown hard-break spaces in `phases/group-P-phases.md`.

Report staged and unstaged whitespace results separately.

If `git diff --check` flags those intentional spaces, explain the precise file/lines.

Do not silently rewrite unrelated documentation or falsely report a passing check.

---

## 19. Physical Android verification

Where a physical Android device and Marionette are available, verify the reusable states through an approved development-only preview or test harness.

Inspect:

- Loading indicator visibility.
- Error message readability.
- Empty-state layout.
- Retry control interaction when provided.
- Safe-area behavior.
- Text scaling where testable.
- No render overflow.
- No Flutter exceptions.
- Theme consistency.

Do not add production navigation controls merely to make a test harness accessible.

If a particular interaction cannot be driven through Marionette, record the limitation and rely on widget tests for that behavior.

Do not claim device accessibility settings were tested if the required device-config integration is unavailable.

---

## 20. Explicit scope exclusions

Do not implement:

- Product catalog fetching.
- Product detail API integration.
- Search.
- Furniture request forms.
- Enquiry forms.
- Account profile UI.
- Clerk sign-in UI.
- Cart.
- Checkout.
- Payments.
- Orders.
- Tracking.
- Favorites.
- A generic form-generation framework.
- A new networking package.
- A second API client.
- Automatic retry middleware.
- Global state-management framework.
- Logging/diagnostics infrastructure — Phase 16.9.
- A new theme or token palette.
- Backend changes.

Do not implement Phase 16.9 prematurely.

---

## 21. Completion report

Provide:

1. Repository inspection findings.
2. State representation decision.
3. Files added and modified.
4. Shared loading components.
5. Shared empty-state components.
6. Error classification and presentation mapping.
7. Retry/recovery rules.
8. Field-error handling.
9. Router/auth integration.
10. Material 3 token usage.
11. Accessibility verification.
12. Automated test results.
13. Physical Android verification.
14. Documentation changes.
15. Staged and unstaged whitespace status.
16. Deferred work and remaining risks.
17. Final Phase 16.8 status.

## Definition of Done

Phase 16.8 passes when:

- Shared loading, empty, and error presentation exists.
- Existing ApiClient error distinctions are preserved.
- Safe error mapping is deterministic and testable.
- Laravel field errors and request IDs remain available.
- Retry actions are explicit and safe.
- No automatic mutation replay exists.
- Clerk and router state ownership remains unchanged.
- Components consume the approved Material 3 design system.
- Accessibility tests cover meaningful states.
- Existing 119 tests continue to pass.
- All required static checks and build checks pass.
- Android verification is completed or accurately documented as limited.
- No later-phase customer functionality is introduced.

**Final instruction:** Implement the smallest maintainable error/loading presentation foundation that Group Q can reuse. Preserve all existing architecture, verify every change, document the result, and stop at Phase 16.8.

---

## Completion record — 2026-10-08

- Added `AsyncViewState<T>` as a lightweight sealed Dart 3 vocabulary for
  initial, loading, content, empty, failure, refreshing, and submitting states.
  Refreshing and failure states can retain typed previous content without
  introducing a universal state machine or state-management dependency.
- Added token-driven `AppLoadingView`, `AppEmptyView`, `AppErrorView`, and
  `AppInlineError` widgets with accessible status semantics, optional caller
  actions, no automatic retries, and no fabricated feature data.
- Added `ErrorPresentationMapper` over the existing `ApiError` and
  `ApiTransportException` classes. It preserves status distinctions, request
  IDs, canonical field paths, and rate-limit guidance; cancellation produces no
  user-facing error by default; unknown exceptions use safe generic text.
- Added mapper, state, accessibility, action, large-text, and recovery tests.
  No catalog, request, enquiry, account, auth UI, API, or backend feature was
  implemented.
- Verification passed: `flutter analyze`, `flutter test --concurrency=1`,
  token synchronization, and the existing router/auth/network/theme regression
  suites. Physical verification remains limited to widget tests for the new
  states unless the development-only preview is explicitly enabled; no
  production preview route was added.
