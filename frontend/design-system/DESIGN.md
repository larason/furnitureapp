# SL Furnitures Design System

## Brand Principle

SL Furnitures is architectural, warm, and editorial. The interface is a quiet, disciplined frame for furniture photography and product information; it never competes with the pieces it presents.

The official brand identity asset is `designs/brandlogo.png`. Do not redraw, recolor, replace, trace, or render its wordmark as browser text. Its warm ivory field and deep brown lettering inform the system without turning every interface element brown.

## Brand Character

| Character | Visual consequence |
| --- | --- |
| Calm | Generous whitespace, few simultaneous calls to action, and no decorative noise. |
| Crafted | Material-led photography, careful type, and precise product details. |
| Architectural | Strong grid, alignment, scale, and restrained geometry. |
| Warm | A warm canvas and editorial surfaces support, rather than replace, charcoal and white. |
| Editorial | Large imagery and Young Serif create story-led moments. |
| Accessible | Legible utility type, visible focus, sufficient contrast, and semantic structure are non-negotiable. |

## Structural Heritage

The previous Nike-derived system remains the structural authority, not a consumer-facing identity. Preserve its strong hierarchy, large editorial imagery, disciplined grid and spacing, clear CTA hierarchy, restrained decoration, responsive composition, accessible contrast, and consistent interaction states.

Translate its former high-energy expression into a curated showroom and interiors publication: calm, material-led, spacious, premium, approachable, and confident. Do not restore apparel terminology or sporty visual language.

## Typography

`--font-display` is Young Serif. Use it for brand moments, hero messaging, major section headings, category storytelling, and selectively prominent product or collection titles. It supplies personality, not interface density. Do not force all display copy uppercase and do not apply aggressive tracking or compressed leading to this serif.

`--font-ui` and its legacy alias `--font-body` preserve the existing Helvetica Now utility stack for navigation, buttons, forms, filters, search, price, metadata, specifications, breadcrumbs, and account or admin interfaces. Utility type supplies precision and scannability. Font loading is framework-specific implementation work, not a shared design-system responsibility; Flutter font loading belongs to Phase 16.2 after licensing and availability are confirmed.

The canonical scale remains 12, 14, 16, 20, 24, 32, 48, and 96 pixels. No page-specific type sizes are permitted.

## Color And Surfaces

Furniture and room photography carry most visual color. The UI stays restrained enough to frame oak, walnut, linen, leather, stone, terracotta, greenery, and blackened metal without fighting them.

| Semantic role | Token | Value | Purpose |
| --- | --- | --- | --- |
| Canvas | `--surface-canvas` | `#FCF4ED` | Default warm page field, derived from the official logo. |
| Paper | `--surface-paper` | `#FFFFFF` | Product, form, and clean contrast surface. |
| Editorial | `--surface-editorial` | `#F4E9DF` | Quiet secondary storytelling surface. |
| Inverse | `--surface-inverse` | `#111111` | High-contrast bands and inverse surfaces. |
| Primary text | `--text-primary` | `#111111` | Main text and strongest iconography. |
| Secondary text | `--text-secondary` | `#707072` | Supporting copy and metadata. |
| Brand accent | `--accent-brand` | `#321E0F` | Select editorial emphasis and material detail only. |
| Primary action | `--action-primary` | `#111111` | Primary interactive action; not brown by default. |

`#111111` and `#FFFFFF` remain core anchors. Brown is never a universal button, heading, border, icon, or navigation color. Functional colors retain their meaning and must not be repurposed as brand decoration. MADE_TO_ORDER is a primary offering, not an error or unavailable treatment.

## Photography And Product Presentation

Use large, realistic furniture imagery with room context where it clarifies scale and isolated product imagery where it clarifies finish or detail. Keep overlays minimal; never place low-contrast text over busy photography. Avoid arbitrary crops, thumbnail-heavy layouts, and badge stacks over imagery.

Product cards are restrained: image, product name, price or starting price, and a small availability or request state. They do not accumulate descriptions, rating blocks, competing CTAs, shipping marketing, or nested decorative cards.

## Shape, Elevation, And Motion

Preserve the existing radius scale: sharp media, small form radius, controlled container radius, and pill geometry only where a control genuinely needs it. Do not create bubble UI or round every card.

Use flat surfaces, borders, spacing, and photographic depth before shadows. Elevation is for real layers such as menus, dialogs, drawers, popovers, and sticky elements, never decoration.

Motion is quiet, purposeful, and functional. Reuse the duration and easing tokens. Avoid springy cards, constant parallax, hover lifts, excessive scaling, or bouncing CTAs. Respect reduced-motion preferences.

## Accessibility And Responsive Composition

Accessibility is brand quality: use WCAG-aware contrast, visible keyboard focus, semantic HTML, meaningful alternative text, logical headings, sufficiently large interactive targets, and more than color alone to communicate state.

Desktop and mobile share the same visual language but not the same composition. Editorial arrangements may stack, reorder, or simplify on smaller screens. Public SEO pages remain server-renderable; visual convenience does not justify a client-only implementation.

## Prohibited Patterns

The brand is not sporty, neon, hyperactive, glassmorphic, gradient-heavy, excessively rounded, card-heavy, dashboard-like, badge-heavy, ornamental luxury, rustic cliche, all-brown, or generic AI storefront styling.

Do not introduce unapproved gradients, blurred translucent cards, decorative blobs, random accent colors, pills everywhere, oversized icon tiles, generic feature-card grids, dashboard homepage sections, unapproved emoji or illustrations, one-off CTA variants, arbitrary type scales, or arbitrary section spacing.

## Token Contract

`tokens.css` is the canonical token authority. It contains primitive values and semantic aliases. `design-tokens.json` and `tailwind-v4.css` are synchronized derived representations; MUI and Flutter mappings consume this contract in later phases and never redefine it.

Component-specific tokens are deferred until a stable component behavior cannot be expressed with global semantic tokens. Components must consume an approved token whenever one applies.
