# Architecture Decision Records — Furniture Platform

> **Source:** Phases 1.1–1.14. New decisions are appended here per `phase-1.13.md §76` / `phase-1.14.md §71` (do not create `api-response-decisions.md` or `api-input-decisions.md`).

---

### ADR/GROUP-H-AND-I-DEFER

**Decision:** Initial Production Commerce Mode — Request Only. The first production release publishes only MADE_TO_ORDER products. Normal Cart→Checkout→Payment→Order purchasing remains disabled until business registration, payment-provider onboarding, and the deferred Groups G/H/I prerequisites are completed. Customers express purchase intent through the Made-to-Order Request flow. This is a deployment-scope decision, not removal of the frozen V1 commerce contracts.

**Reason:** business registration/payment-provider onboarding is not yet complete.

**Status:** Deferred
**Date:** 2026-09-29
**Affected:** Phase group G, H and I

### ADR/AUTH-002 — Application API Rate Limiting

**Decision:** Clerk owns credential-flow abuse protection. Laravel uses named built-in limiters for application API traffic: generous IP-keyed public reads, local-user-keyed authenticated reads/writes, checkout, order cancellation, anonymous submissions, and operational/admin actions. Limits are temporary, configurable where meaningful, and never bypassed by role.

**Security:** Authenticated limiter keys use the local `users.id`; anonymous submission keys use the trusted request IP. Raw bearer tokens, Clerk identifiers, email addresses, and passwords are never used in limiter keys or logs. Rate limiting supplements authentication, authorization, validation, transactions, and idempotency; it does not replace them.

**Status:** Accepted and implemented in Phase 4.11

---

### ADR/AUTH-001 — Clerk Token Verification and Local User Provisioning

**Decision:** Laravel verifies Clerk session tokens with `clerkinc/backend-php` using configured Clerk verification keys, authorized parties, and audiences. The verified token `sub` is the only external identity key. First authenticated requests retrieve the Clerk Backend User by that ID, then transactionally provision one local user with a nullable unique `users.clerk_user_id`, a CUSTOMER role, and a customer profile. Existing mappings are reused; email is never used for automatic linking.

**Security:** Clerk metadata and client-supplied identity/role fields are not trusted. Laravel credentials and sessions are not created. Clerk gateway failures leave local state unchanged and map to the existing external-service error vocabulary.

**Status:** Accepted and implemented in Phase 4.2

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

**Decision:** Production responses never expose `database SQL`, stack traces, `password`/`auth`/`payment secrets`, `internal file paths`, server IPs, framework exception names (`ModelNotFoundException`), `other_customer_id`, `internal_reservation_id`, provider secrets. Client sees safe `code`/`message`/`field`/`details` + `meta.request_id`; server logs retain allow-listed diagnostic metadata (request ID, safe category, status, and exception class), never raw throwable messages, provider bodies, or previous-exception chains. Unauthorized resource access (another customer's order) does not reveal existence via error. Security tests verify this.

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

**Decision:** One shared customer identity across `Next.js`, Flutter, and Admin uses the same Clerk application and maps to one Laravel `users.id`. Every Laravel API client, including the future Flutter app, sends the current Clerk session token as `Authorization: Bearer <Clerk session_token>`; Laravel accepts no browser/session cookie or client-defined mobile token as authentication. Logout is performed through the Clerk client session lifecycle; deleting a local token is not a substitute for Clerk sign-out. Multiple customer sessions (web/phone/tablet) are permitted; revoking one does not revoke all. Passwords remain Clerk-owned and are never sent to Laravel; `role` remains server-controlled.

**Reason:** `phase-1.17.md §29-36`, `§37`, `§56`, `api-contract.md §17.7/§17.10/§17.14`, `api-conventions.md §18.3-18.4`.

**Status:** Accepted

---

### ADR/AUTH-008 — Password Recovery & Verification (Secure, Email-Deferred, Anti-Enumeration)

**Decision:** Recovery uses secure time-limited single-use token (`request → token → set new password → invalidate`), no plaintext password or reset secret in response/storage, generic `"Request received."` to avoid enumeration; email delivery deferred to Group R (contract supports recovery, may be operationally unavailable until then — not weakened). `email_verified_at` is server-set via secure token (`email_verified:true` from client not authoritative); whether verification required before checkout deferred. `Phone/SMS OTP` not an auth requirement by default. Login/recovery avoid distinguishing `email exists` vs `not exists` unless justified; brute-force rate limiting identified for later.

**Reason:** `phase-1.17.md §38-42`, `§62-64`, `api-contract.md §17.8-17.9`.

**Status:** Accepted

---

### ADR/AUTH-009 — Clerk Authentication With Laravel Local Authorization Projection

**Decision:** Clerk is the sole external authority for credentials, sign-up/sign-in, sessions, password recovery, verification, and verified external identity. Laravel remains the authoritative local application identity projection: its `users.id` continues to own all domain foreign keys, roles, permissions, account business state, ownership, policies, and commerce authorization. Laravel receives `Authorization: Bearer <Clerk session token>`, cryptographically verifies the Clerk token, resolves the local user through a unique server-controlled `users.clerk_user_id` mapping derived only from verified `sub`, and attaches that local user to the request. Laravel must not exchange a Clerk token for Sanctum, a Laravel session, or another customer credential.

`clerk_user_id` is introduced only through a new Group D migration: nullable for controlled rollout, unique, indexed, immutable after secure linking, never serialised, and never writable by ordinary clients. Email is a synchronized contact/security snapshot, never an identity-linking key. JIT provisioning creates only `CUSTOMER` local users and is concurrency-safe; staff/admin authority remains exclusively local. Clerk webhooks are signed, idempotent reconciliation only, never a prerequisite for first authenticated request. Clerk deletion retains local historical records and blocks future activity according to the later account-retention policy.

Laravel `AUTH-001..008` credential/session endpoints are retired as a documented V1 post-freeze authentication change and are replaced during Phase 4.2 by Clerk client flows. `/me` remains Laravel-owned for local `name`/`phone` profile updates and local authorization context; email/verification snapshots are Clerk-owned and not writable through `/me`.

**Reason:** Preserves backend authority and local historical ownership while eliminating duplicate password/session systems, prevents email-based account takeover and Clerk-metadata privilege escalation, and keeps Laravel authorization independent of authentication-provider claims.

**Status:** Accepted | **Supersedes authentication-provider portions of:** `ADR/AUTH-002`, `ADR/AUTH-007`, `ADR/AUTH-008` | **Affected:** `docs/clerk-authentication-architecture.md`, `api-contract.md §17`, `api-conventions.md §18`, `api-resources.md §8.1`, `openapi.yaml bearerAuth`; Phase 4.2 implementation and Phase 4.12 tests.

---

### ADR/AUTH-010 — Clerk-Owned Password Recovery, Pending Security Sessions, and Session Revocation (Phase 4.4)

**Decision:** Clerk is the sole authority for password recovery, password change, compromised-password detection, and session lifecycle. Laravel introduces no custom password-reset system: it neither receives passwords/recovery tokens/OTPs nor generates reset tokens or emits reset emails. The legacy `password_reset_tokens` table is retired via a new drop migration; all retired Laravel credential/security endpoints return `410 GONE` uniformly and independent of authentication state — `AUTH-004` forgot, `AUTH-005` reset, `AUTH-008` change-password (and `AUTH-003` logout, `AUTH-006/007` email verify) each map to HTTP `410` with contract code `RESOURCE_NOT_FOUND`. `users.password` stays nullable and null for Clerk-provisioned customers.

A Clerk session whose status claim `sts` is `pending` (outstanding security task such as `reset-password`, `setup-mfa`, or `choose-organization`) is **not** fully authenticated: `OfficialClerkTokenVerifier` rejects it with `SESSION_EXPIRED` (401) before any local user is provisioned or resolved, so protected business APIs (`/me`, cart, checkout, orders, requests, enquiries) never accept a pending security-task session. No local `password_reset_required`/`mfa_enabled`/`force_password_reset` flags are introduced; Clerk remains the security-state authority.

Session revocation is exposed narrowly through a new `ClerkSessionGateway` interface (`OfficialClerkSessionGateway` wrapping `clerkinc/backend-php` `sessions->revoke()`), which maps to Clerk `Session.status=revoked`. Revoking session X revokes only X; other active sessions remain valid (multi-session). Revoke-all is a future explicit security operation and is not implemented. Password recovery never alters local RBAC or application account state (a reset does not reactivate a suspended local account, and roles remain unchanged).

**Reason:** Keeps Laravel from reintroducing a competing recovery/credential path, matches Clerk's own server-side treatment of pending sessions as signed-out, preserves enumeration protection, and documents single-session revocation semantics while avoiding a generic `ClerkService` god-object.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Authentication/Clerk/{ClerkSessionGateway,OfficialClerkSessionGateway}.php` new, `OfficialClerkTokenVerifier.php`, `ClerkAuthenticationFailure.php`, `app/Providers/AppServiceProvider.php`, `database/migrations/2026_09_16_100000_drop_password_reset_tokens_table.php`, `tests/Unit/{OfficialClerkTokenVerifierTest,OfficialClerkSessionGatewayTest}.php`, `tests/Feature/ClerkSecurityBoundaryTest.php`), `docs/domain/business-rules.md §17`, `docs/api/api-conventions.md §18.4-18.7`, `docs/api/api-contract.md`, `docs/api/openapi.yaml`

---

### ADR/AUTH-011 — Email-Verified Signup Baseline (Phase 4.5)

**Decision:** V1 customer registration credentials are `email` + `password` only; **phone is not a signup requirement** (it is application profile/contact data, never authentication identity). Clerk is the sole email-verification authority: email verification at signup is required (`email verification code` baseline; link alternative with same-device protection if the owner configures it). Laravel generates no verification token/code, sends no verification email, and never receives the verification code. `LocalUserProvisioner` now refuses to provision a local user whose Clerk primary email is not `Verified` — it throws `INVALID_AUTHENTICATION` (401) before any local row is created, so an unverified/incomplete Clerk signup cannot reach protected commerce. The local `users.email`/`email_verified_at` snapshot is read-only and derived only from trusted Clerk state (never from request JSON, never via `email != null`). Email stays non-authoritative for local identity linking; verification grants `CUSTOMER` only and never changes role or reactivates a suspended account. Public catalog and anonymous request/enquiry remain verification-free. `users.phone` was already nullable; `users.name` is now nullable through the Phase 4.6 migration because signup does not require a name; `customer_profiles` carries no phone.

**Reason:** Enforces "unverified email → fully registered CUSTOMER with unrestricted commerce" is forbidden, keeps phone out of signup while leaving it available as profile/contact data, and avoids any second Laravel verification path.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Authentication/LocalUserProvisioner.php`, `tests/Unit/LocalUserProvisionerTest.php`), `docs/api/api-contract.md §17.2/§17.9`, `docs/api/api-conventions.md §18.5`, `AGENTS.md §17`

---

### ADR/AUTH-012 — Laravel Profile Ownership and Account Retention (Phase 4.6)

**Decision:** `GET /api/v1/me` and `PATCH /api/v1/me` use the authenticated Clerk-derived local `User`; clients cannot select identity through query/body fields. Laravel owns mutable `name` and optional `phone`; Clerk owns email, verification, credentials, and sessions. Profile updates use a strict `name`/`phone` allow-list, partial semantics, server-side validation, and never call Clerk. Profile responses are explicitly `private, no-store` and exclude `clerk_user_id`, credentials, tokens, permissions, and security metadata. Local `users.name` is nullable because signup requires only email/password; a supplied profile name must be non-empty and cannot be cleared. `users.phone` remains nullable and can be explicitly cleared.

Local users with commerce history are retained when a Clerk identity is deleted or an account is closed; no ordinary profile operation hard-deletes users, rewrites historical Order/Request/Enquiry snapshots, or reactivates account state. `carts.user_id` is canonicalized to `ON DELETE RESTRICT` on all supported database drivers through a new migration, so an authenticated cart cannot silently become a guest cart or lose ownership.

**Reason:** Keeps field authority explicit, prevents profile IDOR/mass assignment, preserves historical commerce and cart ownership, and avoids making normal profile operations dependent on Clerk network availability.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Http/Controllers/Api/V1/MeController.php`, `app/Http/Requests/UpdateMeRequest.php`, `app/Services/UpdateCustomerProfile.php`, `database/migrations/2026_09_17_100000_restrict_cart_user_deletion.php`, `database/migrations/2026_09_17_110000_make_user_name_nullable.php`, `tests/Feature/ProfileOperationsTest.php`, `tests/Unit/LocalUserProvisionerTest.php`), `docs/api/api-contract.md §29.6/§29.9`, `docs/api/api-conventions.md §29.1-29.6`, `docs/api/api-resources.md §8/§13`, `docs/domain/business-rules.md`, `AGENTS.md`

---

### ADR/AUTH-013 — Flutter Uses the Shared Clerk Bearer Boundary (Phase 4.7)

**Decision:** Flutter uses the same Clerk application and identity system as Next.js. The future mobile implementation exposes a small `AuthRepository`/`ClerkAuthAdapter` that obtains the current Clerk session token and an `AuthTokenProvider` for the network layer. Only the network layer attaches `Authorization: Bearer <Clerk session_token>` to authenticated Laravel requests. Flutter sends no password to Laravel, creates no mobile-specific user/token, implements no verifier or refresh token, and stores no bearer token or password in insecure storage. Public API calls remain usable without a token; protected calls use the existing Laravel verifier, `users.clerk_user_id` mapping, provisioning, and authorization path. Clerk owns session restoration, renewal, and sign-out. Laravel `401` means authentication failure and `403` means authenticated-but-unauthorized; refresh/retry is centralized and bounded, and unsafe mutations are not blindly replayed.

No Flutter project or UI is scaffolded in this phase because the mobile application belongs to Phase Group P/Q. The package decision is deferred until Flutter implementation begins: prefer a maintained Clerk-compatible package, otherwise a narrow documented integration; isolate package-specific types behind the adapter. Only client-safe Clerk configuration may ship in the app; Clerk secret keys remain server-only. Production mobile API traffic requires HTTPS and normal certificate validation.

**Reason:** Reuses the proven Laravel authentication boundary, prevents a second identity/token system, limits community-package coupling, and gives future Flutter networking/auth phases an explicit secure contract without prematurely building the application.

**Status:** Accepted | **Affected:** `AGENTS.md §17`, `docs/api/api-conventions.md §29.7`, `docs/api/api-contract.md §31.1`, `docs/api/api-resources.md §13.3`, `docs/api/openapi.yaml bearerAuth`, `docs/clerk-authentication-architecture.md`

---

### ADR/AUTH-014 — Next.js Website Uses the Shared Clerk Bearer Boundary (Phase 4.8)

**Decision:** The future Next.js website uses the same Clerk application and client-neutral Laravel authentication boundary as Flutter. Clerk owns browser sign-up/sign-in, email verification, password recovery, session renewal, and logout. The future App Router implementation uses the current `@clerk/nextjs` integration, places `ClerkProvider` inside `<body>`, follows the installed Next.js version's `proxy.ts`/`middleware.ts` convention, uses asynchronous `await auth()` server-side, obtains a current Clerk session token through `getToken()`, and sends it to Laravel as `Authorization: Bearer <Clerk session_token>`. Laravel continues to verify the token, map `sub` to `users.clerk_user_id`, provision/resolve the local User, and enforce authorization.

The website keeps public catalog/SEO and anonymous request/enquiry pages public. Frontend route protection is an entry/UX layer only; Laravel remains authoritative for `/me`, roles, permissions, ownership, account state, and commerce operations. No NextAuth/Auth.js, Sanctum, custom JWT, refresh-token service, parallel customer cookie auth, website-specific Laravel endpoint, or frontend BFF is introduced by default. Tokens are request-scoped and never placed in rendered HTML, client props, logs, localStorage, or shared caches. Phase 4.8 does not install packages, run Clerk CLI initialization, create frontend routes/providers/middleware/UI, or modify `frontend/web/`; those belong to later website foundation/auth phases.

**Reason:** Establishes one secure, client-neutral contract before frontend implementation, prevents token and authorization duplication, preserves public SEO behavior, and avoids coupling the project to an unverified Next.js version or premature UI structure.

**Status:** Accepted/documented | **Affected:** `AGENTS.md`, `docs/api/api-contract.md §21.9`, `docs/api/api-conventions.md §29.8`, `docs/api/api-resources.md §13.3`, `docs/clerk-authentication-architecture.md`, `docs/api/openapi.yaml`; frontend implementation deferred to Groups L–O.

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

**Decision:** Version 1 roles remain CLOSED `CUSTOMER`/`STAFF`/`ADMIN`; no `*` wildcard as sole model; permission vocabulary uses smallest useful units (`products.view`/`products.manage` for catalog CRUD + images/variants — not stock, `inventory.view`/`inventory.manage` for stock-quantity adjustments — not catalog, `orders.view_operational`/`orders.accept`/`orders.process`/`orders.ready_for_pickup`/`orders.ship`/`orders.deliver`/`orders.complete`/`orders.set_delivery_fee` each distinct with state preconditions per `api-contract.md §18.6`, `requests/enquiries.view/manage`, `staff.approve/manage`) and RBAC + ownership + state baseline. Avoid super-roles; prefer explicit permissions; separation of duties `Staff → operational, Admin → staff approval`.

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
- **Browser (Next.js):** Token issued exclusively as `Set-Cookie: guest_cart_id=<token>; HttpOnly; Secure; SameSite=None`. The `X-Guest-Cart-Id` response header is **never** emitted for browser requests, as it would be readable by page JavaScript (including third-party scripts) and defeat the `HttpOnly` protection. `SameSite=None; Secure` (not `Strict`) is required because the web origin differs from the API origin; browser calls use `credentials: 'include'` with strict-origin CORS plus `Access-Control-Allow-Credentials: true` (see `api-conventions.md §22.6`).
- **Non-browser (Flutter):** Token issued exclusively in the `X-Guest-Cart-Id` response header (no `Set-Cookie`). Flutter must store it in secure device storage and send it as a request header — never in a JSON body field (request log exposure risk).

The two paths are mutually exclusive per request. The token is permanently retired server-side upon merge via Clerk-authenticated `CART-005` and may not be recycled or reused after retirement.

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

### ADR/BACKEND-001 — Phase 2.1 Laravel Backend Foundation Baseline

**Decision:** The existing Laravel application at `backend/laravel` is preserved as the project's backend (no re-scaffold). `composer create-project` was used in Group A; Phase 2.1 only verifies and configures it.

- **Installed baseline (recorded from actual runtime, not guessed):** Laravel Framework `13.29.0`, PHP `8.5.10`, Composer `2.10.3`.
- **Routing/bootstrap mechanism:** Laravel 13 `bootstrap/app.php` `withRouting()` is the mechanism. API routes are registered via `api: routes/api.php` with `apiPrefix: 'api'` — the sole API version boundary, yielding `/api/v1`. The single canonical operational liveness endpoint is `GET /health` (web, outside `/api/v1`). No `/v1` or unversioned API paths are added.
- **Health endpoint:** `GET /health` (outside `/api/v1`, operational, not a Group A business resource, not present in `openapi.yaml`) is the single canonical liveness endpoint. Response is minimal `{"status":"ok"}` with `Cache-Control: no-store` and no PHP/Laravel version, DB credentials, env vars, filesystem paths, or stack traces. A feature test (`tests/Feature/HealthEndpointTest.php`) covers it.
- **Environment (local dev only):** `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, valid `APP_KEY`. `.env` remains untracked; `.env.example` is the committed template (no real credentials).
- **Database baseline:** local driver `mysql` (MariaDB `11.8.8` on `127.0.0.1:3306`), dev database `furnitureapp`, `utf8mb4`. Only the untouched Laravel skeleton framework migrations were applied locally (`users`, `cache`, `jobs`, `sessions`, plus `migrations` metadata) so the web scaffold and `database`-based session/cache drivers boot. **No domain migrations were introduced**, per Phase 2.1 §21.
- **Deferred (deliberate, per contract):** no authentication package (e.g., Sanctum) installed; no authorization package (e.g., Spatie Permission) installed; no Redis/queue/cache infrastructure introduced; queue/cache/session remain `database`-driven on framework tables. These are decided in their dedicated phases.
- **CORS baseline:** no `config/cors.php` published; framework `HandleCors` is inert (no configured paths → no headers emitted). No wildcard `*` CORS. Production allow-list per `ADR/API-SEC-009` is configured deliberately in a later phase.
- **Error handling:** no redesign. Laravel's `bootstrap/app.php` already renders JSON for `api/*` or JSON-expecting requests; the frozen Group A `errors[]` envelope is implemented in later API foundation phases.
- **Welcome route:** retained; `GET /` returns the Laravel development page (acceptable per §27). No frontend pages are built in Laravel.

**Reason:** Phase 2.1 establishes a clean, verified, project-specific Laravel foundation for later domain phases while preserving the frozen Group A contract and the existing `backend/laravel` installation.

**Status:** Accepted | **Affected:** `backend/laravel` (routes/api.php, bootstrap/app.php, .env/.env.example), `docs/api/*` (unchanged), `docs/decisions.md`

---

### ADR/BACKEND-002 — Phase 2.6 API Routing Foundation

**Decision:** The Version 1 routing topology is implemented in `backend/laravel/routes/api.php` using Laravel 13's route-registration mechanism. There is exactly one version boundary: `apiPrefix: 'api'` + `prefix('v1')` → `api/v1`. The removed `/staff/...` aliases are not registered.

- **Endpoint inventory:** All **75** frozen OpenAPI operations are registered (verified automatically against `docs/api/openapi.yaml`); the only extra route is the single canonical operational liveness endpoint `GET /health` (outside `/api/v1`, not part of the V1 inventory, owned by Phase 2.9, absent from OpenAPI by design). No `api/v2`, `/v1`-only, or `/api`-only surfaces exist.
- **Route grouping** separates `PUBLIC` / `AUTHENTICATED OWNER` (`/me`, `/checkout`, `/payments`) / `OPERATIONAL` (`/orders`, `/inventory`, `/requests` & `/enquiries` handling) / `ADMINISTRATIVE` (`/admin/*`, `/users`, catalog/product/category writes) topology. Authorization is a route-registration property (middleware groups), never inferred from the path; paths are not used to encode roles.
- **State actions:** order transitions are controlled `POST` actions (`/orders/{order}/accept|process|ready-for-pickup|ship|deliver|complete|delivery-fee`) per `ORD-007..014`; no generic `PATCH /orders/{order}` exists. This includes `GET /inventory/{inventory}` (`INV-002`, a legit read) and `GET /admin/products(-/{product})` (`CAT-013/014`).
- **Auth boundary today:** protected groups use `clerk.auth`, which requires `Authorization: Bearer <Clerk session_token>`, verifies the Clerk identity, and resolves/provisions the local User. Missing or unusable credentials return the frozen `401` auth error envelope; Laravel session authentication is not used for API routes. `OperationalAccess` and `AdministrativeAccess` then apply the coarse local RBAC role boundary described below; Phase 4.10 owns detailed permissions, ownership, and business-state policies.
- **Authorization attachment points:** middleware `OperationalAccess` (`operational` alias) admits active local `STAFF`/`ADMIN` roles for operational routes, `AdministrativeAccess` (`admin` alias) admits only active local `ADMIN` for administrative routes, and `StaffOrAdminAccess` (`staff-or-admin` alias) admits active Staff/Admin for catalog writes intentionally available to Staff. Operation-specific `permission` middleware then applies canonical permission gates. Catalog writes require `products.manage`; the Staff approval route requires both active `ADMIN` role and `staff.approve`. Phase 4.10 still owns resource/action/ownership/business-state policies. Unauthenticated requests are stopped earlier by `clerk.auth` (→ `401`), so the guards' `403` is strictly an authenticated-but-not-authorized failure.
- **Controllers:** domain stubs under `app/Http/Controllers/Api/V1/` (one per resource family) reference controller methods from routes — no business logic lives in route files. Stubs return `501 {"status":"not_implemented"}`; the frozen responses are implemented in their domain phases. `V1Controller` provides the shared placeholder helper.
- **Constraints:** no date/numeric/UUID route constraints are imposed (`{product}`/`{category}` allow slug-or-opaque-id dual resolution; other params are opaque identifiers per contract); no implicit route-model binding is registered so future object-level authorization and 404-masking are not bypassed.
- **Rate limiting:** no named limiters or thresholds introduced (approved thresholds live in the contract; Phase 4.11 owns them). The framework `throttle` middleware remains the attachment mechanism able to return `429` + standard `Retry-After`.
- **CSRF/CORS/cache:** untouched in this phase — API routes have no CSRF by default (bearer JSON; cookie-SPA CSRF belongs to the SPA-auth phase), CORS remains inert (no config published, no wildcard), and route groups are structured so PUBLIC vs PRIVATE/OPERATIONAL cache middleware can attach later.
- **Guest cart & uploads:** `/me/cart/merge` and `/me/cart/items/*` are registered; `X-Guest-Cart-Id` transport is preserved (cart logic is for later phases). Attachment routes (`/requests/{request}/attachments`, `/enquiries/{enquiry}/attachments`) are registered as scoped `X-Upload-Token`/bearer boundary stubs; `/webhooks/payment/{provider}` is registered under webhook-signature semantics (no bearer auth). Token binding/verification is implemented later.
- **Tests:** `tests/Feature/ApiRoutingSmokeTest.php` verifies public routes need no auth, `/me`, operational, and admin routes reject unauthenticated callers with `401`, authenticated-but-unauthorized callers receive `403` on every OPERATIONAL/ADMINISTRATIVE boundary, removed `/staff/...` aliases return `404`, order transitions are `POST`, and only `/api/v1` exists. Full suite: **14 tests / 70 assertions pass**; `route:list` shows no duplicates and unique route names (`api.*`).

**Reason:** Establishes a clean, canonical, security-aware routing foundation without pulling forward domain, auth, or error-handling work, matching the frozen Group A contract exactly.

**Status:** Accepted | **Affected:** `backend/laravel` (`routes/api.php`, `bootstrap/app.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Middleware/*`, `tests/Feature/ApiRoutingSmokeTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-003 — Phase 2.7 Exception/Error Handling Foundation

**Decision:** One centralized API error boundary renders all `/api/v1` failures into the frozen V1 envelope `{"errors":[{"code","message","field"?,"details"?}],"meta":{"request_id"}}`. No controller builds its own error response; non-API requests fall through to Laravel's default rendering.

- **Architecture:** `ApiExceptionRenderer` (exception-to-contract mapper) registered via `bootstrap/app.php` `$exceptions->render(...)` for the `api/*` namespace; `ApiErrorResponse` builds the envelope and `X-Request-Id`; `ApiErrorCode` (string-backed enum) is the **CLOSED** V1 code vocabulary (structural, business, auth, HTTP/general codes from `api-contract.md §15.3`); `ApiException` is the base for domain-raised failures (code/status/field/safe details). The renderer translates failures only — it never decides business policy, queries, or ownership.
- **Request ID:** `AssignRequestId` middleware (appended to the `api` middleware group) assigns one server-generated UUID per request on `request.attributes`, echoes `X-Request-Id` on the response, and `ApiErrorResponse` reads the same value into `meta.request_id` (with a fallback for unit-test contexts). Same ID across all errors in one request; unrelated to `user_id`/resources; no secrets.
- **Transport:** `ValidateJsonBody` middleware (api group) returns `400 INVALID_JSON` for malformed JSON payloads before domain handling.
- **Framework mappings:** `ValidationException` → 422 with every determinable field error (`MISSING_REQUIRED_FIELD` for required-family rules, `INVALID_FORMAT` for format rules, `INVALID_TYPE` for type rules, else `INVALID_VALUE`) using canonical dot paths (`delivery_address.city`, `items.0.quantity`); `AuthenticationException` → 401 `AUTHENTICATION_REQUIRED`; `AuthorizationException`/`AccessDeniedHttpException` → 403 `FORBIDDEN`; `NotFoundHttpException` → 404 `RESOURCE_NOT_FOUND`; `MethodNotAllowedHttpException` → 405; `UnsupportedMediaTypeHttpException` → 415; `PostTooLargeException` → 413; `TooManyRequestsHttpException` (incl. throttle) → 429 `RATE_LIMITED` preserving the standard `Retry-After` header (no JSON retry field); anything else → sanitized 500 `INTERNAL_SERVER_ERROR` (never leaks class/SQL/stack/paths). External/upstream failures are represented by raising `ApiException` with `EXTERNAL_SERVICE_ERROR` and HTTP `502`/`503`/`504`; `ApiException` carries an optional `headers` array so a failure may propagate the standard `Retry-After` header (never a JSON retry field). Any other unmatched `HttpExceptionInterface` (e.g. `abort(409)`, `abort(410)`, `abort(503)`, maintenance mode) preserves its HTTP status via a fallback branch that maps to the closest frozen code: `409 → CONFLICT`, `502/503/504 → EXTERNAL_SERVICE_ERROR`, `500 and other 5xx → INTERNAL_SERVER_ERROR`, `410 → RESOURCE_NOT_FOUND`, remaining 4xx → `INVALID_VALUE`, forwarding `$e->getHeaders()` (so a 503 can carry `Retry-After`) and using safe generic messages (never the underlying exception text).
- **404 masking:** the foundation supports masked private-resource not-found via the generic 404 path; object-level ownership and masking decisions remain in their domain/Group D phases.
- **No mass-assignment / no business logic:** no `$request->all()` path added; strict unknown-field rejection remains a later FormRequest requirement; no auth, policies, logging subsystem, or health changes.
- **Tests:** `tests/Feature/ApiErrorHandlingTest.php` (envelope shape, 400/401/403/404/405/422/429/500 behavior, multiple nested validation errors, `X-Request-Id`, sanitization) and `tests/Unit/ApiExceptionRendererTest.php` (mapper taxonomy incl. 502/503/504 `EXTERNAL_SERVICE_ERROR` with `Retry-After` preservation, generic `HttpExceptionInterface` status/`CONFLICT` preservation, fall-through for non-API, safe `details`). Full suite: **35 tests / 246 assertions pass**; Pint clean; `route:list` unchanged (76 API routes).

**Reason:** Establishes a single, stable, contract-compliant API failure surface so later FormRequest/DTO/auth/domain phases can raise or map failures without redesigning the error layer.

**Status:** Accepted | **Affected:** `backend/laravel` (`bootstrap/app.php`, `app/Exceptions/Api/*`, `app/Support/ApiError{Code,Response}.php`, `app/Http/Middleware/{AssignRequestId,ValidateJsonBody}.php`, `tests/Feature/ApiErrorHandlingTest.php`, `tests/Unit/ApiExceptionRendererTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-004 — Phase 2.8 Logging Foundation

**Decision:** A small, centralized, file-based logging foundation that shares the Phase 2.7 request correlation ID. `config/logging.php` remains Laravel's default `stack` → `single` channel, environment-driven via `LOG_CHANNEL`/`LOG_LEVEL`/`LOG_STACK` (no new infrastructure, no Sentry/Datadog/ELK/Kafka).

- **Correlation lifecycle:** `AssignRequestId` (prepended to the `api` middleware group, before throttle) generates one server-side UUID per request, validates any client-supplied `X-Request-Id` as a trusted UUID (strict `Str::isUuid` + control-char check, lower-cased on accept; otherwise a new UUID is minted), binds it to `request.attributes['request_id']`, pushes it into `Context::add('request_id', $id)` and `Log::withContext(['request_id' => $id])`, and echoes the same value as `X-Request-Id` on the response. `ApiErrorResponse` and `ApiLogContext` read the same attribute (with a fallback UUID that is also bound back to the request), so `meta.request_id`, `X-Request-Id`, and every log entry for that request share one identifier. Same ID for success, 422/401/403/404/409/429, and 500 paths. `AssignRequestId::terminate()` clears `Context` and `Log` shared context after the response is sent, preventing cross-request leakage in long-lived processes (Octane, queue workers); in traditional FPM each request is a fresh process, but the explicit clear makes the foundation safe for both. `LOG_CHANNEL` remains env-driven (`stack` → `single` locally, `daily` for production via `LOG_CHANNEL=daily` / `LOG_DAILY_DAYS`, documented in `.env.example`), so no unbounded growth in production when configured.
- **Structured context:** `App\Support\ApiLogContext::forException()` builds an allow-listed context (`request_id`, `method`, `path`, `route`, `status`, `code`, `exception_class`, `exception`) with newline/control-char sanitization and length capping (500 chars). No headers, bodies, or model dumps are included. `ApiExceptionRenderer` logs only unexpected server failures (`status >= 500`, including `ApiException` with 500+ and the `HttpExceptionInterface` fallback) at `error` level via `Log::error('api.exception', $context)` wrapped in a `try/catch` so a failing logger never turns a business request into a second failure. Expected client failures (`422`, `401`, `403`, `404`, `405`, `415`, `429`, etc.) are not logged as `error` noise.
- **Sensitive-data protection:** No passwords, password confirmation, access/refresh tokens, guest-cart bearer (`X-Guest-Cart-Id`), upload tokens (`X-Upload-Token`), `Authorization`/`Cookie`/`Set-Cookie`/`X-CSRF-Token` headers, payment/provider/webhook secrets, full private addresses, raw request/response bodies, or full customer records are logged. `ApiLogContext` is allow-listed by construction; `AssignRequestId` rejects log-injection via UUID validation and `ApiLogContext::sanitize()` strips `\r`/`\n`/`\0` and control characters.
- **Validation:** `tests/Feature/ApiLoggingTest.php` proves one request ID is shared across response and logs, multiple logs in one request share the same ID, 500s are logged with `request_id` and sanitized, sensitive headers/bodies/tokens are absent, bodies are not dumped, 422s are not `error`-logged, and malicious `X-Request-Id` values are sanitized. Existing `tests/Unit/ApiExceptionRendererTest.php` additions cover level policy and injection.
- **Out of scope preserved:** No `audit_logs`/`request_logs` tables, no `GET /admin/audit-logs`, no queue/worker, no payment/webhook-specific logging, no external APM/Sentry.

**Reason:** Establishes a diagnosable-but-safe logging substrate that later domain, payment/webhook, and queue phases can build on without recreating logging or leaking credentials.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Http/Middleware/AssignRequestId.php`, `app/Support/ApiLogContext.php`, `app/Exceptions/Api/ApiExceptionRenderer.php`, `config/logging.php` (unchanged, env-driven), `tests/Feature/ApiLoggingTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-005 — Phase 2.11 Test Framework Setup

**Decision:** Reuse the existing PHPUnit-based test framework (PHPUnit 12, `phpunit.xml`) — `tests/Unit` for pure logic, `tests/Feature` for HTTP/API — rather than introducing Pest or a second framework. `Tests\TestCase` remains the minimal Laravel application bootstrap.

- **Isolation:** `phpunit.xml` defines `APP_ENV=testing`, `DB_CONNECTION=sqlite :memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `SESSION_DRIVER=array`, `BCRYPT_ROUNDS=4`; no production credentials or external services are used. Database-backed tests use `RefreshDatabase` (migrations run per test via `sqlite :memory:`), ready for Group C schemas without creating domain tables now.
- **Structure:** `tests/Unit` / `tests/Feature` preserved; no speculative `Product`/`Order`/`Payment` factories or fixtures; only `UserFactory` from the skeleton remains. No global business helpers in `TestCase`; domain helpers will be added with their domain phases.
- **HTTP foundation:** Laravel's `getJson`/`postJson`/`withHeaders`/`actingAs` remain the API test surface, already proven by Phase 2.7–2.9 tests (error envelope, `meta.request_id`, auth boundaries, health).
- **Commands:** `composer test` (`php artisan config:clear && php artisan test`) for full suite, `php artisan test --filter=...` / `php artisan test tests/Feature/...` for focused execution; both return non-zero on failure (verified with an intentional failing test). No CI, E2E, or frontend test tooling introduced.
- **Quality:** Tests are formatted with Pint and clean at PHPStan level 5 (which includes `tests/`).

**Reason:** Provides a single, conventional, isolated test foundation that later phases can extend for unit, feature, security, and regression tests without recreating infrastructure.

**Status:** Accepted | **Affected:** `backend/laravel` (`phpunit.xml` (unchanged), `tests/` (existing), `tests/TestCase.php` (unchanged), `composer.json` (`test` script preserved)), `docs/decisions.md`

---

### ADR/BACKEND-006 — Phase 2.12 CI Baseline

**Decision:** GitHub Actions (`.github/workflows/backend.yml`) as the single canonical CI for the Laravel backend (`Backend Quality` on `push`/`pull_request` to `main`/`review`, `contents: read`, `ubuntu-latest`, PHP 8.5).

- **Runtime:** `shivammathur/setup-php@v2` with PHP 8.5 (matches `composer.json` `^8.3` and local 8.5.10; compatible with Laravel 13, Pint, PHPStan, PHPUnit) + extensions `dom, curl, libxml, mbstring, zip, pdo, pdo_sqlite, sqlite3, bcmath, fileinfo, openssl, tokenizer, xml, ctype, json` (no unused extensions, no Node).
- **Env first:** `cp .env.example .env` before installing dependencies so the `package:discover` post-autoload hook boots with `APP_ENV=local`; without an `.env`, Laravel defaults to `production` and the fail-fast production configuration guard aborts the install.
- **Dependencies:** `composer install --no-interaction --prefer-dist --no-progress` from committed `composer.lock` (reproducible, no `composer update` in CI).
- **Key:** `php artisan key:generate` after install (ephemeral, no production secrets; `phpunit.xml` provides `APP_ENV=testing`, `DB sqlite :memory:`, `CACHE array`, `QUEUE sync`, `MAIL array`, `SESSION array`).
- **Quality gate (explicit steps, each fails the job):** `composer format:check` (non-mutating Pint), `composer analyse` (PHPStan level 5 on `app`, `bootstrap/app.php`, `config`, `routes`, `database`), `composer test` (PHPUnit 46 tests covering Phase 2.7–2.9). No `|| true`, no auto-fix commits, no coverage gate, no deployment, no monitoring, no `api/v1` domain work.
- **Parity:** CI calls the same `composer` scripts documented for local reproduction; failure category is identifiable by step name.

**Reason:** Establishes the Group B automated quality gate that later Group C–W phases can extend without duplicating CI.

**Status:** Accepted | **Affected:** `.github/workflows/backend.yml`, `backend/laravel/README.md` (CI docs), `docs/decisions.md`

---

### ADR/BACKEND-007 — Phase 3.1 Users Schema

**Decision:** One unified `users` identity/authentication table plus two optional one-to-one profile extensions, per the frozen V1 model (`docs/domain/business-rules.md §14`, `api-contract.md §17.1/§29`):

- **`users`** — `id, name, email (unique, required), phone (nullable), password (secure one-way hash; hidden, never serialized), email_verified_at (framework/contract-established), account_state (nullable string; server-controlled, deferred lifecycle — no invented enum), remember_token (framework), timestamps`. No `role`/`is_admin`/`is_staff`/`permissions` columns; RBAC is deferred to Phase 3.2.
- **`customer_profiles`** / **`staff_profiles`** — intentionally minimal: `user_id (FK → users.id, UNIQUE, cascade delete), timestamps`. No loyalty/address/preferences/employee metadata (deferred). Admins share `staff_profiles`; no `admin_profiles`, no separate `customers`/`staff`/`admins` authentication tables.
- **Models** — `User` (`customerProfile`, `staffProfile` `hasOne`; `#[Fillable(['name','email','phone'])]` — `password`, `account_state`, timestamps and role/security fields are **not** mass-assignable; `#[Hidden(['password','remember_token'])]`). Credential assignment is explicit-only: `password` is set directly (or via `forceFill`) in the future dedicated authentication workflow, never through a generic `fill()`/`update()` path (`docs/api/api-conventions.md §29.8` `POST /auth/change-password`; `ADR/API-ENQ-...`/`USER-004`). `UserFactory` assigns `password` in `afterMaking` (not via mass assignment); schema tests set `$user->password` directly before `save()`. `CustomerProfile`/`StaffProfile` (`belongsTo`, fillable `user_id`, no factories per Phase 3.1). No Eloquent event auto-creates profiles (role/registration lifecycle is Group D/Phase 3.2).
- **Tests** (`tests/Feature/UserSchemaTest.php`) verify identity fields, email uniqueness, nullable phone, secure hash, `account_state` not fillable, one-to-one/optional relationships, duplicate-profile rejection, FK enforcement, and no credential serialization. Migration order (`users → customer_profiles → staff_profiles`) and rollback verified against the local MySQL `furnitureapp` DB.

**Reason:** Establishes the unified-identity schema prerequisite for Phase 3.2 RBAC and Group D authentication without inventing unsupported fields or roles.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/0001_01_01_000000_create_users_table.php`, `database/migrations/2026_09_07_*_create_*_profiles_table.php`, `app/Models/{User,CustomerProfile,StaffProfile}.php`, `tests/Feature/UserSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-008 — Phase 3.2 Roles/Permissions (RBAC) Model

**Decision:** Adopt **`spatie/laravel-permission` 8.3.0** as the single RBAC implementation. Verified compatible with Laravel 13 (`illuminate/auth ^12|^13`, `php ^8.3`, resolves cleanly with the existing lockfile); the project owner specified this package in `AGENTS.md`, no equivalent authorization foundation existed, and its relational model is used as-is rather than maintaining a parallel custom implementation.

- **Schema (package-published migration `2026_09_07_221947_create_permission_tables.php`):** `roles`, `permissions` (unique `name`+`guard_name`), `role_has_permissions`, `model_has_roles`, `model_has_permissions` — FKs + `ON DELETE CASCADE`, composite PKs/unique constraints. `config/permission.php` uses default guard `web`, `teams=false`, `enable_wildcard_permission=false`. Empty-DB migration, rollback (drops RBAC tables), and re-seed verified on MySQL and in sqlite `:memory:` tests.
- **Roles are CLOSED `CUSTOMER` / `STAFF` / `ADMIN`** (`App\Support\RoleName`, string-backed enum per project convention). No `users.role`/`is_admin`/booleans; role authority lives in the RBAC model. No hierarchy, no wildcard.
- **Permissions** (`App\Support\PermissionName`, 19 canonical `resource.action` capabilities; `App\Support\PermissionCatalog` is the single source of truth used by seeder and tests). `PermissionCatalog::forRole()` encodes the §3.2.4 matrix: CUSTOMER `[]` (ownership-based), STAFF = 16 operational capabilities (no `staff.approve`/`staff.manage`/`users.manage_authorized`), ADMIN = all 19 (explicit, no wildcard escape hatch).
- **User-to-role:** `User` uses the package `HasRoles` trait (`roles()`, `assignRole()`, `hasRole()`, `hasPermissionTo()`, `checkPermissionTo()`). Assignment is explicit (no Eloquent model-created event); V1 effective-role invariant is one role per ordinary user, enforced by provisioning workflows (Group D), not by a multi-role precedence system.
- **Central primitive:** `App\Authorization\Authorization::allows(?User, PermissionName|string)` is deny-by-default — `null` identity or a missing permission is `false` (uses non-throwing `checkPermissionTo`). Future domain policies (OrderPolicy, ProductPolicy, etc.) depend on this instead of scattered `if ($user->role === 'ADMIN')` checks.
- **Security:** no mass-assignment path can set role/permission/authorization state (`User` fillable is `name/email/phone` only); no client-supplied `{"role":"ADMIN"}`/`{"permissions":["*"]}` can alter authorization (covered by tests).
- **Seed:** `RbacSeeder` (idempotent, deterministic — roles, permissions, matrix; re-seed produces identical baseline). Wired into `DatabaseSeeder`; the pre-existing hard-coded `test@example.com` bootstrap was made idempotent so `db:seed` is repeatable.
- **Tests:** `tests/Feature/RbacTest.php` (15 tests) — role integrity, duplicate rejection, no self-assignment/mass-assignment, full permission seed + uniqueness, wildcard disabled, CUSTOMER/STAFF/ADMIN capability matrix, separation of duties, deny-by-default, identity-based checks, deterministic seed.

**Reason:** Delivers the frozen V1 RBAC foundation (authoritative, explicit, auditable, deny-by-default) so Group D authentication and later domain policies can depend on it without reinventing authorization.

**Status:** Accepted | **Affected:** `backend/laravel` (`composer.json`/`composer.lock` (spatie/laravel-permission), `config/permission.php`, `database/migrations/2026_09_07_221947_create_permission_tables.php`, `database/seeders/{RbacSeeder,DatabaseSeeder}.php`, `app/Models/User.php`, `app/Support/{RoleName,PermissionName,PermissionCatalog}.php`, `app/Authorization/Authorization.php`, `tests/Feature/RbacTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-009 — Phase 3.3 Categories Schema

**Decision:** The catalog taxonomy is modeled as an **adjacency-list hierarchy** in a single `categories` table (`id`, `parent_id`, `name`, `slug`, `space_type`, `display_order`, `is_active`, timestamps). Root categories use `parent_id = null`; `parent_id` is a self-referencing foreign key with `nullOnDelete()` so deleting a parent detaches children rather than cascading an entire branch. V1 deliberately avoids nested-set, materialized-path, closure-table, and graph-database infrastructure (AGENTS.md §9); the intended depth is three levels below a `Furnitures Root` container.

- **Identification & semantics:** `slug` is the unique, server-validated SEO/navigation identifier across the whole tree (kebab-case, e.g. `living-room`, `home-office-corporate-workspaces`); `id` is the server-controlled machine key. The `Category` model's `saving` event enforces the kebab-case contract per the ADR/API-CAT-004 / api-contract.md §21.9 navigation contract: a `DomainException` ("A category slug must use kebab-case.") is thrown for any value failing `^[a-z0-9]+(?:-[a-z0-9]+)*$` (rejects spaces, underscores, uppercase, empty, leading/trailing/consecutive hyphens); the DB additionally enforces `UNIQUE`. `space_type` is a CLOSED enum `home`/`office`/`hybrid` (`App\Support\SpaceType`) describing usage context (not the hierarchy itself). Its seeded assignment is **explicitly documented** in §3.3.6 "Explicit seed mapping" (root container `furnitures-root` = `hybrid`; the six level-1 rooms carry their documented values; every level-2/level-3 category **inherits** its level-1 room root's value) — the complete slug-to-`space_type` mapping for all 76 seeded categories is defined in the phase document rather than invented by the seeder, and tests enforce the rule for every seeded category (root value, each room's documented value, inheritance for all descendants, plus a coverage guard that the rule visits every seeded row). `display_order` makes sibling ordering deterministic (`children` relation is `orderBy('display_order')`); `is_active` is a server-controlled public-availability flag (no activation API yet).
- **No-cycle guard:** the `Category` model enforces the §3.3.3 **no-cycle invariant**. The `saving` event rejects `parent_id === id` (self-parenting, `A → A`). Reparenting a persisted category is routed through `Category::changeParent(?int $parentId)`, which runs inside a transaction on the model's connection and rejects any assignment whose parent is a **descendant** of the category (`DomainException` "A category parent assignment cannot create a hierarchy cycle."), preventing two-node cycles (`A → B → A`) and deeper loops (`A → B → C → A`) that would break recursive category reads. **Concurrency:** the check-and-write is serialized — `changeParent` locks the moved row and its ancestor chain via `lockForUpdate()` in a consistent ascending-`id` order, then re-validates the cycle from the locked rows (expanding the lock set if the chain changed). This prevents the TOCTOU race where two concurrent reparents (e.g. `A → B` and `B → A`) could each validate the old hierarchy and both commit a cycle. This is an O(depth) ancestor walk (taxonomy depth ≤ 4 in V1), not a recursive graph-validation subsystem; closure tables and materialized paths remain out of scope (AGENTS.md §9).
- **Recommendations:** category-to-category cross-sell is a **directed** relational table `category_recommendations` (`category_id` → `recommended_category_id`, `relation_type`, `priority`, timestamps) with `cascadeOnDelete()` on both FKs. The relationship is one row per canonical pair: the uniqueness constraint is on `(category_id, recommended_category_id)` only — `relation_type` is intentionally **not** part of uniqueness, so a pair carries a single relationship row that can be re-typed rather than duplicated. Directionality is explicit: `Sofas → Coffee Tables` does not imply the reverse unless a separate row exists. `relation_type` is a database enum **derived from** the CLOSED `App\Support\CategoryRecommendationType` vocabulary (`COMPLEMENTARY`/`PAIR_WITH`/`COMPLETE_THE_LOOK`/`ALTERNATIVE`) — the migration maps `CategoryRecommendationType::cases()` into the column definition, so values outside the closed set (e.g. `UPSELL`, or a lowercase variant) are rejected at the storage layer for both model writes and raw inserts, not merely by application code; `priority` (int, default 1, higher = more precedence). **Both** recommendation directions are deterministically ordered by pivot priority descending — `recommendedCategories()` and `recommendedByCategories()` each apply `orderByPivot('priority','desc')`, so §3.3.20's "priority ordering is deterministic" requirement holds symmetrically rather than only for outgoing recommendations (a deliberate strengthening of the §3.3.13 specimen, which ordered only the forward relation).
- **Separation from products:** recommendation mappings are **deliberately separated** from product associations. No `product_category` or product relationship is introduced in this phase; the Product schema (Phase 3.4) will establish the appropriate product-category association. The recommendation table is a durable rule/configuration layer only — no recommendation engine, ranking, "frequently bought together", sales analysis, or ML is built here (AGENTS.md §9).
- **Deferred targets:** of the supplied conceptual recommendation mappings, only pairs whose **both** source and target exist in the approved taxonomy are seeded. §3.3.11 now specifies the seed **by canonical slug** — a "Canonical seed rows" table defines exactly 20 rows (one source slug + one target slug + `relation_type` + `priority` per row, with combined conceptual sources split: `Sofas/Sectionals` → `sofas` + `sectionals`, `Standing/Executive Desks` → `standing-adjustable-desks` + `executive-desks`; non-canonical labels resolved: `End Tables` → `end-side-tables`, `TV Stands` → `tv-stands-showcases`) and a "Deferred mappings" list ties each of the five unsupported targets to its requesting sources (`Accent Rugs` ← `sofas`/`sectionals`, `Media Cabinets` ← `tv-stands-showcases`, `Desk Organizers` ← `executive-desks`/`standing-adjustable-desks`, `Accent Mirrors` and `Bedroom Stools` ← `vanity-tables`; `Bedroom Stools` deliberately does **not** resolve to `stools-poufs`). Mappings referencing those targets are deferred rather than inventing undocumented categories — keeping the taxonomy authoritative — and a seed test enforces the canonical rows exactly (no omitted, additional, or non-canonical rows).
- **Authority & mass-assignment:** `Category` mass-assignment allow-list is `name`/`slug`/`space_type`/`display_order`/`is_active` only; `id`, `parent_id` (relationship ownership), timestamps, and recommendation records are server-controlled and excluded from fillable (AGENTS.md §17). No category-management endpoint exists yet; future staff/admin category writes will use the Phase 3.2 RBAC foundation with explicit allow-lists, never client-supplied role/ownership fields.
- **Seed & tests:** `CategorySeeder` is idempotent and deterministic (76 categories: 1 root + 6 rooms + 16 groupings + 53 types; 20 recommendation rows), wired into `DatabaseSeeder`. Tests: `tests/Feature/{CategorySchema,CategoryHierarchy,CategoryRecommendation,CategorySeed,CategoryConcurrentReparent}Test.php` (45 tests) — migration/FK/uniqueness/cascade/nullOnDelete integrity, **kebab-case slug enforcement (accepts valid; rejects uppercase/spaces/underscores/empty/leading/trailing/consecutive hyphens)**, hierarchy + display_order + **no-cycle invariant (self-parenting, two-node `A → B → A`, and deeper `A → B → C → A` cycles all rejected)**, **`space_type` assignment rule enforced for every seeded category (root hybrid, documented room values, inheritance for all descendants, coverage guard = 76)**, **closed `relation_type` vocabulary enforced at the storage layer (rejects `UPSELL`/lowercase variants via attach and raw insert)**, both recommendation directions + pivot + **priority ordering deterministic in both directions (forward and inverse)**, **recommendation seed rows pinned to the §3.3.11 canonical table exactly (20 rows: source slug, target slug, relation_type, priority — no omitted, additional, or non-canonical rows)**, **concurrent-reparent outcome contract (`tests/Feature/CategoryConcurrentReparentTest.php`, pcntl-fork on a file-backed WAL SQLite DB): two concurrent reparents (`A → B` and `B → A`) must not both succeed and the persisted hierarchy must remain acyclic — a regression guard proving the lock-serialized `changeParent` path**, deterministic seed + deferred-target invariant. Verified on sqlite `:memory:`; fresh migration, `Schema::dropIfExists` rollback path, and re-seed all pass.

**Reason:** Delivers the V1 catalog taxonomy foundation — a simple, navigable, SEO-friendly hierarchy plus a durable cross-sell configuration layer — sufficient for the later public category-read API and future recommendation queries, without pulling in product APIs, recommendation engines, or graph infrastructure that belong to later phases.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/{2026_09_08_100000_create_categories_table,2026_09_08_100001_create_category_recommendations_table}.php`, `database/seeders/{CategorySeeder,DatabaseSeeder}.php`, `app/Models/Category.php`, `app/Support/{SpaceType,CategoryRecommendationType}.php`, `tests/Feature/{CategorySchema,CategoryHierarchy,CategoryRecommendation,CategorySeed,CategoryConcurrentReparent}Test.php`), `docs/decisions.md`

---


### ADR/BACKEND-010 — Phase 3.5 Product Variants Schema

**Decision:** `product_variants` is the canonical **sellable/pricing unit** of the catalog. A `Product` is the parent catalog concept (Phase 3.4); a `ProductVariant` represents one distinct purchasable configuration with its own SKU, name, price, dimensions, weight, and flexible attributes.

- **Schema:** `product_variants` (`id`, `product_id` FK → `products` with `cascadeOnDelete`, `sku` unique, `variant_name`, pricing columns, dimensions, weight, `attributes` JSON, `is_default`, `is_active`, `display_order`, timestamps). Indexes: `(product_id, is_active, display_order)` for deterministic variant listing, `(product_id, is_default)` for default lookup. A variant cannot exist independently of a product.
- **Money is integer minor units:** pricing is stored as `price_amount`/`price_currency` (unsigned integer + 3-char currency, default `TZS`), mirroring the frozen V1 money convention (`{"amount": 35000000, "currency": "TZS"}`, `1 TZS = 100` minor units). **No decimal/float money columns** exist; `decimal(10,2)` is used only for non-financial physical measurements (`width_cm`, `height_cm`, `depth_cm`, `weight_kg`). `compare_at_price_amount`/`cost_price_amount` are optional and each require a matching currency when present (amount+currency null together). `cost_price` is internal/sensitive — protected from public serialization by the future explicit field-level resource layer (no automatic model serialization).
- **Dimensions/weight:** numeric centimeters / kilograms, separately stored (no "85 x 90 x 80 cm" strings, no unit columns in V1). Null is valid where a specification is genuinely unavailable; zero/negative values are rejected by the model's `saving` hook (DomainException).
- **Variant attributes:** `attributes` is an optional **structured JSON object** (V1 flexibility) for variant-specific options like `color`/`fabric`/`finish`/`size`/`configuration`/`leg_finish`. Cast as array in the model. It must not duplicate physical dimensions, price, cost, or inventory — those stay in dedicated columns/domains. No EAV system.
- **Default variant:** `is_default` marks the presentation default; the V1 invariant is **at most one default variant per product**, enforced in the application layer (`ProductVariant` `saving` hook throws `DomainException` if a second default is set for the same product). `defaultVariant()` is a `HasOne` relationship (`where is_default = true`) because the invariant is logically singular. Concurrent default-change serialization is deferred to the future mutation workflow phase.
- **Active state:** `is_active` is variant-level catalog availability, independent of inventory (Phase 3.7). `display_order` gives deterministic presentation (non-negative integer, not a ranking score).
- **Relationships:** `ProductVariant::product()` (`BelongsTo`); `Product::variants()` (`HasMany` ordered by `display_order`) and `Product::defaultVariant()` (`HasOne`). Future variant API is nested under the product (`/api/v1/products/{product}/variants`) so a variant ID is always resolved under its parent product.
- **No data duplication:** products carry no `sku`/`price`/dimensions; variants carry no inventory, media paths, or 3D/AR paths. Public product-level `price` is a later *derived* representation, not a second source of truth.
- **Factory:** `ProductVariantFactory` generates real products, unique SKUs, positive dimensions, integer minor-unit pricing, optional structured attributes; not every variant defaults to `is_default` (explicit `asDefault()` state). No inventory/media/order fabrication.
- **Tests:** `tests/Feature/ProductVariantSchemaTest.php` (28 tests) — migration/FK/cascade, SKU global uniqueness, price required + integer minor units + TZS default, compare-at/cost optionality + currency pairing, dimension/weight precision + zero/negative rejection, attribute null/round-trip, default-variant singularity (per product, allowed across products, default lookup excludes non-defaults), boolean casts, inactive-not-deleted, no inventory/media columns, no decimal money.

**Reason:** Establishes the variant as the pricing/sellable boundary required by Phase 3.6 (media), 3.7 (inventory), and later cart/checkout/order-item-snapshot phases — with money stored exactly as integer minor units and financial authority server-side — without implementing any variant API, inventory, media, availability, or pricing logic.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_09_134549_create_product_variants_table.php`, `app/Models/{ProductVariant,Product}.php`, `database/factories/ProductVariantFactory.php`, `tests/Feature/ProductVariantSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-011 — Phase 3.6 Product Images Schema

**Decision:** Product images are stored in a dedicated `product_images` table separate from `products` and `product_variants`. An image belongs to exactly one Product; it may optionally be associated with one Product Variant via a nullable `product_variant_id` that carries `nullOnDelete()` semantics.

- **Image entity separation:** `product_images` is its own table. Image URLs are not stored on `products` or `product_variants`; those tables have no image columns. This keeps image lifecycle independent of product pricing, inventory, and ordering.
- **Storage reference:** The column `file_path` stores an internal storage reference (e.g., `products/123/gallery/front.webp`), not a public CDN URL. The eventual public API resource derives the `url` field from the storage configuration plus `file_path`. Internal storage paths are never exposed in public serialization. This leaves room for local storage in development, object storage in production, and CDN delivery without a DB schema change.
- **Variant association is optional:** `product_variant_id` is nullable. An image may be product-wide or variant-specific. When a variant is deleted its associated images are retained as product-level images (`nullOnDelete`), not deleted (`cascadeOnDelete` is reserved for product deletion). When a product is deleted, all its images are deleted via `cascadeOnDelete`.
- **Product/variant consistency enforced at domain layer:** The application enforces that `product_images.product_variant_id` must reference a variant belonging to `product_images.product_id`. This cross-table invariant cannot be expressed with a simple FK pair; it is validated in `ProductImage::assertProductVariantConsistency()` on the model's `saving` event and covered by a focused test.
- **Deterministic ordering:** `sort_order` (unsigned integer, default 0) provides gallery presentation order. The Eloquent relationships `Product::images()` and `ProductVariant::images()` both apply `orderBy('sort_order')->orderBy('id')` for a stable tie-breaker.
- **Primary image:** `is_primary` (boolean, default false) marks the product's primary gallery image. The invariant "at most one primary image per product" is enforced at both the application layer (`ProductImage::assertSinglePrimary()`, `DomainException`) and the database layer (MySQL stored-column `is_primary_guard = CASE WHEN is_primary THEN product_id ELSE NULL END` with a unique constraint). This mirrors the `is_default_guard` pattern from Phase 3.5.
- **No speculative columns:** `width_px`, `height_px`, `filesize`, `mime_type`, `checksum`, `blurhash`, `dominant_color`, `glb_path`, `usdz_path`, `video_url`, `type`, `status`, `is_published`, and `is_visible` are deliberately absent. Image moderation, 3D/AR assets, and staged-room taxonomy are separate future decisions.
- **API boundary:** Public serialization exposes `id`, `url`, `alt_text`, `sort_order`, `is_primary`. The `file_path` column is never returned by the public API. No separate `GET /products/{product}/images` endpoint is created; images are embedded objects in the Product Detail API per the frozen V1 catalog contract.
- **Mass-assignment protection:** `product_id` is not fillable; it is set as a server-controlled relationship attribute. Only `product_variant_id`, `file_path`, `alt_text`, `sort_order`, and `is_primary` are fillable.
- **Indexes:** Composite indexes `(product_id, sort_order, id)`, `(product_id, is_primary)`, and `(product_variant_id, sort_order, id)` support the three primary image access patterns without premature denormalization.
- **Factory:** `ProductImageFactory` generates realistic storage paths and alt text, defaults `is_primary` to `false`, exposes `asPrimary()` and `forVariant(ProductVariant)` states.
- **Tests:** `tests/Feature/ProductImageSchemaTest.php` (21 tests) — migration/columns, Product→images relationship, ordering by `sort_order ASC, id ASC`, cascade-on-product-delete, null-on-variant-delete, optional variant association, cross-product variant rejection (domain layer), same-product variant acceptance, single-primary-per-product (application + DB constraint), multiple non-primary allowed, different products with independent primaries, `primaryImage` HasOne relationship, nullable `alt_text`, defaults/casts, no speculative columns, file path as internal reference only.

**Reason:** Establishes the image persistence layer required by the later public Product Detail API and admin product management — keeping the database independent of storage/CDN provider, enforcing product/variant ownership at the domain layer, and maintaining a minimal schema without premature media abstractions.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_100000_create_product_images_table.php`, `app/Models/{ProductImage,Product,ProductVariant}.php`, `database/factories/ProductImageFactory.php`, `tests/Feature/ProductImageSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-012 — Phase 3.7 Inventory Schema

**Decision:** Inventory is stored per Product Variant + warehouse/location in a dedicated `product_stocks` table. A stock record belongs to exactly one `product_variant_id` (FK, `cascadeOnDelete`); the same Variant/location pair is unique. `available_quantity` is derived (`quantity - reserved_quantity`), never persisted.

- **Stock per Variant/location:** `product_stocks` (`id`, `product_variant_id` FK → `product_variants` with `cascadeOnDelete`, `warehouse_location`, `quantity`, `reserved_quantity`, timestamps). A Variant may have multiple location rows (e.g. `main`, `dar-es-salaam`, `arusha-store`), but `UNIQUE(product_variant_id, warehouse_location)` (`product_stock_variant_location_unique`) prevents duplicate/ambiguous totals. Inventory is **not** stored on `products` or `product_variants` (no `quantity`/`reserved_quantity`/`available_quantity`/`total_quantity` columns there).
- **Quantity semantics:** `quantity` = physical units owned at the location; `reserved_quantity` = units reserved and unavailable for another reservation/consumption. Both are `unsignedInteger` (non-negative, server-controlled), default `0`. Negative quantities are rejected by the unsigned column type.
- **Core invariant `0 <= reserved_quantity <= quantity`:** enforced at the application layer (`ProductStock::assertValid()` on the model `saving` hook throws `DomainException`) and defensively at the database layer — MySQL `CHECK (reserved_quantity <= quantity)` (`chk_reserved_within_quantity`); SQLite `BEFORE INSERT/UPDATE` triggers with `RAISE(ABORT)` (SQLite cannot add CHECK via `ALTER`). `available_quantity` is a derived model accessor (`quantity - reserved_quantity`), not a column.
- **Location model:** `warehouse_location` is a stable bounded machine-friendly identifier in V1 (e.g. `main`, `dar-es-salaam`). No `warehouses` table, no `warehouse_name`, no address/contact/regions/delivery-zones — those are deferred until the business requires a normalized Warehouse entity. Location selection is an operational/backend concern, never client-controlled.
- **Delete behavior:** deleting a Product Variant cascades to its stock rows (`cascadeOnDelete`) because stock has no standalone meaning without its Variant. Destructive product/variant deletion workflows are not implemented here; historical orders must rely on snapshots, not live inventory.
- **No speculative columns/state:** `available_quantity`, `low_stock_threshold`, `stock_indicator`, `availability`, `product_type`, `warehouse_name`, and `product_id` are deliberately absent. `availability`/`stock_indicator` are derived domain presentation state for a later catalog phase. No reservation table, stock-movement ledger, or adjustment audit is created (deferred to later inventory operational phases).
- **Mass-assignment protection:** `product_variant_id` is server-controlled (fillable only for the factory/test path); clients never control `quantity`, `reserved_quantity`, `available_quantity`, `warehouse_location`, or ownership. Future write operations use validated input → explicit allow-list → command/service → authorization → transaction/concurrency → persistence.
- **Concurrency readiness:** the schema supports future atomic/transactional mutations (row locking, stale-state detection, overselling prevention) without redesign. The `SELECT then UPDATE` anti-pattern is explicitly prohibited for inventory; concurrency algorithms belong to Group E.
- **Relationships:** `ProductStock::productVariant()` (`BelongsTo`); `ProductVariant::stocks()` (`HasMany`). No `Product → stocks` authoritative relationship; products reach inventory through their variants.
- **Factory:** `ProductStockFactory` generates a real Variant, a valid location, non-negative `quantity`, and `reserved_quantity` within the quantity boundary; exposes `forVariant(ProductVariant)` and `atLocation(string)` states. No orders/carts/reservations/payments/warehouse/audit fabrication.
- **Tests:** `tests/Feature/InventorySchemaTest.php` (18 tests) — migration/columns, Variant→stocks + ProductStock→productVariant, multi-location, FK rejection, cascade-on-variant-delete, duplicate Variant/location rejection, same-variant-different-location coexistence, zero defaults, non-negative integer quantities, derived `available_quantity` not persisted, reserved>quantity rejected (application + database), negative quantity rejected, no inventory on products/variants, no speculative columns, unique constraint present.

**Reason:** Establishes the backend-controlled inventory persistence layer required by later cart/checkout, catalog availability, and staff inventory workflows — distinguishing physical/reserved/derived-available quantity, preventing overselling at the schema level, and keeping inventory server-authoritative — without implementing any inventory API, reservation, adjustment, concurrency, or availability logic.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_110000_create_product_stocks_table.php`, `app/Models/{ProductStock,ProductVariant}.php`, `database/factories/ProductStockFactory.php`, `tests/Feature/InventorySchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-013 — Phase 3.8 Cart Schema

**Decision:** Carts are persisted in dedicated `carts` + `cart_items` tables with XOR ownership between an authenticated customer (`user_id`) and an anonymous guest (`guest_token_digest`). The raw guest bearer credential is never stored; the database holds only a keyed HMAC-SHA-256 digest derived with a server-managed secret.

- **Guest credential model:** The raw `guest_token` is generated server-side (cryptographically secure random, ~192+ bits of entropy), held only by the guest client, never persisted, never logged, never placed in errors/analytics/audit. The persisted `guest_token_digest` is `HMAC-SHA-256(server secret, raw token)`, stored as `CHAR(64)`, unique. Deterministic exact lookup uses the digest (`WHERE guest_token_digest = ?`). A password-hashing algorithm (bcrypt/Argon2) is not used because guest tokens already carry high entropy and cart lookup requires deterministic matching. The server secret lives in `config/cart.php` via `GUEST_CART_TOKEN_KEY` (env), never in the database.
- **Ownership XOR invariant:** A cart has exactly one owner context — customer (`user_id IS NOT NULL`, `guest_token_digest IS NULL`) or guest (`user_id IS NULL`, `guest_token_digest IS NOT NULL`). Both-null and both-set are invalid. Enforced at the application layer (`Cart::assertValid()` throws `DomainException`) and at the database layer (MySQL `CHECK` `chk_cart_ownership_exclusive`; SQLite `BEFORE INSERT/UPDATE` triggers with `RAISE(ABORT)`). Client-supplied `user_id`/`guest_token_digest` can never transfer or fabricate ownership; the later handoff is a server workflow based on authenticated principal + validated credential.
- **One active cart per customer:** A customer may have many historical/inactive carts but at most one `ACTIVE`. Enforced in MySQL via stored column `active_user_guard = CASE WHEN status='ACTIVE' AND user_id IS NOT NULL THEN user_id ELSE NULL END` with `UNIQUE`. A `UNIQUE(user_id)` or `UNIQUE(user_id, status)` was deliberately avoided to preserve inactive-history semantics. SQLite uses a `UNIQUE(active_user_guard)` on the same stored column.
- **Cart status:** CLOSED vocabulary `ACTIVE` / `INACTIVE` (`App\Support\CartStatus` enum). `INACTIVE` means the cart is no longer the owner's active cart; it is not an order status, and no other statuses (`ABANDONED`/`CHECKED_OUT`/`EXPIRED`/`MERGED`) exist in V1. `updated_at` supports future staleness policies; no expiration period or cleanup jobs are defined. Carts are never hard-deleted in this phase; `INACTIVE` is the non-destructive terminal-ish state.
- **Cart items:** `cart_items` (`cart_id` FK cascade, `product_id` FK restrict, nullable `variant_id` FK restrict, `quantity`). Item identity is `product_id + variant_id` (variant nullable when the product has none). Duplicate identity is prevented at the DB by a normalized two-key design: `UNIQUE(cart_id, product_id, variant_id)` for variant lines plus `null_variant_guard = CASE WHEN variant_id IS NULL THEN product_id ELSE NULL END` with `UNIQUE(cart_id, null_variant_guard)` for null-variant lines. A single `UNIQUE(cart_id, product_id, variant_id)` is insufficient (MySQL/SQLite treat NULLs as distinct), and arithmetic guard encodings are rejected (product-id/variant-id share ID space, risking false collisions). Merge-on-repeat-add and quantity-increment belong to the later Cart API; the schema guarantees repeats cannot silently duplicate. Variant/product consistency (`variant.product_id === cart_item.product_id`) is enforced at the application boundary (`CartItem::assertVariantBelongsToProduct`) since FKs cannot express it. Quantity is a positive integer, V1 max 100 (`CartItem::MAX_QUANTITY`).
- **No pricing / inventory / reservation coupling:** Carts persist no `unit_price`/`subtotal`/`total`/discounts, no snapshots, no `stock_id`/`reserved_quantity`/`available_quantity`, no `reserved_at`/`reservation_id`/`expires_at`. The cart is mutable purchase intent; current price comes from the live variant; checkout pricing and inventory reservation are resolved later by the backend. Adding a cart item never reserves inventory.
- **Privacy/authorization readiness:** Cart data is private customer data. `guest_token_digest` is `#[Hidden]` from serialization and a unique index; it is never returned to clients. Roles (`STAFF`/`ADMIN`) do not confer cart ownership; no `cart.view_all`/`cart.manage_all` permissions exist. Cart ID is never proof of ownership. No cart API, middleware, handoff, or merge is implemented (deferred to later phases).
- **Indexes:** `carts`: `UNIQUE(guest_token_digest)`, `UNIQUE(active_user_guard)`, `(user_id, status)`, `(status, updated_at)`; `cart_items`: `UNIQUE(identity_guard)`, `(cart_id, product_id)`, `(cart_id, variant_id)`. No speculative indexes on prices/descriptions/inventory.
- **Factory:** `CartFactory` (`customerOwned`, `guestOwned`, `active`, `inactive`) persists only a digest for guests and honors an explicit `for($user)`; `CartItemFactory` generates valid cart/product/optional variant/quantity 1-100. No orders/reservations/payments.
- **Tests:** `tests/Feature/CartSchemaTest.php` (34 tests) — migration/columns, customer/guest ownership, application+DB ownership rejection (neither/both), entropy/uniqueness/predictability of tokens, deterministic digest + distinct tokens, digest lookup, raw-token-never-persisted, serialization hides digest, digest DB uniqueness, one-active-per-customer (application + DB), inactive history not blocking new active cart, status enum/closure + defaults, cart-item relationships, optional variant, cross-product variant rejection, quantity 1-100 validation, duplicate identity rejection (null-variant and variant cases), same-product-different-variants coexistence, cascade on cart delete, no pricing/inventory/reservation columns.
- **Deferred to Cart API / authorization phases:** request-boundary enforcement (client `user_id` substitution, client-supplied `guest_token_digest`, Cart ID as ownership proof, cross-customer claim, Staff/Admin ownership) requires endpoints and auth middleware that are explicitly out of scope here; the schema provides the ownership foundation those phases enforce against. Schema tests assert only what the persistence layer can prove (ownership invariants, digest lookup, raw-never-persisted, serialization hiding).
- **Scope note on §3.8.20 vs "no Cart mutation validation":** the Product/Variant consistency invariant (`variant.product_id === cart_item.product_id`) is enforced at the persistence write path (`CartItem::assertVariantBelongsToProduct()` on the model `saving` hook, covered by `test_cart_item_variant_must_belong_to_same_product`) — this is the application/domain boundary the spec requires, not Cart API mutation behavior. Add/update/remove/merge endpoints remain `notImplemented()` stubs; quantity-merge, stock/price revalidation, and checkout reservation belong to the later Cart API phases.

**Reason:** Establishes the cart ownership and persistence foundation required by the later Cart API, guest/customer lookup, handoff/merge, checkout, and stock-revalidation phases — with security-first guest credential handling (high-entropy token + keyed digest) and an XOR ownership invariant enforced in both application and database layers — without implementing any cart API, reservation, pricing, or checkout behavior.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_130000_create_carts_table.php`, `database/migrations/2026_09_10_130001_create_cart_items_table.php`, `app/Models/{Cart,CartItem}.php`, `app/Support/{CartStatus,GuestCartCredential}.php`, `config/cart.php`, `.env.example`, `database/factories/{CartFactory,CartItemFactory}.php`, `tests/Feature/CartSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-014 — Phase 3.9 Orders Schema

**Decision:** Orders are persisted as historical commercial records in a dedicated `orders` table owned by `customer_id` (FK → `users`, `restrictOnDelete`). The order carries its own financial totals, recipient/contact snapshot, and delivery-address snapshot; it references no live product, payment, delivery, or status-history state.

- **Ownership:** `customer_id` references the shared `users.id` identity; no separate customer table. Deleting a customer with orders is rejected (`restrictOnDelete`) — historical financial records are never cascade-deleted. `User::orders()` (`HasMany`) and `Order::customer()` (`BelongsTo`) are the only Phase 3.9 relationships. `customer_id` is not mass-assignable; ownership comes from the authenticated principal at the later checkout boundary.
- **Order reference:** `order_reference` (`CHAR(8)`, unique) follows `OD-*****` (5 uppercase alphanumerics), server-generated, never derived from the numeric ID, immutable after creation (model rejects changes with `DomainException`; format validated on save). The full generation-with-collision-retry service belongs to the checkout phase; the schema provides the unique boundary.
- **Status:** CLOSED `OrderStatus` enum (9 states), native cast, DB default `PENDING_PAYMENT`. No transition logic, no per-status timestamps, no `previous_status`/JSON history columns — history belongs to Phase 3.11.
- **Fulfillment:** CLOSED `FulfillmentType` enum (`PICKUP`/`DELIVERY`), native cast.
- **Delivery-fee lifecycle (Model B):** CLOSED `DeliveryFeeStatus` enum (`PENDING`/`FINALIZED`). PICKUP ⇒ `FINALIZED` + fee `0` + total `=` subtotal + address `null`. DELIVERY + `PENDING` ⇒ fee `null` + total `=` subtotal (**provisional**, never presented as the payable amount per the frozen contract `api-contract.md §23.6`/`§24` and `ADR/API-ORD-001`). DELIVERY + `FINALIZED` ⇒ fee `>= 0` + total `=` subtotal `+` fee (authoritative, payable). Enforced in `Order::assertValid()`; the model computes nothing — checkout owns calculation.
- **Money:** `subtotal_amount` (required), `delivery_fee_amount`/`total_amount` (nullable until finalized) as `unsignedBigInteger` integer minor units, `currency` `CHAR(3)` default `TZS` (`Order::CURRENCY_TZS`). Non-negativity defended at the DB (MySQL `CHECK`, SQLite triggers); no float/decimal money. **Financial immutability is enforced in `Order::assertFinancialImmutability()` (DomainException on the `saving` hook): once the order has moved past `PENDING_PAYMENT` or its `delivery_fee_status` was already `FINALIZED` in the persisted row, any dirty `subtotal_amount`/`delivery_fee_amount`/`total_amount` is rejected** — gated on the *original* (pre-update) status/fee values so the legitimate PENDING→FINALIZED delivery-fee flow (which happens while still `PENDING_PAYMENT`) is not blocked. Order money is immutable history once the order is past `PENDING_PAYMENT` or finalized; covered by `test_financial_values_are_immutable_once_paid`, `test_financial_values_are_immutable_once_fee_finalized`, and `test_delivery_fee_finalization_flow_is_still_allowed_while_pending_payment`.
- **Snapshots:** `recipient_name`/`recipient_phone` and JSON `delivery_address` (`{address_line, city, region, postal_code}`) are per-order snapshots; profile changes never rewrite them. PICKUP stores address `null`. No saved-address FKs — the V1 address book is deferred.
- **Separations:** no payment/provider columns (Phase 3.12), no logistics columns (Phase 3.13), no item lines/JSON (Phase 3.10), no inventory coupling, no `SoftDeletes` (`CANCELLED` is a status, cancelled orders stay readable).
- **Mass assignment:** `$fillable` is limited to `fulfillment_type`, `recipient_name`, `recipient_phone`, `delivery_address`; all financial/identity/state fields are server-set (factories run unguarded).
- **Indexes:** `(customer_id, created_at)`, `(status, created_at)`, `(fulfillment_type, status)`, `(delivery_fee_status, status)` — customer history and operational queues; no speculative payment/provider indexes.
- **Factory:** `OrderFactory` defaults to a consistent PICKUP order with `for($user, 'customer')` ownership; `pickup()`, `deliveryPending()`, `deliveryFinalized(?fee)` states keep fee/total/address consistent; no items/payments/deliveries/history/reservations.
- **Tests:** `tests/Feature/OrderSchemaTest.php` (33 tests) — migration/columns/rollback, ownership + restrict-delete + fillable surface, reference format/uniqueness/immutability/DB-duplicate, closed vocabularies + defaults + unknown rejection, pickup/pending/finalized fee states + negative cases, integer money + TZS + DB negativity + required subtotal + FK, recipient/address snapshot independence, no speculative columns, no soft deletes.

**Reason:** Establishes the order as the immutable historical commercial record required by later checkout, items-snapshot, status-history, payment, and delivery phases — with server-controlled identity, state, and money, and explicit delivery-fee lifecycle states — without implementing any checkout, transition, payment, delivery, or order-API behavior.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_140000_create_orders_table.php`, `app/Models/{Order,User}.php`, `app/Support/{OrderStatus,FulfillmentType,DeliveryFeeStatus}.php`, `database/factories/OrderFactory.php`, `tests/Feature/OrderSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-015 — Phase 3.10 Order Items Snapshot Model

**Decision:** `order_items` is a historical snapshot table. Each item preserves the commercial facts that existed at order creation (SKU, product name, variant name, unit price, quantity, line total) as snapshot columns. Product/Variant IDs are retained only as traceability references with `nullOnDelete`; current catalog values never rewrite historical items.

- **Historical snapshot semantics:** `order_items` stores `sku`, `name`, `variant_name`, `unit_price_amount`, `quantity`, `line_total_amount` directly. No product-to-order synchronization, variant-to-order synchronization, price-refresh jobs, catalog event listeners, model observers, or scheduled recalculations exist — catalog changes leave stored items untouched. The model has no business-heavy methods.
- **Relationship/FK rules:** `order_id` FK → `orders` with `cascadeOnDelete` (order + items form one historical aggregate; item cannot exist without an order). `product_id` and `variant_id` are nullable FKs → `products`/`product_variants` with `nullOnDelete` — retiring a catalog record nulls the reference but never deletes historical items and never loses snapshot values. No unique constraint on product/variant (a product legitimately appears in many orders); no `(order_id, product_id)` uniqueness.
- **Money & quantity:** integer minor units — `unit_price_amount`/`line_total_amount` `unsignedBigInteger`, `quantity` `unsignedInteger`, no float/DB decimal money. Monetary type matches the Order's single TZS currency (no competing currency column on items). Financial invariants: `unit_price_amount >= 0`, `quantity > 0`, `line_total_amount = unit_price × quantity` — enforced in `OrderItem::assertValid()` (DomainException) **and** at the database (defense-in-depth so query-builder/bulk writes cannot bypass): MySQL `CHECK (line_total_amount = unit_price_amount * quantity)` + non-zero quantity check; SQLite `BEFORE INSERT/UPDATE` triggers for the same two rules. The DB constraints are covered by raw-insertion regression tests (`test_inconsistent_line_total_is_rejected_by_database`, `test_zero_quantity_is_rejected_by_database`), which bypass the model entirely.
- **Product/Variant consistency:** when `variant_id` is present it must belong to `product_id` (`variant.product_id === order_items.product_id`), enforced at the application/domain boundary (`OrderItem::assertVariantBelongsToProduct`) since a generic FK cannot express it.
- **Mass assignment safety:** all historical fields are trusted order-creation inputs only; client input never directly assigns them in the later API (values come from server-resolved pricing/cart state). No internal DB fields are auto-serialized to public responses (explicit allow-lists live in the API phase).
- **Indexes:** `(order_id, id)` as the primary access path (items are retrieved through their order), plus `product_id` and `variant_id` reference indexes. No speculative indexes.
- **Model:** `OrderItem` with `order()`, `product()`, `variant()` BelongsTo; integer/unsigned casts. `Order::items()` hasMany added.
- **Factory:** `OrderItemFactory` produces internally consistent items (line total = unit × qty, valid order/product, optional same-product variant); states `forVariant(ProductVariant, Product)` and `withLineTotal(?unitPrice, ?quantity)` with representative quantities (1, multiple, 100 boundary).
- **Pre-existing fix:** `ProductFactory`'s fallback category slug is now lowercased (`general-{random}`) to satisfy the kebab-case category invariant; surfaced because the item factory creates real products.
- **Tests:** `tests/Feature/OrderItemSchemaTest.php` (20 tests) — migration columns/FKs/indexes, Order↔Item relationships, cascade-on-order-delete, null-on-product/variant-delete with snapshot retention, cross-product variant rejection, positive quantity (app + DB), line-total invariant, negative money rejection, integer money, representative quantities, historical snapshot survival across product name / variant name / SKU / price changes, no synchronization, no unapproved columns.

**Reason:** Establishes the historical order-line persistence required to answer "what did the customer buy, at what price, in what quantity, for what line total" independently of future catalog changes — without implementing any checkout, pricing resolution, stock reservation, or order-workflow logic.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_150000_create_order_items_table.php`, `app/Models/{OrderItem,Order}.php`, `database/factories/OrderItemFactory.php`, `database/factories/ProductFactory.php`, `tests/Feature/OrderItemSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-016 — Phase 3.11 Order Status History Schema

**Decision:** Order lifecycle transitions are persisted as append-only rows in `order_status_history`. Each row records the order, the reach-to (`to_status`), the optional prior state (`from_status`), the actor source (`actor_type` + nullable `actor_id`), optional customer/internal notes, and a server-controlled `occurred_at` timestamp. The timeline is a filtered presentation of this table — never a separate source of truth.

- **Schema:** `order_status_history` (`id`, `order_id` FK `cascadeOnDelete`, nullable `from_status`, required `to_status`, required `actor_type`, nullable `actor_id` FK `nullOnDelete`, nullable `customer_note`/`internal_note`, `occurred_at`, `created_at`). **No `updated_at`** — events are immutable; persisted `created_at` is separate persistence metadata from the domain `occurred_at`. Composite index `(order_id, occurred_at, id)` serves the deterministic timeline query `ORDER BY occurred_at ASC, id ASC`. No unique constraint on `(order_id, to_status)` — duplicate/prevented transitions are an idempotency concern for the future transition workflow, not a schema rule.
- **Status values** exactly reuse the CLOSED `OrderStatus` enum (9 V1 states) — no new `CREATED`/`NEW` status; the initial event is naturally `from_status = null, to_status = PENDING_PAYMENT` (`OrderStatusHistoryFactory::initial()`). No fulfillment branch information is duplicated onto rows (the Order owns `fulfillment_type`). No GPS/carrier/logistics, no `cancelled_at`, no general-audit columns.
- **Actor model:** CLOSED `OrderActorType` enum (`CUSTOMER`/`STAFF`/`ADMIN`/`SYSTEM`), server-derived, never client-provided. System events have `actor_id = null`; customer/staff/admin events require an `actor_id` (enforced in `assertValid()`). `actor_id` FK uses `nullOnDelete` so deleting a user never destroys history. No staff names/phones/profiles duplicated into rows — `actor_id` is sufficient for operational traceability; customer tracking later presents a friendly timeline without operational identity.
- **Notes:** `customer_note` (customer-visible only by explicit serialization allow-list) and `internal_note` (never exposed to customer tracking) are separate columns — no ambiguous single `note` field.
- **Append-only enforcement:** model `$table = 'order_status_history'` (explicit, since Eloquent would inflect to `order_status_histories`), `UPDATED_AT = null`, and `booted()` hooks throw `DomainException` on any update or delete (`updating`, `deleting`). Normal business flows only append; a future controlled correction workflow is a separate audited concern (out of scope). No history-rewriting observers/jobs.
- **Deletion rules:** deleting an Order cascades to its history rows (historical aggregate); deleting an actor user nulls `actor_id` without deleting events; product/variant/payment/delivery are not related here.
- **Order model:** `Order::statusHistory()` hasMany ordered `occurred_at ASC, id ASC`; `OrderStatusHistory::order()` and `::actor()` BelongsTo.
- **Factory:** `OrderStatusHistoryFactory` with `initial()`, `transition(from,to)`, `byUser(user,type)`, `withCustomerNote()`, `withInternalNote()`, `at(carbon)` states; defaults to a valid initial `SYSTEM → PENDING_PAYMENT` event.
- **Tests:** `tests/Feature/OrderStatusHistorySchemaTest.php` (22 tests) — migration columns/no-`updated_at`, order/history relationships, actor resolution + system-null, cascade-on-order-delete, nullOnDelete-on-actor (history retained), initial-event persistence, transition-event persistence, actor-validity rejects (system+actor_id, non-system null actor), closed-status rejections, immutability (update/delete rejections), `occurred_at ASC, id ASC` ordering with same-timestamp ties, notes independence, no staff-profile/GPS/logistics/audit columns.
- **Out of scope (later phases):** the order state machine, transition validation, action endpoints, cancellation, payment/delivery workflows, tracking endpoints, notifications, general audit logging, and history correction — this phase only defines where transition events are stored.

**Reason:** Establishes the append-only, server-authoritative, chronologically-deterministic order-event persistence required by customer tracking and staff operational views — without implementing any state-transition, payment, or tracking behavior.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_10_160000_create_order_status_history_table.php`, `app/Models/{OrderStatusHistory,Order}.php`, `app/Support/OrderActorType.php`, `database/factories/OrderStatusHistoryFactory.php`, `tests/Feature/OrderStatusHistorySchemaTest.php`), `docs/decisions.md`

### ADR/BACKEND-017 — Phase 3.12 Payment Schema & Internal↔Contract Status Mapping

**Decision:** Payments are persisted in a dedicated `payments` table with a separate internal webhook-dedup table `payment_webhook_events`; each Payment is a server-side attempt on an Order and may repeat per Order (no `UNIQUE(order_id)`).

- **Internal `PaymentStatus` enum (DB-internal, per `phases/group-C-phases.md §8` only):** `PENDING`, `PROCESSING`, `SUCCEEDED`, `FAILED`, `CANCELLED`, `EXPIRED`. This is a **storage/domain-internal** vocabulary, deliberately richer than the API contract's.
- **Mapping to frozen contract `payment_status` (`docs/api/openapi.yaml` → `PENDING/PAID/FAILED`):** the V1 contract exposes only three customer/operational values. Internal statuses map at serialization time and **never leak verbatim**:
  - internal `SUCCEEDED` → contract `PAID` (authoritative success; sets `confirmed_at`; the only status that may carry non-null `confirmed_at` per the model's `assertConfirmedAtState()`);
  - internal `PENDING`/`PROCESSING` → contract `PENDING` (in-flight; never surfaced as a terminal value);
  - internal `FAILED` → contract `FAILED`;
  - internal `CANCELLED`/`EXPIRED` are **non-terminal-to-contract** and **never exposed surface-level** — they have no contract value in V1; any consumer-facing representation must be finalized when Group H defines `PAY-002`.
  - **Group H must not emit the internal enum** (`SUCCEEDED`, `PROCESSING`, `CANCELLED`, `EXPIRED`) in any API response; exposing `SUCCEEDED` would break the CLOSED `payment_status` enum (`PENDING/PAID/FAILED`) and the `UPPER_SNAKE_CASE`/closure rules (`api-contract.md §3.8/§5.3`). Contract `PENDING` must not be used to mean a terminal internal state.
- **Schema `payments`:** `id`, `order_id` FK `restrictOnDelete`, unique server-generated `payment_reference` (`PAY-`+8, immutable, not DB-ID derived), `provider`/`method` (required plain strings — provider/method selection is not yet made, no enum invented), `status`, `amount` `unsignedBigInteger` minor units + non-negative constraint (MySQL `CHECK` / SQLite trigger), `currency` `char(3)` `TZS`, nullable `provider_transaction_id`/`provider_reference` (scoped-unique `(provider, provider_transaction_id)` where non-null), nullable `failure_code`/`failure_message` (redacted+truncated to 500 via `DiagnosticText`), `initiated_at` required, nullable `confirmed_at` (null until authoritative success; immutable once set), nullable `expires_at`, timestamps. No `UNIQUE(order_id)` (multiple attempts), no refund statuses, no `provider_payload`, no card/credential fields.
- **Immutability:** after creation `order_id`, `payment_reference`, `provider`, `amount`, `currency`, `initiated_at` are `isDirty`-guarded immutable; `provider_transaction_id`/`provider_reference` allow `null → value` once then immutable; `confirmed_at` immutable once recorded. `status` is a controlled workflow field (Group H transition). Comparisons use `isDirty()`/`getRawOriginal()` (not `getOriginal()` object identity) so datetime-cast fields are compared correctly and no-op saves do not throw.
- **Cross-field rule:** `assertConfirmedAtState()` — `SUCCEEDED ⇔ confirmed_at` (SUCCEEDED must have it; non-SUCCEEDED must be null). Mirrors `PaymentWebhookEvent::assertProcessingState()` (`RECEIVED ⇔ processed_at null`, `PROCESSED`/`FAILED ⇔ processed_at required`).
- **Webhook deduplication `payment_webhook_events`:** `id`, nullable `payment_id` FK `nullOnDelete`, `provider`, `provider_event_id`, nullable `provider_correlation_id` (safe correlation aid for matching an unmatched event to a Payment later; not a dedup key, not a credential), `event_type`, internal `processing_status` (`RECEIVED`/`PROCESSED`/`FAILED`), `received_at`, nullable `processed_at`, nullable `failure_reason` (redacted+truncated 500). Durable `UNIQUE(provider, provider_event_id)` is the primary dedup key. `provider`/`provider_event_id` immutable after creation to preserve dedup identity.
- **Privacy/security:** Payment data private, deny-by-default, never in public catalog, never through unauthenticated endpoints, never `model.toArray()` wholesale; every operation authorized from the authenticated principal + Order ownership (or approved staff role), never client-supplied identity. Webhook-event rows are internal infrastructure (server-side workflows + approved operational roles only). `failure_message`/`failure_reason`/`provider_correlation_id` bound+redacted.
- **Deletion rules:** Order deletion restricted (does not cascade into Payments); Payment deletion not a normal operation; webhook events survive Payment deletion via `nullOnDelete`. No soft deletes.
- **Money authority:** amount is the server-authoritative exact payable total at the attempt; `payments.currency = orders.currency = TZS`; `assertConsistentWithOrder()` enforces amount/currency match; no FX.
- **Models** `Payment` (`belongsTo Order`, `hasMany PaymentWebhookEvent`) and `PaymentWebhookEvent` (`belongsTo Payment` nullable); `Order::payments()` hasMany added.
- **Factories:** `PaymentFactory` (states `pending/processing/succeeded/failed/cancelled/expired`, `generateReference()` `PAY-`+8, amount/currency derived from the same Order fixture) and `PaymentWebhookEventFactory` (`received/processed/failed`, `withCorrelation`, `forPayment`). No fake successful transactions in general seed data.
- **Tests:** `tests/Feature/PaymentSchemaTest.php` (45 tests) and `tests/Feature/PaymentWebhookEventSchemaTest.php` (30 tests) — migration columns, relationships, multiple-attempt, `payment_reference` format/uniqueness/immutability, integer minor-unit TZS, nullable provider identity, scoped provider-transaction uniqueness, cross-provider overlap, `confirmed_at` state/immutability, webhook dedup unique + nullable matching + `nullOnDelete` retention, processing-status/timestamp combinations, redaction/truncation, no secret columns, privacy/authorization boundaries.
- **Deliberately NOT implemented (Group H):** provider selection/SDK, initiation endpoint, state machine, webhook signature verification, callback endpoint, `Order PENDING_PAYMENT → PAID`, refunds/chargebacks/settlement, reconciliation jobs, customer payment UI. This phase only persists the attempts and the dedup foundation.

**Reason:** Persists server-authoritative, provider-agnostic payment attempts and a durable webhook-dedup identity foundation for Group H, while keeping `payment_status` contract-safe (`SUCCEEDED → PAID`, non-terminal statuses never exposed) so Group H cannot emit internal enum values against the frozen CLOSED contract.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_11_100000_create_payments_table.php`, `database/migrations/2026_09_11_110000_create_payment_webhook_events_table.php`, `app/Models/{Payment,PaymentWebhookEvent,Order}.php`, `app/Support/{PaymentStatus,PaymentWebhookProcessingStatus,DiagnosticText}.php`, `database/factories/{PaymentFactory,PaymentWebhookEventFactory}.php`, `tests/Feature/{PaymentSchemaTest,PaymentWebhookEventSchemaTest}.php`), `phases/group-C-phases.md`, `docs/decisions.md`

### ADR/BACKEND-018 — Phase 3.13 Delivery Schema

**Decision:** Order delivery execution is persisted in a dedicated `deliveries` table as an operational record for a `DELIVERY`-type Order. It holds only delivery-execution data; the Order remains authoritative for ownership, reference, financials, fulfillment type, recipient/address snapshot, and overall status, and `order_status_history` remains authoritative for tracking.

- **Schema `deliveries`:** `id`, `order_id` FK `restrictOnDelete` + `unique` (one Delivery per Delivery Order in V1; no multiple active records, no attempt history), `recipient_name` (255), `recipient_phone` (50), `delivery_address` JSON (approved structured snapshot), nullable `delivery_instructions` (bounded, private, plain text), `created_at`/`updated_at`. No `schedule_for` column — V1 has no approved delivery-appointment workflow (phase §11). No `delivery_status`, no `delivery_status_history`, no `tracking_number`/`tracking_url`, no `carrier*`, no GPS/live-tracking fields, no `driver_id`/`assigned_staff_id`/`delivery_agent_id`, no delivery fee/`total` (stays on Order), no payment/inventory/product fields, no `saved_address_id`/`customer_address_id`.
- **Eligibility is a domain invariant, not a column:** `Delivery` carries no `fulfillment_type`; `assertEligibleOrder()` validates `Order.fulfillment_type === DELIVERY` at creation via the Order (`Order::query()->find($this->order_id)`), rejecting Pickup Orders with `DomainException`. `UNIQUE(order_id)` additionally prevents duplicate rows.
- **Snapshot immutability:** `recipient_name`, `recipient_phone`, `delivery_address`, and `order_id` are `isDirty`-guarded immutable after creation (`Delivery {field} is immutable once recorded.`). No model observers sync from User profile / saved address book; later profile changes never rewrite the delivery recipient/address (historical-snapshot principle, same as Order snapshots). `delivery_instructions` is mutable/optional; blank → `null`.
- **Address validation:** new `app/Support/AddressField` allow-list enforces the approved Checkout/Order address structure (`address_line`, `city`, `region`, `postal_code` nullable). Unknown keys → `DomainException` (`Delivery address contains unsupported fields.`); required keys must be non-empty strings. The Delivery row is not a general-purpose JSON blob.
- **No status machine:** Delivery has no `status`, no `shipped_at`/`delivered_at`/`completed_at` (those dates live in `order_status_history.occurred_at`), no observers that mutate Order status, and no soft deletes. Order transition workflow (later) is the only path that changes Order lifecycle; Delivery persistence stays subordinate to it.
- **Deletion rules:** `order_id` FK uses `restrictOnDelete` — deleting an Order is blocked while its Delivery (and Payment) records exist; Delivery is never cascade-deleted. Deleting a User does not delete the Order or Delivery.
- **Privacy/authorization:** Delivery data (recipient, phone, address, instructions) is private — never public, never CDN-cached, `Cache-Control: private, no-store` for protected responses, authorize before serialization. Customer access only via own Order context; no public `GET /deliveries/{delivery}` resource (prevents IDOR/enumeration). Staff access is operational, permission-based, minimal necessary; no generic Delivery CRUD path bypasses Order authorization.
- **Mass assignment:** no `$request->all() → Delivery::create()`; later workflow uses `validated input → DTO/command → domain workflow → trusted persistence`, and client input never controls `order_id`, ownership, timestamps, Order status, or financial fields.
- **Concurrency/idempotency:** `UNIQUE(order_id)` is the DB-level duplicate guard; delivery creation may later join critical checkout/fulfillment transactions atomically (authorize + state + persist), but no Delivery-specific idempotency mechanism is added here — `Idempotency-Key` belongs to the later action workflow.
- **Models/factory:** `Delivery` (`belongsTo Order`, `delivery_address` array cast) and `Order::delivery()` `hasOne`. `DeliveryFactory` defaults to a valid `DELIVERY` order (`Order::factory()->deliveryFinalized()`), snapshots recipient/address from that order; `forOrder(Order)` and `withInstructions()` states. No carrier/GPS/tracking fixture data.
- **Tests:** `tests/Feature/DeliverySchemaTest.php` (25 tests) — migration columns, no-status/carrier/GPS/tracking/payment/inventory/product/fee columns, `order_id` FK required + unique, relationship both directions, DELIVERY-order eligibility, Pickup rejection, snapshot independence from User profile, address allow-list (unknown keys rejected, required fields), recipient/instructions bounds + blank→null, snapshot immutability, restricted Order delete, User-delete retention, `order_id` non-cascade, order separation.
- **Out of scope (later phases):** delivery scheduling/dispatch workflows, courier/carrier integration, driver assignment, GPS/live tracking/ETA, tracking number/URL, delivery attempts/history, proof of delivery/signature/photos, delivery notifications, delivery APIs/UI, automated scheduling jobs, and any Order status transition logic.

**Reason:** Establishes a minimal, private, one-to-one operational Delivery record for the delivery fulfillment path without creating a second Order state machine, a second tracking source, or a speculative logistics/carrier domain — keeping `order_status_history` as the single tracking authority and the Order as the single financial/lifecycle authority.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_12_100000_create_deliveries_table.php`, `app/Models/{Delivery,Order}.php`, `app/Support/AddressField.php`, `database/factories/DeliveryFactory.php`, `tests/Feature/DeliverySchemaTest.php`), `phases/group-C-phases.md`, `docs/decisions.md`

### ADR/BACKEND-019 — Phase 3.14 Furniture Request Schema

**Decision:** Furniture Requests are persisted in a single self-contained `furniture_requests` table serving both registered-customer and guest submissions. A request is an independent customer-intent record — it is not an Order, does not reserve inventory, does not create a payment or price commitment, and never auto-converts to an Order.

- **Schema `furniture_requests`:** `id`, nullable `user_id` FK `nullOnDelete` (authenticated customer owner, else `null` for guests — no `guest_user`/`customer_type`/`is_guest` columns), unique server-generated `request_reference` (`REQ-`+10), nullable `product_id` FK `nullOnDelete` (optional catalog reference), nullable JSON `product_details` (submitted snapshot, allow-listed `product_name`/`description`/`reference`, validated only when present), nullable `style` free text, required `name` + nullable `email`/`phone` contact snapshots, nullable `message` text, nullable `quantity` (1..100, request intent not inventory), nullable JSON `dimensions` (`length`/`width`/`height` ≤10000 + `unit:"cm"`), nullable `material`/`color` free text, `request_status` default `SUBMITTED`, nullable `staff_internal_notes`, timestamps. Indexes: unique `request_reference`, `user_id`, `(request_status, created_at)`, `product_id`, `created_at`. No `order_id`, no payment fields, no inventory fields, no delivery fields, no quoted price/amount/currency.
- **Ownership model:** one table for both origins; `user_id` is server-derived (authenticated principle or `null`). Client never supplies `user_id`. Contact snapshots (`name`/`email`/`phone`) are request-time copies stored independently of the User profile and never rewritten by later profile changes.
- **Contact validation (frozen-contract compatible):** the phase schema stores all three contact channels, but the model enforces the **frozen V1 contract**: `name` required + at least one of `email`/`phone` (not both mandatory). Making all four (`name`+`email`+`phone`+`message`) mandatory at the public API boundary requires a deliberate Group J reconciliation with the frozen contract — not a Phase 3.14 schema change.
- **Field reconciliation vs frozen REQ-001 (recorded deliberate Group-J mapping — Phase 1.35 freeze / Rule 12 compatibility event):** the frozen `REQ-001` contract (`api-contract.md §26.3/line 3128`, `api-resources.md §5.1`, `api-examples.md §10.1`) defines exactly `product_id, quantity, name, phone, email, dimensions, material, color, notes` (+ optional attachment), where **`notes` is Optional (max 5000)** and there is **no** `style`, **no** `product_details`, and **no** `message`. The Phase 3.14 schema deviates from that frozen surface in three ways that must be reconciled deliberately in Group J before any client depends on the schema:
  1. **`message` ⇄ contract `notes`:** the schema's nullable `message` column (`assertMessage` validates only when present, max 5000) is the persistence home for the contract's `notes` free-text field. Group J must map the public `notes` input to the `message` column (or rename the column); making `notes`/`message` required at the public API boundary would be an optional→required compatibility event and must go through the post-freeze contract-change process — it must not be introduced silently. The schema as built accepts a frozen request with no notes.
  2. **`product_details` (new, optional):** no frozen counterpart. Group J must define the public input field (or derive it from the linked `product_id`/`product_reference` snapshot) and its requiredness, since the frozen contract carries only `product_id` + `notes`. The schema stores it only when supplied, so a frozen request without it persists.
  3. **`style` (new, optional):** no frozen counterpart. Group J must introduce a public `style` input (or fold it into `notes`) and reconcile requiredness with the frozen contract; making it required is a compatibility event under the post-freeze process.
  These three are recorded here as a deliberate Group-J reconciliation, **not** as an implicit Phase 3.14 contract change. Group J must either (a) expose the schema fields under the frozen public names (`notes` for `message`; `style`/`product_details` as additive optional fields or folded into `notes`) or (b) run the documented post-freeze contract-change process before making them mandatory. The schema stores the richer structure so Group J can choose the mapping without a migration; it does not authorize emitting `message`/`style`/`product_details` verbatim against the frozen contract.
- **Product reference:** optional; both Product-linked (`product_id` set) and custom/general (`product_id = null`) modes approved. `MADE_TO_ORDER`/active/published/%requestable eligibility is **Group J domain validation** — the `products` table has no `product_type`/`is_published` columns yet, so the FK alone does not enforce requestability (`IN_STOCK` product must not become a request target). Deleting a Product sets `product_id = null` while preserving `product_details` and all submitted fields.
- **Product details & dimensions are validated structured objects:** `RequestField` allow-lists reject unknown keys (`anything`, `internal_price`, `secret`, `depth`, `diameter`, `radius`, etc.). The DB stores validated JSON; Laravel/domain validation is authoritative for nested structure. Never automatically filled from the live Product model.
- **Status:** closed `RequestStatus` enum `SUBMITTED`/`IN_REVIEW`/`CLOSED`, default `SUBMITTED`; `CLOSED` terminal. `request_status` is server-controlled; speculative statuses (`QUOTED`/`APPROVED`/`REJECTED`/`PRODUCING`/`DELIVERING`) are forbidden. Staff transitions are Group J.
- **Historical intake immutability:** `user_id`, `request_reference`, `product_id`, `product_details`, `style`, `name`, `email`, `phone`, `message`, `quantity`, `dimensions`, `material`, `color` are `isDirty`-guarded immutable after submission (`Request {field} is immutable once submitted.`). `staff_internal_notes` is the separate mutable Staff/Admin-only field (bounded, never customer-serialized); `request_status` changes only via Group J controlled transitions.
- **Deletion semantics:** `user_id` and `product_id` both `nullOnDelete` — deleting a User or Product never destroys historical requests; the contact/spec snapshots survive. No cascade into `furniture_requests`.
- **No hard-duplicate constraint:** repeated email/phone/message combinations are permitted; duplicate/abuse handling belongs to the idempotency/rate-limiting/application layers, not a uniqueness assumption. No Request-specific idempotency column.
- **Privacy/authorization:** request data (contact, specs, message, notes) is private — never in public/catalog/SEO responses or unauthenticated listing endpoints; no public `GET /requests/{id}` retrieval (IDOR/email-as-auth prevented); customer retrieval only via own-request authorization, Staff access operational by permission, Admin broad-but-audited. `$model->toArray()` is not an API response; Group J uses explicit serialization allow-lists.
- **Models/factory:** `FurnitureRequest` (`belongsTo User` nullable, `belongsTo Product` nullable; casts `product_details`/`dimensions` arrays + `request_status` enum + `quantity` integer; no Order/Payment/Delivery/Inventory relationships), `User::furnitureRequests()` and `Product::furnitureRequests()` hasMany. `FurnitureRequestFactory` supports guest/auth/product-linked/custom/dimensions/material+color/all-specs `SUBMITTED`/`IN_REVIEW`/`CLOSED` states; never creates Order/Payment/Delivery.
- **Tests:** `tests/Feature/FurnitureRequestSchemaTest.php` (61 tests) — migration columns (no order/payment/inventory/delivery/guest-type columns), guest+authenticated ownership, request↔user/product relationships both directions, `request_reference` format/uniqueness/immutability, Product/User delete → `nullOnDelete` retention, contact-snapshot persistence + `name`-required + `email`/`phone`-at-least-one + only-email/only-phone, style/message optional+bounded (null permitted, blank rejected, whitespace-trimmed boundary tests), product_details/dimensions allow-list validation (unknown keys, unit `cm`, range, nested-value types), quantity range, material/color optional, status default + closed enum + arbitrary rejected, intake-field immutability, historical request untouched by profile/product changes, duplicate contact+message allowed, staff notes separation.
- **Out of scope (Group J):** `POST /requests`, Form Requests/DTOs/commands, authentication/authorization policies, rate limiting/CAPTCHA, status-transition endpoints, staff/customer/anonymous retrieval APIs, attachment upload implementation, notifications, quotation/pricing, payment, Order conversion, inventory reservation, production/delivery workflows, full-text search.

**Reason:** Establishes a private, self-contained, historical Furniture Request persistence that serves both guest and authenticated submissions, preserves submitted product/contact/spec detail independent of later catalog/profile changes, and leaves MADE_TO_ORDER eligibility, strict validation, authorization, and lifecycle to Group J — without leaking Order, payment, inventory, or delivery semantics into the request domain.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_13_100000_create_furniture_requests_table.php`, `app/Models/{FurnitureRequest,User,Product}.php`, `app/Support/{RequestStatus,RequestField}.php`, `database/factories/FurnitureRequestFactory.php`, `tests/Feature/FurnitureRequestSchemaTest.php`), `phases/group-C-phases.md`, `docs/decisions.md`

---

### ADR/BACKEND-020 — Phase 3.17 Schema Integrity Review

**Decision:** Review-and-harden pass over the Phase 3.1–3.16 schema. Two corrective migrations, one portability fix to two prior downs, model/test alignment for product archival, and a 41-test referential-integrity suite. No domain redesign, no contract change.

- **M1 `2026_09_14_100000_add_soft_deletes_to_products_table`:** adds approved `products.deleted_at` (`phases/group-C-phases.md §2.6/§6.1`) + `deleted_at` index; `Product` uses `SoftDeletes`. Operational deletion is now archival: `delete()` hides the product and retains variants/images/cart links (cart revalidation flags them later); `forceDelete()` is the hard path and fires `CASCADE` (variants, images) / `RESTRICT` (cart items) / `SET NULL` (order snapshots, requests, enquiries) exactly as the §19 matrix requires. `ProductSchemaTest` split into archive + hard-delete proofs; `Enquiry`/`FurnitureRequest`/`OrderItem` null-on-delete tests moved to `forceDelete()` (soft delete preserves the link row, which is the archival point).
- **M2 `2026_09_14_110000_harden_schema_checks_phase_3_17`:** database enforcement (MySQL `CHECK`, sqlite triggers) for already-agreed local invariants: `categories.display_order >= 0` (plus matching `Category` model guard), `category_recommendations` self-recommendation rejection, `carts.status ∈ {ACTIVE,INACTIVE}`, `cart_items.quantity 1..100`, `furniture_requests.quantity NULL|1..100`, `product_stocks.warehouse_location` non-blank, `orders` fulfillment/fee/total combos per `ADR/API-ORD-001` + `Order::assertValid` (PICKUP ⇒ FINALIZED/0/total=subtotal; DELIVERY+PENDING ⇒ fee NULL/total=subtotal provisional; DELIVERY+FINALIZED ⇒ fee ≥ 0/total=subtotal+fee).
- **Rollback portability:** `DROP CHECK` (MySQL 8 syntax) fails on MariaDB, which requires `DROP CONSTRAINT`. New `dropCheck()` helper (try `DROP CHECK`, fall back to `DROP CONSTRAINT`) applied in M2 and back-patched into the two prior CHECK migrations (`..._add_money_pair_checks...`, `..._enforce_order_item_line_total...`). `migrate:fresh` + `migrate:reset` verified clean on MariaDB 11.8.8 (scratch DB, zero tables remaining).
- **FK/delete matrix (as built):** profiles→users CASCADE (profile never deletes user); category parent `SET NULL`; product→category RESTRICT; variant→product CASCADE; image→product CASCADE; image→variant `SET NULL`; stock→variant CASCADE; cart→user RESTRICT-equivalent (see V3); cart item→cart CASCADE; cart item→product/variant RESTRICT; order→customer RESTRICT; order item→order CASCADE; order item→product/variant `SET NULL`; history→order CASCADE; history→actor `SET NULL`; payment→order RESTRICT; webhook→payment `SET NULL` (events survive); delivery→order RESTRICT + UNIQUE; request→user/product `SET NULL`; enquiry→user/product `SET NULL`, enquiry→order RESTRICT; notification→recipient RESTRICT.
- **Unique review:** email, category/product slug, `sku_prefix` (retained intentionally, V5), variant SKU, guest digest, recommendation pair, one-active-cart guard, order/payment/request/enquiry references, `(provider, provider_transaction_id)` nullable-safe, `(provider, provider_event_id)`, delivery `order_id`, RBAC composite PKs. No convenience uniqueness added.
- **Index review:** added `products.deleted_at`; otherwise indexes match V1 query shapes (customer order history, staff status queues, cart owner/status, timeline `occurred_at,id`, inbox `created_at DESC,id ASC`). Overlapping indexes kept deliberately (`cart_items (cart_id,product_id)` vs `(cart_id,variant_id)` serve different lookups; notification recipient/created vs recipient/read/created serve inbox vs unread counts).
- **Invariant register:** A (DB): FK existence, all uniqueness above, non-negative money/quantities, line-total formula, money-pair null-together, cart XOR, single default/primary guards, self-rec rejection, order fee combos. B (DB+app): reserved≤quantity, one active cart, variant/image/cart-variant product consistency, pickup/delivery eligibility. C (app/domain only): ownership/authorization, state transitions, payment→order effects, request eligibility, notification derivation, concurrency control.
- **Tests:** `tests/Feature/SchemaIntegrityTest.php` (41 tests) — FK rejection, every delete-policy row, uniqueness, DB-check violations via raw writes, snapshot survival under hard delete, cart XOR/bounds, product archival. Full suite **633 tests pass**; Pint clean; PHPStan level 5 clean.
- **Known variances (no silent redesign):** V1 `products.product_type`/`is_published` absent though the frozen catalog contract references them — deferred to Group E catalog availability (Phase 5.7), eligibility stays Group J domain validation. V2 corrected during review: `phases/group-C-phases.md §5.2` briefly required `source_category_id`, but the implemented schema (migration, `Category` relations, seeder, tests) unanimously uses `category_id` — phase text fixed to `category_id`/`(category_id, recommended_category_id)`; no rename, no migration change. V3 `carts.user_id` FK is RESTRICT on MySQL but `nullOnDelete` on sqlite — preservation holds on both (sqlite via XOR guard abort, proven by test); unify when the account-retention policy lands. V4 corrected during review: `phases/group-C-phases.md §11` briefly stated `total_amount = NULL` for pending delivery, contradicting the frozen contract (`api-contract.md §24`, `ADR/API-ORD-001`: provisional `total = subtotal`, never payable while `PENDING`) — phase text fixed to `total_amount = subtotal_amount` with the provisional note; the database check already encoded the contract rule. V5 `products.sku_prefix` UNIQUE retained as product-line identity, not convenience uniqueness. V6 fixed during review: `enquiries.category` (frozen contract CLOSED nullable `GENERAL|PRODUCT|DELIVERY|OTHER` — `openapi.yaml`, `api-contract.md §27`, `api-resources.md`, `api-conventions.md §27.5`) was missing from the schema — added via `2026_09_14_120000_add_category_to_enquiries_table` (`enum`, nullable, after `phone`) plus `App\Support\EnquiryCategory`, model (PHPDoc/Fillable/cast/validation/immutability), factory, and 5 schema tests; enforced natively on MariaDB and via enum CHECK on sqlite.

**Reason:** Closes Group C with a referentially safe, migration-rebuildable schema aligned to the frozen V1 contract, with every cross-driver gap either fixed or explicitly recorded.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/migrations/2026_09_14_*.php`, `database/migrations/2026_09_09_141000_*`, `database/migrations/2026_09_10_151000_*`, `app/Models/{Product,Category}.php`, `tests/Feature/{SchemaIntegrityTest,ProductSchemaTest,EnquirySchemaTest,FurnitureRequestSchemaTest,OrderItemSchemaTest}.php`), `docs/decisions.md`

---

### ADR/BACKEND-021 — Phase 3.18 Factories and Seed Data

**Decision:** Complete the factory layer and add a production-safe seeder architecture. No schema or API contract changes.

- **New factories:** `CategoryFactory` (kebab slug, valid `space_type`, `inactive()` state; parents via `for($parent, 'parent')`), `CustomerProfileFactory`, `StaffProfileFactory`; `HasFactory` added to the three models. No factory or model for `category_recommendations` — it is pivot-style storage managed through the `Category` relations and `CategorySeeder`, covered by `CategoryRecommendationTest`; a dedicated factory would be artificial.
- **User states:** `customer()` / `staff()` / `admin()` assign the CLOSED role (created on demand, permissions still owned exclusively by `RbacSeeder`) and the matching profile (`ADMIN` shares `staff_profiles` per `ADR/BACKEND-007`). Role assignment inside states is test-data building only, never an authorization path.
- **New states:** `ProductStock::outOfStock()`. All other §23 states already existed (`active/inactive/featured/asDefault/guest/customer/pickup/deliveryPending/deliveryFinalized/read/unread`, etc.).
- **Lazy definitions:** `OrderItemFactory` no longer eagerly creates a throwaway product (lazy `Product::factory()`, synthetic name) — the eager create littered orphan rows in seeded databases; explicit snapshot attributes remain the norm.
- **Seeders:** `DatabaseSeeder` is production-safe reference data only (`RbacSeeder` + `CategorySeeder`); the old automatic `test@example.com` user was removed. New explicit local-only `DemoSeeder` runs `CategorySeeder → DevelopmentUserSeeder → CatalogSeeder → CommerceDemoSeeder` in dependency order. `DevelopmentUserSeeder` creates customer/staff/admin (`customer/staff/admin@example.com`) with an env-configured password (`config/demo.php` + `SEED_DEMO_PASSWORD` in `.env.example`, documented local-only; random generated and printed once when unset, no committed credential). `CatalogSeeder` is deterministic (fixed slugs/SKUs/prices, skip-if-present): featured multi-variant sofa with primary/ordered/variant images and multi-location stock, zero-stock single-variant dining table, inactive imageless armchair. `CommerceDemoSeeder` (fresh-DB only, skips when orders exist) seeds guest/active/historic carts, pickup-completed + delivery-completed (payment succeeded, webhook processed, delivery row) + pending + cancelled orders with coherent items/totals/timelines, guest/auth requests, guest/auth/product/order enquiries, and read/unread notifications. No inventory-reservation, payment-processing, or workflow simulation — rows only.
- **Production safety:** `db:seed` never creates demo users or commerce data; `DemoSeeder` requires explicit `--class=DemoSeeder`. No real credentials, customer data, or provider secrets anywhere in seeds.
- **Tests:** `tests/Feature/SeedDataTest.php` (18 tests) — factory validity per domain, role/profile states, hierarchy, catalog graph, inventory bounds, cart paths, order totals, timeline coherence, delivery/payment matching, reseed idempotency (roles/permissions/taxonomy stable), default-seeder user absence, full demo graph coherence, production refusal, unknown order-state rejection, paid-payment matching, demo skip-on-rerun.
- **Verification:** full suite **693 tests (692 passed, 1 intentional MySQL-only skip)**; Pint clean; PHPStan level 5 clean. MariaDB scratch cycle: `migrate:fresh` → `db:seed` → repeat `db:seed` (3 roles / 17 permissions / 76 categories / 20 recommendations stable) → `DemoSeeder` (exact counts: 3 users, 3 products, 5 variants, 4 orders, 5 items, 16 history events, 2 payments — one succeeded payment per PAID order, 1 delivery, 3 carts, 0 orphans).

**Reason:** Gives local development, API work, and frontend integration a one-command realistic dataset while keeping production seeding to idempotent reference data and leaving all workflows to their domain phases.

**Status:** Accepted | **Affected:** `backend/laravel` (`database/factories/{Category,CustomerProfile,StaffProfile}Factory.php` new, `{User,ProductStock,OrderItem}Factory.php`, `database/seeders/{Database,DevelopmentUser,Catalog,CommerceDemo,Demo}Seeder.php`, `config/demo.php`, `.env.example`, `app/Models/{Category,CustomerProfile,StaffProfile}.php`, `tests/Feature/SeedDataTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-022 — Phase 3.19 Migration Test (Group C Exit Gate)

**Decision:** The Group C database rebuilds from migration history alone and meets the exit condition. No migration, factory, seeder, or contract changes were required — the one new file is a verification suite.

- **New suite `tests/Feature/MigrationRebuildTest.php` (35 tests):** all 22 domain tables exist with primary keys; 3.17 corrections present (`products.deleted_at`, `enquiries.category`, guard columns); soft deletes only on `products`; `order_status_history` has no `updated_at`; every migration file has an applied row; all 29 FKs carry the §19 delete action (verified via `Schema::getForeignKeys`, driver-normalized); every domain table carries exactly the expected FK count (no strays, none missing).
- **MySQL rebuild (MariaDB 11.8.8, scratch DB, FK checks never disabled):** cycle 1 `migrate:fresh` → `db:seed` → `DemoSeeder` (exact counts: 3 users, 3 products, 5 variants, 4 orders, 5 items, 16 history, 2 payments, 1 delivery, 3 carts, 0 orphans); cycle 2 from a dropped database reproduced identical counts; `migrate:reset` (31 downs) + `migrate` (31 ups) clean, including the MariaDB `DROP CONSTRAINT` fallback. Reference reseed stable (3 roles / 17 permissions / 76 categories / 20 recommendations).
- **Constraint proof on MySQL:** duplicate slug/SKU/reference/digest/webhook/delivery rejections hold; CHECKs hold (self-recommendation rejected live); `CHECK` inventory present in `INFORMATION_SCHEMA`.
- **Index sanity (EXPLAIN):** product-by-slug `const` via unique; variants-by-product, orders-by-customer, history-by-order all `ref` via their composite indexes; notification inbox `ref` via `(recipient, created_at)`.
- **Suite results:** sqlite canonical suite **690/690 pass**; supplementary full MySQL run **681/690** with 9 failures classified as test-harness/sqlite assumptions, zero schema defects: 2× raw `PRAGMA index_list` (sqlite-only SQL), 3× pcntl-fork concurrency tests (`MySQL server has gone away` in forked children), 1× direct table-drop while children reference it (FK protection working as designed), 2× raw `DROP CHECK` inside test helpers (MySQL-8-only syntax; the migration `down()` itself is portable), 1× lowercase enum accepted by MySQL's case-insensitive enum collation (app layer owns CLOSED validation via native casts; reads return the canonical member — Group K admin writes must validate before attach).
- **History integrity (§13):** 31 migration files in chronological dependency order, one responsibility each, no hardcoded paths, no production data, seeds create no schema.
- **Security (§17):** no secrets in migrations/seeds; demo credentials env-configured and local-only; `db:seed` creates no users; demo data requires explicit `--class=DemoSeeder`.
- **API compatibility (§16):** untouched — enums, integer-minor-unit money, nullability, and ownership rules unchanged; frozen V1 behavior preserved.
- **Known risks (§23):** MariaDB requires `DROP CONSTRAINT` where MySQL 8 uses `DROP CHECK` (handled via fallback); carts `user_id` delete action differs per driver (restrict vs set-null, preservation proven both); MySQL enums match case-insensitively (see above); `products.product_type`/`is_published` remain deferred to Group E per `ADR/BACKEND-020` V1.
- **Group C exit:** contract → domain → migrations → FKs → constraints → indexes → factories → seeds → fresh rebuild → integration tests all agree. **Database schema accurately represents the agreed domain and can be rebuilt from migrations.**

**Status:** Accepted | **Affected:** `backend/laravel` (`tests/Feature/MigrationRebuildTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-023 — Phase 5.5 Search Regression Mitigations

**Decision:** Keep product search driver-aware. MySQL/MariaDB uses native FULLTEXT for `products.name` and `products.description`; SQLite remains a semantic-test fallback and must not be treated as FULLTEXT coverage. Variant attribute searches use `json_extract()` on SQLite and `JSON_UNQUOTE(JSON_EXTRACT())` on MySQL/MariaDB. LIKE escape clauses must use a single-character escape marker (`'\\'`), and search validation permits an explicitly empty or null search value so whitespace-only input behaves as an unfiltered request after trimming.

- **Regression fixed:** SQLite does not provide MySQL's `JSON_UNQUOTE()` function. Using the MySQL expression in the SQLite fallback caused CAT-001 requests to return HTTP 500.
- **Regression fixed:** SQLite requires the `LIKE ... ESCAPE` expression to contain exactly one character. Over-escaped SQL produced a database error during pagination/count queries.
- **Regression fixed:** URL-encoded whitespace search input was rejected before the query could normalize it. Empty/null search values are now accepted and normalized to no search predicate.
- **Regression fixed:** Relationship search now requires `product_variants.is_active = true`; inactive variants cannot make an active product discoverable by SKU or attribute.
- **Regression fixed:** SQLite product name/description fallback uses bound raw predicates with an explicit single-character `ESCAPE '\\'` clause so literal `%`, `_`, and backslash characters remain literal search input.
- **Required coverage:** Keep SQLite tests focused on application semantics, add a separate MySQL/MariaDB integration test for FULLTEXT behavior where infrastructure permits, and run the full backend suite after query-builder changes.

**Reason:** The catalog query is shared across database drivers and pagination executes a separate count query. Driver-specific JSON functions and escaping rules therefore need explicit handling and regression coverage rather than relying on SQL portability assumptions.

**Status:** Accepted | **Affected:** `backend/laravel/app/Queries/ProductCatalogQuery.php`, `backend/laravel/app/Http/Requests/ProductIndexRequest.php`, `backend/laravel/tests/Feature/ProductReadApiTest.php`, `docs/decisions.md`

---

### ADR/BACKEND-024 — Phase 5.6 Catalog Pagination and Sorting

**Decision:** CAT-001 uses Laravel's `LengthAwarePaginator` through the existing Product catalog query. The shared pipeline remains `search → filters → allow-listed sort → id ASC tie-breaker → paginate`, with `page` defaulting to `1`, `per_page` defaulting to `20`, and a hard maximum of `100`. The response exposes only the frozen `meta.pagination` fields. Requests beyond the last page return an empty `data` array while reporting the last valid page as `current_page`; empty result sets report `last_page = 1` and both navigation flags as false.

- Allowed primary sorts remain `created_at`, `price`, and `name`.
- Default sort remains `created_at DESC, id ASC`; explicit `name` and `price` sorts default to ascending unless overridden.
- Price sorting reuses the active-variant minimum-price subquery used by summary serialization and price filtering.
- Relationship search continues to use `EXISTS`-style `whereHas`, requiring active variants, so matching multiple variants neither duplicates products nor inflates paginator totals.
- No cursor pagination, pagination links, schema changes, dependencies, frontend changes, or new sort modes were introduced.

**Reason:** The existing CAT-001 query and controller already used SQL pagination and the frozen response envelope. Phase 5.6 hardens edge behavior and regression coverage without creating a second pagination abstraction or changing the public contract.

**Status:** Accepted | **Affected:** `backend/laravel/app/Http/Controllers/Api/V1/ProductController.php`, `backend/laravel/tests/Feature/ProductReadApiTest.php`, `docs/decisions.md`

---

### ADR/BACKEND-025 — FULLTEXT Index Migration Operational Safety

**Decision:** The MySQL/MariaDB FULLTEXT migration is a schema-changing operation and must not be treated as zero-downtime. Laravel's `Schema::table()` declaration produces `ALTER TABLE ... ADD FULLTEXT INDEX`; index creation may hold metadata/table locks and block reads or writes while the index is built, especially on a populated `products` table.

Before applying this migration to a populated environment:

- rehearse the migration against a production-sized staging copy using the same MySQL/MariaDB version and storage configuration;
- take or verify a restorable database backup;
- schedule a maintenance or low-traffic window and announce possible catalog read/write interruption;
- monitor migration duration, metadata locks, database load, and application errors;
- verify the index exists and run the CAT-001 search smoke checks before reopening normal traffic;
- stop and use a database-specific online-DDL procedure only if the measured lock impact is unacceptable.

The migration remains unchanged and intentionally does not embed `ALGORITHM=INPLACE`, `LOCK=NONE`, or vendor-specific SQL because those options differ between MySQL and MariaDB and are not guaranteed for every table/storage/version combination. A future zero-downtime requirement needs a separate, tested deployment procedure rather than an unverified migration option.

**Status:** Accepted | **Affected:** `backend/laravel/database/migrations/2026_09_20_120000_add_fulltext_index_to_products_table.php`, `docs/decisions.md`

---

### ADR/BACKEND-026 — Catalog Product Availability Authority

**Decision:** Product publication is independent from operational activity. Public catalog visibility requires an active, published, non-deleted Product and an active Category. The new Product columns are backfilled so existing active products remain visible; new factory products are published by default for compatibility with existing public-read tests, while draft state is explicit.

`IN_STOCK` availability is derived from the sum of `quantity - reserved_quantity` across active variants and all stock locations. Zero or negative quantity is unavailable; positive quantity is available, with `LOW_STOCK` at five or fewer units. `MADE_TO_ORDER` products are always available and use the `MADE_TO_ORDER` indicator regardless of stock rows. The API exposes `product_type`, `availability`, and `stock_indicator`, but never raw inventory fields.

**Status:** Accepted | **Affected:** Product catalog model, query, resources, migration, factory, seeder, and catalog tests

---

### ADR/BACKEND-027 — Inventory Read Model (Phase 5.8)

**Decision:** `INV-001 GET /api/v1/inventory` and `INV-002 GET /api/v1/inventory/{inventory}` expose the current operational state of the existing `product_stocks` rows, read-only. No schema, mutation, reservation, or frontend change. Both endpoints require an authenticated Laravel-local identity resolved through the existing Clerk boundary plus the `inventory.view` permission; roles alone never authorize. Anonymous requests return `401 AUTHENTICATION_REQUIRED`; authenticated CUSTOMER returns `403 FORBIDDEN`.

- **Resource identity:** one `ProductStock` row = one Inventory resource = one Variant at one `warehouse_location`, matching Group C `UNIQUE(product_variant_id, warehouse_location)`. `{inventory}` is the stable opaque Inventory ID (`inv_...`, new `InventoryIdentifier`) and is never reinterpreted as a Product slug/ID, Variant SKU, or warehouse name. Unknown or cross-resource identifiers return `404 RESOURCE_NOT_FOUND`.
- **Representation (`InventoryResource`):** `{id, product_id, variant_id, warehouse_location, quantity, reserved_quantity, available_quantity, updated_at}`. `product_id` is derived `ProductStock → ProductVariant → Product`; `variant_id` is derived from the Variant FK with the public opaque encoding (never raw `product_variant_id`); `available_quantity` reuses the authoritative `ProductStock::available_quantity` accessor (`quantity - reserved_quantity`), never persisted or client-accepted; `created_at` is intentionally omitted. Full Product/Variant objects are not embedded.
- **Collection:** paginated `page`/`per_page` (default `20`, max `100`) with the frozen `meta.pagination` envelope; deterministic ordering `updated_at DESC, id ASC`; empty and beyond-last-page requests return `200` with an empty `data` array and valid metadata. Minimal allow-listed filters `product` (`prod_...`/numeric/slug), `variant` (`var_...` only; SKU rejected), `warehouse_location` (exact). Unknown parameters and arbitrary columns/operators are rejected `422`; CAT-001 `availability`/full-text semantics are not reused.
- **Scope separation:** operational reads are not scoped by public catalog visibility. Zero-stock rows and stock attached to inactive, unpublished, or soft-deleted Products remain visible for reconciliation (`ProductVariant.product` eager-loaded with `withTrashed()`), while CAT endpoints keep exposing only `availability`/`stock_indicator`.
- **Performance:** bounded eager loading (`productVariant.product`) prevents N+1 across collection rows; no locks, transactions, or `SELECT ... FOR UPDATE` are used for reads. GET never mutates stock.
- **Cache:** `Cache-Control: private, no-store` + `Vary: Authorization`; never public/CDN-cacheable. No audit event is emitted for reads.
- **Contract reconciliation:** corrected the ambiguous `INV-002 "by product/variant"` wording to "by Inventory resource ID"; made `Inventory.variant_id` required/non-null; added `warehouse_location` to the operational Inventory representation; documented INV-001 filters/pagination and 422/404 semantics in `api-contract.md §30.5.1.1`, `api-resources.md §10.2.1`, and `openapi.yaml`. INV-003 (`POST /inventory/{inventory}/adjust`) was implemented in Phase 5.9 (`ADR/BACKEND-028`), which superseded the stale `{product}` route wording.
- **Tests:** `tests/Feature/InventoryReadApiTest.php` (18 tests) — authentication, CUSTOMER/STAFF-without-permission/ADMIN authorization, collection envelope and field allow-list, pagination + empty/beyond-last, strict filter/pagination validation, product/variant/location filters, exact detail, cross-resource 404, multi-location distinctness, derived/zero/fully-reserved quantities, inactive/unpublished/soft-deleted visibility, private cache + no mutation, public no-leakage regression, and N+1 guard.

**Reason:** Phase 5.8 completes the read-only operational inventory surface over the authoritative Group C `product_stocks` model without introducing a second inventory table, warehouse entity, adjustment logic, or public leakage.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Http/Controllers/Api/V1/InventoryController.php`, `app/Http/Requests/InventoryIndexRequest.php`, `app/Http/Resources/InventoryResource.php`, `app/Support/InventoryIdentifier.php`, `app/Models/{ProductStock,ProductVariant}.php`, `tests/Feature/InventoryReadApiTest.php`), `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/openapi.yaml`, `docs/decisions.md`

---

### ADR/BACKEND-028 — Inventory Mutation Rules (Phase 5.9)

**Decision:** `INV-003` is one controlled, permission-gated, idempotent, audited action: `POST /api/v1/inventory/{inventory}/adjust`. The stale `{product}` path is superseded (no alias kept) because a Product does not identify one quantity row; the Inventory resource ID does. The action changes `ProductStock.quantity` only.

- **Authorization:** authenticated Laravel-local identity through the Clerk boundary + `inventory.manage` permission; CUSTOMER and STAFF-without-permission → `403 FORBIDDEN`; anonymous → `401 AUTHENTICATION_REQUIRED`. Rate limited by the existing `throttle:inventory-adjust` (20/min/staff).
- **Input:** exactly `quantity_delta` (required strict non-zero integer) and `reason` (required CLOSED `InventoryAdjustmentReason`); unknown fields (`quantity`, `reserved_quantity`, `available_quantity`, `product_id`, `variant_id`, `warehouse_location`, `actor_id`, …) → `422 INVALID_VALUE`. `Idempotency-Key` header required (UUID): missing → `422 MISSING_REQUIRED_FIELD`, malformed → `422 INVALID_FORMAT`.
- **Reason/direction clarification:** `STOCK_RECEIPT`/`RETURN` require `delta > 0`; `DAMAGE` requires `delta < 0`; `CORRECTION`/`AUDIT_ADJUSTMENT` accept either non-zero sign; `quantity_delta = 0` and contradictory intent are `422 INVALID_VALUE` (never silently rewritten). Unknown reason → `422 INVALID_VALUE` `field: reason`.
- **Invariants:** server computes `new_quantity = current_quantity + quantity_delta`; `new_quantity >= 0` and `new_quantity >= reserved_quantity` are mandatory, with no clamping. `reserved_quantity`, `warehouse_location` and Variant/Product ownership are never modified; `available_quantity` stays derived. No upsert: unknown Inventory → `404 RESOURCE_NOT_FOUND`, missing rows are never auto-created, zero-stock rows are retained. Inactive/unpublished/soft-deleted Product or inactive Variant stock may still be reconciled operationally.
- **Idempotency (shared infrastructure):** new durable `idempotency_keys` table + `App\Services\IdempotencyService`, scoped by `(actor_id, action, key_hash)` with a 24h expiry (`config/idempotency.php`, `IDEMPOTENCY_RETENTION_HOURS`) per ADR/API-SEC-006. Keys are stored hashed, never raw. Same scoped key + same intent fingerprint replays the stored `200` body (no second delta, no second audit); same scoped key + different intent → `409 DUPLICATE_OPERATION`; reuse by another actor is a new scope.
- **Audit (shared infrastructure):** new append-only `audit_events` table + `App\Services\AuditRecorder`. Every successful adjustment writes one event (`actor_id`, `actor_role`, `action = INVENTORY_ADJUSTED`, `resource_type = inventory`, `resource_id`, `previous_state`, `resulting_state`, `occurred_at`, `request_id`) inside the same transaction; the actor is server-derived. `actor_id` is a `restrictOnDelete` FK (not `SET NULL`/`CASCADE`) so deleting an actor can never rewrite historical audit attribution. Audit failure rolls back the quantity change. `GET /admin/audit-logs` (`ADM-007`) remains internal-only V1 (stub, no `audit.view` permission added here).
- **Errors/response:** `200` returns the reconciled Phase 5.8 `InventoryResource` (`Cache-Control: private, no-store`, `Vary: Authorization`); errors reuse the CLOSED registry; `DUPLICATE_OPERATION` and `RESOURCE_VERSION_CONFLICT` were added to `App\Support\ApiErrorCode` (already in the CLOSED contract registry).
- **Concurrency:** Phase 5.9 runs each adjustment atomically inside a `lockForUpdate` transaction, so concurrent **independent** adjustments (distinct `Idempotency-Key`) are serialized and both succeed (`100→110→120`, no spurious `409`); `409` is only idempotency conflict or an explicit version precondition. Phase 5.10 still owns adjust-vs-checkout reservation/consumption interaction, overselling prevention, lost-update/versioning, and deadlock/retry policy.
- **Schema:** no `ProductStock` change; the only new tables are the shared `idempotency_keys` and `audit_events`.

**Reason:** Implements the frozen `ADR/STAFF-005`/api-contract `§30.5`/`§30.18` controlled-adjustment contract with the mandatory durable audit (`ADR/STAFF-008`) and the existing identity-scoped idempotency decision, without pulling checkout reservation or the Phase 5.10 concurrency mechanism forward.

**Status:** Accepted | **Affected:** `backend/laravel` (`routes/api.php`, `app/Http/Controllers/Api/V1/InventoryController.php`, `app/Http/Requests/AdjustInventoryRequest.php`, `app/Services/{InventoryAdjustmentService,IdempotencyService,IdempotentOutcome,AuditRecorder}.php`, `app/Models/{IdempotencyKey,AuditEvent}.php`, `app/Support/{ApiErrorCode,InventoryAdjustmentReason,AuditAction,AuditResourceType}.php`, `config/idempotency.php`, `database/migrations/2026_09_22_12000{0,1}_*`, `tests/Feature/InventoryAdjustmentApiTest.php`), `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/api-conventions.md`, `docs/api/openapi.yaml`, `docs/decisions.md`

---

### ADR/BACKEND-029 — Inventory Concurrency & Reservation Allocation (Phase 5.10)

**Decision:** Inventory correctness under concurrency is owned by MySQL/MariaDB InnoDB row locking inside short transactions. `ProductStock` remains authoritative; `available_quantity` stays derived; no reservation table replaces `reserved_quantity`. A reusable checkout-facing primitive (`App\Services\Inventory\InventoryAllocator`) provides `reserve`/`release`/`consume`, and exact multi-location reservation allocation is persisted so release/consume can target the same rows.

- **Locking:** every inventory mutation uses `DB::transaction` + `lockForUpdate`. Deterministic order: `Order` row → `ProductStock` rows `id ASC`; multi-variant operations sort unique variant ids ascending before locking. No Redis/distributed locks, no table locks, no `lock_version`, no global mutex, no queue-based reservation.
- **Reservation:** `reserve(order)` locks candidate rows, validates `requested <= available` against locked state, and holds stock via `reserved_quantity += q` (physical `quantity` unchanged). All-or-nothing across items; failure raises `422 INSUFFICIENT_STOCK` and rolls back everything.
- **Multi-location allocation:** a Variant may have several `ProductStock` rows; reserve allocates deterministically by `warehouse_location ASC, id ASC` until satisfied and may span locations. Customer never selects a location.
- **Allocation persistence (user-flagged correctness gap):** aggregate `reserved_quantity` cannot identify which row supplied which units, so reservations persist an internal `order_item_inventory_allocations` table (`order_item_id`, `product_stock_id`, `quantity`, `UNIQUE(order_item_id, product_stock_id)`, FKs `cascadeOnDelete` on the item and `restrictOnDelete` on the stock row). Release/consume read these rows to hit the exact same rows and then delete them; a repeated business retry is therefore a safe inventory-layer no-op, while the owning order/idempotency state remains the idempotency authority. The table is server-created, never customer-exposed, and is explicitly not a stock ledger or transfer subsystem. This is introduced because V1 advertises aggregate availability across locations and the checkout contract already requires reserve/release/consume; a single-location-only rule was rejected as inconsistent with the catalog.
- **Release:** `reserved_quantity -= q` per allocation, physical `quantity` unchanged (public availability increases); never allows `reserved_quantity < 0`.
- **Consumption:** `quantity -= q` and `reserved_quantity -= q` per allocation, so `available_quantity` is unchanged; never double-applied.
- **Adjust interaction:** INV-003 shares the same locks, so `adjust` vs `reserve`/`release`/`consume` races serialize against locked state; an adjustment can never drive `quantity < reserved_quantity` (active holds protected).
- **Bounded retries:** transient conflicts (`DeadlockException`, `40001`, lock wait `1205`/`1213`, MariaDB `ER_RECORD_CHANGED` `1020`) are retried up to a small bounded count with a jittered backoff inside a rollback-safe transaction; exhaustion maps to `409 CONFLICT`. No SQLSTATE/table/lock internals leak. No public reserve/release/consume endpoints; cart still does not reserve.
- **Schema:** `ProductStock` unchanged (`quantity`/`reserved_quantity` only). One narrowly scoped migration: `2026_09_22_130000_create_order_item_inventory_allocations_table` (with explicit short index/FK names for MariaDB's 64-char identifier limit).
- **Verification:** SQLite proves formulas, invariants, rollback, allocation, and single-threaded release/consume (10 feature tests). True concurrency was verified against disposable MariaDB 11.8.8 (`furnitureapp_test_disposable`) with independent fork processes/connections: reserve-vs-reserve last unit, adjust-vs-adjust lost update, adjust-vs-reserve invariant, and same-Idempotency-Key race, each repeated 10×. SQLite is explicitly not treated as concurrency proof.

**Reason:** Closes the Phase 5.10 exit gate without redistributing inventory authority or pulling checkout/payment scope forward, while fixing the multi-location allocation traceability gap required for correct later release/consumption.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Inventory/InventoryAllocator.php`, `app/Support/{ConcurrentTransaction,ApiErrorCode}.php`, `app/Models/OrderItemInventoryAllocation.php`, `database/migrations/2026_09_22_130000_create_order_item_inventory_allocations_table.php`, `tests/Feature/InventoryReservationTest.php`, `tests/Integration/InventoryConcurrencyMysqlTest.php`), `docs/api/api-contract.md`, `docs/decisions.md`

---

### ADR/BACKEND-030 — Group E Catalog & Inventory Closure (Phase 5.11)

**Decision:** Group E is closed after consolidating regression coverage and running the mandatory driver-specific gates. No new features, schema, endpoints, or dependencies were introduced. The phase surfaced and fixed one genuine MySQL/MariaDB defect.

- **Bug found & fixed (Group E defect, Phase 5.5 class):** on MySQL/MariaDB, any `?search=` request returned `500` because the variant SKU/attribute `LIKE` clause emitted `ESCAPE '\'` (PHP `"\\"` collapses to one backslash; invalid SQL string literal). SQLite was unaffected, so the MySQL branch had never been exercised. `ProductCatalogQuery` now builds the escape clause per driver (`ESCAPE '\'` for SQLite, `ESCAPE '\\'` for MySQL/MariaDB) via `likeEscapeClause()`, with the name/description fallback and variant SKU/attribute searches centralized through it. Regression coverage: `tests/Integration/ProductFulltextSearchMysqlTest.php` (exercises real MySQL search) plus the existing SQLite search suite.
- **Driver split:** SQLite proves request validation, API shapes, authorization, public visibility, filter composition, sorting, pagination, availability formulas, inventory invariants, mutation semantics, and transaction rollback. MySQL/MariaDB proves native FULLTEXT, the FULLTEXT index, and real row-lock concurrency. SQLite is explicitly not concurrency/FULLTEXT proof.
- **Permanent regressions consolidated in `tests/Feature/GroupEContractRegressionTest.php`:** inactive Variant **attribute** (and SKU) matches never surface a Product; exact Group E route surface; rejected routes (`/search`, `/variants`, `/categories/{c}/products`, GET `/products/{p}/images`, `PATCH`/`DELETE /inventory/{inventory}`); `LOW_STOCK` boundaries `0/1/5/6` against `CatalogAvailability::LOW_STOCK_THRESHOLD`; public CAT-001/002/005/006 leak no operational inventory fields.
- **Mandatory gates:** SQLite canonical suite passes; disposable MariaDB FULLTEXT gate passes (`test_fulltext_index_exists_on_products`, name/description match, non-match excluded, public visibility enforced); disposable MariaDB concurrency gate passes (last-unit race, adjust-vs-adjust lost update, adjust-vs-reserve invariant, same-key idempotency; 10 barrier-synchronized iterations each). Disposable DB destroyed after use.
- **Status resolution:** Phase 5.5 is now **PASS** (SQLite semantics + real MySQL FULLTEXT + PHPStan 0 — all prior blockers resolved). Phase 5.10 remains **PASS**. Group E overall **PASS**. PHPStan has no repository baseline remaining (0 errors).
- **Known non-issues preserved:** MySQL/MariaDB enum case-insensitivity is handled by application casts; `carts.user_id` delete-action differs by driver but preservation is proven; Phase 5.5/5.10 were only ever blocked on the disposable MySQL harness, which is now available.

**Reason:** Provides an evidence-based Group E exit that separates SQLite-proven application semantics from MySQL-proven engine behavior, permanently guards the inactive-Variant search rule and concurrency correctness, and records the MySQL search fix without broadening V1.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Queries/ProductCatalogQuery.php`, `tests/Feature/GroupEContractRegressionTest.php`, `tests/Integration/ProductFulltextSearchMysqlTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-031 — Phase 6.1 Cart Model Review

**Decision:** The existing Group C `carts`/`cart_items` persistence model is fit for the frozen V1 Cart contract (`CART-001..005`) without schema redesign. Phase 6.1 reviewed the model, reconciled two small contract mismatches, and deferred all API/workflow implementation to later Group F phases.

- **Existing model:** `carts` (`user_id` nullable FK RESTRICT, `guest_token_digest` char(64) nullable unique, `status` `ACTIVE|INACTIVE`, generated `active_user_guard` unique per active user, timestamps) and `cart_items` (`cart_id` cascade, `product_id`/`variant_id` nullable RESTRICT, `quantity`, generated `null_variant_guard`, `UNIQUE(cart_id, product_id, variant_id)` + `UNIQUE(cart_id, null_variant_guard)`). Ownership XOR (`user_id` xor `guest_token_digest`) is enforced by DB CHECK/triggers and `Cart::assertValid()`; the active-cart guard allows one ACTIVE cart per user while permitting multiple INACTIVE historical carts.
- **Invariants preserved:** one active cart per authenticated user; `ACTIVE`/`INACTIVE` only (no expiry/abandoned/merged states); item identity `product + variant`; quantity `1..100` (domain + DB); no persisted prices/totals/availability/reservation fields; stale lines preserved (FKs are RESTRICT, no cascade deletes).
- **Ownership/auth mapping:** customer carts come from the Clerk-resolved local user; guest carts from a validated guest credential; only `HMAC-SHA-256(raw token, GUEST_CART_TOKEN_KEY)` is persisted; raw token is never stored/logged/serialized (`#[Hidden]`); guest credential authorizes only its bound cart. Contract IDOR defense (`item.cart_id === caller_cart.id`, `CART_ITEM_NOT_FOUND` 404 masking), private cache, and Staff/Admin having zero operational cart endpoints were confirmed.
- **Corrections applied (minimal, allowed by §9/§10 + Rule 17):**
  1. **Guest token wire format (MODEL GAP):** `GuestCartCredential::generate()` returned a 48-char `Str::random()` string, which does not satisfy the frozen OpenAPI `X-Guest-Cart-Id`/`guest_cart_id` `format: uuid` (UUIDv4 CSPRNG ≥122 bits). Now returns `(string) Str::uuid()`; the HMAC digest design is unchanged and `CartSchemaTest` entropy/uniqueness assertions were updated to the UUIDv4 contract.
  2. **Quantity-ceiling drift (MAINTAINABILITY):** the unused `cart.max_item_quantity` config duplicated the authoritative `CartItem::MAX_QUANTITY = 100`; the dead config key was removed so the domain constant is the single source of truth.
- **Group E dependency mapping:** cart display price = current active-variant price (Catalog/ProductCatalogQuery); `availability`/`stock_indicator` = Phase 5.7 `CatalogAvailability`; `is_purchasable` (Phase 6.1 definition) = Product publicly visible (`is_active && is_published && not deleted && category active`) **and** `product_type === IN_STOCK` **and** referenced Variant (when present) active + parent-owned **and** live availability `available`. Cart never re-implements these rules and never reserves; reservation stays at Group G checkout (Phase 5.10 primitive).
- **Deferred implementation gaps (not fixed in 6.1):** guest route topology (`CART-001..004` currently auth-only; guests need `clerk.optional` + guest-token middleware) — 6.2/6.3; Cart/CartItem opaque ID support (`cart_...`/`item_...`) — 6.2; empty-cart = valid `items: []` (never 404) — 6.2; add/update/remove/merge controllers, validation and idempotency (`CART-005` `IDEMPOTENCY_REQUIRED`) — 6.3/6.4/6.5; stale `is_purchasable` projection and stock revalidation — 6.6/6.7; Staff/Admin personal self-commerce authz policy — 6.2.
- **Merge readiness:** `CART-005` is model-compatible with no schema change — duplicate lines merge by `(product_id, variant_id)` capped at 100; the guest source becomes `INACTIVE` while retaining its unique digest (XOR forbids clearing it), so a retired token resolves to no ACTIVE cart and cannot be reused/merged twice.
- **Checkout handoff:** cart remains mutable until checkout; checkout revalidates catalog/price/inventory under lock and, on success, **clears the cart's items while keeping the Cart record `ACTIVE`** (same cart id, empty active cart ready) per the frozen Checkout contract `§23.10`; failure preserves the cart untouched. No schema change required.
- **Schema:** `NONE`. **Dependencies:** `NONE`. **Frontend:** `NONE`.
- **Phase 6.2 readiness:** `READY`. CART-001 semantics are unambiguous: authenticated → the caller's single ACTIVE user cart or a freshly-created empty one; guest token → the bound ACTIVE guest cart; no existing cart → empty ACTIVE cart (`items: []`, `subtotal 0`, never 404); invalid/retired guest token → treated as no guest cart (401 `AUTHENTICATION_REQUIRED` where a guest credential is required); authenticated + guest credential → the authenticated principal's own cart wins for `CART-001` (merge is explicit via `CART-005`).

**Reason:** Confirms Group C Cart persistence already supports the frozen V1 cart behaviours and boundaries, applies only the two small conformance fixes §9/§10 permit, and records the deferred service/route work so Phase 6.2 can begin without schema redesign.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Support/GuestCartCredential.php`, `config/cart.php`, `tests/Feature/CartSchemaTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-032 — Cart Create/Get (CART-001, Phase 6.2)

**Decision:** Implement `GET /api/v1/me/cart` as the canonical holder-scoped read endpoint, with no separate cart-create endpoint and no schema change. Authenticated customer carts remain lazily created. A first-time anonymous read returns the empty Cart representation without persisting a cart or issuing a credential; the first successful `CART-002` mutation creates the guest cart and issues its credential. This security correction prevents unbounded persistent records from anonymous reads. Because the cart does not yet exist, its response is a **transient** empty cart: a non-null, opaque `id` (`cart_...`, derived from that request's guest token and never equal to a persisted id) and a non-null `updated_at` equal to the response time; `items` is `[]` and `subtotal` is `0`. No credential is issued on read, so consecutive first-time reads are independent and may return different transient ids; clients must not rely on the transient id for continuity. The transient handle is superseded by the persisted cart id once the first successful `CART-002` issues the guest credential.

- **Route/auth:** `GET /api/v1/me/cart` now sits behind `clerk.optional` + the private read limiter (was `clerk.auth`), so guests are supported while authenticated bearers still resolve a local user. Invalid attempted bearer is never downgraded to guest (handled by `AuthenticateClerkIfPresent`). CART-002..005 remain auth-only until their own phases.
- **Holder resolution:** `CartHolderResolver` + `GuestCartTransport` resolve `authenticated customer` (from the Clerk-resolved local user) vs `guest` (from the guest credential). Authenticated identity always wins; a supplied guest credential is ignored for ownership and never triggers merge. No `user_id`/`cart_id` input is ever accepted.
- **Guest credential transport:** exactly one transport per interaction — browser `guest_cart_id` cookie (`HttpOnly`, `Secure` (env-aware via `cart.guest_cookie_secure`), `SameSite=None`, session lifetime) or non-browser `X-Guest-Cart-Id` header. Supplying both is rejected `422 INVALID_VALUE`; unknown/retired/malformed credentials return `401 AUTHENTICATION_REQUIRED` (no cart creation under a bad token, no digest-existence disclosure). Only `HMAC-SHA-256(raw, GUEST_CART_TOKEN_KEY)` is persisted; the raw UUIDv4 never enters DB/log/JSON. **Client classification** (browser vs non-browser) is centralized in `GuestCartTransport::isBrowser()`: a request carrying `X-Guest-Cart-Id` is non-browser; otherwise the guest cookie, an `Origin` header, or Fetch Metadata (`Sec-Fetch-Mode`/`Sec-Fetch-Site`/`Sec-Fetch-Dest`) marks a browser (so a first browser GET without `Origin` still receives the HttpOnly cookie); a request with no browser signal defaults to the non-browser header transport. The frozen conventions define the two transports but not a detector; this is the recorded Phase 6.2 assumption. New credentials are issued only for a newly created guest cart; authenticated responses never issue guest credentials.
- **Lazy creation / race:** `GetOrCreateActiveCart` finds the holder's single ACTIVE cart or creates an empty one. The authenticated create relies on the generated `active_user_guard` unique constraint; a `UniqueConstraintViolationException` is recovered by re-reading the winning ACTIVE cart (bounded internal handling, no DB error surfaced). Guest creation uses a fresh unique digest. Verified under real MariaDB concurrency (`tests/Integration/CartConcurrencyMysqlTest.php`, 10 races → one ACTIVE cart, same ID).
- **Representation:** `CartResource`/`CartItemResource` (explicit allow-lists, no `toArray()`): opaque `cart_...`/`item_...` IDs (`CartIdentifier`/`CartItemIdentifier`), `items_count` = distinct lines, `items` ordered `created_at ASC, id ASC`, integer-minor-unit `subtotal` in `TZS`. Item pricing/availability reuse Group E (`CatalogAvailability`) against current catalog state; unit pricing uses **only the item's own active variant** — a missing or inactive variant yields `unit_price`/`line_total` = `null` (`is_purchasable = false`) and contributes nothing to `subtotal`, never a borrowed or fabricated price. `is_purchasable` follows the Phase 6.1 definition (publicly visible product + `IN_STOCK` + active parent-owned variant + live availability). Stale/inactive lines report `availability: unavailable` with the Product-derived `stock_indicator` (never a fabricated `IN_STOCK`), and the embedded `product.price` is the product's current catalog price (cheapest active variant, or null). `openapi.yaml` `CartItem.unit_price`/`line_total` allow `null` for this stale/unpriceable case. Stale/soft-deleted/inactive/out-of-stock lines are preserved and flagged, never deleted; `user_id`, `guest_token_digest`, `active_user_guard`, `cart_id` never leak.
- **Read-only:** GET is observational except lazy creation; it never reserves stock, locks `ProductStock`, mutates cart/item timestamps, or persists prices/availability. Response is `Cache-Control: private, no-cache, no-store, must-revalidate`.
- **OpenAPI reconciliation:** CART-001 security made explicitly optional (`bearerAuth` + anonymous), added `401`/`422`, documented the `Set-Cookie`/`X-Guest-Cart-Id` issuance headers and the `guest_cart_id` cookie parameter. Phase 6.1 froze the guest token as UUIDv4.
- **Schema:** `NONE`. **Dependencies:** `NONE`. **Frontend:** `NONE`.
- **Readiness:** Phase 6.3 (add item) is READY — the holder resolver, get/create service, resources, transport, and opaque-ID codecs are reusable by later cart mutations.

**Reason:** Establishes the foundational cart access path with explicit holder/transport security and correct lazy-create concurrency, without pulling add/update/remove/merge, checkout, or frontend work forward.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Cart/{CartHolder,CartHolderResolver,GuestCartTransport,GetOrCreateActiveCart}.php`, `app/Support/{CartIdentifier,CartItemIdentifier}.php`, `app/Http/Resources/{CartResource,CartItemResource}.php`, `app/Http/Controllers/Api/V1/CartController.php`, `app/Models/{Cart,CartItem,ProductVariant}.php`, `routes/api.php`, `config/cart.php`, `tests/Feature/{CartReadApiTest,ApiRoutingSmokeTest}.php`, `tests/Integration/CartConcurrencyMysqlTest.php`), `docs/api/openapi.yaml`, `docs/decisions.md`

---

### ADR/BACKEND-033 — Cart Item Mutations CART-002/003/004 (Combined Phases 6.3–6.5)

**Decision:** Implement the ordinary cart item lifecycle over the Phase 6.2 holder boundary for authenticated customers and credential-bound guests: `POST /me/cart/items`, `PATCH /me/cart/items/{item}`, `DELETE /me/cart/items/{item}`. No schema change, no dependency, no frontend, no checkout/reservation, and CART-005 merge stays unimplemented.

- **Holder/routing:** all three routes moved to `clerk.optional` (guest-capable) with the existing limiters (`throttle:cart-add` for add, `throttle:authenticated-write` for update/remove). Authenticated identity still wins over any supplied guest credential; invalid bearer never downgrades to guest. `CART-002` lazily creates the holder's active cart (issuing a new guest credential for a first-time guest); `CART-003/004` require an existing active cart and never create one (guest with an unknown/retired credential → `401`, otherwise masked `404`).
- **Add (CART-002):** strict allow-list `product_id` (`prod_...`), `variant_id` (`var_...`, **conditionally required**: required when the Product defines variants, since V1 is variant-priced; omitted only for a no-variant Product, which is rejected `PRODUCT_NOT_PURCHASABLE`), `quantity` (strict integer `1..100`); unknown/server-controlled fields rejected `422`. Admission is resolved **before** lazy cart creation, and if the subsequent mutation fails the just-created, unreachable guest cart (no credential was issued) is discarded — no orphan carts on validation/stock failure. Shared `CartItemAdmission` enforces Product visible (`is_active`, `is_published`, not trashed, active category), `IN_STOCK` (MADE_TO_ORDER → `PRODUCT_NOT_PURCHASABLE`), a Product that actually has a Variant (no-variant Product → `PRODUCT_NOT_PURCHASABLE`, never misreported as out of stock), and variant ownership/activity (`INVALID_PRODUCT_VARIANT`); otherwise `PRODUCT_UNAVAILABLE`. Duplicate `(product_id, variant_id)` additions **merge** into one line and are **clamped to `CartItem::MAX_QUANTITY` (100)** per accepted `ADR/API-CART-005` (`min(existing + requested, 100)`), returning `200` (new line `201`). Informational stock check uses the resulting quantity against Group E aggregate availability via the shared `CartStockGuard` (`CatalogAvailability::availableQuantity`) → `INSUFFICIENT_STOCK`. No reservation, no `ProductStock` lock.
- **Update (CART-003):** body is `quantity` only (`1..100`; `0`/`>100`/non-strict rejected), unknown/immutable fields rejected. Item resolved strictly inside the holder's active cart; cross-holder/unknown → masked `404 CART_ITEM_NOT_FOUND`. Re-runs admission + stock revalidation against the current catalog/stock state (stale line → canonical error; still removable). Same quantity is an idempotent no-op (no timestamp change); a real change touches `CartItem.updated_at`/`Cart.updated_at`.
- **Remove (CART-004):** bodyless; a request body carrying fields is rejected `422 INVALID_VALUE` before any removal (an empty/`[]`/`{}` body is accepted), preserving the frozen no-body contract; holder-scoped resolution with masked `404`; deletes the line with no stock validation and no inventory effect, so stale lines (inactive/unpublished/soft-deleted/out-of-stock/MADE_TO_ORDER) are always removable; removing the last line leaves an empty `ACTIVE` cart; touches `Cart.updated_at`.
- **Concurrency:** add uses a transaction with `lockForUpdate` on the existing line; absent-line races recover from the `cart_items` unique constraints (and MariaDB `ER_RECORD_CHANGED`/deadlock) via the shared bounded retry (`ConcurrentTransaction`) — no raw duplicate-key/deadlock surfaces. Update/remove lock the affected line. No `ProductStock` locks anywhere.
- **Projection/security:** responses reuse `CartResource`/`CartItemResource` (server-derived current pricing/availability, stale-line signaling, opaque `cart_...`/`item_...` IDs, private `no-cache, no-store, must-revalidate`). No ownership/credential/raw-inventory leakage; guest credential transport unchanged and never rotated by ordinary mutations; no auto-merge.
- **Verification:** SQLite proves validation, admission, ownership/IDOR masking, quantity + clamp semantics, pricing, availability, no-reservation, response shapes, stale preservation, timestamps (`CartAddItemApiTest` 15, `CartUpdateItemApiTest` 8, `CartRemoveItemApiTest` 6). MariaDB proves duplicate/first-add merging, overflow clamp, and same-line lost-update protection (`CartMutationConcurrencyMysqlTest`, 4 tests × 10 races). OpenAPI reconciled (optional security + `guest_cart_id` cookie param on CART-002/003/004, CART-002 `200` merge + issuance headers). **Schema:** NONE. **Dependencies:** NONE. **Frontend:** NONE.
- **Readiness:** Phase 6.6 (cart validation) is READY; the shared admission/stock/timestamp rules are centralized for consolidation.

**Reason:** Completes the ordinary cart item lifecycle with the accepted duplicate-merge/clamp and zero-reservation boundaries, reusing the Phase 6.2 holder/projection infrastructure without redesigning the frozen contract.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Cart/{CartItemAdmission,AddCartItem,UpdateCartItemQuantity,RemoveCartItem,GetOrCreateActiveCart}.php`, `app/Http/Requests/{AddCartItemRequest,UpdateCartItemRequest}.php`, `app/Http/Controllers/Api/V1/CartController.php`, `app/Support/{ApiErrorCode,CatalogAvailability,CartItemIdentifier}.php`, `app/Models/CartItem.php`, `routes/api.php`, `tests/Concerns/CartTestSupport.php`, `tests/Feature/{CartAddItemApiTest,CartUpdateItemApiTest,CartRemoveItemApiTest,ApiRoutingSmokeTest}.php`, `tests/Integration/CartMutationConcurrencyMysqlTest.php`), `docs/api/openapi.yaml`, `docs/decisions.md`

---

### ADR/BACKEND-034 — Cart Validation Consolidation & Quantity-Aware Purchasability (Phase 6.6)

**Decision:** There is one authoritative Cart-domain eligibility implementation. `CartItemEligibility` (`app/Services/Cart/`) is the sole rule source and every Product/Variant/quantity/stock rule lives there; it returns an immutable `CartItemValidationResult` (`isPurchasable`, `availability`, `stockIndicator`, internal `reason`). Internal reasons are a non-serialized `CartItemInvalidReason` enum whose `toApiException()` is the single error mapping. Admission/mutation and projection compose those same rules — no rule is re-implemented outside `CartItemEligibility`. Composition boundary: admission/mutation evaluate only the Product/Variant half (`requirementReason`) before/around the transaction and the stock half (`stockReason`) against the **effective** quantity inside it (so inventory is not queried when an earlier rule already invalidates the request); projection composes both halves in one `evaluate()`. No schema change, no dependency, no frontend, no new endpoint, no reservation.

- **Three explicit contexts:** (A) **admission** (`CART-002`) — the shared Product/Variant gate rejects with the mapped `422`; (B) **mutation** (`CART-003`) — the same gate rejects; (C) **projection** (`CART-001` and post-mutation rendering) — the same evaluation never fails the read; a stale line is retained with `is_purchasable = false`. (`CART-004` intentionally bypasses purchasability and remains holder-scoped only.)
- **Validation ordering:** Product existence → visible (`is_active`/`is_published`/not soft-deleted/active Category) → type `IN_STOCK` → Variant requirement (a Product with no sellable Variant → `PRODUCT_NOT_PURCHASABLE`) → Variant existence/ownership/activity → effective-quantity stock sufficiency. Inventory is never queried once an earlier Product/Variant rule already invalidates the request.
- **Quantity-aware `is_purchasable` (defect fixed):** `is_purchasable` now requires the **entire Cart line quantity** to be currently satisfiable:
  `is_purchasable = Product visible AND product_type = IN_STOCK AND Variant valid/active AND live_available_quantity >= CartItem.quantity`.
  Coarse Group E `availability`/`stock_indicator` are unchanged and independent: a line with `quantity = 5` and `available = 2` reports `availability = "available"` (stock > 0) but `is_purchasable = false`. `LOW_STOCK` is informational only and does not by itself block purchasability.
- **Quantity & duplicate rules unchanged:** bounds `1..CartItem::MAX_QUANTITY (100)` via `CartItem::MAX_QUANTITY` only; duplicate add merges/clamps `min(existing + requested, 100)`; direct `PATCH 101` stays rejected. Admission performs Product/Variant checks first; stock is checked against the **effective** resulting quantity inside the mutation transaction.
- **Error mapping (frozen codes):** `MADE_TO_ORDER`/no-variant → `422 PRODUCT_NOT_PURCHASABLE`; inactive/unpublished/soft-deleted/inactive-Category/unknown → `422 PRODUCT_UNAVAILABLE`; missing/inactive/wrong-parent Variant → `422 INVALID_PRODUCT_VARIANT`; insufficient stock → `422 INSUFFICIENT_STOCK`. Same failure maps to the same code across `CART-002` and `CART-003`.
- **Inventory boundary:** validation reads Group E aggregate availability (`CatalogAvailability`) but performs **no** `ProductStock` mutation, **no** `reserved_quantity` mutation, **no** `ProductStock` lock, and **no** reservation. Cart stock checks are informational/advisory; Checkout stays authoritative. Stale lines are never silently deleted or quantity-reduced; they remain visible, flag `is_purchasable = false`, and are always removable.
- **Verification:** `tests/Feature/CartPurchasabilityApiTest.php` (11 tests: quantity-aware partial stock, exact/one-less boundary, reserved stock, multi-location aggregation, LOW_STOCK informational, stock recovery, product reactivation, MADE_TO_ORDER/unpublished/inactive-category projection, no-persistence-on-read, shared admission/mutation error mapping) and `tests/Unit/CartItemInvalidReasonTest.php` (13 mappings). Existing `CartAddItemApiTest`/`CartUpdateItemApiTest`/`CartRemoveItemApiTest`/`CartReadApiTest` and the MariaDB `CartMutationConcurrencyMysqlTest` remain green. **Schema:** NONE. **Dependencies:** NONE. **Frontend:** NONE.
- **Readiness:** Phase 6.7 (stock revalidation) is READY; it can reuse `CartItemEligibility`/`CartItemValidationResult` without redefining Product/Variant rules.

**Reason:** Eliminates validation drift between add/update/read by centralizing the rules in one component, and corrects the earlier projection defect where coarse `availability == available` was incorrectly used as a substitute for line-level purchasability — a line can no longer appear checkout-ready when its full quantity cannot be satisfied.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Cart/{CartItemEligibility,CartItemValidationResult,CartItemInvalidReason,CartItemAdmission,CartStockGuard,UpdateCartItemQuantity}.php`, `app/Http/Resources/CartItemResource.php`, `tests/Feature/CartPurchasabilityApiTest.php`, `tests/Unit/CartItemInvalidReasonTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-035 — Cart-Wide Live Stock Revalidation (Phase 6.7)

**Decision:** Cart projection evaluates every line against live Group E inventory through one read-only, Cart-wide orchestrator. `CartStockRevalidator::revalidate(Cart)` loads the Cart's inventory relations in bounded eager batches and evaluates each line with the single `CartItemEligibility` authority, returning a `CartStockRevalidationResult` (internal map of internal CartItem id → `CartItemValidationResult`). `CartController::present()` runs it once and threads results into `CartItemResource` (via `withValidation()`), so the Resource serializes a precomputed result instead of querying stock in `toArray()`. `CartResource` wires the per-item results. No new endpoint, field, error code, schema, dependency, or frontend change; `CART-002/003/004` semantics are unchanged and `CART-004` triggers no revalidation (204).

- **Reuse, not re-definition:** Product/Variant/quantity rules and the `Product/Variant → stock` ordering stay in `CartItemEligibility`; availability arithmetic stays in Group E `CatalogAvailability` (`quantity - reserved_quantity`, per Variant across its locations). The revalidator owns only orchestration + batched loading — it re-implements no rule and no SQL formula.
- **Quantity-aware:** line purchasability remains `Product visible AND IN_STOCK AND Variant valid/active AND live_available >= CartItem.quantity`; coarse `availability` stays quantity-independent (`available` while `available > 0`), `LOW_STOCK` stays informational, and `stock_indicator` is unchanged.
- **Variant-specific:** stock is summed across locations of the line's own Variant only; sibling Variant inventory is never borrowed to satisfy a line, and inactive-Variant inventory cannot make a line purchasable (requirement rules precede stock).
- **Eligibility-first batching / N+1:** `CartStockRevalidator` resolves the Product/Variant rules first, then batch-loads live stock only for eligible line variants (their own variants — sibling variants are not loaded) and, for rejected lines that need a Product-level `stock_indicator`, primes `summary_available_quantity` from the existing catalog aggregate (`ProductCatalogQuery::addSummaryAggregates`) instead of loading sibling-variant stock. `variant->product` is primed before evaluation so the evaluator never lazy-loads a Product per line. `CartController::loadCart()` no longer eager-loads `items.product.variants.stocks`/`items.variant.stocks`. Both the `products` and `product_stocks` query counts are constant in the line count, verified by query-scaling and per-table regression tests. No new `availableQuantities()` SQL API was added; Group E remains the single arithmetic authority.
- **Read-only / boundary:** revalidation performs no Cart/CartItem/ProductStock mutation, no `reserved_quantity` mutation, no `ProductStock` lock, and no reservation; it is a point-in-time observation, never a checkout guarantee. Group G still locks and revalidates transactionally. Invalid Product/Variant lines short-circuit before the quantity comparison; empty Carts perform no inventory work; unexpected inventory failures surface through normal error handling (never converted to `INSUFFICIENT_STOCK` or `available = 0`).
- **Verification:** `tests/Feature/CartStockRevalidationTest.php` (15 tests: empty-Cart no stock queries, per-line mixed purchasability, stock drop/recovery, reservation created/released, multi-location aggregation, sibling-Variant isolation, invalid-line short-circuit, LOW_STOCK enough/insufficient, no-persistence/no-reservation, bounded stock-query growth, no per-line Product lazy load, eligibility-only batched stock targeting, MADE_TO_ORDER no-stock) and `tests/Unit/CartStockRevalidationResultTest.php` (internal-id keying). Phase 6.6 suites and the MariaDB mutation concurrency gate remain green. **Schema:** NONE. **Dependencies:** NONE. **Frontend:** NONE.
- **Readiness:** Phase 6.8 (cart tests) is READY.

**Reason:** Makes Cart projection reflect current live stock consistently across add/update/read without N+1 query growth, while preserving the hard boundary that only Group G checkout may lock/reserve inventory.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Cart/{CartStockRevalidator,CartStockRevalidationResult}.php` new, `app/Services/Cart/CartItemEligibility.php`, `app/Http/Controllers/Api/V1/CartController.php`, `app/Http/Resources/{CartResource,CartItemResource}.php`, `tests/Feature/CartStockRevalidationTest.php`, `tests/Unit/CartStockRevalidationResultTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-036 — Guest Cart Merge CART-005 & Group F Closure (Phase 6.8)

**Decision:** Implement the frozen `POST /api/v1/me/cart/merge` (CART-005) as the smallest contract-compliant guest→authenticated merge and close Phase Group F. The route already lives under `clerk.auth` + `throttle:authenticated-write`; the controller resolves the source from the guest credential transport, wraps the merge in the shared durable idempotency store, and returns the authenticated target cart via the standard projection. No schema change, no dependency, no frontend, no new endpoint/field/error code, no checkout.

- **Authentication/transport:** merge requires an authenticated Clerk principal **and** a guest credential via exactly one transport (HttpOnly `guest_cart_id` cookie for browsers, `X-Guest-Cart-Id` header for Flutter) reusing `GuestCartTransport`; ambiguous transport → `422 INVALID_VALUE`, malformed → `401`, missing → `422 MISSING_REQUIRED_FIELD`. A guest token alone (`401`) and an invalid bearer (`401`, never downgraded) cannot merge. `Idempotency-Key` is mandatory (`422 MISSING_REQUIRED_FIELD`/`INVALID_FORMAT`), validated by the extracted `IdempotencyKeyHeader` support (also used by inventory adjust).
- **Target/source:** target is always the authenticated holder's own ACTIVE cart via `GetOrCreateActiveCart` (lazy create allowed); the source must be an ACTIVE guest cart matching the server-derived digest. Unknown/retired/invalid digest → `401 AUTHENTICATION_REQUIRED` (no existence disclosure, no cart creation).
- **Consolidation:** guest lines are merged as intent only — **no CART-002 admission and no stock validation** — so stale guest lines survive and are flagged by the normal projection. Overlap is consolidated by `(product_id, variant_id)` to `min(target + guest, CartItem::MAX_QUANTITY)`; non-overlap is copied; source items are preserved (never hard-deleted). No pricing/availability/reservation is persisted, no `ProductStock` mutation/lock/reservation occurs.
- **Retirement:** source cart `ACTIVE → INACTIVE` with `guest_token_digest` preserved (ownership XOR invariant intact); its raw credential can no longer resolve an ACTIVE guest cart or be recycled. Target `updated_at` and changed/new target lines are touched.
- **Idempotency replay ordering (critical):** the guest credential transport is validated and the safe digest derived **before** consulting the durable store, and the fingerprint is `{source_guest_digest}` scoped by `(actor, action, key)`. `IdempotencyService::execute` returns any completed matching record without invoking the merge closure, so a legitimate same-key retry **replays the recorded 200 Cart even after the source is INACTIVE** (no `401`, no re-merge, no duplicate retirement). Same key + different source → `409 DUPLICATE_OPERATION`; keys are identity-scoped (no cross-user replay).
- **Concurrency/atomicity:** merge runs inside the idempotency transaction (`ConcurrentTransaction`); source and target cart rows are locked in deterministic id order, then affected target lines are locked. The whole effect (validate source, consolidate, retire source, record success) is atomic — failure leaves no partial merge and no success record. `GetOrCreateActiveCart`'s `active_user_guard` race recovery now uses a locking (current) read, so a target-cart creation race inside a transaction resolves instead of returning a stale snapshot.
- **Projection reuse:** `CartProjection` centralizes `loadCart` + `CartStockRevalidator` + `CartResource`; both the ordinary Cart responses and the merge response use it, keeping the N+1-safe eligibility-first path and live pricing/stock. Responses stay `private, no-cache, no-store, must-revalidate` and never expose `guest_cart_id`/`guest_token_digest`.
- **OpenAPI:** reconciled documentation drift — CART-005 now lists `GuestCartCookie` (browser merge) alongside `GuestCartId` and `IdempotencyKey`.
- **Verification:** SQLite `CartMergeApiTest` (20 tests: auth/credential/idempotency-key validation, ambiguous/unknown transport, copy, overlap+clamp, variant separation, empty source, stale preservation, no inventory effect, **same-key replay after retirement**, different-source conflict, per-user key scope, browser cookie, lazy target, retired-token rejection, no credential leakage, full guest→authenticated journey). MariaDB `CartMergeConcurrencyMysqlTest` (3 tests: same-key exactly-once, different-key same-source single effect, overlapping target uniqueness). Existing CART-001/002/003/004, purchasability, stock-revalidation and mutation-concurrency gates remain green. **Schema:** NONE. **Dependencies:** NONE. **Frontend:** NONE.
- **Group F:** all of CART-001..005 implemented; exit condition (a customer can maintain a valid cart entirely through the API, including guest→authenticated merge) demonstrably satisfied.

**Reason:** Completes the Cart lifecycle and the Group F→Group G handoff without turning merge into checkout: merge copies intent and retires the source, while stock/pricing stay live and advisory and reservation remains Group G's authority. Consulting idempotency before source resolution is what lets a valid retry replay after the guest credential is permanently retired.

**Status:** Accepted | **Affected:** `backend/laravel` (`app/Services/Cart/{MergeGuestCart,CartProjection}.php` new, `app/Services/Cart/GetOrCreateActiveCart.php`, `app/Support/IdempotencyKeyHeader.php` new, `app/Http/Controllers/Api/V1/{CartController,InventoryController}.php`, `tests/Feature/CartMergeApiTest.php`, `tests/Integration/CartMergeConcurrencyMysqlTest.php`), `docs/api/openapi.yaml`, `docs/decisions.md`

---

### ADR/BACKEND-037 — Pickup Fulfillment State Boundary (Phase 7.3)

**Decision:** Represent the normalized PICKUP branch with the pure immutable `PickupFulfillmentState`. It accepts only the exact `PICKUP` fulfillment type and an absent or `null` delivery address. The branch produces `delivery_address=null`, `billing_address=null`, zero `TZS` delivery fee, and `delivery_fee_status=FINALIZED`. For an authoritative subtotal, its total relationship is `total=subtotal`, while the later canonical totals implementation remains Phase 7.6-owned.

PICKUP order projection remains `PENDING_PAYMENT` with `payment=null`; finalized delivery-fee state does not mean payment occurred. The state boundary performs no Order insert, Cart mutation, inventory query/reservation, idempotency operation, Delivery-row creation, pickup-location selection, payment call, or Checkout route activation. Client-controlled fee, total, currency, status, billing-address, and pickup-location fields are rejected at this branch boundary; complete transport/schema validation remains Phase 7.8-owned.

The response and persistence representations are deliberately separate. `orderProjectionForSubtotal()` is API-facing and exposes money objects as `subtotal`, `delivery_fee`, and `total`; it must not be passed directly to an `Order` model. `orderPersistenceAttributesForSubtotal()` provides scalar model attributes (`subtotal_amount`, `delivery_fee_amount`, and `total_amount`) for a later checkout workflow, performs no write itself, and is covered by a unit-level mapping assertion.

**Reason:** Establishes one deterministic branch result for later Checkout composition without creating a partial PICKUP-only checkout or bypassing the Group G transaction and Group H payment boundaries.

**Status:** Accepted | **Affected:** `backend/laravel/app/Services/Checkout/PickupFulfillmentState.php`, `backend/laravel/tests/Unit/PickupFulfillmentStateTest.php`, `docs/decisions.md`

---

### ADR/BACKEND-038 — Delivery Fulfillment State and Billing Snapshot Model Gap (Phase 7.4)

**Decision:** Represent the normalized DELIVERY branch with the pure immutable `DeliveryFulfillmentState`. It requires the exact `DELIVERY` enum and all four canonical address fields (`recipient_name`, `phone`, `address_line`, `city`), trims them, copies the normalized address into an independent billing snapshot, and produces `delivery_fee=null`, `delivery_fee_status=PENDING`, `total=subtotal` provisionally, `status=PENDING_PAYMENT`, and `payment=null`. It performs no Order insert, inventory reservation, Delivery-row creation, fee lookup, idempotency operation, or payment call.

The current `orders` schema has no `billing_address` column and the repository has no `OrderAddress` model/table. Therefore the DELIVERY branch can prepare both immutable snapshots and the current Order's scalar financial/address attributes, but cannot yet persist the required billing snapshot without a model/schema decision. This is recorded as a **MODEL GAP**; no dummy mapping into `delivery_address` and no unapproved migration is introduced in Phase 7.4.

**Reason:** Preserves the frozen V1 copy semantics and Model B pending-fee state while preventing an incomplete checkout implementation from silently losing billing history.

**Status:** Blocked by model gap | **Affected:** `backend/laravel/app/Services/Checkout/DeliveryFulfillmentState.php`, `backend/laravel/tests/Unit/DeliveryFulfillmentStateTest.php`, `backend/laravel/database/migrations/2026_09_10_140000_create_orders_table.php`, `docs/decisions.md`

---

### ADR/API-FRZ-002 — Post-Freeze Cart Contract Change: Line Cap, Mutation Concurrency, and Bodyless Merge

**Change Request:** The Cart Operation security review raised externally visible cart hardening: (1) cap a cart at `Cart::MAX_ITEMS = 100` distinct `(product_id, variant_id)` lines on `CART-002`/`CART-005`; (2) require `CART-002/003/004` mutations to operate only on a still-`ACTIVE` holder cart (`409 CONFLICT` otherwise); (3) reject non-empty request bodies on the bodyless `CART-005` merge (`422 INVALID_VALUE`). Raised under the formal Post-Freeze Change Process of `ADR/API-FRZ-001`.

**Impact Analysis:**
- Conforming Next.js/Flutter clients never rely on unbounded distinct cart lines, on writing to a retired cart, or on sending a body to a bodyless action. Normal add/update/remove/merge flows are unchanged.
- Newly rejected shapes are undefined/unsafe inputs rather than supported behavior: a cart exceeding 100 distinct lines; a mutation racing `CART-005` merge on a retired guest cart; a merge carrying a body; a `CART-005` merge that would exceed the cap.
- Affected surface: `CART-002` may return `422 INVALID_VALUE` (line cap) and `409 CONFLICT`; `CART-003/004` may return `409 CONFLICT` and (CART-004) `422 INVALID_VALUE`; `CART-005` may return `409 CONFLICT`/`422 INVALID_VALUE`. No path, method, required field, response field, enum, state machine, or financial rule changes.

**Classification:** **Non-Breaking** compatibility refinement (security/DoS and concurrency hardening). It closes undefined/unsafe inputs, preserves all accepted fields and normal-flow semantics, and aligns `CART-005` behavior with its documented bodyless action (`docs/api/api-contract.md §22.6`). Per `phases/api-breaking-change-policy.md` §2, this is a clarification/bug-fix class change, not a removal or narrowing of any documented client contract.

**Contract Review:** Reviewed against `phases/api-breaking-change-policy.md` §1/§2 and `AGENTS.md §7`. The contract text, OpenAPI, and examples are updated together; the change is recorded here rather than introduced silently.

**OpenAPI Update:** `docs/api/openapi.yaml` — `CART-002` line-cap/`409` description and `409` response; `CART-003`/`CART-004` `409` (and `CART-004` `422`); `CART-005` bodyless/line-cap description with `401`/`409`/`422`/`429`.

**Example Update:** `docs/api/api-examples.md §6.6` adds the line-cap, concurrency-`409`, and bodyless-merge error cases.

**Verification:** `tests/Feature/CartMutationGuardTest.php`, `tests/Feature/CartMergeApiTest.php`, `tests/Feature/CartRemoveItemApiTest.php`, `tests/Feature/CartAddItemApiTest.php`, and the MySQL-gated `tests/Integration/CartMergeConcurrencyMysqlTest.php` (mutation-vs-merge and target-add-vs-merge cap races).

**Approval:** Project owner (review remediation request).

**Reason:** Records the cart hardening through the required Post-Freeze Change Process so the observable changes are explicit, classified, reviewed, and approved instead of drifting from the frozen V1 baseline.

**Status:** Accepted (Non-Breaking) | **Date:** 2026-09-29 | **Affected:** `backend/laravel` (`app/Models/Cart.php`, `app/Services/Cart/*`, `app/Http/Controllers/Api/V1/CartController.php`, `app/Http/Middleware/CustomerCartAccess.php`), `docs/api/{api-contract.md,api-examples.md,openapi.yaml}`, `security.md`, `docs/decisions.md`

---

### ADR/BACKEND-039 — Canonical Order Totals Calculator (Phase 7.6)

**Decision:** Phase 7.6 establishes `App\Services\Checkout\OrderTotalsCalculator` as the single canonical, pure calculation boundary for Checkout/Order financial totals, producing the immutable `App\Services\Checkout\OrderTotals` result and `App\Services\Checkout\OrderLineAmount` per-line values. Money is integer minor units (1 TZS = 100), currency fixed `TZS`, with no floats, no DB/Eloquent access, and no Cart/Order/inventory/payment side effects. Overflow is guarded for unit price × quantity, subtotal accumulation, and subtotal + delivery fee.

- **Canonical rules:** PICKUP → fee `0`, `FINALIZED`, `total = subtotal`, financially final; DELIVERY pending → fee `null`, `PENDING`, `total = subtotal`, provisional; DELIVERY finalized → fee `>= 0`, `FINALIZED`, `total = subtotal + fee`, financially final. Null and zero fee remain distinct states; finality is derived from the branch plus `delivery_fee_status`, never numerical equality. All other combinations fail explicitly.
- **Single authority:** `PickupFulfillmentState` and `DeliveryFulfillmentState` delegate amount arithmetic to the calculator (only branch semantics remain local), and `DeliveryFeeFinalizer` (ORD-014) computes the finalized total through `forDeliveryFinalized`, removing the second embedded `subtotal + fee` formula. ORD-014 authorization, fee validation, idempotency, audit-exactly-once, and financial immutability are unchanged.
- **Boundary validation:** scalar entry points accept `mixed` and reject non-`int` values (`is_int`) before any coercion, so a float passed by a caller without `strict_types=1` fails explicitly rather than being silently truncated.
- **Not resolved:** Phase 7.4 remains **BLOCKED** by the missing `orders.billing_address` snapshot persistence. No schema, dependency, frontend, OpenAPI, payment, reservation, Order insert, Cart mutation, or OrderItem persistence is introduced.
- **Verification:** `tests/Unit/OrderTotalsCalculatorTest.php` (financial matrix, null-vs-zero distinction, overflow, float rejection, determinism), `tests/Feature/OrderTotalsCompatibilityTest.php` (Order model agreement), plus the Phase 7.3/7.4 state tests and the Phase 7.5 `DeliveryFeeApiTest` regression.

**Reason:** Gives Checkout, ORD-014, and Group H one reusable calculation authority so no branch or workflow owns a competing total formula, without claiming the blocked DELIVERY persistence path is solved.

**Status:** PASS (financial calculation) / Phase 7.4 DELIVERY persistence remains BLOCKED | **Date:** 2026-09-29 | **Affected:** `backend/laravel` (`app/Services/Checkout/{OrderTotalsCalculator,OrderTotals,OrderLineAmount,PickupFulfillmentState,DeliveryFulfillmentState}.php`, `app/Services/DeliveryFeeFinalizer.php`, `tests/Unit/OrderTotalsCalculatorTest.php`, `tests/Feature/OrderTotalsCompatibilityTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-040 — Checkout Transaction Boundary (Phase 7.7)

**Decision:** Phase 7.7 establishes one authoritative CHK-001 Checkout transaction boundary in `App\Services\Checkout\CheckoutTransaction`, driven by an immutable `CheckoutCommand`. The shared `App\Services\IdempotencyService` owns the single outer transaction, so the Cart lock/final snapshot, authoritative line revalidation, `OrderTotalsCalculator`, `PickupFulfillmentState` projection, Order + OrderItem persistence, `InventoryAllocator` reservation/allocations, initial status history, Cart clear, and the stored successful 201 outcome all commit or roll back as one unit. PICKUP is fully supported internally; DELIVERY fails with `DeliveryCheckoutUnsupportedException` before any mutation because the Phase 7.4 billing-snapshot persistence gap is unresolved.

- **Transaction owner:** `IdempotencyService::execute` → `ConcurrentTransaction::run` (reentrant/short-circuiting on nested calls). Checkout adds no independent commit boundary; `InventoryAllocator` joins the same connection/transaction.
- **Lock order:** idempotency coordination → active Cart (`lockForUpdate`) → Cart lines (`lockForUpdate`) → Order row → ProductStock rows via `InventoryAllocator` (`Order` then `ProductStock id ASC`). Consistent with CART-005 and INV-003; no reverse order.
- **Atomicity / rollback:** creating the Order/OrderItems before reservation is safe because a reservation failure rolls the whole transaction back. Model-event forced-failure tests prove rollback after Order insert, after reservation, and during Cart clear, leaving no Order, OrderItems, history, reservation, allocation, Cart change, or successful idempotency record.
- **Idempotency:** the shared store is reused (no second system). `IdempotentOutcome`/`IdempotencyService` were generalized to preserve the stored HTTP status (default `200`, Checkout `201`) without changing INV-003/CART-005/ORD-014 behavior. Replay lookup precedes Cart validation, so a same-key retry after Cart clear replays the original 201; same key + changed intent returns `409 DUPLICATE_OPERATION`; different customers are separate scopes; a concurrent same-key execution's claim insert waits on the unique claim key and then reconciles to the same 201 (it waits for completion; `409 CONFLICT` is only the fallback for a completed claim with no stored outcome).
- **Cart semantics:** the customer's own `ACTIVE` Cart row is locked and retained; only its items are deleted (via a current/locking read so the clear matches the locked snapshot), and only on success; failures preserve items/quantities/status. The different-key guarantee is scoped to a **Cart contents generation**: two different-key checkouts racing the same locked contents yield one success and one `CART_INVALID`, but because the Cart stays `ACTIVE`, a customer who adds new items after a successful checkout creates a new contents generation that a later checkout legitimately orders as a new Order.
- **Pricing:** current server-side Variant price, never the Cart projection; the canonical `OrderTotalsCalculator` produces line totals/subtotal/branch result. Catalog `Product`/`Variant` write endpoints are still `501` stubs, so no concurrent price mutation exists and no speculative catalog row locks are added (recorded assumption).
- **Order:** server-owned `customer_id`, server-generated `OD-*****` reference (bounded retry on unique collision), opaque `ord_...` id, initial `PENDING_PAYMENT`, exactly one `SYSTEM → PENDING_PAYMENT` history event, TZS integer minor units.
- **Error addendum:** the frozen `CART_INVALID` code (already in the §15.3 registry) was added to the `ApiErrorCode` enum so the empty/missing-cart Checkout mapping is contract-backed; no new public codes and no public OpenAPI change.
- **Driver split:** SQLite (canonical suite) proves business atomicity, rollback, and persistence relationships; MariaDB (`tests/Integration/CheckoutTransactionConcurrencyMysqlTest.php`, disposable DB only, 5 iterations for the last-unit race) proves row-lock concurrency, no duplicate Orders, and no oversell.
- **Not resolved:** Phase 7.4 DELIVERY persistence (billing snapshot) remains **BLOCKED**; complete DELIVERY Checkout persistence is NOT implemented. No Payment row/provider call, no reservation release/consume, no schema, dependency, frontend, or OpenAPI change. The public CHK-001 route stays a `501` stub; Phase 7.8 owns validation and activation.

**Reason:** Closes the Phase 7.1 idempotency-atomicity gap and gives Checkout one safe transaction boundary that future DELIVERY persistence can plug into without a second workflow, while explicitly preserving the 7.4 blocker.

**Status:** PASS (transaction boundary infrastructure) / DELIVERY Checkout persistence remains BLOCKED | **Date:** 2026-09-29 | **Affected:** `backend/laravel` (`app/Services/Checkout/{CheckoutTransaction,CheckoutCommand,CheckoutLine}.php`, `app/Services/{IdempotencyService,IdempotentOutcome}.php`, `app/Support/ApiErrorCode.php`, `app/Exceptions/DeliveryCheckoutUnsupportedException.php`, `app/Models/Cart.php`, `tests/Feature/CheckoutTransactionTest.php`, `tests/Integration/CheckoutTransactionConcurrencyMysqlTest.php`), `docs/decisions.md`

---

### ADR/BACKEND-041 — Checkout Validation Boundary (Phase 7.8)

**Decision:** Phase 7.8 completes the CHK-001 validation/application boundary in `App\Http\Requests\CheckoutRequest` + the thin `App\Http\Controllers\Api\V1\CheckoutController`, producing the existing immutable `App\Services\Checkout\CheckoutCommand` and delegating all business authority to the Phase 7.7 `CheckoutTransaction`. No transaction, pricing, totals, inventory, or persistence logic is duplicated. The public route stays **STUB/GATED** because the Phase 7.4 DELIVERY billing-snapshot persistence gap is unresolved.

- **Authentication/authorization:** the route keeps `clerk.auth` (anonymous → `401 AUTHENTICATION_REQUIRED`) and now also `customer-cart` (`STAFF`/`ADMIN` and mixed-role accounts → `403 FORBIDDEN`). Suspended/inactive accounts are rejected by the centralized `EnsureActiveAccount` (`403`). The controller also asserts an authenticated `User`; `CheckoutTransaction::assertCustomer` remains defense-in-depth.
- **Idempotency-Key:** mandatory via the shared `IdempotencyKeyHeader` (`422 MISSING_REQUIRED_FIELD` / `422 INVALID_FORMAT`, field `Idempotency-Key`); never generated server-side. It is checked in `CheckoutRequest::prepareForValidation()` — after the auth/authorization/throttle middleware but before body rules — so a missing key is reported before `fulfillment_type`/body errors. The validated, normalized intent is the fingerprint input.
- **Request allow-list:** exactly `fulfillment_type` and conditional `delivery_address`; unknown top-level **body** fields are rejected (`INVALID_VALUE`); server-controlled identity, Cart-selector, money, delivery-fee, status/reference, payment, billing-address, and saved-address fields are all rejected. Input is JSON-body only: a query parameter that collides with the body allow-list is rejected (`INVALID_VALUE`), while unrelated query parameters (analytics/cache-buster tags) are ignored because the frozen contract documents no query rule for CHK-001.
- **Fulfillment:** closed enum `PICKUP`/`DELIVERY` (`required`, `string`, `in`). `PICKUP` accepts absent/`null` `delivery_address` and rejects any populated/empty-but-present value with `422 INVALID_FULFILLMENT` (absent and `null` normalize identically for idempotency). `DELIVERY` requires an object address (`required_if`) with exactly `recipient_name`, `phone`, `address_line`, `city`; `city` is canonical and `region`/other nested aliases are rejected; normalization (trim + Phase 7.2 phone rules) reuses `DeliveryFulfillmentState`, and domain-invalid normalized input maps to `422 INVALID_DELIVERY_INFORMATION`.
- **Command mapping:** the controller builds `CheckoutCommand(customer, fulfillmentType, normalizedDeliveryAddress, idempotencyKey)` and never passes the HTTP `Request` into the transaction.
- **Error mapping:** structural errors use the existing renderer (`MISSING_REQUIRED_FIELD`/`INVALID_TYPE`/`INVALID_FORMAT`/`INVALID_VALUE`); branch/domain errors throw canonical `ApiException` codes; domain transaction errors (`CART_INVALID`, `PRODUCT_NOT_PURCHASABLE`, `INVALID_PRODUCT_VARIANT`, `INSUFFICIENT_STOCK`, `DUPLICATE_OPERATION`, `CONFLICT`) pass through unchanged. `INVALID_FULFILLMENT` and `INVALID_DELIVERY_INFORMATION` (already in the §15.3 registry) were added to the `ApiErrorCode` enum.
- **Response:** `201` for a new Checkout and for a same-key replay; the frozen `data` shape is the Phase 7.7 stored outcome (no raw Eloquent), with `Cache-Control: private, no-store` and `Vary: Authorization`.
- **Idempotency ordering fix:** the fulfillment-support guard is an `IdempotencyService::execute` **pre-claim precondition** that runs after a completed replay/conflict is resolved but before the key is claimed. A replay of a completed request returns before the guard; a same-key changed intent (`PICKUP`→`DELIVERY`) resolves to `409 DUPLICATE_OPERATION`; and a new DELIVERY request is rejected before any idempotency row is inserted and before any business mutation (no wasted claim churn). `DeliveryCheckoutUnsupportedException` extends `ApiException` (`BUSINESS_RULE_VIOLATION`, `422`, field `fulfillment_type`) so the failure is contract-mapped even if the route gate is ever enabled, never a generic `500`.
- **Route activation decision:** the `checkout.enabled` middleware (`EnsureCheckoutEnabled`, after `clerk.auth`/`customer-cart` and before `throttle:checkout` and request validation) returns `501` when `config('checkout.route_enabled')` is `false`. The gate runs before the dedicated checkout throttle so the permanently gated route returns the stable stub instead of eventually returning `429 RATE_LIMITED` after burning the per-user budget; the global pre-auth IP throttle and `clerk.auth` still front the route. The stub body is owned by `App\Support\NotImplementedResponse`, shared with `V1Controller::notImplemented()`, so the shape cannot drift; it is an unactivated-endpoint marker (not part of the frozen `{errors:[...]}` envelope), documented as such. Emitting the canonical envelope for the gated route would require a new contracted error code and is deferred as a post-freeze contract decision. The flag is an internal, test-only gate and is **not** environment-driven, so the route (and the unsupported DELIVERY branch) can never be exposed from configuration; tests opt in in-process (`config(['checkout.route_enabled' => true])`). Because it runs before `CheckoutRequest`, a gated route returns the stub response even for an invalid body. No public error is invented for the internal blocker, no `billing_address` persistence is added, and OpenAPI is unchanged.
- **Verification:** `tests/Feature/CheckoutValidationTest.php` (actor boundary, Idempotency-Key, allow-list/tampering, PICKUP/DELIVERY branch and field validation, normalization, replay, changed-intent conflict, gated route, private headers, JSON-only, no side effects) plus the Phase 7.3–7.7 regressions.

**Reason:** Completes the Checkout validation/application boundary on top of the proven transaction without duplicating domain authority, while honestly preserving the unresolved DELIVERY persistence blocker and the frozen contract.

**Status:** PASS (validation boundary) / public route GATED / Phase 7.4 DELIVERY persistence BLOCKED | **Date:** 2026-09-29 | **Affected:** `backend/laravel` (`app/Http/Requests/CheckoutRequest.php`, `app/Http/Controllers/Api/V1/CheckoutController.php`, `app/Services/Checkout/CheckoutTransaction.php`, `app/Support/ApiErrorCode.php`, `config/checkout.php`, `routes/api.php`, `tests/Feature/CheckoutValidationTest.php`, `tests/Feature/CheckoutTransactionTest.php`), `docs/decisions.md`

### ADR/BACKEND-042 — Group G Checkout Verification and Closure Assessment (Phase 7.9)

**Decision:** Phase 7.9 completes the Group G verification/closure-assessment phase. It adds a consolidated cross-component regression suite and an OpenAPI drift guard, executes the real MariaDB Checkout concurrency closure gate, and decides Group G closure. **Group G remains NOT CLOSED** because the Phase 7.4 DELIVERY billing-snapshot persistence blocker is unresolved and the frozen CHK-001 route is intentionally gated; the previously pending MariaDB concurrency proof is now satisfied.

- **Consolidated regression coverage:** `tests/Feature/GroupGCheckoutRegressionTest.php` encodes representative cross-component invariants (active-CUSTOMER end-to-end PICKUP; guest-credential-alone → 401; STAFF/ADMIN → 403; strict allow-list/server-controlled rejection; `city` canonical vs `region` rejected; current authoritative price over the Cart projection; PENDING-null vs FINALIZED-zero fee distinction; same-key replay after Cart clear; changed-intent `409 DUPLICATE_OPERATION`; rollback after reservation; last-unit `available_quantity = 0`; exact allocation rows support later `release`; nullable `variant_name` not coerced). Detailed unit coverage stays in the Phase 7.2–7.8 files.
- **Contract conformance:** `tests/Feature/OpenApiCheckoutContractTest.php` parses `docs/api/openapi.yaml` and asserts the frozen CHK-001 operation (`operationId`, documented responses `201/401/409/422/429`, `CheckoutRequest` allow-list, `CheckoutResponseData` frozen fields). OpenAPI itself is unchanged.
- **SQLite vs MariaDB split:** SQLite (canonical suite) proves request/validation semantics, persistence relationships, rollback, and business atomicity; MariaDB proves real pessimistic locking.
- **MariaDB closure gate (executed):** `CheckoutTransactionConcurrencyMysqlTest` on MariaDB 11.8.8 with a guarded disposable database — same-key same-Customer race, different-key same-Cart race (one Order), last-unit different-Customer race (5 iterations) — passed with one business effect, no oversell, and `available_quantity = 0`. Dependent concurrency gates also passed: `CartConcurrencyMysqlTest`, `CartMergeConcurrencyMysqlTest`, `CartMutationConcurrencyMysqlTest`, `DeliveryFeeConcurrencyMysqlTest`, `InventoryConcurrencyMysqlTest` (16 tests, 668 assertions).
- **Idempotency:** same-key replay after Cart clear returns the original stored `201`; changed intent conflicts; different customers are isolated; success status is preserved (`201` for Checkout, earlier `200` replays unchanged).
- **Route status:** `POST /api/v1/checkout` = **STUB/GATED** (`config('checkout.route_enabled')` is unconditionally `false`; not environment-driven).
- **Phase 7.4 status:** **BLOCKED** — no approved persistence location for the DELIVERY `billing_address` snapshot; complete DELIVERY Checkout persistence is NOT implemented and no workaround was introduced.
- **Group G closure decision:** **NOT CLOSED**. Closure blockers: (1) DELIVERY billing-snapshot persistence (Phase 7.4); (2) the frozen CHK-001 endpoint remains gated so the API-consumer exit condition is not met. The MariaDB concurrency gate is no longer a blocker.
- **No expansion:** no schema, frontend, OpenAPI, payment, reservation-release/consume, or Order-lifecycle change. The only dependency change is a security patch applied during verification: `laravel/framework` `v13.29.0` → `v13.34.0` (low-severity `CVE-2026-102279`, XSS in debug page information) so `composer audit` is clean; no new dependency was introduced.

**Reason:** Records the evidence-backed Group G verification and honestly separates a PASS test/verification phase from a still-open Group G, so later Groups cannot mistake an internally-complete PICKUP path for a contract-complete Checkout.

**Status:** PASS (Phase 7.9 verification) / Group G NOT CLOSED (Phase 7.4 DELIVERY persistence + gated route) | **Date:** 2026-09-29 | **Affected:** `backend/laravel` (`tests/Feature/GroupGCheckoutRegressionTest.php`, `tests/Feature/OpenApiCheckoutContractTest.php`), `docs/decisions.md`

### ADR/BACKEND-043 — Furniture Request Creation Boundary (Phase 10.1)

**Decision:** Phase 10.1 establishes the single canonical REQ-001 Furniture Request creation architecture — `POST /api/v1/requests` → optional-auth actor classification → trusted `CreateFurnitureRequestCommand` → `CreateFurnitureRequest` service → server-derived ownership + REQ reference + `SUBMITTED` persistence → explicit `FurnitureRequestResource` → `201`. Group H (Payments) and Group I (Orders) are deferred, so the Made-to-Order Request flow is a first-class production path; the public route stays **gated (501)** until Phase 10.2 validation, Phase 10.4 linked-product eligibility, and Phase 10.6 attachments land, so the API never ships partial frozen-contract behavior.

- **Optional-auth actor model:** the route keeps `clerk.optional`. Anonymous (no bearer) is permitted; a valid CUSTOMER bearer is permitted; an invalid/stale bearer is rejected by the existing hardened middleware and **never downgrades to anonymous**. A new `CustomerSubmissionAccess` (`customer-submission`) middleware runs after optional auth and rejects a resolved STAFF/ADMIN (or any non-customer identity) with `403 FORBIDDEN` — operational access is never customer submission authority.
- **Server-derived ownership:** anonymous requests persist `user_id = null`; authenticated CUSTOMER requests persist the authenticated local `User` id. The command carries the resolved `?User` actor only; `user_id` is not an accepted field and cannot be supplied by the client (`422 INVALID_VALUE`).
- **Creation service:** `App\Services\Requests\CreateFurnitureRequest` wraps the single-row write in a `DB::transaction`, generates the reference through the shared `App\Support\ReferenceGenerator` (`REQ-` + 10 base36 chars) with the existing bounded uniqueness retry (max 3), maps public fields to persistence fields, and always persists `RequestStatus::SUBMITTED`. The controller builds the immutable `CreateFurnitureRequestCommand`; it never passes the HTTP request to the service.
- **Schema/API reconciliation (frozen):** public `notes` → persistence `message` and back; `message` is never a public field. `style` and `product_details` are never accepted or exposed (no frozen REQ-001 counterpart; a product snapshot, if ever derived, belongs to Phase 10.4). `staff_internal_notes`, `request_reference`, `user_id`, and the numeric primary key stay internal. `request_status` is server-controlled. New requests start `SUBMITTED` — no auto `IN_REVIEW`/`APPROVED`/`QUOTED`/`PRODUCING`.
- **Explicit serialization:** `App\Http\Resources\FurnitureRequestResource` is the allow-listed customer/created representation (`id` opaque `req_...`, `product_id`, `product` `{id,name,slug}|null`, `quantity`, `name`, `phone`, `email`, `dimensions`, `material`, `color`, `notes`, `request_status`, `attachments: []`, `created_at`, `updated_at`). The response uses `Cache-Control: private, no-store` + `Vary: Authorization`. `FurnitureRequestIdentifier` adds the existing opaque-id convention (`req_` + base36) — raw ids are never exposed.
- **No commerce coupling:** REQ-001 creates no Order, OrderItem, Payment, provider call, inventory reservation/quantity mutation, delivery commitment, or authoritative price/quote. Duplicate contact/notes submissions are intentionally not deduplicated; no `Idempotency-Key` is required. The existing `throttle:anonymous-submit` limiter fronts the public mutation.
- **Phase scope boundaries:** Phase 10.1 implements only the allow-list, server-controlled-field protection, and the minimal structural checks needed to avoid unsafe persistence (`CreateFurnitureRequestRequest`). Phase 10.2 owns the complete frozen validation matrix; Phase 10.4 owns `MADE_TO_ORDER` active/published eligibility for `product_id`; Phase 10.6 owns attachment storage (the resource reports `attachments: []`). The creation service is structured so 10.2/10.4/10.6 can plug in without a second creation workflow.
- **Route activation state:** `POST /api/v1/requests` = **STUB/GATED** (`config('requests.route_enabled')` is unconditionally `false`; not environment-driven, tests opt in in-process). `EnsureFurnitureRequestsEnabled` (`requests.enabled`) sits after actor classification and before the submission throttle, so the gated route returns the shared `NotImplementedResponse` stub for every request without consuming the anonymous-submit budget or running body validation.
- **No expansion:** no schema/migration, no OpenAPI change, no frontend, no payment, no Order lifecycle, no notification, no queue, no CAPTCHA. Existing `FurnitureRequest` (Phase 3.14) is reused unchanged apart from two accurate relation `@property-read` annotations.
- **Verification:** `tests/Feature/FurnitureRequestCreationServiceTest.php`, `tests/Feature/FurnitureRequestResourceTest.php`, `tests/Feature/FurnitureRequestCreationApiTest.php` (anonymous + CUSTOMER ownership, reference format/uniqueness, `notes`→`message`, `SUBMITTED`, quantity null preservation, custom/linked requests, schema-only field hidden, actor/gating/invalid-bearer boundary, no commerce side effects) plus the Phase 3.14 schema, routing, security, and rate-limit regressions.

**Reason:** Preserves the frozen Request ≠ Order/Payment/Cart/Quote separation from the first Group J phase, keeps the schema/API reconciliation (`notes`→`message`, non-public `style`/`product_details`/`staff_internal_notes`) explicit rather than leaking the existing table shape, and honestly gates the public endpoint until its validation and product/attachment prerequisites are complete.

**Status:** PASS (Phase 10.1 foundation) / public route GATED | **Date:** 2026-09-30 | **Affected:** `backend/laravel` (`app/Http/Controllers/Api/V1/RequestController.php`, `app/Http/Requests/CreateFurnitureRequestRequest.php`, `app/Http/Resources/FurnitureRequestResource.php`, `app/Services/Requests/{CreateFurnitureRequest,CreateFurnitureRequestCommand}.php`, `app/Http/Middleware/{CustomerSubmissionAccess,EnsureFurnitureRequestsEnabled}.php`, `app/Support/FurnitureRequestIdentifier.php`, `app/Models/FurnitureRequest.php`, `config/requests.php`, `routes/api.php`, `bootstrap/app.php`, `tests/Feature/FurnitureRequest*.php`), `docs/decisions.md`, `phases/group-J-phases.md`
### ADR/BACKEND-044 — Furniture Request Validation Boundary (Phase 10.2)

**Decision:** Phase 10.2 completes the REQ-001 schema/normalization boundary in `App\Http\Requests\CreateFurnitureRequestRequest`, producing the Phase 10.1 `FurnitureRequestInput` value object and `CreateFurnitureRequestCommand`. Validation is explicit rather than Laravel-rule driven because the frozen contract needs JSON-number semantics, strict scalar typing, nested dimension-key checks, and exact error codes the framework's rule-name heuristic cannot express. The public route stays **STUB/GATED**.

- **Top-level allow-list (strict):** exactly `product_id`, `quantity`, `name`, `phone`, `email`, `dimensions`, `material`, `color`, `notes`. Every other key is `422 INVALID_VALUE` with the offending field: schema-only (`message`, `style`, `product_details`, `staff_internal_notes`, `request_reference`, `request_status`), ownership (`user_id`), commerce/payment (`order_id`, `payment_status`, `delivery_fee`, `quoted_price`), attachment JSON (`attachment`), and arbitrary noise (`foo`, `region`, `subject`, `category`). No silent stripping.
- **Product reference (shape only):** `product_id` optional/nullable string; opaque `prod_...` format via `ProductIdentifier::decode`; wrong type → `INVALID_TYPE`, malformed → `INVALID_FORMAT`. **No `Product` query** — existence/active/published/`MADE_TO_ORDER` eligibility stays Phase 10.4.
- **Quantity:** optional/nullable **strict integer** `1..100`; wrong JSON type (string/float/bool/array) → `INVALID_TYPE`; out of range → `INVALID_VALUE`; omitted/null remains `null` (never defaulted to `1`).
- **Contact (Anonymous == CUSTOMER, no fallback):** `name` required; at least one of `phone`/`email` required; when both are absent/blank → `MISSING_REQUIRED_FIELD` `field: phone` (deterministic, single field). A supplied but invalid optional channel is still rejected. Never derives contact from profile/Clerk.
- **Normalization:** `name` trimmed + internal whitespace collapsed (`'\s+'`→space, Unicode-safe) max 120; `email` trimmed/lowercased/`filter_var` validated (matching the model authority) max 255; `phone` trimmed/reused `App\Support\PhoneNumber` (shared with delivery/profile) max 30; `material` max 500, `color` max 200, `notes` max 5000 trimmed with internal newlines preserved. Blank optional text normalizes to `null`; no empty strings persisted.
- **Dimensions (frozen contract reconciliation):** optional/nullable object; keys strictly `length,width,height,unit`; unknown key → `INVALID_VALUE` `field: dimensions.<key>`; unit required when the object is supplied and exactly `"cm"` (aliases `CM`/`inch`/etc. → `INVALID_VALUE`); each measurement must be a JSON number (numeric strings/bools/nulls-as-numbers → `INVALID_TYPE`), `>0` and `<=10000` (`INVALID_VALUE`). Per the frozen contract (`api-contract.md §26.6`, OpenAPI nullable members) measurements are **individually optional** with at least one measurement required, so `App\Support\RequestField::validateDimensions` was relaxed from "all three required" to "at least one supplied measurement, each validated" — a deliberate reconciliation with the frozen contract, recorded here. Decimal values are preserved (model accepts int/float). `dimensions` omitted/`null` = no dimensions; `[]`/`{}` → `INVALID_TYPE` (an empty array is not an object).
- **Notes mapping preserved:** public `notes` → `CreateFurnitureRequestCommand.notes` → `FurnitureRequest.message`; responses/errors use `notes` only, never `message`.
- **Errors:** canonical `{errors:[{code,message,field}],meta:{request_id}}` envelope via the existing renderer; deterministic single code per condition (`MISSING_REQUIRED_FIELD`/`INVALID_TYPE`/`INVALID_FORMAT`/`INVALID_VALUE`). No Laravel default structure, no internals leaked. `$request->validated()` is intentionally not the carrier because the strict/number semantics are handled explicitly; the controller consumes the typed `normalizedInput()` (mirrors the established `CheckoutRequest::normalizedDeliveryAddress()` pattern).
- **Zero-side-effect failures:** validation runs before the Phase 10.1 service, so a rejected request creates no `FurnitureRequest`/`Order`/`Payment` and mutates no stock.
- **Preserved:** optional-auth ordering (invalid bearer `401`, STAFF/ADMIN `403`, anonymous/CUSTOMER allowed), `throttle:anonymous-submit` (3/min anonymous), request-size/security middleware, `Cache-Control: private, no-store` + `Vary: Authorization`, no idempotency key, duplicates permitted, no lifecycle/attachment/staff/enquiry scope.
- **Verification:** `tests/Feature/FurnitureRequestValidationApiTest.php` (102 tests — unknown/server-controlled fields, wrong types, quantity/name/free-text/dimension/product-id boundaries, contact matrix, normalization/Unicode/newlines, zero side effects) plus the Phase 10.1 regressions and the Phase 3.14 schema tests.

**Reason:** Makes the REQ-001 input contract authoritative, deterministic, tamper-resistant, and faithful to the frozen V1 API without leaking the persistence shape, while keeping Product eligibility (10.4), attachments (10.6), and lifecycle (10.3) explicitly out of scope and the public route gated.

**Status:** PASS (Phase 10.2 validation) / public route GATED | **Date:** 2026-09-30 | **Affected:** `backend/laravel` (`app/Http/Requests/CreateFurnitureRequestRequest.php`, `app/Services/Requests/FurnitureRequestInput.php`, `app/Http/Controllers/Api/V1/RequestController.php`, `app/Support/{PhoneNumber,RequestField}.php`, `app/Services/Checkout/DeliveryFulfillmentState.php`, `app/Http/Requests/UpdateMeRequest.php`, `tests/Feature/{FurnitureRequestValidationApiTest,FurnitureRequestCreationApiTest}.php`), `docs/decisions.md`, `phases/group-J-phases.md`
