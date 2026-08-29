# Logical Data Model Review — Version 1 (Phase 1.5)

## 1. Reviewed Artifacts

### Authoritative inputs

- `docs/VISION.md` (project vision; reviewed, consistent with the model)
- `AGENTS.md` (governing project guide, project root; the phase instructions refer to it as `agent.md`)
- `phase-1.1-api-scope.md` + `api-scope-v1.md`
- `phase-1.2-domain-nouns.md` + `domain-glossary.md`, `domain-boundaries.md`, `capability-matrix.md`, `domain-decisions.md`, `open-domain-questions.md`
- `phase-1.3.md` + `domain-invariants.md`, `business-rules.md`, `order-state-rules.md`, `testable-invariants.md`, `domain-conflicts.md`
- `phase-1.4.md` + `logical-data-model.md`, `entity-relationship-matrix.md`, `data-classification.md`, `data-ownership.md`, `historical-data-rules.md`, `data-integrity-requirements.md`, `data-model-decisions.md`, `data-model-open-issues.md`, `api-exposure-classification.md`

---

## 2. Workflow Coverage

Each Version 1 workflow was traced end-to-end through the logical model. All are representable.

| Workflow | Starting entity | Entities created/changed | Historical data | Anonymous-capable | Auditable |
|---|---|---|---|---|---|
| Anonymous browsing | — (no entity) | Reads Category, Product, Image, Variant, derived availability | n/a | Yes | n/a |
| Authenticated purchase | Cart → Checkout | Order, Order Item, Payment, Order Status History, Delivery (if applicable), Inventory (reserve) | Order Item snapshots, totals | No (checkout requires User) | Yes (status history) |
| Pickup order | Order | Order (fulfillment=PICKUP), Order Item(s), Status History, Payment | Snapshots; no delivery address | No | Yes |
| Delivery order | Order | Order (DELIVERY), Delivery entity, Order Address (delivery), Status History, Payment | Delivery fee snapshot, delivery address snapshot | No | Yes |
| Customer cancellation | Order | Order → CANCELLED; Status History entry; Inventory (release reservation) | CANCELLED transition recorded | No (self-service) | Yes |
| Order tracking | Order | Reads Order + Order Status History | Timeline (append-only) | No (own orders only) | Yes |
| Made-to-order request | Request | Request, optional Attachment | Request history (retained) | Yes | Yes (status) |
| Anonymous MTO request | Request (no User) | Request with contact info, optional Attachment | — | Yes | Yes |
| Authenticated MTO request | Request (User) | Request with User, optional Attachment | — | No (needs account) | Yes |
| General enquiry | Enquiry | Enquiry, optional Attachment | Enquiry retained | Yes | Yes (status) |
| Anonymous enquiry | Enquiry (no User) | Enquiry with contact info | — | Yes | Yes |
| Authenticated enquiry | Enquiry (User) | Enquiry with User | — | No | Yes |
| Inventory depletion | Inventory | Inventory quantities decrease; reservation/release | Reserved for lightweight audit (deferred) | n/a | Yes (via invariant) |
| Product changes after purchase | Order Item (snapshot) | None (snapshot immutable) | Snapshots survive rename/repricing/deactivation | n/a | Yes |
| Payment lifecycle | Payment | Payment status changes (backend-verified); Order status follows | Payment history retained | n/a | Yes |
| Admin/staff order operations | Order | Order status transitions; Status History; Delivery updates | Actor recorded | n/a | Yes |

**Result:** Every Version 1 workflow is representable using the logical data model. No workflow requires a missing entity.

---

## 3. Entity Coverage

All 19 Version 1 entities have documented logical data requirements: User, Role, Category, Product, Product Image, Product Variant, Inventory, Cart, Cart Item, Order, Order Item, Order Address, Order Status History, Payment, Delivery, Made-to-order Request, General Enquiry, Attachment, Notification.

Supporting concepts that are configurable but intentionally not persisted as Version 1 data entities are documented as deferred implementation details (Delivery Fee Rule / Delivery Region / Location — Phase 1.13/3.13; Delivery Fee value itself is a snapshot on Order).

---

## 4. Relationship Review

Review of the cardinality matrix (`entity-relationship-matrix.md`) against the workflows:

| Relationship | Cardinality | Required? | Verdict |
|---|---|---|---|
| User → Role | Many-to-one | Required | Correct after review correction (see §8) |
| Category → Product | One-to-many | Required | Correct |
| Product → Image / Variant | One-to-many | Required when exist | Correct |
| Inventory → Product/Variant | One-to-one (purchasable unit) | Required for purchasable | Correct |
| Cart → User / guest | Many-to-one (User) / one-to-one (guest) | Conditional | Correct (guest cart decision) |
| Cart → Cart Items | One-to-many | Required | Correct |
| Order → User (customer) | Many-to-one | Required | Correct |
| Order → Order Items | One-to-many | Required | Correct |
| Order → Status History | One-to-many | Required | Correct |
| Order → Payment | One-to-many (retries/failures) | Conditional | Correct (PAY-001) |
| Order → Delivery | One-to-one (when DELIVERY) | Conditional | Correct |
| Order → Address (billing/delivery) | 0..1 each | Conditional | Correct (ADDR-003) |
| Request → User / Product | Many-to-one (optional) | Optional | Correct (anonymous allowed) |
| Enquiry → User | Many-to-one (optional) | Optional | Correct |
| Attachment → Request/Enquiry | Many-to-one (polymorphic owner) | Optional | Correct |

No ambiguous relationships found.

---

## 5. Historical-Data Review

The four conceptual scenarios from the phase instructions all pass:

| Scenario | Requirement | Model support | Verdict |
|---|---|---|---|
| A — Product renamed | Old order shows original name | Order Item product name snapshot | Passes |
| B — Product repriced | Old order retains original price | Order Item unit price + line subtotal snapshot | Passes |
| C — Product deactivated | Historical orders still display item | Snapshots independent of active state; archival/soft-delete policy for referenced products/variants | Passes |
| D — Variant changed | Historical order identifies what was purchased | Order Item variant reference + SKU snapshot | Passes |

Historical immutability is documented for: order reference, order item purchased price/name/reference/quantity, final total, final delivery fee, transaction addresses, payment transaction reference (`historical-data-rules.md` §3).

---

## 6. Security/Privacy Review

Privacy classifications are explicit for all important data (`data-classification.md`) and API exposure is explicit (`api-exposure-classification.md`).

| Data | Classification | Verdict |
|---|---|---|
| Customer email / phone | PRIVATE | Correct |
| Password credentials | SENSITIVE (hash only) | Correct |
| Payment data / provider reference | SENSITIVE | Correct |
| Staff operational notes | INTERNAL/PRIVATE; owner timeline excludes Operational note | Correct (fixed in prior phase) |
| Orders / order reference | PRIVATE (owner-scoped) | Correct |
| Requests / enquiries | PRIVATE | Correct |
| Product catalog data | PUBLIC | Correct |
| Inventory internals | INTERNAL | Correct |

No sensitive information remains ambiguous.

---

## 7. Complexity, Extensibility, Anti-Pattern Review

### 7.1 Complexity review

Rejected as not in Version 1 (no corresponding entities): warehouse management, multi-vendor marketplace, multi-country tax engine, complex promotion engine, multi-currency ledger, delivery fleet management, manufacturing ERP, supplier management, advanced loyalty program, enterprise workflow engine. The model contains only entities justified by Version 1 requirements.

### 7.2 Extensibility review

The model does not block obvious future growth. The following can be added later without destroying the Version 1 model: wishlist (adds a User→Product reference), coupons/promotions (adds discount concept), reviews (new entity), push notifications (extends Notification delivery), saved addresses (adds address-book entity), delivery tracking (extends Delivery), additional roles (extends Role), additional payment providers (extends Payment.provider), MTO request→order conversion (adds a link; currently absent by design).

### 7.3 Anti-pattern review

| Anti-pattern | Check | Verdict |
|---|---|---|
| God entity | No entity mixes unrelated domains | Passes |
| Generic entity | No `data/records/metadata/activities/communications` tables | Passes (DM-DEC-09) |
| Accidental coupling | Enquiry/Request do NOT require User (anonymous approved) | Passes |
| Historical corruption | Order Item snapshots isolate current catalog changes | Passes |
| Frontend authority | Prices/stock/totals never client-authoritative | Passes |
| Premature enterprise complexity | No enterprise subsystems | Passes |

---

## 8. Issues Found and Corrections Made

The following findings surfaced during the Phase 1.5 review. Corrections were applied to the Phase 1.4 documents in the review cycle preceding this phase. Each is classified per Phase 1.5 §46.

| # | Severity | Location | Finding | Correction | Class |
|---|---|---|---|---|---|
| 1 | Major | `data-integrity-requirements.md` §6 | Inventory reservation had no explicit invariant (reject above `physical − reserved`; `0 <= reserved <= physical`; release paths) | Added reservation invariant and release on cancellation / timeout / permanent payment failure | B (structural logical correction) |
| 2 | Minor | `phase-1.4.md` §18 | `Discount where applicable` implied a discount concept not in V1 | Marked discount as deferred/not in Version 1 | A (clarification) |
| 3 | Major | `data-classification.md` §3 | Order reference classified PUBLIC (enumeration risk) | Reclassified PRIVATE, owner-scoped; AUTHENTICATED (owner)/STAFF/ADMIN | B (structural logical correction) |
| 4 | Major | `entity-relationship-matrix.md` §2 | User–Role modeled one-to-one (role per user) | Corrected to many-to-one (one role, many users) | B (structural logical correction) |
| 5 | Major | `logical-data-model.md` Order Item | Hard-delete of referenced products/variants would dangle REQUIRED references | Require archival/soft-delete or tombstone for referenced products/variants; snapshots remain authoritative | B (structural logical correction) |
| 6 | Major | `phase-1.4.md` §51 | `api-exposure-classification.md` not listed as a Phase 1.4 deliverable | Added as Deliverable I, identified as canonical exposure matrix | A (clarification) |
| 7 | Major | `api-exposure-classification.md` §2 | Guest cart labeled `AUTHENTICATED`, conflating guest bearer token with user session | Introduced `GUEST_TOKEN` (holder) audience distinct from `AUTHENTICATED` (owner); referenced DM-DEC-04 | B (structural logical correction) |
| 8 | Major | `api-exposure-classification.md` §2 | Owner timeline granted full status-history entries (staff Operational note leak) | Owner projection excludes Operational note; `STAFF`/`ADMIN` only | B (structural logical correction) |
| 9 | Major | `logical-data-model-v1.md` / `logical-data-model.md` Order + Delivery | Order.Delivery fee and Delivery.Delivery fee both snapshots, no canonical owner | Order is canonical owner of the delivery-fee snapshot; Delivery carries a non-authoritative projection (equal, never edited independently) | B (structural logical correction) |
| 10 | Major | `logical-data-model-v1.md` / `logical-data-model.md` Order Address + Delivery | Delivery address stored in both Order Address and Delivery with no canonical owner/sync rule | Order Address (delivery) is canonical owner of the address/recipient/phone snapshot; Delivery holds non-authoritative operational projections | B (structural logical correction) |
| 11 | Documentation | `phase-1.4.md` / `domain-conflicts.md` | Payment provider group label G vs H | Aligned to AGENTS.md governing label (Group H); discrepancy recorded in `domain-conflicts.md` | A (clarification) |

No Class C (business rule change) or Class D (new Version 1 feature) items were introduced during review.

---

## 9. Deferrals Confirmed (non-blocking)

These remain deferred exactly as decided and do not block freeze:

- Payment provider selection and provider-specific fields — Phase Group H
- Email/push/SMS notification delivery — Phase Group R
- Staff/admin permission matrix — Phase Group D
- Saved address book — deferred
- Attachment storage/upload implementation — later phase
- Delivery Fee Rule / Region / Location physical representation — Phase 1.13 / Phase 3.13 (the fee value snapshot is already in the model)
- Exhaustive order-transition matrix — later order-state contract phase
- Inventory movement-history subsystem — deferred (DM-DEC-05)
- Business retention periods — pending policy definition

---

## 10. Final Status

| Check | Status |
|---|---|
| Every Version 1 workflow representable | Pass |
| Every major business rule has data support | Pass |
| Anonymous browsing independent of authentication | Pass |
| Anonymous requests supported | Pass |
| Anonymous enquiries supported | Pass |
| Checkout account-required | Pass |
| Product types represented correctly | Pass |
| Inventory responsibility clear | Pass |
| Cart and order separate | Pass |
| Historical order facts protected | Pass |
| Order status history supported | Pass |
| Pickup and delivery distinct | Pass |
| Variable delivery fees supported | Pass |
| Payment and order state separate | Pass |
| Cancellation timing representable | Pass |
| Requests and enquiries separate | Pass |
| Optional attachments supported | Pass |
| Saved addresses deferred | Pass |
| Payment provider deferred (Group H) | Pass |
| Email delivery deferred (Group R) | Pass |
| Detailed permissions deferred (Group D) | Pass |
| Sensitive/private data classified | Pass |
| Business fact ownership unambiguous | Pass |
| Unnecessary duplication removed | Pass |
| Speculative complexity rejected | Pass |
| Future extensibility reviewed | Pass |
| No unresolved contradictions | Pass |
| Model formally frozen | See `freeze-record.md` and `logical-data-model-v1.md` |

**Verdict:** The logical data model is **ready to freeze** as Version 1.0.