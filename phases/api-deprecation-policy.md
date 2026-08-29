# API Deprecation Policy — Version 1

## 0. Purpose
Defines how API features become deprecated, how they are sunset, and how clients migrate. Applies to `API v1` (`/api/v1`) and future major versions. No actual sunset dates for `v1` are defined in this phase; the lifecycle concept is established.

## 1. Lifecycle States
A version or feature may be:

- `ACTIVE` — fully supported, recommended for all clients.
- `DEPRECATED` — still supported and functional, but clients should migrate away; replacement documented.
- `SUNSET` — no longer supported; requests are rejected with an explicit migration response (e.g., `410 Gone` or `426 Upgrade Required` with documented `v2` base path). A sunset must **not** silently redirect ` /api/v1` → `/api/v2` — HTTP clients following such a redirect would receive a contract they did not explicitly select, conflicting with `api-client-compatibility.md` (clients must explicitly select base path `/api/v1` vs `/api/v2`). Redirects, if used, must not change the API major version.

No `v1` resource is `DEPRECATED` or `SUNSET` at this phase; the states are defined for future use.

## 2. Deprecation vs Removal
- **Deprecation** ≠ **Removal**.
- **Deprecation:** Existing clients should migrate away, but the feature still exists and is served. No breaking impact yet.
- **Removal:** The feature no longer exists in the supported contract. Removal of a previously supported feature is normally **breaking** and requires appropriate versioning (new major version `v2` or sunset of the old version after migration).

## 3. What Gets Deprecated
A deprecated item has:
- Deprecation notice (which resource/field/behavior)
- Reason (why it is deprecated)
- Replacement (what to use instead)
- Support expectation (how long it remains functional)
- Removal policy (when and how it will be removed — only through approved version transition)

Example flow:
```
v1 field (ACTIVE)
  ↓ Deprecated (notice + replacement documented)
  ↓ Migration period (both old and new supported where feasible)
  ↓ Removal only through approved version transition (new major version or sunset after migration)
```

Do not immediately remove a deprecated feature.

## 4. Deprecation Process
- Document deprecation (notice, reason, replacement, support expectation, removal policy).
- Provide migration guidance for Next.js, Flutter, and Admin.
- Maintain parallel support where required during migration.
- For long-lived mobile clients (Flutter), verify active installed versions before sunset; do not sunset solely because the latest build no longer uses the feature (see `api-client-compatibility.md`).
- Removal only via version transition.

## 5. Sunset Concept
- Sunset is the end of support for a version or feature. Before retiring a version, verify active mobile-client versions and their API compatibility (Phase 1.8 §27 rule).
- Do not define actual sunset dates for `v1` yet. This policy establishes the concept for later lifecycle planning.
- When `v2` is introduced, `v1` may become `DEPRECATED`, then `SUNSET` after migration — not immediately removed.

## 6. Migration and Parallel Support
For breaking changes:
```
Proposed breaking change
  ↓ Create new major API version (v2)
  ↓ Migration strategy (what clients change)
  ↓ Parallel support where required (v1 and v2 served)
  ↓ Client migration (Next.js, Flutter, Admin at their own cadence)
  ↓ Deprecation (v1 marked deprecated, replacement is v2)
  ↓ Sunset (v1 retired after verification)
```
For non-breaking changes: remain in `v1`, no major version, no parallel support needed.

## 7. Documentation of Deprecated Features
Deprecated features remain documented with version annotation:
- `API Documentation Version: v1` with deprecation notices inline.
- When `v2` exists: `API Documentation Version: v2` is primary, `v1` documentation retained with deprecation/sunset status.

## 8. Governance
Deprecation and sunset follow the same governance as breaking changes:
- Documented review
- Explicit approval
- No silent removal by individual developer
- API documentation versioned

## 9. Version-Specific Notes
- No artificial minor public URL versions (`/v1.1/`, `/v1.2/`) are introduced. Deprecation within `v1` does not create minor URL versions.
- Error, auth, and authorization deprecations follow the same process (they are part of the contract).
