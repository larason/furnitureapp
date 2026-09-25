# Phase 7.1 - Checkout Requirements

**Status:** PASS

**Scope:** Requirements and dependency review for `CHK-001`. This phase does
not activate the checkout route or implement reservation/order creation.

## Contract

The only checkout operation is:

```http
POST /api/v1/checkout
```

The route remains a stub until the later Group G implementation phases. The
frozen response is the `CheckoutResponseData` object inside the standard
`data` envelope. Checkout returns HTTP `201` on a newly created order and
returns the same `201` response on a successful idempotent replay.

## Actor and Cart Authority

- Only an authenticated local `CUSTOMER` may check out.
- Anonymous checkout, including a guest-cart credential without an
  authenticated customer, returns `401 AUTHENTICATION_REQUIRED`.
- A guest must authenticate and complete `CART-005` before checkout.
- The server derives the authenticated customer's own `ACTIVE` Cart.
- The request accepts no cart selector, owner selector, guest token, or user
  identity. Ownership is never taken from request data.
- A missing active Cart and an empty active Cart both return `422 CART_INVALID`.
- Staff/Admin personal cart support does not broaden `CHK-001`; the frozen
  checkout actor remains CUSTOMER-only. This is **NO GAP** at the contract
  boundary, with any future policy change requiring an explicit contract
  decision.

After success, the same Cart row remains `ACTIVE`; only its items are cleared.
After any ordinary validation, stock, conflict, or transaction failure, Cart
items remain unchanged.

## Request Contract

The strict JSON allow-list is:

```json
{
  "fulfillment_type": "PICKUP"
}
```

or:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha Mwangi",
    "phone": "+255700000001",
    "address_line": "Jengo Street 12",
    "city": "Dar es Salaam"
  }
}
```

`additionalProperties` is false. `fulfillment_type` is the closed enum
`PICKUP|DELIVERY`. For `PICKUP`, `delivery_address` must be absent or `null`.
For `DELIVERY`, it is required and is validated as a historical snapshot
input. Saved addresses and `saved_address_id` are deferred.

Delivery-address validation is frozen as follows:

- The object has exactly `recipient_name`, `phone`, `address_line`, and
  `city`; unknown nested fields are rejected.
- All four fields are required for `DELIVERY`, must be JSON strings, and are
  trimmed server-side. A missing, `null`, empty, or whitespace-only value is
  rejected. The normalized value must remain non-empty.
- `recipient_name` is limited to 255 characters by the existing Order/Delivery
  persistence boundary.
- `phone` is trimmed and normalized to the contract's E.164-ish representation,
  with a maximum of 30 characters. It is not optional and is not inferred from
  the customer profile.
- The frozen Checkout contract defines no additional numeric maximum for
  `address_line` or `city`; those fields remain trimmed, non-empty strings.
  Later implementation must not invent a new V1 API limit without a contract
  decision.
- No phone verification, saved-address lookup, address normalization beyond
  the stated trimming/phone normalization, or profile fallback is performed.

Client-controlled cart, identity, product, price, money, stock, order,
payment, status, reference, timestamp, and delivery-fee fields are forbidden
and must fail strict validation rather than being ignored.

## Validation Order

The eventual workflow must execute these authority boundaries in order:

1. Authenticate and authorize the `CUSTOMER` actor. An unauthenticated request
   receives `401 AUTHENTICATION_REQUIRED` before request-shape or idempotency
   validation is exposed.
2. Validate the required `Idempotency-Key` header and its UUID format.
3. Validate transport, JSON content type, strict request shape, and enum.
4. Normalize the request and calculate the identity/endpoint/key fingerprint.
5. Resolve an existing idempotency result before checking whether the Cart is
   now empty. A matching completed result replays the original response.
6. Lock and load the authenticated customer's own active Cart inside the
   checkout transaction.
7. Reject missing/empty Cart and revalidate every Cart line against current
   Product, Category, Variant, and stock state.
8. Re-read current authoritative prices and calculate line totals and
   subtotal using integer minor-unit arithmetic.
9. Validate the fulfillment branch and snapshot its data.
10. Reserve stock, create the complete pending Order and snapshots, record the
   initial status history, and clear Cart items atomically.
11. Persist the successful idempotency result only as part of the same
   committed business transaction and return the frozen response.

No payment provider or external call occurs in this workflow.

## Pricing and Fulfillment

Money is integer minor units with currency `TZS`; no floats are allowed.
Checkout ignores Cart display values as transaction authority and recalculates
every line from the current catalog price.

### PICKUP

```text
delivery_fee        = {amount: 0, currency: TZS}
delivery_fee_status = FINALIZED
delivery_address    = null
```

### DELIVERY (Model B)

Checkout does not calculate, estimate, or accept a delivery fee. It creates:

```text
delivery_fee        = null
delivery_fee_status = PENDING
total               = subtotal  # required provisional response/storage value
```

This explicitly reconciles the required OpenAPI `total` field with Model B.
The provisional total is not payable and must not be presented as final.
`ORD-014` later sets the authoritative fee and changes the total to
`subtotal + delivery_fee`; payment remains blocked while the fee is pending.

## Product, Variant, and Stock Authority

Every Cart line is revalidated transactionally. Product state must be current,
active, published, not soft-deleted, categorized as publicly purchasable, and
`IN_STOCK`. A `MADE_TO_ORDER` line fails with `422 PRODUCT_NOT_PURCHASABLE`
and there is no partial checkout. The referenced Variant must exist, be active,
belong to the exact Product, and remain the Variant represented by the CartItem.

Stock authority is current `ProductStock`, not the Cart projection:

```text
available = quantity - reserved_quantity
```

The requested quantity must be available while locked. The existing
`InventoryAllocator::reserve(Order)` is the reservation primitive. It already
uses deterministic stock-row locking, supports multi-line/all-or-nothing
reservation, and persists exact per-location allocations in
`order_item_inventory_allocations`. Therefore future cancellation, payment
failure, expiry, and payment-success consumption can call `release(Order)` or
`consume(Order)` against the exact allocations. This is **NO GAP** for
reservation traceability.

Checkout increments `reserved_quantity` only; it does not decrement physical
`quantity`. A multi-line failure leaves no reservation, Order, OrderItem, or
Cart mutation. `INSUFFICIENT_STOCK` is `422` for CHK-001, matching the existing
reservation primitive and frozen cart/catalog error mapping.

## Order Snapshot

Successful checkout creates exactly one `PENDING_PAYMENT` Order owned by the
authenticated customer. The server generates the opaque API order ID and the
`OD-` reference using `ReferenceGenerator` with the repository's five-character
suffix format. The client supplies neither.

Each OrderItem snapshots the validated Product/Variant identity, SKU, product
name, variant name, quantity, current unit price, and line total. The initial
`PENDING_PAYMENT` status history event is created in the same transaction.

`DELIVERY` snapshots `recipient_name`, `phone`, `address_line`, and `city` in
the Order fulfillment data. `PICKUP` stores no fake address. A Delivery row is
not created by CHK-001; the existing Delivery model is a later fulfillment
record and, when created, must match the Order snapshot.

## Idempotency

`Idempotency-Key: <uuid>` is mandatory and uses the shared
`IdempotencyService`, scoped by authenticated identity, action, and key. The
logical fingerprint includes normalized `fulfillment_type` and
`delivery_address`, plus the server-derived identity required for safe scope;
volatile price and stock are not fingerprint inputs.

- Same customer, same key, same intent: replay the original `201` and exact
  `CheckoutResponseData`; do not inspect the now-empty Cart first.
- Same customer, same key, changed fulfillment or address: `409
  DUPLICATE_OPERATION`.
- Same key used by another customer: separate identity scope, not a replay.
- Concurrent same-key requests: one Order, one reservation effect, one Cart
  clear, and one logical response.
- Different keys racing on one Cart: Cart locking permits at most one Order.

The shared service is **IMPLEMENTATION GAP** for Checkout until it preserves
the operation's response status (`201`, not hard-coded `200`) and guarantees
the completed response is committed atomically with the Order transaction.
No second idempotency subsystem should be created.

## Atomicity and Locking Requirements

The eventual Phase 7.7 transaction must include:

| Operation | Atomic with CHK-001 |
| --- | --- |
| Own Cart lock and final Cart snapshot | Yes |
| Product/Variant validation and price reads | Yes |
| Stock validation and reservation | Yes |
| Order and OrderItem inserts | Yes |
| Initial status history | Yes |
| Fulfillment snapshot | Yes |
| Cart item clear, retaining ACTIVE Cart | Yes |
| Successful idempotency result | Yes |
| Payment provider call | No; Group H |

Lock ordering must be deterministic and shared with the allocator: idempotency
claim/result coordination, active Cart, then Order/OrderItems and inventory
rows using the allocator's established order. The implementation must avoid
duplicate checkout from concurrent keys and avoid deadlocks with reservation,
release, consume, and inventory adjustment workflows.

## Error Mapping

Known CHK-001 mappings are:

| Condition | HTTP/code |
| --- | --- |
| Missing/invalid authentication | `401 AUTHENTICATION_REQUIRED` |
| Empty or missing active Cart | `422 CART_INVALID` |
| MADE_TO_ORDER line | `422 PRODUCT_NOT_PURCHASABLE` |
| Invalid or wrong-parent Variant | `422 INVALID_PRODUCT_VARIANT` |
| Insufficient stock | `422 INSUFFICIENT_STOCK` |
| Missing, wrong-type, or unknown `fulfillment_type` | `422 MISSING_REQUIRED_FIELD`, `INVALID_TYPE`, or `INVALID_VALUE`, as applicable, with field `fulfillment_type` |
| Valid fulfillment enum with an invalid branch combination, such as a populated address on `PICKUP` | `422 INVALID_FULFILLMENT` |
| Missing `delivery_address` or a missing/wrong-type/unknown nested address field | `422 MISSING_REQUIRED_FIELD`, `INVALID_TYPE`, or `INVALID_VALUE`, as applicable, with the canonical address field path |
| Syntactically valid DELIVERY address rejected by domain rules, such as invalid normalized phone data | `422 INVALID_DELIVERY_INFORMATION` |
| Same key, changed intent | `409 DUPLICATE_OPERATION` |
| Unresolved concurrent/idempotency claim | `409 CONFLICT` |
| Rate limit | `429` with `Retry-After` |

All failures use the standard `errors[]` and `meta.request_id` envelope and
private `no-store` cache semantics.

## Findings and Ownership

| Finding | Classification | Owner |
| --- | --- | --- |
| CUSTOMER-only checkout versus Staff/Admin self-cart support | NO GAP | Frozen contract; preserve |
| Model B provisional DELIVERY total | NO GAP | Frozen by ADR/API-ORD-001 and OpenAPI-compatible semantics |
| Exact reservation traceability | NO GAP | `InventoryAllocator` + allocation model |
| Cart clear-and-retain ACTIVE semantics | NO GAP | Group F closure and current Cart model |
| Checkout response status in shared idempotency replay | IMPLEMENTATION GAP | Phase 7.7/7.9 |
| Idempotency success/result atomicity with checkout transaction | IMPLEMENTATION GAP | Phase 7.7 |
| Existing address helper requires `region`, while frozen CHK-001 requires `city` | IMPLEMENTATION GAP / DOC DRIFT | Phase 7.2/7.8 must reconcile the helper without changing the frozen API |
| Full Checkout service, request, transaction, and route activation | DEFERRED | Phases 7.2-7.9 |
| Payment, fee finalization, release/consume, and expiry workflows | DEFERRED | Group H and Order operations |

No migration, API schema, frontend, or dependency change is introduced in
Phase 7.1.

## Required Future Tests

Later Group G tests must cover strict tampering rejection, no/missing/empty
Cart, stale Product and Variant state, MADE_TO_ORDER, price drift, PICKUP and
DELIVERY branches, provisional total semantics, same-key replay after Cart
clear, changed-input conflict, concurrent same-key and different-key races,
last-unit overselling, multi-line rollback, exact allocation release/consume,
Order snapshots/history, and Cart preservation on every failed attempt.

## Exit and Next Phase

Phase 7.1 is complete because CHK-001 now has explicit actor, Cart, request,
pricing, Model B, reservation, traceability, idempotency, atomicity, error,
and Group H boundaries. Phase 7.2 is **READY** to review the address model,
provided it resolves the existing `city` versus `region` implementation drift
without changing the frozen Checkout API.
