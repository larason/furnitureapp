# Phase 1.18 — Define Authorization and Permission Contract

## 1. Purpose

Phase 1.18 is the project's **security-intensive authorization phase**.

Authentication from Phase 1.17 establishes:

> **Who is the caller?**

Authorization establishes:

> **What is that caller allowed to do, to which resource, under which conditions, and in which business state?**

The goal is to design authorization that is:

```text
SECURE
+
PREDICTABLE
+
OPERATIONALLY PRACTICAL
+
CUSTOMER-FRIENDLY
+
LEAST-PRIVILEGE
```

The system must behave like a normal modern e-commerce platform for customers while strongly protecting:

* customer accounts;
* orders;
* payments;
* inventory;
* staff operations;
* administrative functions;
* sensitive data.

The customer must have **broad freedom over their own shopping experience** without gaining administrative privileges.

Staff must have **enough access to perform daily ecommerce operations efficiently**, without receiving customer-account control.

Admins must have the **highest administrative authority**, including staff approval and management, while still operating through controlled, auditable business operations.

---

# 2. Documentation Strategy

Continue using consolidated documents.

Do **not** create permanent files such as:

```text id="p3th1k"
authorization.md
permissions.md
roles.md
access-control.md
```

unless a genuine future need justifies one.

Update:

```text id="y5c9lq"
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

---

# 3. Authoritative Project Paths

Use:

```text id="wqjn2m"
AGENTS.md
docs/VISION.md
```

The old filenames are obsolete.

---

# 4. Important Existing Rules

Preserve all previously approved rules.

Especially:

```text id="p1wz5g"
Anonymous browsing is allowed.
Anonymous requests are allowed.
Anonymous enquiries are allowed.
Checkout requires an authenticated customer.
Customers own their accounts.
Staff approve/process normal ecommerce operations.
Admins approve/manage staff.
Staff cannot arbitrarily restrict customers from browsing or ordering.
```

Do not weaken any of these rules during authorization design.

---

# 5. Payment Assignment

Payment-specific authorization remains compatible with:

**Phase Group H**

not Group G.

Do not define provider-specific payment permissions here.

This phase may define generic protection requirements for Payment resources.

---

# 6. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

Roles:

```text id="cg6q41"
CUSTOMER
STAFF
ADMIN
```

Do not add extra roles such as:

```text id="xyg8rm"
MANAGER
ACCOUNTANT
DELIVERY_AGENT
SUPPORT
```

without explicit project approval.

If a future requirement needs more granular capabilities, prefer a controlled permission model before multiplying roles unnecessarily.

---

# 7. Dependency Position

Current sequence:

```text id="zpl6vh"
1.1  API Scope
       ↓
1.2  Domain Nouns
       ↓
1.3  Domain Invariants
       ↓
1.4  Logical Data Model
       ↓
1.5  Logical Model Review + Freeze
       ↓
1.6  API Resource Inventory
       ↓
1.7  API Resource Relationships
       ↓
1.8  API Versioning
       ↓
1.9  Endpoint Naming
       ↓
1.10 HTTP Methods
       ↓
1.11 Query Parameters
       ↓
1.12 Pagination
       ↓
1.13 Response Representation
       ↓
1.14 Request/Input
       ↓
1.15 Validation
       ↓
1.16 Error Contract
       ↓
1.17 Authentication
       ↓
1.18 Authorization
       ↓
1.19 ...
```

---

# 8. Authoritative Inputs

Read:

```text id="f2lz8s"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md

docs/domain/business-rules.md
docs/decisions.md
```

Also review the completed work from Phases 1.1–1.17.

---

# 9. Core Authorization Principle

Authorization must follow:

```text id="7bncn4"
AUTHENTICATED IDENTITY
        +
ROLE
        +
RESOURCE
        +
ACTION
        +
OWNERSHIP
        +
BUSINESS STATE
        +
CONTEXT
```

Do not authorize based on role alone.

Example:

```text id="n1y7m1"
CUSTOMER + Order
```

does not mean:

> Customer can modify any Order.

It means:

> Customer may perform only authorized operations on their own eligible orders.

---

# 10. Least Privilege

Every actor receives only the minimum privileges required to perform their legitimate work.

However:

> Least privilege must not become unnecessary friction.

The system should not require Admin approval for ordinary customer activities such as:

```text id="kq8m7m"
browsing
searching
adding items to cart
checking out
viewing own orders
cancelling an eligible own order
submitting requests
```

Security should protect the workflow rather than obstruct it.

---

# 11. Three-Actor Authorization Model

## CUSTOMER

Primary customer-facing actor.

```text id="26f89w"
Broad customer commerce capability
+
own-resource control
-
administrative capability
```

## STAFF

Operational actor.

```text id="3ce3gm"
Normal ecommerce operations
+
order/request/enquiry handling
+
approved inventory/catalog operations
-
customer-account control
-
role administration
```

## ADMIN

Highest administrative actor.

```text id="h0z7i4"
Staff management
+
administrative operations
+
high-level configuration
+
authorized customer/account administration
```

---

# 12. Customer Authorization Philosophy

Customers must be allowed to fully operate their own customer experience.

Unless a business rule explicitly says otherwise, a customer may:

```text id="5qzghw"
browse
search
view products
view categories
manage their cart
checkout
place orders
view their own orders
view own tracking
cancel own eligible order
submit requests
view own requests where supported
submit enquiries
view own enquiries where supported
manage own profile
manage own notifications
```

---

# 13. Customer Must Not Access Another Customer's Data

A customer must not:

```text id="j2x7tu"
read another customer's order
read another customer's request
read another customer's enquiry
read another customer's notification
change another customer's profile
access another customer's private attachments
```

This must be enforced server-side.

---

# 14. Customer Account Ownership

An authenticated customer must be recognized as the owner of their account.

Ownership should be evaluated using trusted authentication context.

Do not accept:

```json id="c8mhs3"
{
  "user_id": "another-customer"
}
```

as sufficient proof of ownership.

---

# 15. Customer ID Must Not Be a Permission Token

Knowing:

```text id="z8m0m4"
user_id = 123
```

does not grant access.

The authorization decision must establish:

```text id="8n8o6q"
authenticated principal
=
resource owner
```

where customer ownership is required.

---

# 16. Customer and Public Catalog

Catalog authorization remains:

```text id="q8bxxi"
PUBLIC
```

for approved public resources.

No customer account is required to:

```text id="yrz0ma"
view products
view categories
search
view product details
```

Do not place customer authorization around public catalog reads.

---

# 17. Customer Ordering

A customer should be able to order normally.

Authorization should only block ordering when a legitimate business/security condition applies, such as:

```text id="8hg8a5"
not authenticated
product unavailable
insufficient stock
invalid checkout
payment failure
invalid order state
```

Staff must not arbitrarily impose customer-specific blocks.

---

# 18. Staff Authorization Philosophy

Staff should be able to do their daily work efficiently.

The authorization model must not make staff ask Admin for permission for every ordinary operational action.

Staff should generally be able to perform approved operations involving:

```text id="69xsbj"
orders
order processing
order statuses
requests
enquiries
approved inventory operations
approved catalog operations
operational notifications
```

subject to the final policy matrix.

---

# 19. Staff Are Not Customer Account Administrators

Staff must not ordinarily:

```text id="0t7vzs"
change customer passwords
change customer roles
disable customer accounts
impersonate customers
view customer credentials
change customer authentication state
```

These are security/account-administration concerns.

---

# 20. Staff Cannot Restrict Customer Shopping

This is an explicit security/business invariant:

> **STAFF cannot arbitrarily restrict a customer's ability to browse or order.**

Do not implement a generic permission such as:

```text id="4n1l9b"
STAFF → block_customer
```

simply because staff need operational authority.

---

# 21. Staff Cannot Modify Customer Ownership

Staff cannot transfer:

```text id="5ytjhe"
Order → another customer
Request → another customer
Enquiry → another customer
```

unless a future, explicitly approved administrative workflow requires controlled reassignment.

---

# 22. Staff Order Access

Staff need operational access to orders.

Conceptually:

```text id="x34d9w"
STAFF
→ view order operational details
→ process order
→ perform authorized status actions
```

But staff should see only information necessary for the operation.

For example, staff do not need:

```text id="x8w7d1"
customer password
authentication tokens
```

and should never receive them.

---

# 23. Staff Order Ownership

Staff are operational handlers, not order owners.

Therefore:

```text id="yhj8bk"
Staff → Order
```

is an operational relationship, not:

```text id="33gbz0"
Staff → owns Order
```

Customer ownership remains intact.

---

# 24. Staff Order Status Operations

Staff may eventually perform:

```text id="yiwqkp"
ACCEPT
PROCESS
READY_FOR_PICKUP
SHIP
DELIVER
```

only according to the final order-state authorization rules.

A staff role alone does not permit invalid state transitions.

---

# 25. Admin Authorization Philosophy

Admin is the highest administrative role.

Admin may:

```text id="m2h9g8"
approve staff
manage staff
manage operational users
perform authorized catalog/inventory operations
manage orders
manage requests
manage enquiries
perform authorized customer-account administration
manage system settings where approved
```

Detailed permission boundaries must still be explicit.

---

# 26. Admin Does Not Equal "Bypass Everything"

Even Admin operations should obey:

```text id="62g8pc"
business invariants
auditability
data integrity
security boundaries
```

For example, Admin should not silently rewrite:

```text id="6sj0z6"
historical order price
payment confirmation
status history
```

without a controlled correction workflow.

---

# 27. Staff Approval

Only authorized Admin actors may approve staff.

Conceptually:

```text id="8yv87f"
Staff candidate
      ↓
ADMIN
      ↓
Approve
      ↓
Active staff access
```

Staff cannot approve themselves.

Customers cannot approve staff.

---

# 28. Role Assignment

Role changes are privileged.

```text id="0jgjkq"
CUSTOMER
→ cannot assign role

STAFF
→ cannot assign role

ADMIN
→ authorized role management
```

Do not allow arbitrary client payloads to change roles.

---

# 29. Role Hierarchy

Document the conceptual hierarchy:

```text id="wm50te"
CUSTOMER
    ↓
customer-owned permissions

STAFF
    ↓
operational permissions

ADMIN
    ↓
administrative permissions
```

But do not implement inheritance such that:

```text id="9e3f7x"
ADMIN automatically bypasses every policy
```

unless specifically justified.

---

# 30. Permission Model

Evaluate whether the project needs:

```text id="i2ofzg"
role-based access control (RBAC)
+
resource ownership policies
+
business-state authorization
```

Recommended baseline:

> Use role-based permissions combined with resource ownership and business-state rules.

Avoid a massive permission matrix when simple policies are sufficient.

---

# 31. Avoid Role Explosion

Do not solve every special case by creating a new role.

Bad:

```text id="wd5yrt"
CUSTOMER
STAFF
SENIOR_STAFF
ORDER_STAFF
INVENTORY_STAFF
DELIVERY_STAFF
MANAGER
SUPERVISOR
ADMIN
SUPERADMIN
```

unless future organizational requirements genuinely justify them.

Start with:

```text id="mb7v0s"
CUSTOMER
STAFF
ADMIN
```

and use explicit permissions for operational differences if needed.

---

# 32. Resource-Level Authorization

Authorization should be evaluated at the resource level.

Example:

```text id="j4tm00"
Order:
  customer → own order
  staff → operational order
  admin → authorized administrative access
```

This is more precise than:

```text id="q76y0f"
STAFF → all database operations
```

---

# 33. Action-Level Authorization

Authorization should also consider the action.

Example:

```text id="e4df45"
STAFF
→ may view order

STAFF
→ may process order

STAFF
→ may not change customer password
```

Therefore:

```text role
+
resource
+
action
```

must be evaluated.

---

# 34. State-Aware Authorization

Certain permissions depend on current state.

Example:

```text id="l0ycd3"
STAFF
→ ship order
```

may be authorized only when:

```text id="e0b9b6"
Order = PROCESSING
```

A staff member cannot ship an order simply because the role permits shipping.

The state transition must also be valid.

---

# 35. Ownership + State

Customer cancellation demonstrates the combination:

```text id="s99m9b"
Customer owns Order
+
Order is cancellable
+
20-minute window is active
```

All conditions must pass.

---

# 36. Authorization Decision Model

Use the conceptual formula:

```text id="o7l35o"
ALLOW only if:

authenticated
AND
authorized actor
AND
authorized resource
AND
authorized action
AND
ownership/context valid
AND
business-state valid
```

A failure at any required stage denies the operation.

---

# 37. Default Deny

For protected resources:

> Access should be denied unless explicitly authorized.

Do not use:

```text id="c3k2p0"
allow by default
then blacklist dangerous actions
```

Prefer:

```text id="8xt9qs"
deny by default
then grant required capability
```

---

# 38. Public Resources Must Be Explicit

The default-deny rule must not accidentally make public browsing inaccessible.

Therefore explicitly classify:

```text id="xk7k3n"
products → public read
categories → public read
```

rather than relying on a global exception.

---

# 39. Authentication Required vs Authorization Required

Distinguish:

```text id="8n4w0m"
not authenticated
```

from:

```text id="67wrf1"
authenticated but not permitted
```

The error contract from Phase 1.16 already provides:

```text id="2p1r3g"
401
403
```

semantics.

---

# 40. Resource Ownership Through Relationships

Use the relationship model from Phase 1.7.

Examples:

```text id="2y9x03"
User → Orders
User → Notifications
User → Cart
User → Requests
User → Enquiries
```

Authorization must use these logical relationships.

---

# 41. Anonymous Request Authorization

Anonymous request submission is explicitly allowed.

Because there is no authenticated User:

```text id="2wvo9j"
authorization
```

must be evaluated differently from authenticated customer-owned resources.

For anonymous creation:

```text id="sv85q4"
public endpoint
+
input validation
+
anti-abuse controls later
```

For later viewing/modification, do not assume anonymous possession of a reference is sufficient unless a secure access mechanism is explicitly designed.

---

# 42. Anonymous Enquiry Authorization

Same principle as requests.

Anonymous users may submit.

But private enquiry data must not become globally readable.

Do not create:

```text id="gmuxod"
/enquiries/{id}
```

as public unrestricted access.

---

# 43. Anonymous Attachments

Anonymous-uploaded request/enquiry attachments must remain private to the associated request/enquiry.

Do not expose predictable public file paths.

---

# 44. Customer Request Ownership

If an authenticated customer submits a request:

```text id="ou7u3o"
Request → User
```

future customer read access can be based on ownership.

If anonymous:

```text id="34t4d3"
Request → no User
```

the secure access mechanism must be explicitly designed later.

Do not invent a weak bearer-ID scheme.

---

# 45. Enquiry Ownership

Same rule:

```text id="i9hr7e"
Authenticated:
Enquiry → owner

Anonymous:
Enquiry → controlled anonymous access model
```

---

# 46. Customer Notifications

Customers may access only notifications intended for them.

Do not allow:

```text id="9b8l0x"
/notifications/{another-user-notification}
```

to be accessible merely by knowing the identifier.

---

# 47. Customer Cart Authorization

Cart access must be bound to the authenticated customer.

The client cannot change:

```text id="6m7q7j"
cart.owner
```

by submitting another user identifier.

---

# 48. Customer Orders

Orders are customer-owned commerce records.

Customer access:

```text id="g7w8zy"
READ own
TRACK own
CANCEL eligible own
```

Not:

```text id="sn79p5"
UPDATE arbitrary order fields
DELETE order
CHANGE status
CHANGE price
```

---

# 49. Customer Order Actions

Customers should have access to only explicitly approved actions.

Version 1 known action:

```text id="co8aoj"
cancel
```

subject to:

```text id="n7y8hg"
20-minute window
eligible state
ownership
```

---

# 50. Customer Cannot Change Order Status

The customer cannot submit:

```json id="w3b4f0"
{
  "status": "DELIVERED"
}
```

or:

```json id="ztvq8m"
{
  "status": "SHIPPED"
}
```

to change an order.

Status transitions are controlled business operations.

---

# 51. Staff Inventory Authorization

Staff may need to perform normal inventory operations.

However:

```text id="d6l7w6"
STAFF
→ only approved inventory operations
```

not:

```text id="3h8cne"
STAFF
→ unrestricted database inventory modification
```

Inventory adjustments should eventually be auditable.

---

# 52. Staff Product Authorization

Staff may be allowed to perform normal ecommerce catalog operations.

Potential examples:

```text id="vrzzny"
create product
update product
manage images
manage variants
update stock
```

The exact matrix must be explicitly finalized.

Do not automatically grant every Product-related operation.

---

# 53. Staff Request/Enquiry Authorization

Staff should be able to:

```text id="xqvecc"
view request
process request
update operational state
view relevant contact information
handle enquiry
```

subject to privacy and business rules.

---

# 54. Staff Notification Authorization

Staff may need operational notifications such as:

```text id="w5a8yx"
new order
new request
new enquiry
payment event
delivery issue
```

Staff notifications must not automatically include private customer-account information unrelated to the operational task.

---

# 55. Admin Customer Access

Admin may need broader customer access for legitimate administration.

However:

> Administrative access must remain purpose-bound.

Possible operations may include:

```text id="f2r7o4"
review account
manage security state
revoke sessions
```

if later approved.

Admin should not receive plaintext credentials or secret authentication material.

---

# 56. Administrative Impersonation

Do not create unrestricted:

```text id="p4vcw6"
"Login as Customer"
```

without a dedicated secure impersonation design.

If impersonation is ever required, it needs:

* explicit permission;
* audit logging;
* clear session context;
* indication that impersonation is active;
* restricted sensitive operations;
* secure exit.

Defer this feature unless genuinely required.

---

# 57. Customer Account Restriction

The business rule is:

> Staff cannot restrict customers from browsing or ordering.

For Admin, do not create a blanket restriction feature during this phase.

Any future account-security restriction must be a specifically approved security capability with:

```text id="hs95ci"
reason
authorization
audit
customer impact
recovery
```

---

# 58. Account Suspension

Treat account suspension as a security-sensitive administrative action.

Do not automatically add it to the normal customer/staff permission matrix.

If later required, specify:

```text id="p0r57a"
who may suspend
why
when
what is blocked
what remains available
how customer is informed
how account is restored
```

---

# 59. Customer Browsing When Account Has Problems

Public browsing should generally remain public.

Do not make:

```text id="b1d7z6"
account issue
→ website catalog inaccessible
```

unless a specific security/legal requirement mandates it.

This preserves the approved public-catalog architecture.

---

# 60. Customer Ordering When Authentication Session Expires

The API should require the customer to re-authenticate when needed, but should not arbitrarily discard legitimate cart/customer workflow data where the security model allows preserving it.

The exact session/cart UX is later.

---

# 61. Object-Level Authorization

The most important security check for customer-owned resources is object-level authorization.

Example:

```text id="2bqg5f"
Customer A
→ GET Order B
```

must fail even though:

```text id="xqzprm"
Customer A is authenticated
```

Authentication alone is insufficient.

---

# 62. Function-Level Authorization

Also protect sensitive actions.

Example:

```text id="cxh3qj"
STAFF
→ cannot approve another Staff account

CUSTOMER
→ cannot create Staff

STAFF
→ cannot assign ADMIN
```

This is function-level authorization.

---

# 63. Mass Assignment and Authorization

Do not rely on input validation alone.

Even if a client cannot normally submit:

```text id="i5i5z3"
role
```

the eventual backend must ensure authorization prevents the field from changing.

Security must exist at multiple layers.

---

# 64. Field-Level Authorization

Some resource fields may be visible to one actor but not another.

Example:

```text id="u6js1n"
Order
```

Customer:

```text id="r9sm5k"
customer-facing fields
```

Staff:

```text id="m9j4h8"
operational fields
```

Admin:

```text id="n6a8m8"
administrative fields
```

Sensitive fields remain internal.

---

# 65. Authorization and Serialization

Authorization must be considered before serialization.

Do not fetch:

```text id="f7l2gk"
all fields
```

and then assume the frontend will ignore sensitive ones.

The API should intentionally expose only the permitted representation.

---

# 66. Authorization and Query Parameters

A customer cannot use query parameters to expand access.

Example:

```text id="b7ohkw"
/orders?user_id=another-customer
```

must not change the authenticated ownership scope.

---

# 67. Authorization and Pagination

Pagination must operate over the authorized dataset.

Example:

```text id="09w16u"
Customer A /me/orders?page=2
```

must paginate:

```text id="z4tbj3"
Customer A's orders
```

not:

```text id="p8yrwu"
all orders
```

followed by a client-side filter.

---

# 68. Authorization and Search

Search results must also be authorization-filtered.

Staff searching orders may have operational access.

Customers searching their own orders must not see others.

---

# 69. Authorization and Caching

Do not cache private responses in a way that can serve one customer's data to another customer.

Private API responses must have appropriate cache-control behavior later.

Public catalog responses may be cacheable separately.

---

# 70. Authorization and CDN

Public:

```text id="d0fcid"
products
categories
images
```

may eventually use CDN caching.

Private:

```text id="zm7bh3"
orders
profile
notifications
requests
enquiries
```

must not accidentally become publicly cached.

This is an architectural security consideration.

---

# 71. Authorization and Background Jobs

Later asynchronous jobs may need to perform system operations without a human user.

Do not treat:

```text id="n4lday"
SYSTEM
```

as a normal user-facing role automatically.

Use explicit system/service authorization for jobs.

Examples:

```text id="go9gpm"
payment callback
notification dispatch
inventory cleanup
order timeout
```

These are implementation concerns later.

---

# 72. Authorization and Webhooks

External webhooks are not customers, Staff, or Admin.

They require separate authentication/signature verification.

Payment webhook authorization belongs to Group H.

Do not let:

```text id="e0p5kf"
public POST
```

stand in for webhook trust.

---

# 73. Staff Approval Security

Staff approval should eventually require:

```text id="n7t2m1"
authenticated Admin
+
authorized action
+
target staff account
```

The client must not be able to submit:

```json id="f7y8a4"
{
  "approved": true
}
```

without authorization.

---

# 74. Role Escalation Protection

Explicitly test:

```text id="bwo3tt"
CUSTOMER → STAFF
CUSTOMER → ADMIN
STAFF → ADMIN
STAFF → ADMIN approval
```

Only approved administrative workflows may perform role changes.

---

# 75. Privilege Escalation Threats

Document protection against:

```text id="xupuxi"
role parameter tampering
user ID swapping
order ID swapping
horizontal privilege escalation
vertical privilege escalation
URL manipulation
query manipulation
mass assignment
ID enumeration
stale authorization
```

---

# 76. Horizontal vs Vertical Access

### Horizontal escalation

Customer A accessing Customer B's data.

Example:

```text id="x1svtz"
Order ownership bypass
```

### Vertical escalation

Customer attempting Staff/Admin operation.

Example:

```text id="8g9dqj"
approve staff
change inventory
ship order
```

Both must be explicitly tested later.

---

# 77. Authorization and Business State

Authorization is not a substitute for domain validation.

Example:

```text id="4i6rm2"
STAFF is authorized to ship orders
```

does not mean:

```text id="x7h8y8"
any order can be shipped
```

The order's current state must permit shipping.

---

# 78. Authorization and Customer Cancellation

Similarly:

```text id="0m7u3w"
CUSTOMER
```

has a possible cancel permission.

But cancellation requires:

```text id="xr4e5q"
owner
+
eligible status
+
20-minute window
```

---

# 79. Authorization and Inventory

Staff may have inventory permission.

But a staff member should not necessarily be allowed to edit:

```text id="wh8xg0"
historical order item quantity
```

because the resource and operation differ.

Permissions must be resource/action specific.

---

# 80. Permission Granularity

Use the smallest useful permission unit.

Example conceptual permissions:

```text id="9je3j0"
products.view
products.manage
inventory.view
inventory.manage

orders.view_operational
orders.process
orders.ship
orders.deliver

requests.view
requests.manage

enquiries.view
enquiries.manage

staff.approve
staff.manage
```

These are examples only.

The final permission vocabulary must be deliberately designed.

Do not implement them yet.

---

# 81. Customer Permissions

Customers do not need a huge explicit permission catalogue.

Ownership-based rules can handle much of their authorization.

Conceptually:

```text id="l9l4mo"
customer.catalog.read
customer.cart.manage_own
customer.orders.read_own
customer.orders.cancel_own
customer.requests.manage_own
customer.enquiries.manage_own
customer.profile.manage_own
customer.notifications.read_own
```

Use policies rather than unnecessary permission records where possible.

---

# 82. Staff Permissions

Staff should get only operational permissions required for their job.

A potential initial set:

```text id="2k73jh"
orders.view_operational
orders.process
orders.ship
orders.deliver

products.view
products.manage  [if approved]

inventory.view
inventory.manage  [if approved]

requests.view
requests.manage

enquiries.view
enquiries.manage

notifications.view_operational
```

Do not treat this as final until the authorization matrix is formally reviewed.

---

# 83. Admin Permissions

Admin has the highest permission set, potentially including:

```text id="8mqi2s"
staff.approve
staff.manage
users.manage_authorized
products.manage
inventory.manage
orders.manage
requests.manage
enquiries.manage
system.manage
```

Do not automatically create a `*` wildcard permission.

Explicit permission sets are easier to audit.

---

# 84. Wildcard Permissions

Avoid permissions such as:

```text id="r1tc5x"
admin.*
staff.*
*
```

as the only protection model.

A wildcard may be useful internally in limited cases, but every sensitive capability should remain understandable and auditable.

---

# 85. Separation of Duties

Consider separation of duties for highly sensitive operations.

For example:

```text id="jcpw3i"
Staff
→ operational processing

Admin
→ staff approval
```

This prevents one role from both requesting and approving its own privileged access.

Do not introduce unnecessary approval workflows for ordinary customer purchases.

---

# 86. Customer vs Staff Separation

Do not reuse Staff permissions for Customers merely because both can view an Order.

The reason for access differs:

```text id="2s4z4b"
Customer
→ own order

Staff
→ operational order
```

---

# 87. Admin Audit Requirements

Privileged administrative actions should eventually produce audit records.

Examples:

```text id="i2l3r5"
staff approval
role change
account security change
critical inventory adjustment
critical order correction
```

Do not implement audit logging in this phase.

---

# 88. Staff Audit Requirements

Important operational Staff actions should also eventually be traceable.

Examples:

```text id="2oxfcb"
order accepted
order shipped
inventory adjusted
request status changed
```

---

# 89. Customer Audit Requirements

Not every customer action needs audit logs.

However, security-sensitive customer actions should be considered:

```text id="8wwe0v"
password change
session revocation
account recovery
```

Exact policy later.

---

# 90. Permission Changes

Changes to Staff/Admin permissions must be controlled.

Do not let a staff member edit their own permissions.

Do not let a customer edit permissions.

Admin permission management must be explicitly authorized.

---

# 91. Authorization Policy Implementation Guidance

The eventual Laravel implementation should favor centralized authorization policies/services rather than scattered checks like:

```php
if ($user->role === 'admin') { ... }
```

throughout controllers.

Conceptually:

```text id="5bt3ls"
Request
 ↓
Authentication
 ↓
Authorization policy
 ↓
Domain/business rule
 ↓
Operation
```

Do not implement this yet.

---

# 92. Avoid Role Checks Everywhere

Avoid fragile code patterns such as:

```text id="t6e5p3"
if role == ADMIN
```

for every business operation.

Use capability/resource/action policies.

Role is an input into authorization, not the whole authorization model.

---

# 93. Resource Policy Concept

Later implementation should be able to express concepts like:

```text id="kwfhni"
OrderPolicy:
    view(user, order)
    cancel(user, order)
    process(user, order)
    ship(user, order)
```

and:

```text id="pgb3gq"
ProductPolicy:
    view(user, product)
    manage(user, product)
```

Do not write actual Laravel policies now.

---

# 94. Customer Order Policy

Conceptually:

```text id="ay2r8n"
view:
    authenticated customer AND owns order

cancel:
    authenticated customer
    AND owns order
    AND order eligible
    AND cancellation window valid
```

---

# 95. Staff Order Policy

Conceptually:

```text id="n55j3h"
view:
    staff with operational order access

process:
    staff with process permission
    AND valid order state

ship:
    staff with ship permission
    AND valid order state
```

---

# 96. Admin Order Policy

Admin can perform authorized administrative order operations.

But even Admin operations should respect business-state rules unless an explicit correction/override workflow is approved.

---

# 97. Administrative Overrides

Do not create generic:

```text id="7wvq6h"
override_everything
```

permission.

If a future administrative override is needed, make it a specific audited capability.

---

# 98. Customer-Friendly Security

Security must not force customers through unnecessary workflows.

Examples of good security:

```text id="kpigcm"
HTTPS
secure session
ownership checks
server-side pricing
server-side inventory
protected credentials
rate limits
```

Examples of unnecessary friction:

```text id="fw7e2m"
Admin approval for registration
Admin approval for every order
Staff approval for browsing
Staff approval for wishlist/cart
```

Avoid the latter.

---

# 99. Authorization and Normal Ecommerce Behavior

A normal customer should experience:

```text id="j5vk9r"
Browse
 ↓
Register/Login
 ↓
Shop
 ↓
Cart
 ↓
Checkout
 ↓
Order
 ↓
Track
```

without staff intervention.

Staff interact with the order after it is created.

```text id="06v6jt"
Customer places order
        ↓
Staff receives order
        ↓
Staff processes
        ↓
Customer tracks
```

This separation is fundamental.

---

# 100. Authorization Matrix

Create the definitive matrix.

At minimum:

| Resource/Action           |          Anonymous | Customer |                   Staff |                           Admin |
| ------------------------- | -----------------: | -------: | ----------------------: | ------------------------------: |
| Browse products           |              Allow |    Allow |                   Allow |                           Allow |
| View categories           |              Allow |    Allow |                   Allow |                           Allow |
| Search catalog            |              Allow |    Allow |                   Allow |                           Allow |
| Register customer         |              Allow |      N/A |                    Deny |                            Deny |
| Submit request            |              Allow |    Allow |             Operational |                     Operational |
| Submit enquiry            |              Allow |    Allow |             Operational |                     Operational |
| Checkout                  |               Deny |    Allow |       Deny as customer* |               Deny as customer* |
| Own cart                  | Deny/defined later |    Allow |                    Deny |                            Deny |
| Own orders                |               Deny |    Allow |           Deny as owner |                   As authorized |
| Operational orders        |               Deny |     Deny |                   Allow |                           Allow |
| Cancel own eligible order |               Deny |    Allow |        Deny as customer | Authorized admin operation only |
| Manage products           |               Deny |     Deny | According to permission |                           Allow |
| Manage inventory          |               Deny |     Deny | According to permission |                           Allow |
| Approve staff             |               Deny |     Deny |                    Deny |                           Allow |
| Manage staff              |               Deny |     Deny |                    Deny |                           Allow |
| Manage own profile        |               Deny |    Allow |                   Allow |                           Allow |

`*` means staff are not automatically treated as customer users merely because they possess an account.

---

# 101. Staff Acting as Customers

Decide and document:

> Staff/Admin accounts should not automatically receive ordinary customer checkout privileges simply because the authentication system technically permits the same endpoint.

If staff need to purchase furniture personally, treat that as a separate business/user-account policy rather than silently mixing operational and customer identities.

Do not let a Staff account exploit customer ownership logic to access unrelated customer resources.

---

# 102. Admin Acting as Customer

Similarly, do not automatically treat Admin as a customer account.

An Admin's ability to perform administration must not give them arbitrary ownership over customer orders.

---

# 103. Resource Access Levels

Use an explicit classification:

```text id="4s1i3e"
PUBLIC
AUTHENTICATED_OWNER
OPERATIONAL_STAFF
ADMINISTRATIVE
INTERNAL
```

Every resource/action should have an appropriate level.

---

# 104. Field-Level Access Matrix

Create a secondary matrix for sensitive fields.

Example:

| Data                       | Customer |                   Staff |      Admin |
| -------------------------- | -------: | ----------------------: | ---------: |
| Product price              |      Yes |                     Yes |        Yes |
| Public stock availability  |      Yes |                     Yes |        Yes |
| Internal reserved quantity |       No | According to permission |        Yes |
| Customer phone on order    |      Own |             Operational | Authorized |
| Customer password          |       No |                      No |         No |
| Payment provider secret    |       No |                      No |         No |
| Internal staff note        |       No |              Authorized | Authorized |
| Historical order price     |      Own |             Operational | Authorized |
| Staff role                 |       No |             Limited/own | Authorized |

Do not expose secret credentials even to Admin through normal API resources.

---

# 105. Security Boundary for Passwords

Absolute rule:

```text id="qw2y1d"
No CUSTOMER
No STAFF
No ADMIN
```

receives another person's plaintext password or authentication secret.

The system does not need to expose those values for normal operations.

---

# 106. Authorization and Password Reset

Password-reset operations belong to authentication/security flows.

A Staff user must not reset a customer's password through ordinary ecommerce APIs.

Admin security operations, if later needed, require explicit design.

---

# 107. Authorization and Email Verification

Changing verification state must not be a generic profile update.

The verification process must be trusted and security-sensitive.

---

# 108. Authorization and Notifications

Customer notification access is owner-based.

Staff notification access is operational.

Admin notification access is administrative only where required.

Do not merge all notification visibility into one role-based rule.

---

# 109. Authorization and Attachments

Attachments inherit the authorization boundary of their parent resource.

Example:

```text id="18fu1w"
Request
  → Attachment
```

If the customer cannot access the Request:

```text id="anft5h"
the customer cannot access the Attachment
```

Staff/admin permissions may differ according to the parent resource.

---

# 110. Authorization and Delivery

Customers can view delivery information for their own order.

Staff can manage operational delivery information.

Admin can manage authorized administrative delivery operations.

Do not expose all delivery records publicly.

---

# 111. Authorization and Payment

Customers may see limited information about their own payment.

Staff may see operational payment information as needed.

Admin may see authorized administrative payment information.

Provider secrets remain internal.

Payment-specific rules are later in Group H.

---

# 112. Authorization and Inventory

Public users get:

```text id="8z9lq3"
availability
```

Staff/Admin get:

```text id="j5p7w5"
inventory operations
```

Do not expose internal inventory controls publicly.

---

# 113. Authorization and Product Management

The public customer sees only published/public product information.

Staff/Admin management views may contain:

```text id="3z3e2h"
internal fields
inventory references
editing metadata
```

Only authorized actors should receive them.

---

# 114. Denial Behavior

Authorization failures should use the Phase 1.16 error contract.

Do not leak sensitive information through denial messages.

Potentially:

```text id="m7gx2m"
403 FORBIDDEN
```

or:

```text id="s4svx1"
404 RESOURCE_NOT_FOUND
```

where hiding resource existence is safer.

The endpoint-specific decision comes later.

---

# 115. Security Testing Requirements

Later automated tests must cover:

### Horizontal escalation

```text id="kfd4d2"
Customer A → Customer B order
Customer A → Customer B notifications
Customer A → Customer B request
```

must fail.

### Vertical escalation

```text id="0nv8m7"
Customer → staff operation
Staff → admin operation
```

must fail.

### Role tampering

```text id="78ryy7"
client sends role=ADMIN
```

must fail.

### Ownership tampering

```text id="fu1iqa"
client changes user_id
```

must fail.

### State bypass

```text id="0o6u2s"
client forces order status
```

must fail.

---

# 116. Security Testing for Staff

Verify Staff cannot:

```text id="3kmqzo"
approve themselves
grant Admin
access passwords
modify customer roles
access unrelated private customer data
arbitrarily block customer ordering
```

---

# 117. Security Testing for Admin

Verify Admin can:

```text id="rpkp3f"
approve staff
manage authorized staff permissions
perform authorized administration
```

but cannot bypass:

```text id="5yoy8c"
fundamental data integrity
audit requirements
payment verification
historical transaction semantics
```

unless an explicitly designed administrative correction workflow exists.

---

# 118. Security Testing for Anonymous Users

Verify anonymous users can:

```text id="1dsy0p"
browse
search
view products
submit request
submit enquiry
```

but cannot:

```text id="2okm3u"
checkout
access private orders
access customer profile
access notifications
perform staff/admin actions
```

---

# 119. Authorization Cache Safety

Later implementation must ensure authorization decisions are not cached incorrectly across users.

Example:

```text id="3ju0ey"
Customer A
→ authorized for Order A

Customer B
→ must not inherit cached authorization for Order A
```

---

# 120. Stale Authorization

If a user's role changes:

```text id="aizwj7"
STAFF → disabled/revoked
```

previously issued credentials must not retain unlimited authority indefinitely.

Session/token revocation requirements from Phase 1.17 must work with authorization.

---

# 121. Time-Sensitive Permissions

Some permissions may be time/state dependent.

Customer cancellation:

```text id="iyb3n5"
20-minute window
```

Staff action:

```text id="4x3coh"
valid order state
```

Authorization must therefore be evaluated at operation time, not assumed from an earlier decision.

---

# 122. Authorization and Transactions

For critical operations:

```text id="1ueh7v"
authorize
+
validate state
+
perform operation
```

must be designed so that the state cannot change between validation and operation in a way that bypasses authorization/business rules.

This is a later concurrency/transaction implementation concern.

---

# 123. Authorization and Idempotency

Privileged actions should remain authorized on every retry.

Do not allow:

```text id="4l2n4h"
first request authorized
second replay bypasses authorization
```

Idempotency does not replace authorization.

---

# 124. Authorization and Audit Logs

Later privileged action logs should identify:

```text id="1vw7mj"
actor
action
resource
target
timestamp
result
```

Do not log secrets.

---

# 125. Authorization Policy for Customer Cancellation

Document the final conceptual policy:

```text id="de17k4"
Actor:
CUSTOMER

Action:
Cancel own order

Requirements:
- authenticated
- order belongs to customer
- cancellation window <= 20 minutes
- order is in a cancellable state
- backend confirms current state
```

This becomes a later implementation/test rule.

---

# 126. Authorization Policy for Staff Approval

Document:

```text id="7q1z12"
Actor:
ADMIN

Action:
Approve Staff

Requirements:
- authenticated Admin
- authorized staff-management capability
- valid target staff account
- operation recorded/audited
```

---

# 127. Authorization Policy for Shipping

Conceptually:

```text id="zdk11n"
Actor:
authorized STAFF/ADMIN

Action:
Ship order

Requirements:
- authenticated
- operational order access
- valid target Order
- order belongs to delivery workflow
- current state permits SHIPPED
```

Exact order rules are finalized later.

---

# 128. Authorization Policy for Product Management

Conceptually:

```text id="5tmppc"
Actor:
authorized STAFF/ADMIN

Action:
manage product

Requirements:
- authenticated
- product-management permission
- valid target Product
```

Do not assume every staff member has this permission.

---

# 129. Authorization Policy for Inventory

Conceptually:

```text id="y0th7p"
Actor:
authorized STAFF/ADMIN

Action:
adjust inventory

Requirements:
- authenticated
- inventory-management capability
- valid product/variant
- adjustment recorded
```

---

# 130. Customer Privacy Principle

Operational staff should receive **enough customer information to fulfill the order**, but not unrestricted access to the customer's entire account.

Examples of operationally relevant data:

```text id="8xje9m"
customer name
contact phone
delivery address
order items
fulfillment information
```

Not required:

```text id="m0h1j2"
password
authentication token
unrelated private enquiries
unrelated order history
```

---

# 131. Data Minimization for Staff

Do not expose "because Staff can see Orders" as justification for exposing every field in User.

The staff representation should be intentionally limited.

---

# 132. Admin Data Minimization

Even Admin should receive sensitive data only when operationally necessary.

Highest privilege does not mean:

> serialize everything.

---

# 133. Service-to-Service Authorization

Future internal jobs/services should use explicit service authorization.

Do not reuse a real Admin credential for background processing.

Examples:

```text id="y1k5c9"
payment webhook processing
notification dispatch
scheduled order tasks
```

Payment-specific service authentication belongs to Group H.

---

# 134. Permission Documentation

In:

```text id="kw8xpx"
docs/api/api-contract.md
```

add:

```text
## Authorization Contract

### Actors
### Authorization Model
### Ownership
### Public Resources
### Customer Permissions
### Staff Permissions
### Admin Permissions
### Role Assignment
### Staff Approval
### State-Aware Authorization
### Field-Level Exposure
### Anonymous Operations
### Denial Behavior
### Least Privilege
### Audit Requirements
### Security Rules
```

---

# 135. Update Resource Documentation

In:

```text id="h4m6hf"
docs/api/api-resources.md
```

for each resource record:

```text
who may read
who may create
who may update
who may delete
who may perform actions
ownership rule
sensitive fields
```

Do not yet define endpoint URLs.

---

# 136. Update Domain Rules

In:

```text id="r89f2h"
docs/domain/business-rules.md
```

make explicit:

```text
Customer owns own account.
Staff are operational users.
Admin approves staff.
Staff cannot restrict customer browsing/order access.
Customer-owned resources require ownership checks.
Privileged business actions require authorization.
```

---

# 137. Update API Conventions

In:

```text id="yb5a4k"
docs/api/api-conventions.md
```

record:

```text
deny by default
server-side authorization
ownership checks
role + resource + action + state
no client-supplied role authority
field-level serialization
authorization-aware queries
private-resource caching precautions
```

---

# 138. Update Central Decisions

In:

```text id="gr2x10"
docs/decisions.md
```

record decisions such as:

```text
AUTHZ-001 — Three-role authorization model
AUTHZ-002 — Customer owns own account
AUTHZ-003 — Staff are operational, not customer-account administrators
AUTHZ-004 — Admins approve staff
AUTHZ-005 — Staff cannot arbitrarily restrict customer browsing/ordering
AUTHZ-006 — Authorization is role + resource + action + state
AUTHZ-007 — Default deny for protected resources
AUTHZ-008 — Version 1 roles are closed
```

Use the project's established decision numbering scheme where appropriate.

---

# 139. AGENTS.md Permanent Rules

Only add principles that should govern all future implementation.

Recommended:

```text
Authorization is enforced server-side.

Never trust client-supplied role, ownership, permission, or resource identity as proof of authorization.

Customer ownership must be preserved.

Staff operational access must not become customer-account administration.

Administrative authority must remain explicit and auditable.

Protected resources use deny-by-default authorization.

Business-state rules and authorization are both required for state-changing operations.
```

---

# 140. Required Authorization Matrix

Create the authoritative role/action matrix inside:

```text id="jo1est"
docs/api/api-contract.md
```

At minimum include:

```text
PUBLIC
CUSTOMER
STAFF
ADMIN
```

against:

```text
Products
Categories
Cart
Orders
Tracking
Payment
Delivery
Requests
Enquiries
Notifications
User/Profile
Inventory
Staff
Roles
```

and:

```text
READ
CREATE
UPDATE
DELETE
CANCEL
PROCESS
APPROVE
SHIP
DELIVER
MANAGE
```

Only include actions meaningful to each resource.

---

# 141. Permission Matrix Review Rule

Do not treat the first matrix as immutable.

Review whether each permission is:

```text
necessary
sufficient
too broad
too narrow
dangerous
duplicated
```

Security review should favor least privilege without destroying operational usability.

---

# 142. Explicitly Out of Scope

Do NOT:

```text id="yv4y85"
Implement Laravel Policies
Implement Gates
Implement middleware
Create Spatie Permission configuration
Create role tables
Create permission tables
Create migrations
Create authorization controllers
Create admin UI
Create customer UI
Create Flutter authorization code
Create token middleware
Implement impersonation
Implement MFA
Implement payment authorization
```

This phase defines the contract, not the implementation.

---

# 143. Definition of Done

Phase 1.18 is complete when:

1. CUSTOMER, STAFF, and ADMIN authorization boundaries are explicitly defined.
2. Customer account ownership is explicit.
3. Customers retain broad freedom over their own ecommerce experience.
4. Public browsing remains unauthenticated.
5. Checkout remains authenticated.
6. Anonymous requests remain allowed.
7. Anonymous enquiries remain allowed.
8. Staff operational responsibilities are explicit.
9. Staff cannot arbitrarily restrict customers from browsing or ordering.
10. Staff cannot access customer credentials.
11. Admin is established as the highest administrative role.
12. Admin approval of staff is explicit.
13. Role assignment is server-controlled.
14. Protected resources use deny-by-default semantics.
15. Authorization considers role, resource, action, ownership, and state.
16. Customer horizontal privilege escalation is addressed.
17. Customer-to-staff/admin vertical escalation is addressed.
18. Staff-to-admin escalation is addressed.
19. Field-level exposure is considered.
20. Anonymous-resource security is considered.
21. Authorization and domain validation remain separate but coordinated.
22. Authorization changes are compatible with the Version 1 closed-enum policy.
23. Payment authorization remains assigned to **Phase Group H**.
24. Consolidated documentation has been updated.
25. No implementation code has been written.

---

# 144. STOP CONDITION — Mandatory

After updating:

```text id="zizd6v"
AGENTS.md where appropriate
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the authorization/security review:

**STOP.**

Do not implement Laravel authorization.

Do not create policies.

Do not create middleware.

Do not create role/permission database tables.

Do not build Admin authorization UI.

Do not implement frontend guards.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.19 — Define Concrete API Endpoint Inventory

That phase will finally move from abstract resource/relationship rules into a **complete Version 1 endpoint catalogue**, defining for every endpoint:

```text id="x6s0rr"
HTTP method
URL/path
resource
actor
purpose
authentication requirement
authorization requirement
query parameters
request body
response
error codes
idempotency
state/business rules
```

It should be done as a contract/design phase only; **no Laravel implementation yet**.