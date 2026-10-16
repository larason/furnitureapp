# Phase 19.8C — Product Media Management UI

## Objective

Complete product image management in `frontend/admin` by integrating the four approved Laravel media mutation endpoints delivered in Phase 19.8B:

1. Delete a product image.
2. Edit image alt text.
3. Select the primary image.
4. Reorder product images.

Phase 19.8A already implemented product image upload and preview. Preserve that working functionality and integrate the new controls into the existing product detail gallery.

**Scope:** Admin frontend only. Do not modify the Laravel backend, API contract, database schema, OpenAPI specification, or customer-facing Flutter and Next.js applications.

Follow the repository root `AGENTS.md`, `frontend/admin/AGENTS.md`, the Phase 19 roadmap, the existing design tokens, and the established Phase 19.2–19.8A architecture.

Use `phases/phase-19.8b-completion.md` as the authoritative record of the delivered API behavior.

---

## 1. Audit the existing implementation first

Before editing code, inspect:

- `frontend/admin/components/catalog/product-image-gallery.tsx`
- `frontend/admin/components/catalog/product-image-preview.tsx`
- `frontend/admin/components/catalog/product-image-uploader.tsx`
- `frontend/admin/components/catalog/product-detail-view.tsx`
- `frontend/admin/lib/catalog/product-image-actions.ts`
- `frontend/admin/lib/catalog/products-api.ts`
- `frontend/admin/lib/api/server-request.ts`
- The product detail page under `frontend/admin/app/(protected)/products/[product]/`
- Existing server-side identity and permission helpers, including `requireOperationalIdentity()` and `resolveAdminRouteAccess()`.
- The reusable `ConfirmDialog`, form controls, loading/error states, and notification or feedback patterns.
- Existing product media tests and the Phase 19.8A completion report.

Verify the actual exported types, current image data shape, server-action conventions, error envelope handling, and product detail refresh/revalidation strategy.

Do not recreate existing infrastructure or add a second API transport layer. Extend the existing implementation with the smallest complete change set.

If the working tree contains owner changes, preserve them. Do not reset, overwrite, or revert unrelated files.

## 2. Use the four approved endpoints exactly

The following endpoints and payloads are the delivered contract. Consume them as documented; do not invent alternative routes or request fields.

### CAT-015 — Delete an image

`DELETE /api/v1/products/{product}/images/{image}`

- Success: HTTP `204 No Content`.
- The response has no JSON body.
- A missing or previously deleted image returns `404 RESOURCE_NOT_FOUND`.
- The backend removes the database record and handles object deletion with its durable retry mechanism.
- Do not retry a successful deletion merely because the client did not receive a response.
- Do not claim that the underlying object is synchronously deleted in every storage-failure scenario.

The UI must require explicit confirmation before deletion. Use the existing `ConfirmDialog` primitive if it fits the established patterns.

The confirmation should identify the image using its thumbnail and/or accessible description and explain that deletion removes it from the product gallery.

After confirmation:

1. Disable duplicate submissions.
2. Call the server action.
3. On `204`, refresh or revalidate the product detail data.
4. Show appropriate success feedback.
5. On failure, preserve the remaining gallery state and show safe, actionable feedback.

Handle a `404` as a stale gallery state: refresh the product data and tell the operator that the image may already have been removed.

Never delete an image solely by manipulating client-side state.

### CAT-016 — Edit alt text

`PATCH /api/v1/products/{product}/images/{image}`

Request:

`{ "alt_text": "Description of the image" }`

Or clear the field using:

`{ "alt_text": null }`

Contract rules:

- `alt_text` is the only accepted mutable field.
- The maximum length is 500 characters.
- The backend trims the supplied value.
- An empty or whitespace-only string becomes `null`.
- Unknown fields, including `sort_order` and `is_primary`, must not be sent.
- Success: HTTP `200`, response envelope `{ "data": ProductImage }`.

Implement a usable editing interaction for each image. An inline form or a small accessible edit panel is acceptable; use the established admin design system.

Requirements:

- Display the existing alt text when present.
- Make the distinction between an absent alt text and an unsaved edit clear.
- Allow the operator to save a description or clear it.
- Enforce the 500-character maximum in the UI as well as relying on server validation.
- Display a character count if it improves usability.
- Provide Save and Cancel controls.
- Preserve unsaved input when a request fails.
- Disable repeated submissions while saving.
- On success, use the returned `ProductImage` or refreshed product data as the source of truth.
- Show validation errors accessibly.

Do not treat image alt text as a filename, image title, or storage URL. Do not automatically invent descriptive alt text for the operator.

### CAT-017 — Select the primary image

`POST /api/v1/products/{product}/images/{image}/primary`

- No request body is accepted.
- Do not send `sort_order`, `is_primary`, or any other JSON fields.
- Success: HTTP `200`, response envelope `{ "data": ProductImage }`.
- Selecting the current primary image is idempotent.
- The backend guarantees at most one primary image per product.
- A product is allowed to have no primary image.

Implement a clear primary-image indicator and a control to make a non-primary image the primary image.

Requirements:

- Clearly distinguish the primary image visually and accessibly.
- Do not render a functional “Make primary” control for the image already selected as primary.
- Disable the action while a media mutation is in progress.
- On success, refresh or update the gallery using the authoritative server response.
- Ensure the previous primary image no longer appears selected.
- Handle products with no primary image without assuming one must always exist.
- Do not silently promote another image when the current primary is deleted; the backend intentionally allows zero primary images.

The UI may display the first image as a visual fallback where appropriate, but must not misrepresent that fallback as a persisted primary image.

### CAT-018 — Reorder images

`POST /api/v1/products/{product}/images/reorder`

Request:

`{ "images": ["img_...", "img_...", "img_..."] }`

- The array must represent the complete current image set for the product.
- The backend rejects duplicates, unknown identifiers, foreign-product identifiers, invalid entries, and incomplete lists.
- An empty array is valid for a product with no images.
- The backend persists the order and normalizes positions to contiguous 1-based `sort_order` values.
- Success: HTTP `200`, response envelope `{ "data": [ProductImage] }`.
- Image metadata and primary-image selection are preserved.

Implement accessible reordering controls.

Preferred approach: simple **Move up** and **Move down** buttons for each image, avoiding a new drag-and-drop dependency. If an existing accessible drag-and-drop solution is already present and fits the project, it may be reused instead.

Requirements:

1. Show the current image order clearly.
2. Let an operator move an image earlier or later in the gallery.
3. Disable Move up for the first image and Move down for the last image.
4. Construct the reorder request from the complete ordered list of current image IDs after the proposed move.
5. Send only the approved `images` array; never attempt to update `sort_order` directly.
6. Do not issue a reorder request for a no-op.
7. Prevent conflicting mutations while an operation is pending.
8. On success, use the server response or re-fetch the product detail so the displayed order reflects persisted data.
9. If the request fails, restore the last confirmed order and show an accessible error.
10. Do not leave an optimistic order displayed as saved after a failed request.

Use the returned `sort_order` and the server's persisted image collection as the source of truth after mutations. Do not rely on locally assigned positions as evidence of successful persistence.

---

## 3. Server-side authentication and authorization

All four mutations must use the existing server-side transport and authentication architecture.

For every mutation:

1. Resolve the current Clerk identity on the server.
2. Enforce the existing operational identity and `products.manage` permission before forwarding the mutation.
3. Forward the current server-side Clerk bearer token through the approved server-only request helper.
4. Call the exact Laravel endpoint and HTTP method.
5. Preserve Laravel's documented response and error semantics.
6. Revalidate or refresh the affected product detail using the established project pattern.

Follow the existing Phase 19.8A server-action authorization pattern, including `resolveAdminRouteAccess()` or the verified equivalent.

Laravel independently enforces authorization. Frontend permission checks are not a substitute for backend authorization.

Never:

- Expose Clerk bearer tokens to client components or browser state.
- Accept the product ID or image ID as proof of authorization.
- Allow the browser to supply storage keys, object paths, disks, or arbitrary image URLs.
- Add direct database or storage access from the admin frontend.
- Bypass the Laravel API for convenience.
- Send undocumented mutation fields.
- Log credentials, authorization headers, or image contents.

Use existing error types and safe error mapping. Handle authentication expiry, `403`, `404`, `422`, rate limiting, network failures, and unexpected server errors without exposing internal stack traces.

For `DELETE`, specifically handle the `204 No Content` response without attempting to parse it as JSON.

---

## 4. Gallery behavior and mutation coordination

Keep the gallery cohesive and predictable.

- Preserve Phase 19.8A upload and preview behavior.
- Keep image previews responsive and preserve aspect ratio.
- Retain accessible image descriptions and the existing broken-image fallback.
- Keep the empty state distinct from loading and API failure states.
- Preserve the existing product detail page and navigation.
- Use the actual product image collection returned by Laravel.
- Keep primary-image status separate from gallery position.
- Do not assume the first image is the primary image.
- Do not assume every product has a primary image.

### Prevent conflicting operations

Avoid overlapping operations that can invalidate the gallery's current state.

At minimum:

- Prevent duplicate submissions for the same action.
- Prevent simultaneous reorder operations.
- Prevent deletion while an incompatible edit, primary selection, or reorder operation is pending.
- Prefer a clear gallery-level pending state during structural mutations such as delete, primary selection, and reorder.
- Keep per-image alt-text editing manageable without allowing stale updates to overwrite newer data.

A simple, well-tested mutation state model is preferable to an elaborate state-management refactor.

After successful mutations, ensure the rendered gallery and product detail data converge on the authoritative Laravel state. Use the existing revalidation or refresh pattern rather than introducing a parallel cache.

Do not use arbitrary timeouts to simulate completion.

---

## 5. UI, accessibility, and design system

Use the canonical design tokens and existing reusable components in `frontend/admin`.

The interface must provide:

- Clear labels and accessible names for every action.
- Keyboard-operable controls.
- Visible focus indicators.
- Accessible pending, success, and error feedback.
- A confirmation dialog for deletion.
- Clear visual indication of the primary image.
- Clear ordering controls and boundary states.
- Alt-text editing with validation feedback.
- Responsive gallery layout suitable for desktop and narrow viewports.
- Loading and empty states consistent with the existing design system.
- No reliance on color alone to communicate primary status or errors.

Do not add a new design system, introduce unrelated layout changes, or add a dependency unless a genuine requirement cannot be met with existing components.

Do not invent success messages claiming that an operation completed before the server confirms it.

---

## 6. Testing requirements

Extend the existing admin media test suite, particularly `frontend/admin/tests/product-media.test.tsx` and the current server-action/API transport tests.

Use deterministic mocks for automated UI tests. Do not require live Clerk credentials, a real R2 upload, or a production API to run the suite.

### Delete tests

- Confirmation is required before deletion.
- Canceling confirmation makes no API request.
- Confirming deletion calls CAT-015 with the correct product and image identifiers.
- A successful `204` response is handled without JSON parsing.
- Duplicate deletion submissions are prevented.
- Success refreshes or revalidates the product data.
- A `404` triggers appropriate stale-state handling.
- A failed request does not falsely remove the image from the confirmed gallery.
- No storage key or URL is sent to Laravel.

### Alt-text tests

- Existing alt text can be edited and saved.
- Clearing alt text sends `null` according to the established form behavior.
- Whitespace-only text follows the backend contract.
- The UI enforces the 500-character maximum.
- The request contains only `alt_text`.
- Save and Cancel behave correctly.
- Validation and network errors are displayed accessibly.
- Failed submissions preserve the user's draft.
- Duplicate submissions are prevented.

### Primary-image tests

- A non-primary image can be selected as primary.
- CAT-017 is called without a request body.
- The returned or refreshed gallery reflects the new primary image.
- The previous primary is no longer marked primary.
- Selecting the current primary does not produce an unnecessary request.
- A product without a primary image renders correctly.
- A failed mutation does not falsely change the persisted primary state.

### Reorder tests

- Moving an image up or down produces the expected complete ordered ID list.
- The first image cannot move up and the last image cannot move down.
- No-op moves do not issue a request.
- CAT-018 sends only the complete `images` array.
- A successful response updates the displayed gallery order.
- A failed reorder restores the last confirmed order.
- Duplicate submissions and concurrent reorder requests are prevented.
- Primary-image status and alt text remain associated with their original image IDs.

### Shared security and regression tests

- Every server action enforces the established server-side operational identity and `products.manage` permission.
- Clerk tokens never reach client components.
- API authorization and validation errors are handled safely.
- Unknown response shapes do not crash the gallery.
- Existing upload and preview tests continue to pass.
- No undocumented API route or payload is introduced.
- No direct browser-side API mutation bypasses the server-only transport.
- Empty galleries and broken image previews remain safe.

Add tests for any discovered edge cases that affect the contract. Do not weaken existing assertions or replace meaningful tests with snapshots alone.

---

## 7. Quality gates and verification

Run the established frontend admin gates:

- `npm run lint`
- `npm run typecheck`
- `npm run test`
- `npm run build`
- `git diff --check`

Inspect the final diff for:

- Unrelated modifications.
- Accidental changes to the frozen API contract or backend.
- Exposed credentials.
- New dependencies without justification.
- Incomplete or unused media controls.
- Stale optimistic UI states.
- Incorrect treatment of `204 No Content`.
- Unhandled mutation failures.

If any gate fails, report the exact command and failure. Do not claim a gate passed unless it was actually run.

### Live verification

If a usable authenticated operator session and development backend are available, verify the complete workflow on a real product:

1. Open its product detail page and inspect the gallery.
2. Upload an image and confirm it appears.
3. Edit its alt text and confirm it persists after refresh.
4. Select a primary image and confirm the selection persists.
5. Reorder at least two images and confirm the order persists after refresh.
6. Delete a test image after confirming the destructive action.
7. Check empty, failed-request, and narrow-viewport behavior where practical.

Do not delete valuable production data to perform these checks. Use an appropriate test product.

If authenticated live testing is unavailable, explicitly record it as pending. Passing mocks and build checks are not evidence that the live authenticated workflow succeeded.

---

## 8. Completion report

Create `phases/phase-19.8c-completion.md` following the established completion-report format.

Record:

1. Final status: `PASS`, `PARTIAL`, or `BLOCKED`, with the reason.
2. Files changed and the purpose of each significant change.
3. How each CAT-015 through CAT-018 endpoint is integrated.
4. The confirmation and stale-state behavior for deletion.
5. Alt-text validation and clearing behavior.
6. Primary-image selection and zero-primary handling.
7. Reordering controls, complete-list payload behavior, and failure recovery.
8. Server-side authentication and permission enforcement.
9. Automated test counts and all quality-gate results.
10. Whether the real authenticated workflow was verified.
11. Remaining limitations and any required owner action.

Update the appropriate roadmap status record only if it is in the authorized scope and consistent with repository instructions. Preserve owner-managed changes. If the roadmap file is explicitly out of scope, record the status in the completion report and identify the pending owner action.

Keep the findings and limitations register accurate. Do not mark the live authenticated smoke test as resolved unless it was performed.

## Acceptance criteria

Phase 19.8C is complete when:

- All four approved media operations work through the actual documented CAT-015–CAT-018 endpoints.
- Image upload and preview from Phase 19.8A remain functional.
- The gallery reflects persisted Laravel state after successful mutations.
- Failed operations do not leave misleading or stale optimistic UI.
- Delete confirmation, validation, accessibility, and mutation coordination are implemented.
- All mutations use server-side Clerk identity and the existing `products.manage` authorization path.
- Automated tests and applicable quality gates pass.
- The completion report accurately distinguishes automated verification from live authenticated verification.

Begin with the repository audit, then implement the smallest complete and well-tested UI change set. Do not modify the backend or invent additional API capabilities.
