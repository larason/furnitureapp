# Group B phases instructions

# Phase 2.10 — Coding Standards and Static Analysis

## Objective

Establish and enforce the Laravel backend coding-quality baseline.

This phase must provide:

* consistent PHP code formatting
* a defined coding standard for the repository
* static analysis for PHP code
* repeatable local quality commands
* configuration that is compatible with the current Laravel application
* documentation of the quality commands and scope
* a clean baseline for later testing and CI phases

The goal is not to "fix everything imaginable" or redesign the codebase.

Keep the implementation small, dependency-aware, and appropriate for the current project size.

---

# 1. Phase Context

Phases 2.1–2.5 are already complete.

Phase 2.6 established API routing.

Phase 2.7 established exception/error handling.

Phase 2.8 established logging.

Phase 2.9 established the health/status endpoint.

Now implement:

**Phase 2.10 — Coding standards and static analysis**

The roadmap places Phase 2.11 immediately afterward for test-framework setup and Phase 2.12 for CI.

Do not implement those later phases here.

---

# 2. Authoritative Sources

Before modifying the repository, read the current versions of:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`

Also inspect the current backend:

* `composer.json`
* existing `composer.lock`
* Laravel configuration
* existing scripts/commands
* current PHP version requirement
* existing formatting/linting/static-analysis configuration, if any
* current tests and test configuration

Do not assume the repository has the exact tools or versions you expect.

---

# 3. Source-of-Truth Rule

The project explicitly requires:

* small cohesive modules
* explicit naming
* single responsibility
* type safety
* dependency injection where useful
* small services/actions instead of huge controllers
* centralized validation
* centralized API clients
* consistent error handling
* formatting/linting/static analysis

It explicitly warns against:

* giant controllers
* giant components
* copy-pasted business rules
* magic strings scattered throughout the code
* database queries embedded everywhere
* premature microservices
* premature generic abstractions
* overengineering for hypothetical future features.

Treat these as repository engineering rules.

Do not replace them with a generic framework-specific style guide that conflicts with the project.

---

# 4. Inspect Before Installing

Before adding any new quality tool:

1. inspect `composer.json`
2. inspect `composer.lock`
3. search for existing formatter/linter/static-analysis configuration
4. inspect existing scripts in `composer.json`
5. inspect any existing developer documentation
6. determine the PHP version actually supported by the project
7. determine whether a formatter or analyzer is already installed

If the repository already has a suitable tool, reuse it.

Do not install a duplicate formatter, analyzer, or overlapping tool.

---

# 5. Tool Selection

The uploaded project sources require the **capability** of formatting, linting, and static analysis, but they do not prescribe specific tools.

Therefore:

* do not claim that a particular tool is mandated by `AGENTS.md`
* choose the smallest mature tooling set compatible with the existing Laravel/PHP project
* prefer tools already present in the repository
* if no formatter exists, a Laravel/PHP-compatible formatter may be introduced
* if no static analyzer exists, introduce one appropriate to the repository's PHP version and current architecture

A reasonable implementation may use the Laravel/PHP ecosystem's standard tools, but the final choice must be based on the repository rather than assumption.

Do not introduce multiple overlapping static-analysis products.

---

# 6. Formatting Standard

Establish one canonical PHP formatting standard.

The formatter must be:

* deterministic
* runnable locally
* suitable for the Laravel codebase
* able to check or format PHP source consistently
* configured in version-controlled project configuration

The project should not depend on each developer's IDE formatting preferences.

---

# 7. Formatting Scope

Apply formatting standards to source files that belong to the Laravel backend.

Include appropriate PHP code such as:

```text
app/
bootstrap/ where applicable
config/
routes/
database/
tests/ if tests already exist
```

Exclude generated/vendor content.

Do not format:

* `vendor/`
* framework-generated external files
* binary files
* environment files
* secrets
* generated artifacts that should not be manually changed

Follow the actual repository layout rather than blindly applying these exact directories.

---

# 8. Formatting Policy

The formatter must become the canonical mechanism.

Do not maintain multiple conflicting formatting rules such as:

```text
IDE-specific formatting
+
manual formatting convention
+
formatter with different output
```

The repository should have one source of truth.

The formatting command must be documented and reproducible.

---

# 9. Existing Code Cleanup

When introducing the formatter:

* inspect the current codebase
* format existing project code where necessary
* avoid unrelated functional refactoring
* avoid changing business behavior
* avoid changing API behavior
* avoid changing database behavior

Formatting-only changes are acceptable when required to establish the baseline.

Do not use the formatting pass as an excuse to rewrite the architecture.

---

# 10. Static Analysis

Introduce a PHP static-analysis baseline appropriate to the current project.

The analyzer should detect issues such as:

* invalid types
* incompatible method signatures
* impossible/nullability assumptions
* unreachable or inconsistent code where supported
* incorrect property/method usage
* invalid return types
* invalid parameter types
* obvious API misuse
* incorrect collection/value assumptions where detectable

Static analysis should run against project code, not third-party vendor code.

---

# 11. Analysis Scope

Start with the application code actually controlled by this repository.

Do not analyze external vendor internals unless the chosen tool explicitly supports this safely and there is a demonstrated need.

The initial scope should normally include:

```text
app/
routes/ where analyzable
config/ where analyzable
database/ application PHP files where applicable
tests/ if present
```

Adapt to the repository structure.

Do not include generated/vendor code merely to increase the reported issue count.

---

# 12. Analysis Level

Do not start with an unrealistically strict analysis level that forces hundreds of unrelated suppressions.

Establish the strongest useful baseline that the current foundation can reasonably satisfy.

Prioritize:

1. correctness
2. type safety
3. meaningful defects
4. maintainability
5. gradual tightening later

Do not create a configuration whose primary effect is to silence the analyzer.

---

# 13. Baseline Errors

The initial repository may contain issues discovered by static analysis.

Handle them deliberately.

For every existing finding:

* fix it when the fix is small and unambiguous
* avoid changing behavior solely to satisfy an analyzer when the intended behavior is unclear
* do not blanket-ignore the entire project
* do not add broad suppression rules without justification
* do not create fake types solely to trick the analyzer

If a specific existing issue cannot reasonably be fixed during Phase 2.10, keep the suppression narrow and document the reason.

Prefer reducing the baseline rather than hiding it.

---

# 14. No Business Logic Refactoring

Do not use static-analysis findings as a reason to implement future domain architecture.

Do not introduce:

* domain services that do not yet have a business requirement
* repositories solely for static-analysis aesthetics
* interfaces with only one trivial implementation
* generic result wrappers
* generic exception hierarchies
* complex dependency-injection containers
* abstract factories without a real use case

The project explicitly discourages premature generic abstractions and overengineering.

---

# 15. Type-Safety Standard

Use explicit PHP types wherever appropriate.

Prefer:

```php
public function handle(Request $request): Response
```

over untyped signatures when the actual type is known.

Use:

* parameter types
* return types
* typed properties
* nullable types where required
* appropriate union/intersection types when actually needed
* precise collection/value documentation where native PHP types are insufficient

Do not add artificial typing that misrepresents runtime behavior.

---

# 16. Nullability

Respect the actual application contract.

Do not suppress nullability warnings simply because the analyzer complains.

Instead determine:

* whether a value can actually be null
* where it is guaranteed to exist
* whether the code should check it
* whether the type declaration is wrong

Do not weaken types to `mixed` as a shortcut.

---

# 17. `mixed` and Loose Typing

Avoid unnecessary:

```php
mixed
```

and broad array shapes where a more precise type is practical.

Do not use:

```php
array<string, mixed>
```

as the default solution for everything.

However, do not create complicated custom types simply to avoid a small amount of `mixed`.

Favor clear, maintainable typing.

---

# 18. Request Validation Boundary

Preserve the project rule that strict request validation uses:

```text
Request
→ FormRequest/schema validation
→ validated()
→ explicit allow-list
→ DTO/Command
→ application/domain logic
→ persistence
```

Never weaken this architecture to satisfy static analysis.

In particular, do not introduce patterns such as:

```php
$model->fill($request->all());
```

or generic request-to-model helpers.

The frozen API conventions explicitly require validated input → DTO/command → domain and prohibit unrestricted `$request->all()` mass assignment.

---

# 19. `additionalProperties: false`

Static-analysis and code-quality work must not undermine the frozen strict-request contract.

Future endpoint validation must remain capable of rejecting unknown fields.

Do not add helper methods whose purpose is effectively:

```text
accept arbitrary request fields
```

Do not convert strict payloads into generic untyped arrays merely because it is convenient.

---

# 20. API Contract Stability

Static-analysis changes must not alter the external Version 1 API contract.

Do not:

* rename routes
* rename response fields
* change error codes
* change HTTP status mappings
* change enum values
* change authentication behavior
* change authorization behavior
* change business-state transitions
* change financial semantics

The Version 1 API is frozen.

If a quality fix appears to require an external API change, do not silently make it.

Stop at that point and preserve the contract.

---

# 21. Laravel-Specific Dynamic Behavior

Laravel can contain framework-managed or dynamically resolved behavior that static analyzers cannot fully infer.

Handle such areas carefully.

Do not silence all framework-related diagnostics globally.

Use narrow, justified configuration or annotations only where necessary.

Prefer correct type declarations and explicit code over broad analyzer suppression.

---

# 22. Configuration Quality

Place tool configuration in appropriate project-managed configuration files.

Do not hard-code developer-specific paths.

Do not include:

* local usernames
* absolute machine paths
* secrets
* machine-specific environment assumptions

The configuration must work across developer environments.

---

# 23. Composer Integration

Add convenient Composer scripts where appropriate so developers can run the quality checks consistently.

A practical structure may be:

```text
composer format
composer format:check
composer analyse
```

Exact script names are flexible.

The important requirement is that the commands are:

* predictable
* documented
* repeatable
* consistent across developers

Do not create a large command abstraction.

---

# 24. Check vs Fix

Where the chosen formatter supports both operations, expose both concepts:

```text
format
format:check
```

The check command must fail when source formatting does not match the configured standard.

This provides the foundation for the later automated quality gate.

Do not implement the CI pipeline yet.

---

# 25. Static Analysis Exit Status

The static-analysis command must return a failing process status when configured analysis failures remain.

Do not configure it so that the command always succeeds.

Avoid:

```bash
static-analyzer || true
```

or equivalent "success regardless of failure" wrappers.

The future CI phase depends on meaningful exit codes.

---

# 26. Local Developer Workflow

Document the intended local workflow:

```text
format
→ format check
→ static analysis
→ later tests
```

The CI phase will eventually combine these into an automated quality gate.

Do not implement that automation yet.

The AGENTS quality philosophy explicitly anticipates a later automated sequence involving format, lint, static analysis, tests, and build.

---

# 27. Linting

The project requires formatting/linting/static analysis as part of code quality.

Where the chosen formatter provides the project's necessary style enforcement, do not install a redundant second linter merely to create another command.

If an actual linting gap exists that the formatter and static analyzer do not cover, introduce only the smallest additional linting tool that provides meaningful value.

Do not create overlapping tools with duplicate diagnostics.

---

# 28. Code Comments

Keep code comments to the absolute minimum.

Do not add comments merely to explain obvious code.

Avoid:

```php
// Check if the user exists
if ($user !== null) {
```

Prefer clear naming and simple control flow.

Comments are justified only when explaining a genuinely non-obvious:

* security constraint
* framework workaround
* static-analysis limitation
* compatibility requirement
* operational constraint

Do not add long explanatory comments to satisfy documentation expectations.

The source documentation should carry architectural explanation.

---

# 29. Documentation

Update developer documentation only as needed.

At minimum document:

* formatting command
* formatting check command
* static-analysis command
* where tool configuration lives
* any intentionally narrow suppression that future developers should understand

Do not create a large coding-style manual if the tool configuration already expresses the standard.

Do not create a second API or architecture specification.

---

# 30. Security

Static-analysis and formatting tools must not introduce security problems.

Never place into tool configuration:

* passwords
* tokens
* API keys
* payment credentials
* production secrets
* database credentials

Do not log tool environment variables.

Do not run analyzers with commands that dump secret-bearing environment configuration.

The project prohibits committing secrets, tokens, and production credentials to source control.

---

# 31. Dependency Management

When adding quality tools:

* use Composer-managed dependencies
* select versions compatible with the current PHP/Laravel constraints
* update `composer.lock` consistently
* do not manually copy tool binaries into the repository
* do not commit vendor code

Do not upgrade unrelated application dependencies simply because a quality tool has a newer ecosystem version.

Only change the dependency set necessary for this phase.

---

# 32. Generated Files

Do not commit generated cache files or machine-local artifacts created by quality tools unless the repository already intentionally tracks them.

Do not add:

```text
.idea/
.vscode/
.phpunit.result.cache
vendor/
machine-local caches
```

merely because a tool generated them.

Respect existing `.gitignore` conventions.

---

# 33. Quality Baseline for New Code

After Phase 2.10, new backend code should be expected to satisfy:

* formatter
* formatting check
* static analysis
* project naming conventions
* type-safety expectations
* existing architecture boundaries

Do not treat static analysis as an optional developer preference.

It becomes part of the backend development baseline.

---

# 34. Scope of Refactoring

Allow only small corrective refactors needed to make the foundation pass the quality baseline.

Examples:

* adding a missing return type
* fixing an obvious nullable assumption
* correcting an invalid parameter type
* removing dead imports
* replacing obviously unsafe loose typing
* fixing straightforward analyzer findings

Do not undertake broad application restructuring.

---

# 35. Testing Relationship

Phase 2.11 owns the dedicated test-framework setup.

However, existing tests must not be broken by the quality work.

Run whatever current test command is already available.

Do not:

* establish a new test framework
* redesign testing architecture
* add comprehensive domain tests
* add integration suites for future business modules

Those belong to later phases.

---

# 36. Health Endpoint Compatibility

Do not use Phase 2.10 as an opportunity to redesign `/health`.

Only make formatting/type corrections needed for the existing Phase 2.9 implementation.

Do not add:

* readiness framework
* dependency health checks
* monitoring
* metrics
* health storage

---

# 37. Error/Logging Compatibility

Do not redesign Phase 2.7 or Phase 2.8.

Static analysis must preserve:

* centralized exception handling
* frozen API error envelope
* request correlation
* safe logging
* sensitive-data protections

A static-analysis fix must not reintroduce raw exception responses or unsafe request logging.

---

# 38. Verification

At minimum, run:

```bash
composer format
composer format:check
composer analyse
```

using the actual command names chosen for the repository.

Also run:

```bash
php artisan test
```

if the current project test setup supports it.

If a formatter/check command is intentionally separated, execute both the write/fix and read-only verification paths.

Verify all configured quality commands return meaningful exit statuses.

---

# 39. Repository Inspection After Tooling

After running the tools, inspect the resulting change set.

Verify that the phase has not created:

* generated junk
* vendor changes
* IDE files
* environment files
* secrets
* unrelated dependency upgrades
* unrelated application rewrites

Only retain changes required for Phase 2.10.

---

# 40. Static-Analysis Review

Review the final analyzer configuration for:

* unnecessary suppressions
* broad ignored directories
* ignored error categories
* dead configuration
* duplicate configuration
* machine-specific paths
* PHP-version mismatch
* framework-specific false-positive handling

The final configuration should make genuine failures visible.

Do not optimize the configuration merely to make the command green.

---

# 41. Definition of Done

Phase 2.10 is complete only when:

1. The repository has one canonical formatting standard.
2. Formatting can be applied through a documented command.
3. Formatting can be checked without modifying files.
4. Static analysis can be run through a documented command.
5. Static analysis returns failure when configured errors remain.
6. The selected tools are Composer-managed and compatible with the project.
7. Existing application code has been brought to the agreed baseline where practical.
8. New quality tooling does not alter the frozen API contract.
9. Existing Phase 2.6–2.9 behavior remains intact.
10. Sensitive configuration and credentials are not committed.
11. Any analyzer suppressions are narrow and justified.
12. Existing tests, where currently available, remain functional.
13. Documentation identifies the local quality commands.
14. New code comments remain minimal.
15. The repository is ready for Phase 2.11 test-framework setup.

---

# 42. Review Checklist

Before completion:

* [ ] repository coding standard is defined
* [ ] formatter is installed or existing formatter standardized
* [ ] formatter configuration is version-controlled
* [ ] formatter command works
* [ ] formatting check works
* [ ] static analyzer is installed or existing analyzer standardized
* [ ] analyzer configuration is version-controlled
* [ ] analyzer command works
* [ ] analyzer has meaningful failure exit codes
* [ ] analysis excludes vendor/generated code appropriately
* [ ] analyzer suppressions are minimal
* [ ] PHP types are improved where appropriate
* [ ] no broad `mixed`/loose-typing workaround was introduced
* [ ] no `$request->all()` mass-assignment path was introduced
* [ ] `validated()` → DTO/command boundary remains intact
* [ ] `additionalProperties: false` assumptions remain intact
* [ ] API contract remains unchanged
* [ ] Phase 2.7 error handling remains unchanged
* [ ] Phase 2.8 logging remains unchanged
* [ ] Phase 2.9 health endpoint remains unchanged except for necessary quality corrections
* [ ] no test-framework redesign was introduced
* [ ] no CI pipeline was introduced
* [ ] no external monitoring tooling was introduced
* [ ] no secrets were added
* [ ] no unnecessary dependencies were upgraded
* [ ] no generated artifacts were added
* [ ] code comments are minimal
* [ ] relevant tests pass
* [ ] quality commands pass

---

# 43. Explicitly Out of Scope

Do **not** implement during Phase 2.10:

* test-framework setup
* test-framework replacement
* CI/CD
* GitHub Actions/GitLab CI/other CI pipelines
* deployment automation
* comprehensive test suites
* integration-test architecture
* end-to-end testing
* static-analysis of frontend code
* Next.js linting
* Flutter analysis
* frontend formatting
* application monitoring
* error tracking
* Sentry
* Datadog
* New Relic
* OpenTelemetry
* database migrations
* domain models
* domain services
* repositories introduced solely for abstraction
* authentication
* authorization
* product/catalog features
* cart features
* checkout features
* order features
* payment integration
* webhook implementation
* audit-log infrastructure
* queue infrastructure
* notification implementation
* API contract changes
* new API endpoints
* `/api/v1` redesign
* health/readiness redesign

---

# 44. STOP Condition

Stop immediately when the Phase 2.10 definition of done is satisfied.

Do not continue into Phase 2.11.

Do not establish the test framework beyond using whatever test capability already exists.

Do not create CI.

Do not upgrade unrelated dependencies.

Do not refactor the application architecture merely because static analysis suggests a different design.

Do not change the frozen Version 1 API contract.

Do not add broad analyzer suppressions simply to achieve a green build.

Do not commit, stage, or push changes. Leave source-control operations to the project owner, consistent with the repository instructions.
