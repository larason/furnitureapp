# Domain Boundaries — Version 1

## 1. Purpose

This document records the **domain groups**, **ownership boundaries**, **aggregate candidates**, **cross-domain references**, and **concepts intentionally deferred** for Version 1.

It is a conceptual model. It deliberately does **not** contain physical columns, foreign keys, indexes, migrations, or API endpoints. The data-model phase will translate this into a physical design.

---

## 2. Domain Groups

The Version 1 domain is organized into the following groups. This is a conceptual map only.

```text
IDENTITY
├── User
├── Customer
├── Guest Visitor
├── Staff
└── Admin

CATALOG
├── Category
├── Product
├── Product Type
├── Product Variant
├── Product Image
├── Product Availability
└── Inventory

COMMERCE
├── Cart
├── Cart Item
├── Order
├── Order Item
├── Payment
└── Fulfillment (Pickup / Delivery / Delivery Fee)

ORDER OPERATIONS
├── Order Status
├── Order Status History
├── Cancellation
└── Cancellation Window

CUSTOMER COMMUNICATION
├── Made-to-order Request
├── General Enquiry
└── Attachment

SUPPORTING
├── Address
├── Billing Address
├── Delivery Address
├── Contact Information
├── Currency
├── Order Number / Reference
└── Notification
```

---

## 3. Ownership Boundaries

These are business ownership relationships, not yet foreign keys.

```text
Product
    owns/contains its catalog information

Product Variant
    belongs to Product

Product Image
    belongs to Product

Inventory
    belongs to a purchasable product/variant

Cart Item
    belongs to Cart

Order Item
    belongs to Order

Order Status
    belongs to Order

Order Status History
    belongs to Order

Cancellation
    is an operation on an Order

Fulfillment (Pickup/Delivery)
    belongs to Order

Delivery Fee
    belongs to the Delivery fulfillment of an Order

Payment
    belongs to Order

Made-to-order Request
    may reference Product
    may reference User
    may exist without User

General Enquiry
    may reference User
    may exist without User

Attachment
    belongs to a Made-to-order Request or a General Enquiry

Address / Billing Address / Delivery Address
    belongs to the relevant Order/payment transaction data

Notification
    references application events (e.g., order-state changes)
```

---

## 4. Aggregate Candidates

Candidates for transaction-style aggregates (things that must succeed or fail / change together):

- **Cart** with its **Cart Items** — treated as one unit when revalidated or converted.
- **Order** with its **Order Items**, **Fulfillment**, and **Order Status History** — the core commerce aggregate; totals, fulfilment, and status changes are validated together.
- **Payment** tied to an **Order** — payment state is tracked at the order level but kept distinct from order status.
- **Made-to-order Request** with its optional **Attachments**.
- **General Enquiry** with its optional **Attachments**.

These are aggregate *candidates* for business-consistency thinking. The exact transaction boundaries are decided in later phases (checkout, payment, order phases).

---

## 5. Cross-Domain References

- **Catalog → Commerce:** Cart Items and Order Items reference products/variants. Order Items additionally preserve order-time snapshots.
- **Catalog → Customer Communication:** Made-to-order Requests may reference a Product.
- **Identity → Commerce:** Orders and Carts reference an Authenticated Customer.
- **Identity → Customer Communication:** Requests and Enquiries may reference a User; both may also exist without one (anonymous, with Contact Information).
- **Commerce → Order Operations:** Orders carry Status, Status History, Cancellation, and Fulfillment.
- **Commerce → Supporting:** Orders carry Addresses, Order Number, and Payment; notifications reference order events.
- **Communication → Supporting:** Requests/Enquiries use Contact Information and optional Attachments.

---

## 6. Concepts Intentionally Deferred

Deferred concepts are recognized as possible future extensions but are **not** modeled in Version 1. Do not let them leak into the Version 1 data model.

```text
Saved address book (persistent customer addresses)
Wishlist
Reviews / ratings
Coupons / promotions
Loyalty points / customer segmentation
Payment provider selection and provider-specific behavior (Phase Group G)
Real email / push / SMS delivery (Phase Group R)
Dynamic delivery-zone / distance / size pricing
Background jobs / queues (until volume requires them)
GPS / real-time courier tracking
Manufacturing workflow / production planning
Multi-vendor marketplace functionality
Enterprise warehouse-management / ERP / route optimization
```

---

## 7. Open Boundary Notes

- **Cart identity/binding** (account-only vs guest-before-login) is an open domain question and affects the Cart ownership boundary — see `open-domain-questions.md`.
- **Delivery fee setting point** (at checkout vs during fulfilment) affects whether the Delivery Fee belongs to checkout-time order data or post-payment fulfilment data — see `open-domain-questions.md`.
- **Request-to-order conversion** affects whether a Made-to-order Request may later reference a created Order — see `open-domain-questions.md`.