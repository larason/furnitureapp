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

## Laravel API Access

- Route all website HTTP calls to Laravel through `web/lib/api/client.ts`; do not use raw page-level `fetch` or add an overlapping HTTP client.
- Keep this transport domain-neutral. Domain/query functions and their cache policies belong in their owning phases.
- Preserve the frozen API success/error envelopes and structured validation details. Do not put business rules, redirects, or authorization decisions in the transport.
- Inject current Clerk bearer tokens per request only in a later approved auth layer; never persist or globally retain tokens.

## Website Routes

- Consult `web/ROUTING.md` before adding website routes. Keep App Router conventions there authoritative.
- Product and category URLs use the slugs returned by Laravel; do not derive frontend-only slugs or use internal numeric database IDs.
- Put shareable collection/search state in documented URL parameters using the Laravel contract vocabulary. Do not implement routes owned by later phases early.

## Icons

Use only `@mui/icons-material` for web UI icons. Do not add `lucide-react`, `react-icons`, Heroicons, Font Awesome, custom SVG icon libraries, or emoji as UI icons.

Icons inherit semantic theme colors rather than hard-coded values and use standard MUI sizes consistently. Hide decorative icons from assistive technology. Icon-only controls with meaningful actions require accessible labels and tooltips. Do not decorate every heading or card with icons, and do not mix outlined and filled icon variants without an intentional design reason.

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

## Website Shell

- Use the canonical site shell at `web/components/layout/site-shell.tsx`. Do not introduce a competing shell (`AppShell`, `StoreShell`, etc.).
- Pages render inside the shell's single `<main id="main-content">`; do not declare another `main` landmark in a page or layout.
- Use `ContentContainer` for horizontal site geometry and `SiteSection` for vertical/semantic composition. Do not invent page-level max-widths, gutters, or centering.
- Shell surfaces, spacing, and typography must consume the design tokens/theme. Do not create a second token authority.
- While the release is request-first, do not add cart, checkout, payment, wishlist, or account controls, and never use `href="#"` or undocumented routes. Do not render reserved-but-unimplemented routes as active links; render them as non-interactive structural content until their page exists (see `web/components/layout/site-navigation.ts`). Only implemented routes may be active links.
- Keep the shell server-first. Introduce a client boundary only for genuine interaction (currently the mobile navigation drawer); do not move the whole header/footer client-side.
- Category navigation is a fixture until Group N supplies authoritative catalog data; do not duplicate the Laravel taxonomy into frontend-only production navigation.
- Established furniture retailers are structural/IA references only. SL Furnitures tokens, typography, components, and accessibility remain authoritative.
