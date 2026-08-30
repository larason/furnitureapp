# HTTP Method Resource Matrix — Version 1

## 1. Purpose
Preliminary mapping of domain operations to preferred HTTP methods. Convention matrix only, not the final endpoint contract. Each row will later be bound to a concrete endpoint path (per `endpoint-naming-conventions.md` and `resource-path-policy.md`) with authorization, validation, and versioning.

## 2. Matrix

| Domain operation | Preferred method | Resource / Action context | Special handling |
|---|---|---|---|
| Read products | `GET` | `Product` collection (`/api/v1/products`) | Safe, public, cache-eligible |
| Read category | `GET` | `Category` (`/api/v1/categories/{category}` or collection) | Safe, public |
| Read product | `GET` | `Product` item (`/api/v1/products/{product}`) | Safe, public; includes availability representation |
| Read availability | `GET` | `Product`/`Variant` availability via product | Safe, public, limited signal |
| Read cart | `GET` | `Cart` (`/api/v1/me/cart` or holder-scoped) | Customer-owned / `GUEST_TOKEN` holder |
| Add cart item | `POST` | `Cart Item` to `Cart` collection | Mutation, holder-scoped |
| Update cart quantity | `PATCH` | `Cart Item` (`{cart}/items/{item}`) — absolute `quantity = n` | Validate inventory; idempotent absolute assignment |
| Remove cart item | `DELETE` | `Cart Item` | True removal (deactivatable: removable) |
| Checkout | `POST` (workflow/action) | `Checkout` → creates `Order` + `Payment` initiation when applicable (provider-specific behavior in Phase Group H) | Mutation, idempotency required, authenticated only, backend-controlled pricing/inventory/fulfillment |
| Create order | `POST` / workflow via checkout | `Order` collection (via checkout) | Backend-controlled only; not direct `POST /orders` with client total |
| Read order | `GET` | `Order` (`/api/v1/me/orders/{order}` or `/api/v1/orders/{order}` with authz) | Owner/authorized (customer own, staff/admin all) |
| Read tracking | `GET` | `Order Tracking` (`/api/v1/orders/{order}/tracking`) | Read-only, projection without operational note for customer |
| Cancel order | `POST` action (`.../cancel`) | `Order` business action | Controlled, 20-minute rule, idempotency required, not `DELETE` |
| Accept order | `POST` action (`.../accept`) | `Order` | Staff/admin only, transition validation |
| Process order | `POST` action (`.../process`) | `Order` | Staff/admin, `ACCEPTED → PROCESSING` |
| Ship order | `POST` action (`.../ship`) | `Order` | Staff/admin, `PROCESSING → SHIPPED` (delivery only), idempotency required |
| Deliver order | `POST` action (`.../deliver`) | `Order` | Staff/admin, `SHIPPED → DELIVERED` |
| Ready for pickup | `POST` action (`.../ready-for-pickup`) | `Order` | Staff/admin, `PROCESSING → READY_FOR_PICKUP` (pickup only) |
| Complete order | `POST` action (`.../complete`) | `Order` | Staff/admin or system, terminal |
| Create request | `POST` | `Made-to-order Request` collection | Anonymous (with contact) or authenticated; not `PUT` |
| Read request | `GET` | `Request` (`/api/v1/me/requests/{request}` or staff view) | Owner / staff/admin |
| Create enquiry | `POST` | `Enquiry` collection | Anonymous or authenticated |
| Read enquiry | `GET` | `Enquiry` | Owner / staff/admin |
| Read notifications | `GET` | `Notification` inbox (`/api/v1/me/notifications`) | Own only |
| Mark notification read | `PATCH` (or controlled action) | `Notification` read state | Own only; `GET /notifications/{id}/read` is forbidden |
| Update profile | `PATCH` | `User/Profile` (`/api/v1/me/profile`) | Authenticated, partial modification only, no credential/security bypass |
| Update product (admin) | `PATCH` | `Product` | Staff/admin authorized; ordinary fields; activation may be controlled action |
| Payment initiation | `POST` / workflow | `Payment` via checkout/order | Authenticated customer, idempotency required |
| Provider webhook | `POST` (event) | Payment callback (provider → backend) | Group H, event-driven, idempotent handling |

## 3. Collection vs Item Semantics
- `GET` collection (`/api/v1/products`) → retrieve collection (public, paginated, filtered later).
- `POST` collection (`/api/v1/requests`) → create new member (server assigns identity).
- `GET` item (`/api/v1/products/{product}`) → retrieve one.
- `PATCH` item → partial modification where authorized (profile, cart quantity absolute, product admin fields).
- `DELETE` item → actual deletion only where `destructible` (cart item, temporary attachment where permitted); never for historically retained (`Order`, `Order history`) or deactivatable (`Product` → archive).

## 4. Idempotency Flags in Matrix
- **Idempotency required:** `Checkout`, `Payment initiation`, `Cancel`, `Ship`, `Deliver` (critical order actions), and `Accept`/`Process`/`Ready-for-pickup`/`Complete` (critical order actions where side effects exist). Noted as `POST` action but must be designed idempotent where flagged.
- **Absolute PATCH idempotent:** `Update cart quantity` (`quantity = n`), `Update profile` (field assignment).
- **Non-idempotent by nature if misdesigned:** `increment by n` PATCH — avoid; use absolute.

## 5. Payment Note
Payment-specific method semantics remain assigned to **Phase Group H**. This matrix establishes general method preferences (creation via `POST`, confirmation not client-trusted, webhooks as `POST` events with idempotency); no provider is selected here.
