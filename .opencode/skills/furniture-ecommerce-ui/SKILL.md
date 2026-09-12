---
name: furniture-ecommerce-ui
description: Build ecommerce UI strictly according to the furniture design system.
---

# Furniture UI System

Before modifying UI:

1. Read /src/theme/tokens.ts
2. Read /docs/design-system.md
3. Inspect existing components
4. Reuse existing primitives before creating new ones
5. use frontend-design skills for guidance available under frontend directory

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