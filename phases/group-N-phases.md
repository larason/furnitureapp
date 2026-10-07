# Phase 14.4 — Product Detail Page

## Entry State

```text
Phase 14.1 — PASS
Phase 14.2A — PASS
Phase 14.2 — PASS
Phase 14.3 — PASS
Phase 14.4 — ACTIVE
```

Implement the canonical public Product Detail Page (PDP) for SL Furnitures.

Expected route:

```text
/products/[slug]
```

Verify the frozen route before coding.

Do NOT start Phase 14.5 or later phases.

---

# 1. Objective

Build the canonical public furniture detail experience where a visitor can understand an individual product sufficiently to decide whether to continue into the appropriate business workflow.

The page should clearly answer:

```text
What is this furniture piece?
What does it look like?
What does it cost?
What type of product is it?
Is it available?
What details/specifications are authoritative?
What options/variants exist?
Is it made to order?
What is the legitimate next action?
```

The PDP must feel:

```text
architectural
warm
editorial
calm
material-led
photography-led
spacious
premium but approachable
```

It must NOT become:

```text
Amazon product page
generic Shopify PDP
Urban Ladder clone
marketplace detail page
sales funnel
badge wall
specification dashboard
```

---

# 2. Read Before Coding

Read and follow the actual repository authority:

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
  ROUTING.md
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

Explicitly inspect implementations from:

```text
13.5 API client
13.7 layout system
13.8 failure states
13.9 responsive foundation

14.1 homepage
14.2 category pages
14.2A category hard-404 remediation
14.3 product listing
```

Do not implement from this prompt alone.

Repository contracts win.

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
tokens
secrets
```

Use an atomic Phase 14.4 commit.

---

# 4. Frozen Product Contract

The frozen catalog decisions establish:

```text
CAT-002
GET /api/v1/products/{product}
```

as the public product-detail resource.

Verify the exact contract.

The endpoint resolves a product by:

```text
public slug
or
opaque machine ID
```

but the website route uses the backend-provided **slug**.

Do NOT generate a slug client-side.

Do NOT expose numeric database IDs in website URLs.

---

# 5. Canonical Website Route

Expected:

```text
/products/[slug]
```

Examples conceptually:

```text
/products/amani-lounge-chair
/products/mkongo-dining-table
```

Do not create aliases such as:

```text
/product/[slug]
/furniture/[slug]
/item/[slug]
/shop/[slug]
```

---

# 6. Public Access

Product detail is public catalog.

Required:

```text
authentication: NONE
Clerk requirement: NONE
customer session requirement: NONE
```

A visitor must be able to view:

```text
product
price
public availability
images
description
variants/public specifications
```

without authentication.

Do not introduce Clerk during 14.4.

---

# 7. Full Detail vs Product Summary

Do NOT use the listing representation as if it were the full PDP contract.

The frozen architecture distinguishes:

```text
Product Summary
→ collection/listing use

Full Product Detail
→ individual product page
```

The detail representation should be inspected for authoritative fields such as:

```text
id
slug
name
product_type
price
category
availability
stock_indicator
description
images[]
variants[]
timestamps
```

Use only fields actually present in the frozen contract.

Do not fabricate fields because furniture websites commonly have them.

---

# 8. Laravel Is Product Authority

Laravel owns:

```text
identity
slug
name
description
product type
price
currency
category
images
variants
availability
stock indicator
publication state
```

The frontend is presentation only.

Do not infer business state from UI assumptions.

---

# 9. Frozen Design System — ABSOLUTE RULE

The implementation hierarchy remains:

```text
tokens.css
    ↓
MUI theme
    ↓
existing primitives
    ↓
existing commerce components
    ↓
PDP composition
```

Do NOT create a PDP-specific design system.

---

# 10. Explicit Token Requirement

Every visual decision must use the frozen system.

Do NOT invent:

```text
colors
font sizes
font families
spacing
radii
shadows
elevation
breakpoints
motion
focus styles
container widths
media ratios
```

Expected audit:

```text
Unapproved ad-hoc design values:
NONE
```

---

# 11. Do Not Reinvent Components

Before creating ANY component, search the existing implementation.

Reuse where applicable:

```text
SiteShell
SiteSection
ContentContainer
NavLink
ProductCard
ProductGrid
existing breadcrumb implementation
existing price formatter
existing media handling
existing state components
MUI primitives
Next.js Image
```

Do not recreate them under PDP-specific names.

---

# 12. Component Creation Gate

A new component is justified only when it owns a stable reusable responsibility.

Before adding one, answer:

```text
What responsibility does it own?

Why can existing composition not express it cleanly?

Is it furniture/product-domain reusable?

Does MUI already provide the primitive?

Does an existing project component already own this behavior?
```

---

# 13. Forbidden Reinventions

Do NOT create redundant components such as:

```text
ProductDetailButton
ProductDetailTypography
ProductDetailContainer
PdpSection
PdpBreadcrumb
ProductDetailPrice
FurnitureButton
ProductDetailProductCard
```

unless a genuine reusable responsibility demonstrably requires one.

---

# 14. ProductCard Activation

Phase 14.3 intentionally left product detail navigation for 14.4.

Now activate canonical product navigation where appropriate in the existing `ProductCard`.

Expected:

```text
ProductCard
→ /products/{backendSlug}
```

This should activate product discovery from:

```text
homepage
category pages
/products
future search results
```

without page-specific card forks.

---

# 15. ProductCard Must Stay Canonical

Do NOT create:

```text
LinkedProductCard
ProductCardLink
ClickableProductCard
ListingProductLinkCard
```

merely because links are now active.

Improve the canonical `ProductCard` contract if necessary.

---

# 16. ProductCard Link Semantics

Use real navigation semantics.

Do not make an entire complex card a fake button.

Ensure:

```text
keyboard accessibility
clear focus
valid link nesting
no nested interactive conflicts
```

Since no wishlist/quick-add controls exist, the card interaction model should remain simple.

---

# 17. Server-First PDP

The page must remain server-first.

Expected:

```text
app/products/[slug]/page.tsx
→ Server Component
```

Do not add:

```tsx
"use client";
```

to the entire PDP.

---

# 18. Dynamic Params

Use the installed Next.js 16.3.8 App Router conventions.

Do not copy outdated examples for synchronous route params.

Verify the installed framework behavior.

---

# 19. API Client

Use the canonical:

```text
frontend/web/lib/api/client.ts
```

Do not introduce:

```text
Axios
SWR
React Query
new fetch wrapper
frontend BFF
API route proxy
```

A thin catalog-domain helper may be reused/extended if 14.1–14.3 already established one.

---

# 20. Cache Policy

Inspect the public catalog fetch policy established by 14.1–14.3.

Current reported policy is:

```text
no-store
```

Remain consistent unless repository authority now says otherwise.

Do not introduce caching architecture during this phase.

---

# 21. Missing Product Semantics

A genuinely nonexistent/unpublished product must render the canonical not-found UI.

Concept:

```text
Laravel:
GET /api/v1/products/definitely-not-real
→ 404 RESOURCE_NOT_FOUND

Next.js:
/products/definitely-not-real
→ canonical not-found UI
```

---

# 22. HARD 404 Is Required

Phase 14.2 exposed an important Next.js streaming issue.

Do NOT repeat it unnoticed.

A missing product page must return:

```text
HTTP 404
```

not:

```text
HTTP 200 + noindex + not-found UI
```

---

# 23. Inspect Phase 14.2A Before Solving 404

Phase 14.2A already introduced a narrow hard-404 preflight architecture.

Inspect:

```text
frontend/web/proxy.ts
```

before implementing product hard-404 behavior.

Do NOT invent an unrelated second mechanism if the existing architecture can safely support product resources.

---

# 24. Proxy Extension Gate

If the correct architecture is to extend the existing preflight mechanism from categories to product detail routes, do so carefully.

The matcher must distinguish:

```text
/products
```

from:

```text
/products/[slug]
```

The collection route MUST NOT receive product-detail preflight.

Required:

```text
/products
→ no product existence preflight

/products/[slug]
→ product resource preflight if required for hard 404
```

---

# 25. No Proxy Data Authority

Even if preflight is required:

```text
proxy
→ status/existence concern

page
→ rendering/data authority
```

Do not turn proxy code into the PDP renderer/data source.

---

# 26. No Error Masking

Only an authoritative upstream product:

```text
404 RESOURCE_NOT_FOUND
```

may become a product 404.

Do NOT convert:

```text
500
502
503
timeout
network failure
invalid API response
```

into 404.

Unexpected failures continue through the established failure architecture.

---

# 27. Preflight Tradeoff

If hard 404 requires two product-detail requests in separate server contexts, document it explicitly as the same deliberate pre-stream status tradeoff established by Phase 14.2A.

Do not hide the cost.

Do not introduce a cache solely to disguise it.

---

# 28. Product Page Composition

Use an original SL Furnitures composition.

A strong PDP hierarchy is likely:

```text
breadcrumb
    ↓
primary product media / gallery
    +
product information
    ↓
description / product story
    ↓
variant/specification information where authoritative
    ↓
appropriate request-first next action
```

Exact composition must come from:

```text
actual product contract
frozen design system
responsive foundation
```

not competitor copying.

---

# 29. Urban Ladder — REFERENCE ONLY

Urban Ladder may be examined only as a structural furniture-commerce reference.

Useful questions:

```text
How does a furniture PDP prioritize photography?
Where is product identity placed?
How are specifications separated from merchandising?
How does mobile reorder gallery and information?
How is visual density controlled?
```

---

# 30. Urban Ladder Is NOT Visual Authority

Do NOT copy:

```text
exact PDP layout
gallery implementation
thumbnail treatment
price block
CTA design
specification layout
accordions
badges
promotions
typography
colors
spacing
radii
shadows
copy
imagery
icons
```

The resulting page must clearly be SL Furnitures.

---

# 31. Product Photography Is Primary

Furniture photography should carry most visual richness.

UI chrome should remain quiet.

Do not bury the product behind:

```text
cards
panels
badges
decorative borders
shadows
colored boxes
```

---

# 32. Product Gallery

Use the authoritative:

```text
images[]
```

from the product-detail representation.

Inspect its exact fields before coding.

Potential fields may include:

```text
url
alt_text
is_primary
sort_order
```

but do not assume names.

---

# 33. Image Ordering

Respect backend-provided ordering.

Do not independently sort images unless the contract explicitly requires the frontend to do so.

The full detail representation is expected to provide the sorted gallery.

---

# 34. Primary Image

Use backend primary/order semantics.

Do not guess primary media from:

```text
array position
filename
largest dimensions
```

unless the frozen API explicitly guarantees the array order for this purpose.

---

# 35. Image Alt

Use authoritative image alt text when supplied.

Where a contractually valid image lacks alt text, apply the existing semantic media fallback convention.

Do not stuff keywords into alt text.

---

# 36. Gallery Must Work Without JavaScript

The core product imagery must remain available in server-rendered HTML.

Do not build a gallery where the product has no usable image until hydration.

---

# 37. Gallery Interaction

Do NOT introduce a complicated carousel library.

No new dependency is expected.

Prefer:

```text
server-rendered gallery
CSS layout
native/MUI primitives
minimal progressive enhancement only if genuinely necessary
```

---

# 38. Gallery on Desktop

Furniture deserves substantial imagery.

Possible structural approaches include:

```text
large lead image + secondary gallery
editorial image grid
lead image + restrained thumbnail navigation
```

Choose based on the actual image contract and frozen design system.

Do not automatically imitate Urban Ladder.

---

# 39. Gallery on Mobile

Mobile must prioritize:

```text
clear product image
product identity
price/state
description/action
```

without excessive scrolling caused by blindly stacking every large gallery image before product information.

Find a balanced composition.

---

# 40. Do Not Invent Gallery Media Ratio

Inspect the frozen media tokens.

Use an existing approved product-detail/gallery ratio if one exists.

If no product-detail ratio exists, first determine whether intrinsic image dimensions or existing media tokens solve the layout.

Do NOT silently add:

```text
4:5
1:1
3:2
```

as a new design authority.

If a genuinely missing global semantic token is discovered, STOP and report it rather than burying a design-system change inside the PDP.

---

# 41. `next/image`

Use Next.js Image through the established media architecture.

Do not manually generate WebP files.

Do not introduce a second image loader.

---

# 42. R2

Existing Cloudflare R2 media architecture already exists in Laravel.

The PDP consumes API-provided public media URLs.

Do NOT:

```text
create R2 infrastructure
install R2 SDK
upload images from frontend
duplicate media configuration
```

---

# 43. Remote Media Configuration

Reuse the media-host allow-list/configuration established during 14.1.

Do not introduce a second environment variable for the same media origin.

---

# 44. Image `sizes`

Set meaningful responsive `sizes` based on actual PDP geometry.

Do not copy ProductCard's listing `sizes` blindly.

PDP media is materially larger.

---

# 45. Image Loading Priority

Only the true initial lead image should be considered for preload/high priority.

Do not eagerly load the entire gallery.

---

# 46. Stable Geometry

Avoid layout shift.

Image containers must have stable geometry.

---

# 47. Missing Images

A product with zero images must still render a valid PDP.

Use the established restrained media-empty treatment.

Do not inject:

```text
stock photography
random fixture image
competitor image
AI-generated replacement
```

in production.

---

# 48. Product Identity

The information area should prioritize factual hierarchy:

```text
category/context
product name
price
product type / public availability
appropriate next action
```

Exact ordering may adapt to the actual design.

---

# 49. H1

Exactly one H1.

The H1 should be the authoritative product name.

Do not add a separate marketing H1 above it.

---

# 50. Breadcrumb

Reuse the existing breadcrumb architecture from category/listing pages.

Conceptually:

```text
Home
/
Furniture
/
<Category>
/
<Product>
```

Use only destinations that actually exist.

Do not create dead breadcrumb links.

---

# 51. Category Link

Since Phase 14.2 implemented category pages, the authoritative product category may link to:

```text
/categories/{backendCategorySlug}
```

provided the detail representation actually supplies the category slug.

Do not generate one locally.

---

# 52. Price

Reuse the canonical price formatter established earlier.

Do NOT create:

```text
formatPdpPrice
productDetailPriceFormatter
```

Money remains:

```text
integer minor units at API boundary
+
currency
```

No floating-point business arithmetic.

---

# 53. Price Copy

Do not invent:

```text
starting from
sale price
discount
finance price
monthly payment
```

unless the frozen product contract actually provides the semantics.

---

# 54. Product Type

Respect the CLOSED enum:

```text
IN_STOCK
MADE_TO_ORDER
```

Do not invent a third UI state.

---

# 55. Availability

Respect the frozen distinction:

```text
availability:
available | unavailable

stock_indicator:
IN_STOCK | LOW_STOCK | MADE_TO_ORDER
```

Do not collapse these into an invented enum.

---

# 56. Availability Is Informational

Public availability is coarse information.

Never display:

```text
physical_quantity
reserved_quantity
available_quantity
warehouse quantities
supplier data
internal inventory records
```

---

# 57. LOW_STOCK

If `stock_indicator=LOW_STOCK`, present only the approved public semantic.

Do not translate it into fabricated urgency such as:

```text
Only 2 left!
Hurry!
Selling fast!
```

---

# 58. MADE_TO_ORDER Is First-Class

MADE_TO_ORDER is not:

```text
unavailable
error
warning
disabled product
second-class listing
```

It is a primary SL Furnitures offering.

The PDP should explain it calmly and provide the appropriate request pathway when that route exists.

---

# 59. Critical Deployment Decision — REQUEST ONLY

Inspect `docs/decisions.md` for the latest deployment-mode decision.

The currently supplied ADR states:

```text
Initial Production Commerce Mode — Request Only
```

with initial production publishing MADE_TO_ORDER products and purchase intent going through the Made-to-Order Request workflow.

Do NOT assume that:

```text
Group H PASS
```

automatically repeals this deployment decision.

Only a newer authoritative ADR may supersede it.

---

# 60. No Cart/Checkout CTA Under Current Deployment Mode

Unless the repository now contains a newer explicit decision enabling transactional purchasing, Phase 14.4 must NOT expose:

```text
Add to cart
Buy now
Checkout
Payment
Quantity selector for purchase
```

even for an `IN_STOCK` product.

The frozen API may support future commerce contracts while the current deployment remains request-only.

---

# 61. Do Not Delete Future Commerce Semantics

Request-only deployment does NOT mean rewriting or deleting the frozen backend contracts for future:

```text
cart
checkout
orders
payment
```

This is a frontend deployment-scope constraint.

---

# 62. Made-to-Order Request Action

The correct domain action for MADE_TO_ORDER is the Request Furniture workflow.

However, verify whether its website route has actually been implemented.

If:

```text
/furniture-requests
```

does NOT yet exist, do not create a dead link.

---

# 63. Do Not Steal Future Phase Work

If the request route belongs to a later frontend phase:

```text
render truthful non-interactive/request-context information
or
use an already-implemented legitimate destination
```

Do not implement the entire request form during 14.4.

---

# 64. No Fake CTA

Forbidden:

```text
href="#"
Request now → dead route
Contact us → nonexistent route
Call us → fabricated phone number
WhatsApp → fabricated account
```

Every interactive action must work.

---

# 65. Variants

The frozen Product Detail representation includes full variants.

Inspect the actual representation.

Do not assume variants mean:

```text
color picker
fabric picker
size selector
configurator
```

unless the API actually supplies enough structured semantics.

---

# 66. Variant Data Is Authoritative

Use only contract fields.

Potential concepts might include:

```text
variant id
SKU
dimensions
color
fabric
price
active/public state
```

but inspect the API.

Do not invent missing variant metadata.

---

# 67. Variant Presentation

Phase 14.4 may present variant information/specifications.

It should NOT create a sophisticated purchase/configuration engine.

Prefer factual presentation over simulated configurability.

---

# 68. Variant Selector Gate

Do not create an interactive variant selector unless it has an actual Phase 14.4 business consequence.

Under request-only deployment, a selector that merely looks purchasable but cannot feed a legitimate workflow is misleading.

---

# 69. No Fake Swatches

Do not turn color names into arbitrary CSS swatches.

A value like:

```text
Walnut
```

does not authorize the frontend to invent a hexadecimal walnut color.

---

# 70. No Fake Fabric Thumbnails

None unless supplied by authoritative media.

---

# 71. SKU

If SKU is public according to the contract, it may be displayed as restrained metadata.

Do not expose internal database IDs as SKU substitutes.

---

# 72. Dimensions

If structured dimensions exist in the detail/variant contract, present them accurately.

Do not concatenate values ambiguously.

Preserve units supplied/defined by the contract.

---

# 73. Product Description

Render authoritative product description.

Do not rewrite it into marketing prose.

Do not generate additional product claims.

---

# 74. Description Semantics

If description is plain text, treat it as plain text.

Do not render arbitrary backend text as HTML unless the frozen contract explicitly establishes sanitized rich content.

No `dangerouslySetInnerHTML` without an approved trusted/sanitized content architecture.

---

# 75. Materials / Room / Style Metadata

The database architecture may contain materials, room tags and style tags.

Do NOT display them unless CAT-002 actually serializes them publicly.

Database existence does not equal API contract exposure.

---

# 76. Specifications

If the API exposes enough factual fields, use a restrained specification area.

Potential structure:

```text
Dimensions
Material
Colour
Fabric
SKU
Variant
```

but only include fields actually supported.

Do not create empty specification labels.

---

# 77. Specification UI

Avoid dashboard-like tables when simple definition semantics suffice.

Consider semantic:

```html
<dl>
```

or equivalent MUI composition.

Use the simplest accessible representation.

---

# 78. No Decorative Icons for Specs

Do not add icons beside every:

```text
dimension
material
colour
SKU
```

Icons should clarify actions/status, not decorate metadata.

---

# 79. No Trust-Badge Row

Do not invent:

```text
Secure payment
Premium quality
Fast delivery
Best price
Quality guaranteed
Easy returns
```

unless those are documented business promises.

---

# 80. No Delivery Promise

Do not display:

```text
Delivered in 3 days
Ships tomorrow
Free delivery
```

without authoritative business data.

---

# 81. No Reviews

Do not add:

```text
stars
rating
review count
customer reviews
```

Reviews are not part of this frozen MVP surface.

---

# 82. No Wishlist

Do not add wishlist/favourite controls.

---

# 83. No Social Share Widget

Do not add social sharing UI in this phase.

---

# 84. No Promotional Urgency

No:

```text
limited time
flash sale
selling fast
X people viewing
```

---

# 85. No Cross-Sell Yet Unless Explicitly Owned

Do not add:

```text
You may also like
Related products
Complete the room
Recently viewed
```

merely because PDPs commonly have them.

Phase 14.10 owns comprehensive internal linking, and recommendation behavior requires an authoritative rule.

Keep Phase 14.4 focused.

---

# 86. ProductGrid Boundary

Do not use `ProductGrid` simply to add unrelated recommendations.

ProductGrid remains canonical for actual product collections.

---

# 87. Responsive Desktop Composition

A reasonable structural model is:

```text
media region | information region
```

at appropriate desktop widths.

But use canonical breakpoints and actual content needs.

Do not hard-code a competitor's proportions.

---

# 88. Mobile Composition

On mobile, content should recompose naturally.

Likely priority:

```text
breadcrumb/context
product image
product name
price/state
relevant action/context
description/specifications
remaining media
```

But validate against actual gallery approach.

Do not simply shrink the desktop layout.

---

# 89. Sticky Product Information

Do NOT add a sticky purchase panel merely because many ecommerce sites do.

Under request-only deployment it is particularly unnecessary unless a real usability requirement is demonstrated.

---

# 90. Mobile Sticky CTA

Do NOT add a bottom sticky CTA in this phase.

---

# 91. No Viewport Detection

Forbidden:

```text
window.innerWidth
navigator.userAgent
device-specific rendering
```

Use canonical CSS/MUI responsive behavior.

---

# 92. Canonical Breakpoints

Use only the established responsive authority.

No PDP-specific raw media-query widths.

---

# 93. Content Width

Reuse:

```text
ContentContainer
SiteSection
```

Do not create a PDP-specific page-width system.

---

# 94. Spacing

Use frozen spacing tokens.

Parent owns external spacing.

Components own internal spacing.

No magic pixel gaps.

---

# 95. Typography

Use the frozen typography contract.

Young Serif:

```text
product name / appropriate editorial display role
```

Utility sans:

```text
price
availability
variant metadata
specifications
controls
breadcrumbs
```

Do not turn the entire PDP into display typography.

---

# 96. Product Name Scaling

Use approved typography tokens/responsive mappings.

Do not introduce a custom `clamp()` merely for PDP.

---

# 97. Color

Photography carries color.

Use established semantic surfaces/text/actions.

Deep brown remains a controlled accent.

Do NOT make every:

```text
heading
CTA
border
icon
status
```

brown.

---

# 98. Flat-First

Prefer:

```text
spacing
typographic hierarchy
photography
subtle separators where approved
```

over:

```text
cards
shadows
floating panels
outlined boxes
```

---

# 99. Shape

Product media should respect frozen media/shape semantics.

Do not round every gallery image heavily.

---

# 100. Motion

No:

```text
parallax
zoom-on-scroll
spring gallery
floating animation
scroll reveal
stagger
```

Any interaction motion must use approved tokens and respect reduced motion.

---

# 101. Accessibility

Follow the frozen accessibility baseline.

Verify:

```text
one H1
one main
logical heading hierarchy
meaningful alt
keyboard navigation
visible focus
touch targets
non-color status communication
200% reflow
reduced motion
```

---

# 102. Gallery Accessibility

If gallery controls exist:

```text
controls need accessible names
current image/state must be understandable
thumbnail images require appropriate semantics
keyboard use must work
focus must remain visible
```

Do not make images keyboard-focusable merely because they are images.

---

# 103. Zoom/Lightbox

Do not add a lightbox unless clearly justified.

If no robust accessible implementation exists without adding unnecessary complexity/dependencies, defer it.

Core PDP must not depend on a lightbox.

---

# 104. Empty Gallery Accessibility

Missing product media must have a meaningful non-image treatment rather than a broken image with meaningless alt.

---

# 105. Long Content

Test:

```text
long product names
long descriptions
long variant names
long material/fabric values
large prices
```

Do not solve by truncating authoritative information aggressively.

---

# 106. 200% Reflow

At 200% equivalent zoom:

```text
no horizontal document scrolling
product identity remains visible
gallery remains usable
price/state remain readable
specifications reflow
```

---

# 107. Narrow Mobile

Verify at 320px.

Particularly inspect:

```text
breadcrumbs
long H1
price
availability
gallery
specifications
action copy
```

---

# 108. Wide Desktop

Do not allow the product information column to become excessively wide.

Respect canonical content width.

---

# 109. Fixture Policy

Production/default mode remains API-backed.

Do not fall back to fixtures after product API failure.

Forbidden:

```text
try API
catch
return fixture product
```

---

# 110. Fixture PDP

If the existing development fixture system can support a product detail page cleanly, it may be extended strictly for visual development.

Requirements:

```text
explicit development-only mode
visible fixture notice
same product-detail component/data contract
no fixture imports inside reusable visual components
no fallback-on-error
```

---

# 111. Do Not Create a Second Fixture Architecture

Reuse the existing Phase 14.1 fixture mechanism.

Do not add:

```text
PDP_USE_FAKE_DATA
PRODUCT_DETAIL_MOCK
mockProduct.ts
```

as competing configuration.

---

# 112. Fixture Images

If fixture PDP data is used, reuse curated furniture imagery where suitable.

Do not add dozens of unnecessary images merely to simulate a giant gallery.

A small representative set is sufficient.

---

# 113. Real Local API State

The local database was previously verified empty.

Therefore a real product detail may still be unavailable.

Do NOT seed production/test data merely for visual verification.

Report truthfully:

```text
Real product detail:
NOT AVAILABLE
```

if the database remains empty.

---

# 114. Real Missing Product Test

Even with an empty database, this is testable.

Verify Laravel:

```text
GET /api/v1/products/definitely-nonexistent-phase-14-4
→ HTTP 404
→ RESOURCE_NOT_FOUND
```

Then verify Next.js:

```text
/products/definitely-nonexistent-phase-14-4
→ HTTP 404
→ canonical not-found UI
```

in both:

```text
next dev
next start
```

---

# 115. Hard-404 Browser Verification

At minimum inspect:

```text
canonical not-found UI
one main
one H1
keyboard navigation
visible focus
no horizontal overflow
no console errors
```

---

# 116. Product Detail Runtime

If fixture mode exists, visually verify the populated PDP separately from real API hard-404 verification.

Clearly report:

```text
API-backed missing-product evidence
vs
fixture visual evidence
```

Do not imply fixture data came from Laravel.

---

# 117. ProductCard Link Regression

After activating product links, verify from:

```text
homepage
category page
/products
```

that URLs use:

```text
/products/{backendSlug}
```

No locally generated slug.

---

# 118. Empty Catalog Regression

The real:

```text
/products
```

must remain HTTP 200 with the current empty API collection.

Do not let product-detail hard-404 infrastructure affect it.

---

# 119. Category Hard-404 Regression

Verify:

```text
/categories/definitely-nonexistent-phase-14-2
→ HTTP 404
```

still works.

---

# 120. Proxy Matcher Regression

Explicitly test:

```text
/products
/products/definitely-nonexistent-phase-14-4
/categories/definitely-nonexistent-phase-14-2
/
```

Expected:

```text
/products
→ no detail preflight

/products/{slug}
→ detail hard-404 mechanism

/categories/{slug}
→ existing category mechanism

/
→ unaffected
```

---

# 121. No Duplicate Preflight on Collection

Hard requirement:

```text
/products
```

must not cause an extra CAT-002 request.

---

# 122. API Failure Test

Test a non-404 upstream failure.

Expected:

```text
NOT converted to not-found
```

It must remain an unexpected API failure and follow Phase 13.8 architecture.

---

# 123. Product Detail Contract Tests

Add a focused command following project convention, preferably:

```text
npm run test:product-detail
```

if consistent with existing scripts.

---

# 124. Minimum Product Detail Tests

Cover:

```text
canonical route uses backend slug
CAT-002 detail endpoint used
canonical API client reused
server-first page
404 becomes canonical not-found
hard 404 mechanism does not affect /products
non-404 upstream failure is not masked
ProductCard links activate using backend slug
price formatter reused
product type semantics preserved
availability semantics preserved
raw inventory never rendered
MADE_TO_ORDER not treated as error
no transactional CTA under current deployment mode
no fake request link
gallery consumes authoritative images
missing gallery is safe
description renders safely
variant data is factual
no duplicate design/component system
```

---

# 125. Gallery Tests

Where practical cover:

```text
zero images
one image
multiple images
authoritative order
primary image semantics
alt text
stable geometry
lead-image priority
secondary-image lazy behavior
```

---

# 126. Variant Tests

Where supported by actual contract:

```text
zero variants
one variant
multiple variants
long values
optional/null fields
variant-specific price if contract exposes it
```

Do not write tests for invented fields.

---

# 127. MADE_TO_ORDER Tests

Required:

```text
MADE_TO_ORDER
→ normal valid PDP
→ not warning/error
→ no Add to cart
→ no Buy now
→ no checkout
→ request workflow represented only if legitimate implemented destination exists
```

---

# 128. IN_STOCK Under Request-Only Deployment

Unless a newer ADR supersedes request-only production:

```text
IN_STOCK
→ may display factual product type/availability
→ must NOT expose transactional purchase CTA
```

This prevents frontend deployment policy from drifting away from repository authority.

---

# 129. ProductCard Regression Tests

Run and extend existing product tests so activation of links does not break:

```text
homepage
categories
/products
```

---

# 130. Existing Test Suites

Run at minimum the established equivalents of:

```text
npm run test:product-detail
npm run test:products
npm run test:category
npm run test:homepage
npm run test:api
npm run test:theme
npm run test:layout
npm run test:responsive
npm run test:states
npm run test:routing
```

Use actual available script names.

Do not invent failing commands merely to match this prompt.

---

# 131. TypeScript

Must pass.

---

# 132. ESLint

Must pass.

---

# 133. Production Build

Must pass.

The build must not require a populated Laravel database.

---

# 134. Browser Widths

Verify representative widths:

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

For populated fixture visual testing, focus particularly on:

```text
320
390
960
1440
```

---

# 135. Horizontal Overflow

Required:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

Do not fix overflow with:

```css
overflow-x: hidden;
```

on the document.

Fix the source.

---

# 136. Visual Verification

Inspect:

```text
gallery prominence
product name hierarchy
price hierarchy
availability clarity
MADE_TO_ORDER treatment
description readability
variant/specification readability
mobile composition
desktop composition
image cropping
long content
missing image behavior
focus state
```

---

# 137. No AI-Slop Audit

Explicitly inspect for:

```text
excessive cards
pill badges everywhere
rounded containers everywhere
decorative icons
gradient surfaces
glassmorphism
huge ornamental headings
fake luxury prose
fake trust claims
fake urgency
generic ecommerce CTA clutter
```

Expected:

```text
NONE
```

---

# 138. Token Audit

Search Phase 14.4 changes for:

```text
raw hex colors
arbitrary spacing
arbitrary font sizes
arbitrary line heights
custom radius
custom shadow
raw breakpoint widths
custom transition timing
new media ratio
```

Expected:

```text
NONE
```

unless an existing approved token literally resolves to that value through the theme.

Do not bypass tokens by copying their raw value locally.

---

# 139. Component Reinvention Audit

For each newly created component report:

```text
Component:
<name>

Responsibility:
<responsibility>

Existing project components inspected:
<list>

MUI primitives considered:
<list>

Why composition alone was insufficient:
<reason>

Reusable outside PDP:
YES / NO
```

If the answer reveals the component is merely a styling wrapper, remove it.

---

# 140. Client Boundary Audit

List every Phase 14.4 file containing:

```tsx
"use client"
```

Expected:

```text
NONE
```

unless a narrowly scoped gallery interaction genuinely requires it.

If one exists, explain:

```text
why server HTML alone was insufficient
why the boundary is narrow
what JS it adds
how no-JS core content remains usable
```

---

# 141. Performance

Do not add a dependency.

Do not ship a large gallery library.

Do not preload all images.

Do not client-render the entire PDP.

Do not duplicate catalog data in client state.

---

# 142. Phase Boundaries

Do NOT implement:

```text
14.5 Search
14.6 Filters/sorting
14.7 comprehensive SEO metadata
14.8 structured data
14.9 sitemap/robots
14.10 comprehensive internal linking
14.11 comprehensive image/performance optimization
```

---

# 143. SEO Boundary

The PDP must still be:

```text
public
server-rendered
semantic
crawlable
slug-based
```

But comprehensive product metadata belongs to 14.7.

Do not consume that phase now.

---

# 144. Structured Data Boundary

Do NOT add:

```text
Product JSON-LD
Offer JSON-LD
BreadcrumbList JSON-LD
```

Phase 14.8 owns it.

---

# 145. Internal Linking Boundary

Functional breadcrumb/category/ProductCard links are allowed.

Do not build:

```text
related products
recommendation engine
room graph
style graph
```

during 14.4.

---

# 146. Image Optimization Boundary

Use `next/image` correctly now.

Do not prematurely implement the full Phase 14.11 optimization strategy.

---

# 147. Backend Boundary

Expected backend changes:

```text
NONE
```

CAT-002 already exists.

If frontend implementation discovers that CAT-002 lacks a field required by the frozen contract, STOP and report the backend contract/implementation defect.

Do not silently patch Laravel from Phase 14.4.

---

# 148. Flutter Boundary

Flutter changes:

```text
NONE
```

---

# 149. Dependencies

Expected:

```text
NONE
```

---

# 150. Documentation

Update:

```text
phases/group-N-phases.md
```

with the Phase 14.4 execution record.

Update `frontend/AGENTS.md` only for genuinely durable newly established rules.

Do not duplicate existing design-system rules.

---

# 151. ADR

Expected:

```text
NONE
```

Ordinary PDP composition does not need an ADR.

If the agent believes an ADR is necessary, explain the unresolved architectural choice before creating one.

---

# 152. Runtime Environment

Use the established local setup:

```text
Laravel:
http://127.0.0.1:8000

Next.js:
available local port

frontend/web/.env.local:
API_BASE_URL=http://127.0.0.1:8000
```

Do not commit `.env.local`.

Do not introduce:

```text
NEXT_PUBLIC_API_BASE_URL
```

---

# 153. Completion Report

Return:

```text
Phase 14.4 status:
PASS / BLOCKED


ROUTE

Canonical route:
<actual>

Page:
<path>

Identifier:
BACKEND SLUG / FAIL

Locally generated slug:
NONE / FAIL

Server Component:
YES / NO


API

Endpoint:
<actual>

Representation:
FULL PRODUCT DETAIL / <other>

Public:
YES / NO

API client reused:
YES / NO

Direct fetch outside client:
NONE / FAIL

Cache policy:
<actual>

Fixture fallback after API error:
NONE / FAIL


HARD 404

Laravel missing product:
HTTP <status>

Next dev missing product:
HTTP <status>

Next start missing product:
HTTP <status>

Canonical not-found UI:
PASS / FAIL

Non-404 upstream failure masked as 404:
NO / FAIL

Mechanism:
<summary>

Preflight tradeoff:
<summary>

Collection /products preflight:
NONE / FAIL


PRODUCTCARD ACTIVATION

Canonical ProductCard reused:
YES / NO

ProductCard fork:
NONE / FAIL

Homepage links:
<status>

Category links:
<status>

Listing links:
<status>

URL source:
BACKEND SLUG / FAIL


PAGE COMPOSITION

Rendered hierarchy:
1. <...>
2. <...>
3. <...>

H1:
<product-name behavior>

Breadcrumb:
<summary>

Product information:
<summary>

Description:
<summary>

Specifications:
<summary>

Variants:
<summary>

Primary action/context:
<summary>


GALLERY

API images[] used:
YES / NO

New media authority:
NONE / FAIL

Lead image:
<selection semantics>

Ordering:
<authority>

Alt:
<behavior>

Zero images:
<behavior>

One image:
<behavior>

Multiple images:
<behavior>

Next Image:
YES / NO

Lead priority:
<behavior>

Secondary loading:
<behavior>

Responsive sizes:
<summary>

Stable geometry:
PASS / FAIL

New dependency:
NONE / FAIL


PRODUCT CONTRACT

Name:
AUTHORITATIVE / FAIL

Description:
AUTHORITATIVE / FAIL

Price:
AUTHORITATIVE / FAIL

Price formatter:
REUSED / FAIL

Product type:
<behavior>

Availability:
<behavior>

Stock indicator:
<behavior>

Raw inventory exposed:
NONE / FAIL

Category:
<behavior>

Variant data:
<behavior>


MADE TO ORDER

Treated as first-class:
PASS / FAIL

Treated as error/unavailable:
NO / FAIL

Normal checkout CTA:
NONE / FAIL

Request action:
<implemented legitimate route / truthful deferred treatment>

Fake/dead request link:
NONE / FAIL


DEPLOYMENT MODE

Latest authoritative commerce deployment decision:
<decision>

Newer superseding ADR found:
YES / NO

Cart CTA:
NONE / <justification>

Buy-now CTA:
NONE / <justification>

Checkout CTA:
NONE / <justification>

Payment CTA:
NONE / <justification>


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

Raw unapproved radii:
NONE / <list>

Raw unapproved shadows:
NONE / <list>

Raw unapproved breakpoints:
NONE / <list>

New media ratio:
NONE / <list>


COMPONENT REUSE

Existing components reused:
<list>

New components:
<list>

New-component justification:
<details>

Duplicate breadcrumb:
NONE / FAIL

Duplicate price formatter:
NONE / FAIL

Duplicate ProductCard:
NONE / FAIL

PDP-specific Button:
NONE / FAIL

PDP-specific Container:
NONE / FAIL


URBAN LADDER

Usage:
STRUCTURAL / IA REFERENCE ONLY

Structural ideas considered:
<list>

Ideas adopted:
<list>

Exact layout copied:
NONE / FAIL

Gallery copied:
NONE / FAIL

CTA copied:
NONE / FAIL

Specs copied:
NONE / FAIL

Visual design copied:
NONE / FAIL

Copy copied:
NONE / FAIL

Assets copied:
NONE / FAIL


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

One main:
PASS / FAIL

One H1:
PASS / FAIL

Heading hierarchy:
PASS / FAIL

Breadcrumb:
PASS / FAIL

Gallery semantics:
PASS / FAIL

Image alt:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Status not color-only:
PASS / FAIL

Reduced motion:
PASS / FAIL


PERFORMANCE

Server-first:
PASS / FAIL

New client boundaries:
NONE / <list>

New client JS:
NONE / <details>

Lead image priority:
PASS / FAIL

Secondary images lazy:
PASS / FAIL

Stable geometry:
PASS / FAIL

No gallery dependency:
PASS / FAIL


CURRENT LOCAL DATA

Laravel origin:
<origin>

Products total:
<number>

Real product available:
YES / NO

Synthetic production data created:
NO / FAIL

Fixture visual PDP:
<USED / NOT USED>


RUNTIME

Missing Laravel product:
PASS / FAIL

Missing Next dev product hard 404:
PASS / FAIL

Missing Next production product hard 404:
PASS / FAIL

/products remains HTTP 200:
PASS / FAIL

Category hard-404 regression:
PASS / FAIL

Browser:
<browser>

Console errors:
NONE / <details>

Network failures:
NONE / <details>


PHASE BOUNDARIES

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

Recommendation engine implemented:
NO

Comprehensive internal linking implemented:
NO

Comprehensive image optimization implemented:
NO

R2 infrastructure changed:
NO

Backend changed:
NO

Flutter changed:
NO


VALIDATION

Product-detail contract:
PASS / FAIL

Hard-404 tests:
PASS / FAIL

ProductCard link activation:
PASS / FAIL

Gallery tests:
PASS / FAIL

Variant tests:
PASS / FAIL

MADE_TO_ORDER tests:
PASS / FAIL

Request-first tests:
PASS / FAIL

Products regression:
PASS / FAIL

Category regression:
PASS / FAIL

Homepage regression:
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
NONE / <id>


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

Phase 14.4:
PASS / BLOCKED

Phase 14.5:
READY / BLOCKED
```

---

# 154. STOP Condition

Phase 14.4 may be declared PASS only when:

- `/products/[slug]` is implemented;
- website URLs use authoritative backend slugs;
- CAT-002/full Product Detail is used;
- the page remains public;
- the page remains server-first;
- the canonical API client is reused;
- no second data-fetch architecture exists;
- missing products produce genuine HTTP 404 in both development and production;
- non-404 upstream failures are not disguised as missing products;
- `/products` remains a normal collection route without detail preflight;
- the Phase 14.2 category hard-404 behavior remains intact;
- canonical ProductCard navigation is activated without creating a card fork;
- homepage/category/listing cards all use `/products/{backendSlug}`;
- the full product gallery consumes authoritative media;
- missing media is safe;
- image ordering follows backend authority;
- image geometry is stable;
- only appropriate initial imagery is prioritized;
- no gallery dependency is added;
- authoritative description is rendered safely;
- price formatting is reused;
- money remains integer minor-unit based at the contract boundary;
- `product_type`, `availability`, and `stock_indicator` retain distinct frozen semantics;
- raw inventory is never exposed;
- MADE_TO_ORDER remains first-class;
- variant information is factual and contract-driven;
- no fake swatches/configurator is invented;
- the latest deployment-mode ADR is respected;
- under the current request-only decision, no cart/buy-now/checkout/payment CTA is exposed unless a newer authoritative ADR explicitly supersedes it;
- no dead Request Furniture link exists;
- no fake contact/WhatsApp/phone action exists;
- no ratings/reviews/wishlist/sale/urgency/trust-badge UI is invented;
- no recommendation engine is added;
- all visual implementation consumes frozen tokens;
- no ad-hoc visual scale is introduced;
- existing primitives/components are reused before new components are created;
- Urban Ladder is used only for structural/IA study;
- no competitor visual design, copy, assets, gallery, CTA or specification treatment is copied;
- responsive behavior passes narrow mobile through wide desktop;
- 200% reflow passes;
- no horizontal overflow exists;
- accessibility checks pass;
- server-first rendering is preserved;
- client JS remains absent or narrowly justified;
- no dependencies are added;
- Phase 14.5+ work remains untouched;
- backend remains unchanged unless a genuine frozen-contract defect blocks the phase;
- Flutter remains unchanged;
- homepage regression passes;
- category regression passes;
- product-listing regression passes;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 14.4 — PASS
Phase 14.5 — READY
```

Do not start Phase 14.5 automatically.

**The Product Detail Page is a product-understanding surface, not permission to invent a second commerce architecture. Let authoritative furniture photography and product information lead the page; let the frozen SL Furnitures tokens control its visual language; reuse the components already earned in Phases 14.1–14.3; and use Urban Ladder only to study proven information hierarchy, never to copy its design.**