# API Resources — Canonical Representations (v1)

> **Resources:** Policy-level field tables, relationship exposure and validation per `phase-1.13.md §72` + `phase-1.14.md` + `phase-1.15.md`. Not per-endpoint JSON schemas. Field enum values are `CLOSED`; `availability` filter vs response vocabularies are separated; money is integer minor-unit `{amount,currency}` everywhere. Validation is layered (Transport→Schema→Auth→Authz→Domain→Concurrency) — backend authoritative, frontend advisory.

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

## 10. Request Input per Resource — Consolidated (Phases 1.14 & 1.15 Validation)

> No final endpoint schemas are defined here; each sub-section reserves `Create | Update (PATCH) | Action | Read-Only / Server-Generated | Role-Specific` shape per `phase-1.14.md §69` plus explicit validation layers per `phase-1.15.md`: **input validation (schema), mutable vs immutable vs conditional fields, business validation, concurrency**. See `api-contract.md §14` and `api-conventions.md §16` for normative rules. This section is resource-specific instantiation of those rules — not a replacement.

### 10.1 Product (Staff/Admin) — Create / Update

- **Create input (staff/admin):** `name`, `slug`, `description`, `category_id`, `product_type` (`IN_STOCK`/`MADE_TO_ORDER` CLOSED), `price:{amount,currency}` **required for every published product including `MADE_TO_ORDER`** (minor units; for `MADE_TO_ORDER` it is a display/starting-at price, never used as checkout total). `is_active`/`is_published` flags. **Not accepted:** `id`, `created_at`, `updated_at`, `reserved_quantity`, `supplier internals`.
- **Update (PATCH):** partial, only mutable fields (`name`, `description`, `price`, `is_active`, `category_id`) — not `id`/`created_at`/`available_quantity`.
- **Read-only / server-generated:** `id`, `slug` uniqueness enforced server-side, `created_at`/`updated_at`, `availability`/`stock_indicator` derived from inventory, not client-set.
- **Role:** Public cannot create/update products.
- **Price contract (single contract, Phase 1.15 fix):** `price` is **non-null required** for every exposed product (`IN_STOCK` and `MADE_TO_ORDER`) — same shape `{amount:int minor units, currency:"TZS"}` per `api-contract.md §3.8`. For `MADE_TO_ORDER` it is informational/SEO display price only; domain validation prohibits `MADE_TO_ORDER` from normal checkout/cart regardless of price, and no money is calculated from it. Alternative of `null`/omission was rejected to keep one consistent representation (`data.price` always object, never `null`). Changing `price` to nullable or omittable would be a breaking change per `api-contract.md §9`.
- **Validation (schema → domain):** Schema: `name` non-empty after trim, length max; `slug` snake/kebab lowercase, unique; `product_type` CLOSED enum; `price:{amount int≥0, currency:"TZS"}` **required, strict types**; `category_id` exists. Domain: `category active`, `price authority` staff-only; `MADE_TO_ORDER` price not treated as purchasable unit price; inventory fields not client-writable. Unknown fields rejected; empty `""` not treated as omitted unless contract says so. Concurrency: creation not high-contention; no inventory reservation here.

### 10.1a Product — Validation Details (Phase 1.15 Matrix)

| Aspect | Rule |
|---|---|
| Input validation | `name` string trimmed, collapses internal whitespace; `quantity` N/A for product create; enum `product_type` exact `IN_STOCK` not `in_stock`; `price` always object `{amount int≥0, currency:"TZS"}` required |
| Mutable | `name`, `description`, `price`, `is_active`, `category_id` (PATCH allow-list) |
| Immutable / server-generated | `id`, `created_at`/`updated_at`, `availability`/`stock_indicator`, `reserved_quantity`/`available_quantity` |
| Conditional | No conditional omission of `price` — price required for **both** `IN_STOCK` and `MADE_TO_ORDER` (single contract); conditional logic is instead `MADE_TO_ORDER → checkout/cart prohibited regardless of price` |
| Business validation | `category exists && active`; `slug unique`; `price non-negative minor units` (informational for `MADE_TO_ORDER`, purchasable unit price for `IN_STOCK`); stock derived server-side |
| Request type | `CREATE` / `PATCH` (no generic `PUT` unless full replacement documented) |

### 10.2 Category (Staff/Admin)

- **Create/Update:** `name`, `slug`, `description`, `image`. `id`, `created_at` server-generated. No `product` embedding in category create.
- **Validation:** Schema: `name`/`slug` length/pattern; `slug` unique global; enum N/A. Mutable: `name`, `slug`, `description`, `image`. Immutable: `id`, `created_at`. Unknown fields rejected. Business: `slug unique` enforced transactionally; no inventory logic.

### 10.3 Cart — Add / Update

- **Add item (POST `/me/cart/items`):** `product_id` + optional `variant_id` + `quantity:int≥1`. **Not accepted:** `price`, `subtotal`, `stock`, `currency`.
- **Update item (PATCH):** `quantity` only (partial). Empty `items:[]` invalid where ≥1 required. Duplicate `product_id` handling per contract (invalid/merged).
- **Server-controlled:** `id`, `created_at`/`updated_at`, totals, availability.
- **Validation:** Schema: `product_id` string required; `variant_id` nullable; `quantity` integer ≥1 strict (not `"2"`). Cross-field/relationship: `variant belongs to product` when supplied, `variant active`. Domain: `product exists && active && product_type=IN_STOCK` (concept `MADE_TO_ORDER` prohibited), `variant purchasable`, `quantity valid`; inventory **not** reserved at cart stage but checked at checkout; concurrency Medium (add/update may affect same cart). Unknown fields rejected; client-supplied price/stock ignored. Query `page/per_page` validated per §16.3.

### 10.4 Checkout (Authenticated Customer)

- **Input:** `fulfillment_type: PICKUP|DELIVERY` (CLOSED) + **conditional** `delivery_address:{name,phone,address_line,city,...}` required when `DELIVERY`, absent/`null` for `PICKUP`. Optional `notes`. **Not accepted:** `cart contents` beyond referencing current `me/cart`, `current prices`, `stock`, `delivery_fee`, `subtotal`, `total`, `order_reference`, `payment_status`, `user_id` (ownership from auth, not body).
- **Validation (full layered pipeline):** `Transport→Schema→Auth→Authz(cart owns)→Cart valid→Products purchasable→Inventory concurrency-safe→Fulfillment cross-field→Price server-calculated→Order/payment workflow`. Cross-field: `DELIVERY→delivery_address required`; `PICKUP→delivery fee zero/not customer-supplied`. State: cart not empty, items still purchasable. Pricing: `subtotal/delivery_fee/total` SERVER-GENERATE, never client `{total:1000}`. Idempotency-sensitive: duplicate `POST` must not duplicate orders (Idempotency-Key deferred). Concurrency High; transaction required; failure atomicity (no reservation left on failure).

### 10.5 Order — Actions (Customer vs Staff/Admin)

- **Customer actions:** `cancel` (within 20-min window) — minimal body e.g., `{"reason":...}` optional; no `{status:"CANCELLED"}`.
- **Staff/Admin actions:** `accept`/`ship`/`deliver` — minimal `{"note":...}` where needed; status transitions are actions (`POST /orders/{order}/ship`), not `PATCH {"status":"SHIPPED"}`. **Read-only:** `order_reference`, `status`, `customer_id`, `created_at`, `payment_status`, `final totals`.
- **Validation:** Schema: `reason`/`note` optional string trimmed. State-dependent: cancellation validates `authenticated owner → owns order → exists → cancellation window (backend time, 20-min) → state eligible`; generic `status!=COMPLETED` insufficient. Transitions: each validates `current state, requested transition, actor auth, business preconditions` (e.g., `PROCESSING→SHIPPED` staff + delivery preconditions). No `PATCH {status:...}` bypass (API-VAL-004). Failure atomicity; concurrency Medium/High; audit: `order_status_history` captured. Query: `?order_status`, `?fulfillment_type` etc. CLOSED enum validation; `?status` generic rejected.

### 10.6 Furniture Request & Enquiry (Anonymous or Authenticated)

- **Request create:** `product_id` nullable + `quantity`, `dimensions`, `material`, `color`, `notes`, `contact:{name,phone,email}` (contact required; `user` optional, not fabricated), optional `attachment` via `multipart/form-data`. **Not accepted:** `order_id`, `request_status`, `inventory_reservation`.
- **Enquiry create:** `name`, `phone`/`email` (at least one), `subject`, `message`, optional `attachment`. **Not accepted:** `status`, `staff assignment`, `closed_at`, `internal_note`.
- **Validation:** Request: `contact info` required, `product_id` nullable (when supplied: exists/active/belongs), `quantity` when supplied integer≥1, `dimensions/material/color/notes` strings trimmed, `attachments` via `multipart` with size/type/extension/signature checks (client MIME not trusted). Anonymous `user=null` valid (REQ-001). Enquiry: `contact + message` required; `subject` where required; User association optional. Both: `request_status`/`enquiry_status` SERVER-GENERATE, never client-settable. Auth Optional; authorization contextual (own records). Concurrency Low.

### 10.7 Profile & Cart vs Order

- **Profile (PATCH `/me/profile`):** `name`, `phone` mutable; `email`/`password`/`role`/`account_status` require dedicated workflows, not ordinary `PATCH`. `id`, `created_at`, verification state server-generated.
- **Identifiers:** Authenticated requests never require `{"user_id":"current-user"}`; server derives ownership. Anonymous requests must contain contact, not fake `user_id`.
- **Validation:** Schema: `name`/`phone` string trimmed, `phone` normalized, `email` format not mutable via this endpoint. Mutable: `name`, `phone` only (allow-list). Immutable: `email`/`password`/`role`/`account_status`/`id`/`created_at`. Auth required, Authz own profile only.

### 10.8 Payment (Generic)

- **Generic principles only:** Customer does **not** send `{payment_status:"PAID"}` or `{amount:{...}}` as authoritative confirmation; amounts and confirmation are backend/provider-controlled. Provider-specific payloads **deferred to Group H**; no `payment_provider` request schema defined here.
- **Validation note:** Generic transport/schema/auth/authz apply; external-provider verification (webhook signature, idempotency, verification) is Group H, not business-input validation. External failures ≠ input `INVALID_VALUE`.

### 10.9 Common Input & Validation Rules Applied

- `snake_case` (`product_id`, `delivery_address`), strict JSON types (`quantity:2` not `"2"`, booleans not `1`), `null` only where explicitly nullable, unknown fields **rejected** (validation error, not silently ignored) to catch typos/version drift, mass-assignment protection via allow-list (`validated input → DTO → domain`), idempotency-sensitive (`checkout`, `payment initiation`) noted for later key, size limits enforced.
- **Validation extensions (Phase 1.15):** Layered hierarchy `Transport→Schema→Auth→Authz→Domain→Concurrency→External→Persistence`; CLOSED enums (unknown → error); cross-field `fulfillment_type↔delivery_address`; conditional `DELIVERY` required; state-dependent `PROCESSING→SHIPPED` vs `COMPLETED→SHIPPED invalid`; relationship `variant belongs to product && active && purchasable`; server-controlled `id/created_at/order_reference/status/payment_status/inventory/totals/history` REJECT/IGNORE; pricing `SERVER-GENERATE`; inventory concurrency-safe; cancellation 20-min backend time; transitions via actions not generic writes; error categories `INVALID_TYPE/INVALID_VALUE/PRODUCT_NOT_PURCHASABLE/INSUFFICIENT_STOCK/INVALID_ORDER_TRANSITION/NOT_AUTHENTICATED/FORBIDDEN` with field-level paths and multiple errors per request (schema all, domain early-stop when unsafe); messages non-leaking, codes stable within `v1` (rename `INSUFFICIENT_STOCK` breaking); logging without secrets; failure atomicity; audit for critical transitions.

### 10.10 Query & Collection Validation (Phase 1.15)

- `page` positive int, `per_page 1–100` (validation error otherwise; missing→default 20). `product_type` CLOSED (`IN_STOCK`/`MADE_TO_ORDER`), `fulfillment_type` CLOSED, `order_status`/`request_status`/`enquiry_status`/`payment_status` CLOSED (not `payment_state`), `sort` allow-list (`created_at`, `price`, `name` plus tie-breaker `id ASC`), `min_price`/`max_price` minor-unit integers with `min≤max`. Filter fields allow-list only. All query enums CLOSED; `?availability=IN_STOCK` is error (use `available|unavailable`); `?status` generic rejected. Strict type for `quantity`, `page`, etc.; not `"2"` strings.

### 10.11 Validation Matrix Reference

See `api-contract.md §14.17` for operation-level matrix (`Browse/Add cart/Checkout/Cancel/Request/Enquiry/Profile/Ship`). Each operation respects same layered validation. Frontend validation advisory; backend authoritative. Payment provider-specific deferred to Group H; no FormRequest/DTO/migration/OpenAPI schema implemented here.
