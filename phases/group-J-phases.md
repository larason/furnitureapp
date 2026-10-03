# Phase 10.8 — Request/Enquiry Tests and Group J Closure Assessment

## 1. Purpose

Complete the final verification phase of:

```text
GROUP J — MADE-TO-ORDER REQUESTS AND ENQUIRIES
```

Phase 10.8 is primarily a:

```text
contract verification
integration testing
security regression
authorization verification
concurrency verification
privacy verification
attachment verification
audit verification
Group J closure assessment
```

phase.

Do not use Phase 10.8 to introduce unrelated business features.

Its job is to prove that the Request and Enquiry domains are production-safe within their approved V1 scope.

---

# 2. Group J Exit Condition

`AGENTS.md` defines:

```text
Non-purchase customer demand can be captured and managed separately from normal orders.
```

This is the authoritative Group J closure condition.

Interpret it strictly.

To close Group J, the repository must prove that:

```text
Furniture Request
≠ Order
≠ Cart
≠ Checkout
≠ Payment
≠ Inventory reservation
```

and:

```text
General Enquiry
≠ Furniture Request
≠ Order
≠ Payment
```

while still supporting the full approved operational lifecycle.

---

# 3. Current Phase Matrix

Before running or modifying tests, inspect the actual latest repository state.

Expected predecessor state:

```text
10.1 PASS — Furniture Request API
10.2 PASS — Request validation
10.3 PASS — Request status lifecycle
10.4 PASS — Product-linked Requests
10.5 PASS — General Enquiries
10.6 PASS/BLOCKED — Attachment handling; verify actual result
10.7 PASS/BLOCKED — Staff/Admin Request management; verify actual result
10.8 CURRENT
```

Do not assume 10.6 or 10.7 PASS merely because these instructions follow them.

---

# 4. Phase 10.8 vs Group J Closure

Keep two separate outcomes:

```text
Phase 10.8 status
```

and:

```text
Group J closure status
```

A valid outcome may be:

```text
Phase 10.8 = PASS
Group J = NOT CLOSED
```

if verification itself is complete but a prerequisite is unresolved.

Examples:

```text
attachment contract incomplete
REQ-001 still gated
ENQ-001 still gated
REQ-004/005/006 incomplete
mandatory audit missing
ENQ operational lifecycle incomplete where V1 requires it
```

Never mark Group J closed just because PHPUnit is green.

---

# 5. First Action — Inventory Actual Implementation

Before adding tests, inspect the exact repository implementation for:

```text
REQ-001..REQ-007
ENQ-001..ENQ-007
```

Create an implementation matrix:

```text
operation
route
controller
authorization
ACTIVE / STUB / GATED
test coverage
known blocker
```

Do not infer route readiness from documentation alone.

---

# 6. Required Request Operations

Verify the V1 Request contract coverage for:

```text
REQ-001 POST   /requests
REQ-002 GET    /me/requests
REQ-003 GET    /me/requests/{request}
REQ-004 GET    /requests
REQ-005 GET    /requests/{request}
REQ-006 PATCH  /requests/{request}
REQ-007 POST   /requests/{request}/attachments
```

If any frozen operation is intentionally deferred/not implemented:

report it explicitly as a Group J closure blocker unless the roadmap formally excludes it from the initial production scope.

---

# 7. Required Enquiry Operations

Likewise inventory:

```text
ENQ-001..ENQ-007
```

including at least the approved:

```text
anonymous/CUSTOMER creation
customer own retrieval
staff operational retrieval
staff close/management
attachment path
```

Do not silently equate “ENQ-001 works” with complete Enquiry domain closure.

---

# 8. Request Domain Permanent Regression Suite

Prefer a focused cross-phase suite such as:

```text
tests/Feature/GroupJFurnitureRequestRegressionTest.php
```

or repository-consistent equivalent.

Its job is to protect the major cross-component invariants.

Do not copy every detailed 10.1–10.7 unit case into one huge test.

---

# 9. Enquiry Domain Permanent Regression Suite

Similarly consider:

```text
tests/Feature/GroupJEnquiryRegressionTest.php
```

Use existing detailed tests where possible.

---

# 10. Request Creation — Anonymous

Prove:

```text
anonymous
→ REQ-001 allowed
→ user_id = null
→ explicit contact snapshot persisted
→ SUBMITTED
→ no Order
→ no Payment
→ no inventory mutation
```

---

# 11. Request Creation — CUSTOMER

Prove:

```text
authenticated CUSTOMER
→ user_id server-derived
→ explicit request-time contact required
→ no profile fallback
→ SUBMITTED
```

---

# 12. Invalid Bearer

Mandatory:

```text
invalid Authorization bearer
→ 401 INVALID_AUTHENTICATION
```

Never downgrade to anonymous.

Test both:

```text
REQ-001
ENQ-001
```

---

# 13. STAFF / ADMIN Customer Submission

For customer-style creation endpoints:

```text
REQ-001
ENQ-001
```

STAFF/ADMIN must not create customer submissions through those paths.

Assert:

```text
403 FORBIDDEN
```

according to current actor middleware.

---

# 14. Request Strict Input Contract

Permanent regression for the exact Request creation allow-list:

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

subject to JSON vs multipart transport.

---

# 15. Request Server-Controlled Rejection

Permanently reject:

```text
user_id
request_reference
request_status
staff_internal_notes
created_at
updated_at
order_id
payment_status
delivery_fee
quoted_price
price
```

---

# 16. Request Persistence Vocabulary Must Stay Private

Assert REQ-001 rejects:

```text
message
style
product_details
```

and output continues using:

```text
notes
```

not:

```text
message
```

---

# 17. Request Contact Rules

Permanent matrix:

```text
name + phone           → valid
name + email           → valid
name + phone + email   → valid
missing name           → invalid
missing both channels  → invalid
blank both channels    → invalid
```

Apply the same explicit-contact rule to authenticated Customer.

---

# 18. Request Contact Snapshot Independence

Sequence:

```text
create authenticated Request
change Customer profile
```

Historical Request contact remains unchanged.

---

# 19. Request Quantity

Protect:

```text
optional/nullable
strict integer
1..100
omitted stays null
```

No default 1.

---

# 20. Request Dimensions

Protect:

```text
optional/nullable
length/width/height/unit only
at least one measurement
JSON numbers
>0
<=10000
unit exactly cm
decimals preserved
```

Unknown nested fields rejected.

---

# 21. Request Free Text

Permanent bounds:

```text
name <=120
material <=500
color <=200
notes <=5000
```

Unicode preserved.

Meaningful notes newlines preserved.

---

# 22. Request Product — Custom Path

Prove:

```text
product_id omitted
```

and:

```text
product_id = null
```

both create custom Request with:

```text
product_id = null
product = null
```

without Product lookup dependency.

---

# 23. Request Product — Public MADE_TO_ORDER

Prove:

```text
public
active
published
not deleted
active Category
MADE_TO_ORDER
```

→ accepted.

---

# 24. Request Product — Public IN_STOCK

Prove:

```text
409 PRODUCT_NOT_REQUESTABLE
field: product_id
```

No Request persisted.

---

# 25. Request Hidden Product Masking

For:

```text
unknown
inactive
unpublished
soft-deleted
inactive Category
```

assert one indistinguishable public not-found family.

No hidden-state leakage.

---

# 26. Request Product Has No Inventory Dependency

Permanent regression:

```text
public MADE_TO_ORDER
zero ProductStock rows
→ requestable
```

No Variant/stock requirement.

---

# 27. Request Product Historical Independence

Sequence:

```text
create linked Request
unpublish/deactivate/type-change Product
```

Request:

```text
still exists
still readable by authorized actor
still transitionable
```

No live-catalog revalidation during lifecycle.

---

# 28. Request Lifecycle Matrix

Protect all 9 state combinations:

```text
SUBMITTED → SUBMITTED     no-op
SUBMITTED → IN_REVIEW     allowed
SUBMITTED → CLOSED        allowed

IN_REVIEW → SUBMITTED     conflict
IN_REVIEW → IN_REVIEW     no-op
IN_REVIEW → CLOSED        allowed

CLOSED → SUBMITTED        conflict
CLOSED → IN_REVIEW        conflict
CLOSED → CLOSED           no-op
```

---

# 29. CLOSED Terminal

Permanent regression:

```text
CLOSED
→ no transition to another status
```

Do not add reopen behavior.

---

# 30. Same-State Idempotence

Same-state update:

```text
success
no UPDATE
updated_at unchanged
```

where no other field changes.

---

# 31. Request Intake Immutability

Status/internal-note operations must never alter:

```text
product_id
quantity
name
phone
email
dimensions
material
color
notes/message
user_id
request_reference
created_at
```

---

# 32. REQ-004 Operational Queue

Prove authorized:

```text
STAFF requests.view
ADMIN authorized
```

may list Requests.

---

# 33. REQ-004 Unauthorized Matrix

Test:

```text
anonymous → 401
CUSTOMER → 403
STAFF without requests.view → 403
```

---

# 34. Request Queue Pagination

Verify:

```text
page
per_page
meta.pagination
```

and bounds:

```text
page >=1
per_page 1..100
```

---

# 35. Request Queue Sort

Permanent ordering:

```text
created_at DESC
id ASC
```

---

# 36. Request Queue Filters

Permanent tests for frozen filters:

```text
search
request_status
product_id
created_from
created_to
```

---

# 37. Request Queue Unknown Filter

Reject:

```text
user_id
customer_id
foo
```

with canonical 422.

---

# 38. Request Search Fields

Test search across approved fields:

```text
name
email
phone
reference
product
```

No global customer-account searching.

---

# 39. Request Detail Operational Privacy

REQ-005 should expose operational Request context, not unrelated account data.

Assert absence of:

```text
credentials
Clerk IDs
roles
other Orders
payments
billing details
```

---

# 40. `staff_internal_notes` Field-Level Authorization

Test exact approved behavior.

At minimum:

```text
CUSTOMER → never sees it
anonymous → never sees it
authorized operational Staff/Admin → only as permission permits
```

If `requests.view` alone does not authorize internal notes:

test that view-only Staff cannot see them.

---

# 41. REQ-006 View vs Manage

Permanent permission matrix:

```text
requests.view only:
GET list → yes
GET detail → yes
PATCH → 403

requests.manage:
PATCH → yes
```

according to seeded permission graph.

---

# 42. REQ-006 Strict Allow-List

Only:

```text
request_status
staff_internal_notes
```

Reject all intake/customer/commerce fields.

---

# 43. Staff Internal Notes

Test:

```text
set notes
clear with null
notes-only update
combined status+notes
```

---

# 44. Customer Notes vs Staff Notes

Assert staff note update does not mutate:

```text
notes
```

customer field.

---

# 45. Combined Update Atomicity

Mandatory:

```text
valid status + notes
→ both commit
```

and:

```text
invalid status transition + new notes
→ neither commits
```

---

# 46. Combined Update Race

On MariaDB, where implemented:

```text
Staff A: CLOSED + note B
Staff B stale: IN_REVIEW + note C
```

Final must never reopen.

Losing transaction must not overwrite notes improperly.

---

# 47. Mandatory Request Status Audit

Business rules require audit for Request status changes.

Verify real persistence of at least:

```text
actor_id
actor role
action
resource type
resource id
previous status
new status
timestamp
request_id
result
```

according to the repository's canonical audit model.

Do not accept logging alone if the contract requires audit records.

---

# 48. Request Audit — Valid Transition

Example:

```text
SUBMITTED → IN_REVIEW
```

must create exactly the required audit record.

---

# 49. Request Audit — Direct Close

Audit:

```text
SUBMITTED → CLOSED
```

---

# 50. Request Audit — Invalid Transition

Inspect the approved audit semantics.

If failed privileged actions are required to be audited:

assert failure result.

If current audit contract records only successful mutations:

do not invent extra semantics.

But report exactly what is implemented.

---

# 51. Same-State Audit

Determine the approved behavior.

Avoid creating duplicate “transition” audit entries if no state transition occurred unless audit contract intentionally records attempted operation.

Document/test one deterministic behavior.

---

# 52. Internal Notes Audit

Verify whether privileged internal-note mutation belongs to generic audit requirements.

If current audit contract says privileged Request mutation is audited, test it.

Do not infer beyond source.

---

# 53. Attachment Creation — Request

If 10.6 is PASS, test REQ-001:

```text
JSON no attachment → valid
multipart no attachment → valid
multipart one valid attachment → valid
```

---

# 54. Attachment Creation — Enquiry

Same for ENQ-001.

---

# 55. Attachment V1 Cardinality

Protect:

```text
0 or 1 attachment per parent
```

No second attachment.

No replacement semantics unless frozen.

---

# 56. Attachment Size

Boundary:

```text
5 MiB - 1 accepted
5 MiB accepted
5 MiB + 1 rejected
```

---

# 57. Attachment Types

Accept actual:

```text
JPEG
PNG
WebP
PDF
```

Reject:

```text
GIF
SVG
TXT
ZIP
```

---

# 58. MIME Spoofing

Mandatory security regressions:

```text
text bytes + .jpg
PDF bytes + client image/jpeg
invalid image signature
```

Client MIME/extension must not be security authority.

---

# 59. Filename Safety

Protect against:

```text
../
..\
slashes
control chars
NUL
oversized filename
```

No path traversal.

---

# 60. Private Storage

Assert attachment storage is not public.

No:

```text
public ACL
permanent public URL
storage_key in response
```

---

# 61. Attachment Resource

Only approved metadata:

```text
id
filename
content_type
size
url
```

with private/temporary semantics.

---

# 62. Storage Internals Hidden

Assert absence:

```text
storage_key
disk
bucket
capability digest
numeric DB ID
```

---

# 63. Inline Attachment Atomicity

Request and Enquiry separately:

```text
valid parent + valid file → both persist
invalid parent + valid file → neither
valid parent + invalid file → neither
storage failure → parent rolls back
DB failure after file write → orphan cleaned
```

---

# 64. Separate Upload Capability

If REQ-007/ENQ-007 are implemented, permanently test:

```text
correct parent + valid capability → success
wrong parent → rejected
wrong parent type → rejected
expired token → rejected
consumed token → rejected
random token → rejected
anonymous missing token → rejected
```

---

# 65. Capability Security

Assert:

```text
raw token not stored
raw token not logged
parent ID alone not enough
```

---

# 66. Capability Single-Use Race

If persisted capability mechanism exists:

run MariaDB race:

```text
same capability
two concurrent uploads
→ at most one attachment
```

Losing stored file must be cleaned.

---

# 67. Customer Cross-Parent Attachment IDOR

Customer A cannot upload/view attachment metadata for Customer B parent.

Use masked not-found behavior where frozen.

---

# 68. Anonymous Attachment Retrieval

Creating anonymously or holding upload capability must not imply:

```text
public parent retrieval
public attachment retrieval
```

---

# 69. General Enquiry Creation — Anonymous

Prove:

```text
name explicit
phone/email at least one
subject
message
OPEN
user_id null
```

---

# 70. General Enquiry Creation — CUSTOMER

Prove trusted fallback behavior:

```text
submitted valid contact wins
missing contact can derive from trusted account/profile
historical snapshot persisted
```

---

# 71. Enquiry Supplied Invalid Contact

Even with valid fallback available:

```text
supplied invalid email/phone
→ reject
```

Do not silently ignore.

---

# 72. Enquiry Contact Snapshot Independence

After Customer profile change:

historical Enquiry contact remains unchanged.

---

# 73. Enquiry Subject

Protect:

```text
required
5..200
plain text
```

---

# 74. Enquiry Message

Protect:

```text
required
10..5000
plain text
newlines preserved
```

---

# 75. Enquiry Category

Closed:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
```

nullable/optional according to frozen contract.

---

# 76. Enquiry Product Context

Both:

```text
public IN_STOCK
public MADE_TO_ORDER
```

must be allowed.

This is an important regression against accidental use of `RequestableProductResolver`.

---

# 77. Enquiry Hidden Product

Unknown/inactive/unpublished/soft-deleted/inactive Category:

masked public not-found behavior.

---

# 78. Enquiry No Inventory Dependency

No stock requirement.

No reservation.

---

# 79. Enquiry Anonymous Order Association

Anonymous:

```text
order_id != null
→ reject
```

unless the repository now contains a separately approved scoped-order-access mechanism.

Do not authorize by knowledge of Order ID/reference.

---

# 80. Enquiry Customer Owned Order

Authenticated Customer:

```text
owned Order → valid
foreign Order → masked 404
unknown Order → same masked family
```

---

# 81. Enquiry Product + Order Together

Valid when each relation independently passes authorization/domain rules.

---

# 82. Enquiry Neither Association

Still valid.

---

# 83. Enquiry No Order Mutation

Association must never:

```text
change Order status
create Order
create history
create Payment
change delivery
```

---

# 84. Enquiry OPEN Default

Every created Enquiry:

```text
OPEN
```

server-side.

---

# 85. Enquiry CLOSED Lifecycle

Inspect actual Phase 10.5/later implementation.

The approved workflow includes staff close behavior (`ENQ-006`).

If implemented:

test:

```text
OPEN → CLOSED
```

and original customer message immutability.

If not implemented:

report as a Group J closure blocker if ENQ-006 remains part of frozen V1.

---

# 86. No Enquiry Reopen Unless Approved

Do not add:

```text
CLOSED → OPEN
```

unless an explicit approved decision exists.

---

# 87. Enquiry Staff Operational Routes

Inspect actual implementation for:

```text
ENQ-004
ENQ-005
ENQ-006
```

If frozen and implemented:

verify analogous operational authorization:

```text
enquiries.view
enquiries.manage/close
```

according to actual contract.

If absent:

do not silently close Group J.

---

# 88. Enquiry Operational Privacy

Staff should see:

```text
contact
subject/message
product/order context
attachments
internal notes where authorized
```

but not unrelated customer account information.

---

# 89. Enquiry Staff Internal Notes

Never customer-visible.

Test explicitly.

---

# 90. Mandatory Enquiry Status Audit

Business rules require Enquiry status changes to be audited.

If ENQ-006 closes:

```text
OPEN → CLOSED
```

verify mandatory audit record contains approved actor/action/resource/previous→new/request-id information.

---

# 91. Request vs Enquiry Separation

Permanent cross-domain tests:

Creating Request:

```text
does not create Enquiry
```

Creating Enquiry:

```text
does not create Request
```

No automatic conversion either direction.

---

# 92. Request vs Order Separation

Request operations must never create:

```text
Order
OrderItem
Payment
reservation
allocation
```

---

# 93. Enquiry vs Order Separation

Same.

---

# 94. Cart Independence

No Request/Enquiry operation should create/mutate Cart.

---

# 95. Payment Independence

No ClickPesa/provider calls.

No Payment records.

Group H remains deferred.

---

# 96. Inventory Independence

For Request/Enquiry operations:

```text
physical quantity unchanged
reserved_quantity unchanged
allocation count unchanged
```

---

# 97. Pricing Independence

No authoritative:

```text
quoted_price
price
subtotal
total
delivery_fee
```

created by Group J intake/management.

---

# 98. Production Promise Independence

Request `CLOSED` must not mean:

```text
manufactured
delivered
approved
paid
```

Keep minimal semantics.

---

# 99. Notifications

Group R owns notifications.

If no approved integration exists yet:

assert Group J operations do not accidentally create Notification rows.

However do not fail an already-approved event hook simply because notification delivery exists elsewhere.

Use actual current architecture.

---

# 100. Customer Request Retrieval

The frozen domain includes:

```text
REQ-002 GET /me/requests
REQ-003 GET /me/requests/{request}
```

Verify actual implementation.

If active:

test ownership:

```text
Customer own → 200
Customer A→B → 404 masked
anonymous Request excluded from /me
```

---

# 101. Customer Request Collection

If implemented:

```text
created_at DESC
id ASC
global pagination
```

and no `staff_internal_notes`.

---

# 102. Customer Enquiry Retrieval

Likewise verify:

```text
ENQ-002
ENQ-003
```

if frozen implementation exists.

Customer A→B must be masked 404.

Anonymous Enquiries do not appear under `/me`.

---

# 103. Anonymous Retrieval

Permanent security regression:

```text
anonymous Request creation ≠ anonymous read permission
anonymous Enquiry creation ≠ anonymous read permission
```

Opaque ID is not a bearer credential.

---

# 104. Staff vs Customer Route Separation

Customer private routes:

```text
/me/...
```

Operational Staff routes:

```text
/requests
/enquiries
```

Do not allow cross-surface privilege confusion.

---

# 105. Permission Matrix — Request

At minimum test:

```text
Anonymous
CUSTOMER
STAFF no permission
STAFF requests.view
STAFF requests.manage
ADMIN
```

across REQ operational endpoints.

---

# 106. Permission Matrix — Enquiry

Likewise:

```text
Anonymous
CUSTOMER
STAFF no permission
authorized Staff
ADMIN
```

according to exact enquiry permissions.

---

# 107. Suspended/Inactive Actors

Preserve hardened account-state behavior.

Suspended/inactive authenticated actor must not bypass Request/Enquiry operational routes.

---

# 108. Mixed-Role Security

If mixed-role accounts are rejected elsewhere:

include Request/Enquiry operational routes in that regression.

---

# 109. Rate Limiting

Verify anonymous creation endpoints retain appropriate limiter:

```text
REQ-001
ENQ-001
```

and respond:

```text
429
Retry-After
```

according to existing middleware.

---

# 110. Body-Size Limit

Attachment-enabled public mutations must retain hardened request-size enforcement.

A valid ≤5 MiB file plus multipart overhead must fit approved ceiling.

Clearly oversized request must fail early.

---

# 111. Content-Type Contract

Protect:

```text
application/json without file
multipart/form-data
```

as approved.

Do not accidentally permit:

```text
application/x-www-form-urlencoded
```

unless frozen.

---

# 112. JSON Strictness

Request JSON:

```json
{"quantity":"2"}
```

remains invalid.

Multipart may intentionally decode transport string `2` into integer semantics.

Test both paths.

---

# 113. Multipart Strictness

Multipart must not bypass:

```text
unknown field rejection
server-controlled field rejection
product rules
contact rules
order ownership
```

---

# 114. Cache Privacy

All private Request/Enquiry reads/updates:

```text
Cache-Control: private, no-store
```

No public/CDN caching.

---

# 115. `Vary: Authorization`

Verify on authenticated private resources where current response conventions require it.

---

# 116. No Sensitive Logging

Regression against logs containing:

```text
Authorization bearer
upload capability
email
phone
notes/message
staff_internal_notes
storage key
```

where practical.

Do not require brittle exact-log tests unless logging architecture supports capture.

---

# 117. No Raw Exception Leakage

Failure responses must never expose:

```text
SQL
filesystem paths
bucket names
stack traces
PHP temp files
Clerk internals
```

---

# 118. Explicit Serialization

No Request/Enquiry public/staff route may mass serialize:

```php
$model->toArray()
```

into API output.

Protect representative hidden fields in tests.

---

# 119. Request Hidden Fields

Ensure external resources omit persistence-only:

```text
message
style
product_details
numeric id
storage internals
```

according to actor representation.

---

# 120. Enquiry Hidden Fields

Ensure customer-facing output omits:

```text
staff_internal_notes
internal IDs
credentials
payment internals
```

---

# 121. Deterministic Error Codes

Permanent Request/Enquiry tests should cover:

```text
MISSING_REQUIRED_FIELD
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
RESOURCE_NOT_FOUND
PRODUCT_NOT_REQUESTABLE
CONFLICT
FORBIDDEN
INVALID_AUTHENTICATION
```

as relevant.

---

# 122. Attachment Error Codes

Protect:

```text
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
UNSUPPORTED_ATTACHMENT_TYPE
```

if 10.6 completed them.

---

# 123. Error Registry/OpenAPI Consistency

Verify every Group J emitted error code is present in:

```text
ApiErrorCode
OpenAPI global error enum
normative contract docs
```

No implementation-only code.

---

# 124. OpenAPI REQ Contract

Add/extend contract tests for:

```text
REQ-001..REQ-007
```

operation IDs, methods, paths, schemas, error responses, permissions descriptions, multipart definitions, filters.

---

# 125. OpenAPI ENQ Contract

Same for:

```text
ENQ-001..ENQ-007
```

---

# 126. REQ-004 Filter Drift

Verify OpenAPI documents frozen:

```text
search
request_status
product_id
created_from
created_to
page
per_page
```

If prior Phase 10.7 corrected this, lock it with a regression test.

If still absent:

classify as contract-consistency blocker/correction.

---

# 127. Multipart OpenAPI

Verify:

```text
REQ-001
ENQ-001
```

correctly advertise both approved JSON/no-file and multipart/file transports.

---

# 128. Attachment Capability Contract

If REQ-007/ENQ-007 use a creation-returned capability:

verify the response field is actually defined in frozen OpenAPI/resources.

No undocumented security token.

---

# 129. Docs Consistency

Review:

```text
AGENTS.md
api-contract.md
api-conventions.md
api-resources.md
business-rules.md
openapi.yaml
decisions.md
group-J-phases.md
```

for material contradictions.

Do not rewrite frozen contract merely to fit implementation.

---

# 130. Audit Is a Closure Gate

The domain business rules explicitly require audit for:

```text
request status changes
enquiry status changes
```

Therefore:

```text
functional status mutation works
but no required audit
```

means:

```text
Group J = NOT CLOSED
```

unless an approved decision has formally deferred that requirement.

---

# 131. Audit Integrity

Audit actor must be server-derived.

Client cannot set:

```text
actor_id
actor_role
timestamp
request_id
previous state
new state
result
```

---

# 132. Audit Must Not Contain Secrets

Do not store:

```text
bearer tokens
upload capabilities
passwords
storage credentials
```

---

# 133. Audit Status Accuracy

For Request transition:

```text
previous
new
```

must correspond to the locked state actually committed.

Do not audit a stale pre-lock state.

---

# 134. Audit and Transaction Semantics

Inspect current approved audit infrastructure.

If status update succeeds:

audit must reflect the committed effect.

If audit is mandatory and synchronous:

ensure failure semantics follow existing audit architecture.

Do not invent a second audit system.

---

# 135. MariaDB — Request Status Concurrency

Execute the existing:

```text
FurnitureRequestStatusConcurrencyMysqlTest
```

against disposable MariaDB.

Do not leave it only skipped for Group J closure.

---

# 136. MariaDB — Operational Update Race

If Phase 10.7 introduced combined status/internal-notes updates:

run the corresponding race test.

At minimum prove stale update cannot reopen CLOSED.

---

# 137. MariaDB — Attachment Capability Race

If Phase 10.6 uses persisted single-use capabilities:

execute the same-token concurrent upload race.

---

# 138. SQLite vs MariaDB Split

Document:

```text
SQLite:
validation
authorization
serialization
persistence relationships
rollback
functional behavior

MariaDB:
FOR UPDATE
concurrent status serialization
single-use token race
DB-specific constraints
```

Never claim SQLite proves row-lock behavior.

---

# 139. Disposable DB Safety

Use only:

```text
furnitureapp_test_disposable
```

or current guarded disposable MariaDB database.

Never destructive-test the application DB.

---

# 140. Database Constraints

If 10.6 added attachment schema:

verify on both drivers as appropriate:

```text
FKs
parent integrity
one attachment per parent
XOR/check constraints where applicable
delete behavior
```

---

# 141. Migration Rebuild

If Group J introduced schema in 10.6:

prove fresh migration rebuild succeeds.

No historical migration edits.

---

# 142. Attachment Orphan Cleanup

Use storage fake/failure injection to prove:

```text
DB rollback after file write
→ stored file removed
```

---

# 143. No Content Deduplication

Repeated identical Request/Enquiry submissions remain distinct.

Test representative duplicate submission.

---

# 144. No Idempotency-Key Requirement

REQ-001/ENQ-001 do not require idempotency.

Do not accidentally inherit Checkout behavior.

---

# 145. REQ-006 Same-State Idempotence

Semantic same-state replay remains valid.

No external Idempotency-Key needed.

---

# 146. Enquiry Close Idempotence

Inspect actual ENQ-006 contract.

If close is idempotent per current implementation, protect it.

If not specified, do not invent semantics.

---

# 147. No Delete Operations

Ensure no unintended public:

```text
DELETE /requests/{request}
DELETE /enquiries/{enquiry}
```

were introduced.

---

# 148. No Request→Order API

Ensure no:

```text
convert_to_order
create_order
approve_to_order
```

endpoint/field exists.

---

# 149. No Price Fields

Request/Enquiry API must not expose authoritative:

```text
quoted_price
total
payment_amount
delivery_fee
```

---

# 150. No Production Workflow Creep

No statuses like:

```text
QUOTED
APPROVED
PRODUCING
REJECTED
CONTACTED
```

unless separately frozen.

---

# 151. Group H/I Deferral Regression

Because transactional commerce is deliberately deferred, Group J must remain independently usable.

Tests must not require:

```text
Checkout active
Payment provider configured
Order-management Group I
```

for Request/Enquiry workflows.

---

# 152. Initial Production Mode

Confirm current production-scope invariant remains possible:

```text
MADE_TO_ORDER catalog
→ Request workflow
```

without requiring Cart/Checkout/Payment.

---

# 153. Requestable vs Purchasable Regression

Critical cross-domain permanent test:

```text
MADE_TO_ORDER
→ REQ-001 requestable
→ Cart/Checkout not purchasable
```

---

# 154. Enquiry Product Independence

Both Product types may be enquired about.

Protect against future regression:

```text
IN_STOCK → enquiry allowed
MADE_TO_ORDER → enquiry allowed
```

---

# 155. Customer Account Boundary

Staff operational Request/Enquiry access must never enable:

```text
change role
suspend customer
edit customer profile
reset credentials
restrict ordering
```

Add authorization/regression checks where feasible.

---

# 156. Search Privacy

Operational search must operate on authorized Request/Enquiry datasets only.

Do not turn search into generic customer lookup.

---

# 157. Attachment Parent Authorization

Attachment permissions inherit the parent.

A Staff permission on Request does not imply access to unrelated Enquiry unless separately authorized.

---

# 158. Resource Identifier Safety

Public IDs:

```text
req_...
enq_...
att_...
prod_...
ord_...
user_...
```

where approved.

No raw numeric DB IDs in API responses.

---

# 159. Route Inventory

Run:

```bash
php artisan route:list
```

and build a Group J route table.

Report every:

```text
REQ-*
ENQ-*
```

route as:

```text
ACTIVE
STUB
GATED
MISSING
```

---

# 160. Group J Cannot Close With Required Stub

If a frozen V1 Group J operation remains:

```text
501
stub
unconditionally gated
```

and is necessary to the Group J exit condition:

```text
Group J = NOT CLOSED
```

Do not hide it behind “tests pass.”

---

# 161. Creation Route Readiness

Explicitly report:

```text
POST /api/v1/requests
POST /api/v1/enquiries
```

ACTIVE/GATED.

Given initial production scope, these are core paths.

---

# 162. Operational Route Readiness

Report:

```text
REQ-004
REQ-005
REQ-006
ENQ-004
ENQ-005
ENQ-006
```

ACTIVE/GATED.

---

# 163. Attachment Route Readiness

Report:

```text
REQ-007
ENQ-007
```

and any scoped capability blocker.

---

# 164. Customer History Route Readiness

Report:

```text
REQ-002
REQ-003
ENQ-002
ENQ-003
```

because these are part of frozen V1 ownership semantics.

---

# 165. Full Request Flow

Group J closure should be able to demonstrate:

```text
Anonymous/CUSTOMER
→ submit Furniture Request
→ optional secure attachment
→ SUBMITTED
→ authorized Staff queue
→ Staff detail
→ IN_REVIEW
→ CLOSED
```

with no Order/payment/inventory side effects.

---

# 166. Full Enquiry Flow

Likewise:

```text
Anonymous/CUSTOMER
→ submit Enquiry
→ optional Product/Order context
→ optional secure attachment
→ OPEN
→ authorized operational handling
→ CLOSED where approved
```

without modifying Product/Order commerce state.

---

# 167. Anonymous Request Flow

Anonymous can submit.

Anonymous cannot later retrieve simply using Request ID.

---

# 168. Anonymous Enquiry Flow

Same.

---

# 169. Customer Request Flow

Authenticated Customer submission ownership must be server-derived.

Where customer history routes exist:

they can see own only.

---

# 170. Customer Enquiry Flow

Same.

---

# 171. Staff Operational Request Flow

`requests.view` / `requests.manage` boundaries proven.

---

# 172. Staff Operational Enquiry Flow

Equivalent approved permissions proven where implemented.

---

# 173. Admin Boundary

Admin has approved higher operational authority but remains field-minimized.

Do not return unrelated account/private data.

---

# 174. Full Canonical PHPUnit Suite

Run:

```bash
php artisan test
```

Report:

```text
tests
passed
failed
skipped
assertions
```

---

# 175. Focused Group J Suite

Run focused Request and Enquiry suites.

Example:

```bash
php artisan test --filter=FurnitureRequest
php artisan test --filter=Enquiry
php artisan test --filter=Attachment
```

Use actual class names.

---

# 176. PHPStan

Run:

```bash
vendor/bin/phpstan analyse
```

Required:

```text
0 errors
```

---

# 177. Pint

Run:

```bash
vendor/bin/pint --test
```

---

# 178. Composer Audit

Run:

```bash
composer audit
```

Must be clean or report exact unresolved advisory.

Do not conceal a vulnerability behind Group J closure.

---

# 179. Diff Check

Run:

```bash
git diff --check
```

---

# 180. OpenAPI Parse

Parse:

```text
docs/api/openapi.yaml
```

Report success.

---

# 181. Route List

Run and verify Group J operation IDs/paths.

---

# 182. MariaDB Suite

Run all Group J MariaDB integration tests actually required by implemented concurrency:

```text
Request lifecycle
operational Request mutation
attachment capability
```

where present.

---

# 183. Existing Cross-Domain Regressions

Run relevant:

```text
Product visibility
Cart MADE_TO_ORDER rejection
Checkout MADE_TO_ORDER rejection
Order ownership for Enquiry order association
Auth/RBAC
security middleware
```

---

# 184. No Feature Work to Make Tests Convenient

Do not change contract merely because a test is hard.

Do not weaken:

```text
authorization
attachment security
visibility rules
status state machine
audit
```

to make suite green.

---

# 185. Allowed Fixes

Small verified defects directly within Group J may be corrected.

Examples:

```text
wrong error mapping
unsafe field exposure
filter bug
missing rollback
status race
resource leak
attachment cleanup bug
audit mismatch
OpenAPI omission of already-frozen field/filter/code
```

---

# 186. Not Allowed as “Test Fix”

Do not:

```text
delete failing tests
skip valid security cases
reduce concurrency iterations merely to hide race
change CLOSED enums
broaden permissions
make storage public
drop audit requirement
change customer contact rules
```

---

# 187. Contract Consistency Corrections

If frozen prose/resources/OpenAPI disagree:

identify the exact contradiction.

Do not silently choose whichever is easiest.

Use established post-freeze reconciliation discipline.

---

# 188. Group J ADR

If project convention uses a closure ADR, add the next available one.

Likely concept:

```text
Group J Request/Enquiry Verification and Closure Assessment
```

Do not assume ADR number.

---

# 189. Closure ADR Content

Record:

```text
REQ route coverage
ENQ route coverage
actor/ownership model
request validation
product-link rules
enquiry Product/Order associations
lifecycle behavior
staff operational permissions
attachments/security
audit proof
SQLite/MariaDB split
privacy/caching
commerce separation
route activation
remaining blockers
Group J closure decision
```

---

# 190. Update Group J Phase Tracking

Return actual states:

```text
10.1 PASS/BLOCKED
10.2 PASS/BLOCKED
10.3 PASS/BLOCKED
10.4 PASS/BLOCKED
10.5 PASS/BLOCKED
10.6 PASS/BLOCKED
10.7 PASS/BLOCKED
10.8 PASS/BLOCKED
```

Do not mechanically copy prior reports if repository evidence now contradicts them.

---

# 191. Closure Decision Matrix

Use an explicit matrix:

```text
Furniture Request creation complete?
Request validation complete?
Request linked-product rules complete?
Request lifecycle complete?
Request staff operational management complete?
Request customer ownership/retrieval complete where frozen?
Request attachment contract complete?

Enquiry creation complete?
Enquiry ownership/retrieval complete where frozen?
Enquiry Product/Order association complete?
Enquiry operational management complete?
Enquiry status close lifecycle complete?
Enquiry attachment contract complete?

Mandatory audit complete?
All private routes authorized?
No IDOR?
No permanent public attachment URLs?
MariaDB concurrency gates passed?
No commerce side effects?
OpenAPI aligned?
No required Group J route left stubbed/gated?
```

Group J closes only if every required answer is YES.

---

# 192. Definition of Phase 10.8 PASS

Phase 10.8 itself is PASS when:

- actual Group J implementation has been inventoried;
- permanent Request regression coverage exists;
- permanent Enquiry regression coverage exists;
- Request actor boundaries are tested;
- Enquiry actor boundaries are tested;
- strict creation validation is tested;
- contact rules are tested;
- Request Product eligibility is tested;
- Enquiry Product association is tested;
- Enquiry Order ownership is tested;
- lifecycle rules are tested;
- staff operational authorization is tested;
- field-level privacy is tested;
- attachments are tested if implemented;
- attachment security is tested;
- audit behavior is tested;
- Request/Enquiry separation is tested;
- Request/Order separation is tested;
- zero inventory/payment side effects are tested;
- cache/privacy behavior is tested;
- OpenAPI contract is guarded;
- route state is verified;
- SQLite canonical suite passes;
- required MariaDB races execute or are explicitly reported as closure blockers;
- PHPStan passes;
- Pint passes;
- Composer audit passes;
- diff check passes;
- Group J closure decision is stated separately.

---

# 193. Definition of Group J CLOSED

Group J may be marked:

```text
CLOSED
```

only when the application can demonstrate the complete approved non-purchase-demand workflow:

```text
customer/anonymous intent
→ Request or Enquiry
→ secure validation
→ optional secure attachment
→ private persistence
→ correct ownership
→ authorized operational handling
→ controlled lifecycle
→ mandatory audit
```

while also proving:

```text
no automatic Order
no Payment
no inventory reservation
no checkout dependency
no public data leakage
no staff customer-account overreach
```

and no required Group J API remains an unresolved stub/gate.

---

# 194. Initial Production Relevance

Remember current initial production mode:

```text
MADE_TO_ORDER only
```

Therefore Group J is not a peripheral subsystem.

It is the primary production customer-conversion workflow.

A closure blocker affecting:

```text
REQ-001
staff Request handling
attachments where required
```

should be treated seriously.

---

# 195. Group H/I Are Still Deferred

Do not use Phase 10.8 to restart:

```text
Group H Payments
Group I Order Management
```

Group J must remain independently operational.

---

# 196. Next Stage

If Group J closes:

move to the next roadmap group that matches the deliberate production scope.

Based on `AGENTS.md`, the nominal next backend group is:

```text
Group K — Admin Backend Operations
```

However do not automatically implement it.

Only report:

```text
Group K — READY
```

if dependencies relevant to its requested phases are satisfied.

Do not resume Groups H/I merely because Group J finished.

---

# 197. Completion Report — Phase Status

Return:

## Phase 10.8 status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 198. Completion Report — Group J

Separately:

```text
Group J: CLOSED
```

or:

```text
Group J: NOT CLOSED
```

---

# 199. Completion Report — Request Routes

Provide a table/state summary:

```text
REQ-001
REQ-002
REQ-003
REQ-004
REQ-005
REQ-006
REQ-007
```

with:

```text
ACTIVE
STUB
GATED
MISSING
```

---

# 200. Completion Report — Enquiry Routes

Same:

```text
ENQ-001..ENQ-007
```

---

# 201. Completion Report — Request Domain

Report:

```text
actor model
validation
contact rule
product rule
status lifecycle
ownership
operational permissions
attachments
audit
```

---

# 202. Completion Report — Enquiry Domain

Report:

```text
actor model
contact derivation
subject/message/category
Product association
Order association/ownership
status lifecycle
operational permissions
attachments
audit
```

---

# 203. Completion Report — Security

Report:

```text
anonymous boundaries
invalid bearer
CUSTOMER IDOR
STAFF permission boundaries
Admin data minimization
attachment privacy
capability security
cache policy
logging privacy
```

---

# 204. Completion Report — Audit

State exactly:

```text
Request status audit PASS/BLOCKED
Enquiry status audit PASS/BLOCKED
```

and fields recorded.

---

# 205. Completion Report — Attachments

Report:

```text
inline Request attachment
inline Enquiry attachment
separate upload
max size
allowed types
signature detection
private storage
URL policy
capability behavior
orphan cleanup
```

---

# 206. Completion Report — MariaDB

Report:

```text
executed YES/NO
server/version
test classes
race scenarios
iterations
result
```

---

# 207. Completion Report — Commerce Separation

Confirm:

```text
Orders created = NONE
Payments created = NONE
Inventory reservations = NONE
Cart mutations = NONE
Quotes = NONE
ClickPesa calls = NONE
```

for Group J.

---

# 208. Completion Report — Schema

Report any Group J schema added, especially Phase 10.6 attachments.

Do not claim NONE if attachment metadata migration exists.

---

# 209. Completion Report — Dependencies

Report actual dependency changes.

---

# 210. Completion Report — Frontend

Expected:

```text
NONE
```

---

# 211. Completion Report — OpenAPI

Report:

```text
aligned
```

plus exact consistency corrections made during Group J.

---

# 212. Completion Report — Tests

Report:

```text
focused Request tests
focused Enquiry tests
Attachment tests
Operational tests
Audit tests
Security tests
MariaDB tests
full suite totals
assertions
```

---

# 213. Completion Report — Quality

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

# 214. Completion Report — Exact Blockers

If Group J is not closed, list exact blockers.

Good:

```text
1. ENQ-006 remains a 501 stub.
2. Request status transitions are not audited.
3. REQ-007 scoped capability contract is unresolved.
```

Bad:

```text
more work needed
```

---

# 215. Completion Report — Group J Phase Matrix

Return:

```text
10.1 ...
10.2 ...
10.3 ...
10.4 ...
10.5 ...
10.6 ...
10.7 ...
10.8 ...
```

---

# 216. Completion Report — Next Stage

If Group J is CLOSED:

```text
Group K — READY
```

subject to actual dependencies.

If Group J is NOT CLOSED:

report only the exact remediation needed.

Do not begin remediation beyond small verified defects within Phase 10.8 scope unless necessary to make an already-approved behavior correct.

---

# 217. Out of Scope

Do not implement:

```text
ClickPesa
Payments
Checkout activation
Order lifecycle
Request→Order conversion
quotation engine
manufacturing workflow
staff assignment system
CRM
chat/messaging
email
notifications beyond already-approved infrastructure
frontend
new design system
```

---

# 218. STOP Condition

STOP when you can provide an evidence-backed summary:

```text
Phase 10.8:
PASS / BLOCKED

Furniture Requests:
PASS / BLOCKED

General Enquiries:
PASS / BLOCKED

Attachments:
PASS / BLOCKED

Operational Staff/Admin management:
PASS / BLOCKED

Mandatory audit:
PASS / BLOCKED

SQLite verification:
PASS / BLOCKED

MariaDB concurrency:
PASS / BLOCKED

Group J:
CLOSED / NOT CLOSED

Remaining blockers:
1. ...
2. ...
```

Do not automatically continue to Group K.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.