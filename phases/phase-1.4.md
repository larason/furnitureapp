# Phase 1.4 — Define Data Model Requirements Before Physical Database Design

## 1. Purpose

Phase 1.4 translates the approved domain concepts and business invariants into a **logical data model specification**.

The goal is to determine:

* what information the system needs to persist;
* which information belongs to which domain entity;
* how entities relate conceptually;
* which information is required, optional, historical, derived, or externally sourced;
* what must be unique;
* what must be immutable;
* what must be auditable;
* what data must exist for each business workflow.

This phase prepares the project for the later physical database design.

It must **not** create the actual MySQL schema yet.

---

# 2. Authoritative Inputs

Read:

```text
VISION.md
agent.md
phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3-domain-invariants.md
business-rules.md
domain-invariants.md
order-state-rules.md
testable-invariants.md
```

The previously approved business decisions are authoritative.

Do not reopen already settled questions.

Do not silently introduce new business behavior.

If a contradiction is discovered, document it and STOP.

---

# 3. Scope

This phase covers the logical data requirements for:

```text
Users
Roles
Categories
Products
Product variants
Product images
Inventory
Carts
Cart items
Orders
Order items
Order addresses
Order status history
Payments
Deliveries
Made-to-order requests
Enquiries
Attachments
Notifications
```

Additional entities may only be introduced when there is a clear Version 1 business requirement.

Avoid speculative enterprise entities.

---

# 4. Physical Database Design Is Explicitly Deferred

Do not decide yet:

```text
MySQL tables
Column types
Primary key implementation
Foreign key names
Indexes
Database constraints
ENUM implementation
JSON columns
Triggers
Stored procedures
Partitioning
Sharding
Replication
```

This phase determines **what data is required**, not how MySQL physically stores it.

---

# 5. Data Modeling Principles

Apply these principles throughout the phase.

## 5.1 One source of truth

Each business fact should have a clear authoritative owner.

Example:

```text
Product price
→ Product/catalog domain

Order-time price
→ Order item historical data

Current stock
→ Inventory domain

Delivery fee for an order
→ Order/fulfillment transaction data
```

Avoid storing the same mutable business fact in multiple unrelated places unless there is a deliberate historical snapshot.

---

## 5.2 Historical data must remain explainable

Anything required to understand a completed transaction later must be preserved as transaction-time data.

Do not depend entirely on current catalog records.

---

## 5.3 Derived values should be identified

For every important value, classify it as:

```text
SOURCE
DERIVED
SNAPSHOT
REFERENCE
EXTERNAL
```

Example:

```text
Product price
→ SOURCE

Order subtotal
→ DERIVED

Order-item unit price
→ SNAPSHOT

Payment provider transaction reference
→ EXTERNAL REFERENCE
```

Do not implement formulas yet.

---

# 6. Define Entity Data Requirements

For every entity, document:

```text
Entity name
Purpose
Business owner
Required attributes
Optional attributes
Derived attributes
Historical/snapshot attributes
External references
Uniqueness requirements
Lifecycle
Relationships
Deletion expectations
Privacy classification
```

Do this for every Version 1 entity.

---

# 7. User Data Requirements

Define the logical information required for `User`.

At minimum consider:

```text
Identity
Display name
Email
Phone
Authentication credentials
Role
Account state
Verification state
Timestamps
```

Classify each item as:

```text
required
optional
derived
sensitive
```

Do not choose password hashing algorithms or database column types here.

---

# 8. Customer Identity Requirements

Clarify the relationship:

```text
User
    ↓
Customer role
```

Do not duplicate an independent customer identity record unless a future business requirement requires additional customer-specific data.

Determine what information must exist to:

* associate orders to a customer;
* associate authenticated requests to a customer;
* associate enquiries to a customer;
* retrieve account history.

Anonymous requests/enquiries must remain possible without requiring a `User`.

---

# 9. Role Data Requirements

The Version 1 roles are:

```text
CUSTOMER
STAFF
ADMIN
```

This phase should record that role information must be persisted or otherwise represented authoritatively.

Detailed permissions are deferred to Group D.

Do not design the full permission/role matrix yet.

---

# 10. Category Data Requirements

Define the logical information required for categories.

At minimum consider:

```text
Name
Slug
Description
Display image
Active/inactive state
Ordering/display position
```

Determine whether a category must support:

* hierarchical categories;
* parent category;
* one-level or multi-level structure.

Do not automatically introduce nested category complexity.

Use the smallest structure that supports the approved Version 1 catalog.

---

# 11. Product Data Requirements

Define Product data.

At minimum consider:

```text
Name
Slug
Description
SKU/business reference
Product type
Category association
Base price
Active state
Catalog visibility
Created/updated information
```

Product type is:

```text
IN_STOCK
MADE_TO_ORDER
```

Determine which properties are mandatory.

For example:

```text
A product cannot be publishable without:
- name
- category
- product type
- required commercial information
```

Do not define exact database constraints yet.

---

# 12. Product Image Requirements

Define the information required for product images.

At minimum:

```text
Product association
Image location/reference
Display order
Primary image indicator
Alt text/accessibility information
Active state
```

The actual image storage provider is deferred.

Do not store binary image data in the logical product model unless explicitly required.

---

# 13. Product Variant Requirements

Determine whether the following require first-class persisted information:

```text
Variant name
SKU
Price override
Attributes
Active state
```

Examples:

```text
Color = Grey
Material = Fabric
Size = 3-Seater
```

Decide conceptually whether the variant itself may be the inventory-controlled purchasable unit.

Use the previously approved domain definition:

> A variant represents a purchasable variation of a product and may have its own stock and pricing when required.

Do not choose between separate columns, JSON, or another physical representation yet.

---

# 14. Inventory Data Requirements

Define information required to accurately represent stock.

At minimum distinguish:

```text
Stock quantity
Reserved quantity
Available quantity
Product/variant ownership
Updated time
```

Determine which values are:

```text
authoritative
derived
historical
```

Do not automatically store `available_quantity` if it can safely be derived from authoritative values. Make this a data-model decision to be finalized later.

The logical requirement is:

> The system must be capable of determining the authoritative quantity available for purchase at checkout.

---

# 15. Inventory History

Determine whether Version 1 requires inventory movement history.

The business currently needs reliable stock operations, but do not automatically build a full warehouse-management subsystem.

Decide the minimum logical information required to investigate:

```text
Why did stock change?
When did it change?
What operation caused it?
Who/what initiated it?
```

If full movement history is not required for Version 1, explicitly document the decision and reason.

---

# 16. Cart Data Requirements

Define:

```text
Cart owner
Cart lifecycle
Cart items
Selected product/variant
Quantity
```

Determine whether anonymous carts are needed.

This is an important distinction:

```text
Anonymous browsing
does NOT automatically mean
anonymous persistent cart
```

Because checkout requires authentication, establish the minimum Version 1 cart behavior needed to support:

```text
Browse
Add to cart
Authenticate
Continue checkout
```

Do not implement the cart yet.

---

# 17. Cart Item Requirements

A cart item logically needs:

```text
Product/variant reference
Quantity
Cart ownership
```

Do not treat cart price as an authoritative final price.

If a displayed price is cached in the cart, it must be treated as non-authoritative.

The final order price comes from backend pricing at checkout.

---

# 18. Order Data Requirements

Define all information required to represent an order.

At minimum:

```text
Order reference
Customer identity
Order status
Fulfillment type
Subtotal
Delivery fee
Discount — deferred, not in Version 1 (no discount handling)
Final total
Currency
Customer note where required
Creation/update timestamps
```

The order must be sufficient to answer:

> What did this customer buy, for what amount, using which fulfillment method, and what is the current state?

---

# 19. Order Item Data Requirements

Order items must preserve transaction-time facts.

At minimum:

```text
Product reference
Variant reference where applicable
Product name snapshot
SKU/reference snapshot
Unit price snapshot
Quantity
Line subtotal
```

Determine which information must remain available even if the product is:

```text
renamed
repriced
deactivated
deleted/archived
reclassified
```

The principle is:

> A historical order must remain understandable without depending on the current presentation of the catalog.

---

# 20. Order Address Requirements

Determine the minimum information required for addresses associated with an order.

Separate conceptually:

```text
Billing address
Delivery address
```

For a pickup order, delivery address may not be required.

For a delivery order, delivery information is required.

Version 1 does not include a persistent customer address book.

Therefore distinguish:

```text
Order address
≠
Saved customer address
```

---

# 21. Order Status History Requirements

Define the information necessary to audit each status change.

At minimum:

```text
Order reference
Previous status
New status
Timestamp
Actor/source
Operational note
```

Determine whether some transitions can be system-generated rather than manually performed.

Do not build the state machine yet.

---

# 22. Payment Data Requirements

Payment is a separate business concept.

Define generic data requirements only.

At minimum consider:

```text
Order association
Payment status
Amount
Currency
Provider
Provider transaction/reference ID
Created time
Updated time
Verification state
```

Payment-provider-specific data is deferred to Group H.

Do not choose a provider.

Do not design gateway payloads.

---

# 23. Payment/Order Separation

Explicitly document that:

```text
Payment status
≠
Order status
```

The data model must support cases such as:

```text
Payment = PAID
Order = ACCEPTED
```

or other valid combinations defined later.

The exact transition relationships will be established in the payment/order lifecycle phases.

---

# 24. Delivery Data Requirements

Define the logical information associated with delivery.

At minimum:

```text
Order association
Fulfillment type
Delivery fee
Delivery status
Recipient
Phone
Delivery address
Delivery notes
```

Determine which delivery information is:

```text
order snapshot
operational status
customer-provided
staff-controlled
```

Do not implement route tracking or driver GPS.

---

# 25. Variable Delivery Fee Requirement

The model must support:

```text
Delivery fee decided operationally
```

The fee must be associated with the relevant transaction once finalized.

Do not assume a global constant such as:

```text
20,000
```

The approved business decision is that the fee is variable and added by authorized admin/staff.

---

# 26. Made-to-Order Request Data Requirements

Define the minimum information needed for a request.

At minimum:

```text
Requester identity when authenticated
Name
Phone
Email
Referenced product where applicable
Quantity
Dimensions
Material
Color
Notes
Status
Created/updated time
```

Determine which fields are mandatory and which are optional.

The model must support:

```text
Anonymous request
Authenticated request
```

without forcing the creation of a user account.

---

# 27. General Enquiry Data Requirements

Define:

```text
Authenticated user reference where applicable
Name
Phone
Email
Subject
Message
Status
Created/updated time
```

An enquiry can exist without a user.

Do not merge it with the made-to-order request.

---

# 28. Attachment Data Requirements

Attachments are optional.

Define the logical information:

```text
Owner record
File reference
Original file name
Content type
Size
Upload timestamp
Security/validation state
```

Do not select the storage provider.

Do not define filesystem paths, S3 keys, or cloud-specific metadata yet.

---

# 29. Notification Data Requirements

Define the logical notification concept.

At minimum consider:

```text
Recipient
Notification type
Related entity/reference
Message/content
Read/unread state
Created time
```

Email delivery is deferred to Group R.

Do not require an external email record structure yet.

---

# 30. Data Classification

For every entity/attribute group, identify privacy sensitivity.

At minimum classify:

```text
PUBLIC
INTERNAL
PRIVATE
SENSITIVE
```

Examples:

```text
Product name → PUBLIC
Product price → PUBLIC
Customer phone → PRIVATE
Customer password credential → SENSITIVE
Payment provider secrets → SENSITIVE
Staff operational note → INTERNAL/PRIVATE
```

The classification will later influence API serialization, authorization, logging, and security.

---

# 31. Required vs Optional Data

For every entity, explicitly distinguish:

```text
REQUIRED
OPTIONAL
CONDITIONAL
DERIVED
SNAPSHOT
```

Example:

```text
Delivery address
→ CONDITIONAL
Required for DELIVERY
Not required for PICKUP
```

Example:

```text
Product dimensions
→ OPTIONAL unless business rules later make them mandatory
```

Do not invent mandatory fields without business justification.

---

# 32. Conditional Data Rules

Identify fields whose requirement depends on business state.

At minimum:

```text
Delivery address
→ required for DELIVERY

Pickup information
→ relevant to PICKUP

Delivery fee
→ relevant for DELIVERY
→ zero/not applicable for PICKUP

Payment information
→ relevant to purchased orders

Made-to-order requirements
→ relevant to requests

Variant
→ required when a product offers variants
```

This prevents the future schema from becoming either overly rigid or excessively nullable without reason.

---

# 33. Ownership and Relationship Matrix

Create a matrix such as:

| Entity                | Belongs to / references | Cardinality                  |
| --------------------- | ----------------------- | ---------------------------- |
| Product               | Category                | Required relationship        |
| Product Image         | Product                 | Many-to-one                  |
| Product Variant       | Product                 | Many-to-one                  |
| Inventory             | Product/Variant         | Defined logically            |
| Cart Item             | Cart                    | Many-to-one                  |
| Order Item            | Order                   | Many-to-one                  |
| Order Status History  | Order                   | Many-to-one                  |
| Payment               | Order                   | Defined by payment lifecycle |
| Delivery              | Order                   | Conditional                  |
| Made-to-order Request | User/Product optional   | Optional references          |
| Enquiry               | User optional           | Optional reference           |
| Attachment            | Request/Enquiry         | Optional                     |
| Notification          | User                    | Many-to-one                  |

Do not translate cardinality directly into foreign keys yet.

---

# 34. Lifecycle Requirements

For each major entity, define whether it needs:

```text
creation
activation/deactivation
update
archival
cancellation
completion
```

Examples:

### Product

```text
Draft/active/inactive concept
```

### Request

```text
New
Contacted
Quoted
...
```

### Order

```text
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
...
```

The exact implementation is later.

---

# 35. Deletion Strategy Requirements

Do not decide SQL `CASCADE`/`SET NULL` behavior yet.

Instead decide logically:

> Should this business record remain historically available?

Examples:

### Orders

Must remain historically available.

### Order items

Must remain historically available.

### Order status history

Must remain historically available.

### Product images

May become inactive/removed from catalog.

### Enquiries

Should remain available for staff history according to later retention policy.

### Requests

Should retain operational history.

Record these logical retention requirements.

---

# 36. Immutability Requirements

Identify information that should become effectively immutable once a transaction is finalized.

At minimum:

```text
Order reference
Order item purchased price
Order item purchased name/reference
Order item quantity
Final charged total
Final delivery fee
Transaction-time addresses
Payment transaction reference
```

This does not mean every database row can never be updated.

It means later modifications must not corrupt historical meaning.

---

# 37. Uniqueness Requirements

Identify business uniqueness requirements without choosing database indexes yet.

Consider:

```text
Product slug
Product SKU/reference
Order reference
User email where applicable
Payment provider transaction reference
```

For each, determine whether uniqueness is:

```text
global
within a parent entity
conditional
historical
not required
```

Do not assume every display name must be unique.

---

# 38. External References

Identify data that originates outside the system.

Potential examples:

```text
Payment provider transaction reference
External file-storage reference
Future notification provider reference
```

Clearly distinguish:

```text
internal identifier
external identifier
customer-facing identifier
```

Example:

```text
Internal order ID
≠
Customer-facing OD-XXXXX
```

This distinction is important.

---

# 39. Derived Data

Identify values that can be calculated.

Examples:

```text
Order subtotal
Line subtotal
Available stock
Order total
```

For each derived value, record:

* source data;
* formula conceptually;
* whether it must be persisted as a historical snapshot;
* whether it can safely be recalculated later.

Do not write code or SQL.

---

# 40. Snapshot Data

Identify information that must be copied into a transaction because the source may change.

At minimum:

```text
Order item product name
Order item SKU/reference
Order item unit price
Order item quantity
Order item subtotal
Final delivery fee
Transaction address information
```

Explain why each snapshot exists.

---

# 41. Search Requirements

Identify what product information will eventually need indexing/searching.

For the Version 1 catalog, consider:

```text
Product name
SKU/reference
Category
Product type
Availability
Price
Variant attributes
```

This phase should describe search requirements only.

Do not create indexes or introduce Elasticsearch/OpenSearch yet.

---

# 42. SEO-Relevant Data

Because the public website is SEO-sensitive, identify the product/category data required by Next.js for public rendering.

At minimum:

```text
Product name
Slug
Description
Images
Price
Currency
Availability
Category
Variant information where relevant
```

Also identify logical SEO fields where needed:

```text
meta title
meta description
canonical information
structured-data-relevant information
```

Do not implement SEO yet.

---

# 43. API Exposure Classification

For each entity, categorize its future API visibility as:

```text
PUBLIC
AUTHENTICATED
STAFF
ADMIN
INTERNAL ONLY
```

Example:

```text
Products
→ Public

Inventory internal quantities
→ Not necessarily public

Customer orders
→ Authenticated owner

All customer orders
→ Admin/authorized staff

Payment internals
→ Restricted

Product management
→ Staff/Admin according to Group D
```

This will later prevent accidentally exposing sensitive fields through API resources.

---

# 44. Data Ownership Matrix

Create:

| Business fact               | Authoritative domain |
| --------------------------- | -------------------- |
| Product description         | Catalog              |
| Current product price       | Catalog              |
| Current inventory           | Inventory            |
| Customer identity           | Identity             |
| Cart contents               | Cart                 |
| Historical purchase price   | Order                |
| Final delivery fee          | Order/Fulfillment    |
| Payment confirmation        | Payment              |
| Current order state         | Order                |
| Order status history        | Order                |
| Made-to-order request state | Request              |
| Enquiry state               | Enquiry              |

This matrix must become a central reference for future implementation.

---

# 45. Avoid Premature Denormalization

Do not duplicate data merely because it might make a query faster.

Every duplicate copy must have a documented purpose:

```text
historical snapshot
derived cache
search projection
integration requirement
```

For Version 1, prefer the simplest normalized logical model that preserves:

* integrity;
* performance requirements;
* historical information;
* clear ownership.

Physical optimization comes later, based on evidence.

---

# 46. Avoid Generic "Everything" Tables

Do not create concepts such as:

```text
records
metadata
entities
activities
objects
communications
```

just to avoid defining proper domain entities.

Use explicit business concepts:

```text
orders
requests
enquiries
payments
deliveries
```

unless a genuine cross-domain abstraction is justified.

---

# 47. Avoid Premature Microservices

Version 1 should use one coherent backend/domain unless actual scale or organizational requirements justify separation.

Do not create separate databases/services for:

```text
products
orders
payments
customers
notifications
```

The current project is intentionally small-scale.

A modular Laravel monolith is the preferred logical boundary unless later requirements prove otherwise.

---

# 48. Transaction Boundaries to Identify

Without implementing transactions, identify operations that will eventually require atomic behavior.

At minimum:

```text
Checkout/order creation
Inventory reservation
Payment confirmation
Critical order status change
```

Describe what must succeed or fail together.

Example:

```text
Order creation
+
Order items
+
Stock reservation
```

should not leave the business in a half-created state.

---

# 49. Audit Requirements

Identify data requiring auditability.

At minimum:

```text
Order status changes
Payment status changes
Inventory changes if inventory history is selected
Administrative modifications to critical business records
```

Do not design an audit-log framework yet.

---

# 50. Data Retention Requirements

For each sensitive/business-critical entity, determine whether Version 1 requires historical retention.

At minimum discuss:

```text
Orders
Payments
Order status history
Requests
Enquiries
```

Do not invent legal retention periods.

If no retention period has been approved yet, record:

```text
Business retention period pending policy definition.
```

---

# 51. Deliverables

Produce these documents.

## A. `logical-data-model.md`

For every entity:

```text
Purpose
Attributes
Required/optional/conditional classification
Relationships
Ownership
Lifecycle
Historical requirements
Privacy classification
```

---

## B. `entity-relationship-matrix.md`

Document conceptual relationships and cardinalities.

---

## C. `data-classification.md`

Document:

```text
Public
Internal
Private
Sensitive
```

for important data categories.

---

## D. `data-ownership.md`

Document which domain owns each important business fact.

---

## E. `historical-data-rules.md`

Document:

* snapshots;
* immutable transaction facts;
* order history;
* retention expectations.

---

## F. `data-integrity-requirements.md`

Document:

* uniqueness;
* mandatory data;
* conditional data;
* ownership;
* referential expectations;
* consistency requirements.

---

## G. `data-model-decisions.md`

Record all decisions made during this phase.

Each decision must contain:

```text
Decision ID
Decision
Reason
Affected entity
Future implementation phase
```

---

## H. `data-model-open-issues.md`

Only include actual unresolved data-model blockers.

Do not reopen settled Phase 1.1 questions.

---

## I. `api-exposure-classification.md`

Document entity/attribute API exposure as `PUBLIC`, `AUTHENTICATED`, `STAFF`, `ADMIN`, `INTERNAL ONLY` per §43, distinct from privacy/sensitivity classification. This file is the canonical API exposure matrix for Phase 1.4 (see Definition of Done item 7).

---

# 52. Validation Checklist

Before completion:

### Identity

* [ ] Users can be associated with customer/staff/admin roles.
* [ ] Anonymous request/enquiry records do not require a user.
* [ ] Private customer information is classified.

### Catalog

* [ ] Product type is represented.
* [ ] Product/category relationship is defined.
* [ ] Product images are represented.
* [ ] Variants are represented where required.
* [ ] Product and inventory remain separate.

### Inventory

* [ ] Stock ownership is clear.
* [ ] Available quantity can be determined.
* [ ] Overselling requirements are documented.
* [ ] Inventory history decision is recorded.

### Commerce

* [ ] Cart and cart items are defined.
* [ ] Order and order items are defined.
* [ ] Historical order facts are identified.
* [ ] Order reference is distinguished from internal identity.

### Fulfillment

* [ ] Pickup and delivery are represented.
* [ ] Variable delivery fee is represented.
* [ ] Delivery address is conditional.
* [ ] Saved address book remains deferred.

### Payment

* [ ] Payment remains separate from order status.
* [ ] Provider-specific data is deferred to Group H.

### Requests

* [ ] Anonymous made-to-order requests are supported.
* [ ] Authenticated requests are supported.
* [ ] Requests do not become orders automatically.
* [ ] Optional attachments are represented.

### Enquiries

* [ ] Anonymous enquiries are supported.
* [ ] Authenticated enquiries are supported.
* [ ] Enquiries remain separate from requests.

### Notifications

* [ ] Notification concept is represented.
* [ ] Email delivery remains deferred to Group R.

### Roles

* [ ] Customer/Staff/Admin concepts are represented.
* [ ] Detailed permissions remain deferred to Group D.

### Security

* [ ] Sensitive information is classified.
* [ ] API exposure levels are identified.
* [ ] Client-controlled financial data is not treated as authoritative.

---

# 53. Explicitly Out of Scope

Do NOT:

```text
Create MySQL migrations
Create actual tables
Choose column types
Create Laravel models
Create Eloquent relationships
Create API routes
Create controllers
Create API Resources
Create OpenAPI schemas
Create Next.js code
Create MUI components
Create Flutter code
Configure authentication
Configure payment provider
Configure file storage
Configure hosting
```

---

# 54. Definition of Done

Phase 1.4 is complete when:

1. Every Version 1 entity has documented logical data requirements.
2. Every important attribute is classified as required, optional, conditional, derived, snapshot, or external where applicable.
3. Conceptual relationships and cardinalities are documented.
4. Business ownership of each important fact is clear.
5. Historical transaction data requirements are explicit.
6. Privacy classifications are explicit.
7. Public vs restricted data exposure is identified.
8. Uniqueness and integrity requirements are documented.
9. Concurrency-sensitive data operations are identified.
10. Transaction boundaries are identified conceptually.
11. No physical database implementation has been created.
12. No Laravel implementation has been created.
13. No API implementation has been created.
14. No frontend implementation has been created.

---

# 55. STOP CONDITION — Mandatory

After completing Phase 1.4:

**STOP.**

Do not automatically create:

```text
MySQL schema
ERD physical diagram
Migrations
Laravel models
Repositories
API endpoints
```

The next phase must be explicitly requested.

Recommended next phase:

**Phase 1.5 — Review and Freeze the Logical Data Model**

That phase should perform a formal consistency review of the logical model before the project moves into physical database design.