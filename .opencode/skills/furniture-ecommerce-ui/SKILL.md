---
name: furniture-ecommerce-ui
description: Build ecommerce UI strictly according to the furniture design system.
---

# Furniture UI System

Before modifying UI:

1. Inspect existing components
2. Reuse existing primitives before creating new ones
3. use frontend-design skills for guidance available under frontend directory
4. read frontend/design-system/DESIGN.md and frontend/design-system/tokens.css

## Design constraints

Every visual value must resolve to a design token.

The agent must not invent visual properties.

### Allowed

- theme.vars.*
- tokens.*
- existing component variants
- MUI system props backed by theme values

### Forbidden

- arbitrary hex colors
- arbitrary spacing
- arbitrary radius
- arbitrary shadows
- arbitrary font sizes
- random CSS

## Composition rules

Use the established layout primitives.

Product cards should remain visually quiet.

Hero sections may use large typography and photography.

Avoid decorative UI without a commerce or navigation purpose.

## Definition of done

A UI task is incomplete until:

- token compliance passes
- responsive layout is verified
- accessibility checks pass
- existing components are reused where appropriate