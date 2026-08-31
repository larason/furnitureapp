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

## 18. Links to Conventions & Resources

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility, **input**, **validation** §16, **errors** §17, **authentication** §18).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure and per-resource input + validation + **errors** §10–11, **authentication** §12).
- Domain: `docs/domain/business-rules.md` (business meaning, validation authority, **error ↔ rule mapping §14**, **authentication §17**).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).
