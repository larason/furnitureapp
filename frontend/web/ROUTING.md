# Website Routing Contract

This document is the canonical URL and App Router convention for `frontend/web`. It reserves routes and defines ownership; it does not create the listed pages. Laravel remains under `/api/v1` and remains authoritative for resource lookup, visibility, authorization, and business state.

## Product And Category Identifier Audit

The frozen public catalog contract provides non-numeric public identifiers and slugs:

- `Product.id` is an opaque `prod_...` API identifier; `Category.id` is an opaque `cat_...` API identifier. The API contract describes these identifiers as stable and distinct from database IDs.
- Both public representations include a URL-safe, unique, lowercase kebab-case `slug`.
- `GET /api/v1/products/{product}` accepts a product slug or public ID. The product detail contract explicitly names the slug as canonical for Next.js SEO routing (`api-contract.md §21.3`, §21.9).
- `GET /api/v1/categories/{category}` accepts a category slug or public ID (`api-contract.md §21.4`).
- Laravel's `ProductController` and `CategoryController` resolve those slugs directly; collection resources serialize the current slug. Laravel routes and read API tests confirm slug resolution. The website MUST use the returned slug, not generate a local slug or expose a database numeric ID.

### Slug stability caveat

Uniqueness and URL-safe syntax do not make a slug immutable. Category updates accept and persist a changed slug, and the API has no old-slug history or redirect contract. Product slug lifecycle documentation is inconsistent: `api-resources.md §11.1` calls the slug read-only/server-generated, while `openapi.yaml` `ProductUpdateRequest` and current Laravel `ProductFormRequest`/`UpdateProduct` accept and persist slug changes. The frozen catalog contract still explicitly designates the slug as the Next.js canonical identifier.

Therefore the canonical route uses the current API slug, but historic URL permanence across a slug rename is **not guaranteed** by V1. Old slugs may resolve as 404. Phase 13.6 does not invent frontend redirects or change the frozen API. Before published slug edits are used as normal content operations, the product-slug mutability discrepancy and URL migration policy require explicit reconciliation.

## Canonical Public Route Matrix

| Website route | Purpose | Visibility | URL identity/state | Owner | Status |
|---|---|---|---|---|---|
| `/` | Public storefront homepage | Public | None | Phase 14.1 | Existing foundation placeholder; reserved for final homepage |
| `/products` | Product collection | Public | Shareable API-aligned query parameters | Phase 14.3 | Reserved |
| `/products/[slug]` | Product detail | Public | Laravel-returned `Product.slug` | Phase 14.4 | Reserved |
| `/categories/[slug]` | Category landing page | Public | Laravel-returned `Category.slug` | Phase 14.2 | Reserved |
| `/search` | Search results | Public | `?search=<term>` | Phase 14.5 | Reserved |
| `/furniture-requests` | Create a Furniture Request, including MADE_TO_ORDER interest | Public | Form state; no public request identifier | Owning request UI phase | Reserved |
| `/contact` | Create a general Enquiry | Public | Form state; no public enquiry identifier | Phase 15.10 | Reserved |
| `/sign-in` | Clerk sign-in entry point | Public | Clerk-owned identity flow | Phase 15.1 | Reserved |
| `/sign-up` | Clerk sign-up entry point | Public | Clerk-owned identity flow | Phase 15.1 | Reserved |
| `/account` | Customer account area | Authenticated UX | Current customer's account only | Owning account UI phase | Reserved |
| `/account/profile` | Customer profile | Authenticated UX | Current customer only | Owning profile UI phase | Reserved |
| `/account/requests` | Customer's own Furniture Requests | Authenticated UX | Current customer only | Owning request/account phase | Reserved |
| `/account/enquiries` | Customer's own Enquiries | Authenticated UX | Current customer only | Owning enquiry/account phase | Reserved |
| `/account/orders` | Order history | Authenticated UX | Transactional commerce deferred | Future commerce phase | Deferred |

`/products/[slug]` is the one canonical website product URL. Category context belongs in category navigation, breadcrumbs, or collection query state; it does not create a second nested product URL.

## Collection Query Contract

Search, filters, sorting, and pagination that a customer should be able to share or revisit belong in URL search parameters. Keep the frozen Laravel vocabulary wherever it applies:

| Purpose | Parameter | Contract values/meaning |
|---|---|---|
| Search text | `search` | String; use the same name on `/search` and collection requests. Do not introduce `q`, `query`, or aliases. |
| Category filter | `category` | Prefer the API-returned category slug; Laravel also accepts the public category ID. |
| Product type | `product_type` | Closed `IN_STOCK` / `MADE_TO_ORDER` values. |
| Availability | `availability` | Closed lowercase `available` / `unavailable`. |
| Price bounds | `min_price`, `max_price` | Integer TZS minor units, serialized as query strings. |
| Sorting | `sort`, `sort_direction` | `sort`: `created_at`, `price`, `name`; direction: `asc` / `desc`. |
| Pagination | `page`, `per_page` | One-based page; `per_page` 1–100, backend default 20. |

Parameter order has no meaning. A URL parser should read only parameters supported by its route and must not globally strip/rewrite unknown parameters. Ephemeral display state (menus, focus, hover, temporary accordion state) stays local. No route implementation, filter UI, or SEO canonicalization is part of this phase.

## Route And Navigation Rules

- Next.js App Router is authoritative. The only current application route is `/` (`app/page.tsx`); it remains a minimal placeholder until Phase 14.1. Do not add `pages/` or create placeholder route files.
- Route paths are lowercase, static multiword segments use kebab-case, and canonical paths have no trailing slash. `next.config.ts` does not enable `trailingSlash`; the installed Next.js default redirects slash-suffixed page URLs to their slashless form.
- Server Components are the default. Pages needing interactive descendants keep client boundaries below the page where practical.
- Ordinary internal navigation uses semantic `next/link` (with MUI composition when needed); buttons perform actions. Use programmatic router navigation only when application logic requires it, not for routine links.
- Route groups, catch-all, parallel, and intercepting routes are not introduced now. A future layout may use a route group only for a real organization/layout boundary; route groups never appear in the public URL.
- A dynamic parameter is a contract value, not a locally derived label. Do not lowercase, transliterate, or otherwise normalize identifiers in the browser.
- Account URLs are UX organization only, not authorization. Clerk provides identity; Laravel must independently enforce authentication, ownership, and permissions. Anonymous requests and enquiries stay public to create but their details are never public by reference.

## Deferred And Separate Namespaces

The initial production release is request-first. `/cart`, `/checkout`, `/payment`, `/order-confirmation`, and `/account/orders` are deferred/inactive; no route files or active links are created for them. A MADE_TO_ORDER request is not an order.

Admin remains a separate Next.js application. This customer website does not add `/admin`, `/staff`, or `/dashboard`. Website routes are not Laravel routes and do not mirror `/api/v1`; no Next.js API proxy or route handler is created for Laravel endpoints.

Public request/enquiry detail routes by identifier are prohibited without a separately approved secure access design. References and opaque IDs are not credentials. The API client's `data` and `meta` do not change the browser URL taxonomy.

## Future Filesystem Sketch (Not Implemented)

```text
app/
  page.tsx                         # existing foundation placeholder
  products/page.tsx                # Phase 14.3
  products/[slug]/page.tsx         # Phase 14.4
  categories/[slug]/page.tsx       # Phase 14.2
  search/page.tsx                  # Phase 14.5
  furniture-requests/page.tsx      # owning request UI phase
  contact/page.tsx                 # Phase 15.10
  account/...                      # owning authenticated account phases
  sign-in/...                      # Phase 15.1 / Clerk
  sign-up/...                      # Phase 15.1 / Clerk
```

This is a route plan only. No route pages, layouts, navigation, Clerk integration, metadata, sitemap, robots, redirects, or Group N catalog behavior are implemented by Phase 13.6.
