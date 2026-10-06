# Phase 14.1 — Public Storefront Homepage

## Objective

Design and implement the production-quality SL Furnitures public homepage at:

```text
/
```

This is the first implementation phase of:

```text
Group N — Public Catalog & SEO
```

The homepage is a major customer-facing surface and must establish the visual standard for all subsequent storefront pages.

It must feel:

```text
architectural
warm
editorial
calm
crafted
material-led
spacious
premium but approachable
furniture-first
photography-led
```

It must NOT feel:

```text
generic ecommerce template
AI-generated landing page
SaaS dashboard
discount marketplace
fashion store with furniture substituted
overdecorated luxury site
```

The homepage should communicate:

1. what SL Furnitures offers;
2. the quality and character of the furniture;
3. how users can discover furniture;
4. that both available and MADE_TO_ORDER furniture are first-class offerings;
5. that customers can make furniture requests;
6. a clear path deeper into the catalog without overwhelming them.

---

# 1. Critical Design Authority

Before writing ANY homepage UI code, explicitly read and follow:

```text
frontend/design-system/DESIGN.md
frontend/design-system/USAGE.md
frontend/design-system/COMPONENTS.md
frontend/design-system/ACCESSIBILITY.md
frontend/design-system/tokens.css
frontend/design-system/design-tokens.json

frontend/AGENTS.md
frontend/web/RESPONSIVE.md        # if Phase 13.9 created it
frontend/web/ROUTING.md
```

Also inspect:

```text
frontend/web/theme/
frontend/web/components/layout/
frontend/web/components/states/
frontend/web/app/layout.tsx
```

The design system is **FROZEN AUTHORITY** for this phase.

---

# 2. Explicit Token Rule — NON-NEGOTIABLE

The homepage MUST consume the frozen design-system tokens.

Do NOT invent:

```text
new colors
new spacing scale
new typography scale
new border-radius scale
new shadows
new elevations
new breakpoints
new motion durations
new easing
new focus treatment
new container widths
new semantic surfaces
```

because the homepage "needs" them.

The dependency direction remains:

```text
Frozen design tokens
        ↓
MUI theme
        ↓
existing primitives
        ↓
homepage components
        ↓
homepage composition
```

Never:

```text
Homepage visual idea
        ↓
random CSS value
        ↓
new pseudo-token
```

---

# 3. No Ad-Hoc Visual Values

Before adding a raw value such as:

```text
#xxxxxx
17px
27px
38px
13px radius
custom box-shadow
custom cubic-bezier
```

STOP.

Search the approved token/theme system.

If an approved token exists, use it.

If the desired appearance cannot be expressed with the frozen system:

```text
do NOT silently extend the design system.
```

Report the actual missing capability.

A homepage is not authorized to mutate the design language.

---

# 4. No Reinventing Components

Before creating ANY component:

1. inspect existing components;
2. inspect `COMPONENTS.md`;
3. inspect layout primitives;
4. inspect MUI;
5. determine whether composition solves the requirement.

Do NOT create a new component simply because:

```text
the homepage needs a button
the homepage needs a link
the homepage needs a container
the homepage needs a section
the homepage needs an image wrapper
the homepage needs a heading
```

if an existing project/MUI primitive already owns that responsibility.

---

# 5. Component Reuse Rule

The preferred order is:

```text
existing project primitive
        ↓
existing MUI primitive
        ↓
composition of existing primitives
        ↓
new reusable component only if a real domain responsibility exists
```

Do not build homepage-specific duplicates of:

```text
ContentContainer
SiteSection
Button
Link
Surface
Navigation
Image
Typography
```

---

# 6. New Components Must Earn Their Existence

A new homepage component is justified only if it represents a real reusable content/domain concept.

Possible examples:

```text
HeroSection
CategoryDiscovery
ProductCard
ProductRail / ProductCollection
EditorialFeature
MadeToOrderFeature
```

but these names are illustrative.

Do NOT mechanically create all of them.

Inspect existing architecture first.

---

# 7. Do Not Create Component Soup

Avoid:

```text
Homepage
 ├── HeroSection
 │    ├── HeroWrapper
 │    ├── HeroContainer
 │    ├── HeroContent
 │    ├── HeroTitle
 │    └── HeroActions
```

when ordinary composition is sufficient.

Component boundaries should represent responsibilities, not every DOM level.

---

# 8. Urban Ladder Reference — EXPLICIT RULE

Urban Ladder is an approved:

```text
STRUCTURAL / INFORMATION-ARCHITECTURE REFERENCE
```

for the homepage.

It is NOT the visual implementation authority.

Use it to study proven furniture-commerce concepts such as:

```text
homepage information hierarchy
category-led discovery
hero positioning
catalog discovery rhythm
product merchandising placement
editorial/lifestyle sections
Made-to-Order/service discovery
footer transition
```

---

# 9. Urban Ladder — DO NOT COPY

Do NOT copy:

```text
exact homepage
exact sections
exact section order
exact wording
exact navigation
exact grids
exact card designs
exact dimensions
exact typography
exact colors
exact promotional mechanics
exact hero treatment
exact images
exact icons
source code
CSS
component structure
```

Do not attempt a pixel clone.

---

# 10. Structural Reference Principle

The correct process is:

```text
Urban Ladder
    ↓
understand furniture-commerce IA
    ↓
translate through SL Furnitures business requirements
    ↓
translate through frozen SL Furnitures design system
    ↓
create original SL Furnitures homepage
```

NOT:

```text
Urban Ladder screenshot
    ↓
copy layout
    ↓
change colors/logo
```

---

# 11. Homepage Identity

The finished page should be identifiable as:

```text
SL Furnitures
```

even if Urban Ladder were removed from the comparison.

If the result resembles a recolored Urban Ladder homepage, the phase fails.

---

# 12. Photography Leads

The design system established that photography carries most visual color.

Therefore:

```text
furniture/interior photography
        ↓
provides visual richness

UI
        ↓
provides calm structure
```

Do not compensate for weak imagery using:

```text
gradients
decorative blobs
colored cards
oversized shadows
floating decorations
excessive accent brown
```

---

# 13. Homepage Content Architecture

Build an intentional editorial-commerce sequence.

Recommended starting architecture:

```text
SITE SHELL
│
├── HERO
│
├── CATEGORY / ROOM DISCOVERY
│
├── FEATURED / SELECTED FURNITURE
│
├── EDITORIAL STORY
│
├── MADE TO ORDER
│
├── SECONDARY CURATED COLLECTION
│
├── CRAFT / MATERIAL / BRAND STORY
│
└── SITE FOOTER
```

This is a design framework, not a mandatory component count.

The agent may refine ordering based on actual business/domain information.

---

# 14. Homepage Rhythm

The page should alternate intentionally between:

```text
discovery
merchandising
editorial breathing space
merchandising
brand/service storytelling
```

Avoid:

```text
grid
grid
grid
grid
grid
footer
```

Likewise avoid:

```text
marketing banner
marketing banner
marketing banner
marketing banner
```

---

# 15. Hero Purpose

The hero must immediately communicate:

```text
furniture
craft
interiors
SL Furnitures character
```

It should not look like a generic startup landing page.

---

# 16. Hero Composition

Prefer:

```text
large editorial furniture/interior image
+
restrained headline
+
short supporting copy
+
one strong primary action
+
optional restrained secondary action
```

Do not overload it.

---

# 17. Hero Image

For development fixtures, use a high-quality wide interior photograph.

Recommended source geometry:

```text
approximately 16:9
or another intentional wide editorial ratio
```

Example development source:

```text
2400 × 1350
```

Exact rendered ratio may respond with composition.

---

# 18. Hero Image Requirements

The hero fixture should:

```text
show real furniture clearly
have deliberate interior composition
leave useful negative space where text overlaps, if applicable
fit the warm/editorial identity
avoid excessive visual clutter
```

Do not use a generic abstract background.

---

# 19. Hero Text Placement

Text may be:

```text
over image
or
adjacent to image
```

depending on the strongest responsive composition.

If overlaid:

- maintain contrast;
- do not rely on uncontrolled image brightness;
- do not add a giant gradient merely to repair poor image selection;
- ensure mobile composition remains legible.

---

# 20. Hero Typography

Young Serif is appropriate for the major homepage display heading.

Do not use Young Serif for:

```text
buttons
navigation
metadata
prices
utility labels
```

Those remain utility sans.

---

# 21. Hero Copy

Keep it restrained.

Do not generate generic AI luxury copy such as:

```text
Elevate your living experience
Where luxury meets comfort
Transform your space today
Timeless elegance redefined
```

Prefer concrete furniture/craft language aligned with the business.

---

# 22. Hero CTA

The primary CTA remains charcoal/action-primary.

Do NOT make brown the universal CTA because it is the brand accent.

CTA destinations must exist under the routing contract.

Do not link to an unimplemented route.

---

# 23. No Dead CTA

If the ideal catalog destination is not implemented yet:

```text
do not create a fake href
do not use href="#"
```

Use only currently valid destinations, or defer the active CTA until the owning route exists.

Because later Group N phases will introduce category/listing routes, document any intentionally deferred homepage link.

---

# 24. Category / Room Discovery

Furniture shopping is naturally category/room driven.

Provide a strong discovery section that helps users understand the catalog.

Possible concepts:

```text
Shop by room
Explore furniture
Furniture for every space
```

Use language appropriate to SL Furnitures.

---

# 25. Category Data Authority

Do NOT invent a second category taxonomy.

Inspect Laravel/category API/seed authority.

Use authoritative:

```text
name
slug
hierarchy
```

when production category data is consumed.

Do not derive slugs from labels.

---

# 26. Phase Boundary for Category Routes

Actual category pages belong to:

```text
14.2
```

Do not implement category pages during 14.1.

If category links would currently target routes that are not implemented, do not expose broken production links.

You may render homepage discovery content using fixture data while documenting link activation for 14.2.

---

# 27. Category Imagery

Development fixtures may use:

```text
4:3
```

editorial/category imagery.

Suggested source size:

```text
1600 × 1200
```

Do not force category photography to 1:1 without a design reason.

---

# 28. Category Presentation

Avoid a dashboard grid of rounded cards.

Prefer furniture/editorial composition where:

```text
image
category name
possibly subtle discovery cue
```

does most of the work.

---

# 29. Category Count

Do not show every taxonomy node on the homepage.

Homepage discovery should curate.

The full hierarchy belongs to navigation/category experiences.

---

# 30. Product Merchandising

The homepage may introduce a small curated product presentation.

Possible concepts:

```text
Featured pieces
Selected furniture
New pieces
For your home
```

Do not fabricate marketing claims such as:

```text
Best sellers
Top rated
Most loved
```

without data supporting them.

---

# 31. Product Card Is a Commerce Component

If no reusable ProductCard exists yet, Phase 14.1 may establish the first canonical public `ProductCard` because the homepage genuinely needs it and later Group N pages will reuse it.

But:

> There must be ONE canonical ProductCard architecture.

Do not create:

```text
HomepageProductCard
CategoryProductCard
ListingProductCard
SearchProductCard
```

later.

Design it for reuse.

---

# 32. Product Card Scope

The canonical product card should remain restrained.

At most, based on actual API/domain data:

```text
product image
product name
price / starting price
small availability or MADE_TO_ORDER state
```

Potentially a semantic link to product detail once the route exists.

---

# 33. Product Card Must NOT Become

```text
image
sale badge
discount percentage
rating stars
review count
wishlist
quick add
buy now
cart
compare
delivery badge
stock badge
three CTAs
```

This conflicts with the project design and request-first release.

---

# 34. MADE_TO_ORDER on Cards

MADE_TO_ORDER is a primary business offering.

Present it calmly.

It is NOT:

```text
error
warning
out of stock
unavailable
```

Do not use error red.

---

# 35. Product Image Ratio

For standard product merchandising, use:

```text
4:5
```

as the canonical initial display frame unless repository design decisions already establish otherwise.

Recommended fixture source:

```text
1200 × 1500
```

or:

```text
1600 × 2000
```

Do not globally force raw media files themselves to 4:5.

---

# 36. Product Image Cropping

Use consistent merchandising frames while respecting furniture composition.

Do not crop:

```text
chair legs
sofa arms
bed frames
table edges
cabinet tops
```

carelessly.

Fixture photography should have sufficient breathing room.

---

# 37. `next/image`

Use:

```text
next/image
```

for homepage photography.

Do not use raw `<img>` for ordinary homepage media unless a specific technical reason exists.

---

# 38. Image Format

Do not build the architecture around manually forcing WebP.

Provide good source media and let the Next.js image pipeline perform delivery optimization according to the project's configuration.

Local development fixtures may be:

```text
JPEG
WebP
PNG only when genuinely appropriate
```

Avoid enormous uncompressed source files.

---

# 39. `sizes`

Every responsive `fill` image should have a meaningful `sizes` contract.

Do not use:

```text
sizes="100vw"
```

for every image merely to silence warnings.

`sizes` should describe actual rendered width.

---

# 40. Phase-Specific `sizes`

14.1 owns `sizes` for homepage placements.

Later:

```text
14.3
14.4
```

may define different `sizes` for listing/PDP placements.

Do not prematurely optimize every future use case.

---

# 41. Image Priority

Only above-the-fold imagery that genuinely affects LCP should receive priority/preload treatment according to the installed Next.js image API.

Do not mark every homepage image high priority.

---

# 42. Image Alt Text

Meaningful content images need meaningful alt text.

Decorative imagery should use the appropriate empty-alt treatment.

Do not write:

```text
image
furniture image
hero image
```

as alt text.

---

# 43. Local Development Fixtures

For Phase 14.1, use curated local fixtures rather than requiring Cloudflare R2.

Suggested organization:

```text
frontend/web/public/fixtures/furniture/
├── hero/
├── products/
├── categories/
└── editorial/
```

Exact structure may follow existing conventions.

---

# 44. Fixture Status

Fixtures must be explicitly documented as:

```text
development / visual-design fixtures
```

They are NOT production catalog media.

Do not make them authoritative business data.

---

# 45. Fixture Data

If product/category fixture objects are needed, isolate them clearly.

For example:

```text
homepage fixtures
```

must not masquerade as production API data.

---

# 46. Source-Agnostic Components

This is NON-NEGOTIABLE.

Components must receive media as data.

Correct:

```text
Fixture data ──────┐
                   ▼
              ProductCard
                   ▲
Laravel/R2 later ──┘
```

Wrong:

```text
ProductCard
    ↓
hard-coded import of sofa-01.jpg
```

---

# 47. Future R2 Compatibility

A ProductCard should not care whether:

```text
image URL
```

comes from:

```text
/public fixture
Cloudflare R2
custom media domain
Laravel API
```

Media source is data.

---

# 48. Do Not Integrate R2 in 14.1

Do NOT:

```text
create R2 bucket
configure R2 credentials
implement upload
change backend media storage
create Cloudflare Worker
add R2 SDK
```

merely to render the homepage.

Production media integration can be wired when appropriate without changing component contracts.

---

# 49. Editorial Story Section

Include at least one section that breaks the catalogue rhythm.

Conceptually:

```text
large interior/furniture image
+
short editorial copy
+
optional restrained action
```

This should reinforce:

```text
craft
materials
living spaces
furniture character
```

rather than generic brand marketing.

---

# 50. Editorial Image Ratio

Use an intentional ratio such as:

```text
3:2
4:3
```

depending on composition.

Do not force every image into the product-card 4:5 frame.

---

# 51. Made-to-Order Section

MADE_TO_ORDER deserves prominent homepage treatment.

It should communicate that customers can request furniture beyond ordinary ready-stock discovery.

This is not a fallback.

It is a core offering.

---

# 52. Made-to-Order Presentation

Use:

```text
strong photography
clear heading
short explanation
clear request action
```

without turning the section into a warning/info box.

---

# 53. Furniture Request Route

Use the canonical existing route:

```text
/furniture-requests
```

only if it is currently implemented and safe to expose according to routing/application state.

Do not invent another route.

---

# 54. Brand / Craft Story

A later homepage section may communicate:

```text
materials
craftsmanship
made-to-order capability
thoughtful furniture for real spaces
```

but only make factual claims supported by project/business information.

---

# 55. No Fabricated Business Claims

Do NOT invent:

```text
20 years of experience
handmade by 50 artisans
sustainably sourced timber
free nationwide delivery
lifetime warranty
locally sourced materials
award-winning designs
```

unless authoritative project data supports them.

---

# 56. No Fake Metrics

No:

```text
10,000+ happy customers
500+ products
4.9/5 rating
99% satisfaction
```

without actual data.

---

# 57. No Fake Reviews

Do not invent testimonials or ratings.

---

# 58. No Fake Brands

Do not add manufacturer/partner logos without business authority.

---

# 59. No Newsletter

Do not add newsletter signup just because ecommerce homepages commonly have one.

There is no approved newsletter feature.

---

# 60. No Social Feed

Do not create an Instagram/Pinterest gallery without approved accounts/data.

---

# 61. No Cart

Request-first policy remains active.

Homepage must NOT introduce:

```text
cart
add to cart
buy now
checkout
mini cart
cart count
```

---

# 62. No Wishlist

Do not introduce wishlist/favourites.

---

# 63. No Reviews

Do not introduce product ratings/reviews unless a later approved feature establishes them.

---

# 64. No Sale System

Do not introduce:

```text
sale prices
strikethrough prices
discount percentages
countdowns
flash sale banners
coupon fields
```

without backend/business support.

---

# 65. No Promotional Clutter

Urban Ladder or other references may contain extensive promotions.

SL Furnitures should not copy that density.

Avoid:

```text
multiple announcement bars
sale ribbons
coupon popups
floating offers
deal badges
countdowns
```

---

# 66. Homepage Surface Rhythm

Use approved semantic surfaces only:

```text
canvas
paper
editorial
inverse
```

where justified.

Do not alternate surfaces mechanically every section.

---

# 67. Canvas

Warm ivory:

```text
#FCF4ED
```

is already represented through the frozen semantic token.

Do not hard-code this hex in homepage components.

Use the token/theme.

---

# 68. Charcoal

Primary action/text anchor remains:

```text
#111111
```

through approved semantic tokens.

Do not hard-code the value.

---

# 69. Brand Brown

Deep brown:

```text
#321E0F
```

remains a controlled accent.

Do not turn every:

```text
heading
button
icon
link
border
```

brown.

---

# 70. White

White remains an important clean content/product surface.

Do not make the whole website beige/brown.

---

# 71. Flat-First

Product and category merchandising should be flat-first.

Prefer:

```text
image
spacing
typography
subtle structure
```

before:

```text
shadow
card background
border
rounded container
```

---

# 72. Radius

Furniture imagery should generally remain architecturally restrained.

Do not round every image into soft SaaS cards.

Use frozen radius tokens where the design system calls for them.

---

# 73. Elevation

Do not use elevation decoratively.

Homepage sections should not float.

---

# 74. Buttons

Use existing button conventions.

Do not create:

```text
HeroButton
BrownButton
EditorialButton
HomepageButton
```

just to vary appearance.

---

# 75. Links

Use semantic links for navigation.

Do not style a `<div>` as a clickable card.

If an image/category/product navigates, ensure link semantics are correct.

---

# 76. Icons

Use:

```text
@mui/icons-material
```

only where an icon genuinely improves comprehension.

Do not decorate every section heading with icons.

---

# 77. No Custom Decorative SVG

Do not introduce random furniture outlines, swooshes, arrows, stars, sparkles, or ornamental SVGs.

Photography is the decoration.

---

# 78. Responsive Foundation

Phase 13.9 is authoritative.

Consume its:

```text
canonical breakpoints
container system
responsive gutters
CSS-first policy
overflow rules
```

Do not invent homepage-specific breakpoint values.

---

# 79. CSS-First Responsiveness

Homepage composition should primarily respond through:

```text
CSS
MUI theme breakpoints
```

Do not use:

```text
window.innerWidth
user-agent detection
device detection
```

---

# 80. Server Components by Default

Homepage should remain server-first.

Do not begin:

```tsx
"use client";
```

at the page root.

Interactive Client Components must be justified individually.

---

# 81. Expected Client JS

The homepage should require very little client JavaScript.

A mostly editorial/catalog-discovery homepage should be largely server-renderable.

---

# 82. No Carousel by Default

Do not automatically build:

```text
hero carousel
product carousel
category carousel
testimonial carousel
```

because ecommerce sites often use them.

Static, intentional composition is preferable.

---

# 83. Product Rail Decision

If horizontal product browsing on narrow screens materially improves the design, justify it explicitly.

Do not add a carousel dependency.

CSS overflow may be sufficient if the interaction is appropriate and accessible.

---

# 84. Mobile Homepage

Do NOT merely stack the desktop page.

Recompose intentionally.

Mobile should prioritize:

```text
hero
discovery
products
Made to Order
editorial story
```

with appropriate visual rhythm.

---

# 85. Hero Mobile Composition

Do not preserve a desktop text-overlay composition if it destroys readability on mobile.

It is acceptable for mobile to use:

```text
image
then text
```

while desktop uses:

```text
text over/alongside image
```

if the same design language is preserved.

---

# 86. Mobile Hero Height

Avoid:

```text
100vh hero
```

unless strongly justified.

Users should see evidence of further content/discovery.

---

# 87. Mobile Product Cards

Maintain the canonical 4:5 merchandising frame.

Do not shrink cards until names/prices become unreadable.

Actual grid behavior should remain restrained because Phase 14.3 will own listing grids.

---

# 88. Desktop Homepage

Desktop should exploit horizontal space for:

```text
editorial image scale
asymmetric compositions
curated product presentation
room/category discovery
```

without filling every available pixel.

---

# 89. Wide Desktop

Use the established container/full-bleed system.

Do not let textual content stretch indefinitely.

Photography may intentionally extend wider.

---

# 90. Horizontal Overflow

Must be zero across supported widths.

Do not fix overflow using:

```css
body {
  overflow-x: hidden;
}
```

Fix the responsible component.

---

# 91. Accessibility

Follow:

```text
frontend/design-system/ACCESSIBILITY.md
```

Homepage accessibility is part of implementation, not post-processing.

---

# 92. Heading Hierarchy

The homepage should contain one meaningful:

```html
<h1>
```

representing the page's primary message.

Section headings follow logically.

Do not use heading levels based purely on desired font size.

---

# 93. Hero Heading

The visual hero headline will likely be the homepage `<h1>`.

Ensure it is descriptive and useful.

Do not use the logo/business name alone as the `<h1>` if a stronger furniture-oriented page heading is appropriate.

---

# 94. Landmark Structure

Do not create another:

```html
<main>
```

The SiteShell already owns it.

Homepage content renders inside that main landmark.

---

# 95. Sections

Use semantic:

```html
<section>
```

where content has a meaningful thematic grouping.

Provide accessible section headings where appropriate.

---

# 96. Image Accessibility

Furniture/product imagery conveying content needs meaningful alt.

Lifestyle imagery that contributes no information beyond surrounding copy may be decorative.

Make this decision deliberately.

---

# 97. Links vs Buttons

Navigation:

```text
link
```

Action that changes local UI:

```text
button
```

Do not use buttons for ordinary route navigation.

---

# 98. Focus

All homepage interactions must use the approved focus treatment.

Do not suppress outlines.

---

# 99. Touch Targets

Maintain the accessibility target-size baseline.

Do not make editorial minimalism result in tiny text links.

---

# 100. Contrast

Verify text over imagery carefully.

Do not assume a photograph provides sufficient contrast.

If reliable contrast cannot be achieved across responsive crops, change the composition.

---

# 101. Reduced Motion

The homepage should work perfectly with reduced motion.

Do not introduce decorative entrance animation.

---

# 102. No Scroll-Reveal Animation

Do not add:

```text
fade-in on scroll
parallax
scroll-triggered scale
spring sections
```

during 14.1.

The visual identity should come from composition, typography and photography.

---

# 103. Loading

Consume the Phase 13.8 loading architecture.

Do not create another global loading system.

If homepage-specific asynchronous content is introduced, use appropriate local/server behavior.

---

# 104. Errors

Consume the Phase 13.8 failure architecture.

Do not create:

```text
HomepageError
```

for generic unexpected errors.

---

# 105. Homepage Data Strategy

Determine what homepage information can truthfully come from the existing API today.

Inspect the API contract before deciding.

Potentially:

```text
categories
selected products
product media
availability
price
product type
```

Do not assume endpoints.

---

# 106. Fixture vs API Decision

For every homepage data section, explicitly classify it:

```text
REAL API DATA
or
DEVELOPMENT FIXTURE
or
STATIC BRAND COPY
```

Do not mix them invisibly.

---

# 107. Real API Data

If an existing generic public API endpoint cleanly supports a homepage section, it may be consumed through the Phase 13.5 API client.

Do not create a special backend:

```text
/homepage
```

endpoint merely for Phase 14.1.

---

# 108. API Client

Use the existing:

```text
frontend/web/lib/api/client.ts
```

Do not:

```text
call fetch directly throughout homepage components
create another API client
use Axios
install React Query
install SWR
```

---

# 109. Server Data Fetching

Public homepage catalog data should normally be fetched server-side where appropriate.

Do not turn the homepage into a client-side SPA.

---

# 110. Cache Strategy

Use explicit Next.js/native-fetch caching behavior consistent with the API client architecture.

Do not invent an elaborate caching system.

Document any cache/revalidation decision.

---

# 111. API Unavailability

Do not silently replace production API failures with fixtures.

Fixtures are for explicit development/design data.

If a section is configured to use real API data and the API fails, follow the failure architecture.

---

# 112. Fixture Fallback Prohibition

Do NOT implement:

```text
try API
if error
show fake products
```

That hides production failures.

---

# 113. Product Price

If fixture products include prices, represent them using the same domain shape/conventions expected from the API.

Money remains integer minor units at API/domain boundaries.

Do not create floating-point production money logic.

---

# 114. Currency

Use authoritative currency conventions.

Do not invent:

```text
$
USD
```

for a TZS furniture business.

---

# 115. Price Formatting

If a reusable money formatter already exists, use it.

If not and the homepage genuinely needs one, establish one reusable formatter rather than formatting prices independently inside cards.

---

# 116. "Starting From"

MADE_TO_ORDER or variant products may require:

```text
From TZS ...
```

only if actual domain/API data supports that meaning.

Do not fabricate "from" pricing.

---

# 117. Product IDs

Never expose numeric database IDs.

Use public API identifiers internally where needed.

URLs use canonical slugs according to `ROUTING.md`.

---

# 118. Product Links

The product detail route:

```text
/products/[slug]
```

belongs to Phase 14.4.

Do not expose broken links during 14.1.

Design ProductCard so linking can be enabled cleanly when 14.4 exists.

---

# 119. Progressive Group N Integration

The intended sequence is:

```text
14.1 Homepage
        ↓
14.2 Category pages
        ↓
14.3 Product listing
        ↓
14.4 Product detail
```

Therefore 14.1 should establish reusable visual primitives without pretending downstream routes already exist.

---

# 120. ProductCard Future Compatibility

If introduced now, ProductCard must be capable of later use in:

```text
homepage
category page
product listing
search results
```

without forks.

But do not prematurely add props for every imaginable future feature.

---

# 121. ProductCard API

Prefer a small domain-oriented API.

Conceptually:

```text
product
or
name
media
price
availability/product type
href when available
```

Follow actual project TypeScript/domain conventions.

Do not expose:

```text
imageHeight
titleFontSize
cardPadding
borderRadius
shadow
```

as arbitrary styling props.

---

# 122. Category Discovery Future Compatibility

Likewise, category discovery components should consume category/media data rather than hard-coded image imports internally.

---

# 123. Homepage Fixtures

A small fixture dataset is sufficient.

Do not create 100 fake products.

Something like:

```text
6–10 product fixtures
4–8 category fixtures
1 hero fixture
2–3 editorial fixtures
```

is enough for visual development.

Use only as much as the final composition requires.

---

# 124. Fixture Naming

Use believable but clearly fictional/fixture product names.

Do not copy competitor product names.

Avoid trademarked collection names.

---

# 125. Fixture Copy

Keep fixture descriptions short.

Do not spend the phase inventing a fake product catalog.

---

# 126. Image Quality

Use high-quality coherent imagery.

Avoid:

```text
mixed photography styles
watermarks
logos from other retailers
visible competitor branding
poor AI artifacts
different white balances everywhere
```

---

# 127. Image Generation

If the coding/design environment can create development imagery, generated fixtures may be used.

They must:

```text
look coherent
contain furniture/interior subjects
avoid text/logos
avoid copying branded products
fit required aspect ratios
```

Do not make image generation part of runtime application code.

---

# 128. Fixture Licensing

If external images are used instead of generated/local-owned fixtures, use assets whose usage is appropriate for the project.

Do not scrape competitor images.

---

# 129. Hero LCP

Measure the hero/LCP behavior.

Do not merely assume `next/image` guarantees good performance.

Verify:

```text
correct dimensions
correct sizes
priority/preload only where appropriate
no giant unnecessary asset
stable geometry
```

---

# 130. CLS

Homepage must avoid obvious cumulative layout shift.

Reserve image geometry.

Do not allow images to determine layout height only after loading.

---

# 131. Font Stability

Use the already established font-loading architecture.

Do not reconfigure Young Serif from the homepage.

---

# 132. No Duplicate Font Import

Do not add another Google Fonts `<link>` or CSS `@import`.

---

# 133. Performance Budget Mindset

Avoid:

```text
large client JS
carousel libraries
animation libraries
huge image fixtures
dozens of eager images
```

The homepage should remain fast.

---

# 134. Above-the-Fold Restraint

Do not eagerly load the entire homepage.

Only the genuinely important initial visual media should receive high loading priority.

---

# 135. Semantic Product Content

If product fixtures are rendered, use meaningful markup.

Do not turn the entire product card into meaningless nested `<div>` elements.

---

# 136. SEO Boundary

Phase 14.7 owns the comprehensive SEO metadata strategy.

However, Phase 14.1 should produce semantic page content suitable for later metadata work.

Do not implement the entire SEO phase now.

---

# 137. Structured Data Boundary

Do NOT add:

```text
Organization JSON-LD
Product JSON-LD
ItemList JSON-LD
WebSite JSON-LD
```

during 14.1.

Phase 14.8 owns structured data.

---

# 138. Sitemap / Robots

Do not modify:

```text
sitemap
robots
```

Phase 14.9 owns them.

---

# 139. Internal Linking Boundary

Natural homepage links are fine.

Do not implement the comprehensive SEO internal-linking strategy.

Phase 14.10 owns it.

---

# 140. Image Optimization Boundary

Use `next/image` correctly now.

But comprehensive:

```text
media CDN strategy
R2 delivery
responsive media policy
performance tuning
format policy
quality policy
```

belongs to Phase 14.11.

Do not consume 14.11 prematurely.

---

# 141. Urban Ladder Structural Review

Before finalizing, explicitly compare the homepage against the structural lessons from Urban Ladder.

Report:

```text
Structural concepts adopted:
<list>

Visual implementation copied:
NONE

Exact section design copied:
NONE

Promotional mechanics copied:
NONE

Competitor imagery copied:
NONE

Competitor copy copied:
NONE
```

---

# 142. Anti-AI-Slop Audit

Explicitly inspect for:

```text
excessive rounded cards
pill overload
random badges
decorative gradients
glassmorphism
floating panels
large decorative shadows
random icons
centered-everything composition
generic marketing copy
fake statistics
fake testimonials
overuse of brand brown
unnecessary animation
generic three-feature icon row
generic SaaS hero
```

Remove them.

---

# 143. Visual Coherence Audit

Ask:

```text
Does this look like a furniture store?

Does it look like SL Furnitures?

Does furniture photography dominate appropriately?

Does it feel calm?

Does it feel crafted?

Is the page editorial without sacrificing commerce?

Is Made to Order visible as a core offering?

Is discovery obvious?

Does each section earn its place?

Could a section be removed without losing meaning?
```

---

# 144. Section Necessity

Every homepage section must answer one of:

```text
What do you sell?
How do I discover it?
Why should I care?
What can I request?
What should I explore next?
```

If it answers none, remove it.

---

# 145. No Filler Sections

Do not add sections merely to make the homepage longer.

Homepage length should emerge from useful content.

---

# 146. Visual Verification

Run the actual website and inspect at the canonical Phase 13.9 viewport matrix.

At minimum verify:

```text
narrow mobile
mobile
tablet
narrow desktop
desktop
wide desktop
```

---

# 147. Hero Verification

Check:

```text
image crop
headline wrapping
contrast
CTA placement
LCP geometry
mobile composition
```

---

# 148. Category Verification

Check:

```text
image consistency
labels
responsive composition
no awkward card grid
no clipped content
```

---

# 149. Product Verification

Check:

```text
4:5 frames
furniture not cropped badly
name wrapping
price formatting
MADE_TO_ORDER presentation
consistent alignment
```

---

# 150. Editorial Verification

Check:

```text
image/copy balance
reading measure
mobile order
surface transition
```

---

# 151. Footer Transition

Ensure the final homepage section transitions naturally into the existing site footer.

Do not duplicate footer content inside the homepage.

---

# 152. Horizontal Overflow

Verify:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

across representative widths.

No global overflow hiding.

---

# 153. Zoom/Reflow

Test meaningful zoom/reflow.

Homepage must remain usable at 200% browser zoom or equivalent accessibility reflow.

---

# 154. Keyboard

Keyboard through all interactive homepage elements.

Verify logical order.

---

# 155. Focus

Verify visible focus is never clipped by images/containers.

---

# 156. Reduced Motion

Verify homepage remains fully usable with reduced motion.

Expected decorative animation:

```text
NONE
```

---

# 157. Image Loading Verification

Inspect rendered image requests/behavior.

Verify:

```text
hero not unnecessarily oversized
product images not all eager
meaningful sizes
stable dimensions
no obvious duplicate huge downloads
```

---

# 158. Automated Tests

Add focused tests for homepage architecture.

Potential coverage:

```text
one h1
no second main landmark
canonical layout primitives used
no cart/checkout/payment controls
no fake href="#"
no unauthorized icon library
no raw <img> for homepage content media
ProductCard contract if introduced
MADE_TO_ORDER not error styled
fixture data isolated
```

Use existing test infrastructure.

---

# 159. Frozen Token Audit

Add or run an explicit audit proving homepage code did NOT introduce a second visual authority.

Check for:

```text
raw hex colors
unapproved px spacing
unapproved font sizes
custom box shadows
custom radius values
raw width breakpoints
custom motion durations
```

Report exceptions individually.

The expected result is:

```text
Unapproved ad-hoc visual values: NONE
```

---

# 160. Component Reinvention Audit

List every new component introduced.

For each report:

```text
Component:
<name>

Responsibility:
<why it exists>

Existing component/MUI primitive considered:
<what was checked>

Why composition alone was insufficient:
<reason>
```

If there is no convincing answer, remove the new component.

---

# 161. Existing Foundation Regression

Run:

```text
responsive contract
breakpoint synchronization
API client tests
layout contract
state contract
routing contract
request-first regression
theme contract
design-system validation
```

All must remain passing.

---

# 162. TypeScript

Run the actual repository typecheck.

Must pass.

---

# 163. ESLint

Run the actual lint command.

Must pass.

---

# 164. Production Build

Run the actual production build command.

Must pass.

---

# 165. `git diff --check`

Must pass.

---

# 166. Dependencies

Expected:

```text
NONE
```

Do not install:

```text
carousel library
animation library
image library
CSS framework
icon library
data-fetching library
```

---

# 167. Documentation

Update:

```text
phases/group-N-phases.md
```

with the Phase 14.1 execution record.

If Group N documentation does not yet exist, inspect roadmap conventions before creating it.

Update `frontend/AGENTS.md` only if Phase 14.1 establishes a genuinely durable implementation rule not already documented.

---

# 168. ADR

Do not create an ADR simply because this is an important visual page.

Add one only if a durable architectural decision is made.

Most homepage design decisions belong in the phase record/design-system documentation, not architecture decision records.

---

# 169. Git

Follow:

```text
git-workflow-and-versioning
```

before any Git operation.

Use an atomic Phase 14.1 commit.

Do not push unless the skill/user workflow requires it.

---

# 170. Completion Report

Return:

```text
Phase 14.1 status:
PASS / BLOCKED


HOMEPAGE

Route:
/

Page:
<path>

Server Component:
YES / NO

SiteShell reused:
YES / NO

Second main landmark:
NONE / FAIL

Homepage h1:
<text>


SECTION ARCHITECTURE

Sections in rendered order:
1. <...>
2. <...>
3. <...>
...

Each section has defined purpose:
PASS / FAIL

Filler sections:
NONE / <list>


DESIGN SYSTEM

Frozen token authority used:
YES / NO

New token authority:
NONE / FAIL

Raw hex colors:
NONE / <list>

Unapproved spacing values:
NONE / <list>

Unapproved typography values:
NONE / <list>

Unapproved radius:
NONE / <list>

Unapproved shadows:
NONE / <list>

Unapproved breakpoints:
NONE / <list>

Unapproved motion:
NONE / <list>

MUI theme consumed:
YES / NO


COMPONENT REUSE

Existing primitives reused:
<list>

New components:
<list>

For each new component:
<responsibility + reuse justification>

Duplicate components:
NONE / FAIL

Homepage-specific Button:
NONE / FAIL

Homepage-specific Container:
NONE / FAIL

Homepage-specific typography system:
NONE / FAIL


URBAN LADDER REFERENCE

Used as:
STRUCTURAL / IA REFERENCE ONLY

Structural concepts adopted:
<list>

Visual implementation copied:
NONE / FAIL

Exact section design copied:
NONE / FAIL

Competitor imagery copied:
NONE / FAIL

Competitor copy copied:
NONE / FAIL

Promotional mechanics copied:
NONE / FAIL


HERO

Composition:
<summary>

Hero image:
<fixture>

Source dimensions:
<dimensions>

Rendered behavior:
<summary>

next/image:
YES / NO

Meaningful sizes:
YES / NO

LCP treatment:
<summary>

Mobile composition:
<summary>

Hero CTA:
<destination / deferred>

Dead CTA:
NONE / FAIL


CATEGORY DISCOVERY

Implementation:
<summary>

Data:
REAL API / FIXTURE / STATIC

Taxonomy authority:
<source>

Fixture ratio:
<ratio>

Broken category links:
NONE / FAIL

Category pages implemented:
NO


PRODUCT MERCHANDISING

Implementation:
<summary>

ProductCard introduced:
YES / NO

Canonical reusable ProductCard:
YES / N/A

Product fixtures:
<count>

Product image ratio:
4:5 / <reason for alternative>

Product source dimensions:
<summary>

next/image:
YES / NO

Hard-coded fixture inside ProductCard:
NO / FAIL

MADE_TO_ORDER presentation:
<summary>

Cart:
NONE

Buy Now:
NONE

Wishlist:
NONE

Ratings:
NONE

Fake sale:
NONE


EDITORIAL

Editorial sections:
<list>

Image ratios:
<list>

Purpose:
<summary>

Generic marketing filler:
NONE / FAIL


MADE TO ORDER

Prominence:
<summary>

Treated as core offering:
YES / NO

Treated as error/unavailable:
NO / FAIL

Request destination:
<route / deferred>


IMAGES

Development fixtures:
<list/count>

Fixture location:
<path>

Production R2 integration:
NO

next/image used:
YES / NO

Raw img:
NONE / <justification>

Hero ratio:
<ratio>

Category ratio:
<ratio>

Product ratio:
<ratio>

Editorial ratio:
<ratio>

Meaningful alt:
PASS / FAIL

Stable geometry:
PASS / FAIL

Responsive sizes:
PASS / FAIL

All images eager:
NO / FAIL

Competitor assets:
NONE / FAIL

Watermarked assets:
NONE / FAIL


DATA

Real API sections:
<list>

Fixture sections:
<list>

Static-copy sections:
<list>

API client reused:
YES / N/A

Direct fetch outside client:
NONE / FAIL

Fixture fallback on API error:
NONE / FAIL

Fake backend endpoint:
NONE / FAIL


RESPONSIVE

Canonical breakpoints:
PASS / FAIL

CSS-first:
YES / NO

window.innerWidth:
NONE / FAIL

Narrow mobile:
PASS / FAIL

Mobile:
PASS / FAIL

Tablet:
PASS / FAIL

Narrow desktop:
PASS / FAIL

Desktop:
PASS / FAIL

Wide desktop:
PASS / FAIL

200% zoom/reflow:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


ACCESSIBILITY

One h1:
PASS / FAIL

Heading hierarchy:
PASS / FAIL

Image alt:
PASS / FAIL

Link/button semantics:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Contrast:
PASS / FAIL

Reduced motion:
PASS / FAIL


ANTI-AI-SLOP

Excessive rounded cards:
NONE / FAIL

Pill overload:
NONE / FAIL

Decorative gradients:
NONE / FAIL

Glassmorphism:
NONE / FAIL

Decorative shadows:
NONE / FAIL

Random icons:
NONE / FAIL

Generic SaaS hero:
NONE / FAIL

Fake metrics:
NONE / FAIL

Fake testimonials:
NONE / FAIL

Generic luxury copy:
NONE / FAIL

Brand-brown overuse:
NONE / FAIL

Decorative animation:
NONE / FAIL


PERFORMANCE

Hero/LCP:
PASS / FAIL

CLS:
PASS / FAIL

Image loading:
PASS / FAIL

Client JS kept minimal:
PASS / FAIL

Carousel dependency:
NONE

Animation dependency:
NONE


SCOPE

Category pages implemented:
NO

Product listing implemented:
NO

Product detail implemented:
NO

Search implemented:
NO

Filters/sorting implemented:
NO

Comprehensive SEO implemented:
NO

Structured data implemented:
NO

Sitemap/robots implemented:
NO

R2 integration implemented:
NO

Backend changed:
NO

Flutter changed:
NO

Dependencies added:
NONE / <list>


VALIDATION

Homepage contract:
PASS / FAIL

Frozen token audit:
PASS / FAIL

Component reinvention audit:
PASS / FAIL

Responsive contract:
PASS / FAIL

Breakpoint synchronization:
PASS / FAIL

API client regression:
PASS / FAIL

Layout contract:
PASS / FAIL

State contract:
PASS / FAIL

Routing contract:
PASS / FAIL

Request-first regression:
PASS / FAIL

Theme contract:
PASS / FAIL

Design-system validation:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


FILES CHANGED

<list>


GIT

git-workflow-and-versioning skill read:
YES / NO

Operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 14.1:
PASS / BLOCKED

Phase 14.2:
READY / BLOCKED
```

---

# 171. STOP Condition

Phase 14.1 may be declared PASS only when:

- `/` is a polished, coherent furniture storefront homepage;
- it consumes the existing SiteShell;
- it does not introduce another `<main>`;
- it has one meaningful `<h1>`;
- the homepage has a deliberate editorial-commerce content sequence;
- every section has a clear customer purpose;
- there are no filler sections;
- the **frozen design-system tokens are explicitly used**;
- no second token/design authority exists;
- no unapproved colors, spacing, typography, radius, elevation, breakpoints or motion values were invented;
- existing components/primitives were inspected before any new component was created;
- every new component has a legitimate reusable responsibility;
- no duplicate Button/Container/Typography/layout primitive was created;
- Urban Ladder was used **only as structural/IA reference**;
- no Urban Ladder visual design, imagery, wording, source code, promotional mechanics or exact section implementation was copied;
- the resulting page clearly looks like SL Furnitures;
- furniture photography carries the majority of visual richness;
- hero composition is strong at desktop and mobile;
- category discovery is clear without implementing Phase 14.2;
- product merchandising is restrained;
- any introduced ProductCard is canonical and reusable;
- product imagery uses a coherent merchandising frame, preferably 4:5;
- components receive media as data rather than hard-coding fixture assets internally;
- `next/image` is used correctly;
- meaningful `sizes` are provided;
- image geometry is stable;
- local fixtures are clearly development fixtures;
- no R2 infrastructure was introduced;
- no competitor images/watermarked assets are used;
- MADE_TO_ORDER is visibly a core offering;
- no cart/checkout/buy-now/wishlist/reviews/sale mechanics were invented;
- no fake statistics/testimonials/business claims exist;
- no dead `href="#"` links exist;
- server-first rendering is preserved;
- client JS remains minimal;
- no carousel or animation dependency was added;
- canonical responsive foundation is consumed rather than replaced;
- narrow mobile through wide desktop pass visual inspection;
- 200% zoom/reflow passes;
- no horizontal overflow exists;
- accessibility checks pass;
- reduced-motion behavior passes;
- hero/LCP behavior is reasonable;
- obvious CLS is absent;
- comprehensive SEO/structured-data/sitemap work remains in its owning phases;
- no backend or Flutter changes occurred;
- no dependencies were added;
- foundation regression tests pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 14.1 — PASS
Phase 14.2 — READY
```

Do not start Phase 14.2 automatically.

**The frozen design system is the authority. Do not redesign it from the homepage. Do not reinvent existing components. Urban Ladder is a furniture-commerce structural/IA reference only—not a design to copy and paste.**

---

# Phase 14.1 Execution Record — 2026-10-07

## Result

```text
Phase 14.1 — PASS
Phase 14.2 — READY
```

## Homepage

- Route: `/`
- Page: `frontend/web/app/page.tsx`
- Rendering: server-first, dynamically rendered so public catalog failures reach the existing route error boundary instead of failing the production build
- Site shell: reused; no second `main`
- H1: `Furniture for the way you live.`
- Sections: furniture hero, room discovery, selected made-to-order furniture, made-to-order editorial feature
- Filler sections: none

## Data

- Production/default data: existing `GET /api/v1/categories` and `GET /api/v1/products` through `lib/api/client.ts`
- Cache policy: explicit `no-store` for current public catalog reads
- Development visual data: enabled only by `HOMEPAGE_DATA_SOURCE=fixtures`
- Fixture fallback after an API error: none
- Fixture notice: rendered on the page whenever fixture mode is active
- Production remote media: `CATALOG_MEDIA_BASE_URL` configures the allow-listed Next.js image origin; no R2 infrastructure was added
- API or schema changes: none

## Components

- `ProductCard`: canonical source-agnostic public commerce card for homepage, category, listing and search reuse. It owns product image, name, contract-safe TZS price formatting, and product state. It has no homepage appearance variant and does not import fixtures.
- `CatalogDiscovery`: owns the homepage's category/product composition and uses the canonical `ProductCard`.
- Existing primitives reused: `SiteShell`, `SiteSection`, `ContentContainer`, `NavLink`, MUI `Box`, `Stack`, `Typography`, `Button`, and Next.js `Image`.
- Duplicate Button, Container, Typography, ProductCard or homepage-specific card: none.

## Visual Decisions

- Urban Ladder was reviewed only for the proven hierarchy of hero → category discovery → merchandising → editorial/service story.
- Visual implementation, exact sections, promotional mechanics, imagery and copy copied: none.
- The hero is an original asymmetric SL Furnitures composition with text adjacent to photography, avoiding image-overlay contrast risk.
- Mobile recomposes to image then text and keeps the next discovery section visible without a viewport-height hero.
- The frozen `--media-product-card` token is `4 / 3`; Phase 14.1 retains that authority instead of silently introducing a 4:5 value. Portrait fixture photographs use `object-fit: contain` so furniture is not cropped.
- Hero source: `1376 × 768`; category sources: approximately `4:3`; product sources: `1200 × 1500`; editorial source: `1200 × 896`.
- Young Serif is loaded locally through the existing framework font architecture with its OFL license; the frozen `--font-display` semantic remains the component-facing authority.

## Routes And Request-First Scope

- Active homepage action: `#rooms` only.
- Category and product cards render as non-interactive semantic content until Phases 14.2 and 14.4 implement their routes.
- Furniture request action is deferred until `/furniture-requests` is implemented.
- Cart, checkout, payment, buy-now, wishlist, ratings, reviews, sales and discount UI: none.
- Category pages, product listing, product detail, search, structured data, sitemap/robots and R2 integration: not implemented.

## Accessibility And Performance

- One H1, one shell-owned main landmark, logical section headings and native link/button semantics.
- Meaningful image alternatives, stable aspect-ratio geometry and meaningful placement-specific `sizes`.
- Hero is the only preloaded content image; other content images remain lazy.
- CSS/MUI breakpoint composition only; no viewport or user-agent rendering branches.
- Keyboard order, visible focus, reduced motion, 200% equivalent reflow and horizontal overflow checked in Chrome.
- Browser console errors, failed requests and unapproved decorative motion: none.

## Audits

- Unapproved raw colors, spacing, typography, radius, shadow, breakpoints or motion in homepage/catalog components: none.
- New design/token authority: none.
- Excessive cards, pills, badges, gradients, glassmorphism, decorative shadows/icons, fake metrics/testimonials/claims and generic luxury copy: none.
- Competitor assets, copy and watermarks: none.
- Dependencies added: none.
- Backend and Flutter changes: none.

## Verification

- Homepage contract tests: pass
- Theme, API client, layout, responsive and state contracts: pass
- TypeScript: pass
- ESLint: pass
- Production build: pass
- `git diff --check`: pass
- Browser viewports: `320`, `390`, `640`, `959`, `960`, `961`, `1024`, `1440`, `1728`, and 200% equivalent reflow: pass
- Browser evidence: one H1, one main, no horizontal overflow, no console/network errors, stable image geometry, and a route-loading minimum height that keeps the footer from shifting through the viewport

## Git

- `git-workflow-and-versioning` skill read: yes
- Commit: not created; the project workflow requires explicit user authorization before committing
- Push: none
