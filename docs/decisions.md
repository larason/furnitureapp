# Architecture Decision Records — Furniture Platform

> **Source:** Phases 1.1–1.13. New decisions are appended here per `phase-1.13.md §76` (do not create `api-response-decisions.md`).

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

### Pending: Error Contract, OpenAPI Operations, Payment Provider

**Deferred:** Full error codes/statuses (error phase), complete `openapi.yaml` operations, provider-specific payment structures (Group H), cursor pagination tokens (Group T if ever).
