# Phase 19.8C — Product Media Management UI (Admin)

## Status

**PASS for the implementation, integration, test and gate criteria of Phase 19.8C.**

One documented limitation is carried forward, outside this agent's permitted scope: the
**authenticated live smoke test was not performed** (F11 — it needs the owner's operator
session). Everything below that is marked *verified* was verified by automated tests, the
build, or a direct reading of the running repository; it is not evidence of a live
authenticated operator walkthrough.

No backend, API-contract, database, OpenAPI or customer-frontend file was modified. The
Phase 19.8A upload and preview behaviour is preserved and still green.

---

## 1. Objective and scope

Wire the four frozen `CAT-015`–`CAT-018` media mutations delivered by Phase 19.8B into the
existing admin product detail gallery, alongside the Phase 19.8A upload:

1. Delete a product image (`CAT-015`).
2. Edit a product image's alt text (`CAT-016`).
3. Select a product's primary image (`CAT-017`).
4. Reorder a product's images (`CAT-018`).

Out of scope and untouched: the Laravel backend, the frozen API contract, the OpenAPI
document, the database schema, the category screens' media handling (category `image`
remains a URL string with no backend upload support), and the customer-facing Next.js and
Flutter applications. No dependency was added.

## 2. Audit performed before implementation

Read the running repository before writing anything:

- The four server-side endpoints, their verbs, bodies and permission gate, cross-checked
  against `phases/phase-19.8b-completion.md`, `docs/api/api-contract.md §21.12` and
  `ADR/API-FRZ-003`.
- The 19.8A media path end to end: `lib/api/client.ts`, `lib/api/server-request.ts`,
  `lib/catalog/products-api.ts`, `lib/catalog/product-image-actions.ts`,
  `lib/catalog/product-image-form.ts`, the gallery/preview/uploader components, and the
  product detail page.
- The existing tests that encoded the 19.8B-era state (`tests/product-media.test.tsx`,
  `tests/products.test.tsx`, `tests/api-client.test.ts`) and the shared primitives the new
  UI reuses (`ConfirmDialog`, `StatusIndicator`, `EmptyState`).

Two audit results shaped the design:

- **Node tests cannot import a module that transitively imports `server-only`** (exact
  error: *"This module cannot be imported from a Client Component module. It should only be
  used from a Server Component."*). The four Server Actions therefore reach the client
  gallery **as props** from the page, and all pure decision logic lives in a
  `server-only`-free module so it stays directly unit-testable.
- `lib/api/client.ts` unconditionally called `response.json()`, so a real `204 No Content`
  delete would have been reported as an invalid response. That was a genuine defect for
  `CAT-015` and is fixed here.

## 3. Files changed

| File | Change |
|---|---|
| `lib/catalog/product-image-mutations.ts` | **New.** Client-safe media vocabulary: mutation kinds/statuses, the outcome type, the `ProductImageGalleryActions` contract, the request field names (`alt_text`, `images`), the 500-character maximum, the shared 401/403/retry/unidentified/too-long messages, the safe message catalogue, `normalizeProductImageAltText`, `productImageAltTextError`, `movedProductImageIds`, `canMoveProductImage`. |
| `lib/catalog/product-image-actions.ts` | Added `deleteProductImageAction`, `updateProductImageAltTextAction`, `selectPrimaryProductImageAction`, `reorderProductImagesAction`, plus a shared `authorizeMediaMutation` → `runImageMutation`/`runProductMutation` → `settleMediaMutation` path. Imports the shared messages so the upload and mutation copy cannot drift. Upload behaviour unchanged. |
| `lib/catalog/products-api.ts` | Added `deleteProductImage`, `updateProductImageAltText`, `selectPrimaryProductImage`, `reorderProductImages`; extracted `productImagesPath`/`productImagePath` (now also used by `submitProductImage`). Each mutation returns `void` — the refreshed product resource is the source of truth. |
| `lib/api/client.ts` | Treat a `204 No Content` as a successful no-payload mutation before `decodePayload`, via `NO_CONTENT_STATUS` and `noContentSuccess<T>()`. |
| `components/catalog/product-image-gallery.tsx` | Rewritten from a read-only Server Component into the interactive Client Component gallery: one-at-a-time mutation guard, pending/feedback region, alt-text editor state, delete confirmation, per-image control group, boundary-disabled move controls, zero-primary notice, stale-safe rendering. |
| `components/catalog/product-image-alt-text-editor.tsx` | **New.** Presentational, controlled `CAT-016` editor with a 500-character counter and an `aria-invalid` error state. |
| `components/catalog/product-detail-view.tsx` | The media section is now a `media?: ReactNode` slot, so the view stays free of the server-only actions and of the client media imports. Copy updated to describe the now-writable media section. |
| `app/(protected)/products/[product]/page.tsx` | Resolves `CATALOG_MEDIA_BASE_URL` in-component and composes the gallery (with the four actions) plus the 19.8A uploader into the media slot. |
| `tests/product-media.test.tsx` | Extended from 24 to 42 tests: mutation vocabulary, editor rendering, gallery controls/boundaries/primary/confirmation, transport/authorization/wiring source assertions, and the `204` transport split. |
| `tests/api-client.test.ts` | Added the `204 No Content` case and the "empty non-204 body is still invalid" guard. |
| `tests/products.test.tsx` | Removed the now-false "media is upload-only / no mutation endpoints" assertions and rewrote the detail-view media assertions for the slot. Net test count unchanged. |
| `frontend/admin/AGENTS.md` | Product-module media rule updated to the four wired operations, the confirmed-not-optimistic rule, the confirmation/alt-text rules and the props-from-the-page rule. |
| `frontend/admin/README.md` | Media section rewritten as "Phases 19.8A–19.8C": contract, data flow, honest limitations. |
| `docs/findings-and-limitations-register.md` | F9 marked RESOLVED for products; F13 marked resolved (`ConfirmDialog` now consumed); F11 recommendation extended to cover the media controls. |

`phases/group-S-phases.md` was replaced by its owner with this phase's specification and is
**not** part of this change set.

## 4. How each endpoint is integrated

| Endpoint | Method / body | UI control | Client path |
|---|---|---|---|
| `CAT-009` `POST /products/{product}/images` | `POST`, multipart `image` only | Uploader (unchanged) | `product-image-uploader.tsx` → `uploadProductImageAction` |
| `CAT-015` `DELETE /products/{product}/images/{image}` | `DELETE`, **no body** | Per-image **Delete**, behind a confirmation | `ConfirmDialog` → `deleteProductImageAction` |
| `CAT-016` `PATCH /products/{product}/images/{image}` | `PATCH`, `{ alt_text }` **only** | Per-image **Edit alt text** | `ProductImageAltTextEditor` → `updateProductImageAltTextAction` |
| `CAT-017` `POST /products/{product}/images/{image}/primary` | `POST`, **no body** | Per-image **Make primary** (absent on the current primary) | `selectPrimaryProductImageAction` |
| `CAT-018` `POST /products/{product}/images/reorder` | `POST`, `{ images: [...complete ordered ids] }` | Per-image **Move up** / **Move down** | `movedProductImageIds` → `reorderProductImagesAction` |

Every mutation is **confirmed, never optimistic**: the Server Action revalidates
`ADMIN_PRODUCT_PATHS.detail(productId)` unless the request failed outright, and the gallery
re-renders only from the refreshed operational product. There is no local copy of the
order, the alt text or the primary selection.

## 5. Deletion: confirmation and stale state

- **Delete goes through `ConfirmDialog`.** The control only sets `deleteTarget`; the request
  is issued from `confirmDelete`. Cancelling only closes the dialog. The confirmation names
  the image and its position, states that Laravel removes the record and its stored object,
  and does not promise a synchronous object removal — the backend's durable retry owns the
  object cleanup (19.8B §7).
- **`404` is a stale gallery, not a failure.** `productImageMutationFailureOutcome` maps it
  to `status: "stale"` with a warning-severity message ("This image may already have been
  removed…" / "…no longer part of this product…"), and the action still revalidates the
  detail, so the display converges on Laravel. A stale outcome is never presented as a hard
  error, and never as a success.
- **`204` is handled.** The client returns `{ data: undefined }` without calling
  `response.json()`; an empty body that is *not* `204` is still rejected as an invalid
  response (both cases covered in `tests/api-client.test.ts`).

## 6. Alt-text validation and clearing

- The editor is seeded from the **stored** value (`image.altText ?? ""`) and is a controlled
  draft; only a confirmed `success` closes it, so a failed save keeps the operator's draft.
- The client trims before sending and **a blank value clears the field** (`null`), mirroring
  Laravel. A value over **500** characters is rejected locally and never forwarded; the
  backend remains authoritative and its own `alt_text` message is shown when it returns one.
- The control enforces `maxLength` and renders a live "N of 500 characters used" counter;
  `aria-invalid` is exposed when the value is rejected. Submission is via the form's submit
  control, so Enter saves and the draft is never lost to a navigation.

## 7. Primary-image selection and zero-primary handling

- **Make primary** is rendered only for non-primary images; selecting the current primary is
  therefore impossible from the UI, and `makePrimary` also short-circuits an
  already-primary image.
- The current primary is marked with the shared `StatusIndicator` (`aria-label="Status:
  Primary image"`), never by colour alone. After a confirmed change, the refreshed resource
  marks the new primary and the previous one no longer carries the chip.
- **A product with no primary image is a normal state**: a `role="status"` notice states that
  no image is primary and that deleting the primary never promotes another one. The gallery
  never invents a primary.

## 8. Reordering: controls, payload and failure recovery

- **Move up** / **Move down** are disabled at the first and last positions respectively, so a
  no-op move cannot be issued (the guard is the same function that builds the payload).
- `movedProductImageIds` returns the **complete** ordered id list (or `null` for an
  impossible move); the component sends that list verbatim as `{ images: [...] }` — never a
  partial set — and never sends `sort_order`.
- **Failure recovery is structural, not cosmetic.** Because there is no optimistic state, a
  failed reorder leaves the last confirmed order on screen and reports "Laravel rejected this
  change. The gallery still shows the last confirmed state." Alt text and primary status
  stay associated with their own image ids, since the display is re-derived from the
  refreshed resource.

## 9. Server-side authentication and permission enforcement

- All four mutation actions live in the `"use server"` module `lib/catalog/product-image-actions.ts`.
  Each repeats `resolveAdminRouteAccess(await requireOperationalIdentity(), { permission:
  PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role })` **before** touching a resource, then
  rejects a blank identifier — Server Functions are directly POST-reachable.
- Failed requests are classified from the shared API error contract (`productWriteErrors`),
  never by matching message text: `401` session, `403` unauthorized, `422` (Laravel's field
  message for `alt_text`, a generic confirmed-state message otherwise), `404` stale, `429`
  with/without `Retry-After`, `null`/5xx retry. No internal detail is rendered.
- **No credential reaches a client component.** The gallery and the editor never import
  `server-only`, the server request transport or `product-image-actions`; the page supplies
  the actions as props. No request bypasses the server-only transport, and the tests assert
  this by reading the sources.

## 10. Tests

`npm run test` runs the whole admin suite: **194 tests, 194 passed, 0 failed, 0 skipped.**

| Suite | Before | After | Notes |
|---|---|---|---|
| `tests/product-media.test.tsx` | 24 | 42 | +18: vocabulary, editor render, gallery controls/boundaries/primary/confirmation, transport/authorization/wiring |
| `tests/api-client.test.ts` | 5 | 7 | +2: `204` success, empty non-`204` still invalid |
| `tests/products.test.tsx` | 42 | 42 | obsolete absence assertions rewritten; detail-view media assertions moved to the slot |

Coverage highlights (all in `tests/product-media.test.tsx` unless noted): exact method/verb/body
per endpoint and the absence of any undocumented media route; `CAT-017` sends no body;
`CAT-016` sends exactly one field; `CAT-018` sends the complete ordered list; no
`sort_order`/`is_primary`/`url` in any request or client source; permission-before-request
ordering and the single shared authorisation path; revalidation on non-`error` outcomes;
`404` → `stale` and its distinct delete/structural messages; `422` `alt_text` message
preference; boundary/no-op move rejection; alt-text trim/clear/length; primary selection
suppressed for the current primary and the zero-primary notice; Delete issuing its request
only from `confirmDelete`; the one-in-flight guard covering every mutation; no
`sort_order`/`is_primary`/`localStorage`/timers/optimistic state in the gallery; the four
actions supplied by the page; the client components importing neither the transport nor the
actions; and the transport's `204` handling. Upload and preview coverage from 19.8A is
preserved.

**Verification honesty:** these are unit, transport and SSR-render assertions. `frontend/admin`
has no DOM library/`@testing-library`, so there is **no simulated click or typing test**;
interaction behaviour is asserted against the rendered markup and the sources that produce
it. No assertion was weakened or replaced with a snapshot.

## 11. Gates run

| Gate | Command | Result |
|---|---|---|
| Lint | `npm run lint` | passed (no findings) |
| Types | `npm run typecheck` | passed |
| Tests | `npm run test` | **194 passed, 0 failed, 0 skipped** |
| Build | `npm run build` (Next.js 16.3.8, Turbopack) | passed, exit 0 |
| Whitespace | `git diff --check` | clean, exit 0 |

No backend gate was run because no backend file was touched.

## 12. Real authenticated workflow

**Not verified — pending the owner.** The checklist in `phases/group-S-phases.md §7`
(open the gallery; upload; edit alt text and confirm it persists; select a primary and
confirm; reorder two images and confirm after refresh; delete after confirming; check empty,
failed and narrow-viewport states) requires a signed-in operator session. F11 remains OPEN:
`/products/{product}` has never been rendered by a signed-in operator, and the mutations are
therefore verified by automated tests only. Passing mocks and a green build are not evidence
that the live authenticated workflow succeeded.

## 13. Remaining limitations and owner action

- **F11 (OPEN):** the authenticated smoke test above. Owner action.
- **F12 (unchanged):** Fallow is unavailable in this environment, so dead-code/duplication
  figures are unverified; this is not a gate.
- **User-visible copy change (intentional):** the previous read-only gallery labelled every
  non-primary image "Not the primary image". That text is gone; a non-primary image now says
  nothing about primary status, the primary carries its chip, and a product with no primary
  shows the zero-primary notice. No state is lost from the display.
- **Upload duplication (unchanged):** repeated uploads still create duplicate images; nothing
  here deduplicates.
- **`ConfirmDialog` exemption (owner-owned file):** the primitive now has a real consumer, so
  the `ignoreFindings` exemption in `.fallowrc.json` can be dropped at the next review. The
  file is owner-managed and was not edited.
- **Roadmap status:** `phases/group-S-phases.md` is owner-managed and carries this phase's
  specification, not a status table; no roadmap status line was edited. Status lives here, in
  `frontend/admin/{AGENTS.md,README.md}` and in the findings register.

## 14. Exit condition

Phase 19.8C is complete: all four approved operations run through the documented
`CAT-015`–`CAT-018` endpoints; 19.8A upload and preview still work; the gallery reflects
persisted Laravel state after every successful mutation and never shows misleading
optimistic state after a failure; delete confirmation, alt-text validation, primary
handling, reorder coordination and accessibility are implemented; every mutation uses the
existing server-side Clerk identity and `products.manage` path; and the automated tests and
all applicable gates pass.

**Demonstrably true** except the live authenticated smoke test, which only the owner can run
(F11).

## 15. Handoff

- The four media operations are the **complete** approved set; do not add a media capability
  beyond them, and never send `sort_order`, `is_primary`, `url` or a storage key in a request
  body.
- Keep the media vocabulary in `lib/catalog/product-image-mutations.ts` (no `server-only`
  import) and the client components free of the transport; the page keeps supplying the
  Server Actions as props.
- Preserve the `204` handling in `lib/api/client.ts`: a future no-body mutation depends on it.
- Category image upload remains unsupported backend work and is not faked in the UI.
