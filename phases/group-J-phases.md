# Phase 10.2 — Furniture Request Validation

## 1. Objective

Implement the complete **REQ-001 request validation and normalization boundary** for:

```http
POST /api/v1/requests
```

Endpoint:

```text
REQ-001
```

Build directly on the Phase 10.1 architecture:

```text
RequestController
→ CreateFurnitureRequestRequest
→ normalized validated payload
→ CreateFurnitureRequestCommand
→ CreateFurnitureRequest
→ FurnitureRequestResource
```

Phase 10.2 must make the request input contract authoritative, deterministic, tamper-resistant, and compatible with the frozen Version 1 API.

This phase owns:

```text
transport/schema validation
strict allow-list
type validation
requiredness
cross-field contact validation
quantity validation
name normalization
phone normalization
email normalization
dimensions validation
material/color/notes validation
server-controlled field rejection
canonical validation errors
zero-side-effect failure behavior
validation tests
```

It does **not** own Product business eligibility, attachments, lifecycle transitions, staff operations, or Enquiries.

---

# 2. Current Group J State

Treat the current state as:

```text
10.1 PASS — Furniture Request API foundation
10.2 CURRENT — Request validation
10.3 NOT STARTED — request status lifecycle
10.4 NOT STARTED — product-linked requests
10.5 NOT STARTED — general enquiries
10.6 NOT STARTED — attachment handling
10.7 NOT STARTED — staff/admin request management
10.8 NOT STARTED — request/enquiry tests
```

The public REQ-001 route remains:

```text
STUB / GATED
```

through:

```php
config('requests.route_enabled') === false
```

Preserve that state during Phase 10.2 unless all remaining frozen externally observable prerequisites are genuinely complete.

They are not currently complete because:

```text
Phase 10.4 product eligibility
Phase 10.6 attachments
```

remain outstanding.

---

# 3. Phase 10.1 Baseline

Reuse:

```text
Controller:
App\Http\Controllers\Api\V1\RequestController

Command:
App\Services\Requests\CreateFurnitureRequestCommand

Service:
App\Services\Requests\CreateFurnitureRequest

Resource:
App\Http\Resources\FurnitureRequestResource
```

Do not replace these with a second request-creation workflow.

Preserve:

```text
anonymous → user_id null
CUSTOMER → server-derived user_id
invalid bearer → rejected
STAFF/ADMIN → rejected

REQ reference → ReferenceGenerator
status → SUBMITTED

public notes → database message
style → not public
product_details → not public
staff_internal_notes → not public
```

---

# 4. Core Validation Principle

Maintain the project validation pipeline:

```text
Transport
→ Schema
→ Optional Authentication
→ Authorization
→ Domain
→ Persistence
```

For Phase 10.2:

```text
Transport + Schema + Cross-field normalization
```

are the primary scope.

Do not put Product database/business-state validation inside the FormRequest.

---

# 5. Product Validation Split

This distinction is mandatory.

Phase 10.2 validates only the **shape** of:

```text
product_id
```

such as:

```text
optional
nullable
string
valid opaque Product identifier format
```

Phase 10.4 owns:

```text
does Product exist?
is Product active?
is Product published?
is Product publicly visible?
is product_type MADE_TO_ORDER?
```

Do not query Product state from the FormRequest merely because `product_id` is present.

---

# 6. No Premature `PRODUCT_NOT_REQUESTABLE`

Do not implement:

```text
IN_STOCK → PRODUCT_NOT_REQUESTABLE
```

in Phase 10.2.

That belongs to Phase 10.4.

Phase 10.2 should ensure only structurally valid `product_id` values can reach that future domain boundary.

---

# 7. Dedicated FormRequest

Complete or introduce:

```php
App\Http\Requests\CreateFurnitureRequestRequest
```

Use this as the single REQ-001 schema-validation authority.

Do not scatter equivalent rules across:

```text
controller
service
model
middleware
```

unless an invariant belongs at a lower persistence/domain layer.

---

# 8. Validated Input Only

Controller must consume:

```php
$request->validated()
```

Never:

```php
$request->all()
```

Never construct the command from raw request input.

---

# 9. Top-Level JSON Allow-List

For JSON REQ-001, accept exactly these public fields:

```text
product_id
quantity
name
phone
email
dimensions
material
color
notes
```

No other JSON fields.

Attachment transport remains separately deferred to Phase 10.6.

---

# 10. Reject Unknown Fields

Unknown top-level properties must produce:

```text
422 INVALID_VALUE
```

with the exact offending field.

Examples:

```text
message
style
product_details
foo
region
subject
category
```

Do not silently discard them.

---

# 11. Explicit Server-Controlled Field Rejection

Reject attempts to submit:

```text
user_id
request_reference
request_status
staff_internal_notes
created_at
updated_at
id
```

---

# 12. Commerce-Tampering Fields

Also reject:

```text
order_id
order_reference
payment
payment_id
payment_status
payment_reference
delivery_fee
delivery_fee_status
subtotal
total
price
quoted_price
currency
inventory_id
reserved_quantity
```

A Furniture Request must remain separate from commerce transactions.

---

# 13. Schema/API Naming

Public:

```text
notes
```

Internal database:

```text
message
```

The validator accepts:

```text
notes
```

and rejects:

```text
message
```

Never expose persistence vocabulary as an API alias.

---

# 14. `style`

Reject:

```text
style
```

It exists in storage but not in frozen REQ-001.

---

# 15. `product_details`

Reject:

```text
product_details
```

It exists in storage but is not part of the frozen public creation schema.

---

# 16. `product_id`

Rules:

```text
optional
nullable
string when non-null
must match canonical opaque Product identifier format
```

Example valid shape:

```text
prod_...
```

Do not accept:

```text
integer DB id
array
object
boolean
arbitrary malformed string
```

---

# 17. `product_id = null`

Valid.

Represents:

```text
custom/general furniture request
```

Do not require a Product.

---

# 18. Omitted `product_id`

Also valid.

Normalize consistently with:

```text
productId = null
```

unless the current command intentionally distinguishes omitted vs explicit null.

No business value requires that distinction in V1.

---

# 19. Do Not Query Product Here

The validator must not do:

```php
Product::query()
```

for MADE_TO_ORDER eligibility.

Phase 10.4 owns that.

---

# 20. `quantity`

Rules:

```text
optional
nullable
strict integer
minimum 1
maximum 100
```

---

# 21. Quantity Type Strictness

Accept:

```json
1
2
100
```

Reject:

```json
"1"
"2"
1.5
true
false
[]
{}
```

No coercion.

---

# 22. Quantity Range

Reject:

```text
0
negative values
>100
```

---

# 23. Quantity Error Classification

Wrong JSON type:

```text
INVALID_TYPE
```

Correct integer type but out of range:

```text
INVALID_VALUE
```

Do not collapse everything into generic validation error.

---

# 24. Omitted Quantity

Must remain:

```text
null / unspecified
```

Do not default to:

```text
1
```

---

# 25. Explicit `quantity: null`

Treat according to the frozen nullable contract.

Normalize to:

```text
null
```

---

# 26. `name`

Required for:

```text
Anonymous
authenticated CUSTOMER
```

No exception.

---

# 27. Name Rules

Must be:

```text
string
trimmed
non-empty
max 120 characters
Unicode-safe
```

---

# 28. Internal Name Whitespace

The frozen contract specifies normalization that collapses internal whitespace.

Example:

```text
"Asha    Mwangi"
```

normalize to:

```text
"Asha Mwangi"
```

Do so deterministically.

Preserve valid Unicode characters.

---

# 29. Name Missing

Expected:

```text
422 MISSING_REQUIRED_FIELD
field: name
```

---

# 30. Name Null

Invalid.

Use the canonical missing/type mapping according to existing validator conventions.

Keep behavior deterministic.

---

# 31. Name Wrong Type

Examples:

```json
"name": 123
"name": true
"name": []
```

Expected:

```text
422 INVALID_TYPE
field: name
```

---

# 32. Empty Name

After normalization:

```text
""
"   "
```

must fail.

Expected semantic category:

```text
MISSING_REQUIRED_FIELD
```

where the frozen Request contract classifies missing/empty required contact as missing.

---

# 33. Name Maximum

Reject >120 characters.

Use:

```text
INVALID_VALUE
```

or the repository's established max-length mapping.

Do not silently truncate.

---

# 34. Contact Rule

For **both**:

```text
Anonymous
CUSTOMER
```

the request must contain:

```text
name
```

and at least one usable contact channel:

```text
phone
or
email
```

Both may be provided.

---

# 35. No Authentication-Based Relaxation

Do not make contact optional for authenticated Customers.

The frozen rule deliberately keeps the Request self-contained.

Do not fallback to profile or Clerk data.

---

# 36. Phone/Email Cross-Field Rule

Valid:

```text
phone only
email only
phone + email
```

Invalid:

```text
phone missing + email missing
phone null + email null
phone blank + email blank
```

---

# 37. Missing Contact Error

When both phone and email are absent/unusable:

return:

```text
422 MISSING_REQUIRED_FIELD
```

Follow the frozen field convention.

The contract identifies `field: phone` as an accepted deterministic representation.

Do not randomly alternate between `phone` and `email`.

---

# 38. `phone`

Optional individually, conditional collectively.

When supplied:

```text
string
trimmed
normalized
non-empty
max 30
```

---

# 39. Phone Normalization

Reuse the project's established E.164-ish normalization primitive if one exists.

Do not create a third independent phone normalizer.

Inspect Phase 7.2 and other profile/contact validation utilities first.

---

# 40. Phone Normalization Boundary

The normalized result must be what enters:

```text
CreateFurnitureRequestCommand
```

and what is persisted.

---

# 41. Phone Wrong Type

Expected:

```text
INVALID_TYPE
field: phone
```

---

# 42. Phone Invalid Format

For a string that cannot satisfy the existing accepted phone representation:

```text
INVALID_FORMAT
```

unless the repository's established contact validator maps this specific condition differently.

Use one deterministic mapping.

---

# 43. Phone Maximum

Reject normalized values >30 characters.

Do not truncate.

---

# 44. Empty Phone with Valid Email

Example:

```json
{
  "phone": "",
  "email": "asha@example.com"
}
```

Decide normalization consistently.

Preferred:

```text
empty optional phone → null
```

provided the contract's at-least-one contact rule is still satisfied.

Do not persist meaningless empty strings.

---

# 45. `email`

Optional individually, conditional collectively.

When supplied:

```text
string
trimmed
lowercased
valid email format
max 255
```

---

# 46. Email Normalization

Example:

```text
" ASHA@Example.COM "
```

normalize to:

```text
asha@example.com
```

---

# 47. Email Wrong Type

Expected:

```text
INVALID_TYPE
```

---

# 48. Invalid Email Format

Expected:

```text
INVALID_FORMAT
field: email
```

---

# 49. Email Maximum

Reject >255 characters.

Do not truncate.

---

# 50. Empty Email with Valid Phone

Normalize optional empty email consistently, preferably:

```text
null
```

when phone provides the required contact channel.

Do not persist empty-string contact snapshots.

---

# 51. Both Contact Fields Empty

Must fail.

---

# 52. Profile Independence Regression

For authenticated Customer:

```text
profile.name exists
profile.phone exists
email exists in Clerk
```

but body lacks required contact.

The body must still fail.

No fallback.

---

# 53. `dimensions`

Optional.

Allowed values:

```text
omitted
null
object
```

---

# 54. Dimensions Object Allow-List

Exact keys:

```text
length
width
height
unit
```

No others.

---

# 55. Unknown Dimension Keys

Reject:

```text
depth
diameter
radius
measurement
units
size
```

Expected:

```text
422 INVALID_VALUE
field: dimensions.<key>
```

---

# 56. Dimension Numeric Fields

For each of:

```text
length
width
height
```

when supplied:

```text
must be JSON number
must be > 0
must be <= 10000
```

---

# 57. Decimal Dimensions

The frozen contract permits JSON numbers, not only integers.

Therefore values such as:

```json
220.5
```

may be valid if the persistence/model representation supports them without loss.

Before implementation, verify the existing `FurnitureRequest` dimension assertion/cast can preserve the approved numeric semantics.

Do not silently convert decimal to integer.

---

# 58. Numeric Strings

Reject:

```json
"length": "220"
```

even though it could be coerced.

Expected:

```text
INVALID_TYPE
```

---

# 59. Zero Dimensions

Reject:

```text
0
```

---

# 60. Negative Dimensions

Reject.

---

# 61. Dimension Maximum

Reject:

```text
>10000
```

---

# 62. Null Individual Dimension Values

The resource representation permits nullable dimension members.

Verify the frozen request schema/current conventions before deciding whether:

```json
{"length": null, "unit": "cm"}
```

is accepted.

Do not guess.

Use the normative API contract/OpenAPI behavior consistently.

If the current frozen request schema allows nullable members, preserve it.

If not, reject with correct type/value error.

Document the exact decision.

---

# 63. Dimensions `unit`

If any actual dimension value is supplied:

```text
unit is required
```

---

# 64. Canonical Unit

The only allowed value is:

```text
cm
```

case-sensitive.

---

# 65. Reject Unit Aliases

Reject:

```text
CM
centimeter
centimeters
centimetre
centimetres
in
inch
inches
mm
m
```

---

# 66. Unit Without Dimension Values

Inspect the frozen request/OpenAPI semantics.

Do not silently invent meaning for:

```json
{"unit": "cm"}
```

Preferred domain interpretation is invalid because no measurable dimension was supplied, unless current contract explicitly permits it.

Record whichever behavior the frozen documents support.

---

# 67. Empty Dimensions Object

Likewise verify:

```json
{}
```

against the contract.

Do not invent values.

If no dimensions are intended, canonical representation should be:

```text
dimensions omitted
or
dimensions = null
```

---

# 68. `dimensions: null`

Valid.

Normalize to:

```text
null
```

---

# 69. Dimensions Ordering

JSON property ordering must not affect normalized command data.

---

# 70. `material`

Optional.

Rules:

```text
string when supplied
trimmed
max 500
free text
```

---

# 71. Material Is Not Enum

Accept legitimate arbitrary furniture vocabulary.

Do not create:

```text
WOOD
METAL
FABRIC
OTHER
```

enum.

---

# 72. Empty Material

Normalize optional blank material consistently.

Preferred:

```text
null
```

rather than persisting meaningless `""`.

Follow existing nullable-text conventions.

---

# 73. Material Wrong Type

Expected:

```text
INVALID_TYPE
```

---

# 74. Material Too Long

Expected:

```text
INVALID_VALUE
```

Do not truncate.

---

# 75. `color`

Optional.

Rules:

```text
string
trimmed
max 200
free text
```

---

# 76. Color Is Not Enum

No color taxonomy.

---

# 77. Empty Color

Normalize optional blank to null if consistent with repository conventions.

---

# 78. Color Wrong Type

Expected:

```text
INVALID_TYPE
```

---

# 79. Color Too Long

Expected:

```text
INVALID_VALUE
```

---

# 80. `notes`

Optional.

Rules:

```text
string when supplied
trimmed
max 5000
Unicode-safe
newlines preserved where meaningful
plain text data
```

---

# 81. Notes Are Not Executed

Do not interpret notes as:

```text
HTML
Markdown
SQL
template
filesystem path
code
```

Store as plain customer-provided text.

Output encoding belongs to clients/framework rendering.

---

# 82. Do Not Destructively Sanitize Notes

Do not strip ordinary punctuation/Unicode merely because it resembles markup.

Validation + safe storage + safe output encoding is the security model.

---

# 83. Notes Maximum

Reject >5000 characters.

Do not truncate.

---

# 84. Empty Notes

Since notes are optional:

normalize empty/whitespace-only notes to:

```text
null
```

if consistent with current nullable persistence semantics.

---

# 85. Notes Mapping

After validation:

```text
validated notes
→ CreateFurnitureRequestCommand.notes
→ FurnitureRequest.message
```

Response remains:

```text
notes
```

---

# 86. `message`

Must remain rejected.

Do not accept it as alias for notes.

---

# 87. `style`

Must remain rejected.

---

# 88. `product_details`

Must remain rejected.

---

# 89. Parameter Pollution

Reject ambiguous/repeated scalar representations where framework parsing would otherwise produce unexpected array values.

Examples:

```text
quantity[]=1
name[]=Asha
```

must not bypass strict typing.

---

# 90. Nested Type Confusion

Reject:

```json
{
  "dimensions": "220x90"
}
```

Expected:

```text
INVALID_TYPE
field: dimensions
```

---

# 91. Boolean Type Confusion

Reject booleans for string/numeric fields.

Do not allow PHP coercion.

---

# 92. Strict Integer Quantity

Use actual strict integer validation.

Do not accept:

```text
"2"
2.0
```

as integer quantity merely because PHP can coerce them.

---

# 93. Validation Normalization Order

Implement deterministic preprocessing.

Conceptually:

```text
1. inspect raw field presence/types
2. normalize only fields that are valid scalar types
3. apply requiredness
4. apply formats/ranges
5. apply cross-field contact rule
6. return normalized validated payload
```

Do not normalize arrays/objects into strings.

---

# 94. Safe Laravel Preparation

If using:

```php
prepareForValidation()
```

only normalize values whose raw types are valid for that normalization.

Do not do:

```php
trim((string) $value)
```

because that converts invalid types into apparently valid strings.

---

# 95. No Silent Coercion

Examples that must remain invalid:

```json
{"name": 123}
{"email": true}
{"quantity": "2"}
{"dimensions": "large"}
```

---

# 96. Command Construction

Update RequestController or mapper so:

```text
validated normalized values
→ CreateFurnitureRequestCommand
```

No raw request values.

---

# 97. Command Field Semantics

Expected normalized values:

```text
productId: string|null
quantity: int|null
name: normalized string
phone: normalized string|null
email: normalized lowercase string|null
dimensions: normalized structure|null
material: string|null
color: string|null
notes: string|null
```

---

# 98. Actor Stays Server-Derived

Do not add actor fields to the validator.

The command's actor comes from optional authentication.

Never from request body.

---

# 99. Request Reference Stays Server-Derived

No validation input for it.

---

# 100. Request Status Stays Server-Derived

No validation input for it.

---

# 101. Error Envelope

All validation failures must use:

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

according to existing global API behavior.

Do not return Laravel's default validation structure.

---

# 102. Error Determinism

For each condition, use one stable code.

Do not alternate between:

```text
INVALID_VALUE
VALIDATION_ERROR
INVALID_REQUEST
```

for the same schema problem.

---

# 103. Error Mapping — Missing Required

Use:

```text
MISSING_REQUIRED_FIELD
```

for:

```text
missing name
empty normalized name
both contact channels absent/empty
required dimension unit missing
```

where frozen semantics classify them as requiredness errors.

---

# 104. Error Mapping — Wrong Type

Use:

```text
INVALID_TYPE
```

for:

```text
quantity string
name integer
phone array
email boolean
dimensions string
dimension numeric string
material array
color object
notes boolean
```

---

# 105. Error Mapping — Format

Use:

```text
INVALID_FORMAT
```

for structurally correct scalar values with invalid format, such as:

```text
malformed email
malformed phone
malformed product opaque id
```

provided this matches existing identifier convention.

---

# 106. Error Mapping — Invalid Value

Use:

```text
INVALID_VALUE
```

for:

```text
quantity 0
quantity 101
dimension <=0
dimension >10000
unit != cm
unknown field
unknown dimension field
too-long bounded strings
```

---

# 107. Do Not Use `INVALID_REQUEST` for Ordinary Schema Errors

Reserve:

```text
INVALID_REQUEST
```

for the Request-specific domain/state cases defined by the frozen contract.

Do not make it a catch-all FormRequest error.

---

# 108. Error Field Paths

Use exact public field paths:

```text
name
phone
email
quantity
product_id
dimensions
dimensions.length
dimensions.width
dimensions.height
dimensions.unit
material
color
notes
```

Do not expose:

```text
message
```

in validation errors.

---

# 109. Multiple Errors

Follow existing project convention on whether validation returns:

```text
first error
or
multiple errors
```

Do not create Request-specific behavior.

---

# 110. Optional Authentication Regression

Preserve:

```text
no bearer → anonymous path
valid CUSTOMER bearer → authenticated path
invalid bearer → 401 INVALID_AUTHENTICATION
STAFF → 403
ADMIN → 403
mixed-role invalid state → 403
```

Validation work must not reorder optional-auth semantics incorrectly.

---

# 111. Auth vs Validation Ordering

Preserve the current repository middleware order.

Do not accidentally allow malformed/invalid bearer requests to be processed as anonymous merely because body validation occurs first.

---

# 112. Public Mutation Abuse Protection

Preserve:

```text
throttle:anonymous-submit
```

and existing request-size/security middleware.

Do not weaken rate limiting during FormRequest integration.

---

# 113. Rate-Limit Response

Existing:

```text
429 RATE_LIMITED
Retry-After
```

must remain.

---

# 114. Route Gating

Keep:

```php
config('requests.route_enabled') === false
```

as the public default during Phase 10.2.

Tests may opt in in-process as already established.

Do not make it environment-driven unless an approved architecture change says so.

---

# 115. Why Route Remains Gated

After Phase 10.2, REQ-001 still lacks:

```text
Phase 10.4:
linked Product MADE_TO_ORDER eligibility

Phase 10.6:
frozen attachment support
```

Therefore do not claim the full frozen REQ-001 contract is public-ready.

---

# 116. No Product Database Query

Again, FormRequest must not implement:

```text
exists
active
published
MADE_TO_ORDER
```

Product rules.

Those are domain validation in 10.4.

---

# 117. Structural Product ID Tests

Test:

```text
omitted → valid
null → valid
valid prod_... → structurally valid
integer → INVALID_TYPE
array → INVALID_TYPE
malformed string → INVALID_FORMAT
```

Do not assert Product existence here.

---

# 118. Attachment Boundary

Phase 10.6 owns:

```text
multipart file
5 MB
MIME/signature
filename sanitization
storage
upload tokens
private URLs
```

Do not implement those here.

---

# 119. `attachment` in JSON

Reject JSON such as:

```json
{
  "attachment": "base64..."
}
```

Do not accept file data as JSON string.

---

# 120. Multipart Route State

Do not falsely claim multipart attachment support simply because the frozen API documents it.

Until 10.6:

```text
route remains gated
```

so no client depends on partial attachment handling.

---

# 121. No Request Lifecycle Validation

Do not implement:

```text
SUBMITTED → IN_REVIEW
IN_REVIEW → CLOSED
```

That is Phase 10.3.

---

# 122. No Staff Input Validation

Do not implement `REQ-006` operational validation in this phase.

REQ-001 only.

---

# 123. No Request Retrieval Validation

Do not implement REQ-002/003 filtering/ownership here.

---

# 124. No Enquiry Validation

Keep Furniture Request and Enquiry validation separate.

Phase 10.5 owns Enquiries.

---

# 125. Persistence Safety

Any failed validation must cause:

```text
FurnitureRequest inserts = 0
Order inserts = 0
Payment inserts = 0
inventory mutation = 0
```

---

# 126. No Service Invocation on Validation Failure

Where practical, test that invalid input does not invoke:

```text
CreateFurnitureRequest
```

or does not produce persistence.

---

# 127. Contact Snapshot Test

For authenticated Customer:

submit explicit Request contact differing from profile.

Assert persisted Request uses:

```text
submission contact
```

not profile.

---

# 128. Name Normalization Test

Input:

```text
"   Asha    Mwangi   "
```

Expected persisted/API value:

```text
"Asha Mwangi"
```

---

# 129. Email Normalization Test

Input:

```text
" ASHA@Example.COM "
```

Expected:

```text
asha@example.com
```

---

# 130. Phone Normalization Test

Use existing project-normalized expected representation.

Do not invent a new Tanzania-only parser.

---

# 131. Unicode Test

Use representative valid Unicode names/text.

Ensure validation does not corrupt characters.

---

# 132. Notes Newline Test

Meaningful newlines should survive normalization unless the established contract explicitly collapses them.

Do not flatten Request descriptions unnecessarily.

---

# 133. Quantity Boundary Tests

Test exactly:

```text
1 → valid
100 → valid
0 → invalid
101 → invalid
-1 → invalid
1.5 → invalid
"1" → invalid
```

---

# 134. Name Boundary Tests

Test:

```text
1 char valid if otherwise acceptable
120 chars valid
121 chars invalid
blank invalid
wrong type invalid
```

---

# 135. Phone/Email Matrix Tests

Required table:

```text
phone only      → valid
email only      → valid
phone + email   → valid
neither         → invalid
both null       → invalid
both blank      → invalid
invalid phone + valid email → phone still invalid; do not silently ignore it
valid phone + invalid email → email still invalid
```

If a field is supplied, it must itself be valid.

---

# 136. Dimensions Boundary Tests

For each numeric dimension:

```text
small positive >0 → valid
10000 → valid
0 → invalid
negative → invalid
10000.1 → invalid
numeric string → invalid
```

Include decimal acceptance if current frozen persistence can preserve it.

---

# 137. Dimensions Unit Tests

Test:

```text
cm → valid
CM → invalid
inch → invalid
missing unit with dimensions → invalid
unknown dimension key → invalid
```

---

# 138. Null Dimensions Test

```json
"dimensions": null
```

valid.

---

# 139. Material Boundary Tests

```text
valid Unicode/free text
500 chars valid
501 invalid
wrong type invalid
blank → normalized according to chosen nullable convention
```

---

# 140. Color Boundary Tests

```text
200 chars valid
201 invalid
wrong type invalid
```

---

# 141. Notes Boundary Tests

```text
5000 chars valid
5001 invalid
wrong type invalid
ordinary < > & quotes safe as plain data
```

---

# 142. Unknown Field Tests

Use table-driven tests for at least:

```text
message
style
product_details
user_id
request_status
staff_internal_notes
order_id
payment_status
delivery_fee
quoted_price
foo
```

---

# 143. Nested Unknown Field Tests

Use:

```text
dimensions.depth
dimensions.diameter
dimensions.units
```

Expected deterministic field paths.

---

# 144. Structural Test Without Product

A fully valid custom Request:

```text
product_id omitted/null
valid contact
other optional fields
```

must pass validation and reach the Phase 10.1 creation service.

---

# 145. Product-Linked Structural Test

A syntactically valid:

```text
prod_...
```

may pass Phase 10.2 structural validation even if the referenced Product's actual business eligibility is not checked until 10.4.

Do not mislabel that as full product-linked Request PASS.

---

# 146. No Duplicate Detection

Two identical valid requests must both be permitted by validation.

No:

```text
same email + notes → duplicate error
```

---

# 147. No Idempotency Key Requirement

Do not require:

```text
Idempotency-Key
```

for REQ-001.

If sent, follow the project's unknown/unconsumed header conventions; do not make it business authority.

---

# 148. Error Privacy

Do not expose:

```text
model class names
database columns
SQL
stack traces
message column
internal normalization exception
```

---

# 149. Logging Privacy

Validation errors must not log the full customer submission.

Especially avoid logging:

```text
phone
email
notes
Authorization token
```

---

# 150. Cache Behavior

Preserve:

```text
Cache-Control: private, no-store
Vary: Authorization
```

where Phase 10.1 established it.

---

# 151. OpenAPI Consistency Review

Compare implementation against:

```text
docs/api/api-contract.md §26
docs/api/api-resources.md §5
docs/api/api-conventions.md §26
docs/api/openapi.yaml
```

Do not change the contract merely to match convenient Laravel rules.

---

# 152. OpenAPI Drift Handling

If you discover inconsistency such as:

```text
nullable member differences
missing error enum entry
requiredness mismatch
```

do not silently alter external behavior.

Classify it explicitly.

Follow the post-freeze reconciliation process if required.

---

# 153. Important `PRODUCT_NOT_REQUESTABLE` Note

The normative Request contract specifies:

```text
IN_STOCK linked Product
→ 409 PRODUCT_NOT_REQUESTABLE
```

but the currently surfaced OpenAPI global error enum should be checked carefully before Phase 10.4.

Do not solve that domain/error-contract issue prematurely in 10.2.

Record it for Phase 10.4 if still inconsistent.

---

# 154. No Schema Change

Expected:

```text
Schema: NONE
```

---

# 155. No Dependencies

Expected:

```text
Dependencies: NONE
```

---

# 156. No Frontend

Expected:

```text
Frontend: NONE
```

---

# 157. OpenAPI

Expected:

```text
UNCHANGED
```

unless a genuine pre-existing frozen-document inconsistency is formally reconciled.

---

# 158. Focused Test File

Create/complete something like:

```text
tests/Feature/FurnitureRequestValidationApiTest.php
```

Use repository naming conventions.

Keep detailed creation-service tests from Phase 10.1 intact.

---

# 159. Suggested Test Organization

Prefer table-driven groups:

```text
unknown fields
wrong types
quantity boundaries
contact matrix
normalization
dimension boundaries
free-text bounds
server-controlled tampering
```

Avoid hundreds of near-identical hand-written methods if data providers are clearer.

---

# 160. API Validation Test — Anonymous

With route enabled in-process:

valid custom Request input should reach:

```text
201
```

provided it does not depend on Phase 10.4 or attachments.

This proves the validator integrates with Phase 10.1.

---

# 161. API Validation Test — Customer

Same for authenticated CUSTOMER.

Assert ownership remains server-derived.

---

# 162. API Validation Test — Invalid Bearer

Must remain:

```text
401 INVALID_AUTHENTICATION
```

not validation 422 and not anonymous fallback.

---

# 163. API Validation Test — Staff/Admin

Must remain:

```text
403 FORBIDDEN
```

---

# 164. Validation Failure Side Effects

For representative failures:

```text
missing name
missing all contact
quantity wrong type
unknown field
invalid dimensions
```

assert:

```text
furniture_requests count unchanged
```

---

# 165. Commerce Side Effects

Representative invalid and valid Request creation must still prove:

```text
Order count unchanged
Payment count unchanged
ProductStock unchanged
reserved_quantity unchanged
```

---

# 166. Response Mapping Regression

After valid creation:

assert:

```text
notes present
message absent
style absent
product_details absent
staff_internal_notes absent
user_id absent
```

---

# 167. Response Normalization Regression

Assert normalized:

```text
name
phone
email
dimensions
material
color
notes
```

come back as expected.

---

# 168. Model Assertions

Do not rely solely on FormRequest rules.

Existing model/domain assertions may remain as defense-in-depth for persistence invariants.

Do not duplicate error presentation logic there.

---

# 169. Direct Service Calls

Tests calling `CreateFurnitureRequest` directly should still use trusted command data.

Do not turn the service into a second HTTP validator.

---

# 170. Controller Complexity

Keep controller thin.

Validation logic belongs in:

```text
CreateFurnitureRequestRequest
normalization helper/value objects
```

not controller branches.

---

# 171. FormRequest Complexity

If `prepareForValidation()` becomes complex:

split normalization into small focused helpers/value objects.

Do not create a 200-line normalization method.

---

# 172. Cognitive Complexity

Touched/created functions:

```text
<= 15
```

---

# 173. Return Statements

Keep:

```text
<= 3 returns where practical
```

---

# 174. Semantic Constants

Reuse meaningful domain constants/enums for:

```text
cm
field sets where genuinely shared
```

Do not build a giant constants class.

---

# 175. Validation Attribute Names

Client errors must use public vocabulary.

Map:

```text
message column
```

back to:

```text
notes
```

everywhere externally.

---

# 176. No Generic Sanitizer

Avoid creating a universal:

```text
sanitizeEverything()
```

helper.

Use field-specific normalization.

---

# 177. No HTML Purifier Dependency

Not needed for Request validation.

Plain text is stored as data.

Safe display encoding belongs to rendering clients/frameworks.

---

# 178. No Phone Library Dependency Unless Already Present

Use existing normalization infrastructure.

Do not add a large package for this phase unless the current contract cannot be correctly implemented otherwise.

Expected dependency change:

```text
NONE
```

---

# 179. Documentation

Add the next backend ADR only if repository convention records each phase.

Likely topic:

```text
Furniture Request Validation Boundary
```

Inspect the current latest ADR number after BACKEND-043.

Do not assume numbering blindly.

---

# 180. ADR Should Record

Document:

```text
strict REQ-001 allow-list
name/contact requirements
normalization rules
quantity semantics
dimension schema
free-text limits
notes→message external/internal mapping
schema vs domain Product validation split
no profile fallback
canonical error mapping
route remains gated
10.4 and 10.6 dependencies
```

---

# 181. Update Group J Tracking

After successful Phase 10.2:

```text
10.1 PASS
10.2 PASS
10.3 READY
10.4 NOT STARTED
10.5 NOT STARTED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

Do not claim REQ-001 production-ready yet.

---

# 182. Phase 10.3 Readiness

Phase 10.3 may proceed independently with:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

lifecycle rules.

However public REQ-001 activation remains dependent on 10.4/10.6 completion.

---

# 183. Verification Commands

Run:

```bash
php artisan test --filter=FurnitureRequest
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Also verify:

```text
docs/api/openapi.yaml
```

still parses.

---

# 184. Route Verification

Report exact current:

```text
POST /api/v1/requests
```

middleware order, controller, route name, and:

```text
STUB/GATED
```

state.

---

# 185. Completion Report

Return:

## Phase 10.2 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Validator

Report exact class:

```text
App\Http\Requests\CreateFurnitureRequestRequest
```

or actual path.

---

## Top-Level Allow-List

Report exactly:

```text
product_id
quantity
name
phone
email
dimensions
material
color
notes
```

---

## Server-Controlled Rejections

Report representative rejected fields.

---

## Product ID Validation

Report:

```text
shape/nullable validation implemented
Product existence/type eligibility deferred to Phase 10.4
```

---

## Quantity

Report:

```text
nullable/optional
strict int
1..100
omitted remains null
```

---

## Contact

Report:

```text
name required
phone or email required
both valid
same rule Anonymous/CUSTOMER
no profile fallback
```

---

## Name

Report trim/collapse/max behavior.

---

## Phone

Report exact normalization and error mapping.

---

## Email

Report trim/lowercase/format/max behavior.

---

## Dimensions

Report:

```text
nullable/optional
allowed keys
number ranges
unit cm
unknown key rejection
decimal behavior
```

---

## Free Text

Report:

```text
material max 500
color max 200
notes max 5000
```

and blank normalization.

---

## Notes Mapping

Confirm:

```text
public notes
→ command notes
→ DB message
→ public notes
```

---

## Error Mapping

Report tested:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
```

---

## Authentication

Confirm existing:

```text
Anonymous allowed
CUSTOMER allowed
invalid bearer 401
STAFF/ADMIN 403
```

---

## Route

Expected:

```text
STUB/GATED
```

with Phase 10.4/10.6 reasons.

---

## Product Eligibility

State:

```text
NOT IMPLEMENTED IN 10.2
Phase 10.4
```

---

## Attachments

State:

```text
NOT IMPLEMENTED
Phase 10.6
```

---

## Lifecycle

State:

```text
no transition implementation
Phase 10.3
```

---

## Orders

```text
NONE
```

---

## Payments

```text
NONE
```

---

## Inventory

```text
NONE
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

or report exact documented pre-existing drift.

---

## Tests

Report:

```text
validation test count
normalization tests
type tests
bounds tests
contact matrix
dimensions tests
tampering tests
zero-side-effect tests
Phase 10.1 regressions
full suite
```

---

## Quality

Report:

```text
PHPUnit
PHPStan
Pint
composer audit
git diff --check
route:list
OpenAPI parse
```

---

## Group J Status

Return:

```text
10.1 PASS
10.2 PASS/BLOCKED
10.3 READY/BLOCKED
10.4 NOT STARTED
10.5 NOT STARTED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 186. Definition of Done

Phase 10.2 is complete when:

- REQ-001 has one dedicated complete schema validator;
- only frozen public fields are accepted;
- unknown fields are rejected;
- database-only fields are rejected;
- server-controlled fields are rejected;
- commerce/payment/order tampering fields are rejected;
- public `message` is rejected;
- `style` is rejected;
- `product_details` is rejected;
- `product_id` is optional and nullable;
- `product_id` structural format is validated;
- Product business-state lookup is not incorrectly pulled into FormRequest;
- quantity is optional/nullable strict integer 1..100;
- omitted quantity remains null;
- name is required for Anonymous and CUSTOMER;
- name is trimmed, internal whitespace normalized, max 120;
- at least one phone/email is required;
- both phone and email may be supplied;
- authenticated Customer gets no contact fallback;
- phone is normalized and bounded;
- email is trimmed/lowercased/formatted/bounded;
- supplied invalid optional contact field is not silently ignored;
- dimensions are optional/nullable;
- dimensions accept only length/width/height/unit;
- numeric dimensions are >0 and <=10000;
- numeric strings are rejected;
- unit is required when applicable;
- unit is exactly `cm`;
- unit aliases are rejected;
- unknown dimension fields are rejected;
- decimal dimension behavior is explicitly verified against persistence;
- material is optional free text max 500;
- color is optional free text max 200;
- notes is optional plain text max 5000;
- Unicode is preserved;
- notes map to internal `message`;
- validation errors use public field names;
- validation errors use canonical envelope;
- missing required fields map deterministically;
- wrong types map to INVALID_TYPE;
- invalid formats map to INVALID_FORMAT;
- invalid values map to INVALID_VALUE;
- invalid bearer never downgrades to anonymous;
- STAFF/ADMIN remain rejected;
- rate limiting remains attached;
- validation failures cause zero persistence;
- validation failures cause zero commerce side effects;
- valid custom Request passes the validator and Phase 10.1 service;
- no idempotency key is required;
- duplicate legitimate submissions remain permitted;
- Product MADE_TO_ORDER eligibility remains explicitly Phase 10.4;
- attachments remain explicitly Phase 10.6;
- public route remains gated until frozen REQ-001 is complete;
- no schema migration is introduced;
- no dependency is introduced;
- no frontend work occurs;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- full regression suite remains green.

---

# 187. Out of Scope

Do not implement:

```text
Phase 10.3 status lifecycle
Phase 10.4 Product existence/active/published/MADE_TO_ORDER eligibility
PRODUCT_NOT_REQUESTABLE behavior
Phase 10.5 Enquiries
Phase 10.6 attachment validation/storage/upload tokens
Phase 10.7 Staff/Admin request management
Phase 10.8 Group J regression closure

Request→Order conversion
quotation
pricing
inventory reservation
ClickPesa/payment
delivery logic
notifications
frontend
```

---

# 188. STOP Condition

STOP when REQ-001 input can be transformed safely and deterministically:

```text
raw request
→ strict frozen field allow-list
→ strict JSON types
→ normalized name/contact
→ contact cross-field rule
→ validated quantity
→ structured dimensions
→ bounded free text
→ normalized validated payload
→ CreateFurnitureRequestCommand
```

while preserving:

```text
Product eligibility → Phase 10.4
Attachments → Phase 10.6
Status lifecycle → Phase 10.3
Public route → still gated
```

Do not continue automatically to Phase 10.3.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 PASS — Request validation (ADR/BACKEND-044)
10.3 READY — Request status lifecycle
10.4 NOT STARTED — Product-linked requests
10.5 NOT STARTED — General enquiries
10.6 NOT STARTED — Attachment handling
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Group J is **not** complete. The REQ-001 input contract is now authoritative
(Phase 10.2), but the public `POST /api/v1/requests` route remains
**STUB/GATED** (`config('requests.route_enabled') === false`, not
environment-driven) until Phase 10.4 (linked-product eligibility) and Phase 10.6
(attachments) are satisfied.
