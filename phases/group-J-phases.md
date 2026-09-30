# Phase 10.3 — Furniture Request Status Lifecycle

## 1. Objective

Implement the authoritative Version 1 lifecycle/state-transition boundary for:

```text
FurnitureRequest.request_status
```

using the frozen CLOSED enum:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

The approved state machine is:

```text
SUBMITTED ─────────────→ IN_REVIEW ─────────────→ CLOSED
     │                                                ▲
     └────────────────────────────────────────────────┘
```

Meaning:

```text
SUBMITTED → IN_REVIEW   allowed
SUBMITTED → CLOSED      allowed
IN_REVIEW → CLOSED      allowed
IN_REVIEW → SUBMITTED   forbidden
CLOSED → SUBMITTED      forbidden
CLOSED → IN_REVIEW      forbidden
```

Repeated assignment of the already-current status must be handled as an **idempotent no-op success**, not as another transition.

This phase establishes the domain/application authority for request-status changes.

It does **not** implement the complete staff request-management surface.

---

# 2. Current Group J State

Treat the repository state as:

```text
10.1 PASS — Furniture Request API foundation
10.2 PASS — REQ-001 validation
10.3 CURRENT — Request status lifecycle
10.4 NOT STARTED — product-linked requests
10.5 NOT STARTED — general enquiries
10.6 NOT STARTED — attachments
10.7 NOT STARTED — staff/admin request management
10.8 NOT STARTED — request/enquiry tests
```

REQ-001 remains gated because:

```text
10.4 product eligibility
10.6 attachments
```

are still outstanding.

Do not change that route state in Phase 10.3.

---

# 3. Frozen Lifecycle Contract

Version 1 supports exactly:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

No additional status values.

Do not add:

```text
CONTACTED
QUOTED
APPROVED
REJECTED
PRODUCING
IN_PRODUCTION
READY
COMPLETED
CANCELLED
ARCHIVED
```

Adding another status is a Version 1 contract compatibility decision.

---

# 4. Status Meanings

Preserve the frozen meanings.

### `SUBMITTED`

```text
new intake
not yet acknowledged/handled by staff
server-created default
```

### `IN_REVIEW`

```text
staff has acknowledged/opened the request
business is actively reviewing/handling it
```

### `CLOSED`

```text
business has finished handling the request
terminal state
```

Do not reinterpret `CLOSED` as:

```text
rejected
approved
manufactured
ordered
paid
delivered
```

It simply means the intake workflow has been closed.

---

# 5. Request Is Still Not an Order

A lifecycle transition must never:

```text
create Order
create OrderItem
reserve inventory
create Payment
create quote
create invoice
set delivery fee
create manufacturing job
```

Status changes remain request-workflow operations only.

---

# 6. Domain Enum

Inspect and reuse the existing enum if Phase 3.14 already created one.

Expected concept:

```php
App\Enums\RequestStatus
```

or current repository equivalent.

It must represent exactly:

```php
SUBMITTED
IN_REVIEW
CLOSED
```

Do not introduce a parallel second enum.

---

# 7. Enum as Domain Authority

Avoid scattered literal checks such as:

```php
if ($status === 'SUBMITTED')
```

through multiple services/controllers.

Centralize semantic transition logic around the enum/state-machine boundary.

---

# 8. Creation Status

Phase 10.1 behavior remains:

```text
new FurnitureRequest
→ SUBMITTED
```

The client cannot choose initial status.

Do not alter REQ-001.

---

# 9. Lifecycle Authority

Introduce one focused transition authority.

Possible design:

```php
App\Services\Requests\RequestStatusTransition
```

or:

```php
App\Services\Requests\RequestStatusMachine
```

and one persistence/application service such as:

```php
App\Services\Requests\TransitionFurnitureRequestStatus
```

Use repository naming conventions.

---

# 10. Separation of Concerns

A good layering is:

```text
RequestStatusMachine
→ pure transition decision

TransitionFurnitureRequestStatus
→ transaction + row lock + persistence
```

Do not make the controller contain transition tables.

---

# 11. Pure State Machine

The state machine should be deterministic.

Conceptually:

```php
transition(
    RequestStatus $current,
    RequestStatus $target
): TransitionDecision
```

It must not depend on:

```text
database
HTTP Request
authenticated actor
clock
randomness
Product state
payment state
inventory
```

---

# 12. Transition Matrix

Implement and test the exact matrix:

| Current | Target | Result |
|---|---|---|
| SUBMITTED | SUBMITTED | idempotent no-op |
| SUBMITTED | IN_REVIEW | allowed |
| SUBMITTED | CLOSED | allowed |
| IN_REVIEW | SUBMITTED | forbidden |
| IN_REVIEW | IN_REVIEW | idempotent no-op |
| IN_REVIEW | CLOSED | allowed |
| CLOSED | SUBMITTED | forbidden |
| CLOSED | IN_REVIEW | forbidden |
| CLOSED | CLOSED | idempotent no-op |

Do not infer additional transitions.

---

# 13. Same-Status Idempotence

REQ-006 is designed to be idempotent.

Therefore:

```text
current = IN_REVIEW
target = IN_REVIEW
```

must not fail.

Likewise:

```text
SUBMITTED → SUBMITTED
CLOSED → CLOSED
```

must return the current state without another business effect.

---

# 14. Idempotent Does Not Mean `Idempotency-Key`

Do not add:

```text
Idempotency-Key
```

to REQ-006 solely because status updates are idempotent.

The frozen contract describes semantic idempotence:

```text
same target applied repeatedly
→ same resulting state
```

not a stored-key replay mechanism.

---

# 15. CLOSED Is Terminal

Terminal means:

```text
CLOSED → any different status
```

is forbidden.

It does **not** mean repeated:

```text
CLOSED → CLOSED
```

must fail.

Same-state repetition remains an idempotent no-op.

---

# 16. Invalid Backward Transition

This must fail:

```text
IN_REVIEW → SUBMITTED
```

---

# 17. Direct Close

This is explicitly allowed:

```text
SUBMITTED → CLOSED
```

Do not require every request to pass through `IN_REVIEW`.

Small/simple requests may be closed directly.

---

# 18. Transition Failure Type

Use a focused domain/application exception.

Example:

```php
InvalidRequestStatusTransition
```

or current repository pattern.

Do not throw:

```text
RuntimeException
LogicException
generic Exception
```

directly into the API boundary.

---

# 19. API Mapping for Invalid Transition

Frozen semantics require a conflict-style response.

Use the repository's deterministic Request mapping, expected:

```text
409 CONFLICT
```

or:

```text
409 INVALID_REQUEST
```

according to the exact existing error registry/renderer.

Do not alternate randomly between 422 and 409 for the same state-transition condition.

---

# 20. Review Existing Registry

Before implementation, inspect:

```text
ApiErrorCode
exception renderer
api-contract.md §15
api-contract.md §26.19
```

Choose the existing canonical code.

Do not invent:

```text
REQUEST_STATUS_INVALID_TRANSITION
```

unless already approved.

---

# 21. Invalid Status Value vs Invalid Transition

Keep these distinct.

### Invalid enum value

Example:

```json
{
  "request_status": "APPROVED"
}
```

should ultimately map to:

```text
422 INVALID_VALUE
field: request_status
```

### Valid enum but illegal current→target transition

Example:

```text
IN_REVIEW → SUBMITTED
```

should map to:

```text
409 conflict
```

Do not collapse both cases.

---

# 22. Persistence Service

Implement an atomic service that changes a request's status.

Possible API:

```php
transition(
    FurnitureRequest $request,
    RequestStatus $target
): FurnitureRequest
```

or an opaque request identifier input following repository conventions.

---

# 23. Concurrency Requirement

REQ-006 has a known race:

```text
Staff A closes
Staff B updates stale request
```

Therefore status mutation must validate against the **current database state inside the mutation transaction**.

Do not validate against a stale Eloquent instance read before the transaction.

---

# 24. Row Lock

Use a pessimistic lock on the FurnitureRequest row for state-changing execution.

Conceptually:

```sql
SELECT ...
FROM furniture_requests
WHERE id = ?
FOR UPDATE
```

inside the transaction.

---

# 25. Correct Mutation Order

Conceptually:

```text
BEGIN

load request FOR UPDATE
read current authoritative status
evaluate target against state machine

if same target:
    no-op

if allowed:
    update request_status

if forbidden:
    throw conflict

COMMIT
```

---

# 26. No Validate-Then-Lock

Do not:

```text
load Request
validate transition
BEGIN
lock Request
save stale decision
```

That introduces race conditions.

Validation must use the locked current state.

---

# 27. Same-State No-Op Persistence

For:

```text
current == target
```

prefer avoiding an unnecessary UPDATE.

Do not modify:

```text
updated_at
```

solely because the same status was sent again unless repository conventions explicitly require it.

Semantic idempotence should ideally have zero persistence effect.

---

# 28. Verify Timestamp Semantics

Test/document whether idempotent same-state replay:

```text
CLOSED → CLOSED
```

leaves `updated_at` unchanged.

Prefer unchanged because no state changed.

If current repository conventions require touching the row, document the behavior rather than guessing.

---

# 29. Valid Transition Persistence

For an actual transition:

```text
SUBMITTED → IN_REVIEW
```

persist exactly:

```text
request_status
updated_at
```

No other customer-submitted field changes.

---

# 30. Immutable Intake Fields

Status transition must not mutate:

```text
product_id
quantity
name
phone
email
dimensions
material
color
message/notes
user_id
request_reference
created_at
```

---

# 31. Staff Internal Notes

REQ-006 eventually allows:

```text
staff_internal_notes
```

but full staff update behavior belongs to:

```text
Phase 10.7
```

Do not implement staff-note mutation merely because the same PATCH endpoint eventually contains both fields.

Phase 10.3 owns status lifecycle only.

---

# 32. No Generic Update Service

Do not implement:

```php
$request->fill($validated)->save();
```

for REQ-006.

That would allow future accidental mutation of intake fields.

Status mutation should be explicit.

---

# 33. No Mass Assignment

Never treat operational request updates as generic model editing.

---

# 34. Authorization Boundary

Frozen REQ-006 actors are:

```text
STAFF with requests.manage
ADMIN with appropriate authority
```

Customers cannot set status.

Anonymous users cannot set status.

However full operational route/policy implementation belongs primarily to Phase 10.7.

---

# 35. Phase 10.3 Authorization Scope

Implement only enough authorization integration to protect any internal route/service exposure created for lifecycle testing.

Prefer:

```text
state machine + transition service
```

without prematurely implementing the entire staff-management endpoint.

---

# 36. Do Not Activate REQ-006 Prematurely

Phase 10.7 owns:

```text
staff/admin request management
REQ-004
REQ-005
REQ-006 operational API
staff representations
staff_internal_notes
filtering/listing
permissions orchestration
```

Therefore Phase 10.3 should not declare:

```text
REQ-006 production complete
```

---

# 37. Public Route State

If REQ-006 is currently a stub:

keep it:

```text
STUB / GATED
```

unless existing repository architecture explicitly activates internal status-only behavior safely without stealing 10.7 scope.

Default recommendation:

```text
domain lifecycle implemented
public operational PATCH remains gated
```

---

# 38. Customer Cannot Set Status During REQ-001

Preserve Phase 10.2:

```json
{
  "request_status": "CLOSED"
}
```

on request creation remains:

```text
422 INVALID_VALUE
```

---

# 39. Anonymous Cannot Set Status During Creation

Same rule.

---

# 40. No Customer Status Mutation Endpoint

Do not add:

```text
PATCH /me/requests/{id}
POST /me/requests/{id}/close
```

No such V1 capability exists.

---

# 41. No Cancel Request State

Do not introduce:

```text
CANCELLED
```

because it feels intuitive.

It is not in the frozen lifecycle.

---

# 42. No Reopen

Do not allow:

```text
CLOSED → IN_REVIEW
```

or:

```text
CLOSED → SUBMITTED
```

V1 has no Request reopen action.

---

# 43. No Approval Semantics

Do not interpret:

```text
CLOSED
```

as approval.

Do not trigger production/order creation.

---

# 44. No Rejection Semantics

Likewise do not treat CLOSED as rejection.

That distinction is deliberately absent from the minimal V1 model.

---

# 45. No Status History Table

The current V1 Request schema does not define a dedicated request-status-history entity/table.

Do not create one in Phase 10.3 unless the current repository already has one.

The frozen docs describe future auditability as a candidate, not a command to invent schema here.

Expected:

```text
Schema changes: NONE
```

---

# 46. Audit Boundary

If an existing generic audit infrastructure already automatically captures operational mutations, reuse it.

Do not build a Request-specific audit/event system in this phase.

---

# 47. Notifications

Do not send:

```text
request acknowledged
request closed
```

email/push/in-app notifications.

Group R owns notifications.

---

# 48. Customer Visibility

The `request_status` field is customer-visible in the Request resource.

Therefore valid service transitions must eventually appear as:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

to the owner through later REQ-002/REQ-003 APIs.

Do not create separate public labels in the backend.

---

# 49. No Human Label Persistence

Do not store:

```text
"Under review"
"Closed"
```

as database state.

Store enum value only.

Presentation labels belong to clients.

---

# 50. Database Enum/Constraint Review

Inspect the Phase 3.14 schema.

Verify the DB/model already supports exactly:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

If it does, no migration.

If schema/model permits broader strings, application enum remains authoritative.

Do not alter an old migration.

---

# 51. Model Cast

Ensure:

```php
FurnitureRequest::$casts
```

uses the RequestStatus enum if repository conventions support it.

Do not maintain:

```text
string in one service
enum in another
```

unless existing architecture requires it.

---

# 52. Model Guard

If `FurnitureRequest` already has status assertions/setters:

align them with the same enum.

Do not create conflicting transition behavior in model and service.

---

# 53. Model vs State Machine

The model may enforce:

```text
status is a recognized enum
```

The lifecycle service should enforce:

```text
whether current → target is allowed
```

Keep those responsibilities distinct.

---

# 54. Concurrency Service

Use existing repository transaction primitives if suitable.

Possible:

```php
ConcurrentTransaction
```

if already used for bounded transient DB retries.

Do not create a second generic retry framework.

---

# 55. Retry Scope

Transient DB conflicts may be retried according to existing project conventions.

Business-state conflicts must not be retried blindly.

Example:

```text
IN_REVIEW → SUBMITTED
```

is never fixed by retrying.

---

# 56. Race Example A

Initial state:

```text
SUBMITTED
```

Staff A target:

```text
IN_REVIEW
```

Staff B target:

```text
CLOSED
```

Possible serialization:

```text
A locks
A commits IN_REVIEW
B locks after
B sees IN_REVIEW
B → CLOSED allowed
```

Final:

```text
CLOSED
```

This is valid.

---

# 57. Race Example B

Initial:

```text
SUBMITTED
```

Staff A:

```text
CLOSED
```

Staff B stale target:

```text
IN_REVIEW
```

Serialization:

```text
A closes
B later locks
B sees CLOSED
CLOSED → IN_REVIEW forbidden
```

B must receive conflict.

Do not overwrite CLOSED.

---

# 58. Race Example C — Same Target

Two staff concurrently target:

```text
IN_REVIEW
```

Expected:

```text
one actual transition
one same-state no-op
final IN_REVIEW
```

No corruption.

---

# 59. Race Example D — Both Close

Two staff target CLOSED.

Expected:

```text
one actual close
one same-state no-op
final CLOSED
```

---

# 60. Idempotent Same-State Result

The service should return the current Request state for same-state calls.

Do not throw conflict just because no transition occurred.

---

# 61. Transition Result Object

Consider a small immutable result if useful:

```php
RequestStatusTransitionResult
```

with e.g.:

```text
request
changed: bool
previousStatus
currentStatus
```

Only if this materially helps Phase 10.7/audit integration.

Do not overengineer.

A returned `FurnitureRequest` may be sufficient if the caller does not need transition metadata.

---

# 62. Preserve Original Intake

Transition tests should explicitly prove no changes to:

```text
contact snapshot
notes
specifications
ownership
product reference
```

---

# 63. No Order Side Effects

Every status transition:

```text
Order count unchanged
OrderItem count unchanged
```

---

# 64. No Inventory Side Effects

Every transition:

```text
ProductStock unchanged
reserved_quantity unchanged
allocations unchanged
```

---

# 65. No Payment Side Effects

Every transition:

```text
Payment count unchanged
provider calls NONE
```

---

# 66. No ClickPesa

Do not introduce payment-gateway code anywhere in Request lifecycle.

---

# 67. No Request-to-Order Conversion

Even:

```text
CLOSED
```

must not automatically:

```text
create Order
convert lead
create invoice
```

---

# 68. API Validation Preparation

If Phase 10.3 introduces a request object for future REQ-006 status input, keep it status-focused.

Possible:

```php
UpdateFurnitureRequestStatusRequest
```

It may accept only:

```text
request_status
```

for Phase 10.3 internal/controller testing.

Do not expand into full 10.7 operational input.

---

# 69. Strict Status Input

If status validation is implemented:

```text
request_status required
string
closed enum
```

Reject arbitrary values.

---

# 70. Wrong Type

Examples:

```json
{"request_status": 1}
{"request_status": true}
{"request_status": []}
```

should map:

```text
422 INVALID_TYPE
```

---

# 71. Invalid Enum

Examples:

```json
{"request_status": "APPROVED"}
{"request_status": "closed"}
{"request_status": "REJECTED"}
```

should map:

```text
422 INVALID_VALUE
field: request_status
```

---

# 72. Exact Case

Enum values are exact:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

No lowercase aliases.

---

# 73. Unknown Fields

If a status-only internal validation request is created in 10.3:

reject any extra fields.

Do not accidentally allow:

```text
notes
product_id
quantity
name
phone
email
user_id
order_id
quoted_price
```

---

# 74. Staff Internal Notes Deferred

If client submits:

```text
staff_internal_notes
```

to the status-only Phase 10.3 internal endpoint/test surface, do not implement mutation yet.

Full combined REQ-006 body belongs to 10.7.

Do not change the frozen OpenAPI; this is an implementation staging choice, not contract removal.

---

# 75. API Contract Remains Frozen

The eventual REQ-006 body remains:

```text
request_status
staff_internal_notes
```

Do not remove `staff_internal_notes` from OpenAPI merely because Phase 10.3 doesn't implement it yet.

---

# 76. Route Activation Remains Later

10.7 will complete the externally usable REQ-006 behavior.

---

# 77. Request Lookup

When persistence service transitions a Request:

use actual internal database identity/opaque resolution according to repository conventions.

Do not accept raw numeric IDs from public callers.

---

# 78. Not Found

Future operational API should map missing Request to:

```text
404 REQUEST_NOT_FOUND
```

or established masked equivalent.

If Phase 10.3 route remains gated, service tests may operate on model instances without defining API behavior anew.

---

# 79. Authorization Future Contract

REQ-006 requires:

```text
requests.manage
```

Staff operational access is not ownership.

Do not write lifecycle service logic like:

```text
$request->user_id === $staff->id
```

Staff never owns the customer Request.

---

# 80. Admin

Admin may eventually perform REQ-006 under explicit authorization.

Do not hard-code role strings in state machine.

Authorization belongs outside pure transition logic.

---

# 81. Customer

Customer is never authorized to transition Request status.

Even if customer owns the Request.

Ownership does not grant lifecycle-control authority.

---

# 82. Anonymous

Anonymous submitter has no lifecycle mutation authority.

---

# 83. Immutability of Historical Contact

After any lifecycle transition:

```text
name
email
phone
```

must remain exactly the request-time snapshot.

---

# 84. Immutability of Specifications

Likewise:

```text
quantity
dimensions
material
color
notes/message
product_id
```

remain unchanged.

---

# 85. `updated_at`

Real transition should update it naturally.

Do not manually rewrite `created_at`.

---

# 86. Database Transaction

A real status mutation should be atomic.

Expected:

```text
one row lock
one state evaluation
one update
one commit
```

No need for broad multi-table transaction.

---

# 87. No Heavyweight Locking

Do not lock:

```text
Product
User
Inventory
Order
```

for Request status change.

Only the Request row is relevant.

---

# 88. Concurrency Test Environment

SQLite can test:

```text
transition matrix
persistence
no-op semantics
side effects
```

but it does not prove MySQL pessimistic-lock behavior.

---

# 89. MariaDB Concurrency

Because REQ-006 has an explicitly documented staff race, add a disposable MariaDB integration test if the repository's existing concurrency harness makes this straightforward.

Suggested:

```text
FurnitureRequestStatusConcurrencyMysqlTest
```

---

# 90. Required MariaDB Race

At minimum test:

```text
initial SUBMITTED

worker A → CLOSED
worker B → IN_REVIEW
```

Final must never become an invalid reopened state.

Acceptable outcome:

```text
CLOSED
```

with stale `IN_REVIEW` writer receiving conflict/no overwrite.

---

# 91. Same-Target MariaDB Race

Also useful:

```text
SUBMITTED
A → IN_REVIEW
B → IN_REVIEW
```

Expected:

```text
final IN_REVIEW
one transition + one idempotent no-op
```

---

# 92. Disposable Database Guard

Follow `AGENTS.md` safety rule exactly.

Never run destructive concurrency setup against dev/staging/production DB.

Disposable database:

```text
furnitureapp_test_disposable
```

only.

---

# 93. If MariaDB Is Unavailable

Do not falsely claim row-lock proof.

Report:

```text
concurrency integration test implemented
MariaDB execution pending
```

This need not necessarily block Phase 10.3 domain PASS if repository conventions reserve full integration proof for 10.8, but report the limitation explicitly.

---

# 94. Unit Tests — Transition Matrix

Create a complete matrix test.

Suggested:

```text
RequestStatusMachineTest
```

Cover all 9 combinations.

---

# 95. Unit Test — Valid Forward

Test:

```text
SUBMITTED → IN_REVIEW
IN_REVIEW → CLOSED
SUBMITTED → CLOSED
```

---

# 96. Unit Test — Forbidden Backward

Test:

```text
IN_REVIEW → SUBMITTED
CLOSED → SUBMITTED
CLOSED → IN_REVIEW
```

---

# 97. Unit Test — Same State

Test:

```text
SUBMITTED → SUBMITTED
IN_REVIEW → IN_REVIEW
CLOSED → CLOSED
```

all as no-op success.

---

# 98. Service Test — Actual Persistence

For valid transition:

```text
DB status updated
updated_at updated
other request fields unchanged
```

---

# 99. Service Test — No-Op

For same-state update:

```text
status unchanged
no second business mutation
```

Prefer `updated_at` unchanged if implementation avoids UPDATE.

---

# 100. Service Test — Invalid Transition

Assert:

```text
DB status unchanged
```

and focused domain exception.

---

# 101. Service Test — CLOSED Terminal

Once CLOSED:

attempt both other enum states.

Assert no mutation.

---

# 102. Service Test — Direct Close

From SUBMITTED:

```text
target CLOSED
```

must succeed.

---

# 103. Creation Regression

New REQ-001-created Request still begins:

```text
SUBMITTED
```

---

# 104. Phase 10.2 Regression

REQ-001 still rejects client-supplied:

```text
request_status
```

---

# 105. No Intake Mutation Regression

Capture before/after values for:

```text
product_id
quantity
name
phone
email
dimensions
material
color
message
user_id
request_reference
```

Status transition must not alter any.

---

# 106. No Commerce Effects Test

After each representative transition:

```text
Orders unchanged
Payments unchanged
ProductStock unchanged
Cart unchanged
```

---

# 107. No Quote Test

Assert no:

```text
price
quoted_price
currency
```

persistence appears.

---

# 108. No Notifications Test

If notification tables exist:

transition itself should not create notifications unless pre-existing approved infrastructure already does.

Group R owns that.

---

# 109. Error Mapping Tests

If an internal/gated API seam exists, test:

```text
invalid enum → 422 INVALID_VALUE
invalid type → 422 INVALID_TYPE
invalid transition → 409 canonical conflict
```

---

# 110. Preserve Public Vocabulary

Errors must use:

```text
request_status
```

not internal DB implementation terminology.

---

# 111. No Schema Migration

Expected:

```text
Schema: NONE
```

---

# 112. No Dependency Change

Expected:

```text
Dependencies: NONE
```

---

# 113. No Frontend

Expected:

```text
Frontend: NONE
```

---

# 114. OpenAPI

Expected:

```text
UNCHANGED
```

The lifecycle already exists in the frozen contract.

Do not alter REQ-006 schema just because only the state-machine portion is implemented now.

---

# 115. REQ-001 Route

Must remain:

```text
STUB/GATED
```

for the existing 10.4/10.6 reasons.

Phase 10.3 does not affect creation-route activation.

---

# 116. REQ-006 Route

Report exact state after implementation.

Expected:

```text
STUB/GATED / operationally not public-ready
```

until Phase 10.7 completes authorization, staff representation, internal-note handling, and operational management.

---

# 117. Code Quality

Maintain:

```text
cognitive complexity <= 15
```

for touched/created functions.

Keep:

```text
<= 3 returns where practical
```

---

# 118. Avoid Magic Strings

Use:

```text
RequestStatus::SUBMITTED
RequestStatus::IN_REVIEW
RequestStatus::CLOSED
```

not repeated string literals.

---

# 119. Avoid Generic State Machine Framework

Do not install or build a generalized workflow engine.

Three states do not justify one.

Use a small explicit transition matrix.

---

# 120. No Event Sourcing

Do not introduce:

```text
event store
CQRS
sagas
workflow engine
```

for Request status.

---

# 121. No Request History Table

Again:

```text
no new status_history table
```

unless already explicitly present and approved.

---

# 122. Documentation

Add the next backend ADR if current repository practice continues one ADR per phase.

Likely topic:

```text
Furniture Request Status Lifecycle
```

Inspect the latest ADR number after:

```text
ADR/BACKEND-044
```

Do not assume the next number without checking.

---

# 123. ADR Content

Record:

```text
CLOSED enum
allowed transition matrix
direct SUBMITTED→CLOSED
CLOSED terminal
same-status idempotent no-op
no reopen
row-lock/concurrency strategy
original intake immutability
no Request→Order side effects
REQ-006 full API deferred to 10.7
no status-history schema
```

---

# 124. Group J Tracking

After PASS:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 READY
10.5 NOT STARTED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 125. Why 10.4 Is Next

Phase 10.4 will close the remaining domain gap on REQ-001:

```text
product_id supplied
→ Product exists
→ active
→ published
→ publicly visible
→ MADE_TO_ORDER
```

It must reuse the existing Request creation workflow.

Do not build a second workflow there.

---

# 126. Focused Verification Commands

Run focused lifecycle tests first, then full suite:

```bash
php artisan test --filter=RequestStatus
php artisan test --filter=FurnitureRequest
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

If MariaDB concurrency test added:

run it against:

```text
furnitureapp_test_disposable
```

using the repository's destructive-test guard.

---

# 127. OpenAPI Verification

Verify:

```text
docs/api/openapi.yaml
```

still parses.

No externally observable contract change is expected.

---

# 128. Completion Report

Return the following.

## Phase 10.3 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Status enum

Report exact enum/class and values:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

---

## State machine

Report exact class/service.

---

## Transition matrix

Report:

```text
SUBMITTED → IN_REVIEW   allowed
SUBMITTED → CLOSED      allowed
IN_REVIEW → CLOSED      allowed

IN_REVIEW → SUBMITTED   rejected
CLOSED → SUBMITTED      rejected
CLOSED → IN_REVIEW      rejected

same-state              idempotent no-op
```

---

## Persistence service

Report exact class and transaction ownership.

---

## Locking

Report:

```text
FurnitureRequest row locked FOR UPDATE
current state re-read inside transaction
```

or actual implementation.

---

## Idempotence

Report:

```text
same status
→ success/no-op
→ no duplicate business effect
```

and whether `updated_at` changes.

---

## Invalid transition

Report exact:

```text
HTTP/error code mapping
```

where tested.

---

## Request creation

Confirm:

```text
new Request → SUBMITTED
client cannot set request_status in REQ-001
```

---

## Intake immutability

Confirm status changes do not mutate:

```text
ownership
contact
product link
quantity
dimensions
material
color
notes
reference
```

---

## Staff notes

State:

```text
NOT IMPLEMENTED IN 10.3
Phase 10.7
```

---

## REQ-006

State exact route status:

```text
ACTIVE
```

or:

```text
STUB/GATED
```

Expected before 10.7:

```text
STUB/GATED
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

## Notifications

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

---

## Tests

Report:

```text
transition matrix tests
persistence tests
idempotent no-op tests
terminal-state tests
immutability tests
race/concurrency tests
10.1 creation regression
10.2 validation regression
full suite
```

---

## MariaDB

If executed, report:

```text
engine/version
test class
race scenarios
iterations
result
```

If not:

```text
NOT EXECUTED
```

with exact reason.

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

## Group J status

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS/BLOCKED
10.4 READY/BLOCKED
10.5 NOT STARTED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 129. Definition of Done

Phase 10.3 is complete when:

- Request status uses one CLOSED domain enum;
- only SUBMITTED, IN_REVIEW, CLOSED exist;
- new Requests still default to SUBMITTED;
- client cannot choose status during creation;
- transition logic is centralized;
- SUBMITTED→IN_REVIEW works;
- SUBMITTED→CLOSED works;
- IN_REVIEW→CLOSED works;
- IN_REVIEW→SUBMITTED fails;
- CLOSED→SUBMITTED fails;
- CLOSED→IN_REVIEW fails;
- CLOSED remains terminal;
- same-state assignment is idempotent no-op;
- no new arbitrary statuses are introduced;
- invalid enum and invalid transition remain different failure classes;
- transition evaluation occurs against current locked DB state;
- concurrent stale staff writes cannot reopen or overwrite CLOSED;
- status update modifies only status/timestamp;
- original intake data remains immutable;
- ownership remains immutable;
- no Order is created;
- no Payment is created;
- no inventory changes;
- no quote is created;
- no Request→Order conversion exists;
- no Request-specific status-history table is invented;
- no notification workflow is added;
- no frontend changes occur;
- REQ-001 remains gated for 10.4/10.6;
- full REQ-006 operational API remains deferred to 10.7;
- no schema migration is added;
- no dependency is added;
- full test suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- diff check passes.

---

# 130. Out of Scope

Do not implement:

```text
Phase 10.4 product-linked eligibility
Phase 10.5 enquiries
Phase 10.6 attachments
Phase 10.7 staff/admin request management
Phase 10.8 Group J closure tests

staff request list
staff request detail
staff_internal_notes mutation
request filtering/search
customer request history API
Request→Order conversion
quotations
pricing
inventory
ClickPesa
payments
notifications
frontend
```

---

# 131. STOP Condition

STOP when the repository has one authoritative lifecycle:

```text
SUBMITTED
  ├──→ IN_REVIEW ───→ CLOSED
  └─────────────────→ CLOSED
```

with:

```text
same-state → idempotent no-op
backward transition → conflict
CLOSED → terminal
```

and the transition is:

```text
transactional
row-locked
race-safe
intake-immutable
commerce-side-effect-free
```

Do not continue automatically to Phase 10.4.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 PASS — Request validation (ADR/BACKEND-044)
10.3 PASS — Request status lifecycle (ADR/BACKEND-045)
10.4 READY — Product-linked requests
10.5 NOT STARTED — General enquiries
10.6 NOT STARTED — Attachment handling
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Group J is **not** complete. The status lifecycle is authoritative, but the
public `POST /api/v1/requests` route remains **STUB/GATED** until Phase 10.4
(linked-product eligibility) and Phase 10.6 (attachments), and the operational
`PATCH /api/v1/requests/{request}` (REQ-006) remains **STUB/GATED** until
Phase 10.7.
