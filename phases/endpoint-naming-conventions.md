# Endpoint Naming Conventions — Version 1

## 0. Purpose
Establishes naming and URL-structure conventions for `API v1`. Every endpoint created later follows one predictable style for public catalog, customer commerce, customer account, requests/enquiries, and staff/admin operations. No endpoint catalog, HTTP methods, or schemas are defined here; only conventions.

Authoritative inputs: `AGENTS.md`, `docs/VISION.md`, `logical-data-model-v1.md` v1.0, `api-resource-inventory.md`, `resource-relationship-map.md`, `api-versioning-strategy.md`.

## 1. Base Path
- **Versioned base:** `/api/v1` per Phase 1.8. All contracted public API resources live under this base. No mix of versioned/unversioned. Example conceptual base `https://api.example.com/api/v1` (domain not finalized).
- All paths below are relative to base. No unversioned `/products` at root.
- SEO website routes (`/products/modern-3-seater-sofa` at `example.com`) remain independent from API paths (`/api/v1/products/{product}` at `api.example.com`).

## 2. General Naming Principles
- **Business resources, not DB tables:** Use domain language (`products`, `orders`, `requests`) not `tbl_products`, `orderModels`, `productRecords`.
- **Lowercase:** All path segments lowercase. Preferred `/api/v1/products`, `/api/v1/categories`, `/api/v1/status-history`. Avoid `/api/v1/Products`, `/api/v1/ProductCategories`, `/api/v1/OrderStatus`.
- **Kebab-case for multi-word segments:** Use hyphen. Preferred `/api/v1/status-history`. Avoid `/api/v1/status_history`, `/api/v1/statusHistory`, `/api/v1/StatusHistory`.
- **Plural nouns for collections:** Collections are plural. `/products`, `/categories`, `/orders`, `/payments`, `/requests`, `/enquiries`, `/notifications`. Avoid singular `/product`, `/category`.
- **Nouns, not verbs:** Resources are nouns (`/orders`), not actions (`/getOrders`, `/createOrder`). Operations use HTTP method or explicit business-action subresource (see §7).
- **Stable across versions:** Within `v1`, resource names do not change meaning; `Product` remains the furniture item defined in `logical-data-model-v1.md`.

## 3. Collection vs Item
- Collection: `/api/v1/products` — represents multiple members.
- Item: `/api/v1/products/{product}` — one product addressed by identifier placeholder.
- Never plural identifier (`/products/{products}`) and never singular collection (`/product`).
- Child collection: `/api/v1/products/{product}/variants` (see §5).

## 4. Identifiers and Slugs
- **Placeholder convention:** Use lowercase singular resource name as placeholder: `{product}`, `{category}`, `{order}`, `{request}`, `{enquiry}`. Avoid mixing `{id}`, `{productId}`, `{product_id}`, `{productID}` within URL templates. One consistent style project-wide: `{product}`.
- **Identifier strategy (naming only):** URL identifies resource via opaque/stable identifier. Exact identifier (internal ID vs customer-facing identifier vs slug) is a later implementation decision, but naming must allow distinct concepts:
  - Internal ID, customer-facing identifier (`OD-…` for orders), slug (`modern-3-seater-sofa`) remain distinct. API URL should not assume slug equals identifier. SEO slug and API identifier are separate concerns.
- **SEO independence:** Public website route `/products/modern-3-seater-sofa` may use slug; API uses its own identification strategy under `/api/v1/products/{product}`.

## 5. Nested Resources
- **Nesting when child cannot exist outside parent and is managed through parent** (Phase 1.7 decision). Examples (conceptual, not final endpoint catalog):
  - `Product → Images`: `/api/v1/products/{product}/images`
  - `Product → Variants`: `/api/v1/products/{product}/variants` and `/api/v1/products/{product}/variants/{variant}` — variant never escapes product.
  - `Cart → Items`: `/api/v1/carts/{cart}/items` (subject to self-context policy §6)
  - `Order → Items`: `/api/v1/orders/{order}/items`
  - `Order → Tracking`: `/api/v1/orders/{order}/tracking` and `/api/v1/orders/{order}/status-history`
  - `Request → Attachments`: `/api/v1/requests/{request}/attachments`
  - `Enquiry → Attachments`: `/api/v1/enquiries/{enquiry}/attachments`
- **Avoid deep nesting:** Keep public API nesting shallow. Typical `/api/v1/orders/{order}/items` is reasonable. Avoid `/api/v1/users/{user}/orders/{order}/items/{item}/product/{product}/variants/{variant}`. Prefer references once depth grows.
- **Availability is representation, not independent top-level:** Prefer availability inside `/api/v1/products/{product}` representation or optional subresource `/api/v1/products/{product}/availability` if later required; never expose internal inventory (`physical/reserved`) via public availability path.

## 6. Self-Context vs User-Scoped Paths
- **Principle:** Customer-facing self-service should prefer self-context to avoid exposing arbitrary user IDs. Recommended for operations concerning the currently authenticated customer: `/api/v1/me`, `/api/v1/me/profile`, `/api/v1/me/orders`, `/api/v1/me/notifications`, `/api/v1/me/requests`, `/api/v1/me/enquiries`, and potentially `/api/v1/me/cart` (vs `/api/v1/carts/{cart}`).
- **Cart:** Customer-facing design may use self-context `/api/v1/me/cart` rather than exposing arbitrary cart IDs, preserving holder-scoped ownership (`GUEST_TOKEN` vs authenticated User).
- **User management vs self-context:** `/api/v1/me` for self-service; separate user-management resource (admin) for administrative traversal of users, subject to later authorization design. Avoid arbitrary `/api/v1/users/{id}/profile` for ordinary customer self-service if `/api/v1/me` is established.
- Decision recorded in `api-naming-decisions.md` (self-context adoption).

## 7. Action Endpoints
- Where an explicit business-action endpoint is necessary (not ordinary field update), prefer consistent **kebab-case action as subresource** under the owning resource:
  - `/api/v1/orders/{order}/cancel`
  - `/api/v1/orders/{order}/accept` (staff/admin)
  - `/api/v1/orders/{order}/ready-for-pickup`, `/api/v1/orders/{order}/ship`, `/api/v1/orders/{order}/deliver`
- Do **not** mix styles (`/cancel`, `/ship-order`, `/markAsDelivered`) — one convention: lowercase kebab-case action segment under resource.
- Do not model every status transition as `PATCH /orders/{order} {"status":"SHIPPED"}` — some transitions are controlled domain actions with invariants/authorization.
- Do not create verb resources at top-level (`/cancel-order`, `/processOrder`) — actions are subresources of the owning resource.

## 8. Relationship Paths
- Use actual relationship noun: `variants`, `images`, `items`, `tracking`, `status-history`, `delivery`, `attachments`, `notifications` — not `children`, `relations`, `linked_entities`, `getVariants`.
- Collection vs relationship consistency: Avoid both `/products?category=...` as sole mechanism and nested `/api/v1/categories/{category}/products` as sole mechanism without decision; filtering vs nesting choice is deferred to later query/filter design, but naming must remain consistent.

## 9. Administrative Boundaries
- **Prefer same domain vocabulary with authorization context** over role-prefixed duplication (`/admin-products`). Resources remain `products`, `orders`, `requests`, `enquiries` with Staff/Admin permissions controlling access.
- **Administrative prefix evaluation:** If project requires `/api/v1/admin/...` for operational separation/authorization clarity, record final convention in `api-naming-decisions.md` and use it consistently — not per-resource ad-hoc. Do not choose it merely because admin app exists; evaluate security, clarity, operational separation, governance.
- Internal-only paths (e.g., `/api/v1/inventory` for staff/admin) must clearly belong to authorized operational API, never public. Do not expose inventory via public endpoint.

## 10. Depth, Characters, and Consistency
- **Depth:** Default maximum shallow; typical two levels (`/orders/{order}/items`) acceptable. Deeper chains require justification and should be flattened via references.
- **Special characters:** No underscores or camelCase in paths; kebab-case only. Consistent across all resources.
- **Database independence:** API names need not match DB tables (`order_status_history` → `status-history`).
- **Search/filter/pagination:** Not resources. Do not create `/search-products`, `/filter-products`, `/products/page/2`; these belong to collection query conventions later (`/api/v1/products?...`).
- **Version consistency:** All resources under `/api/v1`; no mixing `/products` (unversioned) with `/api/v1/orders`.

## 11. Canonical Vocabulary Applies
Resource names in paths derive from `canonical-api-vocabulary.md` vocabulary: `categories`, `products`, `images`, `variants`, `carts`, `items`, `orders`, `payments`, `delivery`, `tracking`, `status-history`, `requests`, `enquiries`, `attachments`, `notifications`, `me`/`users`. Never alternate synonyms (`request` vs `product-request`).

## 12. Out of Scope
This convention does not define HTTP methods, request/response schemas, pagination/filtering syntax, authentication, authorization policies, status codes, Laravel routes, or OpenAPI paths.
