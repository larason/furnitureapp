# Group J Closure Remediation

## 1. Purpose

Perform a **targeted Group J closure remediation** against the exact blockers discovered by Phase 10.8.

This is **not**:

```text
Phase 10.9
```

and must not reopen already-PASS architecture from Phases 10.1–10.8.

Current established state:

```text
10.1 PASS — Furniture Request API
10.2 PASS — Request validation
10.3 PASS — Request status lifecycle
10.4 PASS — Product-linked Requests
10.5 PASS — General Enquiries
10.6 implemented attachment foundation
10.7 implemented Request operational management
10.8 PASS — verification / closure assessment

Group J = NOT CLOSED
```

Exact remaining blockers from Phase 10.8:

```text
1. Customer Request history endpoints remain stubbed.
2. Customer Enquiry history endpoints remain stubbed.
3. Enquiry operational endpoints / close lifecycle remain stubbed.
4. REQ-007 / ENQ-007 attachment capability upload routes remain unimplemented.
5. REQ-001 remains configuration-gated.
6. Required MariaDB Group J concurrency proof has not executed.
```

The purpose of this remediation is to resolve those blockers and nothing else.

---

# 2. Group J Exit Condition

The authoritative `AGENTS.md` exit condition remains:

```text
Non-purchase customer demand can be captured
and managed separately from normal orders.
```

Group J may close only when the complete approved Request/Enquiry journey is usable:

```text
Customer / Anonymous
→ Request or Enquiry intake
→ optional secure attachment
→ private persistence

Authenticated Customer
→ own Request/Enquiry history

Authorized Staff/Admin
→ operational queue/detail
→ controlled status management
→ mandatory audit
```

while maintaining:

```text
Order creation = NONE
Payment creation = NONE
ClickPesa calls = NONE
Cart mutation = NONE
Inventory reservation = NONE
Price/quote authority = NONE
```

---

# 3. Scope Discipline

Implement only the closure blockers.

Do not redesign:

```text
Furniture Request creation
Request validation
Request lifecycle
Request Product eligibility
Enquiry creation
Request operational queue
attachment file validation/storage
audit infrastructure
RBAC architecture
```

unless a small verified correction is required for closure.

---

# 4. Remediation Workstreams

The remediation consists of five workstreams:

```text
A. Customer Request/Enquiry history
B. Enquiry operational management
C. Scoped attachment capability routes
D. REQ-001 production route activation
E. MariaDB closure verification
```

Complete them in that order unless repository dependencies require a minor adjustment.

---

# PART A — CUSTOMER REQUEST / ENQUIRY HISTORY

# 5. Required Request History Operations

Implement the frozen endpoints:

```http
GET /api/v1/me/requests
GET /api/v1/me/requests/{request}
```

IDs:

```text
REQ-002
REQ-003
```

These are authenticated CUSTOMER ownership routes.

---

# 6. Required Enquiry History Operations

Implement:

```http
GET /api/v1/me/enquiries
GET /api/v1/me/enquiries/{enquiry}
```

IDs:

```text
ENQ-002
ENQ-003
```

---

# 7. Customer Ownership Principle

Authenticated Customer may see only records where:

```text
resource.user_id = authenticated local User.id
```

Ownership is server-derived.

Never accept:

```text
user_id
customer_id
owner_id
```

from query/path/body as authorization.

---

# 8. Anonymous Submissions Are Not Retrievable

Anonymous:

```text
FurnitureRequest.user_id = null
Enquiry.user_id = null
```

must never appear under:

```text
/me/requests
/me/enquiries
```

An opaque:

```text
req_...
enq_...
```

identifier is not an anonymous retrieval credential.

---

# 9. Authentication

REQ-002/003 and ENQ-002/003 require valid authenticated CUSTOMER identity.

Anonymous:

```text
401 AUTHENTICATION_REQUIRED
```

Invalid bearer:

```text
401 INVALID_AUTHENTICATION
```

Never downgrade.

---

# 10. Wrong Roles

Operational Staff must not use customer `/me/...` history as a generic operational path unless the frozen actor matrix explicitly permits a purpose-bound Admin case.

Normal:

```text
STAFF → 403
```

Use operational routes instead.

---

# 11. Cross-Customer Masking

Customer A requesting Customer B resource:

```text
GET /me/requests/{request_B}
GET /me/enquiries/{enquiry_B}
```

must return:

```text
404
```

not:

```text
403
```

Do not reveal resource existence.

---

# 12. Ownership-Safe Query

Resolve detail directly through the authorized dataset.

Prefer:

```text
owning Customer
+ opaque identifier
```

in one query boundary.

Do not:

```text
load resource globally
then return 403 if owner differs
```

---

# 13. Request History Pagination

REQ-002 uses global:

```text
page
per_page
```

with:

```text
page >= 1
per_page 1..100
meta.pagination
```

Reuse the project-wide pagination representation.

---

# 14. Request History Sort

Canonical:

```text
created_at DESC
id ASC
```

Newest first with deterministic tie-break.

---

# 15. Request Customer Search

The frozen conventions permit customer history search over the **owned dataset only**.

If `search` is part of REQ-002's frozen schema:

implement it only after ownership scope.

Never search global Requests then filter afterward.

---

# 16. Request Customer Filters

Inspect the actual frozen REQ-002 OpenAPI/conventions before adding filters.

Implement only approved customer-history parameters.

Do not automatically copy every operational REQ-004 filter.

---

# 17. Enquiry History Pagination

ENQ-002 uses:

```text
page
per_page
meta.pagination
```

with the global bounds.

---

# 18. Enquiry History Sort

Canonical:

```text
created_at DESC
id ASC
```

---

# 19. Enquiry Customer Search

If frozen ENQ-002 permits:

```text
search
```

it must search only within:

```text
authenticated Customer's Enquiries
```

Never global Enquiry data.

---

# 20. Customer Request Resource

Use an explicit customer-safe Request serializer.

Must expose approved customer fields only.

Never expose:

```text
staff_internal_notes
internal audit metadata
storage_key
upload capability digest
numeric user_id
internal database IDs
```

---

# 21. Customer Enquiry Resource

Likewise exclude:

```text
staff_internal_notes
audit metadata
storage internals
numeric ownership IDs
```

---

# 22. Customer Attachment Metadata

Attachments inherit parent authorization.

For an owned parent:

return only the approved:

```text
id
filename
content_type
size
url
```

metadata.

No:

```text
storage_key
disk
capability token
capability digest
```

---

# 23. Temporary Attachment URL

If Phase 10.6 implemented private temporary URLs:

REQ-003 / ENQ-003 may expose them only after successful parent authorization.

If not implemented:

```text
url = null
```

rather than a permanent public URL.

---

# 24. Customer Resource Cache

All REQ-002/003 and ENQ-002/003 responses:

```text
Cache-Control: private, no-store
```

with existing:

```text
Vary: Authorization
```

behavior where applicable.

---

# 25. History Reads Are Read-Only

GET history must not change:

```text
status
updated_at
staff notes
attachments
audit records
Product
Order
Inventory
```

---

# 26. Request History Test Matrix

At minimum:

```text
own Request list
own Request detail
anonymous Request excluded
Customer A → Customer B detail = 404
anonymous caller = 401
invalid bearer = 401
STAFF wrong route = 403
pagination
sorting
customer resource privacy
attachment privacy
```

---

# 27. Enquiry History Test Matrix

Same pattern.

Also prove:

```text
owned Order/Product context may be displayed safely
staff_internal_notes hidden
```

---

# PART B — ENQUIRY OPERATIONAL MANAGEMENT

# 28. Required Operations

Implement:

```http
GET  /api/v1/enquiries
GET  /api/v1/enquiries/{enquiry}
POST /api/v1/enquiries/{enquiry}/close
```

IDs:

```text
ENQ-004
ENQ-005
ENQ-006
```

Do not substitute PATCH if frozen V1 uses:

```http
POST /enquiries/{enquiry}/close
```

---

# 29. Permission Split

Operational reads:

```text
enquiries.view
```

Operational mutation:

```text
enquiries.manage
```

Staff operational access is not ownership.

---

# 30. ENQ-004 Actor Matrix

Expected:

```text
Anonymous                  → 401
CUSTOMER                   → 403
STAFF no enquiries.view    → 403
STAFF enquiries.view       → 200
authorized ADMIN           → 200
```

according to existing RBAC permissions.

---

# 31. ENQ-005

Same read authorization:

```text
enquiries.view
```

Missing valid opaque ID:

```text
404
```

after authorization.

---

# 32. ENQ-006

Requires:

```text
enquiries.manage
```

View-only Staff:

```text
403
```

---

# 33. Enquiry Operational Queue

Implement frozen operational filters:

```text
search
enquiry_status
category
product_id
order_id
created_from
created_to
page
per_page
```

and any already-frozen:

```text
sort
sort_direction
```

only if present in authoritative contract/OpenAPI.

Do not invent new filters.

---

# 34. Enquiry Queue Sorting

Default:

```text
created_at DESC
id ASC
```

---

# 35. Enquiry `search`

Approved search fields include:

```text
name
email
phone
subject
message
reference
order reference
```

Search only the authorized operational dataset.

---

# 36. Enquiry Status Filter

Closed values:

```text
OPEN
CLOSED
```

No:

```text
ASSIGNED
IN_PROGRESS
WAITING
RESOLVED
```

---

# 37. Category Filter

Closed:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
```

---

# 38. Product Filter

Use opaque:

```text
prod_...
```

format.

Filtering historical Enquiries must not require Product to remain public.

---

# 39. Order Filter

Use opaque:

```text
ord_...
```

format.

Operational filtering is historical association, not ownership authorization.

---

# 40. Date Filters

Use ISO8601 UTC:

```text
created_from
created_to
```

Validate:

```text
created_from <= created_to
```

---

# 41. Unknown Filter

Reject:

```text
422 INVALID_VALUE
```

Do not silently ignore unsupported query parameters.

---

# 42. Operational Enquiry Resource

Create/reuse an explicit Staff/Admin resource.

May include approved:

```text
id
name
email
phone
subject
message
category
product
order
enquiry_status
attachments
staff_internal_notes where authorized
created_at
updated_at
```

Never mass serialize the model.

---

# 43. Customer Data Minimization

Operational Enquiry access does not authorize:

```text
full Customer profile
all Orders
other Enquiries
credentials
Clerk identity
roles
password/security information
```

---

# 44. Original Enquiry History Is Immutable

Staff cannot modify:

```text
name
email
phone
subject
message
category
product_id
order_id
attachments
user_id
created_at
```

through ENQ-006.

---

# 45. ENQ-006 Lifecycle

Current approved minimum:

```text
OPEN → CLOSED
```

Implement exactly.

---

# 46. Reopen

Do **not** implement:

```text
CLOSED → OPEN
```

unless an existing explicit approved ADR/decision already authorizes reopen.

The contract says reopen is optional and requires explicit approval.

Absence of approval means:

```text
CLOSED = terminal for current implementation
```

Do not invent reopen merely because an API comment says "+ optional reopen."

---

# 47. Same-State Close

Determine and document semantic idempotence.

Recommended:

```text
CLOSED → close again
→ 200 existing CLOSED state
→ no duplicate transition
```

if consistent with existing state mutation conventions.

Do not create duplicate audit status-change events for a no-op.

---

# 48. Enquiry State Machine

Introduce a small pure authority if one does not exist:

```text
OPEN → CLOSED allowed
CLOSED → CLOSED idempotent
CLOSED → OPEN forbidden unless explicitly approved
```

Do not build a generic workflow engine.

---

# 49. Row Lock

ENQ-006 must:

```text
lock Enquiry FOR UPDATE
re-read current state
evaluate transition
persist
audit
commit
```

Do not validate against a stale pre-transaction model.

---

# 50. Transaction Owner

Use one transaction boundary.

The Enquiry status mutation and mandatory audit must commit atomically.

---

# 51. Audit

Use the already-added:

```text
ENQUIRY_STATUS_CHANGED
```

audit vocabulary.

Audit fields must be server-derived:

```text
actor
role
action
resource type
resource id
previous status
new status
timestamp
request correlation ID
result according to existing audit semantics
```

---

# 52. Audit Failure

Mandatory:

```text
audit persistence failure
→ Enquiry status mutation rolls back
```

Match the Request status behavior already established.

---

# 53. Same-State Audit

Do not emit a fake:

```text
OPEN → OPEN
CLOSED → CLOSED
```

status-change audit when there is no actual state change unless existing audit policy explicitly logs attempted no-ops.

Use the same semantics chosen for Request status changes.

---

# 54. Enquiry Internal Notes

If ENQ-006 frozen body includes:

```text
staff_internal_notes
```

implement it only according to exact contract.

If ENQ-006 is strictly the `close` action and staff notes belong another already-approved update path:

do not invent a PATCH route.

Inspect the latest OpenAPI before implementing.

---

# 55. Enquiry Audit Tests

Mandatory:

```text
OPEN → CLOSED creates exactly required audit
audit actor is server-derived
audit previous/new states accurate
audit request ID accurate
audit failure rolls back close
same-state close does not duplicate business transition
```

---

# 56. Enquiry Operational Security Tests

At minimum:

```text
anonymous operational access denied
CUSTOMER denied
STAFF no permission denied
view-only Staff reads allowed
view-only Staff close denied
manage Staff close allowed
cross-resource privacy preserved
internal notes customer-hidden
```

---

# PART C — SCOPED ATTACHMENT CAPABILITY ROUTES

# 57. Required Routes

Implement the frozen separate-upload operations:

```http
POST /api/v1/requests/{request}/attachments
POST /api/v1/enquiries/{enquiry}/attachments
```

IDs:

```text
REQ-007
ENQ-007
```

---

# 58. Reuse Phase 10.6 Attachment Infrastructure

Do not rebuild:

```text
file validation
MIME detection
signature validation
private storage
filename sanitation
AttachmentResource
one-file cardinality
```

Reuse Phase 10.6.

---

# 59. Authorization Principle

Parent ID alone is never authorization.

Separate upload requires:

```text
parent authorization
+
scoped upload capability
```

according to frozen rules.

---

# 60. Anonymous Parent Upload

Anonymous parent has:

```text
user_id = null
```

Therefore no account ownership exists.

Authorization relies on a:

```text
server-issued scoped upload capability
```

bound to that parent.

---

# 61. Authenticated Parent Upload

For authenticated Customer:

require:

```text
parent belongs to Customer
```

plus scoped capability where the frozen contract requires it.

Do not let Customer A upload to Customer B parent.

---

# 62. Staff/Admin

Attachment authorization inherits parent operational permissions.

Do not give all Staff universal upload mutation just because they can view.

Use exact frozen/approved parent authorization.

---

# 63. Capability Requirements

Must be:

```text
cryptographically unpredictable
parent-scoped
resource-type-scoped
upload-action-scoped
time-limited
single-use
server-verifiable
```

---

# 64. Capability Is Upload-Only

It must not authorize:

```text
parent GET
attachment GET
other parent mutation
Request status mutation
Enquiry status mutation
```

---

# 65. Capability Persistence

Store only:

```text
digest/HMAC
```

not raw bearer token.

Use the hardened guest-Cart-token precedent where appropriate.

---

# 66. Raw Capability

Must exist only:

```text
server generation
→ client
```

Never persist plaintext.

Never log it.

---

# 67. TTL

If the exact TTL is already frozen:

use it.

If the contract does not establish a specific TTL:

use one documented configurable bounded value.

Do not bury a magic number.

---

# 68. Single Use

Successful attachment persistence must atomically consume the capability.

Second use:

```text
rejected
```

---

# 69. Failed Validation

Recommended:

```text
invalid attachment
→ capability not consumed
```

so the client can retry with a valid file.

Use Phase 10.6 decision if already frozen.

---

# 70. Failed Storage

Do not consume capability when durable attachment persistence fails unless the existing design explicitly says otherwise.

---

# 71. Parent Binding

Request token cannot work on:

```text
another Request
any Enquiry
```

Enquiry token cannot work on:

```text
another Enquiry
any Request
```

---

# 72. One Attachment Maximum

If parent already has the single V1 attachment:

reject.

Do not replace silently.

---

# 73. Capability Concurrency

Two simultaneous uploads using the same token:

```text
at most one attachment succeeds
at most one token consumption succeeds
```

Use a locked/atomic DB mechanism.

---

# 74. Losing Upload Cleanup

If losing concurrent worker has already stored bytes:

delete its file.

No orphan.

---

# 75. Attachment Retrieval Is Separate

Do not implement:

```text
GET /attachments/{attachment}
```

as a new public collection.

The frozen retrieval model is parent-scoped resources with authorized temporary attachment URLs.

---

# 76. Temporary URL Security

If attachment URLs are generated:

they must be:

```text
private
temporary
parent-scope-authorized
not permanent public
not guessable
```

Cross-parent reuse must fail safely.

---

# 77. Capability Response Contract

Before returning a capability from REQ-001/ENQ-001:

inspect the latest:

```text
api-contract
api-resources
openapi.yaml
decisions
```

for the exact approved response field/location.

Do not invent:

```text
upload_token
attachment_token
capability
```

if no frozen representation exists.

---

# 78. If Capability Representation Is Still Missing

Treat it as a **contract consistency blocker**.

Do not close Group J by introducing an undocumented secret field.

Resolve through the established frozen-contract reconciliation process.

---

# 79. Prefer Inline Attachment When Supplied

If REQ-001/ENQ-001 already receives an attachment inline:

no separate capability is needed for that parent once the single V1 attachment exists.

Do not issue useless upload authority.

---

# 80. Capability Issuance Without Inline File

For successful creation with no attachment:

issue the scoped capability only if the frozen creation contract defines its representation.

---

# 81. Attachment Route Tests

Mandatory:

```text
valid Request capability → upload succeeds
valid Enquiry capability → upload succeeds
wrong parent → rejected
wrong parent type → rejected
random token → rejected
expired token → rejected
consumed token → rejected
missing anonymous token → rejected
Customer A → Customer B parent → masked
existing attachment → rejected
invalid file → no capability consumption
storage failure → no capability consumption
```

---

# 82. MariaDB Capability Race

Mandatory closure proof if capability uses DB single-use state:

```text
same capability
two concurrent uploads
→ one success
→ one attachment
→ no orphan
```

Run on disposable MariaDB.

---

# PART D — REQ-001 ROUTE ACTIVATION

# 83. Current Blocker

Phase 10.8 reports:

```text
POST /api/v1/requests
```

remains configuration-gated.

This is incompatible with the intended MADE_TO_ORDER initial production mode once all REQ-001 prerequisites are complete.

---

# 84. Activation Preconditions

Before activating REQ-001, verify:

```text
10.1 creation complete
10.2 validation complete
10.4 Product eligibility complete
10.6 inline attachment complete
public abuse controls attached
optional auth correct
private/no-store response correct
OpenAPI aligned
```

---

# 85. Do Not Depend on H/I

REQ-001 must not remain gated because:

```text
Group H Payments
Group I Orders
Group G Checkout
```

are deferred.

Furniture Requests are deliberately independent.

---

# 86. Remove Unconditional Gate

If all REQ-001 prerequisites pass:

remove/disable the unconditional:

```php
config('requests.route_enabled') === false
```

stub behavior according to existing route architecture.

The production Request route should become:

```text
ACTIVE
```

---

# 87. Do Not Replace With Environment Accident

Do not simply change:

```env
REQUESTS_ENABLED=true
```

if the project deliberately designed the gate as non-environment-driven.

Use the approved application route lifecycle.

---

# 88. Verify ENQ-001 Route Too

Phase 10.8 only reported REQ-001 as gated.

Still inspect:

```text
POST /api/v1/enquiries
```

and report actual state.

If ENQ-001 is also gated due to attachments, activate it once its prerequisites are satisfied.

---

# 89. Public Creation Middleware

After activation preserve:

```text
optional Clerk auth
customer-submission guard
anonymous rate limiter
body-size enforcement
security headers
safe logging
```

---

# 90. No Staff/Admin Creation

Activation must preserve:

```text
STAFF/ADMIN → 403
```

for customer-style REQ-001/ENQ-001.

---

# 91. Route Contract Test

Add permanent tests proving:

```text
REQ-001 ACTIVE
ENQ-001 ACTIVE
```

if both are ready.

Do not let a future config default silently return them to 501.

---

# PART E — MARIADB CLOSURE GATE

# 92. MariaDB Is Mandatory for Closure

Phase 10.8 skipped concurrency because disposable MariaDB was unavailable.

Therefore:

```text
Group J cannot close
```

until the required row-lock/token-concurrency tests actually execute.

---

# 93. Disposable Database Only

Use:

```text
furnitureapp_test_disposable
```

or the exact guarded test database established by the repository.

Never run destructive concurrency setup on:

```text
development DB
staging DB
production DB
```

---

# 94. Required Request Lifecycle Race

Execute existing:

```text
FurnitureRequestStatusConcurrencyMysqlTest
```

At minimum:

```text
close vs stale review
same-target update
```

Final state must remain valid.

---

# 95. Request Operational Race

If Phase 10.7 has combined:

```text
status + staff_internal_notes
```

race test, execute it.

Prove stale writer cannot reopen CLOSED or overwrite committed state incorrectly.

---

# 96. Enquiry Close Race

Add/execute MariaDB test for:

```text
OPEN
worker A → CLOSED
worker B → CLOSED / stale operation
```

Expected:

```text
one actual close
same-state reconciliation
no invalid reopen
correct audit cardinality
```

---

# 97. Enquiry Audit Under Concurrency

Exactly one real:

```text
OPEN → CLOSED
```

transition should generate the appropriate status-change audit event.

Same-state loser must not create a duplicate status-change audit unless approved audit semantics say otherwise.

---

# 98. Attachment Capability Race

Execute:

```text
same upload capability
two workers
```

Expected:

```text
one attachment
one token consumption
zero orphan objects
```

---

# 99. MariaDB Version Reporting

Report:

```text
engine
version
test database
test classes
scenarios
iterations
assertions
result
```

---

# 100. SQLite vs MariaDB

State clearly:

```text
SQLite
→ functional validation
→ ownership
→ serialization
→ rollback
→ audit semantics

MariaDB
→ FOR UPDATE proof
→ concurrent lifecycle serialization
→ capability single-use proof
```

Do not claim SQLite proves row locks.

---

# PART F — CUSTOMER HISTORY DETAILED REQUIREMENTS

# 101. REQ-002 Resource Privacy

Customer Request list/detail must never include:

```text
staff_internal_notes
audit events
internal storage information
raw user_id
```

---

# 102. ENQ-002 Resource Privacy

Same.

---

# 103. Own Collection Dataset

Customer history query must begin from:

```text
where user_id = authenticated User.id
```

before:

```text
search
filters
pagination
```

---

# 104. Pagination Cannot Leak Counts

`meta.pagination.total` must count only own records.

---

# 105. Search Cannot Leak

Search must not return/count records from another Customer.

---

# 106. Anonymous Historical Records

Never attach anonymous records to Customer solely because:

```text
email matches
phone matches
name matches
```

No retroactive identity linking.

---

# 107. No Email Linking

Especially do not perform:

```text
anonymous Request.email == authenticated Customer.email
→ treat as owned
```

Ownership is only:

```text
user_id
```

---

# 108. No Guest Claim Endpoint

Do not invent a claim/adopt endpoint during remediation.

---

# 109. Detail Identifier

Use opaque:

```text
req_...
enq_...
```

No numeric fallback.

---

# 110. Invalid Identifier

Use the project's deterministic identifier error mapping.

Do not query using malformed numeric/raw strings.

---

# PART G — ENQUIRY OPERATIONAL DETAILS

# 111. ENQ-004 Pagination

Use global:

```text
page/per_page
```

with max 100.

---

# 112. ENQ-004 Query Object

Create a normalized immutable query DTO/value if useful.

Do not pass raw query arrays into deep services.

---

# 113. Query Service

Use focused:

```text
ListOperationalEnquiries
```

or repository equivalent.

Do not put full filter logic in controller.

---

# 114. Operational Detail

ENQ-005 must expose only handling context.

Do not embed whole Customer account.

---

# 115. Order Context

Safe order summary only.

Do not expose:

```text
billing address
payment secret
full payment provider payload
unrelated Order data
```

---

# 116. Product Context

Safe Product summary only.

No stock internals.

---

# 117. Enquiry Internal Notes

Customer representation:

```text
never
```

Operational representation:

according to approved permissions.

---

# 118. Staff Cannot Mutate Customer History

ENQ-006 must not change:

```text
subject
message
contact
category
product_id
order_id
```

---

# 119. No Enquiry→Order Conversion

Closing an Enquiry:

```text
does not create Order
does not reserve stock
does not create Payment
```

---

# 120. No Enquiry→Request Conversion

None.

---

# PART H — MANDATORY AUDIT COMPLETION

# 121. Existing Request Audit

Preserve the Phase 10.8 Request audit implementation.

Do not rewrite unless necessary for reuse.

---

# 122. Audit Vocabulary

Reuse:

```text
REQUEST_STATUS_CHANGED
ENQUIRY_STATUS_CHANGED
```

Do not add duplicate synonyms.

---

# 123. Server-Derived Actor

Client can never submit:

```text
actor_id
actor_role
audit_timestamp
request_id
previous_status
new_status
```

---

# 124. Correlation ID

Use the repository request correlation ID.

Do not generate a second unrelated identifier merely for audit.

---

# 125. Audit Transaction Semantics

Status mutation + audit:

```text
one business transaction
```

Audit failure must not leave status committed.

---

# 126. No Secrets in Audit

Never audit:

```text
bearer tokens
upload capability
storage credentials
passwords
full attachment content
```

---

# PART I — ATTACHMENT DOWNLOAD PRIVACY

# 127. Parent-Scoped Read

Attachment retrieval remains through authorized parent representation / temporary URL.

No public attachment index.

---

# 128. Cross-Customer Attachment

Customer A must not obtain a usable private URL for Customer B attachment.

---

# 129. Cross-Resource Signed URL

A signed/private capability tied to one parent/attachment must not authorize another.

---

# 130. Permanent Public URLs

Forbidden.

---

# PART J — ROUTE CLOSURE MATRIX

# 131. Request Routes

At remediation completion report:

```text
REQ-001 POST /requests
REQ-002 GET /me/requests
REQ-003 GET /me/requests/{request}
REQ-004 GET /requests
REQ-005 GET /requests/{request}
REQ-006 PATCH /requests/{request}
REQ-007 POST /requests/{request}/attachments
```

Each must be:

```text
ACTIVE
```

unless the frozen contract has an explicit approved exception.

---

# 132. Enquiry Routes

Likewise:

```text
ENQ-001 POST /enquiries
ENQ-002 GET /me/enquiries
ENQ-003 GET /me/enquiries/{enquiry}
ENQ-004 GET /enquiries
ENQ-005 GET /enquiries/{enquiry}
ENQ-006 POST /enquiries/{enquiry}/close
ENQ-007 POST /enquiries/{enquiry}/attachments
```

---

# 133. No Required Stub

If any required Group J endpoint remains:

```text
501
STUB
unconditionally gated
MISSING
```

then:

```text
Group J = NOT CLOSED
```

---

# PART K — OPENAPI / CONTRACT RECONCILIATION

# 134. Verify All Operations

Ensure OpenAPI represents:

```text
REQ-001..007
ENQ-001..007
```

correctly.

---

# 135. Customer History

Verify:

```text
REQ-002/003
ENQ-002/003
```

ownership/security descriptions and private resources.

---

# 136. Enquiry Operations

Verify:

```text
ENQ-004/005/006
```

permissions, filters, lifecycle, errors.

---

# 137. Attachment Capability

Verify exact representation of the scoped upload token.

If OpenAPI still lacks an approved place to return it:

do not invent one silently.

Resolve the consistency gap before Group J closure.

---

# 138. Error Codes

Every emitted Group J error must exist in:

```text
ApiErrorCode
OpenAPI error enum
normative docs
```

---

# 139. No New Contract

Do not use remediation to add new:

```text
status
field
filter
endpoint
role
Product type
attachment behavior
```

unless it is already frozen but missing from implementation/documentation.

---

# PART L — REGRESSION TESTS

# 140. Request Regression

Run all:

```text
FurnitureRequest*
RequestStatus*
RequestableProductResolver*
OperationalFurnitureRequest*
Attachment*
```

as applicable.

---

# 141. Enquiry Regression

Run complete:

```text
Enquiry*
```

suite.

---

# 142. Customer History Tests

Add focused:

```text
CustomerFurnitureRequestHistoryApiTest
CustomerEnquiryHistoryApiTest
```

or repository-consistent equivalents.

---

# 143. Enquiry Operational Tests

Add focused:

```text
OperationalEnquiryListApiTest
OperationalEnquiryDetailApiTest
OperationalEnquiryCloseApiTest
```

or equivalents.

---

# 144. Attachment Capability Tests

Add focused:

```text
RequestAttachmentCapabilityApiTest
EnquiryAttachmentCapabilityApiTest
```

or a shared suite where clearer.

---

# 145. Closure Regression Test

Consider one high-level:

```text
GroupJClosureRegressionTest
```

covering only cross-domain invariants:

```text
MADE_TO_ORDER Request flow
Enquiry flow
customer ownership
staff operations
no Order/Payment/Inventory effects
```

Do not duplicate all specialized tests.

---

# PART M — END-TO-END CLOSURE FLOWS

# 146. Authenticated Furniture Request Flow

Prove:

```text
CUSTOMER
→ POST /requests
→ 201 SUBMITTED
→ GET /me/requests
→ sees own
→ GET /me/requests/{id}
→ sees own detail
→ Staff GET /requests
→ Staff GET detail
→ Staff IN_REVIEW
→ Staff CLOSED
→ audit events correct
```

No Order.

---

# 147. Anonymous Furniture Request Flow

Prove:

```text
Anonymous
→ POST /requests
→ 201
→ cannot GET /me/requests
→ Staff operationally handles it
```

---

# 148. Attachment Request Flow

If no inline attachment:

```text
creation
→ scoped upload capability
→ REQ-007
→ one private attachment
```

according to frozen response contract.

---

# 149. Authenticated Enquiry Flow

Prove:

```text
CUSTOMER
→ POST /enquiries
→ OPEN
→ GET /me/enquiries
→ own detail
→ Staff operational queue
→ Staff detail
→ close
→ CLOSED
→ audit
```

---

# 150. Anonymous Enquiry Flow

Prove:

```text
Anonymous create
→ OPEN
→ no anonymous retrieval
→ Staff can handle
→ close
```

---

# 151. Enquiry Attachment Flow

Same scoped upload behavior.

---

# PART N — ZERO COMMERCE SIDE EFFECTS

# 152. Furniture Request

Across create/read/manage/attachment:

```text
Orders unchanged
Payments unchanged
ProductStock unchanged
reserved_quantity unchanged
Cart unchanged
```

---

# 153. Enquiry

Same.

---

# 154. Status Close

Request/Enquiry status transitions create no commerce resources.

---

# 155. Attachments

Uploading attachment creates no commerce resources.

---

# PART O — SECURITY REGRESSION

# 156. Invalid Bearer

All Group J authenticated/optional-auth paths maintain:

```text
invalid bearer → no anonymous downgrade
```

---

# 157. Suspended Account

Preserve existing active-account restrictions.

---

# 158. Mixed Roles

Preserve hardening against invalid mixed-role actors.

---

# 159. Rate Limits

REQ-001/ENQ-001 retain public submission throttles.

REQ-007/ENQ-007 should have suitable upload throttling using existing infrastructure.

Do not leave upload capability route as an unthrottled abuse surface.

---

# 160. Body Limit

Attachment routes retain global body ceiling compatible with valid 5 MiB upload + overhead.

---

# 161. Safe Logging

No:

```text
raw capability
bearer
full message/notes
private file bytes
storage key
```

in ordinary logs.

---

# PART P — QUALITY / VERIFICATION

# 162. Focused Tests

Run closure-focused suites first.

---

# 163. Full PHPUnit

Run:

```bash
php artisan test
```

Report:

```text
total
passed
failed
skipped
assertions
```

---

# 164. MariaDB

Run all required Group J integration tests with actual execution.

Skipped concurrency is not closure proof.

---

# 165. PHPStan

```bash
vendor/bin/phpstan analyse
```

Required:

```text
0 errors
```

---

# 166. Pint

```bash
vendor/bin/pint --test
```

---

# 167. Composer Audit

```bash
composer audit
```

Must be clean or exact advisory reported.

---

# 168. Diff Check

```bash
git diff --check
```

---

# 169. Route List

```bash
php artisan route:list
```

Build final REQ/ENQ route-state matrix.

---

# 170. OpenAPI Parse

Validate:

```text
docs/api/openapi.yaml
```

---

# 171. Migration Verification

If capability persistence requires a new migration:

run normal fresh migration/schema tests.

Do not modify historical migrations.

---

# 172. MariaDB Constraint Verification

If capability schema uses:

```text
unique guards
CHECK
row locks
```

verify on MariaDB, not only SQLite.

---

# PART Q — DOCUMENTATION

# 173. Closure ADR

Add the next available backend ADR.

Concept:

```text
Group J Closure Remediation and Final Verification
```

Inspect actual ADR numbering.

Do not assume it.

---

# 174. ADR Content

Record:

```text
customer Request history
customer Enquiry history
Enquiry operational routes
Enquiry OPEN→CLOSED lifecycle
Enquiry status audit
scoped attachment capability
single-use concurrency
REQ-001 activation
ENQ-001 activation state
MariaDB execution
final route matrix
Group J closure decision
```

---

# 175. Update Group J Tracking

Do not add:

```text
10.9
```

unless the project owner explicitly wants the roadmap changed.

Instead append:

```text
Group J Closure Remediation
```

or repository-consistent closure record.

---

# 176. Preserve Phase Results

Do not rewrite prior PASS phases as if they were incomplete.

Record:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS or exact state after remediation
10.7 PASS
10.8 PASS
Closure Remediation PASS/BLOCKED
```

---

# PART R — FINAL CLOSURE DECISION

# 177. Group J Can Close Only If

All must be true:

```text
REQ-001 ACTIVE
REQ-002 ACTIVE
REQ-003 ACTIVE
REQ-004 ACTIVE
REQ-005 ACTIVE
REQ-006 ACTIVE
REQ-007 ACTIVE

ENQ-001 ACTIVE
ENQ-002 ACTIVE
ENQ-003 ACTIVE
ENQ-004 ACTIVE
ENQ-005 ACTIVE
ENQ-006 ACTIVE
ENQ-007 ACTIVE
```

and:

```text
Request audit PASS
Enquiry audit PASS
customer ownership PASS
IDOR masking PASS
attachment capability PASS
private storage PASS
no public URLs PASS
MariaDB concurrency PASS
OpenAPI aligned
full regression PASS
```

---

# 178. Request End-to-End Closure

Must prove:

```text
capture
→ own customer history
→ operational handling
→ controlled lifecycle
→ audit
```

---

# 179. Enquiry End-to-End Closure

Must prove:

```text
capture
→ own customer history
→ operational handling
→ controlled close
→ audit
```

---

# 180. Attachment Closure

Must prove:

```text
inline optional upload
+
separate scoped upload
+
private authorized retrieval metadata
+
single-use capability
```

according to frozen V1.

---

# 181. Commerce Separation

Must still prove:

```text
Group J creates no Order
Group J creates no Payment
Group J reserves no inventory
Group J mutates no Cart
Group J creates no authoritative quote
```

---

# 182. Phase H/I Status

Do not resume:

```text
Group H
Group I
```

during remediation.

They remain deliberately deferred.

---

# 183. Next Group

If and only if Group J closes:

report:

```text
Group K — READY
```

Do not begin Group K automatically.

---

# PART S — COMPLETION REPORT

Return the following exact categories.

## Closure Remediation Status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Group J Status

Separately:

```text
CLOSED
```

or:

```text
NOT CLOSED
```

---

## Request Routes

Report all:

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
ACTIVE / STUB / GATED / MISSING
```

---

## Enquiry Routes

Report:

```text
ENQ-001..ENQ-007
```

same way.

---

## Customer History

Report:

```text
Request own list/detail
Enquiry own list/detail
cross-customer 404
anonymous exclusion
pagination
sorting
search if approved
private/no-store
```

---

## Enquiry Operations

Report:

```text
list
detail
close
permission model
filters
sort
internal notes
OPEN→CLOSED
reopen policy
```

---

## Audit

Report:

```text
REQUEST_STATUS_CHANGED
ENQUIRY_STATUS_CHANGED
transactional rollback
actor
role
previous/new
resource
timestamp
request_id
```

---

## Attachments

Report:

```text
REQ-007
ENQ-007
capability generation
scope
TTL
digest storage
single-use
failed-upload consumption behavior
concurrency
orphan cleanup
URL privacy
```

---

## Route Activation

Report actual state of:

```text
POST /api/v1/requests
POST /api/v1/enquiries
```

and any removed gate.

---

## MariaDB

Report:

```text
engine/version
database
test classes
race scenarios
iterations
assertions
result
```

---

## Security

Report:

```text
IDOR
permission matrix
invalid bearer
suspended actor
rate limits
body limit
cache privacy
logging privacy
attachment capability security
```

---

## Commerce Side Effects

Confirm:

```text
Orders = NONE
Payments = NONE
Inventory = NONE
Cart = NONE
Quotes = NONE
ClickPesa = NONE
```

---

## Schema

Report exact migration(s), if capability persistence required them.

Do not claim NONE if a new table/columns were created.

---

## Dependencies

Report exact changes.

Expected:

```text
NONE
```

unless unavoidable and justified.

---

## Frontend

Expected:

```text
NONE
```

---

## OpenAPI

Report:

```text
aligned
```

plus any frozen-contract reconciliation.

---

## Tests

Report:

```text
customer history tests
Enquiry operational tests
audit tests
attachment capability tests
security tests
MariaDB concurrency
full suite totals
assertions
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

## Group J Final Matrix

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS/BLOCKED
10.7 PASS
10.8 PASS
Closure Remediation PASS/BLOCKED

Group J CLOSED / NOT CLOSED
```

---

## Remaining Blockers

If not closed, list exact blockers individually.

Do not write:

```text
more work needed
```

---

# 184. Definition of Done

The Group J Closure Remediation is complete when:

- REQ-002 own Request list is implemented;
- REQ-003 own Request detail is implemented;
- Customer A cannot read Customer B Request;
- anonymous Requests are not retroactively claimed;
- ENQ-002 own Enquiry list is implemented;
- ENQ-003 own Enquiry detail is implemented;
- Customer A cannot read Customer B Enquiry;
- customer history is private/no-store;
- customer resources hide staff internal notes;
- ENQ-004 operational list is implemented;
- ENQ-005 operational detail is implemented;
- ENQ-006 OPEN→CLOSED is implemented;
- Enquiry reopen is not invented without explicit approval;
- enquiries.view controls reads;
- enquiries.manage controls close;
- Enquiry original customer content remains immutable;
- Enquiry close is row-locked/concurrency-safe;
- Enquiry close audit is mandatory and transactional;
- Request status audit remains transactional;
- REQ-007 separate upload is implemented;
- ENQ-007 separate upload is implemented;
- parent ID alone cannot authorize upload;
- anonymous upload uses scoped capability;
- capability is random;
- capability is resource-scoped;
- capability is parent-scoped;
- capability is time-limited;
- capability is single-use;
- raw capability is not persisted;
- raw capability is not logged;
- capability consumption is race-safe;
- failed validation does not incorrectly consume capability;
- losing concurrent upload leaves no orphan file;
- one attachment per parent remains enforced;
- storage remains private;
- permanent public attachment URLs do not exist;
- REQ-001 is activated once prerequisites are complete;
- ENQ-001 activation state is verified and corrected if similarly gated;
- no required Group J route remains 501/stub/gated;
- Request MariaDB concurrency executes successfully;
- Enquiry MariaDB concurrency executes successfully;
- attachment capability MariaDB race executes successfully;
- Group J audit requirements are satisfied;
- ownership/IDOR tests pass;
- all Group J APIs remain separate from Orders/Payments/Inventory;
- OpenAPI matches implementation;
- full suite passes;
- PHPStan has zero errors;
- Pint passes;
- Composer audit is clean;
- `git diff --check` passes;
- Group J closure is decided from evidence rather than prior phase labels.

---

# 185. STOP Condition

STOP only when you can provide an evidence-backed result:

```text
Group J Closure Remediation:
PASS / BLOCKED

Customer Request history:
PASS / BLOCKED

Customer Enquiry history:
PASS / BLOCKED

Enquiry operational management:
PASS / BLOCKED

Request audit:
PASS / BLOCKED

Enquiry audit:
PASS / BLOCKED

REQ-007:
PASS / BLOCKED

ENQ-007:
PASS / BLOCKED

REQ-001:
ACTIVE / GATED

ENQ-001:
ACTIVE / GATED

MariaDB concurrency:
PASS / BLOCKED

Group J:
CLOSED / NOT CLOSED

Remaining blockers:
1. ...
2. ...
```

Do not begin Group K.

Do not resume Groups H or I.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.