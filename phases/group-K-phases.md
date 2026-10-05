# Phase 11.10 — Enquiry Management

## Objective

Complete **Group K / Phase 11.10 — Enquiry Management** by reusing and hardening the already-implemented Group J operational Enquiry workflow.

Canonical V1 operational endpoints remain:

```text
ENQ-004
GET /api/v1/enquiries

ENQ-005
GET /api/v1/enquiries/{enquiry}

ENQ-006
POST /api/v1/enquiries/{enquiry}/close
```

Related Enquiry endpoints remain:

```text
ENQ-001
POST /api/v1/enquiries

ENQ-002
GET /api/v1/me/enquiries

ENQ-003
GET /api/v1/me/enquiries/{enquiry}

ENQ-007
POST /api/v1/enquiries/{enquiry}/attachments
```

This phase must not create a second Admin-specific Enquiry API.

The task is:

```text
reuse
→ inspect actual runtime contract
→ harden authorization/filtering/privacy
→ verify immutable history
→ verify audited close behavior
→ verify concurrency
→ reconcile OpenAPI/docs
```

---

# 1. First Action — Inspect Existing Group J Enquiry Implementation

Before editing code, inspect at minimum:

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
Enquiry model
EnquiryStatus
operational Enquiry query/service
ENQ-006 close service
Enquiry FormRequests
Enquiry Resources

AuditRecorder
AuditAction
AuditResourceType

Enquiry attachment services/resources
attachment capability services

PermissionName
PermissionCatalog
Authorization

existing ENQ-001..007 tests
EnquiryStatusConcurrencyMysqlTest
Group J closure tests
OpenAPI Enquiry contract tests
```

Determine the actual implemented behavior.

Do not assume old conceptual wording such as:

```text
optional reopen
```

is implemented.

Runtime + accepted Group J decisions are authoritative.

---

# 2. Mandatory Git Workflow Skill

Git operations are authorized.

Before ANY Git command, locate and read the root project skill:

```text
.agents/skills/git-workflow-and-versioning
```

Follow it exactly for:

```text
branch handling
status inspection
staging
commit format
versioning
push
tags
cleanup
```

Do not substitute your own workflow.

Never commit:

```text
.env
credentials
Cloudflare secrets
Clerk secrets
database secrets
tokens
```

Preserve unrelated owner changes.

Stage only Phase 11.10-related files.

Report every Git action at completion.

---

# 3. Canonical Route Boundary

Use only:

```text
GET  /api/v1/enquiries
GET  /api/v1/enquiries/{enquiry}
POST /api/v1/enquiries/{enquiry}/close
```

Do NOT add:

```text
/api/v1/admin/enquiries
/api/v1/admin/enquiries/{enquiry}
/api/v1/staff/enquiries
/api/v1/support/*
```

No aliases.

The future Admin/Staff frontend consumes the canonical Enquiry domain API.

---

# 4. Enquiry Is a Separate Domain

An Enquiry is private general communication.

It is NOT:

```text
Furniture Request
Order
Payment
Quote
Inventory operation
Support chat thread
CRM ticket
```

Preserve the fundamental distinction:

```text
Request
→ "Can you make this furniture?"

Enquiry
→ "I have a general/product/order/business question."
```

Do NOT introduce:

```text
Enquiry → Request conversion
Enquiry → Order conversion
Enquiry → Payment
quote generation
inventory reservation
production workflow
```

---

# 5. Current Request-First Release Boundary

The project remains request-first.

Phase 11.10 does not reactivate:

```text
checkout
payments
order lifecycle
delivery operations
```

An Enquiry may reference an existing Order historically/contextually where already supported, but Enquiry management must not mutate that Order.

---

# 6. Existing Enquiry Status Enum

Preserve the CLOSED enum:

```text
OPEN
CLOSED
```

Do NOT add:

```text
ASSIGNED
IN_PROGRESS
WAITING_FOR_CUSTOMER
ESCALATED
RESOLVED
ARCHIVED
CANCELLED
```

New Enquiries default to:

```text
OPEN
```

---

# 7. Reopen Ambiguity — Inspect, Do Not Invent

Historical contract text contains:

```text
CLOSED → OPEN
optional if explicitly approved
```

However Group J's executed concurrency closure proves only:

```text
OPEN → CLOSED
```

and concurrent close idempotency/audit.

Therefore:

1. inspect the implemented ENQ-006 runtime;
2. inspect its tests;
3. inspect the accepted Group J decisions;
4. preserve actual approved behavior.

Do NOT implement reopen merely because an older document says:

```text
optional reopen
```

If reopen is not currently implemented:

```text
CLOSED remains terminal for the current runtime surface
```

and Phase 11.10 must not add a reopen endpoint/action.

If reopen is already implemented and permanently tested, preserve it exactly.

Document the resolved behavior explicitly.

---

# 8. ENQ-004 — Operational Enquiry Queue

Canonical:

```text
GET /api/v1/enquiries
```

Authorization:

```text
enquiries.view
```

Expected actors:

```text
STAFF with enquiries.view
ADMIN with enquiries.view
```

Denied:

```text
anonymous
CUSTOMER
authenticated actor without enquiries.view
```

Staff operational access is purpose-bound.

Staff does not own the Enquiry.

---

# 9. Pagination

Preserve:

```text
page
per_page
```

with existing V1 semantics.

Maximum:

```text
100
```

Use the standard:

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

No Enquiry-specific pagination format.

---

# 10. ENQ-004 Filter Allow-List

Preserve the established operational filter set.

Inspect runtime/OpenAPI, but expected approved filters include:

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

Historical docs also mention `sort`/`sort_direction`; only preserve them if they are already frozen and implemented.

Do not invent new filters.

Reject unknown query fields.

Do not silently accept:

```text
status
customer_id
email
phone
assigned_to
priority
resolved
pageSize
sortBy
```

aliases.

---

# 11. Search Semantics

Preserve approved search coverage across fields such as:

```text
name
email
phone
subject
message
enquiry reference
order reference
```

according to the current implementation.

Do not search:

```text
staff_internal_notes
Clerk IDs
raw DB IDs
permissions
attachment storage keys
audit internals
```

unless already frozen.

Search must run over the authorized operational dataset.

---

# 12. Enquiry Status Filter

`enquiry_status` accepts only:

```text
OPEN
CLOSED
```

Unknown values:

```text
422 INVALID_VALUE
```

Do not introduce aliases.

---

# 13. Category Filter

Preserve CLOSED category values where currently supported:

```text
GENERAL
PRODUCT
DELIVERY
OTHER
```

Do not invent:

```text
SUPPORT
PAYMENT
CUSTOM
COMPLAINT
```

without contract approval.

Unknown category must fail validation.

---

# 14. Product Filter

`product_id` must follow existing opaque Product identifier rules.

Do not accept:

```text
Product name
slug unless contract allows it
Variant ID
SKU
raw DB ID
```

as undocumented alternatives.

General Enquiries with:

```text
product_id = null
```

must remain fully operationally visible.

---

# 15. Order Filter

`order_id` filter is operational context only.

Use the existing frozen Order identifier representation.

Do not use the Enquiry API to expose unrestricted Order data.

Do not interpret Order association as permission to mutate the Order.

---

# 16. Date Filters

Preserve:

```text
created_from
created_to
```

as ISO8601 UTC.

Validate strictly.

If:

```text
created_from > created_to
```

use existing cross-field validation.

Do not silently reorder dates.

---

# 17. Deterministic Ordering

Default operational ordering remains:

```text
created_at DESC
id ASC
```

or the exact existing implementation.

Always retain deterministic tie-breaking.

Do not rely on database natural order.

---

# 18. ENQ-005 — Operational Detail

Canonical:

```text
GET /api/v1/enquiries/{enquiry}
```

Authorization:

```text
enquiries.view
```

Resolve using canonical opaque:

```text
enq_...
```

identifier only.

Do not resolve by:

```text
email
phone
subject
order reference
numeric DB ID
```

in the URI.

Unknown resource:

```text
canonical 404
```

---

# 19. Operational Enquiry Representation

Preserve explicit allow-listed operational fields.

Expected approved data includes:

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
staff_internal_notes

user_id
attachments

created_at
updated_at
```

Use exact existing field names.

Do not serialize the Eloquent model wholesale.

---

# 20. Historical Contact Snapshot

Contact fields:

```text
name
email
phone
```

are historical Enquiry snapshots.

A later Customer profile change must NOT rewrite the Enquiry.

Do not dynamically substitute current profile data during serialization.

---

# 21. Immutable Subject and Message

Customer-submitted:

```text
subject
message
```

are immutable historical truth.

Staff/Admin must not edit or "correct" them.

Do not add:

```text
PATCH /enquiries/{enquiry}
```

for arbitrary content mutation.

Preserve plain-text semantics.

---

# 22. Plain-Text Safety

Subject/message remain untrusted plain text.

Do not:

```text
interpret HTML
render Markdown server-side
execute templates
sanitize into different historical content
```

Store and return according to existing plain-text contract.

Frontend escaping remains required downstream.

---

# 23. Product Context Is Historical

If an Enquiry links to a Product, later Product changes must not destroy the Enquiry.

Operational Enquiry management must not require the linked Product to still be:

```text
active
published
public
```

Creation-time eligibility and historical read are separate concerns.

Do not re-run creation eligibility on ENQ-004/005/006.

---

# 24. Order Context Is Historical / Read-Only

If an Enquiry references an Order:

```text
order
```

may expose only the safe approved summary such as:

```text
id
order_reference
status
```

according to existing resources.

Do not expose:

```text
payment secrets
full delivery data
full Order internals
inventory allocations
```

through the Enquiry resource.

Do not mutate Order state from ENQ-006.

---

# 25. `staff_internal_notes`

Preserve separation:

```text
message
→ customer historical communication

staff_internal_notes
→ internal operational notes
```

Never expose `staff_internal_notes` via:

```text
ENQ-001
ENQ-002
ENQ-003
```

Customer cannot read internal notes.

Anonymous creation response cannot read internal notes.

---

# 26. ENQ-006 — Controlled Close Action

Canonical:

```text
POST /api/v1/enquiries/{enquiry}/close
```

Authorization:

```text
enquiries.manage
```

Do NOT replace with:

```text
PATCH /api/v1/enquiries/{enquiry}
```

unless the current frozen runtime already uses an internal body contract behind ENQ-006.

No generic mutation endpoint.

---

# 27. ENQ-006 Writable Fields

Inspect actual implemented FormRequest/service.

Preserve only the already-approved operational fields, expected to be:

```text
enquiry_status
staff_internal_notes
```

or the narrower close-action equivalent already implemented.

Do not broaden.

Reject attempts to write:

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
attachments
created_at
updated_at
```

---

# 28. Close Semantics

If current runtime is close-only:

```text
OPEN → CLOSED
```

is the only real status transition.

Same close replay should follow existing idempotent behavior.

Do not add `OPEN` body values to a `/close` action unless already implemented.

Do not invent:

```text
/reopen
```

route.

---

# 29. Same-State Idempotency

Concurrent or repeated close requests must not create duplicate business effects.

Expected:

```text
OPEN → CLOSED
→ one real transition

CLOSED → close again
→ idempotent existing result
```

according to established Group J behavior.

Do not create duplicate audit records.

---

# 30. Internal Notes + Close Atomicity

Where ENQ-006 supports internal notes alongside close:

```text
status
+
staff_internal_notes
```

must commit atomically.

If close fails:

```text
notes must not partially persist
```

If audit write fails:

```text
status/notes must roll back
```

Preserve existing transaction semantics.

---

# 31. Audit Requirements

Preserve Group J audit behavior.

Real Enquiry status changes must generate the existing:

```text
ENQUIRY_STATUS_CHANGED
```

or current canonical action.

Audit should retain server-derived:

```text
actor_id
actor_role
resource
previous state
resulting state
timestamp
request/correlation ID
```

Never accept actor metadata from client input.

---

# 32. Audit Idempotency

A repeated same-state close must not produce multiple real transition audits.

The Group J MariaDB closure already established:

```text
10 concurrent closes
→ final CLOSED
→ exactly one OPEN → CLOSED audit
```

Preserve this property.

---

# 33. Audit Atomicity

Audit persistence belongs to the business transaction.

If audit fails:

```text
Enquiry mutation rolls back
```

Do not permit unaudited successful state change.

Do not expose general audit browsing here.

Phase 11.13 owns ADM-007.

---

# 34. Concurrency Authority

Preserve the established:

```text
DB transaction
+
lockForUpdate
+
ConcurrentTransaction/bounded retry
```

pattern.

Do not replace with:

```text
Redis locks
distributed mutex
queue serialization
lock_version
table locks
```

MariaDB/InnoDB remains concurrency authority.

---

# 35. Existing MariaDB Close Race

Run the existing:

```text
EnquiryStatusConcurrencyMysqlTest
```

against:

```text
furnitureapp_test_disposable
```

using existing guards.

Preserve proof that concurrent closes result in:

```text
one real OPEN → CLOSED transition
final CLOSED state
one real audit
no stale overwrite
```

SQLite is not sufficient concurrency evidence.

---

# 36. Enquiry Attachments

ENQ-007 remains the canonical separate attachment flow.

Do not add:

```text
GET /enquiries/{enquiry}/attachments
DELETE /enquiries/{enquiry}/attachments/{attachment}
PATCH /enquiries/{enquiry}/attachments/{attachment}
```

unless already frozen.

Operational detail may expose safe metadata only.

---

# 37. Attachment Privacy

Attachments remain private to the parent Enquiry.

Do NOT use the public Product-image CDN model.

Never expose:

```text
storage_disk
storage_key
filesystem path
R2 credentials
capability digest
raw upload token
```

---

# 38. Safe Attachment Metadata

Operational metadata may include:

```text
id
filename
content_type
size
```

and only an already-approved temporary/private URL if current implementation supports it.

No permanent public URLs.

---

# 39. Customer Ownership Boundary

Preserve:

```text
GET /api/v1/me/enquiries
GET /api/v1/me/enquiries/{enquiry}
```

as Customer-owned retrieval.

Customer A → Customer B:

```text
404 masked
```

Customer resources must never include:

```text
staff_internal_notes
audit internals
operational permissions
```

---

# 40. Anonymous Boundary

Anonymous Enquiry creation remains allowed.

Anonymous:

```text
user_id = null
```

must remain visible in the operational queue.

Do not require a Customer account.

Do not auto-link an old anonymous Enquiry to a Customer merely because the email later matches.

Email is contact, not authentication.

---

# 41. Anonymous Retrieval Remains Unsupported

Do NOT create anonymous:

```text
GET /enquiries/{id}
```

access.

Knowing:

```text
enq_...
email
phone
```

is not authorization.

Upload capability is upload-only.

It must not become a read/status token.

---

# 42. Product Association Boundary

At creation, an Enquiry may reference any qualifying public Product, regardless of product type.

Do not incorrectly apply the Request rule:

```text
MADE_TO_ORDER only
```

to Enquiries.

Request and Enquiry product semantics differ intentionally.

---

# 43. Order Association Boundary

Authenticated Customer may reference only an Order they own according to existing ENQ-001 rules.

Anonymous Order linking remains rejected unless an explicit scoped mechanism exists.

Phase 11.10 must not weaken this.

Operational staff read of an already-linked Order does not imply unrestricted Order browsing outside normal Order authorization.

---

# 44. No Enquiry → Request Conversion

Do not add:

```text
POST /enquiries/{enquiry}/convert-to-request
request_id
converted_request_id
```

or equivalent.

The domains remain separate.

---

# 45. No Enquiry → Order Conversion

Do not add:

```text
POST /enquiries/{enquiry}/create-order
```

or automatic Order creation.

No Payment.

No inventory reservation.

No delivery workflow.

---

# 46. No Messaging Thread

Do NOT implement:

```text
replies
messages[]
chat
conversation
email send
SMS
WhatsApp
push
customer-response thread
```

Phase 11.10 is not a support-ticketing system.

External communication remains separate/future.

---

# 47. No Assignment Workflow

Do not add:

```text
assigned_staff_id
assignee
owner
team
queue_owner
```

Staff operational handling remains permission-based.

---

# 48. No Priority / SLA

Do not add:

```text
priority
urgent
severity
SLA
due_at
escalated
```

No such V1 contract exists.

---

# 49. Permission Separation

Verify:

```text
enquiries.view
→ ENQ-004
→ ENQ-005

enquiries.manage
→ ENQ-006
```

An actor with only:

```text
enquiries.view
```

must not close an Enquiry.

Use central authorization.

Do not hard-code role bypasses.

---

# 50. Admin Access

Admin remains explicit-permission based.

Do not implement:

```php
if ADMIN => bypass all policies
```

Use:

```text
PermissionCatalog
Authorization
enquiries.view
enquiries.manage
```

---

# 51. Private Caching

Operational Enquiry responses contain PII.

Preserve:

```http
Cache-Control: private, no-store
Vary: Authorization
```

plus existing Cookie variation where needed.

Never CDN-cache Enquiries publicly.

---

# 52. Required ENQ-004 Authorization Tests

Cover:

```text
anonymous → 401
Customer → 403

Staff with enquiries.view → 200
Admin with enquiries.view → 200

authenticated actor without enquiries.view → 403
```

---

# 53. Required ENQ-005 Authorization Tests

Cover:

```text
anonymous → 401
Customer → 403
Staff with enquiries.view → 200
Admin with enquiries.view → 200
unknown enq_... → 404
malformed identifier → canonical behavior
```

---

# 54. Required ENQ-006 Authorization Tests

Cover:

```text
anonymous → 401
Customer → 403

Staff with enquiries.view only → 403

Staff with enquiries.manage → allowed
Admin with enquiries.manage → allowed
```

Do not permit mutation merely because actor can view.

---

# 55. Required Filter Tests

ENQ-004 must cover the actual implemented frozen filters, including as applicable:

```text
search
enquiry_status
category
product_id
order_id
created_from
created_to
pagination
```

If `sort`/`sort_direction` are currently frozen and implemented, cover them too.

Do not add them just because an older doc mentions them.

Test combined filters.

---

# 56. Search Coverage

Where currently supported, verify search matches:

```text
name
email
phone
subject
message
enquiry reference
order reference
```

and remains deterministic/paginated.

Do not leak unrelated User data.

---

# 57. Strict Query Tests

Reject unknown query fields such as:

```text
status
customer_id
assigned_to
priority
pageSize
sortBy
```

according to current strict-query rules.

---

# 58. Required Representation Tests

Operational detail must verify approved safe fields and absence of:

```text
raw DB IDs
Clerk IDs
credentials
permissions
storage keys
capability digests
payment secrets
full Order internals
```

Use explicit negative assertions.

---

# 59. Customer Internal-Note Privacy Test

Create Enquiry.

Add Staff internal notes through the operational path.

Then fetch through:

```text
ENQ-002 / ENQ-003
```

and prove:

```text
staff_internal_notes
```

is absent.

Mandatory regression.

---

# 60. Intake Immutability Tests

Attempt to mutate:

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
```

through ENQ-006 or any unintended route.

Prove rejection.

Original submission must remain unchanged.

---

# 61. No-Side-Effect Tests

Closing/updating internal notes must produce zero:

```text
Request
Order
OrderItem
Payment
Delivery
ProductStock
reserved_quantity
quote
```

creation/mutation.

This protects domain separation.

---

# 62. Status Behavior Tests

At minimum prove:

```text
OPEN → CLOSED
allowed

CLOSED → close again
existing idempotent behavior
```

If runtime supports reopen, test its exact approved behavior.

If runtime does NOT support reopen:

```text
do not add it
```

and document CLOSED terminal for current implementation.

---

# 63. MariaDB Concurrency Gate

Run real MariaDB concurrency tests against only:

```text
furnitureapp_test_disposable
```

Reuse existing forked-worker infrastructure.

Prove:

```text
simultaneous close
→ one committed transition
→ one audit
→ final CLOSED
```

Do not claim concurrency closure from SQLite alone.

---

# 64. Group J Regression Suite

Run relevant Group J Enquiry suites covering:

```text
ENQ-001 creation
validation
contact rules
product association
Order ownership/masking
customer ownership
attachments
operational read
operational close
audit
MariaDB concurrency
```

Phase 11.10 must not regress Group J closure.

---

# 65. OpenAPI

Verify ENQ-004/005/006 agree with runtime for:

```text
paths
methods
security
filters
pagination
operational resource
status enum
close body if any
staff_internal_notes if applicable
401
403
404
409
422
private response semantics
```

Also verify ENQ-007 remains:

```text
multipart/form-data
field = attachment
```

because Phase 11.9 already corrected the equivalent REQ-007 mismatch and Enquiry must remain consistent.

Do not broaden the API.

---

# 66. Reopen Documentation Reconciliation

This is a specific Phase 11.10 review requirement.

Search all authoritative docs for:

```text
reopen
CLOSED→OPEN
optional reopen
```

Compare against current runtime.

If reopen is not implemented, reconcile misleading wording so the documentation does not falsely advertise a capability.

Do not silently add implementation to satisfy stale prose.

Prefer:

```text
runtime-approved behavior
→ docs corrected
```

over:

```text
ambiguous old docs
→ new functionality invented
```

Record the decision.

---

# 67. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

Record that Group K reuses Group J:

```text
ENQ-004
ENQ-005
ENQ-006
```

and no `/admin/enquiries` aliases exist.

Document:

```text
Enquiry and Request remain separate.
Enquiry intake is immutable.
Internal notes remain private.
No Order/Payment/Inventory side effects.
Actual reopen policy explicitly resolved.
```

Use next repository-consistent ADR identifier.

---

# 68. Schema

Expected:

```text
schema changes = NONE
```

Do not add:

```text
ticket tables
conversation tables
assignment columns
priority columns
enquiry history table
CRM tables
```

If a genuine schema mismatch appears, report it before inventing new state.

---

# 69. Dependencies

Expected:

```text
new dependencies = NONE
```

Do not add:

```text
support desk package
workflow engine
CRM package
search engine
queue package
```

---

# 70. Code Quality

Follow project standards:

```text
thin controllers
strict FormRequests
validated() only
explicit resources
central authorization
service/domain boundaries
short transactions
enums/constants
cognitive complexity <= 15
<= 3 returns where practical
```

Do not refactor unrelated Group J code.

---

# 71. Verification

Run canonical equivalents:

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
Enquiry operational API tests
Enquiry authorization tests
Enquiry filter/search tests
Enquiry privacy tests
Enquiry attachment regressions
Enquiry audit tests
Group J Enquiry suites
MariaDB Enquiry concurrency test
OpenAPI Enquiry contract tests
```

---

# 72. Git Operations

After all verification passes:

1. read root `git-workflow-and-versioning` in .agents/skills;
2. follow it exactly;
3. stage only Phase 11.10 work;
4. commit according to skill conventions;
5. push only if permitted by that workflow.

Do not stage unrelated changes.

---

# 73. Completion Report

Return:

```text
Phase 11.10 status:
PASS / BLOCKED

Existing Group J implementation reused:
YES / NO

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

enquiries.view:
PASS / FAIL

enquiries.manage:
PASS / FAIL

Customer operational queue access:
REJECTED / FAIL

Anonymous operational read:
REJECTED / FAIL

Customer internal-note exposure:
NO / FAIL

Subject/message immutability:
PASS / FAIL

Contact snapshot preservation:
PASS / FAIL

Request/Enquiry separation:
PASS / FAIL

Enquiry-to-Order behavior added:
NO

Enquiry-to-Request behavior added:
NO

Inventory/payment/quote behavior added:
NO

Attachment privacy:
PASS / FAIL

Product association behavior:
PASS / FAIL

Order association behavior:
PASS / FAIL

Reopen policy resolved:
<CLOSED terminal / reopen already implemented>

Reopen endpoint added:
NO unless already frozen/current

Audit atomicity:
PASS / FAIL

Concurrent close:
PASS / FAIL

Exactly-one real close audit:
PASS / FAIL

ENQ-004 filters:
PASS / FAIL

Pagination:
PASS / FAIL

Deterministic ordering:
PASS / FAIL

Full PHPUnit:
<x> passed, <y> skipped

MariaDB Enquiry concurrency:
<x> passed, <assertions>

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

# 74. STOP Condition

Phase 11.10 is PASS only when:

- ENQ-004/005/006 remain canonical;
- Group J implementation is reused;
- `enquiries.view` and `enquiries.manage` remain separated;
- Enquiry subject/message/contact remain immutable history;
- `staff_internal_notes` never leaks to Customers;
- Enquiry remains distinct from Request;
- no Enquiry-to-Request or Enquiry-to-Order workflow is added;
- no Payment, inventory, quote, messaging-thread, assignment, priority, or CRM behavior is introduced;
- private attachments remain parent-scoped;
- Customer ownership and anonymous-read boundaries remain intact;
- the actual reopen policy is explicitly reconciled;
- MariaDB concurrent-close evidence passes;
- exactly one real audited `OPEN → CLOSED` occurs under concurrent close;
- full backend verification passes;
- Git operations follow the root `git-workflow-and-versioning` skill.

Then report:

```text
Phase 11.10 — PASS
Phase 11.13 — READY
```

Do not begin Phase 11.13 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Phase 11.10 Closure Record — 2026-10-05

**Status:** PASS. Group K reuses the Group J canonical `ENQ-004`, `ENQ-005`, and `ENQ-006` routes; no Admin/Staff aliases, reopen route, generic enquiry mutation, schema change, or dependency change was introduced.

- **Close contract:** `POST /api/v1/enquiries/{enquiry}/close` is close-only. It accepts optional `staff_internal_notes` only, sets `CLOSED` server-side, rejects unknown and immutable intake fields, and preserves idempotent repeat-close/audit semantics. `CLOSED` is terminal in current V1.
- **Security and privacy:** `enquiries.view` governs operational list/detail and `enquiries.manage` governs close. Customer and anonymous callers cannot access operational endpoints; customer representations omit internal notes. ENQ-004/005/006 use `Cache-Control: private, no-store` and `Vary: Authorization`.
- **Domain isolation:** Regression coverage confirms close has no Request, Order, OrderItem, Payment, Delivery, ProductStock, product, or inventory-reservation side effect. Enquiry product/order links remain read-only historical context.
- **OpenAPI/docs:** ENQ-004 documents the implemented filter allow-list (`search`, `enquiry_status`, `category`, `product_id`, `order_id`, `created_from`, `created_to`, `page`, `per_page`); ENQ-005/006 document private operational access; ENQ-006 documents its strict optional-note body; ENQ-007 retains multipart field `attachment`.
- **Focused PHP:** `152 passed (678 assertions)` across Enquiry schema, validation, resources, creation, attachment, operational, and OpenAPI suites.
- **MariaDB concurrency:** `1 passed (90 assertions)` against guarded `furnitureapp_test_disposable`; ten concurrent-close iterations each ended `CLOSED` with exactly one `OPEN→CLOSED` audit.
- **Full verification:** `1647 passed, 1 skipped (6713 assertions)`; PHPStan passed; Pint formatted dirty PHP successfully; Composer audit found no advisories; the Enquiry route surface remained the seven canonical routes; `git diff --check` passed.

**Phase 11.13:** READY. Do not begin it automatically.
