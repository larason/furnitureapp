# Phase 19.1 — Admin Architecture & API Contract Audit

## Status

**PASS — audit complete; implementation is blocked only on the owner decisions listed at the end.**

This is a documentation-only phase. No admin application, route, API endpoint, permission, migration, notification event, or deployment was added.

## 1. Audit scope and sources

The audit checked the repository instructions, frontend structure, design-system authorities, Laravel routes/controllers/configuration, the frozen API contract and OpenAPI document, Group K, and the Group R completion records.

Primary sources:

- `AGENTS.md` and `README.md`.
- `frontend/AGENTS.md`, `frontend/web/AGENTS.md`, `frontend/web/README.md`, and `frontend/web/ROUTING.md`.
- `frontend/design-system/{DESIGN.md,tokens.css,COMPONENTS.md,USAGE.md,ACCESSIBILITY.md}`.
- `docs/api/{api-contract.md,api-conventions.md,api-resources.md,openapi.yaml}`.
- `backend/laravel/routes/api.php`, `routes/channels.php`, `config/{clerk,cors,reverb}.php`.
- `backend/laravel/app/Support/PermissionCatalog.php` and `admin:bootstrap`.
- `phases/group-K-phases.md`, `phase-18.6-completion.md`, `phase-18.7-completion.md`, and `group-R-phases.md`.

The audit deliberately treats Laravel runtime behavior as the implementation source of truth and the frozen API documentation as the compatibility source of truth. A documented-but-unimplemented endpoint is not considered usable.

## 2. Repository and architecture findings

### Existing layout

| Area | Actual location | Finding |
| --- | --- | --- |
| Customer website | `frontend/web` | Existing Next.js 16 App Router, React, TypeScript, MUI application. Its routing instructions are customer-site-only. |
| Flutter customer app | `frontend/app` | Existing Material 3 application. Out of scope for Phase 19.1. |
| Shared design system | `frontend/design-system` | `tokens.css` is the canonical token authority; JSON/CSS/framework mappings are derived representations. |
| Laravel API | `backend/laravel` | Shared `/api/v1` API, Clerk verification, local RBAC, operational resources and Reverb authorization. |
| Admin frontend | **No directory exists** | No approved existing admin application or `frontend/web/admin` route was found. The owner-approved `frontend/admin` is therefore the proposed location. |

The root README previously described `frontend/web` as providing an “admin portal”. That wording was corrected to describe it as the customer website; the admin dashboard remains a separate application and origin.

### Architectural decision

The admin dashboard will be a separate Next.js application at `frontend/admin`, with its own:

- App Router, layouts, route tree, package scripts, build and deployment;
- environment configuration and runtime origin;
- Clerk provider boundary and Laravel API client;
- authorization gate and operational navigation;
- production host/subdomain, illustratively `admin.slfurnitures.com` until confirmed.

It must not be implemented under `frontend/web`, `/admin` in the customer site, or a customer-site proxy route.

## 3. Verified operational API coverage

Status meanings:

- **Usable** — route is registered and the runtime implementation is available for the current request-only scope.
- **Partial** — a route is registered, but one or more operations are a 501 stub or another required capability is incomplete.
- **Documented only** — present in the frozen contract/OpenAPI but not registered or not usable at runtime.
- **Deferred** — intentionally excluded from the current request-only production release.
- **Missing** — no endpoint exists and the frozen contract does not authorize inventing one in this phase.

All protected operations use a Clerk bearer token. Successful responses use the frozen `data` envelope; collections use `meta.pagination` where specified. Failure handling is the frozen API error contract (`401`, `403`, masked `404`, `409`, `422`, `429`, and sanitized `5xx` as applicable). Operational/private responses must be treated as `private, no-store`.

### Identity, session and notifications

| Screen/capability | Method and path | Operation | Permission / authority | Request and response | Query behavior | Runtime status |
| --- | --- | --- | --- | --- | --- | --- |
| Resolve current user | `GET /api/v1/me` | `USER-001` | Clerk identity; Laravel local user, role, account state and permissions | No request body; `User`/profile response | None | **Usable** |
| Update own allowed profile | `PATCH /api/v1/me` | `USER-002` | Self only; allow-list is profile fields, never role/permissions/state | `UpdateMeRequest` → `User` response | None | **Usable** |
| Notification inbox | `GET /api/v1/me/notifications` | `NOT-001` | Recipient-scoped; STAFF/ADMIN operational notification eligibility | `Notification[]` + `CollectionMeta` | `page`, `per_page`, unread filter per contract | **Usable** |
| Read/unread mutation | `PATCH /api/v1/me/notifications/{notification}` | `NOT-002` | Own recipient only; only `read` boolean | `MarkNotificationReadRequest` → `Notification` | None; idempotent | **Usable** |
| Clerk logout/session | Clerk client session APIs | Retired Laravel AUTH IDs | Clerk owns credentials, session, recovery and logout | No Laravel credential flow | N/A | **Usable outside Laravel API** |
| Dashboard aggregate/metrics | **No endpoint** | None | No approved analytics contract | No schema exists | None | **Missing**; do not fabricate counts or revenue |

The current-user response is the authorization bootstrap, not a permission grant. The frontend must fail closed if role/state/permissions cannot be resolved.

### Requests and enquiries — current production priority

| Capability | Method and path | Operation | Permission | Contract/schema and query | Runtime status |
| --- | --- | --- | --- | --- | --- |
| Request queue | `GET /api/v1/requests` | `REQ-004` | `requests.view` | Operational request collection; `page`, `per_page`, approved filters/sort | **Usable** |
| Request detail | `GET /api/v1/requests/{request}` | `REQ-005` | `requests.view` | Operational request detail including authorized private fields/attachments | **Usable** |
| Update request state/operational fields | `PATCH /api/v1/requests/{request}` | `REQ-006` | `requests.manage` | `UpdateFurnitureRequestRequest`; controlled `SUBMITTED → IN_REVIEW → CLOSED`; conflict/error contract applies | **Usable** |
| Request attachment | `POST /api/v1/requests/{request}/attachments` | `REQ-007` | Parent scope or operational authorization | Multipart `attachment`; scoped upload capability where required | **Usable** |
| Enquiry queue | `GET /api/v1/enquiries` | `ENQ-004` | `enquiries.view` | Operational enquiry collection; `page`, `per_page`, approved filters/sort | **Usable** |
| Enquiry detail | `GET /api/v1/enquiries/{enquiry}` | `ENQ-005` | `enquiries.view` | Operational enquiry detail, private attachments and authorized internal fields | **Usable** |
| Close enquiry | `POST /api/v1/enquiries/{enquiry}/close` | `ENQ-006` | `enquiries.manage` | Optional `staff_internal_notes`; controlled `OPEN → CLOSED`; idempotent close | **Usable** |
| Enquiry attachment | `POST /api/v1/enquiries/{enquiry}/attachments` | `ENQ-007` | Parent scope or operational authorization | Multipart `attachment`; scoped upload capability where required | **Usable** |

These are the most important first screens for the request-only release. Customer-facing `REQ-001` and `ENQ-001` are public submission endpoints, not admin creation endpoints.

### Catalog and inventory

| Capability | Method and path | Operation | Permission | Request/response | Runtime status |
| --- | --- | --- | --- | --- | --- |
| Operational product list | `GET /api/v1/admin/products` | `CAT-013` | `products.view` or `products.manage` | `OperationalProduct[]` + pagination; `OperationalProductIndexRequest` filters | **Usable**; route exists although frozen inventory marks CAT-013 as proposed |
| Operational product detail | `GET /api/v1/admin/products/{product}` | `CAT-014` | `products.view` or `products.manage` | `OperationalProduct` including fields authorized for inventory visibility | **Usable**; same documentation-status mismatch as CAT-013 |
| Create product | `POST /api/v1/products` | `CAT-007` | `products.manage` | `StoreProductRequest` → product resource | **Usable** |
| Update product | `PATCH /api/v1/products/{product}` | `CAT-008` | `products.manage` | `UpdateProductRequest` → product resource | **Usable** |
| Add product image | `POST /api/v1/products/{product}/images` | `CAT-009` | `products.manage` | Multipart image request → `ProductImage` | **Usable** |
| Add product variant | `POST /api/v1/products/{product}/variants` | `CAT-010` | `products.manage` | Variant creation contract is documented | **Partial**; controller returns 501 |
| Create category | `POST /api/v1/categories` | `CAT-011` | `products.manage` | `StoreCategoryRequest` → category | **Usable** |
| Update category | `PATCH /api/v1/categories/{category}` | `CAT-012` | `products.manage` | `UpdateCategoryRequest` → category | **Usable** |
| Inventory list/detail | `GET /api/v1/inventory`, `GET /api/v1/inventory/{inventory}` | `INV-001`, `INV-002` | `inventory.view` | `Inventory[]`/`Inventory` + pagination for list | **Usable** |
| Inventory adjustment | `POST /api/v1/inventory/{inventory}/adjust` | `INV-003` | `inventory.manage` | `AdjustInventoryRequest` with explicit change/reason; audit required | **Usable** |

Public CAT-001..006 remain customer catalog resources and are not a substitute for the operational product representation. Inventory quantities and reservations must never be exposed through public catalog calls.

### Staff, customers and audit

| Capability | Method and path | Operation | Permission | Request/response and query | Runtime status |
| --- | --- | --- | --- | --- | --- |
| Staff list/detail | `GET /api/v1/admin/staff`, `GET /api/v1/admin/staff/{user}` | `ADM-001`, `ADM-002` | ADMIN + `staff.manage` | `User` collection/detail; list pagination | **Partial**; routed but controller returns 501 |
| Invite staff | `POST /api/v1/admin/staff` | `ADM-003` | ADMIN + `staff.manage` | `StaffInviteRequest` → `User` | **Partial**; routed but controller returns 501 |
| Approve staff | `POST /api/v1/admin/staff/{user}/approve` | `ADM-004` | ADMIN + `staff.approve` | Idempotency key; `User`; audited; self-approval rejected | **Partial**; routed but controller returns 501 |
| Suspend/reactivate staff | `POST /api/v1/admin/staff/{user}/suspend|reactivate` | `ADM-005`, `ADM-006` | ADMIN + `staff.manage` | Idempotency key; `User`; audited/state-checked | **Partial**; routed but controller returns 501 |
| Audit log list | `GET /api/v1/admin/audit-logs` | `ADM-007` | ADMIN + `audit.view` | `AuditLog[]` + pagination; actor/action/resource/date filters | **Usable** |
| Customer list/detail | `GET /api/v1/users`, `GET /api/v1/users/{user}` | `ADM-008`, `ADM-009` | ADMIN + `users.manage_authorized` | `AdministrativeCustomer` collection/detail; pagination on list; STAFF/ADMIN targets masked | **Usable** |

The `admin:bootstrap <clerk-user-id> --confirm` command is an operator-only initial ADMIN bootstrap mechanism. It is not a browser registration, invitation, role-elevation, or frontend feature.

### Explicitly excluded from the initial dashboard

Order operations `ORD-005..014`, checkout `CHK-001`, payment `PAY-*`, and webhook operations are not usable launch features: the current production mode is Request Only and Groups H/I plus transactional order management remain deferred. Their routes/stubs must not appear as active navigation. The same applies to any delivery module.

## 4. Permission-to-screen mapping

The screen must be hidden when permission is absent, but hiding is not authorization. Every API call still relies on Laravel RBAC and state checks.

| Screen | Required role/permission | STAFF | ADMIN | Notes |
| --- | --- | :---: | :---: | --- |
| Overview shell | Active `STAFF` or `ADMIN`; no aggregate API yet | Yes | Yes | Show navigation/status only until a dashboard contract exists; no fabricated metrics. |
| Made-to-order requests | `requests.view`; mutations also `requests.manage` | Yes | Yes | Request-only priority. |
| Enquiries | `enquiries.view`; close also `enquiries.manage` | Yes | Yes | Request-only priority. |
| Products/catalog | `products.view`; writes `products.manage` | Yes | Yes | CAT-010 variant creation remains disabled until backend completion. |
| Categories | `products.manage` | Yes | Yes | Use catalog permission; no separate category permission exists. |
| Inventory | `inventory.view`; adjustments `inventory.manage` | Yes | Yes | Display only fields returned by Laravel. |
| Notifications | Active recipient with operational notification access; `notifications.read_operational` where enforced | Yes | Yes | NOT-001/002 only; no preference UI. |
| Staff administration | ADMIN + `staff.manage`/`staff.approve` | No | Yes | Current API is 501 partial; do not ship active mutations. |
| Customer directory | ADMIN + `users.manage_authorized` | No | Yes | Read-only approved customer operations; no impersonation/credentials. |
| Audit logs | ADMIN + `audit.view` | No | Yes | Read-only, private, audited source. |
| Account/profile/logout | `/me` self only; Clerk session controls | Yes | Yes | No role/permission/account-state edits. |

## 5. Proposed `frontend/admin` architecture (Phase 19.2 input)

No files are to be created until this audit is approved. The proposed application shape is:

```text
frontend/admin/
├── app/
│   ├── (auth)/sign-in/...
│   ├── (dashboard)/layout.tsx
│   ├── (dashboard)/overview/...
│   ├── (dashboard)/requests/...
│   ├── (dashboard)/enquiries/...
│   ├── (dashboard)/catalog/...
│   ├── (dashboard)/inventory/...
│   ├── (dashboard)/notifications/...
│   ├── (dashboard)/staff/...
│   ├── (dashboard)/customers/...
│   ├── (dashboard)/audit-logs/...
│   ├── (dashboard)/account/...
│   ├── error.tsx
│   ├── loading.tsx
│   └── not-found.tsx
├── components/{layout,states,forms,tables}/
├── lib/
│   ├── api/{client,errors,types}.ts
│   ├── auth/{clerk,laravel-identity,authorization}.ts
│   └── features/{requests,enquiries,catalog,inventory,notifications,...}/
├── theme/{theme.ts,index.ts}.ts
├── next.config.ts
├── package.json
└── tsconfig.json
```

### Runtime boundaries

- **Server:** resolve Clerk session token per request, call Laravel, resolve `/me`, enforce active account and role/permission before rendering protected route data. Never serialize the bearer token.
- **Client:** use Clerk UI/session hooks for sign-in, refresh and sign-out; use client components only for genuine table/form/dialog interaction. Client permission state is presentation-only.
- **Transport:** create one typed, framework-aware admin API client that preserves frozen envelopes, errors, pagination and `Cache-Control`; do not duplicate the customer transport by importing customer-site internals across app boundaries.
- **Next.js proxy/middleware:** use only as an early UX redirect for signed-out users. It is not the authorization boundary and must not decide RBAC from Clerk metadata.
- **Role admission:** `/me` must resolve to active local `STAFF` or `ADMIN`. CUSTOMER, missing local projection, suspended/inactive account, invalid/expired token, and failed authorization resolution are denied or shown a safe error; no fallback admission.
- **Permission sections:** navigation is derived from Laravel-resolved permissions, with a safe forbidden state if a deep link is opened without permission. Do not use role-only checks where exact permissions are available.

### Package and deployment proposal

`frontend/admin/package.json` should have independent `dev`, `build`, `start`, `lint`, `typecheck`, unit/component and integration test scripts. Its local port must differ from the customer website (proposed `3001`, owner-confirmable). Separate deployment configuration must define at minimum:

| Variable | Boundary |
| --- | --- |
| `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` | Admin browser, only public Clerk key |
| `NEXT_PUBLIC_ADMIN_ORIGIN` | Admin canonical origin/redirect validation |
| `NEXT_PUBLIC_API_ORIGIN` | Browser API origin if direct browser calls are approved |
| `API_BASE_URL` | Server-only Laravel origin, if server-first calls are used |
| Reverb public host/key/scheme | Browser WebSocket connection only; never secret |

Names and direct-vs-server API transport are implementation decisions for Phase 19.2, not new API contracts. Customer and admin builds must never share deployment secrets or accidentally inherit each other’s environment files.

## 6. Shared design-system strategy

The admin app consumes the existing `frontend/design-system/tokens.css` and synchronized `design-tokens.json`; it does not create an admin-only token system. Its MUI theme is a thin adapter equivalent in principle to `frontend/web/theme/theme.ts`, preferably extracted into a future shared, dependency-light theme mapping only after owner approval. Until then, copying a small adapter is less risky than importing customer application internals.

Admin information density may use MUI tables, filters and navigation, but must retain the same token meanings, typography, focus treatment, spacing, shape, responsive thresholds and restrained visual language. The design system explicitly includes account/admin utility text. Accessibility follows `ACCESSIBILITY.md`: keyboard operation, visible focus, semantic tables/headings/landmarks, non-color statuses, labels/errors, 44px target intent, zoom/reflow, reduced motion and safe private-data messaging.

## 7. Separate-origin security and deployment checklist

Before production deployment, owner/operations must configure exact origins—never wildcards:

1. Add the confirmed admin origin to Clerk allowed origins/redirect configuration and `CLERK_AUTHORIZED_PARTIES`; continue using the existing Clerk instance.
2. Configure Laravel `CORS_ALLOWED_ORIGINS` with the exact admin origin and API origin requirements. `allowed_origins_patterns` remains empty; `supports_credentials` does not justify wildcard origins.
3. Keep Clerk issuer, audience and authorized-party JWT checks enabled. Do not accept an admin token solely because it came from Clerk; Laravel still resolves local role, active state and permissions.
4. Configure Reverb `REVERB_ALLOWED_ORIGINS` with the exact admin host and use HTTPS/WSS in production. The client authenticates `/broadcasting/auth` with the current Clerk bearer token and can subscribe only to its own `private-notifications.{localUserId}` channel.
5. Reconcile NOT-001 after initial connection, reconnect and every `notification.state-changed` signal. The signal is not authoritative; unsubscribe on logout.
6. Use HTTPS, a restrictive CSP with only required API/Clerk/Reverb origins, secure headers, no token persistence, no token in URLs/logs/props, and no customer/admin origin sharing of private caches.
7. Deploy customer and admin independently with separate build artifacts, domains, environment values and rollback paths. API remains the shared authority.

Current Laravel configuration already supports explicit `CORS_ALLOWED_ORIGINS`, `CLERK_AUTHORIZED_PARTIES` and `REVERB_ALLOWED_ORIGINS`; production values are not confirmed in this repository and must not be guessed.

## 8. Notification handoff

The admin app will consume only the existing contracts:

- `NOT-001`: recipient-scoped `GET /api/v1/me/notifications`.
- `NOT-002`: recipient-scoped `PATCH /api/v1/me/notifications/{notification}` with `{ "read": true|false }`.
- Reverb private channel `private-notifications.{localUserId}`.
- `notification.state-changed` as a minimal invalidation signal.

Operational STAFF/ADMIN notifications for new made-to-order requests and enquiries are mandatory under Phase 18.7. No notification preferences, FCM changes, new notification events, or new endpoints are authorized. Notification content remains private and must not be rendered in customer surfaces.

## 9. Initial information architecture and screen inventory

1. **Overview** — operational landing shell only until a dashboard API is approved.
2. **Made-to-order requests** — queue, filters, detail, attachments, controlled status update.
3. **Enquiries** — queue, filters, detail, attachments, close with optional internal note.
4. **Catalog** — products, product detail/edit, media, variants where backend supports it, categories.
5. **Inventory** — operational stock list/detail and explicit audited adjustments.
6. **Notifications** — operational inbox and read/unread state.
7. **Staff administration** — ADMIN-only, initially blocked/labelled unavailable while ADM-001..006 remain 501.
8. **Customers** — ADMIN-only read-only directory/detail through ADM-008/009; no impersonation.
9. **Audit logs** — ADMIN-only read-only filtered audit view.
10. **Account** — current profile fields supported by `/me`, Clerk security/session controls, logout.

Cart, checkout, payments, delivery and order operations are future placeholders only and must not be presented as operational navigation in the request-only release.

## 10. API gaps, inconsistencies and risks

| Finding | Impact | Required handling |
| --- | --- | --- |
| No dashboard aggregate endpoint | Overview cannot show authoritative KPIs | Build a useful shell/queue links only, or obtain an owner-approved contract in a later phase; do not aggregate undocumented data in the frontend. |
| ADM-001..006 are registered but `AdminController` returns 501 | Staff administration cannot ship | Keep screen unavailable; complete backend phase/approval before enabling. |
| CAT-010 is registered but `ProductController::storeVariant()` returns 501 | Variant creation cannot ship | Keep mutation unavailable; use existing read representations only. |
| Frozen API inventory still labels CAT-013/014 and several management rows `PROPOSED`, while runtime routes/implementation exist | Documentation/runtime status drift | Owner/API steward must reconcile status through the frozen-contract change process; Phase 19.1 does not edit the frozen contract. |
| Existing customer app README/structure must not be reused as admin application structure | Accidental coupling and route/deployment leakage | Scaffold only `frontend/admin` after approval. |
| Production admin origin, Clerk allowed party, CORS, CSP and Reverb values are not confirmed | Cross-origin auth/WebSocket deployment risk | Require owner/operations values before staging. |
| No admin invitation URL or CTA is generated by current notifications | Notification links cannot deep-link yet | Notification inbox opens from the dashboard; do not invent email CTA URLs. |

## 11. Roadmap reconciliation and implementation sequence

The roadmap remains ordered as follows; no historical completion evidence was rewritten:

- **19.1** this audit and approval.
- **19.2** separate `frontend/admin` foundation and token-driven MUI theme.
- **19.3** Clerk session integration plus Laravel `/me` admission and permission boundary tests.
- **19.4** dashboard shell/navigation and shared operational states.
- **19.5** overview shell without fabricated analytics.
- **19.6–19.9** catalog, category, media and inventory screens, enabling only usable endpoints.
- **19.10–19.11** request and enquiry workflows (first launch-critical screens).
- **19.12** customer directory (ADM-008/009).
- **19.13** staff management only after ADM-001..006 are implemented and verified.
- **19.14** audit visibility (ADM-007).
- **19.15** account/settings limited to supported Clerk and `/me` behavior.
- **19.16** integration, permission-boundary, accessibility, responsive and production hardening.

Group T remains the QA group after Group S. Its unit/API/web/Flutter/E2E/security/accessibility/performance/regression phases are preserved and are not renumbered or silently consumed by this audit. Phase 19.16 must add admin-specific checks to those existing QA phases; it does not replace them.

## 12. Owner decisions required before Phase 19.2

1. Confirm `frontend/admin` as the permanent application directory.
2. Confirm the admin production origin/subdomain and staging/local origins.
3. Confirm the admin development port (proposed `3001`).
4. Confirm exact Clerk production allowed origins/redirect URLs and Laravel authorized-party values.
5. Confirm exact production API and Reverb HTTPS/WSS origins for CORS/CSP configuration.
6. Decide whether Overview remains a navigation/status shell or whether a separately approved dashboard-summary contract is needed.
7. Approve the handling of CAT-010 and ADM-001..006 as disabled sections until their backend implementations are complete.
8. Approve the frozen-document status reconciliation for CAT-013/014 and other runtime/documentation drift; no contract change is implied by this audit.
9. Confirm whether the admin app should use direct browser API calls or a server-first transport for each protected operation; either choice must preserve bearer-token and cache rules.

## Exit evidence

- Separate application boundary is identified and no customer `/admin` route is proposed.
- Runtime route coverage was compared with Laravel controllers and tests, not inferred from the roadmap alone.
- Frozen operation IDs, permissions, schemas, pagination and error behavior are mapped for the launch-relevant surface.
- Clerk/Laravel/RBAC/Reverb/CORS boundaries are defined without weakening validation.
- Request-only scope, notification handoff, design-system reuse and QA continuity are explicit.
- No application scaffold or backend/API change was made.
