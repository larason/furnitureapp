# Query Relationship Policy — Version 1

## 1. Purpose

Records the project's Version 1 decision on whether clients may dynamically request related resources via query parameters (`include`) or dynamically select response fields via sparse fieldsets (`fields`), and what relationship expansion semantics the API supports for Next.js, Flutter, and Admin. Ensures the catalog/commerce API does not acquire a complex dynamic query language accidentally, while leaving room for later inclusion if genuinely required.

**Authoritative inputs:** `AGENTS.md` §§3,6,8,20, `logical-data-model-v1.md`, `api-resource-inventory.md`, `api-resource-relationships.md`, `resource-embedding-policy.md`, `endpoint-naming-conventions.md`, `phase-1.11.md` §§45-47.

## 2. Decision Summary

| Mechanism | V1 Decision | Notes |
|---|---|---|
| `include` (relationship expansion) | **DEFERRED — not supported in V1** | Do not implement. See §3. |
| `fields` (sparse fieldsets) | **DEFERRED — not supported in V1** | Do not implement. See §4. |
| Relationship reads | Supported via **dedicated subresources** and **predictable embeds** per inventory, not dynamic query language | §5 |
| Field selection | Predetermined per resource representation per contract | §4 |

Rationale summarized in §6; deferred mechanisms may be reconsidered after V1 resource contracts prove a genuine need, with a migration strategy per `api-versioning-strategy.md`.

## 3. `include` — Relationship Expansion

### V1 Policy: Not Supported

- V1 does **not** support `?include=category,variants` or any generic dynamic include language.
- A request such as `GET /api/v1/products?include=category,variants` → validation error (unknown parameter per `query-parameter-conventions.md` §5) or explicitly documented as unsupported in the product contract. Never silently accepted and ignored.
- Rationale: For a small commerce platform, predictable representations and dedicated subresources are simpler to authorize, cache, version, and document than a generic `include` language. Evidence per `phase-1.11.md` §46: MUI/Next.js/Flutter clients can consume predictable responses without a generic handler. Introducing `include` would require per-relationship authorization, allow-list, and complexity-limit design that V1 does not need.

### How Relationships Are Read Without `include`

Per `api-resource-relationships.md` and `resource-embedding-policy.md`:

- **Embedded** (inside parent representation) where the child has no independent lifecycle: `Product → Images`, `Product → Variants`, `Order → Order Items`, `Order → Order Address` are embedded or summarized inside the owning resource per `resource-embedding-policy.md` exposure recommendations.
- **Subresource** (dedicated read via child collection) where child is independently addressable or conditional: `GET /api/v1/products/{product}/variants`, `GET /api/v1/products/{product}/images`, `GET /api/v1/orders/{order}/tracking`, `GET /api/v1/requests/{request}/attachments`. These remain the canonical way to fetch relationships without `include`.
- **Reference** (identifier/link) where the relationship is a pointer to an independent resource: `Product.category` is a `SUMMARY` (`id/slug/name`) embedded, not the full `Category` tree; full traversal uses the `Category` collection.

No V1 `include` does not mean relationships are inaccessible — it means the access path is the embedding and subresource structure already inventoried, not a dynamic client-chosen graph.

### Future `include` Reconsideration (If Ever Needed)

If V1 additive evolution later shows repeated over-fetching or waterfall requests that are measurable, the project may consider a constrained `include` with:
- explicit allow-list per parent (`Product: category`),
- `LIMITED` depth (`1`), maximum included relationships,
- authorization per included relationship,
- documented cache semantics.

Adoption would require: usage evidence, impact analysis, additive vs `v2` compatibility review, and documentation. Not assumed now.

## 4. `fields` — Sparse Fieldsets

### V1 Policy: Not Supported

- V1 does **not** support `?fields=id,name,price` or any sparse-fieldset language.
- `GET /api/v1/products?fields=id,name,price` → validation error in V1 (unknown parameter).
- Rationale: For a small application, response size does not yet justify sparse fieldsets. Per `phase-1.11.md` §45, do not add `fields` merely because some APIs have it. Predictions from `api-resource-inventory.md` already minimize payload (internal `physical_quantity` not in `Product` projection); targeted subresource reads already reduce payload where needed.
- All resources return their **predetermined contract representation** (public projection for `Product`/`Category`, holder-scoped for `Cart`/`Order`, privileged expansion only where Group D or resource ownership allows).

### How Clients Request Smaller Payloads Without `fields`

- Use the minimal subresource (e.g., `GET /api/v1/categories/{category}` vs product collection) and forthcoming pagination sizing (Phase 1.12) rather than ad-hoc field selection.
- Caching and server rendering benefit from stable representations; dynamic shapes complicate Next.js rendering and Flutter deserialization.

### Future `fields` Reconsideration

Same threshold as `include`: measurable payload/caching evidence → impact analysis → compatibility review (`fields` additive if optional, but client handling must be verified) → decision. No commitment now. If deferred mechanisms are adopted they would require field allow-list and security review to prevent `?fields=password_hash` abuse.

## 5. Implicit Expansion Via Endpoint vs Query

- Resource expansion belongs to **endpoint design** (subresource path under `endpoint-naming-conventions.md` §5) or **embedded representation** defined by the resource contract, not to query-driven mutation of the response shape.
- Public website's Next.js rendering and Flutter data layer therefore depend on stable shapes, not on each caller remembering the right `include` set.

## 6. Why Deferral Is the Right Choice for V1

Per `phase-1.11.md` §§45-47 Avoid Generic Query Languages:

- **Simplicity:** The commerce operations (browse catalog, checkout, track order, submit request/enquiry) do not require advanced query capabilities; explicit `min_*`/`max_*` filters and dedicated subresources cover them (prefer `?category=sofas&min_price=...&max_price=...&product_type=...` over a universal DSL such as `filter[field][operator]=value`).
- **Security:** Dynamic `include`/`fields` expands the authorization surface; deferral avoids premature policy complexity.
- **Versioning:** Stable representations are easier to keep compatible within `v1` (additive optional fields are non-breaking per `api-versioning-strategy.md` §4.3; dynamic shapes shift that guarantee).
- **Verifiability:** Predetermined embeds are testable against `logical-data-model-v1.md` and `api-resource-relationships.md` without combinatorial query cases.

## 7. Security Note

- Where relationships involve owner-scoped data (`Order → Address`, `Payment`, `Tracking` with Operational note), expansion would require per-relationship ownership checks. Deferring `include` avoids accidental inclusion of another customer's subresources via query.

## 8. Out of Scope

Exact embedded field lists, subresource response shapes, pagination details, OpenAPI schema, and authorization implementation are deferred to resource-contract and Group D work. This policy solely records the include/fields decision and its successor path.
