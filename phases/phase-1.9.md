# Phase 1.9 — Define Endpoint Naming Conventions

## 1. Purpose

Phase 1.9 establishes the **naming and URL-structure conventions** for the API.

The goal is to ensure that every endpoint created later follows one predictable style regardless of whether it belongs to:

* the public catalog;
* customer commerce;
* customer account;
* customer requests/enquiries;
* staff operations;
* admin operations.

This phase defines the rules for:

* URL structure;
* resource naming;
* pluralization;
* identifiers;
* nested resources;
* collection vs individual resources;
* relationship paths;
* action endpoints;
* special operation naming;
* customer-owned resources;
* administrative boundaries.

This phase does **not** create the actual endpoint catalog.

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
1.9  Endpoint Naming Conventions
       ↓
1.10 HTTP Method Conventions
       ↓
...
```

Phase 1.9 must use the frozen outputs from 1.1–1.8.

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
resource-access-matrix.md
api-resource-relationships.md
resource-relationship-map.md

api-versioning-strategy.md
api-breaking-change-policy.md
api-client-compatibility.md
api-deprecation-policy.md
api-versioning-matrix.md
```

Use the project's actual filenames if they differ.

Do not reopen settled business decisions.

---

# 4. Primary Naming Principle

API URLs should represent **business resources and relationships**, not database implementation details.

Preferred:

```text
/products
/categories
/orders
/requests
/enquiries
```

Avoid:

```text
/tbl_products
/productRecords
/orderModels
/getProducts
/processOrderData
```

The URL describes the resource.

The HTTP method, defined later, describes the operation.

---

# 5. Base API Structure

Use the Version 1 strategy approved in Phase 1.8.

Recommended structure:

```text id="pi0jvx"
/api/v1/...
```

The exact domain is not defined here.

Example:

```text id="xhtah0"
/api/v1/products
```

This is a naming convention, not yet a complete endpoint specification.

---

# 6. Lowercase URL Convention

Use lowercase URL path segments.

Preferred:

```text id="67kr2l"
 /api/v1/products
 /api/v1/categories
 /api/v1/orders/{order}/status-history
```

Avoid:

```text id="l1gq12"
/api/v1/Products
/api/v1/ProductCategories
/api/v1/OrderStatus
```

---

# 7. Plural Resource Names

Use plural nouns for collection resources.

Preferred:

```text id="6aknjt"
/products
/categories
/orders
/payments
/requests
/enquiries
/notifications
```

Avoid:

```text id="oqj6d5"
/product
/category
/order
/payment
```

A collection represents multiple members.

---

# 8. Use Nouns, Not Verbs

Do not name ordinary resources using actions.

Preferred:

```text id="o1u5yf"
/orders
```

Avoid:

```text id="ztrc2e"
/getOrders
/createOrder
/fetchProducts
```

The operation belongs to the HTTP method or explicit business-action endpoint later.

---

# 9. URL Path Meaning

Establish:

```text id="iajsrm"
Collection:
 /products

Individual resource:
 /products/{product}

Child collection:
 /products/{product}/variants
```

Conceptually:

```text id="zwptc7"
/resource
/resource/{identifier}
/resource/{identifier}/subresource
```

Do not define actual endpoint methods yet.

---

# 10. Resource Identifier Convention

Use an opaque/stable identifier where appropriate.

The API URL should not expose database implementation details unnecessarily.

For example:

```text id="pxt5q6"
/products/{product}
```

rather than assuming:

```text id="v7n1ez"
/products/{mysql-auto-increment-id}
```

The exact identifier strategy is a later data/API implementation decision.

However, the naming convention must allow:

```text id="vpk0k1"
internal ID
customer-facing identifier
slug
```

to remain distinct.

---

# 11. Numeric ID vs Slug

Distinguish two concepts:

### Resource identity

Used to uniquely identify a resource.

### Human-readable slug

Used primarily for public catalog navigation/SEO.

For example:

```text id="z3se3m"
Product internal identity:
12345

Product slug:
modern-3-seater-sofa
```

Do not assume the API's resource identifier and public website slug must be the same.

---

# 12. Public Catalog URL Principle

The Next.js website needs SEO-friendly public URLs.

A public website route may be:

```text id="2fi1xk"
/products/modern-3-seater-sofa
```

while the API can have its own resource identification strategy.

Do not force SEO URLs and API URLs to become the same architecture.

---

# 13. API Resource Path vs Website Route

Explicitly document:

```text id="c2m3tk"
API path
≠
website route
```

Example:

```text id="sw1q3u"
Website:
example.com/products/modern-3-seater-sofa

API:
api.example.com/api/v1/products/...
```

The actual domains are not defined in this phase.

---

# 14. Categories

Use:

```text id="3v4yl2"
/categories
/categories/{category}
```

for conceptual category resources.

Category-product relationship can later use:

```text id="w8i6kk"
/categories/{category}/products
```

if that structure is useful.

Do not decide whether filtering `/products?category=...` or nested category products is the primary mechanism until later query/filter design.

---

# 15. Products

Use:

```text id="5f0xbs"
/products
/products/{product}
```

Potential child relationships:

```text id="x0kqol"
/products/{product}/images
/products/{product}/variants
```

These are naming candidates, not final endpoint definitions.

---

# 16. Product Images

Treat images primarily as a child of Product.

Conceptually:

```text id="7svh7n"
/products/{product}/images
```

Avoid:

```text id="x4bj5m"
/images
```

as the primary public design unless an independent image-management requirement later proves necessary.

---

# 17. Product Variants

Treat variants as a Product-owned child resource.

Conceptually:

```text id="feqnh4"
/products/{product}/variants
/products/{product}/variants/{variant}
```

The nested structure reinforces:

```text id="2uw8dt"
Variant belongs to Product
```

It also helps prevent accidentally referencing a variant under the wrong product.

---

# 18. Availability

Availability is a customer-facing representation rather than necessarily an independent resource.

Prefer conceptual structures such as:

```text id="22d5zi"
/products/{product}
```

containing an availability representation.

Or a subresource if later required:

```text id="2e7vwx"
/products/{product}/availability
```

Do not expose internal inventory internals simply to provide public availability.

---

# 19. Inventory

Inventory is operational/internal.

Conceptually:

```text id="zfy9eq"
/inventory
```

should not automatically become a public endpoint.

If staff/admin management later requires a path, it must clearly belong to the authorized operational API.

Do not define it here.

---

# 20. Cart

Cart is a customer-owned resource.

Conceptual candidate:

```text id="4mye5o"
/carts/{cart}
```

However, avoid automatically exposing arbitrary customer's carts.

A customer-facing design may later use a self-context route such as:

```text id="l2yurd"
/me/cart
```

or another ownership-safe convention.

This phase must decide the naming policy but not yet select all final endpoints.

---

# 21. Current User / Self Resources

Consider the use of:

```text id="d3yw6f"
/me
```

for authenticated self-context.

Potential conceptual subresources:

```text id="ijm3s7"
/me/profile
/me/orders
/me/notifications
/me/requests
/me/enquiries
```

Evaluate whether this reduces unnecessary exposure of user IDs for customer-owned data.

Recommended principle:

> Customer-facing APIs should prefer self-context for operations that only concern the currently authenticated customer.

Do not define the final endpoint catalog yet.

---

# 22. Orders

Primary resource:

```text id="7q1e4y"
/orders
/orders/{order}
```

For customer APIs, ownership rules must ensure a customer can only access their own orders.

Do not make:

```text id="4g9s44"
/orders/{arbitrary-order}
```

implicitly public.

The path naming convention and authorization policy must work together.

---

# 23. Order Child Resources

Possible child structures:

```text id="ou2n0l"
/orders/{order}/items
/orders/{order}/tracking
/orders/{order}/status-history
/orders/{order}/delivery
/orders/{order}/payment
```

Each must later be evaluated according to:

* whether it is independently addressable;
* whether it is read-only;
* whether it should instead be embedded;
* whether exposing it creates security concerns.

Do not assume every child must get an endpoint.

---

# 24. Order Actions

Some operations represent business actions rather than ordinary resource replacement.

Candidates:

```text id="ojap1s"
cancel
accept
process
ready-for-pickup
ship
deliver
```

Do not create awkward verb resources such as:

```text id="8d8i0r"
/cancel-order
/process-order
/ship-order
```

unless a later action-endpoint rule explicitly justifies them.

Phase 1.9 should define the preferred naming pattern for actions, but the actual actions will be detailed later.

---

# 25. Recommended Action Naming Principle

Where an explicit action endpoint is necessary, prefer a consistent action representation such as:

```text id="8g68yc"
/orders/{order}/cancel
```

or another single approved convention.

Do not mix styles such as:

```text id="e0z7js"
/orders/{order}/cancel
/orders/{order}/ship-order
/orders/{order}/markAsDelivered
```

Choose one convention and use it consistently.

Do not yet define which actions will use action endpoints.

---

# 26. Avoid CRUD Abuse

Do not represent every business action as:

```text id="m8i4s5"
PATCH /orders/{id}
{
  "status": "SHIPPED"
}
```

simply because status is a field.

Some status transitions are business operations with authorization and invariants.

Later API design must distinguish:

```text id="9drgqv"
ordinary editable data
vs
controlled domain action
```

Phase 1.9 only establishes the naming principle.

---

# 27. Requests

Made-to-order requests are first-class resources.

Conceptual:

```text id="3pqd3m"
/requests
/requests/{request}
```

Do not call these:

```text id="igv9pu"
/product-requests
```

unless that is explicitly the approved domain term.

The project domain language is:

> Made-to-order Request

The API resource can be represented by a concise canonical noun such as:

```text id="f83s9d"
requests
```

while the documentation explains its business meaning.

---

# 28. Enquiries

Use:

```text id="q57n2r"
/enquiries
/enquiries/{enquiry}
```

Do not merge requests and enquiries into:

```text id="d2r2ol"
/communications
```

because they represent distinct business concepts.

---

# 29. Attachments

Attachments belong to their owning resource.

Potential structures:

```text id="v3l9xa"
/requests/{request}/attachments
/enquiries/{enquiry}/attachments
```

Avoid a generic public endpoint:

```text id="3e2zh1"
/attachments
```

unless a specific administrative requirement later justifies independent addressing.

---

# 30. Notifications

Potential:

```text id="ol1y0g"
/notifications
```

or customer self-context:

```text id="9k4gkk"
/me/notifications
```

Use whichever convention is selected for self-owned resources.

The principle is:

> Notifications are private and scoped to their recipient.

---

# 31. Payments

Payment resources must remain separate from Orders.

Potential conceptual structure:

```text id="xxrm91"
/orders/{order}/payment
```

or a separately addressable resource where justified.

Do not define the final structure until payment-specific API work in **Phase Group H**.

This phase should only establish naming principles.

---

# 32. Payment Phase Assignment

Explicitly record:

```text id="o7yqaz"
Payment implementation and provider-specific API design
→ Phase Group H
```

Do not use Group G in any new documentation.

If earlier generated documents contain Group G for payment, mark that assignment as superseded in the project's planning notes.

---

# 33. Delivery

Delivery is associated with Order.

A conceptual nested structure may be:

```text id="0pr5v1"
/orders/{order}/delivery
```

Do not expose delivery as a global public collection simply because many orders have deliveries.

Customer access should remain scoped to their own order.

---

# 34. User/Profile

Avoid arbitrary paths such as:

```text id="k1d7dp"
/users/{id}/profile
```

for ordinary customer self-service if `/me` is the established convention.

A self-context design can be:

```text id="xk28y9"
/me
/me/profile
```

The exact choice is finalized later.

---

# 35. Staff/Admin Resource Boundaries

Do not encode role names into every resource.

Avoid:

```text id="iw3qcg"
/admin-products
/admin-orders
/admin-users
```

as the default.

Prefer the same domain resource vocabulary with different authorization contexts.

For example:

```text id="7d6omk"
products
orders
requests
enquiries
```

with staff/admin permissions controlling access.

Administrative route grouping can be considered later where it genuinely improves separation, but it must not duplicate the domain vocabulary.

---

# 36. Administrative Prefix Decision

Evaluate whether the project requires:

```text id="as6m3f"
/api/v1/admin/...
```

for administrative routes.

Consider:

```text security
authorization clarity
operational separation
frontend architecture
future API governance
```

Do not choose it merely because an "admin" application exists.

Record the final convention in the Phase 1.9 decision document.

---

# 37. Collection vs Item Naming

Use consistent semantics:

```text id="h5wr8r"
/products
```

means collection.

```text id="muw7ks"
/products/{product}
```

means one product.

Never use plural identifiers for an individual resource:

```text id="gg34o0"
/products/{products}
```

and never use singular collection names.

---

# 38. Relationship Path Naming

Use the actual relationship noun.

Preferred:

```text id="8y4f7n"
/products/{product}/variants
/orders/{order}/items
/orders/{order}/tracking
/requests/{request}/attachments
```

Avoid:

```text id="8bl19x"
/products/{product}/getVariants
/orders/{order}/children
```

---

# 39. Avoid Deep Nesting

Set a default maximum nesting philosophy.

Recommended:

```text id="yav7yo"
Keep public API nesting shallow.
```

Typical:

```text id="wnks2m"
/orders/{order}/items
```

is reasonable.

Avoid chains such as:

```text id="88cb4k"
/users/{user}/orders/{order}/items/{item}/product/{product}/variants/{variant}
```

Such paths become difficult to understand and authorize.

Prefer references or separate resources once relationships become too deep.

---

# 40. Identifier Consistency

Use the same identifier naming concept consistently.

Examples:

```text id="u8jupi"
{product}
{category}
{order}
{request}
{enquiry}
```

Avoid mixing:

```text id="oz9f6r"
{id}
{productId}
{product_id}
{productID}
```

within URL path templates.

The exact API schema naming style for JSON fields is later work.

---

# 41. Special Characters

Avoid unnecessary special characters in paths.

Preferred:

```text id="5dz7bx"
/status-history
```

Use hyphens for multi-word path segments where needed.

Avoid:

```text id="lzlx8b"
/status_history
/statusHistory
/StatusHistory
```

Choose one convention.

Recommended:

```text id="s4b4ox"
kebab-case
```

for multi-word URL segments.

---

# 42. Resource Names vs Database Table Names

Do not require API resource naming to exactly match database table names.

For example:

```text id="08za7z"
Database concept:
order_status_history

API concept:
status-history
```

The API should use client-friendly domain language.

---

# 43. Action Names

Where action endpoints are justified later, use lowercase kebab-case.

Examples:

```text id="m7c4xk"
cancel
accept
ready-for-pickup
ship
deliver
```

Avoid:

```text id="n8s8pr"
cancelOrder
markReadyForPickup
SHIP_ORDER
```

Do not decide the complete action inventory yet.

---

# 44. Search and Filtering Are Not Resources

Do not create fake resources such as:

```text id="9mibxi"
/search-products
/filter-products
/sort-products
```

Search/filter/sort behavior belongs to collection query conventions in later phases.

Example conceptual structure:

```text id="7yg30t"
/products?...
```

The exact query syntax is deferred.

---

# 45. Pagination Is Not a Resource

Do not create:

```text id="39r9xr"
/products/page/2
```

unless a very specific requirement demands path-based pagination.

Pagination is a collection query concern and is handled in later query conventions.

---

# 46. Versioning Must Remain Consistent

All resource paths must live under the versioning strategy decided in Phase 1.8.

Do not create:

```text id="5keqtr"
/products
/api/v1/orders
/api/v2/enquiries
```

randomly.

Within the initial release, all contracted resources belong to:

```text id="2w8v3p"
v1
```

unless explicitly documented otherwise.

---

# 47. Public SEO URL Independence

The public web application's URL structure is independent.

Example conceptual relationship:

```text id="rj21hq"
Website:
 /products/modern-3-seater-sofa

API:
 /api/v1/products/{product}
```

Do not use API versioning or internal identifiers to design SEO URLs.

---

# 48. Resource Naming Consistency Table

Create a canonical vocabulary table.

Example:

| Business concept      | API resource name                    |
| --------------------- | ------------------------------------ |
| Category              | `categories`                         |
| Product               | `products`                           |
| Product Image         | `images` as Product child            |
| Product Variant       | `variants` as Product child          |
| Availability          | representation/subresource           |
| Inventory             | restricted operational resource      |
| Cart                  | `carts` / self-context               |
| Cart Item             | `items` as Cart child                |
| Order                 | `orders`                             |
| Order Item            | `items` as Order child               |
| Tracking              | `tracking`                           |
| Status History        | `status-history`                     |
| Payment               | `payment` / restricted               |
| Delivery              | `delivery`                           |
| Made-to-order Request | `requests`                           |
| Enquiry               | `enquiries`                          |
| Attachment            | `attachments`                        |
| User/Profile          | `me` or `users` according to context |
| Notification          | `notifications`                      |

Record the final decisions.

---

# 49. Naming Consistency Rules

The API must never have multiple names for the same business concept.

For example, do not alternate between:

```text id="t2h0o3"
request
product-request
furniture-request
made-to-order-request
```

Use one canonical resource name.

The descriptive phrase can still appear in documentation:

```text id="t1w0h0"
API resource: requests
Business concept: made-to-order request
```

---

# 50. API Domain Vocabulary

Create a canonical vocabulary list for:

```text id="ixk2by"
product
category
variant
inventory
cart
item
order
payment
delivery
tracking
request
enquiry
attachment
notification
user
```

This vocabulary becomes authoritative for all later phases.

---

# 51. Naming Decision on `/me`

Make a deliberate decision.

Evaluate:

```text id="iw5v7m"
Option A
/users/{user}

Option B
/me

Option C
Both depending on authorization/context
```

For customer-facing self-service, `/me` often makes ownership clearer and avoids exposing unnecessary user identifiers.

For administrative user management, a separate user-management resource may still be needed later.

Record the final decision but do not define its endpoints yet.

---

# 52. Naming Decision on Admin Separation

Make a deliberate decision about whether administrative API paths should be:

```text id="5x3zpj"
same resource namespace
```

or:

```text id="q1h7p9"
/admin/...
```

Consider:

* authorization;
* documentation clarity;
* operational separation;
* future staff/admin applications;
* duplication risk.

Prefer consistency over arbitrary separation.

---

# 53. Naming Decision on Child Resources

For each child resource decide whether its canonical path is:

```text id="t5pz7l"
nested
```

or:

```text id="42j8qp"
independent
```

Candidates to review:

```text id="j2xv00"
Product Images
Product Variants
Cart Items
Order Items
Order Tracking
Order Status History
Order Delivery
Request Attachments
Enquiry Attachments
```

Do not force an answer based only on technical convenience.

Use the domain ownership model from Phase 1.7.

---

# 54. Naming Rules for Future Expansion

Ensure the naming strategy can accommodate:

```text id="7b0o5z"
wishlist
reviews
coupons
promotions
saved addresses
push notifications
delivery tracking
additional payment providers
```

without changing the meaning of existing resource names.

Do not add those resources to Version 1.

---

# 55. Required Deliverables

Produce:

## A. `endpoint-naming-conventions.md`

Include:

```text id="v1g4yx"
base path
case convention
pluralization
resource naming
identifier naming
slug rules
collection/item rules
nested resources
action naming
special path rules
depth rules
```

---

## B. `canonical-api-vocabulary.md`

Define the single approved API name for every domain concept.

---

## C. `resource-path-policy.md`

Document:

```text id="u0d6r9"
collection paths
item paths
nested paths
self-context paths
admin context rules
internal-only paths
```

---

## D. `action-naming-policy.md`

Document how future action endpoints must be named.

Do not enumerate every actual action yet.

---

## E. `resource-nesting-policy.md`

Document:

* when nesting is appropriate;
* maximum practical depth;
* when to use references;
* parent/child ownership rules.

---

## F. `api-naming-decisions.md`

Record all final decisions from Phase 1.9.

Each decision:

```text id="xxsd6g"
Decision ID
Decision
Reason
Alternatives
Affected resources
Future phase impact
```

---

# 56. Validation Checklist

Before completing:

### General

* [ ] Versioned base path is consistent with Phase 1.8.
* [ ] URLs use lowercase.
* [ ] Resources use plural nouns.
* [ ] URLs represent resources rather than CRUD verbs.
* [ ] Multi-word segments use one consistent convention.
* [ ] Database naming does not control API vocabulary.

### Catalog

* [ ] `/products` concept is established.
* [ ] `/categories` concept is established.
* [ ] Product images are appropriately nested.
* [ ] Product variants are appropriately nested.
* [ ] Public catalog routes remain anonymous.
* [ ] SEO website URLs remain separate from API paths.

### Commerce

* [ ] Orders have a clear resource name.
* [ ] Order items are conceptually children.
* [ ] Tracking has a consistent naming approach.
* [ ] Payment remains separate.
* [ ] Delivery remains associated with Order.
* [ ] Cart and Cart Items have clear relationships.

### Requests

* [ ] Made-to-order requests have one canonical resource name.
* [ ] General enquiries have one canonical resource name.
* [ ] They are not merged.

### Identity

* [ ] `/me` policy is explicitly decided.
* [ ] Customer ownership does not require exposing arbitrary user IDs.
* [ ] Admin/staff user management remains subject to later authorization design.

### Actions

* [ ] Action endpoint naming is consistent.
* [ ] Status changes are not automatically treated as arbitrary CRUD updates.
* [ ] No inconsistent camelCase/verb naming is introduced.

### Nesting

* [ ] Deep nesting is discouraged.
* [ ] Child resources are nested only when ownership warrants it.
* [ ] Circular representation risks are considered.
* [ ] Internal-only relationships are not exposed.

### Payment

* [ ] Payment-specific implementation remains deferred to **Phase Group H**.
* [ ] No provider-specific payment paths have been defined.

---

# 57. Explicitly Out of Scope

Do NOT:

```text id="sb9k48"
Create actual endpoint catalog
Choose GET/POST/PATCH/DELETE
Define request payloads
Define response payloads
Define query parameters
Define pagination format
Define filtering syntax
Define sorting syntax
Define authentication behavior
Define authorization policies
Define HTTP status codes
Write Laravel routes
Create OpenAPI paths
Implement API middleware
```

---

# 58. Definition of Done

Phase 1.9 is complete when:

1. The API naming philosophy is explicit.
2. Resource names are canonical.
3. URL case and pluralization are fixed.
4. Collection and item naming is fixed.
5. Identifier placeholder conventions are fixed.
6. Slug vs identifier roles are distinguished.
7. Nested-resource rules are documented.
8. Action naming rules are documented.
9. Self-context naming policy is decided.
10. Admin path policy is decided.
11. Internal-only resource naming is distinguished.
12. Deep nesting risks are addressed.
13. Public SEO URLs remain independent from API paths.
14. Payment remains assigned to **Phase Group H**.
15. No HTTP method contract has been created.
16. No endpoint implementation has been created.

---

# 59. STOP CONDITION — Mandatory

After completing:

```text id="8q2sjt"
endpoint-naming-conventions.md
canonical-api-vocabulary.md
resource-path-policy.md
action-naming-policy.md
resource-nesting-policy.md
api-naming-decisions.md
```

and passing the validation checklist:

**STOP.**

Do not automatically continue to HTTP method design.

Do not create endpoint implementations.

Do not create Laravel routes.

Do not create OpenAPI paths.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.10 — Define HTTP Method Conventions

That phase will define when the API uses `GET`, `POST`, `PATCH`, `PUT`, `DELETE`, and controlled action operations, including idempotency expectations and how those methods map to the project's resource and business-action model.
