# API Resources — Canonical Representations (v1)

> **Resources:** Policy-level field tables, relationship exposure and validation per `phase-1.13.md §72` + `phase-1.14.md` + `phase-1.15.md`. Not per-endpoint JSON schemas. Field enum values are `CLOSED`; `availability` filter vs response vocabularies are separated; money is integer minor-unit `{amount,currency}` everywhere. Validation is layered (Transport→Schema→Auth→Authz→Domain→Concurrency) — backend authoritative, frontend advisory.

## 1. Product (PUBLIC)

**Conceptual paths:** `GET /api/v1/products` (`CAT-001`), `GET /api/v1/products/{product}` (`CAT-002`), `GET /api/v1/products/{product}/variants` (`CAT-005`), `GET /api/v1/products/{product}/variants/{variant}` (`CAT-006`).  
*Note:* `GET /api/v1/categories/{category}/products` is **REJECTED** in favor of canonical `GET /api/v1/products?category={category}` (ADR/API-END-002).

### 1.1 Product Detail Representation (`CAT-002`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Stable opaque API identifier (e.g. `prod_01h8x9j2m4k5n6p7q8r9s0t1`) |
| `name` | string | PUBLIC | no | Product display name |
| `slug` | string | PUBLIC | no | URL-safe SEO slug unique in product namespace |
| `description` | string | PUBLIC | no | Full public product description for SEO & landing page |
| `product_type` | enum `IN_STOCK`,`MADE_TO_ORDER` | PUBLIC | no | CLOSED enum |
| `price` | `{amount:int,currency:"TZS"}` | PUBLIC | no | Minor units; `125000000` = 1,250,000.00 TZS; required for all products |
| `category` | `{id,slug,name,description:string\|null}` | PUBLIC | no | Embedded Category summary (`description` is nullable) |
| `images` | `[{id,url,alt_text,sort_order,is_primary}]` | PUBLIC | no | Full gallery array, deterministically sorted by `sort_order ASC, id ASC` |
| `variants` | `[{id,sku,name,price,availability,stock_indicator}]` | PUBLIC | no | Array of active variants belonging to this product |
| `availability` | `"available"\|"unavailable"` | PUBLIC | no | Coarse public signal (filter `?availability=available`). Lowercase exception. |
| `stock_indicator` | `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | no | **Display bucket only**, not filterable via query parameter |
| `created_at` | ISO8601 UTC | PUBLIC | no | `2026-08-30T15:30:00Z` |
| `updated_at` | ISO8601 UTC | PUBLIC | no | `2026-08-31T12:00:00Z` |

### 1.2 Product Summary Representation (`CAT-001` Collection / Search)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Stable opaque API identifier |
| `name` | string | PUBLIC | no | Product display name |
| `slug` | string | PUBLIC | no | URL-safe SEO slug |
| `product_type` | enum `IN_STOCK`,`MADE_TO_ORDER` | PUBLIC | no | CLOSED enum |
| `price` | `{amount:int,currency:"TZS"}` | PUBLIC | no | Minor units `{amount, currency}` |
| `category` | `{id,slug,name}` | PUBLIC | no | Lightweight category summary |
| `primary_image` | `{id,url,alt_text}` | PUBLIC | yes | Primary thumbnail image object (`is_primary: true`) |
| `availability` | `"available"\|"unavailable"` | PUBLIC | no | Lowercase enum |
| `stock_indicator` | `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | no | Display badge bucket |

**Not exposed publicly on any Product representation:** `reserved_quantity`, `physical_quantity`, supplier internals, warehouse location, staff notes, internal cost prices, margin data.

**Inventory detail (STAFF/ADMIN via authorized inventory view `INV-002`):** `{quantity, reserved_quantity, available_quantity}` as **integer item counts (units/pieces, not monetary minor units)** — e.g., `quantity: 12` means 12 items. Not TZS minor units; never multiply stock by 100. Never on public product.

---

## 2. Category

**Conceptual paths:** `GET /api/v1/categories` (`CAT-003`), `GET /api/v1/categories/{category}` (`CAT-004`).

### 2.1 Category Detail Representation (`CAT-004`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Opaque identifier (e.g. `cat_01h8x8a1b2c3d4e5f6g7h8j9`) |
| `name` | string | PUBLIC | no | Category display name |
| `slug` | string | PUBLIC | no | URL-safe unique slug (e.g. `living-room`) |
| `description` | string | PUBLIC | yes | Category description for category landing page |
| `image` | `{url}` | PUBLIC | yes | Category banner image object |
| `created_at` | ISO8601 UTC | PUBLIC | no | `2026-08-20T08:00:00Z` |

### 2.2 Category Summary Representation (`CAT-003` Collection)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Opaque identifier |
| `name` | string | PUBLIC | no | Category display name |
| `slug` | string | PUBLIC | no | URL-safe unique slug |
| `image` | `{url}` | PUBLIC | yes | Category thumbnail image |

*Note:* Products are not embedded in category responses. Category product listings use `GET /api/v1/products?category={category}` with collection pagination and filters.

---

## 2a. Product Variant (Embedded Summary & Standalone Detail)

**Conceptual paths:** Embedded in `GET /api/v1/products/{product}` (`CAT-002`), or accessed via `GET /api/v1/products/{product}/variants` (`CAT-005`), `GET /api/v1/products/{product}/variants/{variant}` (`CAT-006`).

### 2a.1 Standalone Product Variant Detail (`CAT-006` Detail & `CAT-005` Collection)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Variant opaque identifier (e.g. `var_01h8x9k1m2n3p4q5r6s7t8u9`) |
| `product_id` | string | PUBLIC | no | Parent product ID (strictly enforces variant-product ownership) |
| `sku` | string | PUBLIC | no | Public stock keeping unit / option code |
| `name` | string | PUBLIC | no | Option / variant display name (e.g. "Charcoal Grey", "3-Seater Walnut") |
| `price` | `{amount:int,currency:"TZS"}` | PUBLIC | no | Minor units `{amount, currency}` override |
| `availability` | `"available"\|"unavailable"` | PUBLIC | no | Lowercase enum |
| `stock_indicator` | `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | no | Display badge bucket |
| `created_at` | ISO8601 UTC | PUBLIC | no | Variant creation timestamp |
| `updated_at` | ISO8601 UTC | PUBLIC | no | Variant update timestamp |

### 2a.2 Embedded Product Variant Summary (Embedded in `CAT-002` Product Detail)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Variant opaque identifier |
| `sku` | string | PUBLIC | no | Public stock keeping unit / option code |
| `name` | string | PUBLIC | no | Option / variant display name |
| `price` | `{amount:int,currency:"TZS"}` | PUBLIC | no | Minor units `{amount, currency}` |
| `availability` | `"available"\|"unavailable"` | PUBLIC | no | Lowercase enum |
| `stock_indicator` | `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | no | Display badge bucket |

*Note:* `product_id` and timestamps (`created_at`, `updated_at`) are omitted from the embedded summary in `CAT-002` to avoid redundancy with the parent Product container and maintain a lightweight payload. Standalone retrieval via `CAT-005` and `CAT-006` provides full timestamps and explicit `product_id`.

---

## 2b. Product Image (Embedded Representation)

**Embedded in:** `Product Detail` (`CAT-002`) and `Product Summary` (`CAT-001`).

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Image identifier (e.g. `img_01h8x9a0b1c2d3e4f5g6h7j8`) |
| `url` | string (URI) | PUBLIC | no | Full CDN URL to optimized public image |
| `alt_text` | string | PUBLIC | no | Descriptive accessibility & SEO alt text |
| `sort_order` | integer | PUBLIC | no | Display sequence position (1-indexed, ascending) |
| `is_primary` | boolean | PUBLIC | no | `true` for primary cover image, `false` otherwise |

---

## 2c. Product Availability (Embedded Representation)

| Field | Type | Exposure | Notes |
|---|---|---|---|
| `availability` | enum `"available"\|"unavailable"` | PUBLIC | Coarse public boolean-like signal; matches query parameter `?availability=available`. |
| `stock_indicator` | enum `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | Informational customer badge bucket only. Rejected if used in query filter. |

*Inventory Rule:* Informational on catalog; authoritative verification and deduction executed transactionally during checkout. Internal unit counts (`physical_quantity`, `reserved_quantity`, warehouse logs) remain strictly inaccessible.

---

## 3. Order (CUSTOMER — `GET /api/v1/me/orders{,/{order}}`; ADMIN `GET /api/v1/orders`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER (own) | no | Internal API id |
| `order_reference` | string `OD-...` | CUSTOMER | no | Customer-facing reference, distinct from `id` |
| `status` | enum CLOSED | CUSTOMER | no | `PENDING_PAYMENT`→`CANCELLED` etc. — machine value, not display label; `order_status` query key maps here |
| `fulfillment_type` | `PICKUP`/`DELIVERY` | CUSTOMER | no | |
| `items` | `[{sku, name, variant_id, quantity, unit_price:{amount,currency}, line_total:{amount,currency}}]` | CUSTOMER | no | Snapshot at purchase, not live product price |
| `subtotal` | `{amount,currency}` | CUSTOMER | no | Line-sum, minor units |
| `delivery_fee` | `{amount,currency}` | CUSTOMER | yes | Minor units; `{amount: 0, currency: "TZS"}` for `PICKUP`; `null` when `DELIVERY` and `delivery_fee_status=PENDING` (fee not yet set by Staff/Admin) |
| `delivery_fee_status` | enum `PENDING`/`FINALIZED` CLOSED | CUSTOMER | no | `FINALIZED` for `PICKUP` (fee 0 finalized at creation) and for `DELIVERY` after Staff/Admin sets fee; `PENDING` means provisional amounts — payment blocked (see `payment` and §23.11). Frontend must not display `total` as final when `PENDING`. |
| `total` | `{amount,currency}` | CUSTOMER | no | `subtotal + (delivery_fee?.amount ?? 0)` — **provisional** when `delivery_fee_status=PENDING` (equals `subtotal`; not final amount), final when `FINALIZED` |
| `currency` | `"TZS"` | CUSTOMER | no | Single currency |
| `billing_address` | object | CUSTOMER | yes | **V1 snapshot — not separately collected at checkout** (checkout input §10.4 / Line 262 provides only `fulfillment_type` + conditional `delivery_address`; no `billing_address` field). For `DELIVERY`, `billing_address` is a **copy of the `delivery_address` snapshot** provided at `CHK-001` (same `recipient_name, phone, address_line, city`); for `PICKUP`, `billing_address` is `null` (deferred — separate billing address collection is V2). Frontend must treat as nullable; `null` for `PICKUP` is valid. |
| `delivery_address` | object | CUSTOMER | yes | `null` for `PICKUP`; present snapshot for `DELIVERY` (source: `CHK-001` `delivery_address` input, see `api-contract.md §24.9`) |
| `delivery` | `{status, tracking_summary}` | CUSTOMER | yes | `null` for `PICKUP`; embeds delivery summary, not GPS |
| `payment` | `{payment_status, amount:{amount,currency}}` limited | CUSTOMER | yes | `null` while Order is `PENDING_PAYMENT` (before `PAY-001`) — `PAY-001` creates the payment representation (`payment_status: PENDING` then `PAID` after webhook); `PAY-001` blocked `409 DELIVERY_FEE_PENDING` when `delivery_fee_status=PENDING`; present after `PAY-001` (then `PAID` after confirmation). No secrets; full via admin |
| `created_at` / `updated_at` | ISO8601 | CUSTOMER | no | `created_at` starts 20-min cancellation window |

**STAFF/ADMIN extra (not on customer):** `customer:{id,name,email}`, operational `quantity` details, `payment.provider`, internal notes where authorized. `CANCELLED`/`SHIPPED` transitions remain controlled actions (`POST /orders/{order}/cancel` etc.), not `PATCH status`.

### 3.1 Order Summary vs Detail

- **Summary** (`GET /me/orders` collection): `id`, `order_reference`, `status`, `fulfillment_type`, `delivery_fee_status`, `subtotal`, `delivery_fee`, `total`, `currency`, `created_at`, `updated_at` — no `items` full array (or `items_count` only), no `delivery_address` detail, no `status_history` — lightweight for history list.
- **Detail** (`GET /me/orders/{order}`): Full above plus `items[]` historical snapshots, `delivery_address` snapshot, `billing_address` snapshot, `delivery` summary, `payment` summary, `tracking` milestones — see `api-contract.md §24.8-24.16`.

### 3.2 OrderItem — Historical Snapshot (Immutable)

| Field | Type | Exposure | Notes |
|---|---|---|---|
| `product_id` | string | CUSTOMER | Snapshot reference |
| `variant_id` | string \| null | CUSTOMER | Snapshot variant where applicable |
| `sku` | string | CUSTOMER | Snapshot SKU |
| `name` | string | CUSTOMER | Snapshot product name at purchase — not live `Product.name` |
| `variant_name` | string \| null | CUSTOMER | Snapshot variant display |
| `unit_price` | `{amount,currency}` | CUSTOMER | **Historical** price at transaction — integer minor units, never recomputed |
| `quantity` | integer `1..100` | CUSTOMER | Historical quantity |
| `line_total` | `{amount,currency}` | CUSTOMER | `unit_price.amount * quantity` snapshot |
| `primary_image` | `{url}` | CUSTOMER | Optional display snapshot (optional) |

Price/name/image remain even if product deactivated or price changed to `1,200,000`; quantity/price are not `PATCH`able (controlled admin correction only).

### 3.3 Fulfillment & Delivery Snapshot

- `fulfillment_type`: `PICKUP` / `DELIVERY` CLOSED — generally immutable.
- `delivery_address`: `null` for `PICKUP`; `{recipient_name, phone, address_line, city}` snapshot for `DELIVERY` — historical, not live profile address. Saved address book deferred — no `saved_address_id`.
- `delivery`: `{status, tracking_summary}` | `null` for `PICKUP`; delivery progress not GPS.
- Contact snapshot (`recipient_name`, `phone`) preserved for operational fulfillment.

### 3.4 Delivery Fee Lifecycle (Model B — `PENDING` → `FINALIZED` via `ORD-014`)

- `delivery_fee: null, delivery_fee_status: PENDING` at creation for `DELIVERY` (provisional `total == subtotal`, `payment: null` blocked `409 DELIVERY_FEE_PENDING`); `delivery_fee: {amount:0}, status: FINALIZED` for `PICKUP`.
- **Fee finalization endpoint `ORD-014` `POST /api/v1/orders/{order}/delivery-fee`** (Staff/Admin, `orders.set_delivery_fee`, `PENDING_PAYMENT`+`DELIVERY`+`PENDING`): `{"delivery_fee":{"amount":int minor units,currency:"TZS"},"reason":"..."}` → `delivery_fee: {amount,currency}, delivery_fee_status: FINALIZED, total = subtotal+delivery_fee, payment eligible`. `Idempotency-Key` **Required**, concurrency **Critical** (race with `PAY-001`), `amount` integer `>=0`, `currency:"TZS"` only, unknown fields `422`, `404` masked, `409` if already `FINALIZED` or not `PENDING_PAYMENT`. Single finalization; repeated same key replays; different amount with same key `409 DUPLICATE_OPERATION`. Audit `actor, order, old_fee, new_fee, reason, occurred_at`.
- Once `FINALIZED` and especially after `PAID`, fee becomes historical — not changed by later policy. Audit via actor/time/old/new/reason (deferred logging).

### 3.5 Status & State Machine (Closed)

Statuses `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED` CLOSED. Lifecycle `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→(READY_FOR_PICKUP | SHIPPED)→DELIVERED→COMPLETED` with branches per fulfillment; `PENDING_PAYMENT→CANCELLED` via `POST /me/orders/{order}/cancel` (own + 20-min + cancellable state). No `PATCH {status}`.

### 3.6 Status History & Tracking

- **Status History** (`order_status_history`): append-only authoritative events. Fields `id` (`evt_...` opaque, per `§25.14`), `status` CLOSED (`PENDING_PAYMENT`→`COMPLETED`), `occurred_at` ISO8601 `Z` (`2026-09-01T09:45:00Z`), `actor` (`customer:123`/`staff:45`/`system`) + `note` where appropriate (ship note) — immutable, not `PATCH`able; correction is explicit admin workflow (audit). Customer can view own history (`Order` detail + `ORD-012` staff view richer `actor/note`); cannot create/edit/delete.

- **Tracking** — customer-facing progress derived from history (read-only, not independent state machine):
  - **Endpoints:** `GET /me/orders/{order}/tracking` `ORD-003` (`ORD-TRK-001` alias) customer own tracking (`AUTHENTICATED_OWNER`); `GET /orders/{order}/tracking` `ORD-012` staff operational tracking (`OPERATIONAL orders.view_operational`) — richer `actor`/`note` where authorized.
  - **Response (`ORD-003` `200 {"data": {...}}`):** `order_reference OD-...`, `fulfillment_type PICKUP|DELIVERY`, `current_status` CLOSED (== `Order.status`), `delivery_fee_status PENDING|FINALIZED`, `timeline: [TimelineEvent]` deterministic `occurred_at ASC, id ASC` (no mixed formats), `delivery_summary` (`PICKUP`: `pickup_status`, `pickup location` single V1 location, `collection instructions` — not multi-warehouse; `DELIVERY`: `delivery_address` summary, `delivery status`/`shipping milestone` — not `tracking_number/carrier/tracking_url`, not GPS).
  - **TimelineEvent:** `id` (`evt_...` stable), `status` CLOSED, `occurred_at` ISO8601 `Z`, `label` customer-visible (`Order accepted`, `Being prepared`, `Ready for pickup`, `Shipped`, `Delivered`) — not Staff identity (`John Smith shipped`) unless required; Staff operational view may include `actor` where authorized. Filtered: customer sees only milestones for its `fulfillment_type` (`PICKUP` never `SHIPPED`).
  - **Properties:** Read-only (`POST/PATCH/DELETE /tracking` to customers prohibited); private `Cache-Control: private, no-store` (not CDN, not public catalog cache); lightweight (not embedding `entire Order + full profile + payment history + staff records`) — size limited, not paginated in V1 (append-only, small; paginate `page/per_page` only if grows, per `§4`); `current_status` consistent with `Order.status` (`PROCESSING` tracking cannot be `DELIVERED`); no GPS/live vehicle, no courier integration, no `assigned_staff` unless business needs.

### 3.6a Fulfillment & Delivery Snapshot (Order + Tracking)

- **Fulfillment** is `PICKUP` / `DELIVERY` CLOSED (selected at `CHK-001`, stored in Order, generally immutable; `PICKUP` free `delivery_fee 0/FINALIZED` at creation, `DELIVERY` `null/PENDING` until `ORD-014`).
- **Pickup flow:** `PROCESSING → READY_FOR_PICKUP` (`ORD-009 POST .../ready-for-pickup`, `orders.ready_for_pickup` + `PICKUP` + `PROCESSING`) → `COMPLETED` (`ORD-013`, explicit Staff confirms collection) — single pickup location V1 (configurable later), not multi-location system invented.
- **Delivery flow:** `PROCESSING → SHIPPED` (`ORD-010 POST .../ship`, `orders.ship` + `DELIVERY` + `PROCESSING`) → `DELIVERED` (`ORD-011 POST .../deliver`, `orders.deliver` + `DELIVERY` + `SHIPPED`) → `COMPLETED` (`ORD-013`) — `SHIPPED` means `left business`, `DELIVERED` means `delivery completed` (operational, not GPS). Cross-branch `PICKUP→SHIPPED` or `DELIVERY→READY_FOR_PICKUP` rejected `409 INVALID_ORDER_TRANSITION` / `FULFILLMENT_ACTION_NOT_ALLOWED`.
- **Operations:** All fulfillment POST are `Idempotency-Key` **Required**, concurrency **Critical** (`Staff A ship + Staff B ship`, `cancel + ship` races → `409 ORDER_STATE_CONFLICT`), `actor+permission+current_state+fulfillment` atomically, `PATCH {status}` rejected. Side effects `status_history, audit, notifications` are controlled backend effects; `price/delivery_fee/customer/payment` not changed as side effect unless explicitly `ORD-014` financial gate.
- **Relationships:** `Order 1 — * OrderStatusHistory`; `Order 1 — 1 current Fulfillment progress` (derived); `Order → Tracking` is view, not separate table. `Billing_address` snapshot (not separately collected in V1) remains in `Order`, not Tracking; `delivery_address` privacy: visible only to `owns` customer + authorized Staff/Admin.

### 3.7 Representations by Actor

| Data | Customer (own) | Staff (operational) | Admin |
|---|---|---|---|
| `order_reference`, `status`, `items` historical | Yes | Yes | Yes |
| `delivery_address` | Own | Operational | Authorized |
| `payment` limited | Limited (`payment_status`, `amount`) | Operational (as authorized) | Authorized |
| `internal notes` | No | Yes if authorized | Yes |
| `inventory internals` | No | As required | Yes |
| `credentials` | No | No | No |

Field-level before serialization; authorization before data fetch; `password`/`payment secret` never.

### 3.8 Historical Data Mutability Matrix

| Order field | Mutable after creation? | Customer | Staff | Admin |
|---|---|---|---|---|
| `order_reference` | No | Read | Read | Read |
| `customer owner` | No | Read | Operational | Authorized |
| `item quantity` | No | Read | Read | Controlled correction only |
| `historical unit_price` | No | Read | Read | Controlled correction only |
| `order created_at` | No | Read | Read | Read |
| `fulfillment_type` | Generally No | Read | Operational | Controlled |
| `delivery_fee` | `PENDING→FINALIZED` once before `PAID`; thereafter immutable (historical fee — no correction via normal Staff/Admin operations) | Read | Authorized (audit) | Authorized (audit) |
| `total` | Derived/finalized with fee | Read | Operational | Authorized |
| `status` | Action-controlled only | Read | Action | Action |
| `status_history` | Append-only | Read | Read/append via actions | Read/append via actions |

## 4. Cart & Cart Item (CUSTOMER / GUEST — `GET /api/v1/me/cart`, `POST/PATCH/DELETE /api/v1/me/cart/items{,/{item}}`)

### 4.1 Cart Representation

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER / GUEST | no | Opaque Cart identifier (`cart_01h8y0a1...`) |
| `items_count` | integer | CUSTOMER / GUEST | no | Number of distinct lines in the cart, including stale/unavailable lines (matches `items[].length`) |
| `items` | `CartItem[]` | CUSTOMER / GUEST | no | Ordered list of cart item objects |
| `subtotal` | `{amount:int,currency:"TZS"}` | CUSTOMER / GUEST | no | Informational server-calculated subtotal (minor units) |
| `updated_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Timestamp of last cart mutation |

### 4.2 Cart Item Representation

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER / GUEST | no | Cart Item line identifier (`item_01h8y0b2...`) |
| `product_id` | string | CUSTOMER / GUEST | no | Referenced product identifier |
| `variant_id` | string | CUSTOMER / GUEST | yes | Referenced variant identifier (`null` if product has no variants) |
| `product` | `ProductSummary` | CUSTOMER / GUEST | no | Embedded Product summary (id, name, slug, product_type, price, primary_image) |
| `variant` | `VariantSummary` | CUSTOMER / GUEST | yes | Embedded Variant summary (id, sku, name, price); `null` if no variant |
| `quantity` | integer | CUSTOMER / GUEST | no | Selected unit quantity (1..100) |
| `unit_price` | `{amount:int,currency:"TZS"}` | CUSTOMER / GUEST | no | Current catalog unit price (minor units) |
| `line_total` | `{amount:int,currency:"TZS"}` | CUSTOMER / GUEST | no | Informational `unit_price * quantity` (minor units) |
| `availability` | enum `"available"\|"unavailable"` | CUSTOMER / GUEST | no | Current live availability status |
| `stock_indicator` | enum `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | CUSTOMER / GUEST | no | Current inventory badge bucket |
| `is_purchasable` | boolean | CUSTOMER / GUEST | no | `true` if item is active and in stock; `false` if stale/unavailable |
| `created_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Line addition timestamp |
| `updated_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Line update timestamp |

*Authority & Reservation Rules:* Cart totals and prices are informational display values. Adding an item does not reserve stock or guarantee price locks. Final inventory checks and authoritative calculations occur during Checkout (`CHK-001`).

### 4a. Checkout (CUSTOMER — `POST /api/v1/checkout` `CHK-001`, Phase 1.22)

**Checkout is not a resource listing — it is a transaction workflow that creates an Order from the customer's own active Cart.**

- **Request:** `fulfillment_type: PICKUP|DELIVERY` (CLOSED) + conditional `delivery_address` (required when `DELIVERY`, absent/null when `PICKUP`). No `cart_id`, no money, no status, no reference, no ownership override.
- **Response (created Order summary):** `order_id`, `order_reference (OD-…)`, `status (PENDING_PAYMENT)`, `fulfillment_type`, `delivery_address` (null for PICKUP, snapshot for DELIVERY), `subtotal {amount,currency}`, `delivery_fee {amount,currency} | null` (`{amount:0, currency:"TZS"}` + `delivery_fee_status=FINALIZED` for `PICKUP`; `null` + `delivery_fee_status=PENDING` for `DELIVERY` pending fee), `delivery_fee_status: PENDING|FINALIZED` CLOSED, `total {amount,currency}` (provisional equals `subtotal` when `PENDING`, final `subtotal+delivery_fee` when `FINALIZED`), `currency: TZS`, `payment null` (still `PENDING_PAYMENT` before `PAY-001`; `PAY-001` blocked `409 DELIVERY_FEE_PENDING` when `PENDING`, otherwise eligible; `payment` object `{payment_status, amount}` appears only after `PAY-001` creates it). Same money shape `{amount:int,currency:"TZS"}` everywhere; server-calculated; provisional amounts explicitly marked by `delivery_fee_status`.
- **Relationships:** `Cart (intent, informational)` → **`Checkout (validates, recalculates, atomically reserves inventory at CHK-001: reserved_quantity += quantity, available = physical - reserved, held through PENDING_PAYMENT)`** → `Order (snapshot, authoritative — PENDING_PAYMENT reserved, not yet consumed)` → `Payment (Group H)` on final total. **Reservation lifecycle (V1, align api-contract.md §23.10 / §24.13):** `release` atomically (`reserved -= quantity, available += quantity` + status-history `CANCELLED`) on customer `ORD-004` cancel (20-min), admin `POST /orders/{order}/cancel` + `orders.manage` / System TTL expiry via Group H, or Group H payment failure; `commit` atomically (`physical -= quantity, reserved -= quantity`) on `PAID` via Group H webhook (`PENDING_PAYMENT→PAID`, not EXPIRED). Delivery fee finalization occurs on the Order between creation and payment — see `api-contract.md §23.6` / `§23.10` / `§24.13`. No `reserve vs consume` indecision in V1.
- **Security:** `AUTHENTICATION_REQUIRED` (no guest checkout), `AUTHENTICATED_OWNER` own cart, `Idempotency-Key` required, private/no-store, atomic inventory + order + cart transition.

---

## 5. Furniture Request (`/requests`, `/me/requests`) — Approved V1 (Phase 1.25)

**Conceptual paths:** `POST /api/v1/requests` (`REQ-001` public anonymous allowed), `GET /api/v1/me/requests` (`REQ-002` own), `GET /api/v1/me/requests/{request}` (`REQ-003` own), `GET /api/v1/requests` (`REQ-004` staff operational), `GET /api/v1/requests/{request}` (`REQ-005` staff operational), `PATCH /api/v1/requests/{request}` (`REQ-006` staff limited), `POST /api/v1/requests/{request}/attachments` (`REQ-007` scoped).
*Note:* Canonical resource is `/requests` (not `/made-to-order-requests`/`/furniture-requests`); anonymous creation is `PUBLIC` but anonymous retrieval is **not** automatically public (requires scoped token via `REQ-007`).

### 5.1 Request Detail Representation — Customer View (`REQ-001` response, `REQ-003`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string opaque `req_...` | CUSTOMER (own) | no | Stable opaque API identifier, server-generated, not guessable enumeration |
| `product_id` | string \| null | CUSTOMER (own) | yes | Nullable — custom request when `null`; when present must be `MADE_TO_ORDER` product validated server-side |
| `product` | `{id, name, slug}` \| null | CUSTOMER (own) | yes | Summary of linked product when `product_id` present; `null` for custom |
| `quantity` | integer `1..100` \| null | CUSTOMER (own) | yes | Optional integer; not order quantity, not inventory allocation |
| `name` | string | CUSTOMER (own) | no | Contact name snapshot, trimmed, max 120 |
| `phone` | string \| null | CUSTOMER (own) | yes | Normalized, max 30; at least one of `phone`/`email` required |
| `email` | string \| null | CUSTOMER (own) | yes | Lowercased/trimmed, max 255; at least one of `phone`/`email` required |
| `dimensions` | `{length:number\|null, width:number\|null, height:number\|null, unit:"cm"}` \| null | CUSTOMER (own) | yes | Structured only; allowed keys `length,width,height,unit`; `unit` exactly `"cm"` CLOSED; each dimension `>0` and `<=10000`; `null` when no dimensions |
| `material` | string \| null | CUSTOMER (own) | yes | Free text, max 500; not closed enum |
| `color` | string \| null | CUSTOMER (own) | yes | Free text, max 200; not closed enum |
| `notes` | string \| null | CUSTOMER (own) | yes | Free text, max 5000, safe handling |
| `request_status` | enum `SUBMITTED`,`IN_REVIEW`,`CLOSED` CLOSED — **formally approved V1** (was deferred per phase-1.25 §43-44, now approved ADR/API-REQ-010) | CUSTOMER (own) | no | Machine status; `SUBMITTED` at creation, never client-settable; `?request_status=` query key maps here; Staff `REQ-006` mutation approved, not deferred |
| `attachments` | `[{id, filename, content_type, size}]` | CUSTOMER (own) | no | Via subresource `REQ-007`; private URLs, not fully embedded; empty array when none |
| `created_at` / `updated_at` | ISO8601 UTC `Z` | CUSTOMER (own) | no | Server-generated |

**Contact requiredness (final — resolves phase-1.25 §15 Potential, per ADR/API-REQ-001):** `name` required and at least one of `phone`/`email` required for **both** Anonymous and authenticated Customer (identical rule; no trusted-account fallback).

**Product reference policy (final V1 — resolves phase-1.25 §26-27, per ADR/API-REQ-011):** `product_id` is **OPTIONAL (nullable)** — both catalog-linked (`product_id` = existing `MADE_TO_ORDER`) and general/custom (`product_id = null`/omitted) allowed; **not** Required, **not** Absent.

### 5.2 Request Summary Representation — Customer Collection (`REQ-002`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER (own) | no | Opaque identifier |
| `product_id` | string \| null | CUSTOMER (own) | yes | Nullable |
| `product` | `{id, name, slug}` \| null | CUSTOMER (own) | yes | Summary or null |
| `quantity` | integer \| null | CUSTOMER (own) | yes | |
| `request_status` | enum `SUBMITTED`,`IN_REVIEW`,`CLOSED` | CUSTOMER (own) | no | |
| `created_at` | ISO8601 UTC | CUSTOMER (own) | no | |

**Not exposed on customer collection/view:** `staff_internal_notes`, `user_id` (except where ownership context requires), `order_id`, `payment` fields.

### 5.3 Request Detail Representation — Staff View (`REQ-005`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string `req_...` | STAFF (`requests.view`) | no | Same `id` |
| `product` | `{id, name, slug}` \| null | STAFF | yes | Full linked product context |
| `quantity` | integer \| null | STAFF | yes | |
| `name` | string | STAFF | no | Contact snapshot |
| `phone` | string \| null | STAFF | yes | |
| `email` | string \| null | STAFF | yes | |
| `dimensions` | object \| null | STAFF | yes | Same structured `length,width,height,unit:"cm"` |
| `material` | string \| null | STAFF | yes | |
| `color` | string \| null | STAFF | yes | |
| `notes` | string \| null | STAFF | yes | Customer notes preserved |
| `request_status` | enum `SUBMITTED`,`IN_REVIEW`,`CLOSED` CLOSED — **formally approved V1** | STAFF | no | CLOSED; staff transition `SUBMITTED→IN_REVIEW→CLOSED` via approved `REQ-006` (was deferred per §43-44, now approved ADR/API-REQ-010) |
| `staff_internal_notes` | string \| null | STAFF (`requests.manage` where authorized) | yes | Separated from `notes`; never customer-visible |
| `user_id` | string \| null | STAFF | yes | `null` for anonymous; `user_...` when authenticated — derived, never client-supplied |
| `attachments` | `[{id, filename, content_type, size}]` | STAFF | no | Private to parent |
| `created_at` / `updated_at` | ISO8601 UTC | STAFF | no | |

### 5.4 Request Attachment (Subresource `REQ-007`)

| Field | Type | Exposure | Notes |
|---|---|---|---|
| `id` | string `att_...` | own / STAFF (parent-scoped) | Opaque attachment id |
| `filename` | string | own / STAFF | Sanitized display name |
| `content_type` | string | own / STAFF | Validated server-side (client MIME not trusted): allow `image/jpeg`, `image/png`, `image/webp`, `application/pdf` |
| `size` | integer bytes | own / STAFF | Validated `<=5 MB` V1 |
| `url` | string (URI) \| null | own / STAFF (private) | Safe access URL if authorized (signed/temporary later); never permanent public URL; internal storage keys never exposed |

*Inventory/price/promise:* Request never reserves inventory, never guarantees price/production/delivery.

### 5.5 Request Status & History (Minimal V1)

Statuses `SUBMITTED` (default), `IN_REVIEW`, `CLOSED` are **CLOSED** (`UPPER_SNAKE_CASE`). Transitions `SUBMITTED→IN_REVIEW→CLOSED`; `CLOSED` terminal; direct `SUBMITTED→CLOSED` permitted. `QUOTED`/`APPROVED`/`REJECTED`/`PRODUCING` not in V1. Staff `PATCH REQ-006` validates transition; customer cannot set `request_status`. Original submission fields are immutable; `staff_internal_notes` is operational only.

### 5.6 Representations by Actor (Field-Level Exposure)

| Data | Customer (own) | Staff (operational) | Admin | Anonymous |
|---|---|---|---|---|
| `product_id` / `quantity` / `dimensions` / `material` / `color` / `notes` | Own | Operational | Authorized | Input only on creation (no read) |
| `name` / `phone` / `email` (contact snapshot) | Own | Operational | Authorized | Input only |
| `request_status` | Own (`SUBMITTED`→`CLOSED`) | Operational | Authorized | Not readable (no retrieval) |
| `staff_internal_notes` | No | Yes if authorized | Yes | No |
| `attachments` (metadata) | Own | Operational | Authorized | Scoped token only |
| `user_id` | Own context (implicit) | Operational | Authorized | `null` |
| `order_id` / `payment` | No | No* | No* | No |
| `credentials` | No | No | No | No |

`*` only via separately approved Request-to-Order workflow.

### 5.7 Historical Data Preservation

Original customer-provided `product_id`, `quantity`, `dimensions`, `material`, `color`, `notes`, `contact` are preserved as historical intake. Staff `internal_notes` separated; future `request_status` changes are append-style operational history (audit candidate). Changing current `Product` price/name does not rewrite past Request; deactivation of product does not erase Request history.

## 6. Enquiry (`/enquiries`, `/me/enquiries`) — Approved V1 (Phase 1.26)

**Conceptual paths:** `POST /api/v1/enquiries` (`ENQ-001` public anonymous allowed), `GET /api/v1/me/enquiries` (`ENQ-002` own), `GET /api/v1/me/enquiries/{enquiry}` (`ENQ-003` own), `GET /api/v1/enquiries` (`ENQ-004` staff operational), `GET /api/v1/enquiries/{enquiry}` (`ENQ-005` staff operational), `POST /api/v1/enquiries/{enquiry}/close` (+ optional `reopen`) (`ENQ-006` staff limited), `POST /api/v1/enquiries/{enquiry}/attachments` (`ENQ-007` scoped).
*Note:* Canonical resource is `/enquiries` (not `/contacts`/`/messages`/`/support`); anonymous creation is `PUBLIC` but anonymous retrieval is **not** automatically public (requires scoped token via `ENQ-007`).

### 6.1 Enquiry Detail Representation — Customer View (`ENQ-001` response, `ENQ-003`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string opaque `enq_...` | CUSTOMER (own) | no | Stable opaque API identifier, server-generated, not guessable enumeration |
| `name` | string | CUSTOMER (own) | no (anonymous required, customer derived/fallback) | Contact name snapshot, trimmed, max 120 |
| `email` | string \| null | CUSTOMER (own) | yes | Lowercased/trimmed, max 255; at least one of `email`/`phone` required for anonymous; authenticated optional/derived |
| `phone` | string \| null | CUSTOMER (own) | yes | Normalized, max 30; at least one of `email`/`phone` required for anonymous |
| `subject` | string | CUSTOMER (own) | no | Trimmed, 5–200 chars, plain text |
| `message` | string | CUSTOMER (own) | no | Plain text only, 10–5000 chars, preserved newlines, safe handling |
| `category` | enum `GENERAL`,`PRODUCT`,`DELIVERY`,`OTHER` \| null CLOSED | CUSTOMER (own) | yes | Optional triage, CLOSED `UPPER_SNAKE_CASE`; `null` when none |
| `product_id` | string \| null | CUSTOMER (own) | yes | Nullable — when supplied must be existing `is_active:true` + `is_published:true` public product (any `product_type` allowed for enquiry context) |
| `product` | `{id, name, slug}` \| null | CUSTOMER (own) | yes | Summary of linked product when `product_id` present; `null` otherwise |
| `order_id` | string \| null | CUSTOMER (own) | yes | Nullable — when supplied must pass ownership check (authenticated `order` must belong to principal); `null` when not about an order |
| `order` | `{id, order_reference, status}` \| null | CUSTOMER (own) | yes | Summary of linked order when `order_id` present and owned; `null` otherwise |
| `enquiry_status` | enum `OPEN`,`CLOSED` CLOSED — minimal V1 | CUSTOMER (own) | no | Machine status; `OPEN` at creation, never client-settable; `?enquiry_status=` query key maps here; Staff `ENQ-006` mutation approved |
| `attachments` | `[{id, filename, content_type, size}]` | CUSTOMER (own) | no | Via subresource `ENQ-007`; private URLs, not fully embedded; empty array when none |
| `created_at` / `updated_at` | ISO8601 UTC `Z` | CUSTOMER (own) | no | Server-generated |

**Contact requiredness (final — per phase-1.26 §19-21, §97, ADR/API-ENQ-001):** Anonymous: `name` required + at least one of `phone`/`email` required. Authenticated: `name`/`email`/`phone` Optional/derived (server derives from profile when not supplied, preserves snapshot when supplied). `subject` 5–200 required, `message` 10–5000 plain text required for both.

**Product/Order association (final V1):** `product_id` is **OPTIONAL (nullable)** — any public product may be referenced for context, not restricted to `IN_STOCK`/`MADE_TO_ORDER`; `order_id` is **OPTIONAL (nullable)** — when supplied by authenticated customer must be owned order. Both may be `null`/omitted for general business enquiry.

### 6.2 Enquiry Summary Representation — Customer Collection (`ENQ-002`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER (own) | no | Opaque identifier |
| `subject` | string | CUSTOMER (own) | no | |
| `category` | enum \| null | CUSTOMER (own) | yes | |
| `enquiry_status` | enum `OPEN`,`CLOSED` | CUSTOMER (own) | no | |
| `created_at` | ISO8601 UTC | CUSTOMER (own) | no | |

**Not exposed on customer collection/view:** `staff_internal_notes`, `user_id` (except where ownership context requires), `payment`/`order` financial secrets.

### 6.3 Enquiry Detail Representation — Staff View (`ENQ-005`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string `enq_...` | STAFF (`enquiries.view`) | no | Same `id` |
| `name` | string | STAFF | no | Contact snapshot |
| `email` | string \| null | STAFF | yes | |
| `phone` | string \| null | STAFF | yes | |
| `subject` | string | STAFF | no | |
| `message` | string | STAFF | no | Customer message preserved, plain text |
| `category` | enum \| null | STAFF | yes | Same `GENERAL`/`PRODUCT`/`DELIVERY`/`OTHER` CLOSED |
| `product` | `{id, name, slug}` \| null | STAFF | yes | Full linked product context where `product_id` present |
| `order` | `{id, order_reference, status}` \| null | STAFF | yes | Full linked order context where `order_id` present and authorized |
| `enquiry_status` | enum `OPEN`,`CLOSED` CLOSED | STAFF | no | CLOSED; staff transition `OPEN→CLOSED` (and `CLOSED→OPEN` if reopen approved) via `ENQ-006` |
| `staff_internal_notes` | string \| null | STAFF (`enquiries.manage` where authorized) | yes | Separated from `message`; never customer-visible |
| `user_id` | string \| null | STAFF | yes | `null` for anonymous; `user_...` when authenticated — derived, never client-supplied |
| `attachments` | `[{id, filename, content_type, size}]` | STAFF | no | Private to parent |
| `created_at` / `updated_at` | ISO8601 UTC | STAFF | no | |

### 6.4 Enquiry Attachment (Subresource `ENQ-007`)

| Field | Type | Exposure | Notes |
|---|---|---|---|
| `id` | string `att_...` | own / STAFF (parent-scoped) | Opaque attachment id |
| `filename` | string | own / STAFF | Sanitized display name |
| `content_type` | string | own / STAFF | Validated server-side (client MIME not trusted): allow `image/jpeg`, `image/png`, `image/webp`, `application/pdf` |
| `size` | integer bytes | own / STAFF | Validated `<=5 MB` V1 |
| `url` | string (URI) \| null | own / STAFF (private) | Safe access URL if authorized (signed/temporary later); never permanent public URL; internal storage keys never exposed |

*Same file strategy as `REQ-007` — one upload architecture; 0 or 1 attachment per creation in V1; plain-text message, not Markdown/HTML.*

### 6.5 Enquiry Status & History (Minimal V1)

Statuses `OPEN` (default), `CLOSED` are **CLOSED** (`UPPER_SNAKE_CASE`). Transitions `OPEN→CLOSED`; `CLOSED→OPEN` (reopen) only if explicitly approved, otherwise `CLOSED` terminal. `ASSIGNED`/`IN_PROGRESS`/`WAITING_FOR_CUSTOMER`/`ESCALATED`/`RESOLVED`/`SUBMITTED` not in V1. Staff `ENQ-006` validates transition; customer cannot set `enquiry_status`. Original submission fields are immutable; `staff_internal_notes` is operational only. Staff response initially via ordinary business contact process (email/phone), not in-app thread (Group R deferred).

### 6.6 Representations by Actor (Field-Level Exposure)

| Data | Customer (own) | Staff (operational) | Admin | Anonymous |
|---|---|---|---|---|
| `subject` / `message` / `category` / `product`+`order` references | Own | Operational | Authorized | Input only on creation (no read) |
| `name` / `phone` / `email` (contact snapshot) | Own | Operational | Authorized | Input only |
| `enquiry_status` | Own (`OPEN`→`CLOSED`) | Operational | Authorized | Not readable (no retrieval) |
| `staff_internal_notes` | No | Yes if authorized | Yes | No |
| `attachments` (metadata) | Own | Operational | Authorized | Scoped token only |
| `user_id` | Own context (implicit) | Operational | Authorized | `null` |
| `payment` / `order financial` | No | No* | No* | No |
| `credentials` | No | No | No | No |

`*` only via separately authorized Order domain; enquiry does not modify payment.

### 6.7 Historical Data Preservation

Original customer-provided `subject`, `message`, `contact` (`name`/`email`/`phone`), `category`, `product_id`/`order_id`, `attachment` metadata are preserved as historical communication intake. Staff `internal_notes` separated; future `enquiry_status` changes are append-style operational history (audit candidate). Changing current `Product` price/name does not rewrite past Enquiry; order creation does not retroactively create enquiry.

## 7. Notification (`/me/notifications`) — Approved V1 (Phase 1.27)

**Conceptual paths:** `GET /api/v1/me/notifications` (`NOT-001` own, paginated, holder-scoped), `PATCH /api/v1/me/notifications/{notification}` (`NOT-002` mark read/unread), `POST /api/v1/me/notifications/read-all` (`NOT-003` optional if approved), `GET /api/v1/notifications/operations` (`NOT-004` optional shared operational queue).
*Note:* Canonical collection is `GET /me/notifications` holder-scoped via `recipient_user_id`; no `POST /notifications` for clients; no `CustomerNotification` / `StaffNotification` split — one `Notification` resource with authorization/recipient scope. Anonymous in-app notifications not supported.

### 7.1 Notification Detail Representation — Customer / Staff / Admin View

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string opaque `not_...` | Recipient only | no | Stable opaque identifier, server-generated |
| `recipient_user_id` | string | Recipient only | no | Derived server-side (never client-supplied) |
| `type` | enum CLOSED | Recipient only | no | Machine type `ORDER_SHIPPED`, `NEW_ORDER` etc. per `§7.5`; CLOSED |
| `title` | string | Recipient only | no | Short title, server-generated |
| `message` | string | Recipient only | no | Human message, server-generated, safe-encoded; not used for machine logic |
| `read_at` | ISO8601 `Z` \| null | Recipient only | yes | `null` → unread, timestamp → read; server-owned (see `§7.2`) |
| `is_read` | boolean | Recipient only | no | Derived `read_at != null`; convenience, not second source of truth |
| `target` | `{type, id}` \| null | Recipient only | yes | Safe reference `{type: ORDER\|REQUEST\|ENQUIRY, id: ...}`; does not grant access, target fetch still auth-checked |
| `source` | `{type, id}` \| null | Recipient only | yes | Traceability `{type: ORDER\|REQUEST\|ENQUIRY\|PAYMENT, id: ...}`; does not grant access |
| `created_at` | ISO8601 `Z` | Recipient only | no | Server-generated, authoritative |

**Not exposed:** `channel` (`EMAIL`/`SMS`/`PUSH` deferred — V1 `IN_APP` only, do not expose until implemented), delivery logs (`SMTP`), payment secrets, internal notes, full Order payload, `recipient` email/phone.

### 7.2 Read State — `read_at` Contract

| State | `read_at` | `is_read` | Set by |
|---|---|---|---|
| Unread | `null` | `false` | Server at creation |
| Read | ISO8601 `Z` timestamp | `true` | Recipient via `NOT-002` `PATCH {read: true}` |

Do not overload read state with business status (`Order shipped` ≠ `Notification read`). `is_read` is derived from `read_at` to avoid two sources of truth.

### 7.3 Notification Collection Representation (`NOT-001`)

```json
{
  "data": [
    {
      "id": "not_01h8y5a1b2c3d4e5f6g7h8j9",
      "type": "ORDER_SHIPPED",
      "title": "Order shipped",
      "message": "Your order OD-12345 has been shipped.",
      "read_at": null,
      "is_read": false,
      "target": { "type": "ORDER", "id": "ord_01h..." },
      "source": { "type": "ORDER", "id": "ord_01h..." },
      "created_at": "2026-09-01T14:00:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 12, "last_page": 1, "has_next": false, "has_previous": false },
    "unread_count": 3
  }
}
```

`meta.unread_count` preferred if efficiently computable; choose `meta.unread_count` vs `GET .../unread-count` not both. Deterministic ordering `created_at DESC, id ASC` (newest first).

### 7.4 Notification Types — CLOSED Registry (V1)

| Type | Recipient | Source | Notes |
|---|---|---|---|
| `ORDER_RECEIVED` | Customer | Order | `PENDING_PAYMENT` created |
| `ORDER_ACCEPTED` | Customer | Order | `ACCEPTED` |
| `ORDER_PROCESSING` | Customer | Order | `PROCESSING` if useful |
| `ORDER_READY_FOR_PICKUP` | Customer | Fulfillment | `READY_FOR_PICKUP` |
| `ORDER_SHIPPED` | Customer | Fulfillment | `SHIPPED` |
| `ORDER_DELIVERED` | Customer | Fulfillment | `DELIVERED` |
| `ORDER_COMPLETED` | Customer | Order | `COMPLETED` if useful |
| `ORDER_CANCELLED` | Customer | Order | `CANCELLED` |
| `NEW_ORDER` | Staff | Order | Operational |
| `NEW_MADE_TO_ORDER_REQUEST` | Staff | Request | `REQ-001` |
| `NEW_ENQUIRY` | Staff | Enquiry | `ENQ-001` |

CLOSED enum; do not create per-product variants (`SOFA_SHIPPED`). Adding type within `v1` requires compatibility review. `PAYMENT_*` deferred to Group H.

### 7.5 Representations by Actor (Field-Level Exposure)

| Data | Customer (own) | Staff (operational) | Admin |
|---|---|---|---|
| `type` / `title` / `message` / `read_at` / `target` / `source` | Own | Operational where applicable | Limited operational |
| `recipient_user_id` | Own (implicit) | Own (implicit) | Limited |
| `internal delivery logs` | No | No | No |
| `credentials` / `payment secrets` | No | No | No |

`*` Staff operational queue vs personal `read_at` semantics: `Customer: personal read_at`; `Staff: operational queue` — shared queue should not auto-hide for all Staff when one reads; separate `handled` semantics if needed.

### 7.6 Privacy & Source of Truth

- **Classification:** `Customer → PRIVATE`, `Staff operational → PRIVATE/INTERNAL`, `Admin → ADMINISTRATIVE`. Never public. `Cache-Control: private, no-store`.
- **Source of truth:** `Order` / `Request` / `Enquiry` authoritative; Notification is downstream communication. If Notification says `SHIPPED` but Order says `PROCESSING`, Order wins.
- **Not a capability token:** `target: {type: ORDER, id: B}` does not grant access to `Order B`; normal authorization still required.
- **Object-level:** `Customer A → Customer B notification` `404 RESOURCE_NOT_FOUND` masked; `Customer cannot create/change recipient/target/type`.

## 8. Payment (generic — provider-specific deferred to Group H)

- Response shape: `{id, payment_status: CLOSED, amount:{amount,currency}, currency:"TZS", created_at}`. No provider secrets, no `payment_status=success` client-writable field. Provider reference / webhook details are `INTERNAL` until Group H.

## 9. Relationships Summary

- **Embedded:** `product.images`, `order.items` (snapshots), `order.billing_address`, `checkout → order delivery_address snapshot`.
- **Summary/Reference:** `product.category`, `variant.product`, `cart → checkout (own active Cart, no cart_id)`.
- **Subresource:** `products/{product}/variants`, `products/{product}/images/{image}`, `orders/{order}/tracking`, `orders/{order}/status-history`, `requests/{request}/attachments`, `enquiries/{enquiry}/attachments`.
- **Workflow chain:** `Catalog (public, informational availability)` → `Cart (customer intent, no reservation, informational pricing)` → `Checkout CHK-001 (server revalidates inventory, recalculates authoritative prices, validates fulfillment, snaps address, idempotent, atomic)` → `Order (historical snapshot, authoritative totals, fulfillment, status history)` → `Payment (Group H, on final total after delivery_fee finalization)`.
- **Deferred dynamic:** `?include` / `?fields` — not supported; use dedicated subresources.

## 10. Sensitive / Never Serialized

`password_hash`, `tokens`, `provider secrets`, internal `reserved_quantity` (public), staff private notes, internal filesystem paths, authorization flags, internal IDs (beyond `id` where not contracted).

---

## 11. Request Input per Resource — Consolidated (Phases 1.14 & 1.15 Validation)

> No final endpoint schemas are defined here; each sub-section reserves `Create | Update (PATCH) | Action | Read-Only / Server-Generated | Role-Specific` shape per `phase-1.14.md §69` plus explicit validation layers per `phase-1.15.md`: **input validation (schema), mutable vs immutable vs conditional fields, business validation, concurrency**. See `api-contract.md §14` and `api-conventions.md §16` for normative rules. This section is resource-specific instantiation of those rules — not a replacement.

### 11.1 Product (Staff/Admin) — Create / Update

- **Create input (staff/admin):** `name`, `slug`, `description`, `category_id`, `product_type` (`IN_STOCK`/`MADE_TO_ORDER` CLOSED), `price:{amount,currency}` **required for every published product including `MADE_TO_ORDER`** (minor units; for `MADE_TO_ORDER` it is a display/starting-at price, never used as checkout total). `is_active`/`is_published` flags. **Not accepted:** `id`, `created_at`, `updated_at`, `reserved_quantity`, `supplier internals`.
- **Update (PATCH):** partial, only mutable fields (`name`, `description`, `price`, `is_active`, `category_id`) — not `id`/`created_at`/`available_quantity`.
- **Read-only / server-generated:** `id`, `slug` uniqueness enforced server-side, `created_at`/`updated_at`, `availability`/`stock_indicator` derived from inventory, not client-set.
- **Role:** Public cannot create/update products.
- **Price contract (single contract, Phase 1.15 fix):** `price` is **non-null required** for every exposed product (`IN_STOCK` and `MADE_TO_ORDER`) — same shape `{amount:int minor units, currency:"TZS"}` per `api-contract.md §3.8`. For `MADE_TO_ORDER` it is informational/SEO display price only; domain validation prohibits `MADE_TO_ORDER` from normal checkout/cart regardless of price, and no money is calculated from it. Alternative of `null`/omission was rejected to keep one consistent representation (`data.price` always object, never `null`). Changing `price` to nullable or omittable would be a breaking change per `api-contract.md §9`.
- **Validation (schema → domain):** Schema: `name` non-empty after trim, length max; `slug` snake/kebab lowercase, unique; `product_type` CLOSED enum; `price:{amount int≥0, currency:"TZS"}` **required, strict types**; `category_id` exists. Domain: `category active`, `price authority` staff-only; `MADE_TO_ORDER` price not treated as purchasable unit price; inventory fields not client-writable. Unknown fields rejected; empty `""` not treated as omitted unless contract says so. Concurrency: creation not high-contention; no inventory reservation here.

### 11.1a Product — Validation Details (Phase 1.15 Matrix)

| Aspect | Rule |
|---|---|
| Input validation | `name` string trimmed, collapses internal whitespace; `quantity` N/A for product create; enum `product_type` exact `IN_STOCK` not `in_stock`; `price` always object `{amount int≥0, currency:"TZS"}` required |
| Mutable | `name`, `description`, `price`, `is_active`, `category_id` (PATCH allow-list) |
| Immutable / server-generated | `id`, `created_at`/`updated_at`, `availability`/`stock_indicator`, `reserved_quantity`/`available_quantity` |
| Conditional | No conditional omission of `price` — price required for **both** `IN_STOCK` and `MADE_TO_ORDER` (single contract); conditional logic is instead `MADE_TO_ORDER → checkout/cart prohibited regardless of price` |
| Business validation | `category exists && active`; `slug unique`; `price non-negative minor units` (informational for `MADE_TO_ORDER`, purchasable unit price for `IN_STOCK`); stock derived server-side |
| Request type | `CREATE` / `PATCH` (no generic `PUT` unless full replacement documented) |

### 11.2 Category (Staff/Admin)

- **Create/Update:** `name`, `slug`, `description`, `image`. `id`, `created_at` server-generated. No `product` embedding in category create.
- **Validation:** Schema: `name`/`slug` length/pattern; `slug` unique global; enum N/A. Mutable: `name`, `slug`, `description`, `image`. Immutable: `id`, `created_at`. Unknown fields rejected. Business: `slug unique` enforced transactionally; no inventory logic.

### 11.3 Cart — Add / Update / Remove / Merge (Phase 1.21 Contract)

- **Add item (POST `/me/cart/items`):** `product_id` string required + `variant_id` (conditional: required if product has variants; `null` or omitted if the product has no variants) + `quantity: integer (1..100)`. **Rejected:** `price`, `subtotal`, `total`, `stock`, `currency`, `discount`, `delivery_fee`.
- **Update item (PATCH `/me/cart/items/{item}`):** `quantity: integer (1..100)` only. Mutable: `quantity` only. Immutable: `id`, `product_id`, `variant_id`, `created_at`.
- **Remove item (DELETE `/me/cart/items/{item}`):** No request body. Removes specific item from caller's active cart.
- **Merge guest cart (POST `/me/cart/merge`):** `X-Guest-Cart-Id` header or `{"guest_cart_id": "guest_..."}`. Merges guest items into authenticated cart.
- **Server-controlled:** `id`, `items_count`, `subtotal`, `line_total`, `unit_price`, `availability`, `stock_indicator`, `is_purchasable`, `created_at`/`updated_at`.
- **Validation Pipeline:**
  - *Schema:* `product_id` string required; `variant_id` string nullable; `quantity` integer strict (1..100).
  - *Auth & Authz:* `AUTHENTICATED_OWNER` or validated guest token (`GUEST`). Cross-customer cart item mutation rejected with `CART_ITEM_NOT_FOUND` (404 masking).
  - *Domain / Invariants:* `product exists && is_active && is_published && product_type=IN_STOCK` — all three flags required for cart admission; an active draft (`is_published: false`) is rejected with `PRODUCT_NOT_PURCHASABLE` (422); `MADE_TO_ORDER` strictly rejected with `PRODUCT_NOT_PURCHASABLE` (422); `variant belongs to product` (`VAR-OWN-001`) and `variant is_active`; quantity bounded 1..100; duplicate items merged by summing quantities.
  - *Inventory Semantics:* Informational availability check on add/update; **no inventory reservation** occurs at the cart stage. Stock lock occurs exclusively at Checkout (`CHK-001`).
  - *Stale Items:* Inactive or out-of-stock items in cart marked `is_purchasable: false` and `availability: unavailable` rather than silently deleted.
  - *Concurrency:* Medium/Safe. Duplicate additions merge; last valid mutation wins. Non-cacheable mutations.

### 11.4 Checkout — `POST /api/v1/checkout` (`CHK-001`, Phase 1.22)

> Checkout is a dedicated server-controlled workflow that converts the customer's **own active Cart** (`/me/cart`, not a client-supplied `cart_id`) into an Order. Not a `PATCH /me/cart` and not `POST /orders` with client totals.

- **Endpoint:** `POST /api/v1/checkout` (`CHK-001`) — `CUSTOMER`, `AUTHENTICATION_REQUIRED` (401, not guest), `AUTHENTICATED_OWNER` own cart, `PRIVATE`/`no-store`, `IDEMPOTENCY_REQUIRED` (`Idempotency-Key` header), concurrency **Critical**.
- **Input allow-list (body only, no query params):**
  | Field | Required | Rule |
  |---|---|---|
  | `fulfillment_type` | Yes | CLOSED `PICKUP` / `DELIVERY` only (`UPPER_SNAKE_CASE`). `shipping`/`courier` etc. rejected `INVALID_FULFILLMENT`. |
  | `delivery_address` | Conditional | **Required object when `DELIVERY`**, **absent/`null` when `PICKUP`**. Structure `{recipient_name, phone, address_line, city}` trimmed/non-empty. `field: delivery_address.city` on failure. |
  | `delivery_address.recipient_name` / `phone` / `address_line` / `city` | Conditional | Required non-empty when `DELIVERY` |
  | **Prohibited** | — | `delivery_fee`, `total`, `subtotal`, `unit_price`, `line_total`, `discount`, `currency` override, `available_quantity`, `reserved_quantity`, `status`/`payment_status`, `order_reference` (`OD-…`), `cart_id`, `user_id` — all **REJECTED** (`INVALID_VALUE`) if sent; ownership/identity from auth. Unknown fields rejected per `§13.14`. |
- **Fulfillment semantics:** `PICKUP` → `delivery_address: null`, `delivery_fee: {amount:0,currency:"TZS"}`, `delivery_fee_status=FINALIZED` (final at creation), `total` final, `payment: null` until `PAY-001` (still `PENDING_PAYMENT`; `payment` object appears only after `PAY-001` creates it, same as `DELIVERY` — see `payment` field above). `DELIVERY` → delivery_address snapshot stored in Order history (not mutable profile reference), `delivery_fee: null`, `delivery_fee_status=PENDING` (provisional `total` equals `subtotal`; `payment: null` and `PAY-001` blocked with `409 DELIVERY_FEE_PENDING` until finalized). Saved address book deferred — no `saved_address_id`. Enum CLOSED.
- **Delivery-fee authority & timing (CHK-006 / CHK-009):** Customer chooses `DELIVERY` (supplies address); **Staff/Admin add variable location-based fee**; backend stores authoritative `delivery_fee` (`null` → `{amount,currency}` on finalization) + `delivery_fee_status PENDING→FINALIZED`. Checkout never accepts `delivery_fee` from client. **Version 1 timing is Model B (fee-after-order):** `POST /checkout → Order created PENDING_PAYMENT (subtotal authoritative, delivery_fee=null, delivery_fee_status=PENDING, total provisional = subtotal, payment=null blocked) → Staff/Admin sets delivery_fee → delivery_fee_status=FINALIZED, total = subtotal+delivery_fee final, payment becomes eligible → payment (Group H) on final total`. Fee not silently pre-filled as flat `20,000`; payment must equal authoritative final Order total (blocked while `PENDING`). See `api-contract.md §23.6` and `§23.11`.
- **Financial authority (server-controlled):** Client sends intent; server resolves `current catalog unit prices` (recalculated at checkout, not frontend cache), `line totals`, `subtotal`, `delivery_fee` (`null` pending / `{amount,currency}` finalized), `delivery_fee_status`, `total` (provisional `subtotal` when `PENDING`, final `subtotal+delivery_fee` when `FINALIZED` — minor-unit integer `{amount,currency:"TZS"}`), `order_reference OD-…`, `status PENDING_PAYMENT`, `payment` (`null` when `PENDING` and `null` when `FINALIZED` until `PAY-001` — `FINALIZED` only marks payment `eligible`, not present; `payment` object appears only after `PAY-001` creates it, per `payment` field above and `Checkout contract` `PENDING_PAYMENT` `payment null`). `currency` is `TZS` from business config; `{"currency":"USD"}` rejected. Frontend must not display provisional `total` as final (check `delivery_fee_status`).
- **Product/Variant/Inventory revalidation (authoritative, atomic):** `product exists → active && is_published && product_type IN_STOCK → purchasable`; `variant exists && belongs to product (VAR-OWN-001) && active && purchasable`; `requested quantity (1..100) ≤ available authoritative quantity at transaction point`. Race `A sees 1, B buys, A checks out → A fails safely` with no negative stock. `MADE_TO_ORDER` → `PRODUCT_NOT_PURCHASABLE` (422), even if cart somehow contains it. Stale `is_purchasable:false` lines → `CART_INVALID` (422).
- **Cart preconditions:** `cart exists && not empty (CART_INVALID) && no stale item && all quantities valid`. Empty cart → `CART_INVALID` (422, conceptual `CART_EMPTY` maps here).
- **Validation layered pipeline:** `Transport → Schema (type/enum/address) → Auth → Authz(cart owns) → Cart valid (not empty/stale) → Products purchasable → Variants valid → Inventory concurrency-safe → Fulfillment cross-field (DELIVERY→address) → Price server-calculated → Transaction/Idempotency → Persistence (Order creation + inventory reserve/consume + cart clear atomically)`. Failure atomicity: no phantom reservation or incomplete order on error; failed checkout preserves cart.
- **Idempotency & Concurrency:** `IDEMPOTENCY_REQUIRED` — same `Idempotency-Key` replays original `201` with same `order_reference`; same key + different `fulfillment_type`/`delivery_address` → `409 CONFLICT`/`DUPLICATE_OPERATION`; timeout retry uses same key. Inventory + order/cart mutation occur inside protected transaction boundary (mechanism deferred, requirement normative per INV-003).
- **Cart after checkout:** Success → active cart cleared/inactivated (order is record); failure (validation/stock) → cart preserved for adjustment; payment-failure behavior deferred to Group H.
- **Relationship:** `Cart (customer intent, informational pricing, no reservation)` → **`CHK-001` (revalidates inventory, recalculates authoritative pricing, chooses fulfillment, snaps address)** → `Order (historical snapshot, authoritative totals, fulfillment, status history)` → `Payment (Group H)` on final total. Delivery fee finalization happens on Order between creation and payment (Model B).

### 11.5 Order — Actions (Customer vs Staff/Admin)

- **Customer actions:** `cancel` (within 20-min window) — minimal body e.g., `{"reason":...}` optional; no `{status:"CANCELLED"}`.
- **Staff/Admin actions:** `accept`/`ship`/`deliver` — minimal `{"note":...}` where needed; status transitions are actions (`POST /orders/{order}/ship`), not `PATCH {"status":"SHIPPED"}`. **Read-only:** `order_reference`, `status`, `customer_id`, `created_at`, `payment_status`, `final totals`.
- **Validation:** Schema: `reason`/`note` optional string trimmed. State-dependent: cancellation validates `authenticated owner → owns order → exists → cancellation window (backend time, 20-min) → state eligible`; generic `status!=COMPLETED` insufficient. Transitions: each validates `current state, requested transition, actor auth, business preconditions` (e.g., `PROCESSING→SHIPPED` staff + delivery preconditions). No `PATCH {status:...}` bypass (API-VAL-004). Failure atomicity; concurrency Medium/High; audit: `order_status_history` captured. Query: `?order_status`, `?fulfillment_type` etc. CLOSED enum validation; `?status` generic rejected.

### 11.6 Furniture Request & Enquiry (Anonymous or Authenticated) — Phase 1.25 Detail for Request

- **Request create (`REQ-001`):** `product_id` **OPTIONAL (nullable) for V1 per ADR/API-REQ-011** — both `product_id` referencing existing `MADE_TO_ORDER` product and `product_id = null` / omitted (general/custom request) allowed; **not** Required and **not** Absent. When supplied must be `MADE_TO_ORDER` active/published (else `PRODUCT_NOT_REQUESTABLE`). `quantity` integer `1..100` optional (when omitted remains `null` — unspecified, not implicitly `1` — per `api-conventions.md §26.4` and `api-contract.md §26.3`; `null` explicitly indicates no quantity specified), `dimensions` structured `{length,width,height,unit:"cm"}` allow-listed optional, `material` free text optional, `color` free text optional, `notes` free text optional, `name` required + `phone`/`email` (at least one, both valid) — even when authenticated (self-contained record, per ADR/API-REQ-001), optional `attachment` via `multipart/form-data` field `attachment` (preferred inline). **Not accepted:** `user_id`, `request_status`, `staff_internal_notes`, `order_id`, `payment_*`, `delivery_fee`, `created_at`. **Validation:** Schema: `name` trimmed 120, `phone` normalized, `email` lowercased, `quantity` `1..100` strict integer (when supplied), `dimensions` keys strictly `length,width,height,unit` (`unit` exactly `"cm"`), `material` 500, `color` 200, `notes` 5000; attachment `size<=5MB`, types `image/jpeg|png|webp|application/pdf`, signature verified. Domain: `product_id` optional — when supplied `exists && active && is_published && product_type MADE_TO_ORDER` else `PRODUCT_NOT_REQUESTABLE` (409); when `null`/omitted, custom request valid; `quantity` not order allocation and remains `null` when omitted. Auth Optional; `user_id` derived server-side (`null` anonymous, `authenticated principal` when customer); anonymous `user=null` valid. Authorization: PUBLIC create. Concurrency Low; idempotency not required in V1.
- **Request update (`REQ-006` Staff only):** `PATCH /requests/{request}` — mutable only `request_status` (`SUBMITTED`→`IN_REVIEW`→`CLOSED` CLOSED, terminal `CLOSED`) + `staff_internal_notes`. Not mutable: `product_id`, `quantity`, `dimensions`, `material`, `color`, `notes`, `user_id`, `contact snapshot`, `order_id`. Validation: status transition validated against current state; arbitrary status text rejected; internal notes separated.
- **Request attachment (`REQ-007`):** `POST /requests/{request}/attachments` `multipart/form-data` — preferred inline on `REQ-001`; separate POST requires scoped server-issued upload token (single-use/time-limited) + parent ownership; validates same file rules. Private to parent; no permanent public URLs.
- **Enquiry create (`ENQ-001` — Approved V1 Phase 1.26):** `name` (required anonymous, optional/derived authenticated), `phone`/`email` (at least one for anonymous, optional/derived authenticated), `subject` required 5–200, `message` required plain text 10–5000, optional CLOSED `category` (`GENERAL`/`PRODUCT`/`DELIVERY`/`OTHER`), optional `product_id` (any public product, `null`/omitted = general enquiry), optional `order_id` (when supplied by authenticated customer must be owned — ownership-validated, `null`/omitted otherwise), optional `attachment` via `multipart/form-data` field `attachment` (preferred inline, 0 or 1, `<=5MB`, `image/jpeg|png|webp|application/pdf` signature-verified). **Not accepted:** `user_id`, `enquiry_status`, `staff_internal_notes`, `payment_*`/`delivery_fee`/`order_status`, `created_at`. **Validation:** Schema: `name` trimmed 120, `phone` normalized 30, `email` lowercased 255, `subject` 5–200 plain text, `message` 10–5000 plain text (no Markdown/HTML, no script execution, escapes on render), `category` CLOSED when supplied, `product_id` validated public exists when supplied, `order_id` ownership-validated when supplied (another customer's order → `ENQUIRY_NOT_FOUND` 404 masked), attachment same file rules as Request. Domain: `product_id` optional — when supplied `exists && active && is_published && publicly visible`; `order_id` optional — when supplied by customer `exists && owned`. Auth Optional; `user_id` derived server-side (`null` anonymous, `authenticated principal` when customer). Authorization: PUBLIC create. Concurrency Low; idempotency not required in V1.
- **Enquiry update (`ENQ-006` Staff only):** `POST /enquiries/{enquiry}/close` (+ optional `reopen`) — mutable only `enquiry_status` (`OPEN`→`CLOSED`, `CLOSED`→`OPEN` if reopen approved, CLOSED enum) + `staff_internal_notes`. Not mutable: `subject`, `message`, `contact snapshot`, `category`, `product_id`, `order_id`, `user_id`, `payment_*`. Validation: status transition validated against current `enquiry_status`; arbitrary status rejected; internal notes separated; original submission immutable.
- **Enquiry attachment (`ENQ-007`):** `POST /enquiries/{enquiry}/attachments` `multipart/form-data` — preferred inline on `ENQ-001`; separate POST requires scoped server-issued upload token (single-use/time-limited) + parent ownership; validates same file rules (`size/type/signature`). Private to parent; no permanent public URLs; same architecture as `REQ-007`.
- **Validation common:** Unknown fields rejected, `request_status`/`enquiry_status` SERVER-GENERATE never client-settable, authorization contextual (own vs operational), failure atomicity. `subject`/`message` treated as untrusted plain-text (XSS-safe, no HTML/Markdown in V1).

### 11.7 Profile & Cart vs Order

- **Profile (PATCH `/me`):** `name`, `phone` mutable; `email`/`password`/`role`/`account_status` require dedicated workflows, not ordinary `PATCH`. `id`, `created_at`, verification state server-generated. **Canonical profile route is `PATCH /api/v1/me` (not `/me/profile`); `/me/profile` is not a canonical alias in this contract.**
- **Identifiers:** Authenticated requests never require `{"user_id":"current-user"}`; server derives ownership. Anonymous requests must contain contact, not fake `user_id`.
- **Validation:** Schema: `name`/`phone` string trimmed, `phone` normalized, `email` format not mutable via this endpoint. Mutable: `name`, `phone` only (allow-list). Immutable: `email`/`password`/`role`/`account_status`/`id`/`created_at`. Auth required, Authz own profile only.

### 11.8 Payment (Generic)

- **Generic principles only:** Customer does **not** send `{payment_status:"PAID"}` or `{amount:{...}}` as authoritative confirmation; amounts and confirmation are backend/provider-controlled. Provider-specific payloads **deferred to Group H**; no `payment_provider` request schema defined here.
- **Validation note:** Generic transport/schema/auth/authz apply; external-provider verification (webhook signature, idempotency, verification) is Group H, not business-input validation. External failures ≠ input `INVALID_VALUE`.

### 11.9 Common Input & Validation Rules Applied

- `snake_case` (`product_id`, `delivery_address`), strict JSON types (`quantity:2` not `"2"`, booleans not `1`), `null` only where explicitly nullable, unknown fields **rejected** (validation error, not silently ignored) to catch typos/version drift, mass-assignment protection via allow-list (`validated input → DTO → domain`), idempotency-sensitive (`checkout`, `payment initiation`) noted for later key, size limits enforced.
- **Validation extensions (Phase 1.15):** Layered hierarchy `Transport→Schema→Auth→Authz→Domain→Concurrency→External→Persistence`; CLOSED enums (unknown → error); cross-field `fulfillment_type↔delivery_address`; conditional `DELIVERY` required; state-dependent `PROCESSING→SHIPPED` vs `COMPLETED→SHIPPED invalid`; relationship `variant belongs to product && active && purchasable`; server-controlled `id/created_at/order_reference/status/payment_status/inventory/totals/history` REJECT/IGNORE; pricing `SERVER-GENERATE`; inventory concurrency-safe; cancellation 20-min backend time; transitions via actions not generic writes; error categories `INVALID_TYPE/INVALID_VALUE/PRODUCT_NOT_PURCHASABLE/INSUFFICIENT_STOCK/INVALID_ORDER_TRANSITION/AUTHENTICATION_REQUIRED/FORBIDDEN` (canonical; `NOT_AUTHENTICATED`/`RESOURCE_NOT_OWNED` are legacy aliases per `api-contract.md §15.15`) with field-level paths and multiple errors per request (schema all, domain early-stop when unsafe); messages non-leaking, codes stable within `v1` (rename `INSUFFICIENT_STOCK` breaking); logging without secrets; failure atomicity; audit for critical transitions.

### 11.10 Query & Collection Validation (Phase 1.15)

- `page` positive int, `per_page 1–100` (validation error otherwise; missing→default 20). `product_type` CLOSED (`IN_STOCK`/`MADE_TO_ORDER`), `fulfillment_type` CLOSED, `order_status`/`request_status`/`enquiry_status`/`payment_status` CLOSED (not `payment_state`), `sort` allow-list (`created_at`, `price`, `name` plus tie-breaker `id ASC`), `min_price`/`max_price` minor-unit integers with `min≤max`. Filter fields allow-list only. All query enums CLOSED; `?availability=IN_STOCK` is error (use `available|unavailable`); `?status` generic rejected. Strict type for `quantity`, `page`, etc.; not `"2"` strings.

### 11.11 Validation Matrix Reference

See `api-contract.md §14.17` for operation-level matrix (`Browse/Add cart/Checkout/Cancel/Request/Enquiry/Profile/Ship`). Each operation respects same layered validation. Frontend validation advisory; backend authoritative. Payment provider-specific deferred to Group H; no FormRequest/DTO/migration/OpenAPI schema implemented here.

## 12. Error Considerations per Resource (Phase 1.16)

> Each row lists the **subset of `api-contract.md §15.15` codes the resource may return** — not an exhaustive future-proof list. Endpoint-specific HTTP/code mapping is documented per endpoint later; do not invent undocumented `CHECKOUT_FAIL` codes locally (`api-contract.md §86`). All errors follow `{"errors":[{"code","message","field","details"}],"meta":{"request_id":...}}` with `meta.request_id` even for 500. Payment-specific extensions remain Group H.

### 12.1 Product & Category (PUBLIC, Staff/Admin mutations)

| Concern | Codes | HTTP | Notes |
|---|---|---|---|
| Unknown product/category, slug not addressable | `PRODUCT_NOT_FOUND`, `RESOURCE_NOT_FOUND` | 404 | Not `data:null` |
| `MADE_TO_ORDER` added to cart / checkout | `PRODUCT_NOT_PURCHASABLE` | 422 | Distinct from `PRODUCT_UNAVAILABLE`; display price exists but prohibited (§45) |
| Variant mismatch / inactive / not purchasable | `INVALID_PRODUCT_VARIANT` | 422 | Includes `belongs_to_product`/`active` checks; `field: variant_id` |
| Schema on create/update: name/slug/type/price | `MISSING_REQUIRED_FIELD`, `INVALID_VALUE`, `INVALID_FORMAT`, `INVALID_TYPE` | 422 | Multiple determinable errors returned together; dot paths; unknown fields → 422 |

### 12.2 Cart (Holder-scoped)

| Concern | Codes | HTTP |
|---|---|---|
| Cart not found / holder mismatch | `CART_NOT_FOUND` | 404 |
| Item not found in cart | `CART_ITEM_NOT_FOUND` | 404 |
| Invalid `product_id`/`variant_id`/`quantity` | `INVALID_VALUE`, `INVALID_TYPE`, `MISSING_REQUIRED_FIELD`, `INVALID_PRODUCT_VARIANT` | 422 |
| Item unavailable / type not purchasable | `CART_ITEM_UNAVAILABLE`, `PRODUCT_NOT_PURCHASABLE` | 422 |
| Empty `items:[]` where ≥1 required, duplicate semantics | `MISSING_REQUIRED_FIELD` / `INVALID_VALUE` + `field: items` | 422 |

### 12.3 Checkout (Authenticated)

| Concern | Codes | HTTP | Notes |
|---|---|---|---|
| Missing/invalid auth | `AUTHENTICATION_REQUIRED` (canonical; `CHECKOUT_REQUIRES_AUTHENTICATION` is alias — identical 401 semantics) / `INVALID_AUTHENTICATION` | 401 | Authenticate — anonymous checkout uses canonical `AUTHENTICATION_REQUIRED` |
| Cart invalid / empty | `CART_INVALID` | 422 | Correct cart |
| Cart not found / holder mismatch (ownership) | `CART_NOT_FOUND` / `RESOURCE_NOT_FOUND` | 404 | **Masked** — holder-scoped cart is private; holder mismatch returns 404, never 403 `FORBIDDEN`, to avoid revealing another holder’s cart exists (per `api-contract.md §15.8`) |
| Fulfillment cross-field | `INVALID_FULFILLMENT`, `INVALID_DELIVERY_INFORMATION` + `field: fulfillment_type` / `field: delivery_address.city` | 422 |
| Stock race / business invalid | `INSUFFICIENT_STOCK` (409/422*), `PRODUCT_NOT_PURCHASABLE`, `INVALID_PRODUCT_VARIANT` | 409 vs 422 per §15.10; `details: {available_quantity}` safe |
| Idempotency duplicate (same `Idempotency-Key`) | — (no `errors`; replays original success response) | 201 (replay) | Deterministic replay of original `201`/`200` with same `data` and `order_reference`; no new order, no phantom reservation; `DUPLICATE_OPERATION` 409 is **not** used for Checkout idempotency replay (see `api-contract.md §15.10` — Checkout chooses replay over 409) |

### 12.4 Order (Customer `me/orders`, Staff/Admin)

| Concern | Codes | HTTP | Notes |
|---|---|---|---|
| Not found / not owned (customer probing) | `ORDER_NOT_FOUND` / `RESOURCE_NOT_FOUND` | 404 | **Masked** — not `403` to avoid enumeration (`api-contract.md §15.8`) |
| Cancel outside window / state ineligible | `ORDER_NOT_CANCELLABLE` | 422/409* | `details` may include reason; not `order.created_at` leak beyond safe state |
| Invalid transition `PATCH {status}` attempt | `INVALID_ORDER_TRANSITION` | 409/422* | Only `POST /orders/{order}/cancel|ship|deliver` allowed; generic `status` write rejected |
| State conflict / concurrent update | `ORDER_STATE_CONFLICT`, `CONFLICT`, `RESOURCE_VERSION_CONFLICT` | 409 | Client must refresh/reconcile then retry |
| Query `?order_status`, `?fulfillment_type` invalid | `INVALID_VALUE` | 422 | CLOSED enums; `?status` generic rejected |

### 12.5 Furniture Request & Enquiry (Anonymous or Auth)

| Concern | Codes | HTTP |
|---|---|---|
| Missing contact / message / subject | `MISSING_REQUIRED_FIELD`, `INVALID_VALUE` | 422 | `field: contact.email` etc.; multiple errors together |
| Anonymous `user=null` missing — **not** error | — | — | Anonymous valid (REQ-001) |
| Invalid attachment (size/type/signature) | `INVALID_ATTACHMENT`, `ATTACHMENT_TOO_LARGE`, `UNSUPPORTED_ATTACHMENT_TYPE` | 422 / 413 | Safe message, no path leak |
| Not found (lookup) | `REQUEST_NOT_FOUND`, `ENQUIRY_NOT_FOUND` | 404 | Own-record visibility only |

### 12.6 Payment (Generic)

- Generic envelope only: `EXTERNAL_SERVICE_ERROR` family + `INTERNAL_SERVER_ERROR` on unexpected failure. No provider `error string` exposed; Group H will define `payment-specific` codes without altering `errors`/`meta.request_id` shape. Webhook idempotency duplicates → prior result, not second order/payment.

### 12.7 Cross-Cutting

- **Validation:** All fields may also return `INVALID_TYPE`/`INVALID_FORMAT`/`INVALID_VALUE`/`MISSING_REQUIRED_FIELD` with `field` dot path. Unknown fields → 422.
- **Auth:** `AUTHENTICATION_REQUIRED` (401) vs `FORBIDDEN` (403) kept distinct; 401 never masks as 403. Private-resource 404 masking per `api-contract.md §15.8` (and cart holder 404 per §11.3) prevents enumeration.
- **Rate limit:** Any operation may return `RATE_LIMITED` 429 with `Retry-After` header; body follows same envelope.
- **Internal:** Unexpected failure → `500 INTERNAL_SERVER_ERROR` + `meta.request_id` only; full stack stays in server logs, never in `message`/`details`.
- **Pagination/query** `page/per_page` out of range, `sort`/`filter` allow-list miss → 422 `INVALID_VALUE` with `field` indicating param.

## 8. User/Profile — Self-Service Account (Phase 1.28)

> **Authority:** This section is the canonical **User/Profile resource** for V1 self-service. It defines self-service representation, staff/admin boundary, ownership, relationships, read-only/mutable/sensitive classification per `phase-1.28.md`. Endpoint inventory is authoritative in `api-contract.md §29`; conventions in `api-conventions.md §29`.

### 8.1 User/Profile — Self-Service Representation (`USER-001` / `USER-002`)

**Self-service boundary:** `GET /api/v1/me` and `PATCH /api/v1/me` are the **only** Customer/Staff/Admin self-service profile surface. Identity is derived from `authenticated principal`; `user_id` query/body is never trusted.

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string opaque `user_...` | Recipient only (own) | no | Stable opaque, server-generated; never client-supplied or mutable |
| `role` | enum `CUSTOMER`/`STAFF`/`ADMIN` CLOSED | Recipient only (own) read-only | no | Server-controlled; `UPPER_SNAKE_CASE`; `PATCH /me {role:ADMIN}` rejected |
| `name` | string | Own | no | Trimmed non-empty, length validated; mutable via `PATCH /me` allow-list |
| `email` | string | Own | no | Lowercased/trimmed unique; read via Profile, **change via dedicated security workflow** (`POST /auth/*`), not ordinary `PATCH` |
| `phone` | string \| null | Own | yes | Normalized; nullable if business permits clearing; mutable via `PATCH /me` allow-list; `phone_verified` (if later) server-controlled |
| `email_verified` | boolean | Own | no | Server-controlled read-only alias for `email_verified_at`; client `{"email_verified":true}` rejected; derived from `email_verified_at: ISO8601 Z \| null` authoritative attribute |
| `created_at` | ISO8601 `Z` | Own | no | Server-generated |
| `updated_at` | ISO8601 `Z` | Own | no | Server-generated |

**Not serialized (even to Admin via normal `/me`):** `password`, `password_hash`, `authentication tokens`, `refresh tokens`, `reset tokens`, `verification secrets`, `session secrets`, `security answers`, `provider secrets`, `permissions`/`authorization flags`, `internal staff notes`/`internal account flags`, `payment credentials`.

**Lightweight:** `GET /me` contains **only account data** — not `orders[]`, `cart`, `requests[]`, `enquiries[]`, `notifications[]`, `addresses[]`/`saved_addresses[]` (deferred), `notification preferences`. Those via `GET /me/orders`, `GET /me/cart`, `GET /me/requests`, `GET /me/enquiries`, `GET /me/notifications`. Historical `Order`/`Request`/`Enquiry` snapshot (`delivery_address`, `contact`) is preserved separately — profile change never rewrites those.

### 8.2 Staff/Admin Profile Representation Boundary

| Data | Customer `GET /me` | Staff `GET /me` (own) | Staff operational view of Customer (via `Order`/`Request`/`Enquiry`) | Admin `GET /me` or `GET /users/{user}` where authorized |
|---|---|---|---|---|
| `id`, `name`, `email`, `phone`, `role`, `email_verified`, `created_at/updated_at` | **Own** | **Own** (same fields: `id`, `role:STAFF`, `name`, `email`, `phone`, `email_verified`) | **Operational only** — customer contact/order snapshot (`recipient_name`, `phone`, `delivery_address`) relevant to `Order` fulfillment, not unrestricted profile browse | **Own** plus authorized `GET /users/{user}` where `users.manage_authorized` (Phase 1.29) |
| `STAFF/ADMIN → another Customer profile via /me` | No | **No** | No | Only via `ADM-005/006` authorized endpoint, not self-service |
| `role` management | No | No | No | Authorized separate (`staff.approve`/`staff.manage`) |

Self-service `PATCH /me` is **own only** for all roles; Staff cannot edit Customer profile through `/me` and cannot restrict customer browsing/ordering.

### 8.3 Ownership & Relationships (Logical)

- **Ownership:** `User 1 — * Order`, `User 1 — * Notification`, `User 1 — 1 Cart (active, holder-scoped)`, `User 1 — * Request`, `User 1 — * Enquiry` (authenticated) — `User → resource owner == authenticated principal` required for owner-based resources (`api-contract.md §29` + `§18.3`). Anonymous `Request`/`Enquiry` have `user_id = null` and are not owner-matchable via `email`.
- **Cross-platform identity:** `Next.js Website` and `Flutter App` share **same `User` identity** (`api-contract.md §29.9`) via Laravel backend; `Customer changes phone on website → server updated → Flutter GET /me sees same`.
- **Historical preservation:** Changing `name`/`phone` via `PATCH /me` does **not** transfer or rewrite historical `Order customer`, `Request contact snapshot`, `Enquiry contact snapshot`, `Notification target`. Those remain immutable once created.

### 8.4 Field Classification (Normative — Phase 1.15 layered validation)

| Classification | Fields | `GET /me` | `PATCH /me` (`USER-002`) | Dedicated workflow |
|---|---|---|---|---|
| **Server-controlled identity** | `id`, `created_at`, `updated_at` | Read | **No** (rejected `INVALID_VALUE`) | — |
| **Server-controlled authorization** | `role`, `permissions`, `account_state`/`staff_approval_state`/`suspension`/`disablement` | Read (`role`/`account_state` where relevant) | **No** — `PATCH {role: ADMIN}` must fail | Admin `users.manage_authorized` / `staff.manage` (Phase 1.29) |
| **Server-controlled verification** | `email_verified` / `email_verified_at` | Read | **No** — `{"email_verified":true}` rejected | `AUTH-006/007` secure verification |
| **Mutable profile (allow-list)** | `name`, `phone` | Read | **Yes** — partial `PATCH`; omission leaves unchanged; `null` only if contract explicitly permits per field | — |
| **Security-sensitive contact** | `email` | Read | **Prefer dedicated security operation** — treat as identity/auth, not ordinary profile (`api-contract.md §29.7`): authenticated request → security confirmation → new email verification → changed | `POST /auth/change-email` or equivalent (deferred specifics) |
| **Credentials (never serialized)** | `password`/`password_hash`/tokens/secrets | **Never** | **Never** via `PATCH /me` — separate `POST /auth/change-password` (`AUTH-*` canonical) with `current_password` verification | `POST /auth/change-password` |
| **Sensitive never via Profile** | `payment credentials`, `provider secrets` | Never | Never | `Group H` / `Group R` |

Mass-assignment must be prevented — only allow-listed fields may be updated; unknown fields rejected per `api-contract.md §15.15` strict `unknown-field → 422` rule.

### 8.5 Privacy, Caching & Validation Notes

- **Privacy:** `GET /me` is `PRIVATE` — `Cache-Control: private, no-store` (not CDN, not public catalog cache). `PATCH /me` is non-cacheable mutation. Customer must not see `internal staff notes`/`internal flags`; Staff must not see Admin-only fields via operational view; Admin still does not see credentials.
- **Validation (schema → domain → authz):** `name → valid string/length` (trimmed, collapses whitespace), `phone → approved format` (normalized, max 30, not auto `phone_verified:true`), `email → valid format` (lowercased) but change via security workflow. Do not perform business authorization through field validation. `INVALID_VALUE`/`INVALID_FORMAT`/`MISSING_REQUIRED_FIELD` per `api-contract.md §15`.
- **Cross-references:** Validation `api-contract.md §29.6/§29.13`; authorization `api-contract.md §29.12`; error codes `AUTHENTICATION_REQUIRED` (401), `INVALID_VALUE` (422 for immutable fields), `FORBIDDEN` (403) where appropriate; business-rules `business-rules.md §14`.

## 10. Staff/Admin Operational Resources — Privileged Boundaries (Phase 1.29)

> **Authority:** This section defines **operational resource representations** that are distinct from public customer views. Each resource distinguishes `Public Catalog Resource` vs `Operational Product Resource` vs `Inventory Resource` etc. For every resource: `owner`, `privileged readers/writers`, `customer visibility`, `immutable/server-controlled fields`, `state machine` if applicable, `audit requirements`. This complements `§1` Product, `§2` Category, `§3` Order, `§4` Cart, `§5` Request, `§6` Enquiry, `§7` Notification, `§8` User/Profile.

### 10.1 Operational Product Resource (distinct from Public Product §1)

| Aspect | Contract |
|---|---|
| **Owner** | System (catalog) |
| **Privileged readers** | `STAFF`/`ADMIN` with `products.manage` where approved — via `CAT-007..012` operational views and `INV-002` inventory-linked product operational detail |
| **Privileged writers** | `STAFF`/`ADMIN` with `products.manage` — controlled `POST /products` / `PATCH /products/{product}` / `POST .../images` / `POST .../variants` |
| **Customer visibility** | **Not exposed** — public `GET /products` (§1) shows `PUBLIC` fields only (`id/name/slug/description/product_type/price/category/images/variants/availability/stock_indicator`); operational flags `is_active/is_published` internal, `reserved_quantity/available_quantity/supplier` never public |
| **Immutable** | `id`, `created_at`, `availability`/`stock_indicator` derived, `reserved_quantity` server-derived |
| **Server-controlled** | `id`, `slug` uniqueness server, `created_at`/`updated_at`, `availability`/`stock_indicator`, inventory quantities |
| **State** | `is_active` / `is_published` publication state (existing §21 model); no invented `DRAFT` vs `ARCHIVED` beyond approved |
| **Audit** | Privileged catalog changes audited (`actor/old/new/timestamp`) |

### 10.2 Inventory Resource (Operational, not Customer-Editable)

| Aspect | Contract |
|---|---|
| **Owner** | System (operational) |
| **Privileged readers** | `STAFF`/`ADMIN` `inventory.view` — `GET /inventory` (`INV-001`), `GET /inventory/{inventory}` (`INV-002`, by product/variant) |
| **Privileged writers** | `STAFF`/`ADMIN` `inventory.manage` — `POST /inventory/{inventory}/adjust` (`INV-003`) only; no `PATCH {quantity:999}` |
| **Customer visibility** | **None** — public catalog shows `availability`/`stock_indicator` only (coarse); `quantity/reserved_quantity/available_quantity` never customer-visible |
| **Immutable** | Derived `available_quantity = quantity - reserved_quantity` (read-only) |
| **Server-controlled** | `quantity`, `reserved_quantity`, `available_quantity`, `updated_at`; server calculates `new_quantity = current + quantity_delta` transactionally; prevents negative where business prohibits |
| **Validation** | `quantity_delta` integer, `reason` CLOSED (`STOCK_RECEIPT/CORRECTION/DAMAGE/RETURN/AUDIT_ADJUSTMENT`), resulting `new_quantity >=0`, concurrent revalidation inside transaction |
| **Audit** | Every `INV-003` adjustment audited (`actor/inventory/old/new/reason/occurred_at`); concurrency `Critical` |
| **Field exposure** | `quantity` = integer units (not TZS minor units) |

### 10.3 Order Operational Resource (extends §3 Order historical)

| Aspect | Contract |
|---|---|
| **Owner** | `Customer` (customer-owned), operationally managed by `STAFF`/`ADMIN` |
| **Privileged readers** | `STAFF`/`ADMIN` `orders.view_operational` — `GET /orders` (`ORD-005`) + `GET /orders/{order}` (`ORD-006`) + `GET /orders/{order}/tracking` (`ORD-012`) |
| **Privileged writers** | `STAFF`/`ADMIN` `orders.accept/process/ready_for_pickup/ship/deliver/complete/set_delivery_fee` — controlled `POST .../accept` etc. (`ORD-007..011,013,014`), never `PATCH {status}` |
| **Customer visibility** | Customer sees **own** orders only via `GET /me/orders` (§3); customer-facing representation excludes `customer contact beyond own`, `internal notes` where not own |
| **Immutable** | `id`, `order_reference`, `customer owner`, `items quantity/historical unit_price/line_total`, `created_at`, `status_history` append-only; `fulfillment_type` generally immutable; `historical financial` immutable after `FINALIZED`/`PAID` |
| **Server-controlled** | `id`, `order_reference` (`OD-...`), `status`, `subtotal/total` authoritative, `delivery_fee_status`, `status_history`, `timestamps`, `actor` |
| **State machine** | `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` (PICKUP) and `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED` (DELIVERY), `CANCELLED` terminal; fulfillment-branch validated (`READY_FOR_PICKUP` never → `DELIVERED`); `delivery_fee PENDING→FINALIZED` via `ORD-014` before `PAID` |
| **Audit** | Every state transition + delivery-fee change audited (`actor/previous→new/timestamp/request_id`) |
| **Sensitive never** | `password`, `tokens`, `payment secrets`, `raw card` |

### 10.4 Made-to-Order Request Operational Resource

| Aspect | Contract |
|---|---|
| **Owner** | Customer (or `null` anonymous) — staff operational relationship, not owns |
| **Privileged readers** | `STAFF`/`ADMIN` `requests.view` — `GET /requests` (`REQ-004`), `GET /requests/{request}` (`REQ-005`, includes `staff_internal_notes` where authorized) |
| **Privileged writers** | `STAFF`/`ADMIN` `requests.manage` — `PATCH /requests/{request}` (`REQ-006`) only `request_status` (`SUBMITTED→IN_REVIEW→CLOSED`) + `staff_internal_notes`; original `product_id/quantity/dimensions/material/color/notes/contact` immutable |
| **Customer visibility** | Customer sees own requests `GET /me/requests` (§5); never internal notes |
| **Audit** | `request_status` changes audited |

### 10.5 General Enquiry Operational Resource

| Aspect | Contract |
|---|---|
| **Owner** | Customer (or `null` anonymous) — staff operational, not owns |
| **Privileged readers** | `STAFF`/`ADMIN` `enquiries.view` — `GET /enquiries` (`ENQ-004`), `GET /enquiries/{enquiry}` (`ENQ-005`) |
| **Privileged writers** | `STAFF`/`ADMIN` `enquiries.manage` — `POST /enquiries/{enquiry}/close` (`ENQ-006`) only `enquiry_status` (`OPEN→CLOSED`) + `staff_internal_notes`; original `subject/message/contact/category/product_id/order_id` immutable |
| **Customer visibility** | Customer sees own enquiries `GET /me/enquiries` (§6); never internal notes |
| **Audit** | `enquiry_status` changes audited |

### 10.6 Notification Operational Resource

| Aspect | Contract |
|---|---|
| **Owner** | `recipient_user_id` (server-derived) |
| **Privileged readers** | `STAFF`/`ADMIN` `notifications.view_operational` — `GET /me/notifications` filtered `type: NEW_ORDER` etc. (operational queue, recipient-scoped) |
| **Privileged writers** | **None for normal Staff/Admin** — notifications generated from business events (`ORDER_SHIPPED` etc.), not manual `POST /notifications {recipient:...}`; if manual notification exists it is a separately contracted controlled action |
| **Customer visibility** | Customer sees own `GET /me/notifications` (§7) |
| **Sensitive** | Never `channel` until implemented, no delivery logs, no secrets |

### 10.7 User/Staff Resource (Admin-Managed)

| Aspect | Contract |
|---|---|
| **Owner** | System (identity) — each User owns own account via `/me` (§8); Admin manages staff lifecycle |
| **Privileged readers** | `ADMIN` `staff.manage`/`users.manage_authorized` — `GET /admin/staff` (`ADM-001`), `GET /admin/staff/{user}` (`ADM-002`), `GET /users` (`ADM-008`), `GET /users/{user}` (`ADM-009`) |
| **Privileged writers** | `ADMIN` `staff.approve`/`staff.manage` — `POST /admin/staff` (`ADM-003` invite), `POST .../approve` (`ADM-004`), `POST .../suspend` (`ADM-005`), `POST .../reactivate` (`ADM-006`) — all audited, no self-approval, role `STAFF` server-controlled |
| **Customer visibility** | Customer sees own `GET /me` only; never staff list |
| **Staff visibility** | Staff sees own `GET /me` only; not customer browse |
| **Server-controlled** | `id`, `role`, `permissions`, `account_state`, `timestamps`, `verification state` never via `PATCH /me` |

### 10.8 Audit Resource (internal, optionally exposed read-only)

| Aspect | Contract |
|---|---|
| **Owner** | System |
| **Privileged readers** | `ADMIN` `audit.view` where approved — `GET /admin/audit-logs` (`ADM-007`) read-only, filters `actor/action/resource_type/resource_id/created_from/to`, `PRIVATE`/`no-store`, `meta.pagination`; otherwise `internal-only V1` with no read endpoint |
| **Privileged writers** | **None via ordinary API** — audit events generated server-side from privileged actions; no `PATCH/DELETE /audit-logs` |
| **Customer visibility** | **None** |
| **Audit creation mandatory** | All `staff approval/suspend/reactivate`, `role changes`, `delivery-fee changes`, `inventory adjustments`, `order transitions`, `request/enquiry status changes`, `privileged catalog changes` produce event (`actor_id/role/action/resource_type/resource_id/previous_state/resulting_state/timestamp/request_id`) |

## 13. Authentication & Account Resources — Conceptual (Phase 1.17, No Endpoints)

> No endpoint definitions here; this section documents the **conceptual resources** that the authentication contract will later expose via endpoints. Field-level schemas and routes belong to implementation phases. All role values are CLOSED `CUSTOMER`/`STAFF`/`ADMIN`.

### 13.1 User / Profile (Account) — Conceptual Anchor (Detailed in §8)

| Field | Type | Exposure | Auth | Notes |
|---|---|---|---|---|
| `id` | string (opaque) | CUSTOMER `own` / Admin `authorized` | authenticated | Opaque identifier, not DB leak |
| `name` | string | own / authorized admin | authenticated for own; Staff not browse-as-customer | Trimmed, non-empty; **mutable via `PATCH /me` allow-list** (`§8`) |
| `email` | string | own / authorized admin | authenticated | Lowercased/trimmed, unique; not client-set `email_verified`; **change via security workflow, not ordinary `PATCH`** (`§8`/`§29.7`) |
| `phone` | string \| null | own / authorized admin | authenticated | Normalized; important for orders/delivery but not auth verification by default; **nullable, mutable via `PATCH /me`** (`§8`) |
| `role` | enum `CUSTOMER`/`STAFF`/`ADMIN` CLOSED | own (own role) / authorized admin | authenticated | Server-controlled; client self-promotion rejected; **read-only via Profile** (`§8`) |
| `email_verified` | boolean | own | authenticated | Server-controlled read-only alias for `email_verified_at` (`email_verified_at: ISO8601 Z \| null` authoritative backing attribute, not wire field); client `{"email_verified":true}` rejected |
| `created_at` / `updated_at` | ISO8601 `Z` | own | authenticated | Audit; **server-controlled** |

**Rules:** Customer is primary actor with full ownership of own account; `STAFF`/`ADMIN` do not own customer accounts (see `api-contract.md §17.1` / `§29`). Profile `PATCH` (`USER-002`) is **allow-listed (`name`, `phone`)** — not `role`, `email_verified_at`, `password`, `account_state` (dedicated workflows). `email` change is **security-sensitive** — not ordinary `PATCH` (`§29.7`). No `password`/`password_hash` ever serialized. For full V1 resource representation, field matrix, and security boundary see `§8`.

### 13.2 Authentication (Conceptual Resource, Not Serialized Secrets)

- **Concepts:** `registration` (public self-registration → `CUSTOMER`), `login` (shared identity across Website/Flutter/Admin, same Laravel backend), `logout` (invalidates server session/credential), `password reset` (secure single-use time-limited token; email delivery Group R deferred; generic “Request received.” to prevent enumeration), `email verification` (secure token → `email_verified_at`; phone/SMS OTP not required).
- **Representation principles (when endpoints later defined):** Success uses `data` envelope per `api-contract.md §2/§17.13`; not `auth_success`/`login_result`. Error uses `errors` per §15: `AUTHENTICATION_REQUIRED` vs `FORBIDDEN` distinct, `INVALID_CREDENTIALS`/`SESSION_EXPIRED`. No `password`/`hash`/`reset_token`/`session_token` in any resource payload.

### 13.3 Session / Credential (Conceptual, Not Directly Exposed)

| Concern | Concept | Notes |
|---|---|---|
| `Next.js` | first-party browser session (secure httpOnly cookie) | Not long-lived JS secret; suitable for SSR; no auth on public catalog routes |
| `Flutter` | API credential/token | Same identity as web; cross-platform no duplicate accounts |
| `Admin` | administrative session | Same backend system, stronger controls (shorter idle, revocation, visibility, MFA/audit later) |
| Lifecycle | `active → expired → revoked` (conceptual) | Multi-device permitted for Customer; logout on one does not kill others; revocation via logout/password change/admin action |
| Threats identified | `credential theft`, `stuffing`, `brute-force`, `session theft`, `token leakage`, `enumeration`, `privilege escalation`, `role tampering`, `session fixation`, `password-reset abuse`, `cross-account`, `staff impersonation` | Mitigations deferred to implementation but boundaries fixed here |

Do not define endpoints, Sanctum mechanics, hashing, or middleware here; see `api-contract.md §17.15` deferred.

## 14. Authorization per Resource — Who May Do What (Phase 1.18, No Endpoints Yet)

> No endpoint URLs here — authoritative endpoint inventory is Phase 1.19; this section records for each resource **who may read/create/update/delete/perform actions, ownership rule, and sensitive fields**. Any `GET /…` or `POST /…` shown in *Concern* column are **non-normative illustrative examples** only, not approved contracts. All role values are CLOSED `CUSTOMER`/`STAFF`/`ADMIN`; do not add `MANAGER` etc. Authorization is **deny by default** — `PUBLIC` resources explicitly classified; all else requires auth + policy. See `api-contract.md §18` for normative model.

### 14.1 Products & Categories (Catalog — PUBLIC Read, Staff/Admin Manage)

| Concern | Anonymous | Customer | Staff | Admin | Ownership / Sensitivity |
|---|---|---|---|---|---|
| Read products / View product details / List categories (public catalog) — *e.g., `GET /products` non-normative example; endpoints deferred to Phase 1.19* | **PUBLIC** Allow | Allow | Allow | Allow | `access: PUBLIC` — no auth, CDN cacheable; deny-by-default exception is intentional; paths are illustrative, not normative contracts |
| `GET` public fields (`price`, `availability`) | Yes | Yes | Yes | Yes | `PUBLIC` — price/availability public per §18.11 |
| `POST/PUT/PATCH` product, `manage images/variants` (catalog CRUD, no stock) | Deny (401) | Deny (403) | **Allow only with `products.manage`** where approved | **Allow** (explicit) | `products.manage` authorizes catalog CRUD + image/variant management only — not stock-quantity writes; per-permission, not `*` |
| `PATCH` inventory/stock quantity (`/products/{product}/inventory`, stock adjustment) | Deny (401) | Deny (403) | **Allow only with `inventory.manage`** where approved | **Allow** (explicit) | `inventory.manage` authorizes stock-quantity adjustments only — not catalog CRUD; auditable per `api-contract.md §18.6`, separate capability |
| Internal `reserved_quantity`, `supplier` fields | No | No | According to permission | Yes (when operationally necessary) | `PRIVATE` not public product; never to customer |

### 14.2 Cart (Holder-Scoped, Owner-Bound)

| Concern | Who | Rule | Sensitive |
|---|---|---|---|
| Read own cart / add item / update quantity | `AUTHENTICATED_OWNER` — `CUSTOMER` own cart only | `cart.owner == authenticated principal`; client `user_id`/`cart.owner` tampering rejected; `MADE_TO_ORDER` → `PRODUCT_NOT_PURCHASABLE` | Client totals never authoritative |
| Anonymous cart → login merge | **Mandatory** per `business-rules.md §3 #4` | `guest cart MUST move onto user cart` on login (backend merges, preserving ownership); only conflict handling (duplicate product, quantity merge, limits) deferred | Backend is authority |
| Any other holder's cart | Deny 404 `CART_NOT_FOUND`/`RESOURCE_NOT_FOUND` (masked, never 403) | Holder-scoped private, enumeration protection per `§15.8`/`§18.13` | |

### 14.3 Orders, Tracking, Cancellation (Customer-Owned + Operational Staff)

| Concern | Anonymous | Customer | Staff (Operational) | Admin |
|---|---|---|---|---|
| `GET /me/orders`, `GET /me/orders/{order}` (read own) | Deny 401 | **Allow** `AUTHENTICATED_OWNER` (`owns order`) — `401` vs `403` vs `404` masking per §15.8 | Deny as owner (not own) | As authorized (purpose-bound, not unrestricted) |
| `GET` operational orders (staff view) | Deny | Deny | **Allow** `orders.view_operational` (sees operational fields only, not `password`/`token`) | Allow |
| `POST /orders/{order}/cancel` (customer cancel) | Deny | **Allow** `cancel_own` only if `owns + eligible state + 20-min window + backend state check` (see §18.9) | Deny as customer (staff cannot cancel as customer) | Authorized admin operation only (security policy, audited) |
| `POST /orders/{order}/accept|process|ready|ship|deliver` (state) | Deny | Deny (cannot `PATCH {status}`) | **Allow** `orders.accept` + `Order=PAID` (`accept: PAID→ACCEPTED`, ORD-007), `orders.process` + `Order=ACCEPTED` (`ACCEPTED→PROCESSING`, ORD-008), `orders.ready_for_pickup` + `Order=PROCESSING` (pickup, `PROCESSING→READY_FOR_PICKUP`, ORD-009), `orders.ship` + `Order=PROCESSING` (delivery, `PROCESSING→SHIPPED`, ORD-010), `orders.deliver` + `Order=SHIPPED` (`SHIPPED→DELIVERED`, ORD-011) — each requires explicit permission **and** valid state | Allow with same permission + state check; even Admin respects business-state unless explicit correction workflow (see `api-contract.md §19.1` ORD-007/009, §18.6) |
| `GET` tracking / `GET` payment limited / `GET` delivery for own order | Deny | **Own** only (customer-facing fields) | Operational | Authorized |
| Pagination/search over `/me/orders` | — | **Authorized dataset** (`Customer A` only) | Operational dataset | — |
| Sensitive fields | — | `historical order price` own | `customer phone/delivery address` operational | `internal staff note` authorized; never `payment provider secret` |

**Deny examples:** `Customer A → Order B` must fail despite authenticated (object-level); `STAFF → change customer password` must fail; `?user_id=another` must not expand scope.

### 14.4 Furniture Requests & Enquiries (Anonymous Submit, Owner/Operational View)

| Concern | Anonymous | Customer (authed) | Staff | Admin |
|---|---|---|---|---|
| `POST /requests`, `POST /enquiries` (submit) | **Allow** `PUBLIC` + input validation + anti-abuse later | Allow (→ `Request→User` for later `My Requests`) | Operational access later (not via anonymous) | Operational |
| `GET` own requests/enquiries | — (no User) controlled anonymous access model deferred (no weak bearer-ID) | **Allow** `own` where supported | **Allow** `requests.view`/`manage`, `enquiries.view`/`manage` (sees contact, not `password`) | Allow |
| `GET` another’s request/enq or `/enquiries/{id}` public | Deny (private, not globally readable) | Deny | Deny unrelated | As authorized |
| Attachments (`Request → Attachment`) | Private to parent | Private to parent | Inherits parent (if cannot access parent, cannot access attachment) | Inherits parent | No predictable public paths |

### 14.5 Notifications (Owner vs Operational, Not Merged)

- **Customer:** `read` only own intended notifications; `GET /notifications/{another-user-notification}` must fail by ID. Owner-based, authorization-filtered search.
- **Staff:** `view_operational` only operational notifications (`new order`, `new request`, `payment event`) — not private customer-account info unrelated to task.
- **Admin:** administrative only where required, not merged with customer rule; same envelope.

### 14.6 Payment, Delivery, Inventory (Generic + Group H)

- **Payment:** `Customer` sees limited own payment (`payment_status`, `amount` without secrets); `Staff` operational as needed; `Admin` authorized admin; `provider secrets` remain `INTERNAL` never to any role via API. Payment-specific auth is Group H.
- **Delivery:** `Customer` own order’s delivery; `Staff` operational delivery; `Admin` authorized. No public exposure of all deliveries.
- **Inventory:** `Public` → `availability` (e.g., `IN_STOCK` indicator); `Staff/Admin` → `inventory operations` (`inventory.view`/`manage` where approved, auditable); never `historical order item quantity` edit via inventory permission. Internal `reserved_quantity` per §18.11.

### 14.7 User / Profile, Staff, Roles (Account & Admin)

| Resource / Action | Anonymous | Customer | Staff | Admin | Rule |
|---|---|---|---|---|---|
| `POST /register` (customer) | **Allow** | N/A (already authed) | Deny | Deny | Public self-registration only |
| `GET /me`, `PATCH /me` own `name`/`phone` | Deny 401 | **Allow own** (allow-list `name`/`phone` only, not `role`/`password`/`email_verified_at`/`account_status`; canonical `PATCH /api/v1/me`, legacy `/me/profile` **not canonical**) | Allow own (own profile) | Allow own | `user_id` swapping, `role` mass-assignment rejected |
| `GET` another’s profile / `GET /users/{id}` | Deny | Deny (own only) | Deny (no browse-as-customer) | As authorized purpose-bound (review, not impersonation) | Customer owns account |
| `POST` password change | Deny | **Allow own** via secure authenticated workflow (not `PATCH /me {password}`) | Own only | Own only | Security-sensitive |
| `POST` password reset (anonymous) | Allow (public endpoint + anti-abuse) | — | — | — | Generic `"Request received."` |
| `Approve staff` | Deny | Deny | Deny | **Allow** `staff.approve` (authenticated Admin + audit) | Staff cannot approve themselves |
| `Manage staff`, `assign role` | Deny | Deny (cannot assign) | Deny (cannot assign) | **Allow** `staff.manage` / `users.manage_authorized` (explicit, audited) | Client `role` tampering rejected |
| `Impersonate` (`Login as Customer`) | Deny | Deny | Deny (deferred unless secure design) | Deny (deferred; if ever required: explicit permission, audit, restricted ops, secure exit) | No unrestricted impersonation |
| `Restrict customer browsing/ordering` | Deny | Deny | **Deny** (STAFF `→ block_customer` generic prohibited) | **Deny** unless explicit approved security policy with `reason/authorization/audit/impact/recovery` | Staff cannot restrict; Admin only under explicit policy |

### 14.8 Cross-Cutting Conventions Applied

- **Deny by default** for all except explicit `PUBLIC`; **field-level before serialization** — API exposes only permitted representation per actor (even Admin minimized). **Query-aware** (`?user_id=`) cannot expand; **pagination/search** over authorized dataset; **caching** private vs public separated (`Cache-Control` private later); **service-to-service** uses explicit service authorization (not reused Admin credential) for webhooks/jobs (Group H for payment). **Idempotency** does not bypass authz — every retry remains authorized. **Time-sensitive** (`20-min window`, `valid state`) evaluated at operation time.**

*Do not define endpoint URLs, Laravel Policies, or middleware here.*

## 15. Resource → Endpoint Mapping & Operations (Phase 1.19)

> Maps each domain resource to its Version 1 endpoint IDs, operations, and access scope. Endpoint inventory is authoritative in `api-contract.md §19`; this section is resource-centric view. All endpoints use `/api/v1`, CLOSED enums, and global conventions.

| Resource | Public | Customer | Staff | Admin | Endpoint(s) | Operations |
|---|---|---|---|---|---|---|
| **Category** | **Read** | Read | Read | **Manage** | `CAT-003`, `CAT-004`, `CAT-011`, `CAT-012` | `GET /categories` (CAT-003), `GET /categories/{category}` (CAT-004) public; `POST/PATCH` (CAT-011/012) Staff, Admin `products.manage` where approved (Staff only if approved) |
| **Product** | **Read** | Read | Read | **Manage** | `CAT-001`, `CAT-002`, `CAT-005`, `CAT-006`, `CAT-007..010` | `GET /products` (CAT-001) with `?category/product_type/availability/min_price/sort/page` + `GET /products/{product}` (CAT-002) public; `GET variants` (CAT-005/006) public; `POST/PATCH` + images/variants (CAT-007..010) Staff, Admin `products.manage` where approved (Staff only if approved) |
| **Inventory** | No | No | **Manage** | **Manage** | `INV-001`, `INV-002`, `INV-003` | `GET` collection/item `inventory.view` (INV-001/002) vs `POST /inventory/{product}/adjust` explicit action `inventory.manage` (INV-003) — not `PATCH {quantity}` |
| **Cart** | Guest holder via `X-Guest-Cart-Id` (backend-issued, not public enumeration) | **Own** (merged guest) | No | No | `CART-001..005` | `GET` own/guest cart (CART-001), `POST` add item (CART-002), `PATCH` quantity (CART-003), `DELETE` item (CART-004) — each supports `GUEST` via `X-Guest-Cart-Id`/`guest_cart_id` cookie (backend authority); `POST /me/cart/merge` (CART-005) merges guest onto authenticated; `AUTH-002` login also merges automatically; `AUTHENTICATED_OWNER` own + guest handoff |
| **Checkout** | No | **Own (transaction)** | No (operational follows on Order) | No (admin fee via Order) | `CHK-001` | `POST /checkout` (CHK-001) — `CUSTOMER` only, `AUTHENTICATED_OWNER` own active Cart, `PRIVATE`/`no-store`, `IDEMPOTENCY_REQUIRED` (`Idempotency-Key`), `CRITICAL` concurrency. Body `fulfillment_type` + conditional `delivery_address`; **never** `delivery_fee`/`total`/`price`/`status`/`cart_id`. Creates Order `PENDING_PAYMENT` (Model B fee-after-order: subtotal authoritative, delivery_fee pending Staff/Admin assignment before payment). Guest checkout forbidden. |
| **Order** | No | **Own** | **Operational** | **Admin** | `CHK-001` creates → `ORD-001..013` | `POST /checkout` (CHK-001) creates Order → `GET /me/orders` (ORD-001), `GET /me/orders/{order}` (ORD-002), `GET /me/orders/{order}/tracking` (ORD-003), `POST /me/orders/{order}/cancel` (ORD-004) for Customer own; `GET /orders` (ORD-005), `GET /orders/{order}` (ORD-006), state actions `accept/process/ready-for-pickup/ship/deliver/complete` (ORD-007..011 + ORD-013) for Staff Operational (`ORD-013` `complete` covers `DELIVERED→COMPLETED`/`READY_FOR_PICKUP→COMPLETED`); `GET /orders/{order}/tracking` (ORD-012) operational. Delivery_fee finalized on Order (Staff/Admin) before `PAY-001` per Model B. |
| **Payment** | No | **Limited own** (`PAY-001` Customer-only initiate) | Operational (limited, `PAY-002` view only) | Admin (limited, `PAY-002` view only; `PAY-001` is **Customer-only**, Admin does not initiate — see `api-contract.md §19.1`) | `PAY-001` Customer-only `POST /payments` initiate, `PAY-002` `GET /payments/{payment}` status (Customer/Staff/Admin limited), `WEBHOOK-001` system `POST /webhooks/payment/{provider}` — **Owner: Group H** (generic placeholders only) |
| **Request** (made-to-order) | **Create** (anonymous allowed) | **Own** | **Manage** | **Manage** | `REQ-001..007` | `POST /requests` (REQ-001) public submit (preferred multipart with attachments); `GET /me/requests` (REQ-002), `GET /me/requests/{request}` (REQ-003) own; `GET /requests` (REQ-004), `GET /requests/{request}` (REQ-005), `PATCH /requests/{request}` (REQ-006) Staff `requests.view/manage`; `POST /requests/{request}/attachments` (REQ-007) **Scoped** token + parent (anonymous requires server-issued upload token, predictable ID insufficient) |
| **Enquiry** | **Create** (anonymous allowed) | **Own** | **Manage** | **Manage** | `ENQ-001..007` | `POST /enquiries` (ENQ-001) public (preferred multipart with attachments); `GET /me/enquiries` (ENQ-002), `GET /me/enquiries/{enquiry}` (ENQ-003) own; `GET /enquiries` (ENQ-004), `GET /enquiries/{enquiry}` (ENQ-005), `PATCH /enquiries/{enquiry}` (ENQ-006) Staff operational; `POST /enquiries/{enquiry}/attachments` (ENQ-007) **Scoped** token + parent (same token model as `REQ-007`, predictable ID insufficient) |
| **Notification** | No | **Own** (customer notifications, holder-scoped via `/me`) | **Operational own**, recipient-scoped (operational: new order/request/payment event) | **Admin limited own**, recipient-scoped (administrative as needed) | `NOT-001`, `NOT-002` | `GET /me/notifications` (NOT-001) own per actor (Customer own / Staff `OPERATIONAL` own / Admin `ADMIN` limited own), paginated, holder-scoped; `PATCH /me/notifications/{notification}` (NOT-002) read/unread only (content immutable), same actor distinction |
| **User** (Profile) | No (except `POST /auth/register`) | **Own** | **Restricted** (own profile only) | **Admin** | `AUTH-001..008`, `USER-001..002`, `ADM-008/009` | `POST /auth/register` (AUTH-001) public; `POST /auth/login` (AUTH-002) public; `POST /auth/logout` (AUTH-003) self; `POST /auth/password/*` (AUTH-004/005) public; `GET /me` (USER-001) own, `PATCH /me` (USER-002) own (`name`/`phone` only, canonical `PATCH /api/v1/me`; legacy `/me/profile` retired), `POST /auth/change-password` (AUTH-008, canonical; legacy `POST /me/password` `USER-003` **RETIRED**) own; `GET /users` (ADM-008), `GET /users/{user}` (ADM-009) Admin `users.manage_authorized` only |
| **Staff / Roles** | No | No | No | **Manage** | `ADM-001..007` | `GET /admin/staff` (ADM-001), `GET /admin/staff/{user}` (ADM-002), `POST /admin/staff` (ADM-003 invite/create, not `POST {password}` generic), `POST /admin/staff/{user}/approve` (ADM-004), `POST /admin/staff/{user}/suspend` (ADM-005), `POST /admin/staff/{user}/reactivate` (ADM-006) — all Admin `staff.manage`/`staff.approve` (audited, no self-approval, `Idempotency-Key` Required for approve/suspend/reactivate, Critical concurrency), plus `GET /admin/audit-logs` (ADM-007) read-only where approved |
| **Audit Log** | No | No | No | **Read (optional)** | `ADM-007` | `GET /admin/audit-logs` Admin `audit.view` where approved — read-only, filters `actor/action/resource_type/resource_id/created_from/to`, `PRIVATE`/`no-store`, `meta.pagination`; `PATCH/DELETE /audit-logs` prohibited; creation audit mandatory even if read is internal-only |
| **Operational Product** (distinct from Public) | No | No | **Read/Manage** (operational) | **Manage** | `CAT-007..012` + `INV-002` | Operational product view: includes `is_active/is_published`, `internal inventory quantity/reserved/available` where authorized, `operational metadata` — never via public `GET /products` (public remains `PUBLIC` cacheable; operational is `PRIVATE`) |
| **Operational Order** | No | No | **Operational** | **Operational/Admin** | `ORD-005..014` | Staff/Admin operational order queue/detail + controlled actions (`accept/process/ready-for-pickup/ship/deliver/complete/delivery-fee`) — financial `delivery_fee` via `ORD-014` `POST .../delivery-fee` (`Idempotency-Key` Required, Critical); fulfillment branch validated |

| **Staff / Roles** | No | No | No | **Manage** | `ADM-001..006` | `GET /admin/staff` (ADM-001), `GET /admin/staff/{user}` (ADM-002), `POST /admin/staff` (ADM-003), `POST /admin/staff/{user}/approve` (ADM-004), `POST /admin/staff/{user}/suspend` (ADM-005), `POST /admin/staff/{user}/reactivate` (ADM-006) — managed duplicates above retained for traceability |

Additional notes:

- **Coverage:** `PAY-001/002`/`WEBHOOK-001` are `PROPOSED*` placeholders owned by Group H; all other V1 endpoints are **current `PROPOSED`**, target `APPROVED` after Phase 1.21 (see `api-contract.md §19.15`). No `wishlist`/`reviews`/`coupons`/`saved addresses`/`loyalty`/`driver tracking` (MVP discipline, §88). No `/my-orders` duplicate of `/me/orders`, no `/categories/{category}/products` duplicate (canonical `GET /products?category=`), no `/products/{product}/images` or `/availability` separate endpoints (embedded in `GET /products/{product}`).
- **Security review:** All endpoints respect `PUBLIC` vs `CUSTOMER` vs `OPERATIONAL` vs `ADMIN`; anonymous cannot reach private data; `Customer A → B` IDOR blocked via `owns` + 404 masking; `Customer→Staff/Admin`, `Staff→Admin`, `role tampering`, `user_id` swapping all denied per `api-contract.md §18`; `products.manage` ≠ `inventory.manage` distinct; `staff cannot block customer` etc. verified.
- **Business workflows:** `Anonymous browse CAT-001..004` → `Customer register/login AUTH-001/002 → cart CART-001..004 → checkout CHK-001 → payment PAY-001 → order ORD-001..004 → tracking ORD-003 → cancellation ORD-004 (20-min)` → `Staff ORD-005..013` (inc. `ORD-013` `DELIVERED/READY_FOR_PICKUP→COMPLETED`) + tracking `ORD-012`; `Request REQ-001 → REQ-007 → own REQ-002/003 → staff REQ-004..006`; `Admin ADM-001..004` approvals — all workflows endpoint-complete per `api-contract.md §19.11`.

> **Phase 1.30 Cross-Domain Review (2026-09-03):** All resources (`Category`, `Product`, `Inventory`, `Cart`, `Checkout`, `Order`, `Request`, `Enquiry`, `Notification`, `User`, `Staff`, `Audit`) verified per `api-contract.md §31.5`/`§31.9` ownership, `§31.9` request/response types, `§31.10` server-controlled fields, `§31.29B` permission matrix; no duplicate `ADM-005/006`/`NOT-004` IDs, state machine unified `READY_FOR_PICKUP→COMPLETED` vs `SHIPPED→DELIVERED→COMPLETED`, delivery-fee shape `{"amount","currency"}` (`TZS` minor units) consistent.
