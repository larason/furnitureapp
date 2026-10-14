# SL Furnitures — Phase 19.2: Admin Next.js Foundation & Design System

**Group:** S — Admin Dashboard Frontend  
**Phase:** 19.2 — Admin Next.js Foundation & Design System  
**Status:** APPROVED FOR IMPLEMENTATION  
**Application:** `frontend/admin`  
**Framework:** Next.js 16 + React + TypeScript + MUI  
**Local development port:** 3001  
**Identity provider:** Existing Clerk application  
**API:** Existing Laravel `/api/v1`  
**Design authority:** `frontend/design-system`  
**Business scope:** REQUEST ONLY

---

## 1. Objective

Create the independent SL Furnitures Admin Dashboard Next.js application and establish a production-quality, design-token-driven frontend foundation.

This phase is responsible for:

1. Creating and configuring `frontend/admin`.
2. Establishing Next.js App Router and TypeScript foundations.
3. Installing and configuring Material UI correctly.
4. Integrating the existing SL Furnitures design tokens.
5. Creating the admin MUI theme.
6. Establishing reusable UI primitives.
7. Establishing responsive layout foundations.
8. Preparing typed Laravel API transport.
9. Preparing Clerk integration boundaries.
10. Establishing error, loading, and empty-state primitives.
11. Configuring accessibility, testing, linting, and build validation.
12. Preparing independent deployment configuration.
13. Documenting the architecture for later admin implementation phases.

The goal is a clean, maintainable foundation on which later phases can build operational screens without redesigning fundamental components.

**Do not implement real dashboard business features during Phase 19.2.**

## 2. Mandatory pre-implementation audit

Read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/web/AGENTS.md`.
- `frontend/web/README.md`.
- `frontend/web/ROUTING.md`.
- `README.md`.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/group-S-phases.md`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/design-tokens.json`, if present.
- `frontend/design-system/COMPONENTS.md`.
- `frontend/design-system/USAGE.md`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `frontend/design-system/production-caveats.md`, if present.
- Existing `frontend/web/theme/theme.ts`.
- Existing website Next.js/MUI integration.
- Existing font configuration.
- Existing package-manager conventions.
- Laravel API/OpenAPI contracts relevant to the admin client.

Inspect actual files instead of assuming their contents from this prompt.

Preserve established repository conventions unless a concrete incompatibility requires deviation.

Before coding, briefly report the intended directory structure, package-manager approach, and design-token integration approach.

## 3. Application boundary

Create:

`frontend/admin`

This is a separate Next.js application.

It must have its own:

- `package.json`.
- Dependency and build configuration.
- Next.js configuration.
- TypeScript configuration.
- App Router.
- Root layout.
- Theme provider.
- Environment example.
- Testing configuration.
- Error boundaries.
- Documentation.
- Independent build and start commands.

Do not create admin routes inside `frontend/web`.

Do not modify the customer website's routing, metadata, navigation, or layouts.

Do not create a new Laravel application.

Use the same Laravel API and Clerk identity provider that the customer website uses.

## 4. Package management and dependencies

Match the existing project's verified dependency compatibility.

Use Next.js 16 and compatible versions of:

- React.
- React DOM.
- TypeScript.
- Material UI.
- Emotion.
- MUI's supported Next.js App Router integration.
- Clerk Next.js SDK.
- Existing project linting and testing tools.

Inspect the customer website's dependency versions and its working App Router/MUI configuration before choosing exact versions.

Avoid blindly installing latest versions.

Use the repository's current package manager and lockfile conventions.

Do not introduce an independent workspace strategy unless the repository already uses one or it is necessary for the selected integration.

Do not add:

- Redux.
- Zustand.
- A separate CSS component library.
- Tailwind.
- shadcn/ui.
- Another authentication provider.
- A second design-token system.
- Unnecessary icon libraries.
- A large collection of speculative packages.

MUI and the project's approved design system are the foundation.

## 5. Proposed application structure

Use a structure appropriate to the actual Next.js configuration.

A recommended starting point:

```text
frontend/admin/
├── app/
│   ├── layout.tsx
│   ├── page.tsx
│   ├── loading.tsx
│   ├── error.tsx
│   ├── not-found.tsx
│   └── globals.css
├── components/
│   ├── providers/
│   ├── ui/
│   ├── layout/
│   └── states/
├── lib/
│   ├── api/
│   │   ├── client.ts
│   │   ├── errors.ts
│   │   └── types.ts
│   ├── auth/
│   ├── config/
│   └── utils/
├── theme/
│   ├── theme.ts
│   ├── providers.tsx
│   └── index.ts
├── public/
├── tests/
├── .env.example
├── .gitignore
├── next.config.ts
├── package.json
├── tsconfig.json
├── eslint.config.mjs
├── README.md
└── AGENTS.md
```

Adapt filenames to match established repository practices.

Do not build an elaborate folder tree full of empty placeholder modules.

Create only directories and abstractions that are immediately useful.

### Route planning

Reserve space for future protected routes:

- Overview.
- Requests.
- Enquiries.
- Catalog.
- Categories.
- Inventory.
- Notifications.
- Customers.
- Staff.
- Audit logs.
- Account.

The final protected dashboard route structure belongs to Phases 19.3–19.4.

Do not publish these as accessible operational pages during foundation setup.

## 6. Design system — strict source of truth

The existing shared SL Furnitures design system is mandatory.

**Canonical authority:**

`frontend/design-system/tokens.css`

Do not invent new brand colors, typography, spacing scales, shadows, radii, or breakpoint systems.

Do not create an independent admin design system.

Do not treat the customer website's compiled theme as the canonical token source.

Use existing semantic tokens such as:

- `--surface-canvas`
- `--surface-paper`
- `--surface-editorial`
- `--text-primary`
- `--text-secondary`
- `--text-muted`
- `--border-subtle`
- `--border-default`
- `--action-primary`
- `--action-primary-hover`
- `--action-focus`
- `--accent-brand`
- `--color-success`
- `--color-warning`
- `--color-danger`
- `--color-info`

Inspect and preserve all actual token definitions.

The design system defines an architectural, warm, disciplined visual language. The admin dashboard should express it through operational information density rather than introducing unrelated dashboard styling.

### Enforcement

Create a clear process ensuring the admin MUI theme stays aligned with canonical tokens.

Preferred options:

1. Consume the shared CSS tokens directly.
2. Use existing generated token mappings if already supported.
3. Add a small deterministic adapter only if necessary.

Avoid manually duplicating token values throughout components.

If importing shared CSS from outside the Next.js application creates tooling limitations, solve those within the existing build architecture.

Do not copy the canonical token file into a second independently maintained version.

Do not modify canonical brand tokens merely to accommodate a dashboard component.

## 7. MUI theme implementation

Create the admin MUI theme as a thin mapping from canonical design tokens.

Configure:

- Palette.
- Background surfaces.
- Text colors.
- Border colors.
- Typography.
- Spacing.
- Shape.
- Breakpoints.
- Focus styling.
- Component default variants.
- Component style overrides where justified.

Use the semantic meanings defined in the shared design system.

Do not invent a new palette.

### Typography

Inspect the existing font authority carefully.

The current design system defines:

- Display typography: Young Serif.
- UI/body typography: Helvetica Now Text with approved fallbacks.
- Monospace typography: approved system/mono stack.

Follow the documented font-loading and licensing strategy.

Do not download, bundle, or redistribute proprietary fonts without authorized assets.

Do not silently replace the design system with Inter, Poppins, Roboto, or arbitrary Google Fonts.

Use approved fallbacks where the intended font is unavailable.

Keep operational body text legible at dense information sizes.

### MUI components

Establish consistent theme defaults for:

- Button.
- IconButton.
- TextField.
- Select.
- Checkbox.
- Radio.
- Switch, if later approved.
- FormControl.
- Typography.
- Paper.
- Card.
- Divider.
- Chip.
- Alert.
- Dialog.
- Menu.
- Tooltip.
- Table.
- TableCell.
- TableRow.
- Pagination.
- Skeleton.

Do not implement customized overrides for every possible component without a demonstrated need.

Prefer centralized, minimal, documented theme overrides.

Do not mix arbitrary component-level hardcoded colors with semantic token usage.

## 8. Next.js App Router and MUI integration

Use the correct MUI App Router integration for the installed Next.js version.

Verify:

- Emotion cache behavior.
- Server-side rendering.
- Streaming support.
- Correct style insertion.
- Hydration stability.
- Provider boundaries.
- Theme consistency between server and client rendering.
- CSS import behavior.
- Development and production builds.

Avoid a global client-only root merely to make MUI work.

Keep server components as the default.

Make provider wrappers client components only when required.

Do not suppress hydration warnings to hide integration problems.

Do not introduce unsafe style insertion patterns.

## 9. Reusable UI primitives

Implement a small set of reusable, theme-aware building blocks for future admin screens.

Recommended foundation primitives:

### PageContainer

- Consistent maximum content width.
- Responsive gutters.
- Token-based spacing.
- Suitable for narrow and wide admin layouts.

### PageHeader

- Semantic page heading.
- Optional description.
- Optional action slot.
- Responsive alignment.

### SectionHeader

- Clear subsection title.
- Optional contextual action.
- Correct heading hierarchy.

### StatusIndicator

- Uses existing success, warning, danger, and neutral semantics.
- Never communicates meaning through color alone.
- Accessible text labels.
- Supports known operational states without inventing new backend enums.

### EmptyState

- Consistent spacing.
- Clear message.
- Optional contextual action.
- No fabricated records.

### ErrorState

- Sanitized user-facing message.
- Optional genuine retry action.
- No raw server exception or credentials.

### LoadingState

- Accessible loading indicator or appropriate MUI skeleton.
- Avoid layout shifts where feasible.
- Does not imply successful authorization.

### ConfirmDialog

- Accessible confirmation and cancellation.
- Keyboard support.
- Correct initial focus and close behavior.
- Suitable for future protected operations.

### DataTable foundation

Evaluate whether a thin wrapper over MUI Table is justified now.

If implemented, support:

- Semantic table markup.
- Accessible headers.
- Responsive overflow.
- Empty state.
- Loading state.
- Clear pagination slots.
- No assumptions about frontend-owned business sorting.

Do not add MUI X Data Grid or a commercial table dependency unless an actual approved requirement justifies it.

Do not build full request or enquiry tables yet.

## 10. Responsive foundations

Design the foundation for:

- Mobile.
- Tablet.
- Laptop.
- Desktop.
- Large desktop.

Admin information density can differ from the customer storefront, but the responsive system must follow shared design tokens.

Use:

- Responsive container widths.
- Flexible layouts.
- Readable text.
- Appropriate touch target sizing.
- Overflow-safe tables.
- Keyboard-friendly menus and dialogs.

Do not force desktop-only access.

Avoid fixed-width dashboards that overflow on small screens.

Full admin sidebar, topbar, and navigation behavior belong to Phase 19.4.

Prepare layout primitives without prematurely building that shell.

## 11. Accessibility

Follow `frontend/design-system/ACCESSIBILITY.md`.

At minimum:

- WCAG-aligned color contrast.
- Keyboard navigation.
- Visible focus indicators.
- Semantic headings.
- Landmark regions.
- Accessible form labels.
- Descriptive validation messages.
- Appropriate screen-reader announcements.
- Non-color status communication.
- Reduced-motion respect.
- Zoom and text scaling.
- Safe responsive reflow.

Do not rely on placeholder text as the sole form label.

Do not disable default keyboard behavior in MUI components.

Do not use an inaccessible icon-only control without an accessible label.

Test key primitives with keyboard interactions and accessible queries.

## 12. Clerk integration preparation

Install and configure the existing project's compatible Clerk Next.js SDK.

The admin application must use the same Clerk identity instance as the customer website.

However:

**Phase 19.2 does not implement the complete STAFF/ADMIN admission flow. That is Phase 19.3.**

Prepare only the minimal valid Clerk provider and configuration foundation needed for the app.

Do not:

- Create independent admin sign-up.
- Create public staff registration.
- Create a new identity provider.
- Assign Laravel roles from Clerk metadata.
- Trust a frontend `isAdmin` flag.
- Admit any authenticated Clerk identity to protected admin data.
- Implement `/me` role admission incompletely.
- Create a public dashboard route containing private or mock operational data.

If the SDK requires environment variables to initialize, use explicit validated configuration and appropriate non-sensitive developer setup instructions.

Missing Clerk configuration must not silently result in a fake signed-in ADMIN.

Keep actual keys out of the repository.

Prepare Phase 19.3 integration points without adding speculative role logic.

## 13. Laravel API transport foundation

Create a minimal, typed, reusable API client suitable for the future admin application.

The client must follow:

- Frozen V1 API envelope.
- Canonical error structure.
- Pagination metadata.
- Opaque resource IDs.
- Strict query parameter vocabulary.
- `Cache-Control: private, no-store` for operational/private data.
- Clerk bearer authentication.
- Active local role/permission checks performed by Laravel.
- Safe timeouts and network failures.
- Explicit error handling.

### Server-first approach

Prefer server-side API access for protected initial reads.

Create a transport that accepts a caller-provided current bearer token or a safely scoped token provider.

Do not resolve Clerk tokens at module initialization.

Do not use a shared global authorization header.

Do not cache private operational responses across requests.

Never serialize Clerk JWTs into client component props.

Do not persist tokens in localStorage or sessionStorage.

Prepare a browser-compatible authenticated transport abstraction only if required by the existing architecture.

Phase 19.3 will implement full Clerk session integration and `/me` authorization admission.

### Scope

Do not implement request, enquiry, product, or staff repositories yet unless required for transport tests.

Do not invent endpoint DTO fields.

Use exact frozen schemas when type definitions are needed.

Preserve API transport independence from the customer website.

## 14. Environment configuration

Create `.env.example` with documented variables.

Include only variables that the chosen foundation actually needs.

Likely configuration boundaries include:

- Clerk publishable key.
- Admin canonical origin.
- Server-only Laravel API base URL.
- Optional browser API origin if required.
- Future public Reverb connection configuration.

Do not copy secrets from the customer application's environment files.

Do not commit actual Clerk keys, signing secrets, Laravel credentials, or Reverb secrets.

Do not hardcode production domains.

Production admin origin remains an owner/operations configuration decision.

The local development server should use port `3001`.

If it is unavailable, fail with a clear diagnostic rather than silently switching to another origin that may not be authorized by Clerk or Laravel.

## 15. Independent build and deployment

The admin frontend must support independent commands.

At minimum:

- Development.
- Production build.
- Production start.
- Lint.
- Typecheck.
- Unit/component tests.

Provide scripts consistent with the existing repository.

The app must be independently deployable to Coolify later.

Do not perform deployment in this phase.

Do not assume production domains, certificates, proxy headers, or VPS environment configuration are already complete.

Document future requirements for:

- HTTPS.
- Clerk authorized origins and redirects.
- Laravel CORS allowed origins.
- Laravel Clerk authorized parties.
- Reverb allowed origins.
- WebSocket WSS.
- CSP.
- Private-cache protection.

Do not weaken security through wildcard origins.

## 16. SEO and privacy defaults

The admin dashboard is not a public search destination.

Configure appropriate non-indexing defaults.

Use private, non-indexable application metadata.

Avoid including protected routes in public sitemaps.

Do not publish private records in metadata, previews, or static HTML.

A robots policy alone is not authorization.

Do not rely on `noindex` or robots.txt to protect admin resources.

Full route security belongs to Phase 19.3, and all protected data must remain inaccessible until then.

## 17. Foundation demonstration

Provide a minimal, polished foundation view that demonstrates:

- Correct MUI theme.
- Canonical typography.
- Page container.
- Headings.
- Buttons.
- Form controls.
- Status indicators.
- Empty and error states.
- Responsive behavior.
- Accessible focus styles.

This is a development foundation demonstration, not a functioning operational dashboard.

Do not include:

- Fake sales metrics.
- Fake revenue charts.
- Fabricated customer orders.
- Mock business notification counts.
- Fake STAFF approval flows.
- Hardcoded customer identities.
- A simulated ADMIN login.

If the demonstration is served at a public development route, it must contain no protected content, misleading operational UI, or real private data. Remove or safely isolate it before production deployment.

Prefer automated component tests or a clearly isolated development-only showcase over a permanent public component-gallery route.

## 18. Tests

Implement meaningful coverage for the foundation.

### Application

1. Admin app starts independently.
2. Customer website files/routes remain unchanged.
3. Build and typecheck pass.
4. App Router renders without hydration mismatch.
5. Missing required configuration fails safely.
6. No live admin operational data is accessible through scaffolded routes.

### Design system

7. Shared canonical tokens are consumed correctly.
8. MUI palette maps to approved semantic values.
9. Typography uses approved stacks.
10. Spacing and border semantics remain consistent.
11. Focus styles remain visible.
12. Responsive layouts avoid horizontal overflow.
13. No independently invented brand tokens are introduced.

### Components

14. PageHeader renders correct heading semantics.
15. StatusIndicator includes readable text.
16. EmptyState is accessible.
17. ErrorState handles genuine retry actions.
18. LoadingState exposes accessible status.
19. ConfirmDialog supports keyboard interaction.
20. Form controls expose labels and errors.
21. Table foundation uses semantic headers, if implemented.

### API transport

22. Success envelope is parsed.
23. Pagination metadata is preserved.
24. Canonical validation errors are handled.
25. Unauthorized response is distinguishable from forbidden.
26. Rate-limit and server errors remain distinguishable.
27. Missing bearer authentication fails closed for protected calls.
28. Tokens are not persisted or logged.
29. Private fetches cannot accidentally use shared Next.js caching.
30. Concurrent requests do not share another user's bearer token.

### Security boundaries

31. No public ADMIN registration exists.
32. No CUSTOMER is treated as ADMIN through Clerk metadata.
33. No unauthenticated scaffolded route exposes operational data.
34. Environment examples contain no real secrets.
35. No production origin is hardcoded.

Do not fabricate passing results.

## 19. Quality gates

Run the appropriate commands established by the new package configuration, including:

```bash
cd frontend/admin

npm run lint
npm run typecheck
npm run test
npm run build
```

Use the actual repository package manager if it is not npm.

Inspect dependency compatibility.

Run `git diff --check`.

If available, run existing token validation or consistency checks.

If the app is launched locally, verify that it serves on port `3001`.

Test at narrow and wide viewport sizes.

If changes to shared design-system files are necessary, run relevant customer website checks to ensure no regression.

Do not modify Laravel or Flutter merely to make the admin scaffold pass.

## 20. Documentation

Create:

`frontend/admin/README.md`

and:

`frontend/admin/AGENTS.md`

Following project conventions, document:

- Application purpose.
- Independent deployment boundary.
- Setup instructions.
- Local development port.
- Required environment variables.
- Package scripts.
- Folder structure.
- Canonical design-system authority.
- MUI theme implementation.
- API transport usage.
- Clerk/Laravel security boundaries.
- Rules for adding future screens.
- Accessibility requirements.
- Test commands.
- Phase exclusions.

Create:

`phases/phase-19.2-completion.md`

Record:

1. Files added.
2. Dependency versions.
3. App architecture.
4. Theme/token strategy.
5. Component primitives created.
6. API client foundation.
7. Clerk preparation status.
8. Environment configuration.
9. Build and test results.
10. Security observations.
11. Known limitations.
12. Phase 19.3 handoff.

Do not rewrite completed phase history.

## 21. Scope exclusions

Do not implement:

- Complete STAFF/ADMIN authorization gate.
- Admin signup or account invitations.
- Protected dashboard shell.
- Operational request tables.
- Enquiry workflows.
- Product creation/editing forms.
- Category management workflows.
- Inventory adjustments.
- Staff CRUD or approvals.
- Customer directory.
- Audit log UI.
- Reverb subscription client.
- Notification inbox functionality.
- Notification preferences.
- Customer push notifications.
- Checkout, orders, payments or deliveries.
- New Laravel endpoints.
- New database migrations.
- Production Coolify deployment.

These belong to later phases or require separate approval.

## 22. Definition of Done

Phase 19.2 is PASS when:

1. `frontend/admin` exists as an independent Next.js application.
2. The application runs on local port `3001`.
3. The project uses compatible Next.js, React, TypeScript and MUI versions.
4. Canonical shared design tokens drive the admin theme.
5. MUI App Router SSR integration is correct.
6. Reusable accessible primitives are implemented and tested.
7. Typed Laravel transport respects the frozen contract.
8. Clerk SDK integration is prepared without bypassing authorization.
9. No operational data is publicly accessible.
10. Responsive and accessibility foundations are verified.
11. Lint, typecheck, tests and production build pass.
12. Customer website remains unaffected.
13. Documentation is complete.
14. No Laravel API or database contract change is introduced.

## 23. Final agent response

Return:

**PHASE 19.2 — PASS / PARTIAL / FAIL**

Include:

- Files changed.
- Application directory.
- Framework and dependency versions.
- Local development URL.
- Theme/token implementation.
- Reusable primitives.
- API transport design.
- Clerk integration preparation.
- Security boundary status.
- Automated validation results.
- Remaining issues.
- Exact readiness for Phase 19.3.

**Proceed with the implementation now. Do not advance into Phase 19.3 without owner approval.**