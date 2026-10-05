# Business Rules — Version 1 (Domain Authority)

> **Authority:** This file is the plain-language source for business rules referenced by the API contract. Technical invariant IDs are in brackets; see `phases/domain-invariants.md` and `phases/business-to-data-traceability.md`. The backend (Laravel) is the authoritative enforcer of all rules below — clients (Next.js, Flutter, Admin) are advisory only for UX (Phase 1.15 Validation Conventions). Where this file and `docs/api/*` overlap, this file governs business meaning; `api-contract.md` / `api-conventions.md` govern wire representation.

## Overall Promise

The computer system is always the final authority on what is allowed, what costs what, and what is in stock. The website and mobile app help customers shop, but they can never override the system's decisions. **Frontend validation is advisory; backend/domain validation is authoritative** (API-VAL-002).

---

## Note-As of 2026-09-29: Initial Production Commerce Mode — Request Only. The first production release publishes only MADE_TO_ORDER products. Normal Cart→Checkout→Payment→Order purchasing remains disabled until business registration, payment-provider onboarding, and the deferred Groups G/H/I prerequisites are completed. Customers express purchase intent through the Made-to-Order Request flow. This is a deployment-scope decision, not removal of the frozen V1 commerce contracts.


## 1. Browsing the Catalog

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can open the website or app and browse the catalog, look at categories, search for furniture, open product pages, view images, prices, and availability **without logging in**. | IDENT-001/003, CAT-PUB-001 |
| 2 | Browsing, searching, and viewing products must never secretly require an account. | IDENT-003, CAT-PUB-002 |
| 3 | Only products and categories that are published and active (`is_active: true`) appear in public catalog listings and search results. | CAT-PUB-003, CAT-001 |
| 4 | Every product is either **In Stock** (buy it now) or **Made to Order** (request it). Closed enum `IN_STOCK` / `MADE_TO_ORDER` (CLOSED per API-VAL-003). | CAT-002, CAT-TYPE-001 |
| 5 | For "In Stock" products, the customer's main action is **Add to Cart / Buy**. | CAT-003, CART-ACT-001 |
| 6 | For "Made to Order" products, the customer's main action is **Request This Furniture** — you cannot buy it directly at checkout. Price is an informational starting estimate. | CAT-003, REQ-ACT-001 |
| 7 | Products or options that are no longer active can no longer be purchased or carted. Historical orders preserve the purchased snapshot. | CAT-004, ORD-HIST-001 |
| 8 | Every variant belongs strictly to one parent product; a variant from Product B cannot be used with Product A. | VAR-OWN-001 |

**Catalog Business Authority Note (Phase 1.20):** Public catalog reads (`CAT-001..CAT-006`) are strictly read-only and non-mutating. Public availability signals (`availability` and `stock_indicator`) are point-in-time informational reads; they do not reserve stock. Authoritative inventory verification and reservations occur exclusively at Checkout (`CHK-001`).


## 2. Stock and Availability

| # | Business rule | Ref |
|---|---|---|
| 1 | The stock number shown to customers is informative only. The system makes the real, final stock check. | Catalog availability / checkout validation |
| 2 | Available stock can never go below zero. | Inventory invariants |
| 3 | If two customers try to buy the last item at the same time, only one can succeed. The system never sells more than it has. | Inventory concurrency |
| 4 | Adding something to a cart does **not** put it aside or reserve it for you. | INV-004 |
| 5 | An item may become unavailable after a customer has looked at it; the final decision is made when they check out. | INV-005 |
| 6 | The system tracks stock as units held, units reserved, and units available to sell. | INV-006 |
| 7 | If a product has color/size/material options, stock can be tracked separately for each option. | INV-007 |

**Validation note (Phase 1.15):** Inventory validation is concurrency-sensitive and must occur inside/around a transaction (`read → validate quantity → reserve/consume`). The `SELECT then UPDATE` without locking is prohibited. Domain validation checks `product exists, active, product_type IN_STOCK, variant valid/belongs-to-product/active/purchasable, quantity valid, current stock sufficient` — current availability still matters even when `product_type=IN_STOCK`.

## 3. Accounts and Privacy

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can browse and search without an account. | IDENT-001 |
| 2 | **Checkout requires a customer account.** A visitor cannot buy without logging in. | IDENT-002 |
| 3 | A customer sees only their own profile, orders, requests, and payment information — never another customer's. | IDENT-006 |
| 4 | A visitor can create a cart without an account, and the cart moves onto their account when they log in. Checkout still requires being logged in. | IDENT-008 |
| 5 | Customers, staff, and admins share Clerk-authenticated external identity with one local Laravel account projection; Laravel roles decide what they may do. Clerk authenticates the person, while Laravel decides application authorization. | IDENT-007 |

## 4. Cart and Shopping

| # | Business rule | Ref |
|---|---|---|
| 1 | A customer's active cart belongs strictly to that customer and persists across devices and sessions. | CART-OWN-001 |
| 2 | **Cart admission (new additions):** Only a real, purchasable, published, in-stock item (`product_type: IN_STOCK && is_active && is_published`) may be added to a cart. `MADE_TO_ORDER` products cannot be added and must use the request workflow instead. | CART-001, CART-TYPE-001 |
| 3 | If a product has options/variants, the selected option must exist, be active, and belong strictly to that product. | VAR-OWN-001, CART-002 |
| 4 | Adding an item to a cart **does not reserve stock** or hold inventory. | INV-004, CART-RES-001 |
| 5 | Adding an item that is already in the cart increases the existing item's quantity rather than creating a duplicate line (up to the maximum of 100 units). | CART-MRG-001 |
| 6 | Prices and subtotals shown in the cart are display numbers. The system re-checks and recalculates all prices and stock when the customer checks out. | CART-005, PRICE-001/002 |
| 7 | **Stale cart retrieval:** After a valid item is admitted, it may later become unavailable (deactivated, unpublished, or sold out). A stale line is **not deleted** from the cart; it is retained and marked `is_purchasable: false` / `availability: "unavailable"` so the customer can see and address it. Checkout rejects any cart that contains a stale line. | CART-STALE-001 |
| 8 | An unauthenticated guest can start a cart, which automatically transfers to their customer account upon login. | IDENT-008, CART-GST-001 |
| 9 | Staff and administrators do not edit or manage customer shopping carts. | CART-SEC-001 |

**Cart Validation & Domain Authority Note (Phase 1.21, updated Phase 1.23 Model B):** Cart mutations (`CART-001..CART-005`) are customer-isolated actions. The server computes all display subtotals and line totals using minor unit arithmetic (`{amount, currency}`). Clients can only specify product identity, variant choice, and quantity (1..100). Final inventory locking occurs during Checkout (`CHK-001`); **subtotal is calculated at Checkout (`CHK-001`) and the final payment amount is finalized only after delivery-fee finalization via `ORD-014` (Model B: `CHK-001` → `subtotal` authoritative, `ORD-014` → `delivery_fee FINALIZED` → `total` authoritative → payment on final total).**

## 4a. Checkout — Transaction Boundary Before Order Creation (Phase 1.22)

> Checkout is the most important transaction boundary before Order creation. No guest checkout; no `MADE_TO_ORDER` via normal checkout; no customer-controlled money/status.

| # | Business rule | Ref |
|---|---|---|
| 1 | **Checkout requires authentication.** Only a logged-in `CUSTOMER` may call `POST /checkout`; anonymous checkout is rejected (`AUTHENTICATION_REQUIRED` 401). | IDENT-002, CHK-AUTH-001 |
| 2 | **Checkout operates on the customer's own active Cart.** Server derives `own active Cart` from authenticated principal (`/me/cart`); `cart_id` or `user_id` override is rejected; `Customer A → Customer B` cart is blocked via ownership + 404 masking. | CART-OWN-001, CHK-OWN-001 |
| 3 | **Checkout is a dedicated business workflow** — not a `PATCH /me/cart` field edit and not a direct `POST /orders` with client totals. It validates, checks inventory, recalculates prices, decides fulfillment, creates the Order and hands off to payment. | CHK-WORK-001 |
| 4 | **Cart does not reserve inventory.** Adding to cart does not reserve; checkout is the point where inventory authority becomes critical and is re-checked authoritatively inside an atomic transaction. | INV-004, CART-RES-001, CHK-INV-001 |
| 5 | **Checkout revalidates product/variant state** — `exists → active && is_published → IN_STOCK → purchasable`, `variant exists && belongs to product && active && purchasable`. Catalog cache is not trusted. | CAT-004, VAR-OWN-001, CHK-VAL-001 |
| 6 | **Checkout revalidates inventory authoritatively** — `requested quantity ≤ available quantity at transaction point`; race `A sees 1, B buys 1, A checks out → A fails safely` with no negative stock/oversell. Protected by transaction/locking (mechanism deferred). | INV-002/003, CHK-INV-002 |
| 7 | **Checkout recalculates authoritative pricing (subtotal)** — current catalog unit prices are resolved at checkout; `subtotal` is server-calculated at `CHK-001` (`{amount,currency:"TZS"}` integer minor units); `delivery_fee` remains pending at creation for `DELIVERY` (`null`, `PENDING`); final `total` / payment amount become authoritative only after `ORD-014` finalizes `delivery_fee` (`PENDING→FINALIZED`); client `total`/`price`/`currency` is rejected. | PRICE-001/002/003, CHK-PRICE-001 |
| 8 | **`MADE_TO_ORDER` cannot enter normal checkout.** Any `MADE_TO_ORDER` product in cart is rejected (`PRODUCT_NOT_PURCHASABLE` 422); it must use the Request workflow (`REQ-001`). | CAT-003, REQ-ACT-001, CHK-TYPE-001 |
| 9 | **Fulfillment is `PICKUP` or `DELIVERY` only** (CLOSED). `DELIVERY` requires valid `delivery_address` (recipient_name/phone/address_line/city); `PICKUP` requires no delivery address. | FUL-001, CHK-FUL-001 |
| 10 | **Delivery fee is not customer-controlled.** Customer chooses `DELIVERY` (supplies address); **Staff/Admin add the variable location-based fee**; backend stores authoritative `delivery_fee`. Customer `delivery_fee` payload is rejected. Flat `20,000` rate superseded. | PRICE-006, FUL-002, CHK-FEE-001 |
| 11 | **Delivery-fee timing is Model B (fee-after-order) in V1** — `checkout (`CHK-001`) calculates authoritative `subtotal` and creates Order `PENDING_PAYMENT` (`delivery_fee` pending) → Staff/Admin finalizes `delivery_fee` via `ORD-014` (`PENDING→FINALIZED`) which makes `total` and the final payment amount authoritative → payment (Group H) on that final total`. Order can be created before final delivery fee; payment amount must equal `ORD-014`-finalized authoritative total. No silent `pay before fee → staff changes total` mismatch. | CHK-FEE-002, PRICE-006, ORD-001 |
| 12 | **Customer cannot set authoritative subtotal, total, price, stock, status, reference, or cart ownership.** These are server-generated or server-calculated. | SEC-001, CHK-AUTH-002 |
| 13 | **Empty cart checkout is prohibited** — `POST /checkout` with empty cart returns `CART_INVALID` (422) — conceptual `CART_EMPTY` maps to `CART_INVALID` per registry `api-contract.md §15.15`; no separate `CART_EMPTY` code is registered, no empty order is created. | CART-001, CHK-EMPTY-001 |
| 14 | **Checkout is concurrency-sensitive and idempotency-required.** `Idempotency-Key` header required; same key replays original result; same key + different input → `409 CONFLICT`; inventory + order + cart mutations occur atomically. | CHK-IDEM-001, INV-003 |
| 15 | **Cart after success is cleared/inactivated; cart after validation failure is preserved.** Stale `is_purchasable:false` lines remain until checkout rejects (`CART_INVALID`); failed checkout does not empty the cart where safe. | CART-STALE-001, CHK-CART-001 |
| 16 | **Successful checkout creates an Order** (`PENDING_PAYMENT`) with historical snapshots (items, prices, addresses, fulfillment). Order lifecycle, delivery, payment and tracking are handled thereafter. | ORD-001, CHK-ORD-001 |
| 17 | **Checkout does not implement payment provider logic.** Provider integration, callbacks, webhooks, provider statuses belong to Group H. | PAY-002, CHK-PAY-001 |

**Checkout Authority Note (Phase 1.22, updated Phase 1.23 Model B):** Delivery address supplied at checkout becomes part of the Order's historical fulfillment snapshot (not a live profile reference). Saved address book is deferred. Checkout requires `Idempotency-Key`; inventory validation and state-changing operation are safe under concurrent checkout attempts. **Checkout calculates authoritative `subtotal`; `ORD-014` finalizes the payment amount (`subtotal + delivery_fee` → `total` authoritative) — full payment amount is not finalized exclusively during Checkout under Model B.** All financial calculations use integer minor units, never floating arithmetic.

## 5. Prices and Payment

| # | Business rule | Ref |
|---|---|---|
| 1 | Prices and totals are always decided by the system, never the customer. | PRICE-001/002 |
| 2 | All prices are in Tanzanian Shillings (TZS). Minor units `{amount:int, currency:"TZS"}`. | PRICE-003 |
| 3 | Money is always handled carefully by the system — financial amounts are never calculated with floating rounding errors. | PRICE-004 |
| 4 | **Self pickup is free.** `fulfillment_type=PICKUP → delivery_fee {amount:0}`. | PRICE-005 |
| 5 | **Delivery carries a location-based fee, which may be zero for some locations.** The delivery charge is set by staff and depends on delivery location. Customer cannot override `approved delivery fee`. | PRICE-006 |
| 6 | A customer is never treated as paid just because they say they paid. The system verifies payment itself. | PAY-002 |

**Validation note:** Customer sends cart quantities during cart mutations, then sends
  `fulfillment_type` + conditional `delivery_address` at checkout; backend determines `current price`, `subtotal`, `delivery_fee`, `total`. Provider-specific payment payload validation is Group H, not here; generic payment validation respects same layering and external-system separation.

## 6. Orders and Tracking

| # | Business rule | Ref |
|---|---|---|
| 1 | An order is created only through a completed checkout of a real cart. | ORD-001 |
| 2 | Only a logged-in customer can create an order. | ORD-002 |
| 3 | An order keeps a permanent snapshot of what was bought: product name, item reference, unit price, quantity, and line total — even if catalog changes later. Immutable historical fields. | ORD-005 |
| 4 | Orders have a customer-friendly reference number starting with **OD-**. Server-generated, never client-settable. | ORD-006 |
| 5 | Order status can only move along approved steps; neither the customer nor the app can force a jump. State transitions via controlled actions, not generic `PATCH {status:SHIPPED}` (API-VAL-004). | ORD-007 |
| 6 | Every status change is recorded with the time (and who/what caused it). | ORD-008 |
| 7 | Customers can follow their order through milestones (PENDING_PAYMENT → Paid, Accepted, Processing, Ready for Pickup / Shipped, Delivered, Completed — PENDING_PAYMENT shown as initial `Order received` event when order is still pending). Only milestones for their fulfillment type are shown. | FUL-005/006 |
| 8 | Order Items preserve historical `unit_price` and `quantity` and `line_total` — not changed when catalog price later changes to `1,200,000` or product deactivated; product is still shown as `1,000,000`. | ORD-005, ORD-HIST-001 |
| 9 | Delivery information (delivery address, recipient contact) is preserved as snapshot per Order — later profile address change does not rewrite history; saved address book deferred. | ADDR-001, ORD-005 |
| 10 | Delivery fee is variable, location-based, not customer-controlled; Staff/Admin add fee (`null` → `{amount,currency}` `PENDING→FINALIZED` before `PAID`); provisional `total` not final until fee finalized; payment blocked `409 DELIVERY_FEE_PENDING` while pending; historical fee immutable after finalization. | PRICE-006, CHK-FEE-002 |
| 11 | Order `subtotal/delivery_fee/total/currency` are server-authoritative `{amount,currency}` integer minor units; customer `total` override rejected. | PRICE-001/002 |
| 12 | Customer can access only own Orders — `Customer A → Customer B order` fails `404` masked; Staff operational view is separate and does not grant account control. | IDENT-006, OWN-001 |
| 13 | Staff process Orders via authorized operational actions (`accept/process/ready-for-pickup/ship/deliver/complete` only if state + fulfillment permit) — no `STAFF → block_customer` or account control. | STAFF-OP-001 |
| 14 | Admin has highest operational/administrative authority — approves staff, may perform authorized admin order operations, but still action-controlled and audited; no `PATCH {status:anything}` or historical silent rewrite without controlled correction. | AUTHZ-ADMIN-001 |
| 15 | Order data is private and not publicly cacheable — `Cache-Control: private, no-store`; completed/cancelled orders remain readable to owner/staff, not deleted (`DELETE /orders` not customer). | IDENT-006 |
| 16 | Payment is related to Order but defined in Group H — Order exposes limited `payment_status/amount` snapshot, not provider secrets; `Order PAID` is payment-confirmed via Group H webhook, distinct from `ACCEPTED`. | PAY-002 |

## 7. Order Status Paths

**Pickup orders** follow: `PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP → COMPLETED`.

**Delivery orders** follow: `PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → SHIPPED → DELIVERED → COMPLETED`.

| # | Business rule | Ref |
|---|---|---|
| 1 | A pickup order never shows "Shipped". | FUL-005 |
| 2 | A delivery order must not skip required steps. | ORD-007 |

**Validation note:** State-dependent validation — `Order=PROCESSING → SHIP may be valid; Order=COMPLETED → SHIP invalid`. Every transition validates `current state, requested transition, actor authorization, business preconditions`. Valid `order_id` ≠ authorized; server time governs cancellation window.

## 8. Cancellation

| # | Business rule | Ref |
|---|---|---|
| 1 | A customer may cancel their order **within 20 minutes** of placing it. Backend determines authoritative time; `cancelled_at` submitted by client is rejected. | CANCEL-001 |
| 2 | The system itself checks the time; the customer cannot trick it by claiming a different time. | CANCEL-002 |
| 3 | Cancelling an order does not automatically mean a refund will happen. | CANCEL-003 |
| 4 | Staff/admin cancellation is a different process and is not bound by the 20-minute rule. | CANCEL-004 |

**Validation hierarchy:** `authenticated customer → owns order → order exists → customer cancellation allowed → 20-minute window valid → current order state eligible`. Failure atomicity required; do not leave reservation applied if later validation fails.

## 8a. Tracking and Fulfillment

> Tracking = customer-facing `timeline` of fulfillment progress (`PENDING_PAYMENT, PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP, SHIPPED, DELIVERED, COMPLETED` filtered by fulfillment; see rule 8 for PENDING_PAYMENT visibility); Fulfillment = operational `Order → Staff → Process → Ship/Deliver/ReadyForPickup → Complete` (small-scale, not logistics platform).

| # | Business rule | Ref |
|---|---|---|
| 1 | **Pickup and Delivery follow distinct operational paths.** `PICKUP: PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` (no `SHIPPED`/`DELIVERED`); `DELIVERY: PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED` (no `READY_FOR_PICKUP`). Cross-branch `PICKUP→SHIPPED` rejected. | FUL-003, ORD-007 |
| 2 | **Pickup uses `READY_FOR_PICKUP` before completion** — Staff marks `PROCESSING→READY_FOR_PICKUP` (`ORD-009`) when ready for collection; `READY_FOR_PICKUP→COMPLETED` (`ORD-013` explicit Staff confirms collection). Single pickup location V1. | FUL-002, ORD-009 |
| 3 | **Delivery uses `SHIPPED` then `DELIVERED` before completion** — Staff ships `PROCESSING→SHIPPED` (`ORD-010`: left business), then marks `SHIPPED→DELIVERED` (`ORD-011`: delivery completed), then `DELIVERED→COMPLETED` (`ORD-013`). No GPS/`tracking_number`/`carrier`. | FUL-002, ORD-010 |
| 4 | **Customer can track own Order** — `GET /me/orders/{order}/tracking` (`ORD-003`) own tracking `AUTHENTICATED_OWNER` only; `Customer A → Customer B tracking` fails `404` masked. Staff tracking `ORD-012` operational richer view. | ORD-003, FUL-001 |
| 5 | **Staff operate fulfillment** — `orders.ready_for_pickup / ship / deliver / complete` with `valid state + fulfillment_type + permission` (`ORD-009/010/011/013`, `Idempotency-Key` Required, concurrency `Critical`); once authorized, no repetitive Admin approval. | STAFF-OP-001 |
| 6 | **Customer cannot modify fulfillment state** — `POST {status:"SHIPPED"}` / `mark delivered` / `mark ready for pickup` from customer rejected `403/409` (`FULFILLMENT_ACTION_NOT_ALLOWED`); only Staff/Admin via `ORD-009/010/011`. | FUL-002, ORD-007 |
| 7 | **Fulfillment events do not rewrite historical financial data** — `ship/ready/deliver` does not change `historical unit_price/subtotal/delivery_fee` (except `ORD-014` fee `PENDING→FINALIZED` before `PAID`), nor `billing_address` snapshot; inventory `reserved_quantity` not exposed via tracking. | ORD-HIST-001 |
| 8 | **Tracking is read-only and derived** — customer `GET` only (`POST/PATCH/DELETE /tracking` prohibited), `current_status == Order.status`, `timeline` chronological `occurred_at ASC, id ASC` `ISO8601 Z`, `id` stable `evt_...`, `append-only` (customer cannot edit, staff cannot rewrite past — correction requires admin workflow), no `GPS`/`courier integration`/`assigned_staff` in V1, `REST GET` polling (no `WebSockets`). **PENDING_PAYMENT appears in the customer timeline** — when `Order.status == PENDING_PAYMENT`, `timeline` includes the initial `PENDING_PAYMENT` event (`label: Order received`) so that `current_status == Order.status` holds; omitting it would violate `§25.8` consistency. Customer-facing timeline therefore starts at `PENDING_PAYMENT` for pending orders and progresses through `PAID` etc. filtered by fulfillment. | FUL-001, FUL-004 |
| 9 | **Privacy & caching** — customer tracking `private, no-store` (PII/financial, not CDN); `delivery_address` visible only to owns customer + authorized Staff/Admin; internal `staff notes` never to customer; lightweight (not `entire Order`). | FUL-001 |
| 10 | **Payment boundary** — fulfillment/payment distinct (`PAID ≠ SHIPPED`); `SHIPPED` does not mean paid, `PENDING_PAYMENT` with `delivery_fee PENDING` blocks `PAY-001` `409`; tracking does not implement payment. | PAY-002 |

**Tracking Authority Note (Phase 1.24):** Customer tracking `ORD-003` is `REST GET` polling (poll on open, pull-to-refresh) — no `WebSockets/SSE/GPS`. Staff fulfillment `ORD-009/010/011/013` are `Idempotency-Required`, `Critical` concurrency (e.g., `cancel+ship` race → `409 ORDER_STATE_CONFLICT`), validated `actor+permission+current state+fulfillment` atomically.

## 9. Made-to-Order Requests (Phase 1.25 — Approved V1)

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can request a "Made to Order" item — no account needed, as long as they leave contact information (**`name` required plus at least one of `phone` or `email` — identical rule for Anonymous and authenticated, per ADR/API-REQ-001; no trusted-account fallback**). `POST /requests` (`REQ-001`) is `PUBLIC` with optional auth. | REQ-001 |
| 2 | A request can mention an existing `MADE_TO_ORDER` product, but does not have to. **Final V1 policy per ADR/API-REQ-011: `product_id` is OPTIONAL (nullable)** — when supplied must be existing, active, published, `product_type=MADE_TO_ORDER` (otherwise `PRODUCT_NOT_REQUESTABLE`); when `null`/omitted = custom/general furniture request. Both modes allowed; **not** Required, **not** Absent. | REQ-002 |
| 3 | A request can include quantity (`1..100` optional, integer), structured dimensions (`length/width/height` `>0` + `unit:"cm"` CLOSED allow-list), preferred material/color as free text (not closed enums), and notes (max 5000), plus optional single picture/file. File validation: size `<=5 MB`, allowed types `image/jpeg|png|webp|application/pdf`, content signature not client MIME alone, filename sanitized. | REQ-003, ATTACH-001 |
| 4 | **A request is never an order.** It does not create an order, a payment, or reserve any stock. No `order_id`/`payment_*`/`delivery_fee` from client. | REQ-004 |
| 5 | In Version 1, requests are leads for the business to follow up — `SUBMITTED → IN_REVIEW → CLOSED` (CLOSED enum, `SUBMITTED` default, `CLOSED` terminal). Staff handle via `requests.view`/`requests.manage` operational access, not ownership. | REQ-005 |
| 6 | No final price (quotation) is assumed at the time of the request. `price`/`quoted_price` not produced; business may later discuss price/production/delivery but not authoritative from intake. | REQ-006 |
| 7 | Authenticated requests belong to the submitting customer (`Request.user_id = authenticated principal` server-derived). Anonymous requests have `user_id=null` and require explicit contact snapshot even when authenticated (self-contained record, future profile change does not mutate past request). Contact snapshot is historical. | REQ-007 |
| 8 | Customers see only own requests via `GET /me/requests`; staff see operational queue via `GET /requests` (`requests.view`); anonymous retrieval is not supported without explicit secure token — predictable `id` is not bearer credential. Attachments inherit parent authorization; no permanent public URLs. | REQ-008 |
| 9 | A request does not guarantee production, delivery, or completion; attachments are optional; attachments via `multipart/form-data` inline on creation preferred, separate `POST /requests/{request}/attachments` (`REQ-007`) requires scoped server-issued upload token + parent ownership. Internal notes `staff_internal_notes` are private to staff, never customer-visible. | REQ-009 |

Validation: layered `Transport → Schema (name required, phone/email at least one, quantity 1..100, dimensions allow-list unit cm, material/color/notes safe, attachment safe) → Auth (optional) → Authorization (PUBLIC create vs owns OPERATIONAL read) → Domain (product `MADE_TO_ORDER` when linked — `product exists, is_active:true, is_published:true, product_type MADE_TO_ORDER` validated) → Concurrency Low → Persistence`. Anonymous `user=null` is valid (`REQ-001`). Unknown fields rejected, server-controlled fields (`user_id`, `request_status`, `created_at`) never client-settable. See `api-contract.md §26` for normative endpoint contract and `api-conventions.md §26` for reusable conventions.

## 10. General Enquiries (Phase 1.26 — Approved V1)

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can send a general enquiry — no account needed, as long as they give contact information (**Anonymous: `name` required + at least one of `phone`/`email`; Authenticated: `name`/`phone`/`email` Optional/derived — server derives `user_id`, preserves contact snapshot where supplied, per ADR/API-ENQ-001**). `POST /enquiries` (`ENQ-001`) is `PUBLIC` with optional auth. | ENQ-001 |
| 2 | An enquiry must include contact details (**`name` trimmed 120 max plus at least one reachable channel**), a **subject** (required, 5–200 plain text, triage) and a **message** (required, plain text 10–5000, safe handling, no HTML/Markdown in V1). | ENQ-002 |
| 3 | An enquiry is **private communication, not an order** — it does not create an Order, does not reserve stock, does not charge payment, does not guarantee product availability/completion. No `order_id`/`payment_*`/`delivery_fee` from client. | ENQ-003 |
| 4 | **Enquiries and made-to-order requests are two different things and never merged** — Request asks "Can you make this furniture?" with dimensions/material/color; Enquiry asks general business information. No automatic `Request ↔ Enquiry` conversion. | ENQ-004 |
| 5 | An enquiry may optionally refer to a **public Product** (`product_id` nullable — when supplied must be existing, `is_active:true` + `is_published:true` public product, any `product_type` allowed for enquiry context) and/or to an **Order** (`order_id` nullable — `order_id` without an order remains `null`; when supplied, anonymous `order_id` must be rejected with `422 INVALID_VALUE` `field: order_id` unless presenting a server-issued scoped order-access token; authenticated customers may reference only Orders they own (ownership-validated via `ENQ-007`); knowing an `order_id` is not authorization). Both may be `null`/omitted for general contact. | ENQ-005, ENQ-006 |
| 6 | In Version 1, enquiries are private communications for the business to handle — status `OPEN` (default, needs attention) → `CLOSED` (handled, via `ENQ-006` `POST /enquiries/{enquiry}/close`). Customer never sets `enquiry_status`; reopen `CLOSED→OPEN` only if explicitly approved. `ASSIGNED`/`IN_PROGRESS` etc. not in V1. | ENQ-007 |
| 7 | Enquiry status is **CLOSED enum `OPEN`/`CLOSED`** (`UPPER_SNAKE_CASE`). Minimal small-business queue, not a full support workflow. | ENQ-007 |
| 8 | Authenticated enquiries belong to the submitting customer (`Enquiry.user_id = authenticated principal` server-derived). Anonymous enquiries have `user_id=null` and require explicit contact snapshot (self-contained record, future profile change does not mutate past enquiry). Contact snapshot is historical; `subject`/`message` are **immutable history** after creation. | ENQ-008 |
| 9 | Customers see only own enquiries via `GET /me/enquiries`; staff see operational queue via `GET /enquiries` (`enquiries.view`); anonymous retrieval via `GET /enquiries/{id}` is **not supported** without explicit secure mechanism — predictable `id` is not bearer credential (per ADR/API-ENQ-006). Attachments inherit parent authorization (private to parent, scoped token for separate `POST /enquiries/{enquiry}/attachments` `ENQ-007`, no permanent public URLs); attachments **optional** (`0` or `1` on creation, preferred `multipart/form-data` inline on `ENQ-001`). Message treated as untrusted plain text (XSS-safe, no code execution, frontend escapes). Historical original message preserved; internal notes `staff_internal_notes` separated and never customer-visible. | ENQ-009, ATTACH-001 |

Validation: layered `Transport → Schema (name required anonymous / optional derived authenticated, phone/email at least one anonymous, subject 5–200, message 10–5000 plain text, category CLOSED GENERAL/PRODUCT/DELIVERY/OTHER where supplied, product `exists, is_active:true, is_published:true, publicly visible` when linked, order `exists + ownership` validated when linked, attachment safe) → Auth (optional) → Authorization (PUBLIC create vs owns OPERATIONAL read) → Domain (plain text, product `exists, is_active:true, is_published:true, publicly visible` when linked, order `exists + ownership` validated when linked, status OPEN default, immutable message, category CLOSED where supplied, attachment safe) → Concurrency Low → Persistence`. Anonymous `user=null` valid (`ENQ-001`). Unknown fields rejected, server-controlled fields (`user_id`, `enquiry_status`, `created_at`, `staff_internal_notes`) never client-settable. Private `Cache-Control: private, no-store`; no public SEO indexing. See `api-contract.md §27` for normative endpoint contract and `api-conventions.md §27` for reusable conventions. Email delivery deferred to Group R; payment remains Group H.

## 11. Addresses and Files

| # | Business rule | Ref |
|---|---|---|
| 1 | The system does not keep a saved address book in Version 1. Addresses are entered for each order. | ADDR-001/002 |
| 2 | The billing address and the delivery address are treated as separate things even when same value. | ADDR-003 |
| 3 | Files attached to requests/enquiries are optional. Safe file rules (type, size, signature) enforced server-side. | ATTACH-001/003 |

## 12. Accounts, Roles and Staff

| # | Business rule | Ref |
|---|---|---|
| 1 | There are three roles: **Customer**, **Staff**, and **Admin**. | AUTHZ-001 |
| 2 | Every sensitive action must be protected and only allowed for authorized actors. Backend authorization is authoritative. | AUTHZ-002 |
| 3 | Stock, prices, delivery charges, cancellation timing, and payment confirmation are decided by the system, never by client claim. Server-controlled fields (`id`, `created_at`, `updated_at`, `order_reference`, `status`, `payment_status`, `inventory quantities`, `final totals`, `status_history`) are never client-settable. | SEC-001 |
| 4 | Customer-visible totals and order status cannot be changed from the app or website. | AUTHZ-004 |

## 13. Notifications and Emails (Phase 1.27 — Approved V1)

| # | Business rule | Ref |
|---|---|---|
| 1 | **Notifications communicate business events; they do not create, authorize, or become the source of truth for that state.** The system may keep notification records tied to events such as an order changing status (`Order SHIPPED → ORDER_SHIPPED`), but `Order`/`Request`/`Enquiry`/`Payment`/`Fulfillment` remain authoritative. If Notification says `SHIPPED` but `Order` says `PROCESSING`, `Order` wins. | NOTIF-001, NOT-001 |
| 2 | **Customers receive their own private notifications.** In-app `GET /me/notifications` is `AUTHENTICATED_OWNER` recipient-scoped, paginated `page/per_page`, `created_at DESC, id ASC`, `Cache-Control: private, no-store`. `Customer A → Customer B` notification fails `404` masked. | NOTIF-002 |
| 3 | **Staff receive operational notifications.** `NEW_ORDER`, `NEW_MADE_TO_ORDER_REQUEST`, `NEW_ENQUIRY` operational alerts via shared queue or role-filtered personal set; `Order` collection remains source of truth so Staff can refresh queue if notification delivery is delayed. | NOTIF-003 |
| 4 | **Admins receive authorized administrative notifications** where required (`staff approval/security/critical operational`); not every operational notification auto-forwarded to Admin. | NOTIF-004 |
| 5 | **Notification read state is recipient-scoped and separate from business state.** `read_at = null` → unread, `read_at = timestamp` → read (`is_read` derived); `read` ≠ `Order processed` / `payment succeeded`. Only recipient may mark own notification read via `PATCH /me/notifications/{notification} {read: boolean}`; Staff cannot mark Customer notifications via Staff credentials. | NOTIF-005 |
| 6 | **Notification does not grant access to its target.** `target: {type: ORDER, id: ...}` is server-generated navigation reference; normal `Order`/`Request`/`Enquiry` authorization still required on follow-up fetch. | NOTIF-006 |
| 7 | **Notification failure does not invalidate the underlying business transaction.** `Order creation / SHIPPED / payment` must remain correct even if notification generation fails; eventual consistency (`state updates immediately, notification appears shortly after`) acceptable; duplicate source event must not create uncontrolled duplicates (idempotency at processing boundary). | NOTIF-007 |
| 8 | **Real email/SMS/push delivery is deferred; V1 is IN_APP primary and does not depend on sending emails.** `Notification` record (`IN_APP`) is logical message; `Notification → Email` (Group R) and `Notification → Push` are delivery attempts, not separate resources; `channel` `EMAIL`/`SMS`/`PUSH` not exposed until implemented. Anonymous in-app notifications not created without authenticated recipient. | NOTIF-002/003, NOT-008 |
| 9 | **Notification types are CLOSED and machine-readable.** `ORDER_SHIPPED`, `NEW_ORDER` etc. per registry; unknown type → `422`; machine logic depends on `type`, not `message`; dynamic content (`order_reference`, `product name`) safely encoded server-side, not client-submitted. | NOTIF-009 |

---

## 14. Error ↔ Business Rule Mapping (Phase 1.16)

Business failures must correspond to real rules above — do not invent behavior through codes.

| API error `code` | Business meaning | Invariant / Rule |
|---|---|---|
| `PRODUCT_NOT_PURCHASABLE` | Attempted to cart/checkout a `MADE_TO_ORDER` product (request-only) despite display price | CAT-003 |
| `PRODUCT_UNAVAILABLE` / `PRODUCT_NOT_FOUND` / `INVALID_PRODUCT_VARIANT` | Referenced product/variant is inactive (`is_active=false`), does not exist, or variant not belonging/active/purchasable | CAT-004, INV-001 |
| `INSUFFICIENT_STOCK` | Concurrent checkout depleted stock; requested qty unavailable | INV-002, INV-003, CART-004, CHECKOUT-005 |
| `CART_INVALID` / `CART_ITEM_UNAVAILABLE` / `CHECKOUT_NOT_ALLOWED` | Cart contains non-purchasable/now-unavailable items or empty cart on checkout | CART-001, CART-004, CHECKOUT-005 |
| `INVALID_FULFILLMENT` / `INVALID_DELIVERY_INFORMATION` | `DELIVERY` without valid address/contact, `PICKUP` fee misuse | FUL-003, ADDR-004, PRICE-006 |
| `ORDER_NOT_CANCELLABLE` | Customer cancellation outside 20-min window or ineligible state | CANCEL-001/002, ORD-007 |
| `INVALID_ORDER_TRANSITION` / `ORDER_STATE_CONFLICT` | Staff/customer attempted illegal state jump or concurrent conflict | ORD-007, FUL-005/006 |
| `INVALID_ATTACHMENT` / `ATTACHMENT_TOO_LARGE` / `UNSUPPORTED_ATTACHMENT_TYPE` | File attached to request/enquiry fails safe type/size/signature | ATTACH-001/003, REQ-003 |
| `RESOURCE_NOT_FOUND` (generic) / `ORDER_NOT_FOUND` / `REQUEST_NOT_FOUND` / `ENQUIRY_NOT_FOUND` | Resource not addressable / not owned (404 masking prevents enumeration, see §15.8) | IDENT-006, ORD-002 |
| `AUTHENTICATION_REQUIRED` / `FORBIDDEN` | Unauthenticated checkout / not owner of private resource | IDENT-002, IDENT-006, AUTHZ-002 |
| `EXTERNAL_SERVICE_ERROR` | Temporary provider failure — never raw provider text (generic `INTERNAL_SERVER_ERROR` is an unexpected server failure, not a business rule; handled in API error contract `api-contract.md §15.15`) | PAY-002 |

- **Not elaborate:** Do not create `INSUFFICIENT_STOCK_FOR_SOFA` explosion — one `INSUFFICIENT_STOCK` serves all products; `details` carries safe `available_quantity`/`requested_quantity` only.
- **Security stays:** Error messages never leak `other_customer_id`, `internal_reservation_id`, `provider_secret` — map to codes above.
- **Payment specifics deferred:** Provider reconciliation error codes belong to Group H; they will slot into `EXTERNAL_SERVICE_ERROR` family without breaking this table.

## 15. Validation Architecture Reference (Phase 1.15 Summary)

- **Layers:** Transport → Schema/input → Authentication → Authorization → Domain/business → Cross-field → State-dependent → Concurrency/transaction → External (payment provider deferred to Group H). See `docs/api/api-contract.md §14` for normative hierarchy and `api-conventions.md §16` for reusable rules.
- **Enforcement:** `docs/api/api-contract.md §14` and `api-conventions.md §16` are authoritative for validation mechanics; this file is authoritative for business meaning.
- **Centralization:** A business rule has one authoritative implementation boundary (e.g., 20-minute cancellation in domain/application layer, not reimplemented independently in Next.js/Flutter/controller/model).
- **Compatibility:** Validation changes that reject previously accepted input or make a field suddenly required are breaking within `v1` (versioning policy); adding new closed enum values is a formal compatibility decision.
- **Payment:** Generic validation architecture applies to Payment; provider-specific fields/SDK/webhook payload validation remain Group H.

## 16. Error Architecture Reference (Phase 1.16 Summary)

- **Envelope:** `{"errors":[{"code","message","field","details"}],"meta":{"request_id":"..."}}` — one structure for every failure, `errors` array even for single. See `api-contract.md §15.1-15.2` and `api-conventions.md §17.1`.
- **Code vs message:** `code` (`INSUFFICIENT_STOCK`) is machine-stable, CLOSED, version-sensitive; `message` is human, localizable, improvable. Frontend branches on `code` + `HTTP status`, never parses English.
- **HTTP mapping:** `400 INVALID_JSON`, `401 AUTHENTICATION_REQUIRED`, `403 FORBIDDEN`, `404 RESOURCE_NOT_FOUND` (masked for owned resources), `422 validation/business`, `409 conflict concurrency`, `413 REQUEST_TOO_LARGE`, `429 RATE_LIMITED` + `Retry-After`, `500 INTERNAL_SERVER_ERROR`. See `api-contract.md §15.4`.
- **Security:** Raw exceptions/SQL/secrets never exposed; internal diagnostics stay in server logs; `request_id` connects client error to logs.
- **Compatibility:** Removing/renaming codes or changing field-path format is breaking; improving messages is not. All error codes are versioned contract elements.

## 17. Authentication & Account Business Rules (Phase 1.17)

> Customers own their accounts and experience; Staff operate commerce but do not control customer accounts; Admin has highest admin authority and approves staff. All auth failures use common error contract (`api-contract.md §15`).

| # | Business rule | Ref |
|---|---|---|
| 1 | **Customers can self-register without staff approval** via public Clerk sign-up (`Visitor → Clerk email+password sign-up → email verification → completed Clerk signup → first Laravel call provisions CUSTOMER → Authenticate`). Staff/admin accounts are not via public registration. **Registration credentials are `email` + `password` only; phone is not a signup requirement** (Phase 4.5). | AUTH-001, IDENT-001 |
| 2 | **Public catalog browsing is always anonymous-allowed** (`products`, `categories`, `search`, `product details`, `prices`, `availability`) and SSR pages (`/products`, `/products/{slug}`, `/categories/{slug}`) must render without login. | IDENT-003, SEO-001 |
| 3 | **Checkout requires authentication** — anonymous checkout is rejected (`AUTHENTICATION_REQUIRED`/`CHECKOUT_REQUIRES_AUTHENTICATION` 401); backend enforces. | IDENT-002, ORD-002, CART-CHECKOUT-001 |
| 4 | **Anonymous requests/enquiries are allowed** (`Made-to-order Request`, `General Enquiry` with `User=none` + required contact); when authenticated they link to own history (`My Requests`). | REQ-001, ENQ-001 |
| 5 | **Staff are approved by Admin only** (`Staff candidate → Admin review → Approved → Active` or `Admin invite → activate`); self-registration as `STAFF` via public form is prohibited; staff cannot approve themselves and customers cannot approve staff. | AUTHZ-STAFF-001, STAFF-ONBOARD-001 |
| 6 | **Staff cannot restrict ordinary customer browsing or ordering** — no `disable product browsing`, `block legitimate checkout`, `change role/password`, `lock account`, `impersonate`, `can_order` switch without explicit Admin-only security policy. | STAFF-RESTRICT-001, IDENT-006 |
| 7 | **Customer accounts belong to customers** — staff must not browse as customer, alter profile arbitrarily, access credentials, or impersonate; admin customer-account ops (review, disable compromised, force reset, revoke sessions) are explicit auditable security operations, not casual management. Account hard-delete not assumed (historical orders/payments/requests must remain). | IDENT-006, OWN-001 |
| 8 | **Shared cross-platform identity** — a customer registered on website is same account on Flutter app (one identity, no duplicates). Both `Next.js` and Flutter call the same Laravel backend with `Authorization: Bearer <Clerk session_token>`; Laravel accepts no browser/session cookie as an authentication credential. | IDENT-007, X-PLATFORM-001 |
| 9 | **Role assignment is server-controlled** — client self-promotion (`{"role":"ADMIN"}`) rejected; CLOSED roles `CUSTOMER`/`STAFF`/`ADMIN`; adding `MANAGER` etc. requires review. | AUTHZ-ROLE-001 |
| 10 | **Passwords are never plaintext and are never Laravel-owned** — Clerk owns password creation/reset/change/compromise validation; Laravel stores **no usable password** (`users.password` nullable and null for Clerk-provisioned customers). Laravel never receives, stores, or returns passwords or reset secrets; `POST /auth/password/forgot|reset` and `/auth/change-password` are **RETIRED** (`410`). Recovery UX is generic “Request received.” without enumeration. | SEC-PWD-001, SEC-CRED-001 |
| 11 | **Verification & enumeration protection** — `email_verified_at` is server-set and **derived only from trusted Clerk state** (`email_verified:true` from client not authoritative); **email verification is required at signup** (Phase 4.5) and an unverified Clerk email cannot be provisioned as a local `CUSTOMER` (`INVALID_AUTHENTICATION` 401); recovery/security flows avoid distinguishing `email exists` vs `not exists` unless justified; brute-force rate limiting and abuse detection identified for later. | SEC-ENUM-001, SEC-RATE-001 |
| 12 | **Sessions are revocable and multi-device permitted for Customer** — Clerk owns session lifecycle; revoking one session (`ClerkSessionGateway::revoke($sid)`) does **not** revoke other active sessions; multi-session policy is Clerk-managed. A session pending security tasks (`reset-password`, `setup-mfa`, `choose-organization`) is **not** fully authenticated: protected Laravel business APIs deny it (`SESSION_EXPIRED` 401). | SESSION-001, SESSION-PENDING-001 |
| 13 | **Pending security-task sessions are not fully authenticated** — `SIGNED_IN` → protected access may continue; `PENDING` → protected access denied until required tasks complete; `SIGNED_OUT` → no protected access. Laravel rejects the `sts=pending` session token before provisioning/resolving a local user. | SESSION-PENDING-001 |
| 14 | **Credential recovery never alters application account state or RBAC** — a successful Clerk password reset does not reactivate a suspended local account and does not change `CUSTOMER` role. | SEC-REC-001, SESSION-PENDING-001 |
| 15 | **Staff cannot administer customer credentials** — staff have no password-reset/MFA-revoke/impersonate/credential-view capability; any future Admin security operation is a dedicated audited workflow (deferred to a security/admin phase). | STAFF-RESTRICT-001, SEC-ADMIN-001 |

- Staff approval and role changes remain **auditable** (`who approved? when? what changed?`); security events (`login success/failure`, `logout`, `password change/reset`, `staff approval`, `role change`, `session revocation`) are identified for later logging without logging passwords/tokens. `password_reset_tokens` table retired in Phase 4.4; `users.password` remains nullable/reserved for migration compatibility and is never populated for Clerk customers.

**Profile and account-retention policy (Phase 4.6):** `GET /api/v1/me` and `PATCH /api/v1/me` always use the authenticated Clerk-derived local User. Laravel owns mutable profile fields `name` and optional `phone`; Laravel remains authoritative for local identity mapping, role, permissions, account state, and commerce relationships. Clerk owns email, verification, credentials, and sessions. Profile responses are private/no-store and profile updates cannot modify email, verification, identity mapping, role, permissions, account state, or historical Order/Request/Enquiry snapshots. Local Users with commerce history are retained when a Clerk identity is deleted or an account is closed; no ordinary profile operation hard-deletes them. `carts.user_id` uses `ON DELETE RESTRICT` on all supported drivers, so an authenticated cart cannot silently become a guest cart.

## 18. Authorization & Permissions Business Rules (Phase 1.18)

> Least privilege, deny by default, server-side enforcement. Roles are CLOSED `CUSTOMER`/`STAFF`/`ADMIN`.

| # | Business rule | Ref |
|---|---|---|
| 1 | **Customer owns own account** — authenticated principal `==` resource owner is required for owner-based resources (`User → Orders/Notifications/Cart/Requests/Enquiries`). `user_id` knowledge or `{"user_id":"another"}` does not grant access. | OWN-001, IDENT-006 |
| 2 | **Customers retain broad freedom over own commerce** — may `browse/search/view products/categories`, `manage cart`, `checkout`, `view/track/cancel eligible own orders`, `submit/view own requests/enquiries`, `manage own profile/notifications` without admin approval. | CUSTOMER-FREEDOM-001 |
| 3 | **Public catalog remains public** — `products`, `categories`, `search`, `product details`, `prices`, `availability` require no auth (explicitly classified, not exception). | IDENT-001, CAT-PUBLIC-001 |
| 4 | **Staff are operational users** — receive/process orders, perform authorized status actions (`ACCEPT/PROCESS/READY/ SHIP/DELIVER` only if state permits), approved inventory/catalog ops, handle requests/enquiries/notifications; staff do **not** own customer accounts and have zero customer-account control. | STAFF-OP-001 |
| 5 | **Staff cannot restrict customer browsing/ordering — unconditional prohibition for STAFF** — no generic `STAFF → block_customer`; staff cannot `disable browsing`, `block legitimate checkout`, `change role/password`, `lock account`, `impersonate`, `view credentials`, `transfer Order→another customer`, or `modify customer ownership` under any policy. No Admin policy may grant this capability to `STAFF`. Only an authorized `ADMIN` may execute a **separate, explicitly approved restriction operation** via a distinct Admin-only endpoint/policy (audited, with `reason/authorization/audit/impact/recovery`), never via `STAFF` role. | STAFF-RESTRICT-001, IDENT-006 |
| 6 | **Admin is highest administrative role** — approves/manages staff, manages operational users, performs authorized catalog/inventory/orders/requests/enquiries, system settings, authorized customer-account administration (`review`, `manage security state`, `revoke sessions`) where approved; still obeys `business invariants`, `auditability`, `data integrity`, `no silent rewrite of historical order price/payment confirmation/status history` without controlled correction workflow. | AUTHZ-ADMIN-001 |
| 7 | **Role assignment and staff approval are server-controlled** — `CUSTOMER/STAFF` cannot assign roles; only `ADMIN` may `staff.approve`/`staff.manage` with authenticated + authorized + valid target + audit; staff cannot approve themselves; customers cannot approve staff; client payload `role` changes rejected. | ROLE-001, STAFF-APPROVAL-001 |
| 8 | **Protected resources use deny-by-default** — access denied unless explicitly authorized via `ROLE + RESOURCE + ACTION + OWNERSHIP + STATE + CONTEXT`. `role` alone insufficient (`CUSTOMER + Order` ≠ any Order). | DENY-001 |
| 9 | **Customer-owned resources require ownership checks** — `Customer A → Customer B` order/notification/request access must fail despite authenticated (object-level). `?user_id=another` or `order_id` swapping must not expand scope. | HORIZ-001, OWN-001 |
| 10 | **Privileged business actions require authorization AND state** — `STAFF → ship` needs `ship` permission **and** `Order=PROCESSING`; `CUSTOMER → cancel` needs `owns + eligible state + 20-min window`. Valid role alone does not permit invalid transition. Authz evaluated at operation time, atomic with validation. | STATE-AUTHZ-001, CANCEL-001 |
| 11 | **Anonymous requests/enquiries/post-registration browsing are allowed but attachment/notification access is private** — `POST` anonymous via public endpoint + validation; `GET /enquiries/{id}` unrestricted or predictable attachment paths prohibited; `Request → Attachment` inherits parent; notifications owner-based (staff operational, not merged). Cart access bound to owner; `cart.owner` not client-changeable. | ANON-001, ATTACH-001 |
| 12 | **Field-level and query-aware authorization** — authorization before serialization; only permitted fields per actor (e.g., `reserved_quantity` not to `CUSTOMER`); search/pagination operate over **authorized dataset** (`/me/orders?page=2` paginates own orders); public vs private caching separated; background jobs use explicit service authorization, not reused Admin credential. | FIELD-001, QUERY-001 |
| 13 | **Denial respects enumeration protection** — `not authenticated → 401`, `authenticated not permitted → 403` or `404 RESOURCE_NOT_FOUND` where hiding existence is safer (private ownership). Do not leak via divergent errors. | DENIAL-001 |

## 14. User/Profile — Customer Owns Account (Phase 1.28)

> A Customer owns their account. Their profile is self-managed within the fields the business permits. Staff do not control or restrict customer accounts as part of normal e-commerce operations. Admin has the highest administrative authority, but sensitive account operations must remain explicit and controlled.

| # | Business rule | Ref |
|---|---|---|
| 1 | **Every authenticated actor has a profile.** `Customer → owns own profile`; `Staff → owns own profile`; `Admin → owns own profile` — each sees own via `GET /me` (self-context), never via client-supplied `user_id` substitution. | USER-OWN-001, IDENT-006 |
| 2 | **Customers own their accounts and control allowed profile fields.** `name` and `phone` are customer-mutable via `PATCH /me` allow-list; `id`/`role`/`permissions`/`verification state`/`account_state` are **server-controlled** and never changed via `PATCH /me` (even if client submits `{"role":"ADMIN"}` it is rejected). | USER-MUTABLE-001, SEC-001 |
| 3 | **Role is server-controlled, never client-mutable.** `CUSTOMER`/`STAFF`/`ADMIN` are CLOSED; client self-promotion `{"role":"ADMIN"}` / `{"permissions":["*"]}` is rejected; role management belongs to Admin-only Phase 1.29 (`staff.approve`/`staff.manage`), not to `/me`. | AUTHZ-ROLE-001, USER-ROLE-001 |
| 4 | **Staff cannot arbitrarily control Customer accounts.** Staff cannot `change customer role`, `disable customer`, `modify customer ordering/browsing ability`, `change password`, `view credentials`, `impersonate`, or `transfer ownership` through User/Profile APIs. Staff calling `GET /me` sees **Staff identity only**. | STAFF-RESTRICT-001, USER-OWN-001 |
| 5 | **Admin has highest administrative authority but sensitive operations remain explicit.** Customer management via `GET /users` / `GET /users/{user}` (`users.manage_authorized`) is **authorized Admin only**, audited, data-minimized, never via relaxed `/me`; credentials (`password`/`hash`/`tokens`) never exposed even to Admin via normal User/Profile serialization. | AUTHZ-ADMIN-001, USER-ADMIN-001 |
| 6 | **Credential operations are separate from ordinary profile updates.** `Password change` (`POST /auth/change-password`) and `security-sensitive email change` are **Authentication/security workflows**, not `PATCH /me {password}` / `PATCH {email_verified:true}`. Staff cannot normally change Customer passwords. | SEC-PWD-001, USER-CRED-001 |
| 7 | **Profile changes do not alter historical business records.** Changing `name`/`phone` via `PATCH /me` must never modify `Order customer`, `Order historical contact snapshot`, `Order delivery information`, `Request contact history`, `Enquiry contact history`. Saved address book remains **deferred** — no `addresses[]` in Profile. | ORD-HIST-001, USER-HIST-001 |
| 8 | **Profile is lightweight and private.** `GET /me` contains only `id`/`role`/`name`/`email`/`phone`/`email_verified`/`created_at`/`updated_at` — not `orders[]`/`cart`/`requests[]`/`enquiries[]`/`notifications[]`. Response is `PRIVATE` (`Cache-Control: private, no-store`), not publicly cacheable. `GET /me` is `AUTHENTICATED_OWNER` self only; `GET /users/{id}` for another user is `404` masked where ownership fails. | USER-PRIV-001, CACHE-001 |
| 9 | **Shared identity across platforms.** Same `User` identity works on `Next.js` and `Flutter` via same Laravel backend (`customer changes phone on website → Flutter sees same via GET /me`); backend authoritative; local role/profile cache not authoritative. | X-PLATFORM-001, USER-XPLAT-001 |
| 10 | **No self-delete or self-disable via Profile in V1.** `DELETE /me` and `PATCH /me {status: DISABLED}` are **not exposed** until retention/privacy model with `anonymization`/`soft deletion` is explicitly approved; hard deletion would conflict with `Orders`/`Requests`/`Enquiries` retention. | USER-DEL-001 |
| 11 | **Customer visibility is isolated.** `Customer A → Customer B profile` or `Customer → Staff profile` via any future generic `GET /users/{id}` must fail; `Customer` sees only own via `/me`. `Staff receives only operational customer info` (`delivery address` snapshot in `Order`, not unrestricted browse). | IDENT-006, OWN-001 |

Authority for wire semantics: `api-contract.md §29` / `api-conventions.md §29` / `api-resources.md §8` + `§13.1`; decisions `decisions.md ADR/USER-*`.

## 15. Staff/Admin Operational Business Rules (Phase 1.29)

> **Authority:** This section governs operational commerce (`STAFF`) vs administrative control (`ADMIN`) with customer-account protection. Operational via `§30` Staff/Admin contract.

| # | Business rule | Ref |
|---|---|---|
| 1 | **Closed roles remain `CUSTOMER`/`STAFF`/`ADMIN`.** `STAFF` is not via public registration; `ADMIN` via bootstrap/invitation; no `MANAGER`/`SUPPORT` etc. in V1; `{"role":"ADMIN"}` from client rejected. | AUTHZ-ROLE-001 |
| 2 | **Staff operate commerce, not customer accounts.** Staff may `view/operationally manage products/catalog` (`products.manage` where approved), `inventory` (`inventory.view/manage` where approved, `POST .../adjust` with `reason`), `orders/fulfillment` (`ORD-005..014` controlled actions), `delivery-fee` (`ORD-014`), `requests/enquiries` operational (`REQ-004..006`/`ENQ-004..006`), `operational notifications`; Staff have **zero** customer-account control (`disable/block/change role/password/impersonate/view credentials`). | STAFF-OP-001, AUTHZ-003 |
| 3 | **Customer accounts remain protected from Staff.** Staff must not `disable customer`, `block browsing/ordering`, `change role/permissions`, `change ownership`, `impersonate`, `change password`, `edit security attributes`, `delete account` via ordinary ops — explicit `403 FORBIDDEN` if attempted. No hidden staff customer-control endpoint. | STAFF-RESTRICT-001 |
| 4 | **Admin administers Staff (Admin-only).** `list/get/invite/approve/suspend/reactivate Staff` via `ADM-001..006` (`admin/staff`, `admin/staff/{user}/approve|suspend|reactivate`); `approve` requires `staff.approve` + no self-approval; all audited. Staff creation does not accept plaintext password via generic body — credential via Authentication activation (Group R deferred). | AUTHZ-ADMIN-001, STAFF-ONBOARD-001 |
| 5 | **No generic administrative PATCH for state.** `PATCH /admin/orders/{id}` / `PATCH /staff/orders/{id}` / `PATCH /admin/users/{id} {role:ADMIN}` that changes business state is prohibited; use explicit controlled `POST .../accept|ship|deliver|delivery-fee|adjust|approve`. | API-VAL-004 |
| 6 | **Operational catalog is separate from public.** Public catalog (`GET /products`) is `PUBLIC` cacheable; operational product/inventory fields (`quantity/reserved/available`, `is_active/is_published` internal, `operational notes`) are staff-only via `CAT-007..012` / `INV-002`, `PRIVATE` not leaked. | CAT-001 |
| 7 | **Inventory is operational, controlled action.** Only `POST /inventory/{inventory}/adjust {quantity_delta, reason}` (`reason` CLOSED `STOCK_RECEIPT/CORRECTION/DAMAGE/RETURN/AUDIT_ADJUSTMENT`) may change stock; server calculates `new_quantity = current + delta` transactionally; no `PATCH {quantity:999}`; prevents negative where business prohibits. | INV-001/002 |
| 8 | **Order fulfillment uses controlled actions with state+fulfillment validation.** `PICKUP: PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` and `DELIVERY: PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED` (separate branches); `Staff ship` needs `orders.ship` + `PROCESSING` + `DELIVERY`; `Customer cancel` needs `owns+eligible+20-min`; each action validates atomically, audited, `Idempotency-Key` Required, `Critical` concurrency (race → `409 CONFLICT`). | ORD-007..011, ORD-013, ORD-014, ORD-004 |
| 9 | **Variable delivery fee is Staff/Admin only.** `PICKUP fee 0 FINALIZED at creation`; `DELIVERY fee pending at checkout → Staff/Admin sets fee via ORD-014 POST .../delivery-fee {delivery_fee:{amount,currency}} → FINALIZED → total=subtotal+fee authoritative → payment eligible`; customer `delivery_fee` payload rejected; never `total` client-controlled. | PRICE-006, FUL-002 |
| 10 | **Made-to-order and enquiries handled operationally, not as orders.** Staff `GET /requests`/`GET /requests/{id}` + `PATCH {request_status}` (`SUBMITTED→IN_REVIEW→CLOSED`) + internal notes; Staff `GET /enquiries` + `POST .../close`; original customer content immutable; never automatic `request→order` conversion without explicit workflow. | REQ-005, ENQ-007 |
| 11 | **Audit is mandatory.** `actor_id/role/action/resource_type/resource_id/previous→new/timestamp/request_id` audited for `staff approval/suspend/reactivate`, `role changes`, `delivery-fee changes`, `inventory adjustments`, `order transitions`, `request/enquiry status changes`, `privileged catalog changes`; server-derived actor, no secrets; optional read `GET /admin/audit-logs` read-only. | AUDIT-001 |
| 12 | **Default deny & field-level before serialization.** Private order/management responses use `PRIVATE`/`no-store`, not CDN public; only permitted fields per actor (`reserved_quantity` not to Customer, `password` never); pagination/search operate over authorized dataset; service/webhook `SYSTEM` service auth not reused Admin credential. | AUTHZ-002 |

## 19. Endpoint Workflow Coverage (Phase 1.19 — No New Business Rules)

Endpoint review confirms already-approved business rules have endpoint support; no new business behavior is introduced in this phase (see `api-contract.md §19` for inventory):

- `Anonymous browse` (`products/categories/search/product details/prices/availability`) → `CAT-001..004` — §1
- `Clerk registration/sign-in → verified Clerk token → local Laravel User → cart → checkout (PICKUP/DELIVERY) → payment placeholder → order → tracking` → `CART-001..005` (`CART-005` merge) → `CHK-001` → `PAY-001/002` → `ORD-001..004` → `ORD-003/012` — §§3,4,6,7,8,17 (guest-cart handoff is backend authority per §3 #4: anonymous `X-Guest-Cart-Id`/`guest_cart_id` cookie, opaque, `HttpOnly`, merged through Clerk-authenticated `CART-005`)
- `Pickup` (`PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED`) and `Delivery` (`PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED`) → `ORD-007..011` + `ORD-013` with explicit `orders.accept/process/ready_for_pickup/ship/deliver/complete` + state (`ORD-013` `complete` closes `DELIVERED→COMPLETED` and `READY_FOR_PICKUP→COMPLETED` per `api-contract.md §19.1`) — §7, §18.10; state history records each transition (`order_status_history` per §6)
- `Cancellation (20-min window, eligible state, own order)` → `ORD-004` — §8
- `Made-to-order request (+ optional attachment, anonymous allowed, own history)` → `REQ-001..007` (`REQ-007` `POST /requests/{request}/attachments`) — §9, §11
- `General enquiry (+ optional attachment, anonymous allowed)` → `ENQ-001..007` (`ENQ-007` `POST /enquiries/{enquiry}/attachments` mirrors `REQ-007`) — §10, §11
- `Staff order processing / fulfillment / requests / enquiries / operational notifications` → `ORD-005/006` + `ORD-007..011` + `ORD-013` + `ORD-012` tracking, `REQ-004..006`, `ENQ-004..006` — §§6,18.4, §18.6
- `Admin staff approval/management, catalog/inventory management` → `ADM-001..006`, `CAT-007..012`, `INV-001..003` — §§12,18.6, §17.1
- `Payment` and `webhook` are generic placeholders owned by **Group H** — no new payment business rule in this phase — §5, §14

If a workflow had lacked endpoint support, it would be flagged as incomplete per `api-contract.md §19.11` gating — none for V1.

## 20. Cross-Domain Invariants (Phase 1.30 — Verified 2026-09-03)

Per `api-contract.md §31.27`, the 20 invariants below were verified across `§15`/`§17`/`§18`/`§21`/`§22`/`§23`/`§24`/`§25`/`§26`/`§27`/`§28`/`§29`/`§30`:

1. Public catalog browsing does not require authentication.
2. Checkout requires an authenticated `CUSTOMER`.
3. Customers can only access their own private commerce resources.
4. Staff cannot control or restrict ordinary customer accounts.
5. Admin has the highest approved operational authority.
6. Version 1 roles are CLOSED `CUSTOMER|STAFF|ADMIN`.
7. Order status transitions are controlled actions.
8. Order financial totals are server-authoritative.
9. Customer cannot set delivery fee.
10. Pickup has zero delivery fee.
11. Delivery fee is assigned by Staff/Admin (`ORD-014`).
12. Payment consumes the final server-authoritative amount.
13. Made-to-order requests do not automatically create orders.
14. Enquiries do not automatically create orders.
15. Notifications do not become the source of truth.
16. Historical order values remain immutable.
17. Cart does not reserve inventory.
18. Checkout revalidates inventory/pricing atomically.
19. Privileged state changes are auditable.
20. Core business transactions do not depend on successful notification delivery.

Additional invariants from `§31`: `total = subtotal + delivery_fee` (minor units `TZS`), `delivery_fee` nullable `PENDING` + `FINALIZED` via `ORD-014`, `cart→order` historical snapshot, `MADE_TO_ORDER` not purchasable, `Idempotency-Key` required on `CHK-001`/`ORD-004`/`ORD-007..011`/`ORD-013`/`ORD-014`/`INV-003`/`ADM-004..006` (and `ORD-012` is safe `GET`, no key), `409` on stale state, `404` masked for private.

## Quick Non-Negotiables (for staff)

- Customers **can** browse, search, request, and enquire without an account.
- Customers **cannot** check out without an account.
- Customers **cannot** set their own total or delivery charge.
- The system **never** sells more stock than it has.
- Pickup is free; delivery charges are set by the business by location.
- Made-to-order requests are leads, **not** orders.
- Order status only moves along approved steps.
- Customers can cancel within 20 minutes of ordering.
- "Cancelled" does not automatically mean refunded.
- **Frontend validation is advisory; backend validation is authoritative — never trust client-supplied business state.**

---

## 19. Security Review — Abuse Protection and Operational Boundaries (Phase 1.33)

> Customers remain free to browse and purchase; Staff remain operational not account-administrative; Admin remains audited. Security does not weaken business workflows.

| # | Business rule | Ref |
|---|---|---|
| 1 | **Rate limiting protects abuse-prone operations** — `register 5/h/IP`, `login 10/min/IP`, `password recovery 3/min/IP`, `anonymous requests/enquiries 3/min/IP`, `checkout 5/min/user`, `cart writes 30/min/user`, `cancellation 5/min/user`, `attachments 10/h per X-Upload-Token ID + parent + IP` for anonymous (scoped, no user key; `5MB` max `1` per parent) and `10/h per user` for authenticated, `inventory adjust 20/min/staff`, `admin staff approve 30/min/admin` — `429 RATE_LIMITED` with standard `Retry-After` header. Anonymous spam does not become private data enumeration. | SEC-RATE-001 |
| 2 | **Guest cart tokens are opaque bearer credentials (cryptographically secure, high-entropy `UUIDv4` CSPRNG ≥122 bits, not `UUIDv1`/sequential/counter, unpredictable, not guessable) scoped strictly to bound guest cart** — `X-Guest-Cart-Id` (header) and `guest_cart_id` (cookie) `uuid` authorizes that cart only (anonymous cart request has no authenticated principal, so validating this token necessarily authorizes access to its bound guest cart); never authorizes account operations and never authorizes merge by itself (merge requires authenticated principal + valid guest token + server-side check); retired after merge; predictable `guest_123`/`UUIDv1` rejected. | CART-GST-001 |
| 3 | **Attachment uploads require parent ownership + scoped token** — `REQ-007/ENQ-007` `X-Upload-Token` single-use time-limited, verified atomically with parent `request/enquiry` ownership; `Customer A → Customer B` attachment pollution returns `404` masked; no permanent public URL; `url` temporary signed. | ATTACH-001 |
| 4 | **Financial values remain server-controlled and bounded** — `delivery_fee` `0..5000000` minor units (50,000 TZS max) `TZS` only; `PICKUP` `0 FINALIZED`; `DELIVERY` `PENDING→FINALIZED once before PAID` then immutable; `total = subtotal + delivery_fee` server-calculated; huge/negative fee rejected `422`. | PRICE-006 |
| 5 | **Idempotency keys are not authentication** — `Idempotency-Key` `uuid` scoped to `(identity+endpoint+key)` 24h; cross-identity reuse treated as new; same key + different body → `409 DUPLICATE_OPERATION`; never log with PII. | IDEMP-001 |
| 6 | **Transport and browser boundaries enforced** — `HTTPS` `HSTS` `TLS 1.2+` in deployed envs; `Secure HttpOnly SameSite=Strict` cookies; `CSRF` `X-CSRF-Token` double-submit for cookie-auth mutations; `CORS` allow-list not `*` with credentials; `CORS ≠ auth`. | SEC-TRANS-001 |
| 7 | **Caching respects privacy** — `Cart, Checkout, Orders, Tracking, Notifications, Profile, private Request/Enquiry` `Cache-Control: private, no-store` `Vary: Authorization, Cookie`; public catalog `public, max-age=300` CDN-cacheable with no private fields. | CACHE-001 |
