# SL Furnitures — Phase 19.6: Product Management

**Group:** S — Admin Dashboard Frontend  
**Phase:** 19.6 — Product Management  
**Application:** `frontend/admin`  
**Framework:** Existing Next.js App Router, TypeScript and MUI  
**Authentication:** Existing Clerk integration  
**Authorization:** Existing Laravel-backed identity and exact-permission route guards  
**API:** Existing Laravel `/api/v1` contract and typed transport  
**Design authority:** `frontend/design-system/tokens.css`  
**Local origin:** `http://localhost:3001`  
**Status:** APPROVED FOR IMPLEMENTATION, subject to the contract and runtime checks below

## 1. Objective

Replace the existing protected Products placeholder with a usable product management module for SL Furnitures.

Implement:

1. A paginated product list using an approved Laravel operational product endpoint.
2. Search, filters and sorting only where supported by the actual API contract.
3. Product detail views.
4. Product creation.
5. Product editing.
6. Product state and product-type presentation aligned with Laravel's actual domain model.
7. Variant-aware product details using the variant information actually returned by the API.
8. Form validation aligned with Laravel's request validation and error envelope.
9. Correct loading, empty, error, forbidden, not-found and submission states.
10. Automated tests, responsive/accessibility verification and a phase completion report.

This is a real operational feature, not a static mockup.

**Laravel remains authoritative for product data, state, validation, permissions and persistence.** The frontend must not introduce a parallel product model or simulate successful mutations.

Keep this phase focused on products. Do not implement the separate category-management, inventory-adjustment, request-management or enquiry-management workflows.

## 2. Mandatory audit before implementation

Before editing, inspect and read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/admin/AGENTS.md`.
- `frontend/admin/README.md`.
- `frontend/admin/app/(protected)/products/page.tsx`.
- Existing protected layout and route guard implementation.
- `frontend/admin/lib/api/client.ts` and related API types/errors.
- `frontend/admin/lib/navigation/admin-navigation.ts`.
- Existing shared form, table, state and dialog components.
- Existing admin MUI theme and design tokens.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `frontend/design-system/COMPONENTS.md`.
- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- Laravel's actual product routes, controllers, FormRequests, resources, policies, permission catalog, enums/state machines, product and variant models, and relevant tests.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/phase-19.2-completion.md`.
- `phases/phase-19.3-completion.md`.
- `phases/phase-19.4-completion.md`.
- `phases/phase-19.5-completion.md`.
- `phases/group-S-phases.md`.

Verify filenames and actual exports before using them.

### Pre-implementation report

Before making changes, report:

1. The exact approved list/detail/create/update endpoints and their runtime behavior.
2. The response envelope, product resource fields and pagination metadata.
3. The actual product type and product state values.
4. The exact create/update field rules and validation limits.
5. The fields and relationships returned for variants.
6. Whether category selection can be populated from an approved API.
7. Which product operations the current permission catalog permits.
8. Any unresolved API-contract/runtime discrepancy.
9. The planned frontend architecture and tests.

If an endpoint or required field has no approved contract, do not invent it. Identify the dependency and proceed only with independently usable, contract-supported parts of this phase.

## 3. API operations and contract integrity

The Phase 19.1 audit identifies the following existing operations. Verify each one against the current OpenAPI/API contract and actual Laravel implementation before use.

| Operation | Expected endpoint | Required handling |
|---|---|---|
| Operational product list | `GET /api/v1/admin/products` | Use only after verifying the approved request and response contract. |
| Operational product detail | `GET /api/v1/admin/products/{product}` | Use the API-returned resource and identifiers. |
| Create product | `POST /api/v1/products` | Follow `StoreProductRequest` and its documented resource response. |
| Update product | `PATCH /api/v1/products/{product}` | Follow `UpdateProductRequest` and its documented resource response. |
| Upload product image | `POST /api/v1/products/{product}/images` | Implement only if it is supported by the approved contract and fits the existing product-management UX. |
| Create product variant | `POST /api/v1/products/{product}/variants` | Reported as returning `501`; do not present it as a working operation unless the backend is verified to support it. |

Do not confuse public catalog endpoints with operational product endpoints. Public resources may omit unpublished products, internal state or other fields needed by staff.

### Contract discrepancy gate

The Phase 19.1 audit noted that the operational list/detail endpoints exist at runtime while their status in the frozen API inventory is inconsistent.

- Compare the actual endpoint definitions, OpenAPI schemas, resource serialization, authorization middleware and tests.
- Do not silently rewrite `api-contract.md`, `api-resources.md`, `api-conventions.md` or `openapi.yaml`.
- Do not introduce a new endpoint, field, enum, permission or response envelope.
- If the existing documented schemas fully define the endpoint and the discrepancy is only a status-label mismatch, use the documented schema and report the discrepancy without editing the frozen contract.
- If the endpoint shape or authorization contract is genuinely undocumented or contradictory, stop use of that endpoint, report the exact blocker and do not replace it with a guessed or public-only endpoint.

The same rule applies to variant details, category options and product-state changes.

## 4. Authorization and route protection

Preserve the Phase 19.3 security boundary and Phase 19.4 shell.

The existing Products route must retain its URL and registry entry.

At the top of the page, preserve the existing route-guard pattern:

`requireAdminRouteAccess(adminRoute(ADMIN_ROUTE_PATHS.products))`

Use the exact verified project API and keep the guard as the first page operation, before rendering protected product content or performing data access.

Requirements:

- Keep the Products page behind the existing protected layout.
- Continue using Laravel-resolved identity and effective permissions.
- Do not trust Clerk metadata, client-supplied roles or a client-only `isAdmin` flag.
- Do not allow ADMIN to bypass an exact permission requirement.
- Do not create new permission identifiers.
- Do not change Laravel authorization to accommodate the frontend.
- Do not weaken denied, inactive-account, invalid-session or API-unavailable states.
- Keep tokens server-side and use the existing request-scoped token retrieval.
- Never serialize a bearer token, raw `/me` response or private authorization payload into a Client Component.
- Preserve private/no-store behavior for authenticated API requests.

Laravel's permission catalog defines both `products.view` and `products.manage` (`backend/laravel/app/Support/PermissionName.php`), and both are granted to STAFF and ADMIN. That catalog fact is separate from this route's approved requirement: the Products route keeps the existing registry's verified requirement, `products.manage`. The presence of `products.view` does not grant access to this admin route and must not be substituted for `products.manage`; **do not invent a permission or grant access through a frontend-only rule**.

The operational read endpoints (`GET /admin/products`, `GET /admin/products/{product}`) accept `products.view` or `products.manage`, while catalog writes require `products.manage`. The frontend must use the exact permission the registry requires, `products.manage`, and leave authorization to Laravel. Any change to this route's required permission requires a separately approved frontend decision, not a local rule.

## 5. Product list

Replace the Products placeholder with a real product index page.

### Page structure

Use the existing shell and shared components:

- `PageContainer`.
- `PageHeader`.
- Existing table, pagination, field, button and state components where suitable.
- Existing theme and semantic design tokens.

The page should provide:

- A clear Products heading and short description.
- A visible Create product action for users who pass the existing route guard.
- A search field if supported by the operational list contract.
- Filters only for supported server-side fields.
- A product table or responsive list.
- Server-provided pagination.
- A clear way to open product details and editing.

Do not make the page resemble a generic analytics dashboard. Avoid excessive cards and decorative metrics.

### Product rows

Display only fields present in the approved operational product resource and appropriate for the authorized operator.

Useful fields may include product name, image, product type, category, price, product state, variant summary and updated time—but verify the exact resource before selecting columns.

Rules:

- Never fabricate a product name, identifier, price, status, category or variant count.
- Do not derive a product's state from whether a value happens to be present.
- Do not confuse product type with product state.
- Do not display inventory quantities from a public catalog resource or infer stock from product availability.
- Use API-returned identifiers for actions and API-returned values for display.
- Use semantic links or buttons with accessible names.
- Provide appropriate truncation and wrapping for long product names.
- Keep columns usable on mobile; allow a responsive row/card layout if the shared table cannot fit.
- Do not hide important information solely behind hover.

### Search, filtering and sorting

Use only parameters documented and implemented by the operational product-list endpoint.

- Use the API's exact parameter names and allowed values.
- Do not invent aliases, filter semantics or sort fields.
- Preserve pagination and filter state consistently when navigating between pages.
- Avoid client-side filtering of only the current server page when the user expects a global filter.
- Debounce search only if needed, with cleanup and no stale response overwriting newer results.
- Cancel or ignore obsolete requests where appropriate.
- Do not trigger a new request for every keystroke without an intentional interaction design.
- If a filter is not supported by the operational endpoint, omit it and record the limitation.

Use the API's pagination metadata, not locally estimated totals.

## 6. Product detail and variant-aware presentation

Create a detail route consistent with the existing admin route conventions and route guard utilities. Use the stable product identifier expected by Laravel. Do not derive an ID from a slug or create a new identifier format.

The detail page should display the authoritative product resource and provide an Edit action.

### Product information

Show the approved fields available from the operational resource, which may include:

- Name and description.
- Product type.
- Category.
- Product state.
- Price and currency.
- Public slug, if returned and approved for operator display.
- Product images, if returned.
- Created/updated timestamps, if returned.
- Existing variant information, if returned by the approved API.

Use appropriate loading, error and not-found handling. A failed request must not leave stale details from a previously viewed product visible.

### Variant-aware details

The detail view must accurately represent product variants when the approved resource provides them.

- Inspect the real `ProductVariant` schema, relationships and resource serialization.
- Show variant attributes and values only when returned by the API.
- Use authoritative variant IDs and default-variant indicators when present.
- Display variant-specific prices, SKUs, dimensions, materials or availability only if the resource actually contains those fields.
- Distinguish product-level fields from variant-level fields.
- Do not derive variants from product text, names, product type or images.
- Do not invent a default variant when none is returned.
- Do not assume every product must have multiple variants.
- If variants are not returned by the approved detail resource, show a neutral explanation that variant information is not available through the current API rather than fabricating an empty operational state.

**Variant creation is not part of the working mutation scope while `CAT-010` returns `501`.** Do not submit requests that are known to fail. Do not render an enabled Add variant button that promises a working operation. If useful, show a neutral disabled/not-yet-available explanation and record the backend dependency.

Do not implement variant editing or deletion unless the audit finds an existing approved and operational endpoint for that exact operation.

## 7. Create product flow

Implement a real create form using the approved `POST /api/v1/products` contract.

### Form requirements

- Provide persistent labels and appropriate required/optional indicators.
- Follow the existing form and validation component conventions.
- Organize related fields into clear sections.
- Provide field-level errors and an accessible submission-level error summary where appropriate.
- Prevent duplicate submissions while a request is in flight.
- Preserve user input after a validation failure.
- Confirm navigation or display a clear success state only after the API confirms creation.
- Use the created resource returned by Laravel rather than constructing a local product record.
- Navigate to the new product's detail page only if the returned resource includes the required identifier and the route contract supports it.

### Fields and options

Derive the form fields from `StoreProductRequest`, the approved API schema and the current Laravel model.

Do not assume every field is required, nullable or writable.

Do not invent fields such as `status`, `is_active`, `sku`, `stock_quantity`, `sale_price`, `brand` or `variant_options` without verifying that they exist in the approved create contract.

Use exact backend values for enums and product types. In particular, preserve the supported distinction between `IN_STOCK` and `MADE_TO_ORDER` if these are the actual values in the current contract.

A made-to-order product is a valid first-class product type. Do not disable or label it as invalid merely because checkout is deferred.

### Category selection

Product creation may require a category reference. Inspect the actual product request and approved category-list contract.

- Use authoritative category identifiers and labels returned by Laravel.
- Do not hardcode category IDs or seed names into the form.
- Do not create a second frontend category catalog.
- Do not assume the public category endpoint exposes inactive or all operational categories.
- If the available approved endpoint cannot provide valid options for a required category field, report the blocker and do not enable a form that sends guessed identifiers.

Do not implement category creation or editing in this phase.

### Submission behavior

- Submit only contract-approved fields.
- Use the existing typed Laravel transport.
- Respect the documented JSON and multipart conventions.
- Use the existing API envelope and error normalization.
- Map `422` validation errors to the correct fields.
- Handle `401`, `403`, `404`, `409`, `429` and server/network failures according to the project's established error contract.
- Do not expose stack traces, SQL errors or raw backend internals.
- Do not report success until the server returns a successful response.
- Do not automatically retry a non-idempotent create request in a way that could create duplicate products.

## 8. Edit product flow

Implement editing through the approved `PATCH /api/v1/products/{product}` endpoint.

### Loading existing data

- Load the current product through the approved operational detail endpoint.
- Populate the form from Laravel's returned resource.
- Do not reuse the create form's arbitrary defaults as though they were saved values.
- Clearly distinguish loading, not-found, forbidden and API-unavailable states.
- Do not allow an edit submission until the current resource is loaded successfully.

### Update behavior

- Submit only editable fields supported by `UpdateProductRequest`.
- Preserve fields the operator did not change.
- Follow the API's partial-update semantics exactly.
- Do not send fields simply because they exist on the displayed resource.
- Avoid overwriting server-managed fields, timestamps, IDs, derived availability or variant relations.
- Prevent duplicate submissions.
- Preserve user edits after a `422` response.
- Handle stale or conflicting updates according to the actual API contract.
- After a successful update, render the returned authoritative resource or re-fetch it through the approved endpoint.
- Do not show optimistic success that assumes the mutation succeeded.

If the backend uses optimistic concurrency or conflict responses, follow the documented mechanism. Do not invent ETags, revision numbers or idempotency fields.

## 9. Product states and type distinctions

Before implementing state controls, determine the exact state model from Laravel's enums, policies, FormRequests, resource serialization and tests.

The UI must distinguish the following concepts where they exist:

1. **Product type:** for example `IN_STOCK` or `MADE_TO_ORDER`.
2. **Product state:** the actual lifecycle or publication state defined by Laravel.
3. **Availability:** a server-returned or documented derived/public availability concept.
4. **Inventory:** the separately authorized inventory domain, owned by Phase 19.9.

Do not treat these as interchangeable.

### State display

- Use exact API values and an explicit presentation mapping.
- Use readable labels without changing the value sent to Laravel.
- Do not infer that an unpublished, unavailable or soft-deleted product is in a particular state unless the API explicitly defines that meaning.
- Do not render unknown future enum values as a successful known state; use a safe neutral fallback and preserve the raw value only where approved for operator diagnostics.

### State changes

If the existing update endpoint allows a state transition, expose it only when the transition is approved by the backend contract and state machine.

- Use the exact accepted values.
- Respect any role or permission rules enforced by Laravel.
- Confirm destructive or high-impact transitions where the design system supports it.
- Show server validation/conflict responses without pretending a transition occurred.
- Refresh authoritative product state after a successful change.
- Do not implement a new activate, deactivate, publish, archive or restore endpoint.
- Do not invent transition rules in TypeScript.
- Do not assume `PATCH` accepts every field in the resource.

If state changes are not supported by the current API, display the actual state read-only and document the backend dependency.

## 10. Product images

Inspect the existing `CAT-009` image-upload contract and Laravel implementation.

Only implement image upload if the existing contract provides the required behavior and it fits the product create/edit workflow without a new API contract.

If implemented:

- Use the approved multipart field name and validation constraints.
- Follow the existing upload size, MIME-type and image-dimension rules.
- Provide progress or pending feedback where supported by the current transport.
- Prevent duplicate uploads.
- Display useful validation errors for rejected files.
- Do not store images as base64 in product JSON.
- Do not construct public media URLs from guessed storage paths.
- Render the image URL or media representation returned by Laravel.
- Provide meaningful alt text for product images.
- Do not claim image deletion, reordering or replacement is supported unless the API exposes those operations.

If image upload is outside the current approved contract or cannot be verified, implement the other supported product operations and record the limitation.

## 11. Frontend architecture

Follow the existing admin application structure. Avoid building a second data layer.

Use focused modules under the existing feature architecture, such as:

- Product domain/resource types.
- Product API query/mutation functions.
- Product list page components.
- Product detail components.
- Create/edit form components.
- Product state/type presentation helpers.
- Tests.

These are suggested responsibilities, not instructions to duplicate existing modules.

Requirements:

- Keep all authenticated API requests behind `frontend/admin/lib/api/client.ts` or its verified established equivalent.
- Use the existing Clerk token retrieval and request-scoped identity behavior.
- Preserve `cache: "no-store"` for authenticated business requests.
- Do not place authenticated product responses in a shared cache.
- Do not serialize bearer tokens into Client Components.
- Use Server Components by default and small Client Component boundaries for actual form/table interaction.
- Respect the established Next.js/MUI Server/Client Component constraints.
- Do not add a new API proxy, route handler, backend endpoint or service abstraction framework.
- Do not add dependencies unless an unavoidable, documented requirement exists.

Use existing shared operational state components for loading, empty, error, forbidden and other states. Do not create a new state system just for products.

## 12. Caching and request lifecycle

Authenticated product data must remain private and session-scoped.

- Do not use `force-cache`, shared `unstable_cache` or another cross-user cache for authenticated product resources.
- Avoid stale data after create/edit mutations.
- Re-fetch or update state from the authoritative mutation response.
- Ensure request errors are not silently converted into empty product lists.
- Keep empty results distinct from network errors and permission failures.
- Prevent stale asynchronous responses from replacing a newer search/filter result.
- Do not poll product endpoints.
- Do not introduce realtime subscriptions.
- Do not make repeated N+1 requests for variant or image data if the approved list/detail resources already include those relationships.
- Do not add an aggregate endpoint or modify the frozen API contract.

## 13. Accessibility and responsive design

Use the canonical design system and follow `frontend/design-system/ACCESSIBILITY.md`.

### Forms

- Every input has a persistent label.
- Required and optional fields are communicated clearly.
- Errors are programmatically associated with their controls.
- Focus moves appropriately after a failed submission where supported by existing conventions.
- The form remains usable using only a keyboard.
- Disabled/loading states are announced appropriately.
- Destructive or state-changing controls have meaningful accessible names.

### Product list and detail

- Use semantic headings and landmarks.
- Provide accessible names for row actions.
- Ensure table headers are correctly associated with cells.
- Do not rely on color alone for state or type.
- Provide useful image alt text.
- Ensure product name, type, state and actions remain understandable at narrow widths.
- Preserve visible keyboard focus.
- Respect reduced-motion preferences.

### Responsive verification

Test at:

- 360 px.
- 390 px.
- 768 px.
- 1024 px.
- 1440 px.

Verify that:

- The list has no unintended horizontal page overflow.
- Search, filters, pagination and Create product remain usable.
- Forms reflow without clipped labels or buttons.
- Long product names, descriptions and identifiers wrap safely.
- Detail and variant sections remain readable.
- Dialogs fit small viewports and remain keyboard-accessible.
- The existing desktop sidebar/mobile drawer behavior remains unchanged.

Do not redesign the global shell or introduce a second breakpoint system.

## 14. Required automated tests

Use the project's existing testing framework.

### Authorization

1. Products remains protected by the existing route guard.
2. Missing or malformed permission data fails closed.
3. ADMIN does not bypass the required permission.
4. A user lacking the current catalog permission cannot load protected product content.
5. The page does not call product APIs before authorization succeeds.
6. The existing sign-out and admission behavior remains intact.

### Product list

7. Loading state renders correctly.
8. Empty results render as an empty state, not as an API error.
9. API failure is distinct from empty results.
10. Product rows use actual returned fields.
11. Pagination uses server metadata.
12. Search, filter and sort parameters match the approved contract.
13. Stale responses do not overwrite newer results.
14. No fabricated products or business metrics are rendered.
15. List data is not shared across authenticated users.

### Detail and variants

16. Detail data uses the authoritative returned resource.
17. Not-found, forbidden and unavailable states are distinct.
18. Existing variants display only when the API returns them.
19. Product-level and variant-level data remain distinct.
20. Unknown state values render safely.
21. No fabricated variant attributes or counts appear.
22. Variant creation is not presented as functional while CAT-010 returns `501`.

### Create form

23. Required fields and options match the approved contract.
24. Category options use API-returned identifiers.
25. Client validation does not replace Laravel validation.
26. `422` errors map to appropriate fields.
27. Submission prevents duplicate requests.
28. Successful navigation uses the returned product identifier.
29. Network/server failure does not display false success.
30. Made-to-order products remain a supported valid type where the API allows it.

### Edit form and states

31. Existing values are loaded from the API.
32. Edit cannot submit before the resource is loaded.
33. Only approved writable fields are submitted.
34. Unchanged values are not unintentionally overwritten.
35. State transitions follow the approved API rules.
36. Conflicts and validation failures preserve the user's work.
37. Successful edits refresh the authoritative state.
38. No unsupported state mutation endpoint is called.

### Security and architecture

39. No bearer token appears in HTML or Client Component props.
40. Authenticated requests use the existing private/no-store transport.
41. No unapproved endpoint, permission, enum or request field is introduced.
42. No public-only catalog resource is substituted for an operational resource.
43. No additional category, inventory or analytics workflow is created.
44. No unrelated API contract, database or customer frontend changes occur.

### Accessibility and responsive behavior

45. Form labels and validation errors are accessible.
46. Table actions have meaningful names.
47. Keyboard focus remains visible.
48. Responsive layouts work at the required widths.
49. No nested interactive elements or hydration regressions are introduced.
50. Reduced-motion behavior follows the established design system.

Use representative mocked responses only inside tests. Never present test fixtures as live application data.

## 15. Live verification

If the Laravel API and admin app are running, test the actual protected routes using an existing authorized development account.

Verify:

- Products list loads from the real operational endpoint.
- Pagination/filtering, where supported, reflects the server response.
- Product details load correctly.
- Create and edit succeed only when Laravel accepts the request.
- Invalid fields display the actual server validation response.
- Forbidden access remains denied.
- Variant details accurately reflect the actual resource.
- No request to a known `501` variant mutation endpoint occurs.
- The app remains functional after a failed request.
- No sensitive token or private response is exposed in the browser.
- The browser console and network log contain no unexpected errors or calls.

Do not invent product records in a production database. If a test product is needed, use an explicitly approved disposable test environment or an existing authorized test fixture.

If no authorized test product or safe disposable environment is available, do not mutate real catalog data just to claim a live pass. Report the missing verification honestly.

Do not create a staff identity manually or bypass Laravel admission to make a test pass.

## 16. Quality gates

Run from `frontend/admin` with the repository's established package manager:

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

Also run:

```bash
git diff --check
```

Run the relevant regression suites from Phases 19.3–19.5, especially:

- Admission and route guards.
- Navigation/permission filtering.
- Server/Client Component boundaries.
- Overview integrity.
- Shared operational states.

If backend code must be changed because the existing endpoint does not satisfy its approved contract, do not silently expand this frontend phase. Record the exact blocker and request owner approval for a separate backend/contract phase.

Do not report a command as passed unless it actually ran and passed.

## 17. Documentation and completion report

Update `frontend/admin/README.md` and/or `frontend/admin/AGENTS.md` only where needed to explain the real product feature architecture and its API/permission boundaries.

Create:

`phases/phase-19.6-completion.md`

Include:

1. Phase status: PASS / PARTIAL / FAIL.
2. Files changed.
3. Approved endpoints used and their verified contract shapes.
4. Product list, pagination, search and filters implemented.
5. Create/edit fields and exact validation behavior.
6. Product type and state handling.
7. Variant detail behavior and CAT-010 status.
8. Image upload status, if in scope.
9. Category-option source and any limitations.
10. Authorization and route-guard verification.
11. Loading, empty, error, forbidden and not-found behavior.
12. Accessibility and responsive test results.
13. Live browser/API verification performed.
14. Lint, typecheck, test, build and diff-check results.
15. Any contract/runtime discrepancies and unresolved blockers.
16. Explicit confirmation that no fake products or unsupported operations were introduced.
17. Handoff to Phase 19.7.

Do not rewrite prior completion reports or change the status of completed phases.

## 18. Explicit exclusions

Do not implement:

- Category CRUD or category state management.
- Inventory quantities or inventory adjustments.
- Request or enquiry management.
- Order, checkout, payment or delivery workflows.
- Customer or staff management.
- Audit-log workflows.
- Functional notification inbox or Reverb subscription.
- Dashboard analytics.
- Variant creation while CAT-010 is unavailable.
- Variant editing/deletion without an approved working endpoint.
- New API endpoints, permissions, roles or request/response fields.
- Database migrations.
- Changes to the frozen API contract.
- Frontend-only product-state transitions.
- Fake data presented as real.
- Unrelated dependency upgrades or global shell redesign.

## 19. Definition of Done

Phase 19.6 is **PASS** when:

1. The Products placeholder is replaced by a real Laravel-backed product-management module.
2. The operational product list and detail endpoints are verified against approved contracts.
3. Product creation and editing use only approved request fields.
4. Product types and lifecycle states reflect Laravel's authoritative values.
5. Validation errors and API failure states are handled correctly.
6. Variant details reflect actual API data, with unsupported mutations clearly unavailable.
7. Authorization remains Laravel-backed and fail-closed.
8. Authenticated data uses the established private/no-store transport.
9. The UI is accessible and responsive.
10. Automated tests and quality gates pass.
11. Live verification is accurately reported.
12. No fake products, unsupported API calls, new permissions or contract changes are introduced.
13. The completion report clearly records any unresolved backend limitations.

If product listing, category selection or another core operation is blocked by an unresolved contract issue, do not work around it with guessed fields or public data. Report **PARTIAL** with the exact blocker and leave the affected operation disabled.

**Implement Phase 19.6 now. Do not proceed to Phase 19.7 without owner approval.**