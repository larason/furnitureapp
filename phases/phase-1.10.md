# Phase 1.10 — Define HTTP Method Conventions

## 1. Purpose

Phase 1.10 establishes the project's **HTTP method conventions** for the API.

The purpose is to ensure that later endpoint design follows a predictable and consistent mapping between:

```text
Business operation
        ↓
API resource/action
        ↓
HTTP method
```

This phase defines:

* when to use `GET`;
* when to use `POST`;
* when to use `PATCH`;
* whether `PUT` is allowed;
* when to use `DELETE`;
* how business actions are represented;
* idempotency expectations;
* safe vs unsafe operations;
* read vs mutation semantics;
* collection vs individual resource behavior;
* method restrictions;
* concurrency considerations.

It does **not** yet define the complete endpoint catalog or implementation.

---

# 2. Important Project Rules

The current authoritative files are:

```text
AGENTS.md
docs/VISION.md
```

Do not revert to:

```text
agent.md
VISION.md
```

Payment-specific API design belongs to:

```text
Phase Group H
```

not Group G.

Also preserve the API versioning decision from Phase 1.8.

---

# 3. Important Enum Policy

A code review identified that previous work contained an interim rule allowing:

```text
OPEN / EXTENSIBLE
```

enum behavior.

That has now been superseded.

## Version 1 Enum Policy

**All Version 1 enums are CLOSED by default.**

Therefore clients may assume the documented enum set is complete for Version 1 unless a future version explicitly changes the contract.

This rule exists to minimize client breakage across:

* Next.js;
* Flutter;
* Admin;
* future API consumers.

Examples include:

```text
Product Type
Order Status
Fulfillment Type
Roles
Request Status
Enquiry Status
Payment Status
```

Do not introduce an extensible-enum interpretation in later API design unless the project explicitly changes this policy.

If a later business requirement requires a new enum value in Version 1, treat that as a compatibility concern requiring formal review.

---

# 4. Dependency Position

Current sequence:

```text
1.1 API Scope
       ↓
1.2 Domain Nouns
       ↓
1.3 Domain Invariants
       ↓
1.4 Logical Data Model
       ↓
1.5 Logical Model Review + Freeze
       ↓
1.6 API Resource Inventory
       ↓
1.7 API Resource Relationships
       ↓
1.8 API Versioning
       ↓
1.9 Endpoint Naming
       ↓
1.10 HTTP Method Conventions
       ↓
1.11 Query Parameter Conventions
       ↓
...
```

Phase 1.10 must not redefine previous phases.

---

# 5. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3.md
domain-invariants.md
logical-data-model-v1.md
freeze-record.md

api-resource-inventory.md
api-resource-relationships.md
resource-relationship-map.md

api-versioning-strategy.md
api-breaking-change-policy.md

endpoint-naming-conventions.md
canonical-api-vocabulary.md
resource-path-policy.md
action-naming-policy.md
resource-nesting-policy.md
api-naming-decisions.md
```

Use the actual project filenames where they differ.

---

# 6. Core HTTP Principle

Use HTTP methods to describe the **type of operation**, not the internal implementation.

Conceptually:

```text
GET
→ read

POST
→ create or perform an action

PATCH
→ partial modification

PUT
→ complete replacement, only where genuinely appropriate

DELETE
→ remove/delete according to resource semantics
```

Do not use:

```text
POST
```

for everything.

---

# 7. GET — Read Operations

Use `GET` for retrieval.

Examples conceptually:

```text
Product collection
Individual product
Category
Product variants
Public availability
Customer order
Order tracking
Customer notifications
Customer request
Customer enquiry
```

A successful GET must not intentionally change business state.

---

# 8. GET Safety

GET operations must be safe with respect to business state.

Do not design:

```text
GET /orders/{order}/cancel
```

or:

```text
GET /orders/{order}/ship
```

because these cause mutations.

Those operations must use appropriate mutation semantics later.

---

# 9. GET Idempotency

GET is inherently expected to be repeatable from the client's perspective.

A client may make the same GET request multiple times.

The server must not create:

```text
orders
payments
requests
enquiries
inventory changes
```

because a GET was repeated.

---

# 10. POST — Creation

Use `POST` when creating a new member of a collection where the server assigns the identity.

Conceptual examples:

```text
Create cart item
Create order through checkout
Create made-to-order request
Create enquiry
Create payment transaction
```

The exact endpoints are future work.

---

# 11. POST — Business Actions

Use `POST` for business actions that:

* cause a state transition;
* trigger side effects;
* cannot be represented safely as simple field updates;
* may not be naturally idempotent.

Examples:

```text
Checkout
Cancel order
Accept order
Ship order
Mark delivered
Submit request
Submit enquiry
Initiate payment
```

The exact action list is defined in later endpoint-contract phases.

---

# 12. POST — Why Actions Should Not Always Be PATCH

A state change such as:

```text
PROCESSING → SHIPPED
```

may look like:

```text
PATCH order.status = SHIPPED
```

but the business operation could require:

* authorization;
* transition validation;
* inventory checks;
* delivery validation;
* audit history;
* notifications;
* side effects.

Therefore it may be more appropriate to model it as an explicit business action.

Do not use unrestricted client-controlled status updates.

---

# 13. POST Idempotency

POST is not inherently idempotent.

Therefore important POST operations require deliberate idempotency design when duplicate requests could cause duplicate business effects.

At minimum flag:

```text
Checkout/order creation
Payment initiation
Payment callback handling where applicable
Inventory-affecting operations
Critical order actions
```

The actual idempotency mechanism is later.

---

# 14. Client Double Submission

Consider:

```text
Customer taps "Place Order" twice.
```

or:

```text
Mobile app retries because the network appears disconnected.
```

The API design must prevent accidental duplicate business transactions where required.

Do not rely only on the frontend button becoming disabled.

---

# 15. PATCH — Partial Modification

Use `PATCH` when modifying part of an existing resource and the operation represents ordinary data modification.

Examples may include:

```text
Update profile information
Update cart item quantity
Update product information in admin
Update enquiry metadata
Update request administrative information
```

Only use PATCH when the requested change is a genuine partial modification.

---

# 16. PATCH Must Respect Domain Invariants

PATCH must not become a way to bypass business actions.

For example, do not allow arbitrary:

```json
{
  "status": "DELIVERED"
}
```

if moving the order to `DELIVERED` requires:

* valid previous state;
* staff authorization;
* delivery conditions;
* status history;
* audit information.

Such changes should use the later-defined controlled action mechanism.

---

# 17. PUT — Use Sparingly

Decide whether the project will support `PUT`.

Recommended baseline:

> Do not use PUT unless the endpoint represents a genuine complete replacement operation.

Why?

A small project can become inconsistent if some endpoints use:

```text
PUT
PATCH
POST
```

without a clear distinction.

For Version 1:

```text
PATCH
```

should be the normal partial-update method.

`PUT` should require explicit justification.

---

# 18. PUT Semantics

If PUT is ever used, it means:

> Replace the complete resource representation at the target URI.

It should not mean:

```text
maybe partial update
```

Do not design ambiguous PUT behavior.

---

# 19. DELETE — True Deletion

Use `DELETE` only when the business operation genuinely means deletion/removal.

Examples might include:

```text
Remove cart item
Delete temporary resource
Remove an attachment where permitted
```

But do not assume every domain entity should support DELETE.

---

# 20. DELETE Is Not Automatically Cancellation

An Order should not normally be modeled as:

```text
DELETE /orders/{order}
```

simply because the customer wants to cancel it.

Cancellation is a business action with:

* a 20-minute window;
* status eligibility;
* possible payment implications;
* audit requirements.

Therefore customer order cancellation should be treated as a controlled domain operation, not generic deletion.

---

# 21. Soft Delete vs DELETE

Do not decide physical soft-delete implementation here.

However, classify domain resources conceptually as:

```text
destructible
deactivatable
cancellable
historically retained
```

Examples:

```text
Order
→ historically retained

Product
→ typically deactivated/archived rather than destroyed

Order status history
→ retained

Cart item
→ removable

Request
→ usually operationally retained
```

The actual implementation comes later.

---

# 22. Method vs Business Action

Create this distinction:

```text
Generic resource update
        ↓
PATCH

Business state transition
        ↓
Controlled action
```

Example:

```text
PATCH /profile
```

may update a customer's phone.

But:

```text
order → ship
```

is a business operation.

This distinction is foundational for the future order API.

---

# 23. Collection Operations

Define general collection semantics.

### GET collection

Retrieve a collection.

Conceptual:

```text
GET /products
GET /categories
GET /orders
```

Access depends on authorization.

### POST collection

Create a new resource.

Conceptual:

```text
POST /requests
POST /enquiries
```

Do not assume every collection accepts POST.

---

# 24. Individual Resource Operations

For an individual resource:

```text
GET /products/{product}
```

means retrieve.

Possible:

```text
PATCH /products/{product}
```

means partial modification where authorized.

Possible:

```text
DELETE /...
```

means actual deletion if permitted.

Actions use the project's controlled-action convention.

---

# 25. Relationship Retrieval

Relationships that are read-only should generally use `GET`.

Examples conceptually:

```text
GET product → variants
GET product → images
GET order → tracking
GET order → items
GET request → attachments
```

These are retrieval operations.

---

# 26. Relationship Mutation

Creating a child resource generally uses `POST` to the relevant collection.

Example conceptually:

```text
POST cart/items
```

Updating an existing child uses:

```text
PATCH
```

Removing a removable child uses:

```text
DELETE
```

where deletion is actually part of the business model.

---

# 27. Resource Replacement

If a resource ever requires complete replacement, use:

```text
PUT
```

only when the contract clearly specifies replacement semantics.

Do not use PUT simply because:

> "PUT is an update method."

PATCH is preferred for partial updates.

---

# 28. Idempotent vs Non-Idempotent Operations

Create a formal matrix.

| Method / operation type | Expected idempotency                                           |
| ----------------------- | -------------------------------------------------------------- |
| GET                     | Yes                                                            |
| PUT                     | Yes                                                            |
| DELETE                  | Generally yes from HTTP semantics, subject to business outcome |
| PATCH                   | Depends on operation; contract must specify                    |
| POST create             | No by default                                                  |
| POST business action    | No by default                                                  |

Important:

> Idempotency must be evaluated for the actual business operation, not only the HTTP method.

---

# 29. PATCH Idempotency

PATCH is not automatically idempotent in every implementation.

For example:

```text
PATCH quantity = 3
```

can be designed as idempotent.

But:

```text
PATCH increment_quantity = 1
```

is not idempotent.

Therefore prefer absolute state assignment for ordinary PATCH fields.

Example:

```text
quantity = 3
```

rather than:

```text
increment by 3
```

unless the contract explicitly defines the latter as an action.

---

# 30. DELETE Idempotency

Repeated deletion may produce:

```text
first request → resource removed
second request → already removed
```

The API contract must define the expected response behavior later.

Do not depend on all clients understanding nuanced DELETE semantics.

---

# 31. Action Idempotency Categories

Classify future actions into:

```text
IDEMPOTENT ACTION
NON_IDEMPOTENT ACTION
IDEMPOTENCY REQUIRED
IDEMPOTENCY NOT REQUIRED
```

Potential examples:

### Cancel Order

Needs deliberate idempotency design because retrying a cancellation should not cause multiple refund/payment effects.

### Ship Order

Repeated requests must not repeatedly trigger shipping side effects.

### Deliver Order

Repeated requests must not generate duplicate events.

### Create Enquiry

Repeated submission may legitimately create two enquiries, but client retry behavior must be considered.

### Checkout

Requires strong duplicate-prevention design.

---

# 32. Order Action Safety

The following future operations must not be implemented as arbitrary generic PATCHes:

```text
Accept order
Process order
Ready for pickup
Ship
Deliver
Cancel
```

Each operation must respect the order-state invariants from Phase 1.3.

---

# 33. Inventory Action Safety

Inventory operations may affect critical stock state.

Therefore:

```text
PATCH inventory quantity
```

should not automatically be treated as a generic public operation.

Administrative inventory adjustments must be modeled as controlled operations with authorization and audit requirements.

The exact contract is later.

---

# 34. Checkout Method Rule

Checkout is a business workflow.

It must not be treated simply as:

```text
PATCH cart → create order
```

The method must support:

```text
validation
pricing
inventory validation
fulfillment
order creation
payment initiation when applicable
```

The exact endpoint and request/response model come later.

---

# 35. Request Submission

Made-to-order request submission is a creation operation.

Conceptually:

```text
POST requests
```

Anonymous and authenticated callers may both be supported according to Phase 1.1.

Do not use:

```text
PUT request
```

for initial submission.

---

# 36. Enquiry Submission

General enquiry submission is also a creation operation.

Conceptually:

```text
POST enquiries
```

Anonymous and authenticated callers remain supported.

---

# 37. Notification Read State

If a notification supports marking it as read/unread, this is a mutation.

Do not use GET:

```text
GET /notifications/{id}/read
```

A later contract may use:

```text
PATCH
```

for ordinary read-state changes or a controlled action where more appropriate.

The exact endpoint is later.

---

# 38. Product Administrative Updates

Admin/staff product edits may use:

```text
PATCH
```

for ordinary partial changes.

Examples:

```text
name
description
price
category
```

However, activation/deactivation/archive (`is_active`) are **not** general field updates; they are treated as controlled business actions per §39 unless an explicit later contract establishes that they have no lifecycle side effects.

---

# 39. Product Activation/Deactivation

Treat:

```text
activate
deactivate
archive
```

as potentially controlled business actions rather than automatically allowing:

```text
PATCH is_active
```

This is especially important if deactivation affects:

* customer visibility;
* purchasing;
* SEO;
* inventory.

Do not decide exact endpoint behavior yet.

---

# 40. Category Updates

Normal category metadata changes may use PATCH.

Examples:

```text
name
description
display order
```

If a category lifecycle action later has distinct business consequences, evaluate it as an action rather than arbitrary field modification.

---

# 41. Customer Profile

Normal profile changes are partial modifications.

Therefore:

```text
PATCH
```

is the preferred conceptual method.

Do not let profile updates modify protected identity/security information arbitrarily.

---

# 42. Authentication Operations

Authentication operations are not generic CRUD.

For example:

```text
login
logout
password reset
email verification
```

will require explicit authentication contract rules later.

Do not force them into:

```text
PATCH /users
```

or generic resource CRUD.

---

# 43. Payment Operations

Payment-specific method semantics will be defined in:

**Phase Group H**

For this phase, only establish that:

* payment creation/initiation may use POST;
* payment confirmation is not trusted from the client;
* provider callbacks/webhooks are event-driven operations;
* payment operations must be designed with idempotency.

Do not choose provider-specific methods here.

---

# 44. Webhook Semantics

Future payment/webhook endpoints will usually receive events from an external provider.

These are not ordinary browser CRUD requests.

When Group H defines them, ensure duplicate provider delivery does not create duplicate business effects.

Do not implement webhook handling in Phase 1.10.

---

# 45. HTTP Status vs Method

Do not use an HTTP method to communicate business success/failure.

For example:

```text
POST
```

still represents the same kind of requested operation even when the operation fails validation.

HTTP status conventions belong to a later API error/status phase.

---

# 46. Method Does Not Determine Authorization

Do not assume:

```text
GET = public
POST = authenticated
PATCH = admin
```

Authorization depends on the resource/action and actor.

Examples:

```text
GET /products
→ anonymous

GET own order
→ authenticated customer

PATCH product
→ authorized staff/admin

POST made-to-order request
→ anonymous or authenticated

POST order checkout
→ authenticated customer
```

---

# 47. Method Safety and Logging

Document that mutation methods are operationally significant and should eventually be visible in application logs/audit systems where appropriate.

Especially:

```text
POST
PATCH
PUT
DELETE
```

for privileged operations.

Do not log sensitive payloads blindly.

---

# 48. Method and CSRF Considerations

The API's authentication mechanism will determine the precise CSRF requirements later.

Do not design around:

```text
GET mutations
```

as a shortcut.

Safe/read-only methods must remain safe.

---

# 49. Method and Caching

GET is the primary method eligible for caching.

Mutation methods should not be casually cached as if they were reads.

This matters for the public product catalog and Next.js server rendering.

Do not configure caching yet.

---

# 50. Method and Retry Behavior

Document a retry policy principle:

> Clients may safely retry read operations.

Mutation retries require operation-specific analysis.

Particularly:

```text
checkout
payment
stock-affecting actions
order transitions
```

must not be retried blindly without considering duplicate effects.

---

# 51. Method Matrix for Version 1

Create a preliminary matrix:

| Domain operation       | Preferred method | Special handling        |
| ---------------------- | ---------------- | ----------------------- |
| Read products          | GET              | Safe                    |
| Read category          | GET              | Safe                    |
| Read product           | GET              | Safe                    |
| Read availability      | GET              | Safe                    |
| Read cart              | GET              | Customer-owned          |
| Add cart item          | POST             | Mutation                |
| Update cart quantity   | PATCH            | Validate inventory      |
| Remove cart item       | DELETE           | Remove item             |
| Checkout               | POST             | Idempotency required    |
| Create order           | POST/workflow    | Backend controlled      |
| Read order             | GET              | Owner/authorized        |
| Read tracking          | GET              | Read-only               |
| Cancel order           | POST/action      | 20-minute rule          |
| Accept order           | POST/action      | Staff/admin             |
| Process order          | POST/action      | Staff/admin             |
| Ship order             | POST/action      | Staff/admin             |
| Deliver order          | POST/action      | Staff/admin             |
| Create request         | POST             | Anonymous/authenticated |
| Read request           | GET              | Owner/staff/admin       |
| Create enquiry         | POST             | Anonymous/authenticated |
| Read enquiry           | GET              | Owner/staff/admin       |
| Read notifications     | GET              | Own only                |
| Mark notification read | PATCH/action     | Own only                |
| Update profile         | PATCH            | Authenticated           |
| Payment operation      | POST/workflow    | Group H                 |
| Provider webhook       | POST/event       | Group H                 |

This is a convention matrix, not the final endpoint contract.

---

# 52. PUT Policy Decision

Make one formal decision for Version 1.

Recommended:

> `PUT` is permitted only for true complete replacement resources and should not be used by default.

If no Version 1 resource requires genuine replacement semantics, the project may simply document:

> No Version 1 endpoint currently requires PUT; PATCH is preferred for partial modification.

Record the decision explicitly.

---

# 53. DELETE Policy Decision

Make a formal policy:

> DELETE is used only where the business concept supports actual removal.

Therefore:

```text
Order
→ not ordinary DELETE

Product
→ generally deactivate/archive

Order history
→ never ordinary DELETE

Cart item
→ potentially DELETE
```

Record exact resource-specific cases later.

---

# 54. Action Endpoint Policy

Establish a general rule:

> When an operation represents a business command rather than ordinary resource modification, model it as an explicit action using POST unless another method is clearly justified.

Examples:

```text
POST .../cancel
POST .../accept
POST .../ship
POST .../deliver
```

The actual endpoint paths will be finalized later.

---

# 55. Avoid Verb HTTP Endpoints

Do not create:

```text
GET /get-products
POST /create-order
POST /update-order
POST /delete-product
```

The HTTP method already communicates the basic operation.

---

# 56. Avoid Method Tunneling

Do not introduce patterns such as:

```text
POST
{
  "method": "DELETE"
}
```

unless forced by an external infrastructure constraint.

Use the real HTTP method.

---

# 57. Method Override

Do not rely on framework-specific method spoofing as the public API design.

The public API contract should describe real HTTP semantics.

Implementation frameworks may internally support method override, but it should not become part of the API contract unless explicitly required.

---

# 58. Idempotency-Key Preparation

Identify requests that may eventually require an idempotency key.

At minimum:

```text
Checkout
Payment initiation
Potentially critical order actions
```

The exact header name and semantics belong to a later idempotency phase.

Do not implement it yet.

---

# 59. Conditional Request Consideration

Identify whether future APIs may need concurrency controls such as:

```text
ETag
If-Match
version field
optimistic locking
```

This is especially relevant for:

```text
inventory
orders
admin updates
```

Do not implement these yet.

Simply record them as future design considerations.

---

# 60. Method + Domain Invariant Review

Cross-check HTTP methods against Phase 1.3 invariants.

Examples:

```text
PATCH order.status
```

must not bypass:

```text
invalid transition protection
```

```text
POST checkout
```

must not bypass:

```text
stock validation
pricing authority
authentication
```

```text
DELETE order
```

must not bypass:

```text
20-minute cancellation rule
```

The HTTP method is never an escape route around domain rules.

---

# 61. Enum Policy Validation

Because the Version 1 enum policy is now:

> **CLOSED by default**

verify that HTTP method conventions do not depend on clients accepting unspecified future state values.

For all future APIs:

```text
order status
product type
fulfillment type
request status
enquiry status
role
```

must use the documented closed values.

A future additional value is therefore a deliberate compatibility decision.

---

# 62. Required Deliverables

Produce:

## A. `http-method-conventions.md`

Include:

```text
GET
POST
PATCH
PUT
DELETE
HEAD/OPTIONS considerations if applicable
safe methods
idempotency
mutation rules
action rules
retry principles
```

---

## B. `http-method-resource-matrix.md`

Map resource operations to preferred methods.

---

## C. `api-action-method-policy.md`

Define when a business operation must be modeled as a controlled POST/action instead of a generic PATCH.

---

## D. `api-idempotency-candidates.md`

Document operations that require later idempotency design.

---

## E. `api-method-security-rules.md`

Document security implications of methods, including:

```text
safe vs unsafe
authorization
logging
retries
CSRF considerations
```

---

## F. `api-method-decisions.md`

Record final decisions.

Each decision:

```text
Decision ID
Decision
Reason
Alternatives
Affected resources
Future phase
```

---

# 63. Validation Checklist

Before completion:

### GET

* [ ] GET is read-only.
* [ ] GET cannot cause purchases.
* [ ] GET cannot change order state.
* [ ] GET cannot mark notifications read.
* [ ] GET is safe to retry.

### POST

* [ ] POST is used for creation where appropriate.
* [ ] POST is used for business actions where appropriate.
* [ ] Critical POST operations are identified for idempotency.
* [ ] Checkout uses a mutation/workflow model rather than generic update semantics.

### PATCH

* [ ] PATCH is used for genuine partial modification.
* [ ] PATCH cannot bypass domain state transitions.
* [ ] PATCH fields have clearly defined semantics.
* [ ] Increment/decrement behavior is not hidden inside ordinary PATCH.

### PUT

* [ ] PUT policy is explicit.
* [ ] PUT means complete replacement if used.
* [ ] PUT is not used simply because something is "updated."

### DELETE

* [ ] DELETE represents true removal.
* [ ] Order cancellation is not generic DELETE.
* [ ] Historical records are protected from accidental deletion.
* [ ] Product deletion semantics are consistent with archival/deactivation policy.

### Actions

* [ ] Business actions are distinguished from generic updates.
* [ ] Order actions are controlled operations.
* [ ] Inventory-critical operations are controlled.
* [ ] Action naming remains compatible with Phase 1.9.

### Idempotency

* [ ] Checkout is identified.
* [ ] Payment initiation is identified.
* [ ] Critical order transitions are identified.
* [ ] Duplicate external callbacks are identified for later design.

### Enum policy

* [ ] Version 1 enums are treated as CLOSED.
* [ ] No OPEN/EXTENSIBLE interim rule remains.
* [ ] Future enum additions require explicit compatibility review.

### Payment

* [ ] Payment-specific implementation remains assigned to **Phase Group H**.
* [ ] No payment provider is selected here.

---

# 64. Explicitly Out of Scope

Do NOT:

```text
Define complete endpoint list
Define endpoint URLs
Define request payloads
Define response payloads
Define status codes
Define authentication implementation
Define authorization implementation
Define idempotency headers
Implement Laravel routes
Implement controllers
Implement middleware
Write OpenAPI operations
Implement payment provider
```

---

# 65. Definition of Done

Phase 1.10 is complete when:

1. Each HTTP method has a defined project meaning.
2. GET safety is explicit.
3. POST creation semantics are explicit.
4. POST action semantics are explicit.
5. PATCH partial-update semantics are explicit.
6. PUT policy is explicit.
7. DELETE policy is explicit.
8. Business actions are distinguished from ordinary updates.
9. Order state transitions cannot be represented as uncontrolled arbitrary patches.
10. Checkout is recognized as a critical mutation.
11. Idempotency-sensitive operations are identified.
12. Retry behavior is documented.
13. Conditional/concurrency considerations are recorded.
14. Version 1 closed-enum policy is recorded and supersedes the old interim OPEN/EXTENSIBLE rule.
15. Payment remains assigned to **Phase Group H**.
16. No endpoint catalog has been implemented.
17. No Laravel implementation has been created.

---

# 66. STOP CONDITION — Mandatory

After completing:

```text
http-method-conventions.md
http-method-resource-matrix.md
api-action-method-policy.md
api-idempotency-candidates.md
api-method-security-rules.md
api-method-decisions.md
```

and passing all validation checks:

**STOP.**

Do not automatically proceed to endpoint design.

Do not define query parameters.

Do not define request schemas.

Do not create Laravel routes.

Do not create OpenAPI operations.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.11 — Define Query Parameter Conventions

That phase should establish consistent rules for:

```text
filtering
search
sorting
pagination
field selection
relationship inclusion
boolean parameters
date/range parameters
query encoding
validation
unknown parameters
```

while keeping the API predictable for the Next.js website, Flutter application, and admin client.
