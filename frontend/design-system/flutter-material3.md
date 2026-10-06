# Flutter Material 3 Mapping Contract

## Purpose And Boundary

This document defines how a future Flutter customer application consumes the shared SL Furnitures design system. It is an architecture contract for Phase 16.2, not Flutter implementation.

- Canonical token authority: `tokens.css`.
- Portable Flutter input: synchronized `design-tokens.json`.
- Flutter does not parse CSS or load token JSON at runtime.
- Phase 16.2 must use static Dart values produced from, or verified against, the portable token contract.
- No Flutter application, `ThemeData`, font asset, package, dark theme, or dynamic-color implementation is introduced by this contract.

Material 3 supplies platform behavior, accessibility foundations, and theme APIs. It does not supply the SL Furnitures brand. Future implementation must set `useMaterial3: true` and map approved semantics explicitly; `ColorScheme.fromSeed` and wallpaper/device dynamic color are not brand-generation mechanisms.

## Future Theme Boundary

Phase 16.2 should begin with the smallest structure that keeps the adapter legible:

```text
theme/
  app_theme.dart
  app_color_scheme.dart
  app_typography.dart
  app_theme_extensions.dart
  component_themes/
```

Names are guidance, not files to create now. `AppTheme` owns a light `ThemeData`; `AppColorScheme` and `AppTypography` map portable semantics; component themes remain focused. Exact Flutter SDK types and constructor members must be checked when that SDK is installed.

## Color Mapping

Use explicit `ColorScheme` construction from approved semantic values. The following is mapping intent, not a frozen Flutter SDK signature:

| Semantic role | Canonical token | Web/MUI | Future Flutter intent |
| --- | --- | --- | --- |
| Canvas | `--surface-canvas` | `background.default` | App background/root surface; choose the current Material `surface` or lowest surface role after SDK verification. |
| Paper | `--surface-paper` | `background.paper` | Product, form, and clean contrast surface. |
| Editorial | `--surface-editorial` | `surface.editorial` | Editorial/story surface; use a narrow extension if no built-in surface role is semantically correct. |
| Inverse | `--surface-inverse` | `surface.inverse` | Inverse/high-contrast sections and Material inverse role where appropriate; not dark mode. |
| Primary text | `--text-primary` | `text.primary` | `onSurface` or equivalent readable foreground role. |
| Secondary text | `--text-secondary` | `text.secondary` | Supporting foreground role on canvas and paper. |
| Borders | `--border-subtle`, `--border-default`, `--border-strong` | `divider` and component borders | Outline/divider semantics without forcing a status role. |
| Primary action | `--action-primary` | `primary.main` | `ColorScheme.primary`; charcoal remains the primary action. |
| Primary action foreground | `--text-inverse` | `primary.contrastText` | `onPrimary`. |
| Focus | `--action-focus` | `action.focus` | Focus state/overlay treatment; it must remain visible without relying on color alone. |
| Brand accent | `--accent-brand` | `brand.accent` | Restrained brand extension unless a Material role is semantically correct for the component. |
| Error | `--danger` | `error.main` | `error`, `onError`, and container roles as appropriate. |
| Success, warning, information | `--success`, `--warn`, `--info` | status palette | Application status semantics or a narrow extension, never primary/secondary/tertiary substitutes. |

Material's `secondary`, `tertiary`, and container roles must not be assigned merely to expose brown, editorial, or status colors. `ThemeExtension` is reserved for the editorial surface, controlled brand accent, and status semantics only when standard Material roles do not fit. It must never duplicate the full token set.

## Typography

Young Serif remains display-only. The approved utility family remains responsible for navigation, controls, forms, price, metadata, specifications, and dense content. Phase 16.2 must confirm licensing and supported Flutter font loading before bundling either family; do not silently replace the utility family with an unrelated font.

| Future Material role group | Family | Canonical scale |
| --- | --- | --- |
| `displayLarge`, `displayMedium`, selected `headlineLarge`, `headlineMedium`, `titleLarge` | Young Serif where editorial hierarchy requires it | 96, 48, 32, 24, 20 px only |
| `bodyLarge`, `bodyMedium`, `bodySmall`, `labelLarge`, `labelMedium`, `labelSmall`, controls | Approved utility family | 20, 16, 14, 12 px only |

The Material defaults do not create a second type scale. Future Flutter layouts must honor user text scaling and remain usable at enlarged system text sizes.

## Spacing, Shape, Elevation, And Motion

- Spacing: map `primitive.space` to a typed static Dart adapter. Application padding and gaps use the canonical 4, 8, 12, 16, 20, 24, 32, 48, 64, and 80 scale, not ad-hoc values.
- Shape: map the canonical radius scale to sharp media, small form radius, controlled containers, and functional pills only. Phase 16.2 must inspect button, input, card, dialog, bottom sheet, chip, and navigation defaults so Material rounding does not change the brand.
- Elevation: flat surfaces come first. `flat`, `ring`, and `raised` semantics distinguish true layers such as menus, dialogs, drawers, bottom sheets, and overlays without creating decorative shadows across cards.
- Motion: map only the canonical fast/base durations and standard easing. Motion is purposeful, quiet, and never required to understand state. Respect platform reduced-motion preferences where supported.

## Components, Icons, And Accessibility

Future component theme centralization may include app bars, button variants, icon buttons, inputs, cards, dialogs, bottom sheets, navigation bars, dividers, chips, and snack bars. Verify the installed Flutter API before choosing exact theme classes.

Flutter uses built-in Material Icons by default. Do not carry the web-only MUI icon package into Flutter, add Font Awesome/Lucide/custom icon packs, or use emoji as controls. Icon-only actions require semantic labels and tooltips; decorative icons are excluded from semantics. Icons inherit semantic colors, use consistent Material sizing, and should not decorate every heading or card. Filled and outlined variants need an intentional state or hierarchy reason.

Future Flutter UI must provide semantic labels and logical traversal, platform-appropriate minimum touch targets, visible focus treatment, contrast-compliant content, non-color status cues, user text scaling, reduced-motion consideration, and mobile-native safe-area, keyboard, back-navigation, scrolling, dialog, and system-overlay behavior.

## Mobile Adaptation And Status Meaning

The Flutter customer application shares semantics with the web site but is not a compressed browser layout. It may use mobile app bars, bottom navigation, modal bottom sheets, and native scrolling while preserving the same surface hierarchy and charcoal primary action.

`MADE_TO_ORDER` is a primary offering and must never appear disabled, unavailable, or error-like. `IN_STOCK`, `LOW_STOCK`, and `UNAVAILABLE` require explicit text and/or iconography in addition to color. No Staff or Admin mobile theme variant is implied.

## Phase 16.2 Implementation Checklist

1. Verify the installed Flutter Material 3 API and use `useMaterial3: true`.
2. Generate static Dart constants from `design-tokens.json` only if an existing deterministic pipeline supports it; otherwise maintain a small semantic adapter verified against the JSON.
3. Implement explicit light `ColorScheme` and `TextTheme` mappings without `ColorScheme.fromSeed` or dynamic colors.
4. Add minimal extensions only where a Material role cannot express editorial, brand-accent, or status semantics.
5. Validate text scaling, focus, contrast, touch targets, reduced motion, and responsive mobile compositions.
