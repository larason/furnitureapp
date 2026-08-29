# Business-to-Data Traceability — Version 1

## 1. Purpose

This matrix maps every important business requirement from Phases 1.1–1.3 to the logical data concepts that support it (Phase 1.4 model). Every requirement must have data coverage; any requirement without data support is flagged.

Source documents:

- Phase 1.1 — `phase-1.1-api-scope.md`, `api-scope-v1.md`
- Phase 1.2 — `phase-1.2-domain-nouns.md`, `domain-glossary.md`, `domain-boundaries.md`
- Phase 1.3 — `phase-1.3.md`, `domain-invariants.md`, `business-rules.md`, `order-state-rules.md`
- Phase 1.4 — `phase-1.4.md`, `logical-data-model.md`, `entity-relationship-matrix.md`, `data-classification.md`, `data-ownership.md`, `historical-data-rules.md`, `data-integrity-requirements.md`, `data-model-decisions.md`, `api-exposure-classification.md`

Legend: **Supported** = the logical model contains concepts that satisfy the requirement. **Partial (deferred)** = concept exists but a decision or representation is deferred to a later phase (non-blocking). **Flagged** = no data support found; must be resolved before freeze.

---

## 2. Business-to-Data Matrix

### 2.1 Identity and Access

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Guest browsing without login | Phase 1.1 dec. 1; IDENT-001/003 | PUBLIC Category, Product, Product Image, Product Variant, prices, derived availability; no User dependency | Supported |
| Checkout requires authenticated customer | Phase 1.1 dec. 2; IDENT-002, CHECKOUT-001, ORD-002 | Order → User (customer) mandatory; Cart binds to User on authentication | Supported |
| One shared User identity for all personas | IDENT-007 | User entity with Role (CUSTOMER/STAFF/ADMIN) many-to-one | Supported |
| Anonymous made-to-order requests | Phase 1.1 dec. 3; IDENT-004, REQ-001 | Made-to-order Request: User reference optional, name + contact channel required | Supported |
| Anonymous general enquiries | Phase 1.1 dec. 4; IDENT-005, ENQ-001 | General Enquiry: User reference optional, contact info + message required | Supported |
| Customer data privacy / ownership scoping | IDENT-006, AUTHZ-003 | PRIVATE classification on orders, requests, enquiries, addresses, payments; owner-scoped API exposure | Supported |
| Roles: CUSTOMER / STAFF / ADMIN | AUTHZ-001 | User.Role; detailed permissions deferred to Group D | Supported |
| Privileged operations authorization-restricted | AUTHZ-002, SEC-003 | Role data + later Group D policies; STAFF/ADMIN API exposure | Supported |

### 2.2 Catalog

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Product identity unambiguous | CAT-001 | Product (slug, SKU globally unique) | Supported |
| Exactly two product types | CAT-002 | Product.Product type = `IN_STOCK` / `MADE_TO_ORDER` | Supported |
| Product type controls customer action | CAT-003 | Product type gates checkout (purchasability) vs request | Supported |
| Inactive products not newly purchasable | CAT-004 | Product active state | Supported |
| Category is a grouping, not a product | CAT-005 | Category entity separate from Product | Supported |
| Product ≠ Inventory / Order Item / Request | CAT-006, Decision 17 | Separate entities: Inventory, Order Item, Request | Supported |
| Flat single-level categories | DM-DEC-01 | Category without parent hierarchy | Supported |
| Product images | — | Product Image entity (PUBLIC) | Supported |
| Product variants as purchasable units | VAR-005, DM-DEC-02 | Product Variant entity; may carry SKU, price override, inventory | Supported |
| Variant belongs to exactly one product | VAR-001/002 | Product Variant → Product (mandatory many-to-one) | Supported |
| Inactive variants not purchasable | VAR-003 | Variant active state | Supported |

### 2.3 Inventory and Availability

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Inventory is backend authority | INV-001 | Inventory entity (INTERNAL); derived public availability signal | Supported |
| Physical / reserved / available distinguished | INV-006, DM-DEC-03 | Inventory: stock quantity (physical), reserved quantity; available is derived | Supported |
| No negative available stock | INV-002 | Inventory invariant: `0 <= reserved <= physical`; available never negative | Supported |
| No overselling under concurrency | INV-003, CONC-001 | Inventory + reservation invariant (reject above `physical − reserved`) | Supported |
| Cart is not a reservation | INV-004, CART-003 | Cart/Cart Item separate from Inventory; revalidation at checkout | Supported |
| Displayed stock not guaranteed | INV-005 | Derived availability signal vs authoritative Inventory | Supported |
| Variant-level inventory | INV-007 | Inventory attaches to product or variant (purchasable unit) | Supported |
| No full inventory movement history | DM-DEC-05 | Decision recorded; lightweight audit deferred | Partial (deferred) |

### 2.4 Cart

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Guest cart allowed, binds on auth | Phase 1.2 Q1; IDENT-008, CART-002, DM-DEC-04 | Cart: guest identity or User owner (conditional) | Supported |
| Cart item references valid purchasable product/variant | CART-001 | Cart Item → Product (required), → Variant (conditional) | Supported |
| Cart ≠ Order | CART-003 | Separate Cart/Cart Item vs Order/Order Item | Supported |
| Revalidate before checkout | CART-004, CHECKOUT-002 | Cart/Checkout revalidation concept (Product, Variant, price, inventory, quantity, fulfillment) | Supported |
| Frontend cannot set final total | CART-005, PRICE-001/002, CHECKOUT-003 | Backend computes authoritative totals from Order data; client totals ignored | Supported |

### 2.5 Pricing and Money

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Backend authoritative for money | PRICE-001/002 | Catalog price (SOURCE), Order totals (DERIVED, snapshot at finalization) | Supported |
| Single currency TZS | PRICE-003, Decision 16 | Currency on Order/Payment; DM-DEC-13 | Supported |
| Safe money representation | PRICE-004, DM-DEC-13 | Decision recorded (safe discrete representation; no float) | Supported |
| Pickup free | PRICE-005, FUL-002 | Fulfillment PICKUP → delivery fee 0 | Supported |
| Delivery fee variable, staff-managed, region/location-based | Phase 1.1 dec. 5, Phase 1.2 Q2, PRICE-006, Decision 19 | Order.Delivery fee (canonical SNAPSHOT in the authoritative total; Delivery carries a non-authoritative projection); Delivery Fee Rule / Region / Location staff-managed configuration | Partial (deferred) — fee-rule representation deferred to Phase 1.13/3.13 (decision recorded) |

### 2.6 Checkout and Fulfillment

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Checkout requires authentication | CHECKOUT-001, ORD-002 | Checkout → Order → User (customer) | Supported |
| Full revalidation at checkout | CHECKOUT-002 | Revalidation concepts (CART-004) | Supported |
| Backend computes total | CHECKOUT-003 | Order totals derived server-side | Supported |
| Fulfillment selection validated | CHECKOUT-004 | Order.Fulfillment type PICKUP/DELIVERY | Supported |
| Invalid cart cannot create order | CHECKOUT-005 | Order creation gated by valid cart | Supported |
| Exactly two fulfillment modes | FUL-001 | Order.Fulfillment type | Supported |
| Pickup free, proceeds to READY_FOR_PICKUP | FUL-002 | Order lifecycle (order-state-rules) | Supported |
| Delivery requires valid delivery info + staff fee | FUL-003, ADDR-004 | Delivery entity (recipient, phone, address, fee) required for DELIVERY | Supported |
| No automatic distance pricing | FUL-004 | No zone/GPS algorithm in model; staff-managed fee rules only | Supported |
| Pickup never shows SHIPPED | FUL-005 | Order state machine (pickup lifecycle) | Supported |
| Only applicable milestones shown | FUL-006 | Order Status History + fulfillment-specific timeline | Supported |

### 2.7 Orders

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Order created only via purchase flow | ORD-001 | Order entity created via checkout | Supported |
| Order creation requires authenticated customer | ORD-002 | Order → User (customer) | Supported |
| Request does not create order/payment/reservation | ORD-003, REQ-004 | Request separate entity; no request→order link (Decision 20) | Supported |
| Enquiry does not create order | ORD-004 | Enquiry separate entity | Supported |
| Historical order facts preserved | ORD-005, DATA-002 | Order Item snapshots (name, SKU, price, qty, subtotal) | Supported |
| `OD-` order reference | Phase 1.1 dec. 8; ORD-006 | Order reference, globally unique | Supported |
| Backend-validated status transitions | ORD-007 | Order status + state machine rules (order-state-rules) | Supported |
| Status history recorded | ORD-008, OS-026..029 | Order Status History (append-only; prev/new status, time, actor, note) | Supported |
| 20-minute cancellation window | Phase 1.1 dec. 7; CANCEL-001/002 | Order created timestamp + backend window check | Supported |
| Cancellation ≠ automatic refund | CANCEL-003 | Cancellation concept separate from refund (deferred) | Supported |
| Staff/admin cancellation separate | CANCEL-004 | Actor/source on status history; authorization deferred to Group D | Supported |
| Cancellation only from valid states | CANCEL-005 | State machine allows `→ CANCELLED` from eligible states | Supported |

### 2.8 Payments

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Payment state distinct from order state | PAY-001 | Payment entity separate from Order status | Supported |
| Client cannot confirm payment | PAY-002 | Payment verification state (backend-verified) | Supported |
| Provider-agnostic boundary | PAY-003, DM-DEC-08 | Payment entity generic (provider, provider ref conditional EXTERNAL) | Supported |
| Provider selection deferred | Phase 1.1 dec. 6 / AGENTS.md Group H | Payment provider fields conditional/deferred | Partial (deferred) |
| Backend-driven payment transitions | PAY-004 | Payment state via backend-verified events | Supported |
| Payment webhook idempotent | IDEMP-003, CONC-003 | Concurrency/idempotency requirements recorded | Supported |

### 2.9 Requests and Enquiries

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| MTO request anonymous or authenticated | REQ-001 | Request: User optional, contact required | Supported |
| Request may reference product | REQ-002 | Request → Product optional | Supported |
| Request captures requirements | REQ-003 | Request: quantity, dimensions, material, color, notes | Supported |
| Request is lead only in V1 | REQ-005, Decision 20 | No request→order relationship | Supported |
| No quotation at request time | REQ-006 | No authoritative price on Request | Supported |
| Enquiry anonymous or authenticated | ENQ-001 | Enquiry: User optional, contact required | Supported |
| Enquiry contact + message | ENQ-002 | Enquiry: name, contact, message | Supported |
| Enquiry ≠ order | ENQ-003 | Enquiry separate entity | Supported |
| Enquiry ≠ MTO request | ENQ-004 | Request and Enquiry distinct entities (DM-DEC-09) | Supported |

### 2.10 Addresses

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| No persistent address book | Phase 1.1 dec. 11; ADDR-001/002 | Addresses are Order transaction data; no address-book entity | Supported |
| Billing vs delivery distinct | ADDR-003 | Order Address typed billing/delivery (0..1 each) | Supported |
| Delivery requires delivery info | ADDR-004 | Delivery address/contact conditional on DELIVERY | Supported |

### 2.11 Attachments

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Optional attachments on requests/enquiries | Phase 1.1 dec. 13; ATTACH-001 | Attachment → one Request or one Enquiry (polymorphic owner, optional) | Supported |
| Storage provider not selected | ATTACH-002 | Attachment = logical file reference only (DM-DEC-11) | Supported |
| Future upload safety | ATTACH-003, SEC-002 | Security/validation state on Attachment; SEC rules recorded | Supported |

### 2.12 Notifications

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Notification = application-event record | NOTIF-001 | Notification entity (recipient, type, related entity, message, read state) | Supported |
| External delivery deferred to Group R | Phase 1.1 dec. 9; NOTIF-002/003 | No email/SMS/push infrastructure in model | Partial (deferred) |

### 2.13 Data Integrity, Concurrency, Idempotency

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| Referential integrity for mandatory relations | DATA-001 | Logical referential expectations (Product→Category, Order→User, etc.) | Supported |
| Historical order integrity | DATA-002 | Snapshots + append-only status history | Supported |
| Server-side financial/inventory calc | DATA-003 | Backend authority; no client-authoritative values | Supported |
| Atomicity of multi-step ops | DATA-004, CHECKOUT-006 | Order+reservation atomic; payment failure leaves PENDING_PAYMENT | Supported |
| Concurrency-safe inventory/order/payment/status | CONC-001..004 | Concurrency requirements recorded (mechanism deferred) | Supported |
| Idempotency of checkout/order/payment/reservation/status | IDEMP-001..005 | Idempotency requirements recorded (mechanism deferred) | Supported |

### 2.14 SEO

| Business requirement | Source / rule | Supporting data concept | Status |
|---|---|---|---|
| SEO-relevant product/category data | Phase 1.4 §42 | Product: name, slug, description, images, price, currency, availability, category, variants; optional SEO fields | Supported |
| Stable human-readable URLs | Phase 1.4 §37 | Product slug, Category slug (globally unique) | Supported |

---

## 3. Coverage Summary

| Result | Count | Notes |
|---|---|---|---|
| Supported | 70+ | Every major requirement maps to logical data concepts |
| Partial (deferred) | 4 | Delivery Fee Rule representation (Phase 1.13/3.13); full inventory history (deferred); notification delivery (Group R); payment provider fields (Group H) — corresponds to the four `Partial (deferred)` rows in §2 |
| Flagged (no data support) | 0 | — |

## 4. Flagged Items

**None.** No business requirement from Phases 1.1–1.3 lacks a logical data concept.

All "partial" items are explicitly documented deferrals (with decision IDs and target phases), not gaps in the logical model.

---

## 5. Conclusion

The logical data model produced in Phase 1.4 has **full coverage** of the approved Version 1 business requirements. Every workflow-defining rule is backed by at least one data concept. The model is traceable and ready for freezing.