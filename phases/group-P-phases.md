# Group P — Flutter Mobile App Design Foundation
## Layout Inspiration and Shared Design-System Enforcement

### Objective

Develop the Flutter furniture e-commerce application using the existing Next.js website as the authoritative product and design reference.

Use the provided furniture mobile application screenshot exclusively as a reference for layout composition, content hierarchy, and mobile interaction patterns. the asset is in designs/appconcept.webp

**Do not copy, trace, recreate, or reproduce the screenshot's design.**

The Flutter application must express the same brand identity as the Next.js website through shared design-system tokens and consistent component semantics.

## 1. **Mandatory** Design References

Inspect the following before implementing UI:

1. Root `AGENTS.md` and Group P phase documentation.
2. Existing Next.js + React + MUI website implementation.
3. Current approved MUI theme.
4. Design tokens and theme configuration.
5. Reusable website primitives and commerce components.
6. Nike-inspired design-system source, if retained in the repository.
7. Existing product, catalog, cart, favorites, and authentication contracts.
8. The provided mobile layout reference screenshot.

### Authority hierarchy

When references conflict, apply this precedence:

1. Frozen product requirements and API contracts for functionality.
2. Approved shared design-system tokens for visual styling.
3. Existing Next.js website components for brand consistency.
4. Flutter Material 3 conventions for native usability and accessibility.
5. Provided screenshot for layout inspiration only.

The screenshot must never override authoritative tokens or established business rules.

## 2. Screenshot Usage Restrictions

### Allowed inspiration

The screenshot may inform:

- Mobile content hierarchy.
- Product card composition.
- Search placement.
- Category browsing.
- Product gallery organization.
- Favorites layout.
- Bottom navigation concepts.
- Primary action placement.
- Screen density and whitespace balance.

### Explicitly prohibited

The agent must NOT:

- Recreate the screenshot pixel-for-pixel.
- Copy its exact spacing or dimensions.
- Copy its colors or typography.
- Copy its icon styling.
- Duplicate its navigation structure without evaluating actual app requirements.
- Reproduce its promotional text.
- Copy its product photography or assets.
- Use the screenshot as a source of design tokens.
- Introduce screenshot-specific styling overrides.
- Build a visually separate brand identity for mobile.

The screenshot is a UX reference, not a design specification.

## 3. Shared Design-System Architecture

The Next.js website and Flutter app must use a single authoritative set of design-token definitions.

Do not independently invent or manually maintain conflicting token values in two applications.

### Required approach

Inspect how the website currently defines its tokens.

Determine whether a framework-neutral token source already exists.

If it does, reuse that source.

If tokens exist only inside MUI theme files, propose a controlled extraction into a framework-neutral representation without changing the existing website's rendered appearance.

Prefer a structure similar to:

```text
design-system/
  tokens/
    colors.json
    typography.json
    spacing.json
    radii.json
    elevation.json
    breakpoints.json
    web/
    flutter/
```

This structure is illustrative, not mandatory. Follow the repository's existing conventions.

The shared tokens should feed:

- MUI theme configuration for Next.js.
- Flutter ThemeData and ThemeExtensions.

Maintain one source of truth with platform-specific adapters.

### Critical rule

Do not automatically refactor the existing Next.js theme.

If extraction requires architectural changes, document the proposal and obtain approval before proceeding.

The website's existing visual behavior must remain unchanged.

## 4. Flutter Material 3 Integration

Use Flutter Material 3 as the component foundation.

Map the approved shared tokens into:

- ColorScheme
- TextTheme
- ButtonTheme / ButtonStyle
- InputDecorationTheme
- CardTheme
- NavigationBarTheme
- AppBarTheme
- ThemeExtensions for additional semantic tokens

Use the approved typography family and weights.

Do not introduce platform-default visual values when they conflict with approved brand tokens.

However, preserve platform accessibility, safe areas, system text scaling, touch-target requirements, and native navigation behavior.

Use native Flutter interactions where they improve usability without breaking brand consistency.

## 5. Token Enforcement Rules

### Mandatory

Every UI component must obtain its visual properties from approved theme tokens or established semantic component styles.

### Forbidden

- Arbitrary hex colors.
- Hardcoded RGB values.
- Random font sizes.
- Arbitrary spacing constants.
- Unapproved border radii.
- Independent shadow definitions.
- Repeated inline styling.
- Unapproved component variants.
- New font families.
- Screenshot-derived styling constants.

Do not create a second unofficial Flutter design system.

### Exceptions

Platform-specific constraints such as safe-area insets, accessibility minimums, device dimensions, and calculated responsive layout measurements may use runtime values.

Any new brand-level design token requires explicit approval and must be added to the shared token source.

## 6. Mobile Layout Direction

Use the screenshot to explore layouts for three core experiences.

### Home / Catalog

Consider:

- A compact mobile promotional section.
- Search near the beginning of the shopping journey.
- Accessible category navigation.
- Product discovery sections.
- Responsive product cards.
- Favorites actions.
- Clear pricing and product availability.

Do not assume every product is immediately purchasable.

Respect IN_STOCK and MADE_TO_ORDER distinctions.

### Product Details

Consider:

- A prominent product image gallery.
- Thumbnail or swipe-based image navigation.
- Product title and price.
- Variant information.
- Dimensions and material details.
- Availability.
- Relevant purchase or enquiry actions.
- A persistent primary action when appropriate.

For IN_STOCK products, use the supported purchase flow.

For MADE_TO_ORDER products, use the approved furniture request flow.

Do not add unsupported purchase actions.

### Favorites

Consider:

- A clean saved-product grid.
- Product images.
- Clear product titles and prices.
- Favorite removal.
- Product-detail navigation.
- Purchase actions where permitted.
- Useful empty states.

Use the actual favorites contract and persistence rules.

Do not invent backend functionality.

## 7. Navigation Architecture

Evaluate the screenshot's bottom navigation as a possible mobile navigation pattern.

Do not copy it automatically.

Determine navigation destinations from the project's actual feature requirements.

Prefer a small number of high-frequency destinations, with secondary features accessible through contextual navigation.

Follow Flutter navigation conventions and the existing app architecture.

Ensure navigation works consistently for authenticated and anonymous users.

## 8. Cross-Platform Visual Consistency

The website and Flutter app must share:

- Brand colors.
- Typography identity.
- Semantic color roles.
- Component hierarchy.
- Button variants.
- Input styling.
- Product-card design language.
- Iconography conventions where practical.
- Spacing rhythm.
- Feedback states.
- Product information hierarchy.

They do not need identical page layouts.

Web and mobile have different interaction models.

**Target: shared brand identity, platform-appropriate user experience.**

## 9. Design and Implementation Workflow

Follow this sequence:

1. Audit the existing website design system.
2. Identify authoritative tokens.
3. Map tokens to Flutter Material 3.
4. Define reusable Flutter primitives.
5. Define reusable commerce components.
6. Implement screens according to the approved Group P phases.
7. Verify visual consistency.
8. Test responsive behavior and accessibility.
9. Run static analysis and automated tests.
10. Verify actual device rendering.

Do not begin implementing all Flutter screens before establishing the theme and reusable primitives.

Do not combine unrelated Group P phases.

## 10. Verification and Quality Gates

Validate:

- Token compliance.
- Typography consistency.
- Semantic color consistency.
- Reusable component usage.
- Responsive layouts.
- Text scaling.
- Safe-area handling.
- Touch targets.
- Light/dark theme behavior if supported.
- Product state correctness.
- Navigation consistency.

Use Flutter's testing tools and available device or emulator tooling.

Chrome DevTools MCP is intended for browser verification and must not be treated as proof of native Flutter mobile rendering.

If the project has no emulator or device available, report native visual verification as pending.

## 11. Design Review Deliverables

Before major screen implementation, report:

1. Authoritative token locations.
2. Shared-token strategy.
3. MUI-to-Flutter token mapping.
4. Reusable Flutter primitives.
5. Planned commerce components.
6. Navigation proposal.
7. How the screenshot influenced layout decisions.
8. How the implementation differs from the screenshot.
9. Any missing tokens requiring approval.
10. Verification strategy.

Do not claim cross-platform design consistency without checking both implementations.

### Definition of Done

The Flutter app has a documented, enforceable design foundation derived from the approved website design system.

The reference screenshot has informed mobile UX decisions without being copied.

The website and mobile application share a consistent brand identity through authoritative tokens, while each respects its platform's interaction and accessibility requirements.

Proceed phase by phase, preserving frozen API contracts and existing website behavior.

---

## Phase 16.1 — Flutter Project Setup (Closure Record)

### Scope executed

- Initialized the Android-only Flutter application at `frontend/app/`.
- Application ID / Android namespace: `com.slfurnitures.app`. Dart package: `sl_furnitures`.
- Android launcher label: `SL Furnitures`.
- No iOS, web, or desktop targets were generated.
- No theme, environment, networking, auth, routing, or feature code was introduced. Those remain Phases 16.2–16.9.

### Files created or modified

```text
frontend/app/pubspec.yaml                     # name sl_furnitures, description
frontend/app/analysis_options.yaml            # flutter_lints + strict analyzer modes
frontend/app/lib/main.dart                    # entry point
frontend/app/lib/app.dart                     # root widget + bootstrap placeholder
frontend/app/test/app_test.dart               # app-shell smoke test
frontend/app/README.md                        # scope, commands, design-system boundary
frontend/app/android/app/src/main/AndroidManifest.xml   # android:label
frontend/app/android/**                       # generated Android host project
```

### Local verification

| Check | Command | Result |
| --- | --- | --- |
| Dependencies | `flutter pub get` | Passed |
| Static analysis | `flutter analyze` | `No issues found!` |
| Tests | `flutter test` | `All tests passed!` (1 test) |
| Android build | `flutter build apk --debug` | `app-debug.apk` built |
| Artifact identity | `aapt2 dump badging app-debug.apk` | `com.slfurnitures.app`, label `SL Furnitures`, `sdkVersion 24`, `targetSdkVersion 36` |

Native visual verification is **pending**: no Android device or running emulator was attached
during this phase. An AVD (`MyLinuxEmulator`) exists but was not launched. Chrome DevTools MCP
is not proof of native Flutter rendering and was not used as such.

The generated release signing config is still the Flutter debug-key placeholder. Release
signing, versioning, and store metadata belong to a later release phase.

### Design Review Deliverables (Group P §11)

1. **Authoritative token locations.** `frontend/design-system/tokens.css` is the sole
   canonical authority. `design-tokens.json` and `tailwind-v4.css` are synchronized derived
   representations. `DESIGN.md` defines brand/interaction intent; `flutter-material3.md`
   defines the MUI-to-Flutter mapping contract; `frontend/web/theme/theme.ts` is the existing
   MUI adapter and the current reference implementation.
2. **Shared-token strategy.** One framework-neutral token source with platform adapters.
   Flutter does not parse CSS and does not load token JSON at runtime. Phase 16.2 maps the
   tokens into `ThemeData` through a static Dart adapter verified against
   `design-tokens.json`. No second token authority is created for mobile.
3. **MUI-to-Flutter token mapping.** Already specified in
   `frontend/design-system/flutter-material3.md` (color roles, typography scale, spacing,
   shape, elevation, motion). Phase 16.2 consumes that contract; Phase 16.1 does not
   duplicate or redefine it.
4. **Reusable Flutter primitives (planned).** Deferred to the primitives work that follows
   the theme phase. The app currently ships only the root widget and a bootstrap placeholder.
5. **Planned commerce components (planned).** Governed by
   `frontend/design-system/COMPONENTS.md`; implementation belongs to Group Q. No commerce
   widget is introduced in 16.1.
6. **Navigation proposal.** Deferred to Phase 16.6. The reference screenshot's bottom
   navigation is treated as inspiration only (see §7 of this document) and will be evaluated
   against actual feature requirements before adoption.
7. **Screenshot influence.** None yet. Phase 16.1 implements no screen, so no layout decision
   was derived from `designs/appconcept.webp`. The asset remains a UX reference for the
   screen phases.
8. **Differences from the screenshot.** Not applicable in this phase, by design. No visual
   surface exists to compare.
9. **Tokens requiring approval.** Two genuine gaps must be resolved before Phase 16.2:
   - **Utility font licensing.** The utility stack is Helvetica Now Text Medium / Helvetica Now
     Text, a licensed Monotype family. Confirm a distributable Android license or an approved
     substitute before bundling. Young Serif (display) is open-licensed and lower risk.
   - **Dark theme.** The shared token contract defines light semantics only. Do not add a dark
     theme in 16.2 without an explicit decision; `--surface-inverse` is an inverse surface, not
     dark mode.
10. **Verification strategy.** Per phase: `flutter analyze`, `flutter test`, and
    `flutter build apk`. Token compliance is checked by confirming no raw color/spacing/radius
    values appear outside the Phase 16.2 adapter. Native rendering is verified on the attached
    AVD during screen phases; automated checks are not treated as visual proof.

### Exit condition

The Flutter project builds, analyzes cleanly, tests pass, and produces an installable Android
artifact with the approved identity, while the website and its token authority remain
unchanged.

**Phase 16.1: LOCAL IMPLEMENTATION COMPLETE.** Phase 16.2 not started.