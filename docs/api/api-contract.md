# API Contract — Furniture E-Commerce Platform (Consolidated)

> **Version:** `v1` — base `/api/v1` · **Status:** Phase 1.13 Baseline
> **Authority:** This file is the canonical response-envelope and resource-representation contract for `v1`. Phase instruction files are temporary working docs; this file plus `api-conventions.md` / `api-resources.md` / `openapi.yaml` are the consolidated project knowledge per `phase-1.13.md §2`.

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

- Conventions: `docs/api/api-conventions.md` (envelope, naming, timestamps, money, nulls, booleans, enums, links, serialization, compatibility).
- Resources: `docs/api/api-resources.md` (per-resource field tables with PUBLIC/CUSTOMER/STAFF exposure).
- OpenAPI: `docs/api/openapi.yaml` — updated only when OpenAPI phase is reached (this phase keeps rules precise enough for later OpenAPI).
