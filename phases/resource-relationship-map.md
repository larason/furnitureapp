# Resource Relationship Map — Version 1 (Conceptual Graph)

## 0. Purpose
Complete conceptual relationship graph for the API resource inventory. Detailed attributes and classification per `api-resource-relationships.md`. No URL paths defined.

## 1. Global Map

```text
User (Customer/Staff/Admin)
├── Cart (1 active per holder — GUEST_TOKEN holder when guest, authenticated User when logged in)
│   └── Cart Items (0..n)
│       ├── → Product (reference, required)
│       └── → Product Variant (optional reference, must belong to Product)
│       └── (via Cart) → Checkout workflow → Order
├── Orders (0..n, OWNERSHIP)
│   └── Order (each)
│       ├── Order Items (1..n, PARENT_CHILD, immutable snapshots)
│       │   ├── → Product (historical snapshot + optional current reference)
│       │   └── → Product Variant (optional historical + optional current)
│       ├── Order Addresses (PARENT_CHILD)
│       │   ├── billing (exactly 1, TRANSACTION snapshot)
│       │   └── delivery (0..1 conditional on DELIVERY, snapshot; canonical for delivery address)
│       ├── Payment(s) (0..n, REFERENCE transactional, separate resource, PAY-001)
│       ├── Delivery/Fulfillment (0..1 conditional on DELIVERY, projections equal to Order canonicals)
│       └── Tracking (1, read-oriented)
│           └── Status History (0..n append-only, projection without Operational note for customer)
├── Notifications (0..n, OWNERSHIP, private recipient)
├── Made-to-order Requests (0..n optional ownership, OWNERSHIP when linked)
└── Enquiries (0..n optional ownership)

Category (flat, single-level)
└── Products (0..n, DERIVED inverse of Product→Category)

Product
├── → Category (REFERENCE, required, 1)
├── Product Images (0..n, PARENT_CHILD)
├── Product Variants (0..n, PARENT_CHILD)
│   └── → Inventory (INTERNAL_ONLY / OPERATIONAL, one-to-one per purchasable variant)
├── → Inventory (INTERNAL_ONLY / OPERATIONAL, one-to-one per purchasable product without variants)
├── → Availability (DERIVED, embedded: available bool, stock_indicator coarse bucket IN STOCK/LOW STOCK/MADE TO ORDER, product_type — no exact quantity)
└── (via Variant or Product) → Availability

Product Variant
├── → Product (parent, PARENT_CHILD inverse)
└── → Inventory (INTERNAL_ONLY / OPERATIONAL)

Made-to-order Request
├── → Product (OPTIONAL_REFERENCE, when from product page)
├── → User (OPTIONAL_REFERENCE/OWNERSHIP, when authenticated; anonymous = User none + contact info)
└── Attachments (0..n, PARENT_CHILD)

General Enquiry
├── → User (OPTIONAL_REFERENCE/OWNERSHIP, when authenticated)
└── Attachments (0..n, PARENT_CHILD)

Attachment
└── → Owner: exactly one of Request or Enquiry (polymorphic, PARENT_CHILD inverse)

Authentication (operations around User)
└── User ↔ Authentication (INTERNAL_ONLY: register/login/logout/recovery/verification/session)
```

## 2. Views by Concern

### Catalog Browsing (Anonymous, no auth required)
```text
Category → Products → Product → {Images, Variants, Availability, Category summary}
```

### Commerce — Cart to Order
```text
Cart → Cart Items → Product/Variant
  ↓ (Checkout workflow, authenticated)
Order → {Order Items → (historical Product/Variant), Order Addresses, Delivery (if DELIVERY), Payment(s), Tracking → Status History}
```

### Communication — Leads
```text
Request → {Product?, User?, Attachments}
Enquiry → {User?, Attachments}
```

### Admin Operational Traversal
```text
Admin → Product → {Variants, Images, Inventory}
Admin → Order → {Customer (User), Items → Product/Variant, Payment, Delivery, Status History}
Admin → Request → {Product, User, Attachments}
Admin → Enquiry → {User, Attachments}
Admin → User → {Orders, Requests, Enquiries}
```
`User → Notifications` remains recipient-only (private per recipient); there is no unrestricted `Admin → User → Notifications` traversal. Any system-level notification audit requires a separate audited scope that excludes private notification content (see `relationship-security-review.md` §2).

## 3. Conditional and Optional Summary
- Conditional: `Order → Delivery` only when `fulfillment=DELIVERY`; billing Address exactly 1 always, delivery Address 0..1 conditional; `Cart Item → Variant` only when product has variants.
- Optional: `Request → Product`, `Request → User`, `Enquiry → User`.
- Derived: `Category → Products` (inverse), `Product/Variant → Availability` (derived from Inventory).
- Internal-only: `Product/Variant → Inventory` (customer sees Availability, staff sees Inventory), `User → credential internals`, `Payment → provider credentials`.

## 4. No Cycles in Embedded Representations
Conceptual graph contains cycles (User↔Orders↔User, Product↔Category↔Products) but API representations must not recursively embed full parents. Use SUMMARY/REFERENCE for back-links (e.g., Product embeds Category summary, not full Category with products; Order embeds User summary, not full User with orders).

