# Business Rules — Version 1 (Domain Authority)

> **Authority:** This file is the plain-language source for business rules referenced by the API contract. Technical invariant IDs are in brackets; see `phases/domain-invariants.md` and `phases/business-to-data-traceability.md`. The backend (Laravel) is the authoritative enforcer of all rules below — clients (Next.js, Flutter, Admin) are advisory only for UX (Phase 1.15 Validation Conventions). Where this file and `docs/api/*` overlap, this file governs business meaning; `api-contract.md` / `api-conventions.md` govern wire representation.

## Overall Promise

The computer system is always the final authority on what is allowed, what costs what, and what is in stock. The website and mobile app help customers shop, but they can never override the system's decisions. **Frontend validation is advisory; backend/domain validation is authoritative** (API-VAL-002).

---

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
| 1 | The stock number shown to customers is informative only. The system makes the real, final stock check. | INV-001 |
| 2 | Available stock can never go below zero. | INV-002 |
| 3 | If two customers try to buy the last item at the same time, only one can succeed. The system never sells more than it has. | INV-003 |
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
| 5 | Customers, staff, and admins all use the same account system; a person's role decides what they may do. | IDENT-007 |

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

**Cart Validation & Domain Authority Note (Phase 1.21):** Cart mutations (`CART-001..CART-005`) are customer-isolated actions. The server computes all display subtotals and line totals using minor unit arithmetic (`{amount, currency}`). Clients can only specify product identity, variant choice, and quantity (1..100). Final inventory locking and payment calculation occur exclusively during Checkout (`CHK-001`).

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
| 7 | **Checkout recalculates authoritative pricing** — current catalog unit prices are resolved at checkout; `subtotal/delivery_fee/total` are server-calculated `{amount,currency:"TZS"}` integer minor units; client `total`/`price`/`currency` is rejected. | PRICE-001/002/003, CHK-PRICE-001 |
| 8 | **`MADE_TO_ORDER` cannot enter normal checkout.** Any `MADE_TO_ORDER` product in cart is rejected (`PRODUCT_NOT_PURCHASABLE` 422); it must use the Request workflow (`REQ-001`). | CAT-003, REQ-ACT-001, CHK-TYPE-001 |
| 9 | **Fulfillment is `PICKUP` or `DELIVERY` only** (CLOSED). `DELIVERY` requires valid `delivery_address` (recipient_name/phone/address_line/city); `PICKUP` requires no delivery address. | FUL-001, CHK-FUL-001 |
| 10 | **Delivery fee is not customer-controlled.** Customer chooses `DELIVERY` (supplies address); **Staff/Admin add the variable location-based fee**; backend stores authoritative `delivery_fee`. Customer `delivery_fee` payload is rejected. Flat `20,000` rate superseded. | PRICE-006, FUL-002, CHK-FEE-001 |
| 11 | **Delivery-fee timing is Model B (fee-after-order) in V1** — `checkout → Order PENDING_PAYMENT (subtotal authoritative, delivery_fee pending) → Staff/Admin sets delivery_fee → final total authoritative → payment (Group H) on final total`. Order can be created before final delivery fee; payment amount must equal final authoritative Order total. No silent `pay before fee → staff changes total` mismatch. | CHK-FEE-002, PRICE-006, ORD-001 |
| 12 | **Customer cannot set authoritative subtotal, total, price, stock, status, reference, or cart ownership.** These are server-generated or server-calculated. | SEC-001, CHK-AUTH-002 |
| 13 | **Empty cart checkout is prohibited** — `POST /checkout` with empty cart returns `CART_INVALID` (422, conceptual `CART_EMPTY`). No empty order is created. | CART-001, CHK-EMPTY-001 |
| 14 | **Checkout is concurrency-sensitive and idempotency-required.** `Idempotency-Key` header required; same key replays original result; same key + different input → `409 CONFLICT`; inventory + order + cart mutations occur atomically. | CHK-IDEM-001, INV-003 |
| 15 | **Cart after success is cleared/inactivated; cart after validation failure is preserved.** Stale `is_purchasable:false` lines remain until checkout rejects (`CART_INVALID`); failed checkout does not empty the cart where safe. | CART-STALE-001, CHK-CART-001 |
| 16 | **Successful checkout creates an Order** (`PENDING_PAYMENT`) with historical snapshots (items, prices, addresses, fulfillment). Order lifecycle, delivery, payment and tracking are handled thereafter. | ORD-001, CHK-ORD-001 |
| 17 | **Checkout does not implement payment provider logic.** Provider integration, callbacks, webhooks, provider statuses belong to Group H. | PAY-002, CHK-PAY-001 |

**Checkout Authority Note (Phase 1.22):** Delivery address supplied at checkout becomes part of the Order's historical fulfillment snapshot (not a live profile reference). Saved address book is deferred. Checkout requires `Idempotency-Key`; inventory validation and state-changing operation are safe under concurrent checkout attempts. All financial calculations use integer minor units, never floating arithmetic.

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
| 7 | Customers can follow their order through milestones (Paid, Accepted, Processing, Ready for Pickup / Shipped, Delivered, Completed). Only milestones for their fulfillment type are shown. | FUL-005/006 |
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

> Tracking = customer-facing `timeline` of fulfillment progress (`PAID, ACCEPTED, PROCESSING, READY_FOR_PICKUP, SHIPPED, DELIVERED, COMPLETED` filtered by fulfillment); Fulfillment = operational `Order → Staff → Process → Ship/Deliver/ReadyForPickup → Complete` (small-scale, not logistics platform).

| # | Business rule | Ref |
|---|---|---|
| 1 | **Pickup and Delivery follow distinct operational paths.** `PICKUP: PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED` (no `SHIPPED`/`DELIVERED`); `DELIVERY: PENDING_PAYMENT→PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED` (no `READY_FOR_PICKUP`). Cross-branch `PICKUP→SHIPPED` rejected. | FUL-003, ORD-007 |
| 2 | **Pickup uses `READY_FOR_PICKUP` before completion** — Staff marks `PROCESSING→READY_FOR_PICKUP` (`ORD-009`) when ready for collection; `READY_FOR_PICKUP→COMPLETED` (`ORD-013` explicit Staff confirms collection). Single pickup location V1. | FUL-002, ORD-009 |
| 3 | **Delivery uses `SHIPPED` then `DELIVERED` before completion** — Staff ships `PROCESSING→SHIPPED` (`ORD-010`: left business), then marks `SHIPPED→DELIVERED` (`ORD-011`: delivery completed), then `DELIVERED→COMPLETED` (`ORD-013`). No GPS/`tracking_number`/`carrier`. | FUL-002, ORD-010 |
| 4 | **Customer can track own Order** — `GET /me/orders/{order}/tracking` (`ORD-003`) own tracking `AUTHENTICATED_OWNER` only; `Customer A → Customer B tracking` fails `404` masked. Staff tracking `ORD-012` operational richer view. | ORD-003, FUL-001 |
| 5 | **Staff operate fulfillment** — `orders.ready_for_pickup / ship / deliver / complete` with `valid state + fulfillment_type + permission` (`ORD-009/010/011/013`, `Idempotency-Key` Required, concurrency `Critical`); once authorized, no repetitive Admin approval. | STAFF-OP-001 |
| 6 | **Customer cannot modify fulfillment state** — `POST {status:"SHIPPED"}` / `mark delivered` / `mark ready for pickup` from customer rejected `403/409` (`FULFILLMENT_ACTION_NOT_ALLOWED`); only Staff/Admin via `ORD-009/010/011`. | FUL-002, ORD-007 |
| 7 | **Fulfillment events do not rewrite historical financial data** — `ship/ready/deliver` does not change `historical unit_price/subtotal/delivery_fee` (except `ORD-014` fee `PENDING→FINALIZED` before `PAID`), nor `billing_address` snapshot; inventory `reserved_quantity` not exposed via tracking. | ORD-HIST-001 |
| 8 | **Tracking is read-only and derived** — customer `GET` only (`POST/PATCH/DELETE /tracking` prohibited), `current_status == Order.status`, `timeline` chronological `occurred_at ASC, id ASC` `ISO8601 Z`, `id` stable `evt_...`, `append-only` (customer cannot edit, staff cannot rewrite past — correction requires admin workflow), no `GPS`/`courier integration`/`assigned_staff` in V1, `REST GET` polling (no `WebSockets`). | FUL-001, FUL-004 |
| 9 | **Privacy & caching** — customer tracking `private, no-store` (PII/financial, not CDN); `delivery_address` visible only to owns customer + authorized Staff/Admin; internal `staff notes` never to customer; lightweight (not `entire Order`). | FUL-001 |
| 10 | **Payment boundary** — fulfillment/payment distinct (`PAID ≠ SHIPPED`); `SHIPPED` does not mean paid, `PENDING_PAYMENT` with `delivery_fee PENDING` blocks `PAY-001` `409`; tracking does not implement payment. | PAY-002 |

**Tracking Authority Note (Phase 1.24):** Customer tracking `ORD-003` is `REST GET` polling (poll on open, pull-to-refresh) — no `WebSockets/SSE/GPS`. Staff fulfillment `ORD-009/010/011/013` are `Idempotency-Required`, `Critical` concurrency (e.g., `cancel+ship` race → `409 ORDER_STATE_CONFLICT`), validated `actor+permission+current state+fulfillment` atomically.

## 9. Made-to-Order Requests

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can request a "Made to Order" item — no account needed, as long as they leave contact information (email and/or phone). | REQ-001 |
| 2 | A request can mention an existing product, but does not have to. `product_id` nullable, must belong/active when supplied. | REQ-002 |
| 3 | A request can include quantity, dimensions, preferred material/color, and notes, plus optional picture/file. File validation: size, content type, extension, signature not client MIME alone. | REQ-003, ATTACH-001 |
| 4 | **A request is never an order.** It does not create an order, a payment, or reserve any stock. | REQ-004 |
| 5 | In Version 1, requests are leads for the business to follow up. | REQ-005 |
| 6 | No final price (quotation) is assumed at the time of the request. | REQ-006 |

Validation: `contact information, product reference when supplied, quantity when supplied, dimensions, material, color, notes, attachments` — anonymous `user=null` is valid.

## 10. General Enquiries

| # | Business rule | Ref |
|---|---|---|
| 1 | Anyone can send a general enquiry — no account needed, as long as they give contact information. | ENQ-001 |
| 2 | An enquiry must include contact details and a message. | ENQ-002 |
| 3 | An enquiry is communication, not an order. It never creates an order or payment. | ENQ-003 |
| 4 | Enquiries and made-to-order requests are two different things and never merged. | ENQ-004 |

Validation: `contact information, subject where required, message, attachments`. Authenticated User association optional, not mandatory.

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

## 13. Notifications and Emails

| # | Business rule | Ref |
|---|---|---|
| 1 | The system may keep notification records tied to events such as an order changing status. | NOTIF-001 |
| 2 | Real email/SMS/push delivery is a later addition; V1 does not depend on sending emails. | NOTIF-002/003 |

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
| 1 | **Customers can self-register without staff approval** via public registration (`Visitor → Register → Customer account → Authenticate`). Staff/admin accounts are not via public registration. | AUTH-001, IDENT-001 |
| 2 | **Public catalog browsing is always anonymous-allowed** (`products`, `categories`, `search`, `product details`, `prices`, `availability`) and SSR pages (`/products`, `/products/{slug}`, `/categories/{slug}`) must render without login. | IDENT-003, SEO-001 |
| 3 | **Checkout requires authentication** — anonymous checkout is rejected (`AUTHENTICATION_REQUIRED`/`CHECKOUT_REQUIRES_AUTHENTICATION` 401); backend enforces. | IDENT-002, ORD-002, CART-CHECKOUT-001 |
| 4 | **Anonymous requests/enquiries are allowed** (`Made-to-order Request`, `General Enquiry` with `User=none` + required contact); when authenticated they link to own history (`My Requests`). | REQ-001, ENQ-001 |
| 5 | **Staff are approved by Admin only** (`Staff candidate → Admin review → Approved → Active` or `Admin invite → activate`); self-registration as `STAFF` via public form is prohibited; staff cannot approve themselves and customers cannot approve staff. | AUTHZ-STAFF-001, STAFF-ONBOARD-001 |
| 6 | **Staff cannot restrict ordinary customer browsing or ordering** — no `disable product browsing`, `block legitimate checkout`, `change role/password`, `lock account`, `impersonate`, `can_order` switch without explicit Admin-only security policy. | STAFF-RESTRICT-001, IDENT-006 |
| 7 | **Customer accounts belong to customers** — staff must not browse as customer, alter profile arbitrarily, access credentials, or impersonate; admin customer-account ops (review, disable compromised, force reset, revoke sessions) are explicit auditable security operations, not casual management. Account hard-delete not assumed (historical orders/payments/requests must remain). | IDENT-006, OWN-001 |
| 8 | **Shared cross-platform identity** — a customer registered on website is same account on Flutter app (one identity, no duplicates). `Next.js` uses first-party browser session (httpOnly cookie), `Flutter` uses API credential, same Laravel backend. | IDENT-007, X-PLATFORM-001 |
| 9 | **Role assignment is server-controlled** — client self-promotion (`{"role":"ADMIN"}`) rejected; CLOSED roles `CUSTOMER`/`STAFF`/`ADMIN`; adding `MANAGER` etc. requires review. | AUTHZ-ROLE-001 |
| 10 | **Passwords are never plaintext** — stored only as secure one-way hash; never returned in API responses (`password`/`hash`/`reset_token`/`session_token` never serialized). Password change via authenticated secure workflow, not `PATCH /me {password}`; recovery via secure time-limited single-use token (email delivery Group R deferred; generic “Request received.” without enumeration). | SEC-PWD-001, SEC-CRED-001 |
| 11 | **Verification & enumeration protection** — `email_verified_at` is server-set via secure token (`email_verified:true` from client not authoritative); login/recovery responses avoid distinguishing `email exists` vs `not exists` unless justified; brute-force rate limiting and abuse detection identified for later. | SEC-ENUM-001, SEC-RATE-001 |
| 12 | **Sessions are revocable and multi-device permitted for Customer** — logout invalidates server session/credential; multiple sessions (web/phone/tablet) permitted, logout on one does not kill others; durations (`active→expired→revoked`) chosen later. | SESSION-001 |

- Staff approval and role changes remain **auditable** (`who approved? when? what changed?`); security events (`login success/failure`, `logout`, `password change/reset`, `staff approval`, `role change`, `session revocation`) are identified for later logging without logging passwords/tokens.

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

## 19. Endpoint Workflow Coverage (Phase 1.19 — No New Business Rules)

Endpoint review confirms already-approved business rules have endpoint support; no new business behavior is introduced in this phase (see `api-contract.md §19` for inventory):

- `Anonymous browse` (`products/categories/search/product details/prices/availability`) → `CAT-001..004` — §1
- `Customer registration/login → cart → checkout (PICKUP/DELIVERY) → payment placeholder → order → tracking` → `AUTH-001/002` (login merges `X-Guest-Cart-Id` guest cart) → `CART-001..005` (`CART-005` merge) → `CHK-001` → `PAY-001/002` → `ORD-001..004` → `ORD-003/012` — §§3,4,6,7,8,17 (guest-cart handoff is backend authority per §3 #4: anonymous `X-Guest-Cart-Id`/`guest_cart_id` cookie, opaque, `HttpOnly`, merged on `AUTH-002`/`CART-005`)
- `Pickup` (`PAID→ACCEPTED→PROCESSING→READY_FOR_PICKUP→COMPLETED`) and `Delivery` (`PAID→ACCEPTED→PROCESSING→SHIPPED→DELIVERED→COMPLETED`) → `ORD-007..011` + `ORD-013` with explicit `orders.accept/process/ready_for_pickup/ship/deliver/complete` + state (`ORD-013` `complete` closes `DELIVERED→COMPLETED` and `READY_FOR_PICKUP→COMPLETED` per `api-contract.md §19.1`) — §7, §18.10; state history records each transition (`order_status_history` per §6)
- `Cancellation (20-min window, eligible state, own order)` → `ORD-004` — §8
- `Made-to-order request (+ optional attachment, anonymous allowed, own history)` → `REQ-001..007` (`REQ-007` `POST /requests/{request}/attachments`) — §9, §11
- `General enquiry (+ optional attachment, anonymous allowed)` → `ENQ-001..007` (`ENQ-007` `POST /enquiries/{enquiry}/attachments` mirrors `REQ-007`) — §10, §11
- `Staff order processing / fulfillment / requests / enquiries / operational notifications` → `ORD-005/006` + `ORD-007..011` + `ORD-013` + `ORD-012` tracking, `REQ-004..006`, `ENQ-004..006` — §§6,18.4, §18.6
- `Admin staff approval/management, catalog/inventory management` → `ADM-001..006`, `CAT-007..012`, `INV-001..003` — §§12,18.6, §17.1
- `Payment` and `webhook` are generic placeholders owned by **Group H** — no new payment business rule in this phase — §5, §14

If a workflow had lacked endpoint support, it would be flagged as incomplete per `api-contract.md §19.11` gating — none for V1.

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
