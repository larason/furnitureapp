# Phase 14.6 — Product Filtering & Sorting

## Entry State

```text
Phase 14.1 — PASS
Phase 14.2A — PASS
Phase 14.2 — PASS
Phase 14.3 — PASS
Phase 14.4 — PASS
Phase 14.5 — PASS
Phase 14.6 — ACTIVE
```

Implement public catalog filtering and sorting using the already-frozen CAT-001 query contract.

Do NOT start Phase 14.7.

---

# 1. Objective

Extend the existing public catalog experience so visitors can narrow and order furniture using authoritative Laravel catalog semantics.

Primary surfaces:

```text
/products
/search?search=<term>
```

Phase 14.6 must provide:

```text
category filtering
product-type filtering
availability filtering
price-range filtering
sorting
URL-persistent state
pagination preservation
clear/reset behavior
responsive filter controls
accessible forms
```

The implementation must remain:

```text
server-first
URL-driven
shareable
bookmarkable
progressively enhanced
Laravel-authoritative
token-driven
```

Do not build a frontend filtering engine.

---

# 2. Read Before Coding

Read:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
  DESIGN.md
  COMPONENTS.md
  ACCESSIBILITY.md
  USAGE.md
  tokens.css

frontend/web/
  ROUTING.md
  RESPONSIVE.md
  app/
  components/
  lib/

docs/api/
  api-contract.md
  api-conventions.md
  api-resources.md
  openapi.yaml

docs/domain/business-rules.md
docs/decisions.md

phases/group-N-phases.md
```

Inspect actual implementations from:

```text
14.2 category pages
14.3 Product Listing
14.4 Product Detail
14.5 Search

ProductGrid
ProductCard
catalog query/data helpers
pagination
search form
fixture architecture
proxy.ts
```

Also inspect the new `tsx` + `node:test` infrastructure established by the SonarQube remediation.

Do not recreate `load-ts.mjs` or any VM loader.

---

# 3. Git Policy

Before ANY Git command:

```text
locate/read/follow:
git-workflow-and-versioning
```

Preserve unrelated owner changes.

Never commit secrets or `.env*`.

Use an atomic Phase 14.6 commit.

---

# 4. Frozen CAT-001 Query Vocabulary

The public product collection already supports:

```text
GET /api/v1/products
```

with:

```text
search
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

Use these names exactly.

Do NOT introduce aliases.

---

# 5. Canonical Filter Contract

## Category

```text
category=<backend category slug>
```

Laravel also accepts public category IDs, but website URLs should prefer authoritative backend slugs.

Do not generate category slugs locally.

---

## Product Type

Closed values:

```text
IN_STOCK
MADE_TO_ORDER
```

Do not invent:

```text
READY_MADE
CUSTOM
PREORDER
REQUEST_ONLY
```

---

## Availability

Closed values:

```text
available
unavailable
```

Case matters.

Do not send:

```text
AVAILABLE
UNAVAILABLE
in_stock
out_of_stock
```

---

## Price

```text
min_price
max_price
```

These are integer TZS **minor units**.

Do not send floating-point monetary values to Laravel.

---

## Sort

Allowed:

```text
created_at
price
name
```

Direction:

```text
asc
desc
```

No other public sort field is permitted.

---

# 6. Explicitly Forbidden Filter

Do NOT create:

```text
stock_indicator=
```

`stock_indicator` is display-only.

Therefore do not expose filters such as:

```text
Low stock
Stock indicator
Only a few left
```

as CAT-001 filters.

---

# 7. Backend Query Pipeline Is Authority

Laravel already owns:

```text
search
  ↓
filters
  ↓
allow-listed sort
  ↓
id ASC deterministic tie-breaker
  ↓
pagination
```

The frontend supplies URL state only.

Do not reproduce this pipeline in JavaScript.

---

# 8. No Client-Side Filtering

Forbidden:

```text
fetch products
→ Array.filter()
→ Array.sort()
```

Also forbidden:

```text
fetch all 100 products
→ filter locally
```

Every production filter/sort change must result in an appropriate CAT-001 query.

---

# 9. Canonical `/products` Examples

Valid URLs conceptually include:

```text
/products?category=living-room

/products?product_type=MADE_TO_ORDER

/products?availability=available

/products?min_price=5000000&max_price=200000000

/products?sort=price&sort_direction=asc

/products?category=living-room&product_type=IN_STOCK&sort=price&sort_direction=asc
```

Parameter order has no semantic meaning.

---

# 10. Search Composition

Filters must compose with Phase 14.5 search.

Example:

```text
/search?search=chair&category=living-room&product_type=MADE_TO_ORDER
```

Backend request:

```text
GET /api/v1/products?search=chair&category=living-room&product_type=MADE_TO_ORDER
```

Do not create a second search/filter endpoint.

---

# 11. Search Term Must Survive Filtering

Given:

```text
/search?search=chair
```

applying:

```text
product_type=MADE_TO_ORDER
```

must produce conceptually:

```text
/search?search=chair&product_type=MADE_TO_ORDER
```

Never silently drop the search term.

---

# 12. Filters Must Survive Pagination

Given:

```text
/products?category=living-room&sort=price&sort_direction=asc
```

Next page must preserve that state:

```text
/products?category=living-room&sort=price&sort_direction=asc&page=2
```

---

# 13. Search + Filters Must Survive Pagination

Example:

```text
/search?search=chair&availability=available&page=2
```

Pagination must preserve both:

```text
search=chair
availability=available
```

---

# 14. Filter Changes Reset Pagination

When the user changes:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
search
```

the resulting request should return to page 1.

Do NOT preserve a stale:

```text
page=7
```

after changing filters.

The clean page-one URL should omit `page=1`.

---

# 15. `per_page`

Do not expose a page-size selector unless repository authority explicitly requires one.

Continue using the existing product-listing pagination size.

Phase 14.6 is not permission to add:

```text
20 / 40 / 80 per page
```

controls.

---

# 16. Default Sort

Laravel default is:

```text
created_at DESC
then id ASC
```

When the user has not explicitly selected sorting, prefer the clean URL:

```text
/products
```

rather than unnecessarily serializing:

```text
?sort=created_at&sort_direction=desc
```

unless the existing routing architecture requires explicit defaults.

---

# 17. User-Facing Sort Choices

The UI may present clear human labels mapped to frozen query pairs.

A reasonable mapping is:

```text
Newest
→ default / created_at DESC

Price: Low to high
→ sort=price&sort_direction=asc

Price: High to low
→ sort=price&sort_direction=desc

Name: A to Z
→ sort=name&sort_direction=asc

Name: Z to A
→ sort=name&sort_direction=desc
```

But inspect repository UX conventions before finalizing labels.

Do not invent:

```text
Best selling
Popular
Recommended
Featured
Top rated
Relevance
Trending
```

because Laravel provides no such sort semantics.

---

# 18. Sort Pair Is One UI Concept

Although Laravel exposes:

```text
sort
sort_direction
```

the customer does not necessarily need two separate controls.

Prefer one clear sorting control that maps each user-facing option to a valid frozen pair.

Do not make customers understand backend query architecture.

---

# 19. No Fake Relevance Sort

Search must NOT introduce:

```text
Sort by relevance
```

unless the frozen API explicitly adds such a sort later.

It currently does not.

---

# 20. Category Filter Authority

Use CAT-003:

```text
GET /api/v1/categories
```

for public category filter options where category options are needed.

CAT-003 returns active storefront top-level categories beneath the structural Furnitures Root.

Do not duplicate taxonomy in frontend constants.

---

# 21. No Hard-Coded Production Categories

Forbidden:

```ts
const categories = [
  "Living Room",
  "Bedroom",
  ...
];
```

for production filter options.

Use authoritative API data.

Fixture taxonomy may remain only in explicit fixture mode.

---

# 22. Category Slug

Filter value:

```text
category.slug
```

Display:

```text
category.name
```

Do not send the display name.

---

# 23. Category Collection Pagination

CAT-003 itself is paginated.

Do not assume the first page contains every category forever.

Inspect the existing category retrieval helper established in 14.1/14.2 and reuse it.

If the existing architecture already obtains all required active top-level categories safely, reuse that path.

Do not fetch `per_page=100` reflexively without understanding the existing contract and expected taxonomy size.

---

# 24. Category Filter Failure

If product retrieval succeeds but category-option retrieval unexpectedly fails, do not silently invent categories.

Use the established failure architecture or a deliberately degraded filter presentation only if existing architecture clearly supports that distinction.

Do not hide upstream failures with fixtures.

---

# 25. Product Type Filter

Present the two actual domain concepts:

```text
In stock
Made to order
```

while sending:

```text
IN_STOCK
MADE_TO_ORDER
```

MADE_TO_ORDER must remain a first-class offering.

Do not visually style it as an error/warning.

---

# 26. Availability Filter

Use:

```text
Available
Unavailable
```

mapped to:

```text
available
unavailable
```

Do not conflate:

```text
product_type=IN_STOCK
```

with:

```text
availability=available
```

They are separate frozen semantics.

---

# 27. Product Type vs Availability

These combinations are not for the frontend to reinterpret.

For example:

```text
product_type=MADE_TO_ORDER
availability=available
```

must be passed to Laravel if selected.

Do not impose undocumented cross-field rules.

---

# 28. Price Filter UX

The public UI may accept user-friendly TZS amounts.

But CAT-001 requires integer minor units.

Therefore establish one explicit conversion boundary:

```text
user-facing TZS
→ validated integer monetary value
→ minor units
→ URL/API query
```

Reuse existing money utilities if they support this responsibility.

---

# 29. Money Precision

Project convention remains:

```text
1 TZS = 100 minor units
```

Do not perform business arithmetic using floating point.

If the UI accepts whole TZS:

```text
125000 TZS
→ 12500000 minor units
```

using integer-safe conversion.

---

# 30. Do Not Expose Minor Units to Users

Do not label a field:

```text
Minimum price in minor units
```

Customers should see ordinary TZS amounts.

The API boundary handles minor-unit serialization.

---

# 31. Price Input Constraints

Use appropriate accessible numeric input semantics.

Do not accept:

```text
negative prices
NaN
Infinity
scientific notation as intentional money UX
```

Do not silently reinterpret malformed values.

---

# 32. Price Cross-Field Rule

Frozen contract requires:

```text
min_price <= max_price
```

Laravel remains authoritative.

Frontend UX should prevent or clearly handle an obviously inverted range where practical.

Do not replace backend validation.

---

# 33. Invalid URL State

Users can manually edit URLs.

Therefore Phase 14.6 must handle invalid values deliberately.

Examples:

```text
?product_type=WRONG
?availability=yes
?sort=rating
?sort_direction=sideways
?min_price=-1
?min_price=500&max_price=100
```

Do not crash.

Do not silently convert arbitrary values into valid ones and pretend they were requested.

---

# 34. Laravel Validation Authority

CAT-001 invalid supported parameters may return:

```text
422 INVALID_VALUE
```

Do not convert that to:

```text
empty catalog
404
```

Use the established expected-error/state architecture.

---

# 35. Unknown Query Parameters

The routing contract states:

```text
a route reads only the parameters it owns
```

Do not globally strip unknown parameters.

Do not build a query sanitizer that rewrites the browser URL on every render.

---

# 36. URL Is State Authority

Filter state must come from URL search parameters.

Do not maintain a competing durable state in:

```text
React Context
Redux
Zustand
localStorage
sessionStorage
cookies
```

No new state library.

---

# 37. Progressive Enhancement

The filtering/sorting experience should remain functional without JavaScript where practical.

A native GET form is the preferred baseline:

```text
<form method="get">
```

This naturally creates shareable URLs.

---

# 38. `/products` Filter Form

Conceptually:

```text
GET /products
```

with controls named according to the frozen API vocabulary.

Do not require client-side navigation merely to apply filters.

---

# 39. `/search` Filter Form

Conceptually:

```text
GET /search
```

and preserve:

```html
<input type="hidden" name="search" ...>
```

or equivalent server-rendered state.

Do not make users re-enter their search when applying a filter.

---

# 40. Apply Behavior

A clear:

```text
Apply filters
```

action is acceptable and preferable to adding client JavaScript solely for auto-submit.

Do not introduce `useEffect` watching filter values.

---

# 41. Sorting Apply Behavior

Sorting may use the same native GET form/apply action.

Do not add client JS merely so a select auto-submits.

Progressive enhancement is more important than fashionable interaction.

---

# 42. Clear Filters

Provide a clear way to remove filter/sort state.

For `/products`:

```text
Clear filters
→ /products
```

For `/search`:

```text
Clear filters
→ preserve search term
```

Example:

```text
/search?search=chair&category=living-room
```

clear filters becomes:

```text
/search?search=chair
```

Do not clear the search term when the action says “Clear filters.”

---

# 43. Clear Search Is Different

Do not conflate:

```text
Clear filters
```

with:

```text
Clear search
```

Phase 14.5 owns the search term.

---

# 44. Active Filter Summary

A restrained active-filter summary may be useful.

But do not build a badge/chip wall.

If implemented, use existing MUI semantics and frozen tokens.

Do not create a new pill-heavy visual language.

---

# 45. Active Filter Removal

If individual removable filters are implemented, their links/actions must preserve all other current URL state and reset pagination.

Do not require JavaScript merely for removal.

---

# 46. Desktop Information Architecture

A furniture catalog commonly benefits from:

```text
collection heading
result context
sort control
filter controls
product grid
pagination
```

Possible desktop structure:

```text
filters | product results
```

or a restrained filter disclosure above the grid.

Choose based on the existing SL Furnitures layout system.

Urban Ladder may inform information architecture only.

Do not copy its visual treatment.

---

# 47. Mobile Information Architecture

Do not simply squeeze a desktop sidebar into 320px.

A compact mobile filter disclosure is appropriate.

Before introducing a new Drawer, inspect the existing MUI mobile-navigation pattern and design-system conventions.

---

# 48. Mobile Client Boundary

A mobile filter Drawer may justify a narrowly scoped Client Component.

However, it is NOT mandatory.

Prefer native/server-compatible disclosure if it provides a strong accessible experience.

If a Drawer is used:

```text
page remains Server Component
ProductGrid remains server-rendered
URL remains state authority
Drawer only owns temporary open/closed state
filter values still submit through URL
```

---

# 49. Client State Boundary

The only acceptable local state is ephemeral presentation state such as:

```text
filter panel open/closed
```

Do not put authoritative filter values into React state if the URL already owns them.

---

# 50. No Client-Side Result Refresh Architecture

Do not introduce:

```text
fetch in useEffect
router.refresh orchestration
optimistic filtering
client cache
React Query
SWR
```

Phase 14.6 remains server-driven.

---

# 51. Existing ProductGrid

Reuse canonical:

```text
ProductGrid
```

Do not create:

```text
FilteredProductGrid
SortableProductGrid
SearchFilteredGrid
```

---

# 52. Existing ProductCard

Reuse canonical ProductCard unchanged unless a genuine reusable requirement emerges.

Filtering must not alter card design.

---

# 53. Existing Pagination

Extend the canonical pagination query-preservation behavior.

Do not create:

```text
FilterPagination
SearchFilterPagination
```

---

# 54. Shared Catalog Query Representation

By the end of Phase 14.6, `/products` and `/search` should preferably share one typed representation for CAT-001 URL/query state.

Conceptually:

```ts
type ProductCollectionQuery = {
  search?: string;
  category?: string;
  product_type?: "IN_STOCK" | "MADE_TO_ORDER";
  availability?: "available" | "unavailable";
  min_price?: ...;
  max_price?: ...;
  sort?: "created_at" | "price" | "name";
  sort_direction?: "asc" | "desc";
  page?: number;
};
```

Do not copy this literally without inspecting existing types.

Extend existing catalog query types where possible.

---

# 55. One Query Serializer

Do not hand-build query strings separately in:

```text
products page
search page
pagination
filter controls
clear-filter links
```

Prefer one reusable query serialization responsibility.

It must use standard URL encoding.

---

# 56. Serializer Must Not Become Contract Authority

The serializer represents the frozen contract.

It must not invent:

```text
aliases
new enums
fallback sort fields
implicit filters
```

---

# 57. Search Integration

Phase 14.5's `getProductCatalog` should remain the canonical product collection data boundary if appropriate.

Extend it rather than creating:

```text
getFilteredProducts()
getSortedProducts()
getSearchFilteredProducts()
```

unless repository structure genuinely requires otherwise.

---

# 58. Fixture Mode

Production/default mode remains API-backed.

Fixture filtering may be extended only for explicit visual-development mode.

Do not use fixtures as fallback.

---

# 59. Fixture Semantics

Fixture filtering does not need to reproduce Laravel SQL internals.

It should only support deterministic visual verification of:

```text
category
product type
availability
price
sorting
pagination
search composition
```

where practical.

Document it as fixture behavior.

---

# 60. No Fixture Contract Drift

Use the same public query vocabulary in fixture mode.

Do not invent:

```text
fixtureCategory
fixtureSort
filterBy
```

---

# 61. Category Pages

Phase 14.2 category pages are category landing/discovery pages.

Do NOT automatically turn `/categories/[slug]` into the generic filterable listing surface.

Phase 14.6's primary filtering surfaces are:

```text
/products
/search
```

Keep category landing pages focused unless existing repository architecture explicitly establishes otherwise.

---

# 62. Category Navigation to Filtered Products

Do not replace canonical:

```text
/categories/[slug]
```

navigation with:

```text
/products?category=<slug>
```

They serve different IA purposes.

The category filter is an additional catalog-discovery mechanism.

---

# 63. Result Count

Reuse authoritative:

```text
meta.pagination.total
```

if displaying result counts.

Do not use current-page array length as total results.

---

# 64. Empty Filtered Result

A valid filter combination with no matches is:

```text
HTTP 200
```

Render a factual empty state.

Example intent:

```text
No furniture matches these filters.
```

Offer:

```text
Clear filters
```

where appropriate.

Do not show fake recommendations.

---

# 65. Search + Filter Empty Result

Example:

```text
/search?search=chair&product_type=MADE_TO_ORDER
```

with zero results remains:

```text
HTTP 200
```

and should preserve the user's search context.

---

# 66. No 404 for Filters

Never invoke `notFound()` because:

```text
category filter has no products
price range has no products
search + filter has no products
```

A collection query is not a resource lookup.

---

# 67. Proxy Boundary

Do not add `/products` or `/search` to resource-existence preflight.

Existing hard-404 behavior remains only for actual detail resources such as:

```text
/products/[slug]
/categories/[slug]
```

---

# 68. Request-First Commerce

Filtering does not change deployment mode.

Do not add:

```text
Add to cart
Buy now
Checkout
Wishlist
Payment
```

to ProductCard or listing controls.

MADE_TO_ORDER remains first-class.

---

# 69. Filter Labels

Use human-readable labels.

Examples:

```text
Category
Product type
Availability
Price
Sort by
```

Avoid backend jargon such as:

```text
product_type
sort_direction
minor units
```

in customer-facing UI.

---

# 70. Accessibility

Filter controls require:

```text
proper labels
fieldset/legend where groups benefit
keyboard operability
visible focus
clear selected state
non-color-only state
touch-friendly controls
logical tab order
```

---

# 71. Native Form Semantics

Prefer:

```text
fieldset
legend
label
input
select
button
```

or accessible MUI equivalents.

Do not replace native semantics with clickable `Box` elements.

---

# 72. Checkboxes vs Single-Value Contract

CAT-001 accepts one value for:

```text
category
product_type
availability
```

Do not present multi-select checkboxes that imply OR semantics unless the backend actually supports arrays/multiple values.

A single-select/radio/select interaction should accurately reflect the contract.

---

# 73. Critical Multi-Select Rule

Do NOT build UI allowing:

```text
IN_STOCK + MADE_TO_ORDER simultaneously
```

as two selected `product_type` values.

The backend contract is singular.

Likewise do not multi-select:

```text
available + unavailable
```

or multiple categories through repeated query values unless the contract explicitly supports that.

---

# 74. Price Accessibility

Minimum and maximum price controls must have distinct accessible labels.

Do not rely solely on placeholder text.

---

# 75. Sorting Accessibility

Sort control must have an accessible label such as:

```text
Sort products
```

The visible selected option must correspond to URL state.

---

# 76. Mobile Filter Trigger

If using a disclosure/Drawer:

```text
Filter
```

must expose:

```text
accessible name
expanded/open state where appropriate
keyboard operation
Escape close if modal/drawer
focus restoration
```

Reuse established mobile-navigation accessibility patterns where relevant.

---

# 77. Focus

Applying filters performs normal navigation.

Do not add complicated client-side focus management merely to mimic SPA filtering.

Normal server navigation semantics are acceptable.

---

# 78. Responsive Requirements

Verify:

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

Pay particular attention to:

```text
filter controls
sort control
long category names
price inputs
active filter state
ProductGrid width
pagination
```

---

# 79. 200% Reflow

At 200%:

```text
filters remain reachable
labels remain visible
controls do not overlap
sort remains usable
product grid reflows
pagination remains usable
```

No horizontal document scrolling.

---

# 80. Design System

Use:

```text
tokens.css
→ MUI theme
→ existing primitives
→ collection controls
```

Do not create a filtering-specific visual system.

---

# 81. Visual Character

Filters should feel:

```text
quiet
architectural
functional
spacious
editorial
```

Not:

```text
dashboard
admin panel
marketplace control center
badge cloud
pill wall
glass panel
```

---

# 82. No Excessive Containers

Avoid putting every filter group inside its own card.

Use:

```text
spacing
typography
subtle approved separators
```

for hierarchy.

---

# 83. Typography

Use utility sans for:

```text
filter labels
inputs
selects
prices
sort controls
result metadata
```

Do not use Young Serif for ordinary form controls.

---

# 84. Icons

Use only:

```text
@mui/icons-material
```

where icons genuinely clarify controls.

Do not decorate every filter heading.

---

# 85. No Dependency

Expected new dependency count:

```text
0
```

Do not add:

```text
query-string
qs
react-hook-form
Formik
Zod
React Query
SWR
state libraries
slider packages
```

merely for catalog filtering.

---

# 86. Price Slider

Do NOT introduce a dual-handle slider dependency.

Simple accessible min/max inputs are preferable.

If existing MUI Slider is considered, remember that URL/native-form behavior and precise accessible input remain more important than visual novelty.

---

# 87. SEO Boundary

Do not implement comprehensive:

```text
canonical URL policy
filter-page indexing policy
robots metadata
faceted-navigation SEO
Open Graph
dynamic SEO titles
```

Phase 14.7 owns SEO metadata.

Record any faceted-navigation SEO concern for 14.7 rather than solving it early.

---

# 88. Structured Data Boundary

Do not modify JSON-LD.

Phase 14.8 owns structured data.

---

# 89. Sitemap Boundary

Do not add filter URLs to sitemap.

Phase 14.9 owns sitemap/robots.

---

# 90. Internal Linking Boundary

Do not create SEO filter landing pages or generated facet links.

Phase 14.10 owns comprehensive internal linking.

---

# 91. Performance Boundary

Do not add caching/prefetch architecture merely for filters.

Phase 14.11 owns comprehensive performance optimization.

---

# 92. Backend Boundary

Expected:

```text
backend changes: NONE
```

The filtering/sorting contract already exists.

If frontend implementation discovers a frozen-contract/backend mismatch:

```text
STOP
document exact mismatch
do not silently change Laravel
```

---

# 93. Flutter Boundary

Expected:

```text
NONE
```

---

# 94. Test Infrastructure

Use the remediated standard:

```text
node:test
+
tsx
```

for TypeScript behavioral tests.

Do NOT recreate:

```text
load-ts.mjs
node:vm
eval
new Function
custom runtime TypeScript loader
```

---

# 95. Focused Test Command

Add a focused suite following existing conventions, preferably:

```text
npm run test:filters
```

or:

```text
npm run test:catalog-controls
```

Choose one name consistent with repository terminology.

Do not create multiple overlapping suites.

---

# 96. Minimum Query Contract Tests

Test exact serialization for:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
page
search
```

---

# 97. Category Tests

Cover:

```text
backend slug used
display name not sent
no frontend slugification
single category only
CAT-003 options reused
```

---

# 98. Product Type Tests

Cover:

```text
IN_STOCK
MADE_TO_ORDER
invalid value
single selection
```

Ensure MADE_TO_ORDER is not styled/treated as error.

---

# 99. Availability Tests

Cover:

```text
available
unavailable
invalid value
```

Ensure availability is not conflated with product type.

---

# 100. Forbidden Stock Indicator Test

Explicit regression:

```text
stock_indicator filter:
NOT IMPLEMENTED
```

No query serializer/UI should emit it.

---

# 101. Price Tests

Cover:

```text
minimum only
maximum only
both
min == max
min < max
min > max
zero
negative
malformed input
TZS → minor-unit conversion
```

Do not invent backend outcomes; test frontend conversion/validation and Laravel's actual documented response behavior separately.

---

# 102. Sort Tests

Cover every supported pair used by the UI:

```text
created_at DESC
price ASC
price DESC
name ASC
name DESC
```

If “Newest” is represented by omitted sort parameters, test that clean URL behavior.

---

# 103. Unsupported Sort Test

Ensure UI/serializer cannot intentionally emit:

```text
rating
popularity
relevance
best_selling
featured
```

---

# 104. Pagination Tests

Given active filters, test:

```text
next preserves filters
previous preserves filters
page 1 removes page=1
filter change removes stale page
sort change removes stale page
```

---

# 105. Search Composition Tests

Given:

```text
search=chair
```

test applying/removing:

```text
category
product_type
availability
price
sort
```

without losing `search`.

---

# 106. Clear Tests

Verify:

```text
/products + clear
→ /products

/search?search=chair + clear filters
→ /search?search=chair
```

---

# 107. Zero Results Tests

Verify valid zero-result combinations:

```text
HTTP 200
factual empty state
no 404
no fixture fallback
no fake recommendations
```

---

# 108. Error Tests

Verify:

```text
422
429
5xx
timeout
network failure
```

remain distinguishable according to the established API/state architecture.

Do not convert any into an empty product list.

---

# 109. Fixture Tests

Where fixture mode supports visual verification, cover enough combinations to prove:

```text
filtering
sorting
search + filtering
pagination preservation
```

Do not claim fixture behavior proves Laravel SQL behavior.

---

# 110. Laravel Contract Verification

Run existing backend CAT-001 tests covering filters/sorting.

Do not rewrite backend tests unless implementation reveals a genuine defect.

Report exact test command and count.

---

# 111. Runtime API Smoke Tests

If local Laravel is available, test representative CAT-001 URLs.

If the local database remains empty, filters may legitimately all return:

```text
200
data: []
```

Do not create synthetic production data merely for smoke testing.

---

# 112. Browser Verification

Verify both:

```text
/products
/search?search=<term>
```

with filtering controls.

If fixture mode is needed for populated visual verification, clearly distinguish:

```text
API runtime evidence
vs
fixture visual evidence
```

---

# 113. Product Detail Regression

Verify:

```text
/products/[missing]
→ HTTP 404
```

remains intact.

---

# 114. Category Regression

Verify:

```text
/categories/[missing]
→ HTTP 404
```

remains intact.

---

# 115. Collection Regression

Verify:

```text
/products
→ HTTP 200

/search
→ HTTP 200
```

and neither receives resource preflight.

---

# 116. Existing Frontend Suites

Run:

```text
npm run test:filters       # actual chosen name
npm run test:search
npm run test:product-detail
npm run test:products
npm run test:category
npm run test:homepage
npm run test:api
npm run test:theme
npm run test:layout
npm run test:responsive
npm run test:states
```

plus routing tests if separately exposed.

---

# 117. Static Validation

Must pass:

```text
npm run typecheck
npm run lint
npm run build
git diff --check
```

---

# 118. Sonar Regression

Because test infrastructure was recently remediated, ensure Phase 14.6 introduces none of:

```text
node:vm
eval
new Function
SourceTextModule
runInContext
custom TS execution
```

Do not reopen the SonarQube issue.

---

# 119. Component Creation Audit

For each new component report:

```text
Component:
<name>

Responsibility:
<stable responsibility>

Existing components inspected:
<list>

MUI primitives considered:
<list>

Why existing composition was insufficient:
<reason>

Reusable by both /products and /search:
YES / NO
```

Prefer reusable catalog controls rather than route-specific duplicates.

---

# 120. Expected Reuse

Likely reusable responsibilities may include conceptually:

```text
CatalogFilters
CatalogSort
CatalogQuery
```

but these names are NOT requirements.

Inspect the repo first.

Do not create components simply because this prompt names a concept.

---

# 121. Forbidden Duplicates

Do not create:

```text
ProductsFilterPanel
SearchFilterPanel

ProductsSort
SearchSort

ProductsPagination
SearchPagination
```

when the same catalog-query responsibility can be shared.

---

# 122. Client Boundary Audit

Report all new files containing:

```text
"use client"
```

Expected:

```text
NONE
```

or at most a narrowly justified ephemeral mobile filter disclosure.

If one exists, report:

```text
why native/server composition was insufficient
what local state it owns
why URL filter state remains server authority
JS payload introduced
no-JS fallback
```

---

# 123. Token Audit

Search changes for:

```text
raw hex colors
arbitrary spacing
arbitrary font sizes
arbitrary radius
custom shadows
raw breakpoint widths
custom transition timings
```

Expected:

```text
NONE
```

---

# 124. AI-Slop Audit

Explicitly check for:

```text
filter-chip wall
excessive pills
card-per-filter
gradient panels
glassmorphism
decorative icons
floating control islands
fake marketplace badges
oversized rounded controls
```

Expected:

```text
NONE
```

---

# 125. Documentation

Update:

```text
frontend/web/ROUTING.md
```

only if necessary to mark the already-reserved filtering/sorting behavior as implemented.

Do not rewrite the frozen query vocabulary.

Update:

```text
phases/group-N-phases.md
```

with Phase 14.6 execution evidence.

Update `frontend/AGENTS.md` only for a genuinely durable rule not already documented.

---

# 126. ADR

Expected:

```text
NONE
```

The query contract and URL-state architecture are already decided.

If a material unresolved architectural conflict appears, STOP before inventing a new decision.

---

# 127. Completion Report

Return:

```text
PHASE 14.6 — FILTERING & SORTING

Status:
PASS / BLOCKED


ROUTES

/products:
<status>

/search:
<status>

Category pages modified:
YES / NO

Reason:
<summary>


CAT-001 CONTRACT

category:
<implementation>

product_type:
<implementation>

availability:
<implementation>

min_price:
<implementation>

max_price:
<implementation>

sort:
<implementation>

sort_direction:
<implementation>

stock_indicator filter:
NONE / FAIL

Aliases introduced:
NONE / FAIL


CATEGORY AUTHORITY

Category source:
CAT-003 / <other>

Backend slugs used:
YES / NO

Hard-coded production taxonomy:
NONE / FAIL

Frontend slug generation:
NONE / FAIL


URL STATE

URL is authority:
YES / NO

React durable filter state:
NONE / FAIL

localStorage:
NONE / FAIL

sessionStorage:
NONE / FAIL

Filter change resets page:
PASS / FAIL

Sort change resets page:
PASS / FAIL

Pagination preserves state:
PASS / FAIL

Search preserved:
PASS / FAIL

Clean page-one URL:
PASS / FAIL


FILTER UX

Category:
<control>

Product type:
<control>

Availability:
<control>

Price:
<control>

Apply:
<behavior>

Clear filters:
<behavior>

Multi-select unsupported values exposed:
NO / FAIL


SORT UX

Options:
<list>

Default:
<behavior>

Unsupported sort modes:
NONE / FAIL

Separate backend jargon exposed:
NO / FAIL


PRICE

Customer-facing unit:
TZS / FAIL

API unit:
INTEGER MINOR UNITS / FAIL

Conversion boundary:
<summary>

Floating-point business arithmetic:
NONE / FAIL

min > max:
<behavior>


SEARCH COMPOSITION

/search?search=... preserved:
PASS / FAIL

Search + category:
PASS / FAIL

Search + type:
PASS / FAIL

Search + availability:
PASS / FAIL

Search + price:
PASS / FAIL

Search + sort:
PASS / FAIL

Clear filters preserves search:
PASS / FAIL


DATA

Production filtering:
LARAVEL CAT-001 / FAIL

Client-side filtering:
NONE / FAIL

Client-side sorting:
NONE / FAIL

Fetch-all filtering:
NONE / FAIL

CAT-002 N+1:
NONE / FAIL

Fixture fallback:
NONE / FAIL


COMPONENT REUSE

ProductGrid:
REUSED / FAIL

ProductCard:
REUSED / FAIL

Pagination:
REUSED / <details>

Existing catalog helper:
REUSED / <details>

New components:
<list>

Duplicate route-specific controls:
NONE / FAIL


SERVER / CLIENT

Pages server-first:
PASS / FAIL

New client boundaries:
NONE / <list>

Ephemeral state only:
PASS / FAIL / N/A

Filter values in client state:
NONE / FAIL

No-JS filtering:
PASS / FAIL


ACCESSIBILITY

Filter labels:
PASS / FAIL

Fieldsets/legends:
PASS / FAIL

Price labels:
PASS / FAIL

Sort label:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Mobile disclosure:
PASS / FAIL / N/A

200% reflow:
PASS / FAIL


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

Horizontal overflow:
NONE / FAIL


EMPTY / ERROR STATES

Filtered zero results:
HTTP <status>

Search+filter zero results:
HTTP <status>

Zero results → 404:
NO / FAIL

422:
<behavior>

429:
<behavior>

5xx:
<behavior>

Failures converted to empty:
NO / FAIL


PROXY REGRESSION

/products preflight:
NONE / FAIL

/search preflight:
NONE / FAIL

Missing PDP:
HTTP <status>

Missing category:
HTTP <status>


TEST INFRASTRUCTURE

Runner:
node:test + tsx / <other>

load-ts.mjs:
ABSENT / FAIL

node:vm:
NONE / FAIL

eval/new Function:
NONE / FAIL


VALIDATION

Filter/sort suite:
PASS / FAIL

Search:
PASS / FAIL

Product detail:
PASS / FAIL

Products:
PASS / FAIL

Category:
PASS / FAIL

Homepage:
PASS / FAIL

API:
PASS / FAIL

Theme:
PASS / FAIL

Layout:
PASS / FAIL

Responsive:
PASS / FAIL

States:
PASS / FAIL

Laravel CAT-001:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


DESIGN

Frozen tokens:
PASS / FAIL

Ad-hoc visual values:
NONE / <list>

Duplicate design authority:
NONE / FAIL

AI-slop patterns:
NONE / <list>

Urban Ladder usage:
STRUCTURAL REFERENCE ONLY / N/A


PHASE BOUNDARIES

Comprehensive SEO:
NO

Structured data:
NO

Sitemap/robots:
NO

Comprehensive internal linking:
NO

Performance phase:
NO

Backend changed:
NO

Flutter changed:
NO

Dependencies added:
NONE


DOCUMENTATION

ROUTING.md:
UPDATED / UNCHANGED

Group N:
UPDATED / FAIL

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

Phase 14.6:
PASS / BLOCKED

Phase 14.7:
READY / BLOCKED
```

---

# 128. STOP Condition

Phase 14.6 may be declared PASS only when:

- `/products` supports the frozen CAT-001 filters;
- `/search` composes search with those same filters;
- `category`, `product_type`, `availability`, `min_price`, `max_price`, `sort`, and `sort_direction` use exact frozen vocabulary;
- `stock_indicator` is not exposed as a filter;
- no undocumented filter/sort aliases exist;
- category options come from authoritative catalog data rather than duplicated production constants;
- category filter URLs use backend slugs;
- URL search parameters remain durable filter state;
- filtering works through CAT-001 rather than JavaScript arrays;
- sorting happens in Laravel;
- filter/sort changes reset stale pagination;
- pagination preserves active search/filter/sort state;
- page one remains clean;
- `/search` preserves the search term while filtering;
- clearing filters from search does not erase the search term;
- ProductGrid is reused;
- ProductCard is reused;
- canonical pagination is reused/extended rather than forked;
- no CAT-002 N+1 is introduced;
- product type and availability remain distinct semantics;
- MADE_TO_ORDER remains first-class;
- price UI is customer-facing TZS while API values remain integer minor units;
- no floating-point business-money arithmetic is introduced;
- invalid query states do not crash or masquerade as empty results;
- valid zero-result combinations return HTTP 200;
- no collection query becomes a 404;
- `/products` and `/search` receive no detail-resource preflight;
- existing product/category hard-404 behavior remains intact;
- the implementation remains server-first;
- URL state remains authoritative;
- any client state is narrowly limited to ephemeral presentation state;
- native/no-JS filter submission remains functional;
- no new dependency is added;
- no duplicate filter architecture exists between `/products` and `/search`;
- frozen design tokens remain authoritative;
- responsive checks pass;
- 200% reflow passes;
- accessibility checks pass;
- no horizontal overflow exists;
- the new `tsx` test infrastructure is used;
- no VM/dynamic-execution test loader returns;
- CAT-001 backend filter/sort tests pass;
- all existing frontend regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- no Phase 14.7+ work is implemented;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 14.6 — PASS
Phase 14.7 — READY
```

Do not start Phase 14.7 automatically.

**Phase 14.6 is URL-driven catalog refinement, not a client-side product engine. Laravel already owns search, filtering, deterministic sorting, and pagination; the website's job is to expose that contract clearly, accessibly, and beautifully while preserving the restrained SL Furnitures design system.**