# Phase 19.8A — Product Image Upload & Preview

## Objective

Implement product image upload and preview in `frontend/admin` using the existing Laravel API contract and the Cloudflare R2 public development URL configured in the local environment.

This is Phase **19.8A only**. It is not full product media management.

The existing roadmap describes Phase 19.8 as:

> Product Media Management – cloudflare R2: Images, uploads, ordering, alt text, previews, and deletion.

The current backend supports product image upload and image listing, but does not expose approved mutation endpoints for deletion, ordering, editing alt text, or setting the primary image. Do not pretend these missing operations are implemented.

Follow the repository root `AGENTS.md`, `frontend/admin/AGENTS.md`, the Phase 19 roadmap, the frozen API contract, and the established Phase 19.2–19.6 architecture.

## 1. Audit before implementation

Inspect the actual repository and verify:

- The CAT-009 route, controller, FormRequest, validation rules, authorization, storage behavior, and response envelope.
- The product detail response from `GET /api/v1/admin/products/{product}`, including its `images[]` structure.
- The existing product list, detail, create and edit screens, and their routing.
- The current Clerk server-side authentication and Laravel authorization helpers.
- `frontend/admin/lib/api/client.ts`, `frontend/admin/lib/api/server-request.ts`, existing Server Actions and error-handling patterns.
- The current Next.js image configuration and how product image URLs are rendered.
- The configured `R2_PUBLIC_BASE_URL` in the local backend environment and the corresponding public URL configuration available to the admin.
- Existing tests, dependency versions, and phase completion-report conventions.

Do not print or copy local environment files into reports. Never expose R2 access keys, secret tokens, Laravel credentials, Clerk secrets, or other credentials.

Document any contract or runtime discrepancy before proceeding. Do not change the frozen V1 API contract for this phase.

## 2. Upload through the existing CAT-009 contract

Use the existing endpoint:

`POST /api/v1/products/{product}/images`

The request uses multipart form data with the field `image`, and requires the existing `products.manage` authorization.

Requirements:

1. Add an explicit multipart transport path. Do not pass `FormData` through a JSON serializer or break existing JSON requests.
2. Follow the existing admin architecture. Prefer a server-side Server Action receiving `FormData`, obtaining the current Clerk session token on the server, and forwarding the multipart request to Laravel through a server-only helper.
3. Never expose bearer tokens to browser components, client-side state, or client-visible logs.
4. Do not manually set a multipart `Content-Type` header when forwarding native `FormData`; allow the HTTP implementation to set its boundary correctly.
5. Preserve Laravel's real response envelope and validation semantics. Translate known API errors into safe, actionable UI feedback without hiding useful field-level validation errors.
6. Revalidate or refresh the affected product detail data after a successful upload using the repository's established approach.
7. Ensure repeated submissions cannot accidentally trigger duplicate uploads from ordinary double-clicks. Show a clear in-progress state and disable the relevant submit control while an upload is in progress.
8. Keep authorization enforcement server-side. A hidden or disabled UI control is not an authorization boundary.
9. Do not add a new backend endpoint, modify Laravel authorization rules, or change the CAT-009 contract.

Do not invent file types, size limits, or request fields. Derive supported formats and validation constraints from the real Laravel implementation.

## 3. Implement image previews

Use the existing product detail response as the source of truth.

Render the product's current images using the actual returned fields, including:

- `id`
- `url`
- `alt_text`
- `sort_order`
- `is_primary`

Follow the actual response types and existing frontend conventions; do not assume fields can be null or non-null without checking their contract.

The interface should:

- Display the existing product images in a responsive layout.
- Provide a usable image preview and preserve aspect ratio.
- Display alt text when present and an accessible fallback when it is absent.
- Display primary-image status and ordering as read-only information.
- Clearly distinguish an empty image collection from a failed API request.
- Handle broken or inaccessible image URLs with a useful fallback and without crashing the product detail page.
- Refresh the displayed collection after a successful upload.
- Preserve the existing product management design system and reusable primitives.

Do not add fake image records or substitute invented product images for failed previews.

## 4. Cloudflare R2 public development URL

The backend's `R2_PUBLIC_BASE_URL` is configured to use Cloudflare R2's public development URL. A custom domain is not required for this phase.

Inspect the actual configured public URL and determine its origin. Do not guess the hostname, hardcode an example URL, or silently substitute another domain.

Implement the narrowest practical allow-list for the configured public media origin.

Requirements:

1. Use the actual public development URL as the source of truth.
2. Ensure the Next.js image configuration or the existing image-rendering approach allows images from that exact HTTPS origin.
3. Prefer an explicit, environment-driven media origin over a hardcoded host. If the admin needs its own configuration variable, document it and update the appropriate tracked example environment file without copying credentials.
4. Keep the public media URL distinct from private R2 credentials. The public URL may be exposed where required to render images; access keys and secret credentials must not be exposed.
5. Do not allow arbitrary remote image hosts simply to make previews work.
6. Validate URL parsing, origin matching and any necessary path restrictions. Do not use unsafe substring matching to validate a hostname.
7. Handle missing or malformed media-origin configuration gracefully and document the required setup.
8. Verify that the actual image URLs returned by Laravel match the configured origin. Do not rewrite returned image URLs to make a mismatch disappear.
9. If the URL or bucket configuration is incorrect, report the precise non-secret configuration problem rather than printing environment values or silently bypassing the allow-list.

Where possible, verify preview rendering against an actual image returned by the development API. Distinguish configuration inspection from a successful live browser test.

## 5. Request-size and upload security

Inspect the existing infrastructure and backend validation before choosing limits.

- Determine the effective file-size limit imposed by Laravel validation, PHP upload settings, the web server or proxy, and the admin ingress.
- Document any known mismatch between these limits.
- Do not claim that a Next.js Server Action size limit is the only relevant limit.
- Do not raise server limits or change production infrastructure without explicit authorization.
- Do not buffer or log image contents unnecessarily.
- Handle oversized files and unsupported file types with understandable errors.
- Avoid exposing local file paths, internal server exceptions, storage credentials or raw secrets in UI errors.

If a necessary limit cannot be verified from the repository and environment, report the gap rather than inventing a production setting.

## 6. Explicitly out of scope

Do not implement or display working controls for:

- Deleting product images.
- Reordering images or modifying `sort_order`.
- Editing persisted `alt_text`.
- Changing `is_primary`.
- Uploading category images.
- Creating new media mutation endpoints.
- Changing the frozen API contract or OpenAPI specification.
- Refactoring unrelated product management screens.
- Product variants or other deferred roadmap work.

It is acceptable to show returned alt text, sort order and primary status as read-only information. Do not present read-only values as editable.

The missing media operations require a separately approved API contract and backend implementation phase.

## 7. Tests

Add tests consistent with the existing admin testing stack.

Cover at least:

### Multipart transport and server-side security

- FormData is forwarded as multipart data without JSON serialization.
- The multipart boundary is not incorrectly overridden.
- The current server-side Clerk token is forwarded through the approved server-only path.
- Missing or expired authentication follows existing safe authentication handling.
- Laravel authorization failures are displayed safely.
- Validation errors and unexpected network failures are handled without losing the existing form state unnecessarily.
- No bearer token or secret is exposed to client code or logs.

### Product image UI

- Existing images render from the real response shape.
- Empty image collections display the appropriate empty state.
- Upload progress prevents accidental duplicate submission.
- Successful upload refreshes the image collection.
- Failed uploads display actionable feedback.
- Broken images do not break the page.
- Primary status, alt text and ordering are read-only.
- No delete, reorder, alt-edit or primary-selection mutation is attempted.

### Media origin configuration

- The configured R2 public development origin is accepted.
- Unexpected origins are rejected or safely handled.
- Missing and malformed configuration does not cause an uncontrolled application failure.
- Tests do not depend on a live R2 credential or disclose local environment values.

Use mocks for deterministic automated tests. Where live verification is possible, record it separately from mocked test results.

## 8. Quality gates

Run the applicable existing gates:

- `npm run lint`
- `npm run typecheck`
- `npm run test`
- `npm run build`
- `git diff --check`

Do not weaken existing tests, bypass authorization, or change unrelated project files to make the gates pass.

If a gate fails, report the exact failure and whether it was introduced by this phase.

## 9. Completion report

Update the Phase 19.8 roadmap/completion documentation in the established project format.

Report:

1. Files changed and why.
2. Existing CAT-009 behavior verified before implementation.
3. Upload transport and authorization design.
4. How the public R2 development origin is configured and allow-listed, without revealing its full value if it is considered environment-specific.
5. The verified file-size and upload-validation constraints, including any unresolved infrastructure limits.
6. Automated test results and quality-gate results.
7. Whether the real image preview was tested successfully in a browser.
8. Any environment setup still required from the owner.
9. Confirmation that no missing media mutation endpoint was invented.
10. Remaining limitations and recommended next phase.

Be precise about the phase status. Because the original Phase 19.8 roadmap includes ordering, alt text editing and deletion, completing upload and preview alone must not be reported as full completion of the original media-management scope.

Use `PASS` only if the phase's explicitly defined acceptance criteria are met. If the implementation is successful but the broader roadmap scope remains unfinished, record **Phase 19.8A PASS / Phase 19.8 overall PARTIAL**, and explain the remaining API dependencies.

Do not claim a live authenticated upload or real browser preview succeeded unless it was actually performed and observed.

## Acceptance criteria

Phase 19.8A is complete when:

- An authorized operator can upload a product image using the existing CAT-009 contract.
- The admin displays the product's existing images from Laravel's response.
- The configured Cloudflare R2 public development origin is allow-listed narrowly and previews work with the actual returned URLs, or a specific external configuration blocker is documented.
- Upload errors, authorization failures, invalid files and network failures are handled safely.
- Automated quality gates pass, with results recorded accurately.
- No unauthorized contract changes or nonfunctional mutation controls are introduced.

Begin with the audit, then implement the smallest complete change set that meets these criteria.
