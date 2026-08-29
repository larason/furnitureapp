# Domain Decisions — Phase 1.2

## 1. Purpose

This document records the decisions made during Phase 1.2 (domain-noun definition). Each decision identifies its **reason**, **affected concepts**, and **later phases affected**.

Phase 1.1 decisions remain authoritative and are not re-opened here. Any interpretation made to proceed with safe domain modeling is recorded explicitly.

---

## Decision 1 — One shared User concept

- **Decision:** Use a single `User` identity concept for customer, staff, and admin personas. Role controls authorization. Do not create separate authentication systems per persona.
- **Reason:** Avoids duplicated identity/auth logic and inconsistent account handling across the three frontends; matches Phase 1.1 and the project architecture.
- **Affected concepts:** User, Customer, Staff, Admin, Authentication.
- **Later phases affected:** Phase 3.1 (Users schema), Phase Group D (authentication/authorization), Phase Group K (admin operations).

## Decision 2 — Product Type controls the customer's primary action

- **Decision:** `IN_STOCK` and `MADE_TO_ORDER` are the only Version 1 product types. Product type determines the primary customer action: buy vs request.
- **Reason:** Separates purchasable items from request-only items cleanly; a `MADE_TO_ORDER` product is never a purchasable inventory item.
- **Affected concepts:** Product, Product Type, Product Availability, Inventory.
- **Later phases affected:** Phase 1.11 (catalog contract), Phase 3.4 (products schema), Phase Group E (catalog API).

## Decision 3 — Variant is the purchasable unit when variants exist

- **Decision:** A Product Variant represents a purchasable variation of a product (color, material, size, configuration) and may carry its own stock and pricing when required.
- **Reason:** Matches the default interpretation specified in Phase 1.2 §7 and avoids forcing every variation to be a standalone product.
- **Affected concepts:** Product Variant, Inventory, Product Availability, Cart Item, Order Item.
- **Later phases affected:** Phase 3.5 (product variants), Phase 3.7 (inventory), Phase Group E (variant/catalog API).

## Decision 4 — Product and Inventory are separate concepts

- **Decision:** Product describes what is sold; Inventory describes how many purchasable units are available. Inventory distinguishes physical, reserved, and available quantities at the domain level.
- **Reason:** Prevents collapsing catalog facts with availability facts; availability must be authoritative and backend-controlled.
- **Affected concepts:** Product, Inventory, Product Availability.
- **Later phases affected:** Phase 3.7 (inventory schema), Phase Group E (inventory API), overselling/concurrency rules.

## Decision 5 — Cart does not guarantee reservation

- **Decision:** Cart contents do not reserve stock. The backend revalidates purchasability and inventory before creating an order.
- **Reason:** Product availability can change between adding and checkout; client cart totals are never authoritative.
- **Affected concepts:** Cart, Cart Item, Inventory, Order.
- **Later phases affected:** Phase Group F (cart API), Phase Group G (checkout).

## Decision 6 — Order preserves order-time snapshots

- **Decision:** An Order must preserve historical purchase facts (product name, identifier/SKU, unit price, quantity, item subtotal) independent of later catalog changes.
- **Reason:** Historical accuracy and operational accountability; catalog edits must not rewrite history.
- **Affected concepts:** Order, Order Item, Product.
- **Later phases affected:** Phase 3.10 (order items snapshot model), Phase Group I (order management).

## Decision 7 — Pickup free; Delivery fee variable and staff-controlled

- **Decision:** `PICKUP` is free. `DELIVERY` carries a fee that is variable and determined/entered by authorized admin/staff; the customer cannot set the final fee.
- **Reason:** Matches Phase 1.1 decision 5; keeps delivery pricing simple and under business control.
- **Affected concepts:** Fulfillment, Pickup, Delivery, Delivery Fee, Order.
- **Later phases affected:** Phase 1.13 (checkout contract), Phase Group G (checkout totals), Phase Group K (admin fulfilment).

## Decision 8 — Addresses are transaction data, not an address book

- **Decision:** Billing and delivery addresses are captured as part of the relevant order/payment transaction. No persistent saved-address book in Version 1.
- **Reason:** Matches Phase 1.1 decision 11; keeps Version 1 minimal.
- **Affected concepts:** Address, Billing Address, Delivery Address.
- **Later phases affected:** Phase 3.9 (orders), Phase 3.13 (delivery), Phase Group G (checkout).

## Decision 9 — Payment is a provider-agnostic boundary

- **Decision:** Model `Payment` as the financial transaction associated with an Order, with payment state distinct from order state. No provider, provider fields, SDK, callbacks, or webhook payloads are designed in this phase.
- **Reason:** Payment provider selection belongs to Phase Group G; the domain must not hard-code provider concepts.
- **Affected concepts:** Payment, Order Status.
- **Later phases affected:** Phase 3.12 (payment schema), Phase Group H (payments).

## Decision 10 — Order status and payment status are distinct state machines

- **Decision:** Order Status is the lifecycle state of an Order (with history); Payment status is a financial fact. The two must never be collapsed.
- **Reason:** Avoiding this confusion prevents implementation errors in fulfilment vs finance.
- **Affected concepts:** Order Status, Order Status History, Payment.
- **Later phases affected:** Phase 3.11 (status history), Phase 3.12 (payment), Phase Group I (order state machine).

## Decision 11 — 20-minute cancellation window is a backend-evaluated order operation

- **Decision:** Customer cancellation is a time-bounded operation evaluated by the backend within 20 minutes of order creation. No refund policy is invented in this phase.
- **Reason:** Matches Phase 1.1 decision 7; cancellation and refund are distinct concerns.
- **Affected concepts:** Cancellation, Cancellation Window, Order, Payment.
- **Later phases affected:** Phase 1.15 (order contract), Phase Group I (cancellation rules), Phase Group H (refund interaction).

## Decision 12 — Order reference uses the `OD-` prefix

- **Decision:** The customer-facing order reference format is `OD-*****`. Suffix format, length, and generation strategy are not decided here.
- **Reason:** Matches Phase 1.1 decision 8; generation details are an implementation concern.
- **Affected concepts:** Order Number / Reference, Order.
- **Later phases affected:** Phase 1.15 (order contract), Phase Group I (order creation).

## Decision 13 — Notifications are event records only

- **Decision:** Notification is defined as a communication/alert associated with an application event. Real email/push/SMS delivery is deferred to Phase Group R.
- **Reason:** Matches Phase 1.1 decision 9; avoids coupling commerce to external delivery infrastructure.
- **Affected concepts:** Notification.
- **Later phases affected:** Phase 3.16 (notification schema), Phase Group R (notifications).

## Decision 14 — Attachments are optional and belong to requests/enquiries

- **Decision:** An Attachment is an optional file associated with a Made-to-order Request or a General Enquiry. Storage technology and cardinality are deferred.
- **Reason:** Matches Phase 1.1 decision 13; keeps the domain relationship without choosing infrastructure.
- **Affected concepts:** Attachment, Made-to-order Request, General Enquiry.
- **Later phases affected:** Phase 3.14/3.15 (request/enquiry schema), Phase 10.6 (attachment handling).

## Decision 15 — No staff/admin permission design in this phase

- **Decision:** Only the high-level staff/admin distinction is recorded. The permission matrix is deferred to Phase Group D.
- **Reason:** Matches Phase 1.1 decision 12; authorization is backend-enforced but not yet granular.
- **Affected concepts:** Staff, Admin, User.
- **Later phases affected:** Phase Group D (roles/permissions), Phase Group K (admin operations).

## Decision 16 — Single currency (TZS) assumed for Version 1

- **Decision:** Version 1 uses a single currency (TZS per the project vision). Money is still represented safely (no unreliable floating-point financial math); the exact representation is a data-model decision.
- **Reason:** The business operates in one market; a single-currency assumption keeps financial calculations unambiguous.
- **Affected concepts:** Currency, Delivery Fee, Order totals.
- **Later phases affected:** Phase 3.9/3.12 (order/payment schema), Phase Group G (authoritative totals).

## Decision 17 — Concepts that must never be confused are documented guardrails

- **Decision:** The following distinctions are recorded as domain guardrails and must be preserved by later phases:

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

- **Reason:** These are the most common sources of implementation errors; explicit separation prevents silent collapse of concepts.
- **Affected concepts:** All listed concepts.
- **Later phases affected:** All data-model, API, and frontend phases.

---

## Decision 18 — Cart is not strictly bound to an authenticated customer

- **Decision:** A guest may create, view, and modify a cart without an account. A guest cart becomes associated with the customer's account upon authentication. Checkout still requires an authenticated customer account.
- **Reason:** Owner answer to Phase 1.2 open question 1; preserves a cart across login without requiring account creation to begin shopping.
- **Note:** This is the single Version 1 rule for cart creation, viewing, and modification: guests and authenticated customers both have cart access (create/view/modify); authentication is the gate only at checkout. All Phase 1.2 documents use this same rule.
- **Affected concepts:** Cart, Cart Item, Guest Visitor, Authenticated Customer, Checkout.
- **Later phases affected:** Phase 1.12 (cart contract), Phase Group F (cart API), Phase 3.8 (cart schema). These must define guest-cart identity and the attach/merge behavior at login.

## Decision 19 — Delivery fees are staff-managed and keyed by delivery region/location, resolved at checkout

- **Decision:** Delivery fees are staff-controlled and entered as part of product/delivery configuration. Fees are differentiated by delivery region and location (e.g., Dar es Salaam — Kinondoni: free; Ubungo: TZS 5,000). The fee applicable to an order is resolved from the customer's delivery location/address at checkout and displayed to the customer before payment; the customer cannot set the fee.
- **Reason:** Owner answer to Phase 1.2 open question 2. Keeps delivery pricing under business control while supporting location-based pricing and a clear checkout-time display.
- **Affected concepts:** Delivery Fee, Delivery Fee Rule, Delivery Region/Delivery Location, Delivery, Order (totals), Checkout.
- **Later phases affected:** Phase 1.13 (checkout contract), Phase Group G (checkout totals), Phase 3.13 (delivery schema), Phase Group K (admin delivery management). Exact scoping (per-product vs global fee rules) and geographic tiering representation are deferred implementation details for those phases.

## Decision 20 — Made-to-order requests are leads only in Version 1

- **Decision:** A made-to-order request never becomes an order in Version 1. Requests are captured and managed as leads; an order is created only through the normal purchase workflow.
- **Reason:** Owner answer to Phase 1.2 open question 3, consistent with Phase 1.1 decisions 3 and 14.
- **Affected concepts:** Made-to-order Request, Order, Staff/Admin follow-up.
- **Later phases affected:** Phase 1.17 (request contract), Phase 10.3 (request status lifecycle), Phase 3.14 (request schema).