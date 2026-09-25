# Phase 7.2 — Address Model Review

## Purpose

Review and reconcile the Checkout delivery-address model before implementing PICKUP and DELIVERY workflows.

The frozen Version 1 Checkout API remains:

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

The canonical V1 public field is:

```text
city
```

NOT:

```text
region
```

Phase 7.2 must adapt the Laravel implementation to the frozen contract.

Do not change OpenAPI from `city` to `region`.

---

# 1. Phase Scope

Phase 7.2 owns:

```text
Checkout delivery-address model review
AddressField implementation reconciliation
request → normalized address mapping
Order address snapshot compatibility
PICKUP null-address behavior
DELIVERY required-address behavior
address persistence compatibility
validation boundary preparation
```

It does NOT own:

```text
full Checkout implementation
inventory reservation
Order creation transaction
delivery fee finalization
Order totals implementation
transaction locking
payment
frontend
```

---

# 2. Starting State

Phase 7.1 is:

```text
PASS
```

and froze:

```text
CHK-001
POST /api/v1/checkout
```

with:

```text
CUSTOMER-only
own ACTIVE Cart
Idempotency-Key required
PICKUP | DELIVERY
Model B delivery fee
current pricing
transaction-time stock
Order PENDING_PAYMENT
Cart clear-on-success
```

Phase 7.2 must preserve every Phase 7.1 decision.

---

# 3. Frozen Address Contract

The public V1 Checkout DELIVERY address is exactly:

```text
recipient_name
phone
address_line
city
```

These names are frozen.

---

# 4. OpenAPI Is Authoritative

Current OpenAPI defines:

```text
DeliveryAddressInput
```

required fields:

```text
recipient_name
phone
address_line
city
```

and:

```text
DeliveryAddressSnapshot
```

with the same field names.

Do not modify these to `region`.

---

# 5. Existing Laravel Drift

Current implementation has an existing helper/value object/field implementation referred to as:

```text
AddressField
```

which currently expects:

```text
region
```

This is implementation drift.

Phase 7.2 must reconcile the Laravel implementation to:

```text
city
```

while preserving the frozen external API.

---

# 6. Core Rule

The correct direction is:

```text
frozen API contract
        ↓
Laravel adapts
```

NOT:

```text
Laravel helper
        ↓
rewrite frozen API
```

---

# 7. Do Not Support Both Names by Default

Do not silently accept:

```json
{
  "city": "...",
  "region": "..."
}
```

or treat `region` as a V1 alias unless an explicit compatibility requirement already exists.

The V1 request schema has:

```text
additionalProperties: false
```

Therefore:

```text
region
```

from a client should be rejected as an unknown field.

---

# 8. No Alias Drift

Avoid validation such as:

```php
'delivery_address.city' => ...
'delivery_address.region' => ...
```

with "either/or" semantics.

That would broaden the frozen contract.

---

# 9. Public vs Internal Naming

If internal persistence genuinely still uses a column/property named:

```text
region
```

that is acceptable only if the mapping boundary is explicit:

```text
API city
→ internal region
```

and the API never exposes `region`.

However, first inspect whether changing the internal helper to use:

```text
city
```

is cleaner and safer.

Do not assume internal compatibility requirements before inspecting usage.

---

# 10. Preferred Outcome

Prefer one vocabulary throughout Checkout:

```text
city
```

if changing the internal helper is safe.

This reduces translation drift.

---

# 11. Compatibility Outcome

If existing persisted models or unrelated domains require:

```text
region
```

internally:

create a focused mapper/adapter.

Conceptually:

```text
DeliveryAddressInput.city
→ AddressField.region
```

but serialize snapshots back as:

```text
city
```

Never leak internal `region`.

---

# 12. Inspect All `AddressField` Callers

Before modifying it, search all repository usages.

Classify each caller:

```text
Checkout
Order
Delivery
Furniture Request
Enquiry
Customer profile
tests/factories
other
```

Determine whether `AddressField` is:

```text
checkout-specific
shared globally
legacy
unused
```

Do not change shared behavior blindly.

---

# 13. Search for `region`

Audit:

```text
app/
database/
tests/
docs/
config/
```

for:

```text
region
```

and classify every relevant occurrence.

---

# 14. Search for `city`

Likewise audit:

```text
city
```

and identify:

```text
API request usage
Order snapshot columns
Delivery columns
DTOs
Resources
Factories
Tests
Documentation
```

---

# 15. Address Model Inventory

Document the exact model chain:

```text
CheckoutRequest
→ normalized delivery address
→ Order snapshot
→ later Delivery record
→ Order API response
```

For every stage record the concrete field names.

---

# 16. Checkout Request Boundary

Future CHK-001 request validation must accept only:

```json
{
  "recipient_name": "...",
  "phone": "...",
  "address_line": "...",
  "city": "..."
}
```

for `delivery_address`.

---

# 17. Strict Nested Schema

Unknown nested fields must fail.

Examples:

```text
region
district
country
postal_code
latitude
longitude
instructions
```

must be rejected unless explicitly present in frozen V1.

---

# 18. Required Fields

For DELIVERY:

```text
recipient_name
phone
address_line
city
```

are all required.

---

# 19. Nullability

For DELIVERY:

none of the four required address fields may be:

```text
missing
null
empty
whitespace-only
```

after normalization.

---

# 20. PICKUP Address Rule

For:

```text
fulfillment_type = PICKUP
```

`delivery_address` must be:

```text
absent
or
null
```

according to the frozen Checkout contract.

---

# 21. PICKUP Must Not Persist Fake Address

Do not create:

```text
recipient_name = ""
phone = ""
address_line = ""
city = ""
```

for PICKUP.

Use:

```text
delivery_address = null
```

---

# 22. DELIVERY Historical Snapshot

For DELIVERY, the validated address becomes immutable historical Order data.

It must not remain a live reference to:

```text
customer_profiles
future saved-address table
user profile
```

---

# 23. Saved Addresses Remain Deferred

Do not add:

```text
saved_address_id
address_book
default_address
customer_addresses
```

during Phase 7.2.

---

# 24. Customer Profile Fallback Is Forbidden

Do not silently fill missing:

```text
recipient_name
phone
city
```

from profile data.

Checkout requires the frozen DELIVERY payload.

---

# 25. Address Snapshot Authority

Once Checkout creates an Order:

```text
Order.delivery_address
```

must reflect the address supplied and normalized at Checkout time.

Future profile edits must not alter it.

---

# 26. Order Response

The Order API must eventually expose:

```json
{
  "delivery_address": {
    "recipient_name": "...",
    "phone": "...",
    "address_line": "...",
    "city": "..."
  }
}
```

Never:

```json
{
  "region": "..."
}
```

---

# 27. Billing Address

Review existing V1 Order schema for:

```text
billing_address
```

Do not invent billing-address behavior.

If current contract says:

```text
null for PICKUP
```

or reuses the delivery snapshot shape, document exact behavior.

Do not expand Phase 7.2 into billing functionality.

---

# 28. Delivery Model

Phase 7.1 determined:

```text
CHK-001 does not create a Delivery row
```

The Order stores the fulfillment snapshot first.

A Delivery record is created later.

Phase 7.2 must verify that when it is eventually created, it can faithfully carry or reference the frozen Order address snapshot.

---

# 29. Do Not Create Delivery Row

No Delivery persistence workflow should be activated in Phase 7.2.

This remains model review/preparation.

---

# 30. Review Database Columns

Inspect relevant migrations for:

```text
orders
deliveries
```

and any address/value-object fields.

Record whether persistence currently uses:

```text
city
region
JSON address
individual columns
```

---

# 31. No Blind Migration

Do not create a migration merely because `AddressField` uses `region`.

First determine whether the mismatch exists only in code.

---

# 32. Schema Decision

Expected:

```text
Schema changes: NONE
```

if existing storage can represent the frozen snapshot.

---

# 33. True Model Gap

Only classify:

```text
MODEL GAP
```

if existing persistence literally cannot store:

```text
recipient_name
phone
address_line
city
```

without losing meaning or violating constraints.

---

# 34. Prefer Mapper Over Migration When Appropriate

If storage uses a generic field/value that can carry city correctly:

adapt at the application boundary.

Avoid unnecessary schema churn.

---

# 35. `AddressField` Review

Inspect the actual implementation.

Determine whether it is:

```text
validation helper
DTO
value object
cast
model accessor
database mapper
request-field list
```

Do not infer from its name.

---

# 36. If `AddressField` Is Only a Field Constant

If it merely defines:

```text
recipient_name
phone
address_line
region
```

replace/reconcile `region` to:

```text
city
```

where safe.

---

# 37. If `AddressField` Is Shared

If changing it would break unrelated domains:

do NOT force global renaming.

Instead introduce a Checkout-specific address contract/value object such as:

```text
CheckoutDeliveryAddress
```

or equivalent repository-style abstraction.

---

# 38. Avoid Generic Premature Abstraction

Do not create:

```text
UniversalAddress
AddressFramework
AddressSchemaEngine
```

for four fields.

Prefer the smallest coherent abstraction.

---

# 39. Recommended Domain Object

A small immutable object may be appropriate:

```text
CheckoutDeliveryAddress
```

containing:

```text
recipientName
phone
addressLine
city
```

if existing architecture uses DTO/value objects.

---

# 40. Transport Naming

Public JSON remains:

```text
recipient_name
phone
address_line
city
```

Internal PHP property naming may follow repository standards.

---

# 41. Mapping Must Be Explicit

For example conceptually:

```text
validated request
→ CheckoutDeliveryAddress
→ Order snapshot fields
```

Do not pass unstructured request arrays deep into Checkout.

---

# 42. No `$request->all()`

Future request processing must use:

```php
$request->validated()
```

and explicit mapping.

---

# 43. Address Normalization

Phase 7.1 froze:

```text
trimmed strings
```

for all fields.

Implement/review normalization rules accordingly.

---

# 44. Recipient Name

`recipient_name`:

```text
required
string
trimmed
non-empty
max 255
```

according to Phase 7.1.

---

# 45. Phone

`phone`:

```text
required
string
trimmed
normalized
non-empty
max 30
```

Use existing phone normalization behavior where compatible.

---

# 46. Phone Format

Phase 7.1 refers to:

```text
E.164-ish representation
```

Do not silently tighten this to full libphonenumber validation unless already frozen.

No new dependency.

---

# 47. Address Line

`address_line`:

```text
required
string
trimmed
non-empty
```

Do not invent a new numeric max length if the frozen contract does not define one.

---

# 48. City

`city`:

```text
required
string
trimmed
non-empty
```

No new arbitrary length restriction unless persistence itself requires one.

---

# 49. Persistence Limit Conflict

If database schema imposes a narrower maximum than the API contract:

classify that explicitly.

Do not silently truncate input.

Possible outcomes:

```text
MODEL GAP
or
DOC DRIFT
```

depending authority.

---

# 50. Never Truncate Address Data

Do not:

```text
substr()
silent database truncation
```

for valid API input.

If persistence cannot represent valid contract data:

the model must be fixed before implementation.

---

# 51. `region` Request Test

Future validation test:

```json
{
  "delivery_address": {
    "recipient_name": "Asha",
    "phone": "+255700000001",
    "address_line": "Jengo Street",
    "region": "Dar es Salaam"
  }
}
```

must fail.

Expected:

```text
422 INVALID_VALUE
field: delivery_address.region
```

or exact strict-schema mapping.

---

# 52. Missing `city`

Payload with:

```text
region
```

but without:

```text
city
```

must also report the missing canonical `city` field according to validation-envelope behavior.

Do not accept `region` as substitute.

---

# 53. Both `city` and `region`

Payload containing both:

```text
city
region
```

must fail because `region` is an unknown field.

Do not silently prefer `city`.

---

# 54. Snapshot Test

Given:

```text
city = "Dar es Salaam"
```

the historical Order snapshot must later return exactly the normalized value under:

```text
city
```

---

# 55. Internal `region` Must Never Leak

If adapter mapping to an internal `region` property is unavoidable:

assert resources/OpenAPI serialization still exposes:

```text
city
```

only.

---

# 56. Round-Trip Requirement

Prove conceptually:

```text
request.city
→ normalized internal representation
→ persistence
→ Order resource city
```

without:

```text
field rename drift
data loss
```

---

# 57. Historical Stability

If internal Delivery structure changes later:

existing Order snapshots must still serialize correctly.

The Order snapshot is historical authority.

---

# 58. No Address Geocoding

Out of scope:

```text
Google Maps
geocoding
coordinates
distance
delivery zones
maps API
```

---

# 59. No Region/City Lookup Table

Do not create:

```text
regions
cities
districts
wards
```

tables during Phase 7.2.

The contract accepts a string snapshot.

---

# 60. No Address Verification Service

Do not call external APIs to verify:

```text
city
street
phone
```

---

# 61. No Country Field

Do not add:

```text
country
country_code
```

to V1 Checkout address.

TZS/business geography does not justify widening frozen schema.

---

# 62. No Postal Code

Do not add it.

---

# 63. No District/Ward

Do not add them.

---

# 64. No Delivery Instructions

Do not add them.

---

# 65. No Coordinates

Do not add:

```text
lat
lng
```

---

# 66. Fulfillment Validation Separation

Address-model validation should distinguish:

```text
request shape
```

from:

```text
fulfillment branch rule
```

Example:

```text
PICKUP + populated delivery_address
```

is an invalid fulfillment combination.

---

# 67. DELIVERY Missing Address

For:

```text
fulfillment_type = DELIVERY
```

missing `delivery_address` must produce the frozen missing-field behavior.

---

# 68. Wrong Address Type

Examples:

```json
"delivery_address": "Dar es Salaam"
```

or:

```json
"delivery_address": []
```

must fail strict type validation.

---

# 69. Unknown Nested Field

Example:

```json
{
  "city": "Dar es Salaam",
  "region": "Dar es Salaam"
}
```

must fail unknown-field validation.

---

# 70. Null Nested Field

Example:

```json
{
  "city": null
}
```

must fail.

---

# 71. Empty City

```json
{
  "city": ""
}
```

must fail after normalization.

---

# 72. Whitespace City

```json
{
  "city": "   "
}
```

must fail after trim.

---

# 73. Valid City

```json
{
  "city": "Dar es Salaam"
}
```

must remain:

```text
Dar es Salaam
```

after normalization.

---

# 74. City Is Not an Enum

Do not restrict:

```text
Dar es Salaam
Dodoma
Arusha
Mwanza
...
```

to a hardcoded list.

The frozen contract says string.

---

# 75. City Is Not Delivery-Fee Authority

The presence of:

```text
city
```

does not authorize Checkout to calculate a fee.

Model B remains:

```text
Staff/Admin set fee later via ORD-014
```

---

# 76. No Flat Fee Logic

Do not reintroduce:

```text
TZS 20,000
```

based on city.

---

# 77. No Zone Mapping

Phase 7.5 may review delivery-fee workflow, but V1 currently uses Staff/Admin fee assignment.

Do not implement:

```text
city → delivery fee
```

mapping.

---

# 78. Snapshot and Idempotency

Normalized delivery address participates in CHK-001's idempotency fingerprint.

Therefore address normalization must be deterministic.

---

# 79. Equivalent Whitespace

Decide and document whether:

```text
" Dar es Salaam "
```

normalizes to:

```text
"Dar es Salaam"
```

before fingerprinting.

Expected:

```text
YES
```

given Phase 7.1 trim rule.

---

# 80. Fingerprint Uses Normalized Address

Do not fingerprint raw input before normalization.

Otherwise harmless whitespace differences could cause false:

```text
409 DUPLICATE_OPERATION
```

---

# 81. Field Ordering

JSON key order must not materially affect the idempotency fingerprint.

Use normalized structured representation.

---

# 82. Phone Normalization and Fingerprint

Use normalized phone representation before fingerprinting.

The same logical phone must not produce accidental idempotency mismatch due solely to supported formatting differences.

---

# 83. Address Object Immutability

Once built, prefer immutable DTO/value semantics.

Do not mutate address later during Order creation.

---

# 84. Request Validation Ownership

Phase 7.8 owns final Checkout validation orchestration.

Phase 7.2 may prepare:

```text
address DTO
normalizer
field mapping
unit tests
```

but must not fully activate CHK-001.

---

# 85. Phase 7.3 Boundary

Phase 7.3 will implement/review PICKUP behavior.

Phase 7.2 should ensure:

```text
delivery_address = null
```

is easy and valid for PICKUP.

---

# 86. Phase 7.4 Boundary

Phase 7.4 will implement/review DELIVERY behavior.

Phase 7.2 must provide a stable:

```text
validated normalized city-based address snapshot
```

for it.

---

# 87. Phase 7.5 Boundary

Delivery fee remains separate.

Address is input to business review/fulfillment but not a client fee authority.

---

# 88. Phase 7.6 Boundary

Order totals should not depend on arbitrary address model logic.

---

# 89. Phase 7.7 Boundary

Phase 7.7 eventually persists the address snapshot atomically with Order creation.

Phase 7.2 only ensures model compatibility.

---

# 90. Phase 7.8 Boundary

Phase 7.8 integrates strict Checkout request validation.

Do not fully duplicate that phase now.

---

# 91. Existing Order Schema

Review actual:

```text
orders
```

migration/model.

Record how delivery address is stored.

---

# 92. Existing Delivery Schema

Review actual:

```text
deliveries
```

migration/model.

Record whether it currently expects:

```text
city
region
```

or neither.

---

# 93. Existing Casts

Inspect:

```text
casts()
custom Casts
JSON casts
AddressField
DTO hydration
```

that affect address persistence.

---

# 94. Existing Resources

Inspect Order API resources for:

```text
delivery_address
billing_address
```

Confirm serialized key is:

```text
city
```

---

# 95. Factories

Update/test factories only where required to reflect correct internal model shape.

Do not casually rewrite unrelated fixtures.

---

# 96. Seeders

No production seeder changes expected.

---

# 97. Migration Tests

If schema is unchanged:

add/retain tests proving existing columns can store the frozen snapshot.

---

# 98. Address DTO Test

If introducing or modifying a DTO/value object, test:

```text
valid construction
trim normalization
city retained
region rejected/not represented
phone normalization
immutable behavior
```

---

# 99. Mapping Test

If internal storage uses `region`:

test explicitly:

```text
API city
→ internal region
→ API city
```

to prevent future regression.

---

# 100. Prefer Elimination of Mapping

If safely possible, changing the internal helper itself to `city` is preferable to maintaining permanent translation.

But only after caller audit.

---

# 101. No Public `region`

Run codebase search after implementation.

Any public Checkout/Order schema/resource/test expectation of:

```text
region
```

is a failure.

---

# 102. Internal `region`

If retained internally, document exactly why and where.

Do not leave unexplained dual terminology.

---

# 103. Documentation Scope

Update Phase 7.2 documentation to state:

```text
city = frozen V1 API
region = legacy/internal implementation detail only, if retained
```

---

# 104. Do Not Change Frozen OpenAPI to Region

Absolutely do not edit:

```text
DeliveryAddressInput.city
DeliveryAddressSnapshot.city
CHK-001 delivery example city
```

to `region`.

---

# 105. Do Not Change Phase 7.1

Phase 7.1 is already correct.

Its finding:

```text
AddressField region vs API city
```

is the issue Phase 7.2 resolves.

Do not rewrite history to pretend the mismatch did not exist.

---

# 106. Decision Record

Add a backend ADR if implementation reconciliation requires a durable architectural decision.

Suggested concept:

```text
Checkout/Order public address vocabulary is `city`.
Existing Laravel address helpers must adapt to the frozen API.
```

Do not create a new public API decision.

---

# 107. Suggested ADR

If needed:

```text
ADR/BACKEND-0XX —
Checkout Address Model Alignment
```

Use actual next ADR number.

---

# 108. Gap Classification

At the end classify:

```text
AddressField region mismatch
```

as one of:

```text
RESOLVED
BLOCKED
```

It must not remain partially unresolved if Phase 7.2 passes.

---

# 109. Expected Resolution

Preferred:

```text
RESOLVED
```

with:

```text
public API = city
Laravel Checkout mapping = city-compatible
Order snapshot = city
```

---

# 110. Schema Change Expected

Expected:

```text
NONE
```

unless persistence truly cannot represent the V1 snapshot.

---

# 111. Dependency Change Expected

Expected:

```text
NONE
```

---

# 112. Frontend

Expected:

```text
NONE
```

No Next.js or Flutter changes in Phase 7.2.

---

# 113. No Checkout Route Activation

CHK-001 may remain:

```text
501/stub
```

until later implementation phases.

Do not partially activate it just to test address parsing.

---

# 114. Existing Tests

Run relevant existing:

```text
Order schema tests
Delivery schema tests
API resource contract tests
OpenAPI tests
AddressField/helper unit tests
```

---

# 115. Add Focused Tests

Create focused Phase 7.2 tests as appropriate.

Possible suites:

```text
CheckoutDeliveryAddressTest
OrderAddressSnapshotTest
AddressFieldCompatibilityTest
```

Use actual repository naming.

---

# 116. Test — Frozen Keys

Assert canonical keys exactly:

```text
recipient_name
phone
address_line
city
```

---

# 117. Test — `region` Rejected

Where request-schema logic can be tested without activating Checkout:

assert `region` is not an accepted public field.

---

# 118. Test — Round Trip

Ensure:

```text
city
→ storage
→ city
```

---

# 119. Test — PICKUP Null

Ensure models/DTOs allow:

```text
no address
```

without fake values.

---

# 120. Test — DELIVERY Complete Snapshot

All four fields survive normalization/persistence mapping.

---

# 121. Test — Historical Snapshot

Mutating Customer profile afterward must not alter the stored Order address representation.

If Order creation is not yet active, test at model/value-object level rather than building future Checkout workflow early.

---

# 122. Test — No Truncation

Where persistence length limits exist, verify valid frozen input is not silently truncated.

---

# 123. Test — City Whitespace

Input:

```text
"  Dar es Salaam  "
```

normalizes to:

```text
"Dar es Salaam"
```

---

# 124. Test — Empty City

Rejected by prepared validator/value object.

---

# 125. Test — Phone

Verify current approved normalization.

Do not expand validation scope beyond contract.

---

# 126. Test — AddressLine

Trim but do not arbitrarily rewrite.

---

# 127. Test — Recipient Name

Trim and preserve Unicode.

Do not assume ASCII-only names.

---

# 128. Unicode

Address strings must remain Unicode-safe.

Do not corrupt:

```text
names
street names
city names
```

through normalization.

---

# 129. No Lowercasing City

Do not normalize:

```text
Dar es Salaam
```

to:

```text
dar es salaam
```

unless contract explicitly requires it.

Trim only.

---

# 130. No Uppercasing City

Likewise do not force uppercase.

---

# 131. Phone Is Different

Phone may use canonical normalization.

Keep field-specific behavior separate.

---

# 132. Error Field Paths

Future validation errors should identify:

```text
delivery_address.city
delivery_address.phone
...
```

not:

```text
region
```

---

# 133. Internal Exception Leakage

Do not expose:

```text
AddressField::REGION
database column names
cast implementation
```

in public errors.

---

# 134. Mass Assignment

Do not pass raw address arrays into a mass assignment boundary that accepts unrelated fields.

Use explicit mapping.

---

# 135. Security

Ensure no client address field can override:

```text
user_id
order_id
delivery_fee
status
created_at
```

---

# 136. No HTML Sanitization Requirement

This phase is data validation, not rendering.

Do not destroy legitimate characters with aggressive HTML stripping unless repository conventions already require it.

Output escaping belongs at UI/rendering layer.

---

# 137. Database Safety

Eloquent/query parameterization handles SQL injection.

Do not build SQL from address strings.

---

# 138. Address Data Privacy

Addresses are private customer/order data.

Do not log full:

```text
recipient_name
phone
address_line
```

unnecessarily.

---

# 139. Logging

Safe logs may contain:

```text
request_id
operation
validation failure code
```

Avoid full address payload.

---

# 140. Audit

Ordinary customer Checkout address input does not require a privileged audit event merely for address validation.

Order creation/history handles business record later.

---

# 141. Cache

Order/Checkout address information remains private/no-store.

No public caching.

---

# 142. API Contract Verification

Verify current OpenAPI still has:

```text
DeliveryAddressInput.city
DeliveryAddressSnapshot.city
```

after Phase 7.2.

---

# 143. No Contract Widening

Do not add:

```text
region
country
district
postal_code
```

to OpenAPI.

---

# 144. Code Review Search

Before completion search for relevant public serialization of:

```text
region
```

within Checkout/Order paths.

Any leak must be resolved.

---

# 145. Internal Compatibility Search

Also ensure changing `AddressField` does not break unrelated modules.

Run focused tests for every caller found in the initial audit.

---

# 146. Complexity

Keep:

```text
cognitive complexity <= 15
<= 3 returns where practical
```

---

# 147. Do Not Build a God Address Service

Avoid a huge service for simple snapshot mapping.

---

# 148. Reuse Existing Normalization

If a safe shared string/phone normalizer exists:

reuse it.

Do not duplicate.

---

# 149. No New Third-Party Package

Do not add an address library.

---

# 150. Verification Commands

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

# 151. OpenAPI Parse

Also verify:

```text
docs/api/openapi.yaml
```

still parses successfully.

---

# 152. Route Count

Phase 7.2 must introduce:

```text
new routes = NONE
```

---

# 153. Completion Report

Return:

## Phase 7.2 status

```text
PASS
```

or:

```text
BLOCKED
```

## Frozen API

Confirm:

```text
public location field = city
region = not accepted by V1 API
```

## AddressField

Report:

```text
previous behavior
final behavior
whether modified or adapted
all callers audited
```

## Public/Internal Mapping

If internal `region` remains:

state the exact mapper.

If eliminated:

state that clearly.

## Checkout Request

Confirm canonical fields:

```text
recipient_name
phone
address_line
city
```

## PICKUP

Confirm:

```text
delivery_address = null/absent
```

## DELIVERY

Confirm required normalized historical snapshot behavior.

## Order Snapshot

Report how `city` is stored and later serialized.

## Delivery Model

Report compatibility with the Order snapshot.

## Validation

Report:

```text
requiredness
trimming
phone normalization
unknown-field behavior
region rejection
```

## Idempotency

Confirm normalized address is suitable for later deterministic fingerprinting.

## Privacy

Report logging/private-data handling.

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

Confirm:

```text
unchanged frozen city contract
```

unless a genuine documentation typo unrelated to field naming was corrected.

## Tests

Report focused and full test results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
OpenAPI parse
```

## Drift Resolution

Return exactly:

```text
CITY / REGION DRIFT — RESOLVED
```

or:

```text
CITY / REGION DRIFT — BLOCKED
```

with concrete reason.

## Phase 7.3 readiness

Return:

```text
Phase 7.3 — Pickup flow: READY
```

or:

```text
BLOCKED
```

---

# 154. Definition of Done

Phase 7.2 is complete when:

- frozen V1 still uses `city`;
- `region` has not been added to the public API;
- `AddressField` and all callers have been audited;
- Laravel can consume a valid `city` Checkout address;
- Laravel can persist/map the `city` snapshot without data loss;
- Order serialization returns `city`;
- internal `region`, if retained, is hidden behind an explicit adapter;
- PICKUP requires no fake address;
- DELIVERY requires all four frozen fields;
- unknown nested address fields are rejected;
- `region` is rejected publicly;
- strings are trimmed;
- empty/whitespace values are rejected;
- phone normalization remains contract-compatible;
- no saved-address system is introduced;
- no geocoding is introduced;
- no delivery-zone logic is introduced;
- no fee calculation is introduced;
- no arbitrary city enum/list is introduced;
- normalized address can participate deterministically in later idempotency fingerprinting;
- Checkout/Order resources cannot leak `region`;
- no unnecessary migration exists;
- no new dependency exists;
- no frontend code changes;
- full regression suite remains green;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean;
- OpenAPI remains valid;
- CITY / REGION drift is fully resolved.

---

# 155. Out of Scope

Do not implement:

```text
Phase 7.3 PICKUP workflow
Phase 7.4 DELIVERY workflow
delivery fee logic
Order totals
Checkout transaction
stock reservation
Order creation
payment
saved addresses
address verification
geocoding
delivery zones
frontend
```

---

# 156. STOP Condition

STOP when the backend address model can faithfully support:

```text
DELIVERY request
city
→ normalized address
→ historical Order snapshot
→ Order response city
```

while:

```text
region
```

remains absent from the frozen V1 API.

Then report:

```text
CITY / REGION DRIFT — RESOLVED
```

Do not continue automatically to Phase 7.3.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.