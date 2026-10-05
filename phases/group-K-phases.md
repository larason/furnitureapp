# Phase 11.13 — Audit Visibility

## Objective

Implement and close **Group K / Phase 11.13 — Audit Visibility** by exposing the existing append-only audit infrastructure through the frozen V1 administrative read endpoint:

```text id="e3pgrk"
ADM-007

GET /api/v1/admin/audit-logs
```

This phase resolves the known Group K contract/runtime gap:

```text id="d02evs"
Frozen API contract
→ ADM-007 exists
→ requires audit.view

Current runtime before Phase 11.13
→ audit_events already exist
→ AuditRecorder already writes events
→ ADM-007 is placeholder/stub
→ PermissionCatalog lacks audit.view
```

Phase 11.13 must therefore:

```text id="x6kom2"
add explicit audit.view RBAC capability
→ Admin only

activate ADM-007
→ read-only

build explicit safe Audit resource
→ no secrets/internal leakage

implement strict filtering/pagination
→ frozen allow-list only

preserve append-only audit integrity
→ zero mutation endpoints

verify historical audit producers
→ inventory, request, enquiry, staff/catalog where implemented

reconcile OpenAPI/docs
→ runtime truth

close Group K
```

Do NOT redesign the audit-writing architecture.

---

# 1. Read Repository Authorities First

Before editing anything, inspect the current repository.

At minimum inspect:

```text id="7b1j1m"
AGENTS.md

phases/group-K-phases.md
phases/group-J-phases.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md

routes/api.php

AuditEvent model
AuditRecorder

AuditAction
AuditResourceType

PermissionName
PermissionCatalog
RbacSeeder

Authorization

AdminController / Audit controller placeholder
ADM-007 route placeholder

existing audit migrations
existing audit tests
inventory audit tests
request audit tests
enquiry audit tests
staff lifecycle audit tests
catalog audit tests
OpenAPI Admin tests
RBAC tests
```

Do not assume the audit vocabulary from old documentation.

Read the actual current:

```text id="s7kzkj"
AuditAction
AuditResourceType
```

enums/constants and use them as the runtime CLOSED authority unless a genuine contract mismatch is discovered.

---

# 2. Mandatory Git Workflow Skill

The project owner authorizes Git operations.

Before any Git command, locate and read:

```text id="88n7pr"
git-workflow-and-versioning
```

from the project root.

The skill is authoritative for:

```text id="x2uz9m"
branching
status checks
staging
commit messages
versioning
tags
push behavior
cleanup
```

Do not substitute generic Git practices.

Never commit:

```text id="a2yaeg"
.env
database credentials
Clerk secrets
Cloudflare secrets
tokens
private keys
```

Preserve unrelated owner modifications.

Stage only Phase 11.13-related files.

Include all Git operations in the completion report.

---

# 3. Architectural Boundary

Phase 11.13 exposes audit **reads only**.

Canonical endpoint:

```text id="t6szf4"
GET /api/v1/admin/audit-logs
```

Do NOT add:

```text id="i2beu2"
POST   /api/v1/admin/audit-logs
PATCH  /api/v1/admin/audit-logs/{audit}
PUT    /api/v1/admin/audit-logs/{audit}
DELETE /api/v1/admin/audit-logs/{audit}

GET /api/v1/audit-logs
GET /api/v1/staff/audit-logs
GET /api/v1/audits
```

No aliases.

Audit events are system-generated historical records.

---

# 4. Audit Is Append-Only

The existing:

```text id="e01hkm"
audit_events
```

table is append-only business history.

Phase 11.13 must not introduce ordinary API mutation of an existing audit record.

No Admin may:

```text id="5zq2ig"
edit actor
edit action
edit resource
edit state snapshot
edit timestamp
delete audit event
rewrite request_id
```

Admin visibility is not audit ownership.

---

# 5. Preserve Existing Audit Writers

Do not rewrite existing business workflows merely to implement ADM-007.

Existing producers such as:

```text id="xc6n0u"
InventoryAdjustmentService
Request management
Enquiry close
Staff lifecycle
privileged catalog operations
```

where currently audited must continue to use the existing:

```text id="pzo3dq"
AuditRecorder
```

and existing transactional semantics.

Do not create a second audit pipeline.

---

# 6. Resolve `audit.view`

Add the explicit runtime permission:

```text id="r60hj4"
audit.view
```

to the established permission enum/catalog.

Use the same naming architecture as existing:

```text id="6hg9as"
inventory.view
inventory.manage
requests.view
requests.manage
enquiries.view
enquiries.manage
```

Do NOT add:

```text id="nh8p71"
audit.manage
audit.delete
audit.edit
audit.*
```

Phase 11.13 needs one read capability:

```text id="od3nmo"
audit.view
```

---

# 7. Role Matrix

Expected V1 assignment:

```text id="rf0f61"
CUSTOMER
→ no audit.view

STAFF
→ no audit.view

ADMIN
→ audit.view
```

The Admin role must receive it explicitly through:

```text id="fjdu98"
PermissionCatalog
RbacSeeder
```

No wildcard.

Do not infer permission from role at request time.

---

# 8. Permission Catalog Remains Single Authority

Preserve:

```text id="5xgmg2"
PermissionName
PermissionCatalog
RbacSeeder
Authorization
```

as the centralized RBAC chain.

Do NOT scatter:

```php id="ytjyah"
if ($user->role === 'ADMIN')
```

authorization checks.

ADM-007 must require:

```text id="qerdh0"
audit.view
```

through the standard authorization mechanism.

---

# 9. Existing Installations / Seed Reconciliation

Adding a new permission enum/catalog entry must work safely for:

```text id="wywb6h"
fresh DB
existing development DB
test DB
repeat seeding
```

Inspect how existing RBAC reconciliation is performed.

Ensure:

```text id="gq0yuv"
RbacSeeder
```

remains:

```text id="hdss7w"
idempotent
deterministic
```

Re-running it must:

```text id="78qmzj"
create audit.view if absent
assign audit.view to ADMIN
not duplicate permissions
not remove legitimate role state unexpectedly
```

Follow the existing seeding model.

Do not add manual SQL instructions as the normal solution.

---

# 10. Do Not Change CLOSED Roles

Roles remain exactly:

```text id="x1cnma"
CUSTOMER
STAFF
ADMIN
```

Do not add:

```text id="s7mib8"
AUDITOR
SUPERADMIN
SECURITY_ADMIN
MANAGER
```

Phase 11.13 does not justify another role.

---

# 11. ADM-007 Authorization

Canonical:

```text id="2xzqp9"
GET /api/v1/admin/audit-logs
```

Requirements:

```text id="4zcxti"
authentication required
+
audit.view
```

Expected:

```text id="5lzjct"
Anonymous
→ 401

CUSTOMER
→ 403

STAFF
→ 403

ADMIN with audit.view
→ 200

authenticated Admin-like test identity without audit.view
→ 403
```

No role-only bypass.

---

# 12. Read-Only Controller

Use a thin controller.

Conceptually:

```text id="b3q4wc"
request validation
→ authorization
→ authorized audit query
→ deterministic ordering
→ pagination
→ AuditResource
```

Do not put filtering/query complexity directly into a large controller.

Reuse established query-service patterns where appropriate.

---

# 13. Strict Query Allow-List

The frozen ADM-007 filter allow-list is:

```text id="v8fwks"
actor
action
resource_type
resource_id
created_from
created_to
page
per_page
```

Do not accept undocumented fields.

Reject:

```text id="h5mqvv"
actor_id
actor_role
role
user
event
type
resource
request_id
correlation_id
date
from
to
sort
sort_direction
pageSize
limit
```

unless one of those is actually the frozen canonical field after repository reconciliation.

The contract explicitly names:

```text id="h1kmu8"
actor
action
resource_type
resource_id
created_from
created_to
```

so do not casually rename them.

---

# 14. `actor` Filter Semantics — Inspect Before Implementing

The contract says:

```text id="lez3bg"
actor
```

not `actor_id`.

Inspect:

```text id="6ig9e9"
api-contract
OpenAPI
existing placeholder request
historical phase docs
```

and determine the intended frozen wire representation.

Prefer the existing opaque User API identity if that is already established.

Do NOT accept:

```text id="18yiwx"
numeric database ID
Clerk subject
email
```

as undocumented actor selectors.

If the frozen contract is genuinely ambiguous, record a narrow consistency reconciliation rather than silently guessing.

The resolved API must never expose raw DB identity.

---

# 15. `action` Filter

Filter against the existing CLOSED runtime audit action vocabulary.

Inspect:

```text id="hz2a6y"
AuditAction
```

and use those exact values.

Do NOT hard-code a second list in the controller if the enum can be reused.

Unknown action:

```text id="vty3ic"
422 INVALID_VALUE
field: action
```

or existing canonical query-validation equivalent.

Do not silently return an empty result for malformed/unknown enum input.

---

# 16. `resource_type` Filter

Filter using the existing:

```text id="hbb7d4"
AuditResourceType
```

CLOSED vocabulary.

Do not invent resource types merely for display.

Unknown resource type:

```text id="n7ti8s"
422
```

using canonical error behavior.

---

# 17. `resource_id` Filter

The audit table stores the resource identity snapshot used by existing AuditRecorder.

Inspect whether the stored value is:

```text id="kxn0tz"
opaque public ID
canonical resource reference
other server-produced stable identifier
```

Do not transform historical records unnecessarily.

ADM-007 filtering must match the existing stored semantic.

Do not expose a numeric DB key if the audit record already uses public IDs.

If historical events legitimately contain resource IDs of different opaque prefixes:

```text id="u0ow7z"
inv_...
req_...
enq_...
prod_...
...
```

the API may filter the stored resource identifier exactly.

Do not attempt a live lookup before filtering unless the existing contract requires one.

---

# 18. Historical Resource Independence

Audit visibility must not depend on the current business resource still existing.

Example:

```text id="n6ltvx"
AuditEvent
resource_type = product
resource_id = prod_ABC
```

must remain visible even if that Product later becomes:

```text id="it5g3a"
inactive
soft-deleted
otherwise unavailable
```

Do not join audit visibility to live resource authorization/visibility unless strictly necessary.

Audit is historical evidence.

---

# 19. Actor Historical Integrity

The audit schema deliberately preserves actor attribution.

Existing design uses restrictive deletion semantics so actor history cannot silently disappear.

Do not replace audit actor identity with:

```text id="dn0cyu"
current Clerk lookup
current email
current role inferred today
```

Use the recorded audit snapshot.

An actor's current role may differ from:

```text id="aq3wwy"
actor_role
```

recorded at event time.

The historical audit role must win.

---

# 20. Date Filters

Support:

```text id="178ob6"
created_from
created_to
```

using the API's canonical ISO8601 UTC format.

Validate:

```text id="7ljqy4"
valid timestamp
invalid timestamp
created_from <= created_to
```

Do not silently swap invalid ranges.

Use the audit event's authoritative occurrence timestamp.

Inspect whether persistence field is:

```text id="7wdvfd"
occurred_at
```

while API representation uses:

```text id="sdg4qc"
timestamp
```

and preserve the existing contract mapping.

---

# 21. Pagination

Use standard V1 pagination:

```text id="0cgww1"
page
per_page
```

Maximum:

```text id="hng69n"
100
```

Return:

```json id="nltbtb"
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

Do not invent cursor pagination.

---

# 22. Deterministic Ordering

Audit history should be newest-first operationally.

Inspect any frozen ordering.

If not otherwise specified, use:

```text id="ffg7p1"
occurred_at DESC
id DESC or stable deterministic ID tie-breaker
```

consistent with the actual audit schema.

Do not rely on DB natural order.

Document the selected existing/reconciled behavior.

Do not expose arbitrary client sort controls unless already frozen.

---

# 23. Audit Resource — Explicit Serialization

Create/reuse an explicit:

```text id="rtq778"
AuditResource
AuditLogResource
```

according to repository naming conventions.

Do NOT return:

```php id="kkyspm"
AuditEvent::paginate()
```

directly.

Do NOT use unrestricted:

```php id="l6by7v"
$model->toArray()
```

for the API response.

---

# 24. Canonical Audit Representation

Reconcile the existing OpenAPI `AuditLog` with actual stored fields.

The frozen conceptual fields are:

```text id="0bn4as"
id
actor_id
actor_role
action
resource_type
resource_id
previous_state
resulting_state
timestamp
request_id / correlation_id where available
```

Use the exact approved wire field naming.

Do not expose DB implementation names such as:

```text id="r5kfe3"
occurred_at
```

if the contract deliberately exposes:

```text id="rq8hlr"
timestamp
```

unless repository reconciliation establishes otherwise.

---

# 25. Audit ID Must Be Opaque

Inspect how `audit_events.id` currently works.

The API contract requires:

```text id="ogdbjj"
id: string
```

Do not leak an auto-increment database ID if that is considered internal by project conventions.

If an opaque Audit identifier already exists, use it.

If the table only has a numeric primary key and no public identifier, do NOT automatically add a migration just to invent an ID.

First inspect the current frozen OpenAPI and historical implementation intention.

If there is a genuine unresolved incompatibility:

```text id="5us5mw"
STOP expanding schema
document the blocker/reconciliation
```

rather than inventing speculative audit identifiers.

Prefer reuse of an existing safe encoded identifier utility if the project already has one appropriate for the Audit resource.

---

# 26. State Snapshot Representation — Inspect Runtime

Audit records persist:

```text id="kkboqd"
previous_state
resulting_state
```

Inspect actual DB/model casts.

Do not assume these are plain strings.

They may contain structured JSON/state snapshots from:

```text id="tfm3w7"
inventory
request
enquiry
staff lifecycle
catalog operations
```

The current OpenAPI may contain stale string typing.

The agent must reconcile:

```text id="6jmdta"
database reality
AuditRecorder API
existing events
frozen conceptual contract
OpenAPI
```

before finalizing serialization.

Do NOT stringify structured state solely to satisfy stale schema if doing so loses information.

Do NOT broaden the public response to arbitrary sensitive JSON without reviewing what existing writers store.

---

# 27. State Snapshot Data-Minimization Review

This is a mandatory security review.

Enumerate all currently supported:

```text id="oxxvno"
AuditAction × AuditResourceType
```

writers.

For each, inspect what is stored in:

```text id="s8e7li"
previous_state
resulting_state
```

Ensure ADM-007 will not expose secrets such as:

```text id="tkg742"
password/hash
Clerk subject
access token
refresh token
API secret
R2 credential
attachment capability token/digest
payment provider secret
webhook signature
database credential
```

Also review unnecessary PII.

Do not assume "Admin" means arbitrary secret disclosure is safe.

---

# 28. Do Not Silently Destroy Audit Evidence

If existing state snapshots contain legitimate business values such as:

```text id="g865ux"
inventory quantities
status values
catalog flags
internal operational notes where intentionally audited
```

do not silently remove them without contract justification.

The objective is:

```text id="csmhml"
historical usefulness
+
secret minimization
```

not blanket redaction.

Document any field-level serialization decision.

---

# 29. Request ID / Correlation ID

Audit events may store:

```text id="e1n5bx"
request_id
```

for correlation.

If the frozen resource exposes it, return the existing request/correlation identifier.

It is not:

```text id="fvnufy"
User ID
Order ID
secret token
```

Do not allow filtering by it unless the contract already permits that.

The frozen filter allow-list does NOT currently list request_id.

---

# 30. No Live Actor PII Expansion

Do NOT join every Audit event to User and expose:

```text id="90evp4"
email
phone
name
Clerk ID
current permissions
```

unless explicitly frozen.

Canonical audit actor identity is:

```text id="6oxkf9"
actor_id
actor_role
```

Keep ADM-007 lightweight and historically stable.

---

# 31. No Live Resource Expansion

Do NOT expand:

```text id="996w6u"
resource_id
```

into live:

```text id="db64og"
Product
Request
Enquiry
Inventory
Staff
Order
```

objects.

Audit log is history, not a polymorphic API browser.

This also avoids N+1 queries.

---

# 32. Query Performance / N+1

ADM-007 must be efficient.

The normal collection should query:

```text id="tl6zvt"
audit_events
```

with indexed/existing columns and paginate.

Do not produce:

```text id="jnq4bu"
one User query per event
one Product query per event
one Request query per event
```

Add a query-scaling regression where practical.

---

# 33. Do Not Add Speculative Audit Indexes Immediately

Inspect current migration/indexes.

If filters on:

```text id="5cmtnz"
actor
action
resource_type
resource_id
occurred_at
```

lack useful indexes, measure/review first.

Phase 11.13 may add a narrowly justified forward migration only if necessary for the frozen read surface and consistent with project schema practices.

However expected default is:

```text id="nss4ud"
schema changes = NONE
```

Do not add speculative indexes merely because filters exist.

If a schema change is required, document exactly why.

---

# 34. Private Caching

ADM-007 is private administrative data.

Return:

```http id="ihozyj"
Cache-Control: private, no-store
Vary: Authorization
```

plus Cookie variation where current middleware conventions require it.

Never CDN-cache audit logs.

---

# 35. No Audit Logging of Audit Reads by Default

Do NOT automatically create a new `AUDIT_LOG_VIEWED` event for every:

```text id="5j6k35"
GET /admin/audit-logs
```

unless the existing contract explicitly requires it.

Otherwise:

```text id="fjy8os"
reading audit log
→ creates audit event
→ reading audit log
→ creates audit event
```

causes recursive/noisy history growth.

HTTP/security access logs are separate.

Do not expand `AuditAction` solely for reads.

---

# 36. Preserve Mutation Audit Atomicity

Phase 11.13 must not weaken existing rule:

```text id="u1mf4f"
business mutation
+
required audit write
=
same transaction
```

Existing audited flows must still roll back if mandatory audit persistence fails.

ADM-007 is read-only and must not interfere with that.

---

# 37. Inventory Audit Regression

Create or reuse a successful:

```text id="g1w5w9"
INV-003
```

adjustment.

Then query ADM-007.

Prove the audit event is visible with correct:

```text id="pc3glh"
actor
actor_role
action
resource_type
resource_id
previous_state
resulting_state
timestamp
request_id where applicable
```

Do not reimplement inventory audit creation.

---

# 38. Request Audit Regression

Perform a real:

```text id="ewyuf0"
REQ-006
```

status transition.

Verify its event appears through ADM-007.

Prove:

```text id="6b4bwd"
REQUEST_STATUS_CHANGED
```

or actual current enum value is filterable through `action`.

Do not use made-up event names; inspect `AuditAction`.

---

# 39. Enquiry Audit Regression

Perform:

```text id="8cjvmg"
OPEN → CLOSED
```

through ENQ-006.

Verify:

```text id="o2sbst"
ENQUIRY_STATUS_CHANGED
```

or current canonical action appears through ADM-007.

Repeated idempotent close must not create duplicate transition history.

---

# 40. Other Existing Audit Producers

Inspect current repository for all:

```text id="kul0eu"
AuditRecorder
```

call sites.

Add targeted visibility regressions for important already-implemented domains, especially where Group K depends on them, such as:

```text id="g0vx1j"
privileged Product mutation
Category mutation
Staff lifecycle
Inventory adjustment
Request status
Enquiry status
```

Do not create fake producers just to fill the audit screen.

Test what actually exists.

---

# 41. Staff Lifecycle Audit Boundary

If:

```text id="dr4htz"
ADM-004 approve
ADM-005 suspend
ADM-006 reactivate
```

are currently implemented and audited, verify these events are visible.

Do NOT change Staff lifecycle behavior in 11.13.

If an action is still stubbed/not implemented, do not pull it forward simply to populate ADM-007.

---

# 42. Admin Bootstrap Boundary

Initial Admin bootstrap intentionally did not emit a normal audit event because the closed audit vocabulary lacked a truthful bootstrap/system action.

Preserve that decision.

Do NOT fabricate:

```text id="sbhgbu"
STAFF_APPROVED
USER_ROLE_CHANGED
```

for the initial Admin bootstrap.

Do not add a fake human actor.

Phase 11.13 visibility does not retroactively rewrite bootstrap history.

---

# 43. Strict Filter Tests

Add permanent tests for:

```text id="ekxbpu"
actor
action
resource_type
resource_id
created_from
created_to
page
per_page
```

including useful combinations:

```text id="hmw76f"
actor + action
resource_type + resource_id
action + date window
actor + resource_type + date window
```

Pagination totals must reflect the filtered dataset.

---

# 44. Unknown Query Rejection

Explicitly reject:

```text id="z5rd31"
actor_id
role
request_id
event
resource
from
to
sort
pageSize
limit
include
```

unless repository reconciliation establishes a listed field as canonical.

Do not silently ignore unknown query keys.

---

# 45. Enum Filter Tests

For:

```text id="gh3dgp"
action
resource_type
```

test:

```text id="xyh1av"
every representative valid enum
unknown enum
wrong type
empty string
```

Use strict validation.

Do not allow arbitrary strings that can only return empty results.

---

# 46. Actor Filter Security

Verify actor filter cannot be used with:

```text id="ja607a"
raw numeric user ID
Clerk subject
email
```

unless explicitly frozen.

Use safe opaque identity.

Do not reveal whether an unknown Clerk identity exists.

---

# 47. Date Window Tests

Cover:

```text id="u5bfbl"
from only
to only
both
exact boundary
invalid date
from > to
```

Define inclusive/exclusive boundaries according to current project convention and document them.

Do not guess inconsistent behavior between test and docs.

---

# 48. Pagination Tests

Cover:

```text id="tdtrpk"
default page
page 2
custom per_page
max 100
per_page > 100
page 0
negative page
non-integer page
```

Use canonical validation errors.

---

# 49. Ordering Tests

Create events with controlled timestamps/tie conditions.

Prove deterministic newest-first behavior.

Pagination must not duplicate/skip events due to unstable ordering.

---

# 50. Empty Result Behavior

Valid filters with zero matches return:

```json id="7e05fu"
{
  "data": [],
  "meta": {
    "pagination": {
      "...": "..."
    }
  }
}
```

not:

```text id="p9ml4o"
404
```

A valid empty collection is not an error.

---

# 51. Read-Only Route Regression

Explicitly prove these remain nonexistent:

```text id="k9lv8v"
POST /api/v1/admin/audit-logs
PATCH /api/v1/admin/audit-logs/{id}
PUT /api/v1/admin/audit-logs/{id}
DELETE /api/v1/admin/audit-logs/{id}
```

No compatibility aliases.

---

# 52. Customer/Staff Isolation

Test:

```text id="fc0tgf"
CUSTOMER
→ cannot read any audit logs

STAFF
→ cannot read any audit logs
```

Even if Staff created the audited business event.

Example:

```text id="f53mby"
Staff performs inventory adjustment
→ event exists

same Staff calls ADM-007
→ 403
```

Creating an event does not grant visibility into audit history.

---

# 53. Admin Permission Revocation Test

Where practical:

1. create Admin;
2. verify `audit.view`;
3. remove permission in test setup;
4. call ADM-007.

Expected:

```text id="gb15tp"
403
```

This proves the endpoint checks permission, not merely role.

---

# 54. State Snapshot Security Tests

Create representative events whose state payloads exercise existing domains.

Assert response never contains known sensitive keys such as:

```text id="5spzfk"
password
password_hash
clerk_user_id
token
access_token
refresh_token
secret
api_key
storage_key
capability_token
capability_digest
```

Use recursive response inspection where appropriate.

Do not add fake sensitive values to production logic.

---

# 55. Error Disclosure

ADM-007 errors must not reveal:

```text id="bxvpqd"
SQL
table names
file paths
PHP class names
stack traces
permission implementation
raw database IDs
```

Use canonical error envelopes with:

```text id="ju2a6z"
meta.request_id
```

where existing error conventions require it.

---

# 56. OpenAPI — ADM-007

Implement/reconcile the actual OpenAPI operation for:

```text id="ez74ca"
GET /api/v1/admin/audit-logs
```

Ensure it documents:

```text id="21stza"
operationId = ADM-007
authentication required
Admin / audit.view
query filter allow-list
pagination
AuditLog resource
200 collection response
401
403
422
429 if actual middleware applies
500
private caching where represented
```

Do not invent mutation operations.

---

# 57. OpenAPI AuditLog Schema

Reconcile the existing schema with actual runtime.

Current frozen conceptual schema includes:

```text id="dkn9s8"
id
actor_id
actor_role
action
resource_type
resource_id
timestamp
previous_state
resulting_state
```

and potentially:

```text id="8m3o4i"
request_id
```

where established.

Audit state types must match real serialized data.

Do not leave OpenAPI saying:

```text id="d5bay8"
previous_state: string
```

if runtime actually returns structured JSON.

Likewise do not change runtime to a lossy string merely to satisfy stale OpenAPI.

Reconcile deliberately.

---

# 58. API Contract Status

After successful implementation, change ADM-007 from:

```text id="sk6bpx"
PROPOSED / optional / placeholder
```

to the repository-consistent:

```text id="kimqkx"
APPROVED
```

where authoritative status tables are maintained.

Remove stale statements saying:

```text id="vt8c5t"
no audit.view exists
ADM-007 remains internal-only
ADM-007 is a stub
```

by superseding/reconciling them appropriately.

Do not rewrite historical ADR facts as though they never occurred.

---

# 59. Documentation Reconciliation

Update:

```text id="jhqdes"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/decisions.md
phases/group-K-phases.md
```

as necessary.

Final policy should be unambiguous:

```text id="lqj5qm"
ADM-007 is active V1.

Admin only.

Requires audit.view.

Read-only.

Strict filter allow-list.

Private/no-store.

Append-only source.

No PATCH/DELETE.

Audit creation remains mandatory for audited business mutations.
```

---

# 60. New ADR

Add the next repository-consistent ADR, following the latest existing identifier.

Suggested subject:

```text id="eo4h8q"
Phase 11.13 Audit Visibility and audit.view Reconciliation
```

Record:

```text id="1bg62s"
audit.view added to PermissionName/PermissionCatalog

ADMIN receives audit.view explicitly

CUSTOMER/STAFF do not

ADM-007 activated

existing audit_events/AuditRecorder reused

read-only filter/pagination surface

no audit mutation endpoint

no wildcard permission

no live User/resource expansion

state snapshot exposure reviewed for secrets

Admin bootstrap historical exception preserved

Group K audit consistency gap closed
```

---

# 61. Schema

Expected:

```text id="hlp6bg"
schema changes = NONE
```

Audit table already exists.

Do not create a second:

```text id="qms6b9"
audit_logs
admin_audit_logs
activity_logs
```

table.

Do not migrate from audit_events to another package.

If a genuine identifier/index incompatibility requires schema work:

```text id="glzt26"
report and justify it explicitly
```

before expanding scope.

---

# 62. Dependencies

Expected:

```text id="rnysf5"
dependency changes = NONE
```

Do not add:

```text id="tlwki7"
spatie activitylog
audit package
logging SaaS SDK
Elasticsearch
Meilisearch
```

The project already has the required audit persistence.

---

# 63. No External Logging Integration

Phase 11.13 does NOT integrate:

```text id="27ouhy"
Datadog
Sentry audit
CloudWatch
ELK
Loki
SIEM
```

Operational observability and domain audit are separate concerns.

ADM-007 reads the authoritative local audit_events domain history.

---

# 64. No Audit Export Yet

Do not add:

```text id="umnk9p"
CSV export
PDF export
bulk download
email export
external archival
```

unless frozen elsewhere.

Phase 11.13 is paginated API visibility only.

---

# 65. No Full-Text Audit Search

Do not add generic:

```text id="fof17r"
search
q
text
```

over state snapshots.

The frozen contract uses structured filters only.

This limits data exposure and query complexity.

---

# 66. No Audit Retention/Delete Policy

Do not introduce automatic:

```text id="we11pv"
retention cleanup
purge
archive
GDPR delete
TTL
```

during Phase 11.13.

Those require a separate legal/operations decision.

Preserve current records.

---

# 67. No Audit Editing Through Database Model Helpers

Review `AuditEvent` model for mass-assignment and mutation exposure.

Do not add normal:

```text id="ij0y2h"
update()
delete()
```

service paths for the API.

If model-level immutability protections already exist, preserve them.

Do not perform large unrelated model refactors.

---

# 68. Required RBAC Tests

Update permanent RBAC coverage to prove:

```text id="7yum4q"
PermissionName includes audit.view

PermissionCatalog:
CUSTOMER → no audit.view
STAFF → no audit.view
ADMIN → audit.view

wildcard still disabled

seeding is deterministic/idempotent
```

Update any canonical permission-count assertions deliberately.

Do not merely change expected count without proving matrix correctness.

---

# 69. Required ADM-007 Authorization Tests

At minimum:

```text id="td6g67"
anonymous → 401
Customer → 403
Staff → 403
Admin with audit.view → 200
Admin without audit.view → 403
```

Also verify private cache headers on 200.

---

# 70. Required Resource Tests

Assert each Audit row uses explicit approved fields.

Explicitly assert absence of:

```text id="neddo8"
raw user numeric ID
Clerk ID
password/hash
tokens
permission pivots
database internals
storage secrets
```

---

# 71. Required Filter Tests

Cover all canonical filters:

```text id="xoazur"
actor
action
resource_type
resource_id
created_from
created_to
```

plus:

```text id="o2wb33"
combined filters
empty valid result
strict unknown filters
pagination after filtering
```

---

# 72. Required Cross-Domain Visibility Tests

At minimum expose through ADM-007 events generated from currently implemented:

```text id="46otym"
Inventory adjustment

Request status transition

Enquiry close
```

If current Staff/catalog mutations already emit audits, include representative tests for those as well.

Do not fabricate missing audit producers.

---

# 73. Required Historical Snapshot Test

Create event.

Then alter current live business resource state.

Fetch audit event.

Assert:

```text id="r7nr0g"
actor_role
previous_state
resulting_state
resource_id
timestamp
```

remain historical snapshots and are not recomputed from live state.

---

# 74. Required Immutability Route Tests

Assert:

```text id="rl73k4"
POST
PATCH
PUT
DELETE
```

on audit log collection/detail are absent or method-not-allowed according to router behavior.

Do not implement Audit detail endpoint unless already frozen.

ADM-007 is collection-only.

---

# 75. Concurrency Requirements

ADM-007 itself is read-only and does not require a new special MariaDB race harness.

However, run existing relevant audited concurrency tests to prove the visibility implementation did not regress:

```text id="cvpj78"
Inventory concurrency audit
Request status concurrency audit
Enquiry close concurrency audit
```

especially:

```text id="xw7rfc"
exactly one business transition
→ exactly one real audit event
```

Do not create a new concurrency framework for audit reads.

---

# 76. Database Choice for Phase Tests

Normal ADM-007 feature tests may use the standard test database.

Real MariaDB is required only for previously established concurrency claims involving row locking.

Use:

```text id="qj8z5d"
furnitureapp_test_disposable
```

for those existing concurrency suites.

Never production/staging DB.

---

# 77. Code Quality

Follow project standards:

```text id="ngz2tq"
thin controllers

strict FormRequest/query validation

validated() only

central Authorization

explicit API Resources

closed enums/constants

no raw SQL unless established/necessary

cognitive complexity <= 15

<= 3 returns where practical

no secret logging
```

Do not introduce a generic repository framework solely for ADM-007.

---

# 78. Verification Commands

Run canonical equivalents:

```bash id="rsu08h"
cd backend/laravel

php artisan test
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit
php artisan route:list
git diff --check
```

Also run focused:

```text id="o4i8c4"
ADM-007 feature tests
RBAC tests
AuditResource tests
Audit filtering tests
OpenAPI Admin/Audit tests
Inventory audit tests
Request audit tests
Enquiry audit tests
relevant Staff/catalog audit tests
existing MariaDB audited concurrency tests
```

---

# 79. Group K Closure Review

After Phase 11.13 passes, inspect the Group K roadmap and explicitly record:

```text id="lv2qtn"
11.1 Admin information architecture
COMPLETE

11.2 Admin authentication
COMPLETE

11.3 Product CRUD
COMPLETE

11.4 Category CRUD
COMPLETE

11.5 Image management
COMPLETE

11.6 Inventory management
COMPLETE

11.7 Order management
DEFERRED

11.8 Customer management
COMPLETE

11.9 Request management
COMPLETE

11.10 Enquiry management
COMPLETE

11.11 Payment visibility
DEFERRED

11.12 Delivery management
DEFERRED

11.13 Audit visibility
COMPLETE
```

Do not accidentally mark the deferred transactional phases complete.

---

# 80. Group K Exit Criterion

Current request-first release policy allows Group K to close while:

```text id="o4l3y0"
11.7
11.11
11.12
```

remain explicitly deferred.

The Group K exit criterion is satisfied when Staff/Admin can operate all **currently active request-first business workflows** without direct DB access.

Audit visibility should be the final non-deferred Group K closure item.

---

# 81. Git Operations

After all implementation and verification passes:

1. read `git-workflow-and-versioning`;
2. inspect repository status;
3. stage only Phase 11.13 files;
4. follow required commit/version rules;
5. commit;
6. push only if the skill permits/requires it.

Do not stage unrelated files.

Do not bypass skill-required verification.

---

# 82. Completion Report

Return:

```text id="qejtna"
Phase 11.13 status:
PASS / BLOCKED

Known audit.view gap resolved:
YES / NO

ADM-007:
PASS / BLOCKED

Canonical route:
GET /api/v1/admin/audit-logs

Additional audit routes added:
NO

audit.view added:
YES / NO

CUSTOMER audit.view:
NO

STAFF audit.view:
NO

ADMIN audit.view:
YES

Wildcard permission:
NO

Permission seeding:
PASS / FAIL

Admin without audit.view:
403 / FAIL

Anonymous:
401 / FAIL

Customer:
403 / FAIL

Staff:
403 / FAIL

Read-only:
PASS / FAIL

Audit mutation endpoints:
NONE

Filter allow-list:
PASS / FAIL

actor filter:
PASS / FAIL

action filter:
PASS / FAIL

resource_type filter:
PASS / FAIL

resource_id filter:
PASS / FAIL

date filters:
PASS / FAIL

Unknown query rejection:
PASS / FAIL

Pagination:
PASS / FAIL

Deterministic ordering:
PASS / FAIL

Explicit AuditResource:
YES / NO

Sensitive-field review:
PASS / FAIL

Secret exposure:
NONE / FAIL

N+1:
PASS / FAIL

Historical actor snapshot:
PASS / FAIL

Historical state snapshot:
PASS / FAIL

Inventory audit visibility:
PASS / FAIL

Request audit visibility:
PASS / FAIL

Enquiry audit visibility:
PASS / FAIL

Other implemented audit producers:
<list>

Private/no-store:
PASS / FAIL

OpenAPI:
PASS / FAIL

ADM-007 status:
APPROVED / BLOCKED

Docs reconciliation:
PASS / FAIL

Schema changes:
NONE / <explain>

Dependency changes:
NONE / <explain>

Focused tests:
<x> passed, <assertions>

MariaDB audited concurrency:
<x> passed, <assertions>

Full PHPUnit:
<x> passed, <y> skipped, <assertions>

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
<result or NONE>

Group K:
CLOSED / BLOCKED
```

Also list:

```text id="fmsw3b"
files changed
tests added/changed
RBAC changes
documentation reconciliations
genuine defects fixed
security findings
```

---

# 83. STOP Condition

Phase 11.13 may be declared PASS only when:

- `audit.view` is a real centralized runtime permission;
- only Admin receives `audit.view`;
- ADM-007 is active and canonical;
- Customer and Staff cannot read audit history;
- an Admin lacking `audit.view` is denied;
- the endpoint is strictly read-only;
- no audit mutation routes exist;
- filters are strict and match the frozen allow-list;
- pagination and deterministic ordering work;
- audit events use explicit safe serialization;
- actor identity/role remain historical snapshots;
- resource history does not depend on the live resource still existing;
- state snapshots have been reviewed for secret/PII exposure;
- no credentials/tokens/secrets leak;
- existing audit writers remain authoritative;
- existing transactional audit atomicity is preserved;
- representative Inventory, Request and Enquiry events are visible;
- existing MariaDB concurrency/audit guarantees remain passing;
- OpenAPI matches runtime;
- stale `audit.view`/stub/internal-only documentation is reconciled;
- no second audit table/package is added;
- full test suite passes;
- PHPStan passes;
- Pint passes;
- Composer audit passes;
- route surface passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then record:

```text id="mm23pn"
Phase 11.13 — PASS

Group K — CLOSED

Phase 11.7 — DEFERRED
Phase 11.11 — DEFERRED
Phase 11.12 — DEFERRED
```

Do not start Group H, Group I, or any deferred transactional-commerce phase automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Phase Record — 2026-10-05

**Phase 11.13: PASS.** ADM-007 is active only at `GET /api/v1/admin/audit-logs`, requires explicit Admin `audit.view`, and has no detail or mutation routes. The collection uses strict filters, private/no-store caching, deterministic ordering, serialization-only derived audit IDs, and action/resource snapshot allow-lists. No schema or dependency change was required. Group K is closed; 11.7, 11.11, and 11.12 remain deferred.
