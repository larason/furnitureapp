# Resource Future Candidates — Version 1 (Deferred)

## 1. Purpose
Records features/resources explicitly deferred from Version 1. They are not part of the frozen API resource inventory. They should not be introduced as Version 1 resources merely because they may exist later. Each may be added without destroying the Version 1 model (Phase 1.5 extensibility review), but requires a new phase, business decision, and API contract.

Source: `AGENTS.md §26 Phase Group W`, Phase 1.4 deferrals, `logical-data-model-v1.md` §9.

## 2. Deferred Resources / Features

| Candidate | Description | Dependency / Trigger |
|---|---|---|
| **Wishlist** | Customer-saved products for later | Requires User→Product saved relation; Phase Group W |
| **Reviews / Ratings** | Customer reviews on products | New Review entity; moderation; Phase Group W |
| **Coupons / Promotions** | Discounts, promotions, codes | Discount engine; pricing Phase Group G extension |
| **Advanced delivery zones** | Sophisticated zone/distance pricing | Extension of staff-controlled fee rules (current rule is simple region/location) |
| **Customer segmentation** | Segmentation for marketing | Analytics; Group W |
| **Analytics dashboards** | Business analytics | Reporting layer; Group W |
| **Recommendations** | Product recommendations | Data/analytics; Group W |
| **Loyalty features** | Points, tiers | Customer program; Group W |
| **WhatsApp / SMS integration** | Alternative notification channels | Group R extension (beyond email/push) |
| **More sophisticated inventory** | Multi-warehouse, movement history | Inventory movement-history subsystem (DM-DEC-05 deferred) |
| **Manufacturing workflow** | Production planning for made-to-order | Beyond lead-only requests (Decision 20); separate workflow |
| **Delivery-agent app** | Courier/driver tracking, route optimization | GPS/real-time tracking (AGENTS.md §4.3/§15 deferred) |
| **Saved Addresses (address book)** | Persistent customer address book | Currently addresses are transaction-time snapshots on Order (ADDR-001 deferred) |
| **Additional Payment Providers** | Extra gateways beyond first provider | Group H provider-agnostic already supports extensibility |
| **Multi-vendor marketplace** | Third-party sellers | Explicitly out-of-scope (AGENTS.md §26) |
| **Warehouse-management / ERP** | Enterprise inventory/warehouse | Explicitly out-of-scope |
| **Multi-currency ledger** | Beyond single-currency TZS | Deferred; Version 1 is TZS only (1.2 Decision 16) |
| **Reviews moderation / UGC** | Moderation for reviews | Depends on Reviews |
| **Push Notifications infrastructure** | Real push delivery | Group R (Notification delivery) |
| **Background jobs / queues** | Async processing at scale | Deferred until volume requires (api-scope-v1.md) |

## 3. Items Not Deferred (In V1)

For contrast, these are **in** Version 1 and not future candidates: Category/Product/Variant/Image, Availability (limited signal), Inventory (staff), Cart/CartItem, Checkout workflow, Order/OrderItem/OrderAddress/Delivery/Payment, Tracking/Status History, Request/Enquiry/Attachment, User/Profile, Notification (in-app record), Authentication operations.

## 4. Rules for Future Addition

- Must not be introduced in Version 1 without a new business decision and API contract phase.
- Must be evaluated for ownership (data-ownership.md), lifecycle, access matrix, and sensitivity before implementation.
- Must not break the frozen Version 1 model; added via change control (Phase 1.5 §49) with version increment 1.x or 2.0 as appropriate.
- Must remain small-business appropriate (AGENTS.md §2/§25) — no enterprise bloat.

## 5. Checklist Confirmation

All future candidates from Phase 1.6 §43 have been recorded as deferred here, not as Version 1 resources. Phase 1.6 validation (future features deferred) passes.
