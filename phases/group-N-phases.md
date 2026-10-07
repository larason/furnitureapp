# PHASE 14.10 — INTERNAL LINKING

ENTRY STATE

Phase 14.1 — Homepage — PASS
Phase 14.2 — Category Pages — PASS
Phase 14.3 — Product Listing — PASS
Phase 14.4 — Product Detail — PASS
Phase 14.5 — Search — PASS
Phase 14.6 — Filters & Sorting — PASS
Phase 14.7 — SEO Metadata — PASS
Phase 14.8 — Structured Data — PASS
Phase 14.9 — Sitemap / Robots — PASS
Phase 14.10 — Internal Linking — ACTIVE

Phase 14.11 — Image / Performance Optimization — NOT STARTED

Do not start Phase 14.11 automatically.


1. OBJECTIVE

Audit and complete the public catalog's internal-linking architecture so users and crawlers can naturally navigate between implemented canonical storefront resources.

Internal links must arise from genuine information architecture and shopping/discovery relationships.

The target canonical graph is primarily:
```
Homepage
  ↓
Product collection
  ↓
Product detail

Homepage
  ↓
Category
  ↓
Product detail

Category
  ↓
Product detail

Product detail
  ↑
Category

Search
  ↓
Product detail

Header/navigation
  ↓
Implemented category/catalog/search destinations

Pagination
  ↔
Adjacent collection pages
```
The phase must NOT create artificial SEO link farms, keyword blocks, hidden links, speculative related-products systems, or links to routes that do not exist.


2. READ BEFORE CODING

Read and obey:
```
AGENTS.md
frontend/AGENTS.md

frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md

frontend/design-system/DESIGN.md
frontend/design-system/COMPONENTS.md
frontend/design-system/ACCESSIBILITY.md

docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/decisions.md
docs/domain/business-rules.md

phases/group-N-phases.md

Then inspect the ACTUAL current implementation from Phases 14.1–14.9:

app/page.tsx
app/products/page.tsx
app/products/[slug]/page.tsx
app/categories/[slug]/page.tsx
app/search/page.tsx

ProductCard
ProductGrid
CatalogDiscovery
ProductCollectionControls
pagination components
breadcrumb implementation
SiteHeader
PrimaryCategoryNavigation
MobileNavigation
SiteFooter
site-navigation.ts
category navigation data/fixture
catalog data helpers
SEO metadata
structured data
crawl/sitemap helpers
proxy.ts
```
Repository reality wins over assumptions in this prompt.


3. GIT POLICY

Before ANY Git command:

locate/read/follow root skill:
git-workflow-and-versioning

Preserve unrelated owner changes.

Never commit:
```
.env
.env.local
secrets
```
Use one atomic Phase 14.10 commit.


4. FIRST TASK — BUILD AN INTERNAL-LINK AUDIT

Before changing code, enumerate every currently rendered internal navigation surface.

For each, record:
```
SOURCE
ANCHOR / UI ELEMENT
DESTINATION
CANONICAL / DISCOVERY / RESERVED
IMPLEMENTED?
LINK ACTIVE?
SEMANTIC LINK?
BACKEND SLUG?
BROKEN / STALE?
ACTION REQUIRED?
```
At minimum audit:

- logo;
- desktop category navigation;
- mobile category navigation;
- header search;
- footer links;
- homepage category discovery;
- homepage product cards;
- homepage collection actions;
- category breadcrumbs;
- category child-category discovery;
- category product cards;
- category pagination;
- /products product cards;
- /products pagination;
- search product cards;
- search pagination;
- PDP breadcrumbs;
- PDP category context;
- PDP gallery controls, ensuring they are not mistaken for navigation;
- empty-state recovery links;
- not-found recovery links.

Do not begin by adding new sections.

Understand the existing graph first.


5. ROUTING AUTHORITY

frontend/web/ROUTING.md remains authoritative.

Canonical public routes currently include:
```
/
 /products
 /products/[slug]
 /categories/[slug]
 /search
```
Only use other destinations if inspection proves they are implemented.

Do not activate a route merely because ROUTING.md reserves it.


6. BACKEND SLUG AUTHORITY

Product links:

/products/{product.slug}

Category links:

/categories/{category.slug}

Use Laravel-returned slugs.

Never:

- slugify product.name;
- slugify category.name;
- derive paths from IDs;
- use database numeric IDs;
- lowercase arbitrary labels;
- transliterate;
- use fixture-only production slugs.


7. SEMANTIC LINK RULE

Ordinary navigation uses semantic:

next/link

or the project's established accessible wrapper around it.

Do not use:

router.push()

for ordinary links.

Do not use:

button + onClick

to simulate navigation.

Do not use:

<div onClick>

Do not use:

href="#"

Do not add JavaScript merely to make normal navigation work.


8. SERVER-FIRST

Internal linking must not turn server-rendered pages into client components.

Expected new "use client":

NONE

unless inspection proves an existing interactive boundary genuinely owns the behavior.

A simple link never justifies a new client component.


9. PRODUCTCARD IS THE CANONICAL PRODUCT LINK SURFACE

Phase 14.4 already activated ProductCard links using backend slugs.

Preserve that architecture.

ProductCard should remain the canonical reusable product discovery component used by:

homepage
category
/products
/search

Do not create:

HomepageProductLink
CategoryProductLink
SearchProductCard
SeoProductCard

Do not fork ProductCard for internal linking.


10. PRODUCT CARD LINK TARGET

Every actionable ProductCard with a valid backend slug must resolve to:

/products/{slug}

No query-state copy should become the canonical product destination.

For example, do NOT produce:
```
/products/sofa?category=living-room
/products/sofa?search=sofa
/products/sofa?from=homepage
```
unless an already-approved UX requirement exists.

Current expected canonical product link is clean.


11. PRODUCT CARD CLICK AREA

Inspect the current ProductCard semantics.

Prefer a clear semantic product link around the meaningful product identity/navigation surface.

Do not introduce nested interactive controls.

If the whole card is already correctly linked and accessible, leave it alone.

Do not redesign ProductCard merely for SEO.


12. PRODUCT LINK ANCHOR TEXT

The accessible link name must contain the actual product name.

Do not use repetitive generic labels such as:

View
Learn more
Click here
See product

as the only accessible link text.

Visible product-name linking is preferred where compatible with the existing ProductCard.


13. HOMEPAGE INTERNAL LINKING

Audit Phase 14.1.

Homepage should naturally expose implemented discovery paths through:

- category/room discovery;
- product cards;
- a restrained route to the full product collection where appropriate.

Do not redesign the homepage.

Do not add an "SEO links" section.


14. HOMEPAGE → PRODUCTS

If the homepage currently shows a selected/featured product subset but has no natural way to continue browsing the full catalog, add ONE restrained contextual link such as:

View all furniture

to:

/products

ONLY if it fits the existing section composition and design-system conventions.

If an equivalent canonical action already exists:

reuse it.

Do not duplicate it.


15. HOMEPAGE → CATEGORIES

Category discovery items with authoritative category slugs should link to:

/categories/{slug}

This should already exist from Phase 14.2.

Verify rather than reinvent.

Do not activate fixture category links in API mode unless the destination is backed by authoritative catalog data.


16. CATEGORY PAGE

The category page should provide:

breadcrumb:
Home → Category

and product discovery through canonical ProductCard links.

If child categories are actually returned by authoritative public category data and displayed, they should link to their canonical category slugs.

Do not fabricate child-category links from local taxonomy knowledge.


17. CATEGORY IMAGE REFINEMENT

A separate category-page visual refinement may already have removed the standalone category image.

Do not undo it.

Phase 14.10 does not reintroduce:

category hero image
decorative category banner
SEO image block

Internal linking is independent of that visual decision.


18. CATEGORY → PRODUCTS

Products displayed on:

/categories/{slug}

must link directly to canonical:

/products/{product.slug}

Do NOT link through:

/products?category={slug}

for the individual product.


19. CATEGORY → GENERIC COLLECTION

Do not automatically add a generic /products link to every category page just for link count.

Only retain/add it if it is a useful user navigation action such as:

All furniture

and it fits existing IA.

Avoid redundant links.


20. PRODUCT DETAIL BREADCRUMB

Audit PDP breadcrumb.

Expected semantic hierarchy where authoritative category context exists:

Home
→ Furniture
→ Category
→ Product

Expected destinations:
```
Home → /
Furniture → /products
Category → /categories/{backend-category-slug}
Product → current page, non-link preferred
```
Do not locally derive the category slug.


21. PRODUCT BREADCRUMB TRUTH

The visible PDP breadcrumb and Phase 14.8 BreadcrumbList should describe the same hierarchy.

Do not create contradictory navigation such as:

visible:
Home → Products → Sofa

JSON-LD:
Home → Furniture → Living Room → Sofa

when CAT-002 supplies category context.

Reconcile using authoritative data.


22. CURRENT ITEM

The current breadcrumb item should normally be:

aria-current="page"

and not unnecessarily link back to itself.

Do not create self-links purely for SEO.


23. CATEGORY BREADCRUMB

Expected:

Home → Category

where Category is the current item.

If the API provides a truthful parent hierarchy and the UI already supports it, preserve it.

Do not fabricate hierarchy from hard-coded taxonomy.


24. SEARCH RESULTS

Search is a discovery surface and remains:

noindex, follow

Search result ProductCards SHOULD link to canonical product detail pages.

This is exactly what `follow` is useful for.

Do not disable product links merely because /search is noindex.


25. SEARCH QUERY PRESERVATION

Search pagination must continue preserving the current search query.

Do not modify the frozen Phase 14.5 behavior.

But product-detail links themselves should remain clean canonical URLs.

Do not append search terms to PDP URLs.


26. FILTERED PRODUCT COLLECTION

Filtered/sorted /products states remain discovery surfaces.

Product cards from filtered results must still link directly to canonical PDP URLs.

Do not propagate filter parameters into product URLs.


27. PAGINATION

Preserve Phase 14.3/14.5/14.6 server-first pagination.

Pagination links should be real semantic anchors.

Do not replace them with client-side button navigation.


28. CLEAN PAGE ONE

Preserve:

/products

for page 1.

Do not generate:

/products?page=1

The same rule applies to category/search pagination according to their existing architecture.


29. PAGINATION STATE PRESERVATION

When pagination belongs to a discovery state, preserve only the parameters necessary for that discovery state.

Examples:

search:
search + page

filtered listing:
approved filter/sort state + page

category:
category route + page

Do not silently drop active state.

Do not append unrelated state.


30. PREVIOUS/NEXT

Existing previous/next pagination should remain accessible.

Do not implement numeric pagination merely for SEO unless user experience genuinely requires it.

No need to expose hundreds of page-number links.


31. HEADER CATEGORY NAVIGATION — IMPORTANT

The category navigation was originally introduced with fixture-backed taxonomy during the layout phase.

Now inspect its current state carefully.

Determine whether production navigation is still backed by:

category-navigation.fixture.ts

or whether later phases replaced it with authoritative catalog data.

Report this explicitly.


32. NO PRODUCTION FIXTURE TAXONOMY

If production header/mobile navigation still relies on the old fixture as its production category authority:

do NOT silently leave this unresolved.

Phase 14.10 is the correct phase to reconcile real internal navigation with real catalog authority.

However, do not solve it by adding duplicate taxonomy constants.


33. HEADER DATA STRATEGY

If header category navigation requires authoritative catalog categories, first inspect the existing architecture.

Prefer reuse of the canonical CAT-003 catalog category retrieval/data mapping.

Do not:

- query Laravel DB directly;
- import Laravel seeders;
- duplicate category arrays;
- create a second API client;
- hard-code slugs.


34. ROOT-LAYOUT FETCH WARNING

Do NOT casually add an uncached Laravel fetch to the root layout on every request.

Before changing global navigation data flow, assess:

- current shell ownership;
- Next.js server-component caching behavior;
- existing catalog helper cache policy;
- API failure semantics;
- build/runtime behavior;
- whether the navigation already has a suitable data source.

If making global nav data-driven would create a new significant availability/caching architecture decision:

STOP and report it.

Do not make the entire website unavailable merely because category navigation API retrieval fails.


35. SAFE ALTERNATIVE

If authoritative dynamic global category navigation cannot be introduced safely within existing architecture, keep the current established navigation behavior and document the remaining limitation.

Do not invent a new caching layer in Phase 14.10.

Phase 14.11 owns broader performance/cache optimization.


36. STICKY CATEGORY NAVIGATION

A separately requested sticky category navbar may already be implemented.

Preserve it.

Do not remove sticky behavior while changing link destinations.

Do not redesign the header.

Verify sticky navigation still:

- uses semantic links;
- does not obscure focused elements;
- behaves correctly at responsive breakpoints;
- has appropriate stacking from existing z-index tokens.


37. MOBILE NAVIGATION

Desktop and mobile navigation should expose the same canonical destination semantics where appropriate.

Do not allow:

desktop → real category
mobile → stale fixture category

or vice versa.

They should share one route/data authority.


38. FOOTER AUDIT

Inspect SiteFooter.

Footer should contain only useful, implemented destinations.

Do not add dozens of category/product links to increase internal-link count.

Do not create a keyword-heavy SEO footer.


39. FOOTER CATALOG LINK

If no useful catalog entry point exists in the footer and the existing footer IA naturally has a shopping/discovery group, `/products` may be linked there.

But do not redesign the footer solely for Phase 14.10.


40. RESERVED FOOTER ROUTES

Do not activate:

Contact
Account
Orders
Cart
Checkout
Furniture Request

unless the actual Next.js route is implemented.

Reserved ≠ implemented.


41. MADE TO ORDER

MADE_TO_ORDER remains a first-class product state.

Do not route made-to-order product cards to a nonexistent request page.

They still link to their canonical PDP:

/products/{slug}

until a real request journey is implemented.


42. NO FAKE REQUEST CTA

Do not add:

Request this item
Get a quote
Order now
Buy now

as internal links unless the destination/workflow actually exists.

Request-first means absence of checkout does not authorize fake request navigation.


43. RELATED PRODUCTS — OUT OF SCOPE BY DEFAULT

Do NOT invent a "You may also like" or "Related products" algorithm merely to create internal links.

There is no approved related-product API contract in the current phase.

No:

same-category guessed recommendation
random products
client-side recommendation
hard-coded recommendations


44. PDP CATEGORY DISCOVERY IS ENOUGH

For Phase 14.10, a truthful PDP link back to its authoritative category plus `/products` through breadcrumb/navigation provides sufficient reverse discovery.

Do not manufacture recommendation content.


45. CROSS-CATEGORY LINKS

Do not invent cross-category relationships.

The backend data model may support category structures/graphs internally, but unless a frozen public API exposes an approved relation for this page, do not use it.


46. INTERNAL LINK QUALITY > QUANTITY

Do not optimize for:

"number of links per page"

Optimize for:

- user navigation;
- canonical resource discovery;
- semantic hierarchy;
- crawl reachability;
- truthful context;
- accessibility.


47. NO HIDDEN LINKS

Forbidden:

display:none SEO links
visually hidden bulk navigation
zero-size anchors
off-screen keyword links
transparent links
CSS-hidden category lists

Visually-hidden text is allowed only for legitimate accessibility labeling, not crawler manipulation.


48. NO KEYWORD-STUFFED ANCHORS

Use natural UI labels:

Living Room
Furniture
View all furniture
Product Name

Do not generate anchors such as:

Best Premium Luxury Living Room Furniture Tanzania Cheap Sofa Online

unless that exact copy is legitimate visible editorial content, which it currently is not.


49. NO EXTERNAL SEO LINKS

Phase 14.10 concerns internal linking.

Do not add external backlinks/social links/directories for SEO.


50. CANONICAL VS DISCOVERY STATES

Keep the distinction established in Phase 14.7:

INDEXABLE CANONICAL:
/
 /products
 /products/[slug]
 /categories/[slug]

DISCOVERY / NOINDEX:
 /search
 filtered/sorted /products states

Internal links may point through discovery states when needed for user interaction, but canonical content links should favor canonical resources.


51. CATEGORY FILTER LINKS

Do not replace canonical category links with:

/products?category={slug}

The frozen API uses the category query parameter for product retrieval, but the WEBSITE canonical category surface is:

/categories/{slug}

These concepts must remain distinct.


52. FILTER CONTROL LINKS

Phase 14.6 controls may legitimately create query URLs.

Do not remove those because of internal-linking concerns.

They are user discovery controls, not canonical category navigation.


53. SITEMAP CONSISTENCY

Phase 14.9 sitemap contains:

/
 /products
 /categories/{slug}
 /products/{slug}

The natural internal-link graph should make these same resource classes reachable through the UI.

Do not add internal canonical resource classes that contradict the sitemap policy without a real reason.


54. ROBOTS CONSISTENCY

Do not modify Phase 14.9 robots rules.

Search/facet crawling policy remains unchanged.


55. SEO METADATA CONSISTENCY

Do not modify Phase 14.7 canonical/noindex policy unless verification uncovers a genuine contradiction caused by existing links.

Any such contradiction must be reported rather than silently redesigned.


56. STRUCTURED DATA CONSISTENCY

Phase 14.8 BreadcrumbList must stay consistent with visible breadcrumb hierarchy.

If visible breadcrumb implementation is corrected, update the JSON-LD only if necessary to keep both truthful and aligned.

Do not otherwise expand JSON-LD.


57. ORGANIZATION / WEBSITE JSON-LD

Do not change homepage WebSite/Organization JSON-LD for internal linking.

No SearchAction.


58. PRODUCT OFFERS

Do not add Product.offers.

Request-first decision remains unchanged.


59. HTTP STATUS

Internal linking changes must not weaken hard 404 behavior.

Verify:
```
missing /products/[slug] → HTTP 404
missing /categories/[slug] → HTTP 404
```
Do not modify proxy preflight unless a demonstrated internal-linking defect requires it.


60. LINK TO KNOWN PUBLIC RESOURCES ONLY

Never create a product/category link from an absent or invalid slug.

If the canonical component receives malformed data, fail safely according to existing data-boundary conventions.

Do not invent fallback slugs.


61. EMPTY CATALOG

When Laravel returns no products/categories:

- /products remains HTTP 200 with factual empty state;
- homepage/category behavior remains truthful;
- no fake product/category links are generated.

Do not create fixtures in API mode.


62. FIXTURE MODE

Explicit fixture mode may retain fixture-backed navigation for visual development where already designed.

But fixture links must never silently become production authority.

Keep API/default and explicit fixture behavior distinguishable.


63. LINK PREFETCH

Do not globally disable or aggressively enable Next.js prefetch as an SEO technique.

Keep framework defaults unless actual measured behavior requires change.

Phase 14.11 owns performance tuning.


64. PERFORMANCE BOUNDARY

Do not add extra CAT-002/CAT-004 fetches merely to construct links.

Use data already resolved for the page wherever possible.

No N+1 internal-link architecture.


65. PRODUCT COLLECTION DATA

CAT-001 summaries already provide product slugs.

Use them.

Do not fetch product details to build ProductCard links.


66. CATEGORY DATA

Use CAT-003/CAT-004 category slugs/context already available through approved data paths.

Do not fetch category detail solely to construct a link if the slug is already present.


67. ACCESSIBILITY

Every link must:

- be keyboard reachable;
- have meaningful accessible text;
- preserve visible focus;
- not rely on color alone where context is ambiguous;
- meet existing target-size rules where applicable.

Do not suppress outlines.


68. CURRENT PAGE NAVIGATION

Where navigation includes the current page, use appropriate:

aria-current="page"

when the existing component architecture supports it.

Do not add duplicate current-page self-links.


69. BREADCRUMB SEMANTICS

Visible breadcrumbs should use:

nav aria-label="Breadcrumb"

and an ordered hierarchy or MUI Breadcrumbs with equivalent semantics.

The current item should be identifiable.

Do not use breadcrumbs merely as decorative text.


70. RESPONSIVE

Internal links must remain usable at:
```
320
390
640
959
960
961
1024
1440
```
Pay particular attention to:

- sticky category navigation;
- breadcrumb wrapping;
- ProductCard link areas;
- pagination;
- mobile drawer navigation;
- long category/product names.


71. 200% REFLOW

Verify important internal navigation remains usable under browser zoom/reflow.

No horizontal page overflow caused by breadcrumb/link changes.


72. DESIGN SYSTEM

Use existing:

tokens
MUI theme
NavLink
ProductCard
ProductGrid
layout primitives
breadcrumb implementation
pagination implementation

Do not invent new colors, spacing, radii, shadows, or typography.


73. ICON POLICY

If any icon is genuinely needed:

@mui/icons-material only.

But internal-link work should not need decorative new icons.

Do not add arrows to every link.


74. URBAN LADDER REFERENCE

Urban Ladder remains structural/IA inspiration only.

Do not inspect/copy competitor source code.

Do not copy:

copy
visual treatment
link labels
footer
navigation taxonomy
SEO blocks


75. NO NEW DEPENDENCY

Expected:

dependencies added = NONE

Do not install SEO/link-analysis packages.


76. TEST INFRASTRUCTURE

Continue using:

node:test + tsx

Do not reintroduce:

load-ts.mjs
node:vm
eval
new Function
custom runtime TypeScript execution.


77. ADD FOCUSED TEST

Add:

npm run test:links

or equivalent existing naming convention.

Prefer:

test:links

This test should verify the internal-link contract rather than implementation trivia.


78. PRODUCTCARD LINK TEST

Verify ProductCard:

- links to /products/{backend slug};
- uses backend slug verbatim;
- has meaningful accessible product-name context;
- does not append search/filter/category tracking query;
- does not use machine ID.


79. HOMEPAGE LINK TEST

Verify:

- canonical category links use backend slugs;
- homepage product cards reach PDPs;
- /products is reachable through a natural collection action if implemented;
- no reserved/dead route becomes active.


80. CATEGORY LINK TEST

Verify:

- breadcrumb Home link;
- category current-page semantics;
- child category links only from authoritative data;
- product cards link to canonical PDPs;
- no /products?category= replacement for canonical category navigation.


81. PDP LINK TEST

Verify:

Home → /
Furniture → /products
Category → /categories/{slug}

when authoritative category context exists.

Current product should not create an unnecessary self-link.


82. SEARCH LINK TEST

Verify:

search results → clean canonical PDP

Search pagination preserves:

search=<term>

PDP links do NOT preserve the search query.


83. FILTER LINK TEST

Verify:

filtered /products result cards → clean canonical PDP

Pagination preserves approved filter/sort state.

Product links do not carry filter state.


84. PAGINATION TEST

Verify:

- previous/next are semantic links;
- page 1 URL is clean;
- active state preserved;
- invalid unrelated parameters not introduced.


85. NAVIGATION TEST

Audit desktop and mobile navigation.

Verify:

- destinations agree;
- implemented links are active;
- stale/dead destinations are not active;
- backend slug policy is respected;
- no href="#" exists.


86. FOOTER TEST

Verify footer has:

- no broken internal link;
- no placeholder href;
- no link to unimplemented route presented as active;
- no SEO link farm.


87. BROKEN LINK CONTRACT TEST

Create a finite set of known rendered internal destinations from fixture/test data and assert route shape validity.

Do not attempt a network crawler over arbitrary application state.

The test should be deterministic.


88. STATIC SOURCE AUDIT

Search frontend/web for suspicious internal navigation patterns:

href="#"
router.push(
window.location
location.href
onClick navigation
hard-coded /products?category=
machine-ID product URLs
local slugify utilities

Do not mechanically delete legitimate occurrences.

Classify each finding.


89. ROUTE GRAPH TEST

Using controlled fixture data, verify canonical resources are reachable:
```
/ → category
/ → product
/ → /products
/category → product
/products → product
/search → product
/product → category
/product → /products
```
Where a relationship is unavailable because authoritative data lacks it, report NOT AVAILABLE rather than fabricate it.


90. CRAWL DEPTH

Do not attempt to guarantee an arbitrary "all pages within exactly 3 clicks" metric.

Instead verify no canonical product/category resource rendered by the current catalog architecture is orphaned from all normal discovery paths.

Sitemap remains a supplementary discovery mechanism, not a substitute for navigation.


91. ORPHAN PRODUCT ANALYSIS

Given controlled catalog data:

a product returned by CAT-001 should be reachable from:

/products

through ProductCard.

If it is also category-associated, category discovery may provide another path.

Do not require homepage featuring for every product.


92. ORPHAN CATEGORY ANALYSIS

A category intended for public storefront discovery should be reachable through at least one approved category discovery/navigation surface where the public API exposes it.

If CAT-003 exposes categories that current global navigation cannot safely expose because of the previously documented root-layout architecture limitation:

report it explicitly.

Do not hide the limitation.


93. LINK COUNTS

You may report counts for verification, but no minimum link-count target exists.

Do not fail because a page has "too few SEO links."


94. RUNTIME API VERIFICATION

If local Laravel remains empty:

report real catalog link verification as:

NOT AVAILABLE — EMPTY LOCAL CATALOG

Do not create production/test DB data just to populate links.

Use explicit fixture mode for deterministic route/link verification.


95. FIXTURE RUNTIME

Use the existing fixture architecture to verify:
```
homepage → category
homepage → PDP
/products → PDP
category → PDP
search → PDP
PDP → category
PDP → /products
```
where fixture data supports those relationships.


96. BROWSER VERIFICATION

Use installed Chrome.

At minimum verify:

320px
390px
959px
960px
961px
1440px

Check:

- desktop navigation;
- sticky behavior;
- mobile drawer;
- breadcrumb links;
- product cards;
- pagination;
- keyboard traversal;
- focus visibility;
- no horizontal overflow;
- no console errors;
- no unexpected network failures.


97. LINK STATUS

For deterministic fixture/API routes used during runtime verification, follow representative links and verify expected HTTP status.

Expected canonical destinations:

implemented valid route → 200
missing product → 404
missing category → 404

Do not require every discovery query URL to be indexable.


98. NO VISUAL REDESIGN

Expected visual change:

NONE or minimal link-affordance adjustment required for accessibility.

Do not redesign:
```
homepage
category page
PDP
search
filters
footer
header
ProductCard
ProductGrid
```
Do not reintroduce removed category imagery.


99. REGRESSION — CRAWL

Run:

npm run test:crawl

Verify Phase 14.9 remains unchanged.

Sitemap canonical URLs must still match internal-link targets.


100. REGRESSION — SEO

Run:
```
npm run test:seo
npm run test:structured-data
```
Verify:

canonical policy unchanged;
search noindex unchanged;
filtered collection noindex unchanged;
BreadcrumbList remains truthful;
offers remain absent.


101. REGRESSION — CATALOG

Run actual available scripts:
```
npm run test:filters
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

102. STATIC VALIDATION

Must pass:

npm run typecheck
npm run lint
npm run build
git diff --check


103. SONAR REGRESSION

Do not introduce:
```
node:vm
eval
new Function
runtime source compilation
unsafe dynamic execution.
```

104. BACKEND BOUNDARY

Expected:

backend changed = NO

Do not add:

related-products endpoint
SEO endpoint
navigation endpoint
breadcrumb endpoint

unless a genuine frozen-contract blocker is discovered.

If such a blocker exists:

STOP.


105. FLUTTER BOUNDARY

Expected:

Flutter changed = NO


106. DESIGN-SYSTEM BOUNDARY

Expected:

design-system authority changed = NO

Consume existing tokens/components.


107. PHASE 14.11 BOUNDARY

Do NOT perform:

image compression
next/image tuning beyond fixing a direct link regression
bundle analysis
font optimization
Core Web Vitals optimization
prefetch tuning
cache redesign
ISR migration
R2 optimization
responsive image overhaul

Those belong to Phase 14.11.


108. DOCUMENTATION

Update:

phases/group-N-phases.md

Record:

- audited internal-link graph;
- canonical link policy;
- ProductCard link authority;
- breadcrumb hierarchy;
- category-navigation authority;
- search/filter discovery behavior;
- pagination state preservation;
- dead/reserved-route findings;
- fixture/API runtime evidence;
- any unavoidable navigation limitation.

Update:

frontend/web/ROUTING.md

only if current implemented navigation rules need durable clarification.

Do not rewrite canonical policy already documented.


109. ADR

Expected:

ADR = NONE

This phase should apply existing routing/catalog/SEO authority.

If resolving production category navigation requires a new global caching/failure architecture decision:

STOP.

Do not create the ADR silently.


110. COMPLETION REPORT

Return:
```
PHASE 14.10 — INTERNAL LINKING

Status:
PASS / BLOCKED


AUDIT

Internal navigation surfaces audited:
<n>

Broken links before:
<n>

Broken links after:
<n>

Placeholder hrefs:
NONE / <details>

Programmatic ordinary navigation:
NONE / <details>

Dead reserved routes activated:
NONE / FAIL


CANONICAL GRAPH

/ → /products:
PASS / NOT APPLICABLE / FAIL

/ → category:
PASS / NOT AVAILABLE / FAIL

/ → product:
PASS / NOT AVAILABLE / FAIL

/products → product:
PASS / NOT AVAILABLE / FAIL

category → product:
PASS / NOT AVAILABLE / FAIL

search → product:
PASS / NOT AVAILABLE / FAIL

product → /products:
PASS / FAIL

product → category:
PASS / NOT AVAILABLE / FAIL


PRODUCT LINKS

Component:
ProductCard / <actual>

Slug source:
BACKEND / FAIL

Machine IDs:
NONE / FAIL

Local slugification:
NONE / FAIL

Search params propagated to PDP:
NO / FAIL

Filter params propagated to PDP:
NO / FAIL

Accessible product-name link:
PASS / FAIL


CATEGORY LINKS

Canonical shape:
/categories/{backend slug}

products?category used as canonical category navigation:
NO / FAIL

Child-category authority:
<source / NOT AVAILABLE>

Hard-coded production taxonomy added:
NO / FAIL


BREADCRUMBS

Category:
<actual hierarchy>

PDP:
<actual hierarchy>

Semantic breadcrumb nav:
PASS / FAIL

aria-current:
PASS / FAIL

Visible hierarchy matches JSON-LD:
PASS / FAIL


GLOBAL NAVIGATION

Desktop source:
<actual>

Mobile source:
<actual>

Shared authority:
YES / FAIL

Production fixture taxonomy:
<YES/NO>

If YES, disposition:
<reason/remediation/limitation>

Sticky category navigation preserved:
YES / N/A / FAIL


FOOTER

Implemented routes only:
PASS / FAIL

Placeholder links:
NONE / FAIL

SEO link block:
NONE / FAIL


PAGINATION

Semantic links:
PASS / FAIL

Clean page one:
PASS / FAIL

Search state preserved:
PASS / FAIL

Filter state preserved:
PASS / FAIL

Unrelated state introduced:
NONE / FAIL


REQUEST-FIRST

Made-to-order PDP links:
PASS / FAIL

Fake request CTA:
NONE / FAIL

Cart:
NONE / unchanged

Checkout:
NONE / unchanged

Payment:
NONE / unchanged

Product offers JSON-LD:
NONE / unchanged


SEO CONSISTENCY

Phase 14.7 canonicals:
PASS / FAIL

Phase 14.7 noindex:
PASS / FAIL

Phase 14.8 breadcrumbs:
PASS / FAIL

Phase 14.8 offers omission:
PASS / FAIL

Phase 14.9 sitemap:
PASS / FAIL

Phase 14.9 robots:
PASS / FAIL


ACCESSIBILITY

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Meaningful link text:
PASS / FAIL

Nested interactive controls:
NONE / FAIL

Breadcrumb semantics:
PASS / FAIL

Mobile drawer:
PASS / FAIL

200% reflow:
PASS / FAIL


RESPONSIVE

320:
PASS / FAIL

390:
PASS / FAIL

959:
PASS / FAIL

960:
PASS / FAIL

961:
PASS / FAIL

1440:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


RUNTIME

Browser:
<value>

API mode:
PASS / EMPTY CATALOG / API NOT AVAILABLE

Fixture mode:
PASS / FAIL

Representative valid links:
<results>

Missing product:
HTTP 404 / FAIL

Missing category:
HTTP 404 / FAIL

Console errors:
NONE / <details>


TESTS

test:links:
PASS / FAIL

test:crawl:
PASS / FAIL

test:seo:
PASS / FAIL

test:structured-data:
PASS / FAIL

test:filters:
PASS / FAIL

test:search:
PASS / FAIL

test:product-detail:
PASS / FAIL

test:products:
PASS / FAIL

test:category:
PASS / FAIL

test:homepage:
PASS / FAIL

test:api:
PASS / FAIL

test:theme:
PASS / FAIL

test:layout:
PASS / FAIL

test:responsive:
PASS / FAIL

test:states:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


DEPENDENCIES

Added:
NONE / FAIL


BOUNDARIES

Backend changed:
NO

Flutter changed:
NO

Design system changed:
NO

New client component:
NO

Related-products system:
NONE

New SEO link section:
NONE

Category-page image redesign:
NONE

Phase 14.11 work:
NONE


DOCUMENTATION

Group N:
UPDATED / FAIL

ROUTING.md:
UPDATED / UNCHANGED

ADR:
NONE / <id>


GIT

git-workflow-and-versioning read:
YES / NO

Operations:
<exact operations>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 14.10:
PASS / BLOCKED

Phase 14.11:
READY / BLOCKED
```

111. STOP CONDITION

Phase 14.10 may be declared PASS only when:

- the existing internal-link graph has been audited before adding links;
- all ordinary navigation uses semantic links;
- backend slugs remain the sole product/category URL authority;
- ProductCard remains the canonical reusable product-link surface;
- homepage category discovery reaches canonical category pages where authoritative data exists;
- homepage/catalog discovery reaches canonical PDPs;
- /products reaches products through ProductCard;
- category product discovery reaches canonical PDPs;
- search results reach canonical PDPs despite /search being noindex;
- filtered product results reach clean canonical PDPs;
- PDP links back to /products;
- PDP links to its authoritative category when category context exists;
- visible breadcrumbs are semantic and truthful;
- visible PDP breadcrumb hierarchy agrees with Phase 14.8 BreadcrumbList;
- no unnecessary current-page self-links are added;
- pagination remains semantic and preserves required discovery state;
- page-one URLs remain clean;
- no filter/search state leaks into canonical PDP URLs;
- canonical category navigation does not use /products?category= as a substitute for /categories/[slug];
- desktop/mobile navigation do not diverge in route semantics;
- sticky category navigation remains correct if already implemented;
- old fixture-backed global navigation authority is explicitly audited;
- no duplicate hard-coded production taxonomy is introduced;
- no dangerous root-layout/API availability architecture is silently added;
- footer contains no broken/placeholder/SEO-farm links;
- no reserved unimplemented route is activated;
- MADE_TO_ORDER continues to link to its canonical PDP;
- no fake request/cart/checkout/payment destination is introduced;
- no related-products algorithm is invented;
- no hidden links or keyword-stuffed SEO anchors are introduced;
- Phase 14.7 canonical/noindex behavior remains unchanged;
- Phase 14.8 structured data remains truthful;
- Phase 14.9 sitemap/robots remain unchanged and consistent with canonical links;
- missing product/category still return hard HTTP 404;
- empty API catalog produces no fabricated links;
- accessibility and responsive checks pass;
- no new dependency is added;
- no backend change is made;
- no Flutter change is made;
- no design-system authority changes;
- no Phase 14.11 performance work is started;
- focused internal-link tests pass;
- all relevant regression suites pass;
- typecheck passes;
- lint passes;
- production build passes;
- git diff --check passes;
- Git operations follow git-workflow-and-versioning.

Then report exactly:

Phase 14.10 — PASS
Phase 14.11 — READY

Do not start Phase 14.11 automatically.