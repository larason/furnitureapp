# Relationship Decisions — Phase 1.7

Records decisions made during Phase 1.7 (API Resource Relationships). Each includes Decision ID, Decision, Reason, Affected resources, Future implementation phase.

## 1.7-DEC-01 — Product → Category as REFERENCE with SUMMARY embed

- **Decision:** Product references Category (required, 1) as `REFERENCE`; Product embeds Category `SUMMARY` (`id/slug/name`); Category → Products is `DERIVED_RELATIONSHIP` via subresource, not full embed.
- **Reason:** Every product requires a category (single category V1 DM-DEC-01); supports SEO/browsing without recursion. Avoids `Product → Category → Products → Category` cycle.
- **Affected resources:** Product, Category
- **Future phase:** 1.9 (endpoint naming), 5.1/5.2/5.3 (category/product read)

## 1.7-DEC-02 — Product Images/Variants as PARENT_CHILD under Product

- **Decision:** Product Images and Variants are `PARENT_CHILD` of Product; not independently top-level public resources. Variant must remain bound to Product.
- **Reason:** No independent business meaning outside Product; prevents variant escaping product (VAR-001); customer needs images/variants with Product.
- **Affected resources:** Product, Product Image, Product Variant
- **Future phase:** 1.9, 3.5/3.6 (variant/image schemas), 11.x (admin catalog)

## 1.7-DEC-03 — Availability derived, Inventory internal

- **Decision:** Public relationship is `Product/Variant → Availability` as `DERIVED_RELATIONSHIP` (embedded limited qualitative signal: `available` bool, `stock_indicator` as coarse bucket `IN STOCK` / `LOW STOCK` / `MADE TO ORDER`, `product_type`; never exact quantity like `Only 2 left`). Internal relationship `Product/Variant → Inventory` (quantities) is `INTERNAL_ONLY` / `OPERATIONAL_RELATIONSHIP` for Staff/Admin only.
- **Reason:** Distinguishes customer-facing signal from authoritative stock (DM-DEC-03), prevents leaking `physical/reserved` quantities.
- **Affected resources:** Product, Variant, Availability, Inventory
- **Future phase:** 5.7/5.8 (availability/inventory read), 5.9/5.10 (inventory management)

## 1.7-DEC-04 — User → Cart as OWNERSHIP with GUEST_TOKEN holder, one active cart per holder

- **Decision:** `User → Cart` is `OWNERSHIP` when authenticated; guest Cart uses `GUEST_TOKEN` holder (bearer) per DM-DEC-04, one active Cart per holder, holder-scoped, binds to User on authentication. No multiple active carts invented.
- **Reason:** Supports guest persistent cart without requiring account for cart creation, while keeping checkout authenticated; aligns with resource-access-matrix and api-exposure-classification.
- **Affected resources:** User, Cart
- **Future phase:** 1.12 (cart contract), 6.x (cart API), 3.8 (cart schema)

## 1.7-DEC-05 — Cart → Cart Items as PARENT_CHILD, Cart Item → Product/Variant as REFERENCE

- **Decision:** Cart owns Cart Items (`PARENT_CHILD`); Cart Item references Product (required) and optionally Variant (must belong to Product). Product is not child of Cart.
- **Reason:** Prevents cart item referencing variant from unrelated product; keeps Product independent lifecycle while allowing validation at checkout.
- **Affected resources:** Cart, Cart Item, Product, Variant
- **Future phase:** 6.x (cart validation), 1.12

## 1.7-DEC-06 — Cart → Checkout as workflow, not permanent child

- **Decision:** Cart → Checkout is `OPERATIONAL_RELATIONSHIP` workflow (`Cart → Checkout → Order`), not a permanent PARENT_CHILD owning Order. Checkout is ephemeral, validated, creates Order+Payment.
- **Reason:** Multi-step atomic operation; failure between steps must not leave half-created Order; checkout is not a long-lived entity.
- **Affected resources:** Cart, Checkout, Order, Payment
- **Future phase:** 1.13 (checkout contract), 7.x (checkout)

## 1.7-DEC-07 — User → Orders as OWNERSHIP authorization boundary

- **Decision:** `User → Orders` is `OWNERSHIP` + authorization boundary: customer may retrieve only own Orders; staff/admin may traverse all with role checks. No `any authenticated user → any order`.
- **Reason:** Privacy (IDENT-006, api-exposure-classification); backend-authoritative ownership.
- **Affected resources:** User, Order
- **Future phase:** 1.15 (order contract), 9.x (order management), Group D (policies)

## 1.7-DEC-08 — Order → Order Items as PARENT_CHILD immutable

- **Decision:** Order owns Order Items as `PARENT_CHILD`; items are 1..n, immutable snapshots owned by Order, not independently addressable top-level customer resources.
- **Reason:** Historical order facts must remain authoritative (ORD-005, DATA-002); items cannot be edited after creation.
- **Affected resources:** Order, Order Item
- **Future phase:** 3.10 (order items snapshot), 9.x

## 1.7-DEC-09 — Order Item → Product/Variant as historical snapshot + optional current reference

- **Decision:** Order Item carries historical purchase snapshot (`name/SKU/price/qty/subtotal`) as authoritative; optional current catalog reference (`product_id/slug`) links to current Product/Variant if still exists/archived-tombstoned, without overwriting snapshot.
- **Reason:** Product may be renamed/repriced/deactivated after purchase; order must remain explainable six months later (historical-data-rules).
- **Affected resources:** Order Item, Product, Variant
- **Future phase:** 1.15, 3.10

## 1.7-DEC-10 — Order → Payment as separate transactional reference

- **Decision:** Order → Payment is separate `REFERENCE` (transactional, 0..n over retries), not collapsed as `Order.status = Payment.status`. Order owns relation, Payment owns confirmation state (PAY-001). Provider integration deferred to Group H.
- **Reason:** Payment state distinct from order state; webhook idempotent, provider-agnostic resource exists now.
- **Affected resources:** Order, Payment
- **Future phase:** 1.14 (payment contract), 8.x (payments)

## 1.7-DEC-11 — Order → Delivery/Fulfillment as conditional PARENT_CHILD

- **Decision:** `Order → Delivery` is `CONDITIONAL_REFERENCE` / `PARENT_CHILD` 0..1 only when `fulfillment=DELIVERY`; no Delivery for `PICKUP`. Delivery fee/address are projections equal to Order canonicals (Phase 1.5 correction).
- **Reason:** Delivery details are order-associated fulfillment, not required for pickup; conditional prevents requiring delivery data for pickup.
- **Affected resources:** Order, Delivery/Fulfillment, Order Address
- **Future phase:** 1.13 (checkout), 3.13 (delivery schema)

## 1.7-DEC-12 — Order → Tracking → Status History as controlled read-only subresources

- **Decision:** `Order → Tracking` is `PARENT_CHILD` read-oriented (canonical customer-facing timeline); `Tracking → Status History` is the canonical `PARENT_CHILD` append-only store for history. Alternative `Order → Status History` is not a separate public resource — it is a non-public equivalent alias to `Tracking → Status History` (same underlying records, exposed only via Tracking to avoid duplicate read surfaces and split ownership). Customer reads projection without `Operational note`; staff/admin reads full via Tracking. No free customer mutation.
- **Reason:** Powers customer tracking timeline + auditability (AGENTS.md §15, order-state-rules OS-026..029); history never mutated; single ownership semantics required for endpoint design.
- **Affected resources:** Order, Tracking, Status History
- **Future phase:** 1.16 (status history contract), 3.11/9.4/9.7

## 1.7-DEC-13 — Request → Product and Request/Enquiry → User as OPTIONAL_REFERENCE

- **Decision:** `Request → Product` optional (custom request may have no product); `Request → User` and `Enquiry → User` optional (anonymous allowed, User=none + contact info). `User → Requests/Enquiries` is optional ownership when linked.
- **Reason:** Anonymous workflows are first-class (Phase 1.1: anonymous request/enquiry). Do not make User mandatory parent.
- **Affected resources:** Request, Enquiry, Product, User
- **Future phase:** 1.17/1.18 (request/enquiry contracts), 10.x

## 1.7-DEC-14 — Attachments as PARENT_CHILD of Request or Enquiry with inherited authz

- **Decision:** `Request → Attachments` and `Enquiry → Attachments` are `PARENT_CHILD`; polymorphic owner is exactly one Request or Enquiry; access inherits owner authz; never global `GET /attachments/{id}` without owner context.
- **Reason:** Attachments have no independent business meaning; privacy (PRIVATE), security (SEC-002, ATTACH-003 type/size validation).
- **Affected resources:** Attachment, Request, Enquiry
- **Future phase:** 10.6 (attachment handling), 1.17/1.18

## 1.7-DEC-15 — User → Notifications as OWNERSHIP private recipient

- **Decision:** `User → Notifications` is `OWNERSHIP`, private to recipient; no cross-user query.
- **Reason:** NOTIF-001, privacy; lightweight in-app records (Group R for external delivery).
- **Affected resources:** User, Notification
- **Future phase:** Group R (Notifications) — Phase 18.x (Notification domain, email/in-app/push), 3.16 (Notification schema)

## 1.7-DEC-16 — No duplicate Admin/Customer resources

- **Decision:** Staff/Admin operate on same core resources (`Product`, `Order`, `Request`, etc.) via authorization/representation, not duplicate resources `AdminOrder/CustomerOrder`.
- **Reason:** AGENTS.md §27, Phase 1.6 §28; one domain resource, role-specific operations.
- **Affected resources:** All catalog/commerce/communication resources
- **Future phase:** Group D (authorization), Group K (admin operations)

## 1.7-DEC-17 — Embedding policy to avoid cycles and over/under-fetching

- **Decision:** Embed `Product → Images` FULL, `Product → Variants` SUMMARY (+ subresource), `Product → Category` SUMMARY, `Cart → Items` FULL, `Order → Items` FULL snapshots + optional current Product REFERENCE, `Order → Payment` LIMITED SUMMARY, `Order → Delivery` FULL when DELIVERY, `Tracking → Status History` FULL for staff/SUMMARY for customer; never recursively embed full parents (Product→Category→Products cycle broken via SUMMARY).
- **Reason:** Balance of preventing over-fetching (category not embedding all products full) and under-fetching (product detail single-call support for Next.js/Flutter).
- **Affected resources:** Product, Category, Order, Payment, Tracking, etc.
- **Future phase:** 1.9+, 1.11+ (catalog/order contracts), frontend phases

