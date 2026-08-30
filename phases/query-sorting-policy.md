# Query Sorting Policy — Version 1

## 1. Purpose

Defines the canonical **sorting** conventions for `GET` collection reads under `/api/v1` before per-endpoint allow-lists are designed. Ensures Next.js, Flutter, and Admin sort collections predictably without per-endpoint style drift, without allowing arbitrary database column sorting, and without assuming ordering that the API has not documented.

## 2. Sorting Parameters

- **Sort field:** `sort`
- **Sort direction:** `sort_direction`
- **Allowed direction values:** `asc` / `desc` (lowercase only)

No alternative names are permitted. Do not mix `sort`, `sort_by`, `orderby`, `order_by`, `order`, `direction`, `sort-direction` for the same concept. V1 uses exactly `sort` + `sort_direction`.

Compact sign-prefix form `?sort=-price` is **not supported** in V1. The `sort` value is the field name only; direction is exclusively via `sort_direction`.

## 3. Semantics

- `?sort=price&sort_direction=asc` → order by `price` ascending.
- `?sort=price` without `sort_direction` → direction defaults to **`asc`** unless the owning resource contract documents a different default. Contracts should state the default explicitly.
- `sort_direction` without `sort` → validation error (direction has no meaning absent a field).
- Invalid `sort` value → validation error (not silent fallback).
- Invalid `sort_direction` value → validation error.

## 4. Multiple Sort Criteria

- **V1 supports single-sort only.** At most one `sort` value per request. Multiple criteria (`?sort=price,name` or `?sort=price&sort=name`) are rejected as validation errors.
- If a future contract requires secondary sort, it must define `sort` as a CSV with documented precedence and the semantics `ORDER BY field1 dir, field2 dir`. No V1 collection defines it; the rule here records that multi-sort would require explicit design.

## 5. Allowed Sort Fields (Allow-List)

- Clients must not sort by arbitrary database columns. Each resource contract **must expose an explicit allow-list** of sortable fields.
- Example prohibited values that must be rejected if not allow-listed:
  - `?sort=password_hash`
  - `?sort=internal_inventory_transaction_id`
  - `?sort=reserved_quantity`
  - `?sort=payment_provider_secret`
- Allow-list enforcement is mandatory before query execution. Unknown sort fields are validation errors, never ignored.

Candidate sortable concepts by resource (allow-lists are per-contract; this inventory is illustrative and not a contract):

| Resource | Potential sortable fields (contract to confirm) |
|---|---|
| Product | `name`, `price`, `created_at`, `category`, `availability` (coarse) |
| Category | `name`, `created_at`, `display_position` |
| Order (customer) | `created_at`, `total`, `status` |
| Order (admin) | `created_at`, `total`, `status`, `fulfillment_type` |
| Request / Enquiry (admin) | `created_at`, `status` |

Internal inventory quantities, internal notes, and sensitive fields are never sortable.

## 6. Default Sorting and Stable Tie-Breaker

- Every sortable collection **has an explicit default ordering** documented in its contract. Clients must not depend on undocumented ordering.
- Illustrative default (to be confirmed by the product contract): product listing defaults to catalog-defined ordering (e.g., `created_at desc` or explicit merchandising position), not whatever order the storage engine happens to return. The contract, not the database, defines the guarantee.
- Unsorted request (no `sort` param) → server applies the default ordering; response pagination remains deterministic.
- **Stable tie-breaker (required for pagination):** Non-unique sort keys (`price`, `name`, `order_status`/`request_status`/`enquiry_status`/`payment_status`, `availability`, `category`, `created_at`) can contain ties. A single non-unique primary key does not guarantee stable page boundaries, so clients could see duplicates or miss records between pages. Every ordering — whether explicitly requested via `?sort=` or implicit default — **must include an implicit unique secondary key** as final tie-breaker: the immutable primary identifier (`id`) ascending (`ASC`) after the primary field, unless the owning contract documents a different unique tie-breaker (e.g., `created_at`+`id`). The direction of the tie-breaker is `ASC` regardless of the primary `sort_direction` to keep pagination deterministic; contracts that require tie-breaker direction to follow primary direction must document it explicitly. This tie-breaker is not a client-supplied `sort` value and does not count toward the V1 single-sort limit.

## 7. Interaction with Filtering, Search, and Pagination

- Sorting, filtering, search, and pagination compose: filter/search define the result set, `sort`/`sort_direction` plus the implicit unique tie-breaker (`id ASC`) define total ordering within the set, `page`/`per_page` define windowing over that total order. Pagination contracts must define ordering as `ORDER BY <primary_field> <dir>, id ASC`.
- No interaction where sort changes filter semantics. Sort does not implicitly change relevance ordering unless the contract documents that `?search=` with a sortable `relevance` field does so.

## 8. Repeated and Duplicate Handling

- Repeated `sort` keys (`?sort=price&sort=name`) → validation error (V1 single-sort).
- Repeated `sort_direction` (`?sort_direction=asc&sort_direction=desc`) → validation error.
- CSV within `sort` (`?sort=price,name`) → validation error in V1.

## 9. Caching

- Sorting is part of the collection retrieval identity for caching purposes. `?sort=price&sort_direction=asc` and `?sort=price&sort_direction=desc` are distinct cache keys. Caching implementation is later; semantics established here.

## 10. Documentation Requirement (Contract Template)

Each sortable endpoint contract must document:

```
Sort parameter:       sort
Type:                 enum of allowed field names
Allowed values:       [per-resource list]
Default field:        [documented]
Direction parameter:  sort_direction
Direction values:     asc, desc
Default direction:    asc (unless overridden)
Tie-breaker:          implicit id ASC (unique secondary key) after primary — guarantees stable pagination; not client-supplied
Multi-sort:           Not supported in V1 (tie-breaker is implicit, not a second client sort)
Validation:           Unknown field → validation error; direction without field → validation error
Example:              ?sort=price&sort_direction=desc  → ORDER BY price DESC, id ASC
```

## 11. Out of Scope

Exact per-resource allow-lists, pagination response metadata, OpenAPI parameters, Laravel sorting implementation, and index design are deferred to later resource contracts and Group E catalog work.
