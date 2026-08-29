# API Versioning Matrix — Version 1

## 0. Purpose
Documents supported clients and API versions. Initial state: only `API v1` is supported. No future versions (`v2`) are populated; matrix will be extended when a new major version is introduced.

## 1. Supported Versions Overview

| API Version | Base Path | Status | Supported Clients | Notes |
|---|---|---|---|---|
| `v1` | `/api/v1` | `ACTIVE` | Next.js website, Flutter mobile app, Admin application, future clients | Initial explicit version from first production release. All public API resources under `v1`. |

No `v2` or other versions are supported. Do not populate hypothetical `v2` rows.

## 2. Client Compatibility Matrix

| Client | Technology | Current API | Minimum Supported API | Status | Notes |
|---|---|---|---|---|---|
| Next.js Website | Next.js + MUI, server-rendered | `v1` | `v1` | Supported | Controlled deployment, fast update cadence, but still depends on `v1` contract |
| Flutter Mobile App | Flutter + Material 3 | `v1` | `v1` | Supported | Long-lived client — users may keep older app builds installed for months; do not assume immediate upgrade |
| Admin Application | Next.js + MUI (admin) | `v1` | `v1` | Supported | Controlled internal deployment, but not exempt from versioning discipline; same `v1` contract with elevated privileges |
| Future client / integration | Unknown | `v1` | `v1` | Supported (when created) | Targets documented `v1` at time of integration |

## 3. Version Scope Note
- **API contract version** (`v1`) is not `Flutter App Version` (e.g., `1.8.3`), not `Website Release` (e.g., `2026.08`), not `Laravel framework version`, not `database schema version`. Only externally observable contract changes affect API version.
- SEO URLs (`/products/modern-sofa`) and mobile deep links are independent from API versioning (`/api/v1/...`).

## 4. Multiple Client Coexistence
- `Flutter v1.4`, `Next.js release A`, `Admin release B` may all communicate with `API v1` simultaneously.
- No breaking change is made merely because one client has already been updated; all supported clients are considered.

## 5. Future Evolution Placeholder
When a breaking change requires `v2`:
- A new row will be added: `v2` | `/api/v2` | `ACTIVE` | (applicable clients) | Notes (migration from `v1`)
- `v1` row Status may become `DEPRECATED` (still supported) then `SUNSET` (retired after migration and verification of active mobile versions)
- No minor public URL versions (`/v1.1/`) are introduced.

No `v2` design is included in this phase; only **how** `v2` would be introduced is defined (see `api-versioning-strategy.md` and `api-deprecation-policy.md`).

## 6. Payment and Other Deferrals
- Payment provider integration versioning: **Phase Group H** — no provider-specific versioning in this matrix.
- Authentication implementation versioning details remain deferred to authentication contract phases (version implications only are established).
