# Phase 11.1 — Admin Information Architecture

## 1. Objective

Define the authoritative **information architecture for Group K — Admin Backend Operations**.

This phase is an architecture, scope, authorization, and API-surface mapping phase.

It must answer:

```text
What operational areas exist?
Who may access each area?
Which existing API resources serve each area?
Which later Group K phases implement each area?
Which areas are currently deferred?
What data may Staff/Admin see?
What actions may they perform?
What must never be exposed or controlled?
```

Do **not** implement CRUD/business operations in Phase 11.1 unless a tiny documentation/test prerequisite is unavoidable.

---

# 2. Group K Context

Group J is now:

```text
CLOSED
```

The current production strategy remains:

```text
MADE_TO_ORDER / Request-first launch
```

Transactional commerce remains deliberately deferred.

Therefore:

```text
Group H — Payments       DEFERRED
Group I — Order Management DEFERRED
```

and Group K must respect those dependencies.

---

# 3. Group K Roadmap

The current roadmap is:

```text
11.1  Admin information architecture
11.2  Admin authentication
11.3  Product CRUD
11.4  Category CRUD
11.5  Image management
11.6  Inventory management
11.7  Order management
11.8  Customer management
11.9  Request management
11.10 Enquiry management
11.11 Payment visibility
11.12 Delivery management
11.13 Audit visibility
```

Phase 11.1 must formally classify these into:

```text
READY
DEFERRED
DEPENDENCY-BLOCKED
ALREADY-BACKEND-CAPABLE
```

where appropriate.

---

# 4. Current-Scope Group K Classification

Unless current repository evidence contradicts it, establish:

```text
11.1  Admin information architecture   CURRENT

11.2  Admin authentication             READY
11.3  Product CRUD                     READY
11.4  Category CRUD                    READY
11.5  Image management                 READY
11.6  Inventory management             READY

11.7  Order management                 DEFERRED — Group I dependency

11.8  Customer management              READY
11.9  Request management               READY
11.10 Enquiry management               READY

11.11 Payment visibility               DEFERRED — Group H dependency
11.12 Delivery management              DEFERRED — Group G/I transactional dependency

11.13 Audit visibility                 READY
```

Do not reactivate deferred phases during this architecture exercise.

---

# 5. Group K Current-Scope Exit Concept

For the present request-first production scope, Group K should eventually allow Staff/Admin to operate:

```text
catalog
categories
product media
inventory
customers
Furniture Requests
General Enquiries
audit visibility
staff/admin lifecycle
```

without directly using the database.

Current-scope Group K closure does **not** require:

```text
Order operations
Payment visibility
Delivery operations
```

until the prerequisite transactional groups are activated.

Record this explicitly.

---

# 6. Frozen API Contract Remains Authority

Version 1 API contract is frozen.

Do not invent new admin APIs because an admin frontend may eventually want convenient routes.

Use the existing canonical API wherever possible.

Examples:

```text
Requests:
GET /api/v1/requests
GET /api/v1/requests/{request}
PATCH /api/v1/requests/{request}

Enquiries:
GET /api/v1/enquiries
GET /api/v1/enquiries/{enquiry}
POST /api/v1/enquiries/{enquiry}/close

Inventory:
existing INV-* operations

Catalog:
existing CAT-* operational/admin operations
```

Do not create duplicate aliases such as:

```text
/admin/requests
/admin/enquiries
/admin/inventory
/staff/requests
/backoffice/requests
```

unless the frozen contract already contains them.

---

# 7. Administrative Namespace Principle

Not every Admin-consumed resource belongs under:

```text
/api/v1/admin/*
```

The namespace is semantic, not a frontend-navigation convenience.

Use `/admin/*` only where the frozen API already models explicitly administrative resources.

Examples include:

```text
/admin/staff
/admin/audit-logs
/admin/products
```

according to existing V1 definitions.

Operational resources like Requests/Enquiries remain their canonical operational paths.

---

# 8. Avoid Parallel API Universes

Do not create:

```text
/public API
customer API
staff API
admin API
```

as four separate implementations of the same resource.

Authorization and actor-specific serialization should determine access.

---

# 9. Primary Operational Areas

Phase 11.1 should define the following admin information architecture areas.

Recommended conceptual navigation/domain map:

```text
Admin Operations

├── Dashboard / Overview
│
├── Catalog
│   ├── Products
│   ├── Categories
│   ├── Product Media
│   └── Inventory
│
├── Customer Demand
│   ├── Furniture Requests
│   └── Enquiries
│
├── People & Access
│   ├── Customers
│   └── Staff
│
├── Audit
│   └── Audit Logs
│
└── Deferred Commerce
    ├── Orders       [DEFERRED]
    ├── Payments     [DEFERRED]
    └── Deliveries   [DEFERRED]
```

This is an information architecture, not a frontend implementation.

---

# 10. Dashboard / Overview

Phase 11.1 may define a conceptual Admin landing area.

Do **not** create a new dashboard API unless a frozen endpoint already exists.

The future UI may compose existing APIs.

Avoid adding speculative:

```text
GET /admin/dashboard
GET /admin/stats
GET /admin/overview
```

in this phase.

---

# 11. Dashboard Current-Scope Information

A future dashboard may conceptually show:

```text
new Requests
open Enquiries
catalog counts
inventory attention
pending Staff approval
```

but Phase 11.1 should not invent aggregate contracts.

If no aggregate endpoint exists, document:

```text
dashboard metrics are deferred/composed from existing APIs
```

until a real performance need justifies dedicated aggregation.

---

# 12. Catalog Information Area

Map the Catalog area to the existing Catalog API.

Admin/authorized Staff operational product access includes:

```text
CAT-013
GET /api/v1/admin/products

CAT-014
GET /api/v1/admin/products/{product}
```

These operational reads differ from the public catalog.

---

# 13. Operational vs Public Product

Clearly document:

```text
Public Product
≠
Operational Product
```

Public catalog should expose only customer-facing fields.

Operational product context may expose approved internal fields such as:

```text
is_active
is_published
product type
operational inventory context
internal management metadata
```

where authorized.

---

# 14. Product Management Mapping

Phase:

```text
11.3 Product CRUD
```

maps primarily to:

```text
CAT-007..010
CAT-013..014
```

and any existing Product management services.

Do not implement them in 11.1.

---

# 15. Category Management Mapping

Phase:

```text
11.4 Category CRUD
```

maps to:

```text
CAT-011
CAT-012
```

plus existing Category reads.

No new category hierarchy design in 11.1.

---

# 16. Product Media Mapping

Phase:

```text
11.5 Image management
```

must reuse existing Product Media architecture.

Do not confuse:

```text
Product media
```

with:

```text
Request/Enquiry attachments
```

They are separate domains.

---

# 17. Inventory Area

Phase:

```text
11.6 Inventory management
```

maps to existing:

```text
INV-001
INV-002
INV-003
```

and permissions:

```text
inventory.view
inventory.manage
```

where currently defined.

---

# 18. Inventory vs Product Management

Keep permission separation:

```text
products.manage
```

does not automatically mean:

```text
inventory.manage
```

and vice versa.

Catalog content and stock authority are separate responsibilities.

---

# 19. Current Production Inventory Context

Even though initial production is MADE_TO_ORDER-first, inventory APIs already exist.

Do not delete or redesign them.

Phase 11.1 should simply classify Inventory management as:

```text
READY
```

for Group K implementation.

---

# 20. Customer Demand Area

This is currently one of the most important operational areas.

Split clearly:

```text
Furniture Requests
General Enquiries
```

Do not merge them into a generic:

```text
Leads
Tickets
Messages
```

resource at the backend level.

---

# 21. Furniture Request Admin Area

Map to existing:

```text
REQ-004
GET /api/v1/requests

REQ-005
GET /api/v1/requests/{request}

REQ-006
PATCH /api/v1/requests/{request}
```

plus attachment metadata.

---

# 22. Request Permissions

Use:

```text
requests.view
requests.manage
```

Do not create:

```text
admin.requests.*
```

unless already frozen.

---

# 23. Request Operational Semantics

The Request admin area is allowed to support:

```text
queue/list
filters
detail
status lifecycle
staff internal notes
attachment access
contact follow-up context
```

It must not support:

```text
Order conversion
Payment
Inventory reservation
quotation engine
manufacturing workflow
```

unless separately approved later.

---

# 24. Request Status

Current lifecycle remains:

```text
SUBMITTED
→ IN_REVIEW
→ CLOSED
```

with direct:

```text
SUBMITTED → CLOSED
```

allowed.

Do not add admin-specific states.

---

# 25. General Enquiry Admin Area

Map to:

```text
ENQ-004
GET /api/v1/enquiries

ENQ-005
GET /api/v1/enquiries/{enquiry}

ENQ-006
POST /api/v1/enquiries/{enquiry}/close
```

---

# 26. Enquiry Permissions

Use:

```text
enquiries.view
enquiries.manage
```

---

# 27. Enquiry Operational Semantics

Admin/Staff may:

```text
list
filter
search
view
close
read operational context
view authorized internal notes
```

according to existing contract.

Do not add a support ticket/chat system.

---

# 28. Enquiry Status

Remain:

```text
OPEN
CLOSED
```

Do not introduce:

```text
IN_PROGRESS
ASSIGNED
WAITING_FOR_CUSTOMER
RESOLVED
```

in Group K.

---

# 29. People & Access Area

Separate:

```text
Customers
Staff
```

These have fundamentally different management authority.

Do not combine into generic unrestricted:

```text
Users CRUD
```

---

# 30. Customer Information Area

Phase:

```text
11.8 Customer management
```

must start from the existing Admin-only customer/user visibility contract.

Existing endpoints include:

```text
ADM-008
GET /api/v1/users

ADM-009
GET /api/v1/users/{user}
```

with:

```text
users.manage_authorized
```

or exact existing permission.

---

# 31. Customer Management Is Not Generic Mutation

Current contract provides authorized customer visibility.

Do not assume Phase 11.8 automatically means:

```text
edit customer
delete customer
change role
reset password
disable customer
impersonate customer
```

Those actions require explicit contract authority.

Phase 11.1 must mark them as unavailable unless already approved.

---

# 32. Customer Visibility Boundary

Admin Customer detail should be purpose-limited.

Do not expose:

```text
password hashes
Clerk credentials
tokens
security secrets
full unrelated private data
```

---

# 33. Staff Cannot Manage Customers

STAFF must retain:

```text
zero ordinary customer-account control
```

Operational access to a Request/Enquiry does not authorize modifying the Customer account.

---

# 34. Admin Customer Visibility

Admin visibility is broader but still:

```text
authorization
+
purpose limitation
+
field minimization
```

not:

```text
dump entire users table/model
```

---

# 35. Staff Management Area

Phase 11.2 and later staff-management functionality map to:

```text
ADM-001 GET  /admin/staff
ADM-002 GET  /admin/staff/{user}
ADM-003 POST /admin/staff
ADM-004 POST /admin/staff/{user}/approve
ADM-005 POST /admin/staff/{user}/suspend
ADM-006 POST /admin/staff/{user}/reactivate
```

---

# 36. Staff Management Actor

These are Admin-only administrative operations.

STAFF must not manage Staff lifecycle.

---

# 37. Staff Permissions

Existing concepts include:

```text
staff.manage
staff.approve
```

Use exact seeded permission names.

Do not authorize merely via:

```php
$user->role === 'ADMIN'
```

if the existing RBAC model requires permissions.

---

# 38. Staff Lifecycle

Phase 11.1 should record existing lifecycle concepts only.

Do not invent new states.

Inspect exact current model/enum before later implementation.

---

# 39. Staff Creation

`ADM-003` is an invite/create flow.

Do not design it as:

```text
POST /admin/staff
{
  password: ...
}
```

because Clerk owns credentials.

---

# 40. Authentication Boundary

Clerk remains identity/authentication authority.

Laravel remains authority for:

```text
local application User
role
permissions
staff state
business authorization
```

Do not build a second authentication system for Admin.

---

# 41. Admin Authentication vs Admin Information Architecture

Phase:

```text
11.2 Admin authentication
```

should integrate the Admin workspace with existing Clerk/Laravel auth.

Phase 11.1 only defines the boundary.

Do not implement the login UI or Clerk frontend in this phase.

---

# 42. Audit Area

Phase:

```text
11.13 Audit visibility
```

maps to:

```text
ADM-007
GET /api/v1/admin/audit-logs
```

where approved.

Permission:

```text
audit.view
```

---

# 43. Audit Is Read-Only

Admin audit visibility must not permit:

```text
PATCH audit
DELETE audit
edit actor
edit result
```

Audit is historical operational evidence.

---

# 44. Audit Visibility vs Audit Generation

Do not confuse:

```text
audit record creation
```

with:

```text
audit UI/API visibility
```

Audit generation already exists for privileged actions.

11.13 concerns authorized reading.

---

# 45. Audit Filters

Existing contract includes concepts such as:

```text
actor
action
resource_type
resource_id
created_from
created_to
```

Use exact frozen filters later.

Do not redesign them in 11.1.

---

# 46. Deferred Commerce Area

Phase 11.1 must explicitly add an architecture section:

```text
Deferred Commerce
```

containing:

```text
Orders
Payments
Deliveries
```

---

# 47. Phase 11.7 — Order Management

Set:

```text
DEFERRED
```

Reason:

```text
Group I — Order Management is intentionally deferred.
```

Do not implement Admin Order management before Group I is activated and closed.

---

# 48. Existing Order Contract Does Not Mean Current Activation

The frozen V1 contract may already define:

```text
ORD-005..014
```

but contract existence does not require current production activation.

Preserve the contract while deferring Group K operational implementation.

---

# 49. Phase 11.11 — Payment Visibility

Set:

```text
DEFERRED
```

Reason:

```text
Group H — Payments is intentionally deferred.
```

PAY-001/PAY-002/Webhook remain Group H-owned placeholders until activated.

---

# 50. No Payment Admin UI/API Expansion

Do not build:

```text
payment dashboard
payment search
refund tools
payment provider logs
webhook replay
```

during current Group K scope.

---

# 51. Phase 11.12 — Delivery Management

Set:

```text
DEFERRED
```

because operational Delivery depends on transactional Order/fulfilment lifecycle.

Even though a Delivery schema may exist, schema existence is not sufficient justification for an Admin workflow.

---

# 52. Delivery Schema vs Delivery Workflow

Document clearly:

```text
Delivery persistence foundation exists
≠
Delivery management workflow is currently active
```

No speculative:

```text
driver assignment
dispatch
route planning
delivery attempts
GPS
proof of delivery
```

---

# 53. Group K Dependency Map

Create a dependency map similar to:

```text
11.1 Information Architecture
│
├── 11.2 Admin Authentication
│
├── 11.3 Product CRUD
│   ├── 11.5 Image Management
│   └── 11.6 Inventory Management
│
├── 11.4 Category CRUD
│
├── 11.8 Customer Management
│
├── 11.9 Request Management
│
├── 11.10 Enquiry Management
│
└── 11.13 Audit Visibility

DEFERRED:
11.7  ← Group I
11.11 ← Group H
11.12 ← Groups G/I transactional lifecycle
```

---

# 54. Permission Architecture Matrix

Build a canonical matrix for current-scope operational areas.

At minimum include:

```text
Area
Actor
Read permission
Manage permission
Administrative-only?
Audited mutations?
Later Group K phase
```

---

# 55. Suggested Matrix

Conceptually:

```text
Products
Read: products.view / products.manage where approved
Manage: products.manage
Actor: Staff/Admin

Categories
Manage: products.manage
Actor: Staff/Admin

Inventory
Read: inventory.view
Manage: inventory.manage
Actor: Staff/Admin

Requests
Read: requests.view
Manage: requests.manage
Actor: Staff/Admin

Enquiries
Read: enquiries.view
Manage: enquiries.manage
Actor: Staff/Admin

Customers
Read: users.manage_authorized
Actor: Admin

Staff
Manage: staff.manage
Approve: staff.approve
Actor: Admin

Audit
Read: audit.view
Actor: Admin
```

Verify exact permission names against code before documenting.

---

# 56. Never Guess Permission Names

Inspect:

```text
PermissionName
PermissionCatalog
RbacSeeder
Authorization
policies/middleware
```

Use actual constants.

Do not document conceptual names as implementation truth if they differ.

---

# 57. Role Architecture

Closed roles remain:

```text
CUSTOMER
STAFF
ADMIN
```

Do not create:

```text
SUPER_ADMIN
MANAGER
EDITOR
SUPPORT
INVENTORY_MANAGER
```

during Phase 11.1.

Fine-grained authority comes from permissions, not new roles.

---

# 58. Default Deny

Architecture must preserve:

```text
authenticated identity
+
valid role
+
required permission
+
resource context
+
business state
```

where applicable.

Role alone is never sufficient.

---

# 59. Admin Is Not Wildcard

Do not document Admin as:

```text
can do everything
```

Instead:

```text
highest approved role
with explicit permissions and administrative scope
```

---

# 60. Staff Is Operational

STAFF should be modeled as:

```text
operates business resources
```

not:

```text
administers identities/accounts
```

---

# 61. Customer Data Access

Operational Staff may see customer/contact information only where necessary for:

```text
Request handling
Enquiry handling
future fulfilment
```

not as generic Customer browsing.

---

# 62. ADM-008/009 Admin Boundary

Customer list/detail:

```text
GET /api/v1/users
GET /api/v1/users/{user}
```

are Admin-only according to current contract.

Do not expose them to STAFF.

---

# 63. Canonical Resource Boundaries

Create an architecture table mapping:

```text
Information area
Resource
Canonical API
Actor
Permission
Representation
Phase
Status
```

---

# 64. Example Architecture Table

The final document should include something conceptually like:

```text
Catalog Products
→ Product
→ CAT-013/014 + CAT-007..010
→ Staff/Admin
→ products.view/products.manage
→ operational Product
→ 11.3
→ READY

Categories
→ Category
→ CAT-011/012
→ Staff/Admin
→ products.manage
→ operational Category
→ 11.4
→ READY

Inventory
→ ProductStock/Inventory
→ INV-001..003
→ Staff/Admin
→ inventory.view/manage
→ operational Inventory
→ 11.6
→ READY

Requests
→ FurnitureRequest
→ REQ-004..006
→ Staff/Admin
→ requests.view/manage
→ operational Request
→ 11.9
→ READY

Enquiries
→ Enquiry
→ ENQ-004..006
→ Staff/Admin
→ enquiries.view/manage
→ operational Enquiry
→ 11.10
→ READY

Customers
→ User/CustomerProfile
→ ADM-008/009
→ Admin
→ users.manage_authorized
→ administrative Customer
→ 11.8
→ READY

Staff
→ User/StaffProfile
→ ADM-001..006
→ Admin
→ staff.manage/staff.approve
→ administrative Staff
→ 11.2 or relevant lifecycle implementation
→ READY

Audit
→ AuditLog
→ ADM-007
→ Admin
→ audit.view
→ administrative Audit
→ 11.13
→ READY
```

Use actual current API/permissions.

---

# 65. Representation Architecture

Explicitly distinguish:

```text
PUBLIC
CUSTOMER-PRIVATE
STAFF-OPERATIONAL
ADMINISTRATIVE
INTERNAL
```

where current docs already use those concepts.

---

# 66. Public Catalog

May be publicly cacheable.

Operational/Admin resources must never inherit public-cache behavior.

---

# 67. Private Operational Caching

Admin/Staff responses should follow:

```text
Cache-Control: private, no-store
```

where currently defined.

Do not put operational resources behind public CDN caches.

---

# 68. Field-Level Before Serialization

The architecture must state:

```text
authorization
→ choose actor-specific representation
→ serialize only permitted fields
```

Never:

```text
Model::toArray()
→ filter afterward
```

---

# 69. Sensitive Fields Global Deny List

Admin architecture must never expose:

```text
password
password hash
Clerk secret/token
session token
API secret
upload capability
payment provider secret
raw card data
database credentials
private storage key
```

even to Admin unless a very specific future security workflow requires it.

---

# 70. Auditability Matrix

For every management area, classify mutations:

```text
audited
not audited
future
```

based on existing rules.

Examples likely mandatory:

```text
Staff approval/suspend/reactivate
inventory adjustment
Request status change
Enquiry status change
privileged catalog changes
```

Verify source before finalizing.

---

# 71. No Generic Admin PATCH

Preserve the existing convention prohibiting broad generic mutation routes.

Do not design:

```text
PATCH /admin/users/{id}
PATCH /admin/orders/{id}
PATCH /admin/everything/{id}
```

for business-state mutations.

Use controlled actions where frozen.

---

# 72. Explicit Actions

Examples:

```text
approve Staff
suspend Staff
reactivate Staff
adjust Inventory
close Enquiry
transition Request
```

remain explicit operations.

---

# 73. Current-Scope Navigation vs API

Phase 11.1 may define conceptual backend/admin navigation, but it must not assume each navigation item has a unique endpoint.

Example:

```text
Admin UI: Requests
```

may consume:

```text
GET /requests
```

rather than:

```text
GET /admin/requests
```

---

# 74. Admin Frontend Is Not Being Built

Do not create:

```text
React pages
MUI components
Flutter screens
Next.js routes
admin navigation UI
```

in 11.1.

This is backend information architecture.

---

# 75. Future Admin UI Consumer

Document the backend so a later Admin UI can understand:

```text
sections
resources
permissions
actions
deferred areas
```

without hard-coding business assumptions.

---

# 76. No New Admin Application Architecture

Do not decide in this phase whether Admin will be:

```text
same Next.js app
separate Next.js app
subdomain
desktop app
```

unless already decided elsewhere.

Backend information architecture should remain client-independent.

---

# 77. Product vs Inventory Boundary

Document explicitly:

```text
Product CRUD changes catalog description/merchandising
Inventory changes stock authority
```

They are not the same management section semantically even if UI later links them.

---

# 78. Request vs Enquiry Boundary

Document:

```text
Request
= customer asking for furniture to be made

Enquiry
= general private business communication
```

Keep separate operational queues.

---

# 79. Customer vs Staff Boundary

Document:

```text
Customer
= business customer identity/profile

Staff
= privileged operational identity requiring administrative lifecycle
```

Do not manage both with the same actions.

---

# 80. Audit vs Notification Boundary

Audit:

```text
immutable accountability/history
```

Notification:

```text
communication/read-state
```

Do not treat Notification as audit evidence.

---

# 81. Group K Phase Dependency Table

Create/update:

```text
phases/group-K-phases.md
```

if the repository follows per-group tracking documents.

Include:

```text
phase
purpose
dependencies
status
deferred reason
```

---

# 82. Deferred Phase Documentation

Record explicitly:

```text
11.7 DEFERRED
Dependency: Group I

11.11 DEFERRED
Dependency: Group H

11.12 DEFERRED
Dependency: Groups G/I operational transaction lifecycle
```

Do not merely say:

```text
not started
```

---

# 83. Current-Scope Group K Closure Rule

Document a provisional current-scope closure definition:

```text
Group K current-scope may close when:
11.1, 11.2, 11.3, 11.4, 11.5, 11.6,
11.8, 11.9, 11.10, 11.13
are complete

AND

11.7, 11.11, 11.12
remain formally deferred with documented prerequisites.
```

This is not the same as declaring the deferred capabilities permanently unnecessary.

---

# 84. Reactivation Rule

Deferred Group K phases may resume only when their owning prerequisite groups are explicitly reactivated.

Examples:

```text
Group H activated/closed
→ 11.11 eligible

Group I activated/closed
→ 11.7 eligible

transactional delivery/Order lifecycle active
→ 11.12 eligible
```

---

# 85. No Dependency Backflow

Do not make current-scope phases depend on:

```text
Orders
Payments
Deliveries
```

unless their existing domain contracts genuinely require them.

Request/Enquiry administration must remain independent.

---

# 86. Current Production Priority

Record that the highest-priority admin operational areas for the current launch are likely:

```text
Products
Categories
Product Media
Requests
Enquiries
Staff access
Audit
```

Inventory remains available where operationally relevant.

Do not turn this into frontend prioritization yet.

---

# 87. Staff vs Admin Access Table

Create a simple role/access table.

For example:

```text
Area              CUSTOMER   STAFF             ADMIN
Products public   Read       Read              Read
Product ops       No         Permission-based  Permission-based
Inventory         No         Permission-based  Permission-based
Requests ops      No         Permission-based  Permission-based
Enquiries ops     No         Permission-based  Permission-based
Customers admin   Own only   No                Authorized
Staff lifecycle   No         No                Authorized
Audit logs        No         Normally no       Authorized
```

Use actual contract details.

---

# 88. Admin Information Architecture Deliverable

Create/update a repository document that becomes the Group K architectural reference.

Prefer:

```text
phases/group-K-phases.md
```

and/or:

```text
docs/admin-information-architecture.md
```

only if repository documentation conventions justify a separate file.

Avoid unnecessary documentation duplication.

---

# 89. Recommended Sections

The deliverable should include:

```text
1. Purpose
2. Current production scope
3. Deferred transactional scope
4. Operational information areas
5. Resource/API mapping
6. Actor/permission matrix
7. Representation/privacy model
8. Audit model
9. Dependency map
10. Phase readiness map
11. Current-scope Group K exit condition
12. Explicit non-goals
```

---

# 90. No New Schema

Expected:

```text
Schema changes = NONE
```

This phase should not create database tables.

---

# 91. No New Runtime Services

Expected:

```text
Runtime business logic changes = NONE
```

unless correcting documentation drift only.

---

# 92. No New Endpoint

Expected:

```text
API surface changes = NONE
```

Do not add dashboard/navigation-specific endpoints.

---

# 93. No OpenAPI Expansion

Expected:

```text
OpenAPI changes = NONE
```

unless a genuine already-frozen documentation inconsistency is discovered.

If discovered:

record it.

Do not silently alter contract.

---

# 94. No New Permission

Expected:

```text
RBAC permission changes = NONE
```

Use existing permission catalog.

---

# 95. Permission Verification

Inspect:

```text
PermissionName
PermissionCatalog
RbacSeeder
```

and produce an evidence-backed map.

Do not rely exclusively on prose docs if implementation has evolved.

---

# 96. Endpoint Verification

Inspect actual:

```text
routes/api.php
php artisan route:list
```

for relevant current-scope operational routes.

Classify each as:

```text
ACTIVE
GATED
STUB
MISSING
DEFERRED
```

Do not assume frozen endpoint existence equals implementation status.

---

# 97. Group J Reuse

Request and Enquiry operational APIs are already complete.

Phase 11.1 should mark:

```text
backend capability exists
```

for 11.9/11.10 prerequisites.

Later Group K phases should focus on whatever admin/backend operational gap remains rather than duplicating Group J.

---

# 98. Important 11.9 Interpretation

Because Group J already implemented:

```text
Request operational list
detail
status management
internal notes
audit
```

Phase 11.9 may turn out primarily to be:

```text
admin-specific completion/review of Request management
```

rather than rebuilding Request backend APIs.

Document this possibility.

Do not prejudge exact 11.9 implementation until that phase is reviewed.

---

# 99. Important 11.10 Interpretation

Same for Enquiries.

Group J already provides:

```text
operational queue
detail
close
audit
```

Phase 11.10 should reuse that work.

---

# 100. Group K Must Not Duplicate Group J

Explicitly prohibit:

```text
second Request service
second Enquiry service
admin-specific Request tables
admin-specific Enquiry statuses
```

---

# 101. Group E Reuse

Catalog and inventory foundations already exist.

Group K should reuse them.

Do not recreate Product/Category/Inventory domain logic.

---

# 102. Admin API vs Admin UI

Architecture should distinguish:

```text
Backend Admin Operations
```

from:

```text
future Admin User Interface
```

Phase Group K is backend operations.

Do not leak visual-design decisions into backend contracts.

---

# 103. Error Contract

All Admin/operational endpoints continue using existing canonical errors:

```text
401 authentication
403 authorization
404 resource not found / masked where applicable
409 state conflict
422 validation
429 rate limit
```

No admin-specific error envelope.

---

# 104. Pagination

All growing admin/operational collections reuse:

```text
page
per_page
meta.pagination
```

No admin-specific pagination convention.

---

# 105. Filtering

Each resource keeps its frozen allow-list.

Do not create one generic admin filter DSL.

---

# 106. Search

Operational search remains resource-specific and authorization-scoped.

Do not implement global cross-domain search.

---

# 107. No Admin Search Everything

Do not add:

```text
GET /admin/search?q=
```

in Phase 11.1.

---

# 108. Security Classification

Mark operational data such as:

```text
Customer contact
Requests
Enquiries
Inventory internals
Staff data
Audit logs
```

as private.

---

# 109. Cache Discipline

Private operational/admin resources:

```text
private, no-store
```

Public catalog remains independently cacheable.

---

# 110. Logging Discipline

Admin architecture must prohibit logging:

```text
credentials
bearer tokens
Clerk secrets
private attachment capabilities
passwords
payment secrets
```

---

# 111. Impersonation

Not part of current V1 unless explicitly approved elsewhere.

Do not add:

```text
login as customer
impersonate user
```

to Admin information architecture.

---

# 112. Account Deletion

Do not assume Admin can delete customers.

Historical commerce/request/enquiry integrity may prevent or restrict deletion.

Leave unavailable unless existing contract explicitly defines it.

---

# 113. Role Editing

Do not introduce generic:

```text
PATCH /users/{id} {role:...}
```

Use existing Staff lifecycle/approval model.

---

# 114. Permission Editing UI

Do not introduce arbitrary permission assignment endpoints unless already frozen.

Current RBAC seed/catalog remains authority.

---

# 115. No Wildcard Permission

Preserve wildcard-disabled security model.

---

# 116. Staff Self-Approval

Remain forbidden.

---

# 117. Audit Privileged Actions

Architecture should flag the following as auditable where current contract requires:

```text
Staff approval
Staff suspension/reactivation
Inventory adjustments
Request status changes
Enquiry status changes
privileged catalog modifications
```

Verify implementation/docs.

---

# 118. Phase 11.1 Tests

This phase likely needs architecture consistency tests, not business feature tests.

Add tests only if they meaningfully protect architectural assumptions.

Potential examples:

```text
GroupKInformationArchitectureContractTest
```

could verify:

```text
expected current-scope endpoints exist
deferred transactional endpoints are not accidentally activated by Group K
permissions exist in PermissionCatalog
no duplicate admin Request/Enquiry routes exist
```

Do not create brittle documentation-string tests.

---

# 119. Route Contract Regression

A useful test may verify absence of duplicate aliases such as:

```text
/admin/requests
/admin/enquiries
/staff/requests
/staff/enquiries
```

if current contract explicitly rejects those aliases.

---

# 120. Permission Catalog Regression

Verify required Group K current-scope permissions exist.

Do not add new ones unless missing from frozen contract.

---

# 121. Documentation Verification

Ensure the information architecture agrees with:

```text
AGENTS.md
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
PermissionCatalog
routes
```

---

# 122. Resolve Contradictions Carefully

If docs disagree with runtime/frozen contract:

do not silently rewrite.

Classify:

```text
implementation bug
documentation drift
frozen-contract inconsistency
intentional deferral
```

and report it.

---

# 123. Known Current Scope Decision

Explicitly record:

```text
Groups H and I remain deferred.

Therefore Group K:
11.7 deferred
11.11 deferred
11.12 deferred
```

This is a scope decision, not an implementation failure.

---

# 124. Group K Status After Phase 11.1

Expected:

```text
11.1 PASS

11.2 READY
11.3 READY
11.4 READY
11.5 READY
11.6 READY

11.7 DEFERRED

11.8 READY
11.9 READY
11.10 READY

11.11 DEFERRED
11.12 DEFERRED

11.13 READY
```

---

# 125. ADR

Add the next available ADR if repository convention warrants it.

Concept:

```text
Group K Admin Information Architecture and Current-Scope Deferrals
```

Do not guess ADR number.

---

# 126. ADR Should Record

At minimum:

```text
Group K purpose
current request-only production scope
operational areas
canonical API reuse
Admin namespace rules
Staff vs Admin authority
Customer-account protection
permission model
Request/Enquiry reuse
Catalog/Inventory reuse
deferred 11.7/11.11/11.12
dependency reactivation rules
current-scope closure rule
```

---

# 127. Files Expected to Change

Likely:

```text
phases/group-K-phases.md
docs/decisions.md
```

Possibly:

```text
AGENTS.md
```

only if adding explicit deferred annotations is appropriate and does not distort the roadmap.

No runtime PHP change should normally be necessary.

---

# 128. AGENTS.md Update

If updating `AGENTS.md`, keep the roadmap numbering unchanged.

Do not remove:

```text
11.7
11.11
11.12
```

Instead annotate conceptually:

```text
Deferred until prerequisite group is activated
```

if project documentation style permits.

---

# 129. No Renumbering

Do not renumber Group K because three phases are deferred.

Future activation should use the original numbers.

---

# 130. Quality Verification

Run at least:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

If only docs/tests changed, still run the project's required verification baseline.

---

# 131. OpenAPI Verification

Parse/test OpenAPI to ensure Phase 11.1 did not accidentally change the frozen API.

---

# 132. Expected Verification Outcome

Expected:

```text
schema = unchanged
runtime behavior = unchanged
OpenAPI behavior = unchanged
dependencies = unchanged
frontend = unchanged
```

---

# 133. Completion Report — Phase Status

Return:

## Phase 11.1 Status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 134. Completion Report — Information Areas

Report the final areas:

```text
Catalog
Categories
Product Media
Inventory
Requests
Enquiries
Customers
Staff
Audit
Deferred Orders
Deferred Payments
Deferred Delivery
```

---

# 135. Completion Report — API Mapping

For each current-scope area report:

```text
canonical endpoint IDs
routes
permissions
actors
representation type
```

---

# 136. Completion Report — Permission Matrix

Report the evidence-backed matrix from actual code.

---

# 137. Completion Report — Deferred Phases

Explicitly state:

```text
11.7 Order management
DEFERRED
Dependency: Group I

11.11 Payment visibility
DEFERRED
Dependency: Group H

11.12 Delivery management
DEFERRED
Dependency: transactional Group G/I lifecycle
```

---

# 138. Completion Report — Current-Scope Closure Rule

State exactly what Group K must complete now versus later.

---

# 139. Completion Report — API Changes

Expected:

```text
NONE
```

---

# 140. Completion Report — Schema Changes

Expected:

```text
NONE
```

---

# 141. Completion Report — Dependencies

Expected:

```text
NONE
```

---

# 142. Completion Report — Frontend

Expected:

```text
NONE
```

---

# 143. Completion Report — Tests

Report:

```text
architecture/route tests added
full suite result
```

---

# 144. Completion Report — Quality

Report:

```text
PHPUnit
PHPStan
Pint
Composer audit
git diff --check
route:list
OpenAPI parse
```

---

# 145. Completion Report — Next Phase

If Phase 11.1 passes:

```text
Phase 11.2 — Admin authentication READY
```

Do not implement 11.2 automatically.

---

# 146. Definition of Done

Phase 11.1 is complete when:

- Group K's purpose is documented;
- current request-only production scope is explicit;
- Group H remains deferred;
- Group I remains deferred;
- 11.7 is formally marked deferred;
- 11.11 is formally marked deferred;
- 11.12 is formally marked deferred;
- their reactivation dependencies are documented;
- current-scope Group K closure rule is documented;
- Admin operational information areas are defined;
- canonical resources are mapped to each area;
- canonical API paths are mapped;
- duplicate admin APIs are explicitly avoided;
- Staff vs Admin responsibilities are documented;
- Customer account protection is documented;
- existing RBAC permissions are mapped from actual code;
- no new role is introduced;
- no wildcard Admin authority is introduced;
- Product vs Inventory responsibilities remain separated;
- Request vs Enquiry remain separate workflows;
- Customer vs Staff management remains separated;
- Request management reuses Group J;
- Enquiry management reuses Group J;
- Catalog/Inventory management reuses Group E;
- Audit visibility is read-only;
- private operational representations are distinguished from public resources;
- field-level serialization rules are documented;
- cache/privacy requirements are documented;
- audit expectations are mapped;
- no new schema is introduced;
- no new endpoints are introduced;
- no new permission is introduced unless an existing contract defect is proven;
- no OpenAPI contract change is introduced;
- no frontend implementation occurs;
- documentation agrees with current routes and permission catalog;
- tests/quality gates remain green.

---

# 147. STOP Condition

STOP when the repository has an authoritative Group K information architecture showing:

```text
CURRENT SCOPE
- Staff/Admin access
- Products
- Categories
- Product Media
- Inventory
- Customers
- Furniture Requests
- Enquiries
- Audit

DEFERRED
- Orders
- Payments
- Deliveries

DEPENDENCIES
- 11.7  ← Group I
- 11.11 ← Group H
- 11.12 ← transactional Group G/I lifecycle
```

and when the agent can report:

```text
Phase 11.1 PASS

11.2 READY
11.3 READY
11.4 READY
11.5 READY
11.6 READY
11.7 DEFERRED
11.8 READY
11.9 READY
11.10 READY
11.11 DEFERRED
11.12 DEFERRED
11.13 READY
```

Do not begin Phase 11.2 automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.

---

# 148. Phase 11.1 Outcome — Authoritative Group K Information Architecture

## Purpose and Current Scope

Group K defines backend operational access for the request-first production release. It reuses the frozen V1 API and existing Group E and Group J domain services; it does not create an Admin-specific parallel API, schema, workflow, or frontend.

Current scope is:

```text
Staff/Admin access
Products, categories, product media, and inventory
Customers and Staff lifecycle
Furniture Requests and Enquiries
Audit visibility
```

`CUSTOMER`, `STAFF`, and `ADMIN` remain the only roles. Authorization remains deny-by-default: authenticated identity, role, explicit permission, resource context, ownership where applicable, and business state are all required. `ADMIN` is a seeded explicit permission set, not a wildcard authority.

## Canonical Resource Map

| Information area | Canonical resource and route(s) | Actor and permission | Representation and implementation state | Group K phase |
| --- | --- | --- | --- | --- |
| Products | `CAT-013/014`: `GET /api/v1/admin/products`, `GET /api/v1/admin/products/{product}`; writes: `POST/PATCH /api/v1/products` | Staff/Admin, `products.manage` | Administrative Product for reads; public Product remains separate. Registered routes; Product operational reads/writes are placeholders. | 11.3 READY |
| Categories | Public reads: `GET /api/v1/categories`; writes: `POST/PATCH /api/v1/categories` | Staff/Admin, `products.manage` | Public Category read and operational management are separate concerns. Write routes are placeholders. | 11.4 READY |
| Product media | `POST /api/v1/products/{product}/images`; Product variants use the same product management boundary | Staff/Admin, `products.manage` | Product media is distinct from Request/Enquiry attachments. Route is a placeholder. | 11.5 READY |
| Inventory | `INV-001..003`: `GET /api/v1/inventory`, `GET /api/v1/inventory/{inventory}`, `POST /api/v1/inventory/{inventory}/adjust` | Staff/Admin, `inventory.view` / `inventory.manage` | Private operational Inventory; quantity adjustment is an explicit, idempotent, audited action. Implemented. | 11.6 READY |
| Furniture Requests | `REQ-004..006`: `GET /api/v1/requests`, `GET /api/v1/requests/{request}`, `PATCH /api/v1/requests/{request}` | Staff/Admin, `requests.view` / `requests.manage` | Private Staff-operational Request. Group J implementation is reused; no Order conversion, payment, or reservation. | 11.9 READY / backend-capable |
| Enquiries | `ENQ-004..006`: `GET /api/v1/enquiries`, `GET /api/v1/enquiries/{enquiry}`, `POST /api/v1/enquiries/{enquiry}/close` | Staff/Admin, `enquiries.view` / `enquiries.manage` | Private Staff-operational Enquiry. Group J implementation is reused; separate from Requests. | 11.10 READY / backend-capable |
| Customers | `ADM-008/009`: `GET /api/v1/users`, `GET /api/v1/users/{user}` | Admin, `users.manage_authorized` | Purpose-limited administrative User/Customer view. Registered routes are placeholders. No generic customer mutation. | 11.8 READY |
| Staff lifecycle | `ADM-001..006`: `GET/POST /api/v1/admin/staff`, `GET /api/v1/admin/staff/{user}`, explicit approve/suspend/reactivate actions | Admin, `staff.manage` / `staff.approve` | Administrative Staff representation. Clerk owns credentials; Laravel owns local role, state, and authorization. Registered routes are placeholders. | 11.2 READY |
| Audit | `ADM-007`: `GET /api/v1/admin/audit-logs` | Admin route guard only; see consistency gap below | Private, read-only administrative Audit resource. Registered route is a placeholder. | 11.13 READY |

The Dashboard is conceptual only. It composes existing resources when needed; no `/admin/dashboard`, `/admin/stats`, or aggregate endpoint is part of this phase.

## Actor Boundaries

| Area | CUSTOMER | STAFF | ADMIN |
| --- | --- | --- | --- |
| Public catalog | Read | Read | Read |
| Catalog management | No | `products.manage` | `products.manage` |
| Inventory | No | Explicit inventory permissions | Explicit inventory permissions |
| Requests and Enquiries | Own private history only | Explicit operational permissions | Explicit operational permissions |
| Customer accounts | Own account only | No customer-account administration | Authorized read only through `users.manage_authorized` |
| Staff lifecycle | No | No | Explicit `staff.manage` / `staff.approve` actions |
| Audit visibility | No | No | Route is Admin-only; frozen-contract permission alignment is unresolved |

Staff operational access never grants customer-account ownership, impersonation, credential access, role changes, suspension of customers, or unrestricted profile browsing. Admin customer visibility remains purpose-limited and must not expose passwords, Clerk credentials or tokens, sessions, payment secrets, upload capabilities, private storage keys, or database credentials.

## Representation, Privacy, and Audit Rules

- Public catalog representations remain independently cacheable. Operational and administrative representations use `Cache-Control: private, no-store` and `Vary: Authorization` where implemented/documented.
- Authorization selects the actor-specific representation before serialization. Models must not be serialized wholesale and filtered afterward.
- Public Product, private customer-owned Request/Enquiry, Staff-operational Request/Enquiry/Inventory, administrative User/Staff/Audit, and internal-only data remain separate representations.
- Product merchandising and inventory authority are distinct: `products.manage` does not imply `inventory.manage`.
- Request and Enquiry remain distinct workflows and queues. Group K reuses their Group J services and statuses; it does not add admin-specific tables, states, services, or aliases such as `/admin/requests` or `/admin/enquiries`.
- Privileged mutations are auditable where implemented: inventory adjustment, Request status change, and Enquiry status change are currently recorded. Staff lifecycle and privileged catalog mutation audit obligations remain part of their later implementation phases.
- Audit data is append-only evidence, not notification state. Audit visibility is read-only; no ordinary `PATCH` or `DELETE` audit operation is permitted.

## Deferred Transactional Commerce

| Phase | Area | Status | Reactivation dependency |
| --- | --- | --- | --- |
| 11.7 | Order management | DEFERRED | Group I explicitly reactivated and completed for production scope |
| 11.11 | Payment visibility | DEFERRED | Group H explicitly reactivated and completed for production scope |
| 11.12 | Delivery management | DEFERRED | Transactional Group G/I fulfilment lifecycle explicitly activated |

Frozen Order, Payment, and Delivery contracts remain preserved, but their registered placeholder routes do not activate Group K operations. No order, payment, delivery, refund, provider-log, dispatch, driver, GPS, or proof-of-delivery capability is in current Group K scope.

## Dependency and Readiness Map

```text
11.1 Information architecture        PASS
11.2 Admin authentication             READY
11.3 Product CRUD                    READY
11.4 Category CRUD                   READY
11.5 Image management                READY
11.6 Inventory management            READY
11.7 Order management                DEFERRED <- Group I
11.8 Customer management             READY
11.9 Request management              READY (reuse Group J)
11.10 Enquiry management             READY (reuse Group J)
11.11 Payment visibility             DEFERRED <- Group H
11.12 Delivery management            DEFERRED <- transactional Group G/I lifecycle
11.13 Audit visibility               READY
```

Current-scope Group K may close only after 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.8, 11.9, 11.10, and 11.13 are complete while 11.7, 11.11, and 11.12 remain formally deferred with the dependencies above. This is a scope decision, not removal of the frozen commerce contract.

## Recorded Consistency Gap

The frozen API documentation describes `ADM-007` as an optional, `audit.view`-authorized administrative read. Runtime currently registers `GET /api/v1/admin/audit-logs` behind `clerk.auth` and `admin` middleware only; `audit.view` is absent from `PermissionName`, `PermissionCatalog`, and `RbacSeeder`, and `AdminController::auditLogIndex()` returns the shared 501 placeholder.

Classification: frozen-contract/runtime inconsistency. This phase records the discrepancy only. It does not add a permission, change route middleware, alter OpenAPI, or implement audit visibility. Phase 11.13 must reconcile it through the post-freeze contract-change process if the endpoint is to be exposed.

## Phase 11.1 Non-Goals and Verification Boundary

This phase adds no schema, runtime business logic, endpoints, OpenAPI changes, RBAC permissions, frontend, dashboard API, generic Admin `PATCH`, impersonation, customer deletion, or arbitrary role/permission editing. No architecture test was added because a documentation-string test would be brittle; existing route, permission, and feature tests remain the behavioral evidence.
