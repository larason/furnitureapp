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

---

# Phase 13.9 Execution Record — Responsive Foundation

## Result

```text
Phase 13.9: PASS
Group M: CLOSED
Group N: READY
```

## Breakpoint Authority

```text
Canonical source: frontend/design-system/tokens.css
Synchronized source: frontend/design-system/design-tokens.json
MUI adapter: frontend/web/theme/theme.ts

xs: base / 0px
sm: --breakpoint-phone / 640px
md: --breakpoint-tablet / 960px
lg: --breakpoint-desktop / 1024px
xl: --container-max / 1440px

MUI default drift: NONE
Second breakpoint authority: NONE
Raw width media queries: NONE
```

## Responsive Strategy

```text
CSS-first: YES
Device detection: NONE
window.innerWidth layout branching: NONE
Server-first architecture preserved: YES
Hydration issues: NONE observed
```

The one `useMediaQuery` use is justified behavior, not layout styling: the existing interactive mobile drawer is forced closed while desktop navigation is active, preventing a desktop resize from leaving the modal visible or focus-trapping users.

## Container And Typography

```text
ContentContainer: frontend/web/components/layout/content-container.tsx
Maximum width: --container-max / 1440px
Gutters: --container-gutter-phone/tablet/desktop / 16px, 24px, 48px
Contained sections: SiteSection -> ContentContainer
Full-bleed sections: SiteSection width="full" without viewport-width or negative-margin hacks
Nested containers: only intentional nested contained content inside a full-bleed section; never ordinary page composition
Page-specific gutter systems: NONE
Canonical type scale: preserved
Arbitrary responsive typography: NONE
```

The foundation placeholder now uses `SiteSection` rather than MUI `Container`; this removes the only parallel page container. The header no longer has a raw `480px` search cap. The drawer width is now bounded by existing form-width and spacing tokens.

## Accessibility And Runtime Verification

```text
Viewport matrix: 320, 390, 640, 960, 1024, 1440, 1728px
Horizontal overflow: NONE at every tested width
Navigation boundary: 959px mobile only; 960px and 961px desktop only
Both navigation modes active: NO
Neither navigation mode available: NO
Drawer at 320px: PASS (opens, remains within viewport)
Open drawer then resize to desktop: PASS (modal closes through the behavioral md guard)
404 at desktop: PASS (canonical heading, no overflow)
200% zoom method: Chrome CDP page scale factor; visual viewport reduced to 512px with no horizontal overflow
Touch targets: PASS (existing 44px controls retained)
Reduced motion: PASS (global prefers-reduced-motion override added; no responsive animation introduced)
Hover-only essential behavior: NONE
```

The existing Phase 13.8 loading, not-found, and error states retain their `SiteSection`/`PageMessage` token-backed composition, wrapping action row, server-first boundaries, and prior mobile runtime coverage. No state-page redesign was needed.

## Responsive Contract

```text
Added: frontend/web/components/layout/responsive.contract.mjs
Script: npm run test:responsive

Guards:
- canonical design-token to MUI breakpoint mapping;
- ContentContainer maximum width and responsive gutters;
- SiteSection contained composition;
- no page-level MUI Container authority;
- shared md navigation transition;
- drawer resize behavior;
- no raw width media queries, viewport JavaScript, user-agent detection,
  100vw, negative viewport margins, or global overflow-x masking;
- reduced-motion preference support.
```

## Client Boundaries

```text
app/providers.tsx: required MUI App Router provider
app/error.tsx: required App Router retry boundary
components/layout/mobile-navigation.tsx: required drawer interaction
New client boundaries: NONE
Responsive client state: existing mobile drawer only
```

## Scope

```text
Homepage/catalog/product/category/search/filter UI: NOT IMPLEMENTED
SEO/structured data/sitemap/robots: NOT IMPLEMENTED
API client changed: NO
Routing changed: NO
Backend changed: NO
Flutter changed: NO
Dependencies added: NONE
```

## Validation

```text
Theme contract: PASS
API client: PASS (17 tests)
Layout contract: PASS
State contract: PASS
Responsive contract: PASS
TypeScript: PASS
ESLint: PASS
Production build: PASS
git diff --check: PASS
```

## Documentation

```text
Responsive authority: frontend/web/RESPONSIVE.md
Durable enforcement: frontend/AGENTS.md (Responsive Foundation)
ADR: ADR/WEB-004 — Responsive Web Foundation
```

## Group M Exit Review

```text
13.1 Next.js: PASS
13.2 TypeScript: PASS
13.3 MUI: PASS
13.4 Theme: PASS
13.5 API client: PASS
13.6 Routing: PASS
13.7 Layout: PASS
13.8 Failure states: PASS
13.9 Responsive foundation: PASS

Server-first: PASS
Design-system authority: PASS
API-ready: PASS
Routing-ready: PASS
Layout-ready: PASS
Failure-state-ready: PASS
Responsive-ready: PASS
Accessibility foundation: PASS
Request-first policy: PASS
```

## Files Changed

```text
Added:
  frontend/web/RESPONSIVE.md
  frontend/web/components/layout/responsive.contract.mjs

Modified:
  frontend/web/app/globals.css
  frontend/web/app/page.tsx
  frontend/web/components/layout/mobile-navigation.tsx
  frontend/web/components/layout/site-header.tsx
  frontend/web/package.json
  frontend/AGENTS.md
  docs/decisions.md
  phases/group-G-phases-exec-report.md
```

## Result

```text
Phase 13.9 — PASS
Group M — CLOSED
Group N — READY
```

Phase 14.1 was not started.
