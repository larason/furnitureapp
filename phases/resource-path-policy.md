# Resource Path Policy — Version 1

## 0. Purpose
Documents collection paths, item paths, nested paths, self-context paths, admin context rules, and internal-only paths for `API v1` under `/api/v1`. Conceptual policy only; not the endpoint catalog and not Laravel routes.

## 1. Collection and Item Paths
- **Collection:** `/api/v1/products` — represents multiple members. Always plural noun.
- **Item:** `/api/v1/products/{product}` — one product addressed by identifier placeholder `{product}`. Single resource.
- Same pattern for all top-level resources:
  - `categories` → `/api/v1/categories`, `/api/v1/categories/{category}`
  - `products` → `/api/v1/products`, `/api/v1/products/{product}`
  - `orders` → `/api/v1/orders`, `/api/v1/orders/{order}`
  - `requests` → `/api/v1/requests`, `/api/v1/requests/{request}`
  - `enquiries` → `/api/v1/enquiries`, `/api/v1/enquiries/{enquiry}`
   - `notifications` → `/api/v1/notifications` (scoped per policy §3) / `/api/v1/me/notifications`

## 2. Nested Paths
Nesting expresses ownership (parent → child) where child cannot exist outside parent:

- Product images: `/api/v1/products/{product}/images` (+ optional item `/api/v1/products/{product}/images/{image}` for admin)
- Product variants: `/api/v1/products/{product}/variants` (+ `.../variants/{variant}`); variant always under product, never global `/variants` or `/product-variants` top-level.
- Cart items: `/api/v1/carts/{cart}/items` (or self-context `/api/v1/me/cart/items`)
- Order items: `/api/v1/orders/{order}/items`
- Order addresses: embedded inside Order or `/api/v1/orders/{order}/addresses` (transaction snapshot)
- Tracking: `/api/v1/orders/{order}/tracking`
- Status history: `/api/v1/orders/{order}/status-history` or via tracking; kebab-case `status-history`
 - Payment: `/api/v1/orders/{order}/payments` (collection, one-to-many over retries). The collection is **deterministically ordered by ascending payment attempt sequence per order** (ties broken by `created_at`; ordering/attempt field defined in the payment contract); the **current payment** is the latest payment under that ordering, so clients must not infer "current" from arbitrary collection order. Alternative: expose a canonical `current_payment` relation on `Order` to avoid client-side selection — decision deferred to payment contract / Phase Group H. Canonical segment is `payments`; individual payment `payments/{payment}` if addressable.
- Delivery: `/api/v1/orders/{order}/delivery` (conditional `0..1` only when fulfillment=`DELIVERY`; absent for `PICKUP`)
- Request attachments: `/api/v1/requests/{request}/attachments`
- Enquiry attachments: `/api/v1/enquiries/{enquiry}/attachments`
- Category products (relationship view): `/api/v1/categories/{category}/products` may be used if useful, alongside filtering `/api/v1/products?category=...` — choice deferred to query/filter design, but both follow naming conventions if introduced.

## 3. Self-Context Paths
- **Self-context prefix:** `/api/v1/me` represents the currently authenticated customer. Preferred for operations that only concern that customer to avoid exposing arbitrary user IDs. **Documented guest-holder exception:** `/api/v1/me/cart` (and `/api/v1/me/cart/items`) is the sole self-context area that also accepts a valid `GUEST_TOKEN` holder (anonymous persistent cart per DM-DEC-04), so a guest can manage their cart ahead of authentication; every other `me` path requires an authenticated customer. The backend must validate the `GUEST_TOKEN` (well-formed, active, token–cart holder match) and bind the guest cart to the authenticated User on login — cart access is holder-scoped, never cross-customer (per `api-exposure-classification.md`, `relationship-security-review.md`).
- Applied to:
  - `me` (self user): `/api/v1/me`
  - Profile: `/api/v1/me/profile`
  - Orders: `/api/v1/me/orders`, `/api/v1/me/orders/{order}` (alternative to `/api/v1/orders/{order}` with ownership check; self-context makes ownership implicit)
  - Notifications: `/api/v1/me/notifications`
  - Requests: `/api/v1/me/requests`, `/api/v1/me/requests/{request}` (own requests)
  - Enquiries: `/api/v1/me/enquiries`
  - Cart: `/api/v1/me/cart` (and `/api/v1/me/cart/items`) — the sole self-context endpoint with the documented guest-holder exception above (§3): holder-scoped cart resolver serving a `GUEST_TOKEN` holder or authenticated User; avoids arbitrary `carts/{cart}` exposure.
- **When to use vs `users/{user}`:** `/api/v1/me/*` for customer self-service; `/api/v1/users` / `/api/v1/users/{user}` reserved for administrative user management (admin) subject to later authorization design. Do not use `/api/v1/users/{id}/profile` for ordinary customer self-service.

## 4. Admin Context Rules
- **Default:** Same domain vocabulary with authorization context. Resources remain `products`, `orders`, `requests`, `enquiries` with Staff/Admin permissions (via `resource-access-matrix.md`), not duplicated `/admin-products`, `/admin-orders`.
- **Administrative prefix evaluation:** Whether to introduce `/api/v1/admin/...` grouping is decided in `api-naming-decisions.md`. If introduced, it is for operational separation/authorization clarity (e.g., `/api/v1/admin/products`, `/api/v1/admin/orders`) and must be used consistently, not per-resource ad-hoc. Not chosen merely because admin app exists; evaluated against security, documentation clarity, operational separation, governance.
- **Current policy:** No administrative prefix required; authorization distinguishes customer vs staff/admin on same resources. Prefix decision recorded but not mandating duplicate vocabulary.

## 5. Internal-Only Paths
- **Inventory:** Operational/internal. If staff/admin management later requires a path, it is clearly authorized operational and never public: e.g., conceptual `inventory` management under authorized context (exact path deferred; not `/api/v1/inventory` public). Do not expose `physical`/`reserved` quantities via public availability path. Do not define it here as public collection.
- **Payment provider internals, webhook verification internals, credential internals:** Never exposed as customer-facing paths; provider-specific payment paths deferred to Phase Group H.
- Internal backend mechanisms (Laravel Actions/Services) have no URL versioning.

## 6. Special Path Rules
- **Lowercase** throughout; **kebab-case** for multi-word segments: `status-history`, `ready-for-pickup` (action).
- **Versioned base:** All contracted paths under `/api/v1`; no mixing unversioned `/products` with versioned `/api/v1/orders`.
- **Depth:** Keep nesting shallow; typical two levels acceptable (`/orders/{order}/items`). Deeper chains require justification and should flatten via references.
- **Relationship noun:** Use actual relationship noun (`variants`, `images`, `items`, `tracking`, `attachments`) not `children`, `relations`, `linked_entities`.
- Availability is **representation** inside product or optional subresource `/api/v1/products/{product}/availability` if later required; not an independent top-level `availability` collection.
