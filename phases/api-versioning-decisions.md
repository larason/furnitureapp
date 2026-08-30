# API Versioning Decisions — Phase 1.8

Records final decisions for API versioning. Each includes Decision ID, Decision, Reason, Alternatives considered, Affected clients, Future impact.

## 1.8-DEC-01 — Initial API version is v1 with URL path versioning at /api/v1

- **Decision:** Initial API major version is `v1` with representation `URL path versioning` at conceptual base `/api/v1` (e.g., `https://api.example.com/api/v1/...`). One consistent representation for all public API resources.
- **Reason:** Explicit version from first production release creates a compatibility boundary for future evolution. URL path is simplest, most debuggable, most visible to caches/CDNs/logs/monitoring, and most practical for Next.js + Flutter + Admin at small-to-medium scale.
- **Alternatives considered:** Header versioning (`X-API-Version`), Media-type/content-negotiation (`Accept: application/vnd.furniture.v1+json`). Rejected for lower visibility, harder debugging in Flutter, poor CDN/cache visibility, unnecessary complexity for V1 scale.
- **Affected clients:** Next.js website, Flutter app, Admin application, future clients — all target `/api/v1`.
- **Future impact:** `v1` is the major compatibility boundary; breaking changes require `v2` with new base `/api/v2`. No minor public URL versions (`/v1.1/`).

## 1.8-DEC-02 — Version scope is API contract, not application/database version

- **Decision:** API versioning applies to the **public application API contract** (externally observable request/response). It does **not** version the database schema, Laravel framework version, or frontend application versions.
- **Reason:** Separates concerns; a database migration or Laravel refactor does not imply a new API major version. Only externally observable contract changes trigger versioning.
- **Alternatives considered:** Versioning database/Laravel/frontend in lockstep with API. Rejected — couples unrelated concerns and would create artificial `v2` on every migration.
- **Affected clients:** All clients — they depend on contract stability, not internal implementation version.
- **Future impact:** Internal changes remain non-breaking unless they alter the observable contract.

## 1.8-DEC-03 — Breaking change requires new major version

- **Decision:** Any API change that can reasonably cause a supported existing client to stop functioning correctly is **breaking** and requires a new major version `v2` (with migration strategy and parallel support where required). Non-breaking changes remain in `v1`.
- **Reason:** Prevents silent breakage for clients that cannot update simultaneously (especially Flutter long-lived installs). Creates explicit evolution path.
- **Alternatives considered:** Allowing breaking changes within `v1` with client coordination. Rejected — unreliable given multiple independent clients and long-lived mobile installs.
- **Affected clients:** All — classification governs all contract changes (see `api-breaking-change-policy.md` for concrete examples).
- **Future impact:** Establishes `v1` as stable; `v2` introduced only when necessary.

## 1.8-DEC-04 — Non-breaking changes remain in v1

- **Decision:** Additive compatible changes (optional response/request fields, new resources/endpoints, optional filters/sorts, clarifications, bug fixes preserving semantics) remain in `v1` without major version change, **provided** they preserve compatibility (verified per policy).
- **Reason:** Allows evolution without forcing major version churn for every addition.
- **Alternatives considered:** Requiring `v2` for any additive change. Rejected — would make API unnecessarily rigid.
- **Affected clients:** All — additive changes must be verified not to break existing clients (especially enum handling).
- **Future impact:** Enables incremental growth within `v1` while preserving compatibility.

## 1.8-DEC-05 — Enum extensibility handled via OPEN/CLOSED declaration

- **Decision:** Enumerations (`product types`, `order statuses`, `payment states`, `fulfillment types`, `request/enquiry states`, `roles`) require explicit contract declaration as `OPEN/EXTENSIBLE` (new values may be added within `v1`, clients must handle unknown values safely) or `CLOSED` (fixed within `v1`, addition is breaking). Policy established now; per-enum declaration deferred to later resource contracts.
- **Reason:** Adding a new enum value can break clients that assume exhaustive enumeration. Establishes safe evolution for value sets that naturally grow.
- **Alternatives considered:** Treating all enums as `CLOSED` (any addition is breaking) — too rigid; or as `OPEN` without client requirement — unsafe for poorly designed clients. Chosen hybrid requires explicit declaration later.
- **Affected clients:** Next.js, Flutter (especially), Admin — must treat unknown non-critical values safely for `OPEN` enums.
- **Future impact:** Each resource contract (Phase 1.11+) must declare `OPEN` vs `CLOSED` per enum.

## 1.8-DEC-06 — No artificial minor public URL versions

- **Decision:** Do not create public URL minors `/v1.1/`, `/v1.2/`, `/v1.3/` for compatible changes. Minor evolution remains under `/api/v1`.
- **Reason:** Unnecessary operational and documentation complexity for V1 scale; minor versions fragment caching and client logic.
- **Alternatives considered:** Semantic minor URL versions for every compatible addition. Rejected unless later requirement proves necessary.
- **Affected clients:** All — simplifies base URL handling.
- **Future impact:** Compatible additions are documented as `v1` evolution; breaking changes use `/api/v2`.

## 1.8-DEC-07 — Deprecation and sunset are distinct lifecycle states

- **Decision:** Lifecycle states are `ACTIVE` → `DEPRECATED` (still functional, migrate away, replacement documented) → `SUNSET` (no longer supported). No `v1` resource is deprecated or sunset now. Removal of a supported feature is breaking and requires version transition.
- **Reason:** Provides migration path without immediate breakage, especially for long-lived mobile clients. Prevents silent removal.
- **Alternatives considered:** Immediate removal without deprecation. Rejected — would break supported clients without warning.
- **Affected clients:** All — deprecation notices guide migration; sunset verified against active mobile versions.
- **Future impact:** When `v2` is introduced, `v1` may become `DEPRECATED`, then `SUNSET` after migration (see `api-deprecation-policy.md`).

## 1.8-DEC-08 — Documentation and OpenAPI versioning carry API version

- **Decision:** API documentation is versioned (`API Documentation Version: v1`, later `v2`). OpenAPI document clearly identifies API contract version, distinct from OpenAPI specification version (e.g., `3.1.0` ≠ `API v1`). No ambiguous documentation when multiple versions coexist.
- **Reason:** Prevents client confusion when `v1` and `v2` coexist (e.g., Flutter on `v1`, Admin on `v2`).
- **Alternatives considered:** Single unversioned documentation. Rejected — ambiguous when multiple API versions are supported.
- **Affected clients:** All — clients target documented version.
- **Future impact:** Each major version has its own documentation baseline.

## 1.8-DEC-09 — Payment provider integration versioning deferred to Group H

- **Decision:** General versioning policy for future payment API contracts is defined here, but payment provider integration, provider-specific contracts, webhooks, credentials, and confirmation rules are deferred to **Phase Group H**. No provider-specific versioning requirements are defined in this phase; no provider is selected.
- **Reason:** Prevents premature provider-specific design from contaminating general versioning policy. Keeps general policy provider-agnostic.
- **Alternatives considered:** Defining provider-specific versioning now. Rejected — provider not yet selected per `api-resource-decisions.md` and AGENTS.md.
- **Affected clients:** Payment flow clients (checkout, webhook handler) will be versioned under same `v1`/`v2` scheme when Group H defines payment contracts.
- **Future impact:** Group H will define payment-specific breaking/non-breaking examples and migration within the general policy established here.

## 1.8-DEC-10 — Version changes require governance (no silent contract changes)

- **Decision:** API contract changes require documented review; breaking changes require explicit approval; version freeze is a documented baseline. No single frontend or developer may unilaterally redefine the shared API. Error, auth, and authorization contracts are versioned and follow the same breaking/non-breaking rules.
- **Reason:** Ensures `Laravel ↔ Next.js ↔ Flutter ↔ Admin` contract remains explicit and reviewable, per AGENTS.md API-First principle.
- **Alternatives considered:** Allowing individual developer to change contract for implementation convenience. Rejected — would undermine shared contract.
- **Affected clients:** All — contract stability is team-governed.
- **Future impact:** Version change process (proposed → classify → impact analysis → client review → approval → documentation → implementation → testing → release) governs all evolution.
