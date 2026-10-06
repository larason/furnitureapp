# Phase 12.3 — MUI Theme Implementation

## Objective

Implement the **Next.js + Material UI theme bridge** for SL Furnitures.

The goal is:

```text
frontend/design-system/tokens.css
            ↓
      MUI theme adapter
            ↓
Material UI components
```

NOT:

```text
MUI theme
↓
new independent colors / spacing / typography
```

This phase must make the design system consumable by `frontend/web` while preserving:

- token authority;
- semantic color roles;
- typography hierarchy;
- spacing/radius/elevation/motion scales;
- accessibility;
- SSR/App Router compatibility.

Do not begin application component/page design.

---

## 1. Active Scope

Implement only:

```text
Phase 12.3 — MUI Theme Implementation
```

Do NOT begin:

```text
12.4 — Global CSS / font loading
12.5 — Layout primitives
12.6 — UI primitives
12.7 — Commerce primitives
12.8 — Navigation/header/footer
12.9 — Catalog components
12.10 — Product detail components
12.11 — Request/enquiry components
12.12 — Pages
```

The theme should make those phases possible without forcing them to reinterpret tokens.

---

## 2. Read Authorities Before Editing

Inspect first:

```text
frontend/design-system/DESIGN.md
frontend/design-system/tokens.css
frontend/design-system/design-tokens.json
frontend/design-system/USAGE.md
frontend/design-system/manifest.json

frontend/AGENTS.md
AGENTS.md

frontend/web/package.json
frontend/web/tsconfig.json
frontend/web/next.config.*
frontend/web/app/*
frontend/web/src/*              # if present
frontend/web/styles/*           # if present
```

Also inspect the installed versions of:

```text
next
react
@mui/material
@mui/material-nextjs
@emotion/react
@emotion/styled
```

Do not assume package versions from generic MUI documentation.

---

## 3. Mandatory Git Workflow

Before any Git action, read the root:

```text
git-workflow-and-versioning
```

skill.

Git operations are authorized only through that skill.

Preserve unrelated owner changes.

Stage only Phase 12.3 files.

Do not commit secrets or generated local configuration.

---

## 4. Token Authority Is Frozen

The design contract states:

```text
tokens.css
= canonical token authority
```

and:

```text
design-tokens.json
tailwind-v4.css
= synchronized derived representations
```

MUI must **consume**, not redefine, this design contract.

Do not duplicate raw values such as:

```ts
'#FCF4ED'
'#321E0F'
'#111111'
'#FFFFFF'
```

inside dozens of MUI files.

A single controlled mapping layer may reference the approved design-system representation, but that layer must not become a second token authority.

---

## 5. Determine the Correct Consumption Strategy

Before implementation, inspect whether `frontend/web` can directly import:

```text
frontend/design-system/tokens.css
```

through the repository/package/build configuration.

Prefer the simplest maintainable dependency direction.

Acceptable architecture:

```text
tokens.css
    ↓
web global token availability
    ↓
MUI semantic mappings use var(--...)
```

or, where MUI requires concrete token values for theme calculations:

```text
canonical tokens.css
        ↓
synchronized design-tokens.json
        ↓
typed MUI mapping
```

because `design-tokens.json` is a derived synchronized representation of the same canonical contract.

Do not manually duplicate the values.

Document which strategy is used.

---

## 6. Do Not Parse CSS at Runtime

Do not implement:

```text
fetch tokens.css
parse CSS variables
build theme dynamically in browser
```

Do not add runtime token parsing.

Theme construction should be static/build-time friendly.

---

## 7. Do Not Introduce a New Theme Library

Use existing MUI capabilities.

Do not add:

```text
styled-components
Chakra
Tailwind runtime
Theme UI
Emotion replacement
design-token runtime package
```

unless already required by the installed MUI setup.

Expected dependency changes:

```text
Dependencies:
Install only the official MUI packages required by the current Next.js/App Router setup if they are not already present.

Expected candidates:
@mui/material
@emotion/react
@emotion/styled
@mui/material-nextjs

use available nextjs and materialui skills.

Do not install anything else unless repository evidence requires it.
```

unless the web scaffold genuinely lacks required official MUI App Router integration.

---

# Theme Architecture

## 8. Create a Narrow Theme Module

Use repository conventions, for example:

```text
frontend/web/
└── theme/
    ├── theme.ts
    ├── palette.ts          # only if separation is justified
    ├── typography.ts       # only if justified
    └── mui.d.ts            # only if type augmentation needed
```

or the existing project structure.

Do not create numerous tiny theme files without need.

The theme should remain understandable from one obvious entry point.

---

## 9. One Canonical Theme Entry Point

There must be one application-facing import, conceptually:

```ts
import { theme } from '@/theme';
```

Avoid multiple themes such as:

```text
storefrontTheme
productTheme
homeTheme
furnitureTheme
marketingTheme
```

There is one SL Furnitures design system.

Component variants may exist later.

---

## 10. No Dark Theme in Phase 12.3

The current brand contract is light-oriented.

Do not invent:

```text
dark mode
system mode
night theme
```

unless already approved elsewhere.

The dark `--surface-inverse` token is for intentional inverse sections, not a second global theme.

---

# Palette Mapping

## 11. Map Semantic Design Tokens to MUI

The approved semantic roles include:

```text
--surface-canvas
--surface-paper
--surface-editorial
--surface-inverse

--text-primary
--text-secondary

--accent-brand
--action-primary
```

Map them to MUI semantics deliberately.

Conceptually:

```text
palette.background.default
→ surface.canvas

palette.background.paper
→ surface.paper

palette.text.primary
→ text.primary

palette.text.secondary
→ text.secondary

palette.primary.main
→ action.primary

brand-specific semantic access
→ accent.brand
→ surface.editorial
→ surface.inverse
```

Do not force every design-system token into a built-in MUI palette slot if the meaning does not fit.

---

## 12. Primary Is Charcoal

Preserve:

```text
primary action
→ #111111
```

Do NOT set:

```text
palette.primary.main
→ #321E0F
```

merely because brown is the brand accent.

The contract explicitly says primary actions remain charcoal. DESIGN

---

## 13. Brand Accent Must Remain Separately Addressable

The theme must provide a typed way to consume:

```text
accent.brand
```

without abusing:

```text
primary
secondary
warning
```

If MUI module augmentation is the cleanest existing-project-compatible solution, use it narrowly.

For example conceptually:

```ts
theme.palette.brand.accent
theme.palette.surface.editorial
theme.palette.surface.inverse
```

Exact names should follow current repository conventions.

Do not over-augment MUI with dozens of custom fields.

---

## 14. Functional Colors

Inspect current tokens for:

```text
success
warning
error
info
focus
```

Map them faithfully where appropriate.

Do not derive functional colors from the brown brand accent.

Do not invent functional colors if canonical tokens already exist.

---

## 15. Divider and Border Mapping

Map existing semantic border tokens into:

```text
palette.divider
```

and any typed semantic border access needed later.

Do not hard-code gray borders in MUI component overrides.

---

# Typography

## 16. Preserve Two-Family Architecture

Theme typography must distinguish:

```text
Display
→ var(--font-display)
→ Young Serif

UI
→ var(--font-ui)
→ existing Helvetica Now utility stack
```

The design contract explicitly requires this distinction. DESIGN

---

## 17. Do Not Load Fonts Yet

Phase 12.4 owns font loading.

Do not:

```text
import next/font
add Google Fonts
add @font-face
download font files
modify document head for fonts
```

during 12.3.

Theme references may use:

```css
var(--font-display)
var(--font-ui)
```

with canonical fallbacks already defined by the token system.

---

## 18. Default UI Font

MUI's default typography family should resolve to:

```text
--font-ui
```

not Young Serif.

This ensures default:

```text
Button
TextField
Select
Menu
Chip
Breadcrumb
price
form controls
```

remain precise and scannable.

---

## 19. Heading Family Mapping

Map major typography variants deliberately.

A reasonable target is:

```text
display / h1 / h2 / selected h3
→ display family

body1 / body2
subtitle
button
caption
overline / labels
→ UI family
```

But inspect `DESIGN.md` and current tokens before assigning variants.

Do not automatically make every `h1`–`h6` Young Serif if that contradicts established roles.

---

## 20. Preserve Canonical Type Scale

The approved scale is:

```text
12
14
16
20
24
32
48
96 px
```

Do not invent intermediate sizes.

Do not let MUI defaults introduce:

```text
34
36
40
56
60
```

unless the design system already maps them intentionally.

Every MUI typography variant must map to canonical design tokens.

---

## 21. Avoid Uppercase Button Defaults

MUI historically tends toward uppercase button conventions depending on theme/version.

Furniture brand copy should remain editorial and calm.

Set button typography so it does not automatically produce aggressive uppercase styling unless the token contract explicitly calls for it.

Do not hard-code labels themselves.

---

## 22. Letter Spacing and Line Height

Map existing canonical typography tokens.

Do not invent custom:

```text
letterSpacing: '-0.037em'
lineHeight: 0.91
```

for a visually dramatic serif.

The design contract explicitly rejects aggressive tracking/compressed leading for Young Serif. DESIGN

---

# Spacing / Shape / Elevation

## 23. Preserve Spacing Scale

MUI spacing must consume or map to the approved spacing system.

Do not leave MUI's default spacing system in place if it conflicts with the canonical design-system scale.

Do not create a separate MUI-only spacing scale.

The intended dependency is:

```text
canonical spacing tokens
→ theme spacing
```

---

## 24. Do Not Encourage Numeric Magic Values

Later code should not need:

```tsx
sx={{ mt: 7.25 }}
```

to match the design.

Ensure theme spacing supports the canonical token scale cleanly.

Where MUI's numeric multiplier API cannot faithfully preserve the current design-system scale, expose/use a token mapping rather than distorting the canonical scale.

---

## 25. Border Radius

Map MUI:

```text
shape.borderRadius
```

to the existing canonical default/small-container radius as appropriate.

Do not flatten the entire radius scale into one value conceptually.

Additional semantic radii may remain available through tokens for:

```text
sharp media
form control
container
pill
```

where already defined.

The theme should not make every MUI surface rounded.

---

## 26. Elevation

MUI's default 25-shadow elevation array often conflicts with highly restrained design systems.

Inspect the canonical shadow/elevation tokens.

Map or neutralize MUI elevation behavior so components do not spontaneously render material-style heavy shadows inconsistent with the brand.

Preserve meaningful elevation for:

```text
Menu
Popover
Dialog
Drawer
```

and true layers.

Do not remove necessary separation/focus.

---

# Motion

## 27. Motion Mapping

Use existing canonical:

```text
duration
easing
```

tokens where MUI theme transitions permit.

Do not create a MUI-specific motion language.

Furniture motion remains quiet and functional. DESIGN

---

## 28. Reduced Motion

Do not solve reduced-motion behavior globally in this phase unless the theme architecture already has a clean supported mechanism.

Ensure nothing introduced by the theme prevents Phase 12.4/12.13 from respecting:

```text
prefers-reduced-motion
```

---

# Breakpoints / Layout

## 29. Preserve Existing Breakpoints

Map MUI breakpoint values to canonical design-system breakpoints.

Do NOT accept MUI's default breakpoints merely for convenience if they differ.

The design system is authority.

---

## 30. Do Not Implement Containers Yet

Do not create:

```text
PageContainer
Section
ContentGrid
ResponsiveShell
```

during 12.3.

Phase 12.5 owns layout primitives.

Theme may expose breakpoint/container values that later primitives consume.

---

# Z-Index

## 31. Map Existing Z-Index Tokens

If canonical tokens already define layer order, map MUI:

```text
mobileStepper
fab
speedDial
appBar
drawer
modal
snackbar
tooltip
```

only where necessary to preserve the design-system contract.

Do not invent a giant z-index scale.

---

# MUI Component Defaults

## 32. Component Overrides Must Stay Foundational

Phase 12.3 may set **global MUI behavioral defaults** necessary to stop framework defaults from violating the design system.

It must NOT design final product components.

Appropriate foundational overrides may include:

```text
MuiButton
MuiIconButton
MuiLink
MuiPaper
MuiCard
MuiTextField / MuiInputBase
MuiOutlinedInput
MuiDialog
MuiDrawer
MuiMenu / MuiPopover
MuiTooltip
MuiChip
MuiDivider
```

but only where MUI defaults conflict with foundational tokens.

Do not create commerce-specific variants yet.

---

## 33. Button Foundation

Base MUI buttons should respect:

```text
UI font
canonical radius
canonical typography
no automatic uppercase
focus visibility
minimum accessible hit area
canonical motion
```

Primary action styling should remain charcoal.

Do not yet create:

```text
AddToCartButton
RequestPieceButton
HeroCTA
```

---

## 34. Paper / Card Defaults

MUI `Paper` must not introduce heavy elevation or a foreign background.

Default surfaces should align with:

```text
surface.paper
```

Do not style all `Card` components as floating rounded cards.

The brand explicitly rejects card-heavy and excessively rounded UI. DESIGN

---

## 35. Inputs

Inputs should consume canonical:

```text
text
surface
border
radius
focus
spacing
```

tokens.

Do not redesign form components beyond foundational consistency.

Phase 12.6 owns reusable UI primitives.

---

## 36. Focus Treatment

MUI components must have visibly accessible focus states.

Do not globally disable:

```css
outline
```

Do not rely solely on color changes too subtle to see.

Map to canonical focus tokens.

---

## 37. Links

Base links should use intentional token-driven states:

```text
default
hover
focus
visited if approved
```

Do not add decorative brown underlines everywhere solely for branding.

---

# App Router / SSR Integration

## 38. Use the Official MUI Next.js Integration for Installed Versions

Inspect installed:

```text
Next.js
MUI
@mui/material-nextjs
```

versions.

Use the official App Router integration corresponding exactly to those versions.

Potential constructs may include:

```text
AppRouterCacheProvider
ThemeProvider
```

but do not copy a version-specific example blindly.

Use the integration compatible with the repository's installed dependency versions.

---

## 39. Avoid Styling Hydration Mismatches

The theme setup must be safe for:

```text
Next.js App Router
SSR
streaming
React Server Components
```

Avoid client-only theme initialization purely because it is simpler.

Public SEO content must remain server-renderable according to project rules.

---

## 40. Provider Boundary

Create the smallest required client boundary for MUI context.

For example, if ThemeProvider requires a client module:

```text
app/providers.tsx
```

may be a Client Component while:

```text
app/layout.tsx
```

remains a Server Component.

Do not convert the entire root layout or application into:

```text
'use client'
```

without necessity.

---

## 41. Do Not Add Clerk Yet

Authentication frontend implementation is outside this phase.

Do not combine:

```text
ClerkProvider
ThemeProvider
API auth
```

unless Clerk is already scaffolded and theme integration genuinely must compose with it.

Phase 12.3 is design-system infrastructure only.

---

# CSS Baseline Boundary

## 42. Use CssBaseline Only as Theme Infrastructure

MUI `CssBaseline` may be wired if it is necessary for the canonical MUI foundation.

But do NOT implement the full branded global stylesheet yet.

Phase 12.4 owns:

```text
font loading
global CSS
root body behavior
font face
global resets beyond foundational MUI setup
```

Keep 12.3 scoped.

---

## 43. Do Not Override Canonical CSS Variables in CssBaseline

Never use:

```ts
styleOverrides: {
  ':root': {
    '--surface-canvas': '#...',
  }
}
```

to redefine design-system tokens.

The theme consumes tokens.

It does not own them.

---

# Theme Typing

## 44. Type Custom Semantic Additions

If custom semantic palette groups are exposed, TypeScript must understand them.

Do not use:

```ts
(theme as any).brand
```

or widespread casts.

Use narrow MUI module augmentation where justified.

---

## 45. Avoid Huge Theme Augmentation

Do not reproduce all 110 design tokens as:

```ts
theme.foo.bar.baz
```

if components can already consume CSS variables cleanly.

Expose only framework-semantic mappings necessary for MUI.

Canonical token access can remain CSS-variable based where appropriate.

---

# Theme API Rules

## 46. Theme Must Not Expose Raw Brand Decisions Twice

Avoid:

```ts
theme.palette.brandBrown
theme.palette.logoBrown
theme.custom.deepBrown
theme.colors.brown
```

all referring to:

```text
--accent-brand
```

One semantic mapping only.

---

## 47. Theme Consumers Use Semantics

Later code should prefer:

```text
theme.palette.text.primary
theme.palette.background.default
theme.palette.primary.main
theme.palette.brand.accent
```

or canonical CSS variables.

Do not encourage:

```tsx
color="#321E0F"
```

or:

```tsx
color="brown.900"
```

without semantic intent.

---

# Testing

## 48. Theme Unit / Contract Tests

Add lightweight tests appropriate to the current frontend tooling.

At minimum verify mappings for:

```text
background.default
→ surface.canvas

background.paper
→ surface.paper

text.primary
→ text.primary token

text.secondary
→ text.secondary token

primary.main
→ action.primary

brand accent
→ accent.brand

display font
→ font-display

UI font
→ font-ui
```

Tests should verify contract mapping, not implementation trivia.

---

## 49. No Raw Brand Hex Regression

Add a narrow regression where practical ensuring the MUI theme source does not become a second raw color source.

Acceptable exceptions:

```text
framework-required fallback
test fixture
documented compatibility value
```

Otherwise theme mappings should resolve through canonical design-system representations.

---

## 50. Typography Regression

Verify:

```text
body/default UI
→ utility font

buttons
→ utility font

display heading variants
→ Young Serif token
```

No accidental universal Young Serif.

---

## 51. Breakpoint Regression

Verify MUI breakpoints equal canonical design-system breakpoints.

Do not test generic MUI defaults.

---

## 52. Spacing Regression

Verify theme spacing maps to canonical scale or adapter behavior.

No silent MUI default spacing drift.

---

## 53. Radius Regression

Verify default MUI shape is based on the canonical radius system.

---

## 54. SSR Smoke Test

Build/render at least a minimal App Router page with the theme provider.

Prove:

```text
no hydration warning
no Emotion style-order warning
no runtime theme error
```

Do not turn this into page design.

A minimal existing root page is enough.

---

# Design-System Non-Regression

## 55. Do Not Modify Canonical Tokens Without a Genuine Defect

Phase 12.3 should mostly modify:

```text
frontend/web/*
```

If the theme exposes a genuine inconsistency in the token contract:

STOP and reconcile it explicitly rather than silently modifying canonical tokens.

Any design-system token change requires:

```text
source authority update
derived artifact synchronization
documentation
validation
```

Do not patch only `tokens.css`.

---

## 56. Preserve Brand Contract

Verify theme behavior against these principles:

- architectural, warm, editorial;
- UI is a quiet frame around furniture;
- charcoal/white remain anchors;
- brown is restrained;
- photography carries most color;
- Young Serif is display personality;
- UI sans provides precision;
- minimal decorative chrome;
- accessibility is non-negotiable.

These principles are explicitly established in `DESIGN.md`. DESIGN

---

# Files / Expected Scope

## 57. Likely Files

Depending on current frontend structure, expected files may include:

```text
frontend/web/theme/*
frontend/web/app/providers.*
frontend/web/app/layout.*
frontend/web/package.json        # only if required
frontend/web/tsconfig.json       # only if alias needed
frontend/web/tests/*             # or current test location

phases/group-L-phases.md
docs/decisions.md                # if implementation ADR warranted
```

Do not mechanically create all of them.

Follow current structure.

---

## 58. Do Not Touch

Do not modify:

```text
backend/*
docs/api/*
database
Laravel
Flutter app
public catalog components
homepage components
product cards
request forms
navigation
footer
```

unless a narrow repository build requirement demands a non-functional import adjustment.

---

# Documentation

## 59. Record Theme Mapping

Document, preferably in existing design-system usage docs or frontend architecture docs:

```text
canonical tokens.css
↓
MUI theme adapter
↓
ThemeProvider
```

Explain:

```text
which token artifact MUI consumes
where the theme lives
how to access brand semantic values
what is forbidden
```

---

## 60. ADR

Add the next repository-consistent design ADR if Phase 12.3 introduces a material architectural decision.

Suggested subject:

```text
DESIGN-002 — MUI Consumes the Canonical Furniture Token Contract
```

Record:

```text
tokens.css remains canonical

MUI does not redefine tokens

design-tokens.json may be consumed as synchronized build-time data
where concrete values are required by MUI

Young Serif is display-only

utility font is default MUI UI typography

charcoal is primary action

brand brown remains separate semantic accent

Next.js App Router uses official MUI SSR integration

theme provider is the smallest required client boundary
```

---

# Validation

## 61. Inspect Available Frontend Scripts

Before running commands inspect:

```text
frontend/web/package.json
```

Use the repository's actual scripts.

Do not invent:

```text
npm test
pnpm lint
```

if they do not exist.

---

## 62. Required Verification

Run applicable existing commands for:

```text
TypeScript
lint
tests
build
```

At minimum:

```text
typecheck: PASS
lint: PASS
theme contract tests: PASS
Next.js production build: PASS
```

Also rerun relevant design-system token validation if the theme imports derived token data.

---

## 63. Build Gate

A production:

```text
next build
```

or repository equivalent must succeed.

Do not claim Phase 12.3 closure on dev-server rendering only.

---

## 64. Warning Gate

Review build/test output for:

```text
hydration mismatches
unsupported MUI App Router adapter
deprecated MUI theme APIs
Emotion cache warnings
missing CSS token imports
font-loading errors
```

Do not ignore warnings that indicate architectural misuse.

---

# Git

## 65. Git Operations

After verification:

1. read root `git-workflow-and-versioning`;
2. inspect status;
3. stage only Phase 12.3 changes;
4. follow required commit/versioning convention;
5. commit;
6. push only if permitted/required by the skill.

Do not mix application-component work into the Phase 12.3 commit.

---

# Completion Report

## 66. Required Report

Return:

```text
Phase 12.3 status:
PASS / BLOCKED

MUI theme implemented:
YES / NO

Canonical token authority:
tokens.css

MUI token consumption:
<direct CSS vars / synchronized design-tokens.json / combined>

Second token authority created:
NO

Next.js version:
<value>

MUI version:
<value>

Official MUI App Router integration used:
YES / NO

Root layout remains Server Component:
YES / NO

Small client provider boundary:
YES / NO

Palette mapping:
PASS / FAIL

surface.canvas:
<theme mapping>

surface.paper:
<theme mapping>

text.primary:
<theme mapping>

text.secondary:
<theme mapping>

primary action:
#111111 semantic mapping / FAIL

brand accent:
#321E0F semantic mapping / FAIL

Brown used as default primary:
NO

Display font:
Young Serif token

Default UI font:
<existing utility token>

Young Serif universal:
NO

Canonical type scale preserved:
YES / NO

Spacing scale preserved:
YES / NO

Breakpoints preserved:
YES / NO

Radius scale preserved:
YES / NO

Elevation mapped/restrained:
PASS / FAIL

Motion mapped:
PASS / FAIL

Focus treatment:
PASS / FAIL

Dark mode added:
NO

Commerce components added:
NO

Pages designed:
NO

Font loading started:
NO

Backend/API changed:
NO

Dependencies:
NONE / <explain>

Theme contract tests:
<x> passed

TypeScript:
PASS / FAIL

Lint:
PASS / FAIL

Next.js build:
PASS / FAIL

Hydration/style warnings:
NONE / <list>

Design-system validation:
PASS / NOT REQUIRED

Files changed:
<list>

ADR:
<identifier / NONE>

Git workflow skill read:
YES / NO

Git operations:
<list>

Commit:
<hash/message>

Push:
<result or NONE>

Phase 12.4:
READY / BLOCKED
```

---

# Final STOP Condition

Phase 12.3 is PASS only when:

- MUI consumes the approved design-system contract;
- `tokens.css` remains the sole canonical token authority;
- no independent MUI color/spacing/type system is created;
- warm canvas maps correctly;
- white paper maps correctly;
- charcoal remains primary action;
- brown remains a restrained brand accent;
- Young Serif is limited to display/editorial roles;
- UI typography continues using the utility sans stack;
- canonical type scale remains intact;
- canonical spacing, radius, breakpoints, elevation and motion are preserved;
- MUI defaults that conflict with the brand are neutralized;
- accessibility/focus behavior remains intact;
- Next.js App Router SSR integration is correct for installed versions;
- only the smallest necessary client provider boundary exists;
- no font loading has begun;
- no pages or product components have begun;
- TypeScript/lint/tests/build pass;
- no significant hydration or styling warnings remain;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 12.3 — PASS
Phase 12.4 — READY
```

Do not begin Phase 12.4 automatically.

---

## Execution Record — 2026-10-06

### 12.3 — MUI Theme Implementation

**Status:** Complete

`frontend/web/theme/theme.ts` adapts synchronized design tokens to one typed MUI light theme. `app/providers.tsx` uses the official Next 16 MUI App Router cache provider and keeps the root layout server-rendered. No font loading, commerce components, page design, or backend work was introduced.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**
