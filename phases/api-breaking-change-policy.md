# API Breaking Change Policy — Version 1

## 0. Purpose
Defines concrete breaking and non-breaking change examples for `API v1` (`/api/v1`). Clients (Next.js, Flutter, Admin) may depend on this contract simultaneously; breaking changes require a new major version (`v2`) and migration.

## 1. What Is Breaking
A breaking change is any externally observable API change that can reasonably cause an existing supported client to stop functioning correctly within `v1`.

Breaking includes:
- Removing a resource or endpoint
- Removing a required response field
- Changing a field's meaning, unit, or semantic (e:// price total → discounted without new field)
- Changing a required request field (adding new required field, removing accepted field, changing accepted value meaning, narrowing accepted values)
- Changing value type incompatibly (e.g., `price: number 1250000` → `price: object` or `price: "1,250,000 TZS"` string)
- Changing allowed enum/state values incompatibly for a `CLOSED` enum (removing value, renaming, semantic change) — see enum rule below
- Changing authentication requirements incompatibly (e.g., public catalog `Category`/`Product` becomes authenticated)
- Changing authorization semantics incompatibly (e.g., customer could access own `Tracking`, now denied)
- Changing resource identity/responsibility (e.g., `Product` repurposed into `Product + manufacturing job + supplier`)
- Changing endpoint path, HTTP method, request/response format, pagination/filter/sort contract, or error behavior incompatibly
- Changing response envelope or error code semantics that clients branch on

## 2. What Is Non-Breaking (remains in `v1`)
Potentially non-breaking if additive and compatible:
- Adding an optional response field
- Adding a new optional request field (existing clients may omit it)
- Adding a new resource or endpoint
- Adding a new optional relationship (optional `Product → new metadata`)
- Adding a new filter, sort option, or optional pagination/filter metadata
- Adding optional category information
- Adding a new notification type **if** clients handle unknown types safely
- Clarifications and bug fixes that preserve meaning and do not alter required semantics

**Verification required:** Additive changes must be verified not to break existing clients. A new enum value that is `CLOSED` is breaking; if `OPEN`, additive is non-breaking only if clients tolerate unknown values per `api-versioning-strategy.md`.

## 3. Project-Specific Breaking Examples
- Changing `Product.price` from `number` to `object`
- Removing `Product.slug` (SEO URL dependency)
- Changing order status meaning or renaming statuses (e.g., `SHIPPED` semantics)
- Making checkout anonymous when it was previously authenticated-only (AGENTS.md: checkout requires account) — relaxes auth but changes tracking/ownership semantics and is breaking for clients that enforce auth flow
- Changing request authentication semantics incompatibly (e.g., anonymous `Request`/`Enquiry` create becomes authenticated-only)
- Removing order tracking data or fulfillment timeline fields
- Changing delivery fee semantics (`DELIVERY` fee from order snapshot to computed zone-based without contract update)
- Changing order item historical price meaning (snapshot `unit_price_snapshot` → live product price)
- Removing an existing endpoint or required pagination field

## 4. Project-Specific Non-Breaking Examples
- Adding optional product metadata (`materials`, `care_instructions` as optional)
- Adding a new optional product image field (e.g., `image.caption` optional)
- Adding a new endpoint (e.g., `GET /api/v1/availability` as alternative view)
- Adding an optional filter (`?is_featured=true`)
- Adding optional pagination metadata (`meta.total_pages` optional)
- Adding a new notification type if clients handle unknown types safely (per `OPEN` enum rule)
- Adding new optional category fields (`category.description` optional, `category.icon` optional)

## 5. Enum Special Rule
For this project (`product types`, `order statuses`, `payment states`, `fulfillment types`, `request/enquiry states`, `roles`):
- The initial `v1` contract treats the core workflow enums as `CLOSED` by default unless a later resource contract explicitly declares them `OPEN` after a compatibility review.
- If an enum is documented as `CLOSED` in its contract, adding a value is breaking (requires `v2`).
- If the contract explicitly declares `OPEN/EXTENSIBLE`, additive values are non-breaking only when clients are verified to handle unknown non-critical values safely (display generically, ignore). A later contract may declare `OPEN`, but this phase does not allow an unclassified enum to be treated as effectively open by default.

**Default rule for this phase:** Any Version 1 enum not yet explicitly documented as `OPEN` is treated as `CLOSED` for compatibility purposes. This removes the blanket interim rule that previously made unclassified enums effectively extensible without a formal client review.

## 6. Error/Auth/Authorization Contract Sensitivity
- **Error structure** (`error`, `code`, `message`, `validation format`), **authentication behavior**, and **authorization behavior** are part of the versioned contract. Changing error envelope, error codes, auth requirements, or access rules that previously allowed access are reviewed as potential breaking changes.

## 7. Process
Non-breaking: classify → impact analysis → client review → approval → documentation update → implement → test → release as `v1`.
Breaking: classify as breaking → create new major version `v2` → migration strategy → parallel support → client migration → deprecation → sunset (per `api-deprecation-policy.md`). No `v1.1`/`v1.2` public URL minors.

## 8. Payment Deferral Note
Payment provider integration and payment-specific contract versioning are deferred to **Phase Group H**. This document defines general breaking/non-breaking policy for future payment contracts, not provider-specific endpoints.
