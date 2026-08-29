# Phase 1.7 — Define API Resource Relationships

## 1. Purpose

Phase 1.7 defines how the API resources established in Phase 1.6 relate to one another.

The goal is to answer:

> **How can one API resource be related to, reached from, or represented alongside another resource?**

This phase establishes the conceptual API relationship model before endpoint URLs, HTTP methods, request bodies, or response schemas are finalized.

It determines:

* parent and child relationships;
* ownership relationships;
* references between resources;
* nested-resource candidates;
* independently addressable resources;
* embedded vs linked representations;
* relationship traversal;
* circular-reference risks;
* authorization implications;
* public vs private relationship exposure.

It does **not** yet define the actual endpoint paths.

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
...
```

Phase 1.7 must use the **frozen logical model** and the approved resource inventory.

---

# 3. Authoritative Inputs

Read:

```text
VISION.md
agent.md

phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3-domain-invariants.md

logical-data-model-v1.md
freeze-record.md

api-resource-inventory.md
resource-access-matrix.md
resource-lifecycle-matrix.md
resource-ownership.md
resource-actions.md
```

If the project uses different filenames for the Phase 1.5/1.6 deliverables, locate the corresponding approved documents.

Do not reopen previously resolved business decisions.

---

# 4. Core Principle

The API relationship model must reflect **business relationships**, not merely database foreign keys.

For example:

```text
Order → Order Items
```

is both a business relationship and an API relationship.

But:

```text
Order → internal inventory reservation
```

may be a backend implementation relationship that should never become a public API relationship.

Therefore:

> **Database relationship ≠ automatically API relationship.**

---

# 5. Relationship Categories

Classify each relationship as one of:

```text
PARENT_CHILD
OWNERSHIP
REFERENCE
OPTIONAL_REFERENCE
CONDITIONAL_REFERENCE
DERIVED_RELATIONSHIP
OPERATIONAL_RELATIONSHIP
INTERNAL_ONLY
```

Definitions:

### PARENT_CHILD

A resource exists conceptually as part of another resource.

Example:

```text
Product
  └── Variants
```

### OWNERSHIP

A resource belongs to an actor/resource for authorization purposes.

Example:

```text
Customer
  └── Orders
```

### REFERENCE

A resource refers to another resource without being owned by it.

Example:

```text
Product
  → Category
```

### OPTIONAL_REFERENCE

The relationship may not exist.

Example:

```text
Made-to-order Request
  → User (optional)
```

### CONDITIONAL_REFERENCE

The relationship is required only under a business condition.

Example:

```text
Order
  → Delivery
```

only when fulfillment is `DELIVERY`.

### DERIVED_RELATIONSHIP

The relationship is computed or exposed for convenience, not stored as an independent business relationship.

### OPERATIONAL_RELATIONSHIP

The relationship exists for staff/admin operations.

### INTERNAL_ONLY

The relationship must not be exposed as a customer-facing API relationship.

---

# 6. Master Relationship Map

Create a conceptual map beginning with:

```text
User
├── Cart
├── Orders
├── Notifications
├── Requests (optional association)
└── Enquiries (optional association)

Category
└── Products

Product
├── Images
├── Variants
└── Category

Variant
└── Inventory

Cart
└── Cart Items
      └── Product / Variant

Order
├── Order Items
├── Payment
├── Fulfillment/Delivery
└── Tracking
      └── Status History

Made-to-order Request
├── Product (optional)
├── User (optional)
└── Attachments

Enquiry
├── User (optional)
└── Attachments
```

This is a conceptual starting point.

Validate it against the frozen logical model rather than assuming every relationship is final.

---

# 7. Product → Category

Define the relationship:

```text
Product
   ↓
Category
```

Determine:

* whether every product requires a category;
* whether a product can belong to multiple categories in Version 1;
* whether category is independently addressable;
* whether product responses should expose category as an embedded summary or a reference.

Do not design URLs yet.

For Version 1, prefer the simplest model consistent with the approved business requirements.

---

# 8. Category → Product

Determine how the API conceptually supports:

```text
Category
   ↓
Products
```

This relationship exists because the website and Flutter app need category browsing.

The API must eventually support:

```text id="8it7vi"
Category
→ related Products
```

without exposing unrelated private catalog data.

---

# 9. Product → Product Images

Treat Product Images as a child/subresource relationship.

Conceptually:

```text
Product
   └── Images
```

Determine whether images:

* have independent business meaning;
* need standalone addressing;
* should normally be returned with product representation;
* need separate administrative operations.

Recommended baseline:

```text
Customer:
Product → Images

Admin:
Product → Image management
```

Avoid making every image a globally independent public resource.

---

# 10. Product → Product Variants

Define:

```text
Product
   └── Variants
```

A variant must always remain associated with its owning product.

The API relationship must make this ownership clear.

Avoid a public API design where a customer can manipulate:

```text
Variant X
```

without establishing:

```text
Variant X belongs to Product Y
```

---

# 11. Product/Variant → Inventory

This is a critical relationship.

Distinguish:

```text
Customer-facing:
Product/Variant → Availability

Internal:
Product/Variant → Inventory management
```

The API must not expose internal stock-management relationships simply because the backend stores inventory data.

Document:

```text
Public relationship
Internal relationship
```

separately.

---

# 12. Product → Availability

Define availability as a customer-facing representation.

For example:

```text
Product
  → availability
```

or:

```text
Variant
  → availability
```

Determine whether availability belongs:

* to the Product;
* to the Variant;
* to both depending on catalog configuration.

Use the least complicated model that supports Version 1.

Do not expose:

```text
reserved_quantity
internal_adjustments
staff_notes
```

through this customer-facing relationship unless explicitly required.

---

# 13. User → Cart

Define:

```text
User
   ↓
Cart
```

Determine whether a user has:

```text
one active cart
multiple historical carts
one cart per device
```

Do not invent multiple active carts unless required.

Since checkout requires authentication, establish how customer identity and cart ownership are logically connected.

Anonymous cart behavior remains subject to whatever was intentionally left unresolved earlier; do not introduce a new requirement here.

---

# 14. Cart → Cart Items

Define:

```text
Cart
   └── Items
```

Cart Items are children of Cart.

A Cart Item references:

```text
Product
or
Product Variant
```

depending on product configuration.

The API relationship must not allow a cart item to reference a variant belonging to an unrelated product.

---

# 15. Cart Item → Product/Variant

Classify this as a reference relationship.

Conceptually:

```text
Cart Item
   └── references Product/Variant
```

This does not make Product a child of Cart.

Important distinction:

```text
Cart
→ owns Cart Item

Cart Item
→ references Product
```

Do not reverse those relationships.

---

# 16. Cart → Checkout

Treat Checkout as a workflow relationship rather than a simple child resource.

Conceptually:

```text
Cart
   ↓
Checkout workflow
   ↓
Order
```

Checkout does not permanently own the Order.

Its purpose is to validate and transform the current cart state into a purchase transaction.

Do not define the URL or HTTP operation yet.

---

# 17. User → Orders

Define:

```text
User
   └── Orders
```

This is both:

```text
OWNERSHIP
+
AUTHORIZATION BOUNDARY
```

The relationship means:

> A customer can retrieve orders belonging to their own account.

It must not become:

```text
Any authenticated user
→ any order
```

---

# 18. Order → Order Items

Define:

```text
Order
   └── Order Items
```

Order Items are children of Order.

They represent the purchased contents of an order.

They should not generally be treated as independently managed customer resources.

---

# 19. Order Item → Product/Variant

Order Items reference the catalog item purchased.

However, the relationship has a special historical requirement.

The API must be able to return the purchase-time information even if the current Product changes.

Therefore distinguish:

```text
Order Item
├── historical purchase snapshot
└── optional current catalog reference
```

Do not let the current product representation overwrite the order's historical meaning.

---

# 20. Order → Payment

Define:

```text
Order
   ↕
Payment
```

This is a separate resource relationship.

Do not model:

```text
Order.status = Payment.status
```

as a relationship.

Instead:

```text
Order
   └── Payment relationship
```

Payment details must be exposed according to authorization.

Provider-specific implementation remains deferred to Group H.

---

# 21. Order → Fulfillment/Delivery

Define:

```text
Order
   └── Fulfillment
```

Fulfillment represents:

```text
PICKUP
DELIVERY
```

For `DELIVERY`:

```text
Fulfillment
   └── delivery details
```

For `PICKUP`, delivery-specific relationships should not be required.

This is a **conditional relationship**.

---

# 22. Order → Tracking

Define:

```text
Order
   └── Tracking
```

Tracking is a read-oriented representation of the order lifecycle.

It should expose relevant progress without exposing internal operational structures unnecessarily.

---

# 23. Tracking → Status History

Define:

```text
Tracking
   └── Status History
```

or, where appropriate:

```text
Order
   └── Status History
```

The API should present status history in a customer-understandable manner.

The internal storage model may differ.

Do not create an independent customer mutation interface for status history.

---

# 24. Order → Status History

Treat status history as an order-owned relationship:

```text
Order
   └── Status History
```

This supports:

```text
current status
+
historical status events
```

The relationship is primarily:

```text
READ
AUDIT
TRACKING
```

not ordinary CRUD.

---

# 25. Made-to-order Request → Product

This is an optional reference.

A request may originate from:

```text
Product page
```

and therefore reference the product being requested.

However, anonymous custom requests may not correspond to an existing product.

Therefore:

```text
Request
   → Product (optional)
```

must remain valid.

---

# 26. Made-to-order Request → User

This is an optional ownership relationship:

```text
User
   └── Requests
```

but:

```text
Request
   → User
```

may be absent for anonymous requests.

This is essential.

Do not make User a mandatory parent for requests.

---

# 27. Enquiry → User

Same principle:

```text
User
   └── Enquiries
```

with:

```text
Enquiry
   → User (optional)
```

Anonymous enquiries must remain valid.

---

# 28. Request → Attachments

Define:

```text
Made-to-order Request
   └── Attachments
```

Attachments belong to their owning request.

Access must be inherited from the request/enquiry authorization context.

Do not expose:

```text
GET /attachments/{arbitrary-id}
```

without authorization considerations.

---

# 29. Enquiry → Attachments

Similarly:

```text
Enquiry
   └── Attachments
```

Attachments are children of the enquiry.

They are not globally public resources.

---

# 30. User → Notifications

Define:

```text
User
   └── Notifications
```

Notifications are private to their recipient.

Do not allow customers to query notifications belonging to another user.

---

# 31. Authentication Relationships

Authentication should be modeled as operations around User identity.

Conceptually:

```text
User
   ↕
Authentication
```

Do not treat:

```text
login
password reset
email verification
```

as ordinary child records exposed like Product or Order.

Their exact API representation belongs in the authentication contract phase.

---

# 32. Staff/Admin Relationships

Staff/admin operations should generally operate on the same core domain resources.

For example:

```text
Admin
   → Products
   → Orders
   → Inventory
   → Requests
   → Enquiries
```

Do not create duplicate resources such as:

```text
AdminOrder
CustomerOrder
```

unless a future domain requirement explicitly requires separate representations.

Different access levels should generally be handled through authorization and representation rules.

---

# 33. Ownership vs Reference Matrix

Create a formal matrix.

| Source     | Target        | Relationship         | Ownership?       | Optional?               | Public?                   |
| ---------- | ------------- | -------------------- | ---------------- | ----------------------- | ------------------------- |
| Product    | Category      | Reference            | No               | Based on model          | Yes                       |
| Product    | Images        | Parent/Child         | Yes              | No/possibly             | Yes via Product           |
| Product    | Variants      | Parent/Child         | Yes              | Yes                     | Yes                       |
| Cart       | Cart Items    | Parent/Child         | Yes              | Yes                     | Customer only             |
| Cart Item  | Product       | Reference            | No               | No                      | Customer-scoped           |
| User       | Cart          | Ownership            | Yes              | Based on cart model     | Private                   |
| User       | Orders        | Ownership            | Yes              | Yes                     | Private                   |
| Order      | Order Items   | Parent/Child         | Yes              | No                      | Customer-owned            |
| Order Item | Product       | Historical/reference | No               | Based on archival model | Limited                   |
| Order      | Payment       | Transactional        | No               | Payment-flow dependent  | Restricted                |
| Order      | Fulfillment   | Conditional          | Yes/Associated   | Yes                     | Restricted/Customer-owned |
| Order      | Tracking      | Subresource          | Yes              | No                      | Customer-owned            |
| Request    | Product       | Optional reference   | No               | Yes                     | Restricted                |
| Request    | User          | Optional ownership   | Yes when present | Yes                     | Private                   |
| Request    | Attachments   | Parent/Child         | Yes              | Yes                     | Restricted                |
| Enquiry    | User          | Optional ownership   | Yes when present | Yes                     | Private                   |
| Enquiry    | Attachments   | Parent/Child         | Yes              | Yes                     | Restricted                |
| User       | Notifications | Ownership            | Yes              | Yes                     | Private                   |

Adjust the matrix to match the approved logical model.

---

# 34. Nested Resource Candidates

Identify relationships that may later be expressed as nested API resources.

Likely candidates (relationship names only, no URL paths):

```text
Product → Variants
Product → Images
Cart → Cart Items
Order → Order Items
Order → Tracking
Order → Status History (canonical via Tracking → Status History)
Order → Delivery (conditional on DELIVERY)
Made-to-order Request → Attachments
Enquiry → Attachments
User → Notifications (recipient-scoped subresource)
```

These are **candidates only** — expressed as relationships, not routes.

Do not finalize URLs in this phase.

---

# 35. Independently Addressable Resource Candidates

Identify resources that probably deserve independent addressing.

Likely candidates (resource identities, not URL paths):

```text
Category
Product
Cart
Order
Payment
Made-to-order Request
General Enquiry
User → Profile
```

`Notifications` is **not** independently addressable as a top-level global resource — it is a recipient-scoped subresource under the authenticated user (`User → Notifications`), never addressable outside that user's notification scope (recipient-only, private).

Again, no URLs yet — these are relationship/resource names only.

The purpose is to establish whether a resource has a stable identity and meaningful independent lifecycle.

---

# 36. Resources That Should Not Normally Be Standalone

Likely examples:

```text
Product Image
Product Variant* 
Cart Item
Order Item
Order Status History
Attachment
```

`*` Product Variant may be independently addressable for admin/internal operations if later required, but it should remain conceptually owned by Product.

Do not force everything into top-level endpoints.

---

# 37. Embedding vs Linking

For every relationship, decide conceptually whether the related data should usually be:

```text
EMBEDDED
LINKED/REFERENCED
AVAILABLE THROUGH SUBRESOURCE
```

Example:

### Product → Category

Likely:

```text
Product representation
→ category summary/reference
```

### Product → Images

Likely:

```text
Product
→ image representations
```

because customers need them immediately.

### Order → Order Items

Likely:

```text
Order
→ items
```

because items are essential to understanding an order.

### Order → Payment

Likely limited payment summary rather than full payment data.

Do not design the JSON yet.

---

# 38. Prevent Over-Fetching

Avoid relationships that force large unrelated datasets into every response.

For example, retrieving:

```text
Product
```

should not automatically require:

```text
all orders
all reviews
all inventory movements
all customer interactions
```

The relationship model should support targeted retrieval.

---

# 39. Prevent Under-Fetching

At the same time, avoid a design where basic product pages require a dozen API calls.

For example, the public product representation should be capable of supporting the normal product-detail UI without unnecessary request chains.

The exact response shape comes later.

---

# 40. Circular Relationship Review

Identify cycles such as:

```text
User
 → Orders
 → Order
 → Customer
 → User
```

or:

```text
Product
 → Category
 → Products
 → Category
```

Such cycles may be valid conceptually but dangerous in API representations.

Document where cyclic embedding must be prevented.

Rule:

> Related resources should not recursively embed full parent objects indefinitely.

Use summaries/references where appropriate.

---

# 41. Authorization Through Relationships

Relationships also determine authorization.

Example:

```text
GET customer's order
```

must conceptually evaluate:

```text
Order
   ↓
belongs to
   ↓
authenticated user
```

Similarly:

```text
Request
   ↓
belongs to User
```

when authenticated.

For anonymous requests, authorization is based on the request context rather than a User relationship.

Document these rules without implementing middleware.

---

# 42. Anonymous Relationship Review

Explicitly verify anonymous scenarios.

### Anonymous request

```text
Request
├── User = none
├── Contact information
└── Optional Product
```

### Anonymous enquiry

```text
Enquiry
├── User = none
├── Contact information
└── Message
```

### Public product

```text
Product
├── Category
├── Images
├── Variants
└── Public availability
```

No User relationship is required.

---

# 43. Conditional Relationships

Create a dedicated list.

At minimum:

```text
Order → Delivery
```

is conditional on:

```text
fulfillment_type = DELIVERY
```

Potentially:

```text
Order → Pickup details
```

if pickup requires specific information later.

Do not invent pickup entities unless the business actually requires them.

---

# 44. Internal-Only Relationships

Identify relationships that should remain invisible to public API consumers.

Examples:

```text id="m8ja9a"
Order
 → internal stock reservation

Payment
 → provider credentials/configuration

Inventory
 → internal adjustments

User
 → authentication credential internals
```

These may be essential backend relationships but are not public API resources.

---

# 45. Administrative Traversal

Verify that staff/admin can eventually traverse the necessary data.

For example:

```text id="82gw2r"
Admin
 → Order
    → Customer
    → Items
    → Payment
    → Fulfillment
    → Status history
```

and:

```text id="hv2p1r"
Admin
 → Product
    → Variants
    → Images
    → Inventory
```

Do not define the endpoint structure yet.

---

# 46. Relationship Naming

Use business terms consistently.

Preferred:

```text
items
variants
images
tracking
delivery
attachments
notifications
```

Avoid technical names such as:

```text
children
relations
foreign
records
linked_entities
```

unless technically necessary later.

---

# 47. Relationship Access Matrix

Create a matrix showing who may traverse each relationship.

Example:

| Relationship                 |               Anonymous | Customer |       Staff |      Admin |
| ---------------------------- | ----------------------: | -------: | ----------: | ---------: |
| Category → Products          |                     Yes |      Yes |         Yes |        Yes |
| Product → Images             |                     Yes |      Yes |         Yes |        Yes |
| Product → Variants           |                     Yes |      Yes |         Yes |        Yes |
| Product → Internal Inventory |                      No |       No |         Yes |        Yes |
| User → Own Orders            |                      No |      Yes |         N/A | Authorized |
| Order → Own Items            |                      No |      Yes | Operational | Authorized |
| Order → Payment detail       |                      No |  Limited | Operational | Authorized |
| Order → Tracking             |                      No |      Own | Operational | Authorized |
| Request → Attachments        | Through request context |      Own |  Authorized | Authorized |
| Enquiry → Attachments        | Through enquiry context |      Own |  Authorized | Authorized |

Keep this conceptual.

Detailed permission rules remain a later phase.

---

# 48. Relationship Data Exposure

For each relationship, identify whether the target representation should expose:

```text
FULL
SUMMARY
REFERENCE ONLY
INTERNAL ONLY
```

Examples:

### Product → Category

Probably:

```text
SUMMARY
```

### Order → Payment

Probably:

```text
LIMITED SUMMARY
```

### Order → Customer

For customer-facing order responses:

```text
SELF/RELEVANT CUSTOMER INFORMATION ONLY
```

Do not expose credentials or sensitive internal data.

---

# 49. Relationship Consistency with SEO

Public relationship traversal must support SEO-friendly catalog navigation.

The logical API model should allow:

```text
Category
   ↓
Products

Product
   ↓
Category

Product
   ↓
Images
```

without authentication.

This supports the Next.js website's server-rendered product/category pages.

Do not expose private commerce relationships through public catalog resources.

---

# 50. Relationship Consistency with Flutter

Flutter needs efficient access to:

```text
Products
Categories
Product details
Variants
Availability
Cart
Orders
Tracking
Requests
```

Ensure those relationships can later be consumed without requiring excessively complex client-side reconstruction.

Do not let frontend convenience override domain boundaries.

---

# 51. Relationship Consistency with Admin

The admin application needs operational traversal:

```text
Product → Inventory
Product → Variants
Order → Customer
Order → Items
Order → Payment
Order → Delivery
Order → Status history
Request → Product/User/Attachments
Enquiry → User/Attachments
```

Identify any missing relationship now.

---

# 52. Relationship Anti-Patterns

Do not:

### Make every relation nested

Not every relationship needs:

```text
A/B/C
```

Deep nesting can make an API difficult to maintain.

### Make every relation top-level

Not every child requires:

```text
GET /order-items
GET /status-history
GET /attachments
```

### Embed full objects recursively

Avoid:

```text
Product
 → Category
    → Products
       → Category
```

### Expose internal relationships

Never expose backend internals simply because they exist.

### Break ownership boundaries

Customer APIs must never traverse into unrelated customer records.

---

# 53. Relationship Decision Rules

Use these principles when deciding whether a relationship is nested or referenced:

### Nest when:

* the child cannot meaningfully exist outside the parent;
* the child is primarily managed through the parent;
* the child is naturally part of the parent's representation.

### Reference when:

* the target has an independent lifecycle;
* the target is reused by multiple resources;
* the relationship is primarily associative;
* embedding would cause excessive data.

### Keep internal when:

* the relationship exists only for backend implementation;
* the information is sensitive;
* customers have no business reason to access it.

---

# 54. Required Deliverables

Produce:

## A. `api-resource-relationships.md`

The authoritative relationship catalogue.

For every relationship include:

```text
Relationship ID
Source resource
Target resource
Type
Ownership
Optional/conditional status
Access scope
Purpose
Exposure recommendation
```

---

## B. `resource-relationship-map.md`

Create the complete conceptual relationship graph.

Example:

```text
User
├── Cart
├── Orders
├── Requests
├── Enquiries
└── Notifications

Category
└── Products
    ├── Images
    ├── Variants
    └── Availability

Order
├── Items
├── Payment
├── Fulfillment
└── Tracking
    └── Status History
```

---

## C. `nested-resource-candidates.md`

List relationships that may later become nested resources.

Do not define URLs.

---

## D. `resource-embedding-policy.md`

For important relationships classify:

```text
FULL
SUMMARY
REFERENCE
SUBRESOURCE
INTERNAL ONLY
```

---

## E. `relationship-access-matrix.md`

Show anonymous/customer/staff/admin access.

---

## F. `relationship-security-review.md`

Document:

* ownership boundaries;
* privacy risks;
* circular-reference risks;
* overexposure risks;
* anonymous-user implications.

---

## G. `relationship-decisions.md`

Record decisions made during Phase 1.7.

Format:

```text
Decision ID
Decision
Reason
Affected resources
Future phase affected
```

---

# 55. Required Validation

Before completion, verify:

### Catalog

* [ ] Category ↔ Product relationship is clear.
* [ ] Product → Images is clear.
* [ ] Product → Variants is clear.
* [ ] Product/Variant → Availability is clear.
* [ ] Public catalog relationships require no authentication.

### Cart

* [ ] Cart owns Cart Items.
* [ ] Cart Items reference Product/Variant.
* [ ] Cart does not own Product.
* [ ] Checkout is a workflow, not simply another child entity.

### Orders

* [ ] User → Orders ownership is clear.
* [ ] Order → Order Items is clear.
* [ ] Order Item → Product/Variant reference is clear.
* [ ] Historical data remains authoritative.
* [ ] Order → Payment is separate.
* [ ] Order → Fulfillment is clear.
* [ ] Order → Tracking is clear.
* [ ] Status history remains controlled.

### Requests

* [ ] Request may reference Product optionally.
* [ ] Request may reference User optionally.
* [ ] Anonymous request remains supported.
* [ ] Attachments belong to Request.

### Enquiries

* [ ] Enquiry may reference User optionally.
* [ ] Anonymous enquiry remains supported.
* [ ] Attachments belong to Enquiry.

### Security

* [ ] Customer ownership boundaries are explicit.
* [ ] Internal inventory relationships are not public.
* [ ] Payment internals are not public.
* [ ] Authentication internals are not exposed.
* [ ] Circular relationship risks are identified.

### Cross-platform

* [ ] Relationships support Next.js website requirements.
* [ ] Relationships support Flutter app requirements.
* [ ] Relationships support admin operational requirements.

---

# 56. Explicitly Out of Scope

Do NOT:

```text
Define URL paths
Choose HTTP methods
Define request schemas
Define response schemas
Define pagination
Define filtering
Define sorting
Define error formats
Define authentication implementation
Define authorization middleware
Write Laravel code
Write Next.js code
Write Flutter code
Create OpenAPI endpoint definitions
```

---

# 57. Definition of Done

Phase 1.7 is complete when:

1. Every API resource relationship has been classified.
2. Parent/child relationships are explicit.
3. Ownership relationships are explicit.
4. Optional relationships are explicit.
5. Conditional relationships are explicit.
6. Internal-only relationships are explicit.
7. Nested-resource candidates are identified.
8. Independent-resource candidates are identified.
9. Embedding/reference recommendations exist.
10. Authorization implications are documented.
11. Circular-reference risks are documented.
12. Anonymous workflows remain valid.
13. Public catalog traversal remains authentication-free.
14. Sensitive relationships are protected conceptually.
15. No endpoint URLs have been defined.
16. No HTTP contract has been defined.
17. No implementation code has been written.

---

# 58. STOP CONDITION — Mandatory

After all Phase 1.7 deliverables are complete:

**STOP.**

Do not automatically begin endpoint naming.

Do not define URLs.

Do not select HTTP methods.

Do not create Laravel routes.

Do not create OpenAPI paths.

The approved relationship model becomes the input for the next phase.

Recommended next phase:

# Phase 1.8 — Define API Versioning

That phase should establish the versioning strategy, compatibility rules, breaking-change policy, URL/header strategy, lifecycle of API versions, and how the website, Flutter application, admin application, and future clients will coexist as the API evolves.
