# Pagination Convention — Version 1

## 1. Purpose

Defines the project's canonical **pagination strategy** for `GET` collection reads under `/api/v1`. Ensures Next.js, Flutter, Admin and future consumers paginate collections with one predictable language for page numbering, page size, defaults, limits, invalid handling, empty results and stable ordering. Implements the baseline accepted in `phase-1.12.md` §9 and §56.

**Authoritative inputs:** `AGENTS.md`, `docs/VISION.md`, `logical-data-model-v1.md` v1.0 FROZEN, `api-resource-inventory.md`, `api-resource-relationships.md`, `endpoint-naming-conventions.md` §10, `query-parameter-conventions.md`, `query-filtering-policy.md`, `query-sorting-policy.md` (tie-breaker), `query-search-policy.md`, `query-security-policy.md`, `phase-1.12.md`.

## 2. Core Principle

Pagination is a **collection retrieval concern**. It determines which subset of a collection is returned; it does not change the meaning of an individual resource (`/products/{product}` is never paginated).

## 3. Strategy Selection

- **Version 1 strategy:** **Page-number / offset pagination** for all normal V1 collections.
- **Rejected for V1:** cursor pagination (except where explicitly deferred per `pagination-cursor-policy.md`), hybrid mixing. See `pagination-cursor-policy.md` for evaluation and deferred criteria.
- **Rationale:** Simplest for small-scale catalog (products), growing order history and admin tables; trivial for Next.js/Flutter/Laravel to implement; no evidence that V1 volume requires cursor traversal.

## 4. Parameters

- **Page number:** `page` — integer, **1-based**. `page=1` is first page, `page=2` second, etc. Zero-based (`page=0`) is invalid.
- **Page size:** `per_page` — integer, page size. Canonical name established by Phase 1.11; no aliases `limit`, `page_size`, `items_per_page`, `count`.
- **Case:** `page`, `per_page` are `snake_case` lowercase, case-sensitive; `?Page=1` is validation error.

Examples:

```
GET /api/v1/products?page=2&per_page=24
GET /api/v1/me/orders?page=1&per_page=20
```

## 5. Defaults (When Omitted)

- `page` omitted → `page = 1`.
- `per_page` omitted → global default **20** items.

These defaults are applied uniformly across all paginated collections unless a resource contract explicitly documents a different default (not done in V1). No framework default is assumed.

## 6. Limits

- **Minimum:** `per_page >= 1`
- **Maximum:** `per_page <= 100` — server-enforced. A request `?per_page=1000` is a validation error, not truncated and not fulfilled with 1000 records.
- **Minimum page:** `page >= 1`

See `pagination-security-policy.md` for resource-protection rationale.

## 7. Invalid Values

Any invalid pagination value is a **validation error** (error envelope in Phase 1.13, not silently corrected to defaults):

- `page=0`, `page=-1`, `page=abc`, `page=1.5`, `page=` (empty)
- `per_page=0`, `per_page=-10`, `per_page=abc`, `per_page=1.2`, `per_page=` (empty)
- `per_page=1000` (exceeds maximum)
- Unknown pagination parameters (`?page_size=20`, `?limit=20`, `?offset=20`) → validation error per `query-parameter-conventions.md` §5 (allow-list rejection).

No coercion (`abc` → `1`) and no silent clamping (`1000` → `100`).

## 8. Empty Results

- **Empty dataset (total = 0):** `total = 0`, `current_page = 1`, `last_page = 1`, `data = []`, `has_next = false`, `has_previous = false`. No `last_page = 0` variant; chosen convention is uniform `1` for empty collection to avoid page-zero logic in clients.

- **Page beyond `last_page` (e.g., `GET /products?page=10` when `last_page = 3`):** Returns **valid empty collection** with normal pagination metadata (`data = []`, `current_page = 10`, `per_page = <requested/default>`, `total = <authorized total>`, `last_page = 3`, `has_next = false`, `has_previous = true` if `10 > 1`). Not a validation error and not a `404`. Consistent across all resources.

## 9. Ordering Requirements for Pagination

- **Deterministic ordering is mandatory.** Every paginated collection has a deterministic default ordering documented in its contract; clients must not rely on database natural order (`phase-1.12.md` §27, `query-sorting-policy.md` §6).
- **Stable tie-breaker (single canonical direction):** Every ordering — whether explicit `?sort=` or implicit default — **must include implicit unique secondary key `id ASC`** after the primary field to guarantee stable page boundaries when primary keys tie (`price`, `name`, `order_status`, `created_at` etc.). The tie-breaker direction is **always `ASC`**, regardless of the primary `sort_direction` (e.g., `price DESC` → `ORDER BY price DESC, id ASC`), to ensure page membership does not change solely due to tie-breaker direction. This **supersedes** the illustrative `unique identifier DESC` example in `phase-1.12.md` §52, which is not canonical for V1; all V1 pagination and sorting references now use `id ASC`. The tie-breaker does not count toward V1 single-sort limit. Pagination contracts must implement `ORDER BY <primary> <dir>, id ASC`. See `pagination-consistency-policy.md` and `query-sorting-policy.md` §6.

## 10. Preservation of Query Context

Pagination navigation (links/metadata) must preserve active query context: `category`, `product_type`, `availability`, `min_price`/`max_price` (minor-unit integers), `search`, `sort`/`sort_direction`, `created_from`/`created_to`, `order_status`/`request_status`/`enquiry_status`/`payment_status`, etc. The `next` page link for `/products?category=sofas&sort=price&sort_direction=asc&page=2` must retain `category`, `sort`, `sort_direction`.

Query parameter ordering in links is deterministic but does not affect semantics (lexicographic canonicalization per `query-parameter-conventions.md` §15).

## 11. Pagination and Authorization

- Pagination counts and pages are computed over the **authorized dataset only**, not the entire database. `GET /me/orders?page=2` counts only the caller's own orders; `GET /products?page=1` counts only `PUBLIC_READ` products.
- See `pagination-security-policy.md` and `query-security-policy.md` for ownership scoping.

## 12. Pagination and Caching

- `page` and `per_page` plus filters/sorting form the collection identity and therefore the cache key. `GET /products?page=1` and `GET /products?page=2` are distinct cache keys. For protected collections, caching is also authorization-aware (`private` / `Vary: Authorization`) per `query-search-policy.md` §9.

## 13. No Payment / No Cursor in Convention

- No payment implementation is included; payment remains **Phase Group H**.
- Cursor pagination is **deferred** — see `pagination-cursor-policy.md`.

## 14. Out of Scope

No Laravel pagination code, database queries, endpoint-specific response envelopes, HTTP status codes, OpenAPI schemas, Next.js/Flutter widgets, cursor tokens or caching configuration are defined here.
