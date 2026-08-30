# Pagination Metadata Policy — Version 1

## 1. Purpose

Defines the **canonical pagination metadata** returned with every paginated `GET` collection under `/api/v1`. Ensures Next.js, Flutter and Admin can render page controls and infinite-scroll without reconstructing counts, while keeping the wire shape minimal and consistent across resources.

**Authoritative inputs:** `phase-1.12.md` §§46-47, `pagination-convention.md`, `query-parameter-conventions.md`.

## 2. Canonical Metadata Fields

All paginated collections return the following **required** fields (exact names, `snake_case` lowercase):

| Field | Type | Description |
|---|---|---|
| `current_page` | integer | The page number that was returned (1-based). Echoes the requested `page` or `1` if omitted — **except** when `total = 0` (empty dataset) where `current_page` is always `1` regardless of requested `page` (see §4). |
| `per_page` | integer | The page size that was applied (requested or default `20`). |
| `total` | integer | Total number of items matching the authorized, filtered dataset (`COUNT(*)` exact for V1). |
| `last_page` | integer | Total number of pages: `ceil(total / per_page)` with floor `1` (see §4). |

Two **required boolean** convenience fields:

| Field | Type | Description |
|---|---|---|
| `has_next` | boolean | `true` if `current_page < last_page`, else `false`. |
| `has_previous` | boolean | `true` if `current_page > 1`, else `false`. |

No alternative names are permitted (`currentPage`, `page_size`, `totalItems`, `lastPage`, `total_pages`) — exactly those six names project-wide.

## 3. Illustrative Wire Shape

Actual envelope (`data` / `meta` placement) is finalized in **Phase 1.13** (Response Envelope). The conceptual pairing is:

```json
{
  "data": [ /* items for current_page */ ],
  "meta": {
    "current_page": 2,
    "per_page": 20,
    "total": 86,
    "last_page": 5,
    "has_next": true,
    "has_previous": true
  }
}
```

Only metadata naming is decided here; envelope keys (`data`/`meta`) are deferred to Phase 1.13.

## 4. Empty-Collection Conventions

- **Empty dataset (`total = 0`):** `current_page = 1`, `per_page = <requested/default>`, `total = 0`, `last_page = 1`, `has_next = false`, `has_previous = false`, `data = []`. Uniform `last_page = 1` avoids page-zero handling.
- **Page beyond range (`page > last_page` with `total > 0`):** `data = []`, but `current_page` echoes the requested page, `total` and `last_page` reflect the real dataset, `has_next = false`, `has_previous = true` if `page > 1`.

No `last_page = 0` variant is used; see `pagination-convention.md` §8.

## 5. Navigation Links (V1 Decision: Deferred — Metadata Only)

- **V1 provides numeric metadata only (`current_page`, `per_page`, `total`, `last_page`, `has_next`, `has_previous`).** Full navigation URLs (`first`, `prev`, `next`, `last` links, either absolute or relative) are **deferred**.
- **Rationale:** For a multi-client API (Next.js, Flutter, Admin) absolute URLs become problematic across `local` / `staging` / `production` environments (hard-coded hosts), and clients can reliably construct `?page=N` URLs from metadata plus preserved query context. If Phase 1.13 later decides links add value, they will be:
  - optional, additive, non-breaking;
  - absolute or relative consistently (not mixed);
  - with query preservation (filters/sorting retained);
  - with canonical parameter ordering.

This keeps V1 minimal per `phase-1.12.md` §20 and §47.

## 6. Query Preservation for Future Links

If navigation links are later introduced, they must preserve relevant query parameters (`category`, `product_type`, `availability`, `min_price`/`max_price`, `search`, `sort`/`sort_direction`, `order_status` etc.) and use deterministic serialization. Parameter ordering does not affect semantics (§10 of pagination-convention).

## 7. Optional Fields Not in V1

Fields `from`, `to` (item index range), `links` object, etc., are **not included in V1** unless Phase 1.13 explicitly adds them as additive metadata. Do not include ten framework-provided fields merely because Laravel/Livewire provides them.

## 8. Validation

Invalid `page`/`per_page` values are **validation errors**, not clamped metadata. Metadata is only returned for valid paginated requests (or valid empty-collection responses).

## 9. Out of Scope

Final `data`/`meta` envelope key placement, HTTP status codes, OpenAPI schemas and Laravel `LengthAwarePaginator` configuration are deferred to Phase 1.13 and implementation.
