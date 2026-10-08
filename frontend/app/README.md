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
structure)**, **Phase 16.8 (error/loading states)**, and **Phase 16.9
(logging/diagnostics)** foundations.

Group P is complete. Group Q owns customer feature implementation.

The root application now uses `MaterialApp.router`. Route placeholders establish
the Phase 16.6 navigation contract only; customer feature screens remain owned
by their later phases. The development-only theme preview remains directly
testable but is no longer the application home screen.

## Navigation

`lib/navigation/` is the single route registry. Public routes are home, catalog,
product/category details, search, made-to-order requests, contact, and the
future Clerk sign-in/sign-up entry points. `/account` is the sole protected
placeholder route.

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
product detail, search, furniture requests, enquiries, and account are future
feature families. Cart, checkout, payments, orders, order tracking, and
favorites remain unimplemented and unregistered.

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
their respective expected failure events. Cancellation remains silent and the
diagnostics layer never retries, presents UI, starts authentication, or uploads
events remotely.

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

Phase 16.5 uses the current `clerk_auth` community-maintained
beta SDK (`0.0.18-beta`) behind `ClerkAuthAdapter`. The SDK owns Clerk sign-in,
session restoration, renewal, and sign-out. The app exposes only the current
session token through `AuthTokenProvider`; `ApiClient` obtains it at request
time and never stores bearer credentials.

The SDK's default file persistor is not used because it writes plaintext JSON.
`SecureClerkPersistor` stores the SDK's required client/session state through
`flutter_secure_storage`, which uses Android Keystore-backed encryption. Android
backup is disabled in the main manifest to prevent encrypted storage metadata
from being restored without its device key.

The package is beta and community-maintained rather than an official stable
Clerk Flutter SDK. The adapter isolates this dependency so a supported SDK can
replace it without changing API consumers. No sign-in UI is included in this
phase, and live authenticated verification remains blocked until a real Clerk
publishable key and a supported development sign-in workflow are supplied.

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

Any other field — including `CLERK_SECRET_KEY` — is rejected.

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

Android Emulator (the host loopback is `10.0.2.2`):

```bash
flutter run --dart-define-from-file=config/local.json
```

Physical Android device over USB — the device cannot reach your computer's
`127.0.0.1`, so forward the port first. **The mapping does not survive a
reconnect**, so re-run it whenever you replug:

```bash
adb reverse tcp:8000 tcp:8000
adb reverse --list                 # confirm: UsbFfs tcp:8000 tcp:8000
flutter run --dart-define-from-file=config/local-device.json
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
- **Local live API verification is limited.** A temporary Flutter-client probe
  successfully reached Laravel's public `/api/v1/products` endpoint and parsed
  its response. Physical-device request execution remains pending; staging and
  production are not deployed.
- **No sign-in UI yet.** Live authenticated verification needs a real Clerk
  publishable key and a supported development sign-in workflow.
- **Staging and production are unverified.** Their origins are not deployed, so
  only validation rules are tested for them.

## Repository rules

Follow `frontend/AGENTS.md` and the root `AGENTS.md`. In particular: the Laravel
backend is the authority for business rules, and no app may connect to the
database directly.
