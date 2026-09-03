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
- **Idempotency:** `SAFE`: `CAT-*` `GET`, `CART-001` `GET`; `IDEMPOTENT`: `USER-002` `PATCH`, `CART-003/004`, `NOT-002`; `IDEMPOTENCY_REQUIRED`: `CHK-001`, `PAY-001`, `ORD-004` cancel, `ORD-007..011` + `ORD-013` `complete` + `ORD-014` `delivery-fee` state actions, `CART-005` merge, `INV-003` adjust; `NON_IDEMPOTENT` by default: `REQ-001`/`ENQ-001` (+ `REQ-007`/`ENQ-007` attachments), `CART-002`.
- **Concurrency:** `CHK-001`, `INV-003`, `ORD-007..011` + `ORD-013` complete + `ORD-014` delivery-fee, `PAY-001`/`WEBHOOK-001`, `ADM-003` flagged concurrency-sensitive (atomic authz+state+perform).
- **Anonymous mutation safety:** only `AUTH-001` register, `REQ-001`, `ENQ-001`, `AUTH-004/005` recovery approved as anonymous mutations; all reviewed for abuse/spam/rate-limit/file risk.
- **Count & MVP:** `70` endpoints (`CAT 12` + `AUTH 7` + `USER 3` + `CART 5` inc. `CART-005` merge + `CHK 1` + `ORD 14` + `REQ 7` + `ENQ 7` inc. `ENQ-007` + `NOT 2` + `INV 3` + `ADM 6` + `PAY/WEBHOOK 3`) is smallest coherent surface — no `GET /my-orders` duplicate, no `wishlist`/`reviews`/`coupons` unless approved; each endpoint justified for attack surface. Guest-cart `X-Guest-Cart-Id` handling is backend authority, not client ownership proof.

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

## 22. Cart API Conventions (Phase 1.21)

> **Authority:** Conventions governing the Cart domain (`CART-001..CART-005`, customer purchase intent, item aggregation, informational pricing, and guest-cart handoff). Consolidates `phases/phase-1.21.md`.

### 22.1 Cart Item Aggregation and Duplicate Handling

- **Identity Match:** Two cart items represent the same purchasable unit if and only if they share the same `product_id` and the same `variant_id` (or both have `variant_id === null`).
- **Merge Behavior:** When adding an item that already exists in the caller's cart, the server merges the items and increments the quantity (`existing_quantity + added_quantity`), clamped to the maximum allowed limit (`100` units per line). It does not create duplicate rows.

### 22.2 Informational Cart Pricing & Calculation

- **Display Numbers Only:** `unit_price`, `line_total`, and `subtotal` in cart responses are informational server-calculated display numbers reflecting current catalog values.
- **Client Input Prohibition:** Clients cannot submit prices, discounts, delivery fees, or subtotals. Any client-supplied financial values are strictly ignored or rejected.
- **No Price Lock:** Cart pricing is not a transaction lock; authoritative pricing recalculation occurs at Checkout (`CHK-001`).

### 22.3 Non-Reservation Inventory Semantics

- **No Stock Hold:** Adding an item to a cart does not decrement `available_quantity` or increment `reserved_quantity`.
- **Race Tolerance:** An item displayed as available in a cart may become out of stock prior to checkout. Final atomic reservation is deferred to the Checkout database transaction.

### 22.4 Stale Cart Item Preservation and Signaling

- **No Silent Deletion:** When a product or variant becomes inactive, draft, or out of stock after being added to a cart, the server preserves the cart line and sets `availability: "unavailable"` and `is_purchasable: false`.
- **Checkout Block:** A cart containing any line with `is_purchasable === false` is rejected at checkout with `CART_INVALID` (422), requiring the customer to adjust their cart.

### 22.5 Private / No-Store Cache Policy

- **Strict Isolation:** All Cart endpoints must return HTTP headers `Cache-Control: private, no-cache, no-store, must-revalidate` and `Pragma: no-cache`. Customer cart data must never enter shared edge caches or public CDNs.

### 22.6 Guest Cart Token: Transport, Security, and Rotation

The guest cart token is a bearer credential. Possessing it grants access to the associated guest cart. Its transport must match the client's ability to protect it from JavaScript exposure.

**Two mutually exclusive transport paths — the server uses exactly one per client interaction:**

1. **Browser (Next.js web) — Cookie-only path:**
   - On first `CART-002` response, the server sets `Set-Cookie: guest_cart_id=<token>; HttpOnly; Secure; SameSite=Strict; Path=/api`.
   - The server does **not** include `X-Guest-Cart-Id` in any response header for browser clients. A browser-readable response header would defeat the `HttpOnly` protection and expose the token to any JavaScript running on the page, including third-party scripts.
   - The browser automatically sends the cookie on subsequent cart requests; the server reads `guest_cart_id` from the `Cookie` header.
   - Next.js server-side code must never forward the raw cookie value into client-accessible state.

2. **Non-browser (Flutter mobile) — Header-as-bearer-secret path:**
   - On first `CART-002` response, the server returns the token in the `X-Guest-Cart-Id` response header (no `Set-Cookie`).
   - Flutter must treat this token with the same confidentiality as an auth token: store it in secure device storage (e.g., `flutter_secure_storage`), never log it, never embed it in URLs.
   - Flutter sends it back as the `X-Guest-Cart-Id` request header on subsequent cart calls.
   - The `CART-005` merge endpoint accepts the guest token via this request header.

**Rotation and expiry:**
- The guest token is a one-time credential for the lifetime of the guest session. It is permanently retired (invalidated server-side) upon successful merge (`AUTH-002` login or `CART-005`). It cannot be reused after retirement.
- The server must not accept a retired guest token.
- Tokens not merged after a configurable idle period (implementation-defined) may be expired by the server.

**What the server must never do:**
- Return the token in a response header on a browser-originating request.
- Accept a client-supplied token value as proof of identity without server-side lookup.
- Recycle a retired guest token ID.

## 23. Checkout API Conventions (Phase 1.22)

> **Authority:** Reusable conventions governing the Checkout domain (`CHK-001` — Cart-to-Order transaction, fulfillment, delivery-fee authority, idempotency, concurrency, financial/inventory/time/state authority). Consolidates `phases/phase-1.22.md`.

### 23.1 Checkout Is a Dedicated Business Workflow — Not a Cart PATCH or Direct Order Creation

- **Not `PATCH /me/cart`:** Checkout performs validation, inventory checks, price calculation, fulfillment decision, transaction creation and payment handoff — not a field edit.
- **Not `POST /orders` with client totals:** Customer must not directly create an arbitrary Order (`POST /orders` with `total`/`status`/`reference`/`inventory`/`payment_state`) — server creates Order from validated checkout state. Prevents control of `order total`, `order status`, `order reference`, `inventory state`, `payment state`.
- **Meaning:** `POST /api/v1/checkout` means **Customer submits current purchase for server validation and Order creation** (`place order`), not a provisional `start checkout` preview. A separate `review/quote` preview is not added unless delivery-fee timing or payment workflow requires it.

### 23.2 Request Shape, Fulfillment & Delivery Conventions

- **Transport:** `Content-Type: application/json` UTF-8, body only — no query params for business values (`/checkout?delivery_fee=…` prohibited). Request size limited; arbitrary nested JSON rejected.
- **Allow-list (strict):** Only `fulfillment_type` + conditional `delivery_address` are accepted. Unknown fields, `delivery_fee`, `total`/`subtotal`/`unit_price`/`currency` override, `status`, `order_reference`, `cart_id`, `user_id` are rejected (`INVALID_VALUE` + `field`). Mass-assignment via `$request->all()` forbidden — `validated → DTO → domain`.
- **Fulfillment (CLOSED):** `PICKUP` / `DELIVERY` only (`UPPER_SNAKE_CASE`). `shipping`/`courier`/`self-delivery`/`warehouse`/`COURIER`/`EXPRESS` are rejected. `PICKUP` → delivery information not required; `DELIVERY` → `delivery_address` required object `{recipient_name, phone, address_line, city}` (trimmed, non-empty; `field: delivery_address.city` on error). Saved address book is deferred — no `saved_address_id`; address is inline snapshot that becomes Order historical fulfillment data.
- **Delivery-fee authority (server/staff):** Customer chooses `DELIVERY` (supplies address); **Staff/Admin determine and add variable location-based fee**; backend stores authoritative `{amount,currency}`. Customer `{"delivery_fee":1000}` is never authoritative. Flat `TZS 20,000` rate is superseded.
- **Delivery-fee timing (Model B — approved):** Fee is added **after** order creation, before payment: `checkout → Order PENDING_PAYMENT (delivery_address snapshotted, subtotal authoritative, delivery_fee pending) → Staff/Admin sets delivery_fee → final total authoritative → payment (Group H)` (see `api-contract.md §23.6`). No silent payment-before-fee mismatch; final amount == customer-visible final amount.

### 23.3 Idempotency — Required & Standardized

- **Critical idempotency operation:** Network retry must not create two Orders/reservations/payment attempts for same intent.
- **Header:** Client must send `Idempotency-Key: <opaque-uuid>` on `POST /checkout`. Server persists `key → result` durably (unique constraint) and replays original `201` with same `order_reference`/`data` on duplicate same-key retry.
- **Semantics:** Same key + same logical input → replay original result (no new Order). Same key + materially different input (`fulfillment_type`/`delivery_address` differ) → `409 CONFLICT` / `DUPLICATE_OPERATION` (do not silently execute different operation). Timeout does not prove failure — retry with same key reconciles.
- **Storage strategy** (durable store, expiry, exact header casing) finalized in later idempotency implementation phase; requirement is normative now.

### 23.4 Concurrency, Transaction & Inventory Authority

- **Preconditions (all required before success):** `authenticated && has active Cart && cart not empty && all items purchasable (exists/active/IN_STOCK/variant valid) && inventory sufficient (requested ≤ available at transaction point) && fulfillment valid && delivery info valid when DELIVERY && pricing determinable && idempotency key valid`.
- **Revalidation (authoritative, not catalog cache):** Product `exists→active→is_published→IN_STOCK→purchasable`; variant `exists→belongs to product→active→purchasable`; `MADE_TO_ORDER` → `PRODUCT_NOT_PURCHASABLE` (422); inventory `requested ≤ available authoritative quantity` checked **inside atomic boundary**.
- **Race handling:** `Customer A sees 1, B buys 1, A checks out → A fails safely` with no negative stock/oversell. Requires `inventory validation + order creation + cart transition` inside protected transaction (optimistic/pessimistic/atomic — mechanism deferred, correctness normative per INV-003). `SELECT then UPDATE` without locking prohibited.
- **Pricing recalculation:** At checkout, resolve **current** catalog prices, recalculate line totals/subtotal/total (minor-unit integer arithmetic), do not trust frontend cache. Price change example (`1,000,000 → 1,100,000`) uses authoritative price with transparency before final payment.
- **Cart lifecycle:** Empty cart → `CART_INVALID` (422, conceptual `CART_EMPTY` maps here); cart with `is_purchasable:false` → `CART_INVALID` (no partial order). Success → cart cleared/inactivated (order is record); failure (validation/stock) → cart preserved for adjustment.

### 23.5 Financial, Inventory, Identity, Time & State Authority — Server Is Source of Truth

- **Financial:** Server owns `unit_price, line subtotal, cart subtotal, delivery_fee, order total, currency, payment amount`. Customer supplies only intent; `{total:1000}` / `{currency:"USD"}` rejected. Calculation order (Model B — fee-after-order): `load cart → validate items → resolve current unit prices → line totals → subtotal → fulfillment → create Order (PENDING_PAYMENT, subtotal authoritative, delivery_fee pending for DELIVERY / 0 for PICKUP, delivery_address snapshotted) → Staff/Admin sets delivery_fee (server/staff-controlled) → final total (subtotal + delivery_fee) authoritative → payment handoff (Group H) on final total`. Money shape is `§4` minor units `{amount:int,currency:"TZS"}`.
- **Inventory:** Server owns `availability, stock, reservation/consumption`. Client supplies desired quantity only; `available_quantity`/`reserved_quantity` from client rejected.
- **Identity:** Authenticated session determines `customer, cart owner, order owner`. Client must not override via `cart_id`/`user_id`/`customer_id`. `CHK-001` derives own active Cart from principal; any `cart_id` in body rejected (reduces IDOR).
- **Time:** Server determines `checkout timestamp, cancellation timestamp, payment confirmation timestamp`. Client-submitted timestamps rejected; 20-minute cancellation window uses backend time.
- **State:** Client requests operation; backend decides `whether valid + what transition occurs`. Client must not submit desired `status` (`PAID`, `COMPLETED`) as authoritative fact.

### 23.6 Cache, Query, Security & Client Conventions

- **Cache:** `POST /checkout` → no shared caching; `Cache-Control: private, no-store` (contains address + financial data). Cart/checkout responses never public.
- **Query:** No query params for checkout input; transaction in body. Do not create `?delivery_fee=…` authoritative inputs.
- **Security checklist:** `IDOR` (self-context `/me/cart`), `price/total/fee manipulation` (server-calculated), `quantity/stock manipulation` (server authority), `order ownership` (session-derived), `role tampering` (Staff cannot checkout via CHK-001), `duplicate/replay` (idempotency), `payment spoofing` (Group H webhook), `cart substitution` (`cart_id` rejected), `stock race` (atomic transaction) — each has explicit defense per `api-contract.md §23.14`.
- **Next.js & Flutter:** Both submit `fulfillment_type` + conditional address, receive validation errors/stock problems/totals via same contract, retry safely with same `Idempotency-Key`, handle timeout and display resulting Order — no business authority in browser/app. Staff/Admin interact with resulting Order via `ORD-005..013`; checkout exposes no admin ops.
- **Payment remains Group H:** Checkout defines boundary where payment is required; provider integration, SDK, credentials, callbacks, webhooks, provider statuses belong to Group H, not here.

## 24. Order API Conventions (Phase 1.23)

> **Authority:** Reusable conventions governing the Order domain (`ORD-001`..`ORD-014` — historical record, ownership, fulfillment, delivery fee (`ORD-014` fee finalization), status machine, tracking, authorization, privacy). Consolidates `phases/phase-1.23.md` and `phases/phase-1.24.md` (`ORD-014` added in Phase 1.23, tracking fulfillment in Phase 1.24).

### 24.1 Order Is Historical, Customer-Owned, Operationally Managed

- **Historical:** Once created via `CHK-001`, Order becomes authoritative snapshot (`items` historical prices/quantities/names, `subtotal/delivery_fee/total` authoritative, `delivery_address` snapshot). Current `Product.name/price/image` changes do not rewrite history; deactivation does not erase Order.
- **Customer-owned:** `Order.customer_id = authenticated principal` from checkout; `{"customer_id":"..."}` from client rejected; `Customer A → Customer B order` fails `404 ORDER_NOT_FOUND` (masked). Saved address book deferred — no `saved_address_id`; address is per-order snapshot.
- **Operational:** Staff `orders.view_operational` view and act via controlled actions; Admin broader but still action-controlled; no `PATCH {status: "COMPLETED"}` or `DELETE /orders/{order}` for customers.

### 24.2 State Transitions — Controlled Actions, Fulfillment-Aware, Closed

- **Closed statuses:** `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED` (`UPPER_SNAKE_CASE` CLOSED, adding new value is breaking).
- **Actions not field writes:** `POST /me/orders/{order}/cancel` (customer), `POST /orders/{order}/accept|process|ready-for-pickup|ship|deliver|complete|delivery-fee` (staff, `delivery-fee` is `ORD-014` fee finalization). `PATCH {status}` without action is rejected `409 INVALID_ORDER_TRANSITION`.
- **Fulfillment-aware:** `PROCESSING→READY_FOR_PICKUP` only `PICKUP`; `PROCESSING→SHIPPED` only `DELIVERY`; `SHIPPED→DELIVERED` only `DELIVERY`; `READY_FOR_PICKUP→COMPLETED` only `PICKUP`, `DELIVERED→COMPLETED` only `DELIVERY`. Cross-branch `PICKUP→SHIPPED` rejected.
- **Delivery-fee gate:** `PENDING_PAYMENT + delivery_fee_status=PENDING (DELIVERY) → ORD-014 POST /orders/{order}/delivery-fee (orders.set_delivery_fee) → FINALIZED`; `PENDING_PAYMENT→PAID` requires `delivery_fee_status=FINALIZED` (Model B). `PENDING` blocks `PAY-001` `409 DELIVERY_FEE_PENDING`; provisional `total` not final. Fee finalization is `Idempotency-Key` **Required**, concurrency **Critical** (race with payment), `amount` integer minor units, `currency:"TZS"` only, audited.
- **Transition authorization:** Every transition evaluates `actor + permission + current_state + fulfillment_type + business preconditions` atomically; state read+validation+write inside transaction.

### 24.3 Cancellation — 20-Minute Customer Window, Server Time

- **Customer:** `POST /me/orders/{order}/cancel` requires `owns + state == PENDING_PAYMENT (cancellable) + within 20 minutes from `order.created_at` backend time + eligible`. Server computes `elapsed = now - created_at`; `cancelled_at` from client rejected. Success → `CANCELLED`; `ORDER_NOT_CANCELLABLE` (`422/409`) if window expired or state terminal.
- **Staff/Admin:** No automatic customer-cancel via same endpoint; admin cancel is separate controlled workflow (audit, not 20-min window).
- **History remains:** `CANCEL` ≠ `DELETE`; cancelled orders remain readable, not hard-deleted.

### 24.4 Historical Snapshots & Immutable Financial Data

- **Items:** `product_id`, `variant_id`, `sku`, `name`, `variant_name`, `unit_price` (historical `{amount,currency}`), `quantity`, `line_total` — all snapshot at `CHK-001`; immutable (controlled admin correction only with audit, not normal API).
- **Financial:** `subtotal` authoritative at checkout, `delivery_fee` `null→{amount,currency}` `PENDING→FINALIZED` once before `PAID`, `total = subtotal+delivery_fee` final only when `FINALIZED`; `currency TZS` `{amount,currency}` integer minor units; no floating; no recompute from current catalog.
- **Contact/Address:** `delivery_address` + `recipient_name/phone` snapshot preserved; later profile change does not rewrite.

### 24.5 Ownership Checks, Field-Level Access & Query-Aware Authorization

- **Ownership before data:** Verify `authenticated customer` + `owns Order` before serialization; `?customer_id=another` cannot expand; collection `GET /me/orders` paginates own dataset (`Customer A` only).
- **Field-level:** Before serialization select `CUSTOMER` (own `order_reference`, `items`, `totals` limited), `STAFF` (operational `customer contact`, `delivery_address`, not `password`), `ADMIN` (authorized) — per `api-contract.md §24.22`; `reserved_quantity`/`payment secret` never to customer.
- **Search before filter → sort → paginate:** Customer filters `order_status` CLOSED (not `status`), `fulfillment_type`, `created_from/to` ISO8601 — unknown filter `422`.

### 24.6 Private Caching, Concurrency & Idempotency

- **Private:** All Order reads are `Cache-Control: private, no-store` (PII + financial); no CDN public caching. Staff operational similarly private/internal.
- **Concurrency — critical:** `Customer cancel + Staff accept` race, `Staff accept + accept` duplicate, `ship + ship retry`, `fee SET + PAID` race, `Admin vs Staff` transition — all require atomic `authorize + validate state + perform` inside transaction; failure returns `409 ORDER_STATE_CONFLICT` and caller must re-read.
- **Idempotency — required:** `POST .../cancel|accept|process|ready-for-pickup|ship|deliver|complete|delivery-fee` (`ORD-014`) and `checkout` are `IDEMPOTENCY_REQUIRED` (`Idempotency-Key`); retry with same key replays original `201/200` (including `ORD-014` fee finalization replay), same key + different action body → `409 DUPLICATE_OPERATION`; no duplicate `SHIPPED` or duplicate fee finalization on retry.
- **History:** `order_status_history` append-only, generated by actions; `tracking` (`ORD-003` customer, `ORD-012` staff) derives from history but is separate customer-facing progress view (not GPS).

## 25. Order Tracking & Fulfillment Conventions (Phase 1.24)

> **Authority:** Reusable conventions for fulfillment progress (`PICKUP` / `DELIVERY` — distinct paths) and customer tracking (`GET ORD-003/ORD-012`, timeline `occurred_at ASC, id ASC`, `ISO8601 Z`, private, append-only). Consolidates `phases/phase-1.24.md`.

### 25.1 Fulfillment vs Tracking

- **Order:** commercial transaction (`PENDING_PAYMENT`→`COMPLETED`, financials).
- **Fulfillment:** `Order → Staff receives → Process → Fulfill (READY_FOR_PICKUP | SHIPPED→DELIVERED)` — operational, small-scale (`Order → Staff → Process → Fulfill`, not logistics platform).
- **Tracking:** `Timeline = customer-friendly filtered view of order_status_history` (`PENDING_PAYMENT, PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP/SHIPPED, DELIVERED, COMPLETED` filtered by `fulfillment_type`), chronological `occurred_at ASC, id ASC`, not GPS.

### 25.2 Fulfillment Types & Closed States

- `fulfillment_type` `PICKUP`/`DELIVERY` CLOSED; Order states from `§24.13` CLOSED (`PENDING_PAYMENT`→`CANCELLED`). No new status to simplify UI (`SHIPPED` not for `PICKUP`, `READY_FOR_PICKUP` not for `DELIVERY` unless designed).

### 25.3 Pickup vs Delivery Flows (Distinct)

- `PICKUP`: `PROCESSING → READY_FOR_PICKUP` (`ORD-009`) → `COMPLETED` (`ORD-013` explicit, Staff confirms collection) — single pickup location V1.
- `DELIVERY`: `PROCESSING → SHIPPED` (`ORD-010`) → `DELIVERED` (`ORD-011`) → `COMPLETED` (`ORD-013`) — `SHIPPED` = left business, `DELIVERED` = delivery completed (operational, not GPS, not carrier `tracking_number`/`tracking_url`).

### 25.4 Tracking Timeline Ordering & Identity

- **Ordering:** `chronological ascending` (`occurred_at ASC, id ASC` tie-breaker) deterministic for customer display; if Order API elsewhere uses reverse `status_history`, each convention stays explicit.
- **Timestamps:** `ISO8601 UTC Z` (`2026-09-01T09:45:00Z`) per `§3.4` — never mixed.
- **Event ID:** Stable `evt_...` from underlying `order_status_history` `id` (opaque, not raw DB leak); `TimelineEvent {id, status, occurred_at, label}`.
- **Immutability:** Historical events `append-only`; `customer→edit history` and `staff→rewrite past` prohibited; correction via explicit admin workflow (audit).

### 25.5 Actor & Note Privacy

- Customer timeline shows `label` (`"Your order has been shipped."`), not Staff identity (`John Smith shipped`) unless required; `actor` (`staff:45`) is operational (`ORD-012`) where authorized.
- `customer-visible note` explicitly distinguished from `internal Staff note`; internal notes never serialized to `ORD-003` (only `ORD-012` if authorized).

### 25.6 Tracking Privacy, Caching & Size

- `Cache-Control: private, no-store` for `ORD-003` customer tracking and `ORD-012` operational (not public CDN, not `products` catalog cache); delivery address private: visible only to `owns` customer + authorized Staff/Admin.
- Keep tracking lightweight (`order_reference, fulfillment_type, current_status, delivery_fee_status, timeline[]`); not `entire Order + full profile + staff records + payment history`.
- Not paginated in V1 (timeline small, append-only); paginate `page/per_page` per `§4` only if history grows.

### 25.7 Fulfillment Actions — State & Fulfillment-Type Aware

- Every `POST .../ready-for-pickup|ship|deliver` validates `actor + permission + Order + valid current_state + correct fulfillment_type` atomically (`authorize + validate state + perform` in transaction).
- Wrong branch (`PICKUP→ship`, `DELIVERY→ready-for-pickup`) → `409 INVALID_ORDER_TRANSITION` / `FULFILLMENT_ACTION_NOT_ALLOWED` per `§15`.

### 25.8 Real-Time, GPS, Carrier & Notifications — Out of Scope

- V1 = `REST GET tracking + client refresh/polling` (poll on open, pull-to-refresh, visibility-change) — no `WebSockets`, `SSE`, `push`, `GPS`, `tracking_number/carrier/tracking_url`, `assigned_staff` unless business needs.
- Fulfillment does not change `historical unit_price/subtotal/delivery_fee` (except `ORD-014` fee before `PAID`), does not expose `reserved_quantity`, does not implement `payment provider` (Group H) — `fulfillment/Payment` state boundaries stay distinct (`PAID ≠ SHIPPED`).
- Tracking is source event for future notifications (`Group R` email, in-app, webhooks) but does not depend on notification persistence.

### 25.9 Idempotency & Concurrency for Fulfillment

- **Idempotency `Required`:** `ORD-009/010/011/013` fulfillment, `ORD-014` fee, `ORD-004` cancel, `CHK-001`. Retry `Staff A ship + same Idempotency-Key → 200` replay original `SHIPPED` (no second event); same key different body `ship vs deliver → 409 DUPLICATE_OPERATION`.
- **Concurrency `Critical`:** `Staff A ship + Staff B ship`, `Staff deliver + Admin complete`, `Customer cancel + Staff accept` — handled via state machine + transaction; `cancel` uses 20-min server time, not client `cancelled_at`.

## 26. Made-to-Order Request Conventions (Phase 1.25)

> **Authority:** Reusable conventions governing the Request domain (`REQ-001`..`REQ-007` — anonymous creation, customer ownership, private data, attachment authorization, dimensions, status, operational handling). Consolidates `phases/phase-1.25.md`.

### 26.1 Anonymous Creation with Explicit Contact — Self-Contained Record

- **Anonymous allowed:** `POST /api/v1/requests` (`REQ-001`) accepts `User=none` + required contact (`name` required + at least one of `phone` or `email`, per ADR/API-REQ-001). `user_id = null` is valid stored state.
- **Authenticated still requires contact fields (final policy — resolves phase-1.25 §15 Potential):** Even when `Customer` is authenticated, `name` required + at least one of `phone`/`email` remain required (identical rule to Anonymous, per ADR/API-REQ-001 — no trusted-account fallback). The Request's contact snapshot is **historical and self-contained** — later `PATCH /me` profile change does not mutate past request. `Request.user_id` is derived server-side (`authenticated principal`) and supplements contact for ownership, not replaces it.
- **Never trust `user_id` from body:** `{"user_id":"..."}` rejected `422` per `§13.10` server-controlled field rule.

### 26.2 Customer Ownership — Server-Derived

- Ownership rule: `Authenticated Request.user_id = authenticated principal` (derived, not supplied). `Anonymous Request.user_id = null`.
- Retrieval is ownership-scoped: `GET /me/requests` paginates **own dataset only**; `GET /me/requests/{request}` verifies `owns` before serialization; `Customer A → Customer B request` fails `404 REQUEST_NOT_FOUND` masked.
- `email = customer's email` is **not** ownership proof for anonymous retrieval — email is contact, not authentication. Anonymous → authenticated transition does not auto-attach old anonymous requests by email match.

### 26.3 Product Reference — Nullable, MADE_TO_ORDER Validated (Final V1: OPTIONAL per ADR/API-REQ-011)

- `product_id` is **OPTIONAL (nullable)** for V1 per `ADR/API-REQ-011` — resolves `phase-1.25 §26-27` ambiguity. When `null`/omitted = custom/general furniture request (`"Can you make something like this?"`) — valid, relies on `quantity/dimensions/material/color/notes/attachment`.
- When supplied: `exists && is_active && is_published && product_type == MADE_TO_ORDER`; `IN_STOCK` product → `PRODUCT_NOT_REQUESTABLE` (409). Validation failure uses `field: product_id`.
- Two valid modes: `product-linked request` and `custom request` — both supported in V1 to avoid forcing all requests through catalog; **not** Required, **not** Absent.

### 26.4 Quantity Is Not Order Quantity

- `quantity` is optional integer `1..100` (positive, bounded). `0`, `-1`, `1.5`, `"2"` string rejected. When omitted, remains `null` (unspecified, not implicitly `1`) — client must not assume a default; `null` explicitly indicates no quantity specified.
- The requested quantity is **informational intent only** — does not allocate inventory, guarantee production, or become final order quantity.

### 26.5 Structured Dimensions — Allow-Listed, Unit Canonical

- When `dimensions` supplied, it must be object with allow-listed keys only: `length`, `width`, `height` (each `number >0 <=10000`) + `unit` (required when any dimension present, exactly `"cm"` CLOSED).
- No arbitrary keys (`depth`, `diameter` unless explicitly approved) — unknown key → `422` `field: dimensions.depth`.
- Unit synonyms rejected (`centimeter`, `centimetres`, `in`, `inch` → `422`). Single canonical `cm` keeps processing simple.
- `dimensions: null` or omitted = no dimensions — not error.
- This avoids a large furniture-configuration DSL while giving Staff measurable intent.

### 26.6 Material & Color — Free Text, Not Closed Enum

- `material`/`color` are **free text** (not CLOSED enums). V1 enums are CLOSED for `request_status` but material/color would be incomplete and block customers if closed. Small-business flexibility requires strings (`material` max 500, `color` max 200, trimmed).
- No elaborate color-management system in V1.

### 26.7 Notes — Free Text, Bounded, Safe

- `notes` free text, max `5000` chars, trimmed, Unicode-safe, not interpreted as code. Historical `notes` preserved; staff edits separate.

### 26.8 Attachments — Optional, Inline Multipart Preferred, Private

- **Optional:** `0` or `1` attachment on creation in V1; separate `POST /requests/{request}/attachments` (`REQ-007`) with scoped token `+` parent ownership for after-creation upload.
- **Preferred:** `multipart/form-data` field `attachment` inline with `REQ-001` creation — simpler for Laravel/Next.js/Flutter; do not assume pre-upload-then-attach-file flow.
- **Security:** Validate `size <=5 MB`, `type` allow-list (`image/jpeg`, `image/png`, `image/webp`, `application/pdf`), `actual content signature` (client MIME not trusted), filename sanitized, storage authorization. `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE`.
- **Access:** Inherits Request authorization — `Customer own → own attachment`, `Staff → operational`, `Admin → authorized`, `Anonymous → scoped token only`. No permanent public storage URLs; future uses signed/temporary URLs. No internal storage keys exposed.
- **Metadata:** `filename, content_type, size` safe; never expose internal keys.

### 26.9 Request Status — Minimal CLOSED, Not Arbitrary (Formally Approved)

- V1 statuses `SUBMITTED`, `IN_REVIEW`, `CLOSED` are **CLOSED** `UPPER_SNAKE_CASE` — **formally approved via ADR/API-REQ-010** (was deferred per phase-1.25 §43-44 unless formally approved; now approved, so `request_status` and `REQ-006` are not deferred). `QUOTED`/`APPROVED`/`REJECTED`/`PRODUCING` and similar workflow states are **not** valid in V1.
- Default at creation `SUBMITTED`; staff `PATCH REQ-006` (now approved, not deferred) transitions `SUBMITTED→IN_REVIEW→CLOSED` (direct `SUBMITTED→CLOSED` permitted, `CLOSED` terminal, `IN_REVIEW→SUBMITTED` rejected). Customer never sets status.
- Adding a new status is a compatibility decision; staff must not create arbitrary states.

### 26.10 Privacy — Private Data, Not Public Catalog

- Request contact (`name/phone/email`), specs, notes, attachments are **private** — never embedded in public Catalog responses.
- Customer retrieval `REQ-002/003` uses `Cache-Control: private, no-store`; staff `REQ-004/005` operational similarly private; never CDN public. Catalog remains `public` cacheable, Request never.
- Field-level before serialization: only permitted fields per actor (`staff_internal_notes` never to customer, `internal storage keys` never, `credentials` never).
- Anonymous retrieval is **not supported** without explicit secure token — predictable `id` alone insufficient.

### 26.11 Request Is Not Order / Quote / Payment / Inventory

- Submitting a request does **not** `reserve inventory`, `create Order`, `guarantee price`, `guarantee production/delivery/completion`, `charge payment`, `create quoted_price`. No `order_id` returned unless an actual Order has been created via **separately approved** Request-to-Order workflow (out of scope).
- `delivery_fee`, `payment_status`, `order_id` fields from client rejected.

### 26.12 Operational Handling vs Ownership

- Staff `requests.view` is **operational access**, not ownership. `Staff → owns Request` is false. Admin broader but still `authorized + auditability + data minimization`.
- `PATCH REQ-006` may write only `request_status` (controlled) and `staff_internal_notes`; customer-provided fields remain immutable; original intake preserved.
- `Request → Order` future boundary is **outside V1** — `PATCH` must not silently create Order/payment via generic update.

### 26.13 Caching, Pagination, Filtering, Sorting — Global Reuse

- **Pagination:** `REQ-002`/`REQ-004` use global `page`/`per_page` (1–100) → `meta.pagination` per `§11`; no request-specific pagination format.
- **Filtering (staff `REQ-004` allow-list):** `search` (name/email/phone/reference/product), `request_status` CLOSED, `product_id`, `created_from`/`created_to` ISO8601 `Z`.
- **Sorting:** `created_at DESC, id ASC` (newest first) + `id ASC` tie-breaker for both staff queue and customer history.
- **Search privacy:** queries run over **authorized dataset** — `Customer A /me/requests?search=...` searches only own; `Staff search` cannot reveal outside permitted scope.

### 26.14 Anonymous Mutation Safety & Rate Limiting

- `POST /requests` anonymous creation is **public mutation** → `RATE-LIMIT CANDIDATE`; abuse controls identified (`per-IP throttling, spam protection, size limits`) but not implemented here; CAPTCHA deferred unless rate-limit insufficient.
- Do not use `same email + same message` as hard uniqueness constraint — legitimate similar requests exist; rely on explicit idempotency/abuse controls.

### 26.15 Idempotency & Duplicate Handling

- `POST /requests` is **not inherently idempotent** — duplicate tap may create two requests. Duplicate submission risk is documented; explicit idempotency mechanism deferred. Do not block similar `phone+notes` as duplicate via DB unique constraint.
- `PATCH REQ-006` is designed idempotent (same status update replays); concurrency race `Staff A close + Staff B update` handled as state transition concurrency (`409 CONFLICT`).

### 26.16 Validation Hierarchy Applied to Request

```
Transport → Schema → Auth (optional) → Authorization → Domain (product MADE_TO_ORDER when linked, quantity 1..100, dimensions allow-list unit cm, notes bounds, attachment safe) → Concurrency Low → Persistence
```

Cross-field: `product_id` supplied → `product_type` must be `MADE_TO_ORDER`; conditional: `phone`/`email` at least one; state-dependent: `PATCH REQ-006` validates current `request_status` before transition.

## 27. General Enquiry Conventions (Phase 1.26)

> **Authority:** Reusable conventions governing the Enquiry domain (`ENQ-001`..`ENQ-007` — anonymous creation, customer ownership, private communication, attachment authorization, optional product/order association, plain-text message, minimal status). Consolidates `phases/phase-1.26.md`.

### 27.1 Anonymous Creation — Self-Contained Private Communication

- **Anonymous allowed:** `POST /api/v1/enquiries` (`ENQ-001`) accepts `User=none` + required contact (`name` required + at least one of `phone` or `email`, per `§27.3`). `user_id = null` is valid stored state.
- **Authenticated is Optional/derived (per phase-1.26 §97):** When `Customer` is authenticated, `name`/`phone`/`email` are **Optional/derived** — server derives trusted account identity and uses profile contact where not supplied, while still capturing explicit contact snapshot when provided. Historical snapshot preserved alongside `user_id`.
- **Never trust `user_id` from body:** `{"user_id":"..."}` rejected `422` per `§13.10` server-controlled field rule.
- **Enquiry is communication, not transaction:** No `payment*`/`delivery_fee`/`order_status` in payload; no `request_id` linkage.

### 27.2 Customer Ownership — Server-Derived

- Ownership rule: `Authenticated Enquiry.user_id = authenticated principal` (derived, not supplied). `Anonymous Enquiry.user_id = null`.
- Retrieval is ownership-scoped: `GET /me/enquiries` paginates **own dataset only**; `GET /me/enquiries/{enquiry}` verifies `owns` before serialization; `Customer A → Customer B enquiry` fails `404 ENQUIRY_NOT_FOUND` masked.
- `email = customer's email` is **not** ownership proof for anonymous retrieval — email is contact, not authentication. Anonymous → authenticated transition does not auto-attach old anonymous enquiries by email match.
- Anonymous retrieval is **not supported** without explicit secure mechanism — predictable `id` alone insufficient (§27.3).

### 27.3 Product & Order Association — Optional, Validated

- `product_id` is **OPTIONAL (nullable)** — any public product may be referenced for context (`"Can you tell me more about this sofa?"`), validated `exists && active && is_published`, not restricted by `product_type`. `null`/omitted = general business enquiry.
- `order_id` is **OPTIONAL (nullable)** — when supplied by authenticated customer, validated and **ownership-checked** (`order must belong to principal`); knowing `order_id` is not authorization. Anonymous `order_id` discouraged in V1; if supported later requires secure order-access token.
- Do not add `request_id`/`cart_id`/`payment_id` associations in V1 — keep relationships intentional (`product_id` + `order_id` only where valuable, §27.4).

### 27.4 Subject & Message — Required, Bounded, Plain Text Only

- `subject` required, trimmed, **5–200** chars, useful for staff triage. Unconstrained blob rejected.
- `message` required, trimmed, **10–5000** chars, **plain text only** in V1 (no Markdown/HTML), newlines preserved, not interpreted as code/HTML/SQL/script. Server validates length and stores safely; frontend escapes.
- Do not silently transform meaningful customer text — use validation + safe storage + safe output encoding, not destructive sanitization.
- Oversized `subject`/`message` → `422 MISSING_REQUIRED_FIELD`/`INVALID_VALUE` (size limits, `REQUEST_TOO_LARGE` where body exceeds transport limit).

### 27.5 Category — Simple, Optional, CLOSED

- When `category` supplied, must be `GENERAL`, `PRODUCT`, `DELIVERY`, `OTHER` (`UPPER_SNAKE_CASE`, CLOSED). Single small triage taxonomy; do not create large CRM categories without need.
- `null`/omitted = uncategorized triageable via subject/message.
- Unknown `category` value → `422 INVALID_VALUE` `field: category`.

### 27.6 Attachments — Optional, Inline Multipart Preferred, Private (Shares Architecture with Request)

- **Optional:** `0` or `1` attachment on creation in V1; separate `POST /enquiries/{enquiry}/attachments` (`ENQ-007`) with scoped token `+` parent ownership for after-creation upload.
- **Preferred:** `multipart/form-data` field `attachment` inline with `ENQ-001` creation — shares upload strategy with `REQ-007`, no second architecture.
- **Security:** Validate `size <=5 MB`, `type` allow-list (`image/jpeg`, `image/png`, `image/webp`, `application/pdf`), `actual content signature` (client MIME not trusted), filename sanitized, storage authorization. `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE`.
- **Access:** Inherits Enquiry authorization — `Customer own → own attachment`, `Staff → operational`, `Admin → authorized`, `Anonymous → scoped token only`. No permanent public storage URLs; future uses signed/temporary URLs. No internal storage keys exposed.

### 27.7 Enquiry Status — Minimal CLOSED `OPEN`/`CLOSED`

- V1 defines minimal `enquiry_status` CLOSED `OPEN` (default at creation, needs attention) → `CLOSED` (handled). Customer never sets status. Staff `ENQ-006` (`POST /enquiries/{enquiry}/close` + optional `reopen`) validates transition; `OPEN→CLOSED` is sufficient if staff only need boolean. Adding `ASSIGNED`/`IN_PROGRESS` etc. requires explicit justification.
- `CLOSED` may be terminal; `CLOSED→OPEN` reopen only if approved business needs it.

### 27.8 Privacy — Private Communication, Not Public Catalog

- Enquiry content (`subject`, `message`, `contact`, `product`/`order` context, attachments, status) is **private** — never embedded in public Catalog responses, never indexed for SEO.
- Customer retrieval `ENQ-002/003` uses `Cache-Control: private, no-store`; staff `ENQ-004/005` operational similarly private; never CDN public. Catalog remains `public` cacheable, Enquiry never.
- Field-level before serialization: only permitted fields per actor (`staff_internal_notes` never to customer, `internal storage keys` never, `credentials` never, private contact not to anonymous). Predictable `id` not authorization.

### 27.9 Enquiry Is Not Order / Request / Payment / Inventory

- Submitting an enquiry does **not** `reserve inventory`, `create Order`, `guarantee price`, `charge payment`, `create quoted_price`, `convert to Request`. No `order_id`/`payment_*` creation.
- No `order_status`, `payment`, `amount` fields from client.
- `Request` vs `Enquiry` remain separate domains — no automatic merge.

### 27.10 Operational Handling vs Ownership

- Staff `enquiries.view` is **operational access**, not ownership. `Staff → owns Enquiry` is false. Admin broader but still `authorized + auditability + data minimization`.
- `ENQ-006` may write only `enquiry_status` (controlled) and `staff_internal_notes`; customer-provided fields remain immutable; original enquiry preserved.
- Future `Enquiry → Order` boundary outside V1 — `ENQ-006` must not silently create Order/payment.

### 27.11 Caching, Pagination, Filtering, Sorting — Global Reuse

- **Pagination:** `ENQ-002`/`ENQ-004` use global `page`/`per_page` (1–100) → `meta.pagination` per `§11`; no enquiry-specific format.
- **Filtering (staff `ENQ-004` allow-list):** `search` (name/email/phone/subject/message/reference), `enquiry_status` CLOSED, `category` CLOSED where used, `product_id`, `order_id`, `created_from`/`created_to` ISO8601 `Z`.
- **Sorting:** `created_at DESC, id ASC` (newest first) + `id ASC` tie-breaker for both staff queue and customer history.
- **Search privacy:** queries run over **authorized dataset** — `Customer A /me/enquiries?search=...` searches only own; `Staff search` cannot reveal outside permitted scope.

### 27.12 Anonymous Mutation Safety & Rate Limiting

- `POST /enquiries` anonymous creation is **public mutation** → `RATE-LIMIT CANDIDATE` (high priority); abuse controls identified (`per-IP throttling, spam prevention, size limits, attachment limits`) but not implemented here; CAPTCHA deferred unless rate-limit insufficient.
- Do not use `same email + same message` as hard uniqueness constraint — legitimate repeated enquiries exist; rely on explicit idempotency/abuse controls where added.

### 27.13 Idempotency & Duplicate Handling

- `POST /enquiries` is **not inherently idempotent** — duplicate tap may create two enquiries. Duplicate submission risk is documented as operational noise; explicit idempotency mechanism deferred. Do not block similar `email+message` as duplicate via DB unique constraint.
- `ENQ-006` close/reopen is designed idempotent (same close replays); concurrency race `Staff A close + Staff B close` handled as state transition concurrency (`409 CONFLICT`).

### 27.14 Validation Hierarchy Applied to Enquiry

```
Transport → Schema → Auth (optional) → Authorization → Domain (contact valid, subject/message bounds, category CLOSED, product exists when linked, order ownership when linked, attachment safe, plain-text) → Concurrency Low → Persistence
```

Cross-field: `order_id` supplied → validated ownership; conditional: `phone`/`email` at least one for anonymous; state-dependent: `ENQ-006` validates current `enquiry_status` before transition; `subject`/`message` plain-text bounds.

## 28. Notification Conventions (Phase 1.27)

> **Authority:** Reusable conventions governing the Notification domain (`NOT-001`..`NOT-004` — recipient-scoped, downstream of business state, IN_APP primary, private, CLOSED types, `read_at` semantics). Consolidates `phases/phase-1.27.md`.

### 28.1 Notification Ownership — Recipient-Scoped, Server-Derived

- **Customer:** `Notification.recipient_user_id = customer` server-derived. `GET /me/notifications` paginates **own dataset only**; `GET /me/notifications/{notification}` verifies `owns` before serialization; `Customer A → Customer B notification` fails `404 RESOURCE_NOT_FOUND` masked per `§15.8`. `email` equality not ownership proof.
- **Staff/Admin:** Operational `notifications.read_operational` — shared queue or personal operational set per policy, not `global notification` where client determines visibility. Anonymous in-app notifications not supported (no authenticated recipient).
- **Never trust `recipient_user_id` from client:** `{"recipient_user_id":"..."}` rejected `422` per `§13.10` server-controlled field.

### 28.2 Read-State Semantics — `read_at` Contract

- **Single source of truth:** `read_at = null` → unread (`is_read=false`); `read_at = timestamp` → read (`is_read=true`). `is_read` is derived, not second source.
- **Does not mean business status:** `read` ≠ `Order processed` / `payment succeeded` / `request handled` — never overload `read` with domain state.
- **Mark read:** `PATCH /me/notifications/{notification} {read: true}` — only `read` boolean allowed; `type`/`title`/`message`/`recipient`/`target`/`created_at` rejected. Server derives recipient from auth; Staff cannot mark Customer notifications via Staff credentials.

### 28.3 Target References — Server-Generated, Not Capability Tokens

- **Server-generated:** `{type: ORDER|REQUEST|ENQUIRY, id: ...}` and `{source_type, source_id}` traceability are server-generated. Client-submitted `{"target":{"type":"ORDER","id":"another-order"}}` rejected.
- **Not a capability token:** `target` does not grant access to `Order B`; normal Order/Request/Enquiry authorization still required on follow-up `GET`. Staff operational `target` still authorization-checked.
- **Minimal context:** `title`/`message` include only server-derived `order_reference`, safe display fields; do not embed `entire Order`, `full profile`, `internal notes`, `payment secrets`.

### 28.4 Event-Derived Communication — Downstream Only

- **Source of truth:** `Order` / `Request` / `Enquiry` / `Payment` / `Fulfillment` authoritative. `Business Event → Notification` (e.g., `Order SHIPPED → ORDER_SHIPPED`). If `Notification says SHIPPED` but `Order says PROCESSING`, `Order` wins.
- **No client creation:** `POST /notifications` for normal customers/Staff/Admin prohibited; `type`/`title`/`message`/`recipient`/`target` server-generated from authoritative data (`order_reference` safely encoded, XSS-safe).
- **Failure isolation:** `Order creation → SHIPPED` must remain `SHIPPED` even if notification generation fails; Staff can still discover Order via operational queue. `Checkout → Order` must not fail due to notification persistence. Eventual consistency (`state updates immediately, notification appears shortly after`) acceptable; background/queued dispatch is future candidate (outbox pattern candidate, not implemented here).
- **Deduplication / idempotency:** Same source event processed twice must not create two identical notifications for same recipient; use event IDs / idempotency at processing boundary. `Notification → Delivery attempt(s)` model keeps logical message single while allowing future multi-channel.

### 28.5 Private Caching — No Public Cache

- **Never public-cache:** `GET /me/notifications` → `Cache-Control: private, no-store` (Customer), operational → `private/internal`. Not CDN. Consistent with `Order`/`Request`/`Enquiry` private collections.
- **Multi-device sync:** Server-side `read_at` authoritative ensures `Flutter read → website shows read` across devices; local device state is optimization only.

### 28.6 Machine-Readable Notification Types — CLOSED

- **CLOSED enum:** `ORDER_RECEIVED`, `ORDER_ACCEPTED`, `ORDER_PROCESSING`, `ORDER_READY_FOR_PICKUP`, `ORDER_SHIPPED`, `ORDER_DELIVERED`, `ORDER_COMPLETED`, `ORDER_CANCELLED`, `NEW_ORDER`, `NEW_MADE_TO_ORDER_REQUEST`, `NEW_ENQUIRY` — `UPPER_SNAKE_CASE`, validated `?type=ORDER_SHIPPED` against registry; unknown → `422 INVALID_VALUE` `field: type`. No per-product variants (`SOFA_SHIPPED`). `PAYMENT_*` deferred to Group H. Adding type within `v1` is contract change.
- **Machine logic on `type`, not `message`:** `type=ORDER_SHIPPED` → client renders `Your order OD-12345 has been shipped.`; frontend must not parse English `message` for logic; `type` + structured `target` allows `Next.js`/`Flutter` consistent rendering and deep-link.

## 29. User/Profile Conventions (Phase 1.28)

> **Authority:** Reusable conventions governing the User/Profile domain (`USER-001`/`USER-002` — self-context `/me`, server-controlled identity/role, credential separation, allow-list, private caching, cross-platform authoritative identity). Consolidates `phases/phase-1.28.md`.

### 29.1 Self-Context — `/me` Is Always the Authenticated Actor

- **Single self-context path:** `GET /api/v1/me` and `PATCH /api/v1/me` derive identity from `authenticated principal` (session/token). Client never supplies `user_id`, `customer_id`, `account_id` via query/body/URL to select another profile.
- **No identity substitution:** `?user_id=`, `/me?user_id=123`, `?as_user=...`, `{"user_id":"another"}` are rejected and do not bypass policy. `GET /me?user_id=123` prohibited.
- **Role-agnostic self:** `Customer → owns own account via /me`; `Staff → sees Staff own account via /me`; `Admin → sees Admin own account via /me` — never impersonation through `/me`. `GET /users/{id}` remains administrative (`users.manage_authorized` Phase 1.29), not self-service.
- **Why `/me` over `/users/{id}` for self-service:** Makes ownership explicit, reduces IDOR risk, and forces object-level ownership check before serialization.

### 29.2 Server-Controlled Identity & Role

- **Never trust client-supplied identity:** Backend obtains `authenticated principal` from authentication system; does not trust `request body user_id`, `query user_id`, `URL user_id`.
- **Role is server-controlled, never client-mutable:** `PATCH /me {role: ADMIN}` must fail; mass assignment `{"role":"ADMIN","permissions":["*"],"is_admin":true}` must not alter authorization. Field allow-list `§29.4` is normative; server-controlled fields `id`, `role`, `permissions`, `timestamps`, `verification state`, `account_state`, `staff_approval_state` are never changed via profile update.
- **Role mapping (mirrors `§18.1`):** CLOSED roles `CUSTOMER`/`STAFF`/`ADMIN` (`UPPER_SNAKE_CASE`); no `MANAGER`/`SUPPORT` etc. in V1. Client self-promotion (`{"role":"ADMIN"}`) rejected `422 INVALID_VALUE` or `403 FORBIDDEN` per CLOSED enum; adding role requires compatibility review.

### 29.3 Credential Separation — Profile vs Authentication

- **Profile (`PATCH /me`):** `ordinary personal data` — `name`, `phone` (allow-list). Do not treat `email` (identity), `password` (credential), `role`/`permissions`/`account_state`/`verification` as ordinary profile fields simply because they are stored on same `User` row.
- **Authentication (`AUTH-*`):** `credentials/session/security` — `password` change (canonical `POST /api/v1/auth/change-password` `AUTH-008`), password reset (`POST /auth/password/*`), email verification, email security-change workflow. `POST /api/v1/me/password` (`USER-003` legacy) is **RETIRED** and **not duplicated** as authoritative — canonical is `POST /api/v1/auth/change-password` per `api-contract.md §29.8` (`USER-004`). Canonical profile route is `PATCH /api/v1/me` (not `/me/profile`).
- **Credential handling:** `password` never serialized; password change requires `authenticated user + ownership of own credential + current credential verification` where model requires it; `Staff cannot normally change Customer passwords`; `Admin access to Customer credentials must not expose current password` — use dedicated recovery/security workflow where needed.

### 29.4 Profile Field Allow-List & Partial Semantics

- **Allow-list:** `customer_profile.mutable: name, phone` (plus `email` only where explicitly permitted via **dedicated security workflow**, not ordinary allow-list). `server-controlled: id, role, permissions, timestamps, verification state, account state`. Unknown fields rejected (`422 INVALID_VALUE` with `field`) per strict `unknown-field → error` rule (`§15` + `api-resources.md §8.4`).
- **Partial PATCH:** Only fields included in request are changed; omitted field remains unchanged; do not interpret omission as `set to null` unless field explicitly permits clearing. `phone: null` allowed only if contract permits clearing; `name: null` never valid.
- **Nullability documented:** `phone = null` valid if business permits; `email` nullable forbidden (required). Contract must state choice; see `api-resources.md §8.1`.
- **Mass-assignment protection:** Laravel must not `$request->all() → model fill`; `validated input → DTO/command → domain` per `AGENTS.md §3`.

### 29.5 Private Caching — `/me` Is Never Publicly Cached

- **Classification:** `GET /me` → **PRIVATE** — `Cache-Control: private, no-store` (not CDN, not public catalog cache). `PATCH /me` → **NON-CACHEABLE** mutation.
- **Do not leak:** Private profile responses must not be cached in shared CDN or cross-user `Cache-Control`.
- **Lightweight:** Keep `GET /me` compact enough for frequent `app starts → GET /me` calls without loading `orders/notifications/cart/requests/enquiries`.

### 29.6 Cross-Platform Authoritative Identity

- **Shared identity:** Same `User` (`id`, `role`, `email_verified`, `profile`) across `Next.js` (browser session httpOnly cookie) and `Flutter` (API credential) via same Laravel backend; `website phone change → Flutter sees same`.
- **Backend authoritative:** Local `Next.js`/`Flutter` cached profile/role state is advisory only; `role`, `account_state`, `verification`, `authorization` must be refreshed from backend — do not assume cached role valid indefinitely after `Staff role changes` / `account disabled` / `permission change`.
- **Synchronization:** Server is source of truth; `Next.js`/`Flutter` may cache for UX but must revalidate via `GET /me` where operational decisions matter.



