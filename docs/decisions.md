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

**Decision:** V1 **current `PROPOSED` (target `APPROVED` after Phase 1.21)** — `69` total (`66` substantive + `3` Group H placeholders): `CAT 12` [`CAT-001..006` public + `CAT-007..012` management] + `AUTH 7` + `USER 3` + `CART 5` [`CART-001..005` inc. `CART-005` `merge` for guest-cart handoff] + `CHK 1` + `ORD 13` [`ORD-001..013` inc. `ORD-013` `complete`] + `REQ 7` + `ENQ 7` [`ENQ-001..007` inc. `ENQ-007` enquiry attachments] + `NOT 2` + `INV 3` + `ADM 6` + `PAY/WEBHOOK 3` placeholders (`PROPOSED*`). Master table Status reflects current `PROPOSED`/`PROPOSED*` per `api-contract.md §19`/`§19.15`. Excludes `wishlist`/`reviews`/`coupons`/`saved addresses`/`loyalty`/`driver tracking` per MVP discipline. IDs remain retired if removed.

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

**Decision:** The guest cart token is a bearer credential. Its transport is split by client type to prevent JavaScript exposure in browsers:
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

### Pending: OpenAPI Operations, Payment Provider

**Deferred:** Complete `openapi.yaml` operations, provider-specific payment auth (Group H, `EXTERNAL_SERVICE_ERROR`), cursor pagination tokens (Group T). `Phase 1.16` error registry — `api-contract.md §15.15`, `Phase 1.17` auth — `§17`, `Phase 1.18` authorization — `§18`, `Phase 1.19` endpoint inventory — `§19` **current `PROPOSED`**, target `APPROVED` after Phase 1.21 review (see `api-contract.md §19.15`), `Phase 1.20` catalog contract — `§21`, `Phase 1.21` cart contract — `§22`.


