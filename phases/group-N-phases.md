# PHASE 14.11 — IMAGE / PERFORMANCE OPTIMIZATION

ENTRY STATE

Phase 14.1  — Homepage                    PASS
Phase 14.2  — Category Pages              PASS
Phase 14.3  — Product Listing             PASS
Phase 14.4  — Product Detail              PASS
Phase 14.5  — Search                      PASS
Phase 14.6  — Filters / Sorting           PASS
Phase 14.7  — SEO Metadata                PASS
Phase 14.8  — Structured Data             PASS
Phase 14.9  — Sitemap / Robots            PASS
Phase 14.10 — Internal Linking            PASS
Phase 14.11 — Image / Performance         ACTIVE

This is the FINAL phase of Group N.

Do not start Group O automatically.


1. OBJECTIVE

Measure and optimize the performance of the completed public catalog without changing its product behavior, SEO contracts, request-first release model, visual identity, API contract, or routing architecture.

Primary concerns:

- responsive image delivery;
- correct Next.js Image usage;
- image loading priority;
- image layout stability;
- image payload efficiency;
- remote catalog/R2 media compatibility;
- local fixture asset efficiency;
- font loading;
- unnecessary JavaScript;
- client-component boundaries;
- server-rendering preservation;
- catalog request efficiency;
- duplicate fetches;
- caching opportunities that are already safe under the frozen public catalog contract;
- route-level performance;
- Core Web Vitals-oriented improvements;
- production bundle awareness;
- layout stability;
- responsive rendering;
- accessibility preservation.

This phase is NOT:

"make Lighthouse 100 at any cost."

Optimize based on evidence.


2. GROUP N EXIT CRITERION

The root roadmap defines Group N's exit condition as:

"Public catalog pages are usable, crawlable and performant."

Phase 14.11 must therefore perform a final Group N performance verification across the public catalog.

Do not declare Group N closed merely because `next build` passes.


3. READ BEFORE CODING

Read and obey:

AGENTS.md
frontend/AGENTS.md

frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md

frontend/design-system/DESIGN.md
frontend/design-system/COMPONENTS.md
frontend/design-system/ACCESSIBILITY.md
frontend/design-system/USAGE.md
frontend/design-system/tokens.css

docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/decisions.md
docs/domain/business-rules.md

phases/group-N-phases.md

Then inspect the actual implementation from Phases 14.1–14.10.

Do not optimize against old phase assumptions.


4. GIT POLICY

Before ANY Git command:

locate/read/follow root skill:

git-workflow-and-versioning

Preserve unrelated owner changes.

Never commit:

.env
.env.local
secrets

Use an atomic Phase 14.11 commit.


5. MEASURE FIRST

Before modifying code, establish a baseline.

At minimum inspect/measure:

- production build output;
- route rendering mode;
- client JavaScript boundaries;
- image inventory;
- local fixture file sizes;
- generated image markup;
- responsive `srcset`;
- `sizes`;
- preload behavior;
- lazy/eager loading behavior;
- image geometry / CLS risk;
- font loading;
- duplicate API requests;
- catalog request cache policy;
- homepage initial payload;
- listing initial payload;
- PDP initial payload;
- search/filter initial payload;
- layout shifts;
- browser console/network failures.

Where tooling permits, collect browser performance evidence.

Do not start by changing arbitrary values.


6. BASELINE ROUTES

Benchmark representative public routes:

/
 /products
 /products/[slug]
 /categories/[slug]
 /search?search=<term>

Also inspect:

/products with representative filters/sort

where fixture/API data permits.


7. BASELINE VIEWPORTS

At minimum inspect:

390px mobile
1440px desktop

Also regression-check responsive behavior around:

959
960
961

because that breakpoint boundary has already been treated as important by the website architecture.


8. TOOLING

Use installed browser/tooling available in the environment.

Google Chrome is available.

Use production mode for meaningful measurements where practical:

npm run build
npm run start

Do not treat development-mode timing as production performance evidence.


9. LIGHTHOUSE

If Lighthouse is available through installed Chrome/tooling, run representative audits in production mode.

Prefer at minimum:

homepage
/products
representative PDP

Record:

Performance
Accessibility
Best Practices
SEO

and available Core Web Vitals/lab metrics such as:

LCP
CLS
TBT
FCP

Do not install a large new dependency merely to obtain Lighthouse.

If Lighthouse tooling is unavailable:

report NOT AVAILABLE

and use browser/network/build evidence instead.


10. PERFORMANCE TARGET POLICY

Do not invent a contractual score such as:

"Lighthouse must equal 100."

Use measurements to identify real regressions/bottlenecks.

However, any clearly poor result must be investigated and either:

- remediated;
- justified by external/environment limitations;
- or explicitly block PASS if it represents a real storefront performance defect.


11. IMAGE INVENTORY

Audit EVERY meaningful image surface.

At minimum:

- brand logo;
- homepage hero;
- homepage category/room imagery;
- homepage ProductCards;
- homepage editorial/MTO imagery;
- category page media if any remains;
- ProductCard images on /products;
- ProductCard images on category pages;
- ProductCard images on search;
- PDP lead image;
- PDP secondary/gallery images;
- missing-media states;
- fixture images;
- API/R2-backed images.

Produce an inventory before optimization.


12. NEXT/IMAGE

Public catalog photographic content should use:

next/image

unless inspection proves a specific image should not.

Do not replace Next Image with raw `<img>` merely to simplify code.

If any raw `<img>` remains:

classify it.

Fix only when appropriate.


13. REMOTE MEDIA

The frontend already has a catalog media origin boundary through:

CATALOG_MEDIA_BASE_URL

Preserve it.

Do not create a second media configuration system.


14. R2 ARCHITECTURE

IMPORTANT:

Cloudflare R2 product image management already exists from Group K Phase 11.5.

Do NOT:

- create another R2 bucket;
- add another upload workflow;
- add a second media service;
- add a Next.js upload route;
- proxy product images through Next route handlers;
- redesign backend product-media persistence.

Phase 14.11 optimizes consumption of existing media architecture.


15. REMOTE IMAGE ALLOW-LIST

Inspect actual:

next.config.ts

and current remote image configuration.

Ensure API/R2 catalog media can be rendered safely through Next Image using the narrowest reasonable configured origin/pattern.

Do not allow arbitrary remote hosts.

Do not use:

hostname: "*"

or equivalent unrestricted configuration.


16. CONFIG AUTHORITY

If CATALOG_MEDIA_BASE_URL is the existing authoritative catalog-media configuration, continue using it.

Do not introduce:

NEXT_PUBLIC_R2_URL
R2_IMAGE_URL
IMAGE_CDN_URL
MEDIA_HOST

as competing frontend authorities unless repository inspection proves one already exists.


17. IMAGE FORMATS

Do not manually redesign the application around WebP.

Use Next.js image optimization so supported modern formats can be negotiated appropriately.

Do not rewrite API URLs merely to append guessed:

.webp
.avif

suffixes.


18. SOURCE FORMAT

Existing source assets may be:

PNG
JPEG
WebP

Do not perform bulk format conversion without measured benefit.

For local photographic fixtures, oversized PNG photographs should be investigated because they may be wasteful.

The official brand logo is exempt from arbitrary conversion/reconstruction.


19. OFFICIAL LOGO

Preserve the official logo identity.

Do not:

- redraw;
- trace;
- recolor;
- replace;
- convert it into a text wordmark;
- regenerate it with AI.

Optimization must preserve visual fidelity.


20. PRODUCT MEDIA CONTRACT

The public backend image contract already exposes:

id
url
alt_text
sort_order
is_primary

Use that contract.

Do not require speculative frontend fields such as:

width_px
height_px
filesize
blurhash
dominant_color

because those are not part of the frozen image resource.


21. NO API EXPANSION FOR IMAGE DIMENSIONS

Do not modify Laravel merely to add:

width
height
aspect_ratio
blurhash

for this phase.

Use the established UI geometry/aspect-ratio contract where source dimensions are not provided.


22. FROZEN PRODUCT CARD RATIO

The frozen design token remains authoritative.

Inspect the actual token.

Expected current authority:

--media-product-card: 4 / 3

Do NOT change it to 4:5 simply because source fixture images are portrait.

Repository token authority wins.


23. PRODUCT CARD GEOMETRY

ProductCard must reserve stable image geometry before media loads.

Verify no layout shift from image arrival.

Use the frozen media aspect token and existing component geometry.

Do not introduce arbitrary fixed pixel heights.


24. OBJECT FIT

Furniture must remain visually legible.

Do not blindly switch every product image to:

object-fit: cover

if that crops furniture materially.

Preserve the established contain/cover decision based on the component/media treatment.

Performance optimization must not degrade merchandising.


25. PRODUCTCARD RESPONSIVE SIZES

Audit ProductCard `sizes`.

It must reflect actual rendered grid widths rather than a generic inaccurate value.

Account for the actual responsive ProductGrid column behavior.

Do not hardcode a `sizes` string from assumptions.

Inspect the real grid first.


26. HOMEPAGE PRODUCTCARD SIZES

If the homepage uses the same ProductCard at different layout widths than /products:

ensure its `sizes` behavior remains reasonably accurate.

Prefer one reusable component API capable of describing placement if necessary.

Do not fork ProductCard.


27. CATEGORY PRODUCTCARD SIZES

Likewise verify category grid placement.

Do not create:

CategoryOptimizedProductCard.


28. SEARCH PRODUCTCARD SIZES

Likewise verify search result grid placement.

No SearchProductCard fork.


29. HERO IMAGE

Audit homepage hero loading carefully.

The above-the-fold hero is a likely LCP candidate.

Verify:

- Next Image;
- stable geometry;
- correct `sizes`;
- appropriate fetch priority/preload behavior;
- no duplicate preload;
- no eager loading of unrelated images;
- no layout shift.

Do not assume the hero is LCP: verify where possible.


30. PRELOAD POLICY

Only genuinely critical above-the-fold media should be preloaded/high priority.

Expected:

homepage hero MAY qualify.

Do not preload:

every ProductCard;
every gallery image;
category thumbnails below the fold;
search results;
secondary PDP gallery images.


31. NEXT.JS VERSION

Inspect the installed Next.js 16.3.8 image API and use the supported current approach.

Do not copy obsolete examples from old Next.js versions.

In particular, verify the current recommended preload/priority API before modifying it.


32. LCP ON PDP

Audit the PDP lead image.

If the lead product image is above the fold and evidence indicates it is an LCP candidate, use appropriate high-priority loading.

Do not eagerly load every PDP gallery image.


33. PDP GALLERY

Expected strategy:

lead image:
critical when visible above fold

secondary gallery:
lazy unless genuinely above fold

Do not download the entire gallery eagerly.


34. GALLERY DUPLICATION

Ensure the same primary image is not unintentionally rendered/downloaded twice as both:

lead image
and first secondary image

unless the UI intentionally needs both.

Deduplicate presentation where appropriate without changing backend data.


35. MISSING MEDIA

Preserve the existing neutral missing-media state.

Do not make missing media trigger:

- external placeholder requests;
- random image services;
- unnecessary network traffic;
- CLS.


36. CATEGORY IMAGE

Phase 14.10 explicitly preserved the separate category-image refinement.

Do not reintroduce the removed/changed category hero image merely for image testing.

Optimize the current design, not an earlier version.


37. ALT TEXT

Performance changes must preserve accessibility.

For API product media:

use authoritative `alt_text` according to the established mapping.

Do not keyword-stuff alt attributes for SEO.


38. DECORATIVE IMAGES

If an image is genuinely decorative:

use appropriate empty alt/semantics.

Do not hide meaningful product photography from assistive technology.


39. FIXTURE IMAGE AUDIT

Inspect:

public/fixtures/**

or the actual fixture path.

Record for each image:

path
dimensions
format
file size
usage
above/below fold

Identify clearly oversized fixture assets.


40. FIXTURE OPTIMIZATION

If a fixture source is dramatically larger than any realistic rendered need, optimize it.

Preserve enough source resolution for high-DPR displays.

Do not aggressively compress until visible artifacts appear.


41. FIXTURE FILE BUDGET

Do not invent a universal arbitrary "every image <100 KB" rule.

Judge based on:

- rendered dimensions;
- DPR;
- photographic complexity;
- image role;
- resulting Next optimized delivery.

Record before/after bytes when modifying local source files.


42. COMPETITOR ASSETS

Do not introduce:

Urban Ladder images
IKEA images
watermarked images
scraped retailer assets

Fixture assets remain owned/approved project assets.


43. FONT INVENTORY

Audit current fonts.

Expected:

Young Serif display font
Helvetica Now / established utility stack

Young Serif was already loaded through the existing font architecture.

Do not add another font loader.


44. FONT PERFORMANCE

Verify:

- framework/local font loading is appropriate;
- no duplicate Young Serif downloads;
- no blocking remote Google Fonts request if font is already local;
- sensible `font-display`;
- no layout instability from duplicated font definitions.

Do not change typography authority.


45. NO FONT REPLACEMENT

Do not replace Young Serif with another font for performance.

Do not replace the established UI font stack.

Optimize loading, not brand identity.


46. CLIENT COMPONENT AUDIT

Enumerate every `"use client"` component reachable from the public catalog.

Classify WHY each requires client execution.

Expected examples may include:

- mobile drawer;
- Phase 14.6 narrow price conversion;
- Phase 14.6 sort selection;
- route error boundary.

Do not blindly convert components.


47. MINIMIZE CLIENT JS

If a component is marked `"use client"` but contains no browser state/events/hooks/client-only API:

consider converting it to a Server Component.

Only do so when clearly safe.

Do not perform broad architectural churn.


48. PHASE 14.6 JAVASCRIPT LIMITATION

Preserve the documented decision:

JavaScript is required narrowly for:

- whole-TZS → minor-unit price conversion;
- sort selection behavior.

Do NOT attempt to eliminate this requirement by changing the frozen query contract.


49. PHASE 14.6 STATE CAVEAT

Current price/sort client controls initialize from props using:

useState/defaultValue

and are correct because current form submission performs full-page navigation/remount.

Do NOT add synchronization effects merely for hypothetical future client-side navigation.

Documented future caveat remains sufficient.


50. NO SPA CONVERSION

Do not convert:

search
filters
sorting
pagination

into client-side SPA navigation for perceived performance.

The established server-first/native GET architecture remains authoritative.


51. JAVASCRIPT BUNDLE

Inspect production build output and client boundaries.

Identify obvious accidental large client dependencies or components.

Do not add bundle-analysis dependencies unless already available.

Use Next build evidence and browser network/devtools where sufficient.


52. MUI

Do not replace MUI for bundle-size reasons.

Do not rewrite components in raw CSS merely to chase synthetic metrics.

Optimize within the approved stack.


53. ICONS

Continue importing only required icons from:

@mui/icons-material

Do not import an entire icon namespace/barrel in a way that measurably bloats client bundles if direct imports are already supported by current code/tooling.

Do not perform speculative import churn without evidence.


54. SERVER COMPONENTS

Preserve server rendering for SEO-critical pages.

The following should remain server-first:

homepage
/products
/products/[slug]
/categories/[slug]
/search

Do not add `"use client"` to page-level components.


55. API REQUEST AUDIT

Audit requests for:

homepage
/products
category
PDP
search

Record:

endpoint
number of calls
cache policy
whether duplicate
whether metadata/page deduplication occurs
whether request is necessary.


56. METADATA DEDUPLICATION

Phase 14.7 introduced React cache-based deduplication for CAT-002/CAT-004 metadata + page resolution.

Verify it still works.

Do not regress into:

generateMetadata fetch
+
page fetch

for the same detail resource.


57. STRUCTURED DATA

Phase 14.8 reuses already-resolved page resources.

Verify it still introduces:

ZERO extra CAT-002/CAT-004 fetches.

Do not fetch again merely to build JSON-LD.


58. COLLECTION API

CAT-001 intentionally returns lightweight Product Summary resources.

Do not replace collection requests with CAT-002 N+1 calls.

The backend contract deliberately separates lightweight collection summaries from full detail.


59. PAGINATION

Preserve server pagination.

Do not fetch all products client-side for:

sorting
filtering
search
pagination.


60. SITEMAP EXCEPTION

Phase 14.9 sitemap intentionally traverses CAT-001/CAT-003 pagination.

Do not confuse sitemap crawl generation with interactive page behavior.

Do not load the whole catalog on normal pages.


61. CACHE POLICY AUDIT

IMPORTANT:

Earlier Group N pages used:

cache: no-store

during implementation.

Now inspect whether that remains appropriate for every PUBLIC catalog request.


62. PUBLIC CATALOG CACHE AUTHORITY

The frozen API convention states that public catalog endpoints:

CAT-001..CAT-006

require no authentication,
contain zero customer-specific state,
and are safe for public caching/CDN/Next.js ISR.

This makes caching an allowed optimization area.

But do not blindly introduce ISR everywhere.


63. CACHE DECISION

For each public catalog request, determine whether:

- no-store remains justified;
- request memoization is sufficient;
- Next fetch revalidation is appropriate;
- route-level revalidation is appropriate.

Base the decision on:

- freshness expectations;
- admin catalog updates;
- deployment architecture;
- existing API/CDN caching;
- SEO behavior;
- failure semantics.


64. DO NOT INVENT STALE-DATA POLICY

If no repository decision defines acceptable catalog staleness/revalidation duration, do NOT invent:

60 seconds
5 minutes
1 hour

as business policy.

You may optimize duplicate requests without inventing cache freshness.

If choosing persistent revalidation requires a product/business freshness decision not present in repository authority:

STOP that optimization and report:

DEFERRED — CACHE FRESHNESS POLICY NOT DEFINED.


65. REQUEST MEMOIZATION

Safe same-request deduplication is different from persistent caching.

Prefer safe request-local deduplication where useful before inventing stale-cache durations.


66. NO CUSTOMER CACHE LEAK

This phase is public catalog only.

Do not establish cache helpers that might later accidentally cache:

account
orders
cart
checkout
payments
requests/enquiries private detail

as public content.


67. API_BASE_URL

Do not expose API_BASE_URL to the browser.

Do not convert it to NEXT_PUBLIC_* for performance.

Server-only boundary remains.


68. SITE_URL

Do not change Phase 14.7 SITE_URL architecture.

No performance optimization should derive site origin from request Host.


69. CATALOG_MEDIA_BASE_URL

Do not expose credentials/secrets through media configuration.

A public image origin may be public; R2 credentials never are.


70. IMAGE SECURITY

Remote image configuration must not become an SSRF/open-proxy mechanism.

Keep allow-list narrow.

Do not create a generic:

/api/image?url=<arbitrary>

proxy.


71. HTTP CACHING

Inspect actual API/media response headers when a local/available API or media endpoint exists.

Record evidence.

Do not modify backend cache headers unless a real backend defect blocks the phase.

Expected backend changes:

NONE.


72. R2 CACHE HEADERS

If real R2/CDN media is available, inspect:

Cache-Control
Content-Type
Content-Length
ETag
Accept-Ranges

where available.

Report deficiencies.

Do not reconfigure Cloudflare infrastructure unless explicitly within existing repository-managed config and clearly required.

Expected Phase 14.11 frontend scope should not rebuild R2 operations.


73. LOCAL API LIMITATION

If the local Laravel catalog is empty:

report:

API CATALOG PERFORMANCE DATA:
LIMITED — EMPTY LOCAL CATALOG

Do not fabricate production records merely to benchmark.


74. FIXTURE PERFORMANCE MODE

Use explicit fixture mode for deterministic visual/performance analysis when real catalog data is unavailable.

Clearly distinguish:

fixture performance
vs
API-backed performance.


75. NETWORK REQUEST AUDIT

For representative pages, inspect network activity.

Look for:

- duplicate image requests;
- duplicate API requests;
- unexpected remote font requests;
- unnecessary JS chunks;
- eager below-fold images;
- 404 assets;
- redirect chains;
- failed media requests.


76. REDIRECTS

Internal canonical links should not require avoidable redirects.

Do not change canonical routing.

If internal links unexpectedly redirect because of trailing slashes/page=1/etc., fix the link source rather than adding redirect hacks.


77. COMPRESSION

Do not add custom gzip/Brotli middleware to Next.js without evidence.

Deployment/platform compression is a separate operational concern unless repository architecture explicitly owns it here.


78. THIRD-PARTY SCRIPTS

Audit public pages for third-party scripts.

Expected:

NONE or only explicitly approved architecture.

Do not add analytics, tag managers, chat widgets, tracking pixels, or performance SaaS in Phase 14.11.


79. HYDRATION

Check browser console for:

hydration mismatch
recoverable hydration error
client/server markup mismatch

Any new/existing reproducible catalog hydration error blocks PASS.


80. CLS

Inspect for layout shifts caused by:

- images;
- fonts;
- sticky category navigation;
- loading states;
- dynamic controls.

Images must reserve geometry.

Sticky navigation must not unexpectedly shift content when becoming sticky.


81. STICKY NAVIGATION

Phase 14.10 preserved the sticky category navbar.

Audit it for performance/visual stability.

Do not implement scroll listeners if CSS sticky already handles the requirement.

Prefer CSS:

position: sticky

over JavaScript scroll tracking.


82. STICKY COMPOSITING

Do not add:

backdrop-filter
large blur
continuous box-shadow animation

to the sticky nav.

Keep it visually aligned with the frozen design system.


83. LOADING UI

Audit app/loading.tsx and any page-level loading behavior.

Do not add elaborate skeleton systems merely to improve perceived metrics.

If existing loading geometry causes significant layout shift, correct it using existing design conventions.


84. ERROR UI

Do not preload heavy catalog resources from error/not-found states.

Preserve Phase 13.8 architecture.


85. CSS

Do not introduce large duplicated page-specific style blocks.

Reuse theme/tokens/components.

Do not inline enormous CSS strings for micro-optimization.


86. DESIGN TOKENS

No token authority changes are expected.

Especially do not change:

breakpoints
spacing
typography
product media ratio

to manipulate performance scores.


87. PHOTOGRAPHY QUALITY

The design system explicitly prioritizes large realistic furniture imagery.

Performance optimization must balance quality with payload.

Do not reduce image quality until furniture texture/material presentation becomes visibly poor.


88. RESPONSIVE IMAGE VERIFICATION

For each major image role, verify the browser is not downloading a dramatically oversized resource relative to rendered size.

Roles:

hero
ProductCard
PDP lead
PDP secondary
editorial image
category/room discovery image.


89. DPR

Test at normal DPR where possible and reason about high-DPR delivery.

Do not size source images only for 1× screens.


90. IMAGE `sizes`

Every fill/responsive Next Image should have an accurate `sizes` declaration where required.

A missing/inaccurate `sizes` that causes near-100vw downloads for small grid cards is a performance defect.


91. IMAGE DIMENSIONS

For static imports:

use framework-known dimensions where practical.

For remote images without intrinsic dimensions:

use stable fill/aspect-ratio containers.

Do not guess backend image pixel dimensions.


92. FETCH PRIORITY

Audit actual rendered HTML for image loading hints.

Do not combine contradictory hints such as unnecessary preload + lazy load.


93. BELOW-FOLD LAZY LOADING

Below-fold catalog imagery should generally remain lazy-loaded via framework defaults.

Do not manually implement IntersectionObserver image loading unless Next Image demonstrably cannot satisfy the requirement.


94. HERO MOBILE

Ensure the homepage hero's `sizes` does not force desktop-sized image delivery on narrow mobile screens.


95. PRODUCT GRID MOBILE

Ensure a one-column/two-column mobile arrangement downloads appropriately sized ProductCard images.

Inspect the actual current grid before changing `sizes`.


96. PRODUCT GRID DESKTOP

Likewise ensure desktop cards are not all requesting full original 1200–2000px source dimensions when rendered substantially smaller.


97. PDP DESKTOP

The lead image may legitimately need a larger candidate than ProductCard.

Do not globally constrain all catalog media to card dimensions.


98. IMAGE QUALITY SETTING

Do not globally raise image quality.

Do not globally lower image quality without visual evidence.

Use framework defaults unless measured/visual evidence supports a targeted adjustment.


99. STATIC ASSET CACHEABILITY

Inspect immutable Next static asset behavior.

Do not manually add cache headers for `/_next/static` unless framework/platform behavior is broken.


100. LOGO DELIVERY

Audit whether the logo is downloaded at an unnecessarily huge intrinsic size relative to display.

If optimization is possible without modifying brand identity, do it.

Do not rasterize/rebuild the logo merely to shave negligible bytes.


101. SERVER TIMING

If practical, record representative document response timing in local production mode.

Do not interpret local absolute timings as production SLA.

Use them primarily for before/after comparison.


102. PERFORMANCE BUDGET

Do not create arbitrary hard budgets without evidence.

But record meaningful before/after metrics such as:

initial document bytes
initial JS transferred
image transferred for first viewport
request count
LCP candidate size
fixture source bytes

where tooling makes them reliable.


103. TEST — IMAGE CONTRACT

Add focused automated coverage, preferably:

npm run test:performance

or:

npm run test:images

Choose one coherent Phase 14.11 script according to existing test naming conventions.

It should validate durable performance contracts, not brittle generated implementation details.


104. TEST IMAGE CONTRACTS

Test where practical:

- catalog images use Next Image;
- remote media origin remains constrained;
- ProductCard has stable aspect geometry;
- ProductCard responsive sizes exist;
- hero responsive sizes exist;
- only intended critical images request preload/high priority;
- secondary PDP images remain non-priority;
- missing media makes no external request;
- no raw arbitrary remote image injection.


105. TEST SERVER-FIRST CONTRACT

Verify catalog pages remain Server Components.

No page-level `"use client"` on:

/
 /products
 /products/[slug]
 /categories/[slug]
 /search


106. TEST CLIENT BOUNDARIES

Add/extend contract coverage to ensure narrow client components remain narrow.

Do not assert exact chunk filenames/hashes.


107. TEST REQUEST DEDUPLICATION

Where current architecture supports deterministic testing, verify PDP/category metadata + page resolution do not duplicate detail requests.

Do not mock framework internals excessively just to assert an implementation detail.


108. TEST COLLECTION EFFICIENCY

Verify collection rendering does not invoke CAT-002 per ProductCard.

One CAT-001 collection response must remain sufficient for collection cards.


109. TEST IMAGE CONFIG

Verify remotePatterns/allowed image host configuration remains bounded.

Do not hardcode production secrets in tests.


110. EXISTING TEST INFRASTRUCTURE

Continue:

node:test + tsx

Never reintroduce:

load-ts.mjs
node:vm
eval
new Function
SourceTextModule
runtime code injection.


111. SONAR

Ensure Phase 14.11 introduces no dynamic execution finding or equivalent unsafe workaround.


112. BROWSER RUNTIME

In production build, verify:

homepage
/products
representative category
representative PDP
search
filtered/sorted products

where fixture/API data permits.


113. MOBILE RUNTIME

At 390px inspect:

- LCP image;
- responsive image candidate;
- image sharpness;
- CLS;
- horizontal overflow;
- sticky nav;
- product grid;
- PDP gallery;
- filter controls.


114. DESKTOP RUNTIME

At 1440px inspect the same concerns.

Do not optimize only for mobile or only for desktop.


115. BREAKPOINT REGRESSION

Verify:

959
960
961

for:

header/category navigation
ProductGrid
image sizes
sticky navigation
PDP layout

No breakpoint-induced image/layout instability.


116. 200% ZOOM

Verify no performance optimization breaks:

reflow
image containment
focus
navigation
controls.


117. ACCESSIBILITY

Performance optimization must preserve:

- alt text;
- focus;
- semantic links;
- heading structure;
- controls;
- reduced motion;
- keyboard navigation.

Do not hide content from accessibility tree to reduce rendering work.


118. SEO

Run:

npm run test:seo
npm run test:structured-data
npm run test:crawl
npm run test:links

Performance work must not change:

canonicals
robots
noindex
JSON-LD truthfulness
sitemap
internal-link graph.


119. PHASE 14.6 REGRESSION

Run:

npm run test:filters

Verify:

- min/max price works;
- sort selection works;
- narrow JS requirement remains documented;
- URL round-trip works;
- search/filter state preservation remains correct.


120. CATALOG REGRESSION

Run:

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

plus the new Phase 14.11 performance/image suite.


121. STATIC VALIDATION

Must pass:

npm run typecheck
npm run lint
npm run build
git diff --check


122. BACKEND BOUNDARY

Expected:

backend changed = NO

The backend already deliberately distinguishes lightweight collection summaries from full product details to reduce listing bandwidth. Preserve that contract rather than creating frontend N+1 detail fetching.

If a real backend defect is discovered:

STOP and report it separately.


123. R2 BOUNDARY

Expected:

R2 upload/storage architecture changed = NO

Group K already owns media management.

Do not duplicate it.


124. FLUTTER BOUNDARY

Expected:

Flutter changed = NO


125. DESIGN SYSTEM BOUNDARY

Expected:

design system changed = NO

If an actual missing performance-related media token is discovered:

do not invent one casually.

First prove why existing tokens cannot represent the behavior.


126. DEPENDENCIES

Expected:

NONE

Do not install:

image optimization packages
lazy-load libraries
bundle analyzers
Lighthouse npm package
web-vitals package

unless repository inspection demonstrates a genuine requirement that cannot be met by Next/browser tooling.

Prefer built-in platform capabilities.


127. NO ANALYTICS

Do not add runtime Web Vitals analytics/reporting infrastructure.

This phase measures and optimizes; production telemetry belongs to later operations/monitoring work unless already approved.


128. NO SERVICE WORKER

Do not add:

service worker
PWA
offline cache

for performance.


129. NO CLIENT CACHE LIBRARY

Do not add:

SWR
React Query
Apollo

Public catalog remains server-first.


130. NO CDN REARCHITECTURE

Do not redesign deployment/CDN topology.

Document external opportunities separately.


131. NO PREMATURE ISR POLICY

Public catalog endpoints are cache-safe according to the frozen contract, but persistent freshness policy must be intentional.

If no authoritative revalidation period exists:

do not guess one.

Report the opportunity for a later explicit policy decision.


132. GROUP N FINAL AUDIT

Before declaring PASS, perform a concise final Group N audit covering:

USABLE
- responsive;
- accessible;
- functional catalog discovery;
- search/filter/sort;
- product detail;
- truthful states.

CRAWLABLE
- metadata;
- canonical URLs;
- structured data;
- sitemap;
- robots;
- internal links;
- hard 404s.

PERFORMANT
- server-first;
- efficient image delivery;
- correct priority/lazy loading;
- stable geometry;
- minimal client JS;
- no N+1;
- no unnecessary duplicate requests;
- reasonable measured production behavior.


133. DOCUMENTATION

Update:

phases/group-N-phases.md

with:

- baseline measurements;
- image inventory;
- findings;
- optimizations;
- before/after evidence;
- remote media/R2 assumptions;
- cache-policy findings;
- remaining deferred opportunities;
- final Group N exit audit.

Update frontend/AGENTS.md only for durable rules discovered in this phase.

Do not turn transient benchmark numbers into architectural law.


134. ADR

Expected:

NONE

Routine Next Image/performance tuning does not need an ADR.

An ADR may be required only if this phase intentionally establishes a new durable caching/ISR architecture.

If such a decision is not already authorized:

STOP rather than silently create it.


135. COMPLETION REPORT

Return:

PHASE 14.11 — IMAGE / PERFORMANCE OPTIMIZATION

Status:
PASS / BLOCKED


BASELINE

Build:
PASS / FAIL

Browser:
<value>

Mode:
production

Routes measured:
<list>

Lighthouse:
<available/not available>

Homepage:
<metrics if available>

Products:
<metrics if available>

PDP:
<metrics if available>


IMAGE INVENTORY

Total meaningful image surfaces:
<n>

Next Image:
<n>

Raw img:
<n + justification>

Remote media:
<configuration>

Local fixtures:
<count / total bytes>

Oversized sources found:
<n>


IMAGE OPTIMIZATION

Hero:
<findings/actions>

ProductCard:
<findings/actions>

PDP lead:
<findings/actions>

PDP secondary:
<findings/actions>

Editorial/category:
<findings/actions>

Logo:
<findings/actions>

Missing media:
PASS / FAIL

Product media ratio:
<actual frozen token>


RESPONSIVE DELIVERY

Hero sizes:
<actual>

ProductCard sizes:
<actual>

PDP sizes:
<actual>

390px candidate behavior:
PASS / FAIL

1440px candidate behavior:
PASS / FAIL


LOADING PRIORITY

Preloaded/priority images:
<list>

Justification:
<details>

Below-fold lazy loading:
PASS / FAIL

Secondary gallery lazy:
PASS / FAIL

Duplicate primary gallery rendering:
NONE / <details>


LAYOUT STABILITY

Image geometry:
PASS / FAIL

Font stability:
PASS / FAIL

Sticky nav:
PASS / FAIL

CLS:
<metric / observed result>


FONTS

Young Serif:
<loading method>

Duplicate loads:
NONE / FAIL

Unexpected remote font request:
NONE / FAIL

Typography changed:
NO


CLIENT JS

Client components audited:
<n>

Necessary:
<list>

Converted to server:
<list/NONE>

Page-level use client:
NONE / FAIL

Phase 14.6 price JS:
PRESERVED

Phase 14.6 sort JS:
PRESERVED


API PERFORMANCE

Homepage requests:
<details>

Products:
<details>

Category:
<details>

PDP:
<details>

Search:
<details>

Duplicate detail fetch:
NONE / FAIL

CAT-002 N+1:
NONE / FAIL

Structured-data extra fetch:
NONE / FAIL


CACHE POLICY

Current catalog policy:
<details>

Request-local deduplication:
<details>

Persistent revalidation:
<implemented/deferred>

If deferred:
CACHE FRESHNESS POLICY NOT DEFINED / <other reason>

Customer/private public-cache risk:
NONE / FAIL


R2 / MEDIA

Existing Group K architecture reused:
YES / FAIL

Second media architecture:
NONE / FAIL

Remote allow-list:
PASS / FAIL

Arbitrary host:
NO / FAIL

Real media headers inspected:
<results / NOT AVAILABLE>


NETWORK

Duplicate image requests:
NONE / <details>

404 assets:
NONE / <details>

Unexpected redirects:
NONE / <details>

Unexpected third-party scripts:
NONE / <details>

Hydration errors:
NONE / FAIL

Console errors:
NONE / <details>


RESPONSIVE

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

200% zoom:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


PERFORMANCE TESTS

test:performance or test:images:
PASS / FAIL

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


GROUP N EXIT AUDIT

Usable:
PASS / FAIL

Crawlable:
PASS / FAIL

Performant:
PASS / FAIL

Group N exit condition:
PASS / BLOCKED


BOUNDARIES

Backend changed:
NO

R2 architecture changed:
NO

Flutter changed:
NO

Design-system authority changed:
NO

New dependency:
NONE

SPA conversion:
NONE

Service worker:
NONE

Analytics:
NONE

Commerce work:
NONE

Group O work:
NONE


DOCUMENTATION

Group N:
UPDATED / FAIL

frontend/AGENTS.md:
UPDATED / UNCHANGED

ADR:
NONE / <id>


GIT

git-workflow-and-versioning read:
YES / NO

Operations:
<exact>

Commit:
<hash/message>

Push:
<result/NONE>


RESULT

Phase 14.11:
PASS / BLOCKED

Group N — Website Catalog and SEO:
CLOSED / BLOCKED

Next phase:
Phase 15.1 — Registration/Login UI / BLOCKED


136. STOP CONDITION

Phase 14.11 may be declared PASS only when:

- a production-mode baseline was collected before optimization;
- meaningful image surfaces were audited;
- Next Image is used appropriately;
- remote image origins remain narrowly allow-listed;
- existing CATALOG_MEDIA_BASE_URL authority is preserved;
- existing Group K R2 architecture is reused rather than duplicated;
- product media API contract remains unchanged;
- frozen product-card media ratio remains authoritative;
- responsive image `sizes` reflect real layouts;
- above-fold critical media uses appropriate loading priority;
- below-fold imagery remains lazy where appropriate;
- PDP secondary gallery is not eagerly downloaded without justification;
- image geometry prevents avoidable CLS;
- no external random placeholder media is introduced;
- fixture sources are not grossly oversized without justification;
- brand logo identity remains untouched;
- font loading has no obvious duplication/regression;
- public catalog remains server-first;
- unnecessary client boundaries are not introduced;
- Phase 14.6's narrow JS behavior remains intact;
- no speculative prop synchronization is added for hypothetical SPA navigation;
- no CAT-002 N+1 is introduced;
- metadata/page fetch deduplication remains effective;
- structured data adds no duplicate catalog fetch;
- public catalog cache safety is audited;
- no arbitrary persistent revalidation duration is invented without authority;
- no private/customer data is exposed to public caching;
- API_BASE_URL remains server-only;
- no arbitrary image proxy/SSRF surface is introduced;
- no hydration errors exist;
- sticky category navigation remains stable;
- representative mobile and desktop production pages are runtime-verified;
- 959/960/961 breakpoint behavior remains correct;
- accessibility is preserved;
- SEO/crawl/internal-link contracts remain unchanged;
- request-first behavior remains unchanged;
- focused performance/image tests pass;
- all relevant Group N regressions pass;
- typecheck passes;
- lint passes;
- production build passes;
- git diff --check passes;
- no backend change is made;
- no duplicate R2/media architecture is introduced;
- no Flutter change is made;
- no design-system authority is changed;
- no unnecessary dependency is added;
- no Group O implementation is started;
- the final Group N audit demonstrates the public catalog is usable, crawlable, and performant.

Only then report:

Phase 14.11 — PASS
Group N — CLOSED
Phase 15.1 — READY

Do not start Phase 15.1 automatically.