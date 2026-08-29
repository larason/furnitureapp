# Resource Ownership — Version 1

## 1. Purpose
Central reference for which domain owns each API resource. One clear authoritative owner per business fact (Phase 1.6 §32, `data-ownership.md`). Informs authorization and later endpoint structure.

## 2. Ownership Matrix

| Resource | Authoritative Domain | Notes |
|---|---|---|
| **Category** | Catalog | Owns grouping/SEO info; flat single-level. |
| **Product** | Catalog | Owns name/slug/description/price/visibility/type. |
| **Product Image** | Catalog (via Product) | Belongs to Product. |
| **Product Variant** | Catalog (via Product) | Belongs to Product; purchasable unit when present. |
| **Product Availability** (representation) | Inventory → Catalog | Authoritative quantities in Inventory; presentation via Catalog/Product. |
| **Inventory** | Inventory | Owns current stock (physical/reserved/available derived). |
| **Cart** | Commerce — Cart | Owns temporary collection; holder-scoped (guest or User). |
| **Cart Item** | Commerce — Cart | Belongs to Cart. |
| **Checkout** | Commerce — Checkout (workflow) | Coordinated by Commerce, validated by Catalog/Inventory/Payment. |
| **Order** | Commerce — Order | Core transaction aggregate. |
| **Order Item** | Commerce — Order | Belongs to Order (historical snapshot, owned by Order domain). |
| **Order Address** | Commerce — Order | Belongs to Order; transaction-time addresses (canonical owner of delivery address snapshot). |
| **Order Status / Tracking** | Commerce — Order Operations | Current status owned by Order. |
| **Order Status History** | Commerce — Order Operations | Belongs to Order (append-only). |
| **Payment** | Commerce — Payment | Payment owns confirmation state; Order owns relation to payment. Distinct from order state. |
| **Delivery / Fulfillment** | Commerce — Fulfillment | Belongs to Order (when DELIVERY); operational projections of Order fee/address. |
| **Delivery Fee (snapshot)** | Commerce — Order | Canonical on Order (in authoritative total); Delivery is projection. |
| **Made-to-order Request** | Customer Communication | Owns request state; may reference User/Product but not owned by them. |
| **General Enquiry** | Customer Communication | Owns enquiry state; may reference User. |
| **Attachment** | Customer Communication | Belongs to one Request or one Enquiry. |
| **User / Profile** | Identity | Single User concept for customer/staff/admin; role controls authorization. |
| **Notification** | Supporting / Notification | Belongs to recipient User; references related entity/event. |
| **Authentication** | Identity | Owns credentials/sessions/tokens (SENSITIVE, never in resource projection). |

## 3. Ownership Rules

- **One source of truth:** Each business fact has a clear owner; avoid storing the same mutable fact in multiple unrelated places unless it is a deliberate historical snapshot (see Delivery). (Phase 1.4 §5.1, 1.6 §32, data-ownership.md)
  - Current product price/description → Catalog.
  - Current stock → Inventory.
  - Historical purchase price/fee/address → Order / Order Item / Order Address (snapshots, immutable).
  - Current order status → Order; history → Order Status History.
  - Final delivery fee → Order (canonical snapshot in authoritative total); Delivery fee is projection, never independently edited.
  - Payment confirmation/status → Payment (distinct from Order status).
  - Request/Enquiry state → their own domains (never Order).
- **Derived facts:**
  - Available stock = Inventory physical − reserved (computed, may be persisted as snapshot at order time where needed).
  - Order subtotal = Σ line subtotals; final total = subtotal + delivery fee (persisted snapshot at finalization, owned by Order).
- **Single owner for delivery snapshots (Phase 1.5 correction):**
  - Delivery fee canonical on Order; Delivery carries non-authoritative projection (equal, never independently edited).
  - Delivery address/recipient/phone canonical on Order Address (delivery, 0..1); Delivery carries non-authoritative projections (equal, never independently edited).
  - Consistency rule: projections copied once at Delivery creation from authoritative Order values; immutable thereafter.
- **Cart vs Order:** Cart is temporary, not a reservation, not an order; Order is the committed transaction. Cart price never authoritative; final pricing owned by Order/backend at checkout (CART-005, CHECKOUT-003).
- **Request/Enquiry vs Order:** Requests/Enquiries own their state and are separate from Orders; neither becomes an order (REQ-004/005, ENQ-003, Decision 20).
- **Catalog vs Inventory:** Product describes what is sold; Inventory describes how many are available; availability representation derived for public view.

## 4. Implications for API Design

- **Authorization follows ownership:** Customer owns own Cart/Orders/Notifications/own Requests-Enquiries; Staff/Admin operate on all Orders/Requests/Enquiries/Inventory/Catalog per Group D policies.
- **Same domain resource, different operations by role** (Phase 1.6 §28): Product read is public, management is admin; Order read-own is customer, operational is staff/admin — same resource, role-scoped operations, not duplicated resources (`customer-orders` vs `admin-orders` anti-pattern).
- **Parent/subresource ownership drives nesting** (Phase 1.7): Product owns Images/Variants; Order owns Items/Addresses/Tracking/Delivery/Payment; Request/Enquiry owns Attachments; User owns Notifications.
