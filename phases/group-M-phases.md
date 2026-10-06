# Phase 13.8 — Error / Loading / Not-Found Handling

## Objective

Implement the reusable **Next.js App Router failure and transitional-state foundation** for the SL Furnitures public website.

This phase establishes how the website handles:

- route loading;
- route-level unexpected errors;
- root/global failures where appropriate;
- 404/not-found states;
- API `404` → website `notFound()` translation;
- expected API failures versus exceptional failures;
- retry behavior;
- accessible status communication;
- failure-state visual composition;
- server/client boundaries;
- future page integration rules.

The architecture must support later catalog, search, furniture-request, enquiry, and account pages without each feature inventing a separate error/loading system.

The desired model is:

```text
Request / navigation
        │
        ├── pending
        │      ↓
        │   loading boundary
        │
        ├── expected absence
        │      ↓
        │   notFound()
        │      ↓
        │   not-found UI
        │
        ├── expected domain/API failure
        │      ↓
        │   page/component handles intentionally
        │
        └── unexpected exception
               ↓
            error boundary
               ↓
          recovery / retry UI
```

---

# 1. Scope

Implement only:

```text
Phase 13.8 — Error/loading/not-found handling
```

This phase may introduce:

```text
app/loading.tsx
app/error.tsx
app/not-found.tsx
```

and `global-error.tsx` only if technically justified.

It may also introduce small reusable presentation primitives/helpers for these states where they eliminate duplication.

It must NOT implement:

```text
13.9 responsive foundation

14.1 homepage
14.2 category pages
14.3 product listing
14.4 product detail
14.5 search
14.6 filters/sorting
14.7 SEO metadata
14.8 structured data
14.9 sitemap/robots
14.10 internal linking
14.11 image/performance optimization
```

---

# 2. Read Authorities First

Before coding, inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── COMPONENTS.md
├── ACCESSIBILITY.md
└── tokens.css

frontend/web/
├── app/
├── components/layout/
├── lib/api/
│   ├── client.ts
│   └── README.md
├── ROUTING.md
├── theme/
├── package.json
├── tsconfig.json
└── next.config.*

docs/api/
├── api-contract.md
├── api-conventions.md
├── api-resources.md
└── openapi.yaml

docs/decisions.md
phases/group-M-phases.md
```

Inspect the actual installed Next.js version before implementing App Router conventions.

Do not copy stale Next.js examples from memory.

---

# 3. Preserve Phase 13.7

Phase 13.7 established:

```text
SiteShell
SiteHeader
Primary navigation
Mobile navigation
ContentContainer
SiteSection
SiteFooter
single <main id="main-content">
skip-to-main
server-first root layout
```

Preserve this architecture.

The current shell already has one `<main id="main-content">` and keeps the root layout as a Server Component.

Do not create a second application shell merely for errors.

---

# 4. Git Workflow

Before ANY Git command:

```text
locate and read:
git-workflow-and-versioning
```

Follow that skill exactly.

Preserve unrelated owner changes.

Never commit environment files or secrets.

Stage only Phase 13.8 work.

---

# 5. Failure Taxonomy

Document and enforce four distinct concepts:

```text
LOADING
Known pending state

NOT FOUND
Requested public resource does not exist / is unavailable
under the public routing contract

EXPECTED FAILURE
Known API/domain outcome that the feature can present intentionally

UNEXPECTED ERROR
Unhandled exception / infrastructure or programming failure
```

Do not collapse all four into:

```text
Something went wrong
```

---

# 6. Expected Failure Is Not an Error Boundary

An App Router `error.tsx` boundary is for unexpected exceptions.

It must not become the normal presentation mechanism for:

```text
empty product results
no search matches
validation errors
request validation failure
authentication required
permission denied
normal 404
empty order/request history
MADE_TO_ORDER state
```

Those have feature-specific semantics.

---

# 7. Empty State Is Not Not-Found

Establish explicitly:

```text
/product-that-does-not-exist
→ 404 / notFound()

/products?filter=...
with zero matching products
→ valid page + empty result state
```

Do not turn empty search/list results into HTTP 404.

Group N will implement the actual empty catalogue/search presentation.

---

# 8. MADE_TO_ORDER Is Not an Error

Preserve the design-system rule:

```text
MADE_TO_ORDER
≠ unavailable error
≠ warning
≠ failure
```

Never route made-to-order products through failure-state UI merely because they cannot follow a normal stock purchase path.

---

# 9. App Router Boundaries

Inspect the installed Next.js version and implement the correct supported conventions for:

```text
loading.tsx
error.tsx
not-found.tsx
global-error.tsx
notFound()
```

Do not assume APIs or signatures from another Next.js release.

---

# 10. Root Loading Boundary

Evaluate whether:

```text
frontend/web/app/loading.tsx
```

is appropriate for the current architecture.

If introduced, it should provide a restrained route-transition/pending state suitable for the global shell.

Do not create a theatrical full-screen loader.

---

# 11. Loading Visual Philosophy

Loading UI should feel:

```text
quiet
stable
predictable
structural
```

Avoid:

```text
spinning logo
large centered spinner
progress spectacle
bouncing dots
brand animation
shimmer everywhere
pulse-heavy skeletons
```

The furniture site should remain calm.

---

# 12. Layout Stability

Loading states should preserve approximate page geometry when possible.

Future page-level loading states should reduce:

```text
layout shift
content jumping
unexpected shell movement
```

Do not make the global header/footer disappear simply because page content is pending.

---

# 13. Shell Persistence

Normal route-level loading should occur within the established site shell where App Router hierarchy allows it.

Conceptually:

```text
Header
Navigation

[ pending page content ]

Footer
```

not:

```text
[ blank white screen + spinner ]
```

---

# 14. Global Loading vs Feature Loading

Do not attempt to design all future skeletons in 13.8.

Establish the ownership rule:

```text
global loading
→ generic route-level pending treatment

feature loading
→ future owning page/component phase
```

For example:

```text
product-grid skeleton
→ Phase 14.3

product-detail skeleton
→ Phase 14.4

search-results skeleton
→ Phase 14.5
```

---

# 15. Skeleton Policy

If generic skeleton primitives already exist in MUI, they may be used where justified.

Do NOT create an elaborate custom skeleton framework.

Do not add a dependency.

If using MUI `Skeleton`, configure it according to reduced-motion requirements.

---

# 16. Reduced Motion for Loading

Do not rely on animated pulse/wave as the only indication of loading.

Honor the existing reduced-motion contract.

If animation is disabled, the loading state must remain understandable.

---

# 17. Loading Accessibility

Avoid noisy repeated live-region announcements.

A route loading boundary may expose concise status text where useful:

```text
Loading…
```

but do not cause screen readers to announce dozens of skeleton cells.

Decorative skeleton elements should not pollute the accessibility tree.

---

# 18. Root Error Boundary

Implement:

```text
frontend/web/app/error.tsx
```

if supported/appropriate for the installed Next.js version.

Remember that `error.tsx` requires a Client Component under normal App Router semantics.

Keep that client boundary isolated.

Do NOT make the site shell or root layout client-side just because the error boundary requires client behavior.

---

# 19. Error Boundary Responsibility

The root error boundary should catch unexpected failures within its route segment.

It should present:

```text
clear heading
short explanation
retry/recovery action
safe route back to useful content where appropriate
```

It should NOT display:

```text
stack trace
exception class
database error
API body
Laravel trace
environment information
internal path
request headers
secret/token
```

---

# 20. Error Message Tone

Use calm, concise language.

For example conceptually:

```text
We couldn't load this page.
Please try again.
```

Do not use:

```text
Oopsie!
Uh-oh!
Something exploded!
404 furniture not found 😂
```

Avoid overly playful failure copy.

---

# 21. Retry

Use the App Router error-boundary recovery mechanism supported by the installed Next.js version.

Typically this means the provided:

```text
reset()
```

mechanism where appropriate.

Do not implement retry by:

```text
window.location.reload()
```

unless there is a concrete technical reason.

---

# 22. Retry Semantics

Retry must mean:

```text
attempt to render/recover the failed segment again
```

It must not:

```text
repeat a non-idempotent form submission
repeat a furniture request
repeat an enquiry
repeat a payment
```

without explicit feature-level safeguards.

This distinction must be documented for future phases.

---

# 23. Error Logging

Inspect current Next.js/application observability architecture.

Do not install Sentry or another monitoring dependency during 13.8.

Do not create fake logging infrastructure.

If Next.js provides an error digest, it may remain useful internally, but do not expose it unnecessarily to users.

---

# 24. Global Error Boundary

Evaluate:

```text
app/global-error.tsx
```

carefully.

Add it only if:

- supported by installed Next.js;
- it meaningfully protects against root-layout failure;
- its requirements are correctly understood;
- it can be implemented without duplicating normal error UI incorrectly.

Do not add it simply because Next.js supports the filename.

---

# 25. Global Error Independence

If `global-error.tsx` replaces the root layout when active under the installed Next.js behavior, it must contain whatever minimal document structure that framework requires.

Do not assume `SiteShell` will still exist.

Verify this against the installed Next.js documentation/version.

---

# 26. Global Error Styling

A catastrophic root failure should still look recognizably like SL Furnitures using the smallest safe styling strategy.

However, avoid making global error recovery depend on the same provider/theme infrastructure whose failure it may be handling.

Resilience takes priority over perfect visual fidelity at this level.

---

# 27. Not-Found Boundary

Implement a canonical website not-found state using the supported App Router convention.

Expected location likely:

```text
frontend/web/app/not-found.tsx
```

unless installed Next.js semantics require another structure.

---

# 28. Not-Found Purpose

The not-found state should communicate:

```text
the requested page/resource could not be found
```

without implying:

```text
server crash
permission failure
empty catalogue
temporary network problem
```

---

# 29. 404 Visual Treatment

Keep it restrained.

A suitable composition is conceptually:

```text
404

We couldn't find that page.

The address may have changed, or the page may no longer exist.

[Return home]
```

Potentially a second useful route may be offered only if that destination actually exists.

Do not create a giant novelty `404` design.

---

# 30. No Fake Product Recommendations

Do not fill the 404 page with:

```text
Trending furniture
Recommended products
Popular categories
```

because that would require Group N catalog integration.

Keep 13.8 generic.

---

# 31. 404 Navigation

At minimum, provide a safe route to:

```text
/
```

using `next/link`.

Do not link to unimplemented pages merely to make the state appear complete.

---

# 32. Not-Found and Site Shell

Where supported by the current App Router hierarchy, normal not-found presentation should remain compatible with the public site shell.

Avoid duplicating header/footer inside `not-found.tsx` if the layout already provides them.

---

# 33. Resource 404 Mapping

Create/document the rule for future public resource pages:

```text
Laravel says resource does not exist
        ↓
Next.js page translates that expected absence
        ↓
notFound()
        ↓
canonical website not-found UI
```

This is particularly important for future:

```text
/products/[slug]
/categories/[slug]
```

---

# 34. Do Not Implement Product/Category Pages

You may create/test a reusable translation helper only if justified.

Do NOT create:

```text
app/products/[slug]/page.tsx
app/categories/[slug]/page.tsx
```

to demonstrate `notFound()`.

Those remain Group N.

---

# 35. API Client Contract

Phase 13.5 already preserves Laravel success/error envelopes.

Do not destroy that abstraction.

Inspect exactly how:

```text
404
422
401
403
429
5xx
network failure
timeout
abort
```

are represented by the client.

Build 13.8 rules around actual client behavior.

Do not guess.

---

# 36. Do Not Make API Client UI-Aware

The generic API client should NOT call:

```text
notFound()
redirect()
router.push()
toast()
```

itself.

Transport should remain transport.

Page/domain integration owns presentation decisions.

Expected architecture:

```text
API client
→ typed transport result/error

page/domain adapter
→ interprets semantics

Next.js
→ notFound()/expected state/error boundary
```

---

# 37. 404 Translation Helper

If repeated translation logic is clearly inevitable, a tiny server-side helper may be justified.

For example conceptually:

```text
get-or-not-found
```

But do not create an abstraction before inspecting the actual API error model.

It must not hide unrelated failures.

---

# 38. Only 404 Becomes Not Found

Do not accidentally map:

```text
401
403
422
429
500
network error
timeout
```

to `notFound()`.

A 404 masking rule from backend authorization may intentionally make some resources indistinguishable from absence, but the frontend should follow the API's returned status rather than reconstruct backend authorization logic.

---

# 39. 401

Document the future handling rule:

```text
401
→ authentication concern
```

Do not implement Clerk redirects in 13.8.

Public catalog pages generally should not require authentication.

Authenticated feature phases will own actual behavior.

---

# 40. 403

Document:

```text
403
→ known authorization/permission outcome where the contract exposes it
```

Do not turn it into generic 404 unless the Laravel contract already masks that endpoint as 404.

Do not invent authorization behavior in the frontend.

---

# 41. 422

Validation failures belong to forms/features.

Future furniture-request/enquiry forms should map validation errors to fields/form summaries.

Do not send `422` into the global error boundary as normal behavior.

13.8 should document this ownership.

---

# 42. 429

The backend contract supports rate limiting and `Retry-After`.

Document the rule:

```text
429
→ expected rate-limit outcome
→ feature-specific retry messaging/timing
```

Do not implement a global automatic retry loop.

Do not ignore `Retry-After`.

---

# 43. 5xx

Unexpected backend server failures may propagate to a route/feature error boundary depending on context.

Do not convert 5xx into:

```text
No products found
```

or:

```text
404
```

---

# 44. Network Failure

Network/DNS/connectivity failures are not 404.

Treat them as unavailable/error conditions.

Do not claim the requested resource does not exist when Laravel could not be reached.

---

# 45. Timeout

Phase 13.5 introduced timeout behavior.

Timeout should remain distinguishable from:

```text
not found
validation failure
caller cancellation
```

Do not rewrite all fetch failures into one generic status inside the API client.

---

# 46. Caller Cancellation

An intentional `AbortSignal` cancellation should not automatically surface a dramatic error UI.

Inspect the Phase 13.5 semantics and preserve them.

Do not log expected caller cancellation as an application crash.

---

# 47. Error Classification

If a reusable classifier is justified, it must be:

```text
small
typed
transport-aware
framework-independent where practical
```

Do not create a giant `ErrorManager` service.

---

# 48. No Magic String Matching

Do not determine status by:

```ts
error.message.includes("404")
```

Use typed/status-bearing information from the API client.

---

# 49. No `any`

Error handling frequently tempts developers to use:

```ts
catch (error: any)
```

Do not.

Use:

```text
unknown
```

plus safe narrowing, or established typed error/result structures.

---

# 50. Error Cause Preservation

Where errors are wrapped, preserve useful technical cause/status internally where practical.

Do not discard debugging information merely to produce friendly UI.

But never expose internal details directly to the user.

---

# 51. Loading vs Suspense

Understand the installed App Router behavior before adding manual `<Suspense>` boundaries.

Do not sprinkle Suspense throughout the shell simply because `loading.tsx` exists.

Use route loading boundaries first where appropriate.

Future page phases may add granular Suspense when justified.

---

# 52. No Artificial Delays

Do not add:

```ts
await sleep(1000)
```

or fake latency merely to make loading UI visible.

Tests may simulate pending states without modifying production behavior.

---

# 53. No Minimum Spinner Time

Do not force loading indicators to remain visible for aesthetic reasons.

Fast responses should remain fast.

---

# 54. Loading Content Ownership

The global loading state should not know whether the future page is:

```text
homepage
category
product
search
request
enquiry
```

Keep it generic.

---

# 55. Reusable State Presentation

If justified, create a small generic presentation component, conceptually:

```text
StateMessage
```

or:

```text
PageState
```

for:

```text
not-found
unexpected error
possibly generic empty informational state
```

But do not over-generalize.

---

# 56. Avoid One Mega-State Component

Do not build:

```tsx
<AppState
  loading
  error
  empty
  notFound
  offline
  success
  warning
  maintenance
  compact
  fullPage
  product
  search
  account
  ...
/>
```

Prefer simple composition.

---

# 57. Component Conventions

Follow:

```text
frontend/design-system/COMPONENTS.md
```

Use semantic variants only.

Do not create arbitrary visual props.

---

# 58. Error Action Semantics

Use:

```text
button
```

for:

```text
Try again
```

when it invokes `reset()`.

Use:

```text
link
```

for:

```text
Return home
```

because it navigates.

Do not interchange them for styling convenience.

---

# 59. Icons

Use:

```text
@mui/icons-material
```

only if an icon materially improves comprehension.

Do not decorate every state with:

```text
warning triangle
sad face
broken chair
magnifying glass
```

by default.

Text and hierarchy should carry meaning.

---

# 60. No Custom Error Illustrations

Do not generate or add decorative 404/error artwork in this phase.

No broken-chair illustration.

No random stock image.

No custom SVG artwork.

---

# 61. Typography

Use the existing typography system.

A not-found/error state may use Young Serif for a major editorial heading if consistent with the theme.

Utility text/actions remain UI sans.

Do not invent typography values.

---

# 62. Color

Use semantic tokens.

Do not make the entire error page red.

Error color should communicate actual error semantics, not dominate the experience.

Not-found is generally neutral, not destructive.

---

# 63. Brand Accent

Do not use brown merely because the state needs visual interest.

The existing restrained accent policy remains in force.

---

# 64. Surfaces

Prefer the established canvas/paper/editorial surfaces.

Do not put error states inside giant floating cards unless a real component hierarchy requires it.

---

# 65. Elevation

No decorative shadow around a 404/error message.

Flat-first design remains authoritative.

---

# 66. Radius

Do not turn failure states into oversized rounded dashboard cards.

---

# 67. Spacing

Use canonical layout spacing.

The existing `ContentContainer` and `SiteSection` should be reused where appropriate rather than recreating page gutters.

Phase 13.7 established those primitives explicitly.

---

# 68. State Width

Failure copy should have a controlled readable measure.

Do not stretch two lines of error text across the entire furniture canvas.

---

# 69. Vertical Composition

The state should feel intentionally positioned within the content area without relying on fragile:

```text
height: calc(100vh - 173px)
```

magic numbers.

Do not couple state layout to hard-coded header/footer heights.

---

# 70. Mobile

Error/not-found/loading states must remain usable on narrow mobile widths.

No clipped large `404`.

No horizontally overflowing buttons.

No fixed widths that exceed the viewport.

---

# 71. Action Layout

On narrow screens, actions may stack if needed.

Do not shrink touch targets to preserve a desktop row.

---

# 72. Accessibility — Error Heading

Unexpected error and not-found states should have a meaningful heading.

Maintain logical heading structure.

Do not rely on giant `404` numerals as the only accessible heading.

---

# 73. Accessibility — Status

Use live-region semantics deliberately.

Do not make the entire error page:

```text
role="alert"
```

without considering how much content will be announced.

Unexpected asynchronous feature errors may need different treatment later.

For route-level error pages, focus/heading semantics may be sufficient.

---

# 74. Accessibility — Focus After Navigation

Normal navigation to a not-found route should follow App Router/browser focus behavior.

Do not introduce aggressive custom focus management unless needed.

Document future feature-level expectations.

---

# 75. Accessibility — Retry

The retry control must have an understandable accessible name.

Avoid:

```text
Retry icon only
```

for the primary recovery action.

---

# 76. Accessibility — Contrast

Verify:

```text
heading
body copy
links
buttons
focus indicators
```

against the actual surfaces used by all state UIs.

---

# 77. Accessibility — Reduced Motion

Loading/retry transitions must honor reduced motion.

---

# 78. Accessibility — Error Identification

For future forms:

```text
field error
→ associated with field

form-level error
→ clear summary/message where appropriate
```

Do not implement forms now.

Record the ownership rule only if not already covered by ACCESSIBILITY.md.

---

# 79. Native HTTP Meaning

Preserve meaningful HTTP semantics where Next.js supports them.

A not-found resource should use the framework's not-found mechanism rather than rendering a normal `200` page that merely says "404".

---

# 80. Do Not Redirect Missing Resources Home

Never implement:

```text
missing product
→ redirect("/")
```

This hides the error and produces confusing SEO/navigation behavior.

Use `notFound()`.

---

# 81. Do Not Redirect All Errors Home

Unexpected errors should remain errors with recovery options.

Do not mask application failures by silently returning users to `/`.

---

# 82. No Automatic Infinite Retry

Do not repeatedly retry failed API requests without explicit policy.

This is particularly important for:

```text
429
5xx
network outage
```

---

# 83. No Automatic Mutation Retry

Never automatically retry non-idempotent mutations as a global error-handling strategy.

Future:

```text
furniture request submission
enquiry submission
payment
```

must define their own idempotency/retry behavior.

---

# 84. Retry-After

Preserve the Phase 13.5 API client's ability to expose headers/status information needed for `Retry-After`.

If it currently does not expose sufficient information, report the precise gap.

Do not silently redesign the client.

---

# 85. API Client Modification Gate

Expected:

```text
API client changed:
NO
```

If a genuine defect prevents correct status classification, STOP and report it unless the correction is trivially within the established 13.5 contract.

Do not casually reopen the transport layer.

---

# 86. Route-Level vs Component-Level Errors

Document:

```text
route-level rendering/data failure
→ route error boundary

recoverable widget failure
→ future local component handling/boundary if justified

form/domain failure
→ feature-specific state
```

Do not force every small failure to blank the entire page.

---

# 87. Nested Error Boundaries

Do not create nested boundaries for routes that do not exist yet.

Group N may introduce more granular boundaries when actual failure domains are known.

---

# 88. Root Boundary Should Stay Generic

The root boundary should not mention:

```text
product
category
search
order
request
```

because it may catch any route.

---

# 89. Not-Found Should Stay Generic

Likewise, the global not-found UI should not say:

```text
Product not found
```

Future product pages can trigger the generic site 404 unless a justified route-specific not-found UI is introduced later.

---

# 90. Error Copy Authority

Keep user-facing copy centralized with the component/boundary that owns it.

Do not scatter equivalent:

```text
Something went wrong
Page failed
Unable to load
```

strings across unrelated files.

---

# 91. Testing — Loading

Test the global loading state structurally.

Verify:

```text
accessible loading indication
no fake page content
no Group N product skeleton
no prohibited animation behavior
```

---

# 92. Testing — Error

Test the error boundary with a controlled test harness.

Verify:

```text
friendly user message
no raw exception shown
retry invokes reset
home navigation works if provided
accessible action semantics
```

Do not add a permanent production route that intentionally throws.

---

# 93. Testing — Not Found

Verify the not-found UI:

```text
has meaningful heading
contains safe home navigation
uses no broken links
uses no Group N product/category content
```

---

# 94. Test `notFound()` Translation

If a helper is introduced for API 404 translation, test:

```text
404 → not-found behavior

401 → NOT not-found
403 → NOT not-found unless API already returns 404
422 → NOT not-found
429 → NOT not-found
500 → NOT not-found
network → NOT not-found
timeout → NOT not-found
abort → NOT not-found
```

---

# 95. Error Privacy Test

Add/check regression protection that user-visible error UI does not expose:

```text
stack
digest
raw Laravel body
exception message
internal numeric IDs
filesystem path
environment variables
headers
```

---

# 96. Shell Regression

Verify:

```text
normal loading
normal not-found
normal route error
```

do not accidentally duplicate:

```text
header
main
footer
```

under the installed Next.js layout behavior.

---

# 97. Request-First Regression

Error-state work must not introduce links to:

```text
/cart
/checkout
/payment
/order-confirmation
/account/orders
```

The Phase 13.7 shell deliberately contains none of these.

---

# 98. Routing Regression

All recovery navigation must comply with:

```text
frontend/web/ROUTING.md
```

No new route taxonomy.

---

# 99. No Search Implementation

Do not make 404/error recovery include a functioning search form.

Search remains Phase 14.5.

A link to `/search` should only be used if the current routing/shell policy considers the reserved route safe to expose; otherwise prefer `/`.

---

# 100. No Homepage Work

The existing `/` remains the foundation placeholder.

Do not redesign it to support error-state work.

Phase 13.7 explicitly left the homepage unimplemented.

---

# 101. No Error Analytics Dependency

Do not install:

```text
Sentry
Bugsnag
Datadog
LogRocket
```

during this phase.

Observability can be added under an explicit future operational requirement.

---

# 102. No Toast Library

Do not install a toast/snackbar library.

MUI already provides primitives if later features need transient feedback.

Global route errors should not be reduced to transient toast messages.

---

# 103. No State Library

Do not add:

```text
Redux
Zustand
Jotai
```

for error/loading state.

App Router boundaries and local state are sufficient.

---

# 104. No Retry Library

Do not add:

```text
React Query
SWR
axios-retry
```

solely for retry handling.

The project intentionally uses native fetch through the Phase 13.5 client.

---

# 105. Existing Dependencies Only

Expected:

```text
New dependencies: NONE
```

---

# 106. Server/Client Boundary

Expected:

```text
loading.tsx
→ Server Component where possible

not-found.tsx
→ Server Component where possible

error.tsx
→ Client Component only because framework requires reset/error boundary behavior
```

Do not expand `'use client'` farther than necessary.

---

# 107. Error Prop Typing

Use the exact error prop type appropriate for the installed Next.js version.

Do not blindly copy:

```ts
error: Error & { digest?: string }
```

without verifying compatibility.

---

# 108. `reset()` Typing

Use the framework-supported reset signature.

No `any`.

---

# 109. No Browser-Only Error Detection

Do not depend on:

```text
window
navigator
localStorage
```

for generic route failure classification.

---

# 110. Offline Detection

Do not implement an offline-mode system during 13.8.

Network failure may use generic unavailable/error treatment.

Offline-specific UX can be added later if requirements justify it.

---

# 111. Error Boundary Styling Dependency

Because `error.tsx` is client-side, ensure styling does not require creating another theme provider.

It should consume the existing provider/theme hierarchy when that hierarchy remains active.

---

# 112. Global Error Fallback Styling

If a `global-error.tsx` is added and normal providers are unavailable, keep its CSS minimal and resilient.

Do not duplicate the full MUI theme inside it.

---

# 113. Avoid Hydration Mismatch

Do not use unstable render-time data in failure states.

Avoid:

```text
random IDs
current timestamps displayed differently server/client
window-dependent copy
```

---

# 114. Error Digest

Do not render `error.digest` publicly by default.

It may be useful for logging/diagnostics later.

---

# 115. Development vs Production

Do not intentionally expose more exception detail in custom user UI merely because:

```text
NODE_ENV=development
```

Next.js already has development tooling.

The production-facing component should remain safe.

---

# 116. Styling Ownership

Prefer reusable state presentation styles/components rather than duplicating visual CSS across:

```text
error.tsx
not-found.tsx
loading.tsx
```

But loading is semantically different enough that it does not need to be forced into the same component.

---

# 117. Generic Page State Primitive

If introduced, keep its API narrow.

Conceptually:

```tsx
<PageMessage
  eyebrow="404"
  title="Page not found"
  description="..."
  actions={...}
/>
```

But avoid raw React-node configuration if it produces an ungoverned mini page-builder.

Use the simplest implementation consistent with existing component conventions.

---

# 118. Error State and SiteSection

Reuse Phase 13.7 primitives where appropriate.

Do not duplicate:

```text
max width
horizontal gutters
section spacing
```

Phase 13.7 already established token-backed `ContentContainer` and `SiteSection`.

---

# 119. State Height

Do not force every error state to fill the viewport.

The shell/footer should remain naturally composed.

A reasonable minimum vertical breathing space may be token-driven, but avoid header-height calculations.

---

# 120. Button Hierarchy

Retry should normally be the primary action for recoverable unexpected errors.

Return-home can be secondary.

Not-found usually makes navigation/home the primary action because retrying the same nonexistent URL is meaningless.

---

# 121. Link Styling

Use the established navigation/link conventions from Phase 12.6.

Do not introduce a new error-page link style.

---

# 122. Loading Indicator Color

Use semantic action/text tokens.

Do not introduce a special brand-brown spinner token.

---

# 123. Error Color Semantics

Reserve error color for genuinely error-related accents.

Not-found is absence/navigation, not destructive failure.

---

# 124. 404 Numeral

If displaying `404`, treat it as secondary context rather than the sole message.

It should not overwhelm the furniture site's visual hierarchy.

---

# 125. Error Boundary Reset Test

Ensure the retry button actually invokes the framework `reset` function once per activation.

No duplicate handler.

No navigation side effect.

---

# 126. Error Boundary Error Object

Do not mutate the provided error object.

---

# 127. Logging Side Effects

If the boundary currently logs to console in development, do not introduce uncontrolled production `console.error` behavior merely for Phase 13.8.

Follow existing repository logging policy.

---

# 128. API Error Headers

If the Phase 13.5 client exposes response headers, preserve them.

If not, do not redesign transport unless future 429 handling is impossible without doing so.

Record any limitation precisely.

---

# 129. Future Rate-Limit UI

Document that future features should use:

```text
Retry-After
```

when provided.

Do not implement countdown timers now.

---

# 130. Future Validation UI

Document that backend field validation errors must eventually map by contract field names.

Do not create a generic parser that guesses field names.

---

# 131. Future Auth UI

Document that auth-required outcomes belong to Clerk/account integration.

Do not add sign-in redirect behavior now.

---

# 132. Future Request/Enquiry Mutation Errors

Furniture Request and General Enquiry are separate domains.

Their future submission errors must remain separate.

Do not build one generic "contact form error" workflow.

---

# 133. Future Payment Errors

Payments remain deferred.

Do not introduce payment failure UI.

---

# 134. Error Boundary Naming

Use conventional Next.js filenames where framework-owned:

```text
error.tsx
loading.tsx
not-found.tsx
global-error.tsx
```

Do not hide framework boundaries behind custom filenames.

---

# 135. Generic Components Naming

If generic supporting components are introduced, use descriptive semantic names.

Avoid:

```text
ErrorThing
FallbackBox
StateStuff
```

---

# 136. No Error Route

Do not create:

```text
/error
/404
/not-found
```

as navigable website routes.

Use framework boundaries.

---

# 137. No Loading Route

Do not create:

```text
/loading
```

---

# 138. No Query-Based Error Simulator

Do not ship:

```text
?error=true
?throw=1
```

production behavior for testing.

Use tests/harnesses.

---

# 139. Visual Verification

Run the application and visually inspect at least:

```text
loading state
not-found state
unexpected-error state
```

at representative:

```text
desktop
mobile
```

If error state requires a test harness, ensure it is not shipped as a public production route.

---

# 140. Not-Found Real Runtime Check

Use a genuinely nonexistent URL such as a random unreserved path during local verification.

Confirm the expected not-found UI is rendered under the installed Next.js behavior.

Do not add a route just for this test.

---

# 141. Error Runtime Check

Use test infrastructure or temporary local-only instrumentation that is removed before completion.

Confirm the actual `error.tsx` boundary behavior, not merely static component rendering.

---

# 142. Loading Runtime Check

Verify loading behavior without committing artificial delays.

If the current placeholder route is too fast to observe naturally, rely on focused tests rather than altering production timing.

---

# 143. Mobile Visual Check

At narrow width verify:

```text
no overflow
heading wraps
actions remain usable
touch targets remain adequate
404 does not clip
```

---

# 144. Shell Visual Check

Ensure:

```text
header
main
footer
```

still compose correctly around normal not-found/loading states.

---

# 145. Focus Visual Check

Keyboard through recovery actions.

Visible focus must use the established focus treatment.

---

# 146. Contrast Check

Validate the chosen state surfaces/text/actions using existing accessibility tooling.

---

# 147. Automated Contract Tests

Add focused tests or extend existing contract tests for:

```text
framework boundary files
server/client boundary expectations
safe copy
retry behavior
home link
no forbidden commerce links
no raw errors
no unauthorized icons
no arbitrary values
```

Use existing infrastructure.

---

# 148. API Client Regression

Run the Phase 13.5 API client tests.

Expected:

```text
PASS
```

No API client regressions.

---

# 149. Layout Regression

Run the Phase 13.7 layout contract tests.

The execution record currently reports those tests passing.

Expected after 13.8:

```text
PASS
```

---

# 150. Theme Regression

Run the theme contract.

Expected:

```text
PASS
```

---

# 151. Design-System Validation

Run existing:

```text
token/reference validation
component convention validation
accessibility validation
icon-policy audit
```

where available.

---

# 152. TypeScript

Run the actual repository typecheck.

Must pass.

---

# 153. ESLint

Run the actual lint command.

Must pass.

---

# 154. Production Build

Run the actual production build command from `package.json`.

Must pass.

---

# 155. `git diff --check`

Must pass.

---

# 156. Likely File Structure

Inspect existing conventions first.

Possible files:

```text
frontend/web/app/
├── loading.tsx
├── error.tsx
└── not-found.tsx
```

Potential supporting components:

```text
frontend/web/components/states/
├── page-message.tsx
└── route-loading.tsx
```

Do not mechanically create these exact files.

Only introduce abstractions justified by implementation.

`global-error.tsx` is optional and requires explicit technical justification.

---

# 157. Do Not Modify Group N Routes

Expected:

```text
app/products/*       NONE
app/categories/*     NONE
app/search/*         NONE
```

unless such files already existed before this phase.

Do not create them.

---

# 158. Documentation

Update durable guidance where needed.

Likely:

```text
frontend/AGENTS.md
phases/group-M-phases.md
```

Do not duplicate this entire prompt.

Durable rules should include the failure taxonomy and ownership boundaries.

---

# 159. ADR

Add an ADR only if Phase 13.8 establishes a material architectural decision requiring historical explanation.

A possible decision:

```text
WEB-003 — App Router Failure-State Architecture
```

could capture:

```text
route boundary ownership
404 translation
expected vs unexpected failures
API client remains framework-agnostic
retry semantics
```

Only add it if useful.

---

# 160. Completion Report

Return:

```text
Phase 13.8 status:
PASS / BLOCKED


BOUNDARIES

loading.tsx:
<path / NOT ADDED + reason>

error.tsx:
<path / NOT ADDED + reason>

not-found.tsx:
<path / NOT ADDED + reason>

global-error.tsx:
<path / NOT ADDED + technical justification>

Installed Next.js version verified:
YES / NO


FAILURE TAXONOMY

Loading:
<rule>

Not found:
<rule>

Expected API/domain failure:
<rule>

Unexpected error:
<rule>

Empty result treated as 404:
NO / FAIL

MADE_TO_ORDER treated as error:
NO / FAIL


LOADING

Shell preserved while page pending:
YES / NO

Global loading treatment:
<summary>

Product skeleton implemented:
NO / FAIL

Category skeleton implemented:
NO / FAIL

Search skeleton implemented:
NO / FAIL

Artificial delay:
NONE / FAIL

Reduced motion:
PASS / FAIL

Accessible status:
PASS / FAIL


ERROR

Unexpected error presentation:
<summary>

Raw exception exposed:
NO / FAIL

Stack exposed:
NO / FAIL

Digest exposed:
NO / FAIL

Retry:
<implementation>

Retry uses framework recovery:
YES / NO

window.location.reload:
NO / <reason>

Home navigation:
<summary>

Error boundary client scope:
<summary>


NOT FOUND

Canonical 404 presentation:
<summary>

Uses framework not-found mechanism:
YES / NO

Safe home navigation:
YES / NO

Fake product recommendations:
NONE / FAIL

Missing resources redirected home:
NO / FAIL


API ERROR MAPPING

API client changed:
NO / <reason>

404:
notFound() at page/domain integration

401:
<documented ownership>

403:
<documented ownership>

422:
<documented ownership>

429:
<documented ownership>

5xx:
<documented ownership>

Network:
<documented ownership>

Timeout:
<documented ownership>

Caller abort:
<documented ownership>

Status detected through typed/status-bearing data:
YES / NO

Magic message matching:
NONE / FAIL


SECURITY / PRIVACY

Raw Laravel response rendered:
NO

Internal IDs rendered:
NO

Environment data rendered:
NO

Headers rendered:
NO

Stack/path rendered:
NO

Secrets rendered:
NO


ACCESSIBILITY

Meaningful state headings:
PASS / FAIL

Retry semantics:
PASS / FAIL

Link/button semantics:
PASS / FAIL

Keyboard:
PASS / FAIL

Visible focus:
PASS / FAIL

Contrast:
PASS / FAIL

Mobile reflow:
PASS / FAIL

Reduced motion:
PASS / FAIL

Live regions restrained:
PASS / FAIL


SERVER / CLIENT

Root layout remains Server Component:
YES / NO

Site shell remains server-first:
YES / NO

error.tsx client boundary isolated:
YES / NO

not-found server-capable:
YES / NO

loading server-capable:
YES / NO

New global client state:
NONE / FAIL


SCOPE

Homepage implemented:
NO

Product routes implemented:
NO

Category routes implemented:
NO

Search implemented:
NO

Filters/sorting implemented:
NO

Clerk integrated:
NO

Form validation UI implemented:
NO

Payment failure UI implemented:
NO

Backend/API changed:
NO

Flutter changed:
NO

Dependencies added:
NONE / <list>


RUNTIME VERIFICATION

Real nonexistent URL:
PASS / FAIL

Error boundary behavior:
PASS / FAIL

Loading behavior:
PASS / FAIL

Desktop visual:
PASS / FAIL

Mobile visual:
PASS / FAIL

Keyboard/focus:
PASS / FAIL


REGRESSION

API client:
PASS / FAIL

Layout contract:
PASS / FAIL

Routing contract:
PASS / FAIL

Request-first:
PASS / FAIL

Theme contract:
PASS / FAIL

Design-system validation:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


DOCUMENTATION

Failure-state guidance:
<path>

Group M execution record:
PASS / FAIL

frontend/AGENTS.md:
<updated / unchanged>

ADR:
<id / NONE>


FILES CHANGED

<list>


GIT

git-workflow-and-versioning skill read:
YES / NO

Operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 13.8:
PASS / BLOCKED

Phase 13.9:
READY / BLOCKED
```

---

# 161. STOP Condition

Phase 13.8 may be declared PASS only when:

- loading, not-found, expected failure, and unexpected error are explicitly distinguished;
- App Router boundary implementation matches the installed Next.js version;
- a canonical not-found state exists;
- a canonical unexpected-error boundary exists;
- route-level loading behavior is intentionally defined;
- normal loading preserves the website shell where appropriate;
- global/root failure behavior is understood rather than guessed;
- the root layout remains a Server Component;
- the site shell remains server-first;
- any required error Client Component is narrowly isolated;
- no raw exception, Laravel body, stack, path, secret, environment data, or internal identifier is exposed;
- retry uses appropriate framework recovery semantics;
- retry does not establish unsafe mutation retry policy;
- API client remains transport/framework agnostic;
- API 404 can be intentionally translated to `notFound()` by future page/domain integration;
- 401/403/422/429/5xx/network/timeout/abort are not incorrectly translated to 404;
- empty result sets are not treated as not-found;
- MADE_TO_ORDER is not treated as failure;
- `Retry-After` ownership is documented for future rate-limit UI;
- no artificial delays exist;
- no product/category/search-specific skeletons were prematurely implemented;
- no Group N routes/pages were created;
- no Clerk/auth behavior was introduced;
- no payment/error workflow was introduced;
- state UI consumes existing layout/theme/design-system foundations;
- mobile reflow passes;
- keyboard/focus passes;
- contrast passes;
- reduced-motion behavior passes;
- a real unknown URL renders the canonical not-found state;
- actual error-boundary behavior is verified;
- layout/API/routing/request-first/theme regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- no new dependencies were added;
- Git operations follow `git-workflow-and-versioning`.

If the API client cannot reliably distinguish status-bearing API failures from network/timeout/abort failures, report:

```text
Phase 13.8 — BLOCKED

Reason:
The Phase 13.5 transport contract does not expose enough structured
failure information to implement safe routing/error classification.

Observed behavior:
<exact implementation>

Required correction:
<smallest transport-contract correction>
```

Do not use error-message string matching as a workaround.

Otherwise finish with:

```text
Phase 13.8 — PASS
Phase 13.9 — READY
```

Do not start Phase 13.9 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

# Phase 13.8 Execution Record

**Status:** PASS. Phase 13.9 is READY and was not started.

## Boundaries

- `app/loading.tsx` — added, Server Component; renders inside the site shell; generic shell-preserving pending state.
- `app/not-found.tsx` — added, Server Component; generic public 404 rendered through the framework not-found mechanism.
- `app/error.tsx` — added, Client Component; isolated unexpected-error boundary using the Next 16.3.8 `retry` prop.
- `app/global-error.tsx` — NOT added: the root layout is minimal, Next.js already provides a built-in root-layout 500 fallback, and a custom global error would replace the document and lose the theme/providers without demonstrated need.
- Installed Next.js 16.3.8 verified against `node_modules/next/dist/docs/` before implementation.

## Failure Taxonomy

- Loading: pending route segment; generic restrained status; shell persists.
- Not found: expected public absence; framework `notFound()`; generic copy; no product/category specifics.
- Expected API/domain failure: owned by the feature (validation, auth, rate limit, empty results, MADE_TO_ORDER); never the error boundary.
- Unexpected error: `app/error.tsx`; calm copy; framework `retry`; safe home route.
- Empty results are not 404; MADE_TO_ORDER is not treated as failure.

## Implementation

- Shared `components/states/page-message.tsx` (`PageMessage`, `PageMessageLink`) reused by error/not-found; consumes `SiteSection` and design tokens.
- Loading uses static neutral bars plus a concise `role="status"` "Loading…"; no animation, no fake page content, no artificial delay.
- Error copy: "We couldn't load this page." + "Try again" (primary, framework retry) + "Return home" (secondary link).
- Not-found copy: "404" eyebrow + "We couldn't find that page." + "Return home".
- API client unchanged; typed `ApiError.status` / `ApiTransportError.kind` already distinguish 404 from 401/403/422/429/5xx/network/timeout/abort (Phase 13.5 tests confirm).

## Accessibility

- Meaningful `h1` per state; button (retry) vs link (home) semantics preserved; skip link and shell landmarks intact; visible focus via the theme focus ring; approved token contrast; mobile reflow passes at 320/390; no motion beyond tokens.

## Runtime Verification

- `GET /definitely-not-a-real-page-xyz` → HTTP 404 with the canonical not-found UI.
- Temporary dynamic `probe-error` route (removed before completion) → hydrated `error.tsx` UI verified; no raw exception exposed.
- Temporary dynamic `probe-loading` route (removed before completion) → `loading.tsx` rendered with shell preserved at desktop and mobile.
- Single header/main/footer on loading, not-found, and error states.

## Validation

```text
States contract: PASS
Layout contract: PASS
Theme contract: PASS
API client regression: PASS (17 tests)
TypeScript: PASS
ESLint: PASS
Production build: PASS
git diff --check: PASS
New dependencies: NONE
```
