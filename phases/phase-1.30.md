# Phase 1.30 — Cross-Domain API Contract Review

## 1. Purpose

Perform a complete cross-domain consistency review of the Version 1 API contract produced by Phases 1.16–1.29.

This phase is a **contract review and correction phase**, not an implementation phase.

Its purpose is to detect and resolve contradictions involving:

* authentication
* authorization
* endpoint ownership
* resource ownership
* request/response shapes
* state machines
* financial calculations
* inventory rules
* delivery fees
* order fulfillment
* made-to-order requests
* enquiries
* notifications
* user/profile behavior
* Staff/Admin operations
* error handling
* concurrency
* idempotency
* enum closure
* security boundaries

The goal is to ensure that the entire API behaves like **one coherent system**, not a collection of individually plausible endpoint contracts.

---

# 2. Dependencies

Treat all previous Group A phases as authoritative inputs:

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

Also inspect:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not assume that earlier phases are automatically consistent.

This phase exists specifically to discover inconsistencies.

---

# 3. Review Standard

Review the API as if an implementation team is about to build:

```text
                ┌─ Next.js website
Laravel API ────┤
                └─ Flutter application
```

Both clients consume the same Laravel API directly (fan-out, no Flutter via Next.js).

The review must answer:

> Could two competent developers implement the API independently from the current documentation and still produce compatible behavior?

If the answer is no, identify the ambiguity and resolve it before proceeding.

---

# 4. Review Method

Perform the review in this order:

```text
1. Global conventions
2. Actors and authorization
3. Resource ownership
4. Endpoint inventory
5. Request/response shapes
6. State machines
7. Financial rules
8. Inventory rules
9. Cross-domain side effects
10. Notifications
11. Errors
12. Concurrency/idempotency
13. Security
14. Version 1 enum closure
15. Client compatibility
16. Documentation consistency
```

Do not skip a category because the individual phase appears complete.

---

# 5. Global API Convention Review

Verify that every domain uses the same:

* API version prefix
* HTTP method semantics
* naming conventions
* identifier conventions
* pagination format
* filtering conventions
* sorting conventions
* response envelope
* error envelope
* date/time representation
* currency representation
* validation format
* request ID/correlation behavior
* idempotency mechanism
* authentication mechanism

There must not be domain-specific conventions unless an explicit architectural reason exists.

For example, these should not coexist accidentally:

```text
GET /api/v1/me/orders
```

and:

```text
GET /api/v1/customer/orders
```

for the same conceptual ownership model.

Select one canonical convention and use it consistently.

---

# 6. Actor Model Review

Verify that exactly these Version 1 roles exist:

```text
CUSTOMER
STAFF
ADMIN
```

All role enums must be CLOSED.

Reject accidental introduction of:

```text
SUPER_ADMIN
MANAGER
OPERATOR
MODERATOR
SUPPORT
EDITOR
WAREHOUSE
DELIVERY_AGENT
GUEST
```

unless the project explicitly changes the Version 1 role model.

Anonymous users are an authentication state, not a fourth role.

---

# 7. Authentication vs Authorization Review

Verify that the contract never confuses:

```text
authentication
```

with:

```text
authorization
```

For every protected endpoint, identify:

```text
authenticated?
+
which actor?
+
which permission?
+
which resource/context?
```

A valid token must never be treated as sufficient authorization.

Verify:

* Customer cannot access Staff endpoints.
* Customer cannot access Admin endpoints.
* Staff cannot access Admin-only endpoints.
* Admin may perform explicitly approved Staff operations.
* Anonymous users only access endpoints explicitly marked public.

---

# 8. `/me` Boundary Review

Verify Phase 1.28 and all other domains against the self-resource boundary.

Canonical self-context should remain:

```text
GET /api/v1/me
PATCH /api/v1/me
```

Do not introduce duplicate profile endpoints elsewhere unless there is a documented reason.

The following must never become client-controlled through `/me`:

* role
* permissions
* account security state
* account status
* ownership
* internal IDs
* audit fields
* administrative approval
* Staff/Admin status

Verify that Staff/Admin operational APIs do not accidentally expose a generic customer profile-management route.

---

# 9. Customer Ownership Review

Perform a resource-by-resource ownership audit.

At minimum review:

| Resource     | Primary owner               | Customer access     | Staff access                | Admin access         |
| ------------ | --------------------------- | ------------------- | --------------------------- | -------------------- |
| Cart         | Customer                    | Own                 | No ordinary access          | No ordinary access   |
| Order        | Customer                    | Own                 | Operational                 | Operational/admin    |
| Request      | Customer when authenticated | Own                 | Operational                 | Administrative       |
| Enquiry      | Customer when authenticated | Own where supported | Operational                 | Administrative       |
| Notification | Recipient                   | Own                 | Operational recipient scope | Admin scope          |
| Profile      | User                        | Own                 | Own                         | Own                  |
| Inventory    | Business                    | No                  | Operational                 | Administrative       |
| Catalog      | Business                    | Public read         | Operational                 | Administrative       |
| Audit        | Business                    | No                  | Normally no                 | Read-only if exposed |

The exact visibility must match prior contracts.

Resolve any contradiction.

---

# 10. Resource Identifier Review

Verify that all resources use a consistent identifier strategy.

Check:

* internal database IDs
* public slugs
* public order references
* route parameters
* opaque identifiers where required
* UUID/integer decisions if already approved

The customer-facing order reference must remain:

```text
OD-*****
```

Do not accidentally replace the public order reference with a raw internal database ID in customer-facing workflows.

If an internal resource ID is used in a Staff route, ensure that exposing it does not create an authorization shortcut.

---

# 11. Endpoint Inventory Completeness Review

Compare every endpoint mentioned in:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

against the canonical endpoint inventory.

Detect:

* undocumented endpoints
* duplicated endpoints
* conflicting paths
* inconsistent HTTP methods
* duplicate endpoint identifiers
* missing endpoint identifiers
* endpoints mentioned only in examples
* endpoints referenced by another contract but never defined

Every public or privileged endpoint must exist in the inventory.

---

# 12. Endpoint Ownership Review

For each endpoint, verify that its namespace matches its purpose.

Examples:

```text
/me/...
```

for the authenticated user's own resources.

```text
/api/v1/orders, /api/v1/inventory, /api/v1/requests, /api/v1/enquiries (with OPERATIONAL authorization: orders.view_operational, inventory.manage, etc.)
```

for normal operational operations (canonical, not `/staff/...` prefix — `/staff/...` is only conceptual legacy mapping per `docs/api/api-contract.md §30.3`/`§31.8`).

```text
/admin/...
```

for Admin-only administration (`/admin/staff`, `/admin/audit-logs`, `/admin/products` `CAT-013/014`).

Public catalog paths remain public (`GET /api/v1/products`, `/api/v1/categories`).

Do not put customer-owned actions under `/staff`.

Do not put Staff lifecycle management under `/me`.

Do not expose Admin actions under general authenticated routes.

---

# 13. Request/Response Contract Consistency

Cross-check shared fields across all resources.

Common examples include:

```text
id
created_at
updated_at
status
currency
quantity
unit_price
subtotal
delivery_fee
total
```

The same concept must have:

* the same meaning
* the same type
* the same nullability
* the same formatting
* the same mutability rules

For example, `total` must never mean:

```text
subtotal
```

in one endpoint and:

```text
subtotal + delivery_fee
```

in another.

---

# 14. Server-Controlled Field Review

Build a cross-domain list of fields that clients must never control.

At minimum:

```text
user_id
role
permissions
order reference
order status
payment state
subtotal
delivery fee
total
inventory current quantity
created_at
updated_at
approved_by
approved_at
audit actor
business-event identity
notification recipient
notification source
historical order line values
```

The same server-control rule must apply regardless of which endpoint touches the resource.

---

# 15. Order Lifecycle Review

Perform a complete state-machine review.

Verify that the Order state transitions defined in Phases 1.23, 1.24, and 1.29 are identical.

The approved structure should remain conceptually:

```text
PENDING_PAYMENT
    ↓
PAID
    ↓
ACCEPTED
    ↓
PROCESSING
```

Pickup:

```text
PROCESSING
    ↓
READY_FOR_PICKUP
    ↓
COMPLETED
```

Delivery:

```text
PROCESSING
    ↓
SHIPPED
    ↓
DELIVERED
    ↓
COMPLETED
```

Do not allow another phase to introduce contradictory states such as:

```text
CONFIRMED
PREPARING
OUT_FOR_DELIVERY
CANCELLED_BY_STAFF
```

unless those states are explicitly approved and incorporated everywhere.

---

# 16. Order Cancellation Review

Verify the customer cancellation rule across:

* Checkout
* Order
* Tracking
* Staff/Admin operations
* Notification behavior

The customer cancellation window is:

```text
20 minutes
```

The server's clock is authoritative.

Cancellation must be based on the approved cancellable states.

Check specifically that Staff/Admin actions cannot create a contradiction such as:

```text
Customer may cancel after Staff acceptance
```

when the order contract says cancellation is no longer allowed.

Likewise, cancellation must not be implemented as a generic order deletion.

---

# 17. Fulfillment Branch Review

Verify that the `fulfillment_type` value is closed:

```text
PICKUP
DELIVERY
```

Verify all downstream operations against the selected branch.

Pickup must not receive delivery-only actions.

Delivery must not receive pickup-only actions.

The fulfillment branch must not silently change after order creation unless a specifically approved transition exists.

---

# 18. Delivery Fee Cross-Domain Review

This is a mandatory high-priority review.

Verify consistency across:

```text
Checkout
Order
Staff/Admin Operations
Payment
Notifications
Tracking
```

The approved operational model must clearly answer:

### Who chooses fulfillment type?

Customer.

### Who chooses delivery fee?

Staff/Admin.

### Can Customer provide delivery fee?

No.

### Is pickup charged a delivery fee?

No.

```text
PICKUP → delivery_fee = 0
```

### Is delivery fee part of the authoritative order total?

Yes.

```text
total = subtotal + delivery_fee
```

### Can the client submit total?

No.

### Can payment calculate its own delivery fee?

No.

### When does payment receive the amount?

Only after the authoritative payable amount has been established.

Any contradictory wording must be corrected in the source documents.

---

# 19. Delivery Fee State Review

Use one canonical representation for the delivery-fee lifecycle.

The review must determine whether the project represents a pending fee with:

* a nullable `delivery_fee`
* a dedicated fee status
* another already-approved mechanism

Do not create multiple independent concepts for the same condition.

For example, avoid simultaneously having:

```text
delivery_fee = null
delivery_fee_status = PENDING
order_status = PENDING_DELIVERY_FEE
```

unless all three are explicitly required.

Prefer the smallest clear model consistent with the approved business rules.

---

# 20. Payment Boundary Review

Phase 1.30 must ensure payment remains outside the implementation scope of Group A while still leaving a usable contract boundary.

Verify that:

```text
Checkout
    ↓
Order
    ↓
Authoritative financial amount
    ↓
Group H Payment
```

is unambiguous.

Group A must not define:

* gateway API specifics
* payment webhooks
* capture implementation
* refund implementation
* payment-provider-specific state logic

However, Group A must define enough order/payment relationship information for Group H to consume the authoritative amount safely.

---

# 21. Inventory and Checkout Review

Check the interaction between:

```text
Catalog availability
Cart
Checkout
Inventory
Order
Staff operations
```

Verify:

* catalog availability is informational
* cart does not reserve stock
* checkout revalidates stock
* server calculates authoritative order quantities
* concurrent checkout cannot silently oversell stock
* inventory adjustments cannot invalidate completed order history
* order historical quantities remain immutable

If inventory reservation is not part of Version 1, ensure no later phase accidentally assumes that a cart reserved inventory.

---

# 22. Cart and Order Boundary Review

Verify that:

```text
Cart
```

and:

```text
Order
```

remain separate resources.

A successful checkout creates an Order from authoritative cart contents.

After order creation:

* order history must not depend on the mutable cart
* historical order lines must not change when product data changes
* product price changes must not alter existing orders
* cart mutations must not modify existing order lines

---

# 23. Historical Snapshot Review

Verify that orders retain historical information required to understand what was purchased.

At minimum review:

* product identity
* variant identity where applicable
* product name/display information
* unit price
* quantity
* line total

Also review the delivery-address snapshot for delivery orders.

Do not allow the current product record or current profile to silently rewrite historical order meaning.

---

# 24. Catalog vs Order Review

Verify that catalog data and order historical snapshots have different responsibilities.

Catalog:

```text
current business truth
```

Order:

```text
historical transaction truth
```

A product rename, price change, image change, or availability change must not rewrite what an existing order means.

---

# 25. Made-to-Order Request Boundary Review

Verify that the made-to-order request remains separate from:

* Cart
* Order
* Payment

A request must not:

* create an Order automatically
* reserve inventory automatically
* lock a price automatically
* trigger payment automatically

unless that behavior has been explicitly approved.

Check the same rule across Customer, Staff, and Admin APIs.

---

# 26. Made-to-Order Product Review

Verify the interaction between:

```text
product.type = MADE_TO_ORDER
```

and:

```text
normal Cart/Checkout
```

The current Version 1 contract states that made-to-order products are discoverable but do not enter normal purchase Cart/Checkout.

Ensure no Staff/Admin endpoint accidentally creates a path that allows a made-to-order product to bypass the approved request workflow.

If generic made-to-order requests without a linked catalog product are supported by the approved business rules, ensure the API reflects that consistently.

If they are not supported, document the restriction explicitly.

---

# 27. Enquiry Boundary Review

Verify that General Enquiry remains distinct from Made-to-Order Request and Order operations.

An enquiry may reference relevant resources where approved, but:

```text
Enquiry ≠ Order
Enquiry ≠ Made-to-Order Request
```

Customer-submitted enquiry content must remain immutable.

Internal Staff/Admin notes must remain separate from original customer content.

---

# 28. Attachment Boundary Review

Where attachments exist for Requests or Enquiries, verify:

* optionality
* ownership
* authorization
* download/access rules
* deletion rules
* file metadata exposure
* absence of public unrestricted URLs

Attachments must inherit the parent resource's authorization boundary.

Do not let possession of an attachment identifier bypass authorization.

---

# 29. Notification/Event Review

Verify that the system distinguishes:

```text
Business Event
```

from:

```text
Notification
```

For example:

```text
Order accepted
```

is a business event.

A customer's:

```text
ORDER_ACCEPTED
```

notification is a downstream representation.

The notification must never become the source of truth for order state.

---

# 30. Notification Trigger Matrix

Create a contract-level trigger matrix.

At minimum review these events:

| Business event            | Customer notification                     | Staff notification     | Admin notification                      |
| ------------------------- | ----------------------------------------- | ---------------------- | --------------------------------------- |
| New order                 | Yes                                       | Yes                    | According to approved operational scope |
| Order accepted            | Yes                                       | Optional/approved      | Optional/approved                       |
| Order processing          | Yes                                       | Optional/approved      | Optional/approved                       |
| Ready for pickup          | Yes                                       | Optional               | Optional                                |
| Shipped                   | Yes                                       | Optional               | Optional                                |
| Delivered                 | Yes                                       | Optional               | Optional                                |
| Completed                 | Yes                                       | Optional               | Optional                                |
| Cancelled                 | Yes                                       | Operationally relevant | Operationally relevant                  |
| New made-to-order request | No/appropriate customer confirmation only | Yes                    | Appropriate                             |
| New enquiry               | No/appropriate customer confirmation only | Yes                    | Appropriate                             |

Do not invent notification types without reconciling them with Phase 1.27.

---

# 31. Notification Failure Review

Verify that notification creation/delivery cannot cause a successful core business transaction to roll back.

For example:

```text
Order acceptance succeeds
        ↓
Notification generation fails
        ↓
Order remains accepted
```

The notification subsystem is downstream.

Email/push delivery remains deferred according to the project roadmap.

---

# 32. Error Contract Review

Verify that all domains use the same error envelope.

Canonical structure remains:

```json
{
  "errors": [
    {
      "code": "...",
      "message": "..."
    }
  ]
}
```

Check that no domain introduces its own incompatible structure such as:

```json
{
  "error": "..."
}
```

or:

```json
{
  "message": "...",
  "details": "..."
}
```

without explicit approval.

---

# 33. HTTP Status Review

Cross-check domain behavior against the global mappings.

At minimum:

```text
401 → authentication required/invalid
403 → authenticated but not allowed
404 → resource not found/exposed according to policy
409 → state/concurrency/conflict
422 → validation/business rule
429 → rate limited
500 → unexpected server failure
```

Review especially:

* invalid order transition
* invalid fulfillment action
* invalid delivery fee
* insufficient inventory
* unauthorized Staff/Admin action
* duplicate/idempotent requests
* stale concurrent mutation

Avoid inconsistent use of `400`, `409`, and `422` for the same business condition.

---

# 34. Enum Closure Review

Perform a repository-wide search for all enum-like values.

Every Version 1 enum must be CLOSED.

Review at minimum:

* actor role
* fulfillment type
* product type
* order status
* payment-related values referenced by Group A
* notification type
* request status where defined
* enquiry status where defined
* inventory adjustment reason
* any publication/catalog state
* audit action values if exposed as a contract enum

No API documentation may say:

```text
OTHER
CUSTOM
EXTENSIBLE
or any future value accepted by clients
```

unless the project explicitly changes the enum policy.

---

# 35. Nullability Review

For every important field, explicitly establish whether it is:

```text
required
optional
nullable
conditionally required
server-generated
immutable
```

Pay special attention to:

* delivery address
* delivery fee
* payment state
* order cancellation data
* request product association
* enquiry order association
* attachments
* notification read timestamp

Avoid using `null` as an undocumented state machine.

---

# 36. Date/Time Review

All API date/time fields must follow one canonical convention.

Review:

* order creation
* cancellation deadline
* payment timestamps
* fulfillment timestamps
* notification read timestamps
* request timestamps
* enquiry timestamps
* Staff approval timestamps
* audit timestamps

The cancellation rule must be evaluated server-side using the server-authoritative timestamp.

Do not allow clients to submit their own "current time" to qualify for cancellation.

---

# 37. Currency and Money Review

Verify one canonical money representation across:

* Cart
* Checkout
* Order
* Delivery fee
* Inventory where monetary values exist
* Payment boundary

The API must define:

* currency code
* precision
* rounding behavior
* integer/minor-unit vs decimal representation

Do not leave money representation ambiguous.

Do not permit floating-point financial calculations in the API contract.

---

# 38. Idempotency Review

Create a single cross-domain classification.

At minimum review:

### Checkout

Must be safe against accidental duplicate submissions.

### Operational order actions

Must define repeat-request behavior.

### Delivery fee assignment

Must define whether repeating the same request is harmless and how a changed fee request behaves.

### Inventory adjustment

Must not be accidentally applied twice because a mobile client retries.

### Staff approval/deactivation

Must define repeat behavior.

The documentation must not use different idempotency conventions in different domains.

---

# 39. Concurrency Review

Identify all resources where two actors can legitimately mutate the same record.

At minimum:

```text
Order
Inventory
Staff lifecycle
Delivery fee
```

Review whether the contract protects against:

* duplicate Staff actions
* stale state transitions
* simultaneous delivery-fee updates
* concurrent inventory adjustments
* checkout racing with inventory changes

Every such operation must define the expected conflict behavior.

---

# 40. Field-Level Authorization Review

Do not treat authorization only as an endpoint-level question.

For each sensitive resource, classify fields as:

```text
public
customer-readable
customer-writable
staff-readable
staff-writable
admin-readable
admin-writable
server-only
historical/immutable
```

Use this to detect cases where an otherwise authorized user could mutate a field they should not control.

This is especially important for:

* Order
* User
* Inventory
* Product
* Staff
* Notification

---

# 41. Customer/Staff/Admin Boundary Review

Construct a final permission matrix.

At minimum:

| Capability                     | Customer |              Staff |                           Admin |
| ------------------------------ | -------: | -----------------: | ------------------------------: |
| Browse catalog                 |      Yes |                Yes |                             Yes |
| Own profile                    |      Yes |                Yes |                             Yes |
| Own cart                       |      Yes | No ordinary access |              No ordinary access |
| Own orders                     |      Yes |        Operational |                     Operational |
| Accept order                   |       No |                Yes |                             Yes |
| Set delivery fee               |       No |                Yes |                             Yes |
| Process order                  |       No |                Yes |                             Yes |
| Fulfill pickup                 |       No |                Yes |                             Yes |
| Ship delivery                  |       No |                Yes |                             Yes |
| Manage inventory               |       No |                Yes |                             Yes |
| Operational request queue      |       No |                Yes |                             Yes |
| Operational enquiry queue      |       No |                Yes |                             Yes |
| Approve Staff                  |       No |                 No |                             Yes |
| Change roles                   |       No |                 No | Admin-only controlled operation |
| Customer account restriction   |       No |                 No |     Not automatically available |
| Read audit log                 |       No |        Normally no |                 Yes, if exposed |
| Payment gateway administration |       No |                 No |     Group H / explicit contract |

This matrix must agree with the detailed endpoint contracts.

---

# 42. Security Boundary Review

Perform a final threat-oriented pass for:

```text
horizontal privilege escalation
vertical privilege escalation
IDOR
mass assignment
state manipulation
financial tampering
inventory tampering
customer-account takeover
resource enumeration
attachment access bypass
audit spoofing
replay attacks
duplicate operations
stale writes
```

Every identified risk must map to a contract-level rule.

Do not merely write "securely handled."

State what the API must actually enforce.

---

# 43. Client Compatibility Review

Review the contract from three clients' perspectives:

### Next.js website

Verify:

* public catalog access requires no login
* SEO-relevant catalog data is public
* authenticated customer operations have usable contracts
* Staff/Admin operations have explicit protected routes

### Flutter application

Verify:

* same API contract is usable from mobile
* authentication is shared
* network retries do not create duplicate financial/operational actions
* server state remains authoritative
* no client-side state transition is trusted

### Administrative interfaces

Verify:

* operational data is sufficient without exposing excessive customer information
* Staff does not require customer-account administration to complete normal work
* Admin-only functions are clearly separated

---

# 44. API Contract Documentation Review

Perform a contradiction search across every API document.

Search for terms that often expose stale requirements:

```text
20,000
delivery fee
flat fee
guest checkout
anonymous checkout
MADE_TO_ORDER
CANCELLED
COMPLETED
STAFF
ADMIN
customer block
suspend customer
payment
notification
email
push
address
role
permission
status
PATCH
DELETE
```

Any obsolete rule must be removed or superseded explicitly.

Particular care must be taken to remove the outdated flat delivery fee assumption.

---

# 45. Decision Reconciliation

When the review discovers an unresolved policy choice, do not silently select a convenient implementation.

Classify it as:

```text
RESOLVED
CONFLICT
AMBIGUOUS
DEFERRED
```

For every `CONFLICT` or `AMBIGUOUS` item:

1. identify the conflicting documents
2. identify the affected API behavior
3. select the authoritative business rule when one already exists
4. otherwise record a deliberate Version 1 decision in `docs/decisions.md`
5. update all affected API contracts

Do not leave the same unresolved question duplicated across several documents.

---

# 46. Contract Invariants

Produce a final invariant list.

At minimum include:

```text
1. Public catalog browsing does not require authentication.
2. Checkout requires an authenticated CUSTOMER.
3. Customers can only access their own private commerce resources.
4. Staff cannot control or restrict ordinary customer accounts.
5. Admin has the highest approved operational authority.
6. Version 1 roles are CLOSED.
7. Order status transitions are controlled actions.
8. Order financial totals are server-authoritative.
9. Customer cannot set delivery fee.
10. Pickup has zero delivery fee.
11. Delivery fee is assigned by Staff/Admin.
12. Payment consumes the final server-authoritative amount.
13. Made-to-order requests do not automatically create orders.
14. Enquiries do not automatically create orders.
15. Notifications do not become the source of truth.
16. Historical order values remain immutable.
17. Cart does not reserve inventory.
18. Checkout revalidates inventory/pricing.
19. Privileged state changes are auditable.
20. Core business transactions do not depend on successful notification delivery.
```

Add any additional invariants discovered during the review.

These invariants become the basis for later automated tests.

---

# 47. Required Corrections

Do not finish this phase with only a list of problems.

For every identified inconsistency, update the authoritative documentation.

Examples:

```text
Incorrect endpoint path
→ correct endpoint inventory and contract

Conflicting order state
→ select one state machine and update all references

Different total calculation
→ standardize on server-authoritative calculation

Different error response
→ standardize on Phase 1.16

Staff accidentally allowed to modify customer status
→ remove capability from Staff contract

Open enum found
→ close the enum and document allowed values
```

The repository should be more consistent after this phase than before it.

---

# 48. No New Architecture During Review

Do not use this phase to introduce unrelated architecture.

Do not add:

* event bus architecture
* microservices
* CQRS
* GraphQL
* WebSockets
* message brokers
* custom authorization frameworks
* custom workflow engines
* complex CRM models

unless an earlier approved architectural decision already requires them.

This phase reviews and stabilizes the existing Version 1 contract.

---

# 49. Required Review Artifacts

Do not create a large collection of new permanent files.

Update the existing consolidated documentation.

Recommended locations:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

A temporary review checklist or matrix may be created during implementation if useful, but do not keep unnecessary duplicate documentation permanently.

---

# 50. Required Final Review Tables

Before completion, produce these contract-level tables inside the appropriate consolidated documentation.

## A. Endpoint ownership matrix

For each endpoint:

```text
Endpoint ID
Method
Path
Actor
Purpose
```

## B. Resource permission matrix

For each resource:

```text
Resource
Customer
Staff
Admin
```

## C. State transition matrix

For each state:

```text
Current state
Action
Next state
Allowed actor
Conditions
```

## D. Financial authority matrix

At minimum:

```text
Value
Customer
Staff
Admin
Server
Payment System
```

Cover:

* subtotal
* delivery fee
* total
* payment state

## E. Notification trigger matrix

Map business events to recipient/type.

---

# 51. Required Contract Test Scenarios

Even though this phase does not implement tests, document the scenarios future automated tests must verify.

At minimum:

### Authentication

* anonymous catalog access succeeds
* anonymous checkout fails
* unauthenticated Staff endpoint fails

### Authorization

* Customer cannot access Staff endpoint
* Staff cannot access Admin-only endpoint
* Staff cannot modify customer account security
* Customer cannot access another customer's order

### Order

* valid Staff transition succeeds
* invalid transition fails
* direct status mutation is rejected
* customer cancellation obeys 20-minute rule

### Delivery fee

* Customer cannot set delivery fee
* Staff can set valid delivery fee
* Admin can set valid delivery fee
* pickup order rejects delivery fee
* client-supplied total cannot override server calculation
* payment boundary receives authoritative amount

### Inventory

* unauthorized inventory update fails
* concurrent adjustment is handled safely
* duplicate adjustment does not silently double-apply

### Requests/Enquiries

* anonymous submission follows approved rules
* Staff can process operational queue
* customer content remains immutable
* attachments cannot bypass authorization

### Notifications

* correct recipient scope
* notification failure does not change business state
* customer cannot alter notification ownership

### Staff lifecycle

* Staff cannot approve themselves
* Staff cannot perform Admin-only lifecycle actions
* Admin approval is auditable

---

# 52. Definition of Done

Phase 1.30 is complete only when:

* [ ] All Group A API contracts have been compared against one another.
* [ ] All global API conventions are consistent.
* [ ] Authentication and authorization boundaries are consistent.
* [ ] Resource ownership is consistent.
* [ ] Endpoint paths and methods are consistent.
* [ ] Endpoint IDs are unique and complete.
* [ ] Shared fields have consistent meanings and types.
* [ ] Server-controlled fields are consistently protected.
* [ ] Order state machine is unified.
* [ ] Cancellation rules are unified.
* [ ] Fulfillment branching is unified.
* [ ] Delivery-fee authority and lifecycle are unified.
* [ ] Financial calculations are unified.
* [ ] Group H payment boundary is explicit.
* [ ] Inventory and checkout behavior are consistent.
* [ ] Historical order snapshots are protected.
* [ ] Made-to-order request boundaries are consistent.
* [ ] Enquiry boundaries are consistent.
* [ ] Attachment authorization is consistent.
* [ ] Notification/event behavior is consistent.
* [ ] Error envelopes and HTTP mappings are consistent.
* [ ] Enum values are CLOSED throughout Version 1.
* [ ] Nullability rules are explicit.
* [ ] Date/time rules are consistent.
* [ ] Money representation is consistent.
* [ ] Idempotency rules are consistent.
* [ ] Concurrency rules are consistent.
* [ ] Customer/Staff/Admin permission matrix is internally consistent.
* [ ] Security threat review is complete.
* [ ] Next.js, Flutter, and operational-client compatibility has been checked.
* [ ] Obsolete requirements have been removed or explicitly superseded.
* [ ] Unresolved contradictions are recorded in `docs/decisions.md`.
* [ ] Canonical contract invariants have been documented.
* [ ] Future automated contract-test scenarios have been identified.
* [ ] No implementation code has been started.

---

# 53. STOP Condition

**STOP after all cross-domain inconsistencies have been resolved and the consolidated API documentation has been updated.**

Do not begin:

* canonical API example generation
* OpenAPI authoring
* Laravel implementation
* migrations
* models
* controllers
* policies
* middleware
* frontend API clients
* Flutter API clients
* payment integration

The next phase uses this stabilized contract as its input.

**Next phase: 1.31 — Define Canonical API Examples.**
