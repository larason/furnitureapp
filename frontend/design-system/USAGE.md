# SL Furnitures Design System Usage

## Authority And Read Order

1. Read this file, then `DESIGN.md`.
2. Read `tokens.css`, the sole canonical token authority.
3. Use `design-tokens.json` for framework-neutral inspection and future MUI/Flutter mappings.
4. Treat `tailwind-v4.css` as a synchronized export only. Tailwind is not authorized for `frontend/web`.
5. Inspect previews for a visual sanity check. The existing component fixture is reference material, not a source of token authority.

`source/` preserves provenance and audit records from the bundled fixture. It is not a second hand-maintained token authority. A token change starts in `tokens.css`, then updates `design-tokens.json` and `tailwind-v4.css` in the same change. Framework mappings are deferred to Phase 12.3.

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
