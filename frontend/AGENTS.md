# UI Engineering Rules

# Design System Contract

The canonical project design system is:

- `design-system/DESIGN.md` - The SL Furnitures brand and interaction contract
- `design-system/tokens.css` - The sole canonical token authority

These files are authoritative.

Before implementing or modifying UI:

1. Read DESIGN.md.
2. Read tokens.css.
3. Inspect existing components.
4. Reuse existing tokens and components.
5. Use the project frontend-design skill for guidance.

Never invent new:
- colors
- spacing
- typography
- radii
- shadows
- breakpoints

Do not bypass the design system with arbitrary CSS values.

## Forbidden

Do not invent:

- colors
- font sizes
- font weights
- spacing values
- border radii
- shadows
- breakpoints
- animation timings
- z-index values
- media ratios

Do not use arbitrary values such as:

- #ffffff
- #123456
- 13px
- 19px
- 27px
- 18px
- rounded-xl
- random box-shadow values

unless that value already exists as a token.

If a required design cannot use an approved token, do not hard-code it in a component. Propose a reusable semantic token in `design-system/tokens.css`, synchronize `design-tokens.json` and `tailwind-v4.css`, document its purpose, then consume it.

## Styling

Prefer:

- MUI theme tokens
- theme.vars
- sx
- styled()
- reusable components

Do not introduce ad-hoc CSS when an existing token or component can express the same intent.

## Component reuse

Before creating a new UI primitive, inspect existing components.

Do not create a second:

- Button
- Card
- Badge
- Price
- ProductImage
- ProductGrid
- Section
- Container

when an existing primitive already exists.

## Visual restraint

Never add gradients, glass effects, decorative blobs, excessive shadows,
large corner radii, pill-shaped everything, dashboard-like card grids, random accent colors,
or animated effects unless explicitly specified by the design system.

## Verification

After UI changes:

1. run typecheck
2. run lint
3. run tests
4. run visual regression checks
5. report any token violations
