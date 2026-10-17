# Phase 19.9 — Inventory Management UI

## Objective

Implement the operational inventory management interface in `frontend/admin`, integrating the existing, audited Laravel inventory API.

The Laravel implementation is complete and hardened. **This phase must not modify the Laravel backend, database schema, inventory business rules, frozen API contract, or OpenAPI specification.**

The current admin inventory page is a placeholder. Replace it with a usable, permission-aware inventory listing, filtering, detail inspection, and stock-adjustment workflow.

Follow the root `AGENTS.md`, `frontend/admin/AGENTS.md`, the approved Phase Group S roadmap, the frozen API documentation, the existing Phase 19.2–19.8 architecture, and the canonical design system.

Treat the owner-provided inventory audit as the starting evidence, but verify the relevant code and contracts before implementing.

## 1. Mandatory audit before coding

Inspect and document the actual repository implementation for:

- `frontend/admin/app/(protected)/inventory/page.tsx`
- The inventory entry in the admin navigation configuration.
- `frontend/admin/lib/api/client.ts` and `AdminApiRequest`.
- `frontend/admin/lib/api/server-request.ts`.
- Existing server actions, API error handling, authorization helpers, and data-refresh patterns.
- `AdminPermissionName` and any permission unions, catalogs, route metadata, and authorization tests derived from it.
- Existing list, filter, pagination, detail, form, dialog, loading, error, and empty-state components.
- The exact inventory request and response schemas in:
  - `docs/api/api-contract.md`
  - `docs/api/api-resources.md`
  - `docs/api/api-conventions.md`
  - `docs/api/openapi.yaml`
  - `docs/api/api-examples.md`, where relevant.
- The product detail and variant-list contracts needed to resolve inventory labels.
- Existing admin tests and phase completion-report conventions.

Confirm the exact query parameter names, pagination envelope, identifier types, nullable fields, adjustment request fields, accepted adjustment reasons, response envelope, error codes, and status codes from the repository.

Do not guess missing details. If the runtime implementation contradicts the frozen contract, report the discrepancy and stop before inventing a new API shape.

Preserve existing owner changes. Do not reset or revert unrelated files.

---

## 2. Integrate the existing inventory API

Use the existing endpoints exactly as documented.

### INV-001 — List inventory

`GET /api/v1/inventory`

The endpoint provides paginated inventory data, ordered by `updated_at DESC, id ASC`, with supported product, variant, and warehouse-location filters.

Requirements:

- Use the exact existing endpoint path. It is `/api/v1/inventory`, **not** `/api/v1/admin/inventory`.
- Use the existing server-side API transport and Clerk token flow.
- Preserve the API's authoritative pagination and ordering.
- Use server-side filtering. Do not fetch every inventory record and filter the entire dataset in the browser.
- Build the list around the actual `InventoryResource` schema.
- Preserve the backend's deterministic ordering; do not introduce unsupported sorting.
- Preserve pagination metadata and current filters when navigating between pages, following existing admin patterns.
- Reset pagination appropriately when filters change.
- Serialize only documented query parameters and valid values.
- Do not send arbitrary or unknown query parameters.
- Handle loading, empty results, API errors, and permission failures as distinct states.

Display the actual values returned by Laravel, including the inventory identifier, product and variant references, warehouse location, quantity information, and timestamps wherever those fields exist in the verified resource.

The audit reports eight required resource fields. Use the exact schema rather than reconstructing it from this description.

Do not calculate or invent stock quantities when Laravel already returns the authoritative values. Do not replace an API failure with an empty inventory list.

### INV-002 — Retrieve one inventory record

`GET /api/v1/inventory/{inventory}`

Use this endpoint to load an authoritative inventory snapshot when opening the inventory detail or adjustment interaction.

Requirements:

- Use the exact inventory identifier returned by Laravel.
- Do not normalize, lowercase, or otherwise transform opaque identifiers.
- Do not treat an identifier as proof of authorization.
- Preserve the API's not-found behavior, including masking unknown and cross-resource identifiers.
- Display the latest returned inventory values before the operator submits an adjustment.
- If the detail request fails, do not silently substitute stale values and proceed as though the refresh succeeded.
- Reuse the existing server-side transport and error-handling patterns.

A drawer or dialog is acceptable for inventory detail and adjustment if it fits the existing architecture. Do not create a new route hierarchy without a clear benefit consistent with the roadmap.

### INV-003 — Adjust inventory

`POST /api/v1/inventory/{inventory}/adjust`

Use the documented request body and the required `Idempotency-Key` header.

Requirements:

- Read the exact request schema and accepted reason values before building the form.
- Send only the documented request fields.
- Generate a valid UUID idempotency key for each new logical adjustment.
- Send the key using the exact `Idempotency-Key` HTTP header.
- Do not assume the adjustment response has a particular shape until it has been verified in the contract.
- Respect documented validation errors, authorization failures, rate limiting, duplicate-intent behavior, and other status codes.
- Refresh the affected inventory record and list after a confirmed successful adjustment using the existing refresh or revalidation strategy.
- Do not claim that an adjustment succeeded before receiving an authoritative successful response.

Do not create a replacement inventory-adjustment endpoint, directly write to the database, or bypass the existing `InventoryAdjustmentService`.

---

## 3. Fix the Idempotency-Key transport prerequisite

The existing `AdminApiRequest` does not support the `Idempotency-Key` header. INV-003 requires a UUID, so adjustment submission cannot be implemented correctly until the existing transport supports it.

Extend the existing transport narrowly.

Preferred approach:

- Add a dedicated optional `idempotencyKey` request option to `AdminApiRequest`, or use an equally narrow, type-safe mechanism that fits the existing architecture.
- When supplied, the transport must add the `Idempotency-Key` header.
- When absent, existing API requests must behave exactly as they do now.
- Preserve existing `Accept`, bearer authorization, content negotiation, request serialization, error handling, timeouts, and private/no-store behavior.
- Do not introduce arbitrary caller-controlled headers that can override `Authorization`, `Accept`, or other security-sensitive transport behavior.
- Do not create a separate fetch implementation solely for inventory adjustments.
- Do not expose bearer tokens to client components or browser-visible state.

Add tests proving that the header is included for inventory adjustments, omitted for unrelated calls, and forwarded without breaking the existing JSON transport.

### Idempotency lifecycle

Idempotency must cover the actual user intent, not merely an individual HTTP attempt.

Implement the following behavior:

1. A new adjustment receives a new UUID.
2. A retry of the same adjustment, with the same inventory identifier and identical payload, reuses the original UUID.
3. If the operator changes the payload, it represents a new intent and must not reuse the old key.
4. If a network failure leaves the result uncertain, do not blindly submit a new adjustment with a new key.
5. Where retry is offered, retain the original key and exact payload for that retry.
6. Do not automatically retry a stock adjustment unless the retry uses the same idempotency key and identical request intent.
7. Handle the documented `409` duplicate-intent response safely. Do not respond by generating another key and immediately repeating the operation.
8. After a confirmed success, clear the completed submission state so a genuinely new adjustment can receive a new key.

Use an existing UUID utility if available. Otherwise, use an appropriate platform-supported UUID generator. Do not use predictable timestamps, `Math.random()`, or a constant key.

Keep the idempotency key separate from business form fields; never include it in the JSON request body unless the verified API contract explicitly requires that, which the audit says it does not.

---

## 4. Implement the missing `inventory.manage` permission type

The backend already defines `inventory.view` and `inventory.manage`. The audit reports that both are granted to STAFF and ADMIN, but the frontend permission type currently includes only `inventory.view`.

Add the exact `inventory.manage` permission to the existing frontend permission catalog/type system.

Requirements:

- Use the exact permission identifier `inventory.manage`.
- Do not invent a new permission, role, role hierarchy, or authorization shortcut.
- Do not hardcode permissions based only on the user's role.
- Do not grant ADMIN an implicit bypass.
- Preserve the existing Laravel `/api/v1/me` identity and effective-permission model.
- Update affected type guards, permission helpers, route metadata, and tests only where necessary.

### Inventory page authorization

The inventory page requires `inventory.view`.

- A user with `inventory.view` can open the inventory page and inspect inventory.
- A user without `inventory.view` must be denied according to the existing route-authorization architecture.
- The inventory navigation entry must use the existing permission-aware navigation pattern.

### Adjustment authorization

Stock adjustment requires `inventory.manage`.

- Show adjustment controls only when the resolved operational identity has `inventory.manage`.
- A user with `inventory.view` but without `inventory.manage` must still be able to inspect inventory, but must not be able to submit adjustments.
- Enforce the exact permission again inside every adjustment server action, before forwarding the request.
- Do not rely on hidden buttons or client-side permission checks as the authorization boundary.
- Laravel remains the final authorization authority.

The audit reports that STAFF and ADMIN currently hold both permissions. Still, use the effective permissions returned by Laravel for each operational identity rather than assuming every account has the same access.

Add tests for view-only and adjustment-authorized identities, including server-action denial.

---

## 5. Replace the inventory navigation placeholder

The current navigation entry marks Inventory as a placeholder planned for Phase 19.9.

Once the page is implemented:

- Convert Inventory into a real navigation link to `/inventory`.
- Remove the placeholder treatment using the existing navigation metadata conventions.
- Gate the entry using `inventory.view`.
- Preserve all unrelated navigation entries and their existing phase ownership.
- Update tests that intentionally assert the inventory placeholder state.
- Do not change the global dashboard into an inventory analytics page.

The overview must not gain fabricated inventory KPIs, revenue numbers, stock totals, charts, or operational metrics that the approved APIs do not provide.

---

## 6. Product and variant labels without an API contract change

This is the main data-presentation challenge.

The inventory resource exposes opaque identifiers such as product and variant IDs. Displaying only those IDs is not a satisfactory operational inventory interface.

**Do not change the frozen `InventoryResource` schema. Do not add product names, slugs, variant SKUs, or labels to the inventory API.**

Use existing documented catalog read endpoints to resolve labels.

The audit identifies the existing public variant endpoint:

`GET /api/v1/products/{product}/variants` (CAT-005)

It returns variant information including SKU and name. Verify its exact response schema and the existing product-detail endpoint for product names before implementing.

### Label resolution strategy

Implement a small, typed inventory-label resolver using the existing server-side transport.

Requirements:

1. Load the paginated inventory records first.
2. Extract the distinct product IDs from the returned page.
3. Deduplicate product-detail and variant-list requests by product ID.
4. Resolve each unique product's name using the existing documented product-detail endpoint.
5. Resolve its variants using CAT-005, then match each inventory row's `variant_id` to the returned variant data.
6. Do not make duplicate requests for multiple inventory rows belonging to the same product.
7. Avoid one request per inventory row.
8. Bound parallel catalog requests to a reasonable concurrency limit.
9. Reuse results within the current server render/request where practical.
10. Do not introduce a new backend endpoint, new database join, persistent label database, or undocumented query parameter.
11. Do not cache private inventory records in a shared cache.
12. Do not silently reinterpret a failed label lookup as an inventory failure if the inventory request itself succeeded.

The expected seeded inventory is currently small, but the implementation should still deduplicate requests and avoid unbounded concurrency.

### Label fallback behavior

If product or variant metadata cannot be resolved:

- Keep the inventory record visible.
- Show a clear, non-misleading fallback such as “Product details unavailable” or “Variant details unavailable”, with the relevant opaque ID available for troubleshooting.
- Do not invent product names, SKUs, or variant labels.
- Do not fail the entire inventory page because one catalog lookup failed.
- Do not silently display an opaque ID as if it were a human-readable product name.
- Make the fallback accessible and visually restrained.

Use the verified nullability and ownership semantics from the resource schemas. Do not assume every inventory record has a non-null variant if the contract permits otherwise.

### Product and variant filters

The inventory API supports product, variant, and warehouse-location filters, but the frontend must use the exact documented parameter names and expected values.

Provide usable filters while respecting those semantics.

- Prefer labeled product and variant selectors only when the available catalog endpoints provide appropriate options consistent with the actual inventory data.
- If a reliable option set is not available, use clearly labeled exact-identifier inputs with helper text explaining that the backend filter matches an identifier, not an arbitrary product-name search.
- Do not pretend that a local page of paginated rows represents every available filter option.
- Do not claim that a filter searches product names unless a verified API or deliberate client-side interaction actually implements that behavior.
- Do not add a new API endpoint just to populate a dropdown.
- Use the actual warehouse-location values from the contract or current data. Do not invent warehouse names or silently map unknown values to a made-up location.

Keep filtering server-side and preserve filters in pagination/navigation using the existing admin conventions.

Document the chosen label and filter strategy, including its request-count behavior and fallback semantics, in the phase report.

---

## 7. Inventory list and detail experience

Implement a clear, responsive operational table using the existing reusable `DataTable` and other admin primitives where appropriate.

The page should include:

- Page heading and concise explanatory text.
- Supported product, variant, and warehouse-location filters.
- Current filter state.
- Server-side pagination.
- A loading state.
- A distinct empty state.
- A recoverable API error state with a safe retry path.
- Human-readable product and variant labels when resolvable.
- Warehouse location using the actual backend value or a verified label.
- The authoritative quantity fields from `InventoryResource`.
- The update timestamp in the existing date/time presentation style.
- A clear action to inspect the inventory record or open the adjustment flow, according to the user's permissions.

Do not add unsupported sorting, export, bulk adjustments, inventory creation, or variant creation.

### Quantity presentation

- Display actual quantity values returned by Laravel.
- Do not calculate an alternative stock figure and present it as authoritative.
- Preserve the distinction between physical quantity, reserved quantity, and available quantity where those fields exist.
- Render zero values as zero, not as blank or unavailable.
- Format timestamps using the established project convention.
- Avoid inventing a warehouse display name or unit of measurement.

The audit notes that `reserved_quantity` is currently expected to read zero because cart and checkout reservations are deferred. Display the real returned value without pretending that reservation workflows are active.

Do not change the customer-facing catalog or Flutter app as part of this phase.

---

## 8. Stock-adjustment form

Implement an accessible adjustment dialog or panel using the established admin form components.

Inspect the actual INV-003 request schema and reason enum before creating the form. The backend contract is authoritative.

The audit confirms these business rules:

- `STOCK_RECEIPT` requires a positive quantity change.
- `RETURN` requires a positive quantity change.
- `DAMAGE` requires a negative quantity change.
- The resulting quantity cannot fall below `reserved_quantity`.
- Quantity arithmetic is protected against 32-bit overflow.
- Adjustments are transactional and audited.
- The backend enforces reason-to-sign consistency.

Implement the frontend so these rules are understandable before submission, while keeping Laravel authoritative.

### Form behavior

- Identify the product, variant, and warehouse location being adjusted.
- Display the latest authoritative inventory snapshot fetched through INV-002.
- Show current quantity and other relevant returned quantity fields.
- Use the exact accepted reason values from the contract.
- Use the exact adjustment payload fields expected by INV-003.
- Provide a clear quantity-change input with correct sign behavior.
- If the UI accepts an absolute quantity and derives the sign from the reason, make that mapping explicit and deterministic.
- Do not permit a reason/sign combination known to be invalid.
- Do not silently change a user's intended sign.
- Reject zero, non-integer, out-of-range, or otherwise invalid values in the UI whenever the contract requires that.
- Prevent unsafe client-side overflow when previewing the resulting quantity.
- If the proposed result would be below `reserved_quantity`, show a clear validation message and block submission.
- Treat this preview as advisory; Laravel must still perform the authoritative validation and transaction.
- Include any notes or other fields only if they exist in the verified request schema.
- Preserve field-level validation errors returned by Laravel.
- Disable duplicate submissions while an operation is in progress.
- Do not discard form input after an ordinary validation failure.
- Do not report success before the backend confirms it.

Use accessible labels, keyboard-operable controls, visible focus states, and actionable error messages.

### Adjustment result

After a confirmed success:

- Show a concise success confirmation.
- Refresh the affected inventory record and list.
- Update displayed quantities from authoritative API data.
- Keep the active filter and pagination context where possible.
- Reset the form and idempotency state for a new logical adjustment.
- Do not create local-only adjustment records or invent audit entries.

On a `422`, show actionable validation feedback.

On a `403`, show the existing permission-denial treatment.

On a `429`, communicate that the request is rate-limited and follow existing retry guidance.

On a `409`, follow the documented duplicate-intent semantics. Never automatically generate a new key and repeat a possibly completed adjustment.

On a timeout or network failure, treat the outcome as potentially uncertain. Preserve the same key and payload if offering a retry.

Do not automatically retry a stock mutation with a new idempotency key.

---

## 9. Audit trail and scope boundaries

INV-003 already records audited before/after adjustment information.

Do not implement a new audit system. Do not add a frontend-generated audit event or attempt to write directly to the audit-log endpoint.

The existing `GET /api/v1/admin/audit-logs` (ADM-007) remains part of the separate audit-log roadmap phase. Phase 19.9 only needs to use the existing inventory adjustment workflow.

### Explicitly out of scope

Do not implement:

- New Laravel inventory endpoints or schema changes.
- API contract or OpenAPI modifications.
- Inventory creation or deletion.
- Variant creation or editing.
- Product creation or editing.
- Changes to catalog seeders or fixture data.
- Changes to published product states or product types.
- Customer checkout, cart, orders, payments, or reservation workflows.
- A new audit-log screen.
- Bulk stock adjustment.
- Unsupported inventory sorting or export.
- A new API proxy, route handler, or direct database access path.
- Fake inventory records, metrics, quantities, products, variants, or warehouse locations.
- Unrelated changes to other admin pages.

The audit identifies an existing request-first-release concern: published `IN_STOCK` products conflict with the separate demo-fixture removal plan. Do not resolve that conflict within Phase 19.9. Record it as an existing product/catalog decision requiring separate owner action.

Likewise, the fact that variants cannot currently be created means the adjustment interface must operate on inventory records that actually exist. Do not suggest that the UI can create inventory for a new variant.

---

## 10. Testing requirements

Extend the existing admin test suite with deterministic tests. Do not require live Clerk credentials or a production API for ordinary automated testing.

### Transport tests

- Existing API calls remain unchanged when no idempotency key is supplied.
- INV-003 includes the exact `Idempotency-Key` header.
- The key is not placed in the JSON body.
- Existing `Accept` and bearer authorization headers remain intact.
- Arbitrary callers cannot override security-sensitive headers.
- Request serialization and error-envelope handling remain unchanged.
- A valid UUID is used for each new logical adjustment.

### Authorization tests

- A user with `inventory.view` can open the inventory page.
- A user without `inventory.view` is denied.
- A view-only user sees no usable adjustment action.
- A view-only user cannot invoke the adjustment server action successfully.
- A user with `inventory.manage` can access the adjustment workflow.
- Permission checks use Laravel's effective permission data, not a hardcoded role shortcut.
- ADMIN does not receive an implicit permission bypass.

### Listing and filtering tests

- The correct `/api/v1/inventory` endpoint is used.
- The exact documented pagination parameters and response envelope are handled.
- The backend's deterministic order is preserved.
- Product, variant, and warehouse filters serialize using the actual contract.
- Filter changes reset pagination appropriately.
- Pagination preserves current filters.
- Empty data differs from a failed API request.
- A malformed response or unexpected network failure does not become a false empty state.
- Unknown query parameters are never emitted.

### Product and variant label tests

- Product names are resolved using the verified product-detail endpoint.
- Variant names/SKUs are resolved using CAT-005.
- Duplicate product IDs result in deduplicated catalog requests.
- Requests are bounded rather than launched without a concurrency limit.
- A failed product lookup does not hide otherwise valid inventory.
- A failed variant lookup does not hide otherwise valid inventory.
- Fallback labels never invent product or variant data.
- The implementation does not assume that the current inventory page contains every possible filter option.
- No inventory API contract changes are made.

### Adjustment tests

- The adjustment form uses the exact INV-003 request schema.
- Only documented reason values are offered.
- `STOCK_RECEIPT` and `RETURN` produce positive quantity changes.
- `DAMAGE` produces a negative quantity change.
- Invalid sign/reason combinations are rejected before submission.
- Zero, non-integer, and out-of-range inputs are handled safely according to the contract.
- The below-reserved-quantity guard is enforced in the UI.
- The backend remains authoritative if the inventory changes after the form opens.
- Double submission is prevented.
- A new logical adjustment receives a new key.
- A retry of the same uncertain intent reuses the original key and exact payload.
- Changing the payload does not reuse the previous key.
- A successful adjustment refreshes authoritative inventory data.
- A `422`, `403`, `409`, `429`, or network failure is handled safely.
- No failure is falsely reported as success.
- Existing dashboard and permission behavior remains intact.

Use the existing testing stack and patterns. Do not weaken tests to make the new implementation pass.

---

## 11. Accessibility and design system

Use the existing canonical design tokens and reusable components.

- Prefer the existing `DataTable`, `PageHeader`, `EmptyState`, `ErrorState`, `LoadingState`, form primitives, and dialog patterns where appropriate.
- Use semantic headings, labels, tables, buttons, and links.
- Ensure all filters and adjustment fields have persistent accessible labels.
- Provide visible keyboard focus.
- Do not rely on color alone for stock state, validation, or permission feedback.
- Keep action targets usable on narrow viewports.
- Ensure loading, errors, empty states, and successful mutations are communicated accessibly.
- Avoid excessive cards, badges, or decorative visual elements.
- Keep the interface operational and restrained rather than adding dashboard-style marketing or analytics.
- Follow the project's existing responsive and reduced-motion conventions.

Do not introduce a new design system or dependency unless an audited requirement cannot be satisfied with the current stack.

---

## 12. Quality gates

Run the established frontend admin quality gates:

- `npm run lint`
- `npm run typecheck`
- `npm run test`
- `npm run build`
- `git diff --check`

Inspect the final diff for:

- Unrelated file changes.
- Accidental backend or API contract changes.
- Permission bypasses.
- Direct browser-side inventory mutations.
- Missing idempotency headers.
- Unstable idempotency keys on retries.
- Unbounded product/variant lookup requests.
- False empty states.
- Invented labels or quantities.
- Unhandled adjustment errors.
- Secret or bearer-token exposure.
- Changes to the existing product/catalog fixture state.

If a gate fails, report the exact command and failure. Do not claim it passed unless it actually ran.

### Live verification

If a usable authenticated operator session and development backend are available, verify:

1. An authorized operator can open Inventory.
2. The inventory table displays real records.
3. Product and variant labels resolve correctly, or safe fallbacks appear.
4. Filters and pagination work against the actual API.
5. A view-only identity cannot adjust stock.
6. An authorized operator can open an inventory record and see its current values.
7. A valid test adjustment succeeds and updates the UI from authoritative API data.
8. A retry of the same intent does not apply the stock change twice.
9. Invalid reason/sign and below-reserved adjustments are rejected safely.
10. A failed or uncertain request does not cause a blind new adjustment.
11. Narrow-viewport and keyboard interactions remain usable.

Use an appropriate development record and a small, deliberate adjustment. Do not modify production stock merely to test the interface.

If live testing is unavailable, report that fact explicitly. Automated tests and mocked responses do not establish successful live integration.

---

## 13. Completion report and roadmap

Create `phases/phase-19.9-completion.md` using the established completion-report conventions.

Record:

1. Final status: `PASS`, `PARTIAL`, or `BLOCKED`.
2. Files changed and the purpose of each significant change.
3. INV-001, INV-002, and INV-003 integration details.
4. The exact transport change used to send `Idempotency-Key`.
5. How idempotency keys are generated, retained on retries, and reset for new intents.
6. How `inventory.view` and `inventory.manage` are enforced.
7. The product/variant label-resolution strategy, deduplication, concurrency limits, and fallback behavior.
8. Pagination and filter behavior.
9. Adjustment validation, sign rules, and below-reserved handling.
10. Automated test counts and all quality-gate results.
11. Whether the real authenticated inventory workflow was tested.
12. Any unresolved live-verification limitations.
13. The existing product-fixture/request-first-release concern, without changing it.
14. Remaining limitations and owner actions.

Update the approved roadmap status according to repository instructions, preserving owner-managed changes. Do not mark a live smoke test as complete unless it was performed.

### Acceptance criteria

Phase 19.9 is complete when:

- The inventory placeholder is replaced by a real, permission-aware admin workflow.
- INV-001 lists and filters real paginated inventory records.
- INV-002 provides authoritative record details for inspection and adjustment.
- INV-003 submits valid adjustments using a UUID `Idempotency-Key`.
- The API transport change preserves all existing requests.
- A repeated or uncertain request cannot casually become a second stock adjustment.
- `inventory.view` and `inventory.manage` are checked independently, including in server actions.
- Product and variant labels are resolved from existing catalog endpoints without changing the frozen inventory contract or making duplicate requests per row.
- The UI follows the real backend adjustment rules and refreshes from authoritative state.
- No fake inventory data or unrelated commerce features are introduced.
- Tests and quality gates pass, and the completion report distinguishes automated verification from live authenticated verification.

Begin with the audit, then implement the smallest complete, production-conscious change set. Do not modify the Laravel backend or widen the API contract.
