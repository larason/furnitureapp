# Pagination Consistency Policy — Version 1

## 1. Purpose

Documents how pagination behaves consistently with respect to **ordering, filtering, searching, changing data and authorization** for all `GET` collections under `/api/v1`. Ensures clients can reason about what a page contains regardless of which resource they are paging.

## 2. Stable Ordering (Mandatory)

- Every paginated collection has a **deterministic default ordering** documented in its contract. Database natural order is not a contract behavior.
- Ordering is `ORDER BY <primary_field> <dir>, id ASC` where `<primary_field>` is either the requested `sort` value or the implicit default (e.g., `created_at DESC` for orders, catalog-defined position for products). `id ASC` is the **implicit unique tie-breaker** required by `query-sorting-policy.md` §6 and `pagination-convention.md` §9. It guarantees stable page boundaries when primary keys tie (`price`, `name`, `order_status`, `created_at`).

## 3. Search → Filter → Sort → Paginate (Strict Pipeline)

Logical model for every paginated request (single canonical order per `phase-1.12.md` §30, `pagination-decisions.md` 1.12-DEC-15):

```
All authorized records for the collection
  ↓
Apply search  (?search= modern sofa — case-insensitive partial over public searchable fields)
  ↓
Apply filters  (category, product_type, availability, order_status, min_price/max_price, created_from/to, etc.)
  ↓
Apply sorting  (?sort= + sort_direction + implicit id ASC tie-breaker, or default ordering)
  ↓
Apply pagination  (page / per_page windowing)
  ↓
Return page + metadata (total = searched + filtered count)
```

Searching/filtering before sorting/pagination is mandatory; otherwise `total`/`last_page` and user expectations (e.g., “page 2 of sofas”) become incorrect. `Search` and `Filter` are both narrowing steps before `Sort`; the canonical order is **Search → Filter** per the spec, and `Filter → Search` is not a distinct variant — the two are applied as a combined narrowing phase before sorting (see `phase-1.12.md` §§29-30).

## 4. Sorting Consistency Across Pages

- The same `sort`/`sort_direction` plus tie-breaker ordering must be maintained **identically across all pages** of a traversal. The API must not reorder records unpredictably between `page=1` and `page=2` requests for the same filter/sort context.
- Sorting is part of the collection identity and therefore the cache-key (see `query-sorting-policy.md` §9).

## 5. Changing Data & Deleted Records

- Pagination is **current-state browsing**, not a snapshot. `page` numbers do not represent fixed snapshots across HTTP requests.
- If records are inserted/deleted between `page=1` and `page=2` fetches, the client may observe duplicates, missing records or shifted positions — this is acceptable for Version 1 ordinary browsing where slight movement is tolerable (`phase-1.12.md` §32-34).
- Deleted records between requests are handled by **current dataset semantics**: a page that becomes empty because its records disappeared returns an empty `data[]` with valid metadata (as in `pagination-convention.md` §8), not a snapshot replay.
- Snapshot-consistent pagination (repeatable reads across pages) is not introduced in V1; it would be justified only by a real business workflow requiring it, with explicit contract and implementation.

## 6. Do Not Make Collection Reads Transactional

Do not attempt to make the entire product/order collection globally transactional across multiple HTTP page requests. Each page is an independent read over evolving data (`phase-1.12.md` §54).

## 7. Authorization-Aware Counting

- `total`, `last_page`, and page membership are computed over the **authorized dataset only**, not the entire table.
- Example: `GET /me/orders?page=2` counts only the authenticated customer's orders; anonymous `GET /products?page=1` counts only `PUBLIC_READ` products. No unauthorized totals are exposed via `total` (see `pagination-security-policy.md`).

## 8. Availability Filtering at Request Time

For product availability filtering (`?availability=available|unavailable`), the collection membership is evaluated using **current authoritative inventory** at request time (physical/reserved/available quantities via `Inventory`), not stale client-side state (`phase-1.12.md` §31). Inventory note fields are never searchable/filterable directly.

## 9. Closed Enums Remain Closed

When a paginated collection is filtered by a Version 1 enum (`?product_type=IN_STOCK`, `?order_status=PAID`), the enum remains **CLOSED**; `?product_type=SOMETHING_NEW` is a validation error per `query-enum-policy.md`, not silent fallback. See `phase-1.12.md` §45.

## 10. Pagination + Caching Consistency

- `page`, `per_page`, `sort`, `sort_direction` and all filters/search are part of the logical collection identity and therefore the cache-key. `GET /products?page=1` and `GET /products?page=2` (or different `sort`) are distinct cache entries. For protected collections caching is also `private` / `Vary: Authorization` per `query-search-policy.md` §9.

## 11. Out of Scope

Laravel ordering implementation, `COUNT(*)` optimization, cursor tokens and cache configuration are deferred.
