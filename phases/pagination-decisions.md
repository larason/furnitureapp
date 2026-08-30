# Pagination Decisions — Phase 1.12

## Purpose

Records all final decisions from Phase 1.12 Pagination Convention per `phase-1.12.md` §55G. Each entry includes Decision ID, Decision, Reason, Alternatives, Affected resources, Future phase impact.

---

## 1.12-DEC-01 — Page-number pagination is the V1 strategy

- **Decision:** Version 1 uses **page-number / offset pagination** (`?page=` + `?per_page=`) for all normal collections. Cursor pagination is deferred (see 1.12-DEC-14).
- **Reason:** Simplest technically sound model for small-scale catalog and admin tables; trivial for Next.js/Flutter/Laravel; no evidence that V1 volumes require cursor traversal (`phase-1.12.md` §§8-9, §56).
- **Alternatives:** Cursor-only or hybrid per-resource. Rejected — fashionable complexity without measured need.
- **Affected resources:** All collections listed in `pagination-resource-policy.md` (Products, Orders, Notifications, Requests/Enquiries, Users).
- **Future phase:** Phase 1.13 envelope will place pagination metadata; cursor may be introduced only with documented evidence.

## 1.12-DEC-02 — Pages are 1-based

- **Decision:** `page=1` is first page. `page=0` is validation error.
- **Reason:** Human-intuitive, e-commerce expectation, UI control simplicity (`phase-1.12.md` §10).
- **Alternatives:** Zero-based (`page=0` first). Rejected.
- **Affected resources:** All paginated collections.
- **Future phase:** Client pagination components.

## 1.12-DEC-03 — Page size parameter is `per_page`

- **Decision:** Canonical name is `per_page` (snake_case). No aliases `limit`, `page_size`, `items_per_page`, `count`, `offset`, `page_size` are valid pagination size names.
- **Reason:** Established by Phase 1.11 and reused by Phase 1.12 §11; one project-wide name.
- **Alternatives:** `limit`/`page_size`. Rejected — would split allow-list and break `query-parameter-conventions.md`.
- **Affected resources:** All paginated collections.
- **Future phase:** Resource contracts, OpenAPI.

## 1.12-DEC-04 — Global default `per_page` is 20

- **Decision:** When `per_page` is omitted, the server applies **20**.
- **Reason:** Reasonable starting value for catalog and tables (`phase-1.12.md` §§12, 56), documented rather than framework default.
- **Alternatives:** Larger default (e.g., 50) or framework implicit default. Rejected — would hide contract and increase initial payload.
- **Affected resources:** All paginated collections (uniform).
- **Future phase:** Performance tuning may adjust with evidence, but will be documented change.

## 1.12-DEC-05 — Maximum `per_page` is 100 (server-enforced)

- **Decision:** `per_page > 100` is a validation error. The server never returns 1000 records because the client asked for them.
- **Reason:** Resource protection against unreasonable DB/memory usage (`phase-1.12.md` §§13, 37, 56). Starting point 100, tunable with performance evidence but must exist.
- **Alternatives:** No maximum or silent clamping (`1000` → `100`). Rejected — hides contract and allows resource exhaustion.
- **Affected resources:** All paginated collections.
- **Future phase:** Performance testing may tune maximum.

## 1.12-DEC-06 — Minimum `per_page` is 1

- **Decision:** `per_page < 1` (0, -10) is validation error.
- **Reason:** Unambiguous minimum (`phase-1.12.md` §14, §56); `per_page=0` is not treated as “no pagination”.
- **Alternatives:** Treat `0` as all records or as default. Rejected.
- **Affected resources:** All paginated collections.
- **Future phase:** Validation layer.

## 1.12-DEC-07 — Invalid pagination values are validation errors

- **Decision:** `page=0/-1/abc/1.5/empty`, `per_page=0/-10/abc/1.2/empty`, `per_page=1000`, and unknown pagination params (`?limit`, `?offset`, `?page_size`) are **validation errors** (error envelope deferred to Phase 1.13), not silent defaults or clamped corrections.
- **Reason:** Explicit validation surfaces misuse (`phase-1.12.md` §15, §56); unknown params rejected per `query-parameter-conventions.md` §5.
- **Alternatives:** Silent fallback to defaults. Rejected.
- **Affected resources:** All paginated collections.
- **Future phase:** Error contract (Phase 1.13).

## 1.12-DEC-08 — Missing `page`/`per_page` semantics

- **Decision:** Missing `page` → `1`; missing `per_page` → **20**. Consistent across all paginated collections.
- **Reason:** Predictable behavior (`phase-1.12.md` §16, §56).
- **Alternatives:** Per-resource different defaults in V1. Rejected — would confuse clients.
- **Affected resources:** All paginated collections.
- **Future phase:** Resource contracts may document explicit exception only with justification.

## 1.12-DEC-09 — Pagination only for collections

- **Decision:** Pagination applies to collections (`products`, `orders`, `notifications`, `requests`, `enquiries`, admin lists, `categories` where appropriate). It does not apply to single-item resources (`/products/{product}`, `/orders/{order}`, `/requests/{request}`, etc.).
- **Reason:** Collection vs item distinction (`phase-1.12.md` §17, `endpoint-naming-conventions.md` §3).
- **Alternatives:** Paginating single resources. Rejected.
- **Affected resources:** See `pagination-resource-policy.md` classification.
- **Future phase:** Resource contracts.

## 1.12-DEC-10 — Canonical metadata is `current_page, per_page, total, last_page, has_next, has_previous`

- **Decision:** Required metadata names are exactly `current_page`, `per_page`, `total`, `last_page` (with `has_next`, `has_previous` booleans). No aliases `currentPage`, `page_size`, `totalItems`, etc. Envelope shape (`data`/`meta`) is deferred to Phase 1.13 but naming is fixed here.
- **Reason:** One canonical naming (`phase-1.12.md` §§46-47, `pagination-metadata-policy.md`); useful minimal set without excess.
- **Alternatives:** Ten-field framework paginator shapes. Rejected — excessive.
- **Affected resources:** All paginated collections.
- **Future phase:** Phase 1.13 will place metadata in envelope.

## 1.12-DEC-11 — Empty dataset and empty page behavior

- **Decision:** `total=0` → `current_page=1`, `last_page=1`, `has_next=false`, `has_previous=false`, `data=[]` (not `last_page=0`). Page beyond `last_page` → valid empty `data[]` with `current_page` echoing requested page, `total`/`last_page` real, `has_next=false`; not a validation error nor `404`. Uniform across resources.
- **Reason:** Client simplicity, no page-zero logic (`phase-1.12.md` §§24-26, §56). Predictable metadata per `pagination-metadata-policy.md` §4.
- **Alternatives:** `last_page=0` for empty, or error for beyond-range page. Rejected — inconsistent and awkward for Next.js/Flutter.
- **Affected resources:** All paginated collections.
- **Future phase:** Phase 1.13 envelope.

## 1.12-DEC-12 — Navigation links deferred (metadata-only V1)

- **Decision:** V1 returns **numeric metadata only**, not full navigation URLs (`first`/`prev`/`next`/`last` absolute or relative links). If Phase 1.13 later adds links, they will be additive, absolute or relative consistently, with query preservation.
- **Reason:** Absolute URLs become problematic across `local`/`staging`/`production`; clients can construct `?page=N` from metadata (`phase-1.12.md` §§20-21). Keep V1 minimal.
- **Alternatives:** Full navigation links in V1. Rejected — premature and environment-sensitive.
- **Affected resources:** All paginated collections.
- **Future phase:** Phase 1.13 may add optional links as non-breaking additive.

## 1.12-DEC-13 — Query preservation for future links

- **Decision:** If navigation links are introduced, they must preserve active query context (`category`, `product_type`, `availability`, `min_price`/`max_price`, `search`, `sort`/`sort_direction`, `order_status` etc.) with deterministic ordering.
- **Reason:** Pagination navigation must not drop filters (`phase-1.12.md` §22, §46).
- **Alternatives:** Links without query context. Rejected.
- **Affected resources:** All paginated collections (future).
- **Future phase:** Phase 1.13 link design if adopted.

## 1.12-DEC-14 — Deterministic ordering with tie-breaker is mandatory

- **Decision:** Every paginated collection has a deterministic default ordering; when a primary sort key is not unique, the implementation adds an **implicit unique tie-breaker `id ASC`** after the primary field (`ORDER BY <primary> <dir>, id ASC`). This is required for stable page boundaries and is documented in `query-sorting-policy.md` §6 and `pagination-consistency-policy.md` §2. The tie-breaker does not count toward V1 single-sort limit.
- **Reason:** Non-unique `price`/`name`/`order_status`/`created_at` would otherwise cause duplicates/missing records between pages (`phase-1.12.md` §§27, 51-52, §57 ordering checklist).
- **Alternatives:** Database natural order. Rejected — undocumented and unstable.
- **Affected resources:** All paginated collections (Products, Orders, Notifications, etc.).
- **Future phase:** Group E/I ordering implementation; contracts document `Tie-breaker: id ASC`.

## 1.12-DEC-15 — Search → Filter → Sort → Paginate pipeline (single canonical order)

- **Decision:** Logical pipeline is **Search → Filter → Sort (+ tie-breaker) → Paginate** — single canonical order across all policies (aligns `phase-1.12.md` §30 and `pagination-consistency-policy.md` §3). `total`/`last_page` reflect the searched + filtered count. `Filter → Search` vs `Search → Filter` is not two variants; both are narrowing before sort, but the documented order is **Search → Filter**.
- **Reason:** Correct `total` and user expectations (`phase-1.12.md` §§29-30, `pagination-consistency-policy.md` §3). Paginating before narrowing would produce wrong counts. Single order prevents implementation contracts from diverging.
- **Alternatives:** `Filter → Search → Sort → Paginate` as a separate documented variant, or paginate before filter. Rejected — keep one pipeline; the former is not a distinct semantics and the latter would produce wrong counts.
- **Affected resources:** All filtered/searched collections.
- **Future phase:** Implementation ordering.

## 1.12-DEC-16 — Changing-data behavior is current-state

- **Decision:** Pagination reads are **current-state**, not snapshot. Inserts/deletes between `page=1` and `page=2` fetches may cause duplicates/shift; this is acceptable for V1 browsing. Not made globally transactional across HTTP requests.
- **Reason:** Ordinary browsing tolerates movement; snapshot consistency only if real business requirement exists (`phase-1.12.md` §§32-34, §§53-54, `pagination-consistency-policy.md` §5-6).
- **Alternatives:** Snapshot-consistent pagination in V1. Rejected — over-engineering for small scale.
- **Affected resources:** All paginated collections, especially rapidly changing admin feeds.
- **Future phase:** Introduce snapshot pagination only with explicit requirement.

## 1.12-DEC-17 — Authorization-scoped counting

- **Decision:** `total`, `last_page` and page membership are computed over the **authorized dataset only**. `GET /me/orders?page=2` counts only caller's orders; `total_orders` global is never exposed via customer pagination. Unauthorized `Order`/`Cart`/`Payment` pagination attempts are rejected before counting.
- **Reason:** Prevent information leakage and bypass (`phase-1.12.md` §§42-43, `pagination-security-policy.md` §§3-4).
- **Alternatives:** Global counts. Rejected — leaks.
- **Affected resources:** `me/*`, admin collections.
- **Future phase:** Group D policies.

## 1.12-DEC-18 — Page-size as resource protection and caching

- **Decision:** `per_page` max (100) is a **resource-protection limit** (`phase-1.12.md` §37) and `page`/`per_page` plus filters/sorting are part of the **cache key**; `?page=1` vs `?page=2` are distinct. For protected collections caching is `private` / `Vary: Authorization` (see `pagination-security-policy.md`).
- **Reason:** Prevent `?per_page=100000` exhaustion and cache confusion (`phase-1.12.md` §44).
- **Alternatives:** No limit or shared cache for protected pages. Rejected — security/pressure risk.
- **Affected resources:** All paginated collections.
- **Future phase:** Caching design (later).

## 1.12-DEC-19 — Platform neutrality and admin consistency

- **Decision:** Same `page`/`per_page` grammar for Next.js (page navigation, SEO), Flutter (infinite scroll / load-more) and Admin tables; no separate admin pagination grammar. Different default `per_page` for admin not introduced in V1.
- **Reason:** API is platform-neutral (`phase-1.12.md` §§38-41, §57 clients checklist).
- **Alternatives:** Separate admin grammar or admin-specific defaults. Rejected — fragments convention.
- **Affected resources:** All clients.
- **Future phase:** UI components may add load-more wrappers but use same params.

## 1.12-DEC-20 — Closed enums remain closed under pagination

- **Decision:** Enum filters (`?product_type=IN_STOCK`, `?order_status=PAID`, etc.) remain **CLOSED** when paginated; `?product_type=SOMETHING_NEW` is validation error.
- **Reason:** `query-enum-policy.md` and `phase-1.12.md` §45; pagination does not relax enum closure.
- **Alternatives:** Open enum on paginated collections. Rejected — would reintroduce OPEN interim rule.
- **Affected resources:** All enum-filtered collections.
- **Future phase:** Enum expansion follows versioning strategy.

## 1.12-DEC-21 — Cursor pagination deferred for V1 — vocabulary aligned

- **Decision:** **No Version 1 resource requires cursor pagination.** Cursor is deferred; criteria for later introduction are measured `COUNT(*)` cost, high-volume feed instability, or snapshot requirement. Hybrid without justification is not allowed. **Vocabulary:** `phase-1.12.md` §43 `OPTIONAL` maps to `pagination-resource-policy.md` `DEFERRED` (single vocabulary: `REQUIRED / NOT NEEDED / DEFERRED (=OPTIONAL)`).
- **Reason:** Evaluation shows small-scale business does not evidence cursor need (`phase-1.12.md` §33, `pagination-cursor-policy.md`). `DEFERRED` collections (Categories, customer `me/requests`, `me/enquiries`) are not omitted — they are page-pagination candidates deferred to their contracts, not cursor candidates.
- **Alternatives:** Adopt cursor for notifications/orders preemptively. Rejected — complexity without evidence.
- **Affected resources:** All — classification in `pagination-resource-policy.md` is `REQUIRED` (Products, Orders, Notifications, admin Requests/Enquiries/Users), `NOT NEEDED` (Images, Variants, Cart Items, Order Items, Tracking, Payments), and `DEFERRED (=OPTIONAL)` (Categories, customer `me/requests`, `me/enquiries`); none `cursor`.
- **Future phase:** Introduce cursor only with documented evidence and token-security (opaque tokens, no secrets).
