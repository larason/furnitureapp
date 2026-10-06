# SL Furnitures Accessibility Baseline

This is the shared accessibility authority for future website and Flutter application work. It defines a WCAG 2.2 AA-oriented design-system baseline; it does not claim that an application or user journey is fully WCAG conformant. Every future page, component, and journey requires implementation and manual verification.

## Default Invariant

Accessibility is the default system behavior, not an accessibility mode, alternate theme, overlay, widget, or separate component set. Prefer native HTML semantics and MUI/Material primitives before adding ARIA or wrappers. Accessibility requirements must not weaken authentication, authorization, privacy masking, or error-message discipline.

## Visual And Interaction Invariants

- Use canonical text and surface pairings from `tokens.css`; normal text targets 4.5:1, large text 3:1, and meaningful non-text UI boundaries/focus indicators 3:1 where applicable.
- Use `--action-focus` and `--focus-ring` for a persistent, visible focus indication. Never remove focus without an equally visible approved replacement.
- Do not communicate status, errors, required state, availability, or selection through color alone. Pair color with text, iconography, shape, or programmatic state.
- Controls must have usable targets: aim for at least 44 CSS pixels on web and platform-appropriate Material touch targets on Flutter.
- Essential content must survive zoom, reflow, enlarged text, and responsive stacking. Essential functionality must work by keyboard, touch, and other supported pointer methods.

## Semantics And Structure

- Use one logical heading hierarchy and do not skip levels for visual sizing. Use typography tokens rather than heading misuse.
- Future web shells require a keyboard-accessible skip link to main content, one logical `main` landmark, and meaningful landmarks only where their content warrants them.
- Links navigate and buttons act. Preserve native semantics when using MUI polymorphism; do not make a `div` interactive.
- Every form control has a persistent visible/programmatic label, useful instructions where needed, consistent required/optional indication, and an associated actionable error. Placeholder text is never the only label.
- Dialogs, menus, drawers, loading states, errors, empty states, and status updates require appropriate focus management and programmatic semantics. Do not expose backend details or private-resource existence through accessible text.

## Icons And Images

- Meaningful icon-only controls require an accessible name; a tooltip supplements but never replaces it.
- Decorative icons are excluded from assistive technology. Web UI icons remain `@mui/icons-material`; Flutter uses built-in Material Icons.
- Meaningful product imagery receives concise useful alternative text. Decorative imagery receives empty alternative text or equivalent platform semantics. Product cards remain understandable without imagery.

## Motion And Platform Responsibilities

- Motion remains quiet, purposeful, token-driven, and never required to understand or complete an action.
- Future web implementation must honor `prefers-reduced-motion`; future Flutter implementation must honor platform reduced-motion settings where supported.
- MUI and Material 3 provide useful foundations, not automatic conformance. Web and Flutter implementations share meaning while using platform-native semantics, traversal, focus, text scaling, safe areas, keyboard behavior, and dialogs.
- `MADE_TO_ORDER` is a valid first-class request path and must not appear disabled, unavailable, or erroneous merely because it is not normal cart commerce.

## Verification Contract

Future implementation and QA must combine static checks, component/integration tests, browser/device checks, and manual review of keyboard operation, labels, names, states, errors, focus order, headings, landmarks, alternative text, source order, privacy-safe messaging, zoom/reflow, text scaling, forced colors, reduced motion, touch, and screen-reader behavior. Automated checks supplement manual testing and cannot establish full WCAG conformance alone.

## Scope Boundary

This document does not create runtime components, pages, routing, API behavior, Flutter code, or accessibility dependencies. The reference catalog demonstrates representative focus and semantic patterns only; it is not an accessibility test application.
