# Phase 1.13 — Define API Response Envelope and Resource Representation

## 1. Purpose

Phase 1.13 establishes the project's **standard API response format and resource representation rules**.

This phase converts the approved work from Phases 1.1–1.12 into a consistent response language that can be consumed by:

```text
Next.js website
Flutter application
Admin application
Future clients
```

The goal is to prevent each endpoint or developer from inventing a different response structure.

This phase defines:

* common response envelope;
* successful response structure;
* collection response structure;
* individual-resource response structure;
* pagination placement;
* metadata conventions;
* resource identifiers;
* timestamps;
* money representation;
* nullable fields;
* relationship representation;
* embedded vs referenced resources;
* links where appropriate;
* serialization rules;
* response compatibility rules.

This phase does **not** define individual endpoint response payloads.

---

# 2. Documentation Strategy

The project now follows the consolidated-document approach.

Do **not** create a new permanent markdown file for every tiny decision.

The authoritative API documentation should be maintained primarily in:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
```

For this phase:

### Update

```text
docs/api/api-contract.md
docs/api/api-conventions.md
```

### Update resource-specific information where necessary

```text
docs/api/api-resources.md
```

### Do not create

```text
api-response-envelope.md
api-response-decisions.md
api-response-links.md
api-resource-serialization.md
```

unless a later project decision explicitly shows that a separate document is genuinely necessary.

The phase instruction file may exist as temporary working documentation, but the **authoritative project knowledge must be consolidated**.

---

# 3. Authoritative Project Paths

Use:

```text
AGENTS.md
docs/VISION.md
```

Do not revert to:

```text
agent.md
VISION.md
```

---

# 4. Payment Assignment

Payment-specific design remains assigned to:

**Phase Group H**

not Group G.

This phase may define generic representation principles for Payment, but must not define provider-specific structures.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

This applies to response values.

For example:

```text id="q99hcz"
IN_STOCK
MADE_TO_ORDER
PICKUP
DELIVERY
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
```

Only documented values are valid for Version 1.

A future additional enum value requires an explicit compatibility review.

Do not resurrect the old `OPEN/EXTENSIBLE` rule.

---

# 6. Dependency Position

Current sequence:

```text id="taq7x1"
1.1  API Scope
       ↓
1.2  Domain Nouns
       ↓
1.3  Domain Invariants
       ↓
1.4  Logical Data Model
       ↓
1.5  Logical Model Review + Freeze
       ↓
1.6  API Resource Inventory
       ↓
1.7  API Resource Relationships
       ↓
1.8  API Versioning
       ↓
1.9  Endpoint Naming
       ↓
1.10 HTTP Methods
       ↓
1.11 Query Parameters
       ↓
1.12 Pagination
       ↓
1.13 Response Envelope + Resource Representation
       ↓
1.14 ...
```

Do not skip backwards into earlier architectural decisions unless a contradiction is discovered.

---

# 7. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
```

Also review the completed outputs for:

```text id="j5f0im"
Phase 1.1 API scope
Phase 1.2 domain nouns
Phase 1.3 domain invariants
Phase 1.4 logical data model
Phase 1.5 frozen logical model
Phase 1.6 resource inventory
Phase 1.7 resource relationships
Phase 1.8 versioning
Phase 1.9 naming
Phase 1.10 HTTP methods
Phase 1.11 query conventions
Phase 1.12 pagination
```

Use actual project filenames where appropriate.

---

# 8. Core Response Principle

All API responses should follow a **single predictable contract**.

Do not allow:

```text id="m4utx4"
Endpoint A → raw array
Endpoint B → {products: [...]}
Endpoint C → {data: [...]}
Endpoint D → {result: [...]}
```

unless there is a clearly documented reason.

Clients should not have to guess how a response is shaped.

---

# 9. Choose the Response Envelope

Evaluate:

```text id="8qq4qz"
bare resource
data envelope
data + meta
data + meta + links
```

For this project, use a consistent envelope model.

Recommended baseline:

### Individual resource

```json
{
  "data": {}
}
```

### Collection

```json
{
  "data": [],
  "meta": {}
}
```

### Error

```json
{
  "errors": []
}
```

This is a conceptual baseline.

Do not yet design the detailed error contract; that belongs to the later error phase.

---

# 10. `data` as the Primary Success Member

For successful requests, use:

```text
data
```

as the canonical primary result.

Examples:

```text id="x0v61v"
single resource
→ data = object

collection
→ data = array
```

Do not use different success roots such as:

```text id="b1x5ae"
result
resource
payload
response
items
```

as replacements for `data`.

---

# 11. Individual Resource Response

An individual resource response should conceptually be:

```json
{
  "data": {
    "id": "...",
    "type": "...",
    "attributes": {}
  }
}
```

However, the project must formally decide whether to use a JSON:API-like `attributes`/`relationships` structure or a simpler flat representation.

For a small-to-medium custom API, prefer a **simple flat resource representation** unless there is a demonstrated need for JSON:API complexity.

Potential baseline:

```json
{
  "data": {
    "id": "123",
    "name": "Modern Sofa",
    "price": 1250000
  }
}
```

Record the final decision.

---

# 12. Do Not Copy JSON:API Partially

Do not create an inconsistent hybrid such as:

```json
{
  "data": {
    "type": "products",
    "attributes": {},
    "relationships": {}
  },
  "meta": {},
  "links": {}
}
```

while implementing only some JSON:API semantics.

Either intentionally adopt a standard fully enough to justify it or use a simpler project-defined representation.

For this project, the recommended baseline is:

> **Simple project-defined resource objects inside a consistent `data` envelope.**

---

# 13. Resource Identifier

Every independently identifiable resource should have a canonical:

```text id
```

field in API responses.

Example:

```json
{
  "data": {
    "id": "123",
    "name": "Modern Sofa"
  }
}
```

The API `id` must be treated as the API resource identifier.

Do not expose database implementation details unnecessarily.

---

# 14. Resource Type

Evaluate whether every response must include:

```text
type
```

such as:

```json
{
  "id": "123",
  "type": "product"
}
```

For a small custom API, this may be unnecessary if the endpoint context already establishes resource type.

Choose one project-wide policy.

Do not inconsistently include `type` on some resources and omit it on others.

Recommended baseline:

> Do not require a `type` field in every resource unless later OpenAPI/client tooling demonstrates sufficient value.

Record the decision.

---

# 15. Field Naming

Use one canonical JSON property naming convention.

Recommended:

```text id="02nh7v"
snake_case
```

Examples:

```json
{
  "product_type": "IN_STOCK",
  "created_at": "...",
  "delivery_fee": 35000
}
```

Avoid mixing:

```text id="05h5cm"
productType
product_type
product-type
```

within the response contract.

The database naming convention must not dictate the API automatically, but the API itself must remain internally consistent.

---

# 16. Timestamps

Use a single standard representation for timestamps.

Recommended:

> ISO 8601 / RFC 3339-compatible UTC timestamps.

Example:

```text id="m0fwd1"
2026-08-30T15:30:00Z
```

Do not return mixed formats such as:

```text id="1r18ca"
2026-08-30 15:30:00
30/08/2026
1756567800
```

unless an explicit specialized field requires a numeric timestamp.

---

# 17. Created/Updated Fields

For resources that have lifecycle timestamps, standardize:

```text id="t8jcb0"
created_at
updated_at
```

Use them consistently.

Do not expose timestamps simply because the database has them; only expose timestamps useful to the API client.

---

# 18. Nullable Values

Define a consistent rule for fields that have no value.

Prefer:

```json
{
  "delivery_address": null
}
```

when the field is conceptually part of the resource but currently has no value.

Do not arbitrarily alternate between:

```text id="k0bj0d"
null
""
false
omitted
```

for the same semantic condition.

---

# 19. Null vs Absent

Distinguish conceptually:

### `null`

The field exists in the contract but currently has no value.

### absent

The field is not part of the current representation.

The API must define when each is used.

Recommended:

> Use `null` for known nullable resource properties and omission for fields that are not part of the selected representation.

---

# 20. Boolean Values

Return actual JSON booleans:

```json
{
  "is_active": true
}
```

Never:

```text id="q4yzyw"
"is_active": "true"
"is_active": 1
"is_active": "yes"
```

---

# 21. Numeric Values

Use JSON numeric values for numeric concepts.

Example:

```json
{
  "quantity": 2
}
```

Do not return:

```json
{
  "quantity": "2"
}
```

unless a specific external identifier is textual by definition.

---

# 22. Money Representation

Money requires special attention.

Do not return formatted display strings as the primary financial representation.

Avoid:

```json
{
  "price": "TZS 1,250,000"
}
```

because clients cannot reliably perform calculations on it.

Instead use a structured representation or a clearly documented numeric representation.

Finalized project rule (supersedes “must decide” — see `docs/api/api-contract.md §3.8`, `docs/api/api-conventions.md §4`, `ADR/API-019`):

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```
— `amount` is **integer minor units (cents, 1 TZS = 100)** as JSON number (e.g., `125000000` = 1,250,000.00 TZS; `50000000` = 500,000.00 TZS; pickup `delivery_fee` → `{"amount":0,"currency":"TZS"}`); `currency` is `"TZS"`. Bare `price: 1250000` without `currency` and decimal-string forms are not used. The earlier alternatives (`number + currency` / `decimal string + currency`) are **historical options**, not open choices — `integer minor units + currency` is the single global rule; do not mix representations.

---

# 23. Recommended Money Strategy — Finalized

For a commerce API, the canonical fields are:

```text id="3or7x4"
amount  // integer minor units (cents)
currency // "TZS"
```

`amount` is integer minor units per the finalized rule above; zero-decimal handling is documented as integer minor units preserved (TZS cents, display may hide cents). The former “precision strategy remains undecided / Do not implement it here” is **superseded** — precision is finalized as integer minor units.

---

# 24. Price Representation

Product price, order-item price, delivery fee, and order total must use the same financial representation strategy.

Do not have:

```text id="v239fx"
Product price → number
Order total → string
Delivery fee → formatted text
Payment amount → decimal
```

All monetary representations must be consistent.

---

# 25. Product Response

A public Product representation may conceptually contain:

```text id="1e9ytk"
id
name
slug
description
product_type
price
currency
images
variants
availability
category
```

Only include fields that belong to the approved public product contract.

Do not expose:

```text id="8xe6pl"
internal inventory adjustments
reserved quantity
staff notes
supplier internals
private operational fields
```

---

# 26. Availability Representation — Finalized

Public availability is **scalar lowercase `availability: "available" | "unavailable"`** plus separate display bucket `stock_indicator: "IN_STOCK" | "LOW_STOCK" | "MADE_TO_ORDER"` (response-only, not filterable), per `docs/api/api-contract.md §3.9`, `docs/api/api-resources.md §1`, `query-filtering-policy.md §7`, `query-enum-policy.md` and `ADR/API-020`. The earlier alternatives (`"AVAILABLE"` uppercase or `{"availability":{"status":"AVAILABLE"}}` object) are **obsolete and not to be chosen** — they would create incompatible shapes (`UPPERCASE` vs lowercase, scalar vs object). The finalized scalar lowercase field is CLOSED (`available`/`unavailable`); `?availability=IN_STOCK` remains validation error; `IN_STOCK` belongs to `product_type` or `stock_indicator`, not the `availability` filter.

Do not expose internal inventory mechanics (`reserved_quantity`, `physical_quantity`).

---

# 27. Inventory Representation

Staff/admin inventory responses may contain operational values not shown publicly.

For example:

```text id="7x4g2t"
quantity
reserved_quantity
available_quantity
```

Customer Product responses should not automatically expose the same structure.

Therefore define two conceptual representation levels:

```text id="oe4zyn"
PUBLIC AVAILABILITY
vs
OPERATIONAL INVENTORY
```

---

# 28. Product Variants

Variant representation should clearly communicate:

```text id="sn4k9o"
variant identity
variant display information
pricing where applicable
availability where applicable
```

Do not expose unrelated variants outside their Product context unnecessarily.

---

# 29. Category Representation

A Category response should contain only category-relevant public information.

Potential:

```text id="264p0e"
id
name
slug
description
image
```

Whether products are embedded or separately retrieved follows the relationship policy from Phase 1.7.

---

# 30. Relationship Representation

For relationships, choose one consistent approach:

```text id="7l7q4o"
embedded related resource
summary object
identifier/reference
link
```

Avoid different conventions without a documented reason.

---

# 31. Recommended Relationship Strategy

For Version 1:

### Small, essential child data

May be embedded.

Example:

```text id="z6u5p2"
Product
 → images
```

### Large/independent resources

Prefer summary/reference.

Example:

```text id="26o9t0"
Product
 → category summary
```

### Sensitive or restricted relationships

Do not expose automatically.

Example:

```text id="k6w3vd"
Order
 → payment details
```

Use a limited customer-safe representation.

---

# 32. Avoid Recursive Embedding

Never return:

```text id="w4ehx9"
Product
 → Category
   → Products
     → Category
       → Products
```

Relationship representations must be bounded.

Use:

```text id="9jo50p"
summary
reference
```

when necessary.

---

# 33. Collection Response

A collection should conceptually be:

```json
{
  "data": [
    {},
    {}
  ],
  "meta": {}
}
```

The exact pagination fields are governed by Phase 1.12.

Do not duplicate pagination semantics in each endpoint.

---

# 34. Pagination Metadata Placement

Use the `meta` member for collection-level metadata.

Conceptually:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 86,
      "last_page": 5
    }
  }
}
```

Whether the pagination object is nested under `meta.pagination` or placed directly under `meta` must be chosen once and documented consistently.

Recommended:

```text id="55zgvt"
meta.pagination
```

because it leaves room for other collection metadata later.

---

# 35. Response Links

Evaluate whether responses should support a standardized:

```text id="tn9zw3"
links
```

member.

Potential examples:

```text id="908k0a"
self
next
previous
first
last
```

Do not add link fields everywhere unless they provide actual value.

For Version 1, navigation links are especially useful for paginated collections if already accepted in Phase 1.12.

Record the policy once.

---

# 36. `self` Links

Decide whether every resource should expose:

```json
{
  "links": {
    "self": "..."
  }
}
```

This may improve API discoverability but adds payload complexity.

For a relatively small application, it is acceptable to omit per-resource `self` links if clients already know the endpoint structure.

Choose deliberately rather than inconsistently.

---

# 37. Error Separation

Successful responses use:

```text id="2yv0t8"
data
```

Error responses will later use:

```text id="kr7flv"
errors
```

Do not create responses such as:

```json
{
  "data": null,
  "error": "something failed"
}
```

unless the final error contract explicitly adopts that format.

Error design belongs to the later error-contract phase.

---

# 38. Meta Data

Reserve:

```text id="bfy5hr"
meta
```

for response metadata rather than arbitrary business objects.

Appropriate examples:

```text id="j3av59"
pagination metadata
request correlation information where approved
summary information
```

Do not put actual resources into `meta`.

---

# 39. Do Not Put Business Data in `meta`

Bad:

```json
{
  "data": [],
  "meta": {
    "current_user": {},
    "product": {}
  }
}
```

`meta` should not become a second uncontrolled response channel.

---

# 40. Response Envelope for Empty Success

For a collection with no records:

```json
{
  "data": [],
  "meta": {
    "pagination": {}
  }
}
```

Do not return:

```text id="qz4v0k"
data = null
```

for an empty collection.

A collection is still a collection.

---

# 41. Individual Empty/Not Found Behavior

An individual resource that does not exist is not an empty resource.

It must eventually use the error contract.

Do not return:

```json
{
  "data": null
}
```

and make clients guess whether that means:

```text not found
```

or:

```text valid resource with null data
```

Exact error status/format comes later.

---

# 42. Action Response Representation

Business actions such as:

```text id="gboziv"
cancel
accept
ship
deliver
```

may return:

```text id="jyc4b7"
updated resource
operation result
```

The project should prefer returning the updated relevant resource when that is useful.

Example conceptually:

```json
{
  "data": {
    "id": "123",
    "order_reference": "OD-12345",
    "status": "SHIPPED"
  }
}
```

The exact action contract is later.

---

# 43. Checkout Response

Checkout may eventually return an Order/payment-related result.

Do not define the full checkout response now.

The general rule is:

> A workflow response should identify the resulting business resource or state clearly.

Avoid opaque:

```json
{
  "success": true
}
```

when the client needs the resulting order identity.

---

# 44. Resource-Specific Fields

Do not create a universal mega-schema containing every field possible across all resources.

For example:

```text id="6s03ab"
Product
→ should not have order fields
Order
→ should not have product-management fields
```

Each resource representation should contain only relevant data.

---

# 45. Admin Representations

Admin responses may legitimately contain more data than public responses.

Example:

```text id="3yq9kx"
Public Product
→ availability summary

Admin Product
→ operational inventory details
```

But the representation should remain governed by the same API naming and serialization conventions.

Do not create random admin-specific field naming styles.

---

# 46. Customer Order Representation

Customer Order responses should include enough data to understand:

```text id="3clq27"
order reference
status
items
totals
fulfillment
delivery summary where applicable
payment summary where appropriate
tracking information where appropriate
```

Do not expose internal staff-only fields.

---

# 47. Order Status Representation

Because order status is a closed enum, responses must use the canonical values.

Example:

```json
{
  "status": "PROCESSING"
}
```

Do not return display labels as the authoritative status.

The frontend can translate:

```text id="i6xv0x"
PROCESSING
→
"Being prepared"
```

according to the UI.

---

# 48. Human-Readable Labels vs Machine Values

Keep machine values stable.

For example:

```text id="sgy61y"
status = "READY_FOR_PICKUP"
```

rather than:

```text id="7m0f64"
status = "Ready for pickup"
```

This allows Flutter and Next.js to localize/display the state independently.

---

# 49. Machine Identifiers vs Display References

Distinguish:

```text id="wqka8z"
internal/resource id
customer-facing order reference
slug
SKU
payment provider reference
```

For an order:

```text id="8za83h"
id
order_reference = "OD-..."
```

if both are needed.

Do not force the internal identity and customer-facing reference to be the same field unless the final data/API design deliberately chooses that.

---

# 50. Resource Serialization Security

Before serializing a resource, determine whether the field is permitted for that caller.

Never return:

```text id="4rs06z"
password hashes
authentication tokens
provider secrets
internal credentials
private staff notes
```

through normal resource serialization.

---

# 51. Field Exposure Levels

At least conceptually support:

```text id="o0cpmf"
PUBLIC
CUSTOMER
STAFF
ADMIN
INTERNAL
```

Resource serializers must select data according to authorization context.

Do not assume one serializer is safe for every audience.

---

# 52. Avoid Mass Serialization

Do not expose all model/database fields automatically.

For example, avoid:

```text id="7byfjp"
return model.toArray()
```

as the conceptual API policy.

The API response should contain an explicit allowed representation.

This is important for security and backward compatibility.

---

# 53. Versioning of Response Fields

Within Version 1:

* do not silently rename a field;
* do not change its type;
* do not change its meaning;
* do not remove it;
* do not change nullability unexpectedly.

Adding a truly optional field may be non-breaking, subject to the Version 1 compatibility policy.

---

# 54. Enum Compatibility

Because enums are CLOSED:

If:

```text id="8s4fb5"
status:
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
...
```

is part of the Version 1 response contract, clients may rely on that documented set.

Adding:

```text id="4w0i5q"
ON_HOLD
```

is therefore a contract compatibility event.

It must be reviewed before inclusion.

---

# 55. Money Compatibility

Changing:

```text id="cxyqf4"
price = number
```

to:

```text id="oyv4g0"
price = object
```

within Version 1 is breaking.

Therefore choose the money response strategy now and use it consistently.

---

# 56. Timestamp Compatibility

Changing:

```text id="u4rd1l"
created_at = ISO string
```

to:

```text id="4hs8g5"
created_at = Unix integer
```

within Version 1 is breaking.

Freeze the representation once chosen.

---

# 57. Boolean Compatibility

Do not change:

```text id="wd1f90"
true/false
```

to:

```text id="6cq0o3"
1/0
```

within Version 1.

---

# 58. Nullability Compatibility

Changing:

```text id="i80k38"
delivery_address = null
```

from:

```text id="fz0e6k"
delivery_address always present
```

is potentially breaking.

Nullability must therefore be explicitly documented for fields.

---

# 59. Response Consistency Across Clients

The same API field must mean the same thing to:

```text id="4zk2u6"
Next.js
Flutter
Admin
```

Do not create:

```text id="ub9t9l"
price = cents in Flutter API
price = TZS in website API
```

unless they are explicitly different API resources/contracts.

---

# 60. Response Naming and MUI/Flutter

Frontend frameworks must adapt to the API representation.

Do not alter API field naming to suit:

```text id="1xkhs4"
React component naming
Flutter property naming
MUI component props
```

The API contract is shared infrastructure.

---

# 61. Serialization of Related Resources

When embedding a relationship:

```text id="64jr6g"
Product
  → images
```

embedded resources should follow the same resource serialization conventions.

Do not return:

```json
{
  "images": ["url1", "url2"]
}
```

on one endpoint and:

```json
{
  "images": [
    {
      "id": "1",
      "url": "..."
    }
  ]
}
```

on another unless explicitly documented.

---

# 62. Image Representation

Product images should eventually provide enough information for web/app rendering.

Potential fields:

```text id="w6xjf9"
id
url
alt_text
sort_order
is_primary
```

Do not expose internal storage keys unnecessarily.

The actual storage architecture remains outside this phase.

---

# 63. URL Fields

When returning public URLs:

* use valid URLs;
* keep environment-specific host configuration outside hardcoded resource logic;
* do not return internal filesystem paths.

Example:

```text id="l2zrdy"
/uploads/products/sofa.webp
```

may not be an acceptable public API value if the browser requires a full or CDN-accessible URL.

The exact URL strategy is implementation work later.

---

# 64. Security-Sensitive Links

Do not expose unrestricted private resource URLs.

For example, an attachment belonging to a private enquiry must not become a permanently public URL merely because it appears in JSON.

Attachment authorization and signed URL behavior are later security/storage work.

---

# 65. Response Correlation

Evaluate whether successful/error responses should expose a request correlation identifier.

This can aid debugging:

```text id="u7l8tr"
request_id
```

If adopted, define:

* format;
* scope;
* whether clients should display/store it.

Do not place sensitive information in it.

For a small project, this is optional but useful.

---

# 66. Recommended Envelope Baseline

Unless review identifies a compelling reason otherwise:

### Single resource

```json
{
  "data": {
    "id": "..."
  }
}
```

### Collection

```json
{
  "data": [],
  "meta": {
    "pagination": {}
  }
}
```

### Links

Include only where the approved API contract requires them.

### Errors

Reserved for the dedicated error contract phase.

---

# 67. Canonical Response Rules

Create the following global rules:

```text id="v4n5bk"
1. Successful responses use `data`.
2. Collections return `data` as an array.
3. Individual resources return `data` as an object.
4. Collection metadata is under `meta`.
5. Pagination metadata follows Phase 1.12.
6. JSON field names follow one naming convention.
7. Timestamps use one standard.
8. Booleans are JSON booleans.
9. Monetary values use one representation.
10. Nullability is explicitly defined.
11. Resource IDs are explicit.
12. Related resources follow one relationship representation policy.
13. Sensitive fields are never serialized by default.
14. Version 1 enums are CLOSED.
15. API version compatibility rules apply to response changes.
```

---

# 68. What Must Not Appear in Normal Responses

Do not expose:

```text id="1f4w0c"
password
password_hash
authentication tokens
refresh tokens unless explicitly contracted
payment secrets
provider credentials
database credentials
internal filesystem paths
private encryption keys
internal framework metadata
```

Avoid exposing internal database fields that have no domain/API meaning.

---

# 69. Data Leakage Review

Inspect whether common serialization techniques could accidentally expose sensitive fields.

Examples:

```text id="7gak0m"
full model serialization
debug fields
internal timestamps
admin notes
authorization flags
internal IDs
```

The API must use explicit representations.

---

# 70. Response Envelope and HTTP Status

Do not let the envelope obscure HTTP semantics.

For example:

A successful create operation should use the appropriate success status later, while still using:

```text id="ay4et8"
data
```

A failure will follow the future error contract.

The response shape and HTTP status are related but separate contract concerns.

---

# 71. Response Contract Documentation Style

Inside:

```text id="hfsq7c"
docs/api/api-contract.md
```

create sections such as:

```text
## Response Conventions

### Success Envelope
### Individual Resource
### Collection
### Metadata
### Pagination
### Identifiers
### Timestamps
### Money
### Nullability
### Booleans
### Enum Values
### Relationships
### Links
### Security/Field Exposure
### Compatibility
```

Do not create one markdown file for each section.

---

# 72. Update API Resource Documentation

Inside:

```text id="rr8xf1"
docs/api/api-resources.md
```

document, at the resource level:

```text id="x86yrw"
canonical representation
public fields
customer fields
staff/admin fields
relationship representations
sensitive fields
```

Do not yet specify every endpoint.

---

# 73. Update API Conventions

Inside:

```text id="8uhj7m"
docs/api/api-conventions.md
```

consolidate:

```text id="6v7wp4"
response envelope
naming
timestamps
money
nulls
booleans
enum behavior
relationships
serialization
compatibility
```

Avoid creating a separate permanent document for each convention.

---

# 74. OpenAPI Preparation

Do not fully implement OpenAPI resource schemas yet.

However, ensure the response rules are precise enough that a later OpenAPI phase can represent them consistently.

The OpenAPI document remains:

```text id="6v5a8e"
docs/api/openapi.yaml
```

and should be updated only when the relevant OpenAPI phase is reached.

---

# 75. Required Work Product

Update the following existing consolidated documents:

### `docs/api/api-contract.md`

Add/finalize:

```text
Response conventions
Success envelope
Collection envelope
Resource representation
Metadata
Links
Identifiers
Timestamps
Money
Nullability
Boolean representation
Enum response rules
Relationship representation
Serialization rules
Compatibility rules
```

### `docs/api/api-resources.md`

Update resource representation policies where required.

### `docs/api/api-conventions.md`

Record the reusable response/serialization conventions.

### `AGENTS.md`

Only update if the project's governing instructions need to reference the finalized response convention or document ownership.

Do not create additional permanent planning documents merely for Phase 1.13.

---

# 76. Decision Record

Do not create `api-response-decisions.md`.

Instead append the decisions to:

```text id="h0wpww"
docs/decisions.md
```

or the project's existing central decision record.

Use a format such as:

```text id="z2s2q6"
### ADR/API-013 — Response Envelope

Decision:
All successful API responses use a `data` member.

Reason:
Consistent client contract across Next.js, Flutter and Admin.

Status:
Accepted

Affected:
All Version 1 API resources.
```

Use the existing project decision-record convention if one has already been established.

---

# 77. Required Validation

### Envelope

* [ ] Successful responses use one consistent primary envelope.
* [ ] Individual resources use object data.
* [ ] Collections use array data.
* [ ] Metadata has a consistent location.
* [ ] Errors are separated from success semantics.

### Naming

* [ ] JSON naming convention is fixed.
* [ ] No mixed camelCase/snake_case conventions.
* [ ] Identifier naming is explicit.

### Values

* [ ] Boolean representation is fixed.
* [ ] Numeric representation is fixed.
* [ ] Timestamp representation is fixed.
* [ ] Money representation is fixed.
* [ ] Nullability rules are fixed.
* [ ] Enum representation is fixed.

### Relationships

* [ ] Parent/child representations are consistent.
* [ ] Recursive embedding is prevented.
* [ ] Restricted relationships are protected.
* [ ] Embedded vs referenced representations are documented.

### Security

* [ ] Sensitive fields are not serialized by default.
* [ ] Customer data exposure is authorization-aware.
* [ ] Internal inventory information is not publicly exposed.
* [ ] Payment secrets are never exposed.
* [ ] Authentication secrets are never exposed.
* [ ] Private attachments are not automatically public.

### Compatibility

* [ ] Version 1 response changes follow API versioning policy.
* [ ] Version 1 enums remain CLOSED.
* [ ] Changing field types is treated as potentially breaking.
* [ ] Changing field meaning is treated as breaking.
* [ ] Nullability changes are recognized as contract changes.

### Cross-platform

* [ ] Next.js can consume the response consistently.
* [ ] Flutter can consume the response consistently.
* [ ] Admin can consume the response consistently.

### Documentation

* [ ] Existing consolidated documents were updated.
* [ ] No unnecessary permanent phase-specific markdown files were created.
* [ ] Major decisions were recorded centrally.

---

# 78. Explicitly Out of Scope

Do NOT:

```text id="b31px6"
Define individual endpoint responses
Define complete Product JSON schema
Define complete Order JSON schema
Define request body schemas
Define error codes
Define HTTP status mappings
Implement Laravel Resources
Implement Laravel API Resources
Implement JSON serialization code
Create OpenAPI operations
Build Next.js API clients
Build Flutter API clients
Implement payment provider
```

Those belong to later phases.

---

# 79. Definition of Done

Phase 1.13 is complete when:

1. The success response envelope is fixed.
2. Individual-resource representation is fixed.
3. Collection representation is fixed.
4. Metadata placement is fixed.
5. Pagination metadata placement follows Phase 1.12.
6. Resource identifier representation is fixed.
7. JSON naming convention is fixed.
8. Timestamp representation is fixed.
9. Money representation is fixed.
10. Boolean representation is fixed.
11. Nullability rules are fixed.
12. Relationship representation is fixed.
13. Recursive embedding rules are fixed.
14. Sensitive field exposure rules are fixed.
15. Closed Version 1 enum behavior is explicit.
16. Response compatibility rules are documented.
17. Existing consolidated API documents contain the authoritative decisions.
18. No unnecessary permanent phase-specific documentation has been created.
19. No individual endpoint response has been implemented.

---

# 80. STOP CONDITION — Mandatory

After updating:

```text id="3z7z8a"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/decisions.md
```

and passing the validation checklist:

**STOP.**

Do not automatically define endpoint-specific responses.

Do not implement Laravel serializers.

Do not create OpenAPI endpoint schemas.

Do not build frontend API clients.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.14 — Define Request Body and Input Conventions

That phase should establish the project's standards for:

```text
JSON request bodies
field naming
required/optional fields
null handling
nested input
resource creation vs action input
partial updates
boolean/numeric/date formats
input normalization
unknown fields
mass-assignment protection
request size limits
```

while continuing to update the **small set of consolidated API documentation files** rather than creating a separate markdown file for every convention.
