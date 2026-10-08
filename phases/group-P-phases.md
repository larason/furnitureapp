# Phase 16.9 — Logging & Diagnostics

**Project:** SL Furnitures — Flutter Android Customer Application  
**Group:** P — Flutter Application Foundation  
**Prerequisites:** Phases 16.1–16.8 complete  
**Status:** COMPLETE
**Milestone:** Final Group P phase

## 1. Objective

Implement a small, structured, privacy-safe diagnostics foundation for the Flutter Android application.

The implementation must provide enough information to investigate:

- Application startup and configuration failures.
- Network connectivity and API failures.
- Authentication lifecycle failures.
- Navigation failures.
- Unexpected Flutter framework errors.
- Unhandled asynchronous exceptions.
- Future feature-specific failures.

Diagnostics must be useful during development without creating a security or privacy liability.

### Required outcomes

1. One centralized diagnostic interface.
2. A small, closed set of severity levels.
3. Safe structured diagnostic events.
4. Environment-aware output policy.
5. Strict sensitive-data protection.
6. Correlation with Laravel request IDs.
7. Integration with existing startup and failure boundaries.
8. Testable diagnostic sinks.
9. No duplicate reporting of expected failures.
10. Documentation and a final Group P verification report.

**Do not implement a remote observability platform.**

---

## 2. Mandatory repository inspection

Before implementation, read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- Group P Phase 16.9 specification.
- All Group P completion records.
- `frontend/app/README.md`.
- `docs/decisions.md`.
- Existing Flutter application bootstrap.
- `lib/config/`.
- `lib/core/network/`.
- `lib/core/auth/`.
- `lib/core/feature_dependencies.dart`.
- `lib/navigation/`.
- `lib/core/presentation/`.
- `lib/features/README.md`.
- Existing tests.
- Android manifest and build configuration.

Pay particular attention to:

- `ENABLE_DIAGNOSTICS`.
- `AppEnvironment`.
- Existing `ApiError` and `ApiTransportException`.
- Laravel `meta.request_id`.
- Clerk SDK error handling.
- Existing router failure handling.
- Existing `ErrorPresentationMapper`.
- Phase 16.8 cancellation semantics.

Determine whether a logger or error-capture mechanism already exists.

Do not add another if the current implementation can be safely extended.

Do not move completed infrastructure into a new architecture.

---

## 3. Architectural approach

Use a lightweight, SDK-independent diagnostic boundary.

Suggested structure:

```text
lib/
  core/
    diagnostics/
      app_diagnostics.dart
      diagnostic_event.dart
      diagnostic_level.dart
      diagnostic_sink.dart
      console_diagnostic_sink.dart
      diagnostic_policy.dart
      diagnostic_sanitizer.dart
```

The names and exact number of files are illustrative.

Prefer fewer files if responsibilities remain clear.

### AppDiagnostics

Expose a narrow interface for recording diagnostic events.

Example conceptual API:

```dart
abstract interface class AppDiagnostics {
  void record(DiagnosticEvent event);
}
```

The implementation may provide convenience methods if they improve clarity.

Do not create an elaborate logging DSL.

### DiagnosticSink

Separate event creation from event output.

The design should support:

- A no-op sink.
- A development console sink.
- An in-memory test sink.

Do not implement remote sinks.

Do not create a database for logs.

Do not add file-based persistent logging without an explicit requirement.

---

## 4. Severity levels

Define a small closed vocabulary.

Recommended levels:

| Level | Meaning |
|---|---|
| Debug | Development-only diagnostic detail |
| Info | Expected significant lifecycle event |
| Warning | Recoverable abnormal condition |
| Error | Operation failure requiring investigation |
| Critical | Unexpected application-level failure |

Do not add unnecessary levels.

### Important distinctions

A cancelled request is not automatically an error.

A successful empty catalog response is not a warning.

An expected 422 validation response is not a critical failure.

A 401 during an ordinary authentication transition is not necessarily a crash.

Do not classify every non-200 response as an application defect.

---

## 5. Structured diagnostic events

Use typed events with a small, stable structure.

Suggested conceptual fields:

```text
DiagnosticEvent
  level
  category
  code
  timestamp
  requestId?
  safeContext?
```

The implementation must use safe, explicitly defined metadata.

### Categories

Suggested categories:

- startup
- configuration
- network
- authentication
- navigation
- presentation
- unexpected

Use a closed enum or equivalent where useful.

Do not build a dynamic event taxonomy that grows uncontrollably.

### Event codes

Prefer stable event identifiers such as:

```text
APP_STARTUP_FAILED
API_REQUEST_FAILED
API_REQUEST_TIMEOUT
AUTH_SESSION_RESTORE_FAILED
AUTH_SESSION_SIGN_OUT_FAILED
NAVIGATION_ROUTE_UNAVAILABLE
UNEXPECTED_FLUTTER_ERROR
UNHANDLED_ASYNC_ERROR
```

These are illustrative application diagnostic codes.

They must not be confused with or replace Laravel's frozen API error codes.

---

## 6. Sensitive-data protection

**Privacy and credential protection are mandatory.**

Never log:

- Clerk session tokens.
- Authorization headers.
- Cookies.
- Passwords.
- Refresh tokens.
- Clerk secret keys.
- Secure-storage contents.
- Raw API request bodies.
- Raw API response bodies.
- Customer names.
- Email addresses.
- Phone numbers.
- Delivery addresses.
- Furniture request descriptions.
- Enquiry messages.
- User-generated text.
- Complete URLs containing query parameters.
- Raw exception messages from untrusted SDKs or HTTP libraries.
- Full stack traces in production diagnostics.

### Allow-listed diagnostic metadata

Prefer fields such as:

- Diagnostic event code.
- Severity.
- Environment identifier.
- Safe HTTP method.
- HTTP status.
- Stable Laravel API error code.
- Laravel request ID, subject to safe-format validation.
- Transport failure category.
- Safe operation identifier.
- Route name from an approved registry.
- Whether an operation was cancelled.

Do not log raw URL paths if they can contain customer or resource identifiers.

Do not log arbitrary query parameters.

Do not log user identifiers, even if they appear pseudonymous.

### Design requirement

Use an allow-list rather than attempting to redact arbitrary maps.

A generic recursive sanitizer must not be the sole security boundary.

Prefer typed, explicitly approved metadata fields that cannot accidentally contain credentials or personal information.

Where text-based sanitization is still needed, treat it as defense in depth.

---

## 7. Diagnostic output policy

Reuse Phase 16.3 configuration.

Do not introduce another environment configuration system.

### Local development

When diagnostics are enabled:

- Emit structured, readable events.
- Use a single consistent output format.
- Keep metadata minimal.
- Do not print raw exceptions.
- Do not print credentials.
- Avoid excessive per-frame or per-widget logs.

### Staging

Use only the explicitly approved diagnostic behavior.

Do not assume staging is safe for sensitive data.

### Production

Production must not emit verbose diagnostic information merely because a developer previously enabled debug output.

Default to a no-op sink or a strictly minimal approved sink.

Do not create a production telemetry pipeline without a separate decision.

### Important

`ENABLE_DIAGNOSTICS=false` must be meaningful.

Disabled diagnostics should not produce ordinary application diagnostic events.

Critical error handlers must still preserve the application's safe error behavior even when diagnostic output is disabled.

Do not confuse disabling logging with swallowing errors.

---

## 8. Flutter framework error capture

Inspect existing startup error handling.

Integrate safely with supported Flutter/Dart error boundaries, where appropriate:

- `FlutterError.onError`.
- `PlatformDispatcher.instance.onError`.
- `runZonedGuarded`, only if justified.

### Requirements

- Do not blindly install overlapping handlers.
- Preserve existing framework behavior where necessary.
- Avoid reporting the same exception multiple times.
- Do not hide unexpected errors.
- Do not turn framework exceptions into success states.
- Do not crash the app while attempting to record diagnostics.
- Avoid recursively logging logger failures.

### Error ownership

Determine which boundary owns each error.

For example:

- ApiClient owns typed transport classification.
- Feature controller owns expected operation failure handling.
- Flutter framework boundary owns unexpected framework exceptions.
- Application bootstrap owns configuration failure handling.

Do not make every layer log the same failure.

### Stack traces

Keep raw stack traces out of production diagnostic output.

For development, any stack trace emission must be explicitly approved and checked for sensitive content.

Do not serialize arbitrary exception objects.

---

## 9. Network diagnostics integration

Preserve Phase 16.4 ApiClient behavior.

Do not introduce:

- Another HTTP client.
- An interceptor framework.
- Automatic retries.
- Request-body logging.
- Response-body logging.
- Authorization-header logging.

### Safe events

Examples:

- Request timeout.
- Connection failure.
- Invalid API envelope.
- Unsupported response type.
- Unexpected HTTP 5xx.
- Rate limiting.
- Request cancellation, only where diagnostically useful.

### Request correlation

Use the existing request ID extraction behavior.

The existing API client treats:

1. `meta.request_id` as authoritative.
2. `X-Request-Id` as fallback.

Do not change that precedence.

If a request ID is present, retain it as a safe correlation value after validating its expected format and size.

Do not invent request IDs that appear to have originated from Laravel.

Do not log full URLs.

### HTTP semantics

Preserve:

- HTTP status.
- Stable Laravel error codes.
- Retry-After numeric seconds.
- Cancellation.
- Transport error classification.

Do not turn structured Laravel errors into raw exception text.

---

## 10. Authentication diagnostics

Integrate only with the existing Clerk adapter and SDK-independent `AuthSession` boundary.

Do not modify the authentication protocol.

### Permitted event types

- Clerk initialization failed.
- Session restoration failed.
- Session renewal failed.
- Sign-out failed.
- Session state transitioned to unavailable.
- Secure persistence failed.

### Never log

- Session token.
- Clerk client object serialization.
- Stored session payload.
- User email.
- User ID.
- Authentication credentials.
- Raw Clerk exception message.

### Session transitions

Avoid logging every repeated SDK state notification.

If recording a transition, record only approved state categories.

Do not log identity attributes.

### Authentication authority

Clerk continues to own authentication.

Laravel continues to own customer authorization.

Diagnostics must not introduce a second session store.

---

## 11. Navigation diagnostics

Reuse the Phase 16.6 router.

Potential diagnostic events:

- Unknown route.
- Invalid route parameter.
- Rejected external return destination.
- Authentication redirect failure.
- Unexpected navigation exception.

### Requirements

- Do not log complete deep-link URLs.
- Do not log query strings.
- Do not log raw product identifiers.
- Do not log intended return destinations containing parameters.
- Prefer approved route names and safe failure categories.
- Do not duplicate router error presentation.
- Do not change protected-route access rules.

No new navigation destinations should be registered.

---

## 12. Integration with Phase 16.8

Preserve the completed error/loading architecture.

The diagnostics layer must not own UI presentation.

### Ownership

**ErrorPresentationMapper**

Converts existing failures into safe user-facing presentation.

**AppDiagnostics**

Records safe machine-readable diagnostic events.

**Feature controller**

Decides whether to show an error, retry, reconcile, or ignore cancellation.

Do not combine these into one generic error service.

### Cancellation

Cancelled requests must remain silent to the user by default.

Do not record ordinary expected cancellation as an error.

### Mutation failures

Do not add automatic retry behavior.

A timeout during made-to-order request submission must not trigger automatic resubmission.

---

## 13. Dependency injection

Extend the Phase 16.7 composition approach narrowly.

Use the existing application composition root.

If useful, expose the diagnostics boundary through `FeatureDependencies`.

### Requirements

- No global mutable logging singleton unless the existing architecture genuinely requires it.
- No new dependency-injection package.
- No feature-specific console logger.
- No direct `print()` or `debugPrint()` scattered throughout future features.
- No circular imports.
- No duplicate diagnostic sink ownership.

The application root should construct the diagnostic service and own its lifecycle.

Feature modules should receive the interface, not the concrete console implementation.

---

## 14. Performance and reliability

Diagnostics must not materially affect app responsiveness.

### Requirements

- Logging must not block the UI thread.
- Avoid expensive serialization.
- Avoid deep object traversal.
- Avoid unbounded in-memory event accumulation.
- Avoid high-frequency widget rebuild logs.
- Avoid recursive diagnostic failures.
- Disabled diagnostics should have negligible overhead.
- No network request should be required to record an event.
- Diagnostic failures must not interrupt normal user operations.

A bounded in-memory sink may be used for tests.

Do not introduce background workers or persistent log queues.

---

## 15. Testing requirements

Use deterministic fake sinks.

No live Laravel, Clerk, or external telemetry service should be required.

### A. Diagnostic core

Test:

1. Debug event creation.
2. Info event creation.
3. Warning event creation.
4. Error event creation.
5. Critical event creation.
6. Stable event category and code.
7. Timestamp handling.
8. Optional request ID.
9. Disabled diagnostics.
10. Sink failure isolation.

### B. Privacy

Test:

11. Authorization header cannot enter diagnostic output.
12. Clerk token cannot enter diagnostic output.
13. Password cannot enter diagnostic output.
14. Customer email is excluded.
15. Customer phone is excluded.
16. Raw API body is excluded.
17. Raw response body is excluded.
18. Query parameters are excluded.
19. Arbitrary exception messages are excluded.
20. Unsafe metadata keys are rejected.
21. Oversized metadata values are rejected.
22. Raw stack traces are excluded from production output.

### C. Networking

Test:

23. Timeout classification.
24. Connection failure classification.
25. HTTP 401 handling.
26. HTTP 403 handling.
27. HTTP 422 handling.
28. HTTP 429 and Retry-After.
29. HTTP 500/503 handling.
30. Laravel request ID correlation.
31. Header request ID fallback.
32. Expected cancellation is not logged as an error.
33. No duplicate logging.
34. No request/response payload logging.

### D. Authentication

Test:

35. Session restoration failure.
36. Token renewal failure.
37. Sign-out failure.
38. Pending-action state is not misclassified.
39. Secure-storage failure.
40. No Clerk identity or token leakage.

### E. Framework and navigation

Test:

41. Unexpected Flutter error capture.
42. Unhandled asynchronous error capture.
43. Handler failure does not recurse.
44. Unknown-route diagnostic.
45. Unsafe deep-link rejection.
46. Route parameter values are not logged.
47. Existing router behavior remains intact.

### F. Regression

Test:

48. Existing 140 tests continue to pass.
49. No startup API calls are added.
50. No new authentication protocol.
51. No new commerce routes.
52. No change to error/loading presentation semantics.
53. Diagnostics disabled by default where required.
54. Production build configuration remains safe.

This is a coverage matrix, not a requirement to manufacture 54 separate test methods.

Prefer meaningful tests over test-count inflation.

---

## 16. Development diagnostics preview

A small development-only diagnostic demonstration may be useful.

It may trigger synthetic events such as:

- Network timeout classification.
- Safe HTTP 429 metadata.
- Unknown route category.
- Generic unexpected error.

### Restrictions

- No real credentials.
- No real customer data.
- No production debug screen.
- No new production route.
- No live API calls.
- No simulated success claims for Clerk authentication.
- No remote telemetry.

Prefer unit tests when they adequately demonstrate the behavior.

---

## 17. Physical Android verification

Where the physical Android device and Marionette are available, verify:

1. App launches with diagnostics disabled.
2. Public root route remains functional.
3. Material 3 theme remains intact.
4. No unexpected diagnostic noise.
5. Development diagnostics can be enabled through approved local configuration.
6. Only safe diagnostic metadata appears.
7. No Clerk tokens or user information appear.
8. No UI regressions.
9. No Flutter exceptions introduced by diagnostics.

If practical, inspect development logs using `adb logcat`.

### Important

Do not claim authenticated diagnostic verification without a real Clerk development sign-in flow.

Do not claim production telemetry verification because no remote telemetry system is being implemented.

---

## 18. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Group P Phase 16.9 completion record.
- `docs/decisions.md` if a durable diagnostics decision is required.

Document:

1. Diagnostic architecture.
2. Severity levels.
3. Event categories.
4. Safe metadata allow-list.
5. Sensitive-data exclusions.
6. Environment output policy.
7. Flutter error capture.
8. Network request correlation.
9. Clerk lifecycle diagnostics.
10. Router diagnostics.
11. Diagnostic ownership.
12. How future features emit events.
13. Testing approach.
14. Device verification.
15. Known limitations.

Provide a short developer example showing how a future feature records a safe failure event without exposing the API response or user input.

Do not create a second architecture guide.

---

## 19. Verification commands

Run from the Flutter project root:

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

Also inspect:

- Added dependencies.
- Android manifest changes.
- Source files for raw logging.
- Sensitive data exposure.
- Existing Clerk and ApiClient behavior.
- Existing router behavior.
- Token generation freshness.
- Feature dependency direction.

### Acceptance

- All existing 140 tests pass.
- New diagnostics tests pass.
- Analyzer passes.
- Debug APK builds.
- Token check passes.
- Staged and unstaged whitespace checks pass.
- No unapproved packages are added.
- No API contract changes occur.
- No backend changes occur.
- No commerce features are activated.

---

## 20. Explicit exclusions

Do not implement:

- Firebase Crashlytics.
- Sentry.
- Datadog.
- OpenTelemetry exporters.
- Google Analytics.
- Firebase Analytics.
- Remote log upload.
- A Laravel diagnostics endpoint.
- Persistent device log databases.
- Customer activity tracking.
- Behavioral analytics.
- Session recording.
- Screen recording.
- Production debug dashboards.
- Custom crash-report upload.
- Background synchronization.
- Automatic API retries.
- New authentication flows.
- New navigation destinations.
- Customer feature screens.
- Cart, checkout, payments, orders, tracking, or favorites.
- A new state-management framework.
- A new design system.

Future observability integrations require a separate architecture and privacy decision.

---

## 21. Final Group P verification

Because this is the last Group P phase, perform a foundation-wide regression review.

### Phase 16.1 — Project setup

Verify:

- Android-only target.
- Correct application identity.
- Existing package configuration.
- Stable startup.

### Phase 16.2 — Theme

Verify:

- Generated design tokens are synchronized.
- Material 3 theme is unchanged.
- No hard-coded visual values were introduced.

### Phase 16.3 — Environment

Verify:

- Configuration validation remains authoritative.
- No secrets are embedded.
- Diagnostics use the existing configuration field.

### Phase 16.4 — Networking

Verify:

- ApiClient remains centralized.
- Auth tokens remain request-scoped.
- Request IDs are preserved.
- No automatic retry was added.

### Phase 16.5 — Authentication

Verify:

- Clerk remains the sole authentication provider.
- Encrypted session storage remains intact.
- No token is logged.
- No alternative mobile session system exists.

### Phase 16.6 — Routing

Verify:

- One go_router exists.
- Public browsing remains public.
- `/account` remains protected.
- Deferred commerce routes remain absent.

### Phase 16.7 — Features

Verify:

- FeatureDependencies remains the composition boundary.
- Feature-first conventions remain intact.
- No premature feature implementation exists.

### Phase 16.8 — Error/loading

Verify:

- Shared states remain reusable.
- Error mapping remains safe.
- Cancellation behavior remains unchanged.
- No automatic mutation replay exists.

### Phase 16.9 — Diagnostics

Verify:

- Centralized diagnostic interface exists.
- Output is environment-controlled.
- Metadata is allow-listed.
- Sensitive information cannot be emitted through supported APIs.
- Unexpected failures are captured without duplication.

---

## 22. Completion report

Provide:

1. Repository inspection findings.
2. Final diagnostic architecture.
3. Files added and modified.
4. Diagnostic interface and sink design.
5. Severity and event taxonomy.
6. Sensitive-data protection.
7. Environment output policy.
8. Framework error capture behavior.
9. ApiClient integration.
10. Clerk integration.
11. Router integration.
12. Phase 16.8 integration.
13. Dependency injection changes.
14. Test coverage and counts.
15. Physical Android verification.
16. Documentation changes.
17. Foundation-wide regression results.
18. Remaining blockers.
19. Phase 16.9 PASS/FAIL.
20. Group P overall completion status.

## 23. Definition of Done

Phase 16.9 is complete when:

- A centralized diagnostic interface exists.
- Structured events use stable categories and severity levels.
- Diagnostic output is controlled by existing environment configuration.
- Sensitive data is excluded by construction.
- Laravel request IDs can be used for safe correlation.
- Existing network, Clerk, navigation, and presentation boundaries remain intact.
- Unexpected framework errors are handled safely.
- Expected failures are not logged repeatedly.
- Diagnostic sinks are testable.
- Existing 140 tests and new tests pass.
- Analyzer and debug APK build pass.
- Token synchronization passes.
- Documentation is complete.
- Group P regression review passes.
- Remaining external verification blockers are accurately recorded.

**Final instruction:** Implement the smallest useful privacy-safe diagnostics foundation, preserve every completed Group P architecture decision, run the full verification suite, and stop at Phase 16.9. Do not begin Group Q until this phase is reviewed and accepted.

---

## 24. Phase 16.9 Completion Record

### Repository inspection findings

- No existing centralized logger, error-capture service, remote telemetry sink, or
  persistent log store existed.
- `ENABLE_DIAGNOSTICS` already belonged to the validated `AppConfig` contract and
  defaults to false.
- `ApiClient` already preserved Laravel `meta.request_id`, header fallback,
  stable error codes, numeric `Retry-After`, and cancellation classification.
- Clerk, routing, and Phase 16.8 presentation boundaries were already centralized
  and were extended without changing their protocols or UI ownership.

### Final architecture

- `AppDiagnostics` is the single recording interface.
- `DiagnosticEvent` uses closed severity, category, code, operation, transport,
  route, and HTTP-method vocabularies.
- `DiagnosticSink` has no-op, console, and bounded in-memory implementations.
- `DiagnosticPolicy` reuses `AppConfig` and disables production output regardless
  of an accidental enable flag.
- `DiagnosticErrorBoundary` captures framework and unhandled async failures,
  delegates existing handlers, and suppresses duplicate object/stack reports.

### Integration ownership

- Bootstrap records safe startup/configuration failures and installs framework
  boundaries; it never records raw startup exceptions.
- `ApiClient` records typed API/transport failures after awaiting response parsing;
  cancellations remain silent and no retry was added.
- Clerk adapter and secure persistence record restore, renewal, sign-out, and
  storage failures without identity, token, storage-key, or exception details.
- The central router records unknown routes, invalid resource parameters, and
  unsafe authentication destinations without route parameters or URLs.
- `FeatureDependencies.diagnostics` exposes the interface to future features.
- Phase 16.8 remains responsible for user-facing error mapping and recovery UI.

### Privacy and output policy

Only approved context fields can be emitted: environment, operation, HTTP method,
status, stable API error code, Laravel request ID after format/length validation,
numeric retry delay, transport category, route-failure category, and cancellation
state. Bodies, headers, URLs/query strings, Clerk tokens, passwords, cookies,
secure-storage contents, user identity/contact data, user text, raw exception
messages, and production stack traces are excluded.

Local/staging console output requires `ENABLE_DIAGNOSTICS=true`; production always
uses a no-op sink. Sink failures cannot interrupt application operations.

### Verification

- New diagnostics, network, auth, and navigation tests pass.
- Full regression suite: **156 tests passed**.
- `flutter analyze`: passed.
- `dart format --set-exit-if-changed .`: passed.
- `dart run tool/generate_tokens.dart --check`: passed.
- `flutter build apk --debug`: passed.
- `git diff --check`: reports the two pre-existing intentional Markdown
  hard-break spaces on the phase header lines; no new code whitespace errors were
  introduced. `git diff --cached --check`: passed with no staged changes.
- No dependency, Android manifest, backend, API contract, commerce route, or
  authentication protocol change was introduced.

### Physical Android verification and blockers

The debug APK builds, but no physical Android device/Marionette session was
available for this phase's runtime log inspection. Therefore authenticated Clerk
diagnostic verification and production telemetry verification are not claimed.
Existing limitations remain: real Clerk sign-in configuration, deployed staging/
production origins, and Android App Links verification are pending.

### Result

- **Phase 16.9:** PASS
- **Group P overall:** COMPLETE, pending owner review of this final foundation
  record. Group Q must not begin until Phase 16.9 is accepted.
