# API Conventions — Consolidated (Response, Serialization, Compatibility)

> **Authority:** Reusable serialization and response conventions for `v1` (`/api/v1`). Consolidates `phase-1.13.md` + `phase-1.11` / `phase-1.12` rules. Project must not create separate permanent `api-response-*` docs per tiny decision — this file plus `api-contract.md` / `api-resources.md` is the consolidated knowledge per `phase-1.13.md §2`.

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
