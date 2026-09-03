# Phase 1.28 — Define User/Profile API Contract

## 1. Purpose

Phase 1.28 defines the **Version 1 User/Profile API Contract**.

This phase establishes the authenticated account-facing API for the three human actor types already defined:

```text
CUSTOMER
STAFF
ADMIN
```

However, the primary focus is the **Customer's own account/profile**.

The central principle is:

> **A Customer owns their account. Their profile is self-managed within the fields the business permits. Staff do not control or restrict customer accounts as part of normal ecommerce operations. Admin has the highest administrative authority, but sensitive account operations must remain explicit and controlled.**

The contract must support the shared identity model across:

```text
Next.js Website
Flutter App
Staff/Admin application
```

without creating separate identities for each client.

> **Note:** This file was inadvertently deleted during Phase 1.28 documentation updates (git cleanup removed the untracked file) and has been restored. The authoritative User/Profile contract is now consolidated in `docs/api/api-contract.md §29`, `docs/api/api-resources.md §8`/`§13.1`, `docs/api/api-conventions.md §29`, `docs/domain/business-rules.md §14`, `docs/decisions.md ADR/USER-001..007`, and `AGENTS.md §17`. This phase file is retained as the original instruction source; see the consolidated docs for the normative contract.

---

# 2. Scope

This phase covers:

```text
Customer profile
Authenticated self-context
Profile retrieval
Profile updates
Contact information
Account identity
Password-change boundary
Account-security boundary
Customer-owned account data
Staff/Admin profile representation
Cross-platform account consistency
Account privacy
```

This phase does **not** implement:

```text
authentication
authorization middleware
password hashing
password reset
email delivery
saved addresses
payment
notifications
```

Those belong to their respective phases/groups.

---

# 3. Documentation Strategy

Continue the consolidated documentation strategy.

Update:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

Do **not** create:

```text
user-api.md
profile-api.md
customer-profile.md
account-api.md
user-contract.md
```

as permanent standalone documents.

---

# 4. Authoritative Project Paths

Use:

```text
AGENTS.md
docs/VISION.md
```

Do not reintroduce the old filenames.

---

# 5. Payment Boundary

Payment remains assigned to:

**Phase Group H**

This phase must not define payment/profile relationships beyond what is necessary to prevent accidental exposure.

Do not expose:

```text
payment credentials
payment provider data
provider secrets
```

through User/Profile responses.

---

# 6. Email Boundary

Real email delivery remains deferred to:

**Phase Group R**

Therefore this phase may define an:

```text
email
email_verified
```

account attribute if required by the authentication model, but must not implement email delivery.

---

# 7. Version 1 Role Policy

Version 1 has exactly:

```text
CUSTOMER
STAFF
ADMIN
```

as the approved role values.

They remain:

> **CLOSED by default.**

Do not introduce:

```text
MANAGER
SUPPORT
DELIVERY_AGENT
ACCOUNTANT
SUPERADMIN
```

during this phase.

---

# 8. Dependency Position

Current sequence:

```text
1.23 Order API Contract
       ↓
1.24 Tracking + Fulfillment
       ↓
1.25 Made-to-Order Request
       ↓
1.26 General Enquiry
       ↓
1.27 Notification
       ↓
1.28 User/Profile
       ↓
1.29 Staff/Admin Operational API Contract
       ↓
1.30 Cross-Domain Review
       ↓
...
```

User/Profile now provides the account-facing foundation required before final Staff/Admin operational contract work.

---

# 9. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Specifically review:

```text
Phase 1.17 Authentication
Phase 1.18 Authorization
Phase 1.19 Endpoint Inventory
Phase 1.21 Cart
Phase 1.22 Checkout
Phase 1.23 Order
Phase 1.25 Request
Phase 1.26 Enquiry
Phase 1.27 Notification
```

---

# 10. Core User/Profile Principle

The API must distinguish between:

```text
Identity
Profile
Credentials
Authorization
Customer-owned resources
```

Do not combine everything into one mutable `User` object.

---

# 11. Identity vs Profile

Identity answers:

> Who is this authenticated actor?

Profile answers:

> What customer-facing information is associated with that actor?

Credentials answer:

> How does the actor authenticate?

Authorization answers:

> What may the actor do?

These must remain separate concepts even if they share one database model later.

---

# 12. Self Context

The most important customer account endpoint should represent the authenticated actor's own identity.

Recommended:

```text
USER-001

GET /api/v1/me
```

Authentication:

```text
Required
```

Purpose:

> Return the authenticated actor's safe account/profile representation.

---

# 13. Why `/me`

Prefer:

```text
GET /api/v1/me
```

over:

```text
GET /api/v1/users/{id}
```

for ordinary customer self-service.

This makes ownership explicit and reduces unnecessary IDOR risk.

---

# 14. `/me` Authorization

The endpoint uses:

```text
authenticated principal
```

as the identity.

The customer does not supply:

```text
user_id
customer_id
account_id
```

to select whose profile is returned.

---

# 15. User/Profile Response

Conceptually:

```json
{
  "data": {
    "id": "...",
    "role": "CUSTOMER",
    "name": "...",
    "email": "...",
    "phone": "...",
    "email_verified": false,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

This is illustrative.

Only include fields approved for the actual User/Profile resource.

---

# 16. Fields That Should Never Be Returned

Do not expose:

```text
password
password_hash
authentication tokens
reset tokens
verification secrets
session secrets
security answers
provider secrets
```

even to Admin through normal User/Profile serialization.

---

# 17. Role Exposure

The authenticated actor may need to know their current role.

For example:

```text
role = CUSTOMER
```

This may be included in `/me`.

However:

> The client must never be able to modify the role through Profile APIs.

---

# 18. Role Is Not a Profile Field

Do not allow:

```text
PATCH /me
{
  "role": "ADMIN"
}
```

The request must reject, ignore, or otherwise safely prevent protected fields according to the established input policy.

Recommended:

> Reject client attempts to modify server-controlled fields.

---

# 19. User ID

The account's stable identifier may be returned.

The customer should not be allowed to modify it.

---

# 20. Email

Email is both:

```text
contact information
+
authentication/account identity
```

Treat it as security-sensitive.

Do not necessarily process email changes as ordinary profile updates.

---

# 21. Recommended Email-Change Boundary

A safer design is:

```text
PATCH /me
```

may update ordinary profile information, while:

```text
email change
```

uses a dedicated security-sensitive operation.

Do not silently make email changes equivalent to changing a display name.

---

# 22. Phone

Phone is customer profile/contact data.

It may be updated through a normal profile operation if approved.

However, if phone verification exists later, verification status must be managed separately.

Do not let:

```text
phone = new value
```

automatically imply:

```text
phone_verified = true
```

---

# 23. Name

Customer name is ordinary profile information.

It can generally be updated through the profile update operation.

Apply the global validation rules for:

```text
length
format
requiredness
```

---

# 24. Profile Update Endpoint

Recommended:

```text
USER-002

PATCH /api/v1/me
```

Authentication:

```text
Required
```

Authorization:

```text
Own profile only
```

---

# 25. Mutable Profile Fields

Define a controlled allow-list.

Likely candidates:

```text
name
phone
```

Potentially:

```text
email
```

but preferably through a dedicated security operation.

Do not allow arbitrary user-model attributes.

---

# 26. Server-Controlled Fields

The customer cannot modify:

```text
id
role
created_at
updated_at
email_verified
account_status
permissions
staff_approval_state
```

unless a dedicated trusted workflow exists.

---

# 27. Account Status

Account status is distinct from role.

For example:

```text
role = CUSTOMER
status = ACTIVE
```

However, do not introduce an account-status enum unless it is actually required by the domain contract.

If one exists, use the CLOSED Version 1 policy.

---

# 28. Staff Account Status

Staff accounts may have lifecycle states relating to:

```text
approval
activation
suspension
disablement
```

These belong to Staff/Admin operational management, not ordinary Profile PATCH.

---

# 29. Customer Account Restrictions

Explicitly preserve the approved rule:

> Staff cannot arbitrarily restrict a customer's account, browsing, or ordering capability.

Therefore ordinary profile APIs must not expose Staff controls over Customer accounts.

---

# 30. Customer Account Ownership

A Customer can change allowed information about:

```text
their own account
```

but cannot:

```text
change owner
transfer account
change role
grant privileges
```

---

# 31. Profile Update Input

Conceptual:

```json
{
  "name": "New Name",
  "phone": "+255..."
}
```

Do not include:

```json
{
  "role": "ADMIN",
  "user_id": "...",
  "permissions": []
}
```

---

# 32. Partial Update Semantics

Because PATCH is used:

> Only fields included in the request are changed.

An omitted field remains unchanged.

Do not interpret omission as:

```text
set to null
```

unless the individual field explicitly permits that semantic.

---

# 33. Nullability

For each Profile field determine whether:

```text
null
```

is valid.

Do not assume every optional field can be cleared.

For example:

```text
phone = null
```

may be allowed or may not be, depending on the business model.

Document the actual choice.

---

# 34. Email Update Security

If email changes are supported:

```text
old email
→ authenticated request
→ security confirmation
→ new email verification
→ account email changed
```

The exact workflow belongs with authentication/security implementation.

Do not allow a simple unauthenticated PATCH to change login identity.

---

# 35. Password Change

Password change should be treated as a **security operation**, not ordinary profile mutation.

Canonical endpoint (single, per `docs/api/api-contract.md §29.8` `USER-004` / `AUTH-008`):

```text
AUTH-008

POST /api/v1/auth/change-password
```

`POST /api/v1/me/password` (`USER-003`) is **RETIRED** legacy alias — do not implement as duplicate authoritative endpoint.

---

# 36. Password Change Input

Conceptually:

```json
{
  "current_password": "...",
  "new_password": "..."
}
```

The exact fields belong to the Authentication contract.

Do not return or persist plaintext password data beyond the secure processing boundary.

---

# 37. Password Change Authorization

Require:

```text
authenticated user
+
ownership of own credential
+
current credential verification
```

where the chosen security model requires it.

Staff cannot normally change Customer passwords.

---

# 38. Admin Password Operations

Admin access to Customer credentials must not expose the customer's current password.

If an Admin needs account recovery/security intervention later, it must use a dedicated security workflow.

---

# 39. Password Reset

Password reset was already part of Phase 1.17.

Do not redefine it here.

User/Profile documentation should merely reference the Authentication contract.

---

# 40. Email Verification

Likewise, email verification belongs to Authentication.

Profile may expose:

```text
email_verified
```

as a read-only state.

---

# 41. Saved Addresses

Saved address book is explicitly deferred.

Therefore User/Profile must **not** include:

```text
addresses[]
saved_addresses[]
default_address
```

as Version 1 profile data.

Order/Checkout captures transaction-specific delivery information separately.

---

# 42. Notification Settings

Do not automatically add a large notification-preferences object.

The Notification API is separate.

If Version 1 needs notification preferences, define them deliberately rather than embedding arbitrary settings into `/me`.

---

# 43. Cart in Profile

Do not embed the entire Cart in `/me`.

Cart has its own contract:

```text
/me/cart
```

Keep resources separate.

---

# 44. Orders in Profile

Do not embed:

```text
orders[]
```

inside `/me`.

Orders have their own paginated resource.

---

# 45. Requests in Profile

Likewise do not embed:

```text
requests[]
```

inside `/me`.

Use the Request API.

---

# 46. Enquiries in Profile

Do not embed:

```text
enquiries[]
```

inside `/me`.

Use the Enquiry API.

---

# 47. Notifications in Profile

Do not embed:

```text
notifications[]
```

inside `/me`.

Use the Notification API.

This keeps the account representation lightweight.

---

# 48. Resource Boundary

The User/Profile response should describe the account itself.

It should not become:

```text
everything-about-this-customer
```

---

# 49. Customer Data Minimization

Return only data necessary for:

```text
profile display
account management
client authorization context where appropriate
```

Avoid unnecessary sensitive information.

---

# 50. Staff Profile

Staff may have a Profile representation.

However, operational staff fields should not leak into Customer representations.

Potential staff fields:

```text
name
work email
role
staff status
```

Use only approved fields.

---

# 51. Admin Profile

Admin Profile may show:

```text
name
email
role
account status
```

Do not expose administrative secrets.

---

# 52. Customer Viewing Staff Profiles

Do not assume Customers can browse Staff profiles.

A Staff profile is not automatically public.

---

# 53. Staff Viewing Customer Profiles

Staff should not receive unrestricted Customer profiles merely because they process Orders.

They should receive only operationally relevant information through authorized resource representations.

---

# 54. Admin Viewing Customer Profiles

Admin may have authorized Customer management visibility later.

The exact endpoint belongs to the Staff/Admin operational contract.

Do not turn `/me` into a customer administration API.

---

# 55. `/me` Is Self-Service

The `/me` endpoint should always mean:

> The currently authenticated actor.

A Staff member calling `/me` sees themselves.

An Admin calling `/me` sees themselves.

A Customer calling `/me` sees themselves.

---

# 56. No `/me?user_id=`

Do not support query-based identity substitution such as:

```text
/me?user_id=123
```

The authenticated principal determines identity.

---

# 57. Current User Authorization

The backend obtains:

```text
authenticated principal
```

from the authentication system.

It does not trust:

```text
request body user_id
query user_id
URL user_id
```

for self-context.

---

# 58. Profile Update Security

Mass assignment must be prevented.

Only explicitly permitted profile fields may be updated.

Even if an attacker submits:

```json
{
  "role": "ADMIN",
  "is_admin": true,
  "permissions": ["*"]
}
```

none may alter authorization.

---

# 59. Field Allow-List

Create a definitive list:

```text
customer_profile.mutable:
    name
    phone

security-sensitive:
    email
    password

server-controlled:
    id
    role
    permissions
    timestamps
    verification state
    account state
```

Adjust according to final business requirements.

---

# 60. User/Profile Errors

Use Phase 1.16.

Potential:

```text
AUTHENTICATION_REQUIRED
INVALID_VALUE
INVALID_FORMAT
FORBIDDEN
RESOURCE_NOT_FOUND
```

Password/email changes may use authentication-specific codes from Phase 1.17.

Do not create duplicate error vocabularies.

---

# 61. Profile Validation

Profile validation follows Phase 1.15.

Examples:

```text
name → valid string/length
phone → approved format
email → valid format
```

Do not perform business authorization through field validation.

---

# 62. Sensitive Field Validation

Fields related to:

```text
email
password
role
account status
verification
```

require dedicated security handling.

Do not treat them as ordinary profile fields simply because they are stored on the same User record later.

---

# 63. Customer Profile Response Caching

`/me` is private.

Do not publicly cache:

```text
GET /api/v1/me
```

or private profile responses.

---

# 64. Profile Response Headers

The eventual implementation should use appropriate private/no-store cache semantics.

Do not define framework-specific headers here unless already part of the global API convention.

---

# 65. Cross-Platform Profile

The same customer identity must be available from:

```text
Next.js
Flutter
```

A customer should not have:

```text
web profile
mobile profile
```

as two separate accounts.

---

# 66. Profile Synchronization

Example:

```text
Customer changes phone on website
        ↓
server profile updated
        ↓
Customer opens Flutter
        ↓
Flutter retrieves same profile
```

The backend is authoritative.

---

# 67. Local Profile State

Next.js/Flutter may cache a profile locally for UX.

It must not become authoritative over:

```text
role
account status
authorization
verification
```

---

# 68. Role Refresh

Clients must be able to obtain current role/account state from the backend.

This matters if:

```text
Staff role changes
account disabled
Admin changes permissions
```

Do not assume a locally cached role remains valid indefinitely.

---

# 69. Role Changes

Role changes are not performed through `/me`.

They belong to Staff/Admin management.

---

# 70. Permission Changes

Permissions are not customer-editable profile fields.

They belong to authorization management.

---

# 71. Account Deletion

Evaluate whether Version 1 supports customer account deletion.

Do not use:

```text
DELETE /me
```

automatically.

Because Customers may have:

```text
Orders
Requests
Enquiries
Payments
```

hard deletion can conflict with business record retention.

---

# 72. Recommended Account Deletion Policy

For Version 1:

> Do not expose customer self-delete until the retention/privacy model is explicitly approved.

Record the decision if this is the chosen scope.

A later privacy/account-lifecycle phase can define:

```text
anonymization
soft deletion
retention
legal record preservation
```

---

# 73. Account Deactivation

Do not give customers a simple:

```text
PATCH /me
{
  "status": "DISABLED"
}
```

unless account lifecycle explicitly supports self-deactivation.

Do not expose Staff account-state mutation through Customer Profile.

---

# 74. Customer Account Security

The API should eventually support security-sensitive operations such as:

```text
password change
session revocation
email verification
password recovery
```

but these should remain explicitly separated from ordinary profile editing.

---

# 75. Session Management

Evaluate whether Version 1 exposes:

```text
GET /me/sessions
POST /me/sessions/revoke
```

For initial implementation, this can remain deferred unless the authentication implementation requires it.

Do not build unnecessary session-management UI.

---

# 76. Recommended Security Boundary

Keep:

```text
Profile
→ ordinary personal data

Authentication
→ credentials/session/security

Authorization
→ role/permissions

Customer resources
→ orders/cart/requests/enquiries/notifications
```

as separate concepts.

---

# 77. Staff Cannot Edit Customer Profile Through `/me`

Staff calling:

```text
GET /me
```

see their Staff identity.

They cannot use `/me` to impersonate a Customer.

---

# 78. Admin Cannot Change Customer Identity Through `/me`

Admin's `/me` remains Admin self-context.

Customer administration requires separate authorized operations.

---

# 79. No Impersonation Through User/Profile

Do not add:

```text
GET /me?as_user=...
```

or equivalent.

Any future impersonation feature requires a dedicated security design.

---

# 80. User/Profile Endpoint Inventory

Recommended:

```text
USER-001
GET /api/v1/me

USER-002
PATCH /api/v1/me
```

Canonical security endpoint (single, per `docs/api/api-contract.md §29.8` `AUTH-008`):

```text
AUTH-008
POST /api/v1/auth/change-password
```

`POST /api/v1/me/password` (`USER-003`) is **RETIRED** legacy alias — do not duplicate password operations across API domains.

---

# 81. Customer Profile Collection

There should be no:

```text
GET /api/v1/users
```

available to Customers.

Customer self-service does not require a public User collection.

---

# 82. Customer Profile Lookup

Do not make arbitrary:

```text
GET /api/v1/users/{id}
```

customer-accessible.

If Admin later needs User lookup, that belongs to the administrative contract.

---

# 83. Staff Lookup

Staff directory endpoints, if required, belong to Phase 1.29.

Do not define them as part of general User/Profile self-service.

---

# 84. Admin User Management

Likewise, customer/staff management endpoints belong to Phase 1.29.

This phase defines the resource concepts needed to support them.

---

# 85. Profile Endpoint Contract Record

Inside:

```text
docs/api/api-contract.md
```

define:

```markdown
### USER-001 — Current User Profile

Method:
GET

Path:
/api/v1/me

Actor:
Authenticated Customer/Staff/Admin

Authentication:
Required

Authorization:
Self

Purpose:
Retrieve authenticated actor's safe profile representation.

Response:
User/Profile representation

Errors:
AUTHENTICATION_REQUIRED
```

---

# 86. Profile Update Contract

```markdown
### USER-002 — Update Current User Profile

Method:
PATCH

Path:
/api/v1/me

Actor:
Authenticated Customer/Staff/Admin

Authentication:
Required

Authorization:
Self

Purpose:
Update permitted profile fields.

Client-controlled:
Approved mutable fields only.

Server-controlled:
id
role
permissions
timestamps
verification state
account state

Response:
Updated safe profile representation
```

---

# 87. Password Contract Boundary

If Password is kept in the Authentication API:

```text
AUTH-xxx
```

do not duplicate the endpoint under:

```text
USER-003
```

The important decision is:

> Credential operations belong to Authentication, while User/Profile exposes profile information.

Choose one canonical endpoint and record it.

---

# 88. Recommended Password Placement

Prefer keeping password operations under:

```text
/auth
```

because password change is a credential/security operation, not ordinary profile editing.

For example:

```text
POST /api/v1/auth/change-password
```

or the project's established Authentication naming convention.

Do not create two endpoints doing the same thing.

---

# 89. Email Change Boundary

Likewise, treat email change as Authentication/security-sensitive if it affects login identity.

The Profile contract may expose the email, but not necessarily manage its full lifecycle.

---

# 90. Customer Profile Data Matrix

Add:

| Field              |       Customer Read |   Customer Update |                    Staff |                 Admin |
| ------------------ | ------------------: | ----------------: | -----------------------: | --------------------: |
| ID                 |                 Yes |                No |               Authorized |            Authorized |
| Name               |                 Yes |               Yes | Operational where needed |            Authorized |
| Email              |                 Yes | Security workflow | Operational where needed |            Authorized |
| Phone              |                 Yes |               Yes | Operational where needed |            Authorized |
| Role               |                 Yes |                No |           No self-change | Authorized management |
| Verification state |                Read |                No |    Read where authorized |            Authorized |
| Account status     | Read where relevant |                No |                       No |            Authorized |
| Password           |                  No |     Auth workflow |                       No |                    No |
| Permissions        |   No/direct details |                No |                       No | Authorized management |
| Created at         |                 Yes |                No |               Authorized |            Authorized |

---

# 91. Customer Account Privacy

Customer must not see:

```text
internal staff notes
internal account flags
administrative moderation notes
security investigation data
```

through `/me`.

---

# 92. Staff Profile Privacy

Staff should not automatically see Admin-only fields.

---

# 93. Admin Profile Privacy

Admin should still not see credentials/secrets.

---

# 94. Profile and Order Data

Do not expose Orders through Profile.

Use:

```text
GET /me/orders
```

where appropriate.

---

# 95. Profile and Cart Data

Use:

```text
GET /me/cart
```

not Profile embedding.

---

# 96. Profile and Request Data

Use:

```text
GET /me/requests
```

not Profile embedding.

---

# 97. Profile and Enquiry Data

Use:

```text
GET /me/enquiries
```

not Profile embedding.

---

# 98. Profile and Notification Data

Use:

```text
GET /me/notifications
```

not Profile embedding.

---

# 99. Profile as Lightweight Resource

The `/me` response should remain compact enough to be requested frequently by:

```text
Next.js
Flutter
```

without fetching the customer's entire account history.

---

# 100. Authentication State

If `/me` is used to restore application state after startup:

```text
app starts
→ authenticate/session check
→ GET /me
```

the response must provide enough identity information to establish the UI context.

---

# 101. Role-Based UI Is Not Security

Next.js/Flutter may use:

```text
role
```

to decide which navigation items to display.

But the backend still enforces authorization.

Never rely on hidden UI buttons as the security mechanism.

---

# 102. Customer Profile and Staff Restriction

Explicitly verify:

```text
Staff cannot:
- change customer role
- disable customer
- modify customer ordering ability
- modify customer browsing ability
```

through User/Profile APIs.

---

# 103. Admin Profile Management Boundary

Admin management of other accounts will be defined in Phase 1.29.

Do not put administrative user management into `/me`.

---

# 104. Self-Service vs Administration

Use the distinction:

```text
/me
→ self-service

/users/{id}
→ administrative/operational resource
```

and protect the latter appropriately.

---

# 105. Profile Validation Errors

Use stable codes from Phase 1.16:

```text
INVALID_VALUE
INVALID_FORMAT
MISSING_REQUIRED_FIELD
```

For sensitive fields, use Authentication codes where appropriate.

---

# 106. Forbidden Profile Mutation

An attempt to modify:

```text
role
permissions
account_status
user_id
```

must not succeed.

This should be tested explicitly.

---

# 107. Mass Assignment Security Test

Send:

```json
{
  "name": "Alice",
  "role": "ADMIN",
  "permissions": ["*"],
  "account_status": "ACTIVE"
}
```

Expected:

```text
Only explicitly mutable fields can change.
```

The role/permission/account-security fields remain server-controlled.

---

# 108. IDOR Security Test

Customer A attempts:

```text
PATCH /api/v1/users/customer-b
```

through any future generic User endpoint.

Must fail.

Customer self-service should use `/me`.

---

# 109. Role Escalation Security Test

Customer attempts:

```text
PATCH /me
{
  "role": "ADMIN"
}
```

Must fail.

---

# 110. Staff Security Test

Staff attempts to update another Customer profile.

Must fail unless an explicitly approved Admin-only workflow exists.

---

# 111. Customer Ordering Independence

Profile changes must not provide a hidden:

```text
can_order
```

switch controlled by Staff.

This preserves the approved customer autonomy rule.

---

# 112. Account Status and Ordering

If a future security mechanism needs to suspend an account, that must be explicitly defined in a separate authorization/account-lifecycle decision.

Do not let Staff manipulate account status simply because they manage Orders.

---

# 113. Account Status and Browsing

Even if future administrative account security exists, public catalog access remains a separate concern.

Do not couple `/me` account state to public Catalog visibility.

---

# 114. Account Deletion and Historical Orders

If deletion is eventually implemented, Order historical integrity must remain intact.

The customer identity associated with historical Orders must not disappear in a way that destroys transaction history.

---

# 115. Data Anonymization

A future privacy/account-lifecycle design may use:

```text
anonymization
```

instead of hard deletion.

Do not implement or decide the exact anonymization fields here.

---

# 116. Profile and Notifications

Notification preferences, if later required, should not permit customers to disable mandatory transactional account/security communications.

This belongs to Notification design.

---

# 117. Profile and Email Delivery

Changing profile contact details does not require Group R to exist yet.

Email changes can be recorded with verification state while delivery remains deferred.

---

# 118. Profile and Verification

If email verification is required:

```text
email_verified
```

must be server-controlled.

Do not accept:

```json
{
  "email_verified": true
}
```

from the client.

---

# 119. Profile and Customer Requests

Authenticated Request ownership is derived from the User identity.

Profile changes must not alter historical Request ownership.

---

# 120. Profile and Enquiries

Likewise, Enquiry ownership remains tied to the account identity.

Changing name/phone does not transfer old Enquiries to someone else.

---

# 121. Profile and Orders

Profile changes must never modify:

```text
Order customer
Order historical contact snapshot
Order historical delivery information
```

through the `/me` endpoint.

---

# 122. Profile and Delivery Address

Customer Profile does not own the historical Order delivery address.

The Order snapshot remains authoritative for the transaction.

Saved address book remains deferred.

---

# 123. Cross-Client Security

A customer may update profile data through:

```text
website
```

and later see the same data in:

```text
Flutter
```

because the backend remains the source of truth.

---

# 124. Profile Synchronization Race

If website and Flutter update the profile simultaneously:

```text
website → name A
Flutter → name B
```

the implementation must have deterministic behavior.

This is a later concurrency concern.

---

# 125. Profile Idempotency

PATCH profile operations should ideally be idempotent for the same resulting field values.

Do not require special idempotency keys for ordinary Profile PATCH unless the later implementation identifies a need.

---

# 126. Password Idempotency

Password changes are not ordinary idempotent profile updates.

They should use authentication/security semantics.

---

# 127. Profile Audit

Sensitive profile/security actions may eventually require audit logging:

```text
email change
password change
role change
account state change
```

Do not implement audit in this phase.

---

# 128. User/Profile API and Authorization

Customer self-service:

```text
authenticated
+
self
```

Staff/Admin self-service:

```text
authenticated
+
self
```

Administrative account management:

```text
authorized STAFF/ADMIN where explicitly permitted
```

but not part of the basic `/me` contract.

---

# 129. Required Endpoint Matrix

Add to:

```text
docs/api/api-contract.md
```

| ID       | Method | Path                | Actor                | Auth     | Authorization | Purpose                        |
| -------- | ------ | ------------------- | -------------------- | -------- | ------------- | ------------------------------ |
| USER-001 | GET    | `/api/v1/me`        | Customer/Staff/Admin | Required | Self          | Get own profile                |
| USER-002 | PATCH  | `/api/v1/me`        | Customer/Staff/Admin | Required | Self          | Update own profile             |
| AUTH-*   | POST   | Authentication path | Customer/Staff/Admin | Required | Self          | Credential/security operations |

Use the existing Authentication endpoint names for password/email/security operations rather than duplicating them here.

---

# 130. Customer Profile Fields Matrix

Add:

| Field         | Public |                Customer Own |       Staff |      Admin |
| ------------- | -----: | --------------------------: | ----------: | ---------: |
| ID            |     No |                         Yes |   As needed |        Yes |
| Name          |     No |                         Yes | Operational | Authorized |
| Email         |     No |                         Yes | Operational | Authorized |
| Phone         |     No |                         Yes | Operational | Authorized |
| Role          |     No |                   Read-only |   Read-only |     Manage |
| Verification  |     No |                   Read-only |   As needed | Authorized |
| Account state |     No | Read-only where appropriate |  Restricted | Authorized |
| Password      |     No |                          No |          No |         No |

---

# 131. API Response Example

Conceptual:

```json
{
  "data": {
    "id": "customer-id",
    "role": "CUSTOMER",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+255...",
    "email_verified": true,
    "created_at": "2026-09-03T08:00:00Z",
    "updated_at": "2026-09-03T08:30:00Z"
  }
}
```

Do not expose sensitive credentials.

---

# 132. Profile Update Example

Conceptual:

```http
PATCH /api/v1/me
```

```json
{
  "name": "Jane Doe",
  "phone": "+255..."
}
```

Response:

```json
{
  "data": {
    "id": "customer-id",
    "role": "CUSTOMER",
    "name": "Jane Doe",
    "phone": "+255..."
  }
}
```

The exact response structure follows the global contract.

---

# 133. Invalid Update Example

Client submits:

```json
{
  "role": "ADMIN"
}
```

Expected conceptual failure:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "This field cannot be modified.",
      "field": "role"
    }
  ]
}
```

Use the final error-code semantics from Phase 1.16.

---

# 134. Profile Error Security

Do not reveal internal permission architecture through validation errors.

Avoid messages such as:

```text
"Only users with privilege X may change field Y because policy Z failed."
```

Use safe, meaningful client-facing errors.

---

# 135. API Compatibility

Within Version 1, treat these as contract-sensitive:

```text
role
profile field names
requiredness
verification semantics
account-state semantics
```

Do not change them casually.

---

# 136. Closed Role Compatibility

Do not add roles to `/me.role` without compatibility review.

The Version 1 clients know:

```text
CUSTOMER
STAFF
ADMIN
```

---

# 137. Performance

`GET /me` is likely to be called frequently when applications initialize.

Keep it lightweight.

Do not make it automatically load:

```text
orders
notifications
cart
requests
enquiries
```

unless the architecture explicitly requires a compact summary.

---

# 138. Query Complexity

`GET /me` should not become a database-wide aggregation endpoint.

Profile retrieval should remain efficient.

---

# 139. Caching

Classify:

```text
GET /me
→ PRIVATE

PATCH /me
→ NON-CACHEABLE mutation
```

---

# 140. Security Review

Explicitly test:

```text
Customer A → Customer B profile
Customer → role modification
Customer → permission modification
Customer → account-state modification
Customer → user ID modification
Customer → verification modification
Staff → customer profile modification
Staff → customer account restriction
Admin → credential exposure
```

---

# 141. Customer Account Ownership Review

The final review must confirm:

```text
Customer owns account
Customer controls allowed profile fields
Staff does not control customer account
Admin authority is explicit
```

---

# 142. Staff/Admin Separation

The User/Profile contract must not accidentally grant Staff the same account-management capability as Admin.

The self-profile endpoint is shared.

Administrative management of other users is separate.

---

# 143. Role Management Boundary

Do not define role-management endpoints here.

Those belong to:

**Phase 1.29 — Staff/Admin Operational API Contract.**

---

# 144. Customer Management Boundary

Do not define:

```text
GET /users
GET /users/{id}
PATCH /users/{id}
```

as administrative endpoints here.

Phase 1.29 will define them if required.

---

# 145. Account Security Boundary

Do not redefine password reset/verification.

Refer back to Authentication.

---

# 146. Required Documentation Updates

## `docs/api/api-contract.md`

Add:

```text
## User/Profile API Contract

### User/Profile Resource
### Self Context
### USER-001 — Get Current User
### USER-002 — Update Current User
### Credential Boundary
### Immutable Fields
### Mutable Fields
### Role
### Verification
### Account State
### Privacy
### Cross-Platform Identity
### Caching
### Security
```

---

## `docs/api/api-resources.md`

Update:

```text
User
Profile
```

with:

```text
self-service representation
staff/admin representation boundary
ownership
relationships
read-only fields
mutable fields
sensitive fields
```

---

## `docs/api/api-conventions.md`

Add reusable principles:

```text
/me self-context
server-controlled identity
server-controlled role
credential separation
profile field allow-list
private caching
```

---

## `docs/domain/business-rules.md`

Confirm:

```text
Customers own their accounts.
Customers may update permitted profile information.
Staff do not control customer accounts.
Staff cannot arbitrarily restrict customer ordering/browsing.
Admin has highest administrative authority.
Profile changes do not alter historical Orders/Requests/Enquiries.
Saved addresses remain deferred.
```

---

## `docs/decisions.md`

Record the major decisions:

```text
### USER-001 — `/me` Is the Self-Service Identity Boundary

### USER-002 — Customer Account Ownership

### USER-003 — Role Is Server-Controlled

### USER-004 — Credential Operations Are Separate From Profile Updates

### USER-005 — Staff Cannot Control Customer Accounts

### USER-006 — Profile Does Not Embed Orders/Cart/Requests/Enquiries/Notifications

### USER-007 — Saved Address Book Is Deferred
```

Only record decisions actually accepted.

---

# 147. `AGENTS.md` Permanent Rules

Add only permanent rules such as:

```text
Never trust client-supplied user identity for self-service authorization.

Never allow profile updates to modify role, permissions, ownership, or security state.

Credential changes must use dedicated authentication/security workflows.

Customer accounts are customer-owned; Staff operational access does not confer customer-account administration.
```

---

# 148. Validation Checklist

### Self context

* [ ] `/me` is authenticated.
* [ ] `/me` refers only to current actor.
* [ ] Client cannot select another User.
* [ ] Same account works across Next.js and Flutter.

### Profile

* [ ] Own profile is readable.
* [ ] Allowed profile fields are mutable.
* [ ] Unknown fields follow Phase 1.14.
* [ ] Immutable fields are protected.
* [ ] Profile response excludes secrets.

### Roles

* [ ] `CUSTOMER`, `STAFF`, `ADMIN` are closed.
* [ ] Role is read-only through Profile.
* [ ] Customer cannot self-promote.
* [ ] Staff cannot self-promote.
* [ ] Role management is deferred to Phase 1.29.

### Customer ownership

* [ ] Customer owns own account.
* [ ] Staff cannot modify customer account through `/me`.
* [ ] Staff cannot block browsing/order access.
* [ ] Admin controls are separately defined.

### Credentials

* [ ] Password is not exposed.
* [ ] Password operations remain in Authentication.
* [ ] Email security is separated where necessary.
* [ ] Verification state is server-controlled.
* [ ] Password reset remains in Authentication.

### Resource separation

* [ ] Orders are not embedded.
* [ ] Cart is not embedded.
* [ ] Requests are not embedded.
* [ ] Enquiries are not embedded.
* [ ] Notifications are not embedded.
* [ ] Saved addresses are deferred.

### Privacy

* [ ] Profile is private.
* [ ] `/me` is not publicly cached.
* [ ] Sensitive account fields are protected.
* [ ] Staff receives only operational customer information.
* [ ] Admin does not receive plaintext credentials.

### Security

* [ ] IDOR protections are explicit.
* [ ] Mass assignment is prevented.
* [ ] Role tampering is prevented.
* [ ] Ownership tampering is prevented.
* [ ] Account-state tampering is prevented.
* [ ] Verification-state tampering is prevented.

### Cross-platform

* [ ] Website and Flutter share User identity.
* [ ] Server is authoritative.
* [ ] Local role/profile state is not authoritative.

### Compatibility

* [ ] Version 1 role values remain closed.
* [ ] Field semantics are documented.
* [ ] Profile response follows the global response contract.
* [ ] Profile errors follow the global error contract.

### Documentation

* [ ] Consolidated documents updated.
* [ ] No unnecessary User/Profile markdown files created.

---

# 149. Explicitly Out of Scope

Do NOT:

```text
Implement Laravel User model
Create migrations
Implement Sanctum
Implement authentication
Implement password hashing
Implement password reset
Implement email verification delivery
Implement Staff approval
Implement role management
Implement permissions
Implement customer administration
Implement saved addresses
Implement notification preferences
Implement payment
Build Next.js profile UI
Build Flutter profile UI
Build Admin customer-management UI
Generate final OpenAPI schemas
```

---

# 150. Definition of Done

Phase 1.28 is complete when:

1. User/Profile is defined as a Version 1 API resource.
2. `/me` is established as the self-service identity boundary.
3. `/me` is available to authenticated Customer/Staff/Admin actors.
4. Customer account ownership is explicit.
5. Customer profile mutation is restricted to approved fields.
6. Role is server-controlled.
7. Permissions are server-controlled.
8. Account status is server-controlled.
9. Verification state is server-controlled.
10. Credential operations are separated from ordinary Profile updates.
11. Password data is never exposed.
12. Staff cannot arbitrarily control Customer accounts.
13. Staff cannot arbitrarily restrict customer browsing or ordering.
14. Admin's broader account-management authority remains separate.
15. Orders are not embedded in Profile.
16. Cart is not embedded in Profile.
17. Requests are not embedded in Profile.
18. Enquiries are not embedded in Profile.
19. Notifications are not embedded in Profile.
20. Saved addresses remain deferred.
21. Website and Flutter share the same account identity.
22. Profile responses are private.
23. IDOR and mass-assignment risks are explicitly addressed.
24. Role escalation is explicitly addressed.
25. Closed Version 1 role policy remains intact.
26. Authentication remains the source of credential/security semantics.
27. Staff/Admin operational account management is deferred to Phase 1.29.
28. Consolidated documentation has been updated.
29. No implementation code has been written.

---

# 151. STOP CONDITION — Mandatory

After updating:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md where appropriate
```

and completing the ownership/security/cross-platform review:

**STOP.**

Do not implement User/Profile.

Do not create Laravel User models.

Do not create authentication middleware.

Do not create role/permission tables.

Do not implement password changes.

Do not create customer-management endpoints.

Do not build profile UI.

The next phase must be explicitly requested.

## Next phase

**Phase 1.29 — Define Staff/Admin Operational API Contract**

That phase should define the privileged operational surface that remains after all the customer-facing contracts are now established:

```text
Staff
→ receive/process Orders
→ handle Requests
→ handle Enquiries
→ operational Notifications
→ approved Catalog/Inventory operations

Admin
→ approve/manage Staff
→ highest administrative authority
→ authorized Customer/account administration
→ broader Catalog/Inventory/Order management
```

with particular attention to **least privilege, staff approval, separation of customer ownership from operational access, auditability, and preventing privilege escalation**.
