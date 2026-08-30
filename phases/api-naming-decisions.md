# API Naming Decisions — Phase 1.9

## 0. Purpose
Records final decisions from Phase 1.9 Endpoint Naming Conventions. Each includes Decision ID, Decision, Reason, Alternatives considered, Affected resources, Future phase impact.

## 1.9-DEC-01 — Versioned base path /api/v1

- **Decision:** All contracted public API resources live under `/api/v1` per Phase 1.8. No mix of versioned/unversioned.
- **Reason:** Consistent with `api-versioning-strategy.md` (URL path versioning for simplicity/debuggability/caching); one consistent representation.
- **Alternatives:** Unversioned `/products` at root, header versioning. Rejected — loses operational visibility and contradicts Phase 1.8.
- **Affected resources:** All.
- **Future phase:** All endpoint design (1.10+), OpenAPI.

## 1.9-DEC-02 — Lowercase + kebab-case for multi-word segments

- **Decision:** URLs are lowercase; multi-word segments use kebab-case (`status-history`, `ready-for-pickup`). Never `status_history`, `statusHistory`, `StatusHistory`.
- **Reason:** Single predictable style; aligns with prior correction in relationship map and avoids case-sensitivity issues.
- **Alternatives:** snake_case, camelCase. Rejected — inconsistent and less URL-friendly.
- **Affected resources:** `status-history`, action segments, any future multi-word resource.
- **Future phase:** All path design, OpenAPI.

## 1.9-DEC-03 — Plural nouns for collections

- **Decision:** Collections are plural: `products`, `categories`, `orders`, `payments`, `requests`, `enquiries`, `notifications`. Singular `/product` never for collection.
- **Reason:** Collection represents multiple members; REST convention clarity.
- **Alternatives:** Singular collections. Rejected — ambiguous.
- **Affected resources:** All collections.
- **Future phase:** Endpoint catalog.

## 1.9-DEC-04 — Nouns not verbs (operation via method or action subresource)

- **Decision:** Resources are nouns (`orders`), not verbs (`getOrders`, `createOrder`). Operation belongs to HTTP method or explicit business-action endpoint.
- **Reason:** Resource vs operation separation per AGENTS.md API-first principle.
- **Alternatives:** Verb resources. Rejected — mixes concerns.
- **Affected resources:** All.
- **Future phase:** 1.10 HTTP Method Conventions, action endpoints.

## 1.9-DEC-05 — Identifier placeholder convention

- **Decision:** Use lowercase singular resource name as placeholder: `{product}`, `{category}`, `{order}`, `{request}`, `{enquiry}`. Avoid mixing `{id}`, `{productId}`, `{product_id}`.
- **Reason:** One consistent style project-wide; placeholder conveys resource type, not DB detail.
- **Alternatives:** Generic `{id}` or camelCase `{productId}`. Rejected — loses specificity or introduces inconsistency.
- **Affected resources:** All item paths.
- **Future phase:** Endpoint design, route binding.

## 1.9-DEC-06 — Slug vs identifier distinct, SEO independent

- **Decision:** API resource identifier and public website slug remain distinct; website route `/products/modern-3-seater-sofa` (SEO slug) vs API `/api/v1/products/{product}` (identifier). Do not force same architecture.
- **Reason:** `docs/VISION.md` three actions and SEO principles in `AGENTS.md` §9 require human-readable URLs without coupling to API identification strategy.
- **Alternatives:** API uses slug as identifier. Rejected — couples SEO and API identity; identifier strategy is later implementation decision.
- **Affected resources:** Product, Category.
- **Future phase:** Product contract (1.11), Next.js routes.

## 1.9-DEC-07 — Product images nested under Product

- **Decision:** Product images path is `products/{product}/images` (and optionally individual `.../images/{image}` for admin). Avoid top-level `/images` as primary public design.
- **Reason:** Image belongs to product; no independent business meaning; customer needs images with product.
- **Alternatives:** Top-level `/images`. Rejected — loses ownership, requires later justification.
- **Affected resources:** Product Image.
- **Future phase:** Catalog contract, admin image management.

## 1.9-DEC-08 — Product variants nested under Product

- **Decision:** Variants path is `products/{product}/variants` (and `.../variants/{variant}`); variant always under owning product, never standalone.
- **Reason:** Reinforces `Variant belongs to Product` invariant (Phase 1.7); prevents wrong-product variant reference.
- **Alternatives:** Global `/variants`. Rejected — allows escaping product ownership.
- **Affected resources:** Product Variant.
- **Future phase:** Variant API, inventory per variant.

## 1.9-DEC-09 — Availability as representation inside Product

- **Decision:** Availability is not an independent top-level `availability` collection. Prefer representation inside `products/{product}` (field `availability`) or optional subresource `products/{product}/availability` if later required. Never expose internal inventory internals via this path.
- **Reason:** Availability is derived customer-facing signal; inventory is internal operational resource per `api-resource-inventory.md`.
- **Alternatives:** Top-level `/availability`. Rejected — no independent lifecycle.
- **Affected resources:** Availability, Inventory.
- **Future phase:** Availability read model (5.7), inventory management (5.9).

## 1.9-DEC-10 — Self-context /me preferred for customer ownership

- **Decision:** Customer-facing self-service prefers self-context: `/api/v1/me`, `/api/v1/me/profile`, `/api/v1/me/orders`, `/api/v1/me/notifications`, `/api/v1/me/requests`, `/api/v1/me/enquiries`, and potentially `/api/v1/me/cart`. Avoids exposing arbitrary user IDs for customer-owned data.
- **Reason:** Ownership clarity, minimizes user-ID enumeration, aligns with `resource-access-matrix.md` customer-own boundaries.
- **Alternatives:** Always `/users/{user}/orders` for customer self-service. Rejected — exposes unnecessary user identifiers.
- **Affected resources:** Cart, Orders, Requests, Enquiries, Notifications, User/Profile.
- **Future phase:** Auth contract (1.21), HTTP methods, OpenAPI.

## 1.9-DEC-11 — Admin separation via same vocabulary, prefix evaluated not duplicated

- **Decision:** Default is same domain vocabulary with authorization (`products`, `orders` with Staff/Admin permissions), not duplicated `/admin-products`, `/admin-orders`. Administrative prefix `/api/v1/admin/...` may be introduced later if it genuinely improves separation; if so, used consistently, not per-resource ad-hoc. Not chosen merely because admin app exists.
- **Reason:** Consistency over arbitrary separation; duplication risk; evaluated against security, clarity, operational separation, governance.
- **Alternatives:** Per-resource `/admin-...` duplication. Rejected — duplicates vocabulary.
- **Affected resources:** All catalog/commerce/communication resources.
- **Future phase:** Authorization (Group D), admin operations, endpoint naming finalization.

## 1.9-DEC-12 — Collection vs item semantics fixed

- **Decision:** `/api/v1/products` is collection; `/api/v1/products/{product}` is one product. Never plural identifier `/products/{products}` or singular collection `/product`.
- **Reason:** Consistent semantics across all resources.
- **Alternatives:** Singular collections / plural identifiers. Rejected — ambiguous.
- **Affected resources:** All.
- **Future phase:** Endpoint design.

## 1.9-DEC-13 — Relationship path uses actual relationship noun

- **Decision:** Relationship paths use actual noun: `variants`, `images`, `items`, `tracking`, `status-history`, `attachments`, `notifications`, `delivery`. Never `children`, `relations`, `linked_entities`, `getVariants`.
- **Reason:** Business relationship clarity; matches `resource-relationship-map.md`.
- **Alternatives:** Generic relation names. Rejected — obscures domain.
- **Affected resources:** All relationships.
- **Future phase:** Endpoint design.

## 1.9-DEC-14 — Shallow nesting, deep chains avoided

- **Decision:** Keep public API nesting shallow; typical two levels (`/orders/{order}/items`) is reasonable. Avoid chains like `/users/{user}/orders/{order}/items/{item}/product/{product}/variants/{variant}`. Prefer references once depth grows.
- **Reason:** Deep paths are hard to understand and authorize; ownership and performance suffer.
- **Alternatives:** Deeply nested chains. Rejected — authorization and usability issues.
- **Affected resources:** All nested resources.
- **Future phase:** Endpoint design, authorization.

## 1.9-DEC-15 — Action naming lowercase kebab-case under resource

- **Decision:** Where explicit business-action endpoints are justified, use `/{resource}/{identifier}/{action}` in the abstract pattern (and resource-specific placeholders such as `{order}` in concrete routes, e.g., `/api/v1/orders/{order}/cancel`) with lowercase kebab-case action: `cancel`, `accept`, `ready-for-pickup`, `ship`, `deliver`. One consistent convention; no mixing `ship-order`, `markAsDelivered`.
- **Reason:** Single predictable style for controlled domain actions; avoids CRUD abuse (`PATCH {"status":"SHIPPED"}`).
- **Alternatives:** Mixed camelCase, verb resources at top-level, arbitrary verb styles. Rejected — inconsistency.
- **Affected resources:** Order actions, future business actions.
- **Future phase:** 1.10 HTTP methods, action inventory, order status transitions.

## 1.9-DEC-16 — No fake resources for search/filter/pagination

- **Decision:** Search, filtering, sorting, pagination are not resources. No `/search-products`, `/filter-products`, `/products/page/2` path-based pagination. Collection query conventions handle these later via query parameters on collections: `/api/v1/products?...`.
- **Reason:** Keeps resource namespace clean; consistent with later query conventions.
- **Alternatives:** Fake search resources, path-based pagination. Rejected — mixes concerns, bloats namespace.
- **Affected resources:** Product collections, all collections.
- **Future phase:** 1.6+ query conventions, pagination/filter design.

## 1.9-DEC-17 — Consistent canonical vocabulary adopted

- **Decision:** Adopt vocabulary from `canonical-api-vocabulary.md`: `categories`, `products`, `images`, `variants`, `carts`/`items`, `orders`/`items`/`tracking`/`status-history`/`delivery`/`payment`, `requests`, `enquiries`, `attachments`, `notifications`, `me`/`users`, `auth` operations. Single name per concept; alternates like `product-request` vs `request` are prohibited (use `requests`; business concept documented as made-to-order request).
- **Reason:** One vocabulary prevents drift; descriptive phrase remains in docs.
- **Alternatives:** Multiple synonyms. Rejected — ambiguity.
- **Affected resources:** All.
- **Future phase:** All contracts, OpenAPI.

## 1.9-DEC-18 — Payment remains deferred to Phase Group H

- **Decision:** Payment-specific implementation and provider-specific API paths remain deferred to Phase Group H. No provider-specific payment paths (e.g., provider-named routes) are defined. Only general naming principles for `payment`/`payments` as `orders/{order}/payment` subresource are established here.
- **Reason:** `api-resource-inventory.md` and `api-versioning-strategy.md` defer payment provider work; Phase 1.9 must not accidentally introduce provider-specific versioning or paths. Supersedes any earlier Group G assignment per Phase 1.8 correction.
- **Alternatives:** Defining provider paths now. Rejected — premature.
- **Affected resources:** Payment.
- **Future phase:** Group H (8.x), payment contract (1.14).

## 1.9-DEC-19 — Resource names independent from DB table names

- **Decision:** API names need not match DB tables (`order_status_history` → `status-history`; `product_variants` → `variants` under product). API uses client-friendly domain language.
- **Reason:** Decouples API contract from storage implementation; allows refactoring without breaking API.
- **Alternatives:** API mirrors DB tables. Rejected — couples contract to schema.
- **Affected resources:** All.
- **Future phase:** Physical design (Group C), endpoint design.

## 1.9-DEC-20 — Future expansion without renaming

- **Decision:** Naming strategy must accommodate `wishlist`, `reviews`, `coupons`, `promotions`, `saved addresses`, `push notifications`, `delivery tracking`, `additional payment providers` without changing meaning of existing names. Do not add them to V1.
- **Reason:** Ensures `v1` vocabulary is stable and extensible; additions are `v1` additive (non-breaking) or `v2` if breaking.
- **Alternatives:** Adding future resources now. Rejected — premature scope.
- **Affected resources:** Future candidates.
- **Future phase:** Group W, versioning policy.
