# Phase 12.6 — Component Conventions

## Objective

Define and validate the **shared component conventions** for SL Furnitures.

Phases 12.1–12.5 established:

```text
brand
→ token authority
→ framework mappings
→ visual foundations
```

Phase 12.6 establishes:

```text
visual foundations
        ↓
component construction rules
        ↓
future MUI components
future Flutter widgets
```

The goal is to make later component implementation predictable and disciplined.

A future agent should be able to answer:

```text
When should I create a component?

Which component owns spacing?

Which component variants are permitted?

How should buttons differ?

How should forms behave visually?

What belongs in a card?

When should an icon appear?

How are loading/empty/error states represented?

What may MUI/Material defaults control?

What must come from design tokens?

When may a new variant/token be introduced?
```

without inventing a new local design language.

This phase defines conventions.

It does **not** implement the application's reusable component library.

---

# 1. Active Scope

Implement only:

```text
Phase 12.6 — Component Conventions
```

Define conventions for:

```text
component architecture
component ownership
variants
composition
buttons/actions
links
icons
forms
cards/surfaces
media
badges/status
navigation controls
feedback
loading
empty states
errors
dialogs/overlays
responsive component behavior
MUI usage
future Flutter Material usage
token enforcement
```

Do NOT begin application component implementation.

---

# 2. Do Not Pull Later Work Forward

Do NOT build:

```text
Button wrapper
Link wrapper
TextField wrapper
ProductCard
CategoryCard
RequestCard
StatusBadge
Header
Footer
Navbar
ProductGrid
SearchBar
FilterDrawer
RequestForm
EnquiryForm
Dialog system
Toast system
Flutter widgets
```

Those belong to later framework/application phases.

Phase 12.6 defines how such components must eventually be constructed.

---

# 3. Read Authorities First

Before editing, inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── tokens.css
├── design-tokens.json
├── components.html
├── components.manifest.json
├── flutter-material3.md
├── manifest.json
├── preview/
└── source/

frontend/web/
└── existing Phase 12.3 MUI theme

phases/group-L-phases.md
docs/decisions.md
```

Read the Phase 12.5 execution record before changing component guidance.

Do not reconstruct previous decisions from memory.

---

# 4. Preserve Existing Authorities

The authority chain remains:

```text
DESIGN.md
    ↓
tokens.css
    ↓
USAGE.md
    ↓
framework mapping
    ↓
component conventions
    ↓
application components
```

Component conventions cannot override tokens.

They explain how tokens are composed into UI.

---

# 5. Git Workflow

Before any Git operation, locate and read the root:

```text
git-workflow-and-versioning
```

skill.

Git operations are authorized only through that skill.

Preserve unrelated owner changes.

Never commit:

```text
.env
credentials
secrets
font binaries
local configuration
```

Stage only Phase 12.6 work.

---

# 6. Existing Component Catalog

Inspect:

```text
frontend/design-system/components.html
frontend/design-system/components.manifest.json
```

Determine:

```text
which components already have visual examples
which examples remain relevant
which terminology is legacy Nike/apparel terminology
which examples are furniture-oriented
which examples contradict Phase 12.5
```

Do not discard the existing catalog simply to replace it.

Reconcile it.

---

# 7. Component Catalog Is Reference, Not Runtime

Clarify that:

```text
components.html
components.manifest.json
```

are design-system reference artifacts.

They are NOT:

```text
React component source
Flutter widget source
runtime component registry
API schema
```

Future framework components consume the same conventions independently.

---

# 8. Shared Semantics, Independent Implementations

Preserve:

```text
Web
→ MUI / React components

Flutter
→ Material 3 widgets
```

Do not attempt to create a cross-framework runtime component package.

Shared:

```text
semantics
visual hierarchy
state vocabulary
token meaning
interaction intent
```

Framework-specific:

```text
implementation
layout APIs
event APIs
accessibility APIs
navigation behavior
platform adaptation
```

---

# 9. Component Hierarchy

Define a clear conceptual hierarchy.

Recommended:

```text
Foundation
    ↓
Primitive
    ↓
Composite
    ↓
Commerce/domain component
    ↓
Page composition
```

Examples:

```text
Foundation
→ tokens / typography / color / spacing

Primitive
→ Button / Input / Link / Surface

Composite
→ SearchField / QuantitySelector

Commerce
→ ProductCard / PriceDisplay / RequestCTA

Page composition
→ ProductGrid / ProductDetail composition
```

Do not implement these now.

The purpose is ownership clarity.

---

# 10. Primitive Rule

A primitive should exist only when it provides meaningful shared behavior or design-system enforcement.

Do NOT create wrappers such as:

```tsx
<AppBox>
<AppStack>
<AppTypography>
<AppIcon>
```

merely to rename MUI.

Similarly, future Flutter must not wrap every:

```text
Container
Row
Column
Text
Icon
```

without meaningful value.

---

# 11. Prefer Framework Primitives

Future web implementation should prefer MUI directly where MUI already expresses the required semantics correctly.

Example:

```text
MUI Button
+ theme defaults
+ approved variant
```

is preferable to:

```text
CustomButton
→ MyButton
→ BrandButton
→ MuiButton
```

without additional behavior.

Avoid abstraction for abstraction's sake.

---

# 12. Wrapper Justification

A shared wrapper is justified when it centralizes one or more of:

```text
accessibility behavior
repeated semantic variant
loading behavior
routing integration
analytics integration
domain behavior
complex responsive behavior
repeated state logic
```

Styling alone is not automatically sufficient if the MUI theme already handles it.

---

# 13. Composition Over Giant Components

Prefer:

```text
small focused components
+
composition
```

over components with dozens of switches.

Avoid future APIs such as:

```tsx
<ProductCard
  horizontal
  compact
  hero
  featured
  mobile
  dark
  editorial
  rounded
  elevated
  showDescription
  showBadge
  ...
/>
```

If visually/semantically distinct compositions emerge, model them deliberately.

---

# 14. Variant Budget

Component variants must be intentionally limited.

Before adding a variant ask:

```text
Does this represent a reusable semantic distinction?
```

Good:

```text
primary
secondary
quiet
danger
```

where supported by actual component semantics.

Bad:

```text
brown
cream
homepage
productPage
big
smallBrown
special
v2
```

Variants express purpose, not page-specific appearance.

---

# 15. Size Budget

Do not automatically create:

```text
xs
sm
md
lg
xl
xxl
```

for every component.

Only expose sizes that have genuine design-system meaning.

Use canonical spacing and typography internally.

---

# 16. Raw Style Props

Future shared components should not expose uncontrolled styling APIs such as:

```tsx
backgroundColor
borderRadius
fontSize
shadow
padding
```

as ordinary product-level props.

That allows callers to bypass the design system.

Prefer semantic variants.

---

# 17. MUI `sx` Policy

Do NOT ban `sx`.

It is a legitimate MUI composition mechanism.

However:

```text
sx
```

must not become a route around the design system.

Allowed:

```text
layout composition
responsive arrangement
token-based contextual spacing
one-off structural positioning
```

Discouraged/prohibited:

```text
new raw brand colors
random radius
random shadow
new font sizes
new component variants
duplicated reusable component styling
```

Document this distinction.

---

# 18. Flutter Local Styling Policy

Apply the same principle to future Flutter code.

Local:

```dart
Padding
SizedBox
Align
Flex
```

composition is normal.

But do not scatter:

```dart
Color(0xFF...)
TextStyle(...)
BorderRadius.circular(...)
BoxShadow(...)
```

with ad-hoc brand values throughout widgets.

Shared visual decisions come from the theme/token adapter.

---

# 19. Button Taxonomy

Define the semantic action hierarchy.

At minimum reconcile:

```text
Primary
Secondary
Text / quiet
Destructive
Icon-only
```

against existing design artifacts.

Do not automatically create every possible MUI button variant.

---

# 20. Primary Button

Primary action should represent:

```text
the strongest action in the current decision context
```

Visual foundation:

```text
charcoal action surface
high-contrast foreground
utility typography
approved control radius
visible focus
appropriate minimum target
quiet motion
```

Do not use brown as the universal primary CTA.

---

# 21. One Dominant Primary Action

As a general composition rule:

```text
one visually dominant primary action per immediate decision group
```

Avoid screens filled with equally dominant black buttons.

This is a hierarchy principle, not an absolute count per page.

---

# 22. Secondary Action

Secondary actions should remain clearly actionable without competing with the primary action.

Use approved:

```text
border
surface
text
```

semantics.

Do not invent decorative brown buttons as a secondary system unless already approved.

---

# 23. Quiet/Text Action

Use quiet actions for lower-emphasis operations such as:

```text
view details
cancel
learn more
secondary navigation
```

where context supports them.

Do not use low-emphasis styling for destructive or critical actions.

---

# 24. Destructive Action

Destructive semantics must use the approved functional error/destructive language.

Never use:

```text
brand brown
warning amber
primary charcoal alone
```

to disguise a destructive action.

Require clear textual meaning.

---

# 25. Icon-Only Buttons

Icon-only controls must have:

```text
accessible name
adequate target size
visible focus
clear hover/pressed state
```

Tooltips may supplement but must not be the sole accessible name.

---

# 26. Material UI Icons

Web icon source remains:

```text
@mui/icons-material
```

Do not introduce:

```text
Lucide
Heroicons
React Icons
Font Awesome
emoji icons
random SVG icon libraries
```

---

# 27. Flutter Icons

Future Flutter uses:

```text
Material Icons
```

by default.

Aim for semantic parity, not necessarily byte-identical glyphs.

---

# 28. Icon Restraint

Icons are functional aids.

Do not add an icon to:

```text
every heading
every card
every section title
every CTA
```

simply to make UI appear designed.

Text-only controls are valid.

---

# 29. Icon + Text

Where icon and text are combined:

```text
icon supports the label
label remains understandable
```

Do not use ambiguous glyphs to replace clear language.

---

# 30. Links vs Buttons

Formalize:

```text
Link
→ navigation

Button
→ action
```

Do not style navigation anchors as buttons indiscriminately.

Do not use buttons to perform ordinary navigation when semantic links are appropriate.

Framework implementation must preserve correct HTML semantics on web.

---

# 31. Inline Links

Inline links must remain recognizable through more than subtle color difference where necessary.

Do not rely on brand brown alone against body text if affordance becomes unclear.

Use approved decoration/state treatment.

---

# 32. Form Control Taxonomy

Define shared expectations for:

```text
text input
textarea
select
checkbox
radio
switch
search
file input
```

Do not implement them.

---

# 33. Labels

Form controls require persistent accessible labels where appropriate.

Do not use placeholder-only forms.

Placeholder text is supplementary guidance, not a substitute for a label.

---

# 34. Helper Text

Helper text should explain:

```text
format
constraint
context
```

when useful.

Do not fill every input with unnecessary explanatory text.

---

# 35. Error Messages

Validation errors should:

```text
identify the affected field
explain what needs correction
remain associated with the field
not rely solely on red color
```

Do not use vague:

```text
Invalid value
Something went wrong
```

when a safe, actionable validation explanation is available.

---

# 36. Required Fields

Choose and document one consistent required/optional convention.

Do not mix:

```text
*
(required)
(optional)
nothing
```

randomly between forms.

Follow existing product/content requirements where already established.

---

# 37. Input Geometry

All related controls should share coherent:

```text
height
radius
border treatment
label typography
focus treatment
```

through framework theme/component conventions.

Do not invent per-form input styling.

---

# 38. Search

Search is an input behavior, not automatically a pill-shaped decorative component.

Its geometry must follow the shape system.

Do not default to oversized rounded search pills merely because many e-commerce sites do.

---

# 39. File Upload

Future request/enquiry attachment controls must clearly communicate:

```text
allowed file type
size constraint
selected file
remove/replace behavior
upload/error state
```

but must not expose backend implementation details.

Do not implement attachment UI during this phase.

---

# 40. Surface vs Card

Formalize an important distinction:

```text
not every grouped piece of content is a card.
```

Prefer:

```text
layout
whitespace
typography
dividers
surface changes
```

before adding card containers.

This is essential to the architectural/editorial furniture direction.

---

# 41. Card Rule

Use a card when content genuinely forms a:

```text
self-contained interactive/content unit
```

not merely because multiple elements appear near each other.

---

# 42. Card Appearance

Ordinary cards should not automatically have:

```text
rounded container
border
shadow
colored background
```

all at once.

Choose the minimum visual containment required.

---

# 43. Product Cards

Define only conventions, not implementation.

Future product cards should generally prioritize:

```text
large product imagery
product name
price / starting price where applicable
product type/status information where useful
clear navigation/action
```

Avoid:

```text
excess chrome
multiple badges
heavy shadows
decorative icons
large description blocks
```

unless the specific experience requires them.

---

# 44. MADE_TO_ORDER Presentation

The current production model gives MADE_TO_ORDER products first-class importance.

Therefore future components must not visually communicate:

```text
MADE_TO_ORDER = unavailable
```

or:

```text
MADE_TO_ORDER = disabled product
```

It is a valid purchasing/request pathway.

Its primary conversion may be:

```text
Request Furniture
```

rather than Add to Cart.

---

# 45. Product Price Semantics

Future component conventions must accommodate:

```text
base/display price
starting-at price
variant-specific price
```

without inventing pricing logic in the frontend.

The API remains authoritative.

Do not define commerce calculations in the design system.

---

# 46. Media

Furniture imagery should remain visually dominant.

Media conventions should favor:

```text
clean crop
consistent aspect treatment
minimal overlays
minimal decorative chrome
```

Do not place unnecessary text/buttons over product photography.

---

# 47. Media Shape

Preserve Phase 12.5:

```text
product media
→ generally sharp / architectural
```

Do not round product images automatically to match control radii.

---

# 48. Image Fallback

Define a future fallback principle:

```text
missing image
→ neutral branded placeholder/state
```

not:

```text
broken browser icon
random external placeholder
```

Do not create the asset yet unless one already exists in the design system.

---

# 49. Badges and Chips

Use badges/chips for compact semantic information.

Examples may include:

```text
status
stock state
product type
selected filter
```

Do not turn ordinary metadata into pills.

---

# 50. Badge Restraint

A product card should not accumulate many competing badges.

Define a hierarchy if multiple statuses exist.

Prefer the minimum information necessary for the decision context.

---

# 51. Status Semantics

Status appearance must derive from meaning.

Do not use:

```text
green = everything good
red = everything bad
brown = furniture
```

without semantic rules.

Status must not rely on color alone.

---

# 52. Navigation Controls

Define principles for:

```text
breadcrumbs
tabs
pagination
back controls
menus
drawers
bottom navigation
```

but do not implement them.

Navigation should remain:

```text
clear
quiet
predictable
accessible
```

---

# 53. Breadcrumbs

Future desktop/product hierarchy may use breadcrumbs.

They should:

```text
represent actual hierarchy
use links for navigable ancestors
identify current location
```

Do not use breadcrumbs as decorative metadata.

---

# 54. Pagination

Pagination controls should preserve semantic navigation and accessible current-page indication.

Do not use infinite scroll automatically.

Actual catalog pagination behavior belongs to later catalog implementation and API contract.

---

# 55. Feedback Taxonomy

Define conventions for:

```text
inline feedback
toast/snackbar
alert
dialog
page-level state
```

Choose the least disruptive mechanism appropriate to the message.

---

# 56. Snackbar / Toast

Use for transient, non-blocking confirmation.

Examples:

```text
saved
copied
request submitted
```

where appropriate.

Do not use snackbars for errors requiring user correction or critical decisions.

---

# 57. Inline Feedback

Use near the relevant content when the user can act locally.

Examples:

```text
field validation
attachment failure
quantity issue
```

---

# 58. Alerts

Use persistent alerts for information that remains relevant until understood/resolved.

Do not turn ordinary informational copy into colored alert boxes.

---

# 59. Dialogs

Dialogs interrupt workflow and should be reserved for:

```text
important confirmation
destructive action
focused short task
critical information
```

Do not place ordinary forms/content into modals merely to reduce page length.

---

# 60. Dialog Actions

Dialog actions must have clear hierarchy.

Destructive confirmation must explicitly identify the destructive operation.

Avoid ambiguous:

```text
Yes
No
OK
```

where:

```text
Delete
Cancel
```

is clearer.

---

# 61. Loading States

Define three categories:

```text
initial content loading
local/action loading
progressive content loading
```

Future components must use the least disruptive appropriate treatment.

---

# 62. Action Loading

When submitting an action:

```text
preserve context
prevent accidental duplicate submission where necessary
communicate progress
```

Do not replace the entire page with a spinner for a local button operation.

---

# 63. Skeletons

Skeletons may be used where they meaningfully preserve layout during content loading.

Do not:

```text
skeletonize every page
create flashy shimmer everywhere
```

Use them only where content shape is reasonably predictable.

---

# 64. Spinner Restraint

Do not use large centered spinners as the universal loading solution.

Loading representation should match scope.

---

# 65. Empty States

An empty state should answer:

```text
What is empty?
Why might it be empty?
What can I do next?
```

where those answers are useful.

Do not automatically add:

```text
illustration
giant icon
marketing headline
```

to every empty state.

---

# 66. Error States

Errors should distinguish conceptually:

```text
validation error
recoverable request error
permission/authentication state
not found
system/unavailable state
```

Do not expose backend internals or stack traces.

Actual error mapping belongs to Group M/API client work.

---

# 67. Retry

Offer retry only where repeating the operation is:

```text
safe
meaningful
supported
```

Do not add generic Retry buttons everywhere.

---

# 68. Responsive Components

A component may change composition at approved breakpoints.

Do not create separate:

```text
DesktopProductCard
TabletProductCard
MobileProductCard
```

solely because layout changes.

Prefer responsive composition where semantics remain the same.

---

# 69. Different Semantics May Justify Different Components

If mobile interaction genuinely differs—for example:

```text
desktop popover
vs
mobile bottom sheet
```

separate framework-specific composition may be appropriate.

Do not force pixel-identical behavior across platforms.

---

# 70. Component-Owned vs Parent-Owned Spacing

Define:

```text
component owns
→ internal spacing

parent/layout owns
→ external spacing
```

This is mandatory.

A reusable component should generally not impose arbitrary outer margins.

Example:

```text
Button
→ owns icon-label gap and internal padding

Page layout
→ owns distance between Button and neighboring section
```

This prevents unpredictable composition.

---

# 71. Width Ownership

Components should not default to arbitrary fixed widths.

Parent/layout determines available width unless component semantics require otherwise.

Examples where component may legitimately control width behavior:

```text
dialog
popover
tooltip
compact icon control
```

---

# 72. Height Ownership

Avoid fixed content heights where text/data can vary.

Fixed minimum control heights are acceptable for accessibility and consistency.

Do not truncate content solely to preserve decorative card alignment unless explicitly approved.

---

# 73. Content Before Decoration

Components must be designed around actual content semantics.

Do not construct empty visual shells and force data into them later.

This is especially important for:

```text
product names
prices
dimensions
material
MTO status
request summaries
enquiry information
```

---

# 74. Realistic Content Fixtures

Design-system previews should use furniture-oriented realistic fixture text.

Avoid:

```text
Lorem ipsum
Nike Air Max
Sneaker
T-shirt
Apparel
```

Use neutral fictional furniture content.

Do not introduce claims about real inventory or prices unless clearly fixture data.

---

# 75. Component State Matrix

For each important primitive category, define applicable states.

Typical interactive states:

```text
default
hover
focus-visible
active/pressed
disabled
loading
error where relevant
selected where relevant
```

Not every component requires every state.

Do not manufacture meaningless states.

---

# 76. Hover Is Web-Specific

Flutter/mobile does not need to imitate web hover behavior.

Shared semantic states:

```text
focus
pressed
disabled
selected
loading
```

should map appropriately to platform capabilities.

---

# 77. Focus-Visible

Web components should prefer keyboard-appropriate focus-visible behavior rather than showing/removing focus indiscriminately.

Do not suppress focus after pointer interactions in ways that harm accessibility.

---

# 78. Disabled vs Loading

Document:

```text
disabled
≠
loading
```

Loading indicates an operation in progress.

Disabled indicates action is currently unavailable.

Do not use disabled styling as the only loading indication.

---

# 79. Selected vs Active

Document:

```text
selected
→ persistent/current choice

active/pressed
→ transient interaction state
```

Do not conflate them.

---

# 80. Semantic HTML

Future web components must preserve semantic HTML.

Examples:

```text
navigation → nav
navigation action → a/link
action → button
heading → h1-h6 hierarchy
list → ul/ol where appropriate
form label → label
```

Do not build everything with:

```text
div
span
onClick
```

because MUI permits flexible primitives.

---

# 81. MUI Polymorphism

When using MUI's:

```text
component
```

or equivalent polymorphic APIs, preserve semantic behavior.

Visual appearance must not override correct HTML semantics.

---

# 82. Flutter Semantics

Future Flutter components must use appropriate:

```text
Semantics
Tooltip
button semantics
selected state
labels
```

where built-in Material widgets do not already provide enough information.

Do not duplicate semantics unnecessarily.

---

# 83. Component API Naming

Props should describe:

```text
meaning
state
behavior
```

rather than arbitrary appearance.

Prefer:

```text
loading
selected
status
emphasis
```

over:

```text
black
rounded
shadow
big
brown
```

---

# 84. Boolean Prop Explosion

Avoid components with many independent booleans.

If combinations can create invalid visual states, model the state with a closed semantic variant/type.

---

# 85. Closed Variant Types

Future TypeScript components should use closed unions where appropriate:

```ts
type Emphasis = 'primary' | 'secondary' | 'quiet';
```

rather than unrestricted:

```ts
string
```

for design-system variants.

Future Dart should use enums/sealed semantics where appropriate.

Do not implement these yet.

---

# 86. Escape Hatch Policy

When a component needs a visual treatment not represented by existing conventions:

```text
STOP
→ determine whether this is:
   1. local layout composition
   2. reusable component variant
   3. missing design token
   4. genuinely new design-system behavior
```

Then update the correct authority.

Do not patch locally with raw values.

---

# 87. No Page-Specific Design Tokens

Do not create:

```text
--homepage-card-radius
--product-page-brown
--checkout-gap
--request-form-shadow
```

unless the concept is genuinely reusable and semantic.

Pages consume the system.

They do not redefine it.

---

# 88. Component Documentation Structure

Update the design-system documentation so each component convention can eventually describe:

```text
Purpose
When to use
When not to use
Anatomy
Variants
Sizes
States
Token dependencies
Accessibility
Responsive behavior
Platform notes
```

Do not require every existing fixture to have a huge specification if it is not yet an implemented primitive.

Establish the format.

---

# 89. Component Manifest

Inspect:

```text
components.manifest.json
```

and determine its existing purpose/schema.

If it already represents the component catalog:

```text
preserve schema compatibility
```

unless Phase 12.6 exposes a genuine deficiency.

Do not casually redesign the manifest.

---

# 90. Manifest Must Not Become Application Registry

Do not store:

```text
React import paths
Flutter class names
API endpoints
route configuration
business logic
```

unless its existing documented purpose explicitly includes them.

Keep design metadata separate from runtime architecture.

---

# 91. Component Preview Reconciliation

Update:

```text
components.html
```

and/or existing preview artifacts only where necessary to demonstrate the finalized conventions.

Prioritize examples of:

```text
action hierarchy
links
form states
surface/card restraint
status/badge restraint
loading/empty/error language
focus
```

Do not create a complete storefront.

---

# 92. Preview Interactivity

If the existing static preview supports simple state demonstration, use it carefully.

Do not introduce a frontend framework just for design-system previews.

No new dependency is expected.

---

# 93. Anti-AI-Slop Component Rules

Explicitly prohibit future agents from automatically adding:

```text
icon in every heading
icon in every button
pill around every label
rounded card around every section
gradient CTA
glass panel
blur background
floating decorative blobs
huge shadow
animated card lift
random accent borders
oversized marketing copy everywhere
three CTAs of equal weight
unnecessary badge collections
```

The UI should feel deliberately composed rather than generated from generic SaaS/e-commerce patterns.

---

# 94. Furniture-Specific Direction

Components should reinforce:

```text
material
craft
space
proportion
photography
editorial hierarchy
```

rather than sportswear cues such as:

```text
speed
aggression
high-energy contrast everywhere
oversized promotional labels
dense badge systems
```

---

# 95. Admin UI Distinction

The shared foundations apply to both customer and future admin interfaces, but component composition may differ.

Admin UI should prioritize:

```text
clarity
density
operational efficiency
```

Customer storefront may prioritize:

```text
photography
space
editorial presentation
```

Do not create a second admin design system.

---

# 96. Commerce Boundary

Component conventions must not invent business behavior.

Do not define:

```text
cart eligibility
price calculations
inventory rules
request eligibility
order transitions
payment status behavior
```

from visual assumptions.

Backend/API contracts remain authoritative.

---

# 97. Request-First Boundary

For the initial request-first release:

```text
MADE_TO_ORDER product
→ legitimate product
→ Request Furniture conversion
```

The design system must support this without implying disabled commerce.

Do not expose dormant transactional checkout merely because generic e-commerce components normally contain Add to Cart.

---

# 98. No API Work

Do not modify:

```text
backend/
docs/api/
OpenAPI
Laravel
database
RBAC
```

during Phase 12.6.

---

# 99. No Framework Dependencies

Expected:

```text
dependencies added = NONE
```

MUI and Material UI Icons are already installed.

Do not add:

```text
component library
form library
animation library
icon library
CSS framework
Storybook
Chromatic
```

in this phase.

If Storybook or similar tooling already exists, it may be used but not expanded without need.

---

# 100. Documentation Updates

Likely files:

```text
frontend/design-system/DESIGN.md
frontend/design-system/USAGE.md
frontend/design-system/components.html
frontend/design-system/components.manifest.json
frontend/design-system/source/*
frontend/design-system/preview/*
frontend/design-system/flutter-material3.md
frontend/AGENTS.md

phases/group-L-phases.md
docs/decisions.md
```

Modify only what is justified.

A dedicated document such as:

```text
frontend/design-system/COMPONENTS.md
```

is acceptable if the existing documentation has become too large and repository conventions support it.

If created, clearly define its authority relationship:

```text
DESIGN.md
→ visual principles

tokens.css
→ values

USAGE.md
→ token usage

COMPONENTS.md
→ component construction conventions
```

Do not duplicate all existing content.

---

# 101. ADR

Add the next repository-consistent design ADR only if Phase 12.6 introduces material architectural decisions not already captured.

Suggested subject:

```text
DESIGN-005 — Cross-Platform Component Convention Architecture
```

Potential decisions:

```text
shared semantics, independent framework implementation

framework primitives preferred over redundant wrappers

semantic variants instead of raw visual props

component owns internal spacing

parent owns external spacing

links navigate; buttons act

cards used only for genuinely self-contained units

Material-family icons only

component states use closed semantic vocabulary

MUI sx allowed for token-driven composition, not design-system bypass

no cross-framework runtime component library
```

Use the actual next ADR ID.

---

# 102. Validation — Documentation Integrity

Validate:

```text
all referenced tokens exist
no stale token names
no contradictory component rules
no framework-specific values inserted into canonical tokens
no unsupported Flutter implementation claims
```

---

# 103. Validation — Component Catalog

Validate:

```text
components.manifest.json parses

manifest references valid catalog entries

catalog terminology matches furniture brand

no active Nike/apparel examples remain

preview examples use approved tokens

no arbitrary brand hex values in consumers

no unauthorized icon family
```

---

# 104. Validation — MUI Non-Regression

Because Phase 12.6 should not need to rewrite MUI:

```text
existing Phase 12.3 theme must remain intact
```

If any web theme file is changed to reconcile a genuine convention defect, run the repository's actual:

```text
typecheck
lint
tests
build
```

as applicable.

Do not invent command names.

---

# 105. Validation — Flutter Boundary

Verify:

```text
frontend/app remains untouched
```

unless it already contains an unrelated owner change.

No Flutter implementation is permitted.

---

# 106. Validation — Group Boundary

Verify no new application source implementing:

```text
navigation
catalog
product cards
forms
page layout
request UI
```

was added.

Design-system preview/reference files are allowed.

---

# 107. Validation — Raw Values

Run the existing raw-value audit.

Component consumer examples should not introduce:

```text
unapproved hex colors
random px/rem spacing
random radii
random shadows
random transition values
```

Approved preview scaffolding exceptions remain documented.

---

# 108. Validation — Icon Policy

Search relevant frontend/design-system guidance for:

```text
lucide
heroicons
react-icons
fontawesome
font-awesome
emoji icon
```

Active guidance must not authorize them.

Historical documentation may remain where clearly historical.

---

# 109. Validation — AI-Slop Audit

Search component guidance/previews for patterns contrary to the brand:

```text
gradient
glass
glassmorphism
blur card
floating blob
rounded everything
large card shadow
bounce
spring
```

Legitimate technical references may remain.

Active design guidance must preserve the restrained furniture direction.

---

# 110. Validation Commands

Run all existing Phase Group L/design-system validation.

At minimum verify:

```text
JSON parsing
token references
manifest integrity
derived token synchronization
WCAG/contrast where component fixtures introduce states
raw-value audit
legacy apparel audit
framework-leak audit
icon-family audit
git diff --check
```

Use existing repository scripts where available.

Do not introduce a dependency merely to perform validation.

---

# 111. Completion Report

Return:

```text
Phase 12.6 status:
PASS / BLOCKED

Component convention authority:
<file>

Existing catalog reconciled:
YES / NO

Runtime component implementation started:
NO

React application components created:
NO

Flutter widgets created:
NO

Component hierarchy:
PASS / FAIL

Framework primitives preferred:
YES / NO

Redundant wrapper policy:
PASS / FAIL

Semantic variant policy:
PASS / FAIL

Raw visual props restricted:
YES / NO

MUI sx policy:
PASS / FAIL

Internal/external spacing ownership:
PASS / FAIL

Button hierarchy:
PASS / FAIL

Primary action remains charcoal:
YES / NO

Material UI Icons web-only policy:
PASS / FAIL

Flutter Material Icons policy:
PASS / FAIL

Links vs buttons:
PASS / FAIL

Form conventions:
PASS / FAIL

Card/surface restraint:
PASS / FAIL

Product media conventions:
PASS / FAIL

MADE_TO_ORDER first-class treatment:
PASS / FAIL

Status/badge conventions:
PASS / FAIL

Feedback conventions:
PASS / FAIL

Loading conventions:
PASS / FAIL

Empty-state conventions:
PASS / FAIL

Error-state conventions:
PASS / FAIL

Responsive component principles:
PASS / FAIL

Semantic HTML:
PASS / FAIL

Flutter semantics:
PASS / FAIL

State vocabulary:
<summary>

Anti-AI-slop rules:
PASS / FAIL

Request-first boundary:
PASS / FAIL

Component manifest:
PASS / NOT CHANGED / FAIL

Component previews:
PASS / NOT CHANGED / FAIL

Canonical tokens changed:
NO / <explain>

MUI theme changed:
NO / <explain>

Flutter implementation changed:
NO

Backend/API changed:
NO

Dependencies added:
NONE

Token/reference validation:
PASS / FAIL

Manifest validation:
PASS / FAIL

WCAG fixture validation:
PASS / FAIL

Raw-value audit:
PASS / FAIL

Legacy apparel audit:
PASS / FAIL

Icon-family audit:
PASS / FAIL

Framework-leak audit:
PASS / FAIL

git diff --check:
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
<result / NONE>

Phase 12.7:
READY / BLOCKED
```

---

# 112. STOP Condition

Phase 12.6 is PASS only when:

- shared component construction conventions are explicit;
- component hierarchy and ownership are defined;
- framework primitives are preferred over redundant wrappers;
- wrappers require meaningful justification;
- variants are semantic and intentionally limited;
- raw appearance props cannot casually bypass the design system;
- MUI `sx` remains available for legitimate composition but not token bypass;
- future Flutter local styling follows the equivalent rule;
- buttons have a clear action hierarchy;
- primary actions remain charcoal;
- links and buttons retain correct semantics;
- Material UI Icons remain the sole web icon library;
- future Flutter uses Material Icons by default;
- form labeling/error conventions are explicit;
- cards are used selectively rather than as the default grouping mechanism;
- furniture imagery remains dominant;
- MADE_TO_ORDER remains a first-class offering;
- status treatment does not rely on color alone;
- feedback/loading/empty/error conventions are explicit;
- responsive component composition is defined without duplicating desktop/mobile components unnecessarily;
- components own internal spacing and parents own external spacing;
- semantic HTML requirements are explicit;
- Flutter semantics requirements are documented;
- component state vocabulary is defined;
- anti-AI-slop component rules are explicit;
- no application component library has actually been implemented;
- no Group M/N work has leaked forward;
- no backend/API behavior changed;
- no new dependencies were added;
- all design-system validation passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 12.6 — PASS
Phase 12.7 — READY
```

Do not start Phase 12.7 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Execution Record — 2026-10-06

### 12.6 — Component Conventions

**Status:** Complete

`frontend/design-system/COMPONENTS.md` defines the shared component hierarchy, semantic variant budget, spacing ownership, action and icon policy, forms, surfaces/cards, media, states, accessibility, responsive behavior, MUI/Flutter boundaries, and catalog role. No runtime component library or application component was created.
