# Pagination Security Policy — Version 1

## 1. Purpose

Documents the **security rules** for paginated `GET` collections under `/api/v1`: page-size limits as resource protection, authorization scoping of pagination, query-abuse prevention, resource-exhaustion mitigation and information-leakage avoidance.

**Authoritative inputs:** `AGENTS.md` §§5,18, `query-security-policy.md`, `pagination-convention.md`, `api-resource-inventory.md`.

## 2. Page-Size Limits as Resource Protection

- `per_page` maximum **100** and minimum **1** are **server-enforced authorization-irrespective resource-protection limits**. `?per_page=100000` must produce a validation error, not a full table scan, to prevent unreasonable database/memory usage (`phase-1.12.md` §37). Limits are not advisory; clients cannot override them.

## 3. Authorization-Scoped Pagination

- **Every paginated request is authorization-gated before counting and windowing.** The server first resolves the authorized dataset, then computes `total`, `last_page` and page membership over that set.
- `GET /me/orders?page=2` returns only the authenticated customer's orders; anonymous filtering of `Order`/`Cart`/`Payment`/`Delivery` collections is disallowed even with `page` param. `GET /products?page=1` for anonymous counts only `PUBLIC_READ` products. Page numbers cannot be used to infer records outside the caller's scope by probing `total` differences.

## 4. No Information Leakage via Metadata

- `total`/`last_page` in pagination metadata reflect **only the authorized collection** the requester is allowed to see. They do not expose global `total_users`, `total_orders`, `total_revenue` or other cross-tenant counts to an unauthorized customer (`phase-1.12.md` §43).
- Sensitive fields are never made filterable/sortable to enable enumeration, even with pagination.

## 5. Unknown Pagination Parameters Are Validation Errors

- Parameters outside the pagination allow-list (`page`, `per_page` plus resource-specific filters `page` is not `page_size`/`limit`/`offset`/`size`) are rejected as validation errors per `query-parameter-conventions.md` §5. `?offset=20` or `?limit=20` on a page-pagination collection is a validation error, not alias.

## 6. Empty and Out-of-Range Pages Leak No Extra Information

- Requesting `page` beyond `last_page` returns an empty `data[]` with valid metadata (see `pagination-convention.md` §8) — not a distinct error code that would reveal different handling. The behavior is uniform to avoid fingerprinting.

## 7. Query Abuse & Resource Exhaustion

- Pagination limits complement `query-security-policy.md` complexity controls: excessive CSV values, excessive filters, long `search` (>128 chars), and large `per_page` are all bounded centrally. No unbounded universal filter DSL is introduced.
- Client-supplied `total` is never trusted; the backend computes counts from authoritative data.

## 8. Admin vs Public — Same Grammar, Different Authorization

- Admin pagination uses the **same `page`/`per_page` grammar** as public/customer pagination; there is no separate admin pagination syntax. A different default `per_page` for admin is not introduced in V1. Authorization distinguishes who may page which collection, not the pagination language itself (`phase-1.12.md` §38).

## 9. Caching Must Not Leak Across Principals

- `page`/`per_page` are part of the cache key. For public `PRODUCTS` a shared cache keyed by URL is permissible; for protected collections (`me/*`, staff/admin `orders`/`requests`/`enquiries`) caching must be `private` / `Vary: Authorization` and include ownership scope in the key per `query-search-policy.md` §9 and `pagination-consistency-policy.md` §10. A shared cache keyed only by URL for protected pagination would leak another customer's page.

## 10. Out of Scope

PHI/SECRETS handling beyond pagination, WAF/CDN rules, rate-limit mechanisms and Laravel policy implementation are deferred to Group D and operational phases.
