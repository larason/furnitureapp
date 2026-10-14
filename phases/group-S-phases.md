# SL Furnitures — Phase 19.3: Clerk Authentication & Laravel Admin Authorization

**Group:** S — Admin Dashboard Frontend  
**Phase:** 19.3 — Clerk Authentication, Session Integration & STAFF/ADMIN Admission  
**Application:** `frontend/admin`  
**Framework:** Next.js 16.3.8, React 19.2.8, MUI 9.4.0  
**Identity:** Clerk Next.js SDK 7.9.11  
**Backend:** Existing Laravel `/api/v1`  
**Development origin:** `http://localhost:3001`  
**Status:** APPROVED FOR IMPLEMENTATION

## 1. Objective

Implement the secure authentication and authorization foundation for the independent SL Furnitures Admin Dashboard.

The dashboard must:

1. Authenticate users through the existing Clerk application.
2. Obtain valid, current Clerk session tokens.
3. Resolve the authenticated local Laravel identity through `GET /api/v1/me`.
4. Verify local account activation and STAFF/ADMIN eligibility.
5. Enforce server-side admission before protected operational content is rendered or fetched.
6. Preserve exact Laravel permission boundaries.
7. Reject CUSTOMER identities.
8. Reject inactive, suspended, unauthorized or unresolvable accounts.
9. Support safe sign-in, sign-out, session restoration and expiration.
10. Prepare a reusable authenticated identity context for Phase 19.4 and later operational screens.

Do not implement actual request, enquiry, catalog, inventory or notification screens.

This phase establishes the security foundation for those later features.

## 2. Mandatory audit

Before coding, inspect:

- Root `AGENTS.md`.
- `frontend/admin/AGENTS.md`.
- `frontend/admin/README.md`.
- `frontend/admin/app/`.
- `frontend/admin/components/providers/`.
- `frontend/admin/lib/api/`.
- `frontend/admin/lib/config/`.
- `frontend/admin/theme/`.
- `frontend/admin/.env.example`.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/phase-19.2-completion.md`.
- Existing Clerk integration in `frontend/web`.
- Existing Laravel Clerk authentication middleware.
- Existing `LocalUserProvisioner`.
- `GET /api/v1/me` controller and resource.
- Existing STAFF/ADMIN RBAC policies.
- `PermissionCatalog`.
- Clerk authorized-party configuration.
- Relevant API and OpenAPI contracts.
- Existing auth-related frontend and backend tests.

Verify the exact Clerk 7.9.11 APIs and Next.js 16 integration requirements before implementing authentication.

Reuse the existing typed admin API transport.

Do not introduce a second API client or authentication provider.

## 3. Architectural principles

Enforce these boundaries.

### Clerk

Clerk is responsible for:

- Authentication.
- Sign-in.
- Session issuance.
- Session restoration.
- Session expiration.
- Sign-out.
- Identity verification.

### Laravel

Laravel is responsible for:

- Local user projection.
- Account activation state.
- STAFF/ADMIN role determination.
- Permission assignment.
- Business authorization.
- Resource ownership.
- Operational API authorization.

### Next.js admin

Next.js is responsible for:

- Acquiring the current Clerk token.
- Calling Laravel securely.
- Resolving current admin identity.
- Enforcing an application-level admission boundary.
- Rendering appropriate authentication and authorization states.
- Providing role/permission information to presentation components.
- Preventing protected data leaks through rendering or caching.

Never treat authentication as authorization.

## 4. Sign-in architecture

Implement the appropriate Clerk sign-in flow for the separate admin origin.

Requirements:

- Reuse the existing Clerk instance.
- Support sign-in through Clerk's approved SDK components or APIs.
- Do not build custom password authentication.
- Do not create a public STAFF/ADMIN registration flow.
- Do not create role-selection controls.
- Do not introduce local Laravel credential sessions.
- Do not create a separate admin identity database.
- Preserve Clerk's existing verification and security requirements.

The sign-in experience should follow the SL Furnitures MUI design system where customization is supported by the selected Clerk integration.

Do not use fake ADMIN identities for demonstration.

### Sign-in route

Choose an appropriate route such as:

`/sign-in`

Confirm its compatibility with the installed Clerk SDK and existing routing configuration.

Allow only validated, same-origin return destinations.

Prevent external open redirects through untrusted redirect parameters.

After successful Clerk authentication, the user must still pass Laravel admission before entering any protected operational route.

## 5. Server-side admission boundary

Implement a reusable server-side admin admission function.

An illustrative name is:

`requireOperationalIdentity()`

Follow existing naming and module conventions.

The function should:

1. Obtain the current Clerk authentication context using the supported server SDK.
2. Reject missing or invalid authentication.
3. Obtain a fresh/current Clerk session bearer token.
4. Call `GET /api/v1/me` through the existing request-scoped Laravel API client.
5. Validate the response against the actual frozen user resource contract.
6. Verify that the Laravel local account is ACTIVE.
7. Verify that the authoritative local role is STAFF or ADMIN.
8. Preserve the Laravel-returned exact permissions.
9. Return a minimal, typed, authorized operational identity.
10. Fail closed on invalid, missing or ambiguous identity information.

Do not decide authorization solely from:

- Clerk `userId`.
- Clerk metadata.
- Clerk organization role.
- Email domain.
- Frontend local storage.
- Client role flags.
- Route parameters.
- A decoded but unverified JWT payload.

Laravel `/me` must remain authoritative.

### JIT provisioning

The existing Laravel authentication pipeline may perform JIT local-user provisioning.

Do not independently create STAFF or ADMIN users inside Next.js.

If a new Clerk identity causes Laravel to provision a local CUSTOMER, that user is still unauthorized for the admin dashboard.

Do not automatically promote CUSTOMER users.

An existing verified STAFF/ADMIN identity may be admitted only if Laravel confirms the active local role.

If provisioning is unavailable or rejected, deny admission.

## 6. Protected route architecture

Prepare a protected App Router route boundary for later dashboard pages.

An appropriate conceptual structure is:

```text
frontend/admin/app/
  layout.tsx
  (auth)/
    sign-in/
      page.tsx
  (protected)/
    layout.tsx
    page.tsx
```

This is illustrative.

Route groups do not appear in the URL.

Choose actual files according to the current scaffold and avoid duplicate `/` routes.

### Protected layout

The protected route boundary must resolve and validate the Laravel identity before rendering protected children or initiating operational reads.

However, do not rely only on a parent layout for every later authorization decision.

Next.js layouts may be reused across client-side navigation. Future protected data access must independently enforce authentication and appropriate permission checks at the relevant server/API boundary.

Client-side permission state is for presentation, not security.

### Next.js proxy

If the installed Next.js 16 / Clerk integration requires `proxy.ts`, use the supported convention.

Proxy-level authentication may redirect clearly signed-out users.

It must not become the sole authorization boundary.

Do not duplicate authoritative role logic inside proxy code.

## 7. Role admission policy

Implement these exact rules.

| Laravel state | Admin admission |
|---|---|
| ACTIVE ADMIN | Allow |
| ACTIVE STAFF | Allow |
| ACTIVE CUSTOMER | Deny |
| INACTIVE ADMIN | Deny |
| INACTIVE STAFF | Deny |
| Suspended user | Deny |
| Missing local role | Deny |
| Ambiguous role state | Deny |
| Invalid Clerk session | Deny |
| Expired/unavailable session | Deny |
| Laravel identity cannot be resolved | Fail closed |

Check the actual Laravel account-state enum and role model instead of assuming field names.

### Multi-role handling

The existing project defines an authoritative local role model.

Do not invent precedence rules for ambiguous combinations.

If the Laravel response can contain multiple roles, follow the approved backend rules or stop and identify the contract ambiguity.

Do not elevate privilege based on whichever role seems highest.

## 8. Exact permission handling

Phase 19.3 must establish permission-aware foundations for later screens.

Create a narrowly scoped permission helper, such as:

`hasPermission(identity, permission)`

It should operate only on permissions resolved from the current Laravel identity.

Do not maintain an independent frontend permission catalog that can drift from Laravel.

Use existing server permission identifiers.

Examples:

- `requests.view`
- `requests.manage`
- `enquiries.view`
- `enquiries.manage`
- `products.view`
- `products.manage`
- `inventory.view`
- `inventory.manage`
- `notifications.read_operational`
- `staff.manage`
- `staff.approve`
- `users.manage_authorized`
- `audit.view`

These examples must be checked against the actual current `PermissionCatalog`.

Do not invent new permission strings.

### Permission gate

Provide a reusable server-side permission guard for later operational routes.

Examples of behavior:

- STAFF with `requests.view` may access permitted request data.
- STAFF without `requests.manage` cannot perform request mutations.
- ADMIN must still satisfy any required exact permissions.
- Customer directory requires ADMIN plus its approved permission.
- Staff administration requires ADMIN plus the matching permission.

Do not implement business resource screens now.

Do not grant permissions merely because a navigation item is visible.

Laravel must independently enforce authorization on each API operation.

## 9. Authenticated identity representation

Create a minimal, typed representation of the Laravel-resolved operational identity.

Use the actual `/me` response schema.

Consider including only fields needed by later admin interfaces:

- Opaque local user identifier.
- Display name or approved profile fields.
- Authoritative role.
- Account state, if required.
- Exact permissions.
- Approved account/avatar information if present.

Do not serialize the Clerk bearer token.

Do not expose confidential Laravel internal fields.

Do not fabricate user DTO attributes.

Avoid retaining a complete raw Laravel response when a narrower identity shape is sufficient.

If the app needs a client-side identity context, pass only sanitized presentation fields and permission names.

Do not include bearer tokens, provider secrets or other credentials in React props.

## 10. Authenticated API integration

Reuse:

`frontend/admin/lib/api/client.ts`

from Phase 19.2.

Preserve:

- Frozen `/api/v1` URL behavior.
- Request-scoped bearer tokens.
- Success envelope parsing.
- Error classification.
- Pagination handling.
- Network timeout handling.
- Private/no-store request semantics.
- Sanitized errors.
- No credential logging.

### Server-side use

Use current-request authentication context.

Never place a bearer token in module-global state.

Never share authorized API clients across users.

Do not cache `/me` or operational data across sessions.

Ensure all protected reads use dynamic request context and appropriate `no-store` behavior.

### Client-side future use

Prepare an appropriately scoped way for interactive components to obtain the current Clerk token immediately before a protected request.

Do not pass server-acquired Clerk tokens to client components.

Do not rely on a single token captured at initial login.

Clerk session tokens are short-lived and must be refreshed through supported SDK mechanisms.

Avoid adding a speculative browser API framework before interactive screens require it.

## 11. Sign-out and account switching

Provide a safe logout flow.

Requirements:

- Use Clerk's supported sign-out mechanism.
- Reset authenticated admin presentation state.
- Invalidate any application-owned identity cache.
- Redirect to the sign-in or safe signed-out page.
- Prevent stale data from a previous user appearing after logout.
- Ensure protected server requests cannot reuse an old bearer token.

### Account switching

If Customer A signs in after ADMIN B:

- The previous ADMIN's role and permissions must not persist.
- The new Clerk session must trigger independent Laravel `/me` resolution.
- CUSTOMER A must be denied admin admission.
- Stale async results must not update current account state.
- No prior account's operational data may remain in a shared cache.

Avoid cross-user React Query/SWR caches unless an approved cache-isolation strategy exists.

Do not add global state libraries merely for auth.

## 12. Error and denial states

Use the shared Phase 19.2 MUI primitives.

Provide distinct states for:

### Signed out

Show the approved sign-in experience or safe redirect.

### CUSTOMER account

Display a clear access-denied message.

Do not suggest creating another account or self-promoting to STAFF.

Offer a safe sign-out action.

### Inactive or suspended account

Display a generic account-unavailable message.

Do not expose internal enforcement details.

### Missing permission

Display a permission-denied state for that section.

Avoid displaying forbidden business data.

### Laravel unavailable

Show a recoverable service-unavailable state.

Do not show protected content based on stale role information.

### Invalid/expired Clerk session

Use the Clerk session lifecycle to recover or return the user to sign-in.

Do not create endless redirect loops.

### Configuration missing

Preserve the existing safe configuration state from Phase 19.2.

Do not render a fake authenticated admin preview.

### Forbidden versus unavailable

Use actual API status and error semantics where available.

Do not misrepresent a `403` as a network outage or use a `500` fallback to admit the user.

## 13. Styling and design consistency

Use the existing shared design system.

Canonical source:

`frontend/design-system/tokens.css`

Use the existing admin MUI theme from Phase 19.2.

Do not create:

- A second palette.
- Unapproved typography.
- Hardcoded arbitrary brand colors.
- A new component library.
- A full dashboard sidebar prematurely.

Create a polished but minimal sign-in and authorization-state presentation.

Use existing:

- PageContainer.
- PageHeader.
- ErrorState.
- LoadingState.
- MUI Buttons.
- Typography.
- Paper/Card components.
- Approved icons.

Avoid generic AI-generated dashboards with fabricated KPIs.

## 14. Local authentication configuration

The admin site runs at:

`http://localhost:3001`

Verify the existing Clerk application is configured for this origin.

Audit:

- Admin Clerk publishable key.
- Clerk frontend/redirect configuration.
- Laravel `CLERK_AUTHORIZED_PARTIES`.
- Laravel CORS allowed origins if browser API calls are used.
- Local Laravel API origin.
- Next.js server API connectivity.

Use the same Clerk instance as the customer application.

Do not disable authorized-party verification.

Do not change JWT issuer, audience or signature validation.

Do not use wildcard CORS.

### Browser token handling

Do not require the owner to repeatedly copy short-lived Clerk JWTs into terminal commands.

For browser-driven smoke tests, obtain a fresh token through the live Clerk session immediately before each API call.

Do not extend token TTL or persist raw tokens.

If operator action is needed to sign in, ask for it explicitly.

## 15. Environment variables

Update `.env.example` only as required.

Distinguish:

- Public Clerk configuration.
- Server-only Laravel API origin.
- Approved admin canonical origin.
- Optional future browser API configuration.

Do not place Clerk secret keys or API secrets in `NEXT_PUBLIC_*` variables.

Do not commit `.env.local`.

Do not hardcode production domains.

Keep customer/admin deployment configurations isolated.

## 16. Authentication security tests

Add meaningful tests covering:

### Clerk and session

1. Signed-out user cannot access protected operational content.
2. Valid Clerk session triggers Laravel identity resolution.
3. Missing or invalid bearer token is rejected.
4. Expired session is not admitted.
5. Incomplete Clerk verification cannot bypass Laravel.
6. Session restoration resolves identity correctly.
7. Sign-out clears authenticated presentation state.
8. Account switching does not leak previous identity.
9. Repeated navigation does not create uncontrolled auth requests.
10. Missing configuration fails safely.

### Laravel identity

11. ACTIVE ADMIN is admitted.
12. ACTIVE STAFF is admitted.
13. ACTIVE CUSTOMER is denied.
14. INACTIVE ADMIN is denied.
15. INACTIVE STAFF is denied.
16. Missing local identity is not granted privileged access.
17. JIT-provisioned CUSTOMER is denied.
18. Missing local role fails closed.
19. Ambiguous identity response fails closed.
20. Laravel outage produces a safe unavailable state.
21. Backend 401 and 403 are handled distinctly.
22. Server failures do not result in default admission.

### Permissions

23. Exact permission check succeeds when present.
24. Missing permission denies protected operation.
25. ADMIN role alone does not bypass exact permissions.
26. STAFF cannot enter ADMIN-only sections.
27. Client-provided role or permission fields are ignored.
28. Laravel permission changes are reflected on the next authoritative check.
29. Unauthorized deep links do not reveal operational data.

### Security and transport

30. Private `/me` response is not shared-cacheable.
31. Bearer tokens are not serialized to HTML.
32. Bearer tokens are not logged.
33. Concurrent requests use the correct user's token.
34. Old account data is not reused after sign-out.
35. Open redirect parameters cannot redirect outside approved destinations.
36. No public admin signup route exists.
37. No fake/admin fallback identity exists.

Use deterministic mocks for ordinary unit and component tests.

Do not weaken security to simplify tests.

## 17. Controlled integration smoke test

Once automated tests pass, perform a controlled real local test.

Use the existing verified local ADMIN identity from the Group K bootstrap and Phase 18.6 testing.

Do not create another ADMIN automatically.

### Test A — ADMIN

1. Start Laravel.
2. Start `frontend/admin` on port 3001.
3. Sign in using the real verified ADMIN Clerk identity.
4. Confirm a fresh Clerk session token is acquired.
5. Confirm authenticated `/api/v1/me` returns the existing ADMIN.
6. Confirm account state is active.
7. Confirm exact permissions are resolved.
8. Confirm protected admin admission succeeds.
9. Confirm no sensitive token appears in browser output.

### Test B — CUSTOMER

1. Sign out ADMIN.
2. Sign in as a verified CUSTOMER.
3. Confirm Clerk authentication succeeds.
4. Confirm Laravel identifies the local role as CUSTOMER.
5. Confirm admin access is denied.
6. Confirm no operational data is fetched.

### Test C — STAFF

If an approved active STAFF test identity is available:

1. Sign in as STAFF.
2. Confirm active role and exact permissions.
3. Confirm admin admission succeeds.
4. Confirm ADMIN-only authorization still rejects STAFF.

Do not invent a privileged identity solely to claim a live PASS.

### Test D — Session transition

1. Sign out.
2. Confirm protected admission is revoked.
3. Refresh the protected page.
4. Confirm no previous account data appears.
5. Sign in again and verify independent identity resolution.

### Test E — Backend unavailable

Stop or isolate the local Laravel service safely.

Verify the admin dashboard fails closed and displays the expected unavailable state rather than rendering cached operational content.

Do not modify production services.

### Reporting

Clearly distinguish:

- Automated tests.
- Real Clerk session tests.
- Real Laravel API responses.
- Tests not executed due to unavailable credentials or configuration.

Do not mark unexecuted checks as successful.

## 18. Quality gates

Run from `frontend/admin`:

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

Run relevant Laravel authentication tests if backend behavior is audited or changed.

If backend files are changed, run the applicable Laravel test suite, PHPStan and Pint.

Verify:

- No hydration errors.
- No protected data leakage.
- No redirect loops.
- No unauthorized role elevation.
- No regressions in the existing MUI foundation.

Do not force-upgrade dependencies as part of this phase.

The Phase 19.2 report documented five development/transitive dependency audit findings. Keep them visible as a separate dependency follow-up rather than obscuring them with unrelated authentication changes.

## 19. Documentation

Update:

- `frontend/admin/README.md`.
- `frontend/admin/AGENTS.md`, if new security rules are needed.
- Relevant environment documentation.

Create:

`phases/phase-19.3-completion.md`

Document:

- Clerk integration architecture.
- Protected route boundary.
- Server-side Laravel `/me` admission.
- Local role validation.
- Exact permission handling.
- Authentication error states.
- Logout/account-switch behavior.
- Token handling and refresh.
- Test evidence.
- Local smoke-test results.
- Remaining security limitations.
- Phase 19.4 handoff.

Do not rewrite previous completion reports.

## 20. Explicit exclusions

Do not implement:

- Phase 19.4 full dashboard shell.
- Sidebar/topbar/navigation.
- Real request/enquiry views.
- Catalog or inventory workflows.
- Staff management.
- Customer directory.
- Audit log UI.
- Notification inbox.
- Reverb subscription.
- Notification preferences.
- Customer FCM push.
- New backend permissions.
- New Laravel endpoints.
- New admin registration mechanisms.
- Commerce/order/payment functionality.
- Production Coolify deployment.

## 21. Definition of Done

Phase 19.3 is PASS when:

1. Clerk authentication works in the independent admin app.
2. Laravel `/me` determines STAFF/ADMIN admission.
3. ACTIVE STAFF and ADMIN users are admitted.
4. CUSTOMER users are rejected.
5. Inactive/suspended users are rejected.
6. Missing/invalid identity fails closed.
7. Exact permissions remain Laravel-authoritative.
8. Protected routes resolve authorization on the server.
9. Session expiration and logout are safe.
10. Account switching does not expose stale data.
11. No bearer token is persisted, serialized or leaked.
12. Public admin signup/role elevation is impossible.
13. Automated tests pass.
14. Local Clerk/Laravel smoke tests confirm the core admission path.
15. Documentation is updated.
16. No business API contract is changed.

If real Clerk credentials or local infrastructure are unavailable, report **PARTIAL** rather than claiming a live PASS.

## 22. Final report

Return:

**PHASE 19.3 — PASS / PARTIAL / FAIL**

Include:

- Files changed.
- Authentication routes.
- Protected route implementation.
- Clerk SDK integration.
- Laravel `/me` identity resolution.
- Role admission behavior.
- Exact permission handling.
- Token lifecycle security.
- Sign-out behavior.
- Unauthorized and unavailable states.
- Test results.
- Real local smoke-test evidence.
- Remaining limitations.
- Readiness for Phase 19.4.

**Implement Phase 19.3 now. Do not proceed to Phase 19.4 without owner approval.**