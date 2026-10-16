# Phase 19.8B — Product Media API Contract & Backend

> **Scope note — superseded in part:** The reordering operation was later approved by the owner in the "Phase 19.8B Scope Amendment — Product Image Reordering" section at the end of this file. Where this document states the phase covers *three* operations, or forbids reordering, the amendment governs: the approved scope is **four** operations (delete, edit alt text, select primary image, reorder images).

## Objective

Extend the existing Laravel product-media API and frozen V1 API documentation to support these four approved operations:

1. Delete a product image.
2. Edit a product image's alt text.
3. Select a product's primary image.
4. Reorder a product's images.

The owner has explicitly approved updating the frozen API contract for these operations.

This phase establishes the backend and API contract required for the admin UI to implement complete media-management workflows in a subsequent phase.

The existing Phase 19.8A upload and preview implementation must remain working. Do not rebuild or unnecessarily refactor it.

Follow the root `AGENTS.md`, `backend/laravel/AGENTS.md` if present, the existing phase roadmap, the frozen API documentation and OpenAPI specification, and established Laravel architecture and testing conventions.

## 1. Approved scope and explicit boundaries

### Approved for implementation

- Add a documented API operation for deleting an image belonging to a product.
- Add a documented API operation for updating image alt text.
- Add a documented API operation for selecting an image as its product's primary image.
- Add a documented API operation for reordering a product's images (see the Scope Amendment at the end of this file).
- Implement Laravel validation, authorization, persistence, storage behavior, resource responses and error handling for those operations.
- Update the applicable V1 API documentation, OpenAPI specification, backend tests and phase completion documentation.
- Add migrations only when an identified schema or integrity requirement justifies them.

### Not approved in this phase

- Arbitrary or client-controlled `sort_order` — direct ordering writes remain unapproved; ordering changes only through the approved reorder operation (see the Scope Amendment at the end of this file).
- Category image upload or category media management.
- Product variant management.
- Changes to Clerk authentication, staff provisioning, role assignment or permission names.
- Unrelated API, database or frontend refactoring.
- Changes to the established product catalog business rules that are not necessary for the four approved operations.

**Important:** The original Phase 19.8 roadmap includes ordering, and the owner has approved it in the Scope Amendment at the end of this file. Implement the reorder operation per that amendment. Clients must still never submit arbitrary `sort_order` values; ordering changes only through the approved complete-list operation.

Do not invent additional media operations or silently broaden this approval.

## 2. Audit the existing implementation before coding

Inspect the actual code and record the findings before implementation.

### Existing upload and read behavior

Verify:

- `POST /api/v1/products/{product}/images` (CAT-009).
- `ProductController::storeImage` and the associated FormRequest, validator, action/service and resource.
- The existing product image model, migration, relationship, storage disk and object-key conventions.
- The operational product detail response and its `images[]` fields:
  - `id`
  - `url`
  - `alt_text`
  - `sort_order`
  - `is_primary`
- How the first image and subsequent uploads are assigned `sort_order` and `is_primary`.
- Whether the current implementation guarantees at most one primary image, exactly one primary image for a nonempty collection, or a different invariant.
- How product images are removed or replaced elsewhere in the codebase, if at all.
- Existing transaction, locking, storage cleanup, exception handling and logging patterns.

### Existing security and API conventions

Verify:

- Route model binding and how product IDs and image IDs are resolved.
- The existing operational mutation middleware and permission checks.
- API error codes, response envelopes, HTTP status conventions, pagination conventions and validation formats.
- `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/api-conventions.md` and `docs/api/openapi.yaml`.
- Existing tests for products, uploads, resources, storage, authorization, concurrency and migrations.
- The supported database engines and any engine-specific constraints relevant to primary-image uniqueness.

Use actual source code and migrations as evidence. Do not infer the storage key from a public URL or assume that deleting a database row automatically removes its stored object.

Document any discrepancy between runtime behavior and the current frozen contract. The owner has approved the four specified contract extensions, not unrelated contract changes.

## 3. Define the API contract before implementation

Produce a concise contract proposal in the phase working notes before changing routes or implementation.

Use existing API naming, resource, error and authorization conventions. The following is the intended operation set; exact status codes, envelopes and validation details must be reconciled with the repository's established conventions and documented consistently.

### A. Delete a product image

Proposed resource route:

`DELETE /api/v1/products/{product}/images/{image}`

Requirements:

- The image must belong to the specified product.
- An image belonging to another product must not be deleted through a mismatched product/image pair.
- Use the existing operational authorization policy.
- Define and document success, not-found, validation, storage-failure and authorization responses.
- Delete the persisted image record and its associated stored object using the actual storage disk and object-key fields.
- Do not accept a client-provided storage path or URL as the deletion target.
- Do not delete the product or any unrelated image.

Choose the success status and response body according to established API conventions. Document the exact behavior in OpenAPI.

### B. Edit alt text

Proposed resource route:

`PATCH /api/v1/products/{product}/images/{image}`

Request field:

`alt_text`

Requirements:

- Accept only the approved field or fields defined by this operation.
- Support updating alt text and clearing it where the existing schema and resource semantics permit.
- Determine the maximum length and nullability from the actual database schema, model, existing validation conventions and resource contract. Do not invent an incompatible limit.
- Reject unknown request fields according to established API conventions.
- Reject an empty/no-op update if that is the established convention for partial updates.
- Ensure the image belongs to the specified product.
- Return the updated image resource using the established response envelope.
- Do not allow this endpoint to mutate `sort_order`, `is_primary`, the storage key, the disk, or the image URL.

Document whether an empty string is normalized, rejected or converted to null. Keep request validation, persistence and API documentation consistent.

### C. Select a primary image

Preferred proposed route:

`PUT /api/v1/products/{product}/images/{image}/primary`

Use this shape only if it fits the existing route and API conventions. If the repository has a stronger established resource convention, document a justified alternative before implementation.

Requirements:

- The selected image must belong to the specified product.
- Selecting an image must not permit an arbitrary `is_primary` value to be applied to other images.
- The operation must be idempotent: selecting the already-primary image succeeds without producing inconsistent state.
- The API must return the selected image resource, or the established equivalent response, with its resulting `is_primary` value.
- The client must not be allowed to update `sort_order` through this operation.
- The operation must preserve the chosen primary-image invariant under concurrent requests.

Prefer a dedicated primary-selection operation over mixing unrelated fields into the alt-text patch.

### Contract approval boundary

The owner has approved these four operations. The agent may finalize their precise request/response schemas and status codes after the audit, provided the design stays within the approved scope and follows existing conventions.

If an operation requires a material, unrelated business-rule change, stop and document the dependency instead of silently expanding scope.

## 4. Authorization and ownership enforcement

Every new mutation must use the same effective operational access policy as CAT-009.

Verify the existing CAT-009 middleware and permission checks, including the currently used `products.manage` permission. Reuse the actual permission catalog and authorization mechanisms; do not invent a new permission.

Requirements:

- Require the established authenticated operational identity.
- Require the existing eligible STAFF/ADMIN role and `products.manage` permission as applicable to the current route policy.
- Retain operational write rate limiting consistent with CAT-009.
- Enforce authorization in Laravel even if the frontend hides controls.
- Scope image lookup and mutation to the supplied product.
- Prevent cross-product image-ID manipulation.
- Follow existing not-found/forbidden conventions without exposing unnecessary information about other products' images.
- Reject malformed identifiers using the established validation and binding behavior.

Write tests for unauthorized, unauthenticated, insufficient-permission and cross-product access cases.

Do not change role provisioning or broaden access to make tests pass.

## 5. Primary-image integrity and concurrency

Treat primary-image selection as a product-level invariant, not an isolated boolean update.

First determine the existing behavior and database constraints. Then implement and document a consistent rule.

At minimum:

- At most one image for a product may be primary.
- Concurrent primary-selection requests must not leave multiple primary images.
- The selected image must be part of the product's actual image collection.
- A failed operation must not leave a partially updated primary-image state.
- Selecting an already-primary image must be safe and idempotent.
- Existing upload behavior must continue to satisfy the same invariant.

If the established business behavior requires exactly one primary image whenever a product has images, deleting the current primary must promote another remaining image deterministically. Prefer the existing ordering semantics, with a stable tie-breaker, rather than introducing arbitrary ordering.

If the current domain intentionally permits products with images but no primary image, do not silently introduce the exactly-one rule. Record the existing invariant and implement deletion accordingly. Any proposed change to the established domain rule must be documented before coding.

Use database transactions and appropriate product-level locking or another supported concurrency-safe mechanism. Verify behavior on the supported database engines; do not assume SQLite test behavior proves MySQL/MariaDB concurrency safety.

If a schema constraint or migration is necessary, inspect existing data and database compatibility first. Do not use a migration that destroys, arbitrarily rewrites or silently discards existing image data.

## 6. Storage deletion and failure consistency

Deleting an image affects both database state and object storage. A database transaction cannot automatically roll back a successful external R2 deletion.

Before implementing deletion, identify the actual storage abstraction, disk, object key and available retry/job mechanisms.

Requirements:

1. Never construct the deletion target from the client-supplied public URL.
2. Use the persisted image record's trusted storage metadata and existing Laravel filesystem abstraction.
3. Never expose R2 credentials or internal storage paths in API responses.
4. Handle missing objects, storage timeouts and storage exceptions explicitly.
5. Avoid a state where a successful API response implies complete deletion when the object still exists and no cleanup has been recorded.
6. Avoid deleting the object first and then assuming a failed database transaction can restore it.
7. Prefer an existing durable cleanup/retry mechanism if one is available.
8. If no durable cleanup mechanism exists, choose and document a failure-consistent implementation based on the existing architecture. Report any residual orphan-object or stale-record risk instead of claiming atomicity across the database and R2.
9. Log enough operational context to diagnose failures without logging credentials, sensitive URLs, image contents or secrets.
10. Ensure retries cannot delete another image's object or affect unrelated records.

Do not add an elaborate queue, outbox or new infrastructure dependency without demonstrating that it is necessary and consistent with existing project architecture. If reliable deletion cannot be implemented safely within the current design, report the precise blocker before broadening scope.

Use Laravel's storage fakes and failure injection where practical.

## 7. Database and model implementation

Follow the existing domain service/action patterns rather than placing all behavior in the controller.

Requirements:

- Preserve the current `ProductImageResource` contract fields.
- Use the actual relationship and trusted persisted storage metadata.
- Keep product ownership checks close to the operation's data access.
- Ensure alt-text updates cannot accidentally overwrite unrelated fields.
- Ensure primary selection updates are atomic and concurrency-safe.
- Ensure deletion and any primary fallback follow the documented invariant.
- Preserve stable image order outside the approved reorder operation; only that operation (see the Scope Amendment at the end of this file) may change ordering.
- Do not change the CAT-009 request shape or remove its existing validation/security checks.
- Add or modify migrations only where necessary, reversible where practical, and compatible with supported databases.

If a migration is required to repair existing primary-image inconsistencies, report the observed inconsistency and the migration's exact behavior. Do not silently normalize existing production data without an explicit safe migration plan.

## 8. Frozen API contract and OpenAPI updates

The following documentation must be updated consistently for the four approved operations:

- `docs/api/api-contract.md`
- `docs/api/api-resources.md`
- `docs/api/api-conventions.md`, if the new operations require convention documentation
- `docs/api/openapi.yaml`
- The appropriate ADR or contract-change record, following repository conventions
- Phase 19.8B completion documentation
- The Phase 19 roadmap, updating status without erasing the original broader scope

Document:

- Exact HTTP method and path.
- Required authentication and permission.
- Request content type and exact accepted fields.
- Path parameter formats and ownership behavior.
- Success status and response envelope/resource.
- Validation rules and nullability.
- Relevant error codes and statuses.
- Idempotency semantics for primary selection.
- Deletion behavior when the image is primary.
- Storage failure and retry behavior where externally observable.
- Whether any response contains the updated image or no content.

Validate the OpenAPI specification with the existing project tooling if available.

Do not alter unrelated endpoint definitions, response schemas or existing CAT-009 semantics. The only additional endpoint permitted is the approved reorder operation (see the Scope Amendment at the end of this file).

## 9. Backend test requirements

Add focused tests using the existing Laravel test architecture.

### Delete tests

- Authorized operator successfully deletes an image.
- The image record is removed according to the documented behavior.
- The correct stored object is targeted for deletion.
- An image belonging to another product cannot be deleted using the wrong product ID.
- A missing product or image follows documented not-found behavior.
- Unauthenticated requests are rejected.
- Customers and operators without the required permission are rejected.
- Storage exceptions and missing objects follow the documented failure/retry semantics.
- Deleting a primary image preserves the documented primary invariant.
- Deletion does not affect other product images or the product record.

### Alt-text tests

- Authorized operator updates alt text successfully.
- Clearing alt text follows the documented null/empty-string semantics.
- Maximum length, invalid type and malformed values are rejected.
- Unknown fields and forbidden fields such as `sort_order`, `is_primary`, disk and URL cannot be modified.
- Partial updates preserve every other image field.
- Cross-product image access is rejected.
- Authentication and permission failures are covered.

### Primary-selection tests

- Selecting a non-primary image makes it primary.
- The previously primary image is demoted when the domain invariant requires exactly one primary.
- Selecting the current primary is idempotent.
- Selecting an image from another product is rejected.
- A missing image is handled correctly.
- Two competing requests cannot leave multiple primary images.
- Database rollback or simulated failures do not leave partial state.
- The response accurately represents the persisted result.

### Regression and contract tests

- Existing CAT-009 upload tests still pass.
- Product detail continues to return `images[]` with the established fields.
- Tests confirm only the approved four new operations are added (delete, edit alt text, select primary image, reorder images).
- Tests confirm no endpoint permits arbitrary `sort_order` changes.
- Route middleware, permission enforcement, rate limiting and error envelopes match the documented contract.
- OpenAPI paths and schemas match runtime routes and resource output.
- Any database migration is covered by applicable migration/schema tests.

Use realistic factories and isolated storage. Do not use production credentials or mutate a live database to test these operations.

## 10. Quality gates

Run all applicable gates and report exact results:

- Laravel automated tests for product images and related product resources.
- The broader Laravel test suite where feasible.
- PHPStan/Larastan at the repository's configured level.
- Laravel Pint or the established formatting check.
- OpenAPI validation.
- `git diff --check`.

Inspect the final diff for accidental changes to:

- Frozen API operations outside the approved scope.
- Product upload behavior.
- Authentication and authorization policy.
- Storage credentials or local environment files.
- Unrelated frontend files.
- Owner-authored changes that predate this phase.

Do not weaken tests, ignore a failed quality gate or claim a check passed if it was not run.

## 11. Completion report

Create `phases/phase-19.8b-completion.md` using the repository's established report format.

Include:

1. Status and exact scope delivered.
2. Audit findings about the original image model, storage metadata, upload flow and primary-image invariant.
3. Exact new endpoints and their request/response contracts.
4. Frozen documentation and OpenAPI files changed.
5. Authorization and ownership protections.
6. Transaction and concurrency design.
7. Storage deletion consistency and any residual operational risk.
8. Migrations added, if any, and their compatibility.
9. Test counts and exact quality-gate results.
10. Live verification status and any remaining operator smoke-test steps.
11. Explicit confirmation of which approved operations were implemented, including image reordering (or a documented blocker if not).
12. Remaining limitations and the recommended next phase.

Do not print `.env` contents, R2 credentials, Clerk secrets, bearer tokens, private storage keys or sensitive environment values.

Do not claim live R2 deletion was verified unless it was actually executed and observed. Automated storage-fake tests do not constitute live storage verification.

## 12. Acceptance criteria

Phase 19.8B can be marked PASS only when:

- The four approved operations have explicit, consistent V1 contract definitions.
- Laravel implements each operation using the established authorization policy.
- Image ownership is enforced for every operation.
- Alt-text updates cannot mutate unrelated image fields.
- Primary-image selection is safe, idempotent and concurrency-aware.
- Reorder operations are atomic, concurrency-safe and deterministically normalized.
- Deletion handles both database and storage failure scenarios honestly and safely.
- Existing upload and product-detail behavior remain compatible.
- OpenAPI and runtime behavior agree.
- Required automated tests and applicable quality gates pass.
- Documentation accurately records live verification and unresolved risks.

If one of the four operations cannot be implemented safely, record PARTIAL with the precise blocker rather than pretending the whole phase passed.

Begin with the audit and contract proposal, then implement the approved changes. Do not wait for another approval for ordinary details within the four approved operations, but stop before introducing any unapproved endpoint or broader business-rule change.

# Phase 19.8B Scope Amendment — Product Image Reordering

The project owner has explicitly approved extending the frozen V1 API contract to support product image reordering. This approval adds reordering to the three previously approved operations: deletion, alt-text editing, and primary-image selection.

## Additional implementation requirements

Before implementation, audit the existing product-image model, database schema, `sort_order` semantics, CAT-009 upload flow, product-image resource, routes, authorization middleware, API conventions, and OpenAPI specification.

Implement a documented, authorized API operation for reordering images belonging to a product.

### API and validation

- Follow existing Laravel API conventions and preserve the existing response envelope and error format.
- Prefer an atomic batch reorder operation if it fits the established architecture. Choose and document the exact route, HTTP method, request schema, response schema, and status codes after auditing existing conventions.
- Accept only image identifiers and ordering information required by the chosen contract. Never accept client-controlled storage keys, image URLs, or ownership changes.
- Verify that every submitted image belongs to the specified product.
- Reject duplicate identifiers, foreign-product images, invalid values, and incomplete image lists if the selected API contract requires the complete collection.
- Normalize ordering deterministically according to the existing `sort_order` convention. Do not assume zero-based or one-based ordering without inspecting the implementation.
- Perform the reorder atomically using a database transaction and appropriate locking or equivalent concurrency protection.
- Ensure concurrent reorder requests cannot leave duplicate or partially updated ordering values where the data model requires unique ordering.
- Preserve the existing image IDs, URLs, alt text, and primary-image state.
- Apply the same authorization boundary as CAT-009: `clerk.auth`, `staff-or-admin`, `permission:products.manage`, and `throttle:operational-write`, or the repository's verified equivalent.
- Do not broaden authorization or expose mutation access to customers.

### Contract and documentation

Update the approved V1 contract documentation, resource documentation, conventions, OpenAPI specification, relevant ADR or contract record, and Phase 19.8B completion report.

Document the reorder request semantics, validation failures, ordering guarantees, concurrency behavior, and response shape. Keep every contract representation synchronized.

### Backend tests

Cover at least:

- Successful reordering and persistence after a fresh read.
- Correct handling of the first and last positions under the actual ordering convention.
- Empty and single-image collections where supported.
- Duplicate, missing, invalid, or foreign-product image identifiers.
- Unauthorized and insufficient-permission requests.
- Transaction rollback when validation or persistence fails.
- Concurrent reorder attempts and deterministic final ordering.
- Preservation of image metadata and primary-image selection.
- Regression coverage for upload, deletion, alt-text editing, and primary-image selection.
- Contract/OpenAPI consistency.

### Scope and reporting

The approved Phase 19.8B scope now includes **delete, edit alt text, select primary image, and reorder images**. Do not describe reordering as deferred or out of scope.

Do not add unrelated media capabilities, such as category image management, image transformations, or a new storage provider.

Run the established Laravel tests, PHPStan/Larastan, Pint, OpenAPI validation, and `git diff --check`. Record actual results and any unresolved production-storage or live-verification limitations. Do not claim live verification unless it was performed.

