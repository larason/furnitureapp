# Phase 11.10 — Enquiry Management — Remaining Closure Work

## Objective

Finish the remaining Phase 11.10 work and close:

```text
Phase 11.10 — Enquiry Management
```

The first enforcement slice is already complete and must be preserved:

```text
CloseEnquiryRequest exists.

POST /api/v1/enquiries/{enquiry}/close
accepts only optional staff_internal_notes.

Unknown fields are rejected.

Immutable intake fields are rejected.

The note and CLOSED status persist atomically
inside the existing locked transaction.

Focused Enquiry tests:
15 passed / 92 assertions

Full backend:
1641 passed / 1 skipped

Pint:
PASS

git diff --check:
PASS
```

Do **not** reimplement or redesign that work.

The remaining Phase 11.10 closure gates are:

```text
1. OpenAPI/docs reconciliation
2. Expanded operational filtering tests
3. Expanded authorization tests
4. Explicit no-commerce-side-effect regression
5. MariaDB concurrency verification
6. Full verification
7. Phase documentation / ADR closure
8. Git workflow staging + commit
```

The target is:

```text
Phase 11.10 — PASS
Phase 11.13 — READY
```

Do not begin Phase 11.13.

---

# 1. Read Current State Before Editing

Inspect the current implementation rather than relying on earlier phase instructions.

At minimum inspect:

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

EnquiryController
CloseEnquiryRequest

Enquiry model
EnquiryStatus

Enquiry operational query/service
close/update transaction service

EnquiryResource
operational Enquiry resource

AuditRecorder
AuditAction
AuditResourceType

PermissionName
PermissionCatalog
Authorization

EnquiryStatusConcurrencyMysqlTest

existing ENQ-004 tests
existing ENQ-005 tests
existing ENQ-006 tests
existing Group J Enquiry tests
existing OpenAPI Enquiry tests
```

Determine exactly what is already implemented.

Do not duplicate existing passing coverage without reason.

---

# 2. Preserve the Already-Completed Close Contract

The current Phase 11.10 close behavior is now:

```http
POST /api/v1/enquiries/{enquiry}/close
```

Request body:

```json
{
  "staff_internal_notes": "Optional internal note"
}
```

`staff_internal_notes` is optional.

No other request-body fields are accepted.

Unknown fields must return canonical:

```text
422 INVALID_VALUE
```

Do NOT reintroduce:

```text
enquiry_status
subject
message
name
email
phone
category
product_id
order_id
user_id
attachment
created_at
updated_at
```

into the ENQ-006 request body.

The route itself is the controlled action:

```text
POST .../close
→ target status is server-controlled CLOSED
```

The client does not choose the target status.

---

# 3. Resolve Reopen Policy Definitively

This is a required closure decision.

Older documentation contains ambiguous wording such as:

```text
optional reopen
CLOSED → OPEN if approved
```

The implemented API surface is currently:

```text
POST /api/v1/enquiries/{enquiry}/close
```

and the current strict request accepts only:

```text
staff_internal_notes
```

Therefore inspect the actual implementation and tests.

Unless existing production code already contains a separately approved, callable, tested reopen operation:

```text
V1 Phase 11.10 policy =
OPEN → CLOSED
CLOSED remains CLOSED
```

Do NOT invent:

```text
POST /enquiries/{enquiry}/reopen
PATCH /enquiries/{enquiry}
{"enquiry_status":"OPEN"}
```

merely to satisfy stale wording.

If no approved reopen runtime exists, explicitly reconcile docs to say:

```text
ENQ-006 is close-only in current V1.

OPEN → CLOSED is the only state-changing transition.

Repeated close against CLOSED is idempotent.

No public/operational reopen action exists in V1.
```

Record the reconciliation in the ADR.

---

# 4. OpenAPI — ENQ-006

Update OpenAPI so ENQ-006 matches runtime exactly.

Canonical operation:

```text
POST /api/v1/enquiries/{enquiry}/close
```

Security:

```text
authenticated
OPERATIONAL
enquiries.manage
```

Request body must represent:

```json
{
  "staff_internal_notes": "Optional internal note"
}
```

Requirements:

```text
staff_internal_notes:
optional
nullable only if runtime permits null
bounded exactly according to current implementation
plain operational text
```

Schema must use:

```text
additionalProperties: false
```

Do NOT include:

```text
enquiry_status
```

as a client-controlled field.

If no body is required when closing without notes, OpenAPI must allow:

```text
{}
```

or an omitted body, exactly matching runtime.

Do not invent required fields.

---

# 5. ENQ-006 OpenAPI Errors

Ensure OpenAPI documents the real existing error surface.

At minimum verify correct use of:

```text
401 AUTHENTICATION_REQUIRED
403 FORBIDDEN
404 ENQUIRY_NOT_FOUND / RESOURCE_NOT_FOUND
422 INVALID_VALUE / schema validation
```

If the implementation can produce a state conflict, document the existing canonical:

```text
409
```

only if runtime actually does so.

Do not add error codes merely for documentation completeness.

Use only the CLOSED existing error registry.

---

# 6. OpenAPI — ENQ-004

Verify:

```text
GET /api/v1/enquiries
```

against actual runtime.

Authorization:

```text
enquiries.view
```

Check the exact supported query allow-list.

Expected current operational query surface should be reconciled against runtime and docs, including where implemented:

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

If runtime already supports approved:

```text
sort
sort_direction
```

document/test them.

If it does not:

```text
do not add them merely because stale documentation mentions them
```

Runtime and approved implementation should win over stale prose.

Unknown query fields must return canonical 422 behavior.

---

# 7. OpenAPI — ENQ-005

Verify:

```text
GET /api/v1/enquiries/{enquiry}
```

documents:

```text
authentication
enquiries.view
opaque enq_... identifier
operational Enquiry representation
401
403
404
private/no-store response
```

Do not expose internal database keys.

---

# 8. OpenAPI — ENQ-007 Regression

Verify the already-established attachment contract remains:

```text
POST /api/v1/enquiries/{enquiry}/attachments
Content-Type: multipart/form-data

field:
attachment
```

Do not allow OpenAPI to drift back to:

```text
file
upload
document
```

The canonical field is:

```text
attachment
```

This is a regression check, not an attachment redesign.

---

# 9. Operational Filter Test Expansion

Add focused permanent ENQ-004 coverage for all **actually supported frozen filters**.

At minimum, where implemented, prove:

```text
search by name
search by email
search by phone
search by subject
search by message
search by enquiry reference

enquiry_status = OPEN
enquiry_status = CLOSED

category

product_id

order_id

created_from

created_to
```

Also test meaningful combinations, for example:

```text
OPEN + category
product_id + date range
search + CLOSED
```

Do not test undocumented aliases.

---

# 10. Search by Order Reference

If the existing operational search contract supports Order reference, add a regression proving:

```text
search=<order_reference>
```

finds only Enquiries associated with that Order.

The search must not expose arbitrary Order information.

Only Enquiries matching the authorized operational dataset should be returned.

---

# 11. Filter Pagination Correctness

Filtering must happen before pagination.

Create enough Enquiries to prove:

```text
filter
→ authorized matching dataset
→ deterministic order
→ paginate
```

not:

```text
paginate everything
→ filter current page
```

Verify:

```text
total
last_page
has_next
has_previous
```

reflect the filtered result set.

---

# 12. Deterministic Ordering

Confirm ENQ-004 default ordering remains:

```text
created_at DESC
id ASC
```

or the existing equivalent.

Add a tie-condition regression where practical.

Do not rely on natural DB order.

---

# 13. Strict Query Validation

Explicitly reject unknown query parameters.

Cover examples such as:

```text
status
customer_id
assigned_to
priority
pageSize
sortBy
is_closed
```

Expected:

```text
422
canonical INVALID_VALUE-style error
```

according to existing API conventions.

Do not silently ignore them.

---

# 14. Filter Enum Validation

Verify:

```text
enquiry_status
```

accepts only:

```text
OPEN
CLOSED
```

and:

```text
category
```

accepts only the currently CLOSED values:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
```

Unknown values:

```text
422
```

No lowercase aliases unless existing runtime explicitly normalizes them.

---

# 15. Filter Identifier Validation

Verify malformed:

```text
product_id
order_id
```

are rejected through the existing opaque identifier validation.

Do not allow raw numeric IDs.

Do not allow Variant IDs as Product IDs.

Do not leak resource existence unnecessarily.

---

# 16. Date Validation

Verify strict behavior for:

```text
created_from
created_to
```

including:

```text
valid ISO8601 UTC
invalid format
created_from > created_to
```

Use current canonical validation errors.

Do not silently swap the interval.

---

# 17. Expanded ENQ-004 Authorization Tests

Permanent coverage must prove:

```text
Anonymous
GET /api/v1/enquiries
→ 401

Customer
→ 403

Staff with enquiries.view
→ 200

Admin with enquiries.view
→ 200

authenticated Staff without enquiries.view
→ 403
```

Use actual PermissionCatalog behavior.

Do not hard-code roles in the controller.

---

# 18. Expanded ENQ-005 Authorization Tests

Prove:

```text
Anonymous
→ 401

Customer
→ 403

Staff with enquiries.view
→ 200

Admin with enquiries.view
→ 200

Staff without enquiries.view
→ 403

unknown Enquiry
→ canonical 404

malformed opaque identifier
→ canonical behavior
```

---

# 19. Expanded ENQ-006 Authorization Tests

Prove:

```text
Anonymous
→ 401

Customer
→ 403

Staff with enquiries.view only
→ 403

Staff with enquiries.manage
→ allowed

Admin with enquiries.manage
→ allowed
```

If the permission model assigns view alongside manage by default, construct a permission-specific regression where practical so the controller/service itself does not rely merely on role identity.

---

# 20. No Admin Bypass

Verify ENQ-004/005/006 use central authorization.

Do not introduce:

```php
if ($user->hasRole('ADMIN')) {
    return true;
}
```

as a blanket bypass.

Admin authority remains explicit through:

```text
PermissionCatalog
Authorization
enquiries.view
enquiries.manage
```

---

# 21. No-Commerce-Side-Effect Regression

Add an explicit regression for ENQ-006.

Before closing an Enquiry, record counts/current state for relevant commerce/domain resources.

After:

```text
POST /api/v1/enquiries/{enquiry}/close
```

assert no unintended creation/mutation of:

```text
FurnitureRequest

Order
OrderItem

Payment

Delivery

ProductStock
reserved_quantity

order-item inventory allocations

Cart

quote/quoted_price state
```

where these models/tables exist.

At minimum prove that closing an Enquiry:

```text
does not create Request
does not create Order
does not create Payment
does not reserve inventory
does not alter ProductStock
```

Use narrow assertions consistent with current schema.

---

# 22. Order Association Is Read-Only

For an Enquiry linked to an Order:

1. snapshot relevant Order state;
2. close the Enquiry;
3. prove Order state remains unchanged.

Do not modify:

```text
order status
delivery fee
payment status
tracking
totals
```

through ENQ-006.

---

# 23. Product Association Is Read-Only

For an Enquiry linked to a Product:

1. snapshot Product state;
2. close Enquiry;
3. prove Product remains unchanged.

Do not modify:

```text
product type
active state
published state
price
variant
inventory
```

---

# 24. Request/Enquiry Separation Regression

Create:

```text
one Furniture Request
one General Enquiry
```

Operate on the Enquiry.

Assert the Furniture Request remains unchanged.

No automatic linking/conversion.

No shared status mutation.

No cross-resource notes.

---

# 25. Immutable Intake Regression

The first enforcement slice already added some tests.

Complete the matrix so ENQ-006 rejects all immutable intake fields.

At minimum cover:

```text
name
email
phone
subject
message
category
product_id
order_id
user_id
enquiry_status
attachment
created_at
updated_at
```

Expected:

```text
422
```

and verify persisted Enquiry intake is unchanged.

---

# 26. Internal Note Privacy Regression

Create authenticated Customer Enquiry.

Close it as Staff/Admin with:

```text
staff_internal_notes
```

Then retrieve it through:

```text
GET /api/v1/me/enquiries
GET /api/v1/me/enquiries/{enquiry}
```

Assert:

```text
staff_internal_notes
```

does NOT appear.

Also prove anonymous creation response never exposes it.

---

# 27. Operational Note Visibility

Retrieve the same Enquiry through:

```text
ENQ-005
```

with an authorized operational actor.

Verify:

```text
staff_internal_notes
```

is present according to the approved Staff resource.

This proves actor-specific serialization rather than globally hiding/removing the field.

---

# 28. Contact Snapshot Regression

For authenticated Customer:

1. create Enquiry;
2. alter Customer profile name/phone where existing self-service permits;
3. retrieve operational Enquiry;
4. verify stored Enquiry contact remains the historical snapshot.

Do not substitute current User profile values dynamically.

---

# 29. Historical Product Visibility Regression

Where supported by existing test fixtures:

1. create Product-linked Enquiry;
2. later make Product inactive/unpublished/soft-deleted;
3. retrieve operational Enquiry.

The Enquiry must remain visible.

Do not re-run public Product eligibility during ENQ-004/005.

Preserve safe historical Product context according to current implementation.

---

# 30. Historical Order Association Regression

Where existing schema/test helpers permit:

1. create valid Customer-owned Order association;
2. create linked Enquiry;
3. later change the Order workflow state;
4. ensure Enquiry remains retrievable operationally.

The Enquiry association does not disappear because Order state changed.

Do not add transactional Order behavior.

---

# 31. MariaDB Concurrency — Mandatory Closure Gate

Run the existing:

```text
EnquiryStatusConcurrencyMysqlTest
```

against:

```text
furnitureapp_test_disposable
```

Use only the existing guarded disposable database workflow.

Do not use SQLite as concurrency proof.

Do not run against:

```text
furnitureapp
production
staging
```

---

# 32. Concurrent Close Requirements

The MariaDB test must exercise the actual audited ENQ-006 business path.

Use true concurrent workers as already established by Group J.

Expected under repeated simultaneous close:

```text
initial:
OPEN

worker A:
close

worker B:
close
```

Final:

```text
CLOSED
```

Exactly:

```text
one real OPEN → CLOSED transition
```

must occur.

No stale update.

No duplicate business transition.

---

# 33. Concurrent Close Audit Requirement

Under the same race:

```text
exactly one ENQUIRY_STATUS_CHANGED
```

or the current canonical equivalent must represent the real:

```text
OPEN → CLOSED
```

transition.

The idempotent concurrent loser/replay must not create a second real transition audit.

Use DB assertions, not logs alone.

---

# 34. Concurrent Internal Note Behavior

Because ENQ-006 now permits optional:

```text
staff_internal_notes
```

inspect current transaction semantics.

Do not invent a note-merge algorithm.

At minimum ensure:

```text
concurrent close never violates status correctness
```

and the final note value follows deterministic/current transaction behavior.

If both workers submit notes, document actual last-lock-holder/transaction behavior if relevant.

The closure gate is primarily:

```text
status correctness
audit correctness
atomic note+status persistence
```

not collaborative note merging.

---

# 35. Audit Rollback Regression

If not already permanently covered:

force AuditRecorder failure using the established test approach.

Then call ENQ-006.

Assert:

```text
status remains OPEN
staff_internal_notes unchanged
```

The close must not commit without its mandatory audit.

Do not weaken audit atomicity.

---

# 36. Same-State Replay Audit Regression

For a CLOSED Enquiry:

call:

```text
POST /enquiries/{enquiry}/close
```

again.

Prove:

```text
no second real status-transition audit
```

If notes are supplied on the replay, preserve whatever currently approved semantics exist for note changes, but do not fabricate a second:

```text
OPEN → CLOSED
```

event.

---

# 37. Private Cache Headers

Verify:

```text
ENQ-004
ENQ-005
ENQ-006 response
```

use the existing private semantics.

Expected:

```http
Cache-Control: private, no-store
Vary: Authorization
```

plus Cookie where the middleware convention requires it.

Enquiry PII must never be public cached.

---

# 38. Attachment Regression

Do not redesign attachment handling.

Only ensure Phase 11.10 does not regress:

```text
0 or 1 attachment
private parent-scoped storage
safe metadata only
X-Upload-Token behavior
attachment multipart field
```

Operational resources must not expose:

```text
storage_disk
storage_key
capability HMAC/digest
raw X-Upload-Token
filesystem path
```

---

# 39. No New Routes

After changes, verify canonical Enquiry route surface remains unchanged.

Allowed relevant routes:

```text
POST /api/v1/enquiries
GET  /api/v1/me/enquiries
GET  /api/v1/me/enquiries/{enquiry}

GET  /api/v1/enquiries
GET  /api/v1/enquiries/{enquiry}
POST /api/v1/enquiries/{enquiry}/close

POST /api/v1/enquiries/{enquiry}/attachments
```

Do not add:

```text
/admin/enquiries
/staff/enquiries
/enquiries/{enquiry}/reopen
PATCH /enquiries/{enquiry}
DELETE /enquiries/{enquiry}
```

unless such route already existed as a frozen approved contract—which must be proven before keeping it.

---

# 40. Docs Reconciliation — Reopen

Search:

```text
reopen
CLOSED→OPEN
optional reopen
```

in:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/api/openapi.yaml
docs/decisions.md
```

If actual V1 runtime is close-only, update stale wording consistently.

Preferred final documentation:

```text
EnquiryStatus:
OPEN | CLOSED

ENQ-006:
POST /enquiries/{enquiry}/close

OPEN → CLOSED

Repeated close is idempotent.

CLOSED is terminal through the current V1 API.

No reopen operation is exposed.
```

Do not alter historical ADR text where doing so would falsify history; add a superseding reconciliation where appropriate.

---

# 41. Docs Reconciliation — ENQ-006 Request Shape

Document clearly:

```text
ENQ-006 body allow-list:
staff_internal_notes only
```

No:

```text
enquiry_status
```

client input.

Explain:

```text
the action route determines CLOSED status server-side
```

This prevents future generic-status mutation drift.

---

# 42. Docs Reconciliation — Filter Surface

Ensure all authoritative docs agree on the actual ENQ-004 filter allow-list.

Remove stale filters only if runtime/approved contract establishes they are not part of current V1.

Do not casually delete frozen functionality.

Reconcile based on:

```text
runtime
existing tests
accepted ADRs
OpenAPI
```

and document any true consistency correction.

---

# 43. Phase ADR

Add the next repository-consistent ADR, likely following:

```text
ADR/BACKEND-052
```

with the next valid identifier.

Suggested subject:

```text
Phase 11.10 Enquiry Management Closure
```

Record:

```text
Group K reuses Group J ENQ-004/005/006.

ENQ-006 is an explicit close action, not generic status mutation.

staff_internal_notes is the only optional client field for close.

Original Enquiry intake remains immutable.

Request and Enquiry remain separate domains.

No Order/Payment/Inventory/Quote side effects.

Operational reads remain private.

MariaDB concurrent close proves exactly one real transition/audit.

No Admin/Staff alias routes were added.

Actual reopen policy is explicitly reconciled.
```

---

# 44. Phase Documentation

Update:

```text
phases/group-K-phases.md
```

with actual evidence.

Do not mark PASS before all gates below pass.

Record focused and full test counts.

Record MariaDB evidence separately from SQLite/PHPUnit.

---

# 45. Expected Production-Code Scope

This closure slice should mostly be:

```text
tests
OpenAPI
documentation
possibly small query/auth defect fixes
```

Large new production code is a warning sign.

Do not refactor working Group J Enquiry architecture unnecessarily.

---

# 46. Schema and Dependency Gate

Expected:

```text
schema changes = NONE
dependency changes = NONE
```

Do not add migrations.

Do not add Composer packages.

If either appears necessary:

```text
STOP that expansion
report the actual blocker
```

rather than inventing new infrastructure.

---

# 47. Focused Verification

Run all focused Enquiry/Group J suites relevant to:

```text
ENQ-001 creation
ENQ-002/003 ownership
ENQ-004 operational list/filter/search
ENQ-005 detail
ENQ-006 close
ENQ-007 attachments
privacy
authorization
audit
OpenAPI
```

Record:

```text
tests passed
assertions
```

---

# 48. MariaDB Verification

Run:

```text
EnquiryStatusConcurrencyMysqlTest
```

against:

```text
furnitureapp_test_disposable
```

Record:

```text
tests
iterations
assertions
DB engine evidence if current test reports it
```

Do not claim MariaDB PASS if the test was skipped.

---

# 49. Full Verification

Run canonical repository commands:

```bash
cd backend/laravel

php artisan test
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit
php artisan route:list
git diff --check
```

All must pass.

If OpenAPI has a dedicated contract suite, run it explicitly.

---

# 50. Git Workflow

After all closure gates pass:

1. locate/read root `git-workflow-and-versioning`;
2. inspect status;
3. preserve unrelated owner changes;
4. stage only Phase 11.10 files;
5. follow the skill's commit-message/versioning requirements;
6. commit;
7. push only if the skill allows/requires it.

Do not invent Git conventions outside the skill.

Do not bypass any verification required by the skill.

---

# 51. Completion Report

Return this exact information:

```text
Phase 11.10 status:
PASS / BLOCKED

Existing Group J implementation reused:
YES / NO

First enforcement slice preserved:
PASS / FAIL

ENQ-004:
PASS / BLOCKED

ENQ-005:
PASS / BLOCKED

ENQ-006:
PASS / BLOCKED

Canonical routes only:
PASS / FAIL

Admin/Staff Enquiry aliases added:
NO

Reopen policy:
<CLOSED terminal / existing approved reopen>

Reopen route added:
NO

ENQ-006 client-controlled enquiry_status:
NO

ENQ-006 allowed body:
staff_internal_notes only

Unknown close fields rejected:
PASS / FAIL

Immutable intake:
PASS / FAIL

Atomic close + internal note:
PASS / FAIL

enquiries.view:
PASS / FAIL

enquiries.manage:
PASS / FAIL

Customer operational access:
REJECTED / FAIL

Anonymous operational read:
REJECTED / FAIL

Customer internal-note exposure:
NO / FAIL

Operational internal-note visibility:
PASS / FAIL

ENQ-004 filters:
PASS / FAIL

Strict unknown-query rejection:
PASS / FAIL

Pagination:
PASS / FAIL

Deterministic ordering:
PASS / FAIL

Contact snapshot preservation:
PASS / FAIL

Request/Enquiry separation:
PASS / FAIL

Order side effects:
NONE / FAIL

Payment side effects:
NONE / FAIL

Inventory side effects:
NONE / FAIL

Request side effects:
NONE / FAIL

Quote behavior:
NONE

Attachment privacy:
PASS / FAIL

Private/no-store:
PASS / FAIL

Audit atomicity:
PASS / FAIL

Repeated close audit idempotency:
PASS / FAIL

MariaDB concurrent close:
PASS / FAIL

Exactly one real OPEN→CLOSED transition:
PASS / FAIL

Exactly one real transition audit:
PASS / FAIL

OpenAPI:
PASS / FAIL

Docs reconciliation:
PASS / FAIL

Schema changes:
NONE / <explain>

Dependency changes:
NONE / <explain>

Focused tests:
<x> passed, <assertions>

MariaDB tests:
<x> passed, <assertions>

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

git diff --check:
PASS / FAIL

Git workflow skill read:
YES / NO

Git operations performed:
<exact actions>

Commit:
<hash + message>

Push:
<result or NONE according to skill>

Phase 11.13:
READY / BLOCKED
```

Also list:

```text
files changed
tests added/changed
genuine defects fixed
documentation reconciliations
security findings
```

---

# 52. Final Closure Gate

Phase 11.10 may be declared **PASS** only when all of the following are true:

- ENQ-004/005/006 remain canonical;
- no Admin/Staff aliases were added;
- ENQ-006 is strict and only accepts optional `staff_internal_notes`;
- target `CLOSED` status is server-controlled;
- Customer intake remains immutable;
- Customer responses never expose internal notes;
- operational filters/search are fully regression-tested;
- `enquiries.view` and `enquiries.manage` remain distinct;
- Enquiry remains separate from Furniture Request;
- closing an Enquiry creates no Order, Payment, inventory reservation, Request, Delivery, or quote;
- attachment privacy remains intact;
- actual reopen policy is explicitly resolved;
- OpenAPI matches runtime;
- authoritative docs agree with runtime;
- MariaDB concurrent-close verification passes;
- concurrent closes create exactly one real `OPEN → CLOSED` transition;
- exactly one real transition audit is committed;
- full PHPUnit passes;
- PHPStan passes;
- Pint passes;
- Composer audit passes;
- route surface passes;
- `git diff --check` passes;
- Phase documentation/ADR is complete;
- Git operations follow `git-workflow-and-versioning`.

Only then report:

```text
Phase 11.10 — PASS

Phase 11.7 — DEFERRED
Phase 11.11 — DEFERRED
Phase 11.12 — DEFERRED

Phase 11.13 — READY
```

Do not begin Phase 11.13 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**