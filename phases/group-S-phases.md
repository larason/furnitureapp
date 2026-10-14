# SL Furnitures — Phase 19.1: Admin Architecture & API Contract Audit

## Owner-approved architecture correction

The earlier proposal to implement the admin dashboard under `frontend/web/admin` or the customer website's `/admin` URL has been withdrawn.

**The admin dashboard must be a separate Next.js application with its own domain/subdomain.**

Approved architectural direction:

- Customer website: `frontend/web`.
- Admin dashboard: separate frontend application; inspect existing repository architecture to determine the appropriate directory, with `frontend/admin` as the preferred location if no other path is already approved.
- Shared backend: `backend/laravel`.
- Shared identity provider: existing Clerk application.
- Shared authorization authority: Laravel RBAC.
- Separate customer and admin routing, layouts, builds, environment configuration, and deployments.
- Next.js + React + TypeScript + MUI for the admin dashboard.

A production admin subdomain such as `admin.slfurnitures.com` is illustrative until the owner confirms the actual domain.

Do not implement the admin dashboard inside the existing customer Next.js application.

## Phase 19.1 objective

Perform a comprehensive architecture and API contract audit before implementing the separate admin dashboard.

### 1. Audit project instructions and architecture

Inspect:

- Root `AGENTS.md`.
- `README.md`.
- `ROUTING.md`.
- `DESIGN.md`.
- `tokens.css`.
- `COMPONENTS.md`.
- `USAGE.md`.
- `ACCESSIBILITY.md`.
- `production-caveats.md`.
- Existing frontend architecture.
- Group K Laravel admin operations.
- Group R notifications and Phase 18.6 completion.
- Phase 18.7 policy closure.
- Frozen Laravel API documentation and OpenAPI specification.
- Existing Clerk integration and local ADMIN bootstrap mechanism.

Confirm whether the repository already prescribes a location for the separate admin frontend.

Do not invent another directory if an authoritative location already exists.

### 2. Audit Laravel operational API coverage

Produce a verified matrix of existing endpoints needed by the admin dashboard, including:

- Operational dashboard data.
- Products and variants.
- Categories.
- Inventory, where applicable.
- Made-to-order requests.
- General enquiries.
- Operational notifications.
- Staff management.
- Admin-only operations.
- Authentication and current-user resolution.

For each operation, record:

- Actual method and path.
- Contract operation ID.
- Required permission.
- Request/response schema.
- Pagination, filtering and sorting.
- Error handling.
- Whether the endpoint is implemented, documented only, or missing.
- Whether it is usable within the current REQUEST ONLY production scope.

Do not implement new endpoints during this phase.

Report any genuine backend/API gaps for owner approval.

### 3. Design the separate frontend architecture

Propose:

- New Next.js app directory and package scripts.
- Development port distinct from the customer website.
- Deployment boundary and environment variables.
- Shared design-token strategy.
- MUI theme reuse without duplicating competing token systems.
- Clerk provider configuration.
- Typed Laravel API client.
- Server-side and client-side authorization boundaries.
- Operational navigation and layouts.
- Error/loading/empty states.
- Testing strategy.
- Accessibility and responsive behavior.

Do not build pages yet.

### 4. Authentication and authorization

Clerk remains the identity provider.

Laravel remains authoritative for:

- STAFF/ADMIN roles.
- Active account status.
- Exact permissions.
- Operational resource access.

A Clerk-authenticated CUSTOMER must not be admitted to the dashboard.

The proposed design must define safe behavior for:

- Signed-out users.
- Signed-in CUSTOMER users.
- STAFF users.
- ADMIN users.
- Disabled accounts.
- Expired sessions.
- Laravel API outages.
- Missing permission for a specific operational section.

Reuse the existing Clerk instance rather than introducing a second independent identity system without approval.

Do not create public admin registration or role-elevation functionality.

### 5. Separate-domain considerations

Audit the intended deployment and security implications of the separate admin origin.

Specifically address:

- Clerk production domain and allowed origins.
- Clerk token acquisition and refresh.
- Laravel allowed authorized parties.
- Laravel CORS configuration.
- Browser requests from the admin subdomain to the API domain.
- Reverb private-channel authorization.
- Production WebSocket/WSS requirements.
- Credential handling.
- CSP and HTTPS.
- Independent customer/admin deployment configuration.

Do not relax JWT validation or use wildcard CORS merely to make the dashboard work.

### 6. Notification handoff

The dashboard should eventually consume:

- NOT-001 for recipient-scoped inbox retrieval.
- NOT-002 for read/unread mutations.
- Existing `private-notifications.{localUserId}` Reverb channels.
- Existing `notification.state-changed` events.

Operational STAFF/ADMIN notifications are mandatory.

Do not add notification preference controls.

Customer push preferences remain deferred.

No new notification events or backend notification endpoints are authorized.

### 7. Dashboard information architecture

Propose the initial admin navigation based on actual Laravel API capabilities.

Prioritize REQUEST ONLY operations:

- Overview.
- Made-to-order requests.
- Enquiries.
- Products and catalog management.
- Categories and related catalog operations.
- Staff administration, for authorized ADMIN users.
- Operational notification inbox.
- Account/profile and logout.

Do not include working cart, checkout, payment, delivery or order-management modules unless already approved and backed by implemented APIs.

A future-phase placeholder may be documented but should not imply that the feature is operational.

### 8. Documentation and roadmap

Reconcile Group S with the existing project roadmap.

Preserve any previously documented QA phases by explicitly relocating or renumbering them with cross-reference updates.

Record the separate-domain admin dashboard architecture in the appropriate project decision document.

Do not rewrite historical completion evidence.

### 9. Deliverables

Produce:

1. Phase 19.1 architecture audit.
2. Actual repository/frontend layout findings.
3. Verified operational API coverage matrix.
4. Permission-to-screen mapping.
5. Proposed separate Next.js app structure.
6. Clerk authentication and Laravel authorization strategy.
7. Shared design system strategy.
8. Separate-origin security and deployment considerations.
9. Dashboard navigation and screen inventory.
10. API gaps and risks.
11. Proposed Phase 19.2 onward implementation sequence.
12. Explicit owner decisions required before scaffolding.

Save the audit to the project's appropriate `phases/` documentation location.

### 10. Boundaries

Do not:

- Create the admin application yet.
- Build dashboard screens.
- Modify the frozen API contract.
- Introduce new Laravel endpoints.
- Modify staff role permissions.
- Introduce a separate auth provider.
- Change FCM, Resend or Reverb policies.
- Deploy to Coolify.
- Modify the customer website routing to host admin pages.

### 11. Completion criteria

Phase 19.1 is PASS when the architecture and API audit is complete, source-backed, consistent with the actual repository, and all implementation-blocking questions are identified.

The output should make Phase 19.2 — Admin Application Foundation straightforward to implement without inventing API contracts or security assumptions.

**Start with the audit. Do not scaffold the admin app until Phase 19.1 has been reviewed and approved.**