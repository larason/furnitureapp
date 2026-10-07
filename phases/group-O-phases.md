# PHASE 15.1 — AUTH UI VISUAL REFINEMENT

## Scope: existing Sign In / Sign Up implementation only

The Clerk integration is already implemented.

Do NOT redesign or replace the Clerk authentication architecture.

Make the following visual refinements to BOTH:
```
/sign-in
/sign-up
```

### 1. REMOVE AUTH FORM SHADOW

The current Clerk SignIn/SignUp card has a visible floating box shadow.

This conflicts with the established SL Furnitures design language:

- flat first;
- restrained surfaces;
- elevation only where functionally justified;
- no generic floating SaaS-card appearance.

Remove the shadow from the Clerk SignIn and SignUp form/card.

Use Clerk's supported `appearance` customization rather than brittle global CSS
or selectors into undocumented Clerk internals.

Expected visual result:

box-shadow: none

for the primary SignIn/SignUp authentication card.

Do NOT:

- remove focus rings;
- remove borders that are necessary for field affordance;
- change Clerk validation/error behavior;
- modify modal/dropdown elevation globally;
- remove elevation from the Clerk UserButton account/profile modal.

IMPORTANT:

This requirement applies to the SignIn/SignUp authentication form surface.

It does NOT mean:

"remove every Clerk shadow everywhere."

The account-management modal shown from UserButton may retain appropriate modal
elevation because it is an actual overlay.


### 2. DO NOT REPLACE THE CARD SHADOW WITH ANOTHER EFFECT

Do not compensate by adding:

- stronger borders;
- gradients;
- glow;
- backdrop blur;
- glassmorphism;
- colored shadow;
- floating-card effects.

The auth form should feel calm and flat.


### 3. ADD AUTH EDITORIAL HERO IMAGE

Add the project-owned 16:9 image:

furnitures/fixtures/editorial/signup-hero.jpg

First inspect the repository and resolve its actual public/static asset path.

Do not duplicate the image merely to obtain another URL.

Do not rename or convert it unless there is a genuine technical reason.

Use:

next/image

Do not use a raw ```<img>```.


### 4. IMAGE PURPOSE

This is editorial furniture imagery supporting the authentication experience.

It is NOT:

- a background texture;
- a full-screen wallpaper;
- a promotional banner;
- a carousel;
- a marketing CTA;
- an image with text burned over it.

Do not place arbitrary copy, badges, gradients, buttons, or promotional claims
over the image.


### 5. DESKTOP AUTH COMPOSITION

The current desktop auth page leaves excessive unused white space to the right
of the form.

Use that area deliberately.

Refine the auth-page layout into an asymmetric two-region editorial composition:

LEFT
- page heading;
- Clerk SignIn/SignUp form.

RIGHT
- signup-hero.jpg.

Conceptually:
```
┌───────────────────────────────────────────────────────────────┐
│                                                               │
│  Create your account       ┌───────────────────────────────┐  │
│                            │                               │  │
│  ┌───────────────────┐     │                               │  │
│  │ Clerk SignUp      │     │       furniture hero          │  │
│  │                   │     │            16:9               │  │
│  │                   │     │                               │  │
│  └───────────────────┘     │                               │  │
│                            └───────────────────────────────┘  │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```
The exact dimensions must come from the existing layout system and design
tokens rather than these illustrative proportions.


### 6. PRESERVE ASYMMETRY

Do not turn the page into two rigid 50/50 panels unless the existing responsive
grid naturally produces that result.

The authentication task remains the primary content.

Prefer approximately:
```
auth/content region = narrower
editorial image region = wider
```
while respecting:

ContentContainer
existing grid conventions
approved spacing
responsive breakpoints

Inspect the existing layout primitives before implementing.


### 7. HERO ASPECT RATIO

The supplied source is:

16:9

Preserve that editorial character.

Do not crop it into:

1:1
4:3
4:5
portrait

merely to fill the available space.

Use a stable 16:9 geometry unless inspection of the actual image demonstrates
that the existing approved media treatment requires otherwise.


### 8. IMAGE FIT

Inspect the actual image before selecting object-fit behavior.

Furniture must not be materially cropped.

Prefer a treatment that preserves the intended composition.

Do not blindly use `cover` if important furniture is cut off.


### 9. IMAGE SIZE

The image should be visually substantial on desktop.

It should solve the current excessive blank-right-side composition without
becoming a full-viewport hero.

It should align intentionally with the authentication content.

Do not make it:

- tiny thumbnail;
- decorative icon;
- edge-to-edge full-screen background.


### 10. NO NEW CARD AROUND HERO

Do not wrap the image inside another elevated MUI Card/Paper.

The image itself is the editorial surface.

Avoid:
```
shadow
ornamental frame
excessive rounding
```
Use the existing design-system shape rules.


### 11. SIGN-IN AND SIGN-UP CONSISTENCY

Use the SAME auth layout primitive for:
```
/sign-in
/sign-up
```
Do not implement:
```
SignUpLayout
SignInLayout
```
with duplicated styling.

If the current implementation already has a shared AuthLayout/AuthShell,
extend it.

Otherwise extract the smallest reusable auth-page layout needed.

The hero image should appear consistently on both authentication routes.


12. ROUTE-SPECIFIC CONTENT

The outer heading remains route appropriate.

Sign up:

Create your account

Sign in:

Welcome back

Do not duplicate those headings unnecessarily inside custom wrappers if Clerk
already renders equivalent internal copy in a way that produces awkward visual
repetition.

Preserve the existing implementation unless a small refinement is necessary.


13. CLERK REMAINS FUNCTIONAL AUTHORITY

Do not modify:

- SignUp flow;
- SignIn flow;
- email verification;
- Google sign-in if currently enabled by Clerk configuration;
- password recovery;
- session tasks;
- redirects;
- token handling;
- Laravel integration;
- JIT provisioning;
- UserButton;
- logout.

This task is visual/layout refinement only.


14. MOBILE

On narrow screens, authentication must remain the first task.

Preferred composition:
```
heading
↓
Clerk auth form
↓
editorial image
```
Do NOT put the image above the form if doing so forces the customer to scroll
past a large photograph before reaching authentication.

The image may become smaller on mobile while preserving its 16:9 geometry.

Do not hide it unless actual responsive testing demonstrates that retaining it
creates a significant usability/performance problem.


15. TABLET

At intermediate widths, do not squeeze:

auth form + hero

into unusably narrow columns.

Use the existing responsive breakpoint system.

It is acceptable to switch from two-region desktop composition to stacked
composition before mobile if necessary.


16. PERFORMANCE

Use next/image.

Provide an accurate `sizes` value based on the actual responsive layout.

Do NOT preload the auth hero by default.

The functional authentication UI is more important than loading decorative
editorial imagery.

Keep the image lazy unless measurement demonstrates a real reason otherwise.

Do not introduce another image optimization library.


17. ACCESSIBILITY

Give the image appropriate alt semantics.

If the image communicates only atmosphere and no information necessary for
authentication, prefer decorative semantics:

alt=""

Do not SEO-keyword-stuff the image.

Authentication controls must retain:

labels
keyboard operation
focus visibility
password-manager support
verification controls
error messages


18. EXISTING HEADER

Preserve the current header visible in the screenshots:
```
logo
search
Sign in / Create account or signed-in affordance
sticky category navigation
```
Do not create an auth-specific header.


19. AUTH PAGE WIDTH

The current page should no longer visually appear as:

form occupying the far-left portion
+
huge accidental blank canvas

The new composition should look intentional at wide desktop sizes such as:
```
1440
1728
```
while remaining consistent with ContentContainer.


## 20. DESIGN TOKENS

Use **ONLY** the frozen SL Furnitures tokens for:
```
spacing
color
typography
shape
breakpoints
borders
```
Do not introduce arbitrary pixel values where an approved token exists.

Do not modify token authority merely to implement this refinement.


21. VISUAL DIRECTION

Final appearance should remain:

architectural
warm
editorial
calm
spacious
furniture-focused

Avoid:

generic SaaS auth card
floating card
glassmorphism
large shadow
gradient overlay
marketing badges
excessive rounding
centered login box floating in empty space


22. DO NOT MODIFY ACCOUNT MODAL

The Clerk UserButton account/profile modal visible in the supplied screenshot
is OUT OF SCOPE for this refinement.

Do not globally target `.cl-*` card styles in a way that accidentally removes
its modal elevation or breaks Clerk account management.

The shadow-removal rule must be scoped specifically to the SignIn/SignUp
presentation through supported Clerk appearance configuration.


23. RESPONSIVE VERIFICATION

Visually verify:
```
390px
959px
960px
961px
1440px
1728px
```
Also verify:

200% zoom

Check:

- no horizontal overflow;
- form remains readable;
- image does not dominate mobile;
- image is not distorted;
- furniture is not materially cropped;
- heading remains correctly positioned;
- auth form has no shadow;
- header remains stable;
- sticky category navigation remains correct.


24. AUTH REGRESSION

Run:

npm run test:auth

and all Phase 15.1 relevant regression tests.

At minimum confirm:

SignIn renders
SignUp renders
email/password flow unchanged
verification unchanged
Google provider unchanged if enabled
password recovery unchanged
signed-in header unchanged
UserButton unchanged
catalog hard-404 proxy behavior unchanged


25. STATIC VALIDATION

Run:

npm run typecheck
npm run lint
npm run build
git diff --check


26. REPORT

Return:
```
AUTH UI REFINEMENT

SignIn shadow:
REMOVED / FAIL

SignUp shadow:
REMOVED / FAIL

UserButton account modal shadow:
UNCHANGED / FAIL

Hero source:

<actual repository path>

Hero component:
next/image / FAIL

Hero source ratio:
16:9

Desktop composition:
TWO-REGION EDITORIAL / FAIL

Mobile composition:
AUTH FIRST → HERO / FAIL

Hero preload:

NO / <justification>

Hero alt:
<value>

Shared auth layout:
<component/path>

New arbitrary tokens:
NONE / FAIL

Authentication behavior changed:
NO / FAIL

Clerk architecture changed:
NO / FAIL

Laravel auth bridge changed:
NO / FAIL

390px:
PASS / FAIL

959px:
PASS / FAIL

960px:
PASS / FAIL

961px:
PASS / FAIL

1440px:
PASS / FAIL

1728px:
PASS / FAIL

200% zoom:
PASS / FAIL

test:auth:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL
```

27. STOP

This is a Phase 15.1 refinement.

Do NOT start Phase 15.2.

Do NOT implement:

cart
checkout
orders
account dashboard
profile editor
wishlist
payment

After successful verification, Phase 15.1 remains the active/completed auth
phase according to its existing gate.