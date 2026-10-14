# Phase 18.4 Completion Report — Firebase Cloud Messaging

## Result

**COMPLETE for the approved scope — authenticated customer device registration, deactivation, and the push infrastructure fixes. Customer business-event push fan-out remains intentionally disabled. The six pre-existing suite failures have also been diagnosed and fixed; both suites are green.**

Live Firebase delivery was proven before any token was persisted. The approved additive
contract extension is implemented, tested, and documented. `NOTIFICATION_PUSH_ENABLED=false`
and `NOTIFICATION_PUSH_REGISTRATION_ENABLED=false` both ship disabled.

## Approved amendments and how each was met

| # | Amendment | Implementation |
|---|---|---|
| 1 | Retain `POST /api/v1/me/devices` for idempotent registration and rotation | `DeviceRegistrationService::register()`, convergent on `(user, app_instance_id)` |
| 2 | `DELETE /api/v1/me/devices/{device}` with opaque server-issued id, owner-only, idempotent, masked 404 | `dev_` + base36 id, owner-scoped query, `null → 404` |
| 3 | One active owner per token, enforced atomically at the DB level | `UNIQUE (active_token_fingerprint)`, NULL for retired rows so they do not occupy the slot. Originally shipped as `UNIQUE (token_fingerprint)`; rescoped by the 2026-10-13 amendment |
| 4 | `409 CONFLICT` for cross-user registration, no silent transfer | `assertTokenIsUnclaimed()`; uses the frozen `CONFLICT` code |
| 5 | Safe logout/account-switch cleanup; fail closed | `PushRegistrationCoordinator.reconcileSession()` retires then deletes the token before registering |
| 6 | No separate token-refresh endpoint | Rotation reuses `POST /me/devices` |
| 7 | Recipient ownership from the verified Clerk principal; CUSTOMER-only | `clerk.auth` + `EnsureCustomerDeviceAccess` |
| 8 | Encrypt recoverable tokens; keyed fingerprint for uniqueness | `PushTokenCipher` (encrypt) + `PushTokenFingerprint` (HMAC-SHA256) |
| 9 | Reuse frozen `CONFLICT`; preserve envelopes and validation | No new error code; existing `ApiException`/`ApiErrorResponse` |
| 10 | Opaque public device id; precise field and lifecycle definitions | `DeviceInstallationIdentifier`, `api-contract.md §36` |
| 11 | Keep `NOTIFICATION_PUSH_ENABLED=false` | Ships `false`; no fan-out path exists |
| 12 | Review a separate registration-enabled flag | `NOTIFICATION_PUSH_REGISTRATION_ENABLED`, independent of fan-out |
| 13 | Comprehensive tests | 27 new backend tests + 24 new Flutter tests |
| 14 | Fix channel fallback and terminated-message loss | Manifest metadata + `MainActivity` channel creation; `PushInitialSignalBuffer` |
| 15 | Update all four API docs + record the decision | `api-contract.md §36`, `api-resources.md §16`, `api-conventions.md §33`, `openapi.yaml`, `docs/decisions.md` |
| 16 | Follow migration safety rules; preserve Phase 18.1–18.3 | Additive migration; all prior notification tests pass |
| 17 | Document unrelated failures separately | See below; they were diagnosed, fixed, and both suites are now green |

## Implemented paths and schema

### Schema — `device_installations` (new)

Verified on real MySQL via a disposable database:

```
id                   bigint unsigned PK auto_increment
user_id              FK -> users.id ON DELETE CASCADE
platform             varchar(16)          -- CLOSED: ANDROID
app_instance_id      varchar(128)
token_ciphertext     text                 -- encrypted, model-hidden
token_fingerprint    char(64)             -- HMAC-SHA256, model-hidden, no longer unique
active_token_fingerprint char(64) NULL     -- uniqueness slot; NULL unless status = ACTIVE
status               varchar(16) DEFAULT 'ACTIVE'   -- ACTIVE | INACTIVE
app_version          varchar(64) NULL
app_build            varchar(64) NULL
last_registered_at   timestamp NULL
deactivated_at       timestamp NULL
created_at/updated_at timestamp

UNIQUE (active_token_fingerprint)             -- one ACTIVE owner per token
UNIQUE (user_id, app_instance_id)             -- one row per installation
INDEX  (user_id, status)
INDEX  (status, updated_at)

trg_device_installations_active_token_insert   -- BEFORE INSERT: maintain the slot
trg_device_installations_active_token_update   -- BEFORE UPDATE: maintain the slot
```

The block above is the **current** schema on MariaDB, read back from `SHOW CREATE TABLE`. The
triggers are load-bearing: on MariaDB a generated column cannot express the conditional, so
`active_token_fingerprint` is a plain nullable column the triggers maintain, and the unique
index does the enforcement. MySQL 8 uses a `STORED` generated column instead and SQLite a
partial index; see the amendment section below.

Migrations `2026_10_12_120000_create_device_installations_table.php` and
`2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php` are additive only. They
were applied with `migrate:fresh --seed --force` against the disposable database
`furnitureapp_test_disposable` under the `AGENTS.md §11` guard (`APP_ENV=testing`, explicit
database name), then that database was dropped. The development database
`furnitureapp` was never migrated or modified.

### Backend

| Path | Purpose |
|---|---|
| `app/Support/DevicePlatform.php` | CLOSED enum (`ANDROID`) |
| `app/Support/DeviceRegistrationStatus.php` | `ACTIVE` / `INACTIVE` |
| `app/Support/DeviceInstallationIdentifier.php` | Opaque `dev_` + base36 id |
| `app/Models/DeviceInstallation.php` | Model; `token_ciphertext` and `token_fingerprint` hidden; enum casts |
| `app/Models/User.php` | `deviceInstallations()` relation |
| `app/Services/Push/PushTokenCipher.php` | Recoverable encryption at rest |
| `app/Services/Push/PushTokenFingerprint.php` | Irreversible keyed HMAC |
| `app/Services/Push/DeviceRegistrationService.php` | Idempotent upsert, conflict, concurrency |
| `app/Services/Push/DeviceRegistration.php` | Created/updated result |
| `app/Services/Push/DeviceDeactivationService.php` | Owner-scoped deactivate, `deactivateAllFor` |
| `app/Services/Push/StaleDeviceInstallationReaper.php` | `UNREGISTERED` + stale-window reaping |
| `app/Http/Controllers/Api/V1/DeviceInstallationController.php` | DEV-001 / DEV-002 |
| `app/Http/Requests/RegisterDeviceRequest.php` | Allow-list; rejects `user_id`, `role`, `status`, … |
| `app/Http/Resources/DeviceInstallationResource.php` | Public shape; no token |
| `app/Http/Middleware/EnsureCustomerDeviceAccess.php` | CUSTOMER-only |
| `app/Http/Middleware/EnsureDeviceRegistrationEnabled.php` | Independent `501` gate |
| `app/Providers/AppServiceProvider.php` | `device-registration` rate limiter (20/min) |
| `bootstrap/app.php` | `devices.enabled`, `customer-device` aliases |
| `routes/api.php` | Routes inside the authenticated `me` group |
| `config/notifications.php` | `registration_enabled`, `stale_after_days`, `token_fingerprint_key` |
| `config/rate_limits.php` | `device_registration_per_minute` |
| `database/factories/DeviceInstallationFactory.php` | `forOwner`, `inactive`, `withToken` |

### Flutter

| Path | Purpose |
|---|---|
| `lib/core/notifications/device_registration_repository.dart` | DEV-001/DEV-002 client, authenticated |
| `lib/core/notifications/push_registration_coordinator.dart` | Registration/rotation/retirement, fail-closed |
| `lib/core/notifications/push_messaging_service.dart` | `PushInitialSignalBuffer`, coordinator wiring, `discardLocalToken()` |
| `lib/main.dart` | Wires the coordinator; keeps the dev-only diagnostic |
| `android/app/src/main/AndroidManifest.xml` | `default_notification_channel_id` metadata |
| `android/app/src/main/res/values/strings.xml` | Channel id, name, description |
| `android/app/src/main/kotlin/.../MainActivity.kt` | Creates the channel idempotently on launch |

### Documentation

`docs/api/api-contract.md §36`, `docs/api/api-resources.md §16`, `docs/api/api-conventions.md §33`,
`docs/api/openapi.yaml` (two paths, five schemas, one tag update), and three ADRs in
`docs/decisions.md`: `ADR/BACKEND-056`, `ADR/APP-008`, `ADR/APP-009`.

## Security findings

1. **Token protection.** The token is encrypted at rest (recoverable, because delivery needs
   it) and matched through an irreversible keyed HMAC. A bare hash would have permitted
   offline guessing of a bearer-like value. Both fields are model-hidden, so no resource,
   log, or accidental serialization can leak them.
2. **One ACTIVE owner is a database guarantee.** Verified on real MariaDB: a raw insert
   bypassing all application logic was rejected by `UNIQUE (active_token_fingerprint)`
   (`SQLSTATE 23000`), and the service-level path returned `CONFLICT` / `409` leaving exactly
   one active row. Retired rows do not occupy the slot, which is the 2026-10-13 amendment.
3. **No silent transfer.** Ownership is never moved because of a client-supplied token,
   installation id, or any other client value. Fail-closed is intentional: a predecessor who
   failed to deactivate blocks the successor until cleanup succeeds.
4. **Ownership masking.** Another customer's device is `404`, never `403`, so the route
   cannot enumerate devices. Unverified ownership fields are rejected `422` with the
   offending field named.
5. **CUSTOMER-only.** `STAFF` and `ADMIN` are refused. Verified by test.
6. **Independent flags.** Token management and fan-out are separately switchable, so
   addressing a device is never conflated with messaging a customer.
7. **Rate limiting.** A dedicated 20/min budget per customer, tighter than
   `authenticated-write`, because registration mutates security-relevant state.
8. **No token in responses.** Verified by test: the token, ciphertext, and fingerprint are
   absent from every response body.

## Initial Phase 18.4 verification

### Scoped suite results

| Suite | Result |
|---|---|
| `tests/Feature/DeviceInstallationApiTest.php` | **21 tests, 65 assertions passed** |
| `tests/Feature/StaleDeviceInstallationReaperTest.php` | **6 tests, 12 assertions passed** |
| `tests/Feature/FcmHttpV1ClientTest.php` | 5 tests, 18 assertions passed |
| Notification suites (API, broadcast auth, operational email) | 26 tests, 81 assertions passed |
| Device + reaper + FCM combined | **32 tests, 95 assertions passed** |
| `test/core/notifications/` (Flutter) | **24 tests passed** |
| `flutter analyze` | No issues found |
| `vendor/bin/pint --dirty` | passed |

New tests cover: multiple installations, duplicate/concurrent registration, token rotation,
reactivation, cross-user `409`, client-supplied `user_id`/`status` rejection, unknown
platform, unauthenticated access, `STAFF`/`ADMIN` refusal, idempotent deactivation, ownership
masking, unknown and malformed identifiers, the independent `501` gate, registration-enabled
while fan-out-disabled, bulk deactivation, a direct database-level uniqueness check, stale
reaping, `UNREGISTERED` reaping, idempotent reaping, and Flutter-side sign-out,
account-switch, failed-cleanup, and re-entrancy behaviour.

### Live emulator evidence (re-verified this phase)

Android 16 (API 36) x86_64 emulator, Google Play Services and Play Store present.

| Check | Result |
|---|---|
| Background delivery | Provider accepted; notification on **`fcm_default_channel`** (was `fcm_fallback_notification_channel`) |
| Terminated → opened | `FCM_DIAGNOSTIC_SIGNAL messageId=0:1791835339785738… data={kind: diagnostic}` — the buffered initial message is now delivered to the app |
| Permission | Granted through the app's existing explicit flow (`FCM_DIAGNOSTIC_PERMISSION=authorized`) |

The channel fallback and the terminated-message loss were both reproduced before the fix and
confirmed fixed afterwards.

### Initial full-suite verification

**Both suites are green.** The previously failing tests were fixed after the Phase 18.4
implementation; see "Pre-existing failure remediation" below.

| Suite | Result |
|---|---|
| `php artisan test --compact` | **1727 tests, 1726 passed, 1 skipped, 0 failed** |
| `flutter test` | **637 tests, 637 passed, 0 failed** |
| `flutter analyze` | No issues found |
| `vendor/bin/pint --test` | passed |

## Follow-up amendment — ACTIVE-only token uniqueness (2026-10-13)

Approved as an explicit amendment to the device-registration contract.

**The defect.** A global unique index on `token_fingerprint` treated a retired registration as
a permanent claim. Once any customer had held a token, nobody else could ever register it, so
a device handed to a new customer could never receive push: the previous owner's `INACTIVE`
row reserved the fingerprint forever. The client correctly retires the device and deletes the
local token on sign-out, and the server then refuses the new account.

**Migration** `2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php` replaces the
global constraint with an ACTIVE-only one. `token_fingerprint` is preserved on every row, no
row is deleted, and no `user_id` is rewritten.

**Three engine paths.** The server is **MariaDB 11.8.8**, not MySQL, despite the `mysql` driver
name. MariaDB rejects `IF`, `CASE`, `NULLIF`, and `CONCAT` in generated column expressions
(error 1901), so the MySQL 8 generated-column path cannot be used here:

| Engine | Mechanism |
|---|---|
| MySQL 8 | STORED generated column `active_token_fingerprint` + unique index |
| MariaDB | Nullable column maintained by `BEFORE INSERT`/`BEFORE UPDATE` triggers + unique index |
| SQLite | Partial unique index (`WHERE status = 'ACTIVE'`) |

The unique index performs the enforcement on every path, so a race still surfaces as a normal
integrity violation (`SQLSTATE 23000`) that the existing mapping converts to `CONFLICT`.

**Service changes** (`DeviceRegistrationService`):
- `assertTokenIsUnclaimed()` now filters on `status = ACTIVE`, so a retired row is history
  rather than a live claim.
- The conflict message and status were extracted to `tokenConflict()` so every path raises the
  identical frozen `CONFLICT` error.
- `resolveConcurrentRegistration()` now maps a second lost race to `CONFLICT` instead of
  surfacing an unhandled database error.
- `User::deviceInstallations()` carries a generic annotation, and the `$attributes` shape
  documents `platform`, which PHPStan had flagged as read-but-undeclared.

**Fingerprint audit** (requirement 7). Two read queries exist. `assertTokenIsUnclaimed` now
scopes to `ACTIVE`. `StaleDeviceInstallationReaper::reapUnregistered()` matches by fingerprint
and could now match several rows, but it only transitions `ACTIVE` rows, so retired rows are
untouched and its count reflects real transitions. Both are covered by tests.

### Rollback refusal and forward-only mitigation

`down()` refuses with `MigrationPreconditionException` once two rows share a fingerprint.
Restoring global uniqueness would require deleting retired rows, destroying the record of
which customer once held a device. The only lossless rollback state is one in which no
retired row shares a fingerprint, which in practice means before any device has changed hands.

**If `php artisan migrate:rollback` fails with this exception, leave the migration applied.**
Deleting retired rows to force the rollback through destroys the audit trail, and the
constraint is a deliberate strengthening rather than a defect. If the constraint is ever found
to be wrong, the correction is a **new forward migration** — rollback here only ever relaxes
the guarantee back toward the bug this amendment fixed. `migrate:fresh` remains available for a
genuinely disposable environment under the `AGENTS.md §11` guard.

Recorded in `docs/decisions.md` `ADR/BACKEND-057`, following the house precedent in
`ADR/GROUP-K-PRODUCT-BASE-PRICE`. The operator-facing procedure — including the MariaDB
engine difference and the disposable-database verification workflow — is consolidated in
`docs/operations.md` §1.

### Verification for this amendment

| Check | Result |
|---|---|
| `php artisan test --compact` (SQLite, amendment verification) | **1741 tests, 1733 passed, 8 skipped, 0 failed** |
| MySQL uniqueness suite (disposable DB) | 4 tests, 16 assertions passed |
| MySQL concurrency suite (disposable DB) | 3 tests, 16 assertions passed |
| PHPStan (level 5) | **0 errors** |
| Pint | passed |
| `flutter analyze` (amendment verification) | clean |
| `flutter test` (amendment verification) | **655 tests, 655 passed, 0 failed** |

The concurrency suite deliberately avoids `RefreshDatabase`, whose wrapping transaction would
hide one connection's uncommitted work from another. It drives two real connections against
MariaDB and proves that a committed writer blocks the second with an integrity violation, that
a rolled-back writer releases the token, and that a retired row never occupies the unique
slot. Both MySQL suites assert the database name is `furnitureapp_test_disposable` before
touching anything, and the disposable database was dropped afterwards. No destructive
migration was run against the development or production database.

### Security implications

- The single-owner guarantee is **not** weakened. At most one `ACTIVE` owner per token is
  still enforced by the database, verified under real concurrent writers.
- `409 CONFLICT` is still returned whenever another customer currently holds the token
  `ACTIVE`, and a previous owner still cannot reclaim its retired row while someone else holds
  the token live.
- What changed is that **history is no longer treated as authority**. A retired row cannot
  block a legitimate new owner, so an attacker can no longer permanently squat a token by
  registering and abandoning it.
- No endpoint, payload, response, error code, authentication rule, or delivery policy changed.
  `NOTIFICATION_PUSH_ENABLED` and `NOTIFICATION_PUSH_REGISTRATION_ENABLED` both remain `false`.

## Pre-existing failure remediation

Six tests had been failing before this phase. All are now fixed. None of the fixes weakened
an assertion, a security check, or a test — two were test defects, and one was a real
accessibility bug that the failing test had correctly detected.

### 1. Laravel Clerk verifier (4 tests) — test-isolation defect

`phpunit.xml` never pinned the `CLERK_*` environment variables, so
`OfficialClerkTokenVerifierTest` read the developer's local `.env`. `config/clerk.php`
resolves `issuer` and `authorized_parties` from the environment, and the test fixtures use
`https://clerk.example.test`. On any machine with a real Clerk issuer configured, every
fixture was rejected as a forged token — producing `INVALID_AUTHENTICATION` and a
`SESSION_EXPIRED` mismatch that looked like a product bug.

The tests were correct and the verifier was correct; the configuration boundary was leaky.
`phpunit.xml` now pins `CLERK_SECRET_KEY`, `CLERK_JWT_KEY`, `CLERK_ISSUER`,
`CLERK_AUDIENCES`, and `CLERK_AUTHORIZED_PARTIES` to empty values.

**Verification:** the fix was proven hermetic by injecting a deliberately hostile
`CLERK_ISSUER` and `CLERK_AUTHORIZED_PARTIES` into `.env` and re-running the suite — it still
passes. The previous `.env` was restored afterwards. Recorded as `ADR/TESTING-001`.

### 2. Flutter open-source licenses (1 test) — stale test

Commit `512ec36` changed the screen to `© 2026 SL Furnitures` but did not update the test,
which still asserted the exact string `SL Furnitures`. The screen was correct and the test was
out of date, so the assertion was updated to the intended value. This is a strict
full-string match, so it continues to guard the copyright line exactly.

### 3. Flutter navigation shell (1 test) — real accessibility defect

The test expected the semantics label `SL Furnitures home`, but the actual node was
`"SL Furnitures home\n"`. The cause was genuine: `AppBrandLogo` carries its own
`Semantics(label: 'SL Furnitures', image: true)`, and a `Semantics` node nested inside another
labelled node **concatenates** rather than being replaced. Assistive technology therefore
announced the brand name twice on every app bar.

The fix excludes the logo's own semantics inside the app bar, where the enclosing
`Semantics(button: true, label: 'SL Furnitures home')` already names the control. The logo
keeps its own label where it is used standalone (authentication screen, navigation drawer), so
no accessibility information is lost elsewhere. This improves the announcement rather than
relaxing the test. Recorded as `ADR/APP-010`.

## Remaining limitations

1. **Customer push fan-out is not implemented and stays disabled.** No business-event
   notification, recipient policy, delivery job, retry queue, or fan-out command exists.
   Persisting a token grants the ability to address a device, not permission to message it.
2. **No notification-tap navigation.** The initial message is buffered and observable, but
   no customer route is approved as a notification target in this request-first release, so
   acting on a tap would mean navigating to an unimplemented screen.
3. **Foreground notifications are not displayed.** In the foreground the app observes the
   payload; rendering a local notification needs an approved notification UX.
4. **No per-category or opt-in preference UI.** Authorization is single-purpose: the app
   registers a device when a customer is signed in.
5. **`app_instance_id` is a locally generated UUID, not the Firebase installation id.**
   `firebase_installations` would be the natural source, but its Android build applies
   AGP 4.1.0 and references the retired `jcenter` repository and does not compile against
   this project's AGP 9 / Java 17 toolchain. A random RFC 4122 v4 identifier is generated on
   first registration and persisted through the existing secure key/value store instead, so
   it is stable across token rotations without adding a dependency. The seam remains
   injectable, so a real installation id can be adopted later without changing the contract.
   Recorded as `ADR/APP-009`.
6. **Fingerprint key rotation requires a migration, not only re-registration.**
   `PUSH_TOKEN_FINGERPRINT_KEY` defaults to `APP_KEY`. Changing it while ACTIVE rows still
   contain fingerprints made with the old key breaks the database's one-active-owner guarantee:
   the same token can produce a new fingerprint and be inserted for a second owner before the
   original row re-registers. Keep the old key deployed, disable registration and delivery,
   run a reviewed forward migration that decrypts every ACTIVE token and rewrites its
   `token_fingerprint` with the new key, verify complete coverage and uniqueness, then deploy
   the new key and resume registration. Re-registration is only the normal lifecycle after
   that migration; it is not a substitute for it.
7. **The stale-device reaper is a service, not a scheduled command.** `StaleDeviceInstallationReaper`
   is tested and available, but no scheduler entry or Artisan command is wired. Wiring belongs
   with delivery, which is deferred.
8. **Emulator target is `google_apis`, not `google_apis_playstore`.** Play Services and the
   Play Store are both present on the image, so FCM is fully functional, but a strict Play
   target would use the `android-27/google_apis_playstore` image.
9. **Provider behaviour was exercised for delivery only.** `UNREGISTERED` and stale-token
   reaping are covered by tests against the service, not by a live provider rejection.
10. **Local tooling change.** `android/gradle.properties` was reduced from `-Xmx8G` to
    `-Xmx4G` with `org.gradle.daemon=false`, because the 8G daemon was being OOM-killed on this
    host (15 GiB RAM, swap exhausted) and crashed Gradle mid-build.

## Readiness for Phase 18.5 / 18.6

**18.5 (Request/Enquiry Notifications) — ready, with a prerequisite.** In-app notification
creation, delivery, and the existing tests all pass and are unchanged. Emailing or
in-app-notifying on `NEW_MADE_TO_ORDER_REQUEST` / `NEW_ENQUIRY` needs a recipient policy
decision (which role is notified, and whether staff are in-app or out-of-band), not new
infrastructure. Push fan-out for these events additionally requires the separate approval
recorded in limitations 1 and 2.

**18.6 (Notification Preferences) — ready with design decisions outstanding.** The device
model supports opt-out via deactivation, so a preferences screen has a working substrate.
Outstanding decisions: whether preference changes de-register the device or are stored
separately, how a customer revokes notifications already granted at the OS level, and how
preferences interact with a still-`ACTIVE` registration.

Neither phase should be started until the previously failing Clerk verifier and Flutter
navigation/license tests are resolved, so a green run is meaningful again. **That condition
is now met: both suites are green** (see "Full suite status").

## Human checkpoints

1. **Approve a notification event and recipient policy** before any customer push is enabled.
2. **Decide the notification-tap destination** for a terminated-open message.
3. **Schedule the stale-device reaper** once delivery exists.
4. **Keep the suite hermetic.** `phpunit.xml` now pins the `CLERK_*` variables
   (`ADR/TESTING-001`). Any future configuration a test asserts on must be pinned the same
   way, never inherited from a developer's `.env`.
5. Production Firebase credentials, queue supervision, Coolify deployment, and secret
   rotation remain deferred. No Coolify deployment or destructive migration was performed.
