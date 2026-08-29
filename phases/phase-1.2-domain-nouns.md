# Phase 1.2 — Define Domain Nouns and Boundaries

## 1. Purpose

Phase 1.2 translates the approved business decisions from Phase 1.1 into a precise domain vocabulary.

This phase is about deciding **what things exist in the business domain, what each thing means, how they relate, and where their responsibility ends**.

This phase must NOT design database tables, migrations, Laravel models, API endpoints, frontend components, Flutter classes, or implementation code.

The output of this phase will be the domain vocabulary that later phases use to design the data model and API without inventing concepts independently.

---

## 2. Dependency Rule

Read and respect:

- `VISION.md`
- `agent.md`
- completed `phase-1.1-api-scope.md`

Phase 1.1 is authoritative for the currently approved business decisions.

Do not reopen resolved Phase 1.1 decisions unless a direct contradiction is discovered.

If a contradiction is discovered:

1. identify it explicitly;
2. do not silently choose a new rule;
3. record it as a blocking domain decision;
4. stop before proceeding to schema/API design.

---

## 3. Scope of This Phase

Define and document the domain concepts for:

### Customer and identity

- User
- Customer
- Guest visitor
- Authenticated customer
- Admin
- Staff

### Product catalog

- Product
- Category
- Product image
- Product variant
- Product availability
- Inventory/stock

### Commerce

- Cart
- Cart item
- Order
- Order item
- Fulfillment
- Pickup
- Delivery
- Delivery fee
- Payment

### Order lifecycle

- Order status
- Order status history
- Cancellation
- Cancellation window

### Customer requests

- Made-to-order request
- General enquiry
- Attachment

### Supporting concepts

- Notification
- Address
- Billing address
- Contact information
- Currency
- Order number/reference

Only define concepts needed by the currently approved Version 1 scope.

Do not create speculative concepts simply because a large e-commerce platform might eventually need them.

---

# 4. Core Domain Principle

Use business language rather than technical language.

For example:

Good:

> A Product is a furniture item represented in the catalog and may either be available for direct purchase or be requestable for manufacture.

Avoid:

> A Product is a row with an integer primary key and several columns.

The first is domain knowledge.

The second belongs to database design and is out of scope for this phase.

---

# 5. Product Domain

Define exactly what a Product means.

The current business rule is:

### Product types

`IN_STOCK`

A product that can currently be purchased through normal checkout, subject to available inventory.

`MADE_TO_ORDER`

A product that is not purchased directly through normal checkout and instead allows the customer to submit a request.

Document that product type controls the customer's primary action.

Example:

```text
IN_STOCK
    → Add to Cart / Buy
    → Checkout

MADE_TO_ORDER
    → Request This Furniture
```

Do not allow domain language that implies a made-to-order product is automatically an order.

A request is not an order.

---

# 6. Inventory Domain

Define Inventory as a business concept separate from Product.

A Product describes **what the business sells**.

Inventory describes **how many purchasable units are currently available**.

Document the distinction between:

- product
- variant
- stock quantity
- reserved quantity
- available quantity

Do not yet define formulas or database fields.

The later data-model phase will formalize the exact representation.

The domain definition should make clear that customer-facing availability is controlled by authoritative backend inventory, not by a frontend's cached value.

---

# 7. Product Variants

Determine the meaning of a Product Variant.

A variant may represent a purchasable variation such as:

- color
- material
- size
- configuration

The domain definition must answer:

> Is the variant itself the purchasable inventory unit when variants exist?

For this project, use the following default domain interpretation unless Phase 1.1 or business requirements explicitly contradict it:

**A variant represents a purchasable variation of a product and may have its own stock and pricing when required.**

Do not implement this yet.

---

# 8. Customer and User Identity

Clarify the difference between:

### Guest visitor

A person browsing public content without an authenticated account.

A guest visitor can:

- browse products
- browse categories
- search
- view product details
- view prices
- view availability
- submit a made-to-order request
- submit a general enquiry

A guest visitor cannot perform account-only operations.

### Authenticated customer

A registered user who has authenticated successfully.

Account-required checkout means checkout must be associated with an authenticated customer account.

### User

Use `User` as the system-level identity concept.

A User may represent a:

- customer
- staff member
- administrator

Role controls what the user is authorized to do.

Do not create multiple independent authentication systems for customers, staff, and admins unless a later security decision requires it.

---

# 9. Customer Requests

Define a `Made-to-order Request` as a business lead/request, not a purchase.

A request may be submitted:

- by an authenticated customer
- anonymously

Anonymous requests must provide enough contact information for follow-up, including email and/or phone as required by the approved business rules.

A request may optionally reference an existing catalog product.

A request may include:

- quantity
- dimensions
- preferred material
- preferred color
- notes
- optional attachment

A request does not automatically create:

- an order
- a payment
- a stock reservation

The business may later contact the requester, quote the request, and decide how to proceed.

---

# 10. General Enquiry

Define `Enquiry` separately from `Made-to-order Request`.

An enquiry is a general customer communication that does not necessarily ask the business to manufacture a particular furniture item.

An enquiry may be submitted:

- by an authenticated customer
- anonymously

An enquiry must support contact information and a message.

Optional attachment support applies.

Do not merge `Enquiry` and `Made-to-order Request` into one generic concept merely to reduce the number of entities.

They represent different business intents.

---

# 11. Cart

Define a Cart as a customer's temporary collection of items intended for checkout.

The domain must support the fact that:

- the customer must be authenticated to complete checkout;
- product availability can change between adding an item and checkout;
- cart contents do not themselves guarantee inventory reservation;
- the backend must revalidate purchasability and stock before creating the order.

Do not define database persistence strategy yet.

---

# 12. Order

Define an Order as the confirmed commerce record representing a purchase attempt that has entered the checkout/order workflow.

An order contains purchased item information and the selected fulfillment method.

Orders are only created through the purchase flow.

Made-to-order requests and general enquiries are not orders.

The order domain must preserve historical purchase information even if the catalog product later changes.

This means the later data model must be capable of preserving order-time facts such as:

- purchased product name
- purchased SKU/identifier
- unit price
- quantity
- item subtotal

Do not choose the storage implementation yet.

---

# 13. Fulfillment

Define `Fulfillment` as the way the customer receives the order.

Version 1 supports exactly:

```text
PICKUP
DELIVERY
```

### Pickup

- customer collects the order;
- delivery fee is not applied.

### Delivery

- the business delivers the order;
- a delivery fee is added;
- the fee is determined by staff/admin and is variable;
- the customer should not be able to arbitrarily set the final fee.

Do not design delivery-zone algorithms yet.

---

# 14. Address Concepts

Define the distinction between:

### Billing address

The address associated with payment/transaction information as required by the payment flow.

### Delivery address

The destination for a delivery order.

### Saved address book

Deferred from Version 1.

Therefore Version 1 should treat addresses primarily as part of the relevant transaction/order data rather than as a persistent customer address-book feature.

Do not implement an address-book domain yet.

---

# 15. Payment

Define `Payment` as the financial transaction associated with an order.

Version 1 payment-provider selection is deferred to Phase Group G.

Therefore this phase must not:

- choose a provider;
- define provider-specific fields;
- define payment SDKs;
- define gateway callbacks;
- define webhook payloads.

The domain should only establish that:

```text
Order
    ↕
Payment
```

and that payment state is distinct from order state.

Examples of conceptual payment states may be documented only at a generic level if needed, but provider-specific behavior is out of scope.

---

# 16. Order Status

Define order status as a lifecycle state.

Current Version 1 lifecycle:

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
READY_FOR_PICKUP
```

or:

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
SHIPPED
        ↓
DELIVERED
        ↓
COMPLETED
```

Document that the exact legal transitions will be formalized in a later order-state phase.

Do not yet implement state machines or database constraints.

---

# 17. Customer Cancellation

The approved business rule is:

**The customer has a 20-minute cancellation window.**

Document the domain meaning carefully:

- cancellation is time-bounded;
- it is an order operation;
- eligibility must be evaluated by the backend;
- the exact interaction with payment state, processing state, and refunds must be formalized later.

Do not invent a refund policy in this phase.

Do not assume that every cancellation automatically means a refund.

Refund behavior belongs to later payment/order rules.

---

# 18. Order Number

The customer-facing order reference uses:

```text
OD-*****
```

The asterisks represent the future generated sequence/reference portion.

Do not decide:

- numeric vs alphanumeric suffix;
- length;
- database generation method;
- concurrency strategy.

Those belong to later implementation/design phases.

---

# 19. Notifications

Define Notification as a communication/alert associated with an application event, such as order-state changes.

Version 1 does NOT require real email delivery.

Email/notification delivery is deferred to Phase Group R.

Therefore, do not design:

- email providers;
- SMTP configuration;
- email templates;
- push providers.

The domain may acknowledge that an application can have notification records/events later.

---

# 20. Attachment

Define an Attachment as an optional file associated with:

- a made-to-order request; or
- a general enquiry.

Version 1 does not yet choose storage technology.

Do not choose:

- S3
- Cloudinary
- local filesystem
- CDN
- image processing service

at this stage.

Only define the business relationship.

---

# 21. Role Boundaries

Do not define detailed permissions yet.

Record only the high-level distinction:

### Customer

Customer-facing commerce and account operations.

### Staff

Operational business work, including order/request handling, subject to later permission rules.

### Admin

Administrative control, subject to later permission rules.

The exact permission matrix belongs to Phase Group D.

Do not invent permissions in Phase 1.2.

---

# 22. Domain Boundary Map

Produce a boundary map similar to:

```text
IDENTITY
├── User
├── Customer
├── Staff
└── Admin

CATALOG
├── Category
├── Product
├── Product Variant
├── Product Image
└── Inventory

COMMERCE
├── Cart
├── Cart Item
├── Order
├── Order Item
├── Payment
└── Fulfillment

ORDER OPERATIONS
├── Order Status
├── Order Status History
├── Cancellation
└── Delivery

CUSTOMER COMMUNICATION
├── Made-to-order Request
├── Enquiry
└── Attachment

SUPPORTING
├── Address
├── Billing Address
├── Delivery Address
├── Notification
└── Order Number
```

This is a conceptual map only.

---

# 23. Identify Aggregates / Ownership

Without designing database relationships, identify which concepts are owned by which larger business concept.

At minimum consider:

```text
Product
    owns/contains its catalog information

Product Variant
    belongs to Product

Inventory
    belongs to a purchasable product/variant

Cart Item
    belongs to Cart

Order Item
    belongs to Order

Order Status History
    belongs to Order

Delivery
    belongs to Order

Payment
    belongs to Order

Made-to-order Request
    may reference Product
    may reference User
    may exist without User

Enquiry
    may reference User
    may exist without User

Attachment
    belongs to Request or Enquiry
```

Do not convert these directly into foreign keys yet.

The purpose is to establish business ownership.

---

# 24. Identify Concepts That Must Never Be Confused

Explicitly document these distinctions:

```text
Product ≠ Inventory
Product ≠ Order Item
Product ≠ Made-to-order Request

User ≠ Customer Session

Made-to-order Request ≠ Order
Enquiry ≠ Made-to-order Request

Cart ≠ Order

Payment ≠ Order Status

Delivery ≠ Order

Product availability ≠ Payment state

Order status ≠ Payment status
```

These distinctions are important because many implementation errors come from collapsing separate domain concepts.

---

# 25. Anonymous vs Authenticated Capability Matrix

Produce a domain-level matrix.

Example:

| Capability | Anonymous | Authenticated Customer | Staff | Admin |
|---|---:|---:|---:|---:|
| Browse catalog | Yes | Yes | Yes | Yes |
| Search products | Yes | Yes | Yes | Yes |
| View product | Yes | Yes | Yes | Yes |
| Submit made-to-order request | Yes | Yes | Yes* | Yes* |
| Submit enquiry | Yes | Yes | Yes* | Yes* |
| Checkout | No | Yes | Depends on later policy | Depends on later policy |
| View own orders | No | Yes | No | Depends on later policy |
| Manage products | No | No | Later policy | Later policy |

`*` means staff/admin capabilities are not being defined by this phase; do not treat these rows as final permissions.

The purpose is to establish the public-vs-account boundary, not to define authorization in detail.

---

# 26. Do Not Design the API Yet

Do not create:

- routes
- controllers
- HTTP methods
- endpoint names
- JSON payloads
- status codes
- pagination format
- authentication middleware
- OpenAPI schemas

Those belong to the API contract phase after the domain has stabilized.

The domain definitions in this phase should make the API design easier later.

---

# 27. Do Not Design the Database Yet

Do not create:

- ER diagrams with physical columns
- migration files
- primary keys
- foreign-key names
- indexes
- JSON columns
- enum database types
- timestamps strategy

A conceptual domain model is required first.

Database design belongs to the next data-modeling stage.

---

# 28. Required Deliverables

At the end of this phase, produce the following documents:

## A. `domain-glossary.md`

For every domain noun:

- Name
- Definition
- Purpose
- Main owner/parent concept
- Important distinctions
- Version 1 relevance

## B. `domain-boundaries.md`

Contain:

- domain groups
- ownership boundaries
- aggregate candidates
- cross-domain references
- concepts intentionally deferred

## C. `capability-matrix.md`

Contain the anonymous/authenticated/staff/admin high-level capability matrix.

## D. `domain-decisions.md`

Record decisions made during this phase.

Every decision must identify:

- decision
- reason
- affected concepts
- later phases affected

## E. `open-domain-questions.md`

Only questions that genuinely block safe domain modeling should be listed.

Do not create artificial questions merely because more detailed implementation decisions exist later.

---

# 29. Validation Checklist

Before declaring Phase 1.2 complete, verify:

- [ ] Every business noun used in Phase 1.1 has a precise definition.
- [ ] Product and inventory are separate concepts.
- [ ] Product and order item are separate concepts.
- [ ] Made-to-order request is not an order.
- [ ] General enquiry is not a made-to-order request.
- [ ] Anonymous browsing is explicitly represented.
- [ ] Anonymous requests and enquiries are supported.
- [ ] Checkout requires an authenticated customer account.
- [ ] Pickup and delivery are distinct fulfillment modes.
- [ ] Delivery fees are variable and operationally controlled.
- [ ] Payment is distinct from order status.
- [ ] The 20-minute cancellation rule is recorded.
- [ ] Saved addresses are explicitly deferred.
- [ ] Payment provider selection is explicitly deferred to Group G.
- [ ] Email delivery is explicitly deferred to Group R.
- [ ] Detailed staff/admin permissions are deferred to Group D.
- [ ] Optional attachments are supported conceptually without choosing storage.
- [ ] No database schema has been created.
- [ ] No API endpoints have been created.
- [ ] No frontend/mobile implementation has been started.

---

# 30. Definition of Done

Phase 1.2 is complete only when:

1. The project has one agreed vocabulary for all Version 1 business concepts.
2. Concepts that must remain separate are explicitly separated.
3. Ownership and conceptual relationships are documented.
4. Anonymous vs authenticated behavior is clear at the domain level.
5. Deferred implementation decisions are not prematurely decided.
6. No contradictory business rule has been introduced.
7. The resulting domain model is sufficiently precise for the next phase to design the physical data model.

---

# 31. STOP CONDITION — Mandatory

When all Phase 1.2 deliverables are complete:

**STOP.**

Do not continue automatically to:

- database design
- MySQL migrations
- Laravel models
- API endpoints
- authentication implementation
- Next.js
- MUI
- Flutter
- payment integration
- admin UI

The next phase must be requested explicitly.

Suggested next phase:

**Phase 1.3 — Identify Domain Invariants and Business Rules**
