# API Resources — Canonical Representations (v1)

> **Resources:** Policy-level field tables and relationship exposure per `phase-1.13.md §72`. Not per-endpoint JSON schemas. Field enum values are `CLOSED`; `availability` filter vs response vocabularies are separated; money is integer minor-unit `{amount,currency}` everywhere.

## 1. Product (PUBLIC)

**Conceptual paths:** `GET /api/v1/products`, `GET /api/v1/products/{product}`, `GET /api/v1/categories/{category}/products`

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | PUBLIC | no | Opaque API identifier |
| `name` | string | PUBLIC | no | |
| `slug` | string | PUBLIC | no | SEO slug separate from `id` |
| `description` | string | PUBLIC | no | SEO/public |
| `product_type` | enum `IN_STOCK`,`MADE_TO_ORDER` | PUBLIC | no | CLOSED |
| `price` | `{amount:int,currency:"TZS"}` | PUBLIC | no | Minor units; `125000000`=1,250,000.00 TZS; variant override per `VAR-005` |
| `category` | `{id,slug,name}` summary | PUBLIC | no | Full via `categories/{category}` |
| `images` | `[{id,url,alt_text,sort_order,is_primary}]` | PUBLIC | no | Embedded; not bare URL array |
| `variants` | `[{id,sku,name,price:{amount,currency},availability}]` summary | PUBLIC | no | Full via `/products/{product}/variants/{variant}` |
| `availability` | `"available"\|"unavailable"` | PUBLIC | no | Coarse public signal (filter `?availability=available`). Not `IN_STOCK`. |
| `stock_indicator` | `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | PUBLIC | no | **Display bucket only**, not filterable; `?availability=IN_STOCK` is validation error. |
| `created_at` | ISO8601 UTC | PUBLIC | no | `2026-08-30T15:30:00Z` |
| `updated_at` | ISO8601 UTC | PUBLIC | no | |

**Not exposed publicly:** `reserved_quantity`, `physical_quantity`, supplier internals, staff notes, internal adjustments.

**Inventory detail (STAFF/ADMIN via authorized inventory view):** `{quantity, reserved_quantity, available_quantity}` as **integer item counts (units/pieces, not monetary minor units)** — e.g., `quantity: 12` means 12 items. Not TZS minor units; never multiply stock by 100. Never on public product.

## 2. Category

| Field | Type | Exposure | Notes |
|---|---|---|---|
| `id` | string | PUBLIC | |
| `name` | string | PUBLIC | |
| `slug` | string | PUBLIC | Unique global |
| `description` | string | PUBLIC | nullable |
| `image` | `{url}` | PUBLIC | nullable |
| `created_at` | ISO8601 | PUBLIC | |

Products not recursively embedded; use `GET /categories/{category}/products` with pagination.

## 3. Order (CUSTOMER — `GET /api/v1/me/orders{,/{order}}`; ADMIN `GET /api/v1/orders`)

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string | CUSTOMER (own) | no | Internal API id |
| `order_reference` | string `OD-...` | CUSTOMER | no | Customer-facing reference, distinct from `id` |
| `status` | enum CLOSED | CUSTOMER | no | `PENDING_PAYMENT`→`CANCELLED` etc. — machine value, not display label; `order_status` query key maps here |
| `fulfillment_type` | `PICKUP`/`DELIVERY` | CUSTOMER | no | |
| `items` | `[{sku, name, variant_id, quantity, unit_price:{amount,currency}, line_total:{amount,currency}}]` | CUSTOMER | no | Snapshot at purchase, not live product price |
| `subtotal` | `{amount,currency}` | CUSTOMER | no | Line-sum, minor units |
| `delivery_fee` | `{amount,currency}` | CUSTOMER | no | Minor units; `{amount: 0, currency: "TZS"}` for `PICKUP` (same shape, not bare `0`) |
| `total` | `{amount,currency}` | CUSTOMER | no | `subtotal + delivery_fee` |
| `currency` | `"TZS"` | CUSTOMER | no | Single currency |
| `billing_address` | object | CUSTOMER | no | Snapshot |
| `delivery_address` | object | CUSTOMER | yes | `null` for `PICKUP`; present snapshot for `DELIVERY` |
| `delivery` | `{status, tracking_summary}` | CUSTOMER | yes | `null` for `PICKUP`; embeds delivery summary, not GPS |
| `payment` | `{payment_status, amount:{amount,currency}}` limited | CUSTOMER | no | No secrets; full via admin |
| `created_at` / `updated_at` | ISO8601 | CUSTOMER | no | `created_at` starts 20-min cancellation window |

**STAFF/ADMIN extra (not on customer):** `customer:{id,name,email}`, operational `quantity` details, `payment.provider`, internal notes where authorized. `CANCELLED`/`SHIPPED` transitions remain controlled actions (`POST /orders/{order}/cancel` etc.), not `PATCH status`.

## 4. Cart (`GET /api/v1/me/cart`, `GET /api/v1/me/cart/items`)

- `id`, `items: [{product_id, variant_id, quantity}]`, `updated_at`. Cart totals are **not** authoritative — backend computes `subtotal`/`total` at checkout (client totals ignored). Holder-scoped (`GUEST_TOKEN` or User).

## 5. Furniture Request (`/requests`, `/me/requests`)

| Field | Exposure | Notes |
|---|---|---|
| `id` | CUSTOMER (own) / STAFF | |
| `product_id` | CUSTOMER | nullable (custom request) |
| `customer:{name,email,phone}` | own / STAFF | contact captured |
| `request_status` | CUSTOMER/STAFF | CLOSED enum per request contract (values like `QUOTED` future) — query `?request_status=` |
| `details, quantity, preferred_material` etc. | own / STAFF | |
| `attachments` | via `/requests/{request}/attachments` | Private URLs, not fully embedded |

## 6. Enquiry (`/enquiries`, `/me/enquiries`)

Similar to Request: `enquiry_status` query key, `subject`, `message`, `attachments` via subresource, not merged with requests.

## 7. Payment (generic — provider-specific deferred to Group H)

- Response shape: `{id, payment_status: CLOSED, amount:{amount,currency}, currency:"TZS", created_at}`. No provider secrets, no `payment_status=success` client-writable field. Provider reference / webhook details are `INTERNAL` until Group H.

## 8. Relationships Summary

- **Embedded:** `product.images`, `order.items` (snapshots), `order.billing_address`.
- **Summary/Reference:** `product.category`, `variant.product`.
- **Subresource:** `products/{product}/variants`, `products/{product}/images/{image}`, `orders/{order}/tracking`, `orders/{order}/status-history`, `requests/{request}/attachments`.
- **Deferred dynamic:** `?include` / `?fields` — not supported; use dedicated subresources.

## 9. Sensitive / Never Serialized

`password_hash`, `tokens`, `provider secrets`, internal `reserved_quantity` (public), staff private notes, internal filesystem paths, authorization flags, internal IDs (beyond `id` where not contracted).

---

## 10. Request Input per Resource — Space Reserved (Phase 1.14)

> No final endpoint schemas are defined here; each table reserves `Create | Update (PATCH) | Action | Read-Only / Server-Generated | Role-Specific` shape per `phase-1.14.md §69`.

### 10.1 Product (Staff/Admin) — Create / Update

- **Create input (staff/admin):** `name`, `slug`, `description`, `category_id`, `product_type` (`IN_STOCK`/`MADE_TO_ORDER` CLOSED), `price:{amount,currency}` where purchasable (minor units), `is_active`/`is_published` flags. **Not accepted:** `id`, `created_at`, `updated_at`, `reserved_quantity`, `supplier internals`.
- **Update (PATCH):** partial, only mutable fields (`name`, `description`, `price`, `is_active`, `category_id`) — not `id`/`created_at`/`available_quantity`.
- **Read-only / server-generated:** `id`, `slug` uniqueness enforced server-side, `created_at`/`updated_at`, `availability`/`stock_indicator` derived from inventory, not client-set.
- **Role:** Public cannot create/update products.

### 10.2 Category (Staff/Admin)

- **Create/Update:** `name`, `slug`, `description`, `image`. `id`, `created_at` server-generated. No `product` embedding in category create.

### 10.3 Cart — Add / Update

- **Add item (POST `/me/cart/items`):** `product_id` + optional `variant_id` + `quantity:int≥1`. **Not accepted:** `price`, `subtotal`, `stock`, `currency`.
- **Update item (PATCH):** `quantity` only (partial). Empty `items:[]` invalid where ≥1 required. Duplicate `product_id` handling per contract (invalid/merged).
- **Server-controlled:** `id`, `created_at`/`updated_at`, totals, availability.

### 10.4 Checkout (Authenticated Customer)

- **Input:** `fulfillment_type: PICKUP|DELIVERY` (CLOSED) + **conditional** `delivery_address:{name,phone,address_line,city,...}` required when `DELIVERY`, absent/`null` for `PICKUP`. Optional `notes`. **Not accepted:** `cart contents` beyond referencing current `me/cart`, `current prices`, `stock`, `delivery_fee`, `subtotal`, `total`, `order_reference`, `payment_status`, `user_id` (ownership from auth, not body).

### 10.5 Order — Actions (Customer vs Staff/Admin)

- **Customer actions:** `cancel` (within 20-min window) — minimal body e.g., `{"reason":...}` optional; no `{status:"CANCELLED"}`.
- **Staff/Admin actions:** `accept`/`ship`/`deliver` — minimal `{"note":...}` where needed; status transitions are actions (`POST /orders/{order}/ship`), not `PATCH {"status":"SHIPPED"}`. **Read-only:** `order_reference`, `status`, `customer_id`, `created_at`, `payment_status`, `final totals`.

### 10.6 Furniture Request & Enquiry (Anonymous or Authenticated)

- **Request create:** `product_id` nullable + `quantity`, `dimensions`, `material`, `color`, `notes`, `contact:{name,phone,email}` (contact required; `user` optional, not fabricated), optional `attachment` via `multipart/form-data`. **Not accepted:** `order_id`, `request_status`, `inventory_reservation`.
- **Enquiry create:** `name`, `phone`/`email` (at least one), `subject`, `message`, optional `attachment`. **Not accepted:** `status`, `staff assignment`, `closed_at`, `internal_note`.

### 10.7 Profile & Cart vs Order

- **Profile (PATCH `/me/profile`):** `name`, `phone` mutable; `email`/`password`/`role`/`account_status` require dedicated workflows, not ordinary `PATCH`. `id`, `created_at`, verification state server-generated.
- **Identifiers:** Authenticated requests never require `{"user_id":"current-user"}`; server derives ownership. Anonymous requests must contain contact, not fake `user_id`.

### 10.8 Payment (Generic)

- **Generic principles only:** Customer does **not** send `{payment_status:"PAID"}` or `{amount:{...}}` as authoritative confirmation; amounts and confirmation are backend/provider-controlled. Provider-specific payloads **deferred to Group H**; no `payment_provider` request schema defined here.

### 10.9 Common Input Rules Applied

- `snake_case` (`product_id`, `delivery_address`), strict JSON types (`quantity:2` not `"2"`, booleans not `1`), `null` only where explicitly nullable, unknown fields **rejected** (validation error, not silently ignored) to catch typos/version drift, mass-assignment protection via allow-list (`validated input → DTO → domain`), idempotency-sensitive (`checkout`, `payment initiation`) noted for later key, size limits enforced.
