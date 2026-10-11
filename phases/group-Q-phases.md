# Phase 17.15 — Global App Navigation, Drawer & Contextual Account Access

**Project:** SL Furnitures  
**Application:** Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Phase:** 17.15 — Global App Navigation & Drawer  
**Prerequisites:** Phases 17.1–17.4 and 17.10–17.13  
**Production commerce mode:** REQUEST ONLY  
**Status:** Final active implementation phase in Group Q

---

## 1. Objective

Implement the final customer-facing navigation experience for the SL Furnitures Flutter app.

The goal is to replace temporary, fragmented navigation entry points with a consistent global navigation system, making every currently supported customer feature discoverable.

Implement:

1. A shared Material 3 top app bar.
2. A left navigation drawer.
3. A genuinely centered SL Furnitures brand logo.
4. A contextual account button on the right.
5. A guest-only Material 3 account bottom sheet.
6. Proper sign-in and registration navigation.
7. Direct account navigation for authenticated customers.
8. Real SL Furnitures branding on authentication screens.
9. Dynamic category navigation using the existing catalog backend.
10. Removal of obsolete homepage navigation buttons created for testing.
11. Integration of informational and legal routes.
12. Consistent back navigation, authentication transitions, and deep links.

**Do not implement bottom navigation for the initial REQUEST ONLY release.**

This is a navigation integration phase, not an opportunity to redesign existing feature screens or activate deferred commerce capabilities.

## 2. Repository inspection — mandatory

Before modifying the application, inspect:

- Root `AGENTS.md`, including Group Q, design consistency, and the definition of done.
- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- `frontend/design-system/tokens.css`.
- Generated Flutter theme/tokens and their generator.
- Current `MaterialApp.router` setup.
- Existing `go_router` configuration, route names, redirects, and refresh behavior.
- Existing home, catalog, categories, search, product detail, furniture request, and enquiry screens.
- Phase 17.12 authentication implementation and Clerk integration.
- Phase 17.13 information and legal screens.
- Existing `AuthSession`, `ClerkAuthAdapter`, and authentication state listener.
- Current account/profile screens.
- Existing assets and brand/logo resources.
- Current loading/error components.
- Existing route and widget tests.

Identify all temporary navigation controls introduced solely to make incomplete features accessible during development.

Do not remove a control merely because it links to a route now present in the drawer. Preserve feature-specific CTAs such as "Request this furniture" on product details.

Before implementation, briefly document the existing navigation architecture, actual route names, actual logo asset path, and any integration risks.

## 3. Navigation architecture

Reuse the current centralized go_router implementation.

Introduce or extend a shared navigation shell using an appropriate supported go_router pattern, such as `ShellRoute`, if it fits the existing route hierarchy.

Avoid unnecessary migration to a new router architecture.

### Architectural responsibilities

The shared shell owns:

- Global app bar.
- Navigation drawer.
- Consistent page presentation.
- Account entry point.
- Common navigation interactions.

Individual features own:

- Their own content.
- Feature-specific page actions.
- Forms.
- Local loading and error states.
- Feature-specific nested navigation.

The navigation shell must not contain catalog API implementations, authentication business logic, or duplicated route definitions.

### Important

Only one `MaterialApp.router` may exist.

Do not put another `Scaffold` around every page without considering the existing screen structure.

Prevent:

- Nested duplicate app bars.
- Duplicate navigation drawers.
- Unexpected back buttons.
- Conflicting floating action buttons.
- Extra top padding.
- Double safe-area application.
- Screens retaining invisible app bars after navigation.

Refactor existing screen scaffolds only as much as is necessary to support the shared shell.

## 4. Global top app bar

Create a reusable SL Furnitures Material 3 top app bar.

### Required arrangement

```text
┌────────────────────────────────────┐
│                                    │
│  ☰         SL FURNITURES        ♙  │
│                                    │
└────────────────────────────────────┘
```

The diagram is conceptual. Use the real brand logo asset.

### Left action — navigation drawer

Place the menu icon on the left.

Use a standard Material icon consistent with Material 3.

Interaction:

- Tap opens the navigation drawer.
- Drawer opens reliably from all appropriate top-level customer screens.
- Screen readers announce "Open navigation menu".
- Provide proper touch-target dimensions.

### Center — SL Furnitures logo

Use the actual SL Furnitures logo already defined in the project.

The logo must be geometrically centered relative to the full app bar width, not merely centered in the space remaining between the left and right actions.

Use a balanced layout that remains correct when the menu or account icon is present.

Preserve the logo's aspect ratio.

Do not:

- Generate a different logo.
- Substitute an emoji.
- Use a generic furniture icon.
- Replace the logo with plain text if the real asset exists.
- Crop or stretch the logo.
- Introduce a new brand identity.

If the repository contains multiple logo variants, choose the one designed for the light app-bar surface.

If no official usable logo asset exists, report the blocker. Do not invent a replacement.

The logo may act as a Home navigation action if consistent with project conventions.

### Right action — account

Use a recognizable person/account icon.

Its behavior depends on the authoritative current authentication state.

- Guest: open account bottom sheet.
- Authenticated customer: navigate to Account/Profile.

The icon must have a descriptive accessibility label.

### Styling

Use the generated Flutter theme derived from `frontend/design-system/tokens.css`.

Preferred visual character:

- Warm or paper background consistent with the app.
- Dark foreground icons.
- Minimal elevation.
- Restrained separators, only when needed.
- Consistent spacing.
- No arbitrary accent colors.
- No overly tall promotional header.

Do not recreate the Bonobos promotional banner shown in the screenshot.

The screenshot is only an interaction reference for account entry.

## 5. Global drawer — required hierarchy

Create a Material 3 navigation drawer that exposes the currently available customer functionality.

The drawer should prioritize product discovery.

### Proposed content hierarchy

```text
SL Furnitures

[ Search furniture... ]

EXPLORE
  Home
  All Furniture

CATEGORIES
  Category A
  Category B
  Category C
  ...from actual catalog data

SERVICES
  Request Furniture
  Contact Us

INFORMATION
  About Us

LEGAL
  Privacy Policy
  Terms & Conditions
```

Use actual category names from the backend, not the placeholders above.

This hierarchy is an initial design specification. Match the final implementation to the established routes and information architecture.

### Search placement

Search must be the first actionable element in the drawer.

Provide a prominent, accessible search field or search-entry component.

Preferred behavior:

- The drawer displays a search affordance at the top.
- Tapping it closes the drawer and opens the existing Search screen.
- If the existing Search screen supports an initial query parameter, use that established mechanism.
- Do not invent a new search controller or parallel filtering engine.

A navigation-style search field is sufficient.

Do not duplicate the complete Search screen inside the drawer.

If an inline search input is implemented, correctly manage focus, the software keyboard, drawer dismissal, and query transfer.

### Home and catalog

Include navigation to the existing Home and all-furniture/catalog entry points where these are distinct screens.

Do not create duplicate destinations when both labels map to the same route without a meaningful UX difference.

### Categories

The drawer must show the actual available furniture categories using the application's existing category repository/API integration.

Do not hard-code category IDs, names, or counts.

If categories have a supported hierarchy, preserve it.

For nested categories, use an accessible expansion mechanism where appropriate.

Selecting a category must:

1. Close the drawer.
2. Navigate to the existing category screen.
3. Supply the correct established route parameter.
4. Display that category's actual products.
5. Preserve existing catalog pagination/filtering behavior.
6. Avoid loading a different category accidentally.

Use the existing domain category model.

### Category loading behavior

Handle:

- Initial loading.
- Already cached categories.
- Empty catalog.
- Network failure.
- Retry.
- Slow API responses.

The drawer should open immediately without waiting for the category API.

If loading fails, retain working links to Home, Search, Contact, and informational screens.

Do not crash the drawer because categories are unavailable.

Avoid making the same unnecessary API call every time the drawer opens.

Reuse the established repository/cache lifecycle where possible.

### Services

Include:

- Request Furniture.
- Contact Us.

Use existing public routes.

Do not require authentication for these pages.

If the request screen supports an optional product context, preserve the existing product-linked request workflow and avoid manufacturing fake product data when entering from the drawer.

### Information

Include About Us.

### Legal section

Place Privacy Policy and Terms & Conditions toward the bottom of the drawer.

These should have lower visual prominence than discovery and request features but remain easy to find.

Consider layout behavior on short screens: the drawer must remain scrollable and the legal links must never be clipped.

### Open Source Licenses

**Do not place Open Source Licenses in the main furniture navigation drawer.**

Instead, expose the licenses entry inside the existing Account Details/Profile information area using the public licenses route implemented in Phase 17.13.

Preserve Flutter's registered-license display implementation.

Do not make a duplicate license viewer.

For guests, do not unexpectedly require authentication to access the underlying license route; it remains a public route even though its regular navigation entry is located in account details.

## 6. Drawer interaction details

Use Material 3 components that are compatible with the existing theme.

The drawer must:

- Open and close smoothly.
- Close after a destination is selected.
- Highlight the active destination where appropriate.
- Preserve correct category selection.
- Support system-back dismissal.
- Respect safe areas.
- Scroll on small screens.
- Support landscape.
- Remain usable at 2× text scaling.
- Avoid horizontal overflow.
- Support TalkBack.
- Keep touch targets accessible.
- Avoid duplicate route pushes when the user selects the current destination.

Use semantic section labels and clear grouping.

Do not use heavy animations or custom gestures when standard Flutter functionality is sufficient.

Avoid showing all categories as a giant unstructured list if a meaningful hierarchy already exists.

## 7. Account icon — unauthenticated behavior

When a user without an authenticated Clerk customer session taps the global account icon:

**Open a Material 3 modal bottom sheet.**

Do not navigate directly to the Sign In screen.

### Bottom-sheet presentation

The reference screenshot shows an account sheet over an existing shopping screen.

Adapt that interaction to SL Furnitures.

Suggested layout:

```text
┌────────────────────────────────────┐
│  Account                        ×  │
│                                    │
│  Welcome to SL FURNITURES          │
│  Sign in to manage your account.   │
│                                    │
│  ┌──────────────────────────────┐  │
│  │           Sign In            │  │
│  └──────────────────────────────┘  │
│                                    │
│  ┌──────────────────────────────┐  │
│  │        Create Account        │  │
│  └──────────────────────────────┘  │
│                                    │
└────────────────────────────────────┘
```

### Styling

Use the application's established Material 3 buttons, shapes, typography, and spacing.

Recommended:

- Primary filled button: Sign In.
- Secondary outlined button: Create Account.
- Clear sheet title: Account.
- Close button in the top-right.
- Surface color from canonical tokens.
- Rounded top corners from the approved theme.
- Safe-area spacing.

Do not add Shop Pay, social login, or other third-party authentication buttons based on the screenshot.

Do not include Orders, Tracking, Cart, or other deferred commerce actions.

### Interaction

On Sign In:

1. Dismiss the bottom sheet.
2. Navigate to the existing Clerk Sign In screen.

On Create Account:

1. Dismiss the bottom sheet.
2. Navigate to the existing Clerk Registration screen.

On Close:

- Dismiss the sheet without navigating.

On Android system back:

- Dismiss the sheet first.

On outside tap:

- Use standard modal dismissal behavior, unless the established app accessibility conventions require otherwise.

### Important

Use supported Flutter APIs such as `showModalBottomSheet`.

Do not reimplement the bottom-sheet framework.

Avoid creating a bottom sheet from each individual screen. Its trigger belongs to the global navigation shell.

## 8. Account icon — authenticated behavior

When the current customer has a valid authenticated Clerk session:

Tapping the account icon must navigate directly to the existing Account/Profile screen.

Do not show the guest sheet.

Do not prompt for a second login.

Reuse the existing route and authentication guard.

### Authentication-state transitions

The global account entry must react to changes in the actual authentication state.

Handle:

- App starts anonymously.
- Customer signs in.
- Customer registers and verifies email.
- Session is restored at startup.
- Session expires.
- Customer signs out.
- Clerk initialization is delayed.
- Session refresh is in progress.

Do not infer authenticated state from stale local booleans or profile data alone.

### Important pending-state rule

While the authentication state is unresolved, do not briefly show the guest sheet and then redirect the user to the account page.

Wait for or resolve the authoritative state through the existing session abstraction.

Prevent duplicate navigation and race conditions.

## 9. Authentication success routing

Update the relevant login/registration completion behavior to support the intended launch UX.

### Standard flow

```text
Guest taps Account
        ↓
Account bottom sheet
        ↓
Sign In / Create Account
        ↓
Clerk authentication
        ↓
Email verification if required
        ↓
Authenticated session established
        ↓
Home
```

After successful authentication, navigate to the Home screen for a normal account entry flow.

Use `go` or the existing equivalent when appropriate to avoid leaving redundant authentication pages in the back stack.

### Preserve explicit return destinations

Do not break valid protected-route return-to behavior already implemented in Phase 17.12.

If the user originally entered an authenticated workflow with a legitimate allowlisted return destination, honor that existing mechanism.

For authentication initiated directly from the global Account bottom sheet, Home is the preferred destination after successful sign-in or registration.

Do not create open redirects or accept arbitrary external return URLs.

### Failure behavior

If authentication fails or is cancelled:

- Do not navigate to Home as an authenticated user.
- Keep the user in the appropriate auth flow or safely restore the prior public screen.
- Present the existing Clerk error handling.
- Do not alter Laravel account ownership logic.

## 10. Real brand logo on authentication screens

Inspect every authentication screen implemented in Phase 17.12.

Replace the temporary placeholder logo/icon with the actual official **SL Furnitures** logo.

Relevant screens may include:

- Sign In.
- Registration.
- Email verification.
- Password recovery.
- Other existing Clerk authentication entry screens.

If an authentication flow is rendered by a prebuilt Clerk widget, first determine whether a supported header slot, wrapper, theme customization, or surrounding layout permits the official logo.

Do not fork or modify the Clerk SDK to change its branding.

Preserve Clerk's supported authentication behavior.

### Brand requirements

- Use the same canonical asset as the global app bar where practical.
- Preserve its proportions.
- Provide suitable semantic labeling.
- Use the correct variant for the screen background.
- Avoid multiple competing logos.
- Do not create new brand imagery.
- Do not modify authentication credentials or token handling.

The login screens should visually belong to SL Furnitures without creating a second design system.

## 11. Homepage cleanup

Inspect the Home screen for temporary buttons introduced solely to access screens during development and testing.

Remove obsolete test-navigation buttons, especially temporary standalone entry buttons for:

- Search.
- Contact Us.

These destinations are now available through the navigation drawer.

### Preserve legitimate functionality

Do not remove:

- Product cards.
- Catalog browsing.
- Category exploration components.
- Product discovery content.
- Actual product search functionality.
- Made-to-order request CTAs.
- Product-detail request buttons.
- Meaningful editorial content.

The cleanup is intended to remove redundant development shortcuts, not reduce core customer capability.

If Search has an established, intentional discovery component on the Home screen, distinguish it from a temporary testing button before removing anything.

Do not remove functionality that is not otherwise accessible.

## 12. Route inventory and consistency

Use the actual route names from the repository.

The navigation must account for the currently active customer destinations, including:

```text
Home
Catalog / All Furniture
Categories
Category Detail
Search
Product Detail
Request Furniture
Contact Us
About Us
Privacy Policy
Terms & Conditions
Sign In
Registration
Account / Profile
Open Source Licenses
```

Do not assume all these routes are top-level drawer destinations.

Product Detail is normally reached through product selection.

Sign In and Registration are reached through the guest account sheet.

Account/Profile is reached through the account icon.

Open Source Licenses is found in account details.

Only appropriate public discovery, service, information, and legal routes belong in the main drawer.

### Deferred routes

Do not activate:

- Cart.
- Checkout.
- Payments.
- Customer Orders.
- Order Tracking.

Do not display disabled placeholder navigation items for those features in the production drawer.

The frozen API contracts remain unchanged.

## 13. Global-shell coverage

The app bar should be present consistently across suitable customer screens.

However, do not blindly apply it to every route.

Review whether a screen needs its own navigation treatment, for example:

- Auth screens with dedicated presentation.
- Full-screen media/image viewers.
- Nested product pages where a back action is more appropriate.
- Modal flows.
- Keyboard-focused forms.

Document the selected shell boundaries.

For top-level customer screens, prioritize the global menu/logo/account structure.

For nested screens, preserve an understandable back path and avoid removing expected Android navigation behavior solely for visual consistency.

Do not cause a nested product page to become impossible to dismiss or return from.

## 14. Back-stack and deep-link behavior

Verify these flows:

### Normal navigation

```text
Home → Drawer → Categories → Category Detail → Product Detail
```

Back navigation should return to the expected prior customer context.

### Search

```text
Home → Drawer → Search → Search Results → Product Detail
```

Do not reset search state unnecessarily.

### Guest account

```text
Home → Account icon → Bottom sheet → Close
```

Must remain on Home.

### Guest sign-in

```text
Home → Account icon → Bottom sheet → Sign In
```

After successful authentication, navigate to Home unless a valid existing return-to destination applies.

### Authenticated account

```text
Home → Account icon → Account/Profile
```

### Sign-out

```text
Account/Profile → Sign Out → Public Home
```

After sign-out, tapping Account must again show the guest bottom sheet.

### Deep links

Opening a valid public deep link must not unexpectedly redirect through Home or Sign In.

Opening a valid protected account route while unauthenticated must preserve the existing protected-route policy.

Avoid stack loops, duplicate Home entries, and navigation after a widget has been disposed.

## 15. Design token enforcement

The navigation implementation must use the existing canonical design tokens.

Authority:

`frontend/design-system/tokens.css`

Flutter presentation:

Existing generated Material 3 theme and token constants.

Do not manually introduce an unrelated palette.

Pay particular attention to:

- Drawer surface.
- App bar surface.
- Primary actions.
- Secondary actions.
- Dividers.
- Text hierarchy.
- Active navigation states.
- Focus states.
- Border and corner radii.
- Sheet shape.
- Spacing.
- Typography.

The user-supplied screenshot is a layout and interaction inspiration, not a source of brand colors.

Do not copy the purple Shop Pay button or Bonobos typography.

Maintain consistency with existing SL Furnitures UI.

## 16. Accessibility

The navigation experience must support assistive technology.

### App bar

- Menu button has an accessible label.
- Logo has appropriate semantics.
- Account button has an accessible label.
- Actions meet touch-target expectations.
- Focus order is logical.

### Drawer

- Drawer opening announces the navigation context.
- Search is discoverable.
- Sections are understandable.
- Categories have meaningful labels.
- Expanded categories expose their state appropriately.
- Selected destinations are identifiable.
- Legal links are reachable.
- Drawer content scrolls at large text sizes.

### Account bottom sheet

- The sheet title is announced.
- Keyboard/TalkBack focus enters the sheet correctly.
- Sign In and Create Account are distinctly labeled.
- Close action works.
- Background interactions are correctly blocked while modal.
- Android back dismisses the modal appropriately.
- No clipped controls at 2× text scaling.

Test portrait and landscape orientations.

## 17. Performance and stability

Avoid expensive rebuilding of the navigation shell on every route transition.

Do not repeatedly fetch category data when it is already valid and cached.

Do not initialize multiple Clerk SDK instances.

Do not query the Laravel profile endpoint merely to decide whether a guest sheet should appear if the current Clerk session abstraction already provides the necessary authentication state.

Do not block the Home screen on drawer-category loading.

Dispose of listeners and controllers appropriately.

Keep the drawer responsive on low-end Android devices.

Avoid introducing a new state-management framework just for navigation.

## 18. Automated test coverage

Add tests for the following scenarios.

### Global app bar

1. Menu icon renders.
2. Real logo is used.
3. Account icon renders.
4. Logo remains centered.
5. Drawer opens.
6. Logo navigation works if configured.
7. App bar respects the existing theme.

### Drawer

8. Search appears first.
9. Search opens the existing route.
10. Home navigation works.
11. Catalog navigation works.
12. Categories load from the existing repository.
13. Category selection opens the correct category.
14. Category loading state works.
15. Category empty state works.
16. Category failure does not block other links.
17. Category retry works where available.
18. Drawer closes after selection.
19. Active destination is indicated.
20. About Us navigation works.
21. Contact Us navigation works.
22. Request Furniture navigation works.
23. Privacy Policy navigation works.
24. Terms & Conditions navigation works.
25. Licenses are absent from the main drawer.
26. Deferred commerce destinations are absent.

### Guest account bottom sheet

27. Guest account tap opens the sheet.
28. Sheet has Account heading.
29. Sign In button renders.
30. Create Account button renders.
31. Close button dismisses the sheet.
32. Back dismisses the sheet.
33. Sign In navigates to the existing sign-in route.
34. Create Account navigates to the existing registration route.
35. No invented social login buttons are present.
36. No deferred Orders/Cart buttons are present.

### Authenticated account

37. Authenticated account tap navigates directly to profile.
38. Authenticated tap never displays the guest sheet.
39. Session restoration does not cause guest-sheet flicker.
40. Session expiry follows the existing safe guest behavior.
41. Sign-out restores guest account behavior.
42. Authentication state changes do not create duplicate navigation.

### Authentication branding and routing

43. Sign-in screen shows the real brand logo.
44. Registration screen shows the real brand logo.
45. Verification/recovery screens use approved branding where supported.
46. Normal sign-in success navigates to Home.
47. Normal registration success navigates to Home after the required verification and successful session establishment.
48. Authentication failure does not simulate success.
49. Existing protected-route return-to logic remains valid.

### Regression

50. Product-detail routes still work.
51. Category filters still work.
52. Search results still work.
53. Public furniture requests still work.
54. Public enquiries still work.
55. Authenticated request/enquiry submissions still work.
56. Account/profile editing still works.
57. Legal screens remain public.
58. Licenses can be opened from account details.
59. Home no longer displays obsolete test-navigation buttons.
60. No bottom navigation bar exists.

Use existing test infrastructure and fakes.

Do not require live Clerk or Laravel services for deterministic widget tests.

## 19. Android / Marionette verification

Build and launch the actual Android app.

Use Marionette where available to verify real interactions.

Perform the following manual journeys:

**Journey A — Anonymous discovery**

Launch → Open Drawer → Search → Return → Open Drawer → Category → Product → Request Furniture.

**Journey B — Anonymous contact**

Launch → Open Drawer → Contact Us → Return Home.

**Journey C — Legal content**

Launch → Open Drawer → Privacy Policy → Back → Terms & Conditions → Back.

**Journey D — Guest account**

Launch → Account → Bottom sheet → Close → Account → Create Account.

**Journey E — Authenticated account**

Sign In → Home → Account icon → Profile → Open Source Licenses.

**Journey F — Sign-out**

Authenticated Profile → Sign Out → Home → Account icon → Guest bottom sheet.

**Journey G — Accessibility**

Repeat drawer and bottom-sheet verification at 2× text scaling and in landscape orientation.

Record screenshots or concise interaction results.

If the Clerk integration environment prevents a live authenticated journey, report that limitation explicitly and provide the corresponding deterministic test evidence. Do not claim live success without running it.

## 20. Scope exclusions

Do not implement:

- Bottom navigation bar.
- Cart navigation.
- Checkout navigation.
- Payment UI.
- Orders history.
- Order tracking.
- Favorites.
- Promotional offer banners.
- New search backend.
- New category API endpoints.
- New account features.
- New legal-document content.
- New brand logo designs.
- New navigation packages when go_router is sufficient.
- A second `MaterialApp`.
- A second authentication system.
- Any changes to frozen Laravel API contracts.

Do not make unrelated UI changes.

## 21. Verification commands

From `frontend/app`:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug --dart-define-from-file=config/local.json
git diff --check
git diff --cached --check
```

Run additional targeted navigation, authentication, and widget tests where appropriate.

Report exact command results.

## 22. Documentation updates

Update:

- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- Navigation architecture documentation, if present.
- Phase 17.15 completion record.

Document:

- Shared app-bar ownership.
- Drawer hierarchy.
- Dynamic category data source.
- Public and protected route behavior.
- Account-icon authentication logic.
- Bottom-sheet behavior.
- Successful authentication routing.
- Logo asset usage.
- Where to find Open Source Licenses.
- Home cleanup.
- Why bottom navigation is intentionally omitted for REQUEST ONLY launch.
- Deferred commerce navigation policy.
- Test and device verification results.

Do not silently alter previously frozen decisions.

## 23. Definition of Done

Phase 17.15 passes when all of the following are true:

- One shared top-navigation pattern is working.
- Left menu opens the drawer.
- Official SL Furnitures logo is centered.
- Right account action responds correctly to authentication state.
- Guest users see a themed Material 3 account bottom sheet.
- Guest Sign In and Create Account buttons navigate correctly.
- Authenticated users go directly to Account/Profile.
- Successful normal authentication returns to Home.
- Existing protected-route return-to behavior remains intact.
- Actual catalog categories are accessible from the drawer.
- Search is the first drawer action.
- All active public customer destinations are discoverable.
- Privacy Policy and Terms & Conditions appear in the lower drawer section.
- Open Source Licenses is accessed through Account Details, not the main drawer.
- Actual brand logo replaces temporary auth screen branding.
- Obsolete homepage test-navigation buttons are removed.
- Existing made-to-order request and enquiry workflows remain functional.
- Deferred cart, checkout, payments, orders, and tracking features remain unavailable.
- No bottom navigation bar is introduced.
- Accessibility checks pass.
- Automated tests pass.
- Android debug build succeeds.
- Documentation and completion record are updated.

## 24. Completion report

Return a structured report containing:

1. Navigation architecture chosen.
2. Files modified or created.
3. Real logo asset used.
4. Drawer destinations implemented.
5. Dynamic category integration.
6. Guest account bottom-sheet implementation.
7. Authenticated account behavior.
8. Auth success routing.
9. Homepage buttons removed.
10. Open Source Licenses entry location.
11. Automated tests added and results.
12. Android/Marionette verification.
13. Accessibility findings.
14. Outstanding limitations.
15. Final PASS/FAIL.

**Final instruction:** Implement only Phase 17.15. Prioritize a coherent, attractive, accessible customer navigation experience for the initial REQUEST ONLY production release. Do not add bottom navigation or prematurely activate commerce features.