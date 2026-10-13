# Phase 18.4 — Firebase Cloud Messaging (FCM)

**Project:** SL Furnitures  
**Group:** R — Notifications  
**Phase:** 18.4 — Push Notification Architecture & FCM Integration  
**Backend:** Laravel + MySQL  
**Mobile:** Flutter + Android Material 3  
**Authentication:** Clerk  
**Push provider:** Firebase Cloud Messaging (FCM HTTP v1)  
**Deployment:** Future Coolify VPS  
**Initial production scope:** REQUEST ONLY

## 1. Current project status

The following phases are complete:

- 18.1 — Notification Domain.
- 18.2 — Email Notifications using Laravel Mail + Resend.
- 18.3 — In-App Notifications using MySQL + Laravel Reverb.

Phase 18.3 passed its real Clerk-authenticated local WebSocket smoke test.

The existing notification architecture has:

- Recipient-scoped logical notification records.
- Server-authoritative creation and read state.
- Closed notification type registry.
- Permission-based operational recipients.
- Post-commit queue dispatch.
- Source/type/recipient idempotency.
- Operational email delivery.
- Private Reverb broadcast channels.
- Independent `notifications-broadcast` queue.
- Frozen NOT-001 and NOT-002 APIs.

**Firebase preparation completed by owner:**

- Firebase project created through Firebase Console.
- Firebase CLI installed on development machine.

Do not recreate the Firebase project or replace the working notification architecture.

## 2. Objective

Implement a secure, maintainable FCM foundation for future Android customer notifications while preserving the frozen API and REQUEST ONLY production restrictions.

The phase should establish:

1. Firebase configuration for the existing Flutter Android application.
2. Firebase Messaging SDK integration.
3. Android notification permission handling.
4. Device token acquisition and refresh handling.
5. Authentication-bound device-registration architecture.
6. A secure Laravel FCM HTTP v1 sender.
7. OAuth 2.0 service-account authentication.
8. Queued delivery with bounded retries.
9. Secure handling of invalid/stale tokens.
10. Foreground/background/terminated-state message handling.
11. Logout and account-switch cleanup.
12. Automated tests.
13. A controlled local/device delivery test where authorized.
14. Documentation for future activation of approved notification types.

**Critical scope restriction:** The infrastructure may be completed now, but production business-triggered customer push delivery must remain disabled until a qualifying customer notification event is explicitly approved and available.

The existing staff request/enquiry notification types are not eligible for delivery to the customer Flutter app.

## 3. Mandatory audit

Before modifying code, inspect:

- Root `AGENTS.md`.
- Phase 18.1 completion record.
- Phase 18.2 completion record.
- Phase 18.3 completion record.
- Existing Laravel Notification model.
- Existing delivery attempt tracking.
- Existing mail/realtime notification jobs.
- Existing queue and retry infrastructure.
- Existing Clerk authentication middleware.
- Existing user model and role policies.
- Existing Flutter app architecture.
- Existing Flutter Clerk authentication adapter.
- Existing application lifecycle/state management.
- Existing Flutter dependencies and Android configuration.
- Android `applicationId`, build variants, and flavors.
- Existing Firebase configuration, if any.
- Frozen API notification documentation.
- The current version of `docs/api/openapi.yaml`.
- Existing project Firebase and environment notes.

Check actual Android application identity rather than assuming a package name.

Inspect the official documentation:

https://firebase.google.com/docs/cloud-messaging/flutter/client

https://firebase.google.com/docs/cloud-messaging/flutter/receive

https://firebase.google.com/docs/cloud-messaging/send/v1-api

https://firebase.google.com/docs/cloud-messaging/manage-tokens

https://firebase.google.com/docs/flutter/setup

Use maintained, compatible versions of official FlutterFire packages.

### Audit output

Report:

- Existing Firebase project linkage.
- Actual Android package/application ID.
- Existing Firebase packages.
- Whether `firebase_options.dart` exists.
- Current Android SDK requirements.
- Existing token storage.
- Existing authenticated API capabilities.
- Any frozen API gaps.
- Human configuration required.

Do not invent configuration values.

## 4. Approved notification scope

The active REQUEST ONLY notification types are:

- `NEW_MADE_TO_ORDER_REQUEST`
- `NEW_ENQUIRY`

These notifications are intended for authorized operational STAFF/ADMIN recipients.

The existing Flutter Android app is CUSTOMER-facing.

Therefore:

**Do not send these operational notifications to the customer Flutter app.**

Do not invent customer push types such as:

- `REQUEST_SUBMITTED`
- `REQUEST_STATUS_UPDATED`
- `ENQUIRY_RECEIVED`
- `ORDER_PAID`
- `ORDER_SHIPPED`
- `NEW_PROMOTION`

Do not activate frozen order/payment/delivery event producers.

Do not create new types without a formal frozen-contract decision.

### Expected Phase 18.4 outcome

FCM registration, receipt, secure transport, and delivery architecture are prepared and tested.

Automatic production push fan-out can remain dormant until approved customer events exist.

An explicitly authorized, development-only diagnostic message may be used for transport testing, but it must not be persisted as a new public notification type or exposed through the normal notification feed.

## 5. Firebase project setup

The owner has already created a Firebase project.

Do not create another.

Inspect whether the correct Android application is registered in this project.

If absent, notify the owner and use the existing Flutter application ID to register it with the approved project.

Do not rename the Android application ID or change release signing identities merely to simplify Firebase setup.

### FlutterFire CLI

Check:

```bash
firebase --version
flutter --version
dart --version
flutterfire --version
```

If FlutterFire CLI is missing, explain that it is distinct from Firebase CLI.

Use the official installation workflow:

```bash
dart pub global activate flutterfire_cli
```

Then configure only the approved Android target using the supported FlutterFire configuration command.

For example:

```bash
flutterfire configure
```

Use the actual selected Firebase project and Android application.

Do not configure iOS, web push, or desktop platforms unless the current phase requires them.

### Configuration

Prefer the generated FlutterFire options and standard Android Firebase integration.

Confirm:

- Correct Firebase project ID.
- Correct Android application ID.
- Correct sender/project number.
- Correct generated Firebase options.
- Correct debug/release build configuration.
- Correct Firebase initialization timing.

Do not manually fabricate Firebase configuration values.

Client-side Firebase configuration is not a server service-account credential. Treat provider secrets and private keys separately from ordinary Firebase client configuration.

Do not commit private server credentials.

## 6. Flutter dependency integration

Use maintained official packages:

- `firebase_core`
- `firebase_messaging`

Install only if not already present.

Do not add Firebase Authentication.

Clerk remains the only application authentication authority.

Do not introduce Firestore, Firebase Realtime Database, or Firebase Functions for notification storage.

MySQL remains the source of truth.

### Initialization

Initialize Firebase at the appropriate application startup boundary.

Use the generated Firebase configuration.

Ensure startup failures are handled safely.

Firebase initialization failures must not crash unrelated public catalog behavior unnecessarily.

Keep Flutter implementation aligned with the application's existing dependency injection, repository, and state-management architecture.

Do not create a second global state system solely for FCM.

## 7. Android notification permissions

Support Android notification permissions correctly.

For Android 13 and newer, handle runtime notification permission requests.

Do not assume permission is granted.

Do not show repeated permission dialogs at every application launch.

### Recommended user experience

Ask for notification permission at a contextually appropriate time.

Explain why notifications may be useful before the system permission request.

Respect denial.

Provide a route to system settings if permission changes later.

Avoid implying that customer push alerts are currently active when no approved customer events exist.

### Scope safety

For REQUEST ONLY launch, the agent must propose whether to keep automatic permission prompts disabled until actual customer notifications are activated.

Do not enable unsolicited notification permission requests merely because the SDK has been installed.

## 8. Device token lifecycle

Implement a clean Flutter-side abstraction for:

- Obtaining an FCM registration token.
- Detecting token refresh.
- Tracking permission state.
- Detecting authenticated user changes.
- Registering an eligible token with Laravel when supported.
- Deactivating an association on logout.
- Re-associating safely after login.
- Handling transient registration failures.
- Preventing duplicate registration.
- Avoiding unnecessary token requests.
- Ensuring correct behavior after reinstall and token rotation.

### Security invariants

An FCM token is not a Clerk authentication token.

FCM tokens must not be used to authenticate users.

Device registration must never permit a client to choose another user ID.

The Laravel recipient association must be derived from a verified Clerk session JWT.

Do not store application bearer tokens in FCM payloads.

Do not log complete registration tokens.

Do not put FCM tokens into analytics, crash reports, public URLs, or debug screenshots.

## 9. Frozen API checkpoint — device registration

This is a mandatory contract decision.

The frozen V1 API defines NOT-001 and NOT-002, but does not authorize arbitrary device-token registration and deletion endpoints.

Before introducing any externally callable route, inspect the complete current OpenAPI specification for a pre-approved device-registration contract.

If no suitable operation exists:

**STOP and notify the owner.**

Explain that authenticated device registration requires a reviewed API capability or an explicitly approved separate integration boundary.

Present the minimum proposed contract for review.

Do not silently add `/api/v1/me/devices`, `/api/v1/fcm-token`, or any other new public API path.

### Proposed capability for owner review

A minimum authenticated device-registration workflow should support:

- Register/update an app instance's current token.
- Remove/deactivate that app instance's association at logout.
- Bind the token to the authenticated user server-side.
- Preserve idempotency.
- Handle token rotation.
- Handle multiple devices.
- Avoid one user registering devices for another user.
- Avoid leaking whether a token belongs to another account.
- Handle inactive/revoked accounts.
- Use strict request validation.
- Enforce request limits.
- Minimize stored identifiers.
- Preserve backward compatibility.

The exact method/path/schema/error behavior must be explicitly approved under project API governance.

Do not implement the proposed route based solely on this illustrative description.

### Independent progress

Continue Flutter SDK setup, internal sender implementation, and transport tests that do not require the unresolved endpoint.

Do not label the entire phase PASS if this essential production integration remains blocked.

## 10. Laravel FCM server-side sender

Implement a secure internal FCM sending abstraction using the FCM HTTP v1 API.

Use short-lived OAuth 2.0 access tokens derived from server-authorized service-account credentials.

Do not use obsolete FCM legacy server-key authentication.

Do not use the Firebase web API key as a server push credential.

Do not use user-supplied OAuth tokens.

### PHP implementation choice

Review whether the existing Laravel application has a maintained, compatible Firebase messaging integration.

Choose the smallest reliable implementation.

Acceptable approaches include:

- A compatible, maintained PHP Firebase messaging library.
- A small Laravel HTTP client adapter using Google's PHP authentication library for OAuth 2.0.

Do not install multiple competing FCM client libraries.

Prefer vetted libraries for credential parsing, OAuth signing, token refresh, and message delivery.

Do not write custom cryptography or handcrafted service-account JWT signing when maintained libraries exist.

### Sender responsibilities

- Build a valid FCM HTTP v1 request.
- Authorize with short-lived OAuth 2.0 access tokens.
- Send only to approved registered tokens.
- Apply configured timeouts.
- Classify provider errors.
- Support bounded retries.
- Record safe delivery outcomes.
- Avoid duplicate sender implementations.
- Expose internal testable interfaces.
- Remain independent of customer-facing HTTP request processing.

## 11. Human checkpoint — Firebase server credentials

Firebase project creation and Firebase CLI installation do not automatically configure Laravel's service-account credentials.

Notify the owner when server credentials are required.

Guide the owner through:

1. Opening the existing Firebase project.
2. Selecting Project Settings.
3. Opening Service Accounts.
4. Reviewing the appropriate service account and minimum required permissions.
5. Ensuring FCM HTTP v1 API access is enabled.
6. Generating a service-account credential only if needed.
7. Storing it securely outside Git.
8. Configuring Laravel to read the approved credential.
9. Confirming the correct Firebase project ID.

Prefer Application Default Credentials where practical.

For local development, an approved service-account JSON path may be used through the supported environment configuration.

For Coolify, use securely provisioned secrets or protected credential mounts.

Do not copy the service-account JSON into public web assets, Flutter assets, the Docker image, or source control.

Do not request that the owner paste private keys into the chat.

Do not expose the service account to the Flutter application.

## 12. Laravel device-token persistence

If and only if the API contract decision in Section 9 is approved, implement a minimal registration store.

Inspect existing database tables first.

Prefer a normalized association between:

- Local authenticated user.
- App installation/device record.
- FCM registration token or secure token representation.
- Platform.
- Last registered/seen timestamp.
- Active/revoked status.
- Relevant lifecycle timestamps.

### Multiple devices

A user may sign in on multiple Android devices.

Do not overwrite the first device registration when a second device signs in.

### Account changes

A device token must not remain associated with the wrong user after account switching.

Support safe deactivation and reassociation.

### Token privacy

Store the minimum data necessary for delivery.

Use a keyed fingerprint or other appropriate safe lookup/uniqueness mechanism where justified.

Evaluate encryption at rest for any stored tokens that must be retrieved for sending.

Never expose FCM registration tokens through notification resources.

### Database safety

Use additive migrations.

No destructive reset of any existing development or production database.

Follow the root AGENTS.md migration guard.

## 13. Delivery attempts and idempotency

Reuse the notification-channel delivery infrastructure introduced in Phase 18.2 where compatible.

FCM delivery is downstream of a logical notification.

Do not create duplicate logical notifications solely to send push.

### Delivery tracking

Track internal delivery outcomes where applicable:

- Notification reference.
- Target registration or app instance.
- PUSH channel.
- Attempt state.
- Retry count.
- Last attempt time.
- Provider message identifier.
- Sanitized error classification.

Do not expose these fields through NOT-001 or NOT-002.

### Delivery semantics

FCM acceptance means the service accepted the message for processing; it does not prove that a human saw the notification.

Do not claim exactly-once delivery.

Use deduplication at the logical notification and delivery-attempt boundaries.

Handle concurrent workers safely.

## 14. Retry and failure handling

FCM transport errors must not affect successful furniture requests, enquiries, Resend email, or Reverb broadcasts.

### Requirements

- Queue push attempts separately or within an appropriately isolated notification channel workload.
- Use bounded retries.
- Apply exponential backoff.
- Respect provider retry guidance.
- Avoid retry storms.
- Distinguish transient from permanent failures.
- Deactivate tokens proven invalid.
- Do not delete every token after an arbitrary HTTP 400 response.
- Do not retry invalid tokens indefinitely.
- Log only sanitized errors.

Specifically handle expired/unregistered tokens.

Treat `INVALID_ARGUMENT` carefully: it may indicate a malformed payload rather than an invalid token.

Do not silently remove a valid token because the server generated an invalid message.

### Failure independence

If FCM is unavailable:

- Request/enquiry submission still succeeds.
- MySQL notification state remains intact.
- Resend remains functional.
- Reverb remains functional.
- Failed jobs are observable.
- No user data is exposed.

## 15. Android receiving behavior

Prepare Flutter's receiving lifecycle.

Cover:

### Foreground

Handle incoming push messages while the app is open.

Avoid automatically duplicating system notifications and custom UI notifications.

Do not display full customer-sensitive content without verifying the policy for the event.

### Background

Register the correct Flutter background message handler where required.

Keep background handlers small, safe, and compatible with Flutter's supported isolate/lifecycle rules.

Do not make privileged API requests from unauthenticated background contexts.

### Terminated application

Support notification-open handling when the app is launched from a push notification.

Do not assume the application already has a valid Clerk session.

Restore authentication state safely before attempting protected navigation.

### Token refresh

Use the appropriate Firebase Messaging token-refresh stream.

Do not retain obsolete tokens indefinitely.

### Duplicate message behavior

Deduplicate navigation and foreground presentation where practical using stable server-controlled message identity.

## 16. Push payload policy

Prefer minimal, safe payloads.

An approved business push should contain only the information necessary to alert the recipient and enable safe navigation.

Do not include:

- Full customer enquiry text.
- Private furniture specifications.
- Staff-only comments.
- Contact phone numbers.
- Email addresses.
- Passwords.
- Clerk JWTs.
- Firebase service-account credentials.
- Internal database dumps.
- Sensitive attachment URLs.

### Notification targets

A push target is not an authorization capability.

After opening a notification, the app must authenticate and fetch the authorized underlying resource through the Laravel API.

Do not allow raw push payload values to bypass authorization checks.

### Current launch policy

No business-triggered customer FCM events are approved yet.

Do not fabricate sample request-status alerts as production features.

## 17. Local diagnostic test mode

Since no production-eligible customer push event is active, implement a controlled transport-only diagnostic capability.

The diagnostic must be:

- Disabled in production.
- Restricted to local/test environments.
- Explicitly invoked by an authorized developer.
- Directed only to a designated test installation.
- Independent of the public NOT-001 and NOT-002 APIs.
- Unable to send arbitrary mass messages.
- Free from real customer private data.

Do not create a permanent unauthenticated HTTP send endpoint.

A local Artisan command or isolated test harness is preferable, provided its safety boundaries are explicit.

If a test requires a real FCM registration token or Google credential, ask the owner to supply the configuration securely without exposing secrets.

### Test success

Demonstrate a real Firebase transport result and actual Android-device receipt, not merely a mocked HTTP success.

If only provider acceptance is verified, explicitly distinguish that from device presentation.

## 18. Android notification channels and user experience

For approved future notifications, prepare appropriate Android notification-channel behavior.

Avoid unnecessary channel categories.

Use clear user-facing names and descriptions.

Do not create one Android channel per individual notification.

Ensure:

- Icon handling is appropriate.
- Notifications remain legible.
- User permission decisions are respected.
- Duplicate notifications are avoided.
- Deep links are validated.
- App state restoration is safe.
- Accessibility is considered.
- No staff operations appear in the customer Flutter application.

Avoid extensive custom notification UI.

The existing Flutter Material 3 design system remains authoritative.

## 19. Security hardening

Test against:

- Anonymous token registration.
- Forged Clerk identity.
- Client-selected victim user IDs.
- Registration for another account.
- Token reassociation conflicts.
- Missing or expired Clerk sessions.
- Deactivated users.
- Invalid tokens.
- Duplicate device registrations.
- Logout followed by another user's login.
- Sensitive token leakage.
- Unauthorized FCM sending.
- Firebase project mismatch.
- Stale tokens.
- Unsupported Android configuration.
- Incorrect notification deep links.

Do not use FCM registration tokens as authentication credentials.

Do not expose service-account keys to Flutter.

Do not send protected data to a stale device belonging to a previous user.

## 20. Automated testing

Add tests for:

### Flutter setup

1. Firebase initialization is configured.
2. Android app identity matches Firebase configuration.
3. Token lifecycle is handled correctly.
4. Token refresh updates local state.
5. Permission denial is handled.
6. Repeated initialization does not duplicate listeners.
7. Logout triggers the approved cleanup strategy.
8. Account switching does not retain stale recipient identity.

### Laravel sender

9. Correct FCM HTTP v1 endpoint.
10. Correct OAuth 2.0 authorization behavior.
11. Missing credentials fail safely.
12. Invalid project configuration fails safely.
13. Valid diagnostic payload is serialized correctly.
14. Transient HTTP errors retry.
15. Permanent errors do not retry indefinitely.
16. Unregistered tokens are deactivated.
17. Malformed payloads do not cause indiscriminate token deletion.
18. Message identifiers are handled safely.
19. No credentials or raw device tokens appear in logs.

### Authorization

20. Customer identity is server-derived.
21. A user cannot register another user's device.
22. Registration requires active, valid authentication.
23. Multiple devices remain independent.
24. Token rotation is handled.
25. Logout/account switching prevents cross-account leakage.
26. Unapproved STAFF operational events are not routed into the customer application.

### Regression

27. NOT-001 remains unchanged.
28. NOT-002 remains unchanged.
29. Resend email delivery remains unchanged.
30. Reverb notification signals remain unchanged.
31. Source/type/recipient deduplication is preserved.
32. Request/enquiry transactions remain independent of push failures.
33. Deferred order/payment/delivery notifications remain disabled.
34. No new public API endpoints are added without approval.
35. No new notification type is introduced.

Use official testing and mock facilities where possible.

Do not call live Firebase during ordinary CI runs.

## 21. Controlled real-device test

Once the required configuration is available, perform a controlled test using a suitable Android device or emulator with working Google Play services.

Steps:

1. Initialize Firebase successfully.
2. Confirm Firebase project/application identity.
3. Obtain the current FCM registration token securely.
4. Confirm notification permission state.
5. Invoke the approved local diagnostic sender.
6. Confirm provider acceptance.
7. Confirm receipt in foreground.
8. Test background receipt.
9. Test notification tap/launch behavior where applicable.
10. Confirm duplicate suppression.
11. Confirm token refresh behavior.
12. Confirm logout/account-switch handling.
13. Confirm no staff-only notification data is exposed.

Do not use a live customer communication event as the first FCM test.

If device availability or credentials block the test, report PARTIAL rather than fabricating PASS.

## 22. Coolify deployment readiness

Production will eventually use Coolify on a VPS.

Prepare deployment notes for:

- Secure Google credential storage.
- Firebase project ID.
- Queue worker for push.
- Worker supervision.
- Retry visibility.
- Secret rotation.
- Firewall/egress requirements.
- Environment separation.
- Crash recovery.
- Monitoring.
- Protected token data.
- Token cleanup lifecycle.

Do not deploy now.

Do not create a public FCM sender endpoint.

Do not modify production infrastructure without approval.

## 23. Mandatory human checkpoints

Notify the owner when any of these steps require manual action:

**Checkpoint A — Firebase project selection:** Confirm the exact existing project rather than creating a new one.

**Checkpoint B — Android registration:** Verify the Android package name and register the correct application if missing.

**Checkpoint C — FlutterFire configuration:** Help select only Android and the correct environment.

**Checkpoint D — Service account:** Explain how to provision and securely store the required server credentials.

**Checkpoint E — Frozen API approval:** Request authorization before implementing a new device-registration API or changing its public contract.

**Checkpoint F — Real Android device test:** Ask the owner to connect/approve a test device and required permissions.

**Checkpoint G — Live diagnostic push:** Request permission before sending a real FCM test notification.

**Checkpoint H — Customer notification activation:** Obtain separate approval for any new business event or recipient/channel policy.

**Checkpoint I — Production credentials and deployment:** Wait for the deployment phase.

Do not silently skip these checkpoints.

## 24. Verification

Run the project's existing verification commands.

For Laravel:

```bash
composer validate
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
git diff --check
```

For Flutter:

```bash
flutter pub get
flutter analyze
flutter test
```

Also run relevant Android build checks and focused integration tests.

Do not run destructive database migrations.

Do not run live provider tests inside ordinary automated test suites.

Report actual command results, not inferred success.

## 25. Documentation

Create:

`phases/phase-18.4-completion.md`

Follow the established completion-report conventions.

Include:

- Exact Firebase project linkage status.
- Android application identity.
- FlutterFire package versions.
- Generated configuration inventory.
- Server credential strategy.
- Token lifecycle model.
- API-contract decision and approval status.
- FCM sender architecture.
- Retry/error policies.
- Channel isolation.
- Diagnostic test procedure.
- Actual device test results.
- Security review.
- Existing Resend/Reverb regression results.
- Production environment requirements.
- Deferred customer notification activation.
- Human interventions and unresolved blockers.

Never include private credentials or raw FCM tokens.

## 26. Explicit exclusions

Do not implement:

- Firebase Authentication.
- Firestore notification persistence.
- Firebase Realtime Database.
- Cloud Functions for push orchestration.
- Novu.
- SMS.
- Marketing pushes.
- Customer request-status notifications without approval.
- Staff-only notifications on the customer Android app.
- Android staff/admin management screens.
- Production deployment.
- Unapproved API operations.
- New notification enum values.
- Order/payment/delivery notification triggers.
- iOS or web push support unless separately approved.

## 27. Definition of Done

Phase 18.4 can be marked PASS only when:

- The existing Firebase project is correctly linked to Flutter Android.
- The official Firebase Messaging SDK is integrated.
- Authentication remains Clerk-owned.
- Token lifecycle is implemented within an approved contract boundary.
- Laravel can securely authenticate to FCM HTTP v1.
- The sender is testable and correctly handles provider errors.
- Invalid token handling is safe.
- Push attempts are isolated and bounded.
- No frozen API violations exist.
- No staff notifications leak to customer Android users.
- Resend and Reverb remain functional.
- Automated tests pass.
- An authorized diagnostic push has been verified on an Android test device, or the project's approved acceptance criteria explicitly separate environment/device verification.
- Production customer business-event fan-out remains disabled pending a separate approved event.
- Documentation is complete.

If essential work is blocked by device-registration contract approval, credentials, or unavailable hardware, report PARTIAL with exact remaining actions.

Do not mark PASS solely because packages are installed.

## 28. Completion report

Return:

1. PASS, PARTIAL, or FAIL.
2. Repository audit findings.
3. Firebase project and Android app configuration status.
4. Flutter dependencies installed.
5. Server FCM transport choice.
6. Authentication and credentials configuration.
7. Device-token architecture.
8. Frozen API compatibility findings.
9. Queue/retry implementation.
10. Tests and verification results.
11. Real Android delivery evidence.
12. Human actions completed.
13. Human actions still required.
14. Deferred functionality.
15. Readiness for Phase 18.5/18.6.

**Final instruction:** Implement Phase 18.4 only. Establish FCM infrastructure without inventing new notification types, leaking operational data to customer devices, or silently changing the frozen API. Stop at owner intervention checkpoints and continue independent safe work where possible.