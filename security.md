# Security Review Resolution

*Verified and remediated: 2026-09-27*

No Critical vulnerability was confirmed. Every reported High, Medium, and Additional Risk was verified against the implementation before remediation.

## High Findings

1. **Resolved: Clerk authentication executed before rate limiting.** `EnforceApiRequestLimits` now applies an IP-based pre-authentication quota before Clerk verification, rejects oversized Authorization headers, and retains the existing post-authentication per-user limits. A regression test proves Clerk verification is not called after the early quota is exhausted.

2. **Resolved: Anonymous cart reads created persistent records.** A first-time anonymous `GET /api/v1/me/cart` now returns a non-persistent, well-formed transient empty Cart (opaque `id`, non-null `updated_at`) and issues no credential. Consecutive first-time reads are independent transient handles and must not be treated as continuous; the client gains a stable cart identity only after the first successful add-item mutation issues the guest credential, which supersedes the transient handle. New guest-cart creation has a separate IP quota that counts only credential-less (or malformed-credential) creation attempts, so it bounds persistent growth without throttling returning guests: a supplied credential never creates a cart (it resolves an existing one or is rejected `401`), so those requests remain governed solely by the frozen `cart-add` limit. Guest carts are deliberately not time-pruned: deleting a cart whose credential a client may still hold would strand that guest with a `401` (the frozen contract rejects unknown/retired credentials), so the credential is kept resolvable instead.

## Medium Findings

3. **Resolved: Cross-site guest-cart mutation.** Anonymous cookie-authenticated guest mutations require an exact configured `Origin` (missing or mismatched `Origin` is rejected); allow-listed cross-site requests are permitted, matching the frozen conventions. Authenticated customers are not subject to this check because the cart resolver uses the bearer identity and ignores the guest cookie, so a stale cookie cannot block a legitimate customer mutation. API mutations with bodies require `application/json`, except the contracted upload endpoints (`REQ-001`/`ENQ-001` inline and `REQ-007`/`ENQ-007`) which accept `multipart/form-data`. Credentialed CORS is enabled only for configured origins; the guest credential header is explicitly allowed/exposed.

4. **Resolved: Suspended accounts on optional-auth routes.** The active-account assertion is centralized and applied by both required and optional Clerk middleware.

5. **Resolved: Staff/Admin customer-cart access.** Cart read, add, update, remove, merge, and payment placeholder routes require an authenticated CUSTOMER and explicitly deny any account that also holds `STAFF` or `ADMIN` (Spatie roles are additive, so a mixed `ADMIN`+`CUSTOMER` account is still denied the customer-cart boundary). Anonymous guest access remains available where the contract permits it.

6. **Resolved: Raw exception/provider content in logs.** API exception logs carry allow-listed request metadata, status/code, exception class, and redacted diagnostics: a secret-redacted exception message and a compact, argument-free, basename-only stack trace, so 500s of the same class stay distinguishable by request ID. Raw throwable objects, unredacted messages, provider bodies, previous-exception chains, call arguments, and full filesystem paths are not persisted or transmitted by configured log channels; redaction uses the shared `DiagnosticText` secret patterns. Laravel exception reporting remains enabled so error-tracker report callbacks receive real server failures. Clerk transport exceptions are mapped without separately reporting the provider throwable.

7. **Resolved: Clerk restrictions fail closed.** Production boot requires issuer, audiences, authorized parties, and a verification credential. Verification rejects a mismatched `iss` and, when an allow-list is configured, requires the corresponding `aud`/`azp` claim to be present and to intersect that allow-list — an omitted claim is rejected rather than bypassing the restriction. The Clerk SDK skips absent claims, so this application-level check is what enforces the restriction. Claims are only ignored when no allow-list is configured (local/testing).

8. **Resolved in application and repository configuration: HTTPS, proxy trust, and browser headers.** Production requires an HTTPS application URL, secure cookies, and explicit trusted proxy CIDRs. HTTP API requests are rejected in production. API success and error responses receive CSP, nosniff, frame, referrer, permissions, and production HSTS headers. Rendered API error responses (including early pre-auth `429`/`400`) also receive the configured CORS headers so browsers can read them; the CORS middleware alone decorated only successful responses. Deployment must still terminate/redirect HTTP at the ingress and supply only the real proxy CIDRs.

9. **Resolved with layered repository limits: request-body size.** Declared and actual JSON bodies are capped before domain handling, with the frozen `413 REQUEST_TOO_LARGE` response. A non-JSON body without `Content-Length` (for example chunked) is detected with a single-byte probe and rejected with `415` without buffering the body, so an unlimited streamed payload cannot consume application memory. Apache and PHP limits are committed in `public/.htaccess` and `public/.user.ini`; production ingress must apply an equivalent or stricter 6 MiB hard limit when Apache/user INI files are not used.

## Additional Risks

1. **Resolved: Development seeder.** `DevelopmentUserSeeder` is restricted to local/testing environments and never generates or prints a plaintext password. Local execution requires an explicit demo password.

2. **Resolved: Idempotency retention.** Expired idempotency rows are pruned hourly through the Laravel scheduler.

3. **Deferred pending frozen-contract change: Expensive catalog search.** A stricter search-specific quota would lower the frozen `GET /products 100/min/IP` behavior (`api-conventions.md` §32.11), which is not permitted without the post-freeze contract-change process. The documented 100/min/IP limit is preserved; a search-specific limiter must go through that process before it can be enabled. Query/index optimization remains measurement-driven.

4. **Resolved: Unthrottled API routes.** All 75 Version 1 API routes now have a route-specific limiter. An automated topology test prevents new unthrottled Version 1 routes.

5. **Resolved: Optional remote-service encryption.** Production startup rejects remote MySQL/MariaDB without a CA, remote Redis without `rediss://`, and remote SMTP without a secure scheme. Local loopback services and non-SMTP HTTPS mail providers remain supported.

## Cart Operation Review

1. **Resolved: Guest-cart mutation racing guest-to-customer merge.** Every cart mutation now locks the holder cart row first (`ActiveCartLock`) and requires it to still be `ACTIVE` before touching a line; `CART-005` merge locks the source and target cart rows (ordered by id) then the source lines. A mutation that loses the race is rejected `409 CONFLICT` and never silently writes to the retired cart. (Previously lines were locked without the cart row, so an add/update could commit after merge read and retired the source and disappear.)

2. **Resolved: Unbounded cart lines.** A cart is capped at `Cart::MAX_ITEMS = 100` distinct `(product_id, variant_id)` lines, enforced transactionally inside the add path after the cart lock; adding a new line beyond the cap returns `422 INVALID_VALUE`. Increasing the quantity of an existing line is unaffected. This bounds cart read/projection cost and per-cart growth.

3. **Resolved: Mixed-role customer-cart boundary.** `CustomerCartAccess` denies `STAFF`/`ADMIN` even when the account also holds `CUSTOMER` (see Medium item 5).

4. **Deferred: Abandoned/inactive guest-cart retention.** Time-pruning is intentionally **not** re-added: deleting a cart whose credential a client may still hold strands that guest with `401` (see High item 2), and inactive carts are never read. Growth is bounded by the per-cart line cap and the mutation-only creation quota. A retention policy that preserves credential resolvability (e.g., retirement that still answers `401` deterministically) can be designed later.

5. **Resolved: CART-005 bodyless boundary.** `POST /me/cart/merge` rejects a non-empty request body `422 INVALID_VALUE` (empty/`[]`/`{}` accepted), matching the bodyless action contract.

6. **Accepted defense-in-depth gap: Fetch Metadata on cookie guest mutations.** Anonymous cookie mutations still require an exact allow-listed `Origin`; allow-listed cross-site requests are deliberately permitted per the frozen conventions, so additionally rejecting `Sec-Fetch-Site: cross-site` would be a frozen-contract change. The `Origin` allow-list remains the authoritative control.

## Operational Requirements

- Run Laravel's scheduler every minute in production so the hourly idempotency-key pruning executes.
- Configure `TRUSTED_PROXIES` with only ingress CIDRs; never use a public wildcard.
- Enforce HTTPS redirects and the 6 MiB request ceiling at the production load balancer/reverse proxy.
- Set production Clerk restrictions, secure cookies, remote-service TLS values, and exact `CORS_ALLOWED_ORIGINS` before boot.

## Verification

- PHPUnit: 1,102 tests executed, 1,101 passed, 1 skipped, 4,242 assertions.
- `php artisan route:list --path=api -vv`: all 75 Version 1 routes show throttle middleware.
- `php artisan schedule:list`: idempotency-key pruning hourly.
- `git diff --check`: passed.
