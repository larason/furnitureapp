# Phase 1.33 — API Contract Security Review

## 1. Purpose

Perform a formal security review of the complete Version 1 API contract and OpenAPI specification.

This phase validates that the API contract is secure **by design**, before Laravel implementation begins.

The review must identify and correct contract-level weaknesses involving:

* authentication
* authorization
* ownership
* horizontal privilege escalation
* vertical privilege escalation
* IDOR
* mass assignment
* state manipulation
* financial tampering
* inventory tampering
* replay attacks
* duplicate requests
* enumeration
* sensitive-data exposure
* attachment access
* customer-account boundaries
* Staff/Admin boundaries
* rate limiting
* audit integrity
* concurrency
* error disclosure
* client/server trust boundaries

This is a security review of the **API contract**, not a penetration test of an implemented application.

No production backend code should be required.

---

# 2. Dependencies

Treat the following as authoritative:

* Phase 1.16 — API Error Contract
* Phase 1.17 — Authentication Contract
* Phase 1.18 — Authorization and Permission Contract
* Phase 1.19 — Concrete API Endpoint Inventory
* Phase 1.20 — Catalog API Contract
* Phase 1.21 — Cart API Contract
* Phase 1.22 — Checkout API Contract
* Phase 1.23 — Order API Contract
* Phase 1.24 — Order Tracking and Fulfillment API Contract
* Phase 1.25 — Made-to-Order Request API Contract
* Phase 1.26 — General Enquiry API Contract
* Phase 1.27 — Notification API Contract
* Phase 1.28 — User/Profile API Contract
* Phase 1.29 — Staff/Admin Operational API Contract
* Phase 1.30 — Cross-Domain API Contract Review
* Phase 1.31 — Canonical API Examples
* Phase 1.32 — OpenAPI Contract Specification

Also inspect:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Do not begin implementation as part of this phase.

---

# 3. Security Review Standard

Review the contract as if an attacker knows:

* every endpoint
* every field
* every role
* every public identifier
* every request shape
* every documented state
* every OpenAPI schema

Assume the client is completely untrusted.

The attacker may:

* modify every request body
* modify every path parameter
* modify query parameters
* replay requests
* reorder requests
* omit expected fields
* add undocumented fields
* manipulate values
* use valid credentials belonging to another role
* observe public responses
* attempt concurrent requests

The server must remain authoritative.

---

# 4. Threat Model

Review the following actor categories:

```text
Anonymous attacker
Authenticated CUSTOMER
Authenticated STAFF
Authenticated ADMIN
Compromised customer account
Compromised staff account
Malicious client application
Network retry/replay attacker
Concurrent operator
```

Do not assume that a valid authenticated identity is trustworthy.

A valid account can still be malicious.

---

# 5. Security Invariants

Preserve these core invariants:

```text id="n26jv8"
1. Authentication does not grant authorization.
2. Customer resources are ownership-scoped.
3. Staff operational authority does not grant customer-account control.
4. Staff cannot escalate to Admin.
5. Customers cannot escalate to Staff/Admin.
6. Roles are server-controlled.
7. Order state is server-controlled.
8. Financial values are server-controlled.
9. Inventory state is server-controlled.
10. Audit actor identity is server-controlled.
11. Notification ownership is server-controlled.
12. Attachments inherit parent-resource authorization.
13. Historical order data is immutable.
14. Business-state transitions are validated server-side.
15. Retried requests cannot silently create duplicate financial or operational effects.
16. Sensitive data is not exposed through unauthorized representations.
17. Error responses do not disclose unnecessary internal information.
```

Add any additional invariants discovered during the review.

---

# 6. Authentication Security Review

Review:

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
```

Verify:

* authentication is required exactly where expected
* login does not disclose unnecessary account-existence information
* passwords are never returned
* credentials are never logged/documented as response data
* logout semantics cannot accidentally revoke another identity
* authentication state cannot be supplied by the client as an arbitrary role

Do not define credential behavior that is absent from Phase 1.17.

---

# 7. Authentication Enumeration Review

Check whether authentication responses reveal whether an account exists.

Potentially dangerous distinction:

```text
"User does not exist"
```

versus:

```text
"Password is incorrect"
```

when the project intends anti-enumeration behavior.

Use the approved authentication error behavior.

Do not expose unnecessary differences that allow attackers to enumerate customer or Staff accounts.

---

# 8. Credential Boundary Review

Verify that no endpoint except the intended authentication operation accepts credential-management data.

Search for:

```text
password
password_hash
token
refresh_token
secret
credential
```

across all request schemas.

No ordinary:

```text
PATCH /api/v1/me
PATCH /api/v1/admin/staff/{user}
PATCH /api/v1/staff/products/{product}
```

may accidentally accept credential fields.

Authentication-specific credential operations belong to the Authentication contract.

---

# 9. Authorization Model Review

For every protected operation verify that authorization is based on:

```text
identity
+
role
+
resource
+
action
+
ownership/context
+
business state
```

Do not accept:

```text
role = STAFF
```

from the request as proof of authorization.

The server derives the role from the authenticated identity.

---

# 10. Horizontal Privilege Escalation Review

Test conceptually whether a Customer A could replace:

```text id="fpg8wb"
{order}
{request}
{enquiry}
{notification}
{item}
```

with an identifier belonging to Customer B.

Every customer-private endpoint must reject the unauthorized access.

Review:

```text
/me/cart
/me/orders
/me/requests
/me/enquiries
/me/notifications
```

as separate resource families.

Do not assume the `/me` prefix alone makes the implementation safe.

---

# 11. Staff Horizontal Access Review

If the Staff operational contract eventually introduces scoping by operational assignment, review whether Staff A can access records assigned exclusively to Staff B.

If there is no individual Staff ownership/scoping model in Version 1, document that operational Staff access is intentionally shared.

Do not accidentally create a hidden assignment rule through implementation assumptions.

---

# 12. Vertical Privilege Escalation Review

Test:

```text
CUSTOMER → STAFF
CUSTOMER → ADMIN
STAFF → ADMIN
```

against:

* endpoint access
* request fields
* path manipulation
* query parameters
* body injection
* response manipulation

No client field may elevate the caller.

---

# 13. Role Mutation Review

Search OpenAPI and API documentation for:

```text
role
permissions
is_admin
is_staff
account_type
```

Review every request schema containing such concepts.

The default rule:

```text id="e1qv8s"
role is server-controlled
```

must hold.

Any legitimate role mutation must be:

* Admin-only
* explicit
* controlled
* audited
* state-aware
* resistant to self-escalation

---

# 14. Staff Approval Security Review

Review:

```text
POST /api/v1/admin/staff/{user}/approve
```

Verify:

* Admin-only authorization
* target cannot approve themselves
* target cannot supply approval metadata
* approval is auditable
* approval cannot be forged via request body
* repeated approval has defined behavior

A Staff account cannot become operational merely by changing its own profile.

---

# 15. Staff Suspension/Deactivation Review

For:

```text
POST /api/v1/admin/staff/{user}/suspend
POST /api/v1/admin/staff/{user}/reactivate
```

verify:

* Admin-only authorization
* approved lifecycle transitions
* no unauthorized state skipping
* no accidental customer-account behavior
* auditability
* concurrency handling

Do not allow a suspended Staff identity to perform privileged operations merely because an old token remains valid.

The exact token invalidation rule belongs to Authentication/security policy and must be documented if required.

---

# 16. Customer Account Boundary Review

Verify that Staff cannot:

* change customer role
* change customer permissions
* reset customer password through operational endpoints
* disable customer browsing
* disable ordinary ordering
* change resource ownership
* impersonate customer
* modify security status

Admin does not automatically receive every possible customer-control operation merely because Admin is the highest role.

Any future account-control capability must have its own explicit contract.

---

# 17. IDOR Review

Perform an object-reference audit against every path parameter.

Review:

```text
/product
/category
/item
/order
/request
/enquiry
/notification
/user
/inventory
/audit-log
```

For each:

* who may access it?
* how is access authorized?
* is ownership required?
* is business context required?
* what happens if the identifier exists but is inaccessible?

Do not rely on obscurity of IDs.

---

# 18. Identifier Exposure Review

Public identifiers must reveal no sensitive information unnecessarily.

Review:

* sequential IDs
* order references
* user IDs
* request IDs
* enquiry IDs
* attachment IDs
* inventory IDs

An identifier being guessable is not itself authorization.

Authorization must remain mandatory.

If predictable identifiers create unacceptable enumeration risk, record a decision for opaque identifiers.

Do not silently alter identifier strategy at this stage.

---

# 19. Mass Assignment Review

Search all write schemas for fields that should never be client-controlled.

At minimum reject/omit:

```text
id
user_id
owner_id
role
permissions
status
payment_state
order_reference
subtotal
delivery_fee
total
inventory_quantity
created_at
updated_at
approved_by
approved_at
audit_actor
notification_recipient
notification_source
```

The OpenAPI request schemas should make the intended writable surface obvious.

---

# 20. Unknown-Field Review

Verify the contract's policy for unexpected request fields.

Security-sensitive endpoints should not silently accept undocumented privileged fields.

For example, a client must not successfully send:

```json
{
  "delivery_fee": 15000,
  "total": 1,
  "status": "COMPLETED",
  "role": "ADMIN"
}
```

to a request whose contract only permits one of those fields.

Document the expected treatment of unknown fields consistently.

---

# 21. Order State Manipulation Review

Verify that no endpoint permits arbitrary state assignment.

Prohibited conceptual pattern:

```http
PATCH /api/v1/staff/orders/{order}
```

with:

```json
{
  "status": "DELIVERED"
}
```

unless the request is exclusively for an already-approved non-state field update.

Controlled actions must enforce predecessor state.

---

# 22. State-Skipping Security Review

Test conceptual attacks such as:

```text
PENDING_PAYMENT → DELIVERED
PENDING_PAYMENT → COMPLETED
PAID → DELIVERED
PROCESSING → ACCEPTED
PICKUP → SHIPPED
DELIVERY → READY_FOR_PICKUP
```

All invalid transitions must be rejected.

The contract must not rely on client UI sequence to prevent them.

---

# 23. Cancellation Security Review

Review:

```text
POST /api/v1/me/orders/{order}/cancel
```

Verify:

* customer owns order
* order is cancellable
* cancellation window is 20 minutes
* server time is authoritative
* client cannot submit a fake timestamp
* cancellation cannot be performed by Staff on behalf of Customer unless explicitly authorized
* repeated cancellation has defined behavior
* concurrent fulfillment/cancellation resolves safely

---

# 24. Financial Security Review

Review every endpoint that can influence:

```text id="jif65p"
subtotal
delivery_fee
total
payment state
```

Customer must never control:

```text
subtotal
delivery_fee
total
```

through ordinary requests.

Staff/Admin must not directly modify `total`.

The server calculates:

```text
total = subtotal + delivery_fee
```

according to the approved money rules.

---

# 25. Delivery Fee Tampering Review

Attack scenarios:

```text
Customer submits delivery_fee = 0
Customer submits negative delivery_fee
Customer submits huge delivery_fee
Customer submits total inconsistent with delivery_fee
Staff changes fee after financial immutability
Two Staff users change fee simultaneously
Client omits fee where required
```

The API contract must define safe behavior for each.

---

# 26. Financial Immutability Boundary

Identify exactly when order financial values become immutable.

At minimum review:

```text
before payment
after payment
during processing
after shipment
after completion
```

Do not leave this boundary implicit.

If later payment/refund/adjustment behavior is outside Group A, explicitly document the handoff to Group H rather than allowing unrestricted financial mutation.

---

# 27. Payment Boundary Security

Group A must not expose payment implementation secrets or gateway controls.

Verify that:

* payment consumes the authoritative order amount
* customer cannot redefine the amount
* Staff cannot impersonate the payment system
* notification events do not establish payment state
* payment state cannot be changed through order-status operations

Group H owns payment-specific security and implementation.

---

# 28. Inventory Security Review

Review:

```text
GET /api/v1/inventory
GET /api/v1/inventory/{inventory}
POST /api/v1/inventory/{inventory}/adjust
```

Verify:

* Customer has no access
* Staff/Admin authorization is explicit
* quantity changes are controlled
* negative stock behavior is defined
* duplicate requests cannot double-apply accidentally
* concurrency is addressed
* reason values are closed
* inventory cannot be modified via product update accidentally

---

# 29. Inventory Race Review

Consider:

```text
Staff A adjusts +10
Staff B adjusts -8
Customer checkout occurs concurrently
```

The contract must preserve transactional correctness.

Do not rely on client-observed inventory values.

Server-side concurrency handling is mandatory.

---

# 30. Cart Security Review

Verify:

* cart is customer-owned
* Staff/Admin do not ordinarily access customer carts
* cart identifiers cannot expose another customer's cart
* cart content cannot be used to reserve stock
* product type restrictions are enforced server-side
* cart price cannot become payment authority

---

# 31. Checkout Replay Review

Consider:

```text
Customer taps "Pay/Checkout" twice.
Network times out.
Client retries.
Two devices submit the same checkout.
```

The contract must define idempotent behavior.

Review:

* idempotency key
* request identity
* duplicate detection
* resulting Order reuse
* failure handling

No retry may silently create duplicate Orders.

---

# 32. Operational Replay Review

Review duplicate requests for:

```text
accept
start-processing
set-delivery-fee
ready-for-pickup
ship
deliver
complete
approve staff
inventory adjustment
```

The contract must distinguish operations that can safely repeat from those where repeated execution would be harmful.

Inventory adjustments require special attention because repeating an adjustment can change stock twice.

---

# 33. Replay/Idempotency Key Security

If idempotency keys are used, verify:

* client cannot use another user's successful key to retrieve another user's result
* idempotency scope is tied to authenticated identity where appropriate
* keys do not become authorization tokens
* key reuse with a materially different request has defined behavior

Do not treat possession of an idempotency key as authorization.

---

# 34. Concurrency Security

Identify resource pairs that can be modified simultaneously.

At minimum:

```text
Order + cancellation
Order + fulfillment
Order + delivery fee
Inventory + checkout
Staff + approval
Staff + suspension
```

For each, define:

* authoritative ordering
* conflict behavior
* transaction expectations
* resulting state

The API contract must not allow an attacker to exploit race conditions simply by issuing simultaneous requests.

---

# 35. Resource Enumeration Review

Review public and authenticated responses for enumeration leakage.

Potential targets:

* customer IDs
* order IDs
* Staff IDs
* request IDs
* enquiry IDs
* attachment IDs
* inventory IDs

Verify that:

```text
existing but inaccessible
```

and:

```text
nonexistent
```

are handled consistently with the approved resource-exposure policy.

Do not expose private resource existence unnecessarily.

---

# 36. Search and Filtering Abuse Review

Review all search/filter parameters for abuse potential.

Particularly:

```text
search
customer
order reference
email
phone
date ranges
status
```

Ensure the contract does not accidentally create unrestricted customer-data search capabilities for Staff.

Do not expose arbitrary database filtering.

---

# 37. Sensitive Data Exposure Review

Review every response schema for:

```text
password
password_hash
security token
authentication secret
payment credential
internal authorization metadata
unnecessary customer records
internal notes
audit internals
```

Differentiate:

```text
public representation
customer representation
Staff representation
Admin representation
```

Use separate schemas where necessary.

---

# 38. Customer Personal Data Review

For Staff order operations, verify that customer data exposure is limited to operational need.

Potential fields:

* name
* phone
* email
* delivery address

must be justified by operational purpose.

Do not automatically expose unrelated customer profile data.

---

# 39. Internal Notes Security

Where operational notes exist, verify:

* Staff/Admin access only
* customer cannot read them unless explicitly approved
* original customer content cannot be replaced by notes
* notes cannot be injected into public catalog/order responses accidentally

Treat internal notes as privileged business information.

---

# 40. Attachment Security Review

Review every attachment access path.

Verify:

* authentication where required
* parent resource authorization
* object-level authorization
* no guessable public access
* no unrestricted storage URL
* no cross-resource attachment retrieval

An attachment ID must never function as a bearer authorization token unless that behavior is explicitly designed and secured.

---

# 41. File Upload Contract Review

Where attachments are submitted, review:

* allowed content types
* file size limits
* number of attachments
* filename behavior
* content validation
* storage isolation
* malware/security scanning expectations where required
* access control

Do not invent detailed storage technology in the API contract.

The contract must, however, define security-relevant constraints that clients need to understand.

---

# 42. Notification Security Review

Verify:

* customer sees only own notifications
* Staff sees only approved operational notifications
* notification IDs cannot expose another recipient's data
* recipient is server-controlled
* source resource does not bypass authorization
* mark-read does not mutate another user's notification

A notification source reference must not grant permission to read the source resource.

---

# 43. Notification Injection Review

Customers and Staff must not be able to submit arbitrary notification payloads that become trusted business notifications.

Review all request schemas for fields such as:

```text
recipient
type
title
message
source_id
source_type
```

These must be server-controlled except where an explicitly approved operational notification feature says otherwise.

---

# 44. Audit Security Review

Verify that privileged actions produce trustworthy audit records.

Audit actor must derive from authentication.

The client must not control:

```text
actor_id
actor_role
approved_by
timestamp
action identity
```

Audit history must not be mutable through normal API operations.

---

# 45. Audit Log Disclosure Review

If audit logs are exposed through:

```text
GET /api/v1/admin/audit-logs
```

verify:

* Admin-only authorization
* no customer visibility
* no secret leakage
* pagination
* constrained filtering
* no audit mutation
* appropriate redaction of sensitive request data

Do not expose raw request payloads if they may contain credentials or sensitive data.

---

# 46. Error Disclosure Review

Review all error examples and documented error behavior.

Do not disclose:

```text
SQL syntax
database table names
file paths
framework class names
stack traces
storage paths
authentication internals
policy implementation
```

Errors should provide enough information for client behavior without helping an attacker map internal architecture.

---

# 47. 404/403 Security Review

Determine where the contract intentionally uses:

```text
404
```

instead of:

```text
403
```

to avoid resource-existence leakage.

Use one consistent policy.

Do not let different domains make arbitrary choices.

---

# 48. Rate-Limiting Review

Identify endpoints requiring stronger abuse protection.

At minimum review:

```text
register
login
public request creation
public enquiry creation
checkout
cart writes
order cancellation
attachment uploads
inventory adjustments
Admin authentication-sensitive actions
```

The contract need not prescribe infrastructure implementation, but it should identify security-sensitive operations requiring rate limiting.

---

# 49. Anonymous Submission Security

Review public:

```text
POST /api/v1/requests
POST /api/v1/enquiries
```

because these endpoints can be abused without authentication.

Verify that the contract identifies:

* rate limiting
* validation
* abuse/spam protection
* attachment limits
* resource enumeration protection
* safe response behavior

Do not make anonymous retrieval available merely because anonymous creation is allowed.

---

# 50. Public Catalog Abuse Review

Public catalog endpoints are intentionally unauthenticated.

Verify that they do not accidentally expose:

* internal stock controls
* internal notes
* customer information
* unpublished operational information
* private administrative fields

Public endpoints must remain safe for unrestricted browsing.

---

# 51. Cache Security Review

Review cache behavior for every resource type.

Never publicly cache:

* private customer orders
* carts
* customer notifications
* authenticated profile data
* Staff operational data
* Admin data

Where public caching is allowed, confirm the representation contains no private fields.

---

# 52. Cross-Client Security Review

The same security rules must hold regardless of whether the caller is:

```text
Next.js website
Flutter application
Admin UI
direct HTTP client
```

Do not trust the client type.

Do not introduce special API routes that assume Flutter or Next.js is trustworthy.

---

# 53. Browser/CSRF Boundary Review

Review the authentication contract to determine whether browser-based authentication creates CSRF requirements.

If cookie/session authentication is used, the contract must define the appropriate CSRF protection boundary.

If bearer-token authentication is used without cookies, document the intended model.

Do not introduce a contradictory authentication model during implementation.

---

# 54. CORS Boundary Review

Do not treat CORS as authorization.

The API contract may document expected cross-origin usage, but:

```text
CORS ≠ authentication
CORS ≠ authorization
```

A malicious HTTP client can bypass browser CORS enforcement.

Server-side authorization remains mandatory.

---

# 55. Transport Security

Verify that the deployment contract assumes secure transport.

API credentials, tokens, authenticated requests, and sensitive customer information must use HTTPS in deployed environments.

Do not document production API usage over plain HTTP.

---

# 56. Logging Security Boundary

Record the rule that future implementation must not log:

* passwords
* authentication tokens
* payment credentials
* sensitive file contents
* unnecessary personal data

This phase need not design logging architecture.

It must prevent the API contract from encouraging insecure logging through response/request examples.

---

# 57. OpenAPI Security Review

Review `docs/api/openapi.yaml` as a security artifact.

Verify:

* public endpoints do not accidentally inherit security
* protected endpoints do require the security scheme
* sensitive schemas do not appear under public operations
* Admin endpoints are explicitly documented
* request schemas do not expose privileged writable fields
* examples contain no secrets
* no undocumented security bypass appears in descriptions

---

# 58. OpenAPI Schema Abuse Review

Inspect for overly permissive schemas such as:

```yaml id="jw1qub"
additionalProperties: true
```

on sensitive request objects, where it would allow undocumented privileged fields.

Use restrictive request schemas where appropriate.

Do not impose global `additionalProperties: false` blindly if that would conflict with the approved API contract or tooling.

Apply the stricter rule where security requires it.

---

# 59. Security-Relevant Enum Review

Verify that security-sensitive enums are closed:

```text
role
order status
fulfillment type
notification type
request status
enquiry status
inventory adjustment reason
```

An attacker must not supply an undocumented privileged enum value that triggers unintended behavior.

---

# 60. Security-Relevant State Review

Every privileged state mutation must identify:

```text
current state
allowed action
next state
actor
preconditions
```

Do not leave state-dependent authorization in prose so vague that implementation becomes guesswork.

---

# 61. Security Decision Register

For each finding, classify:

```text
CRITICAL
HIGH
MEDIUM
LOW
INFORMATIONAL
```

Also classify its disposition:

```text
FIX NOW
DEFER WITH EXPLICIT ACCEPTANCE
OUT OF SCOPE
FALSE POSITIVE
```

Every `CRITICAL` or `HIGH` issue must be resolved before Phase 1.33 can complete.

Do not silently defer a security-critical contract defect to implementation.

---

# 62. Required Security Findings Format

For every real finding record:

```text
Finding ID
Severity
Affected endpoint/resource
Attack scenario
Security impact
Current contract weakness
Required correction
Affected documentation
Status
```

Example:

```text id="0pvn6a"
Finding: SEC-001
Severity: HIGH
Affected: POST /staff/orders/{order}/set-delivery-fee
Weakness: Client-provided total is permitted by request schema.
Impact: Financial tampering.
Correction: Remove total from request schema; server recalculates it.
Status: FIX NOW
```

---

# 63. Required Security Test Matrix

Create a contract-level matrix covering at least:

| Security test                     | CUSTOMER |       STAFF |                             ADMIN |
| --------------------------------- | -------: | ----------: | --------------------------------: |
| Access public catalog             |      Yes |         Yes |                               Yes |
| Access another customer's order   |       No |         No* |                               No* |
| Access Staff order queue          |       No |         Yes |                               Yes |
| Approve Staff                     |       No |          No |                               Yes |
| Set delivery fee                  |       No |         Yes |                               Yes |
| Change customer role              |       No |          No | Controlled/Admin-only if approved |
| Change own role                   |       No |          No |                                No |
| Modify order status directly      |       No |          No |                                No |
| Adjust inventory                  |       No |         Yes |                               Yes |
| Read another user's notifications |       No |          No |       According to approved scope |
| Read audit logs                   |       No | Normally no |                    Yes if exposed |

`*` Subject to the operational scope explicitly approved by the project.

This table must agree with the final authorization matrix.

---

# 64. Required Attack Scenario Set

Document future automated/security tests for:

```text
1. Customer accesses another customer's order.
2. Customer modifies role to ADMIN.
3. Staff accesses Admin staff approval.
4. Staff manipulates order status.
5. Customer submits delivery fee.
6. Customer submits forged total.
7. Staff submits forged audit actor.
8. Staff changes another operational resource identifier.
9. Customer accesses another user's notification.
10. Attachment ID is replaced with another attachment ID.
11. Checkout is replayed.
12. Inventory adjustment is replayed.
13. Delivery fee is concurrently changed.
14. Cancellation races fulfillment.
15. Public request endpoint is abused with repeated submissions.
16. Unknown privileged fields are added to a request.
17. Error response attempts to enumerate private resources.
18. Public catalog response exposes internal fields.
```

---

# 65. Remediation Rules

When a security finding is discovered:

1. identify the authoritative contract
2. identify the security flaw
3. modify the contract
4. modify affected examples
5. modify OpenAPI
6. update endpoint/resource documentation
7. update `docs/decisions.md` if the change represents a new decision
8. rerun the relevant review

Do not patch only the OpenAPI file while leaving prose contradictory.

---

# 66. No Security Through Obscurity

Do not rely on:

* hidden routes
* obscure IDs
* undocumented fields
* frontend restrictions
* CORS
* UI-only role controls
* mobile-app secrecy

as authorization mechanisms.

Security must be enforced server-side.

---

# 67. No Client Trust

Explicitly document that clients cannot be trusted for:

```text
current time
user ID
role
permission
ownership
order status
inventory
subtotal
delivery fee
total
payment state
audit identity
notification recipient
```

The client submits intent.

The server determines authoritative state.

---

# 68. Business Rule vs Security Rule

Distinguish:

```text
business rule
```

from:

```text
security enforcement
```

Example:

```text
Business rule:
Customer can cancel an eligible order within 20 minutes.

Security enforcement:
Only the authenticated owner of that order may invoke cancellation.
```

Both must be documented.

Do not let business-rule documentation substitute for authorization documentation.

---

# 69. Final Security Documentation

Update:

```text id="7ip6gm"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
docs/api/openapi.yaml
```

Where security decisions affect multiple documents, update all affected representations.

Do not create a permanent `security-review.md` unless the project's documentation policy later determines that a separate security register is genuinely useful.

---

# 70. Definition of Done

Phase 1.33 is complete only when:

* [x] Authentication boundaries have been reviewed.
* [x] Authorization boundaries have been reviewed.
* [x] Horizontal privilege escalation has been reviewed.
* [x] Vertical privilege escalation has been reviewed.
* [x] IDOR/resource ownership has been reviewed.
* [x] Role mutation security has been reviewed.
* [x] Staff approval security has been reviewed.
* [x] Customer-account boundaries are secure.
* [x] Mass assignment risks have been reviewed.
* [x] Unknown privileged fields are addressed.
* [x] Order state manipulation has been reviewed.
* [x] Cancellation security has been reviewed.
* [x] Financial tampering has been reviewed.
* [x] Delivery-fee tampering has been reviewed.
* [x] Financial immutability boundaries are documented.
* [x] Payment boundary security is documented.
* [x] Inventory mutation security has been reviewed.
* [x] Replay/idempotency security has been reviewed.
* [x] Concurrency security has been reviewed.
* [x] Resource enumeration has been reviewed.
* [x] Sensitive-data exposure has been reviewed.
* [x] Attachment security has been reviewed.
* [x] Notification security has been reviewed.
* [x] Audit integrity has been reviewed.
* [x] Error disclosure has been reviewed.
* [x] Rate-limiting requirements have been identified.
* [x] Anonymous submission abuse has been reviewed.
* [x] Cache/privacy boundaries have been reviewed.
* [x] Browser/CSRF implications have been reviewed.
* [x] CORS has not been incorrectly treated as authorization.
* [x] Transport-security assumptions are documented.
* [x] OpenAPI security requirements are correct.
* [x] OpenAPI request schemas do not expose unnecessary privileged fields.
* [x] Security-sensitive enums are CLOSED.
* [x] Security-relevant state transitions are explicit.
* [x] All CRITICAL/HIGH findings are resolved.
* [x] Deferred security findings have explicit acceptance and rationale.
* [x] Security test scenarios are documented.
* [x] Examples, written contracts, and OpenAPI remain synchronized.
* [x] No implementation code has been started.

---

# 71. STOP Condition

**STOP after the Version 1 API contract passes the security review and all Critical/High contract-level findings have been resolved.**

Do not begin:

* Laravel authentication implementation
* Laravel authorization policies
* middleware implementation
* controllers
* database migrations
* Eloquent models
* frontend authentication code
* Flutter authentication code
* payment implementation
* production deployment

The purpose of Phase 1.33 is to make the API contract safe enough to serve as the basis for implementation.

**Next phase: 1.34 — API Contract Consistency & Completeness Review.**
