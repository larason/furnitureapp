# Phase 15.11 — Legal, Privacy & About Pages
## Group O — Final Website Completion Phase

### Objective

Implement three polished, responsive, accessible, and production-structured informational pages for the furniture e-commerce website:

1. Privacy Policy
2. Terms of Service
3. About Us

Integrate these pages into the existing footer and navigation architecture.

This phase extends Group O. Group P must not begin until Phase 15.11's required local implementation criteria have passed.

The implementation must strictly follow the project's established design system and preserve all existing architecture and API contracts.

---

## 1. Mandatory Repository Inspection

Before making changes, inspect:

- Root `AGENTS.md` and relevant nested agent instructions.
- `phases/group-O-phases.md` and existing Phase 15.10 closure notes.
- The established Next.js App Router structure.
- Existing footer and navigation components.
- MUI theme implementation.
- Existing design-system tokens and component primitives.
- The Nike-inspired design-system source files, where retained.
- Current responsive breakpoints and typography conventions.
- Existing SEO, metadata, accessibility, and testing patterns.
- Project documentation containing verified business details, customer policies, payment terms, delivery rules, and data-handling behavior.

Do not begin implementation until the relevant design-system implementation and content sources have been identified.

**Important:** The existing implemented MUI theme and approved project tokens are authoritative. The original Nike reference system must not override intentional project-specific adaptations.

## 2. Strict Design-System Enforcement

This is a mandatory requirement, not a recommendation.

All three pages must use the existing approved design system.

### 2.1 Token-only styling

Use existing tokens for:

- Colors and semantic color roles.
- Typography families, sizes, weights, and line heights.
- Spacing and layout gaps.
- Container widths and responsive breakpoints.
- Border colors, widths, and radii.
- Shadows and elevation.
- Interactive states.
- Focus indicators.
- Component dimensions.

Do not introduce arbitrary values or new design tokens.

### 2.2 Forbidden styling practices

Do not:

- Hardcode hex, RGB, HSL, or other color values.
- Use arbitrary pixel-based spacing or typography.
- Invent new visual effects or decorative gradients.
- Create new button variants without approval.
- Introduce new font families.
- Override MUI tokens with local CSS values.
- Copy IKEA, Nike, or another website's visual elements directly.
- Create page-specific design systems.
- Use generic AI-generated marketing layouts that conflict with the existing website.

Avoid excessive cards, unnecessary rounded containers, decorative icons, and visually unrelated sections.

### 2.3 Reuse existing primitives

Prefer existing components for:

- Page containers.
- Headings and text.
- Breadcrumbs.
- Links and buttons.
- Section layouts.
- Dividers.
- Responsive content grids.
- Navigation elements.

If a genuinely reusable component is missing, implement it using existing tokens and document why it was necessary.

Do not duplicate existing primitives.

### 2.4 Design-system compliance review

Before declaring completion:

1. Audit every new component for token compliance.
2. Check for hardcoded colors, spacing, typography, and breakpoints.
3. Confirm all responsive behavior follows existing MUI conventions.
4. Run existing theme and design-system tests.
5. Inspect the rendered pages with Chrome DevTools MCP.

Any design-system violation is a required defect.

---

## 3. Privacy Policy Page

**Route:** `/privacy-policy`

Create a clear, readable Privacy Policy page.

Use the project's actual architecture and verified data-handling behavior as the factual basis.

Review the existing implementation of:

- Clerk authentication.
- Customer accounts and profiles.
- Cart and checkout.
- Orders and payments.
- Furniture requests.
- General enquiries.
- Cookies and guest-cart credentials.
- Private attachment handling.
- Backend logging and security.
- Any analytics, messaging, or third-party integrations actually used.

The document should address, where applicable:

1. Information collected.
2. Purposes of processing.
3. Authentication and account information.
4. Orders, payments, and delivery information.
5. Furniture requests and enquiries.
6. Cookies and session technologies.
7. Third-party service providers.
8. Data security.
9. Retention and deletion.
10. Customer privacy rights.
11. Policy updates.
12. Contact information for privacy enquiries.

### Legal content safeguards

Do not invent:

- A registered company name.
- A physical business address.
- A privacy contact email.
- Specific data-retention periods.
- Regulatory registrations.
- Legal guarantees.
- Third-party processing arrangements.
- Data-transfer mechanisms.

Research the applicable legal jurisdiction from project documentation.

Do not assume the business is legally registered in a particular jurisdiction merely because of its currency or target market.

Draft content must accurately describe verified behavior and clearly identify missing legal/business decisions.

If substantive legal information is unavailable, mark the policy as **DRAFT — LEGAL REVIEW REQUIRED** in project documentation and prevent unapproved legal text from being presented as a finalized binding policy.

Do not silently publish fabricated or incomplete legal commitments.

---

## 4. Terms of Service Page

**Route:** `/terms-of-service`

Implement a structured Terms of Service page aligned with the actual furniture e-commerce business model.

Inspect the frozen contracts and existing documented policies before drafting.

Address relevant subjects such as:

1. Introduction and acceptance of terms.
2. Account registration and customer responsibilities.
3. Product descriptions and availability.
4. Product variants and pricing.
5. In-stock purchases.
6. Made-to-order furniture requests.
7. Order placement and confirmation.
8. Payment handling.
9. Delivery and self-pickup.
10. Order status and fulfillment.
11. Cancellations, returns, and refunds.
12. Product warranties, where applicable.
13. Intellectual property.
14. Acceptable use.
15. Liability and dispute handling.
16. Updates to the terms.
17. Contact information.

### Business-rule accuracy

Preserve documented Version 1 business rules.

For example:

- Browsing is available without registration.
- Checkout requires an authenticated customer account.
- Furniture requests may be submitted anonymously.
- Delivery and self-pickup follow existing backend contracts.
- Do not imply that made-to-order enquiries automatically create purchases.
- Do not promise payment methods or refunds that have not been implemented or approved.

Do not invent a cancellation window, return period, warranty duration, refund entitlement, or governing-law clause.

Where business or legal approval is required, identify the decision explicitly.

Legal review is required before the Terms of Service are treated as production-approved.

---

## 5. About Us Page

**Route:** `/about`

Create an attractive, credible About Us page consistent with the furniture brand's established visual identity.

The page should communicate the business clearly without unnecessary marketing exaggeration.

Suggested content structure:

### Introduction

Explain what the furniture business offers.

### What We Offer

Describe the supported business model:

- Ready-to-purchase furniture.
- Made-to-order furniture enquiries.
- Product customization where supported.
- Delivery or self-pickup according to existing policies.

### Our Approach

Explain the value of furniture quality, functionality, and thoughtful design without making unverified manufacturing or sourcing claims.

### Explore Our Furniture

Provide a contextual link to the existing product catalog.

### Request Custom Furniture

Link to the existing `/furniture-requests` experience.

### Contact Us

Link to `/contact`.

### Content requirements

Do not invent:

- Founding dates.
- Founder identities.
- Employee counts.
- Factory locations.
- Years of experience.
- Customer testimonials.
- Awards or certifications.
- Sustainability claims.
- Manufacturing capabilities not supported by the business documentation.

Use verified brand assets and existing product imagery when appropriate.

Do not introduce stock imagery or unrelated illustrations merely to fill space.

The page should feel like a natural extension of the existing storefront.

---

## 6. Shared Informational Page Architecture

Use a consistent page structure for the Privacy Policy and Terms of Service.

Prefer server-rendered content using Next.js App Router.

Requirements:

- Semantic heading hierarchy.
- Readable content width.
- Consistent section spacing.
- Clear document titles.
- Effective-date information only when verified or approved.
- Accessible links.
- Mobile-friendly text layout.
- No unnecessary client-side JavaScript.

If the project already has a reusable editorial or content layout, reuse it.

Do not introduce a CMS, Markdown rendering framework, or additional dependency unless justified by an existing requirement.

Keep content maintainable and separate from unnecessarily complex presentation logic.

---

## 7. Footer Integration

Inspect the existing footer architecture.

Add or activate the following links:

| Label | Destination |
|---|---|
| About Us | `/about` |
| Privacy Policy | `/privacy-policy` |
| Terms of Service | `/terms-of-service` |
| Contact Us | `/contact` |

Follow existing footer grouping, typography, spacing, hover, focus, and responsive conventions.

Do not redesign the footer.

Do not duplicate links already present.

Verify the footer on desktop, tablet, and mobile.

All links must use the existing navigation conventions and resolve without errors.

---

## 8. SEO and Metadata

Follow the existing Next.js metadata conventions.

Each page must have:

- A unique title.
- An appropriate description.
- A canonical URL.
- Correct semantic HTML.
- Appropriate indexing directives.

The About page may be indexable when its content is approved and accurate.

Privacy Policy and Terms of Service should use the indexing policy appropriate to the project's launch status.

Do not automatically copy the `/contact` page's `noindex, follow` behavior.

However, do not expose unapproved draft legal documents as finalized production policies.

If the website is not yet ready for public indexing, preserve the existing site-wide indexing restrictions.

---

## 9. Accessibility and Responsive Requirements

Follow established accessibility and responsive design conventions.

Verify:

- Semantic landmarks.
- Correct heading order.
- Keyboard-accessible navigation.
- Visible focus indicators.
- Sufficient text contrast.
- Readable line lengths.
- Proper text wrapping.
- No horizontal overflow.
- Mobile-friendly spacing.
- Support for browser zoom and text scaling.

Do not suppress accessibility warnings.

Investigate any failures and distinguish pre-existing issues from newly introduced defects.

---

## 10. Automated Testing

Add focused tests for the three new routes.

Test:

1. Successful route rendering.
2. Correct metadata.
3. Canonical URLs.
4. Expected page headings.
5. Semantic content structure.
6. Footer navigation.
7. Internal links.
8. Responsive behavior.
9. Accessibility.
10. Design-system token compliance.

Run existing regression suites where applicable:

- Routing and links.
- Layout.
- Responsive design.
- Theme.
- Accessibility.
- SEO.
- Performance.
- TypeScript type checking.
- ESLint.
- Production build.

Do not weaken tests or alter unrelated expectations merely to obtain passing results.

---

## 11. Chrome DevTools MCP Verification

Use the configured `chrome-devtools` MCP server.

Open each route in a real browser:

- `/privacy-policy`
- `/terms-of-service`
- `/about`

Verify desktop and mobile rendering.

Inspect:

- Typography and spacing.
- Container alignment.
- Footer link behavior.
- Responsive layout.
- Browser console errors.
- Accessibility issues.
- Navigation behavior.
- Unexpected layout shifts.

Capture screenshots for visual comparison with existing storefront pages.

Check that all pages maintain the same design language as the existing website.

Do not claim browser verification succeeded unless it was actually performed.

---

## 12. Documentation and Group O Closure

Extend the existing Group O phase documentation with:

**Phase 15.11 — Legal, Privacy & About Pages**

Record:

- Implementation scope.
- Route decisions.
- Design-system requirements.
- Verified content sources.
- Legal review dependencies.
- Acceptance criteria.
- Test evidence.
- Production restrictions.

After Phase 15.11 passes local verification, review the complete Group O implementation.

Preserve the existing Phase 15.9 production transport blocker and Phase 15.10 authenticated-browser verification limitation.

Do not silently change their statuses.

Group O may be marked **LOCAL IMPLEMENTATION COMPLETE** only when every required local criterion is satisfied.

Legal policy approval and production transport verification must remain explicit release gates.

Do not begin Group P automatically.

---

## 13. Required Completion Report

Provide:

1. Files created or modified.
2. Routes implemented.
3. Footer links integrated.
4. Design-system components and tokens reused.
5. Results of the token-compliance audit.
6. Privacy Policy content status.
7. Terms of Service content status.
8. About Us content status.
9. Automated test results.
10. Chrome DevTools MCP verification results.
11. Outstanding legal and production requirements.
12. Final Phase 15.11 and Group O statuses.

### Definition of Done

Phase 15.11 is locally complete when all three routes are implemented, visually consistent with the existing design system, accessible, responsive, correctly linked from the footer, and verified through automated tests and Chrome DevTools MCP.

Privacy Policy and Terms of Service must not be considered legally approved until the appropriate business/legal review is completed.

Group O must not be marked production-ready while existing deployment, authentication verification, or legal approval gates remain unresolved.

**Implementation directive:** Inspect the repository first, reuse existing architecture, enforce approved design tokens strictly, implement all three pages, verify them in the browser, and update Group O documentation without modifying unrelated features.

---

## Phase 15.11 Closure Record

### Implementation scope

- Added server-rendered `/about`, `/privacy-policy`, and `/terms-of-service` App Router pages.
- Added the `InformationPage`, `DocumentSection`, and `DraftLegalNotice` editorial primitives using MUI typography, alerts, stacks, and the existing `SiteSection` layout.
- Added an Information footer group with About Us, Privacy Policy, and Terms of Service links. The existing enquiry label is now Contact Us.
- All three routes use canonical metadata and `noindex, follow`; they are intentionally absent from the sitemap until their business content is approved.

### Verified content and legal status

- Content is limited to verified current website behavior: public catalog browsing, made-to-order requests and enquiries, Clerk authentication, Laravel-backed validation and authorization, private attachments, and the request-first release scope.
- Privacy Policy and Terms of Service are prominently marked `DRAFT — LEGAL REVIEW REQUIRED`. They do not invent a business address, privacy contact, retention period, jurisdiction, governing law, refund/return entitlement, warranty, or other unapproved business term.
- About content is intentionally factual and minimal. It remains `noindex, follow` until approved business copy is available.

### Local verification

- Passed: `npm run test:information`
- Passed: `npm run test:links`
- Passed: `npm run test:theme`
- Passed: `npm run typecheck`
- Passed: `npm run lint -- --max-warnings=0`
- Passed: `npm run build`
- Passed: `git diff --check`
- Chrome DevTools MCP verified each route on desktop and mobile viewports. The rendered accessibility trees show a single page heading, sequential content headings, semantic footer navigation, and named links. Screenshots were captured for all desktop/mobile route combinations.
- The browser console has no page error. It retains the expected local-development Clerk warning about development keys.

### Release gates and status

- **Phase 15.11: LOCAL IMPLEMENTATION COMPLETE.**
- **Group O: LOCAL IMPLEMENTATION COMPLETE.** Group P must not begin automatically.
- Legal/business approval remains required before the Privacy Policy, Terms of Service, and About page can be treated as final or indexable.
- Preserve the existing Phase 15.9 production transport blocker: a production HTTPS Laravel API origin, exact CORS origins, production Clerk configuration, reverse-proxy body limit of at least 6 MiB, and deployed-browser JSON/multipart verification are still required.
- Preserve the existing Phase 15.10 limitation: authenticated browser `ENQ-001` verification remains pending a disposable Clerk customer session.
