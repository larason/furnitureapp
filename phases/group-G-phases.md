# Phase 7.6 — Order Totals

## Purpose

Implement the single authoritative backend calculation boundary for Checkout and Order financial totals.

Phase 7.6 must centralize:

```text
line_total
subtotal
delivery_fee relationship
total
currency
financial finality
overflow protection
```

without persisting a Checkout Order, reserving inventory, or resolving the Phase 7.4 billing-address persistence gap.

This phase must produce a financial component that later phases can safely reuse from:

```text
Phase 7.7 — Checkout transaction boundaries
Phase 7.8 — Checkout validation
ORD-014 — delivery-fee finalization
Group H — payment amount authority
```

---

# 1. Current Group G Status

Treat the current roadmap state as:

```text
7.1  PASS     — Checkout requirements
7.2  PASS     — Address model review
7.3  PASS     — Pickup flow
7.4  BLOCKED  — Delivery flow persistence
                 billing-address snapshot model gap
7.5  PASS     — Delivery fee rules / ORD-014
7.6  CURRENT  — Order totals
```

Do not incorrectly report Phase 7.4 as PASS.

---

# 2. Phase 7.4 Blocker Must Remain Explicit

The DELIVERY branch currently exists as:

```text
DeliveryFulfillmentState
```

and can project:

```text
delivery_address
billing_address
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
```

but cannot persist a complete frozen V1 DELIVERY Order because:

```text
orders has no billing_address persistence
```

and there is currently no approved alternative snapshot model.

Phase 7.6 must NOT resolve this indirectly.

---

# 3. Phase 7.6 Is Safe to Continue

Financial calculation does not require:

```text
billing_address persistence
Delivery row creation
Checkout Order insert
Cart clear
inventory reservation
```

Therefore Phase 7.6 may be implemented independently as a pure financial/domain boundary.

---

# 4. Primary Objective

There must be exactly one authoritative answer to:

```text
Given authoritative line amounts,
fulfillment type,
delivery-fee state,
and currency,

what are:
- subtotal
- delivery fee representation
- total
- whether the total is final?
```

Avoid separate formulas inside:

```text
PickupFulfillmentState
DeliveryFulfillmentState
FinalizeDeliveryFee
Checkout transaction
OrderResource
Payment initiation
```

---

# 5. Canonical Financial Rules

The frozen V1 rules are:

```text
PICKUP
delivery_fee = 0
delivery_fee_status = FINALIZED
total = subtotal
total final = YES
```

```text
DELIVERY + PENDING fee
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
total final = NO
```

```text
DELIVERY + FINALIZED fee
delivery_fee >= 0
delivery_fee_status = FINALIZED
total = subtotal + delivery_fee
total final = YES
```

These rules are already reflected in the Order model invariants.

---

# 6. Model Does Not Own Calculation

Current `Order::assertValid()` validates financial consistency.

It intentionally does not calculate totals.

Preserve that design.

Target separation:

```text
calculator
→ creates authoritative amounts

Order model
→ validates persistence invariants
```

Do not turn the Order model into a calculator.

---

# 7. One Calculation Authority

Introduce or consolidate one focused component.

Examples:

```text
OrderTotalsCalculator
CheckoutTotals
OrderFinancialCalculator
```

Use repository naming conventions.

Prefer a name that expresses:

```text
pure calculation
```

rather than workflow.

---

# 8. Suggested Responsibility

Conceptually:

```text
calculateSubtotal(lines)

forPickup(subtotal)

forDeliveryPending(subtotal)

forDeliveryFinalized(subtotal, deliveryFee)
```

or an equivalent cohesive API.

Do not mechanically implement these exact method names if the repository has a better pattern.

---

# 9. Suggested Result Object

A small immutable value/result object may contain:

```text
subtotal
deliveryFee
deliveryFeeStatus
total
currency
isFinal
```

Conceptually:

```text
OrderTotals
```

---

# 10. Result Must Be Immutable

Financial calculation output should not permit:

```text
total changed independently
delivery fee changed independently
status changed independently
```

after construction.

Prefer immutable values.

---

# 11. Currency

V1 uses:

```text
TZS
```

only.

Every financial result must use the same canonical currency.

---

# 12. No Currency Conversion

Do not introduce:

```text
USD
KES
EUR
FX conversion
exchange rate
```

---

# 13. Integer Minor Units

Every monetary amount uses:

```text
integer minor units
```

No floating point.

---

# 14. TZS Precision

Preserve project convention:

```text
1 TZS = 100 minor units
```

Do not silently reinterpret existing stored amounts as major units.

---

# 15. No Decimal Money

Do not use:

```text
float
double
decimal strings
round()
number_format()
```

for domain calculations.

---

# 16. Line Total Formula

For every Checkout line:

```text
line_total =
unit_price × quantity
```

using integer arithmetic.

---

# 17. Unit Price Authority

The calculator receives:

```text
server-resolved current unit price
```

not client-submitted price.

---

# 18. Quantity Authority

The quantity comes from the locked/revalidated Cart item during Checkout later.

Phase 7.6 should calculate from trusted inputs.

Do not make this calculator responsible for Cart ownership or request validation.

---

# 19. Historical OrderItem Rule

The calculated:

```text
unit_price
quantity
line_total
```

later become immutable `OrderItem` snapshot values.

Current OrderItem invariants already require:

```text
line_total_amount = unit_price_amount × quantity
```

so the calculator should produce values compatible with those persistence rules.

---

# 20. Subtotal Formula

Canonical:

```text
subtotal =
SUM(line_total)
```

for all Checkout OrderItems.

---

# 21. No Delivery Fee in Subtotal

Do NOT calculate:

```text
subtotal =
line totals + delivery fee
```

Delivery fee is separate.

---

# 22. No Tax

Do not add tax.

---

# 23. No Discount

Do not add:

```text
discount
coupon
promotion
voucher
```

to Order totals.

---

# 24. No Service Fee

Do not add:

```text
handling fee
platform fee
pickup fee
processing fee
```

---

# 25. PICKUP Formula

For PICKUP:

```text
delivery_fee_amount = 0
delivery_fee_status = FINALIZED
total_amount = subtotal_amount
```

---

# 26. PICKUP Finality

PICKUP's total is:

```text
FINAL
```

from the Order financial perspective.

It is still:

```text
PENDING_PAYMENT
```

until Group H confirms payment.

Do not confuse:

```text
financial finality
```

with:

```text
payment status
```

---

# 27. DELIVERY Pending Formula

For DELIVERY before ORD-014:

```text
delivery_fee_amount = null
delivery_fee_status = PENDING
total_amount = subtotal_amount
```

---

# 28. DELIVERY Pending Total Is Provisional

Even though:

```text
total_amount == subtotal_amount
```

the total is:

```text
PROVISIONAL
```

and must not be considered payable.

---

# 29. Do Not Use `total == subtotal` to Infer Finality

Both of these may be true:

```text
PICKUP:
total == subtotal
FINAL
```

```text
DELIVERY/PENDING:
total == subtotal
NOT FINAL
```

Therefore finality comes from:

```text
delivery_fee_status
+
fulfillment semantics
```

not numerical equality.

---

# 30. DELIVERY Finalized Formula

After ORD-014:

```text
delivery_fee_status = FINALIZED

total =
subtotal
+
delivery_fee
```

---

# 31. Zero-Fee DELIVERY

If authorized ORD-014 later finalizes:

```text
delivery_fee = 0
```

then:

```text
total = subtotal
delivery_fee_status = FINALIZED
total final = YES
```

This must remain distinguishable from:

```text
delivery_fee = null
delivery_fee_status = PENDING
total final = NO
```

---

# 32. Null vs Zero Is Semantically Important

Never normalize:

```text
null delivery fee
```

into:

```text
0 delivery fee
```

for DELIVERY/PENDING.

These states mean different things.

---

# 33. Exact Financial Matrix

Encode/test:

| Fulfillment | Fee status | Fee | Total | Final |
|---|---|---:|---:|---|
| PICKUP | FINALIZED | 0 | subtotal | yes |
| DELIVERY | PENDING | null | subtotal | no |
| DELIVERY | FINALIZED | >=0 | subtotal + fee | yes |

All other combinations are invalid.

---

# 34. Invalid PICKUP Pending State

This is invalid:

```text
PICKUP
delivery_fee_status = PENDING
```

---

# 35. Invalid PICKUP Null Fee

This is invalid:

```text
PICKUP
delivery_fee = null
```

---

# 36. Invalid PICKUP Non-Zero Fee

This is invalid:

```text
PICKUP
delivery_fee > 0
```

---

# 37. Invalid DELIVERY Pending With Fee

This is invalid:

```text
DELIVERY
PENDING
delivery_fee != null
```

---

# 38. Invalid DELIVERY Finalized Without Fee

This is invalid:

```text
DELIVERY
FINALIZED
delivery_fee = null
```

---

# 39. Negative Fee

Must never be accepted by the totals calculator.

Caller validation already protects ORD-014, but calculator/domain layer should not produce invalid financial state from:

```text
deliveryFee < 0
```

---

# 40. Negative Unit Price

Must fail safely.

Even though catalog pricing should prevent this, trusted-domain components should defend invariants.

---

# 41. Zero Unit Price

If zero-priced Product/Variant is otherwise valid in the existing catalog contract:

```text
unit_price = 0
```

is mathematically valid.

Do not reject zero solely in the totals calculator unless current catalog policy prohibits it.

---

# 42. Quantity

Quantity must be:

```text
> 0
```

when calculating an OrderItem.

The Cart domain already enforces 1..100.

Do not introduce a different Checkout quantity maximum.

---

# 43. Empty Line Set

Checkout cannot succeed with an empty Cart.

However decide how the pure subtotal calculator behaves with:

```text
[]
```

Prefer one of:

```text
subtotal = 0
```

as pure arithmetic, with Checkout validation responsible for rejecting empty Cart,

or an explicit domain exception if existing style requires non-empty collections.

Do not duplicate `CART_INVALID` HTTP behavior inside the pure calculator.

---

# 44. Separation of Arithmetic and Checkout Validation

Preferred:

```text
OrderTotalsCalculator
→ arithmetic / financial invariants

Checkout validation
→ Cart must be non-empty
```

Keep HTTP/business validation outside pure arithmetic.

---

# 45. Overflow Safety

Integer multiplication and addition must not overflow PHP/database-supported monetary ranges.

Protect:

```text
unit_price × quantity
SUM(line totals)
subtotal + delivery fee
```

---

# 46. Never Allow Integer Wrap

Do not permit overflow to produce:

```text
negative value
truncated value
wrapped integer
```

---

# 47. Overflow Error

Use an internal domain exception/value error consistent with existing code.

Do not invent a public Checkout error code in Phase 7.6.

Phase 7.8 can map impossible/invalid financial state appropriately.

---

# 48. Database Range

Current amounts use:

```text
unsignedBigInteger
```

at persistence.

Calculator output must remain representable by the persistence boundary.

---

# 49. PHP Runtime Range

Account for the actual PHP integer range used by production.

Do not assume arbitrary precision.

---

# 50. Multiplication Guard

Before:

```text
unit_price * quantity
```

guard against overflow using safe integer logic.

Avoid float-based checks.

---

# 51. Addition Guard

Before:

```text
subtotal + line_total
```

and:

```text
subtotal + delivery_fee
```

perform safe integer addition.

---

# 52. No BCMath Dependency Unless Already Present

Do not add a dependency merely for normal integer money unless genuinely necessary.

Expected:

```text
Dependencies: NONE
```

---

# 53. Canonical Money Representation

If existing code has a Money value object:

reuse it.

If it does not:

do not create an excessively general currency framework.

The project only needs V1 TZS integer money.

---

# 54. Avoid Duplicate Money Shapes

Do not maintain separate:

```text
CartMoney
CheckoutMoney
OrderMoney
DeliveryFeeMoney
```

if one existing representation suffices.

---

# 55. Persistence vs Projection

Preserve the useful pattern from Phase 7.3:

```text
API projection
≠
Order persistence attributes
```

The calculator should not leak transport format into model persistence.

---

# 56. API Projection Shape

Public money:

```json
{
  "amount": 170000000,
  "currency": "TZS"
}
```

---

# 57. Persistence Shape

Order model expects scalar fields such as:

```text
subtotal_amount
delivery_fee_amount
total_amount
currency
delivery_fee_status
```

---

# 58. Do Not Pass API Money Object Directly to Order Model

Avoid:

```text
Order::create([
  'subtotal' => ['amount' => ...]
])
```

unless model explicitly supports it.

Use trusted persistence mapping.

---

# 59. Calculator Should Support Both Consumers Cleanly

Prefer:

```text
OrderTotals
```

as internal value data that can be mapped separately to:

```text
API resource
Order attributes
```

---

# 60. Existing PickupFulfillmentState

Phase 7.3 currently exposes methods equivalent to:

```text
orderProjectionForSubtotal()
orderPersistenceAttributesForSubtotal()
```

Phase 7.6 should review these.

The goal is to move canonical arithmetic out of branch-specific state where appropriate.

---

# 61. Do Not Break Phase 7.3 API Needlessly

Refactor only enough to make Phase 7.6 the calculation authority.

Do not churn public/internal class APIs without reason.

---

# 62. Pickup State After Refactor

`PickupFulfillmentState` should express:

```text
PICKUP branch semantics
zero fee
FINALIZED
null addresses
```

while canonical total arithmetic comes from Phase 7.6.

---

# 63. Existing DeliveryFulfillmentState

Likewise review Phase 7.4.

It currently projects:

```text
delivery_fee = null
PENDING
total = subtotal
```

Phase 7.6 should centralize the financial calculation relationship without claiming the blocked branch can now persist an Order.

---

# 64. Critical: Do Not Close Phase 7.4

If Phase 7.6 integration makes:

```text
DeliveryFulfillmentState
```

calculate through the new totals component successfully, Phase 7.4 is STILL:

```text
BLOCKED
```

because billing snapshot persistence remains unresolved.

---

# 65. DELIVERY Remains Projection-Only

Do not add:

```text
Order::create()
```

to DELIVERY merely because totals are now available.

---

# 66. No Billing Gap Workaround

Do not:

```text
drop billing snapshot
reuse delivery_address as billing storage
store billing in metadata
stuff billing into Delivery
```

to unblock Checkout.

---

# 67. Phase 7.5 Integration

`FinalizeDeliveryFee` currently calculates:

```text
total = subtotal + fee
```

Review it.

Phase 7.6 should make the new canonical calculator authoritative for this calculation if doing so is clean and preserves proven 7.5 behavior.

---

# 68. Prefer ORD-014 Reuse

After Phase 7.6, ORD-014 should ideally use:

```text
OrderTotalsCalculator::forDeliveryFinalized(...)
```

or equivalent.

Do not keep a second arithmetic formula embedded in `FinalizeDeliveryFee`.

---

# 69. Preserve Phase 7.5 Behavior

Refactor must retain:

```text
zero fee valid
PENDING→FINALIZED
PENDING_PAYMENT unchanged
idempotency
audit exactly once
financial immutability
```

---

# 70. Do Not Touch ORD-014 Authorization

Phase 7.6 is not an authorization phase.

---

# 71. Do Not Change Fee Validation

Do not broaden or tighten ORD-014 fee validation during totals refactor unless a real contract defect is separately identified.

Use the already-validated fee passed into the calculator.

---

# 72. Payment Authority Later

Group H must eventually use the finalized:

```text
Order.total_amount
```

as the payment amount authority.

Phase 7.6 should make that relationship explicit.

---

# 73. Payment Must Never Recalculate Product Prices

After Order creation:

```text
Order subtotal
Order fee
Order total
```

are historical financial values.

Group H should not rebuild them from the live catalog.

---

# 74. Final Payment Rule

Conceptually later:

```text
if delivery_fee_status != FINALIZED:
    payment prohibited

payment amount = Order.total
```

Phase 7.6 does not implement PAY-001.

---

# 75. Checkout Price Recalculation

CHK-001 later recalculates:

```text
unit prices
line totals
subtotal
```

from current server-authoritative catalog state.

Do not use Cart subtotal as transaction authority.

---

# 76. Cart Subtotal Is Informational

Group F Cart subtotal may be identical at a point in time, but Checkout must independently calculate the authoritative Order subtotal.

---

# 77. Checkout Input Should Be Trusted Domain Data

The totals calculator should receive line inputs such as:

```text
unitPriceAmount
quantity
```

from the future transaction-time Checkout resolver.

Do not let it query Cart itself.

---

# 78. No Database Query in Calculator

Target:

```text
pure deterministic computation
```

No Eloquent.

No DB connection.

No Product lookup.

No Cart lookup.

No Order lookup.

---

# 79. No Time Dependency

Calculation must not depend on:

```text
now()
created_at
timezone
```

---

# 80. No Randomness

Deterministic inputs must produce deterministic output.

---

# 81. No Request Dependency

Do not pass Laravel Request/FormRequest into calculator.

---

# 82. No Actor Dependency

Calculator should not know:

```text
Customer
Staff
Admin
```

---

# 83. No Idempotency Dependency

Financial arithmetic does not own idempotency.

---

# 84. No Inventory Dependency

Do not read:

```text
quantity
reserved_quantity
ProductStock
```

except OrderItem quantity as a trusted calculation input.

---

# 85. No Reservation Side Effect

Must state explicitly:

```text
inventory reservation changes = NONE
```

---

# 86. No Order Persistence

Phase 7.6 should not create Checkout Orders.

---

# 87. No Cart Mutation

Do not clear Cart.

---

# 88. No OrderItem Insert

Do not persist OrderItems.

---

# 89. No Status History Insert

Out of scope.

---

# 90. No Delivery Persistence

Out of scope.

---

# 91. No Billing Snapshot Persistence

Still blocked.

---

# 92. No Payment Record

Out of scope.

---

# 93. No Notification

Pure financial calculation should not notify anybody.

---

# 94. Error Model

Use domain-level exceptions/results.

Do not emit API response envelopes from the calculator.

---

# 95. Invalid Financial Combination

If asked to produce something impossible such as:

```text
PICKUP + non-zero delivery fee
```

fail explicitly.

Do not silently correct it.

---

# 96. No Silent Coercion

Do not turn:

```text
negative → zero
null → zero
float → integer
```

inside domain calculation.

---

# 97. Server-Controlled Financials

Nothing in Phase 7.6 should accept client-provided:

```text
subtotal
total
line_total
currency
```

as authoritative.

This component is consumed only after trusted server resolution.

---

# 98. Recommended Internal Line Input

A small immutable calculation input may contain:

```text
unitPriceAmount: int
quantity: int
```

or receive equivalent typed parameters.

Do not include Product model if unnecessary.

---

# 99. Snapshot Names Not Needed

The calculator does not need:

```text
SKU
product name
variant name
```

to calculate money.

Keep SRP.

---

# 100. Calculation Result for Lines

If useful, return:

```text
unitPrice
quantity
lineTotal
```

for later OrderItem snapshot construction.

But do not duplicate already-proven OrderItem snapshot DTOs if one exists.

---

# 101. Phase 7.7 Consumer

Phase 7.7 should be able to:

```text
lock Cart
resolve current prices
construct trusted line inputs
call Phase 7.6 calculator
reserve inventory
persist Order + OrderItems
```

without recomputing totals manually.

---

# 102. Phase 7.8 Consumer

Phase 7.8 should not implement arithmetic.

It validates request/domain eligibility.

---

# 103. Phase 7.9 Tests

Phase 7.9 will prove the full:

```text
Cart → authoritative prices → totals → Order
```

workflow.

Phase 7.6 should provide strong unit-level financial proof now.

---

# 104. Totals Result API

Conceptual output:

```text
OrderTotals {
  subtotalAmount
  deliveryFeeAmount
  deliveryFeeStatus
  totalAmount
  currency
  isFinal
}
```

For `deliveryFeeAmount`, allow:

```text
int|null
```

because DELIVERY/PENDING requires null.

---

# 105. Currency Constant

Reuse:

```text
Order::CURRENCY_TZS
```

or a more appropriate existing currency authority.

Do not duplicate `"TZS"` everywhere.

---

# 106. DeliveryFeeStatus

Reuse existing:

```text
DeliveryFeeStatus::PENDING
DeliveryFeeStatus::FINALIZED
```

---

# 107. FulfillmentType

Reuse:

```text
FulfillmentType::PICKUP
FulfillmentType::DELIVERY
```

---

# 108. No New Financial Status Enum

Do not invent:

```text
PROVISIONAL
FINAL
```

as a persisted enum unless already contracted.

`isFinal` may be an internal derived boolean/value if useful.

The persisted/public financial signal remains:

```text
delivery_fee_status
```

---

# 109. Public `total`

Public API always exposes a Money total.

For DELIVERY/PENDING:

```text
total = subtotal
```

but remains provisional.

This matches the frozen resource contract.

---

# 110. Persistence `total_amount`

Current Order schema expects a stored total relationship compatible with:

```text
DELIVERY/PENDING total=subtotal
```

Do not set total null merely because fee is pending.

The current accepted decisions define the provisional stored total.

---

# 111. Address an Older Comment Carefully

If older schema comments imply:

```text
total_amount nullable until finalized
```

but accepted Model B behavior/tests now require:

```text
DELIVERY/PENDING total=subtotal
```

follow the latest accepted runtime/domain invariant and document any stale comment as documentation drift.

Do not change the frozen API to make total null.

---

# 112. Financial Immutability

Current Order model enforces immutability once:

```text
fee already FINALIZED
or
Order leaves PENDING_PAYMENT
```

Phase 7.6 must preserve this.

---

# 113. Calculator Does Not Mutate Historical Orders

The calculator may be reused to verify values, but it must not automatically recalculate historical Orders because catalog prices changed.

---

# 114. ORD-014 Is the Only Normal Finalization Mutation

For DELIVERY/PENDING:

```text
subtotal remains fixed
fee is introduced
total changes
```

That is valid.

---

# 115. Pickup Is Final at Creation

PICKUP financial fields should not be recalculated later through normal business flow.

---

# 116. Database Invariants

Review that calculated values satisfy existing:

```text
CHECK
model saving hooks
unsigned integer constraints
```

Do not weaken DB validation.

---

# 117. Calculator + Model Agreement Test

For each valid totals result:

construct equivalent Order state and ensure:

```text
Order::assertValid()
```

accepts it.

---

# 118. Invalid State Agreement

For invalid financial combinations:

both the calculator/domain factory and Order model should reject them where responsibility overlaps.

Do not make contradictory rules.

---

# 119. Test — Line Total

Example:

```text
unit price = 25_000
quantity = 4
```

expect:

```text
line total = 100_000
```

---

# 120. Test — Multiple Lines

Example:

```text
line A = 100_000
line B = 250_000
line C = 0
```

expect:

```text
subtotal = 350_000
```

---

# 121. Test — PICKUP

Given:

```text
subtotal = 350_000
```

expect:

```text
fee = 0
fee status = FINALIZED
total = 350_000
is final = true
```

---

# 122. Test — DELIVERY Pending

Given:

```text
subtotal = 350_000
```

expect:

```text
fee = null
fee status = PENDING
total = 350_000
is final = false
```

---

# 123. Test — DELIVERY Finalized

Given:

```text
subtotal = 350_000
fee = 50_000
```

expect:

```text
fee status = FINALIZED
total = 400_000
is final = true
```

---

# 124. Test — DELIVERY Finalized Zero Fee

Expect:

```text
fee = 0
status = FINALIZED
total = subtotal
is final = true
```

---

# 125. Test — Pending vs Free Delivery Distinction

Explicitly prove:

```text
PENDING + null fee
```

is not equivalent to:

```text
FINALIZED + zero fee
```

even though both totals numerically equal subtotal.

This is a critical regression test.

---

# 126. Test — Negative Fee

Reject.

---

# 127. Test — Negative Unit Price

Reject.

---

# 128. Test — Zero Quantity

Reject at domain input or rely on trusted OrderItem invariant depending architecture.

Do not calculate a valid line total for an invalid quantity.

---

# 129. Test — Negative Quantity

Reject.

---

# 130. Test — Maximum Cart Quantity

Ensure normal supported:

```text
quantity = 100
```

calculates correctly.

---

# 131. Test — Overflow Multiplication

Use a value near integer bounds.

Expect controlled failure.

---

# 132. Test — Overflow Subtotal Addition

Multiple individually-valid lines whose sum exceeds supported range.

Expect controlled failure.

---

# 133. Test — Overflow Fee Addition

```text
subtotal near max
+
fee
```

must fail safely.

---

# 134. Test — Currency

Output always:

```text
TZS
```

---

# 135. Test — No Float

An `int` parameter alone does not prevent float coercion: when the calling file does not declare `declare(strict_types=1)`, PHP converts a `float` argument to `int` (e.g. `100.5 → 100`) before the call, so the calculator can receive a silently truncated value.

Test must call the calculator/domain API through a boundary that does **not** declare `strict_types=1`, passing a float:

```text
float input (e.g. 100.5) → explicit rejection
```

Assert the input is rejected, never silently coerced (`100.5` must not become `100`).

The public/domain API must validate the raw value (`is_int`) before scalar coercion (reject or throw), or every caller must declare `strict_types=1`. Strict typing only protects calls made from strict files, so boundary validation of the raw value is authoritative; transport-level request validation remains the first line of defence.

---

# 136. Test — Determinism

Same inputs:

```text
same result
```

every time.

---

# 137. Test — Order Model Compatibility: PICKUP

Calculated values should satisfy existing PICKUP Order invariant.

---

# 138. Test — Order Model Compatibility: DELIVERY Pending

Calculated values should satisfy:

```text
DELIVERY
PENDING
fee=null
total=subtotal
```

model invariant.

This can remain model-level even though Phase 7.4 persistence is blocked by billing snapshot.

Do not persist a fake complete DELIVERY Checkout Order merely for the test.

---

# 139. Test — Order Model Compatibility: DELIVERY Finalized

Use an appropriate fixture/model state independent of Checkout persistence.

Phase 7.5 already creates valid finalization cases.

---

# 140. Test — PickupFulfillmentState Integration

Ensure Phase 7.3 uses or agrees with the new calculator.

No duplicate formula drift.

---

# 141. Test — DeliveryFulfillmentState Integration

Ensure Phase 7.4 state projection uses/agrees with the calculator.

Still report Phase 7.4 BLOCKED.

---

# 142. Test — ORD-014 Integration

Ensure Phase 7.5 finalization uses/agrees with the same canonical formula.

---

# 143. Test — Phase 7.5 Zero Fee

Must remain green.

---

# 144. Test — Phase 7.5 Historical Immutability

Must remain green.

---

# 145. Test — No Inventory Side Effects

Calculator has:

```text
ProductStock mutation = NONE
reserved_quantity mutation = NONE
allocation mutation = NONE
```

---

# 146. Test — No Order Persistence

Pure calculator tests should not require DB writes.

---

# 147. Unit Tests Preferred

Most Phase 7.6 behavior should be covered by fast unit tests.

Use feature tests only for integration with:

```text
Order model
Phase 7.3
Phase 7.4
Phase 7.5
```

---

# 148. No MariaDB Concurrency Requirement

Phase 7.6 is pure arithmetic.

There is no new concurrency algorithm to prove.

Do not invent a MariaDB race suite for the calculator.

---

# 149. Existing MariaDB Regression

If Phase 7.5 service is refactored to use the calculator:

rerun its existing concurrency tests.

Do not change their semantics.

---

# 150. N+1

There is no N+1 concern in a pure calculator.

Do not add database queries.

If the component queries Eloquent, architecture is wrong.

---

# 151. Performance

Calculation complexity should be:

```text
O(number of order lines)
```

with constant additional memory unless result snapshots require otherwise.

---

# 152. No Premature Micro-Optimization

Normal Cart maximum sizes do not justify exotic arithmetic structures.

---

# 153. Suggested Files

Possible:

```text
app/Services/Checkout/OrderTotalsCalculator.php
app/Services/Checkout/OrderTotals.php
tests/Unit/OrderTotalsCalculatorTest.php
```

and small integrations with:

```text
PickupFulfillmentState
DeliveryFulfillmentState
FinalizeDeliveryFee
```

Use repository naming conventions.

---

# 154. Avoid `Support` Dumping Ground

If this is Checkout/Order-domain logic, keep it in the appropriate service/domain namespace.

---

# 155. Cognitive Complexity

Maintain:

```text
<= 15
```

---

# 156. Returns

Keep:

```text
<= 3 returns where practical
```

---

# 157. No Magic Strings

Use enums/constants for:

```text
PICKUP
DELIVERY
PENDING
FINALIZED
TZS
```

---

# 158. Documentation

Add an implementation ADR if current project practice requires it.

Suggested concept:

```text
Phase 7.6 establishes one canonical OrderTotalsCalculator for line totals,
subtotal, pending/final delivery fee semantics, and total.
```

---

# 159. ADR Must Preserve 7.4 Blocker

Explicitly state:

```text
Financial calculation is complete independently of DELIVERY persistence.
Phase 7.4 remains blocked by billing snapshot persistence.
```

---

# 160. Update Group G Phase Tracking

Keep:

```text
Phase 7.4 — BLOCKED
Phase 7.5 — PASS
Phase 7.6 — PASS
```

if 7.6 itself succeeds.

Do not flatten Group G into sequential-all-PASS.

---

# 161. Phase 7.7 Readiness Is Conditional

Even if Phase 7.6 passes:

Phase 7.7 may begin to design/implement transaction boundaries that are independent of the billing snapshot gap.

However a complete DELIVERY Checkout transaction must not be declared ready for successful persistence while 7.4 remains blocked.

---

# 162. Report Readiness Precisely

At completion prefer:

```text
Phase 7.7 — Transaction boundaries: READY WITH BLOCKER
```

if transaction infrastructure can proceed while the DELIVERY model gap remains.

Or:

```text
BLOCKED
```

if current roadmap requires the persistence gap resolved before any 7.7 work.

Base this on updated `phases/group-G-phases.md`.

Do not claim unconditional DELIVERY Checkout readiness.

---

# 163. Do Not Resolve Billing Snapshot in 7.6

Explicitly forbidden:

```text
add billing_address column
add order_addresses table
add billing JSON into unrelated field
remove billing snapshot requirement
alter frozen API
```

---

# 164. Schema Changes

Expected:

```text
NONE
```

for Phase 7.6.

---

# 165. Dependencies

Expected:

```text
NONE
```

---

# 166. Frontend

Expected:

```text
NONE
```

---

# 167. OpenAPI

Expected:

```text
changes = NONE
```

Totals rules are already frozen.

---

# 168. Do Not Add Public Fields

Do not add:

```text
is_total_final
financial_status
calculation_version
```

to the API.

Clients already use:

```text
delivery_fee_status
```

to understand finality.

---

# 169. Do Not Remove Provisional Total

Do not change DELIVERY/PENDING API to:

```text
total = null
```

Frozen contract requires:

```text
total = subtotal
```

provisionally.

---

# 170. Do Not Mark Provisional Total as Payable

The data model may contain a numeric total, but payment remains blocked while:

```text
delivery_fee_status = PENDING
```

---

# 171. No Payment Eligibility Calculation Needed

Phase 7.6 need not return:

```text
can_pay
```

---

# 172. Group H Boundary

Later payment logic must consume:

```text
FINALIZED Order total
```

and never receive a client-supplied amount.

Document this dependency.

---

# 173. Financial Snapshot Semantics

Once Checkout later persists:

```text
OrderItems
subtotal
```

catalog changes must not alter them.

Once fee finalizes:

```text
delivery_fee
total
```

policy changes must not alter them.

---

# 174. No Repricing Historical Orders

Do not add a recalculation command that reads live Product prices for existing Orders.

---

# 175. Cart Price Drift

Future Phase 7.7/7.8 will intentionally use current catalog price at Checkout time.

Phase 7.6 calculator simply calculates from the trusted current price inputs it receives.

---

# 176. ORD-014 Historical Rule

ORD-014 adds the fee to already-fixed subtotal.

It must not reprice items.

The calculator interface should make that natural.

---

# 177. Completion Report

Return:

## Phase 7.6 status

```text
PASS
```

or:

```text
BLOCKED
```

## Canonical calculator

Report:

```text
class/component
result/value object
location
```

## Line totals

State exact formula.

## Subtotal

State exact formula.

## PICKUP

Report:

```text
fee = 0
fee status = FINALIZED
total = subtotal
financial finality = final
```

## DELIVERY pending

Report:

```text
fee = null
fee status = PENDING
total = subtotal
financial finality = provisional
```

## DELIVERY finalized

Report:

```text
fee >= 0
fee status = FINALIZED
total = subtotal + fee
financial finality = final
```

## Null vs zero

Explicitly confirm they remain different states.

## Currency

Confirm:

```text
TZS
integer minor units
no floats
```

## Overflow

Report protection for:

```text
unit price × quantity
subtotal accumulation
subtotal + fee
```

## Pickup integration

Report Phase 7.3 integration.

## Delivery integration

Report Phase 7.4 projection integration and state explicitly:

```text
Phase 7.4 remains BLOCKED
```

## ORD-014 integration

Report whether Phase 7.5 now delegates total calculation to the canonical calculator.

## Inventory

Must state:

```text
ProductStock mutation: NONE
reserved_quantity mutation: NONE
allocation mutation: NONE
```

## Persistence

Must state:

```text
Checkout Order inserts: NONE
Cart mutation: NONE
OrderItem inserts: NONE
billing snapshot persistence: NOT RESOLVED
```

## Payment

Must state:

```text
Payment creation: NONE
provider calls: NONE
```

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

## OpenAPI

Expected:

```text
UNCHANGED
```

## Tests

Report:

```text
focused calculator tests
Phase 7.3 regression
Phase 7.4 projection regression
Phase 7.5 regression
canonical suite
```

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Group G status

Report individually:

```text
7.1  PASS
7.2  PASS
7.3  PASS
7.4  BLOCKED — billing snapshot persistence
7.5  PASS
7.6  PASS/BLOCKED
```

Do not mark Group G closed.

## Phase 7.7 readiness

Return one:

```text
Phase 7.7 — READY
```

```text
Phase 7.7 — READY WITH DELIVERY PERSISTENCE BLOCKER
```

or:

```text
Phase 7.7 — BLOCKED
```

based on the updated roadmap and actual dependency analysis.

---

# 178. Definition of Done

Phase 7.6 is complete when:

- one canonical totals calculator exists;
- line total is `unit_price × quantity`;
- subtotal is the sum of line totals;
- PICKUP fee is zero;
- PICKUP fee status is FINALIZED;
- PICKUP total equals subtotal;
- PICKUP total is financially final;
- DELIVERY pending fee is null;
- DELIVERY pending fee status is PENDING;
- DELIVERY pending total equals subtotal;
- DELIVERY pending total is explicitly provisional;
- DELIVERY finalized fee is non-negative;
- DELIVERY finalized total is subtotal + fee;
- zero-fee finalized delivery works;
- null fee and zero fee remain distinct;
- currency remains TZS;
- money uses integers only;
- no float arithmetic exists;
- multiplication overflow is guarded;
- subtotal-addition overflow is guarded;
- fee-addition overflow is guarded;
- calculated values satisfy Order model invariants;
- calculated line values satisfy OrderItem invariants;
- PickupFulfillmentState no longer owns a competing total formula;
- DeliveryFulfillmentState no longer owns a competing total formula;
- ORD-014 no longer owns a competing total formula where clean integration is possible;
- historical Order subtotal is not repriced;
- no Cart subtotal is trusted as Checkout authority;
- calculator performs no DB query;
- calculator performs no inventory mutation;
- calculator performs no reservation;
- calculator performs no Order persistence;
- calculator performs no Cart mutation;
- calculator performs no billing snapshot persistence;
- no payment logic is introduced;
- Phase 7.4 remains explicitly BLOCKED;
- the billing-address persistence gap is not silently bypassed;
- no schema migration is introduced;
- no dependency is introduced;
- no frontend work occurs;
- OpenAPI remains unchanged;
- full regressions remain green;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean.

---

# 179. Out of Scope

Do not implement:

```text
Phase 7.4 billing-address persistence fix
Phase 7.7 transaction boundaries
Phase 7.8 Checkout validation
Phase 7.9 Checkout closure tests
Checkout Order creation
Cart clearing
inventory reservation
OrderItem persistence
status-history persistence
Delivery-row creation
billing snapshot schema
PAY-001
payment provider
payment webhook
reservation release
inventory consumption
frontend
```

---

# 180. STOP Condition

STOP when all financial branches use one canonical calculation authority:

```text
trusted line prices × quantities
→ line totals
→ subtotal

PICKUP
→ fee 0
→ FINALIZED
→ total = subtotal
→ final

DELIVERY/PENDING
→ fee null
→ PENDING
→ total = subtotal
→ provisional

DELIVERY/FINALIZED
→ fee >= 0
→ FINALIZED
→ total = subtotal + fee
→ final
```

while:

```text
Phase 7.4 remains BLOCKED
billing snapshot persistence remains unresolved
no Checkout Order is created
no inventory is reserved
no Cart is cleared
no Payment is created
```

Do not continue automatically to Phase 7.7.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.