# Phase 4.6 — Profile Operations / Account Profile Synchronization

## Purpose

Implement the authenticated customer profile boundary after Clerk authentication has been established.

This phase defines and implements:

```text
Clerk
    ├── authentication identity
    ├── primary email
    ├── email verification
    ├── password
    └── sessions/security

Laravel
    ├── application User
    ├── CUSTOMER / STAFF / ADMIN role
    ├── application account state
    ├── name/profile data
    ├── optional phone/contact data
    ├── ownership
    └── commerce relationships
```

The canonical application profile endpoints remain:

```http
GET   /api/v1/me
PATCH /api/v1/me
```

Customers must never select their own identity using a request parameter or body field.

`/me` always means:

> the local Laravel User mapped to the currently authenticated Clerk identity.

---

# 1. Dependencies

Required:

* Phase 4.1 complete;
* Phase 4.2 complete;
* Phase 4.3 complete;
* Phase 4.4 complete;
* Phase 4.5 complete;
* Clerk authentication works;
* verified Clerk identity maps to `users.clerk_user_id`;
* public signup creates CUSTOMER only;
* signup requires email + password;
* phone is not required at signup;
* Clerk owns email verification;
* Laravel protected requests resolve an authenticated local User;
* no Laravel password credential exists for Clerk customers.

Do not implement profile operations until the authenticated current-user boundary works reliably.

---

# 2. Scope

Implement:

* `GET /api/v1/me`;
* `PATCH /api/v1/me`;
* profile field ownership;
* name editing;
* optional phone editing;
* local Clerk email snapshot behavior;
* email-verification snapshot behavior where required;
* profile validation;
* profile caching rules;
* synchronization responsibilities;
* application account-retention policy;
* resolution of the deferred `carts.user_id` FK delete-policy inconsistency;
* profile tests;
* synchronization tests;
* documentation updates.

Do not build final Next.js or Flutter profile UI.

Do not implement arbitrary admin customer management.

---

# 3. Authoritative Inputs

Before implementation review:

1. `AGENTS.md`
2. Phase 4.1 Clerk ADR
3. Phase 4.2 local provisioning implementation
4. Phase 4.3 authentication implementation
5. Phase 4.4 security decisions
6. Phase 4.5 email-verification decisions
7. `docs/api/api-contract.md`
8. `docs/api/api-resources.md`
9. `docs/api/api-conventions.md`
10. `docs/api/openapi.yaml`
11. `docs/domain/business-rules.md`
12. `docs/decisions.md`
13. Group C User/profile migrations and models
14. current Clerk documentation.

If Clerk MCP/Skills are installed, use them to verify current behavior.

Do not invent provider methods from memory.

---

# 4. Profile Authority Matrix

Establish this as the default ownership model:

| Field                      | Authority             | Ordinary `/me` writable? |
| -------------------------- | --------------------- | ------------------------ |
| `users.id`                 | Laravel               | no                       |
| `clerk_user_id`            | Clerk/Laravel mapping | no                       |
| primary email              | Clerk                 | no                       |
| email verification         | Clerk                 | no                       |
| password                   | Clerk                 | no                       |
| sessions                   | Clerk                 | no                       |
| name                       | Laravel profile       | yes                      |
| phone                      | Laravel profile       | yes, optional            |
| role                       | Laravel RBAC          | no                       |
| permissions                | Laravel               | no                       |
| application account state  | Laravel               | no                       |
| created/updated timestamps | Laravel               | no                       |

Do not create two authoritative writers for the same field.

---

# 5. Canonical Profile Model

The customer application profile should conceptually contain:

```text
id
email
email_verified
name
phone
role
account state where approved
timestamps where approved
```

Use the actual frozen V1 representation.

Do not casually add fields merely because Clerk exposes them.

Do not return the entire Clerk User object.

---

# 6. Email Is Read-Only Application Identity Data

The authenticated customer may see their current application email through:

```http
GET /api/v1/me
```

but ordinary:

```http
PATCH /api/v1/me
```

must not accept:

```json
{
  "email": "new@example.com"
}
```

as a direct profile mutation.

Authentication email changes require a dedicated Clerk security workflow.

Do not turn email into an ordinary Laravel profile field.

---

# 7. Email Change Future Boundary

The eventual email-change sequence should remain:

```text
authenticated customer
        ↓
Clerk reverification/security flow
        ↓
new email added
        ↓
new email verified
        ↓
new primary Clerk email
        ↓
Laravel email snapshot reconciled
```

Do not implement a parallel Laravel verification process.

If email change is outside the current UI scope, document it and defer the client experience.

---

# 8. Email Snapshot

If Laravel retains:

```text
users.email
```

classify it as:

```text
Clerk-owned local snapshot
```

It is useful for:

* application display;
* internal communication context;
* snapshots where appropriate;
* avoiding repeated Clerk Backend API calls.

It is **not** the identity-linking key.

Identity remains:

```text
users.clerk_user_id
```

---

# 9. Email Synchronization

Do not call Clerk's Backend User API on every:

```http
GET /me
```

just to obtain email.

Use the local synchronized snapshot unless the established synchronization policy requires refresh.

A suitable synchronization model is:

```text
initial JIT provisioning
        +
bounded explicit reconciliation
        +
future user.updated webhook
```

Do not make normal profile reads dependent on Clerk network availability.

---

# 10. Clerk Webhook Consistency

Remember:

```text
Clerk user.updated webhook
```

is asynchronous and eventually consistent.

Therefore:

* webhooks may repair/update local Clerk-owned snapshots;
* webhooks must not be required for synchronous authenticated request correctness;
* profile/domain operations must continue working if webhook delivery is delayed.

Do not wait for a webhook before completing a Laravel-owned name/phone update.

---

# 11. Name Ownership

The application profile owns:

```text
name
```

unless the Phase 4.1 ownership matrix explicitly selected Clerk instead.

For the current V1 model, treat name as application profile data.

The customer may update it through:

```http
PATCH /api/v1/me
```

Name does not need to have been collected during Clerk signup.

---

# 12. Name Validation

Apply clear validation.

At minimum:

* string;
* trimmed;
* non-empty when supplied;
* reasonable maximum length;
* reject control characters where existing validation conventions do so;
* no HTML interpretation.

Do not silently store whitespace-only names.

Do not invent a name from the email address.

---

# 13. Name Requirement and Existing Contract

Inspect the current V1 User/Profile schema.

If the frozen representation currently requires:

```text
name = non-null
```

while email/password-only signup allows a customer to exist before providing a name, resolve this explicitly.

Options must follow the project's post-freeze change process.

Do not:

```text
fake name = email prefix
```

or:

```text
name = "Customer"
```

merely to satisfy a schema.

Preferred architectural direction:

```text
signup authentication
→ email + password

application profile
→ name supplied later
```

If this requires making name nullable until profile completion, document and implement that intentionally.

---

# 14. Phone Ownership

Phone is application profile/contact data.

It is **not**:

* a signup requirement;
* authentication identity;
* a Clerk login requirement;
* an email-verification substitute;
* ownership proof.

The customer may optionally set/update it through:

```http
PATCH /api/v1/me
```

---

# 15. Phone Nullability

Phone must support:

```text
null
```

for customers who have never provided one.

Do not require:

```text
phone
```

just because earlier Group A assumptions expected it during signup.

If the current schema prevents null phone values, create a **new migration**.

Do not edit an already-applied Group C migration.

---

# 16. Phone Validation

When supplied, validate according to the project's chosen phone format.

At minimum:

* string;
* bounded length;
* normalize consistently where policy exists;
* reject obvious malformed values;
* do not trust it as verified authentication data.

Do not silently assign:

```text
phone_verified = true
```

because the user entered a phone number.

---

# 17. Do Not Sync Application Phone to Clerk by Default

Because phone is not an authentication identifier in V1:

```text
PATCH /me { phone: ... }
```

should update the Laravel application profile.

Do not automatically create/update a Clerk phone identifier unless a later authentication requirement explicitly needs it.

Avoid creating two writers.

---

# 18. `/me` GET

Implement or finalize:

```http
GET /api/v1/me
```

Requirements:

* authentication required;
* user resolved from Clerk authentication context;
* no user ID argument;
* return only the current local user's approved profile representation;
* private/no-store caching;
* no orders/cart/notifications embedded;
* no Clerk secrets;
* no `clerk_user_id` unless explicitly approved;
* no permission internals beyond approved public role representation.

Keep it lightweight.

---

# 19. `/me` PATCH

Implement:

```http
PATCH /api/v1/me
```

as a strict partial update.

Approved ordinary mutable fields:

```text
name
phone
```

unless current contract has an even smaller approved set.

Do not accept arbitrary user fields.

---

# 20. Strict Allow-List

For example:

```text
allowed:
- name
- phone

forbidden/server-controlled:
- id
- clerk_user_id
- email
- email_verified
- password
- role
- permissions
- account_state
- is_active
- timestamps
```

Unknown fields must follow the project's strict validation convention.

Do not silently ignore privilege-related fields.

---

# 21. Use Validated Data Only

Follow the project rule:

```text
FormRequest::validated()
        ↓
DTO / command
        ↓
profile service
```

Do not use:

```php
$request->all()
```

Do not mass assign the entire request into User/Profile.

---

# 22. Partial PATCH Semantics

A PATCH request changes only supplied fields.

Example:

```json
{
  "name": "Asha M."
}
```

must leave phone untouched.

Omitted:

```text
phone
```

does not mean:

```text
phone = null
```

---

# 23. Explicit Phone Clearing

If customers are allowed to remove their stored optional phone:

```json
{
  "phone": null
}
```

may explicitly clear it.

Do not treat omission as clearing.

Name should not be clearable to null if the final profile contract requires a name once set/completed.

Follow the final schema decision.

---

# 24. Profile Update Service

Keep controller logic small.

Prefer an application service such as:

```text
UpdateCustomerProfile
```

or the existing project naming convention.

Responsibilities:

* receive authenticated local User;
* receive validated mutable profile fields;
* apply only approved fields;
* persist atomically;
* return refreshed profile representation.

It must not interact with passwords or sessions.

---

# 25. Do Not Make User Model a Service Layer

Avoid:

```php
$user->updateEverythingFromRequest(...)
```

containing:

* validation;
* Clerk API calls;
* role decisions;
* email security changes;
* profile mutation.

Keep model responsibilities focused.

---

# 26. Profile Response Source

After successful update, serialize from authoritative Laravel state.

Do not return the request body as if it were persisted.

Do not require another Clerk API call after changing Laravel-owned name/phone.

---

# 27. Clerk Profile Fields

Clerk may itself contain fields such as:

```text
first name
last name
image/avatar
phone
```

Do not automatically make them authoritative merely because they exist in Clerk's User object.

Phase 4.1 selected a split authority model.

Sync only fields explicitly owned by Clerk.

---

# 28. Avoid Full Clerk User Mirroring

Do not add columns for every Clerk property.

Avoid storing:

```text
Clerk public metadata
unsafe metadata
external accounts
full email array
full phone array
profile image metadata
session data
```

unless a concrete application requirement exists.

Store only what the Laravel domain needs.

---

# 29. Profile Image

Do not introduce avatar/profile-image support merely because Clerk supports profile images.

Unless V1 requirements already include customer avatar:

```text
profile image
```

is out of scope.

Do not expand the profile contract unnecessarily.

---

# 30. Role Representation

If `/me` returns:

```text
role
```

its value comes from Laravel RBAC.

Do not return Clerk metadata as role.

Canonical values remain:

```text
CUSTOMER
STAFF
ADMIN
```

---

# 31. Permissions Exposure

Do not expose the entire permission table to customers unless already explicitly required.

Frontend authorization cues can use approved role/capability information, but backend remains authoritative.

Do not leak internal policy structure unnecessarily.

---

# 32. Profile Update Cannot Change Role

Mandatory security invariant:

```json
{
  "role": "ADMIN"
}
```

must never elevate the user.

Likewise:

```json
{
  "permissions": ["*"]
}
```

must not modify RBAC.

Reject according to strict input rules.

---

# 33. Account State Cannot Be Profile-Edited

Customers must not submit:

```json
{
  "is_active": true,
  "account_state": "ACTIVE"
}
```

to reactivate themselves.

Account state is server-controlled.

---

# 34. Verification State Cannot Be Profile-Edited

Reject:

```json
{
  "email_verified": true
}
```

Verification remains Clerk-controlled.

---

# 35. External Identity Cannot Be Profile-Edited

Reject:

```json
{
  "clerk_user_id": "user_other"
}
```

A customer must never rebind their local account through ordinary profile mutation.

---

# 36. Profile Authorization

`/me` uses the authenticated principal.

Do not query:

```text
User::find($request->user_id)
```

for self-service.

Canonical flow:

```text
Clerk token
    ↓
authenticated local User
    ↓
/me
```

This avoids IDOR by design.

---

# 37. Profile Caching

`GET /me` must remain:

```http
Cache-Control: private, no-store
```

or the exact stronger existing project header policy.

Never allow:

```text
/me
```

into CDN/shared caches.

`PATCH /me` is non-cacheable.

---

# 38. Public Catalog Separation

Do not attach profile queries to public catalog endpoints merely because a user might be signed in.

Public catalog remains customer-state-free.

Avoid:

```text
GET /products
→ automatically load authenticated User profile
```

unless a future personalization requirement explicitly needs it.

---

# 39. Application Contact vs Order Contact

Do not assume:

```text
profile phone
=
every order recipient phone
```

At checkout, recipient/contact information remains an order snapshot.

Customers may use a different delivery recipient.

Profile phone is convenience/application contact data only.

---

# 40. Application Name vs Order Recipient

Likewise:

```text
profile name
```

must not overwrite historical:

```text
order recipient_name
```

Historical order snapshots remain immutable.

---

# 41. Request / Enquiry Contact Snapshots

Changing:

```text
/me phone
/me name
```

must not rewrite historical furniture requests or enquiries.

Those records preserve their submitted contact snapshots.

Do not cascade profile edits into historical communications.

---

# 42. Profile Synchronization Direction

Define explicit directions:

```text
Clerk → Laravel:
- clerk_user_id
- primary email snapshot
- email verification snapshot if locally retained

Laravel only:
- name
- phone
- role
- permissions
- account state
```

Avoid bidirectional automatic sync.

---

# 43. `user.updated` Future Reconciliation

For Clerk-owned identity fields, a future:

```text
user.updated
```

webhook may reconcile:

```text
primary email
email verification
```

only.

It must not overwrite:

```text
name
phone
role
account state
```

if those are Laravel-owned.

Keep synchronization field-specific.

---

# 44. Webhook Implementation Scope

If Clerk webhook infrastructure has not yet been implemented:

do not force its complete implementation into Phase 4.6 unless the roadmap/Phase 4.1 explicitly assigned it here.

Document the synchronization contract now.

Implementation may be deferred to the appropriate integration/security stage.

Profile correctness must not require immediate webhook delivery.

---

# 45. Bounded Synchronous Reconciliation

If there is a legitimate point where Clerk-owned email data must be refreshed synchronously, isolate it in an explicit reconciliation service.

Do not scatter:

```text
ClerkUserGateway::getUser()
```

across controllers.

A possible service:

```text
ReconcileClerkIdentitySnapshot
```

Responsibilities:

* fetch trusted Clerk User;
* compare only Clerk-owned fields;
* update local snapshot;
* never modify Laravel-owned profile/RBAC fields.

---

# 46. Do Not Reconcile on Every Request

Avoid:

```text
every GET /me
→ Clerk Backend API
```

This adds:

* latency;
* rate-limit dependency;
* external outage dependency;
* unnecessary complexity.

Use local application data for routine reads.

---

# 47. Synchronization Conflict Rule

For Clerk-owned fields:

```text
Clerk wins
```

For Laravel-owned fields:

```text
Laravel wins
```

Do not use generic:

```text
last write wins
```

across systems.

Authority is field-specific.

---

# 48. Customer Account Retention Policy

This phase must finally resolve the deferred user-retention issue identified after Group C.

The application contains historical records linked to users:

```text
orders
order history
payments indirectly
requests
enquiries
notifications
carts
```

Therefore the V1 policy should be:

> Do not hard-delete the local Laravel User merely because the Clerk identity is deleted or the customer requests account closure when historical commerce records require retention.

Preserve referential and financial history.

---

# 49. Authentication Account vs Historical Application Record

Distinguish:

```text
Clerk account
```

from:

```text
Laravel historical customer record
```

Deletion of authentication identity means:

```text
customer can no longer authenticate through that Clerk identity
```

It does not mean:

```text
erase historical orders/payments automatically
```

unless a separately approved privacy/legal policy permits and coordinates such deletion.

---

# 50. Clerk User Deletion

Clerk supports deleting a User, and Clerk may emit:

```text
user.deleted
```

for synchronization.

Do not map this event directly to:

```sql
DELETE FROM users
```

in Laravel.

Target concept:

```text
Clerk identity deleted
        ↓
local user retained
        ↓
external identity detached/deactivated according to policy
        ↓
historical commerce preserved
```

The exact privacy/anonymization strategy should be documented.

---

# 51. Account Closure Policy

For V1, define account closure as a security/business operation, not an ordinary profile PATCH.

Do not support:

```json
PATCH /me
{
  "deleted": true
}
```

Account deletion/closure requires a dedicated workflow if exposed.

If no account deletion endpoint exists in the frozen contract:

document the retention policy now and defer the user-facing closure workflow.

Do not invent an endpoint casually.

---

# 52. Account Deletion and Clerk Ordering

If future self-service account deletion is introduced, never use a fragile flow such as:

```text
delete Clerk user first
→ Laravel cleanup fails
→ impossible to authenticate/retry
```

Design a coordinated operation.

Possible architecture:

```text
authenticated + reverified customer
        ↓
mark application account closure pending
        ↓
apply permitted local cleanup/anonymization
        ↓
preserve required historical records
        ↓
delete/revoke Clerk identity
        ↓
finalize local closed state
```

Exact implementation is outside this phase unless already contracted.

---

# 53. Resolve `carts.user_id` FK Mismatch

The previously recorded Group C issue is:

```text
MySQL:
carts.user_id → RESTRICT

SQLite:
carts.user_id → SET NULL
```

Phase 4.6 must now decide the canonical policy.

Because local users with historical commerce are retained rather than normally hard-deleted, the preferred V1 policy is:

```text
users are retained
→ cart FK does not need to preserve carts by nulling owner during normal account closure
```

Canonicalize the FK behavior consistently.

---

# 54. Recommended Cart FK Policy

For V1, prefer:

```text
carts.user_id
ON DELETE RESTRICT
```

on all supported databases.

Rationale:

* authenticated carts belong to a real application User;
* application users with historical state are not routinely hard-deleted;
* silently orphaning an authenticated cart into `user_id = null` conflicts with the cart ownership XOR model unless a valid guest credential also exists;
* a customer cart must not become an unauthenticated guest cart simply because a User row disappears.

Therefore:

```text
SET NULL
```

is unsafe unless the cart is deliberately transformed into a valid guest cart.

Do not perform that transformation implicitly.

---

# 55. FK Correction Migration

If SQLite currently creates:

```text
ON DELETE SET NULL
```

while MySQL uses:

```text
RESTRICT
```

create a **new migration** to unify behavior under the current migration/test strategy.

Do not edit already-applied Group C migrations.

Test both intended schema semantics.

SQLite test limitations do not permit retaining a weaker alternative here. The migration must establish `ON DELETE RESTRICT` on every supported driver, and fresh-schema verification must assert that exact action on every supported driver.

Do not silently ignore or document away a cross-driver schema mismatch.

---

# 56. User Hard Delete Service

Do not expose generic:

```php
$user->delete();
```

through ordinary profile operations.

Any future hard deletion must verify:

* historical relationships;
* retention requirements;
* carts;
* orders;
* requests/enquiries;
* notifications;
* Clerk state;
* audit/security requirements.

For V1, normal customer closure should not hard-delete the local row.

---

# 57. Account Anonymization

Do not implement broad anonymization without explicit policy.

If future privacy requirements demand anonymization:

separate:

```text
data required for legal/transaction records
```

from:

```text
optional personal profile data
```

and design a dedicated workflow.

Do not alter historical order snapshots casually.

---

# 58. Profile Completion State

Because signup requires only:

```text
email
password
```

determine whether the application needs an explicit:

```text
profile complete
```

state.

Do not add one automatically.

If no business operation requires name/phone before use, avoid unnecessary state.

If checkout later requires recipient name/phone, collect those as checkout fields.

Keep signup simple.

---

# 59. Do Not Block Browsing on Profile Completion

A customer should not need:

```text
name
phone
```

merely to browse the public catalog.

Do not add profile-completion gates to public browsing.

---

# 60. Do Not Block Authentication Because Phone Is Missing

Mandatory invariant:

```text
phone = null
```

must not cause:

```text
login failure
session rejection
/me authentication failure
```

Phone is optional profile data.

---

# 61. Checkout Profile Independence

Do not make checkout necessarily depend on:

```text
users.phone
```

The checkout phase should validate required recipient/delivery contact independently.

A customer may:

```text
profile phone = null
```

and still provide:

```text
recipient_phone
```

during checkout where required.

---

# 62. Profile Update Error Semantics

Use the existing API error envelope.

Examples:

```text
invalid name
→ 422 INVALID_VALUE

invalid phone
→ 422 INVALID_FORMAT / INVALID_VALUE

role supplied
→ 422 INVALID_VALUE or approved server-field rejection

unauthenticated
→ 401 AUTHENTICATION_REQUIRED
```

Use exact existing codes.

Do not create profile-specific error-code explosion.

---

# 63. Profile IDOR Protection

`/me` inherently avoids resource-ID selection.

Do not add:

```http
PATCH /users/{id}
```

as an alias for customer self-service.

Administrative user management remains separate.

---

# 64. STAFF Profile

The `/me` concept may also represent the currently authenticated Staff/Admin's own profile according to existing contract.

Do not allow:

```text
Staff /me
```

to turn into customer-account management.

Each authenticated actor owns only their own `/me` context.

---

# 65. Staff Cannot Edit Customer Profile

Do not create ordinary STAFF capability to:

* change customer name;
* change customer phone;
* change customer email;
* change customer account state;
* change customer security fields.

Any future admin customer-management functionality belongs to Group K and requires explicit policy.

---

# 66. Admin Profile vs Customer Management

Keep:

```text
Admin PATCH /me
```

separate from:

```text
Admin manages another user
```

Do not overload `/me`.

`/me` always means current actor.

---

# 67. Profile Serialization

Use a dedicated Resource/Transformer if the project already follows that pattern.

Do not serialize Eloquent User wholesale.

Explicitly select allowed fields.

Never expose:

```text
clerk_user_id
password
remember_token
internal permission pivots
Clerk secrets
provider metadata
```

through accidental model serialization.

---

# 68. Email Verification Representation

If `/me` includes:

```text
email_verified
```

serialize the trusted local snapshot or the approved trusted state.

Do not infer:

```text
email_verified = email != null
```

Do not let profile PATCH mutate it.

---

# 69. Local Timestamps

If API includes:

```text
created_at
updated_at
```

follow existing ISO8601 conventions.

Profile update should change local `updated_at` only for local persisted changes.

Do not pretend Clerk email changes occurred at Laravel update time unless actually reconciled.

---

# 70. Tests — GET `/me`

Given authenticated CUSTOMER:

verify:

* 200;
* correct local User;
* approved fields only;
* no `clerk_user_id`;
* no password/security secrets;
* private cache headers.

---

# 71. Tests — Missing Authentication

```http
GET /api/v1/me
```

without authentication:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 72. Tests — Update Name

Authenticated customer:

```json
{
  "name": "David M."
}
```

Verify:

* name updated;
* phone unchanged;
* email unchanged;
* role unchanged;
* Clerk identity unchanged.

---

# 73. Tests — Update Phone

Authenticated customer:

```json
{
  "phone": "<valid test phone>"
}
```

Verify:

* local profile phone updated;
* Clerk authentication identity not modified;
* role/email unchanged.

Use synthetic test numbers.

---

# 74. Tests — Clear Phone

If clearing is approved:

```json
{
  "phone": null
}
```

Verify:

```text
phone = null
```

and authentication continues normally.

---

# 75. Tests — No Phone Profile

Customer with:

```text
phone = null
```

must:

* authenticate;
* GET `/me`;
* use ordinary authenticated APIs not requiring recipient phone.

This is a required regression test from Phase 4.5.

---

# 76. Tests — Partial PATCH

Initial:

```text
name = A
phone = P
```

PATCH:

```json
{
  "name": "B"
}
```

Expected:

```text
name = B
phone = P
```

---

# 77. Tests — Role Tampering

Submit:

```json
{
  "role": "ADMIN"
}
```

Verify no role change.

Use the exact approved validation response.

---

# 78. Tests — Permission Tampering

Submit:

```json
{
  "permissions": ["*"]
}
```

Verify no authorization change.

---

# 79. Tests — Clerk Identity Tampering

Submit:

```json
{
  "clerk_user_id": "user_other"
}
```

Verify mapping remains unchanged.

---

# 80. Tests — Email Mutation Rejected

Submit:

```json
{
  "email": "attacker@example.test"
}
```

through ordinary `/me`.

Verify:

* local email not changed;
* Clerk email not changed;
* request rejected according to contract.

---

# 81. Tests — Verification Mutation Rejected

Submit:

```json
{
  "email_verified": true
}
```

Verify no change.

---

# 82. Tests — Account State Mutation Rejected

Submit:

```json
{
  "account_state": "ACTIVE"
}
```

Verify customer cannot modify server-controlled state.

---

# 83. Tests — User ID Selection Rejected

Attempt:

```http
GET /api/v1/me?user_id=<other-user>
```

or applicable mutation body.

Ensure it cannot select another user.

---

# 84. Tests — Historical Order Snapshot

Update profile:

```text
name
phone
```

Verify historical:

```text
order recipient_name
order recipient_phone
```

remain unchanged.

---

# 85. Tests — Request/Enquiry Snapshot

Update profile contact information.

Verify existing request/enquiry snapshots remain unchanged.

---

# 86. Tests — Email Reconciliation

Using a fake Clerk integration:

initial:

```text
clerk_user_id = user_A
email = old@example.test
```

trusted Clerk reconciliation:

```text
primary email = new@example.test
```

Verify:

* same local `users.id`;
* email snapshot updated;
* role/name/phone untouched.

---

# 87. Tests — Clerk Sync Cannot Overwrite Laravel Profile

Fake a Clerk `user.updated` representation containing:

```text
first_name
phone
metadata.role
```

alongside email.

Verify reconciliation updates only approved Clerk-owned fields.

It must not modify:

```text
local name
local phone
local role
permissions
account state
```

---

# 88. Tests — FK Retention Policy

Verify customer/User deletion cannot silently orphan an authenticated cart.

If canonical FK is:

```text
RESTRICT
```

test that deletion with dependent cart is rejected.

Do not transform it into a guest cart.

---

# 89. Tests — Historical User Retention

Create:

```text
User
→ Order
```

Attempt ordinary hard deletion.

Verify historical relationship prevents unsafe deletion or application layer denies operation according to the new retention policy.

---

# 90. Tests — Clerk Account Deleted

Using fakes, simulate:

```text
Clerk identity deleted
```

Verify the chosen local retention behavior:

* historical User record retained;
* Orders preserved;
* no authorization elevation;
* identity cannot be silently rebound by email.

Do not require a real Clerk deletion.

---

# 91. Offline Tests

Normal test suite must not call Clerk.

Use:

```text
FakeClerkUserGateway
FakeIdentityReconciler
```

or existing equivalents.

Keep tests deterministic.

---

# 92. Profile Factory Updates

Update factories only as necessary.

Support valid cases such as:

```text
customer with name + phone
customer with name + no phone
customer with minimal Clerk identity
staff
admin
```

Do not require fake phone for every customer.

---

# 93. Name Nullability Migration

Inspect the schema.

If `users.name` or the relevant profile name field is NOT NULL solely because old signup required a name, and the new model allows email/password signup without it:

resolve it intentionally.

Possible approach:

```text
name nullable until profile provided
```

through a new migration.

Do not insert fabricated names.

If the frozen API requires non-null name, follow the post-freeze contract process before changing representation.

---

# 94. Phone Nullability Migration

Likewise, if any customer-profile phone column is non-null solely due to the old signup policy:

make it nullable through a new migration.

Do not alter unrelated recipient/contact columns.

---

# 95. Data Migration

If schema changes require existing data transformation:

* preserve valid existing names/phones;
* do not overwrite data;
* do not create fake placeholders;
* ensure migration rollback is safe where practical.

---

# 96. No Saved Address Book

Do not introduce saved customer addresses during profile work.

Saved address book remains deferred.

Delivery address is handled in checkout/order flows.

Do not expand Phase 4.6 into address management.

---

# 97. No Marketing Preferences

Do not add:

```text
marketing_email
sms_opt_in
promotional_preferences
```

to profile.

Notification preferences belong to Group R.

Marketing remains a separate future concern.

---

# 98. No Avatar

Do not add profile image/avatar unless explicitly required later.

Keep V1 profile minimal.

---

# 99. No Date of Birth / Gender

Do not collect unnecessary customer personal data.

Data minimization remains a project security principle.

---

# 100. Profile Service Performance

`GET /me` should be cheap.

Do not eager load:

```text
orders
notifications
cart
requests
enquiries
payments
```

as part of profile retrieval.

Those have their own endpoints.

---

# 101. Database Query

Current-user lookup should already occur at authentication resolution.

Avoid duplicate queries where possible.

If middleware attaches the local User, `/me` should reuse it.

Do not re-query Clerk identity unnecessarily.

---

# 102. Race Conditions

Concurrent profile PATCH operations must not allow forbidden fields or inconsistent mass assignment.

For simple name/phone updates, normal database atomic update semantics are sufficient unless existing versioning rules specify otherwise.

Do not add complex optimistic locking without a demonstrated requirement.

---

# 103. Email Sync Race

If a Clerk email reconciliation and Laravel name update happen concurrently:

they should update distinct owned fields.

Avoid generic whole-row replacement such as:

```php
$user->fill($externalUser)->save();
```

which could overwrite application fields.

Use field-specific updates.

---

# 104. Error and Transaction Boundaries

Follow:

```text
Transport
→ Schema
→ Authentication
→ Authorization
→ Domain
→ Persistence
```

Profile updates do not require external Clerk calls for Laravel-owned name/phone changes.

Do not put unnecessary external operations inside the DB transaction.

---

# 105. Logging

May log:

```text
request_id
local user id
profile update success/failure
fields changed by category
```

Do not log:

```text
Authorization token
Clerk secret
full Clerk payload
password
full sensitive personal profile values unnecessarily
```

Prefer:

```text
fields_changed = [name, phone]
```

rather than logging the actual new phone.

---

# 106. Audit

Ordinary customer profile name/phone edits do not require a heavyweight permanent audit log unless existing project rules say otherwise.

Security-sensitive changes such as:

```text
email
role
account state
```

remain dedicated operations and may require audit later.

Do not create the complete audit subsystem here.

---

# 107. Caching

Test headers for:

```http
GET /me
```

according to existing private-cache convention.

At minimum no shared caching.

Profile responses must never be served across users.

---

# 108. Documentation Updates

Update relevant consolidated docs:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

only where necessary.

Document:

* email/password signup;
* phone optional;
* profile mutable fields;
* Clerk vs Laravel ownership;
* email snapshot synchronization;
* retention policy;
* cart FK policy;
* account deletion boundaries.

Do not create unnecessary permanent phase documents.

---

# 109. Update Field Ownership Documentation

Record clearly:

```text
Clerk authoritative:
- external identity
- email
- verification
- password
- sessions

Laravel authoritative:
- name
- phone
- role
- permissions
- account state
- commerce ownership
```

This should become the reference for future frontend work.

---

# 110. OpenAPI `/me`

Ensure OpenAPI describes:

```http
GET /api/v1/me
PATCH /api/v1/me
```

with correct Clerk bearer security requirement.

`PATCH /me` request schema should contain only ordinary mutable profile fields.

Do not leave obsolete:

```text
password
email
role
email_verified
```

as writable fields.

---

# 111. Profile Request Schema

A suitable conceptual schema is:

```text
UpdateProfileRequest:
  additionalProperties: false

  name:
    optional

  phone:
    optional / nullable
```

Use exact existing project field names and validation constraints.

Do not invent aliases such as:

```text
displayName
phoneNumber
```

if API uses snake_case.

---

# 112. Contract Compatibility

If nullable name/phone requires changing the frozen V1 response schema:

follow the documented post-freeze change process.

Do not silently change:

```text
string → nullable string
```

without recording it.

Clerk adoption and the email/password-only signup decision provide the rationale, but the compatibility process still applies.

---

# 113. Cart FK Documentation

Record the final chosen rule in:

```text
docs/decisions.md
docs/domain/business-rules.md
```

or the appropriate existing location.

Example:

```text
Authenticated cart ownership does not degrade to guest ownership when a User is deleted.

V1 local Users with commerce history are retained.

carts.user_id uses restrictive deletion semantics.
```

---

# 114. Group K Handoff

Group K customer management must inherit the retention policy.

Admin must not later implement:

```text
DELETE customer
```

that violates:

* historical orders;
* cart ownership;
* payment history;
* request/enquiry retention.

Carry the decision forward explicitly.

---

# 115. Clerk Deletion Handoff

If Clerk `user.deleted` webhook support is implemented later, its handler must obey this Phase 4.6 decision.

It may:

```text
mark external identity unavailable
update local account lifecycle
perform approved anonymization
```

but must not blindly delete Laravel history.

---

# 116. Security Tests

At minimum add regression coverage for:

```text
role tampering
email tampering
verification tampering
clerk_user_id tampering
account-state tampering
cross-user profile access
mass assignment
private cache headers
```

Profile editing is a security-sensitive boundary.

---

# 117. Static Analysis / Formatting

All changes must pass:

```text
PHPStan/Larastan level 5
Laravel Pint
PHPUnit
```

Do not suppress type issues broadly.

---

# 118. Fresh Migration

If any migration changes:

```text
name nullable
phone nullable
cart FK delete policy
```

rerun:

```bash
php artisan migrate:fresh --seed
```

The entire schema must remain rebuildable from zero.

---

# 119. Database Engine Review

Because the cart FK inconsistency was specifically MySQL vs SQLite:

verify the new migration/schema intent against both where practical.

SQLite remains canonical CI unless Group U later introduces MySQL CI.

Do not introduce MySQL-only test assumptions into the canonical suite.

---

# 120. Existing MySQL Harness Failures

Do not attempt to solve the previously recorded nine MySQL-only harness failures here:

```text
raw PRAGMA
pcntl-fork connection loss
raw DROP CHECK
```

They remain Group U concerns if MySQL-backed CI is introduced.

Only verify the intended FK schema as far as the current harness safely permits.

---

# 121. ReferenceGenerator Work

Do not mix the deferred reference-generator cleanup into profile work.

Keep Phase 4.6 focused.

---

# 122. Code Quality

Maintain:

* cognitive complexity ≤15;
* maximum 3 return statements where practical;
* strict DTO/request boundaries;
* small services;
* explicit naming;
* minimal comments;
* no giant User service;
* no magic strings;
* centralized profile serialization;
* centralized identity reconciliation.

Do not duplicate Clerk integration logic.

---

# 123. Expected Files

Depending on existing structure, likely changes include some subset of:

```text
app/Http/Controllers/...
app/Http/Requests/...
app/Http/Resources/...
app/Services/...
app/Models/User.php
app/Models/CustomerProfile.php
database/migrations/...
database/factories/...
routes/api.php
tests/Feature/...
tests/Unit/...
docs/...
```

Reuse existing abstractions rather than introducing duplicates.

---

# 124. Commands / Verification

From:

```text
backend/laravel/
```

run at minimum:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If schema changed:

```bash
php artisan migrate:fresh --seed
```

must also pass.

Use existing repository scripts where defined.

---

# 125. Files Changed Report

At completion provide:

## Files changed

Exact paths.

## Schema changes

Identify:

* name nullability if changed;
* phone nullability if changed;
* cart FK correction if changed.

## API changes

Identify `/me` implementation/contract changes.

## Clerk synchronization

Explain what is synchronized and in which direction.

## Tests

List added/updated tests.

## Commands

Exact commands and results.

## Known risks

Remaining profile/account lifecycle concerns.

## Deferred

Explicitly identify later Group D/Group K/frontend work.

---

# 126. Definition of Done

Phase 4.6 is complete when:

* `GET /api/v1/me` works for authenticated users;
* `PATCH /api/v1/me` supports only approved application profile fields;
* name is Laravel-owned;
* phone is Laravel-owned and optional;
* phone is not required for signup/authentication;
* email is Clerk-owned and read-only through ordinary profile operations;
* email verification is Clerk-owned;
* `clerk_user_id` cannot be modified by clients;
* role/permissions/account state cannot be modified through `/me`;
* partial PATCH semantics are correct;
* unknown/server-controlled fields are rejected;
* profile responses are private/no-store;
* Clerk data is not fetched on every `/me`;
* synchronization ownership is field-specific;
* delayed webhook delivery cannot break profile correctness;
* historical Orders/Requests/Enquiries are not rewritten by profile edits;
* local customer retention policy is documented;
* Clerk deletion does not imply automatic Laravel hard deletion;
* authenticated cart ownership is not converted into guest ownership;
* the `carts.user_id` delete-policy mismatch has an explicit canonical resolution;
* any required new migration preserves fresh rebuild;
* offline tests pass;
* PHPUnit passes;
* Pint passes;
* PHPStan passes;
* Composer audit passes;
* documentation/OpenAPI match implementation.

---

# 127. Out of Scope

Do not implement:

* website profile UI;
* Flutter profile UI;
* password change;
* password recovery;
* phone authentication;
* SMS verification;
* saved addresses;
* avatars;
* marketing preferences;
* notification preferences;
* full Clerk webhook subsystem unless specifically assigned;
* full self-service account deletion UI;
* broad data anonymization;
* Admin customer-management APIs;
* Staff customer editing;
* full audit-log infrastructure.

---

# 128. STOP Condition

STOP when the application's `/me` profile boundary, Clerk/Laravel field ownership, optional phone behavior, synchronization rules, account-retention policy, and cart-user FK deletion policy are implemented/documented and all Phase 4.6 checks pass.

Do not continue automatically.

The next roadmap phase is:

**Phase 4.7 — API Authentication for Mobile / Flutter Clerk Boundary**
