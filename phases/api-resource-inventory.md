# API Resource Inventory — Version 1

## 0. Purpose
Master inventory of API resources derived from the frozen logical data model (`logical-data-model-v1.md` v1.0 FROZEN, `freeze-record.md`). Classifies what the API exposes, who can access it, and the responsibility of each resource. No endpoint URLs, HTTP methods, or JSON schemas are defined here (Phase 1.9+).

**Authoritative inputs:** `docs/VISION.md`, `AGENTS.md`, `phase-1.1-api-scope.md` → `phase-1.4.md`, `logical-data-model-v1.md`, supporting 1.4/1.5 docs.
**Principle:** Backend is authority (AGENTS.md §3); DB is source of truth; resources represent business concepts, not tables.

## 1. Classification Legend
- **PUBLIC API RESOURCE** — openly readable, no auth (catalog).
- **AUTHENTICATED API RESOURCE** — requires auth, customer-owned.
- **STAFF API RESOURCE** — staff operational scope.
- **ADMIN API RESOURCE** — admin operational scope.
- **INTERNAL API CONCEPT** — exists in domain/logic, not directly addressable by clients.
- **SUBRESOURCE/EMBEDDED RESOURCE** — child of a primary resource, not top-level independently.
- **REFERENCE-ONLY RESOURCE** — read-only projection/reference.

Exposure policy (Phase 1.6 §30): `PUBLIC_READ`, `CUSTOMER_OWNED`, `STAFF_OPERATIONAL`, `ADMIN_OPERATIONAL`, `INTERNAL_ONLY`.
Canonical values are those five; the following are defined shorthands/derived values used in this inventory:
- `ANONYMOUS_CREATE` — anonymous create-only (no read/list) for Request/Enquiry creation and Registration; not `PUBLIC_READ`.
- `PUBLIC` — shorthand for `PUBLIC_READ`.
- `STAFF/ADMIN_OPERATIONAL` — shorthand for `STAFF_OPERATIONAL + ADMIN_OPERATIONAL` (both staff and admin operational access).

## 2. Resource Inventory

### 2.1 Public Catalog

#### Category
- **Purpose:** Browseable grouping for product discovery, SEO category pages, catalog organization (VISION.md: admin panel Products/Available/Made to Order; docs/VISION recommends category as navigation).
- **Domain:** Catalog
- **Classification:** PUBLIC API RESOURCE (Primary)
- **Lifecycle:** CATALOG (active/inactive, flat single-level DM-DEC-01)
- **Ownership:** Catalog
- **Access scope:** Anonymous Read, Customer Read, Staff Manage, Admin Manage
- **Exposure:** PUBLIC_READ (read), STAFF/ADMIN_OPERATIONAL (manage)
- **CRUD/Actions:** Create (Staff/Admin), Read (Public), Update (Staff/Admin), Delete → archival/deactivation (not destructive)
- **Parent/Subresource:** None (standalone); parent of Product
- **Public exposure:** Yes — name, slug, description, image ref, active state. No internal notes.
- **Sensitive data:** None. Slug is global unique, public.

#### Product
- **Purpose:** Primary furniture item customers discover; depending on `IN_STOCK` vs `MADE_TO_ORDER`, drives Buy vs Request flow (VISION.md three actions).
- **Domain:** Catalog
- **Classification:** PUBLIC API RESOURCE (Primary)
- **Lifecycle:** CATALOG
- **Ownership:** Catalog
- **Access scope:** Anonymous Read, Customer Read, Staff Manage, Admin Manage
- **Exposure:** PUBLIC_READ + STAFF/ADMIN_OPERATIONAL (manage)
- **CRUD/Actions:** Create/Update (Staff/Admin), Read (Public), Delete→archival, Submit request (separate Request resource for MADE_TO_ORDER)
- **Parent/Subresource:** Belongs to Category; owns Product Image, Variant, Availability projection
- **Public exposure:** identity, name, slug, description, category, type, pricing (base price or variant override), images, variants, availability, visibility, SEO fields. Not: internal adjustment history, supplier info, staff notes.
- **Sensitive data:** None public; price/availability are PUBLIC_READ but backend-computed.

#### Product Image
- **Purpose:** Visual representation as part of product; furniture is visual purchase.
- **Domain:** Catalog
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Product
- **Lifecycle:** CATALOG
- **Ownership:** Catalog (via Product)
- **Access scope:** Read via Product (Anonymous/Customer), Manage via Product (Staff/Admin)
- **Exposure:** PUBLIC_READ (via product representation)
- **CRUD/Actions:** Managed under Product via admin operations; not independently top-level for customers (Read via product). Admin: Create/Update/Delete (ordering, primary flag, alt text) under Product.
- **Parent/Subresource:** Product → Product Images
- **Public exposure:** Yes via Product (location ref, display order, primary flag, alt text). No binary storage internals.

#### Product Variant
- **Purpose:** Purchasable variation (color/material/size/configuration) of a product; when present, the purchasable unit (Domain Invariant).
- **Domain:** Catalog
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Product
- **Lifecycle:** CATALOG
- **Ownership:** Catalog (via Product)
- **Access scope:** Read via Product (Anonymous/Customer), Manage via Product (Staff/Admin)
- **Exposure:** PUBLIC_READ (via product), STAFF/ADMIN_OPERATIONAL (manage)
- **CRUD/Actions:** Create/Update (Staff/Admin under Product), Read (Public via Product), Variant must never escape owning Product (invariant: Variant belongs to Product)
- **Parent/Subresource:** Product → Variants
- **Public exposure:** name, SKU, price override, attributes, active state. Not internal inventory internals.
- **Sensitive data:** None.

#### Product Availability (Representation)
- **Purpose:** Customer-facing purchasability signal derived from Inventory; distinct from internal stock quantities.
- **Domain:** Catalog/Inventory
- **Classification:** REFERENCE-ONLY RESOURCE
- **Lifecycle:** CATALOG projection
- **Ownership:** Inventory (authoritative quantities) → Catalog presentation
- **Access scope:** Limited Read (Anonymous/Customer via Product), Manage (Staff/Admin via Inventory)
- **Exposure:** PUBLIC_READ (limited: available/unavailable, stock indicator; e.g., IN STOCK / Only 2 left / Made to Order)
- **CRUD/Actions:** Read-only for customers; no direct Create/Update/Delete by customers. Staff/Admin adjust via Inventory resource.
- **Parent/Subresource:** Product or Product Variant → Availability (embedded)
- **Public exposure:** Limited to derived signal; must not expose reserved quantity, internal adjustments, staff-only notes.
- **Sensitive data:** Internal quantities are INTERNAL_ONLY.

#### Inventory
- **Purpose:** Authoritative stock: physical, reserved, available (AGENTS.md §12). Prevents overselling.
- **Domain:** Inventory
- **Classification:** INTERNAL API CONCEPT
- **Lifecycle:** OPERATIONAL
- **Ownership:** Inventory
- **Access scope:** Anonymous No, Customer No, Staff Manage, Admin Manage
- **Exposure:** INTERNAL_ONLY (quantities) + STAFF/ADMIN_OPERATIONAL (management operations)
- **CRUD/Actions:** No customer CRUD. Staff/Admin: Read stock, Adjust stock (increment/decrement, transactional), View derived availability. No public endpoint.
- **Parent/Subresource:** Product or Variant (one-to-one for purchasable unit)
- **Public exposure:** No. Customer sees Availability representation only.
- **Sensitive data:** Physical/reserved quantities are INTERNAL.

### 2.2 Customer Commerce

#### Cart
- **Purpose:** Maintain customer's current purchase selection prior to checkout (temporary, not an order, not a reservation).
- **Domain:** Commerce
- **Classification:** AUTHENTICATED API RESOURCE — a valid `GUEST_TOKEN` holder satisfies authentication for this resource (guest-cart per DM-DEC-04, IDENT-008/CART-002); no unauthenticated without token.
- **Lifecycle:** TEMPORARY
- **Ownership:** Customer (User or guest identity); binds to User on authentication (IDENT-008, DM-DEC-04)
- **Access scope:** Anonymous via `GUEST_TOKEN` holder (Create/Read/Update own) where principal = `GUEST_TOKEN` bearer, Customer Own where principal = authenticated User (owns cart), Staff No, Admin No
- **Exposure:** CUSTOMER_OWNED (holder-scoped: principal is `GUEST_TOKEN` holder or authenticated owner)
- **CRUD/Actions:** Create (implicit on first add), Read own, Update via items, Delete→clear/abandon. No staff/admin access. Checkout→creates Order.
- **Parent/Subresource:** User (optional) → Cart; owns Cart Items
- **Public exposure:** No (private, holder-scoped)
- **Sensitive data:** PRIVATE (cart contents).

#### Cart Item
- **Purpose:** Single line: product/variant + quantity in cart.
- **Domain:** Commerce
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Cart — inherits Cart authentication (valid `GUEST_TOKEN` holder satisfies)
- **Lifecycle:** TEMPORARY
- **Ownership:** Commerce (via Cart → Customer/guest)
- **Access scope:** Via Cart where principal = `GUEST_TOKEN` holder (anonymous guest) or authenticated User (customer own)
- **Exposure:** CUSTOMER_OWNED (via Cart, holder-scoped: `GUEST_TOKEN` holder or authenticated owner)
- **CRUD/Actions:** Create (add), Update quantity, Delete (remove). Revalidated at checkout; not independent resource.
- **Parent/Subresource:** Cart → Items (each identifies product/variant, quantity)
- **Public exposure:** No.

#### Checkout (Workflow Resource)
- **Purpose:** Workflow operation coordinating Cart → validation → fulfillment selection → pricing (backend-authoritative) → order creation → payment initiation (AGENTS.md §13). Not a long-lived entity.
- **Domain:** Commerce
- **Classification:** INTERNAL API CONCEPT
- **Lifecycle:** TRANSACTION (ephemeral operation, results in Order + Payment)
- **Ownership:** Commerce (Customer-initiated, backend-executed)
- **Access scope:** Customer Own (authenticated only; anonymous checkout rejected AGENTS.md §13), Staff/Admin No (customer action)
- **Exposure:** CUSTOMER_OWNED (authenticated)
- **CRUD/Actions:** Not CRUD; operation: Initiate checkout (validates cart, resolves delivery fee from staff rules by location, computes totals, creates Order in PENDING_PAYMENT). Details deferred to checkout contract (Phase 1.13).
- **Parent/Subresource:** Cart as input; creates Order
- **Public exposure:** No (authenticated workflow)
- **Sensitive data:** Totals/fee are backend-computed; client totals never trusted.

#### Order
- **Purpose:** Confirmed commerce record; core for fulfillment, payment, tracking. Separate from Request/Enquiry/Cart/Payment.
- **Domain:** Commerce (Order domain per logical model)
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** TRANSACTION (historically retained, immutable snapshots post-finalization)
- **Ownership:** Customer/Commerce (customer owns own orders)
- **Access scope:** Anonymous No, Customer Own (read own), Staff Operational, Admin Operational
- **Exposure:** CUSTOMER_OWNED (own orders) + STAFF/ADMIN_OPERATIONAL (all orders), INTERNAL projection rules for sensitive fields
- **CRUD/Actions:** Create → Checkout workflow only (not direct POST /orders by customer without checkout), Read own (Customer) / Read all (Staff/Admin), Update → controlled status operations only (not arbitrary field edits), Delete → No ordinary delete (→ CANCELLED or archival), Cancel → Controlled cancellation (20-min window, backend-evaluated)
- **Parent/Subresource:** User → Orders; owns Order Item, Order Address, Delivery (when DELIVERY), Payment(s), Status History/Tracking
- **Public exposure:** No. Exposes to owner: reference OD-..., items snapshots, quantities, price snapshots, subtotal/delivery fee/total (snapshots), fulfillment type, currency, status, history projection, payment summary, addresses (owner projection).
- **Sensitive data:** PRIVATE (order data). Canonical owner of delivery fee (Order.Delivery fee); Delivery projection equal. Staff Operational note excluded from customer timeline.

#### Order Item
- **Purpose:** Historical snapshot of purchased product/variant (name, SKU, unit price, qty, subtotal) — preserved even if catalog changes (AGENTS.md §11).
- **Domain:** Commerce
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Order
- **Lifecycle:** TRANSACTION (immutable)
- **Ownership:** Order
- **Access scope:** Via Order (Customer Own, Staff/Admin Operational)
- **Exposure:** CUSTOMER_OWNED via Order
- **CRUD/Actions:** No independent CRUD; created with Order, never updated/deleted. Read via Order.
- **Parent/Subresource:** Order → Order Items
- **Public exposure:** No (via owner order)

#### Order Address
- **Purpose:** Transaction-time address snapshots for an order; billing required, delivery conditional (when DELIVERY). Canonical owner of delivery address/recipient/phone snapshot (Delivery holds non-authoritative projections).
- **Domain:** Commerce — Order
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Order
- **Lifecycle:** TRANSACTION (immutable snapshot at checkout)
- **Ownership:** Commerce — Order (belongs to Order; ADDR-003 distinct billing/delivery)
- **Access scope:** Anonymous No, Customer Own via Order (billing required; delivery when DELIVERY), Staff Operational via Order, Admin Operational via Order
 - **Exposure:** CUSTOMER_OWNED via Order + STAFF/ADMIN_OPERATIONAL (billing exactly 1, delivery 0..1 only when fulfillment=DELIVERY; absent for PICKUP).
- **CRUD/Actions:** No independent CRUD; created with Order at checkout (billing exactly 1 + conditional delivery from checkout input), Read via Order, No Update/Delete independent (immutable historical snapshot). Not a saved-address book (ADDR-001 deferred).
- **Parent/Subresource:** Order → Order Addresses (billing exactly 1, delivery 0..1 only when fulfillment=DELIVERY; absent for PICKUP)
- **Public exposure:** No (via owner order). Contains address fields + required recipient/contact for delivery.
- **Sensitive data:** PRIVATE (address data); owner-scoped, never cross-customer. Retains transaction-time data even if product/customer data changes.

#### Delivery / Fulfillment
- **Purpose:** Fulfillment record for delivery orders (PICKUP free, DELIVERY staff-controlled fee). Holds operational projections of delivery fee/address/recipient (canonical on Order/Order Address).
- **Domain:** Fulfillment
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Order
- **Lifecycle:** OPERATIONAL
- **Ownership:** Fulfillment (per Order)
- **Access scope:** Anonymous No, Customer Own (via Order), Staff Operational, Admin Operational
- **Exposure:** CUSTOMER_OWNED (own) + STAFF/ADMIN_OPERATIONAL; internal projection rule documented
- **CRUD/Actions:** Created with Order when fulfillment=DELIVERY; Read via Order; Update → operational status/notes (Staff/Admin), not fee/address independently (canonical on Order). No Delete. PICKUP needs no Delivery record (fee 0, no address).
- **Parent/Subresource:** Order → Delivery (0..1 conditional on DELIVERY)
- **Public exposure:** No. Delivery fee/address are projections, equal to Order canonical values.

#### Payment
- **Purpose:** Financial transaction associated with Order; state distinct from order state (AGENTS.md §14, PAY-001).
- **Domain:** Commerce/Payment
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** TRANSACTION
- **Ownership:** Commerce/Payment (Order owns relation, Payment owns confirmation state)
- **Access scope:** Anonymous No, Customer Limited own (own payment status/amount, not secrets), Staff Operational (verify/status), Admin Operational
- **Exposure:** CUSTOMER_OWNED (limited: status, amount, currency, verification outcome) + STAFF/ADMIN_OPERATIONAL, INTERNAL_ONLY (provider secrets, verification internals, provider payloads)
- **CRUD/Actions:** Create → checkout/payment initiation (backend), Read own/limited, Update → backend-verified only (webhook, never client `payment_status=success`), No customer Delete. Provider integration deferred to Group H but resource exists conceptually now.
- **Parent/Subresource:** Order → Payments (one-to-many, retries/failures)
- **Public exposure:** No provider internals to client.

#### Order Tracking / Status History
- **Purpose:** Read-oriented tracking timeline for customers; auditability for staff. Simple status tracking, not GPS (VISION.md, AGENTS.md §15).
- **Domain:** Commerce (Order Operations)
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Order (read-only for customers)
- **Lifecycle:** OPERATIONAL (append-only)
- **Ownership:** Order
- **Access scope:** Anonymous No, Customer Own (projection excludes staff Operational note), Staff Operational (full with notes), Admin Operational (full)
- **Exposure:** CUSTOMER_OWNED (timeline projection without Operational note) + STAFF/ADMIN_OPERATIONAL (full)
- **CRUD/Actions:** Customer: Read tracking/timeline only. Staff/Admin: Append via controlled order actions (accept/process/ready/ship/deliver/complete/cancel) — creates Status History record (prev/new status, time backend-authoritative, actor). No free edit/delete of history.
- **Parent/Subresource:** Order → Tracking → Status History
- **Public exposure:** No. Staff notes INTERNAL/STAFF only.

### 2.3 Customer Communication

#### Made-to-order Request
- **Purpose:** Lead requesting manufacture of furniture (IN_STOCK vs MADE_TO_ORDER distinction; Request ≠ Order).
- **Domain:** Customer Communication / Sales
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** COMMUNICATION (operational: New → Contacted → Quoted → ... defined later)
- **Ownership:** Customer Communication (may associate to User if authenticated)
- **Access scope:** Anonymous Create, Customer Own (view own if authenticated-linked), Staff Manage, Admin Manage
 - **Exposure:** ANONYMOUS_CREATE (create-only, no read/list) + CUSTOMER_OWNED (own) + STAFF/ADMIN_OPERATIONAL (all); anonymous read/list denied — private lead records not exposed. May reference Product optionally.
- **CRUD/Actions:** Create (Anonymous with contact + Authenticated), Read own / Manage (Staff/Admin), Update status (Staff/Admin operational), No customer Update after submission (except via staff workflow). No Delete (retained as lead). Attachment via subresource.
- **Parent/Subresource:** Optional User, Optional Product → Request → Attachments
- **Public exposure:** Creation is public (create-only); read/list is owner/staff only — no anonymous read.

#### General Enquiry
- **Purpose:** General customer communication not necessarily tied to a catalog product; separate from Request (VISION.md: custom 6-seater table example).
- **Domain:** Customer Communication
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** COMMUNICATION
- **Ownership:** Customer Communication
- **Access scope:** Anonymous Create, Customer Own (if linked), Staff Manage, Admin Manage
- **Exposure:** ANONYMOUS_CREATE (create-only, no read/list) + CUSTOMER_OWNED (own) + STAFF/ADMIN_OPERATIONAL (all); anonymous read/list denied. Same as Request.
- **CRUD/Actions:** Create (Anonymous/Auth), Read own/Manage, Update status (Staff/Admin). Distinct from Request, never merged.
- **Parent/Subresource:** Optional User → Enquiry → Attachments
- **Public exposure:** Creation public; rest restricted.

#### Attachment
- **Purpose:** Optional file on Request or Enquiry (sketches/photos/docs).
- **Domain:** Customer Communication
- **Classification:** SUBRESOURCE/EMBEDDED RESOURCE of Request or Enquiry
- **Lifecycle:** COMMUNICATION (created with parent, validated, security-checked)
- **Ownership:** Request or Enquiry (exactly one polymorphic owner)
- **Access scope:** Via owner (Anonymous creator via request/enquiry context? No direct global access; inherits owner auth), Customer via owner, Staff/Admin Manage
- **Exposure:** CUSTOMER_OWNED via owner + STAFF/ADMIN; served with owner/role check, not public URL. INTERNAL_ONLY for storage internals.
- **CRUD/Actions:** Create with parent or add to existing (if allowed), Read via owner, No independent update, Delete → later policy (if needed). Security: type/size/malware checks.
- **Parent/Subresource:** Request or Enquiry → Attachments
- **Public exposure:** No.

### 2.4 Customer Account

#### User / Profile
- **Purpose:** Account identity, profile, contact, account state for Customer/Staff/Admin (single User concept, AGENTS.md §5).
- **Domain:** Identity
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** AUTHENTICATION / STATIC (account persists)
- **Ownership:** Identity
- **Access scope:** Anonymous No (except register/login), Customer Own (view/update own profile), Staff Own/Restricted, Admin Manage (all users/role per Group D)
- **Exposure:** CUSTOMER_OWNED (own profile), ADMIN_OPERATIONAL (user management per Group D), INTERNAL_ONLY (credentials never exposed)
- **CRUD/Actions:** Register (public), Read own, Update own (profile), No direct Delete by customer (deactivation). Credentials: separate auth operations (register/login/logout/recovery/verification). Role is Staff/Admin managed.
- **Parent/Subresource:** None (top-level); owns Cart, Orders, Requests, Enquiries, Notifications
- **Sensitive data:** PRIVATE (identity), SENSITIVE (credentials — never in response; hash only backend).

#### Notification
- **Purpose:** Application-event alert record tied to order/request/enquiry events; does not drive state machine (NOTIF-001). Lightweight in-app records; external email/push deferred to Group R.
- **Domain:** Account / Supporting
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** COMMUNICATION
- **Ownership:** User (recipient)
- **Access scope:** Anonymous No, Customer Own (recipient), Staff N/A, Admin Administrative (system)
- **Exposure:** CUSTOMER_OWNED (recipient only)
- **CRUD/Actions:** Create → system (on events, not customer), Read own, Update → read/unread state, Delete → later policy. No customer creation of notifications.
- **Parent/Subresource:** User → Notifications (related entity reference optional)
- **Public exposure:** No.

#### Authentication Resources (Group)
- **Purpose:** Operations: Registration, Login, Logout, Password Recovery, Email Verification, Session/Token. Not ordinary CRUD resources.
- **Domain:** Identity
- **Classification:** AUTHENTICATED API RESOURCE
- **Lifecycle:** AUTHENTICATION
- **Ownership:** Identity
- **Access scope:** Registration/Login/Recovery → Anonymous (public), Logout/Verification → Authenticated, Session/Token → Authenticated
- **Exposure:** PUBLIC (register/login) + CUSTOMER_OWNED (own session), INTERNAL_ONLY (hashes, reset secrets, provider internals)
- **CRUD/Actions:** Register (create account), Login (create session/token), Logout (destroy), Password Recovery (request+reset via secure backend), Email Verification (confirm). Detailed contract deferred to auth phases.
- **Parent/Subresource:** User as principal; not nested under User resource
- **Sensitive data:** SENSITIVE — never expose raw credentials, reset tokens, or allow client-side password reset secrets.

### 2.5 Administration

- **Principle (Phase 1.6 §28):** Same domain resources with role-specific operations, not duplicated `AdminProduct` vs `CustomerProduct`. Customer sees read projection, Admin sees management representation of the same Product domain resource. Authorization via Group D policies.
- **Admin capabilities map to resources:** Product/Category/Image/Variant → Catalog management; Inventory → stock management; Order (+ Tracking/Delivery/Payment) → order operations; Request/Enquiry/Attachment → sales operations; User/Profile → customer/staff management.

## 3. Sensitive Data Considerations (per resource)

- **Category/Product/Variant/Image:** PUBLIC; safe to cache, SEO-friendly. No internal notes exposed.
- **Availability vs Inventory:** Availability LIMITED public; Inventory INTERNAL_ONLY. Never leak physical/reserved quantities.
- **Cart/CartItem:** PRIVATE, holder-scoped (GUEST_TOKEN vs AUTHENTICATED). No cross-customer access.
- **Order/OrderItem/OrderAddress/Delivery/Payment:** PRIVATE/SENSITIVE; owner-scoped + staff. Order reference PRIVATE (owner/STAFF/ADMIN). Payment secrets INTERNAL_ONLY.
- **Status History:** INTERNAL/PRIVATE; actor/note INTERNAL unless explicitly allowed; owner timeline excludes Operational note.
- **Request/Enquiry/Attachment:** PRIVATE; anonymous creation collects contact info but creation endpoint must not leak other users' records.
- **User/Profile:** PRIVATE/SENSITIVE; credentials never in responses.
- **Notification:** PRIVATE; recipient-only.

## 4. Resource Relationship Preview
Conceptual only (detailed in Phase 1.7):
- Catalog: Category → Products → {Images, Variants → Availability}, Availability
- Commerce: Cart → Cart Items → Product/Variant; Order → {Order Items, Order Addresses (billing/delivery), Payment(s), Delivery, Tracking→Status History}
- Communication: Request → Attachments; Enquiry → Attachments
- Account: User → {Cart, Orders, Requests, Enquiries, Notifications}

## 5. Validation Notes
- No new entities introduced beyond frozen logical model (one model → one resource inventory).
- Guest cart holder-scoped via GUEST_TOKEN distinct from AUTHENTICATED (DM-DEC-04).
- Payment provider deferred (Group H) but resource exists conceptually as provider-agnostic.
- No endpoint URLs, HTTP methods, or JSON schemas defined (per 1.6 out-of-scope).
