# Responsive Foundation

## Authority

`frontend/design-system/tokens.css` is the only responsive value authority. `design-tokens.json` is its synchronized representation, and `theme/theme.ts` maps it into MUI:

| MUI key | Semantic token | Value |
| --- | --- | --- |
| `xs` | base | `0px` |
| `sm` | `--breakpoint-phone` | `640px` |
| `md` | `--breakpoint-tablet` | `960px` |
| `lg` | `--breakpoint-desktop` | `1024px` |
| `xl` | `--container-max` | `1440px` |

Breakpoint names mark layout transitions, not device detection. Test widths are evidence points, not new CSS breakpoints.

## Layout Ownership

- `ContentContainer` owns the `1440px` maximum width, centering, and `16px`/`24px`/`48px` phone, tablet, and desktop gutters.
- `SiteSection` owns vertical section rhythm and contained versus full-bleed composition. A full section reaches the viewport naturally; nested `ContentContainer` is permitted only when a full-bleed surface needs contained content.
- Pages must not create another container, page-specific gutter scale, `100vw` width, negative viewport margin, or global horizontal-overflow clipping.
- Components own internal spacing. Parents own external layout spacing. Use the approved type and spacing scales without arbitrary responsive values or fluid `clamp()` typography.

## Implementation Rules

- Prefer CSS and MUI `sx` breakpoint keys. Do not render from `window.innerWidth`, device/user-agent detection, or ordinary `useMediaQuery` layout branching.
- Server-capable shell, section, container, loading, and not-found components remain server components. Client boundaries are limited to real interaction: the provider, mobile drawer, and framework error recovery.
- The header switches from mobile drawer navigation to desktop category navigation at `md` (`960px`). Hidden navigation must not remain keyboard-reachable.
- Hover may enhance a control but never reveal essential functionality. Preserve keyboard, touch, visible focus, text enlargement, and reduced-motion behavior.

## Verification Matrix

Test the shell and generic states at `320`, `390`, `640`, `960`, `1024`, `1440`, and a wide desktop width such as `1728` pixels. Also test the navigation transition at `959`, `960`, and `961` pixels, 200% browser zoom/reflow, long fixture labels, keyboard operation, and:

```js
document.documentElement.scrollWidth <= window.innerWidth
```

Future Group N phases own responsive page composition, catalog grids, filters, search, image ratios/crops, and image loading sizes. They must reserve image geometry, preserve crop intent, and use Next.js image capabilities where appropriate without downloading oversized assets unnecessarily.
