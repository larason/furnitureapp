# Phase 7.8 — Checkout Validation

## 1. Objective

Implement the complete validation and HTTP/application boundary for:

```http
POST /api/v1/checkout
```

Endpoint ID:

```text
CHK-001
```

Phase 7.8 owns:

```text
authentication attachment
CUSTOMER-only authorization
Idempotency-Key validation
JSON/content-type validation
strict request allow-list
fulfillment_type validation
conditional delivery_address validation
address normalization
CheckoutCommand construction
canonical API error mapping
Checkout controller orchestration
Checkout response serialization
private/no-store response behavior
validation regression tests
route activation readiness assessment
```

Phase 7.8 must **not** reimplement any of the transaction, inventory, pricing, totals, or persistence behavior already owned by earlier phases.

---

# 2. Current Group G Baseline

Assume:

```text
7.1 PASS — Checkout requirements
7.2 PASS — Address model review
7.3 PASS — Pickup flow
7.4 BLOCKED — DELIVERY billing snapshot persistence
7.5 PASS — Delivery fee rules / ORD-014
7.6 PASS — Canonical Order totals
7.7 PASS — Checkout transaction boundary
7.8 CURRENT — Checkout validation
```

Group G remains open.

The Phase 7.4 blocker is still:

```text
The frozen V1 DELIVERY Checkout requires a historical billing_address
snapshot copied from delivery_address.

The current persistence model has no approved place to store that snapshot.
```

Do not resolve or bypass that problem inside Phase 7.8.

---

# 3. Important Phase 7.7 Baseline

Reuse the existing transaction implementation.

The transaction owner is:

```php
App\Services\Checkout\CheckoutTransaction::execute(CheckoutCommand $command)
```

It already owns:

```text
idempotency transaction
Cart locking
Cart-line locking
current Product/Variant validation
current authoritative pricing
OrderTotalsCalculator
PICKUP fulfillment persistence
Order creation
OrderItem creation
InventoryAllocator::reserve()
exact inventory allocations
initial Order status history
Cart clearing
successful 201 idempotency outcome
rollback
```

Phase 7.8 must call this boundary.

Do not duplicate its behavior.

---

# 4. Phase Boundary

The desired layering is:

```text
HTTP request
    ↓
security middleware
    ↓
Clerk authentication
    ↓
active-account enforcement
    ↓
CUSTOMER authorization
    ↓
CheckoutRequest
    ↓
normalized CheckoutCommand
    ↓
CheckoutTransaction
    ↓
Checkout response mapper/resource
```

Do not let transport-layer objects leak into the domain transaction service.

---

# 5. Public Endpoint Contract

The frozen endpoint remains:

```http
POST /api/v1/checkout
```

The contract supports exactly:

```text
PICKUP
DELIVERY
```

Do not create:

```text
POST /checkout/pickup
POST /checkout/delivery
POST /orders
POST /checkout/validate
```

---

# 6. Route Activation Constraint

This is critical.

Phase 7.4 still blocks complete DELIVERY persistence.

Therefore Phase 7.8 must **not activate CHK-001 as a PICKUP-only public endpoint** unless the project owner separately approves a formal post-freeze API change.

The frozen endpoint promises both:

```text
PICKUP
DELIVERY
```

It would be contract drift to expose:

```text
PICKUP → works
DELIVERY → implementation-only unsupported error
```

Do not invent:

```text
DELIVERY_NOT_IMPLEMENTED
DELIVERY_UNSUPPORTED
CHECKOUT_DELIVERY_UNAVAILABLE
```

Do not return `501` only for DELIVERY.

Preferred Phase 7.8 state:

```text
validation/controller components = COMPLETE
public CHK-001 route = remains stub/gated
```

until the Phase 7.4 persistence model gap is resolved.

---

# 7. Authentication

CHK-001 requires authenticated Clerk identity.

Use the existing protected middleware.

Unauthenticated:

```text
401 AUTHENTICATION_REQUIRED
```

A guest Cart credential does not authenticate Checkout.

These are not sufficient:

```text
X-Guest-Cart-Id
guest Cart cookie
```

A guest must first authenticate and complete the already-supported guest→Customer Cart merge flow.

---

# 8. Active-Account Enforcement

Preserve the hardened security baseline.

A suspended/inactive Customer must be rejected before reaching Checkout validation/business execution.

Do not add a second account-state rule inside CheckoutController.

Reuse centralized account enforcement.

---

# 9. Authorization

CHK-001 is:

```text
CUSTOMER only
```

Allowed:

```text
active CUSTOMER
```

Denied:

```text
STAFF
ADMIN
```

Expected authenticated-but-wrong-role response:

```text
403 FORBIDDEN
```

Do not reintroduce Staff/Admin personal Cart Checkout.

The current security boundary is Customer-only.

---

# 10. Validation Order

Preserve the established ordering.

Conceptually:

```text
1. pre-auth security controls
2. authentication
3. active-account check
4. CUSTOMER authorization
5. Idempotency-Key validation
6. JSON / request-shape validation
7. request normalization
8. CheckoutCommand construction
9. CheckoutTransaction
10. response serialization
```

Do not expose detailed Checkout validation to anonymous users before authentication.

---

# 11. Request Content Type

Checkout is a JSON mutation.

Require:

```http
Content-Type: application/json
```

Reuse the project's existing JSON-body validation middleware.

Do not allow:

```text
application/x-www-form-urlencoded
multipart/form-data
text/plain
```

for CHK-001.

---

# 12. Body-Size Protection

Reuse the hardened repository-level request-size enforcement.

Do not add custom raw-body parsing in CheckoutRequest.

The production ingress still has its broader hard ceiling.

Checkout itself should remain a very small JSON request.

---

# 13. Dedicated Request Object

Create or complete:

```php
App\Http\Requests\CheckoutRequest
```

or the repository-equivalent name.

The request object owns:

```text
shape validation
strict field allow-list
type validation
closed enum validation
conditional branch validation
field normalization support
```

It must not own:

```text
Cart lookup
Product lookup
Variant lookup
inventory lookup
pricing
Order creation
reservation
```

---

# 14. Never Use `$request->all()`

Use:

```php
$request->validated()
```

Never:

```php
$request->all()
```

The transaction command must be built only from validated normalized input plus authenticated server context.

---

# 15. Top-Level Request Allow-List

Only permit:

```text
fulfillment_type
delivery_address
```

No other fields.

Unknown top-level fields must fail validation rather than be silently discarded.

---

# 16. Explicit Server-Controlled Field Rejection

Test and reject attempts to submit:

```text
cart_id
user_id
customer_id
guest_cart_id
guest_token

product_id
variant_id

subtotal
total
unit_price
line_total
currency

delivery_fee
delivery_fee_status

status
payment_status
payment

order_reference
order_id

billing_address
saved_address_id

created_at
updated_at
```

These are server-controlled.

---

# 17. `fulfillment_type`

Required.

Must be a JSON string.

Allowed values:

```text
PICKUP
DELIVERY
```

Closed enum.

Reject:

```text
pickup
delivery
Pickup
Delivery
SHIPPING
COURIER
EXPRESS
SELF_PICKUP
```

---

# 18. Missing Fulfillment Type

Example:

```json
{}
```

Expected:

```text
422 MISSING_REQUIRED_FIELD
field: fulfillment_type
```

Use the project's standard error envelope.

---

# 19. Wrong Fulfillment Type

Example:

```json
{
  "fulfillment_type": 12
}
```

Expected:

```text
422 INVALID_TYPE
field: fulfillment_type
```

---

# 20. Unknown Fulfillment Value

Example:

```json
{
  "fulfillment_type": "SHIPPING"
}
```

Expected:

```text
422 INVALID_VALUE
field: fulfillment_type
```

---

# 21. PICKUP Request

Canonical minimal request:

```json
{
  "fulfillment_type": "PICKUP"
}
```

Also valid:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": null
}
```

Normalize these into the same logical Checkout intent.

---

# 22. PICKUP Address Rule

For PICKUP:

```text
delivery_address
```

must be:

```text
absent
or null
```

A populated object is invalid.

---

# 23. PICKUP With Populated Address

Example:

```json
{
  "fulfillment_type": "PICKUP",
  "delivery_address": {
    "recipient_name": "Asha Mwangi",
    "phone": "+255700000001",
    "address_line": "Street 12",
    "city": "Dar es Salaam"
  }
}
```

Expected:

```text
422 INVALID_FULFILLMENT
```

Do not silently discard the supplied address.

---

# 24. PICKUP Idempotency Normalization

These must fingerprint identically:

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

---

# 25. DELIVERY Request

Canonical request:

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

---

# 26. DELIVERY Address Required

For:

```text
fulfillment_type = DELIVERY
```

`delivery_address` is required.

It cannot be:

```text
missing
null
```

---

# 27. DELIVERY Address Type

Must be a JSON object.

Reject:

```text
string
integer
boolean
array/list
```

using the canonical validation code.

---

# 28. Delivery Address Allow-List

Exact public fields:

```text
recipient_name
phone
address_line
city
```

Nothing else.

---

# 29. Canonical City Field

The frozen V1 API uses:

```text
city
```

Never publicly accept:

```text
region
```

---

# 30. Reject Region Alias

This is invalid:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Street 1",
    "region": "Dar es Salaam"
  }
}
```

Do not treat `region` as an alias.

---

# 31. Reject City + Region

This is also invalid:

```json
{
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Street",
    "city": "Dar es Salaam",
    "region": "Dar es Salaam"
  }
}
```

because `region` is an unknown nested property.

---

# 32. Unknown Nested Address Fields

Reject:

```text
region
country
postal_code
district
ward
latitude
longitude
instructions
landmark
saved_address_id
```

unless a field is explicitly in the frozen V1 request contract.

---

# 33. `recipient_name`

Required for DELIVERY.

Must be:

```text
string
trimmed
non-empty
max 255
```

Reject:

```text
missing
null
integer
array
boolean
empty string
whitespace-only
```

---

# 34. `phone`

Required.

Must be:

```text
string
trimmed
normalized
non-empty
max 30
```

Reuse the existing Phase 7.2 phone normalization.

Do not introduce a second parser.

---

# 35. No Phone Fallback

If `phone` is absent, do not populate it from:

```text
CustomerProfile
Clerk
previous Order
billing data
```

The Checkout address snapshot must come from the explicit DELIVERY request.

---

# 36. Invalid Phone Mapping

Differentiate:

```text
wrong JSON type
```

from:

```text
string-shaped but invalid after normalization
```

Wrong JSON type:

```text
INVALID_TYPE
```

Domain-invalid normalized phone:

```text
INVALID_DELIVERY_INFORMATION
```

where this matches the frozen error contract.

---

# 37. `address_line`

Required.

Must be:

```text
string
trimmed
non-empty
```

Do not invent a new arbitrary V1 maximum.

Use only an already-approved persistence-bound technical limit if one exists.

---

# 38. `city`

Required.

Must be:

```text
string
trimmed
non-empty
```

Do not introduce a city enum.

---

# 39. No City Whitelist

Do not restrict valid requests to:

```text
Dar es Salaam
Dodoma
Arusha
Mwanza
```

or any other fixed list.

Location-based fee decisions remain Staff/Admin operational logic.

---

# 40. No Address Verification

Do not add:

```text
Google Maps
geocoding
postal verification
distance lookup
zone validation
GPS
```

---

# 41. No Delivery-Fee Calculation

Checkout validation does not calculate the delivery fee.

DELIVERY still enters the financial model as:

```text
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
```

when persistence is eventually enabled.

---

# 42. Billing Address

The client must not submit:

```text
billing_address
```

V1 derives the billing snapshot from the normalized DELIVERY address.

For PICKUP:

```text
billing_address = null
```

For DELIVERY:

```text
billing_address = copy(normalized delivery_address)
```

This remains a domain projection until Phase 7.4 persistence is resolved.

---

# 43. Normalization

Normalize before building CheckoutCommand.

At minimum:

```text
trim recipient_name
trim address_line
trim city
normalize phone according to existing Phase 7.2 behavior
```

Do not mutate semantic content beyond approved normalization.

---

# 44. Normalization and Idempotency

The idempotency fingerprint must use normalized values.

Example:

```text
" Dar es Salaam "
```

and:

```text
"Dar es Salaam"
```

must represent the same logical intent after normalization.

---

# 45. JSON Property Order

Input property order must not change the fingerprint.

These are equivalent:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Street",
    "city": "Dar es Salaam"
  }
}
```

and the same fields in another JSON property order.

---

# 46. Idempotency-Key

Required header:

```http
Idempotency-Key: <uuid>
```

Reuse the current:

```php
IdempotencyKeyHeader
```

or existing canonical parser.

---

# 47. Missing Idempotency-Key

Expected:

```text
422 MISSING_REQUIRED_FIELD
field: Idempotency-Key
```

according to the repository's frozen representation.

---

# 48. Malformed Idempotency-Key

Expected:

```text
422 INVALID_FORMAT
field: Idempotency-Key
```

---

# 49. Do Not Generate a Key

Never generate one server-side when missing.

Safe retries require the client to retain the same key.

---

# 50. CheckoutCommand

Build an immutable normalized command.

Reuse the existing:

```php
App\Services\Checkout\CheckoutCommand
```

or current location.

It should contain only server-trusted fields.

Conceptually:

```text
authenticated Customer identity
idempotency key
fulfillment type
normalized delivery address or null
```

---

# 51. CheckoutCommand Must Not Contain

Do not include client-controlled:

```text
cart id
customer id override
subtotal
total
delivery fee
currency
stock values
payment status
Order status
```

---

# 52. Customer Identity

Customer identity is derived from authenticated context.

Never request body.

---

# 53. Raw Request Boundary

`CheckoutTransaction` must not receive:

```php
Illuminate\Http\Request
```

Controller responsibility:

```text
HTTP Request
→ CheckoutRequest
→ normalized DTO/command
→ CheckoutTransaction
```

---

# 54. Thin Controller

The Checkout controller should do approximately:

```text
resolve authenticated Customer
obtain validated request
normalize fulfillment data
resolve Idempotency-Key
construct CheckoutCommand
execute CheckoutTransaction
serialize Checkout outcome
return appropriate response
```

It must not perform:

```text
Product validation
Variant validation
money arithmetic
Order insert
reservation
Cart clear
```

---

# 55. Controller Error Handling

Do not fill CheckoutController with repeated:

```php
try {
   ...
} catch (...) {
   ...
}
```

for domain errors if the existing API exception renderer already owns mapping.

Prefer domain/application exceptions mapped centrally.

---

# 56. Canonical Error Envelope

Use the existing standard:

```json
{
  "errors": [
    {
      "code": "...",
      "message": "...",
      "field": "..."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

according to current API conventions.

Do not return Laravel's default validator JSON.

---

# 57. Error Code Registry

Reuse the CLOSED `ApiErrorCode`.

Do not invent new codes.

`CART_INVALID` is already available.

---

# 58. Structural Error Mapping

Use the established categories:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
```

---

# 59. Branch Error Mapping

Use:

```text
INVALID_FULFILLMENT
```

for valid enum + invalid branch combination.

Example:

```text
PICKUP + populated delivery_address
```

---

# 60. Delivery-Domain Error Mapping

Use:

```text
INVALID_DELIVERY_INFORMATION
```

for delivery input that passes basic JSON shape but fails established normalized domain requirements.

---

# 61. Cart Errors

From Phase 7.7:

```text
missing active Cart
empty active Cart
```

map to:

```text
422 CART_INVALID
```

for a new execution.

---

# 62. Product/Variant Errors

Preserve established domain mappings, including as applicable:

```text
PRODUCT_NOT_PURCHASABLE
PRODUCT_UNAVAILABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK
```

Do not collapse them into generic validation errors.

---

# 63. MADE_TO_ORDER

Checkout of a MADE_TO_ORDER Product remains:

```text
422 PRODUCT_NOT_PURCHASABLE
```

---

# 64. Idempotency Conflict

Same Customer + same key + changed normalized intent:

```text
409 DUPLICATE_OPERATION
```

---

# 65. Transaction Conflict

Exhausted transient concurrency retries:

```text
409 CONFLICT
```

using the current safe mapping.

---

# 66. Rate Limit

Checkout must remain behind the dedicated limiter introduced during security remediation.

Expected:

```text
429
Retry-After
```

---

# 67. Security Middleware

Do not weaken or bypass:

```text
pre-auth IP throttling
Authorization header size bounds
body-size protection
active-account enforcement
CUSTOMER role enforcement
production HTTPS controls
safe logging
```

---

# 68. Middleware Ordering

Inspect the route after implementation.

Ensure the effective ordering preserves the existing security architecture.

Do not casually reorder security middleware just to make tests easier.

---

# 69. JSON-Only Enforcement

Confirm CHK-001 receives the repository's JSON-only mutation behavior.

Do not rely solely on FormRequest rules after decoding form data.

---

# 70. CORS

Do not redesign CORS.

Use the existing hardened production configuration.

---

# 71. CSRF

CHK-001 uses Clerk bearer authentication.

Do not attach guest-cart cookie mutation CSRF/origin middleware unless architecture explicitly requires it.

---

# 72. Logging

Do not log:

```text
Authorization header
raw Clerk token
full Checkout request body
full delivery address
guest Cart credentials
```

Use existing safe request ID and allow-listed context.

---

# 73. Checkout Success Serializer

Prepare/use an explicit Checkout response serializer/resource.

Do not return raw:

```php
Order::toArray()
```

---

# 74. Frozen PICKUP Response

Eventually:

```json
{
  "data": {
    "order_id": "ord_...",
    "order_reference": "OD-12345",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "PICKUP",
    "delivery_address": null,
    "subtotal": {
      "amount": 170000000,
      "currency": "TZS"
    },
    "delivery_fee": {
      "amount": 0,
      "currency": "TZS"
    },
    "delivery_fee_status": "FINALIZED",
    "total": {
      "amount": 170000000,
      "currency": "TZS"
    },
    "currency": "TZS",
    "payment": null
  }
}
```

Use the exact current frozen field names.

---

# 75. Frozen DELIVERY Response

When persistence is eventually unblocked:

```text
status = PENDING_PAYMENT
fulfillment_type = DELIVERY
delivery_address = normalized historical snapshot
billing snapshot persisted internally
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
currency = TZS
payment = null
```

Do not alter this response now.

---

# 76. No Raw Internal IDs

Never expose:

```text
orders.id
customer_id
cart numeric id
ProductStock id
reservation allocation id
guest_token_digest
```

---

# 77. Order Identifier

Public identifier:

```text
ord_...
```

---

# 78. Order Reference

Public reference:

```text
OD-*****
```

---

# 79. Money Serialization

Use:

```json
{
  "amount": 123,
  "currency": "TZS"
}
```

No floats.

---

# 80. Payment

Checkout response remains:

```text
payment = null
```

No Payment record is created.

---

# 81. Cache-Control

Protected Checkout results/errors that contain private Order data must use:

```text
Cache-Control: private, no-store
```

Use the existing protected-response middleware/convention.

---

# 82. `Vary`

Preserve the current protected API response behavior where applicable:

```text
Vary: Authorization
```

---

# 83. Public Route Activation Decision

At the end of this phase, inspect whether Phase 7.4 has been resolved.

If not:

```text
DO NOT expose CHK-001 as a one-branch-only public endpoint.
```

Recommended:

```text
route remains globally stubbed/gated
```

while the validation stack is fully implemented/tested internally.

---

# 84. Do Not Remove DELIVERY From OpenAPI

The contract remains frozen.

Phase 7.4 is an implementation gap, not a contract removal.

---

# 85. Do Not Add a Temporary Feature Flag to Contract

Avoid externally visible states such as:

```text
delivery_checkout_enabled=false
```

unless separately approved.

---

# 86. DELIVERY Internal Validation

A valid DELIVERY request should be fully normalized successfully.

Then, if passed to the current Phase 7.7 transaction implementation, it may reach:

```text
DeliveryCheckoutUnsupportedException
```

before mutation.

This remains an internal blocker.

---

# 87. Invalid DELIVERY Must Not Reach the Blocker

Example:

```text
DELIVERY missing city
```

must fail normal validation.

It must not simply fail as:

```text
DeliveryCheckoutUnsupportedException
```

---

# 88. No Public Mapping for the Internal Blocker

Do not invent a client-facing error for `DeliveryCheckoutUnsupportedException`.

Until persistence is resolved, keep the route gated.

---

# 89. No Mutation on Blocked DELIVERY

Internal test must prove:

```text
valid DELIVERY
→ blocker
→ no Order
→ no OrderItems
→ no reservation
→ no allocations
→ no history
→ Cart unchanged
→ no idempotency success
```

---

# 90. Preserve Phase 7.7 Variant Snapshot Fix

Historical `variant_name` may be:

```text
null
```

Do not coerce it to:

```text
""
```

Phase 7.8 serialization must preserve correct nullable semantics if the field appears in downstream Order resources.

---

# 91. Preserve Last-Unit Semantics

Do not regress:

```text
quantity = 1
reserved_quantity = 1
available_quantity = 0
```

Validation must not interpret physical quantity alone as available stock.

---

# 92. No Pre-Transaction Stock Authority

Phase 7.8 must not perform an authoritative stock decision before `CheckoutTransaction`.

Final stock authority remains inside the Phase 7.7 transaction.

---

# 93. Request Validation vs Domain Validation

Keep separation:

```text
CheckoutRequest
→ input shape

CheckoutTransaction
→ Cart/Product/Variant/stock business authority
```

Do not put Product/stock queries into FormRequest rules.

---

# 94. No Cart Query in FormRequest

Avoid:

```php
Cart::where(...)->exists()
```

inside CheckoutRequest.

---

# 95. No Inventory Query in FormRequest

Same.

---

# 96. No Pricing Query in FormRequest

Same.

---

# 97. No Total Calculation in Controller

Phase 7.6 owns totals.

---

# 98. No Inventory Reservation in Controller

Phase 7.7 owns reservation.

---

# 99. No Order Persistence in Controller

Phase 7.7 owns persistence.

---

# 100. No Cart Clearing in Controller

Phase 7.7 owns Cart clearing.

---

# 101. Idempotent Replay

Controller must preserve the `IdempotentOutcome` returned from Phase 7.7.

New Checkout:

```text
201
```

Same-key replay:

```text
201
```

---

# 102. Replay After Cart Clear

Explicitly test:

```text
first request
→ 201
→ Cart becomes empty

same key + same request
→ same original 201
```

Do not validate the now-empty Cart before replay resolution.

---

# 103. Same-Key Changed PICKUP/DELIVERY

Example:

First:

```json
{
  "fulfillment_type": "PICKUP"
}
```

retry same key:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": { ... }
}
```

Expected:

```text
409 DUPLICATE_OPERATION
```

---

# 104. Same-Key Address Whitespace

Equivalent normalized DELIVERY address should not create an idempotency conflict.

---

# 105. Same-Key Address Change

A genuinely changed normalized address should conflict.

---

# 106. Different Customer Same Key

Different identity scope.

No cross-customer replay.

---

# 107. Test: Authentication

Add cases for:

```text
no bearer token → 401
invalid bearer token → 401
active CUSTOMER → allowed through auth boundary
suspended CUSTOMER → rejected
STAFF → 403
ADMIN → 403
```

---

# 108. Test: Idempotency Header

Cases:

```text
missing
empty
malformed
valid UUID
```

---

# 109. Test: Top-Level Unknown Fields

Use table-driven tests.

At minimum:

```text
cart_id
user_id
customer_id
subtotal
total
delivery_fee
delivery_fee_status
currency
status
payment
billing_address
saved_address_id
```

---

# 110. Test: Fulfillment Type

Cases:

```text
missing
null
integer
boolean
PICKUP
DELIVERY
pickup
delivery
SHIPPING
```

---

# 111. Test: PICKUP Address

Cases:

```text
absent → valid
null → valid
{} → invalid
populated object → INVALID_FULFILLMENT
string → invalid
```

---

# 112. Test: DELIVERY Address Container

Cases:

```text
missing
null
string
integer
list
object
```

---

# 113. Test: Delivery Required Fields

Each missing independently:

```text
recipient_name
phone
address_line
city
```

---

# 114. Test: Delivery Wrong Types

Each field:

```text
integer
boolean
array
object
null
```

as appropriate.

---

# 115. Test: Delivery Empty Strings

For each string field:

```text
""
"   "
```

must fail.

---

# 116. Test: Recipient Name Length

Exactly the existing approved boundary.

Do not invent another limit.

---

# 117. Test: Phone

Cover:

```text
normalization
valid value
whitespace trimming
invalid normalized value
too long
wrong type
missing
```

---

# 118. Test: City

Cover:

```text
normal value
trimmed
empty
wrong type
region substitution rejected
```

---

# 119. Test: Address Line

Cover:

```text
normal
trimmed
empty
wrong type
```

---

# 120. Test: Unknown Nested Fields

Use table-driven cases:

```text
region
country
postal_code
district
ward
latitude
longitude
instructions
saved_address_id
```

---

# 121. Test: No Profile Fallback

Customer profile may contain:

```text
name
phone
```

but DELIVERY request omits them.

Expected:

```text
validation failure
```

not fallback.

---

# 122. Test: No Billing Address Input

Explicitly reject it.

---

# 123. Test: Valid PICKUP Command

Assert normalized `CheckoutCommand` contains:

```text
fulfillment_type = PICKUP
delivery address = null
authenticated Customer
validated Idempotency-Key
```

---

# 124. Test: Valid DELIVERY Command

Assert normalized command contains exact:

```text
recipient_name
phone
address_line
city
```

and no `region`.

---

# 125. Test: PICKUP Absent vs Null

Commands/fingerprints should match.

---

# 126. Test: Property Ordering

Equivalent DELIVERY objects with different JSON field order produce equivalent normalized intent.

---

# 127. Test: Structural Failure Has No Side Effects

For invalid request:

```text
no Order
no OrderItem
no inventory reservation
no allocation
no history
Cart unchanged
no idempotency success
```

---

# 128. Test: Authorization Failure Has No Side Effects

Same.

---

# 129. Test: Invalid Idempotency-Key Has No Side Effects

Same.

---

# 130. Test: Valid PICKUP Internal Execution

Where controller/service integration allows:

```text
valid validated request
→ CheckoutCommand
→ CheckoutTransaction
→ 201 IdempotentOutcome
```

---

# 131. Test: Checkout Errors Through Controller

Use domain fixtures to prove mappings for:

```text
CART_INVALID
PRODUCT_NOT_PURCHASABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK
DUPLICATE_OPERATION
CONFLICT
```

Do not recreate domain conditions inside request validation.

---

# 132. Test: Private Cache Headers

Check success/error behavior as appropriate.

---

# 133. Test: Request ID

Confirm canonical request ID survives into error/success metadata where the contract requires it.

---

# 134. Test: Rate Limiter Attachment

Verify the Checkout route is attached to the correct dedicated limiter.

Do not merely assume global throttling covers it.

---

# 135. Test: Security Logging

A validation failure containing address data must not dump the full body into logs.

---

# 136. Test: JSON-Only

Attempt form-urlencoded Checkout.

Expected:

```text
rejected
```

through existing mutation transport rules.

---

# 137. Test: Oversized Request

Do not reproduce the global 6 MiB test unnecessarily if already proven centrally.

A route/middleware attachment regression is sufficient unless Checkout adds a custom limit.

---

# 138. Controller Resource Test

Assert the Checkout response uses only frozen public fields.

No internal DB identifiers.

---

# 139. Idempotent Response Stability

Replay should not re-read current Product prices to rebuild the response.

Use the stored successful Checkout outcome.

---

# 140. Existing Phase 7.3 Regression

Run:

```text
PickupFulfillmentStateTest
```

---

# 141. Existing Phase 7.4 Regression

Run:

```text
DeliveryFulfillmentStateTest
```

The test should continue to prove:

```text
city canonical
billing snapshot copied
persistence gap remains
```

---

# 142. Existing Phase 7.5 Regression

Run:

```text
DeliveryFeeApiTest
```

Validation work must not affect ORD-014.

---

# 143. Existing Phase 7.6 Regression

Run:

```text
OrderTotalsCalculatorTest
OrderTotalsCompatibilityTest
```

---

# 144. Existing Phase 7.7 Regression

Run:

```text
CheckoutTransactionTest
```

---

# 145. Cart Regressions

Run critical:

```text
CartGetApiTest
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
CartMergeApiTest
```

or current names.

---

# 146. Security Regressions

Run relevant:

```text
auth middleware
active-account tests
Customer-only Cart tests
JSON mutation protection
request-size/security tests
rate-limit tests
```

---

# 147. Idempotency Regressions

Run:

```text
InventoryAdjustmentApiTest
CartMergeApiTest
DeliveryFeeApiTest
CheckoutTransactionTest
```

---

# 148. MariaDB Concurrency Qualification

Do not falsely state Phase 7.7's MariaDB concurrency proof exists if the suite has still not been executed.

Current status from the previous phase:

```text
CheckoutTransactionConcurrencyMysqlTest
= implemented
= not yet run in the reported environment
```

Phase 7.8 adds no new row-lock mechanism.

---

# 149. Preserve Correct Last-Unit Assertion

The MariaDB test must continue to expect:

```text
quantity = 1
reserved_quantity = 1
available_quantity = 0
```

---

# 150. If MariaDB Becomes Available

Run the existing Phase 7.7 concurrency suite.

Report separately from Phase 7.8 validation success.

---

# 151. SQLite vs MariaDB

Document:

```text
SQLite
→ validation / HTTP / rollback semantics

MariaDB
→ row-lock concurrency proof
```

Do not claim SQLite proves lock behavior.

---

# 152. No Schema Change

Expected:

```text
Schema: NONE
```

Do not add `billing_address` here.

---

# 153. No Dependency Change

Expected:

```text
Dependencies: NONE
```

---

# 154. No Frontend Work

Expected:

```text
Frontend: NONE
```

---

# 155. OpenAPI

Expected:

```text
UNCHANGED
```

because the phase implements already-frozen behavior.

If you discover actual OpenAPI drift:

do not silently fix behavior.

Classify the drift and follow the post-freeze change process if externally observable.

---

# 156. Documentation

Add the next available backend ADR.

Likely:

```text
ADR/BACKEND-041 — Checkout Validation Boundary
```

but inspect the current highest ADR number before using it.

---

# 157. ADR Content

Document:

```text
CHK-001 authentication boundary
CUSTOMER-only authorization
strict request allow-list
Idempotency-Key requirement
PICKUP validation
DELIVERY validation
city canonical
address normalization
server-controlled field rejection
CheckoutCommand mapping
canonical error mapping
controller/resource composition
route activation decision
Phase 7.4 blocker preservation
```

---

# 158. Group G Phase Documentation

After Phase 7.8 passes:

```text
7.1 PASS
7.2 PASS
7.3 PASS
7.4 BLOCKED — billing snapshot persistence
7.5 PASS
7.6 PASS
7.7 PASS
7.8 PASS — Checkout validation boundary
```

Group G remains open.

---

# 159. Route Status Documentation

Report exactly whether:

```text
POST /api/v1/checkout
```

is:

```text
ACTIVE
```

or:

```text
STUB / GATED
```

Recommended while 7.4 remains blocked:

```text
STUB / GATED
```

Do not obscure this in the completion report.

---

# 160. Phase 7.9 Readiness

If validation work passes while the persistence gap remains:

report:

```text
Phase 7.9 — READY WITH DELIVERY PERSISTENCE BLOCKER
```

Phase 7.9 may consolidate tests and closure readiness, but it cannot honestly close Group G while 7.4 remains unresolved.

---

# 161. Full Verification

Run:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Also validate that:

```text
docs/api/openapi.yaml
```

still parses successfully.

---

# 162. Completion Report

Return the following.

## Phase 7.8 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Request validation component

Report exact class/path.

Example:

```text
App\Http\Requests\CheckoutRequest
```

---

## Actor boundary

Report:

```text
anonymous → 401
active CUSTOMER → allowed
STAFF → 403
ADMIN → 403
suspended/inactive CUSTOMER → rejected
```

---

## Request allow-list

Report exactly:

```text
fulfillment_type
delivery_address
```

---

## Fulfillment enum

Report:

```text
PICKUP
DELIVERY
```

closed.

---

## PICKUP validation

Report:

```text
delivery_address absent → valid
delivery_address null → valid
delivery_address object → INVALID_FULFILLMENT
```

---

## DELIVERY validation

Report exact required fields:

```text
recipient_name
phone
address_line
city
```

---

## City / Region

State explicitly:

```text
city = canonical
region = rejected
no alias
```

---

## Normalization

Report exact behavior for:

```text
recipient_name
phone
address_line
city
```

---

## Idempotency-Key

Report:

```text
required
UUID
shared parser reused
normalized request becomes fingerprint input
```

---

## Server-controlled field rejection

List the major rejected classes:

```text
identity
Cart selector
money
delivery fee
Order status/reference
payment
billing address
saved address
```

---

## CheckoutCommand

Report exact class and fields.

---

## Controller

Report exact orchestration.

---

## Error mapping

Report tested behavior for:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
INVALID_FULFILLMENT
INVALID_DELIVERY_INFORMATION
CART_INVALID
PRODUCT_NOT_PURCHASABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK
DUPLICATE_OPERATION
CONFLICT
```

---

## Response serializer

Report exact resource/data mapper.

---

## Success status

Report:

```text
new success = 201
same-key replay = 201
```

---

## Route status

State one:

```text
ACTIVE
```

or:

```text
STUB/GATED
```

If Phase 7.4 is still unresolved, expected:

```text
STUB/GATED
```

---

## DELIVERY persistence

Must state:

```text
NOT IMPLEMENTED
Phase 7.4 remains BLOCKED
```

---

## Billing snapshot persistence

Must state:

```text
NOT RESOLVED IN PHASE 7.8
```

---

## Transaction

Confirm:

```text
CheckoutTransaction reused
no duplicated transaction logic
```

---

## Inventory

State:

```text
validation-layer inventory mutation = NONE
```

---

## Payment

State:

```text
Payment rows = NONE
provider calls = NONE
```

---

## Schema

Expected:

```text
NONE
```

---

## Dependencies

Expected:

```text
NONE
```

---

## Frontend

Expected:

```text
NONE
```

---

## OpenAPI

Expected:

```text
UNCHANGED
```

---

## Tests

Report:

```text
Checkout request validation
actor/auth tests
Idempotency-Key tests
PICKUP branch tests
DELIVERY branch tests
tampering tests
normalization tests
error mapping tests
controller/command tests
transaction regressions
Cart regressions
security regressions
```

---

## MariaDB

Report accurately:

```text
CheckoutTransactionConcurrencyMysqlTest
executed / not executed
```

Do not claim row-lock proof if skipped.

---

## Quality

Report:

```text
PHPUnit
assertions
skipped tests
PHPStan
Pint
composer audit
git diff --check
route:list
OpenAPI parse
```

---

## Group G status

Return:

```text
7.1 PASS
7.2 PASS
7.3 PASS
7.4 BLOCKED — billing snapshot persistence
7.5 PASS
7.6 PASS
7.7 PASS
7.8 PASS/BLOCKED
```

---

## Phase 7.9 readiness

Return:

```text
Phase 7.9 — READY WITH DELIVERY PERSISTENCE BLOCKER
```

or:

```text
Phase 7.9 — BLOCKED
```

with the exact reason.

---

# 163. Definition of Done

Phase 7.8 is complete when:

- CHK-001 has a dedicated validation boundary;
- authentication occurs before Checkout details are exposed;
- suspended/inactive users are rejected;
- only CUSTOMER may Checkout;
- Staff/Admin are denied;
- Idempotency-Key is mandatory;
- malformed Idempotency-Key is rejected;
- request is JSON-only;
- only `fulfillment_type` and conditional `delivery_address` are accepted;
- unknown top-level fields are rejected;
- server-controlled financial fields are rejected;
- server-controlled identity fields are rejected;
- Cart selectors are rejected;
- payment/status/reference fields are rejected;
- `billing_address` input is rejected;
- `saved_address_id` is rejected;
- fulfillment enum is exactly PICKUP/DELIVERY;
- lowercase/aliases are rejected;
- PICKUP accepts absent/null delivery address;
- PICKUP rejects populated delivery address;
- DELIVERY requires a delivery address;
- DELIVERY address is an object;
- DELIVERY address accepts exactly recipient_name/phone/address_line/city;
- `city` is canonical;
- `region` is rejected;
- unknown nested fields are rejected;
- recipient name is trimmed/non-empty/max 255;
- phone uses the existing Phase 7.2 normalization;
- address line is trimmed/non-empty;
- city is trimmed/non-empty;
- profile fallback is not used;
- normalized input feeds the idempotency fingerprint;
- JSON property order does not alter logical intent;
- PICKUP absent/null normalize identically;
- CheckoutCommand contains only trusted normalized inputs;
- raw Request does not enter CheckoutTransaction;
- controller remains thin;
- Phase 7.7 transaction code is reused;
- Phase 7.6 totals code is not duplicated;
- Phase 7.7 inventory logic is not duplicated;
- successful PICKUP internal composition returns 201;
- successful same-key replay returns the original 201;
- invalid requests produce no business side effects;
- structural errors use canonical error codes;
- domain errors remain distinct;
- protected responses use private/no-store semantics;
- response serializer exposes only frozen fields;
- no raw Eloquent serialization is used;
- the public endpoint is not activated as PICKUP-only without an explicit contract decision;
- valid DELIVERY input is fully validated even while persistence remains blocked;
- invalid DELIVERY fails validation before reaching the persistence blocker;
- valid blocked DELIVERY causes no mutation;
- no new public error is invented for the Phase 7.4 implementation gap;
- Phase 7.4 remains explicitly BLOCKED;
- billing snapshot persistence is not bypassed;
- no schema change is introduced;
- no dependency is introduced;
- no frontend change occurs;
- OpenAPI remains aligned;
- full regression suite passes;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- `git diff --check` passes.

---

# 164. Out of Scope

Do not implement:

```text
Phase 7.4 billing snapshot persistence
new billing-address schema
OrderAddress table
DELIVERY persistence workaround
Phase 7.9 Group G closure
Payment initiation
PAY-001
payment provider
payment webhook
delivery fee calculation
reservation release
reservation consumption
Order cancellation
Order lifecycle actions
frontend
```

---

# 165. STOP Condition

STOP when CHK-001 has a complete validation/application boundary:

```text
authenticated active CUSTOMER
→ strict Idempotency-Key
→ strict JSON body
→ exact fulfillment validation
→ normalized PICKUP/DELIVERY intent
→ CheckoutCommand
→ existing CheckoutTransaction
→ canonical Checkout outcome/error mapping
```

while preserving:

```text
Phase 7.4 = BLOCKED
billing snapshot persistence = unresolved
DELIVERY Order persistence = not implemented
public CHK-001 must not falsely operate as PICKUP-only
Group G = not closed
```

Do not continue automatically to Phase 7.9.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.