# SL Furnitures — Customer App (Flutter)

Android customer application for the SL Furnitures platform. It consumes the same
Laravel API as the Next.js website and shares the project design system.

## Scope

- **Platform:** Android only. iOS, web, and desktop targets are intentionally not
  generated and must not be added without an explicit decision.
- **Application ID:** `com.slfurnitures.app`
- **Dart package:** `sl_furnitures`

## Phase status

This project currently contains the **Phase 16.1 (project setup)** foundation only.

Not yet implemented (owned by later Group P phases):

- 16.2 theme / Material 3 token mapping — `frontend/design-system/flutter-material3.md`
- 16.3 environment configuration
- 16.4 networking layer
- 16.5 authentication storage/session
- 16.6 routing/navigation
- 16.7 feature/module structure
- 16.8 error/loading states
- 16.9 logging/diagnostics

The app renders a neutral bootstrap placeholder until those phases land. No brand
colors, typography, or component styles are defined here.

## Design system boundary

The canonical design system lives in `frontend/design-system/`:

- `DESIGN.md` — brand and interaction contract
- `tokens.css` — sole canonical token authority
- `design-tokens.json` — portable, framework-neutral token representation
- `flutter-material3.md` — MUI-to-Flutter mapping contract

Flutter does **not** parse CSS or load token JSON at runtime. Phase 16.2 maps the
tokens into `ThemeData` through a static Dart adapter. Do not hard-code colors,
spacing, radii, typography, or elevation in this app.

## Prerequisites

- Flutter 3.44.x (stable) with Dart 3.12.x
- Android SDK (compile/target SDK from the installed Flutter toolchain)
- JDK 17+ (bundled with Android Studio)

## Commands

```bash
flutter pub get          # resolve dependencies
flutter analyze          # static analysis (flutter_lints + strict analyzer modes)
flutter test             # widget/unit tests
flutter build apk --debug   # verify the Android build
flutter run              # run on a connected device or emulator
```

## Structure

```text
lib/
  main.dart     # entry point
  app.dart      # root widget
test/
  app_test.dart # app-shell smoke test
android/        # Android host project
```

## Repository rules

Follow `frontend/AGENTS.md` and the root `AGENTS.md`. In particular: the Laravel
backend is the authority for business rules, and no app may connect to the database
directly.
