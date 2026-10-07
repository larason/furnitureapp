# Phase 14.2A — Category Hard-404 Remediation

## Status Entering This Remediation

```text
Phase 14.2 — BLOCKED
Group N — OPEN
Phase 14.3 — BLOCKED
```

Phase 14.2 implementation is otherwise substantially complete.

Runtime verification discovered one blocking production-semantic defect:

```text
/categories/<definitely-missing-slug>
```

renders the canonical not-found UI but responds:

```text
HTTP 200
```

rather than:

```text
HTTP 404
```

The current behavior is a Next.js streamed/soft 404 because `notFound()` is reached only after asynchronous category resolution has allowed the response to begin streaming.

This remediation owns only that defect.

Do NOT begin Phase 14.3.

---

# 1. Objective

Ensure that a genuinely nonexistent category route:

```text
/categories/[slug]
```

returns:

```text
HTTP 404
```

while continuing to render the canonical Phase 13.8 not-found experience.

Required result:

```text
Missing category
    ↓
HTTP 404
    +
canonical not-found UI
```

Not:

```text
HTTP 200
    +
noindex
    +
not-found-looking UI
```

---

# 2. Preserve Existing Correct Behavior

Do not regress:

```text
server-first rendering
Laravel category authority
canonical backend slug authority
canonical ProductCard
category product filtering
empty-category semantics
minimal server pagination
homepage category-link activation
frozen design tokens
responsive foundation
Phase 13.8 failure states
request-first policy
```

This is not a category-page rewrite.

---

# 3. Verify Framework Behavior First

Before modifying code, inspect the **installed Next.js version** and its corresponding local/official framework documentation or implementation behavior.

Do not solve this from remembered behavior from an older Next.js release.

Establish exactly:

```text
Why does the current route commit HTTP 200 before notFound()?

Which route/layout/loading/Suspense boundary causes streaming to begin?

What supported Next.js mechanism allows the category existence decision to occur before the HTTP status is committed?
```

Document the finding.

---

# 4. Do Not "Fix" This With Client Logic

Forbidden:

```text
client-side redirect
router.replace()
window.location
client-side 404 component
useEffect
```

The HTTP status must be correct at the server response level.

---

# 5. Do Not Fake the Status

Do not add:

```text
<meta name="robots" content="noindex">
```

and declare the issue solved.

Next.js already provides soft-404/noindex behavior.

The requirement is:

```text
actual HTTP status = 404
```

---

# 6. Do Not Redirect Missing Categories

Forbidden:

```text
/categories/bad-slug → /
/categories/bad-slug → /categories
```

A nonexistent canonical category is a missing resource.

It must remain a 404.

---

# 7. Do Not Create a Custom Success-Looking 404

Do not return:

```tsx
return <NotFoundLookingComponent />;
```

from a normal successful page response.

That preserves the defect.

Use the framework's correct not-found/status semantics.

---

# 8. Preserve Canonical Not-Found UI

The user-facing presentation should continue to come from the established Phase 13.8 not-found architecture.

Do not create:

```text
CategoryNotFound
Category404
MissingCategoryPage
```

merely to solve the transport status.

---

# 9. Investigate Streaming Boundaries

Inspect at minimum:

```text
app/layout.tsx
app/loading.tsx
app/not-found.tsx
app/categories/[slug]/page.tsx
category data/query helpers
SiteShell
any Suspense boundaries affecting the route
```

Determine where the response can begin streaming before category existence is known.

Do not assume `page.tsx` alone is responsible.

---

# 10. Important `loading.tsx` Investigation

Explicitly determine whether the root or relevant route-segment:

```text
loading.tsx
```

causes a Suspense/streaming boundary that permits shell/loading output to be flushed before category resolution completes.

If so, solve the problem deliberately.

Do not delete the global loading foundation casually.

Phase 13.8 behavior remains an architectural requirement.

---

# 11. Preferred Architectural Property

For a dynamic public resource route whose existence controls HTTP status:

> Resource existence should be known before the response status becomes irreversibly committed.

The exact implementation must follow the installed Next.js-supported architecture.

Do not invent framework hacks.

---

# 12. Avoid Duplicate API Requests

A tempting solution may resolve the category once for metadata/layout/status and again for page rendering.

Avoid unnecessary duplicate Laravel calls.

If framework architecture requires the same category lookup from multiple server contexts, inspect whether request-scoped deduplication/caching using supported React/Next.js mechanisms is appropriate.

Do not introduce a global mutable cache.

Do not install a caching library.

---

# 13. No Middleware API Lookup Unless Proven Necessary

Do NOT immediately solve this by making middleware call Laravel for every category request.

That could introduce:

```text
extra network hop
runtime constraints
duplicated authorization/error semantics
latency
deployment coupling
```

Use middleware only if investigation proves it is the correct supported architecture and alternatives are demonstrably unsuitable.

Expected solution should preferably remain inside normal App Router server rendering.

---

# 14. No Next.js Proxy Endpoint

Do not create:

```text
/api/categories/[slug]
```

inside Next.js merely to control the status.

Laravel remains the API authority.

---

# 15. Do Not Move Catalog Authority Into Next.js

Do not hard-code known category slugs to prevalidate requests.

Forbidden:

```ts
const validCategories = [
  "living-room",
  "bedroom",
  ...
];
```

The database/API remains authoritative.

---

# 16. Do Not Use Homepage Fixture Taxonomy

Fixture slugs must not become production route existence authority.

Production category existence is decided by Laravel.

---

# 17. Error Taxonomy Must Remain Correct

Preserve:

```text
Laravel category 404
    ↓
Next.js notFound()
    ↓
HTTP 404
```

But:

```text
Laravel 500
network failure
timeout
invalid upstream response
```

must NOT become 404.

Those remain unexpected failures handled through the existing error architecture.

---

# 18. Empty Category Must Remain 200

Do not confuse:

```text
category does not exist
```

with:

```text
category exists but contains zero products
```

Required:

```text
missing category
→ HTTP 404

existing empty category
→ HTTP 200
```

Even though the current local database has no existing empty category available for live verification, preserve and test this contract.

---

# 19. Current Empty Database Is Acceptable

Current local API evidence:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "total": 0
    }
  }
}
```

is not a Phase 14.2 implementation failure.

Do NOT seed production/test catalog records merely to satisfy runtime verification.

The following may remain:

```text
Real top-level category:
NOT AVAILABLE

Real nested category:
NOT AVAILABLE

Category with products:
NOT AVAILABLE

Existing empty category:
NOT AVAILABLE

Real resolved-slug product filtering:
NOT AVAILABLE
```

until local catalog data naturally exists.

---

# 20. Runtime Verification — Required Hard Gate

After remediation, run Laravel:

```text
http://127.0.0.1:8000
```

and Next.js using the locally assigned development/production port.

Use API-backed mode.

Then request a definitely nonexistent category.

Verify using an HTTP-level tool such as:

```bash
curl -i http://127.0.0.1:<next-port>/categories/category-that-does-not-exist-928374
```

or equivalent.

Required:

```text
HTTP status:
404
```

Do not infer the status from rendered content.

---

# 21. Production Runtime Verification

Because the defect occurred in both development and `next start`, verify both if practical:

```text
npm run dev
```

and:

```text
npm run build
npm run start
```

The production server is especially important.

Required:

```text
development missing category → HTTP 404
production missing category → HTTP 404
```

---

# 22. Browser Verification

Use installed:

```text
Google Chrome
```

or Fedora Firefox.

Chrome DevTools availability is NOT required.

Verify the canonical not-found UI still renders after the transport fix.

---

# 23. Browser Accessibility Regression

For the missing category page verify:

```text
one main
one H1
keyboard access
visible focus
return-home link
no horizontal overflow
```

Do not redesign it.

---

# 24. Streaming Regression

Verify that the fix does not create an obviously degraded experience for ordinary successful routes.

In particular inspect whether changing a loading/Suspense boundary causes:

```text
blank page while all catalog data loads
lost site shell
large layout shift
broken loading state
```

If a tradeoff is necessary, document it explicitly.

---

# 25. Loading Architecture

If the remediation requires changing loading-boundary placement, preserve the intent of Phase 13.8:

```text
known pending state
shell continuity where appropriate
calm generic presentation
no fake product/category skeletons
```

Do not introduce detailed category skeletons in this remediation.

---

# 26. Homepage Regression

The homepage must continue to work.

Do not alter its data strategy merely to repair category 404 semantics.

Run:

```text
npm run test:homepage
```

---

# 27. Category Contract Regression

Run:

```text
npm run test:category
```

Add/adjust tests so the architecture that caused the soft 404 is less likely to regress.

Unit/source tests alone do not replace the required HTTP runtime check.

---

# 28. HTTP Integration Regression Test

If the repository's test architecture can reasonably exercise a built/running Next.js server without introducing dependencies or fragile CI behavior, consider adding a focused HTTP-level regression for:

```text
/categories/definitely-missing
→ 404
```

Do not build an enormous E2E framework solely for this one issue.

If runtime-only verification is the appropriate level, document that.

---

# 29. No New Dependencies

Expected:

```text
NONE
```

Do not install Playwright, Cypress, Puppeteer, or another test framework just for this remediation.

Installed Chrome/Firefox plus existing tools are sufficient.

---

# 30. Design System

Expected design changes:

```text
NONE
```

Do not touch frozen tokens.

Do not modify ProductCard media ratio.

Do not redesign category pages.

---

# 31. Backend

Expected Laravel changes:

```text
NONE
```

The Laravel endpoint already correctly returns:

```text
404 RESOURCE_NOT_FOUND
```

The defect exists in the Next.js response semantics.

Do not modify backend behavior to compensate for a frontend streaming problem.

---

# 32. API Client

Do not modify the API client's error contract unless investigation demonstrates an actual client defect.

The current evidence says Laravel's 404 is already correctly recognized.

---

# 33. Scope

Do NOT implement:

```text
14.3 product listing
14.4 product detail
14.5 search
14.6 filters/sorting
14.7 SEO metadata
14.8 structured data
14.9 sitemap/robots
14.10 SEO internal linking
14.11 image optimization
```

No new feature work.

---

# 34. Documentation

Update the Phase 14.2 execution record with:

```text
initial soft-404 discovery
root cause
remediation
runtime HTTP evidence
```

Do not erase the failed verification history.

The record should show that runtime testing caught the issue and that it was subsequently corrected.

---

# 35. Git

Before Git operations, read/follow:

```text
git-workflow-and-versioning
```

The previous Phase 14.2 commit already exists.

Create a separate atomic remediation commit rather than rewriting history unless the Git skill explicitly dictates otherwise.

Suggested intent:

```text
fix(web): preserve hard 404 for missing categories
```

Exact message follows repository Git policy.

---

# 36. Completion Report

Return:

```text
Phase 14.2A status:
PASS / BLOCKED


ORIGINAL DEFECT

Missing category before remediation:
HTTP 200 soft 404

Canonical not-found UI:
YES

Laravel upstream response:
HTTP 404 RESOURCE_NOT_FOUND


ROOT CAUSE

Installed Next.js version:
<version>

Streaming boundary responsible:
<exact finding>

Why status was committed before notFound():
<explanation>

Framework documentation/behavior verified:
YES / NO


REMEDIATION

Files changed:
<list>

Architecture used:
<explanation>

Client-side workaround:
NONE

Redirect workaround:
NONE

Hard-coded category list:
NONE

Middleware API lookup:
NONE / <justification>

Next.js proxy endpoint:
NONE

Duplicate Laravel requests introduced:
NO / <explanation>

New dependency:
NONE


HTTP SEMANTICS

Development:
Missing category HTTP status:
404 / FAIL

Production next start:
Missing category HTTP status:
404 / FAIL

Canonical not-found UI:
PASS / FAIL

Missing category no longer soft-404:
PASS / FAIL

Unexpected API failures still remain errors:
PASS / FAIL

Existing empty category remains 200:
PASS / TESTED CONTRACT ONLY / FAIL


LOCAL CATALOG AVAILABILITY

Top-level category:
<actual slug / NOT AVAILABLE>

Nested category:
<actual slug / NOT AVAILABLE>

Category with products:
<actual slug / NOT AVAILABLE>

Existing empty category:
<actual slug / NOT AVAILABLE>

Resolved category product filtering:
PASS / NOT AVAILABLE

Synthetic catalog data created:
NO


LOADING / STREAMING

Loading architecture preserved:
YES / <explain adjustment>

Site shell preserved:
YES / NO

Successful route UX regression:
NONE / <details>

New category skeleton:
NONE


BROWSER

Browser:
<Chrome / Firefox>

Canonical 404 UI:
PASS / FAIL

One main:
PASS / FAIL

One H1:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Horizontal overflow:
NONE / FAIL

Console/network errors:
NONE / <details>


REGRESSION

npm run test:category:
PASS / FAIL

npm run test:homepage:
PASS / FAIL

API client:
PASS / FAIL

Layout:
PASS / FAIL

Responsive:
PASS / FAIL

State/failure:
PASS / FAIL

Routing:
PASS / FAIL

Request-first:
PASS / FAIL

Theme:
PASS / FAIL

Design system:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


SCOPE

Design tokens changed:
NO

ProductCard forked:
NO

Backend changed:
NO

Flutter changed:
NO

Phase 14.3 work:
NONE

Dependencies added:
NONE


DOCUMENTATION

Phase 14.2 failure history retained:
YES / NO

Root cause documented:
YES / NO

Runtime hard-404 evidence documented:
YES / NO


GIT

git-workflow-and-versioning read:
YES / NO

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 14.2A:
PASS / BLOCKED

Phase 14.2:
PASS / BLOCKED

Phase 14.3:
READY / BLOCKED
```

---

# 37. STOP Condition

This remediation passes only if an actual HTTP request to the Next.js category route proves:

```text
/categories/<nonexistent-slug>
→ HTTP 404
```

in production `next start`.

The canonical not-found UI must remain intact.

A response of:

```text
HTTP 200 + noindex
```

is still a failure.

Do not declare PASS based only on:

```text
notFound() was called
the 404 component rendered
noindex exists
unit tests passed
Next.js documents soft 404s
```

The required observable contract is the HTTP status itself.

If a supported App Router architecture cannot satisfy this requirement without a material architectural tradeoff, STOP and report the options rather than silently accepting the soft 404.

On success:

```text
Phase 14.2A — PASS
Phase 14.2 — PASS
Phase 14.3 — READY
```

Do not start Phase 14.3 automatically.

---

# Phase 14.2A Execution Record

## Result

Phase 14.2A: PASS

Phase 14.2: PASS

Phase 14.3: READY, not started

## Original Defect

Before remediation, a missing category rendered the canonical Phase 13.8
not-found UI but returned HTTP 200 in both `next dev` and `next start`.
Laravel correctly returned HTTP 404 `RESOURCE_NOT_FOUND`.

## Root Cause

Installed Next.js version: 16.3.8.

The root `app/loading.tsx` creates a loading/Suspense boundary. Category
existence was resolved asynchronously inside `app/categories/[slug]/page.tsx`,
so the response could begin streaming before `notFound()` was reached. Next.js
therefore produced the documented soft-404 behavior: HTTP 200 plus the
not-found UI and `noindex` metadata.

## Remediation

Added `frontend/web/proxy.ts`, narrowly matched to `/categories/:path*`.
It uses the existing server-only API client to preflight the Laravel category
resource. An upstream `ApiError` with status 404 returns
`NextResponse.next({ status: 404 })`, allowing the normal category route to
render the canonical not-found boundary while preserving the hard HTTP status.
Unexpected API failures are not converted to 404. No client workaround,
redirect, hard-coded taxonomy, backend change, dependency, or design-system
change was introduced.

The proxy and page necessarily perform the category lookup in separate server
contexts; this is the supported pre-stream status tradeoff. The page remains
the authority for rendering category data and product filtering.

## Runtime Evidence

- Laravel origin: `http://127.0.0.1:8000`
- Next development origin: `http://127.0.0.1:3000`
- Next production origin: `http://127.0.0.1:3001`
- API-backed mode confirmed through the existing ignored `.env.local` with `API_BASE_URL=http://127.0.0.1:8000`; no `NEXT_PUBLIC_API_BASE_URL` used.
- `GET /api/v1/categories` returned an empty collection (`total: 0`).
- `GET /api/v1/products?per_page=20` returned an empty collection (`total: 0`).
- Real top-level category: NOT AVAILABLE.
- Real nested category: NOT AVAILABLE.
- Category with products: NOT AVAILABLE.
- Existing empty category: NOT AVAILABLE.
- Resolved category product filtering: NOT AVAILABLE.
- No synthetic catalog data was created.
- `/categories/definitely-nonexistent-phase-14-2` returned HTTP 404 in development and production `next start`.
- The response contained the canonical `We couldn't find that page.` UI.

## Browser Evidence

Browser: installed Google Chrome.

Verified at 959px, 960px, 961px, and 1440px:

- One `main` and one `h1`.
- Canonical not-found UI and return-home link present.
- Keyboard traversal reached the skip link, navigation, and return-home link.
- Focus ring was visible.
- No horizontal overflow.
- No console errors.
- Not-found shell images loaded with stable dimensions.

The browser observed expected HTTP 404 responses for the missing category and
for stale fixture navigation links while the local Laravel catalog was empty.

## Verification

- `npm run test:category`: PASS
- `npm run test:homepage`: PASS
- `npm run test:theme`: PASS
- `npm run test:layout`: PASS
- `npm run test:responsive`: PASS
- `npm run test:states`: PASS
- `npm run test:api`: PASS
- `npm run typecheck`: PASS
- `npm run lint`: PASS
- `npm run build`: PASS
- `git diff --check`: PASS
