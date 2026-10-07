# Phase 14.5 — Public Product Search

## Entry State

```text
Phase 14.1 — PASS
Phase 14.2A — PASS
Phase 14.2 — PASS
Phase 14.3 — PASS
Phase 14.4 — PASS
Phase 14.5 — ACTIVE
```

Implement the canonical public furniture search experience.

Canonical website route:

```text
/search?search=<term>
```

Do NOT start Phase 14.6.

---

# 1. Objective

Build a public, server-rendered search experience that allows visitors to search the authoritative Laravel product catalog.

The experience must:

```text
accept a search term
encode that term in the URL
query CAT-001
render Product Summary results
reuse ProductGrid
preserve canonical ProductCard navigation
support pagination
provide truthful empty states
work without JavaScript
remain accessible and responsive
```

Search is a **catalog discovery surface**, not a new search architecture.

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

Inspect the actual implementations from:

```text
13.5 API client
13.7 layout
13.8 failure states
13.9 responsive foundation

14.1 homepage
14.2 category pages
14.2A hard-404 architecture
14.3 product listing
14.4 product detail
```

Do not implement from assumptions.

Repository authority wins.

---

# 3. Git Policy

Before ANY Git command:

```text
locate/read/follow:
git-workflow-and-versioning
```

Preserve unrelated owner changes.

Never commit secrets or `.env*`.

Use one atomic Phase 14.5 commit.

---

# 4. Canonical Search Route

Implement:

```text
/search
```

Search state uses:

```text
?search=<term>
```

Examples:

```text
/search?search=sofa
/search?search=dining%20table
/search?search=walnut
```

Do NOT introduce:

```text
?q=
?query=
?keyword=
?term=
?s=
```

The canonical vocabulary is `search`.

---

# 5. Website Route vs Laravel Endpoint

Important distinction:

```text
Website:
GET /search?search=chair

Laravel:
GET /api/v1/products?search=chair
```

There is NO requirement for:

```text
GET /api/v1/search
```

Do not create one.

Do not modify Laravel merely to make the website route names symmetrical.

---

# 6. CAT-001 Is Search Authority

Use the existing public product collection endpoint:

```text
GET /api/v1/products
```

with:

```text
search=<term>
```

The backend owns:

```text
search semantics
FULLTEXT behavior
variant matching
visibility rules
filter composition
sorting
pagination
SQL escaping
database-driver behavior
```

The frontend must not reproduce these rules.

---

# 7. Do Not Build a Frontend Search Engine

Forbidden:

```text
fetch all products
→ filter in JavaScript
```

Also forbidden:

```text
Fuse.js
MiniSearch
Lunr
Algolia
Meilisearch
ElasticSearch
local fuzzy matching
custom relevance engine
```

No search dependency is expected.

---

# 8. Backend Search Semantics

Inspect the authoritative backend contract and implementation.

Do NOT attempt to duplicate:

```text
MySQL FULLTEXT
MariaDB FULLTEXT
SQLite fallback behavior
variant SKU search
variant attribute search
escaping rules
visibility constraints
```

The browser sends the term.

Laravel determines matches.

---

# 9. Public Access

Search is public.

Required:

```text
Clerk: NONE
authentication: NONE
cookies required: NONE
customer account required: NONE
```

Do not introduce auth during this phase.

---

# 10. Server-First Architecture

Expected:

```text
app/search/page.tsx
→ Server Component
```

Do not add `"use client"` to the search page.

Search results must be present in server-rendered HTML.

---

# 11. Native GET Search Form

Prefer a semantic HTML GET form.

Conceptually:

```html
<form action="/search" method="get">
  <label>...</label>
  <input name="search" ... />
  <button type="submit">Search</button>
</form>
```

Exact implementation must use the existing design system/MUI architecture.

The essential behavior is:

```text
input
→ native GET
→ /search?search=<encoded term>
→ server-rendered results
```

Search must work with JavaScript disabled.

---

# 12. No Client Search State

Do not introduce React state merely to hold the search term.

Do not require:

```text
useState
useEffect
useRouter
router.push
window.location
```

for ordinary search submission.

The URL is the durable state.

---

# 13. No Debounced Search

Do not implement:

```text
search-as-you-type
debounce
live results
autocomplete
suggestions
predictive search
typeahead
```

Those are not required by Phase 14.5.

---

# 14. No Search Overlay

Do not create a modal/command-palette search system.

The canonical search destination is `/search`.

---

# 15. Header Search Integration

Inspect the existing `SearchAffordance` from the website shell.

Phase 13.7 intentionally reserved `/search` without implementing search functionality.

Now activate the legitimate search destination.

At minimum:

```text
header search affordance
→ /search
```

If the existing architecture naturally supports a real semantic search form in the header without unnecessary client state or visual disruption, that may be implemented.

Do NOT rebuild the entire header.

---

# 16. Header Integration Rule

Do not create competing search implementations such as:

```text
HeaderSearch
DesktopSearch
MobileSearch
SearchBar
SearchInput
GlobalSearch
```

unless their responsibilities genuinely differ.

Prefer one reusable search-form responsibility where appropriate.

---

# 17. Search Page Without Query

`/search` is a valid public page.

It should NOT be a 404.

When no meaningful search term exists, render an intentional search-entry state such as:

```text
Search furniture
Find pieces by name, material, style, or other catalog information.
[search field]
```

Copy must remain factual and should not promise fields the backend does not actually search.

---

# 18. Do Not Treat Missing Query as Error

These are not errors:

```text
/search
/search?search=
```

Do not invoke:

```text
notFound()
error boundary
validation error UI
```

merely because a search term is absent.

---

# 19. Blank / Whitespace Search

The backend deliberately normalizes empty/whitespace search to no search predicate.

The website should avoid accidentally turning a blank search page into an unfiltered duplicate of `/products`.

Normalize **presentation intent** conservatively:

```text
missing search
empty search
whitespace-only search
```

should render the search-entry state rather than displaying the entire catalog as “search results.”

Do not change backend semantics.

---

# 20. Search Term Normalization

Do not aggressively transform user search input.

Do NOT:

```text
lowercase manually
stem words
remove punctuation
transliterate
rewrite spelling
singularize/pluralize
remove stop words
```

Laravel owns search interpretation.

Trimming surrounding whitespace for UI/query-state purposes is acceptable if consistent with the backend contract.

---

# 21. URL Encoding

Use standard URL/form encoding.

Do not hand-build unsafe query strings.

Search terms may contain:

```text
spaces
&
%
_
\
punctuation
Unicode
```

The backend already owns SQL escaping.

The frontend must correctly encode URL state.

---

# 22. Next.js Search Params

Use the installed Next.js 16.3.8 App Router conventions.

Verify actual framework behavior for `searchParams`.

Do not copy obsolete Next.js examples blindly.

---

# 23. API Client

Reuse:

```text
frontend/web/lib/api/client.ts
```

and the existing catalog-domain helper established by 14.1–14.4 where appropriate.

Do NOT introduce:

```text
Axios
React Query
SWR
new fetch wrapper
Next.js API proxy
route handler proxy
BFF
```

---

# 24. Existing Product Collection Logic

Phase 14.3 already implemented:

```text
GET /api/v1/products
ProductGrid
pagination
Product Summary mapping
empty collection behavior
```

Reuse that architecture.

Do not duplicate it under search-specific names.

---

# 25. Extend the Existing Catalog Query Boundary

If Phase 14.3 created something conceptually equivalent to:

```text
getProducts(...)
buildProductQuery(...)
ProductCollection
ProductGrid
Pagination
```

extend/reuse it.

Do not create:

```text
searchProductsViaApi()
SearchProductGrid
SearchProductCard
SearchPagination
SearchPriceFormatter
```

when the existing collection architecture can represent the same responsibility.

---

# 26. Search Request

For a meaningful term:

```text
GET /api/v1/products?search=<term>
```

Use the established cache policy.

Current catalog implementation reports:

```text
no-store
```

Remain consistent unless repository authority says otherwise.

---

# 27. Search + Pagination

Search results must support pagination.

Example:

```text
/search?search=chair&page=2
```

The search term must survive Previous/Next navigation.

---

# 28. Clean Page-One URL

Preserve the Phase 14.3 convention.

Page one should not unnecessarily canonicalize UI navigation to:

```text
/search?search=chair&page=1
```

Prefer:

```text
/search?search=chair
```

for page one.

Do not implement comprehensive SEO canonical tags; that remains Phase 14.7.

---

# 29. Preserve Search Term Across Pagination

Required:

```text
/search?search=chair
→ Next
→ /search?search=chair&page=2

page 2
→ Previous
→ /search?search=chair
```

Do not lose the query.

---

# 30. Do Not Implement Filters Yet

Phase 14.6 owns filters and sorting.

Do NOT add UI for:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
```

during Phase 14.5.

---

# 31. Query-Parameter Boundary

The routing contract allows catalog query parameters generally, but Phase 14.5 owns only:

```text
search
page
```

and any already-established pagination parameter necessary for the existing ProductGrid contract.

Do not prematurely build the Phase 14.6 filter parser/UI.

---

# 32. Unknown Parameters

Do not globally strip/rewrite unknown query parameters.

Follow the existing routing convention.

The search page should read only the parameters it owns.

---

# 33. Search Results Heading

For a meaningful query, provide a clear page heading.

Example structure:

```text
Search results
"Dining table"
```

or equivalent.

Avoid awkward keyword-stuffed headings.

Exactly one H1.

---

# 34. Search Term Display

When echoing the user's search term:

```text
render as text
never HTML
```

Do not use:

```text
dangerouslySetInnerHTML
```

Do not attempt custom match highlighting with HTML injection.

---

# 35. No Search-Term Highlighting Requirement

Do not add complex highlighting of matching fragments.

The backend returns products, not authoritative match ranges.

No regex-based HTML highlighting is required.

---

# 36. Product Results

Use the canonical:

```text
ProductGrid
ProductCard
```

from Phase 14.3.

Do not create search-specific cards.

---

# 37. Product Navigation

Search result ProductCards must continue to navigate to:

```text
/products/{backendSlug}
```

using authoritative Laravel slugs.

No locally generated slug.

---

# 38. Search Empty State

A successful search with zero matches is:

```text
HTTP 200
```

It is NOT:

```text
404
error
exception
```

Render a factual empty state.

Example intent:

```text
No furniture found for “<term>”.
Try another search.
```

Do not invent recommendations.

---

# 39. Empty Search vs Empty Results

These are different states:

```text
/search
→ no query / search-entry state

/search?search=zzzzzz
→ query executed / zero-result state
```

Do not collapse them.

---

# 40. No Fake Recommendations

Zero-result search must not invent:

```text
Popular products
Trending now
Customers also searched
Recommended for you
```

unless authoritative data exists.

---

# 41. No Search History

Do not implement:

```text
recent searches
localStorage history
account search history
```

---

# 42. No Analytics Infrastructure

Do not introduce search analytics during this phase.

---

# 43. Error Handling

Search uses the established Phase 13.8 failure architecture.

Expected distinctions:

```text
200 + products
→ results

200 + empty data
→ zero-result state

422
→ expected query/validation handling

429
→ respect Retry-After architecture

5xx
→ unexpected failure

network failure
→ unexpected transport failure

timeout
→ timeout transport failure
```

Do not convert failures into empty search results.

---

# 44. Search Is Never Product 404

A search returning zero products must never call:

```text
notFound()
```

The search page exists independently of whether matches exist.

---

# 45. Hard-404 Proxy Boundary

Phase 14.4 added hard-404 preflight for:

```text
/products/[slug]
```

Phase 14.2A added it for:

```text
/categories/[slug]
```

`/search` must NOT receive resource-existence preflight.

Required:

```text
/search
→ no hard-404 catalog preflight
```

---

# 46. Proxy Regression

Verify the existing proxy remains scoped correctly:

```text
/categories/[slug]
→ category resource preflight

/products/[slug]
→ product resource preflight

/products
→ no detail preflight

/search
→ no resource preflight
```

---

# 47. Search Must Not Trigger CAT-002

Search results use CAT-001 Product Summary.

Do not fetch every result's CAT-002 detail resource.

Forbidden:

```text
CAT-001
→ loop products
→ CAT-002 for each result
```

That would create N+1 API traffic.

---

# 48. Product Summary Is Enough

Search results should use the same Product Summary representation as `/products`.

Do not require:

```text
full description
full gallery
full variants
timestamps
```

for search cards.

---

# 49. Search Form Design

The search field should feel integrated with SL Furnitures:

```text
quiet
clear
functional
editorial
spacious
```

Not:

```text
command palette
tech dashboard
neon search bar
oversized pill
floating glass panel
```

---

# 50. Search Field Label

The control must have an accessible name.

Prefer a visible label where composition permits.

A placeholder is not a replacement for a label.

---

# 51. Search Button

Use an actual submit button.

Do not make a decorative icon the only inaccessible action.

If an icon-only control is genuinely used, it requires an accessible label.

---

# 52. Material UI Icons

Website icon policy remains:

```text
@mui/icons-material only
```

Do not add:

```text
Lucide
Heroicons
react-icons
Font Awesome
custom search SVG
emoji
```

---

# 53. Search Icon Restraint

One search icon where functionally useful is enough.

Do not decorate:

```text
heading
empty state
result count
product cards
```

with repeated search icons.

---

# 54. Search Input Autofocus

Do not automatically autofocus if doing so causes:

```text
unexpected mobile keyboard
scroll jump
focus theft
```

Default to normal document focus unless repository UX rules justify otherwise.

---

# 55. Search Form Submission

Submitting must work via keyboard:

```text
focus input
type term
Enter
→ search
```

and via submit control.

---

# 56. Search Form Reuse

If both header and search page need an actual search form, prefer one reusable semantic responsibility.

But do not force identical layout if header and page compositions differ.

Shared behavior does not require identical presentation.

---

# 57. Design-System Authority

All visual implementation must consume:

```text
tokens.css
→ MUI theme
→ existing primitives
→ search composition
```

No search-specific design scale.

---

# 58. No Ad-Hoc Visual Values

Do not invent:

```text
hex colors
spacing
font sizes
line heights
radii
shadows
breakpoints
focus rings
motion timings
container widths
```

---

# 59. Typography

Use Young Serif selectively for appropriate editorial/display roles.

Search UI controls/results metadata remain utility sans.

Do not make form controls Young Serif.

---

# 60. Product Cards

Product cards remain restrained:

```text
image
name
price
small factual state
```

Do not make search results visually different from other catalog results merely because they came from search.

---

# 61. No Search Result Badges

Do not add:

```text
Best match
Top result
Popular
Trending
Recommended
```

unless the backend supplies authoritative semantics.

---

# 62. Result Count

If the existing CAT-001 pagination metadata supplies total count, it may be displayed factually.

Do not calculate total by:

```text
results.length
```

when pagination exists.

---

# 63. Result Count Copy

If displayed, distinguish:

```text
total matching products
```

from:

```text
products on this page
```

Do not misrepresent pagination metadata.

---

# 64. Relevance Claims

Do not label results:

```text
Most relevant
Best match
```

unless the backend contract explicitly guarantees that ordering.

The frontend must not infer ranking semantics.

---

# 65. Sorting Boundary

Do not add a “Sort by relevance” control.

Phase 14.6 owns sorting UI, and `relevance` is not in the frozen public sort allow-list unless the repository now explicitly says otherwise.

Frozen explicit sort fields are:

```text
created_at
price
name
```

Do not invent another sort value.

---

# 66. Search Pipeline

Preserve backend authority:

```text
search
→ filter
→ sort
→ paginate
```

Phase 14.5 supplies the search portion.

Phase 14.6 will expose appropriate filters/sorting.

---

# 67. Responsive Layout

Reuse:

```text
ContentContainer
SiteSection
ProductGrid
canonical breakpoints
canonical gutters
```

Do not create a search-specific responsive system.

---

# 68. Mobile Search

At 320px:

```text
search field fits
submit control remains usable
term does not overflow
result heading reflows
ProductGrid remains correct
pagination remains usable
```

Do not solve narrow widths by globally hiding overflow.

---

# 69. Desktop Search

Do not stretch the search field across an unreadably wide page merely because space exists.

Use existing content/layout authority.

---

# 70. Long Queries

Test a long but valid search term.

The URL, heading, field and empty-state copy must wrap safely.

Do not truncate the actual search term in a way that obscures what was searched.

---

# 71. Special Characters

Test URL-safe behavior for terms containing:

```text
&
%
_
\
quotes
multiple spaces
Unicode
```

The frontend must not crash or generate malformed URLs.

---

# 72. Security

User-entered search terms are untrusted text.

Required:

```text
no raw HTML rendering
no eval
no custom SQL
no direct database access
no logging sensitive request state unnecessarily
```

React text rendering should provide the normal escaping boundary.

---

# 73. Search Form and XSS

Add regression coverage ensuring a term conceptually like:

```text
<script>alert(1)</script>
```

is rendered as text and never executed/interpreted as markup.

Do not build your own HTML sanitizer merely for plain-text React rendering.

---

# 74. Accessibility

Verify:

```text
one main
one H1
logical headings
search landmark/form semantics
accessible search label
keyboard submit
visible focus
touch target size
result links keyboard accessible
pagination accessible
empty state understandable
status not color-only
200% reflow
```

---

# 75. Search Landmark

Use appropriate search semantics.

Avoid unnecessary nested/duplicate search landmarks if both header and page forms are present.

If multiple search landmarks exist, ensure they can be distinguished accessibly where required.

---

# 76. Focus Behavior

After normal server navigation, do not implement custom focus manipulation unless needed.

Do not forcibly move focus into results using client JavaScript.

Preserve normal browser/document navigation.

---

# 77. JavaScript Boundary

Expected new `"use client"` files:

```text
NONE
```

A normal search form does not need client JavaScript.

If the agent believes otherwise, STOP and justify why native GET submission is insufficient before introducing it.

---

# 78. Fixture Policy

The API remains the default.

Do not catch API errors and substitute fixtures.

If the existing explicit development fixture mode can support search safely, it may be extended only if useful for visual verification.

Requirements remain:

```text
explicit fixture mode
visible fixture notice
no error fallback
same ProductGrid/ProductCard
same query semantics as far as fixture mode explicitly documents
```

---

# 79. Fixture Search Is Not Backend Search Proof

If fixtures are used for visual search testing:

```text
fixture search
≠
proof of Laravel FULLTEXT behavior
```

Do not claim otherwise.

---

# 80. Do Not Reimplement FULLTEXT in Fixtures

Do not build a sophisticated client search engine merely to make fixtures behave like MySQL.

A small deterministic fixture matcher, if already supported by the fixture architecture, may exist only for visual development.

Production/default mode remains CAT-001.

---

# 81. Local Empty Database

If the local Laravel catalog remains empty:

```text
/search?search=chair
→ HTTP 200
→ zero-result state
```

This is valid runtime verification.

Do not seed production data solely to make search screenshots look populated.

---

# 82. Backend Search Verification

Where the existing backend test infrastructure permits, run the established CAT-001 search tests rather than rewriting them.

The backend already has dedicated coverage for:

```text
search semantics
inactive variant exclusion
SQLite fallback
MySQL/MariaDB FULLTEXT
```

Do not change backend code during Phase 14.5 unless a genuine frozen-contract defect is discovered.

---

# 83. Search Page Runtime Verification

Verify:

```text
/search
→ HTTP 200

/search?search=
→ HTTP 200

/search?search=%20%20%20
→ HTTP 200

/search?search=chair
→ HTTP 200

/search?search=definitely-no-match
→ HTTP 200
```

No query state should produce a resource 404.

---

# 84. Pagination Runtime

Where enough fixture/API results exist, verify:

```text
/search?search=chair&page=2
```

preserves `search=chair`.

If the real database cannot produce multiple pages, use focused automated/fixture testing without fabricating production data.

---

# 85. Existing Route Regression

Verify:

```text
/
→ works

/products
→ HTTP 200

/products/[missing]
→ HTTP 404

/categories/[missing]
→ HTTP 404

/search
→ HTTP 200
```

---

# 86. Search Header Regression

The global search affordance must now lead to a real implemented route.

No:

```text
href="#"
disabled fake control
dead /search destination
```

---

# 87. Search Result Product Navigation

From a populated fixture/API search result:

```text
ProductCard
→ /products/{backendSlug}
```

must continue to work.

Do not add search context to the product URL such as:

```text
/products/chair?from=search
```

unless explicitly required later.

---

# 88. Search Page Status Semantics

Expected:

```text
no term
→ 200 entry state

matches
→ 200 results

zero matches
→ 200 empty state

invalid supported query value
→ expected validation handling

backend failure
→ error architecture
```

No accidental 404.

---

# 89. No Search-Specific Error Page

Reuse the established failure architecture.

Do not create:

```text
SearchErrorPage
Search404
SearchNetworkError
```

unless an existing reusable state cannot represent a genuine feature-owned expected failure.

---

# 90. No New Loading Architecture

Use existing App Router loading behavior where applicable.

Do not add artificial delays or client spinners.

---

# 91. Phase 14.6 Boundary

Do NOT implement:

```text
filter drawer
filter chips
category filter UI
product type filter
availability filter
price range
sort selector
clear-all filters
active filter count
mobile filter sheet
```

Those belong to Phase 14.6.

---

# 92. Phase 14.7 Boundary

Do NOT implement comprehensive:

```text
search metadata
canonical metadata
robots decisions
Open Graph
Twitter cards
dynamic title architecture
```

Phase 14.7 owns SEO metadata.

---

# 93. Phase 14.8 Boundary

Do NOT add structured data.

---

# 94. Phase 14.9 Boundary

Do NOT modify sitemap/robots.

---

# 95. Phase 14.10 Boundary

Do not create a search-driven recommendation/internal-linking system.

---

# 96. Phase 14.11 Boundary

Use existing optimized ProductCard image behavior.

Do not expand into comprehensive image-performance work.

---

# 97. Backend Boundary

Expected backend changes:

```text
NONE
```

CAT-001 search already exists.

If the frontend discovers a genuine frozen-contract/backend mismatch:

```text
STOP
document exact mismatch
identify owning backend phase
do not silently redesign the API
```

---

# 98. Flutter Boundary

Expected:

```text
NONE
```

---

# 99. Dependencies

Expected:

```text
NONE
```

---

# 100. Component Creation Gate

For every new component report:

```text
Component:
<name>

Stable responsibility:
<responsibility>

Existing components inspected:
<list>

MUI primitives considered:
<list>

Why existing composition was insufficient:
<reason>

Reusable outside /search:
YES / NO
```

A component existing only to hide a few `sx` properties is not sufficient justification.

---

# 101. Likely Legitimate New Responsibility

A reusable semantic search form may be legitimate if both:

```text
header
search page
```

need the same behavior.

But do not force shared visual composition where the contexts differ.

Behavioral reuse and visual composition can remain separate.

---

# 102. Forbidden Reinventions

Avoid:

```text
SearchProductCard
SearchProductGrid
SearchPrice
SearchContainer
SearchSection
SearchPagination
SearchTypography
SearchButton
```

when canonical equivalents already exist.

---

# 103. Automated Test Command

Add a focused script following project convention, preferably:

```text
npm run test:search
```

if consistent with the current package scripts.

---

# 104. Minimum Search Contract Tests

Cover:

```text
/search route exists
public access
server-first page
canonical search parameter is "search"
q/query aliases are not introduced
meaningful query calls CAT-001 with search=
no /api/v1/search endpoint used
API client reused
no client-side product filtering
no search dependency
missing term renders entry state
empty term renders entry state
whitespace-only term renders entry state
zero matches render HTTP-200 empty state
results reuse ProductGrid
results reuse ProductCard
ProductCard URLs use backend slug
pagination preserves search term
page one removes unnecessary page=1
/search receives no hard-404 preflight
/products collection remains unaffected
product/category hard-404 behavior remains intact
non-404 API failures are not converted to empty results
```

---

# 105. Search Security Tests

Cover:

```text
special characters safely encoded
HTML-like query rendered as text
no dangerouslySetInnerHTML
no query interpolation into markup
```

---

# 106. Search Accessibility Tests

Cover where practical:

```text
search field accessible name
submit button semantics
keyboard submit
one H1
ProductCard link semantics
pagination labels
zero-result message
```

---

# 107. Regression Suites

Run the established equivalents of:

```text
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
npm run test:routing
```

Use actual available script names.

---

# 108. Static Validation

Must pass:

```text
TypeScript
ESLint
production build
git diff --check
```

---

# 109. Visual Verification

Inspect at minimum:

```text
320px
390px
960px
1440px
```

Test both:

```text
search-entry state
zero-results state
populated fixture/API results where available
```

---

# 110. 200% Reflow

Verify:

```text
search input remains usable
button remains reachable
query heading wraps
results reflow
pagination remains usable
no horizontal document scroll
```

---

# 111. Horizontal Overflow

Required:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

Do not use global `overflow-x:hidden` as a fix.

---

# 112. Visual Quality Audit

Search must remain consistent with the existing storefront.

Check for:

```text
excessive rounded search container
oversized search icon
pill-heavy UI
dashboard styling
floating glass search
gratuitous cards
decorative empty-state illustration
fake recommendation blocks
```

Expected:

```text
NONE
```

---

# 113. Token Audit

Search Phase 14.5 changes for:

```text
raw hex
arbitrary spacing
arbitrary typography
custom radius
custom shadow
raw breakpoint
custom transition
```

Expected:

```text
NONE
```

unless already expressed through approved theme/token authority.

---

# 114. Client Boundary Audit

Report every new file containing:

```text
"use client"
```

Expected:

```text
NONE
```

Existing client boundaries from previous phases are allowed.

---

# 115. Network Audit

For one search page render, verify:

```text
one appropriate CAT-001 collection request
no CAT-002 N+1
no /api/v1/search
no duplicate search request caused by component structure
```

Framework/runtime verification traffic does not count as catalog duplication; distinguish it clearly.

---

# 116. Documentation

Update:

```text
phases/group-N-phases.md
```

with the Phase 14.5 execution record.

Update `frontend/AGENTS.md` only if Phase 14.5 establishes a genuinely durable rule not already covered by:

```text
ROUTING.md
design system
responsive authority
API architecture
```

---

# 117. ADR

Expected:

```text
NONE
```

The route and search architecture are already decided.

Do not create an ADR simply to record implementation.

If a material architectural conflict is discovered, STOP and explain it before adding a decision.

---

# 118. Completion Report

Return:

```text
Phase 14.5 status:
PASS / BLOCKED


ROUTE

Search route:
<actual>

Canonical parameter:
search / FAIL

Alternative q/query parameter introduced:
NO / FAIL

Public:
YES / NO

Server Component:
YES / NO


SEARCH ARCHITECTURE

Website route:
/search

Laravel endpoint:
<actual>

CAT-001 reused:
YES / NO

/api/v1/search introduced:
NO / FAIL

Frontend search engine:
NONE / FAIL

New search dependency:
NONE / FAIL

API client reused:
YES / NO

Cache policy:
<actual>


SEARCH FORM

Method:
GET / <other>

Action:
<actual>

Input name:
search / FAIL

Accessible label:
PASS / FAIL

Keyboard Enter:
PASS / FAIL

Works without JS:
YES / NO

Debounce:
NONE / FAIL

Autocomplete/typeahead:
NONE / FAIL


HEADER

Existing search affordance:
REUSED / <details>

Destination:
/search / FAIL

Header rebuilt:
NO / FAIL

Dead search link:
NONE / FAIL


QUERY STATES

/search:
HTTP <status>
State: <state>

/search?search=:
HTTP <status>
State: <state>

Whitespace query:
HTTP <status>
State: <state>

Matching query:
HTTP <status>
State: <state>

Zero-result query:
HTTP <status>
State: <state>


RESULTS

ProductGrid reused:
YES / NO

ProductCard reused:
YES / NO

Product Summary used:
YES / NO

CAT-002 per result:
NONE / FAIL

Backend slug navigation:
PASS / FAIL

Result count:
<behavior>

Fake relevance claims:
NONE / FAIL


PAGINATION

Existing pagination reused:
YES / NO

Search term preserved:
PASS / FAIL

Page 1 URL:
<example>

Page 2 URL:
<example>

Unnecessary page=1:
NONE / FAIL


EMPTY STATES

No-query state:
<summary>

Zero-result state:
<summary>

Zero results treated as 404:
NO / FAIL

Fake recommendations:
NONE / FAIL


ERRORS

422:
<behavior>

429:
<behavior>

5xx:
<behavior>

Network failure:
<behavior>

Timeout:
<behavior>

API failure converted to empty results:
NO / FAIL


PROXY / HARD 404

/search preflight:
NONE / FAIL

/products preflight:
NONE / FAIL

/products/[slug] hard 404:
PASS / FAIL

/categories/[slug] hard 404:
PASS / FAIL


SECURITY

Search term rendered as text:
PASS / FAIL

HTML-like query safe:
PASS / FAIL

dangerouslySetInnerHTML:
NONE / FAIL

Special-character URL encoding:
PASS / FAIL

Client-side SQL/search semantics:
NONE / FAIL


DESIGN SYSTEM

Frozen tokens:
PASS / FAIL

New token authority:
NONE / FAIL

Raw colors:
NONE / <list>

Raw spacing:
NONE / <list>

Raw typography:
NONE / <list>

Raw radii:
NONE / <list>

Raw shadows:
NONE / <list>

Raw breakpoints:
NONE / <list>


COMPONENT REUSE

Existing components reused:
<list>

New components:
<list>

New-component justification:
<details>

SearchProductCard:
NONE / FAIL

SearchProductGrid:
NONE / FAIL

SearchPagination:
NONE / FAIL

Duplicate price formatter:
NONE / FAIL


RESPONSIVE

320:
PASS / FAIL

390:
PASS / FAIL

960:
PASS / FAIL

1440:
PASS / FAIL

200% reflow:
PASS / FAIL

Horizontal overflow:
NONE / FAIL

Long query:
PASS / FAIL


ACCESSIBILITY

One main:
PASS / FAIL

One H1:
PASS / FAIL

Search semantics:
PASS / FAIL

Input label:
PASS / FAIL

Keyboard submit:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Product links:
PASS / FAIL

Pagination:
PASS / FAIL

Empty state:
PASS / FAIL


SERVER / CLIENT

Server-first:
PASS / FAIL

New "use client" files:
NONE / <list>

Client search state:
NONE / FAIL

Browser-width branching:
NONE / FAIL


NETWORK

CAT-001 requests:
<count/reason>

CAT-002 result requests:
NONE / FAIL

/api/v1/search requests:
NONE / FAIL

Unexpected duplicate requests:
NONE / <details>


PHASE BOUNDARIES

Filters implemented:
NO

Sorting UI implemented:
NO

Autocomplete implemented:
NO

Suggestions implemented:
NO

Search history implemented:
NO

Search analytics implemented:
NO

Comprehensive SEO implemented:
NO

Structured data implemented:
NO

Sitemap/robots changed:
NO

Recommendation engine implemented:
NO

Backend changed:
NO

Flutter changed:
NO

Dependencies added:
NONE


VALIDATION

Search contract:
PASS / FAIL

Search security:
PASS / FAIL

Search accessibility:
PASS / FAIL

Product detail regression:
PASS / FAIL

Products regression:
PASS / FAIL

Category regression:
PASS / FAIL

Homepage regression:
PASS / FAIL

Hard-404 regression:
PASS / FAIL

API client:
PASS / FAIL

Theme:
PASS / FAIL

Layout:
PASS / FAIL

Responsive:
PASS / FAIL

States:
PASS / FAIL

Routing:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


VISUAL VERIFICATION

Search entry:
PASS / FAIL

Zero results:
PASS / FAIL

Populated results:
PASS / FAIL / NOT AVAILABLE

Mobile:
PASS / FAIL

Desktop:
PASS / FAIL

Console errors:
NONE / <details>


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

Phase 14.5:
PASS / BLOCKED

Phase 14.6:
READY / BLOCKED
```

---

# 119. STOP Condition

Phase 14.5 may be declared PASS only when:

- `/search` exists;
- `/search` is public;
- `search` is the canonical query parameter;
- no `q`, `query`, or other alias is introduced;
- search uses CAT-001;
- no `/api/v1/search` endpoint is invented;
- no frontend search engine is introduced;
- the canonical API client is reused;
- search remains server-first;
- normal search submission works without JavaScript;
- the URL is the durable search state;
- no-query, blank-query and whitespace-query states are intentional;
- blank search does not accidentally masquerade as a full-catalog search;
- zero results return HTTP 200;
- zero results are not treated as 404;
- API failures are not disguised as empty results;
- ProductGrid is reused;
- ProductCard is reused;
- Product Summary is used;
- no CAT-002 N+1 exists;
- ProductCard navigation continues using authoritative backend slugs;
- pagination preserves the search term;
- page one retains the clean URL convention;
- `/search` receives no hard-404 preflight;
- `/products` remains unaffected by detail preflight;
- category and product detail hard-404 behavior remains intact;
- header search now reaches a legitimate implemented route;
- no dead search interaction exists;
- no autocomplete/typeahead/debounce architecture is added;
- no search history or analytics architecture is added;
- no filter/sort UI is implemented early;
- no fake relevance claims are made;
- no unsupported sort value is invented;
- special characters are safely encoded;
- search terms are rendered only as escaped text;
- no `dangerouslySetInnerHTML` is introduced;
- frozen design tokens remain authoritative;
- no search-specific design system is created;
- no duplicate ProductCard/ProductGrid/Pagination/price formatter exists;
- responsive behavior passes;
- 200% reflow passes;
- horizontal overflow is absent;
- accessibility checks pass;
- new client JS is absent unless narrowly justified;
- no new dependency is added;
- backend remains unchanged unless a genuine contract defect blocks the phase;
- Flutter remains unchanged;
- Phase 14.6+ work remains untouched;
- product-detail regression passes;
- product-listing regression passes;
- category regression passes;
- homepage regression passes;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 14.5 — PASS
Phase 14.6 — READY
```

Do not start Phase 14.6 automatically.

**Search is a thin, truthful website experience over the authoritative CAT-001 catalog query—not a second search engine. Keep the URL shareable, the form native, the results server-rendered, the ProductGrid canonical, and Laravel responsible for deciding what matches.**