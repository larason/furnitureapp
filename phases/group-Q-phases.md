# Phase 17.13 — Flutter Informational, Legal & Open Source Licenses Screens

**Project:** SL Furnitures  
**Application:** Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Phase:** 17.13 (merged with former 17.14)  
**Prerequisite:** Phase 17.12  
**Next phase:** 17.15 — Global App Navigation & Drawer

## 1. Objective

Implement the four informational screens needed for the initial SL Furnitures Android release:

- About Us
- Privacy Policy
- Terms & Conditions
- Open Source Licenses

Treat this as one cohesive implementation phase.

These screens must be accessible to everyone without registration, login, network connectivity, or a Laravel API request.

Use the existing Flutter Material 3 theme and the canonical design tokens generated from `frontend/design-system/tokens.css`.

Do not build a new navigation shell, redesign the existing app, or introduce unnecessary dependencies.

## 2. Mandatory repository inspection

Before editing code, read:

1. Root `AGENTS.md`, particularly Group Q and the phase operating rules.
2. `frontend/app/README.md`.
3. `frontend/app/lib/features/README.md`.
4. `frontend/design-system/tokens.css`.
5. `frontend/design-system/DESIGN.md`, if present.
6. `frontend/design-system/ACCESSIBILITY.md`, if present.
7. Existing Flutter Material 3 theme implementation.
8. Existing generated Flutter design tokens.
9. Existing go_router route definitions.
10. Existing feature-first folder structure and dependency injection conventions.
11. Existing Flutter tests and test helpers.
12. All relevant `.txt` files in the project root containing the Privacy Policy and Terms & Conditions.

First report the exact legal source filenames found and confirm that their content is readable.

Do not assume their filenames, encodings, or internal heading structures.

If either legal source is missing, stop the affected legal-page implementation and report the missing file. Do not invent replacement legal content.

## 3. Architecture and feature ownership

Create a lightweight informational feature consistent with the existing Flutter architecture.

A possible structure is:

```text
frontend/app/lib/features/information/
  presentation/
    about_us_screen.dart
    privacy_policy_screen.dart
    terms_conditions_screen.dart
    open_source_licenses_screen.dart
    widgets/
      information_page_scaffold.dart
      legal_document_view.dart
```

Adjust this to existing repository conventions.

Do not create unnecessary repositories, controllers, state-management layers, or service abstractions for static text.

A dedicated local document loader is acceptable if it simplifies asset management and testing.

### Separation of concerns

- About Us: presentation and locally defined factual content.
- Privacy Policy: display the authoritative text asset.
- Terms & Conditions: display the authoritative text asset.
- Open Source Licenses: display Flutter's actual registered dependency licenses.
- Routing: existing centralized go_router.
- Styling: existing generated theme and design tokens.

No new Laravel API is needed.

## 4. About Us screen

Create a polished but simple About Us page that communicates the SL Furnitures business and its services.

### Content

Use only verified business information from the project documentation or existing approved brand content.

The page may describe:

- SL Furnitures as a furniture business.
- Its focus on furniture products and made-to-order requests.
- How customers can explore the catalog.
- How customers can request furniture.
- How customers can submit general enquiries.
- The business's commitment to clear communication and useful furniture information.

### Important content restrictions

Do not invent:

- A founding year.
- Founder names.
- Workshop or showroom addresses.
- Phone numbers.
- Email addresses.
- Business registration numbers.
- Customer testimonials.
- Manufacturing capacity.
- Delivery coverage.
- Warranties or guarantees.
- Sustainability certifications.
- Claims about materials or production processes that have not been verified.

If official About Us content already exists in the repository, reuse it rather than generating competing wording.

### Suggested layout

- Page heading: "About SL Furnitures".
- Brief introductory section.
- What We Offer.
- Made-to-Order Furniture.
- Getting in Touch.

Use readable paragraphs and restrained visual hierarchy.

Avoid unnecessary animation, decorative counters, statistics, and marketing carousels.

A contact action may navigate to the existing `/contact` route if it is available.

Do not create a second contact form.

## 5. Privacy Policy screen

Locate the actual Privacy Policy `.txt` file in the project root.

Read the entire document.

### Source-of-truth rule

The file's existing text is authoritative.

Do not:

- Rewrite it.
- Summarize it.
- Paraphrase it.
- Generate additional legal clauses.
- Remove sections.
- Change dates.
- Change business names.
- Replace contact information.
- Silently correct wording.
- Alter its legal meaning.

Preserve the complete document, including headings, paragraphs, lists, effective dates, and contact details.

### Asset strategy

Bundle the document as a Flutter asset so that it is available offline.

Prefer a single maintained source with a reproducible build-time copy/synchronization process, rather than manually maintaining two independent versions.

If the app build cannot directly bundle a file outside its package root, use an app-local asset copy with an explicit synchronization/check process.

Ensure changes to the root source are detectable by tests or a documented check.

Do not load the legal text from a remote URL at runtime.

### Presentation

- Heading: "Privacy Policy".
- Scrollable text content.
- Clear paragraph spacing.
- Preserve logical section breaks.
- Support selection/copying where appropriate.
- Support large text scaling.
- Use the existing theme.
- Handle asset loading failures without crashing.

The page must not require Clerk authentication.

## 6. Terms & Conditions screen

Apply the same source and rendering rules as the Privacy Policy.

Locate and read the actual Terms & Conditions `.txt` file from the project root.

Bundle the complete text into the Flutter app.

Do not generate or rewrite legal terms.

### Presentation

- Heading: "Terms & Conditions".
- Scrollable content.
- Readable typography.
- Consistent spacing.
- Selectable text where practical.
- Large-text support.
- Offline access.
- Accessible headings and navigation.

### Legal document consistency

Privacy Policy and Terms & Conditions should use the same reusable document presentation component.

However, do not force a formatting transformation that changes the original content.

Preserve the source documents' order and meaning.

## 7. Legal content integrity

Create focused checks to protect the legal text against accidental changes.

At minimum, verify:

1. The Privacy Policy source exists.
2. The Terms & Conditions source exists.
3. Both files contain nonempty text.
4. Both are valid for the chosen asset encoding.
5. Both assets are registered correctly in `pubspec.yaml`.
6. Both can be loaded by Flutter.
7. Both are displayed in full.
8. No placeholder legal text is substituted.
9. The bundled content matches the authoritative source after only explicitly documented, non-semantic encoding normalization.

Prefer byte-for-byte or normalized-text equality checks between the root source and bundled asset.

Do not rely solely on widget tests that check whether the heading appears.

If legal content is updated in the future, the app must be rebuilt and redistributed for the bundled change to reach installed users.

Document this behavior.

## 8. Open Source Licenses screen

Implement a dedicated Open Source Licenses page.

### Authoritative license source

Use Flutter's license registry and the licenses bundled by Flutter/Dart packages.

Prefer the framework-provided `LicensePage` or the corresponding supported license APIs.

Do not manually construct a hard-coded list of packages and licenses.

Do not copy license text from package websites as the primary implementation.

### Requirements

- Show the actual licenses registered by the Flutter application.
- Preserve the full license texts.
- Allow users to scroll and read them.
- Provide appropriate package grouping and navigation.
- Support text scaling.
- Use the existing app theme where the framework permits.
- Work offline.
- Avoid exposing internal project paths or development-only metadata.

### Branding and appearance

The page should feel consistent with the application without compromising the correctness of third-party license attribution.

Using Flutter's standard `LicensePage` is acceptable, even if its internal layout differs slightly from the custom informational screens.

Do not fork Flutter's license presentation solely for visual customization.

### App-owned notices

If the project includes third-party fonts, images, icons, or other assets with attribution obligations that are not automatically registered, inspect their licenses and include any required notices through an appropriate compliant mechanism.

Do not assume every asset is covered by the package registry.

Do not include fictional license entries.

## 9. Shared informational page design

Use one simple, reusable presentation pattern for About Us, Privacy Policy, and Terms & Conditions.

### Layout

- Existing Material 3 page background.
- Clear page heading.
- Readable content width.
- Appropriate horizontal padding.
- Comfortable vertical rhythm.
- Consistent paragraph spacing.
- Standard back navigation.
- Accessible scrolling.

Use the generated Flutter tokens for:

- Colors.
- Typography.
- Spacing.
- Borders.
- Radii.
- Surface treatments.

Avoid new raw color literals and arbitrary font definitions.

### Typography

Use the existing editorial typography conventions.

Use display typography sparingly for headings.

Use the approved UI/body font configuration for long legal documents.

Legal readability takes priority over decorative typography.

Do not use unusually small text to fit more content on the screen.

## 10. Navigation and routing

Register public routes in the existing central go_router.

Recommended route paths, unless the repository already defines canonical alternatives:

```text
/about
/privacy-policy
/terms-and-conditions
/open-source-licenses
```

Use existing route naming conventions.

### Route behavior

- No authentication required.
- No Clerk session required.
- No Laravel request required.
- Direct navigation works.
- Back navigation works.
- Cold-start navigation is safe.
- Route errors are handled.
- Legal content remains available offline.

Do not implement the final shared app bar or navigation drawer.

That work belongs to Phase 17.15.

### Temporary discoverability

Ensure the screens can be reached through an existing appropriate public UI location or tested through the router without adding a new global navigation system.

Do not add a temporary navigation component that will need to be removed in Phase 17.15.

If an existing account screen already has an appropriate informational section, linking from it is acceptable, provided the pages themselves remain public.

## 11. Accessibility

Test the informational screens for:

- TalkBack traversal.
- Semantic headings.
- Logical reading order.
- Sufficient text contrast.
- 2× text scaling.
- Small Android screens.
- Landscape orientation.
- Keyboard and focus behavior where applicable.
- Scroll position and navigation.
- Long paragraphs.
- Long URLs.
- Lists and numbered sections.
- Text selection.
- No horizontal overflow.
- No clipped document text.

The legal documents must be readable from beginning to end.

Do not introduce fixed-height containers that cut off content.

## 12. Loading and error states

Use existing application conventions for asynchronous asset loading.

Expected behavior:

- Display a modest loading state while an asset is read.
- Show the document once loaded.
- Handle asset loading failure with a clear error presentation.
- Avoid displaying a blank screen.
- Avoid logging entire legal documents.
- Avoid infinite retry loops.

Because these are bundled local assets, do not introduce network retry mechanisms.

The About Us page should not need a loading state if it uses static verified content.

## 13. Privacy and security

These pages are public.

Do not attach Clerk tokens to local document loading.

Do not collect analytics or personal data solely because a user opens a legal page.

Do not introduce WebViews to render plain-text legal files.

Do not fetch third-party license text from the internet during ordinary app usage.

Do not modify backend authorization or the frozen API contract.

## 14. Automated tests

Add focused tests covering the consolidated phase.

### About Us

1. Screen renders.
2. Approved business content appears.
3. Contact action uses the existing route if present.
4. No invented commerce controls.
5. No authentication requirement.

### Privacy Policy

6. Route resolves.
7. Source asset loads.
8. Full content is rendered.
9. Source/asset synchronization passes.
10. Long content scrolls.
11. Text scaling works.
12. Asset failure is handled.

### Terms & Conditions

13. Route resolves.
14. Source asset loads.
15. Full content is rendered.
16. Source/asset synchronization passes.
17. Long content scrolls.
18. Text scaling works.
19. Asset failure is handled.

### Open Source Licenses

20. Route resolves.
21. Flutter license registry is used.
22. Package licenses can be opened.
23. License content is readable.
24. No manually fabricated licenses.
25. Screen works without network access.

### Navigation and regressions

26. All informational routes are public.
27. Back navigation works.
28. Cold-start routing works.
29. Anonymous users can open legal pages.
30. Authenticated users can open legal pages.
31. Clerk initialization failure does not block legal pages.
32. Existing catalog routes remain unchanged.
33. Furniture Requests remain unchanged.
34. Enquiries remain unchanged.
35. Account authentication remains unchanged.
36. Deferred commerce routes remain inactive.

Use deterministic tests.

Do not require live Laravel or Clerk services.

## 15. Android device verification

Use an Android emulator or physical device and Marionette where available.

Verify:

- About Us.
- Privacy Policy.
- Terms & Conditions.
- Open Source Licenses.
- Full document scrolling.
- Text selection.
- Back navigation.
- Small-screen layout.
- Landscape.
- 2× text scaling.
- Offline access.
- Anonymous access.
- License package expansion.
- No overflow or clipped text.

For legal content, explicitly confirm that the final paragraph is reachable.

## 16. Documentation

Update:

- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- The Phase 17.13 completion record.
- Any relevant asset-management documentation.

Document:

- Actual root legal source filenames.
- How legal files become Flutter assets.
- How to update legal text safely.
- How synchronization is verified.
- Informational route names.
- About Us content source.
- Open-source license implementation.
- Offline behavior.
- Accessibility checks.
- Device verification.
- Test results.

Mark former Phase 17.14 as merged into Phase 17.13.

Do not create a separate Phase 17.14 completion record unless the existing documentation process explicitly requires a historical pointer.

## 17. Verification commands

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

Run the legal-asset synchronization check if implemented as a separate command.

If documentation-only changes do not affect the Flutter build, still run the standard phase verification to guard against regressions.

Report actual results rather than assuming success.

## 18. Explicit exclusions

Do not implement:

- Phase 17.15 global navigation shell.
- Shared global drawer.
- Final shared app bar.
- New account dashboard features.
- New authentication flows.
- Cart.
- Checkout.
- Payments.
- Orders.
- Order tracking.
- Customer request history.
- Enquiry history.
- Push notifications.
- Legal consent tracking.
- Legal acceptance checkboxes.
- New Laravel endpoints.
- Remote CMS integration.
- Analytics or tracking SDKs.
- Custom license-management backend.
- New design tokens unrelated to the existing theme.

Do not use this phase to refactor unrelated Flutter features.

## 19. Definition of Done

Phase 17.13 is complete when:

- About Us renders verified business information.
- Privacy Policy displays the complete authoritative source text.
- Terms & Conditions displays the complete authoritative source text.
- Legal source/asset synchronization is verified.
- Open Source Licenses displays real registered dependency licenses.
- All four routes are public.
- All four pages work offline.
- The existing Material 3 theme is respected.
- Large text and long-document scrolling work.
- Back navigation works.
- No new backend dependencies are introduced.
- Automated tests pass.
- Device checks pass or their limitations are explicitly recorded.
- The debug APK builds.
- Documentation is updated.
- Former Phase 17.14 is recorded as merged.
- Phase 17.15 remains untouched.

## 20. Completion report

Return a concise report covering:

1. Exact legal source files discovered.
2. About Us content source.
3. Files created or modified.
4. Public routes registered.
5. Legal asset synchronization strategy.
6. Open Source Licenses implementation.
7. Accessibility checks.
8. Automated tests and counts.
9. Device/Marionette verification.
10. Flutter analysis and build results.
11. Documentation updates.
12. Known limitations.
13. Final PASS/FAIL.

**Final instruction:** Complete Phase 17.13 as one consolidated informational phase. Do not proceed to Phase 17.15 until this phase has passed verification. Phase 17.14 has no separate implementation work.