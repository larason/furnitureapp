# UI Engineering Rules

# Design System Contract

The canonical project design system is:

- `design-system/DESIGN.md` - The furniture e-commerce design system spec (monochrome palette, Futura Condensed display type, pill buttons, flat cards)
- `design-system/tokens.css`

These files are authoritative.

Before implementing or modifying UI:

1. Read DESIGN.md.
2. Read tokens.css.
3. Inspect existing components.
4. Reuse existing tokens and components.
5. use frontend-design skills for guidance. Available in frontend/.agents/skills/

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
large corner radii, or animated effects unless explicitly specified by the design system.

## Verification

After UI changes:

1. run typecheck
2. run lint
3. run tests
4. run visual regression checks
5. report any token violations