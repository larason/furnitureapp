# Phase 16.4 — Flutter Networking Layer
## Group P — Flutter Application Foundation

**Project:** SL Furnitures — Android Customer App
**Status:** COMPLETE
**Prerequisites:** Phases 16.1, 16.2, and 16.3 complete

## 1. Objective

Implement a production-quality, domain-neutral HTTP networking layer for the Flutter Android application.

The networking layer must consume the existing validated `AppConfig.apiBaseUrl`, communicate with Laravel's frozen `/api/v1` API, preserve typed response and error contracts, and provide a secure authentication injection boundary for the future Clerk integration.

This phase must implement real transport behavior, not a collection of placeholder classes.

However, it must not implement application features, Clerk session management, customer screens, or business-domain repositories.

### Core principles

1. One authoritative HTTP transport boundary.
2. One canonical API version prefix.
3. Explicit request authentication requirements.
4. Strongly typed success and error handling.
5. Bounded timeouts and request cancellation.
6. No automatic replay of requests.
7. No storage of bearer credentials.
8. No duplicated Laravel business rules.
9. Deterministic tests with mocked HTTP responses.
10. Minimal, justified dependencies.

---

## 2. Mandatory Repository Inspection

Before writing code, inspect:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- Flutter project `README.md`.
- Phase 16.3 completion report and configuration implementation.
- `docs/decisions.md`.
- Group P phase records.
- `frontend/web/lib/api/client.ts`.
- `docs/api/api-contract.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- `docs/api/api-resources.md`.
- `docs/clerk-authentication-architecture.md`.
- Laravel route registration and error-response implementation.
- Current Flutter `pubspec.yaml`, test conventions, and application entry point.

Treat the actual repository as authoritative.

The Next.js API client is a behavioral reference, not an implementation template to copy mechanically.

Confirm the installed Flutter/Dart versions and current dependency compatibility before choosing a networking library.

### Inspection deliverables

Document:

- Existing configuration API and constructor requirements.
- Existing HTTP dependencies, if any.
- Canonical Laravel request and response contracts.
- Actual request ID and `Retry-After` conventions.
- Existing authentication boundary.
- Relevant API headers.
- Frozen pagination structure.
- Whether a live public read endpoint can be used for integration testing.

Do not modify the frozen backend contract to accommodate Flutter.

---

## 3. HTTP Client Selection

Prefer Dart's maintained `package:http` unless repository inspection demonstrates a concrete need for Dio or another client.

The Phase 13.5 Next.js client uses native `fetch` with a small transport abstraction. Follow the same architectural philosophy.

Avoid adding Dio solely to obtain interceptors, automatic retries, or generic service abstractions.

The selected implementation must support:

- GET, POST, PATCH, PUT where contracted, and DELETE.
- JSON request bodies.
- JSON response parsing.
- Request headers.
- Auth token injection.
- Timeout handling.
- Explicit cancellation.
- Mockable transport.
- Multipart support at the transport level if required by the frozen API.

If `package:http` is selected, inspect its supported cancellation mechanism, including `AbortableRequest` where applicable. Do not assume a `Future.timeout()` wrapper cancels an underlying socket request.

Implement cancellation only through a mechanism actually supported by the chosen client.

Do not build an unnecessary interceptor framework.

---

## 4. Recommended File Organization

Adapt names to existing conventions.

```text
lib/
  core/
    network/
      api_client.dart
      api_request.dart
      api_response.dart
      api_error.dart
      api_transport_exception.dart
      api_pagination.dart
      auth_token_provider.dart
      request_cancellation.dart
      network_constants.dart

test/
  core/
    network/
      api_client_test.dart
      api_response_test.dart
      api_error_test.dart
      api_pagination_test.dart
      api_auth_test.dart
      api_cancellation_test.dart
      api_security_test.dart
```

This is an architectural guide, not a mandatory file count.

Prefer cohesive classes and avoid one-file-per-trivial-type fragmentation.

Do not introduce a broad `core/` framework that extends beyond networking.

---

## 5. Centralized API Client

Implement one reusable client responsible for:

- Resolving the configured API origin.
- Constructing versioned request URLs.
- Applying standard HTTP headers.
- Encoding request bodies.
- Sending HTTP requests.
- Enforcing timeouts.
- Supporting cancellation.
- Parsing success responses.
- Parsing API failures.
- Preserving response metadata.
- Injecting authentication only when explicitly requested.

Suggested conceptual API:

```dart
final response = await apiClient.get<Map<String, dynamic>>(
  '/products',
  queryParameters: {
    'per_page': '20',
  },
);
```

The final method signature may differ.

Ensure the public API is easy for later feature repositories to consume.

### Dependency injection

The client should receive:

- Validated `AppConfig` or its validated API origin.
- An injectable HTTP transport.
- An optional `AuthTokenProvider`.
- A bounded timeout configuration, if justified.

The HTTP transport must be replaceable in tests.

Do not create a global mutable HTTP singleton.

Do not initialize Clerk or call Laravel merely by constructing the client.

### Resource ownership

If the client creates its own underlying HTTP transport, it owns and closes it.

If a transport is injected by the caller, ownership must remain explicit.

Closing the API client must not accidentally close a shared client owned elsewhere.

---

## 6. API URL Construction

Phase 16.3 guarantees that `API_BASE_URL` is an origin without `/api/v1`.

The networking layer must append `/api/v1` exactly once.

Example:

```text
Configured origin:
http://127.0.0.1:8000

Request path:
/products

Final URL:
http://127.0.0.1:8000/api/v1/products
```

### Required behavior

- Never duplicate `/api/v1`.
- Never allow callers to override the configured API host.
- Never allow an absolute URL as an ordinary API path.
- Reject protocol-relative URLs.
- Reject path traversal attempts.
- Preserve legitimate encoded resource identifiers.
- Encode query parameters correctly.
- Avoid double-encoding query values.
- Preserve the API's `snake_case` query naming.
- Handle empty query values according to the frozen contract.
- Support repeated query keys only where the contract explicitly permits them.

Do not create a generic arbitrary-URL fetch utility.

The networking layer is exclusively for the trusted Laravel API origin.

---

## 7. HTTP Headers

Apply the canonical API request headers.

### Standard JSON requests

```http
Accept: application/json
Content-Type: application/json
```

`Content-Type` is required when a JSON body is actually sent.

Do not send a misleading JSON content type on multipart requests.

Use UTF-8 JSON encoding.

### Authentication

For authenticated requests:

```http
Authorization: Bearer <Clerk session token>
```

The token must be obtained from the injected provider at request time.

Do not retain it as mutable client-wide header state.

### Special contract headers

The transport must permit narrowly controlled, explicitly requested headers such as:

- `Idempotency-Key`
- `X-Guest-Cart-Id`
- `X-Upload-Token`

Only where permitted by the frozen endpoint contract.

Do not automatically generate guest-cart credentials, upload tokens, or idempotency keys.

Those belong to later domain workflows.

Protect transport-owned headers from accidental caller overrides.

Do not attach browser cookies, CSRF tokens, or browser-specific headers to the bearer-only Android API path.

---

## 8. Authentication Token Provider

Implement a minimal abstraction equivalent to:

```dart
abstract interface class AuthTokenProvider {
  Future<String?> getToken();
}
```

Adapt naming to the approved Clerk architecture.

The interface must remain independent of Clerk package-specific classes.

### Authentication modes

Support explicit request intent:

- PUBLIC — no bearer token attached.
- REQUIRED — a valid token must be available before sending.

If an optional-authenticated request mode is required by the frozen contract, introduce it deliberately with documented behavior.

Do not make all public requests automatically authenticated merely because a user is signed in.

### Required behavior

For REQUIRED requests:

1. Ask the provider for the current token.
2. If unavailable, fail locally with a typed authentication-unavailable result.
3. Do not send an unauthenticated request accidentally.
4. Attach the bearer token only to the intended Laravel origin.
5. Do not persist or log the token.

For PUBLIC requests:

- Do not require Clerk initialization.
- Do not invoke token acquisition unnecessarily.
- Do not attach Authorization.

### Phase boundary

Phase 16.4 implements the interface and transport integration only.

Phase 16.5 owns:

- Clerk SDK initialization.
- Session restoration.
- Token renewal.
- Secure storage.
- Sign-in/sign-out.
- Authentication state listeners.

Do not introduce a fake production token provider.

Test providers may supply controlled dummy tokens.

### HTTP 401 behavior

Preserve HTTP 401 as a typed API failure.

Do not automatically sign out.

Do not blindly refresh or replay requests.

Phase 16.5 will define how session renewal integrates with the transport while preserving bounded retry semantics.

HTTP 403 must remain distinct from 401.

---

## 9. Typed Success Responses

Laravel uses the frozen success envelope:

```json
{
  "data": {
    "id": "123",
    "name": "Example"
  }
}
```

For collections:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

Implement typed representations for:

- Single-resource responses.
- Collection responses.
- Pagination metadata.
- Optional response metadata.
- Server request correlation ID.

### Data parsing

The transport must validate the envelope structure without imposing product-specific schemas.

Later domain repositories own the decoding of individual product, category, request, or profile models.

Do not generate all API domain models in this phase.

### Empty responses

Handle documented successful responses with no body, such as HTTP 204, separately from JSON envelope responses.

Do not treat a legitimately empty response as malformed JSON.

For a success status that contractually requires an envelope, a missing or invalid envelope is a protocol failure.

Do not fabricate `data: null` to hide malformed responses.

---

## 10. Structured API Errors

The frozen Laravel error envelope is:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "The supplied value is invalid.",
      "field": "delivery_address.city",
      "details": {}
    }
  ],
  "meta": {
    "request_id": "request-correlation-id"
  }
}
```

Optional error members must remain optional.

Implement an `ApiError` representation that preserves:

- HTTP status.
- All returned errors, in order.
- Machine-readable error code.
- Human-readable message.
- Optional field path.
- Optional structured details.
- Request correlation ID.
- Retry-after duration when supplied by the HTTP header.

### Important rules

- `errors` must be treated as an array.
- Preserve multiple field errors.
- Preserve canonical dot-path field names.
- Never branch on English message text.
- Do not collapse all failures into a generic exception.
- Do not rewrite server error codes.
- Do not invent new Laravel machine codes.

Keep the server's CLOSED error-code vocabulary distinct from client-side transport failure kinds.

A future domain layer may map a server code into a localized presentation.

### HTTP status coverage

Test at least:

| HTTP | Expected classification |
|---|---|
| 400 | Malformed request |
| 401 | Authentication failure |
| 403 | Authorization failure |
| 404 | Not found |
| 405 | Method not allowed |
| 409 | Conflict |
| 410 | Gone, where applicable |
| 413 | Payload too large |
| 415 | Unsupported media type |
| 422 | Validation/business failure |
| 429 | Rate limited |
| 500 | Server failure |
| 502–504 | Upstream failure |

Preserve the exact HTTP status independently of the API error code.

Do not translate an API 404 into a Flutter route-not-found event inside the transport.

---

## 11. Request ID Handling

Laravel returns a server-generated request ID.

The networking layer must support:

- `X-Request-Id` response header.
- `meta.request_id` in API error responses.
- Correlation ID preservation in typed failures.

When both sources are present, verify and document the chosen precedence and handling of inconsistencies.

Do not silently invent a new correlation ID to replace Laravel's ID.

Do not use customer IDs, bearer tokens, or resource identifiers as request IDs.

Do not automatically print correlation IDs to logs in this phase.

Phase 16.9 owns application diagnostics.

---

## 12. Retry-After Handling

Laravel uses the standard HTTP header:

```http
Retry-After: 60
```

Interpret numeric values as seconds.

Preserve the result in a typed property such as:

`retryAfterSeconds`

### Rules

- Parse valid non-negative numeric seconds.
- Reject malformed or negative values safely.
- Do not interpret numeric values as milliseconds.
- Do not invent a JSON `retry_after` field.
- Do not automatically retry a rate-limited request.
- Preserve HTTP 429 and its machine code.
- Support `Retry-After` on other responses where the server legitimately supplies it.

Check the frozen contract before supporting HTTP-date values; do not add behavior that contradicts the existing Next.js transport.

---

## 13. Transport Failure Taxonomy

Separate server-returned API failures from failures where no valid API response was received.

Suggested transport failure categories:

- Connection failure.
- DNS resolution failure, where distinguishable.
- TLS/certificate failure.
- Timeout.
- Cancellation.
- Invalid response JSON.
- Invalid response envelope.
- Unsupported response content type, where applicable.

Do not claim distinctions the selected HTTP library cannot reliably identify.

Use safe error messages.

Never expose raw exception strings that might contain sensitive URLs, tokens, request bodies, or device/network internals.

### Failure examples

| Scenario | Classification |
|---|---|
| Laravel returns 422 JSON | API failure |
| Laravel returns 429 JSON | API failure with Retry-After |
| Server unreachable | Transport failure |
| TLS certificate rejected | Transport failure |
| Request times out | Transport failure |
| User cancels request | Cancellation |
| HTTP 200 with malformed JSON | Protocol/decoding failure |
| HTTP 500 with HTML instead of API envelope | Protocol failure retaining HTTP status |

A transport failure must never masquerade as a Laravel domain error.

---

## 14. Timeouts and Cancellation

Implement bounded request timeouts.

Requirements:

- No unbounded HTTP request.
- Timeout duration is centralized and documented.
- Cancellation can be initiated by the caller.
- Timeout and explicit cancellation are distinguishable.
- Cancellation is not reported as an API 500.
- Late responses must not update canceled operations.
- Cancellation resources are cleaned up.
- Underlying connections should be terminated where the HTTP implementation supports it.

Avoid unnecessary configurable timeout hierarchies.

### Retry policy

**Do not implement automatic retries in Phase 16.4.**

In particular, never automatically replay:

- POST requests.
- PATCH requests.
- DELETE requests.
- Checkout or payment operations.
- Made-to-order submissions.
- Attachment uploads.

Even a failed or timed-out request may already have been processed by Laravel.

Future retries require endpoint-specific idempotency and reconciliation decisions.

---

## 15. JSON and Multipart Boundaries

### JSON

Use canonical JSON serialization.

Requirements:

- Encode request objects correctly.
- Preserve integer values without converting them to floating-point numbers.
- Do not transform `snake_case` keys into camelCase.
- Preserve explicit `null` where the contract permits it.
- Do not remove legitimate empty arrays.
- Reject unsupported request-body types.
- Do not silently convert arbitrary objects with `toString()`.

Money remains integer minor units plus currency.

Do not introduce floating-point money calculations.

### Multipart

The frozen API includes attachment operations.

Support multipart request construction through the selected transport library where necessary.

However:

- Do not implement attachment screens.
- Do not invent upload endpoints.
- Do not persist upload tokens.
- Do not implement file selection.
- Do not implement image compression or transformations.
- Do not bypass file-size or MIME restrictions.
- Do not add an upload service that duplicates future feature ownership.

If multipart support requires a distinct request path, keep it inside the same centralized API client boundary.

---

## 16. Response Security and Caching

The networking layer must not persist private API responses automatically.

Do not introduce:

- HTTP response disk caching.
- Offline persistence.
- Credential caches.
- Shared cookie jars.
- Background synchronization.
- Response logging.
- Analytics transmission.

Respect the Laravel contract's private/no-store boundaries.

Public catalog caching, if later required, must be implemented deliberately in its owning feature phase.

Do not implement an implicit in-memory global cache.

Do not attach authorization to external image URLs or arbitrary hosts.

The networking client is for Laravel API requests, not Cloudflare R2 image retrieval.

---

## 17. Automated Tests

Use mocked transport responses.

Tests must not depend on an active Laravel server, Clerk account, or public internet connection.

### A. URL construction

1. Correct API origin.
2. `/api/v1` appended once.
3. Correct path joining.
4. Correct query encoding.
5. Repeated parameters where supported.
6. Absolute URL rejected.
7. Protocol-relative URL rejected.
8. Path traversal rejected.
9. Unexpected API version prefix rejected.
10. Host cannot be overridden.

### B. HTTP requests

11. GET without body.
12. POST JSON encoding.
13. PATCH JSON encoding.
14. DELETE request.
15. Correct Accept header.
16. Correct Content-Type.
17. Explicit custom header allow-list.
18. Unsupported header overrides rejected.

### C. Authentication

19. Public request works without token provider.
20. Public request does not attach Authorization.
21. Required request obtains token.
22. Required request attaches correct bearer header.
23. Missing token fails before network.
24. Provider failure is classified safely.
25. No token persists between requests.
26. HTTP 401 remains distinct from 403.
27. No automatic logout.
28. No automatic authentication retry.

### D. Success parsing

29. Single-resource envelope.
30. Collection envelope.
31. Empty collection.
32. Pagination metadata.
33. Optional metadata.
34. HTTP 204 handling.
35. Invalid success envelope.
36. Malformed JSON.
37. Unexpected content type.

### E. API errors

38. Single error.
39. Multiple errors.
40. Field-level dot paths.
41. Structured details.
42. HTTP 401.
43. HTTP 403.
44. HTTP 404.
45. HTTP 409.
46. HTTP 422.
47. HTTP 429.
48. HTTP 500.
49. HTTP 502/503/504.
50. Request ID preservation.
51. Invalid error envelope.
52. HTML error response.

### F. Transport resilience

53. Connection failure.
54. Timeout.
55. Explicit cancellation.
56. Cancellation before dispatch.
57. Cancellation during request.
58. Late completion after cancellation.
59. No automatic retries.
60. No mutation replay.
61. Resource cleanup.

### G. Retry-After

62. Numeric seconds parsed.
63. Zero seconds accepted.
64. Invalid value rejected.
65. Negative value rejected.
66. Missing header handled.
67. No automatic retry.
68. Non-429 supported where contracted.

### H. Security

69. No bearer token in exception messages.
70. No bearer token in diagnostics.
71. No request-body leakage.
72. No credentials in query parameters.
73. No cross-origin redirect leaking Authorization.
74. No arbitrary-host request execution.
75. No insecure TLS bypass.
76. No private response caching.

Add tests for additional real edge cases discovered during implementation.

Do not add meaningless tests merely to increase the count.

---

## 18. Redirect and Credential-Leak Protection

Review the chosen HTTP client's redirect behavior carefully.

Authenticated requests must never forward bearer credentials to an untrusted redirect destination.

Prefer disabling automatic redirects for the Laravel API transport and treating unexpected redirects as controlled transport/protocol failures.

Do not silently follow an HTTP-to-HTTPS or HTTPS-to-HTTP redirect when authentication is attached.

A production API should already be configured with its canonical HTTPS origin.

Do not disable certificate validation to make local testing easier.

---

## 19. Integration with App Startup

Integrate the networking layer with the existing Phase 16.3 configuration foundation.

The application should be able to construct the API client after successful configuration validation.

However:

- Do not perform automatic API calls during startup.
- Do not require a reachable Laravel server for app launch.
- Do not introduce a provider/state-management package merely to expose the client.
- Do not change the existing theme preview harness.
- Do not implement a new application shell.
- Do not create customer-facing screens.

Preserve the existing debug/release bootstrap behavior.

Use explicit ownership and disposal for networking resources.

---

## 20. Real Android Device Verification

Use the existing local Laravel development environment where available.

For a physical Android device over USB:

```bash
adb reverse tcp:8000 tcp:8000
adb reverse --list
```

Then launch Flutter with the Phase 16.3 device configuration.

Verify:

1. Valid LOCAL configuration loads.
2. App starts successfully.
3. A real public Laravel API request succeeds using a test-only invocation.
4. The final URL contains `/api/v1` exactly once.
5. JSON responses are parsed correctly.
6. A known Laravel API error is parsed into the typed error structure.
7. No bearer token or secret appears in logs.
8. No Flutter crash or uncaught exception occurs.
9. Existing Material 3 preview remains intact.

Prefer a temporary test-only networking probe or an existing development test harness.

Do not leave a permanent customer-facing networking test screen.

Do not add an unconditional request to `main()`.

If Laravel is unavailable, document the limitation and rely on mocked transport tests. Do not report live integration as passing.

---

## 21. Documentation

Update the Flutter README and existing Group P phase record.

Document:

- Selected HTTP library and justification.
- API client architecture.
- Public request examples.
- Authenticated request examples using a test token provider.
- Versioned URL construction.
- JSON and pagination handling.
- API error taxonomy.
- Transport failure taxonomy.
- Request ID semantics.
- Retry-After handling.
- Timeout and cancellation behavior.
- Redirect security.
- Dependency ownership and disposal.
- Local device integration procedure.
- Test commands.
- Deferred responsibilities.

If a durable architecture decision is needed, append it to the existing `docs/decisions.md` following repository conventions.

Do not create competing architecture-decision files.

---

## 22. Verification Commands

Run all checks after the final code and documentation edits.

```bash
dart format --set-exit-if-changed .
flutter analyze
flutter test
dart run tool/generate_tokens.dart --check
git diff --check
```

Additionally, verify:

- `flutter pub get` succeeds.
- No unnecessary dependencies were added.
- No forbidden platform targets were generated.
- Existing environment-configuration tests pass.
- Existing design-system tests pass.
- All networking tests pass.
- No unintended backend changes exist.
- No API contract files were modified.
- No production secrets were introduced.

Use the repository's established static-analysis and CI conventions.

---

## 23. Explicit Scope Exclusions

Do not implement:

- Clerk SDK initialization.
- Clerk sign-in/sign-up.
- Clerk session restoration.
- Token refresh implementation.
- Secure token storage.
- Navigation and routing.
- Product repositories.
- Category repositories.
- Search repositories.
- Customer profile repositories.
- Request submission services.
- Checkout/payment services.
- Cart synchronization.
- Offline storage.
- Push notifications.
- Global error/loading widgets.
- Application logging infrastructure.
- Analytics.
- New Laravel endpoints.
- New API versions.
- Backend schema changes.
- iOS/web/desktop support.

Phase 16.4 must remain a networking foundation.

---

## 24. Completion Report

At the end, report:

1. Repository inspection findings.
2. Files created and modified.
3. HTTP library selection and justification.
4. API client public interface.
5. URL and header construction rules.
6. AuthTokenProvider contract.
7. Typed response and pagination models.
8. API error and transport failure models.
9. Timeout/cancellation behavior.
10. Retry and redirect policies.
11. Security controls.
12. Automated test results.
13. Physical Android verification results.
14. Documentation changes.
15. Risks and deferred work.
16. Final phase status.

Clearly distinguish:

- Automated tests passing.
- Local Android integration passing.
- Staging/production integration not yet verified.

Do not claim success for any check that was not executed.

---

## 25. Definition of Done

Phase 16.4 is complete when:

- One centralized networking client exists.
- The client consumes the validated Phase 16.3 configuration.
- `/api/v1` is appended exactly once.
- HTTP requests use canonical headers and JSON semantics.
- Public requests work without Clerk.
- Protected requests use an injectable token provider.
- Server success envelopes are correctly parsed.
- Structured Laravel errors are preserved.
- Pagination metadata is typed.
- HTTP status and error codes remain distinct.
- Request IDs are retained.
- Retry-After numeric seconds are handled correctly.
- Timeouts and explicit cancellation work.
- No automatic request retries exist.
- Credentials cannot leak through redirects.
- No sensitive request/response logging exists.
- Mocked networking tests pass.
- Existing Flutter tests and token checks remain passing.
- Android local integration is verified or explicitly recorded as blocked.
- Documentation is complete.
- No later-phase features have been implemented.

**Final instruction to the agent:** Inspect first, implement only the networking layer, follow the frozen Laravel V1 API contract, preserve the Phase 16.3 configuration foundation, validate all security boundaries, run every required check after final edits, and stop at Phase 16.4.

---

## Phase 16.4 Implementation Record

**Status:** COMPLETE. Automated networking verification and a local live API
probe passed; physical-device request execution remains explicitly unverified.

### Repository inspection findings

- Flutter 3.44.6 and Dart 3.12.2 are installed.
- No HTTP dependency existed before this phase.
- The frozen client contract uses `/api/v1`, `data`/`meta` success envelopes,
  `errors`/`meta.request_id` failures, `X-Request-Id`, numeric `Retry-After`,
  `snake_case` fields, and bearer-only Laravel authentication.
- The existing Next.js client was used as a behavioral reference for URL
  validation, envelope parsing, request-ID fallback, and safe transport errors.
- A temporary Flutter-client probe used the local public endpoint and was
  removed after verification; permanent tests continue to use mocked transport.

### Implementation

- Added `package:http` and direct `http_parser` dependencies.
- Added the centralized `ApiClient`, injectable `ApiTransport`, production
  `HttpApiTransport`, `AuthTokenProvider`, `RequestCancellation`, typed success
  metadata/pagination, structured API errors, and transport exceptions.
- Added explicit `PUBLIC` and `REQUIRED` authentication intent.
- Added request-scoped bearer injection, allow-listed special headers, JSON and
  multipart request construction, bounded timeout handling, abortable requests,
  redirect blocking, and no automatic retry behavior.
- Integrated client construction after validated startup configuration without
  performing a startup request. The application owns and disposes the client
  it constructs; injected clients remain caller-owned.

### Verification

| Command | Result |
| --- | --- |
| `flutter pub get` | Passed |
| `dart format lib test/core/network` | Passed |
| `flutter analyze` | Passed — no issues |
| `flutter test test/core/network/api_client_test.dart` | Passed — 19 tests |
| `flutter test` | Passed — 97 tests |
| Temporary live Flutter-client probe against `http://127.0.0.1:8000/api/v1/products` | Passed — `data` and pagination parsed |
| `dart run tool/generate_tokens.dart --check` | Passed |
| `flutter build apk --debug --dart-define-from-file=config/local.json` | Passed |
| `git diff --check` | Passed |

The final verification gate passed after the documentation edits. The package
manager reports newer incompatible transitive versions, but dependency
resolution and the locked build completed successfully.

### Deferred and unverified

- Clerk SDK initialization, session restoration, renewal, secure storage, and
  sign-in/sign-out remain Phase 16.5 responsibilities.
- Domain repositories and feature-specific request/multipart workflows remain
  outside Phase 16.4.
- The physical device was detected and USB reverse status was checked, but its
  shell had no `curl` and the app has no customer-facing request screen yet, so
  device-side request execution remains unverified. The host-side Flutter
  client probe passed against the live Laravel endpoint.

**Phase 16.4 exit condition:** COMPLETE.
