# Phase 12.7 — Accessibility Baseline & Group L Closure

## Objective

Establish, document, validate, and freeze the **shared accessibility baseline** for the SL Furnitures design system, then perform the final **Group L closure audit**.

Phases 12.1–12.6 established:

```text
Brand/design direction
        ↓
Canonical token authority
        ↓
MUI theme mapping
        ↓
Flutter Material 3 mapping contract
        ↓
Visual foundations
        ↓
Component conventions
```

Phase 12.7 establishes:

```text
Accessibility baseline
        +
cross-platform design-system audit
        ↓
GROUP L CLOSED
        ↓
GROUP M READY
```

This phase must make accessibility a design-system invariant before application UI implementation begins.

It is NOT a claim that the eventual website or Flutter application is fully WCAG-conformant.

Pages and real user journeys do not yet exist.

The correct outcome is:

```text
Design system accessibility baseline
→ established and validated

Future application accessibility
→ constrained by that baseline
→ verified again during implementation and QA
```

---

# 1. Active Scope

Implement only:

```text
Phase 12.7 — Accessibility Baseline
+ Group L closure verification
```

Cover:

```text
contrast
focus visibility
keyboard interaction principles
semantic structure
heading principles
landmarks
links/buttons
form accessibility
labels/instructions/errors
target sizing
icons
images/alt text
status communication
loading/feedback
motion
zoom/reflow
text scaling
responsive accessibility
screen-reader semantics
touch/pointer considerations
high-contrast/forced-color resilience where practical
cross-platform accessibility responsibilities
future testing requirements
```

Then perform the final Group L consistency audit.

---

# 2. Explicitly Out of Scope

Do NOT implement:

```text
application pages
application components
navigation shell
header/footer
API client
routing
catalog
homepage
product pages
search
filters
request form
enquiry form
Clerk
Flutter widgets
Flutter ThemeData
backend/API work
```

Do NOT start Group M automatically.

---

# 3. Read All Group L Authorities First

Before editing, inspect:

```text
AGENTS.md
frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── COMPONENTS.md
├── tokens.css
├── design-tokens.json
├── tailwind-v4.css
├── flutter-material3.md
├── components.html
├── components.manifest.json
├── manifest.json
├── preview/
└── source/

frontend/web/theme/

phases/group-L-phases.md
docs/decisions.md
```

Read DESIGN-001 through DESIGN-005.

Read all Phase 12.1–12.6 execution records.

Do not reconstruct earlier decisions from assumptions.

---

# 4. Git Workflow

Before any Git command, locate and read the root:

```text
git-workflow-and-versioning
```

skill.

Follow it exactly.

Git operations are authorized only through that skill.

Preserve unrelated owner changes.

Never commit:

```text
.env
secrets
credentials
private keys
font binaries
local machine configuration
```

Stage only Phase 12.7 changes.

---

# 5. Accessibility Target

Use **WCAG 2.2 AA** as the baseline target for future customer-facing web implementation where applicable.

Do NOT claim:

```text
WCAG 2.2 AA certified
```

or:

```text
fully WCAG compliant
```

during Phase 12.7.

The design system alone cannot establish full application conformance.

Document instead:

```text
WCAG 2.2 AA-oriented design-system baseline
```

with later page/component verification required.

---

# 6. Accessibility Is a Constraint, Not a Variant

Accessibility must not become:

```text
accessible mode
a11y theme
special accessible component set
```

The default system must be accessible.

Do not create:

```text
AccessibleButton
AccessibleInput
AccessibleProductCard
```

as alternatives to ordinary components.

Accessibility belongs in the normal component contract.

---

# 7. Accessibility Authority

Create a dedicated accessibility document if the existing documentation would otherwise become overloaded.

Preferred:

```text
frontend/design-system/ACCESSIBILITY.md
```

if repository conventions support it.

Its relationship should be explicit:

```text
DESIGN.md
→ brand/design principles

tokens.css
→ canonical values

USAGE.md
→ visual/token usage

COMPONENTS.md
→ component construction rules

ACCESSIBILITY.md
→ accessibility invariants and verification requirements
```

Do not duplicate every rule from the other documents.

Reference them.

---

# 8. Accessibility Requirements Vocabulary

Use clear requirement language.

Prefer:

```text
MUST
MUST NOT
SHOULD
MAY
```

where useful.

Avoid vague statements such as:

```text
try to make accessible
consider accessibility
use good contrast
```

Important invariants should be testable.

---

# 9. Contrast — Normal Text

Document that future text/background combinations must meet the appropriate WCAG AA contrast requirement.

For normal text, target:

```text
4.5:1 minimum
```

unless a WCAG-defined exception applies.

Do not rely on visual judgment alone.

---

# 10. Contrast — Large Text

Large text may use the appropriate WCAG large-text threshold:

```text
3:1 minimum
```

only when it actually satisfies the WCAG definition of large text.

Do not classify text as "large" merely because it is a heading.

---

# 11. Non-Text Contrast

Important visual information such as:

```text
input boundaries
focus indicators
selected controls
meaningful icons
interactive component states
```

must maintain appropriate non-text contrast against adjacent colors where WCAG requires it.

Use:

```text
3:1
```

as the relevant baseline where applicable.

---

# 12. Existing Contrast Matrix

Re-run and preserve validation for core approved combinations:

```text
text.primary
on
surface.canvas

text.secondary
on
surface.canvas

text.primary
on
surface.paper

text.secondary
on
surface.paper

text on surface.editorial

inverse foreground
on
surface.inverse

primary action foreground
on
action.primary

focus treatment
on
canvas

focus treatment
on
paper

focus treatment
on
editorial

focus treatment
on
inverse
```

Do not assume Phase 12.5 passing means Phase 12.7 can skip verification.

---

# 13. Brand Accent Contrast

Explicitly determine where:

```text
--accent-brand
#321E0F
```

is safe for:

```text
text
icons
borders
decorative use
```

against approved surfaces.

Do not assume a brand color is automatically valid for every foreground/background combination.

---

# 14. Secondary Text

Verify:

```text
--text-secondary
#707072
```

against all surfaces where documentation permits its use.

If a combination fails:

```text
do not silently change the token.
```

Instead:

```text
restrict the usage
or
perform the canonical token-change process
```

and document why.

---

# 15. Color Independence

No important information may rely solely on:

```text
color
```

This applies to:

```text
errors
success
warning
selected state
availability
product type
MADE_TO_ORDER
stock state
form validation
```

Use additional:

```text
text
icon
shape
position
semantic state
```

where appropriate.

---

# 16. MADE_TO_ORDER

Reconfirm:

```text
MADE_TO_ORDER
```

is:

```text
valid offering
```

not:

```text
error
warning
disabled
unavailable
```

Accessibility semantics must match business meaning.

Do not announce it as an error/warning to assistive technology.

---

# 17. Focus Visibility

Keyboard focus MUST remain visible.

Prohibit:

```css
outline: none;
```

and equivalent behavior unless a visible accessible replacement exists.

Do not permit focus removal merely for visual cleanliness.

---

# 18. Focus Indicator

Validate the approved focus treatment against:

```text
canvas
paper
editorial
inverse
```

It must remain distinguishable.

If the design system uses:

```text
outline
ring
border
```

ensure it is not clipped by ordinary component geometry.

---

# 19. Focus vs Other States

Preserve distinct semantics for:

```text
hover
focus-visible
active/pressed
selected
disabled
loading
error
```

Do not collapse:

```text
focus = hover
```

or:

```text
selected = pressed
```

---

# 20. Focus Order

Document that future pages/components must preserve a logical focus order matching reading and interaction order.

Do not use positive:

```html
tabindex="1"
tabindex="2"
```

style ordering to repair poor DOM structure.

Prefer correct source order.

---

# 21. Keyboard Operability

All functionality available through pointer interaction must also be keyboard-operable where the platform interaction supports keyboard input.

This includes future:

```text
menus
dialogs
filters
product controls
forms
navigation
carousels if used
accordions
```

Do not invent custom keyboard interaction where native/framework semantics already provide it.

---

# 22. Native Semantics First

On web, prefer native HTML semantics.

Examples:

```text
button → <button>

navigation → <nav>

navigation destination → <a>

form field → appropriate native input

heading → h1–h6

list → ul / ol

main content → <main>
```

Use ARIA to supplement semantics when necessary.

Do not use ARIA to recreate native controls unnecessarily.

---

# 23. No Clickable Divs

Explicitly prohibit building ordinary controls as:

```html
<div onClick=...>
<span onClick=...>
```

when:

```html
<button>
<a>
<input>
```

provides the correct semantics.

MUI polymorphism must preserve the appropriate underlying element.

---

# 24. Landmarks

Future website layouts should expose meaningful landmarks such as:

```text
header/banner
navigation
main
footer/contentinfo
search where appropriate
```

Do not add redundant landmarks merely to satisfy a checklist.

Landmarks should help users navigate meaningful page regions.

---

# 25. Main Content

Each rendered page should expose a clear main-content region.

Future website shell should support a keyboard-accessible:

```text
Skip to main content
```

mechanism.

Do not implement the application shell in this phase.

Document the requirement for Group M.

---

# 26. Heading Hierarchy

Future pages must use headings for document structure rather than visual sizing.

Do not choose:

```text
h1
h2
h3
```

based on desired font size.

Typography styling and semantic heading level are separate concerns.

---

# 27. H1 Principle

Important public pages should normally expose a meaningful page-level heading.

Do not mechanically force exactly one H1 in every possible rendering context if framework composition creates a legitimate exception.

The requirement is coherent document hierarchy.

---

# 28. Heading Styling

A semantic:

```html
<h2>
```

may visually use an approved typography role independent of its semantic level.

Do not compromise document structure to obtain a visual style.

---

# 29. Links

Links must be recognizable as interactive.

Inline links must not depend on a subtle color difference alone where that makes the affordance unclear.

Hover/focus/visited treatment should remain distinguishable where applicable.

---

# 30. Buttons

Buttons must have accessible names describing the action.

Avoid ambiguous labels such as:

```text
Click here
Go
Yes
OK
```

when a clearer action exists.

Prefer:

```text
Request Furniture
Submit Enquiry
Remove Item
Close
```

as appropriate.

---

# 31. Icon-Only Controls

Every meaningful icon-only control must expose an accessible name.

Examples:

```text
Search
Open menu
Close
Previous image
Next image
Remove
```

The icon glyph itself is not an accessible name.

---

# 32. Decorative Icons

Decorative icons should be hidden from assistive technology where the framework does not already handle this.

Do not cause screen readers to announce:

```text
chair icon
arrow icon
check icon
```

when adjacent text already communicates the meaning.

---

# 33. Material Icon Policy

Preserve:

```text
Web
→ @mui/icons-material

Flutter
→ built-in Material Icons
```

Accessibility metadata is separate from the visual icon source.

Do not add another icon library.

---

# 34. Interactive Target Size

Document a practical baseline consistent with WCAG 2.2 and platform conventions.

Future controls must not create tiny interaction targets merely because the visible icon is small.

Use adequate interactive hit areas for:

```text
icon buttons
menu triggers
carousel controls
close controls
checkbox/radio targets
```

Do not enlarge the visual icon unnecessarily when increasing the hit target.

---

# 35. Mobile Touch Targets

Flutter/mobile implementation should follow Material/platform accessibility target conventions.

Do not shrink mobile targets to mimic desktop density.

---

# 36. Forms — Labels

Every future input requiring a label must have a programmatically associated label.

Placeholder-only labeling is prohibited.

Visible labels are preferred for ordinary forms.

---

# 37. Forms — Instructions

Instructions required to complete a field correctly should be available before or with the field interaction.

Do not reveal essential formatting requirements only after submission.

---

# 38. Forms — Required/Optional

Use one consistent convention per form for required/optional fields.

Whatever visual convention is used must also be programmatically understandable.

Do not rely on:

```text
red asterisk alone
```

without appropriate accessible semantics.

---

# 39. Forms — Errors

Field errors must:

```text
identify the field
describe the problem
remain associated with the field
be discoverable by assistive technology
not rely on red alone
```

When submission fails, future forms should move/announce attention appropriately without creating disruptive focus behavior.

Exact implementation belongs to application phases.

---

# 40. Forms — Error Summary

For longer forms, document that a page/form-level error summary may be appropriate when multiple validation failures occur.

Do not require an error summary for every two-field form.

Use proportionate treatment.

---

# 41. Forms — Autocomplete

Future account/contact forms should use appropriate browser/platform autofill semantics where supported.

Do not disable autocomplete indiscriminately.

Actual field mappings belong to later form implementation.

---

# 42. Forms — Input Purpose

Use appropriate:

```text
input type
inputMode
autocomplete
```

semantics for information such as:

```text
email
phone
```

when implemented.

Do not use generic text inputs for everything.

---

# 43. Forms — File Attachments

Future attachment UI must communicate:

```text
allowed file types
size limits
selected file
upload progress/state where applicable
failure
remove/replace action
```

to assistive technology as well as visually.

Do not expose backend storage implementation.

---

# 44. Error Messaging

Error messages must be useful without exposing:

```text
stack traces
database details
framework exceptions
private resource existence
```

The backend error contract remains authoritative.

Accessibility does not justify leaking sensitive information.

---

# 45. Images — Product Imagery

Furniture product imagery is content, not decoration.

Meaningful product images require appropriate alternative text.

Alt text should identify useful product information without attempting to narrate every visible pixel.

---

# 46. Image Gallery Alt Text

Where multiple images depict the same product:

```text
different views should be distinguishable when that distinction is useful.
```

Example conceptually:

```text
Oak dining table, front view
Oak dining table, side view
```

Do not mechanically repeat identical alt text for every gallery image when the views convey distinct useful information.

---

# 47. Decorative Images

Decorative imagery should not create unnecessary screen-reader noise.

Use appropriate empty/decorative semantics where an image contributes no meaningful content.

---

# 48. Background Images

Do not place essential information exclusively inside CSS/background imagery.

Important product/content information must exist as accessible text/content.

---

# 49. Logo

The official brand logo remains the brand identity authority.

When used as a home link, its accessible name should communicate the destination/brand appropriately.

Do not include redundant:

```text
image of
logo of
```

phrasing where unnecessary.

---

# 50. Product Cards

Future product-card semantics must make the product understandable without depending solely on imagery.

At minimum the accessible experience should expose the relevant:

```text
product name
price/starting price where available
product type/status where decision-relevant
navigation/request action
```

Do not create duplicated competing links/actions that produce noisy keyboard navigation.

---

# 51. Card Clickability

Avoid making an entire complex card a giant pseudo-button when it contains multiple interactive descendants.

Use semantic links/actions with a coherent focus model.

If a stretched-link pattern is later used:

```text
validate keyboard and screen-reader behavior carefully.
```

---

# 52. Price Accessibility

Future price presentation must remain understandable as text.

Do not encode pricing solely through:

```text
visual size
strikethrough
color
```

When a price is:

```text
starting from
```

that meaning must be available textually/programmatically.

---

# 53. Status Accessibility

Status components must expose meaningful text.

Do not make assistive technology infer:

```text
green dot = available
brown pill = made to order
red dot = unavailable
```

Use semantic labels.

---

# 54. Dynamic Status Changes

Future dynamically updated states should use appropriate announcements only when the update is meaningful.

Do not mark large page regions as aggressive live regions.

Prefer the least intrusive announcement mechanism.

---

# 55. Loading Accessibility

Loading must be communicated when it affects interaction or content understanding.

Do not rely solely on a spinner animation.

Future loading controls should provide appropriate text/semantics such as:

```text
Loading
Submitting
```

when necessary.

---

# 56. Skeleton Accessibility

Skeletons are visual placeholders.

They should not create dozens of meaningless screen-reader elements.

Future implementation should hide purely decorative skeleton structure from assistive technology where appropriate while preserving useful loading semantics.

---

# 57. Disabled vs Loading

Preserve:

```text
disabled
≠
loading
```

Assistive technology must be able to understand the actual state.

Do not mark a loading control merely as disabled and provide no progress context.

---

# 58. Empty States

Empty states must remain understandable without illustrations/icons.

Text should communicate the useful meaning.

Illustrations, if introduced later, are supplementary.

---

# 59. Dialog Accessibility

Future dialogs must support appropriate:

```text
accessible name
description where useful
focus entry
focus containment where appropriate
Escape/close behavior
focus restoration
keyboard operation
```

Prefer MUI/Material dialog primitives rather than reimplementing dialog mechanics.

---

# 60. Destructive Confirmation

Destructive dialogs must clearly identify:

```text
what will happen
what item/resource is affected where useful
which action confirms destruction
which action cancels
```

Do not rely on red styling to communicate destructive meaning.

---

# 61. Menus and Popovers

Prefer framework primitives with established keyboard/focus behavior.

Do not create custom menu interaction semantics simply to achieve a unique visual treatment.

---

# 62. Tooltips

Tooltips must not contain information essential to completing an action when that information is otherwise unavailable.

They supplement controls.

They do not replace:

```text
accessible names
labels
instructions
```

---

# 63. Motion

Preserve the quiet motion system from Phase 12.5.

Future animations must respect user reduced-motion preferences where supported.

Do not make understanding state dependent on animation.

---

# 64. Reduced Motion

Document implementation expectations for:

```text
prefers-reduced-motion
```

on web.

Future Flutter implementation should use appropriate platform accessibility/motion information available in the installed SDK.

Do not freeze a volatile Flutter API name in this phase.

---

# 65. Auto-Playing Motion

Do not introduce automatically playing decorative motion by default.

If later media/animation is added, it must be reviewed against applicable accessibility requirements.

---

# 66. Hover Independence

No essential information or action may be available only on hover.

Hover may enhance pointer interaction but cannot be the sole discovery mechanism.

This is particularly important for future product cards.

---

# 67. Responsive Reflow

Future website layouts must remain usable when viewport width is reduced or content is zoomed.

Do not require horizontal page scrolling for ordinary content except where the content inherently requires it.

Examples of potentially legitimate horizontal regions may include specialized tables, but they require their own accessible treatment.

---

# 68. Browser Zoom

Do not use layout techniques that fail merely because the user increases browser zoom.

Future implementation must test substantial zoom/reflow during accessibility verification.

Do not attempt to prevent zoom.

---

# 69. Text Scaling

Do not truncate or overlap important content when text size increases.

Avoid fixed-height text containers.

Phase 12.5's flexible-content-height rule remains authoritative.

---

# 70. Truncation

Use truncation only where information remains understandable and the complete content is available through an accessible mechanism when necessary.

Do not truncate:

```text
critical error messages
important instructions
primary product identity
```

solely for visual alignment.

---

# 71. Orientation

Future mobile experiences should not unnecessarily restrict orientation unless a genuine functional requirement exists.

Do not introduce orientation locks from design preference alone.

---

# 72. Responsive Adaptation

Accessibility semantics must survive layout changes.

Example:

```text
desktop filter panel
→ mobile filter bottom sheet
```

may change composition but must preserve:

```text
label
purpose
selection state
keyboard/screen-reader semantics where applicable
```

---

# 73. Source Order

Responsive CSS must not create a visual reading order that materially contradicts DOM/source order.

Do not use CSS reordering to make a semantically confusing keyboard/screen-reader sequence.

---

# 74. Breakpoints

Accessibility requirements are independent of breakpoint.

Do not treat mobile accessibility and desktop accessibility as separate optional systems.

---

# 75. Tables

Future operational/admin tables must use proper table semantics where tabular relationships matter.

Do not simulate data tables using arbitrary flex/grid divs purely for styling convenience.

Admin UI accessibility remains part of the same shared system.

---

# 76. Lists

Use semantic lists when content represents a list.

Examples may include:

```text
navigation items
search results
product collections
breadcrumbs where appropriate
```

Do not force list semantics where they do not add meaningful structure.

---

# 77. Language

Future web documents must expose the correct document language.

Do not hard-code incorrect language metadata.

If multilingual content is introduced later, language changes require proper semantics.

No multilingual architecture is required in this phase.

---

# 78. Page Titles

Future routes must provide meaningful page titles.

This also supports the SEO requirements that later belong to Group N.

Do not implement metadata now.

Record the accessibility requirement for future routing/page work.

---

# 79. Route Changes

Future client-side navigation must provide understandable focus/announcement behavior.

Do not assume SPA navigation is automatically equivalent to a full page load for assistive technology.

Exact strategy belongs to Group M/N implementation.

---

# 80. Skip Link Requirement

Record a mandatory future web-shell requirement:

```text
Skip to main content
```

It should:

```text
be keyboard reachable
become visible when focused
target the actual main region
```

Implementation belongs to Group M layout work.

---

# 81. Touch + Pointer

Interactive behavior must not require precision pointing beyond reasonable platform expectations.

Avoid tiny:

```text
close icons
carousel arrows
filter toggles
checkbox hit regions
```

even when their visual glyph is small.

---

# 82. Drag-Only Interaction

Do not make future functionality available only through dragging.

If drag interaction is introduced, provide an alternative operation where required.

This is especially relevant if future galleries/reordering functionality is introduced.

---

# 83. Gesture-Only Interaction

Mobile functionality should not depend solely on complex gestures.

Important actions require discoverable alternatives.

---

# 84. Forced Colors / High Contrast

Document that future web components should remain usable under browser/OS forced-color or high-contrast modes where practical.

Do not over-prescribe:

```text
forced-color-adjust: none
```

globally.

Native controls and semantic borders/focus states should remain perceivable.

---

# 85. CSS Background Dependence

Do not encode essential boundaries/state exclusively through subtle background colors that disappear in forced-color environments.

Use semantic structure/borders/text where appropriate.

---

# 86. MUI Accessibility Boundary

MUI provides useful accessible primitives, but:

```text
using MUI
≠
application automatically accessible
```

Future implementation remains responsible for:

```text
correct component choice
labels
content
heading structure
focus order
responsive composition
state announcements
```

Do not duplicate native/MUI accessibility mechanics without need.

---

# 87. Flutter Accessibility Boundary

Material 3 provides useful semantic behavior, but:

```text
using Material widgets
≠
application automatically accessible
```

Future Flutter implementation must verify:

```text
semantics
labels
touch targets
text scaling
focus where applicable
screen-reader order
motion
contrast
```

against actual SDK/device behavior.

---

# 88. SSR and Accessibility

The project's Next.js architecture prefers server rendering for important public content.

Accessibility-critical semantic content such as:

```text
headings
product names
prices
navigation
descriptive content
```

should not unnecessarily depend on client-side JavaScript before becoming available.

Do not implement this in Phase 12.7.

Record it as a Group M/N requirement.

---

# 89. No Accessibility Through Client-Only Duplication

Do not render a visually rich inaccessible server version and replace it with an accessible client version after hydration.

Semantic correctness belongs in the initial structure where practical.

---

# 90. Request-First Accessibility

The initial production mode is request-first.

Future MADE_TO_ORDER flows must make the primary customer action clear:

```text
Request Furniture
```

Do not create inaccessible/confusing disabled Add-to-Cart controls for products that are intentionally request-based.

---

# 91. Authentication Boundary

Accessibility requirements do not change authorization.

Do not expose private information in:

```text
error text
ARIA labels
live regions
page titles
hidden content
```

that would otherwise be masked by backend authorization rules.

---

# 92. Error Privacy

Future accessibility text must preserve the existing security contract.

For protected resources, accessible messaging must not reveal whether another user's private resource exists when the API intentionally masks it.

---

# 93. Automated Testing Strategy

Define the future web accessibility testing layers conceptually:

```text
static/lint checks
        ↓
component-level automated accessibility checks
        ↓
page-level automated checks
        ↓
keyboard/manual verification
        ↓
screen-reader/manual verification
```

Do not install tooling yet unless it already exists and is clearly appropriate to this phase.

---

# 94. No New Testing Dependency by Default

Expected:

```text
dependencies added = NONE
```

Do not automatically install:

```text
axe-core
jest-axe
Playwright
Cypress
Storybook accessibility addons
Lighthouse packages
```

during Group L merely to establish policy.

Later testing/tooling phases may select appropriate dependencies based on the actual frontend test architecture.

If suitable tooling already exists, use it.

---

# 95. Automated Tests Are Not Sufficient

Document explicitly:

```text
automated accessibility checks
≠
complete accessibility verification
```

Future releases require manual checks for:

```text
keyboard use
focus order
zoom/reflow
screen reader behavior
content quality
interaction understanding
```

---

# 96. Future Screen-Reader Matrix

Document a practical future verification expectation rather than prescribing an excessive enterprise matrix.

At minimum the project should eventually test representative supported combinations based on actual deployment platforms.

Do not invent browser/support guarantees during Phase 12.7.

---

# 97. Future Keyboard Checklist

Record future manual checks including:

```text
Tab through page
Shift+Tab backwards
activate buttons
follow links
open/close menus
open/close dialogs
operate form controls
reach skip link
verify visible focus
verify no keyboard traps
```

This becomes reusable QA guidance.

---

# 98. Future Zoom/Reflow Checklist

Record future verification for:

```text
browser zoom
narrow viewport
text enlargement
long product names
long validation errors
long button labels
```

The UI must not rely on ideal short fixture text.

---

# 99. Future Content Accessibility

Document content rules for:

```text
clear labels
descriptive headings
meaningful link text
concise errors
useful alt text
plain action language
```

Accessibility is partly content design, not just code.

---

# 100. Reference Preview Accessibility

Audit the existing design-system preview/reference artifacts.

Check:

```text
semantic HTML where practical
heading order
button/link semantics
form labels
focus visibility
contrast
image alt/decorative treatment
keyboard reachability where interactive
```

These previews are not production pages, but they should not demonstrate anti-patterns that later agents copy.

---

# 101. Preview Scope

Do not transform the design-system preview into a complete accessibility demo application.

Correct demonstrable anti-patterns.

Add only the minimum reference examples necessary to show:

```text
focus
labels
semantic controls
state communication
```

---

# 102. Components Manifest

Do not turn:

```text
components.manifest.json
```

into an accessibility runtime registry.

If its existing design metadata supports concise accessibility metadata, preserve/reconcile it.

Otherwise keep accessibility requirements in documentation.

---

# 103. COMPONENTS.md Reconciliation

Review the Phase 12.6 component contract against the accessibility baseline.

It already requires, among other things:

```text
semantic HTML
visible focus
accessible icon-only controls
form labels
programmatically associated errors
Material semantics
reduced motion
```

Do not duplicate entire sections.

Add cross-references or small corrections where Phase 12.7 establishes stronger requirements.

---

# 104. DESIGN.md Reconciliation

Ensure the high-level design principles still accurately state:

```text
accessible UI
visible keyboard focus
semantic structure
meaningful alt text
logical headings
adequate targets
no color-only state
```

Do not turn DESIGN.md into the detailed accessibility specification.

---

# 105. USAGE.md Reconciliation

Ensure token usage documentation points developers toward the accessibility authority for:

```text
contrast
focus
functional colors
text scaling
motion
```

Do not duplicate the full WCAG rules there.

---

# 106. Flutter Contract Reconciliation

Review:

```text
flutter-material3.md
```

for consistency with:

```text
contrast
semantics
text scaling
touch targets
motion
status meaning
```

Apply only documentation corrections.

Do not implement Flutter code.

---

# 107. MUI Theme Reconciliation

Review the existing MUI theme for foundational contradictions such as:

```text
focus suppressed globally
insufficient known semantic contrast
motion that cannot be reduced
theme defaults contradicting token semantics
```

If compliant:

```text
do not modify it.
```

If a genuine design-system defect exists:

```text
make the smallest token/theme correction necessary
```

and validate it.

Do not begin component implementation.

---

# 108. Accessibility Escape Hatch

When future implementation discovers a conflict:

```text
approved visual rule
vs
accessibility requirement
```

the process is:

```text
STOP
        ↓
identify actual accessibility requirement
        ↓
determine affected token/component convention
        ↓
fix the shared design-system authority
        ↓
synchronize framework mappings
        ↓
validate
        ↓
continue implementation
```

Do NOT locally override accessibility with:

```text
one-off color
one-off font size
one-off focus style
```

unless it is genuinely local and still follows the system.

Accessibility takes precedence over purely decorative preference.

---

# 109. Anti-Overlay Rule

Do not solve accessibility by introducing:

```text
accessibility overlay
accessibility widget
"enable accessibility" button
```

as a substitute for accessible implementation.

The default product must carry the accessibility baseline.

---

# 110. Group L Final Authority Audit

Before closing Group L, verify the authority chain is coherent:

```text
Brand/design intent
        ↓
DESIGN.md
        ↓
tokens.css
        ↓
design-tokens.json / derived representations
        ↓
MUI mapping
future Flutter mapping
        ↓
USAGE.md
        ↓
COMPONENTS.md
        ↓
ACCESSIBILITY.md
        ↓
future application implementation
```

There must be no competing design authority.

---

# 111. Token Authority Audit

Verify:

```text
tokens.css
```

is still canonical.

Ensure:

```text
design-tokens.json
tailwind-v4.css
MUI theme
Flutter mapping documentation
previews
```

do not redefine the system independently.

---

# 112. Typography Audit

Verify Group L closes with:

```text
Display
→ Young Serif

UI
→ approved utility sans

Scale
→ 12 / 14 / 16 / 20 / 24 / 32 / 48 / 96
```

No competing type scale.

No universal Young Serif UI.

No Futura/apparel-era typography rule.

---

# 113. Color Audit

Verify:

```text
Canvas
#FCF4ED

Paper
#FFFFFF

Editorial
#F4E9DF

Inverse
#111111

Primary text
#111111

Secondary text
#707072

Brand accent
#321E0F

Primary action
#111111
```

No competing palette.

No brown-as-everything behavior.

---

# 114. Shape Audit

Verify:

```text
sharp media
restrained controls
controlled containers
pill only where semantically justified
```

No bubble UI.

---

# 115. Elevation Audit

Verify:

```text
flat by default
true layers may elevate
```

No generic card-shadow design.

---

# 116. Motion Audit

Verify:

```text
quiet
functional
token-driven
reduced-motion aware
```

No spring/bounce/parallax defaults.

---

# 117. Icon Audit

Verify:

```text
Web
→ @mui/icons-material only

Flutter
→ Material Icons
```

No active:

```text
Lucide
Heroicons
React Icons
Font Awesome
custom inline SVG icon system
emoji UI icons
```

unless a specific previously approved exception exists.

---

# 118. Component Audit

Verify Phase 12.6 remains authoritative:

```text
framework primitives preferred
wrappers require justification
semantic variants
internal spacing owned by component
external spacing owned by parent
links navigate
buttons act
cards used selectively
MADE_TO_ORDER first class
```

Do not create runtime components to prove this.

---

# 119. Framework Boundary Audit

Verify:

```text
MUI theme
→ already implemented

Flutter ThemeData
→ NOT implemented

React component library
→ NOT implemented

website pages
→ NOT implemented
```

Group L must close before these later implementation phases expand.

---

# 120. Group M Boundary Audit

Verify Group L has NOT implemented:

```text
13.5 API client
13.6 routing conventions
13.7 layout system
13.8 error/loading/not-found application handling
13.9 responsive application foundation
```

Earlier Phase 12.3 MUI work is recognized as the intentionally pulled-forward dependency and must not be duplicated later.

---

# 121. Group N Boundary Audit

Verify Group L has NOT implemented:

```text
homepage
category pages
product listing
product detail
search
filters/sorting
SEO metadata
structured data
sitemap
robots
internal linking
catalog image optimization
```

---

# 122. Group P Boundary Audit

Verify no Flutter application implementation has started.

`frontend/app/` should remain untouched by Group L unless unrelated owner content already exists.

---

# 123. Legacy Nike Audit

Search active design-system artifacts for:

```text
Nike
sneaker
shoe
apparel
sportswear
athletic
performance
Futura
swoosh
```

Distinguish:

```text
historical provenance
```

from:

```text
active design instruction
```

Historical provenance may remain where useful.

Active component/design guidance must be furniture-specific.

---

# 124. Anti-AI-Slop Audit

Verify active guidance still prohibits:

```text
random gradients
glassmorphism
decorative blur
floating blobs
excessive pills
excessive radius
heavy card shadows
decorative icon overload
random animation
arbitrary typography
arbitrary spacing
arbitrary colors
generic SaaS dashboard styling
```

---

# 125. Dependency Audit

Expected Group L final state:

```text
Phase 12.7 dependencies added:
NONE
```

Do not install accessibility libraries solely for this documentation/foundation phase.

Also confirm Phase 12.7 did not accidentally alter package dependencies.

---

# 126. Design-System Validation

Run the complete existing design-system validation suite.

At minimum verify:

```text
JSON parsing
token reference integrity
derived-token synchronization
MUI semantic mapping
Flutter mapping references
component manifest integrity
WCAG core contrast
focus contrast
raw-value audit
framework-leak audit
legacy terminology audit
icon-family audit
preview token usage
git diff --check
```

Use existing repository validation scripts where available.

---

# 127. Accessibility-Specific Validation

Add or extend lightweight validation only where it provides durable value without new dependencies.

Examples:

```text
known canonical contrast-pair assertions
focus token presence
required accessibility-document sections
reference preview label checks
prohibited outline-removal patterns
unauthorized icon checks
```

Do not pretend regex/static checks prove full accessibility.

---

# 128. Raw Accessibility Anti-Pattern Audit

Search relevant frontend/design-system files for active occurrences of patterns such as:

```text
outline: none
outline: 0
tabIndex={1}
tabIndex={2}
aria-hidden="true"
role=
onClick=
```

Do NOT automatically treat every match as a defect.

Review context.

Examples:

```text
aria-hidden="true"
```

may be correct for decorative icons.

The purpose is semantic review, not blind rejection.

---

# 129. MUI Verification

If no files under:

```text
frontend/web/
```

change:

```text
do not modify the application merely to create work.
```

Run existing theme/design-system verification sufficient to establish non-regression.

If MUI files must change because a genuine accessibility foundation defect is discovered, run the actual available:

```text
typecheck
lint
tests
production build
```

as applicable from `package.json`.

Do not invent script names.

---

# 130. Flutter Verification

No Flutter build is required because no Flutter implementation should exist from this phase.

Validate only:

```text
mapping documentation
Group P boundary
no runtime token loading
no Flutter code changes
```

---

# 131. Documentation

Expected likely outputs:

```text
frontend/design-system/ACCESSIBILITY.md

frontend/design-system/DESIGN.md
    # cross-reference/minimal reconciliation only

frontend/design-system/USAGE.md
    # cross-reference/minimal reconciliation only

frontend/design-system/COMPONENTS.md
    # cross-reference/minimal reconciliation only

frontend/design-system/flutter-material3.md
    # only if necessary

frontend/design-system/preview/*
    # only if accessibility demonstration requires correction

frontend/AGENTS.md
    # accessibility enforcement if appropriate

phases/group-L-phases.md
docs/decisions.md
```

Modify only files justified by the phase.

---

# 132. ADR

Add the next repository-consistent ADR if the accessibility baseline constitutes a material design-system decision.

Suggested:

```text
DESIGN-006 — Accessibility Is a Default Design-System Invariant
```

Record decisions such as:

```text
WCAG 2.2 AA-oriented baseline

accessibility is default, not optional mode

native semantics before ARIA

focus visibility mandatory

no color-only meaning

semantic heading/landmark structure

adequate target sizing

text scaling/reflow supported

reduced motion respected

Material/MUI primitives preferred where they provide established semantics

automated testing supplements but does not replace manual accessibility testing

no accessibility overlay as substitute for implementation
```

Use the actual next ADR identifier.

---

# 133. Group L Documentation Reconciliation

At completion, `phases/group-L-phases.md` should clearly show the actual executed sequence:

```text
12.1 — Brand/design goals
       PASS

12.2 — Shared token authority/reconciliation
       PASS

12.3 — MUI theme structure/mapping
       PASS

12.4 — Flutter Material 3 theme structure
       PASS

12.5 — Visual foundations
       PASS

12.6 — Component conventions
       PASS

12.7 — Accessibility baseline
       PASS
```

Do not preserve obsolete numbering that implies unexecuted separate typography/color/spacing phases.

Record that those concerns were consolidated into Phase 12.5.

---

# 134. AGENTS.md Roadmap Reconciliation

The root roadmap currently contains the older Group L breakdown.

Do NOT casually rewrite the master roadmap unless project documentation conventions permit execution-time reconciliation.

At minimum ensure the Group L execution record clearly explains:

```text
original roadmap concerns
        ↓
consolidated implementation phases
```

so future agents do not attempt to rerun:

```text
old 12.5 typography
old 12.6 color
old 12.7 spacing
old 12.8 shape/elevation
old 12.9 component conventions
old 12.10 accessibility baseline
```

as if they were still pending.

If repository policy allows updating `AGENTS.md`, reconcile its Group L subsection to the executed sequence without altering Groups M–W.

If repository policy treats the original roadmap as historical/master planning:

```text
leave it intact
```

and document the superseding execution mapping in:

```text
phases/group-L-phases.md
```

Choose based on established repository convention.

Do not silently create conflicting roadmaps.

---

# 135. Group L Exit Condition

The master Group L intent is:

```text
Website and app have an agreed visual system
before extensive UI implementation.
```

Verify this is demonstrably true.

The website currently has:

```text
shared token contract
+
MUI mapping
```

The future Flutter app has:

```text
shared token contract
+
Material 3 mapping specification
```

Both share:

```text
brand principles
typography semantics
color semantics
spacing
shape
elevation
motion
component conventions
accessibility baseline
```

That is sufficient to close Group L.

Actual Flutter ThemeData remains Phase 16.2.

---

# 136. Do Not Require Flutter Implementation to Close Group L

The original roadmap wording must not cause Phase 12.7 to initialize Flutter merely to demonstrate the design system.

Phase 12.4 already established the Flutter mapping contract.

Actual implementation remains:

```text
Group P
Phase 16.2
```

This boundary is intentional.

---

# 137. Group M Readiness Audit

Before declaring Group M ready, verify:

```text
frontend/web exists

Next.js scaffold exists

TypeScript configuration exists or can be verified in 13.2

MUI packages already installed from Phase 12.3

@mui/icons-material already installed

MUI theme exists

token authority exists

component conventions exist

accessibility baseline exists
```

Do NOT implement missing Group M items.

Only identify them as:

```text
future work
```

---

# 138. Group M Duplication Warning

Record prominently for future agents:

```text
Phase 13.3 — MUI integration
and
Phase 13.4 — Theme integration
```

must begin by inspecting Phase 12.3.

They must NOT automatically:

```text
reinstall MUI
create second theme
replace token mappings
create competing providers
```

If Phase 12.3 already satisfies their required outcome, those phases should become:

```text
inspect
→ verify
→ reconcile only if needed
→ record PASS
```

---

# 139. Phase 13.1 Warning

If the project owner already scaffolded:

```text
frontend/web
```

future Phase 13.1 must NOT rerun:

```text
create-next-app
```

over the existing project.

Phase 13.1 should inspect and reconcile the existing scaffold.

---

# 140. Group N Remains Separate

Do not reinterpret Group L completion as permission to start building catalog pages.

After Group L:

```text
Group M
```

comes next.

Only after the relevant Group M foundation is complete should Group N website catalog/SEO implementation begin.

---

# 141. Group L Final Report

Return a dedicated closure report:

```text
Phase 12.7 status:
PASS / BLOCKED

Accessibility authority:
<file>

Accessibility target:
WCAG 2.2 AA-oriented baseline

Full application WCAG compliance claimed:
NO

Accessibility default/optional:
DEFAULT

Native semantics first:
PASS / FAIL

Keyboard baseline:
PASS / FAIL

Focus visibility:
PASS / FAIL

Focus contrast:
PASS / FAIL

Color-only communication prohibited:
YES / NO

Normal text contrast:
PASS / FAIL

Large text contrast:
PASS / FAIL

Non-text contrast baseline:
PASS / FAIL

Target-size baseline:
PASS / FAIL

Heading hierarchy:
PASS / FAIL

Landmark requirements:
PASS / FAIL

Skip-link requirement:
PASS / FAIL

Link/button semantics:
PASS / FAIL

Form labels:
PASS / FAIL

Form errors:
PASS / FAIL

Icon-only accessible names:
PASS / FAIL

Decorative icon semantics:
PASS / FAIL

Image/alt-text policy:
PASS / FAIL

Product-card accessibility:
PASS / FAIL

Status semantics:
PASS / FAIL

Loading semantics:
PASS / FAIL

Dialog semantics:
PASS / FAIL

Reduced motion:
PASS / FAIL

Hover-only functionality prohibited:
YES / NO

Zoom/reflow:
PASS / FAIL

Text scaling:
PASS / FAIL

Responsive source-order rule:
PASS / FAIL

Forced-colors/high-contrast guidance:
PASS / FAIL

Web/MUI boundary:
PASS / FAIL

Flutter/Material boundary:
PASS / FAIL

Automated testing limitations documented:
YES / NO

Future manual testing checklist:
PASS / FAIL

Accessibility overlay rejected:
YES / NO

Request-first accessibility:
PASS / FAIL

Security/privacy preserved:
PASS / FAIL


GROUP L CLOSURE

12.1:
PASS

12.2:
PASS

12.3:
PASS

12.4:
PASS

12.5:
PASS

12.6:
PASS

12.7:
PASS / BLOCKED

Canonical token authority:
tokens.css

Portable token representation:
design-tokens.json

MUI mapping:
PASS

Flutter mapping contract:
PASS

Visual foundations:
PASS

Component conventions:
PASS

Accessibility baseline:
PASS / FAIL

Competing design authority:
NONE / <explain>

Runtime React component library created:
NO

Flutter implementation started:
NO

Group M implementation started:
NO

Group N implementation started:
NO

Backend/API changed:
NO

Dependencies added:
NONE

Legacy Nike/apparel active guidance:
NONE / <explain>

Unauthorized icon libraries:
NONE / <explain>

Anti-AI-slop audit:
PASS / FAIL

Design-system validation:
<commands/results>

Accessibility validation:
<commands/results>

Raw-value audit:
PASS / FAIL

Framework-leak audit:
PASS / FAIL

git diff --check:
PASS / FAIL

Files changed:
<list>

ADR:
<id / NONE>

Roadmap reconciliation:
<summary>

Git workflow skill read:
YES / NO

Git operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


Group L:
CLOSED / BLOCKED

Group M:
READY / BLOCKED

Next phase:
13.1 — Next.js project setup verification/reconciliation
```

---

# 142. Final STOP Condition

Phase 12.7 and Group L may be declared PASS/CLOSED only when:

- an explicit accessibility authority exists;
- the baseline is WCAG 2.2 AA-oriented without falsely claiming full application conformance;
- accessibility is part of the default system;
- contrast requirements are documented and validated for canonical combinations;
- focus visibility is mandatory;
- keyboard operability principles are explicit;
- native HTML semantics are preferred over unnecessary ARIA;
- heading and landmark principles are explicit;
- the future web shell requires a skip link;
- links and buttons retain correct semantics;
- forms require labels, instructions, accessible validation, and consistent required-state communication;
- icon-only controls require accessible names;
- decorative icons are excluded from unnecessary announcements;
- meaningful product imagery requires appropriate alt text;
- product cards remain understandable without imagery;
- status does not depend on color alone;
- loading/empty/error/dialog semantics are defined;
- reduced motion is respected;
- essential functionality cannot depend on hover;
- zoom, reflow, and text scaling are part of future verification;
- responsive visual order cannot contradict semantic/source order;
- forced-color/high-contrast resilience is documented;
- MUI and Material 3 are treated as useful accessibility foundations, not automatic guarantees;
- automated accessibility tests are explicitly recognized as insufficient by themselves;
- future keyboard/manual/screen-reader verification requirements are documented;
- accessibility overlays are rejected as substitutes for accessible implementation;
- request-first MADE_TO_ORDER behavior remains accessible and first-class;
- security/privacy behavior is not weakened by accessible messaging;
- Phase 12.1–12.6 authorities remain coherent;
- `tokens.css` remains canonical;
- no competing design authority exists;
- no application component/page work was introduced;
- no Flutter implementation was introduced;
- no Group M/N work was started;
- no backend/API behavior changed;
- no new dependency was added merely for this phase;
- the complete design-system validation suite passes;
- roadmap reconciliation prevents future agents from repeating the superseded old Group L numbering;
- Git operations follow `git-workflow-and-versioning`.

Then report exactly:

```text
Phase 12.7 — PASS
Group L — CLOSED
Group M — READY

Next:
Phase 13.1 — Next.js project setup verification/reconciliation
```

Do **not** start Phase 13.1 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**