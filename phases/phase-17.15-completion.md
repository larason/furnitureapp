# Phase 17.15 Completion Record

## Scope

Phase 17.15 implements the request-only Flutter customer application's global
Material 3 navigation. It does not add cart, checkout, payments, orders,
tracking, or another authentication system.

## Navigation Architecture

- `AppRouter` uses one `ShellRoute` for customer routes.
- `CustomerNavigationShell` owns the global app bar, drawer, guest account sheet,
  and Clerk-state-aware account entry.
- Authentication screens and Flutter's public `LicensePage` remain outside the
  shell.
- The centered official logo is `assets/applogo/brandlogo.svg` through
  `AppBrandLogo`.
- `DrawerCategoryController` reads the existing `CategoryRepository`, caches
  successful results, supports pagination and retry, and does not block static
  destinations if category loading fails.

## Authentication Behavior

- Guests receive a modal account sheet with existing sign-in and registration
  routes.
- Signed-in customers navigate directly to `/account`.
- The existing `RouteGuard` redirects direct authentication success to Home and
  preserves an allow-listed protected-route `continue` destination.
- No Clerk tokens, roles, or Laravel authorization behavior changed.

## Verification

- Focused navigation and drawer tests cover shell actions, guest/authenticated
  routing, category caching/pagination/retry, protected returns, and direct
  authentication completion.
- `dart format --set-exit-if-changed lib test`, `flutter analyze`, and
  `flutter test --concurrency=1` pass (536 tests).
- `dart run tool/check_legal_assets.dart` confirms the bundled legal assets match
  their authoritative root sources byte-for-byte.
