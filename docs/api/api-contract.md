# API Contract — Furniture E-Commerce Platform (Consolidated)

> **Version:** `v1` — base `/api/v1` · **Status:** Phase 1.13 Baseline
> **Authority:** This file is the canonical response-envelope and resource-representation contract for `v1`. Phase instruction files are temporary working docs; this file plus `api-conventions.md` / `api-resources.md` / `openapi.yaml` are the consolidated project knowledge per `phase-1.13.md §2`.

---

## 1. Scope & Versioning

- **Base:** All contracted paths under `/api/v1` (e.g., `/api/v1/products`, `/api/v1/me/orders`, `/api/v1/products/{product}/availability`). No mix of versioned/unversioned. SEO routes (`/products/modern-3-seater-sofa`) are independent.
- **Versioning:** URL-path `v1`; breaking changes → `v2` per `api-versioning-strategy.md`. CLOSED enums (see §13) remain CLOSED in responses as in queries.
- **Payment:** Generic Payment principles are defined here; provider-specific structures remain **Phase Group H**.

---

## 2. Response Conventions — Summary

### 2.1 Success Envelope (Single Resource)

```json
{
  "data": {
    "id": "123",
    "name": "Modern Sofa",
    "product_type": "IN_STOCK"
  }
}
```
- `data` is the **only** primary success member for single-resource responses. Never `result`, `resource`, `payload`, `item`.

### 2.2 Collection Envelope (with Metadata)

```json
{
  "data": [
    { "id": "123", "name": "Modern Sofa" }
  ],
  "meta": {
    "pagination": {
      "current_page": 2,
      "per_page": 20,
      "total": 86,
      "last_page": 5,
      "has_next": true,
      "has_previous": true
    }
  }
}
```
- `data` = **array** (empty array for empty collections, never `null`).
- `meta` is reserved for collection-level metadata (pagination, and later optional `request_id`). **No business resources inside `meta`** (see §7).
- Pagination placement is **always `meta.pagination`** (not `meta` top-level nor `pagination` sibling to `data`). This leaves `meta` room for future collection summary fields. Follows `pagination-metadata-policy.md` and `phase-1.12.md`.
- Individual missing resource is **not** `{"data": null}` — it uses the error contract (`errors`).

### 2.3 Error Envelope (Reserved)

```json
{
  "errors": []
}
```
- Successful responses use `data`; errors use `errors` (`phase-1.13.md §37`). Never `{data:null, error:"..."}`. Full error codes/statuses belong to the later error-contract phase.

### 2.4 Empty Collection vs Not Found

- `GET /products?page=99` (beyond `last_page`) or filtered empty: `200` + `{"data":[], "meta":{"pagination":{...}}}`.
- Empty dataset (`total=0`): `current_page=1, last_page=1, has_next=false, has_previous=false, data:[]` (avoids page-zero logic).
- `GET /products/{product}` unknown id: error contract, not `data:null`.

---

## 3. Resource Representation Rules

### 3.1 Identifiers

- Every independently identifiable resource has `id` (API resource identifier, opaque, stable). Customer-facing order reference is separate: `order_reference: "OD-..."` + `id` (internal) if both needed. Slug (`modern-3-seater-sofa`) and SKU remain distinct from `id` per `endpoint-naming-conventions.md §4`. No DB internals leaked.

### 3.2 Type Field (Not Required)

- No `type: "product"` field is required on every resource. Endpoint context already establishes resource type. Not included by default; may be added only if OpenAPI/client tooling later demonstrates value with consistency across all resources.

### 3.3 Field Naming

- **Canonical:** `snake_case` lowercase throughout (`product_type`, `created_at`, `delivery_fee`, `order_status`, `request_status`, `enquiry_status`, `payment_status`). No mixing `productType` / `product-type` within contract. DB naming does not dictate API.

### 3.4 Timestamps

- **Single standard:** ISO 8601 / RFC 3339 UTC with `Z` (`2026-08-30T15:30:00Z`). Not `2026-08-30 15:30:00`, not `30/08/2026`, not Unix integer `1756567800`.
- Lifecycle fields where exposed: `created_at`, `updated_at` consistently. Only expose timestamps useful to the client (not every DB `timestamps()` column).

### 3.5 Booleans

- JSON booleans: `true` / `false` (lowercase, unquoted). Never `"true"`, `1`, `"yes"`. Matches query boolean convention (`?is_active=true`).

### 3.6 Numbers

- JSON numbers for numeric concepts (`quantity: 2`). Not `"2"` unless external textual identifier.

### 3.7 Nullability — `null` vs Absent

- **`null`:** Field is part of contract but currently has no value (e.g., `"delivery_address": null` for `PICKUP` order).
- **Absent (omitted):** Field is not part of selected representation (e.g., `delivery` subresource absent for `PICKUP`; `low_stock` bucket not in filter).
- Do not alternate `null` / `""` / `false` / omitted for same semantic condition. Nullability is documented per field and changing it is a contract change.

### 3.8 Money — Single Global Rule (Aligned with Query: `phase-1.11.md` / `query-parameter-conventions.md §9`)

- **Wire representation:** Integer `amount` in **TZS minor units (cents, 1 TZS = 100)** as JSON **number** + `currency: "TZS"`. This is the **only** representation; no formatted strings (`"TZS 1,250,000"`), no bare numbers without currency, no decimal strings, no mixed `price:number` / `total:string`.
- **Example:**
```json
{
  "price": { "amount": 125000000, "currency": "TZS" },
  "delivery_fee": { "amount": 2000000, "currency": "TZS" },
  "total": { "amount": 127000000, "currency": "TZS" }
}
```
  - `125000000` = 1,250,000.00 TZS; `50000000` = 500,000.00 TZS. Query filters use same minor-unit integer as string (`?min_price=50000000` = same value); response uses number.
- **Applies uniformly:** `product.price`, `variant.price`, `order_item.unit_price`, `order.subtotal`, `order.delivery_fee`, `order.total`, `payment.amount` — all `{amount, currency}`. Adding truly optional money field is non-breaking; changing `price:number` → `price:object` or `price:string` is breaking.
- **Zero-decimal handling:** TZS uses minor units; no floating-point arithmetic (`AGENTS.md §13`, `PRICE-004`). Frontend formats for display; API never sends pre-formatted display text as primary value.

### 3.9 Availability — Filter vs Response (Single Frozen Contract)

- **Filter vocabulary (query):** `?availability=available` / `?availability=unavailable` only (lowercase, per `query-filtering-policy.md §7` / `query-enum-policy.md`). `IN_STOCK` / `MADE_TO_ORDER` are **not** valid `availability` filter values — they belong to `product_type`; `LOW_STOCK` is not a filter at all; `?availability=IN_STOCK` is validation error.
- **Response fields (frozen):**
  - `availability: "available" | "unavailable"` (lowercase, CLOSED) — **explicit documented exception to the global `UPPER_SNAKE_CASE` enum rule** (`§3.10`). Lowercase is intentional to match the query filter and prevent `available` vs `AVAILABLE` drift; all other V1 enums remain `UPPER_SNAKE_CASE`.
  - `stock_indicator: "IN_STOCK" | "LOW_STOCK" | "MADE_TO_ORDER"` — **canonical display bucket field** (UPPER_SNAKE_CASE, CLOSED, response-only, not filterable). `availability_display` is **not** a valid field; do not use it. No client may use `stock_indicator` as query value.
- Inventory operational fields (`quantity`, `reserved_quantity`, `available_quantity`) are **staff/admin only** under `inventory` detail; never exposed in public product `availability` (`PUBLIC vs OPERATIONAL INVENTORY` — `phase-1.13.md §27`).

### 3.10 Enum Values

- All V1 enums are **CLOSED** in responses as in queries (`IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`, `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED`, etc.). Only documented values; adding `ON_HOLD` is compatibility event. Response uses canonical `UPPER_SNAKE_CASE` machine values (e.g., `"status": "READY_FOR_PICKUP"` → `order_status` context), not display labels. **Explicit exception:** `availability` is lowercase `"available" | "unavailable"` per `§3.9` to stay identical to its query filter; this is the single frozen exception to `UPPER_SNAKE_CASE` and does not extend to other enums (`product_type`, `order status`, etc. remain `UPPER_SNAKE_CASE`).

### 3.11 Individual vs Collection — Consistency

- `GET /products/{product}` → `{"data": {object}}`; `GET /products?page=1` → `{"data": [objects], "meta": {"pagination": {...}}}`. Same resource fields in both, same naming/serialization.

---

## 4. Pagination (Placed Under `meta.pagination`)

- Follows `pagination-convention.md` + `query-sorting-policy.md` tie-breaker:
  - Params `page` (1-based) + `per_page` (default `20`, `1 ≤ per_page ≤ 100`, validation error otherwise; missing → defaults).
  - Deterministic ordering required; tie-breaker `id ASC` (`ORDER BY <primary> <dir>, id ASC`) for stable pages even when primary ties (`price`, `name`, `order_status`, `created_at`).
  - Pipeline **Search → Filter → Sort (+ id ASC) → Paginate** (single canonical order).
  - Empty: `total=0 → current_page=1, last_page=1, has_next=false, has_previous=false, data=[]`; beyond `last_page` → empty `data[]` with metadata (not error).
- **Metadata naming** exactly `current_page, per_page, total, last_page, has_next, has_previous` under `meta.pagination`.

---

## 5. Relationships

- **Policy per `api-resource-relationships.md` / `phase-1.13.md §30-32`:**
  - *Small essential child data* — embedded (e.g., `product.images[]` with `{id, url, alt_text, sort_order, is_primary}` — not bare `["url1","url2"]`; embeds follow same conventions).
  - *Large/independent* — summary/reference ` {id, slug, name}` (e.g., `product.category: {id, slug, name}`) — not full tree; full fetch via `/api/v1/categories/{category}` for category details (variants remain via `/api/v1/products/{product}/variants` only for variants).
  - *Sensitive/restricted* — not auto-exposed; Order `payment` shows limited customer-safe summary (`payment_status`, `amount`, `currency`) not secrets.
- **Bounded, no recursive embedding:** `product → category → products → category` is forbidden; use summary/reference to break cycles.
- **V1 `include` / `fields` (sparse fieldsets) are DEFERRED** per `query-relationship-policy.md`; no `?include=category,variants` or `?fields=id,name` — history retained via dedicated subresources, not dynamic graph.

---

## 6. Links

- **V1 provides numeric pagination metadata only** (`meta.pagination`); navigation URLs (`first/prev/next/last`) are **deferred** per `pagination-metadata-policy.md` (absolute/relative would confuse `local`/`staging`/`production`). If later added, they will be under `links` or `meta.links` consistently, with query preservation and deterministic ordering.
- **Per-resource `self` links are omitted by default** in V1 — clients already know endpoint structure; discoverability vs payload tradeoff favors omission. If later adopted, every resource would include `links.self` consistently, not ad-hoc.
- **Private attachment URLs must not be permanently public**; signed/authorized URLs deferred to storage/security design.

---

## 7. Metadata (`meta`) — Reserved

- `meta` is for **collection-level metadata** (pagination) and later optional `request_id` correlation. **No business resources inside `meta`** (`meta.current_user`, `meta.product`) — that is an uncontrolled second channel.
- `meta.request_id` correlation is optional; if adopted, format/scope documented separately, no sensitive data inside.

---

## 8. Serialization Security

- **Field exposure levels:** `PUBLIC` (product name/slug/price/availability), `CUSTOMER` (own orders `order_reference`, `status`, `items`, `totals`), `STAFF/ADMIN` (operational inventory `quantity`/`reserved_quantity`, admin notes), `INTERNAL` (never serialized). Serializer selects per authorization context; one serializer not safe for all audiences — no `model.toArray()` mass-serialization.
- **Never serialize:** `password`, `password_hash`, `authentication tokens`, `refresh tokens` (unless explicitly contracted), `payment secrets`, `provider credentials`, `private encryption keys`, `internal filesystem paths`, `authorization flags`. Private attachments require authorized access.
- **Timestamps:** only useful to client, not every DB timestamp.

---

## 9. Compatibility

- **Additive only within `v1`:** Adding truly optional field is non-breaking; **renaming, changing type, changing meaning, removing, or changing nullability** (e.g., `delivery_address` always-present → `null`) is potentially breaking and follows `api-versioning-strategy.md` / `api-breaking-change-policy.md`. Money shape `price:number` → `price:object` is breaking; timestamp ISO → Unix is breaking; `true/false` → `1/0` is breaking.
- **Enums remain CLOSED:** `status: PROCESSING` → display `Being prepared` is frontend mapping; API sends machine `PROCESSING`.
- **Consistency across clients:** Same field means same thing to Next.js, Flutter, Admin — no `price=cents` in one API and `price=TZS` in another. Naming is `snake_case` everywhere regardless of React/Flutter prop conventions.

---

## 10. Examples (Structure Only — Not Endpoint Payloads)

**Product (public):** `{data: {id, name, slug, description, product_type: IN_STOCK, price:{amount,currency}, images:[{id,url,alt_text,sort_order,is_primary}], variants:[...], availability: available|unavailable, category:{id,slug,name}}}` — no `reserved_quantity`, `staff_notes`.

**Category:** `{data: {id, name, slug, description, image:{url}}}` — products via separate fetch or summary.

**Action response** (e.g., `POST /orders/{order}/ship`): returns updated resource `{"data": {id, order_reference: "OD-12345", status: "SHIPPED"}}` or operation result, not opaque `{"success":true}`.

**Checkout workflow:** returns resulting business resource identity (`order` + `payment` summary) clearly per `phase-1.13.md §43`.

---

## 11. What Must Not Appear in Normal Responses

`password`, `password_hash`, tokens, provider secrets, DB credentials, internal paths, private keys, full model dumps, admin notes on public serializers.

---

## 12. Links to Conventions & Resources

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility, input).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure and per-resource input).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).

---

## 13. Request Conventions — Input Contract (Phase 1.14)

> **Authority:** Single input language for `v1` (`/api/v1`). Backend is authoritative for money, inventory, status, payment and ownership (AGENTS.md §§12-13, 3). Frontend collection vs mutation via `Content-Type` and field allow-lists.

### 13.1 Content Type & Encoding

- **JSON default:** `Content-Type: application/json` for normal create/update/action requests. Example:
```json
{ "quantity": 2, "fulfillment_type": "DELIVERY" }
```
- Incorrect/unsupported content types are **validation errors** (not silently guessed). `multipart/form-data` is permitted only for attachment uploads (see §13.16); otherwise JSON. Do not send URL-encoded JSON strings inside JSON fields.
- **UTF-8** JSON; backend rejects non-UTF-8 with validation error.

### 13.2 Field Naming

- Same `snake_case` as responses and query: `product_id`, `fulfillment_type`, `delivery_fee`, `order_reference`, `created_at`. Never mix `productId` / `product-id` in same API. DB column names do not dictate API (`reserved_quantity` → not an accepted customer input).

### 13.3 Required / Optional / Conditional / Read-Only

- Each endpoint documents each input field as **required | optional | conditional | read-only | server-generated**. Do not infer from DB nullability. Example: `delivery_address` is **conditional** — required when `fulfillment_type=DELIVERY`, absent/`null` for `PICKUP`. `phone = ""` is not “not provided” unless contract says so.

### 13.4 Null Handling

- `null` **only** where field is explicitly nullable (e.g., `delivery_address: null` for `PICKUP`, `product_id: null` for custom request). `{"quantity": null}` where `quantity` is required is validation error. Do not use `null` as omission alias unless contractually defined.

### 13.5 Empty Strings & Whitespace

- Empty `""` is **not** universal “not supplied”. `{"phone": ""}` does not mean omitted unless contract defines it; prefer omission or `null` per field semantics. Validation rejects blank required text after normalization. **Whitespace:** server trims/normalizes user text (`"   Modern Sofa   "` → `"Modern Sofa"`, collapses internal whitespace) where appropriate; clients may cosmetic-trim but server is authoritative. Do not silently alter whitespace where meaningful (e.g., `notes` formatting preserved).

### 13.6 Case Normalization

- Enum and other CLOSED canonical values must be sent **exactly** as documented (`"product_type": "IN_STOCK"`). `in_stock`, `InStock`, `IN-stock` are validation errors unless aliased. CLOSED enums accept no `other`/`unknown` synonym.

### 13.7 Type Strictness & Numerics

- JSON types are strict: `{"quantity": 2, "is_active": true}` not `{"quantity":"2","is_active":"true"}`. No broad coercion. `quantity` = integer `>=1`; `percentage` etc. would be numeric with range/precision validated server-side. Client-side validation is never authoritative.

### 13.8 Money Input (Same Shape as Response — §3.8)

- Wire shape is **`{amount: int minor units (1 TZS=100), currency: "TZS"}`** — **same** as response. Never `"TZS 30,000"` string or bare `35000`. **Crucially, customer is not authoritative for money:** where `fulfillment_type: "DELIVERY"` is selected, backend resolves `delivery_fee` from server rules (price, `subtotal`/`total` calculated server-side per `AGENTS.md §13`). Never accept `{total: 1000}` or `{delivery_fee: {amount:1,currency:"TZS"}}` from customer as authoritative; such fields are **server-controlled** (see §13.10). Staff pricing operations use same `{amount,currency}` shape where contract explicitly permits it.

### 13.9 Enum Inputs — CLOSED

- All V1 enum inputs are **CLOSED**: `product_type`, `fulfillment_type`, `order_status`/`request_status`/`enquiry_status`/`payment_status` (query keys) and `role` — only documented `UPPER_SNAKE_CASE` values. Exception: responses document `availability` lowercase `available|unavailable` with `stock_indicator` display; request inputs for `availability` (where used) mirror that exact same exception. Unknown enum value → validation error, not `"other"`.

### 13.10 Server-Controlled Fields (Never Client-Authoritative)

- `id`, `created_at`, `updated_at`, `order_reference` (`OD-...`), `current_order_status` (`status`), `payment_status`/`payment_status`/`payment_confirmation`, `inventory` (`quantity`, `reserved_quantity`, `available_quantity`), `final_order_total`/`approved_delivery_fee`, `order_customer_identity`, `status_history`. If sent, they are **ignored or rejected** per endpoint contract (validation error for strict operations). Backend determines ownership from authenticated identity, not `{"user_id":"..."}`.

### 13.11 Client-Controlled Fields

- Legitimate client input: `product_id`/`variant_id` + `quantity`, `fulfillment_type`, `delivery_address` / `contact`, `request` `product_reference` + `quantity`/`dimensions`/`material`/`color`/`notes` + contact, `enquiry` `name/phone/email/subject/message` + optional `attachment`, profile `name`/`phone` (limited). Each endpoint later allow-lists its exact accepted fields.

*Checkout* input is account-scoped: `{"fulfillment_type":"DELIVERY","delivery_address":{...}}` only; backend resolves cart contents, current prices, stock, `delivery_fee`, `subtotal`, `total`, `order_reference`. *Cart add* input: `product_id`/`variant_id` + `quantity` only — no `price`/`subtotal`/`stock`. *Cart vs Order:* cart is not a reservation, not an order.

### 13.12 Create vs Update vs Action

- **Create:** client supplies business inputs; server supplies `id`, `timestamps`, `order_reference`, `status`, derived totals, security fields. Ex: `POST /enquiries {name,message}` → server sets `id, created_at, status`.
- **Update (`PATCH`):** partial — body contains **only fields being changed** (`{"phone":"+255..."}`), not full resource. Only documented mutable fields (`name`, `phone`) may be patched; not `role`, `account_status`, `order_total`, `price`.
- **Replace (`PUT`):** if permitted, means **complete replacement**; unused in V1 unless true replacement semantics are documented (otherwise `PATCH`).
- **Action (`POST /orders/{order}/ship` etc.):** minimal — e.g., `{"note":"Delivered to courier"}` not entire order + `{"status":"SHIPPED"}`. `ship` does not require full order body.

### 13.13 Nested Objects & Arrays

- **Nested:** Explicitly defined only (e.g., `shipping_address: {name, phone, address_line, city}`). Arbitrary nesting not persisted. Ownership respected: `{"order":{"customer_id":"someone-else"}}` cannot change ownership; server derives ownership from auth.
- **Arrays:** Single expected element type only (e.g., `items: [{product_id:"123", quantity:2}]`). Not heterogeneous. **Empty arrays:** semantics per resource — e.g., `items:[]` is **invalid** for create requiring ≥1 item, not equivalent to omission. **Duplicate items:** e.g., two lines with `product_id:"123"` — contract will define `invalid` vs `merged` vs `separate lines`; not left to DB.

### 13.14 Unknown Fields

- **Strict validation for create/update/action:** Unknown fields are **rejected** (validation error) rather than silently stored — detects typos, version mismatches, stale mobile clients, and mass-assignment attempts. Forward-compatibility exception (ignore unknown) only where explicitly documented. Do not let Laravel ` $request->all()` become policy.

### 13.15 Immutable & Historical Fields

- Conceptual immutable list (customer cannot change via normal operations): `order_reference`, `order_customer_identity`, `historical order_item unit_price`/`name`/`SKU`, `order created_at`, `payment_confirmation` (`payment_status`), `inventory ownership`, `status_history`, `final delivery_fee`. Updating current `product.price` never rewrites historical `order_item` snapshot; changing delivery fee policy never rewrites historical order fee.

### 13.16 File Uploads

- Attachments optional, via **`multipart/form-data`** where file bytes are sent (or pre-uploaded file reference where contract decides). Do not select storage provider in this phase; field names remain `snake_case` (`requested_dimensions` same in JSON and multipart). Signed/private URLs, size/type limits deferred to later storage contract.

### 13.17 Anonymous vs Authenticated Identity

- Authenticated requests: do **not** require `{"user_id":"current-user"}` — ownership from auth. Admin user-reference is separate authorized context.
- Anonymous made-to-order requests / enquiries: `user` optional; contact (`name` + `phone`/`email`) + `message`/`details` required; no fabricated user account.

### 13.18 Input Normalization

- Backend owns normalization: `email` lowercased/trimmed, `phone` normalized, whitespace as §13.5. Do not alter meaningful content unexpectedly. Normalization rules formally defined in later auth/input work.

### 13.19 Validation Ordering & Authorization

- Conceptual pipeline: `Transport/Content-Type → JSON syntax → type/schema → authentication → authorization → domain/business validation (including cross-field / state-dependent) → persistence/workflow`. Valid `order_id` ≠ allowed to modify order. Validation is separate from authorization.

### 13.20 Mass-Assignment & Mapping

- Laravel must not blindly `$request->all() → model fill`. Allow-list mapping: `validated input → DTO/command → domain logic`. API vocabulary stays intentional (DB field names not auto-accepted). See `AGENTS.md §3`.

### 13.21 Request Size & Idempotency

- **Size limits** will be enforced (JSON body, file upload, array size, cart items, message length); no huge limits, infrastructure not configured now.
- **Idempotency-sensitive:** `checkout`, `payment initiation`, `critical order actions` (cancel/ship/deliver) require later `Idempotency-Key` design — duplicate tap/reload/retry must not create duplicate business effects. Not implemented here.

### 13.22 Security

- Guard: SQL injection, XSS, malicious file upload, oversized payload, mass assignment, authorization bypass, parameter pollution (`quantity=2&quantity=100` → deterministic rejection), type confusion, unexpected nesting — backend-enforced, never frontend-only.

---

## 14. Links to Conventions & Resources

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility, **input**).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure and per-resource input).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).
