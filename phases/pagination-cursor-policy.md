# Pagination Cursor Policy — Version 1

## 1. Purpose

Records the Version 1 decision on **cursor pagination** for `GET` collections under `/api/v1`: whether it is used, why it is deferred, and what criteria would justify introducing it later without drifting into a hybrid model.

## 2. Version 1 Decision

**Cursor pagination is NOT required in Version 1.**

- All normal Version 1 collections use **page-number pagination** (`?page=` / `?per_page=`) per `pagination-convention.md`.
- No Version 1 resource is classified `cursor` in `pagination-resource-policy.md`.

## 3. Evaluation

Potential candidates considered per `phase-1.12.md` §33:

- Very large order history (per-customer and admin-wide)
- Very high-volume notification stream
- High-volume event/activity feeds

For the current small-to-medium furniture business scale, evidence does not justify cursor pagination:

- Catalog size is modest; Next.js/Flutter product browsing is page-oriented and SEO-friendly with page numbers.
- Order history per customer grows slowly; admin order table is operational but bounded and benefits from familiar page controls.
- Notification volume is not event-stream scale; page pagination with pull-to-refresh / load-more is sufficient.
- Database `COUNT(*)` for exact `total` remains acceptable for V1; approximate counts are not yet needed.

Therefore the simplest technically sound strategy (page pagination) is preferred over a fashionable cursor model (`phase-1.12.md` §8-9, §33).

## 4. Why Deferred (Not Just Undecided)

- **No hybrid:** V1 does not mix `page` pagination for some resources and `cursor` for others without explicit justification (`phase-1.12.md` §34, §57). A resource-by-resource mix would be justified only by a clear technical/business reason (e.g., notification stream exceeding page-pagination stability).
- **Future criteria that would justify cursor:** (all require evidence, not speculation)
  - Collection size exceeds page-pagination `COUNT(*)` performance budget (measured, not guessed).
  - Insert-heavy feed where page-pagination duplicates/missing between pages materially harms UX (e.g., real-time activity feed).
  - Requirement for snapshot-consistent traversal that page pagination cannot provide, with explicit product requirement.
- **If later justified:** Cursor design will be explicit per resource, e.g. `Products → page`, `Orders → page`, `Notifications → cursor (high append frequency)` with documented `Reason: high append frequency` per `phase-1.12.md` §49, and will define cursor encoding, ordering and page-size semantics anew.

## 5. Cursor Token Security (Future Only)

- If cursor pagination is later introduced, cursor values must not expose database secrets, sensitive information or implementation details unnecessarily (opaque, integrity-protected tokens). This is a **future requirement only**; no cursor encoding is designed in V1 (`phase-1.12.md` §35).

## 6. Out of Scope

No cursor tokens, cursor ordering implementation or cache configuration are defined in V1.
