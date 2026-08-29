# API Resource Decisions — Phase 1.6

Records decisions made during Phase 1.6 (API Resource Inventory). Each includes Decision ID, Decision, Reason, Affected Resource, Future Implementation Phase. Phase 1.5 freeze and prior decisions remain authoritative.

---

## 1.6-DEC-01 — Category as standalone public resource

- **Decision:** Treat Category as a standalone PUBLIC API RESOURCE (not only referenced by products), because website and app require category browsing.
- **Reason:** Phase 1.6 §8, VISION.md/admin panel Products/Available/Made to Order requires category navigation; SEO category pages.
- **Affected resource:** Category
- **Future phase:** 5.1 (category read API), 11.4 (admin category CRUD)

## 1.6-DEC-02 — Product Image as subresource of Product

- **Decision:** Product Image is a SUBRESOURCE/EMBEDDED RESOURCE of Product (`Product → Product Images`), not a standalone top-level public resource.
- **Reason:** Customers need images as part of product representation; admin image ops are via product management (Phase 1.6 §9).
- **Affected resource:** Product Image
- **Future phase:** Phase 1.7 (relationships), Phase Group E / 11.5 (image management)

## 1.6-DEC-03 — Product Variant as subresource of Product

- **Decision:** Product Variant is a SUBRESOURCE of Product (`Product → Variants`), invariant: Variant belongs to exactly one Product and must not escape it.
- **Reason:** Phase 1.6 §10, Domain Invariants VAR-001/002; prevents invalid product-variant pairing.
- **Affected resource:** Product Variant
- **Future phase:** 5.4 (variant API), 3.5 (variants schema)

## 1.6-DEC-04 — Availability as embedded representation, not internal inventory

- **Decision:** Public Availability is a REFERENCE-ONLY/embedded representation in Product/Variant, derived from Inventory; internal Inventory (physical/reserved quantities) remains INTERNAL_ONLY and not publicly exposed.
- **Reason:** Phase 1.6 §11-12, AGENTS.md §12, DM-DEC-03; simplest customer-facing signal without leaking internals.
- **Affected resource:** Product Availability, Inventory
- **Future phase:** 5.7/5.8 (availability/inventory read model), 5.9/5.10 (mutation/concurrency)

## 1.6-DEC-05 — Inventory as staff/admin operational resource

- **Decision:** Inventory is INTERNAL CONCEPT + STAFF/ADMIN OPERATIONAL RESOURCE; customers get availability signal only, never inventory management.
- **Reason:** Phase 1.6 §12, AGENTS.md inventory rules (§12) — backend-controlled, prevents overselling, transactional.
- **Affected resource:** Inventory
- **Future phase:** Phase Group E (inventory API), 3.7 (inventory schema), Phase Group K (admin inventory)

## 1.6-DEC-06 — Guest cart with GUEST_TOKEN holder distinction

- **Decision:** Cart supports both GUEST_TOKEN holder (anonymous persistent cart) and AUTHENTICATED owner; GUEST_TOKEN is distinct from user session (DM-DEC-04, IDENT-008/CART-002). Cart is holder-scoped, binds to User on authentication; checkout still requires authenticated account.
- **Reason:** Phase 1.6 §13, §29 footnote `*` — anonymous persistent carts approved per Decision 18; Phase 1.6 resolves TBD via prior decision.
- **Affected resource:** Cart, Cart Item
- **Future phase:** 6.x (cart model/API), Phase 1.12/Group F (cart contract)

## 1.6-DEC-07 — Checkout as workflow resource, not persisted entity

- **Decision:** Checkout is a workflow/resource operation coordinating Cart→validation→fulfillment→pricing→order+payment creation, not a long-lived catalog object.
- **Reason:** Phase 1.6 §15; multi-step atomic operation where failure between steps must not corrupt state.
- **Affected resource:** Checkout
- **Future phase:** Group G (checkout), Phase 1.13 (checkout contract)

## 1.6-DEC-08 — Order Item as child of Order, historically immutable

- **Decision:** Order Item is a SUBRESOURCE of Order (`Order → Order Items`), not independently addressable top-level customer resource; purpose is snapshot of what was purchased, immutable after creation.
- **Reason:** Phase 1.6 §17, AGENTS.md §11 historical orders must preserve snapshots.
- **Affected resource:** Order Item
- **Future phase:** 3.10 (order items snapshot), Group I (order management)

## 1.6-DEC-09 — Order Tracking/Status History as read-oriented subresources

- **Decision:** Order Tracking is a read-oriented subresource of Order (`Order → Tracking → Status History`); customer gets projection without Operational note (STAFF/ADMIN only); history is append-only, not freely editable.
- **Reason:** Phase 1.6 §18-19, AGENTS.md §15, order-state-rules.md OS-026..029; powers customer timeline + auditability.
- **Affected resource:** Order Tracking, Order Status History
- **Future phase:** 3.11 (status history schema), 9.4/9.7 (history/tracking), 1.16 (status history contract)

## 1.6-DEC-10 — Payment separate from Order

- **Decision:** Payment is a separate resource from Order (`Order ≠ Payment`); customers see limited own payment info, provider internals remain INTERNAL_ONLY; provider integration deferred to Group H but resource exists conceptually as provider-agnostic.
- **Reason:** Phase 1.6 §20, domain boundary PAY-001, AGENTS.md §14 payment rules.
- **Affected resource:** Payment
- **Future phase:** Group H (payments), 3.12 (payment schema), 1.14 (payment contract)

## 1.6-DEC-11 — Delivery/Fulfillment as order-associated subresource

- **Decision:** Fulfillment is SUBRESOURCE of Order, conditional on DELIVERY (PICKUP has no Delivery record, fee 0). Delivery-specific info only when relevant; canonical fee/address on Order/OrderAddress, Delivery holds non-authoritative projections.
- **Reason:** Phase 1.6 §21, VISION.md PICKUP free / DELIVERY fee, Phase 1.5 projection rule.
- **Affected resource:** Delivery/Fulfillment
- **Future phase:** 3.13 (delivery schema), Group G (delivery flow/fee rules), 1.13 (checkout contract)

## 1.6-DEC-12 — Made-to-order Request as first-class resource with dual identity

- **Decision:** Made-to-order Request is a first-class API resource supporting both Anonymous creation (with contact) and Authenticated creation/viewing; anonymous request exists without User, authenticated may be linked. Remains lead only, never Order (Decision 20).
- **Reason:** Phase 1.6 §22, VISION.md Request This Furniture, AGENTS.md §4.1/§16.
- **Affected resource:** Made-to-order Request
- **Future phase:** Group J (requests), 1.17 (request contract), 3.14 (request schema)

## 1.6-DEC-13 — Enquiry as separate first-class resource

- **Decision:** General Enquiry is a first-class resource distinct from Made-to-order Request; supports Anonymous and Authenticated creation, Staff/Admin management. Do not merge.
- **Reason:** Phase 1.6 §23, DM-DEC-09, ENQ-004.
- **Affected resource:** Enquiry
- **Future phase:** Group J, 1.18 (enquiry contract), 3.15 (enquiry schema)

## 1.6-DEC-14 — Attachment as child of Request or Enquiry

- **Decision:** Attachment is a SUBRESOURCE of Made-to-order Request or General Enquiry with exactly one owner; access inherits owner's authorization; not globally addressable. Storage details deferred.
- **Reason:** Phase 1.6 §24, DM-DEC-11, AGENTS.md §4.2 general enquiries with file.
- **Affected resource:** Attachment
- **Future phase:** 10.6 (attachment handling), 3.14/3.15

## 1.6-DEC-15 — Same domain resource, role-scoped operations (no duplicated admin resources)

- **Decision:** Admin does not get duplicated resources (e.g., `AdminProduct`); same domain resources support role-specific operations: customer read projection vs staff/admin management representation, differentiated by authorization (Group D). Covers Category/Product/Inventory/Order/Request/Enquiry.
- **Reason:** Phase 1.6 §28, AGENTS.md §5/§27.
- **Affected resource:** All catalog/commerce/communication resources
- **Future phase:** Group D (policies), Group K (admin operations), Group E (catalog API)

## 1.6-DEC-16 — Resource naming in domain language

- **Decision:** Resource names use business concepts (`products`, `categories`, `carts`, `orders`, `requests`, `enquiries`, `notifications`) — no `productRows`, `tblProducts`, `OrderRecord`. Precise URL convention deferred to Phase 1.9.
- **Reason:** Phase 1.6 §31, API-first principle.
- **Affected resource:** All resources
- **Future phase:** 1.9 (endpoint naming conventions)

## 1.6-DEC-17 — No automatic table→endpoint mapping

- **Decision:** Not every logical entity becomes a public resource; internal entities (Inventory quantities, reservations), provider internals, and audit internals are not automatically exposed (e.g., no `GET /inventory-reservations`).
- **Reason:** Phase 1.6 §42 resource anti-patterns; security/privacy separation (data-classification.md, api-exposure-classification.md).
- **Affected resource:** Inventory, Payment (provider internals), Status History internals
- **Future phase:** All API contract phases
