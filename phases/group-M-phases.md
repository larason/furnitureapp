# Phase 13.7 — Website Layout System

## Objective

Implement the reusable **Next.js + MUI website layout system** for SL Furnitures.

This phase establishes the structural shell within which later storefront pages will be built.

The layout should use a proven furniture-commerce information architecture inspired structurally by established furniture retailers such as Urban Ladder:

```text
utility/announcement region
        ↓
primary header
        ↓
category navigation
        ↓
main content
        ↓
footer
```

However:

> Urban Ladder is an approved **structural / information-architecture reference only**.

Do NOT copy:

- Urban Ladder branding;
- exact visual styling;
- colors;
- typography;
- dimensions;
- icons;
- promotional language;
- sale mechanics;
- navigation labels without mapping them to our taxonomy;
- page sections;
- component implementation;
- source code;
- interaction details;
- promotional density.

SL Furnitures' own Group L design system remains the visual authority.

The resulting shell must feel:

```text
architectural
warm
editorial
calm
crafted
material-led
spacious
premium but approachable
```

rather than:

```text
marketplace-like
discount-heavy
dashboard-like
generic AI storefront
```

---

# 1. Phase Scope

Implement only:

```text
Phase 13.7 — Layout System
```

This phase owns the reusable structural website shell:

```text
root/site shell composition
header structure
brand/logo region
search entry-point structure
account entry-point structure
desktop category-navigation structure
mobile navigation structure
main-content landmark
content-width/container primitives
section-width/layout primitives
footer structure
desktop/mobile shell adaptation
sticky/header behavior if justified
layout-level accessibility structure
```

It does NOT own final storefront content.

---

# 2. Do Not Start Later Phases

Do NOT begin:

```text
13.8 — Error/loading/not-found handling
13.9 — Responsive foundation

14.1 — Homepage
14.2 — Category pages
14.3 — Product listing
14.4 — Product detail
14.5 — Search
14.6 — Filters/sorting
14.7 — SEO metadata
14.8 — Structured data
14.9 — Sitemap/robots
14.10 — Internal linking
14.11 — Image/performance optimization
```

Also do not start customer feature phases.

---

# 3. Read Authorities Before Coding

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
└── relevant reference catalog

frontend/web/
├── app/
├── theme/
├── lib/api/
├── ROUTING.md
├── package.json
├── tsconfig.json
└── next.config.*

docs/
├── VISION.md
├── decisions.md
└── domain/business-rules.md

phases/group-M-phases.md
```

Inspect the actual current root layout and provider boundary before making changes.

Do not recreate infrastructure that already exists.

---

# 4. Git Workflow

Before ANY Git command:

```text
locate and read:
git-workflow-and-versioning
```

Follow it exactly.

Preserve unrelated owner changes.

Never commit:

```text
.env
.env.local
credentials
tokens
secrets
local SDK state
```

Stage only Phase 13.7 work.

---

# 5. Preserve Completed Foundations

The following are already complete:

```text
13.1 Next.js setup
13.2 TypeScript
13.3 MUI integration
13.4 Theme integration
13.5 API client
13.6 Routing conventions
```

Do not:

```text
rerun create-next-app
replace MUI integration
recreate the theme
duplicate providers
create another API client
change routing conventions casually
```

---

# 6. Architectural Direction

Use this dependency hierarchy:

```text
Root Next.js layout
        ↓
Site shell
        ↓
Header / Navigation / Main / Footer
        ↓
Layout primitives
        ↓
future page composition
        ↓
future commerce components
```

Do not reverse it.

Pages should eventually consume the layout system.

The layout system must not know individual page implementations.

---

# 7. Structural Reference Decision

Record this durable rule:

> Established furniture-commerce websites may inform structural information architecture, but SL Furnitures' tokens, typography, components, accessibility rules, photography philosophy, and interaction principles remain authoritative.

For the shell, the approved structural grammar is:

```text
┌──────────────────────────────────────────────────────────────┐
│ Optional utility / announcement region                      │
├──────────────────────────────────────────────────────────────┤
│ Brand                Search                     Account      │
├──────────────────────────────────────────────────────────────┤
│ Furniture category navigation                               │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│                       MAIN CONTENT                           │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                         FOOTER                               │
└──────────────────────────────────────────────────────────────┘
```

This is structural guidance, not a pixel specification.

---

# 8. Root Layout

Inspect:

```text
frontend/web/app/layout.tsx
```

Preserve it as a Server Component unless an unavoidable framework requirement proves otherwise.

Do NOT add:

```tsx
"use client";
```

to the root layout merely because navigation contains interactive descendants.

Client boundaries belong lower in the tree.

---

# 9. Provider Boundary

Preserve the existing minimal MUI provider architecture established in 13.3–13.4.

Do not create:

```text
SiteProvider
LayoutProvider
NavigationProvider
HeaderProvider
```

unless a genuine runtime requirement exists.

Static shell composition does not require global React state.

---

# 10. Site Shell

Introduce one clear site-shell composition.

Conceptually:

```tsx
<SiteShell>
  <SiteHeader />
  <main>{children}</main>
  <SiteFooter />
</SiteShell>
```

Exact naming should follow repository conventions.

Avoid competing concepts such as:

```text
AppShell
WebsiteShell
PublicShell
MainShell
StoreShell
```

all representing the same thing.

Choose one authority.

---

# 11. Semantic Landmarks

The rendered shell should have meaningful structural landmarks:

```html
<header>
<nav>
<main>
<footer>
```

where appropriate.

Do not construct the site entirely from anonymous:

```html
<div>
```

elements.

---

# 12. Main Landmark

There should be exactly one primary:

```html
<main>
```

for normal storefront pages.

Pages inserted into the shell should not need to recreate the global main landmark.

---

# 13. Skip-to-Main

Phase 12.7 established accessibility requirements.

Implement the structural skip mechanism now because the layout system owns the main landmark.

Provide a keyboard-accessible:

```text
Skip to main content
```

link.

It should:

- be available at the beginning of the navigation sequence;
- become visibly apparent when focused;
- target the primary main-content landmark;
- use approved focus tokens;
- not permanently clutter the visual design.

Do not implement it as a JavaScript click handler.

Use native anchor semantics.

---

# 14. Header Architecture

The desktop header should support three conceptual levels where appropriate:

```text
optional utility/announcement
primary header
category navigation
```

Do not make all three visually equally dominant.

Hierarchy should be clear.

---

# 15. Utility / Announcement Region

Treat the utility region as optional infrastructure.

Potential future content may include:

```text
delivery/service information
showroom/contact information
made-to-order message
```

but Phase 13.7 must NOT invent promotional campaigns.

Do not hard-code:

```text
50% OFF
SALE ENDS TONIGHT
FLASH SALE
FREE SHIPPING TODAY
```

unless actual business requirements later provide them.

If there is no approved content, the architecture may support the region without rendering meaningless filler.

---

# 16. Primary Header

Desktop structural hierarchy should support:

```text
Brand/logo
Search entry point
Account entry point
```

Do not add inactive commerce controls merely because conventional ecommerce headers contain them.

---

# 17. Official Logo

Use:

```text
designs/brandlogo.png
```

as the brand identity authority if the shell requires the actual brand mark.

Do NOT:

```text
retype the wordmark using Young Serif
trace the logo
recolor it
redraw it as SVG
approximate it with CSS
replace it with generic text
```

Preserve its visual integrity.

---

# 18. Logo Navigation

The logo should semantically navigate to:

```text
/
```

using an internal link.

Provide an accessible name where needed.

Do not make the logo a button.

---

# 19. Logo Sizing

Do not hard-code arbitrary logo dimensions based on guesswork.

Choose a restrained shell size compatible with:

```text
header height
visual balance
mobile adaptation
official aspect ratio
```

Use the layout/design token system wherever applicable.

Do not distort the image.

---

# 20. Search Position

The primary header should provide a prominent search entry point.

Furniture discovery benefits strongly from search.

However:

```text
Phase 14.5
```

owns actual search functionality/results.

Phase 13.7 may implement only the **shell-level search affordance** necessary for layout composition.

---

# 21. Search Scope

Do NOT implement:

```text
search API requests
autocomplete
search suggestions
recent searches
search history
search results
debouncing
predictive search
```

during 13.7.

---

# 22. Search Semantics

If the header contains an actual search form rather than merely a navigation entry point, it must use semantic:

```html
<form role="search">
```

or equivalent appropriate semantics.

But do not create fake functionality.

If search submission behavior belongs to Phase 14.5, prefer a safe structural entry point rather than a misleading non-functional form.

---

# 23. Search Destination

The canonical future search route is:

```text
/search
```

with:

```text
?search=<term>
```

according to the Phase 13.6 routing authority.

Do not introduce:

```text
?q=
?query=
?keyword=
```

---

# 24. Account Entry Point

The header may provide an account entry point consistent with the reserved:

```text
/account
```

namespace.

Do not implement authentication behavior.

Do not integrate Clerk.

Do not determine signed-in state during this phase.

If an account link would currently lead to an unimplemented route, do not expose a broken active navigation link merely to fill header space.

Support the structural slot cleanly.

---

# 25. No Cart Icon

The current production policy is request-first.

Therefore do NOT add:

```text
cart icon
cart count
mini-cart
shopping bag
checkout shortcut
```

to the site shell.

The routing authority explicitly keeps transactional commerce inactive.

---

# 26. No Wishlist Icon

Do not add wishlist/favourites functionality merely because furniture websites commonly have it.

It is not part of the current approved website scope.

---

# 27. Header Icon Policy

Web icons must use:

```text
@mui/icons-material
```

only.

Do not add:

```text
lucide-react
react-icons
Heroicons
Font Awesome
custom decorative SVG icons
emoji UI icons
```

Icon-only interactive controls require accessible names.

Decorative icons should be hidden from assistive technology.

---

# 28. Desktop Category Navigation

Provide structural support for category-led furniture navigation.

The top-level navigation should reflect the actual furniture taxonomy rather than copied retailer categories.

Inspect the authoritative category taxonomy.

Likely high-level concepts include existing project categories such as:

```text
Living Room
Bedroom
Dining Room & Kitchen
Office
Outdoor
Kids
Entryway & Hallway
Lighting
Decor
Storage
Hybrid & Multi-purpose
```

Use actual authoritative taxonomy.

Do not blindly render every taxonomy node into the top navigation.

---

# 29. Navigation Density

A premium header should not become a database dump.

Determine a sustainable top-level navigation strategy.

If the taxonomy contains more items than comfortably fit:

```text
prioritize core categories
+
provide a controlled "More" / discovery mechanism
```

only if consistent with the approved IA.

Do not shrink text to force everything into one row.

Do not wrap category navigation onto two chaotic lines.

---

# 30. Category Navigation Is Data-Driven Eventually

Do not hard-code an entirely separate frontend taxonomy that can drift from Laravel.

However, Phase 13.7 does NOT need to implement catalog API fetching merely to establish shell geometry.

If live category navigation belongs to Group N, establish the component/layout contract now and defer data integration.

Do not create fake category IDs/slugs.

---

# 31. Category Links

Once real category links are implemented, they must use:

```text
/categories/[slug]
```

with Laravel-returned slugs.

Never generate slugs from labels in the browser.

---

# 32. Mega Menu Architecture

The desktop layout may support a future furniture-specific mega menu.

Conceptually:

```text
LIVING ROOM
────────────────────────────────────────────

Seating            Tables            Storage
Sofas              Coffee Tables     TV Units
Lounge Chairs      Side Tables       Cabinets
Accent Chairs      Console Tables    Shelving

                              Editorial image
                              Explore Living →
```

This is a **layout capability**, not permission to build the final data-driven menu now.

---

# 33. Mega Menu Scope

If a minimal generic mega-menu primitive is necessary for layout validation, it may be implemented.

But do NOT:

```text
fetch category hierarchy
invent merchandising images
create final category copy
build promotional menu cards
create retailer-specific menu content
```

during Phase 13.7.

Prefer structural primitives with test fixtures/reference data where needed.

---

# 34. Mega Menu Interaction

Any implemented dropdown/mega-menu behavior must support:

```text
keyboard access
focus management
Escape dismissal
pointer interaction
logical tab order
visible focus
```

Do not implement hover-only navigation.

---

# 35. Avoid Hover Traps

A user moving the pointer between navigation trigger and menu content should not encounter a fragile tiny hover gap.

If hover behavior is implemented, keyboard/click behavior must remain first-class.

Do not make navigation dependent exclusively on pointer hover.

---

# 36. Mobile Header

Mobile should use the same visual language but not mechanically shrink the desktop header.

Conceptually:

```text
┌───────────────────────────────┐
│ Menu    Logo      Search/User │
└───────────────────────────────┘
```

Exact composition should follow content priority and available width.

---

# 37. Mobile Navigation

Desktop category navigation should collapse into a mobile navigation mechanism.

Likely:

```text
menu trigger
→ drawer/sheet
→ category navigation
→ utility links
```

Use MUI primitives where appropriate.

Do not invent a separate mobile information architecture.

---

# 38. Mobile Drawer

If a drawer is implemented:

- use MUI's established primitive rather than custom overlay infrastructure;
- preserve keyboard focus;
- close on Escape;
- restore focus appropriately;
- label the drawer/navigation;
- provide an explicit close mechanism;
- avoid nested interaction traps.

---

# 39. Mobile Category Hierarchy

Do not display the entire taxonomy as one enormous flat list.

If hierarchy is needed, use controlled disclosure.

But do not overbuild complex nested navigation before actual category data integration.

---

# 40. Mobile Search

Search must remain easy to discover on mobile.

Do not hide it three levels deep inside the menu.

However, final search interaction belongs to Phase 14.5.

---

# 41. Sticky Header

Evaluate whether a restrained sticky header improves furniture browsing.

If implemented:

```text
position: sticky
```

is preferred over complex scroll-listener JavaScript.

Avoid:

```text
header shrinks on scroll
logo morphs
nav animates away
parallax header
scroll direction detection
```

unless later explicitly approved.

---

# 42. Sticky Elevation

A sticky header may gain subtle separation when necessary.

Use approved elevation/border/surface tokens.

Do not add:

```text
heavy shadow
blurred glass
backdrop-filter spectacle
```

---

# 43. Header Heights

Do not invent dozens of arbitrary heights.

Header geometry should derive from:

```text
content
spacing tokens
control sizing
logo proportions
```

rather than magic numbers.

If fixed/min heights are needed, use the smallest coherent set.

---

# 44. Main Content Region

The layout system must provide a predictable content region.

Pages should not each invent their own:

```text
max-width
horizontal padding
center alignment
```

---

# 45. Content Container Primitive

Create a reusable content-width primitive if one does not already exist.

Conceptually:

```text
<ContentContainer>
  {children}
</ContentContainer>
```

It should own:

```text
maximum readable/site width
horizontal gutters
centering
responsive gutter behavior
```

Use repository naming conventions.

---

# 46. Container Ownership

The global shell should NOT force every page section into the same width.

Furniture sites require both:

```text
contained content
full-bleed editorial imagery
```

Therefore the layout system should support both intentionally.

---

# 47. Full-Bleed Composition

Support future composition such as:

```text
FULL BLEED HERO
────────────────────────────

       contained text

────────────────────────────

contained product grid

────────────────────────────

FULL BLEED EDITORIAL IMAGE
```

without pages resorting to negative-margin hacks.

Do NOT build the hero itself.

---

# 48. Section Primitive

If justified, establish a lightweight section layout primitive controlling:

```text
vertical section spacing
optional content width
semantic section composition
```

Do not make a giant:

```text
Section
```

component with 25 variants.

Keep it structural.

---

# 49. Page Container vs Section Container

Avoid ambiguity.

If both concepts exist, clearly define:

```text
Page/content container
→ horizontal site geometry

Section
→ vertical composition / semantic grouping
```

Do not let both independently set arbitrary widths and padding.

---

# 50. Grid Foundation

The layout system may establish generic grid primitives/configuration needed by future pages.

Do NOT build the product grid.

A generic responsive layout grid can define:

```text
columns
gaps
alignment
```

without knowing product cards.

---

# 51. Product Grid Ownership

Actual:

```text
product card count
product grid breakpoints
listing behavior
filter sidebar relationship
```

belong to Phase 14.3 / 14.6.

Do not prematurely encode them into the global shell.

---

# 52. Editorial Layout Capability

The layout system should allow asymmetric editorial compositions later:

```text
image + text
text + image
large image + narrow copy
```

without creating one-off page CSS.

Do not implement actual editorial homepage sections.

---

# 53. Width Philosophy

Avoid an overly narrow SaaS/dashboard container.

Furniture imagery needs room.

Likewise avoid uncontrolled edge-to-edge text.

The system should support:

```text
wide visual canvas
+
controlled readable text measure
```

as separate concepts.

---

# 54. Text Measure

If a reusable readable-text measure is needed, establish it structurally.

Long editorial copy should not stretch across the full desktop canvas.

Do not invent typography sizes; Group L owns typography.

---

# 55. Layout Spacing

All shell spacing must use approved design tokens/theme mappings.

Do not introduce arbitrary values such as:

```text
19px
37px
53px
```

because they happen to look good.

If an approved token exists, use it.

---

# 56. MUI `sx` Policy

Follow `COMPONENTS.md`.

Do not turn every layout element into:

```tsx
sx={{
  ...
}}
```

with one-off visual values.

Use theme/token-backed structural styling.

Local `sx` is acceptable only under the established policy.

---

# 57. Breakpoints

Use the existing canonical breakpoints mapped through the MUI theme.

Do NOT introduce a second set such as:

```text
mobile = 700
tablet = 950
desktop = 1234
```

unless those are already canonical tokens.

---

# 58. Phase 13.9 Boundary

Phase 13.7 must make the shell structurally responsive enough to function.

But Phase:

```text
13.9 — Responsive foundation
```

still owns broader responsive-system validation and conventions.

Therefore:

```text
13.7
→ responsive shell implementation

13.9
→ systematic responsive behavior audit/foundation
```

Do not consume all of 13.9's scope here.

---

# 59. Footer Architecture

Implement a reusable footer structure appropriate for a furniture business.

Conceptually support groups such as:

```text
Furniture
Services
Help
About
Contact
Legal
```

But only expose links/routes that actually exist or are deliberately safe placeholders without broken navigation.

---

# 60. Footer Restraint

Do not fill the footer with generic ecommerce boilerplate just because competitors have it.

Do NOT invent:

```text
Rewards
Affiliate Program
Investor Relations
Gift Cards
Careers
Press
Trade Program
```

without approved business requirements.

---

# 61. Footer Category Links

If actual category links are eventually rendered, use the authoritative category routes.

Do not generate fake category slugs.

---

# 62. Footer Services

The architecture should support legitimate business concepts such as:

```text
Made to Order
Furniture Requests
General Enquiries / Contact
```

because these exist in the project.

Do not turn MADE_TO_ORDER into an error/help link.

It is a first-class offering.

---

# 63. Footer Contact Information

Do not invent:

```text
phone numbers
email addresses
showroom addresses
opening hours
social accounts
```

If business contact information is not authoritative in the repository, provide the structural slot or omit it.

Never fabricate business details.

---

# 64. Newsletter

Do not add a newsletter subscription form unless newsletter functionality is an approved requirement.

A decorative email input that does nothing is prohibited.

---

# 65. Social Media

Do not add generic Instagram/Facebook/Pinterest icons without authoritative business URLs.

Do not use `href="#"`.

---

# 66. Legal Links

Do not create broken:

```text
Privacy
Terms
Returns
```

links merely to make the footer look complete.

If those pages do not exist, either omit them or render appropriate non-link structural content only if justified.

---

# 67. Footer Responsive Behavior

Desktop may use multi-column groups.

Mobile may collapse/reflow them.

Do not automatically use accordion behavior unless it genuinely improves the layout and accessibility.

If accordion behavior is used, use MUI semantics and keyboard support.

---

# 68. Background Surfaces

Use the established semantic surfaces.

Likely hierarchy:

```text
site canvas
→ warm ivory

product/content relief
→ paper

editorial section
→ editorial surface

inverse region
→ charcoal where justified
```

Do not make every section alternate beige/white/brown.

Surface changes should communicate composition.

---

# 69. Footer Surface

Choose footer treatment from approved semantic surfaces.

A restrained inverse footer may be appropriate if supported by the design system, but do not introduce a new footer-specific color.

Verify text/icon/focus contrast.

---

# 70. Brown Accent

Deep brown remains a controlled brand accent.

Do NOT make:

```text
entire header brown
all nav links brown
all footer headings brown
all icons brown
all borders brown
```

The visual system should remain charcoal-led and photography-led.

---

# 71. Header Surface

Prefer a calm surface that supports furniture photography and navigation clarity.

Do not use:

```text
gradient header
glassmorphism
blurred translucent nav
ornamental texture
```

---

# 72. Borders

Use subtle token-backed borders only where structural separation needs them.

Do not outline every header region and footer column.

Whitespace should do much of the work.

---

# 73. Elevation

Follow Group L:

```text
flat first
```

Header/footer/container surfaces should not become floating cards.

Elevation is reserved for genuine layers such as:

```text
mobile drawer
mega menu
popover
```

---

# 74. Radius

Do not wrap:

```text
header
footer
main content
navigation bar
page container
```

in giant rounded cards.

Architectural page regions should remain structurally clean.

---

# 75. Motion

Navigation motion must remain quiet.

Appropriate:

```text
short fade
subtle opacity
controlled menu transition
underline/color transition
```

Avoid:

```text
bouncing
spring animation
navigation scaling
large slide spectacle
rotating icons
```

Honor reduced motion.

---

# 76. Photography Boundary

The shell should provide room for photography without introducing placeholder stock photography.

Do not download random furniture imagery during Phase 13.7.

Group N will own actual page imagery/content.

---

# 77. Accessibility Authority

Follow:

```text
frontend/design-system/ACCESSIBILITY.md
```

The layout system must establish accessibility structurally rather than trying to repair it later.

---

# 78. Landmark Audit

Verify:

```text
header landmark
navigation landmark(s)
main landmark
footer landmark
```

are meaningful and not unnecessarily duplicated.

If multiple `nav` elements exist, provide useful accessible labels where needed.

---

# 79. Navigation Labels

For example:

```text
Primary navigation
Furniture categories
Footer navigation
```

may be appropriate.

Do not over-label every container.

---

# 80. Heading Ownership

The global shell should not introduce a fake page `<h1>`.

Each future page owns its primary heading.

Header/footer headings must not disrupt logical page heading hierarchy.

---

# 81. Keyboard Navigation

Verify keyboard users can:

```text
reach skip link
reach logo/home
reach navigation
operate any menu
reach search entry
reach account entry
reach main content
reach footer
```

without pointer input.

---

# 82. Focus Visibility

Every interactive shell element must retain visible focus.

Never use:

```css
outline: none;
```

without the approved replacement.

Use the established focus token semantics.

---

# 83. Focus Order

DOM order should broadly match visual order.

Do not use CSS ordering tricks that cause keyboard focus to jump unpredictably.

---

# 84. Touch Targets

Mobile controls should meet the established accessibility target-size requirements.

Do not create tiny:

```text
menu
search
close
account
```

icon targets merely to preserve visual minimalism.

---

# 85. Zoom/Reflow

The shell must remain usable under text zoom and browser zoom.

Do not use fixed-height navigation regions that clip text when fonts enlarge.

Use:

```text
min-height
content-driven sizing
```

where appropriate.

---

# 86. Reduced Motion

Any shell transitions must respect:

```text
prefers-reduced-motion
```

through the established design-system approach.

Do not create a second reduced-motion mechanism.

---

# 87. Mobile Menu Scroll

If the mobile navigation exceeds viewport height:

```text
menu content must scroll
```

without trapping the page or hiding close controls.

Account for safe viewport behavior.

---

# 88. Body Scroll

If MUI Drawer/Modal handles body scroll locking, use its established behavior.

Do not implement a custom global:

```text
document.body.style.overflow
```

mechanism unless unavoidable.

---

# 89. Z-Index

Use the established theme/token z-index hierarchy.

Do not introduce:

```text
z-index: 999999
```

for navigation.

---

# 90. Server/Client Boundaries

Keep static shell components as Server Components where possible.

Likely server-capable:

```text
SiteShell
Footer
logo composition
static header composition
content container
section primitives
```

Client boundaries should be introduced only for genuinely interactive behavior such as:

```text
mobile drawer
interactive mega menu
```

---

# 91. Do Not Make Entire Header Client-Side Automatically

If only the mobile menu requires state, isolate that behavior.

Preferred conceptual architecture:

```text
SiteHeader            Server where practical
 ├── Brand
 ├── DesktopNav
 └── MobileNavTrigger/Navigation  Client boundary
```

Exact implementation may vary with MUI requirements.

Keep the client surface small.

---

# 92. No Global Navigation State

Do not introduce Redux/Zustand/Context merely for:

```text
menu open
mega menu open
```

Local component state is sufficient.

Expected new dependencies:

```text
NONE
```

---

# 93. No API Fetching in Global Layout Without Need

Do not make every website request block on unnecessary API calls from the root layout.

If live category navigation would require catalog fetching, assess whether it belongs in Group N.

The global shell should remain robust and cache-friendly.

---

# 94. No Auth Fetching Yet

Do not call Clerk or Laravel customer endpoints from the layout.

Authenticated account state belongs to later phases.

---

# 95. No Search API Fetching

The header must not query Laravel during 13.7.

---

# 96. No Product API Fetching

Do not fetch featured products, categories, promotions, or recommendations from the root layout.

---

# 97. Layout Primitive API Discipline

Layout primitives should have narrow APIs.

Good conceptual examples:

```tsx
<ContentContainer>
<SiteSection>
```

Avoid:

```tsx
<UniversalLayout
  productGrid
  hero
  category
  inverse
  marketing
  checkout
  dashboard
  editorial
  ...
/>
```

---

# 98. Semantic Props

Props should describe layout meaning rather than arbitrary CSS values.

Prefer conceptually:

```text
width="wide"
surface="canvas"
```

only if these variants are actually justified by the design contract.

Avoid:

```text
paddingTop={37}
maxWidth={1372}
borderRadius={13}
```

---

# 99. Escape Hatch Discipline

Do not expose unrestricted styling props merely to make primitives flexible.

The point of the layout system is to prevent future page-level drift.

Follow the component-convention escape-hatch policy.

---

# 100. Composition Over Configuration

Prefer small composable primitives rather than one giant configurable layout engine.

For example:

```text
SiteShell
ContentContainer
SiteSection
```

is preferable to a 50-prop page builder.

---

# 101. Reference Catalog

Update the existing design-system/reference catalog only if needed to demonstrate layout primitives or shell states.

Do not build a second Storybook-like environment.

Any fixture must remain:

```text
token-driven
non-production
clearly demonstrative
```

---

# 102. Layout Reference Fixtures

If useful, demonstrate:

```text
desktop shell geometry
mobile shell geometry
contained vs full-bleed sections
footer grouping
focus state
mobile drawer state
```

Do not turn the reference fixture into the homepage.

---

# 103. No Fake Product Grid

A few neutral blocks may be used to demonstrate layout geometry.

Do not create realistic product cards or catalogue content in 13.7.

Commerce component/page ownership remains later.

---

# 104. Existing Root Placeholder

Do not convert:

```text
app/page.tsx
```

into the final homepage.

It may be minimally adjusted only if necessary to validate shell composition.

The final homepage remains Phase 14.1.

---

# 105. Layout Integration

The site shell should integrate at the appropriate App Router layout level.

Do not create placeholder route files merely to test it.

The current `/` placeholder should naturally render within the shell.

---

# 106. Route Groups

Phase 13.6 allowed a future route group only for a genuine layout boundary.

Evaluate whether one is actually necessary now.

If the entire current website uses the same public shell, the root layout may be sufficient.

Do not introduce:

```text
(marketing)
(store)
(catalog)
```

solely because they look architecturally sophisticated.

---

# 107. Future Account Layout

Do not implement an account-specific layout now.

The layout system may be composable enough for one later, but `/account` is not implemented.

---

# 108. Future Auth Layout

Do not create:

```text
(auth)/layout.tsx
```

during this phase merely because sign-in/sign-up are reserved.

---

# 109. Future Product Layout

Do not create:

```text
products/layout.tsx
```

unless there is an actual shared layout requirement once Group N starts.

---

# 110. Header Navigation Content Authority

Do not create a permanent hard-coded taxonomy copy inside a layout component without documenting ownership.

If temporary fixture navigation is needed for layout testing, isolate it clearly as fixture/demo data.

The future production category navigation must derive from authoritative catalog data or an explicitly approved navigation configuration.

---

# 111. Navigation Configuration

If a small static configuration is genuinely needed for non-category shell links, keep it typed and intentional.

Do not build a CMS/navigation framework.

---

# 112. Link Integrity

No production shell element should render:

```text
href="#"
```

as a fake destination.

No broken placeholder navigation.

If a destination is not implemented:

```text
omit the active link
or render non-interactive structural content
```

according to context.

---

# 113. External Links

External destinations should use ordinary anchor semantics.

Use:

```text
target="_blank"
```

only when there is a concrete UX reason.

Do not automatically force external links into new tabs.

---

# 114. Current Route Highlighting

Do not introduce complex active-route infrastructure yet.

If category navigation does not exist as real routes yet, active state can wait.

When implemented later, active state must not rely on color alone.

---

# 115. Footer Copyright

If copyright text is included, avoid hard-coding a year that will become stale if a simple safe strategy exists.

Do not require a Client Component merely to compute the year.

Server rendering can handle it.

---

# 116. Performance

The shell appears on essentially every page.

Keep it lean.

Avoid:

```text
large client bundles
animation libraries
unnecessary context providers
large navigation JSON in client code
heavy icon imports
duplicate font loading
```

---

# 117. MUI Icon Imports

Import only icons actually used.

Do not import the entire icon namespace.

---

# 118. Logo/Image Handling

If using Next.js Image for the logo, follow the installed Next.js image API.

Do not introduce image optimization policy for product photography; Phase 14.11 owns broader image/performance work.

---

# 119. CLS

The header/logo should have stable geometry to avoid obvious layout shift.

Provide known dimensions/aspect handling where appropriate.

---

# 120. Hydration

Interactive navigation must not produce avoidable server/client markup differences.

No browser-only condition should alter the initial shell structure unpredictably.

---

# 121. Mobile Detection

Do NOT use:

```text
window.innerWidth
```

during render to decide between desktop/mobile shells.

Use responsive CSS/MUI breakpoint behavior.

If behavior genuinely requires client media queries, isolate them carefully and avoid hydration mismatch.

---

# 122. Duplicate Navigation

If both desktop and mobile structures exist in the DOM for CSS breakpoint switching, ensure:

```text
hidden content is truly unavailable appropriately
focus does not enter invisible navigation
accessibility tree does not become confusing
```

Prefer clean responsive composition.

---

# 123. SEO Structural Semantics

Although metadata is Group N, the shell should use semantic HTML that supports future crawlability.

Do not make core navigation dependent on JavaScript-only click handlers.

---

# 124. No Structured Data

Do not add:

```text
Organization JSON-LD
WebSite JSON-LD
Breadcrumb JSON-LD
```

during 13.7.

Phase 14.8 owns structured data.

---

# 125. No Sitemap/Robots

Do not add:

```text
sitemap.ts
robots.ts
```

during this phase.

---

# 126. No Final Internal-Linking Strategy

Header/footer navigation naturally creates structural links.

But Phase 14.10 owns broader SEO/internal-linking strategy.

Do not attempt to solve it here.

---

# 127. Design Review

Review the implemented shell against these questions:

```text
Does it feel like a furniture website?

Does photography have room to dominate later?

Is the header immediately understandable?

Can users discover categories easily?

Is search prominent without dominating?

Is MADE_TO_ORDER structurally first-class?

Does the shell avoid inactive transactional commerce?

Does desktop feel spacious?

Does mobile feel intentionally composed rather than compressed?

Does the footer provide useful orientation without generic filler?

Does it look like SL Furnitures rather than Urban Ladder?

Does it avoid generic AI storefront patterns?
```

All should be satisfactorily answered.

---

# 128. Anti-AI-Slop Audit

Explicitly audit against:

```text
excessive rounded cards
random pill controls
decorative gradients
glassmorphism
blur
floating panels
oversized shadows
decorative blobs
random icons beside headings
excessive brown
badge clutter
centered-everything layouts
fake statistics
generic "premium quality" filler
sale countdowns
unapproved promotional strips
```

None should appear merely to make the shell look "designed."

---

# 129. Urban Ladder Structural Audit

Report which structural ideas were adopted.

For example:

```text
furniture-oriented category navigation
prominent discovery/search
multi-level header hierarchy
large catalogue-oriented navigation capability
structured footer
```

Then explicitly report what was NOT copied:

```text
visual styling
brand identity
promotional density
sale mechanics
exact menu content
exact dimensions
exact typography
exact component appearance
```

---

# 130. Screenshot / Visual Verification

Because this phase is visual, run the application and inspect at least representative viewport classes.

At minimum:

```text
desktop
tablet/narrow desktop
mobile
```

Use the project's available browser/runtime workflow.

Do not declare the layout visually successful from TypeScript/build alone.

Check:

```text
header proportions
navigation wrapping
logo distortion
horizontal overflow
main width
footer composition
focus visibility
mobile navigation
drawer overflow
```

---

# 131. Narrow Width Stress Test

Test a narrow mobile viewport.

Ensure:

```text
no horizontal page scroll
no clipped logo
no overlapping controls
no inaccessible menu trigger
no off-screen close button
```

---

# 132. Zoom Stress Test

Test meaningful browser zoom/text scaling.

Ensure the header does not collapse or clip critical navigation.

---

# 133. Keyboard Smoke Test

Manually verify:

```text
Tab
Shift+Tab
Enter
Space where applicable
Escape for overlays
```

through shell navigation.

Verify skip-to-main.

---

# 134. Reduced-Motion Smoke Test

Verify implemented navigation transitions remain usable with reduced motion enabled.

---

# 135. Automated Tests

Use the existing frontend test infrastructure.

Add focused tests where appropriate for:

```text
site-shell landmarks
main landmark
skip-link target
logo/home link
navigation accessible names
no forbidden cart/checkout links
mobile navigation accessibility
drawer semantics if implemented
layout primitive contract
```

Do not install another test runner.

---

# 136. Request-First Regression

Add or run a regression check ensuring the production shell does NOT expose:

```text
/cart
/checkout
/payment
/order-confirmation
```

through active navigation.

---

# 137. Routing Regression

Ensure any links introduced conform to:

```text
frontend/web/ROUTING.md
```

Do not introduce undocumented website routes.

---

# 138. API Client Regression

Phase 13.5 should remain untouched.

Run its existing tests where practical.

Expected:

```text
API client changes:
NONE
```

---

# 139. Theme Regression

Run the existing theme contract tests.

The shell must consume the theme rather than changing it casually.

---

# 140. Design-System Regression

Run existing:

```text
token/reference validation
component convention validation
accessibility checks
icon-policy audit
```

where available.

---

# 141. TypeScript

Run the actual repository TypeScript/typecheck command.

Must pass.

---

# 142. ESLint

Run the actual repository lint command.

Must pass.

---

# 143. Production Build

Run:

```text
the actual production build command from package.json
```

Must pass.

Do not invent a command name.

---

# 144. `git diff --check`

Must pass.

---

# 145. Dependencies

Expected:

```text
NONE
```

MUI and MUI Icons already exist.

Do not add:

```text
navigation library
animation library
CSS framework
icon library
layout library
carousel library
```

---

# 146. Likely Files

Inspect existing organization first.

Potential additions may resemble:

```text
frontend/web/components/layout/
├── site-shell.tsx
├── site-header.tsx
├── site-footer.tsx
├── content-container.tsx
├── site-section.tsx
└── mobile-navigation.tsx
```

Do NOT mechanically create all of these.

Only create components justified by actual responsibilities.

Possible modifications:

```text
frontend/web/app/layout.tsx
frontend/AGENTS.md
phases/group-M-phases.md
docs/decisions.md
```

Do not create Group N pages.

---

# 147. ADR

Add an ADR only if Phase 13.7 establishes a material architectural decision not already captured.

A reasonable candidate could document:

```text
WEB-002 — Public Website Shell and Layout Composition
```

if needed.

Potential durable decisions:

```text
furniture-commerce structural IA
SL Furnitures visual authority
server-first shell
contained + full-bleed composition
request-first header
category navigation strategy
minimal client interaction boundary
```

Do not add an ADR solely because previous phases had one.

---

# 148. AGENTS Guidance

Update `frontend/AGENTS.md` only for durable rules.

Potential durable rules:

```text
use canonical site shell
do not recreate page containers
do not bypass layout tokens
no cart/checkout shell controls while request-first
no fake links
no unauthorized icon libraries
Server Components by default
interactive nav boundaries stay small
Urban Ladder is structural reference only
```

Do not duplicate this entire phase prompt.

---

# 149. Phase Record

Update:

```text
phases/group-M-phases.md
```

with:

```text
Phase 13.7
status
shell architecture
layout primitives
header strategy
navigation strategy
mobile strategy
footer strategy
accessibility implementation
visual verification
scope boundaries
validation
```

---

# 150. Completion Report

Return:

```text
Phase 13.7 status:
PASS / BLOCKED


SITE SHELL

Canonical shell:
<path>

Root layout:
<path>

Root layout remains Server Component:
YES / NO

Main landmark:
PASS / FAIL

Skip-to-main:
PASS / FAIL

Duplicate providers:
NONE / <explain>


HEADER

Header:
<path>

Official logo used:
YES / NO / NOT RENDERED <reason>

Logo links to /:
YES / NO

Primary header structure:
<summary>

Search affordance:
<summary>

Search functionality implemented:
NO / FAIL

Account affordance:
<summary>

Clerk/auth behavior implemented:
NO / FAIL

Cart control:
NONE / FAIL

Wishlist control:
NONE / FAIL

Unauthorized icon libraries:
NONE / FAIL


CATEGORY NAVIGATION

Desktop navigation:
<summary>

Taxonomy authority:
<source/strategy>

Hard-coded duplicate production taxonomy:
NO / <explain>

Mega-menu capability:
<implemented/deferred + reason>

Keyboard accessible:
PASS / NOT IMPLEMENTED

Hover-only behavior:
NO / FAIL


MOBILE

Mobile header:
<summary>

Mobile navigation:
<summary>

Drawer/sheet:
<implementation / not required>

Keyboard support:
PASS / FAIL

Escape:
PASS / NOT APPLICABLE

Focus restoration:
PASS / NOT APPLICABLE

Narrow viewport:
PASS / FAIL

Horizontal overflow:
NONE / FAIL


LAYOUT PRIMITIVES

Content container:
<path>

Full-bleed composition supported:
YES / NO

Section primitive:
<path / NONE>

Generic grid foundation:
<summary / NONE>

Page-specific layout encoded:
NO / FAIL

Arbitrary spacing values:
NONE / <explain>

Canonical breakpoints:
PASS / FAIL


FOOTER

Footer:
<path>

Structure:
<summary>

Invented contact details:
NONE / FAIL

Broken placeholder links:
NONE / FAIL

Newsletter functionality invented:
NO / FAIL

Unapproved social links:
NONE / FAIL

MADE_TO_ORDER represented appropriately:
YES / NO / NOT CURRENTLY LINKED


DESIGN SYSTEM

Tokens/theme consumed:
PASS / FAIL

New visual token authority:
NONE / FAIL

Young Serif usage:
<summary>

UI sans usage:
<summary>

Charcoal action hierarchy:
PASS / FAIL

Brown restrained:
PASS / FAIL

Decorative card elevation:
NONE / FAIL

Glassmorphism:
NONE / FAIL

Random gradients:
NONE / FAIL

Excessive pills/radius:
NONE / FAIL


ACCESSIBILITY

Semantic landmarks:
PASS / FAIL

Navigation labels:
PASS / FAIL

Heading ownership:
PASS / FAIL

Keyboard navigation:
PASS / FAIL

Visible focus:
PASS / FAIL

Skip link:
PASS / FAIL

Touch targets:
PASS / FAIL

Zoom/reflow:
PASS / FAIL

Reduced motion:
PASS / FAIL


SERVER / CLIENT

Server-first architecture:
PASS / FAIL

Entire root layout client-side:
NO / FAIL

Entire shell client-side:
NO / <reason>

Interactive client boundaries:
<list>

Global navigation state:
NONE / FAIL

Browser-width render branching:
NONE / FAIL

Hydration issues:
NONE / FAIL


STRUCTURAL REFERENCE

Urban Ladder used as:
STRUCTURAL / IA REFERENCE ONLY

Structural ideas adopted:
<list>

Visual implementation copied:
NONE / FAIL

Promotional mechanics copied:
NONE / FAIL

Exact navigation/menu copied:
NONE / FAIL


REQUEST-FIRST

Active cart link:
NO

Active checkout link:
NO

Active payment link:
NO

Active order-history link:
NO

Request-first policy preserved:
YES / NO


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

SEO metadata implemented:
NO

Structured data implemented:
NO

Sitemap/robots implemented:
NO

Clerk integrated:
NO

Backend/API changed:
NO

Flutter changed:
NO

Dependencies added:
NONE / <list>


VISUAL VERIFICATION

Desktop:
PASS / FAIL

Tablet/narrow:
PASS / FAIL

Mobile:
PASS / FAIL

Zoom:
PASS / FAIL

Keyboard:
PASS / FAIL

Reduced motion:
PASS / FAIL


AUTOMATED VALIDATION

Layout tests:
PASS / FAIL

Routing regression:
PASS / FAIL

Request-first regression:
PASS / FAIL

API client regression:
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

Group M execution record:
PASS / FAIL

frontend/AGENTS.md:
<updated/unchanged>

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

Phase 13.7:
PASS / BLOCKED

Phase 13.8:
READY / BLOCKED
```

---

# 151. STOP Condition

Phase 13.7 may be declared PASS only when:

- one canonical site-shell architecture exists;
- root layout remains server-first;
- existing MUI provider architecture is preserved;
- semantic header/nav/main/footer landmarks are correct;
- skip-to-main is implemented and functional;
- the official brand identity is respected;
- header structure is suitable for furniture discovery;
- search has a clear structural position without prematurely implementing Phase 14.5;
- category navigation has a coherent desktop strategy;
- mobile navigation has a coherent mobile strategy;
- no fake/broken navigation links exist;
- no inactive cart/checkout/payment controls are exposed;
- MADE_TO_ORDER remains first-class;
- layout primitives support contained and full-bleed composition;
- pages will not need to reinvent site gutters/max-width;
- no Group N page composition has been implemented;
- footer structure is useful but does not invent business information;
- all layout styling consumes approved tokens/theme values;
- no duplicate token/theme/layout authority exists;
- no unauthorized icon library is used;
- client boundaries remain narrow;
- no global navigation state is introduced unnecessarily;
- accessibility landmarks, keyboard navigation, focus, zoom/reflow, touch targets, and reduced motion pass;
- representative desktop/tablet/mobile visual inspection passes;
- no horizontal overflow exists at supported widths;
- request-first policy remains intact;
- Urban Ladder influence remains structural only;
- no dependencies were added;
- API client regression passes;
- theme/design-system regressions pass;
- TypeScript passes;
- ESLint passes;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 13.7 — PASS
Phase 13.8 — READY
```

Do not start Phase 13.8 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

# Phase 13.7 Execution Record

**Status:** PASS. Phase 13.8 is READY and was not started.

## Shell Architecture

- Canonical shell: `frontend/web/components/layout/site-shell.tsx`; rendered by the root Server Component `app/layout.tsx` inside the existing `Providers`.
- Shell owns the skip link, `<header>`, the single `<main id="main-content" tabIndex={-1}>`, and `<footer>`.
- Provider boundary unchanged; no new providers, no global navigation state.

## Layout Primitives

- `content-container.tsx` owns site max-width (`--container-max`), centering, and responsive gutters.
- `site-section.tsx` owns vertical section rhythm, optional contained/full-bleed width, and semantic `surface` variants.
- `nav-link.tsx` composes `next/link` with token-backed anchor styling; no MUI `component={NextLink}` function prop crosses a Server/Client boundary.

## Header Strategy

- Primary row: brand mark (official `brandlogo.png`, links `/`) + search entry point; mobile trigger on small screens.
- Category row (desktop, `md+`): fixture-led category labels using canonical `/categories/[slug]` route identities, with a separated Made to Order label.
- Search is a shell-level structural entry point for `/search`; no form, API call, autocomplete, or result behavior.
- Reserved routes that are not yet implemented (`/search`, `/categories/[slug]`, `/contact`, `/furniture-requests`) render as non-interactive structural content; `/` is the only active internal navigation link. Availability is centralized in `site-navigation.ts`.
- Utility/announcement region omitted: no approved delivery/service/promotional content. No cart, wishlist, or auth controls.

## Navigation Strategy

- Desktop nav labelled "Primary navigation"; footer groups labelled "Furniture" and "Services".
- Category fixture mirrors the authoritative `CategorySeeder` top-level slugs; ownership documented in the fixture and deferred to Group N. Unimplemented destinations render as non-links via the shell availability registry.
- Mega menu deferred (not required for shell geometry); no hover-only behavior exists.

## Mobile Strategy

- Header collapses to menu trigger + brand + search icon.
- MUI `Drawer` (role dialog, `aria-modal`) with labelled close control, Escape dismissal, focus restoration to the trigger, and scrollable content.
- Drawer content is unmounted while closed; desktop nav is `display:none` on small screens, so no hidden focus targets.

## Footer Strategy

- Editorial surface with brand, Furniture (category labels), and Services (Made to Order, Furniture Enquiries) groups plus server-rendered copyright year.
- No invented contact details, social links, newsletter, legal pages, or generic commerce boilerplate.

## Accessibility Implementation

- Native skip link to `#main-content` (first tab stop, visible on focus, token focus ring).
- Semantic header/nav/main/footer landmarks with labels; exactly one `main`; page heading hierarchy preserved.
- 44px+ targets for menu/search/nav; visible focus retained; touch/zoom/reflow verified; reduced motion not bypassed (token motion only).

## Visual Verification

- Desktop (1440), tablet (960), and mobile (390) inspected via headless Chrome screenshots; corrected a narrow-width placeholder `h1` overflow.
- CDP audit: `scrollWidth === innerWidth` at 320/360/390/640/960/1024/1440; mobile drawer open/Escape/focus-restore verified.

## Scope Boundaries

- No Group N pages; `/` remains the foundation placeholder. No SEO metadata, structured data, sitemap/robots, Clerk, API, backend, or Flutter changes.

## Validation

```text
Layout contract: PASS
Theme contract: PASS
API client regression: PASS (17 tests)
TypeScript: PASS
ESLint: PASS
Production build: PASS
git diff --check: PASS
Dependencies added: NONE
```
