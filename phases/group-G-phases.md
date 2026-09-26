# Phase 7.5 — Delivery Fee Rules

## Purpose

Implement and harden the Version 1 **delivery-fee finalization rules** for DELIVERY Orders.

This phase owns the business behavior around:

```text
ORD-014
POST /api/v1/orders/{order}/delivery-fee
```

It complements Phase 7.4:

```text
CHK-001 DELIVERY
→ Order PENDING_PAYMENT
→ delivery_fee = null
→ delivery_fee_status = PENDING
→ total = subtotal (provisional)
```

Phase 7.5 must implement the controlled transition:

```text
delivery_fee = null
delivery_fee_status = PENDING

        ↓ ORD-014

delivery_fee = authoritative Money
delivery_fee_status = FINALIZED
total = subtotal + delivery_fee
```

No payment is created.

No inventory is changed.

No Order status transition occurs.

---

# 1. Scope

Phase 7.5 owns:

```text
ORD-014 delivery-fee finalization
Staff/Admin authorization
orders.set_delivery_fee permission
strict fee input
zero-fee delivery support
TZS authority
minor-unit validation
PENDING → FINALIZED rule
total recomputation
idempotency
concurrency
audit recording
historical fee immutability
payment-eligibility boundary
focused tests
```

It does NOT own:

```text
CHK-001 Checkout transaction
delivery-fee estimation at Checkout
automatic city-based pricing
delivery zones
payment creation
PAY-001
payment provider
payment webhook
inventory reservation
reservation release
inventory consumption
Order acceptance/processing/shipping
frontend
```

---

# 2. Starting State

Assume:

```text
Group F — PASS / CLOSED
Phase 7.1 — PASS
Phase 7.2 — PASS
Phase 7.3 — PASS
Phase 7.4 — BLOCKED: billing snapshot persistence model gap
```

Phase 7.4 currently establishes a DELIVERY state projection, not a persisted
Order. The projection describes the intended CHK-001 output once the billing
snapshot model and persistence path are resolved:

```text
fulfillment_type = DELIVERY
status = PENDING_PAYMENT
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal   # provisional
payment = null
```

Phase 7.5 must not change that projected CHK-001 state or imply that DELIVERY
Checkout already persists it.

---

# 3. Canonical Endpoint

Implement/harden only:

```http
POST /api/v1/orders/{order}/delivery-fee
```

Operation:

```text
ORD-014
```

Do not add:

```text
PATCH /orders/{order}
PATCH /orders/{order}/delivery-fee
POST /orders/{order}/fee
POST /checkout/delivery-fee
```

---

# 4. ORD-014 Is Operational

This endpoint is not a customer action.

Allowed actors:

```text
STAFF
ADMIN
```

with:

```text
orders.set_delivery_fee
```

permission.

---

# 5. Customer Access

A CUSTOMER must not set or alter a delivery fee.

Expected:

```text
403 FORBIDDEN
```

for an authenticated customer attempting ORD-014.

---

# 6. Anonymous Access

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 7. STAFF Permission

STAFF must have:

```text
orders.set_delivery_fee
```

Being STAFF alone is insufficient if the authorization model requires explicit permission.

---

# 8. ADMIN

ADMIN may perform the operation according to the established highest-authority operational policy.

Still enforce all Order-state/business rules.

Admin does not bypass:

```text
PENDING_PAYMENT
DELIVERY
PENDING fee status
```

requirements.

---

# 9. Wrong Permission

Permissions such as:

```text
inventory.manage
products.manage
orders.view
```

must not implicitly grant delivery-fee authority.

Use exactly the established permission.

---

# 10. Request Contract

Canonical request:

```json
{
  "delivery_fee": {
    "amount": 35000,
    "currency": "TZS"
  }
}
```

Optional where frozen contract allows:

```json
{
  "delivery_fee": {
    "amount": 35000,
    "currency": "TZS"
  },
  "reason": "Mikocheni zone 2"
}
```

---

# 11. Strict Allow-List

Allowed fields:

```text
delivery_fee
reason   # optional only if current OpenAPI/contract includes it
```

Do not accept anything else.

---

# 12. Server-Controlled Fields

Reject:

```text
subtotal
total
status
delivery_fee_status
currency at top level
customer_id
user_id
order_reference
payment_status
payment
updated_at
created_at
```

---

# 13. Money Shape

`delivery_fee` must use canonical Money shape:

```json
{
  "amount": 35000,
  "currency": "TZS"
}
```

---

# 14. Amount Type

`amount` must be:

```text
integer
```

Reject:

```text
float
numeric string
boolean
null
array
object
```

---

# 15. Minimum

Valid:

```text
amount >= 0
```

Zero is explicitly valid.

---

# 16. Zero-Fee DELIVERY

This must succeed when all other conditions hold:

```json
{
  "delivery_fee": {
    "amount": 0,
    "currency": "TZS"
  }
}
```

A free delivery zone is valid business behavior.

---

# 17. Negative Fee

Reject:

```text
amount < 0
```

with:

```text
422 INVALID_VALUE
```

or exact frozen validation mapping.

---

# 18. Currency

Currency must be exactly:

```text
TZS
```

---

# 19. Reject Currency Drift

Reject:

```text
USD
EUR
KES
tzs
```

V1 is closed around TZS.

---

# 20. Minor Units

Fee amount uses integer minor units.

Never use:

```text
float
decimal currency arithmetic
formatted currency strings
```

---

# 21. Reason

If `reason` is present in current frozen request schema:

```text
trim
non-empty where supplied
audit safely
```

Do not make it required unless contract says so.

---

# 22. Reason Is Not Fee Authority

`reason` has no effect on:

```text
amount
currency
total
permissions
```

---

# 23. No Automatic Fee Calculation

The backend does NOT calculate the delivery fee from:

```text
city
address_line
distance
zone
warehouse
inventory location
order subtotal
item quantity
```

The authorized Staff/Admin supplies the authoritative fee.

---

# 24. No Flat Fee

Do not reintroduce:

```text
TZS 20,000
```

or any fixed default.

---

# 25. Order Preconditions

ORD-014 succeeds only if all hold:

```text
Order exists
actor authorized
fulfillment_type = DELIVERY
status = PENDING_PAYMENT
delivery_fee_status = PENDING
```

---

# 26. DELIVERY Only

For:

```text
fulfillment_type = PICKUP
```

ORD-014 must fail.

Expected:

```text
422 BUSINESS_RULE_VIOLATION
```

according to frozen contract.

---

# 27. Why PICKUP Fails

PICKUP already has:

```text
delivery_fee = 0 TZS
delivery_fee_status = FINALIZED
```

at Checkout.

There is nothing for ORD-014 to finalize.

---

# 28. Order Status Requirement

Order must still be:

```text
PENDING_PAYMENT
```

---

# 29. PAID Order

Do not allow ordinary ORD-014 fee finalization once Order is:

```text
PAID
```

The normal workflow requires fee finalization before payment.

---

# 30. Other Order States

Reject ORD-014 for:

```text
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

according to the frozen state rules.

---

# 31. Fee Status Requirement

Current value must be:

```text
PENDING
```

---

# 32. Already Finalized

If:

```text
delivery_fee_status = FINALIZED
```

a fresh different operation must fail:

```text
409 INVALID_ORDER_TRANSITION
```

unless it is a valid same-key idempotent replay of the original success.

---

# 33. One-Way Transition

Normal ORD-014 transition is exactly:

```text
PENDING → FINALIZED
```

There is no:

```text
FINALIZED → PENDING
```

---

# 34. One-Time Finalization

Do not support repeated arbitrary changes such as:

```text
35000
→ 50000
→ 20000
```

through normal ORD-014.

Once finalized, fee is historical.

---

# 35. Controlled Future Correction

If a later controlled Admin correction workflow exists or is introduced:

it is separate from normal ORD-014.

Do not implement it in Phase 7.5 unless explicitly frozen elsewhere.

---

# 36. Total Calculation

On successful finalization:

```text
total =
subtotal
+
delivery_fee.amount
```

---

# 37. Example

Given:

```text
subtotal = 170000000
delivery_fee = 2500000
```

result:

```text
total = 172500000
```

---

# 38. Zero-Fee Example

Given:

```text
subtotal = 170000000
delivery_fee = 0
```

result:

```text
total = 170000000
```

---

# 39. No Client Total

Client must not supply:

```text
total
```

even if mathematically correct.

Backend computes it.

---

# 40. No Client Subtotal

Subtotal remains historical Checkout authority.

ORD-014 must not recalculate or modify it.

---

# 41. Subtotal Is Immutable Here

ORD-014 reads:

```text
subtotal
```

and computes total from it.

Do not modify:

```text
subtotal_amount
```

---

# 42. Price Is Immutable Here

ORD-014 does not re-read Product/Variant prices.

OrderItems are already historical snapshots.

---

# 43. Cart Is Irrelevant

ORD-014 does not read or mutate the customer's Cart.

The Order already exists.

---

# 44. Inventory Is Unchanged

ORD-014 must not mutate:

```text
ProductStock.quantity
ProductStock.reserved_quantity
order_item_inventory_allocations
```

---

# 45. Reservation Remains Held

The Checkout-created stock reservation remains exactly as-is after successful fee finalization.

Do not:

```text
reserve again
release reservation
consume reservation
```

---

# 46. No Second Reservation

Finalizing delivery fee must never call:

```text
InventoryAllocator::reserve()
```

---

# 47. No Release

A successful ORD-014 does not release inventory.

---

# 48. No Consumption

A successful ORD-014 does not consume inventory.

---

# 49. Payment Boundary

Before ORD-014:

```text
delivery_fee_status = PENDING
PAY-001 blocked
```

After ORD-014:

```text
delivery_fee_status = FINALIZED
PAY-001 eligible from fee-state perspective
```

---

# 50. ORD-014 Does Not Create Payment

After fee finalization:

```text
payment = null
```

until PAY-001 is called.

---

# 51. No Payment Provider Call

Do not call:

```text
Stripe
Flutterwave
Pesapal
mobile money provider
bank gateway
```

or any provider.

---

# 52. No Status Change

Successful ORD-014 leaves:

```text
Order.status = PENDING_PAYMENT
```

---

# 53. No Status History Transition

Do not append a fake Order status change such as:

```text
FEE_FINALIZED
```

to `order_status_history` if that enum is not an Order status.

Fee finalization is a financial mutation, not an Order-status transition.

---

# 54. Audit Is Required

Successful fee finalization is privileged and auditable.

Audit should capture safe fields such as:

```text
actor
order
old delivery fee
new delivery fee
reason
occurred_at
request_id
```

according to existing Audit infrastructure.

---

# 55. Server-Derived Actor

Never accept:

```text
actor_id
actor_role
approved_by
```

from the request.

---

# 56. Server-Derived Timestamp

`occurred_at` must use server time.

Do not accept a client timestamp.

---

# 57. No Sensitive Audit Leakage

Do not audit:

```text
Authorization token
session JWT
payment secrets
unnecessary full customer address
```

---

# 58. Idempotency Required

ORD-014 requires:

```http
Idempotency-Key: <uuid>
```

---

# 59. Reuse Shared Service

Reuse existing:

```text
IdempotencyService
```

proven by earlier phases.

Do not create:

```text
DeliveryFeeIdempotencyService
```

---

# 60. Idempotency Scope

Use established scope:

```text
authenticated actor
+
action ORD-014
+
Idempotency-Key
```

---

# 61. Logical Fingerprint

Material request input should include normalized:

```text
order identity
delivery_fee.amount
delivery_fee.currency
reason   # if part of request semantics
```

Follow existing shared-service conventions.

---

# 62. Do Not Fingerprint Volatile State

Do not include:

```text
current total
updated_at
current reservation
payment availability
```

as logical client-input fingerprint unless shared infrastructure explicitly requires it.

---

# 63. Same-Key Replay

Same actor + same order + same key + same logical request:

```text
200
```

replay original successful result.

No second mutation.

No second audit event.

---

# 64. Replay After FINALIZED

This is critical.

After first success the Order is:

```text
delivery_fee_status = FINALIZED
```

A legitimate same-key retry must still replay success.

Do not reject it merely because the Order is no longer PENDING.

---

# 65. Correct Replay Ordering

Conceptually:

```text
authenticate
→ authorize
→ validate Idempotency-Key
→ validate request shape
→ normalize request
→ derive fingerprint
→ inspect durable idempotency record
→ matching completed replay?
     return original 200
→ otherwise lock/revalidate Order
→ execute transition
```

Do not:

```text
load Order
→ see FINALIZED
→ return INVALID_ORDER_TRANSITION
→ then check replay
```

---

# 66. Same Key, Different Amount

Example:

```text
first:
35000

same key retry:
50000
```

Expected:

```text
409 DUPLICATE_OPERATION
```

---

# 67. Same Key, Different Currency

Also conflict.

---

# 68. Same Key, Different Reason

If `reason` is included in fingerprint semantics:

different reason should conflict.

Follow shared conventions consistently.

---

# 69. Different Key After Finalized

A fresh different key against an already-finalized Order:

```text
409 INVALID_ORDER_TRANSITION
```

No fee rewrite.

---

# 70. Same Key, Different Order

Must not replay a result from another Order.

Order identity is part of operation scope/fingerprint.

---

# 71. Different Actor, Same Key

Idempotency is actor-scoped.

No cross-actor replay.

---

# 72. Concurrency Is Critical

ORD-014 must handle:

```text
Staff A sets fee
Staff B sets fee
```

safely.

---

# 73. Order Row Lock

Acquire a write lock on the Order before validating mutable business state for first execution.

Conceptually:

```text
SELECT ... FOR UPDATE
```

through repository conventions.

---

# 74. Revalidate Under Lock

Inside transaction re-check:

```text
fulfillment_type
status
delivery_fee_status
```

Do not trust a pre-transaction read.

---

# 75. Concurrent Different Fees

Two concurrent operations:

```text
A sets 35000
B sets 50000
```

must not both succeed.

Expected:

```text
one success
one conflict/state failure
```

depending exact locking/idempotency path.

---

# 76. No Lost Update

Never allow:

```text
fee=35000 committed
then silently overwritten with 50000
```

through normal ORD-014.

---

# 77. Race With PAY-001

Frozen contract requires critical concurrency for:

```text
fee SET
vs
PAY-001
```

Phase 7.5 should prepare/implement the fee-side lock/state semantics so Group H can safely share them.

---

# 78. Payment While Pending

PAY-001 must later reject:

```text
delivery_fee_status = PENDING
```

---

# 79. Payment After Finalized

PAY-001 may later proceed only after seeing:

```text
FINALIZED
```

inside its own transaction/state validation.

---

# 80. Atomic Fee Finalization

At minimum, one transaction must cover:

```text
Order lock
state revalidation
delivery_fee write
delivery_fee_status write
total recomputation
audit write
idempotency success record
```

where existing infrastructure supports atomic audit/idempotency participation.

---

# 81. No Partial Financial Mutation

Failure must not leave:

```text
delivery_fee set
but delivery_fee_status PENDING
```

or:

```text
status FINALIZED
but total old
```

---

# 82. Total and Fee Must Commit Together

These fields form one business transition:

```text
delivery_fee
delivery_fee_status
total
```

---

# 83. Order Model Invariants

Reuse `Order::assertValid()` / current financial invariants.

Do not bypass them with raw query updates unless repository architecture explicitly requires safe internal mutation.

---

# 84. Financial Immutability

Current model protects finalized/historical financial state.

ORD-014 must use the legitimate:

```text
PENDING
→ FINALIZED
```

path without weakening immutability globally.

---

# 85. Do Not Disable Model Protection

Do not:

```text
remove financial immutability hook
disable model events globally
mass update protected fields
```

just to make ORD-014 pass.

---

# 86. Dedicated Domain Action

Prefer a focused service such as:

```text
FinalizeDeliveryFee
SetDeliveryFee
```

following repository naming.

---

# 87. Service Responsibilities

The domain/application action may own:

```text
Order locking
state validation
fee normalization/value
total recomputation
financial transition
audit coordination
```

Do not put all logic in controller.

---

# 88. Controller

Controller should remain thin:

```text
validated request
→ action/service
→ resource
```

---

# 89. FormRequest

Use a strict request class.

Never:

```php
$request->all()
```

Use:

```php
$request->validated()
```

---

# 90. Additional Properties

Reject unknown request fields.

---

# 91. Resource

Use existing:

```text
OrderSummary
OrderOperationalDetail
```

or exact frozen ORD-014 response resource.

Do not serialize raw model.

---

# 92. Response

Successful ORD-014:

```text
200
```

with updated Order representation.

At minimum it must expose the frozen financial state:

```text
status = PENDING_PAYMENT
delivery_fee = {amount, currency}
delivery_fee_status = FINALIZED
total = subtotal + fee
```

---

# 93. Payment Still Null

If response includes `payment`:

```text
payment = null
```

until PAY-001 creates one.

---

# 94. Do Not Return Internal Allocation Data

No:

```text
ProductStock IDs
reservation allocation IDs
warehouse IDs
```

in ordinary customer/staff fee response unless separately contracted.

---

# 95. Masked Order Access

Use the established operational Order lookup/authorization semantics.

Unauthorized actors must not gain an existence oracle.

Follow current 404/403 rules exactly.

---

# 96. Order Not Found

Expected:

```text
404 ORDER_NOT_FOUND
```

or current canonical resource-not-found mapping.

Do not leak raw model exceptions.

---

# 97. PICKUP Error

Expected:

```text
422 BUSINESS_RULE_VIOLATION
```

---

# 98. Already Finalized Error

Expected:

```text
409 INVALID_ORDER_TRANSITION
```

for a fresh operation.

---

# 99. Wrong Order State

Expected endpoint mapping should use:

```text
409 INVALID_ORDER_TRANSITION
```

or:

```text
409 ORDER_STATE_CONFLICT
```

according to frozen distinction.

Do not invent a new code.

---

# 100. Concurrent Modification

Expected:

```text
409 ORDER_STATE_CONFLICT
```

where the current state changed under concurrency.

---

# 101. Invalid Amount

Expected:

```text
422 INVALID_VALUE
```

---

# 102. Invalid Currency

Expected:

```text
422 INVALID_VALUE
```

---

# 103. Missing Fee

Expected request-schema missing-field error.

---

# 104. Wrong Fee Type

Expected:

```text
422 INVALID_TYPE
```

or current validation mapping.

---

# 105. Missing Idempotency Key

Use shared required-header error semantics.

Do not generate a server key automatically.

---

# 106. Invalid Idempotency Key

Reject before mutation.

---

# 107. Rate Limit

Use the established operational mutation limiter.

Every:

```text
429
```

must include:

```text
Retry-After
```

---

# 108. Cache

ORD-014 response is private Order state.

Use:

```text
private
no-store
```

according to existing conventions.

---

# 109. No Checkout Fee Mutation

CHK-001 must continue to create DELIVERY as:

```text
fee=null
PENDING
```

Phase 7.5 must not move fee finalization into Checkout.

---

# 110. No Customer Fee Preview

Do not add a customer endpoint that calculates or previews the eventual fee.

---

# 111. No Fee Table

Do not create:

```text
delivery_fee_rules
delivery_zones
city_fees
distance_rates
```

unless separately approved in a future version.

---

# 112. Address Is Context, Not Algorithm

Staff/Admin may inspect Order delivery address operationally when deciding the fee.

Backend does not infer the fee from the address.

---

# 113. No Geocoding

No maps API.

---

# 114. No Distance Calculation

No kilometer formula.

---

# 115. No Automatic Zone Matching

Out of scope.

---

# 116. Historical Fee

After successful ORD-014:

```text
delivery_fee
```

is historical business data.

Future policy changes must not alter it.

---

# 117. Historical Total

After finalization:

```text
total
```

is authoritative final Order amount before payment.

---

# 118. Finality

For DELIVERY:

```text
delivery_fee_status = FINALIZED
```

is the financial finality gate for fee.

---

# 119. Payment Amount Dependency

Group H must later use:

```text
Order.total
```

after fee finalization.

It must not accept:

```text
client payment amount
provisional subtotal-only total
```

---

# 120. Notification Boundary

The contract mentions customer notification after fee finalization.

Do not make notification delivery failure roll back the successful fee transition.

Use existing notification failure-isolation convention if notification creation is in scope.

---

# 121. If Notification Infrastructure Is Deferred

Record:

```text
notification event / intent
```

only if current backend pattern already supports it.

Do not build Group R push/email.

---

# 122. In-App Notification

If current Order operation convention requires an in-app business notification:

implement only the established internal notification side effect.

No email/push.

---

# 123. Notification Is Not Transaction Authority

Financial mutation succeeds based on Order rules, not notification success.

---

# 124. Audit Failure

Follow existing audit integrity policy.

If privileged action requires audit transactionally:

ensure audit cannot silently disappear.

Do not invent inconsistent failure behavior.

---

# 125. No Order Status History Entry Unless Contract Says So

Fee status is not Order status.

Do not pollute `order_status_history` with fee-only records.

Use audit/financial state instead.

---

# 126. Test — Successful Fee

Given:

```text
DELIVERY
PENDING_PAYMENT
fee status PENDING
subtotal 170000000
```

request:

```text
2500000 TZS
```

assert:

```text
fee = 2500000
fee status = FINALIZED
total = 172500000
status = PENDING_PAYMENT
```

---

# 127. Test — Zero Fee

Request:

```text
0 TZS
```

succeeds.

---

# 128. Test — Negative Fee

Reject.

---

# 129. Test — Float Fee

Reject.

---

# 130. Test — Numeric String

Reject.

---

# 131. Test — Wrong Currency

Reject.

---

# 132. Test — Lowercase Currency

Reject.

---

# 133. Test — Unknown Field

Reject.

---

# 134. Test — Client Total

Reject.

---

# 135. Test — Client Status

Reject.

---

# 136. Test — Customer Actor

Customer:

```text
403
```

---

# 137. Test — Staff With Permission

Succeeds.

---

# 138. Test — Staff Without Permission

```text
403
```

---

# 139. Test — Admin

Succeeds when business state valid.

---

# 140. Test — PICKUP

```text
422 BUSINESS_RULE_VIOLATION
```

---

# 141. Test — PAID Order

Reject.

---

# 142. Test — ACCEPTED Order

Reject.

---

# 143. Test — CANCELLED Order

Reject.

---

# 144. Test — Already FINALIZED

Fresh key:

```text
409 INVALID_ORDER_TRANSITION
```

---

# 145. Test — Same-Key Replay After FINALIZED

Original successful key:

```text
200 replay
```

No second update.

---

# 146. Test — Same-Key Different Amount

```text
409 DUPLICATE_OPERATION
```

---

# 147. Test — Same-Key Different Order

Must not replay another Order's result.

---

# 148. Test — Different Actor Same Key

Separate actor scope.

---

# 149. Test — Audit Exactly Once

Successful first execution:

```text
1 audit event
```

Same-key replay:

```text
still 1
```

---

# 150. Test — Reservation Unchanged

Capture:

```text
reserved_quantity
quantity
allocations
```

before/after ORD-014.

Assert unchanged.

---

# 151. Test — Order Status Unchanged

Before:

```text
PENDING_PAYMENT
```

After:

```text
PENDING_PAYMENT
```

---

# 152. Test — Payment Not Created

No Payment row/representation side effect.

---

# 153. Test — Subtotal Unchanged

Fee finalization must not modify authoritative subtotal.

---

# 154. Test — Address Unchanged

Fee finalization must not modify historical delivery/billing snapshots.

---

# 155. Test — Items Unchanged

OrderItems remain unchanged.

---

# 156. Test — Cart Unchanged

Customer Cart remains untouched.

---

# 157. Test — Financial Atomicity

Force failure after fee mutation before transaction completion.

Assert rollback:

```text
fee remains null
status remains PENDING
total remains provisional
no audit success
no idempotency success
```

Use a safe test seam.

---

# 158. Test — Concurrent Same Key

MariaDB:

```text
same actor
same order
same key
same fee
```

Expected:

```text
one business mutation
one audit event
one durable result
both logical responses same
```

---

# 159. Test — Concurrent Different Fees

MariaDB:

```text
Staff A: 35000
Staff B: 50000
```

Expected:

```text
exactly one final fee
no overwrite
other request conflicts/fails state validation
```

---

# 160. Test — Same Fee Different Keys Race

Two distinct keys with same fee:

still only one first-time transition.

Do not execute the transition twice.

---

# 161. Test — Fee vs Simulated Payment Lock

If PAY-001 is not implemented yet:

add an integration-level lock/state test only if the repository can safely model the competing transaction without building Group H.

Otherwise document this as required Group H concurrency coverage.

Do not invent PAY-001 early.

---

# 162. MariaDB Requirement

Real concurrency behavior must be proven on MariaDB/MySQL.

SQLite alone is insufficient for row-lock semantics.

---

# 163. Disposable DB

Reuse existing guarded disposable test DB conventions.

Never run destructive concurrency harness against production/dev primary DB.

---

# 164. Idempotency Persistence

Verify successful response can be replayed after the Order is already FINALIZED.

This is essential.

---

# 165. Response Status Replay

ORD-014 replay must preserve:

```text
200
```

not return another status.

---

# 166. Shared Idempotency Improvements

If Phase 7.1 identified shared service shortcomings around response-status storage:

do not prematurely solve CHK-001-specific `201` behavior here unless the shared improvement is clean and required for ORD-014.

ORD-014 itself uses `200`.

---

# 167. No New Idempotency Schema

Expected:

```text
NONE
```

Reuse durable shared store.

---

# 168. Order Model

Use current:

```text
DeliveryFeeStatus
FulfillmentType
OrderStatus
```

enums.

No magic strings.

---

# 169. Money Arithmetic

Use safe integer addition.

Check for any existing overflow guard/convention.

Do not cast to float.

---

# 170. Overflow Defense

If:

```text
subtotal + fee
```

could exceed supported integer/database range:

fail safely according to existing money/domain convention.

Do not wrap.

---

# 171. Maximum Fee

Do not invent a business maximum unless frozen contract/schema already defines one.

Current normative minimum is:

```text
>= 0
```

If database type imposes technical upper bound, validate safely against representable range.

---

# 172. No Percent Fee

Do not add percentage pricing.

---

# 173. No Discount Fee

Do not add negative fee as discount.

Zero is minimum.

---

# 174. No Tax Logic

Do not add tax.

---

# 175. No Surcharge Type

Do not add fee categories.

---

# 176. No Customer Negotiation State

Do not add:

```text
fee proposed
fee accepted
fee rejected
```

states.

V1 has:

```text
PENDING
FINALIZED
```

only.

---

# 177. No Second Approval

No dual-admin approval requirement exists.

Do not invent one.

---

# 178. No Customer Confirmation Endpoint

Not part of V1.

---

# 179. No Automatic Refund Logic

Out of scope.

---

# 180. OpenAPI

Keep ORD-014 aligned with current frozen:

```text
POST /orders/{order}/delivery-fee
Idempotency-Key required
SetDeliveryFeeRequest
200
401
403
404
409
422
429
```

---

# 181. Do Not Broaden Request Schema

No additional:

```text
zone
distance
city
calculated_by
payment_amount
```

fields.

---

# 182. Documentation

Add/update backend ADR only for implementation-specific choices such as:

```text
lock ordering
idempotency replay ordering
audit atomicity
```

Do not rewrite the frozen fee policy.

---

# 183. Suggested Service Boundary

Potential structure:

```text
SetDeliveryFeeRequest
→ OrderController
→ FinalizeDeliveryFee
→ Order model
→ AuditRecorder
→ IdempotencyService
→ Order Resource
```

Use actual project patterns.

---

# 184. Thin Controller

Controller should not contain:

```text
fee state machine
money arithmetic
row locking
audit construction
idempotency logic
```

---

# 185. Authorization Before Mutation

Resolve authenticated actor and permission before business mutation.

Still re-check mutable Order business state under lock.

---

# 186. Information Disclosure

Do not expose customer private address or other Order data beyond the authorized operational response shape.

---

# 187. N+1

Single-resource ORD-014 is not a collection N+1 risk.

Do not add broad eager-loading optimization work unless the response resource triggers accidental lazy queries.

---

# 188. Resource Loading

Load only relationships needed for the frozen response.

Avoid unnecessary Customer/payment/inventory relation loading.

---

# 189. Notifications

If fee-finalized in-app notification is required by current notification contract, ensure it is not duplicated on replay.

---

# 190. Replay Side Effects

Same-key replay must NOT duplicate:

```text
audit
notification
database writes
```

---

# 191. Failed Attempts

Validation/state failures must not create success audit/notification records.

Security logging can remain separate under existing conventions.

---

# 192. Existing PICKUP Regression

Run Phase 7.3 tests.

No change to:

```text
PICKUP fee = 0
PICKUP fee status = FINALIZED
PICKUP total = subtotal
```

---

# 193. Existing DELIVERY Regression

Run Phase 7.4 tests.

Initial DELIVERY state must remain:

```text
fee null
PENDING
provisional total=subtotal
```

before ORD-014.

---

# 194. Order Model Regression

Run financial-immutability tests.

Ensure legitimate:

```text
PENDING → FINALIZED
```

still succeeds.

---

# 195. Idempotency Regression

Run existing:

```text
INV-003
CART-005
operational action
```

idempotency suites if shared service changes.

---

# 196. Audit Regression

Run focused audit tests if AuditRecorder or shared action code changes.

---

# 197. Schema

Expected:

```text
NONE
```

---

# 198. Dependencies

Expected:

```text
NONE
```

---

# 199. Frontend

Expected:

```text
NONE
```

---

# 200. Verification

Run focused tests, then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 201. MariaDB Verification

Run dedicated concurrency coverage for ORD-014.

Report exact:

```text
tests
assertions
race count
```

---

# 202. Route Verification

Confirm:

```text
POST /api/v1/orders/{order}/delivery-fee
→ real implementation
```

if Phase 7.5 is the implementation point.

No duplicate route.

---

# 203. Completion Report

Return:

## Phase 7.5 status

```text
PASS
```

or:

```text
BLOCKED
```

## ORD-014

Report implementation component and route status.

## Authorization

Report:

```text
CUSTOMER = denied
STAFF + orders.set_delivery_fee = allowed
STAFF without permission = denied
ADMIN = allowed subject to state
```

## Request

Report exact allow-list.

## Fee rules

Report:

```text
integer minor units
amount >= 0
zero valid
currency TZS
```

## Order preconditions

Report:

```text
DELIVERY
PENDING_PAYMENT
delivery_fee_status=PENDING
```

## Transition

Report:

```text
delivery_fee null → Money
PENDING → FINALIZED
total=subtotal+fee
```

## Historical immutability

Confirm subsequent fresh ORD-014 cannot overwrite finalized fee.

## PICKUP

Confirm ORD-014 rejects PICKUP.

## Idempotency

Report:

```text
same-key replay
same-key different fee
replay after FINALIZED
different-key finalized behavior
durable store reuse
```

## Concurrency

Report:

```text
same-key race
different-fee race
same-fee different-key race
```

## Inventory

Must state:

```text
ProductStock quantity mutations: NONE
reserved_quantity mutations: NONE
reservation changes: NONE
allocation changes: NONE
```

## Payment

Must state:

```text
payment created: NO
provider calls: NONE
Order status remains PENDING_PAYMENT
PAY-001 becomes eligible only from fee-state perspective
```

## Audit

Report exactly-once privileged audit behavior.

## Notification

Report behavior if implemented/required.

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

## MariaDB

Report real concurrency results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
route:list
```

## Phase 7.6 readiness

Return:

```text
Phase 7.6 — Order totals: READY
```

or:

```text
BLOCKED
```

with exact reason.

---

# 204. Definition of Done

Phase 7.5 is complete when:

- ORD-014 is implemented according to the frozen contract;
- endpoint requires authentication;
- CUSTOMER cannot set fee;
- STAFF requires `orders.set_delivery_fee`;
- ADMIN remains subject to Order-state rules;
- Idempotency-Key is mandatory;
- fee amount is a strict integer;
- fee amount may be zero;
- negative fee is rejected;
- currency must be exactly TZS;
- unknown fields are rejected;
- subtotal cannot be supplied;
- total cannot be supplied;
- Order status cannot be supplied;
- only DELIVERY Orders can use ORD-014;
- Order must be PENDING_PAYMENT;
- delivery fee must still be PENDING;
- fee transition is PENDING→FINALIZED exactly once;
- delivery fee becomes historical after finalization;
- total becomes subtotal + fee;
- subtotal remains unchanged;
- Order status remains PENDING_PAYMENT;
- payment remains null;
- no payment provider call occurs;
- existing inventory reservation remains held;
- ProductStock quantity does not change;
- reserved_quantity does not change;
- allocation rows do not change;
- same-key replay after finalization succeeds;
- same-key changed amount conflicts;
- fresh key cannot overwrite finalized fee;
- concurrent fee writes cannot overwrite one another;
- privileged audit is exactly once;
- replay does not duplicate audit/notification;
- PICKUP behavior remains unchanged;
- initial DELIVERY pending-fee behavior remains unchanged;
- no fee calculator is introduced;
- no delivery-zone table is introduced;
- no geocoding/distance logic is introduced;
- no schema migration is required;
- no dependency is added;
- no frontend changes occur;
- MariaDB concurrency verification passes;
- full test suite remains green;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean.

---

# 205. Out of Scope

Do not implement:

```text
Phase 7.6 totals engine beyond required ORD-014 recomputation
Phase 7.7 Checkout transaction
Phase 7.8 Checkout validation
Phase 7.9 Checkout closure tests
PAY-001
payment provider
payment webhook
inventory reservation
reservation release
stock consumption
delivery zones
distance pricing
geocoding
automatic delivery-fee calculation
customer fee acceptance
refunds
frontend
```

---

# 206. STOP Condition

STOP when the backend can enforce this exact transition safely and idempotently:

```text
DELIVERY
PENDING_PAYMENT
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
reserved inventory unchanged

        ↓
authorized ORD-014
Idempotency-Key required

delivery_fee = authoritative non-negative TZS Money
delivery_fee_status = FINALIZED
total = subtotal + delivery_fee
status = PENDING_PAYMENT
payment = null
reserved inventory unchanged
```

with:

```text
no overwrite after finalization
no customer authority
no inventory mutation
no payment creation
no duplicate side effects on replay
```

Do not continue automatically to Phase 7.6.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
