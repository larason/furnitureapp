# API Contract — Furniture E-Commerce Platform (Consolidated)

> **Version:** `v1` — base `/api/v1` · **Status:** Phase 1.27 — Notification API Contract (IN_APP primary, PRIVATE, CLOSED types, read_at)
> **Authority:** This file is the canonical response-envelope, resource-representation and validation contract for `v1`. Phase instruction files are temporary working docs; this file plus `api-conventions.md` / `api-resources.md` / `openapi.yaml` are the consolidated project knowledge per `phase-1.13.md §2` and `phase-1.15.md`.

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
- **Price for every product (single price contract):** `product.price` is **required, non-null** for every public product — **both `IN_STOCK` and `MADE_TO_ORDER`** (see `api-resources.md §10.1`). For `IN_STOCK` it is the purchasable unit price; for `MADE_TO_ORDER` it is a display/starting-at price (informational, SEO, listing) that is **never** used as a cart/checkout total — checkout of `MADE_TO_ORDER` is prohibited by domain validation regardless of price (`api-resources.md §10.3`, `api-contract.md §14.5`). The `null`/omission alternative was rejected to keep one consistent representation (`data.price` always `{amount,currency}`, never `null`). Making `price` nullable/omittable would be a breaking change per `§9`.
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

## 14. Validation Conventions — Authority & Layers (Phase 1.15)

> **Authority note:** This section plus `api-conventions.md §16` is the normative validation contract for `v1`. `docs/domain/business-rules.md` remains authoritative for business meaning. No Laravel `FormRequest`, DTO, domain validator, migration, OpenAPI request schema or frontend validator is implemented in this phase — architecture only. Payment provider-specific validation remains **Group H**.

### 14.1 Validation Layers (Separation of Concerns)

Validation is **layered, not monolithic** (API-VAL-001). Each layer has distinct responsibility:

1. **Transport validation** — is request structurally acceptable to infrastructure? `Content-Type` correct, JSON syntactically valid, body size within limits, HTTP method/media type supported. Failures: `malformed JSON`, `unsupported content type`, `body too large`. Not a business-rule violation.
2. **Schema / Input validation** — do supplied fields have correct structure/types? `quantity integer`, `email format`, `name max length`, `fulfillment_type enum`. Does **not** decide business facts (`is product in stock?` belongs later).
3. **Authentication** — who is caller? Valid session / bearer token / customer exists. Distinct from authorization.
4. **Authorization** — is authenticated actor allowed to perform operation on resource? `Customer→own order`, `Staff→operational order`, `Admin→management`. Valid structure may still fail authorization.
5. **Domain / Business validation** — is operation allowed per business rules? `IN_STOCK purchasable`, `MADE_TO_ORDER not checkout-eligible`, `order within cancellation window`, `fulfillment matches delivery info`, `transition allowed`. Enforces Phase 1.3 invariants.
6. **Cross-field validation** — multiple fields together (`fulfillment_type=DELIVERY → delivery address required`; `product_type=MADE_TO_ORDER → checkout prohibited`).
7. **Conditional validation** — fields conditionally required (`PICKUP → delivery address not required`; `DELIVERY → required`). No confusing universal `delivery_address always required` rule.
8. **State-dependent validation** — valid only in certain authoritative states (`Order=PROCESSING → SHIP may be valid`; `Order=COMPLETED → SHIP invalid`). Validate against current server state, not submitted value alone.
9. **Concurrency / Transaction validation** — still valid under concurrent operations (stock, order creation, critical transitions). Later implementation uses atomic/transactional mechanisms; `SELECT then UPDATE` without locking is prohibited.
10. **External-system validation** — provider/webhook verification (payment). Failures are not normal input validation errors; provider specifics are Group H.

**Client vs Server:** `Client validation → UX optimization` (e.g., Flutter disables Checkout when stock appears unavailable). `Server validation → authority` — Laravel must still verify.

### 14.2 Validation Decision Hierarchy (Normative Order)

```
1. Is transport valid?                       (Transport)
2. Is schema/type valid?                     (Schema)
        ↓
3. Is caller authenticated when required?   (Authentication)
        ↓
4. Is caller authorized?                     (Authorization)
        ↓
5. Is operation business-valid?              (Domain / cross-field / state)
        ↓
6. Is current state still valid under concurrency? (Transaction/locking)
        ↓
7. Can operation safely execute?             (Persistence/workflow / external)
```

- Do not access business records unnecessarily before basic schema validation.
- Do not perform expensive external operations before authorization.
- Do not leak authorization info: unauthenticated caller must not learn whether `Order OD-12345 exists` via ownership queries. `Not Found vs Unauthorized` handling is deliberately deferred to error contract but leakage is forbidden now. Authorization failures must not reveal private enumeration.
- `Transport → Schema → Auth → Authorization → Domain → Concurrency → External → Persistence` pipeline (see `api-conventions.md §16.4`).

### 14.3 Request Validation (Body) — Layered Pipeline

Conceptual checkout pipeline:

```
1. Transport valid
        ↓
2. Input schema valid
        ↓
3. Customer authenticated
        ↓
4. Customer authorized for cart
        ↓
5. Cart valid
        ↓
6. Products purchasable
        ↓
7. Inventory available
        ↓
8. Fulfillment valid
        ↓
9. Price calculated server-side
        ↓
10. Order/payment workflow
```

Do not collapse into one generic validator.

### 14.4 Query Validation

Query parameters (`phase-1.11`) are validated like bodies:

- `page` positive integer, `per_page 1–100` (Phase 1.12 rules).
- `product_type` in closed enum, `sort` in allow-list, `min_price <= max_price`.
- Only documented filter fields/operators accepted; no arbitrary `field/operator/value` DSL → DB query.
- `sort` only documented fields — never map arbitrary input to DB columns (correctness + security).

### 14.5 Cross-Field & Conditional Validation (Examples)

- `fulfillment_type = DELIVERY → delivery address required AND delivery fee determined by backend (customer input ignored)`.
- `fulfillment_type = PICKUP → delivery fee must be zero / not supplied as customer-controlled data`.
- `product_type = MADE_TO_ORDER → normal checkout prohibited`.
- Delivery example (better): `fulfillment_type=DELIVERY AND delivery information sufficient AND customer authorized AND fee backend-determined`. Bad: `delivery_address exists` alone.
- Cart example: `variant exists AND belongs to product AND active AND purchasable`.

### 14.6 Domain Validation — Purchasability, Inventory, Pricing, Fulfillment

**Product purchasability (conceptual):**
`Product exists → Product active → Product is IN_STOCK → Variant valid/belongs-to-product → Current stock sufficient → Quantity valid`. Do not rely solely on `product_type=IN_STOCK`; availability still matters.

**Inventory:** Authoritative, concurrency-safe: `read current stock → validate quantity → reserve/consume in transaction`. Later implementation protects against races (optimistic/pessimistic/atomic). UI `In Stock` is only informative.

**Pricing:** API validates request inputs (`product_id`, `quantity`) but **calculates** authoritative pricing itself (`current price`, `subtotal`, `delivery_fee`, `total`). Client-submitted `total` is never authoritative; reject as client-controlled field (API-VAL-005).

**Delivery fee:** `fulfillment_type=DELIVERY →` customer supplies delivery information; business operator determines approved fee. Customer input must not override `approved delivery fee`.

### 14.7 Cart, Checkout, Request, Enquiry, Profile Validation

- **Cart:** `product exists, variant belongs to product, product active, product type purchasable, quantity valid`. At checkout, inventory + current pricing re-checked.
- **Checkout:** See §14.3 pipeline; transaction boundary required.
- **Furniture request:** Validates `contact information, product reference when supplied, quantity when supplied, dimensions, material, color, notes, attachments`. Valid even when `user=null` (anonymous approved per REQ-001). `product_id` nullable; when supplied must belong/active.
- **Enquiry:** Validates `contact information, subject where required, message, attachments`. Authenticated User association optional, not mandatory.
- **Profile:** Each mutable field validated appropriately; do not treat `email`, `password`, `role`, `account_status` as ordinary profile fields — dedicated workflows.

### 14.8 State Validation — Cancellation & Order Transitions

**Cancellation (customer):**
`authenticated customer → owns order → order exists → customer cancellation allowed → 20-minute window valid → current order state eligible`. Backend determines authoritative time; `{"cancelled_at":"10:05"}` from client rejected. Not `status != COMPLETED` alone — better: `authenticated owner AND within 20-min AND state permits cancellation AND not already completed`.

**Order transitions (every controlled transition):**
`current state, requested transition, actor authorization, business preconditions`. Example `PROCESSING → SHIPPED` requires appropriate staff authorization and delivery preconditions. Do not allow `status=SHIPPED` bypassing transition validator. Business transitions cannot be performed via uncontrolled generic field updates (API-VAL-004). Failure must not leave partial state (e.g., inventory reservation without order) — atomicity required; domain validation inside/immediately around transaction where needed.

### 14.9 Enum, Unknown Fields & Immutable Fields

- **Enums CLOSED:** `input ∈ documented enum set` must be true (`IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`, `PENDING_PAYMENT` … `COMPLETED`, `CANCELLED`). Unknown value is invalid; do not normalize to `OTHER/UNKNOWN/CUSTOM` unless explicitly approved. New enum value is a compatibility decision (versioning policy). Physical DB representation may differ; API contract remains authoritative (storage does not dictate API). Exception: `availability` `available|unavailable` lowercase is frozen exception; all other V1 enums `UPPER_SNAKE_CASE`.
- **Unknown fields:** Phase 1.14 strict policy enforced — create/update/action bodies **reject** unknown fields (validation error), not silently stored. Prevents typos, stale clients, mass assignment. Forward-compatibility ignore only where explicitly documented. `Laravel $request->all()` must never become policy.
- **Immutable / Server-controlled fields:** Explicit distinction `ACCEPT / REJECT / IGNORE / SERVER-GENERATE`. Examples: ACCEPT `quantity` after validation; REJECT `order status` via generic write; REJECT `order total` as client-controlled; REJECT/IGNORE `created_at`. List: `id`, `created_at`, `updated_at`, `order_reference` (`OD-...`), `current_order_status`/`status`, `payment_status`/`payment_confirmation`, `inventory` (`quantity`/`reserved_quantity`/`available_quantity`), `final_order_total`/`approved_delivery_fee`, `order_customer_identity`, `status_history`, `final delivery_fee` — if sent, ignored or rejected per endpoint; backend derives ownership from auth, not `{"user_id":"..."}`. Resource input definitions must align with `api-resources.md` exposure levels.
- **Request types:** Classify `CREATE / UPDATE / ACTION / QUERY / AUTHENTICATION / UPLOAD / WEBHOOK` — each has different validation characteristics.

### 14.10 Authorization Separation & Information Hiding

Authentication determines **who**; authorization determines **is actor allowed on resource** (`Customer own order`, etc.). Valid structured request may still fail authorization. Response must not leak whether `Order OD-12345 exists` to unauthenticated/unauthorized caller via enumeration. Exact `Not Found vs Forbidden` mapping is deferred to error contract, but design must avoid leakage.

### 14.11 Database vs Domain, Transactions & Concurrency

- DB enforces `uniqueness, non-null, foreign-key integrity`; DB **cannot** decide `MADE_TO_ORDER cannot checkout`. Keep responsibilities separate. DB constraints are last line of defense; complementary to domain validation, not a replacement.
- Some domain validation must occur inside/around transaction: `stock availability, order creation, reservation, critical transitions` — prevent `validate → state changes → assumption stale` races.
- Identify later needs: `optimistic locking, pessimistic locking, atomic update, transaction isolation`. Requirement: **Business validation must remain correct under concurrent operations** (INV-003). Choice of mechanism deferred.
- Idempotency at correct boundary: **Client-initiated** `POST checkout` / `payment initiation` retried with same `Idempotency-Key` must not duplicate business effects. **Webhook (generic, provider-agnostic):** Repeated deliveries of the same provider event (e.g., payment webhook) must not duplicate payment/order mutations — requires stable event identity (provider event identifier / idempotency key) persisted via unique constraint and atomic check-then-apply inside transaction; duplicate delivery returns prior result without reapplication. Validation and idempotency must cooperate; provider-specific payload fields and signature rules remain Group H.
- Failure atomicity: failed business validation must not leave partial business state (inventory reservation without order).

### 14.12 Validation Error Categories, Codes & Stability

**Categories (canonical CLOSED codes per §15.15):**

- Structural/Input: `INVALID_TYPE`, `INVALID_FORMAT`, `INVALID_VALUE`, `MISSING_REQUIRED_FIELD`
- Business: `PRODUCT_NOT_PURCHASABLE`, `INSUFFICIENT_STOCK`, `ORDER_NOT_CANCELLABLE`, `INVALID_ORDER_TRANSITION`
- Authorization: `FORBIDDEN` (canonical; `RESOURCE_NOT_OWNED` is legacy alias for `FORBIDDEN`/`RESOURCE_NOT_FOUND` per §15.8 masking) and `AUTHENTICATION_REQUIRED` (canonical; `NOT_AUTHENTICATED` is legacy alias)
- External (later): `PAYMENT_PROVIDER_ERROR` (Group H; not normal input error)

**Ownership:** layer detecting error has clear responsibility (`quantity is string → schema`; `not logged in → auth`; `doesn't own order → authorization`; `outside 20-min → domain`; `provider rejected → external`). Do not report all as generic `INVALID_REQUEST` unless contract deliberately groups them.

**Stability:** Once error codes are part of contract, renaming `INSUFFICIENT_STOCK → OUT_OF_STOCK_ERROR` within `v1` is breaking. Codes vs messages are separate: frontend acts on machine code `INSUFFICIENT_STOCK` without parsing prose; messages may change more safely subject to compatibility policy; Next.js/Flutter may localize `"Only 2 units are available"` while API code stays authoritative. Exact JSON shape for `field`/`code`/`message` is deferred to error contract (Phase 1.16), but field-level paths (`delivery_address.city`) and nested paths must be representable, and multiple independent input errors should be returnable per request (first-error-only vs all safely determinable — recommendation: return all determinable schema errors; domain failures may stop early when unsafe). Rate limiting for `login`, `password recovery`, `anonymous request/enquiry`, `checkout`, `payment` is noted but not implemented here.

### 14.13 Message Security, Logging & Rate Limiting

- Messages must not expose `SQL details, schema, stack traces, internal paths, API secrets, payment credentials, private records`; do not return raw exception messages.
- Validation failures may be logged for diagnostics but **never** log `password`, `authentication token`, `payment secrets`, `full private addresses` indiscriminately.
- Validation- and authentication-sensitive operations should later have rate limits; record relationship now, implement later.

### 14.14 File Validation

Attachments require: `file size, content type, extension, actual file signature (client MIME not trustworthy), storage permissions, malware/security scanning where appropriate`. Storage provider remains deferred.

### 14.15 Validation and API Compatibility

Within `v1`, changing `previously accepted → rejected` or making field suddenly required can be breaking. Validation changes follow versioning policy. Because enums are CLOSED, adding `NEW_STATUS` may affect existing clients — treat as formal API compatibility decision; do not silently add to Laravel validation.

### 14.16 Business Rule Centralization & Reuse

A business rule has **one authoritative implementation boundary** (e.g., `20-minute cancellation → domain/application layer`, not reimplemented in `Next.js`, `Flutter`, `Laravel controller`, `Laravel model` separately). Clients may mirror for UX but backend remains authoritative (Rule 7). Purely structural validation may be reusable, but do not create `UniversalBusinessValidator`; prefer clear named rules `ValidateCheckoutInput`, `ValidateDeliverySelection`, `ValidateOrderCancellation`. Eventual architecture: `HTTP Request → Request Validator → Application Command → Domain Validation → Domain/Application Service → Repository/Transaction`. Do not place every business rule inside Form Request.

### 14.17 Validation Matrix (Conceptual)

| Operation       | Transport | Schema | Auth     | Authorization | Domain | Concurrency |
| --------------- | --------: | -----: | -------: | ------------: | -----: | ----------: |
| Browse products |       Yes |    Yes |       No |            No |    Yes |         Low |
| Add cart item   |       Yes |    Yes |     Yes* |           Yes |    Yes |      Medium |
| Checkout        |       Yes |    Yes |      Yes |           Yes |    Yes |        High |
| Cancel order    |       Yes |    Yes |      Yes |           Yes |    Yes | Medium/High |
| Submit request  |       Yes |    Yes | Optional |    Contextual |    Yes |         Low |
| Submit enquiry  |       Yes |    Yes | Optional |    Contextual |    Yes |         Low |
| Update profile  |       Yes |    Yes |      Yes |           Yes |    Yes |         Low |
| Ship order      |       Yes |    Yes |      Yes |           Yes |    Yes |        High |

`*` depends on final cart/authentication design; do not use table to override earlier approved behavior. All flows respect layered hierarchy §14.2.

### 14.18 Testing & Auditability

- Every business validation rule must eventually have tests: `invalid product type, insufficient stock, invalid variant, missing delivery information, invalid enum, unknown field, unauthorized order, expired cancellation window, invalid order transition, duplicate checkout` — covering success and failure.
- Do not test business rules only via React/Flutter UI; backend/domain level is required.
- Later API integration tests: `request → validation → authorization → business operation` as complete contract.
- Later E2E tests: `anonymous browse, authenticated checkout, request submission, enquiry submission, order cancellation, order tracking` — backend remains source of truth.
- Important state changes (`order cancellation, order status transition, inventory adjustment, payment status change`) should eventually be auditable; not every failed request needs permanent audit. Audit infrastructure not built in this phase.

### 14.19 What Remains Deferred

- Laravel `FormRequest` classes, DTOs, domain validators, DB constraints, migrations, OpenAPI request schemas, final error JSON, error middleware, authentication/authorization implementation, provider-specific payment validation (Group H), Next.js/Flutter forms — **not created in this phase** per Explicitly Out of Scope.

---

## 15. Error Contract — Machine-Readable, Secure, Versioned (Phase 1.16)

> **Authority note:** This section plus `api-conventions.md §17` is the normative error contract for `v1`. Errors are part of the public API contract — clients branch on `code` + `HTTP status` + `field`, never on English `message`. Payment provider-specific error codes/workflows remain **Group H**; generic external-service principles are defined here. No Laravel handler, exception class, middleware or OpenAPI error schema is implemented in this phase — architecture only. All `v1` error codes/categories remain **CLOSED** per §15.3 (same as validation enums).

### 15.1 Standard Error Envelope (One Structure, Always Array)

All API errors use one top-level member:

```json
{
  "errors": [
    {
      "code": "PRODUCT_NOT_PURCHASABLE",
      "message": "This product cannot be purchased."
    }
  ]
}
```

- Top-level is always `errors` (**array**, even for single error) — never `error`, `message`, `errors.message`, `failure`, `success:false` envelope. This supports multiple validation/field errors and future structured errors without envelope drift (`§9-10`).
- Successful responses use `data` (`§2`); error responses use `errors` and **never** include `data` sibling (no `{"data":null,"error":"..."}` or `{"success":false,"data":null}`); easy client branching `HTTP success → data` vs `HTTP error → errors` (`§63`).
- Do not return multiple unrelated business errors merely to pad the array; one precise error is preferred when only one domain failure occurred.
- Business-result metadata belongs in `meta` (e.g., `meta.request_id`), never business error objects inside `meta` (`§62`).

### 15.2 Standard Error Object — Fields

Conceptual fields per error object (`§11`):

- `code` **(required)** — stable machine code `UPPER_SNAKE_CASE` (e.g., `INVALID_VALUE`, `INSUFFICIENT_STOCK`). Authoritative for client branching.
- `message` **(required)** — concise human-readable, safe, action-oriented; not authoritative for machines; may evolve. Never `SQL…`, stack trace, or framework exception text.
- `field` **(optional, string)** — for field-level validation failures; canonical dot-notation path (`fulfillment_type`, `delivery_address.city`, `items.0.quantity`). Only where relevant; not every error has a field.
- `details` **(optional, object)** — structured safe context where useful, never a second message string. E.g., `{"available_quantity":2,"requested_quantity":5}` for `INSUFFICIENT_STOCK`. Only safe-for-caller data; no `other_customer_id`, `internal_reservation_id`, `provider_secret`, `database_key`, `staff_only_note` (`§27, §59, §61`).
- `request_id` — **not** inside the error object itself; documented at envelope `meta.request_id` (`§15.12`).

Only fields relevant to the particular error are present. Do not require every error to contain `field`/`details`.

**Examples:**

Single field error:

```json
{
  "errors": [
    { "code": "INVALID_VALUE", "message": "The selected fulfillment type is invalid.", "field": "fulfillment_type" }
  ]
}
```

Business error with details:

```json
{
  "errors": [
    {
      "code": "INSUFFICIENT_STOCK",
      "message": "The requested quantity is not available.",
      "details": { "requested_quantity": 5, "available_quantity": 2 }
    }
  ]
}
```

### 15.3 Error Codes — Stable, UPPER_SNAKE_CASE, CLOSED, Not Impl Leaks

- **Naming:** `UPPER_SNAKE_CASE` (`AUTHENTICATION_REQUIRED`, `INSUFFICIENT_STOCK`, `ORDER_NOT_CANCELLABLE`). No `authenticationRequired`, `insufficient-stock`, `InsufficientStock` (`§13`).
- **Stable:** Every public error has a documented stable `code`; renaming `INSUFFICIENT_STOCK → NOT_ENOUGH_INVENTORY` within `v1` is **breaking** (versioning policy); human messages may improve safely (`§14-15, §55, §84`).
- **Not messages:** Do not use English prose as machine contract; frontend acts on `code` (`INSUFFICIENT_STOCK → refresh stock UI`), may localize to `"Only 2 units are currently available."` (`§16, §68-70`).
- **CLOSED:** Documented code/category lists are **CLOSED** per global policy (`§85`); adding a new code for a newly introduced operation is potentially non-breaking but reviewed; removing/renaming is breaking.
- **Not impl leaks:** Never expose `SQLSTATE_23000`, `MYSQL_DUPLICATE_ENTRY`, `ModelNotFoundException`, `ValidationException`, `Laravel Exception …`, raw parser traces, `password`/`secret`/`file path`/`stack` (`§56-57, §28`). Map impl exceptions to stable API codes.
- **No namespaces in V1:** Prefer `INSUFFICIENT_STOCK` over `commerce.inventory.insufficient_stock` unless domain explosion justifies it (`§58`).
- **Avoid explosion/ambiguity:** New code only when client must behave differently; `INSUFFICIENT_STOCK` is valuable, `INSUFFICIENT_STOCK_FOR_SOFA` is not (`§79`). Avoid generic `ERROR`/`FAILED`/`BAD_REQUEST`/`SOMETHING_WENT_WRONG` alone — HTTP status already gives broad class; code gives machine meaning (`§80`).

**Controlled initial registry (see §15.15):** `VALIDATION_ERROR`, `MISSING_REQUIRED_FIELD`, `INVALID_VALUE`, `INVALID_FORMAT`, `INVALID_TYPE`, `INVALID_JSON`, `METHOD_NOT_ALLOWED`, `UNSUPPORTED_MEDIA_TYPE`, `REQUEST_TOO_LARGE`, `AUTHENTICATION_REQUIRED`, `INVALID_AUTHENTICATION`, `INVALID_CREDENTIALS`, `SESSION_EXPIRED`, `NOT_AUTHENTICATED`, `FORBIDDEN`, `RESOURCE_NOT_OWNED`, `RESOURCE_NOT_FOUND`, `PRODUCT_NOT_FOUND`, `PRODUCT_NOT_PURCHASABLE`, `PRODUCT_UNAVAILABLE`, `INVALID_PRODUCT_VARIANT`, `CART_NOT_FOUND`, `CART_ITEM_NOT_FOUND`, `INVALID_CART_ITEM`, `CART_ITEM_UNAVAILABLE`, `CHECKOUT_REQUIRES_AUTHENTICATION`, `CHECKOUT_NOT_ALLOWED`, `CART_INVALID`, `INSUFFICIENT_STOCK`, `INVALID_FULFILLMENT`, `INVALID_DELIVERY_INFORMATION`, `ORDER_NOT_FOUND`, `ORDER_NOT_CANCELLABLE`, `INVALID_ORDER_TRANSITION`, `ORDER_STATE_CONFLICT`, `REQUEST_NOT_FOUND`, `INVALID_REQUEST`, `ENQUIRY_NOT_FOUND`, `INVALID_ENQUIRY`, `INVALID_ATTACHMENT`, `ATTACHMENT_TOO_LARGE`, `UNSUPPORTED_ATTACHMENT_TYPE`, `CONFLICT`, `RESOURCE_VERSION_CONFLICT`, `DUPLICATE_OPERATION`, `RATE_LIMITED`, `EXTERNAL_SERVICE_ERROR`, `INTERNAL_SERVER_ERROR`. Payment-specific codes remain Group H. (`NOT_AUTHENTICATED` is a legacy alias for `AUTHENTICATION_REQUIRED`; `RESOURCE_NOT_OWNED` is a legacy alias for `FORBIDDEN`/`RESOURCE_NOT_FOUND` per §15.8 masking — canonical codes are `AUTHENTICATION_REQUIRED`/`FORBIDDEN`/`RESOURCE_NOT_FOUND`; aliases remain registered as CLOSED for compatibility but new clients must use canonical codes.)

### 15.4 HTTP Status and API Error Code Are Separate

Every response has **both** `HTTP status` and `API error `code`` (`§17`). Status gives transport class; code gives machine-readable business meaning.

**Preliminary status matrix (convention, endpoint exceptions documented individually):**

| Category | HTTP status | Notes |
|---|---|---|
| Malformed JSON syntax | 400 | `INVALID_JSON`, parser traces hidden (`§41`) |
| Business/validation failure (well-formed but invalid) | 422 | Ordinary schema/domain/business failures (`§19`) — see §15.5 |
| State/concurrency conflict (valid but cannot apply now) | 409 | Stock race, invalid transition as conflict (`§36-37, §67`) |
| Authentication missing/invalid | 401 | `AUTHENTICATION_REQUIRED`/`INVALID_AUTHENTICATION` (`§20`) — never 403 for unauthenticated |
| Authenticated but not authorized | 403 | `FORBIDDEN` (`§21`) — hide existence where needed per §15.8 |
| Resource not addressable in context | 404 | `RESOURCE_NOT_FOUND` / `ORDER_NOT_FOUND` etc. (`§22`) |
| Unsupported HTTP method | 405 | `METHOD_NOT_ALLOWED` — envelope still `{"errors":[...]}` (`§39`; `code` remains `METHOD_NOT_ALLOWED`, not ad-hoc `METHOD_NOT_SUPPORTED_ERROR`) |
| Unsupported Content-Type | 415 | `UNSUPPORTED_MEDIA_TYPE` — envelope still `{"errors":[...]}` (`§40`; `code` remains `UNSUPPORTED_MEDIA_TYPE`) |
| Payload exceeds limits | 413 | `REQUEST_TOO_LARGE` (`§42`) |
| Rate limited | 429 | `RATE_LIMITED` + `Retry-After` header (`§35, §43`) |
| Temporary upstream/provider failure | 502/503/504 | Mapped to `EXTERNAL_SERVICE_ERROR` family, not raw provider text (`§82-83`) |
| Unexpected server failure | 500 | Generic `INTERNAL_SERVER_ERROR` only (`§29`) |

Do not assign every business error to `500`; status choice is version-sensitive (incompatible status change is breaking, §84).

**Validation default:** Structurally well-formed requests that fail input/domain/business validation use **`422 Unprocessable Content`** by default (`VALIDATION_ERROR`, `INVALID_VALUE`, `PRODUCT_NOT_PURCHASABLE`, `ORDER_NOT_CANCELLABLE`, etc.) unless the failure is more precisely a state/concurrency conflict where `409` better communicates *refresh/retry after reconcile* (`§19, §37`). The registry column notes `409/422*` where endpoint contract later resolves.

### 15.5 Validation Error Representation

Validation failures **must** be field-addressable and, where safe, multi-error (`§25-26`):

- Single field dot path: `delivery_address.city`, `delivery_address.phone` — one canonical style across API (`§60`); arrays evaluated as `items.0.quantity` or documented alternative, but one style only.
- Unknown-field and closed-enum failures use same shape: `{"code":"INVALID_VALUE","field":"fulfillment_type","message":"..."}` — no special `OTHER` normalization (`§54`).
- Multiple field errors: For ordinary schema validation, **return all safely determinable errors** in one array rather than stopping at first trivial error. Example multi-error envelope (`§26`) is normative. Do not fabricate multiple unrelated business errors to fill array.

Field-level details identify the exact problem; do not create per-field tiny codes like `NAME_MISSING` unless demonstrated — prefer reusable `MISSING_REQUIRED_FIELD` + `field` (`§54`).

Business-rule specifics that need `details` stay safe: `INSUFFICIENT_STOCK` may include `requested_quantity`/`available_quantity`; never leak `internal_inventory_reservation_id` or `staff_only_note` (`§27, §61`).

### 15.6 Authentication Errors (401)

Use **401 Unauthorized** when authentication is missing or invalid (`§20, §52`):

- Codes: `AUTHENTICATION_REQUIRED` (missing), `INVALID_AUTHENTICATION`, `INVALID_CREDENTIALS`, `SESSION_EXPIRED`. Contract is included in error envelope; exact flows refined in Phase 1.17.
- Do not use `403` for unauthenticated-required paths; do not invent provider payment codes here.

### 15.7 Authorization Errors (403) & Ownership

Use **403 Forbidden** when `authenticated && not authorized` (`§21, §53`):

- Baseline code `FORBIDDEN` (canonical; `RESOURCE_NOT_OWNED` is a legacy alias registered in §15.15 but prefer `FORBIDDEN` or `RESOURCE_NOT_FOUND` masking per §15.8). Do not expose *why* private resource belongs to another customer.
- All authorization/resource-existence decisions must respect §15.8 leakage rules; generic envelope prevents enumeration.

### 15.8 Resource Not Found (404) & Customer Ownership / Enumeration Protection

Use **404 Not Found** with `RESOURCE_NOT_FOUND` (or `ORDER_NOT_FOUND`, `PRODUCT_NOT_FOUND`, etc.) when resource not addressable in request context (`§22`):

- For **private, customer-owned resources** (Orders, etc.), an ownership failure **should not** reveal existence. **Normative rule:** If `Customer A` requests `Customer B`'s order, respond with **`404 RESOURCE_NOT_FOUND`** (or resource-specific `ORDER_NOT_FOUND`) **from that customer's perspective**, not `403 FORBIDDEN`, to avoid leaking `Order OD-xxx exists` (`§23-24`, consistent with validation §14.10 and decisions API-VAL-010).
- Probing `GET /orders/1001`…`1003` must not provide differential errors that enumerate private identifiers; authorization + error behavior cooperate to be non-distinguishing.
- Internal unexpected errors never masquerade as `404`; `500` stays distinct.

### 15.9 Business-Rule Errors (Explicit, Endpoint-Anchored)

Do not collapse business failures into single `VALIDATION_ERROR` when client UX must differ (`§44`):

- `PRODUCT_NOT_PURCHASABLE` / `PRODUCT_UNAVAILABLE` / `INVALID_PRODUCT_VARIANT` / `PRODUCT_NOT_FOUND` — `MADE_TO_ORDER` prohibited via cart/checkout despite having display price (`§45, §14.6`)
- `CART_NOT_FOUND` / `CART_ITEM_NOT_FOUND` / `INVALID_CART_ITEM` / `CART_ITEM_UNAVAILABLE` (`§46`) — distinct from `ORDER_*`
- `CHECKOUT_REQUIRES_AUTHENTICATION` / `CHECKOUT_NOT_ALLOWED` / `CART_INVALID` / `INSUFFICIENT_STOCK` / `INVALID_FULFILLMENT` / `INVALID_DELIVERY_INFORMATION` (`§47`)
- `ORDER_NOT_FOUND` / `ORDER_NOT_CANCELLABLE` (20-min window, state) / `INVALID_ORDER_TRANSITION` / `ORDER_STATE_CONFLICT` (`§48`)
- `REQUEST_NOT_FOUND` / `INVALID_REQUEST` (`§49`), `ENQUIRY_NOT_FOUND` / `INVALID_ENQUIRY` (`§50`) — concise vocabulary, not per-field explosion
- `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE` (`§51`) — file validation

Each later endpoint documents the **subset it may return** rather than inventing undocumented `CHECKOUT_FAIL` codes locally (`§86`); docs keep authoritative registry.

### 15.10 Conflict & Concurrency Errors (409) & Idempotency

Use **409 Conflict** when structurally valid request cannot complete due to current state/concurrency (`§36-37, §65, §67`):

- Codes: `CONFLICT`, `ORDER_STATE_CONFLICT`, `RESOURCE_VERSION_CONFLICT`, `DUPLICATE_OPERATION`, or business `INSUFFICIENT_STOCK`/`INVALID_ORDER_TRANSITION` mapped to `409` when the semantics are *conflict/retry-after-refresh* vs `422` *correct-input*.
- Inventory race example: client sees `stock=1`, another purchases, then checkout → `409 INSUFFICIENT_STOCK` (or `422` per resolved mapping — endpoint contract documents the choice and retry guidance) (`§37`).
- Idempotency conflict: duplicate `Idempotency-Key` must have **deterministic** semantics — documented per operation as *return original result* / *409 `DUPLICATE_OPERATION`* / `422` — not ad-hoc; webhook duplicate deliveries follow same atomic `check-then-apply` per `§14.11`/`§16.9` (`§38, §66`). Error response must never correspond to a phantom reservation (commit status ≠ error, §65).

### 15.11 Rate Limiting (429) & Standard Retry Header

Use **429 Too Many Requests** with `RATE_LIMITED` where throttled (`§43`):

- Never expose internal rate-limit algorithms; do not use arbitrary `retry_after_seconds`/`wait` fields — use standard **`Retry-After`** HTTP header for rate-limit guidance (`§35`). Body still follows `{"errors":[...]}` plus header.

### 15.12 External-Service Errors (Generic, Payment Deferred)

When future provider/upstream fails, **map** to stable project-level API error (`§82-83`):

- Do not expose `provider error string`, internal `provider code`, or `provider credentials`. Client sees project-level `EXTERNAL_SERVICE_ERROR` (or more specific family when Group H refines payment) with HTTP `502/503/504` as appropriate; details stay safe and generic.
- Payment-specific codes, reconciliation, webhook failures, timeout behavior are **Group H**; global envelope accommodates them without breaking existing codes (`§83`, `§53` note).

### 15.13 Request / Correlation ID

- Placement: **envelope-level** `meta.request_id` alongside `errors`, consistent with success `meta` (`§30, §62`). Example:

```json
{
  "errors": [{ "code": "INTERNAL_SERVER_ERROR", "message": "An unexpected error occurred." }],
  "meta": { "request_id": "01H9…-req" }
}
```

- Purpose: connects customer-facing error → server logs/monitoring without embedding secrets; support workflow is *"Please provide your request ID"* (`§31, §90`). ID is per-request processing context, **not** `user_id`/`order_id`/`payment_id`/`session token` unless explicitly designed (`§32`), and must not contain sensitive information. Logging of `INTERNAL_SERVER_ERROR` keeps full exception/stack on server, client only sees generic envelope (`§29, §89`).

### 15.14 Retry Guidance (Conceptual, Not Per-Error Field)

API conceptually distinguishes (`§33-34`):

- **Not retryable without changing input/state** — `INVALID_VALUE`, `MISSING_REQUIRED_FIELD`, `PRODUCT_NOT_PURCHASABLE`, `ORDER_NOT_CANCELLABLE`, `FORBIDDEN` (4xx validation/authorization)
- **Retryable** — `temporary service unavailable` (502/503/504), transient upstream — client may retry as-is
- **Requires re-read/reconciliation** — `CONFLICT`, `INSUFFICIENT_STOCK`, `INVALID_ORDER_TRANSITION` (409) — client must refresh resource/state before retry

Do not add arbitrary `retryable:true` field to every error; contract defines classes that are candidates for retry. Final client behavior remains application-specific; `Retry-After` used only for `429` (`§35`).

### 15.15 Error Code Registry (Controlled, CLOSED)

| Code | Meaning | Typical HTTP | Client action |
|---|---|---:|---|
| `VALIDATION_ERROR` | General validation failure | 422 | Correct input |
| `MISSING_REQUIRED_FIELD` | Required field absent | 422 | Supply field (`field` indicates which) |
| `INVALID_VALUE` | Field value not in allowed set | 422 | Correct value |
| `INVALID_FORMAT` | Field format invalid | 422 | Correct format |
| `INVALID_TYPE` | Field type invalid | 422 | Correct type |
| `INVALID_JSON` | Malformed JSON syntax | 400 | Fix payload |
| `METHOD_NOT_ALLOWED` | HTTP method not allowed for resource | 405 | Use allowed method (`Allow` header) |
| `UNSUPPORTED_MEDIA_TYPE` | Content-Type not supported | 415 | Use `application/json` or `multipart/form-data` where contract allows |
| `REQUEST_TOO_LARGE` | Payload exceeds limits | 413 | Reduce payload |
| `AUTHENTICATION_REQUIRED` | Authentication missing | 401 | Authenticate |
| `INVALID_AUTHENTICATION` | Authentication invalid/expired | 401 | Re-authenticate |
| `INVALID_CREDENTIALS` | Credentials invalid | 401 | Correct credentials |
| `SESSION_EXPIRED` | Session expired | 401 | Re-authenticate |
| `NOT_AUTHENTICATED` | Not authenticated (legacy alias — prefer `AUTHENTICATION_REQUIRED`; CLOSED, registered for compatibility) | 401 | Authenticate |
| `FORBIDDEN` | Authenticated but not permitted | 403 | Stop/adjust access (no enumeration) |
| `RESOURCE_NOT_OWNED` | Authenticated but not owner (legacy alias — prefer `FORBIDDEN` or `RESOURCE_NOT_FOUND` masking per §15.8; CLOSED) | 403 | Stop/adjust access or treat as not-found where ownership masked |
| `RESOURCE_NOT_FOUND` | Resource unavailable in context | 404 | Reconcile / treat as not-found (404 masks ownership per §15.8) |
| `PRODUCT_NOT_FOUND` | Product not found | 404 | Reconcile |
| `PRODUCT_NOT_PURCHASABLE` | Product cannot be purchased (e.g., `MADE_TO_ORDER`) | 422 | Refresh/catalog; show request flow |
| `PRODUCT_UNAVAILABLE` | Product inactive/unavailable | 422 | Refresh |
| `INVALID_PRODUCT_VARIANT` | Variant invalid/belongs/active/purchasable | 422 | Correct variant |
| `CART_NOT_FOUND` | Cart not found | 404 | Reconcile |
| `CART_ITEM_NOT_FOUND` | Cart item not found | 404 | Refresh cart |
| `INVALID_CART_ITEM` | Cart item invalid | 422 | Correct item |
| `CART_ITEM_UNAVAILABLE` | Cart item unavailable | 422 | Refresh |
| `CHECKOUT_REQUIRES_AUTHENTICATION` | Checkout needs auth (legacy alias — identical to `AUTHENTICATION_REQUIRED` 401; prefer `AUTHENTICATION_REQUIRED`; CLOSED, registered for compatibility) | 401 | Authenticate |
| `CHECKOUT_NOT_ALLOWED` | Checkout not allowed (cart invalid etc.) | 422 | Correct cart |
| `CART_INVALID` | Cart invalid for checkout | 422 | Correct cart |
| `INSUFFICIENT_STOCK` | Requested stock unavailable | 409/422* | Refresh stock/retry after reconcile |
| `INVALID_FULFILLMENT` | Fulfillment choice invalid | 422 | Correct fulfillment |
| `INVALID_DELIVERY_INFORMATION` | Delivery info invalid/incomplete | 422 | Correct address |
| `ORDER_NOT_FOUND` | Order not found / not owned (masked) | 404 | Reconcile |
| `ORDER_NOT_CANCELLABLE` | Cancellation not allowed (window/state) | 422/409* | Stop / show why |
| `INVALID_ORDER_TRANSITION` | State transition invalid | 409/422* | Refresh/reconcile |
| `ORDER_STATE_CONFLICT` | Order state conflict (concurrency) | 409 | Refresh/retry |
| `REQUEST_NOT_FOUND` | Furniture request not found | 404 | Reconcile |
| `INVALID_REQUEST` | Request payload invalid | 422 | Correct input |
| `ENQUIRY_NOT_FOUND` | Enquiry not found | 404 | Reconcile |
| `INVALID_ENQUIRY` | Enquiry invalid | 422 | Correct input |
| `INVALID_ATTACHMENT` | Attachment validation failed | 422 | Correct file |
| `ATTACHMENT_TOO_LARGE` | Attachment exceeds size | 413/422 | Reduce file |
| `UNSUPPORTED_ATTACHMENT_TYPE` | Attachment type not allowed | 422 | Change type |
| `CONFLICT` | Generic state/concurrency conflict | 409 | Refresh/retry |
| `RESOURCE_VERSION_CONFLICT` | Version conflict | 409 | Refresh/retry |
| `DUPLICATE_OPERATION` | Duplicate idempotent operation | 409 | Use prior result |
| `RATE_LIMITED` | Too many requests | 429 | Retry after `Retry-After` |
| `EXTERNAL_SERVICE_ERROR` | Temporary upstream/provider failure (generic) | 502/503/504 | Retry/contact support |
| `INTERNAL_SERVER_ERROR` | Unexpected server failure | 500 | Retry/contact support with `request_id` |

`*` `INSUFFICIENT_STOCK` / `ORDER_NOT_CANCELLABLE` / `INVALID_ORDER_TRANSITION` mapping `409 vs 422` is resolved per endpoint business contract; table shows convention with asterisk. Do not finalize resource-specific statuses prematurely where endpoint semantics not yet fixed.

- Payment-specific extensions (Group H) slot into same table shape without altering global envelope.
- Codes like `SQLSTATE_23000` / `ModelNotFoundException` are forbidden as public codes (`§56-57`).
- Structured safe `details` may accompany rows where useful (`§15.2`), e.g., `INSUFFICIENT_STOCK` with `requested_quantity`/`available_quantity`; never internal reservation IDs.

### 15.16 Security & Information-Disclosure Rules

Production errors **never** expose (`§28, §58-59, §61, §88`):

- `database SQL`, stack traces, `password`/`authentication secret`/`payment secret`, `internal file paths`, server IPs, framework exception names, `other_customer_id`, `internal_inventory_reservation_id`, `provider_secret`, `staff_only_note`.
- Logging keeps full diagnostics server-side; client sees safe `code`/`message`/`field`/`details` + `meta.request_id` (`§89`).
- Security tests must verify unauthorized access does **not** reveal `resource existence`, `private data`, `internal identifiers`, `database details` through errors (`§88`).
- Error/transaction correspondence enforced: error response accurately reflects commit status — `INSUFFICIENT_STOCK` must not hide a phantom reservation (`§65`).

### 15.17 Compatibility (Version-Sensitive)

Within `v1`, **potentially breaking** (`§84`): remove/rename error code, change semantics, change field-path format (`delivery_address.city` → other), remove documented detail, change HTTP status incompatibly, make former error suddenly success.

**Potentially non-breaking:** improve human `message`, add optional safe `details` field, add documentation, add new error code for newly introduced operation (reviewed for client impact). Error codes remain versioned API contract elements (`§55, §84-85`); human messages are not machine identifiers (`§92`).

CLOSED enum policy extends to error codes/categories (`§85`); `available|unavailable` lowercase exception does not apply to error codes (always `UPPER_SNAKE_CASE`).

### 15.18 What Remains Deferred

- Laravel exception handlers/middleware/exception classes, rate-limiting implementation, payment provider-specific codes/webhook error handling (Group H), endpoint-specific error schemas, OpenAPI error schemas, Next.js/Flutter error-handling code (`§93`). Minimal business failures go through envelope; batch partial-success was evaluated and **not** adopted for core `checkout/order/payment/cancellation` — explicit business result per operation (`§64`).

### 15.19 Cross-References

See `api-conventions.md §17` for reusable error-convention summary, `api-resources.md §11` for resource-specific error mappings, `docs/domain/business-rules.md §14` for business-error ↔ rule mapping, and `decisions.md ADR/API-ERR-001..006`.

## 17. Authentication Contract — Actors, Sessions, Recovery, Security (Phase 1.17)

> **Authority note:** This section plus `api-conventions.md §18` and `api-resources.md §12` is the normative authentication contract for `v1`. `docs/domain/business-rules.md §17` governs business meaning; `decisions.md ADR/AUTH-*` records choices. No Laravel/Sanctum code, migrations, middleware, or frontend screens are implemented in this phase — architecture only. Roles are **CLOSED** (`CUSTOMER`, `STAFF`, `ADMIN`); adding `MANAGER`/`DELIVERY_AGENT` etc. requires compatibility review. Payment auth interactions remain compatible with Group H.

### 17.1 Actors & Role Hierarchy — Customer Owns Account, Staff Operate, Admin Approves

**Three human actors, shared identity system, different authorization scopes:**

- `CUSTOMER` — primary user: `less admin permission + maximum customer-facing flexibility + full ownership of own account` (public self-registration, browse/search/order owned orders). `STAFF` — operational: receive/process orders, catalog/inventory/order ops, requests/enquiries, operational notifications — **not** customer-account owners, no customer privileges by default. `ADMIN` — highest application permission: `staff approval/management`, `administrative configuration`, `high-level operational management`, `authorized customer/account administration` (only under explicit security policy, not casual impersonation).

Hierarchy:

```
CUSTOMER → limited admin, owns own account
   ↓
STAFF → operational permission
   ↓
ADMIN → highest admin permission (not unrestricted impersonation / business-invariant bypass)
```

**Critical distinction:** `ADMIN`/`STAFF` do **not** "own" customer accounts:

```
Customer → owns own account
Staff/Admin → may have authorized admin access to specific operational data only
```

### 17.2 Customer Registration — Public Self-Registration Only

- Customers: **public self-registration without staff approval** (`Visitor → Register → Customer account → Authenticate → Customer experience`). Staff must not gate every customer registration unless later business explicitly requires it. Minimal required data: `name`, `email`, `phone`, `password` (mandatory/optional deferred to detailed registration contract); do not require `address book`/`delivery address` at registration.
- Staff: **not** via public registration — approved/invited administrative creation (`Staff candidate → Admin review → Approved → Staff account activated` or `Admin creates/invites → Staff activates`). Self-registration as `STAFF` via public form is prohibited.
- Admin: **administrative creation / bootstrap** — no public `POST {role: ADMIN}` self-promotion (see §17.5). Initial admin via controlled deployment/setup.
- All role assignment is **server-controlled**; clients cannot submit `role` to promote themselves (body `role: ADMIN` rejected).

### 17.3 Customer Login — Shared Identity Across Website & Flutter

Both `Next.js Website` and `Flutter App` authenticate against **same central Laravel authentication** and share one customer identity/account:

```
Next.js ──┐
          ├──→ Laravel Authentication
Flutter ──┘
```

- Customer registered on website can log in on Flutter with same credentials and see own orders/profile/requests; no duplicated accounts per client. Shared identity is normative; feature availability per client may differ later, but account is single.
- Login does not distinguish `email exists` vs `not exists` in error responses (see §17.14) to prevent enumeration.

### 17.4 Staff Authentication — Same System, STAFF Role, Authz After Auth

Staff authenticate through same central system and receive `role: STAFF`. Authentication answers `Is this person really this staff account?`; authorization (Phase 1.18) answers `What may they do?` — concepts not collapsed. Staff approval is **Admin-controlled** (see §17.6). Staff account lifecycle concepts (`PENDING`/`ACTIVE`/`SUSPENDED`/`DISABLED`) are deferred to authorization/account phase — not a new public role enum.

### 17.5 Admin Authentication — ADMIN Role, Server-Controlled, No Self-Promotion

Admins authenticate as `role: ADMIN` via trusted administrative processes only. Client-submitted `{"role":"ADMIN"}` is rejected; role tampering yields `403 FORBIDDEN` (or `422` for invalid role value) per CLOSED enum. Bootstrap remains deployment-controlled; implementation deferred.

### 17.6 Staff Approval — Admin Approves, No Self-Approval

Invariant: **only authorized Admin approves staff**; staff cannot approve themselves, customers cannot approve staff. Onboarding is either `candidate → Admin review → Approved → Active` or `Admin invite → staff activates` — exact workflow deferred to authorization phase. This is non-negotiable (ownership of approval).

### 17.7 Session / Token Principles — Browser vs Flutter, Cross-Platform, Revocation

- `Next.js Website` → **first-party authenticated browser session** (secure, httpOnly cookies, no long-lived secrets exposed to JS unnecessarily) — suitable for SSR. `Flutter App` → **authenticated API credential/token** against same Laravel identity. `Admin` → administrative session/client, same backend identity, role-gated.
- **Cross-platform identity** is mandatory (`Visitor → Register (web) → Login (Flutter) → same account`). No per-client account duplication.
- **Logout:** every authenticated client has explicit logout that invalidates the applicable server-side session/credential; deleting frontend token alone is insufficient if server state is revocable.
- **Multi-device baseline:** multiple legitimate customer sessions (web/phone/tablet/browser) are **permitted**; logout on one device does **not** invalidate all others. Revocation of individual sessions is a later security control.
- **Staff/Admin sessions:** may have multiple approved sessions but support stronger controls (shorter idle timeout, revocation, visibility) — evaluated later.
- **Expiration/revocation:** conceptually `active → expired → revoked`; durations chosen later based on customer convenience vs privilege. Revocation events: logout, password change, admin security action, compromised credential — mechanism deferred.

### 17.8 Password Recovery — Secure, Time-Limited, Email-Deferred

- Recovery uses **secure, time-limited, single-use token** (`Request reset → Secure mechanism → Time-limited token → Set new password → Invalidate token`). Do not send passwords, do not store raw reset secrets, do not return reset token in API response.
- **Email delivery is Group R deferred:** contract supports secure recovery, but if V1 launches before email, recovery may be operationally unavailable — explicitly documented, not weakened with insecure `send reset password in response`. Actual email sending deferred.
- Enumeration protection: recovery response is generic `"Request received."` rather than `"This email does not exist."`; existence not revealed.

### 17.9 Verification — Email Verified Attribute, Not Client-Authoritative

- `verified email → account attribute/state`; `verification process → secure time-limited mechanism`. Do not accept client-submitted `{"email_verified":true}` as authoritative.
- Whether verification is required **before checkout or full account use** is deferred; if required, it is enforced server-side, not arbitrary frontend decision. Email sending (and thus full verification) deferred to Group R. **Phone/SMS OTP verification** is **not** an authentication requirement by default; phone remains important for orders/delivery but verification deferred unless business requires.

### 17.10 Role Identity & Exposure — Canonical Roles, Not Permissions

- Canonical V1 roles are **CLOSED** `CUSTOMER`/`STAFF`/`ADMIN` (always `UPPER_SNAKE_CASE`); arbitrary client-supplied roles rejected.
- Client may legitimately receive its own `role` (e.g., profile `{"role":"CUSTOMER"}`) for UX routing, but **must not** receive `database permissions`, `internal policy names`, `security config`. Role ≠ permission — backend evaluates `role + resource + action + ownership + state` per Phase 1.18; `STAFF` does not imply universal permission.

### 17.11 Cross-Client & Catalog Boundaries — Public Catalog vs Protected Commerce

- **Public (anonymous):** `products`, `categories`, `search`, `product details`, `prices`, `availability`, `public content`, plus `Made-to-order Request` and `General Enquiry` (with `User=none` + contact, or associated with User when authenticated) — no authentication required, and authentication must **not** gate SSR catalog pages (`/products`, `/products/{slug}`, `/categories/{slug}`) for SEO.
- **Protected:** `cart interaction` per finalized cart policy, `checkout` (authentication **REQUIRED**), `own orders/details/tracking`, `own requests/enquiries` history, `own notifications`, `profile eligible fields`. Backend enforces checkout boundary — anonymous checkout **MUST** be rejected with canonical `AUTHENTICATION_REQUIRED` 401 (`CHECKOUT_REQUIRES_AUTHENTICATION` is a legacy alias with identical 401 status and semantics — prefer `AUTHENTICATION_REQUIRED`; both remain CLOSED in registry `§15.15` for compatibility, one code path for clients).
- Admin/customer site share same backend identity: `Next.js customer site → Laravel auth ← Admin Next.js ← Flutter`; role/authorization gates after identity.

### 17.12 Authentication Requirements & Boundaries — What Each Actor May Not Do

**Customer ownership invariant:** `authenticated User → resource belongs to user → access allowed`. Staff does **not** become owner by operating.

Staff **must not** (unless later explicitly approved Admin-only security policy): `disable product browsing for a customer`, `block legitimate checkout`, `change customer's role`, `change customer's password`, `lock customer account`, `impersonate customer`, `access Customer password/auth secrets`, `restrict customer browsing/ordering via can_order switch`.

Admin **must not** casually `modify historical purchase facts`, `payment confirmation`, `order history` or `bypass every business invariant`; such corrections require explicit controlled policy and audit. Admin customer-account ops (review, disable compromised account, force credential reset, revoke sessions) are **security/admin operations**, not ordinary staff operations, and must be auditable (`Who approved staff? When? What changed?`).

Account deletion is **not** hard-delete; historical `orders/payments/requests/enquiries/notifications` must remain meaningful — evaluated as privacy/lifecycle operation later.

Anonymous→Authenticated transition is supported (`anonymous visitor → register/login → authenticated commerce`) without losing public browsing context; **anonymous cart MUST move onto the authenticated customer account on login/register — backend merges guest cart onto user cart preserving ownership (per `docs/domain/business-rules.md §3 #4`, backend is authority). Only conflict handling for duplicate carts/items (e.g., same product in both carts, quantity merge strategy, max limits) is deferred to implementation**; session merge beyond cart ownership is also deferred.

### 17.13 Error & Response Integration — Uses Common Contracts

All authentication failures use **common error envelope** (`api-contract.md §15`) — no separate `auth_success`/`login_result`/`token_response` envelope. Examples: `AUTHENTICATION_REQUIRED` (401, not 403 for unauthenticated), `INVALID_CREDENTIALS`, `SESSION_EXPIRED`, `FORBIDDEN` (canonical; legacy `NOT_AUTHENTICATED`/`RESOURCE_NOT_OWNED` aliases per §15.15). Authenticated endpoints use common response conventions (`data`/`meta`) — auth payload may be specialized but not a new envelope. Errors must not leak existence details beyond generic patterns (see §17.14).

### 17.14 Security Requirements — Credentials, Enumeration, Rate Limit, Threats

- **Password storage:** never plaintext, only **secure one-way hash** appropriate to Laravel version; never return `password`, `password_hash`, `reset_token`, `session_token`, `refresh_secret`, private keys in API responses. Password change is **authenticated secure workflow** (not `PATCH /me {password}`), and is security-sensitive.
- **Enumeration protection:** login and password-recovery responses avoid distinguishing `email exists` vs `not exists` unless explicitly justified; recovery is generic `"Request received."`.
- **Brute-force/ abuse:** rate limiting, failed-attempt protection, abuse detection on auth endpoints is **identified as later backend requirement**, not implemented here.
- **Data minimization:** registration stores only genuine need (`name`, `email`, `phone`, `password`); not `full address`, `identity document`, unnecessary demographic.
- **Event logging / audit:** later implementation considers logging `login success/failure`, `logout`, `password change/reset`, `staff approval`, `role change`, `session revocation` without logging passwords/tokens; staff approval remains auditable (`who/when/what`).
- **Threat model to address later (identified, not solved):** `credential theft`, `credential stuffing`, `brute-force login`, `session theft`, `token leakage`, `account enumeration`, `privilege escalation`, `role tampering`, `session fixation`, `password-reset abuse`, `cross-account access`, `staff/admin impersonation`.
- **Priorities:** Customer → simple registration/login + ownership + cross-platform access, low friction but not at security cost; Staff → anti-sharing/role escalation; Admin → MFA, session visibility, forced logout, shorter lifetimes, audit logging (evaluated later).

### 17.15 Versioning & Deferred Implementation

- Authentication behavior is **Version 1 API contract**; breaking changes (`changing credential semantics`, `login response shape`, `auth requirements`, `removing flow`) follow `api-versioning-strategy.md`.
- **Deferred (not implemented in this phase):** Laravel/Sanctum, User model/migrations, hashing, password reset/email verification/MFA, login controllers/middleware/policies, Next.js/Flutter login screens, token/session storage, staff-admin UI, exact session durations, email delivery (Group R).

### 17.16 Cross-References

See `api-conventions.md §18` for reusable authentication conventions, `api-resources.md §12` for conceptual `User`/`Authentication`/`Session` resources (no endpoints), `docs/domain/business-rules.md §17` for business invariants, `decisions.md ADR/AUTH-001..007`.

## 18. Authorization Contract — Role + Resource + Action + Ownership + State (Phase 1.18)

> **Authority note:** This section plus `api-conventions.md §19` and `api-resources.md §13` is the normative authorization contract for `v1`. `docs/domain/business-rules.md §18` governs business meaning; `decisions.md ADR/AUTHZ-*` records choices. Authentication (Phase 1.17, §17) answers *Who is the caller?* — this section answers *What may they do, to which resource, under which conditions, in which business state?* No Laravel Policies/Gates/middleware, role tables, or UI are implemented in this phase — architecture only. Roles are **CLOSED** `CUSTOMER`/`STAFF`/`ADMIN`; adding `MANAGER` etc. requires explicit approval. Payment authorization remains compatible with Group H.

### 18.1 Core Principle — Deny by Default, Least Privilege, Multi-Factor Decision

**Allow only if:**

```
authenticated
AND authorized actor (role)
AND authorized resource
AND authorized action
AND ownership/context valid
AND business-state valid
```

Failure at any stage denies. **Deny by default** for all protected resources — `allow by default then blacklist` is prohibited. Least privilege must not create friction for normal customer flows (`browse`, `search`, `add to cart`, `checkout`, `view own orders`, `cancel eligible own order`, `submit requests`) — security protects, not obstructs.

Authorization follows:

```
AUTHENTICATED IDENTITY + ROLE + RESOURCE + ACTION + OWNERSHIP + BUSINESS STATE + CONTEXT
```

Do not authorize on `role` alone (`CUSTOMER + Order` ≠ any Order).

### 18.2 Actors — Customer Broad Freedom, Staff Operational, Admin Highest

- **CUSTOMER:** `Broad customer commerce capability + own-resource control - administrative capability`. May `browse/search/view products/categories`, `manage cart`, `checkout`, `place orders`, `view/track/cancel own eligible orders`, `submit/view own requests/enquiries where supported`, `manage own profile/notifications` (see §18.4). Horizontal/vertical escalation to another customer or to STAFF operations is denied.
- **STAFF:** `Normal ecommerce operations + order/request/enquiry handling + approved inventory/catalog operations - customer-account control - role administration`. Operationally `receive/process orders`, `perform authorized status actions (ACCEPT/PROCESS/READY_FOR_PICKUP/SHIP/DELIVER) only if state permits`, `manage approved inventory/catalog ops`, `handle operational notifications`. No `change customer passwords/roles/disable accounts/impersonate/view credentials`. No `STAFF → block_customer` generic permission; no arbitrary restriction of browsing/ordering; no transfer of `Order/Request/Enquiry → another customer` unless explicit admin workflow.
- **ADMIN:** `Staff management + administrative operations + high-level configuration + authorized customer/account administration`. `approve/manage staff`, `manage operational users`, `authorized catalog/inventory/orders/requests/enquiries`, `system settings`, `authorized customer-account admin (review, manage security state, revoke sessions)` where approved. Not `bypass everything`: still obeys `business invariants`, `auditability`, `data integrity`, `security boundaries` — no silent rewrite of `historical order price`, `payment confirmation`, `status history` without controlled correction.

**Hierarchy (permission, not ownership):**

```
CUSTOMER → customer-owned permissions
   ↓
STAFF → operational permissions
   ↓
ADMIN → administrative permissions
```

No automatic inheritance `ADMIN bypasses every policy`; capabilities are explicit.

### 18.3 Ownership — Customer Owns Account, Ownership ≠ Operation

- **Customer ownership:** authenticated principal `==` resource owner is required for owner-based resources (`User → Orders`, `User → Notifications`, `User → Cart`, `User → Requests`, `User → Enquiries`). Never accept `{"user_id":"another-customer"}` as proof; `user_id=123` knowledge is not a token.
- **Staff operation vs ownership:** `Staff → Order` is operational relationship, not `owns Order`. Customer remains owner. Staff see only operationally necessary fields (`customer name`, `contact phone`, `delivery address`, `order items`, `fulfillment info`) — not `password`, `auth tokens`, `unrelated private enquiries/history`.
- **Admin ownership:** Admin has broader access but still purpose-bound and auditable; no unrestricted `Login as Customer` without secure impersonation design (explicit permission, audit, session context, restricted sensitive ops, secure exit — deferred unless required).

### 18.4 Public Resources — Explicitly Public, Not Exception

`products → public read`, `categories → public read` (search, product details, `search`, `view product details`) remain **PUBLIC** — no customer account required. Do not place auth around public catalog reads. This is intentional classification, not a global exception to deny-by-default. Public catalog may use CDN caching; private (`orders`, `profile`, `notifications`, `requests`, `enquiries`, `cart`) must not be publicly cached and must use private `Cache-Control` later.

### 18.5 Customer Permissions — Ownership-Based, Limited Catalogue

Customers do not need a huge permission matrix; ownership policies suffice:

```
customer.catalog.read
customer.cart.manage_own
customer.orders.read_own / track_own / cancel_own (eligible)
customer.requests.manage_own
customer.enquiries.manage_own
customer.profile.manage_own
customer.notifications.read_own
```

- `READ own`, `TRACK own`, `CANCEL eligible own` (requires `owner AND eligible status AND 20-minute window AND backend state check` — see §18.9), not `UPDATE arbitrary fields`/`DELETE`/`CHANGE status`/`CHANGE price` via `{"status":"DELIVERED"}`.
- `user_id`/`order_id` swapping, `role=ADMIN` mass-assignment, URL/query `?user_id=another` must fail.

### 18.6 Staff Permissions — Operational Only, Per-Resource/Action

Initial operational set (not final, explicit per-operation):

```
orders.view_operational
orders.accept          [PAID → ACCEPTED]
orders.process         [ACCEPTED → PROCESSING]
orders.ready_for_pickup [PROCESSING → READY_FOR_PICKUP] (pickup)
orders.ship            [PROCESSING → SHIPPED] (delivery)
orders.deliver         [SHIPPED → DELIVERED] (delivery)
orders.complete        [DELIVERED → COMPLETED (delivery) or READY_FOR_PICKUP → COMPLETED (pickup)] — controlled completion

products.view
products.manage [if approved]   // catalog CRUD, image/variant management
inventory.view
inventory.manage [if approved]   // stock-quantity adjustments
requests.view / requests.manage
enquiries.view / enquiries.manage
notifications.view_operational
```

- `view_operational` ≠ `manage`; `orders.ship` requires `ship` permission **and** `Order = PROCESSING` state (see §18.9). `orders.accept` requires `accept` permission **and** `Order = PAID` (or `PENDING_PAYMENT → PAID` via payment, then `PAID → ACCEPTED` via accept); `orders.ready_for_pickup` requires `ready_for_pickup` permission **and** `Order = PROCESSING`. `orders.process` does **not** implicitly cover `ACCEPT` or `READY_FOR_PICKUP` — each transition needs its own explicit permission + state precondition. `orders.deliver` requires `deliver` permission **and** `Order = SHIPPED`; `orders.complete` requires `complete` permission **and** `Order = DELIVERED` (delivery) or `Order = READY_FOR_PICKUP` (pickup) — completing the lifecycle explicitly (see §19.12 state history). Staff with operational access still needs valid state; staff cannot `approve Staff`, `grant Admin`, or `access passwords`.
- Inventory operations are `approved inventory operations` only, not `unrestricted DB modification`; adjustments auditable later.

### 18.7 Admin Permissions — Highest but Explicit

Potentially:

```
staff.approve
staff.manage
users.manage_authorized
products.manage
inventory.manage
orders.manage
requests.manage
enquiries.manage
system.manage
```

No `*` wildcard as sole model; every sensitive capability remains auditable and explicit. Admin cannot silently violate invariants.

### 18.8 Role Assignment & Staff Approval — Server-Controlled, Admin-Only

- `CUSTOMER → cannot assign role`; `STAFF → cannot assign role`; `ADMIN → authorized role management` only.
- Client payloads cannot change roles; mass-assignment `role` is rejected (authz + validation layers).
- **Staff approval:** `Staff candidate → ADMIN → Approve → Active staff access` — only authenticated `ADMIN` with `staff.approve` + valid target + audit record. Staff cannot approve themselves; customers cannot approve staff.

### 18.9 State-Aware Authorization — Ownership + State + Time

State is authoritative (see §14):

- `STAFF → ship order` authorized only when `Order = PROCESSING` (delivery workflow, valid target, operational access).
- `CUSTOMER → cancel own order` requires `authenticated + owns order + cancellable state + 20-minute window + backend current-state check`.
- Authorization evaluated at operation time, not cached assumption. For critical operations `authorize + validate state + perform operation` must be atomic so state cannot change between validation and commit (concurrency/transaction concern later).

### 18.10 Anonymous Operations — Limited, Input-Validated, Attachment-Private

- Anonymous may `submit request` / `submit enquiry` via public endpoint + input validation + later anti-abuse. `User = none` + required contact.
- Anonymous **must not** `GET /enquiries/{id}` unrestricted, read private enquiries, or fetch attachments via predictable public paths. `Request → Attachment` inherits parent authorization; if cannot access parent, cannot access attachment.
- `Authenticated: Enquiry → owner`; `Anonymous: Enquiry → controlled anonymous access model` (secure access mechanism later, no weak bearer-ID).
- Customer notifications are owner-based; `GET /notifications/{another}` by ID must fail. Cart access bound to authenticated customer — cannot change `cart.owner` via `user_id`.

### 18.11 Field-Level Exposure — Authorization Before Serialization

Before serialization, select representation by actor:

| Data | Customer | Staff | Admin |
|---|---|---:|---|
| Product price | Yes | Yes | Yes |
| Public stock availability | Yes | Yes | Yes |
| Internal `reserved_quantity` | No | According to permission | Yes |
| Customer phone on order | Own | Operational | Authorized |
| Customer password | No | No | No |
| Payment provider secret | No | No | No |
| Internal staff note | No | Authorized | Authorized |
| Historical order price | Own | Operational | Authorized |
| Staff role | No | Limited/own | Authorized |

Never fetch `all fields` then rely on frontend to hide; API exposes only permitted representation. Query `?user_id=` cannot expand scope; pagination/search operate over **authorized dataset** (`Customer A /me/orders?page=2` paginates `Customer A` orders, not all then filtered); search results are authz-filtered.

### 18.12 Staff/Admin Acting as Customers — No Silent Mixing

Staff/Admin accounts are **not** automatically customer accounts. `Staff are not automatically treated as customer users merely because they possess an account` (`*` in matrix). If staff need to purchase personally, treat as separate business/user-account policy, not silent operational/customer identity mix. `Admin → arbitrary ownership of customer orders` is prohibited.

### 18.13 Denial Behavior — 401 vs 403 vs 404

Use Phase 1.16 error contract (`§15`):

- `not authenticated` → `401 AUTHENTICATION_REQUIRED` (never 403)
- `authenticated but not permitted` → `403 FORBIDDEN` (or `404 RESOURCE_NOT_FOUND` where hiding existence is safer — e.g., private ownership enumeration)
- Endpoint-specific choice between `403` vs `404` is documented later; do not leak existence via divergent errors.

### 18.14 Privilege Escalation & Testing Requirements

Protect against: `role parameter tampering`, `user ID swapping`, `order ID swapping`, `horizontal escalation (A→B)`, `vertical escalation (Customer→Staff→Admin)`, `URL/query manipulation`, `mass assignment`, `ID enumeration`, `stale authorization`.

Later automated tests must cover:

- **Horizontal:** `Customer A → Customer B order/notifications/request` must fail (object-level authz).
- **Vertical:** `Customer → staff operation`, `Staff → admin operation` must fail (function-level).
- **Role tampering:** `client sends role=ADMIN` must fail.
- **Ownership tampering:** `client changes user_id` must fail.
- **State bypass:** `client forces order status` must fail.
- **Staff:** `approve themselves`, `grant Admin`, `access passwords`, `modify customer roles`, `block ordering` must fail.
- **Admin:** `approve staff`, `manage permissions` succeeds but cannot bypass `data integrity`, `audit`, `payment verification`, `historical semantics` without explicit correction workflow.
- **Anonymous:** can `browse/search/view/submit request/enquiry` but cannot `checkout`, `access private orders/profile/notifications`, `perform staff/admin` actions.
- **Cache safety:** `Customer A → Order A` authorized must not leak via cached response to `Customer B`; private responses have private `Cache-Control`.

### 18.15 Permission Model — RBAC + Ownership + State, No Role Explosion

- **Baseline:** `RBAC + resource ownership policies + business-state authorization`. Prefer centralized policies (`OrderPolicy: view/cancel/process/ship`, `ProductPolicy: view/manage`) over scattered `if ($user->role === 'admin')`.
- **Bad:** `CUSTOMER/STAFF/SENIOR_STAFF/ORDER_STAFF/INVENTORY_STAFF/DELIVERY_STAFF/MANAGER/SUPERVISOR/ADMIN/SUPERADMIN` without justification. Start with `CUSTOMER/STAFF/ADMIN` (CLOSED) and use explicit permissions for operational differences. Smallest useful permission unit:

```
products.view / products.manage
inventory.view / inventory.manage
orders.view_operational / orders.process / orders.ship / orders.deliver
requests.view / requests.manage
enquiries.view / enquiries.manage
staff.approve / staff.manage
```

Customer permissions remain ownership-based (see §18.5); Staff/Admin permissions are explicit sets (see §18.6/18.7).

- **Separation of duties:** `Staff → operational processing`; `Admin → staff approval` — one role cannot request and approve own privilege.
- **Least privilege review:** each permission must be `necessary`, `sufficient`, `not too broad/narrow`, `not dangerous/duplicated`; favor least privilege without destroying operational usability.

### 18.16 Authorization-Aware Operations & Audit

- **Queries/search/pagination:** already covered (see §18.11). Do not allow `?user_id=another` to expand scope.
- **Caching/CDN:** public CDN for public catalog; private resources never publicly cached; private `Cache-Control` later.
- **Background jobs/system services:** do not reuse Admin credential; use explicit service authorization (`SYSTEM` not a user role) for `payment callback`, `notification dispatch`, `inventory cleanup`, `order timeout` (payment webhook auth is Group H).
- **Time-sensitive:** `20-minute cancellation`, `valid order state` — evaluated at operation time.
- **Transactions/idempotency:** `authorize + validate state + perform` must be atomic; idempotency does not bypass authorization — every retry remains authorized.
- **Audit:** privileged actions log `actor`, `action`, `resource`, `target`, `timestamp`, `result` (no secrets). Customer: `password change`, `session revocation`, `account recovery`; Staff: `order accepted/shipped`, `inventory adjusted`, `request status changed`; Admin: `staff approval`, `role change`, `account security change`, `critical inventory adjustment`.

### 18.17 Versioning & Deferred Implementation

Authorization is **Version 1 API contract** — role/action changes follow `api-versioning-strategy.md`. CLOSED roles remain `CUSTOMER`/`STAFF`/`ADMIN`.
**Deferred (not implemented):** Laravel Policies/Gates/middleware, Spatie, role/permission tables/migrations, authorization controllers, admin/customer UI, Flutter authz, token middleware, impersonation, MFA, payment authorization.

### 18.18 Cross-References

See `api-conventions.md §19` for reusable authorization conventions, `api-resources.md §13` for per-resource `who may read/create/update/delete/act + ownership + sensitive fields`, `docs/domain/business-rules.md §18` for authorization business rules, `decisions.md ADR/AUTHZ-*`.

## 19. Version 1 Endpoint Inventory — Concrete Catalogue (Phase 1.19 — Current `PROPOSED`, Target `APPROVED` after Phase 1.21 Review)

> **Authority note:** This section is the **authoritative Version 1 endpoint inventory** — stable IDs, versioned paths, actors, auth/authz, purpose, and contract references. It answers *What exists? Who uses it? What does it do?* Implementation (routes/controllers/middleware/FormRequests) remains deferred. All paths use `/api/v1` per Phase 1.8; roles are CLOSED `CUSTOMER`/`STAFF`/`ADMIN`; reuse global conventions (query Phase 1.11, pagination Phase 1.12, response Phase 1.13, input Phase 1.14, validation Phase 1.15, errors Phase 1.16, auth Phase 1.17, authz Phase 1.18). Payment/webhook endpoints are placeholders owned by Group H (see §19.12). **Current status (accepted):** `APPROVED` for `ORD-001..014` (Phase 1.23), `REQ-001..007` (Phase 1.25), `ENQ-001..007` (Phase 1.26), `NOT-001/002` (Phase 1.27) per their canonical `§24`/`§26`/`§27`/`§28` contracts; `PROPOSED` for remaining V1 endpoints (`CAT-*`, `AUTH-*`, `USER-*`, `CART-*`, `CHK-001`, `INV-*`, `ADM-*`, etc.) and `PROPOSED*` for `PAY-001/002`/`WEBHOOK-001` Group H placeholders; **Target status:** `APPROVED` for remaining `PROPOSED` after review. Master table `Status` column reflects **current** `APPROVED`/`PROPOSED`/`PROPOSED*` as marked per row (see §19.15).

### 19.1 Master Endpoint Table (Stable IDs, Do Not Recycle)

| ID | Method | Path | Domain | Actors | Auth | Authorization | Purpose | Status |
|---|---|---|---|---|---|---|---|
| `CAT-001` | GET | `/api/v1/products` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read | List public products (search/filter/paginate) | PROPOSED |
| `CAT-002` | GET | `/api/v1/products/{product}` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read | Product detail (public-safe) | PROPOSED |
| `CAT-003` | GET | `/api/v1/categories` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read | List categories | PROPOSED |
| `CAT-004` | GET | `/api/v1/categories/{category}` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read | Category detail | PROPOSED |
| `CAT-005` | GET | `/api/v1/products/{product}/variants` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read (variant summary/public fields) | List product variants (independent retrieval when detail summary insufficient) | PROPOSED |
| `CAT-006` | GET | `/api/v1/products/{product}/variants/{variant}` | Catalog | Anonymous, Customer, Staff, Admin | No | **PUBLIC** read | Variant detail | PROPOSED |
| `AUTH-001` | POST | `/api/v1/auth/register` | Identity | Anonymous | No | **PUBLIC** (rate-limited) | Register `CUSTOMER` account | PROPOSED |
| `AUTH-002` | POST | `/api/v1/auth/login` | Identity | Anonymous, Customer, Staff, Admin | No | **PUBLIC** (rate-limited) | Login (shared identity) | PROPOSED |
| `AUTH-003` | POST | `/api/v1/auth/logout` | Identity | Customer, Staff, Admin | Yes | `AUTHENTICATED` self | Logout (invalidate server session/credential) | PROPOSED |
| `AUTH-004` | POST | `/api/v1/auth/password/forgot` | Identity | Anonymous, Customer | No | **PUBLIC** (rate-limited, enumeration-safe) | Request password reset (generic response) | PROPOSED |
| `AUTH-005` | POST | `/api/v1/auth/password/reset` | Identity | Anonymous | No | **PUBLIC** (single-use token) | Reset password with time-limited token | PROPOSED |
| `AUTH-006` | POST | `/api/v1/email/verify/resend` | Identity | Customer | Yes | `AUTHENTICATED_OWNER` own | Resend verification (Group R deferred delivery) | PROPOSED |
| `AUTH-007` | POST | `/api/v1/email/verify` | Identity | Customer | Yes* | `AUTHENTICATED_OWNER` own | Verify email via secure token (`*` token may be unauth query) | PROPOSED |
| `USER-001` | GET | `/api/v1/me` | Identity | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own | Get own profile (`User`) | PROPOSED |
| `USER-002` | PATCH | `/api/v1/me` | Identity | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own (`name`/`phone` only) | Update own profile (allow-list) | PROPOSED |
| `USER-003` | POST | `/api/v1/me/password` | Identity | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own (secure workflow) | Change own password (not `PATCH /me {password}`) | PROPOSED |
| `CART-001` | GET | `/api/v1/me/cart` | Cart | Customer + Anonymous (guest) | Yes for Customer, guest via `X-Guest-Cart-Id` / `guest_cart_id` cookie | `AUTHENTICATED_OWNER` own cart or `GUEST` holder via guest token (backend authority) | Get own/guest cart (guest cart identified server-side) | PROPOSED |
| `CART-002` | POST | `/api/v1/me/cart/items` | Cart | Customer + Anonymous (guest) | Yes for Customer, guest via `X-Guest-Cart-Id` | `AUTHENTICATED_OWNER` own cart or `GUEST` | Add item (`product_id`,`variant_id`,`quantity`) — guest cart supported | PROPOSED |
| `CART-003` | PATCH | `/api/v1/me/cart/items/{item}` | Cart | Customer + Anonymous (guest) | Yes for Customer, guest via `X-Guest-Cart-Id` | `AUTHENTICATED_OWNER` own cart or `GUEST` | Update item quantity (guest supported) | PROPOSED |
| `CART-004` | DELETE | `/api/v1/me/cart/items/{item}` | Cart | Customer + Anonymous (guest) | Yes for Customer, guest via `X-Guest-Cart-Id` | `AUTHENTICATED_OWNER` own cart or `GUEST` | Remove cart item (guest supported) | PROPOSED |
| `CART-005` | POST | `/api/v1/me/cart/merge` | Cart | Customer | Yes | `AUTHENTICATED_OWNER` own cart (merges guest) | **Merge guest cart onto authenticated cart** — backend merges `X-Guest-Cart-Id` guest cart into user cart on demand (also performed automatically on `AUTH-002` login) | PROPOSED |
| `CHK-001` | POST | `/api/v1/checkout` | Checkout | Customer | Yes | `AUTHENTICATED` customer, own cart, state/cart valid | Checkout → order creation (fulfillment+address) | PROPOSED |
| `ORD-001` | GET | `/api/v1/me/orders` | Order | Customer | Yes | `AUTHENTICATED_OWNER` own orders (authorized dataset pagination) | List own orders | **APPROVED** (Phase 1.23) |
| `ORD-002` | GET | `/api/v1/me/orders/{order}` | Order | Customer | Yes | `AUTHENTICATED_OWNER` owns order (404 masked) | Get own order detail | **APPROVED** (Phase 1.23) |
| `ORD-003` | GET | `/api/v1/me/orders/{order}/tracking` | Order | Customer | Yes | `AUTHENTICATED_OWNER` owns order | Order tracking (current state + history milestones) | **APPROVED** (Phase 1.23) |
| `ORD-004` | POST | `/api/v1/me/orders/{order}/cancel` | Order | Customer | Yes | `AUTHENTICATED_OWNER` owns + `cancel_own` + `eligible state + 20-min window` + backend state | Cancel own eligible order | **APPROVED** (Phase 1.23) |
| `ORD-005` | GET | `/api/v1/orders` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.view_operational` | List operational orders (staff view) | **APPROVED** (Phase 1.23) |
| `ORD-006` | GET | `/api/v1/orders/{order}` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.view_operational` (operational fields) | Get operational order detail | **APPROVED** (Phase 1.23) |
| `ORD-007` | POST | `/api/v1/orders/{order}/accept` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.accept` + `Order=PAID` + `delivery_fee FINALIZED` | Accept order `PAID→ACCEPTED` | **APPROVED** (Phase 1.23) |
| `ORD-008` | POST | `/api/v1/orders/{order}/process` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.process` + `Order=ACCEPTED` | Process order `ACCEPTED→PROCESSING` | **APPROVED** (Phase 1.23) |
| `ORD-009` | POST | `/api/v1/orders/{order}/ready-for-pickup` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.ready_for_pickup` + `Order=PROCESSING` (pickup fulfillment) | Ready for pickup `PROCESSING→READY_FOR_PICKUP` | **APPROVED** (Phase 1.23) |
| `ORD-010` | POST | `/api/v1/orders/{order}/ship` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.ship` + `Order=PROCESSING` (delivery) | Ship order `PROCESSING→SHIPPED` | **APPROVED** (Phase 1.23) |
| `ORD-011` | POST | `/api/v1/orders/{order}/deliver` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.deliver` + `Order=SHIPPED` | Deliver order `SHIPPED→DELIVERED` (delivery); completion via `ORD-013` `orders.complete` | **APPROVED** (Phase 1.23) |
| `ORD-012` | GET | `/api/v1/orders/{order}/tracking` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.view_operational` | Operational tracking (staff view) | **APPROVED** (Phase 1.23) |
| `ORD-013` | POST | `/api/v1/orders/{order}/complete` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.complete` + `Order=DELIVERED` (delivery) or `Order=READY_FOR_PICKUP` (pickup) | Complete order `DELIVERED→COMPLETED` (delivery) or `READY_FOR_PICKUP→COMPLETED` (pickup) — controlled completion semantics | **APPROVED** (Phase 1.23) |
| `ORD-014` | POST | `/api/v1/orders/{order}/delivery-fee` | Order | Staff, Admin | Yes | `OPERATIONAL` `orders.set_delivery_fee` + `PENDING_PAYMENT` + `DELIVERY` + `PENDING` | Finalize delivery fee `PENDING→FINALIZED` before payment (Model B gate) | **APPROVED** (Phase 1.23) |
| `REQ-001` | POST | `/api/v1/requests` | Request | Anonymous, Customer | No / Yes | **PUBLIC** submit (validated + anti-abuse later) | Submit made-to-order request (anonymous allowed) | APPROVED |
| `REQ-002` | GET | `/api/v1/me/requests` | Request | Customer | Yes | `AUTHENTICATED_OWNER` own | List own requests | APPROVED |
| `REQ-003` | GET | `/api/v1/me/requests/{request}` | Request | Customer | Yes | `AUTHENTICATED_OWNER` owns request | Get own request detail | APPROVED |
| `REQ-004` | GET | `/api/v1/requests` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.view` | List operational requests | APPROVED |
| `REQ-005` | GET | `/api/v1/requests/{request}` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.view` | Get operational request | APPROVED |
| `REQ-006` | PATCH | `/api/v1/requests/{request}` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.manage` | Update operational request state (`request_status` CLOSED) | APPROVED |
| `REQ-007` | POST | `/api/v1/requests/{request}/attachments` | Request | Anonymous* (scoped), Customer, Staff, Admin | **Scoped** — server-issued upload token (from `REQ-001` response, single-use/time-limited) + parent ownership; predictable ID alone insufficient | Upload request attachment — **preferred: multipart on `REQ-001` creation**; separate `POST` only with scoped token (anonymous requires token), private to parent | APPROVED |
| `ENQ-001` | POST | `/api/v1/enquiries` | Enquiry | Anonymous, Customer | No / Yes | **PUBLIC** submit (validated + anti-abuse) | Submit general enquiry (anonymous allowed) | APPROVED |
| `ENQ-002` | GET | `/api/v1/me/enquiries` | Enquiry | Customer | Yes | `AUTHENTICATED_OWNER` own | List own enquiries | APPROVED |
| `ENQ-003` | GET | `/api/v1/me/enquiries/{enquiry}` | Enquiry | Customer | Yes | `AUTHENTICATED_OWNER` owns enquiry | Get own enquiry detail | APPROVED |
| `ENQ-004` | GET | `/api/v1/enquiries` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.view` | List operational enquiries | APPROVED |
| `ENQ-005` | GET | `/api/v1/enquiries/{enquiry}` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.view` | Get operational enquiry | APPROVED |
| `ENQ-006` | POST | `/api/v1/enquiries/{enquiry}/close` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.manage` | Close enquiry `OPEN→CLOSED` (reopen `CLOSED→OPEN` optional) | APPROVED |
| `ENQ-007` | POST | `/api/v1/enquiries/{enquiry}/attachments` | Enquiry | Anonymous* (scoped), Customer, Staff, Admin | **Scoped** — server-issued upload token (from `ENQ-001` response, single-use/time-limited) + parent ownership; same token model as `REQ-007` | Upload enquiry attachment — **preferred: multipart on `ENQ-001` creation**; separate `POST` only with scoped token, private to parent | APPROVED |
| `NOT-001` | GET | `/api/v1/me/notifications` | Notification | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own — **Customer**: own (customer notifications); **Staff**: `OPERATIONAL` own, recipient-scoped (operational notifications: new order/request/payment event); **Admin**: `ADMIN` limited own, recipient-scoped (administrative notifications as needed) | List own notifications (holder-scoped via `/me`, paginated) | APPROVED |
| `NOT-002` | PATCH | `/api/v1/me/notifications/{notification}` | Notification | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own (read/unread only) — **Customer**: own; **Staff**: `OPERATIONAL` own, recipient-scoped; **Admin**: `ADMIN` limited own, recipient-scoped | Mark notification read/unread (only `read`/`unread` mutable; content immutable) | APPROVED |
| `INV-001` | GET | `/api/v1/inventory` | Inventory | Staff, Admin | Yes | `OPERATIONAL` `inventory.view` | List inventory (operational) | PROPOSED |
| `INV-002` | GET | `/api/v1/inventory/{product}` | Inventory | Staff, Admin | Yes | `OPERATIONAL` `inventory.view` | Get product inventory detail | PROPOSED |
| `INV-003` | POST | `/api/v1/inventory/{product}/adjust` | Inventory | Staff, Admin | Yes | `OPERATIONAL` `inventory.manage` + auditable reason | Adjust inventory (explicit action, not `PATCH {quantity:999}`) | PROPOSED |
| `CAT-007` | POST | `/api/v1/products` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Create product (Staff only if approved) | PROPOSED |
| `CAT-008` | PATCH | `/api/v1/products/{product}` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Update product (Staff only if approved) | PROPOSED |
| `CAT-009` | POST | `/api/v1/products/{product}/images` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Manage product images (Staff only if approved) | PROPOSED |
| `CAT-010` | POST | `/api/v1/products/{product}/variants` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Manage variants (Staff only if approved) | PROPOSED |
| `CAT-011` | POST | `/api/v1/categories` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Create category (Staff only if approved) | PROPOSED |
| `CAT-012` | PATCH | `/api/v1/categories/{category}` | Catalog | Staff, Admin | Yes | `ADMINISTRATIVE` `products.manage` where approved | Update category (Staff only if approved) | PROPOSED |
| `ADM-001` | GET | `/api/v1/staff` | Staff | Admin | Yes | `ADMINISTRATIVE` `staff.manage` | List staff | PROPOSED |
| `ADM-002` | POST | `/api/v1/staff/invitations` | Staff | Admin | Yes | `ADMINISTRATIVE` `staff.approve` | Invite staff (admin creates/invites) | PROPOSED |
| `ADM-003` | POST | `/api/v1/staff/{staff}/approve` | Staff | Admin | Yes | `ADMINISTRATIVE` `staff.approve` + audit | Approve staff | PROPOSED |
| `ADM-004` | PATCH | `/api/v1/staff/{staff}` | Staff | Admin | Yes | `ADMINISTRATIVE` `staff.manage` | Update staff | PROPOSED |
| `ADM-005` | GET | `/api/v1/users` | User | Admin | Yes | `ADMINISTRATIVE` `users.manage_authorized` | List users (authorized admin) | PROPOSED |
| `ADM-006` | GET | `/api/v1/users/{user}` | User | Admin | Yes | `ADMINISTRATIVE` `users.manage_authorized` | Get user detail (authorized) | PROPOSED |
| `PAY-001` | POST | `/api/v1/payments` | Payment | Customer | Yes | `AUTHENTICATED_OWNER` own order | Initiate payment (generic placeholder, Group H) | PROPOSED* |
| `PAY-002` | GET | `/api/v1/payments/{payment}` | Payment | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own / `OPERATIONAL` / `ADMIN` limited | Get payment status (generic) | PROPOSED* |
| `WEBHOOK-001` | POST | `/api/v1/webhooks/payment/{provider}` | Payment | System/Webhook | Signature | `SYSTEM` service auth (Group H) | Payment provider callback (Group H) | PROPOSED* |

> `*` Payment/webhook endpoints are placeholders marked `PROPOSED*` with Owner `Phase Group H` — no provider selection, no detailed payloads (see §19.12). `ORD-001..014`, `REQ-001..007`, `ENQ-001..007`, `NOT-001/002` are **already `APPROVED`** as marked per row (`§24`/`§26`/`§27`/`§28`); all other endpoints remain **current `PROPOSED`**, target `APPROVED` for remaining after review (see authority note and §19.15).

### 19.2 Endpoint Detail Template & Per-Endpoint Contract Summary

Each endpoint above follows the standard record:

```
Endpoint ID / Name / Method / Path / Domain / Resource / Purpose / Actor / Authentication / Authorization / Request / Query / Response / Errors / Business Rules / Idempotency / State / Public-Private / Notes
```

Below are concise summaries for non-obvious endpoints (pagination/query/response/error conventions reuse globals; only specifics are listed):

**Catalog (Public, Embedded Availability):**
- `CAT-001` `GET /api/v1/products` — *Query:* `search`, `category` (`?category={id}` canonical; no separate `/categories/{category}/products` per §19.5), `product_type` CLOSED, `availability=available|unavailable`, `min_price/max_price` minor units string, `sort` allow-list + `sort_direction` + `id ASC` tie-breaker, `page`/`per_page` (1–100) per pagination `meta.pagination`. *Response:* `Product` collection `data[]` + `meta.pagination` (public-safe, no `reserved_quantity`). *Errors:* `INVALID_VALUE` (filter/sort/page), `RATE_LIMITED`. *Idempotency:* `SAFE` (read). *State:* none (public). Availability is **embedded** (`availability` + `stock_indicator`) in `Product`, not separate `/availability` endpoint (preferred embedding per §19.6).
- `CAT-002` `GET /api/v1/products/{product}` — *Response:* single `Product` + `images[]` + `variants` summary + `availability`/`stock_indicator`. *Errors:* `RESOURCE_NOT_FOUND`/`PRODUCT_NOT_FOUND` 404, `RATE_LIMITED`. *Public*.
- `CAT-003/004` Category collection/detail — *Response:* `Category` collection/detail; `GET /categories/{category}/products` is **REJECTED**; use `GET /products?category=` canonical.
- `CAT-005/006` Variants — *Purpose:* independent variant retrieval when product summary insufficient; not required if clients always use embedded variants. *Response:* `Variant` collection/detail.

**Authentication (Rate-Limited, No Role Tampering):**
- `AUTH-001` `POST /auth/register` — *Request:* `name`, `email`, `phone`, `password` (minimal, not `role`/`address book`); `role: ADMIN/STAFF` rejected (422/403). *Response:* `User` `data` (no `password_hash`/tokens). *Errors:* `MISSING_REQUIRED_FIELD`, `INVALID_VALUE`, `CONFLICT` (duplicate email), `RATE_LIMITED`. *Auth:* No. `STAFF` not via this endpoint (must be `ADM-002/003`).
- `AUTH-002` `POST /auth/login` — *Request:* `email`, `password`; *Response:* `data` with safe profile + session/credential per `§17.7` (httpOnly cookie for Web, token for Flutter, never long-lived JS secret). **Guest-cart merge (automatic on login):** on successful login, backend merges guest cart (identified by `X-Guest-Cart-Id` header or `guest_cart_id` cookie) onto the authenticated user cart — backend-authoritative, idempotent, preserves items; if guest cart is empty, no-op; if same product+variant exists in both, quantity is merged (strategy deferred to implementation); if guest cart conflicts exceed stock, backend resolves per `inventory rules §12`. Guest cart token is not a client ownership proof — it is an opaque backend-issued identifier. *Errors:* `INVALID_CREDENTIALS` 401 generic (no `email exists` distinction), `RATE_LIMITED`.
- `AUTH-003` `POST /auth/logout` — *Auth:* Yes, *Authz:* self; must be `POST` (not `GET`), invalidates server state. *Errors:* `AUTHENTICATION_REQUIRED` 401 if not authed.

**Customer (Owner-Based, Server-Calculated Totals) — Guest-Cart Handoff (Backend Authority):**
- `CART-001..004` — *Auth:* `AUTHENTICATED_OWNER` own cart **or `GUEST` via `X-Guest-Cart-Id` / `guest_cart_id` cookie** (backend-issued opaque guest token, `HttpOnly`, `Secure` where applicable; returned on first anonymous `CART-002` `201` via `Set-Cookie`/`X-Guest-Cart-Id` header); `user_id`/`cart.owner` tampering rejected; `product_id+variant_id+quantity` only (no `price`/`totals`/`inventory`); backend revalidates product/variant. **Backend guest-cart handoff:** anonymous cart identified server-side by guest token; on `AUTH-002` `POST /auth/login` (and explicitly via `CART-005` `POST /me/cart/merge`) backend **merges** guest cart items onto authenticated user cart (preserving ownership, backend authority per `business-rules.md §3 #4`; only duplicate-item conflict handling — same product in both carts, quantity merge strategy, max limits — is deferred). No client-supplied `guest_cart_id` ownership proof beyond token; guest token is opaque, not guessable. *Idempotency:* `CART-002` non-idempotent by default (consider later), `CART-003` idempotent `PATCH`, `CART-004` idempotent `DELETE`, `CART-005` `IDEMPOTENCY_REQUIRED` (merge is sensitive, replays prior merge result).
- `CHK-001` `POST /checkout` — *Auth:* `AUTHENTICATION_REQUIRED` canonical 401 for anonymous (`CHECKOUT_REQUIRES_AUTHENTICATION` is alias), *Authz:* own cart valid; `Request:` `fulfillment_type` + conditional `delivery_address` (when `DELIVERY`), no `totals`/`order_reference`/`user_id`; `Response:` `Order` + `Payment` placeholder `data` with server-calculated `subtotal/delivery_fee/total` and `order_reference`. *Errors:* `AUTHENTICATION_REQUIRED`, `CART_INVALID`, `PRODUCT_NOT_PURCHASABLE` (`MADE_TO_ORDER`), `INSUFFICIENT_STOCK`, `INVALID_FULFILLMENT`. *Idempotency:* `IDEMPOTENCY_REQUIRED` (duplicate same `Idempotency-Key` replays original `201`, no new order). *Concurrency:* inventory + order-creation critical.

**Order (Ownership vs Operational, State-Aware):**
- `ORD-001`/`002`/`003` — *Authz:* `AUTHENTICATED_OWNER` owns order; `GET /me/orders?user_id=another` must not expand; pagination over authorized dataset; `tracking` provides `current state + status history` milestones (not GPS). *Errors:* `RESOURCE_NOT_FOUND` 404 masked for not-owned (never `FORBIDDEN` leak), `AUTHENTICATION_REQUIRED`.
- `ORD-004` `POST /me/orders/{order}/cancel` — *Authz:* `owns + eligible state + 20-min window + backend state`; not `DELETE /orders/{order}`; *Idempotency:* `IDEMPOTENCY_REQUIRED` (cancel is sensitive).
- `ORD-005`/`006` operational — *Authz:* `orders.view_operational`; sees `customer phone/delivery address` operational but not `password`/tokens.
- `ORD-007..011` + `ORD-013` state actions — *Authz:* explicit per-action (`orders.accept` for `accept`, etc.; `process` does **not** cover `accept`/`ready_for_pickup`/`complete` per §18.6) **AND** valid state (`accept: PAID→ACCEPTED`, `process: ACCEPTED→PROCESSING`, `ready_for_pickup: PROCESSING→READY_FOR_PICKUP` (pickup), `ship: PROCESSING→SHIPPED` (delivery), `deliver: SHIPPED→DELIVERED`, `complete: DELIVERED→COMPLETED` or `READY_FOR_PICKUP→COMPLETED` via `ORD-013`); `PATCH {status}` rejected. *Idempotency:* state actions `IDEMPOTENCY_REQUIRED`; *Concurrency:* critical.

**Request/Enquiry (Anonymous Submit, Owner/Operational) — Attachments Private to Parent:**
- `REQ-001`/`ENQ-001` `POST /requests`/`/enquiries` — *Auth:* No/Yes (anonymous `User=none` + contact, or authed `→User`); no `user_id` required; attachments inherit parent authz, no predictable public paths. *Errors:* `INVALID_ENQUIRY` etc. with field paths.
- `REQ-002..006` / `ENQ-002..006` — *Authz:* `own` for customer (`me/requests`, `me/enquiries`), `requests.view/manage` / `enquiries.view/manage` for staff/admin; anonymous retrieval of `GET /requests/{id}` / `GET /enquiries/{id}` not automatically public (secure mechanism deferred; `REJECTED` if not designed).
- `REQ-007` / `ENQ-007` attachments — `POST /requests/{request}/attachments` (`REQ-007`) and `POST /enquiries/{enquiry}/attachments` (`ENQ-007`) — **Secure anonymous handling:** preferred is multipart upload inline with `REQ-001`/`ENQ-001` creation; separate `POST` requires a **scoped, server-issued upload capability** (single-use/time-limited token returned on creation, not the predictable request/enquiry ID alone). *Auth:* `Scoped` token + parent ownership (`Anonymous*` via token, `Customer` where parent `→User`, `OPERATIONAL` for staff); private to parent, no predictable public paths; same file validation (`size/type/signature`); `ENQ-007` mirrors `REQ-007`.

**Other:**
- `NOT-001/002` — holder-scoped via `/me`, `AUTHENTICATED_OWNER` own per actor: **Customer** own (customer notifications), **Staff** `OPERATIONAL` own recipient-scoped (operational: new order/request/payment event — not private customer-account info), **Admin** `ADMIN` limited own recipient-scoped (administrative as needed); content immutable, only `read`/`unread` mutable via `PATCH` (`NOT-002`).
- `INV-001..003` — `inventory.view` vs `inventory.manage` strictly split (catalog vs stock); `adjust` explicit action with `quantity/change + reason` (not `PATCH {quantity:999}`), auditable.
- `CAT-007..012` — `products.manage` (catalog CRUD + images/variants, not stock) vs `inventory.manage` (stock only).
- `ADM-001..006` — `staff.approve`/`staff.manage`/`users.manage_authorized` Admin-only, audited; `role` changes rejected from customer/staff; `block` customer not allowed via `STAFF` (only separate `ADMIN` restriction workflow if ever approved, per §18.12).
- `PAY-001/002`, `WEBHOOK-001` — generic placeholders, `Phase Group H` owner, no provider payloads, use global error envelope later.

### 19.3 Endpoint ID Stability, Lifecycle & Ownership

- IDs `CAT-xxx`, `AUTH-xxx`, `CART-xxx`, `CHK-xxx`, `ORD-xxx`, `PAY-xxx`, `REQ-xxx`, `ENQ-xxx`, `NOT-xxx`, `USER-xxx`, `INV-xxx`, `ADM-xxx`, `WEBHOOK-xxx` are stable; removed IDs remain retired, never recycled.
- Lifecycle for Phase 1.19: all V1 endpoints `PROPOSED` → `APPROVED` after Phase 1.21 review; future `DEPRECATED`/`RETIRED` per versioning. Removed `ID` remains retired.
- Dependencies: `AUTH-001 Register → AUTH-002 Login → CART-001… → CHK-001 → PAY-* → ORD-* Tracking`; `REQ-001 → REQ-007`; `ADM staff approval → ORD operational`. All workflows connected.

### 19.4 Public / Customer / Staff / Admin Summary (Actor-Oriented)

| Actor | Allowed Endpoint IDs (Summary) |
|---|---|
| **Anonymous** | `CAT-001..006` (catalog public), `AUTH-001`/`002`/`004`/`005` (register/login/recovery), `REQ-001`, `ENQ-001` (anonymous submit), `AUTH-003` requires auth so not anonymous |
| **Customer** | Catalog reads, `USER-001..003` own profile/password, `CART-001..004` own cart, `CHK-001` checkout, `ORD-001..004` own orders/tracking/cancel, `REQ-002/003`/`ENQ-002/003` own, `NOT-001/002` own (customer notifications, holder-scoped via `/me`), auth/security |
| **Staff** | Operational `ORD-005/006/007..011` + `ORD-013` + `ORD-012` tracking, `REQ-004..006`, `ENQ-004..006`, `INV-001/002` view (and `INV-003` if `inventory.manage` approved), `CAT-007..012` if `products.manage` approved, `NOT-001/002` `OPERATIONAL` own, recipient-scoped (operational notifications) |
| **Admin** | `ADM-001..006` staff/users manage, plus all operational + catalog/inventory management, `NOT-001/002` `ADMIN` limited own, recipient-scoped (administrative notifications as needed), `PAY-002` admin limited (view status only; `PAY-001` initiate is **Customer-only**, not Admin — see §19.1 master permission table), `WEBHOOK-001` system (Group H) |

Full matrix in §18 and `api-resources.md §13`; this summary is not replacement.

### 19.5 Catalog Decision — Category Filtering via Product Collection

**Decision (API-END-002):** Category filtering uses `GET /api/v1/products?category={category}` (canonical, `category` query-convention) rather than duplicate `GET /api/v1/categories/{category}/products`. Single canonical retrieval simplifies caching, filtering (`search`/`product_type`/`availability`/`price`/`sort` combined), and `meta.pagination`. `GET /categories/{category}/products` is **REJECTED** for V1. Recorded in `decisions.md ADR/API-END-002`.

### 19.6 Availability Embedding

Availability is **embedded** in `Product` (`availability: available|unavailable` + `stock_indicator: IN_STOCK|LOW_STOCK|MADE_TO_ORDER`) on `CAT-001/002`; no separate `GET /products/{product}/availability` endpoint in V1 (exposing internal inventory via separate endpoint not needed when embedding satisfies UI without leaking `reserved_quantity`). Inventory operational detail remains `INV-002` (staff/admin).

### 19.7 Product Images / Variants Need

- `CAT-005/006` variants: **Approved** for independent variant retrieval when product summary insufficient (full via `/products/{product}/variants/{variant}`).
- Images: `GET /products/{product}/images` is **REJECTED** for V1 because `CAT-002` already embeds `images[]` as `[{id,url,alt_text,sort_order,is_primary}]` and no independent image retrieval is required.

### 19.8 Query, Pagination, Response, Input Compatibility per Endpoint

- **Query:** `CAT-001` uses `search`, `category`, `product_type` CLOSED, `availability` `available|unavailable` lowercase, `min_price`/`max_price` minor-unit string, `sort` allow-list (`created_at`, `price`, `name` + `id ASC` tie-breaker), `sort_direction`; no `pageSize`/`sortBy` aliases (Phase 1.11). Named per Phase 1.11, validated per Phase 1.15.
- **Pagination:** `CAT-001`, `CAT-003` where applicable, `ORD-001`, `ORD-005`, `REQ-004`, `ENQ-004`, `NOT-001`, `ADM-001`, `INV-001` use `page`/`per_page` (1–100) + `meta.pagination` (total/last_page/has_next/has_previous) per Phase 1.12; `CAT-002`/`ORD-002` single resource not paginated.
- **Response:** every endpoint uses `{"data":…}` or `{"data":[], "meta":{"pagination":…}}` or `{"errors":…}` per Phase 1.13; no raw arrays or custom wrappers; `errors` per Phase 1.16 with `code`/`field`/`details` + `meta.request_id`.
- **Input:** `CART-002` only `product_id`/`variant_id`/`quantity` (no `price`/`totals`), `CHK-001` only `fulfillment_type` + conditional `delivery_address`, no `role`/`status`/`inventory authority`/`payment confirmation` as customer inputs per Phase 1.14.

### 19.9 Authentication/Authorization per Endpoint

- **Public:** `CAT-001..006` remain public (no auth) — `api-conventions.md §18.2`; anonymous `REQ-001`/`ENQ-001` + `AUTH-001/002/004/005` public with rate-limit enumeration safety.
- **Checkout/auth:** `CHK-001` requires `AUTHENTICATION_REQUIRED` canonical 401 for anonymous (`CHECKOUT_REQUIRES_AUTHENTICATION` alias) — backend enforces, not frontend.
- **Ownership vs operational vs admin:** per `api-contract.md §18` and `api-resources.md §13`; every protected endpoint states `owns resource?` / `operational access?` / `administrative access?`; e.g., `ORD-002` ownership, `ORD-010` operational `orders.ship` + `PROCESSING`, `ADM-003` administrative.
- **Anonymous retrieval not implied:** `REQ-001` anonymous creation does **not** imply `GET /requests/{request}` public retrieval (secure mechanism deferred, otherwise rejected).

### 19.10 Security, Idempotency, Concurrency per Endpoint

- **Security review:** `Anonymous → private data?` No (catalog only public). `Customer A → Customer B` (IDOR) blocked via object-level `owns` + 404 masking. `Customer→Staff/Admin` privilege escalation blocked (role tampering, `user_id` swapping). `Staff → block customer / change password / impersonate / transfer ownership` blocked. `PATCH {status}` rejected (controlled actions). `Staff over-permission` limited to per-permission (`products.manage` ≠ `inventory.manage`). `Admin overexposure` minimized (field-level). `Public inventory` safe (no `reserved_quantity`), `payment secrets` never, `attachments` private to parent.
- **Idempotency:** `SAFE`: `CAT-*` `GET`; `IDEMPOTENT`: `USER-002` `PATCH` (designed), `CART-003/004`, `NOT-002`; `IDEMPOTENCY_REQUIRED`: `CHK-001` checkout, `PAY-001` payment initiation, `ORD-004` cancel, `ORD-007..011` state actions, `INV-003` adjust; `NON_IDEMPOTENT` by default: `REQ-001`/`ENQ-001` submit, `CART-002` add (consider later).
- **Concurrency:** `CHK-001`, `INV-003`, `ORD-007..011` (status transitions), `PAY-001`/`WEBHOOK-001`, `ADM-003` staff approval — flagged as concurrency-sensitive (atomic validation + state + authz).
- **Rate-limit candidates:** `AUTH-001` register, `AUTH-002` login, `AUTH-004/005` recovery, `REQ-001`/`ENQ-001` anonymous submit, `CHK-001` checkout, `PAY-001` payment — to be implemented later.

### 19.11 Workflow Coverage & Completeness Gate

| Workflow | Required Endpoint IDs | Complete? |
|---|---|---|
| Anonymous browse | `CAT-001..004` | Yes |
| Customer registration/login | `AUTH-001..003` + `USER-001` | Yes |
| Customer shopping `→ cart → checkout` | `CART-001..004` → `CHK-001` → `PAY-001/002` | Yes (PAY generic placeholder) |
| Pickup order | `CHK-001` → `ORD-007` `ORD-008` → `ORD-009` → `ORD-013` `complete` → `ORD-003/012` tracking | Yes |
| Delivery order | `CHK-001` → `ORD-007` `ORD-008` → `ORD-010` → `ORD-011` → `ORD-013` `complete` | Yes |
| Order tracking | `ORD-003` (customer) / `ORD-012` (staff) — both read `order_status_history` after `ORD-013` completion | Yes |
| Customer cancellation | `ORD-004` (20-min + state) | Yes |
| Made-to-order request (+ attachment) | `REQ-001` → `REQ-007` → `REQ-002/003` (own) → `REQ-004..006` (staff) | Yes |
| General enquiry (+ attachment) | `ENQ-001` → `ENQ-002/003` → `ENQ-004..006` | Yes |
| Staff order processing | `ORD-005/006` → `ORD-007..011` + `ORD-013` + `NOT-001` operational | Yes |
| Admin staff approval | `ADM-001..004` | Yes |
| Product/inventory management | `CAT-007..012` + `INV-001..003` | Yes |

All endpoint dependencies `resource exists + relationship exists + actor exists + auth rule exists + authz rule exists + HTTP method + path naming + request/response/error conventions` are satisfied; any gap is flagged as incomplete (none for V1).

### 19.12 Payment & Webhook Placeholders (Group H Owner)

`PAY-001` (`POST /api/v1/payments`) initiate, `PAY-002` (`GET /api/v1/payments/{payment}`) status, `WEBHOOK-001` (`POST /api/v1/webhooks/payment/{provider}`) provider callback — **Owner: Phase Group H**. No provider selection, no detailed request/response beyond generic `amount {amount,currency}` + `payment_status` CLOSED, no webhook payload detail; they use global error contract (`EXTERNAL_SERVICE_ERROR` family) and require `signature/idempotency/event validation` later. Assignment to Group H prevents accidental Group G implementation.

### 19.13 Endpoint Count, MVP & Surface Discipline

- **Smallest coherent surface:** V1 list **current `PROPOSED` (target `APPROVED` after Phase 1.21)** is `69` total (`66` substantive + `3` Group H placeholders) — `66` substantive: catalog 12 [`CAT-001..006` public 6 + `CAT-007..012` management 6] + auth 7 [`AUTH-001..007`] + user 3 [`USER-001..003`] + cart 5 [`CART-001..005` inc. `CART-005` merge] + checkout 1 [`CHK-001`] + orders 13 [`ORD-001..013`] + requests 7 [`REQ-001..007`] + enquiries 7 [`ENQ-001..007` inc. `ENQ-007` scoped attachments] + notifications 2 [`NOT-001/002`] + inventory 3 [`INV-001..003`] + admin 6 [`ADM-001..006`]; plus `3` placeholders [`PAY-001/002`, `WEBHOOK-001`] (`PROPOSED*`, Group H) — master table Status reflects current `PROPOSED`/`PROPOSED*`. No `GET /my-orders` duplicate of `GET /me/orders`, no `/categories/{category}/products` duplicate, no `/products/{product}/images` separate, no `/products/{product}/availability` separate — canonical choices per §19.5-19.7. Guest-cart identifier is `X-Guest-Cart-Id` / `guest_cart_id` cookie (opaque, backend-issued) handled in `CART-001..004` anonymous support and merged via `AUTH-002`/`CART-005`.
- **MVP discipline:** Excludes `wishlist`, `reviews`, `coupons`, `saved addresses`, `loyalty`, `live driver tracking` unless explicitly approved.
- **Surface security:** each endpoint justified; attack/testing/documentation/authorization cost considered.

### 19.14 Naming, Method, Query, Pagination, Response, Input, Validation, Error, Auth, Authz Reviews — Consolidated

- **Naming (§97):** lowercase, plural resources, kebab-case where required (`ready-for-pickup` path `ready-for-pickup`), shallow nesting (`/products/{product}/variants` not deep), nouns + controlled actions (`/orders/{order}/cancel` via `POST`, not `PATCH {status}`), `/api/v1` prefix — checked.
- **Methods (§98):** `GET` read (`CAT-*`, `USER-001`, `ORD-001`), `POST` create/action (`AUTH-001`, `CART-002`, `CHK-001`, `ORD-007`), `PATCH` partial update (`USER-002`, `CART-003`, `CAT-008`), `DELETE` actual removal (`CART-004`) — not for cancellation.
- **Query (§99):** `page`, `per_page`, `search`, `category`, `product_type`, `availability`, `min_price`, `max_price`, `sort`, `sort_direction` only; no `pageSize`/`sortBy`.
- **Pagination (§100):** paginated where collections, not single resources, per contract `meta.pagination`.
- **Response (§101):** `data`/`meta` + `errors` per Phase 1.13; no raw arrays.
- **Input (§102):** no `totals`/`IDs`/`roles`/`statuses`/`inventory`/`payment confirmation` as customer input.
- **Validation (§103):** business/domain authoritative, not controller/frontend.
- **Errors (§104):** per Phase 1.16 `code`/`field`/`details` + `meta.request_id`, `401` vs `403` vs `404` masking respected.
- **Auth (§105):** `CAT-*` public, `REQ-001`/`ENQ-001` anonymous allowed, `CHK-001`/`ORD-001` authenticated, `ORD-005` staff, `ADM-003` admin.
- **Authz (§106):** `Who/Which resource/Which action/Which ownership/Which state` clear for every protected endpoint (ownership `me/orders`, operational `orders.ship`+`PROCESSING`, administrative `staff.approve`).
- **Security (§107-111):** IDOR, privilege escalation, role tampering, leakage, over-permission, inventory exposure, payment-secret exposure, attachment leakage, anonymous endpoint abuse/rate-limit all reviewed and safe.

### 19.15 Status & Lifecycle (Current vs Target)

**Current (Phase 1.19):** all V1 non-payment endpoints are `PROPOSED`; `PAY-001/002`/`WEBHOOK-001` are `PROPOSED*` (Group H placeholder).  
**Target after Phase 1.21 review:** all V1 endpoints become `APPROVED`. Status table above reflects **current** `PROPOSED`/`PROPOSED*`; any removed ID remains retired, never recycled. Future `DEPRECATED`/`RETIRED` per versioning.

## 20. Links to Conventions & Resources

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility, **input**, **validation** §16, **errors** §17, **authentication** §18, **authorization** §19, **endpoint inventory** §20, **catalog conventions** §21).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure, summary vs detail schemas §1–§2, and per-resource input + validation + **errors** §10–11, **authentication** §12, **authorization** §13, **endpoint mapping** §14).
- Domain: `docs/domain/business-rules.md` (business meaning, validation authority, **error ↔ rule mapping §14**, **authentication §17**, **authorization §18**, **endpoint workflows §19**).
- Decisions: `docs/decisions.md` (`ADR/API-END-*` endpoint inventory, `ADR/AUTH-*`, `ADR/AUTHZ-*`, `ADR/API-CAT-*` catalog decisions).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).

---

## 21. Catalog API Contract (Phase 1.20)

> **Authority:** Canonical domain API contract for the **Catalog** subsystem (`CAT-001..CAT-006` public read operations and `CAT-007..CAT-012` administrative management baseline). Consolidates `phases/phase-1.20.md`.
> **Core Principle:** Fast, public, predictable, SEO-compatible, cache-friendly, safe, and completely independent of customer authentication.

### 21.1 Catalog Endpoint Contract Matrix

| ID | Method | Path | Auth | Authorization | Pagination | Purpose | Caching |
|---|---|---|---|---|---|---|---|
| `CAT-001` | GET | `/api/v1/products` | No | Public read | Yes (`meta.pagination`) | List public products (search, filter, sort) | Public / Cacheable |
| `CAT-002` | GET | `/api/v1/products/{product}` | No | Public read | No | Product detail (public-safe representation) | Public / Cacheable |
| `CAT-003` | GET | `/api/v1/categories` | No | Public read | Yes (`meta.pagination`) | List public categories | Public / Cacheable |
| `CAT-004` | GET | `/api/v1/categories/{category}` | No | Public read | No | Category detail | Public / Cacheable |
| `CAT-005` | GET | `/api/v1/products/{product}/variants` | No | Public read | Optional | List variants belonging to product | Public / Cacheable |
| `CAT-006` | GET | `/api/v1/products/{product}/variants/{variant}` | No | Public read | No | Single variant detail | Public / Cacheable |
| `CAT-007` | POST | `/api/v1/products` | Yes | `products.manage` (Admin/Staff) | No | Create product | Non-cacheable |
| `CAT-008` | PATCH | `/api/v1/products/{product}` | Yes | `products.manage` (Admin/Staff) | No | Update product | Non-cacheable |
| `CAT-009` | POST | `/api/v1/products/{product}/images` | Yes | `products.manage` (Admin/Staff) | No | Add product image | Non-cacheable |
| `CAT-010` | POST | `/api/v1/products/{product}/variants` | Yes | `products.manage` (Admin/Staff) | No | Add product variant | Non-cacheable |
| `CAT-011` | POST | `/api/v1/categories` | Yes | `products.manage` (Admin/Staff) | No | Create category | Non-cacheable |
| `CAT-012` | PATCH | `/api/v1/categories/{category}` | Yes | `products.manage` (Admin/Staff) | No | Update category | Non-cacheable |

*Note on Rejected Duplicate Endpoints:*  
- `GET /api/v1/categories/{category}/products` is **REJECTED** in favor of canonical `GET /api/v1/products?category={category}` (ADR/API-END-002).  
- `GET /api/v1/products/{product}/images` is **REJECTED** as an independent read endpoint because `CAT-002` embeds ordered image objects (`images[]`).

---

### 21.2 Product Collection Contract (`CAT-001`)

- **HTTP Method & Path:** `GET /api/v1/products`
- **Purpose:** Public product discovery, catalog browsing, filtering, search, and category listing.
- **Actor:** Public Anonymous, Customer, Staff, Admin.
- **Authentication & Authorization:** None required; `PUBLIC_READ`.
- **Request Body:** None allowed (GET requests do not accept request bodies).
- **Supported Query Parameters:**

| Parameter | Type | Validation / Rules | Description |
|---|---|---|---|
| `search` | string | Optional. Max 100 characters. Trimmed, case-insensitive. Empty string ignored. | Searches product name, description, SKU, and variant attributes. Untrusted input. |
| `category` | string | Optional. Resolves against Category `slug` (canonical) or Category `id`. | Filters products belonging to the specified category. |
| `product_type` | enum CLOSED | Optional. Must be `IN_STOCK` or `MADE_TO_ORDER`. | Filters products by type. Invalid value produces `INVALID_VALUE` (422). |
| `availability` | enum CLOSED | Optional. Must be lowercase `available` or `unavailable`. | Filters by public availability. Display buckets (`IN_STOCK`, `LOW_STOCK`) rejected. |
| `min_price` | integer / numeric string | Optional. Integer minor units (e.g. `50000000` = 500,000 TZS). Must be >= 0. | Minimum price threshold. |
| `max_price` | integer / numeric string | Optional. Integer minor units. Must be >= `min_price`. | Maximum price threshold. `min_price > max_price` produces `INVALID_VALUE` (422). |
| `sort` | enum allow-list | Optional. Allowed values: `created_at`, `price`, `name`. | Primary sort attribute. Unrecognized attributes produce `INVALID_VALUE` (422). |
| `sort_direction` | enum CLOSED | Optional. Allowed values: `asc`, `desc` (case-insensitive, default `asc` for `name`/`price`, `desc` for `created_at`). | Sort direction. |
| `page` | integer | Optional. Default `1`. Must be >= 1. | Current page number. |
| `per_page` | integer | Optional. Default `20`. Must be between `1` and `100`. | Items per page. |

- **Query Execution Pipeline:** `search` → `filter` (`category`, `product_type`, `availability`, price range) → `sort` → `paginate`.
- **Deterministic Sort & Tie-Breaking:** Every query resolves with primary sort (`sort` + `sort_direction`) followed by `id ASC` as a unique tie-breaker. Default sort when omitted is catalog display order (`created_at DESC, id ASC`).
- **Response Format (Product Summary Collection):**

```json
{
  "data": [
    {
      "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "name": "Modern 3-Seater Fabric Sofa",
      "slug": "modern-3-seater-fabric-sofa",
      "product_type": "IN_STOCK",
      "price": {
        "amount": 125000000,
        "currency": "TZS"
      },
      "category": {
        "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9",
        "name": "Living Room",
        "slug": "living-room"
      },
      "primary_image": {
        "id": "img_01h8x9a0b1c2d3e4f5g6h7j8",
        "url": "https://cdn.furniture.co.tz/products/sofa-front.webp",
        "alt_text": "Modern 3-Seater Fabric Sofa in Charcoal Grey"
      },
      "availability": "available",
      "stock_indicator": "IN_STOCK"
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 48,
      "last_page": 3,
      "has_next": true,
      "has_previous": false
    }
  }
}
```

- **Collection Error Scenarios:**
  - `INVALID_VALUE` (422): invalid `product_type`, invalid `availability`, invalid `sort`, `min_price > max_price`.
  - `INVALID_FORMAT` (422): non-numeric `page`, `per_page`, `min_price`, `max_price`.
  - `RATE_LIMITED` (429): excessive requests exceeding public rate limit.

---

### 21.3 Product Detail Contract (`CAT-002`)

- **HTTP Method & Path:** `GET /api/v1/products/{product}`
- **Parameter `{product}`:** Public identifier — accepts either Product `slug` (canonical for Next.js SEO routing) or Product `id` (stable machine identity).
- **Purpose:** Full product presentation, product landing pages, SSR SEO metadata, Add-to-Cart payload preparation, and Made-to-Order request initiation.
- **Actor:** Public Anonymous, Customer, Staff, Admin.
- **Authentication & Authorization:** None required; `PUBLIC_READ`.
- **Response Format (Product Detail):**

```json
{
  "data": {
    "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
    "name": "Modern 3-Seater Fabric Sofa",
    "slug": "modern-3-seater-fabric-sofa",
    "description": "Premium handcrafted living room sofa featuring high-density foam cushions and solid hardwood frame.",
    "product_type": "IN_STOCK",
    "price": {
      "amount": 125000000,
      "currency": "TZS"
    },
    "category": {
      "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9",
      "name": "Living Room",
      "slug": "living-room",
      "description": "Sofas, coffee tables, and accent seating for modern homes."
    },
    "images": [
      {
        "id": "img_01h8x9a0b1c2d3e4f5g6h7j8",
        "url": "https://cdn.furniture.co.tz/products/sofa-front.webp",
        "alt_text": "Modern 3-Seater Fabric Sofa in Charcoal Grey - Front View",
        "sort_order": 1,
        "is_primary": true
      },
      {
        "id": "img_01h8x9a0b1c2d3e4f5g6h7j9",
        "url": "https://cdn.furniture.co.tz/products/sofa-angle.webp",
        "alt_text": "Modern 3-Seater Fabric Sofa in Charcoal Grey - Angle View",
        "sort_order": 2,
        "is_primary": false
      }
    ],
    "variants": [
      {
        "id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
        "sku": "SOFA-MOD-3S-GRY",
        "name": "Charcoal Grey",
        "price": {
          "amount": 125000000,
          "currency": "TZS"
        },
        "availability": "available",
        "stock_indicator": "IN_STOCK"
      },
      {
        "id": "var_01h8x9k1m2n3p4q5r6s7t8v0",
        "sku": "SOFA-MOD-3S-BEI",
        "name": "Warm Beige",
        "price": {
          "amount": 128000000,
          "currency": "TZS"
        },
        "availability": "available",
        "stock_indicator": "LOW_STOCK"
      }
    ],
    "availability": "available",
    "stock_indicator": "IN_STOCK",
    "created_at": "2026-08-30T10:00:00Z",
    "updated_at": "2026-08-31T14:30:00Z"
  }
}
```

- **Error Scenarios:**
  - `RESOURCE_NOT_FOUND` (404): Product not found, unpublished, draft, or inactive (`is_active: false`). Response masks internal existence (`PRODUCT_EXISTS_BUT_IS_HIDDEN` is prohibited).

---

### 21.4 Category Collection & Detail Contracts (`CAT-003`, `CAT-004`)

#### Category Collection (`CAT-003`)
- **Path:** `GET /api/v1/categories`
- **Purpose:** Public category navigation, menu generation, category landing page.
- **Response Format (Category Summary Collection):**

```json
{
  "data": [
    {
      "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9",
      "name": "Living Room",
      "slug": "living-room",
      "image": {
        "url": "https://cdn.furniture.co.tz/categories/living-room.webp"
      }
    },
    {
      "id": "cat_01h8x8a1b2c3d4e5f6g7h8k0",
      "name": "Dining Room",
      "slug": "dining-room",
      "image": {
        "url": "https://cdn.furniture.co.tz/categories/dining-room.webp"
      }
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 6,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

#### Category Detail (`CAT-004`)
- **Path:** `GET /api/v1/categories/{category}` (`{category}` is slug or id)
- **Response Format (Category Detail):**

```json
{
  "data": {
    "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9",
    "name": "Living Room",
    "slug": "living-room",
    "description": "Sofas, coffee tables, and accent seating for modern homes.",
    "image": {
      "url": "https://cdn.furniture.co.tz/categories/living-room.webp"
    },
    "created_at": "2026-08-20T08:00:00Z"
  }
}
```
- **Error:** `RESOURCE_NOT_FOUND` (404) if category does not exist or is inactive.

---

### 21.5 Product Variants Contracts (`CAT-005`, `CAT-006`)

- **Collection (`CAT-005`):** `GET /api/v1/products/{product}/variants` — lists public variants belonging strictly to the specified parent product (returns array of standalone variant items).
- **Detail (`CAT-006`):** `GET /api/v1/products/{product}/variants/{variant}` — retrieves a single variant. Validates that `{variant}` belongs to `{product}`; mismatch returns `RESOURCE_NOT_FOUND` (404).
- **Standalone Variant Object Structure (`CAT-006` / `CAT-005`):**

```json
{
  "id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
  "sku": "SOFA-MOD-3S-GRY",
  "name": "Charcoal Grey",
  "price": {
    "amount": 125000000,
    "currency": "TZS"
  },
  "availability": "available",
  "stock_indicator": "IN_STOCK",
  "created_at": "2026-08-30T10:00:00Z",
  "updated_at": "2026-08-31T12:00:00Z"
}
```

- **Embedded Variant Summary Structure (Embedded in `CAT-002` Product Detail):**
  Embedded variants in `CAT-002` omit `product_id` (implicit in parent product) and timestamps (`created_at`, `updated_at`) to keep the payload clean:
  `[{ "id": "var_...", "sku": "SOFA-MOD-3S-GRY", "name": "Charcoal Grey", "price": { "amount": 125000000, "currency": "TZS" }, "availability": "available", "stock_indicator": "IN_STOCK" }]`

---

### 21.6 Product Type Semantics & UI Action Mapping

| `product_type` | Business Meaning | Primary Customer Action | Commercial Rule |
|---|---|---|---|
| `IN_STOCK` | Standard physical inventory item available for direct purchase. | **Add to Cart** (`CART-002`) | Can be added to cart and purchased through checkout if available. |
| `MADE_TO_ORDER` | Custom furniture manufactured on request. | **Request Furniture** (`REQ-001`) | **Cannot be added to cart or checked out directly.** Price is informational starting estimate. Add-to-cart attempt returns `PRODUCT_NOT_PURCHASABLE` (422). |

---

### 21.7 Public Availability vs Internal Inventory Separation

- **Public Availability (`availability`):** Coarse boolean-like enum (`available` | `unavailable`). Used in queries (`?availability=available`) and responses.
- **Public Stock Indicator (`stock_indicator`):** Informational display bucket (`IN_STOCK` | `LOW_STOCK` | `MADE_TO_ORDER`). For customer UI badges only; **not filterable** via query parameters.
- **Informational Nature:** Catalog availability is an informational point-in-time snapshot. It does not guarantee inventory reservation. Final authoritative validation occurs in a database transaction during Checkout (`CHK-001`).
- **Internal Protection:** Internal stock fields (`physical_quantity`, `reserved_quantity`, `supplier_id`, internal warehouse notes, stock adjustments) are **strictly prohibited** from serialization on public catalog responses.

---

### 21.8 Summary vs Full Detail Representations

| Entity | Summary Representation (Collections / Lists / Embedded) | Full Detail Representation (Detail Endpoints) |
|---|---|---|
| **Product** | `id`, `name`, `slug`, `product_type`, `price`, `category` (summary), `primary_image`, `availability`, `stock_indicator` | Summary fields + `description`, `images[]` (full ordered gallery), `variants[]` (embedded summary list), `created_at`, `updated_at` |
| **Category** | `id`, `name`, `slug`, `image` | `id`, `name`, `slug`, `description`, `image`, `created_at` |
| **Variant** | `id`, `sku`, `name`, `price`, `availability`, `stock_indicator` (embedded in `CAT-002`) | `id`, `product_id`, `sku`, `name`, `price`, `availability`, `stock_indicator`, `created_at`, `updated_at` (`CAT-005`, `CAT-006`) |


---

### 21.9 SEO, Slugs, Next.js & Flutter Compatibility

- **Public Slug Identification:** `slug` is a URL-safe, unique kebab-case identifier (e.g. `modern-3-seater-sofa`) used by Next.js for crawlable, SEO-friendly page URLs (`/products/modern-3-seater-sofa`).
- **Machine Identifier:** `id` is a stable, opaque string identifier used in machine operations (cart, checkout, orders, relations).
- **Dual Resolution for Products and Categories:** Detail endpoints for Product (`CAT-002`) and Category (`CAT-004`) resolve transparently whether given a `slug` or an `id`. Product Variants (`CAT-005`, `CAT-006`) resolve strictly by Variant `id` (`var_...`) under parent `{product}` (which itself resolves by product slug or id).
- **SSR & OpenGraph Readiness:** Product Detail response contains all necessary fields (`name`, `description`, `price`, primary image selected from `images[]` where `is_primary: true` [or `primary_image.url` on `CAT-001` summary], `availability`) for Next.js to generate OpenGraph tags, Twitter cards, canonical tags, and Schema.org `Product` JSON-LD structured data.
- **Flutter Compatibility:** Flutter mobile client consumes the exact same JSON contract and models without requiring mobile-specific endpoints.

---

### 21.10 Caching, Cache Safety, and Response Determinism

- **Cache Safety:** Public Catalog responses contain zero customer session data, zero cookies, zero customer identifiers, and zero user-specific state.
- **Cache-Control Candidates:** Public GET responses can safely be cached via HTTP `Cache-Control`, CDN edge nodes, and Next.js ISR (Incremental Static Regeneration).
- **Determinism:** Identical query parameters against identical catalog state yield identical JSON byte output.
- **Invalidation Triggers:** Administrative catalog mutations (`CAT-007..CAT-012`, `INV-003`) trigger CDN/application cache invalidation for the affected product/category paths.

---

### 21.11 Catalog Security & Invariant Checklist

| Threat / Risk | Defense Mechanism | Invariant / Rule |
|---|---|---|
| Anonymous access to draft/hidden products | Query automatically scopes `is_active: true` and published state; hidden items return `RESOURCE_NOT_FOUND` 404 | API-SEC-001 |
| Internal inventory quantity leakage | `reserved_quantity` and warehouse units never mapped in public resource serializers | INV-PUB-001 |
| Staff notes or cost prices leakage | Field-level serializer filtering strictly limits output to public whitelist | API-SEC-002 |
| Cross-product variant injection | `CAT-006` and Cart operations validate `variant.product_id === product.id` | VAR-OWN-001 |
| SQL injection via search or sort | Parameterized database queries; strict server-side allow-list for `sort` fields | SEC-INP-001 |
| Enum tampering (`product_type`, `availability`) | Closed enum validation; invalid values immediately trigger `INVALID_VALUE` 422 | API-VAL-003 |
| Unbounded response payload / DoS | Enforced pagination limits (`per_page` max 100); bounded gallery/variant lists | API-RES-001 |

---

## 22. Cart API Contract (Phase 1.21)

> **Authority:** Canonical domain API contract for the **Cart** subsystem (`CART-001..CART-005`). Consolidates `phases/phase-1.21.md`.
> **Core Principle:** The Cart contains a customer's current purchase intent. It belongs strictly to the authenticated customer (with opaque guest-session handoff), persists across devices/sessions, does not reserve inventory, is not a pricing authority, and revalidates all commercial invariants at checkout.

### 22.1 Cart Endpoint Contract Matrix

| ID | Method | Path | Auth | Authorization | Pagination | Purpose | Caching |
|---|---|---|---|---|---|---|---|
| `CART-001` | GET | `/api/v1/me/cart` | Required (or Guest Token) | `AUTHENTICATED_OWNER` / `GUEST` | No | Get current active cart | Private / No-Store |
| `CART-002` | POST | `/api/v1/me/cart/items` | Required (or Guest Token) | `AUTHENTICATED_OWNER` / `GUEST` | No | Add purchasable product/variant to cart | Non-cacheable |
| `CART-003` | PATCH | `/api/v1/me/cart/items/{item}` | Required (or Guest Token) | `AUTHENTICATED_OWNER` / `GUEST` | No | Update cart item quantity | Non-cacheable |
| `CART-004` | DELETE | `/api/v1/me/cart/items/{item}` | Required (or Guest Token) | `AUTHENTICATED_OWNER` / `GUEST` | No | Remove item from cart | Non-cacheable |
| `CART-005` | POST | `/api/v1/me/cart/merge` | Required | `AUTHENTICATED_OWNER` | No | Explicitly merge guest cart into user cart | Non-cacheable |

*Guest-Cart Token Transport (per `api-conventions.md §22.6`):* The server uses one of two mutually exclusive paths depending on client type. **Browser (Next.js):** token issued as `HttpOnly; Secure; SameSite=Strict` cookie (`guest_cart_id`) only — no `X-Guest-Cart-Id` response header is emitted. **Non-browser (Flutter):** token issued in the `X-Guest-Cart-Id` response header only (no `Set-Cookie`) and treated as a bearer secret stored in secure device storage. The token is permanently retired server-side upon merge (`AUTH-002` login or `CART-005`). Retired tokens are rejected and never recycled.

---

### 22.2 Endpoint CART-001 — Get Current Cart

- **HTTP Method & Path:** `GET /api/v1/me/cart`
- **Purpose:** Retrieve the customer's active shopping cart, itemized products, quantities, display prices, and subtotal.
- **Actor:** Authenticated Customer (or anonymous guest with `X-Guest-Cart-Id`).
- **Authentication:** Required for customer (`AUTHENTICATED_OWNER`); guest requires valid opaque guest token.
- **Authorization:** Customer sees only own cart. IDOR is impossible via self-context `/me/cart`.
- **Query Parameters:** None. Active cart items are not paginated (small, bounded collection per ADR/API-CART-008).
- **Empty Cart Behavior:** If the cart contains no items, the API returns a valid empty active Cart representation (`items: []`, `items_count: 0`, `subtotal: {amount: 0, currency: "TZS"}`), never 404 or `null`.
- **Response Format (Active Cart with Items):**

```json
{
  "data": {
    "id": "cart_01h8y0a1b2c3d4e5f6g7h8j9",
    "items_count": 2,
    "items": [
      {
        "id": "item_01h8y0b2c3d4e5f6g7h8j9k0",
        "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
        "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
        "product": {
          "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
          "name": "Modern 3-Seater Fabric Sofa",
          "slug": "modern-3-seater-fabric-sofa",
          "product_type": "IN_STOCK",
          "price": {
            "amount": 125000000,
            "currency": "TZS"
          },
          "primary_image": {
            "id": "img_01h8x9a0b1c2d3e4f5g6h7j8",
            "url": "https://cdn.furniture.co.tz/products/sofa-front.webp",
            "alt_text": "Modern 3-Seater Fabric Sofa in Charcoal Grey"
          }
        },
        "variant": {
          "id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
          "sku": "SOFA-MOD-3S-GRY",
          "name": "Charcoal Grey",
          "price": {
            "amount": 125000000,
            "currency": "TZS"
          }
        },
        "quantity": 1,
        "unit_price": {
          "amount": 125000000,
          "currency": "TZS"
        },
        "line_total": {
          "amount": 125000000,
          "currency": "TZS"
        },
        "availability": "available",
        "stock_indicator": "IN_STOCK",
        "is_purchasable": true,
        "created_at": "2026-09-01T08:00:00Z",
        "updated_at": "2026-09-01T08:00:00Z"
      },
      {
        "id": "item_01h8y0b2c3d4e5f6g7h8j9k1",
        "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t2",
        "variant_id": null,
        "product": {
          "id": "prod_01h8x9j2m4k5n6p7q8r9s0t2",
          "name": "Solid Oak Coffee Table",
          "slug": "solid-oak-coffee-table",
          "product_type": "IN_STOCK",
          "price": {
            "amount": 45000000,
            "currency": "TZS"
          },
          "primary_image": {
            "id": "img_01h8x9a0b1c2d3e4f5g6h7k2",
            "url": "https://cdn.furniture.co.tz/products/coffee-table.webp",
            "alt_text": "Solid Oak Coffee Table"
          }
        },
        "variant": null,
        "quantity": 1,
        "unit_price": {
          "amount": 45000000,
          "currency": "TZS"
        },
        "line_total": {
          "amount": 45000000,
          "currency": "TZS"
        },
        "availability": "available",
        "stock_indicator": "IN_STOCK",
        "is_purchasable": true,
        "created_at": "2026-09-01T08:15:00Z",
        "updated_at": "2026-09-01T08:15:00Z"
      }
    ],
    "subtotal": {
      "amount": 170000000,
      "currency": "TZS"
    },
    "updated_at": "2026-09-01T08:15:00Z"
  }
}
```

---

### 22.3 Endpoint CART-002 — Add Cart Item

- **HTTP Method & Path:** `POST /api/v1/me/cart/items`
- **Purpose:** Add a purchasable physical inventory product (and specific variant if applicable) to the cart.
- **Request Body (JSON):**

```json
{
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
  "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
  "quantity": 2
}
```

- **Input Validation Rules:**
  - `product_id`: required string. Must reference an existing, published, active (`is_active: true && is_published: true`) product. An active draft (`is_published: false`) is rejected with `PRODUCT_NOT_PURCHASABLE` (422).
  - `product_type`: must be `IN_STOCK`. If `product_type === MADE_TO_ORDER`, the backend strictly rejects with `PRODUCT_NOT_PURCHASABLE` (422).
  - `variant_id`: conditional. If the product defines variants, `variant_id` is required, must reference an active variant, and must strictly belong to `product_id` (`VAR-OWN-001`). If the product has no variants, `variant_id` must be `null` or omitted. Mismatched variant returns `INVALID_PRODUCT_VARIANT` (422).
  - `quantity`: required integer, strict minimum `1`, maximum `100` per item (ADR/API-CART-008). Non-integers, strings, negative values, and `0` are rejected with `INVALID_VALUE` (422).
  - Client-supplied financial and inventory fields (`price`, `subtotal`, `total`, `discount`, `stock`) are strictly rejected with `INVALID_VALUE` (422). Silently ignoring them is inconsistent with the global unknown-field rejection rule and would hide client payload errors.
- **Item Aggregation & Duplicate Handling:** If the caller adds an item whose `(product_id, variant_id)` already exists in the cart, the server merges the items by incrementing the existing line's quantity: `new_quantity = existing_quantity + added_quantity` (clamped to max `100`).
- **Inventory Check Semantics:** The backend performs an informational availability check on add/update using these mutually exclusive predicates: if the product fails the purchasability flags (`is_active: false` or `is_published: false`), returns `PRODUCT_UNAVAILABLE` (422); if the product is purchasable but `available_quantity < requested_quantity`, returns `INSUFFICIENT_STOCK` (422). Successful addition **does not place an inventory hold or lock** (ADR/API-CART-002).
- **Response:** Returns the full updated Cart Object (`201 Created` on new line, `200 OK` on quantity merge). For guest callers: browser clients receive a `Set-Cookie: guest_cart_id=<token>; HttpOnly; Secure; SameSite=Strict` header only; Flutter clients receive an `X-Guest-Cart-Id: <token>` response header only. The server never issues both simultaneously (see `api-conventions.md §22.6`).

---

### 22.4 Endpoint CART-003 — Update Cart Item Quantity

- **HTTP Method & Path:** `PATCH /api/v1/me/cart/items/{item}`
- **Purpose:** Adjust the quantity of an existing cart item.
- **Parameter `{item}`:** Opaque Cart Item ID (`item_...`).
- **Request Body (JSON):**

```json
{
  "quantity": 3
}
```

- **Validation Rules:**
  - `{item}` must exist in the caller's active cart. Attempting to update another customer's item returns `CART_ITEM_NOT_FOUND` (404).
  - Only `quantity` is mutable via this endpoint. Changing `product_id` or `variant_id` requires deleting the old item and adding the new item.
  - `quantity` must be an integer between `1` and `100`. Setting `quantity: 0` is rejected with `INVALID_VALUE` (422); removal must use `DELETE` (`CART-004`).
- **Response:** Returns the updated Cart Object (`200 OK`).

---

### 22.5 Endpoint CART-004 — Remove Cart Item

- **HTTP Method & Path:** `DELETE /api/v1/me/cart/items/{item}`
- **Purpose:** Remove an item from the customer's active cart.
- **Parameter `{item}`:** Opaque Cart Item ID (`item_...`).
- **Validation Rules:**
  - `{item}` must belong to the caller's active cart.
  - Removal is an intent deletion; it does not require inventory or catalog stock validation.
- **Response:** `204 No Content` on successful deletion. If the item is already absent, idempotent `204 No Content` is returned.

---

### 22.6 Endpoint CART-005 — Merge Guest Cart

- **HTTP Method & Path:** `POST /api/v1/me/cart/merge`
- **Purpose:** Explicitly merge an anonymous guest cart into the authenticated customer's account cart.
- **Authentication:** Required (`AUTHENTICATED_OWNER`).
- **Guest Token Input:** The server reads the guest token from the transport channel appropriate to the client type: `guest_cart_id` cookie (browser path) or `X-Guest-Cart-Id` request header (Flutter path). A client must not send the token value in a JSON body field, as this would expose the bearer credential in request logs.
- **Merge Semantics:**
  - Matches items by `(product_id, variant_id)`: sums quantities up to the `100` unit limit.
  - Copies unique items into the customer's cart.
  - Deactivates/clears the guest cart record so it cannot be re-merged or accessed.
  - Note: This merge is also executed automatically upon customer login (`AUTH-002`).
- **Response:** Returns the merged updated Cart Object (`200 OK`).

---

### 22.7 Cart & Cart Item Representations

#### Cart Object Structure

| Field | Type | Exposure | Nullable | Description |
|---|---|---|---|---|
| `id` | string | CUSTOMER / GUEST | no | Opaque Cart identifier (`cart_...`) |
| `items_count` | integer | CUSTOMER / GUEST | no | Total number of distinct item lines in the cart |
| `items` | `CartItem[]` | CUSTOMER / GUEST | no | Array of item lines in deterministic insertion order |
| `subtotal` | `{amount: int, currency: "TZS"}` | CUSTOMER / GUEST | no | Informational sum of line totals (minor units) |
| `updated_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Timestamp of last cart mutation |

#### Cart Item Object Structure

| Field | Type | Exposure | Nullable | Description |
|---|---|---|---|---|
| `id` | string | CUSTOMER / GUEST | no | Unique Cart Item line identifier (`item_...`) |
| `product_id` | string | CUSTOMER / GUEST | no | Stable Product identifier |
| `variant_id` | string | CUSTOMER / GUEST | yes | Variant identifier (`null` if product has no variants) |
| `product` | `ProductSummary` | CUSTOMER / GUEST | no | Embedded Product summary (id, name, slug, product_type, price, primary_image) |
| `variant` | `VariantSummary` | CUSTOMER / GUEST | yes | Embedded Variant summary (id, sku, name, price); `null` if no variant |
| `quantity` | integer | CUSTOMER / GUEST | no | Selected item quantity (1..100) |
| `unit_price` | `{amount: int, currency: "TZS"}` | CUSTOMER / GUEST | no | Current catalog unit price (minor units) |
| `line_total` | `{amount: int, currency: "TZS"}` | CUSTOMER / GUEST | no | `unit_price.amount * quantity` (minor units) |
| `availability` | enum `"available"\|"unavailable"` | CUSTOMER / GUEST | no | Live availability status |
| `stock_indicator` | enum `"IN_STOCK"\|"LOW_STOCK"\|"MADE_TO_ORDER"` | CUSTOMER / GUEST | no | Current inventory badge bucket |
| `is_purchasable` | boolean | CUSTOMER / GUEST | no | `true` if item is active and in stock; `false` if stale/unavailable |
| `created_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Line item creation timestamp |
| `updated_at` | ISO8601 UTC | CUSTOMER / GUEST | no | Line item last updated timestamp |

---

### 22.8 Stale Cart Items & State Signaling

- **Preservation Policy (No Silent Deletion):** If a product or variant is deactivated (`is_active: false`), unpublished, or depletes stock while in a customer's cart, the item is **not** silently deleted from the cart.
- **Signaling:** The item is returned with `availability: "unavailable"` and `is_purchasable: false`.
- **Client UX:** Next.js and Flutter UI display an explicit warning badge ("This item is no longer available") and prompt the customer to remove or adjust it.
- **Checkout Enforcement:** Checkout (`CHK-001`) strictly rejects any cart containing `is_purchasable === false` items with `CART_INVALID` (422).

---

### 22.9 Pricing, Inventory & Authority Decoupling

```text
+-------------------+      +-------------------+      +--------------------------+
|      CATALOG      | ---> |       CART        | ---> |         CHECKOUT         |
| Live Master Data  |      | Customer Intent   |      | Transactional Authority  |
| - unit price      |      | - display price   |      | - locks inventory        |
| - stock counts    |      | - no stock hold   |      | - authoritative total    |
| - active status   |      | - informative sum |      | - creates order snapshot |
+-------------------+      +-------------------+      +--------------------------+
```

1. **Non-Reservation:** Items in a cart do **not** reduce `available_quantity` or increase `reserved_quantity`. Inventory reservation occurs strictly during the atomic Checkout transaction (`CHK-001`).
2. **Pricing Integrity:** Cart `unit_price`, `line_total`, and `subtotal` are informational display numbers computed server-side. The client cannot supply prices or totals.
3. **Checkout Recalculation:** All prices, stock levels, delivery fees, and line totals are authoritatively recomputed from database state at checkout.

---

### 22.10 Security, Isolation & IDOR Protection

- **Ownership Isolation:** All cart access is routed through the `/api/v1/me/cart` self-context. The caller's authenticated identity (or validated guest token) is the sole authority for cart lookup.
- **IDOR Defense:** Item endpoints (`PATCH /me/cart/items/{item}`, `DELETE /me/cart/items/{item}`) verify `item.cart_id === caller_cart.id`. If a user attempts to update or delete an item ID belonging to another user, the server returns `CART_ITEM_NOT_FOUND` (404 masking).
- **Staff & Admin Isolation:** Staff and Admin roles have **zero** cart modification endpoints. Staff operations begin strictly at the Order stage (`ORD-005..013`).
- **Cache Isolation:** Cart endpoints return `Cache-Control: private, no-cache, no-store, must-revalidate`. Customer cart data is never cached in public CDNs or shared caches.

---

### 22.11 Cart Error Scenarios & Matrix

| Error Code | HTTP Status | Trigger Condition |
|---|---|---|
| `AUTHENTICATION_REQUIRED` | 401 | Protected cart endpoint accessed without authentication or valid guest token |
| `CART_ITEM_NOT_FOUND` | 404 | Specified cart item does not exist or belongs to another customer |
| `INVALID_VALUE` | 422 | Quantity ≤ 0, > 100, non-integer, malformed, or client-supplied financial field present |
| `INVALID_PRODUCT_VARIANT` | 422 | `variant_id` does not belong to `product_id`, or the variant is inactive |
| `PRODUCT_NOT_PURCHASABLE` | 422 | `product_type === MADE_TO_ORDER` — the product must use the request workflow, not the cart |
| `PRODUCT_UNAVAILABLE` | 422 | Product exists but fails purchasability flags (`is_active: false` or `is_published: false`) |
| `INSUFFICIENT_STOCK` | 422 | Product is purchasable but `available_quantity < requested_quantity` |
| `RATE_LIMITED` | 429 | Excessive rapid cart mutations from the same client |

---

## 23. Checkout API Contract (Phase 1.22)

> **Authority:** Canonical domain API contract for the **Checkout** subsystem (`CHK-001`). Consolidates `phases/phase-1.22.md`.
> **Core Principle:** Checkout is a server-controlled transaction workflow. The client requests checkout; the server decides whether checkout is valid and what the authoritative transaction contains. Checkout converts the customer's current active Cart into an Order while enforcing catalog, cart, inventory, pricing, fulfillment, ownership, concurrency and idempotency invariants. Financial, inventory, identity, time and state authority are server-only.

### 23.1 Endpoint CHK-001 — Checkout

| Attribute | Version 1 Contract |
|---|---|
| **ID** | `CHK-001` |
| **Method** | `POST` |
| **Path** | `/api/v1/checkout` |
| **Domain** | Checkout (Cart → Order creation) |
| **Actor** | `CUSTOMER` only |
| **Authentication** | **Required** — anonymous checkout is rejected with `401 AUTHENTICATION_REQUIRED` (alias `CHECKOUT_REQUIRES_AUTHENTICATION` identical). No guest checkout. |
| **Authorization** | `AUTHENTICATED_OWNER` — authenticated customer may checkout **only their own active Cart**. Cart identity is derived from authenticated principal (`me/cart`), not from a client-supplied `cart_id`. |
| **Purpose** | Validate the customer's purchase intent and create the resulting Order. |
| **Idempotency** | **Required** — client must send `Idempotency-Key` header (see §23.8). Duplicate same-key requests replay original result; same key with materially different input yields conflict error. |
| **Concurrency** | **Critical** — inventory validation and order/cart state transition are atomic (see §23.9). |
| **Caching** | Not cacheable — `Cache-Control: private, no-store`. Contains private order financial and address data. |
| **Payment boundary** | Checkout creates the Order; payment initiation/status remains **Phase Group H** (`PAY-001/002`, `WEBHOOK-001`). Checkout does not implement provider integration. |
| **Status** | `PROPOSED` (target `APPROVED` after Phase 1.23 review) |

*Why not `PATCH /me/cart` and not `POST /orders` directly:* Checkout is not a cart field change and not an arbitrary order creation. It performs validation, inventory checks, price calculation, fulfillment decision, transaction creation and payment handoff. Direct `POST /orders` with client-controlled `total`/`status`/`reference`/`inventory` is prohibited.

### 23.2 Request — Body Only, No Query Params

- **Content-Type:** `application/json` UTF-8. No query parameters for business values (`/checkout?delivery_fee=…` prohibited).
- **Conceptual body for `DELIVERY`:**

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha Mwangi",
    "phone": "+255700000001",
    "address_line": "Block C, Mikocheni B, Dar es Salaam",
    "city": "Dar es Salaam"
  }
}
```

- **Conceptual body for `PICKUP`:**

```json
{
  "fulfillment_type": "PICKUP"
}
```

Delivery address fields must follow the approved `OrderAddress` snapshot model (recipient name, phone, address line, city — exact set aligned with `api-resources.md §3` Order `delivery_address` snapshot). Saved address book is **deferred** — no `saved_address_id` is required; address is supplied inline.

### 23.3 Input Fields — Allow-List & Server-Controlled

| Field | Required | Type | Valid Values / Rule | Client Authority |
|---|---|---|---|---|
| `fulfillment_type` | **Yes** | enum CLOSED | `PICKUP` / `DELIVERY` only (`UPPER_SNAKE_CASE`). `shipping`/`courier`/`self-delivery`/`warehouse`/`COURIER`/`EXPRESS`/`LOCAL_DELIVERY` are rejected with `INVALID_FULFILLMENT` / `INVALID_VALUE`. | Client chooses; server validates |
| `delivery_address` | **Conditional** | object | **Required when `fulfillment_type=DELIVERY`**; **must be absent or `null` when `fulfillment_type=PICKUP`**. Structure validated; cannot be used to alter ownership/identity/fee. | Client supplies info; server validates |
| `delivery_address.recipient_name` | Conditional | string | Trimmed non-empty when DELIVERY | Client |
| `delivery_address.phone` | Conditional | string | Normalized, required when DELIVERY | Client |
| `delivery_address.address_line` | Conditional | string | Trimmed non-empty when DELIVERY | Client |
| `delivery_address.city` | Conditional | string | Trimmed non-empty when DELIVERY | Client |
| *Any* `delivery_fee` | **Prohibited** | — | Customer-supplied `{"delivery_fee": …}` is **never authoritative**; if sent, rejected with `INVALID_VALUE` per server-controlled field rule | **Server/Staff/Admin** only |
| *Any* `total` / `subtotal` / `unit_price` / `line_total` / `discount` / `currency` override | **Prohibited** | — | Client `{"total":100000}` / `{"subtotal":…}` / `{"item_price":…}` / `{"currency":"USD"}` **rejected** with `INVALID_VALUE` (validation error); backend calculates authoritative money per `§3.8`/`§13.8` minor units `{amount:int,currency:"TZS"}` | **Server-calculated** |
| *Any* `quantity` beyond cart, `available_quantity`, `reserved_quantity` | **Prohibited** | — | Inventory counts never client-supplied | **Server authority** |
| *Any* `status` / `order_status` / `payment_status` | **Prohibited** | — | Customer cannot submit `{"status":"PAID"}` or `"COMPLETED"` via checkout | **Server decides** |
| *Any* `order_reference` (`OD-…`) | **Prohibited** | — | Server-generated opaque reference | **Server-generated** |
| *Any* `cart_id` | **Prohibited** | — | Server derives `current authenticated customer → current active Cart`. `{"cart_id":"..."}` as ownership mechanism is rejected | **Server derives** |
| *Any* `user_id` / `customer_id` override | **Prohibited** | — | Ownership from authenticated session, not body | **Server derives** |
| *Unknown fields* | — | — | Strict rejection (`INVALID_VALUE`/`MISSING_REQUIRED_FIELD` envelope) per `§13.14`/`§14.9` | — |

Empty-string `""` is not treated as absent for required address fields unless contract says so; whitespace trimmed server-authoritatively; enums exact case `PICKUP` not `pickup`.

### 23.4 Fulfillment — Closed Enum `PICKUP` / `DELIVERY`

- **CLOSED enum:** Only `PICKUP` and `DELIVERY` exist in V1. No `COURIER`/`EXPRESS`/`WAREHOUSE` extension without formal compatibility review.
- **`PICKUP`:** No delivery information required. Order records `fulfillment_type: PICKUP`, `delivery_address: null`, `delivery_fee: {amount:0,currency:"TZS"}` (same shape, not bare `0`). Pickup location model is deferred; customer receives pickup information via Order contract.
- **`DELIVERY`:** Customer must provide valid `delivery_address`. Backend validates presence and structure. Staff/Admin later determine applicable delivery fee (see §23.5).
- `fulfillment_type` is mutually exclusive with invented values; `PICKUP` + delivery address supplied yields validation error.

### 23.5 Delivery & Delivery-Fee Authority

**Invariant — server/staff authority (business-rules §5 #5, decisions CHK-006):**

> Delivery fee is variable, location-based, and **added directly by Staff/Admin**. The customer chooses `DELIVERY`; Staff/Admin determine and add the appropriate fee; backend stores the authoritative fee; customer sees the resulting fee. Customer-supplied `delivery_fee` is never authoritative.

```
Customer → chooses DELIVERY (provides delivery_address)
Staff/Admin → determines/adds appropriate delivery fee
Backend → stores/calculates authoritative delivery_fee (minor units {amount,currency})
Customer → sees resulting fee in Order
```

- Flat `TZS 20,000` assumption is **superseded**; no fixed rate is assumed.
- Checkout request **must not** contain `delivery_fee`.
- Currency is authoritative `TZS` from backend/business configuration; client `{"currency":"USD"}` does not change transaction currency.

### 23.6 Delivery-Fee Timing — Approved Version 1 Workflow (Model B)

**Decision (CHK-009):** Version 1 uses **Model B — fee-after-order**:

```
Customer selects DELIVERY → POST /checkout (with delivery_address)
        ↓
Order created (PENDING_PAYMENT) — delivery_address snapshotted,
  subtotal authoritatively calculated,
  delivery_fee = null, delivery_fee_status = PENDING (explicit provisional)
        ↓
Staff/Admin reviews delivery → adds authoritative delivery_fee (delivery_fee = {amount, currency}, delivery_fee_status = FINALIZED)
        ↓
Final total (subtotal + delivery_fee.amount) becomes authoritative and payment becomes eligible (payment: {amount: final total})
        ↓
Customer sees final amount (must check delivery_fee_status before displaying total as final)
        ↓
Payment workflow proceeds (Group H) — payment amount equals authoritative final Order total; PAY-001 blocked with 409 DELIVERY_FEE_PENDING while PENDING
```

**Rules following Model B:**
- Order can be **created before final delivery fee is known**. The order is the business record that carries the delivery workflow.
- Payment must operate on the **final authoritative amount stored by the Order**, not a pre-fee amount. `customer pays before fee known → staff changes fee → database total differs from payment amount` is **forbidden**. Payment initiation (`PAY-001`) is only valid after delivery_fee is finalized (or is zero where applicable).
- Client price transparency: the final amount charged must equal the final customer-visible amount; do not silently charge a different amount without review opportunity (see §23.12).
- This adds no new order status value; lifecycle remains `PENDING_PAYMENT → PAID → …` per `AGENTS.md §4.4`, with delivery_fee finalization occurring while in `PENDING_PAYMENT` before `PAID`. If payment-before-fee queuing is needed, it will be modeled as a staff fee-assignment step within `PENDING_PAYMENT`, not a new top-level status.
- Alternative Model A (`fee known/assigned immediately during checkout before order creation`) is not assumed — if business later provides synchronous fee determination at checkout, it will be added as an explicit variant without changing client fee authority.

Recorded in `docs/decisions.md CHK-009` and `docs/domain/business-rules.md §4a`.

### 23.7 Financial Authority — Server Is Source of Truth

> **Critical security rule:** Customer supplies only purchase intent. Server is source of truth for `unit_price`, `line subtotal`, `cart subtotal`, `delivery fee`, `order total`, `currency`, `payment amount`.

```
1. Load active Cart (own)
2. Validate every item (exists, active, IN_STOCK, variant valid, purchasable)
3. Resolve current authoritative catalog unit prices (recalculate; do not trust frontend cache)
4. Calculate line totals (unit_price * quantity — minor-unit integer arithmetic, no float)
5. Calculate subtotal (sum line totals)
6. Determine fulfillment (PICKUP/DELIVERY)
7. Create/prepare Order (PENDING_PAYMENT) with snapshots (items, prices, delivery_address snapshotted, subtotal authoritative, delivery_fee = null + delivery_fee_status = PENDING for DELIVERY / delivery_fee = {amount:0, currency:"TZS"} + delivery_fee_status = FINALIZED for PICKUP, payment = null when PENDING)
8. Staff/Admin sets authoritative delivery_fee (for DELIVERY) per §23.5/§23.6 — server/staff-controlled, never client-supplied (transitions delivery_fee null → {amount,currency} and delivery_fee_status PENDING → FINALIZED)
9. Calculate final total (subtotal + delivery_fee.amount) — authoritative, stored on Order; only final when delivery_fee_status = FINALIZED (provisional total equals subtotal while PENDING)
10. Continue payment workflow (Group H) on final total — payment amount equals authoritative final Order total; blocked with 409 DELIVERY_FEE_PENDING while PENDING (payment remains null)
```

Aligned with Model B (fee-after-order): Order is created before delivery_fee is finalized; Steps 7→8→9 ensure `delivery_fee pending → staff assignment → final total` (see §23.6).

Price recalculation is mandatory at checkout even if cart displayed `Sofa = 1,000,000` and price became `1,100,000` before checkout — backend uses currently authoritative catalog price and communicates changed amount to customer before final payment where workflow allows (price transparency §23.12). Same `city`/`address_line` fees are not silently invented.

All money uses `§3.8` integer minor units `{amount,currency:"TZS"}`; changing shape is breaking.

### 23.8 Idempotency — Required

Checkout is **critical idempotency operation**. Network retry must not create two Orders, two reservations, or two payment attempts for the same logical checkout.

- **Mechanism:** Client must send `Idempotency-Key: <opaque-uuid>` request header on `POST /checkout`. Server persists key → result mapping (durable store, unique constraint) and returns original result on replay.
- **Semantics — same key, same logical attempt:**
```
Request A  key=K123 → Checkout created (201 + Order)
Retry      key=K123 → Return original result (201 replay, same order_reference, same data), no new Order
```
- **Same key, materially different input** (`fulfillment_type` or `delivery_address` differs) → stable conflict/idempotency error `CONFLICT` / `DUPLICATE_OPERATION` (409) — do not silently execute different operation.
- **Timeout handling:** Client timeout does not prove failure. Retry with **same** `Idempotency-Key` reconciles with original operation.
- Storage and exact header name formatting finalized in later idempotency implementation phase; requirement (idempotency required) is normative now (deferred detail per `api-conventions.md §23.3`).

### 23.9 Concurrency & Inventory Authority

**Critical security rules:**
- Server is source of truth for `availability`, `stock`, `reservation/consumption`. Client only supplies desired quantity — never `available_quantity`/`reserved_quantity`.
- Time (`checkout timestamp`, cancellation/payment timestamps) is server-determined; 20-minute window uses server time.

**Preconditions before success (all must hold):**
```
Customer authenticated
AND customer has active Cart
AND Cart contains at least one valid item
AND all items are purchasable (exists, active, IN_STOCK, product_type IN_STOCK)
AND variants exist, belong to product, active, purchasable
AND inventory sufficient (requested quantity ≤ authoritative available_quantity at transaction point)
AND fulfillment valid (PICKUP/DELIVERY)
AND delivery information valid when DELIVERY
AND authoritative pricing determinable
AND idempotency key valid
```

Specific revalidation steps:
- **Product state:** `exists → active → is_published → product_type IN_STOCK → purchasable`. Do not rely on catalog-cached state. `MADE_TO_ORDER` in cart → `CART_INVALID` / `PRODUCT_NOT_PURCHASABLE` (422), no partial order.
- **Variant:** `exists → belongs to product → active → purchasable` (VAR-OWN-001).
- **Inventory:** `requested quantity ≤ available authoritative quantity` at transaction point, checked **inside atomic boundary**. Race scenario `Customer A sees 1, B buys 1, A checks out → A fails safely` with no negative stock or oversell.

Implementation requirement (for later Laravel/database phases): **Inventory validation and the corresponding state-changing operation (order creation, cart transition, inventory reservation/consumption) must be safe under concurrent checkout attempts** (atomic/transactional with appropriate locking/isolation; `SELECT then UPDATE` without protection prohibited). Exact locking strategy not designed here, but correctness under concurrency is normative (INV-003).

### 23.10 Cart & Order Lifecycle Around Checkout

- **Empty cart:** `POST /checkout` with empty `me/cart` → `422 CART_INVALID` (conceptual `CART_EMPTY` maps to `CART_INVALID` per registry) — never create empty order.
- **Invalid cart item (stale/unavailable):** Any line with `is_purchasable:false` → `422 CART_INVALID` (or more specific `PRODUCT_NOT_PURCHASABLE`/`INVALID_PRODUCT_VARIANT`/`INSUFFICIENT_STOCK` with `field` path) — do not create partial order.
- **MADE_TO_ORDER cart protection:** If cart somehow contains `MADE_TO_ORDER`, checkout rejects with `PRODUCT_NOT_PURCHASABLE` (422).
- **Cart after successful checkout:** Active cart must no longer represent same unprocessed purchasable state. Either **clear cart items** or **mark cart inactive/completed** — the choice aligns with Cart contract Phase 1.21: cart items are cleared after successful order creation so a new empty active cart is ready. The successful order becomes the business record.
- **Cart after failed checkout:** Failed validation/stock/fulfillment → cart **preserved** where safe (customer adjusts quantity then retries). Do not empty cart on ordinary `INSUFFICIENT_STOCK`/`CART_INVALID`.
- **Inventory lifecycle for `PENDING_PAYMENT` delivery orders (Model B — reserved, not consumed, with explicit release/restore):**
  - **At `CHK-001` (DELIVERY or PICKUP):** Validate `requested ≤ available` inside atomic transaction, then **reserve** stock: `reserved_quantity += quantity`, `available_quantity = physical_quantity - reserved_quantity`. Reserve happens atomically with order creation (`PENDING_PAYMENT`, `delivery_fee_status=PENDING` for DELIVERY / `0/FINALIZED` for PICKUP) and cart clearance. Stock is **reserved, not consumed** (`physical_quantity` unchanged).
  - **During `PENDING_PAYMENT` before `ORD-014`:** Reservation held; no second reservation. `ORD-014` success (fee `PENDING→FINALIZED`) keeps reservation unchanged; `ORD-014` failure (validation, already finalized, wrong state/fulfillment) does **not** release reservation — order stays `PENDING_PAYMENT` with reservation held for retry or terminal release.
  - **Release / restore (atomically with status change, idempotent via `Idempotency-Key` where applicable, status-history appended):** `reserved_quantity -= quantity`, `available_quantity += quantity` executed atomically when order reaches a terminal non-fulfilled state (`CANCELLED`, not `EXPIRED`):
    - Customer cancellation `ORD-004` (`PENDING_PAYMENT` within 20-min window, `POST /api/v1/orders/{order}/cancel`, idempotent) → atomic release + `order_status_history` append → `CANCELLED`
    - Admin cancellation / System expiry (`Admin: POST /api/v1/orders/{order}/cancel` with `orders.manage` / `System: TTL expiry via Group H` — see `docs/domain/business-rules.md §2/§8` expiry, e.g., pending without fee finalization or without payment beyond configured window, `System` actor) → atomic release + `order_status_history` append, idempotent (`Admin Idempotency-Key` / `System event-id`), `CANCELLED` (not `EXPIRED`)
    - Payment failure (Group H: provider decline/timeout, `PAY-001` failure) → atomic release + `order_status_history` append → `CANCELLED` (not `EXPIRED`; payment failure does **not** keep indefinite hold)
  - **On payment success (`PAID` via Group H webhook):** Reservation retained and **converted to consumption**: `physical_quantity -= quantity`, `reserved_quantity -= quantity` (so `available` unchanged, held stock becomes sold). Alternative implementation may keep `reserved` until `ACCEPTED`/`COMPLETED` then consume, but consumption must occur no later than `PAID`→fulfillment and must be atomic with `PENDING_PAYMENT→PAID`, never leaving `physical` overstated.
  - **Indefinite holds prohibited:** Pending `DELIVERY` without fee finalization or without payment must not hold stock forever; an expiry/TTL (e.g., fee-finalization window + payment window, configured, not client-controlled) must eventually release if terminal state not reached. Expiry mechanism deferred to implementation but lifecycle above is normative.
  - `PICKUP` pending follows same reserve → release on cancel/expiry/failure → consume on `PAID` path; no special case.
- **Payment failure aftermath:** deferred to Group H; payment failure handling follows release rule above — reservation is released when order moves to terminal `CANCELLED`/expired, not kept indefinitely, and does not automatically destroy valid cart intent unless approved payment/order workflow explicitly requires it.
- **Partial order risk:** Failed checkout must not leave an incomplete order without clear reconciliation (`Order created but response says error` ambiguity prohibited). Transaction/idempotency design must prevent this; order creation + inventory reserve + cart transition are atomically bounded (see §23.9). If checkout transaction fails after reserve, reservation is rolled back atomically (no orphan hold).
- **Delivery address snapshot:** `delivery_address` supplied at checkout becomes part of resulting Order's historical fulfillment data (snapshot), not a live mutable profile reference.

### 23.11 Response — Checkout Result

- **Success (`201 Created`):** Returns resulting order/checkout state sufficient for client to continue (order identity, reference, status, financial summary, fulfillment, and payment handoff placeholder). Exact `Payment` representation is Group H; provider-specific fields are not finalized here.

```json
// DELIVERY — fee pending (Model B, immediately after CHK-001)
// delivery_fee is null, delivery_fee_status=PENDING marks provisional amounts;
// payment is null and PAY-001 is blocked until finalization (409 DELIVERY_FEE_PENDING)
{
  "data": {
    "order_id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
    "order_reference": "OD-12345",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "DELIVERY",
    "delivery_address": {
      "recipient_name": "Asha Mwangi",
      "phone": "+255700000001",
      "address_line": "Block C, Mikocheni B, Dar es Salaam",
      "city": "Dar es Salaam"
    },
    "subtotal": { "amount": 170000000, "currency": "TZS" },
    "delivery_fee": null,
    "delivery_fee_status": "PENDING",
    "total": { "amount": 170000000, "currency": "TZS" },
    "currency": "TZS",
    "payment": null
  }
}
```

```json
// PICKUP — fee finalized at creation, still PENDING_PAYMENT before PAY-001
// delivery_fee_status=FINALIZED marks final amounts; payment is still null until PAY-001 (consistent with DELIVERY PENDING and ORD-014 FINALIZED)
// Payment representation is created only by PAY-001, not by CHK-001 or ORD-014
{
  "data": {
    "order_id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
    "order_reference": "OD-12346",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "PICKUP",
    "delivery_address": null,
    "subtotal": { "amount": 170000000, "currency": "TZS" },
    "delivery_fee": { "amount": 0, "currency": "TZS" },
    "delivery_fee_status": "FINALIZED",
    "total": { "amount": 170000000, "currency": "TZS" },
    "currency": "TZS",
    "payment": null
  }
}
```

- **Explicit pending representation:** `delivery_fee: null` + `delivery_fee_status: "PENDING"` marks provisional state; `total` equals `subtotal` but is **provisional** and MUST NOT be displayed or submitted as final amount. Frontend must check `delivery_fee_status === "PENDING"` and show “Delivery fee pending — Staff will confirm” instead of final total. `payment: null` signals payment blocked.
- **Blocked payment & payment lifecycle (Group H boundary):** When `delivery_fee_status=PENDING`, `POST /api/v1/payments` (`PAY-001`) MUST be rejected with `409 CONFLICT` `code: "DELIVERY_FEE_PENDING"` (or `422 INVALID_ORDER_TRANSITION` per registry, mapped to 409) — never accept provisional `total` as payment amount. After Staff/Admin sets fee via `ORD-014` (`delivery_fee: {amount: 2500000, currency:"TZS"}`, `delivery_fee_status: "FINALIZED"`, `total: {amount: 172500000,...}`), `PAY-001` becomes eligible (`payment` still `null` until `PAY-001`; `payment` object is created **only** by `PAY-001`, not by `CHK-001` or `ORD-014`). For `PICKUP`, `delivery_fee_status` is `FINALIZED` at creation (`amount:0`, `total` final immediately, `payment: null` until `PAY-001`; `PAY-001` eligible immediately, no `DELIVERY_FEE_PENDING`). Checkout never creates `payment: {payment_status: PENDING}` — that representation is exposed **only after** `PAY-001` (`Order.status` still `PENDING_PAYMENT` until webhook confirms `PAID`).

- Header on success: `Idempotency-Key` echo or original result metadata (implementation-defined).
- **Failure** uses common `{"errors":[…],"meta":{"request_id":…}}` envelope per `§15`.

### 23.12 Checkout Validation Errors & HTTP Mapping

Potential stable API codes (CLOSED per §15.15/§15.3, business meaning per `business-rules.md §14`):

| Failure | API `code` | HTTP |
|---|---|---|
| Missing/invalid auth (anonymous checkout) | `AUTHENTICATION_REQUIRED` (canonical; alias `CHECKOUT_REQUIRES_AUTHENTICATION`) | 401 |
| Authenticated but not owner / holder mismatch | `CART_NOT_FOUND` / `RESOURCE_NOT_FOUND` (404 **masked** — never `403 FORBIDDEN` for holder-scoped cart, per §15.8) | 404 |
| Empty cart | `CART_INVALID` (conceptual `CART_EMPTY` maps here) | 422 |
| Cart contains invalid/stale item | `CART_INVALID` / `CART_ITEM_UNAVAILABLE` | 422 |
| Product not purchasable (incl. `MADE_TO_ORDER` attempt) | `PRODUCT_NOT_PURCHASABLE` | 422 |
| Variant invalid / belongs-to mismatch | `INVALID_PRODUCT_VARIANT` | 422 |
| Insufficient stock / race | `INSUFFICIENT_STOCK` (`details: {requested_quantity, available_quantity}` safe) | 409 / 422* (*409 when concurrency conflict, 422 when schema business invalid — endpoint contract documents choice per §15.10) |
| Invalid fulfillment value | `INVALID_FULFILLMENT` (`field: fulfillment_type`) | 422 |
| Invalid/ incomplete delivery info | `INVALID_DELIVERY_INFORMATION` (`field: delivery_address.city` etc.) | 422 |
| Generic `fulfillment_type`/`address` schema type/format | `INVALID_VALUE` / `INVALID_TYPE` / `INVALID_FORMAT` / `MISSING_REQUIRED_FIELD` | 422 |
| Unknown field in body | `INVALID_VALUE` + `field` | 422 |
| Idempotency key reuse with different input | `CONFLICT` / `DUPLICATE_OPERATION` | 409 |
| Rate limited | `RATE_LIMITED` + `Retry-After` | 429 |
| Unexpected failure | `INTERNAL_SERVER_ERROR` + `meta.request_id` | 500 |

Checkout **must not** create an order, reduce inventory, or initiate payment on any validation failure; cart remains usable where safe. Checkout is not retryable by changing `total`/`fee`/`status` — client fixes input or stock, then retries with new idempotency key (or same key for true retry).

### 23.13 Authorization & Ownership

- Authenticated `CUSTOMER` may checkout **only own active Cart** (`cart.owner == authenticated principal`). `cart_id` or `user_id` swapping, `role` tampering, or `status` override are rejected and do not bypass policy.
- Staff/Admin are **not** checkout actors via `CHK-001`. Staff do not manually approve ordinary customer checkout before an order can exist; flow remains `Customer → checkout → order created → staff processes order` unless the chosen delivery-fee model necessarily requires staff fee step before payment (which it does — fee addition is separate operational step, not a checkout gate).
- Protected checkout response is private (`Cache-Control: private, no-store`); no address or financial data publicly cached.

### 23.14 Security Review — Threats & Defenses

| Threat | Defense |
|---|---|
| IDOR (Customer A checkout Customer B cart) | Self-context `/me/cart` ownership derived from auth; `cart_id` not client-controlled; holder mismatch → 404 masked |
| Price manipulation (`total`/`subtotal`/`unit_price` override) | Backend recalculates all money; client `total` rejected per §23.3/§23.7 |
| Delivery-fee manipulation | Customer `delivery_fee` rejected; fee staff/admin-only per §23.5 |
| Quantity / stock manipulation | Client supplies quantity only; stock server-authoritative per §23.9 |
| Order ownership manipulation | Order owner set from authenticated principal at creation; not from body |
| Role tampering (`STAFF` checkout as customer) | Staff not authorized for `CHK-001`; role CLOSED and server-controlled |
| Duplicate submission / replay | `Idempotency-Key` required; same-key replay returns original; different input → 409 |
| Payment spoofing (`status: PAID` in body) | Checkout does not accept `status`/`payment_status`; payment verification is Group H webhook |
| Cart substitution (`cart_id` hijack) | No `cart_id` in contract; server resolves own active cart |
| Stock race / oversell | Atomic validation + mutation under transaction (§23.9) |
| Currency manipulation | Currency `TZS` server-determined; client `currency` rejected |
| Time manipulation (cancellation window) | Server time authority per §23.9 |

### 23.15 Client Behavior — Next.js & Flutter

- **Next.js:** Must be able to submit checkout, receive field validation errors (`field: delivery_address.city`), display stock problems (`INSUFFICIENT_STOCK`), display totals/fee info from Order, recover from network retry using same `Idempotency-Key` — without implementing business authority in browser.
- **Flutter:** Must be able to submit checkout, retry safely with same key on timeout, display resulting Order, handle stock conflict (`409 INSUFFICIENT_STOCK`), continue payment flow — using same API contract.
- **Admin/Staff:** Interact with resulting Order via `ORD-005..013`; checkout itself does not expose administrative operations.

### 23.16 Checkout → Order Boundary

> Checkout defines how purchase intent becomes an Order. Order API defines how that created Order is subsequently read and operationally managed.

- Successful `CHK-001` **creates an Order** (`PENDING_PAYMENT`). The Order becomes a separate business resource (`ORD-001..013`) after creation.
- Do not duplicate Order status-management behavior in Checkout.
- Checkout is the **actual customer submission that creates the Order** (`place order`), not a provisional `start checkout` preview. A separate `review/quote` preview is not added unless delivery-fee timing or payment workflow truly requires it.

### 23.17 Checkout Matrix (Normative)

| Attribute | Version 1 |
|---|---|
| Endpoint | `POST /api/v1/checkout` |
| Actor | Customer |
| Authentication | Required |
| Cart | Own active Cart (server-derived) |
| Guest checkout | Not allowed (401) |
| Fulfillment | `PICKUP`, `DELIVERY` (CLOSED) |
| Delivery address | Required for `DELIVERY`, forbidden for `PICKUP` |
| Delivery fee | Server/Staff/Admin controlled, never client-supplied |
| Customer total input | Not allowed |
| Product price input | Not allowed |
| Stock input | Not allowed |
| Order status input | Not allowed |
| Order reference input | Not allowed |
| Cart selection input | Not allowed |
| Currency override | Not allowed |
| Idempotency | Required (`Idempotency-Key`) |
| Inventory check | Authoritative, atomic |
| Pricing | Server-calculated (minor units) |
| `MADE_TO_ORDER` | Not purchasable via checkout (422) |
| Cart after success | Cleared/inactivated → Order is record |
| Cart after validation failure | Preserved |
| Payment | Deferred to Group H — checkout creates Order; payment amount = authoritative Order total after fee finalization |

### 23.18 Validation Matrix (Normative)

| Validation | Required |
|---|---|
| Authentication | Yes (`AUTHENTICATION_REQUIRED` 401) |
| Cart ownership | Yes (own active Cart) |
| Cart not empty | Yes (`CART_INVALID` 422) |
| Product exists | Yes |
| Product purchasable (`is_active && is_published && IN_STOCK`) | Yes |
| Variant valid (`exists && belongs to product && active && purchasable`) | When applicable |
| Quantity valid (1..100, integer) | Yes |
| Current stock available (authoritative, atomic) | Yes |
| Fulfillment valid (`PICKUP`/`DELIVERY` CLOSED) | Yes |
| Delivery information (`delivery_address` object) | When `DELIVERY` |
| Delivery fee authority | Server/Staff/Admin only |
| Current price (authoritative recalculation) | Server |
| Final total (subtotal + delivery_fee) | Server |
| Idempotency (`Idempotency-Key`) | Yes |
| Unknown-field rejection | Yes |

### 23.19 Future Test Coverage (Checkout)

Idempotency, concurrency, cart-ownership, financial-authority and delivery-fee tests are deferred to implementation but required per `§14.18`:
`unauthenticated checkout, Customer A checkout Customer B cart, empty cart, MADE_TO_ORDER checkout, invalid variant, variant from another product, insufficient stock, concurrent checkout, tampered price/subtotal/total/delivery_fee/status/customer_id, duplicate checkout same key, same key different input, network retry after success`. Each must become automated API/feature test before Group G implementation is complete.

### 23.20 Cross-References

- Conventions: `api-conventions.md §23` (checkout conventions — idempotency, concurrency, financial/inventory/time/state authority).
- Resources: `api-resources.md §4a` (Checkout resource, request allow-list, Cart→Checkout→Order relationship).
- Domain: `business-rules.md §4a` (checkout invariants, delivery-fee timing, authority).
- Decisions: `decisions.md CHK-001..CHK-009` (checkout + fee timing).
- Order: `§24` (Phase 1.23) will take the Order created by `CHK-001` and define identity/history/status/cancellation/tracking.

---

## 24. Order API Contract (Phase 1.23)

> **Authority:** Canonical domain API contract for the **Order** subsystem (`ORD-001`..`ORD-013`). Consolidates `phases/phase-1.23.md` and is consistent with `Catalog` (`§21`), `Cart` (`§22`), `Checkout` (`§23`), `Authentication` (`§17`), `Authorization` (`§18`), `Validation` (`§14`), `Error` (`§15`).
> **Core Principle:** Once an Order exists, it becomes a historical business record. Current Catalog data must not silently rewrite its historical meaning. Order is customer-owned, operationally managed, financially authoritative, stateful, private, and auditable. Payment (`Group H`) is related but distinct.

### 24.1 Order as Historical Business Record

An Order is:

```
historical — snapshot of what was purchased, at what price, with what fee
customer-owned — belongs to exactly one authenticated CUSTOMER
operationally managed — Staff/Admin process through controlled actions
financially significant — subtotal/delivery_fee/total authoritative in minor units
stateful — moves only along approved transitions, server-controlled
auditable — every important transition is recorded (order_status_history)
private — non-public, non-CDN-cacheable
```

It is **not** a live copy of `Product` catalog. Historical `unit_price`, `line_total`, `subtotal`, `delivery_fee`, `total`, `quantity`, `delivery_address`, `recipient contact` remain immutable after creation (except explicitly allowed fee finalization).

```
Customer
   ↓
Checkout (CHK-001)
   ↓
Order
   ├── Items (historical snapshots)
   ├── Fulfillment (PICKUP / DELIVERY)
   ├── Payment relationship (Group H, separate state machine)
   ├── Tracking (customer-facing progress)
   └── Status history (audit, append-only)
```

### 24.2 Order Resource & Identity

| Attribute | Contract |
|---|---|
| **Resource** | `Order` |
| **Collection** | `GET /api/v1/me/orders` (customer self-context) — see §24.6 |
| **Detail** | `GET /api/v1/me/orders/{order}` and `GET /api/v1/orders/{order}` (operational) — see §24.7, `api-resources.md §3` |
| **Internal `id`** | Opaque API identifier `ord_...`, stable, server-generated, never client-supplied |
| **Customer-facing `order_reference`** | `OD-*****` (e.g. `OD-2026-00123`), **unique**, **human-readable**, **stable**, **non-editable**, **server-generated** at Order creation (inside `CHK-001` transaction). Exact length/sequence mechanism is implementation detail; must be unique and not guessable as enumeration vector. |
| **Immutability** | `id`, `order_reference`, `customer owner`, `items quantity`, `historical unit_price`, `original created_at` are immutable via normal APIs — not `PATCH`able. Correction is controlled admin workflow only (see §24.14). |

### 24.3 Customer Ownership

Every normal customer Order belongs to exactly one authenticated `CUSTOMER`:

```
Customer → Orders (1:N)
```

- Authenticated ownership is derived from `CHK-001` principal; `POST /checkout` sets `order.customer_id = authenticated principal`.
- Client-supplied `{"customer_id":"..."}` is **rejected** (`INVALID_VALUE` 422) — ownership cannot be chosen.
- Customer can retrieve **only own Orders** (`ORD-001`, `ORD-002`); `Customer A → Customer B order_id` fails with `404 ORDER_NOT_FOUND` (masked, never `403`) per `§15.8`.

### 24.4 Creation Boundary — Checkout Only

Normal customer Orders are created **only through `POST /api/v1/checkout` (`CHK-001`)**.

- No public `POST /api/v1/orders` that lets the client construct an arbitrary Order with `price`/`total`/`status`/`customer_id`/`order_reference`/`inventory`/`payment`. That endpoint, if ever needed for admin, is separate and not customer-authoritative.
- This protects `price`, `inventory`, `ownership`, `order status`, `totals` — all server-calculated/locked inside checkout transaction.

### 24.5 Order Endpoint Inventory (Authoritative — aligned with Phase 1.19 `§19.1`, finalized in Phase 1.23)

Order endpoints `ORD-001`..`ORD-014` are **`APPROVED`** (complete V1 Order contract — finalized in Phase 1.23). This supersedes the provisional `PROPOSED` label in `§19.1`/`§19.15` for the Order domain; `§19.15` remains `PROPOSED` for domains not yet finalized (e.g., pending tracking split in Phase 1.24 will add detail without re-proposing Order core). Clients may consume `ORD-001`..`ORD-014` as authoritative.

| ID | Method | Path | Actor | Auth | Authorization | Purpose | Idempotency | Concurrency |
|---|---|---|---|---|---|---|---|---|
| `ORD-001` | `GET` | `/api/v1/me/orders` | Customer | Required | `AUTHENTICATED_OWNER` own orders | List own orders (paginated, filtered) | — | — |
| `ORD-002` | `GET` | `/api/v1/me/orders/{order}` | Customer | Required | `AUTHENTICATED_OWNER` owns order (404 masked) | Get own order detail | — | — |
| `ORD-003` | `GET` | `/api/v1/me/orders/{order}/tracking` | Customer | Required | `AUTHENTICATED_OWNER` owns order | Customer tracking (current status + history milestones, not GPS) | — | — |
| `ORD-004` | `POST` | `/api/v1/me/orders/{order}/cancel` | Customer | Required | `AUTHENTICATED_OWNER` owns + `cancel_own` + eligible state + 20-min window + backend time | Cancel own eligible order | **Required** (`Idempotency-Key`) | **Critical** (race with staff accept) |
| `ORD-005` | `GET` | `/api/v1/orders` | Staff/Admin | Required | `OPERATIONAL` `orders.view_operational` | List operational orders (staff view) | — | — |
| `ORD-006` | `GET` | `/api/v1/orders/{order}` | Staff/Admin | Required | `OPERATIONAL` `orders.view_operational` | Get operational order detail | — | — |
| `ORD-007` | `POST` | `/api/v1/orders/{order}/accept` | Staff/Admin | Required | `OPERATIONAL` `orders.accept` + `Order=PAID` + `delivery_fee FINALIZED` | Accept `PAID→ACCEPTED` | Required | Critical |
| `ORD-008` | `POST` | `/api/v1/orders/{order}/process` | Staff/Admin | Required | `OPERATIONAL` `orders.process` + `Order=ACCEPTED` | Process `ACCEPTED→PROCESSING` | Required | Critical |
| `ORD-009` | `POST` | `/api/v1/orders/{order}/ready-for-pickup` | Staff/Admin | Required | `OPERATIONAL` `orders.ready_for_pickup` + `Order=PROCESSING` + `fulfillment=PICKUP` | Ready for pickup `PROCESSING→READY_FOR_PICKUP` | Required | Critical |
| `ORD-010` | `POST` | `/api/v1/orders/{order}/ship` | Staff/Admin | Required | `OPERATIONAL` `orders.ship` + `Order=PROCESSING` + `fulfillment=DELIVERY` | Ship `PROCESSING→SHIPPED` | Required | Critical |
| `ORD-011` | `POST` | `/api/v1/orders/{order}/deliver` | Staff/Admin | Required | `OPERATIONAL` `orders.deliver` + `Order=SHIPPED` + `fulfillment=DELIVERY` | Deliver `SHIPPED→DELIVERED` | Required | Critical |
| `ORD-012` | `GET` | `/api/v1/orders/{order}/tracking` | Staff/Admin | Required | `OPERATIONAL` `orders.view_operational` | Operational tracking (staff view, richer fields) | — | — |
| `ORD-013` | `POST` | `/api/v1/orders/{order}/complete` | Staff/Admin | Required | `OPERATIONAL` `orders.complete` + `Order=READY_FOR_PICKUP|DELIVERED` | Complete `READY_FOR_PICKUP→COMPLETED` (pickup) or `DELIVERED→COMPLETED` (delivery) | Required | Critical |
| `ORD-014` | `POST` | `/api/v1/orders/{order}/delivery-fee` | Staff/Admin | Required | `OPERATIONAL` `orders.set_delivery_fee` + `Order=PENDING_PAYMENT` + `fulfillment=DELIVERY` + `delivery_fee_status=PENDING` | Finalize delivery fee `PENDING→FINALIZED` before payment | **Required** (`Idempotency-Key`) | **Critical** (race with payment) |

*Notes:* `ORD-001`..`ORD-004` are the customer-facing IDs referenced in Phase 1.23 (`§115`); they map to the already-approved `ORD-001`=history, `ORD-002`=detail, `ORD-003`=tracking, `ORD-004`=cancel in `§19.1`. Staff `ORD-005`..`ORD-014` are operational (`ORD-014` is the V1 delivery-fee finalization gate required by Model B; fee assignment is not `INV-003` inventory adjust). `PATCH {status:...}` is **prohibited** — transitions only via `POST .../accept|process|ready-for-pickup|ship|deliver|complete|cancel|delivery-fee` (API-VAL-004, ADR/API-END-003). Payment `PAY-001` may start for a fee-finalized `PENDING_PAYMENT` Order; verified success transitions the Order to `PAID`

### 24.6 Customer Order Collection — `GET /api/v1/me/orders`

- **Auth:** Required. **Authz:** `Own Orders` only — query over authorized dataset (`page`/`per_page` paginate own orders, not all then filtered).
- **Pagination:** Global `page` (1-based, default 1) + `per_page` (default 20, 1–100) → `meta.pagination {current_page, per_page, total, last_page, has_next, has_previous}` per `§4`/`api-conventions.md §11`.
- **Default sorting:** `created_at DESC, id ASC` (newest first) + `id ASC` tie-breaker; documented explicitly; `sort` allow-list only (see §24.15).
- **Filters (allow-list only, per `§15` + `query-parameter-conventions`):** `order_status` CLOSED (`?order_status=PROCESSING`, not `?status`), `fulfillment_type` CLOSED (`PICKUP`/`DELIVERY`), `created_from`/`created_to` ISO8601 UTC with `Z` (optional date range). Unknown filter → `422 INVALID_VALUE`. No arbitrary `?field=value` DSL.
- **Response:** Paginated Order Summary collection (`data: [OrderSummary...], meta.pagination`); each summary contains `id`, `order_reference`, `status`, `fulfillment_type`, `delivery_fee_status`, `subtotal`, `delivery_fee`, `total`, `currency`, `created_at` (order creation time), `updated_at`. Never `password`/`payment secret`/`reserved_quantity`.

### 24.7 Customer Order Detail — `GET /api/v1/me/orders/{order}`

- **Auth:** Required. **Authz:** `authenticated customer` + `Order belongs to customer` — otherwise `404 ORDER_NOT_FOUND` (masked).
- **Response:** Full Customer Order Detail — see §24.11 (items, fulfillment, snapshots, financials, tracking summary, payment summary). Errors: `401 AUTHENTICATION_REQUIRED`, `404 ORDER_NOT_FOUND` (masked), `429 RATE_LIMITED`.

### 24.8 Order Items — Historical Snapshot

`Order → Items (1:N)` — owned by Order, `items: [OrderItemSnapshot]`.

Each `OrderItem` preserves at creation time:

| Field | Type | Notes |
|---|---|---|
| `product_id` | string | Reference to catalog product at transaction time |
| `variant_id` | string \| null | Variant reference where applicable |
| `sku` | string | Snapshot of `Product.sku` / `Variant.sku` |
| `name` | string | Snapshot of `Product.name` |
| `variant_name` | string \| null | Snapshot of variant display (`Charcoal Grey`) |
| `unit_price` | `{amount:int, currency:"TZS"}` | **Historical** price at transaction — minor units, integer arithmetic; not recomputed from current catalog |
| `quantity` | integer `1..100` | Historical quantity |
| `line_total` | `{amount,currency}` | `unit_price.amount * quantity` — snapshot |
| `primary_image` | `{url}` | Optional snapshot for display (not live catalog URL dependency for audit) |

**Why historical:** If catalog price `Sofa 1,000,000 → 1,200,000` or product deactivated, old Order still shows `1,000,000` and remains understandable. Order Items never rely solely on live `Product` representation; deletion/deactivation of product does not erase history.

**Immutability:** `PATCH /orders/{order}/items/{item}` to change price/quantity is **prohibited** (`405`/`422`). Operational correction is controlled admin workflow (audit, explicit permission, not normal API).

### 24.9 Fulfillment & Delivery Snapshot

- **Fulfillment:** `fulfillment_type` `PICKUP` / `DELIVERY` CLOSED, stored at Order creation, generally immutable (`Generally No` per §152 matrix). `PICKUP` vs `DELIVERY` drives branch validation (see §24.13).
- **Pickup:** `delivery_address: null`, `delivery_fee: {amount:0, currency:"TZS"}`, `delivery_fee_status: FINALIZED` at creation; lifecycle `PROCESSING→READY_FOR_PICKUP→COMPLETED` (no `SHIPPED`/`DELIVERED`).
- **Delivery:** `delivery_address: {recipient_name, phone, address_line, city}` **snapshot** from checkout (historical, not live profile address; later profile change does not rewrite). `customer contact` (`recipient_name`, `phone`) preserved for fulfillment even if `User` profile changes. Saved address book remains **deferred** — no `saved_address_id` dependency.
- **Billing address (V1):** `billing_address` is a **snapshot, not separately collected** — checkout input (`CHK-001` §23.3 / `api-resources.md §10.4` Line 262) provides only `fulfillment_type` + conditional `delivery_address`; no `billing_address` field is accepted. For `DELIVERY`, `billing_address` is a **copy of the `delivery_address` snapshot** provided at `CHK-001`; for `PICKUP`, `billing_address` is `null` (deferred — separate billing collection is V2). Field is therefore **nullable (`object | null`)** in V1 and must be treated as `null` for `PICKUP` by clients (see `api-resources.md §3`).
- **Delivery object:** `delivery: {status, tracking_summary}` | `null` for `PICKUP`; no GPS.

### 24.10 Delivery Fee — Variable, Staff/Admin, Historical

Variable, location-based, **not customer-controlled** (`PRICE-006`, CHK-006).

- At `CHK-001` for `PICKUP`: `delivery_fee {amount:0}, delivery_fee_status FINALIZED` immediately.
- At `CHK-001` for `DELIVERY`: `delivery_fee null, delivery_fee_status PENDING` (provisional `total = subtotal`), `payment null` blocked.
- **Finalization endpoint `ORD-014` `POST /api/v1/orders/{order}/delivery-fee`** (Staff/Admin, `Idempotency-Key` **Required**, concurrency **Critical**): sets fee from `PENDING` → `FINALIZED` **before** `PAID`. `delivery_fee_status` must be `PENDING` and `fulfillment_type=DELIVERY` and `status=PENDING_PAYMENT`; otherwise `409 INVALID_ORDER_TRANSITION` / `ORDER_STATE_CONFLICT`. Transitions `delivery_fee null → {amount:int (>=0, integer minor units), currency:"TZS"}` and `delivery_fee_status PENDING → FINALIZED`, recomputes `total = subtotal + delivery_fee.amount` authoritative, makes `payment` eligible. Single finalization; repeated calls with same `Idempotency-Key` replay original; same key with different `amount` → `409 DUPLICATE_OPERATION`; `PENDING→FINALIZED` only once before `PAID` (controlled admin correction after `PAID` is separate audited workflow, not normal staff). Zero-fee delivery (`amount: 0`) is explicitly valid where business offers free zone — validated as `>=0` across contract, conventions, and decisions.
  - **Request:** `{"delivery_fee": {"amount": 35000, "currency":"TZS"}, "reason": "Mikocheni zone 2" }` (`reason` optional, trimmed, audit). Strict: `amount` integer `>=0` minor units, `currency` must be `"TZS"`, unknown fields `422`, customer cannot call (403).
  - **Response:** `200` updated Order (`data: {id, order_reference, status: PENDING_PAYMENT, delivery_fee: {amount,currency}, delivery_fee_status: FINALIZED, total: {amount,currency}, payment: null (now eligible for PAY-001) }`).
  - **Auth:** `OPERATIONAL` `orders.set_delivery_fee` (new permission, Staff/Admin where approved; not `products.manage` or `inventory.manage`; `ADMIN` may grant). `Staff` without permission → `403 FORBIDDEN`.
  - **Errors:** `401 AUTHENTICATION_REQUIRED`, `403 FORBIDDEN`, `404 ORDER_NOT_FOUND` (masked if not operational view), `409 INVALID_ORDER_TRANSITION` (already FINALIZED or not PENDING_PAYMENT/DELIVERY), `422 INVALID_VALUE` (bad amount/currency), `409 ORDER_STATE_CONFLICT` (concurrent fee set vs payment), `429 RATE_LIMITED`.
  - **Audit & Idempotency:** Logged `actor, order, old_delivery_fee, new_delivery_fee, reason, occurred_at`; durable `Idempotency-Key` store (unique constraint) prevents duplicate fee writes on retry; `delivery_fee` change after `FINALIZED` outside `ORD-014` is prohibited except controlled admin correction.
- Once authoritative and especially after `PAID`, fee is historical record — not changed by later `delivery policy` change. Unrestricted repeated fee changes are prohibited; only `PENDING→FINALIZED` once before `PAID`, and controlled correction thereafter (audit, admin, not normal staff).
- Financial representation uses `§3.8` money `{amount,currency}` integer minor units.

### 24.11 Order Financials & Payment Relationship (Group H Boundary)

Conceptually:

```
Order { subtotal, delivery_fee, total, currency:"TZS" }  (Order side, this contract)
  └── Payment { payment_status, amount:{amount,currency}, currency } (Payment side, Group H)
```

- **Finality point (Model B):** `subtotal` authoritative at `CHK-001`; `delivery_fee` authoritative after `Staff sets fee, status FINALIZED`; `total = subtotal + delivery_fee.amount` becomes authoritative **before** `PAID`. `PENDING_PAYMENT → PAID` requires `delivery_fee_status=FINALIZED` (payment amount cannot be provisional). `PAY-001` (`POST /payments`) is `409 DELIVERY_FEE_PENDING` while pending (see `§23.6`/`§23.11`). Later Group H answers `Payment amount == final Order total`.
- **Customer-visible amounts:** Customer sees `payment_status` + `amount` + `currency` + safe reference (`payment.id`, `order_reference`), never `provider secret`/`private credentials`/`raw authorization data`. Staff sees operational payment info as per `§18.11`; provider secrets remain `INTERNAL`.
- **Order vs Payment state:** Related but distinct. `Order PAID` means payment confirmed by backend via Group H webhook verification; `Order ACCEPTED` means staff accepted operationally — not automatically `ACCEPTED` when `PAID`. Do not equate `Order.status = Payment.status`.

### 24.12 Order Status — Closed Enum (Authoritative Lifecycle)

Version 1 statuses are **CLOSED** (adding new value is breaking compatibility):

```
PENDING_PAYMENT, PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP, SHIPPED, DELIVERED, COMPLETED, CANCELLED
```

- `CANCELLED` is approved as terminal cancellation status (customer `20-min window` or explicit admin cancellation). Not every order uses every status.
- `CANCELLED` is set only via controlled `POST .../cancel` action, not via generic `PATCH {status:"CANCELLED"}`.
- Every status value is machine `UPPER_SNAKE_CASE` (`READY_FOR_PICKUP`), frontend maps to display.

### 24.13 State Machine (Normative) — Server-Controlled, Fulfillment-Aware

| Current State | Action (POST) | Actor | Fulfillment | Preconditions (in addition to auth) | Next State |
|---|---|---|---|---|---|
| `PENDING_PAYMENT` | `payment success` (System / Group H webhook) | System | Any | `delivery_fee_status=FINALIZED`, payment verified, idempotency, no cancelled | `PAID` |
| `PENDING_PAYMENT` | `cancel` (`ORD-004`) | Customer (own) | Any | `owns + within 20-min + state cancellable + backend time` | `CANCELLED` |
| `PAID` | `accept` (`ORD-007`) | Staff/Admin `orders.accept` | Any | — | `ACCEPTED` |
| `ACCEPTED` | `process` (`ORD-008`) | Staff/Admin `orders.process` | Any | — | `PROCESSING` |
| `PROCESSING` | `ready-for-pickup` (`ORD-009`) | Staff/Admin `orders.ready_for_pickup` | `PICKUP` only | — | `READY_FOR_PICKUP` |
| `PROCESSING` | `ship` (`ORD-010`) | Staff/Admin `orders.ship` | `DELIVERY` only | — | `SHIPPED` |
| `SHIPPED` | `deliver` (`ORD-011`) | Staff/Admin `orders.deliver` | `DELIVERY` only | — | `DELIVERED` |
| `READY_FOR_PICKUP` | `complete` (`ORD-013`) | Staff/Admin `orders.complete` | `PICKUP` only | — | `COMPLETED` |
| `DELIVERED` | `complete` (`ORD-013`) | Staff/Admin `orders.complete` | `DELIVERY` only | — | `COMPLETED` |
| `PENDING_PAYMENT` | `cancel` (Admin: `POST /api/v1/orders/{order}/cancel` + `orders.manage` / System: TTL expiry via Group H / System) | Admin `orders.manage` / System (Group H, TTL) | Any | Idempotent (Admin `Idempotency-Key` / System event-id), atomic `reserved_quantity` release + `available_quantity` restore + `order_status_history` append; use `CANCELLED` (not `EXPIRED`); audit, not customer 20-min window | `CANCELLED` |

**Pickup branch:** `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` (no `SHIPPED`/`DELIVERED`).

**Delivery branch:** `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED`.

**Invalid (rejected `409`/`422`):** `PAID→COMPLETED`, `COMPLETED→PROCESSING`, `DELIVERED→SHIPPED`, `PICKUP→SHIPPED`, `DELIVERY→READY_FOR_PICKUP`, any `PATCH {status}` without action, any transition on `COMPLETED`/`CANCELLED` (terminal), any `PICKUP order → ship`.

All transitions evaluate `authenticated actor + permission + current state + fulfillment_type + business preconditions` (e.g., `delivery_fee FINALIZED` before `PAID`) atomically; state validation inside transaction.

### 24.14 Mutability & Historical Data Matrix

| Order field | Mutable after creation? | Customer | Staff | Admin |
|---|---|---|---|---|
| `order_reference` | **No** | Read | Read | Read |
| `customer owner` | **No** | Read (own) | Operational | Authorized |
| `item quantity` | **No** | Read | Read | Controlled correction only (audit, explicit workflow) |
| `historical unit_price` | **No** | Read | Read | Controlled correction only |
| `line_total` | **No** | Read | Read | Controlled correction only |
| `order created_at` | **No** | Read | Read | Read |
| `fulfillment_type` | **Generally No** | Read | Operational | Controlled (rejected for cross-branch change) |
| `delivery_address snapshot` | **Generally No** | Read (own) | Operational | Authorized |
| `delivery_fee` | `PENDING→FINALIZED` once before `PAID`; thereafter controlled correction only, not unrestricted | Read (own) | Authorized (`orders.set_delivery_fee` only, audit; `inventory.manage` explicitly excluded) | Authorized (audit; `inventory.manage` does not grant) |
| `delivery_fee_status` | `PENDING→FINALIZED` | Read | Read/Authorized (fee setter) | Authorized |
| `total` | Derived, finalized with fee (see §24.11) | Read | Operational | Authorized |
| `status` | **Action-controlled only** | Read | Action (`accept/process/ship/deliver/complete`) | Action (+ admin cancel) |
| `status_history` | **Append-only** | Read | Read / append via actions | Read / append via actions |

Historical `OrderItems`, `snapshot addresses`, `subtotal/total/delivery_fee` once `FINALIZED` are not patched via generic `PATCH /orders/{id}` for all fields.

### 24.15 Collection Sorting, Pagination & Filtering

- **Pagination:** `ORD-001` (customer) and `ORD-005` (staff) both use `page`/`per_page` → `meta.pagination` (`current_page, per_page, total, last_page, has_next, has_previous`) per `§4`.
- **Customer sorting:** `created_at DESC, id ASC` (newest first) default; deterministic tie-breaker `id ASC` — documented; `sort` allow-list only (`created_at`, `total`, `order_reference` — `id` as tie-breaker, not arbitrary DB column).
- **Customer filters (allow-list):** `order_status` (`?order_status=PROCESSING` CLOSED, not `?status`), `fulfillment_type` (`PICKUP`/`DELIVERY`), `created_from`/`created_to` ISO8601. Staff operational lists add `customer reference`, `order_reference` search as authorized.
- **Performance:** Later implementation considers `indexes (customer_id, created_at, status, fulfillment_type)`, pagination, efficient joins, minimal N+1 — not designed here.

### 24.16 Tracking & Status History

- **Status History (audit):** `Order → StatusHistory 1:N`, append-only, generated by actions. Fields: `status` (CLOSED), `occurred_at` ISO8601 `Z`, `actor` / `context` (`customer:123`, `staff:45`, `system`), `note` where appropriate (e.g., `Ready for pickup — aisle 3`). Customer can view relevant history; cannot create/edit/delete; staff notes not exposed via customer representation.
- **Tracking (customer-facing):** Derived from status history but not identical. `GET /me/orders/{order}/tracking` (`ORD-003`) returns customer progress milestones (not GPS). For `PICKUP`: milestones up to `READY_FOR_PICKUP`; for `DELIVERY`: up to `DELIVERED` → `COMPLETED`. Staff tracking (`ORD-012`) is operational view (richer, includes internal notes where authorized).
- **Separation:** `Status History = audit sequence` vs `Tracking = customer progress representation`; both derive from same underlying history.

### 24.17 Cancellation — Customer & Admin

- **Customer (`ORD-004` `POST /me/orders/{order}/cancel`):**
  - Conditions: `authenticated customer + owns Order + within 20 minutes from authoritative Order creation (`order.created_at` backend time) + Order state permits cancellation (`PENDING_PAYMENT` cancellable; `PAID`/`ACCEPTED`+ not cancellable)`.
  - Server determines `current_time`, `order_created_at`, `elapsed = current_time - created_at`; client `cancelled_at` is rejected.
  - Input: none or minimal `{"reason":"..."}` optional; server sets `cancelled_at`, resulting state `CANCELLED`, status history entry.
  - Errors: `401 AUTHENTICATION_REQUIRED` if not authed, `404 ORDER_NOT_FOUND` (masked) if not own, `422/409 ORDER_NOT_CANCELLABLE` if window expired or state ineligible (`ORDER_NOT_CANCELLABLE` preferred over generic `BAD_REQUEST` per `§15`), `409 ORDER_STATE_CONFLICT` if raced with staff accept.
  - Financial consequence: cancellation does not automatically imply refund — handled per business (see `business-rules.md §8`).

- **Staff:** Not automatically given customer cancellation; no `STAFF → cancel any order` via same endpoint. If operational cancellation needed, explicit admin/operational action with authority.

- **Admin:** Broader cancellation override must be explicitly defined (audit, not `DELETE /orders/{id}` generic; orders are never hard-deleted, history remains).

### 24.18 Idempotency — Critical Order Actions

Customer `POST /me/orders/{order}/cancel` and staff `POST .../accept|process|ready-for-pickup|ship|deliver|complete` are **IDEMPOTENCY_REQUIRED** (`Idempotency-Key`).

- First `accept` with `K123 → 200 ACCEPTED`; retry `K123` → replay `200 ACCEPTED`, no second `ACCEPTED` history event.
- Cancel with `K1`: `first → CANCELLED`; second `K1` → deterministic replay `CANCELLED` (no duplicate side effects); if later `CANCELLED→ACCEPTED` attempted, `409 INVALID_ORDER_TRANSITION`.
- Implementation uses durable `Idempotency-Key` store with transaction (as for `CHK-001`).

### 24.19 Concurrency — Critical Races

Must be protected transactionally (state read + validation + transition inside atomic boundary):

- `Customer cancel + Staff accept` same `PENDING_PAYMENT order` → one succeeds, other gets `409 ORDER_STATE_CONFLICT` (must re-read).
- `Staff accept + Staff accept` (duplicate click) → idempotency replay or `409`.
- `Staff ship + retry ship` → no duplicate `SHIPPED` event.
- `Delivery fee SET + Payment PAID` race — fee finalization inside same transaction boundary as `PENDING_PAYMENT→PAID` check ensures payment never on provisional total.
- `Admin vs Staff` simultaneous transition — same atomic check.

### 24.20 Order and Inventory

Order represents what was purchased; inventory is separate operational resource (`INV-001..003`).

- Order Item `quantity` is historical, not live inventory quantity; current stock not stored inside Order Items.
- Inventory mutation (`INV-003 adjust`) is staff `inventory.manage` audited and does not rewrite historical Order price.

### 24.21 Authorization Matrix (Normative)

| Operation | Customer | Staff | Admin |
|---|---|---|---|
| View own Orders (`ORD-001`, `ORD-002`) | **Yes** (`AUTHENTICATED_OWNER` own) | No as owner (operational view via `ORD-005/006`) | Authorized (but purpose-bound) |
| View operational Orders (`ORD-005/006`) | No | **Yes** (`orders.view_operational`) | Yes |
| Tracking own order (`ORD-003`) | **Yes** (own) | — | — |
| Tracking operational (`ORD-012`) | — | **Yes** (operational) | Yes |
| Cancel own eligible Order (`ORD-004`) | **Yes** (`owns + 20-min + cancellable state + backend time`) | No (customer action) | Authorized only if explicitly defined (admin cancel) |
| Accept `PAID→ACCEPTED` (`ORD-007`) | No | **Yes** (`orders.accept` + `PAID` + `FINALIZED`) | Yes |
| Process `ACCEPTED→PROCESSING` (`ORD-008`) | No | **Yes** (`orders.process`) | Yes |
| Ready for Pickup `PROCESSING→READY_FOR_PICKUP` (`ORD-009`) | No | **Yes** (`orders.ready_for_pickup` + `PICKUP`) | Yes |
| Ship `PROCESSING→SHIPPED` (`ORD-010`) | No | **Yes** (`orders.ship` + `DELIVERY`) | Yes |
| Deliver `SHIPPED→DELIVERED` (`ORD-011`) | No | **Yes** (`orders.deliver` + `DELIVERY`) | Yes |
| Complete `→COMPLETED` (`ORD-013`) | No | According to policy (`orders.complete` + `READY_FOR_PICKUP|DELIVERED`) | Yes |
| Finalize delivery fee `PENDING→FINALIZED` before payment (`ORD-014`) | No | **Yes** (`orders.set_delivery_fee` + `PENDING_PAYMENT` + `DELIVERY` + `PENDING`) | Yes (where approved) |
| Modify historical item price | No | No | Controlled correction only (audit) |
| Change Order owner | No | No | Controlled admin workflow only (audit) |

Staff view does not grant `change password / restrict account / self-approve / escalation`; admin does not get `PATCH {status: anything}` without lifecycle validation (see `§18`).

### 24.22 Historical Data Access Matrix

| Role | Order reference | Items & historical prices | Delivery address (own/operational) | Payment summary (limited) | Internal notes | Inventory internals | Credentials |
|---|---|---|---|---|---|---|---|
| Customer | Yes | Yes (own) | Own | Limited (own `payment_status`, `amount`) | No | No | No |
| Staff | Yes | Yes | Operational | Operational (as authorized) | Yes if authorized | As required | No |
| Admin | Yes | Yes | Authorized | Authorized | Yes | Yes | No |

Private (`password`, `payment secret`, `auth token`) never.

### 24.23 Caching, Privacy & Performance

- **Privacy:** Customer Order reads (`ORD-001`..`ORD-004`) contain sensitive PII (delivery address, phone, totals) and financial data — `Cache-Control: private, no-store` / `PRIVATE`; `Staff/Admin` operational data `PRIVATE/INTERNAL`; do not publicly CDN-cache Orders.
- **Performance:** Customer `GET /me/orders` must support growth; implementation considers `indexes (customer_id, created_at, status, fulfillment_type)`, pagination, stable ordering `created_at DESC, id ASC`, minimal joins, avoid N+1 for `items + payment + tracking` (detail). Not optimized here.
- **Security:** Authorization before serialization; enumeration protection `404` masking for not-own order.

### 24.24 Errors — Common Error Contract (`data` vs `errors`)

- Uses `{"data":…}` success and `{"errors":[…],"meta":{"request_id":…}}` failure per `§15` — never custom `order_error` envelope.
- Potential codes (CLOSED per `§15.15`, business meaning per `business-rules.md §14`): `ORDER_NOT_FOUND` (`404` masked), `ORDER_NOT_CANCELLABLE` (`422/409` — window expired or state ineligible, preferred over `BAD_REQUEST`), `INVALID_ORDER_TRANSITION` (`409` — `PAID→COMPLETED` etc.), `ORDER_STATE_CONFLICT` (`409` — concurrent race), `INVALID_VALUE`/`INVALID_TYPE` for bad filter, `AUTHENTICATION_REQUIRED` (`401`), `FORBIDDEN` (`403`), `RATE_LIMITED` (`429`), `DELIVERY_FEE_PENDING` (`409` — payment before fee finalized). Add only needed codes per registry.

### 24.25 Compatibility — Version 1

Within `v1`, do not change without compatibility review: `status field meaning`, `order_reference semantics`, `historical price semantics`, `fulfillment type meaning`, `money structure {amount,currency}`, `ownership semantics`, `pagination/sort/filter` shapes. New status is breaking (`api-versioning-strategy.md`).

### 24.26 Order Response Examples

**Customer Order Summary (collection `ORD-001`):**

```json
{
  "data": [
    {
      "id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
      "order_reference": "OD-2026-00123",
      "status": "PROCESSING",
      "fulfillment_type": "DELIVERY",
      "delivery_fee_status": "FINALIZED",
      "subtotal": { "amount": 170000000, "currency": "TZS" },
      "delivery_fee": { "amount": 2500000, "currency": "TZS" },
      "total": { "amount": 172500000, "currency": "TZS" },
      "currency": "TZS",
      "created_at": "2026-09-01T10:15:00Z",
      "updated_at": "2026-09-01T12:00:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 12, "last_page": 1, "has_next": false, "has_previous": false }
  }
}
```

**Customer Order Detail — DELIVERY finalized (`ORD-002`):**

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
    "order_reference": "OD-2026-00123",
    "status": "PROCESSING",
    "fulfillment_type": "DELIVERY",
    "delivery_fee_status": "FINALIZED",
    "items": [
      {
        "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
        "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
        "sku": "SOFA-MOD-3S-GRY",
        "name": "Modern 3-Seater Fabric Sofa",
        "variant_name": "Charcoal Grey",
        "unit_price": { "amount": 125000000, "currency": "TZS" },
        "quantity": 1,
        "line_total": { "amount": 125000000, "currency": "TZS" }
      },
      {
        "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t2",
        "variant_id": null,
        "sku": "TABLE-OAK-COFFEE",
        "name": "Oak Coffee Table",
        "variant_name": null,
        "unit_price": { "amount": 45000000, "currency": "TZS" },
        "quantity": 1,
        "line_total": { "amount": 45000000, "currency": "TZS" }
      }
    ],
    "subtotal": { "amount": 170000000, "currency": "TZS" },
    "delivery_fee": { "amount": 2500000, "currency": "TZS" },
    "total": { "amount": 172500000, "currency": "TZS" },
    "currency": "TZS",
    "delivery_address": {
      "recipient_name": "Asha Mwangi",
      "phone": "+255700000001",
      "address_line": "Block C, Mikocheni B, Dar es Salaam",
      "city": "Dar es Salaam"
    },
    "payment": {
      "payment_status": "PAID",
      "amount": { "amount": 172500000, "currency": "TZS" }
    },
    "created_at": "2026-09-01T10:15:00Z",
    "updated_at": "2026-09-01T12:00:00Z"
  }
}
```

**PICKUP pending fee not applicable** — `delivery_fee_status: FINALIZED` at creation, `payment` present immediately when `PAID`. Completed Orders remain readable (`COMPLETED` still belongs to customer).

### 24.27 Delivery Fee + Order + Payment Critical Gate (Consistency)

```
Checkout (CHK-001)
  → Order PENDING_PAYMENT (delivery_fee=null/PENDING for DELIVERY, 0/FINALIZED for PICKUP)
  → Staff/Admin sets delivery_fee (DELIVERY) → FINALIZED → final total authoritative
  → Payment (Group H) verifies amount == final Order total (PAY-001 blocked 409 while PENDING)
  → Order PAID → Staff workflow (ACCEPTED→...→COMPLETED)
```

There is never ambiguity about `What exact amount did the customer agree to/pay for this Order?` — the Order's `total` when `delivery_fee_status=FINALIZED` is the authoritative transaction amount; financial finality before `PAID` is normative.

### 24.28 Cross-Phase Consistency Checks

- **Catalog vs Order:** `Current Product (price/name/image)` ≠ `Historical OrderItem (snapshot)` — catalog change does not rewrite history (see §24.8).
- **Cart vs Order:** `Cart intent (informational pricing, no reservation)` → `Order transaction (authoritative pricing, inventory locked)` — checkout revalidates before Order.
- **Checkout vs Order:** `CHK-001 → creates Order PENDING_PAYMENT` — no direct `POST /orders` for customers.
- **Auth vs Order:** `Order ownership → authenticated customer`; `Customer A → Customer B order` fails 404 masked.
- **Authz vs Order:** `Customer → own Order`, `Staff → operational Order (view/process)`, `Admin → administrative Order` — staff does not gain account control.
- **Error vs Order:** `ORDER_NOT_FOUND/ORDER_NOT_CANCELLABLE/INVALID_ORDER_TRANSITION/ORDER_STATE_CONFLICT` via common `errors` envelope, not custom.

### 24.29 Required Security, Financial & Concurrency Tests (Future Implementation)

Future automated tests must verify (as API/feature tests, not UI-only):

- **Security:** `Customer A → Customer B Order 404`, `Customer A → Customer B tracking 404`, `Customer PATCH {status:COMPLETED} rejected 409`, `Customer POST {total:1} not authoritative`, `Customer POST {delivery_fee:0} rejected`, `Customer POST {customer_id:another} rejected`, `Customer cancel after 20min 422`, `Customer cancel COMPLETED 422`, `Staff view order without permission 403`, `Staff self-approve escalation 403`.
- **Financial:** `historical price remains stable after catalog price change`, `subtotal correct`, `delivery_fee authoritative (null→finalized, 0 for PICKUP)`, `total = subtotal + delivery_fee when FINALIZED`, `customer cannot override total (rejected)`, `payment amount == final Order total (Group H)`.
- **Concurrency:** `Customer cancel + Staff accept race → one 409`, `Staff accept + Staff accept race → idempotent replay`, `Staff ship + retry → no duplicate`, `Delivery fee SET + Payment race → payment blocked until finalized`, `Admin vs Staff transition → atomic`.
- **Client workflows:** `Login → View own order → Track → Cancel within 20min` (customer), `Receive order → Accept → Process → Ship/ReadyForPickup → Deliver → Complete` (staff).

### 24.30 Cross-References

- Resources: `api-resources.md §3` (Order, OrderItem, Fulfillment, Tracking, StatusHistory — historical/field-level, mutable/immutable, representation levels).
- Conventions: `api-conventions.md §24` (state transitions, historical snapshots, immutable financial data, ownership checks, controlled actions, private caching, concurrency).
- Domain: `business-rules.md §6` (Orders belong to Customer, created via Checkout, reference server-generated, history preserved, 20-min cancellation, pickup/delivery branches, variable delivery fee, staff/admin authority).
- Decisions: `decisions.md ORD-001..ORD-009` (customer-owned historical records, ownership not client-supplied, historical snapshots, 20-min window, controlled transitions, fee not customer-controlled, distinct pickup/delivery paths, private data, Group H payment).

---

## 25. Order Tracking and Fulfillment Contract (Phase 1.24)

> **Authority:** Narrow contract for **fulfillment progress** (`PICKUP` / `DELIVERY`) and **customer tracking** built on finalized Order contract `§24`. Consolidates `phases/phase-1.24.md`. Tracking is `REST GET` polling, no WebSockets/GPS, no courier integration; fulfillment actions are `Idempotency-Required`, `Critical` concurrency, `CLOSED` enums.

### 25.1 Core Principle — Tracking Read vs Fulfillment Write

```
Customer Tracking → read-only view (GET /me/orders/{order}/tracking, ORD-003)
Staff Fulfillment → authorized state-changing POST (ORD-009 / ORD-010 / ORD-011 / ORD-013 + ORD-014 fee)
```

`Customer can see progress but cannot change it`; `Staff can change progress but cannot rewrite history`.

### 25.2 Fulfillment vs Order vs Tracking

- **Order:** commercial transaction (`PENDING_PAYMENT`→`COMPLETED`, financials, items).
- **Fulfillment:** how goods reach/collect (`PICKUP` free, `DELIVERY` fee via `ORD-014` → `SHIPPED`→`DELIVERED`).
- **Tracking:** customer-friendly `timeline` derived from `order_status_history` (read-only, not independent state machine).

They are related but not one giant object; Order detail contains fulfillment summary, tracking is separate lightweight view.

### 25.3 Fulfillment Types — Closed

Version 1 `fulfillment_type` CLOSED `PICKUP` / `DELIVERY` (selected at `CHK-001`, stored in Order, generally immutable). `PICKUP` requires `READY_FOR_PICKUP` before `COMPLETED`; `DELIVERY` requires `SHIPPED→DELIVERED` before `COMPLETED`. Cross-branch `PICKUP→SHIPPED` or `DELIVERY→READY_FOR_PICKUP` is rejected `409 INVALID_ORDER_TRANSITION` unless explicitly designed.

### 25.4 Pickup Fulfillment Flow

```
Order PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP → COMPLETED
```

- `PROCESSING→READY_FOR_PICKUP` via `ORD-009 POST /orders/{order}/ready-for-pickup` (Staff/Admin `orders.ready_for_pickup` + `PICKUP` + `PROCESSING`).
- `READY_FOR_PICKUP→COMPLETED` via `ORD-013 POST /orders/{order}/complete` (explicit, Staff/Admin `orders.complete` + `READY_FOR_PICKUP`; not automatic customer-collect without Staff confirmation).
- No `SHIPPED/DELIVERED` for pickup. Single pickup location (V1: one business location, configurable later — not multi-warehouse, recorded as dependency, not invented).

### 25.5 Delivery Fulfillment Flow

```
Order PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
```

- `PROCESSING→SHIPPED` via `ORD-010 POST /orders/{order}/ship` (Staff/Admin `orders.ship` + `DELIVERY` + `PROCESSING`).
- `SHIPPED→DELIVERED` via `ORD-011 POST /orders/{order}/deliver` (Staff/Admin `orders.deliver` + `DELIVERY` + `SHIPPED`).
- `DELIVERED→COMPLETED` via `ORD-013` (same).
- No `READY_FOR_PICKUP` for delivery unless explicitly designed.

### 25.6 Tracking Endpoint — Customer

| Attribute | Contract |
|---|---|
| **ID** | `ORD-003` (canonical; `ORD-TRK-001` conceptual alias in Phase 1.24 maps to `ORD-003`) |
| **Method/Path** | `GET /api/v1/me/orders/{order}/tracking` |
| **Auth** | Required |
| **Authz** | `AUTHENTICATED_OWNER` owns order (404 masked) |
| **Purpose** | Customer-facing fulfillment progress — `current fulfillment state + milestones + next expected step + pickup/delivery mode` |
| **Cache** | `private, no-store` (PII + financial, not CDN) |
| **Pagination** | No (timeline short, append-only; paginate only if history grows large, then `page/per_page` per `§4`) |
| **Success** | `200 {"data": {order_reference, fulfillment_type, current_status, timeline: [TimelineEvent...]}}` per `§25.7` |

Staff/operational tracking is `ORD-012 GET /api/v1/orders/{order}/tracking` (`OPERATIONAL orders.view_operational`) — richer (`actor`, `note`) as authorized, not customer data.

### 25.7 Tracking Response — Timeline Representation

Conceptual (`ORD-003`):

```json
{
  "data": {
    "order_reference": "OD-2026-00123",
    "fulfillment_type": "DELIVERY",
    "current_status": "SHIPPED",
    "delivery_fee_status": "FINALIZED",
    "timeline": [
      { "id": "evt_01h...", "status": "PENDING_PAYMENT", "occurred_at": "2026-09-01T10:15:00Z", "label": "Order received" },
      { "id": "evt_01h...", "status": "PAID", "occurred_at": "2026-09-01T10:20:00Z", "label": "Payment confirmed" },
      { "id": "evt_01h...", "status": "ACCEPTED", "occurred_at": "2026-09-01T10:25:00Z", "label": "Order accepted" },
      { "id": "evt_01h...", "status": "PROCESSING", "occurred_at": "2026-09-01T11:00:00Z", "label": "Being prepared" },
      { "id": "evt_01h...", "status": "SHIPPED", "occurred_at": "2026-09-01T14:00:00Z", "label": "Shipped" }
    ]
  }
}
```

### 25.8 Tracking Is Read-Only & Derived From Order State

- `GET` only. No `POST/PATCH/DELETE /orders/{order}/tracking` for customers. Timeline entries are **not** created by clients; they are generated by valid business actions (`accept/process/ship/deliver/complete`).
- **Consistency:** `current_status` in tracking `==` authoritative `Order.status` (`§24.13`); `Order=PROCESSING` with `Tracking=DELIVERED` is impossible (`409` if drifted). Tracking derives from `order_status_history`, not independent state machine.
- **Status History vs Timeline:** `Status History = authoritative historical events (order_status_history: status, occurred_at, actor/context, note)` → `Tracking Timeline = customer-friendly filtered view` (same underlying records, but timeline shows only milestones relevant to fulfillment fulfillment_type, with `label` for UI). Staff may see richer `actor/note` where authorized.

### 25.9 Customer-Visible Timeline Is Filtered

Not every internal event must be shown. Example filtered view: `PAID → ACCEPTED → PROCESSING → SHIPPED` (customer sees `Order accepted / Being prepared / Shipped`); internal `delivery_fee FINALIZED` event is not a visible timeline status (financial flag, not fulfillment milestone) but explains why `total` became final. Staff may see additional operational info (internal note) not serialized to customer.

### 25.10 Fulfillment Actions — Controlled, State & Fulfillment-Type Aware

| ID | Method | Path | Actor | Authz | Fulfillment | Current → Next | Idempotency | Concurrency |
|---|---|---|---|---|---|---|---|---|
| `ORD-009` (`ORD-FUL-001`) | `POST` | `/api/v1/orders/{order}/ready-for-pickup` | Staff/Admin | `orders.ready_for_pickup` + `PROCESSING` + `PICKUP` | `PICKUP` | `PROCESSING→READY_FOR_PICKUP` | Required (`Idempotency-Key`) | Critical |
| `ORD-010` (`ORD-FUL-002`) | `POST` | `/api/v1/orders/{order}/ship` | Staff/Admin | `orders.ship` + `PROCESSING` + `DELIVERY` | `DELIVERY` | `PROCESSING→SHIPPED` | Required | Critical |
| `ORD-011` (`ORD-FUL-003`) | `POST` | `/api/v1/orders/{order}/deliver` | Staff/Admin | `orders.deliver` + `SHIPPED` + `DELIVERY` | `DELIVERY` | `SHIPPED→DELIVERED` | Required | Critical |
| `ORD-013` (complete) | `POST` | `/api/v1/orders/{order}/complete` | Staff/Admin | `orders.complete` + `READY_FOR_PICKUP|DELIVERED` | `PICKUP|DELIVERY` | `READY_FOR_PICKUP→COMPLETED` / `DELIVERED→COMPLETED` | Required | Critical |

`ORD-007 accept` + `ORD-008 process` remain under `§24.13` (not duplicated here unless delegated; this phase focuses on physical fulfillment branch `READY_FOR_PICKUP / SHIPPED / DELIVERED`). Fee `ORD-014` is `§24.10` (financial, not fulfillment). `PATCH {status:"SHIPPED"}` is rejected `409`.

### 25.11 Complete — Explicit

`COMPLETED` requires explicit `ORD-013` Staff/Admin action for both `PICKUP` (`READY_FOR_PICKUP→COMPLETED` after customer collects) and `DELIVERY` (`DELIVERED→COMPLETED` after delivery confirmed). Not automatic on `DELIVERED` alone; documents who confirmed completion. `DELIVERED→COMPLETED` and `READY_FOR_PICKUP→COMPLETED` only.

### 25.12 Customer Cannot Mark Delivered / Ready

- Customer `POST {status:"DELIVERED"}` / `POST {status:"READY_FOR_PICKUP"}` rejected `403 FORBIDDEN` / `409 INVALID_ORDER_TRANSITION` (not `order owner`, needs `orders.deliver` / `orders.ready_for_pickup`). Fulfillment is operational event; only Staff/Admin can change `PROCESSING→READY_FOR_PICKUP|SHIPPED` and `SHIPPED→DELIVERED`.

### 25.13 Authorization — Tracking vs Fulfillment

| Operation | Customer | Staff | Admin |
|---|---|---|---|
| View own tracking `ORD-003` | **Yes** (`owns`) | — | — |
| View operational tracking `ORD-012` | — | **Yes** (`orders.view_operational`) | Yes |
| Mark ready `ORD-009` | No | **Yes** (`ready_for_pickup` + `PICKUP`) | Yes |
| Ship `ORD-010` | No | **Yes** (`ship` + `DELIVERY`) | Yes |
| Deliver `ORD-011` | No | **Yes** (`deliver` + `DELIVERY`) | Yes |
| Complete `ORD-013` | No | Per policy (`complete`) | Yes |
| Modify historical tracking | No | No | No (controlled correction only, audit) |

Staff access to tracking does not grant `change password / block customer / access credentials` (see `§18`). `Customer A → Customer B tracking` fails `404 ORDER_NOT_FOUND` (masked, never `403`).

### 25.14 Timeline Conventions — Deterministic, Immutable, Privacy-Safe

- **Timestamps:** Global `ISO8601 UTC Z` (`2026-09-01T09:45:00Z`) per `§3.4` — never mixed `30/08/2026` or Unix.
- **Ordering:** `chronological ascending` (`PAID → ACCEPTED → PROCESSING → SHIPPED → DELIVERED`) for customer display; deterministic `occurred_at ASC, id ASC`.
- **Event identity:** Stable `id` (`evt_...`) per `order_status_history` record — reuse history `id`, not new UUID for tracking view; customer sees `id` for stable React keys, not internal DB key exposure beyond `evt_` opaque.
- **Immutability:** Historical events **append-only**; customer cannot edit; staff cannot rewrite past status history (`ship` retry does not create duplicate `SHIPPED` event — `Idempotency-Key` replay). Correction requires explicit admin workflow (audit `actor, order, old, new, reason, occurred_at`).
- **Actor privacy:** Customer timeline shows `label` (`"Your order has been shipped."`), not `John Smith shipped`; staff operational timeline may show `actor (staff:45)` where authorized, per minimal exposure.
- **Customer vs internal notes:** `customer-visible note` explicitly flagged; `internal note` (Staff operational) never serialized to `ORD-003` (only `ORD-012` if authorized).

### 25.15 Delivery / Pickup Information in Tracking

- **Delivery:** May include `delivery_address` summary (already in Order detail) + `delivery status`/`shipping milestone`; not `tracking_number/carrier/tracking_url` (no external courier integration in V1 — not invented), not `real-time GPS/vehicle location`, not `carrier integration`.
- **Pickup:** `pickup status` (`READY_FOR_PICKUP` / `COMPLETED`), `pickup location` (V1 single business location, configurable later — not multi-warehouse), `collection instructions` (approved business copy) — not multi-location system invented.
- **Privacy:** `delivery_address` (private) visible only to `owns` customer, authorized Staff/Admin (as in `§24.9`); not public.

### 25.16 Fulfillment Does Not Own Financials, Payment, or Inventory

- Tracking does **not** recalculate `delivery_fee` or `total`; it displays `Order.delivery_fee/total` authoritative from `§24.11` (financial remains in Order). 
- `PAY-001` blocked `409 DELIVERY_FEE_PENDING` while `delivery_fee_status=PENDING` — tracking must not show Order as financially settled until `§24.27` gate (`FINALIZED→PAID`) says so.
- Fulfillment actions (`ship/deliver`) must not change `historical unit_price`/`line_total`/`subtotal` or `billing_address` snapshot; only state. Inventory handling corresponds to Order but tracking does not expose `reserved_quantity` / adjust inventory (see `INV-003` separate).

### 25.17 Recommended V1 Tracking Model — Polling

`REST GET tracking + client refresh/polling` — no `WebSockets`, `Server-Sent Events`, `push` required in V1. Client `GET /me/orders/{order}/tracking` on open + on pull-to-refresh / visibility-change; avoid aggressive polling (contract does not require `WebSocket` nor live GPS). Notifications (`Group R`) may later consume fulfillment events (`ready/shipped/delivered`) but tracking does not depend on notification persistence.

### 25.18 Required State/Fulfillment Matrix (Normative)

| Fulfillment | State | Customer | Staff/Admin |
|---|---|---|---|
| `PICKUP` | `PROCESSING` | Track (`ORD-003`) — shows `Being prepared` | Process (`ORD-008`) |
| `PICKUP` | `READY_FOR_PICKUP` | Track — shows `Ready for pickup` | Set ready (`ORD-009`) |
| `PICKUP` | `COMPLETED` | Track — shows `Completed` | Complete (`ORD-013`) |
| `DELIVERY` | `PROCESSING` | Track — `Being prepared` | Process (`ORD-008`) |
| `DELIVERY` | `SHIPPED` | Track — `Shipped` | Ship (`ORD-010`) |
| `DELIVERY` | `DELIVERED` | Track — `Delivered` | Mark delivered (`ORD-011`) |
| `DELIVERY` | `COMPLETED` | Track — `Completed` | Complete (`ORD-013`) |

Fulfillment/state combinations outside this table (`PICKUP` + `SHIPPED`) are invalid (`409`).

### 25.19 Required Tracking Endpoint Matrix

| ID | Method | Path | Actor | Auth | Authorization | Purpose |
|---|---|---|---|---|---|---|
| `ORD-003` (`ORD-TRK-001`) | `GET` | `/api/v1/me/orders/{order}/tracking` | Customer | Required | `AUTHENTICATED_OWNER` owns order | View customer tracking (timeline, milestones) |
| `ORD-012` | `GET` | `/api/v1/orders/{order}/tracking` | Staff/Admin | Required | `OPERATIONAL orders.view_operational` | View operational tracking (richer: actor, note) |
| `ORD-009` (`ORD-FUL-001`) | `POST` | `/api/v1/orders/{order}/ready-for-pickup` | Staff/Admin | Required | `OPERATIONAL orders.ready_for_pickup + PICKUP + PROCESSING` | Mark pickup ready |
| `ORD-010` (`ORD-FUL-002`) | `POST` | `/api/v1/orders/{order}/ship` | Staff/Admin | Required | `OPERATIONAL orders.ship + DELIVERY + PROCESSING` | Ship delivery order |
| `ORD-011` (`ORD-FUL-003`) | `POST` | `/api/v1/orders/{order}/deliver` | Staff/Admin | Required | `OPERATIONAL orders.deliver + DELIVERY + SHIPPED` | Mark delivered |
| `ORD-013` | `POST` | `/api/v1/orders/{order}/complete` | Staff/Admin | Required | `OPERATIONAL orders.complete + READY_FOR_PICKUP|DELIVERED` | Complete order |

`ORD-007/008` accept/process remain `§24.13` (not duplicated unless delegated); `ORD-014` fee finalization is `§24.10` financial. Tracking is `GET` only — no `POST/PATCH/DELETE /tracking`.

### 25.20 Endpoint Contract Template (Per Fulfillment/Tracking Endpoint)

Each fulfillment/tracking endpoint documents: `Endpoint ID / Method / Path / Purpose / Actors / Authentication / Authorization / Input (none or minimal Idempotency-Key header) / Validation (CLOSED enums, unknown fields 422) / Business preconditions (actor+permission+current state+fulfillment) / State transition / Response (200 tracking {data} or 200 Order + Location) / Errors (per §25.22) / Idempotency (Required for fulfillment, — for GET) / Concurrency (Critical for fulfillment) / Privacy (PRIVATE) / Caching (private, no-store)`.

### 25.21 Errors — Tracking & Fulfillment (`§15` envelope)

| Endpoint | Potential `code` (CLOSED) | HTTP | Condition |
|---|---|---|---|
| `ORD-003` customer tracking | `AUTHENTICATION_REQUIRED` | 401 | Not authed |
| `ORD-003` not own | `ORDER_NOT_FOUND` / `RESOURCE_NOT_FOUND` (masked) | 404 | Customer A → Customer B tracking → 404 (never 403 leak) |
| `ORD-009` ready-for-pickup | `FORBIDDEN` | 403 | Not `STAFF`/`ADMIN` with `orders.ready_for_pickup` |
| `ORD-010` ship | `FORBIDDEN` | 403 | Not `STAFF`/`ADMIN` with `orders.ship` |
| `ORD-011` deliver | `FORBIDDEN` | 403 | Not `STAFF`/`ADMIN` with `orders.deliver` |
| `ORD-010` ship on `PICKUP` / `ORD-009` ready on `DELIVERY` | `INVALID_ORDER_TRANSITION` + `FULFILLMENT_ACTION_NOT_ALLOWED` (422/409) | 409 | Wrong fulfillment for state |
| `ORD-010` already `SHIPPED` retry with same key | — (replay `200`) | 200 | `Idempotency-Key` replay, not `409` |
| `ORD-010` already `SHIPPED` no key | `ORDER_STATE_CONFLICT` | 409 | Concurrent second ship must re-read |
| Any `PATCH {status:"SHIPPED"}` | `INVALID_ORDER_TRANSITION` | 409 | Controlled actions only (API-VAL-004) |
| Tracking exposing another customer's address | `ORDER_NOT_FOUND` / `RESOURCE_NOT_FOUND` (masked) | 404 | Customer A → Customer B address → 404 masked (never 403 leak), privacy check before serialization |

Only add `FULFILLMENT_ACTION_NOT_ALLOWED` if client benefits from distinguishing `INVALID_ORDER_TRANSITION` (state mismatch) vs fulfillment-type mismatch; otherwise use `INVALID_ORDER_TRANSITION`.

### 25.22 Idempotency & Concurrency

- **Idempotency `Required`:** `ORD-009/010/011/013` fulfillment + `ORD-014` fee + `ORD-004` cancel + `CHK-001`. `GET ORD-003/012` tracking is `SAFE`. Retry `ship` with same `Idempotency-Key → 200` replay original `SHIPPED` (no second `SHIPPED` history event); same key with different body (`ship` vs `deliver`) → `409 DUPLICATE_OPERATION`; different keys concurrent `Staff A ship + Staff B ship` → atomic `authorize+validate state+perform` inside transaction → one `200`, other `409 ORDER_STATE_CONFLICT`.
- **Concurrency `Critical`:** `Staff A ship + Staff B ship`, `Staff deliver + Admin complete`, `Customer cancel + Staff ship` (20-min window race) — all require row-level lock / `order_status_history` append inside transaction; failure to lock must not create two `SHIPPED` events or allow `DELIVERED` without `SHIPPED`.

### 25.23 Privacy, Caching & Size

- Customer tracking `ORD-003` is **private** — `Cache-Control: private, no-store`; not public CDN; not `products` catalog cache. Operational `ORD-012` similarly `PRIVATE`.
- Do not embed `entire Order + full customer profile + staff records + payment history` in tracking; keep tracking lightweight (`order_reference, fulfillment_type, current_status, delivery_fee_status, timeline[]`) — size limited, not paginated in V1 (timeline append-only, small; paginate only if history grows `page/per_page` per `§4`).
- Staff see only customer info needed for fulfillment (`recipient, phone, address`) — not unrelated orders/history/credentials.

### 25.24 Operational Review — Staff Without Friction

Once authorized (`orders.ship`), Staff flow `Receive → Accept → Process → Ship/Ready → Deliver → Complete` must not require repetitive `Admin` approval; `Admin` retains `staff approval / role management` (§17–18) separate.

### 25.25 Customer Experience — Tracking Without Staff Contact

Customer `open own Order → see current state → understand progress (label: Processing / Ready for pickup / Shipped / Delivered) → see PICKUP vs DELIVERY mode → see relevant fulfillment info (pickup location instruction or delivery address summary)` — without needing Staff to push state.

### 25.26 Next.js / Flutter Requirements

Same `REST` contract for `Next.js` and `Flutter`: `GET /me/orders/{order}/tracking` returns `current_status + timeline` for `Order timeline, Current status, Pickup readiness, Shipping state, Delivery state`; `Flutter` can `refresh tracking` + `show timeline` + `handle new state` + `show fulfillment instructions` via polling/visibility-change, no Flutter-specific API.

### 25.27 No Real-Time, No GPS, No Courier Integration

V1 tracking = **fulfillment/order milestones** (`PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP, SHIPPED, DELIVERED, COMPLETED`), **not** `real-time vehicle location`, **not** `tracking_number/carrier/tracking_url` (no external carrier — not invented), **not** `assigned_staff/delivery` assignment unless business needs it, **not** `WebSockets` (polling `GET` is starting architecture).

### 25.28 Cross-References

- Order core: `§24` (identity, items, financial finality, state machine, cancellation, payment `Group H`).
- Checkout/Cart/Catalog: `§23` / `§22` / `§21`.
- Resources: `api-resources.md §3.6` (Tracking/StatusHistory — timeline, fulfillment, actor/privacy, ordering/immutability).
- Conventions: `api-conventions.md §25` (timeline ordering `occurred_at ASC, id ASC`, fulfillment-aware actions, privacy `PRIVATE`, append-only history, idempotency, concurrency).
- Domain: `business-rules.md §7/§8` (status paths, cancellation, pickup vs delivery, historical data, privacy).
- Decisions: `decisions.md FUL-001..FUL-006` (read-only tracking, controlled fulfillment, distinct paths, derived from Order state, not customer-mutable, no GPS).

---

## 26. Made-to-Order Request API Contract (Phase 1.25)

> **Authority:** Canonical domain API contract for the **Made-to-Order Request** subsystem (`REQ-001`..`REQ-007`). Consolidates `phases/phase-1.25.md` and is consistent with `Catalog` (`§21`), `Cart` (`§22`), `Checkout` (`§23`), `Order` (`§24`), `Authentication` (`§17`), `Authorization` (`§18`), `Validation` (`§14`), `Error` (`§15`).
> **Core Principle:** A Made-to-Order Request is a separate commerce path from normal purchasing — an inquiry/intake mechanism for furniture the business may produce on request. It is **not** an Order, **not** a reservation, **not** a payment, **not** a price guarantee. It supports both existing `MADE_TO_ORDER` catalog product references and custom/general furniture descriptions, with optional attachments, contact-isolated history, and operational staff handling.

### 26.1 Request Endpoint Inventory (Authoritative — aligned with Phase 1.19 `§19.1`)

Request endpoints `REQ-001`..`REQ-005` are **`APPROVED`** (complete V1 Request contract — finalized in Phase 1.25). `REQ-006` (staff update) and `REQ-007` (attachment upload) are **`APPROVED`** where documented. This supersedes the provisional `PROPOSED` label in `§19.1` for the Request domain; `§19.15` remains `PROPOSED` only for domains not yet finalized. Clients may consume `REQ-001`..`REQ-007` as authoritative.

| ID | Method | Path | Actor | Auth | Authorization | Purpose | Idempotency | Concurrency |
|---|---|---|---|---|---|---|---|---|
| `REQ-001` | `POST` | `/api/v1/requests` | Anonymous, Customer | **Optional** | **PUBLIC** submit (validated + anti-abuse) | Submit made-to-order request (anonymous allowed) | **None required** — not idempotent by default (duplicate tap may create second request; see §26.15) | Low |
| `REQ-002` | `GET` | `/api/v1/me/requests` | Customer | Required | `AUTHENTICATED_OWNER` own requests | List own requests (paginated, filtered) | — | — |
| `REQ-003` | `GET` | `/api/v1/me/requests/{request}` | Customer | Required | `AUTHENTICATED_OWNER` owns request (404 masked) | Get own request detail | — | — |
| `REQ-004` | `GET` | `/api/v1/requests` | Staff, Admin | Required | `OPERATIONAL` `requests.view` | List operational requests (staff queue) | — | — |
| `REQ-005` | `GET` | `/api/v1/requests/{request}` | Staff, Admin | Required | `OPERATIONAL` `requests.view` | Get operational request detail | — | — |
| `REQ-006` | `PATCH` | `/api/v1/requests/{request}` | Staff, Admin | Required | `OPERATIONAL` `requests.manage` | Update operational request (limited staff fields, see §26.9) | Designed idempotent | Low/Medium (race `Staff A close + Staff B update`) |
| `REQ-007` | `POST` | `/api/v1/requests/{request}/attachments` | Anonymous* (scoped), Customer, Staff, Admin | **Scoped** | **Scoped upload token** + parent ownership; predictable ID alone insufficient | Upload request attachment — preferred is inline multipart on `REQ-001`; separate `POST` only with scoped token, private to parent | — | — |

`*` `REQ-007` anonymous path requires server-issued upload token returned on `REQ-001` creation (single-use/time-limited); see §26.8.

*Notes:* `GET /requests/{request}` is **not** automatically public; `GET /me/requests` is ownership-scoped. No `GET /requests` for anonymous. Canonical resource is `/requests` (not `/made-to-order-requests`/`/furniture-requests`/`/custom-furniture` — ADR/API-END-004). `REQ-001` is the `PUBLIC` anonymous creation entry point; all other reads are authenticated + authorized.

### 26.2 Endpoint REQ-001 — Submit Made-to-Order Request (Public, Anonymous Allowed)

| Attribute | Version 1 Contract |
|---|---|
| **ID** | `REQ-001` |
| **Method** | `POST` |
| **Path** | `/api/v1/requests` |
| **Domain** | Request (intake, not purchase) |
| **Actor** | `Anonymous` or `Customer` (authenticated) |
| **Authentication** | **Optional** — `Anonymous` allowed with contact; `Customer` where `Authorization: Bearer` present |
| **Authorization** | **PUBLIC** submit — no prior auth; anti-abuse/rate-limit candidate (see §26.15) |
| **Purpose** | Submit a furniture request the business may produce on request; becomes operational intake, not an Order |
| **Request body** | JSON `application/json` preferred; `multipart/form-data` where inline attachment sent (see §26.8) |
| **Response** | `201 Created` `{"data": {Request}}` (customer view or created view) — same envelope `data` |
| **Idempotency** | Not required in V1; duplicate submission creates distinct request (see §26.15); future `Idempotency-Key` optional |
| **Caching** | Not cacheable — `Cache-Control: private, no-store` on error; success is creation, not cache |
| **Payment/Inventory** | Creates **no** Order, **no** Payment, **no** inventory reservation |

**Why optional auth:** Visitor must move directly from `MADE_TO_ORDER` Product page → `Request` without forced registration (`§8`). Anonymous `user_id = null` is valid; authenticated `Request.user_id = authenticated principal` derived server-side, never from `{"user_id":"..."}`.

### 26.3 Input Fields — Allow-List & Server-Controlled (REQ-001)

Canonical `POST /api/v1/requests` JSON body (when no file) or multipart fields (when file):

```json
{
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
  "quantity": 1,
  "name": "Asha Mwangi",
  "phone": "+255700000001",
  "email": "asha@example.com",
  "dimensions": {
    "length": 220,
    "width": 90,
    "height": 75,
    "unit": "cm"
  },
  "material": "walnut, matte finish",
  "color": "natural walnut with charcoal fabric",
  "notes": "Need a 2.2m dining table for 6, rounded corners, delivery to Mikocheni if feasible."
}
```

| Field | Required | Type | Valid Values / Rule | Client Authority | Notes |
|---|---|---|---|---|---|
| `product_id` | **Conditional** | string \| null | Nullable. When supplied must be existing, `is_active:true` and `publicly requestable` with `product_type = MADE_TO_ORDER` — see §26.4. `null` or omitted = custom/general request not tied to catalog product. | Client supplies reference; server validates | Custom “Can you make something like this?” is valid without product |
| `quantity` | **Optional** | integer | `1..100` positive integer when supplied. `0`, `-1`, `1.5`, `"2"` rejected `422 INVALID_VALUE`. When omitted remains `null` (unspecified, not implicitly `1`) — see `api-conventions.md §26.4` and `api-resources.md §5.1`; not authoritative final order quantity | Client | Request quantity is not order quantity, not inventory allocation |
| `name` | **Yes** | string | Trimmed, non-empty, max 120 chars, Unicode safe. Collapses internal whitespace. | Client | Required for **both** Anonymous and Customer — explicit contact data kept self-contained even when authenticated (see §26.5) |
| `phone` | **Conditional** | string | At least one of `phone` or `email` required. `phone` when supplied: normalized E.164-ish, trimmed, max 30 chars | Client | Staff needs reachable contact |
| `email` | **Conditional** | string | At least one of `phone` or `email` required. When supplied: lowercased/trimmed, RFC-ish format, max 255 | Client | `phone+email` both valid |
| `dimensions` | **Optional** | object \| null | Structured only (see §26.6). Keys strictly allow-listed: `length`, `width`, `height`, `unit`. No arbitrary keys. Each dimension: number (`int` or decimal where business allows, `>0`, `<=10000`) → validated as `number` not string. `unit` must be exactly `"cm"` CLOSED (no `centimeter`/`inch` synonyms). `null` or omitted = no dimensions | Client | Free-text dimensions not accepted |
| `material` | **Optional** | string | Free text, trimmed, max 500 chars. Not closed enum (small business flexibility) | Client | `oak`, `walnut` free text |
| `color` | **Optional** | string | Free text, trimmed, max 200 chars. Not closed enum | Client | No color-management system |
| `notes` | **Optional** | string | Free text, trimmed, max 5000 chars, safe handling (no HTML/SQL/code execution). Newlines preserved where meaningful. | Client | Human description |
| `attachment` | **Optional** | file | Via `multipart/form-data` field `attachment` inline on creation (preferred) — see §26.8. Not JSON string | Client | One attachment per creation in V1 |
| `user_id` | **Prohibited** | — | Never client-supplied; derived server-side `user_id = authenticated principal` or `null` | **Server derives** | `{"user_id":"..."}` rejected `422` |
| `request_status` | **Prohibited** | — | Server-controlled `request_status`; if sent rejected `422` (`field: request_status`) | **Server** | See §26.10 |
| `order_id` / `payment_*` / `delivery_fee` | **Prohibited** | — | Request never creates order/payment/fee; if sent rejected | **Server** | See §26.11 |
| `internal_notes` / `staff_internal_notes` | **Prohibited** | — | Customer/anonymous cannot set internal notes | **Staff/Admin** | See §26.9 |
| `created_at` / `updated_at` | **Prohibited** | — | Server-generated ISO8601 `Z` | **Server** | |
| *Unknown fields* | — | — | Strict rejection `422 INVALID_VALUE` + `field` per `§13.14` | — | Catches typos, stale clients |

**Contact requiredness (final policy — resolves phase-1.25 §15 Potential, per ADR/API-REQ-001):** For **both** Anonymous and authenticated Customer, `name` is **required** and at least one of `phone` or `email` is **required** (both may be supplied). There is no path that may omit all contact by falling back to trusted account data; authenticated `user_id` supplements contact for ownership, not replaces it. `name` trimmed non-empty max 120, `phone` normalized max 30, `email` lowercased max 255. This replaces the “Potential” draft in phase-1.25 §15 and copies the accepted decision `docs/decisions.md ADR/API-REQ-001` into the normative input contract.

**Contact rule (normative):** Even when authenticated, `name` + `phone`/`email` (at least one) are **required** in the submission payload to create a self-contained request record. The request's contact snapshot is historical and does not depend on future profile changes (see §26.5). Authenticated `user_id` supplements contact for ownership, not replaces it.

**Product reference policy (final V1 decision — resolves phase-1.25 §26-27, per ADR/API-REQ-011):** For V1, `product_id` is **OPTIONAL (nullable)**. Both modes are allowed: `product_id` referencing an existing `MADE_TO_ORDER` product, and `product_id = null` / omitted for a general/custom furniture request ("Can you make something like this?"). It is **not** `Required` (which would force all requests to originate from catalog) and **not** `Absent` (which would forbid catalog linking). See §26.4, `api-resources.md §5.1/§5.2`, `api-conventions.md §26.3`, `business-rules.md §9 #2` and `decisions.md ADR/API-REQ-011` for mirrored rule.

### 26.4 Product Reference — MADE_TO_ORDER Only

- When `product_id` supplied:
  - `product` must exist and be `is_active: true` and `is_published: true` and **publicly visible**.
  - `product.product_type` must be `MADE_TO_ORDER`. If `IN_STOCK` product is supplied to `REQ-001`, backend validates and returns `409 PRODUCT_NOT_REQUESTABLE` (or `422 INVALID_REQUEST` per registry `§15.15` — this contract chooses `409 PRODUCT_NOT_REQUESTABLE` to distinguish purchasability rule from generic invalid).
  - Request does not price-lock nor delivery-lock product; catalog price change does not rewrite request.
- When `product_id` is `null`/omitted:
  - Custom/general furniture request — valid. Request then depends on `quantity`/`dimensions`/`material`/`color`/`notes`/`attachment` + contact.
  - Backend must distinguish `custom request` vs `product-linked request` for staff handling.
- `product_id` requiredness (**final V1: OPTIONAL, per ADR/API-REQ-011**): **not mandatory** — V1 supports both `product-linked` and `custom` to match `AGENTS.md §4.2` general enquiry separation while keeping requests product-aware. `Required` (all catalog) and `Absent` (no catalog) are explicitly rejected for V1; `Optional (nullable)` is the approved policy.

### 26.5 Anonymous vs Authenticated Submission — Contact & Ownership

- **Anonymous:**
  - `user_id = null` stored. `name` + `phone`/`email` required. Request is private intake; not auto-linked to later account merely because email matches (see §26.13). No anonymous retrieval in V1 (see §26.6).
- **Authenticated Customer:**
  - `Request.user_id = authenticated principal` derived server-side. `name`/`phone`/`email` still required in payload (self-contained record). Customer's current profile `name`/`phone`/`email` may be available as secondary source but request's contact snapshot is authoritative and historical (profile change later does not mutate past request).
  - Request appears in `GET /me/requests` ownership-scoped.

**Trust rule:** Never use `email = customer's email` as ownership proof for anonymous retrieval. Email is contact, not authentication.

### 26.6 Dimensions — Structured, Allow-Listed, Unit Canonical

When `dimensions` is supplied, shape is strictly:

```json
{
  "length": 220,
  "width": 90,
  "height": 75,
  "unit": "cm"
}
```

- Allowed keys: `length`, `width`, `height` (each optional number `>0`, `<=10000`, type `number` not string) + `unit` (required when any dimension present, value exactly `"cm"` CLOSED).
- No arbitrary keys (`depth`/`diameter` unless explicitly approved) — unknown dimension key → `422 INVALID_VALUE` `field: dimensions.depth`.
- `unit` synonyms rejected (`centimeter`, `centimetres`, `in`, `inch` → `422`).
- `dimensions: null` or omitted = no dimensions supplied — not error.

This avoids a huge furniture-configuration DSL while giving Staff measurable intent.

### 26.7 Material, Color, Notes — Free Text, Bounded

- `material` / `color` are **free text** (not closed enums) to avoid restricting small-business vocabulary. Incomplete enum would block customers. V1 enums for status remain CLOSED but `material`/`color` are intentionally open strings.
- `notes` max `5000` chars; server trims, does not execute/interpret as code/HTML/SQL/file-path/query syntax.
- Server must safely handle Unicode, not mangle.

### 26.8 Attachments — Optional, Inline Multipart Preferred, Private

- **Optional:** Request may have `0` or `1` attachment on creation in V1 (multiple attachments deferred).
- **Preferred transport:** `multipart/form-data` inline with `REQ-001` creation (field `attachment`). Do not invent pre-upload-then-attach-file flow unless approved; single-request flow is simpler for Laravel/Next.js/Flutter.
- **Separate upload (`REQ-007`):** `POST /api/v1/requests/{request}/attachments` with `multipart/form-data` field `attachment`. Requires **scoped server-issued upload token** (single-use/time-limited token returned in `REQ-001` `201` response — not predictable request `id` alone). Scoped token + parent ownership checked atomically. Anonymous `REQ-007` requires that token; otherwise `401/404`.
- **Security:** Validate `file size` (max `5 MB` V1), `allowed types` (`image/jpeg`, `image/png`, `image/webp`, `application/pdf` allow-list), `actual content signature` (client MIME/extension not trusted), filename sanitized, storage authorization. `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE`.
- **Access:** Inherits Request authorization — `Customer → own request attachment`, `Staff → operational`, `Admin → authorized`, `Anonymous → no automatic public retrieval`. No permanent public storage URLs; later implementation may use signed/temporary URLs.
- **Metadata exposure:** `filename/display name, content_type, size` only where authorized; never internal storage keys.

### 26.9 Staff Operational Handling — REQ-006 (Controlled)

- **Staff view/read:** `REQ-004`/`REQ-005` see full specifications, contact data, product context, attachments, status, timestamps — operational, not ownership.
- **Staff mutation:** `PATCH /api/v1/requests/{request}` (REQ-006) may update only operational fields:

  | Field | Staff writable? | Customer writable? | Notes |
  |---|---|---|---|
  | `request_status` | Limited (controlled transition `SUBMITTED→IN_REVIEW→CLOSED`) | No | Via controlled values only, not arbitrary text; request body field is `request_status` |
  | `staff_internal_notes` | Yes | No | Separated from `customer_notes`; never exposed to customer |
  | `customer_notes` / original description | No (preserve history) | No (submitted is immutable) | Original remains reconstructable |
  | `product_id` / `quantity` / `dimensions` / `material` / `color` | Read | Read | Customer-provided data preserved |
  | `user_id` / `ownership` | No | No | Never change |
  | `order_id` / `payment_*` | No* | No | `*` only via separately approved Request-to-Order workflow (see §26.11) |

- `PATCH` with `request_status` arbitrary value beyond CLOSED enum → `422 INVALID_VALUE` (`field: request_status`). Status changes use `request_status` CLOSED.
- Original submission remains reconstructable: staff edits do not silently destroy historical context. Preserve `customer said: "Need 2.2m walnut table"` semantics.

### 26.10 Request Status — Minimal V1 CLOSED Enum (Formally Approved — resolves §43-44 deferral)

Version 1 defines a minimal operational status model **formally approved via `ADR/API-REQ-010`** (was deferred per `phase-1.25.md §43-44` unless formally approved; now approved as `SUBMITTED→IN_REVIEW→CLOSED`, so `request_status` and `REQ-006` are not deferred):

```
SUBMITTED → IN_REVIEW → CLOSED
```

| Status | Meaning | Set by | Terminal? |
|---|---|---|---|
| `SUBMITTED` | New intake, staff has not yet reviewed | Server at creation (default) | No |
| `IN_REVIEW` | Staff has opened/acknowledged and is handling | Staff via `REQ-006` `request_status: IN_REVIEW` | No |
| `CLOSED` | Staff has completed handling / closed intake | Staff via `REQ-006` `request_status: CLOSED` | Yes |

- **CLOSED enum:** Only `SUBMITTED`, `IN_REVIEW`, `CLOSED` are valid (`UPPER_SNAKE_CASE`, CLOSED). `QUOTED`/`APPROVED`/`REJECTED`/`PRODUCING` and similar workflow states are **not** valid in V1 and must not be introduced by clients. Introducing a new status is a compatibility decision.
- **Progression:** `SUBMITTED→IN_REVIEW→CLOSED` is expected. `IN_REVIEW→SUBMITTED` rejected `409 INVALID_REQUEST` / `CONFLICT`. Direct `SUBMITTED→CLOSED` is permitted for simple close without intermediate review. No `CLOSED`→any transition.
- **Future:** A later `CONTACTED` status may be added if staff explicitly needs “customer contacted” milestone, but V1 defers it to avoid over-modeling.
- **Exposure:** Status **is** exposed in V1 to support staff queue filtering and customer visibility; it is never client-settable at creation. Was deferred per §43-44, now approved per ADR/API-REQ-010.

### 26.11 What Request Is Not — Explicit Boundaries

- **Not an Order:** Submitting a request does not `reserve inventory`, `create an Order`, `charge payment`, `guarantee price`, `guarantee production/delivery/completion`. No `order_id` returned unless an actual Order has been created via **separately approved** workflow (see §26.17).
- **Not a Quote:** No `quoted_price` produced at submission; business may later discuss price/availability but not authoritative from intake.
- **Not a Purchase:** No payment fields (`payment_status`, `payment_id`, `payment_amount`, `card`, `mobile_money`) accepted from creator — rejected `422`. Payment belongs to Group H.
- **Not Cart/Checkout:** `MADE_TO_ORDER` product remains not checkout-eligible; request flow is the sole intake.
- **Not Inventory:** Zero stock effect.

### 26.12 Customer Retrieval — Ownership-Scoped

- `REQ-002 GET /api/v1/me/requests` and `REQ-003 GET /api/v1/me/requests/{request}` are `AUTHENTICATED_OWNER` — only authenticated Customer's own requests.
- `customer_id` swapping (`?user_id=another`, `{"user_id":"..."}`) rejected; `Customer A → Customer B request` fails `404 REQUEST_NOT_FOUND` (masked per `§15.8`) never `403` enumeration.
- Anonymous requests (`user_id null`) do **not** appear in `GET /me/requests` unless a secure claim/link mechanism is explicitly introduced later (deferred).

### 26.13 Anonymous Retrieval — Not Supported Without Secure Mechanism

- V1: **No `GET /requests/{request}` public for anonymous.** Predictable `id` is not a bearer credential.
- If later needed, requires `secure access token / one-time link / verified contact mechanism` — out of scope.
- `GET /requests` collection is **never** publicly searchable — anonymous callers receive `401/404` on `GET /requests` and `GET /requests/{request}` unless scoped token path.

### 26.14 Staff/Admin Retrieval & Access Levels

- `REQ-004 GET /api/v1/requests` and `REQ-005 GET /api/v1/requests/{request}` are `OPERATIONAL` `requests.view` — paginated, filtered, latest-first. Staff sees contact, product, specs, attachments, internal notes where authorized. Admin broader but still `authorized + auditability + data minimization`.
- Pagination `page`/`per_page` uses global `meta.pagination`; sorting deterministic `created_at DESC, id ASC`.

### 26.15 Privacy, Caching & Security

- **Private data:** Request contact (`name`, `phone`, `email`), specs, notes, attachments are `PRIVATE` — never exposed via public Catalog. `Cache-Control: private, no-store` for `REQ-002/003` (own) and `REQ-004/005` operational. Not CDN public.
- **Field-level exposure:** Customer view excludes `staff_internal_notes`; staff view includes as authorized; anonymous creation response excludes internal fields.
- **Abuse surface:** Anonymous `POST /requests` is public mutation → `RATE-LIMIT CANDIDATE`. Later implementation considers `per-IP throttling, spam protection, body size limits, attachment limits` — identified, not implemented. CAPTCHA deferred unless rate-limit insufficient.
- **Idempotency:** `POST /requests` is **not inherently idempotent** — duplicate submit creates two requests. Customer duplicate tap is acceptable duplicate (not uniqueness constraint on `email+message`). Future idempotency mechanism deferred; do not add hard uniqueness constraint.
- **Concurrency:** Request intake low concurrency risk; operational `PATCH REQ-006` race `Staff A close + Staff B update` treated as state-transition concurrency (`409 CONFLICT` / `ORDER_STATE_CONFLICT` style).

### 26.16 Query, Pagination, Filter, Search, Sorting — Global Conventions

- **Collection pagination:** `REQ-002` (customer) and `REQ-004` (staff) use `page`/`per_page` (1–100) → `meta.pagination` per `§4` / `api-conventions.md §11`.
- **Staff filters (allow-list only):** `search` (name/email/phone/reference/product), `request_status` CLOSED (`SUBMITTED`/`IN_REVIEW`/`CLOSED`), `product_id` (linked product), `created_from`/`created_to` ISO8601 `Z`, `sort` / `sort_direction`, `page`/`per_page`. Unknown filter → `422`.
- **Customer filters:** implicitly scoped to own; `search` limited to own dataset.
- **Sorting:** `created_at DESC, id ASC` (newest first) default for both staff queue and customer history; `id ASC` tie-breaker; no DB natural order.
- **No request-specific pagination format** — global reuse.

### 26.17 Future Request-to-Order Boundary — Outside V1

Conceptual possibility `Request → business discussion → customer agreement → future Order workflow` is **explicitly outside V1 Request API** unless separately approved. `REQ-006` must not silently `create Order` or `payment` via generic update. No `order_id` field in V1 Request unless that workflow is contracted; `REQUEST_NOT_FOUND` / `RESOURCE_NOT_FOUND` continue to apply.

### 26.18 Validation — Layered (per §14)

```
Transport (JSON/multipart, size) → Schema (types/enums/contact) → Auth (optional) → Authorization (PUBLIC create vs owns OPERATIONAL read) → Domain (product_type MADE_TO_ORDER when linked, quantity range, dimensions allow-list, notes bounds, attachment safe) → Concurrency (low) → Persistence
```

- **Contact (finalized — resolves §15 Potential, per ADR/API-REQ-001):** Schema requires `name` non-empty trimmed max 120 **and** at least one of `phone` (normalized max 30) / `email` (lowercased max 255) for **both** Anonymous and authenticated Customer (identical rule, no fallback to trusted account). `phone+email` both supplied is valid; `phone` empty + `email` empty → `MISSING_REQUIRED_FIELD`. Authenticated path does **not** waive contact.
- **Cross-field:** `product_id` supplied → `product_type` must be `MADE_TO_ORDER` (otherwise `PRODUCT_NOT_REQUESTABLE`).
- **Conditional:** `quantity` optional integer; `dimensions` object optional, `unit` required when any dimension present.
- **State-dependent:** `PATCH REQ-006` `CLOSED` terminal (now approved, not deferred — see §26.10); transitions validated against current `request_status` (`SUBMITTED→IN_REVIEW→CLOSED`).

### 26.19 Errors — Common Error Contract (`data` vs `errors`)

Uses `{"data":…}` success and `{"errors":[…],"meta":{"request_id":…}}` failure per `§15`.

| Failure | API `code` | HTTP |
|---|---|---|
| Missing required `name` or `phone`+`email` empty | `MISSING_REQUIRED_FIELD` (`field: name` / `field: phone`) | 422 |
| Invalid format/type (`email` bad, `quantity` string) | `INVALID_FORMAT` / `INVALID_TYPE` / `INVALID_VALUE` | 422 |
| `product_id` not found / inactive | `RESOURCE_NOT_FOUND` / `PRODUCT_NOT_FOUND` / `INVALID_REQUEST` | 404 / 422 |
| `product_type = IN_STOCK` supplied to made-to-order request | `PRODUCT_NOT_REQUESTABLE` | 409 |
| Invalid attachment (size/type/signature/filename) | `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE` | 422 / 413 |
| Not authenticated where required (`GET /me/requests`) | `AUTHENTICATION_REQUIRED` | 401 |
| Authenticated but not owner (`Customer A → B request`) | `REQUEST_NOT_FOUND` / `RESOURCE_NOT_FOUND` (404 **masked**, never `403`) | 404 |
| Staff without `requests.view` / `requests.manage` | `FORBIDDEN` | 403 |
| Invalid `request_status` enum | `INVALID_VALUE` (`field: request_status`) | 422 |
| Invalid Request transition (`CLOSED→IN_REVIEW`) | `INVALID_REQUEST` / `CONFLICT` | 409 |
| Anonymous `GET /requests` / `GET /requests/{id}` | `AUTHENTICATION_REQUIRED` / `RESOURCE_NOT_FOUND` | 401/404 |
| Rate limited | `RATE_LIMITED` + `Retry-After` | 429 |
| Unexpected | `INTERNAL_SERVER_ERROR` + `meta.request_id` | 500 |

Only add codes with actual utility per `§15.15`; `PRODUCT_NOT_REQUESTABLE` distinguishes type rule.

### 26.20 Representations & Response Examples

**Customer created (REQ-001 anonymous or authenticated):**

```json
{
  "data": {
    "id": "req_01h8y5a1b2c3d4e5f6g7h8j9",
    "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
    "quantity": 1,
    "name": "Asha Mwangi",
    "phone": "+255700000001",
    "email": "asha@example.com",
    "dimensions": {
      "length": 220,
      "width": 90,
      "height": 75,
      "unit": "cm"
    },
    "material": "walnut, matte finish",
    "color": "natural walnut with charcoal fabric",
    "notes": "Need a 2.2m dining table for 6, rounded corners.",
    "request_status": "SUBMITTED",
    "attachments": [
      { "id": "att_01h...", "filename": "inspiration.jpg", "content_type": "image/jpeg", "size": 843210 }
    ],
    "created_at": "2026-09-01T10:15:00Z",
    "updated_at": "2026-09-01T10:15:00Z"
  }
}
```

**Customer summary (REQ-002 collection) — lighter:**

```json
{
  "data": [
    {
      "id": "req_01h8y5a1b2c3d4e5f6g7h8j9",
      "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "quantity": 1,
      "request_status": "SUBMITTED",
      "created_at": "2026-09-01T10:15:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 3, "last_page": 1, "has_next": false, "has_previous": false }
  }
}
```

**Staff detail (REQ-005) — adds contact + internal:**

```json
{
  "data": {
    "id": "req_01h8y5a1b2c3d4e5f6g7h8j9",
    "product": {
      "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "name": "Walnut Dining Table",
      "slug": "walnut-dining-table"
    },
    "quantity": 1,
    "name": "Asha Mwangi",
    "phone": "+255700000001",
    "email": "asha@example.com",
    "dimensions": { "length": 220, "width": 90, "height": 75, "unit": "cm" },
    "material": "walnut",
    "color": "natural walnut",
    "notes": "Need a 2.2m dining table for 6.",
    "request_status": "IN_REVIEW",
    "staff_internal_notes": "Called customer 2026-09-01, discussed walnut availability.",
    "attachments": [{ "id": "att_01h...", "filename": "inspiration.jpg", "content_type": "image/jpeg", "size": 843210 }],
    "user_id": "user_01h...",
    "created_at": "2026-09-01T10:15:00Z",
    "updated_at": "2026-09-01T11:00:00Z"
  }
}
```

- Customer `REQ-002`/`REQ-003` never exposes `staff_internal_notes`; `internal` field is operational only.
- `user_id` nullable (`string | null`) in staff view; omitted or `null` for anonymous in customer view where relevant.

### 26.21 Field Classification Matrix (Normative)

| Field | Anonymous (REQ-001) | Customer (REQ-001) | Staff (REQ-004/005 read) | Server |
|---|---:|---:|---:|---:|
| `product_id` | Input | Input | Read | Validate (`MADE_TO_ORDER` when supplied) |
| `quantity` | Input | Input | Read | Validate (1..100) |
| `name` | Input | Input | Read | Store (trimmed) |
| `phone` | Input | Input | Read | Store (normalized) |
| `email` | Input | Input | Read | Store (lowercased) |
| `dimensions` | Input | Input | Read | Validate (allow-list, unit `cm`) |
| `material` | Input | Input | Read | Store |
| `color` | Input | Input | Read | Store |
| `notes` | Input | Input | Read | Store (safe) |
| `attachment` | Input (multipart) | Input | Read | Validate |
| `user_id` | No | No (derived) | No | Derive (`authenticated` or `null`) |
| `request_status` | No | No | Controlled (`SUBMITTED→IN_REVIEW→CLOSED`) — **formally approved V1** (was deferred per §43-44, now approved ADR/API-REQ-010; Staff `REQ-006` mutation approved) | Server-generated |
| `staff_internal_notes` | No | No | Staff/Admin writable | Server/store |
| `order_id` | No | No | No* | Server later (only via approved Request-to-Order workflow) |
| `created_at` / `updated_at` | No | No | No | Server-generated ISO8601 `Z` |

`*` only through separately approved Request-to-Order process.

### 26.22 Authorization Matrix (Normative — see §18)

| Operation | Anonymous | Customer | Staff | Admin |
|---|---|---:|---:|---:|
| Create Request (`REQ-001`) | **Yes** (public, `Anonymous`/`Customer` only per §26.2) | **Yes** | **No** — operational-only (Staff/Admin do not create customer requests via `REQ-001`; handle via operational queue `REQ-004/005/006`) | **No** — operational-only |
| List own Requests (`REQ-002`) | No | **Yes** (`AUTHENTICATED_OWNER` own) | No | Authorized (purpose-bound) |
| View own Request (`REQ-003`) | No | **Yes** (404 masked if not own) | No | Authorized |
| List operational Requests (`REQ-004`) | No | No | **Yes** (`requests.view`) | **Yes** |
| View operational Request (`REQ-005`) | No | No | **Yes** (`requests.view`) | **Yes** |
| Modify operational fields (`REQ-006`) | No | No/limited (not `request_status`/`internal_notes`/`ownership`) | **Yes** (`requests.manage`) | **Yes** |
| Upload attachment (`REQ-007`) | **Scoped** token only | According to parent | According to parent | According to parent |
| Manage customer account (request context) | No | Own only | No (staff cannot change ownership/credentials) | Authorized only |

`Staff operational` is not ownership; Admin remains explicit + auditable.

### 26.23 Security, Field-Level Exposure & History Preservation

- History preservation: original customer-provided `product_id`, `quantity`, `dimensions`, `material`, `color`, `notes`, `contact` are preserved; staff `internal_notes` is separate and never customer-visible. Future audit of status changes `actor/action/resource/target/timestamp/result` identified.
- Field-level before serialization: select representation by actor (`Customer → own without internal_notes`, `Staff → operational with internal_notes + contact`, `Admin → authorized`). Never `model.toArray()` mass-serialization.
- Search results apply over authorized dataset; pagination cannot leak cross-ownership.

### 26.24 Compatibility — Version 1

Within `v1`, do not change without compatibility review: `field type`, `field meaning`, `requiredness`, `enum value semantics` (`request_status` CLOSED), `ownership semantics`, `pagination/sort/filter` shapes, `response envelope` (`data`/`meta`/`errors`). Adding optional `CONTACTED` status later is a formal compatibility decision; new status value is CLOSED-enum event.

### 26.25 Cross-References

- Resources: `api-resources.md §5` (Request, Request Attachment — ownership, relationships, public/private classification, customer/staff/admin representations, mutable/immutable, dimensions, status).
- Conventions: `api-conventions.md §26` (anonymous creation, customer ownership, private data, attachment authorization, operational representation, dimensions canonical `cm`, free-text material/color, status CLOSED).
- Domain: `business-rules.md §9` (Made-to-Order: anonymous allowed, product-linked or custom, no Order/inventory/payment, optional attachments, staff operational handling).
- Decisions: `decisions.md ADR/API-REQ-001..ADR/API-REQ-011` (anonymous requests, ownership, not an order, no inventory, optional attachments, no anonymous retrieval, payment separation, dimensions, product optional).
- Order: `§24` historical records distinct from Request; Payment `Group H`; Tracking `§25` not Request; Enquiry `§27` separate communication.
- Links to Conventions & Resources updated: `§20` now includes Request conventions `§26`.

---

## 27. General Enquiry API Contract (Phase 1.26)

> **Authority:** Canonical domain API contract for the **General Enquiry** subsystem (`ENQ-001`..`ENQ-007`). Consolidates `phases/phase-1.26.md` and is consistent with `Catalog` (`§21`), `Cart` (`§22`), `Checkout` (`§23`), `Order` (`§24`), `Request` (`§26` — separate intent), `Authentication` (`§17`), `Authorization` (`§18`), `Validation` (`§14`), `Error` (`§15`).
> **Core Principle:** A General Enquiry is a **request for information or communication with the business** — a private business communication. It is **not** a purchase, **not** a quote, **not** a product request, **not** an order, **not** a payment, **not** a support contract. Anonymous and authenticated visitors may submit enquiries; authenticated enquiries are customer-owned and retrieveable only by owner; Staff handle operationally.

### 27.1 Enquiry Endpoint Inventory (Authoritative — aligned with Phase 1.19 `§19.1`)

Enquiry endpoints `ENQ-001`..`ENQ-005` are **`APPROVED`** (complete V1 Enquiry contract — finalized in Phase 1.26). `ENQ-006` (staff close/reopen) and `ENQ-007` (attachment upload) are **`APPROVED`** where documented. This supersedes the provisional `PROPOSED` label in `§19.1` for the Enquiry domain; `§19.15` remains `PROPOSED` only for domains not yet finalized. Clients may consume `ENQ-001`..`ENQ-007` as authoritative.

| ID | Method | Path | Actor | Auth | Authorization | Purpose | Idempotency | Concurrency |
|---|---|---|---|---|---|---|---|---|
| `ENQ-001` | `POST` | `/api/v1/enquiries` | Anonymous, Customer | **Optional** | **PUBLIC** submit (validated + anti-abuse) | Submit general enquiry (anonymous allowed) | **None required** — not idempotent by default (duplicate tap may create second enquiry; see §27.15) | Low |
| `ENQ-002` | `GET` | `/api/v1/me/enquiries` | Customer | Required | `AUTHENTICATED_OWNER` own enquiries | List own enquiries (paginated) | — | — |
| `ENQ-003` | `GET` | `/api/v1/me/enquiries/{enquiry}` | Customer | Required | `AUTHENTICATED_OWNER` owns enquiry (404 masked) | Get own enquiry detail | — | — |
| `ENQ-004` | `GET` | `/api/v1/enquiries` | Staff, Admin | Required | `OPERATIONAL` `enquiries.view` | List operational enquiries (staff queue) | — | — |
| `ENQ-005` | `GET` | `/api/v1/enquiries/{enquiry}` | Staff, Admin | Required | `OPERATIONAL` `enquiries.view` | Get operational enquiry detail | — | — |
| `ENQ-006` | `POST` | `/api/v1/enquiries/{enquiry}/close` (+ optional `reopen`) | Staff, Admin | Required | `OPERATIONAL` `enquiries.manage` | Close / reopen enquiry (controlled state `OPEN→CLOSED`→`OPEN` where approved) | Designed idempotent | Low/Medium (race `Staff A close + Staff B close`) |
| `ENQ-007` | `POST` | `/api/v1/enquiries/{enquiry}/attachments` | Anonymous* (scoped), Customer, Staff, Admin | **Scoped** | **Scoped upload token** + parent ownership; predictable ID alone insufficient | Upload enquiry attachment — preferred is inline multipart on `ENQ-001`; separate `POST` only with scoped token, private to parent | — | — |

`*` `ENQ-007` anonymous path requires server-issued upload token returned on `ENQ-001` creation (single-use/time-limited); see §27.8.

*Notes:* `GET /enquiries/{enquiry}` is **not** automatically public; `GET /me/enquiries` is ownership-scoped. No `GET /enquiries` for anonymous. Canonical resource is `/enquiries` (not `/contacts`/`/messages`/`/support`). `ENQ-001` is the `PUBLIC` anonymous creation entry point; all other reads are authenticated + authorized. Enquiry is separate from `Request` (`REQ-001`) — do not merge (§27.11).

### 27.2 Endpoint ENQ-001 — Submit General Enquiry (Public, Anonymous Allowed)

| Attribute | Version 1 Contract |
|---|---|
| **ID** | `ENQ-001` |
| **Method** | `POST` |
| **Path** | `/api/v1/enquiries` |
| **Domain** | Enquiry (communication, not purchase) |
| **Actor** | `Anonymous` or `Customer` (authenticated) |
| **Authentication** | **Optional** — `Anonymous` allowed with contact; `Customer` where `Authorization: Bearer` present |
| **Authorization** | **PUBLIC** submit — no prior auth; anti-abuse/rate-limit candidate (see §27.15) |
| **Purpose** | Submit a general enquiry to the business; becomes private operational communication |
| **Request body** | JSON `application/json` preferred; `multipart/form-data` where inline attachment sent (see §27.8) |
| **Response** | `201 Created` `{"data": {Enquiry}}` — same envelope `data`; includes `id`, `enquiry_status`, `created_at`, safe submission fields (`subject`, `message`, `product`/`order` reference where supplied, `attachment` metadata). No `staff notes`, no credentials |
| **Idempotency** | Not required in V1; duplicate submission creates distinct enquiry (see §27.15); future `Idempotency-Key` optional |
| **Caching** | Not cacheable — creation, `Cache-Control: private, no-store` |
| **Boundaries** | Creates **no** Order, **no** Payment, **no** inventory reservation, **no** cart |

**Why optional auth:** Visitor must move directly from `Contact / Enquiry` form → submit without forced registration. Anonymous `user_id = null` is valid; authenticated `Enquiry.user_id = authenticated principal` derived server-side, never from `{"user_id":"..."}`.

### 27.3 Input Fields — Allow-List & Server-Controlled (ENQ-001)

Canonical `POST /api/v1/enquiries` JSON body (when no file) or multipart fields (when file):

```json
{
  "name": "Asha Mwangi",
  "email": "asha@example.com",
  "phone": "+255700000001",
  "subject": "Do you deliver to Dodoma?",
  "message": "Hello, I would like to know if you deliver the Modern Sofa to Dodoma and what the delivery time would be.",
  "category": "DELIVERY",
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
  "order_id": "ord_01h8y5a...",
  "attachment": "(file)"
}
```

| Field | Required | Type | Valid Values / Rule | Client Authority | Notes |
|---|---|---|---|---|---|
| `name` | **Yes (anonymous)** / **Optional/derived (authenticated)** | string | Trimmed, non-empty, max 120 chars, Unicode safe. Collapses internal whitespace. When authenticated and omitted, server derives from profile `name` if available. | Client (anonymous required, customer optional) | Self-contained contact snapshot; derived where not supplied |
| `email` | **Conditional** | string \| null | At least one of `email` or `phone` required for **anonymous**; for **authenticated**, optional if profile provides contact, otherwise at least one required. When supplied: lowercased/trimmed, RFC-ish format, max 255 | Client | Staff needs reachable channel |
| `phone` | **Conditional** | string \| null | At least one of `email` or `phone` required for anonymous; authenticated optional/derived. When supplied: normalized E.164-ish, trimmed, max 30 chars | Client | `phone+email` both valid |
| `subject` | **Yes** | string | Required, trimmed, non-empty, min 5, max 200 chars. Not HTML. Not unconstrained blob. | Client | Useful for staff triage |
| `message` | **Yes** | string | Required, trimmed, min 10, max 5000 chars. Plain text only in V1 (no Markdown/HTML). Newlines preserved where meaningful. Safe handling (no code/script execution). | Client | Customer enquiry body |
| `category` | **Optional** | enum CLOSED \| null | When supplied must be `GENERAL`, `PRODUCT`, `DELIVERY`, `OTHER` (`UPPER_SNAKE_CASE`, CLOSED). `REFUND`, `COMPLAINT` etc. not valid unless explicitly approved. `null`/omitted = no category | Client | Simple triage, not full CRM taxonomy (see §27.5) |
| `product_id` | **Optional** | string \| null | When supplied must be existing, `is_active:true` and `is_published:true` **public** product. Validated `exists + publicly visible` (see §27.6); not restricted to `IN_STOCK`/`MADE_TO_ORDER` — any public product reference is allowed for enquiry. `IN_STOCK`/`MADE_TO_ORDER` distinction not used to reject enquiry. `null`/omitted = general enquiry without product link | Client supplies reference; server validates | Optional context "Can you tell me more about this sofa?" |
| `order_id` | **Optional** | string \| null | When supplied must be existing Order **owned by authenticated customer** (see §27.7). Anonymous `order_id` limited: anonymous enquiries may reference an `order_id` only where business approves order-specific support and `order_id` is validated without leaking ownership; in V1 **anonymous `order_id` is discouraged** and when supplied must still pass ownership check when authenticated. Unowned/unknown `order_id` → `404`/`422` ownership handling (see §27.14). `null`/omitted = not about a specific order | Client | Optional "I have a question about my order" |
| `attachment` | **Optional** | file | Via `multipart/form-data` field `attachment` inline on creation (preferred) — see §27.8. Not JSON string | Client | 0 or 1 attachment per creation in V1 |
| `user_id` | **Prohibited** | — | Never client-supplied; derived server-side `user_id = authenticated principal` or `null` | **Server derives** | `{"user_id":"..."}` rejected `422` |
| `enquiry_status` | **Prohibited** | — | Server-controlled `OPEN`/`CLOSED`; if sent rejected `422` (`field: enquiry_status`) | **Server** | See §27.9 |
| `staff_internal_notes` / `internal_notes` | **Prohibited** | — | Customer/anonymous cannot set internal notes | **Staff/Admin** | See §27.10 |
| `payment_*` / `delivery_fee` / `order_reference` | **Prohibited** | — | Enquiry never creates order/payment/fee; if sent rejected | **Server** | See §27.11 |
| `created_at` / `updated_at` | **Prohibited** | — | Server-generated ISO8601 `Z` | **Server** | |
| *Unknown fields* | — | — | Strict rejection `422 INVALID_VALUE` + `field` per `§13.14` | — | Catches typos, stale clients |

**Contact requiredness (normative for Enquiry — per phase-1.26 §19-21, §97):** For **anonymous**, `name` required and at least one of `phone`/`email` required. For **authenticated**, `name`/`email`/`phone` are **Optional/derived** — server derives trusted account identity and uses profile contact where not explicitly supplied, while still capturing contact snapshot actually provided. Historical contact snapshot is recorded as part of enquiry submission where provided. `name` trimmed max 120, `phone` normalized max 30, `email` lowercased max 255. Anonymous `user_id = null` is valid; customer `user_id` derived.

**Contact rule (authenticated):** Authenticated customer may omit `name`/`email`/`phone` where account already provides reachable contact channel; backend associates enquiry with `user_id` automatically and records `contact snapshot` from payload if supplied (preserved historically), otherwise relies on derived account identity internally. The request contract captures contact details actually needed for communication — minimal requirement clearly documented above.

### 27.4 Enquiry vs Made-to-Order Request — Explicit Separation

| Concept | Customer asks | Resource | Result |
|---|---|---|---|
| **Made-to-Order Request** (`REQ-001`) | "Can you make this furniture?" | `/requests` | Intake for production on request; may include dimensions/material/color; not an order |
| **General Enquiry** (`ENQ-001`) | "What time do you open?" / "Do you deliver to Dodoma?" / any general business question | `/enquiries` | Communication; no product manufacturing implied |

- Do not merge `Enquiry` and `Request` into one generic `communication` resource.
- No automatic `Enquiry → Request` or `Request → Enquiry` conversion without explicit business action (distinct intents).
- An enquiry may mention an existing Order (`order_id` optional) but that does not make it an Order operation nor allow Order modification.

### 27.5 Category — Simple, Optional, CLOSED

- Version 1 may use minimal triage enum `GENERAL`, `PRODUCT`, `DELIVERY`, `OTHER` (`UPPER_SNAKE_CASE`, CLOSED). This is **optional** to include; Staff can triage via `subject`/`message` alone if category does not add operational value.
- If no category supplied, enquiry is `category = null` (valid).
- Do **not** create large support taxonomy `PRODUCT_AVAILABILITY`, `DELIVERY_DELAY`, `PAYMENT_PROBLEM`, `ACCOUNT_PROBLEM`, `COMPLAINT`, `REFUND` etc. without operational justification.
- Adding a new category value is a compatibility decision (CLOSED).

### 27.6 Product Association — Optional, Validated, Not Required

- When `product_id` supplied:
  - Product must exist and be `is_active: true` and `is_published: true` and publicly visible.
  - No `product_type` restriction — both `IN_STOCK` and `MADE_TO_ORDER` products may be referenced for enquiry context (question about availability, details, etc.). This differs from Request where `product_id` must be `MADE_TO_ORDER` only.
  - Do not require `product_id` for every enquiry; general contact enquiries omit it.
- When `product_id` is `null`/omitted: general business enquiry — valid. Relies on `subject`/`message` + contact.

### 27.7 Order Association — Optional, Ownership-Validated

- When `order_id` supplied:
  - For **authenticated Customer**: Order must exist **and** be **owned by authenticated principal** (`Enquiry.order.customer_id == auth principal`). `Customer A → Order B` (someone else's order) must be **rejected or 404-masked** to avoid leaking `Order OD-xxx exists` — knowing an `order_id` is not authorization (see §27.14). Staff/admin may have broader operational access per `orders.view_operational`.
  - For **anonymous** (no auth, no scoped token): any `order_id` supplied must be **rejected with `422 INVALID_VALUE` `field: order_id`** in V1 — anonymous callers cannot submit `order_id`. `order_id` is allowed only for **authenticated owners** (`Order owned`) or callers presenting a **scoped order-access token** where business approves order-specific support (token validated, not raw enumeration). Staff/admin operational access also allowed.
- Multiple associations (`product_id` + `order_id` simultaneously) are allowed **only when the caller satisfies the order-access requirement** (authenticated owner or scoped token) — e.g., "Question about Product X in my Order OD-123" is valid for an owner with both fields; anonymous `product_id` + `order_id` without token remains `422` for `order_id` (but `product_id` alone remains allowed). `cart_id`/`payment_id`/`request_id` associations are **not** added in V1 — keep relationships intentional, only `product_id` and `order_id` optional.

### 27.8 Attachments — Optional, Inline Multipart Preferred, Private

- **Optional:** Enquiry may have `0` or `1` attachment on creation in V1 (multiple deferred).
- **Preferred transport:** `multipart/form-data` inline with `ENQ-001` creation (field `attachment`). Do not invent pre-upload-then-attach flow; single-request flow is simpler and shares architecture with `REQ-001`.
- **Separate upload (`ENQ-007`):** `POST /api/v1/enquiries/{enquiry}/attachments` with `multipart/form-data` field `attachment`. Requires **scoped server-issued upload token** (single-use/time-limited token returned in `ENQ-001` `201` response — not predictable enquiry `id` alone). Scoped token + parent ownership checked atomically. Anonymous `ENQ-007` requires that token; otherwise `401/404`.
- **Security:** Validate `file size` (max `5 MB` V1), `allowed types` (`image/jpeg`, `image/png`, `image/webp`, `application/pdf` allow-list), `actual content signature` (client MIME/extension not trusted), filename sanitized, storage authorization. `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE`.
- **Authorization (inheritance):** Inherits Enquiry authorization — `Customer → own enquiry attachment`, `Staff → operational`, `Admin → authorized`, `Anonymous → scoped token only / no automatic retrieval`. No permanent public attachment URLs; later may use signed/temporary URLs.
- **Metadata exposure:** `filename/display name, content_type, size` only where authorized; never internal storage keys. Same file strategy as `REQ-007` — no second upload architecture.

### 27.9 Enquiry Status — Minimal V1 CLOSED `OPEN`/`CLOSED`

Version 1 defines minimal operational state:

```
OPEN → CLOSED
```

| Status | Meaning | Set by | How |
|---|---|---|---|
| `OPEN` | New enquiry requiring staff attention | Server at creation (default) | `ENQ-001` creates `OPEN` |
| `CLOSED` | Staff has handled / closed enquiry | Staff/Admin via `ENQ-006` | `POST /enquiries/{enquiry}/close` |

- **CLOSED enum:** Only `OPEN`, `CLOSED` are valid (`UPPER_SNAKE_CASE`, CLOSED). Do not invent `ASSIGNED`, `IN_PROGRESS`, `WAITING_FOR_CUSTOMER`, `ESCALATED`, `RESOLVED`, `SUBMITTED` for enquiry (that belongs to Request). `OPEN`/`CLOSED` is sufficient if staff only need `needs attention` boolean.
- **Reopen (optional):** `POST /enquiries/{enquiry}/reopen` (`ENQ-006` variant) may transition `CLOSED→OPEN` for authorized Staff/Admin if business actually needs reopening; in V1 **optional** — if not approved, `CLOSED` is terminal and `OPEN → CLOSED` is the only transition.
- **Customer cannot set status:** `{"enquiry_status":"CLOSED"}` from customer/anonymous on creation or via `PATCH` is rejected `422` (`field: enquiry_status`). Status changes only via controlled `ENQ-006` staff action (see also §27.10).
- **Not a second state machine:** Do not use `SUBMITTED`/`IN_REVIEW` (Request) for enquiry — each domain keeps its own minimal state.

**State transition matrix (V1):**

| Current | Action | Actor | Next | Code if invalid |
|---|---|---|---|---|
| `OPEN` | Close | Staff/Admin (`enquiries.manage`) | `CLOSED` | — |
| `CLOSED` | Reopen (if approved) | Authorized Staff/Admin | `OPEN` | `409 CONFLICT` / `INVALID_ENQUIRY` if not approved |

If reopen not approved, table is simply `OPEN → CLOSED`.

### 27.10 Staff Operational Handling — ENQ-006 (Controlled)

- **Staff view/read:** `ENQ-004`/`ENQ-005` see contact (`name`, `email`, `phone`), `subject`, `message`, optional `product`/`order` reference with validated product info and order summary, `attachments` (metadata), `enquiry_status`, `timestamps` — operational, not ownership.
- **Staff mutation:** `POST /api/v1/enquiries/{enquiry}/close` (ENQ-006) and optional `reopen` may update only operational fields:

  | Field | Staff writable? | Customer writable? | Notes |
  |---|---|---|---|
  | `enquiry_status` | Limited (`OPEN→CLOSED`, optional `CLOSED→OPEN` if approved) — controlled actions | No | Via `POST /close`/`reopen`, not generic `PATCH {status}` |
  | `staff_internal_notes` | Yes where authorized | No | Separated from customer `message`; never exposed to customer |
  | `message` / `subject` / contact snapshot | No (preserve history) | No (submitted is immutable) | Original enquiry preserved |
  | `product_id` / `order_id` | Read | Read | Validated references preserved |
  | `user_id` / `ownership` | No | No | Never change |
  | `payment_*` / `order_status` | No | No | Enquiry cannot modify order/payment |

- `PATCH` with arbitrary `status` beyond CLOSED enum → `422 INVALID_VALUE`. Prefer `POST /enquiries/{enquiry}/close` over `PATCH {status:"CLOSED"}` for explicit business action if staff closing represents a distinct operation.
- Original submission immutable: staff edits must not silently destroy historical `customer said: "Do you deliver to Dodoma?"`.
- Internal notes are separate representation — `internal_notes` must not appear in customer response (`ENQ-002`/`ENQ-003`).
- No messaging thread in V1: Staff response can initially occur through ordinary business contact process (email/phone), not in-app conversation (`Phase Group R` deferred). Do not implement chat.

### 27.11 What Enquiry Is Not — Explicit Boundaries

- **Not an Order:** Submitting an enquiry does not `reserve inventory`, `create Order`, `charge payment`. No `order_id` creation.
- **Not a Quote/Purchase:** No `quoted_price`, `payment_*`, `delivery_fee`, `product_type` purchase effect.
- **Not a Request:** `Request` asks "Can you make this furniture?" with dimensions/material/color; `Enquiry` asks general business information — separate workflows.
- **Not Cart/Checkout/Payment:** `Cart`/`Checkout` (`CHK-001`)/`Payment` (`Group H`) remain distinct; no `cart_id`/`payment_id` in enquiry.
- **Not Order Support that Modifies Order:** An enquiry may mention an Order but does not authorize arbitrary `update order status` / `create_order:true` via enquiry. Order-specific support remains Order-linked, not enquiry-driven.
- **Not Inventory:** Zero stock effect.

### 27.12 Customer Retrieval — Ownership-Scoped

- `ENQ-002 GET /api/v1/me/enquiries` and `ENQ-003 GET /api/v1/me/enquiries/{enquiry}` are `AUTHENTICATED_OWNER` — only authenticated Customer's own enquiries.
- `customer_id` swapping (`?user_id=another`, `{"user_id":"..."}`) rejected; `Customer A → Customer B enquiry` fails `404 ENQUIRY_NOT_FOUND` / `RESOURCE_NOT_FOUND` (masked per `§15.8`) never `403` enumeration.
- Anonymous enquiries (`user_id null`) do **not** appear in `GET /me/enquiries` unless a secure claim/link mechanism is explicitly introduced later (deferred).
- Customer history is paginated (`page`/`per_page` 1–100 → `meta.pagination`) if it can grow; sorting `created_at DESC, id ASC` (newest first) by convention.

### 27.13 Anonymous Retrieval — Not Supported Without Secure Mechanism

- V1: **No anonymous `GET /enquiries/{enquiry}`.** Predictable `ENQ-...` or numeric ID must not become a password. Anonymous enquiry creation is supported; anonymous retrieval is **not supported** unless a secure retrieval mechanism is explicitly approved.
- If later needed, requires `secure token / one-time link / verified contact mechanism` — out of scope and must be explicitly contracted.
- Anonymous callers receive `401 AUTHENTICATION_REQUIRED` / `404 RESOURCE_NOT_FOUND` on `GET /enquiries` and `GET /enquiries/{enquiry}` unless scoped token path.

### 27.14 Staff/Admin Retrieval & Access Levels

- `ENQ-004 GET /api/v1/enquiries` and `ENQ-005 GET /api/v1/enquiries/{enquiry}` are `OPERATIONAL` `enquiries.view` — paginated, filtered, newest-first. Staff sees contact, product/order context, attachments, internal notes where authorized. Admin broader but still `authorized + auditability + data minimization`.
- **Staff queue filtering (allow-list):** `search` (name/email/phone/subject/message/reference, order ref), `enquiry_status` CLOSED (`OPEN`/`CLOSED`), `category` CLOSED where used, `product_id`, `order_id`, `created_from`/`created_to` ISO8601 `Z`, `sort`/`sort_direction`, `page`/`per_page`. Unknown filter → `422`. Search constrained to authorized Staff dataset, not global customer database search.
- Staff cannot use enquiry access to: `change customer role`, `change customer password`, `block customer`, `restrict browsing`, `restrict ordering` — explicit authorization boundary.
- Admin has legitimate higher authorization (audit `actor/action/resource/target/timestamp/result`) but still data-minimized; no blanket exposure of unrelated account data.

### 27.15 Privacy, Caching, Abuse & Idempotency

- **Private data:** Enquiry contact (`name`, `phone`, `email`), subject/message, product/order association, attachments are `PRIVATE` — never exposed via public Catalog, never indexed for SEO. Classification: `Catalog → PUBLIC`, `Enquiry → PRIVATE` (`CUSTOMER-PRIVATE` own, `STAFF-OPERATIONAL` authorized, `ADMINISTRATIVE` authorized, `INTERNAL` private). `Cache-Control: private, no-store` for `ENQ-002/003` (own) and `ENQ-004/005` operational. Not CDN public.
- **Field-level exposure:** Customer view excludes `staff_internal_notes`, `internal_tags`; staff view includes as authorized; anonymous creation response excludes internal fields. No `password`/`hash`/`tokens`/`secrets` ever.
- **Abuse surface:** Anonymous `POST /enquiries` is public mutation → `RATE-LIMIT CANDIDATE` (high priority). Later implementation considers `per-IP throttling, spam prevention, request-size limits, attachment limits` — identified, not implemented. CAPTCHA deferred unless rate-limit insufficient.
- **Idempotency:** `POST /enquiries` is **not inherently idempotent** — duplicate submit after timeout may create two enquiries. Duplicate enquiry risk is operational noise, not hard uniqueness constraint on `email+message` (legitimate repeated enquiries can occur). Future `Idempotency-Key` optional; do not use `email+message` uniqueness as idempotency. Attachment retries can duplicate files — upload/idempotency strategy accounts for that later.
- **Message safety:** Treat `message`/`subject` as untrusted input — not interpreted as code/HTML/SQL/file-path/query syntax; backend validates and stores safely; frontend escapes. HTML/Markdown **plain text only** in V1 (not Markdown/HTML) — reduces attack surface and complexity.
- **Concurrency:** Enquiry intake low concurrency; operational `POST .../close` race `Staff A close + Staff B close` treated as state-transition concurrency (`409 CONFLICT`).

### 27.16 Query, Pagination, Filter, Search, Sorting — Global Conventions

- **Collection pagination:** `ENQ-002` (customer) and `ENQ-004` (staff) use `page`/`per_page` (1–100) → `meta.pagination` per `§4` / `api-conventions.md §11`.
- **Staff filters (allow-list, §27.14):** `search`, `enquiry_status` CLOSED (`OPEN`/`CLOSED`), `category` CLOSED where used, `product`/`order` references, `created_from`/`created_to` ISO8601 `Z`, `sort` / `sort_direction`, `page`/`per_page`. Unknown filter → `422`.
- **Customer filters:** implicitly scoped to own; `search` limited to own dataset.
- **Sorting:** `created_at DESC, id ASC` (newest first) default for both staff queue and customer history; `id ASC` tie-breaker; no DB natural order.
- **No enquiry-specific pagination format** — global reuse (`meta.pagination`).

### 27.17 Enquiry and Customer Account — Ownership Derivation

- Authenticated enquiry `Enquiry.user_id = authenticated principal` server-derived (own). `Anonymous enquiry.user_id = null`.
- Customer retrieval `ENQ-002/003` verifies `owns` before serialization; `Customer A → Customer B enquiry` fails `404`.
- `email = customer's email` is **not** ownership proof for anonymous retrieval — email is contact, not authentication. Anonymous → authenticated transition does not auto-attach old anonymous enquiries by email match (privacy). See §27.12/§27.13.
- Enquiry `user_id` knowledge or `order_id` knowledge alone is not authorization — validated via ownership + authz.

### 27.18 Validation — Layered (per §14)

```
Transport (JSON/multipart, size) → Schema (types/enums/contact) → Auth (optional) → Authorization (PUBLIC create vs owns OPERATIONAL read) → Domain (contact valid, subject/message bounds, category CLOSED, product exists when linked, order ownership when linked, attachment safe, plain-text) → Concurrency (low) → Persistence → (Notification later)
```

- **Contact (anonymous vs customer, per §27.3):** Anonymous: `name` required + at least one of `phone`/`email`; Customer: `name` optional/derived, `phone`/`email` optional/derived, at least one reachable contact via derived + supplied must exist. `phone+email` both valid.
- **Cross-field:** `product_id` supplied → validated public product; `order_id` supplied → validated ownership when authenticated.
- **Conditional:** `category` optional CLOSED; `attachment` optional file.
- **State-dependent:** `POST .../close` validates current `enquiry_status == OPEN`; `CLOSED→CLOSED` replay is idempotent where approved, otherwise `409`.
- **Message/Subject constraints:** `subject` 5–200, `message` 10–5000, plain text only, not interpreted as HTML/Markdown; server validates and stores safely; do not silently transform meaningful customer text (validation + safe storage + safe output encoding, not destructive sanitization).
- **Enquiry remains lightweight:** No `payment*`/`delivery_fee`/`order_status` in payload; no `request_id` linkage in V1 (intentional relationship, only `product_id`/`order_id` where valuable).

### 27.19 Errors — Common Error Contract (`data` vs `errors`)

Uses `{"data":…}` success and `{"errors":[…],"meta":{"request_id":…}}` failure per `§15`.

| Failure | API `code` | HTTP |
|---|---|---|
| Missing required `name` (anonymous) or `subject`/`message` | `MISSING_REQUIRED_FIELD` (`field: name` / `field: subject` / `field: message`) | 422 |
| Anonymous without `phone` and `email` | `MISSING_REQUIRED_FIELD` (`field: phone` / `field: email`) | 422 |
| Invalid format/type (`email` bad, `phone` bad, `category` unknown) | `INVALID_FORMAT` / `INVALID_TYPE` / `INVALID_VALUE` | 422 |
| `product_id` not found / inactive / not publicly visible | `RESOURCE_NOT_FOUND` / `PRODUCT_NOT_FOUND` / `INVALID_ENQUIRY` | 404 / 422 |
| `order_id` not found / not owned | `ORDER_NOT_FOUND` / `RESOURCE_NOT_FOUND` / `INVALID_ENQUIRY` (404 **masked** per §15.8, not `403` when ownership fails) | 404 |
| Invalid attachment (size/type/signature/filename) | `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE` | 422 / 413 |
| Not authenticated where required (`GET /me/enquiries`) | `AUTHENTICATION_REQUIRED` | 401 |
| Authenticated but not owner (`Customer A → B enquiry`) | `ENQUIRY_NOT_FOUND` / `RESOURCE_NOT_FOUND` (404 **masked**, never `403`) | 404 |
| Staff without `enquiries.view` / `enquiries.manage` | `FORBIDDEN` | 403 |
| Invalid `enquiry_status` / `category` enum | `INVALID_VALUE` (`field: enquiry_status` / `field: category`) | 422 |
| Invalid Enquiry transition (`CLOSED→CLOSED` re-close without idempotency, or `OPEN→OPEN`) | `INVALID_ENQUIRY` / `CONFLICT` | 409 |
| Anonymous `GET /enquiries` / `GET /enquiries/{id}` | `AUTHENTICATION_REQUIRED` / `RESOURCE_NOT_FOUND` | 401/404 |
| Rate limited (anonymous spam) | `RATE_LIMITED` + `Retry-After` | 429 |
| Unexpected | `INTERNAL_SERVER_ERROR` + `meta.request_id` | 500 |

Only add codes with actual utility per `§15.15`; `INVALID_ENQUIRY` is enquiry-specific invalid payload/state (distinct from `INVALID_REQUEST`).

### 27.20 Representations & Response Examples

**Customer created (ENQ-001 anonymous or authenticated — fields as authorized):**

```json
{
  "data": {
    "id": "enq_01h8y5a1b2c3d4e5f6g7h8j9",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "phone": "+255700000001",
    "subject": "Do you deliver to Dodoma?",
    "message": "Hello, I would like to know if you deliver the Modern Sofa to Dodoma and what the delivery time would be.",
    "category": "DELIVERY",
    "product": {
      "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "name": "Modern 3-Seater Fabric Sofa",
      "slug": "modern-3-seater-fabric-sofa"
    },
    "order": null,
    "enquiry_status": "OPEN",
    "attachments": [
      { "id": "att_01h...", "filename": "reference.jpg", "content_type": "image/jpeg", "size": 843210 }
    ],
    "created_at": "2026-09-01T10:15:00Z",
    "updated_at": "2026-09-01T10:15:00Z"
  }
}
```

- `product` is `{id,name,slug}` summary where `product_id` supplied, `null` otherwise; `order` is `{id,order_reference,status}` summary where `order_id` supplied and validated, `null` otherwise.
- Customer summary where history shows same `subject`/`message` limited.
- Anonymous `ENQ-001` response may omit reversible anonymous-retrieval token (deferred) — ID alone is not bearer.

**Customer summary (ENQ-002 collection) — lighter:**

```json
{
  "data": [
    {
      "id": "enq_01h8y5a1b2c3d4e5f6g7h8j9",
      "subject": "Do you deliver to Dodoma?",
      "enquiry_status": "OPEN",
      "created_at": "2026-09-01T10:15:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 3, "last_page": 1, "has_next": false, "has_previous": false }
  }
}
```

**Staff detail (ENQ-005) — adds contact + internal where authorized:**

```json
{
  "data": {
    "id": "enq_01h8y5a1b2c3d4e5f6g7h8j9",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "phone": "+255700000001",
    "subject": "Do you deliver to Dodoma?",
    "message": "Hello, I would like to know if you deliver the Modern Sofa to Dodoma ...",
    "category": "DELIVERY",
    "product": {
      "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "name": "Modern 3-Seater Fabric Sofa",
      "slug": "modern-3-seater-fabric-sofa"
    },
    "order": {
      "id": "ord_01h8y5a...",
      "order_reference": "OD-000123",
      "status": "DELIVERED"
    },
    "enquiry_status": "OPEN",
    "staff_internal_notes": "Customer asked about Dodoma delivery; check courier zones 2026-09-01.",
    "attachments": [{ "id": "att_01h...", "filename": "reference.jpg", "content_type": "image/jpeg", "size": 843210 }],
    "user_id": "user_01h...",
    "created_at": "2026-09-01T10:15:00Z",
    "updated_at": "2026-09-01T11:00:00Z"
  }
}
```

- Customer `ENQ-002`/`ENQ-003` never exposes `staff_internal_notes`; internal field is operational only.
- `user_id` nullable (`string | null`) in staff view; `null` for anonymous in customer view where relevant.
- `staff_internal_notes` private to staff, never in customer response.

### 27.21 Field Classification Matrix (Normative)

| Field | Anonymous (ENQ-001) | Customer (ENQ-001) | Staff (ENQ-004/005 read) | Server |
|---|---:|---:|---:|---:|
| `name` | Input (required) | Input (Optional/derived, see §27.3) | Read | Store (trimmed, derived fallback) |
| `email` | Input (≥1 of phone/email) | Input (Optional/derived) | Read | Store (lowercased) |
| `phone` | Input (≥1 of phone/email) | Input (Optional/derived) | Read | Store (normalized) |
| `subject` | Input (required) | Input (required) | Read | Store (trimmed, bounded 5–200) |
| `message` | Input (required) | Input (required) | Read | Store (safe, plain text, 10–5000) |
| `category` | Input (optional) | Input (optional) | Read | Validate CLOSED `GENERAL`/`PRODUCT`/`DELIVERY`/`OTHER` |
| `product_id` | Input (optional) | Input (optional) | Read | Validate public product exists |
| `order_id` | Input (optional, discouraged anonymous) | Input (optional) | Read | Validate + ownership check |
| `attachment` | Input (multipart, optional) | Input (optional) | Read | Validate size/type/signature |
| `user_id` | No | No (derived) | No | Derive (`authenticated` or `null`) |
| `enquiry_status` | No | No | Controlled (`OPEN→CLOSED`, optional reopen) | Server-generated `OPEN` default, CLOSED enum |
| `staff_internal_notes` | No | No | Staff/Admin writable | Server/store |
| `order_id` link to payment/delivery_fee | No | No | No | Server later only via Order domain, not enquiry |
| `created_at` / `updated_at` | No | No | No | Server-generated ISO8601 `Z` |

### 27.22 Authorization Matrix (Normative — see §18)

| Operation | Anonymous | Customer | Staff | Admin |
|---|---|---:|---:|---:|
| Create Enquiry (`ENQ-001`) | **Yes** (public, validated + anti-abuse) | **Yes** (→ `Enquiry.user_id` association for `ENQ-002/003`) | **No** — operational-only (Staff handle via `ENQ-004/005/006`) | **No** — operational-only |
| List own Enquiries (`ENQ-002`) | No | **Yes** (`AUTHENTICATED_OWNER` own, `private, no-store`, paginated) | No | Authorized (purpose-bound) |
| View own Enquiry (`ENQ-003`) | No (no anonymous retrieval) | **Yes** (404 masked if not own) | No | Authorized |
| List operational Enquiries (`ENQ-004`) | No | No | **Yes** (`enquiries.view`, private, filter `OPEN` etc.) | **Yes** |
| View operational Enquiry (`ENQ-005`) | No | No | **Yes** (`enquiries.view`) | **Yes** |
| Close / Reopen enquiry (`ENQ-006`) | No | No | **Yes** (`enquiries.manage` + valid state) | **Yes** (same, audited) |
| Upload attachment (`ENQ-007`) | **Scoped** token only | According to parent (`owns` + scoped) | According to parent | According to parent |
| Manage customer account (enquiry context) | No | Own only | No (staff cannot change ownership/credentials) | Authorized only |

`Staff operational` is not ownership; Admin remains explicit + auditable; Customer `User=none` anonymous enquiries are private intake with no retrieval bearer in V1.

### 27.23 Security, Field-Level Exposure & History Preservation

- History preservation: original customer-provided `subject`, `message`, `contact` (`name`/`email`/`phone`), `category`, `product_id`, `order_id`, `attachment` metadata are **immutable history** — stored as submitted. Staff `internal_notes` is separate operational record and never customer-visible. Future audit of enquiry state changes `actor/action/resource/target/timestamp/result` identified (not implemented).
- Field-level before serialization: select representation by actor (`Customer → own without internal_notes`, `Staff → operational with internal_notes + contact`, `Admin → authorized`). Never `model.toArray()` mass-serialization. Private enquiry responses `private, no-store`, not CDN public.
- Search results apply over authorized dataset; pagination cannot leak cross-ownership. Customer `ENQ-002?search=...` searches only own; Staff `ENQ-004?search=` over permitted operational set.
- **Threats addressed:** `anonymous spam/IDOR/customer-to-customer access (ENQ-002/003)/staff privilege escalation/role tampering/order association leakage/private attachment exposure/internal note leakage/XSS via message/oversized message/oversized attachment/search abuse/rate-limit bypass` — see §27.8, §27.14, §27.15, §27.19 for defenses (ownership check, 404 masking, plain-text only, size limits, field allow-list, validation, private caching).

### 27.24 Compatibility — Version 1

Within `v1`, do not change without compatibility review: `field type`, `field meaning`, `requiredness`, `enum value semantics` (`enquiry_status` CLOSED `OPEN`/`CLOSED`, `category` CLOSED), `ownership semantics`, `pagination/sort/filter` shapes, `response envelope` (`data`/`meta`/`errors`), `anonymous creation + no anonymous retrieval` contract. Adding optional `IN_PROGRESS`/`WAITING_FOR_CUSTOMER` statuses later is a formal compatibility decision; new status value is CLOSED-enum event. Closing does not mean delete.

### 27.25 Cross-References

- Resources: `api-resources.md §6` (Enquiry, Enquiry Attachment — ownership, relationships, public/private classification, customer/staff/admin representations, mutable/immutable, optional product/order association, status `OPEN`/`CLOSED`, plain-text message).
- Conventions: `api-conventions.md §27` (anonymous creation, customer ownership, private data, attachment authorization `inherits parent`, product/order optional references, immutable original submission, simple status, XSS-safe plain text, rate-limit candidate).
- Domain: `business-rules.md §10` (General Enquiry: anonymous/customer allowed, contact+message required, not an Order, separate from Request, optional product/order, optional attachment, staff operational, no anonymous retrieval, plain text, immutable message).
- Decisions: `decisions.md ADR/API-ENQ-001..ADR/API-ENQ-008` (anonymous enquiry, ownership, separate from Request, not an Order, no payment/inventory, optional attachments, no anonymous retrieval, immutable message/plain text).
- Request `§26` and Order `§24` remain distinct; Payment `Group H`; Tracking `§25` not Enquiry; Notification `§28` now authoritative (IN_APP).
- Links to Conventions & Resources updated: `§20` now includes Enquiry conventions `§27` and Notification conventions `§28`.

---

## 28. Notification API Contract (Phase 1.27)

> **Authority:** Canonical domain API contract for the **Notification** subsystem (`NOT-001`..`NOT-004`). Consolidates `phases/phase-1.27.md` and is consistent with `Order` (`§24`), `Tracking/Fulfillment` (`§25`), `Request` (`§26`), `Enquiry` (`§27`), `Authentication` (`§17`), `Authorization` (`§18`), `Validation` (`§14`), `Error` (`§15`).
> **Core Principle:** **Notifications communicate business state; they do not create, authorize, or become the source of truth for that state.** The authoritative data remains `Order` / `Request` / `Enquiry` / `Payment` / `Fulfillment` / `User`. If a Notification says `Order shipped` but `Order` says `PROCESSING`, `Order` wins. Notifications are downstream communication derived from authoritative business events.

### 28.1 Notification Resource

A Notification is an independent API resource representing a logical in-app message derived from a business event.

**Canonical fields (IN_APP V1):**

| Field | Type | Exposure | Nullable | Notes |
|---|---|---|---|---|
| `id` | string opaque `not_...` | Recipient only | no | Stable opaque identifier, server-generated |
| `recipient_user_id` | string | Recipient only | no | Derived server-side, never client-supplied |
| `type` | enum CLOSED | Recipient only | no | Machine type `ORDER_SHIPPED`, `NEW_ORDER` etc. (`§28.4`); CLOSED |
| `title` | string | Recipient only | no | Short human title, server-generated |
| `message` | string | Recipient only | no | Human message, server-generated, safe-encoded; not used for machine logic |
| `read_at` | ISO8601 `Z` \| null | Recipient only | yes | `null` → unread, timestamp → read; server-owned; see `§28.8` |
| `is_read` | boolean | Recipient only | no | Derived `read_at != null`; convenience, not second source of truth |
| `target` | `{type, id}` \| null | Recipient only | yes | Safe navigation reference `{type: ORDER\|REQUEST\|ENQUIRY, id: ...}`; authorization still required on target fetch; does not grant access |
| `source` | `{type, id}` \| null | Recipient only | yes | Traceability `{type: ORDER\|REQUEST\|ENQUIRY\|PAYMENT, id: ...}`; does not grant access |
| `created_at` | ISO8601 `Z` | Recipient only | no | Server-generated, authoritative event-derived time |

**Not exposed:** `recipient` email/phone, internal delivery attempts (`SMTP log`), payment secrets, internal notes, full Order payload. `channel` is not exposed in V1 responses unless delivery channels beyond `IN_APP` actually exist (`EMAIL`/`SMS`/`PUSH` deferred).

### 28.2 Recipients — Customer / Staff / Admin

- **Customer:** Personal `notifications.read_own` / `notifications.mark_read_own`. Each notification has single `recipient_user_id = customer`. Only own notifications via `GET /me/notifications`.
- **Staff:** Operational `notifications.read_operational`. Shared operational queue model preferred: `Order created → Staff notification/queue → any authorized Staff can handle; Order remains source of truth`. Alternatively role-filtered personal operational notifications (`recipient_user_id = staff`). For V1 small-business: **Customer = personal read state; Staff = shared operational queue** (separate semantics, see `§28.7`).
- **Admin:** Administrative `notifications.read_operational` where required (`staff approval/security/critical operational`). Not every Staff notification auto-forwarded to Admin.

No `global notification` — backend decides recipients per event.

### 28.3 Channel Architecture

- **V1 primary channel:** `IN_APP` only. Logical `Notification` record is the API contract.
- **Future channels:** `EMAIL` (Group R), `PUSH` (Flutter), `SMS` — each is a **delivery attempt** derived from `Notification`, not a separate `CustomerNotification` / `StaffNotification` resource. `Notification → Delivery attempt(s)` extensibility preserved; `Notification` contract does not change when `EMAIL` added.
- **V1 response:** Do not expose `channel: EMAIL` / `SMS` / `PUSH` unless actually implemented. Do not claim undelivered capabilities.

### 28.4 Notification Types — CLOSED Registry (V1)

All types are `UPPER_SNAKE_CASE`, CLOSED. Unknown type → `422 INVALID_VALUE`.

| Type | Recipient | Source | Description |
|---|---|---|---|
| `ORDER_RECEIVED` | Customer | Order | Order created (`CHK-001` → `PENDING_PAYMENT`) |
| `ORDER_ACCEPTED` | Customer | Order | `PAID → ACCEPTED` (`ORD-007`) |
| `ORDER_PROCESSING` | Customer | Order | `ACCEPTED → PROCESSING` (`ORD-008`) |
| `ORDER_READY_FOR_PICKUP` | Customer | Fulfillment | `PROCESSING → READY_FOR_PICKUP` (`ORD-009`) |
| `ORDER_SHIPPED` | Customer | Fulfillment | `PROCESSING → SHIPPED` (`ORD-010`) |
| `ORDER_DELIVERED` | Customer | Fulfillment | `SHIPPED → DELIVERED` (`ORD-011`) |
| `ORDER_COMPLETED` | Customer | Order | `... → COMPLETED` (`ORD-013`) |
| `ORDER_CANCELLED` | Customer | Order | `... → CANCELLED` (`ORD-004`) |
| `NEW_ORDER` | Staff | Order | New Order operational alert |
| `NEW_MADE_TO_ORDER_REQUEST` | Staff | Request | `REQ-001` |
| `NEW_ENQUIRY` | Staff | Enquiry | `ENQ-001` |

Only events actually required by approved workflows (`§24`/`§26`/`§27`) are registered. Do not create per-product variants (`SOFA_SHIPPED`). Adding `ORDER_SOMETHING_NEW` within `v1` is a contract change; review client behavior. `PAYMENT_*` types remain **Group H**.

### 28.5 Customer Notifications

- Triggered by customer-relevant business events (`ORDER_ACCEPTED`, `READY_FOR_PICKUP`, `SHIPPED`, etc.). `type` + structured `target` (`{type: ORDER, id: ord_...}`) allows `Next.js`/`Flutter` to deep-link to `Order detail` / `Tracking`; target fetch still requires normal Order authorization.
- Machine logic must depend on `type` (`ORDER_SHIPPED`), not `message` (`Your order OD-12345 has been shipped.`). `message` may be server-generated or localized client-side from `type` + context (`order_reference`); frontend must not parse English.
- `payment` context deferred to Group H.

### 28.6 Staff Operational Notifications

- Operational events (`NEW_ORDER`, `NEW_MADE_TO_ORDER_REQUEST`, `NEW_ENQUIRY`) create Staff-visible notifications. Notification is an alert; **Order/Request/Enquiry collection remains the source of truth** — Staff can refresh operational queue independently if notification delivery is delayed.
- For V1 small-business: `Customer: personal read_at`; `Staff: operational queue` — `one Staff reads` does not necessarily mean `all Staff should stop seeing the item`. Shared queue should use `handled/acknowledged` semantics separate from personal `read_at` if needed; do not conflate `read` with `handled`.

### 28.7 Admin Notifications

- Administrative scope (`staff approval/security/critical operational`). Not every operational notification auto-broadcast to Admin. Admin visibility/mutation separate from Staff.

### 28.8 Read/Unread State

- **Semantics:** `read_at = null` → unread; `read_at = timestamp` → read. `read_at` is server-owned. `is_read` is derived convenience.
- **Does not mean:** `Order processed`, `payment succeeded`, `request handled` — never overload read state with business status.
- **Mark read:** `PATCH /api/v1/me/notifications/{notification} {read: true}` or `POST .../read` — canonical is `PATCH` with `{read: boolean}` (only `read` allowed, no `type`/`title`/`recipient`/`target` mutation). Server derives recipient from authentication; `{"recipient_user_id":"..."}` or `{"user_id":"another"}` rejected.
- **Mark all read:** `POST /api/v1/me/notifications/read-all` applies only to authenticated actor's accessible notifications where approved; do not accept `user_id`.
- **Authorization:** Only recipient may mark own notification read; Staff cannot mark Customer notifications via Staff credentials; Admin visibility does not change recipient read state.
- **Synchronization:** Server-side authoritative; `Next.js` and `Flutter` see consistent state via server (`read on Flutter → website shows read`).

### 28.9 Notification Targets

- Targets are server-generated `{type, id}` (e.g., `{type: ORDER, id: ord_01h...}`) referencing `Order` / `Request` / `Enquiry`. Do not allow customer to construct `{target: {type: ORDER, id: another-order}}` to create unauthorized navigation.
- **Not a capability token:** `Notification {target: ORDER, id: B}` does not grant access to `Order B` nor bypass Order ownership validation; normal `GET /me/orders/{order}` authorization still applies.
- Source reference `{source_type, source_id}` traceability similarly server-generated, not client-supplied.

### 28.10 Collection, Pagination, Filtering, Ordering

- **Collection:** `GET /api/v1/me/notifications` — `AUTHENTICATED_OWNER` own (or `OPERATIONAL` own for Staff/Admin via same path with role). Collection already contains `title`, `message`, `type`, `read_at`, `target`, `created_at` — separate `GET /me/notifications/{notification}` detail only if UI needs deep-linking, otherwise collection sufficient.
- **Pagination:** Standard `page`/`per_page` (1–100) → `meta.pagination` per `§4`; deterministic `created_at DESC, id ASC` (newest first) + stable ID tie-breaker. Do not invent cursor pagination unless needed. Do not create notification-specific pagination metadata.
- **Filtering:** Allow-list only: `unread=true` (`?unread=true` boolean), `type=ORDER_SHIPPED` (validated against CLOSED registry), `page`/`per_page`. Do not add free-text search in V1. Staff filters `unread`, `type`, `created_from/created_to` only if operationally useful.
- **Metadata:** `meta.unread_count` (or `meta.pagination` + `meta.unread_count`) preferred if efficiently computable; choose one of `GET .../unread-count` vs `meta.unread_count`, not both without reason. Do not add both.
- **Ordering:** Newest first, deterministic `created_at DESC, id ASC`.

### 28.11 Event Sources

Authoritative events that may create notifications (only approved workflows):

| Business Event | Customer notified? | Staff notified? |
|---|---|---|
| `Order created` (`PENDING_PAYMENT`) | Yes | Yes (`NEW_ORDER`) |
| `Payment confirmed` (`PAID`) | No | Per Group H |
| `Order accepted` (`ACCEPTED`) | Yes | Via Order queue |
| `Order processing` (`PROCESSING`) | Yes | Via Order queue |
| `Ready for pickup` (`READY_FOR_PICKUP`) | Yes | Via Order queue |
| `Shipped` (`SHIPPED`) | Yes | Via Order queue |
| `Delivered` (`DELIVERED`) | Yes | Via Order queue |
| `Completed` (`COMPLETED`) | Yes | Via Order queue |
| `Order cancelled` (`CANCELLED`) | Yes | Yes if relevant |
| `New Made-to-Order Request` (`REQ-001`) | No | Yes |
| `New Enquiry` (`ENQ-001`) | No | Yes |
| Payment event | Per Group H | Per Group H |

Do not send notification for every internal event. `PAYMENT_*` mapping belongs to Group H.

### 28.12 Notification Creation Rules

- **No client creation:** `POST /notifications` for normal customers/Staff/Admin is prohibited. Notifications are generated by trusted backend events only. `{"type":"ORDER_SHIPPED","title":"...","recipient_user_id":"..."}` from client rejected `422`/`403`.
- **No arbitrary content:** `title`/`message`/`target`/`recipient`/`type`/`created_at` server-generated from authoritative data (`order_reference`, `product name` safely encoded). Do not allow client-submitted arbitrary content to become system Notification.
- **Data minimization:** Notification contains only contextual data to tell recipient what happened (`order_reference`, `type`, `target.id`); do not embed `entire Order`, `full customer profile`, `full delivery address`, `internal notes`, `payment secrets`.
- **XSS:** Product name, customer name, request text, enquiry subject may appear in notifications — treat as untrusted, render safely (backend/frontend encode).

### 28.13 Privacy

- **Classification:** `Customer notifications → PRIVATE`, `Staff operational → PRIVATE/INTERNAL`, `Admin → ADMINISTRATIVE`. Never public.
- **Object-level:** `Customer A → Customer B notification` via `GET /me/notifications/{id}` must fail `404 RESOURCE_NOT_FOUND` (masked per `§15.8`), not `403` leaking existence; `GET /me/notifications?unread=true` paginates only own. Staff `view_operational` only operational queue, not private customer notifications unrelated to task; Admin limited.
- **No privilege escalation:** Notification `target` does not bypass target resource authorization.

### 28.14 Caching

- **Never public-cache:** `GET /me/notifications` → `Cache-Control: private, no-store` (Customer), operational → `private/internal`. Not CDN public.
- **Multi-device:** Server authoritative `read_at` ensures `Flutter read → website shows read`.

### 28.15 Failure / Retry Principles

- **Downstream only:** `Order shipped → Order is SHIPPED` authoritative. If `Notification generation temporarily fails`, Order must still remain `SHIPPED`. `Notification failure must not roll back business transaction` unless explicitly required.
- **No critical-path dependency:** `Checkout → Order` must not fail because notification cannot be persisted; Staff can still discover Order via operational queue. System remains resilient; eventual consistency (`Order state updates immediately, notification appears shortly afterward`) acceptable.
- **Idempotency / deduplication:** Same source event delivered twice (e.g., `Order SHIPPED` processed twice) must not create two identical notifications for same recipient. Use event IDs / idempotency key at processing boundary. `Notification → Delivery attempt(s)` model preserves future multi-channel without duplicating logical message.
- **Retry:** Later infrastructure may retry creation/delivery safely (outbox/event pattern candidate, not implemented here). Do not make controller response depend on completing every delivery. Background/queued dispatch is future candidate.

### 28.16 Channel Architecture

- Logical `Notification` is channel-agnostic. Future `EMAIL` (Group R) `→ Email delivery attempt` and `PUSH` (Flutter) `→ Push delivery` are both derived from `Notification`. Existing `IN_APP` contract remains valid when `EMAIL` added.

### 28.17 Email Deferral

- Real `EMAIL` delivery remains **Group R**. `Business event → Notification → Email delivery` (Group R). Notification contract does not depend on email; `anonymous request/enquiry` does not create persistent in-app notification without authenticated recipient — may trigger `email` in Group R later, but not in-app.

### 28.18 Endpoint Inventory (Authoritative)

| ID | Method | Path | Actor | Auth | Authorization | Purpose | Idempotency | Concurrency |
|---|---|---|---|---|---|---|---|---|
| `NOT-001` | `GET` | `/api/v1/me/notifications` | Customer, Staff, Admin | Required | `AUTHENTICATED_OWNER` own (Customer), `OPERATIONAL` own recipient-scoped (Staff), `ADMIN` limited own recipient-scoped (Admin) per `§18` | List own notifications (paginated, unread filter) | — | — |
| `NOT-002` | `PATCH` | `/api/v1/me/notifications/{notification}` | Customer, Staff, Admin | Required | `AUTHENTICATED_OWNER` own / `OPERATIONAL` own for Staff — only recipient may mark own read (`read: boolean` only) | Mark notification read/unread | Designed idempotent | Low |

Potential (only if approved): `NOT-003 POST /api/v1/me/notifications/read-all` (mark all accessible read) and `NOT-004 GET /api/v1/notifications/operations` (shared operational queue) — include only if frontend genuinely needs and through same `Notification` resource (no `CustomerNotification`/`StaffNotification` split). Do not create `GET /me/notifications/{notification}` detail unless UI needs deep-linking.

*Notes:* `NOT-001` via `/me` holder-scoped; no `POST /notifications` for clients. `NOT-002` body `{read: true}` or `{read: false}` only; server derives recipient.

### 28.19 Authorization Matrix (Normative — see §18)

| Operation | Anonymous | Customer | Staff | Admin |
|---|---|---:|---:|---:|
| List own notifications (`NOT-001`) | No | **Yes** (`AUTHENTICATED_OWNER` own, private, paginated) | **Yes** where applicable (`notifications.read_operational` / `OPERATIONAL` own) | **Yes** where applicable (`ADMIN` limited own) |
| Mark own notification read (`NOT-002`) | No | **Yes** (only own, `read` only) | **Yes** for own/operational own | **Yes** where applicable (does not change recipient state without scope) |
| Create notification | No | No | No | No* |
| View another customer's notification | No | No | No | Only if explicitly approved |
| Change notification content (`type`/`title`/`message`/`recipient`/`target`/`created_at`) | No | No | No | No* |
| Mark all read (`NOT-003` if approved) | No | **Yes** (own accessible) | **Yes** where applicable | **Yes** where applicable |

`*` Admin broadcast not part of V1; Staff cannot mark Customer notifications via Staff credentials; Staff operational queue read does not equal handled.

### 28.20 Notification Type Registry (Normative — CLOSED)

See `§28.4` table. `PAYMENT_*` deferred to Group H. `type` validated against registry; unknown → `422 INVALID_VALUE` `field: type`. Message locale: `type` + structured context (`order_reference`) preferred; frontend renders display text, not parsing English.

### 28.21 Event-to-Notification Matrix (Normative)

| Business Event | Customer | Staff |
|---|---|---|
| `Order created` | Yes (`ORDER_RECEIVED`) | Yes (`NEW_ORDER`) |
| `Order accepted` | Yes (`ORDER_ACCEPTED`) | Via Order queue |
| `Order processing` | Yes (`ORDER_PROCESSING`) | Via Order queue |
| `Ready for pickup` | Yes (`ORDER_READY_FOR_PICKUP`) | Via Order queue |
| `Shipped` | Yes (`ORDER_SHIPPED`) | Via Order queue |
| `Delivered` | Yes (`ORDER_DELIVERED`) | Via Order queue |
| `Completed` | Yes (`ORDER_COMPLETED`) | Via Order queue |
| `New Made-to-Order Request` | No | Yes (`NEW_MADE_TO_ORDER_REQUEST`) |
| `New Enquiry` | No | Yes (`NEW_ENQUIRY`) |

Prevents random notification creation; implementation must reference this matrix.

### 28.22 Security, Field-Level Exposure & History Preservation

- History preservation: Notification is append-only logical message; `read_at` toggle does not rewrite `type`/`title`/`message`/`target`. Staff operational queue vs personal read semantics separated.
- Field-level before serialization: `Customer → own without internal delivery logs`, `Staff → operational without private customer notifications`, `Admin → limited`. Never `model.toArray()`.
- Threats: `Customer A → B notification IDOR`, `Customer cannot create/change recipient/target`, `Staff cannot read unrelated private notifications`, `Notification cannot expose internal notes/credentials/provider secrets` — see `§28.13`.

### 28.23 Compatibility — Version 1

Within `v1`, do not change without review: `field type`, `field meaning`, `enum value semantics` (`type` CLOSED, `read_at` semantics), `target` semantics, `recipient` semantics, `pagination/filter` shapes, `response envelope` (`data`/`meta`/`errors`). Adding `ORDER_SOMETHING_NEW` within `v1` is a contract change. `NOT-001` collection metadata `unread_count` vs `GET .../unread-count` choice is stable once published.

### 28.24 Cross-References

- Resources: `api-resources.md §7` (Notification — recipient, relationships, representation, read state `read_at`, target `type/id`, source, privacy `PRIVATE`).
- Conventions: `api-conventions.md §28` (ownership, read-state `read_at null`→unread, target server-generated, event-derived, private caching, CLOSED types).
- Domain: `business-rules.md §13` (Notifications communicate business events, not authoritative; Customer private, Staff operational, Admin administrative; failure does not roll back transaction; Email Group R).
- Decisions: `decisions.md ADR/NOT-001..008` (downstream, private, not capability token, recipient-scoped read, not source of truth, IN_APP, Group R, failure does not roll back).
- Order `§24` / Tracking `§25` / Request `§26` / Enquiry `§27` remain sources of truth; Payment `Group H`; Email `Group R`.
- Links to Conventions & Resources updated: `§20` now includes Notification conventions `§28`.


