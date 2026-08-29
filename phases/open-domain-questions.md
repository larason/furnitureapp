# Open Domain Questions — Phase 1.2

## 1. Purpose

Only questions that genuinely **block safe domain modeling** are listed here. Implementation-level details (formulas, field types, sequence generation, storage providers) intentionally belong to later phases and are **not** listed.

Each question records the smallest safe assumption so later phases can proceed if the question is not answered in time.

---

## Question 1 — Cart identity: account-only, or guest-with-later-attachment?

**Question:** Is a Cart strictly bound to an authenticated customer, or may a guest accumulate cart items before logging in and have them attached to their account upon authentication?

**Why it blocks domain modeling:** It decides whether `Cart` is owned by `User` unconditionally or whether a guest-owned (anonymous) cart state must exist in the domain. This affects the cart identity boundary and the checkout prerequisite flow.

**Smallest safe assumption:** Cart is an authenticated-customer capability; a guest browsing state has no cart. A customer who logs in starts a fresh cart. If the business later wants guest carts, it can be added without redesigning orders.

**Affected concepts:** Cart, Cart Item, Guest Visitor, Authenticated Customer.

**Blocks:** Phase 1.12 (cart contract), Phase Group F (cart API), Phase 3.8 (cart schema).

**User answer** cart is not strictly bound to authenticated customer

---

## Question 2 — When is the delivery fee set?

**Question:** Is the staff-controlled delivery fee entered at checkout time (before payment, as part of the order) or during fulfilment (after payment)?

**Why it blocks domain modeling:** It decides whether the Delivery Fee belongs to the checkout-time order aggregate (part of the authoritative order total the customer sees before paying) or to the fulfilment phase (applied after payment). This changes what the customer commits to paying and how order totals are finalized.

**Smallest safe assumption:** The delivery fee is included in the backend-authoritative order total at checkout, entered/validated by authorized admin/staff before the customer pays. The customer cannot set it, but the customer sees the confirmed fee before payment.

**Affected concepts:** Delivery Fee, Order, Payment, Checkout.

**Blocks:** Phase 1.13 (checkout contract), Phase Group G (checkout totals), Phase Group H (payments).

**User answer** Staff-controlled delivery fee is entered when adding a furniture product and its details. This later will be divided according to regions and locations. example in Dar es salaam (kinondoni-free, ubungo-Tzs5000, and so on). And its displayed at user checkout upon user input of delivering location and address.

---

## Question 3 — Can a made-to-order request become an order in Version 1?

**Question:** After the business agrees on commercial terms (quotation), is converting a Made-to-order Request into a normal Order within Version 1 scope, or does Version 1 only capture and manage the request as a lead?

**Why it blocks domain modeling:** It decides whether a `Made-to-order Request` needs a domain relationship to a resulting `Order` (and whether that order follows the normal purchase flow or a special quotation flow).

**Smallest safe assumption:** Version 1 captures and manages the request as a lead only. If a quotation leads to a sale, the business creates a normal order through the approved purchase workflow; no automatic request-to-order link is modeled in Version 1.

**Affected concepts:** Made-to-order Request, Order, Staff/Admin follow-up.

**Blocks:** Phase 1.17 (request contract), Phase 10.3 (request status lifecycle), Phase 3.14 (request schema).

**User answer** NO made-to-order will not become an order in version1.
