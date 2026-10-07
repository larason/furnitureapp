# Phase 14.3 — Public Product Listing

## Entry State

```text
Phase 14.1 — PASS
Phase 14.2A — PASS
Phase 14.2 — PASS
Phase 14.3 — ACTIVE
```

Implement the canonical public product listing experience for SL Furnitures.

This phase owns the general product collection route and the reusable listing architecture that later search/filter/sort work will extend.

Do NOT start Phase 14.4 or later work.

---

# 1. Objective

Implement the canonical public product listing route established by `ROUTING.md`, expected to be:

```text
/products
```

Verify the actual frozen route before coding.

The listing must allow a visitor to browse the public furniture catalog while preserving the established SL Furnitures character:

```text
architectural
warm
editorial
calm
crafted
photography-led
spacious
commerce-oriented
```

It must NOT become:

```text
dense marketplace UI
generic Shopify template
Urban Ladder clone
Amazon-style results page
filter-heavy dashboard
sale/deal wall
```

The page should answer:

```text
What furniture is available to explore?
What kind of piece is this?
What does it cost?
Is it ready-stock or made-to-order?
How can I continue browsing the catalog?
```

---

# 2. Read Before Coding

Read and follow:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
  DESIGN.md
  USAGE.md
  COMPONENTS.md
  ACCESSIBILITY.md
  tokens.css
  design-tokens.json

frontend/web/
  ROUTING.mdProduct listing
  RESPONSIVE.md
  app/
  components/
  lib/api/
  theme/

docs/api/
  api-contract.md
  api-conventions.md
  api-resources.md
  openapi.yaml

docs/domain/business-rules.md
docs/decisions.md

phases/group-N-phases.md
```

Inspect the actual implementations produced by:

```text
13.5 API client
13.7 layout system
13.8 failure states
13.9 responsive foundation
14.1 homepage
14.2 category pages
14.2A hard-404 remediation
```

Do not implement from assumptions.

---

# 3. Git Policy

Before ANY Git command:

```text
locate/read/follow:
git-workflow-and-versioning
```

Preserve unrelated owner changes.

Never commit:

```text
.env
.env.local
credentials
secrets
```

Use an atomic Phase 14.3 commit.

---

# 4. Frozen Design System — NON-NEGOTIABLE

The dependency remains:

```text
Frozen tokens
    ↓
MUI theme
    ↓
existing primitives
    ↓
canonical commerce components
    ↓
product listing
```

The listing page is NOT allowed to become another visual authority.

---

# 5. Repository Authority Wins

If this phase prompt suggests something conflicting with:

```text
DESIGN.md
USAGE.md
COMPONENTS.md
ACCESSIBILITY.md
tokens.css
RESPONSIVE.md
ROUTING.md
```

the frozen repository authority wins.

Report the conflict.

Do not silently change the design system.

---

# 6. Product Media Ratio

Phase 14.1 established that the actual frozen authority is:

```text
--media-product-card: 4 / 3
```

Continue using it.

Do NOT introduce:

```text
4:5
1:1
listing-specific ratio
```

for the canonical ProductCard merely because listing pages elsewhere use them.

---

# 7. No New Visual Scale

Do not invent:

```text
colors
spacing
font sizes
line heights
radii
shadows
elevations
breakpoints
container widths
motion values
focus styles
media ratios
```

Expected:

```text
Unapproved ad-hoc visual values: NONE
```

---

# 8. No Component Reinvention

Before creating ANY component, inspect existing project and MUI primitives.

Most importantly:

```text
ProductCard
```

already exists and is canonical.

Reuse it.

Forbidden:

```text
ListingProductCard
CatalogProductCard
ProductsPageCard
ProductTile
ShopProductCard
GridProductCard
```

---

# 9. ProductCard Cross-Page Contract

Phase 14.3 is another important reuse test for ProductCard.

The same component should now serve:

```text
homepage
category pages
/products
future search results
```

without page-specific visual forks.

If a real reusable domain capability is missing, improve the canonical component.

Do not create styling variants merely to make `/products` look different.

---

# 10. ProductCard Modification Gate

If ProductCard is changed, report:

```text
Missing capability:
<what>

Why it is domain/reusable:
<why>

Homepage impact:
<none/details>

Category-page impact:
<none/details>

Why a new card was not created:
<reason>
```

---

# 11. Existing Layout Primitives

Reuse:

```text
SiteShell
SiteSection
ContentContainer
NavLink
```

and established MUI primitives.

Do not create:

```text
ProductsContainer
ListingSection
CatalogButton
ListingTypography
```

where existing primitives suffice.

---

# 12. Canonical Route

Verify `ROUTING.md`, then implement the approved route.

Expected:

```text
/products
```

Do not introduce aliases such as:

```text
/shop
/catalog
/furniture
/store
/all-products
```

---

# 13. Server-First

The listing page must remain server-first.

Expected page root:

```text
Server Component
```

Do NOT put:

```tsx
"use client";
```

on `/products` simply because later filters may become interactive.

Phase 14.6 owns that interaction.

---

# 14. Public API

Use the existing public products endpoint.

Expected contract:

```text
GET /api/v1/products
```

but verify it from the frozen API documentation and implementation.

Do not guess.

---

# 15. API Client

Use:

```text
frontend/web/lib/api/client.ts
```

Do not introduce:

```text
Axios
SWR
React Query
another fetch wrapper
Next.js API proxy
```

A thin catalog query/helper layer is acceptable if it delegates transport to the canonical API client.

---

# 16. Laravel Remains Authority

Product data comes from Laravel.

The frontend must not become authority for:

```text
product identity
slug
price
availability
product type
category membership
media
inventory
```

---

# 17. Product Collection Contract

Inspect and document the actual frozen collection contract, including:

```text
pagination
default page size
maximum page size
sort defaults
product_type
availability
category
min_price
max_price
sort
sort_direction
```

where supported.

Do NOT expose all of those controls yet.

Phase 14.6 owns filters and sorting.

---

# 18. Phase 14.3 Query Ownership

Phase 14.3 owns only query state genuinely required for basic collection navigation.

At minimum this may include:

```text
page
```

if the API is paginated.

Do not prematurely activate:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
```

as public listing controls.

---

# 19. Canonical Listing URL

Initial catalog:

```text
/products
```

should remain clean.

Avoid unnecessary:

```text
/products?page=1
```

for the first page where possible.

Subsequent pages may use the canonical query convention:

```text
/products?page=2
```

if confirmed by routing/API architecture.

---

# 20. Search Params

Use the installed Next.js 16.3.8 App Router conventions.

Do not copy outdated synchronous `searchParams` examples.

Verify installed framework behavior.

---

# 21. Page Validation

Safely parse page input.

Handle:

```text
missing page
page=1
page=2
page=0
negative values
non-integer values
huge values
duplicate values
```

deterministically.

Do not crash.

---

# 22. Do Not Invent Semantics

Before deciding what invalid/out-of-range pages do, inspect:

```text
API behavior
ROUTING.md
api-conventions.md
existing pagination conventions
```

Do not silently invent redirects or 404 semantics.

If the frozen contract does not resolve an important case, document the ambiguity and use the smallest defensible behavior.

---

# 23. Pagination Is Part of 14.3

Unlike 14.2, the generic product listing needs a complete **basic server-first pagination experience**.

Implement pagination sufficient for normal catalog browsing.

Do NOT defer all pagination to 14.6.

---

# 24. Pagination Must Be Server-First

Prefer ordinary links:

```text
/products?page=2
/products?page=3
```

rather than client state.

No JavaScript is necessary for normal pagination.

---

# 25. Pagination Component

Before creating a new component, inspect:

```text
MUI Pagination
existing project primitives
```

However, ensure whatever is used produces crawlable/navigable link semantics appropriate for App Router navigation.

Do not introduce a client-only pagination system just because MUI provides one.

---

# 26. Pagination Accessibility

Pagination should have:

```text
navigation landmark
accessible label
current-page semantics
usable previous/next controls
keyboard accessibility
visible focus
```

Do not rely only on visual color to identify the current page.

---

# 27. Pagination Density

Do not render dozens or hundreds of page links.

Use a restrained strategy appropriate to the existing pagination metadata.

---

# 28. Previous / Next

Previous and next navigation should not create invalid links.

On page 1:

```text
Previous
```

must not link to page 0.

On the final page:

```text
Next
```

must not link beyond the valid collection if total-page metadata is authoritative.

---

# 29. Page 1 URL

Where practical:

```text
Previous from page 2
→ /products
```

rather than:

```text
/products?page=1
```

to preserve a clean canonical initial URL.

Do not implement comprehensive canonical metadata yet; that belongs to 14.7.

---

# 30. No Infinite Scroll

Do NOT implement infinite scrolling.

---

# 31. No Load-More Client State

Do NOT implement client-side "Load more."

Basic server navigation is preferable for:

```text
accessibility
shareability
crawlability
predictability
server-first architecture
```

---

# 32. Listing Header

The page needs a restrained catalog introduction.

Possible content:

```text
H1: Furniture
```

or another truthful catalog heading based on project language.

Do not invent promotional marketing copy.

---

# 33. H1

Exactly one meaningful H1.

It should describe the product collection.

Do not use:

```text
Shop Now
Our Products
Discover Luxury
```

without considering actual project terminology.

Prefer concrete furniture language.

---

# 34. Supporting Copy

If supporting text is used, keep it factual and restrained.

Do not generate generic AI copy such as:

```text
Discover timeless pieces designed to elevate every corner of your home.
```

unless that language is explicitly approved brand copy.

The page does not need marketing prose to function.

---

# 35. Breadcrumb

A simple breadcrumb may be appropriate:

```text
Home / Furniture
```

if consistent with the category-page breadcrumb architecture.

Reuse the existing breadcrumb implementation if Phase 14.2 established one.

Do not create a second breadcrumb component.

---

# 36. Product Count

If pagination metadata exposes an authoritative total, a restrained factual count may be displayed.

Example concept:

```text
42 pieces
```

Do not fabricate counts.

---

# 37. Count Is Not a Badge

Do not wrap the count in a decorative pill/badge simply because it is metadata.

---

# 38. Listing Composition

Recommended:

```text
Breadcrumb / context
        ↓
Listing heading
        ↓
Optional factual total
        ↓
Product grid
        ↓
Pagination
```

Keep it straightforward.

This page's primary purpose is browsing furniture.

---

# 39. Product Grid

Phase 14.3 now owns the canonical public product-grid behavior.

Establish one reusable layout approach.

Do NOT create multiple grid implementations for:

```text
/products
category
future search
```

if they share the same collection responsibility.

---

# 40. Reuse Existing Grid First

If Phase 14.2 already introduced a simple reusable product-grid primitive:

```text
inspect it
verify it
reuse it
```

Do not replace it merely because 14.3 now owns listing.

---

# 41. If No ProductGrid Exists

First determine whether straightforward composition using:

```text
MUI Box
CSS Grid
canonical breakpoints
canonical spacing
```

is sufficient.

Only create a `ProductGrid` abstraction if it represents a stable reusable responsibility.

---

# 42. No CategoryProductGrid Fork

If Phase 14.2 created something category-specific that is actually generic collection layout, reconcile it into one canonical abstraction rather than duplicating it.

Do not break 14.2.

---

# 43. Grid Columns

Determine columns from:

```text
available container width
ProductCard readable width
canonical breakpoints
existing design tokens
```

Do not copy Urban Ladder's exact column counts.

Do not invent raw breakpoints.

---

# 44. Grid Quality

Avoid:

```text
cards becoming excessively narrow
huge gaps
tiny product names
price wrapping awkwardly
enormous empty desktop margins
marketplace density
```

Furniture photography should remain visually substantial.

---

# 45. ProductCard Media

Use the frozen:

```text
--media-product-card: 4 / 3
```

through existing ProductCard/theme infrastructure.

Do not hard-code the ratio again in the listing page.

---

# 46. Product Images

ProductCard owns product image rendering.

Do not duplicate `next/image` logic in `/products`.

---

# 47. Image `sizes`

Phase 14.3 must verify ProductCard's responsive `sizes` accurately reflect the canonical listing grid.

If Phase 14.1's homepage-specific sizes are insufficient, improve ProductCard/media API in a reusable way.

Do NOT:

```text
hard-code /products detection inside ProductCard
```

---

# 48. Image Source

Production product media comes from Laravel/API.

Do not use local fixture images as production fallback.

---

# 49. Missing Product Media

Use the already-established product-media fallback behavior if one exists.

If none exists and real products may lack media, establish a restrained reusable media-empty treatment.

Do not use:

```text
random stock photo
generated furniture image
competitor image
```

as fallback.

---

# 50. Missing Media Is Not Error

A product without an image should not crash the entire listing.

The card should preserve stable geometry.

---

# 51. Product Detail Route Boundary

Phase 14.4 owns:

```text
/products/[slug]
```

Do not implement the detail page now.

---

# 52. ProductCard Links

Do not activate product links to an unimplemented route.

No dead navigation.

Design the card contract so Phase 14.4 can activate canonical slug links without rewriting the component.

---

# 53. Slugs

When Phase 14.4 activates product links, they will use backend-returned slugs.

Do not generate slugs now.

Do not expose numeric database IDs.

---

# 54. Product Information

Keep cards restrained according to the frozen component conventions.

Expected concepts:

```text
image
name
price / contract-supported price presentation
availability or product type where useful
```

No merchandising clutter.

---

# 55. MADE_TO_ORDER

MADE_TO_ORDER remains first-class.

Do not visually demote it as:

```text
error
warning
unavailable
disabled
```

---

# 56. IN_STOCK

Use actual domain terminology and availability semantics.

Do not infer stock from product type alone unless the API explicitly defines that relationship.

---

# 57. Price Formatting

Reuse the Phase 14.1 formatter.

Do not create:

```text
listingPriceFormatter
```

---

# 58. Money

Preserve integer minor units at API/domain boundaries.

No floating-point money arithmetic.

---

# 59. No Ratings

Do not add:

```text
stars
review count
rating
```

---

# 60. No Wishlist

Do not add wishlist/favourite controls.

---

# 61. No Cart

Request-first policy remains authoritative.

Do not add:

```text
Add to cart
Buy now
Quick add
Cart
Checkout
```

---

# 62. No Quick View

Do not add a quick-view modal.

Product detail belongs to 14.4.

---

# 63. No Compare

Do not invent product comparison.

---

# 64. No Sales UI

No:

```text
sale badges
discount percentages
old prices
coupon messaging
flash-sale labels
```

unless the backend/business model explicitly establishes such functionality in a later phase.

---

# 65. Filters Boundary

Phase 14.6 owns filters.

Do NOT implement:

```text
category filter
room filter
price filter
availability filter
product type filter
material filter
style filter
filter drawer
filter chips
```

now.

---

# 66. Sorting Boundary

Phase 14.6 owns sorting.

Do NOT add:

```text
Newest
Price low-high
Price high-low
Featured
Popularity
```

controls.

Even if the API already supports sort parameters.

---

# 67. Search Boundary

Phase 14.5 owns search.

Do not add an inline search field to `/products`.

The existing shell search affordance remains unchanged.

---

# 68. URL State Boundary

Do not build a generic filter URL-state framework during 14.3.

Only basic listing pagination query state belongs here.

---

# 69. Category Boundary

Do not redesign category pages.

If a canonical ProductGrid established in this phase can safely replace duplicated category layout, a narrowly scoped reconciliation is acceptable.

Document it.

Do not otherwise refactor 14.2.

---

# 70. Empty Catalog

An existing product collection with zero products is a valid page.

Required:

```text
GET /products
→ HTTP 200
→ factual empty catalog state
```

This is particularly important because the current local Laravel catalog is empty.

---

# 71. Current Local Database

Current verified state:

```text
categories total: 0
products total: 0
```

Therefore `/products` provides an excellent real runtime empty-state test.

Do NOT seed data merely to make the listing visually full.

---

# 72. Empty State

Use the established state/component conventions.

The empty catalog should be calm and factual.

Concept:

```text
No furniture is listed yet.
```

Use wording appropriate to the project.

Do not claim:

```text
Sold out
Restocking soon
Coming soon
```

without authoritative data.

---

# 73. Empty State Must Not Be 404

Required:

```text
/products with zero products
→ HTTP 200
```

Do not call `notFound()` because the collection is empty.

---

# 74. Fixture Mode

If visual verification of a populated listing genuinely requires fixtures, reuse the established explicit fixture architecture rather than creating another one.

Any fixture mode must remain:

```text
explicit
development-only
visibly identifiable
never fallback-on-error
```

Do not create a hidden `/products` fixture fallback.

---

# 75. Prefer Real Empty-State Runtime Evidence

Because the actual local database is empty, use real API-backed mode to prove the empty-state semantics.

Fixture mode may separately help visual grid verification if already supported.

Keep the two evidence types clearly distinguished.

---

# 76. API Failure

Unexpected API failure:

```text
must use established error architecture
```

Do not turn API failure into empty catalog.

This distinction is critical:

```text
API returns valid empty collection
→ empty state

API fails
→ error architecture
```

---

# 77. No Fixture Fallback

Forbidden:

```text
try API
catch
return fixtures
```

---

# 78. Listing 404 Semantics

`/products` itself is a valid collection route.

It should not become 404 simply because:

```text
page is empty
database is empty
```

---

# 79. Out-of-Range Pagination

Inspect actual API behavior for:

```text
/products?page=999999
```

when total pages are known.

Do not invent behavior before inspecting it.

Document whether the backend returns:

```text
empty collection
validation error
last page
other defined response
```

Frontend should preserve the frozen contract unless routing policy says otherwise.

---

# 80. Phase 14.2A Proxy — DO NOT GENERALIZE CASUALLY

Phase 14.2A introduced:

```text
frontend/web/proxy.ts
```

for category resource-existence preflight.

Do NOT automatically add `/products` to that proxy.

`/products` is a collection route and does not need resource-existence preflight.

Expected:

```text
/products
→ no category-style proxy lookup
```

---

# 81. Proxy Regression

Verify Phase 14.3 does not broaden:

```text
/categories/:path*
```

matching accidentally.

The proxy must not cause duplicate product-listing API requests.

---

# 82. Urban Ladder Reference

Urban Ladder remains:

```text
STRUCTURAL / IA REFERENCE ONLY
```

It may inform understanding of:

```text
catalog hierarchy
product-grid rhythm
result context
pagination/listing navigation
furniture merchandising density
```

---

# 83. Urban Ladder Is Not Visual Authority

Do not copy:

```text
exact grid
column count
product card
filter bar
sort controls
badges
promotional tiles
typography
spacing
colors
imagery
copy
```

The resulting listing must look like SL Furnitures.

---

# 84. Anti-Marketplace Rule

Do not maximize the number of products visible above the fold.

Furniture needs visual breathing room.

A calm collection of substantial product imagery is preferable to marketplace density.

---

# 85. Photography

Photography carries visual richness.

The UI remains quiet.

Do not decorate product cards to compensate for sparse catalog data.

---

# 86. Flat-First

Product cards remain flat-first.

Prefer:

```text
image
spacing
typography
```

over:

```text
shadow
border
floating card
colored background
large radius
```

---

# 87. No Hover Lift

Do not add product-card elevation/lift on hover.

Use established interaction conventions.

---

# 88. No Decorative Animation

No:

```text
scroll reveal
staggered card animation
fade-up
spring
parallax
```

---

# 89. Responsive Foundation

Consume Phase 13.9.

Do not invent listing-specific breakpoints.

Use:

```text
canonical MUI breakpoint mapping
canonical container
canonical gutters
canonical spacing
```

---

# 90. Narrow Mobile

At 320px ensure:

```text
H1 fits
count fits
cards remain legible
prices do not overflow
pagination remains usable
no horizontal document scroll
```

---

# 91. Mobile Grid

Choose grid behavior based on actual readable card width and frozen responsive rules.

Do not blindly force two columns on very narrow screens if that makes furniture/cards unusable.

Likewise do not assume one column without testing.

---

# 92. Tablet

Use available space intentionally.

Do not create a tablet-specific design system.

---

# 93. Desktop

Maintain substantial product imagery and comfortable spacing.

Avoid excessive card density.

---

# 94. Wide Desktop

Respect ContentContainer/max-width authority.

Do not add arbitrary extra columns simply because more viewport width exists.

---

# 95. CSS-First

Do not use:

```text
window.innerWidth
navigator.userAgent
device detection
```

for listing layout.

---

# 96. Client Boundary

Expected new client components:

```text
NONE
```

or extremely limited.

Pagination links, grid and server-fetched products do not inherently require client state.

Any `"use client"` introduced in Phase 14.3 requires explicit justification.

---

# 97. Accessibility

Follow `ACCESSIBILITY.md`.

Verify:

```text
one H1
logical headings
pagination semantics
keyboard navigation
visible focus
image alt
touch targets
contrast
zoom/reflow
no color-only product state
```

---

# 98. Product Grid Semantics

Use meaningful collection/list semantics where appropriate.

Do not force ARIA roles when semantic HTML already provides sufficient meaning.

---

# 99. Product Names

Ensure product names remain readable with:

```text
long names
multiple lines
narrow cards
```

Do not truncate important product identity aggressively.

---

# 100. Prices

Prices must remain readable at narrow widths.

Do not reduce them below the approved typography scale to solve layout problems.

---

# 101. Pagination Keyboard

All pagination links must be keyboard reachable.

Focus indication must remain visible.

---

# 102. Pagination Touch Targets

Previous/next/page controls must meet established target-size rules.

Do not create tiny page-number links.

---

# 103. Pagination Current State

Current page must be communicated semantically, not only by color.

---

# 104. 200% Reflow

Verify the listing at meaningful 200% equivalent zoom/reflow.

Do not require horizontal document scrolling.

---

# 105. Image Alt

Reuse ProductCard's established media-alt strategy.

Do not duplicate alt logic on the page.

---

# 106. Image Geometry

Product media geometry must remain stable before image load.

No obvious CLS.

---

# 107. Image Loading

Do not preload every product.

Only genuinely critical imagery should receive priority behavior.

A listing grid normally should not eagerly prioritize an entire first row without evidence.

---

# 108. `sizes`

Verify actual network/render behavior for listing cards.

The browser should not fetch unnecessarily enormous image variants for small cards.

---

# 109. R2 Boundary

Do NOT:

```text
create R2 bucket
add R2 SDK
implement uploads
change backend media storage
```

The listing consumes API-provided media URLs.

---

# 110. Image Optimization Boundary

Use `next/image` correctly.

Do not consume the comprehensive Phase 14.11 media-performance work.

---

# 111. SEO Boundary

Phase 14.7 owns comprehensive metadata.

Do not implement full:

```text
canonical metadata
OpenGraph strategy
pagination metadata strategy
title templates
description strategy
```

during 14.3.

---

# 112. Semantic Crawlability

Even though comprehensive SEO comes later, the listing must be structurally crawlable:

```text
server-rendered content
real links
semantic headings
ordinary pagination URLs
```

Do not create a client-only catalog.

---

# 113. Structured Data Boundary

Do NOT add:

```text
Product JSON-LD
ItemList JSON-LD
BreadcrumbList JSON-LD
```

Phase 14.8 owns it.

---

# 114. Sitemap / Robots Boundary

Do not modify sitemap/robots.

Phase 14.9 owns them.

---

# 115. Internal Linking Boundary

Normal functional links are allowed.

Do not implement comprehensive Phase 14.10 SEO linking strategy.

---

# 116. Product Detail Boundary

Do not create:

```text
app/products/[slug]/page.tsx
```

during 14.3.

That is Phase 14.4.

---

# 117. Search Boundary

Do not implement `/search`.

That is Phase 14.5.

---

# 118. Filter / Sort Boundary

Do not implement filter/sort UI.

That is Phase 14.6.

---

# 119. No Fake Product Data in Production

Do not ship fixture products as normal production results.

If fixtures are enabled explicitly for development, make that distinction clear.

---

# 120. No Fake Business Claims

Do not add:

```text
best seller
most popular
customer favourite
premium quality
handcrafted
sustainable
limited edition
```

unless authoritative data supports the claim.

---

# 121. No Fake Ratings

None.

---

# 122. No Fake Availability

Use actual API data.

Do not infer:

```text
Only 2 left
Selling fast
Ready to ship
```

without backend support.

---

# 123. No Promotional Tiles in Grid

Do not interrupt the canonical listing grid with:

```text
sale banners
newsletter cards
brand-story cards
MTO promotional tiles
```

Phase 14.3 should establish a clean product collection.

---

# 124. MADE_TO_ORDER in Listing

MADE_TO_ORDER products belong naturally in the catalog unless API/business rules state otherwise.

Do not filter them out by default.

Do not make `/products` synonymous with ready-stock only.

---

# 125. Category Products

Do not alter category filtering semantics established in 14.2.

Generic `/products` means the public collection according to backend defaults.

---

# 126. Default Backend Ordering

If the API has an established default order, use it.

Do not silently add frontend sorting.

Phase 14.6 will expose supported sort choices later.

---

# 127. Cache Policy

Inspect the current Phase 14.1/14.2 public catalog fetch policy.

Currently Phase 14.1 reported:

```text
no-store
```

Do not invent a different listing cache strategy without architectural reason.

Use consistent behavior unless the repository has since established another authority.

Document the actual choice.

---

# 128. Do Not Optimize Prematurely

Do not introduce:

```text
ISR architecture
tag invalidation
custom CDN caching
Redis
client cache
```

during 14.3.

Those require explicit architectural decisions.

---

# 129. Loading State

Use the established Phase 13.8 route-loading architecture.

Do not create elaborate product skeleton cards merely because this is a listing.

---

# 130. Empty vs Loading

Do not render the empty catalog state while data is merely pending.

Server rendering should make this distinction naturally.

---

# 131. Empty vs Error

Do not render empty state when API fetch fails.

---

# 132. Runtime Verification — Real API

The current local database is empty, which is useful.

Run:

```text
Laravel:
http://127.0.0.1:8000

Next.js:
actual local port
```

with:

```text
API_BASE_URL=http://127.0.0.1:8000
```

and API mode active.

Do not commit `.env.local`.

---

# 133. Real Empty Catalog Test

Verify:

```text
GET /api/v1/products
→ valid empty collection
```

then:

```text
GET /products
→ HTTP 200
→ factual empty state
```

This is a hard gate.

---

# 134. Empty Catalog Browser Verification

Verify in Chrome or Firefox:

```text
one main
one H1
empty-state copy
footer
no fake products
no console errors
no failed browser requests attributable to listing
no horizontal overflow
```

---

# 135. Populated Grid Verification

Because the real database currently has zero products, use one of these only if needed:

1. existing explicit development fixture mode;
2. automated component/contract fixtures;
3. existing test fixtures.

Do NOT seed production/local catalog data merely to satisfy visual testing.

Report:

```text
Real populated catalog:
NOT AVAILABLE
```

if appropriate.

---

# 136. Pagination Runtime Verification

Real pagination may be:

```text
NOT AVAILABLE
```

because the local API contains zero products.

Do not fabricate production records.

Test pagination logic using existing automated fixture/mocked-contract infrastructure.

---

# 137. Browser Widths

Verify representative canonical widths including:

```text
320
390
640
959
960
961
1024
1440
1728
```

You may use automated/headless Chrome where appropriate.

---

# 138. Grid Boundary Verification

Pay particular attention around the breakpoint where product column count changes.

Verify:

```text
one pixel below
exact breakpoint
one pixel above
```

where practical.

---

# 139. Horizontal Overflow

Required:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

No global overflow hiding.

---

# 140. Visual Verification

For populated fixture/test rendering inspect:

```text
product-card proportions
grid rhythm
image crop/contain behavior
long product names
prices
MADE_TO_ORDER state
missing-media state
pagination placement
```

---

# 141. Urban Ladder Audit

At completion report:

```text
Reference:
Urban Ladder — STRUCTURAL / IA ONLY

Ideas considered:
<list>

Ideas adopted:
<list>

Exact grid copied:
NONE

Visual design copied:
NONE

Product card copied:
NONE

Filter/sort UI copied:
NONE

Copy copied:
NONE

Assets copied:
NONE

Promotions copied:
NONE
```

---

# 142. Component Reinvention Audit

For every new component:

```text
Component:
<name>

Responsibility:
<reason>

Existing component inspected:
<list>

MUI primitive considered:
<list>

Why composition alone was insufficient:
<reason>

Reusable in category/search:
YES / NO
```

Remove unjustified components.

---

# 143. Token Audit

Check Phase 14.3 code for:

```text
raw hex
arbitrary spacing
arbitrary typography
custom radius
custom shadow
raw width breakpoint
custom animation
new media ratio
```

Expected:

```text
NONE
```

---

# 144. Client Boundary Audit

List every Phase 14.3 file containing:

```tsx
"use client"
```

Expected:

```text
NONE
```

unless genuinely required.

Explain every exception.

---

# 145. Proxy Audit

Verify:

```text
/categories/:path*
```

hard-404 preflight remains narrowly scoped.

Expected `/products` proxy preflight:

```text
NONE
```

---

# 146. ProductCard Regression

Verify ProductCard still works for:

```text
homepage
category page
listing
```

No appearance fork.

---

# 147. Homepage Regression

Run:

```text
npm run test:homepage
```

Must pass.

---

# 148. Category Regression

Run:

```text
npm run test:category
```

Must pass.

Do not regress hard-404 behavior.

Where practical verify again:

```text
/categories/definitely-nonexistent-phase-14-2
→ HTTP 404
```

---

# 149. Listing Tests

Add a focused command following repository convention, ideally:

```text
npm run test:products
```

or the repository's established naming convention.

Do not invent inconsistent script naming.

---

# 150. Listing Test Coverage

Cover at minimum:

```text
/products uses canonical API client
valid empty collection renders empty state
empty collection does not call notFound
API failure is not rendered as empty state
canonical ProductCard reused
no duplicate card implementation
pagination URLs are correct
page 1 URL normalization behavior is correct
previous/next boundaries are correct
MADE_TO_ORDER remains non-error
no cart/buy-now/wishlist
no filter/sort/search controls
```

---

# 151. Query Tests

Test malformed page input according to the chosen documented policy.

Include:

```text
page absent
page=1
page=2
page=0
page=-1
page=abc
```

Add large/out-of-range coverage if meaningful.

---

# 152. ProductCard Media Tests

Preserve proof that the frozen:

```text
--media-product-card
```

authority is used.

Do not duplicate raw `4 / 3` throughout listing code.

---

# 153. Request-First Regression

Explicitly verify absence of:

```text
cart
checkout
payment
buy now
quick add
wishlist
```

---

# 154. Design-System Regression

Run existing design/theme validation.

---

# 155. Responsive Regression

Run existing responsive contract tests.

---

# 156. State Regression

Run existing failure-state tests.

---

# 157. Routing Regression

Run routing contract tests.

---

# 158. API Client Regression

Run API client tests.

---

# 159. TypeScript

Must pass.

---

# 160. ESLint

Must pass.

---

# 161. Production Build

Must pass.

The production build must not require a populated Laravel catalog.

---

# 162. Production Runtime

Run `next start` and verify:

```text
/products
→ HTTP 200
```

against the real empty Laravel collection.

This is required.

---

# 163. `git diff --check`

Must pass.

---

# 164. Dependencies

Expected:

```text
NONE
```

Do not install anything for Phase 14.3.

---

# 165. Documentation

Update:

```text
phases/group-N-phases.md
```

with the Phase 14.3 execution record.

Update `frontend/AGENTS.md` only if a genuinely durable rule is established that is not already documented.

---

# 166. ADR

Expected:

```text
NONE
```

unless a genuinely durable architectural decision arises.

Do not create an ADR for ordinary listing composition.

---

# 167. Completion Report

Return:

```text
Phase 14.3 status:
PASS / BLOCKED


ROUTE

Canonical route:
<actual>

Page:
<path>

Server Component:
YES / NO

Client components introduced:
NONE / <list>

HTTP status with empty catalog:
200 / FAIL


API

Endpoint:
<actual>

API client reused:
YES / NO

Direct fetch outside client:
NONE / FAIL

Cache policy:
<actual>

Backend default ordering preserved:
YES / NO

Fixture fallback after API error:
NONE / FAIL


CURRENT LOCAL DATA

Laravel origin:
<origin>

Products total:
<number>

Real populated catalog:
<AVAILABLE / NOT AVAILABLE>

Real pagination:
<AVAILABLE / NOT AVAILABLE>

Synthetic catalog data created:
NO / FAIL


LISTING COMPOSITION

Rendered order:
1. <...>
2. <...>
3. <...>

H1:
<text>

Breadcrumb:
<summary / NONE>

Authoritative total:
<displayed / not displayed>

Product grid:
<summary>

Pagination:
<summary>

Empty state:
<summary>


PRODUCT GRID

Existing grid reused:
YES / NO / N/A

New ProductGrid:
NONE / <component>

Column behavior:
<summary>

Canonical breakpoints:
YES / NO

Raw breakpoints:
NONE / FAIL

Canonical spacing:
YES / NO


PRODUCT CARD

Canonical Phase 14.1 ProductCard reused:
YES / NO

ProductCard fork:
NONE / FAIL

ProductCard modifications:
NONE / <details>

Frozen media token:
<token>

Listing-specific ratio:
NONE / FAIL

Product links:
DEFERRED TO 14.4 / <other>

Price formatter reused:
YES / NO

MADE_TO_ORDER:
<summary>

Missing media:
<summary>


PAGINATION

Server-first:
YES / NO

Pagination source:
<API metadata>

Page query:
<contract>

Page 1 URL:
<behavior>

Previous:
<behavior>

Next:
<behavior>

Current page semantics:
PASS / FAIL

Keyboard:
PASS / FAIL

Client pagination state:
NONE / FAIL

Infinite scroll:
NONE

Load more:
NONE


EMPTY / ERROR

Empty API collection:
VALID 200 / FAIL

Empty state:
PASS / FAIL

Empty converted to 404:
NO / FAIL

API failure converted to empty:
NO / FAIL

Fixture fallback:
NONE / FAIL


DESIGN SYSTEM

Frozen tokens:
PASS / FAIL

New token authority:
NONE / FAIL

Raw unapproved colors:
NONE / <list>

Raw unapproved spacing:
NONE / <list>

Raw unapproved typography:
NONE / <list>

Raw unapproved radius:
NONE / <list>

Raw unapproved shadows:
NONE / <list>

Raw unapproved breakpoints:
NONE / <list>

New media ratio:
NONE / <list>


COMPONENT REUSE

Existing primitives reused:
<list>

New components:
<list>

New-component justifications:
<list>

Duplicate ProductCard:
NONE / FAIL

Duplicate breadcrumb:
NONE / FAIL

Listing-specific Button:
NONE / FAIL

Listing-specific Container:
NONE / FAIL


URBAN LADDER

Usage:
STRUCTURAL / IA REFERENCE ONLY

Ideas considered:
<list>

Ideas adopted:
<list>

Exact grid copied:
NONE / FAIL

Visual design copied:
NONE / FAIL

Product card copied:
NONE / FAIL

Filter/sort UI copied:
NONE / FAIL

Copy/assets/promotions copied:
NONE / FAIL


REQUEST-FIRST

Cart:
NONE

Checkout:
NONE

Buy now:
NONE

Quick add:
NONE

Wishlist:
NONE

Payment:
NONE

Ratings/reviews:
NONE

Sales/discount UI:
NONE


PHASE BOUNDARIES

Product detail implemented:
NO

Search implemented:
NO

Filters implemented:
NO

Sorting implemented:
NO

Comprehensive SEO implemented:
NO

Structured data implemented:
NO

Sitemap/robots implemented:
NO

Comprehensive internal linking implemented:
NO

R2 integration implemented:
NO

Backend changed:
NO

Flutter changed:
NO


PROXY

Category hard-404 proxy still narrow:
PASS / FAIL

/products preflight:
NONE / FAIL

Duplicate listing request caused by proxy:
NO / FAIL


RESPONSIVE

320:
PASS / FAIL

390:
PASS / FAIL

640:
PASS / FAIL

959:
PASS / FAIL

960:
PASS / FAIL

961:
PASS / FAIL

1024:
PASS / FAIL

1440:
PASS / FAIL

1728:
PASS / FAIL

200% reflow:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


ACCESSIBILITY

One H1:
PASS / FAIL

Heading hierarchy:
PASS / FAIL

Product semantics:
PASS / FAIL

Pagination landmark:
PASS / FAIL

Current page semantics:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Image alt:
PASS / FAIL

Contrast:
PASS / FAIL


PERFORMANCE

Server-first:
PASS / FAIL

New client JS:
NONE / <list>

Stable image geometry:
PASS / FAIL

Image sizes:
PASS / FAIL

Image loading priority:
PASS / FAIL

CLS:
PASS / FAIL

New dependency:
NONE / FAIL


RUNTIME

Real API empty collection:
PASS / FAIL

/products development:
HTTP <status>

/products production next start:
HTTP <status>

Real populated grid:
PASS / NOT AVAILABLE

Real pagination:
PASS / NOT AVAILABLE

Browser:
<browser>

Console errors:
NONE / <details>

Network failures:
NONE / <details>


VALIDATION

Listing contract:
PASS / FAIL

Pagination tests:
PASS / FAIL

Query parsing tests:
PASS / FAIL

ProductCard reuse:
PASS / FAIL

ProductCard regression:
PASS / FAIL

Homepage regression:
PASS / FAIL

Category regression:
PASS / FAIL

Category hard-404 runtime regression:
PASS / FAIL

API client:
PASS / FAIL

Layout:
PASS / FAIL

Responsive:
PASS / FAIL

States:
PASS / FAIL

Routing:
PASS / FAIL

Request-first:
PASS / FAIL

Theme:
PASS / FAIL

Design system:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


DOCUMENTATION

Group N record:
PASS / FAIL

frontend/AGENTS.md:
UPDATED / UNCHANGED

ADR:
<id / NONE>


FILES CHANGED

<list>


GIT

git-workflow-and-versioning read:
YES / NO

Operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 14.3:
PASS / BLOCKED

Phase 14.4:
READY / BLOCKED
```

---

# 168. STOP Condition

Phase 14.3 may be declared PASS only when:

- the canonical `/products` route exists;
- the route is server-first;
- Laravel remains product authority;
- the canonical API client is reused;
- `/products` does not receive category-style proxy preflight;
- the current real empty Laravel catalog produces HTTP 200;
- empty catalog and API failure remain distinct;
- no silent fixture fallback exists;
- the Phase 14.1 canonical ProductCard is reused;
- no listing-specific ProductCard fork exists;
- the frozen `--media-product-card: 4 / 3` authority remains intact;
- a canonical responsive product-grid strategy is established without duplicate grids;
- product imagery remains substantial and calm;
- basic server-first pagination is correctly implemented from authoritative API metadata;
- pagination URLs are shareable and accessible;
- page 1 uses the clean route where appropriate;
- no infinite-scroll/load-more client architecture is introduced;
- malformed page parameters behave deterministically;
- MADE_TO_ORDER remains first-class;
- price formatting is reused;
- no cart/checkout/buy-now/quick-add/wishlist/review/sale behavior exists;
- product-detail links remain deferred until 14.4 unless the destination genuinely exists;
- filters and sorting remain deferred to 14.6;
- search remains deferred to 14.5;
- comprehensive SEO remains deferred to 14.7;
- structured data remains deferred to 14.8;
- sitemap/robots remain deferred to 14.9;
- R2/media optimization remains outside this phase;
- no copied Urban Ladder visual design exists;
- no second design/token authority exists;
- no unapproved visual values are introduced;
- responsive behavior passes from narrow mobile through wide desktop;
- 200% reflow remains usable;
- no horizontal overflow exists;
- accessibility checks pass;
- client JS remains minimal;
- no dependencies are added;
- homepage regression passes;
- category regression passes;
- the Phase 14.2A hard-404 behavior remains intact;
- `/products` returns HTTP 200 under production `next start`;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 14.3 — PASS
Phase 14.4 — READY
```

Do not start Phase 14.4 automatically.

**Phase 14.3 establishes one canonical product collection architecture. Reuse the frozen design system, canonical `ProductCard`, existing state/layout/API foundations, and Laravel contracts. Do not turn product listing into an excuse to reinvent the storefront or prematurely implement search/filter/sort/detail functionality.**
