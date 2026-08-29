# Entity Relationship Matrix — Version 1 (Logical)

## 1. Purpose

Conceptual relationships and cardinalities between Version 1 entities. These are **logical** relationships; physical foreign keys, join strategies, and constraints are decided in the physical database design phase.

## 2. Relationship Matrix

| Entity | Belongs to / references | Cardinality | Requirement |
|---|---|---|---|
| User | Role | Many-to-one | Required |
| Category | Products | One-to-many | Required relationship (Product → Category) |
| Product | Category | Many-to-one | Required |
| Product Image | Product | Many-to-one | Required when images exist |
| Product Variant | Product | Many-to-one | Required when variants exist |
| Inventory | Product or Variant | One-to-one (per product/variant) | Required for purchasable items |
| Cart | User (authenticated) or guest token | Many-to-one (User) / one-to-one (guest) | Conditional |
| Cart Item | Cart | Many-to-one | Required |
| Cart Item | Product / Variant | Many-to-one | Required |
| Order | User (Customer) | Many-to-one | Required |
| Order Item | Order | Many-to-one | Required |
| Order Item | Product / Variant (traceability) | Many-to-one (optional) | Referential trace |
| Order Address (billing) | Order | 0..1 | Conditional — at most one billing address per order |
| Order Address (delivery) | Order | 0..1 | Conditional — at most one delivery address per order; present only when fulfillment = DELIVERY, absent for PICKUP |
| Order Status History | Order | Many-to-one | Required |
| Payment | Order | One-to-many | Defined by payment lifecycle |
| Delivery | Order | One-to-one (when DELIVERY) | Conditional |
| Made-to-order Request | User | Many-to-one (optional) | Optional reference |
| Made-to-order Request | Product | Many-to-one (optional) | Optional reference |
| General Enquiry | User | Many-to-one (optional) | Optional reference |
| Attachment | Made-to-order Request or General Enquiry | Many-to-one (polymorphic owner) | Optional |
| Notification | User | Many-to-one | Required |

## 3. Cardinality Notes

- **User ↔ Cart:** A User may have carts across devices/sessions; guest carts exist without a User and are associated upon authentication (IDENT-008/CART-002).
- **Product ↔ Variant:** A product may have zero variants (directly purchasable) or many variants; when variants exist, the variant is the purchasable inventory unit (VAR-005).
- **Inventory ↔ Product/Variant:** Inventory attaches to the purchasable unit — a product without variants, or each variant when variants exist.
- **Order ↔ Payment:** An order may have one or more payment records (e.g., retries/failures); payment state is separate from order state (PAY-001).
- **Order ↔ Delivery:** Delivery exists only when fulfillment type is `DELIVERY` (conditional). Delivery fee, recipient, phone, and delivery address on Delivery are **non-authoritative projections** of the canonical Order snapshots (Order fee; Order Address delivery) — they are copied once at Delivery creation and never edited independently.
- **Order Address:** At most one billing address and at most one delivery address per order (0..1 each); billing and delivery are distinct (ADDR-003). A delivery address is present only when fulfillment = DELIVERY and is absent for PICKUP; this prevents duplicate transaction addresses and keeps order data unambiguous. For a delivery order, Order Address (delivery) is the canonical owner of the delivery address/recipient/phone snapshot.
- **Attachment owner:** An attachment belongs to exactly one request OR one enquiry (polymorphic ownership in logical terms).

## 4. Cross-Cutting Relationship Rules

- Historical traceability: Order Item references Product/Variant but preserves all display facts as snapshots (ORD-005).
- Requests/Enquiries may exist without any User (anonymous) — no mandatory User relationship (REQ-001, ENQ-001).
- Notifications reference application events/entities but do not drive the order state machine (NOTIF-001).
- Saved address book does not exist; addresses are order transaction data (ADDR-001).