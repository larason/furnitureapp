# Phase 17.12 — Flutter Registration, Login & Profile/Account

**Project:** SL Furnitures  
**Application:** Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Phase:** 17.12  
**Scope:** Customer registration, login, verification, recovery, authenticated sessions, profile/account  
**Prerequisites:** Phases 16.1–16.9, 17.1–17.4, 17.10–17.11  
**Status:** READY FOR IMPLEMENTATION

## 1. Mission

Implement the first complete customer authentication and account-management experience in the Flutter Android application.

Use the existing Clerk integration, Laravel API, secure session storage, centralized routing, and Material 3 design system.

The feature must allow a customer to:

1. Register using email and password.
2. Verify their email when required by Clerk.
3. Sign in.
4. Recover a forgotten password.
5. Remain signed in across application restarts.
6. Sign out securely.
7. View their account.
8. View and edit permitted profile details.
9. Access authenticated customer features.
10. Recover gracefully from expired or invalid sessions.

Do not build a new authentication provider or store passwords in Laravel through Flutter.

**Critical:** This phase must make the existing authenticated Furniture Requests and Enquiries flows genuinely usable and testable.

Do not activate deferred commerce functionality.

---

## 2. Mandatory repository and contract inspection

Before modifying code, read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`, if present.
- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- `docs/decisions.md`.
- `docs/api/api-contract.md` sections 17, 18 and 29.
- `docs/api/api-conventions.md`.
- `docs/api/api-resources.md`.
- `docs/api/openapi.yaml`.
- `docs/domain/business-rules.md`.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/ACCESSIBILITY.md`.
- Existing Flutter authentication adapter.
- Existing AuthSession implementation.
- Existing secure-storage integration.
- Existing ApiClient authentication modes.
- Existing go_router configuration.
- Existing `/account` placeholder and protected-route logic.
- Existing Laravel Clerk middleware and user provisioning logic.
- Laravel USER-001, USER-002 and USER-003 handlers.
- Existing frontend website Clerk configuration and authentication conventions.
- Phase 17.10 and 17.11 completion records.

Inspect installed Flutter dependencies and the current supported Clerk Flutter SDK.

### Pre-implementation report

Identify:

- Exact Clerk Flutter SDK and version.
- Whether native Flutter sign-up/sign-in APIs are supported.
- Existing Clerk initialization lifecycle.
- Existing session-token retrieval contract.
- Existing session persistence mechanism.
- Current Laravel Clerk JWT verification.
- How Laravel creates or synchronizes a local customer record.
- Current customer role assignment.
- Actual USER-001/002/003 request and response schemas.
- Existing registration requirements.
- Email verification requirements.
- Password recovery support.
- Existing account deletion policy, if any.
- Existing route protection and redirect handling.
- Existing Flutter testing conventions.

Do not begin with UI implementation before these boundaries are understood.

If the installed Clerk package cannot support the required native workflow, investigate the officially supported integration path and document the decision before changing dependencies.

Do not fabricate Clerk SDK methods or rely on obsolete examples.

---

## 3. Identity and account architecture

The approved architecture is:

**Clerk → Authentication and identity**

**Laravel → Application authorization and customer profile**

**Flutter → Presentation, navigation and orchestration**

### Clerk responsibilities

- Email/password registration.
- Password verification.
- Email verification challenges.
- Login.
- Password recovery.
- Session creation.
- Session restoration.
- Token issuance and renewal.
- Sign-out.
- Identity lifecycle operations supported by the approved integration.

### Laravel responsibilities

- Verify Clerk bearer tokens.
- Resolve the local user.
- Enforce CUSTOMER authorization.
- Manage application-level customer profile data.
- Enforce active/inactive account state.
- Validate profile updates.
- Return canonical API errors.

### Flutter responsibilities

- Present authentication screens.
- Collect and validate user input.
- Invoke supported Clerk operations.
- Reflect authentication state.
- Attach tokens only through the existing ApiClient boundary.
- Load and update the Laravel profile.
- Navigate correctly.
- Clear private UI state on sign-out.

Do not introduce Firebase Authentication, Supabase Auth, custom Laravel password login, or a second identity database.

---

## 4. Registration

Implement a native registration experience using the approved Clerk Flutter integration.

### Registration fields

The established signup policy is:

- Email address — required.
- Password — required.

Do not require:

- Phone number.
- Delivery address.
- Billing address.
- Furniture preferences.
- Date of birth.
- Marketing consent.
- Customer role selection.

The account should be created as a CUSTOMER through the existing trusted backend identity provisioning flow.

Do not allow registration as STAFF or ADMIN.

### Registration screen

Recommended layout:

**Create your account**

Supporting copy:

"Create an account to manage your details and stay connected with SL Furnitures."

Fields:

- Email address.
- Password.
- Password visibility toggle.

Actions:

- Create account.
- Already have an account? Sign in.

A confirm-password field may be used only if it is consistent with the approved design and does not duplicate Clerk's security controls unnecessarily.

### Validation

Use Clerk's actual password policy.

Do not invent a minimum length that conflicts with Clerk settings.

Show useful validation messages without disclosing sensitive implementation details.

Handle:

- Invalid email.
- Weak password.
- Email already registered.
- Network failure.
- Rate limiting.
- Verification required.
- Unsupported sign-up configuration.

Do not automatically treat account creation as a fully authenticated session if Clerk requires verification.

---

## 5. Email verification

Implement the verification flow supported by the configured Clerk application.

Prefer the existing approved email-code verification method if it is enabled.

Do not hard-code an assumption that Clerk always uses six digits.

### Verification screen

Heading:

"Verify your email"

Supporting copy:

"Enter the verification code sent to your email address."

Controls:

- Code entry.
- Verify action.
- Resend code.
- Back or cancel.

### Behavior

- Respect Clerk challenge expiration.
- Respect resend restrictions.
- Handle invalid and expired codes.
- Prevent repeated submissions while verifying.
- Preserve the pending sign-up state.
- Do not log verification codes.
- Do not persist codes in ordinary app storage.

On successful verification, complete Clerk's supported session activation flow.

Then reconcile the customer identity with Laravel before showing protected account data.

### Important

Do not bypass email verification with local flags.

Do not mark email as verified in Flutter or Laravel without authoritative confirmation.

---

## 6. Login

Implement the sign-in screen.

### Fields

- Email address.
- Password.

### Actions

- Sign in.
- Forgot password?
- Create account.

### Behavior

Use Clerk's supported sign-in flow.

Handle all returned sign-in states explicitly.

Do not assume that providing correct credentials always completes authentication immediately.

If Clerk requires an additional supported verification factor, handle it through the approved SDK or show a clear unsupported-state message rather than bypassing it.

### Error handling

Handle:

- Incorrect credentials.
- Unverified account.
- Account not found.
- Disabled or blocked account.
- Rate limiting.
- Connectivity failure.
- Session activation failure.
- Unexpected Clerk state.

Avoid account-enumeration disclosures beyond the behavior of the approved Clerk integration.

Never log passwords.

---

## 7. Forgot password and reset password

Implement the officially supported Clerk password-recovery flow.

### Forgot-password screen

Heading:

"Reset your password"

Field:

- Email address.

Action:

"Continue"

Use Clerk's configured email-based recovery mechanism.

### Reset flow

Depending on the supported Clerk workflow, collect:

- Verification code or recovery challenge.
- New password.
- Any other required SDK-approved step.

Do not implement your own reset token generation.

Do not call a custom Laravel password reset endpoint unless the frozen architecture explicitly delegates this responsibility to Laravel.

### Security

- Do not log recovery codes.
- Do not persist recovery codes.
- Do not reveal whether an email belongs to an account beyond the provider-approved response.
- Respect provider throttling.
- Handle expired challenges.
- Require successful completion before reporting password reset success.

---

## 8. Clerk SDK integration

Inspect the existing ClerkAuthAdapter.

Prefer extending the existing adapter over creating a second independent authentication implementation.

### Required capabilities

Expose typed operations for:

- Sign up.
- Email verification.
- Sign in.
- Password recovery.
- Session activation.
- Session restoration.
- Sign out.
- Current authentication state.
- Current verified session token.
- Relevant identity metadata.

Use the actual supported SDK API.

### Separation

Presentation widgets must not directly depend on low-level Clerk internals.

The existing adapter should remain the authentication boundary.

Do not call Clerk REST endpoints manually if the supported Flutter SDK already implements the operation.

Do not duplicate secure-storage logic.

### SDK changes

If a dependency upgrade is necessary:

1. Identify the current and target versions.
2. Review migration requirements.
3. Check Android compatibility.
4. Check the project's Flutter/Dart constraints.
5. Update the lockfile.
6. Update affected adapter tests.
7. Document the change.

Do not upgrade unrelated dependencies.

---

## 9. Session persistence and restoration

A returning customer should not need to log in on every application launch when the Clerk session remains valid.

### Startup behavior

- Initialize the existing Clerk integration.
- Restore the session using the approved SDK and secure-storage mechanism.
- Resolve authenticated/anonymous state.
- Refresh tokens through the provider's supported mechanism.
- Avoid flashing protected account content before verification.
- Keep public catalog browsing available.

Do not make the entire application dependent on successful Clerk initialization.

### Session states

Support at least:

- Initializing.
- Anonymous.
- Authenticated.
- Expired/invalid.
- Recoverable error.

Represent intermediate provider states when necessary.

### Important distinction

Clerk authentication success does not automatically guarantee that Laravel authorizes the customer.

The app must handle:

- Valid Clerk identity, Laravel customer provisioned.
- Valid Clerk identity, local account provisioning pending.
- Valid Clerk identity, Laravel account inactive.
- Valid Clerk identity, unexpected role.
- Invalid or expired token.
- Temporary Laravel outage.

Do not silently treat a Laravel authorization failure as successful profile loading.

---

## 10. Laravel identity provisioning

Inspect the existing backend provisioning design.

Determine whether a local customer is created:

- Through verified-token first-use provisioning.
- Through a webhook synchronization flow.
- Through an existing explicit registration or synchronization endpoint.

Use the actual implementation.

Do not invent a new registration endpoint.

Do not create a local Laravel user by submitting a client-controlled Clerk subject.

### Role safety

Customer registration must never allow privilege escalation.

Do not accept client-supplied:

- `role`
- `permissions`
- `is_admin`
- `is_staff`
- `staff_state`
- `account_state`
- `user_id`
- `clerk_user_id`

Laravel must remain authoritative.

---

## 11. Account screen

Replace the existing `/account` placeholder with a real account experience.

### Authenticated state

Recommended sections:

**My Account**

- Customer name.
- Email address.
- Email verification status, if available.
- Phone number, if present.
- Edit profile.
- Account security.
- Sign out.

Use the canonical Laravel USER endpoint for application-level profile data.

Do not construct an account screen solely from unverified local identity metadata.

### Unauthenticated state

Display a welcoming account entry screen with:

- Sign in.
- Create account.

Do not show a broken protected-route placeholder.

If `/account` remains a protected route, implement the approved redirect to sign-in with a safe return destination.

Alternatively, use a public account landing route and protected account subroutes, but document and test the routing decision.

Do not silently change route semantics.

---

## 12. Profile read

Integrate the frozen USER-001 profile-read endpoint.

Confirm the exact method and path from the repository.

Use the existing ApiClient with required authentication.

### Data model

Decode only documented fields, such as the canonical customer identity and profile representation.

Do not invent:

- Loyalty points.
- Wallet balance.
- Customer tier.
- Saved addresses.
- Purchase totals.
- Review counts.
- Marketing preferences.
- Profile avatar uploads.

Handle absent optional fields safely.

### Loading behavior

Use existing AsyncViewState patterns:

- Loading.
- Content.
- Failure.
- Refreshing.

An empty optional phone number is not a failed profile response.

---

## 13. Edit profile

Integrate the frozen USER-002 profile-update endpoint.

Inspect its exact field allow-list.

Potential editable fields include:

- Name.
- Phone number.
- Other explicitly supported profile fields.

Do not assume email is editable through Laravel.

### Identity-owned fields

Email/password changes must follow Clerk's supported verified identity-management flow, not a generic Laravel profile update.

If those operations are not part of the frozen Phase 17.12 scope, provide truthful guidance or defer the controls rather than showing nonfunctional actions.

### Form behavior

- Prefill from confirmed profile data.
- Allow edits only to approved fields.
- Validate locally.
- Submit through the authenticated ApiClient.
- Handle backend 422 field errors.
- Disable duplicate submissions.
- Refresh the canonical profile after success.
- Preserve edits after recoverable errors.

Do not optimistically claim the profile was saved before Laravel confirms it.

---

## 14. Account security

Create a clear account-security section.

It may include:

- Verified email information.
- Change-password entry point, if the supported Clerk workflow permits it.
- Sign out.

Do not invent:

- Two-factor authentication settings.
- Trusted devices.
- Session history.
- Active-device revocation.
- Account deletion.
- Biometric login.

Only expose operations supported and approved by the configured Clerk integration.

If a security capability is not available, omit it or document it as deferred.

---

## 15. Sign-out

Sign-out must invalidate the active Clerk session using the provider's supported operation.

### Required behavior

- Invoke Clerk sign-out.
- Update AuthSession.
- Clear account-specific in-memory state.
- Cancel or invalidate protected in-flight operations.
- Remove stale profile UI.
- Return to a public route.
- Preserve anonymous catalog access.

Do not delete user-owned server records.

Do not delete anonymous enquiry or request records.

Do not rely on merely clearing a boolean `isSignedIn`.

### Error handling

If sign-out fails, do not falsely report that the provider session was revoked.

Prevent stale private account content from being displayed during transitions.

---

## 16. Navigation and route protection

Use the existing central go_router.

Suggested route structure, subject to the current route registry:

- `/account`
- `/sign-in`
- `/sign-up`
- `/verify-email`
- `/forgot-password`
- `/reset-password`
- `/account/edit`
- `/account/security`

Adapt names to the repository's established conventions.

Do not duplicate an existing route.

### Return-to navigation

When a customer is asked to sign in before accessing a protected screen:

1. Preserve the intended internal destination.
2. Complete authentication.
3. Verify the resulting session.
4. Return to the intended destination.

Only allow recognized internal application routes.

Do not accept arbitrary external redirect URLs.

### Public routes

These must remain accessible anonymously:

- Home.
- Catalog.
- Categories.
- Product details.
- Search.
- Furniture request submission.
- Contact & Enquiries.
- Legal/informational pages when implemented.

Do not redirect all visitors to registration on app launch.

### Shared navigation

Do not implement the global navigation drawer or final shared top app bar in this phase.

That work is reserved for Phase 17.15.

Use local navigation controls as needed to make authentication and account screens accessible.

---

## 17. Integrate with Phase 17.10 Furniture Requests

Phase 17.10 already supports both anonymous and authenticated submission.

Once login is implemented:

- Authenticated submissions must obtain the active Clerk token.
- Laravel must derive ownership.
- The client must never submit `user_id`.
- Anonymous submissions must remain available.
- Expired sessions must not silently downgrade to anonymous.
- Sign-out must restore explicitly anonymous behavior.

Test the actual existing Request repository rather than adding a replacement integration.

Do not modify the furniture request API contract.

---

## 18. Integrate with Phase 17.11 Enquiries

The Phase 17.11 completion record identifies authenticated live submission as an outstanding verification gap.

Close that gap.

### Requirements

- Sign in through the new Flutter UI.
- Open `/contact`.
- Submit an authenticated enquiry.
- Confirm the Clerk bearer token reaches Laravel.
- Confirm Laravel derives the correct local customer ownership.
- Confirm the client sends no `user_id`.
- Confirm account-derived contact behavior matches the backend contract.
- Confirm sign-out restores anonymous submission.
- Confirm a broken authenticated session does not silently submit anonymously.

Do not change ENQ-001 validation or endpoint topology.

---

## 19. Customer request and enquiry history

The frozen API includes authenticated ownership-scoped read operations for requests and enquiries.

However, this phase's core scope is registration/login and profile/account.

Do not automatically expand Phase 17.12 into a full customer request-history or enquiry-history implementation.

A future account screen may include these sections when approved.

For now, avoid dead buttons such as:

- My Orders.
- My Payments.
- My Cart.
- My Deliveries.
- My Enquiry History, unless implemented.
- My Furniture Requests, unless implemented.

If the roadmap explicitly assigns ownership-scoped request/enquiry history to Phase 17.12, verify that requirement first and implement only the approved API surface.

Do not expose private records through public identifiers.

---

## 20. Visual design

Follow the existing SL Furnitures design system.

### Registration and login

Use a calm, premium, editorial design:

- Brand identity.
- Clear heading.
- Short supporting copy.
- Simple form.
- Strong primary action.
- Restrained secondary links.
- Generous whitespace.

Suggested registration heading:

"Create your account"

Suggested login heading:

"Welcome back"

Suggested account heading:

"My Account"

### Account layout

Organize account information into clear sections rather than a dense dashboard.

Use existing:

- Generated Material 3 theme.
- Typography tokens.
- Spacing tokens.
- Input components.
- Buttons.
- Error presentations.
- Loading components.

Do not add a new design system.

Do not hard-code arbitrary colors, spacing, typography, or radii.

Do not use generic marketplace widgets, loyalty badges, fake statistics, or decorative profile charts.

---

## 21. Accessibility

Verify:

- Screen-reader labels.
- Email keyboard.
- Password keyboard and visibility toggle.
- Autofill support where appropriate.
- Keyboard-safe scrolling.
- Focus movement after validation.
- Verification-code accessibility.
- Accessible loading feedback.
- Disabled-state semantics.
- Touch targets.
- Error announcements.
- 2× text scaling.
- Small screens.
- Landscape layouts.
- Reduced-motion behavior.
- TalkBack traversal.

Authentication screens must remain usable without relying on visual cues alone.

---

## 22. Security requirements

### Secrets

Never commit:

- Clerk secret keys.
- Production tokens.
- Passwords.
- Verification codes.
- Recovery codes.
- Session cookies.
- Private user records.

Only use client-safe Clerk configuration in Flutter.

### Token handling

- Use the existing secure session mechanism.
- Retrieve current tokens through ClerkAuthAdapter.
- Never persist bearer tokens in ordinary preferences.
- Never include tokens in logs.
- Never place tokens in route parameters.
- Never put tokens in crash reports.
- Do not add custom token cryptography.

### Backend authority

- Laravel validates JWTs.
- Laravel enforces role and account state.
- Laravel validates profile updates.
- Flutter never grants CUSTOMER/STAFF/ADMIN permissions.
- Profile data must not leak between users.

### Authentication errors

Handle 401 and 403 separately.

An authenticated customer with a forbidden action should not automatically be logged out.

A temporary backend outage should not destroy a valid Clerk session.

### Privacy

On sign-out or account switching, ensure previously loaded private data is no longer visible.

---

## 23. Diagnostics

Reuse AppDiagnostics and existing allow-listed codes.

Do not introduce raw Clerk exceptions into diagnostic payloads.

Never log:

- Email.
- Password.
- Verification code.
- Recovery code.
- Bearer token.
- Clerk subject.
- Local user ID.
- Profile data.
- Raw request body.
- Full authentication error response.

Avoid duplicate network events already recorded by ApiClient.

Production diagnostics must follow the existing no-op policy.

---

## 24. Automated testing

Implement comprehensive tests.

### Registration

1. Valid email/password.
2. Invalid email.
3. Password-policy failure.
4. Existing account.
5. Verification-required response.
6. Duplicate-submit prevention.
7. Registration cancellation.
8. Network failure.
9. Rate limiting.
10. No local role injection.

### Verification

11. Valid code.
12. Invalid code.
13. Expired code.
14. Resend flow.
15. Resend throttling.
16. Session activation.
17. No premature authentication.
18. No code persistence.

### Login

19. Successful login.
20. Incorrect credentials.
21. Unverified account.
22. Provider-required additional step.
23. Rate limiting.
24. Network failure.
25. Session activation.
26. No password logging.

### Recovery

27. Recovery initiation.
28. Invalid email.
29. Verification challenge.
30. Expired challenge.
31. Valid password reset.
32. Invalid password reset.
33. No code leakage.

### Sessions

34. Cold-start anonymous state.
35. Cold-start authenticated state.
36. Session restoration.
37. Token renewal.
38. Expired session.
39. Invalid session.
40. Clerk initialization failure.
41. Laravel unavailable.
42. Laravel unauthorized.
43. Inactive customer.
44. Account switching.
45. Sign-out cleanup.

### Profile

46. USER-001 request.
47. Correct bearer header.
48. Profile decoding.
49. Optional phone handling.
50. Profile loading.
51. Profile refresh.
52. USER-002 update.
53. Allowed-field serialization.
54. Forbidden-field exclusion.
55. Validation errors.
56. Update success.
57. Update failure.
58. No stale private data.

### Navigation

59. Account route.
60. Sign-in route.
61. Sign-up route.
62. Verification route.
63. Recovery route.
64. Protected-route redirect.
65. Safe return-to behavior.
66. Anonymous catalog access.
67. Back navigation.
68. Sign-out navigation.
69. Deep-link safety.

### Integration regression

70. Authenticated REQ-001.
71. Anonymous REQ-001.
72. Authenticated ENQ-001.
73. Anonymous ENQ-001.
74. Broken session does not silently downgrade.
75. Product detail unaffected.
76. Catalog unaffected.
77. Existing multipart submission unaffected.
78. Deferred commerce remains disabled.

These are required coverage areas, not necessarily separate test functions.

Use deterministic fakes for Clerk and ApiClient where appropriate.

Do not require real credentials for the ordinary Flutter test suite.

---

## 25. Live integration testing

Where the development Clerk application and Laravel backend are available, verify the full chain:

Flutter → Clerk → verified token → Laravel → local CUSTOMER → profile.

### Registration flow

1. Register a disposable test account.
2. Complete required email verification.
3. Activate session.
4. Fetch Laravel profile.
5. Confirm CUSTOMER role.
6. Confirm no STAFF/ADMIN privileges.

### Login flow

7. Sign out.
8. Sign in.
9. Restore the session after app restart.
10. Verify token-based Laravel access.

### Profile flow

11. Read profile.
12. Update an allowed field.
13. Read again and confirm persistence.
14. Verify forbidden fields are rejected.

### Existing feature regression

15. Submit authenticated furniture request.
16. Submit authenticated enquiry.
17. Verify ownership on Laravel.
18. Sign out.
19. Submit anonymous enquiry.
20. Verify anonymous ownership remains null.

Do not use real customer credentials.

Do not use production Clerk keys or production databases.

If a live Clerk environment is unavailable, clearly report that real authentication integration remains unverified.

---

## 26. Android device and Marionette testing

Use the available emulator or physical Android device.

Verify:

- Registration screen.
- Email keyboard and autofill.
- Password visibility.
- Email verification.
- Sign-in.
- Forgot-password flow.
- Session restoration after restart.
- Account screen.
- Profile editing.
- Sign-out.
- Anonymous browsing.
- Protected-route redirects.
- Furniture request authenticated submission.
- Enquiry authenticated submission.
- Offline and rate-limit behavior.
- Small-screen scrolling.
- 2× text scaling.
- Landscape.
- TalkBack where available.

Do not temporarily change production routing and leave the change committed.

If a test-only initial route is needed, revert it and verify the final route configuration.

Record which checks were performed with real Clerk and which used fakes.

---

## 27. Documentation

Update:

- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- Phase 17.12 completion record.
- Relevant auth integration documentation.
- `docs/decisions.md` only for durable new architectural decisions.

Document:

- Clerk SDK/version.
- Auth flow architecture.
- Registration policy.
- Email verification.
- Password recovery.
- Session lifecycle.
- Laravel provisioning behavior.
- USER endpoint mappings.
- Route protection.
- Profile editing.
- Sign-out cleanup.
- Security boundaries.
- Live testing.
- Device testing.
- Known limitations.

Do not silently change the frozen API.

---

## 28. Verification

From `frontend/app`, run:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug --dart-define-from-file=config/local.json
git diff --check
git diff --cached --check
```

Also run relevant Laravel tests if backend integration or provisioning code is modified.

Report actual test counts.

Do not claim verification that was not performed.

---

## 29. Explicit exclusions

Do not implement:

- Cart.
- Checkout.
- Payments.
- Orders.
- Order tracking.
- Favorites.
- Loyalty.
- Saved delivery addresses.
- Customer reviews.
- Staff login in the customer app.
- Admin login in the customer app.
- Social login unless already explicitly approved.
- Phone-only registration.
- Guest-to-account cart merge.
- Global navigation drawer.
- Final shared app bar.
- New identity provider.
- Custom Laravel password storage.
- New user roles.
- Full customer enquiry/request history unless explicitly required by the phase roadmap.

The global navigation shell remains Phase 17.15.

---

## 30. Definition of Done

Phase 17.12 passes when:

- Registration is functional.
- Email verification is functional where required.
- Login is functional.
- Password recovery is functional.
- Clerk session restoration works.
- Secure sign-out works.
- Laravel recognizes authenticated customers.
- The account placeholder is replaced.
- Profile data loads from Laravel.
- Approved profile edits persist.
- Authentication errors are handled.
- Anonymous browsing remains available.
- Furniture Requests support real authenticated submissions.
- Enquiries support real authenticated submissions.
- No customer can grant themselves elevated privileges.
- Private account state is cleared on sign-out.
- Routing is correct.
- Accessibility requirements are satisfied.
- Automated tests pass.
- The debug APK builds.
- Design tokens remain synchronized.
- Documentation is complete.
- Deferred commerce functionality remains inactive.

If Flutter functionality passes but real Clerk/Laravel integration is unavailable, report the implementation as complete with **live authentication verification outstanding**, not as fully end-to-end verified.

---

## 31. Completion report

Return:

1. Repository inspection findings.
2. Clerk SDK compatibility findings.
3. Authentication architecture.
4. Registration implementation.
5. Email verification implementation.
6. Login implementation.
7. Password recovery implementation.
8. Session persistence.
9. Laravel provisioning.
10. Account screen.
11. Profile read/update.
12. Sign-out.
13. Routing changes.
14. REQ-001 authenticated regression.
15. ENQ-001 authenticated regression.
16. Security review.
17. Automated test counts.
18. Live Clerk/Laravel verification.
19. Marionette device verification.
20. Files changed.
21. Documentation updated.
22. Outstanding limitations.
23. Final PASS/FAIL.

**Final instruction:** Implement Phase 17.12 only. Preserve the existing API-first architecture, Clerk identity ownership, Laravel authorization authority, and request-only launch scope. Do not proceed to Phase 17.13 until Phase 17.12 has been verified and its completion record is written.