# Phase 13.9 — Responsive Foundation

## Objective

Establish, verify, and document the canonical responsive foundation for the SL Furnitures Next.js + MUI public website.

This is the final phase of **Group M — Website Foundation**.

The phase must ensure that future Group N pages can be built on one coherent responsive system rather than inventing page-specific breakpoint strategies.

The responsive model must preserve the project's established principle:

> The website and mobile application share a visual language, but the website must respond naturally as a website. Desktop must not be forced to behave like mobile, and mobile must not be treated as a compressed desktop.

The foundation should support:

```text
small mobile
larger mobile
tablet
narrow desktop
desktop
wide desktop
```

through the existing canonical design-system breakpoints and layout primitives.

This phase owns **responsive infrastructure, conventions, verification, and foundation behavior**.

It does NOT own final responsive designs for pages that do not exist yet.

---

# 1. Phase Scope

Implement only:

```text
Phase 13.9 — Responsive Foundation
```

This phase should:

- audit the existing canonical breakpoints;
- verify the MUI breakpoint mapping;
- formalize responsive ownership rules;
- audit the Phase 13.7 shell across viewport ranges;
- audit Phase 13.8 state pages across viewport ranges;
- verify site gutters and content widths;
- verify contained/full-bleed behavior;
- verify typography behavior under responsive layouts;
- verify navigation transitions between mobile and desktop;
- verify touch/pointer behavior;
- verify reflow and zoom;
- verify horizontal-overflow prevention;
- define responsive image/layout responsibilities for future pages;
- define responsive page-composition rules;
- establish testing viewport classes;
- establish anti-breakpoint-drift rules;
- close Group M.

Do not use this phase as an excuse to build storefront pages.

---

# 2. Read Authorities Before Coding

Inspect at minimum:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── COMPONENTS.md
├── ACCESSIBILITY.md
├── tokens.css
├── design-tokens.json
├── tailwind-v4.css
└── relevant manifests/reference fixtures

frontend/web/
├── app/
├── components/layout/
├── components/states/
├── lib/api/
├── theme/
├── ROUTING.md
├── package.json
├── tsconfig.json
└── next.config.*

docs/decisions.md
phases/group-M-phases.md
```

Inspect the actual Phase 13.7 and 13.8 implementations.

Do not redesign compliant code merely to make Phase 13.9 substantial.

---

# 3. Git Workflow

Before ANY Git command:

```text
locate and read:
git-workflow-and-versioning
```

Follow the skill exactly.

Preserve unrelated owner changes.

Never commit:

```text
.env
.env.local
credentials
secrets
tokens
local SDK state
temporary screenshots
browser artifacts
```

unless a repository policy explicitly owns particular generated artifacts.

Stage only Phase 13.9 work.

---

# 4. Preserve Completed Group M Architecture

The following are already established:

```text
13.1 Next.js setup
13.2 TypeScript
13.3 MUI integration
13.4 Theme integration
13.5 API client
13.6 Routing conventions
13.7 Layout system
13.8 Error/loading/not-found handling
```

Do NOT:

```text
re-scaffold Next.js
replace MUI
recreate the theme
replace the API client
redesign routing
replace SiteShell
create a second layout system
replace failure boundaries
```

unless inspection reveals an actual defect.

---

# 5. Phase Philosophy

Responsive design is not:

```text
desktop
↓
shrink everything
↓
mobile
```

It is:

```text
same design language
+
different composition according to available space
```

Preserve hierarchy and meaning while allowing composition to change.

---

# 6. Breakpoint Authority

There must be one canonical breakpoint authority.

Inspect:

```text
frontend/design-system/tokens.css
frontend/design-system/design-tokens.json
frontend/web/theme/
```

Determine exactly which breakpoint values are authoritative and how MUI consumes them.

Do not invent a new breakpoint scale.

---

# 7. No Parallel Breakpoint Systems

The project must NOT end up with:

```text
design-system breakpoints
+
MUI default breakpoints
+
page CSS breakpoints
+
component-specific arbitrary breakpoints
```

as independent authorities.

The design-system breakpoint contract remains canonical.

MUI maps to it.

Components consume MUI/theme mappings or canonical CSS variables where appropriate.

---

# 8. Breakpoint Mapping Audit

Verify every MUI breakpoint:

```text
xs
sm
md
lg
xl
```

or whatever mapping the existing theme actually defines.

Record:

```text
semantic breakpoint
canonical token
resolved value
MUI mapping
```

Do not assume MUI defaults survived Phase 12.3.

Inspect actual implementation.

---

# 9. Breakpoints Are Transition Tools

Do not treat breakpoint names as device detection.

For example:

```text
md
```

does not mean:

```text
iPad
```

It means a layout threshold.

Document this explicitly.

---

# 10. No Device-Specific CSS

Do not introduce rules such as:

```text
iPhone
Samsung
iPad Pro
MacBook
```

as layout concepts.

Responsive behavior should depend on available space and input/accessibility characteristics where justified.

---

# 11. Responsive Viewport Classes

Define a canonical **testing matrix**, not another CSS breakpoint authority.

At minimum test representative widths for:

```text
narrow mobile
standard mobile
large mobile
tablet
narrow desktop
desktop
wide desktop
```

A reasonable audit matrix may include existing tested widths such as:

```text
320
360/390
640
960
1024
1440
```

plus a wide desktop viewport if useful.

These are test points.

They must NOT become new layout breakpoints simply because they are test widths.

---

# 12. Existing Phase 13.7 Evidence

Phase 13.7 already verified:

```text
320
360
390
640
960
1024
1440
```

without horizontal overflow.

Phase 13.9 should turn that point-in-time verification into a durable responsive policy and regression strategy.

Do not merely repeat screenshots and call the phase complete.

---

# 13. Mobile-First vs Desktop-First

Inspect current styling strategy.

Prefer responsive rules that are understandable and minimize overrides.

Do not mechanically rewrite every existing component to "mobile first" if it is already clean and correct.

The goal is coherence, not ideological CSS rewriting.

---

# 14. Site Canvas

Define the relationship between:

```text
viewport
site canvas
content container
section
readable text measure
full-bleed media
```

Future pages must know which layer owns width.

---

# 15. ContentContainer Authority

Phase 13.7 established:

```text
ContentContainer
```

as the horizontal site geometry authority.

Audit it carefully.

It should own:

```text
maximum content width
horizontal centering
responsive gutters
```

Pages should not recreate those concerns.

---

# 16. Responsive Gutters

Formalize site gutter behavior across the canonical breakpoint scale.

Use existing spacing tokens.

Do not invent page-specific values such as:

```text
homepage padding = 27px
product page padding = 31px
category page padding = 22px
```

The global container owns ordinary site gutters.

---

# 17. Gutter Philosophy

Mobile:

```text
enough edge breathing room
without wasting scarce width
```

Tablet:

```text
comfortable transition
without behaving like stretched mobile
```

Desktop:

```text
generous architectural spacing
without unnecessarily narrowing furniture imagery
```

Wide desktop:

```text
controlled maximum canvas
rather than infinitely stretched content
```

---

# 18. Full-Bleed Sections

Phase 13.7 established:

```text
SiteSection width="full"
```

and contained composition.

Verify full-bleed sections:

- reach intended viewport edges;
- do not create horizontal scroll;
- can contain a nested `ContentContainer`;
- do not require negative-margin hacks;
- remain compatible with mobile gutters.

---

# 19. Contained Sections

Verify contained sections use the canonical content geometry.

No page should need:

```css
width: 92%;
margin: 0 auto;
```

to imitate the container.

---

# 20. Nested Container Audit

Prevent accidental:

```text
ContentContainer
  └── ContentContainer
```

from doubling gutters.

Document when nesting is prohibited or intentionally supported.

---

# 21. Section Spacing

Responsive vertical spacing should use the canonical spacing system.

Future pages may need tighter section rhythm on smaller screens and more breathing room on larger screens.

Do not create an arbitrary responsive section-spacing scale if existing tokens already support this.

---

# 22. Typography Responsive Audit

Group L owns typography.

Phase 13.9 owns ensuring responsive layouts do not misuse it.

Verify:

```text
display headings
page headings
body copy
labels
metadata
navigation
```

behave at narrow widths.

Do not introduce arbitrary responsive font sizes.

---

# 23. Canonical Type Scale

Preserve:

```text
12
14
16
20
24
32
48
96
```

as the approved type scale.

Responsive typography should choose appropriately from the approved scale.

Do not introduce:

```text
18
22
28
36
40
56
72
```

because a particular viewport seems to need them.

---

# 24. Display Typography

Young Serif display typography must not become unusably large on mobile.

Use approved responsive mappings.

Do not solve overflow by:

```text
transform: scale()
```

or compressed letter spacing.

---

# 25. No `clamp()` Drift

Do not introduce arbitrary fluid typography via:

```css
clamp(...)
```

unless the design system already formally supports it.

The existing type scale remains authoritative.

---

# 26. Text Reflow

Test long realistic text strings.

Ensure:

```text
navigation
headings
button labels
footer labels
state messages
```

can wrap/reflow appropriately.

Do not optimize only for short fixture text.

---

# 27. Long Word Handling

Avoid global aggressive:

```css
word-break: break-all;
```

Use appropriate overflow/wrapping behavior.

URLs or unusual identifiers should not destroy page geometry.

---

# 28. Zoom

Verify the website at meaningful browser zoom.

At minimum audit behavior equivalent to:

```text
200% zoom
```

for core shell/state functionality where practical.

The site must remain usable without horizontal two-dimensional scrolling for ordinary text content except where inherently necessary.

---

# 29. Text Scaling

Do not assume browser zoom is the only accessibility transformation.

Layouts should tolerate enlarged text without:

```text
clipping
overlap
hidden actions
fixed-height breakage
```

---

# 30. Fixed Heights Audit

Search website foundation code for fixed heights.

For each one determine whether it is genuinely necessary.

Avoid fixed heights for:

```text
header text rows
navigation labels
error messages
footer groups
content sections
```

where content-driven sizing is safer.

---

# 31. Min-Height vs Height

Prefer:

```text
min-height
```

where a visual minimum is desired but content may grow.

Do not convert the shell into fixed pixel bands.

---

# 32. Header Responsive Audit

Audit the Phase 13.7 header across the entire width range.

Verify:

```text
logo integrity
search affordance
navigation visibility
menu trigger
touch targets
focus
no overlap
no wrapping chaos
```

---

# 33. Desktop Navigation Transition

The transition between:

```text
mobile navigation
↔
desktop category navigation
```

must occur at a canonical breakpoint.

No arbitrary one-off media query.

---

# 34. Boundary Width Test

Test immediately around the navigation transition breakpoint.

For example conceptually:

```text
breakpoint - 1px
breakpoint
breakpoint + 1px
```

Verify there is no width where:

```text
both navigations disappear
both navigations become interactively active
controls overlap
layout jumps incorrectly
```

---

# 35. Hidden Navigation Accessibility

If CSS hides one navigation mode while the other is active, verify hidden interactive elements cannot receive keyboard focus.

Do not rely solely on:

```text
visibility
opacity
```

if it leaves invisible controls interactive.

---

# 36. Mobile Navigation

Re-audit the MUI Drawer from Phase 13.7.

Verify:

```text
320px width
large text
long category labels
viewport-height constraints
keyboard
Escape
focus restoration
scrolling
```

---

# 37. Drawer Width

Do not hard-code a drawer width that breaks narrow devices.

Use a responsive strategy that preserves an appropriate viewport margin if needed.

---

# 38. Safe Viewport Height

Mobile browser chrome changes viewport height.

Avoid fragile:

```text
height: 100vh
```

assumptions where dynamic viewport behavior matters.

Use modern viewport units only if supported by the project's browser target and actually needed.

Do not introduce them gratuitously.

---

# 39. Orientation

The site should remain usable in both portrait and landscape mobile/tablet orientation.

Do not create orientation-specific layouts unless genuinely required.

---

# 40. Search Affordance

The current shell exposes search without implementing search functionality.

Ensure the affordance remains discoverable across widths.

Do not implement Phase 14.5.

---

# 41. Footer Responsive Audit

Verify the Phase 13.7 footer at:

```text
narrow mobile
large mobile
tablet
desktop
wide desktop
```

Check:

```text
group wrapping
reading order
link spacing
touch targets
brand placement
copyright
```

---

# 42. Footer DOM Order

Responsive visual reflow must not create a nonsensical keyboard/screen-reader order.

Prefer DOM order that works naturally across viewport sizes.

---

# 43. Footer Columns

Do not force multi-column footer groups on widths that cannot support them.

Likewise do not leave desktop footer content as an unnecessarily long single column.

Use canonical responsive layout behavior.

---

# 44. State Pages

Audit Phase 13.8:

```text
loading
not-found
error
```

across the viewport matrix.

Verify:

```text
heading wraps
copy remains readable
actions reflow
404 does not clip
no fixed-width state card
no horizontal overflow
```

---

# 45. Error Action Reflow

Where retry/home actions share a row on larger widths, allow them to stack naturally when space is insufficient.

Do not shrink touch targets or typography to preserve a row.

---

# 46. Pointer vs Touch

Do not assume:

```text
desktop = mouse
mobile = touch
```

Hybrid devices exist.

Core functionality must not depend exclusively on hover.

---

# 47. Hover

Hover may enhance:

```text
links
buttons
navigation
```

but must never be required to reveal essential functionality without keyboard/touch alternatives.

---

# 48. `hover` / `pointer` Media Features

If using CSS interaction media queries such as:

```css
@media (hover: hover)
```

use them only where they improve interaction behavior.

Do not create a second responsive layout system from input media features.

---

# 49. Touch Targets

Revalidate interactive targets against the accessibility baseline.

At minimum inspect:

```text
menu trigger
drawer close
search affordance
navigation links
footer links
error actions
```

Do not reduce target sizes at narrow widths to fit content.

---

# 50. Responsive Images — Foundation Rule

Group N owns actual catalog/page images.

Phase 13.9 should establish only the responsive image responsibility contract.

Future images must:

- reserve stable geometry;
- avoid distortion;
- use meaningful responsive sizing;
- avoid downloading desktop-sized assets unnecessarily on mobile;
- preserve crop intent;
- use Next.js image capabilities appropriately where relevant.

Do NOT implement product image optimization now.

---

# 51. Image Aspect Ratios

Future component phases should own domain-specific ratios.

Do not globally decree:

```text
all product images = 1:1
```

unless the design system already does.

Furniture imagery may require different compositions.

---

# 52. Logo Responsiveness

Verify the official logo remains:

```text
undistorted
legible
stable
appropriately sized
```

across shell breakpoints.

Do not create alternate/redrawn mobile logos.

---

# 53. Responsive Grid Foundation

Do not build the product grid.

However, document the rule that future grids should use:

```text
CSS Grid / MUI responsive layout
+
canonical breakpoints
+
canonical spacing
```

rather than JavaScript viewport detection.

---

# 54. Product Grid Ownership

The actual:

```text
column count
product card width
filter/grid relationship
```

belongs to:

```text
14.3 Product listing
14.6 Filters/sorting
```

Do not decide those here.

---

# 55. Homepage Ownership

Do not decide the final responsive:

```text
hero height
hero crop
category tile count
featured-product grid
editorial split
Made-to-Order section
```

Those belong to Phase 14.1.

---

# 56. Category Page Ownership

Do not implement responsive category composition.

Phase 14.2 owns it.

---

# 57. Product Detail Ownership

Do not implement:

```text
desktop image gallery + sticky information panel
mobile gallery + stacked product info
```

yet.

Phase 14.4 owns the final product-detail composition.

---

# 58. Search Ownership

Do not implement responsive search results or mobile search overlays.

Phase 14.5 owns search.

---

# 59. Filter Ownership

Do not implement:

```text
desktop filter sidebar
mobile filter drawer
sort sheet
```

during 13.9.

Phase 14.6 owns them.

---

# 60. CSS vs JavaScript Responsiveness

Layout responsiveness should primarily use:

```text
CSS
MUI breakpoint styling
```

not JavaScript viewport inspection.

Do not use:

```ts
window.innerWidth
```

to determine layout during render.

---

# 61. `useMediaQuery`

Use MUI `useMediaQuery` only when behavior—not merely styling—genuinely requires JavaScript breakpoint awareness.

Do not use it for ordinary layout changes that CSS can solve.

---

# 62. Server-First Constraint

Responsive foundation must not cause server-capable components to become Client Components merely to determine viewport size.

This is critical.

Do not add `'use client'` to:

```text
SiteShell
SiteHeader
SiteFooter
ContentContainer
SiteSection
root layout
not-found
loading
```

merely for responsiveness.

---

# 63. Hydration

Do not render materially different initial markup based on unknown client viewport in a way that creates hydration mismatch.

Prefer CSS-driven responsive presentation.

---

# 64. Client Boundary Audit

After implementation, report every:

```text
"use client"
```

within website foundation code.

Verify each is still justified.

Do not expand client surface unnecessarily.

---

# 65. CSS Ownership

Inspect where responsive styles currently live.

Keep them close to the component/layout responsibility that owns them.

Do not create a giant:

```text
responsive.css
```

containing unrelated site rules unless the existing architecture already requires that pattern.

---

# 66. No Utility CSS Framework

Tailwind application pipeline was deliberately removed in Phase 13.1–13.4 reconciliation.

Do not reintroduce Tailwind.

Do not add another utility CSS framework.

---

# 67. MUI `sx`

Follow `COMPONENTS.md`.

Responsive `sx` syntax is acceptable when it:

- uses theme breakpoint keys;
- uses theme/token values;
- remains local to the owning component;
- does not become a dumping ground for page design.

---

# 68. No Raw Media Queries When Theme Mapping Suffices

Prefer canonical theme breakpoint APIs.

Raw `@media (min-width: 947px)` is prohibited when an approved breakpoint already expresses the transition.

---

# 69. Raw Media Query Exceptions

Raw media features remain appropriate for things such as:

```text
prefers-reduced-motion
hover
pointer
forced-colors
```

when semantically required.

Do not force those concepts into width breakpoints.

---

# 70. No Magic Responsive Values

Audit for arbitrary values introduced specifically for responsive fixes.

Examples to avoid:

```text
max-width: 1173px
padding: 23px
gap: 29px
top: 71px
```

when approved tokens exist.

---

# 71. Overflow Audit

Search for:

```text
overflow-x: hidden
```

used globally.

Do not use global overflow clipping to hide layout defects.

If horizontal overflow exists, fix the source.

---

# 72. Body Overflow

The page should not rely on:

```css
body {
  overflow-x: hidden;
}
```

as a responsive strategy.

Modal/drawer scroll locking through MUI is separate and acceptable.

---

# 73. Flex Min-Width

Audit flex/grid children for common overflow problems.

Where necessary:

```css
min-width: 0;
```

may be appropriate.

Use it intentionally, not globally.

---

# 74. Long Navigation Labels

Test actual longer taxonomy labels, including existing concepts such as:

```text
Dining Room & Kitchen
Entryway & Hallway
Hybrid & Multi-purpose
```

where relevant to fixtures/audits.

Do not shorten authoritative category names merely to make the layout pass.

---

# 75. No Text Truncation by Default

Do not solve ordinary layout problems by ellipsizing:

```text
headings
navigation
button labels
category names
```

unless the component semantics genuinely permit truncation.

---

# 76. Internationalization Resilience

The application may not implement i18n in V1, but avoid layouts that work only because current English strings are short.

Do not implement localization infrastructure.

Just avoid fragile fixed-width assumptions.

---

# 77. Tables

No current public foundation should require data tables.

Do not build responsive table infrastructure preemptively.

---

# 78. Forms

Future request/enquiry/account forms must inherit responsive field/container behavior from the design system.

Do not implement actual forms now.

Document only broad foundation rules if missing.

---

# 79. Form Width Rule

Future forms should use controlled readable widths rather than spanning the entire wide desktop canvas.

Do not establish furniture-request-specific form widths yet.

---

# 80. Dialogs and Drawers

Existing MUI overlay primitives should remain responsive by default.

Audit the current mobile navigation drawer.

Do not create a custom modal responsive system.

---

# 81. Safe Areas

Do not add broad safe-area padding everywhere.

If mobile overlays require:

```css
env(safe-area-inset-*)
```

inspect whether the current UI genuinely needs it and whether browser/device support justifies it.

Do not introduce device-specific hacks.

---

# 82. Scrollbars

Responsive geometry should tolerate scrollbar width.

Do not calculate layouts assuming:

```text
100vw == content width
```

where that could cause overflow.

Prefer `width: 100%` for ordinary containers.

---

# 83. `100vw`

Audit use of:

```css
width: 100vw;
```

It often creates unnecessary horizontal overflow.

Use only where genuinely required.

---

# 84. Full-Bleed Without `100vw` Hacks

The SiteSection architecture should support full-bleed content naturally.

Do not introduce:

```css
margin-left: calc(50% - 50vw);
```

unless there is no cleaner architecture and the decision is justified.

---

# 85. Header Positioning

If the header remains sticky, verify it across:

```text
mobile
tablet
desktop
zoomed content
```

Ensure enlarged navigation content is not clipped.

---

# 86. Sticky Content

Do not introduce additional sticky page content during this phase.

Future product/filter phases own their sticky behavior.

---

# 87. Scroll Offset

If sticky header behavior affects future anchor navigation, document the concern.

Do not build an anchor-offset framework without actual anchors.

---

# 88. Responsive Motion

Responsive layout transitions should not animate dramatically simply because a viewport changes.

Do not animate between breakpoint layouts.

---

# 89. Resize Behavior

Desktop browser resizing should not produce:

```text
overlap
stale drawer state problems
invisible focused controls
duplicate visible navigation
```

Audit this.

---

# 90. Drawer State Across Resize

If the mobile drawer is open and the viewport crosses into desktop mode, inspect current behavior.

Ensure the UI does not become confusing or trap focus.

Implement the smallest robust correction if needed.

Do not add global responsive state management.

---

# 91. Navigation Duplication

Verify mobile and desktop navigation do not both become active at transition widths.

This includes:

```text
visual display
keyboard focusability
accessibility tree
```

---

# 92. Breakpoint Boundary Tests

Add regression coverage around important shell transition breakpoints.

Do not test every pixel.

Test the boundaries that materially change structure.

---

# 93. Container Contract Tests

Add/extend tests for:

```text
canonical max width
canonical gutters
full vs contained section behavior
no arbitrary second container authority
```

Prefer contract tests consistent with existing foundation tests.

---

# 94. Breakpoint Contract Test

Add a test ensuring MUI breakpoint values remain synchronized with canonical design tokens.

If such a test already exists from Phase 12.3, extend/reuse it rather than duplicate it.

---

# 95. No MUI Default Drift

The test should catch accidental reversion to default MUI breakpoints if the project uses customized values.

---

# 96. Raw Breakpoint Audit

Audit foundation application code for unapproved raw width media queries.

Approved design-system/token definitions themselves are not violations.

Report exceptions explicitly.

---

# 97. Raw Spacing Audit

Audit responsive code for ad-hoc spacing values.

Do not falsely flag legitimate primitive token definitions.

---

# 98. Horizontal Overflow Automated Check

Where practical, retain/extend the Phase 13.7 browser check that verifies:

```js
document.documentElement.scrollWidth <= window.innerWidth
```

across representative widths.

Do not rely exclusively on screenshots.

---

# 99. Screenshot Verification

Run representative screenshots or browser inspection for:

```text
320
390
tablet
narrow desktop
1440
wide desktop
```

Exact widths may align with the canonical audit matrix.

Inspect actual rendered output.

---

# 100. Screenshot Purpose

Screenshots are for verification.

Do not commit generated screenshots unless repository policy explicitly requires them.

---

# 101. Visual Audit — Mobile

Verify:

```text
logo proportion
menu trigger
search affordance
drawer
main gutter
state pages
footer
focus
no overflow
```

---

# 102. Visual Audit — Tablet

Tablet must not feel like an awkward enlarged phone.

Verify:

```text
header transition
content width
footer grouping
spacing rhythm
```

---

# 103. Visual Audit — Narrow Desktop

This is often the highest-risk range.

Verify:

```text
category navigation fits
search/header controls fit
no accidental wrapping
content gutters remain appropriate
footer columns fit
```

---

# 104. Visual Audit — Desktop

Verify the shell has enough whitespace and architectural presence.

Do not make it unnecessarily dense merely because more space is available.

---

# 105. Visual Audit — Wide Desktop

Ensure:

```text
content does not stretch infinitely
navigation remains coherent
footer does not become excessively sparse
```

Photography will later be allowed intentional full-bleed behavior.

---

# 106. Real Content Stress Test

Use representative fixture text only where necessary.

Test:

```text
long category labels
long heading
multi-line state copy
footer labels
```

Do not build fake Group N content.

---

# 107. Keyboard Audit

Across responsive modes verify:

```text
skip link
logo
navigation
menu trigger
drawer
search affordance
state actions
footer
```

Keyboard behavior must remain logical.

---

# 108. Focus Audit

Verify no focus indicator is clipped by:

```text
overflow
sticky containers
drawer edges
tight layout
```

---

# 109. Focus After Responsive Change

Do not create elaborate viewport-resize focus management.

But ensure CSS breakpoint changes do not leave focus permanently inside invisible UI under normal interaction.

---

# 110. Accessibility Reflow

Audit against the Phase 12.7 accessibility authority.

At minimum verify:

```text
zoom
text enlargement
reflow
touch targets
focus visibility
logical DOM order
no color-only responsive states
```

---

# 111. Reduced Motion

Run the existing reduced-motion regression.

Responsive foundation must not introduce new animation.

---

# 112. Forced Colors

If the existing accessibility baseline includes forced-colors/high-contrast handling, ensure responsive changes do not break it.

Do not create a new high-contrast theme.

---

# 113. Performance

Responsive foundation should not add JavaScript merely to solve CSS layout.

Expected new runtime JS:

```text
NONE
```

unless a genuine behavioral defect in existing navigation requires a tiny correction.

---

# 114. Bundle Discipline

Do not add:

```text
responsive libraries
device detection packages
CSS frameworks
grid libraries
image libraries
```

Expected dependencies:

```text
NONE
```

---

# 115. No User-Agent Detection

Do not branch UI by:

```text
navigator.userAgent
headers user-agent
device package
```

for responsive layout.

---

# 116. SSR

The site should remain server-renderable independent of viewport.

Responsive CSS should determine presentation after the same meaningful server-rendered structure where practical.

---

# 117. API Client

Phase 13.9 should not modify:

```text
frontend/web/lib/api/client.ts
```

Expected:

```text
API client changed: NO
```

---

# 118. Routing

Do not modify routing conventions unless a genuine defect is discovered.

Expected:

```text
ROUTING.md changed: NO
```

unless only a necessary cross-reference is added.

---

# 119. Failure Boundaries

Do not redesign Phase 13.8 states.

Only make the smallest responsive corrections if testing exposes a genuine defect.

---

# 120. Design Tokens

Do not change canonical design tokens merely because a component is inconvenient at one width.

First ask whether the implementation is misusing the existing system.

Token changes require a genuine reusable design-system defect.

---

# 121. MUI Theme

Do not rewrite the MUI theme.

Only fix a mapping if the responsive audit reveals an actual Phase 12.3 defect.

If changed, report the exact defect and why the correction belongs at theme level.

---

# 122. Group N Boundary

Absolutely do NOT implement:

```text
homepage hero
shop-by-category
featured products
product cards
product listing grid
category page
product gallery
product information panel
search results
filter sidebar
mobile filters
sorting
SEO metadata
structured data
sitemap
robots
```

Those belong to Group N.

---

# 123. Responsive Documentation

Create or update a durable responsive authority.

Prefer a focused document if the rules are substantial, for example:

```text
frontend/web/RESPONSIVE.md
```

or the existing appropriate website architecture document if one already owns this concern.

Do not create documentation fragmentation unnecessarily.

---

# 124. Responsive Authority Content

The durable guidance should establish:

```text
breakpoint authority
testing viewport matrix
container/gutter ownership
full-bleed vs contained composition
CSS-first responsiveness
useMediaQuery policy
server/client rule
typography rule
navigation transition rule
overflow rule
touch/hover rule
image responsibility
future page responsibilities
```

---

# 125. `frontend/AGENTS.md`

Update only durable enforcement rules.

Potential additions:

```text
canonical breakpoints only
CSS-first responsive layout
no window.innerWidth rendering
no device-specific breakpoints
ContentContainer owns site gutters
no global overflow-x hiding
no arbitrary page breakpoint scales
useMediaQuery only for behavioral needs
future page responsive composition remains owning-phase responsibility
```

Do not paste this entire prompt into AGENTS.md.

---

# 126. ADR

Add an ADR only if there is a material architectural decision not already captured.

A reasonable candidate is:

```text
ADR/WEB-004 — Responsive Web Foundation
```

if it captures durable decisions such as:

```text
canonical breakpoint mapping
CSS-first responsiveness
container ownership
test matrix vs breakpoint distinction
server-first constraint
```

Do not add an ADR merely to maintain numbering symmetry.

---

# 127. Group M Execution Record

Update:

```text
phases/group-M-phases.md
```

with the Phase 13.9 implementation and verification.

Because this closes Group M, include an explicit **Group M Exit Review**.

---

# 128. Group M Exit Criterion

The root roadmap defines Group M's exit condition as:

> Website can consume API using agreed architecture.

Interpret that broadly as foundation readiness, not as permission to implement catalog pages.

At Group M exit, verify:

```text
Next.js foundation
TypeScript
MUI integration
theme integration
API client
routing conventions
layout system
error/loading/not-found handling
responsive foundation
```

all work together coherently.

---

# 129. Group M Exit — Next.js

Verify:

```text
App Router foundation valid
production build passes
root layout server-first
no starter residue affecting architecture
```

---

# 130. Group M Exit — TypeScript

Verify:

```text
strict configuration preserved
path aliases work
no responsive any-casts introduced
```

---

# 131. Group M Exit — MUI

Verify:

```text
App Router integration valid
one provider hierarchy
one MUI theme authority
no duplicate styling system
```

---

# 132. Group M Exit — Design System

Verify:

```text
tokens → MUI theme → layout/state consumers
```

without parallel authorities.

---

# 133. Group M Exit — API

Verify the generic Laravel API client remains ready for Group N server-side consumption.

Do not require a live production domain to close Group M if client contract tests already establish correctness.

A configured development/staging API can be added when integration testing begins.

---

# 134. Group M Exit — Routing

Verify Group N can safely create:

```text
/categories/[slug]
/products/[slug]
/search
```

under the Phase 13.6 routing contract.

Do not create those routes yet.

---

# 135. Group M Exit — Layout

Verify future pages can compose:

```text
full bleed
contained content
readable text
responsive sections
```

without recreating shell geometry.

---

# 136. Group M Exit — Failure Handling

Verify future pages have a clear integration path for:

```text
pending
404
expected API/domain failure
unexpected exception
```

---

# 137. Group M Exit — Responsive

Verify future Group N pages have one canonical breakpoint/container/responsive policy.

This is the principal Phase 13.9 deliverable.

---

# 138. Group M Exit — Accessibility

Verify foundation-level:

```text
semantic landmarks
skip navigation
keyboard navigation
focus
reflow
zoom
touch targets
reduced motion
```

remain passing.

---

# 139. Group M Exit — Request-First Policy

Verify the website foundation still exposes no active:

```text
cart
checkout
payment
order-history
```

flows.

Do not let responsive changes introduce conventional ecommerce controls.

---

# 140. Group M Exit — Dependencies

Review frontend dependencies for obvious accidental duplication introduced during Group M.

Do not perform package cleanup unrelated to Group M.

Expected Phase 13.9 additions:

```text
NONE
```

---

# 141. Automated Validation

Run existing tests for:

```text
API client
theme contract
layout contract
state/failure contract
routing contract
request-first regression
design-system validation
```

plus new responsive contract checks.

---

# 142. Responsive Contract Tests

Add/extend tests covering at least:

```text
canonical breakpoint mapping
container ownership
responsive navigation transition
no forbidden raw breakpoint drift
no window.innerWidth layout branching
no global overflow-x hiding
```

Use the repository's existing test style.

---

# 143. Browser Verification

Run actual browser/runtime verification.

At minimum:

```text
narrow mobile
mobile
tablet
narrow desktop
desktop
wide desktop
```

Do not declare responsive PASS from source inspection alone.

---

# 144. Overflow Verification

For representative widths verify:

```text
document.documentElement.scrollWidth <= window.innerWidth
```

or an equivalent reliable check.

If overflow exists, identify the element causing it.

Do not mask it globally.

---

# 145. Breakpoint Transition Verification

Explicitly test structural transition points.

Report which transitions were tested.

---

# 146. Zoom Verification

Perform meaningful zoom/reflow testing.

Report method and result.

Do not simply infer PASS from responsive CSS.

---

# 147. Keyboard Verification

Test both:

```text
mobile navigation mode
desktop navigation mode
```

with keyboard.

---

# 148. Production Build

Run the actual build command from:

```text
frontend/web/package.json
```

Must pass.

---

# 149. TypeScript

Run the actual typecheck command.

Must pass.

---

# 150. ESLint

Run the actual lint command.

Must pass.

---

# 151. `git diff --check`

Must pass.

---

# 152. No New Dependencies

Expected:

```text
Dependencies added: NONE
```

If any dependency appears necessary:

```text
STOP
```

and justify it before installing.

A responsive foundation should not require another dependency.

---

# 153. Completion Report

Return:

```text
Phase 13.9 status:
PASS / BLOCKED


BREAKPOINT AUTHORITY

Canonical source:
<path>

Canonical breakpoints:
<resolved mapping>

MUI mapping:
<summary>

MUI default drift:
NONE / <explain>

Second breakpoint authority:
NONE / FAIL

Raw unapproved width media queries:
NONE / <list>


RESPONSIVE STRATEGY

CSS-first:
YES / NO

Device detection:
NONE / FAIL

window.innerWidth layout branching:
NONE / FAIL

useMediaQuery:
<NONE / list + behavioral justification>

Server-first architecture preserved:
YES / NO

Hydration issues:
NONE / FAIL


CONTAINER SYSTEM

ContentContainer:
<path>

Canonical max width:
<token/mapping>

Responsive gutters:
<token/mapping>

Full-bleed:
PASS / FAIL

Contained:
PASS / FAIL

Nested-container policy:
<summary>

Page-specific gutter systems:
NONE / FAIL


TYPOGRAPHY

Canonical type scale preserved:
YES / NO

Arbitrary responsive font sizes:
NONE / <list>

Young Serif mobile behavior:
PASS / FAIL

Long heading reflow:
PASS / FAIL

Text scaling:
PASS / FAIL


HEADER / NAVIGATION

Mobile mode:
<summary>

Desktop mode:
<summary>

Transition breakpoint:
<canonical breakpoint>

Boundary -1:
PASS / FAIL

Boundary:
PASS / FAIL

Boundary +1:
PASS / FAIL

Both navigations active simultaneously:
NO / FAIL

Neither navigation available:
NO / FAIL

Long category labels:
PASS / FAIL

Drawer narrow viewport:
PASS / FAIL

Drawer long content:
PASS / FAIL

Resize while drawer open:
PASS / FAIL


FOOTER

Mobile:
PASS / FAIL

Tablet:
PASS / FAIL

Desktop:
PASS / FAIL

Wide desktop:
PASS / FAIL

DOM/read order:
PASS / FAIL

Touch targets:
PASS / FAIL


STATE PAGES

Loading:
PASS / FAIL

Not found:
PASS / FAIL

Error:
PASS / FAIL

320px:
PASS / FAIL

Large text:
PASS / FAIL

Action reflow:
PASS / FAIL


OVERFLOW

Global overflow-x hiding:
NONE / FAIL

100vw hacks:
NONE / <justified list>

Negative-margin viewport hacks:
NONE / <justified list>

Narrow mobile overflow:
NONE / FAIL

Mobile overflow:
NONE / FAIL

Tablet overflow:
NONE / FAIL

Narrow desktop overflow:
NONE / FAIL

Desktop overflow:
NONE / FAIL

Wide desktop overflow:
NONE / FAIL


ACCESSIBILITY / INPUT

200% zoom/reflow:
PASS / FAIL

Keyboard mobile:
PASS / FAIL

Keyboard desktop:
PASS / FAIL

Visible focus:
PASS / FAIL

Touch targets:
PASS / FAIL

Hover-only essential behavior:
NONE / FAIL

Reduced motion:
PASS / FAIL

Focus in hidden navigation:
NONE / FAIL


RESPONSIVE IMAGES

Foundation rule documented:
YES / NO

Product image behavior implemented:
NO / FAIL

Image optimization implemented:
NO / FAIL

Logo responsive integrity:
PASS / FAIL


CLIENT BOUNDARIES

Website foundation "use client" files:
<list>

New client boundaries introduced:
NONE / <list + reason>

Responsive JS state:
NONE / <reason>


SCOPE

Homepage implemented:
NO

Category page implemented:
NO

Product listing implemented:
NO

Product detail implemented:
NO

Search implemented:
NO

Filters/sorting implemented:
NO

SEO implemented:
NO

Structured data implemented:
NO

Sitemap/robots implemented:
NO

Backend changed:
NO

Flutter changed:
NO

API client changed:
NO

Dependencies added:
NONE / <list>


VISUAL VERIFICATION

Viewport matrix:
<list>

Narrow mobile:
PASS / FAIL

Mobile:
PASS / FAIL

Tablet:
PASS / FAIL

Narrow desktop:
PASS / FAIL

Desktop:
PASS / FAIL

Wide desktop:
PASS / FAIL

Breakpoint transitions:
PASS / FAIL

Zoom:
PASS / FAIL


AUTOMATED VALIDATION

Responsive contract:
PASS / FAIL

Breakpoint synchronization:
PASS / FAIL

Raw breakpoint audit:
PASS / FAIL

Overflow regression:
PASS / FAIL

API client:
PASS / FAIL

Layout contract:
PASS / FAIL

State contract:
PASS / FAIL

Routing contract:
PASS / FAIL

Request-first regression:
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

Responsive authority:
<path>

frontend/AGENTS.md:
<updated / unchanged>

ADR:
<id / NONE>

Phase 13.9 execution record:
PASS / FAIL


GROUP M EXIT REVIEW

13.1 Next.js:
PASS / FAIL

13.2 TypeScript:
PASS / FAIL

13.3 MUI:
PASS / FAIL

13.4 Theme:
PASS / FAIL

13.5 API client:
PASS / FAIL

13.6 Routing:
PASS / FAIL

13.7 Layout:
PASS / FAIL

13.8 Failure states:
PASS / FAIL

13.9 Responsive foundation:
PASS / FAIL

Server-first:
PASS / FAIL

Design-system authority:
PASS / FAIL

API-ready:
PASS / FAIL

Routing-ready:
PASS / FAIL

Layout-ready:
PASS / FAIL

Failure-state-ready:
PASS / FAIL

Responsive-ready:
PASS / FAIL

Accessibility foundation:
PASS / FAIL

Request-first policy:
PASS / FAIL


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

Phase 13.9:
PASS / BLOCKED

Group M:
CLOSED / BLOCKED

Group N:
READY / BLOCKED
```

---

# 154. STOP Condition

Phase 13.9 may be declared PASS only when:

- one canonical breakpoint authority exists;
- MUI breakpoint mapping is synchronized with it;
- no parallel page-specific breakpoint authority exists;
- responsive layout is CSS-first;
- no `window.innerWidth` render branching exists;
- device/user-agent detection is not used for layout;
- `useMediaQuery` is limited to genuine behavioral needs;
- server-first architecture remains intact;
- no unnecessary Client Components are introduced;
- `ContentContainer` remains the site-gutter/max-width authority;
- full-bleed and contained compositions work across the viewport range;
- no duplicate page gutter systems exist;
- typography remains on the approved scale;
- long headings and navigation labels reflow safely;
- mobile/desktop navigation transitions at a canonical breakpoint;
- transition boundary tests pass;
- hidden navigation cannot receive inappropriate focus;
- mobile drawer remains usable at narrow width and with enlarged text;
- footer reflows coherently;
- Phase 13.8 state pages reflow coherently;
- no global `overflow-x: hidden` is used to hide defects;
- no unexplained `100vw`/negative-margin hacks exist;
- no horizontal overflow exists across representative widths;
- meaningful zoom/reflow testing passes;
- keyboard navigation works in mobile and desktop modes;
- touch targets remain compliant;
- no essential behavior is hover-only;
- reduced-motion behavior remains intact;
- responsive-image responsibilities are documented without implementing Group N image behavior;
- logo remains stable and undistorted;
- no product/category/search/filter responsive UI has been prematurely implemented;
- no Group N page has been created;
- API client remains unchanged unless an actual defect was discovered;
- no backend or Flutter work occurred;
- no dependencies were added;
- responsive/browser verification was actually performed;
- responsive contract tests pass;
- existing API/layout/state/routing/request-first/theme/design-system regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

---

# 155. Group M Closure Gate

After Phase 13.9 passes, perform the Group M exit review.

Group M may be declared:

```text
CLOSED
```

only if all phases:

```text
13.1
13.2
13.3
13.4
13.5
13.6
13.7
13.8
13.9
```

remain PASS together.

The resulting architecture must support:

```text
Next.js App Router
        ↓
MUI + canonical theme
        ↓
server-first public shell
        ↓
canonical responsive foundation
        ↓
generic Laravel API client
        ↓
routing conventions
        ↓
loading / error / not-found semantics
        ↓
Group N public catalog pages
```

without requiring Group N to undo foundation decisions.

If all exit gates pass, report exactly:

```text
Phase 13.9 — PASS
Group M — CLOSED
Group N — READY
```

Do **not** start Phase 14.1 automatically.

If any foundation defect prevents Group N from safely proceeding, report:

```text
Phase 13.9 — BLOCKED
Group M — OPEN

Blocking issue:
<exact issue>

Owning foundation phase:
<13.x>

Required remediation:
<smallest correction>
```

Do not close Group M merely because the production build succeeds.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**