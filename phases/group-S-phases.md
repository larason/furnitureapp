# SL Furnitures — Phase 19.5: Dashboard Overview

**Group:** S — Admin Dashboard Frontend  
**Phase:** 19.5 — Dashboard Overview  
**Application:** `frontend/admin`  
**Framework:** Existing Next.js App Router + TypeScript + MUI  
**Authentication:** Existing Clerk integration  
**Authorization:** Existing Laravel `/api/v1/me` admission and route guards  
**Design authority:** `frontend/design-system/tokens.css`  
**Local origin:** `http://localhost:3001`  
**Status:** APPROVED FOR IMPLEMENTATION

## 1. Objective

Implement the finished, production-quality Overview landing page for the SL Furnitures Admin Dashboard.

Phase 19.4 established the dashboard shell and a preliminary Overview page. This phase must refine that page into a coherent, useful landing experience that helps each authorized staff member or administrator understand their workspace and navigate to relevant areas.

The overview must be honest about the current implementation state. The application is being delivered in phases, and several linked destinations remain placeholders.

**This phase is a navigation-first overview, not an analytics dashboard.**

Do not introduce a dashboard-summary API, aggregate business metrics from unrelated endpoints, or fabricate data to make the page look populated.

## 2. Mandatory audit before implementation

Inspect the current repository and read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/admin/AGENTS.md`.
- `frontend/admin/README.md`.
- `frontend/admin/app/(protected)/page.tsx`.
- `frontend/admin/app/(protected)/layout.tsx`.
- `frontend/admin/components/layout/admin-overview-body.tsx`.
- `frontend/admin/components/layout/admin-route-placeholder.tsx`.
- `frontend/admin/lib/navigation/admin-navigation.ts`.
- `frontend/admin/lib/navigation/admin-route-paths.ts`.
- `frontend/admin/lib/auth/operational-identity.ts`.
- `frontend/admin/lib/auth/server-operational-identity.ts`.
- `frontend/admin/lib/auth/route-guard.ts`.
- Existing shared UI components and the MUI theme.
- `frontend/design-system/tokens.css`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `frontend/design-system/COMPONENTS.md`.
- `phases/phase-19.1-architecture-api-audit.md`.
- `phases/phase-19.2-completion.md`.
- `phases/phase-19.3-completion.md`.
- `phases/phase-19.4-completion.md`.
- `phases/group-S-phases.md`.

Verify the actual filenames and exported APIs rather than assuming this list exactly matches the current tree.

Before editing, report a short implementation plan and summarize which existing overview elements will be retained, improved, or removed.

Do not begin by scaffolding another application, redesigning the shell, or replacing working authentication infrastructure.

## 3. Scope

Implement the Overview at the existing protected `/` route.

The page should provide:

1. A clear welcome heading and concise introduction.
2. A well-organized workspace navigation area.
3. Permission-filtered shortcuts generated from the existing navigation registry.
4. A clear distinction between the dashboard shell being available and business modules still being implemented.
5. Useful, truthful descriptions of accessible modules and their current implementation status.
6. A restrained identity/access summary where it improves the experience.
7. Responsive, accessible page composition consistent with the existing shell.
8. Automated tests and updated phase documentation.

Reuse the existing `AdminShell`, navigation registry, sanitized identity provider, route guards, `PageContainer`, `PageHeader`, `SectionHeader`, and other shared primitives wherever appropriate.

Do not create a second navigation configuration or another authorization layer.

## 4. Security and route protection

Preserve the Phase 19.3 and Phase 19.4 security boundaries.

The Overview must remain behind the existing protected route layout and server-side admission mechanism.

- Keep the existing Overview route and URL.
- Preserve the existing `requireAdminRouteAccess(adminRoute(...))` guard pattern, using the actual verified API.
- Resolve identity and effective permissions through the established Laravel-backed admission flow.
- Do not trust Clerk metadata as the authoritative business role.
- Do not use a client-side role or `isAdmin` flag as authorization.
- Do not allow ADMIN to bypass an exact permission requirement.
- Do not expose bearer tokens, raw `/me` responses, internal permission payloads, or sensitive server-side details.
- Do not weaken any existing denied, invalid-session, inactive-account, or unavailable-service state.

Overview shortcuts are presentation and navigation only. The destination route must retain its own server-side authorization guard.

Do not add public overview content containing operational identity information.

## 5. Design and content direction

Use the existing MUI theme and canonical shared design tokens. Follow the current dashboard shell's visual language.

The Overview should feel like an intentional operational workspace, not a generic analytics template.

Prioritize:

- Clear visual hierarchy.
- Concise, meaningful copy.
- Readable section headings.
- Consistent spacing and alignment.
- Restrained use of Paper/Card surfaces.
- Clear distinction between navigation and status information.
- Responsive layouts that do not rely on hover.
- Accessible links and keyboard focus.
- The existing brand and typography system.

Avoid filling the page with excessive cards, decorative charts, gradients, meaningless icons, or redundant panels.

Do not copy token values into a new file, introduce a second palette, or install another component library.

## 6. Recommended Overview structure

Use judgment and existing components to deliver the clearest page. The following content hierarchy is the target.

### A. Welcome and context

Show a clear heading such as “Welcome back, [approved display name]” using the existing sanitized identity helper.

- Use the approved fallback if the name is absent.
- Never invent a name, email, role, or profile image.
- Provide a short explanation of the dashboard's purpose.
- Do not make claims that request handling, enquiry handling, catalog editing, or other business workflows are already available when their routes remain placeholders.

Keep the introduction short and useful.

### B. Workspace navigation

Create a clean, easy-to-scan workspace section using the existing typed navigation registry.

It may group accessible destinations according to the existing navigation groups:

- Operations.
- Catalog.
- Administration.

Include Overview only if doing so improves navigation rather than linking users redundantly to their current page.

Each destination must obtain its label, description, path, icon, permission requirement, role restriction, and availability information from the existing approved configuration or a small, clearly defined shared metadata source.

Requirements:

- Reuse the existing permission-filtering logic.
- Show only destinations allowed by the Laravel-resolved effective permissions and existing route policy.
- Do not reproduce permission checks in a separate array.
- Do not invent new permissions.
- Do not reveal restricted destinations as shortcuts to users who cannot access them.
- Ensure each shortcut points to the correct existing route.
- Make the entire interactive area accessible if card-style links are used.
- Preserve semantic link behavior and normal browser navigation.

The overview must remain usable for a STAFF member with a limited permission set. Do not assume all staff members have the same capabilities or that every ADMIN has every permission.

### C. Accurate implementation status

The current application contains real dashboard navigation and protected route placeholders, but those placeholders do not perform business operations.

Make this distinction explicit.

Use concise wording to communicate that:

- The authenticated dashboard shell and navigation are available.
- Business workflows are being implemented in scheduled phases.
- A link to a section does not mean that its business workflow is functional.
- No live operational summary is displayed because there is no approved dashboard-summary contract.

Use the actual roadmap to determine the phase or status of a feature. Do not hardcode a phase number without verifying it.

Where useful, show a neutral status such as “Planned” or “Not yet implemented.” Avoid status labels that could be mistaken for real operational state.

Do not turn every status into a large colored badge. Use restrained text or a subtle semantic status treatment.

Do not display an “available now” badge on a business module whose page is still a placeholder.

### D. Identity/access information

Review the existing “Your access” panel.

Retain it only in a form that provides real value and does not duplicate the account menu unnecessarily.

If included, use sanitized, approved identity fields and the established local role label. Do not display the raw permission list or any token.

Keep the explanation of Laravel's authorization boundary concise. Avoid making the Overview a technical documentation page.

## 7. No fabricated analytics or business data

There is no approved aggregate dashboard endpoint.

Therefore, do not add:

- Revenue or sales totals.
- Order counts or order status totals.
- Conversion rates.
- Customer totals.
- Inventory quantities or low-stock counts.
- Pending-request or enquiry counts.
- Unread-notification counts.
- Recent activity timelines.
- Charts, trends, or comparisons.
- “Today,” “this week,” or other time-based summaries that imply real data.
- Mock records or sample operational data presented as live.
- Placeholder numeric values such as `0`, `—`, or `N/A` arranged to resemble real KPI cards.

Do not fetch multiple unrelated endpoints and aggregate their responses in the browser or server to simulate a dashboard API.

Do not add a new endpoint, database migration, backend query, permission, or API contract change.

Do not create a Reverb connection, notification subscription, or notification polling loop.

The Overview should make **zero additional business-data API calls**. The existing protected admission flow's `/api/v1/me` request is expected and must remain intact.

If a genuine operational summary is considered necessary, document it as a future, separately approved API/contract decision. Do not implement it in Phase 19.5.

## 8. API, caching, and rendering boundaries

Keep the Overview primarily server-rendered wherever practical.

- Reuse the existing server-side identity and permission data already available through the established architecture.
- Do not add client-side data fetching for static roadmap information.
- Do not add new route handlers or proxy endpoints.
- Do not serialize a Clerk bearer token across the Server/Client Component boundary.
- Keep client components limited to genuine interaction.
- Do not pass non-serializable function props from a Server Component into a Client Component.
- Follow the Phase 19.4 regression lesson regarding `component={NextLink}` and MUI components across the React Server Component boundary.
- Preserve private/no-store behavior for authenticated requests.
- Do not introduce shared caches containing authenticated identity or permission-specific content.

If the page needs a client-side child, keep the boundary small and pass only serializable, approved presentation data.

## 9. Navigation and accessibility

Follow `frontend/design-system/ACCESSIBILITY.md` and existing admin shell conventions.

At minimum:

- Use logical heading levels.
- Use semantic links for navigation.
- Provide accessible names for icon-only controls.
- Maintain visible keyboard focus.
- Do not communicate implementation status through color alone.
- Ensure link targets and labels make sense when read by assistive technology.
- Avoid nested interactive elements.
- Keep text contrast and focus treatment consistent with the design system.
- Ensure the content works with keyboard-only navigation.
- Preserve the single main landmark supplied by the dashboard shell.
- Avoid adding a second page-level `main` element.
- Respect reduced-motion preferences already implemented in the shell.

Do not introduce custom interaction behavior when a normal link or existing shared component is sufficient.

## 10. Responsive behavior

Verify the Overview at minimum at:

- 360 px.
- 390 px.
- 768 px.
- 1024 px.
- 1440 px.

Check:

- Heading wrapping and truncation.
- Shortcut layout at narrow widths.
- Long navigation labels.
- Long display names.
- Adequate spacing and readable line lengths.
- No horizontal overflow.
- Usable touch targets.
- Correct layout alongside the existing desktop sidebar.
- Correct layout with the mobile navigation drawer.
- Keyboard focus visibility.
- No hydration errors or console errors caused by the Overview.

Reuse existing breakpoints and layout conventions. Do not redesign the global shell in this phase.

If a live authenticated browser session is available, test the actual `/` route, not only a synthetic component harness. If live STAFF credentials are unavailable, do not create or alter an identity to bypass the limitation; use appropriately scoped automated tests and document the missing live verification.

## 11. Automated tests

Use the existing test framework and conventions in `frontend/admin`.

Add or extend tests for the Overview.

### Content and correctness

1. The Overview renders the approved welcome heading and fallback behavior.
2. The page has a clear, logical heading hierarchy.
3. Workspace shortcuts use the centralized navigation configuration.
4. Only permission-eligible destinations appear.
5. The page remains useful with a small permission set.
6. Missing or malformed permissions fail closed.
7. The page distinguishes dashboard-shell availability from unfinished business workflows.
8. Any displayed phase/status metadata matches the approved roadmap.
9. No fake analytics, sample records, fabricated counts, or unread badges appear.
10. No new business API calls are introduced by the Overview.

### Authorization and identity

11. The Overview remains protected by the existing admission boundary.
12. Unauthorized users cannot access private Overview content.
13. ADMIN does not receive a permission bypass.
14. The page displays only approved, sanitized identity information.
15. No Clerk token or raw internal API response is rendered.

### Navigation and rendering

16. Shortcuts point to the correct existing routes.
17. Shortcut destinations retain their own route guards.
18. Active navigation behavior remains correct.
19. The page preserves the shell's single `main` landmark.
20. Server/Client Component boundaries remain valid.
21. No hydration or serialization regression is introduced.

### Responsive and accessibility behavior

22. Shortcut layouts remain usable at the required viewport sizes.
23. Long labels and names do not cause horizontal overflow.
24. Links have meaningful accessible names.
25. Keyboard focus is visible.
26. No nested interactive elements are introduced.

Test through the project's existing tools. Do not add a large test dependency solely for this page.

## 12. Quality gates

Run from `frontend/admin` using the repository's established package manager:

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

Re-run the relevant Phase 19.3 and Phase 19.4 authorization, navigation, and Server/Client Component boundary tests.

Verify the production build and protected route behavior. If the app is running locally, test the actual Overview page at `http://localhost:3001/`.

Do not report a check as passed unless it actually ran and passed. Separate automated tests, browser checks, and unavailable live identity tests in the final report.

## 13. Documentation and completion report

Update `frontend/admin/README.md` or `frontend/admin/AGENTS.md` only where the new Overview behavior creates a meaningful documentation change.

Create:

`phases/phase-19.5-completion.md`

Include:

1. Phase status: PASS / PARTIAL / FAIL.
2. Files changed.
3. Overview layout and content hierarchy.
4. Navigation registry and permission-filtering reuse.
5. Implementation-status presentation and roadmap source.
6. Identity display and authorization-boundary preservation.
7. Confirmation that no aggregate API or additional business-data request was introduced.
8. Confirmation that no fabricated analytics or operational records are shown.
9. Accessibility and responsive test results.
10. Authentication and route-guard regression results.
11. Lint, typecheck, test, build, and diff-check results.
12. Live browser verification and any unavailable identity tests.
13. Known limitations.
14. Handoff to Phase 19.6.

Do not rewrite previous completion reports or retroactively change their status.

## 14. Explicit exclusions

Do not implement:

- Dashboard analytics or KPI cards.
- A dashboard-summary API.
- Made-to-order request management.
- Enquiry management.
- Product or category CRUD.
- Inventory operations.
- Customer or staff management.
- Audit-log viewing.
- A functional notification inbox.
- Reverb subscriptions or polling.
- New roles or permissions.
- New Laravel endpoints or database migrations.
- Changes to the frozen API contract.
- Checkout, orders, payments, or delivery workflows.
- Production deployment configuration.
- Unrelated dependency upgrades or redesign of the global dashboard shell.

Those responsibilities remain in their approved phases.

## 15. Definition of Done

Phase 19.5 is **PASS** when:

1. The Overview is polished and consistent with the existing dashboard shell.
2. Its content is truthful and useful without operational metrics.
3. Shortcuts are generated from the existing navigation registry and permission-filtering logic.
4. Users see only destinations permitted by the established policy.
5. Business placeholders are clearly distinguished from functional modules.
6. Identity display remains sanitized and private.
7. The existing Laravel-backed admission and route guards remain intact.
8. No new business-data API calls, aggregate endpoint, or fabricated data are introduced.
9. The page is accessible and responsive at the required viewport widths.
10. Automated tests and all required quality gates pass.
11. Phase 19.3 and 19.4 security and rendering regressions are not introduced.
12. The completion report accurately documents results and limitations.

If a requirement cannot be verified, report it honestly as a limitation. Do not weaken authorization, create fake data, or claim success without evidence.

**Implement Phase 19.5 now. Do not proceed to Phase 19.6 without owner approval.**