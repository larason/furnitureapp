# Phase 17.13 Completion Record

Phase 17.13 is implemented as the consolidated informational, legal, and
open-source licenses phase. Former Phase 17.14 has no separate implementation.

## Sources

The authoritative root legal files were found and read successfully:

- `privacy-policy.txt`
- `terms-of-service.txt`

Their existing wording, including template placeholders, is displayed without
rewriting or semantic transformation. About Us content is based on the verified
scope in `AGENTS.md`, the approved SL Furnitures design documentation, and the
implemented request-first catalog/enquiry flows. No unverified contact details,
founding claims, testimonials, delivery claims, or manufacturing claims were
added.

## Routes

- `/about`
- `/privacy-policy`
- `/terms-and-conditions`
- `/open-source-licenses`

All four routes are public and registered in `lib/navigation/app_router.dart`.
They do not require Clerk, a Laravel request, or network connectivity.

## Assets

The root documents are copied into:

- `frontend/app/assets/legal/privacy-policy.txt`
- `frontend/app/assets/legal/terms-of-service.txt`

The app assets are explicitly registered in `pubspec.yaml`. The deterministic
`tool/check_legal_assets.dart` command compares source and asset bytes. Update the
asset whenever a root legal source changes, run the check, and redistribute the
rebuilt APK for installed users to receive the update.

## Implementation

- About Us uses the shared informational scaffold and links to `/contact`.
- Legal documents use the shared scaffold, cached local asset futures, scrolling,
  large-text-compatible theme typography, and `SelectionArea`.
- Open Source Licenses uses Flutter's registered `LicensePage`; no manual license
  list was created.
- No Phase 17.15 app bar, drawer, or global shell was added.

## Verification

Focused information and route tests: **6 passed**.

Legal asset synchronization: **passed**.

Full Flutter test suite: **527 passed**.

Flutter analysis: **passed**.

Token freshness check: **passed**.

Debug APK build with `config/local.json`: **passed**.

Scoped working-tree and staged diff checks for this implementation: **passed**.
The repository-wide working-tree check still reports two pre-existing trailing
whitespace lines in the separately modified `phases/group-Q-phases.md`; those
lines were not changed by this phase.

Marionette verified the app can launch on the Android emulator, but direct device
verification of these routes is limited because Phase 17.15 global navigation is
explicitly not implemented. The public route registration and anonymous behavior
are covered by deterministic router tests; no temporary navigation shell was
added.

## Final Result

**PASS for Phase 17.13.** Former Phase 17.14 is merged. Phase 17.15 remains
untouched.
