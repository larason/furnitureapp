It should be **Defining the Catalog Contract**, not API contract examples.

The previous Phase 1.20 recommendation was incorrect relative to the decomposition we established from `AGENTS.md`. Since you want the micro-phases to remain faithful to the master roadmap while expanding broad phases into smaller dependency-safe steps, **1.20 should now begin the concrete resource contract work with Catalog**.

Also, because Phase 1.19 has already established the endpoint inventory, Phase 1.20 is the appropriate point to start specifying the actual contract for catalog endpoints.

# Phase 1.20 — Define Catalog API Contract

## 1. Purpose

Phase 1.20 takes the approved:

```text
id="n4r0p5"
API resource inventory
API resource relationships
API naming conventions
HTTP method conventions
query conventions
pagination conventions
response conventions
request conventions
validation conventions
error contract
authentication contract
authorization contract
concrete endpoint inventory
```

and defines the **first complete domain-specific API contract: Catalog**.

The Catalog contract covers the public ecommerce discovery experience:

```text
id="7l6p2u"
Categories
Products
Product Variants
Product Images
Public Availability
Product Search
Product Filtering
Product Sorting
Product Detail
```

The central principle is:

> **The Catalog API must be fast, public, predictable, SEO-compatible, safe, and independent of customer authentication.**

Customers must be able to browse normally without registering or logging in.

---

# 2. Documentation Strategy

Continue the consolidated documentation strategy.

Update:

```text id="2cfm6s"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/decisions.md
```

Update:

```text id="xk4d7v"
docs/domain/business-rules.md
```

only when documenting an already-approved catalog business rule.

Do **not** create:

```text id="p1q6jr"
catalog-contract.md
product-api.md
category-api.md
catalog-decisions.md
```

unless the project later becomes large enough to justify separate API-domain documents.

---

# 3. Authoritative Project Paths

Use:

```text id="b0y3hz"
AGENTS.md
docs/VISION.md
```

These are authoritative.

---

# 4. Payment Assignment

Payment remains assigned to:

**Phase Group H**

Do not introduce payment behavior into Catalog.

A product may expose:

```text id="f9rg57"
price: {amount, currency}
availability
stock_indicator
```

but Catalog does not define:

```text id="h9qj7c"
payment initiation
payment confirmation
payment provider
payment webhooks
```

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

For Catalog, this includes at minimum:

```text id="bj3g3z"
product_type
availability status
```

where these are represented as enums.

Do not accept undocumented values.

---

# 6. Dependency Position

The sequence is now:

```text id="ac2m59"
1.19 Concrete API Endpoint Inventory
       ↓
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
...
```

This phase must use the concrete endpoint inventory from Phase 1.19.

---

# 7. Catalog API Design Principle

Catalog endpoints are primarily:

```text id="uqo4hx"
READ-ONLY
PUBLIC
HIGH-TRAFFIC
CACHE-FRIENDLY
SEO-RELATED
```

They should not require authentication.

---

# 8. Public Catalog Authentication Boundary

The following public endpoints must remain accessible without login:

```text id="v2cv3x"
GET /api/v1/categories (CAT-003)
GET /api/v1/categories/{category} (CAT-004)
GET /api/v1/products (CAT-001, including search ?search= and embedded availability)
GET /api/v1/products/{product} (CAT-002, including embedded availability)
GET /api/v1/products/{product}/variants (CAT-005, including embedded availability)
GET /api/v1/products/{product}/variants/{variant} (CAT-006)
```

Availability is an embedded capability within product and variant representations (not a standalone `/availability` endpoint), and catalog search is a query capability on `GET /api/v1/products` (not a standalone `/products/search` endpoint).

Do not add authentication merely because the rest of the application contains private resources.

---

# 9. Catalog Resource Inventory

Finalize the Version 1 catalog endpoint/resource contract for:

```text id="wsqg01"
Category
Product
Product Variant
Product Image
Product Availability
```

Determine which are:

```text id="1qy1f3"
standalone endpoints
nested resources
embedded representations
```

Use Phase 1.7's relationship decisions.

---

# 10. Product Collection Contract

Define the final contract for:

```text id="n2j8df"
GET /api/v1/products
```

The endpoint must support the approved collection-query architecture.

Document:

```text id="5xwytp"
purpose
actor
authentication
authorization
pagination
search
filters
sorting
response
errors
caching considerations
```

---

# 11. Product Collection Query Parameters

Only include parameters justified by the catalog requirements.

Candidate parameters:

```text id="f7mj34"
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

Every parameter must conform to Phase 1.11.

Do not invent endpoint-specific naming.

---

# 12. Product Search Contract

Define what:

```text id="qju3nb"
search
```

means for products.

Document:

```text id="0o2s9j"
searchable fields
matching behavior
case handling
empty search behavior
maximum input considerations
```

Possible searchable concepts:

```text id="ls9p47"
product name
description
SKU/reference where publicly searchable
variant display values
```

Do not expose internal operational fields.

Do not select a search engine in this phase.

---

# 13. Product Category Filter

Define the canonical product-category filter.

Example:

```text id="r8x4f6"
?category=sofas
```

Determine whether the value represents:

```text id="f6j0je"
category slug
category identifier
```

Use the chosen resource-identity conventions consistently.

---

# 14. Product Type Filter

The product type is:

```text id="d4u5vn"
IN_STOCK
MADE_TO_ORDER
```

Define:

```text id="j9iluk"
?product_type=IN_STOCK
```

as a closed enum filter.

Unknown values are invalid.

Do not silently return zero results for invalid enum input if that would hide a client/API compatibility problem.

Use the global validation/error contract.

---

# 15. Availability Filter

Define how customers may filter by public availability.

Do not expose internal concepts such as:

```text id="3dw0q0"
reserved_quantity
stock_adjustments
inventory_transactions
```

The catalog contract exposes only the public availability concept.

---

# 16. Price Range

Define:

```text id="w3b9oe"
min_price
max_price
```

with the semantics established in Phase 1.11.

Validate:

```text id="w6n6v9"
min_price <= max_price
```

Do not silently swap invalid ranges.

---

# 17. Sorting

Define the allowed Catalog sort fields.

Possible candidates:

```text id="3j2j9f"
price
name
catalog order
created_at
```

Only use fields that make business sense.

Do not expose arbitrary database columns.

---

# 18. Default Product Sorting

Define one deterministic default.

Potential:

```text id="1y9gzq"
catalog display order
```

rather than relying on database natural ordering.

The actual ordering rule must be recorded explicitly.

---

# 19. Stable Sorting

Every paginated Product collection must have deterministic ordering.

If the primary sort is not unique, define a stable tie-breaker conceptually.

Example:

```text id="av8q1o"
display_order ASC
+
unique product identity ASC
```

Do not implement SQL yet.

---

# 20. Product Pagination

Apply the global pagination convention:

```text id="16p0op"
page
per_page
current_page
last_page
total
```

or the finalized Phase 1.12 structure.

Do not create catalog-specific pagination metadata.

---

# 21. Product Detail Contract

Define:

```text id="st0rb5"
/api/v1/products/{product}
```

The endpoint must be public.

It must provide enough information for:

```text id="3g5t1j"
Next.js product page
Flutter product detail page
SEO metadata generation
Add-to-cart decision
Made-to-order request decision
```

---

# 22. Product Detail Representation

Define the catalog-safe fields.

Likely:

```text id="o6d9vb"
id
name
slug
description
product_type
price: {amount, currency}
category
images
variants
availability
stock_indicator
```

Only include fields justified by the public catalog.

Do not expose internal administration data.

---

# 23. Product Type Behavior

The Product response must clearly communicate:

```text id="lr79w8"
IN_STOCK
or
MADE_TO_ORDER
```

This allows clients to determine the appropriate primary UI action.

Conceptually:

```text id="gwwfqt"
IN_STOCK
→ Add to Cart

MADE_TO_ORDER
→ Request Furniture
```

The backend still controls whether a purchase is actually allowed.

---

# 24. Product Price

Public Product responses may expose the current customer-visible price.

However:

> The current Product price is not the historical Order Item price.

Do not conflate them.

---

# 25. Product Currency

The Catalog contract must communicate the currency associated with the price.

Use the money representation established in Phase 1.13.

Do not format:

```text id="j10xcr"
"TZS 1,250,000"
```

as the only machine-readable value.

---

# 26. Product Images

Define how images appear in Product responses.

Each image should have the approved fields, such as:

```text id="w0r1om"
id
url
alt_text
sort_order
is_primary
```

Do not expose internal storage paths.

---

# 27. Product Image Ordering

Image ordering must be deterministic.

The primary image must also be deterministically identified.

This supports:

```text id="x7nq0e"
product cards
product galleries
Flutter image carousel
SEO image selection
```

---

# 28. Product Variants

Define how variants are represented in a Product response.

At minimum:

```text id="dy90jm"
variant identity
display attributes
price where applicable
availability where applicable
```

Do not allow unrelated variants to appear under the wrong Product.

---

# 29. Variant Ownership

The API must preserve:

```text id="k0sm2v"
Product
   └── Variant
```

A variant belongs to one owning Product.

The API must not allow a client to combine:

```text id="3ygax8"
Product A
+
Variant belonging to Product B
```

during later cart operations.

---

# 30. Category Resource

Define the Category contract.

Conceptually:

```text id="6v9yjs"
GET /api/v1/categories
GET /api/v1/categories/{category}
```

where both are public if approved by Phase 1.19.

---

# 31. Category Response

Public Category representation should include only relevant catalog fields.

Potential:

```text id="9e1qcd"
id
name
slug
description
image
```

Do not expose internal category-management metadata.

---

# 32. Category → Products

Decide the canonical strategy.

Possible:

```text id="5f8ke6"
/categories/{category}/products
```

or:

```text id="fzeut0"
/products?category={category}
```

Do not keep both unless there is an actual product requirement.

Use the query-based Product collection if it provides sufficient capability and avoids redundant endpoints.

Record the final decision.

---

# 33. SEO Consideration

The API must provide enough information for Next.js to generate SEO-friendly pages.

The Catalog API should support:

```text id="0j6mcn"
title
description
slug
images
price
availability
category
```

where appropriate.

Do not put API versioning into public website SEO URLs.

---

# 34. Public Slug

Product and Category slugs are public catalog identifiers for website navigation.

Define:

```text id="7n80xk"
slug
```

as a human-readable public identifier.

Do not assume slug is the same thing as the internal resource ID.

---

# 35. Slug Uniqueness

Document that Product slug and Category slug must be uniquely resolvable within their relevant resource namespace.

Do not define database indexes yet.

---

# 36. Product Not Found

If a requested Product is not available as a public catalog resource, use the common error contract.

Do not reveal internal unpublished product information.

For example:

```text id="j6q4u8"
RESOURCE_NOT_FOUND
```

rather than:

```text id="e1l1i9"
PRODUCT_EXISTS_BUT_IS_HIDDEN
```

unless there is a deliberate public distinction.

---

# 37. Unpublished Products

A product may exist internally but not be publicly visible.

Public Catalog endpoints must return only products that meet the public visibility/publishability rules.

This is separate from:

```text id="h8k1m2"
database existence
```

---

# 38. Deactivated Products

A deactivated product should not normally appear in new public catalog results.

However, historical Orders may still reference it.

Catalog visibility and historical order integrity remain separate concerns.

---

# 39. Made-to-Order Product Display

A `MADE_TO_ORDER` product remains publicly discoverable.

Its public representation should allow the UI to communicate:

```text id="9l79x8"
not directly purchasable
request available
```

Do not hide all Made-to-Order products simply because they do not participate in normal checkout.

---

# 40. In-Stock Product Display

An `IN_STOCK` product may appear publicly as purchasable when its current availability permits.

A public Product response should not be interpreted as a guarantee of inventory at checkout.

---

# 41. Availability Staleness

Document:

```text id="4p32xk"
Catalog availability
→ informational/current read

Checkout inventory
→ authoritative final validation
```

A customer may see:

```text AVAILABLE
```

and encounter:

```text INSUFFICIENT_STOCK
```

during checkout because another customer purchased the item.

That is a valid race condition, not necessarily an API inconsistency.

---

# 42. Internal Inventory Protection

Never expose through public Catalog:

```text id="0v8v6m"
reserved quantity
inventory transaction history
staff inventory notes
stock adjustment history
internal reorder information
```

Expose only the approved customer-facing availability information.

---

# 43. Catalog Cacheability

Identify that public Catalog GET endpoints are candidates for caching.

Potentially:

```text id="1dqr9u"
products
categories
product detail
category detail
images
```

may later use:

```text id="ogx2m0"
HTTP caching
CDN caching
Next.js caching
```

Do not configure caching in this phase.

---

# 44. Cache Safety

Only public responses should be safely shared through public caches.

Do not mix private/customer data into Catalog responses.

Catalog data should remain independent of:

```text id="wgy2on"
customer identity
customer cart
customer orders
```

---

# 45. Catalog Response Determinism

Public catalog responses should be deterministic for the same:

```text id="7xmdf0"
resource
query
catalog state
```

This improves:

* caching;
* testing;
* SSR;
* Flutter consistency.

---

# 46. Catalog Authorization

All public Catalog reads use:

```text id="d8s6ap"
Authorization:
PUBLIC_READ
```

No account is required.

Staff/Admin may also read the public representation, while their operational catalog views may contain additional fields through later admin contracts.

---

# 47. Public vs Administrative Product Representation

Do not expose one unrestricted Product object to everyone.

Conceptually:

```text id="x7a9y0r"
Public Product
→ catalog fields

Administrative Product
→ catalog + operational fields
```

The actual administrative resource contract will be defined later.

---

# 48. Catalog Field Exposure

Define exposure for every Product field:

```text id="7dl9ev"
PUBLIC
INTERNAL
STAFF
ADMIN
```

At minimum:

| Field                     | Public |
| ------------------------- | -----: |
| name                      |    Yes |
| slug                      |    Yes |
| description               |    Yes |
| price: {amount, currency} |    Yes |
| public availability       |    Yes |
| stock_indicator           |    Yes |
| images                    |    Yes |
| variants                  |    Yes |
| internal stock quantities |     No |
| internal notes            |     No |
| staff-only metadata       |     No |

---

# 49. Catalog Error Contract

Use Phase 1.16's common error structure.

Potential Catalog errors:

```text id="79p0xu"
RESOURCE_NOT_FOUND
INVALID_VALUE
INVALID_FORMAT
```

For query filters:

```text id="a9gaw0"
INVALID_VALUE
```

For example:

```text id="tso20z"
?product_type=NOT_A_PRODUCT_TYPE
```

must use the common error contract.

---

# 50. Catalog Security Review

Explicitly test:

```text id="n2ra5d"
Can an anonymous user access hidden products?
Can an anonymous user access internal inventory?
Can a customer see staff notes?
Can a customer retrieve unpublished variants?
Can a client use arbitrary database fields in filters?
Can a client expose inactive products through query manipulation?
```

All must be safely addressed.

---

# 51. Catalog Query Allow-Lists

Define that only explicitly documented filters are accepted.

Examples:

```text id="n4h3bq"
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

Do not translate arbitrary query keys into database conditions.

---

# 52. Search Security

Search input must be treated as untrusted input.

The eventual implementation must protect against:

```text id="9g7p0i"
SQL injection
query abuse
excessive search length
resource exhaustion
```

Do not implement those protections here; record them as requirements.

---

# 53. Sorting Security

Sorting must use a server-defined allow-list.

Never accept arbitrary database column names.

---

# 54. Category Security

A customer must not use a category identifier to reach:

```text id="t2r4g9"
private categories
draft categories
administrative category data
```

unless specifically authorized.

---

# 55. Variant Security

Variant references must always respect their Product relationship.

This relationship will become especially important when the Cart API is designed.

---

# 56. Catalog Contract and Cart Dependency

The Catalog API should provide the information necessary for Cart operations, but Catalog must remain read-only.

Conceptually:

```text id="db4yz2"
Catalog
  ↓
Cart
  ↓
Checkout
```

Catalog does not create orders or reserve stock.

---

# 57. Catalog Contract and Made-to-Order Requests

Catalog should expose enough product information for the customer to discover:

```text id="5l0k8v"
"Request this furniture"
```

but the Request workflow remains a separate API contract.

Do not make Product API create a request implicitly.

---

# 58. Catalog Contract and Customer Authentication

Do not return different basic Product information solely because a customer is logged in unless there is a genuine business need.

The public catalog should remain publicly understandable.

---

# 59. Catalog Contract and Staff Authentication

Staff may later receive richer operational catalog information through privileged endpoints.

Do not contaminate the public Catalog contract with staff-only fields.

---

# 60. Endpoint Contract Records

For each Catalog endpoint, document:

```text id="j6k5r0"
Endpoint ID
Method
Path
Purpose
Actors
Authentication
Authorization
Query parameters
Pagination
Request body
Response
Errors
Business rules
Caching classification
Security classification
```

---

# 61. Recommended Catalog Endpoint Set

Use the Phase 1.19 inventory as the source of truth.

The approved Version 1 public catalog endpoint set is:

```text id="1b6p0r"
CAT-001
GET /api/v1/products

CAT-002
GET /api/v1/products/{product}

CAT-003
GET /api/v1/categories

CAT-004
GET /api/v1/categories/{category}

CAT-005
GET /api/v1/products/{product}/variants

CAT-006
GET /api/v1/products/{product}/variants/{variant}
```

*Note:* `GET /api/v1/categories/{category}/products` is **REJECTED** per `ADR/API-END-002`. Category filtering is handled canonically via `GET /api/v1/products?category={category}` (`CAT-001`).

---

# 62. Endpoint Example — Product Collection

Conceptual contract:

```text id="dy05h5"
ID:
CAT-001

Method:
GET

Path:
/api/v1/products

Authentication:
Not required

Authorization:
Public catalog read

Purpose:
Retrieve the publicly visible Product collection.

Query:
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

Response:
Paginated Product collection

Mutation:
None

Business authority:
Current catalog/public availability

Caching:
Public/cacheable candidate
```

Do not treat this example as permission to finalize fields not already approved.

---

# 63. Endpoint Example — Product Detail

```text id="d7u2r2"
ID:
CAT-002

Method:
GET

Path:
/api/v1/products/{product}

Authentication:
Not required

Authorization:
Public catalog read

Purpose:
Retrieve one publicly visible Product.

Response:
Public Product representation

Errors:
RESOURCE_NOT_FOUND
```

---

# 64. Endpoint Example — Category Collection

```text id="guf9q5"
ID:
CAT-003

Method:
GET

Path:
/api/v1/categories

Authentication:
Not required

Authorization:
Public catalog read

Purpose:
Retrieve public categories.

Response:
Category collection
```

---

# 65. Endpoint Example — Category Detail

```text id="4kwx9y"
ID:
CAT-004

Method:
GET

Path:
/api/v1/categories/{category}

Authentication:
Not required

Authorization:
Public catalog read

Purpose:
Retrieve one public category.

Response:
Category representation

Errors:
RESOURCE_NOT_FOUND
```

---

# 66. Request Bodies

Catalog public GET endpoints normally have:

```text id="x1n7iy"
no request body
```

Do not use GET request bodies for search/filter operations.

Query parameters handle collection selection.

---

# 67. HTTP Method Consistency

Catalog public retrieval must use:

```text id="1jby5n"
GET
```

Do not create:

```text id="p5lmpk"
POST /products/search
```

for ordinary product discovery unless a later requirement genuinely demands a complex search operation.

---

# 68. Catalog Filtering Consistency

Do not create separate endpoints:

```text id="83yrk7"
/products/sofas
/products/in-stock
/products/cheap
/products/made-to-order
```

for ordinary filter dimensions.

Use the established collection query convention unless a distinct resource genuinely exists.

---

# 69. Search Result Identity

Search results must use the same Product representation conventions as normal Product collections.

Do not create a second incompatible Product format just for search.

---

# 70. Catalog Pagination Consistency

Search/filter/sort/pagination must compose.

For example:

```text id="r7d95h"
/products
?search=sofa
&product_type=IN_STOCK
&min_price=500000
&sort=price
&sort_direction=asc
&page=2
&per_page=24
```

must follow the ordering:

```text id="nnh0ik"
search
→ filter
→ sort
→ paginate
```

as established earlier.

---

# 71. Catalog Closed Enums

Document Catalog enum values explicitly.

At minimum:

```text id="0qd9u1"
product_type:
IN_STOCK
MADE_TO_ORDER
```

If availability status is an enum, list its approved Version 1 values as well.

Do not add values without a decision record.

---

# 72. Catalog Compatibility

Within API Version 1:

Do not change:

```text id="7j5xg8"
Product field types
Product field meanings
Product enum values
slug semantics
money representation
timestamp formats
```

without evaluating breaking compatibility.

---

# 73. Catalog API and Next.js

The contract must support efficient server-side rendering.

The Next.js application must be able to retrieve public Product data without:

```text id="ub3rmm"
client authentication
browser-only API calls
```

where server-side rendering requires the data.

---

# 74. Catalog API and Flutter

Flutter must be able to consume the same Product/Category contract.

Do not create:

```text id="4x4rtd"
mobile-products
mobile-categories
```

API variants simply to suit Flutter.

The client adapts to the shared contract.

---

# 75. Catalog API and Admin

Admin product management will use richer operational contracts later.

Do not expand public Catalog responses simply to satisfy Admin.

Use separate authorized representations/endpoints where needed.

---

# 76. SEO and Structured Data

Catalog responses should contain enough product information for the Next.js layer to generate:

```text id="8g38zn"
Product structured data
canonical metadata
Open Graph metadata
search-friendly pages
```

SEO implementation remains a frontend/web phase.

---

# 77. Catalog Performance Considerations

Flag:

```text id="n9e4hj"
Product list
Product detail
Category list
Category detail
```

as high-read endpoints.

Later implementation should consider:

```text id="1h6t7y"
database indexes
query optimization
HTTP caching
CDN
Next.js caching
image optimization
```

Do not implement them yet.

---

# 78. N+1 Query Risk

The Catalog contract should avoid designs that unnecessarily force:

```text id="oxw7dw"
one database query per Product
```

when loading images/variants/categories.

This is an implementation consideration for the later Laravel phases.

Do not optimize prematurely.

---

# 79. Response Size

Do not return unlimited:

```text id="f9n6c3"
images
variants
related products
```

by default.

Use the relationship policy from Phase 1.7.

Keep Product Detail useful without turning Product List into an enormous response.

---

# 80. Product Collection Representation

Product collection items should use the appropriate **summary representation** where full Product Detail information is unnecessary.

For example, list views may need:

```text id="ug7a3q"
id
name
slug
primary image
price: {amount, currency}
availability
stock_indicator
product_type
```

while Product Detail can provide richer information.

Document this distinction.

---

# 81. Summary vs Full Representation

Define:

```text id="p53r7w"
Product Summary
```

for collection/list contexts.

Define:

```text id="p9t3w8"
Product Detail
```

for detail contexts.

They must use consistent field meanings.

Do not create unrelated schemas with conflicting semantics.

---

# 82. Category Summary vs Detail

Similarly determine whether Category list responses need only:

```text id="y7z7r9"
id
name
slug
image
```

while Category Detail can include description.

Do not return unnecessary data on every collection request.

---

# 83. Resource Representation Naming

Use the same JSON property names regardless of client.

Example:

```text id="j3rj4i"
product_type
created_at
updated_at
alt_text
is_primary
```

Do not create:

```text id="3w0wmd"
mobile-only field names
web-only field names
```

for the same resource.

---

# 84. Catalog Business Rules to Encode

Document the rules that affect Catalog endpoints:

```text id="4nm1qg"
Only public products are returned.
Only public categories are returned.
MADE_TO_ORDER products are publicly discoverable.
MADE_TO_ORDER products are not normally purchasable through checkout.
IN_STOCK products may be purchasable when currently available.
Current availability is informational.
Checkout performs final authoritative inventory validation.
```

---

# 85. Catalog Contract Decision Review

Before finalizing, review:

```text id="wq5nkt"
Does every field have a business meaning?
Is every public field actually safe?
Are hidden fields excluded?
Are all filters necessary?
Are endpoints redundant?
Are query semantics consistent?
Are representations consistent?
Is the API easy for Next.js?
Is it easy for Flutter?
Can the API scale beyond the current small catalog?
```

---

# 86. Required Documentation Updates

## `docs/api/api-contract.md`

Add/finalize:

```text id="jzk30p"
## Catalog API Contract
### Product Collection
### Product Detail
### Category Collection
### Category Detail
### Catalog Query Parameters
### Catalog Pagination
### Catalog Representations
### Catalog Errors
### Catalog Authorization
### Catalog Caching Classification
### Catalog Compatibility
```

Include the endpoint IDs.

---

## `docs/api/api-resources.md`

Update:

```text id="4e5ij3"
Product
Category
Product Variant
Product Image
Availability
```

with:

```text id="bp8h9c"
public representation
summary representation
detail representation
relationships
public/private fields
endpoint references
```

---

## `docs/api/api-conventions.md`

Record catalog-specific conventions that are genuinely reusable.

Do not create exceptions that apply only to one endpoint without justification.

---

## `docs/domain/business-rules.md`

Confirm the Catalog rules already established in Phase 1.3.

Do not invent new product-business rules here.

---

## `docs/decisions.md`

Record significant Catalog decisions.

Example:

```text id="sz4wm0"
### API-CAT-001 — Public Catalog Requires No Authentication

Decision:
Product and category browsing remain publicly accessible.

Reason:
Customer browsing must not require registration/login.

### API-CAT-002 — Product Type Is Closed

Decision:
Version 1 product_type accepts only approved values.

### API-CAT-003 — Public Availability Is Not Internal Inventory

Decision:
Catalog exposes customer-facing availability rather than internal stock-management data.
```

Use the project's existing decision numbering convention if different.

---

# 87. Required Endpoint Contract Matrix

Include in:

```text id="pjyx45"
docs/api/api-contract.md
```

| ID      | Method | Path                            | Auth | Authorization | Pagination | Purpose         |
| ------- | ------ | ------------------------------- | ---- | ------------- | ---------- | --------------- |
| CAT-001 | GET    | `/api/v1/products`              | No   | Public read   | Yes        | List products   |
| CAT-002 | GET    | `/api/v1/products/{product}`    | No   | Public read   | No         | Product detail  |
| CAT-003 | GET    | `/api/v1/categories`            | No   | Public read   | As defined | List categories |
| CAT-004 | GET    | `/api/v1/categories/{category}` | No   | Public read   | No         | Category detail |

Add/remove rows according to the actual approved Phase 1.19 inventory.

---

# 88. Required Security Review

The agent must explicitly test the design against:

```text id="j0nh4h"
Anonymous access
Hidden products
Hidden categories
Inactive resources
Internal inventory
Arbitrary filter fields
Arbitrary sort fields
Variant/product mismatch
Customer-specific data leakage
Cache leakage
Identifier enumeration
```

---

# 89. Definition of Done

Phase 1.20 is complete when:

* [ ] Public Catalog resources are explicitly defined.
* [ ] Product collection contract exists.
* [ ] Product detail contract exists.
* [ ] Category collection contract exists where required.
* [ ] Category detail contract exists where required.
* [ ] Product type behavior is explicit.
* [ ] Public availability behavior is explicit.
* [ ] Search contract is defined.
* [ ] Filter contract is defined.
* [ ] Sort contract is defined.
* [ ] Pagination integration is defined.
* [ ] Product summary/detail representations are distinguished where useful.
* [ ] Product images are represented consistently.
* [ ] Product variants are represented consistently.
* [ ] Slug/identifier semantics are explicit.
* [ ] Public vs internal fields are explicit.
* [ ] Catalog endpoints require no authentication where approved.
* [ ] Hidden/private catalog information cannot leak.
* [ ] Query parameters use the global conventions.
* [ ] Version 1 Catalog enums remain CLOSED.
* [ ] Catalog responses use the global response envelope.
* [ ] Catalog errors use the global error contract.
* [ ] Next.js requirements are covered.
* [ ] Flutter requirements are covered.
* [ ] Admin requirements are not allowed to contaminate public responses.
* [ ] No Laravel implementation has been created.
* [ ] No frontend implementation has been created.
* [ ] No payment provider work has been performed.
* [ ] Payment remains assigned to **Phase Group H**.
* [ ] Consolidated documentation has been updated.

---

# 90. Explicitly Out of Scope

Do NOT:

```text id="z31d1w"
Create Laravel routes
Create Laravel controllers
Create Laravel API Resources
Create Eloquent models
Create database migrations
Create database indexes
Implement search
Implement caching
Implement authorization middleware
Implement Next.js catalog pages
Implement Flutter catalog screens
Generate final OpenAPI schemas
Implement image storage
Implement payment
```

---

# 91. STOP CONDITION — Mandatory

After completing the Catalog contract and updating:

```text id="u3xq4e"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

**STOP.**

Do not continue directly to the Cart contract.

Do not begin Laravel implementation.

Do not generate the complete OpenAPI file.

Do not implement Product or Category APIs.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.21 — Define Cart API Contract

That phase should build directly on the now-finalized Catalog contract and define the authenticated customer's cart behavior, including:

```text id="4tj3yv"
cart ownership
cart retrieval
add item
update quantity
remove item
product/variant validation
stock revalidation
cart pricing representation
cart lifecycle
anonymous-to-authenticated considerations
authorization
concurrency
idempotency
```

with **no checkout or payment implementation yet**.
