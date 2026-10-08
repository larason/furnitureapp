# Phase 16.5 — Authentication Storage & Session

**Project:** SL Furnitures — Flutter Android Customer Application  
**Group:** P — Flutter Application Foundation  
**Prerequisite:** Phase 16.4 completed  
**Implementation status:** READY

## 1. Objective

Implement secure, persistent Clerk authentication session management for the Flutter Android application and connect it to the existing Phase 16.4 networking layer.

The application must be able to:

1. Initialize its Clerk client safely.
2. Restore an existing authenticated session after app restart.
3. Maintain accurate authentication state.
4. Obtain a current Clerk session token for authenticated Laravel API requests.
5. Support Clerk-managed session renewal.
6. Sign out through Clerk.
7. Clear or invalidate session-dependent application state appropriately.
8. Continue supporting anonymous access without authentication.

This is an authentication infrastructure phase, not a sign-in UI phase.

**Non-negotiable:** Clerk is the only authentication authority. Laravel is the authority for application roles, ownership, permissions, and business account state.

## 2. Mandatory inspection

Before implementation, read:

- Root and Flutter `AGENTS.md`.
- `frontend/mobile` or the actual Flutter application directory.
- Existing Flutter README.
- `docs/decisions.md`, especially ADR/AUTH-009 through AUTH-013.
- `docs/clerk-authentication-architecture.md`.
- Group P Phase 16.1–16.4 records.
- `docs/api/openapi.yaml`.
- `docs/api/api-conventions.md`.
- `docs/api/api-contract.md`.
- `frontend/web` Clerk integration for reference.
- Phase 16.3 `AppConfig` implementation.
- Phase 16.4 `ApiClient`, `AuthTokenProvider`, cancellation, and API error handling.
- Existing Flutter test conventions and Android build configuration.

Determine the actual Phase 16.5 scope in the repository's Group P plan. If this instruction conflicts with a frozen decision, follow the frozen decision and report the conflict.

Do not overwrite or duplicate an existing abstraction.

## 3. Clerk SDK selection and feasibility gate

Before adding dependencies, investigate the current official Clerk Flutter/Dart SDK and any maintained compatible alternatives.

Verify against authoritative package documentation:

- Android support.
- Compatibility with the installed Flutter/Dart versions.
- Compatibility with the existing Clerk application.
- Session restoration.
- Secure session persistence.
- Session token acquisition.
- Token renewal behavior.
- Sign-out.
- Authentication state notifications.
- Handling of pending security tasks.
- Package maintenance and stability.

**Prefer an official maintained Clerk integration where it satisfies the requirements.**

If no suitable package exists, investigate a narrow supported integration, but do not invent a proprietary authentication protocol.

Do not independently implement Clerk's session cookies, refresh tokens, or token exchange from undocumented endpoints.

### Required decision

Record the selected SDK, version, supported APIs, persistence mechanism, and limitations.

If a secure supported session-restoration path cannot be established, stop before implementing a custom workaround and report the blocker.

Do not claim persistence is secure merely because a package calls its storage mechanism secure.

## 4. Authentication architecture

Implement a small, testable architecture.

Suggested structure, adaptable to repository conventions:

```text
lib/
  core/
    auth/
      auth_repository.dart
      clerk_auth_adapter.dart
      auth_session_state.dart
      auth_session_controller.dart
      auth_failure.dart
```

Create only files justified by real responsibilities.

### AuthRepository

Expose a narrow, SDK-independent contract for:

- Initializing authentication.
- Reading current session state.
- Observing authentication changes.
- Acquiring the current session token.
- Signing out.
- Disposing listeners and resources.

Do not expose Clerk SDK objects to feature repositories or screens.

### ClerkAuthAdapter

The adapter owns direct communication with the selected Clerk SDK.

It must:

- Use the configured Clerk publishable key.
- Restore and observe Clerk sessions.
- Obtain current session tokens using supported SDK methods.
- Delegate renewal to Clerk.
- Delegate sign-out to Clerk.
- Translate SDK failures into safe application-level results.

Avoid creating a generic authentication service with unrelated responsibilities.

### AuthSessionController

Introduce a controller only if it adds meaningful state coordination beyond the SDK's existing facilities.

Do not add a state-management dependency solely for this phase.

Use existing Flutter primitives where sufficient.

## 5. Authentication state model

Represent at least these conceptual states:

| State | Meaning |
|---|---|
| Initializing | Session restoration has not completed |
| Signed out | No usable Clerk session exists |
| Authenticated | Clerk reports a usable completed session |
| Action required | Clerk requires an outstanding verification/security step |
| Temporarily unavailable | Session validity cannot currently be established |

Adapt the model to the selected SDK's actual state vocabulary.

### Requirements

- Do not represent an unknown session as signed out prematurely.
- Do not represent a pending security-task session as authenticated.
- Do not equate local token presence with authentication.
- Do not equate an authenticated Clerk user with an authorized Laravel CUSTOMER.
- Do not trust cached email or user metadata as proof of identity.
- Do not create a second application-specific login session.

Authentication state transitions must be deterministic and testable.

## 6. Secure session storage

Use the SDK's supported persistent session mechanism where it provides the required Android security guarantees.

If the SDK requires a storage adapter, use an appropriate Android Keystore-backed encrypted storage mechanism.

### Security requirements

- No passwords in Flutter application storage.
- No Clerk secret keys in the APK.
- No raw bearer tokens in SharedPreferences.
- No raw bearer tokens in ordinary files.
- No credentials in SQLite.
- No credentials in logs, crash reports, or analytics.
- No credentials in route arguments.
- No credentials in widget state unnecessarily.
- No manually persisted bearer-token cache.

Only persist the minimum session material required by the supported Clerk integration.

Document what is stored, how it is protected, and which component owns deletion.

### Android-specific considerations

Inspect:

- Android backup and restore behavior.
- Device migration behavior.
- Keystore key availability.
- Reinstallation behavior.
- App-data clearing.
- Secure-storage read failures.
- Session invalidation after remote revocation.

Ensure encrypted session material cannot become silently reusable or corrupted following a backup/restore mismatch.

Do not disable Android backup globally without understanding the existing app policy.

If storage is unavailable or corrupted, fail safely and preserve anonymous app functionality.

## 7. Session restoration

At application startup:

1. Validate Phase 16.3 configuration.
2. Initialize the selected Clerk integration.
3. Attempt supported session restoration.
4. Resolve the initial authentication state.
5. Expose that state to the application.
6. Allow public application functionality regardless of authentication outcome.

Do not make a Laravel `/me` request merely to initialize the networking layer.

Do not block the entire public app indefinitely while Clerk is unavailable.

Use bounded initialization behavior and a recoverable failure state.

### Important

Session restoration is not the same as checking whether an old JWT string exists.

A restored session must be recognized by the Clerk integration as usable.

If connectivity is unavailable and validity cannot be established, do not silently grant protected access.

## 8. Phase 16.4 AuthTokenProvider integration

Implement the concrete adapter for the existing `AuthTokenProvider`.

**Do not redesign the networking layer.**

The flow must be:

```text
Protected feature request
        |
        v
ApiClient — REQUIRED auth
        |
        v
AuthTokenProvider
        |
        v
ClerkAuthAdapter
        |
        v
Current Clerk session token
        |
        v
Authorization: Bearer <token>
        |
        v
Laravel /api/v1
```

### Required behavior

- Obtain a current token for each authenticated request.
- Use the SDK's supported renewal mechanism.
- Do not retain a long-lived bearer token in `ApiClient`.
- Do not attach Authorization to public requests unless the request explicitly requires optional authentication.
- Do not attach tokens to third-party image or storage hosts.
- Do not expose tokens through exception messages.
- Do not create Laravel sessions or exchange Clerk tokens for custom mobile tokens.

The existing `AuthTokenProvider` contract must remain stable unless a proven compatibility issue requires a documented, narrowly scoped change.

## 9. Token expiry and renewal

Clerk owns token lifecycle management.

The Flutter implementation must use documented SDK methods for obtaining usable session tokens.

### Rules

- Do not implement a custom JWT signer.
- Do not parse JWT claims as a substitute for Clerk session authority.
- Do not create an independent refresh-token mechanism.
- Do not store manually calculated token-expiry timestamps as the session authority.
- Do not refresh continuously in the background without a documented requirement.
- Do not implement an unbounded renewal loop.

Where the SDK supports transparent renewal, use it.

Where renewal fails, surface a typed authentication failure or appropriate recoverable state.

Preserve the distinction between temporary connectivity failure and confirmed session invalidation.

## 10. Laravel 401 and 403 behavior

The existing API client already preserves typed HTTP errors.

Integrate authentication state handling without destroying that behavior.

### HTTP 401

May indicate an invalid, expired, revoked, or otherwise unacceptable authentication session.

Requirements:

- Preserve the original Laravel error code.
- Do not automatically sign out on every 401.
- Do not silently replay mutations.
- Do not initiate infinite refresh loops.
- Do not hide `SESSION_EXPIRED` or `INVALID_AUTHENTICATION`.
- Allow the owning authentication layer to reconcile the session using supported Clerk APIs.

If a narrowly bounded renewal/retry mechanism is genuinely necessary, document the exact conditions and prohibit unsafe mutation replay.

### HTTP 403

Means the request was authenticated but not authorized.

Do not treat 403 as a reason to refresh or sign out.

Laravel remains authoritative for local account status and permissions.

### Pending sessions

Clerk sessions with outstanding security tasks must not be treated as fully authenticated.

Laravel already rejects pending security-task sessions. The Flutter client must not bypass this boundary.

## 11. Sign-out behavior

Implement sign-out using Clerk's supported session lifecycle API.

### Required sequence

1. Initiate Clerk sign-out for the current session.
2. Observe or reconcile the resulting Clerk state.
3. Invalidate the local authenticated session projection.
4. Notify listeners.
5. Ensure subsequent REQUIRED API requests cannot use the old token.
6. Preserve public browsing.

### Important security cases

- In-flight authenticated requests during sign-out.
- Session changes during token acquisition.
- Sign-out while offline.
- SDK sign-out failure.
- Multiple sessions on different devices.
- Late authentication callbacks.
- Restart after successful sign-out.

A late token response from the previous session must not re-authenticate the application.

Where practical, use a session generation/version mechanism to reject stale asynchronous completions.

Do not implement global sign-out across all devices unless explicitly supported and required.

If sign-out fails, do not falsely report that the server-side Clerk session was revoked. Preserve a safe local state and communicate the unresolved remote state.

## 12. Multi-session behavior

The same customer may have sessions on:

- Next.js website.
- Android phone.
- Another Android device.

Do not assume one active session globally.

Signing out on Android must not automatically revoke unrelated web sessions.

Do not derive session ownership from email addresses.

Do not use Clerk user metadata to assign Laravel roles.

The backend's `users.clerk_user_id` mapping remains authoritative for linking external identity to local ownership.

## 13. Configuration and secrets

Reuse the Phase 16.3 configuration foundation.

Do not introduce a second `.env` loader.

Only public, client-safe Clerk configuration may be included in Flutter.

Clerk secret keys, backend verification secrets, and privileged API credentials must never be embedded in the Android app.

If Phase 16.3 does not currently provide the required public Clerk configuration, extend its validated configuration narrowly and document the change.

Do not weaken release-mode configuration validation.

Never hardcode a development Clerk key as a production fallback.

## 14. Integration with application startup

Integrate the authentication infrastructure into the existing Flutter bootstrap.

Requirements:

- Preserve the Phase 16.2 Material 3 theme.
- Preserve the Phase 16.3 configuration validation.
- Preserve the Phase 16.4 API client.
- No unnecessary startup Laravel calls.
- No sign-in screen in this phase.
- No new navigation architecture.
- No duplicate application root.
- No global mutable Clerk singleton outside the SDK's documented ownership model.
- Dispose subscriptions and resources correctly.

A Clerk initialization failure must not crash anonymous browsing unless the failure is a genuine invalid application configuration that the existing startup policy treats as fatal.

## 15. Automated tests

Use a fake SDK gateway or adapter for deterministic tests.

Do not require real Clerk credentials for the main unit-test suite.

### A. Initialization and restoration

Test:

1. No stored session.
2. Valid restored session.
3. Expired session.
4. Revoked session where detectable.
5. Pending security-task session.
6. Temporary network failure.
7. Corrupted session storage.
8. Repeated initialization.
9. Initialization timeout or bounded failure.
10. Safe anonymous fallback.

### B. Authentication state

Test:

11. Initializing to signed out.
12. Initializing to authenticated.
13. Authenticated to signed out.
14. Authenticated to action required.
15. State notifications.
16. Listener disposal.
17. Stale asynchronous callback rejection.
18. No duplicate event emission where inappropriate.

### C. Token provider

Test:

19. Valid current token returned.
20. No session returns no token.
21. Pending session returns no usable token.
22. Token renewal through SDK.
23. Renewal failure.
24. Concurrent token requests.
25. Session changes during token acquisition.
26. No bearer token stored in ApiClient.
27. No token returned from a signed-out session.

### D. Laravel integration

Test:

28. PUBLIC request requires no Clerk token.
29. REQUIRED request obtains token.
30. Bearer header attached correctly.
31. HTTP 401 preserved.
32. HTTP 403 preserved.
33. Pending-session token never dispatched.
34. No automatic unsafe mutation replay.
35. No custom Laravel authentication endpoint used.

### E. Sign-out

Test:

36. Successful Clerk sign-out.
37. Token unavailable afterward.
38. Listener notification.
39. Sign-out failure.
40. Offline sign-out behavior.
41. Sign-out during token retrieval.
42. Sign-out during an in-flight request.
43. Multiple sessions remain independent.
44. Restart after sign-out.

### F. Security

Test:

45. No raw token persistence.
46. No password persistence.
47. No token in exception text.
48. No token in logs.
49. No secret key embedded in configuration.
50. Secure storage failure handling.
51. No arbitrary-host bearer forwarding.
52. No unauthorized local role assignment.

Use the list as a coverage matrix, not a requirement to manufacture exactly 52 separate test functions.

Test actual SDK-backed persistence behavior where possible through an Android integration test rather than claiming mocked storage tests prove device security.

## 16. Live integration verification

Where a disposable Clerk development customer is available, verify the real authentication boundary.

Recommended sequence:

1. Initialize Clerk on Android.
2. Authenticate using a supported development-only test workflow or an already established test session.
3. Verify session restoration after process restart.
4. Acquire a current session token.
5. Call `GET /api/v1/me` through the existing ApiClient.
6. Verify Laravel returns the correct authenticated customer response.
7. Confirm token handling remains private.
8. Sign out using Clerk.
9. Confirm REQUIRED requests can no longer acquire a usable token.
10. Confirm public catalog requests continue working.

Use the real backend and real Clerk development application for this integration test.

Do not inject arbitrary test JWT strings and call that successful Clerk authentication.

Do not hardcode test credentials or ship a development authentication bypass.

If no supported sign-in workflow is available before a later UI phase, record the live authenticated integration as **BLOCKED**, not PASS.

A temporary development harness is acceptable if secure and removed afterward.

The Phase 16.4 physical-device request evidence gap should remain open until a real device request is observed.

## 17. Documentation

Update the Flutter README and existing Group P phase records.

Document:

- SDK/package selected.
- Why it was selected.
- Supported Android versions.
- Session storage mechanism.
- Restoration behavior.
- Authentication state model.
- Token-provider integration.
- Token renewal behavior.
- Sign-out behavior.
- 401 versus 403 handling.
- Offline and corrupted-storage behavior.
- Multi-device session semantics.
- Configuration requirements.
- Testing and live verification evidence.
- Known limitations.
- Deferred sign-in UI work.

Append an ADR to the existing `docs/decisions.md` only where a new durable architecture decision is required.

Do not create a competing decision log.

## 18. Verification

After final edits, run:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test
dart run tool/generate_tokens.dart --check
flutter build apk --debug
git diff --check
```

Adjust paths and token-check commands to the actual repository conventions.

Also verify:

- No unrelated dependencies.
- No Android permission added without justification.
- No iOS/web/desktop target generation.
- No frozen Laravel API changes.
- No design-token drift.
- No sensitive files committed.
- No auth secret exposed in the APK.
- No broken Phase 16.4 tests.
- No unintended startup network dependency.

## 19. Explicit exclusions

Do not implement:

- Customer sign-in screen.
- Customer registration screen.
- Email verification UI.
- Password recovery UI.
- Profile screen.
- Laravel profile repository.
- Catalog or product repositories.
- New Laravel auth endpoints.
- Sanctum or custom mobile JWTs.
- Custom refresh-token services.
- Application navigation.
- Cart, checkout, or payments.
- Biometric authentication.
- Push notifications.
- Background sync.
- Application-wide logging.
- Analytics.
- New design tokens.

Do not add a placeholder login screen simply to demonstrate SDK initialization.

## 20. Definition of Done

Phase 16.5 passes when:

- A suitable supported Clerk integration has been selected and documented.
- Secure Android session persistence is implemented through the supported integration.
- Existing sessions can be restored.
- Authentication state is observable and correct.
- Pending security sessions are not considered authenticated.
- `AuthTokenProvider` is backed by Clerk.
- Current session tokens are obtained without independent bearer-token storage.
- Laravel authenticated requests use the existing ApiClient.
- 401 and 403 remain distinct.
- No unsafe automatic request replay occurs.
- Sign-out uses Clerk and invalidates local authenticated access.
- Anonymous access continues to work.
- Unit tests and regression tests pass.
- Debug APK builds.
- Live integration evidence is recorded accurately.
- Documentation is updated.
- No later-phase UI or business features are introduced.

**Final instruction:** Inspect the existing repository and Clerk SDK capabilities first. Implement the narrowest secure session architecture compatible with the frozen decisions. Do not invent authentication protocols, weaken storage security, or change the Laravel authentication contract. Stop at Phase 16.5 and report any unresolved SDK or device-verification blocker explicitly.