# SL Furnitures — Customer App (Flutter)

Android customer application for the SL Furnitures platform. It consumes the same
Laravel API as the Next.js website and shares the project design system.

## Scope

- **Platform:** Android only. iOS, web, and desktop targets are intentionally not
  generated and must not be added without an explicit decision.
- **Application ID:** `com.slfurnitures.app`
- **Dart package:** `sl_furnitures`

## Phase status

This project contains the **Phase 16.1 (project setup)** and **Phase 16.2
(theme / Material 3)** foundations.

Not yet implemented (owned by later Group P phases):

- 16.3 environment configuration
- 16.4 networking layer
- 16.5 authentication storage/session
- 16.6 routing/navigation
- 16.7 feature/module structure
- 16.8 error/loading states
- 16.9 logging/diagnostics

No customer-facing screen exists yet. Debug builds show the development-only
theme preview harness; release builds show the neutral bootstrap placeholder.

## Design system boundary

The canonical design system lives in `frontend/design-system/`:

- `DESIGN.md` — brand and interaction contract
- `tokens.css` — **sole canonical token authority**
- `design-tokens.json` — portable, framework-neutral token representation
- `flutter-material3.md` — MUI-to-Flutter mapping contract

Flutter does **not** parse CSS or load token JSON at runtime. A deterministic
generator converts `tokens.css` into typed Dart once, and the theme consumes that
output. Do not hard-code colors, spacing, radii, typography, or elevation in this
app — if a value is missing, add the token to `tokens.css` and regenerate.

### Token generation

```bash
dart run tool/generate_tokens.dart           # regenerate lib/theme/tokens/generated_tokens.dart
dart run tool/generate_tokens.dart --check   # fail if generated tokens are stale
```

The generator resolves `var(...)` references, preserves semantic names, converts
colors/px/line-heights/durations/aspect ratios, and fails on unresolved
references, missing required tokens, or unsupported syntax. Output is
deterministic and `dart format`-stabilised, and carries a header naming its
source and regeneration command.

`test/theme/token_sync_test.dart` runs the same staleness check inside
`flutter test`, so a change to `tokens.css` without regeneration fails CI.

**`lib/theme/tokens/generated_tokens.dart` is the only file allowed to contain
raw `Color(0x...)` values.** This is enforced by
`test/theme/theme_definitions_test.dart`.

## Theme architecture

```text
lib/theme/
  tokens/generated_tokens.dart   # GENERATED — do not edit; regenerate instead
  app_color_scheme.dart          # explicit light ColorScheme (no ColorScheme.fromSeed)
  app_color_extensions.dart      # ThemeExtensions: editorial surface, brand accent, status
  app_typography.dart            # TextTheme mapped from the canonical type scale
  app_spacing.dart               # spacing, gutters, sections, breakpoints
  app_shapes.dart                # radii, borders, elevation decomposition
  app_motion.dart                # durations, easing, reduced-motion helper
  app_theme.dart                 # AppTheme.light() — the single ThemeData
  component_themes/              # AppBar, buttons, inputs, surfaces, navigation,
                                 # selection, feedback
  preview/theme_preview.dart     # development-only preview harness
```

Adapter files (`app_spacing.dart`, `app_shapes.dart`, `app_motion.dart`,
`app_typography.dart`) are **aliases over `GeneratedTokens`** — they define no
values of their own, so there is no second token palette.

Only a **light** theme exists. `tokens.css` defines no dark palette, so dark
mode is deferred; `--surface-inverse` is an inverse surface, not dark mode.

## Fonts

| Family | Status | Notes |
| --- | --- | --- |
| Young Serif | **Bundled** | `assets/fonts/YoungSerif-Regular.ttf` + `OFL-Young-Serif.txt`, the OFL asset the website already ships. Display typography only (96/48/32). |
| Helvetica Now Text | **Not bundled** | Commercial and distributed nowhere in this repository. Utility type falls back to the platform sans — the terminal generic of the canonical stack, not a substituted brand font. |

No Google Fonts or typography package is used. Do not download arbitrary font
files.

## Commands

```bash
flutter pub get          # resolve dependencies
dart format .            # format (CI requires --set-exit-if-changed clean)
flutter analyze          # static analysis (flutter_lints + strict analyzer modes)
flutter test             # unit, widget, and design-system compliance tests
dart run tool/generate_tokens.dart --check   # token freshness
flutter build apk --debug   # verify the Android build
flutter run              # run on a connected device or emulator
```

## Theme preview harness

`lib/theme/preview/theme_preview.dart` renders typography, semantic color
swatches, buttons with disabled/focused states, inputs, cards, chips,
selection controls, loading indicators, and navigation — as a verification
tool only. It is shown in debug builds and is not a production screen.

## Prerequisites

- Flutter 3.44.x (stable) with Dart 3.12.x
- Android SDK (compile/target SDK from the installed Flutter toolchain)
- JDK 17+ (bundled with Android Studio)

## Structure

```text
lib/
  main.dart     # entry point
  app.dart      # root widget — applies AppTheme.light()
  theme/        # Phase 16.2 theme layer (see above)
test/
  app_test.dart           # app-shell smoke test
  support/tokens_css.dart # canonical CSS fixture for generator tests
  theme/                  # sync, structure, accessibility, rendering tests
  tool/                   # generator behaviour tests
tool/                     # token generator + CLI
assets/fonts/             # bundled Young Serif + OFL licence
android/                  # Android host project
```

## Repository rules

Follow `frontend/AGENTS.md` and the root `AGENTS.md`. In particular: the Laravel
backend is the authority for business rules, and no app may connect to the
database directly.
