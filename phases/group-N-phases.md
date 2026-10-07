# Phase 14.7 — SEO Metadata

## Entry State

```text
Phase 14.1 — Homepage — PASS
Phase 14.2 — Category Pages — PASS
Phase 14.3 — Product Listing — PASS
Phase 14.4 — Product Detail — PASS
Phase 14.5 — Search — PASS
Phase 14.6 — Filters & Sorting — PASS
Phase 14.7 — SEO Metadata — ACTIVE
```

Implement the public website's canonical Next.js metadata architecture.

Do **not** start Phase 14.8.

---

# 1. Objective

Implement accurate, server-rendered SEO metadata for the public catalog routes already built:

```text
/
/products
/products/[slug]
/categories/[slug]
/search
```

Phase 14.7 owns:

```text
site title/default metadata
page titles
meta descriptions
canonical URLs
robots metadata decisions at page level
Open Graph metadata
Twitter card metadata
metadata image selection
metadata URL/origin handling
query/facet canonicalization policy
not-found metadata correctness
```

This phase does **not** own:

```text
JSON-LD / Schema.org
sitemap.xml
robots.txt
SEO landing-page generation
new internal-linking architecture
performance optimization
```

Those remain later Group N phases.

---

# 2. Read Before Coding

Read and inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md

frontend/design-system/DESIGN.md
frontend/design-system/ACCESSIBILITY.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/openapi.yaml
docs/decisions.md

phases/group-N-phases.md
```

Then inspect the actual implementations of:

```text
app/layout.tsx
app/page.tsx

app/products/page.tsx
app/products/[slug]/page.tsx

app/categories/[slug]/page.tsx

app/search/page.tsx

proxy.ts

catalog data helpers
product detail helper
category helper
media helpers
fixture-mode helpers
```

Also inspect any existing `metadata`, `generateMetadata`, `metadataBase`, Open Graph, robots, or title implementation before adding anything.

Do not assume Phase 13 or earlier phases left metadata completely empty.

---

# 3. Git Policy

Before ANY Git command:

```text
locate/read/follow:
git-workflow-and-versioning
```

Preserve unrelated owner changes.

Do not commit:

```text
.env
.env.local
secrets
temporary runtime files
```

Use an atomic Phase 14.7 commit.

---

# 4. Next.js Metadata API

Use the installed Next.js App Router metadata architecture.

Prefer:

```text
export const metadata
```

for static metadata and:

```text
export async function generateMetadata(...)
```

for resource-derived metadata.

Do not manually inject:

```html
<title>
<meta>
<link rel="canonical">
```

through arbitrary page JSX.

Do not add `next/head`.

Do not create a parallel metadata system.

---

# 5. Server-First Metadata

Metadata generation must remain server-side.

Expected new client boundaries:

```text
NONE
```

Do not fetch metadata in:

```text
useEffect
browser JavaScript
client components
```

Search engines and non-JavaScript clients must receive metadata from the server response.

---

# 6. Site Identity

Canonical site/brand name:

```text
SL Furnitures
```

Use that exact existing brand identity.

Do not rename the business for SEO purposes.

Do not invent slogans, awards, locations, shipping promises, quality claims, guarantees, or market leadership.

The design system describes SL Furnitures as architectural, warm, and editorial; metadata copy should remain factual and restrained rather than keyword-stuffed.

---

# 7. Root Title Architecture

Establish one consistent title architecture.

Prefer a root title template conceptually equivalent to:

```text
%s | SL Furnitures
```

with an appropriate site default.

Individual pages should supply their page-specific portion rather than manually concatenating inconsistent brand strings everywhere.

Inspect the installed Next.js metadata API and implement the correct template/default mechanism.

---

# 8. No Keyword Stuffing

Forbidden titles such as:

```text
BEST CHEAP FURNITURE TANZANIA | SOFAS BEDS CHAIRS SALE | SL FURNITURES
```

Do not stuff category/product names repeatedly.

Metadata should read naturally.

---

# 9. Metadata Descriptions

Descriptions must be:

```text
factual
concise
page-specific where useful
derived from authoritative data where available
```

Do not invent:

```text
free delivery
same-day delivery
best prices
luxury quality
award-winning
handmade
sustainable
premium materials
nationwide shipping
```

unless those claims are explicitly established by project authority.

---

# 10. Site Origin Is Required for Canonical Metadata

Canonical and social metadata need an authoritative public website origin.

Before implementing this, inspect whether the repository already defines something equivalent to:

```text
SITE_URL
APP_URL
WEB_URL
metadataBase
```

Reuse existing authority if present.

Do not introduce a second site-origin configuration.

---

# 11. If No Site-Origin Authority Exists

If no website-origin configuration exists, introduce **one server-side website-origin configuration** for metadata.

Preferred conceptual name:

```text
SITE_URL
```

unless repository naming conventions establish another name.

It represents only the public website origin:

```text
https://example.com
```

not:

```text
https://example.com/
https://example.com/products
https://api.example.com
```

Do NOT use `API_BASE_URL` as the website canonical origin.

---

# 12. Server-Only Site URL

Do not introduce:

```text
NEXT_PUBLIC_SITE_URL
```

merely because metadata needs the origin.

Metadata generation is server-side.

Prefer a server-only environment variable unless existing deployment architecture establishes otherwise.

---

# 13. Do Not Invent the Production Domain

The repository currently does not establish a production SL Furnitures website origin in the evidence available for this phase.

Therefore:

```text
DO NOT GUESS ONE.
```

Do not hard-code:

```text
slfurnitures.com
slfurniture.co.tz
example.com
localhost
```

as the production canonical origin.

If production configuration is unavailable, implement the configuration boundary and report:

```text
Production SITE_URL:
REQUIRED / NOT CONFIGURED
```

rather than inventing a domain.

---

# 14. Local Development

Development/test behavior may use an explicit local test origin where needed for deterministic tests.

Do not allow a localhost fallback to silently become production canonical metadata.

Production misconfiguration must be detectable.

Choose the exact validation behavior after inspecting existing environment/config conventions.

---

# 15. Root Metadata

The root layout should own site-wide metadata that genuinely applies everywhere, such as:

```text
site title template
default site title
metadataBase/site origin integration
appropriate default description
Open Graph site name
```

Do not put product/category-specific metadata in the root layout.

---

# 16. Homepage Metadata

Route:

```text
/
```

Implement a unique title and description appropriate to the actual homepage.

The homepage currently uses the message:

```text
Furniture for the way you live.
```

It may inform the homepage title/description.

Do not invent promotional claims.

Canonical:

```text
/
```

---

# 17. Product Listing Metadata

Route:

```text
/products
```

Provide a factual title such as the repository's chosen equivalent of:

```text
Furniture
```

and an appropriate restrained description.

Canonical base collection:

```text
/products
```

---

# 18. Product Detail Metadata

Route:

```text
/products/[slug]
```

Use CAT-002 authoritative product data.

The frozen API explicitly provides the necessary public detail fields for SSR/Open Graph metadata.

Title should derive from:

```text
product.name
```

Canonical must use:

```text
/products/{product.slug}
```

using the backend-returned canonical slug.

Do not generate the slug locally.

---

# 19. Product Description

Prefer:

```text
product.description
```

when it contains useful customer-facing content.

If the product description is absent/blank according to actual types, use a short factual fallback based only on known product fields.

Do not synthesize marketing prose with AI-style claims.

---

# 20. Product Canonical Slug

CAT-002 may resolve by slug or opaque product ID, but the website's canonical public route is slug-based.

Therefore if a product detail request somehow resolves through an identifier and the returned product has:

```text
slug = modern-3-seater-sofa
```

canonical metadata must point to:

```text
/products/modern-3-seater-sofa
```

not the machine ID.

The API contract explicitly distinguishes public crawlable slugs from machine identifiers.

---

# 21. Product Open Graph Image

Use authoritative product media.

Selection priority:

```text
CAT-002 primary image
→ appropriate first ordered product image if existing helper defines that fallback
→ no fabricated product image
```

The Product Image contract exposes:

```text
url
alt_text
sort_order
is_primary
```

for public catalog media.

Reuse existing media-selection logic if one already exists.

Do not duplicate product-primary-image rules in metadata code.

---

# 22. Missing Product Image

If no valid product image exists:

```text
do not invent one
do not use an unrelated fixture image
do not use another product
```

Use the site's legitimate default social metadata behavior if one exists.

If no appropriate default social image exists, omit the image rather than lying.

---

# 23. Fixture Media

Explicit fixture mode may generate fixture metadata for local visual/testing purposes.

Production/default API mode must never emit fixture product imagery as fallback metadata.

---

# 24. Category Metadata

Route:

```text
/categories/[slug]
```

Use CAT-004 authoritative category data.

Title derives from:

```text
category.name
```

A natural title may conceptually be:

```text
Living Room Furniture
```

where appropriate.

Do not blindly append “Furniture” if the resulting title becomes nonsensical or repetitive.

---

# 25. Category Description

Prefer:

```text
category.description
```

when meaningful.

If unavailable, use only a restrained factual fallback based on:

```text
category.name
SL Furnitures
```

Do not fabricate room-design claims.

---

# 26. Category Canonical

Canonical:

```text
/categories/{category.slug}
```

Use the backend-returned slug.

Do not derive it from the category display name.

---

# 27. Category Image

If the authoritative category response supplies a valid public image suitable for social metadata, it may be used.

Do not use unrelated homepage/editorial fixtures as a fake category image in production.

---

# 28. Search Metadata

Route:

```text
/search
```

Search-result pages should **not be indexable**.

Use page-level robots metadata equivalent to:

```text
index: false
follow: true
```

for `/search`, including populated search queries.

This avoids indexing arbitrary internal search-result combinations.

---

# 29. Search Title

The page may use a useful title such as:

```text
Search furniture
```

and, when a meaningful query exists, may include the search term in a restrained way.

Example conceptually:

```text
Search results for “chair”
```

Do not put raw unbounded user input into metadata without applying the same validated/bounded search contract already established by Phase 14.5.

---

# 30. Search Description

Do not generate elaborate descriptions from user-entered search text.

A generic factual search description is sufficient.

Search pages are noindex.

---

# 31. Search Canonical

Do not create a huge canonical universe for arbitrary internal search terms.

Use the phase's chosen canonical policy consistently with:

```text
noindex, follow
```

Do not treat each arbitrary search query as a valuable indexable landing page.

---

# 32. Faceted `/products` SEO Policy

Phase 14.6 introduced combinations of:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
page
```

These create potentially large URL spaces.

Do not allow every arbitrary filter/sort combination to become a distinct indexable SEO page.

---

# 33. Filtered Product Collections

For `/products` URLs whose distinguishing query state is only filtering/sorting:

```text
category
product_type
availability
min_price
max_price
sort
sort_direction
```

prefer:

```text
robots:
  index: false
  follow: true
```

unless repository SEO authority explicitly establishes an indexable facet.

Do not create indexable faceted landing pages in Phase 14.7.

---

# 34. Why Category Filters Are Not SEO Category Pages

The project already has canonical category pages:

```text
/categories/[slug]
```

Therefore:

```text
/products?category=living-room
```

must not compete with:

```text
/categories/living-room
```

as a second SEO category landing page.

Keep category pages as the canonical category-resource surface.

---

# 35. Sorting URLs

URLs differing only by:

```text
sort
sort_direction
```

must not create new indexable content identities.

Sorting changes presentation order, not the underlying resource identity.

Use the chosen noindex/canonical policy consistently.

---

# 36. Price Filter URLs

URLs such as:

```text
/products?min_price=...
/products?max_price=...
```

must not become automatically indexable SEO landing pages.

No generated “Furniture under X” SEO architecture is part of V1 Phase 14.7.

---

# 37. Availability/Product-Type Facets

Likewise:

```text
availability
product_type
```

do not automatically create indexable landing pages.

`MADE_TO_ORDER` remains a first-class customer offering, but Phase 14.7 does not invent a new SEO landing-page taxonomy.

---

# 38. Pagination Is Different From Sorting

Do not blindly canonicalize every paginated collection page to page 1.

A page such as:

```text
/products?page=2
```

contains a different slice of products.

Inspect current search-engine guidance and existing project routing semantics before choosing canonical behavior for pagination.

Do not solve duplicate sorting/faceting by incorrectly collapsing meaningful pagination.

---

# 39. Conservative Pagination Policy

For plain product-list pagination with no search/filter/sort state:

```text
/products?page=N
```

prefer a self-referencing canonical that preserves meaningful `page=N` for pages greater than 1.

For page 1:

```text
/products
```

remains canonical.

Do not serialize:

```text
?page=1
```

into the canonical.

---

# 40. Filtered Pagination

A URL such as:

```text
/products?product_type=MADE_TO_ORDER&page=2
```

remains part of a filtered/faceted collection and should inherit the filtered collection's noindex policy.

Its canonical handling must not pretend it is an indexable standalone landing page.

---

# 41. `per_page`

`per_page` is a presentation/pagination control, not a distinct SEO content identity.

Do not create canonical variants solely because the page size differs.

Inspect whether the frontend currently exposes or serializes it before implementing metadata logic.

---

# 42. Unknown Query Parameters

Do not globally strip/rewrite arbitrary unknown query parameters.

ROUTING.md explicitly states that routes read only the parameters they own and must not globally rewrite unknown parameters.

Metadata logic should operate on owned query state.

Do not turn SEO metadata generation into a URL-cleanup router.

---

# 43. Canonical Builder

Create or reuse one narrow canonical URL responsibility.

Do not manually concatenate canonical strings independently across:

```text
homepage
products
PDP
categories
search
```

Use standard URL APIs.

Avoid double slashes and accidental API origins.

---

# 44. Canonical Origin Safety

Canonical URLs must use the website origin.

Never generate canonicals from:

```text
API_BASE_URL
request Host header without deliberate trust policy
Cloudflare R2 media origin
fixture origin
```

Do not trust arbitrary forwarded host input to determine SEO identity.

---

# 45. Open Graph Base Metadata

Establish shared Open Graph values where appropriate:

```text
siteName: SL Furnitures
type: website
```

Resource pages may override fields.

Do not invent social usernames/accounts.

---

# 46. Product Open Graph Type

Use the correct supported Next.js/Open Graph metadata semantics available in the installed version.

Do not invent unsupported metadata fields.

Phase 14.8, not this phase, owns Schema.org `Product`.

---

# 47. Open Graph Titles

Open Graph titles should align with page titles but do not need duplicated brand strings if the framework/template already handles presentation appropriately.

Avoid:

```text
SL Furnitures | Chair | Chair Furniture | Buy Chair | SL Furnitures
```

---

# 48. Open Graph Descriptions

Use the same factual source hierarchy as normal metadata descriptions.

Do not maintain separate invented marketing copy.

---

# 49. Twitter Metadata

Use Next.js metadata support for a standard large-image card where a suitable image exists.

Do not invent:

```text
twitter creator
twitter site
social account handles
```

unless repository authority provides them.

---

# 50. Social Image Dimensions

If authoritative image metadata does not include width/height, do not invent dimensions.

Use fields supported by actual source data.

Do not hard-code false `1200x630` dimensions onto arbitrary product imagery.

---

# 51. Image Alt Metadata

Where the metadata API supports image alt text, reuse authoritative:

```text
alt_text
```

from product/category media.

Do not generate keyword-stuffed image alt text.

---

# 52. Homepage Social Image

Inspect whether the homepage has a legitimate brand/editorial image suitable for social sharing.

If an existing production-authoritative homepage hero is appropriate, it may be used.

Do not automatically use fixture-only imagery as production Open Graph media.

If no durable production social image exists:

```text
omit it
```

rather than inventing one.

---

# 53. Logo Is Not Automatically an OG Hero

The official brand logo is identity authority, but do not automatically stretch it into a social preview image if its dimensions/composition are unsuitable.

Do not modify/recolor the logo.

---

# 54. Metadata and R2

Do not create new R2 infrastructure.

Existing backend product media remains authoritative.

Metadata should consume the same public media URLs already supplied through the catalog API.

---

# 55. No Duplicate Product Fetch if Avoidable

`generateMetadata()` and the PDP may both require CAT-002.

Inspect the existing product-detail data architecture and installed Next.js fetch behavior.

Prefer sharing/deduplicating the authoritative resource retrieval where safely possible.

Do not introduce:

```text
metadata CAT-002 request
+
page CAT-002 request
```

as an unavoidable permanent N+1 without investigating reuse/deduplication.

---

# 56. Do Not Add Metadata to API Client

The generic Phase 13.5 API transport remains domain-neutral.

Do not add SEO concepts to:

```text
lib/api/client.ts
```

SEO belongs at the web/page/domain integration layer.

---

# 57. Missing Product Metadata

For a missing:

```text
/products/[slug]
```

preserve the existing hard HTTP 404 behavior.

Do not generate normal indexable product metadata before the resource is known to exist.

The canonical Phase 13.8 not-found experience remains authoritative.

---

# 58. Missing Category Metadata

Likewise:

```text
/categories/[slug]
```

must preserve hard HTTP 404 behavior.

Do not emit a canonical/indexable category identity for a missing category.

---

# 59. Proxy Regression

Phase 14.2A/14.4 proxy preflight exists to preserve hard 404 status before streaming.

Phase 14.7 must not break or duplicate it.

Do not add SEO-specific proxy fetches.

---

# 60. Unexpected API Failure

A:

```text
500
429
timeout
network failure
```

must not be converted into:

```text
404
```

because metadata generation failed.

Preserve the established failure-state architecture.

---

# 61. Metadata Failure Must Not Lie

If authoritative product/category retrieval unexpectedly fails, do not fabricate:

```text
generic product title
fake canonical slug
fake social image
```

that makes the resource appear valid.

Use the existing error behavior.

---

# 62. Fixture Mode

Explicit fixture mode may support deterministic metadata tests.

But:

```text
fixture mode != production fallback
```

Do not silently fall back from API failure to fixture metadata.

---

# 63. Metadata Security

Never include in public metadata:

```text
internal IDs unless required by public URL contract
staff data
customer data
email
phone
private enquiry content
request details
internal notes
storage keys
authorization information
API error details
request IDs
```

Public catalog fields only.

---

# 64. Search Input Safety

Search terms may be reflected in `<title>` or metadata.

Treat them as untrusted user input.

Use framework metadata escaping.

Do not manually construct raw HTML metadata.

Do not use:

```text
dangerouslySetInnerHTML
```

for metadata.

---

# 65. Description Normalization

If API descriptions contain formatting/newlines, normalize them safely for metadata.

Do not interpret API text as HTML.

Do not create an HTML sanitizer dependency merely for metadata.

---

# 66. Metadata Length

Keep titles and descriptions sensible and readable.

Do not implement brittle SEO logic whose only purpose is to hit an exact character count.

If truncation is needed, centralize it and avoid cutting Unicode incorrectly where practical.

Accuracy is more important than an arbitrary “SEO score.”

---

# 67. Product Price in Metadata

Normal title/description metadata does not need to embed price.

Do not generate brittle price-bearing titles such as:

```text
Chair - TZS 450,000 - Buy Now
```

especially under request-first deployment.

Product price belongs naturally in the page and later structured data where appropriate.

---

# 68. Availability in Metadata

Do not turn availability into promotional title spam.

Avoid:

```text
IN STOCK NOW!!!
```

Metadata may remain focused on product identity.

---

# 69. Request-First Policy

Metadata must not imply functionality the production website does not expose.

Forbidden copy:

```text
Buy online
Add to cart
Checkout now
Order today
Pay online
```

unless those flows are actually active.

The release remains request-first.

---

# 70. MADE_TO_ORDER

MADE_TO_ORDER is not:

```text
unavailable
error
out of stock
```

Do not generate metadata that frames made-to-order products negatively.

---

# 71. Category vs Product Listing

Maintain the information architecture:

```text
/categories/[slug]
    canonical category-resource landing page

/products
    canonical generic product collection
```

Do not make filtered `/products?category=...` compete with category-resource pages.

---

# 72. Search vs Product Listing

Maintain:

```text
/search
```

as internal discovery.

Do not redirect search queries to `/products`.

Do not merge the routes merely for SEO.

---

# 73. Not-Found Robots

Inspect actual Next.js behavior for `notFound()` in the installed version.

Do not manually duplicate framework-provided noindex behavior unless necessary and tested.

The existing canonical not-found architecture must remain intact.

---

# 74. Error Page Metadata

Do not spend Phase 14.7 creating elaborate SEO metadata for transient error boundaries.

Unexpected failures should not become indexable content identities.

Keep scope focused on successful public routes and established not-found semantics.

---

# 75. Structured Data Boundary — Critical

Do NOT add:

```text
application/ld+json
Product JSON-LD
BreadcrumbList
Organization
WebSite
SearchAction
ItemList
Offer
```

in Phase 14.7.

Phase 14.8 owns structured data.

Even though CAT-002 was explicitly designed to support Product JSON-LD later, do not implement it early.

---

# 76. Sitemap Boundary

Do NOT add:

```text
app/sitemap.ts
sitemap.xml
```

Phase 14.9 owns sitemap/robots.

---

# 77. Robots.txt Boundary

Do NOT add:

```text
app/robots.ts
robots.txt
```

Phase 14.9 owns site-level crawler directives.

Page-level `robots` metadata is allowed and required where appropriate in Phase 14.7.

---

# 78. Internal Linking Boundary

Do not redesign breadcrumbs/navigation/product links for SEO.

Phase 14.10 owns comprehensive internal-linking work.

Existing links must simply continue working.

---

# 79. Performance Boundary

Do not redesign image loading/caching/fetch strategy merely for metadata.

Phase 14.11 owns comprehensive performance optimization.

Avoid obvious duplicate API fetches, but do not turn Phase 14.7 into a caching project.

---

# 80. No New Dependency

Expected:

```text
new dependencies: NONE
```

Next.js already supplies the metadata API.

Do not add:

```text
next-seo
react-helmet
SEO libraries
slug libraries
schema libraries
```

---

# 81. Metadata Helper Architecture

Create shared helpers only for genuinely repeated stable responsibilities, for example:

```text
site identity/config
canonical URL construction
description normalization
social-image mapping
```

Do not create an enormous generic:

```text
SeoManager
SeoEngine
MetadataFactory with 30 flags
```

Prefer small typed functions.

---

# 82. No Page-Specific Boolean Soup

Avoid APIs such as:

```text
buildMetadata({
  isProduct: true,
  isCategory: false,
  isSearch: false,
  useImage: true,
  noIndex: false,
  ...
})
```

Use clear page/resource-specific composition.

---

# 83. Type Safety

Use Next.js:

```text
Metadata
ResolvingMetadata
```

types where appropriate.

Do not use:

```text
any
```

to bypass metadata typing.

---

# 84. Canonical URL Encoding

Use standard URL construction.

Product/category slugs come from backend authority.

Search/query values must be encoded correctly.

Do not concatenate unescaped user input into canonical/social URLs.

---

# 85. Trailing Slash

Canonical URLs must follow existing routing policy:

```text
no trailing slash
```

except root `/`.

ROUTING.md already establishes slashless canonical paths.

---

# 86. Page-One Canonical

Do not emit:

```text
/products?page=1
```

as canonical.

Use:

```text
/products
```

Phase 14.6 already established clean page-one URLs.

---

# 87. Sort Canonical

Do not make:

```text
/products?sort=price&sort_direction=asc
```

a separate indexable canonical collection.

Sorting is presentation order.

---

# 88. Search Canonical/Robots Test

Test at least:

```text
/search
/search?search=
/search?search=chair
/search?search=chair&page=2
/search?search=chair&product_type=MADE_TO_ORDER
```

All must follow the deliberate search noindex policy.

---

# 89. Product Collection Metadata Tests

Test:

```text
/products
/products?page=2
/products?sort=price&sort_direction=asc
/products?category=living-room
/products?product_type=MADE_TO_ORDER
/products?availability=available
/products?min_price=...
/products?<multiple facets>
```

Verify canonical and robots behavior according to this phase's policy.

---

# 90. Product Detail Tests

Test metadata derived from an authoritative fixture/resource containing:

```text
name
slug
description
primary image
image alt
```

Verify:

```text
title
description
canonical
Open Graph URL
Open Graph image
Twitter metadata
```

Do not assert invented fields.

---

# 91. Missing Product Test

Verify missing product still produces:

```text
HTTP 404
canonical not-found UI
non-indexable framework behavior
```

and does not emit a normal product canonical.

---

# 92. Category Tests

Test:

```text
category name
backend slug
description
image when present
canonical
Open Graph metadata
```

No local slug generation.

---

# 93. Missing Category Test

Verify:

```text
HTTP 404
```

remains intact.

---

# 94. Homepage Test

Verify:

```text
unique title
description
canonical /
Open Graph site identity
```

and no fixture-only production metadata.

---

# 95. HTML Runtime Verification

Do not rely only on TypeScript object tests.

Run the production Next.js server and inspect rendered HTML/metadata for representative routes.

Verify actual output contains the expected:

```text
<title>
meta description
canonical link
robots meta where applicable
og:title
og:description
og:url
og:image when valid
twitter card metadata
```

Use actual generated HTML as evidence.

---

# 96. Duplicate Tag Audit

Verify no accidental duplicate:

```text
title
description
canonical
robots
og:url
```

is emitted through competing metadata implementations.

---

# 97. Absolute URL Audit

Where absolute URLs are required, verify they resolve against the configured website origin.

No canonical URL may point to:

```text
127.0.0.1
localhost
Laravel API origin
R2 origin as page URL
```

in production configuration.

Media URLs may of course use the authoritative CDN/R2 origin supplied by the API.

---

# 98. Social Preview Image Audit

Verify metadata never points to:

```text
missing fixture files
localhost-only fixture media
unconfigured internal storage keys
private media
```

in production/API mode.

---

# 99. Test Infrastructure

Use the remediated frontend test infrastructure:

```text
node:test
+
tsx
```

where TypeScript behavioral tests are required.

Do NOT recreate:

```text
load-ts.mjs
node:vm
eval
new Function
SourceTextModule
custom runtime TypeScript execution
```

---

# 100. Focused Test Command

Add one focused Phase 14.7 suite following current conventions, preferably:

```text
npm run test:seo
```

Do not add a new testing framework.

---

# 101. Existing Regression Suites

Run all relevant frontend regressions, including:

```text
npm run test:seo
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

Use actual available script names if Phase 14.6 chose a different focused suite name.

---

# 102. Static Verification

Must pass:

```text
npm run typecheck
npm run lint
npm run build
git diff --check
```

---

# 103. Backend Regression

No Laravel implementation change is expected.

You may use existing CAT-002/CAT-004 tests as evidence that the resource fields consumed by metadata remain contract-correct.

Do not modify backend behavior for SEO.

---

# 104. Browser/HTTP Verification

Verify representative production-build routes with Chrome/curl or equivalent:

```text
/
 /products
 /products/[fixture-or-real-slug]
 /categories/[fixture-or-real-slug]
 /search
 /search?search=chair
```

If the local Laravel database still has no catalog records, use explicit fixture mode for populated metadata verification and clearly distinguish it from API runtime evidence.

Do not create production database records solely for this test.

---

# 105. Source-of-Truth Audit

For every metadata value, identify its source:

```text
Site name:
repository brand authority

Product title:
CAT-002 product.name

Product description:
CAT-002 product.description / documented factual fallback

Product canonical slug:
CAT-002 product.slug

Product image:
authoritative product media

Category title:
CAT-004 category.name

Category description:
CAT-004 category.description / documented factual fallback

Category canonical slug:
CAT-004 category.slug

Search term:
validated URL search state

Canonical origin:
server-side site-origin configuration
```

No invented database or marketing data.

---

# 106. Metadata vs Visible Content

Metadata should accurately describe the page customers actually receive.

Do not create an SEO-only hidden narrative that differs materially from visible content.

No cloaking-like behavior.

---

# 107. Accessibility

Metadata work should not alter visible heading semantics.

Do not change H1 text merely to satisfy title-tag preferences unless a genuine content defect exists.

Metadata title and visible H1 may differ modestly because they serve different contexts.

---

# 108. Design System

Expected visual changes:

```text
NONE
```

SEO metadata should not require new colors, typography, spacing, components, or layout.

Do not touch frozen design tokens.

---

# 109. Sticky Navigation Regression

The recently added sticky category navigation must remain unchanged unless metadata work reveals an unrelated compile/test issue.

Do not combine shell redesign with SEO metadata.

---

# 110. Phase 14.6 Regression

Do not change:

```text
filter vocabulary
sort vocabulary
price conversion architecture
search preservation
pagination behavior
```

SEO logic may read owned query state but must not rewrite Phase 14.6 behavior.

---

# 111. Price URL Contract

Canonical/facet logic must understand that:

```text
min_price
max_price
```

contain integer TZS minor units.

Do not reinterpret them as customer-facing TZS.

Do not modify the approved narrow JavaScript conversion boundary.

---

# 112. Search Preservation Regression

The valid code-review finding already fixed in Phase 14.6 must remain fixed:

```text
ProductCollectionControls preserves existing search state
```

Metadata work must not alter this.

---

# 113. Documentation

Update:

```text
phases/group-N-phases.md
```

with Phase 14.7 execution evidence.

Update `ROUTING.md` only if a durable canonical/indexing rule genuinely belongs there.

If you document the SEO policy, clearly distinguish:

```text
canonical route identity
indexability
query-state behavior
```

Do not rewrite the frozen routing contract.

---

# 114. ADR Policy

Expected:

```text
ADR: NONE
```

Normal Next.js metadata implementation does not require an ADR.

If introducing a durable site-origin configuration is merely framework configuration, document it with the frontend environment/config contract rather than creating an architecture decision.

If a genuinely new cross-application SEO architecture decision is required:

```text
STOP
```

and report it before inventing one.

---

# 115. Files Expected to Change

Likely areas include:

```text
frontend/web/app/layout.tsx
frontend/web/app/page.tsx
frontend/web/app/products/page.tsx
frontend/web/app/products/[slug]/page.tsx
frontend/web/app/categories/[slug]/page.tsx
frontend/web/app/search/page.tsx

frontend/web/lib/...metadata helpers if justified

frontend/web/package.json
  only for test script, not dependency

frontend/web/ROUTING.md
  only if durable SEO routing policy requires it

phases/group-N-phases.md
```

Actual repository architecture wins.

Do not create files merely because they appear in this list.

---

# 116. Completion Report

Return:

```text
PHASE 14.7 — SEO METADATA

Status:
PASS / BLOCKED


SITE IDENTITY

Site name:
<value>

Root title default:
<value>

Title template:
<value>

Default description:
<value/source>

Production website origin:
<configured value / REQUIRED NOT CONFIGURED>

Origin source:
<environment/config>

API_BASE_URL used for canonical origin:
NO / FAIL


HOMEPAGE

Title:
<value>

Description:
<summary>

Canonical:
<value>

Indexable:
YES / NO

Open Graph:
PASS / FAIL

Twitter:
PASS / FAIL


PRODUCT LISTING

Base title:
<value>

Base canonical:
<value>

Base indexable:
YES / NO

Page > 1 canonical policy:
<policy>

Filtered collection indexable:
NO / FAIL

Sorted collection indexable:
NO / FAIL

Price facet indexable:
NO / FAIL

Availability facet indexable:
NO / FAIL

Product-type facet indexable:
NO / FAIL


PRODUCT DETAIL

Metadata source:
CAT-002 / FAIL

Title source:
<field>

Description source:
<field/fallback>

Canonical slug source:
BACKEND / FAIL

Canonical:
<pattern>

Primary social image:
<source>

Fixture production fallback:
NONE / FAIL

Missing product:
HTTP 404 / FAIL


CATEGORY

Metadata source:
CAT-004 / FAIL

Title source:
<field>

Description source:
<field/fallback>

Canonical slug source:
BACKEND / FAIL

Canonical:
<pattern>

Category image:
<behavior>

Filtered /products category competing canonical:
NO / FAIL

Missing category:
HTTP 404 / FAIL


SEARCH

Indexable:
NO / FAIL

Robots:
noindex, follow / <actual>

Search title:
<behavior>

Search term escaped safely:
PASS / FAIL

Arbitrary search landing pages created:
NONE / FAIL


CANONICALS

Builder:
<path/responsibility>

Website origin:
PASS / FAIL

Trailing slash policy:
PASS / FAIL

page=1 removed:
PASS / FAIL

Product uses returned slug:
PASS / FAIL

Category uses returned slug:
PASS / FAIL

API origin used:
NO / FAIL

Host header trusted:
NO / <details>


SOCIAL METADATA

Open Graph site name:
PASS / FAIL

Open Graph URLs:
PASS / FAIL

Product image:
PASS / FAIL

Product image alt:
PASS / FAIL

Category image:
PASS / FAIL / N/A

Twitter card:
PASS / FAIL

Invented social handles:
NONE / FAIL

Invented image dimensions:
NONE / FAIL


DATA SAFETY

Private data in metadata:
NONE / FAIL

Internal IDs exposed unnecessarily:
NONE / FAIL

API error details exposed:
NONE / FAIL

Fixture fallback in production:
NONE / FAIL

Marketing claims invented:
NONE / FAIL


SERVER ARCHITECTURE

Metadata server-rendered:
YES / FAIL

New client components:
NONE / FAIL

Duplicate CAT-002 request:
NONE / <justification>

Duplicate CAT-004 request:
NONE / <justification>

Generic API client modified for SEO:
NO / FAIL


BOUNDARIES

JSON-LD:
NONE

Structured data:
NONE

sitemap.ts:
NONE

robots.ts:
NONE

New internal-linking architecture:
NONE

Performance phase work:
NONE

Backend changed:
NO

Flutter changed:
NO

Design system changed:
NO

Dependencies added:
NONE


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

test:seo:
PASS / FAIL

Phase 14.6 filter suite:
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


RUNTIME HTML

Homepage metadata:
PASS / FAIL

Products metadata:
PASS / FAIL

PDP metadata:
PASS / FAIL / DATA NOT AVAILABLE

Category metadata:
PASS / FAIL / DATA NOT AVAILABLE

Search noindex:
PASS / FAIL

Filtered collection noindex:
PASS / FAIL

Duplicate title tags:
NONE / FAIL

Duplicate canonical tags:
NONE / FAIL

Production localhost canonicals:
NONE / FAIL


DOCUMENTATION

Group N:
UPDATED / FAIL

ROUTING.md:
UPDATED / UNCHANGED

Environment/config documentation:
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


RESULT

Phase 14.7:
PASS / BLOCKED

Phase 14.8:
READY / BLOCKED
```

---

# 117. STOP Condition

Phase 14.7 may be declared PASS only when:

- one canonical Next.js metadata architecture exists;
- the site identity is consistently `SL Furnitures`;
- a production website origin is not guessed;
- canonical URLs use website origin, never `API_BASE_URL`;
- homepage has unique factual metadata;
- `/products` has unique collection metadata;
- product metadata derives from CAT-002;
- product canonical uses the backend-returned slug;
- category metadata derives from CAT-004;
- category canonical uses the backend-returned slug;
- missing products/categories retain hard HTTP 404 behavior;
- search pages are `noindex, follow`;
- arbitrary filtered/sorted/price/availability/product-type collection combinations are not turned into indexable SEO landing pages;
- `/products?category=...` does not compete with `/categories/[slug]`;
- meaningful plain pagination is not blindly canonicalized to page 1;
- `page=1` does not create a duplicate canonical;
- Open Graph metadata is factual;
- Twitter metadata is factual;
- authoritative catalog imagery is reused where suitable;
- no fixture media leaks into production metadata;
- no social accounts are invented;
- no image dimensions are invented;
- no marketing claims are invented;
- no private data enters metadata;
- metadata remains server-rendered;
- no unnecessary client component is introduced;
- no SEO concepts leak into the generic API transport;
- no new dependency is added;
- Phase 14.6 query/filter behavior remains unchanged;
- sticky navigation remains unaffected;
- no JSON-LD is implemented;
- no sitemap is implemented;
- no `robots.txt` implementation is added;
- no Phase 14.10 internal-linking work is introduced;
- no Phase 14.11 performance project is introduced;
- focused metadata tests pass;
- runtime HTML metadata is inspected, not merely TypeScript objects;
- all relevant regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 14.7 — PASS
Phase 14.8 — READY
```

Do not start Phase 14.8 automatically.