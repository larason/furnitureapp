# Phase 11.9 — Request Management

## Objective

Complete **Group K / Phase 11.9 — Request Management** by promoting the already-implemented Group J Made-to-Order operational Request workflow into the verified canonical backend surface that Staff/Admin will use to manage customer furniture Requests.

This is primarily:

```text
reuse
→ contract verification
→ operational hardening
→ privacy/security regression
→ concurrency/audit verification
→ documentation closure
```

Do NOT build a second Admin Request API.

The canonical V1 operational endpoints remain:

```text
REQ-004
GET /api/v1/requests

REQ-005
GET /api/v1/requests/{request}

REQ-006
PATCH /api/v1/requests/{request}
```

Related Request endpoints remain:

```text
REQ-001
POST /api/v1/requests

REQ-002
GET /api/v1/me/requests

REQ-003
GET /api/v1/me/requests/{request}

REQ-007
POST /api/v1/requests/{request}/attachments
```

Phase 11.9 must inspect and reuse the existing Group J implementation before changing production code.

If a requirement is already fully implemented and permanently tested:

```text
verify it
do not rewrite it
do not duplicate it
```

---

# 1. First Action — Inspect Existing Group J Implementation

Before making changes, inspect at minimum:

```text
AGENTS.md
phases/group-J-phases.md
phases/group-K-phases.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md

routes/api.php

RequestController
FurnitureRequest model
RequestStatus
RequestStatusMachine
RequestStatusTransitionOutcome
TransitionFurnitureRequestStatus

operational Request query/service
Request FormRequests
Request Resources

AuditRecorder
AuditAction
AuditResourceType

Request attachment services/resources
Attachment authorization/capability services

PermissionName
PermissionCatalog
Authorization

existing REQ-001..007 tests
Request concurrency MariaDB tests
Group J closure tests
```

Determine precisely what Phase 10.7/10.8 and Group J closure already implemented.

Do not create replacements for working production code.

---

# 2. Mandatory Git Workflow Skill

The project owner authorizes Git operations.

Before any Git operation, locate and read the root project skill:

```text
git-workflow-and-versioning
```

The skill is authoritative for:

```text
branching
status checks
staging
commits
commit format
push
versioning
tags
cleanup
```

Do not substitute generic Git habits.

Do not perform Git actions before reading the skill.

Never commit:

```text
.env
credentials
R2 secrets
Clerk secrets
database credentials
tokens
generated secret material
```

Never discard unrelated owner changes.

If unrelated working-tree modifications exist:

```text
preserve them
do not stage them accidentally
```

Include all Git actions in the completion report.

---

# 3. Group K Architecture Boundary

Group K reuses Group J Request APIs.

Therefore use:

```text
GET   /api/v1/requests
GET   /api/v1/requests/{request}
PATCH /api/v1/requests/{request}
```

Do NOT add:

```text
/api/v1/admin/requests
/api/v1/admin/requests/{request}
/api/v1/staff/requests
/api/v1/request-management/*
```

No aliases.

The eventual Admin/Staff frontend must consume the canonical Request resources.

---

# 4. Request Is Not an Order

This invariant is mandatory.

A Furniture Request is a business lead for a Made-to-Order item.

It is NOT:

```text
Order
Quote
Invoice
Payment
Reservation
Production job
Delivery
```

Phase 11.9 must not introduce:

```text
request → order conversion
quoted_price
price approval
payment initiation
stock reservation
production scheduling
delivery creation
```

A status update or internal note must never create or mutate:

```text
orders
order_items
payments
product_stocks
reserved_quantity
inventory allocations
deliveries
```

Any future Request-to-Order workflow requires separate approval.

---

# 5. Existing Request Status Lifecycle

Preserve the CLOSED enum exactly:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

No additional status values.

Do NOT add:

```text
CONTACTED
QUOTED
APPROVED
REJECTED
PRODUCING
READY
COMPLETED
CANCELLED
```

The valid state graph remains:

```text
SUBMITTED → IN_REVIEW
SUBMITTED → CLOSED
IN_REVIEW → CLOSED
```

And:

```text
CLOSED
→ terminal
```

Same-state assignment remains idempotent.

---

# 6. Forbidden Status Transitions

The following remain invalid:

```text
IN_REVIEW → SUBMITTED

CLOSED → SUBMITTED

CLOSED → IN_REVIEW
```

A valid enum value used in an invalid transition must remain:

```text
409 CONFLICT
field: request_status
```

An unknown enum value remains:

```text
422 INVALID_VALUE
field: request_status
```

Do not collapse schema validation and business-state conflicts.

---

# 7. REQ-004 — Operational Request Queue

Canonical endpoint:

```text
GET /api/v1/requests
```

Authorization:

```text
requests.view
```

Expected actors:

```text
STAFF with requests.view
ADMIN with requests.view
```

Denied:

```text
anonymous
CUSTOMER
authenticated actor without requests.view
```

Do not use ownership as the Staff authorization model.

Staff operational access is:

```text
business-purpose access
```

not:

```text
Staff owns Request
```

---

# 8. REQ-004 Pagination

Preserve:

```text
page
per_page
```

with existing V1 pagination behavior.

Maximum:

```text
100
```

Use the canonical:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

Do not create Request-specific pagination fields.

---

# 9. REQ-004 Filter Allow-List

Preserve the frozen operational filter set:

```text
search
request_status
product_id
created_from
created_to
page
per_page
```

No undocumented filter may be accepted.

Unknown query fields must be rejected according to existing strict-query conventions.

Do not silently ignore:

```text
status
customer_id
email
phone
product_type
sort
order
closed
assigned_to
priority
```

aliases unless already frozen.

---

# 10. Search Semantics

Preserve the existing operational search contract across approved fields:

```text
name
email
phone
request reference
product
```

Use existing implementation behavior exactly.

Do not broaden search to internal fields.

Do not search:

```text
staff_internal_notes
raw DB IDs
Clerk IDs
attachment storage keys
audit metadata
```

unless an already-frozen contract explicitly says otherwise.

Search must run only against the authorized operational dataset.

---

# 11. Status Filter

`request_status` accepts only:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

Unknown values:

```text
422 INVALID_VALUE
```

Do not accept lowercase aliases unless the existing frozen contract explicitly normalizes them.

Do not implement partial matching.

---

# 12. Product Filter

`product_id` must use the existing frozen Product identifier rules.

Do not silently interpret:

```text
Product name
SKU
Variant ID
raw DB ID
```

as `product_id`.

Preserve nullable/unlinked custom Requests.

A Request with:

```text
product_id = null
```

is valid and must remain visible operationally.

---

# 13. Date Filters

Preserve:

```text
created_from
created_to
```

as ISO8601 UTC according to the existing API conventions.

Validate strictly.

If:

```text
created_from > created_to
```

apply existing cross-field validation rather than silently swapping values.

Do not introduce local-time ambiguity.

---

# 14. Deterministic Ordering

Operational Request queue remains:

```text
created_at DESC
id ASC
```

or the exact equivalent already implemented by Group J.

Do not add arbitrary sorting.

Do not let pagination drift.

---

# 15. REQ-005 — Operational Request Detail

Canonical:

```text
GET /api/v1/requests/{request}
```

Authorization:

```text
requests.view
```

Resolve only by the canonical opaque Request identifier:

```text
req_...
```

Do not use:

```text
request_reference
email
phone
numeric DB ID
```

as URI alternatives.

Unknown resource:

```text
canonical 404
```

No database existence details.

---

# 16. Operational Request Representation

Preserve the operational representation.

Expected approved fields include:

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

Use exact repository schema/field naming.

Do not serialize the Eloquent model wholesale.

---

# 17. Product Summary

For linked Requests, Product representation remains a safe summary such as:

```text
id
name
slug
```

according to existing Group J behavior.

Do not embed:

```text
full Product
Variants
stock
reserved_quantity
cost
internal flags
```

into Request detail.

The Request must remain valid historical data even if the Product later becomes:

```text
inactive
unpublished
soft-deleted
```

Do not hide or invalidate the Request because current catalog visibility changed.

---

# 18. Intake Data Is Historical and Immutable

Original Customer/Anonymous intake must not be editable through REQ-006.

Preserve:

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
request reference
attachments
created_at
```

as historical submission state except where an already-approved attachment workflow adds the one allowed attachment.

REQ-006 must not allow Staff/Admin to rewrite customer history.

---

# 19. Contact Snapshot Preservation

Request contact fields are historical snapshots.

A later change to:

```text
Customer name
Customer phone
Customer email
```

must NOT rewrite old Requests.

Do not dynamically serialize current User profile values in place of the stored Request contact snapshot.

Authenticated and anonymous Requests must remain self-contained.

---

# 20. `staff_internal_notes`

Operational internal notes are distinct from customer `notes`.

Customer-visible:

```text
notes
```

Operational-only:

```text
staff_internal_notes
```

Never expose `staff_internal_notes` through:

```text
REQ-002
REQ-003
customer /me Request resources
anonymous flows
```

Staff/Admin operational resources may expose it where authorized.

---

# 21. Internal Notes Are Not Customer Intake

Do not merge:

```text
notes
staff_internal_notes
```

Do not overwrite customer notes.

Do not rename customer notes as internal notes.

Do not use one DB column ambiguously for both.

Preserve the established Group J storage mapping.

---

# 22. REQ-006 — Controlled Operational Update

Canonical:

```text
PATCH /api/v1/requests/{request}
```

Authorization:

```text
requests.manage
```

Accepted writable fields remain only:

```text
request_status
staff_internal_notes
```

according to the existing frozen contract.

No generic Request mutation.

---

# 23. Strict REQ-006 Body

Reject attempts to write:

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
attachments
created_at
updated_at

order_id
payment_status
quoted_price
price
delivery_fee
```

Unknown fields must be rejected.

Use:

```text
FormRequest
validated()
```

only.

Never:

```php
$request->all()
```

---

# 24. Partial Update Semantics

Inspect and preserve the already-implemented REQ-006 rules for:

```text
status-only
internal-note-only
status + internal-note
```

Do not guess.

If both are currently valid:

```text
PATCH
{
  "request_status": "IN_REVIEW",
  "staff_internal_notes": "..."
}
```

must be handled atomically according to the existing implementation.

If the repository has a narrower frozen shape, preserve it.

Do not broaden the API merely for convenience.

---

# 25. Atomic Status + Note Mutation

Where REQ-006 permits both fields in the same request:

```text
request_status
staff_internal_notes
```

they must commit atomically.

If status transition fails:

```text
internal note must not partially persist
```

If audit persistence fails:

```text
status/note changes must roll back
```

Use the existing Group J transaction boundary.

---

# 26. Same-State Idempotency

Same-status update:

```text
current = IN_REVIEW
target = IN_REVIEW
```

remains a business no-op.

Do not:

```text
rewrite status
touch updated_at merely for status
create duplicate status audit
```

unless another actual mutable field, such as internal notes, legitimately changes in the same request.

Test status no-op separately from note mutation.

---

# 27. CLOSED Is Terminal

After:

```text
request_status = CLOSED
```

REQ-006 must never reopen it.

Do not add a reopen action.

Do not allow internal notes to implicitly reopen or change status.

A CLOSED Request may remain readable operationally.

Inspect current contract before deciding whether internal notes may still be edited on CLOSED Requests; preserve the frozen behavior.

Do not invent a restriction or permission that is not present.

---

# 28. Audit Requirements

Preserve the Group J durable audit design.

Real Request state changes must remain auditable.

Use existing audit enum/action names.

Do not invent new audit types unnecessarily.

At minimum prove the existing system records:

```text
actor
actor role
Request resource
previous state
resulting state
timestamp
request/correlation ID
```

where established.

Actor identity must be server-derived.

Never accept:

```text
actor_id
performed_by
staff_id
role
timestamp
```

from REQ-006 input.

---

# 29. Audit Atomicity

Audit persistence and Request mutation must remain transactionally consistent.

If audit write fails:

```text
Request mutation rolls back
```

Same-state status no-op:

```text
must not create duplicate status-transition audit
```

Concurrent losing transition:

```text
must not create false audit event
```

Audit count must reflect committed business effects only.

---

# 30. Request History / Audit Boundary

Do NOT create a new:

```text
request_status_history
request_history
request_events
```

table just for Phase 11.9.

Group J already chose the existing audit infrastructure.

Phase 11.13 owns general Audit visibility.

Do not expose audit history through REQ-005 unless already frozen.

---

# 31. Concurrency Authority

Preserve:

```text
ConcurrentTransaction
+
lockForUpdate()
```

around authoritative Request state mutation.

Do not replace with:

```text
Redis lock
distributed lock
optimistic lock_version
queue serialization
table lock
```

MariaDB/MySQL remains concurrency authority for the real race behavior.

---

# 32. Status Race — Current Locked State

Never decide transition validity from a stale controller-loaded Request instance.

Correct sequence:

```text
begin transaction
→ reload Request
→ lockForUpdate
→ read current status
→ evaluate RequestStatusMachine
→ apply allowed mutation
→ audit
→ commit
```

This prevents:

```text
Staff A reads SUBMITTED
Staff B closes
Staff A later writes IN_REVIEW from stale state
```

from reopening CLOSED.

---

# 33. Existing MariaDB Request Race Gate

Reuse the existing Group J MariaDB concurrency suite.

At minimum preserve proof for:

```text
close vs IN_REVIEW race
same-target concurrent transition
terminal CLOSED cannot reopen
exactly one real state-change audit where appropriate
```

Do not duplicate equivalent tests unnecessarily.

If existing tests already prove these properties:

```text
run them
reference them in completion report
```

---

# 34. Internal-Note Concurrency

Inspect whether Group J already tests:

```text
status update + internal-note update
```

under concurrency.

If not, add only the minimum meaningful regression.

The key rule:

```text
status transition invariants may never be violated
```

Do not invent a complex collaborative-note merge model.

Last-write behavior for internal notes is acceptable only if already consistent with the existing contract and transaction design.

---

# 35. Request Attachments

REQ-007 remains the canonical attachment flow.

Phase 11.9 must not create:

```text
GET /requests/{request}/attachments
DELETE /requests/{request}/attachments/{attachment}
PATCH /requests/{request}/attachments/{attachment}
```

unless already frozen.

Operational Request representation may expose safe attachment metadata.

Never expose:

```text
storage key
filesystem path
R2/S3 credential
capability digest
raw upload token
internal bucket
```

---

# 36. Attachment Privacy

Request attachments are private to the parent Request.

Operational Staff/Admin access follows parent Request authorization.

Do not convert them to public media.

Do not use the Product-image R2/CDN public-delivery design for Request attachments.

The Request attachment privacy model remains separate.

---

# 37. Attachment Metadata

Operational metadata may include the already-approved fields:

```text
id
filename
content_type
size
```

and only an already-approved safe temporary/private `url` if current implementation provides one.

Do not expose internal storage paths.

---

# 38. Customer Request Boundary

Preserve:

```text
GET /api/v1/me/requests
GET /api/v1/me/requests/{request}
```

as Customer-owned Request reads.

Customer A must never read Customer B's Request.

Cross-customer access remains masked:

```text
404 RESOURCE_NOT_FOUND
```

Customer resources must not expose:

```text
staff_internal_notes
audit internals
operational permissions
```

---

# 39. Anonymous Request Boundary

Anonymous Request creation remains supported.

Anonymous Requests:

```text
user_id = null
```

They are visible in the operational queue.

Do not require a User relationship for REQ-004/005.

Do not create Customer accounts from anonymous Request data.

Do not allow anonymous general retrieval by Request ID.

---

# 40. Product-Linked vs Custom Requests

Both remain valid:

```text
product_id = MADE_TO_ORDER Product
```

and:

```text
product_id = null
```

A custom Request must not be omitted from the operational queue.

Do not force product linkage during Request management.

---

# 41. Historical Product Changes

A linked Product being later:

```text
renamed
unpublished
deactivated
soft-deleted
```

must not erase the Request or make it operationally inaccessible.

Preserve historical Request visibility.

Do not re-run creation-time requestability rules during operational viewing.

Creation-time product validation and historical operational read are different concerns.

---

# 42. No Revalidation of Intake on Management

REQ-006 must not re-run creation rules such as:

```text
current Product must still be published
current Product must still be MADE_TO_ORDER
```

before allowing legitimate status management.

The Request already exists as history.

Operational management evaluates:

```text
authorization
current Request state
update fields
```

not whether the original Product would still be requestable today.

---

# 43. Private Caching

REQ-004/005/006 responses contain private contact information.

Preserve:

```http
Cache-Control: private, no-store
Vary: Authorization
```

and any existing Cookie variation required by the authentication model.

Never public-cache Request operational data.

---

# 44. PII Minimization

REQ-004/005 may expose the Request contact snapshot because it is operationally required.

Do not expand exposure to unrelated Customer-profile information.

Do NOT join or serialize:

```text
Customer credentials
Clerk ID
permissions
full account history
orders
payments
saved addresses
security state
```

through Request resources.

---

# 45. Permission Separation

Verify:

```text
requests.view
→ REQ-004
→ REQ-005

requests.manage
→ REQ-006
```

An actor with:

```text
requests.view
```

but without:

```text
requests.manage
```

must not mutate Requests.

Do not assume every Staff user automatically has both in controller code.

Use centralized authorization.

---

# 46. Admin Authorization

Admin access remains explicit through seeded permissions.

Do not hard-code an Admin bypass.

Avoid:

```php
if ($user->hasRole('ADMIN')) {
    allowEverything();
}
```

Use:

```text
PermissionCatalog
Authorization
requests.view
requests.manage
```

as established.

---

# 47. No Assignment/Ownership System

Do NOT add:

```text
assigned_staff_id
assignee
team
queue_owner
claimed_by
```

in Phase 11.9.

Staff operational access is shared permission-based handling.

A Request is not owned by a Staff user.

If Request assignment is desired later, it requires its own contract/schema decision.

---

# 48. No Priority System

Do NOT add:

```text
priority
urgent
severity
SLA
due_at
```

No such V1 workflow is frozen.

---

# 49. No Quote System

Internal notes must not become a hidden quotation API.

Do not add:

```text
quoted_price
quote_currency
quote_status
quote_expiry
deposit
```

A future quotation domain is separate.

---

# 50. No Communications System

Do not implement:

```text
chat
email reply
SMS
WhatsApp
push
customer message thread
```

inside Request management.

Phase 11.9 manages Request state and internal notes only.

Group R owns external communication delivery where applicable.

---

# 51. Required REQ-004 Authorization Tests

Ensure permanent coverage:

```text
anonymous
GET /requests
→ 401

Customer
GET /requests
→ 403

Staff with requests.view
→ 200

Admin with requests.view
→ 200

authenticated actor without requests.view
→ 403
```

---

# 52. Required REQ-005 Authorization Tests

Cover:

```text
anonymous → 401
Customer → 403
Staff with requests.view → 200
Admin with requests.view → 200
unknown req_... → 404
malformed identifier → canonical validation/not-found behavior
```

Do not expose DB IDs.

---

# 53. Required REQ-006 Authorization Tests

Cover:

```text
anonymous → 401
Customer → 403
Staff with requests.view only → 403
Staff with requests.manage → allowed
Admin with requests.manage → allowed
```

Do not permit mutation merely because the actor can view.

---

# 54. Required Filter Tests

REQ-004 must cover:

```text
search by name
search by email
search by phone
search by request reference
search by product

request_status
product_id
created_from
created_to

combined filters
pagination after filtering
deterministic ordering
```

Also test:

```text
custom request product_id = null remains discoverable
```

where relevant.

---

# 55. Strict Query Tests

Reject unknown query fields such as:

```text
status
customer_id
sortBy
pageSize
assigned_to
priority
```

according to existing strict conventions.

Do not silently ignore them.

---

# 56. Required Representation Tests

Operational Request detail must assert:

```text
opaque Request ID
safe Product summary or null
quantity
contact snapshot
dimensions
material
color
customer notes
request_status
staff_internal_notes
opaque user_id or null
safe attachment metadata
created_at
updated_at
```

and absence of:

```text
raw DB IDs
Clerk IDs
storage paths
capability digests
credentials
permissions
payment fields
inventory internals
```

---

# 57. Required Customer Privacy Tests

Prove Customer Request response does NOT contain:

```text
staff_internal_notes
internal audit
operational metadata not approved for Customer
```

even after Staff has populated internal notes.

This regression is mandatory.

---

# 58. Required Intake-Immutability Tests

Attempt REQ-006 with:

```text
name
phone
email
quantity
dimensions
material
color
notes
product_id
user_id
request_reference
```

and prove rejection.

Original values must remain unchanged.

---

# 59. Required Status Matrix Tests

Permanent tests must prove the full matrix:

```text
SUBMITTED → SUBMITTED
idempotent

SUBMITTED → IN_REVIEW
allowed

SUBMITTED → CLOSED
allowed

IN_REVIEW → SUBMITTED
409

IN_REVIEW → IN_REVIEW
idempotent

IN_REVIEW → CLOSED
allowed

CLOSED → SUBMITTED
409

CLOSED → IN_REVIEW
409

CLOSED → CLOSED
idempotent
```

Do not weaken the existing unit test.

---

# 60. Required No-Side-Effect Tests

REQ-006 must prove zero creation/mutation of:

```text
Order
OrderItem
Payment
Delivery
ProductStock
reserved_quantity
inventory allocation
quote
```

after:

```text
IN_REVIEW
CLOSED
internal-note update
```

This protects the request-first release boundary.

---

# 61. Required Audit Tests

Prove:

```text
real status transition
→ correct audit event

same-state status
→ no duplicate transition audit

actor/role server-derived

previous state correct
resulting state correct
resource ID correct
request correlation ID correct

audit failure
→ mutation rollback
```

Where internal-note changes are already audited by Group J, preserve and verify that behavior.

Do not invent audit behavior that contradicts the existing implementation.

---

# 62. Required Concurrency Tests

Run the existing MariaDB Request concurrency suite against:

```text
furnitureapp_test_disposable
```

with established safety guards.

Prove:

```text
close-vs-review race
same-target race
CLOSED remains terminal
no stale overwrite
audit reflects committed transition only
```

SQLite is not sufficient proof of `FOR UPDATE`.

---

# 63. No New MariaDB Harness

Reuse the existing:

```text
RunsConcurrentWorkers
```

or current repository equivalent.

Do not introduce another concurrency framework.

Do not run destructive concurrency tests against:

```text
furnitureapp
staging
production
```

---

# 64. Group J Regression Suite

Run all relevant existing Group J Request suites.

At minimum cover:

```text
creation
validation
product-linked requestability
status lifecycle
attachments
operational Request API
audit
MariaDB concurrency
customer history/ownership
```

Phase 11.9 must not regress earlier Group J closure.

---

# 65. OpenAPI

Verify REQ-004/005/006 OpenAPI agrees with runtime for:

```text
paths
methods
security
permissions
filters
pagination
Request operational resource
strict REQ-006 body
request_status enum
staff_internal_notes
401
403
404
409
422
private caching where documented
```

Do not add new operational routes.

These endpoints are already APPROVED; preserve that status.

---

# 66. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

to record Phase 11.9 verification/closure.

If a new ADR is needed, use the repository's next consistent identifier.

Suggested subject:

```text
Group K Request Management Reuse / Closure
```

Record that:

```text
Group K reuses Group J REQ-004/005/006.
No /admin request aliases are introduced.
No Request-to-Order conversion is introduced.
Request intake remains immutable.
Internal notes remain operational-only.
Existing state-machine and MariaDB locking remain authoritative.
```

Do not rewrite historical Group J ADRs unnecessarily.

---

# 67. Schema

Expected:

```text
schema changes = NONE
```

Do not add:

```text
request assignment
priority
status history table
quote table
CRM table
workflow table
```

If a schema change appears necessary, stop and report the genuine mismatch before inventing a model.

---

# 68. Dependencies

Expected:

```text
new dependencies = NONE
```

Do not add:

```text
workflow engine
state-machine package
CRM package
queue package
search engine
```

for Phase 11.9.

Existing Laravel/domain infrastructure is sufficient.

---

# 69. Code Quality

Follow project standards:

```text
thin controller
strict FormRequest
validated() only
explicit resource serialization
central permission checks
domain state machine
transaction/service boundary
enums/constants
cognitive complexity <= 15
<= 3 returns where practical
```

Do not refactor unrelated Group J code.

---

# 70. Verification Commands

Run the repository's canonical equivalents of:

```bash
cd backend/laravel

php artisan test
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit
php artisan route:list
git diff --check
```

Also run focused:

```text
Request operational API tests
Request authorization tests
Request filter/search tests
Request privacy tests
Request state-machine tests
Request audit tests
Request attachment regressions
Group J Request tests
MariaDB Request concurrency tests
OpenAPI route/contract regression
```

---

# 71. Git Operations

After implementation and all required verification pass:

1. read the root `git-workflow-and-versioning` skill;
2. follow it exactly;
3. stage only Phase 11.9-related files;
4. use its required commit/versioning conventions;
5. push only if the skill permits/requests it under the current workflow.

Do not stage unrelated changes.

Do not bypass skill-required checks.

The project owner's Git permission does not override safety restrictions in the skill.

---

# 72. Completion Report

Return:

```text
Phase 11.9 status:
PASS / BLOCKED

Existing Group J implementation reused:
YES / NO

REQ-004:
PASS / BLOCKED

REQ-005:
PASS / BLOCKED

REQ-006:
PASS / BLOCKED

Canonical routes only:
PASS / FAIL

Admin/Staff Request aliases added:
NO

requests.view:
PASS / FAIL

requests.manage:
PASS / FAIL

Customer operational queue access:
REJECTED / FAIL

Customer internal-note exposure:
NO / FAIL

Anonymous operational read:
REJECTED / FAIL

Intake immutability:
PASS / FAIL

Request status enum unchanged:
YES / NO

CLOSED terminal:
PASS / FAIL

Same-state idempotency:
PASS / FAIL

Internal notes separation:
PASS / FAIL

Audit atomicity:
PASS / FAIL

Request-to-Order behavior added:
NO

Inventory reservation added:
NO

Payment/quote behavior added:
NO

Attachment privacy:
PASS / FAIL

Historical Product visibility:
PASS / FAIL

REQ-004 filters:
PASS / FAIL

Pagination:
PASS / FAIL

Deterministic ordering:
PASS / FAIL

MariaDB close-vs-review:
PASS / FAIL

MariaDB same-target transition:
PASS / FAIL

Full PHPUnit:
<x> passed, <y> skipped

PHPStan:
PASS / FAIL

Pint:
PASS / FAIL

Composer audit:
PASS / FAIL

Route surface:
PASS / FAIL

OpenAPI:
PASS / FAIL

git diff --check:
PASS / FAIL

Schema changes:
NONE / <explain>

Dependency changes:
NONE / <explain>

Git workflow skill read:
YES / NO

Git operations performed:
<exact actions>

Commit:
<hash/message or NONE>

Push:
<result or NONE>

Phase 11.10:
READY / BLOCKED
```

Also list:

```text
files changed
tests added/changed
genuine defects fixed
documentation changes
security findings
```

---

# 73. STOP Condition

Phase 11.9 is PASS only when:

- REQ-004/005/006 remain the canonical operational Request surface;
- Group J implementation is reused rather than duplicated;
- `requests.view` and `requests.manage` remain separated;
- customer intake remains immutable;
- `staff_internal_notes` never leaks to Customer responses;
- Request status stays CLOSED to `SUBMITTED/IN_REVIEW/CLOSED`;
- `CLOSED` remains terminal;
- state transitions use current locked database state;
- audit remains transactionally consistent;
- Request attachments remain private;
- anonymous Requests remain manageable operationally without requiring a User;
- historical linked Requests remain visible after Product visibility changes;
- no Request-to-Order, inventory, payment, quote, assignment, priority or CRM behavior is introduced;
- MariaDB concurrency gates pass;
- full backend verification passes;
- Git operations follow the root `git-workflow-and-versioning` skill.

Then report:

```text
Phase 11.9 — PASS
Phase 11.10 — READY
```

Do not begin Phase 11.10 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Phase 11.9 Completion Record

**Status:** Implemented pending final verification.

- Reused canonical Group J `REQ-004`, `REQ-005`, and `REQ-006`; no Admin or Staff Request aliases were added.
- Preserved immutable Request intake, operational-only internal notes, existing state machine, private caching, and request-first boundary.
- Added operational authorization/filter/identifier/no-commerce-side-effect regressions and audited MariaDB race verification.
- Reconciled OpenAPI runtime responses, strict non-empty REQ-006 body, and the REQ-007 `attachment` multipart field.
- No schema or dependency changes; no Request-to-Order conversion, quote, payment, stock/reservation, assignment, priority, or communications capability was added.
