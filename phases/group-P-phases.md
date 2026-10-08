# Phase 16.2 — Flutter Theme / Material 3
## Group P — Flutter Application Foundation

### Objective

Implement the Flutter Material 3 theme using the existing authoritative design tokens defined in `tokens.css`.

The Flutter application must share the same brand identity as the existing Next.js + MUI website while preserving native Flutter interaction patterns.

This phase covers:

- Design-token integration.
- Material 3 theme configuration.
- Typography and font integration.
- Semantic color mapping.
- Spacing, shape, and elevation foundations.
- Reusable theme extensions.
- Theme-level component styling.
- Automated design-system compliance tests.

**Do not implement customer-facing screens during this phase.**

The previously provided furniture mobile screenshot remains a layout inspiration reference only. It is not a source of colors, typography, spacing, or component styling.

---

## 1. Mandatory Repository Inspection

Before implementation, inspect:

1. Root `AGENTS.md`.
2. Current Group P documentation.
3. Phase 16.1 Flutter project structure and completion report.
4. Authoritative `tokens.css`.
5. Existing Next.js MUI theme and token mappings.
6. Existing Flutter theme files, if any.
7. Flutter SDK version and `pubspec.yaml`.
8. Existing fonts and licensed font assets.
9. Existing design-system tests and generation scripts.
10. Existing repository tooling and CI conventions.

Identify the exact canonical location of `tokens.css` in the repository.

Do not assume the uploaded file path is the repository's canonical path.

Document the findings before making architectural changes.

## 2. Design-Token Authority

The existing `tokens.css` is authoritative.

Do not replace, redesign, or independently reinterpret its visual identity.

The file contains:

- Primitive colors.
- Semantic color roles.
- Compatibility aliases.
- Typography families, weights, sizes, and line heights.
- Spacing and layout measurements.
- Responsive breakpoints.
- Media aspect ratios.
- Border radii.
- Elevation.
- Focus indicators.
- Motion durations and easing.
- Layering values.

### Mandatory authority order

1. Frozen business/API contracts for functional behavior.
2. Canonical `tokens.css` for visual values.
3. Existing MUI theme for approved component semantics.
4. Flutter Material 3 for platform-specific component behavior.
5. Mobile layout screenshot for structural inspiration only.

If Material 3 defaults conflict with approved brand tokens, explicitly map the relevant component styling instead of silently adopting unrelated defaults.

Do not modify canonical tokens to accommodate Flutter.

## 3. Shared Token Integration

Create a maintainable Flutter token-consumption mechanism.

### Preferred approach

Use a deterministic generation or transformation process that reads the existing canonical CSS tokens and produces typed Dart token definitions.

The Flutter output must be derived from the same source consumed by Next.js.

Avoid manually maintaining a separate list of token values.

A possible structure is:

```text
flutter_app/
  lib/
    core/
      design_system/
        tokens/
          generated_tokens.dart
        theme/
          app_theme.dart
          app_color_extensions.dart
          app_spacing.dart
          app_typography.dart
          app_shapes.dart
          app_motion.dart
```

This structure is illustrative. Adapt it to the existing Phase 16.1 project structure.

### Generation requirements

The token generator must:

1. Read the canonical CSS file.
2. Resolve `var(...)` references.
3. Preserve semantic token names.
4. Convert CSS color values into Flutter-compatible values.
5. Convert pixel-based design measurements into appropriate Flutter logical dimensions.
6. Preserve unitless line-height multipliers.
7. Convert durations into `Duration` values.
8. Preserve aspect ratios as numeric ratios.
9. Fail on unresolved references.
10. Fail on missing required tokens.
11. Produce deterministic output.
12. Avoid duplicating canonical values in manually maintained Dart files.

Do not build a large general-purpose CSS parser unless necessary.

Use the simplest reliable parser or existing project tooling capable of handling the actual token syntax.

Preserve the generated-file header identifying its source and regeneration command.

### Synchronization

Add a verification mechanism that detects when generated Flutter tokens are stale relative to `tokens.css`.

A canonical token change must not silently leave Flutter using outdated values.

Do not introduce a second authoritative token file.

---

## 4. Semantic Color Mapping

Map the canonical semantic roles into Flutter Material 3.

The supplied token file defines:

- `surface-canvas`
- `surface-paper`
- `surface-editorial`
- `surface-inverse`
- `text-primary`
- `text-secondary`
- `text-muted`
- `text-inverse`
- `border-subtle`
- `border-default`
- `border-strong`
- `action-primary`
- `action-primary-hover`
- `action-primary-active`
- `action-primary-disabled`
- `action-secondary`
- `action-focus`
- `accent-brand`
- `accent-material`

It also defines semantic success, warning, danger, and information colors.

### Material 3 mapping

Create a coherent `ColorScheme` using the approved semantic roles.

Use `ThemeExtension` for semantic values not represented directly by `ColorScheme`.

Ensure:

- Canvas backgrounds use the approved canvas role.
- Paper surfaces use the approved paper role.
- Primary actions use the approved action role.
- Brand accents remain distinct from primary action colors.
- Text uses the approved semantic hierarchy.
- Borders use the approved border roles.
- Error styling uses the approved danger role.
- Focus indicators use the approved focus role.

Do not substitute Material 3's generated tonal palettes for canonical brand values.

Do not use `ColorScheme.fromSeed()` as the final source of visual authority.

Where Flutter requires additional Material 3 color roles that are not explicitly defined in `tokens.css`, derive them from approved semantics where unambiguous.

If a required role cannot be mapped safely, document the gap rather than inventing a new brand color.

### Theme mode

Implement the approved light theme.

Do not invent a dark palette.

If the project has no approved dark-mode tokens, record dark-mode support as deferred.

Do not automatically generate dark colors from Material 3 defaults.

---

## 5. Typography Integration

The canonical typography definitions include:

**Display family:** Young Serif, with fallback fonts.

**UI family:** Helvetica Now Text Medium / Helvetica Now Text, with system fallbacks.

**Text sizes:**

- XS: 12
- SM: 14
- Base: 16
- LG: 20
- XL: 24
- 2XL: 32
- 3XL: 48
- 4XL: 96

**Weights:** Regular 400 and Medium 500.

**Line heights:**

- Body: 1.75
- Snug: 1.2
- Display: 1.05

### Implementation requirements

1. Inspect the existing website font assets and loading strategy.
2. Determine which font assets are licensed and available for mobile redistribution.
3. Register approved fonts through Flutter's asset configuration.
4. Map canonical text sizes and line-height multipliers to `TextTheme`.
5. Create reusable semantic typography styles where necessary.
6. Preserve text scaling and accessibility.
7. Avoid hardcoded font sizes in components.
8. Avoid introducing Google Fonts or unrelated typography packages without approval.

If Young Serif or Helvetica Now Text assets are unavailable or cannot legally be bundled, use a documented platform-safe fallback temporarily.

Do not silently substitute a different brand font.

Do not download arbitrary font files.

Do not treat CSS font-family fallback strings as guaranteed font assets available on Android or iOS.

### Display typography

The 96-unit display token must not automatically be used for mobile headings.

Use an appropriate existing semantic typography role based on content hierarchy and available screen space.

Responsive typography must not introduce undocumented brand-scale values.

---

## 6. Spacing and Layout Tokens

The canonical spacing scale is:

| Token | Value |
|---|---|
| space-1 | 4 |
| space-2 | 8 |
| space-3 | 12 |
| space-4 | 16 |
| space-5 | 20 |
| space-6 | 24 |
| space-7 | 32 |
| space-8 | 48 |
| space-9 | 64 |
| space-10 | 80 |

Implement typed access to these values.

Use the existing responsive gutter and section-spacing tokens where appropriate.

### Important

Do not invent a second spacing scale for Flutter.

Do not scatter arbitrary `EdgeInsets` or `SizedBox` values throughout the application.

Use approved spacing tokens for brand-driven layout decisions.

Runtime safe-area padding, keyboard insets, viewport calculations, and accessibility-related minimum dimensions are permitted as platform-specific requirements.

### Responsive behavior

The CSS source defines phone, tablet, and desktop breakpoints.

Expose those values through the Flutter token layer, but do not assume web breakpoints always translate directly into native layout decisions.

Use available viewport constraints and Flutter layout conventions.

---

## 7. Shape, Elevation, and Motion

Use the canonical values:

**Radii**

- Small: 8
- Medium: 20
- Large: 24
- Pill: 30

**Elevation**

- Flat: none
- Ring: token-defined border ring
- Raised: token-defined ring

**Motion**

- Fast: 150 ms
- Base: 200 ms

Use the approved standard easing curve.

### Requirements

Implement reusable shape and motion accessors.

Prefer subtle, token-compliant surfaces rather than introducing default Material elevation shadows everywhere.

The CSS ring and focus-ring definitions must be mapped deliberately to Flutter borders or focus decorations; do not assume CSS box shadows translate directly into native Flutter elevation.

Support reduced-motion accessibility behavior where applicable.

Do not introduce arbitrary animation timings.

---

## 8. Material 3 Component Themes

Configure reusable theme-level styling for foundational Flutter components.

Include components where relevant to the existing app foundation:

- AppBar
- FilledButton
- OutlinedButton
- TextButton
- IconButton
- TextField / InputDecoration
- Card
- NavigationBar
- NavigationRail, if needed
- Dialog
- BottomSheet
- Checkbox
- Radio
- Switch
- SnackBar
- Divider
- ProgressIndicator

### Component rules

1. Use the canonical semantic colors.
2. Use approved typography.
3. Use approved shape tokens.
4. Use existing spacing tokens.
5. Preserve disabled, focused, pressed, and error states.
6. Maintain accessible touch targets.
7. Avoid unnecessary Material elevation.
8. Do not introduce unrelated component variants.

Configure theme-level defaults rather than repeatedly styling individual widgets.

Do not create a large custom widget library during this phase.

Reusable commerce widgets belong to subsequent phases.

---

## 9. Accessibility Requirements

The theme must support:

- System text scaling.
- Readable contrast.
- Visible keyboard focus.
- Accessible disabled states.
- Appropriate interactive target sizes.
- Screen-reader-compatible components.
- High-contrast considerations.
- Reduced-motion preferences where supported.

Check contrast using actual semantic foreground/background pairs.

Do not assume that a token is accessible in every possible combination merely because it exists in the canonical file.

If an approved token pairing fails accessibility checks, report the specific pairing and proposed resolution.

Do not silently change the canonical palette.

---

## 10. Theme Verification

Create automated tests for:

1. Canonical token loading.
2. CSS variable resolution.
3. Correct color conversion.
4. Spacing values.
5. Typography mappings.
6. Shape values.
7. Motion values.
8. Material 3 enablement.
9. Semantic ColorScheme mappings.
10. ThemeExtension availability.
11. Deterministic token generation.
12. Detection of stale generated tokens.
13. Missing-token and invalid-reference failures.
14. No unapproved colors in theme definitions.
15. Basic widget rendering under the configured theme.

Where appropriate, add golden tests for representative Material 3 components.

Avoid screenshot-dependent tests that merely reproduce the reference image.

### Quality checks

Run:

```bash
flutter pub get
dart format .
flutter analyze
flutter test
```

Also run the token-generation verification command established by the implementation.

Use the actual repository's tooling and supported Flutter SDK.

Do not claim tests passed unless they were executed successfully.

---

## 11. Theme Preview and Visual Verification

Provide a small development-only theme preview or test harness using existing project conventions.

It may display:

- Typography hierarchy.
- Semantic colors.
- Buttons and states.
- Text inputs.
- Cards.
- Navigation components.
- Focus states.
- Loading indicators.

This is a verification tool, not a production customer screen.

Do not begin implementing the catalog, product details, favorites, or other Group Q features.

If a Flutter emulator or physical device is available, verify rendering on a native target.

If no device is available, report native visual verification as pending.

Chrome DevTools MCP may help inspect the Next.js website but cannot substitute for native Flutter verification.

---

## 12. Scope Restrictions

Do not implement:

- Phase 16.3 environment configuration.
- Phase 16.4 networking.
- Phase 16.5 authentication/session handling.
- Phase 16.6 navigation architecture.
- Group Q customer features.
- Cart, checkout, payment, or order flows.
- Furniture request or enquiry forms.
- New backend endpoints.
- API contract changes.
- A new website design system.

Do not refactor the Next.js MUI theme unless required for a separately approved shared-token integration change.

Preserve all existing website behavior.

### Production commerce restriction

The project's initial production mode is REQUEST ONLY.

Normal cart, checkout, payment, and order purchasing remain deferred.

Do not enable these flows in the Flutter theme preview or introduce purchase-oriented behavior.

---

## 13. Documentation

Update the Group P phase documentation with:

- Canonical token source location.
- Flutter token generation strategy.
- Generated Dart output location.
- MUI-to-Flutter semantic mapping.
- Font integration decisions.
- Light/dark theme status.
- Accessibility findings.
- Verification commands.
- Test results.
- Deferred decisions.

Document any missing assets or required token additions.

Do not modify unrelated Group O completion or production-gate records.

---

## 14. Completion Report

Provide:

1. Repository inspection findings.
2. Files created or modified.
3. Token generation strategy.
4. Material 3 theme architecture.
5. Semantic color mappings.
6. Typography/font decisions.
7. Spacing, shape, elevation, and motion mappings.
8. Component themes configured.
9. Accessibility verification results.
10. Automated test results.
11. Native visual verification status.
12. Remaining risks or missing approvals.
13. Final Phase 16.2 status.

### Definition of Done

Phase 16.2 passes when:

- Flutter Material 3 is enabled.
- The Flutter theme is derived from canonical `tokens.css`.
- There is no independently maintained competing token palette.
- Typography, colors, spacing, shapes, and motion are mapped consistently.
- Theme-level Material components use approved semantic roles.
- Token synchronization is verified.
- Automated tests and static analysis pass.
- Missing font or accessibility decisions are explicitly documented.
- No unrelated application features are introduced.

**Implementation directive:** Inspect first, implement the smallest maintainable theme architecture, enforce canonical tokens, verify generation and rendering, document results, and stop at Phase 16.2.

---

## Phase 16.2 Closure Record

### 1. Repository inspection findings

- **Canonical token source confirmed:** `frontend/design-system/tokens.css`. The uploaded/illustrative path was not assumed. Synchronized derived artifacts remain `design-tokens.json` and `tailwind-v4.css`; neither became a Flutter source.
- **Existing MUI adapter:** `frontend/web/theme/theme.ts` (enforced by `theme.contract.mjs`, which rejects raw hex in theme definitions). Used as the approved-semantics reference only; it was not refactored.
- **Mapping contract:** `frontend/design-system/flutter-material3.md` supplied the role-by-role intent checked during implementation.
- **Phase 16.1 baseline:** `frontend/app/` — Android-only, package `sl_furnitures`, applicationId `com.slfurnitures.app`. No pre-existing Flutter theme, tokens, or fonts existed.
- **Toolchain:** Flutter 3.44.6 / Dart 3.12.2. `useMaterial3` defaults to true in this SDK; every `*ThemeData` type and every optional `ColorScheme` role was verified against the installed SDK before use rather than assumed.
- **Fonts:** Young Serif TTF plus its OFL licence are already shipped by the website at `frontend/web/public/fonts/`. Helvetica Now Text is commercial and appears nowhere in the repository.
- **Tooling:** `dart format`, `flutter analyze` (strict-casts/strict-inference/strict-raw-types), `flutter test`. No Flutter token-generation script existed before this phase.

### 2. Files created or modified

**Created — generator**

- `tool/token_generator.dart` — pure generation library: `generateTokensDart(css, {sourceLabel})`, `TokenGenerationException`, `requiredTokenNames`, `dartIdentifier()`.
- `tool/generate_tokens.dart` — CLI with write mode and `--check` staleness mode; applies `dart format` in both modes so write and check compare identical output.

**Created — generated tokens**

- `lib/theme/tokens/generated_tokens.dart` — `abstract final class GeneratedTokens`. The only file permitted to contain raw `Color(0x...)` literals.

**Created — theme adapters (aliases over `GeneratedTokens`, no locally defined values)**

- `lib/theme/app_color_scheme.dart`, `app_color_extensions.dart`, `app_typography.dart`, `app_spacing.dart`, `app_shapes.dart`, `app_motion.dart`, `app_theme.dart`
- `lib/theme/component_themes/` — `app_button_themes.dart`, `app_input_themes.dart`, `app_surface_themes.dart`, `app_navigation_themes.dart`, `app_selection_themes.dart`, `app_feedback_themes.dart`
- `lib/theme/preview/theme_preview.dart` — development-only preview harness.

**Modified**

- `lib/app.dart` — applies `AppTheme.light()`; shows the preview screen in debug and the existing bootstrap placeholder otherwise.
- `pubspec.yaml` — registers the `Young Serif` family.
- `assets/fonts/YoungSerif-Regular.ttf` + `assets/fonts/OFL-Young-Serif.txt` — bundled OFL display font and its licence.
- `test/app_test.dart` — app-shell smoke test now asserts the token-driven brand theme.

**Deleted (obsolete after the redesign)**

- `lib/theme/app_tokens.dart`, `lib/theme/app_theme_extensions.dart`, `test/theme/token_contract_test.dart`.

**Unchanged:** `frontend/design-system/tokens.css`, the Next.js MUI theme, all Group O records, and every backend/API artifact.

### 3. Flutter token generation strategy

A deterministic generator reads the canonical CSS and emits typed Dart; it is not a general-purpose CSS parser.

1. Reads `frontend/design-system/tokens.css` (path constant `sourceRelativePath`; label constant `sourceLabel`).
2. Parses `:root` custom properties and resolves every `var(...)` reference, with circular-reference detection.
3. Preserves semantic token names through `dartIdentifier()` (`--surface-canvas` → `surfaceCanvas`, `--space-1` → `space1`).
4. Converts `#RRGGBB`/`#RRGGBBAA` → `Color(0x…)`; `px` lengths → `double` (CSS unitless `0` allowed); unitless line-height multipliers preserved; durations → `Duration`; aspect ratios → numeric ratio; `z-*` → `int`; font stacks → `List<String>`.
5. **Fails** on unresolved `var()` references, on a missing `:root`, on any missing entry in `requiredTokenNames`, and on unsupported value syntax — each with a `TokenGenerationException` message.
6. Output is deterministic: the same CSS always yields byte-identical, `dart format`-stabilised output.
7. The generated header records `Source: frontend/design-system/tokens.css`, the regenerate command, and the verify command.

**Synchronization:** `dart run tool/generate_tokens.dart --check` regenerates and compares, failing when `tokens.css` changed but the Dart file did not. The same check runs inside the test suite via `test/theme/token_sync_test.dart`, so staleness fails `flutter test` as well. No second authoritative token file exists; `design-tokens.json`/`tailwind-v4.css` remain web-side representations and are not consumed by Flutter.

### 4. Material 3 theme architecture

`AppTheme.light()` returns one `ThemeData` with `useMaterial3: true`, an explicit `ColorScheme`, `AppTypography.textTheme`, three `ThemeExtension`s, and theme-level component themes. There is no `ColorScheme.fromSeed`, no dynamic/system color, and no `surfaceTint` colouring — `surfaceTint` is transparent so elevation never tints a brand surface.

### 5. MUI-to-Flutter semantic mapping

| Semantic role | Canonical token | MUI | Flutter |
| --- | --- | --- | --- |
| App canvas | `--surface-canvas` | `background.default` | `ColorScheme.surface` + `scaffoldBackgroundColor` |
| Paper surface | `--surface-paper` | `background.paper` | `surfaceContainer*` / `surfaceBright` |
| Editorial surface | `--surface-editorial` | `surface.editorial` | `ThemeExtension AppSurfaceColors.editorial` |
| Inverse surface | `--surface-inverse` | `surface.inverse` | `inverseSurface` / `onInverseSurface` (not dark mode) |
| Primary action | `--action-primary` | `primary.main` | `ColorScheme.primary` (charcoal `#111111`) |
| Action foreground | `--text-inverse` | `primary.contrastText` | `onPrimary` |
| Action container | `--action-primary-disabled` | `action.disabledBackground` | `primaryContainer` |
| Secondary | `--text-secondary` | `text.secondary` | `secondary`, `onSurfaceVariant` |
| Text hierarchy | `--text-primary` / `--text-secondary` / `--text-muted` / `--text-inverse` | `text.*` | `onSurface`, `onSurfaceVariant`, `onInverseSurface` |
| Borders | `--border-subtle` / `--border-default` / `--border-strong` | `divider` | `outlineVariant`, `outline`, `dividerTheme` |
| Error | `--danger` | `error.main` | `error` / `onError` |
| Focus | `--action-focus` | `action.focus` | focus border + `--focus-ring` decoration (`#275DC5`) |
| Brand accent | `--accent-brand` | `brand.accent` | `ThemeExtension AppBrandColors` — never `secondary` |
| Status | `--success` / `--warn` / `--info` | status palette | `ThemeExtension AppStatusColors` — never primary/secondary/tertiary |

Status colours are deliberately **not** placed in `ColorScheme.secondary`/`tertiary`; the brown accent is deliberately **not** placed in `secondary`; editorial is not forced into a container role.

### 6. Typography and font decisions

- **Young Serif: bundled.** The OFL TTF the website already ships is copied to `assets/fonts/` with `OFL-Young-Serif.txt`, registered as family `Young Serif`. Safe to redistribute; no arbitrary font was downloaded.
- **Helvetica Now Text: not bundled.** It is commercial and distributed nowhere in this repository, so utility type resolves to the platform sans (the terminal generic `sans-serif` of the canonical stack). This is the documented fallback required by §5, **not** a substituted brand font, and no Google Fonts or typography package was added.
- Young Serif covers `displayLarge` (96), `displayMedium` (48), `displaySmall` (32) and `headlineLarge` (32) only. Every other role uses the utility family at the canonical 12/14/16/20/24/32 sizes with weights 400/500 and line heights 1.75/1.2/1.05 — no Material default size (36/45/57) survives.
- The 96 unit is exposed as `displayLarge` but is **not** applied to any screen; mobile headings must use an existing semantic role sized to the viewport. No undocumented responsive scale was introduced.
- The mapping contract's optional Young Serif usage on `headlineMedium`/`titleLarge` is intentionally not exercised, since no editorial screen exists yet; component phases may request it.

### 7. Spacing, shape, elevation, and motion

- **Spacing:** `AppSpacing` exposes `space-1..10` (4…80), the phone/tablet/desktop gutters and section spacing, and container/form/lead widths — all aliases of `GeneratedTokens`. Web breakpoints are exposed for reference only; native layouts must react to real viewport constraints. Safe-area, keyboard-inset and minimum-target values remain permitted platform concerns.
- **Shape:** `AppRadii` = 8 / 20 / 24 / 30 from `radiusSm/Md/Lg/Pill`. Media stays sharp by default.
- **Elevation:** flat surfaces first. `AppElevation` decomposes `--elevation-ring` (0 0 0 1 `#CACACB`) and `--elevation-raised` (0 8 24 `rgba(17,17,17,0.12)`) into `BoxDecoration` borders/shadows. CSS box shadows were **not** translated into `Material` elevation: `Card` is flat with a hairline ring, while dialogs and bottom sheets carry the raised ring. `--focus-ring` (0 0 0 3 `#275DC5`) maps to the focused input border decoration, not to a native shadow.
- **Motion:** `AppMotion.fast` = 150 ms, `AppMotion.base` = 200 ms, standard easing `cubic-bezier(0.2, 0, 0, 1)` exposed as control points, plus a `prefersReducedMotion`/`resolve` helper. No arbitrary timings.

### 8. Component themes configured

AppBar, FilledButton, ElevatedButton, OutlinedButton, TextButton, IconButton, FloatingActionButton, SegmentedButton, TextField/InputDecoration, SearchBar, Card, Dialog, BottomSheet, Divider, NavigationBar, TabBar, ListTile, Chip, Checkbox, Radio, Switch, ProgressIndicator, SnackBar, Tooltip. All are theme-level defaults wired in `AppTheme.light()`; pill actions, 48 dp minimum targets, charcoal pressed/disabled states, token focus borders, and inverse-surface snackbars/tooltips.

No custom widget library, navigation architecture, environment config, networking, auth, or Group Q screen was introduced.

### 9. Accessibility findings

Automated checks in `test/theme/theme_accessibility_test.dart` (they supplement — they do not replace — manual and assistive-technology review):

- **11 text pairs pass ≥ 4.5:1:** primary text on canvas/paper/editorial; secondary text on canvas/paper; inverse text on inverse surface, primary action, success, warning, danger, and information.
- **3 focus-indicator pairs pass ≥ 3:1:** focus ring on canvas, paper, and inverse.
- **Touch targets:** primary action and icon-only action both render ≥ 48 dp under the theme.
- **Text scaling:** layout reflows at 2× linear system text scaling.
- Reduced-motion preference is representable through `AppMotion.resolve`.

**No approved token pairing failed.** Gaps that could not be satisfied from canonical tokens are recorded in §12 rather than silently re-coloured.

### 10. Verification commands and results

Run from `frontend/app/`:

| Command | Result |
| --- | --- |
| `dart format --output=none --set-exit-if-changed .` | Passed — 27 files, 0 changed |
| `flutter analyze` | Passed — `No issues found!` |
| `flutter test` | Passed — `All tests passed!` (48 tests) |
| `dart run tool/generate_tokens.dart --check` | Passed — `Token generation: OK (generated tokens are current).` |
| `flutter build apk --debug` | Passed — `app-debug.apk` built |

48 tests across 7 files, by requirement: generator parsing, resolution, conversion, determinism, header, required-token enforcement, identifier mapping, and failure cases — 12; generated-token staleness detection — 1; theme structure, semantic mappings, no-brand-brown-in-action-roles, Young Serif scope, canonical sizes, extensions, elevation, shapes, reduced motion — 13; contrast pairs (11 text at 4.5:1, 3 indicator at 3:1), 48 dp touch targets, and text scaling — 17; raw-colour audit, generated-file locality, and Material component widget rendering — 3; preview harness rendering — 1; app shell — 1.

### 11. Native visual verification

**Performed on a physical Android device** (`R58TA1771DR`, Android 12, armeabi-v7a), not an emulator:

- `adb install -r app-debug.apk` → Success; `com.slfurnitures.app/.MainActivity` launched and gained focus.
- Logcat showed **no** `E/flutter`, `FATAL EXCEPTION`, or `AndroidRuntime` errors.
- Captured frame renders the canonical palette exactly: `#FCF4ED` (canvas), `#FFFFFF` (paper), `#111111` (charcoal text/action), `#E5E5E5`, `#CACACB`, `#707072`, plus the blue focus indicator `#275DC5`.

**Limitation:** the captured frame covers only the top of the scrollable preview; `#F4E9DF` (editorial) and the status colours `#007D48` / `#8A5A00` / `#1151FF` sit below the fold in that frame. They are exercised by the automated contrast and extension tests, but a below-the-fold device frame was not captured. Headless-emulator verification was unreliable (intermittent `adb` disconnects), which is why a physical device was used.

### 12. Deferred decisions, gaps, and missing approvals

1. **Dark theme: deferred.** `tokens.css` defines no dark palette, so only the approved light theme exists. `--surface-inverse` is an inverse surface, **not** dark mode, and no dark colours were generated from Material defaults.
2. **`scrim` token gap.** Material requires a translucent modal scrim that canonical tokens do not define; it is derived as `--surface-inverse` at 54% alpha and flagged in code as a gap, not a new brand colour.
3. **Helvetica Now Text licensing.** Bundling it requires a redistribution licence the repository does not hold. The platform-sans fallback stands until licensing is approved; this must be revisited before any claim of full typographic parity.
4. **`44ch` lead width** is emitted as an approximate `44` with a source-unit comment; CSS `ch` has no exact Flutter equivalent.
5. **Below-fold device frame** for editorial/status colours not yet captured (see §11).
6. **Golden tests** were not added: representative-component rendering is covered by widget tests, and screenshot tests reproducing the reference image are explicitly discouraged by §10.
7. **Phase 16.2 scope only.** Environment configuration (16.3), networking (16.4), auth/session (16.5), routing (16.6), and all Group Q features remain unimplemented.

### 13. Phase 16.2 status

**Phase 16.2: COMPLETE.** Definition of Done is satisfied:

- Flutter Material 3 is enabled and the theme is derived from canonical `tokens.css` through a deterministic, `--check`-verified generator.
- There is no independently maintained competing token palette; only the generated file holds raw values, enforced by test.
- Typography, colour, spacing, shape, elevation, and motion map consistently from the same source the website consumes.
- Theme-level Material components use approved semantic roles with preserved disabled/focused/pressed/error states.
- Token synchronization, static analysis, formatting, 48 tests, and the Android debug build all pass.
- Missing font, `scrim`, and dark-mode decisions are explicitly documented above.
- No unrelated application feature was introduced.

Group P does **not** proceed to Phase 16.3 automatically; that remains a separate owner instruction. All Group O completion and production-gate records are unchanged.