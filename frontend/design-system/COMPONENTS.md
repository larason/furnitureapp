# SL Furnitures Component Conventions

This document defines how future web components and Flutter widgets compose the shared design system. It is a convention contract, not a runtime component library.

## Authority And Boundaries

The authority chain is:

```text
DESIGN.md
  -> tokens.css
  -> USAGE.md
  -> framework mapping
  -> component conventions
  -> application components
```

- `tokens.css` owns values.
- `design-tokens.json` is the synchronized portable representation.
- `frontend/web/theme/` is a MUI consumer, not a second token authority.
- `flutter-material3.md` defines the future Flutter mapping contract.
- `components.html` and `components.manifest.json` are reference catalog artifacts only. They are not React source, Flutter source, an application registry, API schema, or runtime component metadata.
- Web and Flutter share semantics, visual hierarchy, state vocabulary, and token meaning, but use independent platform implementations.
- `ACCESSIBILITY.md` is the accessibility authority for all future component and page implementation.

Do not create application components, wrappers, Flutter widgets, or new dependencies in this phase.

## Component Hierarchy

Use the smallest level that owns meaningful behavior:

```text
Foundation -> Primitive -> Composite -> Commerce/domain -> Page composition
```

- Foundation: tokens, typography, color, spacing, focus, motion.
- Primitive: a framework control such as Button, Input, Link, or Surface when it adds shared behavior or enforcement.
- Composite: a focused combination such as SearchField or QuantitySelector.
- Commerce/domain: ProductCard, PriceDisplay, RequestCTA, or status presentation.
- Page composition: ProductGrid, product detail composition, or request flow layout.

Prefer MUI and Material primitives directly when their semantics and themed behavior are already correct. Create a wrapper only for centralized accessibility, repeated semantic variants, loading behavior, routing or analytics integration, domain behavior, complex responsive behavior, or repeated state logic. Do not create name-only wrappers such as `AppBox`, `AppStack`, `AppTypography`, or `AppIcon`.

Components use composition over giant configurable APIs. Avoid boolean and appearance-prop combinations such as `rounded`, `elevated`, `brown`, `hero`, `mobile`, and `showBadge` when a smaller semantic composition or closed variant is clearer.

## Variants And APIs

Variants express purpose, not page-specific appearance. The default action vocabulary is:

```text
primary | secondary | quiet | destructive | icon-only
```

Only expose a variant when it represents a repeated semantic distinction. Do not create `homepage`, `productPage`, `brown`, `cream`, `special`, or versioned visual variants. Do not create every size from `xs` through `xxl`; add a size only when it has a real shared meaning.

Component props describe meaning, state, or behavior: `loading`, `selected`, `status`, and `emphasis` are valid concepts. Uncontrolled product-level props such as `backgroundColor`, `borderRadius`, `fontSize`, `shadow`, and arbitrary `padding` are not a component API.

When a design-system variant is typed, use a closed TypeScript union or Dart enum/sealed value rather than an unrestricted string. If a needed treatment is absent, stop and decide whether it is local composition, a reusable variant, a missing token, or new design-system behavior before implementing it.

## Spacing And Layout Ownership

Components own internal spacing: icon-label gaps, control padding, field label gaps, and content rhythm inside a contained unit. Parents own external spacing: distance between siblings, sections, grids, and page regions. Reusable components should not impose arbitrary outer margins.

Parents normally determine width. Components control width only when their semantics require it, such as dialogs, popovers, tooltips, or compact icon controls. Avoid fixed content heights; allow text and data to grow. Responsive layouts may change composition at approved breakpoints without duplicating desktop/tablet/mobile component classes when semantics remain the same.

MUI `sx` and Flutter local layout primitives are allowed for composition, responsive arrangement, and token-based contextual spacing. They must not introduce raw brand values, random radii/shadows/fonts, new variants, or duplicated reusable styling.

## Actions, Links, And Icons

- Links navigate; buttons perform actions. Preserve semantic HTML on web and Material semantics on Flutter.
- Use one visually dominant primary action per immediate decision group where practical.
- Primary actions use charcoal `--action-primary`; brown is never the universal CTA.
- Secondary actions use approved border, surface, and text semantics.
- Quiet actions are for lower-emphasis navigation or secondary operations, not destructive actions.
- Destructive actions use functional destructive semantics and clear text; never disguise them as brand brown or generic charcoal.
- Web icons come only from `@mui/icons-material`; Flutter uses built-in Material Icons.
- Icons support meaning and do not decorate every heading, card, section, or CTA.
- Icon-only controls require an accessible name, adequate target, visible focus, and clear pressed/hover state. A tooltip supplements but does not replace the accessible name.
- Decorative icons are hidden from assistive technology. Icons inherit semantic theme colors and use consistent Material-family sizing and variant treatment.

## Forms

Use persistent accessible labels; placeholders are supplemental guidance, never the only label. Related controls share the themed control radius, border, typography, focus treatment, and minimum target behavior. Search is an input behavior, not automatically a pill.

Helper text explains useful format, constraint, or context without filling every field with noise. Validation errors identify the affected field, explain the correction, remain programmatically associated, and do not rely on red alone. Required/optional indication must be consistent within a form. File controls state allowed type, size constraint, selected file, replacement/removal, and upload error without exposing backend details.

## Surfaces, Cards, And Media

Not every grouped section is a card. Prefer layout, whitespace, typography, dividers, and semantic surface changes before adding containment. Use a card only for a genuinely self-contained content or interactive unit. An ordinary card does not automatically receive a border, rounded container, colored background, and shadow together; use the minimum containment needed. Cards and ordinary content are flat by default. `--elev-raised` is reserved for true layers such as menus, popovers, dialogs, drawers, bottom sheets, and sticky overlays.

Product/domain cards prioritize large furniture imagery, name, price or starting price where applicable, product type/status, and one clear navigation/action path. Avoid excess chrome, badge collections, decorative icons, heavy shadows, and large description blocks. Product media is generally sharp and architectural with approved aspect treatment and minimal overlays. Missing media uses a neutral branded state, not a random external placeholder.

`MADE_TO_ORDER` is a first-class valid offering. Its request action may be `Request Furniture`, but it must never look disabled, unavailable, or erroneous. Status, stock, and product type must use semantic text and/or iconography in addition to color. The API remains authoritative for pricing, inventory, eligibility, and order behavior; visual conventions do not invent commerce rules.

## Feedback And States

Use the least disruptive state treatment:

| State | Convention |
| --- | --- |
| Initial loading | Preserve page structure with a restrained skeleton where content shape is predictable. |
| Local/action loading | Preserve context, prevent unsafe duplicate submission, and communicate progress on the action. |
| Progressive loading | Reveal partial content without replacing the entire page unnecessarily. |
| Empty | Explain what is empty, why it may be empty, and what the user can do next when useful. Avoid automatic giant icons or marketing copy. |
| Validation error | Place actionable feedback beside the affected field or content. |
| Recoverable request error | Explain the problem and offer retry only when safe and meaningful. |
| Permission/authentication | Explain the required next step without leaking private resource existence. |
| Not found | State that the requested resource is unavailable without backend internals. |
| System/unavailable | Use persistent, actionable feedback; never expose stack traces or infrastructure details. |
| Snackbar | Transient, non-blocking confirmation such as saved, copied, or request submitted. |
| Dialog | Important confirmation, destructive action, focused short task, or critical information only. |

Loading is not disabled; disabled means unavailable, while loading means an operation is in progress. Selected is a persistent/current choice; active/pressed is a transient interaction state. Not every component needs every state.

## Accessibility And Platform Behavior

Future web components preserve semantic HTML: `nav` for navigation, links for navigation actions, buttons for actions, heading hierarchy, lists for lists, and labels for form controls. MUI polymorphism must not sacrifice semantics. Future Flutter components use built-in Material semantics or `Semantics`, `Tooltip`, labels, selected state, and button roles only where needed.

Focus, pressed, selected, disabled, loading, hover, and error are distinct states. Focus must remain visible; never remove focus without an approved visible replacement. Hover is web-specific and is not required to imitate on Flutter/mobile. Reduced-motion preferences must be respected. Responsive adaptation may use mobile-native composition such as bottom sheets or bottom navigation without changing semantic meaning.

## Catalog And Documentation

The reference catalog remains useful for visual evidence, but future convention documentation for an implemented component should cover:

```text
purpose, when to use, when not to use, anatomy, variants, sizes,
states, token dependencies, accessibility, responsive behavior,
platform notes
```

Do not add React import paths, Flutter class names, routes, API endpoints, or business logic to `components.manifest.json`.
