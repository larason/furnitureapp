# SL Furnitures — Flutter Authenticated Session Bootstrap & Eager Laravel JIT Provisioning

**Implementation type:** Focused authentication reliability improvement  
**Application:** `frontend/app` — Flutter Android customer application  
**Backend:** `backend/laravel` — shared Laravel API  
**Identity authority:** Clerk  
**Local user authority:** Laravel + MySQL/MariaDB  
**API:** Existing frozen `GET /api/v1/me`  
**Production commerce mode:** REQUEST ONLY

## 1. Objective

Implement automatic Laravel JIT provisioning in the Flutter customer application immediately after Clerk establishes an eligible authenticated session.

Currently, Flutter initializes Clerk and restores existing sessions, but it does not automatically request the current Laravel user.

As a result, a customer may successfully register or sign in through Clerk without having a corresponding local Laravel `users` record until another authenticated API operation occurs.

Correct this gap by implementing an authenticated session bootstrap that automatically calls:

`GET /api/v1/me`

The call must occur after an eligible Clerk session becomes active, including after signup, signin, and session restoration.

Do not introduce any new API endpoint, authentication provider, user creation mechanism, or frontend-owned user record.

Laravel's existing JIT provisioning remains authoritative.

## 2. Current implementation context

The existing Flutter app already:

- Initializes Clerk during startup.
- Restores Clerk authentication state.
- Obtains the current Clerk session JWT through its authenticated API client.
- Sends `Authorization: Bearer <Clerk session token>` to Laravel.
- Calls `/me` when Profile/Account functionality requires the current user.
- Uses the same backend for authenticated furniture requests and enquiries.
- Integrates Firebase Messaging and its authenticated device-registration lifecycle.
- Uses the existing shared Flutter architecture and Material 3 design system.

The current `frontend/app/lib/app.dart` documents that no request is made during startup.

That behavior must be updated narrowly: public startup remains nonblocking, but an eligible authenticated session must trigger the background `/me` bootstrap.

Do not modify the meaning of anonymous catalog access.

## 3. Mandatory repository audit

Before making changes, inspect:

- Root `AGENTS.md`.
- `frontend/app/AGENTS.md`, if present.
- `frontend/app/lib/app.dart`.
- `frontend/app/lib/main.dart`.
- Clerk initialization and lifecycle adapters.
- Clerk authentication state listeners.
- Sign-up and sign-in screens.
- Email verification flow.
- Session restoration behavior.
- Existing authenticated API client.
- Existing `/me` API repository.
- Profile/Account screen implementation.
- Existing application lifecycle observers.
- `PushRegistrationCoordinator` and authentication-dependent FCM registration.
- Existing logout/account-switching logic.
- Relevant Flutter tests.
- Existing Laravel `GET /api/v1/me` implementation.
- Laravel Clerk authentication middleware.
- `LocalUserProvisioner`.
- Frozen authentication and user API documentation.
- Current configuration and dependency injection conventions.

Do not assume that the website implementation is suitable for direct copying into Flutter.

Use the Flutter application's established architecture.

### Audit deliverable

Before implementation, identify:

1. Which existing class owns Clerk session state.
2. How Flutter distinguishes loading, signed out, signed in, and incomplete sessions.
3. How it obtains a current Clerk bearer token.
4. How `/me` is currently requested.
5. Which app-level lifecycle boundary is safest for bootstrap.
6. How authentication changes are propagated to the app.
7. Whether multiple existing consumers can already request `/me`.
8. Whether push device registration depends on a local Laravel user projection.

Reuse these mechanisms rather than duplicating them.

## 4. Required authentication flow

The expected flow is:

Clerk initializes or changes authentication state.

If no valid session exists, do nothing.

If a session is incomplete, pending verification, or subject to required security tasks, do not attempt Laravel JIT provisioning.

When Clerk establishes an eligible authenticated session:

1. Resolve the active session through the existing Clerk SDK adapter.
2. Obtain a current Clerk session JWT.
3. Call the existing authenticated `GET /api/v1/me`.
4. Allow Laravel to verify the token.
5. Allow the existing Laravel JIT provisioner to create or resolve the local user.
6. Treat HTTP success as confirmation that Laravel has resolved the authenticated local identity.
7. Record successful bootstrap for that session.
8. Avoid repeating successful provisioning unnecessarily.

The existing backend remains responsible for determining whether the Clerk email is verified and whether the user is allowed to access the application.

Do not rely solely on client-side claims of verification.

## 5. Bootstrap trigger conditions

Implement bootstrap for the following cases.

### Case A — New signup

A customer registers through Clerk and completes the required email verification.

Once the Clerk session becomes eligible for authenticated API access, Flutter automatically calls `/me`.

The user should not have to open Profile or submit a form.

### Case B — Existing user signs in

A customer with a previously existing Clerk account signs in.

Flutter calls `/me` to resolve the corresponding Laravel user.

If the local user already exists, it is reused.

If the local user does not exist, Laravel performs its existing JIT provisioning.

### Case C — Restored session

The customer previously signed in and later reopens the application.

Flutter initializes Clerk and restores the session.

Once authentication state is ready, the app calls `/me` if that session has not already been bootstrapped during the current app process.

### Case D — Sign-out then sign-in

When the user signs out, clear the completed bootstrap state.

When a new eligible session becomes active, initiate a fresh bootstrap.

### Case E — Account switching

If Customer A signs out and Customer B signs in:

- Cancel or invalidate A's pending bootstrap.
- Clear A's cached bootstrap results.
- Resolve B's current Clerk token.
- Bootstrap B independently.
- Never associate A's Laravel user response with B's session.

### Case F — Temporary connectivity failure

If Laravel cannot be reached:

- Keep Clerk authentication state intact.
- Preserve public catalog browsing.
- Mark local-account bootstrap as pending/recoverable.
- Retry according to the approved bounded policy.
- Do not falsely report success.

## 6. Architecture and ownership

Create the smallest reusable session-bootstrap coordinator needed by the existing architecture.

Prefer a narrow service/coordinator that listens to Clerk authentication state and invokes the existing current-user repository.

Illustrative responsibilities:

`AuthenticatedSessionBootstrapCoordinator`

- Observe eligible Clerk session changes.
- Deduplicate bootstrap attempts.
- Obtain authenticated `/me` through the existing API abstraction.
- Track pending/succeeded/failed state.
- Reset on logout or identity change.
- Handle transient failures.
- Prevent stale asynchronous results from affecting a newer session.
- Dispose subscriptions and resources correctly.

The name is illustrative. Follow existing project naming conventions.

### Integration location

Integrate the coordinator at a stable app-level authentication lifecycle boundary.

Do not implement separate bootstrap logic inside:

- Home screen.
- Product listing screen.
- Profile screen.
- Furniture request form.
- Enquiry form.
- Navigation drawer.
- Individual authentication buttons.

Those existing features may continue to call `/me` when they genuinely need fresh profile data, but provisioning bootstrap itself should be centrally owned.

## 7. Startup performance

The application must remain usable while the bootstrap runs.

Do not block:

- Splash-screen dismissal unnecessarily.
- Public homepage rendering.
- Product browsing.
- Search.
- Category navigation.
- Public product detail screens.
- Anonymous furniture requests.
- Anonymous enquiries.

Do not make the entire Flutter app await Laravel availability merely because Clerk restored a session.

Use a background asynchronous operation with an appropriate lifecycle.

Do not introduce a full-screen loading overlay for successful normal authentication bootstrap.

If a protected feature requires the Laravel user projection, that feature may wait for the bootstrap or use the same shared `/me` request.

## 8. Deduplication and concurrency

Prevent duplicate simultaneous bootstrap requests.

The coordinator must be safe when:

- Clerk emits repeated authentication events.
- The application rebuilds.
- Authentication listeners are reattached.
- App lifecycle changes trigger reconciliation.
- Profile and bootstrap request `/me` concurrently.
- A session refresh occurs.
- Flutter widget tests rebuild the root application.

### Required semantics

- At most one coordinated in-flight bootstrap per session identity.
- Repeated notifications for the same successfully bootstrapped session should not trigger unnecessary requests.
- Failed attempts must remain retryable.
- A new authenticated identity must bootstrap independently.
- A stale response from the old identity must be ignored.
- Concurrent requests must never cause a duplicate Laravel user.
- Do not persist Clerk JWTs or sensitive session identifiers in insecure local storage.

If a shared in-flight `/me` request abstraction already exists, reuse it.

Do not invent a second user cache solely for this phase.

### Session identity

Use a stable identifier available from the verified SDK session context for deduplication.

Do not use email addresses as the authoritative identity key.

Do not put bearer tokens in log lines or persistent cache keys.

## 9. Failure handling and retry policy

Distinguish failure categories.

### Transient failures

Examples:

- Temporary network outage.
- Request timeout.
- Backend temporarily unavailable.
- Recoverable server error.

Requirements:

- Use bounded exponential backoff.
- Avoid retry storms.
- Cancel pending retries when authentication changes.
- Retry again on an appropriate application/authentication/connectivity transition if needed.
- Do not block public navigation.
- Do not create an unbounded loop.

Reuse existing HTTP retry conventions where applicable.

### Authentication failures

Examples:

- Expired Clerk session.
- Invalid bearer token.
- Incomplete verification.
- Pending required security tasks.
- Invalid Clerk issuer or audience.

Requirements:

- Do not attempt to bypass Laravel security.
- Do not automatically create a local user by another method.
- Do not retry the same invalid token indefinitely.
- Allow the normal Clerk SDK authentication lifecycle to resolve session changes.
- Do not sign out the customer automatically because of a transient backend error.

### Authorization failures

Examples:

- Inactive local account.
- Valid identity without permission for the requested operation.

Requirements:

- Preserve backend authorization.
- Do not reactivate a suspended account.
- Do not reset STAFF/ADMIN roles.
- Do not convert another user's profile into the current user's profile.
- Do not treat an authorization rejection as a successful bootstrap.

### Error presentation

The background bootstrap should avoid unnecessary snackbars.

Only show a user-facing error when authenticated functionality actually depends on the unavailable Laravel identity.

Reuse existing application error-state patterns.

## 10. Laravel API contract

Use the existing:

`GET /api/v1/me`

Do not create:

- `/api/v1/sync-user`
- `/api/v1/create-account`
- `/api/v1/provision-user`
- `/api/v1/clerk-sync`

Do not use a Clerk webhook as a replacement for this focused task.

Do not change the `/me` response schema.

Do not send:

- Client-selected role.
- User ID.
- Email for identity matching.
- Password.
- Clerk metadata claiming privileged access.
- Firebase token as authentication.
- Arbitrary device identifiers.

The request must send only the necessary authenticated request information, using the existing Laravel client contract.

## 11. Backend provisioning invariants

Audit the existing Laravel JIT implementation and preserve:

1. A verified Clerk identity may provision a local user.
2. Default first-time role is CUSTOMER.
3. The appropriate local customer profile is created.
4. Repeated requests reuse the same mapped user.
5. Existing role assignments remain unchanged.
6. Existing account status remains authoritative.
7. Email is not used to merge unrelated Clerk identities.
8. Unverified Clerk identities do not provision.
9. Invalid JWTs do not provision.
10. Concurrent provisioning remains idempotent.
11. Local authorization is determined by Laravel, not Flutter.

Change backend code only if the audit identifies a genuine defect preventing this behavior.

Do not expand into general authentication refactoring.

## 12. Interaction with Firebase device registration

Phase 18.4 added authenticated customer-device registration.

Audit how device registration reacts to Clerk authentication and Laravel local-user provisioning.

### Required behavior

If push registration is enabled, ensure device registration does not race ahead of an unavailable local user projection.

Prefer the following ordering:

1. Clerk eligible session established.
2. `/me` bootstrap succeeds.
3. Device registration may proceed through the existing approved flow, subject to its independent feature flag.

However, do not introduce a hard global dependency that blocks the whole app.

### Important constraints

- `NOTIFICATION_PUSH_REGISTRATION_ENABLED` and `NOTIFICATION_PUSH_ENABLED` remain independent.
- Do not enable either flag as part of this task.
- Do not change the approved device endpoints.
- Do not activate customer push fan-out.
- Do not re-register a device repeatedly after every `/me` call.
- Preserve logout cleanup.
- Preserve account-switch safety.
- Preserve existing fail-closed token ownership rules.
- Do not modify the ACTIVE-only uniqueness amendment.

Any interaction changes must have focused regression coverage.

## 13. Lifecycle and cancellation

Flutter has distinct application and authentication lifecycles.

The coordinator must handle both correctly.

### App background / foreground

Do not initiate duplicate `/me` calls on every resume.

If bootstrap previously failed due to a transient condition, an app resume may be an appropriate bounded retry trigger.

If bootstrap succeeded for the current session, avoid an unnecessary repeat.

### Logout

Immediately invalidate the bootstrap result for the former session.

Prevent in-flight responses from updating current-user state after logout.

Dispose or reset authentication-scoped listeners according to the existing architecture.

### App disposal

Do not leave orphaned timers, callbacks, streams, or pending operations.

Do not call `setState` on a disposed widget.

Do not let bootstrap exceptions become uncaught asynchronous errors.

## 14. Privacy and security

Maintain strict separation between Clerk and Laravel.

Clerk owns:

- Identity creation.
- Authentication.
- Email verification.
- Session issuance.
- Session restoration.

Laravel owns:

- Local user projection.
- CUSTOMER/STAFF/ADMIN roles.
- Account activation state.
- Customer profile.
- Business authorization.

Flutter owns:

- Session lifecycle observation.
- Bearer token transport.
- Bootstrap coordination.
- Presentation and recoverable client state.

### Explicit security prohibitions

Never:

- Store passwords.
- Log JWTs.
- Log complete `/me` response bodies.
- Persist bearer tokens in plaintext.
- Use emails for identity association.
- Allow customers to choose their Laravel role.
- Create a Firebase Auth identity.
- Use FCM tokens as an authentication credential.
- Modify Laravel permissions from Flutter.
- Disable Clerk email verification to make bootstrap easier.

## 15. Automated Flutter tests

Add deterministic tests covering the coordinator and its integration with application startup.

### Authentication lifecycle

1. Clerk loading: no request.
2. Signed-out state: no request.
3. Incomplete signup: no request.
4. Unverified email: no successful provisioning.
5. Pending required security tasks: no premature provisioning.
6. Active eligible session: one `/me` request.
7. Restored eligible session: one `/me` request.
8. Existing session re-emission: no unnecessary duplicate.
9. Rebuild of app root: no unnecessary duplicate.
10. Logout: bootstrap state resets.
11. Logout during in-flight request: stale response ignored.
12. Customer A to B: B bootstraps independently.
13. Session refresh with same identity: no duplicate user or unnecessary request.

### Networking and errors

14. Successful `/me`: state marked succeeded.
15. Timeout: recoverable failure.
16. Backend 5xx: bounded retry.
17. Temporarily offline: public app remains usable.
18. Token temporarily unavailable: wait or retry safely.
19. Invalid JWT: no infinite retry.
20. Inactive-account rejection: no accidental success.
21. Retry success after temporary failure: state recovers.
22. Duplicate simultaneous triggers: one in-flight bootstrap.

### Regression

23. Profile screen can still load current account details.
24. Authenticated furniture request still uses Clerk bearer authentication.
25. Authenticated enquiry still uses Clerk bearer authentication.
26. Anonymous catalog browsing still works.
27. Anonymous request/enquiry submission remains available.
28. Flutter navigation remains correct.
29. Existing accessibility semantics remain correct.
30. FCM device registration does not race incorrectly with provisioning.
31. Push registration remains disabled by default.
32. Existing notification tests remain green.

Avoid real Clerk or network calls in ordinary widget tests.

Use mocks/fakes at the appropriate existing SDK and repository boundaries.

## 16. Backend regression tests

Run relevant Laravel tests to confirm:

- New verified Clerk identity provisions correctly.
- Existing local user is reused.
- Duplicate calls do not duplicate users.
- Existing STAFF/ADMIN identities retain their roles.
- Invalid/unverified identities do not provision.
- Account suspension remains enforced.
- Authenticated `/me` preserves its frozen representation.

Do not weaken the existing hermetic Clerk verifier tests or modify their fixture assumptions.

If backend code is unchanged, reuse existing coverage and report what was actually tested.

## 17. Controlled local integration smoke test

Using a valid local Clerk instance and local Laravel backend:

### Test A — New customer

1. Create or identify a dedicated verified Clerk test customer that does not yet have a Laravel user row.
2. Launch the Flutter application.
3. Complete sign-in.
4. Verify automatic `/me` bootstrap occurs without opening Profile.
5. Confirm exactly one local `users` row is created.
6. Confirm its correct CUSTOMER role and customer profile.
7. Confirm no new user was created on subsequent `/me` requests.

Do not reset the database or delete normal customer users to construct the test.

### Test B — Session restoration

1. Sign in using the test account.
2. Close the Flutter app.
3. Relaunch it.
4. Wait for Clerk session restoration.
5. Verify `/me` synchronization occurs automatically.
6. Confirm the same Laravel user is reused.
7. Confirm public catalog screens are not blocked.

### Test C — Failure recovery

1. Simulate temporary Laravel unavailability.
2. Launch or sign in with an eligible Clerk session.
3. Confirm background synchronization fails safely.
4. Confirm public browsing remains available.
5. Restore Laravel availability.
6. Verify bounded retry/recovery successfully resolves `/me`.

### Test D — Account switch

1. Sign in as Customer A.
2. Trigger or observe a pending bootstrap.
3. Sign out.
4. Sign in as Customer B.
5. Confirm stale A results are discarded.
6. Confirm B is provisioned/resolved independently.
7. Confirm no cross-account user data is exposed.

### Test E — FCM regression

Verify that existing device-registration behavior remains unchanged and that no new push event becomes active.

### Smoke-test reporting

Record which results were observed using real Clerk sessions and Laravel, versus mocked tests.

Do not report a live test as passed when only automated tests were executed.

## 18. Documentation

Update the appropriate Flutter authentication completion record or create a focused implementation report following the project's existing conventions.

Record:

- The original lazy JIT provisioning gap.
- Bootstrap owner and integration point.
- Clerk session readiness criteria.
- Deduplication mechanism.
- Retry/cancellation behavior.
- Interaction with existing `/me` repository.
- Interaction with FCM registration.
- New tests.
- Real smoke-test results.
- Remaining limitations.

Update `frontend/app/lib/app.dart` documentation to reflect that a signed-in eligible session can now initiate background account synchronization.

Do not create conflicting architecture documents.

## 19. Verification

Run from `frontend/app`:

```bash
flutter pub get
flutter analyze
flutter test
```

Run relevant Android build validation if the implementation affects application startup, platform integration, or dependency wiring.

Run relevant Laravel Clerk/authentication and `/me` tests from `backend/laravel`.

If backend code changes, also run the full Laravel suite, PHPStan, and Pint.

Inspect the final diff:

```bash
git diff --check
```

Do not expose environment secrets in logs or reports.

Do not run destructive database migrations.

Preserve the green baseline:

- Laravel: 1,733 passed, 8 skipped, 0 failed before this task.
- Flutter: 655 passed before this task.

Report actual new totals.

## 20. Definition of Done

Mark this task PASS only when:

1. An eligible Clerk session automatically initiates `/me`.
2. New verified customer identities provision without opening Profile or submitting a form.
3. Existing customers reuse their local Laravel user.
4. Restored Clerk sessions also trigger synchronization.
5. Public browsing is not blocked.
6. Repeated authentication events do not produce redundant requests.
7. Failed attempts are recoverable.
8. Logout and account switching cannot publish stale user state.
9. Existing backend role/security invariants are preserved.
10. Existing authenticated API client and frozen `/me` contract are reused.
11. FCM registration behavior remains safe.
12. Automated tests pass.
13. Any live integration verification is reported accurately.
14. Documentation is updated.

If real Clerk credentials or local test accounts are required, ask for the necessary human sign-in action rather than bypassing authentication.

## 21. Completion report

Return:

- PASS / PARTIAL / FAIL.
- Files changed.
- Existing authentication infrastructure reused.
- Bootstrap integration point.
- Session eligibility rules.
- Deduplication mechanism.
- Retry/error handling.
- Cancellation and account-switch safety.
- Laravel provisioning behavior.
- FCM registration interaction.
- Automated test results.
- Live smoke-test results.
- Remaining limitations.
- Confirmation that no new API endpoint or authentication mechanism was added.

**Implement only Flutter eager Laravel JIT provisioning. Keep Clerk as the identity authority, Laravel as the authorization and local-user authority, and public customer browsing nonblocking.**