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

### ADR/AUTH-001 — Three Human Actor Roles (CLOSED)

**Decision:** V1 has exactly three CLOSED roles: `CUSTOMER` (primary, self-registers, owns own account, maximum commerce flexibility), `STAFF` (operational commerce, approved by Admin, no customer-account ownership), `ADMIN` (highest admin, staff approval/management, audited). No `MANAGER`/`DELIVERY_AGENT` etc. in V1; additions require compatibility/business review.

**Reason:** `phase-1.17.md §5-6`, `api-contract.md §17.1` — minimal roles for small business, prevents privilege drift.

**Status:** Accepted | **Affected:** All authZ, `api-contract.md §17.1`, `api-conventions.md §18.1`, `api-resources.md §12.1`

---

### ADR/AUTH-002 — Customer Self-Registration (No Staff Gate)

**Decision:** Customers self-register via public registration without staff approval (`Visitor → Register → Customer account → Authenticate`). Staff/admin accounts are **not** via public registration. Minimal data `name`/`email`/`phone`/`password`; not `address book`.

**Reason:** `phase-1.17.md §12-13`, `api-contract.md §17.2` — preserves public catalog UX and `IDENT-001`.

**Status:** Accepted

---

### ADR/AUTH-003 — Customer Account Ownership (Non-Negotiable)

**Decision:** A customer’s account belongs to that customer. Staff have **no** ordinary permission to browse as customer, restrict browsing, restrict legitimate ordering, alter profile arbitrarily, access credentials, impersonate, or cancel access for convenience. Admin authority is bounded to explicit auditable security operations (review, disable compromised, force reset, revoke sessions) — not unrestricted impersonation, and no hard-delete when history exists.

**Reason:** `phase-1.17.md §8`, `§48`, `api-contract.md §17.12`, `AGENTS.md §17` ownership — protects customer trust; distinguishes ownership vs operational access.

**Status:** Accepted

---

### ADR/AUTH-004 — Staff Scope (Operate Commerce, Not Customer Accounts)

**Decision:** Staff operate commerce workflows (`orders`, `requests`, `enquiries`, `operational notifications`, approved catalog/inventory/order ops) but **do not** control customer account ordering/browsing and never receive `Customer password`/`auth secrets`/`unrestricted impersonation`. Role ≠ permission — backend evaluates `role+resource+action+ownership+state` (Phase 1.18).

**Reason:** `phase-1.17.md §9`, `§49`, `§51`, `api-contract.md §17.1/§17.12`.

**Status:** Accepted

---

### ADR/AUTH-005 — Staff Approval Is Admin-Only

**Decision:** Only authorized `ADMIN` approves staff (`Staff candidate → Admin review → Approved → Active` or `Admin invite → activate`). Staff cannot self-approve or approve peers; customers cannot approve staff. Lifecycle `PENDING`/`ACTIVE`/`SUSPENDED`/`DISABLED` deferred to authorization phase; no new public role enum.

**Reason:** `phase-1.17.md §18-19`, `api-contract.md §17.6`, `api-conventions.md §18.7` — prevents privilege escalation.

**Status:** Accepted

---

### ADR/AUTH-006 — Checkout Requires Authenticated Customer; Anonymous Catalog & Requests Remain Public

**Decision:** `Browse` (`products`, `categories`, `search`, `product details`, `prices`, `availability`) requires **no** auth; `Made-to-order Request` / `General Enquiry` allow `User=none` + contact (linked when authenticated); `Checkout` / `own orders` / `own tracking` / `own requests/enquiries history` require authenticated `CUSTOMER`. Backend enforces boundary (`401 AUTHENTICATION_REQUIRED` / `CHECKOUT_REQUIRES_AUTHENTICATION` on anonymous checkout); SEO pages remain SSR-public without login.

**Reason:** `phase-1.17.md §22-24`, `§65`, `api-contract.md §17.11`, `api-conventions.md §18.2` — preserves `IDENT-001` vs `IDENT-002`/`ORD-002`.

**Status:** Accepted

---

### ADR/AUTH-007 — Shared Cross-Platform Identity & Session/Token Principles

**Decision:** One shared customer identity across `Next.js` (first-party browser session, httpOnly cookie, not long-lived JS secret) and `Flutter` (API credential/token) against same Laravel backend; same credentials see same orders/profile. `Admin` shares same system. Logout invalidates server-side session/credential (deleting frontend token alone insufficient). Multiple customer sessions (web/phone/tablet) **permitted** (logout on one not all); revocation, shorter admin timeout, MFA, visibility, forced logout, audit logging evaluated later. Passwords only as secure one-way hash, never in responses; change via authenticated secure workflow (not `PATCH /me {password}`); `role` is server-controlled.

**Reason:** `phase-1.17.md §29-36`, `§37`, `§56`, `api-contract.md §17.7/§17.10/§17.14`, `api-conventions.md §18.3-18.4`.

**Status:** Accepted

---

### ADR/AUTH-008 — Password Recovery & Verification (Secure, Email-Deferred, Anti-Enumeration)

**Decision:** Recovery uses secure time-limited single-use token (`request → token → set new password → invalidate`), no plaintext password or reset secret in response/storage, generic `"Request received."` to avoid enumeration; email delivery deferred to Group R (contract supports recovery, may be operationally unavailable until then — not weakened). `email_verified_at` is server-set via secure token (`email_verified:true` from client not authoritative); whether verification required before checkout deferred. `Phone/SMS OTP` not an auth requirement by default. Login/recovery avoid distinguishing `email exists` vs `not exists` unless justified; brute-force rate limiting identified for later.

**Reason:** `phase-1.17.md §38-42`, `§62-64`, `api-contract.md §17.8-17.9`.

**Status:** Accepted

---

### ADR/AUTHZ-001 — Three-Role Authorization Model (CLOSED)

**Decision:** V1 authorization uses exactly three CLOSED roles `CUSTOMER`/`STAFF`/`ADMIN` as inputs to `ROLE + RESOURCE + ACTION + OWNERSHIP + STATE + CONTEXT`. No `MANAGER`/`DELIVERY_AGENT` etc. without explicit approval; use explicit permissions before multiplying roles.

**Reason:** `phase-1.18.md §6`, `api-contract.md §18.1` — minimal roles, least privilege, prevents explosion.

**Status:** Accepted

---

### ADR/AUTHZ-002 — Customer Owns Own Account (Object-Level)

**Decision:** For all customer-owned resources (`Orders`, `Notifications`, `Cart`, `Requests`, `Enquiries`, `Profile`) the authenticated principal `==` `resource owner` is required. Knowing `user_id` or submitting `{"user_id":"another"}` never grants access; `?user_id=` cannot expand scope; pagination/search operate over authorized dataset.

**Reason:** `phase-1.18.md §14-15`, `api-contract.md §18.3`, `api-conventions.md §19.5`.

**Status:** Accepted

---

### ADR/AUTHZ-003 — Staff Are Operational, Not Customer-Account Administrators

**Decision:** Staff may `view/operate` operational commerce (`orders.view_operational`, `orders.process/ship/deliver` with valid state, `requests/enquiries` operational, approved `inventory/catalog`) but must not `change customer passwords/roles/disable accounts/impersonate/view credentials/change auth state/transfer ownership/block browsing-or-ordering`. Staff capabilities are explicit per-resource/action, not `STAFF → all DB`.

**Reason:** `phase-1.18.md §18-19`, `api-contract.md §18.2/§18.6`, `api-resources.md §13.1-13.7`.

**Status:** Accepted

---

### ADR/AUTHZ-004 — Admins Approve Staff (Server-Controlled, Audited)

**Decision:** Only authenticated `ADMIN` with `staff.approve` may approve staff (`candidate → Admin → Active`). Staff cannot self-approve, customers cannot approve staff, and role changes (`CUSTOMER/STAFF` cannot assign) are privileged, validated, and audited (`actor/action/resource/target/timestamp/result`).

**Reason:** `phase-1.18.md §27-28`, `api-contract.md §18.8`.

**Status:** Accepted

---

### ADR/AUTHZ-005 — Staff Cannot Arbitrarily Restrict Customer Browsing/Ordering

**Decision:** No generic `STAFF → block_customer`. Staff cannot disable product browsing or block legitimate checkout/order; `Admin` may only restrict under explicit approved security policy with `reason/authorization/audit/impact/recovery`, and public catalog (`/products`, `/categories`) remains public even when account has problems unless specific legal/security mandate.

**Reason:** `phase-1.18.md §20`, `§57-59`, `api-contract.md §18.2`.

**Status:** Accepted

---

### ADR/AUTHZ-006 — Authorization Is Role + Resource + Action + State (Deny by Default)

**Decision:** Allow only if `authenticated AND authorized actor AND resource AND action AND ownership/context valid AND business-state valid`. **Deny by default** for protected resources (explicit `PUBLIC` for catalog). Authorization is evaluated at operation time, atomic with validation; `STAFF ship` needs both permission *and* `Order=PROCESSING`, `Customer cancel` needs `owner + eligible state + 20-min window`.

**Reason:** `phase-1.18.md §9`, `§34-37`, `§77`, `api-contract.md §18.1/§18.9`, `api-conventions.md §19.1/§19.3`.

**Status:** Accepted

---

### ADR/AUTHZ-007 — Default Deny & Explicit Public, Field/Caching Safety

**Decision:** `deny by default` with explicit `PUBLIC` classification for catalog; authorization before serialization — only permitted fields per actor (`reserved_quantity` not to `CUSTOMER`, `password` never). Private responses use private `Cache-Control`, not CDN-public; authorization decisions not cached across users; search/pagination over authorized dataset; service-to-service uses explicit service auth, not reused Admin credential.

**Reason:** `phase-1.18.md §37-38`, `§64-70`, `api-contract.md §18.11/§18.16`.

**Status:** Accepted

---

### ADR/AUTHZ-008 — Version 1 Roles Are Closed, Least Privilege Without Role Explosion

**Decision:** Version 1 roles remain CLOSED `CUSTOMER`/`STAFF`/`ADMIN`; no `*` wildcard as sole model; permission vocabulary uses smallest useful units (`products.view`/`products.manage` for catalog CRUD + images/variants — not stock, `inventory.view`/`inventory.manage` for stock-quantity adjustments — not catalog, `orders.view_operational`/`orders.accept`/`orders.process`/`orders.ready_for_pickup`/`orders.ship`/`orders.deliver` each distinct with state preconditions per `api-contract.md §18.6`, `requests/enquiries.view/manage`, `staff.approve/manage`) and RBAC + ownership + state baseline. Avoid super-roles; prefer explicit permissions; separation of duties `Staff → operational, Admin → staff approval`.

**Reason:** `phase-1.18.md §30-31`, `§80-82`, `api-contract.md §18.15`.

**Status:** Accepted

---

### ADR/API-END-001 — Canonical Customer Order Path Uses Self-Context (`/me/orders`)

**Decision:** Customer order collection/detail use `GET /me/orders` and `GET /me/orders/{order}` (self-context) with server-side ownership filtering, not `GET /orders` with client-supplied `?user_id` or duplicate `GET /my-orders`. Staff operational uses `GET /orders` / `GET /orders/{order}` with `orders.view_operational`. No duplicate forms (`/my-orders`, `/orders?mine=true`) preserved.

**Reason:** `phase-1.19.md §40`, `api-contract.md §19.1` `ORD-001/002` vs `ORD-005/006` — single canonical per actor prevents enumeration and aligns with `api-conventions.md §19.5` authorization-aware pagination.

**Status:** Accepted

---

### ADR/API-END-002 — Category Filtering Uses Product Collection (No Separate Nested Retrieval)

**Decision:** Category filtering uses `GET /api/v1/products?category={category}` (canonical, `category` query-convention) with combined `search`/`product_type`/`availability`/`price`/`sort`+`id ASC`+`meta.pagination`. `GET /api/v1/categories/{category}/products` is **REJECTED** for V1 as duplicate. `GET /products/{product}/images` also **REJECTED** (images embedded in product detail). Availability is embedded (`availability` + `stock_indicator`) not separate endpoint.

**Reason:** `phase-1.19.md §18-19`, `api-contract.md §19.5/19.7` — simplicity, discoverability, single filtering pipeline per `api-conventions.md §20.2`.

**Status:** Accepted

---

### ADR/API-END-003 — Order State Changes Use Controlled Actions, Not Unrestricted PATCH

**Decision:** Order status transitions use explicit `POST /orders/{order}/accept`, `/process`, `/ready-for-pickup`, `/ship`, `/deliver` (each distinct permission `orders.accept`/`process`/`ready_for_pickup`/`ship`/`deliver` + state precondition per `api-contract.md §18.6`), never generic `PATCH /orders/{order} {status: …}`. Customer cancellation uses `POST /me/orders/{order}/cancel` with `owns + eligible state + 20-min window` (not `DELETE /orders/{order}`).

**Reason:** `phase-1.19.md §39/44`, `api-contract.md §19.1` `ORD-007..011` — prevents bypassing `valid state + permission` and keeps `CANCEL` idempotency semantics distinct.

**Status:** Accepted

---

### ADR/API-END-004 — Anonymous Request/Enquiry Creation Does Not Imply Anonymous Retrieval

**Decision:** `POST /requests` (REQ-001) and `POST /enquiries` (ENQ-001) allow anonymous `User=none` + required contact. `GET /requests/{request}` is **not** automatically public; `GET /me/requests` (own) and `GET /requests` (staff operational) are separate. Anonymous retrieval, if ever needed, requires explicit secure access mechanism later — no predictable ID bearer.

**Reason:** `phase-1.19.md §51`, `api-contract.md §19.1` `REQ-001` vs `REQ-002..005` — protects private request/enquiry data and attachments (inherit parent).

**Status:** Accepted

---

### ADR/API-END-005 — Smallest Coherent Surface (69 Endpoints, Payment Group H)

**Decision:** V1 `70` total (`67` substantive + `3` Group H placeholders): `CAT 12` [`CAT-001..006` public + `CAT-007..012` management] + `AUTH 7` + `USER 3` + `CART 5` [`CART-001..005` inc. `CART-005` `merge` for guest-cart handoff] + `CHK 1` + `ORD 14` [`ORD-001..014` inc. `ORD-014` delivery-fee] + `REQ 7` + `ENQ 7` [`ENQ-001..007` inc. `ENQ-007` enquiry attachments] + `NOT 2` + `INV 3` + `ADM 6` + `PAY/WEBHOOK 3` placeholders (`PROPOSED*`). Master inventory table `api-contract.md §19/§19.15` snapshot remains `PROPOSED` pending final consolidated review, but `ORD-001..014` (`APPROVED` via `§24`/`§25`), `REQ-001..007` (`APPROVED` via `§26`), `ENQ-001..007` (`APPROVED` via `§27`) are individually `APPROVED` via their canonical contracts — `PROPOSED` now scopes only to the master-table snapshot, not to those domains. Excludes `wishlist`/`reviews`/`coupons`/`saved addresses`/`loyalty`/`driver tracking` per MVP discipline. IDs remain retired if removed.

**Reason:** `phase-1.19.md §121/124/126`, `api-contract.md §19.13` — smallest surface that fully supports anonymous browse, customer purchase (pickup/delivery), cancellation, made-to-order, staff operational, admin staff approval.

**Status:** Accepted

---

### ADR/API-CAT-001 — Public Catalog Requires No Authentication

**Decision:** Public catalog endpoints (`CAT-001..CAT-006`: products, product details, categories, category details, variants) require zero client authentication, cookies, or user tokens (`PUBLIC_READ`). Next.js SSR and Flutter mobile clients access identical public catalog resources anonymously.

**Reason:** `phases/phase-1.20.md §8`, `api-contract.md §21.1` — Customer product discovery, landing pages, and search engine crawling must never be gated behind an authentication barrier.

**Status:** Accepted

---

### ADR/API-CAT-002 — Product Type Is Closed Enum (`IN_STOCK`, `MADE_TO_ORDER`)

**Decision:** The `product_type` attribute accepts only the closed enum values `IN_STOCK` and `MADE_TO_ORDER`. `IN_STOCK` products permit Add-to-Cart and checkout when available; `MADE_TO_ORDER` products route to the Request Furniture workflow (`REQ-001`) and strictly reject cart/checkout attempts with `PRODUCT_NOT_PURCHASABLE` (422).

**Reason:** `phases/phase-1.20.md §5, §14, §23`, `AGENTS.md §4.1` — Made-to-order furniture requires custom manufacturing agreements and must not participate in standard physical inventory checkout.

**Status:** Accepted

---

### ADR/API-CAT-003 — Public Availability Is Informational and Decoupled from Internal Inventory

**Decision:** The catalog exposes coarse public signals (`availability`: `available|unavailable`, `stock_indicator`: `IN_STOCK|LOW_STOCK|MADE_TO_ORDER`). Internal unit quantities (`physical_quantity`, `reserved_quantity`), supplier notes, and warehouse logs are strictly prohibited from public serialization. Catalog availability is an informational read; final stock reservation and validation occur exclusively during checkout in a database transaction.

**Reason:** `phases/phase-1.20.md §15, §41, §42`, `api-contract.md §21.7` — Prevents competitor scraping, business data leakage, and race condition assumptions in clients.

**Status:** Accepted

---

### ADR/API-CAT-004 — Canonical Resource Identification (Public Slug and Machine ID)

**Decision:** Product and Category catalog entities support dual identification: a URL-safe, unique kebab-case `slug` for SEO canonical URLs and Next.js page routes, and an opaque, stable string `id` for machine operations and foreign keys. Product (`CAT-002`) and Category (`CAT-004`) detail endpoints resolve transparently by either `slug` or `id`. Product Variants (`CAT-005`, `CAT-006`) resolve strictly by Variant `id` (`var_...`) under parent `{product}`.

**Reason:** `phases/phase-1.20.md §33–§35`, `api-conventions.md §21.3` — Optimizes SEO search engine discovery while preserving internal identity stability across renames.

**Status:** Accepted

---

### ADR/API-CAT-005 — Canonical Category Filtering via Query Parameter (No Nested Routes)

**Decision:** Category product retrieval is exclusively handled via `GET /api/v1/products?category={category_slug|category_id}`. Nested collection route `GET /api/v1/categories/{category}/products` is rejected as redundant.

**Reason:** `phases/phase-1.20.md §32`, `docs/decisions.md ADR/API-END-002` — Consolidates all search, multi-faceted filtering, sorting, and pagination logic into a single high-performance pipeline (`CAT-001`).

**Status:** Accepted

---

### ADR/API-CAT-006 — Product Summary vs Full Detail Representations

**Decision:** Product collection endpoints (`CAT-001`) return a lightweight **Product Summary** representation (id, name, slug, product_type, price, category summary, primary thumbnail image, availability badges). Product detail endpoints (`CAT-002`) return the **Full Product Detail** (summary fields plus full description, sorted gallery images array, full variants list, timestamps).

**Reason:** `phases/phase-1.20.md §80–§82`, `api-contract.md §21.8` — Minimizes bandwidth and Edge caching payload size on high-traffic listing pages while providing full data for product detail views and SSR metadata.

**Status:** Accepted

---

### ADR/API-CAT-007 — Strict Variant-to-Product Ownership Constraint

**Decision:** Product variants belong strictly to a single parent product (`Product 1 ──* ProductVariant`). A variant cannot exist independently or be combined with a mismatched product in cart or checkout operations (`VAR-OWN-001`). `CAT-006` validates parent-child ownership during resolution.

**Reason:** `phases/phase-1.20.md §29, §55`, `api-contract.md §21.5` — Guarantees data integrity and prevents variant price/inventory hijacking across different products.

**Status:** Accepted

---

### ADR/API-CAT-008 — Deterministic Query Execution Pipeline and Tie-Breaking Sort

**Decision:** Collection queries strictly execute in the order `search → filter → sort → paginate`. Sort fields are restricted to a strict server-side allow-list (`created_at`, `price`, `name`). Queries sorting by non-unique fields append `id ASC` as a deterministic tie-breaker to ensure stable pagination. Default sort is catalog display order (`created_at DESC, id ASC`).

**Reason:** `phases/phase-1.20.md §18–§19, §70`, `api-conventions.md §21.1–§21.2` — Eliminates pagination drifting/duplicate items between pages and prevents arbitrary SQL column injection.

**Status:** Accepted

---

### ADR/API-CART-001 — Cart Is Customer-Owned and Session-Persistent

**Decision:** The authoritative shopping cart belongs strictly to the authenticated customer account (`/api/v1/me/cart`) and persists across devices and sessions. Next.js and Flutter access the identical server-side cart.

**Reason:** `phases/phase-1.21.md §9, §54, §55`, `AGENTS.md §3` — Ensures a unified multi-platform customer shopping experience without fractured local-storage state.

**Status:** Accepted

---

### ADR/API-CART-002 — Cart Does Not Reserve Inventory

**Decision:** Adding items to a cart (`CART-002`) or updating quantities (`CART-003`) does not reserve physical stock or increment `reserved_quantity`. Inventory reservation occurs strictly during the atomic Checkout transaction (`CHK-001`).

**Reason:** `phases/phase-1.21.md §31, §32`, `docs/domain/business-rules.md §4 #4` — Prevents inventory lockup caused by abandoned carts while maintaining concurrency safety.

**Status:** Accepted

---

### ADR/API-CART-003 — Cart Pricing Is Informational and Server-Calculated

**Decision:** All cart monetary values (`unit_price`, `line_total`, `subtotal`) are server-calculated display numbers representing current catalog prices in minor units (`{amount, currency}`). Clients cannot submit prices or subtotals. Authoritative transaction pricing is recomputed at Checkout.

**Reason:** `phases/phase-1.21.md §18, §19, §59`, `docs/api/api-conventions.md §22.2` — Prevents client-side price tampering and guarantees financial calculation integrity.

**Status:** Accepted

---

### ADR/API-CART-004 — Made-to-Order Products Strictly Excluded from Cart

**Decision:** Only `IN_STOCK` products may be added to a cart. `MADE_TO_ORDER` products route to the furniture request workflow (`REQ-001`) and are strictly rejected by the Cart API with `PRODUCT_NOT_PURCHASABLE` (422).

**Reason:** `phases/phase-1.21.md §22, §46`, `AGENTS.md §4.1` — Custom manufacturing requests require separate administrative review and commercial terms before order creation.

**Status:** Accepted

---

### ADR/API-CART-005 — Cart Item Duplicate Addition Merges Quantities

**Decision:** Adding an item with an existing `(product_id, variant_id)` identity in the cart merges the lines and increments the quantity (`existing_quantity + added_quantity`, clamped to the maximum limit of 100), rather than creating duplicate lines or rejecting the request.

**Reason:** `phases/phase-1.21.md §28, §29`, `docs/api/api-conventions.md §22.1` — Provides a seamless ecommerce user experience across web and mobile retries.

**Status:** Accepted

---

### ADR/API-CART-006 — Stale Cart Items Are Preserved and Flagged as Non-Purchasable

**Decision:** If an item in a customer's cart becomes inactive or out of stock, the server retains the cart line and sets `availability: "unavailable"` and `is_purchasable: false`. The customer sees an explicit warning in the UI, and Checkout rejects the cart until the stale item is removed or adjusted.

**Reason:** `phases/phase-1.21.md §41, §71`, `docs/api/api-conventions.md §22.4` — Avoids silent cart modifications and clearly communicates product availability changes to the customer.

**Status:** Accepted

---

### ADR/API-CART-007 — Guest Cart Token: Client-Type-Split Transport

**Decision:** The guest cart token is a bearer credential **scoped strictly to its bound guest cart** (cryptographically secure, high-entropy `UUIDv4` CSPRNG ≥122 bits, not `UUIDv1`/sequential/counter, unpredictable, not guessable) — it authorizes read/write to that guest cart only (anonymous cart request has no authenticated principal, so validating this token necessarily authorizes access to its bound cart); it **never authorizes account operations** and **never authorizes merge by itself** (merge requires authenticated principal + valid guest token + server-side ownership check). Its transport is split by client type to prevent JavaScript exposure in browsers:
- **Browser (Next.js):** Token issued exclusively as `Set-Cookie: guest_cart_id=<token>; HttpOnly; Secure; SameSite=Strict`. The `X-Guest-Cart-Id` response header is **never** emitted for browser requests, as it would be readable by page JavaScript (including third-party scripts) and defeat the `HttpOnly` protection.
- **Non-browser (Flutter):** Token issued exclusively in the `X-Guest-Cart-Id` response header (no `Set-Cookie`). Flutter must store it in secure device storage and send it as a request header — never in a JSON body field (request log exposure risk).

The two paths are mutually exclusive per request. The token is permanently retired server-side upon merge (`AUTH-002` login or `CART-005`) and may not be recycled or reused after retirement.

**Reason:** `phases/phase-1.21.md §10, §11`, `api-conventions.md §22.6` — A browser-readable response header carrying the same token as an `HttpOnly` cookie allows any JS running on the page to steal the credential and impersonate the guest cart from a different client. Splitting by client type eliminates this exposure without sacrificing Flutter usability.

**Status:** Accepted

---

### ADR/API-CART-008 — Quantity Limits and Validation Boundary

**Decision:** Cart item quantities are strictly constrained to positive integers between `1` and `100` per line. Setting `quantity: 0` is rejected (removal requires `DELETE /me/cart/items/{item}`). Active cart item lists are not paginated.

**Reason:** `phases/phase-1.21.md §26, §27, §78`, `docs/api/api-resources.md §4` — Protects database resources, prevents accidental massive orders, and simplifies cart UI rendering.

**Status:** Accepted

---

### ADR/API-CHK-001 — Checkout Requires Authentication

**Decision:** `POST /api/v1/checkout` (`CHK-001`) requires an authenticated `CUSTOMER`. Anonymous checkout is rejected with `401 AUTHENTICATION_REQUIRED` (alias `CHECKOUT_REQUIRES_AUTHENTICATION` — identical 401). Guest checkout is explicitly prohibited; `X-Guest-Cart-Id` guest token alone does not authorize checkout — a guest must register/login first, after which the guest cart is merged and checkout proceeds as authenticated.

**Reason:** `phases/phase-1.22.md §45`, `docs/domain/business-rules.md §4a #1`, `AGENTS.md §3` — checkout creates a customer-owned Order (requires ownership identity) and involves payment; anonymous Order creation would bypass account privacy and ownership invariants.

**Status:** Accepted

---

### ADR/API-CHK-002 — Checkout Uses Customer's Own Active Cart

**Decision:** The server derives the customer's **own active Cart** from the authenticated principal (`/me/cart` self-context). The client does not supply `cart_id` or `user_id` to select a cart. `POST /checkout` with a forged `cart_id` or `user_id` is rejected. Ownership is `cart.owner == authenticated principal`; holder mismatch yields 404 masked per enumeration protection.

**Reason:** `phases/phase-1.22.md §28-29`, `api-contract.md §23.3/§23.13` — eliminates IDOR (`Customer A → Customer B cart`) and cart-substitution attacks and simplifies customer UX (no cart selection).

**Status:** Accepted

---

### ADR/API-CHK-003 — Checkout Is a Dedicated Business Workflow

**Decision:** Checkout is neither `PATCH /me/cart` (cart field edit) nor `POST /orders` with client-controlled `total`/`status`/`reference`. It is a server-controlled transaction workflow: `validate cart → revalidate products/variants → revalidate inventory atomically → recalculate authoritative prices → decide fulfillment → snapshot address → create Order → hand off to payment (Group H)`. Direct `POST /orders` with client totals/statuses is prohibited.

**Reason:** `phases/phase-1.22.md §10-11`, `api-contract.md §23.1` — prevents bypassing inventory checks, price authority, fulfillment validation, and transaction boundaries.

**Status:** Accepted

---

### ADR/API-CHK-004 — Checkout Revalidates Inventory Authoritatively

**Decision:** Checkout re-reads authoritative product/variant state and stock at transaction time: `product exists→active→is_published→IN_STOCK→purchasable`, `variant exists→belongs to product→active→purchasable`, `requested quantity ≤ available_quantity`. Cart `In Stock` display is informational only. Inventory check and state-changing operation are atomic under concurrency (transaction/locking, `SELECT then UPDATE` without protection prohibited).

**Reason:** `phases/phase-1.22.md §34-38`, `AGENTS.md §12` (INV-003), `api-contract.md §23.9` — prevents overselling under concurrent checkout (race `A sees 1, B buys, A fails safely`).

**Status:** Accepted

---

### ADR/API-CHK-005 — Checkout Recalculates Authoritative Pricing

**Decision:** Backend recalculates **all** money at checkout from current catalog state (Model B — fee-after-order): `current unit prices → line totals → subtotal (authoritative at checkout, stored on Order PENDING_PAYMENT with delivery_fee pending for DELIVERY / 0 for PICKUP) → Staff/Admin sets authoritative delivery_fee (server/staff-controlled) → final total (subtotal + delivery_fee) authoritative, stored on Order` as integer minor units `{amount,currency:"TZS"}`. Frontend cached prices, `total`, `subtotal`, `discount`, `currency` overrides are rejected. Price change between cart view and checkout uses current authoritative price with price-transparency disclosure before final payment.

**Reason:** `phases/phase-1.22.md §39-42`, `AGENTS.md §13`, `api-contract.md §23.7` — client totals are never authoritative; prevents price manipulation and floating-point errors.

**Status:** Accepted

---

### ADR/API-CHK-006 — Customer Cannot Set Delivery Fee

**Decision:** Delivery fee is **variable, location-based, and added by Staff/Admin**. Customer may only choose `DELIVERY` (and supply `delivery_address`); Staff/Admin determine the appropriate fee; backend stores the authoritative `delivery_fee`. Client-supplied `{"delivery_fee": …}` is never authoritative and is rejected. Flat `TZS 20,000` rate is superseded and not reintroduced silently.

**Reason:** `phases/phase-1.22.md §17-18, §21`, `docs/domain/business-rules.md §4a #10` — preserves business pricing authority and prevents delivery-fee manipulation.

**Status:** Accepted

---

### ADR/API-CHK-007 — Checkout Requires Idempotency

**Decision:** `POST /checkout` is `IDEMPOTENCY_REQUIRED` via `Idempotency-Key` header. Same key + same logical input replays original `201` with same `order_reference` (no duplicate Order); same key + materially different input (`fulfillment_type`/`delivery_address` differ) yields `409 CONFLICT`/`DUPLICATE_OPERATION`. Client timeout does not prove failure — retry with same key reconciles. Durable `key→result` storage with unique constraint is required; exact header/storage details finalized in later idempotency phase but requirement is normative now.

**Reason:** `phases/phase-1.22.md §49-53`, `api-contract.md §23.8` — prevents duplicate Orders/reservations/payments on network retry.

**Status:** Accepted

---

### ADR/API-CHK-008 — MADE_TO_ORDER Products Cannot Enter Normal Checkout

**Decision:** `MADE_TO_ORDER` products are **never purchasable via normal checkout** — they belong to the Request workflow (`REQ-001`). Any cart containing a `MADE_TO_ORDER` item is rejected at checkout with `PRODUCT_NOT_PURCHASABLE` (422) even if display price exists and even if item somehow entered the cart. Cart admission already rejects `MADE_TO_ORDER`; checkout enforces second line of defense.

**Reason:** `phases/phase-1.22.md §33`, `AGENTS.md §4.1`, `docs/domain/business-rules.md §4a #8` — made-to-order requests require custom manufacturing agreements and are not standard inventory purchases.

**Status:** Accepted

---

### ADR/API-CHK-009 — Delivery Fee Timing (Model B: Fee-After-Order)

**Decision:** Version 1 adopts **Model B — fee-after-order**:

```
Customer POST /checkout (fulfillment_type=DELIVERY + delivery_address)
  → Order created PENDING_PAYMENT (delivery_address snapshotted, subtotal authoritative, delivery_fee pending)
  → Staff/Admin reviews delivery and adds authoritative delivery_fee
  → final total (subtotal + delivery_fee) becomes authoritative
  → customer sees final amount
  → payment (Group H) proceeds on final authoritative total
```

Payment must operate on the **final authoritative amount stored by the Order**; `customer pays before fee known → staff changes total` mismatch is forbidden. `PICKUP` remains `delivery_fee {amount:0}`. No new order status value is introduced — fee finalization occurs within `PENDING_PAYMENT` before `PAID`. Alternative Model A (synchronous fee during checkout) was not assumed due to current staff-added variable fee process.

**Reason:** `phases/phase-1.22.md §19-20, §82-85`, `api-contract.md §23.6`, `docs/domain/business-rules.md §4a #11` — aligns with stated business process `Staff/Admin add the delivery fee directly` and avoids customer-controlled pricing while keeping payment consistent with stored Order total. Decision explicitly documented rather than guessed; affects Order contract, Payment Group H, Order statuses, and UI workflows.

**Status:** Accepted

---

### ADR/API-ORD-001 — Orders Are Customer-Owned Historical Records

**Decision:** Order is a first-class `v1` resource (`Order`) created only via `POST /api/v1/checkout` (`CHK-001`). Once created it becomes a historical business record: `id` + `order_reference OD-*****` (unique, human-readable, server-generated) + `customer owner` + `items historical snapshots` + `subtotal authoritative; delivery_fee/total provisional for `DELIVERY` (delivery_fee `null`/`PENDING`, total = `subtotal` until `ORD-014` finalizes to `FINALIZED`, then `total` final and payable) and final at creation for `PICKUP` (`delivery_fee 0`/`FINALIZED`, `total` final immediately, `payment` still `null` until `PAY-001`)` + `fulfillment + delivery_address snapshot` + `status + status_history` + `payment relationship` (payment `null` while `PENDING_PAYMENT` until `PAY-001`). Not a live `Product` copy. Model B provisional totals must not be treated as payable amount before `ORD-014`.

**Reason:** `phases/phase-1.23.md §8-13, §21-23`, `AGENTS.md §11` (historical orders preserve price snapshot) — preserves auditability and prevents catalog mutation from rewriting history.

**Status:** Accepted

---

### ADR/API-ORD-002 — Customer Order Ownership Cannot Be Client-Supplied

**Decision:** `Order.customer_id = authenticated principal` from checkout; `{"customer_id":"..."}` / `?customer_id=another` from client is rejected `422 INVALID_VALUE`; `Customer A → Customer B` order/tracking fails `404 ORDER_NOT_FOUND` (masked per `§15.8`). Staff operational access is separate (`orders.view_operational`) and does not own customer accounts.

**Reason:** `phases/phase-1.23.md §10-11`, `api-contract.md §24.3` — prevents ownership tampering and enumeration.

**Status:** Accepted

---

### ADR/API-ORD-003 — Order Items Preserve Historical Purchase Information

**Decision:** Each `OrderItem` stores at creation `product_id`, `variant_id`, `sku`, `name`, `variant_name`, `unit_price` (historical `{amount,currency}`), `quantity`, `line_total` — all snapshots. Even if `Sofa 1,000,000 → 1,200,000` or product deactivated, old Order still shows `1,000,000`. Price/quantity are not `PATCH`able via `PATCH /orders/{order}/items/{item}`; correction is controlled admin workflow (audit) only.

**Reason:** `phases/phase-1.23.md §21-26`, `api-contract.md §24.8` — ensures historical financial integrity and display after catalog changes.

**Status:** Accepted

---

### ADR/API-ORD-004 — Order Cancellation Has a 20-Minute Customer Window

**Decision:** `POST /me/orders/{order}/cancel` (`ORD-004`, `Idempotency-Key` required, `409` on race) requires `owns + state == PENDING_PAYMENT (cancellable) + within 20 minutes from authoritative `order.created_at` backend time`. Server computes `elapsed = now - created_at`; `cancelled_at` from client rejected. Success → `CANCELLED`; after window or on `PAID/COMPLETED` → `422/409 ORDER_NOT_CANCELLABLE`. Staff cannot use customer cancel; admin cancel is separate explicit workflow (audit). `CANCEL` ≠ `DELETE` — order remains readable.

**Reason:** `phases/phase-1.23.md §50-55`, `AGENTS.md §8`/`§17` time authority, `api-contract.md §24.17` — prevents time tampering and late cancellation.

**Status:** Accepted

---

### ADR/API-ORD-005 — Order Status Transitions Are Controlled Actions

**Decision:** Statuses `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED` are CLOSED. No `PATCH {status: "COMPLETED"}`; only `POST .../accept|process|ready-for-pickup|ship|deliver|complete|cancel` with `actor+permission+current_state+fulfillment+preconditions` validated atomically inside transaction. Example `PENDING_PAYMENT→PAID` (System, `delivery_fee FINALIZED`), `PAID→ACCEPTED` (Staff), `ACCEPTED→PROCESSING`, `PROCESSING→READY_FOR_PICKUP` (PICKUP) / `SHIPPED` (DELIVERY), `SHIPPED→DELIVERED`, `→COMPLETED`. Invalid `PAID→COMPLETED`, `DELIVERED→SHIPPED`, `PICKUP→SHIPPED` rejected `409 INVALID_ORDER_TRANSITION`.

**Reason:** `phases/phase-1.23.md §38-49, §126-128`, `api-contract.md §24.13` — prevents arbitrary state jumps; idempotency `POST` with same `Idempotency-Key` replays original.

**Status:** Accepted

---

### ADR/API-ORD-006 — Delivery Fee Is Not Customer-Controlled

**Decision:** Variable location-based `delivery_fee` is Staff/Admin (`null→{amount,currency}`, `PENDING→FINALIZED` before `PAID` per Model B via `ORD-014 POST /orders/{order}/delivery-fee`). Customer `{"delivery_fee":0}` rejected; historical `delivery_fee` immutable after `FINALIZED` (especially after `PAID`). `PENDING` blocks `PAY-001` `409 DELIVERY_FEE_PENDING`; provisional `total == subtotal` not final. Flat `20,000` superseded. `ORD-014` requires `orders.set_delivery_fee`, `Idempotency-Key` Required, concurrency Critical, audited `actor,time,old,new,reason`.

**Reason:** `phases/phase-1.23.md §29-31`, `api-contract.md §24.10-24.11 (ORD-014)`, `business-rules.md §5/§6` — preserves fee authority and financial finality before payment.

**Status:** Accepted

---

### ADR/API-ORD-007 — Pickup and Delivery Follow Distinct Order Paths

**Decision:** `PICKUP`: `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` (no `SHIPPED/DELIVERED`). `DELIVERY`: `PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED`. `delivery_address: null` for `PICKUP`, snapshot for `DELIVERY`. Tracking milestones filtered by fulfillment; operational fulfillment data uses same branches.

**Reason:** `phases/phase-1.23.md §32-47`, `api-contract.md §24.13` — distinct fulfillment avoids invalid cross-branch transitions.

**Status:** Accepted

---

### ADR/API-ORD-008 — Customer Order Data Is Private

**Decision:** All Order reads (`ORD-001`..`ORD-004` customer, `ORD-005`..`ORD-012` operational via `orders.view_operational`) are `Cache-Control: private, no-store`; no CDN public caching; authorization before serialization; `404` masking prevents enumeration; only permitted fields per actor (`internal notes` not to customer, `password/payment secret` never). Pagination `page/per_page` + `meta.pagination` stable `created_at DESC, id ASC`.

**Reason:** `phases/phase-1.23.md §139-141`, `api-contract.md §24.23` — protects PII/financial data and prevents leakage.

**Status:** Accepted

---

### ADR/API-ORD-009 — Payment Is Related to Order but Defined in Group H

**Decision:** Order exposes limited `payment: {payment_status, amount:{amount,currency}} | null` (null while `delivery_fee PENDING`). `Order PAID` vs `Payment PAID` are distinct state machines; `PAY-001/002` + `WEBHOOK-001` remain `Group H` generic placeholders (`PROPOSED*`), no provider SDK/credentials/webhooks in Order contract. `Payment amount == final Order total (subtotal+delivery_fee when FINALIZED)` is the reconciliation invariant.

**Reason:** `phases/phase-1.23.md §66-71, §132-135`, `api-contract.md §24.11` — keeps payment provider specifics separate while ensuring financial consistency.

**Status:** Accepted

---

### ADR/API-ORD-010 — Delivery Fee Finalization Endpoint (Model B Gate)

**Decision:** V1 adds dedicated `ORD-014 POST /api/v1/orders/{order}/delivery-fee` (Staff/Admin, `orders.set_delivery_fee` + `PENDING_PAYMENT`+`DELIVERY`+`PENDING`) — the only V1 gate that transitions `delivery_fee null→{amount,currency}` and `delivery_fee_status PENDING→FINALIZED` before `PAY-001`. Request `{"delivery_fee":{"amount":int minor units,currency:"TZS"},"reason":"..."}` (currency must be `TZS`, amount `>=0`, unknown fields `422`); Response `200` updated Order (`delivery_fee`, `delivery_fee_status: FINALIZED`, `total` final, `payment` now eligible). Single finalization; `409` if already `FINALIZED` or not `PENDING_PAYMENT`/`DELIVERY`; `Idempotency-Key` Required (same key replays, different amount same key `409 DUPLICATE_OPERATION`); concurrency Critical (race with `PAY-001`); audited.

**Reason:** `phases/phase-1.23.md §31, §84`, `api-contract.md §24.10 (ORD-014)` — without a dedicated fee-assignment path, the `DELIVERY Orders block payment until FINALIZED` gate in `§24.5/§24.13` would be unimplementable; `ORD-014` closes the Model B loop with explicit permission, shape, idempotency and concurrency.

**Status:** Accepted

---

### ADR/API-FUL-001 — Tracking Is Read-Only for Customers

**Decision:** Customer tracking via `GET /me/orders/{order}/tracking` (`ORD-003` customer, `ORD-012` staff operational) is `GET` only. No `POST/PATCH/DELETE /tracking` for customers. Timeline is `order_status_history`-derived, `chronological ASC, id ASC` `ISO8601 Z`, private `Cache-Control: private, no-store` (not CDN). `Customer A → Customer B tracking` fails `404 ORDER_NOT_FOUND` masked; `GET /me/orders/{order}/tracking` requires `AUTHENTICATED_OWNER` owns order.

**Reason:** `phases/phase-1.24.md §14-21, §31`, `api-contract.md §25.6/§25.8` — read-only view preserves audit integrity; derived state prevents drift.

**Status:** Accepted

---

### ADR/API-FUL-002 — Fulfillment Actions Are Controlled Staff/Admin Operations

**Decision:** Physical fulfillment branch (`ORD-009 ready-for-pickup for PICKUP`, `ORD-010 ship for DELIVERY`, `ORD-011 deliver for DELIVERY`, `ORD-013 complete`) are `POST` `Idempotency-Key` **Required**, concurrency **Critical**, fulfillment-type-aware (`PICKUP→SHIPPED` rejected `409 FULFILLMENT_ACTION_NOT_ALLOWED`), state-aware (`PROCESSING→SHIPPED` only from `PROCESSING`). `PATCH {status:"SHIPPED"}` rejected. All require `actor+permission+current_state+fulfillment` atomically.

**Reason:** `phases/phase-1.24.md §23-27, §65-67`, `api-contract.md §25.10` — controlled actions prevent arbitrary state jumps; idempotency prevents duplicate `SHIPPED` events on retry.

**Status:** Accepted

---

### ADR/API-FUL-003 — Pickup and Delivery Use Distinct Fulfillment Paths

**Decision:** `PICKUP: PROCESSING→READY_FOR_PICKUP→COMPLETED` (single pickup location V1, `ORD-009` then `ORD-013` explicit) vs `DELIVERY: PROCESSING→SHIPPED→DELIVERED→COMPLETED` (`ORD-010` then `ORD-011` then `ORD-013`). Cross-branch `PICKUP→SHIPPED` / `DELIVERY→READY_FOR_PICKUP` rejected `409`. Tracking timeline filtered by `fulfillment_type` (pickup never shows `SHIPPED`).

**Reason:** `phases/phase-1.24.md §10-13, §91`, `api-contract.md §25.3-25.5` — distinct operational paths avoid forcing one flow onto both.

**Status:** Accepted

---

### ADR/API-FUL-004 — Tracking Is Derived From Authoritative Order/Fulfillment State

**Decision:** `tracking.current_status == Order.status` (`§24.13`); `timeline` is filtered `order_status_history` (`status, occurred_at, id, label`) chronological `ASC`. No independent tracking state machine that can drift (`Order=PROCESSING` with `Tracking=DELIVERED` impossible). Not every internal event is shown; `delivery_fee FINALIZED` is financial flag, not timeline milestone.

**Reason:** `phases/phase-1.24.md §19-22`, `api-contract.md §25.8-25.9` — single source of truth.

**Status:** Accepted

---

### ADR/API-FUL-005 — Fulfillment History Is Not Customer-Mutable (Append-Only)

**Decision:** `order_status_history` is `append-only`; customer cannot `POST/PATCH/DELETE` history, staff cannot `rewrite past`; timeline `TimelineEvent {id: evt_..., status, occurred_at, label}` stable `id` from history `id`, `occurred_at` `ISO8601 Z`, `append-only` (retry with same `Idempotency-Key` replays original `SHIPPED`, not second event). Correction requires explicit admin workflow (audit `actor, order, old, new, reason, occurred_at`).

**Reason:** `phases/phase-1.24.md §43-44`, `api-contract.md §25.14` — preserves audit integrity.

**Status:** Accepted

---

### ADR/API-FUL-006 — No Live GPS Tracking in Version 1

**Decision:** V1 tracking = fulfillment/order milestones (`PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP, SHIPPED, DELIVERED, COMPLETED`) via `REST GET polling` + client refresh (`GET /me/orders/{order}/tracking` on open / pull-to-refresh / visibility-change). No `WebSockets/SSE/push`, no `real-time vehicle location`, no `tracking_number/carrier/tracking_url` (no external courier integration — not invented), no `assigned_staff/assigned_delivery` unless business needs.

**Reason:** `phases/phase-1.24.md §52-59, §100`, `api-contract.md §25.17/§25.27` — small-scale commerce does not require live logistics; polling is starting architecture.

**Status:** Accepted

---

### ADR/API-REQ-001 — Anonymous Made-to-Order Requests (Public Intake, Required Contact)

**Decision:** `POST /api/v1/requests` (`REQ-001`) accepts anonymous `User=none` + required contact (`name` + at least one of `phone`/`email`) as well as authenticated `Customer` (where `Request.user_id = authenticated principal` server-derived). `user_id = null` for anonymous is valid; `{"user_id":"..."}` from client rejected `422`. Even when authenticated, `name`/`phone`/`email` remain required in payload to create a self-contained historical contact snapshot (future profile change does not mutate past request).

**Reason:** `phases/phase-1.25.md §12-16`, `api-contract.md §26.2/§26.5` — visitor must move from `MADE_TO_ORDER` Product page → Request without forced registration; self-contained snapshot ensures staff handling is reliable.

**Status:** Accepted

---

### ADR/API-REQ-002 — Authenticated Request Ownership (Server-Derived)

**Decision:** Authenticated requests belong to the submitting customer (`Customer → owns Request`). `GET /me/requests` (`REQ-002`) and `GET /me/requests/{request}` (`REQ-003`) are `AUTHENTICATED_OWNER` ownership-scoped; `Customer A → Customer B request` fails `404 REQUEST_NOT_FOUND` masked. Anonymous requests have `no authenticated owner` and require a separate secure access mechanism if later retrieval is needed — not email equality.

**Reason:** `phases/phase-1.25.md §18-20`, `api-contract.md §26.12/§26.22` — preserves privacy and prevents enumeration; `email` is contact, not authentication.

**Status:** Accepted

---

### ADR/API-REQ-003 — Request Is Not an Order (Separate Workflow)

**Decision:** Submitting a made-to-order request does not create an `Order`, does not enter `Cart → Checkout → Payment`, does not reserve inventory, does not guarantee price/production/delivery/completion. `order_id`/`payment_*`/`delivery_fee` from client rejected. Request remains inquiry/intake for furniture the business may produce upon request.

**Reason:** `phases/phase-1.25.md §9, §47-48, §51-53`, `api-contract.md §26.11` — keeps made-to-order commerce path distinct from normal purchasing.

**Status:** Accepted

---

### ADR/API-REQ-004 — Request Does Not Reserve Inventory (No Stock Effect)

**Decision:** Made-to-order request submission has zero inventory effect — no `physical_quantity`/`reserved_quantity`/`available_quantity` change. Inventory remains backend-controlled and only checkout/orders affect stock.

**Reason:** `phases/phase-1.25.md §52`, `api-contract.md §26.11` — a request is lead, not purchase.

**Status:** Accepted

---

### ADR/API-REQ-005 — Optional Attachments, Inline Multipart Preferred, Private

**Decision:** Request attachments are **optional** (`0` or `1` on creation in V1). Preferred transport is `multipart/form-data` inline with `REQ-001` creation (field `attachment`). Separate `POST /requests/{request}/attachments` (`REQ-007`) requires scoped server-issued upload token (single-use/time-limited) + parent ownership; predictable request `id` alone insufficient. Validation: `size <=5 MB`, types `image/jpeg|png|webp|application/pdf`, actual content signature (client MIME not trusted). Access inherits parent (`Customer own → own attachment`, `Staff → operational`, `Anonymous → scoped token only`); no permanent public URLs; internal storage keys never exposed.

**Reason:** `phases/phase-1.25.md §37-42, §81`, `api-contract.md §26.8` — supports reference furniture/sketch while keeping private data protected and storage provider deferred.

**Status:** Accepted

---

### ADR/API-REQ-006 — Anonymous Retrieval Not Supported Without Secure Mechanism

**Decision:** V1 does **not** support `GET /requests/{request}` public for anonymous merely because creation is public. Predictable `id` is not bearer credential. Anonymous retrieval, if ever needed, requires explicit secure access mechanism (one-time link, verified contact, secure token) — out of scope. `GET /requests` collection is never publicly searchable; only `POST /requests` is public.

**Reason:** `phases/phase-1.25.md §19, §65`, `api-contract.md §26.13` — prevents enumeration and private data exposure.

**Status:** Accepted

---

### ADR/API-REQ-007 — Payment Separation (Request Does Not Initiate Payment)

**Decision:** Requests do not directly initiate payment; `payment_status`, `payment_id`, `payment_amount`, `card`, `mobile_money` fields are never accepted at `REQ-001`. Payment begins only through a later separately approved Order/payment workflow (Group H). No `quoted_price` produced at submission.

**Reason:** `phases/phase-1.25.md §92, §49-51`, `api-contract.md §26.11` — payment boundary stays with Order; request is intake.

**Status:** Accepted

---

### ADR/API-REQ-008 — Dimensions Structured with Canonical cm Unit

**Decision:** When `dimensions` supplied, it must be structured object `{length, width, height, unit:"cm"}` with allow-listed keys only and `unit` exactly `"cm"` CLOSED. Each dimension `>0` `<=10000`. Arbitrary keys rejected. Free-text dimensions not accepted.

**Reason:** `phases/phase-1.25.md §32-33`, `api-contract.md §26.6` — avoids furniture-configuration DSL while giving measurable intent with one canonical unit.

**Status:** Accepted

---

### ADR/API-REQ-009 — Material & Color Free Text, Not Closed Enum

**Decision:** `material` and `color` are free text (max 500/200) rather than closed enums. Incomplete closed enum would block customers; small-business flexibility requires strings. V1 status enum remains CLOSED but `material`/`color` intentionally open.

**Reason:** `phases/phase-1.25.md §34-35`, `api-contract.md §26.7` — avoids premature enum restriction.

**Status:** Accepted

---

### ADR/API-REQ-010 — Minimal Request Status (SUBMITTED → IN_REVIEW → CLOSED)

**Decision:** V1 defines minimal `request_status` CLOSED enum `SUBMITTED` (default at creation), `IN_REVIEW`, `CLOSED` (terminal). Expected `SUBMITTED→IN_REVIEW→CLOSED`; direct `SUBMITTED→CLOSED` permitted; `CLOSED` terminal. `QUOTED`/`APPROVED`/`REJECTED`/`PRODUCING` and similar not in V1. Staff `PATCH REQ-006` validates transition; customer cannot set `status`.

**Reason:** `phases/phase-1.25.md §43-44, §99`, `api-contract.md §26.10` — small-business queue needs operational state without over-engineered CRM; CLOSED prevents arbitrary states.

**Status:** Accepted

---

### ADR/API-REQ-011 — Product Reference Policy: Optional product_id for V1

**Decision:** For V1, `product_id` on `POST /api/v1/requests` (`REQ-001`) is **OPTIONAL (nullable)** — resolves `phases/phase-1.25.md §26-27` which left `product_id = null` as “only if business agrees” and `§27` “only if workflow” ambiguity. Both modes are approved: `product_id` referencing an existing `MADE_TO_ORDER` product, and `product_id = null` / omitted for a general/custom furniture request ("Can you make something like this?"). It is **not** Required (which would force all requests to originate from catalog) and **not** Absent (which would forbid catalog linking). Example including `product_id` in `§24` is illustrative, not a requiredness rule; nullable example is equally valid. Mirrored in `api-contract.md §26.3-26.4`, `api-resources.md §5.1/§5.2/§10.6`, `api-conventions.md §26.3`, `business-rules.md §9 #2`.

**Reason:** `phases/phase-1.25.md §26-27`, `AGENTS.md §4.2` general-enquiry separation — small business needs both catalog-linked requests and free-form custom requests; forcing one mode would block legitimate use. Choosing `Optional` keeps contract single and avoids future breaking change.

**Status:** Accepted

---

### ADR/API-ENQ-001 — Anonymous General Enquiry Submission (Public Intake, Required Contact)

**Decision:** `POST /api/v1/enquiries` (`ENQ-001`) accepts **anonymous `User=none` + required contact** (`name` required + at least one of `phone`/`email`, per `phases/phase-1.26.md §19/§97`) as well as **authenticated `Customer`** (where `Enquiry.user_id = authenticated principal` server-derived, `name`/`phone`/`email` Optional/derived with snapshot where supplied). `user_id = null` for anonymous is valid; `{"user_id":"..."}` from client rejected `422`. Anonymous enquiries are private intake with no automatic retrieval bearer.

**Reason:** `phases/phase-1.26.md §11-13, §19-21`, `api-contract.md §27.2/§27.3` — visitor must submit general business question without forced registration; self-contained contact snapshot ensures staff can respond; Optional/derived for authenticated keeps frictionless UX without trusting client ownership.

**Status:** Accepted

---

### ADR/API-ENQ-002 — Authenticated Enquiry Ownership (Server-Derived)

**Decision:** Authenticated enquiries belong to the submitting customer (`Customer → owns Enquiry`, `Enquiry.user_id = authenticated principal` derived). `GET /me/enquiries` (`ENQ-002`) and `GET /me/enquiries/{enquiry}` (`ENQ-003`) are `AUTHENTICATED_OWNER` ownership-scoped; `Customer A → Customer B enquiry` fails `404 ENQUIRY_NOT_FOUND` masked per `§15.8`. Anonymous enquiries have `no authenticated owner` and require a separate secure access mechanism if later retrieval is needed — not email equality or predictable ID.

**Reason:** `phases/phase-1.26.md §14, §52-53`, `api-contract.md §27.12/§27.22` — preserves privacy and prevents enumeration; `email` is contact, not authentication; `user_id` from body is never authoritative.

**Status:** Accepted

---

### ADR/API-ENQ-003 — Enquiries Are Separate From Made-to-Order Requests

**Decision:** General Enquiry (`ENQ-001`, `message` + `subject` + optional `product_id`/`order_id`) and Made-to-Order Request (`REQ-001`, `dimensions`/`material`/`color` etc.) are **distinct V1 resources** with separate intents, endpoints, validation, and storage. Customer asks `"What time do you open?"` / `"Do you deliver to Dodoma?"` via Enquiry; `"Can you make this furniture?"` via Request. No automatic `Enquiry ↔ Request` conversion in V1 without explicit business action.

**Reason:** `phases/phase-1.26.md §8-9, §46`, `api-contract.md §27.4/§27.11` — distinct customer intents must not be forced into one generic communication resource; separation keeps Request's `MADE_TO_ORDER` product validation and dimensional intake clear from general messaging.

**Status:** Accepted

---

### ADR/API-ENQ-004 — Enquiry Is Not an Order (Separate Workflow)

**Decision:** Submitting a general enquiry does **not** create an `Order`, does not enter `Cart → Checkout → Payment`, does not reserve inventory, does not guarantee purchase. No `order_id` creation; `payment_*`/`delivery_fee`/`order_status` from client rejected. An enquiry may optionally reference an existing `order_id` for context (`order_id` nullable, ownership-validated), but that does not make it an Order operation nor allow Order modification via enquiry. `create_order:true` payload via enquiry rejected.

**Reason:** `phases/phase-1.26.md §8, §31, §46-48`, `api-contract.md §27.11` — keeps general business communication distinct from commercial transaction; Order-specific support remains Order-linked.

**Status:** Accepted

---

### ADR/API-ENQ-005 — Enquiry Does Not Initiate Payment or Reserve Inventory

**Decision:** General enquiries have **zero payment or inventory effect** — no `physical_quantity`/`reserved_quantity` change, no `payment_status`/`payment_id`/`provider_reference` handling. Payment remains **Phase Group H** only. `payment_id`, `payment_status`, `amount_paid`, `provider_reference` fields from client rejected. Enquiry attachments, like Request attachments, are optional and not payment artifacts.

**Reason:** `phases/phase-1.26.md §4, §48`, `api-contract.md §27.11` — payment boundary stays with Order/Group H; enquiry is communication; inventory remains backend-controlled and only checkout/orders affect stock.

**Status:** Accepted

---

### ADR/API-ENQ-006 — Anonymous Enquiry Retrieval Is Not Supported Without Secure Mechanism

**Decision:** V1 does **not** support `GET /enquiries/{enquiry}` public for anonymous merely because creation (`ENQ-001`) is public. Predictable `id` (`enq_...` or sequential) is not a bearer credential. Anonymous retrieval, if ever needed, requires explicit secure access mechanism (scoped token, one-time link, verified contact mechanism) — out of scope. `GET /enquiries` collection is never publicly searchable; only `POST /enquiries` is public. Customer `GET /me/enquiries` remains ownership-scoped; staff `GET /enquiries` operational.

**Reason:** `phases/phase-1.26.md §15, §75`, `api-contract.md §27.13` — prevents enumeration and private communication exposure; identifiers are not authorization (§27.23).

**Status:** Accepted

---

### ADR/API-ENQ-007 — Enquiry Attachments Are Optional, Private, Inline Preferred

**Decision:** Enquiry attachments are **optional** (`0` or `1` on creation in V1). Preferred transport is `multipart/form-data` inline with `ENQ-001` creation (field `attachment`), same upload architecture as `REQ-007`. Separate `POST /enquiries/{enquiry}/attachments` (`ENQ-007`) requires **scoped server-issued upload token** (single-use/time-limited) + parent ownership; predictable enquiry `id` alone insufficient. Validation: `size <=5 MB`, types `image/jpeg|png|webp|application/pdf`, actual content signature (client MIME not trusted), filename sanitized. Access inherits parent (`Customer own → own attachment`, `Staff → operational`, `Admin → authorized`, `Anonymous → scoped token only`); no permanent public URLs; internal storage keys never exposed.

**Reason:** `phases/phase-1.26.md §35-38, §45-46`, `api-contract.md §27.8` — supports reference image/document for enquiry while keeping private data protected and sharing proven `REQ-007` architecture.

**Status:** Accepted

---

### ADR/API-ENQ-008 — Original Enquiry Message Is Immutable, Plain Text, Minimal OPEN/CLOSED Status

**Decision:** Submitted `subject`/`message` + contact snapshot is **immutable history** after creation — preserved as plain text (`subject` 5–200, `message` 10–5000, validated bounds, `plain text only` in V1, no Markdown/HTML, no script execution, frontend escapes, backend safe storage + safe output encoding not destructive sanitization). Staff correction uses separate communication process, not rewrite. Status is minimal CLOSED `OPEN` (default, needs attention) → `CLOSED` (handled via `ENQ-006` `POST /enquiries/{enquiry}/close`, optional `reopen` `CLOSED→OPEN` if approved); `ASSIGNED`/`IN_PROGRESS` etc. not in V1. Customer never sets `enquiry_status`. `staff_internal_notes` separated, never customer-visible. Privacy `PRIVATE` (`CUSTOMER-PRIVATE` own, `STAFF-OPERATIONAL`, `ADMINISTRATIVE`), `Cache-Control: private, no-store` for `ENQ-002/003/004/005`, never CDN public, never SEO-indexed. Rate-limit candidate for anonymous spam.

**Reason:** `phases/phase-1.26.md §23-26, §39-42, §64`, `api-contract.md §27.9-27.10/§27.15/§27.23` — protects historical truth of what customer asked, reduces XSS/attack surface, keeps V1 small without full messaging/CRM; minimal `OPEN/CLOSED` sufficient for small-business queue.

**Status:** Accepted

---

### ADR/NOT-001 — Notification Is Downstream of Business State

**Decision:** A Notification is a user-facing or operational message derived from an authoritative business event (`Order SHIPPED → ORDER_SHIPPED`). The Notification does not create, authorize, or become the source of truth for `Order` / `Request` / `Enquiry` / `Payment` / `Fulfillment` / `User`. If Notification says `SHIPPED` but `Order` says `PROCESSING`, `Order` wins.

**Reason:** `phases/phase-1.27.md §9-11`, `api-contract.md §28.1` — keeps Order/Request/Enquiry authoritative; prevents notification drift from becoming business state.

**Status:** Accepted

---

### ADR/NOT-002 — Customers Have Private Notifications

**Decision:** Customer in-app notifications are `PRIVATE`, recipient-scoped via `GET /me/notifications` (`NOT-001`). `Customer A → Customer B` notification fails `404 RESOURCE_NOT_FOUND` masked per `§15.8`; no global notification; backend decides recipients; anonymous in-app notifications not supported.

**Reason:** `phases/phase-1.27.md §13, §17`, `api-contract.md §28.2/§28.13` — object-level authorization prevents IDOR and enumeration.

**Status:** Accepted

---

### ADR/NOT-003 — Notification Does Not Grant Resource Access

**Decision:** A Notification `target: {type: ORDER, id: ord_...}` is a server-generated navigation reference, not an authorization credential. Following a notification still requires normal `GET /me/orders/{order}` ownership/operational authorization; `Customer cannot create/change recipient/target/type` and `target: another-order` rejected.

**Reason:** `phases/phase-1.27.md §25-26`, `api-contract.md §28.9` — target is capability-free; prevents privilege escalation via notification tampering.

**Status:** Accepted

---

### ADR/NOT-004 — Notification Read State Is Recipient-Scoped

**Decision:** `read_at = null` → unread, `read_at = timestamp` → read (`is_read` derived). `read_at` is server-owned; only recipient may mark own notification read via `PATCH /me/notifications/{notification} {read: true}` (`NOT-002`); Staff cannot mark Customer notifications via Staff credentials; Admin visibility does not mutate recipient state; server authoritative across `Next.js` + `Flutter` multi-device.

**Reason:** `phases/phase-1.27.md §27-28, §50`, `api-contract.md §28.8`, `api-conventions.md §28.2` — single source of truth prevents device-local divergence and overloading read with business status.

**Status:** Accepted

---

### ADR/NOT-005 — Notification Is Not the Source of Truth

**Decision:** `Order` / `Request` / `Enquiry` authoritative even when Notification disagrees. `NOT-001` collection may include `title`/`message`/`type`/`target`, but full resource fetch remains required; notification payload does not duplicate entire business resource and does not populate authoritative state.

**Reason:** `phases/phase-1.27.md §11, §83-84`, `api-contract.md §28.1/§28.6` — downstream communication, not transaction record.

**Status:** Accepted

---

### ADR/NOT-006 — In-App Notifications Are Version 1 (Channel-Deferred)

**Decision:** `IN_APP` is the primary V1 channel; `Notification` record is logical message. `EMAIL` (Group R), `PUSH`/`SMS` are future delivery attempts (`Notification → Delivery attempt(s)`), not separate `CustomerNotification`/`StaffNotification` resources; `channel` not exposed until implemented. V1 responses do not claim `EMAIL`/`SMS`/`PUSH` capabilities.

**Reason:** `phases/phase-1.27.md §2, §64`, `api-contract.md §28.3` — keeps contract stable when `EMAIL` added via Group R; avoids `IN_APP`/`EMAIL` duplication explosion.

**Status:** Accepted

---

### ADR/NOT-007 — Email Delivery Is Deferred to Group R

**Decision:** Real `EMAIL` delivery remains **Group R**. `Business event → Notification → Email delivery` (Group R). Anonymous `request/enquiry` does not create persistent in-app notification without authenticated recipient; may trigger `email` in Group R later. `NOT-001`/`NOT-002` contract remains valid when `EMAIL` added.

**Reason:** `phases/phase-1.27.md §2, §62`, `api-contract.md §28.17` — defers SMTP/push infrastructure while keeping logical notification contract forward-compatible.

**Status:** Accepted

---

### ADR/NOT-008 — Notification Failure Does Not Roll Back Business Transactions

**Decision:** `Notification generation temporarily fails` or `Staff notification fails` must not roll back `Order shipped` / `Checkout → Order` / `Payment` / `Request` / `Enquiry` transactions; Order remains `SHIPPED` even if notification fails. Duplicate source event (`SHIPPED` processed twice) must not create two identical notifications (idempotency at event boundary). Order/Request queue remains source of truth if notification delayed.

**Reason:** `phases/phase-1.27.md §88-92, §125-126`, `api-contract.md §28.15` — notification is downstream, eventual consistency acceptable; background/queued dispatch with idempotency is future candidate (outbox).

**Status:** Accepted

---

### ADR/USER-001 — `/me` Is the Self-Service Identity Boundary

**Decision:** Version 1 self-service account API uses **`GET /api/v1/me` (`USER-001`) and `PATCH /api/v1/me` (`USER-002`)** as the **only** Customer/Staff/Admin self-service surface. Identity is derived from `authenticated principal`; client does not supply `user_id`/`customer_id`/`account_id` via query, body, or URL. `GET /me?user_id=123`, `?as_user=...`, `/users/{id}` for self-service are rejected; `Staff → Customer profile via /me` impossible. `GET /users/{id}` remains administrative (`users.manage_authorized` Phase 1.29) not self-service.

**Reason:** `phases/phase-1.28.md §12-14`, `api-contract.md §29.2/§29.3`, `api-conventions.md §29.1` — makes ownership explicit, reduces IDOR, and forces server-derived identity per `AGENTS.md §17` authentication ownership.

**Status:** Accepted

---

### ADR/USER-002 — Customer Account Ownership

**Decision:** **Customers own their accounts and control allowed profile fields.** `name`/`phone` are customer-mutable via `PATCH /me` allow-list; `id`/`role`/`permissions`/`verification state`/`account_state` are server-controlled and never changed via `PATCH /me` (`PATCH {role:ADMIN}` rejected `422` with `field: role`, mass-assignment prevented). `Customer A → Customer B profile` via any future `/users/{id}` must fail object-level check with 404 masking; `Customer cannot transfer ownership`, `change owner`, `grant privileges`. Staff operational view receives only `operational where needed` customer data via `Order`/`Request`/`Enquiry` snapshot, not unrestricted browse.

**Reason:** `phases/phase-1.28.md §10, §30`, `api-contract.md §29.5/§29.6/§29.12`, `business-rules.md §14 #1-2, #4-5, #8` — preserves `IDENT-006`/`OWN-001` ownership; prevents privilege escalation via profile update.

**Status:** Accepted

---

### ADR/USER-003 — Role Is Server-Controlled

**Decision:** `role` is `CLOSED` `CUSTOMER`/`STAFF`/`ADMIN` (`UPPER_SNAKE_CASE`) and **read-only via Profile** (`GET /me` returns `role`, `PATCH /me {role: ADMIN}` must fail with `422 INVALID_VALUE` and `field: role` per `api-contract.md §29.6`). Roles remain `CLOSED by default`; adding `MANAGER`/`DELIVERY_AGENT` etc. requires compatibility review. Role management belongs to Admin-only Phase 1.29 (`staff.approve`/`staff.manage`/`users.manage_authorized`), not to `/me`.

**Reason:** `phases/phase-1.28.md §17-18`, `api-contract.md §29.4/§29.6`, `api-conventions.md §29.2` — prevents vertical escalation via mass-assignment; keeps `api-contract.md §17.5` server-controlled role.

**Status:** Accepted

---

### ADR/USER-004 — Credential Operations Are Separate From Profile Updates

**Decision:** **Credential/security operations are separate from ordinary `PATCH /me`.** `Password change` uses `POST /api/v1/auth/change-password` (or project's established Auth path) with `{"current_password":"...","new_password":"..."}` — not `PATCH /me {password:...}`; `email` change (when supported) uses **dedicated security workflow** (`authenticated request → security confirmation → new email verification → changed`), not silent `PATCH {email: new}`. `password`/`hash`/`tokens`/`secrets` never serialized; verification state `email_verified` server-controlled (`{"email_verified":true}` from client rejected). `Staff cannot normally change Customer passwords`; Admin access to Customer credentials must not expose current password.

**Reason:** `phases/phase-1.28.md §21, §35-37`, `api-contract.md §29.7/§29.8`, `api-conventions.md §29.3`, `business-rules.md §14 #6` — keeps `Profile → ordinary personal data`; `Authentication → credentials/session/security`; prevents credential exposure.

**Status:** Accepted

---

### ADR/USER-005 — Staff Cannot Control Customer Accounts

**Decision:** **Staff cannot arbitrarily control Customer accounts through User/Profile.** `Staff cannot: change customer role, disable customer, modify customer ordering/browsing ability, change password, view credentials, impersonate, transfer ownership, block browsing-or-ordering, grant privileges`. Their `/me` is self-only. Customer administration is **Admin-only** via `GET /users` / `GET /users/{user}` (`users.manage_authorized`) with `reason/authorization/audit` — never via `STAFF` role. This is the `USER-005` separation: Staff operational access does not confer customer-account administration.

**Reason:** `phases/phase-1.28.md §28-29`, `api-contract.md §29.12`, `business-rules.md §14 #4-5`, `api-conventions.md §29.1` — preserves `AGENTS.md §17` Staff restriction as unconditional prohibition.

**Status:** Accepted

---

### ADR/USER-006 — Profile Does Not Embed Orders/Cart/Requests/Enquiries/Notifications

**Decision:** **`GET /me` is account-only.** It does **not embed** `orders[]`, `cart`, `requests[]`, `enquiries[]`, `notifications[]`. Use `GET /me/orders` (`ORD-001`), `GET /me/cart` (`CART-001`), `GET /me/requests` (`REQ-002`), `GET /me/enquiries` (`ENQ-002`), `GET /me/notifications` (`NOT-001`). Keeps representation lightweight (`private, no-store`) for frequent `app starts → GET /me` and leaves `Order`/`Request`/`Enquiry`/`Notification` as authoritative sources.

**Reason:** `phases/phase-1.28.md §43-48`, `api-contract.md §29.4/§29.10`, `api-resources.md §8.1` — prevents `everything-about-this-customer` fat resource; preserves historical snapshots in `Order`/`Request`/`Enquiry`.

**Status:** Accepted

---

### ADR/USER-007 — Saved Address Book Is Deferred

**Decision:** **Saved address book is explicitly deferred for V1.** Version 1 profile does **not include** `addresses[]`/`saved_addresses[]`/`default_address`. Order/Checkout captures transaction-specific delivery information as `delivery_address` snapshot (`api-resources.md §3.3` / `api-contract.md §24.9/§23.3`). Billing address remains V1 deferred (nullable). Future `saved_addresses` will be a separate domain, not a retroactive Profile field.

**Reason:** `phases/phase-1.28.md §41`, `api-contract.md §29.4/§29.10/§29.11`, `business-rules.md §14 #7` — keeps `Profile lightweight`, `Order` historical.

**Status:** Accepted

---

### ADR/STAFF-001 — Closed Role Model Remains CUSTOMER/STAFF/ADMIN

**Decision:** V1 roles remain **CLOSED** `CUSTOMER`/`STAFF`/`ADMIN` (`UPPER_SNAKE_CASE`). Hierarchy is permission not ownership. `STAFF` does not own customer accounts; `ADMIN` approves Staff. Client `{"role":"ADMIN"}` / `{"permissions":["*"]}` rejected `422 INVALID_VALUE` `field: role`; role mutation only via Admin controlled actions (`POST /admin/staff/{user}/approve|suspend|reactivate`), not generic `PATCH /admin/users/{id}`.

**Reason:** `phases/Phase-1.29.md §3-4`, `AGENTS.md §17` authentication ownership, `api-contract.md §30.1`.

**Status:** Accepted

---

### ADR/STAFF-002 — STAFF Operational Scope Is Commerce, Not Customer-Account Administration

**Decision:** Staff operate `products/catalog operational`, `inventory`, `orders/fulfillment`, `delivery-fee`, `requests`, `enquiries`, `operational notifications` where explicitly authorized (`products.manage`, `inventory.view/manage`, `orders.view_operational/accept/process/ship` etc.). Staff have **zero** customer-account control (`disable/block/change role/impersonate/password/credentials/delete`). No hidden staff endpoint bypasses this.

**Reason:** `Phase-1.29.md §5.3/§30.8`, `api-contract.md §30.2/§30.12`, `business-rules.md §15 #2-3`.

**Status:** Accepted

---

### ADR/STAFF-003 — No Generic Administrative PATCH for State

**Decision:** State-changing privileged operations use **explicit controlled actions** (`POST /orders/{order}/accept`, `POST /orders/{order}/ship`, `POST /orders/{order}/delivery-fee`, `POST /inventory/{inventory}/adjust`, `POST /admin/staff/{user}/approve`) — never generic `PATCH /admin/orders/{id}` that accepts arbitrary status/role/totals. Mass-assignment of `role/permissions/account_state/audit actor/timestamps` via generic body is rejected.

**Reason:** `Phase-1.29.md §5.1`, `api-contract.md §30.2`, `api-conventions.md §30.1`.

**Status:** Accepted

---

### ADR/STAFF-004 — Variable Delivery Fee Is Staff/Admin Only, Server-Calculated

**Decision:** `PICKUP fee 0 FINALIZED at creation`; `DELIVERY fee pending at checkout → Staff/Admin sets via ORD-014 POST /orders/{order}/delivery-fee {delivery_fee:{amount,currency}} → FINALIZED → total=subtotal+fee authoritative → payment eligible`. Customer `delivery_fee`/`total` payload rejected; server recalculates `total = subtotal + delivery_fee.amount` (`minor units integer arithmetic`); `PICKUP → 422 BUSINESS_RULE_VIOLATION`; already `FINALIZED → 409`.

**Reason:** `Phase-1.29.md §17-19`, `api-contract.md §30.8`, `business-rules.md §15 #9`, `api-conventions.md §30.6`.

**Status:** Accepted

---

### ADR/STAFF-005 — Inventory Adjustments Are Controlled Actions with Reason

**Decision:** Inventory mutations only via `POST /inventory/{inventory}/adjust {quantity_delta:int, reason:CLOSED}` (`reason` = `STOCK_RECEIPT/CORRECTION/DAMAGE/RETURN/AUDIT_ADJUSTMENT` CLOSED); server calculates `new_quantity = current + delta` transactionally, prevents negative where prohibited; `quantity` = units not TZS. No `PATCH {quantity:999}`. `Idempotency-Key` Required, `Critical` concurrency, audited. Customer never controls inventory.

**Reason:** `Phase-1.29.md §8`, `api-contract.md §30.5`, `api-resources.md §10.2`.

**Status:** Accepted

---

### ADR/STAFF-006 — Staff Lifecycle Is Admin-Only, Audited, No Self-Approval

**Decision:** Staff `list/get/invite/approve/suspend/reactivate` via `GET /admin/staff` / `GET /admin/staff/{user}` / `POST /admin/staff` / `POST .../approve|suspend|reactivate` (`ADM-001..006`) — `ADMIN` `staff.manage`/`staff.approve` only, `no STAFF self-approve`, `Idempotency-Key` Required for approve/suspend/reactivate, `Critical` concurrency, audited (`actor/previous→new/timestamp`). Invite `POST /admin/staff` does not accept arbitrary `password` — credential via Authentication activation.

**Reason:** `Phase-1.29.md §24-26`, `api-contract.md §30.13`.

**Status:** Accepted

---

### ADR/STAFF-007 — Role/Permission Mutation Is Privileged Controlled Operation

**Decision:** No generic `PATCH /admin/users/{id} {role:ADMIN}`. Role changes are `ADMIN`-only, explicitly named (`approve` lifecycle vs arbitrary `PATCH`), audited, self-escalation prevented, validated against `approved transition policy` (`staff_state: PENDING→ACTIVE`, `staff_state: SUSPENDED→ACTIVE`; `role: CUSTOMER→STAFF` only via invite/approval and reserve role changes for explicit role transitions). Approval is `staff_state` change, not `role` change. V1 closed roles `CUSTOMER/STAFF/ADMIN` only.

**Reason:** `Phase-1.29.md §27`, `api-contract.md §30.14`.

**Status:** Accepted

---

### ADR/STAFF-008 — Audit Creation Is Mandatory for Privileged State Changes

**Decision:** Every privileged state change creates audit event (`actor_id/role/action/resource_type/resource_id/previous→new/timestamp/request_id`) — server-derived actor, no secrets, no client-provided actor. At minimum `staff approval/suspend/reactivate`, `role changes`, `delivery-fee changes`, `inventory adjustments`, `order transitions`, `request/enquiry status changes`, `privileged catalog changes`. Optional read `GET /admin/audit-logs` read-only, filtered `actor/action/resource_type/resource_id/created_from/to`, `PRIVATE`; `PATCH/DELETE` audit prohibited. Creation remains mandatory even if read is internal-only V1.

**Reason:** `Phase-1.29.md §31-32`, `api-contract.md §30.16`, `api-resources.md §10.8`.

**Status:** Accepted

---

### Phase 1.30 — Cross-Domain Review (Completed 2026-09-03)

**Result:** All Group A contracts (`§15` errors, `§17` auth, `§18` authz, `§19` inventory, `§21` catalog, `§22` cart, `§23` checkout, `§24` order, `§25` tracking/fulfillment, `§26` request, `§27` enquiry, `§28` notification, `§29` user/profile, `§30` staff/admin) were compared per `phases/phase-1.30.md §4` 16-category method. **10 prior conflicts RESOLVED** (operational route canonical unprefixed `§30.3`, `CAT-013/014` operational reads added, `ADM-008/009` users vs `ADM-005/006` suspend, `NOT-004` duplicate removed, `INV-003` idempotency unconditional, state machine two branches, role vs `staff_state` separation, delivery-fee shape, error vocabulary, inventory `inventory exists`), `0` `CONFLICT`/`AMBIGUOUS` remaining; all `DEFERRED` remain explicit (Group H `PAY-001/002`/`WEBHOOK-001`, Group R email/push, `NOT-003/004` optional, `saved addresses`). `§31` invariants (20) and matrices A-E and test scenarios added to `api-contract.md §31`; obsolete `20,000` flat fee superseded.

### Pending: OpenAPI Operations, Payment Provider

**Deferred:** Complete `openapi.yaml` operations, provider-specific payment auth (Group H, `EXTERNAL_SERVICE_ERROR`), cursor pagination tokens (Group T). `Phase 1.16` error registry — `api-contract.md §15.15`, `Phase 1.17` auth — `§17`, `Phase 1.18` authorization — `§18`, `Phase 1.19` endpoint inventory — `§19` master table snapshot remains `PROPOSED` (see `api-contract.md §19.15`; `ORD-001..014` `APPROVED` via `§24`/`§25`, `REQ-001..007` `APPROVED` via `§26`, `ENQ-001..007` `APPROVED` via `§27`, `NOT-001..002` `APPROVED` via `§28`, `USER-001..002` `APPROVED` via `§29`, `Phase 1.29` operational detail — `§30` Staff/Admin adds controlled actions/audit/inventory-fee/staff lifecycle (catalog operational `CAT-007..012`/`INV-*`/`ADM-*` remain `PROPOSED` pending consolidated review, order `ORD-005..014` already `APPROVED` with §30 detail) and `Phase 1.30` cross-domain review — `§31` (see above) are individually `APPROVED` where marked — `PROPOSED` now scopes only to the master-table snapshot, not to those domains; consolidated `APPROVED` pending), `Phase 1.20` catalog contract — `§21`, `Phase 1.21` cart contract — `§22`, `Phase 1.22` checkout contract — `§23`, `Phase 1.23` order contract — `§24`, `Phase 1.24` tracking+fulfillment — `§25`, `Phase 1.25` made-to-order request — `§26`, `Phase 1.26` general enquiry — `§27`, `Phase 1.27` notification — `§28`, `Phase 1.28` user/profile — `§29`, `Phase 1.29` staff/admin operational — `§30`, `Phase 1.30` cross-domain review — `§31`.



### Phase 1.33 — API Contract Security Review (Completed 2026-09-05)

**Result:** Formal security review per `phases/phase-1.33.md` across 60+ checkpoints (auth, enumeration, mass assignment, financial, inventory, replay, concurrency, attachment, notification, audit, rate limiting, cache, CSRF, CORS, transport, OpenAPI). **15 findings classified, 13 FIX NOW, 2 DEFER WITH ACCEPTANCE.** All CRITICAL/HIGH resolved before implementation. No new business workflow introduced; contract now machine-enforceable for implementation.

**Deferred Findings Register — DEFER WITH ACCEPTANCE (2):**

| Finding ID | Severity | Affected Scope | Rationale | Acceptance |
|---|---|---|---|---|
| `SEC-2026-014` | LOW | `openapi.yaml` `Cache-Control` response headers not machine-verified (`GET /products` public `max-age`, `GET /me/orders` `private, no-store`, etc. rely on prose in `api-conventions.md §32.12`/`api-contract.md §32.4`; no `Cache-Control` header schema in OpenAPI) | `Cache-Control` is deployment/CDN behavior, not contract-breaking for `v1` implementation gate; prose is authoritative and implementation will enforce `private, no-store` for `PRIVATE` and `public, max-age=300` for `PUBLIC` per `api-conventions.md §32.12`; machine verification deferred to `Phase Group B` when Laravel middleware is implemented and can expose header schemas | **Accepted** — owner `API Architect` on `2026-09-05`; mitigation: prose controls + review checklist `§32.12`; residual risk `LOW`; revisit in `Phase 2.1` with `Cache-Control` header tests |
| `SEC-2026-015` | LOW | `openapi.yaml` `Notification.type` enum partial (`openapi.yaml:1040-1044` enumerates `ORDER_SHIPPED, ORDER_ACCEPTED, NEW_ORDER` vs full registry `ORDER_RECEIVED, ORDER_PROCESSING, ORDER_READY_FOR_PICKUP, ORDER_SHIPPED, ORDER_DELIVERED, ORDER_COMPLETED, ORDER_CANCELLED, NEW_MADE_TO_ORDER_REQUEST, NEW_ENQUIRY` in `api-resources.md §8.1`/`api-contract.md §28`) | Full registry deferred from `Phase 1.32` OpenAPI `PROPOSED` snapshot; `v1` `CLOSED` enforcement will be completed when `Group R` email/push delivery expands `Notification` types; current `3` values cover `Phase 1.27` `IN_APP` primary and do not block Staff/Customer flows; no security bypass (unknown type → `422` per `CLOSED` rule) | **Accepted** — owner `API Architect` on `2026-09-05`; mitigation: `CLOSED` validation + registry in `api-contract.md §28` authoritative; residual risk `LOW`; revisit in `Phase 1.34` when OpenAPI is finalized to full registry |

> Both deferrals are `LOW`, not `CRITICAL/HIGH`; they do not expose financial tampering, IDOR, or privilege escalation; they are tracked above with explicit `Severity / Scope / Rationale / Acceptance` per `phases/phase-1.33.md:1321-1339` `DEFER WITH ACCEPTANCE` requirement. No `CRITICAL/HIGH` remains unresolved.

---

### ADR/API-SEC-001 — Guest Cart Token Cryptographically Secure High-Entropy UUID, Scoped Bearer Credential

**Decision:** `X-Guest-Cart-Id` (`GuestCartId` parameter) and `guest_cart_id` cookie format is `uuid` **cryptographically secure, high-entropy `UUIDv4` CSPRNG (≥122 bits entropy, not `UUIDv1`/sequential/counter, unpredictable, not guessable)** (`550e8400-e29b-41d4-a716-446655440000`, `HttpOnly Secure` cookie `guest_cart_id` for browser, `X-Guest-Cart-Id` header for Flutter, mutually exclusive per client type). Token is an opaque **bearer credential scoped strictly to its bound guest cart** — it authorizes read/write to that guest cart only (anonymous cart request has no authenticated principal, so validating this token necessarily authorizes access to its bound cart); it **never authorizes account operations** (profile, orders, checkout) and **never authorizes merge by itself** (merge `AUTH-002`/`CART-005` requires authenticated principal + valid guest token + server-side ownership check); retired after merge.

**Reason:** Predictable sequential/`UUIDv1`/counter guest IDs allow hijacking guest carts before login merge. `UUIDv4` CSPRNG high-entropy prevents guessing/enumeration; `format: uuid` alone does not guarantee unpredictability.

**Status:** Accepted | **Affected:** `openapi.yaml:146`, `api-contract.md §19.2`, `api-conventions.md §22.6`

---

### ADR/API-SEC-002 — Authentication Credential Operations Strict Schemas, Anti-Enumeration

**Decision:** `POST /auth/change-password` requires `ChangePasswordRequest {current_password writeOnly, password writeOnly 8-128, password_confirmation writeOnly} additionalProperties:false`; `POST /auth/password/forgot` requires `ForgotPasswordRequest {email format:email} additionalProperties:false`; `POST /auth/password/reset` requires `ResetPasswordRequest {email, token, password, password_confirmation} additionalProperties:false`. All return `422` for validation, `429` with `Retry-After` for rate limiting, `401` for auth where needed. Responses are generic `MessageResponse {data:{message}}` to avoid account enumeration (`INVALID_CREDENTIALS` generic, `Request received.` for forgot).

**Reason:** Missing requestBody allowed bypass of `current_password` re-auth and mass-assignment of `role`. Anti-enumeration per `api-contract.md §17.8`.

**Status:** Accepted | **Affected:** `openapi.yaml:1249-1291`, `api-contract.md §17.8`, `api-resources.md §8.4`

---

### ADR/API-SEC-003 — Catalog Management Request Schemas Separate From Response Schemas (Mass-Assignment Protection)

**Decision:** `POST /products` uses `ProductCreateRequest {name,slug,description,product_type,price,category_id,is_active,is_published} additionalProperties:false` (required `name,slug,product_type,price,category_id`); `PATCH /products/{product}` uses `ProductUpdateRequest` (all optional, same allow-list); `POST /categories` → `CategoryCreateRequest {name,slug,description, image}`; `PATCH /categories/{category}` → `CategoryUpdateRequest`. `readOnly` fields `id, created_at, updated_at, availability, stock_indicator, reserved_quantity` absent from request schemas; `additionalProperties:false` rejects `role`, `quantity`, `approved_by` etc. with `422 INVALID_VALUE field: id`. Laravel must use `validated()->only(allowList) → DTO` never `$request->all()`.

**Reason:** Reusing `Product` response schema as requestBody permits `id` hijack, inventory override, privilege confusion. `api-contract.md §13.10/13.14` server-controlled fields never client-settable.

**Status:** Accepted | **Affected:** `openapi.yaml:1373-1399,1423-1449,1575-1599,1621-1647`, `api-contract.md §13.10`, `decisions.md ADR/API-IN-006`

---

### ADR/API-SEC-004 — Scoped Upload Token for Attachment Access

**Decision:** `POST /requests/{request}/attachments` (`REQ-007`) and `POST /enquiries/{enquiry}/attachments` (`ENQ-007`) require `security: [{bearerAuth: []}, {uploadToken: []}]` where `uploadToken` is `apiKey` in header `X-Upload-Token` (scoped single-use, time-limited, server-issued, bound to parent `request/enquiry` id). Anonymous `*` via token inherits parent authorization (`Request→Attachment`); no permanent public URL; `url` in `Attachment` is temporary signed; fetch requires parent auth. Token verified atomically with parent ownership: `404 NotFound` masked if parent inaccessible, `403` if token invalid, `422` for invalid file, `429` for rate limit, `401` for missing auth.

**Reason:** Previously `security: []` (public) or `bearerAuth` only; anonymous request/enquiries need attachment upload without bearer but with scoped token; predictable `att_...` ID alone must not authorize.

**Status:** Accepted | **Affected:** `openapi.yaml:115,146,2401,2577`, `api-contract.md §26.8/27.8`, `api-resources.md §5.4/6.4`

---

### ADR/API-SEC-005 — Canonical Operational Routes, Alias Deprecation (Attack Surface Reduction)

**Decision:** Canonical operational routes are `GET /orders`, `POST /orders/{order}/accept|process|ready-for-pickup|ship|deliver|complete`, `GET /inventory`, `POST /inventory/{inventory}/adjust`, `GET /products` (staff via same `products.view`), `GET /requests`, `GET /enquiries`. Duplicate alias paths `/staff/orders`, `/staff/orders/{order}`, `/staff/orders/{order}/accept`, `/staff/inventory`, `/staff/inventory/{inventory}`, `/staff/products`, `/staff/requests`, `/staff/requests/{request}`, `/staff/enquiries`, `/staff/enquiries/{enquiry}` removed from `openapi.yaml`. Also removed duplicate `POST /inventory/{inventory}` (kept `POST /inventory/{inventory}/adjust` canonical). If alias needed later, it must be `308` redirect to canonical with identical `Policy`.

**Reason:** Duplicate paths double attack surface; divergent `role` checks between `/orders/*` vs `/staff/*` enable vertical escalation via non-canonical path.

**Status:** Accepted | **Affected:** `openapi.yaml:2951-3186`, `api-contract.md §19.1`

---

### ADR/API-SEC-006 — Idempotency-Key Scoped to Identity + Endpoint, Not Auth Token

**Decision:** `Idempotency-Key: uuid` is opaque, not authentication. Durable store key is composite `(authenticated_identity + endpoint + key)` unique; reuse across different identity → treated as new key (no cross-account replay). Key expires 24h; never log full key with PII; same key + different body → `409 CONFLICT DUPLICATE_OPERATION`; same key + same body → replay `200/201` with original `order_reference`. `Idempotency-Key` Requires `bearerAuth` context where endpoint is `AUTHENTICATED_OWNER`/`OPERATIONAL`/`ADMINISTRATIVE`.

**Reason:** Global `key` UNIQUE allows `Customer A` to replay `Customer B`'s UUID if leaked/logged, or probe existence. Scoping prevents key-as-token.

**Status:** Accepted | **Affected:** `api-conventions.md §20.3`, `api-contract.md §23.6/30.13`, `openapi.yaml:137`

---

### ADR/API-SEC-007 — Financial Value Bounds and Immutability (Delivery Fee, Inventory)

**Decision:** `SetDeliveryFeeRequest.delivery_fee.amount` must be `>=0` and `<=5000000` minor units (50,000 TZS max) per `api-resources.md §3.4`; `currency` only `TZS`; `PICKUP` must be `0` `FINALIZED`; `DELIVERY` `null → {amount,currency}` `PENDING→FINALIZED once before PAID`, then immutable; concurrent `ORD-014` vs `PAY-001` → `409 DELIVERY_FEE_PENDING`. `InventoryAdjustRequest.quantity_delta` transactionally `new_quantity = current + delta` must remain `>=0`; `reason` CLOSED `STOCK_RECEIPT/CORRECTION/DAMAGE/RETURN/AUDIT_ADJUSTMENT` `additionalProperties:false`.

**Reason:** Unbounded `amount: 99999999900` hijacks order total before `PAID` (insider/compromised staff).

**Status:** Accepted | **Affected:** `openapi.yaml:871-876`, `api-resources.md §3.4/10.2`, `api-contract.md §23.11`

---

### ADR/API-SEC-008 — Rate Limiting Requirements (Abuse Protection)

**Decision:** All security-sensitive operations require rate limiting `429 + Retry-After` (standard `Retry-After` header, not custom field). Thresholds (infra may tune, contract identifies need): `POST /auth/register 5/h/IP`, `POST /auth/login 10/min/IP + 5/min/user`, `POST /auth/password/forgot|reset 3/min/IP`, `POST /requests 3/min/IP (anonymous) 10/min/user`, `POST /enquiries 3/min/IP`, `POST /checkout 5/min/user`, `POST /me/cart/items 30/min/user`, `POST /me/orders/{order}/cancel 5/min/user`, `POST /requests/{request}/attachments` and `POST /enquiries/{enquiry}/attachments` `10/h` per `X-Upload-Token ID + parent resource + IP` for anonymous (scoped, no user key; `5MB` max `1` per parent) and `10/h per user` for authenticated, `POST /inventory/{inventory}/adjust 20/min/staff`, `POST /admin/staff/* 30/min/admin`. `GET /products` public cacheable but still `100/min/IP` to prevent scraping. `Attachment` inline `0 or 1` per `REQ-001/ENQ-001` via `multipart`. Anonymous submissions require validation + `X-Upload-Token` scope + not enumerating private parents.

**Reason:** Anonymous public `REQ-001/ENQ-001` and auth endpoints are spam/credential-stuffing vectors. Contract previously “to be implemented later” insufficient for launch.

**Status:** Accepted | **Affected:** `api-contract.md §15.11/19`, `api-conventions.md §17.7`, `openapi.yaml:158`

---

### ADR/API-SEC-009 — Transport, CSRF, CORS, Cache Security

**Decision:** 
- **Transport:** `https://api.example.com/api/v1` only in deployed envs; `Secure` flag on cookies `HttpOnly SameSite=Strict Path=/api`, `HSTS` `max-age=31536000 includeSubDomains`, redirect `HTTP→HTTPS`, `TLS 1.2+`.
- **CSRF:** Cookie-auth mutations (`POST /checkout`, `POST /me/orders/{order}/cancel`, `PATCH /me`, `POST /requests`) require `CSRF-Token` header (`X-CSRF-Token` double-submit) validated server-side despite `SameSite=Strict`; bearer-only Flutter path exempt. Documented in `api-conventions.md §15`.
- **CORS:** `Access-Control-Allow-Origin` allow-list `https://www.example.com, https://admin.example.com` (never `*` when `Allow-Credentials true`), `Allow-Methods GET,POST,PATCH,DELETE`, `Allow-Headers Content-Type, Authorization, Idempotency-Key, X-Guest-Cart-Id, X-Upload-Token, X-CSRF-Token`, `Vary: Origin`, `Access-Control-Max-Age`. CORS ≠ auth; server-side authz mandatory.
- **Cache:** `Cache-Control: private, no-store` for `Cart, Checkout, Orders, Tracking, Notifications, Profile, Request/Enquiry private` (`PRIVATE` not CDN, `Vary: Authorization, Cookie`); `Cache-Control: public, max-age=300, s-maxage=600` + `CDN-Cache-Control` for `CAT-001..006` `PUBLIC` (no private fields). OpenAPI documents header via `Cache-Control` response header spec where needed.

**Reason:** `SameSite=Strict` alone insufficient for older browsers; wildcard CORS with credentials breaks auth; private orders must not be CDN-cached.

**Status:** Accepted | **Affected:** `api-conventions.md §15/20`, `api-contract.md §16-18`, `openapi.yaml:13`

---

### ADR/API-SEC-010 — Error Disclosure, 404 Masking, Audit Integrity (Reaffirmed)

**Decision:** Production errors never expose `SQL/table names/file paths/class names/stack traces/storage paths/auth internals/policy impl`. Customer-private `GET /me/orders/{order}`, `GET /me/requests/{request}`, `GET /me/enquiries/{enquiry}`, `PATCH /me/notifications/{notification}`, `CART-003/004` use `404 RESOURCE_NOT_FOUND` masked (not `403`) to avoid existence oracle; probing `GET /orders/1001..1003` non-distinguishing. Every error carries `meta.request_id` (per-request correlation, not user/order/payment ID). Audit actor `actor_id/role` server-derived, `timestamp` ISO8601 Z server-generated, `client cannot control actor_id/approved_by/timestamp`, history immutable (`PATCH/DELETE /audit-logs` prohibited). `GET /admin/audit-logs` `ADMIN` only, `PRIVATE no-store`, paginated, filtered allow-list, no secret leakage.

**Reason:** Reaffirms `ADR/API-ERR-004/007` and `ADR/STAFF-008` with explicit `404` masking for horizontal targets.

**Status:** Accepted | **Affected:** `api-contract.md §15.8/15.12`, `api-conventions.md §17.5`, `openapi.yaml:162-222`

---

### Phase 1.35 — Version 1 API Contract Freeze (Completed 2026-09-05)

**Result:** Formal freeze of the Version 1 API Contract concluding Phase Group A. All 75 endpoint operations (72 substantive + 3 Group H placeholders), schemas, error codes (49 closed codes), state transitions, financial bounds, and actor boundaries are frozen. Implementation teams in Phase Group B (Laravel Backend Foundation) must treat this contract as immutable input.

---

### ADR/API-FRZ-001 — Formal Freeze of Version 1 API Contract Baseline

**Decision:** The Version 1 API Contract is officially **FROZEN** as of **2026-09-05**.
- **Contract Version:** `v1` (`/api/v1` namespace)
- **Contract Status:** `FROZEN`
- **Canonical OpenAPI Specification:** `docs/api/openapi.yaml` (SHA-256: `ab81f5d140ff3a209215fd459bc2539ee1f4a878d1fc790ee438931a7b978642`, 75 operations across 64 paths)
- **Reference Documents:** `AGENTS.md`, `docs/VISION.md`, `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/api-conventions.md`, `docs/api/openapi.yaml`, `docs/domain/business-rules.md`, `docs/decisions.md`
- **Known Deferred Items (Maintained as Non-Goals for Group A):** Payment gateway integration details and provider credentials (Group H), real email/SMS/push delivery (Group R), saved customer address book, live GPS courier tracking, chat platform, custom role builders, multi-vendor marketplace abstractions.
- **Change Policy:** After this freeze, implementation teams must NOT alter externally observable API contracts (paths, verbs, schemas, field nullability, enums, error codes, state machines, financial calculation rules, or authorization logic). Any proposed modification must follow the formal Post-Freeze Change Process: `Change Request → Impact Analysis → Breaking/Non-Breaking Classification → Contract Review → ADR in docs/decisions.md → OpenAPI Update → Example Update → Verification`. Internal implementation details (DB indexes, query optimizations, internal service classes, logging) remain flexible provided they adhere to the frozen contract.

**Reason:** Concludes Group A (Phases 1.16 through 1.35). Establishes a stable, unambiguous contract baseline so backend (Laravel), frontend (Next.js), and mobile (Flutter) teams can implement their systems in parallel without API drift or redesign during construction (`phase-1.35.md §1-47`).

**Status:** ACCEPTED / FROZEN | **Freeze Date:** 2026-09-05 | **Affected:** Entire Version 1 API surface across Laravel, Next.js, and Flutter

---

