# Phase 14.2 — Public Category Pages

## Objective

Implement production-quality public furniture category pages using the canonical route:

```text
/categories/[slug]
```

A category page must answer:

```text
Where am I?
What furniture belongs here?
What can I explore?
```

while preserving the calm, editorial, furniture-first SL Furnitures design established in Phase 14.1.

This phase must convert category discovery from homepage presentation into real, crawlable, server-rendered category destinations backed by the existing Laravel catalog API.

The result must remain:

```text
architectural
warm
editorial
calm
photography-led
spacious
commerce-oriented
```

without becoming a generic ecommerce grid page.

---

# 1. Phase Boundary

Implement only:

```text
14.2 — Category pages
```

Do NOT implement:

```text
14.3 — Product listing
14.4 — Product detail
14.5 — Search
14.6 — Filters / sorting
14.7 — SEO metadata
14.8 — Structured data
14.9 — Sitemap / robots
14.10 — Internal linking strategy
14.11 — Image / performance optimization
```

Natural category-page semantics and links are allowed.

Do not consume the owning phases above prematurely.

---

# 2. Read Authorities Before Coding

Before modifying code, inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── COMPONENTS.md
├── ACCESSIBILITY.md
├── tokens.css
└── design-tokens.json

frontend/web/
├── app/
├── components/
├── lib/api/
├── theme/
├── ROUTING.md
└── RESPONSIVE.md        # if present

docs/api/
├── api-contract.md
├── api-resources.md
├── api-conventions.md
└── openapi.yaml

docs/domain/business-rules.md
docs/decisions.md
phases/group-N-phases.md
```

Inspect the actual Phase 14.1 implementation, especially:

```text
ProductCard
CatalogDiscovery
homepage catalog data mapping
money formatting
media handling
fixture architecture
```

Do not work from memory or assumptions.

---

# 3. Git Policy

Before ANY Git operation:

```text
locate/read/follow:
git-workflow-and-versioning
```

Preserve unrelated owner changes.

Never commit secrets or environment files.

Stage only Phase 14.2 work.

Use an atomic phase commit.

---

# 4. Frozen Design System — NON-NEGOTIABLE

The frozen design system remains the visual authority.

The dependency chain is:

```text
frozen tokens
    ↓
MUI theme
    ↓
existing primitives
    ↓
existing commerce components
    ↓
category-page composition
```

Do not reverse this relationship.

A category page does NOT have authority to invent another design system.

---

# 5. Repository Authority Beats Prompt Suggestions

Phase 14.1 correctly discovered that:

```text
--media-product-card: 4 / 3
```

is the frozen product-card media token.

Preserve it.

Do NOT change it to 4:5 merely because an earlier planning recommendation suggested 4:5.

General rule:

> If this prompt contains a visual recommendation that conflicts with an already-frozen repository token or documented design-system decision, the repository authority wins.

Report the discrepancy rather than silently changing the design system.

---

# 6. No New Visual Values

Do not invent:

```text
colors
spacing values
font sizes
line heights
radii
shadows
elevations
breakpoints
container widths
motion durations
easing
focus treatments
media ratios
```

when frozen values already exist.

Expected audit result:

```text
Unapproved ad-hoc visual values: NONE
```

---

# 7. No Component Reinvention

Before creating ANY component, inspect:

```text
frontend/web/components/
frontend/design-system/COMPONENTS.md
MUI primitives
```

Especially preserve and reuse the canonical Phase 14.1:

```text
ProductCard
```

Do NOT create:

```text
CategoryProductCard
RoomProductCard
CategoryPageProductCard
ProductTile
FurnitureCard
```

to display the same product concept.

---

# 8. ProductCard Is Canonical

The Phase 14.1 `ProductCard` is intended for:

```text
homepage
category pages
product listing
search results
```

Phase 14.2 is its first cross-page reuse test.

If the category page reveals a genuine missing semantic capability, improve the canonical component carefully.

Do NOT fork it.

---

# 9. ProductCard Changes

Any modification to ProductCard must answer:

```text
What domain capability was missing?

Why is it reusable outside category pages?

Why is this not a category-specific visual variation?

Does the change preserve Phase 14.1?
```

Do not add arbitrary styling props such as:

```text
compact
categoryVariant
largeImage
cardGap
imageHeight
rounded
shadow
```

just to fit this page.

---

# 10. Existing Layout Primitives

Reuse:

```text
SiteShell
SiteSection
ContentContainer
NavLink
```

and existing MUI/theme primitives.

Do not create:

```text
CategoryContainer
CategorySection
CategoryButton
CategoryTypography
```

where existing primitives already own those responsibilities.

---

# 11. Urban Ladder Reference

Urban Ladder remains approved as:

```text
STRUCTURAL / INFORMATION-ARCHITECTURE REFERENCE ONLY
```

For category pages, it may inform understanding of:

```text
category hierarchy
category introduction
sub-category discovery
product discovery
breadcrumb placement
commerce page rhythm
```

It is NOT a visual authority.

---

# 12. Do Not Copy Urban Ladder

Do NOT copy:

```text
category-page layout pixel-for-pixel
grid dimensions
filter treatment
card styling
copy
promotions
sale treatments
badges
colors
typography
spacing
imagery
source code
CSS
```

Translate furniture-commerce lessons through the frozen SL Furnitures system.

---

# 13. Canonical Route

Implement:

```text
/categories/[slug]
```

using Next.js App Router.

Do not create alternate category URL schemes such as:

```text
/category/[slug]
/shop/[category]
/collections/[category]
/rooms/[slug]
```

unless already approved by `ROUTING.md`.

---

# 14. Slug Authority

The slug comes from Laravel.

Never generate category slugs in the browser/frontend from:

```text
category.name
```

Do not use frontend slugification libraries.

Do not expose numeric database IDs.

---

# 15. Existing Slug Caveat

Preserve the Phase 13.6 routing decision:

- category slugs are canonical website identifiers;
- category slugs may currently be mutable;
- old-slug redirect/history behavior is not yet established.

Do not invent slug-history redirects during 14.2.

If the issue remains relevant, document it as an existing known constraint.

---

# 16. Server-First Route

The category page must be a Server Component by default.

Do NOT put:

```tsx
"use client";
```

at the category page root.

Public catalog discovery should remain server-renderable.

---

# 17. Route Parameter Handling

Use the installed Next.js version's actual App Router conventions.

Do not copy outdated examples from memory.

Verify the installed Next.js behavior before implementing dynamic route parameter access.

---

# 18. Category Resolution

Resolve the category using the existing Laravel API and Phase 13.5 API client.

Do not:

```text
query MySQL directly
create frontend category authority
hard-code production category objects
create Next.js API proxy
```

Laravel remains authoritative.

---

# 19. API Client

Use:

```text
frontend/web/lib/api/client.ts
```

Do not create:

```text
categoryApi.ts with another fetch abstraction
Axios client
SWR layer
React Query layer
Next API proxy
```

A thin domain query/helper layer is acceptable if consistent with existing architecture and it delegates transport to the canonical client.

---

# 20. Inspect Existing Category Contract

Before coding, verify from Laravel/OpenAPI:

```text
category detail endpoint
accepted identifier
category response shape
parent relationship
children relationship
slug
name
description if any
media if any
product relationship if any
pagination behavior
```

Do not guess.

---

# 21. Product Retrieval Contract

Determine from the actual public products endpoint how products are filtered by category.

Phase 13.6 documented the collection query contract including:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
page
per_page
```

Verify actual implementation before use.

Do not invent another category-products endpoint unless one already exists.

---

# 22. Category Page Data Flow

Preferred conceptual flow:

```text
URL slug
   ↓
Laravel category detail
   ↓
resolved canonical category
   ↓
Laravel product collection filtered by category
   ↓
category-page presentation
```

Use actual API capabilities.

---

# 23. 404 Semantics

If Laravel establishes that the requested category does not exist:

```text
notFound()
```

should produce the Phase 13.8 canonical not-found experience.

Do not redirect missing categories to `/`.

Do not render:

```text
No category found
```

with HTTP 200.

---

# 24. 404 Masking

Respect backend security semantics.

Do not reinterpret arbitrary failures as 404.

Use typed/status-bearing API failures.

No magic message matching.

---

# 25. Unexpected Failures

Unexpected API/transport/server failures should propagate through the established failure architecture.

Do not silently show fixtures.

Do not convert network failure into "category not found."

---

# 26. Fixtures

Phase 14.1 introduced explicit homepage fixture mode.

Do not automatically reuse homepage fixture behavior for production category routes unless there is a clearly established development architecture supporting it.

If fixtures are needed for category-page visual development:

- keep them explicitly opt-in;
- identify them visibly in development;
- do not silently fall back after API failure;
- do not create a second fixture architecture.

Prefer reusing the existing fixture infrastructure.

---

# 27. Category Page Purpose

The page should clearly communicate:

```text
category identity
category context
relevant child categories, when meaningful
relevant products
```

without unnecessary marketing filler.

---

# 28. Recommended Category Composition

A strong starting architecture is:

```text
Breadcrumb
    ↓
Category introduction
    ↓
Optional child-category discovery
    ↓
Category product preview
```

Do not mechanically implement every block if API/domain data does not support it.

---

# 29. Category Introduction

The category header may include:

```text
category name
short authoritative description
optional category imagery if authoritative media exists
```

Do not invent category marketing descriptions if the backend does not provide them.

---

# 30. No Fake Category Copy

If Laravel only provides:

```text
name
slug
```

do not generate AI descriptions such as:

> Discover beautifully crafted pieces designed to transform your living room...

Either omit description or use approved static editorial content only if the repository explicitly owns such content.

---

# 31. Category Hero Restraint

A category page does not automatically need a giant homepage-style hero.

Do not make every category route another landing page.

The category identity should be clear without burying products below enormous imagery.

---

# 32. Category Media

If authoritative category media exists, use it appropriately.

If it does not:

```text
do not invent production category imagery
```

merely because homepage fixture categories have images.

Homepage fixtures are not production category media authority.

---

# 33. Child Categories

If the category has child categories and the API exposes them, provide useful child-category discovery.

Examples conceptually:

```text
Living Room
├── Seating
├── Tables
└── Storage & Media
```

Do not duplicate the taxonomy manually.

---

# 34. Child Category Links

Child-category links use:

```text
/categories/[slug]
```

with backend-returned slugs.

No frontend slug generation.

---

# 35. Category Hierarchy

Respect the existing three-level category taxonomy.

Do not flatten everything merely for visual simplicity if hierarchy matters.

Likewise, do not display the entire taxonomy tree on every category page.

Show contextually useful relationships.

---

# 36. Breadcrumb

Implement a restrained semantic breadcrumb if the actual category hierarchy supports it.

Use proper navigation semantics.

Conceptually:

```text
Home / Living Room / Seating
```

but derive actual labels/slugs from authoritative data.

---

# 37. Breadcrumb Accessibility

Use an appropriately labelled navigation landmark.

Current page should be represented semantically and should not need a redundant clickable self-link.

Do not use decorative slash characters as screen-reader content when avoidable.

---

# 38. Breadcrumb Component

Before creating a breadcrumb component, inspect whether:

```text
MUI Breadcrumbs
existing project primitive
```

already satisfies the requirement.

Do not reinvent it.

If a project wrapper is justified, keep it generic and reusable.

---

# 39. Product Presentation

Category pages may show products belonging to that category.

Reuse:

```text
ProductCard
```

from Phase 14.1.

---

# 40. Phase 14.3 Boundary

This is critical.

Phase 14.2 must NOT implement the complete generic product-listing experience owned by Phase 14.3.

Do NOT prematurely build:

```text
/products generic listing page
listing toolbar
result-count architecture
advanced pagination UI
generic catalog grid system
listing URL-state architecture
listing empty-state system
```

unless the category page genuinely requires a minimal reusable primitive.

---

# 41. Category Product Preview vs Generic Listing

For 14.2, implement enough product presentation to make the category page useful.

Think:

```text
category-specific product discovery
```

not:

```text
Phase 14.3 embedded early
```

If the category can contain many products, use the existing API pagination contract responsibly, but do not turn this phase into the complete listing/filter/sort experience.

---

# 42. Product Grid

A simple responsive product layout is permitted because products must be presented.

It must use:

```text
canonical breakpoints
canonical spacing
canonical ProductCard
```

Do not establish a second visual grid system.

---

# 43. Product Grid Component

Before creating a reusable `ProductGrid`, determine whether simple MUI/CSS Grid composition is sufficient.

Only create a canonical reusable grid component if there is a real cross-page responsibility that Phase 14.3 can reuse.

Do not create:

```text
CategoryProductGrid
```

unless it truly has category-specific domain semantics.

---

# 44. Product Card Media Ratio

Continue using the frozen:

```text
--media-product-card
```

authority.

Do not introduce another ratio in category pages.

---

# 45. Product Images

Use `next/image` through the canonical ProductCard implementation.

Do not duplicate image handling inside the category page.

---

# 46. Product Media Source

Production media must come from API data.

Do not map product names to local fixture images in production.

Fixture mode may use explicit fixture media according to the established development architecture.

---

# 47. Product Detail Links

Phase 14.4 owns:

```text
/products/[slug]
```

implementation.

Do not create that page now.

If product cards cannot safely link without producing 404s, preserve their non-interactive Phase 14.1 behavior until 14.4.

Do not use dead links.

---

# 48. Homepage Category Links

Once `/categories/[slug]` is genuinely implemented and verified, update Phase 14.1 category discovery so production category cards can navigate to the now-valid category routes.

This is an expected integration step for 14.2.

Do not leave category cards artificially non-interactive once their destination exists.

---

# 49. Homepage Link Source

Homepage category links must use the category's authoritative backend-returned:

```text
slug
```

Never derive the href from category names.

---

# 50. Fixture Homepage Links

If fixture mode has category slugs compatible with fixture category pages, linking may be enabled only if those destinations actually work under fixture mode.

Otherwise keep fixture-only destinations non-interactive rather than creating broken routes.

---

# 51. Product Type

Preserve domain meaning such as:

```text
IN_STOCK
MADE_TO_ORDER
```

or whatever the frozen API enums actually use.

Do not rename backend states casually in frontend domain logic.

Presentation copy may be human-friendly.

---

# 52. MADE_TO_ORDER

Continue treating MADE_TO_ORDER as a first-class category result.

It is not:

```text
unavailable
error
disabled
sold out
```

unless another domain field explicitly says so.

---

# 53. Availability

Use only actual API availability semantics.

Do not infer stock from:

```text
price
product type
presence of image
```

---

# 54. Price

Reuse the Phase 14.1 contract-safe TZS formatting.

Do not create another category price formatter.

---

# 55. Money

Preserve the project's integer minor-unit convention at domain/API boundaries.

Do not introduce floating-point money arithmetic.

---

# 56. Empty Category

A valid category containing zero products is NOT a 404.

This distinction is mandatory:

```text
missing category → 404

existing category with zero products → valid category page
```

---

# 57. Empty Category Presentation

Use a calm, useful empty state.

Do not treat it as an application error.

Do not invent fake products.

Do not silently switch to another category.

---

# 58. Empty Category Copy

Keep copy factual.

Example concept:

```text
There are no pieces listed in this category yet.
```

Do not claim:

```text
Sold out
Coming soon
Restocking
```

unless backend data supports it.

---

# 59. Empty State Component

Inspect existing state primitives before creating anything.

If a generic reusable empty-state concept is genuinely missing, create one only if consistent with `COMPONENTS.md`.

Do not create a giant category-specific empty card.

---

# 60. Pagination

Inspect the API response.

If category products are paginated, do not discard pagination semantics.

However, comprehensive listing pagination UX can remain Phase 14.3 if not needed to make 14.2 correct.

Do not fetch an arbitrary enormous `per_page` value just to avoid dealing with pagination.

---

# 61. No Infinite Scroll

Do not introduce infinite scroll.

---

# 62. No "Load More" Architecture Prematurely

Do not add client-side load-more state merely because the API paginates.

If category pages need navigation between result pages now, use the smallest server-first solution consistent with the routing contract.

Do not preempt Phase 14.3.

---

# 63. Filters

Do NOT implement filters.

Phase 14.6 owns:

```text
product_type
availability
price
filter UI
filter drawers
```

even though the API already supports those parameters.

---

# 64. Sorting

Do NOT implement sort controls.

Phase 14.6 owns sorting UX.

---

# 65. Search

Do NOT add category-local search.

Phase 14.5 owns search.

---

# 66. Result Count

A simple factual count may be shown only if directly available and useful.

Do not build a listing toolbar around it.

---

# 67. Category Navigation

Do not duplicate the SiteHeader category navigation inside the page.

Page-level child-category discovery is different from global navigation.

---

# 68. Category Page Visual Hierarchy

A good category page should generally prioritize:

```text
context
    ↓
category identity
    ↓
sub-category discovery when relevant
    ↓
products
```

Avoid excessive promotional interruption.

---

# 69. No Promotional Banners

Do not add:

```text
sale banners
coupon strips
discount promos
delivery promos
countdowns
```

to category pages.

---

# 70. No Fake Editorial Blocks

Do not insert random lifestyle sections between product rows merely to make the category page visually rich.

Phase 14.2 is primarily discovery.

---

# 71. Photography

Furniture/product imagery should remain the dominant visual richness.

UI remains restrained.

---

# 72. Surface Usage

Use only approved semantic surfaces.

Do not create category-specific background colors.

---

# 73. Brown Accent

Continue using deep brown as a controlled accent.

Do not make category headings, every link, every border and every state brown.

---

# 74. Typography

Use:

```text
Young Serif
```

selectively for category identity/editorial display where appropriate.

Use utility sans for:

```text
breadcrumb
product names where design authority says so
prices
metadata
states
controls
```

Follow the frozen typography mapping.

---

# 75. Heading Hierarchy

Each category page needs one meaningful:

```html
<h1>
```

representing the category name.

Do not reuse the site logo as H1.

Do not produce multiple H1s.

---

# 76. Dynamic Heading

The H1 should use authoritative category data.

Do not hard-code route-specific headings in frontend code.

---

# 77. Semantic Sections

Use headings for child-category and product sections where useful.

Do not skip heading levels for visual reasons.

---

# 78. Responsive Foundation

Consume Phase 13.9.

Do not invent category-specific:

```text
breakpoints
site gutters
container widths
media queries
```

---

# 79. Mobile

At narrow widths ensure:

```text
breadcrumb reflows
category title reflows
child categories remain usable
product cards remain legible
empty state remains readable
```

Do not shrink typography below approved values.

---

# 80. Tablet

Tablet should not simply become an enlarged mobile page.

Use the canonical responsive composition.

---

# 81. Desktop

Desktop should use space efficiently without turning into a dense marketplace grid.

Maintain the project's editorial calm.

---

# 82. Wide Desktop

Use existing maximum-width/full-bleed rules.

Do not stretch product cards indefinitely.

---

# 83. CSS-First

No:

```text
window.innerWidth
navigator.userAgent
device detection
```

for layout.

---

# 84. Client Components

Expected category-page root:

```text
Server Component
```

Any new Client Component must be explicitly justified.

With no filters/sorting/search in this phase, there should be little reason for category-page client state.

---

# 85. Loading

The existing App Router loading foundation should remain applicable.

Do not create elaborate category skeletons unless a genuine route-segment requirement justifies them.

Phase 13.8 deliberately deferred product/category-specific skeletons.

---

# 86. Error

Do not create a duplicate generic category error system.

Unexpected failures use the established error boundary.

---

# 87. Not Found

Missing category uses:

```text
notFound()
```

and the established canonical 404 presentation.

---

# 88. Accessibility

Follow `ACCESSIBILITY.md`.

Verify:

```text
one H1
logical headings
semantic breadcrumb
link semantics
keyboard
focus
touch targets
image alt
contrast
zoom/reflow
no color-only states
```

---

# 89. Product Image Alt

Use actual product media alt text if the API provides authoritative alt text.

Otherwise use the established safe product-media fallback strategy.

Do not expose filenames as alt text.

---

# 90. Category Image Alt

If category media is meaningful, provide meaningful alt.

If purely decorative, use appropriate decorative treatment.

---

# 91. Keyboard

Users must be able to traverse:

```text
breadcrumb
child categories
product interactions that are actually active
footer
```

in logical order.

---

# 92. Focus

Use the frozen focus treatment.

Do not suppress outlines.

---

# 93. Zoom/Reflow

Verify at meaningful 200% equivalent reflow.

No horizontal two-dimensional scrolling for ordinary page content.

---

# 94. Horizontal Overflow

Check:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

at representative Phase 13.9 widths.

Do not use global overflow hiding.

---

# 95. Image Stability

Category/product media must reserve stable geometry.

No obvious CLS from image loading.

---

# 96. `next/image`

Continue using Next.js image handling.

Do not create another media component solely for category pages unless a reusable domain need exists.

---

# 97. `sizes`

ProductCard already owns its placement/image behavior.

If the category grid changes the effective rendered widths enough that its `sizes` contract needs enhancement, improve the canonical component appropriately.

Do not hard-code category-only image logic inside it without considering Phase 14.3 reuse.

---

# 98. Remote Media

Preserve the Phase 14.1:

```text
CATALOG_MEDIA_BASE_URL
```

architecture if that is the actual established implementation.

Do not introduce another media environment variable.

---

# 99. R2

Do not add R2 infrastructure.

The category page consumes URLs from API data.

Storage implementation remains separate.

---

# 100. Performance

Keep the page server-first and lean.

Do not add:

```text
carousel library
grid library
animation library
client data-fetching library
```

Expected dependencies:

```text
NONE
```

---

# 101. Above-the-Fold Images

Do not mark all category/product images as priority/preloaded.

Only genuinely critical above-the-fold media should receive special treatment.

Avoid competing with the page's actual LCP resource.

---

# 102. Category LCP

Inspect the real rendered page.

Determine whether category title/media/product imagery becomes LCP.

Do not guess.

Do not perform comprehensive Phase 14.11 optimization yet.

---

# 103. No Decorative Motion

No:

```text
scroll reveal
product-card lift animation
parallax
spring effects
```

---

# 104. Natural Homepage Integration

Once category routes are implemented, homepage category discovery should become genuinely navigable.

This is part of Phase 14.2 integration.

Do not redesign the homepage.

Only activate/adjust the appropriate links.

---

# 105. Homepage Regression

After enabling category links, verify Phase 14.1 still passes:

```text
visual composition
accessibility
responsive behavior
request-first policy
no dead links
```

---

# 106. Global Header Integration

If the existing global category navigation currently has deferred/non-interactive category destinations and can now safely link to implemented category routes using authoritative data already available to it, inspect whether activation belongs here.

Do NOT introduce root-layout API fetching merely to accomplish this.

If activation requires new global taxonomy-fetch architecture, defer it and report why.

---

# 107. No Hard-Coded Taxonomy Duplication

Do not solve global/header linking by copying category slugs into another frontend array.

---

# 108. URL Query Contract

If minimal pagination is required, preserve the canonical URL query contract.

Do not invent:

```text
?p=2
?categoryPage=2
```

if the API/routing convention uses:

```text
?page=2
```

---

# 109. Query Validation

Do not trust arbitrary URL query input blindly.

If category pagination is implemented, parse and constrain it safely.

Malformed values should have deterministic behavior.

Do not crash the page.

---

# 110. Canonical Category URL

Do not append unnecessary query parameters to the default category page.

Prefer:

```text
/categories/living-room
```

over:

```text
/categories/living-room?page=1
```

for the initial state where applicable.

---

# 111. SEO Boundary

Do not perform comprehensive Phase 14.7 metadata work.

However, do not create category pages that fundamentally prevent future metadata generation from authoritative category data.

---

# 112. Metadata

If the existing application already has a basic metadata pattern required for route correctness, preserve it.

Do not turn 14.2 into metadata architecture.

Document that dynamic category metadata remains Phase 14.7 unless already explicitly owned elsewhere.

---

# 113. Structured Data

Do NOT add:

```text
BreadcrumbList JSON-LD
ItemList JSON-LD
Product JSON-LD
```

Phase 14.8 owns structured data.

Semantic HTML breadcrumb is appropriate now; JSON-LD is not.

---

# 114. Sitemap

Do not add category routes to sitemap generation now.

Phase 14.9 owns sitemap/robots.

---

# 115. Internal Linking

Homepage → category and parent → child category links are natural functional navigation and should be implemented where routes exist.

Do not perform the comprehensive Phase 14.10 SEO internal-linking strategy.

---

# 116. Category Fixture Images

Do not generate a new set of images merely because 14.2 exists if existing development fixtures adequately support visual verification.

Reuse coherent fixtures where appropriate.

Do not bloat Git with duplicate images.

---

# 117. Component Reinvention Audit

At completion, list every new component.

For each:

```text
Component:
<name>

Responsibility:
<reason>

Existing project primitive inspected:
<list>

MUI primitive considered:
<list>

Why a new component was necessary:
<reason>

Reusable outside category pages:
YES / NO
```

A component that cannot justify itself should probably be removed.

---

# 118. Frozen Token Audit

Audit new/modified category code for:

```text
raw hex colors
arbitrary spacing
arbitrary font sizes
custom radius
custom shadows
raw width breakpoints
custom motion values
new media ratios
```

Expected:

```text
Unapproved values: NONE
```

---

# 119. Urban Ladder Audit

Report:

```text
Structural ideas considered:
<list>

Structural ideas adopted:
<list>

Visual design copied:
NONE

Exact layout copied:
NONE

Copy copied:
NONE

Assets copied:
NONE

Promotional mechanics copied:
NONE
```

---

# 120. Anti-AI-Slop Audit

Explicitly inspect for:

```text
rounded-card overload
pill overload
badge overload
gradient backgrounds
glassmorphism
decorative shadows
random icons
generic category marketing copy
fake promotions
fake ratings
fake metrics
excessive centered layout
brown everywhere
unnecessary animation
```

Expected:

```text
NONE
```

---

# 121. Tests — Route

Add coverage proving:

```text
valid category slug renders category
missing category invokes not-found semantics
unexpected API failure is not converted to 404
empty category is valid, not 404
```

Use the repository's existing testing conventions.

---

# 122. Tests — Slug

Prove:

```text
backend slug is used
numeric database ID is not exposed
frontend slug generation is absent
```

---

# 123. Tests — Product Filtering

Prove the category product request uses the contract-approved category filter/identifier.

Do not merely assert rendered fixture content.

---

# 124. Tests — ProductCard Reuse

Prove the category page uses the canonical ProductCard.

No duplicate category product-card implementation.

---

# 125. Tests — Request-First

Verify category pages expose no:

```text
cart
checkout
buy now
payment
wishlist
```

controls.

---

# 126. Tests — Links

Verify:

```text
homepage category links → valid /categories/[slug]
child-category links → valid /categories/[slug]
no href="#"
no known broken product-detail links
```

according to implemented routes.

---

# 127. Tests — Accessibility

Where supported by existing test infrastructure, verify:

```text
one H1
breadcrumb landmark
heading hierarchy
semantic links
empty state
```

Do not claim automated accessibility tests prove complete conformance.

---

# 128. Runtime Verification

Use actual runtime/browser verification for at least:

```text
top-level category
nested category if supported
category with products
empty category if available/fixture-supported
nonexistent category
```

---

# 129. Viewport Verification

Use the canonical Phase 13.9 viewport matrix.

At minimum:

```text
320
390
tablet
narrow desktop
1440
wide desktop
```

or the exact documented matrix.

---

# 130. Boundary Width Verification

Retain checks around the shell's major responsive transition.

Category implementation must not break navigation behavior.

---

# 131. Real API Smoke

If local Laravel/API configuration is available, perform a real Next.js → Laravel category smoke test.

Verify:

```text
category detail
category product retrieval
404 category
```

Do not block Phase 14.2 solely because a production domain is not deployed.

If local API is unavailable, report exactly what could not be runtime-integrated rather than fabricating PASS.

---

# 132. API Error Verification

Where practical, verify typed handling for:

```text
404
5xx
network/transport failure
```

No message matching.

---

# 133. Existing Regression Suite

Run:

```text
homepage contract
API client
layout contract
responsive contract
state/failure contract
routing contract
request-first regression
theme contract
design-system validation
```

All must remain PASS.

---

# 134. TypeScript

Must pass.

---

# 135. ESLint

Must pass.

---

# 136. Production Build

Must pass.

Dynamic category routing must not require production API availability during build unless architecture explicitly intends that.

Do not solve build failures by swallowing real runtime failures.

---

# 137. `git diff --check`

Must pass.

---

# 138. Dependencies

Expected:

```text
NONE
```

If a new dependency appears necessary:

```text
STOP
```

and justify it before installation.

This phase should not need one.

---

# 139. Documentation

Update:

```text
phases/group-N-phases.md
```

with the Phase 14.2 execution record.

Update `frontend/AGENTS.md` only for genuinely durable rules not already covered.

Do not paste phase-specific implementation details into global agent guidance.

---

# 140. ADR

Expected:

```text
NONE
```

unless a material architectural decision is genuinely required.

Do not create an ADR simply because a dynamic route was added.

---

# 141. Completion Report

Return:

```text
Phase 14.2 status:
PASS / BLOCKED


ROUTE

Canonical route:
/categories/[slug]

Page:
<path>

Server Component:
YES / NO

Numeric database IDs in URLs:
NONE / FAIL

Frontend slug generation:
NONE / FAIL


CATEGORY RESOLUTION

Detail endpoint:
<actual endpoint>

Identifier:
<actual contract>

Slug authority:
LARAVEL / FAIL

Missing category:
notFound() / FAIL

Unexpected failure converted to 404:
NO / FAIL

Empty category converted to 404:
NO / FAIL


DATA

Category source:
REAL API / FIXTURE / BOTH

Product source:
REAL API / FIXTURE / BOTH

API client reused:
YES / NO

Direct fetch outside canonical client:
NONE / FAIL

Product category filter:
<actual query/contract>

Fixture fallback after API error:
NONE / FAIL

Backend changes:
NONE / <list>


PAGE COMPOSITION

Rendered order:
1. <...>
2. <...>
3. <...>

Category H1:
<source>

Breadcrumb:
<summary>

Child-category discovery:
<summary / N/A>

Product presentation:
<summary>

Empty state:
<summary>


COMPONENT REUSE

Canonical ProductCard reused:
YES / NO

ProductCard modified:
NO / <explain reusable change>

Existing layout primitives reused:
<list>

New components:
<list + justification>

Duplicate product card:
NONE / FAIL

Category-specific Button:
NONE / FAIL

Category-specific Container:
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

Product-card media authority:
<token>


URBAN LADDER

Usage:
STRUCTURAL / IA REFERENCE ONLY

Structural ideas considered:
<list>

Structural ideas adopted:
<list>

Visual design copied:
NONE / FAIL

Exact layout copied:
NONE / FAIL

Copy copied:
NONE / FAIL

Assets copied:
NONE / FAIL

Promotional mechanics copied:
NONE / FAIL


PRODUCTS

Canonical ProductCard:
PASS / FAIL

Product media:
<source>

Product detail links:
<active / deferred>

MADE_TO_ORDER:
<summary>

Price formatter reused:
YES / NO

Cart:
NONE

Buy now:
NONE

Wishlist:
NONE

Ratings:
NONE

Fake sale:
NONE


HOMEPAGE INTEGRATION

Category discovery links activated:
YES / NO / N/A

Uses backend slugs:
YES / NO

Broken homepage links:
NONE / FAIL

Homepage redesign:
NO / FAIL


RESPONSIVE

Canonical breakpoints:
PASS / FAIL

CSS-first:
YES / NO

320:
PASS / FAIL

390:
PASS / FAIL

Tablet:
PASS / FAIL

Narrow desktop:
PASS / FAIL

Desktop:
PASS / FAIL

Wide desktop:
PASS / FAIL

200% reflow:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


ACCESSIBILITY

One H1:
PASS / FAIL

Breadcrumb semantics:
PASS / FAIL

Heading hierarchy:
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

Reduced motion:
PASS / FAIL


PERFORMANCE

Server-first:
YES / NO

New client state:
NONE / <list>

Image geometry stable:
PASS / FAIL

Image priority restrained:
PASS / FAIL

New runtime dependency:
NONE / <list>


PHASE BOUNDARIES

Generic /products listing implemented:
NO

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

R2 integration implemented:
NO

Flutter changed:
NO


ANTI-AI-SLOP

Rounded-card overload:
NONE / FAIL

Pill overload:
NONE / FAIL

Badge overload:
NONE / FAIL

Gradients:
NONE / FAIL

Glassmorphism:
NONE / FAIL

Decorative shadows:
NONE / FAIL

Random icons:
NONE / FAIL

Fake copy/claims:
NONE / FAIL

Promotional clutter:
NONE / FAIL

Brand-brown overuse:
NONE / FAIL

Decorative motion:
NONE / FAIL


VALIDATION

Category route tests:
PASS / FAIL

404 semantics:
PASS / FAIL

Empty-category semantics:
PASS / FAIL

Slug contract:
PASS / FAIL

Product category filtering:
PASS / FAIL

ProductCard reuse:
PASS / FAIL

Homepage integration:
PASS / FAIL

Homepage regression:
PASS / FAIL

API client:
PASS / FAIL

Layout contract:
PASS / FAIL

Responsive contract:
PASS / FAIL

State contract:
PASS / FAIL

Routing contract:
PASS / FAIL

Request-first:
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


RUNTIME

Top-level category:
PASS / FAIL / NOT AVAILABLE

Nested category:
PASS / FAIL / NOT AVAILABLE

Category with products:
PASS / FAIL / NOT AVAILABLE

Empty category:
PASS / FAIL / NOT AVAILABLE

Missing category:
PASS / FAIL

Real Laravel integration:
PASS / NOT AVAILABLE

Unavailable checks:
<exact explanation>


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

git-workflow-and-versioning skill read:
YES / NO

Operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 14.2:
PASS / BLOCKED

Phase 14.3:
READY / BLOCKED
```

---

# 142. STOP Condition

Phase 14.2 may be declared PASS only when:

- `/categories/[slug]` is implemented using the App Router;
- the page is server-first;
- category identity comes from Laravel;
- canonical backend-returned slugs are used;
- no frontend slug generation exists;
- numeric database IDs never appear in category URLs;
- missing categories produce the established 404;
- unexpected failures are not mislabeled as 404;
- valid empty categories remain HTTP-valid category pages;
- category products are retrieved through the actual frozen API contract;
- no silent fixture fallback exists;
- the Phase 14.1 canonical ProductCard is reused;
- no CategoryProductCard fork exists;
- the frozen `--media-product-card` authority remains intact;
- no second token/component/layout authority is created;
- existing SiteSection/ContentContainer/MUI primitives are reused;
- any breadcrumb is semantic and hierarchy-backed;
- child-category discovery uses authoritative taxonomy data;
- homepage category links are activated where their destinations now genuinely exist;
- no dead links or `href="#"` exist;
- no generic `/products` listing experience has been implemented prematurely;
- product detail remains Phase 14.4;
- filters/sorting remain Phase 14.6;
- comprehensive SEO remains Phase 14.7;
- structured data remains Phase 14.8;
- sitemap/robots remain Phase 14.9;
- R2/media optimization remains outside this phase;
- MADE_TO_ORDER remains a first-class offering;
- no cart/checkout/buy-now/wishlist/review/sale behavior appears;
- no fake category descriptions, promotions, statistics or claims exist;
- Urban Ladder remains structural/IA reference only;
- no copied competitor visuals/assets/copy exist;
- responsive behavior consumes Phase 13.9;
- no horizontal overflow exists;
- 200% reflow remains usable;
- accessibility checks pass;
- server-first architecture remains intact;
- no unnecessary client state is added;
- no dependencies are added;
- Phase 14.1 remains passing;
- API/layout/responsive/state/routing/request-first/theme/design-system regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations comply with `git-workflow-and-versioning`.

Then report:

```text
Phase 14.2 — PASS
Phase 14.3 — READY
```

Do not start Phase 14.3 automatically.

**The category page must extend the storefront architecture established by 14.1, not start another design exercise. Frozen tokens remain authoritative, `ProductCard` remains canonical, Laravel remains the taxonomy authority, and Urban Ladder remains a structural reference rather than something to copy.**