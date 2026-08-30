# Query Parameter Conventions — Version 1

## 1. Purpose

Establishes the project's canonical conventions for **query parameters** used by collection and read-oriented API resources under `/api/v1`. Ensures Next.js, Flutter, Admin, and future clients use one consistent query language for searching, filtering, sorting, pagination, range queries, boolean conditions, date filtering, and availability filtering. Defines the language conventions only; complete per-endpoint parameter lists belong to later resource contracts. No individual endpoint contract is implemented here.

**Authoritative inputs:** `AGENTS.md` §§3,7,11-13, `docs/VISION.md`, `logical-data-model-v1.md` v1.0 FROZEN, `api-resource-inventory.md`, `api-resource-relationships.md`, `api-versioning-strategy.md` / `api-breaking-change-policy.md` (CLOSED enums), `endpoint-naming-conventions.md`, `canonical-api-vocabulary.md`, `http-method-conventions.md`, `phase-1.11.md`.

## 2. Core Principle

Query parameters modify **how a resource collection is read**. They select, filter, order, and paginate retrieval; they must not silently become hidden business operations or mutations.

```
Good: GET /api/v1/products?category=sofas
Bad:  GET /api/v1/products?delete=true
Bad:  GET /api/v1/orders?cancel=true
Bad:  GET /api/v1/products?activate=true
```

All `GET` collection query parameters are read-only. Never use `GET` with `?checkout=true`, `?ship=true`, `?cancel=true`, or any `?action=` that mutates state (see §14). Business actions use controlled `POST` endpoints per `http-method-conventions.md` and `api-action-method-policy.md`.

## 3. Naming Style

- **Canonical form:** `snake_case`, all `lowercase`.
- **Rule:** One consistent representation project-wide. No mixing of `sortBy`, `sort_by`, `sort-by` for the same concept.
- **Generic vs resource-specific distinction:**
  - Generic (reusable across collections): `page`, `per_page`, `sort`, `sort_direction`, `search`, `created_from`, `created_to`.
  - Resource-specific (domain language): `category`, `product_type`, `availability`, `min_price`, `max_price`, `fulfillment_type`, `order_status`, `request_status`, `enquiry_status`, `payment_status`, `is_active`.
- Generic parameters use the global convention; resource-specific use domain vocabulary from `canonical-api-vocabulary.md`.

Examples (prices are minor-unit integers — see §9):
```
?page=2
?sort=price
?sort_direction=asc
?min_price=10000000
?max_price=50000000
?product_type=IN_STOCK
```

## 4. Case Sensitivity

- **Parameter names** are lowercase and case-sensitive. Clients must use the documented spelling. `?Category=sofas`, `?CATEGORY=sofas` are invalid (rejected as unknown).
- **Enum values** are case-sensitive and use the canonical `UPPER_SNAKE_CASE` form (e.g., `IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`). See `query-enum-policy.md`. `?product_type=in_stock` is invalid.
- **Boolean values** are lowercase `true` / `false` only (see §8).

## 5. Unknown Query Parameters

**Policy: Reject unknown parameters for known collections.**

- Any query parameter not in the allow-list for the requested collection is a **validation error** (error structure deferred to later error-contract phase; HTTP status defined later).
- Rationale: Explicit validation surfaces client/API contract mismatches during development rather than silently ignoring mistakes. This aligns with Phase 1.11 §8 recommendation (prefer explicit validation) and prevents arbitrary database column injection (`?password=...` must never become a filter).

For intentionally extensible future collections, the owning resource contract may declare a different policy, but the V1 default is strict reject.

## 6. Empty Values

**Policy: Empty values are validation errors.**

- `?search=` , `?category=` , `?min_price=` , `?sort=` , `?product_type=` → validation error (rejected) regardless of collection.
- Rationale: Empty vs absent must not gain divergent semantics per endpoint. Treating empty as a silent absent hides bugs and breaks consistency across `price`, `enum`, `boolean`, and `date` typed parameters where empty is never meaningful. The single exception is where a future contract explicitly documents a parameter that allows empty as an alias for absent; no V1 parameter does so.
- Distinction preserved: `parameter absent` (not sent) vs `parameter present but empty` (error) vs `parameter explicitly null` (not used in V1 query semantics — `?field=null` is literal string `null`, not null; null filtering is not supported unless a later contract defines it).

## 7. Null Values

- `?field=null` means the literal string `null`, not SQL/philosophical NULL, unless a later resource contract explicitly defines `null` handling for that field.
- No V1 collection defines nullable query semantics. Query semantics distinguish `absent` from `empty` (error) from literal `null` string.

## 8. Boolean Parameters

- **Canonical representation:** `true` / `false` (lowercase only).
- Not accepted: `1`, `0`, `yes`, `no`, `TRUE`, `FALSE`, `True`, `Yes`. Those are validation errors.
- Example: `?is_active=true`

## 9. Numeric Parameters

Applies to `page`, `per_page`, `min_price`, `max_price`, and any future `min_*` / `max_*`.

- **Price unit and wire format (single canonical choice):** Prices are **TZS minor-unit integers** (cents, where 1 TZS = 100 cents) transmitted as an **integer string of digits** (no decimal point, no commas, no currency symbols). This is the only wire format. `500000` means 500,000 minor units = 5,000.00 TZS; `50000000` means 50,000,000 minor units = 500,000.00 TZS. For furniture catalog ranges, `min_price=50000000&max_price=150000000` corresponds to 500,000.00–1,500,000.00 TZS. No `1,250,000`, no `TZS 500000`, no `500000.00` decimal form — only the minor-unit integer string is accepted. Currency is single-currency TZS per `PRICE-003`; physical storage also uses discrete minor units to satisfy `PRICE-004` (no floating-point arithmetic).
- **Integer vs decimal:** `page` / `per_page` are integers ≥ 1. `min_price` / `max_price` are **integers ≥ 0** (minor units, digits only); decimal points are validation errors.
- **Bounds:** `page >= 1`, `per_page >= 1` (max defined in pagination phase). `min_price >= 0`, `max_price >= 0` as minor-unit integers. Negative values are validation errors.
- **Invalid input:** Non-numeric, decimal-containing price, out-of-range, or malformed numeric → validation error (deferred error format). Never coerce `abc` to `0`.
- **Contradictory ranges:** `min_price > max_price`, `created_from > created_to` → validation error; server does not silently swap.

## 10. Range Convention

- Use explicit `min_*` / `max_*` pairs. Preferred: `min_price` / `max_price`, `min_width` / `max_width` if later required, `created_from` / `created_to` for dates.
- Semantics are inclusive: `min_price` → `>=`, `max_price` → `<=`, `created_from` → `>=`, `created_to` → `<=`.
- Do not introduce bracket syntax `price[gte]=...`, `price[lte]=...`, or ambiguous `?price=500000-1500000` without project-wide decision (V1 has no such syntax).

## 11. Multiple Values

- **Canonical multi-value representation:** Comma-separated list (CSV) within a single parameter occurrence.
  - Example: `?category=sofas,beds` means `category = sofas OR beds`.
  - Example: `?product_type=IN_STOCK,MADE_TO_ORDER`
- Not supported: `?category[]=sofas&category[]=beds`, `?category=sofas&category=beds`. Duplicate occurrences of the same parameter are rejected as validation errors unless a contract explicitly allows them.
- Applied uniformly across V1. No per-resource mixing.

## 12. Date / Time Parameters

- **Pattern:** `created_from` / `created_to` (and future `updated_from` / `updated_to` if needed). One consistent pattern; not `from_created_at` / `to_created_at`.
- **Format:** ISO 8601 UTC with timezone: `YYYY-MM-DDTHH:MM:SSZ` (e.g., `2026-08-01T00:00:00Z`). Date-only `2026-08-01` is accepted where the contract documents it as date-only; otherwise timestamp form is required. Bare ambiguous timestamps without timezone are rejected.
- **Semantics inclusive** (`created_from` ≥, `created_to` ≤) per §10.
- **Timezone:** UTC (`Z`) is canonical. Server does not interpret ambiguous timestamps differently per server location.

## 13. Enum Handling (Summary)

- Enum query parameters use **CLOSED** semantics and canonical `UPPER_SNAKE_CASE` values (e.g., `IN_STOCK`, `MADE_TO_ORDER`, `PICKUP`, `DELIVERY`). Unknown enum values are validation errors, never wildcards. Details in `query-enum-policy.md`.

## 14. Non-Mutation Rules (in this convention)

- **GET queries are read-only:** Mutation triggers such as `?activate=true`, `?cancel=true`, `?checkout=true`, `?action=cancel` on `GET` are prohibited; those attempts are validation errors. Business actions use `POST` to action subresources per `http-method-conventions.md`. **Filtering by `?order_status=` (e.g., `?order_status=DELIVERED`) and likewise `?request_status=`, `?enquiry_status=`, `?payment_status=` is a valid read filter for authorized collections and is not a mutation — see `query-security-policy.md` §6 for the read-filter vs mutation-handler distinction. Generic `?status=` without resource prefix is a validation error in V1.**
- **No request body for GET filtering:** `GET /products` filtering belongs in the query string (`?category=sofas`), not in a JSON body.
- **Path vs query vs body:**
  - Identity → path: `/products/{product}`
  - Collection selection → query: `/products?category=sofas`
  - Creation/mutation payload → body (on `POST`/`PATCH`), never as query.

## 15. Ordering and Canonicalization

- Query parameter order does not change semantics: `?category=sofas&min_price=50000000` ≡ `?min_price=50000000&category=sofas`.
- Where caching or signatures matter later, canonical ordering is lexicographic by parameter name; values within CSV are not reordered (order is semantically significant only where documented).

## 16. Repeated and Duplicate Parameters

- Repeated identical parameter keys (`?sort=price&sort=name`) are **rejected** as validation errors because V1 supports single-sort criterion only (see `query-sorting-policy.md`). If multi-value filtering is documented, it uses the CSV form (§11), not repetition.
- Duplicate identical key-value (`?category=sofas&category=sofas`) is also rejected.

## 17. Query Encoding

- All query parameters must be properly URL-encoded per RFC 3986. Clients must encode spaces as `+` or `%20`, special characters, and raw search input before constructing URLs. Server validates decoded values. This is especially relevant for `search` (free text) and any text filter.

## 18. Pagination Reservation

- Generic pagination params `page` / `per_page` are reserved per §9. Exact numbering, max page size, metadata, and cursor-pagination decisions are deferred to Phase 1.12 `query-pagination-conventions.md`. No other pagination names (`page_num`, `limit`, `offset`) are used.

## 19. Query Parameter Documentation Format (Contract Template)

Every future endpoint contract that exposes query parameters must document each parameter as:

```
Name:                product_type
Type:                enum
Required:            No
Allowed values:      IN_STOCK, MADE_TO_ORDER
Default:             none (no filter)
Description:         Filters products by commercial type.
Validation:          CLOSED enum; unknown value → validation error.
Security/access:     PUBLIC_READ; no ownership override.
Example:             ?product_type=IN_STOCK
```

Fields required per `phase-1.11.md` §56: Name, Type, Required/optional, Allowed values, Default, Description, Validation, Security/access, Example.

## 20. Allow-Lists and Security Reference

- Every resource must define an explicit `supported query parameters` allow-list. Parameters outside the list are rejected (§5). Sensitive/internal fields are never in the allow-list. See `query-security-policy.md`.
- Ownership cannot be overridden by query (e.g., `?user_id=` never changes effective owner for `GET /me/orders`). See `query-security-policy.md`.

## 21. Out of Scope

No per-endpoint parameter inventories, pagination response format, HTTP status codes, error JSON envelope, Laravel validation rules, database queries, controllers, OpenAPI parameters, Next.js/Flutter client code, search engine selection, or caching implementation are defined here. Those belong to later resource-contract and implementation phases.
