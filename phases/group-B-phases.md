# Group B phases instructions

# Phase 2.1 — Laravel Backend Foundation Baseline & Project Setup Verification

## 1. Purpose

Begin **Group B — Laravel Backend Foundation** using the Laravel application that already exists at:

```text
backend/laravel
```

The project was created from the project root with:

```bash
composer create-project --prefer-dist laravel/laravel backend/laravel
```

The Laravel application is therefore **already scaffolded**.

This phase must **not recreate or replace the Laravel project**.

The purpose of Phase 2.1 is to establish a clean, verified, project-specific Laravel foundation that is ready for the domain implementation phases that follow.

The phase must:

* verify the existing Laravel installation
* verify PHP/Composer/Laravel compatibility
* establish the repository's backend conventions
* configure local environment safely
* establish the API application baseline
* verify database connectivity configuration
* verify the development server
* establish health/status verification
* remove only scaffold behavior that conflicts with the API project
* preserve the frozen Group A contract
* prepare the Laravel application for migrations/models and API implementation in later phases

This is the **first implementation phase** after Group A.

---

# 2. Critical Starting Rule

The Laravel project already exists.

Do **not** run:

```bash
composer create-project
laravel new
composer install
```

to recreate the project unless the existing installation is actually broken and the repair is explicitly required.

Do not delete:

```text
backend/laravel
```

Do not rebuild the Laravel application from scratch.

The starting assumption is:

```text
backend/laravel
        ↓
existing Laravel installation
        ↓
verify and configure
        ↓
establish project baseline
```

---

# 3. Dependencies

Group A must be complete before beginning this phase.

Treat the following as frozen inputs:

```text
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml

docs/domain/business-rules.md
docs/decisions.md
```

Group A is:

```text
Phase 1.16 through Phase 1.35
```

The implementation agent must also inspect the actual current repository state before modifying anything.

---

# 4. Mandatory First Action — Read Project Instructions

Before executing project changes, read:

```text
AGENTS.md
```

from the project root.

Also inspect any additional `AGENTS.md` files located inside relevant directories, especially:

```text
backend/
backend/laravel/
```

if they exist.

The most specific applicable instructions take precedence where the repository's agent-instruction hierarchy defines such behavior.

Do not assume that the roadmap reproduced elsewhere in conversation overrides the actual repository `AGENTS.md`.

The implementation agent must obey:

* project conventions
* prohibited files/directories
* testing requirements
* formatting requirements
* commit/change conventions
* architecture constraints
* security rules
* scope restrictions

from the repository instructions.

---

# 5. Mandatory Group A Contract Review

Before modifying Laravel files, read enough of the frozen API contract to understand:

* API version
* authentication model
* authorization model
* roles
* endpoint structure
* error contract
* resource boundaries
* money representation
* date/time representation
* enum policy
* order state model
* delivery-fee workflow

Especially verify:

```text
/api/v1
```

and the three roles:

```text
CUSTOMER
STAFF
ADMIN
```

The agent must not begin implementation by redesigning any Group A behavior.

---

# 6. Project Structure

The backend application must remain at:

```text
backend/laravel
```

Do not move it to:

```text
laravel/
api/
server/
backend/
```

or another location.

The expected relationship is:

```text
project-root/
├── backend/
│   └── laravel/
├── docs/
├── AGENTS.md
└── ...
```

Preserve this structure.

---

# 7. Laravel Installation Verification

Navigate to:

```bash
cd backend/laravel
```

Verify:

```bash
php artisan --version
php -v
composer --version
```

Also verify:

```bash
php artisan about
```

where supported by the installed Laravel version.

Record the actual installed:

* Laravel version
* PHP version
* Composer version

Do not hard-code guessed version numbers into documentation.

---

# 8. Dependency Integrity

Run the appropriate dependency verification for the existing project.

At minimum:

```bash
composer validate
```

and:

```bash
composer install
```

only when needed to ensure the existing dependency state is complete.

Do not unnecessarily rewrite the lock file.

If `composer.lock` already exists, preserve it unless dependency correction is genuinely required.

Inspect:

```text
composer.json
composer.lock
```

for unexpected or unrelated dependencies.

Do not install authentication, payment, API, permission, or infrastructure packages merely because they might be useful later.

Those decisions belong to their dedicated implementation phases.

---

# 9. PHP Extension Verification

Verify that the Laravel project has the PHP extensions required by its current dependency set.

Use the existing Laravel/Composer requirements as the source of truth.

Do not invent a large list of mandatory extensions.

If a required extension is missing, report it clearly and document the local prerequisite rather than modifying application code to work around it.

---

# 10. Environment Configuration

Inspect:

```text
.env.example
.env
```

Do not commit `.env`.

Ensure the local environment can be created from the project template.

The local `.env` must contain appropriate application configuration for development.

Do not place:

* production secrets
* real credentials
* payment credentials
* real customer data
* real API keys

into repository files.

---

# 11. Application Key

Verify the Laravel application key behavior.

A local development environment must have a valid `APP_KEY`.

If the existing local environment is missing one, generate it using Laravel's standard mechanism.

Do not commit the resulting local `.env`.

Do not replace a deliberately configured application key without a legitimate reason.

---

# 12. Application Environment

For local development, establish the appropriate development environment.

Use the repository's existing conventions where defined.

The implementation agent should verify:

```text
APP_ENV
APP_DEBUG
APP_URL
```

and ensure they are appropriate for local development.

Do not configure production settings in this phase.

---

# 13. API Application Baseline

The Laravel application is ultimately an API backend for:

```text
Next.js website
Flutter application
```

The phase should therefore verify that Laravel can serve API requests cleanly.

Do not build domain endpoints yet.

The API baseline should establish:

```text
/api/v1
```

as the future API namespace without prematurely implementing all routes.

---

# 14. Route Baseline

Inspect the current Laravel route files.

Determine whether the installed Laravel version uses:

```text
routes/api.php
```

and/or the current Laravel routing/bootstrap mechanism.

Do not assume a particular Laravel version's file layout.

Use the structure that exists in the installed project.

Do not migrate the entire routing system simply to match an older Laravel tutorial.

---

# 15. API Versioning Baseline

Establish the API version boundary:

```text
/api/v1
```

Use one clean mechanism for this.

Do not scatter:

```text
/v1
/api/v1
/api
```

across unrelated route definitions.

The actual endpoint implementation begins later.

This phase only establishes the routing/application foundation needed to support the frozen contract.

---

# 16. Health Endpoint

Create a minimal backend health/status endpoint for local infrastructure verification.

Preferred conceptual endpoint:

```text
GET /api/v1/health
```

Only create this if it does not conflict with the frozen Group A endpoint inventory.

If `/health` was intentionally excluded from the frozen public API contract, keep the health mechanism internal/development-only instead.

The purpose is:

```text
HTTP request
        ↓
Laravel application
        ↓
routing
        ↓
response
```

not business functionality.

The health response must not expose:

* PHP version
* Laravel version
* database credentials
* environment variables
* filesystem paths
* internal infrastructure
* debug stack traces

A minimal response is preferable.

---

# 17. Health Endpoint Contract Rule

The health endpoint must not be mistaken for a domain API resource.

It should not expose:

```text
users
orders
products
inventory
payments
```

It exists only to verify application availability.

If added, document it as an infrastructure endpoint and ensure it does not contradict the frozen API contract.

---

# 18. Database Configuration Baseline

Inspect the application's database configuration.

Do not create migrations yet.

Verify that Laravel can be configured for the project's MySQL backend.

The database driver must be consistent with the selected architecture:

```text
Laravel
    ↓
MySQL
```

Do not introduce PostgreSQL, SQLite, MongoDB, or another database architecture unless the project decision explicitly changes.

---

# 19. Local Database Configuration

Configure the local development `.env` with the appropriate MySQL connection values.

Do not commit real credentials.

Use the project's actual local database conventions if already documented.

Do not create production databases.

Do not modify unrelated host-level database configuration.

---

# 20. Database Connectivity Verification

After the local database is available, verify the Laravel application can connect to it.

Use a safe Laravel/database verification approach.

Do not create domain tables yet.

The goal is only to prove:

```text
Laravel
    ↓
database configuration
    ↓
MySQL connection
```

works.

If the database is unavailable, report the specific configuration/environment problem.

Do not fabricate a successful result.

---

# 21. Migration Baseline

Do not begin domain migrations in Phase 2.1.

Do not create tables for:

```text
users
products
orders
cart
inventory
requests
enquiries
notifications
payments
```

unless they are already created by the untouched Laravel skeleton and the project explicitly requires them.

Domain migrations belong to subsequent foundation/domain phases.

---

# 22. Authentication Package Decision

Do not install an authentication package automatically.

First inspect the current Laravel project and the approved Authentication Contract.

The authentication implementation must later match:

```text
Phase 1.17
```

If a package such as Sanctum is selected in a future authentication phase, that decision must be deliberate.

Phase 2.1 should not silently choose the authentication architecture.

---

# 23. Authorization Package Decision

Do not install Spatie Permission or another authorization package automatically.

The project already has a defined conceptual authorization model.

The later implementation phase must first establish whether Laravel native policies/gates plus the application's role model are sufficient.

Do not introduce a permission package merely because Staff/Admin authorization exists.

---

# 24. API Error Baseline

Do not redesign the error contract.

The frozen contract from Phase 1.16 requires the standard shape:

```json
{
  "errors": [
    {
      "code": "...",
      "message": "..."
    }
  ]
}
```

Phase 2.1 may establish the infrastructure required for future API error handling, but the complete error implementation belongs to later API foundation phases.

Do not allow Laravel's default HTML exception pages to become the intended production API contract.

---

# 25. JSON API Baseline

Verify that future API routes can return JSON consistently.

Do not build all error handling in this phase.

Do ensure that basic API behavior is not accidentally coupled to Blade/web responses.

The backend is an API service even though Laravel may retain its default web scaffolding.

---

# 26. Default Scaffold Review

Inspect Laravel's default scaffold.

Identify what is:

```text
required
useful
irrelevant
potentially conflicting
```

Do not delete default files indiscriminately.

Do not remove Laravel functionality merely because it is not currently used.

Only remove or alter scaffold behavior where it conflicts with:

* project architecture
* security
* API-only backend behavior
* repository instructions

---

# 27. Welcome Route Review

If Laravel's default welcome/web route exists, determine whether it conflicts with the project's backend architecture.

It is acceptable for:

```text
http://127.0.0.1:8000
```

to respond with a development landing page while the API operates under:

```text
/api/v1
```

unless project instructions require otherwise.

Do not create frontend pages in Laravel.

The production website remains Next.js.

---

# 28. Development Server Verification

From:

```bash
cd backend/laravel
```

verify:

```bash
php artisan serve
```

starts successfully.

The expected local development URL is:

```text
http://127.0.0.1:8000
```

Verify at least:

```text
GET http://127.0.0.1:8000
```

and, if the health endpoint is introduced:

```text
GET http://127.0.0.1:8000/api/v1/health
```

Use the actual application's response rather than assuming success.

---

# 29. Route Inspection

Use Laravel's route inspection tooling appropriate to the installed Laravel version.

Verify that:

* the intended API route is registered
* route paths are correct
* HTTP methods are correct
* no duplicate health route exists
* no unexpected catch-all route intercepts API requests

Do not add business routes yet.

---

# 30. Configuration Cache Safety

Verify that local configuration works both:

```text
without cached configuration
```

and:

```text
with normal Laravel configuration caching where applicable
```

Do not leave a development environment dependent on stale cached configuration.

Do not commit framework cache artifacts.

---

# 31. Storage and Filesystem Baseline

Verify the Laravel storage structure is usable.

Do not yet implement:

* customer attachment storage
* request attachments
* enquiry attachments
* product media
* private file downloads

Those belong to later resource implementation.

Only establish that the standard Laravel application can initialize its expected storage directories.

---

# 32. Logging Baseline

Verify Laravel can write development logs.

Do not redesign logging architecture yet.

Do not log:

* passwords
* access tokens
* secrets
* payment credentials
* full sensitive customer records

This phase establishes application viability, not production observability architecture.

---

# 33. Queue Baseline

Do not implement business queues or jobs yet.

Inspect the queue configuration and confirm that it does not prevent Laravel from booting.

Do not introduce Redis, RabbitMQ, SQS, or another queue system merely because future notifications may require queues.

Queue architecture belongs to a later phase.

---

# 34. Cache Baseline

Likewise, do not introduce Redis or another cache system merely because later API operations may use caching.

Verify the current Laravel configuration can boot normally.

Caching strategy belongs to a later performance/infrastructure phase.

---

# 35. CORS Baseline

Do not treat CORS as authorization.

Review the installed Laravel configuration and project requirements.

Do not open CORS broadly to:

```text
*
```

merely to make frontend development convenient.

If CORS configuration is needed now, use the narrowest development configuration consistent with the approved architecture.

The production configuration must be handled deliberately later.

---

# 36. Backend URL Convention

Record that local Laravel development uses:

```text
http://127.0.0.1:8000
```

for the development server.

Do not hard-code this URL into API business logic.

Next.js and Flutter client configuration should later receive the API base URL through environment/configuration.

---

# 37. Environment Safety Audit

Check repository status before and after the phase.

Ensure the implementation agent does not accidentally stage/commit:

```text
.env
storage/logs/*
bootstrap/cache/*
local database files
IDE files
OS files
credentials
tokens
```

Use the existing `.gitignore`.

Only modify `.gitignore` when a genuine project-specific omission is discovered.

---

# 38. Coding Standards Baseline

Inspect the repository's existing PHP code style and tooling.

Use project-defined tools where present.

If none are configured yet, do not install a large collection of formatting/linting tools merely for this phase.

The later backend foundation phases can establish:

* Pint
* PHPStan
* testing tools
* architectural checks

as deliberate project dependencies.

---

# 39. Testing Baseline

Run the existing Laravel test suite:

```bash
php artisan test
```

or the repository's approved equivalent.

The goal is to establish a clean baseline.

If the untouched Laravel skeleton has no meaningful tests yet, document that fact rather than fabricating coverage.

At minimum verify the application boots successfully under the test environment.

---

# 40. Static Validation

Run available validation commands appropriate to the repository.

At minimum consider:

```text
composer validate
php artisan test
php artisan route:list
php artisan about
```

Use commands that actually exist in the installed Laravel version.

Do not claim a validation passed if it was not executed.

---

# 41. Security Baseline

Perform a lightweight security check for the newly established foundation.

Verify:

* `.env` is not tracked
* debug mode is appropriate for local development only
* production secrets are absent
* health response does not disclose internals
* no anonymous privileged route was created
* no wildcard CORS authorization assumption was introduced
* no authentication bypass was added
* no role field is accepted by any new route

---

# 42. API Base URL Test

Verify the Laravel server responds at:

```text
http://127.0.0.1:8000
```

and the Version 1 API namespace responds according to the baseline established in this phase.

Do not create placeholder business endpoints simply to make all future URLs respond.

A missing future endpoint is not a foundation failure.

---

# 43. Documentation Changes

Update the smallest necessary existing documentation.

Potential locations:

```text
AGENTS.md
docs/api/api-conventions.md
docs/decisions.md
```

Only document decisions actually made during this phase.

Examples:

```text
Laravel application location
backend/laravel

Local development command
cd backend/laravel && php artisan serve

Local development URL
http://127.0.0.1:8000

API version prefix
/api/v1
```

Do not create a new `backend-setup.md` merely for these facts unless the repository documentation structure explicitly calls for it.

---

# 44. Architectural Decision Recording

Record any new architectural decision made during Phase 2.1 in:

```text
docs/decisions.md
```

Examples:

* selected API bootstrap mechanism
* health endpoint inclusion/exclusion
* Laravel authentication package deferred
* authorization package deferred
* development database conventions
* API CORS baseline

Do not record routine implementation details as architectural decisions.

---

# 45. No Contract Changes

Phase 2.1 must not modify the frozen Group A contract unless a genuine implementation-blocking contradiction is discovered.

If a contradiction is found:

```text
implementation discovery
        ↓
identify contract issue
        ↓
STOP affected implementation
        ↓
use post-freeze contract-change process
```

Do not silently edit:

```text
docs/api/openapi.yaml
```

to make Laravel implementation easier.

The API contract remains frozen.

---

# 46. No Domain Implementation

Do not create the full application domain in Phase 2.1.

Do not implement:

```text
User model
Product model
Category model
Variant model
Cart model
Order model
OrderItem model
Inventory model
Request model
Enquiry model
Notification model
Payment model
AuditLog model
```

unless a later phase explicitly calls for one of these.

This phase establishes the application foundation only.

---

# 47. No API Endpoint Implementation Beyond Foundation

Do not implement:

```text
/products
/me/cart
/checkout
/me/orders
/staff/orders
/staff/inventory
/requests
/enquiries
/notifications
/admin/staff
```

in Phase 2.1.

Their contracts are already frozen.

Implementation begins in later dependency-ordered phases.

---

# 48. Required Repository State Review

At completion, inspect:

```bash
git status
```

Confirm that all changed files are intentional.

Review the diff before declaring the phase complete.

Do not modify unrelated frontend or mobile files during this phase.

Do not reformat the repository globally.

---

# 49. Definition of Done

Phase 2.1 is complete only when:

* [ ] The current repository `AGENTS.md` has been read.
* [ ] Any applicable nested agent instructions have been read.
* [ ] Group A frozen contract documents have been reviewed.
* [ ] Existing Laravel project at `backend/laravel` has been preserved.
* [ ] Laravel version has been verified.
* [ ] PHP version has been verified.
* [ ] Composer version has been verified.
* [ ] `composer.json` and `composer.lock` have been inspected.
* [ ] Dependency integrity has been checked.
* [ ] Local `.env` configuration is valid.
* [ ] `.env` remains untracked.
* [ ] `APP_KEY` is valid for local development.
* [ ] Local application configuration is appropriate.
* [ ] MySQL configuration is established.
* [ ] Database connectivity has been verified where a local database is available.
* [ ] No domain migrations have been introduced.
* [ ] API version boundary `/api/v1` is established consistently.
* [ ] The Laravel routing mechanism matches the installed Laravel version.
* [ ] No duplicate or conflicting API version mechanism exists.
* [ ] A minimal health endpoint has been added only if compatible with the frozen contract.
* [ ] Health output does not leak internal information.
* [ ] Default scaffold behavior has been reviewed.
* [ ] No unnecessary scaffold deletion has occurred.
* [ ] Laravel development server starts successfully.
* [ ] `http://127.0.0.1:8000` has been verified.
* [ ] API baseline route behavior has been verified.
* [ ] Route registration has been inspected.
* [ ] Configuration/cache behavior has been checked.
* [ ] Storage baseline is functional.
* [ ] Development logging works without sensitive-data leakage.
* [ ] No premature queue architecture has been introduced.
* [ ] No premature cache architecture has been introduced.
* [ ] CORS has not been treated as authorization.
* [ ] No authentication package has been installed without an approved decision.
* [ ] No authorization package has been installed without an approved decision.
* [ ] Existing Laravel tests pass, or the baseline failure state is documented.
* [ ] Composer validation passes.
* [ ] Repository changes have been reviewed.
* [ ] No secrets or generated local artifacts were accidentally introduced.
* [ ] Relevant documentation has been updated.
* [ ] No Group A contract has been silently changed.
* [ ] No business-domain implementation has been started.
* [ ] No frontend or Flutter implementation has been started.

---

# 50. Expected Initial Backend State

At the end of this phase, the backend should conceptually be:

```text
backend/laravel
        │
        ├── Laravel application boots
        ├── local environment works
        ├── MySQL configuration works
        ├── API namespace is established
        ├── health/baseline verification works
        └── ready for domain implementation
```

It should **not** yet contain the completed ecommerce domain.

---

# 51. STOP Condition

**STOP after the existing Laravel project is verified, configured, documented, and ready for the next dependency-ordered implementation phase.**

Do not continue into:

* database schema design
* user migrations
* product migrations
* authentication implementation
* authorization implementation
* Eloquent domain models
* API Resources
* controllers
* business services
* repositories
* order workflow implementation
* payment implementation
* notification implementation
* Next.js integration
* Flutter integration

Do not use this phase to "get ahead" of the roadmap.

Phase 2.1 establishes the stable Laravel foundation only.

**Next phase: Phase 2.2 — Laravel Project Conventions, Code Quality & Backend Development Standards.**

**Group B is now officially started.**

