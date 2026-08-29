# Nested Resource Candidates — Version 1

## 0. Purpose
Lists relationships that may later be expressed as nested API resources (subresources). Candidates only; no URL paths, HTTP methods, or schemas defined. Decision for nesting vs top-level vs embedded vs linked deferred to Phase 1.9/endpoint design, but this inventory guides it.

## 1. Principles (Phase 1.7 §53)
- **Nest when:** child cannot exist outside parent; managed through parent; naturally part of parent representation.
- **Reference when:** target has independent lifecycle; reused by multiple resources; embedding would cause excessive data.
- **Keep internal when:** backend-only, sensitive, or no customer business reason.

## 2. Candidates for Nesting (Subresources)

| Candidate | Source → Target | Rationale |
|---|---|---|
| Product Images under Product | `Product → Product Images` | Images belong to Product, no independent business meaning outside Product; managed via Product. Customer needs images with Product; avoid top-level `Product Images` collection for customers. |
| Product Variants under Product | `Product → Product Variants` | Variant must remain bound to Product (VAR-001); not independently purchasable outside Product. Admin may need variant management under Product. |
| Cart Items under Cart | `Cart → Cart Items` | Items owned by Cart, ephemeral, holder-scoped; no cross-cart identity. Naturally nested. |
| Order Items under Order | `Order → Order Items` | Items are immutable snapshots owned by Order; never top-level `Order Items` collection. Embedded + subresource read via Order. |
| Order Addresses under Order | `Order → Order Addresses` | Billing (exactly 1) and delivery (0..1 conditional) are transaction snapshots owned by Order; never standalone. |
| Delivery/Fulfillment under Order | `Order → Delivery` | 0..1 conditional on DELIVERY; fulfillment is order-associated, not independently addressable by customers. |
| Order Tracking under Order | `Order → Tracking` | Read-oriented timeline projection; access via Order. |
| Status History under Order/Tracking | `Order → Tracking → Status History` or `Order → Status History` | Append-only audit; staff/admin append via controlled actions; customer read-only. Nest under Order to enforce ownership. Choose one canonical path (tracking includes history vs separate status-history) to avoid duplicate endpoints. |
| Payment under Order | `Order → Payments` | Payment(s) associated with Order (one-to-many over retries); customer limited summary via Order. Provider integration separate but relationship is order-associated. Consider nested for order-scoped payment views; top-level `payments` may be used for webhook/internal only. |
| Request Attachments | `Made-to-order Request → Attachments` | Polymorphic owner is Request; inherit Request authorization. Never global `Attachments` collection without owner context. |
| Enquiry Attachments | `Enquiry → Attachments` | Same as Request attachments — never global `Attachments` collection. |
| Notifications under User | `User → Notifications` | Private to recipient; nested under authenticated user/profile for customer inbox. |

## 3. Candidates for Independent Addressing (Top-Level Resources)

| Resource | Reason for Independent Identity |
|---|---|
| Categories | Stable public identity (slug), SEO pages, browsable independently of any Product. |
| Products | Stable public identity (slug/SKU), catalog core, independent lifecycle. |
| Carts (holder-scoped) | Temporary but addressable as holder's current cart (`current` cart concept). Still holder-scoped, not globally listable. |
| Orders | Independent lifecycle (status machine), own reference `OD-…`, authorization boundary; listed under User but also addressable as top-level Orders collection by order identity with ownership check. |
| Payments | May need independent webhook/internal handling; but customer view is via Order. Keep order-associated. |
| Made-to-order Requests | Independent lifecycle (lead states), first-class; anonymous create, owner/staff traversal. |
| General Enquiries | Same as Requests, separate domain. |
| Users / Profile | Independent identity; admin manages users. |

## 4. Not Normally Standalone (Avoid Top-Level)
- Product Image — always via Product.
- Product Variant* — conceptually owned by Product; may be addressable for admin/internal as nested `Product → Variants` under Product but not as global `Variants` collection. (*If later required, still nested.)
- Cart Item — always via Cart.
- Order Item — always via Order; no top-level `Order Items` collection.
- Order Status History — always via Order/Tracking; no top-level `Status History` collection.
- Attachment — always via owning Request or Enquiry; no global `Attachments` access without owner context.
- Order Address — always via Order; no standalone address book (ADDR-001 deferred).

## 5. Over-Fetching / Under-Fetching Considerations
- **Product** should embed image representations and variant summaries + availability + category summary to support product-detail UI without chain of requests, but must not embed full `Category → Products` recursion or `Order` data.
- **Order** should embed items + addresses + limited payment summary + delivery + tracking summary to be self-explanatory six months later (historical-data-rules), without requiring separate calls for each child, but tracking history can be paginated subresource if large.
- Availability is **limited signal** embedded; full Inventory is never embedded for customers.
- Staff operational traversal may fetch fuller Inventory/Payment details via nested subresources with elevated auth.

## 6. Decision Pending
Final nesting vs referencing vs embedding decisions will be made in Phase 1.9 (Endpoint Naming) with URL design, balancing targeting vs convenience, and will be documented in endpoint contracts (1.11+).

