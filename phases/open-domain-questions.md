# Resolved Domain Questions — Phase 1.2

## 1. Purpose

All Phase 1.2 domain questions have been **answered by the project owner**. This document records each question, the answer, and its impact so later phases can rely on the resolution.

No blocking domain questions remain for Phase 1.2.

---

## Question 1 — Cart identity: account-only, or guest-with-later-attachment?

**Question:** Is a Cart strictly bound to an authenticated customer, or may a guest accumulate cart items before logging in and have them attached to their account upon authentication?

**Why it blocked domain modeling:** It decides whether `Cart` is owned by `User` unconditionally or whether a guest-owned (anonymous) cart state must exist in the domain. This affects the cart identity boundary and the checkout prerequisite flow.

**RESOLVED — User answer:** "Cart is not strictly bound to authenticated customer."

**Domain impact:** A guest may create, view, and modify a cart without an account. A guest cart becomes associated with the customer's account upon authentication. Checkout still requires an authenticated customer account.

**Affected concepts:** Cart, Cart Item, Guest Visitor, Authenticated Customer.

**Blocks:** Phase 1.12 (cart contract), Phase Group F (cart API), Phase 3.8 (cart schema). These must define guest-cart identity and the attach/merge behavior at login (implementation detail, not a domain boundary).

---

## Question 2 — When is the delivery fee set?

**Question:** Is the staff-controlled delivery fee entered at checkout time (before payment, as part of the order) or during fulfilment (after payment)?

**Why it blocked domain modeling:** It decides whether the Delivery Fee belongs to the checkout-time order aggregate (part of the authoritative order total the customer sees before paying) or to the fulfilment phase (applied after payment). This changes what the customer commits to paying and how order totals are finalized.

**RESOLVED — User answer:** "Staff-controlled delivery fee is entered when adding a furniture product and its details. This later will be divided according to regions and locations. Example in Dar es Salaam (Kinondoni — free, Ubungo — TZS 5,000, and so on). And it is displayed at user checkout upon user input of delivering location and address."

**Domain impact:** Delivery fees are staff-managed pricing configuration differentiated by delivery region/location. The fee applicable to an order is resolved and displayed at checkout once the customer inputs their delivery location and address. The fee belongs to the checkout-time authoritative order total; the customer cannot set it. Introduction of a Delivery Fee Rule and Delivery Region/Delivery Location concept.

**Affected concepts:** Delivery Fee, Delivery Fee Rule, Delivery Region/Delivery Location, Delivery, Order (totals), Checkout.

**Blocks:** Phase 1.13 (checkout contract), Phase Group G (checkout totals), Phase Group H (payments), Phase 3.13 (delivery schema). Exact fee-rule scoping (per-product vs global) and geographic tiering representation are deferred implementation details for those phases.

---

## Question 3 — Can a made-to-order request become an order in Version 1?

**Question:** After the business agrees on commercial terms (quotation), is converting a Made-to-order Request into a normal Order within Version 1 scope, or does Version 1 only capture and manage the request as a lead?

**Why it blocked domain modeling:** It decides whether a `Made-to-order Request` needs a domain relationship to a resulting `Order` (and whether that order follows the normal purchase flow or a special quotation flow).

**RESOLVED — User answer:** "NO, made-to-order will not become an order in version 1."

**Domain impact:** A made-to-order request is captured and managed as a lead only. No request-to-order relationship is modeled in Version 1; orders are created only through the normal purchase workflow.

**Affected concepts:** Made-to-order Request, Order, Staff/Admin follow-up.

**Blocks:** Phase 1.17 (request contract), Phase 10.3 (request status lifecycle), Phase 3.14 (request schema).