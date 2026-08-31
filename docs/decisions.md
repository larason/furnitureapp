# Architecture Decision Records — Furniture Platform

> **Source:** Phases 1.1–1.14. New decisions are appended here per `phase-1.13.md §76` / `phase-1.14.md §71` (do not create `api-response-decisions.md` or `api-input-decisions.md`).

---

### ADR/API-013 — Response Envelope: `data` + `meta.pagination` (no `result`/`payload` drift)

**Decision:** All `v1` successful responses use `data` as primary member. Single resource → `data: object`; collection → `data: []` + `meta.pagination: {current_page, per_page, total, last_page, has_next, has_previous}`. Empty collection → `data:[]` (not `null`); missing resource → `errors`.

**Reason:** One predictable contract for Next.js/Flutter/Admin; prevents per-endpoint `result`/`payload` drift (`phase-1.13.md §8-10, §34`).

**Status:** Accepted

**Affected:** All `v1` resources

---

### ADR/API-014 — Flat Resource Representation (not JSON:API hybrid)

**Decision:** Simple flat objects inside `data` (e.g., `{"data":{"id":"123","name":"..."}}`), not JSON:API `attributes`/`relationships` hybrid. No `type` field required per resource.

**Reason:** Small/medium custom API does not justify JSON:API complexity (`§11-12, §14`).

**Status:** Accepted

---

### ADR/API-015 — Field Naming: `snake_case`

**Decision:** JSON and query params share `snake_case` lowercase (`product_type`, `created_at`, `order_status`, `per_page`). No `productType` / `product-type` mixing.

**Reason:** Single canonical style (`§15`, query conventions).

---

### ADR/API-016 — Timestamps: ISO 8601 UTC with `Z`

**Decision:** All timestamps `2026-08-30T15:30:00Z` (RFC 3339 UTC). Not `2026-08-30 15:30:00`, not Unix integer.

**Reason:** One standard, avoids mixed formats (`§16-17`). Changing within `v1` is breaking.

---

### ADR/API-017 — Nullability: `null` vs Absent

**Decision:** `null` = contract field currently has no value (`"delivery_address": null` for `PICKUP`); absent = field not part of selected representation. No alternation `null`/`""`/`false`.

**Reason:** Explicit contract for clients (`§18-19`).

---

### ADR/API-018 — Booleans: JSON `true`/`false`

**Decision:** Booleans as JSON booleans, not `"true"`/`1`/`"yes"`. Applies to `is_primary`, `has_next`, etc.

**Reason:** One representation (`§20`, query boolean `true`/`false`).

---

### ADR/API-019 — Money: Integer Minor Units + Currency (Single Global Rule)

**Decision:** All monetary fields (`product.price`, `variant.price`, `order.*`, `payment.amount`, `delivery_fee`) are `{amount: int (minor units, 1 TZS=100), currency: "TZS"}`. Example `{"amount":125000000,"currency":"TZS"}` = 1,250,000.00 TZS. Query `?min_price=50000000` same minor-unit value. No `"TZS 1,250,000"` string, no bare number.

**Reason:** Aligns with `query-parameter-conventions.md §9` (minor-unit integer string) and `AGENTS.md PRICE-004` (no floating arithmetic). Single wire unit prevents `500000` ambiguity (5,000 vs 500,000) flagged in Phase 1.11 review. Changing `price:number` → `price:object` within `v1` is breaking.

**Status:** Accepted

---

### ADR/API-020 — Availability: Filter vs Response Split (Closed Enum)

**Decision:** Filter `?availability=available|unavailable` (lowercase, only values). Product response `availability` mirrors filter (`"available"`/`"unavailable"`) plus separate display bucket `stock_indicator: IN_STOCK|LOW_STOCK|MADE_TO_ORDER` (response only, not filterable). `?availability=IN_STOCK` is validation error. `product_type` `IN_STOCK`/`MADE_TO_ORDER` is distinct concept per enum policy.

**Reason:** Fixes review finding where `IN STOCK, LOW STOCK, MADE TO ORDER` were introduced as undocumented filter values; response vs filter vocabularies now separate.

---

### ADR/API-021 — Enums: CLOSED, Canonical Keys (Single `availability` Exception)

**Decision:** All V1 enums CLOSED with canonical query keys: `product_type`, `fulfillment_type`, `order_status`, `request_status`, `enquiry_status`, `payment_status` (not `payment_state`), `role`; response `status` fields send machine `UPPER_SNAKE_CASE` values (`PENDING_PAYMENT`, `READY_FOR_PICKUP`); generic `?status=` is validation error. **Explicit frozen exception:** `availability` response/request is lowercase `available|unavailable` (CLOSED) per `api-contract.md §3.9` to mirror `?availability=` filter — the single exception to `UPPER_SNAKE_CASE`. Display bucket is canonical `stock_indicator: IN_STOCK|LOW_STOCK|MADE_TO_ORDER` (`UPPER_SNAKE_CASE`, response-only); `availability_display` is not valid.

**Reason:** Past drift `status` vs `order_status` and `payment_state` vs `payment_status` caused allow-list ambiguity. `availability` lowercasing prevents filter/response drift (`available` vs `AVAILABLE`); without exception, `UPPER_SNAKE_CASE` rule would contradict the `available/unavailable` query contract. `stock_indicator` vs `availability_display` drift similarly required freezing `stock_indicator` canonical.

---

### ADR/API-022 — Pagination Placement: `meta.pagination` with `id ASC` Tie-Breaker

**Decision:** Pagination metadata under `meta.pagination` (`current_page, per_page, total, last_page, has_next, has_previous`), `last_page` floor `1` (empty → `1`, not `0`), beyond `last_page` → empty `data[]` with metadata. Deterministic ordering `ORDER BY <primary> <dir>, id ASC` (always `ASC` tie-breaker, superseding illustrative `DESC` in spec §52) for stable pages.

**Reason:** Follows `pagination-convention.md` / `query-sorting-policy.md §6`; prevents duplicate/missing records when primary ties.

---

### ADR/API-023 — Relationships: Embedded vs Summary, No Recursive Embedding, No Dynamic `include`/`fields`

**Decision:** Small child data embedded (`product.images[]` as objects); large/independent as summary `{id,slug,name}` via subresource (`/products/{product}/variants`); sensitive via limited summary; no recursive embedding (`product→category→products`); V1 `?include` / `?fields` deferred.

**Reason:** Bounded representations per `phase-1.13.md §30-32` and `query-relationship-policy.md`.

---

### ADR/API-024 — Links: Pagination URLs & `self` Deferred

**Decision:** Numeric pagination metadata only in V1; navigation URLs (`first/prev/next/last`) and per-resource `self` links omitted by default (deferred).

**Reason:** Absolute URLs problematic across `local`/`staging`/`production` (`phase-1.13.md §35-36`, `pagination-metadata-policy.md`); additive later under `links`.

---

### ADR/API-025 — Serialization Security & Exposure Levels

**Decision:** Explicit allow-lists per audience: `PUBLIC` (product name/slug/price), `CUSTOMER` (own orders), `STAFF/ADMIN` (operational quantities), `INTERNAL` (never). No `model.toArray()`; private attachments not permanently public; `password_hash` etc. never serialized. AUTHORIZATION before serialization.

**Reason:** Prevents mass-serialization leakage (`§50-52`).

---

### ADR/API-026 — Compatibility: `v1` Breaking vs Additive

**Decision:** Within `v1`: no silent rename, type/meaning/nullability change, or removal; adding truly optional field is non-breaking. Money shape or timestamp format change is breaking per versioning policy.

**Reason:** `phase-1.13.md §53-58`, `api-versioning-strategy.md`.

---

### ADR/API-IN-001 — JSON is the Default Request Format

**Decision:** All normal create/update/action requests use `application/json` UTF-8; `multipart/form-data` only for attachment file uploads. Unsupported `Content-Type` → validation error, not silent guess.

**Reason:** Single predictable transport (`phase-1.14.md §9-10`) for Next.js/Flutter/Admin; `multipart` isolated to file bytes.

**Status:** Accepted | **Affected:** All `v1` endpoints

---

### ADR/API-IN-002 — Version 1 Enums Are CLOSED for Input

**Decision:** Request enum fields (`product_type`, `fulfillment_type`, `order_status`/`request_status`/`enquiry_status`/`payment_status`, `role`) accept only documented `UPPER_SNAKE_CASE` values; `available`/`unavailable` is the single lowercase exception mirroring response. Unknown value → validation error, not `"other"`.

**Reason:** Prevents `other` drift (`phase-1.14.md §5, §61`); aligns with response CLOSED policy and query `query-enum-policy.md`.

**Status:** Accepted

---

### ADR/API-IN-003 — Client-Supplied Totals Are Never Authoritative

**Decision:** Backend calculates `subtotal`, `delivery_fee`, `total` from authoritative prices/stock; `{total:1000}` or `{delivery_fee:{amount:1,currency:"TZS"}}` from customer is ignored/rejected. Checkout input is only `fulfillment_type` + conditional `delivery_address`.

**Reason:** `AGENTS.md §13`, `phase-1.14.md §23` — client totals would bypass money authority.

**Status:** Accepted | **Affected:** Cart/Checkout/Order

---

### ADR/API-IN-004 — PATCH Is for Partial Modification Only (Allow-Listed)

**Decision:** `PATCH` bodies contain **only fields being changed** (e.g., `{"phone":"+255..."}`), partial not full replacement; `PUT` means complete replacement where permitted (otherwise unused). Only documented mutable fields (`name`, `phone`) may be patched — not `role`, `account_status`, `order_total`, `price`.

**Reason:** Prevents arbitrary field writes and role escalation (`phase-1.14.md §29-31`).

**Status:** Accepted

---

### ADR/API-IN-005 — Unknown Input Fields: Strict Rejection

**Decision:** Unknown fields in create/update/action bodies are **rejected** (validation error) rather than silently accepted/stored. Forward-compatibility ignore only where explicitly documented. `user_id` ownership override via body is rejected — ownership from `auth` (`phase-1.14.md §39-40`).

**Reason:** Catches typos, stale mobile clients, version mismatch, mass-assignment (`phase-1.14.md §53`, `query-parameter-conventions.md §5` allow-list).

**Status:** Accepted

---

### ADR/API-IN-006 — Server-Controlled Fields Are Never Client-Settable

**Decision:** `id`, `created_at`, `updated_at`, `order_reference` (`OD-...`), `order_customer_identity`, `current_order_status`/`status`, `payment_status`/`payment_confirmation`, `inventory` (`quantity`/`reserved_quantity`), `final_order_total`, `status_history` are server-generated; if sent, ignored/rejected. Mass-assignment via `$request->all()` is forbidden — allow-list mapping `validated input → DTO → domain`.

**Reason:** `phase-1.14.md §21, §41-42`, `AGENTS.md §3` backend authority.

**Status:** Accepted

---

### ADR/API-IN-007 — Money Input Mirrors Response (`{amount minor units, currency}`)

**Decision:** Monetary inputs use same shape as responses: `{amount: int minor units 1 TZS=100, currency:"TZS"}`; never `"TZS 30,000"` string. Where `fulfillment_type=DELIVERY` is chosen, backend resolves `delivery_fee`; customer does not submit arbitrary fees.

**Reason:** Single global money rule (`phase-1.13` + `phase-1.14.md §20`).

**Status:** Accepted

---

### ADR/API-IN-008 — Conditional, Nullable, and Sizing Rules

**Decision:** `delivery_address` is **conditional** (required when `fulfillment_type=DELIVERY`, omitted/`null` for `PICKUP`); `null` only where explicitly nullable (`phase-1.14.md §14`); empty `""` not universal “not supplied”; whitespace trimmed server-authoritatively; arrays single-type, `items:[]` invalid where ≥1 required; duplicate `product_id` handling per contract; reasonable JSON/file/array/cart limits enforced; `multipart` field names remain `snake_case` consistent with JSON; checkout/payment idempotency deferred for later `Idempotency-Key`.

**Reason:** Standardizes required/optional/conditional, empty/whitespace, array/nested, file-upload and size handling (`phase-1.14.md §12-16, §34-37, §56-58`).

**Status:** Accepted

---

### ADR/API-VAL-001 — Layered Validation (Not Monolithic)

**Decision:** Validation is separated into distinct layers: Transport → Schema/input → Authentication → Authorization → Domain/business (including cross-field, conditional, state-dependent) → Concurrency/transaction → External-system. Each layer has clear ownership. Do not treat all validation as one generic validator and do not access business records before schema validation nor external providers before authorization.

**Reason:** Prevents duplication across Next.js/Flutter/Laravel, avoids leaking authorization info, and enforces correct pipeline `phase-1.15.md §8, §37, §73`.

**Status:** Accepted | **Affected:** All `v1` endpoints

---

### ADR/API-VAL-002 — Frontend Validation Advisory; Backend/Domain Authoritative

**Decision:** Client validation (Next.js, Flutter) is UX optimization only (e.g., disable Checkout when stock appears unavailable). Laravel/domain is the authority for every business decision — stock, pricing, delivery fee, cancellation window (backend time), order transitions, inventory concurrency, payment confirmation. Clients never override via direct API calls.

**Reason:** Backend authority is core architectural principle (`AGENTS.md §3`, `§12-13`) and `phase-1.15.md §14-15`. Duplicated critical logic in `Flutter.getValidation` vs `Laravel controller` is prohibited.

**Status:** Accepted

---

### ADR/API-VAL-003 — Version 1 Enums Are CLOSED (Strict)

**Decision:** All V1 enums are CLOSED by default (`IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`, `PENDING_PAYMENT`→`CANCELLED`, etc.). Validation enforces documented values; unknown → validation error, not silently normalized to `OTHER/UNKNOWN/CUSTOM`. Adding a new value is a formal API compatibility decision per versioning policy. Physical DB enum representation does not dictate API semantics. Frozen exception: `availability` `available|unavailable` lowercase mirrors query filter; all other enums `UPPER_SNAKE_CASE`.

**Reason:** `phase-1.15.md §5, §30-31, §56`; prevents `available` vs `AVAILABLE` drift and silent enum growth.

**Status:** Accepted

---

### ADR/API-VAL-004 — Business State Transitions Cannot Use Generic Field Writes

**Decision:** Order/request status and similar controlled state must not be changed via generic `PATCH {status: "SHIPPED"}` or `{status: "CANCELLED"}`. Transitions are explicit actions (`POST /orders/{order}/cancel`, `/ship`, `/deliver`, etc.) validating `current state, requested transition, actor auth, business preconditions`. `PROCESSING→SHIPPED` may be valid; `COMPLETED→SHIPPED` invalid. State is authoritative server-side, not client-submitted.

**Reason:** `phase-1.15.md §18, §29` — prevents bypassing transition validators and arbitrary state forcing.

**Status:** Accepted

---

### ADR/API-VAL-005 — Client-Supplied Financial Totals Never Authoritative

**Decision:** Client sends `product_id`/`variant_id` + `quantity`, `fulfillment_type` + conditional `delivery_address`/`contact`; backend calculates authoritative `current price`, `subtotal`, `delivery_fee`, `total`. `{total:1000}` or `{delivery_fee:{amount:1,currency:"TZS"}}` from customer is rejected/ignored. `approved delivery fee` is business-determined, not customer-controlled.

**Reason:** `AGENTS.md §13`, `phase-1.15.md §21-23` (`CART-005/PRICE-001`); prevents price manipulation. Complements ADR/API-IN-003 but re-affirmed as validation-layer rule.

**Status:** Accepted | **Affected:** Cart, Checkout, Order, Payment

---

### ADR/API-VAL-006 — Critical Inventory Validation Must Remain Concurrency-Safe

**Decision:** Inventory validation (`read → validate quantity → reserve/consume`) must be transactional/concurrency-safe (atomic update / transaction isolation / locking — mechanism deferred). Prohibits bare `SELECT stock; if stock>qty then UPDATE stock` without protection. UI `In Stock` is only informative; final check is Laravel/DB inside transaction. Failure must not leave partial reservation.

**Reason:** `AGENTS.md §12` overselling prevention, `phase-1.15.md §20, §42-43` (INV-003).

**Status:** Accepted

---

### ADR/API-VAL-007 — Cross-Field, Conditional & State-Dependent Validation

**Decision:** Rules examining multiple fields together are explicit: `DELIVERY→address required`, `PICKUP→fee zero / not customer-supplied`, `MADE_TO_ORDER→checkout prohibited`. Conditional fields (`delivery_address` conditional on `fulfillment_type`) not universally required. State-dependent checks current authoritative state (`Order=PROCESSING` vs `COMPLETED`). Validate relationships thoroughly (`variant exists AND belongs to product AND active AND purchasable`).

**Reason:** `phase-1.15.md §16-18, §66-69` — prevents reducing business rules to isolated field validators.

**Status:** Accepted

---

### ADR/API-VAL-008 — Validation Error Categories, Codes vs Messages, Stability

**Decision:** Categories: Structural `INVALID_TYPE/INVALID_FORMAT/INVALID_VALUE/MISSING_REQUIRED_FIELD`; Business `PRODUCT_NOT_PURCHASABLE/INSUFFICIENT_STOCK/ORDER_NOT_CANCELLABLE/INVALID_ORDER_TRANSITION`; Authorization `AUTHENTICATION_REQUIRED` (canonical; `NOT_AUTHENTICATED` legacy alias)/`FORBIDDEN` (canonical; `RESOURCE_NOT_OWNED` legacy alias per `api-contract.md §15.8`) ; External later `PAYMENT_PROVIDER_ERROR`. Layer detecting error owns it. Machine-stable `code` separate from human `message`; frontend acts on code (`INSUFFICIENT_STOCK`) and may localize display. Messages must not leak `SQL/schema/stack/paths/secrets`. Renaming `INSUFFICIENT_STOCK` within `v1` is breaking; codes version-sensitive. Future contract supports field-level `field: delivery_address.city` and multiple determinable schema errors per request. Exact JSON deferred to Phase 1.16.

**Reason:** `phase-1.15.md §32-35, §47-49`; aligned with `api-contract.md §15.15` CLOSED registry (aliases `NOT_AUTHENTICATED`/`RESOURCE_NOT_OWNED` remain registered for compatibility, canonical codes are `AUTHENTICATION_REQUIRED`/`FORBIDDEN`/`RESOURCE_NOT_FOUND`).

**Status:** Accepted

---

### ADR/API-VAL-009 — Server-Controlled Fields, Unknown Fields & Mass-Assignment Protection

**Decision:** Server-controlled fields `id`, `created_at`/`updated_at`, `order_reference` (`OD-...`), `current_order_status`, `payment_status`/`confirmation`, inventory quantities, `final_order_total`/`approved_delivery_fee`, `customer identity`, `status_history` are never client-settable — REJECT/IGNORE if sent, ownership derived from `auth`. Unknown fields in strict create/update/action are rejected (validation error), not silently stored; ignore-forward only where explicitly documented. Mass assignment via `$request->all()→model fill` is forbidden — allow-list `validated → DTO → domain`.

**Reason:** `phase-1.15.md §45-46, §60, §74`.

**Status:** Accepted

---

### ADR/API-VAL-010 — Validation Compatibility & Authorization Leakage

**Decision:** Making a previously accepted input rejected, or making a field suddenly required, can be breaking within `v1` and follows versioning policy. Adding CLOSED enum values is a compatibility decision. Authorization design must not reveal whether `Order OD-xxx exists` to unauthenticated callers via enumeration; `Not Found vs Forbidden` mapping deferred to error phase but leakage prevention is normative now. Validation changes respect `api-versioning-strategy.md`.

**Reason:** `phase-1.15.md §55-56, §38-39`.

**Status:** Accepted

---

### ADR/API-VAL-011 — Transactional Validation, Idempotency & Auditability (incl. Webhooks)

**Decision:** Domain validation for `stock, order creation, reservation, critical transitions` must occur inside/immediately around transaction/concurrency control to prevent stale assumptions. Idempotency for **client-initiated** operations (`POST checkout`, `POST payment initiation` with same `Idempotency-Key`) must not duplicate business effects; validation cooperates with idempotency. **Webhook idempotency (generic, provider-agnostic):** Repeated deliveries of the same provider event must not create duplicate payment/order effects. This requires stable event identity (provider-supplied idempotency key / event identifier persisted alongside the webhook), deduplication via a durable store (e.g., unique constraint on `webhook_event_id` / idempotency key table), and atomic check-then-apply of state changes inside a transaction (lookup existing event → if seen, return prior result without reapplying; if new, apply payment/order mutations atomically). Provider-specific fields, payload schemas, and signature verification remain deferred to Group H.

**Reason:** `phase-1.15.md §42-44, §71-72, §53-54`; payment rules require webhook handling be idempotent (`AGENTS.md §14`), extended here to a generic, provider-agnostic guarantee. Group H retains provider specifics.

**Status:** Accepted

---

### ADR/API-ERR-001 — All Errors Use Single `errors` Array

**Decision:** Every API failure returns `{"errors":[{"code","message","field","details"}],"meta":{"request_id":...}}` — `errors` is always an array, even for single error. Never `error`, `message`, `success:false` envelope. Allows multiple field errors + future structured details without envelope drift. Successful responses use `data`, never both `data` and `errors`.

**Reason:** `phase-1.16.md §9-11` — single predictable contract for Next.js/Flutter/Admin; easy branching `HTTP success → data` vs `HTTP error → errors`; generic across validation/business/auth conflicts.

**Status:** Accepted | **Affected:** All `v1` endpoints

---

### ADR/API-ERR-002 — Every Public Error Has Stable Machine Code (`UPPER_SNAKE_CASE`, CLOSED)

**Decision:** Each error has documented `code` in `UPPER_SNAKE_CASE` (`INSUFFICIENT_STOCK`, `ORDER_NOT_CANCELLABLE`). Codes are CLOSED, not database/Laravel exception names, no per-field explosion (`NAME_MISSING`) except where branch-required. Frontend branches on `code` (mapped to localized UI), not English `message`. Messages are human, improvable, action-oriented, never `SQLSTATE…` or stack traces.

**Reason:** `phase-1.16.md §12-16, §54-58` — machine contract prevents brittle parsing; localization via frontend; closed registry prevents drift.

**Status:** Accepted

---

### ADR/API-ERR-003 — HTTP Status and API Error Code Are Separate

**Decision:** Response has both `HTTP status` (transport class: `400` malformed, `401` missing auth, `403` not authorized, `404` not addressable, `409` state/concurrency conflict, `422` well-formed business/validation invalid, `413` too large, `415` unsupported type, `405` unsupported method, `429` Rate Limited + `Retry-After`, `502/503/504` upstream, `500` unexpected) and machine `code`. Changing status is a contract change. Validation default is `422` unless conflict semantics make `409` more precise (inventory race, transition conflict documented per endpoint).

**Reason:** `phase-1.16.md §17-19, §81` — standard HTTP semantics + machine business meaning; `INSUFFICIENT_STOCK` may map `409/422*` per resource, resolved per endpoint.

**Status:** Accepted

---

### ADR/API-ERR-004 — Production Errors Never Expose Implementation Details (Security)

**Decision:** Production responses never expose `database SQL`, stack traces, `password`/`auth`/`payment secrets`, `internal file paths`, server IPs, framework exception names (`ModelNotFoundException`), `other_customer_id`, `internal_reservation_id`, provider secrets. Client sees safe `code`/`message`/`field`/`details` + `meta.request_id`; server logs retain full exception/stack/deployment context. Unauthorized resource access (another customer's order) does not reveal existence via error. Security tests verify this.

**Reason:** `phase-1.16.md §15, §28, §61, §88-89` — prevents data leakage and supports support workflow (`request_id` → logs).

**Status:** Accepted

---

### ADR/API-ERR-005 — Field-Level Validation Uses Canonical Dot-Path, Multiple Errors

**Decision:** Field errors carry `field` as canonical dot notation (`delivery_address.city`, `delivery_address.phone`) and arrays as `items.0.quantity`; one style across API. For ordinary schema validation, return **all safely determinable** field errors together, not first-only. Unknown-field and closed-enum failures fit same `{"code":"INVALID_VALUE","field":"fulfillment_type"}` shape. Business `details` (e.g., `available_quantity`) are structured safe object, never second message.

**Reason:** `phase-1.16.md §25-27, §60` — precise UX (field highlighting), multi-error form improvement, safe details.

**Status:** Accepted

---

### ADR/API-ERR-006 — Version 1 Error Codes Are Stable, Version-Sensitive, Non-Explosive

**Decision:** Within `v1`, removing/renaming error code, changing semantics/status, changing field-path format, removing documented `details` are **breaking**; improving `message`, adding optional safe `details`, adding reviewed new code for new operation are **potentially non-breaking**. Error codes/categories are CLOSED like validation enums (`§85`); no `commerce.inventory.insufficient_stock` namespaces in V1; avoid explosion (`INSUFFICIENT_STOCK_FOR_SOFA` forbidden) and ambiguous `ERROR`/`FAILED` generic. Compatibility aligns with `api-versioning-strategy.md`.

**Reason:** `phase-1.16.md §55, §84-86, §79-80` — protects Next.js/Flutter release trains; registry in `api-contract.md §15.15` stays controlled.

**Status:** Accepted

---

### ADR/API-ERR-007 — Customer-Owned Resources Use 404 Masking; Request ID + Retry Semantics

**Decision:** Private customer-owned lookup that fails ownership returns **`404 RESOURCE_NOT_FOUND` / `ORDER_NOT_FOUND`** (not `403`) to avoid leaking `exists` via enumeration (`GET /orders/1001…1003` non-distinguishing). Every error carries envelope `meta.request_id` (per-request correlation ID, not user/order/payment ID) for support triage. Retry semantics: `RETRYABLE` (502 transient) vs `NOT_RETRYABLE` (`INVALID_VALUE`, `FORBIDDEN`) vs `REQUIRES RECONCILIATION` (`409 CONFLICT`, `INSUFFICIENT_STOCK`) are documented conceptually, not per-error field; `429` uses standard `Retry-After` header. Internal `INTERNAL_SERVER_ERROR` reflects distinct `500` with `request_id` only.

**Reason:** `phase-1.16.md §23-24, §30-32, §33-35, §88` — enumeration protection + operational support + deterministic idempotency semantics.

**Status:** Accepted

---

### Pending: OpenAPI Operations, Payment Provider

**Deferred:** Complete `openapi.yaml` operations, provider-specific payment error codes/SDK/webhook payload validation (Group H, slots into `EXTERNAL_SERVICE_ERROR` family), cursor pagination tokens (Group T if ever). `Phase 1.16` error registry and status matrix now accepted — see `api-contract.md §15.15`.
