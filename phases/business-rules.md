# Business Rules — Version 1

## About This Document

These are the business rules in **plain language** for product owners and staff. No technical terms are required to understand them. Where a rule has a technical reference, the invariant ID is shown in brackets (see `domain-invariants.md`).

The overall promise of the platform: **the computer system is always the final authority on what is allowed, what costs what, and what is in stock.** The website and mobile app help customers shop, but they can never override the system's decisions.

---

## 1. Browsing the Catalog

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can open the website or app and browse the catalog, look at categories, search for furniture, open product pages, view images, prices, and availability **without logging in**. | IDENT-001/003 |
| 2 | Browsing, searching, and viewing products must never secretly require an account. | IDENT-003 |
| 3 | Every product is either **In Stock** (buy it now) or **Made to Order** (request it). | CAT-002 |
| 4 | For "In Stock" products, the customer's main action is **Add to Cart / Buy**. | CAT-003 |
| 5 | For "Made to Order" products, the customer's main action is **Request This Furniture** — you cannot buy it directly at checkout. | CAT-003 |
| 6 | Products that are no longer active can no longer be purchased. | CAT-004 |

## 2. Stock and Availability

| # | Business rule | Ref |
|---|---|---|
| 1 | The stock number shown to customers is informative only. The system makes the real, final stock check. | INV-001 |
| 2 | Available stock can never go below zero. | INV-002 |
| 3 | If two customers try to buy the last item at the same time, only one can succeed. The system never sells more than it has. | INV-003 |
| 4 | Adding something to a cart does **not** put it aside or reserve it for you. | INV-004 |
| 5 | An item may become unavailable after a customer has looked at it; the final decision is made when they check out. | INV-005 |
| 6 | The system tracks stock as units held, units reserved, and units available to sell. | INV-006 |
| 7 | If a product has color/size/material options, stock can be tracked separately for each option. | INV-007 |

## 3. Accounts and Privacy

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can browse and search without an account. | IDENT-001 |
| 2 | **Checkout requires a customer account.** A visitor cannot buy without logging in. | IDENT-002 |
| 3 | A customer sees only their own profile, orders, requests, and payment information — never another customer's. | IDENT-006 |
| 4 | A visitor can create a cart without an account, and the cart moves onto their account when they log in. Checkout still requires being logged in. | IDENT-008 |
| 5 | Customers, staff, and admins all use the same account system; a person's role decides what they may do. | IDENT-007 |

## 4. Cart and Checkout

| # | Business rule | Ref |
|---|---|---|
| 1 | A cart can only contain real, purchasable items. | CART-001 |
| 2 | At checkout, the system re-checks everything: the item still exists, is active, is buyable, the chosen option is valid, the price is current, and the stock is enough. | CART-004 |
| 3 | The system, not the customer, works out the final to-pay amount. Nobody can type in their own total. | CART-005, PRICE-001/002 |
| 4 | Customers may not choose "Delivery" without giving a valid delivery address and contact details. | FUL-003, ADDR-004 |
| 5 | A cart that is no longer valid cannot become an order. | CHECKOUT-005 |

## 5. Prices and Payment

| # | Business rule | Ref |
|---|---|---|
| 1 | Prices and totals are always decided by the system, never the customer. | PRICE-001/002 |
| 2 | All prices are in Tanzanian Shillings (TZS). | PRICE-003 |
| 3 | Money is always handled carefully by the system — financial amounts are never calculated with the kind of rounding that loses money. | PRICE-004 |
| 4 | **Self pickup is free.** | PRICE-005 |
| 5 | **Delivery carries a location-based fee, which may be zero for some locations.** The delivery charge is set by staff and depends on the delivery location (for example, in Dar es Salaam: Kinondoni free, Ubungo TZS 5,000). The customer sees the delivery charge at checkout after entering their location and address, but cannot change it. | PRICE-006 |
| 6 | A customer is never treated as paid just because they say they paid. The system verifies payment itself. | PAY-002 |

## 6. Orders and Tracking

| # | Business rule | Ref |
|---|---|---|
| 1 | An order is created only through a completed checkout of a real cart. | ORD-001 |
| 2 | Only a logged-in customer can create an order. | ORD-002 |
| 3 | An order keeps a permanent snapshot of what was bought: product name, item reference, unit price, quantity, and line total — even if the catalog changes later. | ORD-005 |
| 4 | Orders have a customer-friendly reference number starting with **OD-**. | ORD-006 |
| 5 | Order status can only move along approved steps; neither the customer nor the app can force a jump. | ORD-007 |
| 6 | Every status change is recorded with the time (and who/what caused it), so staff and customers can see the full story. | ORD-008 |
| 7 | Customers can follow their order through milestones (Paid, Accepted, Processing, Ready for Pickup / Shipped, Delivered, Completed). Only milestones that apply to their delivery choice are shown. | FUL-005/006 |

## 7. Order Status Paths

**Pickup orders** follow: Placed (payment pending) → Paid → Accepted → Processing → Ready for Pickup → Completed.

**Delivery orders** follow: Placed (payment pending) → Paid → Accepted → Processing → Shipped → Delivered → Completed.

| # | Business rule | Ref |
|---|---|---|
| 1 | A pickup order never shows "Shipped". | FUL-005 |
| 2 | A delivery order must not skip the required steps. | ORD-007 |

## 8. Cancellation

| # | Business rule | Ref |
|---|---|---|
| 1 | A customer may cancel their order **within 20 minutes** of placing it. | CANCEL-001 |
| 2 | The system itself checks the time; the customer cannot trick it by claiming a different time. | CANCEL-002 |
| 3 | Cancelling an order does not automatically mean a refund will happen. Refunds are a separate decision made later. | CANCEL-003 |
| 4 | Staff/admin cancellation is a different process and is not bound by the 20-minute rule. Its conditions come later. | CANCEL-004 |

## 9. Made-to-Order Requests

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can request a "Made to Order" item — no account needed, as long as they leave contact information (email and/or phone). | REQ-001 |
| 2 | A request can mention an existing product, but does not have to. | REQ-002 |
| 3 | A request can include quantity, dimensions, preferred material/color, and notes, plus an optional picture/file. | REQ-003, ATTACH-001 |
| 4 | **A request is never an order.** It does not create an order, a payment, or reserve any stock. | REQ-004 |
| 5 | In Version 1, requests are leads for the business to follow up. A request cannot become an order. | REQ-005 |
| 6 | No final price (quotation) is assumed at the time of the request. | REQ-006 |

## 10. General Enquiries

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can send a general enquiry — no account needed, as long as they give contact information. | ENQ-001 |
| 2 | An enquiry must include contact details and a message. | ENQ-002 |
| 3 | An enquiry is communication, not an order. It never creates an order or payment. | ENQ-003 |
| 4 | Enquiries and made-to-order requests are two different things and are never merged into one. | ENQ-004 |

## 11. Addresses and Files

| # | Business rule | Ref |
|---|---|---|
| 1 | The system does not keep a saved address book in Version 1. Addresses are entered for each order. | ADDR-001/002 |
| 2 | The billing address and the delivery address are treated as separate things even when they are the same. | ADDR-003 |
| 3 | Files attached to requests/enquiries are optional. The system will enforce safe file rules (type and size) when implemented. | ATTACH-001/003 |

## 12. Accounts, Roles and Staff

| # | Business rule | Ref |
|---|---|---|
| 1 | There are three roles: **Customer**, **Staff**, and **Admin**. | AUTHZ-001 |
| 2 | What each role can do is defined later; the one firm rule now is that every sensitive action must be protected and only allowed for people who are allowed to do it. | AUTHZ-002 |
| 3 | Stock, prices, delivery charges, cancellation timing, and payment confirmation are decided by the system, never by what a customer's screen claims. | SEC-001 |
| 4 | Customer-visible totals and order status cannot be changed from the app or website. | AUTHZ-004 |

## 13. Notifications and Emails

| # | Business rule | Ref |
|---|---|---|
| 1 | The system may keep notification records tied to events such as an order changing status. | NOTIF-001 |
| 2 | Real email/SMS/push delivery is a later addition; Version 1 does not depend on sending emails. | NOTIF-002/003 |

---

## Quick Non-Negotiables (for staff)

- Customers **can** browse, search, request, and enquire without an account.
- Customers **cannot** check out without an account.
- Customers **cannot** set their own total or delivery charge.
- The system **never** sells more stock than it has.
- Pickup is free; delivery charges are set by the business by location.
- Made-to-order requests are leads, **not** orders.
- Order status only moves along approved steps.
- Customers can cancel within 20 minutes of ordering.
- "Cancelled" does not automatically mean refunded.