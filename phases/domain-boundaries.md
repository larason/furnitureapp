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
├── Fulfillment (Pickup / Delivery / Delivery Fee)
├── Delivery Fee Rule
└── Delivery Region / Delivery Location

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

Cart
    may be guest-owned (no User) or bound to an Authenticated Customer;
    becomes associated with the customer's account upon authentication

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

Delivery Fee Rule
    is staff-managed delivery pricing configuration keyed by
    Delivery Region / Delivery Location; does not belong to a single order

Delivery Region / Delivery Location
    belongs to the Delivery Fee Rule configuration (staff-managed)

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
- **Identity → Commerce:** Orders reference an Authenticated Customer. Carts reference an Authenticated Customer when the user is authenticated; a guest cart exists without a User and is bound to the account upon authentication.
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
Payment provider selection and provider-specific behavior (Phase Group H)
Real email / push / SMS delivery (Phase Group R)
Automatic/algorithmic delivery pricing (distance/size/item-count calculation)
Additional delivery regions/locations beyond what staff configure for Version 1
Background jobs / queues (until volume requires them)
GPS / real-time courier tracking
Manufacturing workflow / production planning
Multi-vendor marketplace functionality
Enterprise warehouse-management / ERP / route optimization
```

Note: staff-managed delivery fees differentiated by region/location are **in scope**; only automatic/algorithmic computation is deferred.

---

## 7. Resolved Boundary Notes

All Phase 1.2 open questions are resolved by the project owner. The resolutions affect boundaries as follows:

- **Cart identity/binding:** Cart is **not** strictly bound to an authenticated customer. A guest may create, view, and modify a cart without an account; the cart becomes associated with the customer's account upon authentication. Checkout still requires an authenticated account. The exact merge/resolution behavior between a guest cart and an existing account cart is an implementation detail for the cart phase, not a domain boundary.
- **Delivery fee ownership:** Delivery fees are staff-controlled and entered as part of product/delivery configuration, differentiated by delivery region/location. The fee applicable to an order is resolved and displayed at checkout once the customer inputs their delivery location and address. The Delivery Fee therefore belongs to checkout-time order data (part of the authoritative order total), and the Delivery Fee Rule is separate, staff-managed pricing configuration.
- **Request-to-order conversion:** Confirmed **out of Version 1 scope**. A Made-to-order Request never becomes an Order in Version 1; requests are captured and managed as leads only. Orders are created only through the normal purchase workflow.