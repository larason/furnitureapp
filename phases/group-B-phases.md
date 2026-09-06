# Group B phases instructions

# Phase 2.9 — Health/Status Endpoint

## Objective

Implement a minimal, reliable Laravel health/status endpoint that can be used to determine whether the application process is running and able to respond to HTTP requests.

The endpoint must be:

* lightweight
* deterministic
* safe to expose
* independent of business/domain functionality
* suitable for local and later operational checks
* compatible with the existing routing, exception, and logging foundations

Do not turn this phase into a full production monitoring, readiness, dependency-discovery, or observability implementation.

---

# 1. Phase Context

Phases 2.1–2.5 are already complete.

Phase 2.6 established API routing.

Phase 2.7 established centralized API exception/error handling.

Phase 2.8 established the logging foundation.

Now implement:

**Phase 2.9 — Health/status endpoint**

The roadmap places this immediately before coding standards/static analysis, test-framework setup, and CI.

Do not redo previous phases.

Do not continue into Phase 2.10.

---

# 2. Important Contract Boundary

The provided frozen API documentation does **not** define a Version 1 health/status endpoint or its response schema.

Therefore:

* do not invent a new `/api/v1` contract without explicit evidence
* do not add an endpoint to the frozen Version 1 endpoint inventory merely because health functionality is needed
* do not change `docs/api/api-contract.md`, `docs/api/api-resources.md`, or `docs/api/api-conventions.md` to manufacture a new V1 business/API contract
* do not reuse an existing V1 resource envelope for an operational endpoint unless the repository already establishes that behavior

For this phase, treat health/status as an **operational application endpoint**, not a customer/business API resource.

### Implementation decision for this phase

Unless the existing repository already contains a health/status endpoint, use:

```text
GET /health
```

as the operational endpoint.

Keep it outside `/api/v1`.

This is an implementation-level operational endpoint, not a new Version 1 business API contract.

If the existing repository already has an established health/status route, preserve that route instead of creating `/health`.

---

# 3. Scope

The endpoint must answer one basic question:

> Is the Laravel application currently alive enough to respond to a health request?

The initial implementation should verify application-level liveness only.

A healthy response should not require:

* customer authentication
* Staff authentication
* Admin authentication
* database business records
* product data
* inventory data
* carts
* orders
* payments
* notifications
* queues
* external payment providers
* third-party APIs

Keep the endpoint cheap enough to be called frequently.

---

# 4. Liveness vs Readiness

Do not conflate health concepts.

For this phase, implement **liveness**.

Liveness means:

```text
The Laravel process can receive and successfully produce a health response.
```

Do not implement full readiness semantics such as:

```text
database ready
payment provider ready
queue workers ready
email provider ready
file storage ready
external dependencies ready
```

unless the repository already has an explicit requirement for them.

A later production-readiness phase can add broader dependency checks.

---

# 5. Endpoint Characteristics

The health endpoint should be:

```text
GET /health
```

with:

* no request body
* no authentication requirement
* no customer state
* no business authorization
* no mutation
* no database writes
* no external side effects

It should be safe to call from:

* local development
* deployment verification
* process supervision
* simple uptime checks
* future infrastructure health probes

---

# 6. Success Response

Because the uploaded project sources do not define a frozen health-response schema, use the smallest stable implementation response necessary for operational use.

Recommended response:

```json
{
  "status": "ok"
}
```

Return:

```text
HTTP 200
```

Do not add speculative fields such as:

```text
version
environment
hostname
server_ip
database_status
queue_status
payment_status
memory_usage
uptime
```

unless the repository or a later approved contract explicitly requires them.

Keep the response deliberately small.

---

# 7. Failure Response

For the minimal liveness endpoint, failure means the application cannot successfully execute its health operation.

Do not manufacture dependency-specific failure states that are not implemented.

If an actual exception occurs while handling the health request:

* allow the centralized Phase 2.7 exception handling to protect the client
* do not expose stack traces
* do not expose Laravel exception class names
* do not expose filesystem paths
* do not expose database credentials
* do not expose server internals

The project's security baseline explicitly requires raw framework, database, and infrastructure exceptions to remain out of API responses.

---

# 8. Relationship to Phase 2.7 Error Handling

Do not create a second exception-handling mechanism for `/health`.

The health endpoint must use the existing centralized exception/error infrastructure where applicable.

Do not add endpoint-specific JSON error construction such as:

```php
return response()->json([
    'error' => 'health_failed',
]);
```

Do not create a second error envelope.

The Phase 2.7 error handling remains authoritative for failures where exception translation is required.

---

# 9. Relationship to Phase 2.8 Logging

Use the existing logging foundation.

Do not create a health-specific logging subsystem.

Normal successful health checks generally should not generate high-volume application logs.

Avoid logging every successful request to `/health` at ERROR/WARNING levels.

If an unexpected health-check exception occurs:

* it should be diagnosable through the existing exception/logging infrastructure
* the request ID should remain available through the existing correlation mechanism
* sensitive data must not be logged

The project requires important failures to be diagnosable through application/API logging.

---

# 10. Request Correlation

If the existing HTTP foundation establishes a request ID, preserve it for `/health`.

Do not create a separate health-specific request ID mechanism.

A health request should therefore participate in the same general correlation model used elsewhere.

Do not include request IDs in the success body unless already required by the operational contract.

The existing API request-ID convention is intended to correlate client-visible errors with server logs.

---

# 11. Authentication

Do not require authentication for the basic liveness endpoint.

Reason:

A liveness check must be usable by an operational process before customer authentication, staff authorization, or application business functionality is necessarily available.

Do not:

* invoke customer authentication
* invoke Staff/Admin authorization
* query user roles
* create sessions
* access customer data
* depend on bearer tokens

---

# 12. Database Dependency

Do not make the minimal liveness endpoint depend on a successful database query unless the existing repository explicitly defines health as database-dependent.

The endpoint should be capable of answering:

```text
Is the application process alive?
```

independently of:

```text
Is every dependency healthy?
```

Do not add:

```php
DB::select(...)
```

merely to prove that the database exists.

The database connection was already established in Phase 2.4.

A later readiness/production-health design may intentionally include dependency checks.

---

# 13. External Dependency Checks

Do not call:

* payment providers
* email providers
* SMS providers
* storage services
* third-party APIs
* webhooks
* queues
* notification providers

from the basic health endpoint.

A health endpoint must not generate external side effects or create unnecessary load.

Payment and provider-specific behavior remain deferred to later work. The project architecture explicitly separates external/provider failures from basic validation and infrastructure concerns.

---

# 14. Security and Information Disclosure

Do not return infrastructure information.

The response must not reveal:

* database hostname
* database name
* database credentials
* Redis details
* filesystem paths
* server hostname
* server IP
* PHP version
* Laravel version
* package versions
* environment variable values
* secret configuration
* internal service URLs
* queue configuration
* payment configuration
* deployment topology

Do not expose "debug health" through the public endpoint.

This follows the project's security requirement to prevent infrastructure and implementation details from leaking through responses.

---

# 15. Environment Information

Do not return:

```json
{
  "environment": "production"
}
```

or similar environment metadata unless explicitly required.

An externally accessible health endpoint should reveal as little as necessary.

Environment-specific diagnostics belong in server-side operational tooling and logs.

---

# 16. Cache Behavior

Do not introduce caching that could make a liveness result stale.

The health endpoint must return an explicit no-store response contract so that a
browser or intermediary cannot serve a stale cached 200 after the process has
died:

```http
Cache-Control: no-store
```

The health endpoint should represent the application's current ability to respond.
Every successful health response must include `Cache-Control: no-store` (and must
not be cacheable by shared or private caches). Equivalent deployment-level
guarantees (e.g., CDN rule `Cache-Control: no-store` for the health path) are
acceptable only if verified. Tests must assert the header.

Do not place it behind application-level response caching.

Do not introduce CDN caching rules as part of this phase.

---

# 17. HTTP Semantics

The endpoint must:

* accept only `GET`
* reject inappropriate mutation methods
* return a normal HTTP success code on success
* not modify state

Do not add:

```text
POST /health
PATCH /health
DELETE /health
```

Do not create multiple aliases such as:

```text
/status
/api/status
/api/v1/status
/system/health
```

Use one canonical operational endpoint.

---

# 18. Routing

Register the route in the appropriate existing Laravel routing location.

Do not place the operational health route inside the customer/business API group solely for convenience.

Do not duplicate the route in several route files.

Do not recreate removed `/staff/...` aliases.

Do not alter the existing `/api/v1` route topology except where absolutely required to preserve correct route separation.

---

# 19. Controller / Handler Design

Keep the implementation extremely small.

A health handler should do little more than return the operational response.

Avoid:

* service layers with no real responsibility
* repositories
* database queries
* model loading
* business services
* DTOs for a one-field liveness response
* generic "system status engine"
* health-check registries
* plugin architectures

Do not overengineer the endpoint.

---

# 20. Response Stability

Even though the health response is not a frozen V1 business resource, treat the response as an operational interface.

Once implemented:

* keep the response stable
* do not casually rename `status`
* do not add environment-sensitive behavior
* do not change HTTP semantics without reason

Operational tooling may depend on it.

---

# 21. Content Type

Return JSON consistently if the existing Laravel application uses JSON for the operational endpoint.

Ensure the response has an appropriate JSON content type.

Do not return HTML.

Do not return framework debug pages.

---

# 22. Testing

Add focused tests for the health endpoint.

At minimum verify:

### Success

```text
GET /health
→ HTTP 200
```

and the body contains the expected minimal health representation.

### Method restriction

Verify inappropriate methods are rejected.

### No authentication dependency

Verify the endpoint works without a customer, Staff, or Admin session/token.

### No business dependency

Verify the endpoint does not require:

* products
* orders
* carts
* inventory
* payment records

### Exception safety

Trigger a controlled failure where practical and verify:

* internal details are not exposed
* stack traces are not returned
* the centralized exception handling remains effective

### Logging/correlation compatibility

Verify that an unexpected server-side failure remains diagnosable through the existing Phase 2.8 logging path and request correlation mechanism.

Do not build a second testing framework.

---

# 23. Minimal Code Comments

Keep comments in new code to the absolute minimum.

Do not write:

```php
// Health check endpoint
```

above an obviously named health handler.

Do not add explanatory essays to the route or controller.

A comment is justified only where a non-obvious operational/security constraint cannot be made clear through naming and structure.

Prefer self-explanatory code.

---

# 24. Documentation

Because the frozen API documents do not define a health endpoint, do not modify the V1 API contract documentation merely to record `/health`.

Document the operational endpoint only where the existing project documentation structure already has an appropriate location.

A small note is sufficient:

```text
GET /health
Operational liveness endpoint.
```

Do not create a large health/monitoring specification.

Do not document unsupported future dependencies.

---

# 25. No Monitoring Platform

Do not add:

* Prometheus
* Grafana
* Sentry
* Datadog
* New Relic
* OpenTelemetry
* cloud monitoring agents
* distributed tracing
* uptime SaaS integrations

The AGENTS roadmap places broader production logging/monitoring and error tracking later in the production-readiness phase.

---

# 26. No Readiness Framework

Do not create a general abstraction such as:

```text
HealthCheckInterface
ReadinessCheck
DependencyCheck
HealthRegistry
SystemStatusManager
```

unless the repository already contains such infrastructure.

For this phase, a minimal liveness endpoint is preferable.

---

# 27. No Database Health Table

Do not create:

* health tables
* heartbeat tables
* system-status tables
* monitoring tables
* uptime tables

The endpoint does not need database storage.

---

# 28. No Business Status Exposure

Do not return business state through the health endpoint.

For example, do not expose:

```text
orders_processing
products_available
inventory_status
payments_enabled
delivery_enabled
customers_registered
```

The health endpoint is about application operation, not business metrics.

---

# 29. No Deployment Logic

Do not add:

* automatic restart behavior
* deployment hooks
* migrations
* cache clearing
* queue restarting
* self-healing behavior
* environment mutation

The endpoint must observe the application, not modify it.

---

# 30. No Secret Validation

Do not use the health endpoint to test whether secrets are present by returning secret configuration state.

For example, do not return:

```json
{
  "payment_secret_configured": true
}
```

or:

```json
{
  "database_password_present": true
}
```

Configuration diagnostics belong in controlled server-side tooling.

---

# 31. Performance Requirements

The health endpoint must be extremely lightweight.

Avoid:

* database queries
* external network calls
* file scans
* model hydration
* large serialization
* logging large payloads
* expensive configuration processing

The normal successful execution path should be simple and fast.

---

# 32. Review Against Previous Phases

Before completion verify compatibility with:

### Phase 2.6

* route registration remains clean
* no duplicate route aliases
* `/api/v1` remains unchanged

### Phase 2.7

* no second exception-handler architecture
* no raw framework errors exposed
* existing error handling remains intact

### Phase 2.8

* existing logger is reused
* request correlation remains intact
* no sensitive health diagnostics are logged

---

# 33. Verification Commands

Run the currently available project verification commands.

At minimum:

```bash
php artisan test
php artisan route:list
```

Verify that the health route appears exactly once.

Verify that the V1 route collection has not been unintentionally changed.

Perform a direct request against:

```text
GET /health
```

and verify the expected HTTP status and response.

Do not wait for:

* Phase 2.10 static analysis
* Phase 2.11 test-framework expansion
* Phase 2.12 CI

---

# 34. Review Checklist

Before marking Phase 2.9 complete:

* [ ] one canonical health endpoint exists
* [ ] default route is `GET /health` unless an existing repository health route already exists
* [ ] endpoint remains outside `/api/v1`
* [ ] endpoint requires no authentication
* [ ] endpoint performs no mutations
* [ ] endpoint does not depend on business data
* [ ] endpoint does not call payment/external providers
* [ ] endpoint does not require queues
* [ ] endpoint does not expose infrastructure details
* [ ] endpoint does not expose environment configuration
* [ ] endpoint does not expose secrets
* [ ] endpoint does not expose framework debug information
* [ ] response is minimal and stable
* [ ] inappropriate methods are rejected
* [ ] Phase 2.7 exception handling remains authoritative
* [ ] Phase 2.8 logging remains authoritative
* [ ] request correlation remains compatible
* [ ] successful health requests do not generate unnecessary error-level log noise
* [ ] focused health tests pass
* [ ] existing tests pass
* [ ] route list shows no duplicate health aliases
* [ ] comments in new code are minimal

---

# 35. Definition of Done

Phase 2.9 is complete only when:

1. Laravel exposes one canonical operational liveness endpoint.
2. The endpoint responds successfully when the application is alive.
3. It does not require authentication or business-domain state.
4. It does not introduce a new `/api/v1` business contract.
5. Its response contains only minimal health information.
6. It does not disclose infrastructure, secrets, or framework internals.
7. It uses the existing exception-handling foundation.
8. It remains compatible with the existing logging/correlation foundation.
9. Focused automated tests pass.
10. Existing backend tests remain green.
11. No monitoring platform, readiness framework, or dependency-health subsystem has been introduced.
12. New code contains only the minimum necessary comments.

---

# 36. Explicitly Out of Scope

Do **not** implement during Phase 2.9:

* `/api/v1/health`
* `/api/v1/status`
* customer-facing status endpoints
* database health dashboards
* readiness probes for every dependency
* payment-provider checks
* email/SMS-provider checks
* queue-worker health systems
* external-service health aggregation
* monitoring platforms
* metrics collection
* Prometheus
* Grafana
* Sentry
* Datadog
* New Relic
* OpenTelemetry
* distributed tracing
* uptime SaaS integration
* business metrics
* order/inventory/product status reporting
* database health tables
* audit logs
* deployment automation
* self-healing
* authentication
* authorization
* domain models
* migrations
* static analysis
* CI implementation
* frontend health UI
* Flutter health UI
* Next.js health UI

---

# 37. STOP Condition

Stop immediately when the Phase 2.9 definition of done is satisfied.

Do not continue into Phase 2.10.

Do not implement static analysis.

Do not implement coding standards tooling.

Do not implement the test-framework expansion assigned to Phase 2.11.

Do not implement CI.

Do not expand `/health` into a readiness or monitoring platform.

Do not add database or external dependency checks without an explicit later requirement.

Do not modify the frozen Version 1 API contract.

Do not commit, stage, or push changes. Leave source-control operations to the project owner, consistent with the repository instructions.
