# Phase 10.1 — Furniture Request API

## 1. Objective

Implement the backend application/API foundation for the Made-to-Order Furniture Request domain.

Primary endpoint:

```http
POST /api/v1/requests
```

Endpoint ID:

```text
REQ-001
```

The purpose of this phase is to establish the request-creation pipeline:

```text
Anonymous / authenticated CUSTOMER
        ↓
Furniture Request API boundary
        ↓
trusted request command
        ↓
FurnitureRequest creation service
        ↓
furniture_requests persistence
        ↓
explicit Request resource
        ↓
201 Created
```

This phase is **not** the entire Group J implementation.

Do not implement Phases 10.2–10.8 early.

---

# 2. Business Context

The current initial production mode is:

```text
MADE_TO_ORDER products only
```

The primary customer conversion flow is therefore:

```text
Browse
→ MADE_TO_ORDER Product
→ Request Furniture
→ Business follow-up
```

not:

```text
Cart
→ Checkout
→ Payment
→ Order
```

The Furniture Request API is therefore a first-class production path.

However, that does not justify collapsing Requests into Orders.

---

# 3. Core Domain Principle

A Furniture Request is:

```text
customer intent / production enquiry
```

It is not:

```text
Order
Cart
Checkout
Payment
Quote
Reservation
Production commitment
Delivery commitment
```

The database and API must preserve this separation.

---

# 4. Group J Scope

Current roadmap:

```text
10.1 Furniture request API
10.2 Request validation
10.3 Request status lifecycle
10.4 Product-linked requests
10.5 General enquiries
10.6 Attachment handling if required
10.7 Staff/admin request management
10.8 Request/enquiry tests
```

Work on **10.1 only**.

---

# 5. Phase 10.1 Owns

Phase 10.1 owns the foundational REQ-001 application flow:

```text
route/controller architecture
optional-auth actor resolution
anonymous vs CUSTOMER ownership
creation command/DTO
creation service
request_reference generation
mapping frozen public fields to existing schema
default SUBMITTED persistence
explicit API resource
201 response
private/no-store response behavior
minimal persistence/integration tests
API/domain documentation decision
```

---

# 6. Phase 10.1 Does Not Own

Do not implement in this phase:

```text
complete request validation matrix        → Phase 10.2
status transitions                         → Phase 10.3
complete linked-product eligibility flow  → Phase 10.4
general enquiries                          → Phase 10.5
file upload implementation                 → Phase 10.6
staff request queue / update API           → Phase 10.7
Group J closure/regression suite           → Phase 10.8
```

Do not pull those phases forward unless a tiny prerequisite is unavoidable.

---

# 7. Frozen REQ-001 Contract

Canonical endpoint:

```http
POST /api/v1/requests
```

Do not add alternate public routes such as:

```text
/made-to-order-requests
/furniture-requests
/custom-furniture
/custom-orders
```

Canonical API resource remains:

```text
/requests
```

---

# 8. HTTP Method

Exactly:

```text
POST
```

---

# 9. Response Status

Successful creation:

```text
201 Created
```

Response envelope:

```json
{
  "data": {
    ...
  }
}
```

---

# 10. Authentication Model

REQ-001 uses:

```text
optional authentication
```

Permitted actors:

```text
Anonymous
CUSTOMER
```

Not permitted to create customer Requests through REQ-001:

```text
STAFF
ADMIN
```

Staff/Admin operate on requests through later operational endpoints.

Do not treat operational access as customer submission authority.

---

# 11. Anonymous Submission

Anonymous creation must work without requiring:

```text
account
Clerk registration
login
guest account
temporary User row
```

Persist:

```text
user_id = null
```

---

# 12. Authenticated Customer Submission

When a valid authenticated CUSTOMER sends REQ-001:

```text
user_id = authenticated local User
```

Derive ownership server-side.

Never accept ownership from request data.

---

# 13. Optional Authentication Semantics

Use the existing optional Clerk authentication boundary.

Requirements:

```text
no Authorization header
→ anonymous request allowed

valid CUSTOMER bearer
→ authenticated customer request

invalid bearer
→ reject
```

An invalid bearer must never silently downgrade to anonymous.

Preserve the hardened optional-auth behavior already established in Cart/security remediation.

---

# 14. STAFF / ADMIN Optional-Auth Rule

If optional auth resolves a local STAFF or ADMIN:

do not treat them as anonymous.

Reject REQ-001 because the normative actor matrix permits only:

```text
Anonymous
CUSTOMER
```

for customer Request creation.

Use the existing authorization/error conventions.

Do not create customer-owned Requests on behalf of staff.

---

# 15. Existing Persistence Model

Reuse:

```php
App\Models\FurnitureRequest
```

and the existing:

```text
furniture_requests
```

table.

Do not create a second Request table.

---

# 16. Existing Schema

The persistence layer already contains broadly:

```text
id
user_id
request_reference
product_id
product_details
style
name
email
phone
message
quantity
dimensions
material
color
request_status
staff_internal_notes
timestamps
```

Do not expose database structure directly as API structure.

---

# 17. Critical Schema/API Reconciliation

The frozen REQ-001 public API exposes:

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
attachment
```

It does **not** expose:

```text
message
style
product_details
staff_internal_notes
user_id
request_status as input
```

Therefore establish an explicit mapping.

---

# 18. `notes → message` Mapping

The existing database column:

```text
message
```

is the persistence home for public:

```text
notes
```

Therefore:

```text
API notes
→ FurnitureRequest.message
```

and on output:

```text
FurnitureRequest.message
→ API notes
```

Do not expose a public `message` alias.

Do not return both.

Do not rename the frozen API field.

---

# 19. `style`

`style` has no frozen REQ-001 counterpart.

For Phase 10.1:

```text
do not accept style
do not expose style
```

Do not invent it as an optional public field.

---

# 20. `product_details`

`product_details` also has no frozen REQ-001 input field.

Do not expose:

```text
product_details
```

to clients in this phase.

If later product-linking work derives an internal product snapshot, that belongs to Phase 10.4 and must not silently modify the frozen external contract.

---

# 21. `staff_internal_notes`

Never accepted during REQ-001.

Never exposed in the created/customer representation.

This field is operational and belongs to Phase 10.7.

---

# 22. `request_status`

The client does not control it.

Every successful new request begins as:

```text
SUBMITTED
```

server-side.

Rejecting a client-supplied status belongs to the validation boundary, but do not allow mass assignment now.

---

# 23. `request_reference`

Generate server-side.

Existing format:

```text
REQ- + 10-character suffix
```

Use:

```php
App\Support\ReferenceGenerator
```

Do not use:

```text
faker
random fixture helper
client-generated reference
timestamp concatenation
```

---

# 24. Public Request ID

Use the existing opaque identifier convention:

```text
req_...
```

Never expose raw numeric DB primary keys.

---

# 25. Contact Snapshot

The Request is self-contained historical intake.

Persist the explicit contact data supplied at submission time.

Do not later rewrite it when:

```text
Customer profile changes
Clerk email changes
phone changes
name changes
```

---

# 26. No Authenticated Profile Fallback

The frozen Request contract deliberately requires explicit contact snapshot even for authenticated Customers.

Do not implement:

```text
name missing → profile.name
phone missing → profile.phone
email missing → Clerk email
```

Phase 10.2 will enforce the complete contact-requiredness matrix.

The API architecture introduced now must not assume profile fallback.

---

# 27. Request Creation Service

Introduce one focused application/domain service.

Example:

```php
App\Services\Requests\CreateFurnitureRequest
```

or repository-consistent equivalent.

Its job should be roughly:

```text
accept trusted normalized command
derive ownership
generate request reference
map public fields to persistence fields
persist FurnitureRequest
return created aggregate
```

Do not put creation logic inside the controller.

---

# 28. Command / DTO

Introduce a typed immutable command, for example:

```php
CreateFurnitureRequestCommand
```

The exact class name should follow repository conventions.

Possible fields:

```text
actor/User|null
productId|null
quantity|null
name
phone|null
email|null
dimensions|null
material|null
color|null
notes|null
```

Do not put Laravel's raw Request object into the service.

---

# 29. Command Must Not Contain

Do not include client authority over:

```text
user_id
request_status
request_reference
staff_internal_notes
order_id
payment_id
delivery_fee
quoted_price
created_at
updated_at
```

---

# 30. Controller

Use or complete a dedicated controller such as:

```php
App\Http\Controllers\Api\V1\RequestController
```

or the existing repository class.

Keep it thin.

Conceptually:

```text
resolve optional actor
obtain trusted input
build command
call CreateFurnitureRequest
load required response relations
serialize Request resource
return 201
```

No business logic in route definitions.

---

# 31. Naming Collision

Be careful with:

```php
Illuminate\Http\Request
```

versus the Furniture Request domain.

Prefer explicit class naming:

```text
FurnitureRequestController
FurnitureRequestResource
CreateFurnitureRequestCommand
```

if that improves clarity.

Do not introduce confusing generic names just because the endpoint path is `/requests`.

---

# 32. Resource / Serializer

Create an explicit customer/created representation.

Example:

```php
FurnitureRequestResource
```

Do not use:

```php
return $furnitureRequest;
```

or:

```php
$furnitureRequest->toArray()
```

---

# 33. REQ-001 Created Representation

The public response must contain the frozen customer-facing fields:

```text
id
product_id
product
quantity
name
phone
email
dimensions
material
color
notes
request_status
attachments
created_at
updated_at
```

Use the actual frozen OpenAPI/resource definition as authority.

---

# 34. Product Summary

When a linked Product exists, customer representation expects a safe summary:

```text
id
name
slug
```

Do not expose:

```text
inventory internals
reserved quantity
staff fields
cost
database ids
```

---

# 35. Custom Request Product Representation

For a custom/general Request:

```text
product_id = null
product = null
```

This is valid Version 1 behavior.

---

# 36. Attachments During Phase 10.1

Phase 10.6 owns attachment implementation.

Therefore in Phase 10.1, for creation without attachment:

```json
"attachments": []
```

Do not invent file-storage infrastructure here.

---

# 37. Multipart Contract

The frozen contract eventually permits:

```text
multipart/form-data
```

for inline attachment.

Do not remove that future contract capability from OpenAPI.

But do not implement attachment storage prematurely in Phase 10.1.

If the public endpoint remains gated until Phase 10.6 for multipart support, document that clearly.

---

# 38. Route Activation Strategy

Because Phase 10.2 owns full validation and Phase 10.4 owns complete product-linked request behavior, do not rush to expose an incomplete public REQ-001 implementation.

Recommended Phase 10.1 outcome:

```text
API architecture / service / serializer implemented
creation behavior internally tested
route wiring prepared
public activation only if frozen REQ-001 behavior is fully safe
```

If current route is a `501` stub, it is acceptable to keep it gated until the next prerequisite phases complete.

---

# 39. Do Not Ship Partial Contract Behavior

Do not publicly activate an endpoint that:

```text
accepts invalid contact
accepts IN_STOCK product references unchecked
exposes schema-only fields
silently ignores tampering
claims attachment support without implementing it
```

A stub is preferable to a misleading partial V1 endpoint.

---

# 40. Phase 10.2 Boundary

Do not implement the entire validation matrix now.

However Phase 10.1 must build the architecture so Phase 10.2 can plug in a dedicated:

```php
CreateFurnitureRequestRequest
```

without rewriting the creation service.

---

# 41. Minimal Safety Boundary

Even before Phase 10.2, never pass:

```php
$request->all()
```

to the model.

No mass assignment from arbitrary client JSON.

Only explicitly mapped trusted keys may enter the command.

---

# 42. Unknown Fields

The final V1 behavior is strict rejection.

If Phase 10.1 route remains gated, exhaustive unknown-field tests can wait for 10.2.

Do not implement silent field stripping as the long-term design.

---

# 43. Phase 10.4 Boundary — Product Linking

The frozen contract allows:

```text
product_id = null
```

or a linked product.

When linked, it must eventually be:

```text
exists
active
published
publicly visible
product_type = MADE_TO_ORDER
```

That complete domain validation belongs to:

```text
Phase 10.4
```

Do not duplicate catalog logic prematurely in 10.1.

---

# 44. Product-Linked Route Safety

Until Phase 10.4 exists, do not publicly accept arbitrary `product_id` and persist it unchecked.

If the endpoint is not yet public, unit/service tests may focus on custom requests.

If existing domain/model validation already safely enforces the necessary invariant, reuse it—but do not create a competing rule.

---

# 45. IN_STOCK Products

Eventually:

```text
IN_STOCK product
→ REQ-001 product link rejected
```

because normal IN_STOCK commerce and MADE_TO_ORDER request flows remain separate.

Do not change Product type semantics.

---

# 46. Current Production Mode

Although initial production publishes only MADE_TO_ORDER products, do not hard-code:

```text
all products are MADE_TO_ORDER
```

into the Request service.

The core V1 architecture still supports IN_STOCK products later.

---

# 47. Custom Request

The contract permits no Product link:

```json
{
  "product_id": null,
  ...
}
```

or omission.

A general/custom furniture request is valid.

Do not require every request to originate from a catalog Product.

---

# 48. Quantity Semantics

Quantity is request intent.

It is **not**:

```text
inventory allocation
Order quantity
reservation
production commitment
```

No inventory action may occur.

---

# 49. Omitted Quantity

Do not default omitted quantity to:

```text
1
```

The frozen semantics are:

```text
omitted
→ null / unspecified
```

---

# 50. Dimensions

Persist as structured JSON according to the existing model.

Phase 10.2 owns exhaustive validation.

Do not flatten into free text.

---

# 51. Unit

The frozen contract eventually allows only:

```text
cm
```

Do not design a unit-conversion subsystem in Phase 10.1.

---

# 52. Material

Free text.

Do not create a Material enum/table.

---

# 53. Color

Free text.

Do not create a color taxonomy.

---

# 54. Notes

Free text persisted through:

```text
notes
→ message column
```

No HTML rendering assumptions.

No Markdown processing subsystem.

---

# 55. Request Status

New Request:

```text
SUBMITTED
```

only.

Do not implement:

```text
IN_REVIEW
CLOSED
```

transitions now.

That is Phase 10.3.

---

# 56. No Auto Lifecycle

Creation must not automatically transition to:

```text
IN_REVIEW
APPROVED
QUOTED
PRODUCING
```

---

# 57. No Auto Order Creation

Absolutely do not create:

```php
Order
OrderItem
```

from REQ-001.

---

# 58. No Inventory Effect

REQ-001 must produce:

```text
ProductStock.quantity mutation = NONE
reserved_quantity mutation = NONE
allocation creation = NONE
```

---

# 59. No Payment Effect

REQ-001 must produce:

```text
Payment row = NONE
ClickPesa call = NONE
payment URL = NONE
payment status = NONE
```

---

# 60. No Price Commitment

Do not store or return:

```text
quoted_price
price
estimated_price
subtotal
total
delivery_fee
```

as an authoritative part of Request submission.

---

# 61. No Delivery Commitment

A Request may mention customer preferences in notes, but REQ-001 does not establish:

```text
fulfillment_type
delivery fee
delivery promise
ETA
```

unless later contract explicitly adds such behavior.

---

# 62. Reference Generation

Use the existing centralized reference service.

Expected:

```text
REQ-XXXXXXXXXX
```

according to the current exact implementation.

Handle uniqueness safely.

Do not create reference-generation logic inside the controller.

---

# 63. Reference Collision

Use the existing bounded collision/retry strategy if already provided.

Do not swallow database uniqueness errors indefinitely.

---

# 64. Transaction Boundary

Request creation is low-concurrency and mostly one-row persistence.

Use a transaction only where necessary for:

```text
reference + Request persistence
```

or other atomic internal writes.

Do not introduce heavyweight locks.

---

# 65. No Idempotency Requirement

REQ-001 is intentionally:

```text
NON_IDEMPOTENT
```

in V1.

Do not require:

```text
Idempotency-Key
```

---

# 66. Duplicate Submission

Two legitimate submissions with identical:

```text
name
phone
notes
```

may result in two Requests.

Do not add a uniqueness constraint over contact/content.

---

# 67. No Duplicate Heuristics

Do not reject based on:

```text
same email
same phone
same notes
same product
same day
```

This may reject legitimate business enquiries.

---

# 68. Abuse Controls

REQ-001 is a public mutation.

It must use the existing security infrastructure for public mutation abuse control.

Verify whether a dedicated:

```text
requests create limiter
```

already exists from security remediation.

If not, record the missing attachment point/readiness for later hardening rather than inventing an arbitrary threshold inconsistent with current docs.

---

# 69. Rate Limiting

Public anonymous submission must not be unthrottled in production.

Reuse established limiter conventions.

Return:

```text
429
Retry-After
```

through the canonical API layer.

---

# 70. Pre-Auth Security

Preserve existing:

```text
IP rate limiting
body-size enforcement
HTTPS production enforcement
secure response headers
safe logging
```

---

# 71. Optional Auth Ordering

Conceptually:

```text
public security middleware
→ optional Clerk auth
→ actor classification
→ request API boundary
```

Invalid Clerk credentials must not fall through to anonymous.

---

# 72. Request Privacy

Furniture Requests contain private contact information.

Never public-cache them.

Creation response:

```text
Cache-Control: private, no-store
```

or the current equivalent private creation policy.

---

# 73. Logging

Do not dump:

```text
full request body
phone
email
notes
attachment content
bearer token
```

into ordinary logs.

Use safe request ID/domain metadata only.

---

# 74. Staff Notes Privacy

Even though the model contains:

```text
staff_internal_notes
```

REQ-001 resource must never expose it.

---

# 75. `user_id` Privacy

Customer/created Request representation does not need to expose database ownership identity.

Ownership is implicit.

Do not expose internal numeric User IDs.

---

# 76. Explicit Serialization

Do not mass serialize:

```php
FurnitureRequest::toArray()
```

to public output.

Use an allow-listed Resource.

---

# 77. Response Product Loading

Avoid N+1 patterns when later list APIs arrive.

For REQ-001, load only what is needed for one created representation.

Do not overengineer collection query abstractions yet.

---

# 78. Customer Created Response

Expected structure conceptually:

```json
{
  "data": {
    "id": "req_...",
    "product_id": null,
    "product": null,
    "quantity": null,
    "name": "Asha Mwangi",
    "phone": "+255700000001",
    "email": null,
    "dimensions": null,
    "material": null,
    "color": null,
    "notes": "Custom bookshelf request",
    "request_status": "SUBMITTED",
    "attachments": [],
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Use exact frozen field semantics.

---

# 79. `request_reference` Exposure

The database stores a `request_reference`.

Before exposing it publicly, verify whether the frozen `Request` resource actually includes it.

Do not automatically expose every persistence field.

The current frozen customer representation is authoritative.

If `request_reference` is not present there, keep it internal.

---

# 80. Existing Model Review

Before changing `FurnitureRequest`, inspect:

```text
fillable
casts
hidden
model assertions
relationships
factory states
opaque id helper
request status cast
```

Reuse existing behavior where correct.

Do not rewrite Phase 3.14.

---

# 81. Migrations

Expected:

```text
Schema changes: NONE
```

The Furniture Request schema already exists.

---

# 82. Do Not Edit Historical Migration

If a genuine mismatch is discovered:

do not casually modify the old migration.

Follow `AGENTS.md` migration-history rule.

But Phase 10.1 should not need a schema migration.

---

# 83. Status Enum

Reuse existing:

```php
RequestStatus
```

or repository equivalent.

No magic:

```text
"SUBMITTED"
```

scattered through controller/service/resource/tests if a domain enum already exists.

---

# 84. Product Type Enum

Do not duplicate:

```text
MADE_TO_ORDER
```

as arbitrary strings if the existing ProductType enum exists.

Full use belongs to Phase 10.4.

---

# 85. Opaque Identifier

Reuse existing Request identifier encoding.

Do not create a second ID format.

---

# 86. Error Handling

Use the canonical API exception renderer.

Do not expose:

```text
SQL errors
constraint names
stack traces
filesystem paths
```

---

# 87. 500 Failures

Unexpected persistence failure:

```text
canonical 500 envelope
request_id
safe logging
```

No raw exception text in client response.

---

# 88. Phase 10.1 Validation Scope

Because Phase 10.2 is explicitly “Request validation,” keep this phase disciplined.

Implement only validation necessary to:

```text
protect server-controlled fields
prevent unsafe persistence
support internal happy-path creation
```

Do not attempt to close every validation edge case here.

---

# 89. FormRequest Preparation

It is acceptable to introduce the basic dedicated class now:

```php
CreateFurnitureRequestRequest
```

if this avoids later controller rewrites.

But Phase 10.2 will own its complete contract rule matrix.

Document that distinction.

---

# 90. If Basic FormRequest Is Added

It should at least ensure the controller does not consume uncontrolled input.

Do not claim Phase 10.2 PASS.

---

# 91. Product Validation Deferral

If product-linked submission cannot be safely completed until Phase 10.4:

keep route activation gated.

Do not temporarily accept any arbitrary existing Product.

---

# 92. Anonymous Contact Validation Deferral

Similarly, do not publicly activate a creation endpoint that would persist unreachable anonymous Requests with no valid contact.

Phase 10.2 closes that boundary.

---

# 93. Recommended Activation State

At the end of Phase 10.1, expected route state:

```text
foundation implemented
public REQ-001 route still gated/stubbed
```

unless the implementation already satisfies all frozen externally observable behavior without stealing Phase 10.2/10.4 scope.

Prefer correctness over premature activation.

---

# 94. Existing Routes

Inspect `routes/api.php`.

Preserve the frozen route surface.

Do not add duplicate route names.

---

# 95. Expected Route Name

Use the current repository convention.

Example:

```text
api.requests.store
```

Do not rename an established route without reason.

---

# 96. Tests — API Foundation

Add focused Phase 10.1 tests.

Suggested:

```text
FurnitureRequestCreationTest
FurnitureRequestResourceTest
```

or repository-consistent names.

---

# 97. Test — Anonymous Creation Service

Prove:

```text
actor = null
→ user_id = null
→ Request persisted
→ SUBMITTED
→ reference generated
```

---

# 98. Test — Authenticated Customer Creation Service

Prove:

```text
CUSTOMER
→ FurnitureRequest.user_id = authenticated user's DB id
```

---

# 99. Test — Client Cannot Control Ownership

Even at basic Phase 10.1 mapping level:

```text
client user_id
```

must not reach persistence.

Full canonical validation response belongs to Phase 10.2, but no unsafe assignment may exist.

---

# 100. Test — Default Status

Every newly created Request:

```text
SUBMITTED
```

---

# 101. Test — Request Reference

Prove:

```text
generated server-side
correct REQ format
unique
```

---

# 102. Test — Notes Mapping

Mandatory regression:

```text
public notes
→ database message
→ public notes
```

No public `message`.

---

# 103. Test — Schema-Only Fields Not Exposed

Assert response omits:

```text
message
style
product_details
staff_internal_notes
user_id
numeric id
```

unless explicitly frozen.

---

# 104. Test — Request Has No Commerce Effects

After creation:

```text
Order count unchanged
Payment count unchanged
ProductStock unchanged
reserved_quantity unchanged
Cart unchanged
```

---

# 105. Test — Quantity Null Preservation

If internally creating a trusted command with no quantity:

```text
quantity = null
```

Do not auto-default to 1.

---

# 106. Test — Custom Request

A trusted creation command with:

```text
product_id = null
```

persists correctly.

This establishes the custom-request foundation.

---

# 107. Product-Linked Tests

Do not attempt the complete linked-product contract suite here.

That belongs to Phase 10.4.

Only cover persistence relationship mechanics if needed to prove the service architecture.

---

# 108. Test — Contact Snapshot Independence

For authenticated Customer:

create request.

Then modify profile.

Assert stored Request contact does not change.

---

# 109. Test — Resource

Verify:

```text
201-compatible created representation
explicit allow-list
notes mapping
attachments=[]
SUBMITTED
timestamps
opaque id
```

---

# 110. Test — Staff Fields Hidden

Explicitly assert:

```text
staff_internal_notes
```

is absent from created/customer representation.

---

# 111. Test — No Payment Fields

Assert no:

```text
payment
payment_status
delivery_fee
total
order_id
```

in response.

---

# 112. Test — No Price Fields

Assert no:

```text
price
quoted_price
estimate
```

unless the frozen resource explicitly contains something otherwise.

---

# 113. Optional Auth Tests

If routing is wired internally:

test:

```text
no bearer → anonymous context
valid CUSTOMER bearer → customer context
invalid bearer → authentication rejection
STAFF → rejected
ADMIN → rejected
```

---

# 114. Route-Gating Test

If public activation remains deferred:

add/update routing test so the repository accurately records:

```text
REQ-001 implementation present
public route still gated/stubbed
```

Do not accidentally activate it.

---

# 115. Existing Schema Tests

Run:

```text
FurnitureRequestSchemaTest
```

or current equivalent.

Do not regress Phase 3.14.

---

# 116. Catalog Regressions

If touching Product relationship code, run relevant Product model/catalog tests.

Do not modify catalog behavior.

---

# 117. Auth Regressions

If optional-auth middleware is reused/changed:

run its existing regression suite.

Avoid changing shared middleware unless necessary.

---

# 118. Security Regressions

Run applicable:

```text
rate limit
body-size
optional auth
safe logging
JSON/public mutation controls
```

when route/middleware wiring changes.

---

# 119. No Attachment Tests Yet

Do not implement:

```text
file signature
5 MB limit
upload token
private URL
storage
```

in Phase 10.1.

That is Phase 10.6.

---

# 120. No Request Retrieval Yet

Do not implement:

```text
GET /me/requests
GET /me/requests/{request}
GET /requests
GET /requests/{request}
```

unless existing stubs remain untouched.

Those workflows involve ownership/operational access and later phases.

---

# 121. No Status PATCH

Do not implement:

```text
PATCH /requests/{request}
```

business behavior.

Phase 10.3/10.7 own that.

---

# 122. No Request-to-Order Conversion

Do not implement any:

```text
approve request
convert to order
create quote
create invoice
```

workflow.

---

# 123. No Notification Yet

Do not send:

```text
email
push
in-app request notification
```

from this phase.

Notifications belong to Group R unless an already-approved minimal internal event exists.

---

# 124. No Queue Requirement

Do not introduce queues just for Furniture Request creation.

Synchronous DB creation is adequate for current business scale.

---

# 125. No CAPTCHA Yet

CAPTCHA is not automatically required.

Reuse rate limiting.

CAPTCHA remains deferred unless abuse demonstrates need.

---

# 126. Maintainability

Keep new functions:

```text
cognitive complexity <= 15
```

and:

```text
<= 3 returns where practical
```

following project quality rules.

---

# 127. Avoid Giant Controller

Creation logic belongs in service/command layers.

Controller should remain small.

---

# 128. Avoid Giant Service

If mapping becomes substantial, use a focused command/factory/mapper rather than turning `CreateFurnitureRequest` into a god object.

Do not prematurely abstract one-use trivial code.

---

# 129. Duplicate Semantic Strings

Reuse domain enums/constants for:

```text
SUBMITTED
MADE_TO_ORDER
```

where already available.

Do not create a generic `StringConstants` dump.

---

# 130. Documentation

Add the next appropriate backend ADR if repository convention requires it.

Likely concept:

```text
Furniture Request Creation Boundary
```

Do not assume ADR number; inspect the current latest ADR.

---

# 131. ADR Should Record

Document:

```text
REQ-001 architecture
anonymous + CUSTOMER optional-auth actor model
server-derived ownership
Request != Order
Request != Payment
Request != inventory reservation
request reference generation
notes → message schema mapping
style/product_details non-public
SUBMITTED default
explicit resource serialization
Phase 10.2 validation deferred
Phase 10.4 linked-product domain validation deferred
Phase 10.6 attachments deferred
route activation state
```

---

# 132. Update Group J Tracking

After successful implementation:

```text
10.1 PASS — Furniture Request API foundation
10.2 READY — Request validation
10.3 pending
10.4 pending
...
```

Do not mark Group J complete.

---

# 133. Phase 10.2 Readiness

Expected after Phase 10.1 PASS:

```text
Phase 10.2 — READY
```

Phase 10.2 should be able to take the API foundation and implement the complete frozen validation matrix without restructuring persistence.

---

# 134. Schema Changes

Expected:

```text
NONE
```

---

# 135. Dependencies

Expected:

```text
NONE
```

---

# 136. Frontend

Expected:

```text
NONE
```

---

# 137. OpenAPI

Expected:

```text
UNCHANGED
```

because this phase implements the frozen REQ-001 contract rather than changing it.

---

# 138. Verification Commands

Run at minimum:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Verify OpenAPI still parses.

---

# 139. Focused Test Command

Also run the focused Request tests directly before the full suite.

Use actual filenames/classes introduced.

---

# 140. Route Verification

Inspect:

```text
POST /api/v1/requests
```

and report:

```text
route name
middleware stack
controller target
ACTIVE or STUB/GATED
```

---

# 141. Completion Report

Return:

## Phase 10.1 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Endpoint

Report:

```text
REQ-001
POST /api/v1/requests
```

---

## Route state

State:

```text
ACTIVE
```

or:

```text
STUB/GATED
```

with reason.

---

## Actor model

Report:

```text
Anonymous → allowed
CUSTOMER → allowed
invalid bearer → rejected
STAFF → rejected
ADMIN → rejected
```

as implemented.

---

## Controller

Report exact class.

---

## Command / DTO

Report exact class and fields.

---

## Creation service

Report exact class.

---

## Ownership

Report:

```text
anonymous → user_id null
CUSTOMER → server-derived user_id
client cannot set user_id
```

---

## Reference

Report:

```text
REQ- + suffix
ReferenceGenerator reused
```

---

## Status

Report:

```text
SUBMITTED server-side
```

---

## Schema mapping

Explicitly report:

```text
public notes → database message
style → not public
product_details → not public
staff_internal_notes → not public
```

---

## Resource

Report exact serializer and fields.

---

## Product linking

State:

```text
full MADE_TO_ORDER linked-product domain validation
= deferred to Phase 10.4
```

unless already safely reused without expanding scope.

---

## Validation

State exactly what Phase 10.1 implements and what remains for 10.2.

Do not claim Phase 10.2 complete.

---

## Attachments

Report:

```text
NOT IMPLEMENTED
Phase 10.6
```

unless pre-existing infrastructure is merely represented as empty metadata.

---

## Orders

Report:

```text
created = NONE
```

---

## Payments

Report:

```text
created = NONE
provider calls = NONE
```

---

## Inventory

Report:

```text
quantity mutation = NONE
reserved mutation = NONE
allocations = NONE
```

---

## Price / quote

Report:

```text
NONE
```

---

## Tests

Report focused tests and full suite totals.

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

## Group J state

Return:

```text
10.1 PASS/BLOCKED
10.2 READY/BLOCKED
10.3 NOT STARTED
10.4 NOT STARTED
10.5 NOT STARTED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 142. Definition of Done

Phase 10.1 is complete when:

- the Furniture Request API creation architecture exists;
- REQ-001 has one canonical backend creation path;
- optional authentication can distinguish anonymous from authenticated CUSTOMER;
- invalid bearer does not downgrade to anonymous;
- STAFF/ADMIN are not treated as customer creators;
- ownership is server-derived;
- anonymous Requests persist `user_id=null`;
- authenticated Customer Requests persist the authenticated User ID;
- client-controlled ownership is impossible;
- Request references are generated centrally;
- new Requests start `SUBMITTED`;
- public `notes` maps deliberately to database `message`;
- `style` is not silently added to the public API;
- `product_details` is not silently added to the public API;
- `staff_internal_notes` is not exposed;
- the customer/created representation is explicitly serialized;
- opaque `req_...` IDs are used publicly;
- no Order is created;
- no Payment is created;
- no inventory reservation occurs;
- no inventory quantity changes;
- no authoritative price/quote is created;
- duplicate submissions are not incorrectly deduplicated;
- request creation does not require `Idempotency-Key`;
- private customer/contact data is not publicly cached;
- sensitive request data is not dumped into logs;
- existing Request schema is reused;
- no historical migration is rewritten;
- no frontend work is introduced;
- no payment work is introduced;
- Phase 10.2 can add full validation without rewriting the creation service;
- Phase 10.4 can add linked-product eligibility without a second creation workflow;
- Phase 10.6 can add attachments without redesigning the Request resource;
- tests cover the foundational creation invariants;
- full backend regression suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- `git diff --check` passes.

---

# 143. Out of Scope

Do not implement:

```text
Phase 10.2 full validation matrix
Phase 10.3 status transition service
Phase 10.4 complete product-linked eligibility
Phase 10.5 enquiries
Phase 10.6 attachment upload/storage
Phase 10.7 staff/admin request queue
Phase 10.8 Group J closure tests

Request→Order conversion
quotation workflow
pricing
ClickPesa
payment initiation
delivery fee
inventory reservation
production scheduling
manufacturing workflow
notifications
frontend
```

---

# 144. STOP Condition

STOP when the repository has one clean Furniture Request creation architecture:

```text
Anonymous/CUSTOMER
→ optional-auth actor resolution
→ trusted creation command
→ FurnitureRequest creation service
→ server-derived ownership
→ REQ reference
→ SUBMITTED Request
→ explicit customer resource
→ 201-ready outcome
```

with:

```text
no Order
no Payment
no inventory reservation
no price commitment
```

and with the frozen schema/API reconciliation explicitly preserved:

```text
API notes
→ DB message

style
→ internal/not public

product_details
→ internal/not public
```

Do not continue automatically to Phase 10.2.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.

---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 READY — Request validation
10.3 NOT STARTED — Request status lifecycle
10.4 NOT STARTED — Product-linked requests
10.5 NOT STARTED — General enquiries
10.6 NOT STARTED — Attachment handling
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Group J is **not** complete. Phase 10.1 implemented the creation architecture
but the public `POST /api/v1/requests` route remains **STUB/GATED**
(`config('requests.route_enabled') === false`, not environment-driven) until
Phase 10.2 (validation), Phase 10.4 (linked-product eligibility), and Phase 10.6
(attachments) are satisfied.