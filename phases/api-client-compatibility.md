# API Client Compatibility — Version 1

## 0. Purpose
Documents compatibility expectations for each client consuming `API v1` (`/api/v1`). Multiple clients may be deployed at different cadences and must coexist on the same API version without forced simultaneous updates.

## 1. Clients

| Client | Technology | Deployment Cadence | Compatibility Notes |
|---|---|---|---|
| **Next.js Website** | Next.js + MUI, server-rendered where appropriate | Fast, team-controlled deployment (continuous) | Consumes catalog (SEO), cart, checkout, orders, tracking, requests, enquiries. Can be updated quickly, but still depends on `v1` contract; breaking changes still require `v2`. |
| **Flutter Mobile App** | Flutter + Material 3, repository-based data access | Slower — users may keep older builds installed for months | **Long-lived client concern.** Must not assume all users upgrade immediately. Supported Flutter builds may be multiple versions behind latest. API must remain compatible for supported app versions. |
| **Admin Application** | Next.js + MUI (admin) | Team-controlled, internal | Consumes catalog management, inventory, orders, requests, enquiries, customers, fulfillment. Also a client of the same `v1` API; not exempt from versioning discipline despite being internal. Different privileges, same contract. |
| **Future clients/integrations** | Unknown (e.g., future integrations, tooling) | Unknown | Must be able to target `v1` as documented. New major version `v2` introduces parallel support, not immediate replacement. |

## 2. Coexistence on `v1`
- `Flutter v1.4`, `Next.js release A`, `Admin release B` may all communicate with `API v1` simultaneously.
- Do not make breaking API changes merely because one client has already been updated. All supported clients must be considered.

## 3. Client-Specific Considerations

### 3.1 Mobile (Flutter) — Long-Lived
- Before retiring a version, verify active mobile-client versions and their API compatibility. Do not sunset `v1` solely because the latest mobile build no longer uses it.
- Supported Flutter versions will have an explicit compatibility period defined later in the lifecycle policy (not invented in this phase; no fixed months defined here).
- Flutter must handle `OPEN` enum additions safely (ignore unknown non-critical values) per breaking-change policy.

### 3.2 Website (Next.js)
- Can often be deployed quickly, but website and admin still depend on the `v1` contract. Do not assume website control justifies breaking changes.
- SEO URLs (`/products/modern-sofa`) remain independent from API versioning (`/api/v1/products/modern-sofa`). No API version in SEO URLs or mobile deep links.

### 3.3 Admin
- Admin is not exempt from versioning discipline despite being internal. Admin consumes documented contracts under `v1` with elevated privileges (Staff/Admin), not bypass APIs.

## 4. API Version vs Application Version
- `API v1` ≠ `Flutter App Version 1.8.3` ≠ `Website Release 2026.08`. A Flutter update or website deployment does **not** automatically create a new API major version. Only externally observable contract changes matter (per `api-versioning-strategy.md` Version Scope).

## 5. Expectations for Future Clients
- Future clients target the documented `v1` contract at time of integration. If `v2` exists in parallel, clients choose `v1` or `v2` base path explicitly. No transparent auto-upgrade.

## 6. Compatibility Matrix (Conceptual, `v1` Only — No Future Versions Populated)

| Client | Current API | Minimum Supported API | Notes |
|---|---|---|---|
| Next.js Website | `v1` | `v1` | Controlled deployment |
| Flutter App | `v1` | `v1` | Long-lived client concern; multiple app builds may be in the wild |
| Admin | `v1` | `v1` | Controlled deployment |
| Future client | `v1` | `v1` | Targets `v1` until `v2` migration path exists |

No unsupported future versions (`v2`) are populated. Matrix will be extended when `v2` is introduced.

## 7. Negotiation and Visibility
- For Version 1, prefer simplicity: version is obvious from request path (`/api/v1/...`). No complex dynamic version negotiation (no `Accept` header versioning) unless demonstrated requirement emerges.
- With URL path versioning, the version is visible to HTTP caches, CDNs, logs, monitoring, and developer tools.

## 8. Auth and Error Compatibility
- Authentication and authorization behavior are part of the versioned contract (see `api-versioning-strategy.md` §7). Future auth changes must not unexpectedly invalidate supported clients without version handling.
- Error structure and codes are versioned; Flutter and website clients may branch on error codes.

## 9. Payment Note
Payment provider integration is deferred to **Phase Group H**. Client compatibility for payment flows (checkout initiation, webhook-driven confirmation) will be versioned under the same `v1`/`v2` scheme when payment contracts are defined; no provider-specific compatibility is defined here.
