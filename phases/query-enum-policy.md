# Query Enum Policy — Version 1

## 1. Purpose

Establishes the project's policy for **enumerated query parameters** under `/api/v1`. Ensures every enum-bearing filter (`product_type`, `fulfillment_type`, `order_status`, `request_status`, `enquiry_status`, and future `payment_status`, `role`) follows closed-enum semantics consistently across Next.js, Flutter, Admin, and future integrations, that unknown values do not silently become wildcards, and that any later enum expansion is treated as an explicit compatibility decision under `api-versioning-strategy.md`.

## 2. Authoritative Principle: CLOSED by Default

> **All Version 1 enum query parameters use CLOSED enum semantics.**

This is the authoritative rule for the entire V1 contract (`api-versioning-strategy.md` §4.4, `api-breaking-change-policy.md` §5, `phase-1.11.md` §43, `query-parameter-conventions.md` §13). The interim `OPEN/EXTENSIBLE` interpretation is superseded per `phase-1.10.md` §61.

- Any Version 1 enum not yet explicitly documented as `OPEN` in its owning resource contract is treated as **CLOSED** for compatibility purposes. No blanket "unclassified enums are open" rule.
- A later contract may reclassify a specific enum as `OPEN` only after a documented compatibility review verifying that clients handle unknown values safely (display generically, ignore). Without that reclassification, it remains `CLOSED`.

## 3. Known Enums in V1 and Their Canonical Representations

| Enum / State Set | Query Key(s) (Canonical) | Allowed V1 Values (CLOSED) | Source |
|---|---|---|---|
| Product modes | `product_type` | `IN_STOCK`, `MADE_TO_ORDER` | `domain-invariants.md` CAT-002, `logical-data-model-v1.md` Product |
| Order statuses | `order_status` | `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED` | `VISION.md` §Order tracking, `domain-invariants.md` ORD-007, `order-state-rules.md` |
| Fulfilment types | `fulfillment_type` | `PICKUP`, `DELIVERY` | `domain-invariants.md` FUL-001 |
| Roles (admin user management only) | `role` | `CUSTOMER`, `STAFF`, `ADMIN` | `domain-invariants.md` AUTHZ-001, `logical-data-model-v1.md` User.Role |
| Furniture Request statuses | `request_status` | Operational lifecycle — exact V1 values defined by later request contract; until then no value is assumed beyond the admin board states (`New`, `Contacted`, `Quoted`, `In Production`, `Completed` as display names, with API values `UPPER_SNAKE_CASE`) | `api-resource-inventory.md` Request, `domain-invariants.md` REQ-005 |
| Enquiry statuses | `enquiry_status` | Operational lifecycle — exact V1 values defined by later enquiry contract | `api-resource-inventory.md` Enquiry |
| Payment statuses (admin/payment domain) | `payment_status` | Deferred to Phase Group H; when defined they are CLOSED | `logical-data-model-v1.md` Payment, `domain-invariants.md` PAY-* |
| Notification types | `type` (notifications collection) | Not a filter in V1; when filtering is added it is CLOSED unless reclassified | `logical-data-model-v1.md` Notification |
| Availability (derived public enum-like filter) | `availability` | `available`, `unavailable` (coarse public signal) | `query-filtering-policy.md` §7, `api-resource-inventory.md` Availability |

Exact `request_status` / `enquiry_status` / `payment_status` canonical value spellings are defined by their owning contracts when those contracts are written; until then clients may not assume values, and the server must reject any value not in the documented set.

## 4. Case and Spelling

- **Canonical enum values are `UPPER_SNAKE_CASE`** per `api-versioning-strategy.md` and `phase-1.11.md` §44. Example: `IN_STOCK`, `MADE_TO_ORDER`, `READY_FOR_PICKUP`.
- Case-sensitive. Aliases such as `in_stock`, `InStock`, `in-stock`, `madeToOrder` are **rejected** as validation errors unless a contract explicitly documents them as aliases (no V1 contract does).
- One canonical representation per enum value; no dual spelling.

## 5. Query Semantics

- **Exact equality:** `?product_type=IN_STOCK` means `product_type = IN_STOCK`. No partial or case-insensitive match on enum values.
- **Single value:** `?product_type=IN_STOCK` filters to that value only. Absence → no filter (all values) unless the contract documents a default.
- **Multi-value (OR within attribute):** If a collection documents multi-value enum support, `?product_type=IN_STOCK,MADE_TO_ORDER` means `product_type = IN_STOCK OR MADE_TO_ORDER` (see `query-filtering-policy.md` §11). No V1 product contract currently requires this; it is available where documented.
- **Unknown value:** Validation error (never wildcard). `?product_type=FOO` does **not** return all products; it is a validation error. Similarly `?order_status=SHIPPED` on a pickup-only view does not silently become `*`; server either validates it as disallowed status for that view or applies it with correct empty-set semantics — never wildcard. The same rule applies to `?request_status=...`, `?enquiry_status=...`, and `?payment_status=...`.

## 6. Multi-value Enum Semantics

Where a CSV enum filter is supported, commas are the **OR** separator inside one parameter (see `query-parameter-conventions.md` §11). Separate parameters use **AND**: `?product_type=IN_STOCK&order_status=ACCEPTED` means `product_type = IN_STOCK AND order_status = ACCEPTED`. No per-enum `OR`/`AND` keyword.

## 7. Addition of New Enum Values is Breaking

Per `api-versioning-strategy.md` §4.4:

> Any additive change to a `CLOSED` enum is a **breaking change in `v1`** and requires a new major version (`v2`) and the version change process in `api-versioning-strategy.md` §9 (migration strategy, parallel support, client migration, deprecation, sunset). No `v1.1`/`v1.2` public URL minors.

Therefore:

- Adding `Product_type = PRE_ORDER` (VISION.md future note) to `v1` without a `v2` is **breaking** — clients that branched exhaustively on `IN_STOCK`/`MADE_TO_ORDER` would encounter unknown state and may crash, misrender, or misroute workflows. Must be treated as `v2` until compatibility is proven and contract reclassification occurs.

If a later business requirement genuinely needs an additive enum within `v1`, it must follow the explicit path:
1. Reclassify the specific enum as `OPEN` in its contract after verifying clients handle unknown values safely (generic display, ignore).
2. Only then additive values are **non-breaking within `v1`** per `api-breaking-change-policy.md` §5. No silent addition.

## 8. Client Contract

Clients funded by V1 (`Next.js`, `Flutter`, `Admin`) **may branch exhaustively** on the documented CLOSED value set. They must not be expected to handle unknown values. Server guarantees not to send or accept unknown values within `v1`. If a contract ever reclassifies an enum to `OPEN`, clients must be updated to handle unknowns safely and the contract must document that obligation.

## 9. Validation Behavior

- Unknown enum value → validation error (error envelope defined later).
- Empty value (`?product_type=`) → validation error (`query-parameter-conventions.md` §6).
- Lowercase alias (`?product_type=in_stock`) → validation error.
- CSV with any invalid member (`?product_type=IN_STOCK,FOO`) → validation error for the whole parameter (no partial application).
- Duplicate key (`?product_type=IN_STOCK&product_type=MADE_TO_ORDER`) → validation error (use CSV where supported).

## 10. Documentation Requirement (Per Contract)

Each enum-bearing filter must be documented per `query-parameter-conventions.md` §19:

```
Name:            product_type
Type:            enum (CLOSED)
Required:        No
Allowed:         IN_STOCK, MADE_TO_ORDER
Default:         none
Description:     Filters products by commercial type.
Validation:      CLOSED V1 enum; unknown value → validation error.
```

## 11. Out of Scope

Exact `request_status`/`enquiry_status`/`payment_status` value spellings, pagination metadata, status codes, and storage implementation belong to their owning phases (Groups H, J, I). This policy solely governs query-level enum handling for V1.
