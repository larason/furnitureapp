# Phase 1.35 — Version 1 API Contract Freeze

## 1. Purpose

Formally freeze the **Version 1 API Contract** after completion of all Group A design, review, security, consistency, completeness, example, and OpenAPI phases.

This is the **last phase in Group A — API Contract**.

The purpose of this phase is to establish one stable baseline that later implementation phases must follow.

The frozen contract consists of:

```text
Business Rules
      ↓
API Contract
      ↓
Endpoint Inventory
      ↓
Canonical Examples
      ↓
OpenAPI Specification
```

After this phase, changes to the Version 1 API must be treated as deliberate contract changes rather than ordinary documentation edits.

---

# 2. Dependencies

All of Group A must be complete:

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
* Placeholder endpoint resolution
* Phase 1.32 — OpenAPI Contract Specification
* Phase 1.33 — API Contract Security Review
* Phase 1.34 — API Contract Consistency & Completeness Review

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

---

# 3. Freeze Objective

The objective is to reach:

> **A Version 1 API contract that implementation teams can consume without redesigning the API while they build it.**

The frozen contract must be:

* complete
* internally consistent
* security-reviewed
* example-backed
* machine-readable
* traceable
* implementation-independent

---

# 4. What Is Being Frozen

Freeze the following as the Version 1 API baseline:

### 4.1 Endpoint surface

* HTTP methods
* paths
* path parameters
* endpoint IDs
* operation IDs

### 4.2 Request contracts

* fields
* types
* requiredness
* nullability
* validation constraints
* writable fields

### 4.3 Response contracts

* fields
* types
* visibility
* nullability
* calculated values
* immutable values

### 4.4 Error contracts

* error envelope
* error codes
* HTTP status mappings
* validation behavior

### 4.5 Security contracts

* authentication
* authorization
* resource ownership
* role boundaries
* field-level access
* security-sensitive operations

### 4.6 State machines

* Order states
* permitted transitions
* fulfillment branches
* cancellation rules
* approved Staff lifecycle

### 4.7 Financial rules

* money representation
* subtotal
* delivery fee
* total
* financial mutability boundaries
* payment handoff boundary

### 4.8 Shared conventions

* pagination
* filtering
* sorting
* identifiers
* timestamps
* headers
* idempotency
* response formatting

### 4.9 Examples

* canonical request examples
* canonical response examples
* canonical error examples
* end-to-end workflow examples

### 4.10 OpenAPI

```text
docs/api/openapi.yaml
```

---

# 5. Canonical Frozen Documents

The following become the official Group A contract documents:

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

The endpoint inventory from Phase 1.19 must also remain authoritative wherever it is stored.

Do not create additional permanent contract documents merely to mark the freeze.

---

# 6. Version Identity

Define an explicit Version 1 contract identifier.

For example:

```text
API Version: v1
Contract Status: FROZEN
```

If the project uses a release identifier or contract revision number, record it in `docs/decisions.md`.

Do not invent semantic versioning rules for individual endpoints unless the project explicitly adopts them.

---

# 7. Freeze Date

Record the actual date on which the contract is frozen.

Do not hard-code a fictional date.

Record:

```text
Contract freeze date
Contract version
Reviewer/owner if the project's process requires it
```

Do not record personal credentials or unnecessary identifying information.

---

# 8. Frozen Scope Summary

The frozen Version 1 API supports:

```text id="w3f6p8"
Public catalog browsing
Customer registration/authentication
Customer profile
Customer cart
Authenticated checkout
Pickup fulfillment
Delivery fulfillment
Variable delivery fees
Customer orders
Customer order tracking
Made-to-order requests
General enquiries
In-app notifications
Inventory operations
Staff operations
Staff approval/lifecycle
Admin operational control
Audit creation
```

The frozen Version 1 API does **not** automatically include:

```text id="f7i4o6"
Payment gateway implementation
Email delivery
Push notification delivery
SMS
Saved address book
Live GPS tracking
Full CRM
Chat platform
Quotation engine
Custom roles
Custom permission builder
Staff control over customer accounts
```

Those remain deferred or belong to later groups as already documented.

---

# 9. Frozen Actor Model

The Version 1 actor roles are permanently defined for this contract as:

```text
CUSTOMER
STAFF
ADMIN
```

These are CLOSED.

Do not add:

```text
SUPER_ADMIN
MANAGER
OPERATOR
SUPPORT
EDITOR
WAREHOUSE
DELIVERY_AGENT
```

during ordinary implementation.

A new role requires an explicit contract-change process.

---

# 10. Frozen Authentication Boundary

The frozen contract retains:

```text
Public browsing
        ↓
No authentication required

Checkout
        ↓
Authentication required
```

Customers may self-register.

Staff/Admin do not self-register through public customer registration.

Authentication is shared across:

```text
Next.js
Flutter
```

using the approved authentication contract.

Authentication implementation may evolve internally as long as externally observable behavior does not violate the frozen contract.

---

# 11. Frozen Authorization Boundary

Authorization remains:

```text
authenticated identity
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

Default deny applies to protected operations.

Staff cannot use operational privileges to control or restrict ordinary customer accounts.

Admin has the highest approved operational authority but does not receive an undocumented universal bypass.

---

# 12. Frozen Customer Boundary

Customers own:

* their cart
* their orders
* their private notifications
* their profile
* their authenticated requests/enquiries where applicable

Customers cannot control:

* order status
* delivery fee
* subtotal
* total
* inventory
* Staff lifecycle
* roles
* permissions

The customer cancellation window remains:

```text
20 minutes
```

subject to the approved cancellable states.

---

# 13. Frozen Staff Boundary

Staff handles normal operational ecommerce workflows.

Staff may perform approved operations involving:

* products/catalog
* inventory
* orders
* fulfillment
* delivery fees
* made-to-order requests
* enquiries
* operational notifications

Staff may not:

* manage customer passwords
* change customer roles
* change customer ownership
* restrict ordinary customer browsing
* restrict ordinary customer ordering
* impersonate customers
* perform Admin-only Staff lifecycle operations

---

# 14. Frozen Admin Boundary

Admin has the highest approved administrative authority.

Admin may perform approved Staff operations and Admin-only operations.

Admin-only areas include, where explicitly present:

```text
Staff management
Staff approval/lifecycle
Audit-log access
other explicitly approved administrative functions
```

Admin does not gain undocumented capabilities merely from having the highest role.

---

# 15. Frozen Order State Model

The frozen Order lifecycle must remain consistent across all documentation and OpenAPI.

Core flow:

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

Only approved controlled actions may perform these transitions.

No implementation may introduce direct arbitrary status assignment.

---

# 16. Frozen Cancellation Rule

Customer cancellation remains:

```text
20-minute cancellation window
```

The server determines eligibility using server-authoritative time.

The client cannot supply its own timestamp to extend the window.

Cancellation is an explicit domain action.

It is not order deletion.

---

# 17. Frozen Fulfillment Model

The fulfillment type enum remains:

```text
PICKUP
DELIVERY
```

These values are CLOSED.

Rules:

```text
PICKUP
→ delivery_fee = 0
```

```text
DELIVERY
→ delivery fee must be established according to the Staff/Admin workflow
```

Pickup cannot receive delivery operations.

Delivery cannot receive pickup-only operations.

---

# 18. Frozen Delivery-Fee Rule

The Version 1 delivery fee is **variable**.

The old flat:

```text
TZS 20,000
```

assumption is not part of the frozen contract.

Customer:

```text
chooses fulfillment type
```

Staff/Admin:

```text
sets delivery fee
```

Server:

```text
calculates authoritative total
```

The customer cannot submit or override the delivery fee.

The server calculates:

```text
total = subtotal + delivery_fee
```

according to the approved money rules.

---

# 19. Frozen Payment Boundary

Group A freezes only the order/payment boundary required by later payment work.

Group A does not freeze a particular payment provider implementation.

The contract establishes:

```text
Checkout
    ↓
Order
    ↓
Authoritative payable amount
    ↓
Group H Payment
```

Group H must consume the authoritative server-calculated amount.

Group H must not invent a second delivery-fee calculation.

---

# 20. Frozen Inventory Boundary

The Version 1 contract retains:

```text
Catalog availability = informational
Cart = no inventory reservation
Checkout = authoritative inventory revalidation
Inventory = Staff/Admin operational resource
```

Inventory adjustments are controlled operations.

Inventory changes must remain concurrency-safe and auditable.

---

# 21. Frozen Historical Order Data

Order history is authoritative transaction history.

Historical order data must not depend on future changes to:

* product names
* product prices
* product availability
* customer profile
* current cart data

Historical order values remain immutable through normal API operations.

---

# 22. Frozen Request/Enquiry Boundary

Made-to-order requests remain distinct from Orders.

General enquiries remain distinct from Orders.

Neither automatically creates:

```text
Order
Payment
Inventory reservation
```

unless a future contract change explicitly introduces such a workflow.

---

# 23. Frozen Notification Boundary

Notifications remain downstream representations of business events.

They are not the source of truth.

Core business transactions must not be rolled back because notification delivery fails.

Version 1 includes the approved in-app notification contract.

Real:

```text
email
push
SMS
```

delivery remains outside the current Group A implementation scope.

---

# 24. Frozen Error Contract

The error envelope remains:

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

All API domains use the same contract.

No Laravel exceptions, database errors, stack traces, or infrastructure details may become API error responses.

---

# 25. Frozen Security Contract

The security findings from Phase 1.33 must be resolved or formally accepted according to the project decision process.

The frozen contract must prohibit:

```text
IDOR
mass assignment of privileged fields
role escalation
state skipping
financial tampering
inventory tampering
notification ownership bypass
attachment authorization bypass
audit spoofing
unauthorized customer-account control
```

---

# 26. Frozen OpenAPI Specification

The canonical machine-readable API contract is:

```text
docs/api/openapi.yaml
```

It must:

* validate successfully
* have no unresolved references
* have unique operation IDs
* contain all approved paths
* contain all approved schemas
* contain appropriate security requirements
* include canonical examples
* reflect closed enums
* match written documentation

The implementation must not silently modify OpenAPI to justify behavior that is not present in the approved contract.

---

# 27. OpenAPI Integrity Hash

If the project has a reproducible release process, record a checksum or equivalent immutable artifact identifier for the frozen OpenAPI specification.

This is optional but recommended for CI/release traceability.

Do not require an external registry merely to complete this phase.

---

# 28. Contract Baseline

Record the following baseline:

```text
API version: v1
OpenAPI file: docs/api/openapi.yaml
Endpoint inventory: Phase 1.19 canonical inventory
Examples: Phase 1.31 canonical examples
Security review: Phase 1.33 completed
Consistency review: Phase 1.34 completed
Status: FROZEN
```

This becomes the reference point for implementation.

---

# 29. Freeze Rules

After freeze:

### Allowed without a contract-version change

Internal implementation changes that do not alter externally observable API behavior.

Examples:

* database indexing
* internal service organization
* repository structure
* controller organization
* caching implementation
* query optimization
* internal class names
* logging implementation

provided they preserve the frozen API behavior.

### Not allowed without contract review

Changes to:

* endpoint paths
* HTTP methods
* required fields
* response fields
* enum values
* error codes
* authorization behavior
* resource ownership
* state transitions
* financial rules
* delivery-fee authority
* pagination contract
* identifiers
* authentication behavior

---

# 30. Breaking Change Definition

Treat the following as breaking API changes unless explicitly versioned:

```text id="q2b9ea"
removing an endpoint
renaming an endpoint
changing HTTP method
removing a response field relied upon by clients
changing field type
making an optional request field required
changing enum semantics
changing ownership/authorization expectations
changing order state behavior
changing money representation
changing authentication requirements
```

Do not casually classify a breaking change as "small."

---

# 31. Non-Breaking Change Definition

Potentially non-breaking changes may include:

```text id="h8frnz"
adding optional response metadata
adding new non-required response fields
performance improvements
internal implementation changes
additional logging
database indexes
```

However, review generated-client and strict-schema implications before treating a change as safe.

---

# 32. Post-Freeze Change Process

Any proposed API change after freeze must follow:

```text id="m44q96"
Change request
    ↓
Impact analysis
    ↓
Breaking/non-breaking classification
    ↓
Contract review
    ↓
Decision record
    ↓
OpenAPI update
    ↓
Example update
    ↓
Validation
    ↓
Version/release decision
```

Do not edit the frozen contract casually inside an implementation task.

---

# 33. Versioning Rule

The current frozen API remains:

```text
/api/v1
```

Do not create:

```text
/api/v1.1
/api/v1.5
```

for ordinary contract changes.

Use the project's formal API versioning strategy when it becomes necessary.

A new major contract version should be deliberate and documented.

---

# 34. Compatibility Rule for Clients

Next.js and Flutter clients must treat the API contract as server-defined.

Clients must not:

* calculate authoritative order totals
* assume inventory is reserved
* mutate order states locally as truth
* assume delivery fees
* assume future enum values
* bypass authentication rules
* depend on undocumented fields

Client implementations may cache state but must reconcile against server authority.

---

# 35. Contract Change Ownership

Define who is authorized to approve API contract changes.

At minimum require a deliberate project-level decision rather than allowing any implementation commit to redefine the API.

The actual person/team name may be recorded according to the project's governance process.

Do not embed personal credentials or sensitive organizational information in the API contract.

---

# 36. Final Documentation Synchronization

After freeze, verify these documents agree:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Search again for:

```text
20,000
flat delivery fee
guest checkout
anonymous checkout
SUPER_ADMIN
OPEN enum
generic order PATCH
customer suspension by Staff
customer-controlled total
customer-controlled delivery fee
```

No obsolete active Version 1 requirement may remain.

---

# 37. Final Example Synchronization

Verify all canonical examples still validate against the frozen OpenAPI specification.

At minimum recheck:

* pickup checkout
* delivery checkout
* delivery fee assignment
* order acceptance
* processing
* pickup fulfillment
* delivery fulfillment
* cancellation
* inventory adjustment
* Staff approval
* authorization failure
* error responses

No example may describe pre-freeze behavior that is no longer approved.

---

# 38. Final OpenAPI Validation

Run the selected OpenAPI validator one final time.

Require:

```text id="zr0cdm"
Valid YAML
Valid OpenAPI document
All references resolve
All path parameters defined
Unique operation IDs
Valid schemas
Valid responses
Examples conform
Security definitions valid
No unintended undocumented paths
```

Record the validation result.

---

# 39. Final Contract Diff

Compare the final frozen contract against the immediately preceding Phase 1.34 state.

The expected result should be:

```text id="8d59hn"
No unresolved changes
```

If any change exists, determine whether it is:

```text intentional correction
```

or:

```text accidental drift
```

Do not freeze with unexplained differences.

---

# 40. Final Security Recheck

Perform a final targeted scan for:

```text id="ysn1z2"
role escalation
IDOR
mass assignment
state manipulation
financial tampering
delivery-fee tampering
inventory tampering
notification authorization
attachment authorization
customer-account control
error disclosure
replay/idempotency weaknesses
```

This is a final regression check against Phase 1.33.

---

# 41. Freeze Decision Record

Add a final decision entry to:

```text
docs/decisions.md
```

recording that Version 1 API Contract has been frozen.

The record should contain:

```text
Decision
Status: ACCEPTED/FROZEN
Scope
Reference documents
Contract version
Freeze date
Known deferred items
Change policy
```

Keep it concise.

Do not reproduce the entire API contract in the decision record.

---

# 42. Recommended Freeze Marker

Add a clear marker to the consolidated API documentation:

```text
VERSION 1 API CONTRACT STATUS: FROZEN
```

Also identify:

```text
Contract version: v1
Freeze date: <actual date>
```

This makes contract state obvious to human developers and AI agents.

---

# 43. Agent Instruction Update

Update `AGENTS.md` with a concise rule:

```text
The Version 1 API contract is frozen.
Do not change API paths, request/response schemas, enums, authorization behavior,
business-state transitions, financial rules, or other externally observable API
behavior without following the post-freeze contract-change process.
```

Do not duplicate the full API specification inside `AGENTS.md`.

---

# 44. Implementation Handoff

After freeze, Group B may treat the contract as implementation input.

The implementation order must follow the project's dependency-first plan.

The implementation agent must:

```text
read frozen contract
        ↓
identify affected endpoint/resource
        ↓
implement contract behavior
        ↓
write tests against contract
        ↓
validate implementation
```

The implementation agent must not redesign the API while implementing it.

If implementation reveals a genuine contract defect, stop the affected implementation task and use the post-freeze change process.

---

# 45. Group A Exit Criteria

Group A is complete only when:

* [ ] All phases 1.16–1.34 are complete.
* [ ] API Contract Freeze is formally recorded.
* [ ] Version 1 status is marked FROZEN.
* [ ] `docs/api/openapi.yaml` is valid.
* [ ] Endpoint inventory matches OpenAPI.
* [ ] Canonical examples match OpenAPI.
* [ ] Written API contract matches OpenAPI.
* [ ] Business rules match the frozen contract.
* [ ] Authorization rules match the frozen contract.
* [ ] Security review findings are resolved/accepted.
* [ ] No unresolved Critical/High API contract issue remains.
* [ ] No obsolete flat TZS 20,000 delivery-fee requirement remains.
* [ ] Delivery-fee authority is unambiguous.
* [ ] Payment boundary is explicit.
* [ ] Customer/Staff/Admin boundaries are explicit.
* [ ] Version 1 enums are CLOSED.
* [ ] Order state machine is frozen.
* [ ] Customer cancellation window is frozen at 20 minutes.
* [ ] Public/private resource visibility is frozen.
* [ ] Error contract is frozen.
* [ ] Idempotency/concurrency behavior is frozen.
* [ ] Traceability is complete.
* [ ] Post-freeze change rules are documented.
* [ ] No implementation code has been started as part of Group A.

---

# 46. STOP Condition — Group A Complete

**STOP HERE. GROUP A IS COMPLETE.**

Do not continue into backend implementation inside Phase 1.35.

The following are explicitly outside this phase:

* Laravel application setup
* Laravel API routing implementation
* migrations
* Eloquent models
* Form Requests
* API Resources
* policies
* middleware
* services
* repositories
* queues/jobs
* tests implementation
* Next.js API client implementation
* Flutter API client implementation
* payment implementation
* notification delivery implementation

The frozen contract is now the implementation baseline.

---

# 47. Group B Handoff

The next phase begins the Laravel backend foundation.

The implementation team must start from:

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

and must treat the Version 1 API contract as **frozen input**.

**Group A — API Contract: COMPLETE.**

**Next: Group B — Laravel Backend Foundation.**
