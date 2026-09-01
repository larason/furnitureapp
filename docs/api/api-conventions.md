# API Conventions — Consolidated (Response, Serialization, Compatibility, Validation, Errors, Authentication)

> **Authority:** Reusable serialization, response, validation, error and authentication conventions for `v1` (`/api/v1`). Consolidates `phase-1.13.md` + `phase-1.11` / `phase-1.12` + `phase-1.14` / `phase-1.15` / `phase-1.16` / `phase-1.17` rules. Project must not create separate permanent `api-response-*`, `api-validation-*`, `api-errors-*` or `auth-*` docs per tiny decision — this file plus `api-contract.md` / `api-resources.md` is the consolidated knowledge per `phase-1.13.md §2`.

## 1. Response Envelope

| Case | Envelope | Notes |
|---|---|---|
| Single resource | `{"data": {object}}` | `data` object, not array. Missing resource → `errors` (not `data:null`). |
| Collection | `{"data": [objects], "meta": {"pagination": {...}}}` | `data` array (empty `[]` for no records). `meta` reserved for pagination / `request_id`. |
| Error | `{"errors": []}` | No `data` sibling; `data: {error:"…"}` is forbidden. Details deferred to error phase. |

- Success uses `data` only; never `result`, `resource`, `payload`, `items` as top-level. Clients rely on single primary member.
- Collections always nest pagination under `meta.pagination` (not sibling `pagination`, not `meta` top-level page fields) — leaves `meta` for future `request_id` without collision.

## 2. Naming

- **Canonical:** `snake_case` everywhere (`product_type`, `created_at`, `delivery_fee`, `order_status`, `request_status`, `enquiry_status`, `payment_status`, `order_reference`, `sort_direction`). Query params, JSON fields and metadata share one naming.
- No `productType` / `product-type` mixing. DB `order_status_history` → API `status-history` path still snake JSON internally.

## 3. Timestamps

- **ISO 8601 / RFC 3339 UTC with `Z`:** `2026-08-30T15:30:00Z`. Not `2026-08-30 15:30:00`, not `30/08/2026`, not `1756567800`.
- Lifecycle: `created_at`, `updated_at` consistently where exposed; only useful to client, not every DB timestamp. Changing ISO → Unix within `v1` is breaking.

## 4. Money (Single Global Rule)

- **Amount:** JSON number **integer minor units (cents, 1 TZS = 100)**. Never decimal string, never pre-formatted. Same physical model as query (`?min_price=50000000` = same value as `{"amount":50000000}`).
- **Shape:** `{ "amount": 125000000, "currency": "TZS" }`. Bare `price: 1250000` without `currency` is not used; `price: "TZS 1,250,000"` is forbidden.
- **Applies to:** `product.price`, `variant.price`, `order_item.unit_price`, `order.subtotal`, `order.delivery_fee`, `order.total`, `payment.amount` — identical shape. Zero-decimal currencies would be documented with minor-unit semantics preserved (TZS uses `cents` even if display hides cents).
- **Single price contract:** `product.price` is **required, non-null** for every public product (`IN_STOCK` and `MADE_TO_ORDER`) — same `{amount,currency}`. For `MADE_TO_ORDER` it is display/starting-at price only (informational, never authoritative for checkout/cart). `MADE_TO_ORDER → checkout prohibited` regardless of price (`api-resources.md §10.1`). Making `price` nullable/omittable would be breaking per `api-contract.md §9`.
- **Compatibility:** `price: number` → `price: {amount,currency}` within `v1` is breaking — chosen once here.

## 5. Nulls & Absence

- **`null`:** Field is in contract but currently has no value. E.g., `"delivery_address": null` for `PICKUP` order; `"variant_id": null` where no variant.
- **Absent (omitted):** Field not part of selected representation (e.g., `delivery` subresource absent for `PICKUP`; `low_stock` not a filter). 
- Never alternate `null` / `""` / `false` / omitted for same semantic. Changing always-present → `null` is a contract change.

## 6. Booleans

- JSON booleans `true` / `false` (lowercase, no quotes). Never `"true"`, `1`, `"yes"`. Includes `has_next`, `has_previous`, `is_active`, `is_primary`.

## 7. Numerics

- JSON numbers for quantities. Not strings unless external textual identifier.

## 8. Enums — CLOSED (Single Availability Exception)

- All V1 response enums are **CLOSED** and `UPPER_SNAKE_CASE` (`IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`, `PENDING_PAYMENT` … `CANCELLED`, etc.). Only documented values valid; `ON_HOLD` additive is breaking and requires review. Response sends machine value (`"status": "READY_FOR_PICKUP"`), frontend maps to display (`Being prepared`). **Explicit frozen exception:** `availability` is lowercase `"available" | "unavailable"` per `api-contract.md §3.9` to match `?availability=` filter; `stock_indicator` remains `UPPER_SNAKE_CASE` (`IN_STOCK`/`LOW_STOCK`/`MADE_TO_ORDER`). `availability_display` is not a valid field; `stock_indicator` is canonical. This is the single exception to `UPPER_SNAKE_CASE`; all other enums stay `UPPER_SNAKE_CASE`.

## 9. Relationships & Embedding

- **Embedded small child data:** `product.images[]` as `[{id, url, alt_text, sort_order, is_primary}]` (not `["url"]` one endpoint and objects another).
- **Large/independent:** `product.category → {id, slug, name}` summary; full via `/api/v1/categories/{category}` for category details (variants remain via `/api/v1/products/{product}/variants` only for variants).
- **Sensitive:** `order.payment → {payment_status, amount: {amount,currency}}` limited summary, not provider secrets.
- **Bounded:** No recursive `product → category → products → category`; use summary/reference to break cycles.
- **V1 `include`/`fields` deferred:** No `?include=category` or `?fields=id,name` — dedicated subresources, not dynamic graph.

## 10. Links

- Pagination URLs deferred — metadata-only (`has_next` etc.) in V1. If added later: under `links` or `meta.links` consistently, absolute or relative uniformly, with query preservation (filters/sort retained), deterministic ordering.
- Per-resource `self` link omitted by default; if later adopted, every resource would include `links.self` consistently.

## 11. Pagination Placement

- Always `meta.pagination` with `current_page (1-based), per_page (default 20, 1–100), total, last_page, has_next, has_previous`. Empty `total=0 → last_page=1, current_page=1`; beyond `last_page` → empty `data[]` with metadata (not error). `page`/`per_page` + sort/filter/search are cache keys, with `private` caching for protected collections.

## 12. Serialization & Exposure

- Explicit allow-lists per audience; no `model.toArray()` mass serialization. Levels: `PUBLIC` (product name/slug/price/availability), `CUSTOMER` (own `order_reference`, `status`, `items`, `totals`), `STAFF/ADMIN` (operational `quantity`/`reserved_quantity`, admin notes), `INTERNAL` (never serialized — passwords, tokens, provider secrets, internal paths). Check authorization before serializing.

## 13. Compatibility

- Within `v1`: no silent rename, type/meaning/nullability change, or removal. Adding truly optional field is non-breaking per `api-versioning-strategy.md`. Changing `price` shape or `created_at` format is breaking.

## 14. Cross-Platform Consistency

- Same field means same thing for Next.js, Flutter, Admin. No `price=cents` vs `price=TZS` per client; API is shared infrastructure — frontends adapt to API, not vice versa.

## 15. Request Input Conventions (Consolidated — Phase 1.14)

> **Authority:** Single input language for `v1` (`/api/v1`). Covers JSON, naming, types, nullability, arrays, unknown fields, server-authoritative values, mass-assignment and idempotency per `phase-1.14.md`.

- **Transport:** `Content-Type: application/json` UTF-8 for normal create/update/action; `multipart/form-data` only for file-upload attachments. Incorrect content type → validation error, not silent guess.
- **Naming:** `snake_case` everywhere — same as response/query (`product_id`, `fulfillment_type`, `delivery_fee`). DB names do not dictate API (`reserved_quantity` not accepted from customer). No `productId`/`product-id` mixing.
- **Types strict:** JSON types strict (`quantity: 2` not `"2"`, `is_active: true` not `"true"`). No broad coercion; numeric `quantity` integer `≥1`, `percentage` etc. range/precision validated server-side. Timestamps ISO8601 UTC `Z`; money as `§4` shape `{amount:int minor units, currency:"TZS"}` — never `"TZS 30,000"` string.
- **Required/optional/conditional:** Each endpoint documents each input field as `required | optional | conditional | read-only | server-generated`. Not inferred from DB nullability. Example: `delivery_address` is **conditional** — required when `fulfillment_type=DELIVERY`, omitted/`null` for `PICKUP`. Optional field omitted (`{"name":"Modern Sofa"}`) is distinct from explicit `{"description":null}` unless contract defines equivalence.
- **Nulls/empty/whitespace/case:** `null` **only** where explicitly nullable (e.g., `delivery_address: null` for `PICKUP`); `{"quantity":null}` where required is error. Empty `""` is not universal “not supplied” — `{"phone":""}` does not mean omitted unless contract says so; prefer omission/`null`. Server trims/normalizes whitespace (`"   Modern Sofa   "` → `"Modern Sofa"`) where appropriate; clients may cosmetic-trim but server authoritative. Enums case-sensitive exact (`"IN_STOCK"` not `in_stock`).
- **Arrays & nested:** Single element type only (`items:[{product_id, quantity}]`); empty `[]` invalid where ≥1 required (not equivalent to omission); duplicate items (two lines `product_id:"123"`) handling per contract (`invalid`/`merged`/separate). Nested objects explicitly defined (`shipping_address:{name,phone,address_line,city}`); arbitrary nesting not persisted; ownership respected — `{"order":{"customer_id":"someone-else"}}` cannot hijack ownership (server derives from auth).
- **Unknown fields:** **Rejected** (validation error) for strict create/update/action inputs — catches typos, stale clients, version mismatch, mass-assignment. Forward-compatibility ignore only where explicitly documented. Not left to Laravel `$request->all()` default.
- **Server-authoritative & read-only:** `id`, `created_at`, `updated_at`, `order_reference`, `current_order_status`/`status`, `payment_status`/`payment_confirmation`, `inventory` (`quantity`/`reserved_quantity`/`available_quantity`), `final_order_total`/`approved_delivery_fee`, `order_customer_identity`, `status_history` are **never client-settable**; if sent, ignored or rejected. Frontend never controls price, total, inventory, payment confirmation, or order status transition (those are actions, not field writes).
- **Client-controlled:** Only `product_id`/`variant_id` + `quantity`, `fulfillment_type`, `delivery_address`/contact, `request` details + contact, `enquiry` message, profile `name/phone` (explicit allow-list per endpoint). Checkout input is `{"fulfillment_type":"DELIVERY","delivery_address":{...}}` only — backend resolves cart contents, prices, `delivery_fee`, `subtotal`/`total`, `order_reference`. `PUT` means complete replacement where permitted; otherwise `PATCH` partial (only fields being changed).
- **Action & role layering:** Create supplies business inputs → server supplies `id/timestamps/status`; `PATCH` contains only changed fields with allow-list (e.g., `name`/`phone` yes, `role`/`order_total` no); actions (`cancel`/`ship`) send minimal `{"note":...}` not full order + `{"status":"SHIPPED"}`. Same resource has different accepted inputs per actor: Customer `→ own profile`; Staff/Admin `→ operational order state`.
- **Historical immutability:** Updating current product price never rewrites historical `order_item` snapshot; delivery fee policy changes never rewrite historical order fee.
- **Idempotency-sensitive:** `checkout`, `payment initiation`, `critical order actions` require later `Idempotency-Key` design — duplicate tap/reload/retry must not duplicate business effects (not implemented here).
- **Size/security:** Reasonable max JSON body / file upload / array size / message length enforced server-side; parameter pollution (`quantity=2&quantity=100`) → deterministic rejection; SQL injection/XSS/CSRF bypass never mitigated by frontend alone. Transport → Schema/type → Authentication → Authorization
  → Domain → Concurrency/transaction → External → Persistence/workflow (backend pipeline).
- **File uploads:** Attachments optional via `multipart/form-data` (or pre-uploaded reference per contract); storage provider not selected now; logical field names stay `snake_case` consistent with JSON.
- **Mass-assignment protection:** Laravel must not `$request->all() → model fill`; validated input → DTO/command → domain logic. Domain model not coupled to HTTP request structure.

## 16. Validation Conventions (Consolidated — Phase 1.15)

> **Authority:** Single validation architecture for `v1`. Consolidates `phase-1.15.md`. No separate `validation.md` / `api-validation.md` per §2 documentation strategy. Reusable rules here; normative hierarchy in `api-contract.md §14`; business meaning in `docs/domain/business-rules.md`.

### 16.1 Layers

- **Transport:** `Content-Type` correct, JSON syntactically valid, size within limits, method/media supported. Not business error. `malformed JSON`, `unsupported content type`, `body too large` → transport failure.
- **Schema/Input:** Field structure/types correct: `quantity int`, `email format`, `name length`, `fulfillment_type enum CLOSED`. Does not decide stock.
- **Authentication vs Authorization:** `Authentication → who?` (valid session/token). `Authorization → allowed on resource?` (`Customer own order`, etc.). Valid structured request may still fail authorization. Do not conflate.
- **Domain/Business:** Business-rule allowed? `IN_STOCK purchasable`, `MADE_TO_ORDER not checkout-eligible`, `within cancellation window`, `fulfillment matches delivery info`, `transition allowed`. Enforces Phase 1.3 invariants.
- **Cross-field, Conditional, State-dependent, Concurrency:** Cross-field requires multiple fields together; conditional defines `PICKUP` vs `DELIVERY` requirements; state-dependent checks current authoritative state; concurrency ensures correctness under race (transactional).

**Rule:** Never move domain rules entirely to client. Clients may duplicate for UX; API independently enforces invariants. Frontend validation advisory, backend authoritative.

### 16.2 Type, Enum & Null Strictness

- JSON types strict: `quantity:2` not `"2"`, booleans not `1`. No broad coercion. Enums **CLOSED**: `IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`, `PENDING_PAYMENT`…`CANCELLED` only; unknown → validation error, not `OTHER`. New enum value is compatibility decision. Exception `availability` lowercase `available|unavailable` frozen per `api-contract.md §3.9`; does not extend to others.
- `null` only where explicitly nullable (e.g., `delivery_address:null` for `PICKUP`, `product_id:null` for custom request). `{"quantity":null}` where required is error.
- Empty `""` not universal "not supplied"; whitespace trimmed server-authoritatively where appropriate; meaningful whitespace (e.g., `notes` formatting) preserved; enums exact case.

### 16.3 Query, Pagination, Sorting, Filtering, Relationships

- `page ≥1`, `1 ≤ per_page ≤100` (Phase 1.12). Only documented sort fields accepted; never map arbitrary input to DB columns (correctness + security). Only documented filter fields/operators; no arbitrary DSL. Query strings not inherently safe.
- Relationships: `product_id`/`variant_id`/`category_id` — validate `exists` plus when required `belongs to parent, active, allowed in operation`. Example: `variant exists AND belongs to product AND active AND purchasable` — not `variant_id exists` alone.

### 16.4 Validation Order & Pipeline

```
Transport → Schema/type → Authentication → Authorization → Domain (cross-field/state) → Concurrency/transaction → External → Persistence/workflow
```

- Do not access business records before schema validation; do not run external operations before authorization.
- Do not leak authorization: `Order OD-xxx exists but not owned` must not be revealed via invalid queries; resource exposure/authorization design avoids enumeration. `Not Found vs Forbidden` mapping deferred to error phase but leakage prohibited now.
- DB constraints (`uniqueness, non-null, foreign-key`) complement domain validation; DB cannot decide `MADE_TO_ORDER cannot checkout`. Keep separate; DB is last line of defense.

### 16.5 Inventory, Pricing, Delivery Fee (Reusable)

- **Inventory:** `read current stock → validate quantity → reserve/consume in transaction`. `SELECT then UPDATE` without concurrency control prohibited. `product exists, active, IN_STOCK, variant valid/belongs-to, stock sufficient` — current availability still checked.
- **Pricing:** Customer sends `product_id+quantity` / checkout `fulfillment_type+address`; backend resolves `current price, subtotal, delivery_fee, total`. Never validate customer-submitted total as authoritative.
- **Delivery fee:** `DELIVERY` → customer supplies delivery info; business determines fee. Customer override of `approved delivery fee` rejected.

### 16.6 Cart / Checkout / Request / Enquiry / Profile / Cancellation / Transitions

- **Cart:** `product exists, variant belongs to product, active, purchasable (IN_STOCK), quantity valid`. Inventory/pricing re-checked at checkout.
- **Checkout:** `Transport→Schema→Auth→Authz→Cart valid→Products purchasable→Inventory→Fulfillment→Price server-side→Order/payment`. Do not collapse.
- **Request:** `contact info, product ref when supplied, quantity when supplied, dimensions, material, color, notes, attachments`; `user=null` valid (anonymous). File: `size, content type, extension, signature not MIME alone, permissions, scanning`.
- **Enquiry:** `contact info, subject where required, message, attachments`; User association optional.
- **Profile:** Mutable fields each validated; `email/password/role/account_status` not ordinary fields — dedicated workflows.
- **Cancellation:** `authenticated customer → owns order → exists → cancellation allowed → 20-min window valid (backend time, not client `cancelled_at`) → state eligible`. Not `status != COMPLETED` alone.
- **Transitions:** Every transition validates `current state, requested transition, actor authorization, business preconditions` (e.g., `PROCESSING→SHIPPED` requires staff auth). Never allow `status=SHIPPED` generic write.

### 16.7 Unknown, Immutable, Client-Controlled & Mass-Assignment

- **Unknown fields:** Rejected (validation error) for strict create/update/action; catches typos, stale mobiles, mass-assignment. Ignore-forward only where documented.
- **Immutable/Server-controlled:** Never client-settable: `id`, `created_at`, `updated_at`, `order_reference` (`OD-...`), `status`/`current_order_status`, `payment_status`/`payment_confirmation`, inventory (`quantity`/`reserved_quantity`/`available_quantity`), `final_order_total`/`approved_delivery_fee`, `customer identity`, `status_history`. If sent, ignored/rejected. Ownership from auth, not body.
- **Client-controlled:** Explicit `ACCEPT/REJECT/IGNORE/SERVER-GENERATE` distinction. ACCEPT `quantity` after validation; REJECT `order total`/`status`/`created_at` generic writes. Do not allow arbitrary fields to flow.
- **Mass-assignment:** Not `$request->all()→model fill`; `validated input → DTO/command → domain`.

### 16.8 Errors, Messages & Compatibility

- **Categories (canonical CLOSED codes per `api-contract.md §15.15`):** Structural `INVALID_TYPE/INVALID_FORMAT/INVALID_VALUE/MISSING_REQUIRED_FIELD`; Business `PRODUCT_NOT_PURCHASABLE/INSUFFICIENT_STOCK/ORDER_NOT_CANCELLABLE/INVALID_ORDER_TRANSITION`; Authorization `AUTHENTICATION_REQUIRED` (canonical; `NOT_AUTHENTICATED` is legacy alias) / `FORBIDDEN` (canonical; `RESOURCE_NOT_OWNED` is legacy alias for `FORBIDDEN`/`RESOURCE_NOT_FOUND` per masking §15.8); External later `PAYMENT_PROVIDER_ERROR` (Group H).
- **Ownership:** Layer detecting error owns it (`string quantity→schema`, `not logged in→auth`, `doesn't own→authz`, `expired window→domain`, `provider rejected→external`). Not generic `INVALID_REQUEST` unless deliberately grouped.
- **Field-level & multiple errors:** Future error contract must support `field: "fulfillment_type", code:"INVALID_VALUE"` and nested `delivery_address.city`. For form-heavy ops, return all safely determinable schema errors; domain failures may stop earlier when unsafe. Exact JSON deferred to error phase (Phase 1.16).
- **Codes vs messages:** `machine-stable code` separate from `human-readable message`. Frontend acts on `INSUFFICIENT_STOCK`, not parsing English. Messages must not leak `SQL/schema/stack/paths/secrets/private records`; never return raw exception messages. Localization via frontend mapping; backend not required to produce every locale.
- **Stability:** Renaming `INSUFFICIENT_STOCK→OUT_OF_STOCK_ERROR` within `v1` is breaking. Validation changes that reject previously accepted input or make field suddenly required are breaking; follow versioning policy. Adding `NEW_STATUS` to CLOSED enum is formal compatibility decision.
- **Logging:** May log failures for diagnostics but never log `password, token, payment secrets, full private addresses` indiscriminately.

### 16.9 Transactions, Concurrency, Idempotency (incl. Webhooks), Audit

- Domain validation for `stock, order creation, reservation, critical transitions` inside/around transaction; prevent `validate→state change→assumption stale`. Identify `optimistic/pessimistic/atomic/isolation` needs; requirement: correct under concurrent ops. Do not choose mechanism now, but requirement is normative.
- Idempotency at correct boundary: **Client-initiated** (`POST checkout`, `POST payment initiation` with same `Idempotency-Key`) must not duplicate. Validation + idempotency cooperate; duplicate retry must not create duplicates.
- **Webhook idempotency (generic, provider-agnostic):** Repeated deliveries of the same provider event (e.g., payment webhook) must not duplicate effects. Requires stable event identity (provider event identifier / idempotency key persisted), deduplication via durable unique constraint, and atomic check-then-apply inside transaction; duplicate delivery returns prior result without reapplying inventory/payment/order mutations. Provider-specific payload fields and signature verification remain Group H (deferred).
- Failure atomicity: failed validation must not leave partial state (reservation without order).
- Auditability: important state changes (`cancellation, status transition, inventory adjustment, payment change`) eventually auditable; not every failed request needs permanent audit; no infrastructure now.
- Rate limiting relationship noted (`login, password recovery, anonymous submissions, checkout, payment`) — not implemented now; record required.
- External dependencies (payment) provider failures ≠ input validation errors; provider specifics Group H.

### 16.10 Centralization & Architecture

- One authoritative boundary per business rule (e.g., `20-min cancellation → domain/application layer`, not duplicated in `Next.js/Flutter/controller/model`). Clients may mirror for UX.
- Purely structural validation may reuse, but no `UniversalBusinessValidator`; prefer `ValidateCheckoutInput`, `ValidateDeliverySelection`, `ValidateOrderCancellation`. Architecture: `HTTP Request → Request Validator → Application Command → Domain Validation → Domain/Application Service → Repository/Transaction`. Do not place every business rule inside Form Request.
- Input definitions must align with `api-resources.md` resource definitions: resource read-only ≠ validator mutable.

### 16.11 Testing Requirements

- Every business validation rule must eventually have tests: `invalid product type, insufficient stock, invalid variant, missing delivery info, invalid enum, unknown field, unauthorized order, expired cancellation window, invalid transition, duplicate checkout` — success + failure.
- Not tested only via React/Flutter UI; backend/domain level required.
- Later API integration: `request→validation→authorization→business`.
- Later E2E: `anonymous browse, authenticated checkout, request/enquiry submission, cancellation, tracking` — backend truth.

### 16.12 Deferred

Payment provider-specific fields/SDK/webhook payload validation → Group H. Laravel Form Requests/DTOs/domain validators/DB constraints/migrations/OpenAPI schemas/error middleware/auth impl/frontend forms → not created here (Explicitly Out of Scope).

## 17. Error Conventions (Consolidated — Phase 1.16)

> **Authority:** Single error architecture for `v1`. Consolidates `phase-1.16.md`. No separate `api-errors.md` / `validation-errors.md` per §2. Reusable rules here; normative registry in `api-contract.md §15` and domain mapping in `docs/domain/business-rules.md §14`.

### 17.1 Envelope & Object

- One top-level array **`errors`** for all failures, even single error — never `error`/`message`/`failure`/`success:false`. Allows multiple field errors + future structured errors.
- Success uses `data`, errors use `errors`, never both. No `{"data":null,"error":"..."}`.
- Error object fields: `code` (required, `UPPER_SNAKE_CASE`, stable, CLOSED), `message` (required, safe, human, not machine contract), `field` (optional, dot-notation `delivery_address.city`, arrays `items.0.quantity` — one canonical style), `details` (optional, structured safe object — never second message string or `internal_id`). `request_id` is envelope `meta.request_id`, not inside error.

### 17.2 Codes & Messages vs HTTP Status

- Codes are **machine** contract (`INSUFFICIENT_STOCK`), messages are human and may evolve; frontends branch on code, map to localized UI copy (`Only 2 units are currently available.`).
- Codes are `UPPER_SNAKE_CASE`, never `camelCase`/`kebab-case`; never `SQLSTATE_23000` / `ModelNotFoundException` leak; no `ERROR`/`FAILED` generic alone.
- HTTP status and code are **separate**: `422 INVALID_VALUE`, `409 CONFLICT`, `401 AUTHENTICATION_REQUIRED`, `403 FORBIDDEN`, `404 RESOURCE_NOT_FOUND`, `429 RATE_LIMITED`, `500 INTERNAL_SERVER_ERROR`, `413 REQUEST_TOO_LARGE`, `415 UNSUPPORTED_MEDIA_TYPE`, `405 METHOD_NOT_ALLOWED`, `400 INVALID_JSON`. Changing status is a contract change.

**Status convention (defer endpoint exceptions):** `400` malformed, `401` missing/invalid auth, `403` authenticated-not-authorized, `404` not addressable (with ownership masking per §17.5), `422` well-formed but business/validation invalid, `409` state/concurrency conflict (refresh-then-retry), `429` throttled + `Retry-After`, `500` unexpected, `502/503/504` temporary upstream (`EXTERNAL_SERVICE_ERROR` family). Payment specifics Group H.

### 17.3 Validation, Field Paths & Multi-Error

- Field-level errors require `field` with canonical dot path (`delivery_address.phone`). Arrays use `items.0.quantity` consistently.
- For schema validation, return **all safely determinable** field errors in one `errors` array, not first-only. Business/domain failures may stop earlier when unsafe.
- Unknown-field and closed-enum failures fit same shape: `{"code":"INVALID_VALUE","field":"fulfillment_type"}` — never `OTHER` normalization.
- Small reusable vocabulary (`MISSING_REQUIRED_FIELD`, `INVALID_VALUE`, `INVALID_FORMAT`, `INVALID_TYPE`) + `field`; not `NAME_MISSING` explosion.
- Safe details example: `INSUFFICIENT_STOCK` may include `requested_quantity`/`available_quantity`; never `internal_reservation_id`/`provider_secret`.

### 17.4 Request ID

- Envelope-level **`meta.request_id`** for every error (consistent with success `meta`). Connects customer error → server logs without leaking secrets; support workflow is *Please provide your request ID*. Per-request correlation ID, not `user_id`/`order_id`/`payment_id`, no sensitive data inside. See `api-contract.md §15.13`.

### 17.5 Not Found & Enumeration Protection

- Private customer-owned resources (Orders, etc.) use **404 masking**: request for another customer's order → `404 RESOURCE_NOT_FOUND` / `ORDER_NOT_FOUND` (not `403`) to avoid leaking `exists`. Probing `GET /orders/1001…1003` must not give differential errors that enumerate private identifiers. Auth + error behavior cooperate.

### 17.6 Security & Information Disclosure

- Production errors **never** expose `database SQL`, stack traces, `password`/`auth secret`/`payment secret`, `internal file paths`, server IPs, framework exception names, `other_customer_id`, `staff_only_note`. Client gets `code`/`message`/`field`/`details` + `meta.request_id`; server logs keep full exception/stack.
- Error accurately reflects **commit status** — failed business validation does not leave phantom reservation; `INSUFFICIENT_STOCK` error means no inventory reserved (`§65`).
- Security tests verify unauthorized access does not reveal existence/private data via errors.

### 17.7 Retry & Rate Limiting

- Conceptually: **Not retryable** without changing input (`INVALID_VALUE`, `MISSING_REQUIRED_FIELD`, `PRODUCT_NOT_PURCHASABLE`, `ORDER_NOT_CANCELLABLE`, `FORBIDDEN`); **Retryable** (502/503/504 transient upstream); **Requires re-read/reconciliation** before retry (`CONFLICT`, `INSUFFICIENT_STOCK`, `INVALID_ORDER_TRANSITION` with `409`). Do not add per-error `retryable:true`; contract defines classes. Final client behavior app-specific.
- Rate limiting: `429 RATE_LIMITED` uses standard **`Retry-After`** header, not custom `retry_after_seconds` field. No internal algorithm disclosure.

### 17.8 Compatibility (CLOSED)

- Removing/renaming error code, changing semantics/status incompatibly, changing field-path format, removing documented detail → **breaking** within `v1`.
- Improving `message`, adding optional safe `details`, adding new code for new operation (reviewed) → **potentially non-breaking**.
- Error codes/categories are **CLOSED** like validation enums; new code Addition is explicit decision.
- Frontend (Flutter/Next.js/Admin) uses `code`/`field`/`details` + `HTTP status` to decide `show field error` / `toast` / `refresh` / `redirect to login` / `retry` / `contact support` without parsing message; Admin uses same base contract.

### 17.9 Business Error Vocabulary (Concise)

- Use distinct codes only when client must branch: `PRODUCT_NOT_PURCHASABLE`, `INSUFFICIENT_STOCK`, `ORDER_NOT_CANCELLABLE`, `INVALID_ORDER_TRANSITION` beat single `VALIDATION_ERROR`. Do not create `INSUFFICIENT_STOCK_FOR_SOFA` explosion.
- Validation authz business external categories align with invariant IDs (`CART-`, `INV-`, `ORD-`, etc.) — see `api-contract.md §15.9` for full registry and `api-resources.md §11` for per-resource mapping.
- External/provider failures mapped to project-level `EXTERNAL_SERVICE_ERROR` family, not raw provider strings; Group H will define payment-specific extensions without breaking envelope.

### 17.10 Deferred

Payment provider-specific error codes/SDK/webhook failures (Group H), Laravel handlers/middleware/classes, rate-limit enforcement, OpenAPI/endpoint-specific schemas, Next.js/Flutter handlers — not implemented here (Explicitly Out of Scope §93).

## 18. Authentication Conventions (Consolidated — Phase 1.17)

> **Authority:** Single authentication conventions for `v1`. Consolidates `phase-1.17.md`. No separate `authentication.md` per §2. Reusable rules here; normative contract in `api-contract.md §17`; business meaning in `docs/domain/business-rules.md §17`; decisions in `decisions.md ADR/AUTH-*`.

### 18.1 Actors, Roles & Hierarchy

- **CLOSED roles:** `CUSTOMER`/`STAFF`/`ADMIN` (`UPPER_SNAKE_CASE`, CLOSED). No `MANAGER`/`DELIVERY_AGENT` etc. in V1. Role assignment is **server-controlled** — client self-promotion (`{"role":"ADMIN"}`) is rejected (422 `INVALID_VALUE` or 403 `FORBIDDEN`). Staff not via public registration; Admin via bootstrap.
- **Ownership vs hierarchy:** `Customer owns own account`; `Staff/Admin own operational data`, not customer accounts. Hierarchy is permission, not ownership.

### 18.2 Authentication Boundaries

- **Public (no auth):** `GET /products`, `/products/{slug}`, `/categories`, `/categories/{slug}`, `search`, product details/prices/availability. Must remain SSR-renderable for SEO without login; no auth middleware on public catalog routes.
- **Anonymous allowed but authenticatable:** `POST /requests` (made-to-order), `POST /enquiries` — `User=none` + contact when anonymous, linked to account when authenticated.
- **Auth required:** `POST /checkout`, `GET /me/orders`, `GET /me/orders/{order}`, `POST /orders/{order}/cancel`, profile, own requests/enquiries history. Backend enforces; `401 AUTHENTICATION_REQUIRED` (or `CHECKOUT_REQUIRES_AUTHENTICATION`) on anonymous checkout — never frontend-only guard.
- **Checkout boundary:** `Browse → no auth | Cart interaction → per cart policy | Checkout → auth REQUIRED`.
- **Customer flexibility:** `Customer: maximum customer-facing commerce, minimum admin` — rich shopping without operational privileges.

### 18.3 Cross-Client Identity & Session/Token Principles

- One shared customer identity across `Next.js` and `Flutter` against same Laravel backend; registering on web allows login on app with same credentials (no duplicate accounts).
- `Next.js` → **first-party browser session** (secure httpOnly cookie, not long-lived JS token); `Flutter` → **API credential/token**; `Admin` → administrative session. Same identity system, role gates after auth.
- **Logout:** explicit, invalidates server-side session/credential; deleting frontend token alone insufficient if server state revocable.
- **Multi-device:** multiple legitimate customer sessions (web/phone/tablet) **permitted**; logout on one does not invalidate others. Revocation of individual sessions, staff/admin stronger controls (shorter idle, visibility, forced logout, MFA/audit later) — deferred but not prohibited.
- **Expiration:** conceptually `active → expired → revoked`; durations chosen later based on convenience vs privilege.

### 18.4 Credential Handling & Data Minimization

- Registration collects only **minimum**: `name`, `email`, `phone`, `password`; not `address book`/`delivery address` (separate flows).
- Passwords **never** plaintext, only **secure one-way hash**; never return `password`, `password_hash`, `reset_token`, `session_token`, `refresh_secret`, private keys in API responses. Role is not permission — backend evaluates `role+resource+action+ownership+state` (Phase 1.18); exposing `database permissions`/`internal policy` in profile is prohibited.

### 18.5 Verification, Recovery & Enumeration Protection

- **Email verification:** `verified email → account attribute`; `verification process → secure time-limited mechanism`; `{"email_verified":true}` from client not authoritative. Requirement before checkout/full use deferred; if required, enforced server-side. Email delivery (Group R deferred). **Phone/SMS OTP** not an auth requirement by default.
- **Password recovery:** secure **time-limited single-use token** (`request → token → set new password → invalidate`); no plaintext password in email/response; no raw reset secret stored. If V1 launches before email, recovery may be operationally unavailable — documented, not weakened with insecure `send reset in response`.
- **Enumeration protection:** login and recovery responses avoid distinguishing `email exists` vs `not exists` unless explicitly justified; recovery is generic `"Request received."` rather than `"This email does not exist."`.

### 18.6 Authentication Error Conventions

- All auth failures use common error envelope `api-contract.md §15` — never `auth_success`/`login_result`/`token_response` separate envelope. Codes: `AUTHENTICATION_REQUIRED` (401, not 403 for unauthenticated), `INVALID_CREDENTIALS`, `SESSION_EXPIRED`, `INVALID_AUTHENTICATION`, `FORBIDDEN` (canonical; aliases `NOT_AUTHENTICATED`/`RESOURCE_NOT_OWNED` per §15.15). Authenticated endpoints use common response conventions (`data`/`meta`); auth payload may be specialized but consistent.
- **Threat boundary:** responses must not allow `credential theft`/`stuffing`/`brute-force`/`session theft`/`token leakage`/`account enumeration`/`privilege escalation`/`role tampering`/`session fixation`/`password-reset abuse`/`cross-account access`/`staff impersonation` to succeed; brute-force rate limiting, failed-attempt protection, abuse detection are identified as later backend requirements.

### 18.7 Security Principles & Versioning

- **Account lifecycle:** staff approval is **Admin-only** (`PENDING`→`ACTIVE` etc. lifecycle deferred); self-approval prohibited; customer accounts belong to customers; staff cannot restrict browsing/ordering, access credentials, impersonate, or change customer role/password/lock account unless explicit Admin-only security policy (auditable). No hard-delete of customers with historical `orders/payments/requests/enquiries/notifications`; deletion as privacy operation later.
- **Change operations:** `password change` via authenticated secure workflow (not `PATCH /me {password}`); `role change` privileged — customer/staff cannot change own role; admin may manage staff roles per authorization.
- **Logging/audit:** consider `login success/failure`, `logout`, `password change/reset`, `staff approval`, `role change`, `session revocation` without logging passwords/tokens; staff approval auditable (`who/when/what`).
- **Versioning:** auth behavior is Version 1 contract — breaking changes (credential semantics, login shape, requirements, removing flow) follow `api-versioning-strategy.md`.

### 18.8 Deferred

Laravel/Sanctum, User model/migrations, hashing, password reset/email verification/MFA, login controllers/middleware/policies, Next.js/Flutter screens, token/session storage, staff-admin UI, exact durations, email delivery (Group R) — not implemented here (Explicitly Out of Scope §87).

## 19. Authorization Conventions (Consolidated — Phase 1.18)

> **Authority:** Single authorization conventions for `v1`. Consolidates `phase-1.18.md`. No separate `authorization.md` per §2. Reusable rules here; normative contract in `api-contract.md §18`; business meaning in `docs/domain/business-rules.md §18`; decisions in `decisions.md ADR/AUTHZ-*`.

### 19.1 Deny by Default & Least Privilege

- **Deny by default** for all protected resources — `allow by default then blacklist` is prohibited. Explicitly classify `products → public read`, `categories → public read` as public; public catalog remains cacheable separately.
- **Least privilege:** every actor gets only minimum for legitimate work. Customer retains `browse/search/add to cart/checkout/view own orders/cancel eligible own order/submit requests` without admin approval; staff get only operational perms needed.

### 19.2 Server-Side, No Client-Supplied Authority

- Authorization is **server-side only**; never trust client-supplied `role`, `ownership`, `permission`, `resource identity`. Client `{"role":"ADMIN"}` or `{"user_id":"another"}` is rejected; mass-assignment `role` is blocked at validation + authorization layers.
- Frontend route protection is UX only, not final authority; backend evaluates `AUTHENTICATED IDENTITY + ROLE + RESOURCE + ACTION + OWNERSHIP + BUSINESS STATE + CONTEXT` (see §18.1) for every operation, including idempotent retries.

### 19.3 Ownership, Resource & Action, State-Aware

- **Resource-level:** `Order: customer → own, staff → operational, admin → authorized admin` — more precise than `STAFF → all DB`.
- **Action-level:** `STAFF → may view order` ≠ `may process order` ≠ `may change customer password` (denied). Evaluate `role + resource + action`.
- **State-aware:** `STAFF → ship` only if `Order = PROCESSING` and `operational order access + ship permission`; customer `cancel` only if `owns + cancellable state + 20-min window`. Authz evaluated at operation time, atomic with `validate state + perform` so state cannot change in between. Authorization does **not** substitute for domain validation.

### 19.4 Public vs Private & Anonymous Boundaries

- **Public:** `products`, `categories`, `search`, `product details` — `PUBLIC`, no auth. **Private:** `orders`, `profile`, `notifications`, `requests`, `enquiries`, `cart` — `AUTHENTICATED_OWNER` / `OPERATIONAL_STAFF` / `ADMINISTRATIVE`.
- **Anonymous:** may `submit request/enquiry` via public endpoint + input validation (+ anti-abuse later) with `User=none` + contact; must not `GET /enquiries/{id}` unrestricted or fetch attachments via predictable public paths. `Request → Attachment` inherits parent; if cannot access parent, cannot access attachment. Notifications are owner-based; staff notifications are operational, not mixed.
- **Staff not customer-admin:** staff cannot `change customer passwords/roles/disable accounts/impersonate/view credentials/change auth state`; such is security/admin.

### 19.5 Ownership Checks, Query Awareness & Serialization

- **Ownership via relationships:** `User → Orders/Notifications/Cart/Requests/Enquiries` — authorization uses these logical relationships, never `?user_id=another-customer` to expand scope.
- **Authorization-aware queries:** pagination/search operate over **authorized dataset** (`Customer A /me/orders?page=2` paginates `Customer A` only, not all then client-filter); search results are authz-filtered.
- **Field-level before serialization:** select representation by actor before fetching (`Order: customer → customer-facing fields, staff → operational, admin → administrative`); never `fetch all then assume frontend hides`. Sensitive fields (`password`, `provider secret`, `internal staff note` vs `historical order price`) follow `§18.11` matrix, even Admin receives only when operationally necessary.
- **Cart binding:** `cart.owner` cannot be changed via `user_id`.

### 19.6 Denial, Caching & Background Safety

- **Denial:** use Phase 1.16 envelope — `401 AUTHENTICATION_REQUIRED` (not authenticated) vs `403 FORBIDDEN` vs `404 RESOURCE_NOT_FOUND` where hiding existence is safer. Do not leak via divergent errors.
- **Caching:** private responses use private `Cache-Control`, not CDN-public; do not cache authz decisions across users (`Customer A → Order A` must not leak via cached `Customer B`); public catalog may be CDN.
- **Background jobs/webhooks:** use explicit service authorization (`SYSTEM` not a user role) for `payment callback`, `notification dispatch`, `inventory cleanup`, `order timeout` (payment webhook auth is Group H); external webhooks are not `CUSTOMER`/`STAFF`/`ADMIN`.
- **Policy implementation:** centralized `OrderPolicy.view/cancel/process/ship`, `ProductPolicy.view/manage` etc., not scattered `if ($user->role === 'admin')`. Use capability/resource/action policies; role is input, not whole model.

### 19.7 Permission Vocabulary & Separation of Duties

- **Baseline:** `RBAC + ownership + business-state`; avoid role explosion (`SENIOR_STAFF/ORDER_STAFF` etc. without justification) — start with `CUSTOMER/STAFF/ADMIN` CLOSED and explicit permissions like `products.view`/`products.manage` (catalog CRUD + images/variants, not stock) , `inventory.view`/`inventory.manage` (stock-quantity adjustments, not catalog), `orders.view_operational`/`orders.accept`/`orders.process`/`orders.ready_for_pickup`/`orders.ship`/`orders.deliver` (each distinct, see `api-contract.md §18.6` state preconditions; `process` does not cover `accept`/`ready_for_pickup`), `requests/enquiries.view/manage`, `staff.approve/manage`. Wildcard `admin.*` as sole model avoided; explicit sets are auditable.
- **Separation of duties:** `Staff → operational processing`; `Admin → staff approval` — one role cannot request and approve own privilege. Customer permissions are ownership-based (`customer.orders.read_own` etc.) via policies, not huge permission tables; staff/admin explicit.
- **Data minimization:** staff receive only operationally relevant customer data (`name`, `phone`, `delivery address`, `order items`); not `password`, `token`, `unrelated history`. Admin also minimized.
- **Audit & changes:** `staff approval`, `role change`, `account security change`, `critical inventory/order correction` produce audit `actor/action/resource/target/timestamp/result` (no secrets); staff `order accepted/shipped`, `inventory adjusted` likewise; permission changes only by authorized Admin.

### 19.8 Deferred

Laravel Policies/Gates/middleware, Spatie, role/permission tables/migrations, authorization controllers, admin/customer UI, Flutter code, token middleware, impersonation, MFA, payment authorization — not implemented here (Explicitly Out of Scope §142).

## 20. Endpoint Inventory Conventions (Phase 1.19)

> **Authority:** Version 1 endpoint catalogue conventions. Consolidates `phase-1.19.md`. No separate per-endpoint docs; inventory lives in `api-contract.md §19` (authoritative table + per-endpoint blocks) and resource mapping in `api-resources.md §14`. Conventions here are reusable across endpoints.

### 20.1 Stable IDs, Version Prefix, No Recycling

- IDs `AUTH-xxx`, `CAT-xxx`, `CART-xxx`, `CHK-xxx`, `ORD-xxx`, `PAY-xxx`, `REQ-xxx`, `ENQ-xxx`, `NOT-xxx`, `USER-xxx`, `INV-xxx`, `ADM-xxx`, `WEBHOOK-xxx` are stable; removed IDs remain **retired, never recycled**. All paths use `/api/v1` per Phase 1.8; no unversioned V1 endpoints.

### 20.2 Naming, Methods, and Query Reuse

- **Naming:** lowercase, plural resources, kebab-case for actions (`ready-for-pickup`), shallow nesting (`/products/{product}/variants`), nouns + controlled `POST /orders/{order}/cancel` (not `PATCH {status}`) per Phase 1.9. No `pageSize`/`sortBy` aliases — only `page`/`per_page`, `search`, `category`, `product_type`, `availability`, `min_price`, `max_price`, `sort`, `sort_direction` per Phase 1.11. **Category filtering:** canonical `GET /products?category={category}`; `GET /categories/{category}/products` is **REJECTED** duplicate. **Images:** `GET /products/{product}/images` **REJECTED** (embedded in product detail). **Availability:** embedded `availability` + `stock_indicator` in `Product`, not separate endpoint.
- **Methods:** `GET` read (`CAT-*`, `USER-001`, `ORD-001`), `POST` create/action (`AUTH-001`, `CART-002`, `CHK-001`, `ORD-007`), `PATCH` partial update (`USER-002`, `CART-003`, `CAT-008`), `DELETE` actual removal (`CART-004`) per Phase 1.10 — `DELETE` not for cancellation.
- **Pagination:** `CAT-001`, `CAT-003`, `ORD-001`, `ORD-005`, `REQ-004`, `ENQ-004`, `NOT-001`, `ADM-001`, `INV-001` use `page`/`per_page` (1–100) + `meta.pagination` per Phase 1.12; single-resource endpoints not paginated.
- **Response / Input / Validation / Errors:** reuse `data`/`meta` + `errors` (`§15`) envelopes, `snake_case`, money `{amount,currency}`, type strictness, `AUTHENTICATION_REQUIRED` canonical 401 (alias `CHECKOUT_REQUIRES_AUTHENTICATION`), CLOSED enums, field `delivery_address.city` + `items.0.quantity`, `meta.request_id` — no raw arrays or custom wrappers.

### 20.3 Auth / Authz, Security, Idempotency, Concurrency per Endpoint

- **Auth:** `CAT-001..006` public (no auth) remain SSR/SEO-friendly; `REQ-001`/`ENQ-001` + `AUTH-001/002/004/005` public with rate-limit safety; `CHK-001`/`ORD-001` require `AUTHENTICATION_REQUIRED` canonical 401; `ORD-005` staff `orders.view_operational`; `ADM-003` admin `staff.approve` (audited). No `STAFF → block_customer`.
- **Authz:** every protected endpoint declares `owns resource?` / `operational access?` / `administrative access?` per `api-contract.md §18`; e.g., `ORD-002` ownership vs `ORD-010` operational `orders.ship` + `PROCESSING`.
- **Security classification:** each endpoint tagged `PUBLIC` / `CUSTOMER` (`AUTHENTICATED_OWNER`) / `STAFF` (`OPERATIONAL`) / `ADMIN` (`ADMINISTRATIVE`) / `SYSTEM/WEBHOOK` for `WEBHOOK-001`; data `PUBLIC` vs `PRIVATE` vs `INTERNAL` helps prevent overexposure (`payment secrets` never).
- **Idempotency:** `SAFE`: `CAT-*` `GET`, `CART-001` `GET`; `IDEMPOTENT`: `USER-002` `PATCH`, `CART-003/004`, `NOT-002`; `IDEMPOTENCY_REQUIRED`: `CHK-001`, `PAY-001`, `ORD-004` cancel, `ORD-007..011` + `ORD-013` `complete` state actions, `CART-005` merge, `INV-003` adjust; `NON_IDEMPOTENT` by default: `REQ-001`/`ENQ-001` (+ `REQ-007`/`ENQ-007` attachments), `CART-002`.
- **Concurrency:** `CHK-001`, `INV-003`, `ORD-007..011` + `ORD-013` complete, `PAY-001`/`WEBHOOK-001`, `ADM-003` flagged concurrency-sensitive (atomic authz+state+perform).
- **Anonymous mutation safety:** only `AUTH-001` register, `REQ-001`, `ENQ-001`, `AUTH-004/005` recovery approved as anonymous mutations; all reviewed for abuse/spam/rate-limit/file risk.
- **Count & MVP:** `69` endpoints (`CAT 12` + `AUTH 7` + `USER 3` + `CART 5` inc. `CART-005` merge + `CHK 1` + `ORD 13` + `REQ 7` + `ENQ 7` inc. `ENQ-007` + `NOT 2` + `INV 3` + `ADM 6` + `PAY/WEBHOOK 3`) is smallest coherent surface — no `GET /my-orders` duplicate, no `wishlist`/`reviews`/`coupons` unless approved; each endpoint justified for attack surface. Guest-cart `X-Guest-Cart-Id` handling is backend authority, not client ownership proof.

## 21. Catalog API Conventions (Phase 1.20)

> **Authority:** Conventions governing the Catalog domain (`CAT-001..CAT-006` public read operations, representations, filters, sorts, and SEO integration). Consolidates `phases/phase-1.20.md`.

### 21.1 Query Pipeline Execution Order

All catalog query evaluation on collection endpoints (`CAT-001`) strictly executes in the following sequence:
1. **Search:** Text matching against product name, description, SKU, variant options (`search`).
2. **Filter:** Strict condition evaluation (`category`, `product_type`, `availability`, `min_price`, `max_price`). Invalid parameters yield `INVALID_VALUE` (422), never silent truncation or unconstrained scans.
3. **Sort:** Server-defined allow-list sorting (`sort`, `sort_direction`).
4. **Tie-Breaker:** Deterministic append of `id ASC` to ensure repeatable pagination order across pages.
5. **Paginate:** Slicing window using `page` and `per_page` returning `meta.pagination`.

### 21.2 Deterministic Sort & Tie-Breaking Rule

- **Allow-list Only:** Allowed sort fields are strictly limited to `created_at`, `price`, `name`. Arbitrary database column names in `?sort=` are rejected with `INVALID_VALUE` (422).
- **Tie-Breaker:** When sorting by non-unique fields (e.g. `price`, `created_at`, `name`), the server deterministically appends `id ASC` to guarantee page-to-page stability during pagination.
- **Default Sort:** When `sort` is omitted, the default sort order is `catalog display order` (`created_at DESC, id ASC`).

### 21.3 Public Slug vs Machine Identifier Dual Resolution

- **Dual Lookup for Products and Categories:** Single-resource detail endpoints for Product (`/api/v1/products/{product}`) and Category (`/api/v1/categories/{category}`) accept either the human-readable URL `slug` (e.g. `modern-3-seater-sofa`) or the opaque machine `id` (e.g. `prod_01h8x9...`). Product Variants (`/api/v1/products/{product}/variants/{variant}`) resolve strictly by Variant `id` (`var_...`) under parent `{product}`.
- **Namespace Uniqueness:** Slugs are guaranteed unique within their respective entity namespaces (`Product.slug`, `Category.slug`).
- **SEO Uniformity:** Next.js uses `slug` for SEO canonical URLs (`/products/modern-3-seater-sofa`), while Flutter and machine integrations may use either `slug` or `id`.

### 21.4 Summary vs Full Detail Serialization Policy

- **Collections (`CAT-001`, `CAT-003`):** Return **Summary representations** (lightweight payload, single primary thumbnail, essential pricing and badges) to optimize bandwidth, mobile performance, and edge cache hit ratios.
- **Detail (`CAT-002`, `CAT-004`):** Return **Full Detail representations** (full description, complete gallery array with sort order, active variants list, complete category context).
- **Field Consistency:** Field names, data types, and enum values are identical across Summary and Detail schemas; fields are never renamed between collection and detail views.

### 21.5 Public Catalog No-Auth & Cache Safety Rule

- **No Authentication Required:** Public catalog endpoints (`CAT-001..CAT-006`) must never require registration, cookies, tokens, or sessions.
- **Zero Customer State:** Catalog responses contain zero customer-specific data. They are safe for aggressive HTTP caching (`public, max-age=...`), CDN caching, and Next.js Incremental Static Regeneration (ISR).
- **Masking of Unpublished State:** Products or categories that are draft, hidden, or deactivated return standard `RESOURCE_NOT_FOUND` (404), never leaking internal visibility states.

