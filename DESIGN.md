# DESIGN.md — Cross-Platform Design System Rules

This repository contains a unified furniture e-commerce platform across two targets:
- **Mobile (`/apps/mobile`):** Flutter using **Material 3 (M3)**
- **Web (`/apps/web`):** Next.js (App Router) using **MUI v6** (`@mui/material` v6)

All AI agents (OpenCode, Antigravity CLI, or IDE extensions) MUST strictly follow these rules to ensure token consistency, component choices, accessibility, and clean code generation across platforms.

---

## 1. Unified Design Tokens & Brand System

Both Flutter and Next.js targets MUST share the same design token logic. **Never hardcode hex values or static raw pixel dimensions directly inside UI components.**

### Brand Tokens Reference
- **Primary Seed:** `#2C3E50` (Slate Navy)
- **Secondary Seed:** `#8E6E53` (Warm Oak / Bronze)
- **Tertiary Seed:** `#A2B59F` (Sage Green / Accent)
- **Neutral Surface (Light):** `#F8F9FA`
- **Neutral Surface (Dark):** `#121417`
- **Font Family:** `Inter` (Sans-serif display & body)

---

## 2. Flutter Mobile Guidelines (`/apps/mobile`)

### Framework & Theme Rules
1. **Enable Material 3:** `useMaterial3: true` must be explicitly set on `ThemeData`.
2. **ColorScheme Generation:** Use `ColorScheme.fromSeed()` for generating tonal palettes.
   ```dart
   final lightTheme = ThemeData(
     useMaterial3: true,
     colorScheme: ColorScheme.fromSeed(
       seedColor: const Color(0xFF2C3E50),
       secondary: const Color(0xFF8E6E53),
       brightness: Brightness.light,
     ),
     textTheme: AppTextTheme.lightTextTheme,
   );

```

3. **No Legacy Properties:** NEVER use `primaryColor`, `accentColor`, `backgroundColor`, or raw `Colors.white`/`Colors.black` inside widgets. Always consume context:
```dart
// CORRECT:
color: Theme.of(context).colorScheme.surfaceContainer;
color: Theme.of(context).colorScheme.onSurface;

// INCORRECT:
color: Colors.white;
color: Colors.grey[200];

```



### M3 Component Mapping

* **Buttons:**
* High Emphasis: `FilledButton`
* Medium Tonal: `FilledButton.tonal`
* Low Emphasis: `OutlinedButton` or `TextButton`


* **Navigation:** Use `NavigationBar` or `NavigationDrawer` (DO NOT use legacy `BottomNavigationBar`).
* **Cards & Depth:** Prefer tonal surface hierarchy (`surfaceContainerLow`, `surfaceContainerHigh`) and minimal elevation (`scrolledUnderElevation`) over hard shadows.
* **App Bars:** Use `SliverAppBar.large` or `AppBar` with `scrolledUnderElevation: 2.0`.

---

## 3. Next.js Web Guidelines (`/apps/web`)

### Framework & Theme Rules

1. **MUI Version:** Strictly use `@mui/material` v6 with Next.js App Router.
2. **CSS Variables & Color Schemes:** Use `CssVarsProvider` or `createTheme` with `cssVariables: true` and `colorSchemes` (MUI v6 API).
```tsx
import { createTheme } from '@mui/material/styles';

export const theme = createTheme({
  cssVariables: true,
  colorSchemes: {
    light: {
      palette: {
        primary: { main: '#2C3E50' },
        secondary: { main: '#8E6E53' },
        background: { default: '#F8F9FA', paper: '#FFFFFF' },
      },
    },
    dark: {
      palette: {
        primary: { main: '#90CAF9' },
        secondary: { main: '#D7CCC8' },
        background: { default: '#121417', paper: '#1E2228' },
      },
    },
  },
  typography: {
    fontFamily: 'var(--font-inter), sans-serif',
  },
});

```


3. **App Router SSR Safety:** Wrap client components with `'use client';` when consuming theme hooks or interactive state. Use `@mui/material-nextjs` for emotion/styles registry integration.
4. **Layout Grid:** Use `<Grid2>` from `@mui/material/Grid2`. DO NOT import deprecated `Unstable_Grid2` or standard legacy `Grid`.
5. **System Styling Props:** Prefer the `sx` prop or Pigment CSS utilities. Avoid raw inline `style={{ ... }}` blocks.
```tsx
// CORRECT:
<Box sx={{ p: 2, borderRadius: 2, bgcolor: 'background.paper' }}>

// INCORRECT:
<div style={{ padding: '16px', backgroundColor: '#fff' }}>

```



---

## 4. Cross-Platform Token Alignment Matrix

When generating equivalent components across web and mobile, map abstract roles according to this matrix:

| Semantic Role | Flutter Widget / Token | Next.js MUI v6 Component / Token |
| --- | --- | --- |
| **Primary Action** | `FilledButton` | `<Button variant="contained" color="primary">` |
| **Secondary Action** | `FilledButton.tonal` | `<Button variant="soft" color="secondary">` |
| **Surface Card** | `Card(elevation: 0)` | `<Card variant="outlined">` or `<Paper>` |
| **Card / Surface Container** | `colorScheme.surfaceContainer` | `palette.background.paper` / `var(--mui-palette-background-paper)` |
| **Primary Text** | `textTheme.bodyLarge` | `<Typography variant="body1">` |
| **Screen Title** | `textTheme.headlineMedium` | `<Typography variant="h4">` |
| **Input Fields** | `TextField` (Outlined decoration) | `<TextField variant="outlined">` |
| **Notification Badge** | `Badge` | `<Badge color="primary">` |

---

## 5. Mobile & Web Responsiveness & Touch Rules

1. **Touch Targets:** Minimum touch region on both Flutter and Next.js inputs/buttons MUST be **48x48 dp/px**.
2. **Breakpoints (Next.js):**
* `xs`: 0px (Mobile)
* `sm`: 600px (Tablet Portrait)
* `md`: 900px (Tablet Landscape / Small Desktop)
* `lg`: 1200px (Desktop)


3. **Layout Adaptivity (Flutter):**
* Width < 600dp: Render `NavigationBar` (Bottom).
* Width >= 600dp: Render `NavigationRail` or `NavigationDrawer` (Side).



---

## 6. Code Generation Principles for Agents

1. **Self-Correction & Linting:** Before returning generated code, verify:
* Flutter code passes `flutter analyze` with zero deprecation warnings.
* React code has zero TypeScript type mismatches (`tsc --noEmit`).


2. **Accessibility (a11y):**
* Ensure minimum contrast ratio of 4.5:1 for body text against surfaces.
* Web images MUST include non-empty `alt` text.
* Flutter icons inside buttons MUST have `semanticLabel` or use explicit semantic wrappers when necessary.


3. **No Unused Dependencies:** Stick strictly to `@mui/material`, `@mui/icons-material`, and native Flutter Material components unless explicitly requested otherwise.