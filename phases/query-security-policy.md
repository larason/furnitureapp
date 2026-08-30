# Query Security Policy — Version 1

## 1. Purpose

Defines the **security rules** that govern every collection query parameter under `/api/v1`. Ensures query parameters cannot expose hidden records, bypass authorization, alter prices or order states, expose internal inventory or sensitive fields, select another customer's resources, or reveal information the authenticated principal is not authorized to see. Covers ownership, allow-lists, sensitive-field handling, resource authorization, and complexity considerations before any resource contract is designed.

**Authoritative inputs:** `AGENTS.md` §§5,7,11,18, `logical-data-model-v1.md`, `api-resource-inventory.md`, `api-resource-relationships.md`, `resource-ownership.md`, `data-classification.md`, `api-versioning-strategy.md` (CLOSED enums), `phase-1.11.md` §§39-43, `query-parameter-conventions.md`.

## 2. Core Principle

Every collection query is **authorization-gated before filtering**. The server determines *which records the caller is authorized to see*, then applies client-supplied filters within that scope. Query parameters **narrow** the authorized set; they never widen it.

## 3. Ownership Cannot Be Overridden by Query

### 3.1 Customer-Owned Collections (Self-Context)

- For customer-owned reads (`GET /api/v1/me/orders`, `GET /api/v1/me/requests`, `GET /api/v1/me/enquiries`, `GET /api/v1/me/cart`, `GET /api/v1/me/notifications`), ownership is derived from the **authenticated principal** (JWT/session/`GUEST_TOKEN` holder for `Cart` only). The server determines the effective owner; no query parameter may change it.

Forbidden patterns (must be ignored or rejected — V1 rejects as unknown/validation error):
```
GET /api/v1/me/orders?user_id=123
GET /api/v1/me/orders?customer_id=another-user
GET /api/v1/me/orders?email=victim@example.com
GET /api/v1/orders?user_id=123          // outside self-context
```

Correct behavior: `GET /api/v1/me/orders?order_status=SHIPPED` returns `order_status = SHIPPED` restricted to the **caller's own orders only**. Any `user_id`/`customer_id` query that attempts to address another owner's records is an **unknown parameter → validation error** (or ignored-when-unsupported) and never an ownership change. No V1 customer-owned endpoint accepts an owner-selecting query parameter. The same prefix rule applies per collection: `?order_status=` for orders, `?request_status=` for requests (`GET /api/v1/me/requests?request_status=QUOTED`), `?enquiry_status=` for enquiries, `?payment_status=` for payments.

### 3.2 Cart Access

- `Cart` is `CUSTOMER_OWNED` holder-scoped (`GUEST_TOKEN` vs authenticated User). Query parameters on cart reads (`GET /api/v1/me/cart` or `GET /api/v1/carts/{cart}` under authorization) never allow traversing to another holder's cart. `?cart_id=...` or `?user_id=...` filter attempts are rejected.

### 3.3 Public Catalog (Anonymous)

- Public catalog collections (`GET /api/v1/products`, `GET /api/v1/categories`, public availability representations) are `PUBLIC_READ`. No customer ownership is involved; any `?user_id=` param on public collections is rejected as unknown. No customer data is leaked via catalog queries.

## 4. Allow-Lists

### 4.1 Requirement

Every resource **must** declare an explicit allow-list of supported query parameters (`query-parameter-conventions.md` §20). Parameters outside the list are **rejected as validation errors** (see conventions §5). This blocks arbitrary database column filtering.

### 4.2 Forbidden Generic Mapping

Unknown parameters must not be silently translated into database `WHERE` clauses. In particular:

```
?password=...
?password_hash=...
?email_victim=...
?internal_note=...
?reserved_quantity=...
?payment_provider_secret=...
?provider_reference=...
?operational_note=...
?cost_price=...
```

must never become filters. All are outside any planned allow-list and are therefore rejected. No endpoint may implement a fallback that treats unknown keys as column names.

### 4.3 Sensitive Fields Are Never Filterable/Sortable/Searchable

Fields with classifications `PRIVATE`, `SENSITIVE`, or `INTERNAL_ONLY` per `data-classification.md` and `api-resource-inventory.md` are never in any public/customer allow-list:

- Inventory authoritative quantities (`physical`, `reserved`, derived `available_quantity` besides the public coarse `availability`)
- Payment secrets, provider payloads, `verification_state` internals, provider reference when unauthenticated
- Operational note, actor internal identifiers where not authorized, audit internals
- Credentials (`password_hash`, `reset_token`)

Admin-facing allow-lists are distinct but still constrained to operational fields the staff/admin contract explicitly declares, and remain authorization-gated.

## 5. Authorization Checks

- **Public reads:** Catalog filtering is allowed for anonymous callers, but only within the `PUBLIC_READ` allow-list; no anonymous client may filter `Order`/`Cart`/`Payment`/`Delivery`/`Tracking` collections.
- **Authenticated customer:** May filter own collections only. Any query that would select a privileged record (e.g., `?order_status=PROCESSING` on global orders) must first scope to own orders, then filter. Cross-customer filtration is impossible.
- **Staff / Admin:** May filter operational collections (orders, requests, enquiries, payments, delivery) per their privileged allow-lists, but only when authenticated with `STAFF`/`ADMIN` role and passing Group D authorization policies. No staff query gains extra privilege via a query value; staff permission is role-based, not query-based.
- Query presence never upgrades a 403 to 200. Failing authorization precedes filter application.

## 6. Query Parameters Must Not Mutate — Status Filters Are Read-Only

No `GET` query parameter may cause state changes. The following **mutation triggers** are prohibited on `GET` and are validation errors: `?activate=true`, `?cancel=true`, `?ship=true`, `?checkout=true`, `?paid=true`, `?action=` and any mutation-handler alias (e.g., `GET /orders/{order}?action=cancel` or `GET /orders/{order}?cancel=true`). Mutations use `POST`/`PATCH`/`DELETE` per `http-method-conventions.md` and `api-action-method-policy.md`. This prevents `GET`-based price alteration (`?price=1`) or inventory exposure.

**Status filtering is a valid read filter, not a mutation.** `?order_status=DELIVERED`, `?order_status=SHIPPED`, `?order_status=ACCEPTED` (and per-collection `?request_status=...`, `?enquiry_status=...`, `?payment_status=...`) are documented **exact-match read filters** for authorized collections (`GET /api/v1/me/orders?order_status=DELIVERED`, `GET /api/v1/orders?order_status=SHIPPED` for staff/admin, `GET /api/v1/me/requests?request_status=QUOTED`). They narrow the authorized result set (see §2, §5) and do not transition state. Implementations must not reject these documented filters as mutation attempts; only mutation handlers that would change state are prohibited. Because unknown parameters are rejected, `?status=DELIVERED` (without prefix) is a validation error — the canonical keys are `order_status`, `request_status`, `enquiry_status`, `payment_status`.

## 7. Exposure of Internal Inventory Concepts — Filter vs Response Vocabulary

- Internal inventory concepts — `physical_quantity`, `reserved_quantity`, `stock_adjustment`, `inventory_transaction`, `inventory_movement_history` — must never be exposed as public query parameters nor returned as public availability fields. Exact quantities are `INTERNAL_ONLY` and never `Only 2 left` (VISION.md recommendation, `logical-data-model-v1.md` Inventory invariants).

- **Filter vocabulary (query parameters):** `availability` filter is strictly `available` / `unavailable` per `query-filtering-policy.md` §7 and `query-enum-policy.md` §3. Values `IN_STOCK`, `LOW_STOCK`, `MADE_TO_ORDER` are **not** valid `availability` filter values; `IN_STOCK` / `MADE_TO_ORDER` belong to `product_type` (see enum policy), and `LOW_STOCK` is not a filter value at all.

- **Response vocabulary (display bucket):** Product responses may expose a derived display indicator (e.g., `stock_indicator` / `availability_display` in the product representation) with buckets such as `IN_STOCK` / `LOW_STOCK` / `MADE_TO_ORDER` for UI display. This is a **response field**, not a filter, and is documented separately from the `availability` query filter. Implementations must not accept `?availability=IN_STOCK` or `?availability=LOW_STOCK` — those are validation errors; only `?availability=available` / `?availability=unavailable` are accepted.

## 8. Filters That Must Remain Distinct From Internal Behavior

- `category`, `product_type`, `availability`, `min_price`, `max_price`, `search`, `sort`, `page`, `per_page`, `created_from/created_to` are public-domain concepts. Internal concepts like `stock_adjustment` vs customer-facing stock indicator remain separate even where similarly named. No synonym reuse across boundaries.

## 9. Complexity and Rate Limits (Considerations, Not Implementation)

V1 recognizes that inconsistent or expensive queries must be bounded even though pagination and rate-limit implementation are later (Phase 1.12):

- `search` length limited to 128 characters (`query-search-policy.md` §6)
- `per_page` bounded by pagination phase (max page size to be set)
- Reasonable limits anticipated: maximum number of CSV values per multi-value filter (e.g., `category=...` at most 10), maximum number of active filters per collection, maximum date range

Actual limits belong to later phases but this policy records their security relevance. No unbounded universal DSL (e.g., `filter[field][operator]=value` for every condition) is introduced in V1.

## 10. Admin Filtering — Canonical Keys Per Resource

Admin collections may require richer filters, but each filter uses its **canonical per-resource key** (no generic `status`):
- Orders: `order_status`, `customer` (admin-authorized traversal), date range (`created_from`/`created_to`), `payment_status`, `fulfillment_type`
- Requests: `request_status`, `customer`, date range
- Enquiries: `enquiry_status`, `customer`, date range
- Payments: `payment_status` (not `payment_state`), date range

They remain **authorization-controlled** (`STAFF`/`ADMIN` role required), use the **same query language** as customer/public — no separate grammar — and `?customer=` in admin context is an admin-authorized customer traversal filter, not a customer-supplied ownership override; anonymous or customer-role attempts to filter by `customer` are rejected at authorization. Unknown `?status=` (without prefix) or `?payment_state=` (legacy alias) is a validation error — the canonical keys are `order_status`, `request_status`, `enquiry_status`, `payment_status`.

## 11. Validation Behavior Recap

- Unknown parameter → validation error (conventions §5)
- Contradictory ranges → validation error (no silent swap)
- Empty value → validation error (conventions §6)
- Invalid enum / invalid boolean / invalid numeric / invalid date → validation error
- Ownership-override attempt → validation error / rejected as unknown (never widens scope)

Error envelope / HTTP status are defined by the later error-contract phase; this policy defines the *semantic* response (validation rejection).

## 12. Out of Scope

PHI/SECRETS handling beyond the filter domain, rate-limiting mechanism, WAF/CDN rules, caching invalidation, and Laravel implementation details are deferred to Group D authorization and implementation phases. This policy governs query-parameter authorization and exposure at the API-contract level.
