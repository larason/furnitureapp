# Resource Embedding Policy — Version 1

## 0. Purpose
For important relationships, classifies whether the target should usually be `FULL`, `SUMMARY`, `REFERENCE`, `SUBRESOURCE` (fetch via subresource), or `INTERNAL ONLY` (never customer-facing). No JSON schemas defined; policy guides later response shapes.

Legend: `FULL` — complete representation embedded; `SUMMARY` — small summary embedded; `REFERENCE` — identifier + link/reference (id/slug) without full embed; `SUBRESOURCE` — available through dedicated subresource, not embedded by default; `INTERNAL ONLY` — never exposed to customer; `LIMITED SUMMARY` — reduced summary for privacy.

## 1. Policy Table

| Relationship | Recommended Exposure | Rationale |
|---|---|---|
| **Product → Category** | SUMMARY | Product embeds category `id/slug/name` summary; full Category via `Category → Products` separately. Prevents `Product → Category → Products → Category` recursion. SEO-friendly. |
| **Category → Products** | SUBRESOURCE + SUMMARY | Category representation does not embed all Products by default; provide product count or paginated summaries via subresource. Avoids over-fetching. |
| **Product → Product Images** | FULL (for customer) | Images are essential to product-detail UI; embed image objects (`location/reference, display_order, primary, alt_text`) inside Product. Admin manages via subresource under Product. |
| **Product → Product Variants** | SUMMARY (inside Product) + SUBRESOURCE for detail | Product embeds variant summaries (`id/name/SKU/price_override/active, attributes`) to choose variant; full variant detail manageable via subresource if needed. Variant never standalone global. |
| **Product/Variant → Availability** | SUMMARY (embedded) | Embed limited qualitative signal (`available` bool, `stock_indicator` as coarse bucket: `IN STOCK` / `LOW STOCK` / `MADE TO ORDER`, `product_type`) inside Product/Variant. Never include exact quantity (no `Only 2 left`) or `physical/reserved` quantities. Approved buckets prevent stock inference. |
| **Product/Variant → Inventory** | INTERNAL ONLY (customer) / FULL for Staff/Admin | Customer: no inventory exposure except Availability. Staff/Admin: full quantities (`physical, reserved, available derived, updated_at`) via Inventory subresource/operation. |
| **User → Cart** | REFERENCE + SUBRESOURCE | User does not embed full Cart; Cart is holder-scoped current resource fetched via Cart subresource. Guest holder uses `GUEST_TOKEN`. |
| **Cart → Cart Items** | FULL (embedded) | Cart embeds items (`product_id/variant_id, product_name/slug/price at add time non-authoritative, quantity`) — cart is not useful without items. Still allow subresource item operations. |
| **Cart Item → Product/Variant** | REFERENCE (SUMMARY) | Cart Item references Product/Variant via summarized reference (`id/slug/name/price` at add time); not full Product embed (avoid duplication and stale). Full Product fetch separate if needed. |
| **Cart → Checkout** | INTERNAL workflow (not embedded) | Checkout is ephemeral workflow from Cart; not embedded, creates Order. No direct embed. |
| **User → Orders** | SUBRESOURCE | User does not embed full Orders; Orders listed via `User → Orders` subresource with ownership check; paginated. |
| **Order → Order Items** | FULL (embedded) | Order must be self-explanatory historically; embed full snapshots (`product_name_snapshot, SKU, unit_price_snapshot, quantity, line_subtotal`). Optional current catalog reference as lightweight `product_reference` id/slug, not full current Product. |
| **Order Item → Product (current)** | REFERENCE (optional) | Only if product still exists/archived; otherwise snapshot alone. Historical snapshot is authoritative. |
| **Order → Order Addresses** | FULL (embedded) | Billing (exactly 1) and delivery (when DELIVERY) snapshots embedded inside Order for historical clarity; no separate fetch required for order view. |
| **Order → Payment** | LIMITED SUMMARY + SUBRESOURCE | Order embeds limited payment summary for customer (`payment_status, amount, currency, verification_state` without secrets); full payment details for Staff/Admin via Payment subresource. No provider credentials/secrets ever embedded. |
| **Order → Delivery/Fulfillment** | FULL (when DELIVERY) | Delivery details (`fulfillment_type, delivery_fee projection, recipient/phone/address projections, delivery_status/notes`) embedded inside Order when `fulfillment=DELIVERY`; absent for PICKUP. Projections equal Order canonicals. |
| **Order → Tracking** | SUMMARY + SUBRESOURCE | Order embeds tracking summary (`current_status, fulfillment milestones`) and link to full timeline; full history via Tracking subresource to allow pagination if needed, but small history may be embedded. |
| **Tracking → Status History** | FULL for Staff/Admin; SUMMARY projection for Customer | Customer: summary rows without `Operational note`; Staff/Admin: FULL rows with `previous/new status, timestamp (backend), actor, note`. History is append-only. |
| **Made-to-order Request → Product** | REFERENCE (optional) | If from product page, embed Product summary (`id/slug/name`); custom request has no product → null. |
| **Made-to-order Request → User** | REFERENCE (optional) | If authenticated, embed User summary (`id/name`); anonymous → contact info instead, no User. |
| **General Enquiry → User** | REFERENCE (optional) | Same as Request. |
| **Request → Attachments** | SUBRESOURCE | Request does not embed full file content by default; provide attachment summaries (`file_reference, content_type, size, original_name`) or count, with link to subresource for download with owner authz. Avoid global embed. |
| **Enquiry → Attachments** | SUBRESOURCE | Same as Request. |
| **User → Notifications** | SUBRESOURCE | User does not embed all Notifications; notifications via subresource inbox, paginated, recipient-only. |
| **User ↔ Authentication** | INTERNAL ONLY | Credentials, hashes, reset secrets, provider internals never embedded or exposed. Separate auth operations. |

## 2. Preventing Circular Embedding
- Product embeds Category SUMMARY, not full Category with products.
- Category does not embed full Products; Product does not embed full Order history.
- Order embeds Order Items FULL, but Order Item's optional current Product reference is SUMMARY/REFERENCE only, not full Product with its variants/images.
- User embeds no Orders by default; Orders reference User SUMMARY.

## 3. Over/Under-Fetching Balance
- **Product detail** embeds Images + Variant summaries + Availability + Category summary → single call supports product-detail UI (VISION.md three actions) without chain.
- **Order detail** embeds Items + Addresses + Delivery (if DELIVERY) + limited Payment summary + Tracking summary → order is self-explanatory without 5 calls; full history can be subresource if paginated.
- **Category browsing** does not embed all Products FULL; use subresource with pagination.
