# Phase 13.8 Execution Record — Error / Loading / Not-Found Handling

## Result

```text
Phase 13.8: PASS
Phase 13.9: READY
```

The public website now has a generic, shell-preserving App Router failure foundation matching the installed Next.js 16.3.8 conventions: a route loading state, a generic not-found state driven by `notFound()`, and an isolated unexpected-error boundary. Expected domain/API failures remain owned by future feature phases, and the server-first shell and root layout are unchanged.

## BOUNDARIES

```text
loading.tsx: frontend/web/app/loading.tsx (added)
error.tsx: frontend/web/app/error.tsx (added)
not-found.tsx: frontend/web/app/not-found.tsx (added)
global-error.tsx: NOT ADDED — the root layout is minimal; Next.js already provides a built-in root-layout 500 fallback; a custom global error replaces the document, loses the theme/providers, and duplicates document styling without a demonstrated need
Installed Next.js version verified: YES (16.3.8; docs under node_modules/next/dist/docs/)
```

## FAILURE TAXONOMY

```text
Loading: known pending route segment; generic restrained pending UI; shell preserved
Not found: expected public absence under the routing contract; framework notFound() + generic 404
Expected API/domain failure: owned by the feature (validation, auth, authorization, rate limit, empty results, MADE_TO_ORDER); never the error boundary
Unexpected error: unhandled exception caught by app/error.tsx; calm copy + framework retry + safe home route
Empty result treated as 404: NO
MADE_TO_ORDER treated as error: NO
```

## LOADING

```text
Shell preserved while page pending: YES
Global loading treatment: SiteSection + concise role="status" "Loading…" + static neutral bars (no animation, no fake page content)
Product skeleton implemented: NO
Category skeleton implemented: NO
Search skeleton implemented: NO
Artificial delay: NONE
Reduced motion: PASS (static, non-animated)
Accessible status: PASS (single concise status; decorative bars aria-hidden)
```

## ERROR

```text
Unexpected error presentation: "We couldn't load this page." + "Something went wrong on our side. Please try again." + Try again (primary) + Return home (secondary)
Raw exception exposed: NO
Stack exposed: NO
Digest exposed: NO
Retry: framework retry() invoked once by the primary "Try again" button
Retry uses framework recovery: YES
window.location.reload: NO
Home navigation: PageMessageLink -> "/" using next/link
Error boundary client scope: app/error.tsx only ('use client'); shared states components are otherwise server-capable
```

## NOT FOUND

```text
Canonical 404 presentation: "404" eyebrow + "We couldn't find that page." + "The address may have changed, or the page may no longer exist." + Return home
Uses framework not-found mechanism: YES (notFound() / unmatched route -> app/not-found.tsx; HTTP 404)
Safe home navigation: YES
Fake product recommendations: NONE
Missing resources redirected home: NO
```

## API ERROR MAPPING

```text
API client changed: NO
404: notFound() at page/domain integration
401: authentication concern; owned by the Clerk/account phase (not implemented here)
403: known authorization outcome where the contract exposes it; never converted to 404 unless Laravel already masks it
422: form/feature validation; field-associated presentation owned by the request/enquiry phases
429: expected rate-limit outcome; feature must honor Retry-After (no global auto-retry)
5xx: unexpected; may propagate to a route/feature error boundary
Network: transport failure; unavailable/error condition, not 404
Timeout: ApiTransportError kind "timeout"; distinct from absence/validation/cancellation
Caller abort: ApiTransportError kind "aborted"; expected cancellation, not an application crash
Status detected through typed/status-bearing data: YES (ApiError.status, ApiError.retryAfterSeconds, ApiTransportError.kind)
Magic message matching: NONE
```

## SECURITY / PRIVACY

```text
Raw Laravel response rendered: NO
Internal IDs rendered: NO
Environment data rendered: NO
Headers rendered: NO
Stack/path rendered: NO
Secrets rendered: NO
```

## ACCESSIBILITY

```text
Meaningful state headings: PASS (each state owns an h1)
Retry semantics: PASS (button); home link remains a link
Link/button semantics: PASS
Keyboard: PASS (skip link, nav, retry button, home link reachable)
Visible focus: PASS (theme focus ring)
Contrast: PASS (approved token pairings)
Mobile reflow: PASS (no overflow at 320/390)
Reduced motion: PASS
Live regions restrained: PASS
```

## SERVER / CLIENT

```text
Root layout remains Server Component: YES
Site shell remains server-first: YES
error.tsx client boundary isolated: YES (only app/error.tsx)
not-found server-capable: YES
loading server-capable: YES
New global client state: NONE
```

## SCOPE

```text
Homepage implemented: NO
Product routes implemented: NO
Category routes implemented: NO
Search implemented: NO
Filters/sorting implemented: NO
Clerk integrated: NO
Form validation UI implemented: NO
Payment failure UI implemented: NO
Backend/API changed: NO
Flutter changed: NO
Dependencies added: NONE
```

## RUNTIME VERIFICATION

```text
Real nonexistent URL: PASS (HTTP 404 + canonical not-found UI)
Error boundary behavior: PASS (temporary dynamic probe route; hydrated error UI verified; no raw exception; probe removed before completion)
Loading behavior: PASS (temporary dynamic probe route; loading UI with shell preserved; probe removed before completion)
Desktop visual: PASS
Mobile visual: PASS
Keyboard/focus: PASS
```

## REGRESSION

```text
API client: PASS
Layout contract: PASS
Routing contract: PASS (links only to implemented routes)
Request-first: PASS
Theme contract: PASS
Design-system validation: PASS
TypeScript: PASS
ESLint: PASS
Production build: PASS
git diff --check: PASS
```

## DOCUMENTATION

```text
Failure-state guidance: frontend/AGENTS.md (Failure States) + docs/decisions.md ADR/WEB-003
Group M execution record: PASS
frontend/AGENTS.md: updated
ADR: ADR/WEB-003 — App Router Failure-State Architecture
```

## FILES CHANGED

```text
Added:
  frontend/web/app/loading.tsx
  frontend/web/app/error.tsx
  frontend/web/app/not-found.tsx
  frontend/web/components/states/page-message.tsx
  frontend/web/components/states/states.contract.mjs

Modified:
  frontend/web/package.json
  frontend/AGENTS.md
  docs/decisions.md
  phases/group-M-phases.md
```

No `global-error.tsx`, Group N route, API client, Clerk integration, dependency, backend, or Flutter change was made. Temporary runtime probes were removed before completion.

## GIT

```text
git-workflow-and-versioning skill read: YES
Operations: stage Phase 13.8 files; atomic commit
Commit: feat: add website failure-state foundation
Push: NONE (not requested)
```

## RESULT

```text
Phase 13.8: PASS
Phase 13.9: READY
```

Phase 13.9 was not started automatically.
