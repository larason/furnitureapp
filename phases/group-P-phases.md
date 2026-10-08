# Phase 16.6 — Flutter Routing & Navigation

**Project:** SL Furnitures — Android Customer Application  
**Group:** P — Flutter Application Foundation  
**Prerequisites:** Phases 16.1–16.5 completed  
**Status:** COMPLETED — 2026-10-08

## 1. Objective

Implement a robust, declarative, authentication-aware routing and navigation foundation for the Flutter Android application.

The navigation system must:

- Support public browsing without Clerk authentication.
- Integrate with the existing Clerk authentication state.
- Protect destinations requiring authentication.
- Preserve intended destinations across sign-in when appropriate.
- Support Android system back navigation.
- Support deep links and route parameters.
- Handle unknown and invalid routes safely.
- Establish a scalable route hierarchy for later feature phases.
- Use the approved Material 3 design system.
- Avoid implementing customer-facing screens prematurely.

**Phase 16.6 is navigation infrastructure, not UI feature development.**

The existing request-only commerce policy remains authoritative.

---

## 2. Mandatory repository inspection

Before implementation, inspect:

1. Root `AGENTS.md` and applicable nested instructions.
2. Group P Phase 16.6 requirements.
3. Phase 16.1–16.5 completion records.
4. Flutter README and `pubspec.yaml`.
5. Existing `lib/app.dart` and application bootstrap.
6. Phase 16.2 `AppTheme.light()`.
7. Phase 16.3 `AppConfig`.
8. Phase 16.4 `ApiClient`.
9. Phase 16.5 `ClerkAuthAdapter`, `AuthRepository`, and authentication state model.
10. ADR/AUTH-013 and ADR/AUTH-015.
11. Existing Next.js customer route structure.
12. Frozen API contracts and commerce restrictions.
13. Existing Flutter widget and integration tests.

Confirm the exact project structure and installed package versions.

Do not assume that previously suggested file names were adopted.

Identify the current authentication state semantics before designing route guards.

If existing decisions conflict with these instructions, follow the authoritative repository decisions and report the conflict.

---

## 3. Router selection

**Preferred router: `go_router`.**

Before installation, verify compatibility with the installed Flutter SDK and existing dependencies.

Use the current stable compatible release rather than copying an arbitrary version number.

### Why go_router

It provides:

- Declarative route configuration.
- Path parameters.
- Nested routes.
- Redirect handling.
- Deep-link support.
- Stateful navigation branches.
- Integration with Flutter's Router APIs.
- Android system-back compatibility.

Do not add another navigation framework unless repository constraints justify it.

Avoid implementing a custom routing engine.

Do not combine multiple competing navigation architectures.

Use a single authoritative router.

---

## 4. Recommended architecture

Adapt the structure to existing repository conventions.

```text
lib/
  navigation/
    app_router.dart
    app_routes.dart
    route_guard.dart
    navigation_state.dart
    navigation_scaffold.dart
    route_error_screen.dart
```

Only create files that have meaningful responsibilities.

### Responsibilities

**AppRouter**

Owns route registration and router configuration.

**AppRoutes**

Provides stable route names and typed path-building helpers.

**RouteGuard**

Evaluates route access based on the existing authentication state.

**NavigationState**

Owns only navigation-related transient state, such as an intended destination.

**NavigationScaffold**

Provides the future shared navigation container, without implementing the actual commerce screens.

**RouteErrorScreen**

Displays safe unknown-route and invalid-route feedback.

Avoid unnecessary abstractions.

Do not introduce a new dependency-injection or state-management framework solely for routing.

---

## 5. Route authority and route registry

Inspect the approved mobile information architecture and Group Q plan before defining routes.

The following route registry is a proposed starting point, not permission to invent new features.

| Destination | Suggested path | Access |
|---|---|---|
| Home | `/` | Public |
| Catalog | `/products` | Public |
| Product details | `/products/:productId` | Public |
| Categories | `/categories` | Public |
| Category products | `/categories/:categoryId` | Public |
| Search | `/search` | Public |
| Favorites | `/favorites` | Confirm approved persistence/ownership policy |
| Furniture request | `/furniture-requests` | Public, optionally authenticated |
| Contact/enquiry | `/contact` | Public, optionally authenticated |
| Account | `/account` | Authenticated |
| Sign in | `/sign-in` | Public |
| Sign up | `/sign-up` | Public |

Additional profile or account destinations may be registered if explicitly approved by the frozen project plan.

Do not create checkout, payment, or purchasing destinations.

### Route naming

Use stable route names.

Avoid scattering raw route strings across widgets.

Provide path builders for parameterized routes.

Do not create multiple routes for the same logical destination without a documented reason.

---

## 6. Public and protected routes

Navigation must distinguish:

- Public routes.
- Protected routes.
- Authentication flow routes.
- Unknown routes.

### Public routes

Public routes must work without a Clerk session.

Examples:

- Home.
- Catalog.
- Categories.
- Product details.
- Search.
- Anonymous furniture requests.
- Anonymous enquiries.

Do not redirect anonymous users to sign-in simply because the Clerk SDK is initialized.

### Protected routes

Protected routes require a completed usable Clerk session.

Examples include approved account-specific destinations.

The router must not equate an incomplete Clerk session with authenticated access.

### Important security boundary

Flutter route guards are a user-experience mechanism, not authorization enforcement.

Laravel remains authoritative for:

- Account activation.
- Roles.
- Permissions.
- Resource ownership.
- Account restrictions.
- Business authorization.

Never infer Laravel CUSTOMER authorization from Clerk email, metadata, or route state.

---

## 7. Authentication-aware redirects

Integrate route guards with the existing Phase 16.5 authentication state.

### State behavior

| Authentication state | Protected destination behavior |
|---|---|
| Initializing | Defer decision until restoration resolves |
| Signed out | Redirect to sign-in and preserve intended destination |
| Authenticated | Allow navigation, subject to backend authorization |
| Action required | Route to the approved Clerk completion flow when implemented |
| Temporarily unavailable | Avoid granting protected access; provide recoverable behavior |

### Critical requirements

1. Do not redirect to sign-in while session restoration is still unresolved.
2. Do not expose protected content during initialization.
3. Do not create infinite redirect loops.
4. Do not turn temporary network failure into confirmed sign-out.
5. Do not permit pending security-task sessions to access protected destinations.
6. Do not automatically sign out on Laravel HTTP 403.
7. Do not implement a second authentication-state store.

Use the existing observable Clerk session state.

### Auth completion route

If the sign-in or security-task UI is not implemented yet, use a minimal route placeholder or defer the route according to the approved phase plan.

Do not pretend a placeholder completes Clerk authentication.

Document this dependency clearly.

---

## 8. Intended destination restoration

When an anonymous user attempts to open a protected route:

1. Validate the destination.
2. Preserve the intended internal route.
3. Redirect to the approved sign-in destination.
4. Wait for completed authentication.
5. Revalidate the destination.
6. Navigate to the original destination where still permitted.

### Security requirements

- Accept only internal application routes.
- Reject absolute external URLs.
- Reject protocol-relative URLs.
- Reject malformed or recursive redirect targets.
- Reject authentication-flow routes as return destinations where they would create loops.
- Prevent open redirects.
- Avoid persisting sensitive route arguments.
- Do not place Clerk tokens in query parameters.

If the original destination is no longer valid, navigate to a safe public destination.

Do not invent a sign-in success event before Clerk confirms authentication.

---

## 9. Android navigation behavior

Implement Android-native navigation expectations.

### System back

- Back should return to the previous logical destination.
- Back should not repeatedly reopen an authentication redirect.
- Back from a product detail should return to its prior catalog context where that context exists.
- Back from a top-level destination should behave consistently with Android navigation conventions.
- Do not create duplicate route entries when selecting the active navigation destination.

### Predictive back

Use Flutter's supported back-navigation APIs.

Verify predictive-back behavior against the installed SDK and Android target configuration.

Do not implement deprecated back interception patterns without justification.

### Process restoration

Investigate the supported router restoration mechanisms.

Preserve appropriate navigation state where practical.

Do not restore authenticated screens before the Clerk session has been validated.

Do not persist bearer tokens or sensitive request data as route state.

---

## 10. Nested navigation and navigation shell

Evaluate `StatefulShellRoute.indexedStack` for future top-level destinations.

Use it only where independent navigation stacks are genuinely required.

Do not create unnecessary nested navigators.

### Navigation layout

The eventual mobile experience may use Material 3 bottom navigation.

However, the actual destination set must come from the approved mobile information architecture.

Do not copy the navigation labels, iconography, or styling from the mobile inspiration screenshot.

### Design-system rules

- Use existing `AppTheme.light()`.
- Use approved semantic tokens.
- Use the existing `NavigationBarThemeData`.
- Use approved Material iconography.
- Preserve safe-area behavior.
- Support accessible labels.
- Preserve focus and text scaling.
- Avoid arbitrary spacing, radii, colors, or elevation.

Do not create a second theme.

---

## 11. Feature boundaries

Phase 16.6 may create minimal route placeholders to prove navigation behavior.

Each placeholder should:

- Clearly identify the intended destination.
- Use the existing Material 3 theme.
- Avoid fabricated catalog content.
- Avoid fake products.
- Avoid mock account data presented as real.
- Avoid implementing repositories.
- Avoid unnecessary animations.
- Avoid becoming a production feature implementation.

The goal is to verify routing, not to design or build Group Q screens.

Where possible, use test-only route builders instead of shipping numerous placeholder pages.

---

## 12. Deep links

Implement or prepare the approved Android deep-link routing foundation.

Potential future destinations include:

```text
/products/{productId}
/categories/{categoryId}
/furniture-requests
```

### Requirements

- Parse path parameters safely.
- Handle invalid or missing parameters.
- Avoid crashes on unknown routes.
- Do not permit arbitrary external URL navigation.
- Prevent unauthorized protected-route exposure.
- Avoid putting credentials in deep links.
- Handle cold-start and warm-start navigation.
- Handle a deep link received while Clerk restoration is pending.

### Android App Links

Do not claim verified Android App Links support without:

- An approved production domain.
- Correct Android intent filters.
- Published Digital Asset Links verification.
- Successful on-device verification.

If the production domain is not deployed, leave verified App Links as a documented release dependency.

Do not invent a domain.

---

## 13. Navigation state and lifecycle

The router must respond correctly when:

- Clerk finishes session restoration.
- A user signs in.
- A user signs out.
- A session expires.
- A security task becomes required.
- A session becomes temporarily unavailable.
- A protected route is opened from a deep link.
- The application resumes from background.
- A navigation destination becomes invalid.

Avoid unnecessary router reconstruction on every authentication event.

Do not accumulate stale listeners.

Ensure subscriptions are disposed correctly.

### Sign-out

After sign-out:

- Protected routes must no longer be accessible.
- Public routes must remain accessible.
- Previously authenticated screens must not remain exposed through the back stack.
- Future authenticated feature state must be invalidated by its owning layer.

Do not create a second sign-out implementation inside navigation.

---

## 14. Error and unknown-route handling

Provide safe navigation-level error handling.

Distinguish:

- Unknown route.
- Invalid route parameter.
- Protected route requiring authentication.
- Authentication restoration pending.
- Temporarily unavailable authentication.
- Missing future feature implementation.

Do not turn a Laravel API 404 into a navigation 404 automatically.

A product API resource-not-found error belongs to its feature screen, not the router's route-matching layer.

Use concise, accessible messages.

Do not expose stack traces, route internals, or credentials.

---

## 15. Testing requirements

Add focused routing and widget tests.

### A. Route registry

Test:

1. Home route.
2. Catalog route.
3. Product detail parameter parsing.
4. Category parameter parsing.
5. Search route.
6. Furniture request route.
7. Contact route.
8. Unknown route.
9. Invalid route parameter.
10. Route name/path consistency.

### B. Public navigation

11. Public destinations work while signed out.
12. Public destinations work while authenticated.
13. Public destinations remain available during temporary Clerk unavailability.
14. Public destinations do not trigger token acquisition.
15. Navigation does not require Laravel connectivity.

### C. Protected navigation

16. Signed-out user redirects to sign-in.
17. Authenticated user may access protected route.
18. Initializing state does not prematurely redirect.
19. Pending security state does not grant access.
20. Temporarily unavailable state does not grant access.
21. Intended destination is preserved.
22. Unsafe redirect destination is rejected.
23. No redirect loop.
24. Auth completion restores the intended route.

### D. Session transitions

25. Signed out to authenticated.
26. Authenticated to signed out.
27. Authenticated to pending action.
28. Authenticated to temporarily unavailable.
29. Stale authentication event handling.
30. Router listener disposal.
31. Protected content removed after sign-out.

### E. Android navigation

32. Back from detail to catalog.
33. Back across top-level destinations.
34. Re-select active navigation destination.
35. Nested stack restoration, if implemented.
36. Deep link cold start.
37. Deep link warm start.
38. Unknown deep link.
39. Deep link to protected route.
40. Process restoration behavior.

### F. Accessibility and regression

41. Navigation labels accessible.
42. Keyboard focus behavior.
43. Large text scaling.
44. Safe-area handling.
45. Existing theme tests pass.
46. Existing configuration tests pass.
47. Existing networking tests pass.
48. Existing authentication tests pass.

Treat these as a coverage matrix, not a requirement to create exactly 48 tests.

Use fake authentication state sources.

Do not require live Clerk or Laravel services for automated router tests.

---

## 16. Physical Android verification

Use the existing physical Android device if available.

Verify:

1. App launches.
2. Public home route displays.
3. Navigation between public placeholders works.
4. Product detail route accepts a valid parameter.
5. Android system back works.
6. Unknown route displays safe feedback.
7. Protected route redirects correctly when signed out.
8. Auth restoration does not cause redirect flicker.
9. Existing theme remains consistent.
10. No Flutter exceptions or crashes.

Where supported, use ADB to test explicit Android deep-link intents.

Do not claim verified production App Links without domain association evidence.

Live authenticated navigation remains dependent on a working Clerk development configuration and sign-in workflow.

Document that limitation rather than simulating a successful real Clerk sign-in.

---

## 17. Scope exclusions

Do not implement:

- Actual catalog screens.
- Product detail UI.
- Search functionality.
- Favorites persistence.
- Furniture request forms.
- Contact forms.
- Customer profile screens.
- Clerk sign-in or registration forms.
- Email verification UI.
- Password recovery UI.
- API repositories.
- New networking clients.
- New session storage.
- Backend endpoints.
- Checkout, payments, or purchasing.
- Push notifications.
- Analytics.
- Production App Links domain configuration without approval.
- A second design system.

Do not alter the frozen API contract.

---

## 18. Documentation

Update the Flutter README and Group P Phase 16.6 completion record.

Document:

- Selected router package and version.
- Route registry.
- Public/protected route classification.
- Authentication redirect behavior.
- Intended destination restoration.
- Navigation shell architecture.
- Android system-back behavior.
- Deep-link support.
- Process restoration.
- Unknown-route handling.
- Test coverage.
- Native verification results.
- Deferred UI features.
- Production App Links dependencies.

Record any new durable architecture decision in `docs/decisions.md` using the existing ADR conventions.

Do not rewrite unrelated phase records.

---

## 19. Verification commands

After the final code and documentation edits, run:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test
dart run tool/generate_tokens.dart --check
flutter build apk --debug
git diff --check
```

Verify that:

- Existing 102 tests continue to pass.
- No theme-token drift exists.
- Phase 16.3 environment validation remains intact.
- Phase 16.4 ApiClient remains unchanged unless integration requires a narrowly justified edit.
- Phase 16.5 Clerk storage/session behavior remains intact.
- No new authentication protocol is introduced.
- No iOS/web/desktop targets are generated.
- No backend/API changes are introduced.
- No unapproved customer features are implemented.

---

## 20. Completion report

Provide:

1. Repository inspection findings.
2. Router selection and justification.
3. Files created and modified.
4. Route registry.
5. Authentication guard design.
6. Intended-destination restoration behavior.
7. Navigation shell structure.
8. Android back-navigation results.
9. Deep-link implementation status.
10. Unknown-route handling.
11. Automated test results.
12. Physical Android verification results.
13. Accessibility verification.
14. Documentation updates.
15. Remaining blockers and deferred work.
16. Final Phase 16.6 status.

### Definition of Done

Phase 16.6 passes when:

- One declarative router exists.
- Public routes work without authentication.
- Protected routes integrate with Clerk session state.
- Pending and unavailable sessions cannot access protected content.
- Intended destinations are handled safely.
- Android back navigation behaves correctly.
- Deep links are parsed safely.
- Unknown routes do not crash.
- The approved Material 3 theme is preserved.
- Router tests pass.
- Existing 102 tests remain passing.
- Debug APK builds successfully.
- Native verification is completed or accurately recorded as blocked.
- No later-phase customer features have been implemented.

**Final instruction:** Inspect the existing repository and approved mobile information architecture first. Implement the smallest maintainable declarative routing foundation, preserve Clerk and Laravel security boundaries, verify Android navigation behavior, and stop at Phase 16.6.

---

## Completion record — 2026-10-08

- Added `go_router ^18.0.2` as the single declarative router and integrated
  `MaterialApp.router` without changing the existing Material 3 theme,
  configuration validation, API client, or Clerk session implementation.
- Registered public home, catalog, product/category detail, search,
  furniture-request, contact, and Clerk entry placeholders; `/account` is the
  single protected placeholder. Cart, checkout, payment, orders, favorites,
  bottom-navigation branches, and all feature UI remain deferred.
- Route guards use the existing `ClerkAuthAdapter` observable state. Protected
  access is blocked while the session initializes, needs action, or is
  temporarily unavailable; signed-out access redirects to sign-in. Only a
  query-free internal `/account` continuation is restored after authentication.
- Route-level deep links use `go_router` paths, validate opaque product/category
  parameters, and show safe unknown or invalid-link feedback. Android system
  back from a pushed product detail returns to catalog in automated tests.
- Verification passed: `flutter analyze`, `flutter test --concurrency=1` (116
  tests), token generation freshness, and `flutter build apk --debug`.
- Native device/App Link verification remains blocked: no connected device and
  no approved production domain, intent filter, or Digital Asset Links file.
  Live authenticated navigation remains blocked on the Clerk development
  sign-in workflow; controlled authentication-state router tests cover the
  routing behavior without simulating a Clerk sign-in.
