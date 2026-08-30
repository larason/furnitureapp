# Phase 1.11 — Define Query Parameter Conventions

## 1. Purpose

Phase 1.11 establishes the project's standard conventions for **query parameters** used by collection and read-oriented API resources.

The goal is to ensure that the Next.js website, Flutter application, admin application, and future clients use one consistent query language for:

* searching;
* filtering;
* sorting;
* pagination;
* range queries;
* boolean conditions;
* inclusion of relationships;
* optional field selection;
* date/time filtering;
* availability filtering.

This phase defines the **query language conventions**, not the complete query parameters for individual endpoints.

---

# 2. Dependency Position

The current sequence is:

```text
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
1.10 HTTP Method Conventions
       ↓
1.11 Query Parameter Conventions
       ↓
1.12 Pagination Convention
       ↓
...
```

Phase 1.11 must not redefine the resource model, HTTP methods, or versioning strategy.

---

# 3. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3-domain-invariants.md
logical-data-model-v1.md
freeze-record.md

api-resource-inventory.md
api-resource-relationships.md
resource-relationship-map.md

api-versioning-strategy.md
api-breaking-change-policy.md

endpoint-naming-conventions.md
canonical-api-vocabulary.md
resource-path-policy.md
action-naming-policy.md
resource-nesting-policy.md

http-method-conventions.md
http-method-resource-matrix.md
api-action-method-policy.md
api-idempotency-candidates.md
```

Use the actual filenames present in the project if they differ.

Do not reopen settled business decisions.

---

# 4. Core Query Principle

Query parameters modify **how a resource collection is read**.

They must not silently become hidden business operations.

Good:

```text
GET /api/v1/products?category=sofas
```

Bad:

```text
GET /api/v1/products?delete=true
GET /api/v1/orders?cancel=true
```

Queries are for selection, presentation, and retrieval behavior—not mutation.

---

# 5. Query Parameter Naming Style

Establish one canonical naming convention.

Recommended:

```text
lowercase
snake_case or kebab-case consistently
```

Because URL query parameters are often easier to work with using `snake_case`:

```text
?page=2
?sort_by=price
?sort_direction=asc
?min_price=100000
?max_price=500000
```

Choose one convention and apply it consistently.

Do not mix:

```text
sortBy
sort_by
sort-by
```

in the same API.

---

# 6. Query Parameter Vocabulary

Create a distinction between:

### Generic query parameters

Reusable across multiple collections:

```text
page
per_page
sort
sort_direction
search
include
fields
```

### Resource-specific parameters

Specific to a particular resource:

```text
category
product_type
availability
min_price
max_price
```

Generic parameters should use a predictable global convention.

Resource-specific parameters should use domain language.

---

# 7. Query Parameter Case Sensitivity

Decide and document whether parameter names are:

```text
case-sensitive
```

Recommended:

> Query parameter names are lowercase and clients must use the documented spelling.

Do not permit arbitrary aliases such as:

```text
?Category=sofas
?CATEGORY=sofas
?category=sofas
```

unless there is a strong compatibility reason.

---

# 8. Unknown Query Parameters

Define how the API treats parameters it does not understand.

Recommended:

> Unknown query parameters should be rejected rather than silently ignored when they could indicate client/API contract mismatch.

However, document whether unknown parameters should produce:

```text
validation error
```

or simply be ignored.

For this project, prefer explicit validation for known resource collections so client mistakes are visible during development.

The exact HTTP error structure belongs to a later error-contract phase.

---

# 9. Search Convention

Define a generic search parameter:

```text
search
```

Conceptually:

```text
GET /products?search=modern+sofa
```

The search behavior should eventually specify:

* which fields are searchable;
* whether matching is exact/partial;
* whether matching is case-insensitive;
* how multiple terms behave;
* whether relevance ordering exists.

Do not decide the search engine.

Do not introduce Elasticsearch/OpenSearch merely because search exists.

---

# 10. Search Scope

Search must use business-relevant public fields.

For products, potential searchable information includes:

```text
Product name
SKU/reference where public
Description where appropriate
Category
Variant labels/attributes
```

Do not automatically search internal fields.

For example, internal inventory notes must not become public search criteria.

---

# 11. Search vs Filter

Maintain the distinction:

```text
search
→ broad textual discovery

filter
→ exact/structured condition
```

Example:

```text
?search=sofa
?category=sofas
?product_type=IN_STOCK
```

Do not create:

```text
?find=sofa
?lookup=sofa
?filter=sofa
```

for the same purpose.

---

# 12. Category Filtering

Product collections will eventually need category filtering.

Conceptually:

```text
GET /products?category=sofas
```

or another canonical identifier/reference.

Determine whether the query accepts:

```text
category slug
category identifier
```

and record the policy.

Do not define the final product endpoint yet.

---

# 13. Product Type Filtering

The approved Version 1 product types are:

```text
IN_STOCK
MADE_TO_ORDER
```

The query convention should support filtering by the documented closed enum.

Example conceptually:

```text
?product_type=IN_STOCK
```

Because all Version 1 enums are **CLOSED**, clients may rely on the documented value set.

Any later new enum value requires explicit compatibility review.

---

# 14. Availability Filtering

The public catalog may need to filter products by customer-visible availability.

Examples conceptually:

```text
?availability=available
?availability=unavailable
```

Do not expose internal inventory concepts such as:

```text
reserved_quantity
stock_adjustment
inventory_transaction
```

as public query parameters.

Public availability and internal inventory remain separate.

---

# 15. Price Filtering

Define a consistent numeric range convention.

Recommended:

```text
min_price
max_price
```

Example:

```text
?min_price=500000&max_price=1500000
```

The meaning must be:

```text
price >= min_price
price <= max_price
```

Do not create ambiguous:

```text
?price=500000-1500000
```

unless a formal range syntax is later chosen globally.

---

# 16. Numeric Query Parameter Validation

For numeric parameters:

```text
page
per_page
min_price
max_price
```

define:

* accepted numeric representation;
* integer vs decimal;
* minimum values;
* maximum values;
* invalid-input behavior.

Prices should eventually respect the application's currency precision.

Do not hardcode currency-specific assumptions here unless already approved.

---

# 17. Range Convention

Use explicit lower/upper boundaries for ranges.

Preferred:

```text
min_price
max_price
```

Future generic examples:

```text
min_width
max_width
min_weight
max_weight
```

if those filters are later required.

Do not introduce arbitrary comparison syntax such as:

```text
price[gte]
price[lte]
```

without a deliberate project-wide choice.

---

# 18. Boolean Parameters

Boolean parameters must have one canonical representation.

Recommended:

```text
true
false
```

Example:

```text
?is_active=true
```

Avoid mixing:

```text
?is_active=1
?is_active=yes
?is_active=true
```

across endpoints.

Define whether uppercase variants are accepted.

Recommended:

> API documentation uses lowercase `true` and `false`.

---

# 19. Multiple Values

The project must choose how a parameter accepts multiple values.

Potential options:

```text
?category=sofas,beds
```

or:

```text
?category[]=sofas&category[]=beds
```

or:

```text
?category=sofas&category=beds
```

Choose one consistent representation.

For a relatively small REST API, prefer the simplest representation that Laravel, Next.js, Flutter, and standard HTTP tooling handle predictably.

Do not support multiple syntaxes unnecessarily.

---

# 20. Multiple Filter Semantics

Document the difference between:

```text
AND
OR
```

For example:

```text
?product_type=IN_STOCK&category=sofas
```

should normally mean:

```text
product type = IN_STOCK
AND
category = sofas
```

If multi-value parameters are supported, define whether:

```text
category=sofas,beds
```

means:

```text
category = sofas OR beds
```

or something else.

Do not leave this ambiguous.

---

# 21. Sorting Convention

Choose one global sorting structure.

Recommended:

```text
sort
```

with a documented value such as:

```text
?sort=price
```

and a separate:

```text
sort_direction=asc
```

or a compact convention such as:

```text
?sort=-price
```

Choose one.

Do not mix:

```text
sort
sort_by
orderby
order_by
```

across resources.

---

# 22. Sorting Direction

Support:

```text
asc
desc
```

as the canonical direction values.

Example:

```text
?sort=price&sort_direction=asc
```

The exact implementation is later.

---

# 23. Default Sorting

Every sortable collection should have an explicit default ordering.

For example, Product listing might default to:

```text
catalog-defined ordering
```

rather than:

```text
whatever order the database happens to return
```

The exact product default should be established when the Product endpoint is designed.

The rule here is:

> API consumers must not depend on undocumented ordering.

---

# 24. Allowed Sort Fields

Clients must not be allowed to sort by arbitrary database columns.

Example:

Bad:

```text
?sort=password_hash
```

or:

```text
?sort=internal_inventory_transaction_id
```

The API must expose a controlled allow-list of sort fields per resource.

The specific allow-list belongs to later resource endpoint contracts.

---

# 25. Pagination

Pagination is part of collection retrieval.

The generic query convention should leave room for:

```text
page
per_page
```

or the project's chosen pagination model.

Do not finalize the complete pagination contract in Phase 1.11.

A dedicated pagination phase will define:

* page numbering;
* page size;
* maximum page size;
* metadata;
* navigation;
* cursor pagination if needed.

---

# 26. Query Parameter vs Request Body

GET collection filtering belongs in the query string.

Do not use a request body for ordinary collection filtering such as:

```text
search
category
price range
sort
```

Example:

```text
GET /products?category=sofas
```

rather than:

```text
GET /products
{
  "category": "sofas"
}
```

---

# 27. Query Parameter vs Path Parameter

Use path structure for resource identity:

```text
/products/{product}
```

Use query parameters for collection selection:

```text
/products?category=sofas
```

Do not turn identity into a query parameter:

```text
/products?id=123
```

when addressing a specific resource.

---

# 28. Query Parameter vs Action

Do not encode business actions as query parameters.

Bad:

```text
/orders/{order}?action=cancel
```

Good conceptual separation:

```text
order resource
+
controlled cancellation action
```

The actual action endpoint is defined later.

---

# 29. Query Parameters Must Not Mutate State

All collection query parameters on GET must remain read-only.

Bad:

```text
/products?activate=true
/orders?cancel=true
/cart?checkout=true
```

Query parameters must not become hidden commands.

---

# 30. Date/Time Query Parameters

Define a convention for future date/range filters.

Potential format:

```text
created_from
created_to
```

or:

```text
from_created_at
to_created_at
```

Choose one consistent pattern.

Use ISO 8601-compatible timestamps when date-time values are required.

Example conceptually:

```text
?created_from=2026-08-01T00:00:00Z
```

Do not implement timezone behavior yet.

---

# 31. Date Range Semantics

Define whether boundaries are inclusive.

Recommended:

```text
created_from
→ greater than or equal to

created_to
→ less than or equal to
```

Document this consistently.

Do not allow one endpoint to interpret range boundaries differently from another.

---

# 32. Timezone Rules

Document that timestamps with time components should use an unambiguous timezone representation.

For APIs, prefer UTC-based representation unless a resource-specific business rule requires another timezone.

The server should not interpret a bare ambiguous timestamp differently depending on server location.

---

# 33. Query Encoding

All query parameters must be properly URL-encoded.

Clients should not manually construct unsafe URLs from raw user search input.

This is especially relevant for:

```text
search
text values
special characters
spaces
```

Do not implement client escaping in this phase; establish the rule.

---

# 34. Empty Values

Define how the API treats:

```text
?search=
?category=
?min_price=
```

Recommended:

> Empty values should either be treated as absent or rejected consistently; the project must not give different semantics to empty parameters across endpoints.

For critical typed parameters, validation may reject an empty value.

Record the final convention.

---

# 35. Null Values

Do not assume:

```text
?field=null
```

means the same as:

```text
field absent
```

unless a later resource contract explicitly defines it.

Query semantics should distinguish:

```text
parameter absent
parameter empty
parameter explicitly null
```

where that distinction matters.

---

# 36. Repeated Parameters

Decide whether repeated parameters are allowed.

Example:

```text
?sort=price&sort=name
```

If multiple sorting criteria are not supported, reject duplicates rather than silently choosing one.

If multi-value filtering is supported, use the globally selected representation.

Document this clearly.

---

# 37. Query Ordering

The order of query parameters should not change their meaning.

These should be logically equivalent:

```text
?category=sofas&min_price=500000
```

and:

```text
?min_price=500000&category=sofas
```

This helps:

* caching;
* testing;
* client consistency;
* debugging.

---

# 38. Query Canonicalization

Where caching or signatures later matter, define a canonical ordering/normalization strategy.

Do not implement it now.

The important rule:

> Equivalent queries should have predictable semantic equivalence even if parameter order differs.

---

# 39. Query Parameter Security

Do not allow query parameters to:

* expose hidden records;
* bypass authorization;
* alter prices;
* change order states;
* expose internal inventory information;
* select another customer's resources;
* reveal sensitive fields.

Example:

```text
GET /orders?user_id=123
```

must not automatically allow a customer to retrieve another user's orders.

Ownership is determined by authorization, not by a client-supplied query parameter.

---

# 40. Customer Order Queries

For customer-owned collections, query parameters must never override ownership.

For example:

```text
GET /me/orders?user_id=another-user
```

must not change the effective owner.

The server determines the authenticated customer's identity.

---

# 41. Admin Filtering

Admin collections may require richer filters.

Examples later:

```text
status
customer
date range
payment state
fulfillment type
```

These are legitimate operational filters.

However, admin query parameters must remain subject to authorization.

Do not create a separate query-language grammar for admin unless necessary.

---

# 42. Product Query Requirements

The product collection will eventually need to support combinations such as:

```text
search
category
product_type
availability
min_price
max_price
sort
pagination
```

Example conceptually:

```text
/products
?search=sofa
&category=sofas
&product_type=IN_STOCK
&min_price=500000
&max_price=1500000
&sort=price
&sort_direction=asc
```

The exact endpoint and final parameter set belong to later contract phases.

---

# 43. Enum Query Parameters

For closed Version 1 enums:

```text
product_type
fulfillment_type
order_status
request_status
enquiry_status
```

the query parameter must accept only documented values.

Unknown enum values should be handled consistently according to the later validation/error contract.

Do not interpret unknown values as wildcard/other.

---

# 44. Case Sensitivity of Enum Values

Choose one canonical representation.

Recommended:

```text
IN_STOCK
MADE_TO_ORDER
```

or another single project-wide representation.

Since these enums are closed, clients can rely on the documented values.

Do not accept arbitrary variants such as:

```text
in_stock
InStock
in-stock
```

unless the contract explicitly defines them as aliases.

Prefer one canonical value.

---

# 45. Field Selection

Evaluate whether Version 1 needs:

```text
fields
```

for sparse fieldsets.

Example:

```text
/products?fields=id,name,price
```

For a small application, this may be unnecessary initially.

Do not add `fields` merely because some APIs have it.

If it is deferred, document that.

---

# 46. Relationship Inclusion

Evaluate whether Version 1 needs:

```text
include
```

to request related resources.

Example:

```text
/products?include=category,variants
```

Do not automatically introduce this mechanism.

MUI/Next.js/Flutter clients can use predictable resource responses without a generic dynamic include language.

If the project does not require it in Version 1, defer it.

---

# 47. Avoid Generic Query Languages

Do not create an overly flexible syntax such as:

```text
filter[field][operator]=value
```

for every possible condition unless the product genuinely needs advanced query capabilities.

This is a small-scale commerce platform.

Prefer:

```text
?category=sofas
?min_price=500000
?max_price=1500000
?product_type=IN_STOCK
```

over a complex universal query DSL.

---

# 48. Query Parameter Allow-Lists

Every resource should later define an allow-list:

```text
supported query parameters
```

Unknown parameters must not silently become database column names.

For example:

```text
?password=...
```

must never be translated into arbitrary database filtering.

---

# 49. Query Depth / Complexity Limits

The API should eventually impose reasonable limits on expensive queries.

Examples:

```text
search length
maximum page size
maximum number of filters
maximum date range
maximum included relationships
```

Do not implement limits yet.

Identify them as later performance/security requirements.

---

# 50. Query and Caching

Consistent query semantics are important for caching.

For example:

```text
/products?category=sofas
```

should represent a deterministic read request.

Do not change the meaning of a query parameter based on server state unrelated to the documented resource rules.

Caching implementation is later.

---

# 51. Query and SEO

Public product/category queries used by Next.js must remain deterministic and crawl-friendly.

Avoid exposing arbitrary combinations that generate millions of near-duplicate public pages.

The website can use query parameters for:

```text
interactive filters
```

without necessarily making every filtered URL an SEO-indexed page.

SEO URL strategy remains separate from this API query contract.

---

# 52. Query and Flutter

Flutter may issue queries for:

```text
catalog filtering
search
sorting
pagination
```

The convention must be platform-neutral.

Do not create Flutter-specific query syntax.

---

# 53. Query and Admin

The admin application can consume the same query conventions.

Example future:

```text
/orders?status=PROCESSING
```

The difference is authorization, not a different query language.

---

# 54. Query Parameter Precedence

Document precedence when parameters overlap.

Examples:

```text
search + exact product identifier
```

or:

```text
min_price > max_price
```

Define validation expectations later.

Recommended:

> Contradictory values are validation errors rather than silently corrected.

For example:

```text
min_price=1500000
max_price=500000
```

should not silently swap them.

---

# 55. Default Values

Do not leave defaults implicit.

For each generic parameter that eventually has a default, document it.

Examples:

```text
page
per_page
sort_direction
```

The exact values belong to later phases, especially pagination.

---

# 56. Query Parameter Documentation Format

Every future endpoint contract should document query parameters using:

```text
Name
Type
Required/optional
Allowed values
Default
Description
Validation
Security/access implications
Example
```

Example:

```text
Parameter: product_type
Type: enum
Required: No
Allowed: IN_STOCK, MADE_TO_ORDER
Default: none
Description: Filters products by commercial type.
Validation: Closed Version 1 enum.
```

---

# 57. Required Deliverables

Produce:

## A. `query-parameter-conventions.md`

Define:

```text
naming
case
encoding
unknown parameters
empty values
boolean values
enum values
multiple values
range values
dates
```

---

## B. `query-filtering-policy.md`

Define:

```text
search
exact filters
range filters
AND/OR semantics
resource-specific filters
filter allow-lists
```

---

## C. `query-sorting-policy.md`

Define:

```text
sort parameter
sort direction
default sorting
allowed sort fields
multiple sort rules
```

---

## D. `query-search-policy.md`

Define:

```text
search parameter
searchable concepts
matching philosophy
minimum/maximum input considerations
case handling
```

Do not select a search engine.

---

## E. `query-relationship-policy.md`

Document the decision on whether Version 1 supports:

```text
include
fields
relationship expansion
```

If deferred, explicitly record that they are deferred.

---

## F. `query-security-policy.md`

Document:

```text
ownership
allow-lists
sensitive fields
resource authorization
complexity limits
```

---

## G. `query-enum-policy.md`

Explicitly record:

> All Version 1 enum query parameters use CLOSED enum semantics.

Document the currently known enums and state that additions require compatibility review.

---

## H. `query-parameter-decisions.md`

Record all Phase 1.11 decisions.

Format:

```text
Decision ID
Decision
Reason
Alternatives
Affected resources
Future phase
```

---

# 58. Validation Checklist

### Naming

* [ ] Query parameter naming is standardized.
* [ ] Query parameter case is standardized.
* [ ] Generic and resource-specific parameters are distinguished.
* [ ] No duplicate names exist for the same concept.

### Search

* [ ] `search` semantics are defined.
* [ ] Search is distinct from filtering.
* [ ] Search does not expose internal fields.
* [ ] Search does not mutate state.

### Filtering

* [ ] Exact-filter semantics are defined.
* [ ] Range-filter semantics are defined.
* [ ] Multiple-value semantics are defined.
* [ ] AND/OR semantics are explicit.
* [ ] Contradictory filters are treated consistently.

### Sorting

* [ ] Sort parameter convention is defined.
* [ ] Direction convention is defined.
* [ ] Allowed sort fields are controlled.
* [ ] Default ordering is recognized as necessary.

### Booleans

* [ ] `true`/`false` representation is defined.
* [ ] No mixed boolean conventions are permitted.

### Enums

* [ ] Version 1 enums are CLOSED.
* [ ] Unknown enum values do not silently become wildcards.
* [ ] Product type query values follow the canonical enum representation.
* [ ] Future enum additions require compatibility review.

### Dates

* [ ] Date/time query representation is defined.
* [ ] Range semantics are defined.
* [ ] Timezone ambiguity is avoided.

### Security

* [ ] Query parameters cannot override customer ownership.
* [ ] Internal fields are not filterable by arbitrary name.
* [ ] Admin filters remain authorization-controlled.
* [ ] Expensive query mechanisms are recognized as needing limits.

### Cross-platform

* [ ] Conventions work for Next.js.
* [ ] Conventions work for Flutter.
* [ ] Conventions work for Admin.
* [ ] No frontend-specific query language exists.

### SEO

* [ ] API query conventions remain independent of public SEO URLs.
* [ ] Query parameters do not accidentally redefine public resource identity.

---

# 59. Explicitly Out of Scope

Do NOT:

```text
Define exact product endpoint parameter lists
Define exact order endpoint parameter lists
Define pagination response format
Define HTTP status codes
Define error JSON
Implement Laravel validation
Implement database queries
Create controllers
Create repositories
Create OpenAPI operations
Build Next.js
Build Flutter
Implement search engine
Implement caching
```

---

# 60. Definition of Done

Phase 1.11 is complete when:

1. Query parameter naming is standardized.
2. Query case rules are standardized.
3. Search conventions are defined.
4. Filtering conventions are defined.
5. Range conventions are defined.
6. Boolean conventions are defined.
7. Multiple-value conventions are defined.
8. Sorting conventions are defined.
9. Date/time query conventions are defined.
10. Unknown/empty parameter behavior is defined.
11. Closed enum behavior is explicitly documented.
12. Relationship expansion policy is decided or explicitly deferred.
13. Field selection policy is decided or explicitly deferred.
14. Query security principles are defined.
15. Ownership cannot be overridden by query parameters.
16. Query complexity considerations are identified.
17. SEO/query separation is explicit.
18. No individual endpoint contract has been implemented.

---

# 61. STOP CONDITION — Mandatory

After completing:

```text
query-parameter-conventions.md
query-filtering-policy.md
query-sorting-policy.md
query-search-policy.md
query-relationship-policy.md
query-security-policy.md
query-enum-policy.md
query-parameter-decisions.md
```

and passing the validation checklist:

**STOP.**

Do not automatically proceed to endpoint-specific parameter definitions.

Do not implement Laravel query logic.

Do not create OpenAPI parameters.

Do not create frontend API calls.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.12 — Define Pagination Convention

That phase should establish the exact pagination strategy, including page numbering, page-size rules, maximum limits, response metadata, navigation links/fields, empty-page behavior, consistency under changing data, and whether cursor pagination is needed for any Version 1 collection.
