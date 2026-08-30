# Pagination Resource Policy — Version 1

## 1. Purpose

Classifies which `GET` collections under `/api/v1` are paginated in Version 1. Prevents over-paginating tiny embedded children while ensuring growing collections (catalog, orders) are bounded by `page`/`per_page`.

**Authoritative inputs:** `api-resource-inventory.md`, `resource-relationship-map.md`, `pagination-convention.md`.

## 2. Classification — Single Vocabulary (Aligned with `phase-1.12.md` §43)

Spec `phase-1.12.md` §43 defines `REQUIRED / OPTIONAL / NOT NEEDED`. This policy uses **one vocabulary**: `REQUIRED / NOT NEEDED / DEFERRED`, where **`DEFERRED` is the project implementation of `OPTIONAL`** — both mean “pagination decision deferred to the owning resource contract (Phase 1.13+ / Group E/I/J)”. `OPTIONAL` is therefore not a fourth state; it maps 1:1 to `DEFERRED`. The table below uses `DEFERRED` (i.e., `OPTIONAL`) consistently.

Each collection is one of:

- **REQUIRED** — paginated; response must include pagination metadata and enforce `page`/`per_page` limits.
- **NOT NEEDED** — not paginated; collection is embedded or intrinsically small, returned fully.
- **DEFERRED (`OPTIONAL`)** — pagination decision deferred to the owning resource contract (Phase 1.13+ or Group E/I/J); the contract must explicitly decide `REQUIRED` or `NOT NEEDED` and document why. No silent default.

## 3. Version 1 Classification Table

| Collection (`GET`) | Path (conceptual) | Pagination | Rationale |
|---|---|---|---|
| **Products** | `/api/v1/products` (+ `?category=`, `?product_type=`, `?availability=`, `?search=`) | **REQUIRED** | Catalog grows; public listing, search, category browsing, Next.js SEO product listings all require bounded pages. |
| **Categories** | `/api/v1/categories` | **DEFERRED** → likely `REQUIRED` if category count grows beyond ~100, otherwise small collection may return fully with optional pagination for consistency. Contract decides. | Single-level V1 (DM-DEC-01), currently small, but admin table may still paginate. |
| **Orders** (customer) | `/api/v1/me/orders` , `/api/v1/orders/{order}` is item not collection | **REQUIRED** | History grows indefinitely per user; `me/orders` and admin `orders` are primary paginated views. |
| **Orders** (admin) | `/api/v1/orders` (STAFF/ADMIN) | **REQUIRED** | Operational list grows with business; filtering by `order_status`, `payment_status` etc. before pagination. |
| **Notifications** | `/api/v1/me/notifications` , `/api/v1/notifications` | **REQUIRED** | Per-user stream, potentially many records; supports pull-to-refresh / infinite scroll. |
| **Furniture Requests** | `/api/v1/me/requests` (customer own), `/api/v1/requests` (admin) | **REQUIRED** (admin) / **DEFERRED** (customer `me/requests` likely REQUIRED as history grows) | Admin review table is primary paginated use; customer own list grows slowly but should still be paginated for consistency. |
| **Enquiries** | `/api/v1/me/enquiries`, `/api/v1/enquiries` (admin) | **REQUIRED** (admin) / **DEFERRED** (customer) | Same as requests. |
| **Customers / Users** | `/api/v1/users` (admin) | **REQUIRED** | Admin customer management table. |
| **Product Images** | `/api/v1/products/{product}/images` (+ item) | **NOT NEEDED** | Embedded in product representation; typical count small (<20). Independent pagination would complicate product detail; if admin gallery grows large, contract may introduce optional pagination with explicit reason. |
| **Product Variants** | `/api/v1/products/{product}/variants` | **NOT NEEDED** | Always under owning product; small collection; pagination unnecessary for normal detail; deferred only if variant explosion is proven. |
| **Cart Items** | `/api/v1/me/cart/items` | **NOT NEEDED** | Cart is temporary, few items; no pagination. |
| **Order Items** | `/api/v1/orders/{order}/items` | **NOT NEEDED** | Historically immutable snapshot embedded in Order; small fixed set per order. |
| **Order Tracking / Status History** | `/api/v1/orders/{order}/tracking`, `/status-history` | **NOT NEEDED** | Timeline per order, very small; no pagination. |
| **Payments** (per order) | `/api/v1/orders/{order}/payments` | **NOT NEEDED** (DEFERRED if retries grow large) | One-to-many over retries, typically 1–3 records; full list returned. |
| **Delivery / Addresses / Payments (single)** | embedded / item `…/delivery` | **NOT NEEDED** | Single record per order (conditional). |

## 4. Rules

- Any collection marked **REQUIRED** must enforce `page` (≥1), `per_page` (1–100) validation, deterministic ordering with tie-breaker, and return canonical metadata (`current_page`, `per_page`, `total`, `last_page`, `has_next`, `has_previous`).
- **NOT NEEDED** collections do not accept `page`/`per_page`; if a client sends pagination params to a non-paginated collection, they are **validation errors** (unknown parameter) per `query-parameter-conventions.md` §5, not silently ignored.
- **DEFERRED** collections must explicitly document in their contract whether they are paginated and, if so, under the same convention; silent exception without documentation is not allowed (`phase-1.12.md` §49).

## 5. Admin Consistency

Admin collections follow the **same** pagination convention as public/customer collections (same `page`/`per_page`, same limits, same metadata). Different default `per_page` for admin is not introduced in V1 unless a contract justifies it with evidence; if introduced it is documented explicitly.

## 6. Platform Neutrality

The classification supports Next.js (page navigation, SEO), Flutter (infinite scroll / load-more) and Admin tables uniformly; the API remains platform-neutral.

## 7. Out of Scope

Exact per-resource `per_page` tuning and Laravel implementation are deferred to resource contracts and Group E/I/J.
