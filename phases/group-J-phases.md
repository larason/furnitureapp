# Group J MariaDB Concurrency Closure Gate

## 1. Objective

Execute the final **real MariaDB concurrency verification** required to close Group J.

No new business behavior should be introduced.

Current state:

```text
Group J implementation = complete

SQLite verification = PASS
PHPStan = PASS
Pint = PASS
Composer audit = PASS
OpenAPI = PASS
git diff --check = PASS

Group J = NOW CLOSED
```

The only remaining blocker is:

```text
required MariaDB concurrency races have not yet executed
```

The repository implementation is complete. This task is now a **database-engine verification gate**, not a feature-development phase.

---

# 2. Development and Production Database Context

Treat the following environment split as authoritative.

## Development / closure-test environment

The project is currently developed on:

```text
Operating system:
Fedora 44 workstation

Database server:
MariaDB 11.8.8

Local MariaDB client:
MariaDB client 15.2
Linux x86_64
EditLine wrapper
```

The Group J concurrency closure tests MUST run against this local MariaDB server using the dedicated disposable database:

```text
furnitureapp_test_disposable
```

This local MariaDB environment is sufficient to prove the Group J database concurrency invariants.

---

## Future production environment

Production will later be deployed using:

```text
Coolify
→ Contabo VPS
→ MySQL-compatible production database
```

The production Coolify/Contabo database is:

```text
NOT required
NOT used
NOT modified
```

for this Group J closure gate.

Do not delay Group J closure waiting for production infrastructure.

Do not run destructive concurrency tests against the future production database.

---

# 3. Why Local MariaDB Is Valid Closure Evidence

SQLite cannot prove:

```text
SELECT ... FOR UPDATE
row-level locking
cross-process transaction serialization
deadlock/retry behavior
single-use capability races
```

MariaDB can.

The repository already uses real MariaDB/MySQL verification for concurrency-sensitive behavior.

Therefore:

```text
Fedora 44
+
local MariaDB 11.8.8
+
real concurrent forked workers
```

is valid Group J closure evidence.

---

# 4. Production Compatibility Note

The future production database may be Oracle MySQL or MariaDB-compatible MySQL depending on final Coolify configuration.

Do not treat MariaDB and Oracle MySQL as identical implementations.

However, the Group J closure requirement is specifically to prove that the Laravel transaction design works against a real MySQL/MariaDB transactional engine rather than SQLite.

The final production deployment should later perform its own:

```text
migration verification
connection verification
transaction smoke tests
driver/version verification
```

without destructive concurrency testing against live production data.

That later deployment verification is outside this Group J closure task.

---

# 5. Closure Principle

Skipped MariaDB tests are not closure evidence.

Group J may close only after all required races:

```text
execute
AND
pass
```

against:

```text
furnitureapp_test_disposable
```

on the local Fedora workstation.

---

# 6. Scope

This task owns only:

```text
local MariaDB disposable database setup
test-only MariaDB credentials
missing concurrency test classes
true concurrent race execution
race-result verification
final Group J closure assessment
documentation of evidence
```

Do not add:

```text
new API fields
new routes
new statuses
new attachment capability semantics
new business rules
new external dependencies
frontend work
Coolify configuration
Contabo deployment configuration
production database provisioning
```

unless a race exposes a genuine backend defect.

---

# 7. Mandatory Race Families

Exactly three Group J concurrency areas must execute.

## A. Furniture Request status race

Existing class:

```text
FurnitureRequestStatusConcurrencyMysqlTest
```

Mandatory scenarios:

```text
close vs review
same target transition
```

---

## B. Enquiry close race

Add the integration test if it does not exist.

Recommended:

```text
tests/Integration/EnquiryStatusConcurrencyMysqlTest.php
```

---

## C. Attachment capability race

Add the integration test if it does not exist.

Recommended:

```text
tests/Integration/AttachmentCapabilityConcurrencyMysqlTest.php
```

---

# 8. Test Database

The integration tests must use exactly:

```text
furnitureapp_test_disposable
```

Do not run against:

```text
normal development database
staging database
production database
future Coolify database
future Contabo database
```

---

# 9. Disposable Database Safety

Before any destructive operation, enforce:

```text
APP_ENV != production

AND

DB_DATABASE == furnitureapp_test_disposable
```

Do not infer disposability from:

```text
APP_ENV=testing
```

alone.

The exact database-name guard is mandatory.

---

# 10. Environment Variable Guard

The existing integration infrastructure currently uses:

```text
REQUEST_STATUS_MYSQL_TEST_DATABASE
```

The closure run must set:

```text
REQUEST_STATUS_MYSQL_TEST_DATABASE=furnitureapp_test_disposable
```

If shared traits expose additional existing variables, inspect and use them.

Do not invent new environment variable names unless the current test infrastructure truly requires refactoring.

---

# 11. Local MariaDB Server Verification

Before test execution, verify the actual database server rather than relying only on the CLI client banner.

Run:

```sql
SELECT VERSION();
```

Record the result.

Expected current environment is approximately:

```text
MariaDB 11.8.8
```

Also report the local CLI client separately:

```text
MariaDB client 15.2
Linux x86_64
EditLine wrapper
```

Do not confuse:

```text
client version
```

with:

```text
database server version
```

---

# 12. Local Database Creation

If it does not already exist, create:

```sql
CREATE DATABASE furnitureapp_test_disposable
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Use a local/test MariaDB administrator only for database/user setup.

Do not commit these commands with real passwords embedded.

---

# 13. Dedicated Test User

Prefer a dedicated local test user.

Conceptually:

```sql
CREATE USER 'furniture_test'@'localhost'
IDENTIFIED BY '<local-test-password>';

GRANT ALL PRIVILEGES
ON furnitureapp_test_disposable.*
TO 'furniture_test'@'localhost';

FLUSH PRIVILEGES;
```

The exact username may differ.

Do not require:

```text
global database privileges
production privileges
SUPER
```

unless MariaDB specifically proves they are required for an existing test operation.

---

# 14. Test User Privileges

The test user should have sufficient permissions for:

```text
CREATE
ALTER
DROP
INDEX
REFERENCES
SELECT
INSERT
UPDATE
DELETE
transactions
row locking
migrate:fresh
```

limited to:

```text
furnitureapp_test_disposable
```

where possible.

---

# 15. Connection Host

Prefer explicit TCP:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
```

for the concurrency suite unless the project intentionally uses a Unix socket.

This avoids accidental differences caused by:

```text
localhost
```

resolving to a socket connection.

---

# 16. Test Environment

Conceptually:

```env
APP_ENV=testing

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=furnitureapp_test_disposable
DB_USERNAME=furniture_test
DB_PASSWORD=<local-test-secret>

REQUEST_STATUS_MYSQL_TEST_DATABASE=furnitureapp_test_disposable
```

Use actual existing Laravel test configuration and environment-loading rules.

Do not blindly overwrite the normal `.env`.

Prefer:

```text
test-specific environment
shell environment injection
phpunit environment
```

consistent with the repository.

---

# 17. Secrets

Do not commit:

```text
local MariaDB password
production DB password
Coolify DB password
Contabo credentials
```

Use environment injection.

---

# 18. Migration Preparation

Before running concurrency tests:

```text
verify non-production
verify exact disposable database name
run migrate:fresh
prepare only required fixtures
```

Do not use production seed data.

---

# 19. Repository Guard

Preserve the repository-wide safety rule conceptually:

```bash
test "${APP_ENV:-}" != "production"

test "${DB_DATABASE:-}" = ":memory:" -o \
     "${DB_DATABASE:-}" = "furnitureapp_test_disposable"

php artisan migrate:fresh --seed --force
```

For the MariaDB integration gate:

```text
DB_DATABASE must be furnitureapp_test_disposable
```

---

# 20. Do Not Run Full PHPUnit Against MariaDB by Default

The canonical functional suite remains SQLite unless the repository explicitly supports a full MySQL/MariaDB run.

Use MariaDB primarily for:

```text
driver-specific integration
row locking
true concurrency
database-specific constraints
```

Do not turn this task into full driver-portability remediation.

---

# 21. Existing Worker Harness

Reuse:

```text
RunsConcurrentWorkers
```

The test harness must provide genuine concurrent workers.

Expected design:

```text
parent process
→ pcntl_fork worker A
→ pcntl_fork worker B
→ both wait on file/barrier
→ parent releases barrier
→ workers execute simultaneously
→ parent waits
→ final invariant asserted
```

Do not replace this with sequential calls.

---

# 22. PCNTL Requirement

Because the local development machine is Fedora Linux, `pcntl_fork()` is appropriate.

Before executing the gate, verify:

```bash
php -m
```

contains:

```text
pcntl
```

If `pcntl` is unavailable, report that as an environment blocker.

Do not replace true concurrency with sequential calls.

---

# 23. Forked Database Connections

Each child process must establish an independent MariaDB connection after:

```text
pcntl_fork()
```

Do not reuse/inherit the parent's active PDO connection across child processes.

This avoids:

```text
MySQL server has gone away
shared socket corruption
cross-process connection state
```

---

# 24. Laravel Connection Reset in Workers

Follow the repository's established concurrency-test pattern.

Conceptually each child should:

```text
disconnect inherited connection
purge connection
reconnect
```

before performing database work.

Use the existing helper if already implemented.

---

# 25. Request Race Scenario 1 — Close vs Review

Initial:

```text
SUBMITTED
```

Worker A:

```text
SUBMITTED → CLOSED
```

Worker B:

```text
SUBMITTED → IN_REVIEW
```

released at the same time.

---

# 26. Valid Serialization — Close First

Possible:

```text
A locks row
A sees SUBMITTED
A closes
A audits
A commits

B acquires lock
B re-reads CLOSED
B attempts CLOSED → IN_REVIEW
B receives business conflict
```

Final:

```text
CLOSED
```

Valid.

---

# 27. Valid Serialization — Review First

Also possible:

```text
B locks
SUBMITTED → IN_REVIEW
B commits

A locks
A re-reads IN_REVIEW
IN_REVIEW → CLOSED
A commits
```

Final:

```text
CLOSED
```

Valid.

---

# 28. Request Race Invariant

The forbidden outcome is:

```text
final IN_REVIEW after a committed CLOSED transition
```

CLOSED must never be reopened by stale state.

---

# 29. Request Race Scenario 2 — Same Target

Initial:

```text
SUBMITTED
```

Both workers:

```text
target IN_REVIEW
```

Expected:

```text
one real transition
one same-state no-op
final IN_REVIEW
```

No duplicated state effect.

---

# 30. Request Audit Under Race

Assert the actual status transition audit is correct.

For same-target:

```text
exactly one actual SUBMITTED → IN_REVIEW status-change audit
```

unless existing audit semantics intentionally record no-op attempts separately.

Do not modify established audit policy in this closure task.

---

# 31. Enquiry Race Setup

Create Enquiry:

```text
OPEN
```

---

# 32. Enquiry Race

Two workers simultaneously call:

```text
close
```

Expected:

```text
OPEN → CLOSED
```

exactly once as a real business transition.

---

# 33. Enquiry Final Invariant

After workers complete:

```text
enquiry_status = CLOSED
```

Never:

```text
OPEN
corrupted value
reopened value
```

---

# 34. Enquiry Same-State Reconciliation

The second serialized close should follow the implemented idempotent/no-op semantics.

Do not make duplicate close state changes.

---

# 35. Enquiry Audit

Mandatory:

```text
one real ENQUIRY_STATUS_CHANGED event
previous = OPEN
new = CLOSED
```

for the true state change.

Audit must be associated with the committed transaction.

---

# 36. Audit Rollback

If audit persistence fails during a status transition:

```text
status mutation rolls back
```

Preserve the already-tested functional invariant.

---

# 37. Attachment Capability Race Setup

Create:

```text
Request or Enquiry
without inline attachment
```

so creation returns:

```text
X-Upload-Token
```

---

# 38. Capability Rules Already Frozen

Do not change:

```text
hashed capability storage
parent binding
resource-type binding
action binding
expiry
single-use behavior
X-Upload-Token header
```

---

# 39. Capability Race

Two workers concurrently submit:

```text
same raw X-Upload-Token
same parent
two valid attachment uploads
```

at the same barrier release.

---

# 40. Capability Race Invariant

Final DB state:

```text
attachments for parent = 1
```

Never:

```text
2
```

---

# 41. Capability Consumption

Final capability state must show:

```text
consumed exactly once
```

No later reuse possible.

---

# 42. Loser Outcome

The losing worker must receive the approved authorization/conflict outcome.

Do not invent a new error solely for the test.

The primary invariant:

```text
no second attachment
```

---

# 43. No Orphan File

If both workers successfully write bytes before one loses the DB race:

the losing worker's file must be removed.

Final durable storage:

```text
one live attachment file
```

---

# 44. Cleanup Failure

If cleanup itself fails, preserve:

```text
AttachmentCleanupRecoveryRequired
```

behavior.

Do not suppress it merely so the race looks successful.

---

# 45. Private Test Storage

Concurrency tests must use:

```text
local private test storage
```

or existing fake/test storage appropriate for cross-process behavior.

Do not touch production object storage.

---

# 46. Cross-Process Storage Caveat

If:

```text
Storage::fake()
```

cannot safely represent shared storage across forked workers, use a dedicated temporary local filesystem disk for this integration test.

The test must observe actual files from both child processes.

Do not rely on process-local in-memory fake state.

---

# 47. Temporary Test Directory

Use a uniquely scoped test directory under a test-only path.

Clean it after each scenario.

Never point at:

```text
public uploads
production attachment directory
```

---

# 48. Race Iterations

Use repeated iterations.

Recommended consistent minimum:

```text
10
```

per race scenario.

Do not reduce iterations to make flaky behavior disappear.

---

# 49. Request Iterations

Expected:

```text
10 close-vs-review
10 same-target
```

unless the existing Request test already defines an approved count.

---

# 50. Enquiry Iterations

Expected:

```text
10 concurrent close races
```

---

# 51. Capability Iterations

Expected:

```text
10 same-capability races
```

---

# 52. Worker Exit Behavior

Workers must exit predictably.

Expected business conflicts should be handled as expected outcomes, not process crashes.

Parent process must assert worker exit codes.

---

# 53. Parent Final-State Verification

After both workers terminate, assert:

```text
database row state
audit records
capability state
attachment count
storage file count
```

Do not treat process exit alone as proof.

---

# 54. True Concurrency Requirement

The workers must overlap around the concurrency-sensitive section.

Do not release worker B only after worker A completes.

The file barrier or equivalent synchronization must ensure meaningful overlap.

---

# 55. MariaDB Transaction Engine

Verify relevant tables use a transactional engine such as:

```text
InnoDB
```

if current schema/configuration allows checking this safely.

Do not run a concurrency proof against a non-transactional storage engine.

---

# 56. Isolation Level

Record the actual MariaDB transaction isolation level if practical:

```sql
SELECT @@transaction_isolation;
```

or MariaDB-compatible equivalent.

Do not alter isolation level solely to make tests pass unless the application explicitly configures it.

Test the environment as the application will normally use it.

---

# 57. Server Identity Evidence

Record at least:

```sql
SELECT VERSION();
SELECT DATABASE();
```

Optionally record:

```sql
SELECT @@transaction_isolation;
```

Do not log credentials.

---

# 58. Expected Environment Report

Final evidence should resemble:

```text
Host OS:
Fedora 44

Database server:
MariaDB 11.8.8

Client:
MariaDB client 15.2
Linux x86_64
EditLine wrapper

Database:
furnitureapp_test_disposable

Connection:
local test-only

Production database touched:
NO
```

Use actual observed server output.

---

# 59. Existing Disposable DB Reuse

If:

```text
furnitureapp_test_disposable
```

already exists, it may be reused only after verifying it is the intended disposable test DB.

Run:

```text
migrate:fresh
```

under the safety guards.

---

# 60. Database Teardown

After testing, either:

```text
drop furnitureapp_test_disposable
```

or leave the explicitly disposable DB empty/available according to existing repository practice.

Do not leave test data in the normal development DB.

---

# 61. Do Not Destroy Local MariaDB Instance

Only the disposable database is disposable.

Do not:

```text
drop other databases
remove MariaDB installation
reset root account
```

---

# 62. No Production Infrastructure Work

Do not configure:

```text
Coolify
Contabo firewall
production MySQL
production backups
production database users
production secrets
```

during this gate.

Those belong to deployment/operations phases.

---

# 63. If Future Production Uses Oracle MySQL

Document that local closure was proven on:

```text
MariaDB 11.8.8
```

and future deployment uses:

```text
MySQL <actual version>
```

once known.

Do not claim exact engine equivalence.

---

# 64. Future Deployment Verification

Later production deployment should verify:

```text
Laravel migrations run successfully
supported DB version
indexes/FKs present
transactional engine
connection charset/collation
application health
```

but must not run:

```text
migrate:fresh
forked destructive race tests
```

against production.

---

# 65. Existing MySQL/MariaDB Compatibility

Do not modify production code merely because MariaDB differs internally from Oracle MySQL unless a test exposes a real defect.

---

# 66. Expected Schema Change

```text
NONE
```

The implementation is already complete.

---

# 67. Expected Dependency Change

```text
NONE
```

---

# 68. Frontend

```text
NONE
```

---

# 69. OpenAPI

```text
UNCHANGED
```

The concurrency gate does not change HTTP contracts.

---

# 70. Route State Regression

Reconfirm:

```text
REQ-001 ACTIVE
ENQ-001 ACTIVE
REQ-007 ACTIVE
ENQ-007 ACTIVE
```

No route change is expected.

---

# 71. Attachment Contract Regression

Keep:

```text
creation without inline attachment
→ X-Upload-Token response header
```

and:

```text
creation with inline attachment
→ no X-Upload-Token
```

---

# 72. JSON Resource Regression

Never reintroduce:

```text
upload_token
```

into Request/Enquiry JSON.

---

# 73. No Raw Capability Persistence

Verify again during race setup:

```text
raw token absent from DB
```

Only hashed/digested capability data may persist.

---

# 74. No Raw Capability Logging

Do not dump raw tokens for race debugging.

If debug correlation is needed, use:

```text
capability record ID
parent ID
hash prefix only if safe and existing policy permits
```

Never raw secret.

---

# 75. If Request Race Fails

Investigate only:

```text
FOR UPDATE
transaction boundary
current-state re-read
ConcurrentTransaction retry
status/audit atomicity
```

Do not alter lifecycle semantics.

---

# 76. If Enquiry Race Fails

Investigate:

```text
row locking
current-state re-read
close idempotence
audit transaction
```

Do not invent another Enquiry state.

---

# 77. If Capability Race Fails

Investigate:

```text
capability lookup/lock
atomic consumption
one-attachment unique guard
transaction ordering
storage compensation
```

Do not weaken single-use security.

---

# 78. Transient Database Failures

Reuse:

```text
ConcurrentTransaction
```

and its approved bounded retry policy.

Do not create a separate MariaDB-only retry service.

---

# 79. Business Conflicts

Do not retry:

```text
CLOSED → IN_REVIEW
expired capability
consumed capability
wrong-parent capability
```

These are business/security outcomes, not transient DB failures.

---

# 80. Deadlocks / Lock Waits

Existing transient handling should cover approved cases such as:

```text
40001
1205
1213
MariaDB transient record-change errors where already supported
```

Do not introduce unlimited retry loops.

---

# 81. Missing Test Classes

If absent, create:

```text
tests/Integration/EnquiryStatusConcurrencyMysqlTest.php
tests/Integration/AttachmentCapabilityConcurrencyMysqlTest.php
```

Do not create new feature behavior to support them.

---

# 82. Test Support Reuse

Reuse:

```text
UsesDisposableMysqlDatabase
RunsConcurrentWorkers
```

and existing test-fixture helpers.

---

# 83. Generic Trait Refactoring

If `UsesDisposableMysqlDatabase` or related support is artificially tied to Request status tests, refactor minimally so all three Group J tests can reuse it.

Do not duplicate destructive database guards.

---

# 84. Do Not Change Existing Request Race Semantics

Preserve the already-existing Request integration test behavior unless a real defect is discovered.

---

# 85. MariaDB Test Commands

Use actual repository paths.

Conceptually:

```bash
php artisan test \
  tests/Integration/FurnitureRequestStatusConcurrencyMysqlTest.php
```

then:

```bash
php artisan test \
  tests/Integration/EnquiryStatusConcurrencyMysqlTest.php
```

then:

```bash
php artisan test \
  tests/Integration/AttachmentCapabilityConcurrencyMysqlTest.php
```

with the test MariaDB environment supplied.

---

# 86. Optional Combined Command

If useful:

```bash
php artisan test tests/Integration
```

only if that directory contains safe integration tests intended for the disposable MariaDB environment.

Inspect first.

Do not inadvertently execute unrelated destructive tests.

---

# 87. Full SQLite Regression

After MariaDB tests pass, run:

```bash
php artisan test
```

using the repository's canonical SQLite test configuration.

Do not accidentally leave the environment pointed to MariaDB for the canonical suite unless intentional.

---

# 88. Environment Reset

After MariaDB integration tests:

restore the normal test environment before full SQLite suite.

Avoid accidentally running:

```text
full canonical tests
```

against:

```text
furnitureapp_test_disposable
```

if the suite assumes SQLite.

---

# 89. PHPStan

Run:

```bash
vendor/bin/phpstan analyse
```

Expected:

```text
0 errors
```

---

# 90. Pint

Run:

```bash
vendor/bin/pint --test
```

---

# 91. Composer Audit

Run:

```bash
composer audit
```

---

# 92. Diff Check

Run:

```bash
git diff --check
```

---

# 93. Route Verification

Run:

```bash
php artisan route:list
```

Confirm Group J route activation remains intact.

---

# 94. OpenAPI Parse

Run the existing OpenAPI contract/parser tests.

No API changes expected.

---

# 95. Documentation

Update:

```text
phases/group-J-phases.md
docs/decisions.md
```

with actual executed evidence.

---

# 96. Do Not Record Intended Results

Only record:

```text
executed
iterations
assertions
actual server version
actual pass/failure
```

Do not document a race as PASS merely because the code appears correct.

---

# 97. Group J Closure Record

If all gates pass, update the existing closure/remediation ADR rather than creating redundant documentation unless repository convention requires a new ADR.

---

# 98. Final Database Evidence

Include:

```text
server version
client version
database name
OS
driver
test classes
race scenarios
iterations
results
```

---

# 99. Request Race Completion Report

Report:

```text
class:
FurnitureRequestStatusConcurrencyMysqlTest

scenarios:
- close vs review
- same-target transition

iterations:
...

assertions:
...

result:
PASS / FAIL

final invariants:
...
```

---

# 100. Enquiry Race Completion Report

Report:

```text
class:
EnquiryStatusConcurrencyMysqlTest

scenario:
concurrent close

iterations:
...

audit events:
...

result:
PASS / FAIL
```

---

# 101. Attachment Race Completion Report

Report:

```text
class:
AttachmentCapabilityConcurrencyMysqlTest

scenario:
two workers / one capability

iterations:
...

winner count:
...

loser behavior:
...

final attachment count:
...

capability consumed:
...

orphan files:
...

result:
PASS / FAIL
```

---

# 102. Local Database Report

Report:

```text
Host OS:
Fedora 44

DB server:
<SELECT VERSION()>

DB client:
MariaDB client 15.2

Database:
furnitureapp_test_disposable

Production DB touched:
NO
```

---

# 103. Production Note

Record:

```text
Future deployment:
Coolify on Contabo VPS

Production DB:
MySQL-compatible service, exact engine/version to be confirmed during deployment

Not used for Group J concurrency closure
```

Do not block Group J on production infrastructure availability.

---

# 104. Full Suite Report

Report the fresh post-concurrency canonical result:

```text
tests:
passed:
failed:
skipped:
assertions:
```

---

# 105. Quality Report

Report:

```text
PHPStan:
Pint:
Composer audit:
git diff --check:
route:list:
OpenAPI:
```

---

# 106. Expected Final Group J Matrix

If every race passes and existing functional tests remain green:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS
10.7 PASS
10.8 PASS

Group J Closure Remediation PASS
Group J MariaDB Concurrency Closure Gate PASS

Group J CLOSED
```

---

# 107. If MariaDB Connection Fails

If local MariaDB remains inaccessible:

```text
MariaDB Concurrency Closure Gate = BLOCKED
Group J = NOT CLOSED
```

Report the concrete cause:

```text
authentication failure
server unavailable
database missing
pcntl unavailable
permission denied
test guard mismatch
```

Do not call it an implementation failure unless production code is actually defective.

---

# 108. If One Race Fails

Then:

```text
Gate = FAIL
Group J = NOT CLOSED
```

Report:

```text
race
iteration
expected invariant
actual invariant
DB error if safe
suspected transaction boundary
```

Do not automatically broaden scope beyond the discovered defect.

---

# 109. Definition of Done

This closure gate is complete only when:

- Fedora 44 local environment is used;
- actual MariaDB server version is verified;
- local MariaDB client version is recorded separately;
- `furnitureapp_test_disposable` exists and is verified;
- production database is untouched;
- destructive guards pass;
- forked workers use independent DB connections;
- Request status races execute;
- CLOSED cannot be reopened;
- same-target Request race remains idempotent;
- Enquiry close race executes;
- final Enquiry state is CLOSED;
- exactly one real Enquiry close occurs;
- Enquiry audit remains correct under race;
- attachment capability race executes;
- one token produces at most one attachment;
- capability is consumed exactly once;
- losing upload creates no surviving orphan;
- raw token remains secret;
- all required race iterations complete;
- no required MariaDB test is skipped;
- canonical SQLite suite remains green;
- PHPStan passes;
- Pint passes;
- Composer audit passes;
- diff check passes;
- routes remain active;
- OpenAPI remains aligned;
- evidence is documented;
- no Coolify/Contabo production infrastructure is touched.

---

# 110. STOP Condition

STOP only when the final result is one of:

```text
MariaDB concurrency closure gate PASS.
All required Group J races executed on furnitureapp_test_disposable.
Group J CLOSED.
```

or:

```text
MariaDB concurrency closure gate BLOCKED/FAILED.
Group J NOT CLOSED.

Exact reason:
...
```

Do not begin Group K automatically.

Do not configure production Coolify/Contabo infrastructure as part of this task.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.

---

# 111. EXECUTED CLOSURE EVIDENCE

Recorded from the actual local run. Nothing below is an intended result.

## Database / engine

```text
Host OS:                  Linux (Fedora-class workstation)
DB server (SELECT VERSION()): 11.8.8-MariaDB
DB client:                mariadb from 11.8.8-MariaDB, client 15.2 for Linux (x86_64) using EditLine wrapper
Database:                 furnitureapp_test_disposable (utf8mb4 / utf8mb4_unicode_ci)
Connection:               127.0.0.1:3306, dedicated local user scoped to this database only
Transaction isolation:    REPEATABLE-READ
Default storage engine:   InnoDB
Relevant tables:          furniture_requests, enquiries, audit_events, attachments,
                          attachment_upload_capabilities -> ALL InnoDB
Production database touched: NO
```

Dedicated least-privilege test user granted privileges on `furnitureapp_test_disposable.*` only.

## Race A — Furniture Request status (`FurnitureRequestStatusConcurrencyMysqlTest`)

```text
scenarios:   close vs review; same-target transition
iterations:  10 per scenario
result:      PASS
```

## Race B — Enquiry close (`EnquiryStatusConcurrencyMysqlTest`, NEW)

```text
scenario:    two workers concurrently close one OPEN enquiry
iterations:  10
assertions:  90
result:      PASS
final state: enquiry_status = CLOSED
audit:       exactly one ENQUIRY_STATUS_CHANGED, previous OPEN -> new CLOSED
```

## Race C — Attachment capability (`AttachmentCapabilityConcurrencyMysqlTest`, NEW)

```text
scenarios:   (1) two workers share one X-Upload-Token
             (2) two authenticated workers without a token
iterations:  10 per scenario
assertions:  200
result:      PASS

Scenario 1: one winner creates the attachment; the loser receives
            INVALID_AUTHENTICATION (capability consumed exactly once).
Scenario 2: one winner creates the attachment; the loser receives
            CONFLICT via the locked parent existence check.

Invariants held every iteration:
  attachments for parent = 1
  capability rows per race = 1, used_at set (consumed once)
  stored files = 1 per race (no surviving orphan)
  raw capability never persisted (keyed HMAC digest only)
```

## Combined race run

```text
php artisan test \
  tests/Integration/FurnitureRequestStatusConcurrencyMysqlTest.php \
  tests/Integration/EnquiryStatusConcurrencyMysqlTest.php \
  tests/Integration/AttachmentCapabilityConcurrencyMysqlTest.php

tests: 5   passed: 5   assertions: 410   result: PASS
```

No required MariaDB test was skipped.

## Canonical SQLite regression (MariaDB env unset)

```text
tests: 1540   passed: 1539   skipped: 1   assertions: 6033   result: PASS
```

The single skip is the pre-existing, intentional
`Tests\Feature\ConcurrentJitProvisioningTest` skip ("MySQL concurrency
integration tests are disabled in the canonical suite"); it is not a Group J
gate and not a MariaDB result.

## Quality gates

```text
PHPStan:          0 errors
Pint:             PASS
Composer audit:   PASS (no advisories)
git diff --check: clean
route:list:       REQ-001/002/003/004/005/006/007 and ENQ-001..007 active
OpenAPI:          aligned (covered by canonical suite)
```

## Closure decision

```text
MariaDB concurrency closure gate: PASS
All required Group J races executed on furnitureapp_test_disposable:
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS
10.7 PASS
10.8 PASS

Group J Closure Remediation: PASS
Group J MariaDB Concurrency Closure Gate: PASS
Group J: CLOSED

Remaining blockers: NONE
```

Production note: future deployment will use Coolify on a Contabo VPS with a
MySQL-compatible service whose exact engine/version is confirmed during
deployment. That infrastructure was not touched for this closure gate.