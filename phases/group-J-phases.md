# Phase 10.5 — General Enquiries

## 1. Objective

Implement the Version 1 **General Enquiry domain and ENQ-001 creation pipeline**:

```http
POST /api/v1/enquiries
```

Operation:

```text
ENQ-001
```

The purpose is to support private general-business communication such as:

```text
"Do you deliver to Dodoma?"
"What are your opening hours?"
"Can I ask about this sofa?"
"I have a question about my Order."
```

This phase must keep General Enquiries completely separate from:

```text
Furniture Requests
Orders
Payments
Inventory
Checkout
```

Do not merge Request and Enquiry concepts.

---

# 2. Current Group J State

Treat the current state as:

```text
10.1 PASS — Furniture Request API
10.2 PASS — Request validation
10.3 PASS — Request status lifecycle
10.4 PASS — Product-linked Requests
10.5 CURRENT — General Enquiries
10.6 NOT STARTED — Attachment handling
10.7 NOT STARTED — Staff/Admin Request management
10.8 NOT STARTED — Request/Enquiry tests
```

Phase 10.5 owns the General Enquiry intake foundation.

Do not implement the whole Enquiry operational subsystem prematurely.

---

# 3. Existing Enquiry Persistence

Inspect and reuse the existing:

```php
App\Models\Enquiry
```

and existing:

```text
enquiries
```

table from the Group C schema.

Do not create a second Enquiry table.

Do not modify historical migrations unless a real schema defect is found.

Expected:

```text
Schema changes = NONE
```

---

# 4. Existing Enquiry Schema

The current persistence model already contains the Enquiry domain established earlier.

Inspect the exact implementation, expected broadly:

```text
id
user_id
product_id
order_id
enquiry_reference
name
email
phone
subject
message
category
enquiry_status
staff_internal_notes
created_at
updated_at
```

Use actual repository fields as authority.

Do not expose persistence columns automatically as the API.

---

# 5. Enquiry Is Not Furniture Request

This separation is mandatory.

Furniture Request intent:

```text
"Can you make/build/customize this furniture?"
```

General Enquiry intent:

```text
"I want information / help / clarification."
```

Do not automatically convert:

```text
Enquiry → FurnitureRequest
FurnitureRequest → Enquiry
```

Do not share persistence rows.

Do not infer intent from free text.

---

# 6. Enquiry Is Not Order

ENQ-001 must never:

```text
create Order
create OrderItem
reserve stock
consume stock
create Payment
create quote
create invoice
set delivery fee
initiate ClickPesa
```

An Enquiry may reference an existing Order as context, but that is only a relationship.

---

# 7. Canonical Endpoint

Implement:

```http
POST /api/v1/enquiries
```

Do not introduce aliases such as:

```text
/contact
/contact-us
/messages
/support
/questions
```

The frozen V1 resource is:

```text
/enquiries
```

---

# 8. Actor Model

ENQ-001 allows:

```text
Anonymous
CUSTOMER
```

It does not allow customer-style submission by:

```text
STAFF
ADMIN
```

Staff/Admin use later operational Enquiry endpoints.

Follow the same optional-auth safety principles used in REQ-001:

```text
no bearer → anonymous
valid CUSTOMER bearer → authenticated Customer
invalid bearer → reject, never downgrade
STAFF → reject
ADMIN → reject
```

---

# 9. Authentication Middleware

Reuse:

```text
clerk.optional
```

or the current canonical optional-auth middleware.

Do not implement another token parser.

---

# 10. Staff/Admin Submission Guard

Reuse the customer-submission actor middleware if semantically appropriate.

If the current:

```text
customer-submission
```

middleware is generic enough to protect both REQ-001 and ENQ-001, reuse it.

Do not duplicate role checks unnecessarily.

If its naming/behavior is Request-specific, introduce the smallest reusable actor boundary rather than copy-paste authorization.

---

# 11. Critical Contact Difference From Requests

Furniture Requests require explicit contact even for authenticated Customers.

Enquiries are different.

### Anonymous

Must provide:

```text
name
AND
phone OR email
```

### Authenticated CUSTOMER

May omit:

```text
name
phone
email
```

because trusted account/profile data may be used to derive missing contact fields.

This distinction is frozen.

Do not reuse `CreateFurnitureRequestRequest` contact rules wholesale.

---

# 12. Authenticated Contact Derivation

For authenticated Customer:

```text
explicit body value if supplied
otherwise trusted local profile/account value
```

Use trusted server-side sources.

Do not trust body ownership.

---

# 13. Contact Snapshot Principle

Even when values are derived:

persist a historical Enquiry contact snapshot.

Later changes to:

```text
Customer profile name
phone
email
Clerk identity
```

must not rewrite the old Enquiry.

---

# 14. Contact Precedence

Use deterministic precedence:

```text
explicit valid submitted contact
→ preferred

otherwise trusted authenticated profile/account fallback
```

Do not overwrite a deliberately supplied valid contact with profile data.

---

# 15. Email Source

Inspect the current local identity model carefully.

Email may be Clerk-owned rather than Laravel profile-owned.

Use the existing trusted authenticated identity projection already available to the backend.

Do not perform a live Clerk API call during Enquiry creation unless current architecture already requires one.

Prefer locally available trusted identity context.

---

# 16. Missing Authenticated Contact

Authenticated Customer must still end with:

```text
name present
AND
at least one reachable phone/email
```

after supplied + derived values are combined.

If no valid reachable contact exists, reject.

Do not create an unreachable Enquiry.

---

# 17. Anonymous Contact

Anonymous has no fallback.

Require body:

```text
name
phone and/or email
```

---

# 18. ENQ-001 Request Allow-List

Accept exactly:

```text
name
phone
email
subject
message
category
product_id
order_id
```

for JSON Enquiry creation.

Attachment is handled later in Phase 10.6.

---

# 19. Strict Unknown-Field Rejection

Reject unknown fields.

Examples:

```text
user_id
enquiry_status
staff_internal_notes
request_id
request_status
dimensions
material
color
quantity
order_status
payment_status
delivery_fee
quoted_price
price
created_at
updated_at
```

Do not silently strip them.

---

# 20. Requests-Specific Fields Are Invalid

Explicitly reject:

```text
dimensions
material
color
quantity
notes
```

as Enquiry fields.

Enquiries use:

```text
subject
message
```

Do not blur domain boundaries.

---

# 21. `subject`

Required for every actor.

Rules:

```text
string
trimmed
plain text
minimum 5
maximum 200
```

Do not derive it.

---

# 22. Subject Wrong Type

Reject:

```text
number
boolean
array
object
null
```

with:

```text
422 INVALID_TYPE
field: subject
```

where appropriate.

---

# 23. Subject Missing / Blank

Use:

```text
422 MISSING_REQUIRED_FIELD
field: subject
```

for absent or normalized blank required subject according to existing API conventions.

---

# 24. Subject Too Short / Long

Correct type but outside:

```text
5..200
```

→:

```text
422 INVALID_VALUE
field: subject
```

Do not truncate.

---

# 25. `message`

Required for all Enquiries.

Rules:

```text
string
trimmed outer whitespace where appropriate
plain text
minimum 10
maximum 5000
Unicode-safe
meaningful newlines preserved
```

---

# 26. Plain Text Only

Message is not interpreted as:

```text
HTML
Markdown
template
SQL
code
filesystem path
```

Do not render/sanitize it destructively in the backend.

Store as untrusted plain text.

Frontend escaping protects rendering.

---

# 27. Do Not Strip Ordinary Markup-Like Characters

Input such as:

```text
"<table>"
"5 > 3"
"&"
```

is legitimate plain text.

Do not destructively rewrite content simply because it resembles markup.

---

# 28. Message Missing / Blank

Return canonical required-field error.

---

# 29. Message Wrong Type

Use:

```text
INVALID_TYPE
```

---

# 30. Message Length

Valid:

```text
10..5000
```

Reject outside range with:

```text
INVALID_VALUE
```

---

# 31. `category`

Optional and nullable.

Use existing:

```php
App\Support\EnquiryCategory
```

from the Group C reconciliation.

Closed values:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
```

---

# 32. Category Exactness

Reject:

```text
general
Product
SHIPPING
PAYMENT
ORDER
SUPPORT
```

No aliases.

---

# 33. Category Wrong Type

Use:

```text
INVALID_TYPE
```

---

# 34. Unknown Category

Use:

```text
INVALID_VALUE
field: category
```

---

# 35. Product Association

`product_id` is optional/nullable.

Unlike Furniture Requests, Enquiries may reference:

```text
IN_STOCK
or
MADE_TO_ORDER
```

as long as the Product is currently public.

Do not reuse the `RequestableProductResolver` because it deliberately rejects `IN_STOCK`.

---

# 36. Reuse Public Product Visibility Authority

Use:

```php
Product::query()->public()
```

or current authoritative public scope from Phase 10.4/Group E.

Product must be:

```text
active
published
not soft-deleted
active Category
```

---

# 37. Enquiry Product Resolver

Introduce a separate focused component, e.g.:

```php
App\Services\Enquiries\PublicEnquiryProductResolver
```

or equivalent.

Do not generalize `RequestableProductResolver` into a giant polymorphic business engine unless very small refactoring cleanly exposes a shared public-visibility lookup.

---

# 38. Product Type Is Irrelevant for Enquiries

Both:

```text
IN_STOCK
MADE_TO_ORDER
```

are valid Enquiry context.

Do not return:

```text
PRODUCT_NOT_REQUESTABLE
```

for Enquiries.

---

# 39. Product ID Shape

Validate:

```text
optional
nullable
strict string
prod_... opaque format
```

using existing ProductIdentifier logic.

No numeric ID fallback.

No slug fallback.

---

# 40. Product Missing / Hidden

For:

```text
unknown
inactive
unpublished
soft-deleted
inactive Category
```

use one safe public-not-found mapping.

Prefer the same established mapping already used by public Product lookup:

```text
404 RESOURCE_NOT_FOUND
```

or current canonical Product-not-found code.

Do not leak hidden state.

---

# 41. No Inventory Dependency

Enquiry Product association requires no:

```text
ProductStock
Variant
available quantity
reservation
warehouse
```

---

# 42. `order_id`

Optional/nullable.

This is not an Order creation field.

It means:

```text
"This enquiry concerns this existing Order."
```

---

# 43. Order ID Shape

Validate:

```text
optional
nullable
strict string
ord_... opaque identifier
```

using existing:

```text
OrderIdentifier
```

or current identifier service.

No numeric DB ID.

---

# 44. Anonymous `order_id`

Anonymous ENQ-001 must not be able to reference arbitrary Orders.

Without a separately approved server-issued scoped Order-access token:

```text
anonymous + order_id supplied
→ 422 INVALID_VALUE
field: order_id
```

Do not attempt ownership by:

```text
email
phone
order reference knowledge
```

---

# 45. No Scoped Order Token Yet

If no scoped anonymous order-access mechanism exists in the repository:

do not invent one in Phase 10.5.

The correct V1 behavior is:

```text
anonymous order_id → reject
```

---

# 46. Authenticated Order Association

Authenticated Customer may supply `order_id` only if the Order belongs to them.

Ownership is authoritative.

---

# 47. Order Ownership Resolver

Introduce/reuse a focused ownership-safe resolver.

Possible:

```php
OwnedOrderResolver
```

or:

```php
EnquiryOrderResolver
```

Use existing Order ownership query patterns where available.

---

# 48. Ownership Failure Must Be Masked

Customer A supplying Customer B's `order_id`:

```text
404
```

not:

```text
403
```

Do not reveal cross-customer Order existence.

---

# 49. Unknown Order

Unknown and not-owned should return the same masked response family.

Use current:

```text
ORDER_NOT_FOUND
RESOURCE_NOT_FOUND
```

according to the established registry.

Do not introduce a different response for ownership failure.

---

# 50. Order Reference Is Not Accepted

Input remains:

```text
order_id
```

not:

```text
order_reference
```

unless the frozen contract explicitly supports both.

Do not add alias parsing.

---

# 51. Product + Order Together

Both may be supplied.

Example:

```text
"I have a question about the sofa on Order OD-..."
```

Do not force one or the other.

---

# 52. Neither Product Nor Order Required

General Enquiry:

```text
product_id = null
order_id = null
```

is valid.

---

# 53. Association Does Not Mutate Linked Resources

Enquiry creation must never modify:

```text
Product
Order
Order status
Order totals
Payment
Inventory
Delivery
```

---

# 54. Enquiry Ownership

Authenticated Customer:

```text
Enquiry.user_id = authenticated local User
```

Anonymous:

```text
Enquiry.user_id = null
```

Never accept:

```text
user_id
```

from body.

---

# 55. Initial Enquiry Status

Every new Enquiry begins:

```text
OPEN
```

Use existing closed enum/support type.

Do not accept `enquiry_status` from client.

---

# 56. Status Values

Frozen Enquiry status is:

```text
OPEN
CLOSED
```

Do not add:

```text
SUBMITTED
IN_REVIEW
ASSIGNED
WAITING_FOR_CUSTOMER
RESOLVED
ESCALATED
```

---

# 57. No Lifecycle Work Beyond Creation

Phase 10.5 should ensure:

```text
new Enquiry → OPEN
```

Do not implement the complete ENQ-006 close/reopen operational API unless Group J roadmap/current docs explicitly assign it here.

Keep operational staff management for later Group J work.

---

# 58. Reopen Ambiguity

The frozen docs state reopen is only valid if explicitly approved.

Do not make a new business decision in 10.5.

If no approved reopen behavior exists in current repository decisions:

```text
do not implement reopen
```

Record it as deferred/closed-terminal behavior for later operational phase.

---

# 59. Enquiry Reference

Inspect the schema.

If Enquiry has:

```text
enquiry_reference
```

use existing `ReferenceGenerator`.

Do not invent format.

Verify current approved prefix/length before implementation.

If it is already established, reuse exactly.

---

# 60. Opaque Enquiry ID

Use existing:

```text
enq_...
```

identifier convention.

Never expose numeric DB id.

---

# 61. Enquiry Creation Command

Introduce a typed immutable command such as:

```php
App\Services\Enquiries\CreateEnquiryCommand
```

Fields conceptually:

```text
actor
name
phone
email
subject
message
category
productId
orderId
```

or preferably resolved Product/Order references where architecture supports it.

---

# 62. Normalized Enquiry Input

Introduce a readonly value object if helpful:

```php
EnquiryInput
```

mirroring the successful Request pattern.

Avoid passing arbitrary arrays through multiple layers.

---

# 63. Creation Service

Create:

```php
App\Services\Enquiries\CreateEnquiry
```

or repository-consistent equivalent.

Responsibilities:

```text
derive contact for authenticated Customer
resolve Product context
resolve/authorize Order context
generate reference if applicable
persist Enquiry
set OPEN
return created aggregate
```

---

# 64. Keep Service Focused

Do not put raw HTTP validation in `CreateEnquiry`.

Schema validation belongs to the request boundary.

Domain association/ownership checks belong to focused resolvers/services.

---

# 65. Dedicated FormRequest

Create:

```php
App\Http\Requests\CreateEnquiryRequest
```

or equivalent.

It should own:

```text
strict top-level allow-list
type validation
basic normalization
anonymous/auth-aware contact contract where actor context is needed
subject/message validation
category validation
opaque Product/Order ID shape
```

---

# 66. Validation Architecture

Because REQ-001 already uses explicit validation for strict JSON typing/exact codes, consider following the same approach where it keeps behavior consistent.

Do not mechanically use Laravel rule strings if they cause:

```text
numeric string coercion
ambiguous error codes
unknown-field leakage
```

---

# 67. FormRequest and Actor Context

Enquiry contact requiredness depends on authenticated actor.

That means validation may need access to the resolved optional-auth Customer.

Keep that dependency narrow.

Do not query Clerk directly from the FormRequest.

---

# 68. Validation Ordering

Preferred effective ordering:

```text
security middleware
→ optional authentication
→ actor classification
→ schema validation
→ trusted-contact derivation
→ domain associations
→ persistence
```

---

# 69. Explicit Contact Snapshot Builder

Strongly consider one focused service/value object:

```php
EnquiryContactSnapshotFactory
```

or equivalent.

Input:

```text
actor|null
submitted name/phone/email
trusted profile/account context
```

Output:

```text
normalized name
normalized phone|null
normalized email|null
```

---

# 70. Do Not Reuse Furniture Request Contact Logic Blindly

The contact contracts differ.

You may reuse lower-level primitives:

```text
PhoneNumber
name normalization
email normalization
```

but not Furniture Request requiredness logic.

---

# 71. Anonymous Name

Required.

Rules:

```text
string
trimmed
internal whitespace normalized
max 120
non-empty
```

---

# 72. Authenticated Name

Optional in body.

If supplied:

```text
validate + normalize
```

If omitted:

```text
derive from trusted profile/account
```

If neither submitted nor derivable:

reject.

---

# 73. Anonymous Phone/Email

At least one required.

Both may be supplied.

---

# 74. Authenticated Phone/Email

Either may be:

```text
submitted
derived
```

At least one reachable channel must exist in the final contact snapshot.

---

# 75. Supplied Invalid Contact Must Still Fail

Example:

Authenticated Customer has valid stored email but supplies:

```text
email = "bad-email"
```

Do not silently ignore it and fall back.

Supplied data must itself validate.

---

# 76. Phone

Reuse:

```php
App\Support\PhoneNumber
```

introduced/reused in Phase 10.2.

Do not add a second normalizer.

---

# 77. Email

Rules:

```text
trim
lowercase
max 255
valid format
```

---

# 78. Subject/Message Are Historical

Once Enquiry is created:

```text
subject
message
```

are immutable customer-intake history.

Do not create a generic edit endpoint.

---

# 79. Category Is Historical

Likewise preserve submitted:

```text
category
```

unless later operational contract explicitly allows staff recategorization.

Current frozen text treats customer submission fields as historical.

Do not invent mutable triage category behavior.

---

# 80. Product/Order Links Are Historical Context

Do not silently relink later.

---

# 81. Later Product Changes

After Enquiry creation:

```text
Product unpublished
Product renamed
Product type changes
```

must not invalidate the Enquiry.

Do not re-run public Product eligibility during later Enquiry status operations.

---

# 82. Later Order Changes

After creation:

```text
Order status changes
Order cancelled
Order completed
```

do not remove the historical Enquiry relationship.

---

# 83. Enquiry Resource

Create:

```php
App\Http\Resources\EnquiryResource
```

or reuse existing if already present.

Never return:

```php
$enquiry->toArray()
```

---

# 84. Created/Customer Representation

Expose the frozen customer-safe fields:

```text
id
name
email
phone
subject
message
category
product_id
product
order_id
order
enquiry_status
attachments
created_at
updated_at
```

Use exact current OpenAPI/resource definition.

---

# 85. Product Summary

When linked:

```text
{id, name, slug}
```

only.

---

# 86. Order Summary

When linked:

```text
{id, order_reference, status}
```

only.

Do not expose:

```text
totals
payment details
delivery address
customer id
billing address
```

through the Enquiry resource.

---

# 87. Attachments

Until Phase 10.6:

```text
attachments = []
```

in the resource if that matches current Request staging architecture.

Do not implement file storage here.

---

# 88. Staff Internal Notes

Never expose:

```text
staff_internal_notes
```

in ENQ-001 response.

---

# 89. Internal Ownership

Do not expose numeric:

```text
user_id
product_id internal FK
order_id internal FK
```

Public IDs must be opaque.

---

# 90. Cache-Control

Enquiry contains private communication.

Use:

```text
Cache-Control: private, no-store
```

and existing:

```text
Vary: Authorization
```

where appropriate.

Never public-cache.

---

# 91. No SEO Exposure

Do not embed Enquiry content in public catalog resources.

---

# 92. Public Route Gating

Like REQ-001, ENQ-001 should remain gated until attachment support is completed if the frozen endpoint promises inline multipart attachment capability.

Recommended Phase 10.5 outcome:

```text
ENQ-001 implementation complete for JSON/no attachment
route STUB/GATED
```

until Phase 10.6.

---

# 93. Enquiry Feature Gate

Introduce/reuse a feature gate consistent with Requests.

Possible:

```php
EnsureEnquiriesEnabled
```

and:

```php
config('enquiries.route_enabled')
```

if current routes already expect this.

Do not invent environment-driven partial production activation unless approved.

---

# 94. Route Middleware

Expected conceptual order:

```text
api
→ clerk.optional
→ customer-submission
→ enquiries.enabled
→ throttle:anonymous-submit
```

Use actual repository conventions.

---

# 95. Anonymous Submission Limiter

Reuse the public anonymous submission limiter if designed for both Request and Enquiry intake.

Do not create arbitrary new rate thresholds without contract/security basis.

---

# 96. Invalid Bearer

Still:

```text
401 INVALID_AUTHENTICATION
```

Never downgrade.

---

# 97. Staff/Admin

Still:

```text
403 FORBIDDEN
```

for ENQ-001.

---

# 98. No Idempotency-Key

ENQ-001 is not inherently idempotent.

Do not require:

```text
Idempotency-Key
```

Duplicate submission may create two Enquiries.

---

# 99. No Content-Based Deduplication

Never create a uniqueness rule such as:

```text
email + message
phone + subject
user + subject + day
```

Repeated legitimate questions are possible.

---

# 100. Creation Status

Persist:

```text
OPEN
```

server-side.

---

# 101. No Staff Lifecycle Endpoint Yet

Do not implement full:

```text
ENQ-004
ENQ-005
ENQ-006
```

unless the roadmap/current phase explicitly requires them.

Phase 10.5 is the General Enquiry domain foundation/intake.

Operational queues belong with later staff-management work/10.8 closure as appropriate.

---

# 102. No Customer Retrieval Yet

Do not automatically implement:

```text
GET /me/enquiries
GET /me/enquiries/{enquiry}
```

unless current Group J plan explicitly assigns them to 10.5.

Keep phase scope focused on creation/domain.

---

# 103. Order Ownership Query

Reuse existing ownership semantics from Order endpoints.

Do not write:

```text
Order::find(id)
then compare loosely
```

if an ownership-safe query helper exists.

---

# 104. Order IDOR Protection

Mandatory:

```text
Customer A → Customer B order_id
→ 404 masked
```

Do not return 403.

Do not expose Order existence.

---

# 105. Anonymous Order Probe Protection

Anonymous + any non-null order ID:

```text
422 INVALID_VALUE
field: order_id
```

unless future scoped token infrastructure exists.

Do not perform Order lookup first if it would permit probing.

---

# 106. Product Visibility Probe Protection

Malformed Product ID:

```text
422 INVALID_FORMAT
```

without Product query.

Valid-looking hidden/missing Product:

```text
canonical 404
```

without hidden-state detail.

---

# 107. Product Association Any Type

Tests must prove:

```text
public IN_STOCK → valid
public MADE_TO_ORDER → valid
```

This is a major regression guard against accidental reuse of Request eligibility.

---

# 108. Product Zero Stock

Still valid Enquiry context.

No stock requirement.

---

# 109. Order Association Does Not Require Active Commerce

A Customer may enquire about an existing owned Order regardless of normal workflow state unless the frozen contract says otherwise.

Do not restrict to:

```text
PENDING_PAYMENT
ACTIVE
open orders
```

without contract support.

---

# 110. Order Ownership, Not Order Status

The primary domain check is:

```text
exists + owned
```

not:

```text
currently editable
currently payable
```

---

# 111. No Order Mutation

Association must not:

```text
change order status
create status history
cancel order
reopen order
set staff notes
```

---

# 112. No Notification Yet

Although the frozen Notification contract identifies:

```text
NEW_ENQUIRY
```

for staff, Group R owns notification implementation.

Do not create Notification rows in Phase 10.5 unless current roadmap explicitly moved that scope.

---

# 113. No Email

Do not send emails.

Group R.

---

# 114. No Queue

Do not introduce async queues just for Enquiry intake.

---

# 115. No CAPTCHA

Continue rate limiting.

CAPTCHA remains deferred unless abuse warrants it.

---

# 116. Schema/API Validation

Strict input allow-list:

```text
name
phone
email
subject
message
category
product_id
order_id
```

Attachment deferred.

---

# 117. Server-Controlled Rejections

Reject:

```text
user_id
enquiry_status
staff_internal_notes
enquiry_reference
id
created_at
updated_at
```

---

# 118. Commerce Tampering

Reject:

```text
order_status
payment
payment_status
payment_id
delivery_fee
subtotal
total
price
currency
```

---

# 119. Request-Domain Tampering

Reject:

```text
request_id
request_status
dimensions
material
color
quantity
notes
```

---

# 120. Validation Error Mapping

Use existing canonical envelope.

Typical:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
RESOURCE_NOT_FOUND
```

Do not create generic `VALIDATION_ERROR`.

---

# 121. Anonymous Missing Name

```text
422 MISSING_REQUIRED_FIELD
field: name
```

---

# 122. Anonymous Missing Contact

Use deterministic field mapping according to frozen contract, preferably:

```text
field: phone
```

if that is current convention.

---

# 123. Authenticated Contact Resolution Failure

If after supplied + derived data there is no reachable contact:

return deterministic validation/domain error.

Document exact mapping.

---

# 124. Subject Errors

Use public field:

```text
subject
```

---

# 125. Message Errors

Use:

```text
message
```

---

# 126. Category Errors

Use:

```text
category
```

---

# 127. Product Errors

Use:

```text
product_id
```

---

# 128. Order Errors

Use:

```text
order_id
```

---

# 129. `INVALID_ENQUIRY`

The frozen contract recognizes an Enquiry-specific business error family.

Do not use it as a catch-all for ordinary schema validation.

Use it only where current normative contract actually calls for Enquiry-specific invalid state/business rules.

---

# 130. Error Registry Review

Before implementing, inspect:

```text
ApiErrorCode
openapi global error enum
api-contract §15.15
§27.19
```

If an already-frozen Enquiry error code is missing, reconcile it exactly as Phase 10.4 did with `PRODUCT_NOT_REQUESTABLE`.

Do not invent new codes unnecessarily.

---

# 131. Plain-Text Safety Test

Persist input containing:

```text
<script>alert(1)</script>
```

as literal text if within limits.

Do not execute, interpret, or transform it into behavior.

The backend should treat it as text.

---

# 132. Subject Newlines

Inspect frozen semantics.

If subject is intended as one short triage line, normalize/reject newlines consistently.

Do not guess.

Use current docs/OpenAPI tests if available.

---

# 133. Message Newlines

Preserve meaningful newlines.

---

# 134. Unicode

Preserve valid Unicode in:

```text
name
subject
message
```

---

# 135. Contact Snapshot Independence

Authenticated Customer:

```text
create Enquiry
change profile
```

Stored Enquiry contact must remain unchanged.

---

# 136. Explicit Contact Overrides Profile

Customer has:

```text
profile phone = A
```

submits:

```text
phone = B
```

Persist:

```text
B
```

assuming valid.

---

# 137. Partial Derived Contact

Example:

```text
submitted name
profile email
no submitted phone/email
```

Final snapshot may use submitted name + derived email.

Test mixed-source contact.

---

# 138. Invalid Supplied + Valid Derived

Example:

```text
submitted invalid email
valid profile email
```

Reject the invalid submission.

Do not silently overwrite it with profile email.

---

# 139. Product + Order Resource Loading

Load only safe summaries needed for the created response.

Avoid N+1-style over-fetch.

One creation response does not require catalog detail graphs.

---

# 140. Historical Product Relationship

Do not make later Enquiry serialization dependent on Product still being public.

Eligibility is checked at creation.

---

# 141. Historical Order Relationship

Do not make Enquiry history disappear if Order status later changes.

---

# 142. Product Hard Delete Policy

Inspect the schema's FK behavior.

Earlier schema decisions indicate Enquiry/Product relationships may use `SET NULL` on hard deletion.

Do not change that in 10.5 unless a genuine contract issue exists.

---

# 143. Order Deletion Policy

Inspect existing FK policy.

Do not redesign.

---

# 144. No Snapshot Columns Added

Do not add:

```text
product_details
order_details
```

just to preserve related labels.

Use existing schema/contracts.

---

# 145. No Price Snapshot

Even product-linked Enquiry does not snapshot price.

---

# 146. No Payment Snapshot

Order-linked Enquiry does not snapshot payment.

---

# 147. No Delivery Snapshot

Order-linked Enquiry does not expose or duplicate addresses.

---

# 148. Resource Privacy

Anonymous submitter receives the creation response but gains no future retrieval capability merely from knowing `enq_...`.

Do not make ID into a bearer credential.

---

# 149. No Anonymous GET

Do not add public:

```text
GET /enquiries/{id}
```

---

# 150. Tests — Creation Service

Add focused:

```text
EnquiryCreationServiceTest
```

or equivalent.

Test:

```text
anonymous ownership null
CUSTOMER ownership derived
OPEN default
reference/id generation
contact snapshot
no commerce side effects
```

---

# 151. Tests — Validation

Add:

```text
EnquiryValidationApiTest
```

Cover strict types/allow-list/bounds.

---

# 152. Tests — Anonymous Contact Matrix

At minimum:

```text
name + phone → valid
name + email → valid
name + phone + email → valid
missing name → invalid
name + no channel → invalid
invalid supplied phone → invalid
invalid supplied email → invalid
```

---

# 153. Tests — Authenticated Contact Matrix

At minimum:

```text
all submitted → submitted snapshot
none submitted + trusted profile/account complete → derived
name only + derived email → valid
email only + derived name → valid
invalid supplied email + valid derived email → still invalid
no reachable submitted/derived contact → invalid
```

---

# 154. Tests — Subject

```text
4 chars invalid
5 valid
200 valid
201 invalid
wrong type invalid
blank invalid
```

---

# 155. Tests — Message

```text
9 invalid
10 valid
5000 valid
5001 invalid
wrong type invalid
blank invalid
newline preservation
markup-like plain text safe
```

---

# 156. Tests — Category

```text
omitted → null
null → null
GENERAL → valid
PRODUCT → valid
DELIVERY → valid
OTHER → valid
lowercase → invalid
unknown → invalid
wrong type → invalid
```

---

# 157. Tests — Product

```text
omitted → valid
null → valid
public IN_STOCK → valid
public MADE_TO_ORDER → valid
unknown → 404
inactive → masked 404
unpublished → masked 404
soft-deleted → masked 404
inactive Category → masked 404
```

---

# 158. Tests — Anonymous Order

```text
order_id omitted → valid
order_id null → valid
non-null order_id → 422 INVALID_VALUE
```

No Order lookup/ownership inference should be used to authorize anonymous.

---

# 159. Tests — Customer Order

```text
owned Order → valid
other Customer's Order → masked 404
unknown Order → same masked 404 family
```

---

# 160. Tests — Both Associations

```text
valid public Product + owned Order
→ valid
```

---

# 161. Tests — No Associations

General Enquiry with neither:

```text
product_id
order_id
```

→ valid.

---

# 162. Tests — Resource

Assert exact:

```text
id
name
email
phone
subject
message
category
product_id
product
order_id
order
enquiry_status
attachments
created_at
updated_at
```

No internal fields.

---

# 163. Tests — Product Summary

Exactly:

```text
id
name
slug
```

---

# 164. Tests — Order Summary

Exactly:

```text
id
order_reference
status
```

---

# 165. Tests — Hidden Fields

Response omits:

```text
user_id
staff_internal_notes
numeric IDs
payment
totals
delivery_address
billing_address
```

---

# 166. Tests — No Side Effects

After valid Enquiry:

```text
Order count unchanged
Payment count unchanged
ProductStock unchanged
Cart unchanged
FurnitureRequest count unchanged
```

---

# 167. Tests — Request Separation

Creating an Enquiry must not create/update:

```text
FurnitureRequest
```

---

# 168. Tests — Duplicate Submission

Two identical valid ENQ-001 submissions create:

```text
two Enquiries
```

No deduplication.

---

# 169. Tests — Auth Boundaries

```text
anonymous → allowed
CUSTOMER → allowed
invalid bearer → 401
STAFF → 403
ADMIN → 403
```

---

# 170. Tests — Route Gating

If ENQ-001 is gated:

assert:

```text
default route → NotImplementedResponse
in-process enabled → real controller
```

following current Request feature-gate pattern.

---

# 171. Tests — Cache Headers

Created response:

```text
private, no-store
```

plus `Vary: Authorization` where applicable.

---

# 172. Tests — Rate Limiting

Verify the public submission limiter remains attached.

Do not exhaustively retest middleware internals already covered globally.

---

# 173. Tests — Error Privacy

Hidden Product and foreign Order failures must not expose internal state/owner.

---

# 174. Existing Request Regressions

Run:

```text
FurnitureRequest*
RequestStatus*
RequestableProductResolver*
```

General Enquiry work must not regress Request behavior.

---

# 175. Product Regressions

If public Product scope reused but not modified:

run relevant Product visibility tests.

If modified:

run full Group E catalog regression.

---

# 176. Order Regressions

If reusing/changing Order ownership helpers:

run:

```text
customer Order detail/list ownership tests
404 masking tests
```

Do not weaken IDOR protections.

---

# 177. Phone Regression

Because Enquiry uses shared `PhoneNumber`:

run:

```text
Request phone validation
DeliveryFulfillmentState
UpdateMeRequest
```

where appropriate.

---

# 178. Schema Regressions

Run:

```text
EnquirySchemaTest
SchemaIntegrityTest
```

or actual equivalents.

---

# 179. Status Enum

Reuse existing:

```text
EnquiryStatus
```

or repository equivalent.

Do not introduce duplicate enum.

---

# 180. Category Enum

Reuse:

```text
EnquiryCategory
```

from Group C.

---

# 181. Reference Generator

If Enquiry reference generation is already centralized, reuse:

```text
ReferenceGenerator
```

Do not use Faker/random in production.

---

# 182. Identifier

Reuse/create the canonical:

```text
EnquiryIdentifier
```

only if not already present.

Follow existing opaque-ID pattern.

---

# 183. No Schema Change

Expected:

```text
NONE
```

---

# 184. No New External Dependency

Expected:

```text
NONE
```

---

# 185. Frontend

Expected:

```text
NONE
```

---

# 186. OpenAPI

Expected:

```text
UNCHANGED
```

except for a strictly confirmed pre-existing frozen-error-enum omission.

Do not redesign ENQ-001.

---

# 187. Attachment Contract

Do not remove:

```text
multipart/form-data
attachment
```

from the frozen API simply because Phase 10.6 has not implemented it yet.

Keep the route gated.

---

# 188. Documentation

Add the next backend ADR if repository practice continues.

Likely:

```text
ADR/BACKEND-047 — General Enquiry Intake Boundary
```

but inspect the actual next ADR number first.

---

# 189. ADR Content

Record:

```text
Enquiry distinct from Request
anonymous + CUSTOMER creation
authenticated contact derivation
explicit contact overrides trusted fallback
historical contact snapshot
subject/message plain-text constraints
category CLOSED enum
optional any-public-Product association
optional owned-Order association
anonymous order_id rejected
OPEN default
no idempotency requirement
no content deduplication
no Order/Payment/Inventory side effects
private resource serialization
route gated pending Phase 10.6
```

---

# 190. Group J Tracking

After PASS:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 READY
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 191. Phase 10.6 Readiness

After 10.5, Phase 10.6 should be able to build one shared secure attachment architecture for:

```text
Furniture Requests
General Enquiries
```

without changing either intake service's domain semantics.

---

# 192. Verification Commands

Run focused tests first:

```bash
php artisan test --filter=Enquiry
```

Then:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Validate OpenAPI parsing.

---

# 193. Completion Report

Return:

## Phase 10.5 status

```text
PASS
```

or:

```text
BLOCKED
```

## Endpoint

```text
ENQ-001
POST /api/v1/enquiries
```

## Route

Report:

```text
name
middleware
controller
ACTIVE or STUB/GATED
```

Expected before 10.6:

```text
STUB/GATED
```

## Actor model

```text
Anonymous allowed
CUSTOMER allowed
invalid bearer rejected
STAFF rejected
ADMIN rejected
```

## Request validator

Report exact class.

## Input object

Report exact normalized input/VO if used.

## Command

Report exact command.

## Creation service

Report exact service.

## Contact

Report separately:

```text
Anonymous:
name explicit
phone/email explicit

CUSTOMER:
name/phone/email optional submitted
missing fields derived from trusted profile/account
explicit valid submitted values win
at least one final reachable contact required
```

## Subject

Report:

```text
5..200
plain text
```

## Message

Report:

```text
10..5000
plain text
newlines preserved
```

## Category

Report:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
nullable
```

## Product association

Report:

```text
any public Product type
IN_STOCK allowed
MADE_TO_ORDER allowed
hidden/missing masked
```

## Order association

Report:

```text
anonymous non-null order_id rejected
CUSTOMER owned Order allowed
foreign/unknown Order 404 masked
```

## Ownership

Report:

```text
anonymous user_id=null
CUSTOMER user_id server-derived
```

## Initial status

```text
OPEN
```

## Resource

Report exact serializer fields.

## Attachments

```text
NOT IMPLEMENTED
Phase 10.6
```

## Request relationship

```text
NO Enquiry↔FurnitureRequest conversion
```

## Orders

```text
created = NONE
mutated = NONE
```

## Payments

```text
NONE
```

## Inventory

```text
NONE
```

## Price / Quote

```text
NONE
```

## Idempotency

```text
not required
duplicate valid submissions allowed
```

## Notifications

```text
NONE
Group R
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

Expected:

```text
NONE
```

## OpenAPI

Expected:

```text
UNCHANGED
```

or exact approved consistency correction.

## Tests

Report:

```text
anonymous contact matrix
authenticated contact derivation matrix
subject bounds
message bounds/plain-text safety
category enum
Product association both Product types
Product visibility masking
anonymous order rejection
Order ownership/IDOR masking
product+order combination
no association path
ownership
resource exposure
duplicate submissions
zero commerce effects
Request regressions
full suite
```

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

## Group J status

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS/BLOCKED
10.6 READY/BLOCKED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 194. Definition of Done

Phase 10.5 is complete when:

- General Enquiry is implemented as a distinct domain from Furniture Request;
- ENQ-001 has one canonical creation path;
- Anonymous may create Enquiry;
- authenticated CUSTOMER may create Enquiry;
- STAFF/ADMIN cannot create customer Enquiries through ENQ-001;
- invalid bearer never downgrades to anonymous;
- anonymous name is required;
- anonymous phone/email at least one is required;
- authenticated Customer contact may be derived;
- explicit valid submitted contact takes precedence over fallback;
- invalid supplied optional contact is rejected rather than ignored;
- final authenticated contact snapshot remains reachable;
- contact snapshot is historical;
- subject is required, 5..200;
- message is required, 10..5000;
- subject/message are plain text;
- category is nullable CLOSED GENERAL/PRODUCT/DELIVERY/OTHER;
- Product association is optional;
- any public Product type is accepted;
- Product visibility reuses existing public scope;
- hidden/missing Product is safely masked;
- no stock/Variant requirement exists;
- Order association is optional;
- anonymous non-null order_id is rejected;
- authenticated Customer may reference only owned Order;
- cross-customer Order ID is 404 masked;
- knowing order ID is not authorization;
- Product and Order may both be supplied;
- neither Product nor Order is required;
- ownership user_id is server-derived;
- new Enquiry begins OPEN;
- client cannot set enquiry_status;
- client cannot set staff_internal_notes;
- original subject/message/contact/category/product/order context is immutable intake;
- explicit resource serialization is used;
- staff_internal_notes is hidden;
- no Order is created;
- no Order is mutated;
- no Payment is created;
- no inventory changes occur;
- no quote is created;
- no Enquiry↔Request conversion exists;
- no Idempotency-Key is required;
- duplicate submissions are allowed;
- no content-based uniqueness is introduced;
- response is private/no-store;
- route remains gated pending attachment support;
- no schema migration is added;
- no new dependency is added;
- no frontend work occurs;
- regression suite remains green;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean;
- `git diff --check` passes.

---

# 195. Out of Scope

Do not implement:

```text
Phase 10.6 attachment storage/security
Phase 10.7 staff/admin Request management
Phase 10.8 Group J closure testing

full staff Enquiry queue
customer Enquiry history/list APIs
ENQ-006 operational close/reopen unless already assigned by current roadmap
staff_internal_notes mutation
Request↔Enquiry conversion
Enquiry→Order conversion
pricing/quotation
inventory
payment
ClickPesa
delivery
notifications
email
frontend
```

---

# 196. STOP Condition

STOP when the backend can correctly model:

```text
Anonymous/CUSTOMER
→ valid contact snapshot
→ subject + message
→ optional category
→ optional any-public Product
→ optional owned Order
→ OPEN Enquiry
→ explicit private resource
```

while preserving:

```text
Enquiry ≠ FurnitureRequest
Enquiry ≠ Order
Enquiry ≠ Payment
Enquiry ≠ Inventory operation
```

and:

```text
attachments → Phase 10.6
operational management → later phase
public route → remains gated until frozen attachment support exists
```

Do not continue automatically to Phase 10.6.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 PASS — Request validation (ADR/BACKEND-044)
10.3 PASS — Request status lifecycle (ADR/BACKEND-045)
10.4 PASS — Product-linked requests (ADR/BACKEND-046)
10.5 PASS — General enquiries (ADR/BACKEND-047)
10.6 READY — Attachment handling
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Group J is **not** complete. The public `POST /api/v1/requests` and
`POST /api/v1/enquiries` routes remain **STUB/GATED**
(`config('requests.route_enabled')` / `config('enquiries.route_enabled')` both
`false`) until Phase 10.6 attachment support is implemented.
