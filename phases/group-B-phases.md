# Group B phases instructions

# Phase 2.8 — Logging Foundation

## Objective

Implement the Laravel backend logging foundation required for reliable diagnosis of application and API failures.

The implementation must establish:

* consistent application logging
* API request/error correlation
* safe structured log context
* appropriate log channels and configuration
* useful log levels
* integration with the Phase 2.7 exception/error foundation
* tests proving sensitive information is not logged

Keep the implementation intentionally small and dependency-first.

This phase establishes logging infrastructure for later backend features. It must not become a full production monitoring or observability platform.

---

# 1. Phase Context

Phases 2.1–2.5 have already been implemented as part of the Laravel foundation:

* repository/project setup
* Laravel initialization
* environment configuration
* database connection
* base application structure

Phase 2.6 established API routing.

Phase 2.7 established the centralized API exception/error handling foundation.

Now implement **Phase 2.8 — Logging Foundation**.

Do not redo completed foundation work unless a minimal compatibility correction is necessary.

The roadmap sequence remains:

```text
2.6 API routing foundation
→ 2.7 Exception/error handling foundation
→ 2.8 Logging foundation
→ 2.9 Health/status endpoint
→ 2.10 Coding standards/static analysis
→ 2.11 Test framework setup
→ 2.12 CI baseline
```

Stop at Phase 2.8.

---

# 2. Authoritative Sources

Before modifying code, read the current versions of:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`

Treat the frozen Version 1 API contract as authoritative.

Do not create a separate logging specification merely to duplicate the project's existing documentation.

Relevant project requirements include:

* important failures must be diagnosable
* application logs are required
* API error logging is required
* payment/webhook/queue failures will eventually require diagnostic logging
* sensitive information must not be logged indiscriminately
* API errors must use the existing request correlation mechanism
* auditability exists as a business requirement but is not the same thing as application logging

The project explicitly requires application logs and API error logging as part of observability.

---

# 3. Core Principle

Logging is an operational diagnostic mechanism.

It is **not**:

* an API response mechanism
* a database audit trail
* a business-event store
* a customer notification mechanism
* a replacement for error handling
* a replacement for authorization
* a replacement for tests

The relationship should remain:

```text
Application operation
        ↓
success / expected failure / unexpected failure
        ↓
centralized logging
        ↓
diagnostic record
```

API clients continue receiving the Phase 2.7 error contract.

Logs remain server-side diagnostic data.

---

# 4. Logging Scope

The foundation must support at least:

### Application logs

For meaningful application events and failures.

### API error logs

For server-side diagnostics of API failures, especially unexpected failures.

### Request correlation

Logs must be correlatable with the API `meta.request_id`.

### Exception context

Unexpected exceptions should retain useful server-side diagnostic context without exposing that context to clients.

### Environment-aware behavior

Development and production must be able to use appropriate logging configurations without requiring code changes.

---

# 5. Request Correlation

The request ID established by Phase 2.7 is the primary correlation identifier.

Use the same request ID in:

* API error response `meta.request_id`
* request-scoped log context
* exception/error log entries
* application log messages generated during that request

Do not generate unrelated identifiers for each log line.

Do not use any of these as a replacement for the request ID:

```text
user_id
order_id
payment_id
```

Those may be additional context when safely available, but they do not replace the request ID.

The frozen API contract explicitly defines `request_id` as a per-request correlation identifier intended to connect customer-visible errors with server logs.

---

# 6. Request ID Lifecycle

Implement a clear request-scoped lifecycle:

```text
incoming request
    ↓
obtain existing trusted request ID or generate one
    ↓
bind to request context
    ↓
make available to API error handler
    ↓
make available to logger context
    ↓
request completes
    ↓
request-scoped context is cleared
```

Do not allow request context to leak between requests.

The mechanism must work for:

* successful requests
* validation failures
* authentication failures
* authorization failures
* 404s
* 409s
* 429s
* unexpected exceptions

The logger and Phase 2.7 exception handler must use the same request ID for the same request.

---

# 7. Trusted Request ID Input

If the implementation accepts a client-provided request ID, do not blindly trust arbitrary input as the internal correlation identifier.

Use an explicit policy for accepted identifiers.

At minimum:

* validate format/length
* reject obviously unsafe values
* avoid control characters/newlines
* prevent log injection
* do not allow the value to contain secrets or sensitive payloads
* preserve the canonical request ID format used by the application

If the existing Phase 2.7 implementation already generates request IDs server-side, preserve that behavior rather than introducing a second mechanism.

Prefer a server-generated identifier unless the existing architecture explicitly requires propagation from a trusted upstream.

---

# 8. Structured Logging

Prefer structured context over large free-form strings.

Useful log context may include:

```text
request_id
environment
application
HTTP method
route name/path
HTTP status
duration
exception class (server-side logs only)
error code
authenticated principal identifier when appropriate
resource identifier when appropriate
```

Only include identifiers when they are genuinely useful for diagnosis and are safe to retain.

Do not blindly dump the entire request, response, authentication object, exception object, or model into the log context.

Avoid logs such as:

```text
"request": $request->all()
```

or equivalent whole-request serialization.

Structured logging should be deliberate and allow-listed.

---

# 9. Sensitive Data Policy

Never log sensitive information indiscriminately.

The existing project security baseline prohibits exposing secrets and requires careful error-message/logging discipline.

Do **not** log:

* passwords
* password confirmation
* access tokens
* refresh tokens
* guest cart bearer tokens
* upload tokens
* cookies containing credentials
* authorization headers
* CSRF tokens
* payment secrets
* provider credentials
* webhook secrets
* full private addresses
* private customer records unnecessarily
* raw request bodies containing sensitive information
* raw exception payloads that may contain secrets

The API conventions specifically identify passwords, tokens, payment secrets, and full private addresses as data that must not be indiscriminately logged.

Do not assume production configuration alone will make unsafe logging acceptable.

The code itself must avoid creating those log records.

---

# 10. HTTP Header Logging

Do not log all request headers by default.

In particular, never log whole:

```text
Authorization
Cookie
Set-Cookie
X-Guest-Cart-Id
X-Upload-Token
X-CSRF-Token
```

headers.

The guest cart token and upload token are bearer credentials under the frozen API security model and must therefore be treated as secrets.

If diagnostic header information is ever needed, log only explicitly allow-listed non-sensitive metadata.

---

# 11. Request Body Logging

Do not automatically log request bodies.

This is especially important because requests can eventually contain:

* passwords
* authentication data
* private delivery addresses
* contact information
* uploaded-file metadata
* payment-related fields
* free-form messages

The logger foundation should provide safe contextual fields rather than raw body dumps.

Do not create middleware that records every incoming JSON payload for convenience.

---

# 12. Response Logging

Do not automatically log complete API response bodies.

The API response can contain:

* personal data
* order information
* private operational information
* error details
* temporary attachment URLs
* other data inappropriate for general logs

Prefer compact metadata such as:

```text
request_id
route
status
duration
error_code
```

For errors, use the centralized error representation rather than duplicating the entire response body.

---

# 13. Log Levels

Establish a small, consistent log-level policy.

Use levels according to operational significance rather than arbitrarily.

A reasonable foundation is:

### DEBUG

Local/development diagnostics that are useful during development but should not become routine production noise.

Do not use DEBUG to dump secrets or request bodies.

### INFO

Meaningful normal application events that are useful for operational understanding.

Examples may eventually include:

* application lifecycle events
* selected background-operation milestones
* major non-sensitive workflow events

Do not turn every successful HTTP request into a verbose INFO record unless the chosen architecture explicitly requires it.

### WARNING

Unexpected but non-fatal conditions requiring attention.

Examples may eventually include:

* degraded external dependency behavior
* recoverable infrastructure conditions

### ERROR

Failures requiring investigation.

Examples:

* unexpected API exception
* database operation failure
* failed background operation
* important infrastructure error

### CRITICAL

Only for genuinely severe failures requiring immediate operational attention.

Do not mark ordinary validation failures as CRITICAL.

---

# 14. Validation and Expected Client Errors

Do not log every expected client validation failure as an ERROR-level event.

For example:

```text
422 INVALID_VALUE
422 MISSING_REQUIRED_FIELD
```

are often expected API outcomes and can create significant noise.

The foundation should allow the application to distinguish:

```text
expected client/business failure
```

from:

```text
unexpected server failure
```

The exact logging policy for individual domain errors can be refined later as those domains are implemented.

Do not duplicate every API error into high-severity logs by default.

---

# 15. Integration with Phase 2.7

Phase 2.7 established the centralized API exception/error mechanism.

Integrate logging at that boundary.

For unexpected exceptions:

```text
exception
    ↓
log diagnostic event
    ↓
map to safe API error
    ↓
return 500 INTERNAL_SERVER_ERROR
    ↓
meta.request_id remains correlated
```

The API client must still receive the sanitized Phase 2.7 contract.

The log should contain enough server-side context to diagnose the problem.

Do not expose the logged exception details through the API response.

The frozen API conventions explicitly require safe client errors while retaining full exception/stack information on the server side for diagnostics.

---

# 16. Exception Logging

When logging an unexpected exception, capture useful diagnostic information through the logging system rather than manually concatenating strings.

Where appropriate, include:

* exception object/stack for server logs
* request ID
* route/path
* HTTP method
* status being returned
* safe error code
* environment
* relevant safe identifiers

Do not automatically attach entire request/session/model objects.

Never use raw exception messages as the API client's `message`.

The API error contract explicitly forbids leaking framework exception names, SQLSTATE values, stack traces, paths, secrets, and similar implementation details.

---

# 17. SQL and Database Errors

Do not suppress database exceptions from the server log merely because they are sensitive.

They must remain diagnosable server-side.

However:

* database SQL must never be returned to the API client
* SQLSTATE values must not become API error codes
* query bindings containing sensitive data must not be indiscriminately logged
* connection credentials must never be logged
* database connection configuration must never be logged

The Phase 2.7 error boundary handles client-safe translation.

Phase 2.8 handles safe server-side diagnostics.

---

# 18. Authentication and Credential Safety

Authentication is not yet implemented in this phase, but the logging foundation must be designed so future authentication code can use it safely.

Make it structurally difficult to log:

```text
password
password_confirmation
Authorization
access_token
refresh_token
session secret
guest_cart_id
upload token
```

Authentication-related event logging can later record safe metadata such as:

```text
event
request_id
outcome
safe principal identifier where appropriate
source/IP metadata where justified
timestamp
```

but never credential material.

The frozen authentication conventions explicitly require protection against credential theft, token leakage, and similar threats.

Do not implement authentication logging workflows before authentication exists.

---

# 19. Guest Cart and Upload Token Safety

The logging layer must respect the existing security model.

Guest cart IDs are bearer credentials and upload tokens are scoped credentials.

Therefore:

* never log `X-Guest-Cart-Id` values
* never log `guest_cart_id` cookie values
* never log `X-Upload-Token`
* never include them in generic request context
* never include them in exception context
* never include them in debug dumps

The frozen API conventions explicitly define these as security-sensitive bearer credentials.

---

# 20. User and Resource Context

Later application code may need to log safe identifiers such as:

```text
user_id
product_id
order_id
request_id
enquiry_id
```

Do not prohibit all identifiers.

Instead, distinguish:

```text
safe diagnostic identifier
```

from:

```text
sensitive payload
```

Never use logs as an excuse to copy complete private records.

For private resources, log the minimum identifier/context necessary for diagnosis.

Do not log:

```text
full order
full customer profile
full delivery address
full enquiry message
full request payload
```

unless a later, explicitly justified diagnostic policy says otherwise.

---

# 21. Log Channels

Configure a simple Laravel logging structure suitable for the current project scale.

Prefer the simplest reliable setup supported by the existing Laravel application.

At minimum, the implementation should support:

* application logging
* error logging
* environment-specific configuration

Do not introduce:

* Elasticsearch
* OpenSearch
* Loki
* Datadog
* Sentry
* cloud log ingestion
* distributed tracing
* Kafka
* message buses
* Kubernetes logging infrastructure

unless such infrastructure already exists in the repository and is explicitly required by the current project.

The project's architecture principles favor a simple reliable architecture rather than premature distributed infrastructure.

---

# 22. Environment Configuration

Logging configuration must remain environment-driven.

Do not hard-code production-only behavior.

Ensure the application can configure at least:

```text
log channel
log level
environment-appropriate destination
```

through the project's existing environment/configuration mechanism.

Do not commit secrets or production credentials.

The project explicitly prohibits committing secrets, tokens, and production credentials to source control.

Do not create unnecessary new environment variables merely for hypothetical future monitoring.

---

# 23. Log Format

Use a consistent machine-readable or structured format where supported by the existing Laravel configuration.

The format should make these fields easy to identify:

```text
timestamp
level
message
request_id
context
```

Additional context should remain structured rather than embedded in ad hoc text whenever practical.

Do not create a custom proprietary log format when Laravel's existing logging facilities can satisfy the requirement.

Keep messages concise and useful.

---

# 24. Correlation with API Error Contract

When Phase 2.7 produces:

```json
{
  "errors": [
    {
      "code": "INTERNAL_SERVER_ERROR",
      "message": "An unexpected error occurred."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

the corresponding server log must contain the same:

```text
request_id
```

This gives support/operations a safe bridge:

```text
customer-visible error
        ↓
request_id
        ↓
server logs
        ↓
exception diagnostics
```

Do not expose server log references, stack traces, or internal storage locations in the API response.

---

# 25. Log Injection Protection

Treat values eventually written to logs as untrusted input.

Do not construct log messages by directly concatenating arbitrary user-controlled values where that can create:

* fake log lines
* embedded newlines
* control-character injection
* misleading severity prefixes
* forged timestamps
* forged structured fields

Prefer structured logger context.

Particular care is required for:

* request IDs
* query parameters
* user-agent values
* referrer values
* contact fields
* free-form enquiry/request text

Do not log free-form user content by default.

---

# 26. Performance

Logging must not materially degrade the API.

Avoid:

* expensive database queries merely to construct a log entry
* serializing large objects
* serializing entire requests/responses
* synchronous external log delivery
* repeated serialization of the same exception
* excessive INFO/DEBUG volume

Use the framework's normal logging infrastructure.

At this stage, local/file-based or similarly simple logging is preferred over distributed logging systems.

---

# 27. Logging Failures

Logging must not cause the API to fail whenever possible.

A logging destination failure should not turn an otherwise unrelated business request into a second application failure unless the chosen infrastructure explicitly requires fail-closed behavior.

Avoid recursive patterns such as:

```text
logging fails
→ log logging failure
→ logging fails
→ ...
```

Keep the logging foundation robust and simple.

---

# 28. Audit vs Application Logging

Do not confuse normal application logs with the project's future audit mechanism.

Application logs are primarily for:

```text
diagnostics
errors
operational troubleshooting
```

The eventual audit mechanism is for:

```text
privileged state changes
actor
action
resource
previous state
resulting state
timestamp
request_id
```

The API conventions require privileged state changes to become auditable, but do not implement that audit infrastructure in this phase.

Do not create an `audit_logs` table during Phase 2.8.

Do not implement `GET /admin/audit-logs`.

---

# 29. Business Event Logging

Do not add logging calls throughout the codebase merely because "logging is now available."

Only add foundational integration points now.

Future domain phases can add meaningful operational events such as:

```text
order transition
inventory adjustment
staff approval
payment failure
webhook failure
```

when those domains actually exist.

The current phase should establish the infrastructure and safe usage pattern.

---

# 30. Payment and Webhook Logging

Payment failures and webhook failures are explicitly part of the project's eventual observability requirements.

However, payment integration belongs to Group H.

Therefore:

* establish only the generic logging foundation
* do not add payment-provider SDK logging
* do not log provider payloads
* do not log webhook secrets
* do not log payment credentials
* do not implement payment failure workflows
* do not implement webhook handlers

Future Group H work must use this foundation.

---

# 31. Queue/Job Logging

The AGENTS observability requirements also include queue/job failures where queues are used.

Do not build the queue system in Phase 2.8.

Only ensure the logging architecture is usable from future jobs.

Do not introduce worker infrastructure merely to demonstrate logging.

---

# 32. Testing Requirements

Add focused tests for the logging foundation.

At minimum verify:

### Request correlation

* a request receives one request ID
* the request ID is available to application logging
* API error responses use the same request ID
* multiple log entries for one request can use the same correlation ID

### Unexpected exception

Trigger a controlled unexpected exception.

Verify:

* API response remains sanitized
* server logging occurs
* request ID is present in the log context
* exception diagnostics remain server-side

### Sensitive-data protection

Create tests demonstrating that logs do not contain:

* passwords
* authorization headers
* access tokens
* refresh tokens
* guest cart tokens
* upload tokens
* CSRF tokens
* payment secrets
* full private addresses

Do not merely rely on code review for this requirement.

### Request/response body protection

Verify generic request middleware does not dump complete request bodies or response bodies.

### Log levels

Verify representative application events can use the intended level policy without turning expected client errors into inappropriate critical errors.

### Log injection

Test that unsafe request metadata cannot forge additional log records or corrupt structured logging.

---

# 33. Test Strategy Before Phase 2.11

Phase 2.11 is responsible for the formal test-framework setup.

Use whatever testing capability already exists in the repository.

Do not implement a second testing framework as part of Phase 2.8.

Do not block this phase on future test infrastructure.

Add focused tests using the currently available project test setup.

---

# 34. Minimal Code Comments

Keep source-code comments to the absolute minimum.

Do not add comments that merely explain obvious logging calls.

Avoid:

```php
// Log the exception
Log::error(...);
```

Prefer clear method names and straightforward structure.

A comment is justified only when explaining:

* a non-obvious security restriction
* a framework-specific logging behavior
* a subtle correlation requirement
* a deliberate sensitive-data exclusion
* an operational compatibility constraint

Do not embed the entire logging policy inside source-code comments.

Do not create long explanatory comments in middleware or exception handlers.

---

# 35. Code Quality

Follow the existing AGENTS code-quality rules:

* small cohesive modules
* explicit naming
* single responsibility
* dependency injection where useful
* centralized behavior where appropriate
* no giant middleware
* no giant exception handler
* no copy-pasted sanitization logic
* no magic strings scattered throughout the code
* no premature generic logging framework
* no unnecessary abstractions

The project's code-quality rules explicitly favor small cohesive modules and centralized error handling while discouraging giant components and premature abstractions.

---

# 36. Documentation

Update documentation only where necessary to make the implemented logging foundation understandable and maintainable.

Do not create a large operational manual.

Document only important implementation facts such as:

* where request correlation is established
* what logging channel/configuration is used
* how sensitive fields are protected
* how developers should add safe contextual logs

Follow the project's rule that important architecture decisions and operational procedures should be documented near relevant code or in project documentation.

Do not create speculative documentation for production observability systems that do not yet exist.

---

# 37. Security Review

Before completion, explicitly review for:

```text
password leakage
token leakage
cookie leakage
guest-cart token leakage
upload-token leakage
CSRF-token leakage
payment-secret leakage
request-body leakage
response-body leakage
full-address leakage
exception-message leakage
SQL leakage
stack-trace leakage
log-injection risk
cross-request correlation leakage
```

Remember that logs are sensitive infrastructure data.

Do not assume logs are harmless simply because they are not returned by the API.

---

# 38. Files and Structure

Use the existing Laravel project conventions.

Possible areas include:

```text
config/logging.php
app/Exceptions/
app/Http/Middleware/
app/Support/
bootstrap/
tests/
```

Do not create files solely because these paths are common Laravel locations.

Follow the repository's existing structure.

Avoid creating a custom logging subsystem if Laravel's built-in logging facilities are sufficient.

---

# 39. No Direct Database Audit Storage

Do not add:

```text
audit_logs table
activity_logs table
request_logs table
api_logs table
```

to the database during this phase.

Application logs should remain infrastructure logs, not relational business data.

Future audit requirements will be implemented intentionally when the appropriate domain/security phase is reached.

---

# 40. No External Monitoring Platform

Do not integrate:

* Sentry
* Bugsnag
* Datadog
* New Relic
* Elastic APM
* OpenTelemetry collectors
* Grafana/Loki
* cloud-specific log services

unless such a dependency already exists and this project explicitly requires it.

The roadmap separates basic logging from later production observability/error-tracking work.

The AGENTS roadmap places production `Logging/monitoring` and `Error tracking` much later in the production-readiness phase, not in this backend foundation phase.

---

# 41. Verification Commands

Run the project's currently available verification commands.

At minimum:

```bash
php artisan test
```

Also verify the application can start normally and that logging works in the current environment.

If the project's current scripts expose appropriate commands, run the relevant backend checks.

Do not wait for Phase 2.10 static-analysis tooling or Phase 2.12 CI tooling to exist.

---

# 42. Manual Verification

Perform a small manual verification of the logging path.

Verify that:

1. a normal API request can execute without logging-related failure
2. a controlled API exception creates a server-side diagnostic entry
3. the diagnostic record contains the expected request ID
4. the API response contains the same request ID
5. no sensitive request credentials appear in the log
6. unexpected exceptions do not expose stack traces to clients

Keep the verification focused.

Do not construct a full observability environment.

---

# 43. Review Checklist

Before marking Phase 2.8 complete, verify:

* [ ] application logging is configured
* [ ] API error logging is integrated with Phase 2.7
* [ ] one request ID is shared across the request
* [ ] API error `meta.request_id` matches the logged request ID
* [ ] unexpected exceptions are logged server-side
* [ ] expected client validation failures do not create excessive high-severity noise
* [ ] sensitive request headers are not logged
* [ ] request bodies are not automatically logged
* [ ] response bodies are not automatically logged
* [ ] passwords are not logged
* [ ] auth tokens are not logged
* [ ] guest cart bearer tokens are not logged
* [ ] upload tokens are not logged
* [ ] CSRF tokens are not logged
* [ ] payment secrets are not logged
* [ ] full private addresses are not logged indiscriminately
* [ ] raw SQL/SQLSTATE is not exposed to API clients
* [ ] stack traces are not exposed to API clients
* [ ] log injection is addressed
* [ ] environment-driven logging configuration works
* [ ] logging remains simple and local-scale appropriate
* [ ] no audit database has been introduced
* [ ] no external monitoring platform has been introduced
* [ ] no payment/webhook implementation has been introduced
* [ ] no queue infrastructure has been introduced
* [ ] comments in new code are minimal
* [ ] relevant tests pass
* [ ] existing tests pass

---

# 44. Definition of Done

Phase 2.8 is complete only when:

1. Laravel has a working, centralized logging foundation.
2. API errors from Phase 2.7 can be correlated to server logs using `request_id`.
3. Unexpected exceptions produce useful server-side diagnostics.
4. Logging does not leak credentials, tokens, private payloads, or implementation secrets.
5. Request/response bodies are not indiscriminately logged.
6. Log records use consistent levels and structured context.
7. Logging configuration is environment-driven.
8. Logging does not require new distributed infrastructure.
9. Focused security and correlation tests pass.
10. The implementation is small, maintainable, and aligned with the existing Laravel structure.
11. No audit database, monitoring platform, payment integration, or queue system has been added.
12. New code contains only the minimum necessary comments.

---

# 45. Explicitly Out of Scope

Do **not** implement during Phase 2.8:

* database migrations
* audit-log tables
* audit API endpoints
* customer activity history
* authentication
* authorization
* role/permission logging workflows
* catalog logging workflows
* inventory logging workflows
* cart logging workflows
* checkout logging workflows
* order logging workflows
* payment integration
* payment-provider logging
* webhook handlers
* webhook-specific logging
* queue/worker implementation
* queue-specific operational infrastructure
* notification logging workflows
* external monitoring
* distributed tracing
* OpenTelemetry infrastructure
* Sentry/Bugsnag/error-tracking platforms
* centralized cloud logging
* SIEM integration
* dashboards
* alerting
* production incident-management tooling
* health/status endpoint
* static analysis
* CI implementation
* frontend logging
* Flutter logging
* Next.js logging
* comprehensive audit infrastructure
* business-rule implementation

Later phases must build on this foundation rather than recreating logging independently.

---

# 46. STOP Condition

Stop immediately when the Phase 2.8 definition of done is satisfied.

Do not continue into Phase 2.9.

Do not implement the health/status endpoint.

Do not implement authentication.

Do not implement authorization.

Do not implement domain models.

Do not implement database audit tables.

Do not integrate an external monitoring service.

Do not implement payment/webhook logging workflows.

Do not redesign the frozen API contract.

Do not add broad logging merely for the sake of increasing log volume.

Do not commit, stage, or push changes. Leave source-control operations to the project owner, consistent with the repository instructions.
