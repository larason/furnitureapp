# Domain Invariants — Version 1

## 1. Purpose

This document defines the **testable invariants** that must remain true regardless of which frontend, admin screen, API consumer, or future application performs an operation.

- An **invariant** is a condition that must hold unconditionally.
- A **business rule** (see `business-rules.md`) describes what the system allows, prevents, calculates, or transitions.
- Each invariant below has a **stable ID** so future API, database, Laravel, and automated-test documents can reference it.

This is a **business/domain specification**, not implementation code. It does not create database tables, Laravel models/controllers, API endpoints, OpenAPI schemas, or any frontend code.

## 2. Governing Principle — Backend Authority

Every rule in this document must ultimately be enforceable by the backend. A rule is **not** satisfied merely because it is enforced in Next.js, Flutter, browser JavaScript, the admin UI, client-side validation, or cached state.

The backend is authoritative for: authentication requirements, authorization, product purchasability, inventory, prices, delivery fee, checkout, order totals, cancellation, order transitions, payment state, and request/enquiry ownership.

**Rule of thumb:** if the backend disagrees with a client (e.g., client says "Stock = 2", backend says "Stock = 0"), the backend's answer wins and the purchase must fail.

---

# Identity

## IDENT-001 — Public browsing requires no authentication

- **Rule:** A visitor without an account may browse the homepage, categories, product listings, product search/filter, product detail, images, prices, and availability. Public catalog reads must not become accidental login dependencies.
- **Reason:** Anonymous discovery is a first-class Version 1 capability (Phase 1.1 decision 1).
- **Implementation:** Later backend; public read endpoints must require no session/access token/account.
- **Testing:** See `testable-invariants.md` → IDENT-001.

## IDENT-002 — Checkout requires an authenticated customer account

- **Rule:** Version 1 checkout is available only to an authenticated customer. A visitor attempting to complete checkout anonymously must be rejected by the backend.
- **Reason:** Phase 1.1 decision 2; commerce requires an identified customer.
- **Implementation:** Later backend authorization on checkout operations.
- **Testing:** See `testable-invariants.md` → IDENT-002.

## IDENT-003 — Authentication and public discovery are separate

- **Rule:** Product/category reads are `PUBLIC_ANONYMOUS`. They must not be marked authenticated merely because carts or orders require accounts.
- **Reason:** Prevents anonymous browsing from becoming account-gated.
- **Implementation:** Later backend routing/authorization.
- **Testing:** See `testable-invariants.md` → IDENT-003.

## IDENT-004 — Anonymous made-to-order requests are allowed

- **Rule:** A made-to-order request may be submitted without an account. Anonymous requests must collect sufficient contact details (email and/or phone).
- **Reason:** Phase 1.1 decision 3.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → REQ-001.

## IDENT-005 — Anonymous general enquiries are allowed

- **Rule:** A general enquiry may be submitted without an account. It must contain sufficient contact information for follow-up.
- **Reason:** Phase 1.1 decision 4.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → ENQ-001.

## IDENT-006 — Customer data privacy

- **Rule:** An authenticated customer may access only their own profile, orders, private requests, private enquiries, private addresses, and private payment-related information. A customer must not access another customer's data.
- **Reason:** Privacy and authorization baseline.
- **Implementation:** Later backend ownership/authorization checks; frontend route guards are never the final authority.
- **Testing:** See `testable-invariants.md` → IDENT-006.

## IDENT-007 — One shared User identity for all personas

- **Rule:** Customer, staff, and admin are roles on a single system User identity. Do not create separate, incompatible identity systems per persona.
- **Reason:** Phase 1.1/Phase 1.2 decision 1.
- **Implementation:** Later user schema + roles.
- **Testing:** See `testable-invariants.md` → IDENT-007.

## IDENT-008 — Guest carts have an identity

- **Rule:** A guest (unauthenticated visitor) may create, view, and modify a cart without an account; the guest cart is associated with the customer's account upon authentication. Checkout still requires an authenticated account.
- **Reason:** Phase 1.2 decision 18 (owner answer to open question 1).
- **Implementation:** Later cart identity scheme; attach/merge behavior at login.
- **Testing:** See `testable-invariants.md` → IDENT-008.

---

# Catalog

## CAT-001 — Product business identity

- **Rule:** Each catalog product must have an unambiguous business identity.
- **Reason:** Products are referenced by carts, orders, requests, and admin operations.
- **Implementation:** Later data model decides physical uniqueness enforcement.
- **Testing:** See `testable-invariants.md` → CAT-001.

## CAT-002 — Product type is exactly IN_STOCK or MADE_TO_ORDER

- **Rule:** Version 1 supports exactly two product types: `IN_STOCK` (purchasable through normal checkout when active, purchasable, and sufficiently stocked) and `MADE_TO_ORDER` (requestable for manufacture, not directly purchasable through normal checkout).
- **Reason:** Product type is the business classifier that drives the customer's primary action.
- **Implementation:** Later data model + backend validation.
- **Testing:** See `testable-invariants.md` → CAT-002.

## CAT-003 — Product type controls the customer's primary action

- **Rule:** `IN_STOCK` → Add to Cart / Buy → Checkout. `MADE_TO_ORDER` → Request This Furniture. A `MADE_TO_ORDER` product must not be processed through an ordinary checkout.
- **Reason:** Prevents the wrong workflow for request-only items.
- **Implementation:** Later backend purchasability check.
- **Testing:** See `testable-invariants.md` → CAT-003.

## CAT-004 — Inactive products cannot be newly purchased

- **Rule:** A product that is not active cannot be newly purchased (though historical orders remain valid).
- **Reason:** Prevents purchase of withdrawn items.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → CAT-004.

## CAT-005 — Category is a grouping, not a product

- **Rule:** A category organizes products; it is not itself a purchasable item.
- **Reason:** Preserves the Catalog domain boundary (Category ≠ Product).
- **Implementation:** Later data model.
- **Testing:** See `testable-invariants.md` → CAT-005.

## CAT-006 — Catalog concepts must never collapse

- **Rule:** Product ≠ Inventory, Product ≠ Order Item, Product ≠ Made-to-order Request, Product availability ≠ Payment state.
- **Reason:** Phase 1.2 decision 17 guardrails; prevents implementation errors.
- **Implementation:** Later data model + API contract.
- **Testing:** See `testable-invariants.md` → CAT-006.

---

# Inventory

## INV-001 — Inventory is backend authority

- **Rule:** Inventory displayed in the website or app is informational only. The backend performs the final inventory check.
- **Reason:** Phase 1.1 inventory rules; a UI "In Stock" badge does not guarantee availability.
- **Implementation:** Later backend inventory service.
- **Testing:** See `testable-invariants.md` → INV-001.

## INV-002 — No negative available stock

- **Rule:** The system must never permit available inventory to fall below zero.
- **Reason:** Prevents impossible inventory state and overselling.
- **Implementation:** Later backend/data layer with transactional/concurrency controls.
- **Testing:** See `testable-invariants.md` → INV-002.

## INV-003 — No overselling under concurrency

- **Rule:** Two or more customers attempting to purchase the same remaining units concurrently must not cause the system to sell more units than exist.
- **Reason:** Example: 3 available; A requests 2 and B requests 2 → the system must not allocate 2+2.
- **Implementation:** Later transactional/concurrency controls (e.g., atomic stock check + decrement).
- **Testing:** See `testable-invariants.md` → INV-003.

## INV-004 — Cart is not a permanent reservation

- **Rule:** Adding an item to a cart does not reserve stock. The backend re-checks stock when the customer proceeds through checkout.
- **Reason:** Phase 1.2 decision 5; Cart ≠ Inventory reservation.
- **Implementation:** Later checkout logic.
- **Testing:** See `testable-invariants.md` → INV-004.

## INV-005 — Displayed stock is not guaranteed purchasability

- **Rule:** A product may become unavailable after being displayed. Displayed stock ≠ guaranteed purchasability; the final decision happens server-side.
- **Reason:** Phase 1.2 / stale-inventory rule.
- **Implementation:** Later checkout validation.
- **Testing:** See `testable-invariants.md` → INV-005.

## INV-006 — Inventory distinguishes physical, reserved, available

- **Rule:** The system conceptually distinguishes physical quantity, reserved quantity, and available quantity, with available quantity as the purchasable figure.
- **Reason:** Phase 1.2 decision 4; drives availability signals and overselling protection.
- **Implementation:** Later data model formalizes exact representation.
- **Testing:** See `testable-invariants.md` → INV-006.

## INV-007 — Inventory may be tracked at variant level

- **Rule:** Inventory may be maintained at product-variant level where variants exist.
- **Reason:** Phase 1.2 decision 3 (variant is the purchasable unit when variants exist).
- **Implementation:** Later data model.
- **Testing:** See `testable-invariants.md` → INV-007.

---

# Variants

## VAR-001 — Variant belongs to its product

- **Rule:** A variant belongs to exactly one product and is valid only for that product.
- **Reason:** Prevents selecting a variant belonging to Product B when purchasing Product A.
- **Implementation:** Later data model + backend validation.
- **Testing:** See `testable-invariants.md` → VAR-001.

## VAR-002 — Invalid variant combinations are rejected

- **Rule:** The system must reject a purchase/cart attempt that pairs a product with a variant belonging to another product.
- **Reason:** Data integrity and core variant rule.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → VAR-002.

## VAR-003 — Inactive variants cannot be purchased

- **Rule:** An inactive variant cannot be purchased.
- **Reason:** Prevents purchase of discontinued variations.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → VAR-003.

## VAR-004 — Backend checks the selected variant

- **Rule:** The backend must verify the selected variant at cart/checkout time; it is never trusted from client input alone.
- **Reason:** Variant selection is a server-side business decision.
- **Implementation:** Later backend validation.
- **Testing:** See `testable-invariants.md` → VAR-004.

## VAR-005 — Variant may carry its own stock and price

- **Rule:** When variants exist, the variant is the purchasable unit and may have its own stock and pricing where required.
- **Reason:** Phase 1.2 decision 3.
- **Implementation:** Later data model + pricing/inventory logic.
- **Testing:** See `testable-invariants.md` → VAR-005.

---

# Cart

## CART-001 — Cart item must reference a valid, purchasable product/variant

- **Rule:** A cart item must reference a currently existing, active, purchasable product/variant at add/modify time and again at checkout revalidation. A previously valid line that becomes stale due to catalog or inventory changes may remain in the stored cart until checkout, where revalidation (CART-004) rejects it.
- **Reason:** Carts must never allow checkout of invalid or un-purchasable lines, while avoiding immediate purge of stale lines.
- **Implementation:** Backend validation on add/modify and on checkout revalidation; no background cart pruning required.
- **Testing:** See `testable-invariants.md` → CART-001, CART-004.

## CART-002 — Guest carts are not account-bound

- **Rule:** A cart is not strictly bound to an authenticated customer. Guests may create/view/modify a cart; the cart is associated with the customer's account upon authentication.
- **Reason:** Phase 1.2 decision 18.
- **Implementation:** Later cart identity + attach/merge at login.
- **Testing:** See `testable-invariants.md` → CART-002.

## CART-003 — Cart is not an order

- **Rule:** A cart is tentative; an order is confirmed and enters the checkout/order workflow. Cart contents do not guarantee inventory reservation or pricing.
- **Reason:** Cart ≠ Order guardrail (Phase 1.2 decision 17).
- **Implementation:** Later cart/order model separation.
- **Testing:** See `testable-invariants.md` → CART-003.

## CART-004 — Revalidate cart before checkout

- **Rule:** At checkout, the backend must revalidate at minimum: product exists, product active, product type purchasable, variant valid, current price, current inventory, quantity, fulfillment selection, and required address/contact information.
- **Reason:** Availability, price, and product state may change between adding and checkout.
- **Implementation:** Later checkout flow.
- **Testing:** See `testable-invariants.md` → CART-004.

## CART-005 — Frontend cannot set the final total

- **Rule:** The frontend may display totals for UX, but the backend computes the authoritative order total. A client-supplied total must not be accepted.
- **Reason:** Money is backend-authoritative (Phase 1.1).
- **Implementation:** Later checkout/order service ignores client totals.
- **Testing:** See `testable-invariants.md` → CART-005.

---

# Pricing

## PRICE-001 — Backend is authoritative for all monetary values

- **Rule:** The backend is authoritative for product price, variant price, subtotal, delivery fee, and final total (and discounts when later introduced).
- **Reason:** Money calculations must not rely on client data.
- **Implementation:** Later pricing service; server-side calculation.
- **Testing:** See `testable-invariants.md` → PRICE-001.

## PRICE-002 — Client requests, backend prices

- **Rule:** A client may request a product and quantity, but may not dictate the final payable amount.
- **Reason:** Prevents price manipulation.
- **Implementation:** Later backend-only pricing.
- **Testing:** See `testable-invariants.md` → PRICE-002.

## PRICE-003 — Single currency (TZS) assumed

- **Rule:** Version 1 assumes a single currency (TZS per the project vision).
- **Reason:** Phase 1.2 decision 16, keeps financial math unambiguous.
- **Implementation:** Later data model.
- **Testing:** See `testable-invariants.md` → PRICE-003.

## PRICE-004 — Safe money representation

- **Rule:** Financial amounts must be represented safely; unreliable floating-point arithmetic must not be used for financial calculations.
- **Reason:** Phase 1.1 money-safety rule.
- **Implementation:** Later backend/data layer decision.
- **Testing:** See `testable-invariants.md` → PRICE-004.

## PRICE-005 — Pickup delivery fee is zero

- **Rule:** Pickup fulfillment carries a delivery fee of zero.
- **Reason:** Phase 1.1/Pickup is free.
- **Implementation:** Later checkout totals.
- **Testing:** See `testable-invariants.md` → PRICE-005.

## PRICE-006 — Delivery fee is staff-managed, resolved at checkout

- **Rule:** The delivery fee is variable and staff-controlled; it is differentiated by delivery region/location (e.g., Dar es Salaam — Kinondoni: free; Ubungo: TZS 5,000). The fee applicable to an order is resolved from the customer's delivery location/address at checkout and displayed before payment. The customer cannot set the fee.
- **Reason:** Phase 1.2 decision 19 (owner answer to open question 2).
- **Implementation:** Later delivery-fee configuration + checkout resolution; exact per-product vs global scoping and geographic tiering representation are later implementation details.
- **Testing:** See `testable-invariants.md` → PRICE-006.

---

# Checkout

## CHECKOUT-001 — Checkout requires authentication

- **Rule:** Only an authenticated customer may complete Version 1 checkout and create an order. Anonymous checkout must be rejected by the backend.
- **Reason:** Phase 1.1 decision 2; IDENT-002.
- **Implementation:** Later backend authorization.
- **Testing:** See `testable-invariants.md` → CHECKOUT-001.

## CHECKOUT-002 — Full revalidation at checkout

- **Rule:** Checkout revalidates product state, variant, price, inventory, quantity, fulfillment choice, address/contact requirements (see CART-004).
- **Reason:** Prevents stale carts from producing invalid orders.
- **Implementation:** Later checkout service.
- **Testing:** See `testable-invariants.md` → CHECKOUT-002.

## CHECKOUT-003 — Backend computes authoritative order total

- **Rule:** Subtotal, delivery fee, and total are computed by the backend. A client-supplied total is not accepted.
- **Reason:** Money authority (PRICE-001/002, CART-005).
- **Implementation:** Later checkout service.
- **Testing:** See `testable-invariants.md` → CHECKOUT-003.

## CHECKOUT-004 — Fulfillment selection is validated

- **Rule:** The customer selects exactly one of `PICKUP` or `DELIVERY`. Invalid selections and invalid fulfillment data (e.g., missing delivery address for delivery) are rejected.
- **Reason:** Fulfillment is a checkout-controlled decision.
- **Implementation:** Later checkout validation.
- **Testing:** See `testable-invariants.md` → CHECKOUT-004.

## CHECKOUT-005 — Invalid cart cannot create an order

- **Rule:** If any cart item is invalid, un-purchasable, or insufficiently stocked, the checkout must not create an order.
- **Reason:** Order integrity.
- **Implementation:** Later checkout + inventory reservation.
- **Testing:** See `testable-invariants.md` → CHECKOUT-005.

## CHECKOUT-006 — Checkout may need atomicity

- **Rule:** Operations that create an order and interact with inventory/payment must be designed so that failure between steps cannot corrupt business state.
- **Reason:** DATA-004; multi-step commerce operations.
- **Implementation:** Later transaction boundaries.
- **Testing:** See `testable-invariants.md` → CHECKOUT-006.

---

# Fulfillment

## FUL-001 — Exactly two fulfillment modes

- **Rule:** Version 1 supports exactly `PICKUP` and `DELIVERY`.
- **Reason:** Phase 1.1 fulfillment scope.
- **Implementation:** Later backend model.
- **Testing:** See `testable-invariants.md` → FUL-001.

## FUL-002 — Pickup is free and proceeds to Ready for Pickup

- **Rule:** Pickup is free; a pickup order proceeds toward `READY_FOR_PICKUP`.
- **Reason:** Phase 1.1 / 1.2 decision 7.
- **Implementation:** Later order state machine.
- **Testing:** See `testable-invariants.md` → FUL-002.

## FUL-003 — Delivery requires valid delivery information and a staff-controlled fee

- **Rule:** Delivery requires valid delivery contact/address information. The delivery fee is variable, staff-controlled, and cannot be set by the customer.
- **Reason:** Phase 1.2 decision 19.
- **Implementation:** Later checkout + fulfilment.
- **Testing:** See `testable-invariants.md` → FUL-003.

## FUL-004 — No automatic distance pricing in Version 1

- **Rule:** Version 1 does not assume GPS distance pricing, zone algorithms, route calculation, or driver optimization. Delivery fees come from staff-managed region/location rules.
- **Reason:** Phase 1.2 decision 19 / Phase 1.1 scope.
- **Implementation:** Later delivery-fee configuration (staff-managed).
- **Testing:** See `testable-invariants.md` → FUL-004.

## FUL-005 — Pickup orders never show shipped

- **Rule:** A pickup order follows the pickup lifecycle and must never enter `SHIPPED`.
- **Reason:** Tracking correctness (Phase 1.3 §18).
- **Implementation:** Later order state machine.
- **Testing:** See `testable-invariants.md` → FUL-005.

## FUL-006 — Only applicable milestones are shown

- **Rule:** The customer tracking view shows only the milestones applicable to the selected fulfillment method.
- **Reason:** No misleading tracking.
- **Implementation:** Later tracking API/UI.
- **Testing:** See `testable-invariants.md` → FUL-006.

---

# Orders

## ORD-001 — Orders are created only through the purchase flow

- **Rule:** An order is created only through checkout of a validated cart. It is not a product, cart, made-to-order request, general enquiry, or payment.
- **Reason:** Order domain boundary (Phase 1.2 decision 17).
- **Implementation:** Later order service.
- **Testing:** See `testable-invariants.md` → ORD-001.

## ORD-002 — Order creation requires an authenticated customer

- **Rule:** Only an authenticated customer may complete the purchase flow and create an order.
- **Reason:** IDENT-002 / CHECKOUT-001.
- **Implementation:** Later backend authorization.
- **Testing:** See `testable-invariants.md` → ORD-002.

## ORD-003 — Made-to-order request does not create an order

- **Rule:** Submitting "Request This Furniture" must not automatically create an order, payment, or stock reservation.
- **Reason:** Phase 1.1/1.2 decisions 14, 20; Request ≠ Order.
- **Implementation:** Later request service; no order linkage in V1.
- **Testing:** See `testable-invariants.md` → ORD-003.

## ORD-004 — Enquiry does not create an order

- **Rule:** A general enquiry is communication, not a commerce transaction; it never creates an order.
- **Reason:** Enquiry ≠ Order (Phase 1.2 decision 17).
- **Implementation:** Later enquiry service.
- **Testing:** See `testable-invariants.md` → ORD-004.

## ORD-005 — Historical order facts are preserved

- **Rule:** An order must retain order-time facts (product name at purchase, SKU/reference at purchase, unit price, quantity, item subtotal) even if the catalog product later changes.
- **Reason:** Phase 1.2 decision 6; historical integrity.
- **Implementation:** Later order-item snapshot model.
- **Testing:** See `testable-invariants.md` → ORD-005.

## ORD-006 — Order reference uses the OD- prefix

- **Rule:** Customer-facing order references use the `OD-*****` format. Each reference is globally unique across all orders (system-wide uniqueness scope); the system must never persist a duplicate reference and must retry generation on collision or concurrent creation.
- **Reason:** Phase 1.1 decision 8; suffix/generation details are deferred, but uniqueness scope and collision handling are required for testability.
- **Implementation:** Later order-contract phase (unique constraint + retry).
- **Testing:** See `testable-invariants.md` → ORD-006.

## ORD-007 — Order status transitions are backend-validated

- **Rule:** Clients never invent or force status changes; the backend validates every transition against the order state machine.
- **Reason:** Phase 1.1 order-state rule.
- **Implementation:** Later order state machine.
- **Testing:** See `testable-invariants.md` → ORD-007; `order-state-rules.md`.

## ORD-008 — Status history is recorded

- **Rule:** Every significant order status change is recorded (previous status, new status, time, actor/source, optional note).
- **Reason:** Powers tracking timeline and operational accountability (Phase 1.3 §17).
- **Implementation:** Later status-history model.
- **Testing:** See `testable-invariants.md` → ORD-008.

---

# Cancellation

## CANCEL-001 — 20-minute customer cancellation window

- **Rule:** A customer may cancel an order only within 20 minutes of order creation.
- **Reason:** Phase 1.1 decision 7; Phase 1.2 decision 11.
- **Implementation:** Later backend; eligibility computed from server timestamp.
- **Testing:** See `testable-invariants.md` → CANCEL-001.

## CANCEL-002 — Cancellation timing is backend-controlled

- **Rule:** The backend determines whether a cancellation request is inside the allowed window. A client-supplied timestamp must not bypass the window.
- **Reason:** Time-bounded rule must be tamper-resistant.
- **Implementation:** Later backend; server time authority.
- **Testing:** See `testable-invariants.md` → CANCEL-002.

## CANCEL-003 — Cancellation is not automatic refund

- **Rule:** Cancellation and refund are separate concepts. Cancelling an order must not be assumed to refund the customer automatically.
- **Reason:** Phase 1.3 §20; refunds defined later with payment behavior.
- **Implementation:** Later payment/order rules.
- **Testing:** See `testable-invariants.md` → CANCEL-003.

## CANCEL-004 — Staff/admin cancellation is not 20-minute-bound

- **Rule:** Staff/admin cancellation is a different operation and is not assumed to carry the customer's 20-minute restriction. Its permissions/conditions are defined later (Phase Group D and later order phases).
- **Reason:** Phase 1.3 §21.
- **Implementation:** Later authorization + order operations.
- **Testing:** See `testable-invariants.md` → CANCEL-004.

## CANCEL-005 — Cancellation must be a valid transition

- **Rule:** Cancellation is only permitted where the order state machine allows a path to `CANCELLED`.
- **Reason:** Prevents arbitrary state jumps.
- **Implementation:** Later order state machine.
- **Testing:** See `testable-invariants.md` → CANCEL-005.

---

# Payments

## PAY-001 — Payment state is distinct from order state

- **Rule:** Payment state and order state are separate facts (e.g., Payment PAID while Order ACCEPTED). They must never be collapsed into one status.
- **Reason:** Phase 1.2 decision 10; finance vs fulfilment.
- **Implementation:** Later payment model + order model.
- **Testing:** See `testable-invariants.md` → PAY-001.

## PAY-002 — Client payment success is not authoritative

- **Rule:** A frontend cannot declare an order paid by sending a `payment_status = success` value. The backend must verify payment through an approved payment-provider integration.
- **Reason:** Phase 1.1 payment rules; webhook/verification pattern.
- **Implementation:** Later payment boundary (Phase Group H).
- **Testing:** See `testable-invariants.md` → PAY-002.

## PAY-003 — Payment integration is provider-agnostic at this stage

- **Rule:** No provider, SDK, webhook payload, credentials, payment URL, or provider-specific statuses are decided here. The domain keeps a provider-agnostic payment boundary.
- **Reason:** Phase 1.1 decision 6. See `domain-conflicts.md` for the Phase Group G vs H numbering note.
- **Implementation:** Later payment phase.
- **Testing:** See `testable-invariants.md` → PAY-003.

## PAY-004 — Payment state transitions are backend-driven

- **Rule:** Payment state changes come from backend-verified events (e.g., provider callback verified by the backend), never from client claims.
- **Reason:** Trustworthy payment state.
- **Implementation:** Later payment service.
- **Testing:** See `testable-invariants.md` → PAY-004.

---

# Requests (Made-to-Order)

## REQ-001 — Requests may be anonymous or authenticated

- **Rule:** A made-to-order request may be submitted anonymously (with sufficient contact details) or by an authenticated customer.
- **Reason:** Phase 1.1 decision 3; IDENT-004.
- **Implementation:** Later request service.
- **Testing:** See `testable-invariants.md` → REQ-001.

## REQ-002 — Requests may optionally reference a product

- **Rule:** A request may optionally reference an existing catalog product; it may also exist without one.
- **Reason:** Phase 1.2 glossary.
- **Implementation:** Later request model.
- **Testing:** See `testable-invariants.md` → REQ-002.

## REQ-003 — Requests may specify requirements

- **Rule:** A request may specify quantity, dimensions, preferred material/color, and notes.
- **Reason:** Needed to capture manufacture intent.
- **Implementation:** Later request fields.
- **Testing:** See `testable-invariants.md` → REQ-003.

## REQ-004 — Requests are not orders, payments, or stock reservations

- **Rule:** A request does not create an order, payment, or inventory reservation. The business evaluates the request first.
- **Reason:** ORD-003; Request ≠ Order.
- **Implementation:** Later request service.
- **Testing:** See `testable-invariants.md` → REQ-004.

## REQ-005 — Requests are leads only in Version 1

- **Rule:** A made-to-order request never becomes an order in Version 1.
- **Reason:** Phase 1.2 decision 20 (owner answer to open question 3).
- **Implementation:** Later request lifecycle; no request→order flow in V1.
- **Testing:** See `testable-invariants.md` → REQ-005.

## REQ-006 — No quotation price assumed at request time

- **Rule:** The final quotation price must not be assumed or recorded as authoritative at request time.
- **Reason:** Phase 1.3 §23.
- **Implementation:** Later business workflow.
- **Testing:** See `testable-invariants.md` → REQ-006.

---

# Enquiries

## ENQ-001 — Enquiries may be anonymous or authenticated

- **Rule:** A general enquiry may be submitted anonymously (with contact information) or by an authenticated customer.
- **Reason:** Phase 1.1 decision 4; IDENT-005.
- **Implementation:** Later enquiry service.
- **Testing:** See `testable-invariants.md` → ENQ-001.

## ENQ-002 — Enquiries contain contact info and a message

- **Rule:** An enquiry must contain adequate contact information and a customer message.
- **Reason:** Enquiries are leads requiring follow-up.
- **Implementation:** Later validation.
- **Testing:** See `testable-invariants.md` → ENQ-002.

## ENQ-003 — Enquiries are not orders or payments

- **Rule:** An enquiry does not create an order or payment and does not reserve stock.
- **Reason:** ORD-004; Enquiry ≠ Order.
- **Implementation:** Later enquiry service.
- **Testing:** See `testable-invariants.md` → ENQ-003.

## ENQ-004 — Enquiry is distinct from made-to-order request

- **Rule:** Enquiry and Made-to-order Request must not be merged into one concept.
- **Reason:** Phase 1.2 decision 17; different business intents.
- **Implementation:** Later separate models/contracts.
- **Testing:** See `testable-invariants.md` → ENQ-004.

---

# Addresses

## ADDR-001 — No persistent address book

- **Rule:** Version 1 has no persistent saved-address book. Addresses are captured for the relevant checkout/order use case.
- **Reason:** Phase 1.1 decision 11; Address ≠ Address Book.
- **Implementation:** Later order/checkout data model.
- **Testing:** See `testable-invariants.md` → ADDR-001.

## ADDR-002 — No mandatory saved addresses on the account

- **Rule:** A customer account must not be required to hold a saved address list.
- **Reason:** ADDR-001.
- **Implementation:** Later account model.
- **Testing:** See `testable-invariants.md` → ADDR-002.

## ADDR-003 — Billing and delivery addresses stay distinct

- **Rule:** Billing address and delivery address are conceptually distinct concepts even when they contain the same information.
- **Reason:** Phase 1.2 glossary / Phase 1.3 §26.
- **Implementation:** Later order data model.
- **Testing:** See `testable-invariants.md` → ADDR-003.

## ADDR-004 — Delivery requires valid delivery information

- **Rule:** A delivery order requires valid delivery contact and address information.
- **Reason:** FUL-003.
- **Implementation:** Later checkout validation.
- **Testing:** See `testable-invariants.md` → ADDR-004.

---

# Attachments

## ATTACH-001 — Attachments are optional, for requests/enquiries only

- **Rule:** Attachments are optional and may belong to a made-to-order request or a general enquiry. They are not used elsewhere in Version 1.
- **Reason:** Phase 1.1 decision 13.
- **Implementation:** Later request/enquiry models.
- **Testing:** See `testable-invariants.md` → ATTACH-001.

## ATTACH-002 — Storage provider not selected

- **Rule:** No file-storage provider (S3, Cloudinary, local filesystem, CDN, image processing) is chosen at this stage.
- **Reason:** Phase 1.2 decision 14 deferred implementation.
- **Implementation:** Later phase.
- **Testing:** See `testable-invariants.md` → ATTACH-002.

## ATTACH-003 — Future upload safety requirements

- **Rule:** Later implementation must enforce allowed file types, file size limits, secure upload handling, malware/security considerations, and storage access rules.
- **Reason:** MANDATORY security baseline (AGENTS.md §18).
- **Implementation:** Later attachment phase.
- **Testing:** See `testable-invariants.md` → ATTACH-003.

---

# Notifications

## NOTIF-001 — Notifications are application-event records

- **Rule:** A notification is a communication/alert associated with an application event (e.g., order-state changes). Notifications reference events; they do not drive the order state machine.
- **Reason:** Phase 1.2 decision 13.
- **Implementation:** Later notification model.
- **Testing:** See `testable-invariants.md` → NOTIF-001.

## NOTIF-002 — External delivery is deferred

- **Rule:** Real email/push/SMS delivery is deferred to Phase Group R. Version 1 does not require external email delivery as a core dependency.
- **Reason:** Phase 1.1 decision 9.
- **Implementation:** Later Phase Group R.
- **Testing:** See `testable-invariants.md` → NOTIF-002.

## NOTIF-003 — No email infrastructure assumed

- **Rule:** SMTP, email providers, email templates, and external notification providers are not designed at this stage.
- **Reason:** NOTIF-002.
- **Implementation:** None now.
- **Testing:** See `testable-invariants.md` → NOTIF-003.

---

# Authorization

## AUTHZ-001 — High-level roles only

- **Rule:** Version 1 recognizes the high-level roles CUSTOMER, STAFF, and ADMIN. No ad-hoc permission matrix is created here.
- **Reason:** Phase 1.1 decision 12; Phase Group D owns permissions.
- **Implementation:** Later role model.
- **Testing:** See `testable-invariants.md` → AUTHZ-001.

## AUTHZ-002 — Every privileged operation is authorization-restricted

- **Rule:** Every privileged business operation must eventually be restricted by explicit authorization.
- **Reason:** Phase 1.3 §27.
- **Implementation:** Later policies/permissions (Phase Group D).
- **Testing:** See `testable-invariants.md` → AUTHZ-002.

## AUTHZ-003 — Customers see only their own data

- **Rule:** Ownership-based access for customer data (profile, orders, requests, enquiries, addresses, payment info) — see IDENT-006.
- **Reason:** Privacy.
- **Implementation:** Later backend ownership checks.
- **Testing:** See `testable-invariants.md` → AUTHZ-003.

## AUTHZ-004 — Commerce and status mutations are backend-only

- **Rule:** Setting totals, changing order status, confirming payment, and altering inventory are backend-only mutations; clients cannot perform them on their authority.
- **Reason:** Backend authority principle.
- **Implementation:** Later backend.
- **Testing:** See `testable-invariants.md` → AUTHZ-004.

---

# Security

## SEC-001 — Server-side decisions for sensitive operations

- **Rule:** Customer identity authentication, stock decisions, price decisions, delivery-fee decisions, cancellation eligibility, and payment verification happen server-side.
- **Reason:** Backend authority + security baseline.
- **Implementation:** Later backend.
- **Testing:** See `testable-invariants.md` → SEC-001.

## SEC-002 — File upload validation

- **Rule:** File uploads require validation (type, size, security).
- **Reason:** ATTACH-003; AGENTS.md §18.
- **Implementation:** Later attachment handling.
- **Testing:** See `testable-invariants.md` → SEC-002.

## SEC-003 — Administrative actions require authorization

- **Rule:** Administrative actions are restricted to authorized staff/admin.
- **Reason:** AUTHZ-002; Group D policies.
- **Implementation:** Later authorization.
- **Testing:** See `testable-invariants.md` → SEC-003.

## SEC-004 — No secrets in clients

- **Rule:** Payment provider secrets, database credentials, and other secrets must not appear in browser code, Flutter application code, public configuration, or the Git repository.
- **Reason:** AGENTS.md §18.
- **Implementation:** Later backend-only secret management.
- **Testing:** See `testable-invariants.md` → SEC-004.

---

# Data Integrity

## DATA-001 — Referential integrity for mandatory relationships

- **Rule:** A business record must not silently reference an entity that no longer exists where that relationship is mandatory.
- **Reason:** Data integrity baseline.
- **Implementation:** Later data model/constraints.
- **Testing:** See `testable-invariants.md` → DATA-001.

## DATA-002 — Historical order integrity

- **Rule:** Historical order records must remain explainable even when current catalog information changes (see ORD-005).
- **Reason:** Historical order facts are preserved.
- **Implementation:** Later snapshot model.
- **Testing:** See `testable-invariants.md` → DATA-002.

## DATA-003 — Server-side financial and inventory calculation

- **Rule:** Financial and inventory calculations use trusted backend data.
- **Reason:** Money/inventory authority.
- **Implementation:** Later backend.
- **Testing:** See `testable-invariants.md` → DATA-003.

## DATA-004 — Atomicity of multi-step operations

- **Rule:** Operations involving multiple related changes must be designed transactionally where failure between steps could corrupt business state. Order creation with inventory reservation is atomic: if either fails, no order is persisted and no stock is reserved. Payment confirmation is separate: `PENDING_PAYMENT` is a valid order state, so a payment-related failure does not roll back the order; the order remains in `PENDING_PAYMENT` with its inventory reservation intact (released only via later expiry or cancellation).
- **Reason:** Phase 1.3 §29.4; `PENDING_PAYMENT` is a valid state (FUL/ORD).
- **Implementation:** Later transaction boundaries; distinct handling for order+inventory vs payment.
- **Testing:** See `testable-invariants.md` → DATA-004.

---

# Concurrency

## CONC-001 — Inventory reservation is concurrency-safe

- **Rule:** Concurrent inventory reservations must never oversell (see INV-003).
- **Reason:** Example: two requests for the last unit → only one succeeds.
- **Implementation:** Later transactional/concurrency controls.
- **Testing:** See `testable-invariants.md` → CONC-001.

## CONC-002 — Order creation is concurrency-safe

- **Rule:** Concurrent order-creation requests must not produce duplicate or conflicting orders from one checkout intent.
- **Reason:** Prevents double-purchase.
- **Implementation:** Later concurrency + idempotency.
- **Testing:** See `testable-invariants.md` → CONC-002.

## CONC-003 — Payment callbacks are concurrency-safe

- **Rule:** Concurrent/repeated payment callbacks must not create duplicate effects.
- **Reason:** IDEMP-003.
- **Implementation:** Later payment service.
- **Testing:** See `testable-invariants.md` → CONC-003.

## CONC-004 — Order status changes are concurrency-safe

- **Rule:** Concurrent status transitions must be serialized/guarded so only valid, single transitions apply.
- **Reason:** Prevents race corruption of order state.
- **Implementation:** Later state machine.
- **Testing:** See `testable-invariants.md` → CONC-004.

---

# Idempotency

## IDEMP-001 — Checkout finalization is idempotent

- **Rule:** Retrying checkout finalization must not create duplicate business effects.
- **Reason:** Signal/retry safety.
- **Implementation:** Later idempotency keys.
- **Testing:** See `testable-invariants.md` → IDEMP-001.

## IDEMP-002 — Order creation is idempotent

- **Rule:** Retrying order creation for the same checkout intent must not create two orders.
- **Reason:** Prevents duplicate orders.
- **Implementation:** Later idempotency design.
- **Testing:** See `testable-invariants.md` → IDEMP-002.

## IDEMP-003 — Payment webhook/callback handling is idempotent

- **Rule:** A payment provider sending the same callback twice must not create two orders, record two payments, or send two incompatible status transitions.
- **Reason:** Phase 1.3 §31.
- **Implementation:** Later payment service.
- **Testing:** See `testable-invariants.md` → IDEMP-003.

## IDEMP-004 — Inventory reservation is idempotent

- **Rule:** Repeating an inventory reservation operation must not double-reserve.
- **Reason:** Prevent stock corruption.
- **Implementation:** Later reservation logic.
- **Testing:** See `testable-invariants.md` → IDEMP-004.

## IDEMP-005 — Critical status-changing operations are idempotent

- **Rule:** Critical status-changing operations must be safe against duplicate execution.
- **Reason:** Phase 1.3 §31.
- **Implementation:** Later state machine + idempotency.
- **Testing:** See `testable-invariants.md` → IDEMP-005.

---

# Error Semantics

Business-level error meanings (conceptual; HTTP status codes and JSON structures are decided in the API contract phase).

## ERR-001 — Authentication required

- **Rule:** Operations requiring an identified customer return an "authentication required" domain error when the actor is anonymous.
- **Reason:** IDENT-002, CHECKOUT-001.
- **Implementation:** Later API contract.

## ERR-002 — Unauthorized operation

- **Rule:** Operations an actor is not authorized to perform return an "unauthorized operation" domain error.
- **Reason:** AUTHZ-002.
- **Implementation:** Later API contract.

## ERR-003 — Product not found

- **Rule:** Referencing a non-existent product returns a "product not found" domain error.
- **Reason:** CAT-001, DATA-001.
- **Implementation:** Later API contract.

## ERR-004 — Product not purchasable

- **Rule:** Attempting to purchase a non-`IN_STOCK` product returns a "product not purchasable" domain error.
- **Reason:** CAT-003.
- **Implementation:** Later API contract.

## ERR-005 — Invalid product variant

- **Rule:** Selecting an invalid or foreign variant returns an "invalid product variant" domain error.
- **Reason:** VAR-002, VAR-003.
- **Implementation:** Later API contract.

## ERR-006 — Insufficient stock

- **Rule:** A quantity exceeding available stock returns an "insufficient stock" domain error at the authoritative check.
- **Reason:** INV-002/003/005.
- **Implementation:** Later API contract.

## ERR-007 — Checkout not allowed

- **Rule:** Checkout attempted without an authenticated customer, or otherwise disallowed, returns a "checkout not allowed" domain error.
- **Reason:** CHECKOUT-001.
- **Implementation:** Later API contract.

## ERR-008 — Invalid fulfillment

- **Rule:** An invalid fulfillment selection returns an "invalid fulfillment" domain error.
- **Reason:** CHECKOUT-004, FUL-001.
- **Implementation:** Later API contract.

## ERR-009 — Invalid delivery information

- **Rule:** A delivery order with missing/invalid delivery contact or address returns an "invalid delivery information" domain error.
- **Reason:** FUL-003, ADDR-004.
- **Implementation:** Later API contract.

## ERR-010 — Invalid order state

- **Rule:** A disallowed status transition returns an "invalid order state" domain error.
- **Reason:** ORD-007.
- **Implementation:** Later API contract.

## ERR-011 — Order not cancellable

- **Rule:** A cancellation outside the allowed window/state returns an "order not cancellable" domain error.
- **Reason:** CANCEL-001/005.
- **Implementation:** Later API contract.

## ERR-012 — Payment not confirmed

- **Rule:** A payment the backend cannot confirm returns a "payment not confirmed" domain error.
- **Reason:** PAY-002.
- **Implementation:** Later API contract.

## ERR-013 — Request invalid

- **Rule:** An invalid made-to-order request returns a "request invalid" domain error.
- **Reason:** REQ-001/003.
- **Implementation:** Later API contract.

## ERR-014 — Enquiry invalid

- **Rule:** An invalid general enquiry returns an "enquiry invalid" domain error.
- **Reason:** ENQ-001/002.
- **Implementation:** Later API contract.

---

## Endnote

These invariant IDs are stable references. Later phases (data model, API contract, Laravel, tests) must reference these IDs rather than inventing new rules independently.