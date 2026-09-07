# Group B phases instructions

# Phase 2.11 — Test Framework Setup

## Objective

Establish the Laravel backend's test-framework foundation so future phases can add unit, API/feature, security, domain, integration, and regression tests consistently.

This phase must establish:

* the project's supported test framework
* test configuration
* test directory conventions
* test environment configuration
* base test helpers/fixtures only where genuinely reusable
* reliable local test execution
* clear commands for running the full suite and focused tests
* a clean foundation for later domain/API test development

Do not implement the project's comprehensive business test suite in this phase.

---

# 1. Phase Context

Phases 2.1–2.5 are already complete.

Phase 2.6 established API routing.

Phase 2.7 established exception/error handling.

Phase 2.8 established logging.

Phase 2.9 established the health/status endpoint.

Phase 2.10 established coding standards and static analysis.

Now implement:

**Phase 2.11 — Test Framework Setup**

Phase 2.12 will establish CI.

Do not implement Phase 2.12 in this phase.

The roadmap explicitly requires the project to proceed one micro-phase at a time.

---

# 2. Authoritative Sources

Before modifying the repository, read:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`

Also inspect the actual Laravel repository:

* `composer.json`
* `composer.lock`
* existing test files
* `phpunit.xml` if present
* `tests/`
* existing test helpers
* existing test bootstrap
* `.env.example`
* test environment configuration
* any existing Composer test scripts
* Phase 2.7 exception/error tests
* Phase 2.8 logging tests
* Phase 2.9 health endpoint tests
* Phase 2.10 quality scripts

Do not assume the repository is still in its default Laravel state.

---

# 3. Inspect Before Installing

First determine whether a test framework already exists.

Check for:

```text
PHPUnit
Pest
phpunit.xml
pest.php
tests/
```

and existing Composer scripts.

If a working test framework already exists:

* reuse it
* do not replace it
* improve only what is necessary for this phase

If no usable framework exists, introduce the smallest mature framework appropriate for the existing Laravel/PHP version.

Do not install multiple competing test frameworks.

---

# 4. Framework Selection

The uploaded project documents require a testing capability but do not mandate PHPUnit or Pest specifically.

Therefore:

* do not claim that a particular framework is required by `AGENTS.md`
* prefer the framework already used by the repository
* if choosing a new framework, choose one that integrates cleanly with Laravel
* keep the choice conventional and maintainable
* avoid experimental or overly specialized testing frameworks

Do not add additional test libraries merely because they might be useful someday.

---

# 5. Composer Dependency Management

If a testing dependency must be added:

* use Composer
* choose a version compatible with the current PHP/Laravel version
* update `composer.lock`
* do not modify unrelated runtime dependencies
* do not commit vendor code
* do not upgrade Laravel or PHP merely to obtain a newer testing tool

Only change dependencies required for the test framework.

---

# 6. Test Environment

Create or standardize an isolated test environment.

Tests must not depend on developer production configuration.

Do not use production credentials.

Do not use real payment credentials.

Do not connect tests to a production database.

The project defines separate LOCAL, STAGING, and PRODUCTION environments and prohibits production secrets in local development.

---

# 7. Test Database Strategy

Establish the mechanism future backend tests will use for database-backed testing.

At this stage there may be no domain tables yet.

Therefore:

* make the test database configuration ready
* do not create future domain migrations solely for tests
* do not create test-only business tables
* do not invent fixtures for entities that do not yet exist

The database/domain schemas begin in Group C.

The test framework should be ready to execute migration-based tests once those schemas exist.

---

# 8. Database Isolation

Future database tests must not contaminate each other.

Establish the test infrastructure so tests can use appropriate isolation mechanisms such as the Laravel-supported transaction or refresh strategy when database-backed tests are introduced.

Use the simplest correct mechanism compatible with the current application.

Do not invent a custom database-reset framework.

Do not optimize database testing before the domain schema exists.

---

# 9. Test Directory Structure

Use a clear structure consistent with Laravel and the project's current repository.

At minimum, distinguish:

```text
tests/
    Unit/
    Feature/
```

Use additional directories only when they are genuinely needed later.

A reasonable conceptual separation is:

### Unit

Pure logic with minimal framework/database dependencies.

Examples later:

* calculations
* state transition validation
* delivery-fee calculations
* value objects

### Feature

HTTP/API and application-integration behavior.

Examples later:

* authentication
* catalog endpoints
* cart
* checkout
* orders
* requests
* enquiries
* authorization

Do not build all of those now.

---

# 10. Test Naming

Establish predictable test naming.

Use descriptive test names that explain behavior rather than implementation.

Prefer:

```text
customer_cannot_cancel_order_after_cancellation_window
```

over:

```text
testCancelMethod2
```

Test names should make failures understandable without opening the implementation.

---

# 11. Test Organization

Keep tests cohesive.

One test should have a clear behavioral purpose.

Avoid giant test classes containing unrelated domains.

Avoid deeply nested helper hierarchies.

Avoid generic fixtures that exist only to shorten a few tests.

Follow the same project code-quality principles applied to production code:

* small cohesive modules
* explicit naming
* single responsibility
* avoid premature abstraction.

---

# 12. Base Test Class

If the framework/repository requires a common application test base, establish one.

Keep it minimal.

It may provide common application bootstrap behavior required by Laravel.

Do not turn the base class into a giant collection of domain helpers.

Avoid methods such as:

```text
createCustomer()
createPaidOrder()
createDeliveryOrder()
createInventory()
createStaff()
createAdmin()
```

when those domain entities do not yet exist.

Future domain-specific helpers should be introduced with the corresponding domain phase.

---

# 13. HTTP Testing Foundation

Prepare the framework for future API/feature tests.

The project API uses:

```text
/api/v1
```

and the frozen API architecture requires consistent response/error behavior.

The test infrastructure should make it easy for later tests to verify:

* HTTP method
* path
* status
* JSON body
* headers
* request ID
* authentication behavior
* authorization behavior
* validation behavior

Do not implement comprehensive endpoint tests now.

---

# 14. API Test Assertions

Do not create a custom assertion layer unless repeated real needs justify it.

Laravel's existing JSON/HTTP testing facilities should be preferred.

Future API tests must be able to verify the frozen contract, including:

```text
data
errors
meta
meta.request_id
field
details
HTTP status
```

The frozen API contract requires consistent `errors` envelopes and request correlation.

Do not duplicate the entire API contract into test helper code.

---

# 15. Phase 2.7 Error Tests

Ensure the existing Phase 2.7 error-handling tests execute under the standardized test framework.

Where useful, add a small foundational test set covering:

* error envelope
* HTTP status mapping
* request ID presence
* unexpected exception sanitization
* validation error representation

Do not expand this into the full application error matrix.

---

# 16. Phase 2.8 Logging Tests

Ensure the Phase 2.8 logging tests execute correctly.

At minimum preserve the ability to test:

* request correlation
* unexpected-exception logging
* sensitive-data exclusions

Do not add comprehensive operational logging tests for future payment/webhook/queue systems.

---

# 17. Phase 2.9 Health Tests

Ensure the Phase 2.9 health endpoint has a focused test suite.

At minimum verify:

```text
GET /health
→ expected success
```

and appropriate method/authentication behavior.

Do not add readiness or infrastructure tests that do not exist in the implementation.

---

# 18. Test Configuration

Establish version-controlled test configuration.

Configure:

* application environment
* test bootstrap
* test discovery
* test directories
* appropriate output
* appropriate failure behavior

Do not place developer-specific configuration in the test framework.

Do not commit secrets in test configuration.

---

# 19. Environment Variables

Tests must use safe test values.

Use existing configuration infrastructure where possible.

Do not hard-code production-like credentials.

Do not add fake payment credentials that resemble real production secrets.

Use clearly non-production test configuration.

---

# 20. Time Handling Foundation

Future business rules rely on backend-controlled time, including the 20-minute order-cancellation window.

The test infrastructure should make deterministic time testing possible using the Laravel/framework facilities already available.

Do not introduce a custom time abstraction solely for testing.

Do not implement cancellation behavior now.

The frozen API/domain conventions require the cancellation window to use backend time rather than client-supplied timestamps. This test foundation must eventually support deterministic verification of that rule.

---

# 21. Randomness and IDs

Do not make foundational tests depend on unpredictable values unless randomness itself is under test.

Where the framework already provides deterministic/fake mechanisms, use them appropriately.

Do not create a custom global ID generator for testing.

Do not hard-code assumptions about future opaque IDs or order references beyond what is already defined by the contract.

---

# 22. External Services

The test framework must default to isolated tests.

Do not allow ordinary test runs to call:

* payment providers
* email providers
* SMS providers
* third-party APIs
* real webhooks
* production storage
* external monitoring

External integration tests can be introduced deliberately in later phases.

---

# 23. File/System Isolation

Prepare the test environment so future tests can safely use temporary storage where needed.

Do not create permanent test files in production/application storage.

Do not allow test execution to modify developer data unexpectedly.

Do not build a custom file-testing framework before attachment/upload functionality exists.

---

# 24. Queue Isolation

Do not introduce queue infrastructure.

If current application code already references queues, configure tests so ordinary tests do not unintentionally dispatch real asynchronous work.

Use the framework's standard fake/synchronous testing mechanisms where already applicable.

Future queue behavior belongs to later phases.

---

# 25. Mail/Notification Isolation

Do not implement notification functionality.

If the current framework/application already has mail/notification hooks, ensure tests can safely fake or suppress external delivery.

Do not send real emails during ordinary test execution.

Actual notification features belong to later phases.

---

# 26. Authentication Test Preparation

Authentication is not implemented yet.

Therefore do not implement *additional* product-level authentication fixtures:

* token factories
* login helpers (beyond framework `actingAs`)
* role helpers for future domain roles
* customer/admin identity builders for future business entities

The skeleton `UserFactory` and existing framework-level authentication boundary tests that use Laravel's `actingAs` (as proven in Phase 2.7–2.9 for `401`/`403` via `tests/Feature/ApiErrorHandlingTest` and `ApiRoutingSmokeTest`) **remain and continue to run**. Only new product-level fixtures for future domain authentication are deferred.

When authentication is implemented later, the test framework should support authenticated-request testing through the framework's standard facilities.

---

# 27. Authorization Test Preparation

Do not implement authorization policies in Phase 2.11.

Only ensure the test architecture is capable of later testing:

```text
CUSTOMER
STAFF
ADMIN
```

ownership and permissions when those capabilities are introduced.

Do not invent fake authorization infrastructure solely for tests.

---

# 28. Factories and Seeders

Do not create comprehensive model factories yet.

The database/domain model begins in Group C.

Create factories only for models that already exist and actually need them for current tests.

Do not generate speculative factories for:

```text
Product
Order
Payment
Delivery
Inventory
FurnitureRequest
Enquiry
Notification
```

if those models do not yet exist.

Likewise, do not create domain seeders merely to make the test suite look complete.

---

# 29. Fixtures

Prefer small, local fixtures over a giant global fixture system.

When future tests need representative payloads, introduce them in the relevant domain/API phase.

Do not create hundreds of fixture records now.

---

# 30. Test Data Security

Do not put sensitive information into committed test fixtures.

Never commit:

* real passwords
* API keys
* tokens
* production customer data
* real payment credentials
* real personal addresses
* real phone numbers where unnecessary

Use clearly synthetic test data.

The project security baseline prohibits committing secrets and production credentials.

---

# 31. Assertion Quality

Tests should verify observable behavior rather than private implementation details.

Prefer:

```text
HTTP 422
error code INVALID_VALUE
field fulfillment_type
```

over:

```text
validator class contains rule X
```

when testing API behavior.

Prefer state/business outcomes over internal method-call counts unless interaction testing is specifically justified.

Do not over-specify implementation details that would make harmless refactoring break tests.

---

# 32. Test the Contract, Not the English Message

For API error tests, assert:

```text
code
field
details
HTTP status
meta.request_id
```

rather than relying primarily on exact English messages.

The frozen API conventions define the machine-readable code as the stable client contract and the message as human-readable text.

Messages may evolve without necessarily changing the contract.

---

# 33. Closed Enums

Future tests must treat V1 enums as closed.

The test foundation must not encourage assertions like:

```text
status is any string
```

Later tests should verify exact documented enum values.

The V1 API conventions explicitly define closed enums and require exact documented values.

Do not introduce new enum values during this phase.

---

# 34. Strict Request Validation

Future API tests must be capable of verifying the strict-request policy.

In particular:

* unknown fields are rejected where required
* `additionalProperties: false` semantics are preserved
* server-controlled fields cannot be submitted
* `validated()` data flows through controlled boundaries

Do not create test helpers that encourage unrestricted payload construction through:

```php
$request->all()
```

or equivalent.

The existing contract explicitly rejects unknown fields for strict operations and prohibits unrestricted mass assignment.

---

# 35. Test Isolation from API Contract Changes

Do not encode an unofficial second version of the API in tests.

Tests should reference the frozen contract through:

* actual route definitions
* actual response behavior
* authoritative constants/enums where they already exist

Do not duplicate every API schema as manually maintained test metadata.

That creates two sources of truth.

---

# 36. Static Analysis Compatibility

The new tests must be compatible with the Phase 2.10 static-analysis baseline.

Run static analysis against test code if the chosen project configuration includes tests.

Resolve genuine type issues rather than suppressing the analyzer broadly.

Do not exclude the entire `tests/` directory simply because tests are difficult to type.

---

# 37. Formatting Compatibility

Apply the Phase 2.10 formatting standard to test code.

Tests are first-class project code.

Do not maintain a separate test formatting style.

---

# 38. Test Commands

Provide simple, documented commands for:

### Full test suite

```text
composer test
```

or the repository's equivalent.

### Focused test execution

The framework's standard mechanism should make it possible to run:

```text
one test file
one test class
one named test
```

Do not create a complicated custom CLI wrapper unless genuinely necessary.

---

# 39. Composer Integration

Where appropriate, add a Composer script for the normal test suite.

For example:

```text
composer test
```

The exact command may differ according to the chosen framework.

The important requirement is consistency.

The command must return:

* success when tests pass
* failure when tests fail

Do not hide failures with constructs such as:

```bash
test-command || true
```

The later CI phase depends on meaningful exit status.

---

# 40. Test Output

Configure normal test execution to provide useful failure information without excessive noise.

The default developer command should make failures easy to diagnose.

Do not customize output excessively.

Do not suppress failed-test details simply to make logs shorter.

---

# 41. Test Bootstrap

Keep test bootstrap code minimal.

It should establish only what all tests genuinely need.

Avoid putting application-specific business setup into the global bootstrap.

Do not initialize:

* product fixtures
* customer records
* orders
* payments
* inventory
* external services

globally.

Future test suites should control their own fixtures.

---

# 42. Parallel Testing

Do not make parallel testing a mandatory requirement in this phase.

If the existing Laravel/test framework supports parallel execution naturally, ensure the test configuration does not prevent it.

Do not optimize for parallel execution before there is a meaningful test suite.

Avoid introducing complexity that is not currently needed.

---

# 43. Coverage

Do not introduce an aggressive mandatory coverage threshold in this phase.

There is not yet a sufficiently developed business/domain test suite to justify an arbitrary percentage.

The test framework should be compatible with coverage reporting where supported.

Later quality phases can establish meaningful coverage expectations based on actual critical workflows.

Do not manipulate coverage by excluding production code merely to achieve a target.

---

# 44. Regression Test Foundation

Make it easy to add a regression test whenever a production bug is fixed.

The AGENTS testing strategy explicitly requires regression tests when production bugs are corrected.

Do not build a separate regression framework.

Normal tests should serve this purpose.

---

# 45. Security Test Foundation

Ensure the test setup can later support security-focused API tests.

Future tests must be able to verify:

* authentication boundaries
* authorization boundaries
* IDOR protection
* 404 masking
* rate limiting
* CSRF behavior where applicable
* CORS behavior where applicable
* strict input validation
* sensitive-data protection
* mass-assignment protection

Do not implement those business/security systems now.

Only make the test infrastructure capable of testing them.

---

# 46. Logging Test Safety

Tests must not accidentally emit sensitive logs into persistent development logs.

Where logging is tested:

* use isolated test configuration
* use controlled log destinations/fakes where appropriate
* assert sensitive values are absent
* avoid printing credentials to test output

This is especially important because Phase 2.8 established sensitive-data logging restrictions.

---

# 47. HTTP Error Compatibility

Keep the test foundation compatible with the frozen error architecture.

Future tests must be able to verify the canonical relationships:

```text
400 → INVALID_JSON
401 → AUTHENTICATION_REQUIRED
403 → FORBIDDEN
404 → RESOURCE_NOT_FOUND
409 → CONFLICT
413 → REQUEST_TOO_LARGE
415 → UNSUPPORTED_MEDIA_TYPE
422 → validation/business error
429 → RATE_LIMITED + Retry-After
500 → INTERNAL_SERVER_ERROR
502/503/504 → external-service family
```

Do not add new error mappings merely to simplify tests.

---

# 48. Rate-Limit Test Preparation

Do not implement rate limiting yet.

However, the test infrastructure should later allow headers to be asserted, especially:

```text
Retry-After
```

for `429 RATE_LIMITED`.

Do not create fake rate-limit middleware merely for this phase.

The frozen API contract requires standard `Retry-After` behavior.

---

# 49. Request ID Test Preparation

Tests must be capable of checking that:

```text
API error meta.request_id
==
server-side request correlation identifier
```

where the logging test infrastructure permits this without depending on production logging.

Do not create a second request-ID mechanism specifically for tests.

---

# 50. Test Naming and Comments

Keep test-code comments to the absolute minimum.

Do not comment obvious Arrange/Act/Assert sections mechanically.

Avoid:

```php
// Arrange
// Act
// Assert
```

unless the test is genuinely difficult to follow without them.

Prefer clear test names and straightforward structure.

A comment is justified only for a non-obvious testing constraint, framework workaround, or security requirement.

---

# 51. Documentation

Document the minimum developer workflow.

At minimum specify:

```text
composer test
```

or the actual repository equivalent.

Also document:

* how to run a focused test
* where tests live
* how the test environment is configured
* any important database isolation requirement

Do not create a large testing manual.

---

# 52. No CI

Do not implement:

* GitHub Actions
* GitLab CI
* Bitbucket pipelines
* Jenkins
* deployment checks
* branch protections
* CI quality gates

Phase 2.12 owns CI.

The repository's roadmap explicitly separates test-framework setup from CI baseline.

---

# 53. No Comprehensive Business Tests

Do not implement the future test matrix yet.

The project eventually needs tests for areas such as:

```text
invalid product type
insufficient stock
invalid variant
missing delivery information
invalid enum
unknown field
unauthorized order
expired cancellation window
invalid transition
duplicate checkout
```

but those belong alongside the corresponding business/domain implementations.

The API conventions explicitly require such business rules to have backend/domain-level tests eventually.

Do not create fake domain implementations just to test them now.

---

# 54. No Domain Factories

Do not implement factories for domain entities that have not yet been built.

Group C will establish the database/domain model.

The test framework should be ready for those factories later.

---

# 55. No Frontend Testing

Do not configure:

* Next.js testing
* React testing
* MUI component testing
* Flutter tests
* Dart analysis/testing

This phase is Laravel backend test infrastructure only.

---

# 56. No E2E Framework

Do not introduce:

* Playwright
* Cypress
* browser automation
* mobile device automation

The project's end-to-end test strategy comes later in Quality Assurance.

The current phase only establishes backend test-framework infrastructure.

---

# 57. No External Integration Test Infrastructure

Do not introduce test environments for:

* payment gateways
* SMS providers
* email providers
* cloud object storage
* third-party APIs

Those integrations belong to later phases.

---

# 58. Verification

Run the repository's test command after setup.

At minimum:

```bash
composer test
```

using the actual script name chosen for the project.

Also run:

```bash
php artisan test
```

when the repository uses Laravel's standard test entry point.

Run the Phase 2.7–2.9 focused tests and confirm they remain green.

Run the Phase 2.10 formatting/static-analysis commands as configured.

Do not wait for CI to verify the work.

---

# 59. Verification of Failure Behavior

Intentionally verify that the test command fails when a test fails.

Do not merely confirm that the command exits successfully once.

The test process must have a meaningful non-zero exit status on failure.

Similarly, verify focused test execution works.

---

# 60. Repository Inspection

After setup, inspect the change set.

Ensure it contains only:

* test-framework dependencies
* test configuration
* necessary test directory/bootstrap changes
* focused foundation tests
* documentation required for local testing

Do not leave behind:

* generated reports
* machine-local test artifacts
* coverage files
* IDE files
* secret-bearing environment files
* unrelated dependency upgrades

---

# 61. Definition of Done

Phase 2.11 is complete only when:

1. A single supported backend test framework is established.
2. The test framework runs successfully against the current Laravel application.
3. The test environment is isolated from production.
4. Test configuration is version-controlled.
5. `tests/Unit` and `tests/Feature` conventions are established or preserved.
6. A normal full-suite test command exists.
7. Focused test execution works.
8. Test failures produce non-zero exit status.
9. Existing Phase 2.7–2.9 tests run successfully.
10. The framework is ready for database-backed tests once Group C creates the domain schemas.
11. No speculative factories, domain fixtures, or business implementations have been introduced.
12. Static analysis and formatting remain compatible with the Phase 2.10 baseline.
13. Test documentation is sufficient for another developer to run tests locally.
14. New test code contains only the minimum necessary comments.
15. The repository is ready for Phase 2.12 CI baseline.

---

# 62. Review Checklist

Before completion:

* [ ] existing test framework was inspected first
* [ ] no duplicate test framework was introduced
* [ ] selected framework is compatible with PHP/Laravel
* [ ] Composer dependencies are appropriate
* [ ] `composer.lock` is consistent
* [ ] test environment is isolated
* [ ] production credentials are not used
* [ ] test database configuration is safe
* [ ] `tests/Unit` exists or existing convention is preserved
* [ ] `tests/Feature` exists or existing convention is preserved
* [ ] common test bootstrap is minimal
* [ ] no speculative domain factories were added
* [ ] no speculative domain fixtures were added
* [ ] HTTP/API tests can inspect status/body/headers
* [ ] request ID testing is possible
* [ ] error-envelope testing is possible
* [ ] Phase 2.7 tests pass
* [ ] Phase 2.8 tests pass
* [ ] Phase 2.9 tests pass
* [ ] full test command succeeds
* [ ] focused test execution succeeds
* [ ] intentional test failure produces non-zero exit status
* [ ] formatting remains compliant
* [ ] static analysis remains compliant
* [ ] no CI was added
* [ ] no E2E framework was added
* [ ] no frontend test framework was added
* [ ] no external integration-test infrastructure was added
* [ ] no secrets were added
* [ ] no generated artifacts were committed
* [ ] code comments are minimal
* [ ] documentation explains the test commands

---

# 63. Explicitly Out of Scope

Do **not** implement during Phase 2.11:

* CI/CD
* GitHub Actions
* GitLab CI
* Jenkins
* automated deployment gates
* comprehensive domain tests
* database/domain migrations
* business-rule implementations
* Product factories unless Product already exists and genuinely requires one for current tests
* Order factories
* Payment factories
* Inventory factories
* User authentication implementation
* authorization implementation
* role/permission implementation
* catalog implementation
* cart implementation
* checkout implementation
* order implementation
* payment implementation
* webhook implementation
* delivery implementation
* furniture-request implementation
* enquiry implementation
* notification implementation
* rate-limiter implementation
* audit-log implementation
* frontend testing
* Next.js testing
* Flutter testing
* browser E2E testing
* mobile E2E testing
* payment-provider integration testing
* external API integration infrastructure
* production monitoring
* error tracking
* test dashboards
* mandatory coverage thresholds
* parallel-test optimization
* custom test orchestration frameworks
* API contract redesign
* new API endpoints

---

# 64. STOP Condition

Stop immediately when the Phase 2.11 definition of done is satisfied.

Do not continue into Phase 2.12.

Do not create CI.

Do not create domain factories for future entities.

Do not implement business rules simply to increase test coverage.

Do not introduce frontend or E2E test tooling.

Do not introduce external-service test environments.

Do not replace the established testing framework merely because another tool has attractive features.

Do not change the frozen Version 1 API contract.

Do not commit, stage, or push changes. Leave source-control operations to the project owner, consistent with the repository instructions.
