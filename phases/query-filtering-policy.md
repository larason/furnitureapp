# Query Filtering Policy — Version 1

## 1. Purpose

Defines the project's filtering conventions for collection reads under `/api/v1`. Distinguishes search (broad textual discovery) from structured filtering, establishes exact-filter, multi-value, range-filter, AND/OR, and allow-list rules before per-endpoint parameter lists are designed. No per-endpoint parameter list or Laravel implementation is defined here.

**Authoritative inputs:** `AGENTS.md` §§11-13, `logical-data-model-v1.md` v1.0, `api-resource-inventory.md`, `api-resource-relationships.md`, `query-parameter-conventions.md` (this phase), `phase-1.11.md`.

## 2. Search vs Filter

- **`search`** → broad textual discovery (free-text). Conceptually `?search=modern+sofa`.
- **Filter** → exact/structured condition. Conceptually `?category=sofas`, `?product_type=IN_STOCK`.

Do not create aliases `?find=`, `?lookup=`, `?filter=` for the same purpose. `search` handles text; resource-specific names handle structure.

## 3. Exact Filters

- An exact filter requires exact equality (with case rules per parameter type). Example: `?category=sofas` means `category = 'sofas'` (slug equality, case-sensitive lowercase). `?product_type=IN_STOCK` means `product_type = 'IN_STOCK'` (CLOSED enum, case-sensitive). No partial or fuzzy matching on exact filters.
- Each resource contract declares which exact filters it supports; unsupplied means unfiltered (no implicit default except where documented).

## 4. Product Filtering Examples (Conceptual — Not Endpoint Contract)

Future product collection will support combinations such as (prices are minor-unit integers per `query-parameter-conventions.md` §9 — `50000000` = 500,000.00 TZS):

```
GET /api/v1/products?search=sofa&category=sofas&product_type=IN_STOCK&availability=available&min_price=50000000&max_price=150000000&sort=price&sort_direction=asc&page=1&per_page=20
```

Individual semantics:

| Parameter | Type | Notes |
|---|---|---|
| `category` | exact filter (slug or identifier — contract decides) | Filters to a single category; multi-value CSV will be defined if supported |
| `product_type` | CLOSED enum exact filter | `IN_STOCK` or `MADE_TO_ORDER` (see enum policy) |
| `availability` | exact filter (customer-visible) | `available` / `unavailable` coarse public signal; never `reserved_quantity` |
| `min_price` / `max_price` | range filter (TZS minor-unit integers, `50000000` = 500,000.00 TZS) | Inclusive `price >= min_price` and `price <= max_price` (price is effective purchasable price in minor units, backend-computed) |

The final product endpoint name/shape and its exact allowed filters are deferred to the catalog contract phases (Group E). This section is illustrative only.

## 5. Category Filtering Policy

- **Query key:** `category`
- **Accepted value:** Canonical category **slug** (lowercase, kebab-case as stored) per `logical-data-model-v1.md` `Category.slug` unique global. Identifier-based filtering (`category_id`) is not supported in V1 query language; if ever needed it would be a distinct parameter, not an alias.
- **Semantics:** Exact equality on slug. Unknown slug → empty result set (not validation error), unless the contract documents category-identifier validation.
- **Multi-value:** If later enabled per §7, `?category=sofas,beds`.

## 6. Product-Type Filtering Policy

- **Query key:** `product_type`
- **Allowed values:** `IN_STOCK`, `MADE_TO_ORDER` (CLOSED). Unknown enum → validation error (see enum policy).
- **Semantics:** Exact. No case-insensitive alias (`in_stock`, `in-stock` rejected).

## 7. Availability Filtering Policy

- **Query key:** `availability`
- **Allowed values (public):** `available`, `unavailable` (lowercase). These are customer-visible derived signals, not internal inventory fields.
- **Must not expose:** `physical_quantity`, `reserved_quantity`, `stock_adjustment`, `inventory_transaction`, `available_quantity` as query parameters. Those are `INTERNAL_ONLY` per `api-resource-inventory.md`.
- **Semantics:** `available` → product/variant is purchasable now (active, purchasable type, sufficient `available_quantity`); `unavailable` → otherwise. Exact definition of the availability derivation belongs to catalog/inventory contract Phase 5.7/5.8.

## 8. Price Filtering Policy

- **Keys:** `min_price`, `max_price` (see `query-parameter-conventions.md` §§9-10 for numeric rules). **Wire format is TZS minor-unit integer** (e.g., `min_price=50000000` = 500,000.00 TZS, `max_price=150000000` = 1,500,000.00 TZS). Decimal strings are validation errors.
- **Semantics:** `price >= min_price` and `price <= max_price` (inclusive, compared in minor units). `price` means the effective purchasable price in minor units (base price or variant override per `VAR-005`, backend-computed, never client-supplied).
- **Do not use:** ambiguous `?price=500000-1500000`, `?price[gte]=...`, or decimal `?min_price=500000.00`. Only the `min_*`/`max_*` minor-unit integer pair.
- **Contradiction:** `min_price > max_price` → validation error (no silent swap).

## 9. Range Filters — General

- Pattern generalizes to future numeric/date ranges: `min_*` / `max_*` and `*_from` / `*_to` (dates). All ranges are inclusive on both ends. No bracket operator syntax in V1.

## 10. Date / Time Filtering

- Uses `created_from` / `created_to` (and if needed later `updated_from` / `updated_to`). ISO 8601 UTC with `Z` per `query-parameter-conventions.md` §12. Inclusive: `created_from` ≥, `created_to` ≤. `created_from > created_to` → validation error. Bare ambiguous timestamps without timezone are rejected.

## 11. Multiple Values and AND/OR Semantics

- **Across different parameters:** `AND`. Example: `?product_type=IN_STOCK&category=sofas` means `product_type = IN_STOCK AND category = sofas`.
- **Multiple values within one parameter (CSV):** `OR` within that attribute. Example: `?category=sofas,beds` means `category = sofas OR category = beds`. Combined with AND across parameters: `?product_type=IN_STOCK&category=sofas,beds` means `(category = sofas OR category = beds) AND product_type = IN_STOCK`.
- **Forbidden reuse:** Do not invent per-endpoint `OR`/`AND` keywords without a project-wide decision. No generic filter DSL (`filter[field][operator]=...`) in V1.

## 12. Boolean Filtering

- Where a collection supports boolean filters (e.g., future `?is_active=true` for admin), canonical values are `true`/`false` lowercase only (see conventions §8). No mixing with `1`/`0`/`yes`.

## 13. Existence / Truth Test (No Wildcards)

- Missing filter means no constraint (all values). Unknown enum value does not become wildcard — it is a validation error. `?product_type=UNKNOWN` must not silently mean `*`.

## 14. Filter Allow-Lists — Canonical Keys Per Resource

- Every resource collection must declare an explicit `supported query parameters` allow-list. Any parameter outside the list is rejected as validation error (see `query-parameter-conventions.md` §5). This prevents arbitrary database column filtering and protects against `?password=...` or `?internal_note=...` becoming filters. Unknown `?status=` without resource prefix is a validation error on all V1 collections.
- Admin collections may have richer allow-lists but remain authorization-controlled and use the same language; no separate admin grammar. Canonical keys are per resource:
  - Orders: `order_status`, `customer`, date range (`created_from`/`created_to`), `payment_status`, `fulfillment_type`, `category`, `product_type`, `availability`
  - Requests: `request_status`, `customer`, date range
  - Enquiries: `enquiry_status`, `customer`, date range
  - Payments: `payment_status` (not `payment_state`)
  - Generic `status` without prefix is not a valid allow-list entry in V1; `?payment_state=` is a validation error — use `payment_status`.

## 15. Cross-parameter Validation

- Contradictory values are **validation errors**, not silent corrections:
  - `min_price > max_price`
  - `created_from > created_to`
  - mutually contradictory exact filters if a contract documents them
- The server never swaps boundaries.

## 16. Security: Ownership and Sensitive Fields

- Filters never override ownership: `GET /api/v1/me/orders?user_id=123` must not change owner; owner is the authenticated principal (see `query-security-policy.md`).
- Filters never expose hidden records, bypass authorization, alter prices, change order states, or select another customer's resources.
- Internal inventory and internal audit fields are never filterable by arbitrary name.

## 17. Complexity Considerations

- Future phases will impose limits (max filters per request, search length, max `per_page`, max date range). V1 recognizes that need; implementation is later. No expensive universal filter DSL is introduced.

## 18. Out of Scope

No definitive per-endpoint filter lists, pagination response format, error JSON, HTTP status codes, Laravel query builders, or storage-level implementation are defined here; each resource contract will enumerate its supported filters.
