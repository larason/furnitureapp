# Phase 16.3 — Flutter Environment Configuration
## Group P — Flutter Application Foundation

### Objective

Implement a secure, typed, maintainable environment-configuration foundation for the Flutter customer application.

The application must support three environments:

- LOCAL — development against the locally running Laravel API.
- STAGING — integration and pre-production testing.
- PRODUCTION — public release against the deployed Laravel API.

The implementation must support Android and iOS without introducing environment-specific business logic.

**Current deployment status:** Laravel is running locally. The production API is not deployed, and the existing production transport verification gate remains blocked.

This phase establishes configuration only. Do not implement HTTP clients, Clerk authentication sessions, navigation, or customer features.

---

## 1. Mandatory Repository Inspection

Before making changes, inspect:

1. Root `AGENTS.md` and any nested Flutter instructions.
2. Group P phase documentation.
3. Phase 16.1 and Phase 16.2 completion records.
4. Flutter project structure and `pubspec.yaml`.
5. Existing `lib/app.dart` and `lib/main.dart`.
6. Existing `lib/theme/` implementation.
7. Android and iOS platform configurations.
8. Existing configuration utilities or build scripts.
9. Next.js environment-variable conventions.
10. Laravel's current local API configuration.
11. Clerk integration contracts from Group D.
12. Repository `.gitignore` rules and CI conventions.

Identify the actual Flutter project directory before editing files.

Do not assume a particular directory name.

Preserve the completed Phase 16.2 theme implementation.

## 2. Configuration Architecture

Use Flutter's native compile-time environment mechanism:

`--dart-define-from-file`

Prefer a small typed configuration abstraction built around `String.fromEnvironment`, `bool.fromEnvironment`, and related supported Dart compile-time APIs.

Do not introduce an environment-management package unless the existing repository already requires one.

Avoid runtime `.env` loading as the primary configuration mechanism.

### Recommended structure

Adapt to existing project conventions:

```text
lib/
  config/
    app_environment.dart
    app_config.dart
    config_validation.dart

config/
  local.example.json
  staging.example.json
  production.example.json
```

These paths are illustrative.

Do not move the existing `lib/theme/` files.

The configuration implementation must remain separate from the design-system token architecture.

### Design principles

- One typed configuration entry point.
- Explicit environment selection.
- Immutable configuration after startup.
- Centralized validation.
- No scattered environment lookups throughout features.
- No duplicated API URLs.
- No configuration-based duplication of business rules.

Avoid unnecessary interfaces, service locators, or dependency-injection frameworks.

---

## 3. Environment Definitions

Implement a closed environment model:

```dart
enum AppEnvironment {
  local,
  staging,
  production,
}
```

Unknown environment names must fail validation.

Do not silently default an invalid environment to LOCAL.

The application must not accidentally select PRODUCTION or LOCAL because a configuration value is missing.

### Required configuration fields

| Field | Purpose |
|---|---|
| APP_ENV | Selected environment |
| API_BASE_URL | Laravel API origin |
| CLERK_PUBLISHABLE_KEY | Public Clerk application key, when required by the approved mobile integration |
| ENABLE_DIAGNOSTICS | Non-secret diagnostics setting, if needed |

The actual Clerk configuration requirements must be verified against the installed or approved Clerk Flutter SDK and Group D authentication architecture.

Do not invent Clerk environment variables or SDK configuration parameters.

Only include fields required by the current architecture.

### API URL convention

Use the Laravel API origin as the canonical base URL, for example:

`http://127.0.0.1:8000`

Do not automatically append `/api/v1` to the configuration value.

The future networking layer must apply the frozen API version and endpoint paths consistently.

Do not duplicate `/api/v1` across configuration and request builders.

---

## 4. Local Development — Android and iOS

The Laravel backend currently runs locally.

Mobile devices and emulators do not always resolve the development computer's loopback address in the same way.

The agent must account for this explicitly.

### Android emulator

For the standard Android Emulator, the development computer's loopback service is commonly accessible through:

`http://10.0.2.2:8000`

This address is for the Android Emulator and must not be treated as a universal Android host address.

### iOS simulator

For an iOS Simulator running on the same Mac as Laravel, a local loopback URL such as:

`http://127.0.0.1:8000`

may be appropriate.

### Physical Android device

A physical Android device cannot normally reach the development computer through its own `127.0.0.1`.

Support a documented development workflow using one of:

- A development computer's LAN IP, where the network and firewall permit access.
- An approved Android Debug Bridge reverse-port mapping.

For USB debugging, an example is:

```bash
adb reverse tcp:8000 tcp:8000
```

When ADB reverse is active and correctly configured, the Android device can access the forwarded service through its own loopback address.

Do not assume that ADB reverse persists after reconnecting the device.

Do not hardcode a developer's private LAN IP into tracked application source.

### Local transport security

Local HTTP may be permitted only in explicitly configured development builds.

Do not disable TLS certificate validation globally.

Do not add permissive certificate callbacks.

Do not allow production builds to use arbitrary HTTP API origins.

Any Android cleartext traffic exception or iOS App Transport Security exception must be limited to the intended development configuration.

Do not add unrestricted cleartext allowances to production platform manifests.

If the existing Android/iOS configuration cannot safely support the required development transport without a platform-specific change, document and implement the smallest debug-only exception.

---

## 5. Staging Configuration

Prepare staging configuration support without inventing deployed infrastructure.

Staging must:

- Use an explicit STAGING environment.
- Require a valid HTTPS API origin.
- Use staging-specific public Clerk configuration where applicable.
- Remain isolated from production credentials and production-only services.
- Fail validation if required settings are absent.

Do not fabricate a staging domain.

Do not use production endpoints as silent staging fallbacks.

Example configuration files may use clearly marked placeholders, but such files must not be accepted as valid runnable configurations.

---

## 6. Production Configuration

Production must require:

1. Explicit `APP_ENV=production`.
2. A valid HTTPS API origin.
3. No localhost, loopback, emulator-only, or private-network API endpoint.
4. Valid required public Clerk configuration.
5. No development-only transport exceptions.
6. No development diagnostics enabled by default.
7. No debug-only environment fallback.

Do not embed production secrets in Flutter.

### Important security boundary

Flutter application bundles are inspectable.

Values passed through `--dart-define` are not a secure secret store.

A Clerk publishable key is intended to be public.

A Clerk secret key is not.

Never include:

- `CLERK_SECRET_KEY`
- Database credentials
- Laravel `APP_KEY`
- Payment-provider secret keys
- Signing secrets
- Private API credentials

Production API and Clerk configuration must remain externally supplied until actual deployment details are available.

Do not claim production configuration is verified merely because a production template exists.

---

## 7. Configuration Validation

Implement deterministic validation before normal application initialization.

### Environment validation

Reject:

- Missing environment selection.
- Unknown environment names.
- Missing required configuration.
- Placeholder values.
- Invalid URLs.
- Unsupported URL schemes.
- URL userinfo, query strings, or fragments in the API origin.
- Unexpected API path prefixes when the contract expects an origin.
- Development-only addresses in staging/production.
- Insecure production transport.

Normalize only where normalization is unambiguous.

For example, handling a trailing slash may be acceptable.

Do not silently rewrite an invalid API host or protocol.

### Local validation

Allow explicit local HTTP configuration.

Support emulator, simulator, and physical-device development addresses where applicable.

Do not assume that a valid URL is reachable.

### Failure behavior

Invalid configuration must stop normal application startup with a clear diagnostic.

Never print credentials or raw configuration objects.

Do not display private values in an error screen.

Do not silently continue with an unintended environment.

---

## 8. Configuration Files and Git Hygiene

Provide tracked, non-secret example configuration files.

For example:

```json
{
  "APP_ENV": "local",
  "API_BASE_URL": "http://10.0.2.2:8000"
}
```

If the approved Clerk integration requires a publishable key, include a documented placeholder in the example.

Do not put real keys into tracked examples.

Create or document the appropriate local configuration workflow.

Ensure private developer configuration files are ignored by Git.

Avoid broad ignore patterns that accidentally hide legitimate source files or example templates.

### Recommended commands

Android Emulator:

```bash
flutter run \
  --dart-define-from-file=config/local.json
```

Physical Android with ADB reverse:

```bash
adb reverse tcp:8000 tcp:8000

flutter run \
  --dart-define-from-file=config/local-device.json
```

The device configuration should use the forwarded loopback origin.

These commands are illustrative; adapt them to the actual repository layout and connected device.

Do not create unnecessary platform-specific entry points when one entry point and explicit configuration files are sufficient.

---

## 9. Integration with the Existing App

Wire configuration into the Flutter startup sequence.

Requirements:

- Load and validate configuration before initializing dependent services.
- Make validated configuration accessible through a simple, explicit mechanism.
- Avoid mutable global configuration.
- Preserve the existing `AppTheme.light()` integration.
- Preserve the Phase 16.2 theme preview and tests.
- Avoid adding API calls during startup.
- Avoid requiring a live Laravel server merely to construct the app.

Prefer constructor injection or another lightweight established project convention.

Do not introduce a dependency-injection package solely for configuration.

### Boundary with Phase 16.4

Phase 16.4 will implement the networking layer.

This phase should only expose a validated API origin that the future networking layer can consume.

Do not implement:

- Dio or HTTP clients.
- Request interceptors.
- Authentication headers.
- API response parsing.
- Retry policies.
- Pagination.
- API repositories.
- Network connectivity monitoring.

---

## 10. Clerk Configuration Boundary

Preserve the existing Group D authentication decisions.

Clerk remains the authentication provider for both Next.js and Flutter.

Laravel remains responsible for backend token verification and authorization.

For this phase:

- Identify which public Clerk values the future Flutter SDK requires.
- Expose those values through typed configuration when appropriate.
- Validate their presence according to environment requirements.
- Do not initialize customer authentication flows.
- Do not implement token persistence.
- Do not create custom Laravel authentication endpoints.
- Do not implement a second token verifier.
- Do not send passwords to Laravel.

The actual Clerk session and secure-storage implementation belongs to Phase 16.5.

Do not copy `CLERK_SECRET_KEY` from the backend or website server environment into Flutter.

---

## 11. Diagnostics and Logging

Provide safe startup diagnostics for development.

Permitted diagnostic information:

- Selected environment.
- Configuration validation status.
- Non-sensitive application build information.
- Whether optional public configuration is present.

Avoid printing the complete API configuration object.

Never print secret keys, bearer tokens, session identifiers, or private credentials.

Do not add a full logging framework during this phase.

Structured application diagnostics belong to Phase 16.9.

---

## 12. Automated Tests

Add focused tests for the environment-configuration contract.

### Required test cases

1. LOCAL parses successfully.
2. STAGING parses successfully.
3. PRODUCTION parses successfully.
4. Unknown environment is rejected.
5. Missing environment is rejected.
6. Missing API origin is rejected.
7. Malformed API origin is rejected.
8. Unsupported URL schemes are rejected.
9. Production HTTP is rejected.
10. Staging HTTP is rejected.
11. Local HTTP is permitted.
12. Production loopback hosts are rejected.
13. Production emulator hosts are rejected.
14. Production private-network hosts are rejected.
15. Placeholder configuration is rejected.
16. API origins with unexpected paths are rejected.
17. API origin normalization is deterministic.
18. Required Clerk public configuration is validated.
19. Secret-key configuration is not accepted as a public configuration field.
20. Invalid configuration cannot silently fall back to another environment.
21. Configuration objects are immutable.
22. Existing Flutter app initialization and theme tests continue to pass.

### Testing strategy

Separate parsing and validation from compile-time environment access so the validator can be tested using controlled values.

Do not require changing process environment variables during tests.

Do not require live network access.

Use test-only configuration factories or explicit constructor arguments where appropriate.

### Quality checks

Run:

```bash
dart format --set-exit-if-changed .
flutter analyze
flutter test
dart run tool/generate_tokens.dart --check
git diff --check
```

The Phase 16.2 token synchronization check must continue to pass.

Do not modify generated theme tokens manually.

---

## 13. Platform Verification

Verify the configuration on available development targets.

The previously verified Android physical device may be reused if still available.

Check:

- Application launches with valid LOCAL configuration.
- Application refuses invalid configuration.
- Selected environment is correctly resolved.
- Existing Material 3 theme renders correctly.
- No startup crashes.
- No sensitive configuration values appear in logs.
- No production configuration is accidentally selected.

If using ADB reverse, verify that the mapping exists.

Do not claim that Laravel API communication works until an actual request is performed in Phase 16.4.

For staging and production, configuration-validation tests are sufficient at this stage.

Actual deployed connectivity remains pending.

---

## 14. Scope Restrictions

Do not implement:

- Phase 16.4 networking.
- Phase 16.5 Clerk session handling.
- Phase 16.6 routing/navigation.
- Phase 16.7 feature modules.
- Phase 16.8 error/loading architecture.
- Phase 16.9 application logging.
- Group Q customer screens.
- New Laravel endpoints.
- Backend environment changes.
- Production deployment.
- A second design-token system.

Do not enable cart, checkout, payment, or order purchasing.

The initial production commerce mode remains REQUEST ONLY.

Preserve the existing design system and all completed Group O work.

---

## 15. Documentation

Update the existing Group P documentation and Flutter README.

Document:

- Configuration architecture.
- Environment names.
- Required public values.
- Example configuration files.
- Local Android Emulator setup.
- Physical Android device setup.
- ADB reverse workflow.
- iOS Simulator considerations.
- Staging and production requirements.
- Configuration validation rules.
- Secret-management boundaries.
- Test commands.
- Deferred networking and authentication responsibilities.

Do not rewrite unrelated project documentation.

Do not modify the frozen API contract.

---

## 16. Required Completion Report

At completion, provide:

1. Repository inspection findings.
2. Files created and modified.
3. Configuration architecture.
4. Environment variables supported.
5. Local Android Emulator configuration.
6. Physical Android configuration.
7. Staging/production validation rules.
8. Clerk public-configuration decisions.
9. Security and secret-handling verification.
10. Automated test results.
11. Native-device verification results.
12. Known limitations and deferred work.
13. Final Phase 16.3 status.

### Definition of Done

Phase 16.3 is complete when:

- LOCAL, STAGING, and PRODUCTION are explicitly supported.
- Configuration is typed, immutable, and validated.
- Local Android development is correctly documented.
- Production rejects insecure or development-only API origins.
- No secret values are embedded in the Flutter app.
- Existing Phase 16.2 theme and token-generation tests remain passing.
- Flutter launches correctly with valid local configuration.
- Invalid configuration fails safely.
- No networking or authentication implementation has leaked into this phase.
- Documentation and verification evidence are complete.

**Implementation directive:** Inspect the existing repository first, implement the smallest secure environment-configuration foundation, test it thoroughly, preserve all previous phase results, and stop at Phase 16.3.

---

## Phase 16.3 Closure Record

### 1. Repository inspection findings

- **Flutter project directory:** `frontend/app/` (confirmed, not assumed). Package `sl_furnitures`, applicationId `com.slfurnitures.app`.
- **No `ios/` host project exists.** The project is Android-only by decision (ADR/DESIGN-007), so no iOS file could be added without reopening that decision.
- **Android manifests:** `src/main` had **no** `android:usesCleartextTraffic` and `targetSdkVersion` is 36, so plain `http://10.0.2.2:8000` would have been blocked on Android 9+. A debug-only exception was therefore required by §4.
- **No existing configuration utility or build script**; `lib/main.dart` was three lines and `lib/app.dart` held the theme wiring.
- **Laravel local API:** `backend/laravel/.env.example` sets `APP_URL=http://127.0.0.1:8000` — the canonical loopback origin used in examples.
- **Next.js conventions:** no committed `.env.example`; `frontend/web/.env.local` is the working file. Env handling is therefore file-based and git-ignored on web too.
- **Clerk contract (Group D):** `docs/clerk-authentication-architecture.md` records that the **Flutter Clerk package choice is deferred** and that "only publishable/client-safe Clerk configuration ships in the app"; `docs/api/api-conventions.md:920` states `CLERK_SECRET_KEY` remains server-only.
- **Git hygiene:** root `.gitignore` held only `.kilo/` and `.freebuff/`; the app `.gitignore` had no config rules.
- **Phase 16.2 preserved:** theme files, generated tokens, and tests untouched; token `--check` still passes.

### 2. Files created and modified

**Created**

- `lib/config/app_environment.dart` — closed `AppEnvironment` enum with `tryParse`.
- `lib/config/app_config.dart` — immutable `AppConfig` value object.
- `lib/config/config_validation.dart` — `ConfigValidationException`, `CompileTimeDefines`, `ConfigValidator`.
- `config/local.example.json`, `config/local-device.example.json`, `config/staging.example.json`, `config/production.example.json`.
- `test/config/config_validation_test.dart` (27 tests) and `test/support/test_config.dart`.

**Modified**

- `lib/main.dart` — validates before `runApp`; renders `ConfigFailureApp` on failure.
- `lib/app.dart` — `SLFurnituresApp` now takes `required AppConfig config`; added `ConfigFailureApp`.
- `test/app_test.dart` — injects `localTestConfig()`.
- `android/app/src/debug/AndroidManifest.xml` — debug-only `usesCleartextTraffic="true"`.
- `.gitignore` — ignores `/config/*.json`, re-includes `!/config/*.example.json`.
- `README.md` — configuration architecture and workflow.

**Not touched:** `frontend/design-system/**`, the frozen API contract, all Group O records, `src/main/AndroidManifest.xml`, and the generated theme tokens.

### 3. Configuration architecture

One typed entry point, validated once, before any widget exists:

```text
--dart-define-from-file  →  CompileTimeDefines.values  →  ConfigValidator.validate()
                                                              ↓
                                                    AppConfig (immutable)
                                                              ↓
                                          SLFurnituresApp(config:)  — constructor injection
```

Parsing/validation is deliberately separated from compile-time access so every rule is testable with controlled values, without process environment variables or network access (§12 testing strategy). No service locator, DI package, interface layer, or runtime `.env` loader was introduced. `lib/theme/` was not moved; the config layer does not reference the token architecture.

`apiBaseUrl` is stored as a **`String`** rather than `Uri` so a configuration instance can be declared `const` — a `Uri` is not const-constructible in Dart, and const construction is what makes immutability provable in a test (§12 case 21).

### 4. Environment variables supported

| Field | Required | Behavior |
| --- | --- | --- |
| `APP_ENV` | always | `local` \| `staging` \| `production`; missing or unknown is rejected |
| `API_BASE_URL` | always | Laravel API **origin** only — no `/api/v1`, path, query, fragment, or userinfo |
| `CLERK_PUBLISHABLE_KEY` | staging + production | optional locally; must start `pk_`; never `sk_` |
| `ENABLE_DIAGNOSTICS` | no | defaults `false`; accepts boolean or `"true"`/`"false"` |

Any other key is rejected — including `CLERK_SECRET_KEY`, which gets its own diagnostic.

### 5. Local Android Emulator configuration

`config/local.example.json` → `API_BASE_URL: http://10.0.2.2:8000`, the emulator's alias for the host loopback. Run with:

```bash
flutter run --dart-define-from-file=config/local.json
```

### 6. Physical Android configuration

`config/local-device.example.json` → `http://127.0.0.1:8000`, used **with** port forwarding:

```bash
adb reverse tcp:8000 tcp:8000
adb reverse --list            # verified: UsbFfs tcp:8000 tcp:8000
```

The README states explicitly that the mapping does not survive a reconnect, and that a private LAN IP must never be committed. `config/*.json` is git-ignored while `*.example.json` stays tracked (verified with `git check-ignore` for all eight names).

### 7. Staging/production validation rules

Both must be `https`, must carry a valid public Clerk publishable key, and must not use a development-only host: loopback (`localhost`, `127.0.0.0/8`, `::1`), emulator (`10.0.2.2`, `10.0.3.2`), private ranges (`10/8`, `172.16–31/12`, `192.168/16`, `169.254/16`), or `.local` / `.internal` / `.localhost` names. Placeholder values are rejected in every environment. `staging`/`production` example files are intentionally **not runnable**. Production additionally rejects development-only origins and defaults diagnostics to off.

### 8. Clerk public-configuration decisions

- The **Flutter Clerk package choice remains deferred** (Group D), so no SDK parameter was invented — only the framework-neutral publishable key is exposed.
- Required for staging/production; optional locally, because no authentication flow exists until Phase 16.5 (documented assumption).
- Format is enforced (`pk_` prefix) and `sk_` values are refused even when supplied under the publishable-key field.
- No authentication flow, token persistence, or second verifier was implemented; `CLERK_SECRET_KEY` is never read.

### 9. Security and secret-handling verification

- **Cleartext is debug-only.** The exception lives solely in `src/debug/AndroidManifest.xml`; the main manifest still contains no `usesCleartextTraffic` or `networkSecurityConfig` (verified by grep), so release builds keep the platform default of no cleartext HTTP.
- No TLS is disabled anywhere; no permissive certificate callback exists.
- Validation messages and the failure screen name **fields and rules only, never values** — asserted by tests that confirm a rejected secret and an unknown field's value do not appear in the message.
- Device logcat scan for `sk_live|sk_test|pk_live|pk_test|api.acmefurniture|127.0.0.1|10.0.2.2|APP_KEY|password` returned only Flutter's own Dart VM service URL.
- Diagnostics print only the environment name and whether optional public config is present.
- No secret exists in any tracked file; all four real config names are git-ignored.

### 10. Automated test results

Quality checks (run from `frontend/app/`, exit status captured):

| Command | Result |
| --- | --- |
| `dart format --output=none --set-exit-if-changed .` | Passed — 32 files, 0 changed |
| `flutter analyze` | Passed — `No issues found!` |
| `flutter test` | Passed — `All tests passed!` (**76 tests**) |
| `dart run tool/generate_tokens.dart --check` | Passed — `Token generation: OK (generated tokens are current).` |
| `git diff --check` | Passed |
| `flutter build apk --debug --dart-define-from-file=config/local.json` | Passed — `app-debug.apk` built |

`test/config/config_validation_test.dart` contributes 27 tests and covers **all 22 required cases** from §12 (verified by extracting the numbered test names 1–22). The Phase 16.2 suites (49 tests) remain passing unchanged.

Two defects were found by these tests/verification and fixed rather than suppressed:

1. `http://` passed validation because it has an authority component but an **empty host** — now rejected (`uri.host.isEmpty`).
2. The failure screen resolved `Theme.of(context)` **above** its `MaterialApp`, so it rendered Material's default `#1D1B20` text instead of brand `#111111` — fixed with a `Builder` inside the `MaterialApp`, confirmed on the device before/after.

### 11. Native-device verification results

Physical device `R58TA1771DR` (Android 12, armeabi-v7a).

| Scenario | Result |
| --- | --- |
| Valid LOCAL config | Install success; logcat `SL Furnitures configuration valid: environment=local, clerkPublishableKey=absent`; no `E/flutter`/`FATAL`; screenshot shows canonical palette (`#FCF4ED`, `#FFFFFF`, `#111111`, `#275DC5`) |
| Build with **no** config file | Startup stopped: `SL Furnitures configuration rejected: APP_ENV is required and must be one of: local, staging, production.` — no crash, no fallback to a valid start (`configuration valid` count = 0). Failure screen renders on canvas `#FCF4ED` with brand `#111111` text |
| ADB reverse mapping | `adb reverse tcp:8000 tcp:8000` → `adb reverse --list` reports `UsbFfs tcp:8000 tcp:8000` |
| Secret leakage | None found in logcat |

**Honesty note:** early screenshots were captured before the Flutter surface was created (`FlutterRenderer: Width is zero` → `surfaceChanged` about 4s later) and came out black. Those frames were re-taken after the surface was up; only the verified frames are reported above.

Laravel API communication is **not** claimed — no request is performed until Phase 16.4.

### 12. Known limitations and deferred work

1. **iOS not implemented.** No `ios/` project exists, so no ATS exception was added; the config layer is platform-neutral and an iOS build would need a debug-scoped ATS exception mirroring the Android one.
2. **CLERK_PUBLISHABLE_KEY optional in LOCAL** — an assumption justified by §8's example (APP_ENV + API_BASE_URL only) and by no auth flow existing yet.
3. **Staging/production connectivity unverified** — their origins are not deployed; only validation rules are tested.
4. **`ENABLE_DIAGNOSTICS=true` is not refused in production**, only off by default, because §6 requires "not enabled by default" rather than forbidding it.
5. **Emulator/genuine LAN-IP workflow** documented but only the ADB-reverse path was exercised on hardware.
6. **Phase 16.4 boundary respected:** no Dio/HTTP client, interceptor, auth header, parser, retry, pagination, repository, or connectivity monitoring.

### 13. Phase 16.3 status

**Phase 16.3: COMPLETE.** Definition of Done is satisfied:

- LOCAL, STAGING, and PRODUCTION are explicitly supported with a closed enum and no fallback.
- Configuration is typed, immutable (const-constructed in tests), and centrally validated.
- Local Android Emulator, physical device, and ADB-reverse workflows are documented and the mapping verified.
- Production rejects insecure and development-only API origins.
- No secret value is embedded in the app; debug-only cleartext is scoped to the debug manifest.
- All 76 tests pass, including every Phase 16.2 theme and token test; token `--check` passes.
- Flutter launches with valid LOCAL configuration and refuses invalid configuration safely on a physical device.
- No networking or authentication implementation leaked into this phase.
- Documentation and verification evidence are complete.

Group P does **not** proceed to Phase 16.4 automatically; that remains a separate owner instruction. Group O records are unchanged.

**Out-of-scope observation (not changed by this phase):** `android:label` was changed to `Buy Furnitures` and `mipmap-hdpi/ic_launcher.png` was replaced by another actor during this phase. ADR/DESIGN-007 records the label as `SL Furnitures`, so that ADR and the manifest now disagree; reconciling the app identity was left to the owner.