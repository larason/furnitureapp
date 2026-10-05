# Phase Group L — Frontend Design System Foundation

## Active Scope

Implement only:

```text
Phase 12.1 — Furniture Brand / Design Goals
Phase 12.2 — Token Authority and Reconciliation
```

Do NOT begin:

```text
12.3 MUI theme implementation
12.4 global CSS/font implementation
12.5 layout primitives
12.6 UI primitives
12.7 commerce primitives
12.8 navigation/header/footer
12.9 catalog components
12.10 product-detail components
12.11 request/enquiry components
12.12 pages
```

These two phases establish the design contract that all later Group L work must follow.

---

# 1. Project Context

Repository:

```text
furnitureapp/
```

Relevant frontend structure:

```text
frontend/
├── app/
├── web/
├── design-system/
│   ├── preview/
│   ├── source/
│   ├── components.html
│   ├── components.manifest.json
│   ├── DESIGN.md
│   ├── design-tokens.json
│   ├── manifest.json
│   ├── tailwind-v4.css
│   ├── tokens.css
│   └── USAGE.md
└── AGENTS.md
```

Important project references:

```text
designs/
├── brandlogo.png
├── Desktop-Top-Banner.jpeg
├── heroimage.png
├── homepage.png
├── homepage2.png
├── productcards.png
├── appconcept.webp
└── appmockup.webp
```

The design system originated from the Open Design Nike system but has already been adapted away from clothing-specific language toward furniture.

Do NOT restore Nike apparel wording.

Do NOT re-import/re-clone the original Nike files over the current furniture adaptation.

The existing:

```text
frontend/design-system/
```

is now the project's design-system source.

---

# 2. Engineering Context

The public website will use:

```text
Next.js
App Router
TypeScript
Material UI
```

The mobile application will later use:

```text
Flutter
Material 3
```

The design system must therefore describe a **shared visual language**, while allowing framework-specific implementation.

The project already requires shared concepts for:

```text
color
typography
spacing
shape/radius
elevation
interaction
```

Do not make the design-system specification Next.js-only.

The token representation should remain portable enough for:

```text
MUI
Flutter Material 3
CSS
design previews
```

---

# 3. Design-System Dependency Direction

The architecture must be:

```text
Brand intent
      ↓
Primitive tokens
      ↓
Semantic furniture tokens
      ↓
Framework mappings
      ↓
MUI / Flutter themes
      ↓
UI primitives
      ↓
Commerce components
      ↓
Pages
```

Never:

```text
Page
↓
random CSS
↓
later copied into theme
```

And never:

```text
Component
↓
hard-coded hex / px / radius
```

unless explicitly documented as an exceptional asset-specific case.

---

# 4. Phase 12.1 Objective

Phase 12.1 defines what the furniture brand should **feel like** before changing token values.

It must answer:

```text
What remains from the Nike design grammar?

What changes for furniture?

What visual emotions should the brand communicate?

How should typography, photography, whitespace,
surfaces and product presentation behave?

What visual patterns are prohibited?
```

Phase 12.1 is a **brand/design-contract phase**, not a component implementation phase.

---

# 5. Source Material to Inspect

Before making design decisions, inspect:

```text
frontend/design-system/DESIGN.md
frontend/design-system/design-tokens.json
frontend/design-system/tokens.css
frontend/design-system/tailwind-v4.css
frontend/design-system/USAGE.md
frontend/design-system/manifest.json
frontend/design-system/components.manifest.json
frontend/design-system/components.html

frontend/design-system/source/*
frontend/design-system/preview/*

frontend/AGENTS.md

designs/brandlogo.png
designs/Desktop-Top-Banner.jpeg
designs/heroimage.png
designs/homepage.png
designs/homepage2.png
designs/mobiledesign.png
designs/productcards.png
designs/appconcept.webp
designs/appmockup.webp

docs/VISION.md
AGENTS.md
```

Do not infer token names from previous Nike documentation if the current files already changed them.

The current repository wins.

---

# 6. Official Brand Logo

Treat:

```text
designs/brandlogo.png
```

as the official brand identity asset.

The current logo contains:

```text
SL FURNITURES
PREMIUM FOR LESS
```

Do not redraw, reinterpret, restyle, trace, recolor or replace the logo during 12.1/12.2.

Do not create a new logo.

Do not edit the image asset.

The design system may document how the logo influences brand direction.

---

# 7. Logo Color Reference

The supplied logo visually establishes a warm furniture identity.

Approximate dominant source colors from the supplied asset are:

```text
warm ivory background ≈ #FCF4ED
deep brown lettering ≈ #321E0F
```

These are **brand-reference observations**, not automatically mandatory primitive tokens.

During Phase 12.2 determine whether they should become:

```text
brand
surface
text/accent
```

tokens, or whether nearby normalized values already exist in the current token system.

Do NOT proliferate nearly identical neutrals unnecessarily.

For example, avoid ending up with:

```text
#FCF4ED
#FDF5EE
#FAF3EC
#FBF4ED
```

all representing the same semantic surface.

Normalize deliberately.

---

# 8. Nike Heritage — Preserve the Design Grammar

The project should retain the valuable parts of the Nike-derived system:

```text
strong hierarchy
large editorial imagery
generous whitespace
minimal UI chrome
high-contrast focal moments
disciplined spacing
strong grid
clear CTA hierarchy
simple surfaces
restrained decoration
responsive composition
accessible contrast
consistent interaction states
```

Do NOT interpret "furniture oriented" as requiring a completely new design system.

The objective is:

```text
Nike structural discipline
+
furniture emotional language
```

---

# 9. Brand Translation

Translate the emotional character from:

```text
athletic
urgent
performance-driven
sport
sharp
high-energy
```

toward:

```text
architectural
calm
editorial
crafted
warm
material-led
premium
approachable
confident
spacious
```

The brand should feel like:

```text
a curated furniture showroom
+
an interiors editorial publication
+
a usable commerce experience
```

not:

```text
sportswear
SaaS dashboard
marketplace clutter
luxury-for-luxury's-sake
generic AI storefront
```

---

# 10. Core Brand Principle

Record a concise brand principle approximately equivalent to:

> SL Furnitures should feel architectural, warm and editorial. The interface provides a quiet, disciplined frame around furniture photography and product information rather than competing with the products themselves.

Adapt wording to the repository's documentation style.

This principle becomes authoritative for later agents.

---

# 11. Brand Personality

Formalize a small CLOSED brand-character set.

Recommended direction:

```text
CALM
CRAFTED
ARCHITECTURAL
WARM
EDITORIAL
ACCESSIBLE
```

Do not create dozens of vague adjectives.

Each characteristic should include a practical visual consequence.

Example:

```text
CALM
→ generous whitespace
→ restrained number of simultaneous calls to action
→ little decorative noise

CRAFTED
→ careful typography
→ material photography
→ precise details

ARCHITECTURAL
→ strong grids
→ alignment
→ scale
→ clear geometry

WARM
→ warm neutral surfaces
→ brown/wood-inspired supporting accents

EDITORIAL
→ large photography
→ serif display typography
→ story-led sections

ACCESSIBLE
→ readable UI typography
→ strong contrast
→ visible focus states
→ assistive-technology support
```

---

# 12. Anti-Brand Characteristics

Document explicitly what the brand must NOT become.

At minimum:

```text
not sporty
not neon
not hyperactive
not glassmorphic
not gradient-heavy
not excessively rounded
not card-heavy
not dashboard-like
not badge-heavy
not ornamental luxury
not rustic cliché
not "everything brown"
not AI-generated SaaS styling
```

These are enforcement constraints, not mood-board suggestions.

---

# 13. Typography Direction

The approved brand/display family is:

```text
Young Serif
```

Reference:

```text
Google Fonts — Young Serif
```

Phase 12.1 must establish its role.

Young Serif should primarily communicate:

```text
brand
editorial storytelling
hero messaging
major section headings
category storytelling
select product-display typography
```

Do not yet implement font loading in `frontend/web`.

That belongs to the following implementation phase.

---

# 14. Young Serif Must Not Automatically Own Every UI Role

Do NOT assume:

```text
font-family: Young Serif
```

means every interface string should use Young Serif.

Phase 12.1 should explicitly distinguish:

```text
DISPLAY / BRAND TYPOGRAPHY
and
UTILITY / INTERFACE TYPOGRAPHY
```

Recommended architecture:

```text
Young Serif
→ hero/display
→ major headings
→ editorial sections
→ high-emphasis product/category titles

Utility sans-serif
→ navigation
→ buttons
→ forms
→ filters
→ search
→ price
→ metadata
→ specifications
→ breadcrumbs
→ account/admin interfaces
```

Before choosing a new utility font, inspect what the existing furniture-adapted Nike system currently uses.

Do NOT introduce a new font merely because this prompt gives an example.

If a suitable existing sans-serif token already exists:

```text
preserve it.
```

---

# 15. Typography Principle

The desired typography contrast is:

```text
Young Serif
→ personality

utility sans
→ precision
```

Do not make the interface difficult to scan merely to maximize brand character.

Commerce usability wins over decorative consistency.

---

# 16. Color Direction

Preserve the Nike-derived anchors where useful:

```text
#111111
#FFFFFF
```

Do not delete them simply because the brand is furniture-oriented.

Recommended semantic roles:

```text
#111111
→ deepest neutral
→ primary CTA
→ strong typography
→ inverse surfaces

#FFFFFF
→ pure product surface
→ clean contrast surface
```

Furniture warmth should be introduced primarily through:

```text
warm canvas surfaces
warm secondary neutrals
deep brown brand accents
material photography
```

not by making every component brown.

---

# 17. Furniture Color Philosophy

Document this principle:

```text
Furniture and room photography carries most of the visual color.

The UI remains restrained enough to frame those materials.
```

That means product photography may naturally contain:

```text
oak
walnut
linen
leather
cream
stone
terracotta
green
blackened metal
```

The interface should not fight those colors.

---

# 18. Avoid "Wood Means Brown UI"

Prohibit:

```text
brown primary button everywhere
brown heading everywhere
brown border everywhere
brown icon everywhere
brown navigation everywhere
```

A brown/wood-derived brand color should be a supporting semantic accent.

Primary actions may remain strong charcoal.

---

# 19. Surface Hierarchy

Phase 12.1 should establish the conceptual surface hierarchy:

```text
canvas
product/paper
editorial/secondary
inverse
```

Do not choose final raw values until Phase 12.2 reconciles current tokens.

Conceptually:

```text
canvas
→ warm off-white

product/paper
→ pure white

editorial
→ warm stone / warm neutral

inverse
→ charcoal/deep neutral
```

---

# 20. Photography Direction

Furniture photography is a first-class design element.

Document expectations:

```text
large imagery
room context where useful
clean product isolation where useful
natural materials
realistic scale
minimal visual overlays
little UI text painted over busy photography
```

Avoid:

```text
tiny thumbnails dominating layouts
many badges over images
heavy gradients over every photograph
text overlays with weak contrast
arbitrary cropping
```

---

# 21. Product Card Philosophy

Product cards should remain restrained.

Prefer:

```text
image
product name
price / starting price
small availability/request state
```

Do not make every Product card contain:

```text
many badges
multiple CTA buttons
long description
large ratings block
shipping marketing
floating action clusters
decorative cards inside cards
```

Actual component implementation belongs later.

Phase 12.1 only records the principle.

---

# 22. MADE_TO_ORDER Brand Treatment

Initial production mode is request-only and publishes:

```text
MADE_TO_ORDER
```

Products.

The design language must support this naturally.

Do not make MADE_TO_ORDER appear like an error or unavailable state.

It is a primary business offering.

Visual language should support:

```text
Made to order
Customizable/requestable
Request this piece
```

with premium, deliberate presentation.

Do not implement the page/CTA yet.

---

# 23. Future IN_STOCK Compatibility

The design system must still support the frozen future:

```text
IN_STOCK
```

commerce workflow.

Do not build a token system that only makes sense for request-only production.

Later components need semantic room for:

```text
In stock
Low stock
Made to order
Unavailable
```

without changing base design tokens.

---

# 24. Shape Philosophy

Retain restrained shapes.

Furniture branding does NOT require:

```text
huge border radii
pill-shaped everything
floating rounded cards
bubble UI
```

Preserve current Nike-derived shape discipline unless existing adaptation intentionally changed it.

If current radius tokens are coherent:

```text
keep the scale.
```

Phase 12.2 may assign semantic usage, but should not invent many new radius values.

---

# 25. Shadow / Elevation Philosophy

Prefer:

```text
flat surfaces
borders
spacing
photographic depth
```

over heavy shadows.

Elevation should communicate actual layering:

```text
menu
dialog
drawer
popover
sticky element where appropriate
```

not decorate every card.

Preserve/reconcile existing elevation tokens.

---

# 26. Motion Philosophy

Preserve existing motion tokens where possible.

Furniture UI motion should feel:

```text
quiet
purposeful
smooth
not theatrical
```

Do not introduce:

```text
springy product cards
constant parallax
excessive hover scaling
large bouncing CTAs
```

Motion remains functional.

---

# 27. Accessibility Is Brand Quality

Accessibility is not optional.

Phase 12.1 must record:

```text
WCAG-aware color contrast
visible keyboard focus
semantic HTML
screen-reader support
reduced-motion respect
large enough interactive targets
meaningful image alt text
heading hierarchy
no color-only state communication
```

The master project explicitly requires strong assistive-technology support.

Preserve that requirement.

---

# 28. Responsive Philosophy

The web design must not simply shrink desktop.

Document:

```text
same brand language
different responsive composition
```

Large editorial desktop arrangements may become:

```text
stacked
reordered
simplified
```

on mobile.

Do not force desktop layouts onto narrow screens.

Do not make the site client-only for visual convenience.

---

# 29. Phase 12.1 Deliverables

Update/create the repository-consistent documentation needed to formally record:

```text
brand mission
brand personality
Nike elements retained
furniture changes
logo authority
typography direction
color philosophy
photography principles
shape/elevation/motion principles
accessibility principles
anti-patterns
```

Primary authority should be:

```text
frontend/design-system/DESIGN.md
```

Update:

```text
frontend/AGENTS.md
```

only where enforcement rules need clarification.

Also record Phase 12.1 in:

```text
phases/group-L-phases.md
```

Create that Group L phase file if it does not yet exist, following the format of existing group phase documents.

Record a design-system ADR in:

```text
docs/decisions.md
```

using the next repository-consistent identifier.

---

# 30. Phase 12.1 STOP Gate

Phase 12.1 passes only when an agent reading:

```text
DESIGN.md
USAGE.md
frontend/AGENTS.md
```

can clearly answer:

```text
What does SL Furnitures look and feel like?

What parts of Nike were intentionally preserved?

Why is Young Serif used?

Where should Young Serif be used?

How does furniture warmth enter the system?

What styling patterns are forbidden?

How should photography behave?

What does accessibility require?
```

No MUI theme implementation yet.

---

# 31. Phase 12.2 Objective

Phase 12.2 reconciles the current Nike-derived token system into a canonical furniture token model.

It must NOT start from blank files.

The process is:

```text
inventory current tokens
      ↓
classify primitives
      ↓
identify duplicates/drift
      ↓
preserve stable scales
      ↓
introduce semantic furniture aliases
      ↓
reconcile generated representations
      ↓
validate all outputs
```

---

# 32. Token Source Authority

First determine which file under:

```text
frontend/design-system/source/
```

or another documented design-system source is the canonical generator/source.

Do NOT assume:

```text
tokens.css
design-tokens.json
tailwind-v4.css
```

are all independently hand-maintained.

Inspect:

```text
manifest.json
USAGE.md
source/*
```

for generation/authority relationships.

There must be one documented source-of-truth direction.

For example:

```text
source tokens
      ↓
design-tokens.json
      ↓
tokens.css
      ↓
tailwind-v4.css
      ↓
framework consumers
```

ONLY if that is how the repository actually works.

Do not invent a generation pipeline that does not exist.

---

# 33. Never Maintain Duplicate Authorities

Do not allow:

```text
design-tokens.json says one value

tokens.css says another

Tailwind says another

DESIGN.md says another
```

Determine which artifacts are:

```text
authoritative
generated
documentary
preview
```

and record this explicitly.

---

# 34. Inventory Every Token Family

Create an inventory of existing token categories.

At minimum inspect:

```text
color
typography
font family
font size
font weight
line height
letter spacing

spacing

breakpoints

container/layout

radius

border

shadow/elevation

motion duration
motion easing

z-index

image/media ratios if already present

component tokens if already present
```

Do not delete an existing family simply because Phase 12.1 did not discuss it.

---

# 35. Classify Tokens Into Layers

Reconcile into three conceptual layers where possible:

```text
1. Primitive
2. Semantic
3. Component-specific
```

Example:

```text
Primitive:
color.neutral.950

Semantic:
text.primary

Component:
button.primary.background
```

Do not force this exact naming if the current design system already has an established convention.

Preserve current conventions unless they are inconsistent.

---

# 36. Primitive Tokens Should Remain Brand-Agnostic

Primitive tokens describe available values, not business meaning.

Examples conceptually:

```text
color.neutral.*
color.warm.*
color.brand.*
space.*
radius.*
shadow.*
duration.*
easing.*
```

Avoid primitives such as:

```text
color.productCardBackground
color.checkoutButtonBrown
color.livingRoomHero
```

Those belong to semantic/component layers.

---

# 37. Semantic Tokens Carry Furniture Meaning

Establish/reconcile semantic roles such as:

```text
surface.canvas
surface.paper
surface.editorial
surface.inverse

text.primary
text.secondary
text.muted
text.inverse

border.subtle
border.default
border.strong

action.primary
action.primary.hover
action.primary.disabled
action.secondary
action.focus

accent.brand
accent.material
```

Use the current repository naming scheme rather than copying these names blindly.

The objective is semantic separation, not exact naming.

---

# 38. Logo-Derived Palette Reconciliation

Compare existing tokens against the official logo reference.

Brand observations:

```text
warm ivory ≈ #FCF4ED
deep brown ≈ #321E0F
```

Nike-derived anchors:

```text
charcoal #111111
white #FFFFFF
```

Reconcile rather than simply append all four everywhere.

A reasonable conceptual system is:

```text
deep charcoal
→ strongest UI neutral / primary action

pure white
→ pure paper/product surface

warm ivory
→ brand canvas/editorial background

deep brown
→ brand/material accent or brand-emphasis foreground
```

But inspect current tokens first.

If close equivalents already exist:

```text
reuse or normalize them.
```

Do not create duplicate shades unnecessarily.

---

# 39. Preserve Charcoal

Do NOT replace every:

```text
#111111
```

with brown.

Charcoal remains useful for:

```text
primary text
primary CTA
inverse surface
high-contrast iconography
```

The furniture transformation should come largely through:

```text
warm surrounding surfaces
serif typography
photography
space
```

not wholesale replacement of dark neutrals.

---

# 40. Preserve Pure White

Do NOT remove:

```text
#FFFFFF
```

from the system.

Pure white is appropriate for:

```text
product presentation
paper surfaces
forms
clean visual relief
```

The default page canvas does not necessarily have to be pure white.

---

# 41. Brand Brown Must Remain Controlled

If the logo brown becomes a formal token:

```text
deep brown ≈ #321E0F
```

give it a semantic purpose.

Do not let agents freely use it for any decorative element.

Potential allowed roles:

```text
brand emphasis
select editorial text
material accent
subtle decorative detail
```

Primary interactive action may continue to use charcoal.

---

# 42. Warm Canvas

If Phase 12.2 approves the logo ivory or normalized equivalent as:

```text
surface.canvas
```

ensure contrast against:

```text
text.primary
text.secondary
interactive states
```

is validated.

Do not approve palette solely because it matches the logo.

Accessibility remains mandatory.

---

# 43. Functional Colors

Preserve or reconcile functional semantic colors for:

```text
success
warning
error
information
focus
```

Do not recolor functional status semantics brown for branding.

Functional meaning wins.

Do not rely on color alone.

---

# 44. Product Status Colors

Do NOT create raw product-status colors directly in card implementations later.

If status treatment needs tokens, use semantic roles.

Potential future status meanings:

```text
available
low stock
made to order
unavailable
```

But avoid adding unnecessary status color tokens during 12.2 unless existing design-system architecture already supports them.

Initial MADE_TO_ORDER should not be styled like an error/warning state.

---

# 45. Typography Tokens

Reconcile typography into clearly named semantic roles.

At minimum the token model must distinguish:

```text
display
heading
body
label
utility
```

Do not create arbitrary sizes specifically for individual pages.

Keep the existing Nike-derived type scale where coherent.

Adapt family/weight/leading rather than inventing a totally new scale.

---

# 46. Young Serif Token Role

Create/reconcile an explicit token for the brand/display family.

Conceptually:

```text
font.family.display = Young Serif
```

Use the repository's actual naming scheme.

Do not yet load Google Fonts inside the web application.

Token phase only.

---

# 47. Utility Font Token

Inspect the existing system.

If an approved utility sans already exists, preserve it:

```text
font.family.ui
```

or current equivalent.

Do not choose a replacement without evidence that the existing UI font is unsuitable.

Document the distinction:

```text
display family
UI family
```

---

# 48. Do Not Use Young Serif for Everything by Token Accident

Do not set the root token so that:

```text
all buttons
all forms
all filters
all price values
all admin tables
```

automatically become Young Serif unless the existing design contract explicitly intends that.

The token model should permit:

```text
display serif
+
UI sans
```

without local overrides.

---

# 49. Typography Scale Discipline

Do not introduce arbitrary values such as:

```text
37px
19px
43px
13.5px
```

for isolated use cases.

Every supported text style must map to the canonical type scale.

Remove or reconcile unjustified duplicates.

Do not break existing well-structured scale just to make it different from Nike.

---

# 50. Spacing Tokens

Preserve the established spacing scale if coherent.

Do NOT create:

```text
homepageHeroPadding
productGridMagicGap
footerSpecialSpace
```

as primitive tokens.

Use semantic/layout aliases only when a repeated architectural concept justifies them.

---

# 51. Section Spacing

If the current system does not yet define semantic section spacing and repeated preview/layout evidence justifies it, consider semantic roles such as:

```text
section.compact
section.default
section.spacious
section.editorial
```

mapped to existing spacing primitives.

Do not create new raw measurements if existing primitives suffice.

---

# 52. Container Tokens

Inspect whether canonical container tokens already exist.

The design system should ultimately support:

```text
content max width
mobile gutters
tablet gutters
desktop gutters
wide editorial/media width
```

using existing scales.

Do not implement `PageContainer` yet.

Do not hard-code future MUI breakpoint logic during 12.2.

Document token intent.

---

# 53. Breakpoints

Preserve current breakpoint values unless there is a documented usability defect.

Do NOT change breakpoints simply because MUI has defaults.

Later MUI must adapt to the design system, not the reverse.

If current Nike-derived breakpoints are coherent:

```text
keep them authoritative.
```

---

# 54. Radius Tokens

Inventory and normalize the radius scale.

Avoid proliferation.

The system should not require:

```text
2px
3px
4px
5px
6px
7px
8px
10px
12px
14px
16px
```

without purpose.

Preserve current scale if already disciplined.

Semantic aliases can later determine component use.

---

# 55. Shadows

Inventory all shadows.

Remove/reconcile accidental duplicates only with evidence.

Furniture design should use shadows sparingly.

Do not remove required elevation states for:

```text
dialogs
drawers
menus
popovers
```

just to make the brand flat.

---

# 56. Motion Tokens

Preserve:

```text
duration
easing
```

scales where coherent.

Do not invent component-local:

```text
transition: 237ms cubic-bezier(...)
```

later.

Phase 12.2 should ensure there is a reusable motion vocabulary.

---

# 57. Focus Tokens

Ensure the design system has a clear accessible focus treatment.

If current Nike tokens already define it:

```text
preserve it.
```

If not, define semantic focus values based on existing primitives.

A focus state must remain visible against:

```text
warm canvas
white paper
dark inverse surfaces
```

Do not remove focus outlines for aesthetics.

---

# 58. Media / Image Ratio Tokens

Inspect current system.

Furniture benefits from canonical image proportions.

If no ratios exist and the current designs show repeated patterns, Phase 12.2 may add semantic media-ratio tokens such as conceptually:

```text
media.productCard
media.productHero
media.category
media.editorial
```

Do NOT guess ratios without inspecting:

```text
homepage.png
homepage2.png
productcards.png
heroimage.png
Desktop-Top-Banner.jpeg
mobile designs
```

Derive them from existing design intent.

Do not implement image components yet.

---

# 59. Logo Tokens vs Logo Asset

Do NOT encode the logo wordmark into CSS/text tokens.

Do not replace the logo PNG with:

```text
"SL FURNITURES"
```

rendered in a browser font.

Brand logo and site typography are separate.

The official logo remains an asset.

---

# 60. Token Portability

`design-tokens.json` must remain framework-neutral.

Do not put MUI-specific objects such as:

```text
palette.primary.main
components.MuiButton
sx
```

into the canonical shared token source.

Those mappings belong in Phase 12.3.

Likewise do not add Flutter classes to the canonical token document.

---

# 61. CSS Token Output

`tokens.css` should expose canonical CSS variables generated/reconciled according to the repository's existing convention.

Avoid manually introducing aliases that do not exist in `design-tokens.json` or source authority.

No silent divergence.

---

# 62. Tailwind Output

The project uses MUI for the website.

Do NOT expand Tailwind usage into the application merely because:

```text
tailwind-v4.css
```

exists in the design-system export.

Treat it as a design-system representation/output unless the project explicitly uses Tailwind elsewhere.

Do not install Tailwind in `frontend/web` during this phase.

---

# 63. MUI Boundary

Phase 12.2 must prepare for MUI but not implement it.

Do not yet create:

```text
theme.ts
createTheme()
MuiButton overrides
MuiTypography variants
ThemeProvider
CssBaseline customization
```

Those belong to Phase 12.3.

The output of 12.2 should make 12.3 mechanical.

---

# 64. Flutter Boundary

Do not implement Flutter theme code.

Ensure token names/meaning can later be mapped to:

```text
ColorScheme
TextTheme
ThemeData
```

without requiring different brand semantics.

---

# 65. Component Tokens

Do not prematurely define hundreds of component tokens.

A component-specific token is justified only when:

```text
the component has repeated, stable semantic behavior
that cannot be cleanly expressed by global semantic tokens.
```

Avoid:

```text
card.product.title.color
card.product.title.hover.color
card.product.title.mobile.color
```

before the component has even been designed.

---

# 66. Preserve Existing Furniture Adaptation

Because the Nike design system has already been converted away from apparel terminology:

do NOT reintroduce:

```text
shoe
sneaker
apparel
menswear
sportswear
training
athlete
jersey
clothing size
```

into:

```text
DESIGN.md
tokens
USAGE.md
previews
component manifests
```

unless present only as historical attribution clearly marked as such.

Use furniture terminology.

---

# 67. Furniture Terminology

Where examples are needed, prefer:

```text
chair
sofa
table
bed
cabinet
lighting
living room
bedroom
dining
office
made-to-order furniture
material
finish
dimensions
```

Do not encode those examples into primitive token names.

---

# 68. Token Naming Quality

Token names must describe:

```text
meaning
role
hierarchy
```

not appearance alone when semantic.

Prefer conceptually:

```text
text.primary
surface.canvas
action.primary
```

over:

```text
darkBrownText
creamBackground
blackButton
```

Primitive palettes may legitimately use scale/color names.

Semantic layer should not.

---

# 69. No Ad-Hoc Values Rule

Strengthen:

```text
frontend/design-system/USAGE.md
frontend/AGENTS.md
```

to explicitly prohibit application components from inventing:

```text
hex colors
rgb/hsl colors
font sizes
font families
line heights
letter spacing
spacing
radii
shadows
breakpoints
transition durations
easing curves
z-index
```

when an applicable approved token exists.

---

# 70. Escape Hatch

Do not make the token rule impossible to work with.

Define a strict exception process:

```text
If no existing token can correctly express a required design:

1. do not hard-code locally;
2. determine whether requirement is genuinely reusable;
3. propose/update canonical token;
4. update source + generated representations;
5. document purpose;
6. only then consume it.
```

This is essential for preventing token bypass.

---

# 71. Anti-AI-Slop Enforcement

Add explicit guidance for future agents.

Prohibit automatic introduction of:

```text
gradients without design authority
glassmorphism
blurred translucent cards
decorative blobs
random accent colors
random rounded cards
pills everywhere
overuse of shadows
oversized icon tiles
generic feature-card grids
dashboard-style homepage sections
unapproved emojis
unapproved illustrations
random type scales
random section spacing
one-off CTA variants
```

Agents must derive visual decisions from the canonical design system.

---

# 72. Preview Reconciliation

If the design system already has a supported preview-generation process, update/regenerate preview artifacts to demonstrate:

```text
palette
typography
spacing
semantic surfaces
```

after reconciliation.

Do not hand-edit generated previews if a source generator exists.

If previews are hand-maintained by design, follow existing process.

---

# 73. Visual Comparison

Review resulting previews against:

```text
brandlogo.png
homepage mockups
product cards
hero artwork
mobile concepts
```

Look for:

```text
brand mismatch
excessive whiteness
excessive brown
low contrast
sporty typography residue
generic SaaS appearance
too many token variants
```

Do not redesign the mockups.

Use them as evidence.

---

# 74. Validation Report

If the current:

```text
frontend/design-system/source/
```

contains token validation or contract reports, update/regenerate them using the existing workflow.

Verify at minimum:

```text
valid JSON
no unresolved token references
no duplicate semantic names
CSS variables generated consistently
all documented required families present
manifest references valid
```

Do not invent an unrelated validation framework if one already exists.

---

# 75. Cross-Artifact Consistency

The following must agree:

```text
DESIGN.md
design-tokens.json
tokens.css
tailwind-v4.css where applicable
USAGE.md
manifest.json
components manifest where affected
source validation
preview artifacts
```

No stale Nike values should survive accidentally in one representation while another uses the furniture system.

---

# 76. Do Not Touch Business/API Contracts

Phases 12.1/12.2 must not modify:

```text
backend/*
docs/api/*
Laravel behavior
API schemas
database
auth
RBAC
commerce state
```

Design-system work must not alter backend contracts.

---

# 77. Do Not Build `frontend/web` Yet

Unless the existing design-system tooling itself requires a narrowly scoped reference, do not modify the actual Next.js application in these two phases.

Specifically do not:

```text
build homepage
build product cards
add ThemeProvider
load Google Font
implement navigation
create checkout
wire APIs
implement Clerk
```

Those follow later.

---

# 78. Phase 12.2 Deliverables

Expected modified artifacts may include:

```text
frontend/design-system/DESIGN.md
frontend/design-system/design-tokens.json
frontend/design-system/tokens.css
frontend/design-system/USAGE.md
frontend/design-system/source/*
frontend/design-system/preview/*
frontend/design-system/manifest.json
frontend/design-system/tailwind-v4.css
```

ONLY as justified by current source/generation architecture.

Also:

```text
frontend/AGENTS.md
phases/group-L-phases.md
docs/decisions.md
```

where needed.

Do not touch every file merely to show activity.

---

# 79. Required Documentation of Token Authority

By completion, `USAGE.md` or another canonical location must state explicitly:

```text
what file is the source of truth

what artifacts are generated

what artifacts consumers should import/read

how a token change is made

how framework mapping works

what ad-hoc values are prohibited
```

An agent should not have to guess.

---

# 80. Required Token Map

Produce a concise reconciliation map showing:

```text
existing Nike-derived role
→ final furniture semantic role
→ raw primitive
→ rationale
```

For important tokens only.

Example structure:

```text
Nike/current role:
primary foreground

Furniture semantic role:
text.primary

Primitive:
neutral.950

Decision:
preserved
```

and:

```text
Nike/current role:
white page background

Furniture semantic role:
surface.paper

New/default canvas:
surface.canvas → approved warm-neutral primitive

Decision:
white retained, but no longer necessarily the universal canvas
```

Do not create a huge table for every spacing integer.

---

# 81. Required Brand Palette Report

Document final approved semantic palette after token inspection.

It should make clear:

```text
primary text
secondary text
muted text
canvas surface
paper/product surface
editorial surface
inverse surface
primary action
secondary action
brand/material accent
border levels
functional states
focus
```

Do not approve palette values by intuition alone; reconcile against existing primitives and accessibility.

---

# 82. Accessibility Validation

Verify foreground/background combinations intended for text/actions.

At minimum test:

```text
text.primary on surface.canvas
text.primary on surface.paper
text.secondary on surface.canvas
primary CTA text/background
secondary CTA
inverse text/surface
focus indicators
functional states
```

Use WCAG contrast targets appropriate to text/control usage.

Do not weaken contrast for visual warmth.

---

# 83. Typography Validation

Verify:

```text
Young Serif supports required Latin brand copy
display scale remains readable
line-height avoids clipping
utility UI remains legible
mobile headings wrap reasonably
```

Do not distort Young Serif with arbitrary letter spacing.

Do not use fake bold/italic styles if the font/source does not support them appropriately.

---

# 84. Brand Logo Contrast

Verify official logo presentation against any approved surrounding surface tokens.

Do not assume the logo's built-in background should be transparently removed.

Do not alter the asset.

Document safe usage only if existing design-system docs include logo guidance.

---

# 85. Phase 12.1 Tests / Verification

Phase 12.1 is primarily documentary/design-contract work.

Verification should include:

```text
all relevant design sources inspected
brand goals explicitly documented
logo recognized as authority
Nike structural principles preserved
Young Serif role documented
anti-pattern list documented
accessibility included
future MUI/Flutter boundary preserved
```

---

# 86. Phase 12.2 Tests / Verification

Run all existing design-system validation commands.

Inspect:

```text
package scripts
source validation scripts
manifest tooling
```

before inventing commands.

At minimum verify:

```text
design-tokens.json parses
manifest files parse
CSS syntax is valid
token references resolve
generated outputs are synchronized
no obvious duplicate semantic tokens
no accidental apparel references
```

If the repository has no automated validation for some artifact, perform a narrow deterministic validation using existing tooling or a small project-local script only if appropriate.

Do not add a large new dependency for token validation.

---

# 87. Apparel Reference Audit

Search the canonical design-system directory for stale Nike apparel terminology.

Examples:

```text
shoe
shoes
sneaker
sneakers
apparel
clothes
clothing
sportswear
athlete
running shoe
jersey
```

Do not blindly delete references if they are legitimate provenance notes.

Remove/rewrite only active UI/design guidance that still treats this as an apparel site.

---

# 88. Raw-Value Audit

Search design-system semantic/component documentation for uncontrolled:

```text
hex
rgb
px
rem
shadow definitions
transition values
```

Raw values are expected in primitive token definitions.

They should not be scattered through semantic examples/components when a token should be used.

Document any intentional exceptions.

---

# 89. No MUI Defaults as Design Authority

Do NOT change canonical tokens to match MUI defaults merely because doing so makes implementation easier.

The dependency direction is:

```text
design system
→ MUI
```

not:

```text
MUI defaults
→ design system
```

Phase 12.3 will map MUI to the approved tokens.

---

# 90. No Flutter Defaults as Design Authority

Likewise:

```text
design system
→ Flutter Material 3
```

not:

```text
Material 3 defaults
→ canonical brand tokens
```

Flutter may adapt implementation details later while preserving shared semantics.

---

# 91. ADR Requirement

Add a repository-consistent ADR capturing the important decision:

```text
The Nike-derived furniture system is retained as structural design authority.

Furniture branding is achieved through semantic palette,
Young Serif display typography, warm neutral surfaces,
photography and material-led editorial presentation.

#111111 and #FFFFFF remain valid primitives/anchors.

The official SL Furnitures logo is the brand identity authority.

Canonical shared tokens are framework-neutral.

MUI and Flutter consume mappings rather than becoming token authorities.

Application components may not invent ad-hoc design values
when an approved token exists.
```

Do not call the brand "Nike" in consumer-facing UI.

Historical design provenance may be documented internally.

---

# 92. Group L Phase File

Create/update:

```text
phases/group-L-phases.md
```

Document at minimum:

```text
12.1 — Furniture brand/design goals
12.2 — Token authority and reconciliation
12.3 — MUI theme implementation
12.4 — Global CSS/font loading
12.5 — Layout primitives
12.6 — UI primitives
12.7 — Commerce primitives
12.8 — Navigation/header/footer
12.9 — Catalog components
12.10 — Product detail components
12.11 — Request/enquiry components
12.12 — Pages
12.13 — Responsive/accessibility validation
12.14 — Visual consistency / anti-AI-slop audit
```

Only mark:

```text
12.1
12.2
```

according to actual execution.

Do not start later phases.

---

# 93. Git Workflow

Before performing Git operations, locate and read the root:

```text
git-workflow-and-versioning
```

skill.

Git operations are authorized only through that skill.

Preserve unrelated owner work.

Stage only Phase 12.1/12.2 files.

Follow its required:

```text
status checks
branching
commit format
versioning
push rules
```

Do not commit generated or secret artifacts contrary to repository policy.

---

# 94. Completion Report

Return:

```text
Phase 12.1 status:
PASS / BLOCKED

Brand authority:
PASS / FAIL

Official logo documented:
YES / NO

Nike structural principles preserved:
YES / NO

Furniture brand personality documented:
YES / NO

Young Serif approved as display family:
YES / NO

Utility/UI typography boundary:
PASS / FAIL

Color philosophy:
PASS / FAIL

Photography principles:
PASS / FAIL

Accessibility principles:
PASS / FAIL

Anti-AI-slop rules:
PASS / FAIL


Phase 12.2 status:
PASS / BLOCKED

Canonical token source identified:
YES / NO

Generated artifacts identified:
YES / NO

Primitive token layer:
PASS / FAIL

Semantic token layer:
PASS / FAIL

Framework-neutral tokens:
PASS / FAIL

Logo colors reconciled:
PASS / FAIL

#111111 retained:
YES / NO

#FFFFFF retained:
YES / NO

Warm canvas semantic:
<token + value>

Brand/material accent:
<token + value>

Display font token:
<token + Young Serif>

Utility font token:
<token + value>

Spacing scale:
PRESERVED / CHANGED

Breakpoints:
PRESERVED / CHANGED

Radius scale:
PRESERVED / CHANGED

Elevation:
PRESERVED / RECONCILED

Motion:
PRESERVED / RECONCILED

Focus treatment:
PASS / FAIL

Media ratios:
<PRESERVED / ADDED / NOT NEEDED>

Token duplication review:
PASS / FAIL

Apparel terminology audit:
PASS / FAIL

Raw/ad-hoc value policy:
PASS / FAIL

DESIGN.md:
PASS / FAIL

USAGE.md:
PASS / FAIL

design-tokens.json:
PASS / FAIL

tokens.css:
PASS / FAIL

tailwind-v4.css:
PASS / NOT APPLICABLE / FAIL

manifest/source validation:
PASS / FAIL

preview validation:
PASS / FAIL

Accessibility contrast checks:
PASS / FAIL

MUI implementation started:
NO

Frontend pages/components started:
NO

Backend/API changed:
NO

Dependencies added:
NONE / <explain>

Files changed:
<list>

Design-system validation:
<commands + results>

Git workflow skill read:
YES / NO

Git operations performed:
<list>

Commit:
<hash/message or NONE>

Push:
<result or NONE>

Phase 12.3:
READY / BLOCKED
```

---

# 95. Final STOP Condition

Phases 12.1 and 12.2 are complete only when:

- SL Furnitures has an explicit furniture-oriented brand language;
- the official logo is recognized as brand authority;
- Nike's useful structural design discipline is preserved;
- active apparel-specific language is removed;
- Young Serif has a precise semantic typography role;
- UI typography remains usable;
- warm furniture surfaces are introduced semantically rather than ad hoc;
- `#111111` and `#FFFFFF` remain available where appropriate;
- logo-derived brown/ivory are reconciled rather than blindly scattered;
- photography is established as a primary source of visual color;
- primitive, semantic and component-token responsibilities are clear;
- exactly one token authority/generation direction is documented;
- design-token artifacts agree;
- framework-neutral tokens remain separate from MUI/Flutter mapping;
- no page/component implementation has started;
- no MUI theme has started;
- accessibility contrast is verified;
- ad-hoc values and AI-slop patterns are explicitly prohibited;
- validation passes;
- Phase 12.3 can consume the result without needing to reinterpret brand intent.

Then report:

```text
Phase 12.1 — PASS
Phase 12.2 — PASS

Phase 12.3 — READY
```

Do not begin Phase 12.3 automatically.

---

## Execution Record — 2026-10-06

### 12.1 — Furniture Brand / Design Goals

**Status:** Complete

`frontend/design-system/DESIGN.md` now records SL Furnitures as an architectural, warm, editorial furniture brand. It recognizes `designs/brandlogo.png` as authoritative, preserves the prior system's structural discipline, assigns Young Serif to display/editorial roles, retains the utility sans boundary, and documents photography, accessibility, responsive, and anti-pattern rules.

### 12.2 — Token Authority and Reconciliation

**Status:** Complete

`frontend/design-system/tokens.css` is the sole canonical token authority. `design-tokens.json` and `tailwind-v4.css` are synchronized portable representations. The reconciled semantic palette establishes `--surface-canvas` as `#FCF4ED`, `--accent-brand` as `#321E0F`, retains charcoal and white anchors, preserves the existing spacing, breakpoint, radius, elevation, and motion scales, and adds no MUI, Flutter, page, or component implementation.

### Remaining Group L Phases

- 12.3 — MUI theme implementation: Not started
- 12.4 — Global CSS/font loading: Not started
- 12.5 — Layout primitives: Not started
- 12.6 — UI primitives: Not started
- 12.7 — Commerce primitives: Not started
- 12.8 — Navigation/header/footer: Not started
- 12.9 — Catalog components: Not started
- 12.10 — Product-detail components: Not started
- 12.11 — Request/enquiry components: Not started
- 12.12 — Pages: Not started
- 12.13 — Responsive/accessibility validation: Not started
- 12.14 — Visual consistency / anti-AI-slop audit: Not started

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**
