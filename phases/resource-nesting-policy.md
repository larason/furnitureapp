# Resource Nesting Policy — Version 1

## 0. Purpose
Documents when nesting is appropriate, maximum practical depth, when to use references, and parent/child ownership rules for `API v1`. Derived from `api-resource-relationships.md` and `resource-relationship-map.md`.

## 1. When Nesting Is Appropriate
Nesting (`/parent/{parent}/child`) is appropriate when:
- Child cannot meaningfully exist outside parent.
- Child is primarily managed through parent.
- Child is naturally part of parent's representation.

**Appropriate nested (child) resources:**
- Product Images → `products/{product}/images` (image belongs to product)
- Product Variants → `products/{product}/variants` (variant never escapes product)
- Cart Items → `carts/{cart}/items` or `me/cart/items` (ephemeral, holder-scoped)
- Order Items → `orders/{order}/items` (immutable snapshots, 1..n, owned by order)
- Order Addresses → embedded or `orders/{order}/addresses` (billing exactly 1, delivery 0..1 conditional)
- Order Tracking → `orders/{order}/tracking`
- Status History → `orders/{order}/status-history` or via tracking (append-only; choose one canonical path)
- Delivery → `orders/{order}/delivery` (conditional on `DELIVERY`)
- Request Attachments → `requests/{request}/attachments`
- Enquiry Attachments → `enquiries/{enquiry}/attachments`

## 2. Maximum Practical Depth
- **Keep public API nesting shallow.** Two levels is typical and reasonable: `/api/v1/orders/{order}/items`.
- **Avoid deep chains** such as `/api/v1/users/{user}/orders/{order}/items/{item}/product/{product}/variants/{variant}` — difficult to understand and authorize.
- **Rule:** Once relationships exceed two levels, prefer references or separate resources (e.g., Order Item's optional current product reference is not full product embed; Product embedded inside Order Item only as snapshot, not via deep nesting).

## 3. When to Use References Instead of Nesting
Use references (identifier + summary/link, not full nest) when:
- Target has independent lifecycle or is reused by multiple resources (Category is standalone, referenced by Product).
- Relationship is associative (`Product → Category` is reference, not `categories/{category}/products/{product}` nesting for creation).
- Embedding would cause excessive data or circular recursion (Category → Products full embed would recurse).

**Examples:**
- `Product → Category` is reference: Product embeds Category summary (`id/slug/name`), not full Category with products.
- `Category → Products` for browsing may be via `categories/{category}/products` relationship view **or** filtering `products?category=...` — choice deferred to query/filter design, but not deep nesting of categories inside products inside categories.

## 4. Parent/Child Ownership Rules
- **Variant belongs to Product:** Nested structure reinforces this; API must prevent referencing a variant under the wrong product (validation: variant must belong to product identified in path).
- **Cart Item belongs to Cart:** Holder-scoped; no cross-cart identity.
- **Order Item belongs to Order:** Immutable; never top-level `Order Items` global collection.
- **Attachment belongs to exactly one Request or Enquiry:** Polymorphic owner is the parent in path; never global `attachments` collection without owner context.
- **Tracking/Status History belongs to Order:** Append-only; customer read-only projection.

## 5. Circular Representation Risks
- Product embeds Category SUMMARY, not full Category with products, to break `Product → Category → Products → Category` cycle.
- Order embeds Order Items FULL but Order Item's optional current Product reference is SUMMARY/REFERENCE only, not full Product with images/variants.
- User does not embed full Orders by default; Orders reference User SUMMARY; listing via `me/orders` or `users/{user}` (admin) with pagination.
- Inventory internal quantities never embedded in public Product; availability is limited signal (`IN STOCK`/`LOW STOCK`/`MADE TO ORDER`).

## 6. Internal-Only Relationships Not Exposed
The following backend relationships exist but have no public API nesting:
- `Product/Variant → Inventory` internal stock (`physical/reserved`) — internal only, customer sees `Availability`.
- `Order → internal stock reservation` — backend implementation, not API.
- `Payment → provider credentials/configuration` — internal only, deferred to Group H.
- `User → credential internals` — never exposed via user/profile paths.

## 7. Self-Context and Admin Context
- Customer self-service prefers `/api/v1/me/*` for own resources (ownership implicit, no arbitrary user IDs).
- Admin user management may still use `/api/v1/users` / `/api/v1/users/{user}` for traversal of all users, subject to authorization. Do not mix `/api/v1/me` self-service with `/api/v1/users/{id}/profile` for ordinary customer flow.
