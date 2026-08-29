# Relationship Security Review — Version 1

## 1. Purpose
Documents ownership boundaries, privacy risks, circular-reference risks, overexposure risks, and anonymous-user implications for relationships. No implementation code.

## 2. Ownership Boundaries & Authorization
- **Cart:** Holder-scoped (`GUEST_TOKEN` bearer vs authenticated User). One active Cart per holder (DM-DEC-04). No staff/admin traversal; binding on authentication merges guest to User. Backend must enforce holder check for every Cart/Item operation; checkout requires authenticated principal only.
- **Orders:** `User → Orders` and `Order → Order Items/Addresses/Payment/Delivery/Tracking` are ownership boundaries. Customer may traverse only own Orders (AGENTS.md §17). Staff/Admin traverse all via operational scope per Group D, but still backend-authorized, never frontend route guard alone.
- **Requests/Enquiries:** Optional User ownership. Authenticated → own; anonymous → no User but contact info. Staff/Admin traverse all as operational leads (sales). Customer cannot traverse others' leads.
- **Notifications:** Strictly `User → Notifications` recipient-only; no cross-user query.
- **Attachments:** `Request/Enquiry → Attachments` inherits owner authz; attachment cannot be fetched via global `GET /attachments/{id}` without providing owning request/enquiry context and passing owner/role check. Serves with owner check, not public URL.

## 3. Privacy Risks & Mitigations
- **Cart contents** — PRIVATE, holder-scoped. Risk: token leakage allows cart hijack. Mitigation: `GUEST_TOKEN` is bearer; treat as credential-like, short-lived/abandoned-cart cleanup, never in URL query logs; rotate on auth binding.
- **Order data** — PRIVATE, owner-scoped + staff operational. Risk: order enumeration via `OD-…` reference. Mitigation: order reference classified PRIVATE (api-exposure-classification.md); `Order → Tracking` customer projection excludes `Operational note`; full history with notes is `STAFF/ADMIN` only. Delivery fee/address projections equal canonical Order snapshots, not independently editable.
- **Order Status History** — `INTERNAL/PRIVATE` for actor/note. Risk: staff note leakage. Mitigation: customer exposure is projection without `Operational note`; internal actor/source exposed only as customer-understandable type, not raw staff identity.
- **Inventory quantities** — `INTERNAL_ONLY` for `physical/reserved`. Risk: competitor inventory inference via near-exact indicator. Mitigation: customer relationship is `Product/Variant → Availability` derived qualitative signal only (`available` bool + `stock_indicator` as coarse bucket `IN STOCK`/`LOW STOCK`/`MADE TO ORDER`, never exact quantity like `Only 2 left`), never quantities.
- **Payment** — `SENSITIVE` for secrets. Risk: provider secrets via `Order → Payment`. Mitigation: `Order → Payment` limited summary for customer (status/amount/currency/verification_state without secrets); full verification internals are `INTERNAL_ONLY`, staff/admin operational with separate scope.
- **Requests/Enquiries/Attachments** — PRIVATE with anonymous creation. Risk: anonymous creation endpoint abused to enumerate private leads. Mitigation: anonymous may `Create` only (`ANONYMOUS_CREATE`), not `Read/List`; read requires owner (authenticated linked) or Staff/Admin. Rate limiting applicable (AGENTS.md §18).
- **User/Profile** — `PRIVATE/SENSITIVE`. Risk: credential exposure via `User → credential internals`. Mitigation: `User ↔ Authentication` internal-only; profile never exposes credential hash, reset secrets, role via public read.
- **Contact information** (phone/email in Request/Enquiry, recipient phone in Order Address) — PRIVATE. Risk: contact harvest via relationship traversal. Mitigation: contact exposed only to owner and staff/admin operational, never via public catalog.
- **Delivery address** — PRIVATE, owner-scoped. Risk: address enumeration. Mitigation: `Order → Order Addresses` embedded inside Order, not globally listable; address is transaction snapshot inside owned Order only.

## 4. Circular-Reference Risks
- **Conceptual cycles are valid but must not be recursively embedded:**
  - `User → Orders → Order → User` — Order should embed at most User `SUMMARY` (id/name), never full User with orders list.
  - `Product → Category → Products → Category…` — Product embeds Category `SUMMARY`; Category does not embed all Products `FULL` (use SUBRESOURCE with pagination and product summaries).
  - `Order → Order Item → Product (current) → Product variants/images` — Order Item's optional current Product reference is `SUMMARY/REFERENCE` only, not full Product with images/variants to avoid deep nesting.
- **Rule:** Related resources should not recursively embed full parent objects indefinitely (Phase 1.7 §40). Use `SUMMARY` or `REFERENCE` for back-links; provide links or separate subresource fetches for full detail.

## 5. Overexposure Risks
- **Internal relationships never exposed to customer:**
  - `Product/Variant → Inventory` quantities, `Order → internal stock reservation`, `Payment → provider credentials/configuration`, `User → credential hash / reset secrets`, `Inventory → internal adjustments` — all `INTERNAL_ONLY`. Even though backend stores them, they have no customer-facing API relationship.
- **Excessive embedding:**
  - Product must not automatically embed `all Orders`, `all Reviews`, `all inventory movements`.
  - Category must not embed all Products `FULL` (use SUBRESOURCE).
- **Sensitive fields via relationship:**
  - `Order → Payment` must be limited summary for customer; staff/admin see more but still not raw provider secrets.
  - `Order → Order Addresses` for PICKUP must not require delivery address (conditional).

## 6. Anonymous-User Implications
- **Anonymous allowed to create** (create-only, no read/list): Made-to-order Requests, General Enquiries, and Registration/Login. These use `ANONYMOUS_CREATE`. Anonymous cannot list or read others' leads.
- **Anonymous cart:** Guest holder via `GUEST_TOKEN` may create/read/update own Cart/Items, but Cart is not globally public and is holder-scoped. No staff traversal.
- **Public catalog anonymous read** (Category, Product, Images, Variants, Availability limited signal) requires no User relationship; public relationships are authentication-free and SEO-friendly (Next.js server-rendered per AGENTS.md §9).
- **Anonymous cannot traverse commerce:** No anonymous `User → Orders`, `Order → Payment`, `Order → Tracking`, `User → Notifications`. Checkout is authenticated-only.
- **Anonymous Request/Enquiry with optional Product:** When anonymous submits from product page, `Request → Product` optional reference is allowed, but does not imply Request owns Product or vice versa; it's a reference only.

## 7. Authorization Through Relationships (Summary)
- Customer traversal of ownership relationships (`Orders`, `Notifications`, linked `Requests`/`Enquiries`, `Cart` holder) requires backend ownership check per relationship.
- Staff/Admin traversal of operational relationships (`Inventory`, full `Payment`, full `Status History`, all `Orders/Requests/Enquiries`) requires role check per Group D; same core resources, elevated representation, not duplicate `AdminOrder` resources.
- Frontend route protection is never final authority (AGENTS.md §17).

