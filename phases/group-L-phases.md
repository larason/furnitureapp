# Phase 12.5 — Visual Foundations

## Objective

Finalize and validate the shared SL Furnitures visual foundation for:

```text
Typography
Color
Spacing
Shape / radius
Elevation
Motion
Focus / interaction visibility
```

This phase consolidates the former separate roadmap concerns into one design-system foundation phase.

The output must give later UI implementation phases a precise answer to:

```text
Which typography role should I use?
Which surface/background role should I use?
Which spacing token should I use?
Which radius is appropriate?
When is elevation allowed?
Which transition should I use?
How should focus be shown?
```

without requiring the agent to invent visual values.

---

# 1. Active Scope

Implement only:

```text
Phase 12.5 — Visual Foundations
```

Do NOT begin:

```text
12.6 — Component conventions
12.7 — Accessibility baseline

Group M — Next.js Website Foundation
Group N — Website Catalog / SEO
Group O — Customer Commerce
Group P — Flutter Foundation
```

Do not build:

```text
PageContainer
Button primitive
ProductCard
Header
Footer
Navigation
Homepage
Product page
Request form
Flutter widget
```

This phase is the finalization of shared visual rules.

---

# 2. Authorities to Read First

Inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── tokens.css
├── design-tokens.json
├── tailwind-v4.css
├── manifest.json
├── components.html
├── components.manifest.json
├── flutter-material3.md
├── preview/
└── source/

phases/group-L-phases.md
docs/decisions.md
```

Also inspect the existing Phase 12.3 MUI theme implementation to ensure the foundation being documented matches the actual mappings.

Do not change runtime application behavior merely because a documentation value is easier.

---

# 3. Canonical Authority

Preserve:

```text
tokens.css
→ sole canonical token authority
```

with:

```text
design-tokens.json
→ synchronized portable representation

tailwind-v4.css
→ synchronized derived representation

MUI theme
→ framework consumer

future Flutter ThemeData
→ framework consumer
```

No new token authority may be introduced.

---

# 4. Git Workflow

Before any Git operation:

```text
read root git-workflow-and-versioning skill
```

Follow it exactly.

Git operations are authorized only through that skill.

Preserve unrelated owner work.

Never commit:

```text
.env
credentials
tokens
private keys
font binaries
local SDK state
```

Stage only Phase 12.5 files.

---

# 5. Phase Strategy

For each visual foundation:

```text
inspect existing token scale
      ↓
identify semantic roles
      ↓
identify duplication/drift
      ↓
preserve stable primitives
      ↓
document usage rules
      ↓
verify MUI mapping
      ↓
verify future Flutter mapping
      ↓
validate previews/accessibility
```

Do not redesign stable token scales merely to make this phase look substantial.

---

# 6. Typography Foundation

The approved family architecture remains:

```text
DISPLAY
→ Young Serif

UTILITY / UI
→ existing Helvetica Now utility stack
```

Young Serif supplies personality.

The utility stack supplies precision and scannability.

Do not alter this architecture without an explicit new design decision.

---

# 7. Typography Roles

Finalize a CLOSED set of semantic typography roles.

At minimum distinguish:

```text
display
heading
body
label
utility
```

Use current token naming where possible.

Do not create page-specific roles such as:

```text
homepageHeading
productHeroHeading
checkoutTitle
footerHeading
```

unless a truly reusable semantic need exists.

---

# 8. Canonical Type Scale

Preserve the approved scale:

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

Do not introduce intermediate arbitrary sizes.

Every semantic type role must map to one of the canonical sizes.

If the existing token system has named roles already, reconcile those names rather than adding a parallel scale.

---

# 9. Display Typography Rules

Young Serif may be used for:

```text
hero statements
major page headings
editorial section headings
category storytelling
select prominent product/collection titles
```

Do not use it for:

```text
navigation
buttons
forms
filters
search
breadcrumbs
prices
dense metadata
technical specifications
account/admin UI
status labels
```

unless a later explicitly approved component convention says otherwise.

---

# 10. UI Typography Rules

Utility typography should own:

```text
navigation
buttons
form controls
input labels
filter controls
prices
product metadata
dimensions
material names
status text
breadcrumbs
utility links
tables
account/admin interfaces
```

Do not allow browser/MUI defaults to silently substitute unrelated fonts.

---

# 11. Weight Rules

Inspect existing weight tokens.

Normalize usage around a small intentional set.

For example conceptually:

```text
regular
medium
semibold/bold where genuinely needed
```

Do not use many near-identical weights merely for visual micro-adjustment.

Young Serif should not be synthetically bolded if the loaded face does not support that style properly.

Actual font loading remains implementation-specific.

---

# 12. Line Height

Formalize line-height usage for:

```text
large display
heading
body
labels
```

Young Serif should retain enough line-height to avoid clipping and cramped editorial typography.

Do not use compressed sportswear-style leading.

---

# 13. Letter Spacing

Keep letter spacing restrained.

Do not introduce:

```text
aggressive negative tracking
wide all-caps tracking everywhere
```

The furniture brand is calm/editorial, not athletic.

Utility labels may use approved tracking where the design system already supports it.

---

# 14. Uppercase Policy

Do not force:

```text
BUTTONS
HEADINGS
NAVIGATION
```

to uppercase by default.

Uppercase is an intentional content treatment, not a universal component style.

---

# 15. Typography Responsiveness

Do not invent separate arbitrary mobile and desktop sizes.

Use the canonical scale and approved responsive role mappings.

Where display typography needs responsive reduction:

```text
96 → approved smaller token
48 → approved smaller token
```

not arbitrary interpolation unless the current token system intentionally supports fluid typography.

---

# 16. Fluid Typography

Inspect current tokens.

If fluid typography is not already part of the system:

```text
do not introduce clamp() merely because it is fashionable.
```

If it already exists, preserve its approved bounds.

---

# 17. Text Scaling Accessibility

Foundation must remain compatible with:

```text
browser zoom
user font scaling
mobile accessibility scaling
```

Do not document fixed-height containers that require text not to grow.

---

# 18. Color Foundation

Preserve approved semantic anchors:

```text
surface.canvas
→ #FCF4ED

surface.paper
→ #FFFFFF

surface.editorial
→ #F4E9DF

surface.inverse
→ #111111

text.primary
→ #111111

text.secondary
→ #707072

accent.brand
→ #321E0F

action.primary
→ #111111
```

These values already express the intended warm-but-disciplined furniture identity.

---

# 19. Color Philosophy

Formalize:

```text
Furniture photography carries most visual color.

Interface colors frame the products.
```

Do not add unnecessary decorative color families.

---

# 20. Charcoal Rule

`#111111` remains the main functional anchor for:

```text
primary text
primary CTA
strong icons
inverse surfaces
```

Do not replace it globally with brown.

---

# 21. Brown Rule

`#321E0F` remains a controlled brand/material accent.

Appropriate roles may include:

```text
editorial emphasis
brand moments
subtle material cue
select decorative detail
```

Inappropriate default use:

```text
every button
every link
every heading
every border
every icon
navigation chrome
```

---

# 22. White Rule

`#FFFFFF` remains the pure clean surface for:

```text
product surfaces
form surfaces
clean content blocks
visual relief
```

Do not remove white because the default canvas is warm ivory.

---

# 23. Editorial Surface Rule

`#F4E9DF` should be reserved for:

```text
storytelling sections
brand/editorial blocks
quiet visual differentiation
```

Do not use it as a random card background.

---

# 24. Functional Colors

Inspect and preserve canonical roles for:

```text
success
warning
error
information
focus
```

Do not repurpose functional colors for decorative branding.

---

# 25. Product Status Semantics

Document that:

```text
MADE_TO_ORDER
```

is not:

```text
warning
error
unavailable
```

It is a normal primary offering.

Likewise future:

```text
IN_STOCK
LOW_STOCK
UNAVAILABLE
```

should use semantic status treatment rather than arbitrary decorative colors.

Do not implement the actual badge components yet.

---

# 26. Contrast Validation

Validate at minimum:

```text
text.primary on surface.canvas
text.secondary on surface.canvas
text.primary on surface.paper
text.secondary on surface.paper

inverse text on surface.inverse

primary action foreground/background

secondary action

focus indicator on:
- canvas
- paper
- editorial
- inverse

functional status foreground/background pairs
```

Use WCAG-compliant targets according to text/control usage.

---

# 27. Color-Only Communication Prohibited

Document explicitly:

```text
state may not be communicated by color alone
```

Future status components must combine color with:

```text
text
icon
shape
position
```

as appropriate.

---

# 28. Spacing Foundation

Inspect the existing spacing scale.

Preserve it if coherent.

Do NOT create a second spacing scale for:

```text
MUI
Flutter
homepage
mobile
```

All framework consumers map to the shared canonical scale.

---

# 29. Spacing Categories

Document intended spacing use conceptually:

```text
micro spacing
control internal spacing
component spacing
content spacing
section spacing
page/container spacing
```

Map these to existing primitives.

Do not create raw values unless a repeated semantic need cannot be represented by existing tokens.

---

# 30. Micro Spacing

Micro spacing is for:

```text
icon ↔ label
small metadata separation
inline elements
tight control composition
```

Do not use large layout tokens inside controls.

---

# 31. Component Spacing

Component spacing is for:

```text
card content
form fields
product information blocks
list items
```

This phase documents the rhythm.

It does not build the components.

---

# 32. Section Spacing

Define/reconcile semantic section spacing using existing primitives.

Conceptually:

```text
section.compact
section.default
section.spacious
section.editorial
```

ONLY if such aliases are justified.

Do not create new raw pixel values when existing primitives suffice.

---

# 33. Page Gutters

Inspect existing container/gutter tokens.

Document how future responsive layouts should use:

```text
mobile gutter
tablet gutter
desktop gutter
wide editorial gutter
```

Do not implement `PageContainer`.

That belongs to Group M layout work.

---

# 34. Grid Gaps

Document consistent grid gaps for:

```text
product grids
category grids
editorial grids
forms
```

using existing spacing primitives.

Do not hard-code product-grid values into Group L tokens if a generic grid gap role is sufficient.

---

# 35. Avoid Arbitrary Numeric Multipliers

Later MUI code should not rely on unexplained:

```text
theme.spacing(7.25)
```

Flutter should not rely on:

```text
EdgeInsets.all(19)
```

unless those values correspond to approved tokens.

---

# 36. Shape Foundation

Preserve the current philosophy:

```text
sharp media
small form radius
controlled container radius
pill only for controls that genuinely need it
```

Do not turn every surface into a rounded card.

---

# 37. Radius Roles

Finalize semantic radius usage.

At minimum distinguish conceptually:

```text
none/sharp
control
container
pill
```

Use current token names.

Do not create many nearly-identical radii.

---

# 38. Media Radius

Product imagery should generally remain visually architectural.

If canonical guidance says:

```text
sharp media
```

preserve it.

Do not round every furniture image because MUI/Material defaults use rounded surfaces elsewhere.

---

# 39. Form Radius

Inputs, selects, buttons, and interactive controls should use the approved control radius consistently.

Do not allow:

```text
button 24px
input 6px
select 14px
search field 40px
```

unless the token system intentionally distinguishes those roles.

---

# 40. Pill Geometry

Use pill geometry only for components that semantically justify it, such as potentially:

```text
small state chips
segmented controls
compact filter tokens
```

Do not make:

```text
cards
inputs
dialogs
navigation
product tiles
```

pill-like.

---

# 41. Elevation Foundation

Preserve:

```text
flat surfaces first
```

Use:

```text
spacing
border
surface difference
photographic depth
```

before shadows.

---

# 42. Elevation Roles

Finalize a minimal meaningful elevation hierarchy.

Conceptually:

```text
none
raised
overlay
modal
```

Use the current token scale rather than inventing those names if equivalents already exist.

---

# 43. No Decorative Card Shadows

Product cards, editorial sections, and ordinary content containers should not automatically use elevation.

Furniture imagery and whitespace should provide visual richness.

---

# 44. True Layers

Elevation is appropriate for actual layers such as:

```text
menu
popover
dialog
drawer
bottom sheet
tooltip where appropriate
sticky overlay
```

Do not eliminate depth where it communicates hierarchy/accessibility.

---

# 45. MUI Elevation Reconciliation

Inspect Phase 12.3 MUI theme behavior.

Ensure MUI defaults are not reintroducing:

```text
heavy Paper shadows
Card elevation
floating surfaces everywhere
```

If the theme already correctly maps the canonical elevation system:

```text
leave it.
```

If a small correction is required:

```text
fix the mapping only.
```

Do not redesign components.

---

# 46. Future Flutter Elevation Reconciliation

Update `flutter-material3.md` only if Phase 12.5 clarifies an existing ambiguity.

Do not implement Flutter code.

Future Material 3 should use canonical elevation semantics rather than default shadow-heavy styling.

---

# 47. Motion Foundation

Preserve existing duration/easing tokens.

Motion should remain:

```text
quiet
purposeful
functional
```

---

# 48. Motion Categories

Document semantic motion purposes such as:

```text
instant/feedback
standard
enter/exit
overlay
```

only if current tokens support a clean mapping.

Do not invent dozens of animation durations.

---

# 49. Prohibited Motion

Explicitly prohibit default use of:

```text
springy cards
bounce
excessive hover scaling
constant parallax
decorative rotations
continuous animation
large page-transition spectacle
```

---

# 50. Hover Motion

On pointer devices, future hover states should be subtle.

Prefer:

```text
color
underline
border
small opacity change
```

over:

```text
large translateY
large scale
deep shadow
```

Do not implement hover styles yet.

---

# 51. Reduced Motion

Document that all future framework implementations must respect reduced-motion preferences.

Motion must never be essential for understanding content or state.

---

# 52. Focus Foundation

Focus is a visual foundation and must be finalized here.

Ensure a semantic focus token/treatment exists.

It must remain visible on:

```text
warm canvas
white paper
editorial surface
inverse surface
```

---

# 53. Focus Cannot Be Removed

Explicitly prohibit:

```css
outline: none;
```

without an approved visible replacement.

No future component may hide keyboard focus for aesthetics.

---

# 54. Focus vs Hover

Document that:

```text
hover
focus
active
disabled
```

are separate interaction states.

Do not make focus merely copy hover behavior.

---

# 55. Disabled State

Document disabled-state principles:

```text
clearly non-interactive
still readable enough
not communicated only by opacity when that harms legibility
```

Do not introduce exact component styles yet.

---

# 56. Active / Pressed State

Document that active/pressed states should derive from semantic action tokens rather than arbitrary darker/lighter colors.

---

# 57. Icon Foundation

The website icon source has already been approved:

```text
@mui/icons-material
```

Do not add:

```text
Lucide
React Icons
Heroicons
Font Awesome
custom icon packs
emoji-as-icons
```

Future Flutter uses the Material icon family.

Phase 12.5 should document shared icon principles only:

```text
meaning before decoration
consistent stroke/fill family where possible
semantic theme colors
accessible names for meaningful icon-only actions
decorative icons hidden from accessibility APIs
```

Do not build icon wrappers yet.

---

# 58. Icon Sizes

If the design system already defines icon-size tokens:

```text
preserve them.
```

If not, only add a small semantic size scale if repeated visual evidence justifies it.

Do not create many arbitrary sizes.

---

# 59. Photography Interaction With Foundations

Reconfirm that photography remains the dominant source of visual color.

Spacing and surface choices must allow images to breathe.

Do not compensate for strong imagery by adding excessive borders/shadows/brand colors.

---

# 60. Image Backgrounds

Where isolated furniture product imagery uses a clean background:

```text
surface.paper
```

or approved product-media surface should be preferred.

Do not invent gray/beige image backgrounds per component later.

---

# 61. Cross-Platform Semantic Matrix

Update/reconcile the shared mapping so important foundations mean the same thing across:

```text
Canonical token
Web / MUI
Future Flutter / Material 3
```

Include at least:

```text
display typography
UI typography
canvas
paper
editorial
inverse
primary text
secondary text
primary action
brand accent
border
focus
control radius
container radius
elevation
standard motion
```

Do not require identical framework API names.

Meaning must remain identical.

---

# 62. MUI Non-Regression Review

Review the completed Phase 12.3 theme.

Check:

```text
typography mappings
palette mappings
spacing
shape
shadows
transitions
focus behavior
breakpoints
```

against the finalized Phase 12.5 rules.

If already compliant:

```text
do not rewrite.
```

If not:

```text
apply smallest necessary reconciliation.
```

---

# 63. Do Not Pull Group M Forward

Do not implement:

```text
API client
routing
layout primitives
error pages
responsive page shell
loading UI
not-found page
```

Those are Group M.

Phase 12.5 may define rules they will later use.

---

# 64. Do Not Pull Group N Forward

Do not build:

```text
homepage
category page
product listing
product detail
search
filters
SEO metadata
structured data
sitemap
robots
```

Those are Group N.

---

# 65. Design-System Preview

Update existing design-system previews where supported to visibly demonstrate:

```text
typography hierarchy
surface hierarchy
spacing rhythm
radius examples
elevation examples
motion tokens if preview tooling supports them
focus treatment
```

Do not build an application page as a preview.

Use the existing preview system.

---

# 66. Preview Must Be Token-Driven

Preview files must not introduce independent raw visual values.

They should consume canonical tokens.

Any raw values required purely for preview scaffolding must be clearly non-brand/layout infrastructure.

---

# 67. Typography Preview

Ensure preview demonstrates:

```text
Young Serif display
UI sans body
labels
metadata
price/utility
multiple canonical sizes
```

Do not add noncanonical type sizes for showcase aesthetics.

---

# 68. Color Preview

Ensure preview demonstrates:

```text
canvas
paper
editorial
inverse
primary text
secondary text
brand accent
primary action
functional states
focus
```

---

# 69. Spacing Preview

Demonstrate the existing spacing scale in a way that makes relationships obvious.

Do not show arbitrary values outside the scale.

---

# 70. Shape Preview

Demonstrate:

```text
sharp
control radius
container radius
pill
```

using canonical tokens.

---

# 71. Elevation Preview

Demonstrate:

```text
flat/default
true raised layer
overlay/modal depth
```

without turning the preview into a gallery of decorative shadows.

---

# 72. Accessibility Preview

Where supported, demonstrate keyboard focus visibly.

Ensure preview examples preserve readable contrast.

---

# 73. Token Inventory Audit

Run a focused audit for:

```text
unused semantic tokens
duplicate semantic aliases
two aliases representing identical meaning without reason
primitive values bypassing semantics
framework-specific token leakage
```

Do not delete tokens merely because no application component uses them yet.

Delete/reconcile only obvious design-system duplication or stale legacy drift.

---

# 74. Legacy Nike Audit

Search active design guidance for any remaining visual instructions that conflict with the current furniture brand, including:

```text
athletic
sport
performance
aggressive typography
shoe
sneaker
apparel
sportswear
```

Historical provenance references are allowed.

Active UI guidance must be furniture-oriented.

---

# 75. Raw-Value Audit

Search future-consumer documentation/examples for direct:

```text
hex
rgb
hsl
px
rem
shadow
transition
radius
```

values that bypass approved tokens.

Primitive definitions may contain raw values.

Consumer examples should normally use semantic tokens.

---

# 76. Anti-AI-Slop Rules

Reconfirm in `USAGE.md` / `frontend/AGENTS.md` as appropriate:

Future agents must not invent:

```text
random gradients
glassmorphism
blur cards
decorative blobs
random pills
oversized radius
heavy shadows
random animation
one-off type sizes
one-off spacing
unapproved colors
arbitrary font changes
```

when implementing later UI.

---

# 77. Escape Hatch

Keep the approved process:

```text
Need a visual value
        ↓
Does approved token exist?
        ↓
YES → use it
NO  → determine whether reusable semantic need exists
        ↓
update token authority
        ↓
synchronize derived artifacts
        ↓
validate
        ↓
consume
```

Never:

```text
Need value
→ hard-code locally
```

---

# 78. Documentation Updates

Expected likely updates:

```text
frontend/design-system/DESIGN.md
frontend/design-system/USAGE.md
frontend/design-system/preview/*
frontend/design-system/source/*
frontend/design-system/flutter-material3.md   # only if clarification needed
frontend/AGENTS.md                            # only enforcement changes

phases/group-L-phases.md
docs/decisions.md
```

Do not edit every file unless necessary.

---

# 79. ADR

Add the next repository-consistent design ADR if Phase 12.5 formalizes material decisions not already covered by DESIGN-001/002/003.

Suggested subject:

```text
DESIGN-004 — Shared Visual Foundation Semantics
```

Record:

```text
Young Serif remains display-only

utility sans remains UI default

canonical type scale remains unchanged

warm canvas / paper / editorial / inverse surfaces remain distinct

charcoal remains primary action

brown remains restrained brand accent

shared spacing scale remains authoritative

shape remains restrained

elevation indicates true layering only

motion remains functional and quiet

focus visibility is mandatory

framework mappings consume these semantics
```

Do not add an ADR just to repeat existing documentation if no new architecture decision exists.

---

# 80. No New Dependencies

Expected:

```text
dependencies = NONE
```

Do not install:

```text
animation libraries
design-token libraries
color libraries
font packages
icon packages
CSS frameworks
```

MUI and MUI Icons are already installed from Phase 12.3.

---

# 81. No Font Installation

Do not perform:

```text
next/font integration
Google Fonts import
@font-face
Flutter font assets
font package installation
```

unless Phase 12.3 already necessarily established a harmless placeholder mechanism.

Actual loading remains owned by the appropriate application-foundation phase.

---

# 82. Validation

Run all existing design-system validation.

At minimum:

```text
JSON parsing
token reference resolution
CSS token validation
derived representation synchronization
WCAG contrast validation
framework-leak checks
legacy apparel terminology audit
raw-value/ad-hoc-value audit
git diff --check
```

---

# 83. MUI Verification

Because MUI theme implementation already exists, also run the relevant frontend checks if Phase 12.5 modifies any MUI mapping.

Use the actual scripts in:

```text
frontend/web/package.json
```

Potential categories:

```text
typecheck
lint
theme tests
build
```

Do not invent command names.

---

# 84. No Need to Rebuild Web If Untouched

If Phase 12.5 makes no changes under:

```text
frontend/web/
```

a full Next.js build is optional unless project validation requires it.

Still validate the design-system artifacts fully.

---

# 85. Cross-Platform Validation

Verify:

```text
MUI semantic mapping
and
future Flutter mapping
```

both align with the finalized visual rules.

No framework may reinterpret:

```text
primary
canvas
brand accent
display typography
radius
elevation
```

differently.

---

# 86. Phase Completion Report

Return:

```text
Phase 12.5 status:
PASS / BLOCKED

Typography foundation:
PASS / FAIL

Display family:
Young Serif

UI family:
<approved utility stack>

Canonical type scale:
12 / 14 / 16 / 20 / 24 / 32 / 48 / 96

New arbitrary type sizes:
NONE

Color foundation:
PASS / FAIL

Canvas:
#FCF4ED

Paper:
#FFFFFF

Editorial:
#F4E9DF

Inverse:
#111111

Primary text:
#111111

Secondary text:
#707072

Primary action:
#111111

Brand accent:
#321E0F

Functional color semantics:
PASS / FAIL

MADE_TO_ORDER treated as error:
NO

Contrast:
PASS / FAIL

Spacing foundation:
PASS / FAIL

Spacing scale:
PRESERVED / RECONCILED

Section spacing semantics:
<summary>

Page gutter semantics:
<summary>

Shape foundation:
PASS / FAIL

Media shape:
<summary>

Control radius:
<token>

Container radius:
<token>

Pill use restricted:
YES / NO

Elevation foundation:
PASS / FAIL

Decorative card elevation:
PROHIBITED / FAIL

True layer elevation:
PASS / FAIL

Motion foundation:
PASS / FAIL

Reduced-motion rule:
PASS / FAIL

Focus foundation:
PASS / FAIL

Focus removed globally:
NO

Icon policy:
MUI Icons web / Material Icons Flutter

Cross-platform semantic matrix:
PASS / FAIL

MUI reconciliation required:
YES / NO

MUI files changed:
<list / NONE>

Flutter implementation changed:
NO

Application components created:
NO

Group M work started:
NO

Group N work started:
NO

Dependencies added:
NONE

Design previews:
PASS / NOT CHANGED / FAIL

Token validation:
<commands/results>

WCAG validation:
PASS / FAIL

Legacy Nike/apparel audit:
PASS / FAIL

Raw-value audit:
PASS / FAIL

Files changed:
<list>

ADR:
<id / NONE>

Git workflow skill read:
YES / NO

Git operations:
<list>

Commit:
<hash/message>

Push:
<result/NONE>

Phase 12.6:
READY / BLOCKED
```

---

# 87. STOP Condition

Phase 12.5 may be declared PASS only when:

- typography semantics are explicit;
- Young Serif remains display/editorial only;
- utility sans remains UI default;
- canonical type scale remains authoritative;
- no arbitrary type sizes are introduced;
- semantic color hierarchy is explicit;
- charcoal remains primary action;
- brown remains restrained brand accent;
- canvas, paper, editorial, and inverse surfaces remain distinct;
- functional colors retain functional meaning;
- MADE_TO_ORDER is not treated as an error/warning;
- contrast validation passes;
- spacing hierarchy and usage rules are explicit;
- shared spacing primitives remain authoritative;
- shape usage is explicit and restrained;
- media is not arbitrarily rounded;
- pills are limited to justified controls;
- elevation is reserved for real layering;
- decorative card shadows are prohibited;
- motion remains quiet and functional;
- reduced-motion requirements are preserved;
- focus treatment is visible and mandatory;
- web and Flutter mappings remain semantically aligned;
- no application component/page work has started;
- no Group M or N implementation has leaked forward;
- no new dependencies were added;
- design-system validation passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 12.5 — PASS
Phase 12.6 — READY
```

Do not start Phase 12.6 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Execution Record — 2026-10-06

### 12.5 — Visual Foundations

**Status:** Complete

`frontend/design-system/USAGE.md` now closes the shared typography, color, spacing, shape, elevation, motion, interaction, and photography rules. Existing token-driven previews demonstrate inverse/action/focus, utility typography, true-layer elevation, and focus states. The existing MUI theme and Flutter mapping contract were reviewed without runtime changes.
