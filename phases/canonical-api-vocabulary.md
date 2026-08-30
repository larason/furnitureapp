# Canonical API Vocabulary — Version 1

## 0. Purpose
Single approved API name for every domain concept. Becomes authoritative for all later phases; the API must never have multiple names for the same business concept.

## 1. Vocabulary Table

| Business Concept | Canonical API Resource / Segment | Notes |
|---|---|---|
| Category | `categories` | Top-level collection; `categories/{category}` item. Single-level V1. |
| Product | `products` | Top-level; `products/{product}`. SEO slug separate from API identifier. |
| Product Image | `images` as Product child | Path `products/{product}/images` (and `products/{product}/images/{image}` if individual image addressable for admin). |
| Product Variant | `variants` as Product child | `products/{product}/variants` (+ `products/{product}/variants/{variant}`). Variant never standalone. |
| Availability | representation / subresource `availability` | Representation inside Product (`availability`) or optional subresource `products/{product}/availability`; never internal inventory. |
| Inventory | restricted operational `inventory` | Internal/operational, not public; staff/admin only (see `resource-path-policy.md`). |
| Cart | `carts` / self-context `me/cart` | Cart resource; self-context preferred for customer (`me/cart`). |
| Cart Item | `items` as Cart child | `carts/{cart}/items` (or `me/cart/items` under self-context). |
| Order | `orders` | Top-level; `orders/{order}` (customer own, staff/admin all with authz). |
| Order Item | `items` as Order child | `orders/{order}/items`. Historical snapshots. |
| Order Address | `addresses` as Order child (or embedded) | `orders/{order}/addresses` if address is subresource; otherwise embedded inside Order representation (transaction snapshot). |
| Tracking | `tracking` as Order child | `orders/{order}/tracking`. Read-oriented timeline. |
| Status History | `status-history` | `orders/{order}/status-history` or via `tracking`; kebab-case. Canonical `status-history` (not `statusHistory` or `status_history`). |
| Payment | `payments` as Order child | `orders/{order}/payments` — collection (one-to-many over retries); deterministically ordered by ascending payment attempt sequence per order (ties broken by `created_at`; exact ordering field defined in payment contract), with the **current payment** being the latest under that ordering. Segment is `payments` (plural per collection convention). Individual payment if addressable is `orders/{order}/payments/{payment}`; Group H will define payment contract details, but canonical segment is `payments`. |
| Delivery / Fulfillment | `delivery` as Order child | `orders/{order}/delivery` — conditional only when fulfillment=`DELIVERY`. |
| Made-to-order Request | `requests` | Top-level `requests`, `requests/{request}`. Business concept is “made-to-order request”, API resource is `requests`; documentation explains meaning. Not `product-requests`, `furniture-requests`, `made-to-order-request`. |
| General Enquiry | `enquiries` | Top-level `enquiries`, `enquiries/{enquiry}`. Distinct from `requests`; never merged into `communications`. |
| Attachment | `attachments` as Request/Enquiry child | `requests/{request}/attachments`, `enquiries/{enquiry}/attachments`. Polymorphic owner is Request or Enquiry; never top-level `attachments` public. |
| User / Profile | `me`, `me/profile` for self; `users` for admin management | Self-context `me` / `me/profile` for authenticated customer; `users` / `users/{user}` for admin user management (subject to later authz). |
| Notification | `notifications` | `notifications` top-level is recipient-scoped, or `me/notifications` under self-context per self-context policy (see `resource-path-policy.md`). Never global `attachments`-style public. |
| Auth operations | `auth` operations (deferred) | Registration, login, logout, recovery, verification, session/token — operations, not ordinary CRUD; resource vocabulary `auth` grouping deferred to auth contract (Phase 1.9 not enumerating). |

## 2. Consistency Rules
- Never alternate synonyms: `request` vs `product-request` vs `furniture-request` vs `made-to-order-request` — use `requests` only.
- Enquiries remain `enquiries` (not `enquiry` singular for collection, not `communications`).
- Multi-word segment `status-history` always kebab-case, never `status_history` or `statusHistory`.
- Resource names match domain language, not DB tables (`order_status_history` → `status-history`; `product_variants` → `variants` under product).
- Identifiers use singular placeholder matching resource: `{product}`, `{category}`, `{order}`, `{request}`, `{enquiry}` — never `{id}`, `{productId}`, `{product_id}` mixing.

## 3. Aliases Not Permitted
- `product-request`, `furniture-request`, `made-to-order-request` → use `requests`
- `communications` → use `requests` or `enquiries` separately
- `orderModels`, `tbl_products` → use `orders`, `products`
- `me/cart` vs `carts/{cart}` — both valid under self-context policy, but vocabulary is `carts`/`items` with self-context prefix `me`

## 4. SEO vs API
API vocabulary `products`, `categories` is independent from website route vocabulary — website may use `/products/modern-3-seater-sofa` (SEO slug), API uses `/api/v1/products/{product}` (identifier). Do not conflate.
