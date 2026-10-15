# SL Furnitures — Phase 19.4: Dashboard Shell & Navigation

**Group:** S — Admin Dashboard Frontend
**Phase:** 19.4 — Dashboard Shell & Navigation
**Application:** `frontend/admin`
**Framework:** Existing Next.js App Router + TypeScript + MUI
**Authentication:** Existing Clerk integration
**Authorization:** Existing Laravel `/api/v1/me` admission boundary
**Design authority:** `frontend/design-system/tokens.css`
**Local origin:** `http://localhost:3001`
**Status:** APPROVED FOR IMPLEMENTATION

## 1. Objective

Build the responsive, accessible, production-quality dashboard shell for the independent SL Furnitures Admin Dashboard.

This phase turns the Phase 19.3 authentication foundation into a consistent operational interface.

Implement:

1. A responsive desktop sidebar.
2. A mobile navigation drawer.
3. A top application bar.
4. Brand and application identity.
5. Permission-aware navigation.
6. Active route highlighting.
7. Breadcrumbs or equivalent page context.
8. A consistent page content area.
9. An authenticated user/account menu.
10. A reliable sign-out control.
11. A neutral overview landing page without fabricated business metrics.
12. Consistent page titles and section placeholders for later phases.
13. Accessible navigation and responsive layout behavior.
14. Shared operational page-state integration.
15. Tests and documentation.

**Do not implement business workflows or retrieve real operational data in this phase.**

The purpose is to establish the shared shell that later request, enquiry, catalog, inventory, notification, staff, and audit screens will use.

## 2. Mandatory audit before implementation

Inspect the current repository rather than assuming the files or APIs described below exist exactly as written.

Read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/admin/AGENTS.md`.
- `frontend/admin/README.md`.
- `frontend/admin/app/`.
- `frontend/admin/components/`.
- `frontend/admin/lib/auth/`.
- `frontend/admin/lib/api/`.
- `frontend/admin/theme/`.
- `frontend/admin/app/(protected)/layout.tsx`.
- `frontend/admin/app/(protected)/page.tsx`.
- `frontend/admin/proxy.ts`.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/phase-19.2-completion.md`.
- `phases/phase-19.3-completion.md`.
- `phases/group-S-phases.md`.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `frontend/design-system/COMPONENTS.md`.
- Existing Laravel `PermissionCatalog` and relevant API authorization rules.

Verify the existing Phase 19.3 implementations of:

- `requireOperationalIdentity()`.
- `requirePermission()`.
- `hasPermission()`.
- The sanitized authenticated identity provider.
- The existing sign-out control.
- The four authentication/authorization failure states.
- Private/no-store API transport behavior.

Reuse these implementations. Do not build a parallel authentication or permission system.

Before editing, report a brief plan describing the shell components, navigation permission mapping, route structure, and responsive behavior.

## 3. Non-negotiable security boundary

The Phase 19.3 implementation is authoritative.

Every protected dashboard request must remain behind the established server-side admission boundary.

Use the existing Laravel-resolved identity and effective permission list.

Never trust:

- Client-supplied role values.
- Clerk metadata as the business role.
- A frontend `isAdmin` boolean.
- Navigation visibility as authorization.
- A previously cached identity from another session.
- A role inferred from the current URL.
- An ADMIN role as a substitute for an exact permission.

The shell may receive the sanitized identity already approved by Phase 19.3. It must never receive or expose a Clerk bearer token.

Do not weaken, bypass, or replace the protected layout.

Do not move protected content into a public route.

Do not change the Laravel `/me` contract.

Do not add a new authentication provider, role, or permission.

## 4. Design system and theme

Use the existing admin MUI theme and canonical shared design tokens.

Authoritative token source:

`frontend/design-system/tokens.css`

Follow the existing documented semantic tokens for:

- Canvas and paper surfaces.
- Primary and secondary text.
- Borders and dividers.
- Primary actions.
- Brand accents.
- Status colors.
- Typography.
- Spacing.
- Radius.
- Focus styling.
- Breakpoints.

Do not introduce an independent admin palette.

Do not copy token values into a new file.

Do not change the customer website's visual system.

Do not install another component library.

The admin shell should look like a cohesive SL Furnitures application, with a disciplined operational layout rather than a generic analytics template.

Prefer restrained surfaces, clear information hierarchy, readable labels, consistent spacing, and accessible interaction states.

## 5. Application shell architecture

Build reusable components in the existing project structure.

Suggested responsibilities:

- `AdminShell`: shared authenticated application frame.
- `AdminSidebar`: desktop navigation.
- `AdminMobileDrawer`: responsive mobile navigation.
- `AdminTopbar`: menu control, page context, account menu.
- `AdminNavigation`: permission-filtered navigation items.
- `AdminBreadcrumbs`: current page context.
- `AdminUserMenu`: current identity and sign-out.
- `AdminPageContainer`: consistent page gutters and width.
- `AdminPageHeader`: consistent title, description, and action placement.

These are proposed names, not a requirement to duplicate existing components. Reuse or extend an existing primitive when it already serves the same purpose.

Keep the shell modular without introducing an elaborate abstraction framework.

The shell should wrap all future protected operational pages through the existing protected route layout.

Do not duplicate Clerk or Laravel admission logic inside individual shell components.

## 6. Desktop layout

Implement a responsive desktop frame with:

### Sidebar

- SL Furnitures brand/application identity. logo assets in `frontend/admin/public`.
- Navigation grouped into meaningful operational sections.
- Clear current-page indicator.
- Consistent icon and label alignment.
- Appropriate active, hover, and focus states.
- Optional collapse behavior only if it can be implemented accessibly without unnecessary complexity.

Avoid an oversized logo or excessive decorative content.

The sidebar must not show links the current identity is not permitted to access.

### Topbar

Include:

- A mobile menu trigger.
- Current page title or breadcrumb context.
- Authenticated account menu.
- Sign-out action.
- Optional restrained brand or environment context if useful.

Do not add a global search box unless a real, approved search workflow already exists.

Do not add fake notifications, unread badges, profile images, or account details.

### Main content

- Responsive page container.
- Consistent heading placement.
- Clear separation between title and page content.
- Appropriate minimum width and overflow behavior.
- No horizontal overflow at common desktop sizes.

Avoid nested cards around every element. Use MUI Paper/Card only when it provides meaningful grouping.

## 7. Mobile and tablet navigation

On narrower screens:

- Replace the persistent desktop sidebar with a mobile drawer.
- Provide a clearly labelled menu trigger.
- Use the MUI-supported drawer behavior.
- Support keyboard opening, navigation, and dismissal.
- Close the drawer after selecting a destination.
- Keep focus behavior accessible.
- Prevent interaction with obscured content while a modal drawer is open.
- Respect reduced-motion preferences.
- Avoid layout shifts and clipped page titles.

Do not simply shrink the desktop sidebar until its labels become unreadable.

Do not require hover to discover a navigation action.

Use the shared token-defined breakpoints unless an actual accessibility or layout constraint requires a documented adjustment.

## 8. Navigation model

Define navigation through one centralized, typed configuration.

Each item should specify only the metadata it needs, such as:

- Stable identifier.
- Label.
- Path.
- Icon.
- Navigation group.
- Required exact permission, when applicable.
- Optional explicitly approved role restriction.

Do not scatter permission checks throughout multiple copies of the sidebar.

Filter navigation from the Laravel-resolved effective permissions.

### Initial navigation groups

Use the approved Group S roadmap and actual backend permission catalog to determine the final entries. The expected operational areas include:

**Workspace**

- Overview.

**Operations**

- Made-to-order requests.
- Enquiries.
- Notifications.

**Catalog**

- Products.
- Categories.
- Inventory.

**Administration**

- Customers.
- Staff.
- Audit logs.

Only show a section when its required permission is present. Use the actual route and permission vocabulary established in the project; the labels above are human-readable examples.

Do not invent permission identifiers or assume that every listed area has a complete backend implementation.

Do not introduce links to checkout, orders, payments, delivery management, or other deferred commerce workflows.

Do not expose staff-management actions merely because the user can enter the dashboard.

### Permission mapping

Before implementing the navigation, inspect Laravel's actual `PermissionCatalog`.

Build a documented mapping from each implemented navigation entry to its exact required permission.

Potential mappings to verify include:

- Requests → `requests.view`.
- Enquiries → `enquiries.view`.
- Notifications → `notifications.read_operational`.
- Products → `products.view`.
- Inventory → `inventory.view`.
- Staff → the approved staff-management permission.
- Customers → the approved customer-management permission.
- Audit logs → `audit.view`.

These are candidates to verify, not permission definitions to create.

For categories, determine the actual catalog authorization rule rather than assuming that product permission automatically grants category permission.

If the backend has no distinct permission for a feature, follow the approved backend policy. Do not invent a broader frontend-only authorization rule.

### ADMIN is not an automatic bypass

An ADMIN identity without the exact permission must not receive a protected navigation link solely because its role is ADMIN.

An authorized STAFF identity should see every navigation item for which it has the necessary permissions, regardless of whether the item is traditionally considered an administrative feature.

Do not hardcode an assumption that every STAFF user has one fixed set of capabilities.

## 9. Direct route authorization

Hiding a link is not sufficient.

For every route placeholder implemented in this phase:

1. Resolve the existing operational identity on the server.
2. Apply the appropriate exact permission guard where that route represents a permission-controlled feature.
3. Render the page only after authorization succeeds.
4. Use the existing denial state for a missing permission.
5. Never fetch business data from a placeholder.
6. Never render private content in static output.

Use the existing `requirePermission()` helper or its verified equivalent.

Do not create a client-only permission gate as the sole protection for a route.

Do not duplicate the full permission catalog in a frontend role matrix.

If a navigation entry has no valid permission mapping or route contract, omit it and document the dependency rather than guessing.

## 10. Overview landing page

Replace the Phase 19.3 development confirmation page with a minimal, polished authenticated overview.

The overview should establish the visual language and help the operator navigate.

It may contain:

- A welcome heading using approved profile information.
- A short explanation of the dashboard's purpose.
- Permission-filtered shortcuts to implemented route placeholders.
- A neutral description of available operational areas.
- A clear distinction between available navigation and features that are not yet implemented.

**It must not contain fabricated:**

- Revenue.
- Sales.
- Orders.
- Conversion rates.
- Customer counts.
- Inventory totals.
- Pending-request counts.
- Notification counts.
- Activity timelines.
- Charts.
- Recent transactions.

Do not call arbitrary API endpoints to populate overview cards.

Do not invent metrics just to make the dashboard appear populated.

The overview is a navigational landing page, not a fake analytics dashboard.

Do not add API calls unless a real approved overview endpoint already exists and is explicitly within the current phase's scope.

## 11. Section placeholders

Later phases will implement the real operational sections.

If the navigation includes destinations that do not yet have actual screens, provide simple, protected placeholder pages where necessary to avoid dead links.

A placeholder must:

- Have the correct page title.
- State clearly that the feature will be implemented in a later phase.
- Avoid suggesting the feature is already functional.
- Make no business API requests.
- Show no fabricated records.
- Require the correct permission before rendering.
- Use the shared page container and header.

Do not build fake CRUD forms, fake tables, fake inboxes, or mock operational workflows.

If a route depends on an unimplemented backend endpoint or unapproved permission, do not create a misleading operational page. Omit the navigation item until its dependency is resolved and document the reason.

Avoid creating many unnecessary routes solely to make the navigation appear complete.

## 12. Active navigation and page context

Implement correct active-route behavior.

Requirements:

- The exact overview route highlights Overview.
- A future nested route highlights its correct parent navigation item.
- Similar route prefixes do not accidentally highlight multiple unrelated items.
- Hidden or unauthorized navigation entries cannot become visible through active-route state.
- Breadcrumbs reflect the actual route hierarchy.
- Page titles are consistent between the topbar, breadcrumb, and main heading where appropriate.

Use supported Next.js routing APIs.

Do not inspect browser globals during server rendering.

Avoid hydration mismatches between the server and client.

If responsive drawer state or active-route state requires a client component, keep that boundary small.

## 13. User menu and sign-out

Reuse the existing Phase 19.3 sign-out mechanism.

The account menu should show only approved, sanitized local identity information, such as:

- Display name.
- Email address, if present and approved for display.
- Local role label.

Do not expose the Clerk token or internal API response.

Use an approved fallback for missing profile values. Do not fabricate an email address or role.

The menu must provide a working sign-out action.

Sign-out must use the existing Clerk SDK integration and its safe redirect behavior.

After sign-out:

- Protected content must no longer be accessible.
- Stale client identity state must not remain visible.
- Protected API calls must not reuse the prior identity.
- The user must return to the approved signed-out route.

Do not implement an independent session store.

Do not introduce a custom sign-out API.

## 14. Notifications in the shell

The notification area must not imply a functioning notification inbox before its UI phase is implemented.

Do not display a bell with a fabricated unread count.

Do not create a Reverb client in Phase 19.4.

Do not introduce a new notification endpoint.

If the approved notification page is not implemented, either provide a clearly labelled permission-protected placeholder or omit its navigation item until its scheduled implementation, according to the Group S roadmap.

Preserve the existing Phase 18 notification behavior and contracts.

## 15. Accessibility

Follow `frontend/design-system/ACCESSIBILITY.md`.

At minimum:

- Use semantic navigation landmarks.
- Provide accessible names for navigation controls.
- Use appropriate `aria-current` on the active destination.
- Keep keyboard focus visible.
- Support drawer opening and dismissal through the keyboard.
- Ensure focus is managed appropriately for modal navigation.
- Provide accessible names for icon-only buttons.
- Use headings in a logical hierarchy.
- Do not communicate active state through color alone.
- Maintain sufficient text and focus contrast.
- Respect reduced-motion preferences.
- Ensure responsive text does not clip.
- Test keyboard-only navigation.

Do not disable built-in MUI accessibility behavior.

Do not use placeholder labels as substitutes for accessible names.

## 16. Responsive and visual verification

Verify the shell at minimum at these viewport widths:

- 360 px.
- 390 px.
- 768 px.
- 1024 px.
- 1440 px.

Test:

- Sidebar behavior.
- Drawer behavior.
- Long page titles.
- Long account names.
- Navigation with many authorized items.
- Navigation with very few permissions.
- STAFF and ADMIN presentations.
- Missing profile fields.
- Denied route states.
- Small-screen page overflow.
- Keyboard focus visibility.

The UI should remain useful even if an authorized user has only a small subset of permissions.

Do not assume that an ADMIN always has more effective permissions than STAFF.

## 17. Tests

Create automated tests for the shell and navigation.

### Navigation configuration

1. Every configured link has a valid path and unique stable identifier.
2. Every protected entry maps to an existing approved permission.
3. No fabricated permission identifier is used.
4. Navigation is filtered from Laravel-resolved permissions.
5. A CUSTOMER identity cannot render the protected shell.
6. ADMIN role alone does not reveal a missing-permission link.
7. STAFF with the required permission can see the relevant link.
8. STAFF without the permission cannot see it.
9. Missing permission data fails closed.

### Routing

10. Overview renders only behind the protected layout.
11. Authorized placeholders render their neutral state.
12. Unauthorized direct navigation shows the existing denial state.
13. Unknown routes use the correct not-found behavior.
14. Active-route matching is correct for nested routes.
15. Similar path prefixes do not produce incorrect highlights.
16. Protected pages do not initiate business API calls in this phase.

### User menu

17. Sanitized identity fields are displayed correctly.
18. Missing display fields use approved fallbacks.
19. No Clerk bearer token is rendered.
20. Sign-out invokes the existing Clerk flow.
21. Stale identity state is cleared after sign-out.

### Responsive behavior and accessibility

22. Desktop sidebar renders correctly.
23. Mobile drawer opens and closes.
24. Selecting a destination closes the drawer.
25. Keyboard focus is visible.
26. Navigation controls have accessible names.
27. Active destinations expose the correct semantic state.
28. Long navigation labels do not break layout.
29. Small screens do not introduce unintended horizontal overflow.
30. Empty navigation permission sets remain usable.

### Overview integrity

31. No fabricated business metrics are rendered.
32. No unapproved API endpoints are called.
33. No fake unread count is displayed.
34. No customer, request, enquiry, or inventory records are mocked as live data.

Use the project's actual testing tools and accessible queries.

## 18. Quality gates

Run the existing admin application commands:

```bash
cd frontend/admin

npm run lint
npm run typecheck
npm run test
npm run build
```

Also run:

```bash
git diff --check
```

Use the repository's established package manager if it differs from npm.

Verify the application runs on port `3001`.

Re-run relevant Phase 19.3 authentication tests to ensure the new layout has not bypassed the protected route boundary.

Check that the customer website, Laravel API, and database remain unchanged.

Do not modify backend code to accommodate frontend-only navigation assumptions.

## 19. Documentation

Update `frontend/admin/README.md` and `frontend/admin/AGENTS.md` if needed to document:

- Shell component responsibilities.
- Navigation configuration.
- Permission mapping.
- Route protection requirements.
- How to add future navigation entries.
- How to implement protected pages.
- Design-token usage.
- Accessibility expectations.
- How to run tests.

Create:

`phases/phase-19.4-completion.md`

Include:

1. Files changed.
2. Shell architecture.
3. Sidebar and topbar implementation.
4. Responsive drawer behavior.
5. Navigation groups and routes.
6. Verified permission mapping.
7. Direct route authorization.
8. Overview page behavior.
9. Placeholder routes and deferred dependencies.
10. Accessibility and responsive test results.
11. Authentication regression results.
12. Lint/typecheck/test/build results.
13. Known limitations.
14. Phase 19.5 handoff.

Do not rewrite completed phase reports.

## 20. Explicit exclusions

Do not implement:

- Request management.
- Enquiry management.
- Catalog CRUD.
- Category management workflows.
- Inventory operations.
- Customer management.
- Staff management.
- Audit log screens.
- Functional notification inbox.
- Reverb subscription.
- Notification preferences.
- Dashboard metric APIs.
- Charts or analytics.
- New Laravel routes.
- New database migrations.
- New roles or permissions.
- Checkout, orders, payments or delivery workflows.
- Production Coolify deployment.

These must be implemented in their approved later phases.

## 21. Definition of Done

Phase 19.4 is PASS when:

1. The existing protected route boundary remains intact.
2. The responsive dashboard shell is complete.
3. Desktop sidebar and mobile drawer behave correctly.
4. Navigation is generated from a centralized, typed configuration.
5. Each implemented protected entry uses an actual approved permission.
6. Unauthorized navigation and direct route access are denied.
7. The overview contains no fabricated operational data.
8. Placeholders are explicit, protected and non-functional by design.
9. User identity display uses sanitized Laravel-resolved data.
10. Sign-out works and clears stale identity presentation.
11. Accessibility and responsive behavior are verified.
12. Authentication regression tests pass.
13. Lint, typecheck, tests and production build pass.
14. No Laravel contract, database, customer website or business workflow changes are introduced.
15. Documentation and the completion report are complete.

If a required permission mapping is ambiguous, do not guess. Omit the affected navigation entry and document the dependency. If a real smoke test cannot be performed, report PARTIAL instead of claiming PASS.

## 22. Final report

Return:

**PHASE 19.4 — PASS / PARTIAL / FAIL**

Include:

- Files changed.
- Shell architecture.
- Sidebar/topbar/mobile drawer behavior.
- Navigation groups and exact permission mappings.
- Protected route behavior.
- Overview content and confirmation that no fabricated metrics exist.
- Placeholder routes and deferred sections.
- Sign-out verification.
- Responsive and accessibility results.
- Automated validation results.
- Authentication regression results.
- Remaining limitations.
- Readiness for Phase 19.5.

**Implement Phase 19.4 now. Do not advance to Phase 19.5 without owner approval.**
