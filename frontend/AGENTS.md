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

## Structured Data (SEO)

- Emit JSON-LD only from server components, in the initial HTML, through the shared `components/seo/json-ld.tsx` renderer and `lib/seo/structured-data.ts` builders.
- Reuse the Phase 14.7 `SITE_URL` origin, canonical builder, media selection, and cached CAT-002/CAT-004 resources. Never use `API_BASE_URL`, CDN origins, or request host headers for page identity.
- Describe only what the page truthfully shows. Do not invent brand, SKU, GTIN/MPN, reviews, ratings, variants, or business details, and do not serialize API minor-unit prices as major units.
- `Product.offers` stays omitted while the release is request-first (no active cart/checkout/payment). Do not fabricate merchant-listing fields. `MADE_TO_ORDER` is a valid offering, never `OutOfStock`.
- `Product` JSON-LD belongs only on canonical product-detail pages; keep collection, category, and search pages free of product graphs and `SearchAction`.

## Crawl Discovery

- Serve `/sitemap.xml` and `/robots.txt` through `app/sitemap.ts` / `app/robots.ts` (Next metadata routes); never add static `public/` snapshots.
- Sitemap entries are canonical, query-free URLs from the public catalog APIs (`/`, `/products`, `/categories/{slug}`, `/products/{slug}`), using backend slugs. Do not enumerate search, facet, sort, price, pagination, or reserved routes, and do not fabricate `lastModified`/`changeFrequency`/`priority`.
- `robots.txt` allows public crawling, disallows `/search` and the owned facet parameters as first and subsequent query params, and advertises `Sitemap: {SITE_URL}/sitemap.xml` only when `SITE_URL` is configured. Keep page-level `noindex` metadata independent of robots rules.

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

## Responsive Foundation

- `design-system/tokens.css` owns the canonical responsive values. MUI maps them in `web/theme/theme.ts`; do not add page-specific breakpoint scales or device-specific layout rules.
- Use CSS/MUI breakpoint styling for presentation. Do not branch layout during render with `window.innerWidth`, user-agent detection, or `useMediaQuery` unless JavaScript behavior genuinely requires it.
- `ContentContainer` owns ordinary site max-width and gutters. `SiteSection` owns contained versus full-bleed composition; pages must not create a second container, gutter, or viewport-width hack.
- Do not hide layout defects with global `overflow-x: hidden`, `100vw`, or negative viewport margins. Fix the overflowing element and preserve logical DOM order as layouts reflow.
- Future page phases own their responsive compositions, grids, image ratios, and feature-specific overlays. Follow `web/RESPONSIVE.md` before adding them.

## Failure States

- Keep four failure classes distinct: route loading, not found, expected domain/API failure, and unexpected error. Never collapse them into one generic "Something went wrong".
- Route-level unexpected errors belong to `app/error.tsx` (an isolated Client Component using the framework `retry()`). Expected outcomes (validation, authentication, authorization, empty results, rate limits, MADE_TO_ORDER) must not be routed through the error boundary.
- `app/not-found.tsx` is the generic public 404. Use the framework `notFound()` for expected absence; never redirect missing resources home or show product/category-specific 404 copy.
- `app/loading.tsx` is a generic, shell-preserving pending state. Do not encode product/category/search skeletons or artificial delays; feature skeletons belong to their owning Group N phases.
- Retry re-renders the failed segment only. Never use `window.location.reload()`, and never auto-retry non-idempotent mutations (requests, enquiries, payments).
- The API client stays framework-agnostic and only a 404 is translated to `notFound()` at page/domain integration. Never map 401/403/422/429/5xx/network/timeout/abort to 404, and never detect status by message string matching.
- Never render raw exception messages, stacks, digests, Laravel bodies, filesystem paths, headers, environment data, or internal identifiers in failure UI.
