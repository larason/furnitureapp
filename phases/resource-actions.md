# Resource Actions — Version 1

## 1. Purpose
Identifies non-CRUD domain operations (business actions/workflows) that should not be forced into generic CRUD. These are backend-authoritative operations (AGENTS.md §7). No URLs or HTTP methods defined here; actions are domain behaviors to be mapped to resource operations in later phases. (Phase 1.6 §35)

---

## 2. Action Inventory

### Catalog

| Action | Resource | Actor | Description |
|---|---|---|---|
| Browse categories | Category | Anonymous/Customer | Public read, paginated |
| Browse products | Product | Anonymous/Customer | Discovery via catalog |
| View product details | Product (+ images/variants/availability) | Anonymous/Customer | Full product + embedded availability |
| Search / filter products | Product | Anonymous/Customer | By name/category/type/availability/price (no index commitment yet) |
| Manage categories | Category | Staff/Admin | Create/update/deactivate |
| Manage products | Product | Staff/Admin | Create/update/deactivate/archive |
| Manage product images | Product Image | Staff/Admin | Add/order/remove via Product |
| Manage product variants | Product Variant | Staff/Admin (Admin for price/SKU) | Create/update/deactivate via Product |
| Adjust inventory | Inventory | Staff/Admin | Read stock, transactional adjust; public never adjusts |

### Commerce — Cart & Checkout

| Action | Resource | Actor | Description |
|---|---|---|---|
| Create/get cart | Cart | Guest holder / Customer | Implicit on first add; holder-scoped get |
| Add item to cart | Cart Item (via Cart) | Guest holder / Customer | Validates product/variant active + purchasable |
| Update cart item quantity | Cart Item | Guest holder / Customer | Revalidate purchasability/stock |
| Remove cart item | Cart Item | Guest holder / Customer |  |
| Clear cart | Cart | Guest holder / Customer | Abandon/remove all |
| **Checkout** | Checkout workflow | Customer (authenticated) | Validates cart, fulfillment, addresses, resolves delivery fee, computes authoritative totals, creates Order + Payment (pending). Anonymous rejected. |
| Select fulfillment | Order (fulfillment type) | Customer (at checkout) | Choose PICKUP (free) or DELIVERY (with address); variable staff-controlled fee resolved at checkout |

### Commerce — Order

| Action | Resource | Actor | Description |
|---|---|---|---|
| View own orders | Order | Customer (own) | List own orders |
| View order details | Order (+ items/addresses/payment/tracking) | Customer (own) / Staff/Admin (all) | Snapshot presentation |
| Track order | Order Tracking | Customer (own) / Staff/Admin | Timeline: current + history (customer projection excludes Operational note) |
| Cancel own order | Order → CANCELLED | Customer (own, within 20-min backend-evaluated window) | Controlled cancellation; refund separate (later Group H) |
| Cancel order (staff/admin) | Order → CANCELLED | Staff/Admin | Different authorization, not 20-min bound (Group D) |
| **Accept order** | Order | Staff/Admin | Validates transition: PAID → ACCEPTED (Order must already be PAID via Confirm payment; payment-state transition is not performed here) |
| **Mark processing** | Order | Staff/Admin | ACCEPTED → PROCESSING |
| **Mark ready for pickup** | Order | Staff/Admin | PROCESSING → READY_FOR_PICKUP (pickup only; pickup never SHIPPED FUL-005) |
| **Ship order** | Order | Staff/Admin | PROCESSING → SHIPPED (delivery only) |
| **Mark delivered** | Order | Staff/Admin | SHIPPED → DELIVERED (delivery) |
| **Mark completed** | Order | Staff/Admin (or system) | READY_FOR_PICKUP→COMPLETED (pickup) / DELIVERED→COMPLETED (delivery) |

*Status transitions are backend-validated; clients never invent/force arbitrary jumps (AGENTS.md §4.4). Full exhaustive matrix deferred, but primary paths + invalid-transition principles fixed (order-state-rules.md).*

### Commerce — Payment

| Action | Resource | Actor | Description |
|---|---|---|---|
| Initiate payment | Payment (via Order/Checkout) | Customer (own order) | Create payment intent for PENDING_PAYMENT order |
| Confirm payment | Payment | System (backend-verified provider callback/webhook) | Backend verifies provider, updates Payment + Order (PAID); idempotent, never client `payment_status=success` |
| Handle payment callback/webhook | Payment | Provider → Backend | Idempotent verification; duplicate callbacks must not duplicate orders/payments |

*Provider selection/fields deferred to Group H; resource exists conceptually as provider-agnostic.*

### Commerce — Delivery / Fulfillment

| Action | Resource | Actor | Description |
|---|---|---|---|
| View delivery info | Delivery (via Order) | Customer (own) / Staff/Admin | Fulfillment type, fee projection, address projection, status |
| Update delivery status/notes | Delivery | Staff/Admin | Operational updates (e.g., delivery notes, internal status). Fee/address are projections, never independently edited. |

### Customer Communication

| Action | Resource | Actor | Description |
|---|---|---|---|
| **Submit made-to-order request** | Made-to-order Request | Anonymous (with contact) / Customer | Includes quantity/dimensions/material/color/notes + optional attachment; lead only, never Order (Decision 20) |
| View own requests | Request | Customer (own) / Staff/Admin (all) |  |
| **Manage request status** | Request | Staff/Admin | Operational status (New→Contacted→Quoted→…) |
| **Submit general enquiry** | General Enquiry | Anonymous (with contact) / Customer | Message + contact + optional attachment |
| View own enquiries | Enquiry | Customer (own) / Staff/Admin |  |
| **Manage enquiry status** | Enquiry | Staff/Admin | Operational status |
| Add/view attachment | Attachment (via Request/Enquiry) | Anonymous (with parent) / Customer via owner / Staff/Admin via owner | Owner-scoped; type/size/security validated |

### Account / Identity

| Action | Resource | Actor | Description |
|---|---|---|---|
| Register | Authentication | Anonymous | Create User (Customer) |
| Login | Authentication | Anonymous → Authenticated | Create session/token |
| Logout | Authentication | Authenticated | Destroy session/token |
| Password recovery | Authentication | Anonymous/Authenticated | Secure backend-controlled request+reset (delivery mechanism deferred to auth phases) |
| Email verification | Authentication | Authenticated | Verify contact (secure backend-controlled) |
| View own profile | User/Profile | Customer (own) / Staff own / Admin |  |
| Update own profile | User/Profile | Customer (own) / Staff own / Admin | Name, contact, etc. (not credentials via profile) |
| View own notifications | Notification | Customer (own) |  |
| Mark notification read/unread | Notification | Customer (own) |  |

---

## 3. Why Actions (Not Generic CRUD)

- **Checkout** — coordinates validation/pricing/fulfillment/order+payment creation atomically; not `POST /orders` with client total.
- **Order status transitions** (accept/process/ready/ship/deliver/complete/cancel) — backend state machine, not `PATCH /orders {status: "SHIPPED"}`.
- **Payment confirm** — backend-verified, not client field update.
- **Submit request/enquiry** — creates a lead, not an order; anonymous allowed.
- These map to resource operations/subresources in later endpoint design (e.g., checkout workflow action, status transition actions), preserving AGENTS.md §7 backend authority.

## 4. Non-Actions (Explicitly NOT Business Actions)

- Editing order items after creation, editing snapshot prices/totals, editing delivered fee projection independently, merging Request→Order, directly managing Inventory as customer, exposing payment secrets — all prohibited; would violate invariants.

## 5. Security Note

Every action marked Customer Own or Staff/Admin is backend-authorized; frontend route protection is never the final authority (AGENTS.md §17). Private resources require ownership checks; privileged operations require role checks (Group D).
