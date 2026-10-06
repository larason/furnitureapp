# Phase 13.6 — Website Routing Conventions

## Objective

Define and enforce the canonical **Next.js App Router routing conventions** for the SL Furnitures website before application layouts and storefront pages are implemented.

This phase determines:

- public URL structure;
- route naming;
- route ownership;
- static vs dynamic segments;
- canonical resource identifiers in URLs;
- route groups;
- search/filter query-parameter ownership;
- account/authenticated route namespace;
- made-to-order and enquiry route namespaces;
- URL normalization principles;
- Server Component defaults;
- route-level client-boundary rules;
- navigation/link conventions;
- reserved namespaces;
- future metadata ownership;
- boundaries between Next.js routing and Laravel `/api/v1`.

This phase must establish the **routing contract**, not implement the storefront.

Expected progression:

```text
13.5 API client
        ↓
13.6 routing conventions
        ↓
13.7 layout system
        ↓
13.8 error/loading/not-found handling
        ↓
13.9 responsive foundation
        ↓
Group N public catalog implementation
```

---

# 1. Scope

Implement only:

```text
Phase 13.6 — Routing conventions
```

Do NOT begin:

```text
13.7 — Layout system
13.8 — Error/loading/not-found handling
13.9 — Responsive foundation

14.1 — Homepage
14.2 — Category pages
14.3 — Product listing
14.4 — Product detail
14.5 — Search
14.6 — Filters/sorting
14.7 — SEO metadata
14.8 — Structured data
14.9 — Sitemap/robots
14.10 — Internal linking
14.11 — Image/performance optimization
```

A routing convention may reserve or document future URLs without implementing those pages.

---

# 2. Read Authorities First

Before changing anything, inspect:

```text
AGENTS.md
frontend/AGENTS.md

docs/
├── VISION.md
├── decisions.md
├── domain/business-rules.md
├── clerk-authentication-architecture.md
└── api/
    ├── api-contract.md
    ├── api-conventions.md
    ├── api-resources.md
    └── openapi.yaml

frontend/design-system/
├── DESIGN.md
├── COMPONENTS.md
├── ACCESSIBILITY.md
└── USAGE.md

frontend/web/
├── app/
├── lib/api/
├── package.json
├── tsconfig.json
└── next.config.*

phases/group-M-phases.md
```

Also inspect actual Laravel routes/resource identifiers where necessary.

Do not infer route identifiers from database fields if the public API contract already establishes them.

---

# 3. Git Workflow

Before ANY Git command:

```text
locate and read:
git-workflow-and-versioning
```

Follow that skill exactly.

Preserve unrelated owner changes.

Never commit secrets or local environment configuration.

Stage only Phase 13.6 work.

---

# 4. Existing Foundation Must Be Preserved

Phases 13.1–13.5 already established:

```text
Next.js App Router
strict TypeScript
MUI integration
token-backed theme
minimal provider boundary
generic Laravel API client
```

Do not recreate or refactor those foundations merely because routing is now being documented.

In particular:

```text
do not rerun create-next-app
do not reinstall MUI
do not recreate the theme
do not create another API client
do not introduce Pages Router
```

---

# 5. App Router Is Authoritative

The website uses:

```text
Next.js App Router
```

Do not introduce:

```text
pages/
getServerSideProps
getStaticProps
next/router
```

for new architecture.

Future navigation should use App Router APIs.

---

# 6. Routing Principle

URLs are part of the product contract.

They must be:

```text
human-readable
stable
predictable
shareable
crawlable where public
bookmarkable
independent of temporary UI composition
```

Do not derive routes from component names.

Bad conceptual examples:

```text
/product-page
/product-screen
/shop-component
/home-screen
/category-view
```

URLs describe resources and user intent.

---

# 7. Route Taxonomy

Establish a documented canonical taxonomy for the website.

At minimum evaluate and define namespaces for:

```text
/
products
categories
search
made-to-order / furniture requests
enquiries/contact
account
authentication entry points if website-owned
```

Only include namespaces supported by project requirements.

Do not invent unrelated sections such as:

```text
blog
community
marketplace
wishlist
designer portal
loyalty
```

unless already approved elsewhere.

---

# 8. Homepage

Reserve:

```text
/
```

for the public storefront homepage.

Do NOT implement Phase 14.1.

The existing minimal scaffold page may remain until Group N.

---

# 9. Product Listing

Establish the canonical product collection route.

Prefer a stable resource-oriented namespace such as:

```text
/products
```

unless repository requirements already establish another route.

This will eventually own the public product listing.

Do NOT build it now.

---

# 10. Product Detail

Establish the canonical dynamic product route.

Conceptually:

```text
/products/[identifier]
```

Determine the identifier from the actual API/public resource contract.

Possible examples might be:

```text
slug
public ID
```

but DO NOT guess.

Never expose an internal numeric database ID merely because it is easy.

---

# 11. Product Identifier Rule

Inspect the product API contract and model/resource behavior.

The routing document must explicitly state:

```text
what identifier appears in the URL
whether it is immutable/stable
whether it is human-readable
how Laravel resolves it
```

If the current frozen contract does not provide an appropriate public route identifier:

```text
STOP
```

and report the contract gap.

Do not silently invent a frontend slug system.

---

# 12. Categories

Establish the canonical category URL convention.

A likely resource shape is:

```text
/categories/[identifier]
```

but inspect the contract first.

Do not choose between:

```text
/categories/chairs
/category/chairs
/shop/chairs
/collections/chairs
```

based purely on aesthetic preference.

Use the project's actual taxonomy semantics.

---

# 13. Category Identifier

As with products, inspect whether category routing uses:

```text
slug
public identifier
other frozen API field
```

Do not expose internal numeric IDs without explicit contract support.

---

# 14. Category Hierarchy

The database taxonomy supports hierarchy.

Do NOT automatically encode the entire hierarchy into URLs such as:

```text
/categories/living-room/seating/armchairs
```

unless the public API/domain contract explicitly establishes hierarchical canonical URLs.

Prefer the simplest stable route identity.

Breadcrumb hierarchy and canonical URL hierarchy are separate concerns.

---

# 15. Product/Category Relationship

Do not create duplicate canonical product URLs such as:

```text
/products/oak-chair
/categories/chairs/oak-chair
/living-room/chairs/oak-chair
```

A product should have one canonical public URL.

Category context belongs in navigation/breadcrumb/query state unless later architecture deliberately specifies otherwise.

---

# 16. Search

Reserve a canonical search route.

Recommended shape if consistent with the project:

```text
/search
```

Search terms should generally be URL state, conceptually:

```text
/search?q=oak+table
```

rather than:

```text
/search/oak-table
```

unless the existing requirements specify otherwise.

Do NOT implement search functionality.

Phase 14.5 owns search.

---

# 17. Search Query Parameter

Define one canonical query parameter for the search term.

For example:

```text
q
```

Do not permit multiple equivalent conventions:

```text
q
query
search
keyword
term
```

The actual implementation comes later.

---

# 18. Filters

Phase 14.6 owns filters/sorting.

Phase 13.6 should only define the routing principle:

```text
filter state that users should share/bookmark
→ URL query parameters

ephemeral presentation state
→ local UI state
```

Do not implement filters.

---

# 19. Filter Parameter Stability

Future filter parameters must be:

```text
explicit
documented
stable
contract-like
```

Do not encode the entire filter state into an opaque JSON/base64 query parameter.

---

# 20. Sorting

Reserve a canonical sorting parameter convention if the API contract already establishes one.

For example conceptually:

```text
?sort=price_asc
```

but use actual contract terminology.

Do not invent frontend enum values that differ from Laravel.

---

# 21. Pagination

Inspect the frozen API pagination convention.

Determine how future website pagination maps to URL state.

If API uses:

```text
page
```

and that is appropriate for the website, preserve it.

Do not invent cursor/page translation unnecessarily.

The browser URL should support meaningful back/forward navigation.

---

# 22. Query Parameter Authority

Where public URL state maps directly to Laravel query parameters, avoid unnecessary renaming.

Bad pattern:

```text
URL:
?order=cheap

frontend converts to:
sort=price_asc
```

unless a deliberate UI contract justifies the translation.

Prefer one vocabulary across layers where appropriate.

---

# 23. Unknown Query Parameters

Document the policy for unknown query parameters.

Do not build a global aggressive query stripper.

Future pages should parse only supported parameters and safely ignore or normalize unsupported ones according to page requirements.

---

# 24. Query Parameter Ordering

Do not make application correctness depend on query parameter order.

These should be semantically equivalent:

```text
?sort=price&page=2

?page=2&sort=price
```

Canonical SEO normalization can be handled later in Group N.

---

# 25. Made-to-Order Is First-Class

MADE_TO_ORDER is a primary business offering.

Its routing must not imply:

```text
error
fallback
unavailable
special failure
```

Establish a clear public route for initiating or learning about the made-to-order request flow.

Determine naming from existing project terminology.

Do not invent a different marketing concept.

---

# 26. Furniture Request Route

The backend calls the domain concept:

```text
Furniture Request
```

Choose/document a human-facing route namespace that maps clearly to this domain.

Examples must be evaluated against existing project wording:

```text
/made-to-order
/furniture-requests
```

Do not create both as competing canonical routes.

One should be canonical if either is adopted.

---

# 27. Request Detail/History Routes

Do not automatically expose:

```text
/furniture-requests/[reference]
```

publicly.

Inspect whether customers have authenticated request-history/detail requirements.

If such functionality belongs to a later account phase:

```text
reserve the convention only if justified
```

Do not implement it.

Anonymous requests must never become enumerable through predictable URLs.

---

# 28. General Enquiries

The project has a separate General Enquiry domain.

Define its future public route convention based on existing product language.

Potential concepts include:

```text
/contact
/enquiries
```

but choose based on repository terminology and UX requirements.

Do not create multiple routes for the same purpose without canonicalization policy.

---

# 29. Requests and Enquiries Must Remain Distinct

Do not collapse:

```text
Furniture Request
```

and:

```text
General Enquiry
```

into one routing/domain concept merely because both contain forms.

Their backend workflows are separate.

---

# 30. Account Namespace

Reserve:

```text
/account
```

as the authenticated customer-area namespace unless existing architecture establishes another convention.

Future account routes should live beneath one coherent namespace.

Conceptually:

```text
/account
/account/orders
/account/requests
/account/enquiries
/account/profile
```

ONLY where those capabilities actually exist.

Do not implement these pages now.

---

# 31. Do Not Invent Saved Addresses

Saved addresses are deferred.

Therefore do not reserve/build:

```text
/account/addresses
```

as though it were a V1 feature.

---

# 32. Customer Account vs Admin

The public/customer Next.js website and admin interface have distinct responsibilities.

Do not place admin functionality under:

```text
/account/admin
```

or mix staff/admin routes into the customer route tree.

Admin route architecture belongs to the admin application.

---

# 33. Staff/Admin URLs

Do not create website routes such as:

```text
/admin
/staff
/dashboard
```

unless this specific `frontend/web` application is explicitly intended to host them.

The project architecture identifies admin as a separate Next.js + MUI application concern.

Preserve that boundary.

---

# 34. Authentication Routes

Inspect the planned Clerk integration.

Define where sign-in/sign-up URLs will eventually live only if required by the architecture.

Possible convention:

```text
/sign-in
/sign-up
```

but do not guess if Clerk architecture already specifies routes.

Do NOT integrate Clerk in Phase 13.6.

---

# 35. Checkout

The project has request-first production constraints and transactional purchase remains deferred/disabled.

Do NOT establish active routes such as:

```text
/checkout
/payment
/order-confirmation
```

as currently usable storefront routes.

If roadmap documentation needs them for future work, clearly mark them:

```text
deferred / inactive
```

Do not create route files.

---

# 36. Cart

Likewise, do not expose an active cart route merely because the backend has historical cart work.

Respect the current request-first production policy.

If transactional commerce is disabled in the current release:

```text
/cart
```

must not become an active navigable website feature during 13.6.

---

# 37. Order Routes

Order/customer purchase routes remain subject to the deferred transactional flow.

Do not implement or advertise:

```text
/orders
/account/orders
```

unless current V1 website requirements explicitly require them independently of the disabled purchase flow.

Document deferred ownership where appropriate.

---

# 38. Route Groups

Use Next.js route groups:

```text
(group)
```

only when they provide a genuine layout/organization boundary without changing the URL.

Potential future conceptual grouping:

```text
(marketing)
(catalog)
(account)
```

but do not create route groups merely to make the tree look sophisticated.

Phase 13.7 will own layout implementation.

---

# 39. Route Groups Are Not URL Segments

Document explicitly:

```text
(catalog)
```

does NOT become:

```text
/catalog
```

in the browser URL.

Do not use route groups as a substitute for clear URL taxonomy.

---

# 40. Do Not Build Layouts Yet

Even if route groups are documented, do NOT create elaborate:

```text
(catalog)/layout.tsx
(account)/layout.tsx
```

during this phase.

Phase 13.7 owns layout system implementation.

A route group may be created only if strictly necessary to validate a routing convention, and preferably not at all during 13.6.

---

# 41. Dynamic Segments

Use:

```text
[slug]
```

or the actual identifier name rather than vague:

```text
[id]
```

when the public contract specifically uses a slug.

The filesystem should communicate the route contract.

---

# 42. Catch-All Segments

Do NOT use:

```text
[...slug]
[[...slug]]
```

for catalog routing unless a concrete hierarchical URL requirement exists.

Catch-all routing often hides ambiguity.

Prefer explicit route structures.

---

# 43. Parallel Routes

Do not introduce:

```text
@modal
@sidebar
```

parallel routes in Phase 13.6.

Those are implementation mechanisms that should be justified by later UX requirements.

---

# 44. Intercepting Routes

Do not introduce intercepting route syntax for product modals or similar behavior.

A product must have a normal independently navigable canonical page first.

---

# 45. URL Case

Canonical application paths should use:

```text
lowercase
```

Do not define mixed-case routes.

Use:

```text
/made-to-order
```

not:

```text
/MadeToOrder
```

---

# 46. Multiword Segments

Use:

```text
kebab-case
```

for multiword static route segments.

Example:

```text
/made-to-order
```

not:

```text
/made_to_order
/madeToOrder
```

---

# 47. Trailing Slash Policy

Inspect `next.config.*`.

Choose one consistent policy based on existing Next.js behavior.

Do not add trailing-slash redirects merely for preference.

Document whether canonical URLs are conceptually:

```text
/products
```

rather than:

```text
/products/
```

if that matches the existing configuration.

---

# 48. Route Constants

Do NOT automatically create a giant:

```text
ROUTES
```

object containing every possible path.

Use route helpers/constants only where they provide actual type safety or prevent repeated dynamic URL construction.

Static links such as:

```tsx
<Link href="/products">
```

do not necessarily need abstraction.

---

# 49. Dynamic Route Helpers

A small typed helper may eventually be justified for dynamic paths:

```ts
productPath(product.slug)
```

but do not implement helpers for pages that do not exist unless Phase 13.6 needs them for a contract/test.

Avoid speculative abstraction.

---

# 50. No Router Service

Do not create:

```text
RouterService
NavigationManager
RouteRepository
```

The App Router already provides navigation infrastructure.

---

# 51. Link Component

Future internal navigation should use:

```text
next/link
```

or MUI composition with Next Link where required.

Do not use raw:

```html
<a href="/products">
```

for routine internal navigation if it defeats Next.js navigation behavior.

External links remain normal anchors.

---

# 52. MUI + Next Link

Document the convention for components that need both:

```text
MUI visual behavior
+
Next.js navigation
```

Prefer semantic composition rather than click handlers that call `router.push()` for ordinary links.

A navigation action should remain a link when it semantically is a link.

---

# 53. Button vs Link

Preserve Phase 12.6 semantics:

```text
navigation
→ link

action
→ button
```

Do not style a `<div>` or button as a fake navigation link when an anchor is semantically correct.

---

# 54. Programmatic Navigation

Use:

```text
router.push()
router.replace()
```

only when navigation is caused by application logic rather than an ordinary link.

Do not make every product card navigation depend on an `onClick`.

---

# 55. Server Components by Default

Route/page files should remain:

```text
Server Components by default
```

Do not put:

```tsx
'use client';
```

on a page merely because a child eventually needs interaction.

Keep client boundaries as low as practical.

---

# 56. `useRouter`

Do not use:

```text
useRouter
```

where:

```text
<Link>
```

is sufficient.

`useRouter` requires a Client Component and should have a concrete interaction reason.

---

# 57. Search Params

Future server-renderable pages should accept App Router search parameters through the framework-supported API for the installed Next.js version.

Do not adopt stale examples from older Next.js versions.

Verify against the installed Next.js version before documenting signatures.

---

# 58. Dynamic Params

Likewise, use the parameter API supported by the installed Next.js version.

Do not copy old synchronous `params` examples if the installed framework version uses different typing/behavior.

The routing convention must match the actual version in the repository.

---

# 59. No Global Client Router State

Do not mirror URL state into a global React context/store merely for navigation.

The URL itself is authoritative for:

```text
route identity
shareable search state
shareable filter state
pagination
sorting
```

where appropriate.

---

# 60. Ephemeral State

Do not put every UI detail into the URL.

Examples of generally ephemeral state:

```text
temporary hover
accordion animation progress
menu open state
focus state
```

URL state should represent meaningful navigational state.

---

# 61. Product Variant URL State

Do not decide yet whether:

```text
color
fabric
dimensions
variant
```

belong in product-detail query parameters.

That decision belongs with product-detail requirements unless the frozen API/product contract already mandates it.

Document as:

```text
DEFERRED TO 14.4
```

rather than guessing.

---

# 62. SEO Boundary

Phase 13.6 defines URL structure.

It does NOT implement:

```text
generateMetadata
canonical tags
Open Graph
JSON-LD
robots
sitemap
```

Those belong to Group N.

However, routing decisions must not make future SEO unnecessarily difficult.

---

# 63. Canonical URL Principle

Document that every indexable public resource should eventually have one canonical URL.

Do not implement canonical metadata yet.

---

# 64. Redirects

Do not create speculative redirects for route aliases that have never existed publicly.

Redirects should solve:

```text
real legacy URL
real renamed route
real canonicalization requirement
```

not hypothetical future migrations.

---

# 65. Permanent vs Temporary Redirects

When future redirects are needed, their permanence must be intentional.

Do not default every redirect to permanent.

No redirect implementation is expected in 13.6 unless reconciling an existing route conflict.

---

# 66. Backend API Routes Are Separate

Browser routes:

```text
/products
/categories/...
/search
```

are NOT Laravel API routes.

Laravel remains under:

```text
/api/v1/...
```

Do not expose backend paths directly as user-facing website URLs.

---

# 67. No Next.js API Mirror

Do not create:

```text
/app/api/products
/app/api/categories
```

to mirror Laravel.

Phase 13.5 already established the API client boundary.

Laravel remains the backend API.

---

# 68. API Identifier Alignment

Where public website routes refer to Laravel resources, use public identifiers compatible with the API contract.

Do not create a frontend-only identifier mapping table.

---

# 69. Reference Identifiers

Business references such as:

```text
OD-xxxxx
REQ-xxxxxxxxxx
PAY-xxxxxxxx
```

have domain meaning.

Do not assume they automatically belong in public URLs.

For each future authenticated resource route, consider:

```text
privacy
enumerability
authorization
404 masking
shareability
```

before using references.

Phase 13.6 may document this principle without creating such routes.

---

# 70. Furniture Request Privacy

Anonymous furniture requests must never gain publicly accessible detail URLs merely because a request reference exists.

A reference is not authentication.

Document this explicitly.

---

# 71. Enquiry Privacy

The same rule applies to enquiries.

Do not expose enquiry detail routes publicly without authentication/authorization architecture.

---

# 72. Account Authorization

Route names do not provide authorization.

Future:

```text
/account/*
```

must still enforce Clerk identity and Laravel ownership/authorization.

Do not treat URL obscurity as security.

---

# 73. Middleware

Do NOT implement Clerk middleware during Phase 13.6.

Do not introduce auth middleware merely to reserve account routes.

Authentication routing protection belongs to the appropriate auth/account implementation phase.

---

# 74. Middleware for URL Normalization

Avoid middleware for simple routing conventions where Next.js native routing/configuration suffices.

Do not build a universal URL rewriting layer.

---

# 75. Locale Routing

Do not introduce:

```text
/en
/sw
/en-US
```

locale prefixes unless internationalization is already approved.

Do not pre-architect i18n speculatively.

---

# 76. Currency in URLs

Do not add currency to route paths/query parameters merely because prices use TZS.

Currency handling is not a routing concern unless future multi-currency requirements explicitly require it.

---

# 77. Tenant Routing

This is not a multi-tenant storefront.

Do not introduce:

```text
/store/[tenant]
/shop/[seller]
```

architecture.

---

# 78. Café/Campus Concepts

Do not leak concepts from unrelated projects into this furniture application.

Routing terminology must remain furniture-domain specific.

---

# 79. Route Documentation

Create one concise routing authority for the website.

Preferred location:

```text
frontend/web/ROUTING.md
```

unless the repository already has an appropriate architecture-document location.

Do not scatter the route contract across multiple documents.

---

# 80. Required Routing Matrix

The routing authority should contain a table conceptually like:

| Route | Purpose | Visibility | Identifier | Implementation owner | Current state |
|---|---|---|---|---|---|
| `/` | Storefront homepage | Public | — | 14.1 | Reserved |
| `/products` | Product listing | Public | — | 14.3 | Reserved |
| `/products/[...]` | Product detail | Public | contract-defined | 14.4 | Reserved |
| category route | Category discovery | Public | contract-defined | 14.2 | Reserved |
| `/search` | Search | Public | query state | 14.5 | Reserved |
| MTO route | Furniture request | Public | — | later owning phase | Reserved |
| enquiry route | General enquiry | Public | — | later owning phase | Reserved |
| `/account` | Customer area | Authenticated | — | later owning phase | Reserved |

Use the actual route decisions established from repository evidence.

Do not blindly copy placeholders from this prompt.

---

# 81. Route Status Vocabulary

Use a small status vocabulary such as:

```text
EXISTING
RESERVED
DEFERRED
PROHIBITED
```

This helps future agents distinguish:

```text
documented route
```

from:

```text
implemented route
```

---

# 82. Ownership Column

Every reserved route should identify the phase or feature group that owns its implementation where known.

This prevents Phase 13.6 documentation from being mistaken for permission to build it.

---

# 83. Deferred Commerce Routes

Explicitly document transactional routes affected by request-first production policy as:

```text
DEFERRED
```

Examples if relevant:

```text
/cart
/checkout
/account/orders
/payment-related routes
```

Do not create route files for them.

---

# 84. Prohibited Routes

Document obvious anti-patterns where useful, such as:

```text
/api/* as website mirror
/admin inside customer storefront
/public request detail by reference
```

Do not create an enormous blacklist.

---

# 85. Filesystem Plan

The routing authority may show a **future conceptual** App Router tree.

For example:

```text
app/
├── page.tsx
├── products/
│   ├── page.tsx
│   └── [public-identifier]/
│       └── page.tsx
└── ...
```

Mark it clearly:

```text
planned structure
not implemented by Phase 13.6
```

Do not create empty route files merely to mirror the diagram.

---

# 86. No Empty Placeholder Pages

Do not create:

```text
/products/page.tsx
/categories/.../page.tsx
/search/page.tsx
```

that merely say:

```text
Coming soon
```

This pollutes the route tree and can accidentally expose unfinished pages.

Reserve routes in documentation until their owning implementation phase.

---

# 87. Existing Root Page

The existing scaffold root page may remain.

Do not replace it with the final homepage.

If it contains inappropriate starter residue that survived 13.1–13.4, make only the minimum cleanup.

---

# 88. Route Collision Audit

Audit planned route namespaces for collisions.

Examples:

```text
/products/new
/products/[slug]
```

could collide if `new` is a valid slug.

Do not reserve static subpaths inside dynamic namespaces without considering collision semantics.

---

# 89. Reserved Slugs

Do NOT invent a global reserved-slug database unless needed.

If route architecture creates a real collision risk, document the reserved namespace rule or select a structure that avoids the conflict.

---

# 90. Slug Normalization

If the API contract uses slugs, determine whether normalization occurs in Laravel.

Do not independently:

```text
lowercase
transliterate
replace spaces
strip punctuation
```

in the frontend unless the contract requires it.

Laravel/domain authority should produce canonical resource identifiers.

---

# 91. Invalid Identifiers

Phase 13.6 does not decide UI for invalid identifiers.

Future page implementation will map backend 404 to the appropriate Next.js not-found behavior.

Phase 13.8 establishes error/not-found infrastructure.

---

# 92. Accessibility

Routing/navigation conventions must preserve:

```text
semantic links
keyboard operability
browser history
open-in-new-tab behavior
copy/share URL behavior
back/forward navigation
```

Avoid click-only navigation patterns.

---

# 93. Skip Link Boundary

Phase 12.7 established the future requirement for a skip-to-main mechanism.

Do NOT implement it in Phase 13.6.

Phase 13.7 layout system owns the appropriate structural integration.

Routing documentation may reference that dependency.

---

# 94. Page Titles

Do not implement page titles/metadata.

Phase 14.7 owns SEO metadata.

Routing documentation may note that route identity must provide enough context for future metadata generation.

---

# 95. Breadcrumbs

Do not implement breadcrumbs.

Document that breadcrumbs are navigation/UI derived from domain hierarchy and route context, not necessarily a literal reflection of URL path segments.

This is especially important for the category taxonomy.

---

# 96. Navigation Menus

Do not implement desktop/mobile menus.

Phase 13.7 owns structural layout and later public UI phases own actual navigation content.

13.6 only defines where links will point.

---

# 97. Testing Strategy

Because this is largely architectural, tests should target any executable routing helpers/configuration actually introduced.

Do not add a testing framework just to test documentation.

If no runtime routing code is added, validation may primarily consist of:

```text
routing policy checks
route collision audit
documentation consistency
TypeScript
ESLint
production build
existing theme/API regression suites
```

---

# 98. Contract Audit

Cross-check documented public identifiers against:

```text
OpenAPI
API resources
Laravel routes
Laravel resource serializers
relevant models
```

Do not claim a slug exists merely because a database table contains a `name`.

---

# 99. API Route Audit

Confirm planned website route names do not accidentally duplicate backend `/api/v1` semantics in a confusing way.

The distinction should remain obvious:

```text
Website:
https://example.com/products/oak-chair

API:
https://api.example.com/api/v1/...
```

Actual deployment hostnames remain environment concerns.

---

# 100. Request-First Audit

Explicitly verify the routing document does not accidentally re-enable transactional commerce.

Report:

```text
Active cart route:
NO

Active checkout route:
NO

Active payment route:
NO
```

unless project policy has explicitly changed.

---

# 101. Admin Boundary Audit

Report:

```text
Admin routes added to customer web:
NO
```

---

# 102. Clerk Boundary Audit

Report:

```text
Clerk dependency changed:
NO

Auth middleware added:
NO

Sign-in UI implemented:
NO
```

unless an existing route convention needed documentation only.

---

# 103. API Client Boundary Audit

Phase 13.6 should not modify the Phase 13.5 transport unless a genuine integration defect is discovered.

Expected:

```text
API client implementation changed:
NO
```

If changed, explain why.

---

# 104. Design System Boundary

Routing should not require changes to:

```text
MUI theme
tokens
component conventions
accessibility authority
Flutter mapping
```

Expected:

```text
design-system implementation changes:
NONE
```

---

# 105. Dependencies

Expected:

```text
NONE
```

Do not install routing libraries.

Next.js App Router already provides the required infrastructure.

Do not add:

```text
react-router
wouter
TanStack Router
```

---

# 106. AGENTS Guidance

Update `frontend/AGENTS.md` only if durable routing rules need enforcement.

Useful durable rules may include:

```text
App Router only
Server Components by default
next/link for ordinary internal navigation
URL state for shareable filters/search/pagination
no public internal DB IDs
no arbitrary route aliases
no public request/enquiry detail routes
no active transactional routes while request-first policy applies
```

Avoid duplicating all of `ROUTING.md`.

---

# 107. ADR

Add an ADR only if this phase establishes a material routing architecture decision requiring historical explanation.

A possible ADR might cover:

```text
resource-oriented public URLs
stable public identifiers
query parameters for shareable collection state
request-first transactional-route deferral
```

Use the repository's existing ADR numbering convention.

Do not create an ADR merely because every phase has had one recently.

---

# 108. Phase Record

Update:

```text
phases/group-M-phases.md
```

with:

```text
Phase 13.6
status
route authority location
canonical route decisions
identifier decisions
deferred routes
validation performed
scope boundaries
```

---

# 109. Validation

Run the actual available repository commands.

At minimum:

```text
TypeScript
ESLint
production build
git diff --check
```

Also run existing relevant regression checks for:

```text
API client
theme contract
```

where inexpensive and available.

If a routing-policy validation script already exists, run it.

Do not invent fake command names.

---

# 110. Completion Report

Return:

```text
Phase 13.6 status:
PASS / BLOCKED


ROUTING AUTHORITY

Routing document:
<path>

Router:
Next.js App Router / FAIL

Pages Router introduced:
NO / FAIL

Routing library added:
NONE / FAIL

Server Components default:
YES / NO


PUBLIC ROUTES

Homepage:
<route>

Product listing:
<route>

Product detail:
<route>

Product route identifier:
<identifier + contract source>

Category route:
<route>

Category identifier:
<identifier + contract source>

Search:
<route>

Search query parameter:
<parameter>

Pagination URL convention:
<summary>

Sorting URL convention:
<summary/deferred>

Filter URL convention:
<summary/deferred>

Made-to-order:
<route/convention>

General enquiry:
<route/convention>

Customer account namespace:
<route/convention>


TRANSACTIONAL ROUTES

Cart active:
NO / <explain>

Checkout active:
NO / <explain>

Payment route active:
NO / <explain>

Order route active:
NO / <explain>

Request-first policy preserved:
YES / NO


PRIVACY / SECURITY

Internal numeric IDs exposed:
NO / <explain>

Public request detail by reference:
NO / <explain>

Public enquiry detail by reference:
NO / <explain>

Account routes treated as authorization:
NO / FAIL

Admin routes added to customer website:
NO / FAIL


NEXT.JS CONVENTIONS

Route groups:
<documented/none>

Catch-all routes:
NONE / <reason>

Parallel routes:
NONE

Intercepting routes:
NONE

Internal navigation:
next/link

Programmatic navigation policy:
<summary>

Shareable state:
URL search params

Ephemeral UI state:
local state

Trailing slash policy:
<summary>

Path casing:
lowercase

Multiword segments:
kebab-case


BOUNDARIES

Pages implemented:
NONE / <list>

Layouts implemented:
NONE / <list>

Navigation UI implemented:
NO

Breadcrumbs implemented:
NO

SEO metadata implemented:
NO

Sitemap/robots implemented:
NO

Clerk integrated:
NO

Auth middleware added:
NO

API client changed:
NO / <reason>

Theme/design system changed:
NO / <reason>

Flutter changed:
NO

Backend/API changed:
NO

Dependencies added:
NONE


VALIDATION

Identifier/API contract audit:
PASS / BLOCKED

Route collision audit:
PASS / FAIL

Request-first route audit:
PASS / FAIL

Privacy route audit:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

API client regression:
PASS / FAIL

Theme regression:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


DOCUMENTATION

frontend/AGENTS.md:
<updated/unchanged>

Group M execution record:
PASS / FAIL

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

Phase 13.6:
PASS / BLOCKED

Phase 13.7:
READY / BLOCKED
```

---

# 111. STOP Condition

Phase 13.6 may be declared PASS only when:

- one canonical website routing authority exists;
- App Router remains authoritative;
- Pages Router was not introduced;
- no routing dependency was added;
- public URL naming is consistent and resource-oriented;
- product URL identifier is supported by the actual API contract;
- category URL identifier is supported by the actual API contract;
- no internal numeric database IDs are exposed without explicit contract approval;
- homepage, catalog, product, category, search, MTO, enquiry, and account route conventions are either explicitly defined or explicitly deferred with a reason;
- product resources have one canonical route convention;
- search/shareable collection state uses a documented URL-query strategy;
- request and enquiry routing remain separate;
- anonymous request/enquiry references are not treated as authentication;
- customer and admin route spaces remain separate;
- request-first production policy remains intact;
- cart/checkout/payment routes have not accidentally been activated;
- Server Components remain the default;
- ordinary internal navigation is defined around semantic links;
- no layouts were prematurely built;
- no catalog pages were prematurely built;
- no SEO/structured-data/sitemap implementation was started;
- no Clerk/auth implementation was started;
- Phase 13.5 API client remains intact;
- no backend, Flutter, or design-system implementation was changed unnecessarily;
- TypeScript passes;
- ESLint passes;
- production build passes;
- relevant regression checks pass;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

If the frozen API does **not** provide a suitable public product or category identifier, report:

```text
Phase 13.6 — BLOCKED

Reason:
Public routing requires a stable identifier that is not established by the frozen V1 contract.

Conflict:
<exact files/fields/routes>

Required resolution:
<precise contract decision>
```

Do not invent a slug locally just to obtain PASS.

Otherwise finish with:

```text
Phase 13.6 — PASS
Phase 13.7 — READY
```

Do not start Phase 13.7 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

# Phase 13.6 Execution Record

## Result

```text
Phase 13.6: PASS
Phase 13.7: READY
```

Canonical routing authority: `frontend/web/ROUTING.md`.

## Public Identifier Audit

```text
Product route: /products/[slug]
Category route: /categories/[slug]
Canonical website identifier: API-returned slug
Internal numeric database IDs in URLs: NO
API public opaque ID fallback: prod_... / cat_... (not the canonical website URL)
```

The frozen catalog contract explicitly selects product slugs for Next.js SEO (`api-contract.md §21.3/§21.9`) and defines category detail lookup by slug or public ID (`§21.4`). The actual Laravel controllers resolve those slugs; both identifier routes were covered by the existing focused backend read API tests. This is not a numeric-ID-only API gap, so routing is not blocked.

Slug lifecycle caveat recorded in `ROUTING.md`: category slugs are mutable and no historical redirect/alias is contracted. There is also a product discrepancy: `api-resources.md §11.1` describes product slug as read-only/server-generated, while OpenAPI `ProductUpdateRequest` and current Laravel product update handling accept/persist slug changes. A rename can invalidate an old canonical URL; V1 provides no old-slug history or redirect behavior. Phase 13.6 follows the frozen SEO canonical slug, does not change the API, and does not invent a redirect mechanism. Reconcile the product slug contract and published-slug URL migration policy before making slug edits a routine published-catalog operation.

## Canonical Route Decisions

```text
/                                      existing root placeholder; final homepage 14.1
/products                             public product collection; 14.3
/products/[slug]                      public product detail; 14.4
/categories/[slug]                    public category landing; 14.2
/search?search=...                    public search; 14.5, API parameter preserved
/furniture-requests                   public request-first Furniture Request; 15.9
/contact                              public General Enquiry; 15.10
/sign-in, /sign-up                    reserved Clerk UI; 15.1
/account, /account/profile            reserved customer area/profile
/account/requests                     reserved authenticated own requests
/account/enquiries                    reserved authenticated own enquiries
```

Collection URL state uses Laravel's `search`, `category`, `product_type`, `availability`, `min_price`, `max_price`, `sort`, `sort_direction`, `page`, and `per_page` vocabulary. Category filters prefer the API slug. Paths are lowercase, multiword static segments kebab-case, slashless; Next.js 16 default trailing-slash normalization applies. Search/filter/pagination state is URL state; ephemeral presentation state remains local.

Request and enquiry stay distinct. Anonymous request/enquiry detail-by-reference routes are prohibited. Account URL naming does not authorize access; Laravel ownership/auth checks remain required. Admin stays a separate app.

```text
Cart, checkout, payment, order confirmation, account order history: DEFERRED
Route groups/pages/layouts: NOT IMPLEMENTED
Search/filter UI: NOT IMPLEMENTED
SEO metadata/redirects/sitemap/robots: NOT IMPLEMENTED
Clerk/auth middleware: NOT IMPLEMENTED
Backend/API/Flutter/design system: UNCHANGED
```

No API client, route page, layout, routing dependency, or executable routing helper was added. The filesystem tree in `ROUTING.md` is explicitly conceptual only.

## Validation

```text
ProductReadApiTest: PASS (29 tests)
CategoryReadApiTest: PASS (5 tests)
Identifier/API contract audit: PASS, with slug mutability caveat above
Route collision/request-first/privacy audits: PASS
API client regression: PASS
Theme regression: PASS
TypeScript: PASS
ESLint: PASS
Production build: PASS
git diff --check: PASS
Dependencies added: NONE
```

Group N remains unstarted. Phase 13.7 is ready and was not started automatically.
