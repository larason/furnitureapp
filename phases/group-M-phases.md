# Group M — Website Foundation

# Combined Phase 13.1–13.4
## Next.js + TypeScript + MUI + Theme Foundation Verification/Reconciliation

## Objective

Verify, reconcile, and formally close the first four Group M phases against the **existing implementation** rather than rebuilding work already completed before or during Group L.

This combined phase covers:

```text
13.1 — Next.js project setup
13.2 — TypeScript configuration
13.3 — MUI integration
13.4 — Theme integration
```

The expected execution model is:

```text
existing implementation
        ↓
inspect
        ↓
compare against Group M requirements
        ↓
identify genuine gaps
        ↓
make smallest necessary corrections
        ↓
verify production behavior
        ↓
record 13.1–13.4 PASS
```

NOT:

```text
existing implementation
        ↓
delete/recreate/reinstall
```

This is primarily a **verification and reconciliation phase**.

---

# 1. Why These Phases Are Combined

The repository already has overlapping completed work:

```text
13.1
Next.js scaffold
→ project owner created frontend/web manually

13.3
MUI integration
→ implemented early during Phase 12.3

13.4
Theme integration
→ implemented early during Phase 12.3
```

Phase 13.2 must now verify the TypeScript configuration supporting that existing implementation.

Therefore running four independent implementation phases would risk:

```text
duplicate providers
duplicate themes
package reinstall churn
create-next-app overwrite
configuration drift
unnecessary refactors
```

The original Group M ownership remains intact.

These phases are being **closed by verification/reconciliation**, not removed from the roadmap.

---

# 2. Active Scope

Implement only:

```text
13.1 — Next.js project setup verification
13.2 — TypeScript configuration verification
13.3 — MUI integration verification
13.4 — Theme integration verification
```

Do NOT begin:

```text
13.5 — API client
13.6 — Routing conventions
13.7 — Layout system
13.8 — Error/loading/not-found handling
13.9 — Responsive foundation
```

Do NOT begin Group N.

---

# 3. Read Authorities First

Before making any change, inspect:

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
├── flutter-material3.md
└── relevant manifests/records

frontend/web/
├── package.json
├── package-lock.json / current lockfile
├── tsconfig.json
├── next.config.*
├── eslint.config.* / equivalent
├── app/
├── theme/
└── existing provider/integration files

phases/group-L-phases.md
phases/group-M-phases.md
docs/decisions.md
```

Also inspect the Phase 12.3 execution record and relevant design ADRs.

Do not infer what Phase 12.3 implemented.

Verify the actual repository.

---

# 4. Git Workflow

Before ANY Git command:

```text
locate and read:
git-workflow-and-versioning
```

Follow that root skill exactly.

Git operations are authorized through that skill.

Preserve unrelated owner changes.

Never commit:

```text
.env
.env.local
credentials
tokens
Clerk secrets
private keys
machine-specific configuration
```

Stage only this combined phase's work.

---

# 5. Critical Preservation Rule

Do NOT run:

```bash
npx create-next-app
```

or any equivalent scaffolding command.

The project already exists.

Do NOT delete/recreate:

```text
frontend/web/
```

Do NOT replace the application with a fresh template.

Phase 13.1 is:

```text
inspect
→ verify
→ reconcile
```

not scaffolding.

---

# 6. No Dependency Reinstallation by Default

MUI and MUI Icons were already installed during Phase 12.3.

Do NOT automatically run:

```bash
npm install @mui/material
npm install @mui/icons-material
npm install @emotion/react
npm install @emotion/styled
```

or equivalents.

First inspect:

```text
package.json
lockfile
node_modules state where appropriate
```

and verify existing versions.

Only repair dependency state if there is an actual defect.

---

# 7. Package Manager Authority

Determine the package manager from the existing repository.

Examples:

```text
package-lock.json
→ npm

pnpm-lock.yaml
→ pnpm

yarn.lock
→ yarn
```

Do not introduce a second package manager.

Do not regenerate a lockfile with a different package manager.

---

# 8. Phase 13.1 — Next.js Project Setup Verification

Inspect the existing:

```text
frontend/web/
```

project.

Verify that it is a valid Next.js application using the intended architecture:

```text
Next.js
React
App Router
TypeScript
MUI
```

Do not convert it to Pages Router.

---

# 9. Next.js Version

Record the actual installed:

```text
next
react
react-dom
```

versions.

Do not arbitrarily upgrade them during this phase.

If there is a known compatibility problem between the installed versions and existing MUI integration:

```text
document the issue
→ make only the minimum supported correction
```

Do not perform unrelated dependency modernization.

---

# 10. App Router

Verify the project uses:

```text
app/
```

as the application routing architecture.

Do not introduce:

```text
pages/
```

as a competing routing system.

If `pages/` exists for an intentional technical reason, document it rather than deleting blindly.

---

# 11. Root Layout

Verify a valid root:

```text
app/layout.tsx
```

exists.

The root layout should remain a:

```text
Server Component
```

unless a concrete Next.js requirement proves otherwise.

Do NOT add:

```tsx
'use client';
```

to the root layout merely because theme/provider infrastructure needs client behavior.

Use the smallest client boundary.

---

# 12. Application Entry

Verify the existing root application structure is minimal and valid.

Do NOT build:

```text
header
footer
navigation
storefront shell
catalog
homepage composition
```

in Phase 13.1–13.4.

Those belong later.

---

# 13. Current Homepage

If the scaffold contains:

```text
app/page.tsx
```

keep it minimal.

Do not implement the Group N homepage.

A simple foundation/smoke page is acceptable.

Do not treat placeholder application content as final storefront design.

---

# 14. Source Organization

Inspect the existing project organization.

Do NOT reorganize the entire project merely to impose a preferred template.

Only correct structure when it creates a genuine architectural problem.

Likely future concerns may include:

```text
app/
theme/
components/
lib/
```

but do not create empty directories merely to anticipate later phases.

---

# 15. Next.js Configuration

Inspect:

```text
next.config.*
```

Verify it is:

```text
valid
minimal
compatible with installed Next.js
```

Do not add:

```text
image hosts
redirects
rewrites
experimental features
```

without a current requirement.

Image optimization configuration belongs to the appropriate later catalog phase unless already required by existing infrastructure.

---

# 16. Environment Configuration

Do not add production environment variables merely to complete setup.

Phase 13.1–13.4 does not require API or Clerk integration.

If existing `.env.example` conventions exist:

```text
preserve them
```

but do not add speculative configuration.

---

# 17. Ignore Rules

Verify `.gitignore` or relevant repository ignore rules protect common Next.js artifacts such as:

```text
.next/
node_modules/
environment files as appropriate
```

Do not broaden ignore patterns in ways that hide source/configuration that should be versioned.

---

# 18. Build Scripts

Inspect:

```text
frontend/web/package.json
```

Verify appropriate existing scripts for the project, typically covering:

```text
dev
build
start
lint
```

and type checking where the project already provides it.

Do not invent redundant scripts if equivalent validation already exists.

---

# 19. Phase 13.2 — TypeScript Configuration Verification

Inspect:

```text
frontend/web/tsconfig.json
```

and any related TypeScript configuration.

The goal is:

```text
strict
predictable
Next.js-compatible
maintainable
```

TypeScript must protect the architecture rather than merely transpile JavaScript.

---

# 20. Strict TypeScript

Verify:

```json
"strict": true
```

or an equivalent configuration that preserves strictness.

Do not disable strictness to silence implementation problems.

---

# 21. No Broad Type Escapes

Do not normalize patterns such as:

```ts
any
as any
// @ts-ignore
// @ts-nocheck
```

as ordinary implementation techniques.

Existing legitimate exceptions should be reviewed contextually.

Do not blindly rewrite third-party compatibility workarounds.

---

# 22. Path Aliases

Inspect existing import aliases.

If the scaffold already uses something such as:

```text
@/*
```

verify it resolves correctly.

Do not create multiple competing aliases such as:

```text
@/*
~/*
src/*
#/*
```

without reason.

Use the existing convention.

---

# 23. Include / Exclude

Verify TypeScript includes the necessary Next.js-generated and application files without accidentally type-checking irrelevant build artifacts.

Do not hand-edit generated Next.js type files.

---

# 24. JSX / Module Configuration

Preserve Next.js-supported TypeScript compiler settings.

Do not override framework-managed compiler behavior merely for stylistic preference.

---

# 25. TypeScript Build Integrity

Run the project's actual TypeScript validation.

If no explicit typecheck script exists, use an appropriate non-emitting TypeScript check consistent with the project.

Do not modify configuration merely to make invalid code disappear.

---

# 26. Phase 13.3 — MUI Integration Verification

Inspect the existing MUI packages.

Expected relevant packages from Phase 12.3 may include:

```text
@mui/material
@mui/icons-material
@mui/material-nextjs
@emotion/react
@emotion/styled
```

Verify actual package state.

Do not reinstall if correct.

---

# 27. Official MUI Integration

Verify Next.js App Router integration follows the installed MUI version's supported approach.

Use the existing official MUI integration package already selected in Phase 12.3.

Do not replace it with:

```text
custom Emotion cache hacks
Pages Router examples
old unofficial snippets
```

unless repository evidence shows the existing integration is defective.

---

# 28. SSR Styling

Verify server-rendered MUI styles behave correctly.

Requirements:

```text
no initial unstyled flash caused by broken integration
no duplicate style injection
no obvious hydration mismatch
no server/client theme divergence
```

Do not solve SSR problems by converting the whole app to a Client Component.

---

# 29. Provider Boundary

Inspect the current provider architecture.

Expected principle:

```text
Server root layout
        ↓
small client provider boundary
        ↓
MUI/theme-dependent descendants
```

Do not expand the client boundary unnecessarily.

---

# 30. No Global Client Conversion

Explicitly prohibit solving MUI integration with:

```tsx
'use client';
```

at every layout/page level.

Client Components should exist only where:

```text
state
effects
browser APIs
event handling
client-only context
```

require them.

---

# 31. Emotion Integration

Verify Emotion is configured only as required by MUI.

Do not introduce:

```text
styled-components
Tailwind runtime styling
CSS-in-JS alternative
second Emotion cache
```

as competing runtime styling systems.

The presence of design-system Tailwind output does not mean the Next.js application should adopt Tailwind as a second component styling authority.

---

# 32. Material UI Icons

Verify:

```text
@mui/icons-material
```

is installed and is the approved website UI icon library.

Do not add:

```text
lucide-react
react-icons
heroicons
fontawesome
custom generic SVG icon libraries
```

---

# 33. No Icon Wrapper Yet

Do not create:

```text
AppIcon
BrandIcon
IconFactory
```

during this phase.

Phase 12.6 already established that redundant wrappers are not desirable.

Use direct Material UI Icons later where appropriate.

---

# 34. CssBaseline

Inspect whether:

```text
CssBaseline
```

is integrated as part of the Phase 12.3 theme infrastructure.

If correct:

```text
preserve it.
```

Do not use CssBaseline to redefine canonical token values.

It may normalize framework/browser behavior.

---

# 35. CSS Authority

Inspect global CSS integration.

Global CSS may handle legitimate application/document foundations.

It must not create a second theme.

Do not duplicate:

```text
colors
typography
radius
shadows
spacing
```

with independent hard-coded values if they belong to the design system.

---

# 36. Design Token Import

Verify canonical token CSS is made available through the existing intended integration.

Do not create duplicate copies of:

```text
tokens.css
```

inside `frontend/web`.

The design-system source remains shared.

---

# 37. Phase 13.4 — Theme Integration Verification

Inspect:

```text
frontend/web/theme/
```

or the actual Phase 12.3 theme location.

Verify there is:

```text
one intentional theme entry point
```

not multiple competing `createTheme()` implementations.

---

# 38. Theme Is a Consumer

Verify the MUI theme remains:

```text
consumer of shared design tokens
```

not:

```text
second token authority
```

Canonical values remain owned by:

```text
frontend/design-system/tokens.css
```

with portable/build representations as previously established.

---

# 39. No Runtime CSS Parsing

Verify the MUI theme does NOT parse:

```text
tokens.css
```

at runtime to construct the theme.

Do not introduce runtime token parsing.

---

# 40. No Runtime Token JSON Fetching

Do not:

```text
fetch design-tokens.json
import it as client runtime configuration unnecessarily
load token values over HTTP
```

for static theming.

Use the established Phase 12.3 mapping strategy.

---

# 41. Palette Mapping

Verify existing MUI palette semantics still satisfy Group L.

At minimum:

```text
primary action
→ #111111

canvas
→ #FCF4ED

paper
→ #FFFFFF

editorial
→ #F4E9DF

brand accent
→ #321E0F

primary text
→ #111111

secondary text
→ #707072
```

Do not change values merely because MUI defaults differ.

---

# 42. Brown Is Not Primary

Verify:

```text
#321E0F
```

has not become the universal:

```text
primary.main
button color
link color
icon color
heading color
```

unless a specific semantic mapping justifies a usage.

Primary action remains charcoal.

---

# 43. No ColorScheme-from-Seed Equivalent

The web theme must use explicit approved semantics.

Do not introduce palette-generation machinery that synthesizes an alternative tonal brand system.

---

# 44. Typography Mapping

Verify:

```text
Young Serif
→ display/editorial roles

approved utility sans
→ body/navigation/forms/buttons/metadata
```

Do not make Young Serif the default `body` family.

---

# 45. Font Loading Boundary

Inspect existing Phase 12.3 implementation.

If font loading was intentionally deferred:

```text
do not pull it into this phase unless required for a valid existing theme integration.
```

If it was already implemented as part of the actual existing integration:

```text
verify rather than recreate.
```

Do not duplicate font imports.

---

# 46. Typography Scale

Verify the theme uses the approved scale:

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

Do not allow MUI's default typography scale to silently become a competing system.

---

# 47. Spacing

Verify MUI spacing maps coherently to the canonical design-system spacing scale.

Do not preserve MUI default spacing behavior if Phase 12.3 intentionally replaced/mapped it.

Do not introduce arbitrary fractional spacing values.

---

# 48. Shape

Verify MUI shape mapping follows:

```text
sharp media
restrained controls
controlled containers
pill only when semantically justified
```

Do not globally increase `borderRadius`.

---

# 49. Elevation

Verify theme shadows/elevation follow:

```text
flat by default
true layers may elevate
```

Do not restore MUI's decorative card-elevation defaults if Phase 12.3 intentionally neutralized/reconciled them.

---

# 50. Motion

Verify transitions use approved duration/easing semantics where mapped.

Do not introduce:

```text
spring
bounce
large scale
card lift
```

as theme defaults.

---

# 51. Reduced Motion

Phase 12.7 established:

```text
future web implementation
must honor prefers-reduced-motion
```

Verify existing theme/global foundation does not actively contradict this.

Do not build a full animation subsystem.

---

# 52. Focus

Verify existing foundational theme/global CSS does not suppress focus.

Search for:

```text
outline: none
outline: 0
```

Review matches contextually.

Do not blindly reject legitimate replacements.

Any focus replacement must satisfy the Phase 12.7 accessibility authority.

---

# 53. Breakpoints

Verify MUI breakpoint mapping uses the approved design-system breakpoint contract.

Do not create a second breakpoint scale.

Do not implement responsive page layouts yet.

---

# 54. Z-Index

If Phase 12.3 mapped z-index/layering semantics, verify them.

Do not create arbitrary application-specific z-index values in this phase.

---

# 55. Component Overrides

Inspect existing `components` theme overrides.

They should be limited to foundational behavior.

Allowed examples:

```text
baseline Button behavior
baseline input shape
baseline Paper/Card elevation behavior
focus behavior
```

Do not add:

```text
ProductCard
FurnitureRequestCard
Header
FilterDrawer
CommerceCTA
```

through theme overrides.

---

# 56. Theme Augmentation

Verify TypeScript module augmentation is:

```text
minimal
typed
semantic
```

Do not expose the entire token inventory through MUI theme augmentation.

Only expose semantics that application code genuinely needs.

---

# 57. No `any` in Theme Typing

Theme augmentation must not use:

```ts
any
```

as an escape hatch.

Resolve actual types.

---

# 58. Theme Provider Count

Search for theme/provider initialization.

There should not be multiple unrelated:

```text
ThemeProvider
createTheme
AppRouterCacheProvider
```

trees unless architecture genuinely requires them.

Record the actual provider chain.

---

# 59. Hydration

Run the application sufficiently to verify there are no obvious:

```text
hydration mismatch
server/client class mismatch
style injection warnings
```

associated with theme integration.

If a warning exists:

```text
fix the cause
```

rather than suppressing it.

---

# 60. Server Component Preservation

Audit existing:

```text
app/
```

files for unnecessary:

```tsx
'use client';
```

introduced solely because of theming.

Do NOT broadly refactor legitimate Client Components.

Correct only obvious foundation-level leakage.

---

# 61. Accessibility Foundation Non-Regression

The Phase 12.7 authority states that accessibility is default behavior and that native/MUI primitives should be preferred before custom ARIA/wrappers.

Verify 13.1–13.4 do not contradict:

```text
visible focus
semantic controls
adequate targets
contrast
reduced motion
```

Do not claim the scaffold itself constitutes full accessibility verification.

---

# 62. Contrast Non-Regression

Re-run existing core theme contrast validation.

At minimum preserve:

```text
normal text
large text
non-text/focus
```

requirements from the accessibility baseline.

Do not alter canonical colors just to make a new fixture convenient.

---

# 63. No Accessibility Overlay

Verify no dependency or component has been added for an:

```text
accessibility overlay
accessibility mode
accessibility widget
```

Accessibility remains built into normal UI.

---

# 64. No API Client Yet

Do NOT create:

```text
api.ts
http.ts
fetcher.ts
axios client
Laravel client
OpenAPI client
```

unless one already exists from unrelated owner work.

Phase 13.5 owns API client architecture.

If existing API code exists:

```text
leave it untouched
and report it.
```

---

# 65. No Clerk Integration Yet

Do NOT integrate:

```text
ClerkProvider
SignIn
SignUp
auth middleware
token acquisition
```

in this phase unless already present from owner work.

Authentication integration belongs to its owning frontend phase.

Do not pull backend Clerk architecture into foundation setup prematurely.

---

# 66. No Application Routing Conventions Yet

The presence of App Router is Phase 13.1.

The project's **routing conventions** belong to:

```text
13.6
```

Do not define:

```text
catalog route taxonomy
account routes
request routes
search URL contract
```

here.

---

# 67. No Layout System Yet

Do not implement:

```text
PageContainer
Section
Grid
Stack abstraction
Header
Footer
Desktop navigation
Mobile navigation
```

Phase 13.7 owns layout system work.

---

# 68. No Application Error States Yet

Do not implement:

```text
error.tsx
global-error.tsx
not-found.tsx
loading.tsx
```

merely to complete the scaffold unless Next.js generated an existing minimal file.

Phase 13.8 owns intentional application error/loading/not-found behavior.

Existing framework files may remain.

---

# 69. No Responsive Foundation Implementation Yet

Do not build responsive application composition.

Phase 13.9 owns that work.

Only verify the design-system breakpoints and MUI mapping exist.

---

# 70. No Group N Work

Absolutely do not implement:

```text
homepage
category page
product listing
product detail
search UI
filters
sorting
SEO metadata architecture
structured data
sitemap
robots
internal linking
catalog image optimization
```

during this combined phase.

---

# 71. Existing Placeholder Content

Audit scaffold/template content for obvious:

```text
Next.js starter branding
Vercel starter content
create-next-app tutorial links
generic starter SVGs
```

If still present in the visible application foundation:

remove or simplify them.

Do NOT replace them with the final furniture homepage.

Use a minimal neutral foundation placeholder if needed.

---

# 72. Unauthorized Icon Audit

Search active web application code for imports from:

```text
lucide-react
react-icons
@heroicons/*
@fortawesome/*
```

If such a dependency/import was accidentally introduced by scaffold/template work:

determine whether it is actually used.

Do not blindly remove unrelated owner functionality.

For the project design system, future website UI icons remain:

```text
@mui/icons-material
```

---

# 73. Styling-System Audit

Search for competing application styling systems.

Examples:

```text
Tailwind component styling
styled-components
Sass theme variables
duplicate CSS variables
custom theme provider
```

Do not automatically remove a tool solely because a file exists.

Determine whether it is actively acting as a competing design authority.

The goal is:

```text
MUI
+
shared canonical design tokens
```

for website UI.

---

# 74. Tailwind Clarification

The existence of:

```text
frontend/design-system/tailwind-v4.css
```

is a synchronized design-system representation.

It does NOT automatically authorize:

```text
Tailwind as the Next.js component styling framework.
```

Do not install/configure Tailwind in `frontend/web` unless separately approved.

---

# 75. CSS Modules

CSS Modules are not inherently prohibited.

They may later be appropriate for specialized structural styling.

But they must not become a second theme/token authority.

Do not introduce them in this phase merely to demonstrate support.

---

# 76. Static Assets

Do not reorganize:

```text
public/
designs/
```

or copy brand assets into multiple locations without need.

Asset integration belongs to the phase that consumes the asset.

---

# 77. Brand Logo

Do not redraw:

```text
designs/brandlogo.png
```

as text or SVG during this phase.

No application header is being built yet.

---

# 78. Dependency Audit

Produce a concise inventory of relevant existing foundation dependencies.

At minimum record actual versions for:

```text
next
react
react-dom
typescript
@mui/material
@mui/icons-material
@mui/material-nextjs
@emotion/react
@emotion/styled
```

Do not change them unless required for compatibility.

---

# 79. Dependency Security

If the normal package-manager audit command is part of the repository workflow, it may be run and reported.

Do not perform broad major-version upgrades in response to unrelated audit findings during this phase.

Report material findings separately.

---

# 80. Build Reproducibility

Use the repository's lockfile.

Do not delete/regenerate it unnecessarily.

The project should install/build consistently using its selected package manager.

---

# 81. TypeScript Verification

Run the actual project TypeScript validation.

Expected result:

```text
0 errors
```

Do not suppress errors.

---

# 82. ESLint Verification

Run the existing lint command.

Expected:

```text
0 errors
```

Warnings should be reviewed and reported.

Do not globally disable rules merely to obtain PASS.

---

# 83. Theme Contract Tests

Run the existing Phase 12.3 theme contract tests.

Verify at least:

```text
palette
typography
spacing
breakpoints
shape
elevation
token mappings
```

as supported by the actual suite.

Do not rewrite tests simply because they expose a real regression.

---

# 84. Accessibility Foundation Checks

Run existing checks for:

```text
contrast
focus
reduced motion where covered
```

and any Phase 12.7 authority/token validation.

Remember:

```text
automated checks
≠
full WCAG conformance
```

The accessibility authority explicitly requires later manual verification as well.

---

# 85. Production Build

Run:

```text
the repository's actual production build command
```

for `frontend/web`.

Expected:

```text
PASS
```

Do not substitute development startup for a production-build gate.

---

# 86. Runtime Smoke Check

Where practical, perform a minimal runtime smoke check.

Verify:

```text
application starts
root route renders
MUI styles render
theme provider works
no obvious hydration warning
no missing provider error
no token/theme runtime exception
```

Do not test nonexistent commerce flows.

---

# 87. Server Rendering Check

Inspect/render the root route sufficiently to verify the theme integration does not require the whole page to become client-only.

Important static content should remain server-renderable.

Do not claim full SEO readiness.

Group N owns catalog SEO.

---

# 88. No Browser-Only Theme Initialization

Theme construction should not depend unnecessarily on:

```text
window
document
localStorage
```

No dark-mode preference system is currently approved.

The initial theme is deterministic.

---

# 89. No Dark Mode

Verify:

```text
dark theme
color-mode switch
system theme synchronization
```

has not been introduced.

Do not add it.

---

# 90. One Theme

Expected:

```text
one SL Furnitures website theme
```

Do not maintain:

```text
Nike theme
default MUI theme
brand theme
legacy theme
```

simultaneously.

Historical design provenance may exist in documentation, not runtime competing themes.

---

# 91. Raw Value Audit

Run the existing raw-value audit against foundation/theme code.

Approved mapping files may necessarily contain concrete generated/mapped values depending on Phase 12.3 architecture.

The audit must distinguish:

```text
approved adapter mapping
```

from:

```text
ad-hoc application styling
```

Do not blindly fail all raw literals.

---

# 92. Legacy Brand Audit

Search active web foundation code for:

```text
Nike
swoosh
shoe
sneaker
apparel
sportswear
Futura
```

Historical documentation may remain.

Active application/theme code must not present the old brand language.

---

# 93. AI-Slop Regression Audit

Ensure foundation code has not introduced:

```text
gradient theme
glass effects
random radius
heavy global shadows
decorative animation
random accent colors
```

No visual redesign is expected in this phase.

---

# 94. Files Changed Policy

Because this is primarily verification:

```text
zero code changes
```

is a valid successful outcome.

Do not manufacture changes merely so the phase has a diff.

If the existing implementation satisfies the contract:

```text
document verification
→ update phase record
→ PASS
```

---

# 95. Allowed Reconciliation Changes

Changes are permitted only for genuine gaps such as:

```text
incorrect TypeScript strictness
broken alias
duplicate theme provider
unsupported MUI App Router integration
theme mapping regression
unnecessary root client boundary
broken token import
missing build configuration
starter-template residue
```

Keep corrections narrow.

---

# 96. Documentation Record

Update:

```text
phases/group-M-phases.md
```

with a combined execution record explaining:

```text
13.1
→ existing owner-created scaffold verified/reconciled

13.2
→ TypeScript configuration verified/reconciled

13.3
→ Phase 12.3 MUI implementation verified/reconciled

13.4
→ Phase 12.3 theme implementation verified/reconciled
```

Do not erase the original roadmap ownership.

---

# 97. Group L Relationship

Record explicitly:

```text
Group L
→ established design-system architecture

Group M 13.1–13.4
→ validates that the actual website foundation correctly consumes it
```

This avoids future confusion about why MUI/theme work appears in both groups.

---

# 98. ADR

Do NOT automatically add another ADR.

The early implementation/reconciliation relationship may simply belong in the phase execution record.

Add an ADR only if a genuine new architectural decision is required.

Do not create:

```text
DESIGN-007
```

merely to say existing work passed verification.

---

# 99. No Group L Reopening

If 13.1–13.4 uncover a minor implementation mismatch:

```text
fix the website consumer
```

where appropriate.

If they uncover a genuine flaw in the canonical design system:

```text
STOP
→ identify Group L authority affected
→ reconcile canonical source
→ regenerate/synchronize derived representations
→ re-run Group L validation
→ then continue
```

Do not silently fork the website away from the design system.

---

# 100. Security Boundary

No secrets may enter client code.

Search for obvious accidental exposure patterns around:

```text
Clerk secret keys
Laravel credentials
database credentials
private API tokens
```

Do not introduce authentication configuration in this phase.

Public environment variables are not automatically safe merely because Next.js permits them.

---

# 101. Browser Environment Boundary

Do not move server-capable code into Client Components unnecessarily.

The frontend architecture should preserve:

```text
Server Components by default
Client Components when interaction requires them
```

This remains important for:

```text
performance
initial rendering
SEO
bundle size
```

---

# 102. Bundle Restraint

Do not import:

```text
entire icon namespaces
large utility libraries
unused MUI modules
```

merely for convenience.

Prefer normal tree-shakeable imports.

Do not perform speculative micro-optimization.

---

# 103. Accessibility Non-Claim

The completion report must NOT say:

```text
website is WCAG compliant
```

The correct statement is:

```text
website foundation is consistent with the WCAG 2.2 AA-oriented design-system baseline
```

Actual page/journey verification remains future work.

---

# 104. Combined Phase Acceptance — 13.1

Phase 13.1 passes only if:

```text
existing Next.js project verified
App Router verified
root layout valid
Server Component boundary preserved
package manager identified
Next config valid
scripts valid
no scaffold overwrite performed
production build passes
```

---

# 105. Combined Phase Acceptance — 13.2

Phase 13.2 passes only if:

```text
TypeScript config valid
strictness preserved
aliases valid
Next.js compatibility preserved
no broad type suppression introduced
type checking passes
```

---

# 106. Combined Phase Acceptance — 13.3

Phase 13.3 passes only if:

```text
MUI packages verified
MUI Icons verified
official App Router integration verified
Emotion integration valid
SSR style integration valid
provider boundary minimal
no duplicate MUI/theme infrastructure
no competing icon library introduced
```

---

# 107. Combined Phase Acceptance — 13.4

Phase 13.4 passes only if:

```text
single theme authority verified
tokens.css remains canonical
theme remains consumer
palette mapping correct
typography mapping correct
spacing mapping correct
shape mapping correct
elevation mapping correct
motion mapping correct
breakpoint mapping correct
focus/accessibility foundation preserved
no dark theme introduced
theme tests/build pass
```

---

# 108. Completion Report

Return:

```text
Combined Phase 13.1–13.4 status:
PASS / PARTIAL / BLOCKED


13.1 — NEXT.JS PROJECT SETUP

Status:
PASS / BLOCKED

Project already existed:
YES

create-next-app rerun:
NO

Next.js version:
<version>

React version:
<version>

Router:
APP ROUTER / FAIL

Root layout:
PASS / FAIL

Root layout server component:
YES / NO

Package manager:
<manager>

Next config:
PASS / FAIL

Starter/template residue:
NONE / <what was reconciled>

Production build:
PASS / FAIL


13.2 — TYPESCRIPT

Status:
PASS / BLOCKED

TypeScript version:
<version>

Strict mode:
YES / NO

Path aliases:
<summary>

Broad any/ts-ignore foundation escapes:
NONE / <explain>

Type check:
PASS / FAIL


13.3 — MUI INTEGRATION

Status:
PASS / BLOCKED

Existing Phase 12.3 implementation reused:
YES / NO

@mui/material:
<version>

@mui/icons-material:
<version>

@mui/material-nextjs:
<version>

@emotion/react:
<version>

@emotion/styled:
<version>

Packages reinstalled:
NO / <explain>

Official App Router integration:
PASS / FAIL

SSR style integration:
PASS / FAIL

Provider boundary:
PASS / FAIL

Duplicate provider/theme:
NONE / <explain>

Root converted to client component:
NO

Unauthorized icon library:
NONE / <explain>


13.4 — THEME INTEGRATION

Status:
PASS / BLOCKED

Existing Phase 12.3 theme reused:
YES / NO

Theme entry points:
<list>

Single effective theme:
YES / NO

Canonical authority:
frontend/design-system/tokens.css

Theme is consumer:
YES / NO

Runtime CSS parsing:
NO

Runtime token JSON loading:
NO

Primary action:
#111111 / FAIL

Brand accent:
#321E0F / FAIL

Canvas:
#FCF4ED / FAIL

Paper:
#FFFFFF / FAIL

Editorial:
#F4E9DF / FAIL

Display typography:
Young Serif / FAIL

UI typography:
<approved utility stack>

Typography scale:
PASS / FAIL

Spacing:
PASS / FAIL

Shape:
PASS / FAIL

Elevation:
PASS / FAIL

Motion:
PASS / FAIL

Breakpoints:
PASS / FAIL

Focus foundation:
PASS / FAIL

Reduced-motion compatibility:
PASS / FAIL

Dark theme:
NO

Theme contract tests:
PASS / FAIL


CROSS-PHASE VALIDATION

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Theme tests:
PASS / FAIL

Accessibility foundation checks:
PASS / FAIL

Production build:
PASS / FAIL

Runtime smoke:
PASS / FAIL / NOT RUN <reason>

Hydration/style warnings:
NONE / <details>

Raw-value audit:
PASS / FAIL

Legacy brand audit:
PASS / FAIL

Icon-family audit:
PASS / FAIL

git diff --check:
PASS / FAIL


SCOPE

API client implemented:
NO

Routing conventions implemented:
NO

Layout system implemented:
NO

Application error/loading handling implemented:
NO

Responsive application foundation implemented:
NO

Group N started:
NO

Flutter changed:
NO

Backend/API changed:
NO

New dependencies:
NONE / <explain>


FILES CHANGED

<list>


DOCUMENTATION

Group M execution record:
PASS / FAIL

ADR:
NONE / <id and reason>


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

13.1:
PASS / BLOCKED

13.2:
PASS / BLOCKED

13.3:
PASS / BLOCKED

13.4:
PASS / BLOCKED

Combined Phase 13.1–13.4:
PASS / BLOCKED

Phase 13.5:
READY / BLOCKED
```

---

# 109. STOP Condition

Combined Phase 13.1–13.4 may be declared PASS only when:

- the existing Next.js scaffold has been verified rather than recreated;
- `create-next-app` was not rerun;
- App Router is the active architecture;
- the root layout remains server-capable;
- TypeScript configuration is strict and valid;
- type checking passes;
- the existing MUI installation from Phase 12.3 is reused;
- `@mui/icons-material` remains the approved web icon library;
- official MUI App Router integration is valid for the installed versions;
- SSR styling works without obvious hydration/style mismatch;
- provider boundaries remain minimal;
- no duplicate theme/provider infrastructure exists;
- `tokens.css` remains canonical;
- the MUI theme remains a consumer rather than a competing authority;
- palette, typography, spacing, shape, elevation, motion and breakpoints remain aligned with Group L;
- primary action remains charcoal;
- brown remains a controlled brand accent;
- accessibility foundation remains intact;
- no dark theme was introduced;
- no API client was implemented;
- no routing convention work was pulled forward;
- no layout system was implemented;
- no application error/loading system was implemented;
- no responsive application foundation was implemented;
- no Group N work was started;
- no Flutter/backend work changed;
- no dependency was reinstalled/added without an actual need;
- TypeScript passes;
- lint passes;
- theme tests pass;
- production build passes;
- design-system/theme validation passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 13.1 — PASS
Phase 13.2 — PASS
Phase 13.3 — PASS
Phase 13.4 — PASS

Combined Phase 13.1–13.4 — PASS

Phase 13.5 — READY
```

Do not start Phase 13.5 automatically.

---

# Combined Phase 13.1–13.4 Execution Record

## Result

```text
13.1 — PASS
13.2 — PASS
13.3 — PASS
13.4 — PASS
Combined Phase 13.1–13.4 — PASS
Phase 13.5 — READY
```

### 13.1 — Next.js Project Setup

- Existing `frontend/web` scaffold verified; `create-next-app` was not rerun.
- Next.js `16.3.8`, React `19.2.8`, and React DOM `19.2.8` are installed.
- App Router is active through `app/`; no competing `pages/` directory exists.
- `app/layout.tsx` is a Server Component and `next.config.ts` is valid and minimal.
- The starter route was reconciled to a minimal MUI foundation page; no Group N homepage work was started.
- Production build passed.

### 13.2 — TypeScript

- TypeScript `5.9.3` is installed.
- `strict: true`, `noEmit: true`, the existing `@/*` alias, and Next.js compiler integration are preserved.
- No broad `any`, `@ts-ignore`, or `@ts-nocheck` foundation escape was introduced.
- `npx tsc --noEmit` passed.

### 13.3 — MUI Integration

- Existing Phase 12.3 integration was reused without package reinstallation.
- Installed versions: `@mui/material` `9.4.0`, `@mui/icons-material` `9.4.0`, `@mui/material-nextjs` `9.4.0`, `@emotion/react` `11.14.0`, `@emotion/styled` `11.14.1`.
- `AppRouterCacheProvider` and `ThemeProvider` remain in the small client boundary at `app/providers.tsx`.
- The root layout remains server-capable; no duplicate provider/theme or unauthorized icon library exists.
- The Tailwind application pipeline was removed; `frontend/design-system/tailwind-v4.css` remains export-only.

### 13.4 — Theme Integration

- Existing Phase 12.3 theme was reused from `theme/theme.ts`; it is the single `createTheme()` entry point.
- `frontend/design-system/tokens.css` remains canonical and the MUI adapter consumes synchronized `design-tokens.json` values.
- No runtime CSS parsing, token fetch, dark theme, or system color-mode synchronization exists.
- Palette, typography, spacing, shape, elevation, motion, breakpoint, and focus mappings remain aligned with Group L.
- Theme contract passed.

## Cross-Phase Validation

```text
TypeScript: PASS
ESLint: PASS
Theme contract: PASS
Production build: PASS
Runtime smoke: PASS
Hydration/style warnings: NONE observed in startup or root response
Accessibility foundation: PASS by non-regression review
Legacy brand audit: PASS
Icon-family audit: PASS
git diff --check: PASS
```

Scope preserved: no API client, routing conventions, layout system, application error/loading system, responsive application foundation, Group N work, Flutter work, backend/API work, or new dependency was introduced by this combined phase. `future.md` remains unrelated owner work and was not changed.

Group L established the shared design-system architecture; Group M 13.1–13.4 verified that the existing website foundation consumes it correctly. The next phase is 13.5 API client architecture; it is ready but was not started automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**
