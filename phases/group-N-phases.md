# PHASE 14.8 — STRUCTURED DATA

## ENTRY STATE

Phase 14.1 — Homepage — PASS
Phase 14.2 — Category Pages — PASS
Phase 14.3 — Product Listing — PASS
Phase 14.4 — Product Detail — PASS
Phase 14.5 — Search — PASS
Phase 14.6 — Filters & Sorting — PASS
Phase 14.7 — SEO Metadata — PASS
Phase 14.8 — Structured Data — ACTIVE

Do not start Phase 14.9 automatically.


# 1. OBJECTIVE

Implement truthful, server-rendered Schema.org structured data for the existing public SL Furnitures website.

Primary targets:

- WebSite
- Organization, but only with repository-authoritative facts
- BreadcrumbList where a real breadcrumb hierarchy exists
- Product on canonical product-detail pages

Use JSON-LD.

The implementation must describe the content and commercial capabilities that actually exist.

Do not optimize for Rich Results Test scores by inventing data.

Do not claim merchant-listing eligibility merely because the API contains price and availability.


# 2. READ BEFORE CODING

Read and follow:

```
AGENTS.md
frontend/AGENTS.md

frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md

frontend/design-system/DESIGN.md
frontend/design-system/ACCESSIBILITY.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md

phases/group-N-phases.md

Then inspect the actual Phase 14.1–14.7 implementation, especially:

frontend/web/app/layout.tsx
frontend/web/app/page.tsx
frontend/web/app/products/page.tsx
frontend/web/app/products/[slug]/page.tsx
frontend/web/app/categories/[slug]/page.tsx
frontend/web/app/search/page.tsx

frontend/web/lib/seo/site.ts
all Phase 14.7 SEO helpers
catalog/Product detail data helpers
category data helpers
breadcrumb components/data
price/money helpers
media-selection helpers
fixture-mode helpers
proxy.ts
```

Inspect package.json and current test architecture.

Repository reality wins over this prompt when filenames differ.


# 3. EXTERNAL SPECIFICATION CHECK

Before implementing JSON-LD, consult current official documentation for:

- Schema.org Product
- Schema.org Offer
- Schema.org ItemAvailability
- Schema.org BreadcrumbList
- Schema.org Organization/WebSite
- Google Product structured data
- Google Breadcrumb structured data
- Google Organization structured data

Use official Schema.org and Google Search Central sources.

Do not copy an old ecommerce JSON-LD tutorial blindly.

Structured-data search-engine requirements evolve independently of the project's frozen API.


# 4. GIT POLICY

Before ANY Git command:

locate/read/follow:
```
git-workflow-and-versioning
```
Preserve unrelated owner changes.

Never commit:
```
.env
.env.local
secrets
temporary runtime output
```
Use an atomic Phase 14.8 commit.

Report exact Git operations, commit hash/message and push result.


# 5. EXISTING SEO ARCHITECTURE IS AUTHORITATIVE

Phase 14.7 established:

- SL Furnitures site identity
- SITE_URL as server-side website-origin authority
- canonical URL construction
- canonical slug behavior
- CAT-002/CAT-004 React cache() reuse
- metadata media selection
- production-vs-fixture boundaries

REUSE those responsibilities.

Do not create:

STRUCTURED_DATA_SITE_URL
SCHEMA_SITE_URL
PUBLIC_SITE_URL
another canonical builder
another product media selector
another category resolver
another CAT-002 fetch path

Structured data and metadata must describe the same resource identity.


# 6. SITE_URL REQUIREMENT

Absolute structured-data URLs require the canonical public website origin.

Use the existing Phase 14.7 SITE_URL boundary.

Do not:

- use API_BASE_URL
- use the R2/CDN origin as the page origin
- trust arbitrary Host/X-Forwarded-Host values
- guess the production domain
- silently use localhost in production

Phase 14.7 established that production SITE_URL is currently REQUIRED / NOT CONFIGURED.

Preserve that behavior.

If SITE_URL is unavailable, do not fabricate absolute canonical structured-data URLs merely to make validation pass.

Report the resulting behavior explicitly.


# 7. JSON-LD DELIVERY

Structured data must be emitted in the initial server-rendered HTML using:
```
<script type="application/ld+json">
```
Do not fetch or inject it after hydration.

Expected new client components:

NONE

Do not use:

useEffect
browser-only JSON-LD generation
react-helmet
next-seo
third-party schema packages


8. SAFE JSON SERIALIZATION — SECURITY REQUIREMENT

Do not interpolate raw API/user strings into hand-written JSON.

Build typed JavaScript/TypeScript objects and serialize them.

The JSON embedded inside a script element must be safe against script termination/injection.

At minimum, ensure characters capable of breaking out of the script context are escaped appropriately, including "<".

Use a small centralized JSON-LD serialization/rendering boundary.

Do not use raw API descriptions inside hand-built:

`{ "@type": "...", "description": "${product.description}" }`

strings.

Add an explicit regression test using hostile content such as:

</script><script>alert(1)</script>

The generated HTML/JSON-LD must not create a second executable script.


9. NO NEW DEPENDENCY

Expected:

new dependencies: NONE

Do not install a schema library merely for object construction.

Use typed local structures sufficient for this phase.


10. GRAPH IDENTITY

Use stable absolute `@id` values where useful to connect entities.

Conceptually:

SITE_URL/#website
SITE_URL/#organization
SITE_URL/products/{slug}#product

Do not expose Laravel opaque IDs merely to create Schema.org identifiers.

Public canonical URLs are the identity boundary.


11. HOMEPAGE STRUCTURED DATA

The homepage may establish the site-level entities:

WebSite
Organization

Do not emit product/catalog ItemLists on the homepage merely because products appear there.

Keep the graph small and factual.


12. WEBSITE ENTITY

Create a WebSite entity using authoritative facts only.

Expected concepts:

@context: https://schema.org
@type: WebSite
@id: {SITE_URL}/#website
url: {SITE_URL}/
name: SL Furnitures

Do not invent:

alternateName
publisher details not established
copyright holder details
languages not established
search actions merely for completeness


13. SEARCHACTION — DO NOT ADD

Do NOT add SearchAction solely because `/search` exists.

Google retired the sitelinks search box feature.

Do not preserve obsolete SEO cargo cult behavior.

The website's ordinary search functionality remains unchanged.


14. ORGANIZATION ENTITY

Homepage may emit:

@type: Organization
@id: {SITE_URL}/#organization
name: SL Furnitures
url: {SITE_URL}/

Only add additional properties when repository authority supports them.

Do NOT invent:

legalName
telephone
email
streetAddress
postalCode
foundingDate
founder
sameAs
social profiles
tax IDs
registration IDs
numberOfEmployees
areaServed
award
slogan


15. ORGANIZATION TYPE

Do not upgrade the entity to:

LocalBusiness
FurnitureStore
OnlineStore

without checking whether the repository establishes the facts required for that interpretation.

The initial request-first website does not automatically make the business an online transactional store.

A conservative truthful Organization is preferable to an over-specific false type.


16. ORGANIZATION LOGO

The official logo authority is:

designs/brandlogo.png

and the served website copy already exists.

However, do not automatically emit it as Organization.logo.

First verify:

- it is served through a stable production URL;
- SITE_URL can produce an absolute crawlable URL;
- its dimensions/content satisfy the current relevant guidance;
- the exact asset is the official unchanged logo.

Do not resize/recolor/redraw the logo for this phase.

If the existing asset is unsuitable for Organization.logo:

omit logo

and report why.

Do not generate a new logo.


17. NO INVENTED BUSINESS DETAILS

A minimal truthful Organization object is valid.

Do not treat Google recommendations as permission to fabricate:

address
telephone
email
return policy
shipping policy

Missing business data must remain missing.


18. PRODUCT STRUCTURED DATA SCOPE

Emit Product JSON-LD only on:

/products/[slug]

Do NOT put Product schema on:

/products
/categories/[slug]
/search
homepage product-card grids

Google's product guidance focuses Product rich-result markup on pages representing a specific product.

The canonical PDP is the product leaf resource.


19. PRODUCT DATA AUTHORITY

Use CAT-002.

The frozen contract explicitly states CAT-002 contains the public fields required for Schema.org Product JSON-LD.

Relevant authoritative fields include:

name
slug
description
price
product_type
category
images[]
variants[]
availability
stock_indicator

Do not query the database directly.

Do not add backend fields just for structured data.


20. PRODUCT IDENTITY

Product structured-data URL:

canonical /products/{backend-returned-slug}

Do not use:

product.id

as the public URL.

Do not locally slugify product.name.

Use the same canonical builder from Phase 14.7.


21. PRODUCT NAME

Map:

CAT-002 product.name
→ Product.name

No keyword stuffing.

Do not append:

"Buy Online"
"Best Furniture Tanzania"
"Cheap Furniture"

unless such text is actually the product's authoritative name, which normally it must not be.


22. PRODUCT DESCRIPTION

Map the same authoritative product description used by the PDP/metadata.

Do not create a special SEO description containing invented claims.

If description is absent, omit the Schema.org property rather than inventing prose unless the existing canonical factual fallback is explicitly appropriate.


23. PRODUCT IMAGE

Use authoritative CAT-002 public media URLs.

Prefer the same ordered image/media authority already used by the PDP and Phase 14.7.

Product.image may contain legitimate product image URLs.

Do not:

- use unrelated fixture media in production
- use category imagery
- use another product's image
- invent alternate aspect-ratio URLs
- invent image dimensions


24. PRODUCT CATEGORY

If CAT-002 exposes a truthful public category name suitable for Schema.org Product.category, it may be included.

Do not serialize internal category IDs as customer-facing category labels.


25. BRAND

Do NOT automatically set:

brand: SL Furnitures

unless the repository establishes that SL Furnitures is actually the product brand/manufacturer.

Selling a product does not prove that the merchant is its brand.

If product-level brand is absent from the API contract:

omit Product.brand.

Do not add a backend field during Phase 14.8.


26. SKU

Do not use:

product.id
product.slug

as a fake SKU.

CAT-002 variants contain SKU values, but the parent Product contract does not necessarily define one canonical product SKU.

Do not arbitrarily choose the first variant SKU as Product.sku.

Variant modelling is addressed separately below.


27. GTIN / MPN

Do not invent:

gtin
gtin8
gtin12
gtin13
gtin14
mpn

If the frozen contract does not expose them, omit them.


28. REVIEWS / RATINGS

Do NOT add:

Review
AggregateRating
ratingValue
reviewCount

unless the actual public product page exposes authoritative review/rating data from an implemented backend contract.

Never fabricate:

4.8 stars
100 reviews
"5-star customer rating"

to satisfy a rich-results validator.


29. PRODUCT PRICE — MONEY CONVERSION

The API uses:

{ amount: integer minor units, currency: "TZS" }

Project convention:

1 TZS = 100 API minor units

Structured data price must represent the customer-facing major-unit TZS value.

Therefore reuse or centralize the existing authoritative money conversion.

Do not serialize API minor units directly as TZS.

Example concept only:

API amount:
45000000

Customer TZS:
450000

Schema price:
450000
or an equivalent valid decimal representation

Do not use locale-formatted:

"450,000 TZS"

as Schema.org numeric price.


30. PRICE CURRENCY

Use the API currency authority.

Expected V1:

TZS

Do not hard-code USD.

Do not infer currency from user locale.


31. OFFER SEMANTICS — CRITICAL

Do not assume every Product must have an Offer solely to satisfy Google rich-result eligibility.

First determine whether the visible canonical PDP truthfully presents the product as an actual current commercial offer.

The production release is request-first.

There is no active cart/checkout/payment purchase journey.

Structured data must not imply capabilities the visible page does not have.


32. GOOGLE MERCHANT LISTING BOUNDARY

Do NOT implement structured data specifically claiming or targeting merchant-listing eligibility while online purchase is unavailable.

Do not add fake:

shippingDetails
hasMerchantReturnPolicy
priceValidUntil
seller policies
checkout URLs

to satisfy merchant-listing validation.

The implementation may describe a Product without claiming merchant-listing eligibility.


33. OFFER DECISION GATE

Before adding Product.offers, inspect:

- current visible PDP semantics;
- request-first business policy;
- CAT-002 price meaning;
- whether the business is genuinely offering that product at the displayed price even though fulfillment begins through a request/contact workflow;
- current Schema.org semantics;
- current Google Product guidance.

Then record one of:

A. OFFER SEMANTICALLY VALID
   Add a minimal truthful Offer.

B. OFFER NOT JUSTIFIED
   Omit offers.

Do not choose A merely because Google prefers an Offer.


34. IF OFFER IS JUSTIFIED

Use only authoritative properties.

Potentially:

@type: Offer
url: canonical PDP URL
price: converted authoritative price
priceCurrency: authoritative currency
availability: mapped authoritative availability

Do not add fields unsupported by the project.


35. IF OFFER IS NOT JUSTIFIED

Emit truthful Product JSON-LD without Offer.

Accept that this may not qualify for Google's Product rich-result enhancement.

Schema correctness and truthful representation outrank rich-result eligibility.

Report:

Google Product rich-result eligibility:
NOT CLAIMED / INCOMPLETE BY DESIGN

Do not manufacture review/rating data as an alternative.


36. AVAILABILITY MAPPING

The backend has two relevant public concepts:

availability:
available | unavailable

stock_indicator:
IN_STOCK | LOW_STOCK | MADE_TO_ORDER

The mapping must preserve those semantics.


37. IN-STOCK PRODUCTS

For product_type IN_STOCK:

availability = available
→ https://schema.org/InStock

availability = unavailable
→ https://schema.org/OutOfStock

Do not use stock_indicator alone to determine actual availability.

The frozen API explicitly says `availability` is authoritative when an IN_STOCK product has zero available units.


38. LOW_STOCK

LOW_STOCK with:

availability = available

remains truthfully purchasable/available.

Do not mark it OutOfStock.

If current official Schema.org/Google guidance supports LimitedAvailability and the mapping is semantically appropriate, evaluate it deliberately.

Do not introduce it merely because the enum name says LOW_STOCK.

Document the chosen mapping.


39. MADE_TO_ORDER

MADE_TO_ORDER is a first-class valid offering.

It is not:

OutOfStock
Discontinued
PreOrder

Schema.org currently defines MadeToOrder as an ItemAvailability member.

If structured-data availability is emitted for MTO, prefer truthful Schema.org semantics:

https://schema.org/MadeToOrder

Do not map it to PreOrder merely to satisfy a search-engine supported-value list.

If current Google Product rich-result validation does not recognize MadeToOrder, document that compatibility limitation rather than lying.


40. VARIANT STRUCTURED DATA — DO NOT OVERMODEL

CAT-002 embeds variants with:

id
sku
name
price
availability
stock_indicator

But the current website uses one canonical parent product URL.

Do not automatically implement:

ProductGroup
hasVariant
isVariantOf
variant-specific Product graphs

merely because variants exist.

Current Google variant markup has additional identity/URL expectations.

Phase 14.8 should use the simplest truthful representation supported by the current route/data architecture.


41. VARIANT DECISION

Unless the existing PDP exposes independently addressable canonical variant identities satisfying current Product variant structured-data guidance:

do not implement ProductGroup/variant Product markup.

Do not invent:

?variant=
variant routes
variant canonicals
variant IDs in public URLs

to improve structured data.


42. BREADCRUMBLIST

Implement BreadcrumbList only where the page already has a meaningful navigational hierarchy.

Primary candidates:

/categories/[slug]
/products/[slug]

Structured data must correspond to real navigation/content relationships.


43. BREADCRUMB AUTHORITY

Reuse the actual breadcrumb data/model already rendered by the page.

Do not create one breadcrumb hierarchy for visible UI and a different hidden hierarchy for search engines.

If the visible PDP currently shows:

Home → Products → Product Name

structured data should describe that same hierarchy.

If category ancestry is visibly represented and authoritative, use it consistently.


44. CATEGORY HIERARCHY

The backend supports a multi-level category taxonomy.

If category detail data available to the page does not provide enough authoritative ancestry to construct a complete parent chain:

do not query or fabricate ancestry solely to create a richer BreadcrumbList.

Use only the hierarchy actually known and represented.

Do not infer parent categories from slug/name conventions.


45. BREADCRUMB URLS

Breadcrumb item URLs must use:

SITE_URL
+
canonical route paths

Examples conceptually:

/
 /products
 /products/{slug}
 /categories/{slug}

Do not use API URLs.


46. BREADCRUMB POSITIONS

ListItem.position must be deterministic and one-based:

1
2
3
...

No duplicates.

No gaps.


47. CURRENT PAGE BREADCRUMB

Follow current official Schema.org/Google guidance for the terminal breadcrumb item.

Do not invent a destination different from the canonical page.

Visible and structured breadcrumb names must agree semantically.


48. PRODUCT LISTING STRUCTURED DATA

Do not add Product JSON-LD for every card on `/products`.

Do not emit dozens of hidden Product objects from the listing page.

Phase 14.8 does not need to turn catalog listings into a massive ItemList graph.


49. CATEGORY STRUCTURED DATA

Do not invent a Category schema type.

Schema.org does not have a reason for us to fabricate a custom "FurnitureCategory" entity.

For category pages, BreadcrumbList is sufficient unless an official, semantically justified schema type is identified during implementation.

Do not use Product for category pages.


50. SEARCH STRUCTURED DATA

Do not add Product or ItemList structured data to:

/search

Search is already noindex, follow.

Do not emit SearchAction.

Do not create structured-data identities for arbitrary search terms.


51. WEBPAGE TYPES

Do not create a complex WebPage/CollectionPage graph merely because those Schema.org types exist.

Add only entities that have clear value and factual backing.

Prefer:

small
correct
maintainable

over:

large
clever
validator-maximal


52. ORGANIZATION DUPLICATION

Emit the site-level Organization/WebSite graph once at the appropriate stable site/homepage boundary.

Do not duplicate identical Organization JSON-LD in every product and category page unless there is a concrete semantic reason.

Use stable `@id` references where useful rather than repeated divergent organization definitions.


53. PRODUCT SELLER

If an Offer is used, do not automatically set:

seller: Organization

unless the business semantics establish SL Furnitures as the seller of the offer.

If established, reference the stable Organization `@id`.

Do not duplicate a second differently-shaped Organization object.


54. REQUEST-FIRST INVARIANT

Structured data must not imply:

Add to cart
Online checkout
Online payment
Instant purchase
Free delivery
Nationwide shipping
Same-day delivery
Returns policy

unless those capabilities become authoritative and visible.

No hidden ecommerce claims.


55. CATEGORY/PDP DATA FETCHING

Phase 14.7 already uses React cache() to deduplicate CAT-002/CAT-004 between metadata and pages.

Structured data must consume the already-resolved page resource where practical.

Do not introduce:

metadata CAT-002
+
page CAT-002
+
JSON-LD CAT-002

as three independent calls.


56. API CLIENT BOUNDARY

Do not add Schema.org concepts to:

lib/api/client.ts

The generic API client remains transport-only.

Structured-data mapping belongs to frontend SEO/domain presentation code.


57. HELPER ARCHITECTURE

Small typed helpers are acceptable, for example conceptually:

lib/seo/structured-data.ts

Possible responsibilities:

serializeJsonLdSafely()
buildWebsiteStructuredData()
buildOrganizationStructuredData()
buildProductStructuredData()
buildBreadcrumbStructuredData()
mapAvailability()

Actual repository architecture wins.

Do not build a giant generic schema engine.


58. JSON-LD COMPONENT

A tiny server-safe reusable renderer may be introduced if useful.

Example conceptual responsibility:

<JsonLd data={...} />

It must not become a design-system component.

It has no visual styling responsibility.

No client directive.


59. TYPE SAFETY

Avoid `any`.

Use narrow local TypeScript types for emitted structures.

Do not attempt to reproduce the entire Schema.org vocabulary in TypeScript.

Do not install a giant generated schema type package.


60. NULL/UNDEFINED PROPERTIES

Do not emit meaningless properties such as:

"description": null
"brand": ""
"image": []
"sku": null

Omit unsupported/absent fields.


61. NO PRIVATE DATA

Structured data is public HTML.

Never include:

customer information
staff information
enquiry data
request data
internal notes
storage keys
inventory quantities
reserved quantities
cost price
API request IDs
authorization data


62. INVENTORY PRIVACY

The public API intentionally exposes availability buckets, not warehouse counts.

Structured data may expose only public availability semantics.

Never serialize:

physical_quantity
reserved_quantity
warehouse stock
internal inventory location


63. FIXTURE MODE

Explicit fixture mode may generate structured data for deterministic development tests.

Production/API mode must never silently fall back to fixture structured data.

Fixture entities must never masquerade as production resources.


64. MISSING PRODUCT

A missing product must continue to produce the existing production/API hard HTTP 404.

Do not emit Product JSON-LD for a missing product.

Do not emit a fake canonical Product identity before CAT-002 succeeds.


65. MISSING CATEGORY

Likewise:

missing category
→ existing hard HTTP 404
→ no category breadcrumb graph pretending the resource exists


66. FIXTURE 404 CAVEAT

Phase 14.7 documented:

HOMEPAGE_DATA_SOURCE=fixtures
→ proxy intentionally skipped
→ missing slug can render not-found UI with HTTP 200 because of metadata streaming

Do not attempt to "fix" that development-only fixture behavior in Phase 14.8.

Production/API mode is the acceptance authority for hard 404 behavior.


67. ERROR SEMANTICS

Do not turn:

500
429
timeout
network error

into a structured-data fallback.

Unexpected API failure must retain the existing Phase 13.8 error architecture.


68. NO VISUAL REDESIGN

Expected visible UI changes:

NONE

Do not modify:

design tokens
MUI theme
ProductCard
product gallery styling
filters
sticky category navigation
page composition

unless a genuine defect blocks correct structured data.


69. ACCESSIBILITY

JSON-LD is machine-readable supplemental data.

It must not replace visible:

product names
prices
availability
breadcrumbs

Structured data must correspond to visible content.

Do not hide user-relevant content only in JSON-LD.


70. SEO METADATA REGRESSION

Phase 14.7 metadata must remain unchanged unless a demonstrated structured-data integration defect requires a narrow correction.

Preserve:

title template
descriptions
canonicals
robots policies
Open Graph
Twitter metadata
SITE_URL boundary
noindex filtered/search behavior


71. PHASE 14.6 REGRESSION

Do not change:

filters
sorting
search preservation
price form conversion
pagination
canonical query vocabulary

Structured data should not mutate URL behavior.


72. PHASE 14.9 BOUNDARY

Do NOT implement:

app/sitemap.ts
sitemap.xml
app/robots.ts
robots.txt

Phase 14.9 owns sitemap/robots.


73. PHASE 14.10 BOUNDARY

Do not redesign internal links or navigation for SEO.

Phase 14.10 owns comprehensive internal-linking work.


74. PHASE 14.11 BOUNDARY

Do not turn structured-data implementation into:

image optimization
cache redesign
ISR migration
bundle optimization
performance project

Phase 14.11 owns comprehensive image/performance hardening.


75. TEST: WEBSITE

Test WebSite JSON-LD:

- @context correct
- @type WebSite
- stable @id
- absolute SITE_URL
- name exactly SL Furnitures
- no SearchAction
- no API origin
- no localhost production fallback


76. TEST: ORGANIZATION

Test Organization:

- correct type
- stable @id
- name SL Furnitures
- website URL
- logo only if valid/authoritative
- no invented address
- no invented telephone
- no invented email
- no invented social profiles
- no invented legal details


77. TEST: PRODUCT

Use a representative CAT-002 fixture.

Verify mapping of:

name
description
canonical URL
image(s)
category if used
price if Offer is justified
currency if Offer is justified
availability if Offer is justified

Verify opaque product ID does not become the public URL.


78. TEST: MONEY

Explicitly test API minor-unit conversion.

Example:

amount = 45000000
currency = TZS

must not become:

price = 45000000 TZS

Expected customer-facing magnitude:

450000 TZS

Use the project's actual money helper/contract.


79. TEST: AVAILABILITY

If Offer availability is emitted, test at least:

IN_STOCK + available
IN_STOCK + unavailable
LOW_STOCK + available
MADE_TO_ORDER + available

Expected semantics must be documented.

Never map MADE_TO_ORDER to OutOfStock.


80. TEST: MALICIOUS DESCRIPTION

Test:

product.description =
</script><script>alert(1)</script>

and equivalent hostile strings.

Assert generated JSON-LD cannot terminate the original script element.

Also test:

<
>
&
quotes
Unicode

The structured data must remain valid JSON after decoding.


81. TEST: PRODUCT WITHOUT OPTIONAL FIELDS

Test missing/blank optional fields.

No:

null descriptions
fake brand
fake SKU
fake GTIN
fake ratings
fake image


82. TEST: BREADCRUMBS

Verify:

- correct @type BreadcrumbList
- correct ListItem positions
- canonical absolute URLs
- names match visible hierarchy
- backend slugs preserved
- no locally generated slugs
- no API URLs


83. TEST: CATEGORY

Verify category page receives only semantically justified structured data, primarily breadcrumb data.

Do not expect Product markup on category pages.


84. TEST: SEARCH

Verify `/search` does not emit:

Product
ProductGroup
ItemList product inventory
SearchAction

unless a separately justified specification explicitly requires something.

Expected:

no product structured-data graph.


85. TEST: LISTING

Verify `/products` does not emit one Product object per ProductCard.

No structured-data payload explosion.


86. TEST: MISSING RESOURCES

API mode:

missing PDP:
HTTP 404
Product JSON-LD absent

missing category:
HTTP 404
resource-specific JSON-LD absent

Preserve Phase 14.7 behavior.


87. RUNTIME HTML VERIFICATION

Run production Next.js build/server.

Inspect actual initial HTML.

Verify:

<script type="application/ld+json">

is present where intended.

Parse every JSON-LD block with JSON.parse.

Do not rely solely on helper unit tests.


88. VALIDATION AGAINST EXTERNAL TOOLS

Where network/tool availability permits, validate representative production-shaped JSON-LD against:

- Google Rich Results Test or equivalent official Google validation
- Schema.org validator

Important:

A warning that an optional property is absent is not permission to invent it.

A Google rich-result eligibility warning is not automatically a schema defect.

Record:

ERROR
WARNING
EXPECTED / NOT APPLICABLE

separately.


89. GOOGLE PRODUCT ELIGIBILITY REPORTING

Explicitly report:

Product schema valid:
YES/NO

Google Product rich-result eligibility:
ELIGIBLE / NOT CLAIMED / INCOMPLETE BY DESIGN

Google merchant-listing eligibility:
NOT CLAIMED

unless the actual production purchase model clearly satisfies current merchant-listing requirements.

Do not report PASS merely because a validator turns green.


90. FOCUSED TEST SUITE

Add a focused test following existing infrastructure:

npm run test:structured-data

Use the existing:

node:test + tsx

architecture.

Do not add a new test framework.


91. TEST INFRASTRUCTURE PROHIBITIONS

Do not recreate:

load-ts.mjs
node:vm
eval
new Function
SourceTextModule
custom TypeScript execution loaders


92. REGRESSION SUITES

Run all relevant existing suites, including actual available script names:

npm run test:structured-data
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

If a script has a different repository name, use that real name and report it.


93. STATIC VALIDATION

Must pass:

npm run typecheck
npm run lint
npm run build
git diff --check


94. BACKEND

Expected backend changes:

NONE

CAT-002/CAT-004 are already authoritative.

Do not modify the API merely to improve structured-data scores.


95. DOCUMENTATION

Update:

phases/group-N-phases.md

Record:

- structured-data entity policy
- Offer decision and rationale
- availability mapping
- variant decision
- Organization property authority
- SITE_URL dependency
- fixture boundary
- validation evidence

Update frontend documentation only if a durable implementation rule genuinely belongs there.


96. ADR POLICY

Expected:

ADR: NONE

Using JSON-LD for standard Schema.org representation is normal frontend implementation.

If implementation reveals a genuinely new cross-system business semantic decision, especially whether displayed request-first pricing constitutes a formal Offer and repository authority cannot resolve it:

STOP

Report the ambiguity.

Do not silently invent the business meaning.


97. COMPLETION REPORT

Return:

PHASE 14.8 — STRUCTURED DATA

Status:
PASS / BLOCKED


SITE ORIGIN

SITE_URL reused:
YES / FAIL

Production SITE_URL configured:
YES / REQUIRED NOT CONFIGURED

API_BASE_URL used:
NO / FAIL

Host header trusted:
NO / FAIL

Localhost production fallback:
NONE / FAIL


JSON-LD ARCHITECTURE

Renderer/helper:
<path>

Server-rendered:
YES / FAIL

New client components:
NONE / FAIL

Serialization injection-safe:
PASS / FAIL

New dependencies:
NONE / FAIL


WEBSITE

WebSite:
PASS / FAIL

@id:
<pattern>

Name:
SL Furnitures / FAIL

SearchAction:
NONE / FAIL


ORGANIZATION

Organization:
PASS / FAIL

@id:
<pattern>

Name:
SL Furnitures / FAIL

URL:
<source>

Logo:
<included + reason / omitted + reason>

Invented address:
NONE / FAIL

Invented telephone/email:
NONE / FAIL

Invented social profiles:
NONE / FAIL

Invented legal details:
NONE / FAIL


PRODUCT

Route:
products/[slug]

Source:
CAT-002 / FAIL

Product.name:
<source>

Product.description:
<source/omitted>

Product.url:
<pattern>

Product.image:
<source/omitted>

Product.category:
<source/omitted>

Product.brand:
<source/omitted>

Product.sku:
<source/omitted>

GTIN/MPN:
<source/omitted>

Ratings/reviews:
<source/omitted>

Opaque product ID used as public URL:
NO / FAIL


OFFER

Decision:
INCLUDED / OMITTED

Rationale:
<why>

Request-first semantics preserved:
YES / FAIL

Price source:
<field/N/A>

Minor-unit conversion:
PASS / N/A / FAIL

Currency:
<value/N/A>

Availability:
<behavior/N/A>

Merchant-listing eligibility claimed:
NO / FAIL

Fake checkout/shipping/returns claims:
NONE / FAIL


AVAILABILITY

IN_STOCK + available:
<mapping>

IN_STOCK + unavailable:
<mapping>

LOW_STOCK + available:
<mapping>

MADE_TO_ORDER:
<mapping>

MADE_TO_ORDER mapped to OutOfStock:
NO / FAIL

MADE_TO_ORDER mapped falsely to PreOrder:
NO / FAIL


VARIANTS

ProductGroup implemented:
YES / NO

Variant Product objects:
YES / NO

Decision rationale:
<text>

Invented variant URLs:
NONE / FAIL


BREADCRUMBS

BreadcrumbList:
PASS / FAIL

Product page:
PASS / N/A

Category page:
PASS / N/A

Uses visible hierarchy:
YES / FAIL

Backend slugs preserved:
YES / FAIL

Absolute website URLs:
YES / FAIL

API URLs:
NONE / FAIL


COLLECTION/SEARCH BOUNDARIES

Product objects on /products listing:
NONE / FAIL

Product objects on category listing:
NONE / FAIL

Product objects on /search:
NONE / FAIL

SearchAction:
NONE / FAIL

Invented category schema type:
NONE / FAIL


SECURITY

Script-breakout regression:
PASS / FAIL

Raw interpolation:
NONE / FAIL

Private data:
NONE / FAIL

Internal inventory quantities:
NONE / FAIL

API error details:
NONE / FAIL

Fixture production fallback:
NONE / FAIL


RESOURCE FAILURES

Missing PDP API mode:
HTTP 404 / FAIL

Product JSON-LD on missing PDP:
NONE / FAIL

Missing category API mode:
HTTP 404 / FAIL

Resource JSON-LD on missing category:
NONE / FAIL

Unexpected API failures masked as 404:
NO / FAIL


VALIDATION

test:structured-data:
PASS / FAIL

test:seo:
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


RUNTIME HTML

Homepage JSON-LD:
PASS / FAIL

PDP JSON-LD:
PASS / FAIL / DATA NOT AVAILABLE

Category BreadcrumbList:
PASS / FAIL / DATA NOT AVAILABLE

JSON.parse all emitted blocks:
PASS / FAIL

Duplicate site entities:
NONE / FAIL

Fixture leakage:
NONE / FAIL


EXTERNAL VALIDATION

Schema.org validation:
PASS / WARNINGS / NOT AVAILABLE

Google validation:
PASS / WARNINGS / NOT AVAILABLE

Product schema valid:
YES / NO

Google Product rich-result eligibility:
ELIGIBLE / NOT CLAIMED / INCOMPLETE BY DESIGN

Google merchant-listing eligibility:
NOT CLAIMED / <justification>


BOUNDARIES

SEO metadata changed:
NO / <narrow reason>

sitemap.ts:
NONE

robots.ts:
NONE

Internal-linking phase work:
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


DOCUMENTATION

Group N:
UPDATED / FAIL

Frontend docs:
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
<result/NONE>


RESULT

Phase 14.8:
PASS / BLOCKED

Phase 14.9:
READY / BLOCKED


98. STOP CONDITION

Phase 14.8 may be declared PASS only when:

- JSON-LD is server-rendered in initial HTML;
- JSON-LD serialization is safe against script breakout;
- WebSite identity is truthful;
- Organization contains only authoritative business facts;
- no SearchAction is added;
- SITE_URL from Phase 14.7 is reused;
- no production domain is guessed;
- API_BASE_URL is never used as page identity;
- Product JSON-LD appears only on canonical PDPs;
- Product data comes from CAT-002;
- product URL uses the backend-returned slug;
- product images come from authoritative public media;
- no fake brand/SKU/GTIN/MPN exists;
- no fake reviews or ratings exist;
- API money is never serialized as TZS without minor-unit conversion;
- Offer is included only if semantically justified by the actual request-first business model;
- merchant-listing eligibility is not falsely claimed;
- availability mapping preserves backend semantics;
- MADE_TO_ORDER is never represented as OutOfStock;
- MADE_TO_ORDER is not falsely represented as PreOrder;
- ProductGroup/variant markup is not invented without valid variant identities;
- BreadcrumbList reflects the visible authoritative hierarchy;
- no Product graph is dumped into listing/category/search pages;
- no private/internal inventory data is exposed;
- missing API resources retain hard production 404 behavior;
- no fixture data leaks into production structured data;
- no new client component is introduced;
- no new dependency is introduced;
- Phase 14.7 metadata/canonical behavior remains correct;
- Phase 14.6 filters/search/price conversion remain unchanged;
- no sitemap/robots implementation is started;
- no Phase 14.10 or 14.11 work leaks in;
- focused structured-data tests pass;
- malicious `</script>` content is regression-tested;
- runtime JSON-LD is parsed from actual production HTML;
- official validation is performed where available without inventing data to silence warnings;
- all relevant regressions pass;
- typecheck passes;
- lint passes;
- production build passes;
- git diff --check passes;
- Git operations follow git-workflow-and-versioning.

Then report exactly:

Phase 14.8 — PASS
Phase 14.9 — READY

Do not start Phase 14.9 automatically.