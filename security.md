# Security Review Resolution

*Verified and remediated: 2026-09-27*

No Critical vulnerability was confirmed. Every reported High, Medium, and Additional Risk was verified against the implementation before remediation.

## High Findings

1. **Resolved: Clerk authentication executed before rate limiting.** `EnforceApiRequestLimits` now applies an IP-based pre-authentication quota before Clerk verification, rejects oversized Authorization headers, and retains the existing post-authentication per-user limits. A regression test proves Clerk verification is not called after the early quota is exhausted.

2. **Resolved: Anonymous cart reads created persistent records.** A first-time anonymous `GET /api/v1/me/cart` now returns a non-persistent, well-formed transient empty Cart (opaque `id`, non-null `updated_at`) and issues no credential. Consecutive first-time reads are independent transient handles and must not be treated as continuous; the client gains a stable cart identity only after the first successful add-item mutation issues the guest credential, which supersedes the transient handle. New guest-cart creation has a separate IP quota, and stale empty guest carts are pruned daily.

## Medium Findings

3. **Resolved: Cross-site guest-cart mutation.** Cookie-authenticated guest mutations require an exact configured Origin and reject cross-site Fetch Metadata. API mutations with bodies require `application/json`. Credentialed CORS is enabled only for configured origins; the guest credential header is explicitly allowed/exposed.

4. **Resolved: Suspended accounts on optional-auth routes.** The active-account assertion is centralized and applied by both required and optional Clerk middleware.

5. **Resolved: Staff/Admin customer-cart access.** Cart read, add, update, remove, merge, and payment placeholder routes enforce the CUSTOMER role when authenticated. Anonymous guest access remains available where the contract permits it.

6. **Resolved: Raw exception/provider content in logs.** API exception logs contain only allow-listed request metadata, status/code, and exception class. Raw throwable objects, messages, provider bodies, and previous-exception chains are not persisted or transmitted by configured log channels. Laravel exception reporting remains enabled so error-tracker report callbacks receive real server failures. Clerk transport exceptions are mapped without separately reporting the provider throwable.

7. **Resolved: Clerk restrictions failed open.** Production boot now requires issuer, audiences, authorized parties, and a verification credential. Verification rejects a mismatched `iss`, and rejects a present `aud`/`azp` that is not allow-listed. Absent `aud`/`azp` claims are accepted to match the Clerk SDK and default session tokens (which carry `azp` but may omit `aud`), so valid authenticated users are not rejected.

8. **Resolved in application and repository configuration: HTTPS, proxy trust, and browser headers.** Production requires an HTTPS application URL, secure cookies, and explicit trusted proxy CIDRs. HTTP API requests are rejected in production. API success and error responses receive CSP, nosniff, frame, referrer, permissions, and production HSTS headers. Deployment must still terminate/redirect HTTP at the ingress and supply only the real proxy CIDRs.

9. **Resolved with layered repository limits: request-body size.** Declared and actual JSON bodies are capped before domain handling, with the frozen `413 REQUEST_TOO_LARGE` response. Apache and PHP limits are committed in `public/.htaccess` and `public/.user.ini`; production ingress must apply an equivalent or stricter 6 MiB hard limit when Apache/user INI files are not used.

## Additional Risks

1. **Resolved: Development seeder.** `DevelopmentUserSeeder` is restricted to local/testing environments and never generates or prints a plaintext password. Local execution requires an explicit demo password.

2. **Resolved: Idempotency retention.** Expired idempotency rows are pruned hourly through the Laravel scheduler.

3. **Resolved: Expensive catalog search.** Product listing/search now has a dedicated 30/minute IP limiter in addition to the general public-read limit. Query/index optimization remains measurement-driven.

4. **Resolved: Unthrottled API routes.** All 75 Version 1 API routes now have a route-specific limiter. An automated topology test prevents new unthrottled Version 1 routes.

5. **Resolved: Optional remote-service encryption.** Production startup rejects remote MySQL/MariaDB without a CA, remote Redis without `rediss://`, and remote SMTP without a secure scheme. Local loopback services and non-SMTP HTTPS mail providers remain supported.

## Operational Requirements

- Run Laravel's scheduler every minute in production so hourly/daily pruning executes.
- Configure `TRUSTED_PROXIES` with only ingress CIDRs; never use a public wildcard.
- Enforce HTTPS redirects and the 6 MiB request ceiling at the production load balancer/reverse proxy.
- Set production Clerk restrictions, secure cookies, remote-service TLS values, and exact `CORS_ALLOWED_ORIGINS` before boot.

## Verification

- PHPUnit: 1,065 tests executed, 1,064 passed, 1 skipped, 4,073 assertions.
- `php artisan route:list --path=api -vv`: all 75 Version 1 routes show throttle middleware.
- `php artisan schedule:list`: idempotency pruning hourly; stale empty guest-cart pruning daily.
- `git diff --check`: passed.
