# SL Furnitures — Phase 19.7: Categories Management

**Group:** S — Admin Dashboard Frontend  
**Phase:** 19.7 — Categories Management  
**Application:** `frontend/admin`  
**Framework:** Existing Next.js App Router, TypeScript and MUI  
**Authentication:** Existing Clerk integration  
**Authorization:** Existing Laravel `/api/v1/me` admission boundary  
**Design authority:** `frontend/design-system/tokens.css`  
**Local origin:** `http://localhost:3001`  
**Status:** APPROVED FOR IMPLEMENTATION, SUBJECT TO API-CAPABILITY AUDIT

## 1. Objective

Implement the Categories Management area in the independent SL Furnitures Admin Dashboard.

The goal is to let an authorized operator inspect and maintain product categories through the existing Laravel API, using only verified endpoint contracts, request fields, response fields, validation rules and authorization behavior.

Potential capabilities include:

- Category listing.
- Category details where supported.
- Category creation.
- Category editing.
- Category hierarchy and parent-category display, if the API exposes them.
- Active/inactive state display and editing, if supported by the approved contract.
- Search, filtering and pagination only where supported by the actual endpoint.
- Correct loading, empty, validation, authorization and service-error states.
- Integration with the existing product form's category options where safe and supported.

**Important:** Do not assume every capability above is available. Establish the actual backend capabilities before implementing the UI. If a complete operational category listing is unavailable, do not disguise the limitation by presenting a partial public catalog as a complete administrative inventory.

## 2. Mandatory repository and API audit

Before editing, inspect the current repository and the actual code.

Read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/admin/AGENTS.md`.
- `frontend/admin/README.md`.
- `phases/group-S-phases.md`.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/phase-19.2-completion.md`.
- `phases/phase-19.3-completion.md`.
- `phases/phase-19.4-completion.md`.
- `phases/phase-19.5-completion.md`.
- `phases/phase-19.6-completion.md`.
- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- Laravel API routes, category controllers, FormRequests, resources, policies, middleware and tests.
- Laravel `PermissionName` and `PermissionCatalog`.
- Existing admin navigation registry, route paths, protected layouts, access helpers and shared page states.
- Existing `frontend/admin/lib/api/server-request.ts`.
- Existing `frontend/admin/lib/catalog/` modules and product/category-option helpers.
- Existing category-related tests.

Do not overwrite unrelated owner changes. In particular, inspect the current diff before touching `phases/group-S-phases.md`, `future.md` or root `AGENTS.md`. Preserve pre-existing edits and avoid modifying these files unless the phase explicitly requires it and the change is approved.

### Verify every category operation

The Phase 19.6 handoff reported these runtime endpoints:

- `GET /api/v1/categories`
- `GET /api/v1/categories/{category}`
- `POST /api/v1/categories`
- `PATCH /api/v1/categories/{category}`

The handoff also reported that writes use `products.manage`, and that the public listing returns active top-level categories only.

Treat these as audit leads, not a substitute for examining the current code and frozen API documentation.

For every endpoint, establish:

1. Whether it is registered and implemented.
2. Whether it appears in the frozen API contract and OpenAPI document.
3. Whether its response schema is documented and matches runtime behavior.
4. Whether its authorization middleware and policies match the documented contract.
5. Its exact request fields, required/optional rules and validation behavior.
6. Whether category identifiers are IDs, slugs or both.
7. Whether it returns inactive categories, nested categories or only active top-level categories.
8. Whether pagination, search or filtering is supported.
9. Whether create/update operations can modify hierarchy and category state.
10. Whether an operation has business-rule restrictions or is currently a stub.

Inspect the actual `StoreCategoryRequest`, `UpdateCategoryRequest`, category controller and resource implementations. Do not infer request fields from the product forms or database model alone.

Before implementation, provide a concise audit summary and plan. If an endpoint's authorization or response contract is materially ambiguous, stop that operation and report the precise blocker rather than inventing a workaround.

## 3. Non-negotiable API and data-integrity rules

- Do not create new Laravel endpoints.
- Do not change the frozen API contract or OpenAPI schema.
- Do not add permissions, roles, enums, migrations or database fields.
- Do not add an alternative category API or create a frontend-only catalog.
- Do not use hardcoded category IDs, slugs or names as operational data.
- Do not substitute mock categories or fabricated counts for API responses.
- Do not infer category state from missing fields.
- Do not silently omit inactive or nested categories and then claim the page represents all categories.
- Do not use the public customer catalog as an administrative source if its filtering prevents complete, truthful management.
- Do not call undocumented query parameters. If the endpoint rejects unknown parameters, preserve that behavior.
- Do not change backend authorization to make the frontend easier to implement.

If a complete operational listing is required for safe category administration but no suitable endpoint exists, implement only independently safe, documented functionality if doing so remains useful and truthful. Mark the phase **PARTIAL** when a missing capability materially blocks complete category management. Document the exact backend dependency for a future owner-approved change.

## 4. Authorization

Reuse the established Phase 19.3 server-side admission and permission architecture.

The Phase 19.6 report identifies `products.manage` as the existing verified permission for category writes and notes that no separate category permission is available in the current frontend navigation policy. Verify the current Laravel catalog and endpoint middleware before relying on that finding.

Requirements:

- Use the existing configured categories navigation entry and its approved permission requirement.
- Do not invent `categories.view`, `categories.manage` or another permission.
- Do not broaden access based on an ADMIN role.
- Do not treat hidden navigation as route authorization.
- Protect every category route with the existing server-side route guard.
- Recheck the exact required permission at the top of every Server Action.
- Deny direct requests when authorization fails.
- Do not retrieve category business data before the page's route authorization succeeds.
- Preserve the existing safe handling of expired sessions, `401`, `403`, throttling and service failures.
- Never expose Clerk bearer tokens or private `/me` data to client components.

If the actual Laravel permission mapping differs from the expected `products.manage` mapping, document the evidence and follow the established backend policy. Do not change the frontend permission requirement merely to make a test pass.

## 5. Category listing

Replace the existing `/categories` placeholder only to the extent that the available contract supports a truthful operational screen.

Reuse the established product-list architecture where appropriate:

- Server Component by default.
- Request-scoped authenticated transport.
- `private, no-store` behavior for authenticated responses.
- URL-based filters only when the API supports them.
- Server-provided pagination only when returned by the API.
- Shared page container and page header.
- Existing `EmptyState`, `ErrorState`, `LoadingState` and access-denial components.
- Accessible tables or a responsive list layout appropriate to the actual category fields.

Display only fields returned by the verified category resource, for example a name, slug, parent reference or state **only if those fields are actually present**.

### Incomplete-list safeguard

The known public endpoint may return only active top-level categories.

If that behavior remains true:

- Label the returned scope accurately.
- Do not call the result “all categories.”
- Do not infer that a missing category is deleted or nonexistent.
- Do not offer actions that imply the operator can manage categories absent from the listing.
- Do not invent a hidden administrative endpoint.
- Do not send undocumented filter parameters in an attempt to retrieve inactive or nested records.

Determine whether the detail endpoint can safely support editing an existing category without relying on an incomplete listing. Do not assume that it can discover every category.

If the current contract cannot support a meaningful, complete management list, record that limitation and mark the phase PARTIAL if it blocks the phase's core purpose.

## 6. Category creation

Implement a create screen only after verifying the exact approved request contract.

Requirements:

- Use only fields accepted by `StoreCategoryRequest`.
- Follow Laravel's actual required/optional rules and maximum lengths.
- Use existing design-system tokens and MUI form primitives.
- Use category options from a verified API response only if a parent-category selector is supported by the backend.
- Do not create a hierarchy selector if parent assignment is not an approved request field.
- Do not hardcode state choices unless they are verified contract enums or booleans.
- Do not fabricate preview data or a success state.
- Do not submit unknown fields.
- Preserve user input when validation or network requests fail.

For successful creation, require a successful API response and use the returned category identifier and resource for subsequent navigation or confirmation. Do not report success before Laravel confirms it.

If a create request is rejected by a business rule, present the actual safe server message and field-level errors where applicable.

## 7. Category editing

Implement editing only when the category can be reliably resolved through a supported endpoint.

Requirements:

- Load the current category from Laravel.
- Render no editable form until the category has been loaded successfully and authorization is confirmed.
- Use the verified category resource and update request.
- Respect the exact partial-update semantics of `UpdateCategoryRequest`.
- Send only allowed fields.
- Avoid sending unchanged fields when doing so is appropriate to the verified API contract.
- Prevent a no-op update from generating an unnecessary request.
- Preserve user input on `422`, `409`, throttling or service errors.
- Display field-level validation messages using accessible MUI error and helper-text behavior.
- Never optimistically claim a category update succeeded.
- Use the returned resource as the authoritative result.

Do not invent deletion, archive, activation or deactivation operations. Only expose a state mutation if the existing update endpoint explicitly supports it.

If a category is missing or inaccessible, distinguish the actual `404`/`403` result from a generic service failure.

## 8. Hierarchy and category state

Determine the supported category model from the current backend implementation and resource schema.

If parent-child relationships are supported, establish:

- How the parent is represented in the API.
- Whether the listing includes child categories.
- Whether a parent selector is supported on create/edit.
- Whether self-parenting or cyclic hierarchy is rejected by Laravel.
- Whether the API restricts eligible parents.
- How a missing or inaccessible parent is displayed.

Mirror server validation only as advisory client-side feedback. Laravel remains authoritative.

If category active/inactive state is exposed, show the actual returned state with an explicit label. If state mutation is supported, use the approved request fields and preserve server-side business rules.

Do not infer state from a category's presence or absence in the public catalog.

If hierarchy or state is not exposed by the current contract, do not manufacture controls or derive hidden state from the database schema.

## 9. Product form integration

Phase 19.6 uses `GET /api/v1/categories?per_page=100` for product-form category options and documented a limitation: the public endpoint may expose only active top-level categories, and a product's existing category may need to be preserved as an extra option during editing.

Audit this behavior and determine whether Phase 19.7 can improve it using an already-approved and sufficiently complete endpoint.

- If an approved operational category listing exists and returns the required categories, use it only after confirming its contract and authorization behavior.
- If no complete operational listing exists, retain the current safe behavior and document the limitation.
- Do not change the frozen contract to introduce an operational endpoint.
- Do not bypass API authorization.
- Do not remove a product's existing category from an edit form merely because it is absent from the current options response.
- Do not silently reassign a product to another category.
- Do not duplicate the category catalog in the frontend.
- Do not cause a product edit to fail merely because an unrelated category-list request is unavailable, unless category selection is essential to the operation being performed.

If the category endpoint's public visibility is unsuitable for authenticated administration, report the required backend contract extension for owner approval rather than using an unapproved alternative.

## 10. Navigation and route structure

Reuse the existing `categories` navigation destination.

Inspect the current route registry and match the established product route pattern where appropriate. Possible route shapes include:

- `/categories`
- `/categories/new`
- `/categories/[category]`
- `/categories/[category]/edit`

These are candidate routes, not permission to add unnecessary or unsupported pages.

For each route implemented:

1. Apply the existing server-side route access guard before business-data access.
2. Use the same approved categories route requirement as the navigation registry.
3. Provide a clear title and consistent page header.
4. Handle invalid or missing identifiers safely.
5. Avoid duplicate `main` landmarks.
6. Ensure nested routes are covered by the established route matcher or explicitly update it without weakening protection.
7. Keep placeholder routes protected and clearly labelled if a capability is deferred.

Do not add routes solely to make the module appear complete.

## 11. API transport and architecture

Reuse `frontend/admin/lib/api/server-request.ts` and the established catalog modules.

Follow the Phase 19.6 architecture:

- Server-side API access by default.
- Request-scoped tokens.
- No cross-user authenticated response caching.
- No bearer token in client props.
- No raw authorization payload passed to client components.
- Server Actions for mutations where consistent with the existing implementation.
- Server-side reauthorization inside every mutation action.
- Typed response parsing and defensive handling of malformed responses.
- Clear distinction between authorization failures, validation failures, conflict responses, rate limits, service errors and unusable response data.

Do not introduce a second API client, global token store or new state-management dependency.

Avoid a client-side fetch loop or requests on every keystroke. Use native GET forms and URL state if supported filters are implemented.

## 12. Error and empty states

Handle the actual responses safely.

| Situation | Required behavior |
|---|---|
| Loading | Reuse existing route loading behavior. |
| Empty result | Show a truthful empty state only when the API confirms a valid empty result. |
| Partial public catalog | Explain the returned scope; never imply completeness. |
| Malformed response | Show an error state, not an empty list. |
| `401` | Use the existing recoverable invalid-session state. |
| `403` | Use the existing access-denied state. |
| `404` | Show the established not-found behavior where appropriate. |
| `409` | Show a conflict state or the relevant server message. |
| `422` | Map returned field errors and preserve user input. |
| `429` | Use the existing rate-limit state and `Retry-After` where available. |
| `5xx` or network failure | Show the established service-unavailable state. |

Never expose stack traces, raw exception messages or sensitive API response data to users.

## 13. Accessibility and responsive design

Follow:

- `frontend/design-system/ACCESSIBILITY.md`
- `frontend/design-system/COMPONENTS.md`
- Existing admin MUI primitives and canonical design tokens.

Requirements:

- One main heading per page.
- Logical heading hierarchy.
- Persistent labels for every form control.
- Accessible required-field and validation messaging.
- Keyboard-operable links, menus, forms and actions.
- Visible focus indicators.
- Table header semantics where tables are used.
- State conveyed through text as well as color.
- Long category names and slugs wrap without breaking layout.
- Responsive behavior without global horizontal overflow.
- Clear accessible names for icon-only controls.
- Confirmation before any destructive action, but do not implement destructive actions unless supported and approved.

Verify at minimum 360, 390, 768, 1024 and 1440 pixel viewport widths. Record whether live browser verification was performed or whether verification is limited to automated tests/build checks.

## 14. Automated tests

Add `frontend/admin/tests/categories.test.tsx` or the project's equivalent, and register it in the existing `npm run test` script.

Cover, where the corresponding feature is implemented:

### Authorization and routing

1. Every implemented category route repeats the correct server-side access guard.
2. Authorization happens before category business-data access.
3. Every Server Action reauthorizes the caller.
4. Unauthorized users cannot mutate categories by calling an action directly.
5. No ADMIN-role bypass is introduced.
6. The configured permission is a real approved `PermissionName`.
7. Nested category routes remain protected.

### API contract and data handling

8. Only approved category endpoints and query parameters are used.
9. Response parsers accept valid responses and reject malformed ones.
10. No fabricated categories, category IDs, hierarchy or state are rendered.
11. Partial listing scope is communicated accurately.
12. Pagination and filtering are used only if the endpoint actually supports them.
13. Request payloads contain only approved fields.
14. API responses remain authoritative after mutations.
15. No bearer token or private authorization data crosses into client components.
16. Authenticated responses remain request-scoped and `no-store`.

### Create and edit

17. Required fields and validation match the verified FormRequests.
18. Successful creation uses the returned resource.
19. Successful update uses the returned resource.
20. `422` field errors are mapped accessibly.
21. `409` and service failures are handled without false success.
22. User input is preserved after failed submission.
23. No-op updates do not send unnecessary requests where supported by the implementation.
24. No unsupported delete/state/parent mutation is offered.

### Product form integration

25. Existing product category values are preserved safely.
26. Category options use real returned identifiers only.
27. No undocumented endpoint or query parameter is used.
28. Category-list failure does not silently reassign a product.

### Rendering and states

29. Empty results differ from API errors.
30. Not-found and forbidden states are distinct.
31. Loading behavior uses the existing route architecture.
32. Forms and tables remain accessible at narrow widths.
33. No second `main` landmark is introduced.

Tests must reflect actual functionality. Do not add tests that merely encode invented contract behavior.

## 15. Quality gates

Run the established checks from `frontend/admin`:

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

If the repository uses a different package manager, follow the existing lockfile and project instructions.

Run relevant navigation, authorization and product regression tests. Do not modify unrelated owner edits to obtain a clean unfiltered diff.

When safe test infrastructure is available, verify the category endpoints against a disposable or approved environment. Do not mutate real production or owner data just to claim a live test.

Do not claim authenticated browser verification unless it actually occurred with an approved test identity.

## 16. Documentation and completion report

Update `frontend/admin/README.md` and `frontend/admin/AGENTS.md` only as needed to document:

- Category module structure.
- Exact endpoint usage.
- Verified permission mapping.
- Supported fields and state behavior.
- Category-list scope and any known completeness limitations.
- Route guards and Server Action authorization.
- Product-form category-option integration.
- Test commands.

Create:

`phases/phase-19.7-completion.md`

Include:

1. Final status: PASS, PARTIAL or FAIL.
2. Files created and modified.
3. Endpoint and API contract audit.
4. Exact permission mapping.
5. List/detail/create/edit capabilities actually implemented.
6. Verified request and response fields.
7. Category hierarchy and state behavior.
8. Product-form integration changes, if any.
9. Loading, empty, validation, conflict, forbidden and error behavior.
10. Accessibility and responsive verification.
11. Automated test results.
12. Lint, typecheck, test, build and diff-check results.
13. Live verification performed and not performed.
14. Known backend dependencies and discrepancies.
15. Recommended next-phase handoff.

Do not rewrite completed phase reports or silently edit frozen API documents.

## 17. Explicit exclusions

Do not implement:

- New Laravel endpoints or API contract changes.
- New permissions or roles.
- Category deletion unless an approved endpoint already exists and the roadmap explicitly authorizes it.
- Category media upload or image management.
- Cloudflare R2 integration.
- Product media management, which is deferred to Phase 19.8.
- Inventory management, deferred to its approved phase.
- Product variant creation, which remains backend-dependent.
- Request or enquiry management.
- Orders, checkout, payments or delivery workflows.
- Analytics or fabricated category/product counts.
- A second category data store.
- Unrelated customer-site or Flutter changes.
- Production deployment work.

## 18. Definition of Done

Phase 19.7 is **PASS** only when:

1. The category module uses existing, verified API contracts.
2. Every implemented route and mutation preserves the established authorization boundary.
3. The UI exposes only verified category fields and operations.
4. Create/edit validation matches Laravel's FormRequests.
5. Category hierarchy and active/inactive state are represented accurately wherever supported.
6. Product category selection remains safe and does not silently reassign existing products.
7. An incomplete public category listing is never presented as a complete operational catalog.
8. Unsupported operations are not represented as working controls.
9. Tests and quality gates pass.
10. Documentation and the completion report are accurate.

If the lack of a complete operational listing prevents meaningful category administration, do not invent an endpoint or change the frozen contract. Report **PARTIAL**, explain exactly what is blocked, identify the required backend capability, and wait for owner approval before proposing contract work.

**Implement Phase 19.7 only. Do not advance to Phase 19.8 without owner approval.**