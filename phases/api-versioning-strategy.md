# API Versioning Strategy — Version 1

## 0. Purpose
Establishes the API versioning strategy before endpoint contracts are defined. Ensures the Laravel backend can evolve without breaking the Next.js website, Flutter app, admin app, or future integrations. This strategy governs the compatibility boundary for `v1` and the introduction of future major versions.

**Authoritative inputs:** `AGENTS.md` §7 (API-First, `/api/v1/...`), `docs/VISION.md`, Phase 1.1–1.7 outputs (`api-resource-inventory.md`, `api-resource-relationships.md`, `logical-data-model-v1.md` v1.0, `freeze-record.md`).

## 1. Initial Version
- **API major version:** `v1`
- **Status:** Explicit from first production release. No implicit or undocumented version.
- **Version scope:** API contract version (externally observable request/response contract). Not database version, not Laravel framework version, not frontend application version (`Flutter 1.8.3` ≠ `API v1`, `Website 2026.08` ≠ `API v1`).

## 2. Version Representation
- **Strategy:** URL path versioning — selected for simplicity, debuggability, frontend (Next.js) and mobile (Flutter) compatibility, caching/CDN visibility, logs/monitoring, and operational clarity for a small-to-medium application.
- **Alternatives considered and rejected:** Header versioning (less visible in logs/caches, harder to debug in Flutter), Media-type/content-negotiation (`Accept: application/vnd.furniture.v1+json` — more complex, poor CDN/cache visibility, overkill for V1 scale).
- **Conceptual base path:** `/api/v1`
  - Example conceptual base (not yet endpoint-specific): `https://api.example.com/api/v1`
  - SEO URLs remain independent: `https://example.com/products/modern-sofa` is public SEO URL; internal data request is `https://api.example.com/api/v1/products/modern-sofa` — API version never contaminates SEO URLs or deep links.
- **Consistency rule:** One consistent representation for all public API resources under `v1`. No mix of versioned and unversioned public resources.

## 3. Version Scope
Version applies to the **public application API** intended for client consumption (website, app, admin). It does **not** apply to:
- Laravel internal Actions/Services/Repositories/Database schema
- Internal backend mechanisms not exposed to clients
A database migration or Laravel refactor does **not** imply `v2`. Only externally observable contract changes matter.

## 4. Compatibility Policy

### 4.1 Major vs Minor
- `v1` is the major compatibility boundary.
- **Within `v1`:** Compatible additions, clarifications, bug fixes that preserve the contract are permitted without changing the major version.
- **Breaking contract change:** Requires a new major version `v2`. No artificial minor public URLs (`/v1.1/`, `/v1.2/`) unless a later requirement proves necessary.

### 4.2 Breaking Changes (require `v2`)
Any change that can reasonably cause a supported existing client to stop functioning correctly:
- Removing a resource or endpoint
- Removing a required response field
- Changing a field's meaning or unit
- Changing a required request field (adding new required field, removing accepted field)
- Changing value type incompatibly (e.g., `price: 1250000` number → `price: "1,250,000 TZS"` string, or number → object)
- Changing allowed enum/state values incompatibly for a `CLOSED` enum (e.g., removing `IN_STOCK`, renaming order status values) where old clients cannot handle it
- Changing authentication requirements incompatibly (e.g., making previously public catalog authenticated)
- Changing resource semantics (e.g., repurposing `Product` into `Product + manufacturing job + supplier record`)
- Changing endpoint path, method, request/response format, or error behavior incompatibly
- Changing authorization semantics that previously allowed access (e.g., customer could access `Tracking`, now denied)

### 4.3 Non-Breaking Changes (remain in `v1`)
- Adding an optional response field
- Adding a new optional request field
- Adding a new resource or endpoint
- Adding a new optional relationship (e.g., optional `product → new optional metadata`)
- Adding a new filter, sort option, or optional pagination metadata
- Adding a new notification type **if** clients treat unknown types safely (per enum policy)
- Adding new category information (optional)
- Adding optional product metadata / optional product image field
- Clarifications and bug fixes that preserve meaning

**Caution:** Additive changes can still break poorly designed clients. Specifically, **adding a new enum value** can break clients that assume exhaustive enumeration — see §5. Enum policy governs this.

### 4.4 Enum and State Compatibility
Version 1 contains extensible value sets: `product types (IN_STOCK, MADE_TO_ORDER)`, `order statuses (PENDING_PAYMENT … CANCELLED)`, `payment states`, `fulfillment types (PICKUP, DELIVERY)`, `request/enquiry states`, `roles (CUSTOMER, STAFF, ADMIN)`.

Rule: **Clients must treat unknown non-critical enum values safely unless the contract explicitly defines the value set as `CLOSED`.**

Later API contracts (Phase 1.11+) must state for each enumeration whether it is:
- `OPEN/EXTENSIBLE` — new values may be added within `v1`; clients must handle unknown values (ignore or display generically, not crash)
- `CLOSED` — value set is fixed within `v1`; addition is breaking and requires major version

No individual enum is declared `OPEN`/`CLOSED` in this phase; the policy is established for later contracts to apply.

**Interim rule for unclassified enums (until a later contract declares `CLOSED`):** Any Version 1 enum not yet explicitly declared `CLOSED` is treated as `OPEN/EXTENSIBLE` for additive values within `v1` — adding a new value is non-breaking **provided** clients have been verified to treat unknown values safely per the rule above. Once a contract declares an enum `CLOSED`, additive values become breaking and require `v2`. This interim rule applies consistently across `api-versioning-strategy.md` and `api-breaking-change-policy.md`.

### 4.5 Response Compatibility
Within `v1`, existing fields must not silently change meaning:
- Response shape and semantics are frozen for supported clients.
- Example non-breaking within `v1` must not become: `{"price": 1250000}` number → `{"price": "1,250,000 TZS"}` string.
- Semantic change (e.g., `price` total product price → discounted price without new field) is breaking.

### 4.6 Request Compatibility
Within `v1`:
- Do not make an existing optional field suddenly required.
- Do not remove a previously accepted request field.
- Do not change semantic meaning of an accepted value.
- Do not unexpectedly narrow accepted values.

### 4.7 Resource Compatibility
Within `v1`, do not silently change a resource's fundamental identity:
- `Product` continues to be the furniture item defined in `api-resource-inventory.md` / `logical-data-model-v1.md`.
- Do not repurpose `Product` into manufacturing/supplier composite without a version decision.

## 5. Error, Auth, and Authorization Contracts are Versioned
- `error structure`, `error code`, `message semantics`, `validation format` — once established, follow same compatibility rules (breaking vs non-breaking).
- Authentication behavior and authorization behavior changes that affect client access are potential breaking changes and must be reviewed as contract changes (detailed authz deferred to Group D, but version implications established here).

## 6. Caching and Operational Considerations
With URL path versioning (`/api/v1`), the version is naturally visible to HTTP caches, CDNs, logs, monitoring, and developer tools — operationally valuable for a small team. No CDN caching is configured in this phase.

## 7. Documentation and OpenAPI Versioning
- API documentation carries the API version: `API Documentation Version: v1` (and `v2` when it exists). No ambiguous documentation when multiple versions coexist.
- OpenAPI document identifies API contract version clearly. Distinguish **OpenAPI specification version** (e.g., `3.1.0`) from **API contract version** (`v1`). Do not conflate them.

## 8. Governance
- **API contract changes** → documented review.
- **Breaking changes** → explicit approval required.
- **Version freeze** → documented baseline (see `freeze-record.md` pattern for model; future API v1 freeze will be separate).
- No single frontend may unilaterally redefine the shared API. No developer may silently change the contract for implementation convenience.

## 9. Version Change Process
- **Non-breaking:** Proposed change → determine non-breaking → impact analysis → client compatibility review → approval → documentation update → implementation → testing → release (remains `v1`).
- **Breaking:** Proposed change → determine breaking → create new major API version (`v2`) → migration strategy → parallel support where required → client migration → deprecation → sunset (see `api-deprecation-policy.md`).

Once `v1` is frozen, every change must be classified. No `it's only one small field` bypass.

## 10. No Premature v2
Do not design `v2` now. Only define **how** `v2` would be introduced if a future breaking contract becomes necessary (parallel support, migration, deprecation, sunset). No hypothetical `v2` endpoints are invented in this phase.

## 11. Payment and Other Deferrals
- **Payment provider integration, payment-specific API contracts, payment webhooks, provider credentials, and confirmation rules → Phase Group H.** This versioning strategy defines only general versioning policy for future payment contracts, not provider-specific versioning requirements.
- Authentication implementation details remain deferred to authentication contract phases; version implications only are defined here.
