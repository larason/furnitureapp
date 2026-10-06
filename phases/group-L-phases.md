# Phase 12.4 — Flutter Material 3 Theme Structure

## Objective

Define the **Flutter Material 3 theme architecture and token-mapping contract** for SL Furnitures without implementing the Flutter application yet.

The purpose of this phase is to make future:

```text
Phase 16.1 — Flutter project setup
Phase 16.2 — Theme / Material 3
```

mechanical rather than interpretive.

The dependency direction must be:

```text
frontend/design-system/tokens.css
            ↓
 synchronized portable representation
            ↓
frontend/design-system/design-tokens.json
            ↓
Flutter Material 3 mapping contract
            ↓
future ThemeData / ColorScheme / TextTheme
```

Do NOT create a second Flutter-specific brand system.

---

# 1. Active Scope

Implement only:

```text
Phase 12.4 — Flutter Material 3 Theme Structure
```

This phase defines:

```text
token consumption
ColorScheme mapping
TextTheme mapping
spacing model
shape/radius mapping
elevation model
motion mapping
focus/accessibility expectations
responsive principles
Flutter theme file architecture for later implementation
```

Do NOT begin:

```text
Flutter application initialization
Flutter package installation
ThemeData implementation
MaterialApp
routing
navigation
networking
Clerk integration
screens
widgets
catalog
cart
request/enquiry UI
```

Those belong to Groups P and Q.

---

# 2. Read Authorities First

Before editing, inspect:

```text
AGENTS.md

frontend/AGENTS.md

frontend/design-system/
├── DESIGN.md
├── USAGE.md
├── tokens.css
├── design-tokens.json
├── manifest.json
├── source/
└── preview/

phases/group-L-phases.md
docs/decisions.md
```

Also inspect:

```text
frontend/app/
```

only to determine whether a Flutter project already exists.

Do not modify it merely because it exists.

---

# 3. Group Boundary Is Mandatory

The master roadmap separates:

```text
Group L
→ shared design-system foundation

Group P
→ Flutter application foundation
```

Therefore Phase 12.4 must NOT prematurely perform Group P work.

If:

```text
frontend/app/
```

is empty or not yet initialized:

```text
leave it that way.
```

If a Flutter scaffold already exists:

```text
do not start modifying application/theme files
unless a tiny validation-only fixture is already part of design-system tooling.
```

Primary outputs belong under:

```text
frontend/design-system/
phases/
docs/
```

not the Flutter app.

---

# 4. Git Workflow

Before any Git command, locate and read the root:

```text
git-workflow-and-versioning
```

skill.

Git operations are authorized only through that skill.

Preserve unrelated owner changes.

Never commit:

```text
.env
tokens
credentials
local SDK configuration
generated secret files
```

Stage only Phase 12.4 work.

---

# 5. Existing Design Authority

Preserve the established brand system:

```text
Architectural
Warm
Editorial
Crafted
Calm
Accessible
```

The interface remains a quiet frame around furniture imagery and product information.

Do NOT create a Flutter-native visual identity that drifts from the website.

The website and app should share:

```text
visual language
semantic colors
typographic hierarchy
spacing rhythm
shape philosophy
elevation philosophy
interaction principles
```

but they do NOT need identical layouts.

---

# 6. Token Authority

The canonical design token authority remains:

```text
frontend/design-system/tokens.css
```

Flutter must NOT become another authority.

For Flutter consumption, use:

```text
frontend/design-system/design-tokens.json
```

as the portable synchronized representation.

Do not make Flutter parse CSS.

Do not duplicate all canonical token values manually into documentation.

Do not create:

```text
flutter-tokens.json
mobile-colors.json
app-theme-tokens.json
```

unless the current design-system generation pipeline explicitly requires such an artifact.

---

# 7. Future Flutter Mapping

Define the future mapping architecture:

```text
design-tokens.json
        ↓
Flutter token adapter
        ↓
Material 3
├── ColorScheme
├── TextTheme
├── ThemeData
├── component themes
└── extensions only where justified
```

The Flutter adapter must consume semantics.

It must not invent brand values.

---

# 8. Material 3 Is the Framework, Not the Brand Authority

Material 3 should provide:

```text
component behavior
accessibility foundations
platform conventions
theme APIs
state systems
```

It must NOT override the brand contract.

Dependency:

```text
SL Furnitures design system
        ↓
Material 3 mapping
```

NOT:

```text
Material 3 defaults
        ↓
SL Furnitures design
```

---

# 9. Material 3 Must Be Enabled Later

Document that the future Flutter implementation must use:

```dart
useMaterial3: true
```

Do not implement it now.

Do not design against Material 2 assumptions.

---

# 10. ColorScheme Mapping

Define how canonical semantic colors map conceptually into Material 3.

At minimum reconcile:

```text
--surface-canvas
--surface-paper
--surface-editorial
--surface-inverse

--text-primary
--text-secondary

--action-primary
--accent-brand

functional colors
focus colors
border/outline colors
```

into appropriate future:

```text
ColorScheme
```

roles.

---

# 11. Primary Color

Preserve:

```text
primary action
→ #111111
```

Future Material 3:

```text
ColorScheme.primary
```

should represent the primary action semantics unless a Material-specific conflict is documented.

Do NOT make the logo brown the primary interaction color.

---

# 12. Brand Accent

Preserve:

```text
--accent-brand
→ #321E0F
```

as a restrained furniture/brand accent.

Do NOT automatically map it to:

```text
ColorScheme.secondary
```

without considering semantic correctness.

If Material's built-in slots do not cleanly represent the accent, document a future:

```text
ThemeExtension
```

strategy.

Do not misuse functional roles just to access the color.

---

# 13. Warm Canvas

The canonical warm canvas:

```text
--surface-canvas
→ #FCF4ED
```

should influence the future root/background surface.

Document the intended Material role.

Likely candidates include:

```text
surface
surfaceContainerLowest
```

depending on Flutter's installed Material 3 API at implementation time.

Do NOT freeze an API member that may differ by future Flutter version without noting version verification.

---

# 14. Paper Surface

Canonical:

```text
--surface-paper
→ #FFFFFF
```

represents:

```text
product surfaces
forms
clean contrast areas
```

Document its Material 3 role separately from canvas.

Do not collapse:

```text
canvas
paper
editorial
```

into one surface just because Material has default tonal surfaces.

---

# 15. Editorial Surface

Canonical:

```text
--surface-editorial
→ #F4E9DF
```

must remain available for editorial/storytelling compositions.

If standard ColorScheme roles cannot represent this semantic cleanly:

```text
use a narrow ThemeExtension later
```

rather than abusing:

```text
errorContainer
tertiaryContainer
```

or another unrelated Material role.

---

# 16. Inverse Surface

Canonical:

```text
--surface-inverse
→ #111111
```

may map to Material inverse/high-contrast roles where semantically appropriate.

Do not interpret this as dark mode.

No global Flutter dark theme is approved in this phase.

---

# 17. Functional Colors

Preserve canonical semantic meaning for:

```text
error
warning
success
information
focus
```

where defined.

Material 3 has built-in:

```text
error
onError
errorContainer
onErrorContainer
```

but does not necessarily have direct standard roles for every application semantic.

Document which roles:

```text
map directly
need ThemeExtension
remain component-semantic
```

Do not force all functional statuses into Material's primary/secondary/tertiary scheme.

---

# 18. Do Not Generate a ColorScheme From a Seed

Do NOT approve:

```dart
ColorScheme.fromSeed(...)
```

as the canonical brand generation mechanism.

The palette is already approved.

Seed generation would create new tonal colors that could drift from:

```text
#111111
#FFFFFF
#FCF4ED
#F4E9DF
#321E0F
```

Use explicit semantic mapping in future Phase 16.2.

---

# 19. No Automatic Dynamic Color

Do not introduce:

```text
Android dynamic color
Material You wallpaper colors
platform-generated theme palette
```

for the brand baseline.

User wallpaper/device colors must not replace the SL Furnitures identity.

A future opt-in decision could revisit this separately.

---

# 20. Typography Architecture

Preserve:

```text
DISPLAY
→ Young Serif

UTILITY/UI
→ existing Helvetica Now utility stack
```

Young Serif is intended for brand/editorial moments, while the utility sans remains responsible for navigation, controls, forms, pricing, metadata, specifications, and dense interfaces.

---

# 21. Flutter Font Constraint

Do NOT install font packages yet.

Do NOT:

```text
download Young Serif
add pubspec font assets
use google_fonts package
```

during Phase 12.4.

Actual font loading belongs to:

```text
Phase 16.2
```

after the Flutter application exists.

Phase 12.4 defines the semantic mapping only.

---

# 22. Future TextTheme Mapping

Define which Material typography roles should use:

```text
display family
```

versus:

```text
UI family
```

Conceptually:

```text
displayLarge
displayMedium
headlineLarge
headlineMedium
selected titleLarge
→ Young Serif where brand hierarchy requires it
```

and:

```text
bodyLarge
bodyMedium
bodySmall
labelLarge
labelMedium
labelSmall
buttons / controls
→ utility sans
```

Do not map blindly.

Use the canonical typography role definitions from `DESIGN.md`.

---

# 23. Preserve Canonical Type Scale

The current canonical scale remains:

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

Do not allow Material 3 defaults to introduce an independent Flutter type scale.

Map Flutter text roles to approved values.

Do not create mobile-specific:

```text
15
17
18
22
28
```

values unless a later user-tested accessibility requirement justifies a token-contract change.

---

# 24. System Text Scaling

Future Flutter UI must respect user text scaling.

Do not disable:

```text
MediaQuery text scaling
system font scaling
accessibility text size
```

merely to preserve layouts.

The future implementation must tolerate larger text.

Phase 12.4 should document this constraint.

---

# 25. Spacing

Preserve the canonical spacing scale.

Future Flutter widgets should consume a typed token adapter rather than scatter:

```dart
EdgeInsets.all(17)
SizedBox(height: 23)
```

throughout the app.

Conceptual future mapping:

```text
space token
→ Dart constant/value
→ padding/gap
```

Do not implement those Dart constants yet.

---

# 26. No Material Default Spacing Authority

Material component defaults may be retained where they do not conflict with the design system.

But when application-level spacing is intentional:

```text
canonical token wins.
```

Do not redefine the shared spacing system to accommodate default widget padding.

---

# 27. Shape

Preserve current shape philosophy:

```text
sharp media
small form radius
controlled container radius
pill only where function justifies it
```

The brand explicitly rejects bubble UI and excessive rounding.

Future Flutter component themes should map to canonical radius tokens.

---

# 28. Material 3 Default Shape Reconciliation

Document that future Phase 16.2 must inspect Material 3 defaults for:

```text
ButtonStyle
InputDecorationTheme
CardTheme
DialogTheme
BottomSheetTheme
ChipTheme
NavigationBarTheme
```

and override them only where necessary to respect canonical shape tokens.

Do not allow Material 3's default rounded aesthetic to silently make the Flutter app more rounded than the website.

---

# 29. Elevation

Preserve the brand principle:

```text
flat surfaces first
elevation only for true layers
```

Future Flutter:

```text
Card
Material
Dialog
Menu
BottomSheet
Drawer
```

must not automatically create decorative shadow-heavy interfaces.

Map approved elevation tokens.

---

# 30. True-Layer Elevation

Elevation remains appropriate for:

```text
dialogs
menus
drawers
bottom sheets
floating overlays
```

where layering needs visual communication.

Do not globally set every elevation to zero without examining accessibility/separation.

---

# 31. Motion

Map canonical:

```text
duration
easing
```

semantics to future Flutter animations.

Do not invent a Flutter-only animation vocabulary.

Avoid:

```text
springy cards
bounce
large hover-like scaling
decorative page transitions
```

Motion should remain quiet and purposeful.

---

# 32. Reduced Motion

Document that future Flutter animation implementation must respect platform/user accessibility preferences where supported.

Do not create motion that is essential to understanding state.

---

# 33. Component Theme Architecture

Define which future Material 3 component themes are likely to be centralized.

Examples:

```text
AppBarTheme
FilledButtonThemeData
OutlinedButtonThemeData
TextButtonThemeData
IconButtonThemeData
InputDecorationTheme
CardThemeData / CardTheme depending installed Flutter API
DialogTheme
BottomSheetThemeData
NavigationBarThemeData
DividerThemeData
ChipThemeData
SnackBarThemeData
```

These names must be verified against the installed Flutter SDK during Phase 16.2.

Do not implement them now.

---

# 34. Avoid Huge ThemeData File

Define future architecture so ThemeData does not become a thousand-line monolith.

Recommended conceptual structure:

```text
theme/
├── app_theme.dart
├── app_color_scheme.dart
├── app_typography.dart
├── app_theme_extensions.dart
└── component_themes/
```

ONLY as a future guideline.

The Phase 16.2 agent should use the smallest structure warranted by actual complexity.

Do not create these files yet unless a non-app design-system Dart package already exists and is intentionally part of the repository.

---

# 35. ThemeExtension Policy

Flutter's:

```dart
ThemeExtension
```

should be used only for canonical semantics that Material 3 cannot represent cleanly.

Likely candidates may include:

```text
editorial surface
brand accent
custom status semantics
```

Do NOT create a ThemeExtension mirroring all 110 tokens.

Standard Material theme APIs should be used where semantics fit.

---

# 36. Icons

The web icon policy is:

```text
@mui/icons-material only
```

Do NOT incorrectly apply that package to Flutter.

For Flutter, default future icon source should be:

```text
Material Icons
```

provided by Flutter/Material, unless the project owner later approves another icon system.

Maintain conceptual consistency:

```text
Web
→ Material UI Icons

Flutter
→ Material Icons
```

No Font Awesome, Lucide, custom icon packs, emoji-as-icons, or mixed libraries by default.

---

# 37. Icon Semantics

Both platforms should aim for equivalent meanings, not necessarily identical glyphs.

Example:

```text
search
favorite
menu
close
arrow back
shopping cart
account
filter
```

Use the closest native Material-family icon appropriate to each platform.

Do not force the exact web SVG into Flutter.

---

# 38. Accessibility

Document Flutter-specific baseline requirements:

```text
Semantic widgets where needed
tooltips/labels for icon-only controls
logical traversal
minimum interactive target sizes
screen-reader meaningful labels
contrast compliance
text scaling
not color-only states
reduced-motion consideration
```

Accessibility remains a brand requirement.

---

# 39. Touch Targets

Future Flutter controls must respect platform accessibility target sizing.

Do not shrink icon buttons merely to imitate desktop website proportions.

Shared visual language does not mean identical control dimensions.

---

# 40. Responsive Mobile Composition

The Flutter app is not a compressed website.

Document:

```text
shared tokens
+
mobile-native composition
```

The project's frontend architecture already requires the website and app to share a visual language without requiring identical layouts.

Future Flutter layouts may use:

```text
bottom navigation
mobile app bars
modal bottom sheets
native scrolling compositions
```

where appropriate.

---

# 41. Platform Adaptation

Permit platform-appropriate behavior for:

```text
navigation
safe areas
keyboard handling
system overlays
scrolling
back navigation
dialogs
```

without changing the brand semantics.

Design tokens are not a command to make Flutter behave like a browser.

---

# 42. Surface Hierarchy Across Platforms

Document expected parity:

```text
Web surface.canvas
↔ Flutter app background/canvas semantic

Web surface.paper
↔ Flutter paper/product surface

Web surface.editorial
↔ Flutter editorial/story surface

Web surface.inverse
↔ Flutter inverse section
```

Exact framework properties may differ.

Meaning must not.

---

# 43. Primary Action Parity

Across platforms:

```text
primary action
→ charcoal
```

Do not allow:

```text
web primary = charcoal
Flutter primary = brown/blue/default Material purple
```

This is a cross-platform contract.

---

# 44. Typography Parity

Across platforms:

```text
brand display
→ Young Serif

UI
→ approved utility sans/fallback strategy
```

If the exact web utility font cannot be distributed/licensed/loaded appropriately in Flutter later:

STOP and document that issue.

Do not silently substitute an unrelated font in Phase 12.4.

The actual implementation decision belongs to Phase 16.2.

---

# 45. Font Licensing / Availability Boundary

Do not copy font files from the web or system into the Flutter repository.

Do not bundle any font file during this phase.

Record only:

```text
font family semantic roles
future loading requirements
```

---

# 46. Status Semantics

The design contract must remain capable of representing:

```text
MADE_TO_ORDER
IN_STOCK
LOW_STOCK
UNAVAILABLE
```

without encoding them as Material error states.

In particular:

```text
MADE_TO_ORDER
```

is a primary offering, not warning/error.

Do not define widget implementations yet.

---

# 47. Request-First Production Mode

The first production release currently emphasizes:

```text
MADE_TO_ORDER
→ Request Furniture
```

The Flutter theme must support that product presentation with the same premium treatment as the web.

Do not introduce styling that visually implies:

```text
request-only = disabled
```

---

# 48. Theme Generation Strategy

Phase 12.4 must decide/document how Phase 16.2 should consume portable tokens.

Preferred options:

```text
A. generated Dart constants from design-tokens.json

or

B. manually maintained narrow Flutter semantic adapter
   whose values are generated/verified against design-tokens.json
```

Choose based on current design-system tooling.

Do not build a complex code generator if the repository does not need one.

---

# 49. Prefer Simple Generation

If the existing token pipeline can emit Dart safely with a small deterministic transformation:

```text
document that option.
```

If not:

```text
do not introduce Node/Dart codegen machinery merely for elegance.
```

A small explicit adapter in Phase 16.2 may be preferable.

---

# 50. No Runtime JSON Token Loading

Future Flutter must NOT:

```text
bundle design-tokens.json
read JSON at runtime
construct ThemeData after parsing assets
```

for static brand tokens.

Use compile-time/static Dart values.

`design-tokens.json` is a build/design contract, not application runtime configuration.

---

# 51. Future Theme Structure Contract

Document a target such as:

```text
AppTheme
  ├── lightTheme
  ├── ColorScheme mapping
  ├── TextTheme mapping
  ├── component themes
  └── minimal extensions
```

Do not implement:

```text
darkTheme
```

unless separately approved later.

---

# 52. Theme Naming

Use brand-neutral implementation names such as:

```text
AppTheme
AppColorScheme
AppTypography
```

or repository equivalents.

Do not name code:

```text
NikeTheme
NikeColors
NikeSpacing
```

The Nike system is historical structural provenance, not consumer identity.

---

# 53. Token Naming

Future Dart tokens should preserve semantic meaning.

Prefer conceptually:

```dart
AppSemanticColors.canvas
AppSemanticColors.editorial
```

over:

```dart
Colors.cream1
Colors.brown7
```

where semantics matter.

Do not decide exact Dart API prematurely if not needed.

---

# 54. Flutter Admin Boundary

Do not assume the Flutter app will contain Admin UI.

The project identifies:

```text
Admin application
→ Next.js + MUI
```

The Flutter app is the customer mobile application.

Do not design Staff/Admin mobile theme variants.

---

# 55. Do Not Touch Backend/API

No changes to:

```text
backend/
docs/api/
Laravel
database
RBAC
auth
commerce rules
```

Phase 12.4 is design architecture only.

---

# 56. Documentation Updates

Update:

```text
frontend/design-system/DESIGN.md
```

only if Flutter mapping principles belong there.

Update:

```text
frontend/design-system/USAGE.md
```

with the Flutter consumption rule:

```text
tokens.css canonical
→ design-tokens.json portable representation
→ future static Flutter adapter
```

Do not duplicate the full token table.

---

# 57. Group L Phase Record

Update:

```text
phases/group-L-phases.md
```

to reflect:

```text
12.1 PASS
12.2 PASS
12.3 PASS
12.4 <current>
```

Also preserve the corrected distinction:

```text
12.3
→ MUI structure was implemented early

12.4
→ Flutter Material 3 structure is contract only

16.2
→ actual Flutter ThemeData implementation
```

This prevents the future agent from duplicating work.

---

# 58. ADR

Add the next repository-consistent design ADR if warranted.

Suggested decision:

```text
DESIGN-003 — Flutter Material 3 Consumes Shared Furniture Tokens
```

Record:

```text
tokens.css remains canonical

design-tokens.json is the portable synchronized representation

Flutter does not parse CSS

Flutter does not load token JSON at runtime

Material 3 is the framework mapping, not brand authority

ColorScheme uses explicit approved semantic values

no ColorScheme.fromSeed as brand authority

Young Serif remains display typography

utility type remains UI typography

ThemeExtension used only for semantics Material cannot represent

Material Icons are the default mobile icon family

actual ThemeData implementation remains Phase 16.2
```

Use the actual next ADR identifier in the repository.

---

# 59. No New Dependencies

Expected:

```text
dependencies = NONE
```

Do not run:

```text
flutter pub add
dart pub add
npm install
```

for Phase 12.4.

If design-system validation already uses existing Node tooling, reuse it.

---

# 60. Validation

Run the existing design-system validation suite.

At minimum verify:

```text
design-tokens.json parses

all Flutter-referenced semantic tokens exist

no MUI-only names leak into portable token contract

no Flutter-only values are added to tokens.css

no unresolved references

no duplicate semantic color roles

contrast remains valid

git diff --check
```

---

# 61. Cross-Platform Mapping Audit

Create a concise matrix for important semantics:

| Semantic role | Canonical token | Web/MUI | Future Flutter |
|---|---|---|---|
| Canvas | `--surface-canvas` | `background.default` | Material surface/background semantic |
| Paper | `--surface-paper` | `background.paper` | surface/paper semantic |
| Editorial | `--surface-editorial` | custom semantic | ThemeExtension or suitable M3 surface role |
| Primary text | `--text-primary` | `text.primary` | `onSurface` or equivalent |
| Primary action | `--action-primary` | `primary.main` | `ColorScheme.primary` |
| Brand accent | `--accent-brand` | brand semantic | extension/appropriate semantic |
| Display font | `--font-display` | display typography | display/headline roles |
| UI font | `--font-ui` | body/control typography | body/label/control roles |

Do not blindly copy these exact Material properties if current Flutter APIs/documentation establish a better semantic mapping later.

This matrix is an architectural guide.

---

# 62. No False Precision

Because Phase 16.2 may run against a newer Flutter SDK, avoid freezing volatile implementation details such as:

```text
exact constructor signatures
deprecated ThemeData field names
specific ThemeExtension generic boilerplate
```

unless required.

Phase 12.4 defines:

```text
semantics
ownership
mapping intent
constraints
```

Phase 16.2 resolves exact SDK APIs.

---

# 63. Phase 12.4 Completion Report

Return:

```text
Phase 12.4 status:
PASS / BLOCKED

Flutter implementation started:
NO

Flutter project modified:
NO / <explain>

Canonical token authority:
tokens.css

Portable Flutter source:
design-tokens.json

Runtime JSON token loading:
NO

Material 3 mapping contract:
PASS / FAIL

ColorScheme strategy:
PASS / FAIL

ColorScheme.fromSeed as authority:
NO

Dynamic system colors:
NO

Primary action:
#111111 semantic / FAIL

Brand accent:
#321E0F semantic / FAIL

Warm canvas:
#FCF4ED semantic / FAIL

Paper:
#FFFFFF semantic / FAIL

Editorial surface:
#F4E9DF semantic / FAIL

Dark theme added:
NO

Display typography:
Young Serif

UI typography:
<existing utility family>

Font assets installed:
NO

Material Icons policy:
PASS / FAIL

Spacing mapping:
PASS / FAIL

Shape mapping:
PASS / FAIL

Elevation mapping:
PASS / FAIL

Motion mapping:
PASS / FAIL

Accessibility:
PASS / FAIL

ThemeExtension policy:
PASS / FAIL

Cross-platform semantic matrix:
PASS / FAIL

Design-system validation:
<commands/results>

Dependencies added:
NONE

Backend/API changed:
NO

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

Phase 12.5:
READY / BLOCKED
```

---

# 64. STOP Condition

Phase 12.4 is PASS only when:

- Flutter's future Material 3 architecture is documented;
- `tokens.css` remains the only canonical token authority;
- `design-tokens.json` is explicitly the portable Flutter input;
- Flutter will not parse CSS;
- Flutter will not load token JSON at runtime;
- Material 3 does not become a second brand authority;
- explicit color mapping is preferred over `ColorScheme.fromSeed`;
- charcoal remains the primary action;
- brown remains a controlled brand accent;
- warm canvas, paper, editorial and inverse surfaces retain distinct meanings;
- Young Serif remains display typography;
- utility typography remains UI typography;
- spacing, radius, elevation and motion semantics are preserved;
- ThemeExtension is reserved only for semantics Material 3 cannot cleanly express;
- Material Icons are the default Flutter icon family;
- accessibility/mobile adaptation requirements are documented;
- no Flutter application code has been implemented;
- no new package has been installed;
- Group P Phase 16.2 remains the owner of actual `ThemeData` implementation;
- design-system validation passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 12.4 — PASS
Phase 12.5 — READY
```

Do not start Phase 12.5 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Execution Record — 2026-10-06

### 12.4 — Flutter Material 3 Theme Structure

**Status:** Complete

`frontend/design-system/flutter-material3.md` defines the future static Material 3 mapping from the canonical portable token contract. It does not create Flutter code, a Flutter project, dependencies, font assets, a dark theme, dynamic color, or runtime JSON token loading. Phase 16.2 remains the owner of actual `ThemeData` implementation.
