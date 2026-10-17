# Phase 19.10 — Made-to-Order Request Management

## Objective

Implement the operational Made-to-Order Request management interface in `frontend/admin`, integrating the existing, audited Laravel request workflow (`REQ-004`, `REQ-005`, `REQ-006`).

The Laravel request intake, operational reads/writes, status machine, validation and authorization are already implemented and tested. Unlike Phase 19.9, the owner has approved **one narrowly scoped, additive backend completion** — authorized private attachment delivery — because the phase cannot pass without it (see §2). Everything else in this phase is frontend integration.

The `/requests` admin route is currently a permission-protected placeholder (`placeholder: true`, `plannedFor: "Phase 19.10"`) that loads no data. Replace it with a real, permission-aware inbox, request detail, lifecycle actions and attachment inspection.

Follow the root `AGENTS.md`, `frontend/AGENTS.md`, `frontend/admin/AGENTS.md`, the Phase Group S roadmap, the frozen API documentation, the Phase 19.2–19.9 architecture and the canonical design system.

Treat the Phase 19.10 pre-implementation audit (summarized in §1.2) as starting evidence, but verify the relevant code and contract before implementing.

### Why this phase is different from 19.9

Phase 19.9 forbade all backend change. Phase 19.10 has exactly two approved backend changes (§2, §3.3). They are additive, they stay inside `v1`, and every other Laravel, schema, business-rule, contract and OpenAPI change remains prohibited without a further explicit owner decision.

## 1. Mandatory audit before coding

### 1.1 Inspect and document the actual repository implementation for

- `frontend/admin/app/(protected)/requests/page.tsx` and the `requests` entry in `ADMIN_ROUTES`.
- `frontend/admin/lib/navigation/admin-route-paths.ts`, `admin-navigation.ts` (`adminRoute`, `adminRouteMetadata`, `placeholder`/`plannedFor`).
- `frontend/admin/lib/auth/permission-names.ts`, `operational-identity.ts`, `route-guard.ts`, `server-route-guard.tsx`, `server-operational-identity.ts`.
- `frontend/admin/lib/api/client.ts`, `server-request.ts`, `errors.ts`, `idempotency.ts`, `types.ts`.
- The Phase 19.6–19.9 module conventions (`lib/catalog/*`, `components/catalog/*`, `components/states/*`, `components/ui/*`), including the list/filter/pagination/failure-state patterns and the server-action shape.
- `frontend/admin/package.json` — the test script is an explicit file list; a new test file must be added to it.
- The admin README route→permission→API table and the placeholder/implemented-module conventions.
- The exact request contract in `docs/api/api-contract.md` (§26), `docs/api/api-resources.md` (§5, §10.4/§10.5), `docs/api/api-conventions.md` (§26), `docs/api/openapi.yaml` (REQ paths, `MadeToOrderRequest`, `OperationalFurnitureRequest`, `Attachment`), and `docs/api/api-examples.md`.
- `backend/laravel/routes/api.php`, `app/Http/Controllers/Api/V1/RequestController.php`, `app/Http/Resources/*` (`AttachmentResource`, `OperationalFurnitureRequestResource`, `PresentsFurnitureRequest`), `app/Http/Requests/UpdateFurnitureRequestRequest.php`, `app/Http/Requests/ListOperationalFurnitureRequestsRequest.php`, `app/Services/Requests/*`, `app/Services/Attachments/*`, `app/Support/{PermissionCatalog,PermissionName,RequestStatus,FurnitureRequestIdentifier,AttachmentIdentifier}.php`, `config/attachments.php`.
- The existing backend request/attachment tests and the admin test suite.

Confirm the exact query parameter names, pagination envelope, identifier types, nullable fields, operational field allow-list, status transitions, response envelope, error codes and status codes from the repository.

Do not guess missing details. If runtime behavior contradicts the frozen contract, report the discrepancy and record it; do not silently invent a new API shape.

Preserve existing owner changes. Do not reset or revert unrelated files.

### 1.2 Pre-implementation audit findings and their disposition

The audit confirmed the following. The `Disposition` column is binding for this phase.

| # | Finding (verified) | Disposition |
| --- | --- | --- |
| G1 | `AttachmentResource` hard-codes `url: null`; `AttachmentStorage` has no read/temporary-URL capability; there is no download route. An operator cannot open a submitted attachment without server filesystem access. | **Fixed** by §2 item **C1** (owner-approved additive backend completion). This is the one change that determines whether the phase can PASS. |
| G2 | `request_reference` is searchable (`ListOperationalFurnitureRequests::applySearch`) but is never serialized in any resource; the website confirmation line that tries to render it never runs (`frontend/web/components/furniture-requests/furniture-request-form.tsx`). | **Fixed for the operational surface** by §2 item **C2**. The customer-facing acknowledgement is a separate customer-contract decision — see `D2` in §15. |
| G3 | `staff_internal_notes: null` conflates "actor lacks `requests.manage`" with "empty note". | **Fixed in the admin UI** — permission-keyed rendering (§8.4). No contract change. |
| G4 | There is no reply/respond-to-customer action; only status + internal notes. | **Accepted V1 limitation.** No reply UI, no invented email action. Record it. |
| G5 | Write allow-list is exactly `request_status` + `staff_internal_notes` with `minProperties: 1`; transitions `SUBMITTED→{IN_REVIEW,CLOSED}`, `IN_REVIEW→{CLOSED}`, `CLOSED→∅`; forbidden transition → `409 CONFLICT` (`field: request_status`). | **Integrated exactly** (§9). |
| G6 | No concurrency control on `staff_internal_notes` (last-write-wins); the `409` covers only the status transition race. | **Accepted V1 limitation.** Record it; do not build optimistic concurrency. |
| G7 | `api-contract.md §26.16` mentions `sort`/`sort_direction`, but `api-conventions.md §26.13`, `openapi.yaml` and the implementation do not define or accept them (unknown query parameter → `422`). | **Documentation reconciliation** — §2 item **C3**. The implementation is correct; the prose is the outlier. |
| G8 | `frontend/admin/lib/auth/permission-names.ts` exposes only `REQUESTS_VIEW`. | **Fixed** — add `REQUESTS_MANAGE` (§5.2). |
| G9 | STAFF cannot resolve the customer behind `user_id` (no `users.manage_authorized`); anonymous requests have `user_id: null`. | **Out of scope** (staff-administration capability). Render only the submitted contact snapshot; never link to a customer profile (§8.5). |
| G10 | Staff identity/onboarding is incomplete (`ADM-001…006` `501`, no `staff_state`). | **Owner-excluded** (F5/F11) — see §14.3. Do not attempt to fix. |
| G11 | At most one attachment per request; a second upload is rejected `409`. | **Reflected in the UI** (§8.6). No admin-side upload. |
| G12 | `NEW_MADE_TO_ORDER_REQUEST` notifications reach only `requests.manage` holders. | **Recorded.** No change. |

## 2. Approved backend completion (the only authorized Laravel work)

### 2.1 Why C1 is required for the phase to pass

The owner approved a contract change for private attachments **only if the phase needs it to PASS**.

Phase 19.10 needs it:

- The roadmap line for this phase (`AGENTS.md`) names *"private attachments"* as a deliverable of this phase.
- `AGENTS.md §14` (favor real business workflows over demos) and the Phase 11 exit condition (staff operate without touching the database or filesystem) mean a panel that only lists a filename is a demo, not an operational workflow. A request exists so the operator can evaluate the customer's requirement — including the photo or PDF they attached.
- The frozen contract **already** specifies a temporary signed URL: `api-resources.md §5.4` types `url` as `string (URI) | null` and describes it as *"Safe access URL if authorized (signed/temporary later); never permanent public URL; internal storage keys never exposed"*, and `api-conventions.md` already states for `REQ-007` *"no permanent public URL, `url` temporary signed"*.

Delivering the signed URL therefore **completes the documented contract** rather than inventing new behaviour. The only genuinely new surface is the download route itself, which is additive under `phases/api-breaking-change-policy.md §2` (*"Adding a new resource or endpoint"*).

If the owner withdraws this approval, the fallback is a metadata-only attachment panel and the phase can only reach **PARTIAL**. Confirm the approval stands before writing C1.

### 2.2 C1 — Private attachment delivery (`REQ-008`, additive)

**New endpoint:** `GET /api/v1/requests/{request}/attachments/{attachment}`

- Route name `api.requests.attachments.download`; registered under the existing `/api/v1` group.
- Guarded by Laravel's `signed` middleware (framework default alias; confirm it is available) plus a throttle. **No bearer token is required** — the signature is the credential, so the browser can open the link directly.
- Use an existing documented rate limiter rather than inventing a threshold (`api-conventions.md` lists the approved limits). `authenticated-read` (180/min; keyed by IP for an anonymous signed request) is the pragmatic choice. Record whichever limiter is used.
- The controller resolves the request identifier and the attachment identifier, both via the existing `*Identifier::decode`, and returns `404 RESOURCE_NOT_FOUND` (masked) when either is unknown **or when the attachment does not belong to that request**. Parent binding is a required security property.
- Stream the file from the attachment's own `storage_disk`/`storage_key`. The disk name and key are never serialized, logged, or included in a response body or URL path.
- Response headers: the stored `content_type`, `Content-Disposition: attachment; filename="…"` (a stable download disposition — see §9.2), `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`.
- Deleting nothing, creating nothing, and mutating nothing: the endpoint is a pure read.

**Resource change:** `AttachmentResource` must expose a real `url`.

- `url` = an absolute temporary signed URL for the attachment's own parent, or `null` when no authorized delivery route exists for that parent.
- Generate it with a short TTL read from `config('attachments.download_url_ttl_minutes')` (default `15`), declared beside the existing `config/attachments.php` keys and mirrored in `.env.example` the same way `ATTACHMENTS_DISK` is.
- Gate generation on the route existing for the parent type (including enquiry parents, which stay `null` until Phase 19.11 adds `ENQ-008`) so the resource never throws when a route is absent.
- The generated URL uses the application's configured absolute URL. Confirm it resolves to the API origin the admin browser can reach, and that `no-store` plus the TTL bound the exposure of a leaked link.

**Classification:** additive — new endpoint, plus a nullable response field that becomes populated. No existing path, method, request field, response field, enum, status code, authorization rule or business-state transition changes. Classify and record it under `ADR/API-FRZ-004`.

### 2.3 C2 — `request_reference` on the operational representation (additive)

- Add `request_reference` to `OperationalFurnitureRequestResource` only (`REQ-004`, `REQ-005`, `REQ-006` responses).
- **Do not** add it to `FurnitureRequestResource` or `PresentsFurnitureRequest`'s shared projection: `FurnitureRequestResourceTest::test_internal_and_schema_only_fields_are_absent` deliberately asserts its absence from the customer representation, and the customer contract is not part of this phase (`D2`).
- Rationale: the staff `search` filter already matches `request_reference`, and the operator must be able to read the reference they are quoting back to a customer. Without this, reference search is one-directional and unverifiable.
- **Classification:** additive response field → non-breaking per `phases/api-breaking-change-policy.md §2`.

### 2.4 C3 — Contract documentation reconciliation (no behaviour change)

The implementation is the source of truth. Update the documents to match it:

- `docs/api/api-contract.md §26.1` — add the `REQ-008` row (verb, path, actor, auth, purpose).
- `docs/api/api-contract.md §26.8` — replace the "deferred" attachment-access prose with the implemented `REQ-008` signed-URL delivery, TTL, and the no-permanent-public-URL rule.
- `docs/api/api-contract.md §26.16` — remove the `sort`/`sort_direction` mention that `api-conventions.md §26.13`, `openapi.yaml` and the implementation do not define; state the fixed order `created_at DESC, id ASC` and the unknown-query-parameter `422`.
- `docs/api/api-contract.md §26.20` — show a populated `url` in the staff detail example.
- `docs/api/api-resources.md §5.3` (operational request fields) — add `request_reference`; §5.4 — describe `url` as the implemented temporary signed URL.
- `docs/api/api-conventions.md §26` — align the attachment access paragraph and the filter/sort statement with `REQ-008` and the implementation.
- `docs/api/openapi.yaml` — new `REQ-008` path, `OpenApiAttachmentContractTest`-compatible `Attachment.url` description (*"Temporary signed private-access URL; null when no authorized delivery route exists"*), `request_reference` added to `OperationalFurnitureRequest`, and the `REQ-008` operation documented with `401`-free but `403` (invalid/expired signature), `404`, `429` responses.
- `docs/api/api-examples.md` — add the delivery example.
- `docs/decisions.md` — append `ADR/API-FRZ-004` using the `ADR/API-FRZ-003` template (Change Request / Impact Analysis / Classification / Contract Review / OpenAPI Update / Example Update / Verification / Approval / Reason / Status / Date / Affected). Record the owner's approval, its date, and the explicitly excluded customer-facing `request_reference` exposure.

### 2.5 Not approved

Unless the owner separately approves it in writing, do **not**:

- Change `REQ-001…REQ-007` request or response semantics in any way.
- Expose `request_reference` to customers, or change `FurnitureRequestResource`/`CustomerFurnitureRequestSummaryResource`.
- Add enquiry attachment delivery (`ENQ-008`) — Phase 19.11 owns it; C1 must be shaped so 19.11 can add the mirror route without reworking `AttachmentResource`.
- Add an admin-side upload capability, attachment deletion, or attachment replacement.
- Change the attachment allow-list, size limit, or the one-attachment-per-request rule.

## 3. Integrate the existing request API

Use the endpoints exactly as documented and implemented.

### 3.1 REQ-004 — List operational requests

`GET /api/v1/requests` — requires `requests.view`.

Accepted query parameters (allow-list; anything else → `422`):

| Parameter | Notes |
| --- | --- |
| `search` | ≤ 100 characters; matches name, email, phone, request reference, linked product. |
| `request_status` | `SUBMITTED` \| `IN_REVIEW` \| `CLOSED` (CLOSED enum). |
| `product_id` | Exact opaque `prod_…` identifier. Not a name search. |
| `created_from` | Inclusive ISO8601 UTC lower bound. |
| `created_to` | Inclusive ISO8601 UTC upper bound. |
| `page` / `per_page` | `per_page` 1–100. |

- The backend order is fixed (`created_at DESC, id ASC`). There is no `sort` parameter; do not send one.
- The envelope is `{ data: [...], meta: { pagination: { current_page, per_page, total, last_page, has_next, has_previous } } }`.
- `staff_internal_notes` is populated only when the actor holds `requests.manage`.
- Responses are `Cache-Control: private, no-store`; render per request, never cache across operators.

### 3.2 REQ-005 — Retrieve one operational request

`GET /api/v1/requests/{request}` — requires `requests.view`. `{request}` is `req_…`.
An unknown or unreadable identifier, or a deleted request, is `404 RESOURCE_NOT_FOUND` → the admin renders `notFound()`, matching the product detail precedent.

### 3.3 REQ-006 — Update operational request state

`PATCH /api/v1/requests/{request}` — requires `requests.manage`. Body allow-list is exactly:

```json
{ "request_status": "IN_REVIEW", "staff_internal_notes": "…" }
```

- `additionalProperties: false`; any other field → `422 INVALID_VALUE`.
- `minProperties: 1` — an empty body is rejected. Never send a no-op update.
- `request_status` transitions (current → allowed targets): `SUBMITTED → IN_REVIEW | CLOSED`; `IN_REVIEW → CLOSED`; `CLOSED → (none)`. A forbidden transition is `409 CONFLICT` with `field: request_status`.
- `staff_internal_notes`: **omitted** preserves the existing value; `null` or a whitespace-only string clears it; a non-blank string replaces it. Trimmed maximum is 5000 characters. The client must therefore send the field **only when it changed**.
- The operation is designed idempotent by value. Do **not** invent an `Idempotency-Key` for it and do **not** add an undocumented header. Prevent double submission in the UI instead (§9.5).
- The response is the updated `OperationalFurnitureRequest`.

### 3.4 REQ-008 — Download an attachment

Added by §2.2. Consumers only follow `attachments[].url`; they never construct the URL from an identifier.

## 4. Permission model

### 4.1 Rules

- The page, the detail route and every read require `requests.view`, repeated server-side on each route (deep links must never rely on navigation visibility).
- Status changes and internal-note edits require `requests.manage`.
- The internal-notes **display** is also gated on `requests.manage`, because the backend only populates the field for that permission (§8.4).
- Evaluate Laravel's effective permission data via `hasPermission`; a role never authorizes on its own, and missing permission data fails closed.
- ADMIN holds `requests.view` and `requests.manage` legitimately through the permission catalog; it receives no implicit bypass and none is hardcoded.
- Server Actions are directly reachable, so `updateRequestAction` (or equivalent) repeats the exact `requests.manage` requirement before calling Laravel.

### 4.2 Add the missing permission constant

Add to `frontend/admin/lib/auth/permission-names.ts`:

```ts
REQUESTS_MANAGE: "requests.manage",
```

It mirrors `backend/laravel/app/Support/PermissionName.php`. It grants nothing; Laravel remains authoritative. Update any union, map or authorization test derived from the same constant set.

## 5. Replace the placeholder

### 5.1 Route paths

Add to `frontend/admin/lib/navigation/admin-route-paths.ts`, following the `ADMIN_PRODUCT_PATHS` precedent:

```ts
export const ADMIN_REQUEST_PATHS = {
  list: ADMIN_ROUTE_PATHS.requests,
  detail: (requestId: string) => `${ADMIN_ROUTE_PATHS.requests}/${encodeURIComponent(requestId)}`,
} as const;
```

`ADMIN_PROTECTED_MATCHERS` already covers `/requests(.*)`, and nested request routes are not navigation entries — keep them out of the navigation registry for the same reasons documented for products and categories.

### 5.2 Navigation registry

In `frontend/admin/lib/navigation/admin-navigation.ts`, remove `placeholder: true` and `plannedFor` from the `requests` entry and keep `permission: AdminPermissionName.REQUESTS_VIEW`. This is what retires the user-visible "Not yet implemented — Phase 19.10" wording (`adminRouteStatus`, `AdminRoutePlaceholder`). Do not change the entry's `id`, `href`, `group`, `icon` or label.

### 5.3 Pages

- `app/(protected)/requests/page.tsx` — the inbox (Server Component). Replace the `AdminRoutePlaceholder` body with the real list.
- `app/(protected)/requests/[request]/page.tsx` — the detail (Server Component), mirroring the product detail page's `requireAdminRouteAccess(ROUTE)` → `attemptRequestRead` → `notFound()` shape.

### 5.4 Module layout

Follow the existing module conventions (one focused file per concern, no giant components):

- `lib/requests/request-api.ts` — REQ-004/REQ-005/REQ-006 calls through the shared transport, plus an `attemptRequestRead` outcome wrapper modelled on `inventory-api.ts`.
- `lib/requests/request-resource.ts` — strict defensive parsers for the operational envelope (`RequestResourceError` on unreadable data: missing/non-array `data`, wrong types, unreadable attachment entries). Reject rather than reconstruct.
- `lib/requests/request-query.ts` — the filter vocabulary, URL-state parsing/serialization, page reset on filter change, and the request-query serializer. Never emit an unknown parameter.
- `lib/requests/request-presentation.ts` — status labels, quantity/dimension/date/size formatting, contact precedence, reference display.
- `lib/requests/request-mutations.ts` — the dependency-free shared vocabulary: `RequestStatusValue`, allowed transitions, `MAX_STAFF_NOTES`, outcome types and safe user-facing messages.
- `lib/requests/request-actions.ts` — `"use server"` actions (`updateRequestAction`, and a read helper if needed). Each repeats `requireAdminRouteAccess` + the exact permission, validates the payload shape, calls Laravel, and revalidates the list and detail paths.
- `components/requests/*` — list filters, table, pagination, failure state, detail view, operational form, attachment panel.

### 5.5 README

Update `frontend/admin/README.md`: move `/requests` out of the placeholder list, document the implemented module, its permission gates, the exact endpoints, and the C1/C2 contract additions. Keep the route→permission→API table accurate.

## 6. Request inbox (`/requests`)

Server-rendered, real API data only.

### 6.1 Filters

- Native GET form (the inventory-filter precedent), so the list stays server-rendered and link-shareable.
- Controls: `search` (text), `request_status` (select: any / SUBMITTED / IN_REVIEW / CLOSED), `product_id` (exact `prod_…` text input with helper text — the API has no staff product-name search), `created_from` / `created_to` (date inputs).
- Date inputs produce `YYYY-MM-DD`; convert `from` → `T00:00:00Z` and `to` → `T23:59:59Z` and label the bounds as **UTC** so the inclusive-boundary behaviour is explicit rather than surprising.
- Reset `page` to 1 whenever any filter changes. Provide an explicit clear-filters control.
- A filter control's state must reset per control (use a per-control `key`; a form-level `key` causes a React hydration mismatch — this was already hit in Phase 19.9).

### 6.2 Table

Columns: **Reference** (`request_reference`, C2), **Submitted** (`created_at`), **Customer** (name + the submitted phone/email), **Furniture** (linked product name, or an explicit "Custom request" for `product_id: null`), **Quantity** (or an explicit "Not specified" for `null`), **Status**, **Attachment** (present/absent indicator only), **Action** (open detail).

- Do not fetch attachment bytes for the list.
- Do not render `user_id` as a customer link (G9).
- The row action links to `ADMIN_REQUEST_PATHS.detail(id)` using the opaque `req_…` id.

### 6.3 Pagination

Use the documented `meta.pagination` and preserve every active filter in the page links (the inventory-pagination precedent). Never fabricate a page count.

### 6.4 States

Distinct, accessible loading, empty and failure states:

- Empty (no requests, and no filters) → "No requests have been submitted."
- Empty (filters active) → "No requests match these filters." plus the clear action.
- Failure → the existing failure-state component with the mapped reason; **an unreadable response is `invalid` (rejected parsing), not `unavailable`** (the F16 lesson).
- A failed request must never render as an empty list.

## 7. Request detail (`/requests/{request}`)

Present the authoritative REQ-005 record:

- **Header**: `request_reference` as the page title with the opaque `req_…` id as secondary technical context; current status; submitted and last-updated timestamps.
- **Customer**: `name`, `phone`, `email`. Null values render as an explicit "Not provided" — never a blank cell, a dash without a legend, or a fabricated value.
- **Furniture**: linked `product.name` (and slug) when `product` is present; an explicit "Custom / general request" when `product_id` is `null`. **Link to `/products/{product_id}` only when the identity also holds `products.manage`**; otherwise render the name as plain text (a request-only operator must not be sent to a route they cannot open).
- **Specification**: `quantity` (distinguish `null` = unspecified from a number), `dimensions` (`length`/`width`/`height`/`unit`, showing the canonical `cm`; absent measurements shown as unspecified), `material`, `color`, `notes`.
- **Attachment panel** (§8.6).
- **Operational form** (§8.3).

Do not render a status timeline: the request API exposes no per-request status history, and inventing one from `updated_at` would be fabricated data. Audit visibility is a later phase (§10).

## 8. Lifecycle actions and attachment inspection

### 8.1 Status control

- A select offering the current status plus only the transitions the state machine allows (`SUBMITTED → IN_REVIEW, CLOSED`; `IN_REVIEW → CLOSED`).
- Provide a "no change" option so notes can be updated without forcing a status transition.
- When `request_status` is `CLOSED`, render the status read-only with an explicit "This request is closed." statement and offer no further transition (there is no reopen in V1).
- The UI's allowed set is guidance only; Laravel validates the transition authoritatively.

### 8.2 Internal notes control

- A textarea with a persistent accessible label, helper text explaining the replace/clear semantics, and a 5000-character limit.
- Submit `staff_internal_notes` **only when the value differs from the loaded value**; omitting it preserves the stored note.
- Clearing must send `null` (or a whitespace-only string) deliberately — never send `""` merely because the operator did not type anything.

### 8.3 Submission behaviour

- Send only changed fields from the allow-list, never both if only one changed, never an empty body, never an unknown field.
- Disable the submit control while in flight; prevent double submission client-side.
- On success: revalidate the detail and the list from authoritative state (do not trust the mutation response shape), show a confirmation, and reset the form to the returned values.
- On failure, map and surface the exact outcome:
  - `409 CONFLICT` → "This request was changed by someone else. Reload to see the current status." No automatic retry.
  - `422` → surface the field-level validation error.
  - `403` → the permission-denied treatment.
  - `401` → the session treatment (`session`), no data rendered.
  - `429` → the throttled treatment, including `Retry-After` where present.
  - network/unknown → the unavailable treatment.
- No failure is ever reported as success.

### 8.4 Internal-notes gating (resolves G3)

- The backend populates `staff_internal_notes` only when the actor holds `requests.manage`.
- With `requests.manage`: show the stored note, or an explicit "No internal notes recorded."
- Without `requests.manage`: show a distinct "Internal notes are not available with your permissions." — never "No internal notes recorded", which would assert an empty state the API did not confirm.
- The edit control renders only with `requests.manage`.

### 8.5 Customer identity (G9)

- Render the request's own contact snapshot only.
- Do not resolve, link to, or fetch a customer profile; the operational request resource exposes `user_id` as an opaque `user_…` value and STAFF has no `users.manage_authorized`.
- Render `user_id: null` as "Anonymous submission".
- Never treat `user_id` as an authorization signal.

### 8.6 Attachment panel (C1, G11)

- Zero attachments → "No attachment was submitted with this request."
- One attachment → `filename`, a readable content-type label, and the formatted size in bytes/KiB/MiB.
- When `attachments[].url` is present → an "Open attachment" link with `target="_blank"` and `rel="noopener noreferrer"`.
- When an attachment exists but `url` is `null` → an explicit "Preview or download is not available for this attachment." (defensive; should not occur for a request attachment after C1).
- Never render `storage_key`, `storage_disk`, a numeric id, or a filesystem path.
- Never construct a download URL from the attachment id, and never proxy or re-stream the bytes through an admin route handler.
- Add a short, honest statement that opened links are time-limited and that reloading the request refreshes them. Do not attempt client-side expiry detection.
- **Inline preview is deliberately out of scope** (§9.2). No `next/image` remote host, no new image allow-list entry, no `CATALOG_MEDIA_BASE_URL` change.

## 9. Design, security and boundary decisions

### 9.1 Design system

Use the existing canonical tokens and components (`DataTable`, `PageHeader`, `PageContainer`, `EmptyState`, `ErrorState`, `LoadingState`, form primitives, `StatusIndicator`). Do not introduce a new design system, dependency, or dashboard-style analytics.

### 9.2 Download disposition

`REQ-008` serves with `Content-Disposition: attachment`. This is deliberate: it avoids inline PDF rendering on a privileged origin, keeps the admin free of any remote image allow-list change, and makes the security posture trivial to reason about. The operator downloads and opens the file locally.

### 9.3 Attachment link exposure

Signed URLs are bearer credentials. The response is `private, no-store`; the TTL bounds leakage; the link is opened in a new tab with `rel="noopener noreferrer"`. Do not log, persist, or otherwise retain a signed URL beyond the rendered page.

### 9.4 No invented reply channel (G4)

Do not add "Reply", "Send email", "Mark contacted", "Convert to order", or "Quote" controls. There is no API for them. Render the contact details for manual follow-up and record the limitation.

### 9.5 No optimistic concurrency

Internal notes are last-write-wins at the API (G6). Do not implement ETag/`If-Match`, do not send an assumed version, and do not claim conflict detection for notes. Only the status transition can legitimately conflict (`409`).

### 9.6 No fabricated data anywhere

No dashboards, no metrics, no request counts, no response-time figures, no status timeline, no placeholder rows, no seeded fake requests.

## 10. Explicitly out of scope

Do not implement:

- Any Laravel/schema/business-rule change beyond §2.2 (`C1`) and §2.3 (`C2`).
- Any API contract or OpenAPI change beyond §2.4 (`C3`).
- Enquiry management or `ENQ-008` (Phase 19.11).
- Customer-facing `request_reference` exposure or any change to `frontend/web` (owner decision `D2`).
- Request → Order conversion, quoting, pricing, invoicing, or payment.
- Customer reply/notification sending.
- Admin-side attachment upload, deletion, replacement, or inline preview.
- Bulk actions, export, or any unsupported sorting.
- Customer-profile resolution, linking, editing, or impersonation.
- Staff management, onboarding, invitations, approvals, or role editing (F5/F11 — owner-excluded).
- A new admin API proxy, route handler, or direct database/filesystem access path.
- Fake request data, metrics, or states.
- Unrelated changes to other admin pages.

## 11. Testing requirements

Extend the existing admin test suite with deterministic tests. Do not require live Clerk credentials or a real API for ordinary automated tests. Do not weaken tests to make the implementation pass. Add every new test file to the explicit list in `frontend/admin/package.json`.

### 11.1 Transport tests

- REQ-004/REQ-005/REQ-006 are called with the exact documented paths and methods.
- Only allow-listed query parameters and body fields are emitted; unknown parameters are never sent.
- `PATCH` sends `request_status` and/or `staff_internal_notes` only, only when changed, and never an empty body.
- Existing authorization and `Accept` headers remain intact; arbitrary callers cannot override security-sensitive headers.
- Error-envelope handling is unchanged.

### 11.2 Authorization tests

- `requests.view` opens the inbox and the detail.
- Without `requests.view` the routes are denied.
- A view-only identity sees no usable status/notes control.
- A view-only identity cannot invoke the update server action successfully.
- `requests.manage` enables the status and notes workflow.
- The update action repeats the exact permission check.
- Permission checks use effective permission data, not a role shortcut.
- ADMIN receives no hardcoded bypass.
- `REQUESTS_MANAGE` resolves to `requests.manage`.

### 11.3 Listing and filtering tests

- The exact documented filters serialize correctly, including the UTC date-boundary conversion.
- Unknown query parameters are never emitted.
- Filter changes reset pagination; pagination preserves filters.
- The backend's fixed order is preserved (no client-side reordering presented as authoritative).
- Empty data is distinguishable from a failed request.
- An unreadable/malformed response renders `invalid`, not an empty list and not `unavailable`.

### 11.4 Detail tests

- A `404` renders `notFound()`.
- Unreadable payloads are rejected, not reconstructed.
- Null fields render as explicit "Not provided"/"Not specified" rather than blanks or zeros.
- The product link renders only with `products.manage`; otherwise the name is plain text.
- `user_id: null` renders as "Anonymous submission"; a non-null `user_id` is never linked or resolved.

### 11.5 Lifecycle tests

- Only the allowed transition targets are offered for each current status; `CLOSED` offers none.
- Notes are sent only when changed; clearing sends `null`, not `""`.
- A no-op submit is impossible.
- `409`, `422`, `403`, `429`, `401` and network failures are each handled and surfaced safely.
- No automatic retry occurs after any failure.
- Success revalidates authoritative state rather than echoing the response.
- No reply/quote/convert control exists.
- No status timeline is rendered.

### 11.6 Attachment tests

- Zero/one attachment states render correctly.
- A present `url` renders an accessible "Open attachment" link with `target="_blank"` and `rel="noopener noreferrer"`.
- A `null` `url` with an attachment present renders the explicit unavailable message.
- Storage keys, disks and numeric ids are never rendered.
- No download URL is constructed client-side from an identifier.
- The panel performs no byte fetch, and the list performs no attachment fetch.

### 11.7 Backend tests (required by §2)

- `REQ-008` returns the file bytes for a valid signed URL (valid request/parent pair).
- An unsigned or tampered URL is rejected (`403`).
- An expired URL is rejected (`403`).
- A signed URL whose attachment belongs to another request is rejected `404`.
- An unknown request or attachment identifier is `404` without leaking internals.
- The response sets the stored content type, `nosniff`, `private, no-store`, and an attachment disposition; no storage disk, key, or path appears anywhere in the response or the URL path.
- `AttachmentResource` exposes a non-null signed `url` for a request attachment and `null` for a parent type with no delivery route.
- `OperationalFurnitureRequestResource` exposes `request_reference`; `FurnitureRequestResource` still does not.
- Update `FurnitureRequestAttachmentApiTest` (currently asserts `url === null` and the exact key set) and any OpenAPI contract test affected by C1–C3.
- Regression: the existing `OperationalFurnitureRequestApiTest`, `FurnitureRequestResourceTest`, `ScopedAttachmentUploadApiTest`, `SecurityRequestBoundaryTest`, `OpenApiRequestContractTest` and `OpenApiAttachmentContractTest` still pass.

## 12. Accessibility and design system

- Semantic headings, labels, tables, list/link semantics and buttons; the table uses `th[scope="col"]`.
- Every filter, form field and action has a persistent accessible label — placeholder text is not a label.
- Visible keyboard focus; keyboard-operable filters, links and forms.
- Status, permission denial, validation and failure are never conveyed by color alone.
- The loading state announces itself (the `role="status"` precedent from 19.9).
- Action targets remain usable on narrow viewports; the table scrolls rather than breaking the layout.
- Follow the project's responsive and reduced-motion conventions.
- Keep the interface operational and restrained.

## 13. Quality gates

Frontend (required):

- `npm run lint`
- `npm run typecheck`
- `npm run test`
- `npm run build`
- `git diff --check`

Backend (required because §2 changes Laravel):

- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`
- the relevant `php artisan test --compact` filters (request/attachment/OpenAPI suites), plus `phpunit.mysql.xml` where a real-engine suite applies

Inspect the final diff for:

- Backend changes beyond C1/C2/C3.
- Any customer-contract or `frontend/web` change.
- Permission bypasses or role-only authorization.
- Sentinel `url` construction client-side, or storage internals rendered.
- Unknown fields or empty bodies sent to REQ-006.
- Missing `requests.manage` checks in the server action.
- False empty states or unreadable responses treated as outages.
- Invented reply/metrics/timeline data.
- Secret, bearer-token, signed-URL, or storage-key exposure.
- Changes to product/catalog fixtures or published product states.

If a gate fails, report the exact command and failure. Do not claim it passed unless it actually ran.

### 13.1 Live verification

Live verification is limited to an ADMIN identity. STAFF onboarding is owner-excluded (F5/F11), so a real STAFF-only session cannot be created and must not be faked:

- An authorized operator can open `/requests` and see real records.
- Filters, search (including by reference) and pagination work against the real API.
- A detail page opens from the inbox and from a deep link.
- A valid status transition succeeds and refreshes from authoritative data.
- An internal note can be added, replaced and cleared.
- An attachment can be opened through the signed link, and the link fails after its TTL.
- An invalid transition is rejected with the `409` message.
- Narrow-viewport and keyboard interactions remain usable.

Use a development request record. Do not modify production data merely to test the interface. If live verification is unavailable, report that fact explicitly; automated tests and mocked responses do not establish live integration.

## 14. Risks and constraints to record

### 14.1 Live-ready but not live-proven

The request workflow is complete in Laravel and unit/feature-tested, but no STAFF session can be provisioned. Record the ADMIN-only live verification and the residual risk.

### 14.2 Contract debt recorded, not hidden

Record G4 (no reply channel), G6 (notes last-write-wins), G9 (no customer resolution), G11 (one attachment), G12 (notification reach) in `docs/findings-and-limitations-register.md` with a disposition, so they are visible rather than implied.

### 14.3 Owner-excluded

Do not attempt F5/F11 (staff endpoints `501`, staff onboarding). This phase must not create, invite, approve, or promote a STAFF identity.

### 14.4 Request-only release boundary

This phase must not enable cart/checkout/order/payment behavior. No published-product or product-type state is changed here.

## 15. Completion report, owner decisions and roadmap

Create `phases/phase-19.10-completion.md` using the established completion-report conventions. Record:

1. Final status: `PASS`, `PARTIAL`, or `BLOCKED`.
2. Files changed and the purpose of each significant change.
3. REQ-004/REQ-005/REQ-006 integration details and the exact filter/body allow-lists used.
4. The C1 delivery mechanism: route, signed-URL TTL, headers, and how parent binding and expiry are enforced.
5. The C2 `request_reference` exposure and confirmation that the customer representation is unchanged.
6. The C3 documentation reconciliation, including the `§26.16` `sort` correction.
7. How `requests.view` and `requests.manage` are each enforced, including in the server action.
8. The audit-gap disposition table (§1.2) with the final outcome per item.
9. Automated test counts, backend suite results, and all quality-gate results.
10. Whether the real authenticated request workflow was tested, and with which role.
11. Unresolved live-verification limitations (F5/F11) and the ADMIN-only verification.
12. Remaining limitations and owner actions.
13. Proof that no unrelated backend, contract, customer-website, or fixture change was introduced.

Update the approved roadmap status according to repository instructions, preserving owner-managed changes. Do not mark a live smoke test as complete unless it was performed.

### Owner decisions referenced

- **D1 — Attachment delivery (APPROVED 2026-10-17).** Owner approved a contract change for private attachments if the phase requires it to PASS; §2.1 establishes the requirement. Record the approval in `ADR/API-FRZ-004`. If withdrawn, fall back to a metadata-only panel and report `PARTIAL`.
- **D2 — Customer-facing `request_reference` (OPEN).** The website acknowledgement tries to render a reference the customer contract withholds, and the customer's only currently exposed identifier (`req_…`) is not staff-searchable. Recommendation: expose `request_reference` in the customer request representation **and** `CustomerFurnitureRequestSummary` in a separate, explicitly approved customer-contract change (which also repairs the website line with no website code change), or remove the dead reference line from `frontend/web`. Not part of this phase.
- **D3 — `§26.16` `sort` mention (APPROVED as a doc correction).** Treated as a documentation slip; the implementation is authoritative; no code change.
- **D4 — Staff-related gaps F5/F11 (OWNER-EXCLUDED).** Not addressed here.

### Acceptance criteria

Phase 19.10 is complete when:

- The `/requests` placeholder is replaced by a real, permission-aware operational workflow, and the navigation entry no longer advertises a future phase.
- REQ-004 lists and filters real paginated requests using exactly the documented parameters, order and envelope.
- REQ-005 provides the authoritative request record, with nulls rendered explicitly rather than invented.
- REQ-006 updates only `request_status` and/or a changed `staff_internal_notes`, follows the real transition rules, and surfaces `409`/`422`/`403`/`429`/`401` without auto-retry or false success.
- `requests.view` and `requests.manage` are enforced independently, server-side, including in the server action.
- An authorized operator can open the attachment submitted with a request through a time-limited, authorized URL — without filesystem or database access — and expired or tampered links fail closed.
- Internal-notes rendering distinguishes "not authorized" from "empty" (G3), and the customer representation is unchanged.
- Reference search is verifiable from the inbox because `request_reference` is exposed operationally (C2).
- The approved additive contract changes are documented in `api-contract.md`, `api-resources.md`, `api-conventions.md`, `api-examples.md`, `openapi.yaml` and `docs/decisions.md` (`ADR/API-FRZ-004`), and are classified non-breaking.
- No fake request data, metrics, timelines, reply channels, or unsupported operations are introduced.
- Customer-facing applications, other admin modules, staff onboarding and published-product state are untouched.
- Every quality gate in §13 passes, and the completion report distinguishes automated verification from live authenticated verification.

Begin with the audit, then implement the smallest complete, production-conscious change set. Make only the approved backend changes in §2; leave every other Laravel, contract and customer-frontend behaviour exactly as it is.
