### Recommended customer flow

```text
                         CUSTOMER
                            │
                 ┌──────────┴──────────┐
                 │                     │
          Browse available       Browse request-only
             furniture               furniture
                 │                     │
          Add to cart/buy       Request furniture
                 │                     │
                 └──────────┬──────────┘
                            │
                     Customer account
                            │
                ┌───────────┴───────────┐
                │                       │
             Purchase              Direct enquiry
                │
       Self-pickup / Delivery
                │
             Payment
                │
        Order confirmation
                │
          Order processing
                │
       Accepted → Shipped/Ready
                │
         Delivered/Picked up
```

I would make **three distinct actions** visible to the customer.

## 1. "Buy Now" / "Add to Cart"

For furniture that is **already manufactured and physically available**.

Example:

> Modern 3-Seater Sofa
> TZS 1,250,000
> **In Stock: 3**

Customer can:

**Add to Cart → Checkout → Choose Pickup/Delivery → Pay → Track Order**

The product should have a clear inventory status such as:

```text
IN STOCK
```

and optionally:

```text
Only 2 left
```

---

## 2. "Request This Furniture"

For furniture that **can be manufactured but isn't currently available**.

Instead of pretending it's purchasable, show something like:

> **Made to Order**
> This item is currently not in stock but can be manufactured upon request.

Then:

**Request This Furniture**

The form could collect:

```text
Name
Phone
Email
Furniture
Quantity
Preferred color/material
Dimensions
Additional requirements
```

And optionally:

> "I would like someone to contact me about this furniture."

This request becomes a **lead/request in the admin system**, rather than an order.

That distinction is important.

A made-to-order request is not necessarily:

```text
ORDER = YES
```

until you've confirmed price, specifications, production time, etc.

---

# 3. "Contact Us / Make an Enquiry"

This is broader than a product request.

Someone may come to the site and say:

> "I need a custom 6-seater dining table, 2.4m long, walnut finish."

They aren't necessarily requesting one of your catalog products.

So I'd allow:

**General Furniture Enquiry**

with:

```text
Name
Phone
Email
Message
Optional image/file
```

This becomes an enquiry for your sales/admin team.

---

# Product model

I'd therefore avoid simply having:

```text
product.stock = 5
```

I'd introduce a product availability/type concept.

For example:

```text
product_type

AVAILABLE
MADE_TO_ORDER
```

And possibly later:

```text
DISCONTINUED
```

So your frontend can behave differently:

```text
AVAILABLE
   → Add to Cart
   → Buy Now

MADE_TO_ORDER
   → Request This Furniture

DISCONTINUED
   → No purchase
```

This is much cleaner than trying to force everything through a shopping cart.

---

# Checkout

Once somebody buys an available product:

### Step 1 — Cart

```text
Modern Sofa       1 × TZS 1,250,000
Coffee Table      1 × TZS   350,000
-----------------------------------
Subtotal                    1,600,000
```

### Step 2 — Fulfilment

Give them:

**Self Pickup — FREE**

or

**Delivery — TZS X**

For delivery, the fee could eventually be calculated based on:

* Delivery zone
* Distance
* Furniture size
* Number of items

But for the first version, I'd keep this **simple**.

For example:

```text
Pickup
FREE

Delivery
TZS 20,000
```

You can introduce dynamic delivery pricing later.

---

# Step 3 — Payment

Then:

```text
Order total
TZS 1,620,000

[ Pay Now ]
```

I'd architect payments so that your business can later support whatever payment providers you need without changing the order system.

---

# Order tracking

You don't need an enormously complicated logistics system.

For your scale, I'd start with something like:

```text
Order #ORD-10482

✓ Order Placed
✓ Payment Confirmed
✓ Order Accepted
● Preparing
○ Ready for Pickup / Shipped
○ Delivered
```

But I'd make the underlying statuses more precise.

For example:

```text
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

For self-pickup:

```text
PAID
  ↓
ACCEPTED
  ↓
PROCESSING
  ↓
READY_FOR_PICKUP
  ↓
COMPLETED
```

For delivery:

```text
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

You don't necessarily need a GPS-style courier tracking system at this stage.

A simple order timeline is sufficient.

---

# The admin panel becomes very important

Since the business is small, I would make the **admin dashboard the operational center**.

Something like:

```text
Dashboard

Orders
├── New
├── Accepted
├── Processing
├── Ready for Pickup
├── Shipped
├── Delivered
└── Cancelled

Products
├── Available
├── Made to Order
└── Out of Stock

Customer Requests
├── New
├── Contacted
├── Quoted
├── In Production
└── Completed

Enquiries
├── New
├── In Progress
└── Closed

Customers
Payments
Inventory
Settings
```

Then the actual workflow can be very straightforward.

For example, an employee receives an order:

```text
NEW ORDER
    ↓
Review
    ↓
ACCEPT
    ↓
Prepare furniture
    ↓
READY / SHIP
    ↓
COMPLETE
```

---

# Your database can remain fairly small

You don't need 100 tables.

A reasonable initial model might look like:

```text
users
categories
products
product_images
product_variants
inventory

carts
cart_items

orders
order_items
order_addresses
order_status_history

payments
deliveries

furniture_requests
enquiries

notifications
```

And later you can add:

```text
reviews
wishlists
coupons
promotions
```

without redesigning everything.

---

# One particularly important table: `order_status_history`

Don't only store:

```text
orders.status = "SHIPPED"
```

I'd also keep the history:

```text
order_status_history

id
order_id
status
note
created_at
```

So an order could have:

```text
27 Aug 2026  14:02  PAID
27 Aug 2026  14:18  ACCEPTED
27 Aug 2026  16:45  PROCESSING
28 Aug 2026  09:20  SHIPPED
28 Aug 2026  15:10  DELIVERED
```

That gives you the customer's **tracking timeline** essentially for free.

It also helps your staff resolve disputes later.

---

# How the whole technology stack fits together

I'd structure your system like this:

```text
                  ┌─────────────────────┐
                  │       MySQL         │
                  └──────────┬──────────┘
                             │
                     ┌───────▼────────┐
                     │ Laravel API    │
                     │ Authentication │
                     │ Orders         │
                     │ Products       │
                     │ Payments       │
                     │ Inventory      │
                     │ Requests       │
                     └───────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
        Next.js + MUI     Flutter       Admin
          Website           App       Next.js + MUI
```

The really nice part is that **your business rules live in Laravel**, not inside either frontend.

For example:

> Can this product be purchased?

Laravel decides.

> Is this item in stock?

Laravel decides.

> Can this order be shipped?

Laravel decides.

> Is delivery available for this location?

Laravel decides.

That prevents your Flutter app and website from developing different business logic.

---

# I'd also make one terminology change

Instead of thinking about:

> "buyable products" and "non-buyable products"

I'd think in terms of **fulfilment mode**:

```text
IN_STOCK
MADE_TO_ORDER
```

because eventually you may have:

```text
IN_STOCK
MADE_TO_ORDER
PRE_ORDER
OUT_OF_STOCK
DISCONTINUED
```

That gives you room to grow without redesigning your product system.

---

## Overall architecture I'd recommend

For your current business size:

**Website:** Next.js + MUI
**App:** Flutter + Material 3
**Backend:** Laravel API
**Database:** MySQL
**Admin:** Next.js + MUI
**Authentication:** Shared account across app/site
**Product images:** Object storage/CDN
**Payments:** Payment gateway integrated through Laravel
**Orders:** Simple status workflow + history
**Delivery:** Pickup or delivery
**Made-to-order:** Request/quotation workflow
**General enquiries:** Separate enquiry system

And I'd deliberately **not build** complex things such as warehouse management, multi-vendor marketplace features, advanced logistics, or manufacturing ERP functionality yet. Your architecture can accommodate them later, while version 1 stays appropriate for a small furniture business.
