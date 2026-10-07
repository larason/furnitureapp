# PHASE 14.9 — SITEMAP / ROBOTS

# ENTRY STATE
```
Phase 14.1 — Homepage — PASS
Phase 14.2 — Category Pages — PASS
Phase 14.3 — Product Listing — PASS
Phase 14.4 — Product Detail — PASS
Phase 14.5 — Search — PASS
Phase 14.6 — Filters & Sorting — PASS
Phase 14.7 — SEO Metadata — PASS
Phase 14.8 — Structured Data — PASS
Phase 14.9 — Sitemap / Robots — ACTIVE
```
Do not start Phase 14.10 automatically.


1. OBJECTIVE

Implement the public website's crawl-discovery layer using the installed Next.js App Router metadata-file conventions:
```
app/sitemap.ts
app/robots.ts
```
The implementation must:

- expose canonical public indexable URLs through /sitemap.xml;
- discover public products/categories from authoritative Laravel catalog APIs;
- avoid search/filter/facet URL explosion;
- expose an appropriate /robots.txt;
- advertise /sitemap.xml from robots.txt only when a valid public SITE_URL exists;
- reuse the Phase 14.7 site-origin/canonical architecture;
- preserve Phase 14.7 page-level robots/canonical decisions;
- preserve Phase 14.8 structured data unchanged.

No hard-coded production catalog snapshot.


2. READ BEFORE CODING

Read:

AGENTS.md
frontend/AGENTS.md

frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md

docs/api/
  api-contract.md
  api-conventions.md
  api-resources.md
  openapi.yaml

docs/decisions.md
docs/domain/business-rules.md

phases/group-N-phases.md

Then inspect:

frontend/web/app/
frontend/web/lib/api/
frontend/web/lib/products/
frontend/web/lib/category/
frontend/web/lib/seo/

especially:

lib/seo/site.ts
Phase 14.7 metadata implementation
Phase 14.8 structured-data implementation
catalog pagination helpers
CAT-001 integration
CAT-003 integration
fixture architecture
proxy.ts
package.json

Repository reality wins over this prompt where filenames differ.


3. CHECK CURRENT FRAMEWORK / CRAWLER GUIDANCE

Before coding, verify the installed Next.js version's current App Router conventions for:

MetadataRoute.Sitemap
MetadataRoute.Robots
app/sitemap.ts
app/robots.ts

Also check current official Google guidance for:

XML sitemaps
canonical URLs in sitemaps
lastmod
robots.txt
faceted-navigation crawling

Do not use an outdated Pages Router tutorial.


4. GIT POLICY

Before ANY Git command:

locate/read/follow:
git-workflow-and-versioning

Preserve unrelated owner changes.

Never commit:

.env
.env.local
secrets

Use an atomic Phase 14.9 commit.


5. USE NEXT.JS METADATA FILE CONVENTIONS

Prefer:

app/sitemap.ts
app/robots.ts

using:

MetadataRoute.Sitemap
MetadataRoute.Robots

Do not manually build XML strings unless the installed framework has a demonstrated limitation.

Do not create:

public/sitemap.xml
public/robots.txt

as static snapshots when catalog URLs are dynamic.


6. PROXY EXCLUSION — IMPORTANT

Next.js metadata files are special route handlers.

Inspect the existing proxy.ts matcher.

Ensure:

/sitemap.xml
/robots.txt

are NOT accidentally subjected to product/category hard-404 preflight.

Do not broaden proxy matching.

If they are already excluded, leave proxy.ts unchanged.


7. SITE ORIGIN AUTHORITY

Reuse Phase 14.7:

SITE_URL
lib/seo/site.ts
canonical URL helpers

Do NOT create:

SITEMAP_BASE_URL
ROBOTS_BASE_URL
PUBLIC_URL
NEXT_PUBLIC_SITE_URL

Do not use:

API_BASE_URL
Host
X-Forwarded-Host
R2 origin
fixture origin

as website identity.


8. SITE_URL MISSING — DECISION ALREADY MADE

Do NOT stop for a decision here.

If SITE_URL is missing:

- do not guess a production domain;
- do not use localhost;
- do not fail npm run build solely because deployment SITE_URL is absent;
- sitemap generation must not emit fabricated absolute URLs;
- robots.txt must not emit a fabricated Sitemap directive.

Conservative expected behavior:

sitemap:
no canonical URL entries requiring an invented origin

robots:
valid crawler policy
Sitemap directive omitted

Use the existing Phase 14.7 configuration/warning architecture where possible.

Report:

Production SITE_URL:
REQUIRED / NOT CONFIGURED

This is a deployment readiness caveat, not permission to invent an origin.


9. MALFORMED SITE_URL

Phase 14.7 already treats malformed SITE_URL as configuration error.

Preserve that behavior.

Do not silently repair malformed production origins.

Do not turn:

foo
localhost-ish garbage
API URL

into a sitemap origin.


10. SITEMAP URL POLICY

Include only canonical URLs that the website wants search engines to discover/index.

Primary V1 sitemap URL classes:

/
 /products
 /products/{backend-product-slug}
 /categories/{backend-category-slug}

Only include routes that are actually implemented and indexable.


11. DO NOT INCLUDE RESERVED ROUTES

Do not include routes merely because ROUTING.md reserves them.

For example, do not include an unimplemented:

/account
/contact
/furniture-requests

unless inspection proves that route is implemented, public, canonical and indexable.

Sitemap describes deployed content, not future roadmap.


12. SEARCH EXCLUSION

Do NOT include:

/search
/search?search=...
/search?...filters...

Phase 14.7 deliberately marks search:

noindex, follow

A noindex search route does not belong in the sitemap.


13. FILTER/FACET EXCLUSION

Do NOT include URLs containing:

category=
product_type=
availability=
min_price=
max_price=
sort=
sort_direction=

No faceted product URL belongs in the sitemap.

Examples forbidden:

/products?category=living-room
/products?product_type=MADE_TO_ORDER
/products?availability=available
/products?sort=price&sort_direction=asc
/products?min_price=...
/products?...multiple facets...

Phase 14.7 already deliberately prevents these from becoming indexable landing-page identities.


14. PAGINATION EXCLUSION FROM SITEMAP

Do NOT enumerate:

/products?page=2
/products?page=3
/categories/foo?page=2

inside the sitemap.

Although Phase 14.7 may self-canonicalize meaningful plain pagination, sitemap discovery should focus on:

- collection root;
- category canonical resources;
- product canonical resources.

Individual products/categories are explicitly discoverable through the sitemap, so listing pagination does not need sitemap enumeration.

Do not change Phase 14.7 pagination canonical behavior.


15. PAGE=1

Never emit:

?page=1

Sitemap URLs are clean canonical paths.


16. NO QUERY STRINGS

Expected sitemap URLs:

query string count = 0

unless an already-established canonical public route genuinely requires one.

For current Group N public catalog:

NONE should.


17. HOMEPAGE

Include:

SITE_URL/

exactly once.


18. PRODUCT COLLECTION

Include:

SITE_URL/products

exactly once.

Do not include `/products/` with trailing slash.


19. PRODUCT DETAILS

Discover products from authoritative CAT-001.

For every public catalog product returned by CAT-001, include:

SITE_URL/products/{product.slug}

Use backend-returned slug.

Do NOT use:

product.id
product.name slugification
local fixture slug
array index


20. PRODUCT COLLECTION PAGINATION

CAT-001 is paginated.

The sitemap must retrieve ALL public product summaries needed to enumerate canonical product URLs.

Do not fetch only page 1 and silently omit the rest.


21. SAFE API PAGINATION

Respect the frozen pagination contract:

page
per_page
meta.pagination

Maximum per_page is 100.

A reasonable sitemap crawl strategy is:

per_page=100
page=1..last_page

provided the actual frozen contract confirms this.

Do not request:

per_page=10000
unbounded response
undocumented "all=true"


22. PAGINATION TERMINATION

Use authoritative pagination metadata.

Do not loop until an empty page if reliable:

last_page
has_next

already exists.

Protect against malformed/non-progressing pagination metadata.

Do not create an infinite server-side loop.


23. PRODUCT DEDUPLICATION

Sitemap must not contain duplicate product canonical URLs.

If duplicate slugs unexpectedly appear across API pages:

- deduplicate defensively by canonical URL;
- do not silently invent alternate URLs;
- report the unexpected contract condition.

Backend slug uniqueness remains authoritative.


24. CATEGORY DETAILS

Discover public categories from authoritative CAT-003.

Include:

SITE_URL/categories/{category.slug}

using backend-returned slugs.

No local slugification.


25. CATEGORY PAGINATION

CAT-003 is paginated.

Do not assume page 1 contains every public category.

Traverse its pagination safely according to the frozen API contract.


26. CATEGORY SCOPE — IMPORTANT

Inspect CAT-003's actual V1 semantics.

If CAT-003 exposes only active top-level storefront categories, sitemap generation must not fabricate nested category URLs from seed knowledge.

Only include category resources discoverable through authoritative public API data.

Do not query the database.

Do not import Laravel seeders.

Do not hard-code the known furniture taxonomy.


27. NESTED CATEGORY DISCOVERY

If the public API does not expose a complete recursive category collection, do not invent a crawler solely from frontend fixture knowledge.

Report:

Nested public category discovery:
SUPPORTED / NOT AVAILABLE THROUGH CAT-003

If existing authoritative public category data already exposes nested children sufficient for traversal, use it.

Otherwise sitemap completeness is bounded by the frozen public API.

Do not change Laravel in Phase 14.9 merely to satisfy sitemap completeness.


28. ACTIVE/PUBLISHED RESOURCES ONLY

Public catalog endpoints already mask unpublished/hidden/deactivated resources.

Sitemap must consume those public endpoints rather than operational/admin endpoints.

Do not expose draft resources.


29. NO AUTHENTICATION

Sitemap generation must use public catalog access only.

Do not:

attach Clerk token
use staff/admin endpoint
use customer session
read cookies

Public catalog is anonymous.


30. GENERIC API CLIENT

Reuse the canonical API transport and existing catalog data helpers where appropriate.

Do not build a second HTTP client inside sitemap.ts.

Do not put sitemap-specific concepts into lib/api/client.ts.


31. DATA HELPER REUSE

If existing CAT-001/CAT-003 helpers are tied to UI pagination, extract/reuse the smallest appropriate public-catalog retrieval responsibility.

Do not copy API response parsing into sitemap.ts if canonical typed parsing already exists.


32. NO CAT-002 N+1

Do NOT call:

CAT-002 once per product

to construct the sitemap.

CAT-001 summary data already contains canonical product slugs.

Expected:

CAT-001 paginated collection requests only.


33. NO CAT-004 N+1

Likewise do not fetch every category detail merely to obtain its slug.

Use CAT-003 collection data where sufficient.


34. SITEMAP LASTMOD — TRUTH ONLY

Only emit lastModified when an authoritative source represents the last significant modification of that public page.

Do not use:

new Date()
build time
request time
server startup time
Git commit time

as fake lastmod.


35. UPDATED_AT

If CAT-001/CAT-003 public summaries expose an authoritative content updated timestamp appropriate for the canonical page, it may be used.

If they do not:

omit lastModified.

Do not change the backend solely to populate sitemap lastmod.


36. CHANGEFREQUENCY

Do not invent changeFrequency values merely because Next.js supports them.

Google treats sitemap hints as hints.

Unless project authority has a meaningful policy:

omit changeFrequency.


37. PRIORITY

Do not assign arbitrary sitemap priority scores such as:

homepage = 1.0
products = 0.9
categories = 0.8

unless project authority explicitly establishes them.

Prefer omission.

Do not perform "SEO score" theater.


38. IMAGE SITEMAP

Do NOT add image sitemap entries in Phase 14.9 unless existing project roadmap explicitly requires them.

Product media already appears on canonical product pages.

Keep Phase 14.9 focused.

Do not call CAT-002 per product to collect galleries.


39. SITEMAP SIZE

Google's sitemap protocol limits a sitemap to 50,000 URLs or 50 MB uncompressed.

Assess expected catalog size.

For current V1, a single sitemap is likely sufficient.

Do not implement generateSitemaps/sharding preemptively unless actual catalog scale requires it.


40. FUTURE SCALE

If the current authoritative catalog could exceed 50,000 sitemap URLs:

STOP

Report the measured/contract-supported scale and implement an appropriate sitemap-index/sharding design only if genuinely required.

Do not prematurely add complexity.


41. DETERMINISTIC ORDER

Return sitemap entries in a deterministic order.

Recommended conceptual grouping:

/
 /products
categories sorted deterministically
products sorted deterministically

Do not rely on unstable object iteration.

The exact order has no SEO ranking meaning; determinism is for testability and operational stability.


42. SITEMAP API FAILURE

Do not silently replace Laravel catalog failure with fixture data.

Do not emit a misleading "complete" sitemap containing only static URLs if product/category discovery unexpectedly fails, unless a deliberate existing failure policy establishes that degradation.

Prefer surfacing the upstream failure according to the framework route-handler behavior.

A crawler receiving a temporary server error can retry.

Do not convert infrastructure failure into a permanently incomplete successful sitemap.


43. 404 RESOURCE RACE

If a product disappears between sitemap generation and later crawl, its PDP may legitimately return 404.

Do not preflight every sitemap URL through CAT-002.

Normal catalog churn is acceptable.


44. ROBOTS.TXT PURPOSE

robots.txt controls crawling.

It is NOT:

- an authorization mechanism;
- a privacy mechanism;
- a replacement for noindex;
- a replacement for authentication;
- a way to hide secrets.

Do not place sensitive URLs in public robots policy under the assumption that they become private.


45. ROBOTS DEFAULT

The public storefront should remain crawlable.

Use a general crawler policy based on:

User-agent: *
Allow: /

with deliberate exclusions below.


46. SEARCH CRAWLING

Disallow crawling of:

/search

because:

- it is internal search;
- Phase 14.7 already marks it noindex;
- arbitrary search terms create unbounded crawl space;
- there is no SEO value in crawling internal search combinations.

Do not include /search in sitemap.


47. FACETED NAVIGATION CRAWLING

Phase 14.6 creates URL-parameter facets.

Google's current crawling guidance explicitly warns that faceted navigation can create effectively infinite URL spaces and recommends preventing crawling when those URLs do not need indexing.

Therefore robots.txt should prevent crawler exploration of the owned non-indexable product facet parameters where safely expressible:

category
product_type
availability
min_price
max_price
sort
sort_direction

Use Robots Exclusion patterns compatible with the current Google interpretation and Next.js MetadataRoute.Robots output.


48. FACET PATTERN SAFETY

Do not write one broad rule such as:

Disallow: /*?*

because that would also suppress useful pagination or unrelated future query state.

Rules must target the known Phase 14.6 facet/sort parameter names specifically.


49. QUERY PARAMETER ORDER

Facet parameters can occur:

first
middle
last

Example:

/products?category=living-room
/products?page=2&category=living-room
/products?category=living-room&page=2

Ensure chosen robots patterns address parameter presence rather than only one exact ordering where feasible under robots syntax.

Test the generated text.


50. SEARCH PARAMETER

The canonical search UI uses:

/search?search=...

Since /search itself is disallowed, no separate `search=` query-pattern rule is required solely for that route.

Do not accidentally block a future unrelated route merely because it has a parameter named `search` unless the current routing policy requires it.


51. PAGINATION CRAWLING

Do NOT disallow:

?page=

globally.

Phase 14.7 deliberately treats meaningful plain collection pagination differently from faceted state.

Crawlers may need pagination to discover content through ordinary links.

Sitemap directly exposes products, but robots policy must not silently contradict the established pagination/indexability architecture.


52. PRODUCT/CATEGORY DETAIL CRAWLING

Do NOT disallow:

/products/
/categories/

These are primary indexable resource surfaces.


53. ROOT PRODUCT COLLECTION

Do NOT disallow:

/products

The canonical product collection is indexable.


54. ADMIN/API ROUTES

This is the public Next.js website host.

Do not invent disallow rules for Laravel API paths that do not exist on this host.

Likewise do not invent:

/admin
/internal
/private

rules unless those paths actually exist on this Next.js application and crawling policy requires them.

Robots should describe the actual host.


55. RESERVED AUTH ROUTES

Do not disallow hypothetical future:

/account
/orders
/checkout

unless those routes actually exist on this public host.

Phase 14.9 is not future-route policy design.


56. ROBOTS SITEMAP DIRECTIVE

When valid SITE_URL exists:

Sitemap: {SITE_URL}/sitemap.xml

Use the existing canonical URL/origin helper.

Do not hand-concatenate a second origin implementation.


57. ROBOTS WITHOUT SITE_URL

When SITE_URL is absent:

omit the Sitemap directive.

Do not emit:

Sitemap: /sitemap.xml
Sitemap: http://localhost:3000/sitemap.xml
Sitemap: http://127.0.0.1:...
Sitemap: {API_BASE_URL}/sitemap.xml

Robots rules themselves may still render validly.


58. HOST DIRECTIVE

Do not emit a robots Host directive unless there is a demonstrated requirement.

It is not needed merely because MetadataRoute.Robots supports it.


59. CRAWL DELAY

Do not emit:

Crawl-delay

without an operational requirement.

Do not invent crawler throttling policy.


60. BOT-SPECIFIC RULES

Do not create special Googlebot/Bingbot/AI crawler policies in this phase unless project authority explicitly requires them.

Use:

User-agent: *

for the general public crawler policy.


61. GOOGLE-EXTENDED

Do not make a policy decision about Google-Extended, AI training/use, or other AI crawlers in Phase 14.9 unless the project owner explicitly requests one.

That is a business/policy decision, not a technical default.


62. ROBOTS VS PAGE-LEVEL NOINDEX

Preserve Phase 14.7 page-level robots metadata.

Do not remove:

noindex, follow

from search/filtered pages just because robots.txt now reduces crawling.

They serve related but distinct purposes.

Do not modify Phase 14.7 metadata merely to "simplify" robots.txt.


63. CANONICAL SIGNAL CONSISTENCY

Sitemap should reinforce canonical identity established in Phase 14.7:

homepage:
/

product collection:
/products

product:
/products/{slug}

category:
/categories/{slug}

Do not list noncanonical aliases.

Google treats sitemap inclusion as a canonicalization signal, so sitemap and rel=canonical must agree.


64. TRAILING SLASH

Preserve Phase 14.7 slashless policy.

Expected:

https://site.example/products

not:

https://site.example/products/


65. HTTP/HTTPS

SITE_URL determines the canonical scheme.

Do not rewrite HTTPS to HTTP.

Do not infer scheme from API_BASE_URL.


66. WWW/NON-WWW

SITE_URL determines the canonical hostname.

Do not produce both:

https://example.com/...
https://www.example.com/...

unless project authority intentionally uses both, which current architecture does not.


67. FIXTURE MODE

Fixture mode may be used for deterministic local sitemap tests.

But production/default API mode must remain authoritative.

No silent API failure → fixture sitemap fallback.


68. FIXTURE SITEMAP

If explicit fixture mode is supported for sitemap visual/runtime verification:

- use only explicit fixture data;
- make the mode unmistakable;
- never claim it proves production catalog completeness.

Do not add a second fixture dataset just for sitemap if existing catalog fixtures can be reused safely.


69. PRODUCTION DATABASE EMPTY

If local Laravel API returns zero products/categories:

a valid API-backed sitemap may contain only:

/
 /products

provided SITE_URL is explicitly configured for the test.

Do not create fake database catalog records merely to make sitemap look populated.


70. DEPLOYMENT SITE_URL TEST

For runtime verification, it is acceptable to launch the production Next.js server with an explicit temporary test origin such as:

SITE_URL=https://example.invalid

or another clearly non-production test origin supported by the existing validation rules.

Do not commit it.

Do not use it as production configuration.

If `.invalid` conflicts with existing URL validation, use an explicit local test configuration only in tests and clearly report it.

Never infer the production domain.


71. NO CLIENT CODE

Expected new "use client":

NONE

Sitemap and robots are server metadata routes.

No React component should be required.


72. NO VISUAL CHANGES

Expected visual UI changes:

NONE

Do not touch:

MUI theme
tokens
ProductCard
ProductGrid
category header
sticky navigation
filters
search UI
PDP gallery


73. CATEGORY PAGE IMAGE REFINEMENT

If the separately requested category-page image refinement has not yet been implemented, do NOT bundle it into Phase 14.9.

Keep it as a separate visual commit/task.

Sitemap/robots should remain SEO infrastructure only.


74. NO NEW DEPENDENCY

Expected:

dependencies added = NONE

Do not install:

sitemap
next-sitemap
robots-txt
SEO packages
XML libraries

Next.js already supplies the required metadata routes.


75. TEST INFRASTRUCTURE

Use existing:

node:test + tsx

Do not recreate:

load-ts.mjs
node:vm
eval
new Function
SourceTextModule
custom TS loaders


76. FOCUSED TEST SUITE

Add one focused script following repository conventions:

npm run test:crawl

or:

npm run test:sitemap

Choose ONE name that accurately covers sitemap + robots.

Prefer:

test:crawl

if it tests both resources.

Do not create overlapping test suites unnecessarily.


77. SITEMAP STATIC URL TESTS

With valid test SITE_URL, assert exactly one:

/
 /products

base entry.

Assert:

/search absent.


78. PRODUCT SITEMAP TESTS

Use paginated fake CAT-001 transport/data.

Test:

- one page;
- multiple pages;
- final partial page;
- zero products;
- backend slugs;
- duplicate defensive handling;
- deterministic output;
- no product IDs in URLs;
- no local slugification.


79. CATEGORY SITEMAP TESTS

Use paginated fake CAT-003 data.

Test:

- one page;
- multiple pages;
- zero categories;
- backend slugs;
- deterministic output;
- no hard-coded taxonomy.


80. PAGINATION REQUEST TEST

Verify collection traversal respects:

per_page <= 100

and advances using authoritative pagination metadata.

No unbounded request.

No CAT-002/CAT-004 N+1.


81. QUERY-FREE SITEMAP TEST

Assert every sitemap URL has:

search === ""

for current V1 sitemap policy.

No:

page
category
product_type
availability
min_price
max_price
sort
sort_direction
search


82. CANONICAL CONSISTENCY TEST

Assert sitemap URLs correspond to Phase 14.7 canonical route shapes.

No:

machine IDs
trailing slash variants
API routes
fixture-only aliases


83. SITE_URL MISSING TEST

Unset SITE_URL.

Verify:

- no localhost sitemap URLs;
- no API origin;
- no fabricated public origin;
- build/test remains viable;
- robots omits Sitemap directive.

Test actual intended empty/no-origin sitemap behavior.


84. MALFORMED SITE_URL TEST

Provide malformed SITE_URL.

Verify existing Phase 14.7 configuration error behavior remains intact.

Do not silently omit a malformed configured value as though it were simply missing.


85. LASTMOD TEST

If authoritative lastmod is unavailable:

assert sitemap entries omit it.

Do not assert current time.

If authoritative timestamp exists:

assert correct mapping.

No `new Date()` as fabricated content freshness.


86. ROBOTS TEST

Verify generated robots policy contains:

User-agent: *
Allow: /

and the deliberate search/facet crawl exclusions.

Verify it does NOT contain invented:

Crawl-delay
Host
bot-specific policies
private-route guesses


87. ROBOTS SITEMAP TEST

With SITE_URL:

Sitemap: {SITE_URL}/sitemap.xml

Without SITE_URL:

no Sitemap directive.


88. ROBOTS FACET TEST

Verify each owned facet/sort parameter is covered as intended:

category
product_type
availability
min_price
max_price
sort
sort_direction

Verify:

?page=

is NOT globally disallowed.


89. ROBOTS SEARCH TEST

Verify:

/search

is disallowed from crawling.

Do not rely on robots.txt as the only noindex mechanism; Phase 14.7 remains unchanged.


90. PROXY TEST

Verify:

/sitemap.xml
/robots.txt

are not intercepted by category/product preflight.

Missing product/category hard-404 behavior must remain intact.


91. RUNTIME SITEMAP VERIFICATION

Run production build/server.

With explicit valid test SITE_URL, request:

GET /sitemap.xml

Verify:

HTTP 200
Content-Type appropriate XML
valid XML
absolute URLs
no localhost
no API origin
no search URL
no filter URL
no duplicate loc entries


92. RUNTIME ROBOTS VERIFICATION

Request:

GET /robots.txt

Verify:

HTTP 200
text/plain-compatible response
correct user-agent
correct allow/disallow rules
correct Sitemap directive when SITE_URL exists
no fabricated host


93. API MODE RUNTIME

When local Laravel is available, run sitemap in API-backed mode.

If catalog remains empty, report:

Products discovered: 0
Categories discovered: 0

Do not call that a failure if Laravel legitimately returns an empty public catalog.


94. MULTI-PAGE PAGINATION EVIDENCE

If the local API has too little data to exercise multiple CAT-001/CAT-003 pages:

use typed transport/unit fixtures to prove multi-page traversal.

Do not create production DB records solely for sitemap testing.


95. XML PARSING

Parse generated sitemap XML in test/runtime verification where practical.

Do not validate merely by searching for `<url>` strings.

No malformed XML.


96. URL LIMIT

Count emitted URLs.

Report:

Total sitemap URLs:
<n>

If >= 50,000:

BLOCK

and implement/plan proper sitemap splitting before PASS.


97. API FAILURE TEST

Simulate CAT-001/CAT-003 unexpected failure.

Verify:

- no fixture fallback;
- no fabricated successful complete sitemap;
- failure propagates according to chosen metadata-route architecture.

Do not mask 500 as empty catalog.


98. REGRESSION — SEO

Run:

npm run test:seo
npm run test:structured-data

Verify:

canonicals unchanged
robots meta unchanged
JSON-LD unchanged


99. REGRESSION — CATALOG

Run existing suites:

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

Use actual script names.


100. STATIC VALIDATION

Must pass:

npm run typecheck
npm run lint
npm run build
git diff --check


101. SONAR REGRESSION

Do not introduce:

node:vm
eval
new Function
runtime code injection
custom TS module loader

The previous Sonar remediation remains closed.


102. BACKEND BOUNDARY

Expected:

backend changed = NO

Do not add a special sitemap endpoint to Laravel.

Do not modify CAT-001/CAT-003 merely for sitemap convenience.


103. FLUTTER BOUNDARY

Expected:

Flutter changed = NO


104. DESIGN SYSTEM BOUNDARY

Expected:

design system changed = NO


105. PHASE 14.10 BOUNDARY

Do NOT perform comprehensive internal-linking changes.

Do not:

redesign navigation
add SEO link clouds
add related-category blocks
add footer keyword links
add breadcrumb hierarchy merely for crawling

Phase 14.10 owns internal linking.


106. PHASE 14.11 BOUNDARY

Do NOT begin:

image optimization
cache redesign
ISR migration
bundle optimization
performance tuning

Phase 14.11 owns comprehensive performance hardening.


107. SEARCH CONSOLE

Do not attempt to submit the sitemap to Google Search Console in this phase unless explicitly requested and an appropriate authenticated integration exists.

Implementation ends with making:

/sitemap.xml
/robots.txt

correctly available.

Document future deployment step:

configure SITE_URL
deploy
verify public URLs
submit/verify sitemap in Search Console

Do not claim submission occurred.


108. DOCUMENTATION

Update:

phases/group-N-phases.md

Record:

- sitemap inclusion policy;
- sitemap exclusion policy;
- CAT-001/CAT-003 discovery;
- pagination strategy;
- lastmod decision;
- robots policy;
- faceted-navigation crawl policy;
- SITE_URL missing behavior;
- runtime evidence.

Update ROUTING.md only if sitemap/robots route conventions or crawler policy are durable routing documentation.

Do not rewrite Phase 14.7 canonical rules.


109. ADR

Expected:

ADR = NONE

The architecture already has:

SITE_URL authority
canonical route authority
public catalog authority

Next.js metadata-file conventions are implementation.

If a genuinely new cross-system policy is required:

STOP

Do not invent one silently.


110. COMPLETION REPORT

Return:

PHASE 14.9 — SITEMAP / ROBOTS

Status:
PASS / BLOCKED


FRAMEWORK

Next.js version:
<value>

Sitemap convention:
app/sitemap.ts / <actual>

Robots convention:
app/robots.ts / <actual>

New dependency:
NONE / FAIL


SITE ORIGIN

SITE_URL reused:
YES / FAIL

Production SITE_URL:
<configured / REQUIRED NOT CONFIGURED>

API_BASE_URL used:
NO / FAIL

Host header trusted:
NO / FAIL

Localhost production fallback:
NONE / FAIL

Missing SITE_URL behavior:
<summary>


SITEMAP

/sitemap.xml:
HTTP <status>

Absolute URLs:
PASS / FAIL

Canonical URLs only:
PASS / FAIL

Trailing slash variants:
NONE / FAIL

Query strings:
NONE / FAIL

Duplicate URLs:
NONE / FAIL

Total URLs:
<n>


STATIC ENTRIES

Homepage:
INCLUDED / FAIL

/products:
INCLUDED / FAIL

/search:
EXCLUDED / FAIL

Reserved unimplemented routes:
NONE / FAIL


PRODUCT DISCOVERY

Source:
CAT-001 / FAIL

Pagination:
<strategy>

All pages traversed:
PASS / FAIL

per_page:
<value>

CAT-002 N+1:
NONE / FAIL

Product slug source:
BACKEND / FAIL

Machine IDs in URLs:
NONE / FAIL

Products discovered:
<n>


CATEGORY DISCOVERY

Source:
CAT-003 / FAIL

Pagination:
<strategy>

All pages traversed:
PASS / FAIL

Category slug source:
BACKEND / FAIL

Hard-coded taxonomy:
NONE / FAIL

CAT-004 N+1:
NONE / FAIL

Categories discovered:
<n>

Nested public category discovery:
SUPPORTED / NOT AVAILABLE THROUGH CAT-003


EXCLUSIONS

/search:
EXCLUDED / FAIL

Filter URLs:
EXCLUDED / FAIL

Sort URLs:
EXCLUDED / FAIL

Price URLs:
EXCLUDED / FAIL

Pagination URLs:
EXCLUDED FROM SITEMAP / FAIL

page=1:
EXCLUDED / FAIL

Fixture-only URLs:
NONE / FAIL


LASTMOD

Authoritative source:
<field / NOT AVAILABLE>

Fabricated current timestamps:
NONE / FAIL

changeFrequency:
<OMITTED / justified>

priority:
<OMITTED / justified>


ROBOTS

/robots.txt:
HTTP <status>

User-agent:
*

Public crawling:
ALLOWED / FAIL

/search crawling:
DISALLOWED / FAIL

Faceted navigation:
<rules>

Pagination globally blocked:
NO / FAIL

Products blocked:
NO / FAIL

Categories blocked:
NO / FAIL

Crawl-delay:
NONE / FAIL

Host directive:
NONE / FAIL

Bot-specific policy:
NONE / FAIL


SITEMAP DIRECTIVE

With SITE_URL:
<value>

Without SITE_URL:
OMITTED / FAIL


PROXY

/sitemap.xml preflight:
NONE / FAIL

/robots.txt preflight:
NONE / FAIL

Missing PDP:
HTTP 404 / FAIL

Missing category:
HTTP 404 / FAIL


FAILURE BEHAVIOR

CAT-001 failure:
<behavior>

CAT-003 failure:
<behavior>

Fixture fallback:
NONE / FAIL

Incomplete successful sitemap on unexpected API failure:
NO / FAIL


SEO REGRESSION

Phase 14.7 canonicals:
PASS / FAIL

Search noindex:
PASS / FAIL

Filtered collection noindex:
PASS / FAIL

Pagination canonical:
PASS / FAIL

Phase 14.8 JSON-LD:
PASS / FAIL


TEST INFRASTRUCTURE

Runner:
node:test + tsx / <actual>

load-ts.mjs:
ABSENT / FAIL

node:vm:
NONE / FAIL

eval/new Function:
NONE / FAIL


VALIDATION

test:crawl/test:sitemap:
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


RUNTIME

Production sitemap XML:
PASS / FAIL

XML parsing:
PASS / FAIL

Production robots.txt:
PASS / FAIL

API-backed runtime:
PASS / FAIL / API NOT AVAILABLE

Fixture runtime:
PASS / FAIL / NOT REQUIRED

No localhost canonical URLs:
PASS / FAIL

No API-origin sitemap URLs:
PASS / FAIL


BOUNDARIES

Backend changed:
NO

Flutter changed:
NO

Design system changed:
NO

Visible UI changed:
NO

Category-page visual refinement bundled:
NO

Phase 14.10 work:
NONE

Phase 14.11 work:
NONE

Search Console submission:
NOT PERFORMED


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
<list>

Commit:
<hash/message>

Push:
<result / NONE>


DEPLOYMENT FOLLOW-UP

SITE_URL configuration required:
YES / NO

Public sitemap verification required:
YES

Search Console submission/verification:
PENDING


RESULT

Phase 14.9:
PASS / BLOCKED

Phase 14.10:
READY / BLOCKED


111. STOP CONDITION

Phase 14.9 may be declared PASS only when:

- app/sitemap.ts uses the installed Next.js metadata convention;
- app/robots.ts uses the installed Next.js metadata convention;
- SITE_URL from Phase 14.7 is the only public-site origin authority;
- no production domain is guessed;
- missing SITE_URL does not cause localhost/API-origin leakage;
- missing SITE_URL does not unnecessarily break the production build;
- robots omits the sitemap directive when no valid SITE_URL exists;
- homepage is included;
- /products is included;
- every sitemap product URL comes from authoritative CAT-001 backend slugs;
- every sitemap category URL comes from authoritative CAT-003 backend slugs;
- CAT-001 pagination is fully traversed;
- CAT-003 pagination is fully traversed;
- per_page stays within the frozen <=100 contract;
- no CAT-002 N+1 exists;
- no CAT-004 N+1 exists;
- no hard-coded production taxonomy is used;
- /search is excluded;
- all Phase 14.6 filter/sort/price facet URLs are excluded;
- pagination URLs are not unnecessarily enumerated in sitemap;
- no query strings appear in current V1 sitemap URLs;
- no machine IDs are used as canonical paths;
- no duplicate URLs exist;
- sitemap entries are deterministic;
- lastmod is emitted only from authoritative content timestamps, otherwise omitted;
- current time/build time is not fabricated as lastmod;
- priority/changeFrequency are omitted unless genuinely justified;
- robots allows public canonical catalog crawling;
- robots disallows /search crawling;
- robots deliberately controls known non-indexable faceted navigation without globally blocking useful query strings;
- ?page= is not globally blocked;
- products/categories remain crawlable;
- no invented private/admin/API route policy is added;
- no bot-specific/AI-crawler business policy is invented;
- sitemap/robots bypass resource proxy preflight;
- unexpected catalog API failure is not masked by fixture/incomplete-success fallback;
- sitemap stays below protocol limits or is properly split;
- production-shaped /sitemap.xml returns valid XML;
- production-shaped /robots.txt returns valid text;
- sitemap and Phase 14.7 canonical policy agree;
- Phase 14.7 page-level robots behavior remains intact;
- Phase 14.8 JSON-LD remains intact;
- no client component is added;
- no visual UI changes are bundled;
- no category-page redesign is bundled;
- no dependency is added;
- no backend change is made;
- no Flutter change is made;
- no Phase 14.10 work is started;
- no Phase 14.11 work is started;
- focused crawl tests pass;
- all relevant regressions pass;
- typecheck passes;
- lint passes;
- production build passes;
- git diff --check passes;
- Git operations follow git-workflow-and-versioning.

Then report exactly:

Phase 14.9 — PASS
Phase 14.10 — READY

Do not start Phase 14.10 automatically.