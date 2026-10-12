# PR #33 — Focused Flutter/Laravel integration verification

Inspected 12 October 2026 (Africa/Dar_es_Salaam). [PR #33](https://github.com/larason/furnitureapp/pull/33) merged at commit `ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38`; resolving `main` during this inspection returned that same commit. Flutter and the current Laravel contract therefore share the inspected snapshot.

**Result:** request field mapping and ordinary response parsing align. Three source-verified edge-case mismatches remain, with focused integration test gaps. This is a static verification report: Flutter, Dart and PHP executables are unavailable in this environment. No live Clerk/Laravel calls or automated suites were run. Repository completion records report earlier passing tests; those results are not independently reproduced here. No application code or GitHub review state was changed.

## Closed findings remain closed

The latest Gitar review records four closed findings and functional validation “Not enabled.” Inspected merged code confirms:
- Blank supplied names are included in PATCH, enabling Laravel validation.
- Profile load failures show mapped copy in a live-region Semantics widget plus retry.
- Legal load failures show neutral customer copy.
- Diagnostics flow from AppRouter through both legal screens to LegalDocumentView. The direct missing-asset widget test records the event and asset path.

Evidence: [frontend/app/lib/features/account/data/profile_repository.dart:29-39](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/features/account/data/profile_repository.dart#L29-L39); [frontend/app/lib/features/account/presentation/account_screen.dart:57-79](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/features/account/presentation/account_screen.dart#L57-L79); [frontend/app/lib/navigation/app_router.dart:163-171](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/navigation/app_router.dart#L163-L171); [frontend/app/test/features/information/information_screen_test.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/test/features/information/information_screen_test.dart). None of these four issues is reopened below.

## Wire contract and parsing

Laravel: [backend/laravel/routes/api.php](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/backend/laravel/routes/api.php), [backend/laravel/app/Http/Requests/UpdateMeRequest.php:19-51](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/backend/laravel/app/Http/Requests/UpdateMeRequest.php#L19-L51), [backend/laravel/app/Http/Controllers/Api/V1/MeController.php:20-44](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/backend/laravel/app/Http/Controllers/Api/V1/MeController.php#L20-L44), [backend/laravel/app/Services/UpdateCustomerProfile.php](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/backend/laravel/app/Services/UpdateCustomerProfile.php).

Flutter: [frontend/app/lib/features/account/data/profile_repository.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/features/account/data/profile_repository.dart), [frontend/app/lib/features/account/data/customer_profile.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/features/account/data/customer_profile.dart), [frontend/app/lib/core/network/api_client.dart:356-400](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/core/network/api_client.dart#L356-L400).

Both requests resolve to `/api/v1/me`, require a current Clerk bearer token, and send `Accept: application/json`. PATCH also sends `Content-Type: application/json`. GET has no body. No identity, email, role, password or verification fields are sent in PATCH.

| Repository invocation | Exact JSON body | Laravel result under its validation rules |
| --- | --- | --- |
| name: ' Asha ', phone: ' +255712345678 ' | {"name":"Asha","phone":"+255712345678"} | Both fields submitted after trimming; phone must satisfy backend pattern |
| name: ' Asha ', clearPhone: true | {"name":"Asha","phone":null} | Clears phone |
| name: '   ' | {"name":""} | Rejected as invalid name; blank names cannot be saved |
| phone: '+255712345678', name omitted | {"phone":"+255712345678"} | Valid partial update if phone satisfies pattern |
| no arguments | {} | Rejected with profile-level validation error |

Laravel permits only name/phone; supplied name must be a string of 1–120 characters; phone may be null or a string matching its centralized format. Updates consume strictly `validated()`, persist dirty attributes and return refreshed data.

GET and PATCH both return `{"data":{...}}` with id, role, name, email, phone, email_verified and timestamps. Flutter decodes `data` rather than the whole envelope, preserves nullable name/phone and requires string id/role/email plus boolean email_verified. Ignoring timestamps is harmless for this screen. No verified swap between name, email and phone was found. Laravel returns private/no-store and Vary: Authorization headers. Flutter refetches GET after a successful PATCH rather than displaying the PATCH result directly.

## Verified mismatches and smallest fixes

### 1. Phone-only edit is rejected when the existing name is null

**Evidence:** [docs/api/openapi.yaml:726-740](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/docs/api/openapi.yaml#L726-L740) allows a null read-side name; Flutter also explicitly accepts it. [frontend/app/lib/features/account/presentation/account_screen.dart:188-194](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/features/account/presentation/account_screen.dart#L188-L194) always supplies `name: _name.text`; its controller is empty for a null name. The repository consequently emits `{"name":"","phone":"+255712345678"}`. [backend/laravel/app/Http/Requests/UpdateMeRequest.php:19-24](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/backend/laravel/app/Http/Requests/UpdateMeRequest.php#L19-L24) rejects that name, although a phone-only PATCH is allowed.

This is an unchanged-null-field problem, distinct from the closed issue where a deliberately cleared existing name was silently discarded.

**Smallest fix:** in the form, omit name only when the loaded name was null and its trimmed input is still empty. Continue sending "" when a previously populated name is deliberately cleared. Do not relax Laravel name validation.

**Regression:** add an account-screen test loading name:null, changing only phone and asserting the repository receives name:null; add a real ApiProfileRepository transport test asserting the body contains only phone. Retain the existing cleared-name payload test.

### 2. Local authentication failures receive generic service-error copy

**Evidence:** [frontend/app/lib/core/network/api_client.dart:356-372](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/core/network/api_client.dart#L356-L372) throws ApiAuthenticationException when the token is absent, blank or fails retrieval. [frontend/app/lib/core/presentation/error_presentation_mapper.dart:29-46](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/core/presentation/error_presentation_mapper.dart#L29-L46) handles ApiError and ApiTransportException but not ApiAuthenticationException, so this path receives “We could not complete that request. Please try again later.” An HTTP 401 instead maps to “Please sign in to continue.”

The client correctly prevents transport without a token and records an authentication diagnostic. The mismatch is in customer-facing classification, not bearer security. The exception currently conflates an absent token with token-provider failure, so an unavailable SDK must not be assumed to mean the session ended.

**Smallest fix:** explicitly map ApiAuthenticationException to safe authentication-unavailable copy. If distinct sign-in guidance is desired, preserve a typed reason for missing-token versus retrieval-failure. Keep SDK details private.

**Regression:** add a mapper test and account load/save tests exercising a real ApiProfileRepository with an unavailable token. Assert zero transport calls, appropriate copy, preserved edits and a recorded authentication diagnostic.

### 3. Missing-data envelopes drop diagnostic correlation

**Evidence:** [frontend/app/lib/core/network/api_client.dart:535-565](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/core/network/api_client.dart#L535-L565) extracts a sanitized X-Request-Id, then throws a const invalidEnvelope exception without requestId or statusCode when a successful JSON response lacks data. The transport diagnostic later reads those properties from the exception. A response such as HTTP 200, X-Request-Id:req-profile-123, body:{} therefore produces an uncorrelated failure event. Decoder failures follow a separate path that preserves the header request ID.

**Smallest fix:** construct invalidEnvelope with `requestId: responseId` and `statusCode: response.statusCode`. Keep the existing sanitization.

**Regression:** in api_client_test.dart, request /me with body:{} and a safe request ID; assert the exception and recording sink retain request ID and status. Also assert unsafe request IDs remain omitted.

## Auth/session and guards

Source alignment: [frontend/app/lib/core/auth/clerk_auth_adapter.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/core/auth/clerk_auth_adapter.dart), [frontend/app/lib/navigation/route_guard.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/navigation/route_guard.dart), [frontend/app/lib/navigation/app_router.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/navigation/app_router.dart), [frontend/app/lib/app.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/app.dart), [frontend/app/lib/main.dart](https://github.com/larason/furnitureapp/blob/ee824444d4bb4aa8f54e4aa7d37f1c39d1a65c38/frontend/app/lib/main.dart).

One shared Clerk state backs the UI and token adapter. Auth notifier changes and token-stream changes refresh state. Only active sessions count as signed in; pending sessions require action. Required requests obtain tokens at request time. Explicit adapter sign-out blocks local token access immediately, and a token acquisition completing after sign-out starts is discarded.

A signed-out /account visit redirects to sign-in with a safe continue destination. Initializing, pending and unavailable sessions are held at /auth-status. Authentication restores safe intended destinations; direct sign-in returns Home; an observed signed-in → signed-out transition on a protected route returns Home. Public routes remain available. Guest/authenticated shell behavior and category pagination/cache/retry have deterministic tests.

Existing router tests predominantly use synthetic statuses and the Account placeholder rather than a real profile repository. Adapter tests use a fake gateway. These establish component behavior but do not prove the complete private-data lifecycle.

## Focused test gaps in inspected suites

| Exact test file | Coverage gap verified by reading its tests | Smallest addition |
| --- | --- | --- |
| frontend/app/test/features/account/profile_repository_test.dart | Only three synthetic-success tests; empty-name test returns 200 instead of a canonical Laravel rejection | Use a captured Laravel 422 envelope through the real API client; assert name field, status and request ID. Add populated phone and malformed profile parsing cases |
| frontend/app/test/features/account/account_screen_test.dart | Error tests inject repository exceptions; success stub does not persist a changed GET result; no successful clear-phone or blank-name rejection journey | Drive screen with ApiProfileRepository and sequenced GET/PATCH/GET transport responses; assert payload, refreshed controls, unchanged backend values after 422, retained input and no false success |
| frontend/app/test/navigation/app_router_test.dart | Protected routing uses placeholder Account; no pending profile load/save during sign-out | Mount the real Account route with delayed transport, sign out, complete the old response and assert Home remains visible with no private controls or newly issued authenticated requests |
| frontend/app/test/navigation/information_routes_test.dart | Tests public route access without a recording diagnostics sink | Assert both routed legal screens receive the same enabled diagnostics instance; existing direct missing-asset test already covers event emission |
| frontend/app/test/core/network/api_client_test.dart | Generic client coverage does not connect profile decoding, form mapping and diagnostics | Add /me-specific 401/422/429/5xx and malformed-response cases with a recording sink; assert correlation without logging name, phone, email or bearer values |

These are gaps in the inspected named suites, not a claim that every repository test was exhaustively inventoried. Generic mapper tests already distinguish HTTP error categories and preserve request IDs/Retry-After; those should be reused, not duplicated indiscriminately. The account screen currently shows mapped messages but does not render request IDs; correlation belongs in diagnostics and this is not treated as a UI defect.

**Follow-up order:** fix unchanged-null-name submission and local auth classification; preserve malformed-envelope correlation; add the targeted regression tests above. Then run the focused Flutter tests and one configured Clerk → Laravel GET/PATCH/GET journey covering populated fields, phone clearing, rejected name clearing, sign-out and re-entry. That live journey remains unverified.

