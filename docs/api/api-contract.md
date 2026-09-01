# API Contract — Furniture E-Commerce Platform (Consolidated)

> **Version:** `v1` — base `/api/v1` · **Status:** Phase 1.23 — Order API Contract (Customer-Owned Historical Records, State Machine, Delivery-Fee Model B)
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

> **Authority note:** This section is the **authoritative Version 1 endpoint inventory** — stable IDs, versioned paths, actors, auth/authz, purpose, and contract references. It answers *What exists? Who uses it? What does it do?* Implementation (routes/controllers/middleware/FormRequests) remains deferred. All paths use `/api/v1` per Phase 1.8; roles are CLOSED `CUSTOMER`/`STAFF`/`ADMIN`; reuse global conventions (query Phase 1.11, pagination Phase 1.12, response Phase 1.13, input Phase 1.14, validation Phase 1.15, errors Phase 1.16, auth Phase 1.17, authz Phase 1.18). Payment/webhook endpoints are placeholders owned by Group H (see §19.12). **Current status (accepted):** `PROPOSED` (Phase 1.19) for all V1 endpoints (`PROPOSED*` for `PAY-001/002`/`WEBHOOK-001` Group H placeholders); **Target status:** `APPROVED` after Phase 1.21 review — no V1 endpoint is `APPROVED` until then. Master table `Status` column reflects **current** `PROPOSED`/`PROPOSED*`; target `APPROVED` retained for implementation tracking (see §19.15).

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
| `REQ-001` | POST | `/api/v1/requests` | Request | Anonymous, Customer | No / Yes | **PUBLIC** submit (validated + anti-abuse later) | Submit made-to-order request (anonymous allowed) | PROPOSED |
| `REQ-002` | GET | `/api/v1/me/requests` | Request | Customer | Yes | `AUTHENTICATED_OWNER` own | List own requests | PROPOSED |
| `REQ-003` | GET | `/api/v1/me/requests/{request}` | Request | Customer | Yes | `AUTHENTICATED_OWNER` owns request | Get own request detail | PROPOSED |
| `REQ-004` | GET | `/api/v1/requests` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.view` | List operational requests | PROPOSED |
| `REQ-005` | GET | `/api/v1/requests/{request}` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.view` | Get operational request | PROPOSED |
| `REQ-006` | PATCH | `/api/v1/requests/{request}` | Request | Staff, Admin | Yes | `OPERATIONAL` `requests.manage` | Update operational request state | PROPOSED |
| `REQ-007` | POST | `/api/v1/requests/{request}/attachments` | Request | Anonymous* (scoped), Customer | **Scoped** — server-issued upload token (from `REQ-001` response, single-use/time-limited) + parent ownership; predictable ID alone insufficient | Upload request attachment — **preferred: multipart on `REQ-001` creation**; separate `POST` only with scoped token (anonymous requires token), private to parent | PROPOSED |
| `ENQ-001` | POST | `/api/v1/enquiries` | Enquiry | Anonymous, Customer | No / Yes | **PUBLIC** submit (validated + anti-abuse) | Submit general enquiry (anonymous allowed) | PROPOSED |
| `ENQ-002` | GET | `/api/v1/me/enquiries` | Enquiry | Customer | Yes | `AUTHENTICATED_OWNER` own | List own enquiries | PROPOSED |
| `ENQ-003` | GET | `/api/v1/me/enquiries/{enquiry}` | Enquiry | Customer | Yes | `AUTHENTICATED_OWNER` owns enquiry | Get own enquiry detail | PROPOSED |
| `ENQ-004` | GET | `/api/v1/enquiries` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.view` | List operational enquiries | PROPOSED |
| `ENQ-005` | GET | `/api/v1/enquiries/{enquiry}` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.view` | Get operational enquiry | PROPOSED |
| `ENQ-006` | PATCH | `/api/v1/enquiries/{enquiry}` | Enquiry | Staff, Admin | Yes | `OPERATIONAL` `enquiries.manage` | Update operational enquiry state | PROPOSED |
| `ENQ-007` | POST | `/api/v1/enquiries/{enquiry}/attachments` | Enquiry | Anonymous* (scoped), Customer | **Scoped** — server-issued upload token (from `ENQ-001` response, single-use/time-limited) + parent ownership; same token model as `REQ-007` | Upload enquiry attachment — **preferred: multipart on `ENQ-001` creation**; separate `POST` only with scoped token, private to parent | PROPOSED |
| `NOT-001` | GET | `/api/v1/me/notifications` | Notification | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own — **Customer**: own (customer notifications); **Staff**: `OPERATIONAL` own, recipient-scoped (operational notifications: new order/request/payment event); **Admin**: `ADMIN` limited own, recipient-scoped (administrative notifications as needed) | List own notifications (holder-scoped via `/me`, paginated) | PROPOSED |
| `NOT-002` | PATCH | `/api/v1/me/notifications/{notification}` | Notification | Customer, Staff, Admin | Yes | `AUTHENTICATED_OWNER` own (read/unread only) — **Customer**: own; **Staff**: `OPERATIONAL` own, recipient-scoped; **Admin**: `ADMIN` limited own, recipient-scoped | Mark notification read/unread (only `read`/`unread` mutable; content immutable) | PROPOSED |
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

> `*` Payment/webhook endpoints are placeholders marked `PROPOSED*` with Owner `Phase Group H` — no provider selection, no detailed payloads (see §19.12). All other endpoints are **current `PROPOSED`**, target `APPROVED` after Phase 1.21 review (see authority note and §19.15).

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
- **Payment failure aftermath:** deferred to Group H; payment failure does not automatically destroy valid cart intent unless approved payment/order workflow explicitly requires it.
- **Partial order risk:** Failed checkout must not leave an incomplete order without clear reconciliation (`Order created but response says error` ambiguity prohibited). Transaction/idempotency design must prevent this; order creation + inventory + cart transition are atomically bounded (see §23.9).
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
// PICKUP — fee finalized at creation (or DELIVERY after Staff/Admin sets fee)
// delivery_fee_status=FINALIZED marks final amounts; payment is present and can be initiated
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
    "payment": {
      "payment_status": "PENDING",
      "amount": { "amount": 170000000, "currency": "TZS" }
    }
  }
}
```

- **Explicit pending representation:** `delivery_fee: null` + `delivery_fee_status: "PENDING"` marks provisional state; `total` equals `subtotal` but is **provisional** and MUST NOT be displayed or submitted as final amount. Frontend must check `delivery_fee_status === "PENDING"` and show “Delivery fee pending — Staff will confirm” instead of final total. `payment: null` signals payment blocked.
- **Blocked payment:** When `delivery_fee_status=PENDING`, `POST /api/v1/payments` (`PAY-001`) MUST be rejected with `409 CONFLICT` `code: "DELIVERY_FEE_PENDING"` (or `422 INVALID_ORDER_TRANSITION` per registry, mapped to 409 in endpoint contract) — never accept provisional `total` as payment amount. After Staff/Admin sets fee (`delivery_fee: {amount: 2500000, currency:"TZS"}`, `delivery_fee_status: "FINALIZED"`, `total: {amount: 172500000,...}`), payment becomes eligible (`payment` present, `PAY-001` allowed and amount equals authoritative `total`). For `PICKUP`, `delivery_fee_status` is `FINALIZED` at creation (fee 0, total final immediately).

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
- **Finalization endpoint `ORD-014` `POST /api/v1/orders/{order}/delivery-fee`** (Staff/Admin, `Idempotency-Key` **Required**, concurrency **Critical**): sets fee from `PENDING` → `FINALIZED` **before** `PAID`. `delivery_fee_status` must be `PENDING` and `fulfillment_type=DELIVERY` and `status=PENDING_PAYMENT`; otherwise `409 INVALID_ORDER_TRANSITION` / `ORDER_STATE_CONFLICT`. Transitions `delivery_fee null → {amount:int (>0, integer minor units), currency:"TZS"}` and `delivery_fee_status PENDING → FINALIZED`, recomputes `total = subtotal + delivery_fee.amount` authoritative, makes `payment` eligible. Single finalization; repeated calls with same `Idempotency-Key` replay original; same key with different `amount` → `409 DUPLICATE_OPERATION`; `PENDING→FINALIZED` only once before `PAID` (controlled admin correction after `PAID` is separate audited workflow, not normal staff).
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
| `PENDING_PAYMENT` | `admin cancel` (controlled, explicit) | Admin `orders.manage` (explicit) | Any | audit, not customer 20-min window | `CANCELLED` |

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
| `delivery_fee` | `PENDING→FINALIZED` once before `PAID`; thereafter controlled correction only, not unrestricted | Read (own) | Authorized (`inventory/fee` permission, audit) | Authorized (audit) |
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
- Next: `Phase 1.24 Order Tracking & Fulfillment` will isolate `PICKUP vs DELIVERY / READY_FOR_PICKUP / SHIPPED / DELIVERED` timeline details without duplicating this core Order contract.


