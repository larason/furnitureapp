# API Contract — Furniture E-Commerce Platform (Consolidated)

> **Version:** `v1` — base `/api/v1` · **Status:** Phase 1.15 Baseline (Validation Conventions)
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

**Categories:**

- Structural/Input: `INVALID_TYPE`, `INVALID_FORMAT`, `INVALID_VALUE`, `MISSING_REQUIRED_FIELD`
- Business: `PRODUCT_NOT_PURCHASABLE`, `INSUFFICIENT_STOCK`, `ORDER_NOT_CANCELLABLE`, `INVALID_ORDER_TRANSITION`
- Authorization: `NOT_AUTHENTICATED`, `FORBIDDEN`, `RESOURCE_NOT_OWNED`
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

## 15. Links to Conventions & Resources

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility, **input**, **validation** §16).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure and per-resource input + validation §10–11).
- Domain: `docs/domain/business-rules.md` (business meaning, validation authority).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).
