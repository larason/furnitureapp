# Phase 7.3 — Pickup Flow

## Purpose

Implement the **PICKUP fulfillment branch** of Checkout without prematurely implementing the full CHK-001 transaction.

This phase should make the backend capable of producing the correct PICKUP-specific fulfillment and financial state for a future successful Checkout transaction.

The frozen PICKUP semantics are:

```text
fulfillment_type    = PICKUP
delivery_address    = null
billing_address     = null
delivery_fee        = {amount: 0, currency: TZS}
delivery_fee_status = FINALIZED
total               = subtotal
status              = PENDING_PAYMENT
payment             = null
```

Payment remains Group H.

Inventory reservation remains part of the later Checkout transaction boundary.

---

# 1. Scope

Phase 7.3 owns:

```text
PICKUP fulfillment semantics
PICKUP request branch
PICKUP address prohibition
zero delivery-fee representation
FINALIZED delivery-fee state
PICKUP total relationship
PICKUP Order snapshot preparation
PICKUP response preparation
PICKUP-specific domain tests
```

It does NOT own:

```text
DELIVERY workflow
delivery-address snapshot implementation
delivery fee assignment
ORD-014
full Checkout transaction
inventory reservation orchestration
Cart locking
final idempotency transaction
payment provider
payment creation
payment webhook
frontend
```

---

# 2. Starting State

Assume:

```text
Group F — PASS / CLOSED
Phase 7.1 — PASS
Phase 7.2 — address model reviewed/aligned
```

Phase 7.1 froze:

```text
CUSTOMER-only checkout
own ACTIVE Cart
strict request
PICKUP | DELIVERY CLOSED enum
current Product/Variant/price authority
Checkout-time inventory reservation
PENDING_PAYMENT Order
Cart clear only after successful transaction
Idempotency-Key required
```

Do not revisit those decisions.

---

# 3. Frozen PICKUP Contract

For:

```json
{
  "fulfillment_type": "PICKUP"
}
```

the server must produce fulfillment state equivalent to:

```text
fulfillment_type    = PICKUP
delivery_address    = null
billing_address     = null
delivery_fee        = 0 TZS
delivery_fee_status = FINALIZED
total               = subtotal
payment             = null
```

---

# 4. PICKUP Has No Delivery Address

A PICKUP Checkout does not accept a populated:

```text
delivery_address
```

Valid:

```json
{
  "fulfillment_type": "PICKUP"
}
```

and where the schema permits:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": null
}
```

Invalid:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Street",
    "city": "Dar es Salaam"
  }
}
```

---

# 5. Invalid PICKUP + Address Combination

A syntactically valid address object attached to:

```text
PICKUP
```

is a fulfillment-rule violation.

Use the frozen mapping:

```text
422 INVALID_FULFILLMENT
```

Do not silently discard the address.

---

# 6. Null Address

Internal PICKUP representation must be:

```text
delivery_address = null
```

not:

```text
{}
```

and not:

```text
{
  recipient_name: "",
  phone: "",
  address_line: "",
  city: ""
}
```

---

# 7. Billing Address

V1 does not collect a separate billing address.

For PICKUP:

```text
billing_address = null
```

Do not derive one from:

```text
customer profile
pickup location
delivery address
```

---

# 8. No Delivery Record at Checkout

Phase 7.1 determined that CHK-001 does not create the later operational:

```text
Delivery
```

record.

For PICKUP this is especially clear:

```text
delivery = null
```

Do not create a fake Delivery row.

---

# 9. No Pickup Address Snapshot

Do not create an address representing:

```text
store location
warehouse
pickup point
```

unless a future contract explicitly introduces pickup-location selection.

V1 has no customer-supplied pickup location.

---

# 10. No Pickup Location Selector

Do not add:

```text
pickup_location_id
warehouse_id
store_id
branch_id
collection_point
```

to CHK-001.

---

# 11. Delivery Fee

For PICKUP:

```text
delivery_fee.amount = 0
delivery_fee.currency = TZS
```

This is server-controlled.

---

# 12. Delivery Fee Status

For PICKUP:

```text
delivery_fee_status = FINALIZED
```

immediately when the Order is created.

There is no later ORD-014 step for PICKUP.

---

# 13. ORD-014 Must Not Apply to PICKUP

Later:

```text
POST /orders/{order}/delivery-fee
```

against a PICKUP Order must remain invalid.

Current contract maps PICKUP fee assignment to a business-rule failure.

Do not create any dependency from PICKUP Checkout to ORD-014.

---

# 14. Total

For PICKUP:

```text
total = subtotal + 0
```

therefore:

```text
total = subtotal
```

This total is final from the delivery-fee perspective.

---

# 15. Currency

Use:

```text
TZS
```

only.

No client override.

---

# 16. Minor Units

All amounts remain:

```text
integer minor units
```

No floats.

---

# 17. Do Not Copy Money Objects Casually

If the project has:

```text
Money
MoneyValue
Price
```

or equivalent:

reuse the canonical money abstraction.

Do not invent a second PICKUP-specific money type.

---

# 18. Zero Fee Constant

Avoid scattered literal structures such as:

```php
['amount' => 0, 'currency' => 'TZS']
```

if the repository already has an authoritative money/value-object factory.

Prefer something conceptually equivalent to:

```text
Money::zeroTzs()
```

only if consistent with existing architecture.

Do not create abstraction solely for this phase if none is needed.

---

# 19. Initial Order Status

Successful PICKUP Checkout eventually creates:

```text
status = PENDING_PAYMENT
```

NOT:

```text
PAID
ACCEPTED
READY_FOR_PICKUP
COMPLETED
```

---

# 20. Why PENDING_PAYMENT

PICKUP being fee-finalized does not mean payment has occurred.

It only means:

```text
delivery_fee_status = FINALIZED
```

The customer still has to enter the Group H payment flow.

---

# 21. Payment Representation

At CHK-001 success for PICKUP:

```text
payment = null
```

Do not create:

```text
payment_status = PENDING
```

inside Checkout.

The Payment resource appears only when PAY-001 later creates it.

---

# 22. Payment Eligibility

Because PICKUP has:

```text
delivery_fee_status = FINALIZED
```

PAY-001 will later be eligible immediately from the fee-state perspective.

But:

```text
Phase 7.3 must not call PAY-001
```

or implement payment.

---

# 23. Product/Cart Preconditions Still Apply

PICKUP does not bypass Checkout rules.

Eventually successful PICKUP still requires:

```text
authenticated CUSTOMER
own ACTIVE Cart
Cart not empty
valid Product
valid Variant
IN_STOCK only
current price
current stock
quantity valid
Idempotency-Key
```

---

# 24. MADE_TO_ORDER

PICKUP does not make MADE_TO_ORDER purchasable.

A MADE_TO_ORDER Cart line remains:

```text
422 PRODUCT_NOT_PURCHASABLE
```

---

# 25. Stock Requirement

PICKUP still reserves stock at successful Checkout.

Do not treat pickup as:

```text
reserve later when customer arrives
```

The frozen lifecycle says Checkout reserves for both PICKUP and DELIVERY.

---

# 26. Reservation Timing

The eventual transaction does:

```text
validate available stock
→ reserve stock
→ create PICKUP Order
→ clear Cart
```

atomically.

Phase 7.3 should prepare the branch data but should not duplicate Phase 7.7 transaction mechanics.

---

# 27. Physical Stock

At Checkout:

```text
physical quantity unchanged
reserved_quantity increases
```

PICKUP does not consume inventory immediately.

---

# 28. Consumption

Later payment/fulfillment converts reservation into consumption according to Group H/order lifecycle.

Do not consume stock in 7.3.

---

# 29. Pickup Fulfillment Object

Introduce or refine the smallest domain representation needed to express:

```text
PICKUP
delivery_address = null
billing_address = null
delivery_fee = zero
delivery_fee_status = FINALIZED
```

Examples could include:

```text
CheckoutFulfillment
PickupFulfillment
CheckoutFulfillmentState
```

Use actual repository conventions.

---

# 30. Avoid Branch Logic Everywhere

Do not scatter:

```php
if ($fulfillmentType === 'PICKUP')
```

through:

```text
controller
resource
order model
pricing service
checkout service
```

Centralize the PICKUP-specific fulfillment projection/state in one focused boundary.

---

# 31. Do Not Build Giant CheckoutService Yet

Phase 7.3 should not prematurely introduce the entire:

```text
CheckoutService
```

unless the repository already created a shell specifically intended for branch composition.

Prefer a focused PICKUP branch component.

---

# 32. Suggested Component

A small component such as:

```text
BuildPickupCheckoutState
```

or:

```text
PickupFulfillment
```

may own:

```text
fulfillment type
address nullability
fee zero
fee status FINALIZED
```

Do not let it own inventory, Cart, idempotency, or persistence.

---

# 33. Branch Result

Conceptually the PICKUP branch can yield:

```text
fulfillment_type = PICKUP
delivery_address = null
billing_address = null
delivery_fee = zero TZS
delivery_fee_status = FINALIZED
```

Then Phase 7.6 later combines:

```text
subtotal
+ delivery fee
→ total
```

---

# 34. Do Not Let PICKUP Own Subtotal

Subtotal comes from validated OrderItems/current prices.

The PICKUP branch only contributes:

```text
delivery fee = 0
```

---

# 35. Phase 7.6 Boundary

Phase 7.6 owns the canonical financial calculation implementation.

Phase 7.3 may assert:

```text
PICKUP total must equal subtotal
```

but should not create a second totals engine.

---

# 36. No Discounts

Do not add:

```text
pickup discount
delivery discount
coupon
promotion
```

---

# 37. No Pickup Fee

Do not introduce:

```text
service fee
handling fee
pickup fee
```

V1 delivery fee for pickup is zero.

---

# 38. No Minimum Order

Do not add minimum-order logic.

---

# 39. No Pickup Scheduling

Do not add:

```text
pickup_time
pickup_date
pickup_slot
```

---

# 40. No Store Hours Validation

Out of scope.

---

# 41. No Pickup Location Inventory Selection

Inventory reservation remains based on Group E's allocation mechanism.

Do not expose which warehouse fulfils PICKUP.

---

# 42. Inventory Allocation Is Internal

If `InventoryAllocator` reserves across one or more locations:

the customer does not choose them.

Do not put allocation details into PICKUP response.

---

# 43. Response Contract

PICKUP CHK-001 response eventually must follow:

```json
{
  "data": {
    "order_id": "ord_...",
    "order_reference": "OD-.....",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "PICKUP",
    "delivery_address": null,
    "subtotal": {
      "amount": 0,
      "currency": "TZS"
    },
    "delivery_fee": {
      "amount": 0,
      "currency": "TZS"
    },
    "delivery_fee_status": "FINALIZED",
    "total": {
      "amount": 0,
      "currency": "TZS"
    },
    "currency": "TZS",
    "payment": null
  }
}
```

Use actual calculated monetary amounts.

---

# 44. Do Not Return Billing Address Unless Contract Includes It

`CheckoutResponseData` is not automatically identical to OrderDetail.

Do not add:

```text
billing_address
delivery
status_history
```

to CHK-001 response unless frozen schema includes them.

---

# 45. Explicit Serializer

Later response must use explicit:

```text
CheckoutResponseResource
CheckoutResponseData
```

or existing equivalent.

Do not serialize the raw Order model.

---

# 46. Order Persistence Mapping

Review how future Order creation will map PICKUP fields:

```text
fulfillment_type = PICKUP
delivery_fee = zero
delivery_fee_status = FINALIZED
subtotal = authoritative subtotal
total = subtotal
currency = TZS
delivery_address = null
billing_address = null
status = PENDING_PAYMENT
```

Document exact fields.

---

# 47. No Delivery Row

Confirm Order persistence does not require a Delivery row merely because an Order exists.

If the database forces one:

classify as a model gap.

Do not create dummy Delivery records.

---

# 48. Order Address Nullability

Verify schema allows:

```text
delivery_address = null
billing_address = null
```

for PICKUP.

If not:

```text
MODEL GAP
```

must be reported.

---

# 49. Fee Nullability

Verify Order schema can persist:

```text
delivery_fee = zero Money
delivery_fee_status = FINALIZED
```

for PICKUP.

---

# 50. Total Persistence

Verify:

```text
total = subtotal
```

can be represented without a delivery-fee finalization operation.

---

# 51. State Enum

Verify:

```text
PENDING_PAYMENT
```

is valid initial status for PICKUP.

---

# 52. Fulfillment Enum

Verify:

```text
PICKUP
```

is the canonical persisted enum.

Do not use:

```text
SELF_PICKUP
COLLECTION
STORE_PICKUP
```

---

# 53. Initial History Entry

Later successful Checkout must append:

```text
PENDING_PAYMENT
```

to Order status history.

Phase 7.3 should verify the PICKUP branch requires no special alternate initial history state.

---

# 54. Pickup Is Not READY_FOR_PICKUP Yet

Do not initialize Order as:

```text
READY_FOR_PICKUP
```

That status belongs later in the operational lifecycle after payment and processing.

---

# 55. PICKUP Operational Path

Future PICKUP path is conceptually:

```text
PENDING_PAYMENT
→ PAID
→ ACCEPTED
→ PROCESSING
→ READY_FOR_PICKUP
→ COMPLETED
```

Only the initial state is relevant to Phase 7.3.

Do not implement transitions.

---

# 56. No SHIPPED for PICKUP

PICKUP later must not enter:

```text
SHIPPED
DELIVERED
```

But Phase 7.3 should only record this dependency, not implement staff transition guards.

---

# 57. Fulfillment-Type Integrity

Once Order is created:

```text
fulfillment_type = PICKUP
```

is historical Order meaning.

Do not allow ordinary Checkout/PICKUP code to mutate it later.

---

# 58. Client Cannot Set Delivery Fee

Strict validation already forbids:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_fee": {
    "amount": 0,
    "currency": "TZS"
  }
}
```

Even the correct zero value is server-controlled and must be rejected if client supplies it.

---

# 59. Client Cannot Set Total

Reject:

```json
{
  "fulfillment_type": "PICKUP",
  "total": {
    "amount": 1000,
    "currency": "TZS"
  }
}
```

---

# 60. Client Cannot Set Status

Reject:

```json
{
  "fulfillment_type": "PICKUP",
  "status": "PENDING_PAYMENT"
}
```

Server owns status even when client sends the correct value.

---

# 61. Client Cannot Set Currency

Reject:

```json
{
  "fulfillment_type": "PICKUP",
  "currency": "TZS"
}
```

because currency is server-controlled.

---

# 62. Request Normalization

Normalize:

```text
fulfillment_type
```

only according to frozen enum rules.

Do not lowercase/uppercase arbitrary client values into validity.

---

# 63. Exact Enum

This:

```text
PICKUP
```

is valid.

These:

```text
pickup
Pickup
SELF_PICKUP
```

are invalid.

---

# 64. No PICKUP Alias

Do not accept:

```text
COLLECT
COLLECTION
SELF_PICK
```

---

# 65. Address Branch Ordering

For PICKUP:

first determine valid fulfillment enum.

Then enforce:

```text
delivery_address must be null/absent
```

Do not run DELIVERY nested-field validation when no address is required.

---

# 66. Null vs Absent

Both:

```json
{
  "fulfillment_type": "PICKUP"
}
```

and:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": null
}
```

should normalize to the same logical PICKUP intent if this matches the frozen schema.

---

# 67. Idempotency Fingerprint

Those two logically equivalent PICKUP requests should produce the same normalized Checkout intent.

Do not make:

```text
absent delivery_address
```

and:

```text
delivery_address = null
```

materially different idempotency fingerprints.

---

# 68. Canonical PICKUP Fingerprint

Conceptually:

```text
fulfillment_type = PICKUP
delivery_address = null
```

after normalization.

---

# 69. No Address Data in Fingerprint

For PICKUP:

there is no delivery-address payload in the normalized fingerprint.

---

# 70. Idempotency Still Required

PICKUP CHK-001 still requires:

```text
Idempotency-Key
```

Phase 7.3 does not weaken this.

---

# 71. Same-Key Replay

Later:

```text
PICKUP + key K
```

replay returns original 201 result.

No second Order.

No second reservation.

---

# 72. Same-Key Changed to DELIVERY

Later:

```text
first K = PICKUP
retry K = DELIVERY
```

must:

```text
409 DUPLICATE_OPERATION
```

---

# 73. No Idempotency Implementation Duplication

Do not build PICKUP-specific idempotency.

Reuse shared Checkout idempotency later.

---

# 74. Security

PICKUP does not change actor policy:

```text
CUSTOMER-only
```

No Staff/Admin checkout unless frozen contract changes later.

---

# 75. Guest Checkout

Still:

```text
401
```

for guest-only caller.

PICKUP is not an exception.

---

# 76. Cart Ownership

Server derives authenticated customer's own active Cart.

No Cart ID accepted.

---

# 77. No Pickup Cart Shortcut

Do not expose:

```text
POST /me/cart/pickup
```

---

# 78. Error Mapping

Expected PICKUP-specific failures include:

```text
invalid fulfillment enum
→ schema/domain 422

PICKUP + populated delivery_address
→ 422 INVALID_FULFILLMENT

client delivery_fee
→ 422 INVALID_VALUE

client total/subtotal/currency/status
→ 422 INVALID_VALUE
```

Use exact frozen envelope.

---

# 79. Domain Result Type

If creating a PICKUP branch result, make invalid states impossible.

It should not permit:

```text
PICKUP + non-null delivery address
PICKUP + pending delivery fee
PICKUP + non-zero delivery fee
```

---

# 80. Strong Invariant

The code should encode:

```text
PICKUP
implies
delivery_address = null
delivery_fee = zero
delivery_fee_status = FINALIZED
```

as one coherent domain decision.

---

# 81. Avoid Mutable State Object

Do not create an object that can later be mutated into:

```text
PICKUP + delivery_fee_status=PENDING
```

without explicit invariant checks.

Prefer immutable construction where repository style allows it.

---

# 82. Reuse Fulfillment Enum

Do not create:

```text
PickupType
```

if existing:

```text
FulfillmentType::PICKUP
```

already exists.

---

# 83. Reuse DeliveryFeeStatus Enum

Use existing:

```text
FINALIZED
```

enum/value.

No magic string.

---

# 84. Reuse OrderStatus Enum

Use:

```text
PENDING_PAYMENT
```

from existing closed enum.

---

# 85. Reuse Currency Authority

Use existing TZS source.

Do not hardcode currency differently across branch services.

---

# 86. Phase 7.3 Implementation Target

The desired result is that later Checkout orchestration can ask:

```text
Build the fulfillment state for PICKUP
```

and receive a correct, validated immutable branch result.

---

# 87. Suggested Flow

Conceptually:

```text
validated fulfillment_type
→ PICKUP branch resolver
→ verify delivery_address absent/null
→ produce PickupFulfillmentState
```

No DB mutation required.

---

# 88. No Inventory Query in PICKUP Branch Component

The branch component should not query ProductStock.

Inventory belongs to Checkout transaction orchestration.

---

# 89. No Product Query

Likewise no Product/Variant validation inside the pure PICKUP fulfillment component.

---

# 90. No Order Insert

Do not persist Order in the branch component.

---

# 91. No Cart Mutation

Do not clear Cart in Phase 7.3 branch code.

---

# 92. No Transaction Requirement Yet

Pure PICKUP branch construction does not need a DB transaction.

Actual persistence belongs later.

---

# 93. Database Review

Still verify Order schema supports PICKUP invariants.

No runtime persistence required yet.

---

# 94. Likely Files

Potential implementation areas:

```text
app/Domain/Checkout/
app/Services/Checkout/
app/Enums/FulfillmentType.php
app/Enums/DeliveryFeeStatus.php
app/ValueObjects/Money.php
tests/Unit/Checkout/
tests/Feature/Checkout/
docs/decisions.md
```

Use actual repository structure.

---

# 95. Do Not Activate Checkout Route Yet

Unless the project's established implementation plan explicitly activates CHK-001 incrementally, leave:

```text
POST /checkout
```

as its current stub.

Phase 7.3 is one branch of a later complete workflow.

---

# 96. If Route Is Already Activated

If previous implementation unexpectedly activated it:

do not expose a half-implemented PICKUP-only Checkout while DELIVERY remains incomplete unless the frozen contract permits partial availability.

Prefer maintaining current stub until both branches and transaction behavior are ready.

---

# 97. Focused Unit Tests

Add unit tests for PICKUP branch semantics.

At minimum:

```text
PICKUP produces null delivery address
PICKUP produces null billing address
PICKUP produces zero TZS delivery fee
PICKUP produces FINALIZED fee status
```

---

# 98. Test — Total Relationship

Given:

```text
subtotal = X
```

assert branch/totals integration expectation:

```text
delivery fee = 0
total = X
```

If Phase 7.6 owns totals implementation, keep this as requirement-level/unit integration without duplicating calculator logic.

---

# 99. Test — No Delivery Address

Valid request:

```json
{
  "fulfillment_type": "PICKUP"
}
```

passes PICKUP branch validation.

---

# 100. Test — Null Address

Valid:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": null
}
```

normalizes identically.

---

# 101. Test — Populated Address

Reject:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Jengo Street",
    "city": "Dar es Salaam"
  }
}
```

with:

```text
INVALID_FULFILLMENT
```

at the appropriate domain boundary.

---

# 102. Test — Correct Client-Supplied Fee Still Rejected

Payload containing:

```text
delivery_fee = 0
```

must still fail.

---

# 103. Test — Total Injection

Reject.

---

# 104. Test — Status Injection

Reject.

---

# 105. Test — Currency Injection

Reject.

---

# 106. Test — Billing Address Injection

Because V1 Checkout does not accept billing address:

reject it as unknown.

---

# 107. Test — Pickup Location Injection

Reject:

```text
pickup_location_id
```

as unknown.

---

# 108. Test — Lowercase Enum

Reject:

```text
pickup
```

---

# 109. Test — PAYMENT Remains Null

Any branch/result object intended for Checkout response must not fabricate Payment.

---

# 110. Test — No Delivery Object

PICKUP Order projection later must have:

```text
delivery = null
```

where OrderDetail includes that field.

---

# 111. Model Compatibility Test

Verify Order model can represent:

```text
PICKUP
delivery_address null
billing_address null
delivery_fee zero
delivery_fee_status FINALIZED
PENDING_PAYMENT
```

without violating model assertions or DB constraints.

---

# 112. No Dummy Address Regression

Explicitly verify no helper substitutes:

```text
region
city
store address
customer profile address
```

for PICKUP.

---

# 113. Phase 7.2 Regression

Run address-model tests.

PICKUP changes must not reintroduce:

```text
region
```

to public V1 Checkout/Order schemas.

---

# 114. Existing Cart Regression

No Cart behavior should change during PICKUP implementation.

Run relevant Group F focused tests if Checkout helper touches shared Cart code.

---

# 115. Existing Inventory Regression

If PICKUP code references money/fulfillment enums only:

no inventory changes expected.

If any shared reservation interface is touched, rerun its focused tests.

---

# 116. No MariaDB Checkout Race Yet

Phase 7.7 owns actual Checkout transaction concurrency.

Do not fabricate a partial MariaDB checkout race harness before Checkout persistence exists.

---

# 117. No Schema Change Expected

Expected:

```text
Schema: NONE
```

If Order model cannot represent PICKUP state:

report a MODEL GAP rather than silently altering V1.

---

# 118. Dependencies

Expected:

```text
NONE
```

---

# 119. Frontend

Expected:

```text
NONE
```

---

# 120. Documentation

Record the PICKUP implementation decision if needed:

```text
PICKUP:
delivery_address null
billing_address null
fee 0 TZS
fee status FINALIZED
total=subtotal
Order PENDING_PAYMENT
payment null
```

Do not create a duplicate API contract.

---

# 121. No Delivery Fee ADR Redesign

This phase does not reconsider Model B.

---

# 122. No Payment ADR Redesign

This phase does not reconsider Group H boundaries.

---

# 123. Validation Responsibility

If Phase 7.8 owns final Checkout FormRequest, Phase 7.3 may provide a reusable PICKUP branch validator but must not duplicate final transport/schema validation.

---

# 124. Keep Separation

Preferred layers:

```text
request schema
→ normalized Checkout input
→ fulfillment branch
→ later Checkout transaction
```

---

# 125. No Request Object in Domain

Avoid passing the Laravel Request directly into the PICKUP domain component.

Use validated normalized input.

---

# 126. Deterministic Output

Given identical PICKUP input, the branch result must be deterministic.

No current-time/random/database dependency.

---

# 127. Money Equality

When comparing:

```text
total
subtotal
```

compare integer amount + currency.

No float conversion.

---

# 128. Currency Mismatch Impossible

The branch should not permit:

```text
subtotal TZS
delivery fee USD
```

V1 uses TZS only.

---

# 129. Total Finality

For PICKUP:

```text
delivery_fee_status = FINALIZED
```

means:

```text
total is final
```

from the Order financial perspective before payment.

Document this distinction from DELIVERY's provisional total.

---

# 130. Payment Still Null

Final total does not mean Payment exists.

Keep those concepts separate.

---

# 131. Cancellation Window Dependency

The future Order's:

```text
created_at
```

starts the configured cancellation window.

Phase 7.3 does not implement cancellation.

Do not special-case PICKUP.

---

# 132. Operational Fulfillment Dependency

Later Staff processing uses:

```text
PICKUP
```

to determine valid transition:

```text
PROCESSING → READY_FOR_PICKUP
```

This phase should preserve the enum exactly so operational code can branch reliably.

---

# 133. No Tracking Delivery Object

PICKUP should not generate delivery tracking state.

---

# 134. No Shipping Status

Do not initialize:

```text
shipping_status
```

or equivalent.

---

# 135. No Store Notification

Notification behavior is outside this phase.

---

# 136. No Email

Out of scope.

---

# 137. No FCM

Out of scope.

---

# 138. No Receipt

Out of scope.

---

# 139. Security Review

Ensure PICKUP cannot be used to bypass:

```text
stock validation
price recalculation
authentication
Cart ownership
idempotency
```

just because delivery address is absent.

---

# 140. Do Not Branch Before Authorization

Future CHK-001 should still authenticate CUSTOMER before exposing domain behavior according to Phase 7.1 validation ordering.

---

# 141. No Fee Finalization Permission Required

PICKUP customer's Checkout does not invoke the staff permission:

```text
orders.set_delivery_fee
```

Fee zero/finalized is intrinsic server behavior.

---

# 142. Audit

No privileged delivery-fee audit event is needed merely because PICKUP gets zero fee.

This is not ORD-014.

---

# 143. Historical Financial Record

Once the Order is later created:

```text
delivery_fee = zero
delivery_fee_status = FINALIZED
```

must remain part of historical financial meaning.

Do not recalculate it based on later delivery policy changes.

---

# 144. No Later Fee Addition

A PICKUP Order must not later gain a delivery fee through normal flow.

---

# 145. Pickup→Delivery Conversion

Do not implement changing an Order from:

```text
PICKUP
```

to:

```text
DELIVERY
```

after Checkout.

No such V1 operation exists.

---

# 146. Delivery→Pickup Conversion

Likewise out of scope.

---

# 147. Test Naming

Prefer focused suites such as:

```text
PickupFulfillmentTest
CheckoutPickupContractTest
```

Use repository conventions.

---

# 148. Avoid Premature Full Feature Tests

Do not write tests that expect a fully working CHK-001 transaction if that route is intentionally still stubbed.

Test the branch/component/model compatibility now.

---

# 149. Full Checkout Tests Later

Phase 7.9 will prove:

```text
authenticated request
Cart
reservation
Order
Cart clear
idempotency
response
```

end-to-end.

---

# 150. Verification

Run focused Phase 7.3 tests.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 151. Route Verification

Expected:

```text
new routes = NONE
```

unless existing roadmap specifically activates CHK-001 earlier.

Do not add a PICKUP-only route.

---

# 152. OpenAPI

Expected:

```text
changes = NONE
```

The frozen PICKUP contract already exists.

---

# 153. Completion Report

Return:

## Phase 7.3 status

```text
PASS
```

or:

```text
BLOCKED
```

## PICKUP branch

Report the component(s) responsible for PICKUP state.

## Fulfillment

Confirm:

```text
fulfillment_type = PICKUP
```

## Addresses

Confirm:

```text
delivery_address = null
billing_address = null
```

and no dummy address.

## Delivery fee

Confirm:

```text
amount = 0
currency = TZS
status = FINALIZED
```

## Total

Confirm:

```text
total = subtotal
```

and note whether final total calculation itself remains Phase 7.6-owned.

## Order status

Confirm:

```text
PENDING_PAYMENT
```

## Payment

Confirm:

```text
payment = null
provider calls = NONE
```

## Inventory

Report:

```text
reservation implementation in this phase = NONE
ProductStock mutation = NONE
```

unless roadmap explicitly required otherwise.

## Validation

Report PICKUP/address branch behavior.

## Model compatibility

Report Order/Delivery schema compatibility.

## Idempotency

Confirm normalized PICKUP intent:

```text
fulfillment_type=PICKUP
delivery_address=null
```

is deterministic for later fingerprinting.

## Schema

Expected:

```text
NONE
```

## Dependencies

Expected:

```text
NONE
```

## Frontend

```text
NONE
```

## Tests

Report focused and canonical results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Phase 7.4 readiness

Return:

```text
Phase 7.4 — Delivery flow: READY
```

or:

```text
BLOCKED
```

---

# 154. Definition of Done

Phase 7.3 is complete when:

- PICKUP is represented by the frozen enum;
- PICKUP accepts no populated delivery address;
- absent and null delivery address normalize identically;
- delivery address remains null;
- billing address remains null;
- no Delivery row is required at Checkout;
- no pickup location selector is introduced;
- no fake address is created;
- delivery fee is exactly zero TZS;
- delivery fee status is FINALIZED;
- total relationship is frozen as total=subtotal;
- initial Order state remains PENDING_PAYMENT;
- Payment remains null;
- no payment provider call exists;
- no MADE_TO_ORDER bypass exists;
- no stock-validation bypass exists;
- Checkout reservation requirement remains intact;
- no stock reservation is prematurely implemented by the branch component;
- no physical stock is consumed;
- client-supplied delivery fee is rejected;
- client-supplied total is rejected;
- client-supplied currency is rejected;
- client-supplied status is rejected;
- PICKUP enum aliases are rejected;
- normalized PICKUP intent is deterministic for idempotency;
- Order persistence can represent the PICKUP state;
- public address vocabulary remains `city` for DELIVERY and does not leak `region`;
- no schema migration is required unless a real model gap is found;
- no new dependency is added;
- no frontend work occurs;
- full regression suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit remains clean.

---

# 155. Out of Scope

Do not implement:

```text
Phase 7.4 DELIVERY flow
Phase 7.5 delivery fee rules
Phase 7.6 final totals engine
Phase 7.7 Checkout transaction
Phase 7.8 complete Checkout validation
Phase 7.9 full Checkout tests
payment
order cancellation
reservation release
stock consumption
pickup scheduling
pickup locations
frontend
```

---

# 156. STOP Condition

STOP when the backend can represent the PICKUP branch unambiguously as:

```text
PICKUP
→ delivery_address null
→ billing_address null
→ delivery_fee 0 TZS
→ delivery_fee_status FINALIZED
→ total = subtotal
→ Order PENDING_PAYMENT
→ payment null
```

without activating a partial or unsafe Checkout workflow.

Do not continue automatically to Phase 7.4.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.