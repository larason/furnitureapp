# SL Furnitures Design System Usage

## Authority And Read Order

1. Read this file, then `DESIGN.md`.
2. Read `tokens.css`, the sole canonical token authority.
3. Use `design-tokens.json` for framework-neutral inspection and future MUI/Flutter mappings.
4. Treat `tailwind-v4.css` as a synchronized export only. Tailwind is not authorized for `frontend/web`.
5. Inspect previews for a visual sanity check. The existing component fixture is reference material, not a source of token authority.

`source/` preserves provenance and audit records from the bundled fixture. It is not a second hand-maintained token authority. A token change starts in `tokens.css`, then updates `design-tokens.json` and `tailwind-v4.css` in the same change.

## MUI Bridge

`frontend/web/theme/theme.ts` is the single MUI adapter. It consumes synchronized `design-tokens.json` values where MUI needs concrete build-time values and keeps `tokens.css` globally available for CSS-variable references. `app/providers.tsx` uses the official Next 16 `AppRouterCacheProvider` around `ThemeProvider`; the root layout remains a Server Component.

MUI maps canvas to `background.default`, paper to `background.paper`, charcoal action to `primary.main`, the utility font to default UI typography, and Young Serif to display headings only. Typed `theme.palette.brand.accent`, `theme.palette.surface.editorial`, and `theme.palette.surface.inverse` expose the few furniture semantics MUI does not natively name. Do not add an MUI-only token system or raw color values to components.

Use only `@mui/icons-material` for UI icons. Icons inherit semantic theme colors and use standard MUI sizes. Decorative icons are hidden from assistive technology; meaningful icon-only controls require accessible labels and tooltips. Do not substitute emoji, other icon libraries, or arbitrary custom SVG icons, and do not mix outlined and filled variants without an intentional reason.

## Flutter Material 3 Contract

`flutter-material3.md` defines the future Flutter mapping. Flutter consumes `design-tokens.json` through a static semantic adapter in Phase 16.2; it does not parse `tokens.css` or load token JSON at runtime. Material 3 maps the shared brand rather than generating it from a seed or device colors. No Flutter application code, package, or font asset belongs to this design-system phase.

## Component Conventions

`COMPONENTS.md` defines how future MUI components and Flutter widgets compose these tokens. It is subordinate to `DESIGN.md`, `tokens.css`, and the framework mappings. It defines semantics, ownership, variants, states, accessibility, and platform boundaries; it does not implement a runtime component library.

## Token Layers

- Primitive tokens hold raw reusable values such as `--color-neutral-950`, `--color-warm-100`, and `--space-4`.
- Semantic tokens assign product meaning, such as `--surface-canvas`, `--text-primary`, and `--action-primary`.
- Component-specific tokens are not created until a stable repeated component behavior cannot use a global semantic token.

Important reconciliation:

| Previous role | Furniture role | Primitive | Decision |
| --- | --- | --- | --- |
| White universal page background | `--surface-paper`; canvas is `--surface-canvas` | `#FFFFFF`; `#FCF4ED` | White retained for product and form surfaces; ivory becomes the default canvas. |
| Charcoal foreground and CTA | `--text-primary`, `--action-primary` | `#111111` | Preserved for legibility and primary actions. |
| Monochrome-only accent | `--accent-brand`, `--accent-material` | `#321E0F` | Added as controlled editorial/material emphasis, never a universal UI color. |
| Condensed uppercase display face | `--font-display` | Young Serif | Replaced by an editorial display serif; utility UI remains sans-serif. |

## Rules

Use an approved token for colors, font families, font sizes, line heights, letter spacing, spacing, radii, shadows, breakpoints, durations, easing curves, z-index, and media ratios whenever one applies. Do not place raw values in application components.

If no token expresses a real requirement:

1. Do not hard-code it locally.
2. Decide whether the requirement is reusable.
3. Add or revise the canonical token in `tokens.css`.
4. Synchronize the JSON and Tailwind representations.
5. Document its purpose in `DESIGN.md` or this file.
6. Consume the new token only after that review.

Do not introduce gradients without authority, glass effects, blurred translucent cards, decorative blobs, random accent colors, random rounded cards, excess shadows, generic feature-card grids, dashboard styling, unapproved emoji or illustrations, or one-off CTA variants.

## Accessibility Checks

Approved core pairings meet their intended WCAG use: primary text on canvas and paper, secondary text on canvas, inverse text on inverse surface, and white text on the charcoal primary action. The focus ring uses `--action-focus` and remains distinct on canvas, paper, and inverse contexts. Functional status requires text, iconography, or another non-color cue alongside color.

The official logo keeps its own asset background. Present it unchanged on compatible light surfaces; do not infer transparency or recolor it.

## Visual Foundation Rules

These are the closed semantic choices for later component work. Framework APIs may differ, but the meaning does not.

| Foundation | Use | Canonical roles |
| --- | --- | --- |
| Display typography | Editorial statements, major headings, storytelling, select prominent product titles | `--font-display`, 24 / 32 / 48 / 96 |
| UI typography | Navigation, controls, forms, pricing, metadata, status, specifications, account/admin UI | `--font-ui`, 12 / 14 / 16 / 20 |
| Body rhythm | Readable paragraphs and supporting copy | `--leading-body` |
| Tight rhythm | Labels, controls, metadata, compact headings | `--leading-snug` |
| Display rhythm | Young Serif headings without cramped leading | `--leading-display` |
| Canvas | Default page field | `--surface-canvas` |
| Paper | Product, form, and clean content surface | `--surface-paper` |
| Editorial | Storytelling and quiet differentiation only | `--surface-editorial` |
| Inverse | High-contrast section or true inverse surface, not dark mode | `--surface-inverse` |
| Control radius | Inputs, buttons, and ordinary interactive controls | `--radius-sm` |
| Container radius | Controlled panels or grouped surfaces | `--radius-md` / `--radius-lg` |
| Pill radius | Chips, compact filters, or controls that require it | `--radius-pill` only |
| Elevation | Real layers only | `--elev-flat` by default; `--elev-raised` for overlays |
| Motion | Feedback and standard transitions | `--motion-fast` / `--motion-base` with `--ease-standard` |

Use the spacing scale by relationship: `--space-1` to `--space-3` for micro/control rhythm, `--space-4` to `--space-6` for component rhythm, `--space-7` to `--space-8` for content rhythm, and `--space-9` to `--space-10` for section/page rhythm. Responsive gutters and section spacing use their existing semantic layout tokens; do not create mobile-only scales.

Interaction states are distinct: hover may adjust color, border, underline, or restrained opacity; focus is always visible through `--focus-ring`; active/pressed derives from action semantics; disabled remains readable and is not communicated by opacity alone. Never remove focus without an equally visible approved replacement. State must not rely on color alone.

Photography remains the dominant source of visual color. Do not compensate with decorative gradients, heavy shadows, arbitrary borders, rounded media, or repeated accent treatments. Product imagery is generally sharp; cards and ordinary containers are flat unless they represent a true layer.
