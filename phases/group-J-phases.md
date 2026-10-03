# Phase 10.7 — Staff/Admin Furniture Request Management

## 1. Objective

Implement the complete Version 1 **operational Furniture Request management API** for authorized Staff/Admin.

Operations:

```http
GET   /api/v1/requests
GET   /api/v1/requests/{request}
PATCH /api/v1/requests/{request}
```

Operation IDs:

```text
REQ-004 — List operational Furniture Requests
REQ-005 — View operational Furniture Request
REQ-006 — Update controlled operational fields
```

Phase 10.7 must connect the already-completed Request foundations:

```text
10.1 creation
10.2 validation
10.3 lifecycle/state machine
10.4 product eligibility
10.6 attachments
```

into a safe Staff/Admin operational workflow.

The operational flow should become:

```text
Staff/Admin
    ↓
requests.view
    ↓
GET /requests
    ↓
filtered operational queue
    ↓
GET /requests/{request}
    ↓
private operational detail
    ↓
requests.manage
    ↓
PATCH /requests/{request}
    ↓
request_status and/or staff_internal_notes only
```

Do not turn this phase into generic customer-account administration.

---

# 2. Current Group J Baseline

Treat the current state as:

```text
10.1 PASS — Furniture Request API
10.2 PASS — Request validation
10.3 PASS — Request status lifecycle
10.4 PASS — Product-linked Requests
10.5 PASS — General Enquiries
10.6 PASS/BLOCKED — inspect actual latest result
10.7 CURRENT — Staff/Admin Request management
10.8 NOT STARTED — Group J verification/closure
```

Before implementation:

inspect the actual Phase 10.6 result.

Do not assume:

```text
attachments
REQ-001 route activation
REQ-007 activation
```

unless the repository actually confirms them.

---

# 3. Scope

Phase 10.7 owns:

```text
REQ-004 operational collection
REQ-005 operational detail
REQ-006 controlled mutation
requests.view authorization
requests.manage authorization
Staff/Admin operational Request resource
staff_internal_notes validation/persistence
queue filtering
search
pagination
deterministic sorting
not-found behavior
field-level privacy
status lifecycle integration
private caching
operational Request tests
route activation for REQ-004/005/006
```

---

# 4. Out of Scope

Do not implement:

```text
customer Request retrieval unless already assigned elsewhere
General Enquiry staff management
Request→Order conversion
quoting
pricing
production workflow
manufacturing
payments
ClickPesa
inventory reservation
notifications
email
chat
frontend
```

Phase 10.7 is Furniture Request operational management only.

---

# 5. Core Authorization Model

Use separate permissions:

```text
requests.view
requests.manage
```

These permissions are not interchangeable.

---

# 6. `requests.view`

Allows:

```text
REQ-004 GET /requests
REQ-005 GET /requests/{request}
```

It does not imply:

```text
status mutation
internal-note mutation
customer account mutation
Request ownership
```

---

# 7. `requests.manage`

Allows:

```text
REQ-006 PATCH /requests/{request}
```

for the specific approved operational fields.

Do not infer:

```text
requests.manage
→ customer account administration
```

---

# 8. Staff Is Operational, Not Owner

A Staff member viewing a Request does not become:

```text
Request owner
Customer proxy
Customer account controller
```

Do not write authorization such as:

```php
$request->user_id === $staff->id
```

for Staff access.

Operational authorization is permission-based.

---

# 9. Customer Ownership Remains Separate

Customer ownership paths, where implemented, remain:

```text
/me/requests
/me/requests/{request}
```

Do not make operational `/requests` act as a disguised customer route.

---

# 10. Anonymous Access

Anonymous:

```text
GET /requests
GET /requests/{request}
PATCH /requests/{request}
```

must never gain operational access.

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 11. CUSTOMER Access

CUSTOMER:

```text
GET /requests
GET /requests/{request}
PATCH /requests/{request}
```

must not use operational routes.

Expected:

```text
403 FORBIDDEN
```

Do not redirect to `/me/requests`.

---

# 12. STAFF Access

STAFF with:

```text
requests.view
```

may:

```text
REQ-004
REQ-005
```

STAFF without:

```text
requests.manage
```

may not:

```text
REQ-006
```

---

# 13. ADMIN Access

ADMIN may perform operational Request functions only according to the existing approved RBAC model.

Do not bypass policies merely because:

```text
role == ADMIN
```

unless current authorization architecture explicitly grants Admin through role-derived permissions.

Reuse the established permission system.

---

# 14. Explicit Permission Checks

Prefer existing policy/middleware architecture.

Examples conceptually:

```text
permission:requests.view
permission:requests.manage
```

or the repository's authorization service/policy.

Do not manually duplicate RBAC queries in controller methods.

---

# 15. Route Surface

Canonical routes remain:

```http
GET   /api/v1/requests
GET   /api/v1/requests/{request}
PATCH /api/v1/requests/{request}
```

Do not add aliases:

```text
/staff/requests
/admin/requests
/request-management
/backoffice/requests
```

The actor/permission determines behavior, not duplicate URLs.

---

# 16. REQ-004 — Operational Collection

Implement:

```http
GET /api/v1/requests
```

Purpose:

```text
authorized operational queue
```

Return:

```json
{
  "data": [...],
  "meta": {
    "pagination": { ... }
  }
}
```

---

# 17. REQ-004 Authentication

Requires bearer authentication.

No optional auth.

---

# 18. REQ-004 Authorization

Requires:

```text
requests.view
```

Anonymous:

```text
401
```

CUSTOMER:

```text
403
```

STAFF without permission:

```text
403
```

authorized STAFF/ADMIN:

```text
200
```

---

# 19. REQ-004 Pagination

Reuse global:

```text
page
per_page
```

Rules:

```text
page >= 1
per_page 1..100
```

Use the exact repository global pagination semantics.

Do not invent Request-specific pagination.

---

# 20. Default Pagination

Use the established global default.

Do not create a special Request default unless the frozen contract already says so.

---

# 21. Pagination Metadata

Use canonical:

```text
meta.pagination
```

Do not create:

```text
pagination
paging
page_info
```

parallel formats.

---

# 22. Deterministic Queue Sorting

Operational Request queue ordering:

```text
created_at DESC
id ASC
```

Newest first.

The secondary:

```text
id ASC
```

must make equal timestamps deterministic.

---

# 23. No Arbitrary Sort Yet

The normative Request conventions define deterministic newest-first sorting.

Do not add arbitrary:

```text
sort=name
sort=status
sort=email
```

unless the actual frozen REQ-004 contract explicitly contains such parameters.

---

# 24. Filter Allow-List

REQ-004 supports exactly the approved operational filters:

```text
search
request_status
product_id
created_from
created_to
page
per_page
```

Do not add:

```text
user_id
customer_id
email_exact
phone_exact
staff_internal_notes
quantity
material
color
order_id
```

as query filters unless already frozen.

---

# 25. Unknown Query Parameters

Unknown filter/query parameters must be rejected:

```text
422 INVALID_VALUE
```

with the offending parameter.

Do not silently ignore unsupported filters.

---

# 26. `search`

Optional string.

Searches approved Request operational fields:

```text
name
email
phone
request reference
linked product
```

Use exact repository interpretation of "product" from frozen conventions.

Prefer a bounded string length consistent with other search endpoints.

Do not invent full-text infrastructure.

---

# 27. Search Normalization

Trim.

Empty search should either:

```text
normalize to absent
```

or follow the existing global collection convention.

Do not run:

```sql
LIKE '%%'
```

unnecessarily.

---

# 28. Search Privacy

Search operates only across the already-authorized operational Request dataset.

Do not expand into:

```text
global customer search
all user profiles
orders
payments
```

---

# 29. Search Must Not Become Customer-Account Lookup

A Staff member searching a phone/email may find Requests containing that historical Request contact.

It must not produce unrelated:

```text
User profile
customer account
orders
credentials
roles
```

---

# 30. Search Fields

Keep search tied to Request intake:

```text
request name
request email
request phone
request reference
linked Product context
```

Do not search:

```text
password
Clerk identity data
private unrelated records
```

---

# 31. `request_status` Filter

Optional.

Exact CLOSED values:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

Wrong type:

```text
INVALID_TYPE
```

Unknown value:

```text
INVALID_VALUE
```

Do not accept lowercase aliases.

---

# 32. `product_id` Filter

Optional.

Validate opaque:

```text
prod_...
```

format.

No numeric database ID.

No slug alias unless contract explicitly says so.

---

# 33. Product Filter Semantics

Filtering by a valid Product ID should filter historical Request association.

Do not require the Product to remain:

```text
public
active
published
MADE_TO_ORDER
```

at query time.

A Request created validly in the past must remain operationally visible even if Product later changes.

---

# 34. Unknown Product Filter

Decide using the frozen collection/filter convention.

Prefer:

```text
empty collection
```

for a syntactically valid but unused Product ID unless current contract says filter references must resolve.

Do not leak hidden catalog state.

---

# 35. `created_from`

Optional.

Require ISO8601 UTC `Z` format according to frozen convention.

Example:

```text
2026-10-01T00:00:00Z
```

---

# 36. `created_to`

Same.

---

# 37. Date Range Validation

If both supplied:

```text
created_from <= created_to
```

Otherwise:

```text
422 INVALID_VALUE
```

Use deterministic field mapping.

---

# 38. Date Boundary Semantics

Define inclusivity consistently.

Recommended based on normal collection filtering:

```text
created_at >= created_from
created_at <= created_to
```

but verify repository conventions.

Do not invent inconsistent range semantics.

---

# 39. Query Object

Introduce one normalized query value/DTO such as:

```php
OperationalRequestQuery
```

containing:

```text
search
requestStatus
productId
createdFrom
createdTo
page
perPage
```

Do not pass raw Request query arrays deep into repository/service code.

---

# 40. Collection Query Service

Create/reuse focused query service:

```php
ListOperationalFurnitureRequests
```

or repository-consistent equivalent.

Responsibilities:

```text
apply authorized query
apply allow-listed filters
apply deterministic sort
paginate
load safe required relations
```

---

# 41. Do Not Put Query Logic in Controller

Controller should not contain long chains such as:

```php
if ($request->has(...)) {
    $query->...
}
```

Keep it thin.

---

# 42. Query Efficiency

Eager-load only what the operational resource needs:

```text
Product summary
Attachment metadata
```

and any approved owner identifier representation.

Avoid N+1.

Do not eager-load:

```text
Orders
Payments
Cart
Product variants
inventory
```

unless needed by the resource.

---

# 43. Index Awareness

Use existing indexes where possible.

Do not add speculative indexes immediately.

If the new operational query clearly lacks required indexes:

document actual EXPLAIN/test evidence before proposing a migration.

Expected Phase 10.7:

```text
Schema changes NONE
```

unless evidence proves otherwise.

---

# 44. REQ-004 Resource

Operational collection must use an explicit allow-listed Resource.

Do not:

```php
return FurnitureRequest::paginate();
```

with raw model serialization.

---

# 45. Collection Representation

Use the frozen operational representation.

If the same:

```text
MadeToOrderRequest
```

resource schema is used for both list/detail, preserve it.

Do not silently create a reduced incompatible list shape unless contract allows.

---

# 46. Staff Operational Fields

Operational Staff representation may include:

```text
id
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
staff_internal_notes
user_id
attachments
created_at
updated_at
```

according to frozen resource rules.

Use actual OpenAPI/resource definition as final authority.

---

# 47. `staff_internal_notes`

This is operationally visible to authorized Staff/Admin.

It is never visible in:

```text
customer Request representation
anonymous creation response
public catalog
```

---

# 48. `user_id`

Staff operational representation may include opaque:

```text
user_...
```

or null.

Never expose numeric DB User ID.

---

# 49. Anonymous Request

Operational Request detail for an anonymous request correctly shows:

```text
user_id = null
```

with the historical contact snapshot.

Do not create fake User ownership.

---

# 50. Product Summary

Keep:

```text
{id, name, slug}
```

where linked.

Do not expose inventory or pricing solely because Staff sees the Request.

---

# 51. Historical Product

Operational Request must remain viewable even if linked Product is later:

```text
unpublished
inactive
type-changed
```

Do not run 10.4 requestability validation on read.

---

# 52. Soft-Deleted Product Relationship

Inspect the actual relationship behavior.

Do not make a Request detail 500 because Product was archived.

If current relation intentionally becomes null:

resource must handle it.

If the contract expects historical product context and existing schema cannot provide it after hard/soft deletion:

report rather than inventing a snapshot.

---

# 53. Attachment Metadata

Staff operational detail may include authorized attachment metadata.

Use the completed Phase 10.6:

```text
AttachmentResource
```

or actual shared resource.

Never expose:

```text
storage_key
disk
capability digest/token
private filesystem path
```

---

# 54. Attachment URL

Only expose:

```text
temporary/private authorized URL
```

if Phase 10.6 implemented it.

Otherwise:

```text
url = null
```

Do not generate permanent public URLs in 10.7.

---

# 55. REQ-005 — Operational Detail

Implement:

```http
GET /api/v1/requests/{request}
```

---

# 56. REQ-005 Authorization

Requires:

```text
requests.view
```

No ownership requirement for Staff.

---

# 57. REQ-005 Path Identifier

Use opaque:

```text
req_...
```

identifier resolution.

No numeric fallback.

No request_reference alias unless contract explicitly allows it.

---

# 58. Invalid Opaque ID

Use canonical:

```text
INVALID_FORMAT
```

or route-resolution behavior consistent with other operational endpoints.

Do not query using malformed raw values.

---

# 59. Missing Request

Authorized operational actor requesting a nonexistent Request:

```text
404 RESOURCE_NOT_FOUND
```

or exact canonical Request-not-found code.

Do not leak database IDs.

---

# 60. Customer on Operational Detail

CUSTOMER:

```text
403
```

not owner-based success.

Customer must use own canonical `/me/...` resource if implemented.

---

# 61. Operational Detail Privacy

Staff sees only what is needed to handle the Request.

Do not expose:

```text
password
credential data
Clerk tokens
customer roles
other Orders
payment data
billing data
unrelated profile fields
```

---

# 62. REQ-006 — Operational Mutation

Implement:

```http
PATCH /api/v1/requests/{request}
```

Purpose:

```text
controlled operational Request update
```

Allowed mutable fields:

```text
request_status
staff_internal_notes
```

Nothing else.

---

# 63. REQ-006 Authorization

Requires:

```text
requests.manage
```

`requests.view` alone is insufficient.

---

# 64. View vs Manage Regression

Explicitly test:

```text
STAFF with requests.view only
→ GET collection 200
→ GET detail 200
→ PATCH 403
```

and:

```text
STAFF with requests.manage
→ PATCH permitted
```

according to RBAC assignment.

---

# 65. REQ-006 Request Allow-List

Exactly:

```text
request_status
staff_internal_notes
```

Reject all other fields.

---

# 66. Original Intake Is Immutable

Reject attempts to modify:

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
user_id
request_reference
created_at
```

---

# 67. Commerce Fields Rejected

Reject:

```text
order_id
payment_id
payment_status
delivery_fee
price
quoted_price
currency
```

No future workflow may be smuggled through generic PATCH.

---

# 68. Unknown Field

Any other field:

```text
422 INVALID_VALUE
```

with exact offending public field name.

---

# 69. Empty PATCH

Determine frozen behavior.

Prefer rejecting:

```json
{}
```

because it performs no operational action.

Use canonical:

```text
MISSING_REQUIRED_FIELD
or INVALID_VALUE
```

according to existing mutation conventions.

Do not invent behavior without checking.

---

# 70. `request_status`

Optional within PATCH if notes alone may be updated.

If supplied:

```text
string
SUBMITTED|IN_REVIEW|CLOSED
```

exact case.

---

# 71. Status Wrong Type

Examples:

```json
{"request_status": 1}
{"request_status": true}
```

→:

```text
422 INVALID_TYPE
```

---

# 72. Status Unknown Value

Example:

```json
{"request_status":"APPROVED"}
```

→:

```text
422 INVALID_VALUE
```

---

# 73. Status Transition Authority

Do not implement transition rules again in the controller.

Reuse:

```php
App\Services\Requests\TransitionFurnitureRequestStatus
```

from Phase 10.3.

---

# 74. Valid Lifecycle

Preserve:

```text
SUBMITTED → IN_REVIEW
SUBMITTED → CLOSED
IN_REVIEW → CLOSED
```

---

# 75. Invalid Lifecycle

Reject:

```text
IN_REVIEW → SUBMITTED
CLOSED → SUBMITTED
CLOSED → IN_REVIEW
```

with:

```text
409 CONFLICT
field: request_status
```

using existing Phase 10.3 exception mapping.

---

# 76. Same-State Status

Same target:

```text
SUBMITTED → SUBMITTED
IN_REVIEW → IN_REVIEW
CLOSED → CLOSED
```

remains an idempotent no-op.

Do not return conflict.

---

# 77. Same-State Timestamp

Preserve Phase 10.3 behavior:

```text
no UPDATE
updated_at unchanged
```

when only same-state status is supplied and no notes change.

---

# 78. `staff_internal_notes`

Operational only.

May be:

```text
string
null
```

according to frozen OpenAPI.

---

# 79. Notes Validation

Inspect existing approved bounds.

If the frozen contract does not establish a numeric maximum:

do not invent an arbitrary business limit.

However enforce any technical DB column bound before persistence.

---

# 80. Internal Notes Plain Text

Treat as private untrusted text.

Do not interpret:

```text
HTML
Markdown
commands
SQL
```

Store as text.

Render safely on clients.

---

# 81. Empty Internal Notes

Decide consistently.

Prefer:

```text
blank/whitespace-only → null
```

if current nullable-text conventions use that pattern.

Document.

---

# 82. Customer Notes vs Staff Notes

Keep separate:

```text
notes
→ original customer intake
```

versus:

```text
staff_internal_notes
→ operational staff-only notes
```

Never overwrite customer notes when staff writes internal notes.

---

# 83. No Combined `notes` Alias

REQ-006 must not accept:

```text
notes
```

as staff notes.

That field is immutable customer history.

---

# 84. Internal Notes Mutation Service

Create a focused service if useful:

```php
UpdateFurnitureRequestOperationalFields
```

It should coordinate:

```text
status transition
staff_internal_notes
transaction
```

without duplicating the Phase 10.3 state machine.

---

# 85. Atomic Combined Update

If a PATCH contains both:

```text
request_status
staff_internal_notes
```

they should commit atomically.

Example:

```text
status transition valid
notes valid
→ both persist

status transition invalid
→ neither persists
```

Do not persist notes before discovering the status conflict.

---

# 86. Transaction Owner

Use one transaction owner for REQ-006.

Do not nest independent transactions between:

```text
status service
notes service
```

in a way that allows partial persistence.

---

# 87. Phase 10.3 Integration Challenge

`TransitionFurnitureRequestStatus` already owns a transaction.

If combined REQ-006 requires status + notes atomically:

refactor carefully so the state-machine decision can participate in one outer transaction.

Possible safe options:

```text
A. operational update service owns one transaction and uses a non-transactional locked transition primitive
B. existing transition service detects/joins an outer transaction if current architecture supports it
```

Do not introduce nested independent commit boundaries.

---

# 88. Preserve Phase 10.3 Public Service Semantics

Any refactor must preserve:

```text
row lock
locked-state re-read
bounded transient retry
same-state no-op
409 on invalid transition
```

Run all Phase 10.3 tests.

---

# 89. Locking

REQ-006 should lock the FurnitureRequest row:

```text
FOR UPDATE
```

before evaluating current status and mutating operational fields.

---

# 90. Notes-Only Update

Notes-only PATCH still needs safe row-level persistence.

No elaborate concurrency system required beyond normal transaction/lock if used consistently.

---

# 91. Concurrent Status Updates

Preserve proven behavior:

```text
close vs review
same target
```

No reopening.

---

# 92. Concurrent Notes Updates

Last serialized authorized write may win unless the frozen contract defines optimistic locking/versioning.

Do not invent ETags/version columns.

---

# 93. Status + Notes Race

If two Staff update simultaneously:

each transaction should evaluate current locked status.

No stale status transition may overwrite CLOSED.

Notes should correspond to the transaction that actually committed.

---

# 94. Business Conflict Is Not Retryable

Do not retry:

```text
CLOSED → IN_REVIEW
```

because it is not transient.

---

# 95. Transient DB Conflicts

Reuse:

```php
ConcurrentTransaction
```

for bounded retry.

Do not build another retry framework.

---

# 96. No Idempotency-Key

REQ-006 is semantically idempotent for same status.

Do not require an Idempotency-Key unless frozen contract says so.

Internal-note changes are ordinary operational updates.

---

# 97. Operational Update Result

Return:

```text
200
```

with updated Request resource.

---

# 98. Representation After Update

Return the operational Staff representation.

It may include:

```text
staff_internal_notes
```

because caller has `requests.manage`.

---

# 99. No Raw Model Serialization

Never:

```php
return $requestModel;
```

Use explicit resource.

---

# 100. Resource Split

If customer and staff representations differ, use either:

```text
FurnitureRequestResource with actor-aware safe projection
```

or separate:

```text
FurnitureRequestCustomerResource
FurnitureRequestOperationalResource
```

Prefer whichever matches repository conventions.

Do not use one resource that accidentally exposes internal notes to customers.

---

# 101. Strong Preference — Separate Operational Resource

A dedicated:

```php
OperationalFurnitureRequestResource
```

is often safer because:

```text
customer output
staff output
```

have materially different private fields.

But reuse existing design if already safely actor-aware.

---

# 102. Staff Internal Notes Exposure

Only actor with legitimate operational authorization should receive them.

Do not assume:

```text
every authenticated user
```

may see them.

---

# 103. `requests.view` and Internal Notes

The resource contract says Staff operational detail includes internal notes where authorized.

Verify whether:

```text
requests.view
```

alone is enough to see `staff_internal_notes`, or whether notes require:

```text
requests.manage
```

specifically.

The resource text references:

```text
staff_internal_notes — STAFF (`requests.manage` where authorized)
```

Therefore implement the strictest contract-consistent field gating:

```text
requests.view only
→ operational Request detail
→ internal notes hidden/null unless current contract clearly grants visibility

requests.manage
→ internal notes visible
```

Do not overexpose.

---

# 104. Important Contract Ambiguity Check

The prose says:

```text
Staff sees ... internal notes where authorized
```

and the field table says:

```text
staff_internal_notes — STAFF (`requests.manage` where authorized)
```

Verify OpenAPI representation cannot express permission-dependent omission/nullability ambiguously.

If necessary:

document the field-level rule without changing the externally frozen field name.

---

# 105. Admin Internal Notes

Authorized Admin may view/manage internal notes according to permissions.

Do not expose them merely because role string is ADMIN if current RBAC permission is absent.

---

# 106. Contact Privacy

Operational Request contains:

```text
name
phone
email
```

because Staff needs them to follow up.

Do not expose unrelated Customer profile fields.

---

# 107. User Context

Operational resource may include:

```text
user_id
```

opaque, where authenticated submission exists.

Do not automatically embed:

```text
full User object
roles
account state
Clerk ID
```

---

# 108. No Customer Account Mutation

Phase 10.7 must never allow Staff to:

```text
change customer name
change customer email
change customer phone/profile
change role
disable account
change password
restrict browsing
restrict ordering
```

The historical Request contact snapshot may be read, not used as an account-edit route.

---

# 109. Request Contact Snapshot Is Immutable

REQ-006 cannot modify:

```text
name
phone
email
```

even if Staff says customer called with an update.

Version 1 preserves original intake.

New operational information belongs in:

```text
staff_internal_notes
```

not rewriting the customer's submission.

---

# 110. Product Link Is Immutable

REQ-006 cannot change:

```text
product_id
```

---

# 111. Quantity Immutable

Cannot change.

---

# 112. Dimensions Immutable

Cannot change.

---

# 113. Material / Color Immutable

Cannot change.

---

# 114. Attachments

Do not replace/delete attachments via REQ-006.

Attachment mutation is governed by REQ-007/Phase 10.6.

---

# 115. No Request-to-Order Conversion

REQ-006 must not accept:

```text
create_order
order_id
convert_to_order
approved
quoted_price
```

---

# 116. No Quote

Do not add:

```text
quoted_price
quote_status
estimated_price
```

to operational Request update.

---

# 117. No Production Statuses

Do not add:

```text
APPROVED
QUOTED
PRODUCING
READY
REJECTED
CONTACTED
```

The frozen lifecycle remains:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

---

# 118. REQ-004 Search Reference

Search must use the actual Request reference field internally if the contract says reference search.

Do not expose the internal `request_reference` if the public resource intentionally excludes it unless frozen operational resource includes it.

Searchability does not imply field exposure.

---

# 119. Request Reference Privacy

A Staff queue may search by internal/business reference.

That does not turn it into customer/public authentication material.

---

# 120. Filters Apply Before Pagination

Correct order:

```text
authorized dataset
→ filters
→ deterministic sort
→ pagination
```

Do not paginate first.

---

# 121. Search + Filters Combine

Filters should combine via AND unless current conventions say otherwise.

Example:

```text
search="Asha"
request_status=SUBMITTED
```

→ SUBMITTED Requests matching search.

---

# 122. Filter Validation Before Query

Invalid query parameter should fail before expensive DB search.

---

# 123. Search Query Escaping

Treat search as data.

Use parameter binding.

Do not interpolate into raw SQL.

Escape wildcard behavior according to current search implementation.

---

# 124. SQL Injection

No raw concatenation from:

```text
search
product_id
dates
status
```

---

# 125. Pagination Privacy

`total` should count the authorized/filtered Request dataset only.

Do not expose counts from unrelated domains.

---

# 126. Caching

REQ-004/005/006 responses are private.

Use:

```text
Cache-Control: private, no-store
```

No CDN caching.

---

# 127. `Vary`

Use existing:

```text
Vary: Authorization
```

protected-response convention where applicable.

---

# 128. Security Headers

Preserve global hardened response headers.

---

# 129. Logging

Do not dump:

```text
full notes
phone
email
attachment URLs
Authorization header
staff internal notes
```

into routine request logs.

Use:

```text
request_id
actor id/opaque operational identifier where permitted
operation
request opaque id
status transition
result
```

according to safe logging policy.

---

# 130. Auditability

The contract identifies future/operational auditability:

```text
actor
action
resource
target
timestamp
result
```

Inspect whether a generic audit infrastructure already exists.

If yes:

reuse it for REQ-006.

If no:

do not invent a full audit-log subsystem unless Group J contract requires immediate persistence.

Document the gap/decision.

---

# 131. Do Not Use Internal Notes as Audit Log

`staff_internal_notes` is editable operational content.

It is not a trustworthy audit trail.

---

# 132. No Notifications Yet

Do not create:

```text
NEW_MADE_TO_ORDER_REQUEST
```

notifications here.

Group R owns notifications.

---

# 133. No Customer Status Notifications

Changing:

```text
SUBMITTED → IN_REVIEW
CLOSED
```

must not automatically send email/SMS/push in this phase.

---

# 134. REQ-004 Query Request Class

Create a dedicated normalized query validator such as:

```php
ListOperationalFurnitureRequestsRequest
```

or equivalent.

Do not reuse creation FormRequest.

---

# 135. REQ-006 Request Class

Create:

```php
UpdateFurnitureRequestRequest
```

or equivalent.

Exact allow-list:

```text
request_status
staff_internal_notes
```

---

# 136. Strict Types

Do not accept:

```text
request_status = 1
staff_internal_notes = []
```

via coercion.

---

# 137. `staff_internal_notes` Null

Explicit:

```json
{
  "staff_internal_notes": null
}
```

should clear notes if frozen contract permits null.

The OpenAPI does permit string|null.

Therefore support clearing.

---

# 138. Notes-Only Update

Valid:

```json
{
  "staff_internal_notes": "Called customer; awaiting measurements."
}
```

---

# 139. Status-Only Update

Valid:

```json
{
  "request_status": "IN_REVIEW"
}
```

---

# 140. Combined Update

Valid:

```json
{
  "request_status": "IN_REVIEW",
  "staff_internal_notes": "Called customer."
}
```

Atomic.

---

# 141. Same Status + New Notes

Example:

```text
current IN_REVIEW
target IN_REVIEW
new internal notes
```

Status portion is idempotent no-op.

Notes still update.

`updated_at` changes because notes changed.

---

# 142. Same Status + Same Notes

If both values identical:

prefer no DB UPDATE.

Do not touch `updated_at` merely for identical replay if practical.

Document behavior.

---

# 143. Notes Change + Invalid Status

Must roll back notes.

Example:

```text
current CLOSED
target IN_REVIEW
notes changed
```

Response:

```text
409 CONFLICT
```

Persisted notes:

```text
unchanged
```

---

# 144. Update Service Design

Create one orchestration service such as:

```php
UpdateFurnitureRequestOperationalFields
```

Responsibilities:

```text
lock Request
read current state
validate status transition via RequestStatusMachine
apply allowed status if needed
apply internal notes if changed
persist once
return locked/current Request
```

---

# 145. Reuse Pure State Machine

Call:

```php
RequestStatusMachine
```

inside the locked transaction.

Do not invoke a second independent transaction if it breaks combined atomicity.

---

# 146. Refactor Phase 10.3 Carefully

If needed, extract the locked transition decision/application portion into a small internal component reused by:

```text
TransitionFurnitureRequestStatus
UpdateFurnitureRequestOperationalFields
```

Do not duplicate the matrix.

---

# 147. Backward Compatibility

Existing:

```php
TransitionFurnitureRequestStatus
```

tests/API must remain valid.

---

# 148. No Nested Commit

Do not call a transaction-owning service inside another transaction if the inner layer can commit separately.

One REQ-006 outer transaction.

---

# 149. Request Row Only

Do not lock:

```text
User
Product
Attachment
Order
Inventory
```

for operational field update.

---

# 150. Not-Found Resolution

Resolve opaque Request ID before/inside service safely.

Authorized operational endpoint missing target:

```text
404
```

---

# 151. Authorization Before Sensitive Data Fetch

Do not load full Request private data, then discover actor lacks:

```text
requests.view
```

if middleware/policy can reject earlier.

---

# 152. Policy

Consider:

```text
FurnitureRequestPolicy
```

with operational methods:

```text
viewOperational
manageOperational
```

if current project architecture uses policies.

Do not create policy architecture just for this domain if permissions are already middleware-based.

---

# 153. Route Activation

REQ-004/005/006 were previously stubbed/gated.

Phase 10.7 should activate them once:

```text
authorization
query validation
resources
mutation service
privacy
tests
```

are complete.

Do not leave them stubbed merely from inertia.

---

# 154. Activation Does Not Depend on Group H/I

Furniture Request operations are independent of:

```text
Checkout
Payment
Order Management
```

No reason to gate them on deferred transactional commerce.

---

# 155. Activation Does Depend on Attachment Privacy

If REQ-005 returns attachment metadata/URLs:

ensure Phase 10.6 authorization/privacy is complete.

If 10.6 remains blocked:

do not expose an unsafe attachment representation.

Possible safe fallback only if frozen contract permits:

```text
attachments metadata without URL
```

Do not hide blocker silently.

---

# 156. Customer Request Routes

Do not activate or implement:

```text
REQ-002
REQ-003
```

unless already implemented by another phase or 10.8 expects them.

Phase 10.7 is operational Staff/Admin management.

---

# 157. General Enquiry Operational Routes

Do not implement:

```text
ENQ-004
ENQ-005
ENQ-006
```

in this phase unless Group J roadmap explicitly includes them under 10.7.

The user asked specifically for Staff/Admin **request management**.

Keep domain scope disciplined.

---

# 158. OpenAPI REQ-004 Review

The normative conventions define filters:

```text
search
request_status
product_id
created_from
created_to
page
per_page
```

Verify `openapi.yaml` lists them.

---

# 159. Potential OpenAPI Gap

If OpenAPI currently omits the frozen REQ-004 filter parameters:

do not drop implementation support.

Instead:

```text
add the already-frozen filter parameters
```

as a contract consistency correction.

Document explicitly:

```text
not new V1 behavior
alignment with api-conventions §26.13
```

---

# 160. Do Not Add Undocumented Filters

Only reconcile parameters already defined normatively.

---

# 161. OpenAPI REQ-006

Verify body already contains:

```text
request_status
staff_internal_notes
additionalProperties:false
```

Preserve.

---

# 162. Response Resource Contract

Verify REQ-004/005/006 response schemas align with actual operational resource.

If OpenAPI uses one generic:

```text
MadeToOrderRequest
```

but field-level permissions differ:

do not silently expose more to customers.

Operational field-level authorization remains mandatory.

---

# 163. Error Responses

REQ-004:

```text
401
403
422 for query validation
```

plus current canonical failures.

REQ-005:

```text
401
403
404
```

REQ-006:

```text
401
403
404
422
409
```

as applicable.

---

# 164. Invalid Transition

Keep:

```text
409 CONFLICT
field=request_status
```

from 10.3.

Do not change to 422.

---

# 165. Query Validation Errors

Use:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
```

according to existing conventions.

---

# 166. Date Format Error

Malformed:

```text
created_from
created_to
```

→:

```text
INVALID_FORMAT
```

---

# 167. Invalid Date Range

```text
created_from > created_to
```

→:

```text
INVALID_VALUE
```

---

# 168. Unknown Query Param

→:

```text
INVALID_VALUE
```

with field.

---

# 169. Permission Failure

→:

```text
403 FORBIDDEN
```

Do not mask Staff permission failure as 404 when accessing the operational route itself.

---

# 170. Missing Resource

Once actor is operationally authorized:

nonexistent Request:

```text
404
```

---

# 171. Customer Attack Scenario

Customer tries:

```text
GET /requests
GET /requests/req_other
PATCH /requests/req_...
```

All:

```text
403
```

after authentication.

No private operational data leakage.

---

# 172. Anonymous Attack Scenario

No bearer:

```text
401
```

---

# 173. Staff View-Only Attack Scenario

Staff has:

```text
requests.view
```

tries PATCH:

```text
403
```

---

# 174. Staff Manage Without View

Inspect current RBAC graph.

If:

```text
requests.manage
```

does not imply `requests.view` at permission level:

decide based on frozen RBAC design whether manage should independently authorize returned operational resource.

Do not invent permission hierarchy silently.

Prefer current seeded permission model.

---

# 175. Admin Data Minimization

Admin should not receive:

```text
all customer-account details
```

just because Admin is highest role.

Return the same operational Request representation unless a specifically approved Admin representation exists.

---

# 176. Request Resource Tests

Test Staff resource includes operational fields.

Test customer resource does not include:

```text
staff_internal_notes
```

---

# 177. Collection Test — Default

Create Requests with varying timestamps/statuses.

Assert:

```text
created_at DESC
id ASC tie-break
```

---

# 178. Collection Test — Pagination

Test:

```text
page
per_page
meta.pagination
```

---

# 179. Pagination Bounds

Test:

```text
page=0 invalid
per_page=0 invalid
per_page=101 invalid
```

---

# 180. Search Tests

Cover at least:

```text
name
email
phone
request reference
product
```

per frozen contract.

---

# 181. Search Case Behavior

Use repository's established case-insensitive search convention if applicable.

Do not create DB-driver-divergent behavior knowingly.

---

# 182. Status Filter Test

Each:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

---

# 183. Product Filter Test

Linked Requests for A/B.

Filter A returns only A.

Custom requests excluded when filtering by Product.

---

# 184. Date Filter Tests

Cover:

```text
created_from only
created_to only
both
boundary timestamp
invalid range
malformed value
```

---

# 185. Combined Filter Test

Example:

```text
search=Asha
request_status=IN_REVIEW
product_id=prod_...
```

All conditions enforced.

---

# 186. Unknown Filter Test

Example:

```text
?user_id=...
```

must reject.

Do not let Staff enumerate by user IDs unless contract says so.

---

# 187. Query SQL Safety Test

Use search strings with:

```text
%
_
'
"
```

and confirm safe behavior/no SQL failure.

---

# 188. Operational Detail Test

Authorized Staff:

```text
200
```

with:

```text
contact
specifications
status
product context
attachments
authorized notes
timestamps
```

---

# 189. Anonymous Request Detail

Operational Staff can view anonymous Request:

```text
user_id null
contact preserved
```

---

# 190. Authenticated Request Detail

Operational Staff sees opaque user context only.

No full account expansion.

---

# 191. Missing Detail

Authorized Staff:

```text
404
```

---

# 192. Invalid ID

Use canonical invalid format/not-found behavior.

---

# 193. Update Status Test

```text
SUBMITTED → IN_REVIEW
```

200.

---

# 194. Direct Close Test

```text
SUBMITTED → CLOSED
```

200.

---

# 195. Review Close Test

```text
IN_REVIEW → CLOSED
```

200.

---

# 196. Invalid Backward Test

```text
IN_REVIEW → SUBMITTED
```

409.

---

# 197. Terminal Test

```text
CLOSED → IN_REVIEW
```

409.

---

# 198. Same-State Test

Same status:

```text
200
```

no unwanted update.

---

# 199. Notes Test

Staff sets internal note.

Persisted.

---

# 200. Clear Notes Test

Set:

```json
{"staff_internal_notes": null}
```

Clears notes.

---

# 201. Customer Notes Preservation Test

Before:

```text
notes = original customer content
```

After Staff internal-note update:

```text
notes unchanged
staff_internal_notes updated
```

---

# 202. Combined Status + Notes Test

Both persist atomically on valid transition.

---

# 203. Combined Rollback Test

Invalid transition + new notes:

```text
409
status unchanged
notes unchanged
```

---

# 204. Immutable Field Tampering Matrix

Use table-driven PATCH cases for:

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
user_id
request_reference
created_at
attachment
order_id
payment_status
quoted_price
```

All rejected.

---

# 205. Permission Matrix Tests

Mandatory:

```text
anonymous
CUSTOMER
STAFF no permission
STAFF view only
STAFF manage
ADMIN
```

for:

```text
REQ-004
REQ-005
REQ-006
```

---

# 206. Role + Permission, Not Role Alone

Create Staff fixtures with missing permissions to prove permission checks are real.

---

# 207. Mixed-Role Regression

If repository guards mixed roles as invalid:

test that same hardening remains.

Do not let mixed-role actor bypass operational permission boundaries.

---

# 208. Suspended Staff

If account-state middleware rejects suspended/inactive Staff:

ensure operational Request routes preserve that behavior.

---

# 209. Attachment Exposure Test

Operational resource never exposes:

```text
storage_key
disk
upload capability
```

---

# 210. Private Cache Test

REQ-004/005/006:

```text
Cache-Control: private, no-store
```

---

# 211. No Side Effects — Read

REQ-004/005 must not mutate:

```text
Request status
updated_at
internal notes
attachments
```

---

# 212. No Commerce Side Effects — Update

REQ-006 must not:

```text
create Order
create Payment
reserve inventory
mutate ProductStock
change Cart
create quote
```

---

# 213. No Notification Side Effect

No notification rows from REQ-006 in Phase 10.7 unless pre-existing explicitly approved event integration exists.

Group R remains owner.

---

# 214. Concurrency Tests

Reuse Phase 10.3 MariaDB status concurrency suite.

Add operational combined-update races where useful.

---

# 215. Required Combined Race

Recommended:

Initial:

```text
IN_REVIEW
notes = A
```

Worker 1:

```text
CLOSED + notes B
```

Worker 2 stale:

```text
IN_REVIEW + notes C
```

Expected:

```text
final CLOSED
stale reopen rejected
no overwrite from losing transaction
```

---

# 216. Same-Status Notes Race

No need to over-specify winner beyond serialized consistency unless contract defines it.

Ensure no invalid state.

---

# 217. MariaDB

Use:

```text
furnitureapp_test_disposable
```

only.

Never destructive test on app DB.

---

# 218. SQLite

Use canonical suite for:

```text
validation
authorization
filtering
serialization
atomic rollback
```

---

# 219. MariaDB Proof

Use MariaDB for:

```text
row-lock race
combined status/notes transaction
```

if added.

---

# 220. REQ-004 Performance Sanity

If dataset test infrastructure exists, inspect query count.

Avoid obvious N+1.

Do not chase premature micro-optimization.

---

# 221. Query Complexity

Keep filter builder straightforward.

Do not create dynamic SQL DSL.

---

# 222. No Full-Text Search Engine

No Elasticsearch/Meilisearch/etc.

SQL search is sufficient for V1.

---

# 223. No Export

Do not add CSV/Excel export.

---

# 224. No Bulk Update

Do not add:

```text
bulk close
bulk assign
bulk delete
```

---

# 225. No Assignment Feature

No:

```text
assigned_staff_id
```

unless already in frozen schema/contract.

Do not invent ticketing/CRM workflow.

---

# 226. No Deletion

Do not add:

```text
DELETE /requests/{request}
```

V1 preserves intake history.

---

# 227. No Editing Customer Submission

Again, operational handling is:

```text
status
internal notes
```

only.

---

# 228. No Order Creation

Even closing:

```text
CLOSED
```

does not mean:

```text
Order created
```

---

# 229. No Price/Quote

Staff internal note may contain informal communication, but backend does not expose authoritative quoted-price fields.

---

# 230. Documentation ADR

Add next available backend ADR if current pattern continues.

Likely concept:

```text
Staff/Admin Furniture Request Operational Management
```

Inspect latest number first.

Do not guess.

---

# 231. ADR Should Record

At minimum:

```text
REQ-004/005/006 activated
requests.view vs requests.manage
Staff operational ≠ ownership
operational queue filters
deterministic sorting
private resource
staff_internal_notes field-level access
original intake immutability
Phase 10.3 state-machine reuse
combined atomic update design
row locking/concurrency
no Request→Order side effects
no customer-account mutation
OpenAPI filter reconciliation if required
```

---

# 232. Group J Tracking

After successful Phase 10.7:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS/BLOCKED according to actual result
10.7 PASS
10.8 READY
```

Do not mark Group J closed yet.

---

# 233. Phase 10.8 Readiness

Phase 10.8 should become the final:

```text
Request/Enquiry integration
security
contract
regression
Group J closure
```

phase.

Do not start it automatically.

---

# 234. OpenAPI Verification

Verify:

```text
REQ-004
REQ-005
REQ-006
```

against implementation.

---

# 235. Expected OpenAPI Change

Ideally:

```text
NONE
```

except an already-frozen contract consistency correction for omitted REQ-004 filters if confirmed.

---

# 236. Schema

Expected:

```text
NONE
```

No new Request-management columns.

---

# 237. Dependencies

Expected:

```text
NONE
```

---

# 238. Frontend

Expected:

```text
NONE
```

---

# 239. Focused Test Files

Possible:

```text
OperationalFurnitureRequestListApiTest
OperationalFurnitureRequestDetailApiTest
OperationalFurnitureRequestUpdateApiTest
FurnitureRequestOperationalConcurrencyMysqlTest
```

Use repository naming conventions.

---

# 240. Regression Suites

Run:

```text
FurnitureRequest*
RequestStatus*
RequestableProductResolver*
Attachment*
```

as relevant.

---

# 241. Auth/RBAC Regressions

Run current:

```text
permission tests
Clerk auth tests
active-account middleware tests
role-boundary tests
```

---

# 242. Resource Privacy Regressions

Ensure Phase 10.7 does not expose internal notes through existing creation/customer resource.

---

# 243. Product Regression

Operational reads must not use live requestability as access authority.

Test:

```text
linked MTO request created
Product later unpublished
staff detail still works
```

---

# 244. Status Regression

Run complete:

```text
RequestStatusMachineTest
FurnitureRequestStatusTransitionTest
FurnitureRequestStatusConcurrencyMysqlTest
```

---

# 245. Attachment Regression

If 10.6 implemented attachment metadata:

run attachment authorization/privacy tests.

---

# 246. Full Verification Commands

Run:

```bash
php artisan test --filter=OperationalFurnitureRequest
php artisan test --filter=FurnitureRequest
php artisan test

vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Validate OpenAPI parse.

---

# 247. Route Verification

Report exact:

```text
GET /api/v1/requests
GET /api/v1/requests/{request}
PATCH /api/v1/requests/{request}
```

route names, middleware, permissions, controller actions, and ACTIVE/STUB state.

---

# 248. Completion Report — Phase Status

Return:

## Phase 10.7 status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 249. Completion Report — Routes

Report:

```text
REQ-004 GET /requests
REQ-005 GET /requests/{request}
REQ-006 PATCH /requests/{request}
```

and ACTIVE/STUB state.

---

# 250. Completion Report — Permissions

Report:

```text
REQ-004 → requests.view
REQ-005 → requests.view
REQ-006 → requests.manage
```

---

# 251. Completion Report — Actor Matrix

Report:

```text
Anonymous
CUSTOMER
STAFF no permission
STAFF requests.view
STAFF requests.manage
ADMIN
```

for each operation.

---

# 252. Completion Report — Queue Filters

Report exact implementation for:

```text
search
request_status
product_id
created_from
created_to
page
per_page
```

---

# 253. Completion Report — Sort

Report:

```text
created_at DESC
id ASC
```

---

# 254. Completion Report — Operational Resource

List exact exposed fields.

State exact rules for:

```text
staff_internal_notes
user_id
attachments
product
```

---

# 255. Completion Report — Update Allow-List

Exactly:

```text
request_status
staff_internal_notes
```

---

# 256. Completion Report — Lifecycle

Report:

```text
state machine reused
same-state behavior
invalid transition behavior
row locking
```

---

# 257. Completion Report — Notes

Report:

```text
string/null
normalization/bounds
customer notes unaffected
clear semantics
```

---

# 258. Completion Report — Atomicity

Report combined:

```text
status + staff_internal_notes
```

transaction behavior.

---

# 259. Completion Report — Immutability

Confirm Request intake remains immutable:

```text
product
quantity
contact
dimensions
material
color
notes
ownership
reference
attachments
```

except through their explicitly separate attachment workflow.

---

# 260. Completion Report — Customer Account Boundary

Confirm Staff management cannot modify:

```text
customer profile
role
credentials
account status
```

---

# 261. Completion Report — Side Effects

Confirm:

```text
Orders = NONE
Payments = NONE
Inventory = NONE
Cart = NONE
Quote = NONE
Notifications = NONE
```

---

# 262. Completion Report — OpenAPI

State:

```text
UNCHANGED
```

or exact filter consistency correction.

---

# 263. Completion Report — Schema

Expected:

```text
NONE
```

---

# 264. Completion Report — Dependencies

Expected:

```text
NONE
```

---

# 265. Completion Report — Frontend

Expected:

```text
NONE
```

---

# 266. Completion Report — MariaDB

If operational race test executed:

report:

```text
engine/version
class
scenario
iterations
result
```

---

# 267. Completion Report — Tests

Report:

```text
permission matrix
queue pagination
filters
search
sorting
detail privacy
status updates
internal notes
combined atomic rollback
immutability
IDOR/role attacks
historical Product independence
attachments
10.1–10.6 regressions
full suite
```

---

# 268. Completion Report — Quality

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

# 269. Completion Report — Group J

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS/BLOCKED
10.7 PASS/BLOCKED
10.8 READY/BLOCKED
```

---

# 270. Definition of Done

Phase 10.7 is complete when:

- REQ-004 operational collection is implemented;
- REQ-005 operational detail is implemented;
- REQ-006 controlled update is implemented;
- operational routes require authentication;
- anonymous actors are rejected;
- CUSTOMER cannot use operational routes;
- `requests.view` governs operational reads;
- `requests.manage` governs operational mutation;
- view-only Staff cannot PATCH;
- Staff operational access is not treated as ownership;
- Admin access remains permission-governed;
- operational queue uses global pagination;
- queue sorting is `created_at DESC, id ASC`;
- `search` is implemented against approved Request fields;
- `request_status` filter is CLOSED;
- `product_id` filter uses opaque ID;
- created date filters use ISO8601 UTC;
- unknown filters are rejected;
- filters apply before pagination;
- operational search does not become unrestricted customer search;
- resource is explicitly serialized;
- customer contact is exposed only as Request operational data;
- numeric DB user ID is not exposed;
- Product summary remains safe;
- archived/unpublished Product does not erase historical Request;
- attachment storage internals are not exposed;
- internal notes are properly permission-scoped;
- REQ-006 accepts only `request_status` and `staff_internal_notes`;
- all original customer intake fields remain immutable;
- request reference remains immutable;
- ownership remains immutable;
- lifecycle uses Phase 10.3 state machine;
- direct close remains valid;
- CLOSED remains terminal;
- same-status remains idempotent;
- invalid transition remains 409;
- notes-only updates work;
- status-only updates work;
- combined updates are atomic;
- invalid status rolls back note changes;
- same status + changed notes updates only notes;
- same status + same notes can be no-op;
- Request row is current-state locked for mutation;
- transient DB conflicts use existing bounded retry;
- business conflicts are not retried;
- no Request→Order conversion exists;
- no quote exists;
- no Payment is created;
- no Inventory mutation occurs;
- no Cart mutation occurs;
- no Notification is created;
- Staff cannot mutate customer profile/account;
- Staff cannot change customer roles/credentials;
- operational responses are `private, no-store`;
- OpenAPI is aligned with frozen REQ-004/005/006 behavior;
- no schema migration is added;
- no dependency is added;
- no frontend work occurs;
- full regressions pass;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean;
- diff check passes.

---

# 271. Out of Scope

Do not implement:

```text
Phase 10.8 Group J closure
General Enquiry Staff/Admin management
customer Request history unless already separately approved
Request deletion
bulk Request mutation
assignment to Staff
CRM/ticketing
CONTACTED status
quotation workflow
Request→Order conversion
production scheduling
payment
ClickPesa
inventory
notifications
email
chat
frontend
```

---

# 272. STOP Condition

STOP when authorized operations satisfy:

```text
requests.view
    ↓
GET /requests
GET /requests/{request}
    ↓
private operational representation
```

and:

```text
requests.manage
    ↓
PATCH /requests/{request}
    ↓
{
  request_status?,
  staff_internal_notes?
}
    ↓
one locked atomic Request update
```

with:

```text
original customer intake immutable
customer account untouched
Request ≠ Order
Request ≠ Payment
Request ≠ Quote
Request ≠ Inventory operation
```

Do not continue automatically to Phase 10.8.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.