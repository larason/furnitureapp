# Group B phases instructions

# Phase 2.12 — CI Baseline

## Objective

Establish the project's first reliable continuous-integration baseline for the Laravel backend.

This is the **final phase of Phase Group B — Laravel Backend Foundation**.

The CI baseline must automatically verify that the backend:

* installs correctly
* satisfies the configured coding-quality checks
* passes static analysis
* passes the established test suite
* starts correctly enough for the defined verification checks
* does not silently bypass failed quality checks

The CI pipeline must remain small and appropriate for the current project.

Do not turn this phase into full CI/CD, deployment automation, staging deployment, production deployment, monitoring, or release management.

---

# 1. Phase Context

Phases 2.1–2.5 are already complete.

Phase 2.6 established API routing.

Phase 2.7 established centralized exception/error handling.

Phase 2.8 established logging.

Phase 2.9 established the health/status endpoint.

Phase 2.10 established coding standards and static analysis.

Phase 2.11 established the backend test framework.

Now implement:

**Phase 2.12 — CI baseline**

This is the last phase in Group B.

The Group B exit condition is:

```text
Laravel runs reliably in local development
with the agreed conventions and automated checks.
```

This phase establishes those automated checks in CI.

The roadmap then moves to:

**Phase Group C — Database and Domain Model**

Do not begin Group C implementation in this phase.

---

# 2. Authoritative Sources

Before modifying the repository, read:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`

Also inspect the actual repository for:

* CI configuration already present
* `.github/workflows/`
* `.gitlab-ci.yml`
* `bitbucket-pipelines.yml`
* other CI configuration
* `composer.json`
* `composer.lock`
* PHP version requirement
* Laravel version
* Phase 2.10 quality commands
* Phase 2.11 test command
* existing environment configuration
* existing scripts/documentation

Do not assume a particular CI provider.

---

# 3. CI Provider Rule

The project sources do not prescribe GitHub Actions, GitLab CI, Bitbucket Pipelines, Jenkins, or another CI platform.

Therefore:

* inspect the repository and hosting configuration first
* reuse an existing CI platform when one is already established
* if no CI platform exists, select the platform naturally associated with the repository
* do not introduce multiple CI systems
* do not create provider-specific alternatives "just in case"

Implement one canonical CI pipeline.

---

# 4. Core Principle

CI is a quality gate.

The intended relationship is:

```text
Developer change
    ↓
CI environment
    ↓
dependency installation
    ↓
format check
    ↓
static analysis
    ↓
tests
    ↓
optional minimal application verification
    ↓
pass / fail
```

A failed required check must fail the CI job.

Do not allow a quality check to fail while CI reports success.

---

# 5. CI Scope

The initial pipeline should verify the Laravel backend only.

Include the checks established by Phase 2.10 and Phase 2.11.

Do not add unrelated frontend or deployment checks.

The minimum expected CI quality gate is:

```text
format check
static analysis
tests
```

Add a lint step only if Phase 2.10 established a separate lint command.

Do not invent a new linting command in Phase 2.12.

---

# 6. Use Existing Local Commands

CI must execute the same commands developers use locally.

Do not create CI-only substitutes for:

```text
format check
static analysis
tests
```

The CI pipeline should call the project's documented Composer/application commands.

For example, if Phase 2.10 established:

```text
composer format:check
composer analyse
```

then CI should use those exact commands.

If Phase 2.11 established:

```text
composer test
```

then CI should use that exact command.

Do not duplicate the underlying tool configuration inside the CI file.

---

# 7. Reproducible Dependency Installation

The CI environment must install dependencies reproducibly.

Use the committed lock file.

Do not allow CI to silently resolve a different dependency graph on every run.

Prefer the Composer workflow appropriate to the repository's existing configuration, such as a lock-file-respecting installation.

Do not modify `composer.lock` automatically inside CI.

Do not run dependency-update operations as part of the normal CI quality gate.

---

# 8. PHP Version

Use the PHP version supported by the repository.

Determine it from the actual project configuration rather than guessing.

CI must not use a PHP version incompatible with:

* `composer.json`
* Laravel
* existing project code
* configured static-analysis tools
* the selected test framework

Do not introduce multiple PHP-version matrices in this baseline unless the repository already requires multi-version support.

For the initial project scale, prefer one authoritative supported PHP version.

---

# 9. Dependency Installation Failure

If dependency installation fails:

* CI must fail
* subsequent quality steps must not be presented as successful
* the failure must be visible in the CI result

Do not use:

```bash
composer install || true
```

or equivalent.

Do not continue after an unrecoverable setup failure.

---

# 10. Application Environment

CI requires a safe non-production environment.

Use a dedicated test/CI configuration.

Do not use:

* production secrets
* production databases
* production payment credentials
* real customer data
* real API credentials

The repository explicitly separates LOCAL, STAGING, and PRODUCTION environments and prohibits production credentials in local development.

---

# 11. Secrets

The baseline CI pipeline should not require production secrets.

Do not introduce CI secrets simply because a future feature may need them.

Do not store secrets inside:

* workflow YAML
* shell scripts
* Composer configuration
* committed `.env` files
* test fixtures

The project explicitly prohibits committing secrets, tokens, or production credentials.

---

# 12. Test Database

Use an isolated CI test database if the test suite requires a database.

The exact strategy should follow the test framework configuration established in Phase 2.11.

Do not use:

* a production database
* a developer-local database
* a shared persistent database without isolation

If the current test suite does not yet require database services, do not provision one merely because Laravel will eventually need it.

---

# 13. Database Service

Only provision a database service in CI if current tests actually require it.

If required:

* use a supported version compatible with the application
* initialize it deterministically
* use safe test credentials
* isolate it to the CI job/environment
* do not persist business data across unrelated runs

Do not create future Group C database schemas in this phase.

---

# 14. Migrations in CI

If the current test configuration requires migrations, run the existing migration mechanism against the isolated CI database.

Do not add new migrations merely for CI.

Do not create domain tables that belong to Group C.

Do not seed future business data merely to make CI green.

CI should execute whatever current application/test setup requires and nothing more.

---

# 15. Formatting Check

Run the Phase 2.10 formatting check in CI.

The check must be non-mutating.

CI should detect formatting drift rather than rewrite the repository.

Do not run the formatter in "fix" mode inside CI and then report success.

The purpose of CI is to tell the developer that the repository does not satisfy the formatting baseline.

---

# 16. Static Analysis

Run the Phase 2.10 static-analysis command in CI.

The command must:

* analyze project code according to the configured scope
* return non-zero on real failures
* respect the committed configuration
* not depend on developer-local files
* not silently ignore all errors

Do not change the static-analysis level simply to make CI pass.

Do not add broad suppressions because CI exposed an existing problem.

---

# 17. Tests

Run the Phase 2.11-established test command in CI.

Test failures must fail the job.

Do not:

```bash
test-command || true
```

Do not convert test failures into warnings.

Do not allow a partially executed test suite to count as passing.

---

# 18. Existing Foundation Tests

The CI baseline must cover the currently implemented foundation tests, including where present:

* Phase 2.7 exception/error handling
* Phase 2.8 logging
* Phase 2.9 health endpoint

Do not create fake future business tests.

---

# 19. Health Endpoint Verification

If the current repository already has a stable way to start the Laravel application in CI, add a minimal smoke verification of the Phase 2.9 health endpoint only when it can be done reliably without introducing disproportionate complexity.

For example:

```text
start application
→ request /health
→ verify successful response
→ stop application
```

This is optional for the baseline if running a local application server inside CI adds substantial complexity.

Do not build a general deployment-smoke framework.

Do not add readiness/dependency checks.

---

# 20. Test Ordering

Arrange CI steps so failures are easy to diagnose.

A reasonable sequence is:

```text
checkout
→ runtime setup
→ dependency installation
→ application/test environment setup
→ format check
→ static analysis
→ tests
→ optional minimal health smoke test
```

Do not run expensive downstream steps after fundamental setup failure.

Do not hide failures through shell constructs.

---

# 21. Caching

Use dependency caching only if it is simple and supported by the selected CI platform.

Caching must not compromise correctness.

Do not cache:

* production secrets
* test databases
* mutable business state
* entire project workspaces indiscriminately

If caching becomes complicated, omit it from the initial baseline.

Correctness is more important than shaving a small amount of CI time.

---

# 22. Lock File and Cache Correctness

CI caching must respect dependency changes.

Changes to:

```text
composer.lock
composer.json
```

must invalidate or appropriately refresh the dependency cache.

Do not reuse stale dependencies after dependency changes.

---

# 23. PHP Extensions

Install only the PHP extensions genuinely required by:

* Laravel
* Composer dependencies
* current tests

Determine them from the actual project/dependency configuration.

Do not provision a large collection of unused extensions.

---

# 24. Node/npm/Frontend Tools

Do not install Node.js or frontend dependencies in the backend CI pipeline unless the current Laravel backend genuinely requires them to execute its established checks.

Phase 2.10 and 2.11 concern the Laravel backend.

Next.js and Flutter quality automation belong to their respective development phases and later CI/CD phases.

---

# 25. MySQL / Other Services

Do not provision:

* Redis
* Elasticsearch
* RabbitMQ
* Kafka
* external payment providers
* cloud storage
* SMTP servers

unless current backend tests genuinely require a service that already exists in the project.

Do not build future infrastructure into CI.

---

# 26. Environment File Handling

Do not commit a real `.env` file.

If Laravel needs environment configuration during CI:

* use CI environment variables
* use a safe test environment mechanism
* generate a temporary environment file only if required by the existing application
* ensure it cannot contain production secrets

Clean up generated temporary files where appropriate.

---

# 27. Application Key

If the current Laravel test environment requires an application key, generate/use a safe ephemeral CI key.

Do not commit a production application key.

Do not use a production key.

Do not require the project owner to manually enter secrets for ordinary CI runs.

---

# 28. Mail and External Delivery

Tests must not send real emails.

If current application behavior requires mail configuration:

* use the test/null/log mail mechanism appropriate to the existing application
* do not use real SMTP credentials
* do not send customer messages

The actual notification implementation is later in the project roadmap.

---

# 29. Queue Behavior

Tests must not dispatch real background work unless specifically designed to test queue behavior.

Use the existing test configuration established in Phase 2.11.

Do not provision persistent workers.

Do not add queue infrastructure to CI.

---

# 30. Logging in CI

CI logs must remain useful but must not expose secrets.

Do not print:

* environment variables indiscriminately
* access tokens
* database credentials
* CI secrets
* payment credentials
* cookies
* guest cart bearer tokens
* upload tokens

The Phase 2.8 sensitive-data logging restrictions remain applicable.

---

# 31. Error Output

When a quality check fails, preserve enough output for diagnosis.

Do not suppress:

* static-analysis errors
* formatter failures
* failing test names
* assertion details

Do not replace useful failure output with "CI failed" alone.

---

# 32. API Contract Protection

CI must not modify the frozen Version 1 API contract.

Do not have CI regenerate or rewrite:

* API routes
* OpenAPI
* response schemas
* enums
* error codes
* generated API documentation

unless the repository already has an explicit existing generation workflow.

The Version 1 API remains frozen.

---

# 33. No Automatic Formatting Commits

Do not configure CI to:

* modify source files
* commit formatting fixes
* push changes
* create automatic commits
* amend pull requests

CI is a verification mechanism.

The project owner retains control over source changes.

---

# 34. Trigger Scope

Configure the baseline to run for the project's normal code-review events.

At minimum, CI should run when changes are proposed for the repository's primary development branch.

Follow the selected CI platform's native pull-request/merge-request mechanism.

Do not add complicated release/tag workflows.

---

# 35. Branch Strategy

Do not redesign the repository's Git branching strategy.

Phase 2.12 only needs a working CI check.

The project documentation recommends feature branch → focused changes → review → automated checks → merge, but this phase should not introduce broader branch governance.

---

# 36. Required Status

The baseline CI result must clearly indicate:

```text
PASS
```

or:

```text
FAIL
```

Do not create ambiguous "warning" states for required checks.

A required quality check that fails must prevent the baseline job from passing.

---

# 37. Job Structure

Keep the initial CI configuration simple.

Prefer one backend quality job unless the repository's existing CI structure naturally requires separation.

A single coherent job is acceptable:

```text
Laravel Backend Quality
```

containing:

```text
setup
→ install
→ format check
→ static analysis
→ tests
```

Split jobs only when there is a real benefit such as independent runtime requirements or clear failure isolation.

Do not create ten jobs for three checks.

---

# 38. CI Configuration Quality

The CI configuration itself should follow the Phase 2.10 code-quality principles:

* explicit naming
* small steps
* no duplicated commands
* no unnecessary abstraction
* no commented-out speculative workflows
* no magic environment assumptions
* no hidden failure suppression

Keep the workflow readable.

---

# 39. Comments in CI Configuration

Keep comments to the absolute minimum.

Do not document obvious YAML steps.

Avoid:

```yaml
# Install dependencies
- run: composer install
```

when the step name already makes that clear.

A comment is justified only for a non-obvious:

* security decision
* platform limitation
* compatibility workaround
* cache invalidation requirement

Do not fill the CI configuration with explanatory prose.

---

# 40. Composer Script Integration

Prefer centralized Composer commands established in Phase 2.10 and 2.11.

For example:

```text
composer format:check
composer analyse
composer test
```

CI should consume these commands rather than duplicating the implementation:

```text
php-cs-fixer ...
phpstan ...
phpunit ...
```

unless the repository does not expose a suitable script.

There should be one authoritative definition of each quality command.

---

# 41. Local/CI Parity

A developer should be able to reproduce a CI failure locally using the documented commands.

For example:

```text
composer format:check
composer analyse
composer test
```

should correspond directly to the relevant CI checks.

Do not create CI-only flags that materially change application behavior unless required by the CI environment.

---

# 42. Failure Reproducibility

When CI fails, the developer should be able to identify the failing category:

```text
format
static analysis
tests
health smoke
```

Do not combine all output into one opaque shell command.

Prefer explicit steps.

---

# 43. Dependency Failure Handling

If Composer installation or environment setup fails, do not attempt to "work around" the failure by:

* ignoring exit status
* using stale caches
* installing unpinned dependencies
* skipping tests
* disabling static analysis

Fix the actual setup/configuration problem.

---

# 44. CI Security

Review the CI workflow for:

* secret exposure
* untrusted pull-request code accessing privileged secrets
* unsafe shell interpolation
* unnecessary token permissions
* generated credentials
* artifact leakage

Grant only the permissions needed by the baseline workflow.

Do not expose production secrets to the baseline CI job.

---

# 45. Third-Party CI Actions/Plugins

If the selected CI provider requires reusable actions/plugins:

* prefer official/provider-maintained components
* pin versions appropriately where the platform permits
* avoid unnecessary third-party actions
* do not install a large ecosystem of actions for simple tasks

Keep the dependency surface small.

---

# 46. Artifact Handling

Do not create large CI artifact pipelines.

For the baseline:

* normal test output should remain visible in CI logs
* do not upload coverage reports unless already required
* do not upload application logs containing sensitive data
* do not upload `.env` files
* do not upload dependency caches as artifacts

Future quality/production phases may add artifact/report handling.

---

# 47. Coverage

Do not make code coverage a required CI gate in Phase 2.12 unless Phase 2.11 already established an explicit project coverage policy.

The project does not prescribe a coverage threshold in the supplied sources.

Do not invent an arbitrary threshold.

---

# 48. Static-Analysis Baseline

Do not permanently suppress static-analysis failures merely to get CI green.

If the current Phase 2.10 baseline contains an intentionally documented narrow suppression, preserve it.

If CI exposes an unexpected problem:

* fix the underlying issue where practical
* otherwise use the same narrow documented baseline mechanism established in Phase 2.10
* do not weaken the analyzer globally

---

# 49. Test Baseline

Do not weaken the test suite to make CI pass.

Do not skip:

* exception/error tests
* logging tests
* health tests

when they already exist.

Do not add `--dont-catch`/debug options or other developer-only behavior to normal CI unless required for useful diagnostics.

---

# 50. Pull Request/Merge Request Behavior

The baseline should give reviewers a visible quality signal.

A proposed change should not appear fully validated while:

* formatting fails
* static analysis fails
* tests fail

Do not implement branch protection rules in this phase unless they are already part of the repository configuration.

CI checks are the prerequisite; repository governance belongs later.

---

# 51. Health Smoke Test

If the Phase 2.9 health endpoint is included in CI verification:

Use it strictly as a smoke check.

Do not:

* inspect database health
* inspect payment provider health
* inspect queue health
* inspect filesystem capacity
* inspect deployment state

The endpoint is a liveness mechanism, not a monitoring platform.

---

# 52. Documentation

Document the minimum CI workflow.

At minimum explain:

* what CI checks
* how developers reproduce CI checks locally
* what command corresponds to formatting
* what command corresponds to static analysis
* what command corresponds to tests

Do not create a large CI operations manual.

---

# 53. CI and Future Phases

This baseline is intended to support future phases.

Later phases may add:

* database/domain tests
* authentication tests
* authorization tests
* integration tests
* frontend CI
* Flutter CI
* security testing
* E2E
* production deployment checks

Do not implement those now.

The roadmap explicitly separates the backend foundation from later QA, production operations, and deployment groups.

---

# 54. No Deployment

Do not implement:

* staging deployment
* production deployment
* automatic migrations against staging/production
* rollback
* release publishing
* Docker deployment
* server provisioning
* domain configuration

Those belong to later CI/CD and production phases.

---

# 55. No Production Monitoring

Do not implement:

* uptime monitoring
* dashboards
* alerts
* error tracking
* distributed tracing
* log aggregation
* Prometheus
* Grafana
* Sentry
* Datadog
* New Relic
* OpenTelemetry

The AGENTS roadmap places broader logging/monitoring and error tracking later in production operations.

---

# 56. No Database/Domain Work

Do not use CI setup as an opportunity to begin Group C.

Do not create:

* users schema
* roles schema
* products schema
* inventory schema
* carts schema
* orders schema
* payments schema
* requests/enquiries schema
* domain factories
* domain services

The Group B exit is infrastructure reliability, not domain implementation.

---

# 57. Verification

Before declaring Phase 2.12 complete:

Run the same commands locally that CI executes.

At minimum:

```text
composer format:check
composer analyse
composer test
```

using the actual repository command names.

Then run the CI pipeline through the chosen repository platform.

Verify that:

* a passing change produces a passing CI result
* a formatting failure causes CI failure
* a static-analysis failure causes CI failure
* a test failure causes CI failure

Do not declare CI complete solely because the YAML/workflow file parses.

---

# 58. Negative Verification

Perform at least one controlled negative verification.

Temporarily introduce a harmless failure such as a deliberately failing test or formatting violation in a local/uncommitted state and confirm the CI command would fail appropriately.

Do not merge the deliberate failure.

The purpose is to prove that the quality gate actually rejects failures.

---

# 59. Repository Inspection

After CI configuration is complete, inspect the repository change set.

Confirm that only Phase 2.12 changes remain:

* CI configuration
* minimal CI documentation
* necessary supporting configuration
* necessary Composer/environment adjustments

Do not leave:

* CI-generated secrets
* temporary environment files
* test databases
* generated reports
* machine-specific paths
* debug files
* unrelated dependency upgrades

---

# 60. Final Group B Verification

Because this is the final Group B phase, explicitly verify all foundation layers together:

```text
2.6  API routing
2.7  exception/error handling
2.8  logging
2.9  health/status
2.10 coding standards/static analysis
2.11 test framework
2.12 CI baseline
```

Confirm that these layers work together without introducing unrelated business functionality.

The Group B exit condition is only satisfied when Laravel reliably runs locally with the agreed conventions and automated checks.

---

# 61. Definition of Done

Phase 2.12 is complete only when:

1. One canonical CI pipeline exists for the Laravel backend.
2. CI uses the repository's supported PHP/runtime configuration.
3. Dependencies are installed reproducibly from the committed lock file.
4. The Phase 2.10 formatting check runs in CI.
5. The Phase 2.10 static-analysis check runs in CI.
6. The Phase 2.11 test suite runs in CI.
7. Required check failures cause CI failure.
8. CI does not suppress or ignore quality/test failures.
9. CI does not require production secrets.
10. CI does not access production infrastructure.
11. CI uses an isolated test environment where required.
12. Existing Phase 2.7–2.9 tests remain covered.
13. Developers can reproduce CI quality checks locally.
14. No deployment automation has been introduced.
15. No production monitoring platform has been introduced.
16. No Group C domain/database implementation has been introduced.
17. CI configuration is readable and maintainable.
18. New CI configuration contains only the minimum necessary comments.
19. The complete Group B foundation passes its automated checks.

---

# 62. Review Checklist

Before completion:

* [ ] existing CI configuration was inspected first
* [ ] one canonical CI provider/workflow was selected
* [ ] no duplicate CI systems were added
* [ ] PHP version matches repository requirements
* [ ] Composer uses the committed lock file
* [ ] dependency installation failure fails CI
* [ ] test environment is non-production
* [ ] no production credentials are required
* [ ] no secrets are committed
* [ ] formatter check runs
* [ ] static analysis runs
* [ ] tests run
* [ ] all required checks have meaningful failure exit status
* [ ] no `|| true` or equivalent failure suppression exists
* [ ] existing Phase 2.7–2.9 tests execute
* [ ] optional health smoke test, if implemented, is minimal and safe
* [ ] CI does not mutate source files
* [ ] CI does not commit or push changes
* [ ] CI does not deploy
* [ ] CI does not provision production infrastructure
* [ ] no monitoring platform was added
* [ ] no coverage threshold was invented
* [ ] no domain/database work was introduced
* [ ] local commands reproduce the CI checks
* [ ] negative verification proves failures fail CI
* [ ] no generated secrets/artifacts remain
* [ ] comments in CI/configuration are minimal
* [ ] all Group B foundation checks pass

---

# 63. Explicitly Out of Scope

Do **not** implement during Phase 2.12:

* staging deployment
* production deployment
* deployment approval workflows
* rollback automation
* release automation
* Docker/Kubernetes deployment
* infrastructure provisioning
* production migrations
* production database access
* branch protection rules unless already present
* production secrets
* monitoring
* alerting
* dashboards
* error-tracking platforms
* distributed tracing
* log aggregation platforms
* Prometheus
* Grafana
* Sentry
* Datadog
* New Relic
* OpenTelemetry
* frontend CI
* Next.js CI
* Flutter CI
* E2E/browser CI
* mobile CI
* security-scanning platform
* dependency-update bots
* coverage thresholds
* performance testing
* load testing
* database/domain implementation
* migrations for Group C entities
* authentication
* authorization
* catalog
* inventory
* cart
* checkout
* orders
* payments
* webhooks
* notifications
* audit infrastructure
* new API endpoints
* API contract changes

---

# 64. Group B Exit Condition

After completing Phase 2.12, verify the Group B exit condition:

```text
Laravel runs reliably in local development
with the agreed conventions and automated checks.
```

The following foundation must now exist as one coherent backend baseline:

```text
API routing
+
centralized exception/error handling
+
logging
+
health/status
+
coding standards/static analysis
+
test framework
+
CI baseline
```

Do not move into Group C automatically as part of this phase.

The project owner should request the next phase separately, consistent with the repository's one-micro-phase development rule.

---

# 65. STOP Condition

Stop immediately when the Group B exit condition and Phase 2.12 definition of done are satisfied.

Do not begin Phase Group C.

Do not create database/domain schemas.

Do not implement users, products, inventory, carts, orders, payments, requests, enquiries, or notifications.

Do not add frontend CI.

Do not add deployment automation.

Do not add production monitoring.

Do not change the frozen Version 1 API contract.

Do not introduce CI-specific behavior that developers cannot reproduce locally without a clear reason.

Do not commit, stage, or push changes. Leave source-control operations to the project owner, consistent with the repository instructions.
