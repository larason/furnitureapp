# Phase 7.4 — Delivery Flow

## Purpose

Implement the **DELIVERY fulfillment branch** of CHK-001 while preserving the frozen Version 1 API contract and Model B delivery-fee workflow.

The canonical DELIVERY state immediately after successful Checkout is:

```text
fulfillment_type    = DELIVERY

delivery_address    = {
  recipient_name,
  phone,
  address_line,
  city
}

billing_address     = copy(delivery_address)

delivery_fee        = null
delivery_fee_status = PENDING

subtotal            = authoritative Checkout subtotal
total               = subtotal   # provisional

status              = PENDING_PAYMENT
payment             = null
```

The DELIVERY total is **not final** while:

```text
delivery_fee_status = PENDING
```

Payment must remain blocked until Staff/Admin finalizes the fee through `ORD-014`.

---

# 1. Phase Scope

Phase 7.4 owns:

```text
DELIVERY fulfillment semantics
DELIVERY address requirement
DELIVERY historical address snapshot
billing-address copy semantics
Model B pending fee state
provisional total semantics
DELIVERY Order-state preparation
DELIVERY response-state preparation
DELIVERY-specific domain tests
```

It does NOT own:

```text
ORD-014 implementation
delivery-fee calculation
Staff/Admin fee assignment
full Checkout transaction
inventory reservation orchestration
Cart locking
Order transaction orchestration
final totals engine
payment creation
provider integration
payment webhook
delivery operational lifecycle
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
```

Phase 7.1 froze the overall Checkout requirements.

Phase 7.2 established that the public V1 address vocabulary remains:

```text
recipient_name
phone
address_line
city
```

Phase 7.3 established the PICKUP branch.

Phase 7.4 must now provide the corresponding DELIVERY branch without changing those decisions.

---

# 3. Frozen DELIVERY Request

Valid conceptual request:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha Mwangi",
    "phone": "+255700000001",
    "address_line": "Block C, Mikocheni B, Dar es Salaam",
    "city": "Dar es Salaam"
  }
}
```

---

# 4. `city` Remains Canonical

The public field is:

```text
city
```

not:

```text
region
```

Do not reintroduce the superseded region change from the abandoned Phase 7.1 revision.

All public:

```text
Checkout request
Checkout response
Order snapshot
Order resource
validation paths
tests
```

must continue to use:

```text
city
```

---

# 5. DELIVERY Requires Address

For:

```text
fulfillment_type = DELIVERY
```

`delivery_address` is mandatory.

Missing:

```text
delivery_address
```

must fail.

Do not fall back to:

```text
Customer profile
previous Order
saved address
browser location
IP location
```

---

# 6. Required Address Shape

The address object contains exactly:

```text
recipient_name
phone
address_line
city
```

No additional V1 fields.

---

# 7. Required Nested Fields

All four are required.

Reject:

```text
missing recipient_name
missing phone
missing address_line
missing city
```

---

# 8. Unknown Address Fields

Reject:

```text
region
district
country
postal_code
latitude
longitude
delivery_instructions
saved_address_id
```

because the nested object is strict.

---

# 9. No `region` Alias

Do not support:

```text
delivery_address.region
```

as:

```text
delivery_address.city
```

V1 is frozen.

---

# 10. Address Normalization

Use Phase 7.2's normalized representation.

Expected behavior:

```text
trim recipient_name
normalize phone
trim address_line
trim city
```

Do not perform broad location normalization.

---

# 11. Recipient Name

Must remain:

```text
required
string
trimmed
non-empty
```

and respect the established persistence limit.

Do not derive it from the User profile.

---

# 12. Phone

Must remain:

```text
required
string
trimmed
normalized
non-empty
```

according to the approved Phase 7.2 phone rules.

Do not make it optional because the authenticated User has a phone.

---

# 13. Address Line

Must remain:

```text
required
string
trimmed
non-empty
```

---

# 14. City

Must remain:

```text
required
string
trimmed
non-empty
```

Do not turn it into an enum.

---

# 15. No City Whitelist

Do not restrict Checkout to a hardcoded list such as:

```text
Dar es Salaam
Dodoma
Arusha
Mwanza
```

The frozen contract defines `city` as a string.

---

# 16. Historical Snapshot

The normalized DELIVERY address becomes historical Order data.

After Order creation:

```text
User profile changes
address-helper changes
future saved addresses
```

must not rewrite the historical snapshot.

---

# 17. Snapshot Contents

Order delivery snapshot must preserve:

```text
recipient_name
phone
address_line
city
```

exactly as normalized at Checkout.

---

# 18. Billing Address

V1 does not separately collect:

```text
billing_address
```

from the customer.

For DELIVERY:

```text
billing_address = copy(delivery_address)
```

---

# 19. Billing Address Is a Snapshot

It is not:

```text
reference to delivery_address object
profile address
saved address
```

Conceptually copy the values into the historical Order representation.

---

# 20. Billing Address Public Shape

If serialized later:

```text
recipient_name
phone
address_line
city
```

must match the frozen snapshot schema.

No `region`.

---

# 21. Checkout Request Must Reject Billing Address

Because billing address is server-derived in V1:

```json
{
  "billing_address": { ... }
}
```

must fail strict Checkout validation.

---

# 22. No Delivery Row at CHK-001

Per Phase 7.1:

```text
CHK-001 does not create the operational Delivery row
```

The Order carries the historical address snapshot first.

Do not create a Delivery model merely because fulfillment type is DELIVERY.

---

# 23. Later Delivery Record

When operational fulfillment later creates a Delivery record:

it must agree with the Order's historical fulfillment meaning.

Phase 7.4 should only ensure the DELIVERY branch provides suitable snapshot data.

---

# 24. No Tracking Yet

Do not create:

```text
tracking number
delivery status
driver assignment
GPS state
shipping carrier
```

during Checkout.

---

# 25. Initial Order Status

DELIVERY Checkout eventually creates:

```text
status = PENDING_PAYMENT
```

not:

```text
PAID
ACCEPTED
PROCESSING
SHIPPED
DELIVERED
```

---

# 26. Model B

Version 1 uses:

```text
fee-after-order
```

for DELIVERY.

Immediately after CHK-001:

```text
delivery_fee = null
delivery_fee_status = PENDING
```

---

# 27. Checkout Must Not Calculate Fee

Do not:

```text
calculate fee from city
calculate fee from address
calculate fee from distance
calculate fee from inventory location
use flat fee
```

Phase 7.4 must leave the fee pending.

---

# 28. Old Flat Fee Is Dead

Do not reintroduce:

```text
TZS 20,000
```

The previous flat-fee assumption was superseded.

---

# 29. Customer Cannot Supply Fee

Any Checkout body containing:

```text
delivery_fee
```

must fail strict validation.

Even:

```json
"delivery_fee": null
```

is client control over a server field and must be rejected.

---

# 30. Delivery Fee Status

Initial DELIVERY state:

```text
delivery_fee_status = PENDING
```

Do not set:

```text
FINALIZED
```

during CHK-001.

---

# 31. Provisional Total

Initial DELIVERY Checkout uses:

```text
total = subtotal
```

because:

```text
delivery_fee = null
```

This total is provisional.

---

# 32. Total Is Required but Not Final

Do not confuse:

```text
total is present
```

with:

```text
total is payable/final
```

For DELIVERY:

```text
delivery_fee_status = PENDING
```

means the current total is provisional.

---

# 33. Financial Finality Signal

Clients must determine finality from:

```text
delivery_fee_status
```

not merely from the presence of:

```text
total
```

---

# 34. Final Total Later

After ORD-014:

```text
delivery_fee = {amount, currency}
delivery_fee_status = FINALIZED
total = subtotal + delivery_fee.amount
```

Phase 7.4 does not implement that mutation.

---

# 35. Zero-Fee DELIVERY

A future Staff/Admin finalization may legitimately set:

```text
delivery_fee.amount = 0
```

for a free delivery zone.

But CHK-001 must still start DELIVERY as:

```text
delivery_fee = null
delivery_fee_status = PENDING
```

Do not infer zero delivery automatically.

---

# 36. Currency

Currency remains:

```text
TZS
```

server-controlled.

---

# 37. Minor Units

All amounts use integer minor units.

No floats.

---

# 38. Subtotal Authority

DELIVERY branch does not calculate the subtotal itself.

Subtotal comes from:

```text
current validated Product/Variant prices
×
Cart quantities
```

under Checkout's authoritative pricing workflow.

---

# 39. Phase 7.6 Boundary

Phase 7.6 owns the reusable totals calculation.

Phase 7.4 should only enforce:

```text
initial DELIVERY total = subtotal
```

and:

```text
initial fee = null
```

---

# 40. No Duplicate Totals Engine

Do not create a DELIVERY-only arithmetic implementation that Phase 7.6 must later replace.

---

# 41. Payment

Initial DELIVERY Checkout:

```text
payment = null
```

No Payment record or API representation is created by CHK-001.

---

# 42. Payment Block

While:

```text
delivery_fee_status = PENDING
```

PAY-001 later must fail:

```text
409 DELIVERY_FEE_PENDING
```

Phase 7.4 must preserve enough state for that rule.

---

# 43. Do Not Implement PAY-001

Group H owns payment creation and provider communication.

---

# 44. No Provider Call

DELIVERY branch performs no:

```text
payment provider request
authorization
capture
webhook
payment intent
```

---

# 45. Checkout Stock Rules Still Apply

DELIVERY is not a soft reservation path.

The same Checkout requirements apply:

```text
current Product
current Variant
current price
current authoritative stock
```

---

# 46. DELIVERY Reserves Stock at Checkout

Successful CHK-001 later reserves inventory immediately.

Do not delay stock reservation until delivery fee finalization.

---

# 47. Why Reservation Happens Before Fee

The frozen workflow intentionally creates:

```text
PENDING_PAYMENT DELIVERY Order
+
reserved inventory
+
pending delivery fee
```

until Staff/Admin finalizes fee or the Order eventually cancels/expires.

---

# 48. No Physical Consumption

At Checkout:

```text
physical quantity unchanged
reserved_quantity increases
```

Consumption remains later.

---

# 49. Phase 7.7 Owns Reservation Transaction

Phase 7.4 must not independently call:

```text
InventoryAllocator::reserve()
```

unless current roadmap explicitly uses branch composition inside a non-committing builder.

Actual atomic reservation belongs in Phase 7.7.

---

# 50. Reservation Lifecycle Dependency

Later:

```text
cancellation
payment failure
expiry
```

releases reservation.

Payment success later consumes it.

Do not implement these flows.

---

# 51. DELIVERY Does Not Bypass Cart Validation

A DELIVERY Cart must still reject:

```text
empty Cart
stale Product
MADE_TO_ORDER
invalid Variant
insufficient stock
```

according to frozen CHK-001 mappings.

---

# 52. MADE_TO_ORDER

Still:

```text
422 PRODUCT_NOT_PURCHASABLE
```

No "delivery request" conversion.

---

# 53. Address Does Not Affect Product Eligibility

Do not mix address validation with Product/Variant eligibility.

Keep concerns separate.

---

# 54. Suggested DELIVERY Component

Use a focused component consistent with Phase 7.3, for example:

```text
DeliveryFulfillment
BuildDeliveryCheckoutState
DeliveryFulfillmentState
```

Use actual project conventions.

---

# 55. Symmetry With PICKUP

Prefer a common branch interface so later Checkout orchestration can conceptually do:

```text
fulfillmentResolver.resolve(normalizedCheckoutInput)
```

returning either:

```text
PickupFulfillmentState
```

or:

```text
DeliveryFulfillmentState
```

---

# 56. Do Not Over-Abstract

Do not create:

```text
FulfillmentEngine
ShippingFramework
AddressPolicyGraph
```

for two branches.

---

# 57. DELIVERY Branch Responsibilities

A DELIVERY branch component may own:

```text
validated normalized address
billing-address snapshot copy
fulfillment type
delivery fee pending state
delivery fee status
```

It should not own:

```text
Cart
inventory
Order insert
idempotency
payment
```

---

# 58. Immutable Branch State

Prefer immutable state so this cannot happen accidentally:

```text
DELIVERY
delivery_fee_status = FINALIZED
delivery_fee = null
```

at initial Checkout.

---

# 59. Core DELIVERY Invariant

Encode:

```text
DELIVERY at CHK-001
implies
delivery_address != null
billing_address = delivery_address snapshot copy
delivery_fee = null
delivery_fee_status = PENDING
```

---

# 60. Model Compatibility

Verify Order model accepts:

```text
fulfillment_type = DELIVERY
delivery_fee_amount = null
delivery_fee_status = PENDING
total_amount = subtotal_amount
```

---

# 61. Order Model Invariant

Current Order model already defines DELIVERY + PENDING as:

```text
delivery_fee = null
total = subtotal
```

Do not fight that invariant.

---

# 62. Address Persistence

Verify Order persistence can store the normalized:

```text
recipient_name
phone
address_line
city
```

snapshot.

---

# 63. No Data Loss

Do not silently lose:

```text
recipient_name
phone
address_line
city
```

during model mapping.

---

# 64. Internal Address Mapping

If Phase 7.2 retained any internal legacy mapping:

reuse that mapping.

Do not reintroduce a second `city ↔ region` translation layer.

---

# 65. Public Contract Remains `city`

Any serializer/resource must return:

```text
city
```

not:

```text
region
```

---

# 66. DELIVERY Response

Future successful CHK-001 response should conceptually contain:

```json
{
  "data": {
    "order_id": "ord_...",
    "order_reference": "OD-.....",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "DELIVERY",
    "delivery_address": {
      "recipient_name": "Asha Mwangi",
      "phone": "+255700000001",
      "address_line": "Block C, Mikocheni B, Dar es Salaam",
      "city": "Dar es Salaam"
    },
    "subtotal": {
      "amount": 170000000,
      "currency": "TZS"
    },
    "delivery_fee": null,
    "delivery_fee_status": "PENDING",
    "total": {
      "amount": 170000000,
      "currency": "TZS"
    },
    "currency": "TZS",
    "payment": null
  }
}
```

Use actual amounts in implementation.

---

# 67. Do Not Add Billing Address to Checkout Response Arbitrarily

`CheckoutResponseData` and `OrderDetail` are separate contracts.

If Checkout response does not include:

```text
billing_address
```

do not add it merely because Order persistence stores it.

---

# 68. Historical Order Resource

Later Order detail may expose both:

```text
delivery_address
billing_address
```

according to the frozen Order contract.

Phase 7.4 should ensure stored snapshots support that.

---

# 69. Address Copy Semantics

For V1 DELIVERY:

```text
billing_address values == delivery_address values
```

at creation.

Do not store a pointer/reference that can later diverge unintentionally.

---

# 70. No Separate Billing Input

Reject:

```json
{
  "billing_address": { ... }
}
```

as an unknown Checkout field.

---

# 71. No Saved Address ID

Reject:

```text
saved_address_id
```

---

# 72. No Delivery Instructions

Do not add:

```text
instructions
notes
landmark
```

to Checkout address.

---

# 73. No Delivery Zone

Do not derive:

```text
zone
```

during this phase.

---

# 74. No City-Based Fee Calculation

`city` is a fulfillment snapshot field, not a pricing authority.

---

# 75. No Geocoding

Do not call external mapping/geolocation APIs.

---

# 76. No Coordinates

Do not add:

```text
latitude
longitude
```

---

# 77. No Country

Do not add `country`.

---

# 78. No Postal Code

Do not add it.

---

# 79. No Region

Do not add it publicly.

---

# 80. No Courier Selection

Do not add:

```text
carrier
courier
shipping_method
```

---

# 81. No Delivery Speed

Do not add:

```text
standard
express
same_day
```

---

# 82. No ETA

Do not calculate delivery ETA.

---

# 83. No Delivery Fee Estimate

Do not send:

```text
estimated_delivery_fee
```

---

# 84. No Provisional Client Fee

The only provisional money in DELIVERY is:

```text
total = subtotal
```

because delivery fee is not yet known.

Do not fabricate a fee estimate.

---

# 85. Payment Eligibility Flag

Do not add:

```text
can_pay
payment_ready
```

unless frozen contract contains it.

The existing signal is:

```text
delivery_fee_status
```

plus:

```text
payment = null
```

---

# 86. Idempotency Fingerprint

Normalized DELIVERY address participates in Checkout's future logical fingerprint.

Conceptually:

```text
fulfillment_type = DELIVERY
delivery_address = normalized snapshot
```

---

# 87. Deterministic Normalization

Fingerprint input must use normalized values from Phase 7.2.

Do not fingerprint raw whitespace-sensitive payloads.

---

# 88. Field Order

JSON property order must not affect fingerprint.

Use normalized structured data.

---

# 89. Same Address Whitespace

Example:

```text
" Dar es Salaam "
```

and:

```text
"Dar es Salaam"
```

should become the same logical normalized city if trimming is supported.

---

# 90. Same-Key Same DELIVERY Intent

Later:

```text
same Customer
same Idempotency-Key
same normalized DELIVERY address
```

must replay original 201.

---

# 91. Same-Key Changed Address

Changing:

```text
city
address_line
phone
recipient_name
```

materially changes Checkout intent.

Expected later:

```text
409 DUPLICATE_OPERATION
```

---

# 92. Same-Key PICKUP→DELIVERY

Also a material conflict.

---

# 93. No Branch-Specific Idempotency Store

Use shared Checkout idempotency later.

---

# 94. Authentication

DELIVERY remains:

```text
CUSTOMER-only
```

---

# 95. Guest Checkout

Guest token alone remains:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 96. Staff/Admin

Do not broaden CHK-001 actor semantics.

---

# 97. Cart Authority

Server derives Customer's own ACTIVE Cart.

No Cart ID accepted.

---

# 98. Address Cannot Select Cart

No relationship between:

```text
delivery_address
```

and Cart ownership.

---

# 99. Strict Request

Allowed top-level fields remain:

```text
fulfillment_type
delivery_address
```

---

# 100. Reject Client Financial Fields

Reject:

```text
delivery_fee
delivery_fee_status
subtotal
total
currency
unit_price
line_total
```

---

# 101. Reject Client Order State

Reject:

```text
status
payment_status
order_reference
```

---

# 102. Reject Client Ownership

Reject:

```text
cart_id
user_id
customer_id
```

---

# 103. Address Error Mapping

Maintain Phase 7.1 distinctions.

Missing delivery address:

```text
MISSING_REQUIRED_FIELD
```

or exact schema mapping.

---

# 104. Missing Nested Field

Example:

```text
delivery_address.city missing
```

must point to the canonical field path.

---

# 105. Wrong Nested Type

Example:

```json
"city": 123
```

must fail:

```text
INVALID_TYPE
```

or exact frozen validator mapping.

---

# 106. Unknown Nested Field

Example:

```text
delivery_address.region
```

must fail:

```text
INVALID_VALUE
```

or exact strict-field mapping.

---

# 107. Domain-Invalid Normalized Address

If syntactically valid input becomes invalid after phone normalization/domain checks:

use:

```text
INVALID_DELIVERY_INFORMATION
```

where Phase 7.1 established that boundary.

---

# 108. DELIVERY + Null Address

Invalid.

Unlike PICKUP:

```text
delivery_address = null
```

is not valid for DELIVERY.

---

# 109. DELIVERY + Empty Object

Invalid.

---

# 110. DELIVERY + Whitespace Fields

Invalid after normalization.

---

# 111. No Partial Snapshot

Do not allow:

```text
address_line + city only
```

with contact inferred elsewhere.

All four fields are required.

---

# 112. Order Status History

Future successful DELIVERY Checkout creates initial:

```text
PENDING_PAYMENT
```

history entry.

No special DELIVERY initial state.

---

# 113. Delivery Status Not Yet Present

Do not initialize operational delivery state just because fulfillment type is DELIVERY.

---

# 114. Future Operational Lifecycle

DELIVERY later conceptually follows:

```text
PENDING_PAYMENT
→ PAID
→ ACCEPTED
→ PROCESSING
→ SHIPPED
→ DELIVERED
→ COMPLETED
```

Phase 7.4 does not implement these transitions.

---

# 115. No READY_FOR_PICKUP

DELIVERY must never use pickup-only operational state.

Do not implement transition guards here; preserve fulfillment meaning for later operations.

---

# 116. Financial Historical State

Once ORD-014 finalizes delivery fee:

that fee becomes historical Order data.

Phase 7.4 must not recalculate it from current address later.

---

# 117. Address Changes After Checkout

There is no ordinary V1 operation to mutate the Checkout delivery snapshot.

Do not add one.

---

# 118. User Profile Changes

Must not alter Order delivery/billing snapshots.

---

# 119. City Policy Changes

Future delivery-pricing policy changes must not rewrite historical Order address or finalized fee.

---

# 120. DELIVERY State Builder

The desired branch result should conceptually be:

```text
DeliveryFulfillmentState {
    fulfillmentType = DELIVERY
    deliveryAddress = normalized snapshot
    billingAddress = copy(snapshot)
    deliveryFee = null
    deliveryFeeStatus = PENDING
}
```

---

# 121. Total Integration

Phase 7.6 can then combine:

```text
subtotal
+
pending delivery fee semantics
```

to produce:

```text
provisional total = subtotal
```

---

# 122. No DB Query in Pure Branch Builder

A pure fulfillment builder should not query:

```text
Cart
Product
ProductStock
Order
```

---

# 123. No Fee Lookup

Do not query a delivery-fee table.

There should be no such V1 dependency.

---

# 124. No Staff Permission Check

Phase 7.4 Customer Checkout does not require:

```text
orders.set_delivery_fee
```

Staff/Admin permission belongs to ORD-014 later.

---

# 125. No Audit Event for Pending Fee

Creating an Order with:

```text
delivery_fee_status = PENDING
```

is normal Checkout behavior, not privileged fee assignment.

Do not emit ORD-014 audit semantics yet.

---

# 126. Private Data

Delivery address is customer-private Order data.

Do not log full:

```text
recipient_name
phone
address_line
```

---

# 127. Safe Logging

Logs may include:

```text
request_id
operation
fulfillment_type=DELIVERY
safe internal resource IDs
failure code
```

Avoid raw address payload.

---

# 128. Cache

Checkout/Order response remains:

```text
private
no-store
```

---

# 129. Mass Assignment

Do not mass-assign raw request address arrays into Order.

Map validated normalized fields explicitly.

---

# 130. No Raw Request in Domain Layer

Do not pass the Laravel Request object into DELIVERY domain components.

---

# 131. FormRequest Boundary

Final transport validation belongs to Phase 7.8.

Phase 7.4 may reuse/prep DTOs and domain branch validation.

Avoid duplicating the future final request rules.

---

# 132. Phase 7.5 Boundary

Phase 7.5 owns the delivery-fee rules and ORD-014 semantics.

Phase 7.4 only establishes:

```text
initial fee = null
initial fee status = PENDING
```

---

# 133. Phase 7.6 Boundary

Phase 7.6 owns authoritative subtotal/total calculation helpers.

Phase 7.4 only freezes provisional relationship.

---

# 134. Phase 7.7 Boundary

Phase 7.7 owns:

```text
Cart lock
Order creation
OrderItem inserts
inventory reserve
address snapshot persistence
Cart clear
idempotency commit
```

as one atomic transaction.

---

# 135. Phase 7.8 Boundary

Phase 7.8 owns full CHK-001 validation orchestration and exact API activation logic.

---

# 136. Phase 7.9 Boundary

Phase 7.9 performs full end-to-end Checkout closure tests.

---

# 137. No Checkout Route Activation Required

If CHK-001 is still stubbed:

keep it stubbed unless the roadmap explicitly activates it incrementally.

Do not expose DELIVERY without the complete transaction.

---

# 138. If Partial Checkout Already Exists

Do not let DELIVERY create an Order without reservation/atomicity merely to make the endpoint work early.

Safety takes precedence over premature endpoint activation.

---

# 139. Model Compatibility Review

Verify current Order model can represent:

```text
DELIVERY
delivery address snapshot
billing address snapshot
delivery_fee null
delivery_fee_status PENDING
total=subtotal
PENDING_PAYMENT
```

---

# 140. Schema Expectation

Expected:

```text
Schema changes: NONE
```

because Group C already designed Model B.

---

# 141. Model Gap Rule

If current persistence cannot represent a valid frozen DELIVERY state:

report:

```text
MODEL GAP
```

Do not hack around it.

---

# 142. Dependency Expectation

Expected:

```text
Dependencies: NONE
```

---

# 143. Frontend

Expected:

```text
Frontend: NONE
```

---

# 144. Focused Test — Valid DELIVERY State

Build a valid normalized address.

Assert:

```text
fulfillment_type = DELIVERY
delivery_address present
billing_address copied
delivery_fee null
delivery_fee_status PENDING
```

---

# 145. Test — `city`

Assert:

```text
city
```

survives normalization/snapshot.

---

# 146. Test — No `region`

Assert public branch representation contains no:

```text
region
```

---

# 147. Test — Billing Snapshot Copy

Assert all four values match:

```text
recipient_name
phone
address_line
city
```

---

# 148. Test — Snapshot Independence

If represented as mutable PHP arrays/objects:

ensure later mutation of one representation cannot unexpectedly mutate the historical copy.

Prefer immutable DTO/value object behavior.

---

# 149. Test — Missing Address

DELIVERY without `delivery_address` fails.

---

# 150. Test — Null Address

Fails.

---

# 151. Test — Missing City

Fails.

---

# 152. Test — `region` Instead of City

Fails.

---

# 153. Test — Both Region and City

Fails because region is unknown.

---

# 154. Test — Missing Recipient Name

Fails.

---

# 155. Test — Missing Phone

Fails.

---

# 156. Test — Missing Address Line

Fails.

---

# 157. Test — Empty City

Fails.

---

# 158. Test — Whitespace City

Fails after normalization.

---

# 159. Test — Normalized Address

Example:

```text
" Dar es Salaam "
```

becomes:

```text
"Dar es Salaam"
```

---

# 160. Test — Phone Normalization

Use Phase 7.2 behavior.

Do not create conflicting phone rules.

---

# 161. Test — Fee Null

Initial DELIVERY branch:

```text
delivery_fee = null
```

---

# 162. Test — Fee Status Pending

Initial:

```text
PENDING
```

---

# 163. Test — Provisional Total Requirement

Given:

```text
subtotal = X
```

expected eventual initial:

```text
total = X
```

without marking it final.

---

# 164. Test — Payment Null

Initial:

```text
payment = null
```

---

# 165. Test — Client Fee Injection

Reject.

---

# 166. Test — Client Fee Status Injection

Reject.

---

# 167. Test — Client Total Injection

Reject.

---

# 168. Test — Billing Address Injection

Reject.

---

# 169. Test — Saved Address ID Injection

Reject.

---

# 170. Test — Region Injection

Reject.

---

# 171. Test — Delivery Zone Injection

Reject.

---

# 172. Test — Lowercase Enum

Reject:

```text
delivery
```

---

# 173. Test — Alias Enum

Reject:

```text
SHIPPING
COURIER
```

---

# 174. Test — Model Invariant

Construct/persist equivalent DELIVERY Order state through model-level tests if appropriate.

Ensure `Order::assertValid()` accepts:

```text
PENDING delivery fee
null fee
total=subtotal
```

---

# 175. Test — Invalid DELIVERY Financial State

Model should reject impossible combinations such as:

```text
DELIVERY
delivery_fee_status = PENDING
delivery_fee != null
```

if current invariant defines this as invalid.

---

# 176. Test — Another Invalid State

Reject:

```text
DELIVERY
delivery_fee_status = FINALIZED
delivery_fee = null
```

---

# 177. Test — PENDING Total Drift

Reject/model-detect where applicable:

```text
delivery_fee_status = PENDING
total != subtotal
```

---

# 178. PICKUP Regression

Run Phase 7.3 tests.

DELIVERY implementation must not alter:

```text
PICKUP fee zero
PICKUP fee FINALIZED
PICKUP address null
```

---

# 179. Address Regression

Run Phase 7.2 tests.

Public API must remain:

```text
city
```

---

# 180. Cart Regression

No Group F behavior should change.

Run focused Cart tests if any shared Checkout input/enum code touches them.

---

# 181. Order Schema Regression

Run:

```text
Order schema/model tests
Order financial invariant tests
Order snapshot tests
```

---

# 182. Delivery Schema Regression

Run relevant Delivery model tests if shared enum/value objects are touched.

---

# 183. No MariaDB Checkout Race Yet

Concurrency implementation belongs to Phase 7.7.

Do not write a false partial race test before transactional Checkout exists.

---

# 184. Documentation

Record DELIVERY branch implementation if project ADR practice requires it.

Document only implementation-specific decisions.

Do not redefine the frozen API.

---

# 185. Suggested ADR Content

If needed:

```text
DELIVERY branch uses normalized city-based historical snapshot
billing_address copies delivery_address in V1
delivery_fee=null
delivery_fee_status=PENDING
total=subtotal provisional
payment=null
no Delivery row at CHK-001
```

---

# 186. OpenAPI

Expected:

```text
changes = NONE
```

The frozen DELIVERY contract already exists.

---

# 187. Do Not Touch Abandoned Region Artifact

The previously generated `phase-7.1-checkout-requirements-region.md` is not authoritative.

Do not use it as a source of truth.

Canonical sources remain:

```text
current openapi.yaml
current api-contract.md
current api-resources.md
current decisions.md
current Phase 7.1 city-based requirements
```

---

# 188. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
closed enums
immutable/simple DTOs
no magic strings
no duplicate normalization rules
no duplicate totals logic
no duplicate money types
```

---

# 189. Verification Commands

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

# 190. OpenAPI Parse

Verify current OpenAPI parses successfully.

No field drift.

---

# 191. Completion Report

Return:

## Phase 7.4 status

```text
PASS
```

or:

```text
BLOCKED
```

## DELIVERY branch

Report the component(s) responsible for DELIVERY state.

## Fulfillment

Confirm:

```text
fulfillment_type = DELIVERY
```

## Delivery address

Confirm exact fields:

```text
recipient_name
phone
address_line
city
```

## City contract

Confirm:

```text
city = canonical
region = rejected publicly
```

## Billing address

Confirm:

```text
billing_address = copy of delivery_address
```

for V1.

## Delivery fee

Confirm:

```text
delivery_fee = null
delivery_fee_status = PENDING
```

## Total

Confirm:

```text
total = subtotal
```

and explicitly state:

```text
PROVISIONAL
```

## Payment

Confirm:

```text
payment = null
PAY-001 blocked until fee finalization
```

## Order status

Confirm:

```text
PENDING_PAYMENT
```

## Delivery record

Confirm:

```text
created by CHK-001 = NO
```

## Inventory

Report:

```text
reservation implementation in Phase 7.4 = NONE
ProductStock mutation = NONE
```

unless actual roadmap explicitly requires otherwise.

## Idempotency

Confirm normalized DELIVERY address is suitable for deterministic Checkout fingerprinting.

## Model compatibility

Report Order model/state compatibility.

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

Confirm unchanged frozen contract.

## Tests

Report focused and full results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
OpenAPI parse
```

## Phase 7.5 readiness

Return:

```text
Phase 7.5 — Delivery fee rules: READY
```

or:

```text
BLOCKED
```

---

# 192. Definition of Done

Phase 7.4 is complete when:

- DELIVERY is represented by the frozen enum;
- DELIVERY requires a non-null delivery address;
- all four canonical address fields are required;
- `city` remains canonical;
- `region` remains rejected publicly;
- normalized address is preserved as a historical Order snapshot;
- V1 billing address is a copy of the delivery snapshot;
- no saved-address mechanism is introduced;
- no Delivery row is created by CHK-001;
- delivery fee starts as null;
- delivery fee status starts as PENDING;
- provisional total equals subtotal;
- provisional total is not treated as payable/final;
- Order status remains PENDING_PAYMENT;
- payment remains null;
- PAY-001 remains blocked until fee finalization;
- client cannot supply delivery fee;
- client cannot supply delivery fee status;
- client cannot supply total/subtotal/currency;
- no city-based fee calculation is introduced;
- no flat fee is introduced;
- no zone calculator is introduced;
- no geocoding is introduced;
- no delivery ETA is introduced;
- no inventory reservation is prematurely implemented in the branch;
- no physical stock is consumed;
- normalized DELIVERY intent is deterministic for later idempotency;
- Order model can represent the pending-fee state;
- PICKUP behavior remains unchanged;
- no schema migration is required unless a genuine model gap is found;
- no dependency is added;
- no frontend change occurs;
- OpenAPI remains unchanged and valid;
- full regression suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit remains clean.

---

# 193. Out of Scope

Do not implement:

```text
Phase 7.5 ORD-014 / delivery fee finalization
Phase 7.6 totals engine
Phase 7.7 transaction boundaries
Phase 7.8 full Checkout validation
Phase 7.9 full Checkout tests
inventory reservation orchestration
payment
order cancellation
reservation release
stock consumption
delivery operational transitions
shipping carrier
tracking
ETA
delivery zones
frontend
```

---

# 194. STOP Condition

STOP when the backend can represent the DELIVERY branch unambiguously as:

```text
DELIVERY
→ normalized {recipient_name, phone, address_line, city}
→ historical delivery snapshot
→ billing snapshot copy
→ delivery_fee null
→ delivery_fee_status PENDING
→ total = subtotal (provisional)
→ Order PENDING_PAYMENT
→ payment null
→ no Delivery row yet
```

without activating an incomplete Checkout transaction.

Do not continue automatically to Phase 7.5.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.