# Phase 1.29 — Define Staff/Admin Operational API Contract

## 1. Purpose

Define the complete Version 1 API contract for authenticated **STAFF** and **ADMIN** operational workflows.

This phase establishes exactly what privileged operational users can see and do through the API, how staff and admin permissions differ, how operational mutations are represented as controlled actions, how sensitive customer data is protected, how delivery fees are assigned, and what actions must be auditable.

This phase must produce a contract that can be implemented consistently by:

- Laravel API
- Next.js administration/operations interface
- Flutter staff/admin interface, if introduced
- future OpenAPI specification
- automated authorization and integration tests

This phase is part of **Group A — API Contract**. It does not implement backend code.

---

# 2. Dependencies

Complete and treat these phases as authoritative before starting:

- Phase 1.16 — API Error Contract
- Phase 1.17 — Authentication Contract
- Phase 1.18 — Authorization and Permission Contract
- Phase 1.19 — Concrete API Endpoint Inventory
- Phase 1.20 — Catalog API Contract
- Phase 1.21 — Cart API Contract
- Phase 1.22 — Checkout API Contract
- Phase 1.23 — Order API Contract
- Phase 1.24 — Order Tracking and Fulfillment API Contract
- Phase 1.25 — Made-to-Order Request API Contract
- Phase 1.26 — General Enquiry API Contract
- Phase 1.27 — Notification API Contract
- Phase 1.28 — User/Profile API Contract

Also treat these existing project documents as authoritative inputs:

- `AGENTS.md`
- `docs/VISION.md`
- `docs/domain/business-rules.md`
- `docs/decisions.md`
- `docs/api/api-contract.md`
- `docs/api/api-resources.md`
- `docs/api/api-conventions.md`

Do not contradict an already approved rule merely to simplify this phase.

Where documents disagree, record the conflict in `docs/decisions.md` and resolve it before freezing this phase.

---

# 3. Authoritative Business Model for This Phase

The API uses exactly three Version 1 actor roles:

```text
CUSTOMER
STAFF
ADMIN
```

These role values are **CLOSED**.

The operational hierarchy is:

```text
ADMIN
  ↓
STAFF
  ↓
CUSTOMER
```

This does **not** mean Staff inherit every Customer-management capability.

The intended boundaries are:

### CUSTOMER

Owns and manages their normal ecommerce experience.

Customers may:

- browse the public catalog
- maintain their own profile
- manage their own cart
- checkout
- view their own orders
- track their own orders
- cancel eligible own orders
- submit made-to-order requests
- submit general enquiries
- read their own notifications

### STAFF

Handles normal day-to-day ecommerce operations.

Staff may operationally manage resources such as:

- products/catalog operational data
- inventory
- orders
- fulfillment
- delivery fees
- made-to-order requests
- general enquiries
- operational notifications
- other explicitly authorized operational workflows

Staff must **not** gain customer-account administration authority merely because they are operational users.

### ADMIN

Has the highest project-defined administrative authority.

Admin may:

- approve and manage Staff
- perform Staff operational functions
- perform explicitly approved administrative operations
- review operational/audit information
- manage system-level operational configuration where separately authorized

Admin still must not use generic "superuser bypass" behavior that defeats business invariants, auditability, ownership protections, or security rules.

---

# 4. Primary Goal

At the end of this phase, every Staff/Admin API operation must have an explicit answer to:

1. Who may call it?
2. What resource is affected?
3. What exact action is allowed?
4. What business state permits the action?
5. What fields may be supplied by the client?
6. What fields are server-controlled?
7. What information is visible to the caller?
8. What concurrency/idempotency rule applies?
9. What errors may be returned?
10. Does the action require an audit event?
11. Does the action generate a notification?
12. Does the action affect payment eligibility?
13. Can the action affect a customer account?

If an endpoint cannot answer these questions, it is not ready for the API contract.

---

# 5. Privileged API Design Principles

Use these rules throughout the phase.

## 5.1 No generic administrative PATCH

Do not design endpoints such as:

```text
PATCH /admin/orders/{id}
PATCH /staff/orders/{id}
PATCH /admin/users/{id}
```

when the operation changes business state.

Use explicit controlled actions.

Examples:

```text
POST /api/v1/staff/orders/{order}/accept
POST /api/v1/staff/orders/{order}/start-processing
POST /api/v1/staff/orders/{order}/ready-for-pickup
POST /api/v1/staff/orders/{order}/ship
POST /api/v1/staff/orders/{order}/deliver
POST /api/v1/staff/orders/{order}/set-delivery-fee
```

The exact names must align with the endpoint naming convention already established in earlier phases.

---

## 5.2 Default deny

A Staff or Admin user receives no permission merely because a route exists.

Authorization must be evaluated using:

```text
authenticated actor
+ role
+ resource
+ action
+ resource ownership/context
+ business state
+ operational scope, if any
```

---

## 5.3 Customer accounts remain protected

Staff must not be able to:

- disable a customer's account
- block a customer from browsing
- block a customer from ordering
- change a customer's role
- change customer permissions
- change customer ownership
- impersonate a customer
- change a customer's password
- arbitrarily edit customer security attributes
- delete a customer account through ordinary operations

Do not create a hidden "staff customer control" endpoint that bypasses this boundary.

Any customer-account administration capability for Admin must be explicitly defined and justified rather than inherited from the Staff role.

---

## 5.4 No authorization through identifiers alone

Do not assume that possession of an order ID, product ID, request ID, enquiry ID, or user ID grants access.

Always perform server-side authorization.

---

## 5.5 Sensitive fields are never client-controlled

Never accept role, permissions, internal status, ownership, audit metadata, approval actor, timestamps, or other privileged fields directly from the client unless a dedicated controlled operation explicitly defines that field.

---

# 6. Staff/Admin API Surface

Define the operational API under the project-wide Version 1 API namespace.

Recommended structure:

```text
/api/v1/staff/...
/api/v1/admin/...
```

Use `/staff` for normal operational functions.

Use `/admin` for administrative functions that Staff must not perform.

Do not duplicate every Staff route under `/admin` merely for convenience.

Where Admin is explicitly allowed to perform the same operational action, authorization should normally permit:

```text
STAFF or ADMIN
```

on the same operational endpoint.

---

# 7. Operational Catalog Contract

Define the Staff/Admin contract for catalog management consistently with Phase 1.20.

The public catalog representation and internal operational representation must remain separate.

Operational users may require fields that public customers must never receive.

Examples include:

- internal inventory information
- operational stock values
- internal notes
- unpublished/draft state
- operational metadata
- administrative flags

Do not expose private operational fields through public catalog endpoints merely because Staff/Admin can see them.

## 7.1 Required operations

At minimum define contracts for:

- list products for operations
- retrieve a product operationally
- create a product
- update permitted product fields
- publish/unpublish where the catalog model supports publication state
- manage categories where supported
- manage permitted product variants
- manage product images/media metadata where supported

Do not introduce product states that contradict the existing domain model.

If product publication state is already defined elsewhere, use that exact state model.

---

# 8. Inventory Operational Contract

Inventory is an operational resource, not a customer-editable resource.

Define endpoints for the operational inventory workflow already identified in the endpoint inventory.

At minimum establish contracts for:

- viewing inventory
- viewing inventory by product/variant
- increasing stock through a controlled operation
- decreasing stock through a controlled operation where business rules permit
- inventory adjustment with a reason
- identifying insufficient stock
- identifying inventory conflicts

Do not use arbitrary:

```text
PATCH /inventory/{id}
```

to let Staff directly rewrite stock.

Prefer explicit operations such as:

```text
POST /api/v1/staff/inventory/{inventory}/adjust
```

with a controlled request body.

Example conceptual input:

```json
{
  "quantity_delta": 10,
  "reason": "STOCK_RECEIPT"
}
```

The actual reason values must use the project's approved closed enum set.

The server must calculate:

```text
new_quantity = current_quantity + quantity_delta
```

within a transaction and must prevent invalid negative inventory where the business rules prohibit it.

---

# 9. Order Operations Contract

Orders are one of the most important Staff/Admin operational resources.

Staff and Admin may read operational order information needed to fulfill orders, but the contract must distinguish:

```text
customer-facing order representation
```

from:

```text
staff/admin operational order representation
```

The operational representation may contain:

- customer contact information necessary for fulfillment
- order reference
- order lines
- product/variant historical snapshots
- fulfillment type
- delivery address snapshot where applicable
- delivery fee state
- financial totals
- payment-related summary fields that are safe to expose
- internal operational metadata where approved
- fulfillment state
- timestamps
- cancellation eligibility/state
- operational notes if such notes are part of the approved model

Do not expose secrets such as:

- passwords
- authentication tokens
- payment credentials
- raw card data
- internal security secrets

Payment-specific fields remain constrained by Group H.

---

# 10. Staff Order Listing

Define an operational order-list endpoint supporting appropriate filters and pagination.

Recommended conceptual shape:

```text
GET /api/v1/staff/orders
```

Supported filters should be limited to approved operational needs.

Potential filters include:

- order status
- fulfillment type
- payment state
- delivery-fee state
- date range
- customer order reference
- search term where approved

Do not create uncontrolled arbitrary field filtering.

Pagination must follow the global API convention.

The default response must not expose customer data unrelated to operational fulfillment.

---

# 11. Order Detail

Define:

```text
GET /api/v1/staff/orders/{order}
```

and make the authorization explicit:

```text
STAFF or ADMIN
```

The response must contain enough data to perform normal fulfillment operations without requiring access to the customer's entire account.

Do not embed:

- customer's other orders
- customer's entire cart
- customer's password/security profile
- unrelated enquiries or requests

unless the API contract explicitly defines a separate authorized relationship.

---

# 12. Controlled Order State Actions

Use explicit endpoints for the approved order state machine.

The action set must exactly match the state transitions defined in Phase 1.23 and Phase 1.24.

Conceptually:

```text
PENDING_PAYMENT
    ↓
PAID
    ↓
ACCEPTED
    ↓
PROCESSING
```

Then use the fulfillment branch:

```text
PICKUP:
PROCESSING
    ↓
READY_FOR_PICKUP
    ↓
COMPLETED
```

or:

```text
DELIVERY:
PROCESSING
    ↓
SHIPPED
    ↓
DELIVERED
    ↓
COMPLETED
```

Do not permit clients to directly submit arbitrary target states.

For example, this is prohibited:

```json
{
  "status": "DELIVERED"
}
```

through a generic update route.

Instead the server must expose a controlled action.

Each action must validate:

- actor authorization
- current order state
- fulfillment type
- payment prerequisites where applicable
- required fields
- concurrency/version state
- business invariants

---

# 13. Order Acceptance

Where the approved state machine requires acceptance, define the controlled acceptance operation.

Conceptually:

```text
POST /api/v1/staff/orders/{order}/accept
```

Only an order in the appropriate predecessor state may be accepted.

The server must:

1. lock/recheck the current order state as required
2. verify authorization
3. apply the state transition transactionally
4. record relevant timestamps
5. create the corresponding business event
6. trigger downstream notification creation without allowing notification failure to invalidate the order transition

The customer must not be able to invoke this action.

---

# 14. Processing Action

Define the controlled action for moving an accepted order into processing.

Conceptually:

```text
POST /api/v1/staff/orders/{order}/start-processing
```

The action must reject invalid predecessor states.

Staff cannot skip state validation merely because the target state is operationally plausible.

---

# 15. Pickup Fulfillment Actions

For pickup orders define the actions required by the approved fulfillment contract.

Conceptually:

```text
POST /api/v1/staff/orders/{order}/ready-for-pickup
POST /api/v1/staff/orders/{order}/complete-pickup
```

The actual route names must follow the project's naming conventions.

Do not allow delivery-only actions on pickup orders.

---

# 16. Delivery Fulfillment Actions

For delivery orders define the controlled actions required by the approved workflow.

Conceptually:

```text
POST /api/v1/staff/orders/{order}/ship
POST /api/v1/staff/orders/{order}/deliver
POST /api/v1/staff/orders/{order}/complete
```

Do not permit:

- shipping a pickup order
- delivering a pickup order
- completing an order from an invalid predecessor
- changing the fulfillment branch after operations have already begun unless an explicit business transition exists

---

# 17. Variable Delivery Fee Contract

This phase must make the variable delivery-fee workflow explicit.

The customer chooses:

```text
PICKUP
```

or:

```text
DELIVERY
```

during checkout.

Pickup has:

```text
delivery_fee = 0
```

Delivery requires Staff/Admin to assign the actual delivery fee.

### Required Version 1 workflow

Use the following contract unless `docs/decisions.md` contains an already-approved conflicting rule:

1. Customer submits checkout with `fulfillment_type = DELIVERY`.
2. Server creates the order in the approved pre-payment state.
3. Delivery fee is initially **pending**.
4. Payment must not be considered payable/final until the delivery fee is assigned.
5. Staff or Admin sets the delivery fee using a dedicated action.
6. Server recalculates the authoritative order total.
7. Customer receives the updated order state/price through the order API and notification system.
8. Group H payment processing uses the final server-calculated amount.

This avoids allowing either the customer or payment integration to guess the delivery fee.

Do **not** let the customer submit the delivery fee.

Do **not** permit Staff/Admin to edit `total` directly.

The server must calculate:

```text
total = subtotal + delivery_fee
```

using the approved currency/rounding rules.

---

# 18. Delivery Fee Action

Define a dedicated endpoint, conceptually:

```text
POST /api/v1/staff/orders/{order}/set-delivery-fee
```

or the equivalent action name consistent with existing API conventions.

Request body should contain only the variable fee input necessary for the operation.

Example:

```json
{
  "delivery_fee": {
    "amount": 15000,
    "currency": "TZS"
  }
}
```

The server controls:

- order subtotal
- final total
- currency
- order identifiers
- actor metadata
- timestamps

Validation must require:

- order exists
- actor is authorized
- fulfillment type is `DELIVERY`
- order is still in a fee-editable state
- fee is non-negative
- value meets currency precision rules
- order has not entered a state where the financial amount is immutable
- concurrent modifications are detected

A pickup order must reject this operation.

Do not allow a delivery fee change after the order reaches a payment/fulfillment state where the project's financial rules make the amount immutable, unless a separate approved adjustment/refund model exists.

---

# 19. Delivery Fee Recalculation

When Staff/Admin sets or updates the delivery fee:

```text
subtotal
+
delivery_fee
=
total
```

must be calculated by the server.

Never trust a client-supplied total.

Never permit:

```json
{
  "delivery_fee": {
    "amount": 15000,
    "currency": "TZS"
  },
  "total": {
    "amount": 99999,
    "currency": "TZS"
  }
}
```

to control the final amount.

The supplied `total` field must either be rejected or ignored according to the project's global input-validation convention. Prefer rejecting unexpected privileged fields where practical.

---

# 20. Made-to-Order Operational Contract

Made-to-order requests are not normal Orders.

Define Staff/Admin endpoints for:

- listing requests
- viewing a request
- filtering requests
- updating the approved operational status
- adding internal operational information if such a model exists
- attaching approved internal follow-up data if already modeled

Do not automatically convert a request into an Order.

Do not introduce quotation/payment behavior here unless the project has explicitly added that workflow.

Attachments must inherit the request's authorization boundary.

Staff must only see requests they are authorized to handle.

Admin has broader operational visibility.

---

# 21. General Enquiry Operational Contract

Define Staff/Admin endpoints for:

- list enquiries
- view enquiry
- filter/search enquiries
- update approved enquiry workflow state
- add internal notes if approved

The original customer-submitted message must remain immutable.

Do not let Staff overwrite the original enquiry text.

Keep customer-provided content separate from internal operational notes.

Do not turn an enquiry into an order automatically.

---

# 22. Notification Operational Contract

Use the notification contract from Phase 1.27.

Staff/Admin notification operations should distinguish between:

```text
reading operational notifications
```

and:

```text
creating business notifications
```

Staff must not manually fabricate arbitrary customer notifications unless a dedicated approved notification action exists.

Notifications should normally be generated from business events.

Operational notification endpoints may support:

- Staff notification listing
- Staff notification read state
- Admin operational notification listing where applicable
- mark-as-read actions

Do not allow ordinary users to change:

- recipient
- notification type
- source entity
- notification content
- business-event identity

---

# 23. Customer Data Visibility

Staff/Admin access to customer information must be **purpose-limited**.

For normal order fulfillment, expose only information needed for:

- identifying the purchaser
- contacting the purchaser where operationally necessary
- fulfilling pickup/delivery
- resolving the order operationally

Do not expose unrelated customer information.

For example, an order endpoint should not implicitly return:

- all of the customer's previous orders
- complete notification history
- unrelated requests
- unrelated enquiries
- authentication information
- security credentials

The customer account is not the Staff operational workspace.

---

# 24. Staff Management Contract

Staff lifecycle is an Admin-only administrative responsibility.

Define Admin endpoints for the approved Staff-management workflow.

At minimum contractually cover:

- list Staff
- retrieve Staff details
- create/invite Staff through the approved mechanism
- approve Staff
- suspend/deactivate Staff where the approved lifecycle supports it
- restore/reactivate Staff where supported
- view Staff operational status

Recommended conceptual routes:

```text
GET  /api/v1/admin/staff
GET  /api/v1/admin/staff/{user}
POST /api/v1/admin/staff
POST /api/v1/admin/staff/{user}/approve
POST /api/v1/admin/staff/{user}/suspend
POST /api/v1/admin/staff/{user}/reactivate
```

Use only the lifecycle states already approved by the project.

Do not invent a second independent Staff-role system.

---

# 25. Staff Approval Rules

Staff approval must be explicit.

Admin approval must be:

- authenticated
- authorized
- server-side
- auditable
- protected against self-escalation

A Staff user must not be able to approve:

- themselves
- another Staff member
- a role change submitted by a client

unless the approved administrative model explicitly allows that action.

The role itself remains server-controlled.

---

# 26. Staff Creation and Credentials

The Staff-management API must not accept arbitrary plaintext passwords as part of a generic Admin user-management request.

Authentication/credential issuance belongs to the Authentication contract.

Since real email delivery is deferred to Group R, do not assume an email invitation can be implemented during this phase.

Define the Staff lifecycle contract without coupling it to immediate email delivery.

If an activation mechanism is required, record the mechanism separately in the Authentication/security decision set rather than inventing insecure credential behavior here.

---

# 27. Role and Permission Mutation

Do not expose generic APIs such as:

```text
PATCH /admin/users/{id}
```

with:

```json
{
  "role": "ADMIN"
}
```

Role changes are privileged controlled operations.

Version 1 has a closed role model:

```text
CUSTOMER
STAFF
ADMIN
```

Any endpoint that changes a role must be:

- Admin-only
- explicitly named
- audited
- validated against self-escalation
- validated against the approved role-transition policy

Where possible, Staff role approval should be modeled as a dedicated lifecycle action rather than arbitrary role mutation.

---

# 28. Customer Account Administration Boundary

Do not automatically create:

```text
/admin/customers/*
```

merely because Admin has the highest role.

Admin customer-account management requires an explicit business need and explicit rules.

The Version 1 default is:

```text
Customer account is customer-owned.
Staff cannot control it.
Admin does not receive an unrestricted customer-control API automatically.
```

If later business requirements require account suspension, fraud controls, legal retention operations, or similar functions, introduce dedicated contracts with separate business rules and audit requirements.

Do not smuggle those capabilities into this phase.

---

# 29. Admin Operational Scope

Admin may perform Staff operational actions where the authorization contract explicitly permits:

```text
STAFF OR ADMIN
```

Do not duplicate routes unnecessarily.

For example:

```text
POST /api/v1/staff/orders/{order}/accept
```

may be accessible by both Staff and Admin.

Admin-only actions remain under:

```text
/api/v1/admin/...
```

The API documentation must make this distinction explicit.

---

# 30. Operational Notes

Where internal notes are required for:

- orders
- made-to-order requests
- enquiries
- catalog items

define notes as a separate operational field/resource rather than allowing arbitrary customer-visible text mutation.

Do not expose internal notes through customer endpoints.

Do not allow customers to submit fields that map directly into internal Staff/Admin notes.

If notes are not yet part of the approved domain model, do not add a full notes subsystem in this phase; record it as deferred.

---

# 31. Audit Contract

Privileged actions that change operational or administrative state must produce an audit event.

At minimum audit:

- actor ID
- actor role
- action
- resource type
- resource identifier
- previous relevant state
- resulting relevant state
- timestamp
- request ID/correlation ID where available

Especially audit:

- Staff approval
- Staff suspension/deactivation
- role changes
- delivery-fee changes
- inventory adjustments
- order state transitions
- operational request/enquiry state changes
- privileged catalog changes
- administrative configuration changes

Never trust a client-provided audit actor.

The server derives the actor identity from authentication.

---

# 32. Audit Read API

If an Admin audit viewer is included in Version 1, define it as read-only.

Recommended conceptual endpoint:

```text
GET /api/v1/admin/audit-logs
```

Supported filters should be constrained and indexed appropriately.

Potential filters:

- actor
- action
- resource type
- resource ID
- date range

Do not allow Admin clients to edit or delete audit history through the ordinary API.

If the project decides that audit history is internal-only for V1, record that explicitly in `docs/decisions.md` and omit the read endpoint from the public API contract.

The creation of audit events remains mandatory regardless.

---

# 33. Concurrency Requirements

Operational mutations can race with each other.

This is especially important for:

- inventory
- order acceptance
- order processing
- delivery-fee assignment
- shipping
- delivery completion
- Staff approval

Each controlled mutation must define its concurrency rule.

Acceptable approaches include:

- transactional row locking
- expected-version checks
- state revalidation inside a transaction
- idempotency keys where repeated requests are possible

The API must return the standard conflict error when the requested transition is no longer valid because another operation changed the resource first.

Do not rely solely on UI state to prevent double actions.

---

# 34. Idempotency Requirements

Classify each operational write.

At minimum evaluate idempotency for:

- delivery-fee assignment
- order state transitions
- inventory adjustments
- Staff approval/deactivation
- any action likely to be retried by a mobile client or unstable network

Do not make an inherently non-idempotent operation silently safe by merely ignoring repeated requests.

Document the exact behavior for repeat submissions.

---

# 35. Operational API Error Contract

Use the global error contract from Phase 1.16.

Expected categories include:

```text
401 AUTHENTICATION_REQUIRED
403 FORBIDDEN
404 RESOURCE_NOT_FOUND
409 CONFLICT
422 INVALID_VALUE / BUSINESS_RULE_VIOLATION
429 RATE_LIMITED
500 INTERNAL_SERVER_ERROR
```

Examples:

### Unauthorized Staff action

```text
403 FORBIDDEN
```

### Invalid order state transition

```text
409 CONFLICT
```

### Delivery fee supplied for pickup order

```text
422 BUSINESS_RULE_VIOLATION
```

### Another operator changed the order first

```text
409 CONFLICT
```

### Staff attempting Admin-only approval

```text
403 FORBIDDEN
```

Do not expose raw database exceptions, stack traces, SQL errors, or internal implementation details.

---

# 36. Security Requirements

Verify the operational contract against at least these threats:

## Horizontal privilege escalation

Staff A must not gain access to resources merely by changing an ID to one associated with another operational resource they are not authorized to handle.

## Vertical privilege escalation

Staff must not call Admin-only endpoints or mutate roles/permissions.

## Customer-account escalation

Staff must not use order/customer identifiers to gain unrestricted access to a customer's account.

## Mass assignment

Privileged fields must not be accepted through generic request bodies.

## State skipping

Clients must not jump directly to later order states.

## Financial tampering

Clients must not control:

- subtotal
- delivery fee
- total
- payment state
- financial history

except through specifically authorized server-side operations.

## Inventory tampering

Clients must not directly overwrite inventory quantities without the approved adjustment operation.

## Enumeration

Not-found and forbidden behavior must follow the resource-exposure policy defined in the global error/auth contract.

## Audit spoofing

Clients cannot provide or modify the effective audit actor.

---

# 37. API Endpoint Inventory Updates

Update the endpoint inventory from Phase 1.19.

Every new Staff/Admin endpoint must receive a stable endpoint identifier.

Example naming scheme:

```text
STAFF-001
STAFF-002
ADMIN-001
ADMIN-002
INV-001
```

Use the already established endpoint-ID convention rather than creating an incompatible second convention.

Each endpoint record must contain:

```text
Endpoint ID
Method
Path
Domain
Resource
Purpose
Allowed actor(s)
Authentication requirement
Authorization rule
Request body
Query parameters
Response shape
Possible errors
Idempotency behavior
Concurrency behavior
Business-state constraints
Audit requirement
Notification/event side effects
```

No endpoint may exist only implicitly in narrative documentation.

---

# 38. API Resource Updates

Update:

```text
docs/api/api-resources.md
```

to identify the operational resources and their boundaries.

At minimum distinguish:

```text
Public Catalog Resource
Operational Product Resource
Inventory Resource
Order Resource
Made-to-Order Request Resource
General Enquiry Resource
Notification Resource
User/Staff Resource
Audit Resource (only if exposed)
```

For every resource document:

- owner
- privileged readers
- privileged writers
- customer visibility
- immutable fields
- server-controlled fields
- state machine, if applicable
- audit requirements

---

# 39. Contract Documentation Updates

Update the consolidated API contract documentation rather than creating unnecessary new files.

Primary targets:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not create:

```text
docs/api/staff-api.md
docs/api/admin-api.md
docs/api/operations-api.md
```

unless the documentation has become materially too large to maintain as a consolidated contract.

The project's preferred documentation strategy is consolidation over file proliferation.

---

# 40. Required Cross-Domain Checks

Before declaring this phase complete, verify:

### Orders

Staff/Admin operational actions agree exactly with:

- Phase 1.23 Order API Contract
- Phase 1.24 Tracking/Fulfillment Contract

### Checkout

The delivery-fee workflow does not contradict:

- Phase 1.22 Checkout Contract

### Payment

The contract clearly stops before actual payment implementation.

Group H will later consume:

```text
final authoritative order amount
```

and must not invent its own delivery-fee calculation.

### Notifications

Operational mutations generate the approved notification events from Phase 1.27 without making notifications the source of truth.

### Users

Staff/Admin access does not violate Phase 1.28 `/me` ownership and profile rules.

### Authorization

Every privileged endpoint agrees with Phase 1.18.

---

# 41. Required Canonical Examples

For this phase, document at least these examples in the API contract or examples section:

## A. Staff lists orders

```text
GET /api/v1/staff/orders
```

## B. Staff accepts an order

```text
POST /api/v1/staff/orders/{order}/accept
```

## C. Staff starts processing

```text
POST /api/v1/staff/orders/{order}/start-processing
```

## D. Staff sets delivery fee

```text
POST /api/v1/staff/orders/{order}/set-delivery-fee
```

## E. Staff marks pickup ready

```text
POST /api/v1/staff/orders/{order}/ready-for-pickup
```

## F. Staff ships a delivery order

```text
POST /api/v1/staff/orders/{order}/ship
```

## G. Admin approves Staff

```text
POST /api/v1/admin/staff/{user}/approve
```

## H. Staff attempts an Admin-only action

Must demonstrate the standard `403` error envelope.

Examples must use the canonical API response structure established in earlier phases.

---

# 42. Explicit Non-Goals

Do not implement or contract the following as part of this phase unless already explicitly approved elsewhere:

- payment gateway behavior
- payment authorization/capture/refund implementation
- push notifications
- email delivery
- SMS
- customer password management
- customer password reset implementation
- customer account deletion
- saved address book
- live GPS delivery tracking
- full CRM
- chat/messaging platform
- quotation engine for made-to-order requests
- automatic request-to-order conversion
- advanced role/permission builder
- custom roles
- arbitrary per-user permissions
- generic "superadmin" role
- staff self-registration
- staff ability to control customer accounts
- inventory reservation semantics beyond already-approved checkout rules

Group H owns payment.

Group R owns real email/notification delivery.

---

# 43. Definition of Done

Phase 1.29 is complete only when all of the following are true:

- [ ] STAFF and ADMIN capabilities are explicitly separated.
- [ ] The closed role model remains `CUSTOMER`, `STAFF`, `ADMIN`.
- [ ] Staff operational scope is documented.
- [ ] Admin-only operations are documented.
- [ ] Customer-account control boundaries are explicit.
- [ ] Operational product/catalog actions are defined.
- [ ] Inventory operations are defined as controlled actions.
- [ ] Staff order listing/detail contracts are defined.
- [ ] Order state transition endpoints are explicitly defined.
- [ ] Pickup fulfillment actions are defined.
- [ ] Delivery fulfillment actions are defined.
- [ ] Variable delivery-fee assignment is explicitly defined.
- [ ] Delivery-fee authority is Staff/Admin only.
- [ ] Client-controlled financial totals are prohibited.
- [ ] Final payment amount dependency is documented for Group H.
- [ ] Made-to-order operational endpoints are defined.
- [ ] General enquiry operational endpoints are defined.
- [ ] Staff/Admin notification operations are defined.
- [ ] Staff lifecycle/approval operations are defined.
- [ ] Role/permission mutation rules are defined.
- [ ] Audit requirements are defined.
- [ ] Concurrency rules are defined.
- [ ] Idempotency behavior is defined.
- [ ] Error handling uses Phase 1.16.
- [ ] Authorization uses Phase 1.18.
- [ ] Endpoint inventory has stable IDs.
- [ ] Resource documentation is updated.
- [ ] Cross-domain consistency checks pass.
- [ ] Canonical examples exist for the major operational workflows.
- [ ] No payment implementation has been accidentally pulled into Group A.
- [ ] No undocumented privileged endpoint remains.

---

# 44. STOP Condition

**STOP after the API contract is complete.**

Do not begin:

- Laravel controllers
- Laravel policies
- middleware implementation
- database migrations
- Eloquent models
- service classes
- repositories
- admin UI
- staff UI
- Flutter operational screens
- payment integration

Those belong to later implementation phases.

At the end of Phase 1.29, the repository must contain an internally consistent written contract for Staff/Admin operations, but no implementation code is required by this phase.

**Next phase: 1.30 — Cross-Domain API Contract Review.**
