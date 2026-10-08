# Phase 15.10 — Enquiry/Contact UI
## Group O — Final Implementation Phase

### Objective

Implement the complete Enquiry/Contact user interface for the furniture e-commerce website.

Customers and visitors must be able to contact the business through a clear, accessible, responsive enquiry form.

The implementation must integrate with the existing Laravel enquiry API while preserving the frozen Version 1 API contract.

This is the final phase of Group O. Do not expand its scope into unrelated functionality.

### 1. Mandatory Repository Inspection

Before implementation:

1. Read `AGENTS.md`, the current Group O phase documentation, and the frozen API contract.
2. Review Phase 15.9's implementation and verification results.
3. Inspect the Laravel enquiry endpoints, request validation, authentication policy, rate limiting, and error contracts.
4. Inspect the existing Next.js App Router structure, shared API client, Clerk integration, design tokens, MUI theme, reusable components, and navigation.
5. Identify existing contact-related routes, components, or placeholders.
6. Check whether the enquiry API supports anonymous and authenticated submissions.
7. Inspect the canonical request and response examples.

Do not assume the enquiry endpoint, field names, validation limits, or response shape.

Use the repository as the source of truth.

If an essential contract is missing or contradictory, report the issue rather than inventing a replacement.

### 2. Enquiry/Contact Page

Implement the contact experience using the established routing conventions.

Prefer `/contact` as the public-facing route unless the project already defines another canonical route.

The page should provide:

- A clear heading and brief introduction.
- A well-structured enquiry form.
- Existing verified business contact information, where available.
- Appropriate submission, loading, success, and error states.
- A responsive layout for desktop, tablet, and mobile.

Do not invent a business address, telephone number, email address, operating hours, or social media account.

Use the existing Nike-inspired design system, MUI theme, design tokens, and reusable primitives.

Do not introduce new visual tokens, arbitrary colors, spacing values, typography scales, or competing component styles.

### 3. Enquiry Form

Build the form strictly from the frozen enquiry API contract.

Fields may include name, email, phone, subject, and message only where supported by the backend contract.

Requirements:

1. Use the exact API field names and permitted values.
2. Apply client-side validation consistent with backend constraints.
3. Clearly identify required and optional fields.
4. Provide accessible labels and field-level error messages.
5. Preserve entered values after recoverable validation or network errors.
6. Disable duplicate submissions while a request is pending.
7. Support keyboard navigation and screen readers.
8. Never silently discard user input.
9. Do not add file attachments unless explicitly supported by the enquiry contract.

The Laravel API remains authoritative for validation.

### 4. Authentication and API Integration

Use the existing approved API transport.

Do not introduce:

- A second API client.
- A Next.js proxy.
- An unapproved Server Action submission path.
- A new authentication mechanism.
- Direct database access from the frontend.

If the backend supports anonymous enquiries, allow visitors to submit without signing in.

If authenticated enquiries are supported, reuse the existing Clerk bearer-token integration.

Do not require authentication unless the frozen API contract requires it.

Do not assume that authenticated profile information automatically overrides submitted contact fields.

Follow the existing API conventions for request IDs, validation errors, rate limiting, and response handling.

### 5. Submission Lifecycle

Implement a predictable submission lifecycle:

**Idle:** Form is editable and ready.

**Submitting:** Show progress and prevent duplicate submissions.

**Validation error:** Display the backend's field-level validation errors without clearing the form.

**Rate limited:** Display an appropriate message and respect `Retry-After` when provided.

**Network failure:** Display a recoverable error and preserve entered values.

**Success:** Show a clear acknowledgement based on the actual API response.

**Unknown outcome:** If the request may have reached the server but the response was lost, do not automatically resubmit it.

Do not implement automatic retries for non-idempotent enquiry creation.

Do not promise response times, email notifications, or reference numbers unless those behaviors are supported by the backend.

### 6. Navigation and Discoverability

Integrate the contact page into existing website navigation.

Inspect the current header, footer, mobile navigation, and related links before changing them.

Requirements:

- Avoid duplicate navigation entries.
- Preserve existing navigation hierarchy.
- Use the canonical contact route consistently.
- Verify that all contact links resolve correctly.
- Preserve responsive navigation behavior.

Do not redesign unrelated navigation components.

### 7. SEO, Accessibility, and Responsiveness

Follow existing Next.js metadata and SEO conventions.

Implement appropriate page title, description, and canonical metadata.

Do not introduce fabricated structured business information.

Ensure:

- Responsive behavior across established breakpoints.
- WCAG-aligned form labels, error announcements, and focus handling.
- Accessible success and failure feedback.
- Proper loading and disabled states.
- No unexpected layout shifts.
- No unnecessary client-side rendering of static content.

Prefer a server-first page structure, with client components limited to functionality requiring browser interaction.

### 8. Security and Privacy

Enforce the existing application security boundaries.

- Do not expose Clerk secrets or private environment variables.
- Do not log enquiry contents, authentication tokens, or unnecessary personal information.
- Do not introduce public access to private API resources.
- Do not bypass backend rate limiting or validation.
- Do not introduce CAPTCHA or third-party anti-spam services without approval.
- Do not expose internal error traces to users.
- Do not persist enquiry form data in browser storage unless already authorized by project requirements.

Treat all user-entered content as untrusted.

### 9. Automated Verification

Add focused tests following the repository's established test conventions.

Cover:

1. Public page rendering.
2. Required-field validation.
3. Optional-field behavior.
4. Successful anonymous submission, where supported.
5. Authenticated submission, where supported.
6. Backend validation errors.
7. Rate limiting and `Retry-After`.
8. Network failures.
9. Duplicate-submission prevention.
10. Success acknowledgement.
11. Accessibility and keyboard navigation.
12. Responsive layouts.
13. Contact navigation links.
14. Existing API client compatibility.

Run the relevant existing regression suites, including type checking, linting, and production build.

Do not modify unrelated tests merely to obtain a passing result.

### 10. Browser Verification — Chrome DevTools MCP

Use the configured local `chrome-devtools` MCP server to test the implementation in a real browser.

Verify:

- Contact page navigation and rendering.
- Form interaction and validation.
- Anonymous submission.
- Authenticated submission, where supported.
- Actual network requests to Laravel.
- Successful response handling.
- Error and rate-limit behavior.
- Desktop and mobile layouts.
- Browser console errors.
- Accessibility-related interaction issues.

Use the actual locally running Laravel backend when feasible.

Clean up disposable test records and artifacts.

Do not claim browser verification succeeded unless the relevant browser checks actually ran.

### 11. Production Gate Preservation

Phase 15.9's production transport gate remains BLOCKED.

Do not treat successful local enquiry submission as production readiness.

Preserve the outstanding requirements for:

- Deployed HTTPS API origin.
- Exact production CORS origins.
- Production Clerk configuration.
- Reverse-proxy request limits.
- Production browser transport verification.

These requirements must remain visible in Group O's final status.

Do not reopen or modify the Phase 15.9 implementation unless a genuine regression is discovered.

### 12. Group O Closure

After implementing Phase 15.10:

1. Review all Group O phases against their documented acceptance criteria.
2. Confirm that local implementation and verification are complete for each applicable phase.
3. Identify any unresolved defects, deferred decisions, or production-only verification requirements.
4. Update the existing Group O phase documentation.
5. Record the tests executed and their actual results.
6. Clearly separate implementation completion from production readiness.
7. Preserve unrelated working-tree changes.

**Group O may be marked LOCAL IMPLEMENTATION COMPLETE only if every required local acceptance criterion passes.**

Do not mark Group O fully production-ready while the Phase 15.9 production transport gate remains blocked.

### 13. Completion Report

Provide:

- Files created or modified.
- Enquiry API contract used.
- Routes and navigation integrated.
- Anonymous/authenticated behavior.
- Validation and submission states.
- Automated test results.
- Chrome DevTools MCP browser verification results.
- Outstanding issues.
- Final Phase 15.10 status.
- Final Group O status.

### Definition of Done

Phase 15.10 is locally complete when the public contact experience is functional, accessible, responsive, integrated with the frozen Laravel enquiry contract, verified through automated tests and real-browser testing, and free of unresolved required local implementation blockers.

Group O closure must preserve all outstanding production release gates.

---

## Implementation Status — 2026-10-08

### Phase 15.9 — Furniture Request UI

- Local implementation and transport verification: **PASS**.
- Production browser transport and release: **BLOCKED** pending a deployed HTTPS API origin, exact production CORS origins, production Clerk configuration, reverse-proxy body limit verification, and deployed-browser verification.

### Phase 15.10 — Enquiry/Contact UI

- Implemented `/contact` as a server-first, `noindex, follow` public contact page with canonical metadata and existing service-navigation links.
- `ENQ-001` uses the frozen `POST /api/v1/enquiries` contract: anonymous submission requires `name`, at least one contact method, `subject` (5-200), and plain-text `message` (10-5000); optional category and one private inline attachment follow the closed backend contract.
- Browser submission uses the existing API client with the approved public `NEXT_PUBLIC_API_BASE_URL` origin. It sends an ephemeral Clerk bearer only while a signed-in visitor submits; no proxy, Server Action, second client, token persistence, or attachment upload capability is introduced.
- Local browser verification: desktop and 390px mobile rendering passed; empty-form validation exposes labelled field errors; anonymous JSON submission preflight returned `204` and `POST /api/v1/enquiries` returned `201` with the OPEN acknowledgement. The disposable browser enquiry was deleted after verification.
- Automated verification: focused enquiry tests (6); Laravel ENQ contract suites (155 tests, 698 assertions); all web regression scripts; `npm run typecheck`; `npm run lint -- --max-warnings=0`; `npm run build`.
- Authenticated API behavior is covered by the Laravel ENQ suites. Authenticated browser submission is not yet demonstrated because no disposable Clerk browser session is available.

### Group O Status

- **LOCAL IMPLEMENTATION COMPLETE: BLOCKED** until the authenticated `ENQ-001` browser submission is verified with a disposable Clerk customer session.
- **PRODUCTION READY: BLOCKED**. Phase 15.9/15.10 require a deployed HTTPS Laravel origin, exact production CORS origins, production Clerk issuer/keys/authorized parties, reverse-proxy request bodies of at least 6 MiB, and deployed-browser anonymous/authenticated JSON plus 5 MiB multipart verification.

Proceed with repository inspection first, then implement Phase 15.10 according to existing architecture and design conventions. Do not invent API contracts, change frozen behavior, or expand scope without authorization.
