# Resource Lifecycle Matrix — Version 1

## 1. Purpose
Documents whether each API resource is static/catalog/temporary/transactional/operational/communication/authentication and its conceptual lifecycle. Informs later endpoint design, caching, retention, and state-machine handling.

## 2. Matrix

| Resource | Lifecycle Class | Conceptual Lifecycle | Persistence / Retention |
|---|---|---|---|
| **Category** | CATALOG | Created → Active → (Inactive) → Retained (no destructive delete); flat single-level (DM-DEC-01). Ordering/display position staff-managed. | Historically retained; deactivation hides from public catalog but preserves for existing products. |
| **Product** | CATALOG | Draft → Active → Inactive (CAT-004); purchasability gated by active + type + variant state + inventory. Catalog visibility controls public listing. | Historically retained; must not be hard-deleted when referenced by Order Items (archival/tombstone; snapshots authoritative). |
| **Product Image** | CATALOG | Active → Inactive; may be removed from catalog (retained or deleted per later policy; not historical). | Retained while product active; removal does not affect order snapshots. |
| **Product Variant** | CATALOG | Active → Inactive (VAR-003); belongs to Product; may carry SKU/price override/inventory. | Retained; archival/tombstone if referenced by Order Items. |
| **Product Availability** (representation) | CATALOG (projection) | Derived from Inventory authoritative values; reflects available/unavailable at request time. Not persisted as independent record. | Computed on read; no separate retention. |
| **Inventory** | OPERATIONAL | Updated on stock operations; physical/reserved/available (available derived DM-DEC-03). No full movement history (DM-DEC-05). | Current authoritative quantities retained; history lightweight/deferred. |
| **Cart** | TEMPORARY | Created on first add → used until checkout or abandoned; guest or authenticated (IDENT-008). Associated to User on login. | Ephemeral; abandoned cart cleanup later policy (not Version 1 retention). No historical immutability. |
| **Cart Item** | TEMPORARY | Add → Update quantity → Remove; revalidated at checkout. | Lives with Cart; removed with Cart. |
| **Checkout** | TRANSACTION (workflow) | Ephemeral workflow: Cart → validation → fulfillment → pricing (backend) → order creation → payment initiation. Not a persisted long-lived entity. | Not persisted as separate record; results in Order + Payment. Idempotent handling later. |
| **Order** | TRANSACTION | `PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP/SHIPPED → (DELIVERED) → COMPLETED` or `→ CANCELLED` (VISION.md, order-state-rules.md). 20-min customer cancellation window. | Historically retained (immutable snapshots after finalization: totals, delivery fee, addresses). |
| **Order Item** | TRANSACTION | Immutable snapshot at order creation (name, SKU, price, qty, subtotal). | Historically retained, never updated. |
| **Order Address** | TRANSACTION | Transaction snapshot at checkout (billing required, delivery when DELIVERY). Canonical for delivery address (Delivery is projection). | Historically retained; immutable. |
| **Delivery / Fulfillment** | OPERATIONAL | Created with Order when DELIVERY; holds operational projections (fee, recipient, phone, address) plus operational status/notes. PICKUP has no Delivery record (fee 0). | Retained with Order; operational status evolves; fee/address projections immutable. |
| **Payment** | TRANSACTION | Created at initiation → verified via backend/provider callback → status distinct from order (PAY-001). One order may have multiple payment records (retries). | Historically retained; verification state backend-controlled; idempotent webhook handling. |
| **Order Tracking / Status History** | OPERATIONAL | Append-only per status change: previous/new status, time (backend-authoritative), actor, optional note (excluded from customer projection). | Historically retained, never mutated. |
| **Made-to-order Request** | COMMUNICATION | New → Contacted → Quoted → … (operational status per later request phase); captured as lead only (Decision 20: never becomes Order in V1). | Retained for staff history/operations. |
| **General Enquiry** | COMMUNICATION | Operational status; retained for staff history. Distinct from Request (ENQ-004, DM-DEC-09). | Retained per later retention policy. |
| **Attachment** | COMMUNICATION | Created with parent Request/Enquiry; validated/security-checked. | Retained with owner; private. |
| **User / Profile** | AUTHENTICATION / STATIC | Created at registration/admin creation → active/inactive/disabled → retained (not deleted). Role CUSTOMER/STAFF/ADMIN. | Historically retained; credentials never stored raw. |
| **Notification** | COMMUNICATION | Created on event → read/unread; lightweight in-app record; external email/push deferred (Group R). | Retained per recipient; lightweight. |
| **Authentication operations** | AUTHENTICATION | Registration → Login (session/token) → Logout; Password Recovery; Email Verification. Secure backend-controlled. | Session/token ephemeral; reset/verification secrets SENSITIVE, backend-only, short-lived. |

## 3. Lifecycle Categories (Phase 1.6 §33)
- **STATIC/REFERENCE** — User/Profile (static identity lifecycle: active/inactive/disabled; Role values CUSTOMER/STAFF/ADMIN are static set modeled via User).
- **CATALOG** — Category, Product, Product Image, Product Variant, Availability representation.
- **TEMPORARY** — Cart, Cart Item (ephemeral, bound to session/guest identity).
- **TRANSACTION** — Order, Order Item, Order Address, Payment, Checkout workflow (results in transaction).
- **OPERATIONAL** — Inventory, Delivery/Fulfillment, Order Tracking/Status History.
- **COMMUNICATION** — Request, Enquiry, Attachment, Notification.
- **AUTHENTICATION** — User/Profile (also STATIC) + Authentication operations.

## 4. Notes
- Transactions (Order family + Payment) preserve snapshots and are append-only/immutable after finalization → later physical design must protect via constraints/app logic.
- Catalog lifecycle allows deactivation without hard delete (preserves order snapshots).
- Temporary resources (Cart) have no historical retention requirement but need holder-scoped access and later abandoned-cart policy.
- Communication resources are operational leads, not transactions; no automatic conversion to Order (Decision 20).
