# SL Furnitures — Customer App (Flutter)

Android customer application for the SL Furnitures platform. It consumes the same
Laravel API as the Next.js website and shares the project design system.

## Scope

- **Platform:** Android only. iOS, web, and desktop targets are intentionally not
  generated and must not be added without an explicit decision (ADR/DESIGN-007).
- **Launcher label:** `Buy Furnitures`
- **Application ID:** `com.slfurnitures.app`
- **Dart package:** `sl_furnitures`

## Phase status

This project contains the **Phase 16.1 (project setup)**, **Phase 16.2 (theme /
Material 3)**, **Phase 16.3 (environment configuration)**, **Phase 16.4
(networking layer)**, **Phase 16.5 (authentication storage/session)**,
**Phase 16.6 (routing/navigation)**, and **Phase 16.7 (feature/module
structure)**, **Phase 16.8 (error/loading states)**, **Phase 16.9
(logging/diagnostics)**, **Phase 17.1 (home and catalog)**, **Phase 17.2
(categories)**, **Phase 17.3 (product detail)**, **Phase 17.4 (search and filtering)**,
**Phase 17.10 (furniture requests)**, **Phase 17.11 (enquiries)**, and
**Phase 17.13 (informational, legal, and open-source licenses; former 17.14
merged)**.

Group P is complete. Group Q owns customer feature implementation.

The root application now uses `MaterialApp.router`. Home, catalog, categories,
and product detail are implemented feature screens; the remaining routes are
placeholders that establish the Phase 16.6 navigation contract only. The
development-only theme preview remains directly testable but is no longer the
application home screen.

## Navigation

`lib/navigation/` is the single route registry. Public routes are home, catalog,
product/category details, search, made-to-order requests, contact/enquiries,
and the future Clerk sign-in/sign-up entry points. Informational routes are public
and offline-readable: `/about`, `/privacy-policy`, `/terms-and-conditions`, and
`/open-source-licenses`. `/account` is the sole protected placeholder route.

The router refreshes from `ClerkAuthAdapter` without requesting or retaining a
token. Protected routes redirect to a Clerk entry point when signed out and to a
neutral status screen while a session is restoring, needs action, or is
temporarily unavailable. Public catalog and request routes remain available in
all of those states.

After sign-in, the router restores only the allow-listed internal `/account`
destination. It rejects external origins, authentication-flow loops, query
parameters, and malformed resource identifiers. Android App Links are deferred
until a production domain, manifest intent filters, `assetlinks.json`, and
device verification are available.

`/products/{slug-or-id}` is now the implemented product-detail screen. The
router still validates the parameter with `ResourceIdentifier` before any
request, and the screen resolves unknown, unpublished, or otherwise non-public
products as an unavailable state that returns to the catalog. Catalog cards and
home cards navigate with the server-returned **slug**; the opaque ID remains
accepted because `CAT-002` resolves either.

## Feature Modules

Feature code is organized by customer capability under `lib/features/`, with
the convention documented in `lib/features/README.md`. Future Group Q work may
add `data/`, `domain/`, and `presentation/` inside the owning feature, but small
features should not create layers without a concrete responsibility.

Feature composition uses explicit constructor injection through
`FeatureDependencies`. It supplies the existing `ApiClient` and, when needed,
the SDK-independent `AuthSession` boundary. The application composition root
owns and disposes the API client, transport, and Clerk adapter; feature modules
do not dispose shared services.

The dependency direction is:

```text
feature presentation -> feature state -> feature repository -> core ApiClient
```

Features do not create HTTP clients, initialize Clerk, read secure storage or
compile-time configuration, parse API error envelopes in widgets, or duplicate
Laravel authorization. The central `lib/navigation/` router remains the only
route registry and `MaterialApp` root.

Group Q feature implementation must remain request-first. Catalog, categories,
product detail, search, furniture requests, and enquiries are implemented;
account is a future feature family. Cart, checkout, payments, orders, order
tracking, and favorites remain unimplemented and unregistered.

### Enquiries (Phase 17.11)

`lib/features/enquiries/` replaces the `/contact` placeholder with the public
ENQ-001 enquiry form. It is a separate domain from `furniture_requests/`: the
contact page never creates a furniture request, order, quotation, payment, or
reservation, and only links to the Phase 17.10 route.

- Anonymous visitors submit with a name plus at least one reachable phone or
  email. An authenticated CUSTOMER uses the existing Clerk bearer boundary, and
  Laravel derives ownership. No user identifier is ever sent, and a signed-in
  session that cannot produce a token stops the submission instead of silently
  degrading to an anonymous enquiry.
- Validation mirrors the frozen bounds (subject 5-200, message 10-5000, name
  120, phone 30, email 255) for usability only; Laravel remains authoritative.
  Server field paths map back onto the matching controls.
- `category` and `order_id` are not modelled: `category` is optional and staff
  triage from `subject`/`message`, and `order_id` belongs to the deferred order
  phases.
- Zero or one private JPEG, PNG, WebP, or PDF up to 5 MiB is sent inline on the
  same `multipart/form-data` ENQ-001 submission under the canonical
  `attachment` field. Bytes stay in memory; only the file name and size are
  shown. `ENQ-007` separate upload is not used.
- The submission lifecycle is `editing -> submitting -> success | uncertain`.
  The action is disabled while in flight so rapid taps cannot duplicate. A
  timeout or connection failure is an **uncertain** outcome: it is never
  auto-retried, never reported as "not submitted", and the warning survives
  editing until the customer deliberately resubmits. A local cancellation is
  never presented as a server rejection.
- Optional product context is internal: the router builds it only from an
  already-loaded `ProductDetail` passed as route `extra`. No product-detail
  button was added, no identifier is derived from a name, and browsing history
  never attaches a product implicitly.
- The confirmation view shows only the returned `enquiry_status` and the opaque
  reference. It claims no email or SMS delivery, promises no response time, and
  never navigates to a private enquiry endpoint.
- `lib/core/attachments/pending_attachment.dart` owns the frozen attachment
  limits and the content-type hint. Phase 17.10 keeps its own equivalent so
  Furniture Requests remain unchanged.

### Search and filtering (Phase 17.4)

`lib/features/search/` owns the public search screen and criteria state. It
reuses `CatalogRepository`, `CatalogController`, `ProductGridSliver`, the Phase
17.2 category repository, shared async/error views, and the central `go_router`.

- CAT-001 requests use only the documented `search`, `category`, `sort`,
  `sort_direction`, `page`, and `per_page` parameters, plus the fixed
  `product_type=MADE_TO_ORDER` restriction.
- Search text is trimmed, capped at 100 characters, and submitted after a 350 ms
  debounce or immediately from the keyboard/action button.
- Category and sort changes reset to page one. Server pagination and ordering
  remain authoritative; stale requests are cancelled/discarded and duplicate
  products are suppressed while loading another page.
- Fixture mode provides deterministic name/slug/category search, supported
  sorting, pagination, empty results, and the made-to-order restriction. It is
  local-debug-only and never a fallback after an API failure.
- Results reuse shared product cards and navigate with the server product slug
  to product detail. No unsupported availability, price, material, colour,
  rating, discount, recommendation, cart, checkout, or payment control was
  introduced.
- The submit button explicitly uses the theme `primary`/`onPrimary` pair so its
  search icon remains visible on the charcoal action surface.

## Catalog, Categories, and Product Detail

Phase 17.1, 17.2, and 17.3 read the public Laravel catalog only:

- `GET /api/v1/products` with `product_type=MADE_TO_ORDER`. `CatalogRepository.fetchProducts` takes an optional `categorySlug`, which becomes the canonical `?category={slug}` filter.
- `GET /api/v1/categories` (`CAT-003`) returns the paginated summary collection. `GET /api/v1/categories/{category}` (`CAT-004`) resolves one category by its server slug or opaque ID.
- `GET /api/v1/products/{product}` (`CAT-002`) resolves one product by its server slug or opaque ID and is the only request the product-detail page makes.
- Category products always come from `GET /api/v1/products?category={slug}&product_type=MADE_TO_ORDER`. The nested `/categories/{category}/products` route is rejected by ADR/API-END-002 and is never requested.

`CategorySummary` and `CategoryDetail` model only the documented fields: `id`,
`name`, `slug`, nullable `image.url`, and (detail only) `description` and
`created_at`. There is no `children` field, because CAT-003 is not a recursive
tree. The Laravel taxonomy has a structural `Furnitures Root` and deeper levels,
but the public collection returns only active storefront categories beneath the
root, and the app never fabricates subcategory navigation from that deeper
taxonomy.

Category identity flows one way: the router accepts a slug or opaque ID, CAT-004
resolves it, and the resolved canonical slug scopes the product query. Slugs are
never derived from display names, and a changed category rebuilds the listing so
previous products cannot survive.

Category loading is failure-independent. A product-listing failure keeps the
valid category header on screen with a recoverable inline message; a CAT-004
`RESOURCE_NOT_FOUND` (404) becomes an unavailable-category state and returns to
the index. A category that resolves but has no furniture shows a
category-specific empty message, never an empty state that implies the category
itself is missing.

Category and product photography share `CatalogImage`, so loading feedback,
neutral fallback, aspect ratio, and semantics cannot drift between the two
features. Category imagery is presentation-only: no crop overlays text, and no
contrast depends on a photograph.

All three features honor `CATALOG_DATA_SOURCE=fixtures` in local debug builds
only. The fixture taxonomy mirrors the approved Next.js `HOMEPAGE_CATEGORY_FIXTURES`
and the Laravel `CategorySeeder` rooms, and fixture products stay scoped to their
own category. Staging and production always read the real API, and no code path
falls back to fixtures after an API error.

### Product detail (Phase 17.3)

`lib/features/product_detail/` owns the CAT-002 read and the single product page:

- `ProductDetail` extends the shared `ProductSummary` rather than re-parsing it. Identity, price, availability, and the primary image are decoded once by `catalog/data/product_summary.dart`; product detail adds only `description`, the full `images[]` gallery, the embedded `variants[]`, the category description, and the timestamps. `ProductDetail.summary` projects back to a summary so any `CAT-001` consumer can read a detail product.
- `ApiProductDetailRepository` performs one anonymous `GET /products/{product}` through the existing `ApiClient` in public auth mode. No bearer token, no session, no `CAT-005` call, and no request per embedded variant.
- The gallery keeps the backend's `sort_order ASC, id ASC` order as received, opens on the image flagged `is_primary`, and navigates by native horizontal swipe inside a `PageView` at the canonical `--media-product-hero` ratio. A single image shows no position indicator; two or more show one live-region "Showing image N of M" announcement plus a dot row. There is no autoplay, no zoom, no custom gallery engine, and no gallery dependency. Off-screen photographs are not built and therefore not announced.
- Options are a single-choice `RadioGroup` keyed on the stable variant ID, showing only the contract fields: name, SKU, price, and the coarse `availability`/`stock_indicator`. Nothing is selected by default, so the product's own base price stays visible until the customer chooses, and the displayed price switches to the chosen option's price. A selection that does not belong to the loaded product is ignored, and a reload clears it.
- Prices stay integer minor units and reuse the Phase 17.1 `formatTzs`. A made-to-order figure is presented as `From TZS …` with an explicit note that it is indicative and confirmed through the request conversation — never as a quotation. No discount, installment, deposit, or saving is invented.
- Category navigation uses the embedded category's server slug and the existing `/categories/{slug}` route. No second category route, repository, or locally derived slug exists.
- A `RESOURCE_NOT_FOUND` (404) becomes an unavailable-product state that returns to the catalog. It never reveals whether an unpublished product exists, and it is never confused with a network failure, which stays a retryable `AppErrorView`.
- A new route identifier rebuilds the controller, and obsolete requests are cancelled and their responses discarded, so one product can never appear under another.
- **Request-only behaviour is preserved.** `ProductRequestAction` enables the
  "Request this furniture" button and opens the Phase 17.10 request flow. The
  flow submits a `REQ-001` request, not an order, payment, deposit, reservation,
  or cart action.

Development fixture details reuse the shared `fixtureProductSummaries` so a
catalog card always opens a detail page with the same identity, category, price,
and cover image. The description is the copy the Next.js fixture data already
approves. **The second gallery image and the two options per fixture product are
synthetic development values added by Phase 17.3** so the position indicator and
option selection can be exercised locally; they are not inventory and not a claim
about any real product. An unknown fixture identifier returns the same
`RESOURCE_NOT_FOUND` (404) the API returns for a non-public product.

## Async Presentation

`lib/core/presentation/` contains the shared Phase 16.8 presentation contract:

- `AsyncViewState<T>` provides typed initial, loading, content, empty, failure, refreshing, and submitting states without a global state manager.
- `AppLoadingView` supports full-content and inline progress with accessible status semantics.
- `AppEmptyView` distinguishes successful empty data from failure and accepts an optional caller-owned action.
- `AppErrorView` and `AppInlineError` render safe mapped failure descriptions; recovery is explicit and caller-owned.
- `ErrorPresentationMapper` consumes parsed `ApiError`/`ApiTransportException` values, preserves field errors and request IDs, and suppresses cancellation UI.

Features must not parse raw HTTP bodies or duplicate API error-code handling.
They decide whether a retry or refresh is safe and pass the callback to the
shared widget. The mapper does not start authentication, sign the user out, or
retry requests automatically. The existing `AuthSession` and router continue to
own authentication states and access policy.

## Diagnostics

`lib/core/diagnostics/` provides the SDK-independent diagnostics boundary. Future
features receive `AppDiagnostics` through the existing composition boundary and
record typed `DiagnosticEvent` values with one of the closed severity levels:
`debug`, `info`, `warning`, `error`, or `critical`.

Events use closed categories and codes. Their context is allow-listed to approved
fields such as environment, operation, HTTP method, status, stable Laravel error
code, numeric `Retry-After`, transport category, approved route-failure category,
and a validated Laravel request ID. Request/response bodies, URLs and query
parameters, headers, Clerk credentials, secure-storage contents, identity data,
user text, raw exception messages, and production stack traces are not supported
event fields.

Diagnostics use the existing `ENABLE_DIAGNOSTICS` configuration field. Local and
staging output is available only when explicitly enabled. Production always uses a
no-op policy, even if the flag is accidentally enabled. Sink failures are isolated
from application operations, and the test sink is bounded in memory.

The application root installs framework and unhandled-async error capture, while
`ApiClient`, `ClerkAuthAdapter`, secure persistence, and the central router own
their respective expected failure events. `ApiClient` records every eligible
transport failure exactly once — whether the transport already fails with an
`ApiTransportException` or surfaces a raw `dart:io`/`package:http` error that the
client converts into one — so a connection failure is no longer invisible.
Cancellation remains silent and the diagnostics layer never retries, presents UI,
starts authentication, or uploads events remotely.

Phase 17.3 deliberately records **no** product-detail diagnostic events. Every
CAT-002 failure it can surface — transport failure, timeout, rate limit,
`RESOURCE_NOT_FOUND`, and an invalid or malformed product payload — is already
recorded by `ApiClient`, which owns those codes, and a second event would
duplicate the same failure. The closed `DiagnosticCode` vocabulary has no
feature-level code, and adding one for a single screen would widen the boundary
without a cross-feature need. Image presentation failures degrade to the shared
neutral `CatalogImage` fallback instead of being logged, so image loading behaves
identically on every catalog screen. Nothing about a product URL, query
parameter, response body, raw exception, product identifier, or customer
information is recorded anywhere.

Future feature example:

```dart
diagnostics.record(
  DiagnosticEvent.now(
    level: DiagnosticLevel.error,
    category: DiagnosticCategory.network,
    code: DiagnosticCode.apiRequestFailed,
    context: const DiagnosticContext(
      operation: DiagnosticOperation.apiRequest,
    ),
  ),
);
```

## Networking layer

Phase 16.4 uses maintained `package:http` rather than Dio. The dependency is
small, supports `AbortableRequest`, and does not require an interceptor or
automatic-retry framework.

The domain-neutral boundary is in `lib/core/network/`:

- `ApiClient` receives validated `AppConfig`, an injectable `ApiTransport`, and
  an optional `AuthTokenProvider`.
- `HttpApiTransport` owns a `package:http` client only when it creates one;
  injected transports remain caller-owned.
- Requests use the configured API origin and append `/api/v1` exactly once.
- Public requests use `Accept: application/json`; JSON bodies additionally use
  `Content-Type: application/json`.
- Authenticated requests explicitly use `ApiAuthMode.required` and obtain the
  current token at request time. The client never stores bearer credentials.
- JSON and multipart bodies are supported at the transport boundary. Multipart
  bodies are finalized into an abortable request and do not accept a caller
  supplied content type.

Example public request:

```dart
final response = await apiClient.get<Map<String, Object?>>(
  '/products',
  queryParameters: {'per_page': 20},
  decoder: (value) => Map<String, Object?>.from(value! as Map),
);
```

Example authenticated request with a test-only provider:

```dart
final client = ApiClient(
  config: config,
  authTokenProvider: TestTokenProvider('token-used-only-for-a-test'),
);
final response = await client.get<Object?>(
  '/me',
  authMode: ApiAuthMode.required,
);
```

Successful responses are parsed as `data` plus optional typed `meta`, including
`meta.pagination`. API failures preserve HTTP status, all structured errors,
`meta.request_id`, and numeric `Retry-After` seconds. When both request-ID
sources exist, the response body's `meta.request_id` is authoritative and the
`X-Request-Id` header is the fallback.

Connection, timeout, cancellation, malformed response, invalid envelope, and
unsupported content-type failures remain distinct from Laravel API errors. The
client never retries or replays requests. Redirects are disabled so bearer
credentials cannot cross an origin boundary.

The default timeout is 10 seconds and every request accepts an explicit
`RequestCancellation`. Closing an `ApiClient` closes only a transport created by
that client; injected transports remain caller-owned. Startup constructs the
client after configuration validation but performs no API request.

## Authentication session boundary

Phase 17.12 uses `clerk_auth` and the official Flutter companion
`clerk_flutter` beta SDKs (`0.0.18-beta`) behind `ClerkAuthAdapter`. The
adapter owns the one shared `ClerkAuthState`; `ClerkAuth` receives that state
for the official `ClerkAuthentication` flow and never creates a second session
lifecycle. Clerk owns email/password sign-up, email-code verification, sign-in,
password recovery, session restoration, renewal, and sign-out. The app exposes
only the current session token through `AuthTokenProvider`; `ApiClient` obtains
it at request time and never stores bearer credentials.

The SDK's default file persistor is not used because it writes plaintext JSON.
`SecureClerkPersistor` stores the SDK's required client/session state through
`flutter_secure_storage`, which uses Android Keystore-backed encryption. Android
backup is disabled in the main manifest to prevent encrypted storage metadata
from being restored without its device key.

The package is beta. The adapter isolates the SDK dependency so API consumers
remain independent of Clerk widget internals. The Material 3 theme supplies a
`ClerkThemeExtension` from generated canonical tokens. `/sign-in` and
`/sign-up` use Clerk's prebuilt flow when the app has an initialized adapter;
`/account` reads and updates Laravel's frozen `GET/PATCH /api/v1/me` profile
representation. Only `name` and `phone` are submitted to Laravel. Password,
email verification, recovery, and session controls remain Clerk-owned.

## Marionette MCP

The debug app initializes `MarionetteBinding` for runtime Flutter interaction;
the binding is skipped under `flutter test` to avoid competing with Flutter's
test binding. The MCP server is configured for OpenCode and VS Code with:

```bash
dart pub global activate marionette_mcp
dart pub global run marionette_mcp
```

Run the app in debug mode, copy its VM service URI from the Flutter console,
then connect Marionette to inspect widgets, tap controls, enter text, scroll,
and capture screenshots. A hot restart is required after changing the binding
in `main.dart`.

## Environment configuration

Configuration uses Flutter's compile-time mechanism, `--dart-define-from-file`.
There is no runtime `.env` loading and no environment-management package.

```text
lib/config/
  app_environment.dart    # closed enum: local, staging, production
  app_config.dart         # immutable AppConfig value object
  config_validation.dart  # deterministic validator + compile-time reader
config/
  local.example.json
  local-device.example.json
  staging.example.json
  production.example.json
```

`main()` calls `ConfigValidator.loadCompileTime()` **before** `runApp`. Invalid
configuration never starts the app: a `ConfigFailureApp` screen is shown with a
diagnostic that names fields and rules only, never a configuration value.

### Supported fields

| Field | Required | Notes |
| --- | --- | --- |
| `APP_ENV` | always | `local`, `staging`, or `production`. Unknown or missing values are rejected — there is no fallback. |
| `API_BASE_URL` | always | Laravel API **origin** only. Do not add `/api/v1`; Phase 16.4 applies the frozen version and paths. |
| `CLERK_PUBLISHABLE_KEY` | staging, production | Optional locally (no auth flow exists yet). Must start with `pk_`. |
| `ENABLE_DIAGNOSTICS` | no | Defaults to `false`. Non-secret startup logging only. |
| `CATALOG_DATA_SOURCE` | no | `api` (default) or development-only `fixtures`. Fixtures require `APP_ENV=local` and a debug build. |

Any other field — including `CLERK_SECRET_KEY` — is rejected.

### Catalog data source

Home and catalog use public `GET /api/v1/products` with
`product_type=MADE_TO_ORDER`, server pagination, and no Clerk token. API mode is
the default and never falls back to fixture data after a failed request.

For an image-rich, development-only catalog without a populated Laravel database,
copy `config/local.example.json` to ignored `config/local.json` and add:

```json
"CATALOG_DATA_SOURCE": "fixtures"
```

Fixture mode is rejected in profile/release builds and outside `local`. It uses
the three existing web fixture products and bundled project image assets only for
development verification; it is not a production catalog or an image-licensing
assertion.

### Validation rules

- **All environments:** origin must parse, use `http`/`https`, carry a host, and
  contain no userinfo, query, fragment, or path prefix. Placeholder values
  (`example.invalid`, `changeme`, …) are rejected. Trailing slashes and scheme/
  host case are normalized deterministically.
- **staging / production:** must be `https`, and must not use a development-only
  host — loopback (`localhost`, `127.0.0.0/8`, `::1`), Android emulator
  (`10.0.2.2`, `10.0.3.2`), private networks (`10/8`, `172.16/12`, `192.168/16`,
  `169.254/16`), or `.local` / `.internal` / `.localhost` names.
- **local:** plain `HTTP` is explicitly permitted.

### Local development

Real configuration files are **ignored by Git**; only `*.example.json` is
tracked. Create your own copy:

```bash
cp config/local.example.json config/local.json
```

Android Emulator (the host loopback is `10.0.2.2`; this is the default in
`config/local.json`):

```bash
flutter run --dart-define-from-file=config/local.json
```

Physical Android device over USB — use `config/local.json` with `adb reverse`,
because the emulator-only `10.0.2.2` address is not available on a physical
device. **The mapping does not survive a reconnect**, so re-run it whenever you
replug:

```bash
adb reverse tcp:8000 tcp:8000
adb reverse --list                 # confirm: UsbFfs tcp:8000 tcp:8000
flutter run --dart-define-from-file=config/local.json
```

Alternatively use your development machine's LAN IP (where the firewall permits
it). Never commit a private LAN IP into tracked source.

iOS Simulator would use `http://127.0.0.1:8000`, but **no iOS host project
exists** — see limitations below.

### Transport security

Local HTTP is allowed **only** in debug builds, through a debug-only Android
manifest exception in `android/app/src/debug/AndroidManifest.xml`
(`android:usesCleartextTraffic="true"`). The main/release manifest never sets
it, so production keeps the platform default of no cleartext traffic. TLS is
never disabled globally and no permissive certificate callback is installed.

### Staging and production

`staging.example.json` and `production.example.json` use clearly marked
placeholders (`.invalid` hosts, `pk_*_XXXX` keys). Those files are **deliberately
not runnable** — validation rejects them until replaced with real values.

Production additionally requires an explicit `APP_ENV=production`, HTTPS, no
development host, valid public Clerk configuration, and diagnostics off by
default.

### Secret-management boundary

Flutter bundles are inspectable; `--dart-define` is **not** a secret store.

- A Clerk **publishable** key is public and may ship in the app.
- Never place in this app: `CLERK_SECRET_KEY`, database credentials, Laravel
  `APP_KEY`, payment secrets, signing secrets, or private API credentials.

Startup diagnostics print only the selected environment and whether optional
public configuration is present — never the API origin or any key.

## Design system boundary

The canonical design system lives in `frontend/design-system/`:

- `DESIGN.md` — brand and interaction contract
- `tokens.css` — **sole canonical token authority**
- `design-tokens.json` — portable, framework-neutral token representation
- `flutter-material3.md` — MUI-to-Flutter mapping contract

Flutter does **not** parse CSS or load token JSON at runtime. A deterministic
generator converts `tokens.css` into typed Dart once, and the theme consumes that
output. Do not hard-code colors, spacing, radii, typography, or elevation in this
app — if a value is missing, add the token to `tokens.css` and regenerate.

### Token generation

```bash
dart run tool/generate_tokens.dart           # regenerate lib/theme/tokens/generated_tokens.dart
dart run tool/generate_tokens.dart --check   # fail if generated tokens are stale
```

**`lib/theme/tokens/generated_tokens.dart` is the only file allowed to contain
raw `Color(0x...)` values**, enforced by `test/theme/theme_definitions_test.dart`.

## Theme architecture

```text
lib/theme/
  tokens/generated_tokens.dart   # GENERATED — do not edit; regenerate instead
  app_color_scheme.dart          # explicit light ColorScheme (no ColorScheme.fromSeed)
  app_color_extensions.dart      # ThemeExtensions: editorial surface, brand accent, status
  app_typography.dart            # TextTheme mapped from the canonical type scale
  app_spacing.dart               # spacing, gutters, sections, breakpoints
  app_shapes.dart                # radii, borders, elevation decomposition
  app_motion.dart                # durations, easing, reduced-motion helper
  app_theme.dart                 # AppTheme.light() — the single ThemeData
  component_themes/              # AppBar, buttons, inputs, surfaces, navigation,
                                 # selection, feedback
  preview/theme_preview.dart     # development-only preview harness
```

Only a **light** theme exists. `tokens.css` defines no dark palette, so dark mode
is deferred; `--surface-inverse` is an inverse surface, not dark mode.

## Fonts

| Family | Status | Notes |
| --- | --- | --- |
| Young Serif | **Bundled** | `assets/fonts/YoungSerif-Regular.ttf` + `OFL-Young-Serif.txt`, the OFL asset the website already ships. Display typography only (96/48/32). |
| Helvetica Now Text | **Not bundled** | Commercial and distributed nowhere in this repository. Utility type falls back to the platform sans — the terminal generic of the canonical stack, not a substituted brand font. |

No Google Fonts or typography package is used. Do not download arbitrary font
files.

## Commands

```bash
flutter pub get          # resolve dependencies
dart format .            # format (CI requires --set-exit-if-changed clean)
flutter analyze          # static analysis (flutter_lints + strict analyzer modes)
flutter test             # unit, widget, config, and design-system tests
dart run tool/generate_tokens.dart --check   # token freshness
git diff --check         # whitespace errors
flutter build apk --debug --dart-define-from-file=config/local.json
flutter run --dart-define-from-file=config/local.json
```

## Known limitations

- **iOS is not implemented.** The project is Android-only by decision, so no
  `ios/` host project and no App Transport Security exception exist. The
  configuration layer itself is platform-neutral; if iOS is later enabled it
  will need a debug-scoped ATS exception mirroring the Android one.
- **Local live API verification is limited.** Phase 17.3 was verified against
  the local Laravel development server both from a Dart client probe and on a
  physical Android device. `GET /api/v1/products/{product}` (`CAT-002`) returned
  the full detail representation anonymously (3 ordered images, 2 embedded
  variants, integer minor-unit prices, a null `description` that the app renders
  as an omitted section), an unknown slug returned `RESOURCE_NOT_FOUND` (404),
  and the app displayed the live payload unchanged on device. Staging and
  production are not deployed.
- **Live request verification depends on the configured backend.** Phase 17.10
  implements anonymous/custom and catalog-linked REQ-001 submissions with
  optional inline private attachments. It does not persist drafts or tokens,
  retry uncertain POST outcomes, or implement request history.
- **Live enquiry verification is anonymous-only.** Phase 17.11 verified ENQ-001
  against the local Laravel development server from a Dart client probe: JSON
  creation, multipart with one private attachment, product association, `OPEN`
  initial status, and the `422`/`429`/`UNSUPPORTED_ATTACHMENT_TYPE` failure
  paths all behaved as the frozen contract requires. No Clerk sign-in workflow
  exists yet, so the authenticated CUSTOMER path is **not** live-verified and is
  not claimed. Enquiry history (`ENQ-002`/`ENQ-003`) is intentionally absent, so
  a submitted enquiry is visible only through the returned reference.
- **Physical-device coverage is partial.** The enquiry form, its states, and 2x
  text scaling were verified on an Android emulator with Marionette against live
  Laravel. Landscape orientation and a full screen-reader traversal pass are
  still outstanding.
- **Product-detail fixture content is partly synthetic.** In `fixtures` mode the
  second gallery image and the per-product options are development-only values
  documented in the Phase 17.3 section above. Only local debug builds can read
  them.
- **No sign-in UI yet.** Live authenticated verification needs a real Clerk
  publishable key and a supported development sign-in workflow.
- **Staging and production are unverified.** Their origins are not deployed, so
  only validation rules are tested for them.

## Repository rules

Follow `frontend/AGENTS.md` and the root `AGENTS.md`. In particular: the Laravel
backend is the authority for business rules, and no app may connect to the
database directly.
