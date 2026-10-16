# Phase 19.8B — Product Media API Contract & Backend

## Status

**PASS** for the implementation, contract, test and gate criteria of Phase 19.8B.

One documented **pending owner action** remains outside this agent's permitted file scope: the
roadmap status record (§11). It affects no code, contract or test delivered here.

The existing Phase 19.8A upload and preview behaviour is unchanged and still green.

---

## 1. Objective and scope

Add the backend and the frozen-contract documentation for four owner-approved product-media
mutations so a later admin phase can implement complete media management:

1. Delete a product image.
2. Edit a product image's alt text.
3. Select a product's primary image.
4. Reorder a product's images.

Out of scope and untouched: category media upload (categories still take an `image` URL string;
no backend support exists), any gallery UI (Phase 19.8C), customer-facing website/Flutter changes,
and any new dependency.

## 2. Audit performed before implementation

Verified against the running repository, not assumed:

- **CAT-009 boundary (reused verbatim):** `clerk.auth` + `staff-or-admin` + `permission:products.manage`
  + `throttle:operational-write`; response envelope `{data: ProductImage}`; `response()->noContent()`
  is the established `DELETE` shape (`CartController::removeItem`, `DeviceInstallationController::destroy`).
- **Validation convention:** FormRequests reject unknown input with `422 INVALID_VALUE` carrying the
  offending `field` (`_unknown_parameter => prohibited`); a missing required key is
  `422 MISSING_REQUIRED_FIELD`. Both codes already exist in `ApiErrorCode` — **no enum value was added**.
- **Primary-image invariant, verified on the live MariaDB 11.8.8 engine:** `product_images` really has
  a **stored generated column** `is_primary_guard` (`CASE WHEN is_primary …`) plus a unique index
  `product_images_unique_primary_per_product`, with no triggers. The `ProductImage` model additionally
  throws from `saving` if a second primary would exist, so the previous primary must be demoted before
  the new one is promoted.
- **Zero-primary is already legal:** `ProductImageSchemaTest::test_product_without_primary_image_returns_null_for_primary_image`
  proves the read path tolerates a product with no primary image.
- **Sort-order convention is inconsistent in existing data:** uploads assign 1-based values
  (`(int) null + 1`), while seeds/factories use `0`; live rows 1–6 confirm both.
- **Durable queue already exists and is used:** `QUEUE_CONNECTION=database`, `jobs`/`failed_jobs`
  migrated, `ShouldQueue` jobs already in the codebase. Attachments already have an
  attachment-specific cleanup mechanism.
- **No `PUT` route exists anywhere in the API**, and decision `1.10-DEC-06` records that *"No Version 1
  endpoint currently requires `PUT`"* (`phases/api-action-method-policy.md §12`).

## 3. Contract proposal and the decisions taken

The owner's suggested shape was `PUT …/{image}/primary` and `PUT …/images/order`. The audit found a
**stronger established convention**, which the phase instructed the agent to prefer and document:

| Operation | Proposed | **Delivered** | Why |
|---|---|---|---|
| Delete | `DELETE …/images/{image}` | same | Matches the repo's existing `DELETE` convention (`CART-004`, `DEV-002`). |
| Alt text | `PATCH …/images/{image}` | same | Only mutable field; matches the repo's `PATCH` partial-update convention. |
| Primary | `PUT …/{image}/primary` | **`POST …/{image}/primary`** | V1 defines no `PUT`. This is a controlled cross-row action, so it follows `POST /{resource}/{identifier}/{action}` (`/orders/{order}/cancel`, `/cart/merge`). |
| Reorder | `PUT …/images/order` | **`POST …/images/reorder`** | Same reason: a complete ordered-list replacement executed as a validated batch action, not a partial field update. |

Other decisions:

- **At most one primary, not exactly one** (ADR/API-FRZ-003): deleting the primary deliberately leaves
  the product with no primary; no silent auto-promotion. Reads fall back to the first image by
  `sort_order ASC, id ASC`.
- **Reorder normalises to contiguous 1-based positions** (`1..n`), matching the upload path and
  converging legacy `0`-based rows. The submitted list must be the product's **complete** image set.
- **Repeat `DELETE` returns `404`, not `204`** — the spec requires "documented not-found behaviour"
  for a missing image, and the record genuinely no longer resolves.
- **No invented per-array cap on reorder**: the list is bounded by the global JSON body limit
  (`security.max_json_body_bytes` → `413` above it).
- **Storage deletion is row-first with a durable retry** (ADR/BACKEND-058). DB↔object-store atomicity
  is **not** claimed.

## 4. Delivered endpoints

| ID | Method | Path | Success | Notes |
|---|---|---|---|---|
| `CAT-015` | `DELETE` | `/api/v1/products/{product}/images/{image}` | `204` | Row deleted in a product-locked transaction, then the object. Body ignored (target comes from the path + persisted record). |
| `CAT-016` | `PATCH` | `/api/v1/products/{product}/images/{image}` | `200 {data: ProductImage}` | Body `{alt_text: string\|null}` only; trimmed, max 500, blank → `null`. |
| `CAT-017` | `POST` | `/api/v1/products/{product}/images/{image}/primary` | `200 {data: ProductImage}` | No body accepted (any field → `422 INVALID_VALUE`); demotes the previous primary in the same transaction; idempotent. |
| `CAT-018` | `POST` | `/api/v1/products/{product}/images/reorder` | `200 {data: [ProductImage]}` | Body `{images: ["img_…"]}` = the complete set; normalised to `1..n`. |

Route names: `api.products.images.store` (existing), `api.products.images.update`,
`api.products.images.destroy`, `api.products.images.primary`, `api.products.images.reorder`.

**Ownership scoping:** `{image}` is resolved strictly inside `{product}` through
`resolveOperationalImage()`. An unknown, malformed (`/broken`), or **other-product** identifier all
return `404 RESOURCE_NOT_FOUND`, so the route cannot be used to discover or mutate another product's
media. All four are `Cache-Control: private`.

## 5. Invariants and validation

- **No client-writable `sort_order`, `is_primary`, `url`, `file_path`, storage key or disk.**
  `CAT-016` accepts only `alt_text`; `CAT-017` accepts nothing; `CAT-018` accepts only `images`;
  `CAT-009` still accepts only `image`. Tests assert each rejection and that persisted values are
  unchanged afterwards.
- **`CAT-018` set equality:** duplicates, unknown ids, other-product ids, non-string entries, and an
  **incomplete** list are all `422 INVALID_VALUE`. `images: []` is valid only for a product with no
  images. Metadata (`id`, `url`, `alt_text`, `is_primary`) is preserved; only `sort_order` is written.
- **`CAT-017` idempotency:** selecting the current primary returns `200` unchanged, with `sort_order`
  untouched.

## 6. Concurrency

`CAT-015` (delete), `CAT-017` (primary selection) and `CAT-018` (reorder) each lock the **`products` row**
(`lockForUpdate`) inside their transaction, so competing structural changes to the same product serialise;
`CAT-016` (`UpdateProductImageAltText`) performs a single-column write with no transaction or lock. The
`is_primary_guard` unique index is the last-resort database guard if a writer bypasses the service.

Proven on a **real MariaDB server** by `ProductImageMutationConcurrencyMysqlTest` (two independent
database sessions, no `RefreshDatabase`):

- a held product lock blocks a competing primary selection (MySQL `1205` lock-wait timeout), and after
  release exactly the requested image is primary;
- the unique guard refuses a second primary (`1062` duplicate entry) even when the lock is bypassed;
- a held product lock blocks a competing reorder, the blocked attempt leaves no partial ordering, and
  the last accepted complete order wins deterministically with contiguous positions.

SQLite cannot exercise either mechanism, which is why the proof is a `MysqlTest` suite.

## 7. Storage failure and residual risk

`CAT-015` order of effects: delete the record inside the product-locked transaction → commit → delete
the object using the record's persisted `file_path`.

- Storage provider reports failure → `App\Jobs\DeleteProductImageObject` (carries `disk` + `objectKey`,
  `tries = 5`, escalating backoff) is dispatched, `Log::warning('product_image.object_delete_deferred')`
  is written, and the response is still `204` because the authoritative record is gone.
- Retries exhausted → `failed()` logs `product_image.object_delete_failed` and the job lands in
  `failed_jobs` for operator inspection.
- A **missing** object is treated as already deleted: both the local adapter and S3/R2 report success,
  so a retry after manual cleanup is harmless.
- **Residual risk (documented, not hidden):** a permanently failing job can leave an orphaned stored
  **object**. A record is never resurrected and a product representation is never left pointing at a
  missing object. DB↔object-store atomicity is not claimed anywhere in the contract.

## 8. Files changed

**Backend (`backend/laravel`):**

- `routes/api.php` — four media-mutation routes registered after CAT-009.
- `app/Http/Controllers/Api/V1/ProductController.php` — `updateImage`, `destroyImage`,
  `selectPrimaryImage`, `reorderImages`, and the private `resolveOperationalImage` helper.
- `app/Support/ProductImageIdentifier.php` — added `decode(string): ?int`.
- `app/Http/Requests/UpdateProductImageRequest.php` *(new)*, `ReorderProductImagesRequest.php` *(new)*,
  `SelectPrimaryProductImageRequest.php` *(new)* — body-less/no-unknown-field contracts.
- `app/Services/ProductImages/UpdateProductImageAltText.php` *(new)*, `SelectPrimaryProductImage.php`
  *(new)*, `ReorderProductImages.php` *(new)*, `DeleteProductImage.php` *(new)*.
- `app/Jobs/DeleteProductImageObject.php` *(new)* — durable object-removal retry.
- Tests: `tests/Feature/ProductImageMutationApiTest.php` *(new)*,
  `tests/Feature/ProductImageMutationConcurrencyMysqlTest.php` *(new)*,
  `tests/Feature/OpenApiProductImageContractTest.php` (extended).

**Documentation (`docs/` and `phases/`):**

- `docs/api/api-contract.md` — `CAT-015..018` in the master inventory (§19), the catalog matrix (§21.1),
  a full `§21.12` contract section, the §21.10 invalidation rule, §21.11 threat rows, the §30.4.1
  operational table, §30.21, §31.7 counts and the capability matrices.
- `docs/api/api-resources.md` — `alt_text` nullability, `sort_order` provenance, the at-most-one
  primary note, and the privileged reader/writer capability rows.
- `docs/api/api-conventions.md` — media-mutation conventions (`§21.6`), idempotency classification and
  endpoint counts.
- `docs/api/openapi.yaml` — four paths, the `ImagePath` parameter, `ProductImageAltTextRequest` and
  `ProductImageOrderRequest` schemas, and the `ProductImage.alt_text`/`sort_order` corrections.
- `docs/api/api-examples.md` — `§9.9` request/response examples for all four operations.
- `docs/decisions.md` — `ADR/API-FRZ-003` (post-freeze contract extension, method choice, primary
  invariant, ordering normalisation) and `ADR/BACKEND-058` (row-first deletion with a durable retry
  and the residual orphan risk).
- `docs/findings-and-limitations-register.md` — F9 updated.
- `frontend/admin/AGENTS.md`, `frontend/admin/README.md` — corrected the now-false statements that "no
  such endpoint exists"; the admin app still renders no control for the four operations (Phase 19.8C).

No file under `frontend/web`, no Flutter file, and no migration was touched.

## 9. Tests

| Suite | Tests | Assertions |
|---|---|---|
| `ProductImageMutationApiTest` (SQLite) | 17 | 141 |
| `ProductImageMutationConcurrencyMysqlTest` (MariaDB, two sessions) | 3 | 18 |
| `OpenApiProductImageContractTest` (extended) | 6 | 65 |

Coverage highlights: full authorization matrix (`401` anonymous / `403` customer, staff-without-
permission and admin-without-permission / allow for permitted staff) for all four operations; delete
happy path + object gone + siblings untouched + repeat `404`; delete-primary leaves no primary;
missing-object tolerance; storage-failure → `204` + retry job pushed; `CAT-016` update/clear/blank-
to-null/500-max/invalid-type/unknown-field/forbidden-`sort_order`-and-`is_primary`/absent-key/cross-
product `404`; `CAT-017` demote-previous/idempotent/`sort_order`-unchanged/body-rejected/cross-product;
`CAT-018` persisted order, legacy zero-based convergence, single image, `[]` on an image-less product,
duplicates/foreign/unknown/incomplete/invalid-type/unknown-field rejections, metadata preservation,
unknown product `404`; and OpenAPI paths/verbs/operationIds/schemas/reference-resolution.

## 10. Gates run

| Gate | Command | Result |
|---|---|---|
| Format | `vendor/bin/pint --dirty --format agent` | passed |
| SQLite suite | `vendor/bin/phpunit` | **1817 tests, 1801 passed, 16 skipped, 0 failed**, 7421 assertions |
| MySQL suite | `vendor/bin/phpunit -c phpunit.mysql.xml` | **15 tests, 15 passed**, 74 assertions |
| OpenAPI | `OpenApiProductImageContractTest` (Symfony YAML parse + structure) | passed |
| Route registration | `php artisan route:list --path=products` | 14 routes incl. the four new ones with correct verbs |

The 16 SQLite skips are pre-existing (engine-specific or environment-gated suites), not new.

## 11. Pending owner action (not performed here)

- **Phase 19 roadmap status record — not applied.** `phases/group-S-phases.md` is the owner-authored
  phase instruction (it now *is* the 19.8B specification, not a status roadmap), and root `AGENTS.md`
  is out of scope for this agent. The status of Phase 19.8B therefore lives in this record, in
  `ADR/API-FRZ-003` and in `docs/findings-and-limitations-register.md` F9. The owner should update the
  roadmap status line for 19.8 themselves if they want it in that file.
- **`phases/group-S-phases.md` remains modified in the working tree** by its owner; nothing here
  depends on that file's state.
- **`phases/group-S-phases.md` scope reconciliation — applied on owner authorisation (no longer
  pending).** The file's original three-operation sections still forbade reordering, which its own
  appended "Phase 19.8B Scope Amendment" supersedes. A "superseded in part" pointer was added at the
  top, and the contradicting lines (approved-scope lists, explicit boundaries, the documentation
  section and the acceptance criteria) were corrected so the file states the approved four-operation
  scope. The amendment itself is unchanged.

## 12. Discrepancies and limitations carried forward

- **F5 (unchanged, non-launch-blocking):** staff endpoints still return `501`; only the single
  clerk-linked ADMIN can exercise the new operations in a live environment.
- **F11 (OPEN):** the authenticated page render of `/products/{product}` and the 19.8A upload have
  never been executed by a signed-in operator. Consequently the four new mutations are **verified by
  automated tests, not by a live authenticated operator**. Live smoke-testing needs the owner's session.
- **F12 (unchanged):** Fallow is unavailable in this environment; dead-code/duplication figures remain
  unverified and are not a gate.
- **`sort_order` documentation correction (intentional, recorded):** the `ProductImage` schema said
  `minimum: 1`, but legacy seeded rows can be `0`. The minimum was widened to `0` and the provenance
  documented, so OpenAPI and runtime behaviour agree. This is a non-breaking consistency correction
  tied to `CAT-015..018`, recorded in `ADR/API-FRZ-003`, and not a scope expansion.
- **`alt_text` documentation correction (intentional, recorded):** the schema marked it a non-nullable
  read-only string; it can be `null` and `CAT-016` makes clearing it a supported operation, so it is
  now `string | null`.
- **Orphan-object risk:** see §7 — a permanently failing cleanup job can leave an orphaned object.
- **No per-array cap on `CAT-018`:** deliberate; bounded by the global JSON body limit.

## 13. Exit condition

The admin application can complete product media management against a documented, frozen-contract
backend: delete, alt-text edit, primary selection and reorder are all implemented, authorized,
validated, concurrency-safe, storage-failure-aware, documented in `api-contract.md` /
`api-resources.md` / `api-conventions.md` / `openapi.yaml` / `api-examples.md` / `decisions.md`, and
covered by automated tests including two-session MariaDB proofs.

**Demonstrably true** except for the live-authenticated smoke test, which only the owner can run
(F11), and the owner-owned roadmap status record (§11).

## 14. Handoff

- **Phase 19.8C (admin gallery wiring):** consume `CAT-015`–`CAT-018` in
  `frontend/admin/components/catalog/product-image-*.tsx` and
  `frontend/admin/lib/catalog/product-image-actions.ts`. Do not send `sort_order`/`is_primary`; use the
  dedicated endpoints. The four operations are the *complete* approved set.
- Category image upload remains unsupported backend work and must not be faked in the UI.
