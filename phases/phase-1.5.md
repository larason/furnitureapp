# Phase 1.5 — Review and Freeze the Logical Data Model

## 1. Purpose

Phase 1.5 is a **formal review and freeze phase**.

The purpose is not to add new features or begin implementation.

The purpose is to verify that the logical data model produced in Phase 1.4 is:

* internally consistent;
* compatible with Phase 1.1 business decisions;
* compatible with Phase 1.2 domain boundaries;
* compatible with Phase 1.3 invariants;
* sufficient to support the Version 1 workflows;
* free from unnecessary complexity;
* ready to become the foundation for the physical database design.

At the end of this phase, the logical data model should become a **stable baseline**.

Future database implementation must trace back to this frozen model.

---

# 2. Authoritative Inputs

Read all of the following before beginning:

```text
AGENTS.md
docs/VISION.md

phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3.md
phase-1.4.md
```

Also read the Phase 1.4 supporting documents if they exist:

```text
entity-relationship-matrix.md
data-classification.md
data-ownership.md
historical-data-rules.md
data-integrity-requirements.md
data-model-decisions.md
```

Do not treat a later draft as more authoritative than an approved earlier business decision.

---

# 3. Core Rule

This phase is a **review gate**.

Do not use this phase to redesign the business.

Do not add speculative features.

Do not compensate for gaps by inventing technical structures.

The goal is:

```text
Business requirements
        ↓
Domain concepts
        ↓
Business invariants
        ↓
Logical data model
        ↓
FORMAL REVIEW
        ↓
FROZEN BASELINE
```

---

# 4. Review the Entire Model as One System

Do not review entities individually only.

Review them as interconnected workflows.

At minimum walk through:

```text
Anonymous browsing
Authenticated purchase
Pickup order
Delivery order
Customer cancellation
Order tracking
Made-to-order request
Anonymous made-to-order request
Authenticated made-to-order request
General enquiry
Anonymous enquiry
Authenticated enquiry
Inventory depletion
Product changes after purchase
Payment lifecycle
Admin/staff order operations
```

Every workflow must be representable using the logical data model.

---

# 5. Business-to-Data Traceability

Create a traceability matrix.

Every important business requirement from Phase 1.1–1.3 must map to one or more logical data concepts.

Example:

| Business requirement       | Supporting data concept        |
| -------------------------- | ------------------------------ |
| Anonymous browsing         | Public product/category data   |
| Authenticated checkout     | User + Order                   |
| Variable delivery fee      | Order/Fulfillment data         |
| 20-minute cancellation     | Order lifecycle/time data      |
| Order tracking             | Order + Status History         |
| Made-to-order request      | Furniture Request              |
| Anonymous enquiry          | Enquiry without mandatory User |
| Optional attachments       | Attachment                     |
| Historical purchased price | Order Item snapshot            |
| Payment                    | Payment                        |
| Product availability       | Inventory                      |

Every requirement must have coverage.

If a requirement has no data support, flag it.

---

# 6. Workflow Coverage Review

For each workflow, answer:

1. What entity starts the workflow?
2. What entities are created or changed?
3. What information must be available?
4. What information must remain historical?
5. Who owns the information?
6. Can the workflow work for anonymous users where required?
7. Can the workflow work for authenticated users where required?
8. Can the workflow be audited later?
9. Does the model support the workflow without introducing duplicated facts?

Do this for every Version 1 workflow.

---

# 7. Anonymous Browsing Review

Verify that the data model supports public access to:

```text
Categories
Products
Product images
Product prices
Availability
Variants
```

without requiring:

```text
User
Customer account
Authentication session
```

The model must not accidentally make public catalog records dependent on customer identity.

---

# 8. Authenticated Checkout Review

Verify the data model can represent:

```text
Authenticated customer
        ↓
Cart
        ↓
Checkout
        ↓
Order
        ↓
Payment
        ↓
Fulfillment
```

Confirm that the model contains enough information to:

* identify the customer;
* identify purchased products;
* preserve quantities;
* preserve purchase-time prices;
* calculate/store relevant totals;
* identify pickup vs delivery;
* associate payment;
* track order state.

---

# 9. Guest Request Review

Verify that a made-to-order request can exist without a user.

The logical model must support:

```text
Request
├── authenticated user reference: optional
├── name: required
├── phone/email: required as approved
└── request details
```

The model must not require a fake customer account merely to store an anonymous request.

---

# 10. Guest Enquiry Review

Perform the same review for enquiries.

Verify:

```text
Enquiry
├── user: optional
├── name/contact: required
├── message: required
└── attachment: optional
```

Do not merge this concept into the request entity merely to reduce the entity count.

---

# 11. Product Lifecycle Review

Verify that the logical model can represent:

```text
Product created
        ↓
Product published
        ↓
Product available
        ↓
Product becomes unavailable
        ↓
Product deactivated
```

Review whether the model distinguishes:

```text
product existence
product activity
product purchasability
inventory availability
```

These are not automatically the same business concept.

---

# 12. Product Type Review

Verify that the model correctly separates:

```text
IN_STOCK
MADE_TO_ORDER
```

Check that the model does not unintentionally allow:

```text
MADE_TO_ORDER
→ inventory purchase
→ normal checkout
```

without violating a business invariant.

---

# 13. Inventory Review

Verify that the logical model can support:

```text
current stock
reserved stock
available stock
```

and eventually enforce:

```text
available stock >= 0
```

Review whether the model can support simultaneous purchases without logical ambiguity.

Do not yet select locking strategies or MySQL mechanisms.

---

# 14. Variant Review

Verify that a product with variants can be represented without ambiguity.

Example:

```text
Modern Sofa
├── Grey
├── Beige
└── Black
```

The model must make it possible to determine:

```text
Which product?
Which variant?
What price?
What stock?
```

when applicable.

Ensure that a variant cannot logically belong to two unrelated products.

---

# 15. Cart Review

Verify the model supports:

```text
Cart
    ↓
Cart Item
    ↓
Product/Variant
    ↓
Quantity
```

Check that cart data does not accidentally become an inventory ledger.

Ensure the model supports revalidation at checkout.

---

# 16. Order Review

Ask:

> Can an order explain itself six months after purchase?

A complete order must be able to establish:

```text
Customer
Order reference
Purchased items
Quantity
Price
Subtotal
Delivery fee
Total
Fulfillment type
Address where applicable
Current status
Status history
Payment relationship
```

without depending on mutable frontend state.

---

# 17. Historical Data Review

Explicitly test these scenarios conceptually.

### Scenario A — Product renamed

```text
Product:
"Modern Sofa"

Later:
"Modern Luxury Sofa"
```

Can the old order still show the original purchased name?

### Scenario B — Product repriced

```text
Old price:
1,250,000

New price:
1,450,000
```

Can the old order retain its original price?

### Scenario C — Product deactivated

Can historical orders still display the purchased item?

### Scenario D — Variant changed

Can the historical order still identify what was purchased?

If the answer is no, the logical model is not ready to freeze.

---

# 18. Order Status History Review

Verify the model can answer:

```text
What was the previous status?
What is the new status?
When did it change?
Who/what changed it?
Was there an operational note?
```

A simple current-status field is not sufficient by itself.

---

# 19. Cancellation Review

Verify the model can support the approved:

**20-minute customer cancellation window.**

It must be possible later to determine:

```text
When did the cancellation window begin?
Is the customer request inside the window?
What is the order's current status?
Was cancellation already performed?
```

Do not decide exact database fields yet.

The purpose is only to confirm the logical model contains enough concepts.

---

# 20. Payment Review

Verify that payment remains separate from orders.

The model must support:

```text
Order
   ↕
Payment
```

without forcing:

```text
payment status = order status
```

The model must also have a place for future provider references.

Do not choose a provider yet.

---

# 21. Fulfillment Review

Verify the model supports:

```text
PICKUP
DELIVERY
```

and distinguishes:

```text
delivery fee
delivery address
delivery status
```

from the general order.

Check that a pickup order does not require unnecessary delivery data.

---

# 22. Variable Delivery Fee Review

Verify that the data model supports:

```text
Delivery fee = variable
```

rather than assuming:

```text
Delivery fee = one global constant
```

The model must preserve the fee actually associated with a historical order.

If the business changes its normal delivery pricing later, old orders must not change.

---

# 23. Address Review

Verify:

```text
Billing address
Delivery address
```

can exist conceptually where needed.

Verify that saved customer addresses remain outside Version 1.

Do not accidentally build the logical model around a persistent address book.

---

# 24. Attachment Review

Verify optional attachments can belong to:

```text
Made-to-order Request
General Enquiry
```

without making attachments mandatory.

Check that attachments have an unambiguous owner.

Avoid a vague model such as:

```text
Attachment → random record
```

without defining ownership.

---

# 25. Notification Review

Verify notification concepts do not accidentally make email delivery mandatory.

The logical model must remain compatible with:

```text
email deferred to Phase Group R
```

Do not introduce an external email provider dependency.

---

# 26. Role Review

Verify the logical model can distinguish:

```text
CUSTOMER
STAFF
ADMIN
```

without prematurely modeling the complete permission system.

Detailed authorization remains deferred to Group D.

---

# 27. Privacy Review

Review all important data classifications.

Verify that the model clearly identifies:

```text
PUBLIC
INTERNAL
PRIVATE
SENSITIVE
```

At minimum review:

```text
Customer email
Customer phone
Password credentials
Payment data
Staff notes
Customer requests
Customer enquiries
Orders
Product catalog data
Inventory internals
```

Any sensitive information that is currently ambiguous must be documented.

---

# 28. Ownership Review

For every important business fact, ask:

> Which entity owns this fact?

Examples:

```text
Current product price
→ Product/catalog

Current stock
→ Inventory

Historical purchase price
→ Order Item

Current order status
→ Order

Status history
→ Order Status History

Final delivery fee
→ Order/Fulfillment transaction

Payment confirmation
→ Payment
```

There should be one clear authoritative owner.

---

# 29. Duplication Review

Identify all data that appears in more than one logical concept.

For every duplication, classify it:

```text
intentional historical snapshot
derived value
cached value
integration reference
unnecessary duplication
```

Anything classified as unnecessary duplication should be removed before freezing.

---

# 30. Derived Data Review

For every derived value, identify the source.

Examples:

```text
Line subtotal
→ quantity × unit price

Order subtotal
→ sum of line subtotals

Order total
→ subtotal + delivery fee - discounts

Available inventory
→ based on authoritative stock/reservation model
```

Do not decide exact persistence strategy yet.

---

# 31. Snapshot Review

Explicitly verify transaction snapshots.

At minimum confirm the model preserves:

```text
Purchased product name
Purchased SKU/reference
Purchased unit price
Purchased quantity
Purchased subtotal
Final delivery fee
Transaction address information
```

The exact physical database representation comes later.

---

# 32. Historical Immutability Review

Identify values that must not be silently changed after transaction finalization.

At minimum:

```text
Order reference
Order item purchased price
Order item purchased name/reference
Order item quantity
Final order total
Final delivery fee
Transaction address
Payment transaction reference
```

The later implementation must protect these business facts.

---

# 33. Referential Relationship Review

Review every conceptual relationship.

For each relationship determine:

```text
Required or optional?
One-to-one?
One-to-many?
Many-to-one?
Many-to-many?
Conditional?
Historical?
```

Do not automatically translate this into physical foreign keys yet.

---

# 34. Cardinality Review

Specifically inspect relationships such as:

```text
Category → Products
Product → Images
Product → Variants
Variant → Inventory
Cart → Cart Items
Order → Order Items
Order → Status History
Order → Payment
Order → Delivery
User → Orders
User → Notifications
User ↔ Requests
User ↔ Enquiries
Request → Attachments
Enquiry → Attachments
```

Ensure no relationship is logically ambiguous.

---

# 35. Optional vs Required Review

Audit every attribute group.

Use:

```text
REQUIRED
OPTIONAL
CONDITIONAL
DERIVED
SNAPSHOT
EXTERNAL
```

Examples:

```text
Delivery address
→ CONDITIONAL

Request user
→ OPTIONAL

Order item price
→ REQUIRED SNAPSHOT

Available inventory
→ DERIVED/authoritative according to final model

Payment provider reference
→ EXTERNAL
```

If the reason for a nullable/optional value is unclear, fix the logical specification before freezing.

---

# 36. Deletion and Retention Review

For each entity, decide the logical business expectation:

```text
can disappear entirely
can be deactivated
must remain historically available
must be archived
retention policy pending
```

At minimum:

```text
Orders
Order Items
Order Status History
Payments
Requests
Enquiries
```

should be reviewed explicitly.

Do not invent legal retention durations.

---

# 37. Auditability Review

Verify that critical operations can eventually be investigated.

At minimum:

```text
Order status changes
Payment status changes
Critical inventory changes
Administrative critical changes
```

If a business-critical event cannot be traced conceptually, flag it.

---

# 38. Concurrency Review

Review logical data support for:

```text
Two customers purchasing the last item
Duplicate checkout submission
Duplicate payment notification
Repeated order status command
```

The model must not make these cases impossible to reason about.

Do not implement locks or idempotency keys yet.

---

# 39. API Readiness Review

The data model will later drive the API.

Check whether each future API domain has enough logical information:

```text
Authentication
Products
Categories
Inventory/availability
Cart
Checkout
Orders
Tracking
Requests
Enquiries
Profile
Payments
Admin operations
```

Do not design endpoints yet.

The goal is simply to ensure that the underlying logical data is sufficient.

---

# 40. Frontend Readiness Review

Verify that the logical model can support the required UI experiences.

### Website

```text
Product cards
Product details
Categories
Search
Availability
Cart
Checkout
Order tracking
Request form
Enquiry form
```

### Flutter app

```text
Catalog
Product details
Cart
Checkout
Orders
Tracking
Requests
Profile
```

### Admin

```text
Products
Inventory
Orders
Requests
Enquiries
Customers
```

Do not build UI.

This is only a readiness check.

---

# 41. SEO Data Readiness Review

Verify the product/category model supports the public website's SEO requirements.

At minimum the public product concept must contain enough information for:

```text
Product title
Slug
Description
Images
Price
Availability
Category
Variant information where applicable
```

Review logical SEO fields from Phase 1.4.

Do not add SEO-specific technical structures unless required.

---

# 42. Complexity Review

This is an important architectural gate.

Identify anything that exists only because:

> "A large e-commerce company might eventually need it."

Examples:

```text
warehouse management
multi-vendor marketplace
multi-country tax engine
complex promotion engine
multi-currency ledger
delivery fleet management
manufacturing ERP
supplier management
advanced loyalty program
enterprise workflow engine
```

Unless explicitly required for Version 1, remove these from the logical model.

The system must remain appropriate for the current business scale.

---

# 43. Future Extensibility Review

At the same time, verify that the model does not block obvious future growth.

It should remain reasonably possible to later add:

```text
Wishlist
Coupons
Promotions
Reviews
Push notifications
Saved addresses
Delivery tracking
Additional roles
Additional payment providers
Made-to-order conversion into an order
```

Do not implement these features.

The test is:

> Can they be added later without destroying the Version 1 model?

---

# 44. Anti-Pattern Review

Explicitly check for:

### God entity

One entity containing unrelated information from many domains.

### Generic entity

An entity such as:

```text
data
records
metadata
activities
```

used instead of real domain concepts.

### Accidental coupling

For example:

```text
Enquiry requires User
```

when anonymous enquiries are approved.

### Historical corruption

Current product data rewriting old orders.

### Frontend authority

The model assuming client-provided prices or stock are trustworthy.

### Premature enterprise complexity

Adding systems unrelated to Version 1.

Record every violation and correct it before freeze.

---

# 45. Contradiction Matrix

Create a formal matrix comparing:

```text
Phase 1.1
Phase 1.2
Phase 1.3
Phase 1.4
```

Check:

```text
Authentication
Product types
Inventory
Checkout
Fulfillment
Delivery fee
Cancellation
Orders
Payments
Requests
Enquiries
Attachments
Notifications
Roles
Addresses
```

For each area record:

```text
CONSISTENT
or
CONFLICT
```

There must be no unresolved conflict before freeze.

---

# 46. Change Classification

Any proposed changes discovered during review must be classified.

### Class A — Clarification

No business behavior changes.

May be incorporated into the documentation.

### Class B — Structural logical correction

Fixes an error in the model without changing approved business behavior.

May be incorporated before freeze.

### Class C — Business rule change

Changes actual business behavior.

Must NOT be silently incorporated.

It must return to the appropriate decision/requirements phase.

### Class D — New Version 1 feature

Must be rejected from this phase and scheduled separately.

This prevents scope creep.

---

# 47. Freeze Rules

Once all validation passes, freeze:

```text
Entity names
Domain boundaries
Entity responsibilities
Core relationships
Required/optional semantics
Historical requirements
Business fact ownership
Privacy classification
Version 1 data scope
```

The frozen model becomes the source from which physical schema design is derived.

---

# 48. Version the Frozen Model

Create a formal baseline:

```text
Logical Data Model
Version: 1.0
Status: FROZEN
```

Include:

```text
Freeze date
Documents reviewed
Approvals/decision status
Known deferred items
Change-control rule
```

Do not overwrite previous drafts without retaining their history.

---

# 49. Change-Control Rule After Freeze

After freezing:

A future change must not simply edit the model silently.

Use:

```text
Change request
    ↓
Reason
    ↓
Affected business rule
    ↓
Affected entities
    ↓
Impact analysis
    ↓
Approval
    ↓
Version increment
```

For example:

```text
1.0
↓
1.1
```

for a compatible refinement.

A major business-model change may require:

```text
2.0
```

according to the project's versioning convention.

---

# 50. Required Deliverables

Produce:

## A. `logical-data-model-review.md`

Complete review findings.

Include:

```text
Reviewed artifacts
Workflow coverage
Entity coverage
Relationship review
Historical-data review
Security/privacy review
Complexity review
Extensibility review
Issues found
Corrections made
Final status
```

---

## B. `business-to-data-traceability.md`

Map business requirements to logical data concepts.

---

## C. `logical-model-consistency.md`

Show consistency across:

```text
Phase 1.1
Phase 1.2
Phase 1.3
Phase 1.4
```

---

## D. `logical-model-risk-review.md`

Document risks such as:

```text
historical data corruption
overselling
ambiguous ownership
missing anonymous workflow support
excessive duplication
privacy leakage
premature complexity
```

---

## E. `logical-data-model-v1.md`

This is the **final frozen logical model**.

It should reference the approved documents and contain the definitive entities, relationships, ownership, classifications, and requirements.

---

## F. `freeze-record.md`

Example:

```text
Logical Data Model Version: 1.0
Status: FROZEN
Review completed: YYYY-MM-DD

No unresolved logical-model conflicts found.

Approved scope:
Version 1 ecommerce platform
```

---

# 51. Definition of Done

Phase 1.5 is complete only when:

* [ ] Every Version 1 workflow can be represented.
* [ ] Every major business rule has data support.
* [ ] Anonymous browsing remains independent of authentication.
* [ ] Anonymous requests are supported.
* [ ] Anonymous enquiries are supported.
* [ ] Checkout remains account-required.
* [ ] Product types are represented correctly.
* [ ] Inventory responsibility is clear.
* [ ] Cart and order are separate.
* [ ] Historical order facts are protected.
* [ ] Order status history is supported.
* [ ] Pickup and delivery are distinct.
* [ ] Variable delivery fees are supported.
* [ ] Payment and order state remain separate.
* [ ] Cancellation timing can be represented.
* [ ] Requests and enquiries remain separate.
* [ ] Optional attachments are supported.
* [ ] Saved addresses remain deferred.
* [ ] Payment provider remains deferred to Group H.
* [ ] Email delivery remains deferred to Group R.
* [ ] Detailed permissions remain deferred to Group D.
* [ ] Sensitive/private data is classified.
* [ ] Business fact ownership is unambiguous.
* [ ] Unnecessary duplication has been removed.
* [ ] Speculative enterprise complexity has been rejected.
* [ ] Future extensibility has been reviewed.
* [ ] No unresolved contradictions remain.
* [ ] The logical model has been formally frozen.

---

# 52. Explicitly Out of Scope

Do NOT:

```text
Create MySQL tables
Create migrations
Choose column types
Create indexes
Create foreign keys
Create Laravel models
Create Eloquent relationships
Create repositories
Create API endpoints
Create OpenAPI schemas
Create authentication code
Create Next.js code
Create MUI code
Create Flutter code
Select payment provider
Select file storage provider
Deploy infrastructure
```

This phase is still architecture/specification work.

---

# 53. STOP CONDITION — Mandatory

When the following are complete:

```text
logical-data-model-review.md
business-to-data-traceability.md
logical-model-consistency.md
logical-model-risk-review.md
logical-data-model-v1.md
freeze-record.md
```

and all validation checks pass:

**STOP.**

Do not begin physical database design automatically.

Do not create migrations.

Do not create Laravel models.

Do not begin API implementation.

The frozen logical model must become the explicit input to the next requested phase.

Recommended next phase:

# Phase 1.6 — Physical Database Design and ERD

That phase should take the **frozen logical model** and translate it into a concrete MySQL design, including tables, columns, keys, relationships, constraints, indexes, naming conventions, and migration strategy—before any Laravel application code is written.
