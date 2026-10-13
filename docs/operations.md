# Operations Runbook

Operational procedures for this repository. This document is for humans running the
application in an environment; agent-facing rules stay in `AGENTS.md`, and architectural
decisions stay in `docs/decisions.md`.

## 1. Database schema operations

### 1.1 Never run a destructive migration against a real database

`php artisan migrate:fresh` drops every table. It is only safe for a genuinely disposable
database. Both conditions must hold — checking only one is not enough:

```bash
test "${APP_ENV:-}" != "production"
test "${DB_DATABASE:-}" = ":memory:" -o \
     "${DB_DATABASE:-}" = "furnitureapp_test_disposable"
php artisan migrate:fresh --seed --force
```

Never point this at the development, staging, or production database. The canonical source of
this rule is `AGENTS.md §11`.

### 1.2 Verify against a disposable MySQL database, not only SQLite

The test suite runs on in-memory SQLite, which cannot prove MySQL/MariaDB-specific behavior.
Schema changes that depend on engine features must be verified against a disposable MySQL
database:

```bash
# Create the disposable database (never the application database)
mysql -e "CREATE DATABASE IF NOT EXISTS furnitureapp_test_disposable"

vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/<Suite>MysqlTest.php

# Drop it afterwards
mysql -e "DROP DATABASE IF EXISTS furnitureapp_test_disposable"
```

`phpunit.mysql.xml` hard-codes the disposable database name. Every MySQL suite additionally
asserts `DB::connection()->getDatabaseName() === 'furnitureapp_test_disposable'` in `setUp`,
so a misconfigured run fails before touching data. If that assertion ever needs relaxing, the
guard has been removed — do not relax it.

### 1.3 The application server is MariaDB, not MySQL

`DB_CONNECTION=mysql` selects the PDO driver, not the engine. The current server reports
**MariaDB 11.8.8**.

This matters because MariaDB rejects several expressions that MySQL 8 accepts. Concretely,
MariaDB refuses `IF`, `CASE`, `NULLIF`, and `CONCAT` inside a `GENERATED ALWAYS AS` clause
(error 1901), so a generated-column approach written for MySQL 8 will fail at migration time.

Before writing a migration that depends on an engine feature, confirm it works on the real
engine using §1.2. SQLite will happily accept things MariaDB rejects, so the SQLite suite
cannot catch this class of defect.

### 1.4 Migrations with intentionally blocked rollbacks

Some migrations refuse to roll back, because rolling back would destroy data that the
project deliberately preserves. This is intentional. **When a rollback is refused, leave the
migration applied.** The constraint is a deliberate decision, not a defect to be forced
through.

| Migration | Refuses rollback when | Why |
|---|---|---|
| `2026_10_04_130000_add_base_price_to_products_table` | any Product row exists | Product base price is independent of every Variant price, so dropping the column is irreversible; an empty catalog is the only lossless rollback state |
| `2026_10_13_120000_scope_device_token_uniqueness_to_active_rows` | two rows share a `token_fingerprint` | Restoring global token uniqueness would require deleting retired device rows, destroying the record of which customer once held a device |

#### Device token uniqueness specifically

`2026_10_13_120000_scope_device_token_uniqueness_to_active_rows` scopes token uniqueness to
`ACTIVE` rows, so a device can be registered for a new customer after the previous owner
retired it. See `docs/decisions.md` `ADR/BACKEND-057`.

Operational response if `php artisan migrate:rollback` fails with
`MigrationPreconditionException` for this migration:

1. **Leave the migration applied.** Do not delete `device_installations` rows to force the
   rollback through. Retired rows are the audit record of device ownership.
2. Do not work around it by dropping the unique index manually.
3. If the constraint itself is ever found to be wrong, correct it with a **new forward
   migration**. Rolling back only relaxes the guarantee back toward the defect this
   migration fixed.

On this engine the constraint is enforced by a nullable `active_token_fingerprint` column
maintained by `BEFORE INSERT`/`BEFORE UPDATE` triggers plus a unique index. **The triggers
are load-bearing.** If the column is ever seen to drift from `status`, check the triggers
before investigating application code. A unique index on MySQL 8 uses a generated column
instead; SQLite uses a partial index. The enforcement is the unique index on all three.

### 1.5 Forward, not back

A blocked rollback is a signal to write a new forward migration. A migration that has been
applied anywhere should be treated as history: do not edit it to change what it does, because
databases that already ran it will not receive the change.

## 2. Notification feature flags

Token management and customer push are gated by **separate** flags, both defaulting to
`false`. This is deliberate: registering a device grants the ability to *address* it, not
permission to *message* the customer.

| Variable | Default | Effect when `false` |
|---|---|---|
| `NOTIFICATION_PUSH_REGISTRATION_ENABLED` | `false` | `POST`/`DELETE /api/v1/me/devices` return `501 not_implemented` |
| `NOTIFICATION_PUSH_ENABLED` | `false` | No customer push is ever sent |

Registration may be enabled while fan-out stays disabled. Do not enable either in production
without the approvals recorded in `phases/phase-18.4-completion.md`.

Related, and not yet wired:

- `StaleDeviceInstallationReaper` exists and is tested but has **no scheduler entry or
  Artisan command**. Retired-device cleanup does not run on a schedule yet.
- `fcm:diagnostic` is local/testing only and refuses to run outside a `local`/`testing`
  environment unless `NOTIFICATION_PUSH_DIAGNOSTIC_ENABLED` is set.

## 3. Secrets

- `GOOGLE_APPLICATION_CREDENTIALS` and the FCM service-account JSON live only on the server and
  are never committed, never copied into the Flutter bundle, and never exposed to a frontend.
- `php artisan config:show services.firebase` prints the FCM diagnostic token to the console.
  Do not run it in an environment whose token matters.
- `PUSH_TOKEN_FINGERPRINT_KEY` overrides the application key as the HMAC key for device token
  fingerprints. **Never rotate it in place while ACTIVE device rows exist.** A new key produces
  different fingerprints, so registration could temporarily create two ACTIVE owners for one
  token. A rotation is a maintenance event requiring the following procedure:

  1. Disable `NOTIFICATION_PUSH_REGISTRATION_ENABLED` and customer push delivery. Keep the old
     fingerprint key deployed and retain it securely for the entire migration.
  2. Prepare and review a forward-only migration or one-off maintenance command that uses both
     keys explicitly. In one transaction, decrypt each ACTIVE `token_ciphertext` with the
     existing application key, compute its HMAC with the new fingerprint key, and update only
     that row's `token_fingerprint`. Do not accept registrations during this step.
  3. Verify that the number of migrated ACTIVE rows equals the pre-migration count, every ACTIVE
     row has a fingerprint computed with the new key, and the new fingerprints have no duplicate
     values. Abort and restore the old-key configuration if verification fails.
  4. After the migration commits and verification is recorded, deploy the new key. Only then
     enable registration again. Re-registration handles later token rotation; it does not
     replace this migration.

  Never discard the old key before the migration and verification finish. Do not use a client
  re-registration sweep as the migration: offline, stale, or signed-out devices would leave
  old ACTIVE rows unprotected.

## 4. Test commands

| Scope | Command |
|---|---|
| Backend, fast loop | `php artisan test --compact <path>` |
| Backend, full | `php artisan test --compact` |
| Backend, MySQL features | `vendor/bin/phpunit -c phpunit.mysql.xml <path>` (see §1.2) |
| Static analysis | `vendor/bin/phpstan analyse --no-progress` |
| Formatting | `vendor/bin/pint --dirty` |
| Flutter, static analysis | `flutter analyze` |
| Flutter, tests | `flutter test` |
| Flutter, Android build | `flutter build apk --debug --dart-define-from-file=config/local.json` |

Gradle is configured for a 4 GB heap with the daemon disabled
(`android/gradle.properties`). A larger heap was being OOM-killed on the development host
during Phase 18.4; raise it only if a build needs it, and re-check the host's available memory
first.

## 5. Verification before any production change

See `docs/production-caveats.md` for the pre-launch checklist. The database-specific items
that apply to every schema change are covered in §1. Do not deploy, change DNS or firewall
rules, or configure production secrets without notifying the project owner and obtaining
approval (`docs/hosting.md`).
