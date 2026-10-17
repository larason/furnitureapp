# Phase 19.9 — Inventory Management UI (Admin)

## Status

**PASS for the implementation, integration, test and gate criteria of Phase 19.9.**

One documented limitation is carried forward, outside this agent's permitted scope: the
**authenticated live smoke test was not performed** (F11 — it needs the owner's operator
credential and a running backend). Everything below that is marked *verified* was verified
by automated tests, the production build, or a direct reading of the running repository; it
is **not** evidence of a live authenticated operator walkthrough.

No backend, database, API-contract, OpenAPI or customer-frontend file was modified. The
frozen `InventoryResource` and the frozen `INV-001..003` request/response shapes are
unchanged.

This session **resumed** Phase 19.9. On resume, the transport change (`Idempotency-Key`),
`lib/api/idempotency.ts`, `inventory.manage` in the permission catalog, the navigation
placeholder removal, and the six `lib/catalog/inventory-*` modules were already present as
uncommitted work. This session completed the phase: the four list components, the
detail/adjustment dialog, the real page, the test suites, the documentation, the quality
gates and this report. One pre-existing defect in the resumed work was fixed (see §3).

---

## 1. Objective and scope

Replace the `/inventory` placeholder with a permission-aware operational inventory workflow
built on the already-approved endpoints:

1. `INV-001` `GET /api/v1/inventory` — list and filter persisted stock rows.
2. `INV-002` `GET /api/v1/inventory/{inventory}` — authoritative record detail.
3. `INV-003` `POST /api/v1/inventory/{inventory}/adjust` — audited adjustment with an
   `Idempotency-Key`.

Out of scope and untouched: the Laravel backend, the frozen API contract, the OpenAPI
document, the database schema, catalog seeders and fixture state, cart/checkout/order/payment
and reservation workflows, the audit-log screen, bulk adjustment, inventory or variant
creation, sorting and export, and the customer-facing Next.js and Flutter applications. No
dependency was added.

## 2. Audit performed before coding

Verified against the running repository, not the phase prose:

- `docs/api/openapi.yaml` §`Inventory`/`InventoryAdjustRequest` and the
  `/inventory`, `/inventory/{inventory}`, `/inventory/{inventory}/adjust` operations, plus
  `InventoryProductFilter`/`InventoryVariantFilter`/`InventoryLocationFilter`
  (`product` ≤100, `variant` `^var_[0-9a-z]+$` ≤100, `warehouse_location` ≤255 exact).
- `docs/api/api-resources.md §10.2/§10.2.1` (one ProductStock row = one Variant at one
  `warehouse_location`; `available_quantity` derived and never persisted; quantities are
  integer **units**, not minor currency units) and `docs/api/api-conventions.md` §30.5
  (sign rules, `Idempotency-Key` semantics, `409 DUPLICATE_OPERATION`, 20/min/staff limit).
- `docs/api/openapi.yaml` `CAT-005` (unpaginated active variant list with `id`/`sku`/`name`)
  and the public `CAT-002` product detail, which are the only documented label sources
  readable with `inventory.view` alone.
- `backend/laravel/app/Support/PermissionName.php` — `inventory.view` and `inventory.manage`
  both exist and are the exact identifiers used.
- The existing admin primitives (`DataTable`, `PageHeader`, `EmptyState`, `ErrorState`,
  `PageContainer`, `admission-state`), the request-scoped transport in
  `lib/api/server-request.ts` + `lib/api/client.ts`, the route guard chain
  (`requireAdminRouteAccess` → `resolveAdminRouteAccess` → `resolveOperationalIdentity`), and
  the existing test/`package.json` conventions.
- The resumed 19.9 work in full, which is where the one defect in §3 was found.

## 3. Files changed

| File | Change |
|---|---|
| `lib/api/client.ts` | **Already present on resume.** `AdminApiRequest` gained the narrow, typed optional `idempotencyKey`; when supplied it is validated as a UUID and set as the `Idempotency-Key` header. |
| `lib/api/idempotency.ts` | **Already present on resume.** `isIdempotencyKey` (UUID shape) and `newIdempotencyKey` (`crypto.randomUUID()`), shared by the transport and the dialog. |
| `lib/auth/permission-names.ts` | **Already present on resume.** `INVENTORY_MANAGE: "inventory.manage"` added to the frontend catalog. |
| `lib/navigation/admin-navigation.ts` | **Already present on resume.** `placeholder`/`plannedFor` removed from the `/inventory` entry; a real link gated by `inventory.view`. |
| `lib/catalog/inventory-resource.ts` | **Already present on resume.** Defensive parser for the eight documented `Inventory` fields; `updated_at` carried as an explicit unavailable value. |
| `lib/catalog/inventory-query.ts` | **Already present on resume.** The filter vocabulary, URL state, request query and `inventoryListHref`. Changed here: `INVENTORY_LIST_DEFAULT_PER_PAGE` is now exported so the filter form can preserve a non-default page size. |
| `lib/catalog/inventory-mutations.ts` | **Already present on resume.** Dependency-free shared vocabulary: reason set and labels, sign rules, advisory pre-flight, preview, intent signature, outcome catalogue and safe messages. |
| `lib/catalog/inventory-label.ts` | **Already present on resume.** Pure label mapping: `uniqueInventoryProductIds`, `catalogProductName`, `catalogVariantEntries`, `buildInventoryLabels`, subject builders. |
| `lib/catalog/inventory-api.ts` | **Already present on resume.** INV-001/002/003 calls, the read-outcome conversion, failure classification, and the deduplicated, concurrency-bounded label reads. |
| `lib/catalog/inventory-actions.ts` | **Already present on resume, one defect fixed here.** The dangling `export type { InventoryAdjustmentRequest }` re-exported a type that was never imported, so `tsc --noEmit` failed with `TS2552`. Nothing consumed it; the line was removed. |
| `lib/catalog/inventory-presentation.ts` | **Changed here.** Added the serializable `InventoryRow` shape and `inventoryRows(records, labels)`, plus `INVENTORY_SKU_UNAVAILABLE`. A `Map` cannot cross the Server/Client boundary, so labels are flattened into plain objects for the client table. |
| `components/catalog/inventory-failure-state.tsx` | **New.** Distinct session/forbidden/unavailable/throttled/unreadable states for an inventory read. |
| `components/catalog/inventory-list-filters.tsx` | **New.** Native `GET` filter form for the three exact-identifier filters; no selector that could imply a complete option set. |
| `components/catalog/inventory-list-pagination.tsx` | **New.** Server-provided totals and filter-preserving page links. |
| `components/catalog/inventory-table.tsx` | **New.** Client Component table (eight columns) whose only action opens the detail/adjustment dialog. |
| `components/catalog/inventory-adjustment-dialog.tsx` | **New.** Client Component detail + adjustment dialog: INV-002 re-read on open, advisory pre-flight, preview, idempotency-intent tracking, confirmed (never optimistic) refresh. |
| `app/(protected)/inventory/page.tsx` | **Rewritten** from `AdminRoutePlaceholder` into the real INV-001 workflow. |
| `components/layout/admin-overview-body.tsx` | One factual sentence updated: the implemented modules now include inventory. No metric, count, chart or new data source was added. |
| `components/catalog/inventory-*` tests | See §10. |
| `tests/inventory.test.tsx` | **New.** 52 deterministic tests. |
| `tests/api-client.test.ts` | +5 transport tests for the new header. |
| `tests/overview.test.tsx` | Two assertions updated for the implemented inventory module (see §10). |
| `package.json` | `tests/inventory.test.tsx` added to the `test` script. |
| `frontend/admin/AGENTS.md` | New **Inventory module** rule block. |
| `frontend/admin/README.md` | New **Inventory module (Phase 19.9)** section; permission table row and "Phase exclusions" updated. |
| `docs/findings-and-limitations-register.md` | F11 updated (dated) to include `/inventory`; still OPEN, not resolved. |
| `phases/group-S-phases.md` | Owner-managed; replaced by its owner with this phase's specification and **not** part of this change set. |

## 4. How each endpoint is integrated

| Endpoint | Method / body | UI | Client path |
|---|---|---|---|
| `INV-001` `GET /inventory` | `GET`, `product`/`variant`/`warehouse_location`/`page`/`per_page` | List page: filters, table, pagination | `fetchInventoryPage` → `inventoryListRequestQuery` |
| `INV-002` `GET /inventory/{inventory}` | `GET`, `inv_...` path id | Dialog, on open and after a confirmed adjustment | `loadInventorySnapshotAction` → `fetchInventoryRecord` |
| `INV-003` `POST /inventory/{inventory}/adjust` | `POST`, `{quantity_delta, reason}` + `Idempotency-Key` | Dialog submit | `adjustInventoryAction` → `submitInventoryAdjustment` |

- The endpoint is `/api/v1/inventory`, **not** `/api/v1/admin/inventory`.
- The `inv_...` identifier is passed through verbatim (`encodeURIComponent` only); it is never
  lower-cased, normalised or treated as proof of authorization. Unknown and non-Inventory
  identifiers stay masked as `404`.
- The `INV-003` response body is deliberately ignored: the dialog re-reads `INV-002` and the
  action revalidates `/inventory`, so no local copy of a quantity is ever authoritative.
- No replacement endpoint, direct database write, second fetch path or bypass of the Laravel
  `InventoryAdjustmentService` exists.

## 5. The exact transport change

`lib/api/client.ts`, inside the existing `createAdminApiClient` request closure:

```ts
const headers = new Headers({ Accept: "application/json" });
if (token) headers.set("Authorization", `Bearer ${token}`);
if (input.idempotencyKey !== undefined) {
  if (!isIdempotencyKey(input.idempotencyKey)) throw new ApiConfigurationError("Idempotency-Key must be a UUID.");
  headers.set("Idempotency-Key", input.idempotencyKey);
}
```

- The option is a typed field on `AdminApiRequest` (`idempotencyKey?: string`), not a header
  bag: there is no caller-controlled header override, so `Authorization`, `Accept` and
  `Content-Type` cannot be replaced by a caller, and the bearer token never leaves the
  server-side transport.
- When the option is absent the request is unchanged — no forged header, no extra branch on
  the serialization path.
- The transport still writes exactly three headers plus the new one
  (`Accept`, `Authorization`, `Content-Type`, `Idempotency-Key`), asserted by counting the
  `headers.set(...)` literals in the source.
- A malformed key is rejected with `ApiConfigurationError` **before** any network call.
- `no-store` behaviour is preserved: `cache: token ? "no-store" : undefined` is untouched.
- No separate `fetch` implementation was added for inventory.

## 6. Idempotency-key lifecycle

- The key belongs to the **logical intent**, identified by
  `${inventoryId}:${reason}:${quantity_delta}` (`inventoryAdjustmentSignature`).
- A new intent generates `crypto.randomUUID()`. There is no timestamp, `Math.random()` or
  constant key; a test asserts 50 generated keys are distinct and UUID-shaped.
- Retrying the identical intent reuses the stored key (`intent.signature === signature`).
  Changing the quantity or the reason changes the signature, so the previous key is **not**
  reused and a new one is generated.
- A confirmed success clears the intent (`setIntent(null)`) and the draft, so the next
  adjustment receives a fresh key.
- A failure of any kind — `409`, `422`, `403`, `404`, `429`, timeout or network — keeps the
  intent, so an offered retry carries the original key and the identical payload.
- A `409 DUPLICATE_OPERATION` is surfaced as a duplicate-intent message that tells the
  operator to change the quantity or reason (which is precisely what allocates a new key) or
  to close the dialog. The implementation never answers a `409` by generating another key
  and immediately repeating a possibly-completed adjustment.
- Nothing retries automatically: there is exactly one `await submitInventoryAdjustment(...)`
  per action invocation, no loop and no timer.
- The key is never part of the JSON body — asserted structurally against the body literal and
  by a transport test that inspects the serialized bytes.

## 7. `inventory.view` and `inventory.manage`

- The page repeats `requireAdminRouteAccess(ROUTE)` (which resolves `inventory.view` through
  `resolveAdminRouteAccess`) **before** `await searchParams` and before any API call, and
  renders `access.denial` when not granted. Navigation visibility is presentation only.
- The snapshot action re-checks `INVENTORY_ROUTE.permission`/`role`; the adjustment action
  re-checks the exact `inventory.manage` through the same `resolveAdminRouteAccess` path,
  because Server Functions are directly POST-reachable.
- The dialog renders the adjustment form only when `canManage` is true, and
  `canManage = hasPermission(access.identity, AdminPermissionName.INVENTORY_MANAGE)` — the
  **effective permission** Laravel returned for this identity, never the role. A view-only
  identity sees the snapshot and an explicit explanation instead of a disabled-looking form,
  and its server action call is refused regardless of what the browser sends.
- An `ADMIN` role holding only `inventory.view` is `forbidden` for `inventory.manage` — there
  is no wildcard and no implicit bypass.
- Hidden controls are not the boundary: the action re-authorizes and then validates the
  payload shape (identifier pattern, CLOSED reason, sign, UUID key) before forwarding.

## 8. Product and variant label strategy

The frozen `InventoryResource` was **not** changed. Labels are resolved from the existing
public catalog reads, which are the only documented sources readable with `inventory.view`
alone:

1. Read the INV-001 page first.
2. `uniqueInventoryProductIds(records)` extracts the distinct product IDs in first-seen order.
3. One `GET /products/{product}` (name) and one `GET /products/{product}/variants` (CAT-005,
   `id`/`name`/`sku`) per distinct product — never per inventory row.
4. The fan-out is bounded by `mapWithConcurrency(..., LABEL_LOOKUP_CONCURRENCY = 4)`; an
   unbounded `Promise.all(ids.map(...))` is asserted absent.
5. `buildInventoryLabels` maps product ID → name and variant ID → `{name, sku}`; the page
   flattens the result into plain `InventoryRow` objects (`inventoryRows`) because a `Map`
   cannot cross the Server/Client boundary.

**Fallback semantics:** each lookup catches independently and returns `null`, so a failed or
missing product/variant leaves that label unresolved while the inventory record stays
visible. The table renders “Product details unavailable” / “Variant details unavailable” /
“SKU unavailable” and keeps the opaque `prd_...`/`var_...`/`inv_...` identifier as an
explicit technical reference — an identifier is never presented as a human name. A label
failure is never classified as an inventory failure: the row list is derived from the
INV-001 outcome only, and the empty state is never reached because a label lookup failed.

Request-count behaviour for a page of *n* rows spanning *p* distinct products: **2p** catalog
reads, at most 4 in flight, and 1 inventory read.

## 9. Pagination, filters and ordering

- Filter state lives in the URL (native `GET` form), so the list stays a Server Component and
  no request is issued per keystroke.
- Only `product`, `variant`, `warehouse_location`, `page` and `per_page` are ever emitted;
  `variant` is dropped unless it matches `^var_[0-9a-z]+$`; `product`/`warehouse_location` are
  trimmed to their contract maxima; `page` falls back to 1 and `per_page` to 20 (max 100).
  Malformed and out-of-range values degrade to "no filter" rather than being sent to be
  rejected.
- The filter form contains no `page` field, so applying a filter resets pagination to page 1;
  page links preserve the current filters and a non-default `per_page` (carried through a
  hidden field).
- The API's `updated_at DESC, id ASC` ordering is preserved verbatim: no sort parameter, no
  client-side re-sort or slice.
- Totals (`total`, `last_page`, `has_next`, `has_previous`) come from `meta.pagination`.
- Loading, empty, failure, forbidden and rate-limited states are distinct; `!page.ok` is
  handled before the empty check, so an API failure can never render as an empty list, and an
  unreadable response is classified as invalid data rather than a service outage.

## 10. Adjustment validation, sign rules and below-reserved handling

- The form sends exactly `quantity_delta` and `reason`; the reason list is the CLOSED contract
  set (`STOCK_RECEIPT`, `CORRECTION`, `DAMAGE`, `RETURN`, `AUDIT_ADJUSTMENT`).
- Sign rules mirror Laravel: `STOCK_RECEIPT`/`RETURN` require `> 0`, `DAMAGE` requires `< 0`,
  `CORRECTION`/`AUDIT_ADJUSTMENT` accept either. The operator types a signed whole number and
  the selected reason's rule is stated as helper text; an invalid combination is blocked
  before submission and the entered sign is never silently rewritten.
- `parseQuantityDelta` accepts only `^[+-]?\d+$` within safe-integer range, so zero, decimals,
  exponent notation, empty input and overflow-sized input are rejected.
- A live preview shows the resulting quantity, computed in safe-integer arithmetic; a result
  that would be negative, would fall below `reserved_quantity`, or would overflow is shown as
  a blocking validation message and disables submission.
- All of this is **advisory**: Laravel re-validates every rule inside the transaction and its
  own `422` field message is preferred over the generic fallback. The dialog also re-reads
  INV-002 rather than trusting the row it was opened from, so a concurrent change is handled
  by the backend.
- Duplicate submission is prevented by a ref claimed before any await plus the disabled
  submit control; the draft is controlled, so a failed submit keeps the operator's input.
- `422` → Laravel's field message; `403` → the permission-denial treatment; `404` → "no longer
  available"; `409` → duplicate-intent guidance; `429` → rate-limit message with
  `Retry-After` when present; timeout/network → **uncertain** (warning severity), which keeps
  the key and is never reported as success.

## 11. Tests and gates

### Automated tests

`npm run test` → **254 tests, 254 passed, 0 failed, 0 skipped** (was 197 before this phase).

| Suite | Change | Notes |
|---|---|---|
| `tests/inventory.test.tsx` | **+52 (new)** | authorization and route protection; permission separation and no-bypass; endpoint/parameter/ordering assertions; resource parsing and rejection; label resolution, deduplication, concurrency bound and fallbacks; adjustment vocabulary, sign rules, delta parsing, below-reserved guard, double-submit guard; idempotency lifecycle; failure classification; scope boundaries; SSR rendering of the table, filters, pagination and failure states |
| `tests/api-client.test.ts` | 7 → 12 (+5) | header present and body clean; no key ⇒ byte-for-byte unchanged; malformed key rejected before the network; header allow-list; UUID generation randomness |
| `tests/overview.test.tsx` | count unchanged (2 assertions updated) | the "implemented modules" sentence now names inventory; the displayed-roadmap-phase assertion now derives its expected set from the registry plus the two prose-scheduled areas, so an implemented destination can no longer keep advertising a status |

**Verification honesty:** `frontend/admin` has no DOM library/`@testing-library`, so there is
**no simulated click, typing or dialog-mount test**; interaction behaviour is asserted against
the rendered markup, the pure decision modules, and the sources that produce it. The MUI
`Dialog` is portal-rendered and does not appear in static markup, so the dialog's contract is
asserted through `lib/catalog/inventory-mutations.ts` plus source assertions rather than a
rendered interaction. No assertion was weakened or replaced with a snapshot.

### Gates

| Gate | Command | Result |
|---|---|---|
| Lint | `npm run lint` | passed — 0 errors, 0 warnings |
| Types | `npm run typecheck` | passed, exit 0 |
| Tests | `npm run test` | **254 passed, 0 failed, 0 skipped** |
| Build | `npm run build` (Next.js 16.3.8, Turbopack) | passed, exit 0; `/inventory` is built as a dynamic server-rendered route |
| Whitespace | `git diff --check` | clean, exit 0 |

No backend gate was run because no backend file was touched. The final diff was inspected for
unrelated changes, permission bypasses, missing idempotency headers, unstable retry keys,
unbounded lookups, false empty states, invented labels/quantities, secret exposure and
fixture-state changes: none found.

## 12. Real authenticated workflow

**Not verified — pending the owner (F11).** Live testing was unavailable in this session:
nothing is listening on the development backend origin (`127.0.0.1:8000`) and no operator
Clerk credential exists in the agent session. The checklist below therefore remains
outstanding, and the automated suites above must not be read as evidence that it passed:

1. an authorized operator opens Inventory; 2. the table shows real records; 3. product and
variant labels resolve or safe fallbacks appear; 4. filters and pagination work against the
real API; 5. a view-only identity cannot adjust; 6. the dialog shows the record's current
values from INV-002; 7. a small deliberate adjustment succeeds and the UI updates from
authoritative data; 8. an identical retry does **not** apply the change twice; 9. a wrong-sign
or below-reserved adjustment is rejected safely; 10. a failed/uncertain request does not
cause a blind new adjustment; 11. narrow-viewport and keyboard interaction stay usable
(`/inventory` is a dialog-based screen, not a new route hierarchy).

`/inventory` has never been rendered by a signed-in operator.

## 13. Existing product-fixture / request-first-release concern (unchanged)

The audit reports that published `IN_STOCK` products conflict with the separate
demo-fixture-removal plan. This phase **did not** resolve or alter that conflict: no catalog
seeder, product state, product type or fixture was touched. It remains an existing
product/catalog decision requiring separate owner action.

Likewise, because variants cannot currently be created, the adjustment interface operates
only on inventory records that already exist; the UI offers no way to create inventory for a
new variant.

## 14. Remaining limitations and owner actions

- **F11 (OPEN):** the authenticated smoke test in §12. Owner action; this report and the
  findings register keep it open.
- **`reserved_quantity` reads zero by design.** Cart and checkout reservations are deferred,
  so the returned value is displayed as-is and the page says so; no reservation workflow is
  presented as active.
- **Only the contract's identifiers are filterable.** There is no product-name search, no SKU
  filter and no warehouse-option endpoint, so the filters are labelled exact-identifier inputs
  with explanatory helper text rather than selectors. A dropdown was deliberately not built,
  because no approved endpoint enumerates the values and the current page is not the complete
  option set.
- **Label resolution costs 2 catalog reads per distinct product on the page.** Bounded and
  deduplicated, but a future inventory page with many distinct products would benefit from an
  embedding decision (`inventory` + `product`/`variant` labels) — an additive v2 contract
  question for the owner, not a change to be made here.
- **F12 (unchanged):** Fallow is unavailable in this environment, so dead-code/duplication
  figures are unverified; this is not a gate.
- **F10 evidence is now stale by one string:** the implemented `/inventory` entry no longer
  carries a `plannedFor` phase string, so the register's measured count of user-visible
  `Phase 19.x` source occurrences should be re-measured at the Group S closure, where F10 is
  owned. It was not re-measured here.
- **Roadmap status:** `phases/group-S-phases.md` is owner-managed and carries this phase's
  specification, not a status table, and the root `AGENTS.md` carried an owner change that was
  left untouched. Status lives here, in `frontend/admin/{AGENTS.md,README.md}` and in the
  findings register. No live smoke test was marked complete.

## 15. Acceptance criteria

| Criterion | Result |
|---|---|
| The inventory placeholder is replaced by a real, permission-aware admin workflow | Met |
| `INV-001` lists and filters real paginated inventory records | Met (`/api/v1/inventory`, server-side filters, server totals, API ordering preserved) |
| `INV-002` provides authoritative record details for inspection and adjustment | Met (re-read on dialog open and after every confirmed change) |
| `INV-003` submits valid adjustments using a UUID `Idempotency-Key` | Met (header-only, UUID, per-intent) |
| The API transport change preserves all existing requests | Met (5 transport tests; absent key ⇒ unchanged) |
| A repeated or uncertain request cannot casually become a second stock adjustment | Met (intent signature, key reuse, no auto-retry, `409` handling) |
| `inventory.view` and `inventory.manage` are checked independently, including in server actions | Met |
| Product and variant labels resolve from existing catalog endpoints without contract change or per-row requests | Met (deduplicated, concurrency-bounded, honest fallbacks) |
| The UI follows the real backend adjustment rules and refreshes from authoritative state | Met (advisory pre-flight, confirmed-not-optimistic, revalidation) |
| No fake inventory data or unrelated commerce features are introduced | Met |
| Tests and quality gates pass, and this report distinguishes automated from live verification | Met (§11 vs §12) |

## 16. Exit condition

Phase 19.9 is complete: the placeholder is replaced by a permission-aware inventory workflow;
INV-001/002/003 are integrated exactly as documented; the transport sends `Idempotency-Key`
without disturbing any other request; idempotency is bound to the logical intent; `inventory.view`
and `inventory.manage` are enforced independently on the server; product and variant labels
come from the existing catalog reads without touching the frozen inventory contract; the
adjustment rules mirror Laravel and the display converges on authoritative state; and all
applicable gates pass.

**Demonstrably true** except the live authenticated smoke test, which only the owner can run
(F11, §12).
