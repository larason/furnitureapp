# Phase 1.19 — Define Concrete API Endpoint Inventory

## 1. Purpose

Phase 1.19 is the point where the project moves from **API architecture and conventions** to the **concrete Version 1 endpoint catalogue**.

All major foundations should already exist:

```text
Business scope
→ Domain language
→ Invariants
→ Logical data model
→ Frozen model
→ API resources
→ Resource relationships
→ Versioning
→ Naming
→ HTTP methods
→ Query conventions
→ Pagination
→ Response conventions
→ Request/input conventions
→ Validation
→ Error contract
→ Authentication
→ Authorization
```

Now define:

> **Exactly which API endpoints exist in Version 1, why they exist, who can call them, and what contract each endpoint will eventually implement.**

This phase is still **API design**, not Laravel implementation.

---

# 2. Documentation Strategy

Continue the consolidated-document strategy.

Do **not** create one markdown file per endpoint.

The authoritative API design should primarily live in:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/decisions.md
```

The eventual machine-readable API specification will be:

```text
docs/api/openapi.yaml
```

but do not fully generate the OpenAPI document until its designated phase.

---

# 3. Authoritative Project Paths

Use:

```text
AGENTS.md
docs/VISION.md
```

Do not use:

```text
agent.md
VISION.md
```

as current authoritative files.

---

# 4. Payment Assignment

All payment-specific API design and implementation belongs to:

**Phase Group H**

not Group G.

Phase 1.19 may include payment endpoints in the inventory so the API is complete, but:

* do not choose a payment provider;
* do not define provider-specific request/response behavior beyond generic boundaries;
* do not define webhook payloads in detail;
* do not implement payment integration.

Those belong to Group H.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

The endpoint inventory must use only the currently approved enum values.

Do not introduce undocumented values such as:

```text
ON_HOLD
CANCELLED_BY_ADMIN
REFUNDED
SUSPENDED
```

unless those values have already been approved by the project's domain/API model.

A genuinely new state requires an explicit compatibility/business review.

---

# 6. Dependency Position

Current sequence:

```text
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
1.19 Concrete Endpoint Inventory
       ↓
1.20 API Contract Examples
       ↓
1.21 OpenAPI Structure
       ↓
...
```

Phase 1.19 uses the decisions from all previous phases.

---

# 7. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/decisions.md
docs/domain/business-rules.md
```

Also review the completed Phase 1.6–1.18 outputs where available.

Especially:

```text
API resources
API relationships
versioning
endpoint naming
HTTP methods
query conventions
pagination
response contract
input conventions
validation
error contract
authentication
authorization
```

---

# 8. Endpoint Inventory Is Not Implementation

The endpoint inventory answers:

```text
What exists?
Why does it exist?
Who uses it?
What does it conceptually do?
```

It does not answer yet:

```text
How do we implement it in Laravel?
```

Do not create:

```text
routes
controllers
middleware
Form Requests
API Resources
DTOs
database queries
service classes
```

---

# 9. Endpoint Definition Template

Every endpoint in the Version 1 inventory must have a standard record.

Use:

```text
Endpoint ID:
Name:
Method:
Path:
Domain:
Resource:
Purpose:
Actor:
Authentication:
Authorization:
Request:
Query Parameters:
Response:
Errors:
Business Rules:
Idempotency:
State/Concurrency:
Public/Private:
Notes:
```

Example:

```text
Endpoint ID:
CAT-001

Name:
List products

Method:
GET

Path:
/api/v1/products

Domain:
Catalog

Resource:
Product

Purpose:
Retrieve the public product collection.

Actor:
Anonymous / Customer / Staff / Admin

Authentication:
Not required

Authorization:
Public catalog read

Request:
None

Query:
Defined later/approved query conventions

Response:
Product collection

Errors:
Standard collection/read errors

Business Rules:
Only publicly visible products are returned.

Idempotency:
Safe/read operation.
```

Do not copy this example blindly; use actual approved decisions.

---

# 10. Endpoint IDs

Assign stable endpoint IDs.

Recommended namespaces:

```text
AUTH-xxx
CAT-xxx
CART-xxx
CHK-xxx
ORD-xxx
PAY-xxx
FUL-xxx
REQ-xxx
ENQ-xxx
NOT-xxx
USER-xxx
INV-xxx
ADM-xxx
```

These IDs help later documents refer to endpoints without relying only on URLs.

Do not change an endpoint ID casually after the contract is frozen.

---

# 11. Endpoint ID Example

```text
CAT-001
```

means:

```text
Catalog endpoint #001
```

Not:

```text
database table #001
```

---

# 12. API Version Prefix

Every endpoint must use the Version 1 convention established in Phase 1.8.

Baseline:

```text
/api/v1/...
```

Do not create unversioned Version 1 endpoints.

---

# 13. Public Catalog Endpoints

Define the complete public catalog endpoint inventory.

At minimum evaluate:

```text
Product collection
Product detail
Category collection
Category detail
Category → products
Product → variants
Product → images
Public availability representation
```

Do not automatically create every relationship as a separate endpoint.

Only include an endpoint when clients genuinely need independent retrieval.

---

# 14. Product Collection

Define an endpoint for public product discovery.

Conceptual:

```text
GET /api/v1/products
```

It must support the approved query conventions where relevant:

```text
search
category
product_type
availability
min_price
max_price
sort
sort_direction
pagination
```

Do not add arbitrary filters.

---

# 15. Product Detail

Define:

```text
GET /api/v1/products/{product}
```

or the identifier convention selected in Phase 1.9.

The contract must eventually support the public product-detail page and Flutter product-detail screen.

It must remain public.

---

# 16. Category Collection

Define:

```text
GET /api/v1/categories
```

if the public catalog requires category listing.

Determine whether pagination is required based on the Version 1 resource policy.

Do not add pagination simply because Products have it.

---

# 17. Category Detail

Define:

```text
GET /api/v1/categories/{category}
```

if category pages are independently addressable.

The resource should provide enough information for the website/category view.

---

# 18. Category Products

Evaluate:

```text
GET /api/v1/categories/{category}/products
```

versus:

```text
GET /api/v1/products?category={category}
```

Do not implement both unless there is a clear reason.

For a small API, prefer one canonical retrieval mechanism.

Choose the approach that best fits:

```text
simplicity
discoverability
Next.js
Flutter
filtering conventions
```

Record the decision.

---

# 19. Product Images

Evaluate whether:

```text
GET /api/v1/products/{product}/images
```

is necessary.

If Product detail already contains image representations and there is no independent image retrieval need, this endpoint may be unnecessary.

Do not create endpoints solely because an API relationship exists.

---

# 20. Product Variants

Evaluate:

```text
GET /api/v1/products/{product}/variants
```

if the client needs independent variant retrieval.

If the normal Product response already includes variants, avoid unnecessary duplicate requests.

---

# 21. Availability

Decide whether availability is:

```text
embedded in Product
```

or:

```text
GET /products/{product}/availability
```

For Version 1, prefer embedding when it satisfies the product-detail UI without exposing internal inventory.

---

# 22. Public Catalog Security

Every public catalog endpoint must ensure it cannot expose:

```text
internal stock reservations
staff notes
supplier information
private operational data
```

Public endpoints must return public-safe representations.

---

# 23. Authentication Endpoints

Define the Version 1 authentication endpoint inventory.

At minimum evaluate:

```text
customer registration
login
logout
current authenticated user
password change
password recovery request
password reset
email verification
```

Staff/Admin authentication uses the same central authentication architecture where appropriate, but public customer registration must not allow staff/admin role creation.

The exact authentication contract comes from Phase 1.17.

---

# 24. Registration

Conceptual:

```text
POST /api/v1/auth/register
```

Purpose:

```text
Create a CUSTOMER account.
```

This endpoint must never accept:

```json
{
  "role": "ADMIN"
}
```

or:

```json
{
  "role": "STAFF"
}
```

as a customer-controlled field.

---

# 25. Login

Conceptual:

```text
POST /api/v1/auth/login
```

It authenticates a valid account.

The response must follow the common response contract.

Do not expose credentials.

---

# 26. Logout

Conceptual:

```text
POST /api/v1/auth/logout
```

or the project's final authentication operation convention.

Logout is a business/security action, not a GET request.

---

# 27. Current User

Define an authenticated self-context endpoint.

Potential:

```text
GET /api/v1/me
```

or the self-context structure selected in Phase 1.9.

It should return safe profile/account information.

Do not return:

```text
password hash
tokens
private authentication secrets
```

---

# 28. Password Recovery

Define the conceptual flow:

```text
POST password recovery request
POST password reset
```

The exact delivery mechanism remains compatible with:

```text
Email delivery → Phase Group R
```

Do not invent unsafe reset flows because email has not yet been implemented.

---

# 29. Email Verification

Define the conceptual endpoint(s) needed for secure verification.

The implementation should later use:

```text
secure
time-limited
single-use
```

verification mechanisms.

Do not expose raw verification secrets unnecessarily.

---

# 30. Customer Profile Endpoints

Define the required customer account endpoints.

Potential:

```text
GET /me
PATCH /me
```

Do not create separate profile endpoints for every field.

Credential changes should use dedicated security operations where appropriate.

---

# 31. Cart Endpoints

Define the Version 1 cart inventory.

Potential:

```text
GET cart
POST cart items
PATCH cart item
DELETE cart item
```

If the project uses self-context:

```text
GET /api/v1/me/cart
POST /api/v1/me/cart/items
```

may be preferable for ownership clarity.

Decide one approach.

---

# 32. Cart Authorization

Customer cart operations must be scoped to the authenticated customer.

A client must not be able to substitute:

```text
user_id
cart_owner_id
```

to access another customer's cart.

---

# 33. Cart Item Creation

Conceptual request:

```json
{
  "product_id": "...",
  "variant_id": "...",
  "quantity": 2
}
```

The endpoint must not accept:

```text
price
subtotal
inventory quantity
order status
```

as client-authoritative values.

---

# 34. Cart Item Update

Use PATCH for ordinary quantity changes according to Phase 1.10.

Conceptual:

```text
PATCH .../items/{item}
```

The backend revalidates the product/variant and quantity.

---

# 35. Cart Item Removal

Use DELETE only if cart-item removal is semantically deletion.

Conceptual:

```text
DELETE .../items/{item}
```

Do not use DELETE for order cancellation.

---

# 36. Checkout Endpoint

Checkout is one of the most important endpoints.

Define the endpoint conceptually as:

```text
POST /api/v1/checkout
```

or the final path selected by the naming policy.

It must require:

```text
authenticated CUSTOMER
```

and perform the approved business workflow.

---

# 37. Checkout Input

The customer may supply:

```text
fulfillment type
delivery information when required
```

The backend determines:

```text
cart
prices
inventory
delivery fee
subtotal
total
order reference
```

Do not accept client-controlled totals.

---

# 38. Checkout Security

Checkout must enforce:

```text
authentication
authorization
cart ownership
product purchasability
inventory availability
pricing authority
fulfillment validity
```

It must also be designed as an idempotency-sensitive operation.

The exact idempotency contract is finalized later.

---

# 39. Order Endpoints

Define the customer-facing and operational Order inventory.

Likely candidates:

```text
GET order collection
GET order detail
GET order tracking
POST order cancellation action
```

Operational actions may include:

```text
accept
process
ready-for-pickup
ship
deliver
complete
```

Only include actions actually required by the approved lifecycle.

---

# 40. Customer Order Collection

Decide whether the canonical customer route is:

```text
GET /orders
```

with server-side ownership filtering:

or:

```text
GET /me/orders
```

Use the self-context policy selected in Phase 1.9.

Do not create both unless required.

---

# 41. Customer Order Detail

Conceptual:

```text
GET /orders/{order}
```

The endpoint must enforce ownership.

A customer cannot retrieve another customer's order by changing the ID.

---

# 42. Order Tracking

Define:

```text
GET /orders/{order}/tracking
```

or the equivalent approved structure.

It should provide:

```text
current state
status history/progress
relevant fulfillment milestone
```

Do not implement GPS tracking.

---

# 43. Order Cancellation

Define a controlled action endpoint.

Conceptual:

```text
POST /orders/{order}/cancel
```

Requirements:

```text
authenticated customer
owns order
within 20-minute cancellation window
order is cancellable
```

Do not use:

```text
DELETE /orders/{order}
```

for customer cancellation.

---

# 44. Staff Order Endpoints

Define the operational endpoints Staff require.

Potential:

```text
GET /orders
GET /orders/{order}
POST /orders/{order}/accept
POST /orders/{order}/process
POST /orders/{order}/ready-for-pickup
POST /orders/{order}/ship
POST /orders/{order}/deliver
```

Only include actions appropriate to the actual status model.

Do not give Staff generic:

```text
PATCH /orders/{order}
{
  "status": "..."
}
```

as a substitute for controlled transitions.

---

# 45. Admin Order Endpoints

Do not create duplicate Admin-specific Order resources unnecessarily.

Admin should generally use the same Order resource and authorized operational actions.

Only introduce an administrative namespace if Phase 1.9 explicitly selected one.

---

# 46. Order Status History

Evaluate whether customers need:

```text
GET /orders/{order}/status-history
```

or whether:

```text
GET /orders/{order}/tracking
```

already supplies sufficient information.

Prefer one canonical customer-facing tracking interface.

---

# 47. Internal Order Status History

Do not create a publicly writable status-history endpoint.

There should be no ordinary:

```text
POST /orders/{order}/status-history
```

available to customers.

Status history is produced by controlled domain operations.

---

# 48. Request Endpoints

Define the made-to-order request inventory.

Likely:

```text
POST /requests
GET /requests/{request}
GET /requests
```

where access is appropriate.

Remember:

```text
anonymous creation
authenticated ownership
staff operational access
admin management
```

---

# 49. Anonymous Request Creation

This endpoint must permit anonymous creation.

It must collect approved contact information.

It must not require:

```text
user_id
customer_id
```

from the anonymous client.

---

# 50. Authenticated Request Ownership

When authenticated:

```text
Request → User
```

may be used for customer history and authorization.

Customers must only see their own requests.

---

# 51. Anonymous Request Retrieval

Do not automatically make:

```text
GET /requests/{request}
```

public simply because anonymous creation is allowed.

A secure anonymous retrieval mechanism, if needed, must be separately designed.

Do not use predictable IDs as bearer access credentials.

If anonymous retrieval is not required in Version 1, omit it.

---

# 52. Request Attachments

Evaluate:

```text
POST /requests/{request}/attachments
GET /requests/{request}/attachments
DELETE /requests/{request}/attachments
```

only if independently useful.

Attachments inherit request authorization.

---

# 53. Enquiry Endpoints

Define:

```text
POST /enquiries
GET /enquiries/{enquiry}
GET /enquiries
```

according to ownership/operational needs.

Anonymous creation remains supported.

---

# 54. Enquiry Attachments

Evaluate the same child-resource policy as Requests.

Do not create unnecessary duplicate attachment mechanisms.

---

# 55. Notification Endpoints

Define customer notification operations.

Potential:

```text
GET /me/notifications
PATCH /me/notifications/{notification}
```

where PATCH changes read/unread state.

Do not allow customers to modify arbitrary notification content.

---

# 56. Staff Notifications

Staff operational notifications should use the same Notification concept where possible.

The difference is:

```text
recipient
+
authorization scope
```

not a duplicate resource type.

---

# 57. Inventory Endpoints

Define only the operational endpoints actually required by Staff/Admin.

Potential:

```text
GET inventory
GET inventory/{product-or-variant}
PATCH inventory/{...}
```

But carefully distinguish ordinary modification from stock-adjustment actions.

Do not expose inventory management publicly.

---

# 58. Inventory Adjustment

Because inventory affects critical business state, evaluate whether an explicit action is safer than generic PATCH.

Potential conceptual pattern:

```text
POST inventory/{item}/adjust
```

with:

```text
quantity/change
reason
```

rather than:

```text
PATCH inventory/{item}
{
  "quantity": 999
}
```

Do not finalize the exact action until the inventory contract phase.

---

# 59. Product Management Endpoints

Staff/Admin operations may include:

```text
POST products
PATCH products/{product}
manage product images
manage variants
activate/deactivate
```

Public customers only get read operations.

---

# 60. Category Management Endpoints

Staff/Admin may eventually need:

```text
POST categories
PATCH categories/{category}
```

Deletion/deactivation semantics should follow the logical data model.

Do not create public category mutation endpoints.

---

# 61. Staff Management Endpoints

Admin-only management may include:

```text
GET staff
GET staff/{staff}
POST staff/invitations or equivalent
POST staff/{staff}/approve
PATCH staff/{staff}
```

The exact workflow must align with the staff-approval model from Phase 1.17.

Do not allow public customer registration to create staff.

---

# 62. Role Management Endpoints

If role management is required, it must be explicitly administrative.

Do not expose:

```text
PATCH /me
{
  "role": "ADMIN"
}
```

Roles are server-controlled.

---

# 63. Customer Management Endpoints

Admin may eventually require:

```text
GET customers
GET customers/{customer}
```

and explicitly approved account-management operations.

Do not give Staff unrestricted customer-account management.

---

# 64. Customer Account Restrictions

Do not create a general Staff endpoint such as:

```text
POST /customers/{customer}/block
```

because the approved business rule says Staff cannot arbitrarily restrict customers from browsing or ordering.

Any future security restriction must be explicitly approved as an administrative/security operation.

---

# 65. Payment Endpoints

Include generic placeholders in the endpoint inventory as needed:

```text
POST payment initiation
GET payment status
POST provider callback/webhook
```

but do not finalize provider-specific semantics.

Mark:

```text
Phase Group H
```

as the implementation owner.

---

# 66. Payment Error Boundary

Payment endpoints must use the global error contract from Phase 1.16.

Provider-specific error mappings will be defined later in Group H.

Do not invent provider-specific codes now.

---

# 67. Webhook Endpoints

If payment/webhook endpoints are included:

```text
POST /api/v1/webhooks/payment/...
```

or equivalent.

They are not customer authentication endpoints.

They require:

```text
signature/authentication
idempotency
event validation
```

All detailed behavior belongs to Group H.

---

# 68. Endpoint Inventory by Actor

Produce an actor-oriented summary.

### Anonymous

```text
GET products
GET categories
GET product details
POST requests
POST enquiries
POST customer registration
POST login
password recovery initiation
```

### Customer

```text
catalog reads
profile
cart
checkout
orders
tracking
eligible cancellation
requests
enquiries
notifications
authentication/security
```

### Staff

```text
operational orders
order processing
approved product/inventory operations
requests
enquiries
operational notifications
```

### Admin

```text
staff approval/management
administrative customer operations
catalog management
inventory
orders
requests
enquiries
system operations
```

This is a summary, not a replacement for the endpoint matrix.

---

# 69. Public API Principle

Every public endpoint must answer:

> Why is this information/action public?

If there is no strong business reason, it should not be public.

---

# 70. Customer API Principle

Customer endpoints should answer:

> Is this operation about the authenticated customer's own account/commerce data?

If not, it probably requires Staff/Admin authorization.

---

# 71. Staff API Principle

Staff endpoints should answer:

> Is this necessary for normal ecommerce operations?

If not, Staff should not receive the permission merely because they are Staff.

---

# 72. Admin API Principle

Admin endpoints should answer:

> Is this an administrative capability that Staff should not have?

If there is no such distinction, prefer the shared domain resource.

---

# 73. Endpoint Completeness Review

For every Version 1 user journey, map the necessary endpoints.

### Public browsing

```text
Homepage content
Categories
Products
Product detail
Search/filter
```

### Customer shopping

```text
Authentication
Cart
Checkout
Payment
Order
Tracking
Cancellation
```

### Made-to-order

```text
Request creation
Optional attachments
Request tracking where appropriate
```

### Enquiry

```text
Enquiry creation
Optional attachment
```

### Staff operations

```text
Order receipt
Processing
Fulfillment
Requests
Enquiries
Notifications
```

### Admin

```text
Staff approval
Staff management
Catalog
Inventory
Orders
Requests
Enquiries
```

If a workflow has no endpoint support, flag it.

---

# 74. Endpoint Redundancy Review

Look for endpoints that do the same thing.

Examples:

```text
GET /my-orders
GET /me/orders
GET /orders?mine=true
```

Choose one canonical approach.

Do not preserve multiple forms without a compelling reason.

---

# 75. Endpoint Ambiguity Review

Reject endpoints where purpose is unclear.

Bad:

```text
POST /orders/process
```

without identifying which order.

Prefer resource-scoped concepts such as:

```text
/order/{order}/...
```

according to the naming conventions.

---

# 76. Endpoint Ownership Review

For every protected endpoint, explicitly state:

```text
owns resource?
or
operational access?
or
administrative access?
```

Example:

```text
GET customer order
→ ownership

POST ship order
→ operational authority

POST approve staff
→ administrative authority
```

---

# 77. Endpoint State Review

Every state-changing endpoint must identify its state requirements.

Example:

```text
Ship Order
→ current state must permit shipping
```

Do not create a status action with no state precondition.

---

# 78. Endpoint Input Review

Every mutation endpoint must identify:

```text
client-controlled fields
server-generated fields
immutable fields
conditional fields
```

Use Phase 1.14 rules.

---

# 79. Endpoint Error Review

Every endpoint must reference applicable global/domain errors.

For example:

### Checkout

```text
AUTHENTICATION_REQUIRED
CART_INVALID
PRODUCT_NOT_PURCHASABLE
INSUFFICIENT_STOCK
INVALID_FULFILLMENT
```

### Cancel Order

```text
AUTHENTICATION_REQUIRED
RESOURCE_NOT_FOUND
ORDER_NOT_CANCELLABLE
ORDER_STATE_CONFLICT
```

Do not invent endpoint-specific error envelopes.

---

# 80. Endpoint Idempotency Review

Every mutation must be classified:

```text
SAFE
IDEMPOTENT
NON_IDEMPOTENT
IDEMPOTENCY_REQUIRED
```

Examples:

```text
GET products
→ SAFE

PATCH profile
→ designed to be idempotent where possible

POST checkout
→ IDEMPOTENCY_REQUIRED

POST request
→ non-idempotent by default
```

---

# 81. Endpoint Concurrency Review

Flag operations where concurrent requests matter:

```text
checkout
inventory adjustment
order state transition
payment callback
staff approval
```

The endpoint catalogue must identify these.

Do not design the locking mechanism yet.

---

# 82. Endpoint Security Classification

For each endpoint classify:

```text
PUBLIC
CUSTOMER
STAFF
ADMIN
SYSTEM/WEBHOOK
```

Use this as a security review aid.

---

# 83. Endpoint Data Classification

For every endpoint, identify whether its response contains:

```text
PUBLIC
PRIVATE
INTERNAL
SENSITIVE
```

This helps prevent accidental response overexposure.

---

# 84. Endpoint URL Inventory

Build one master table inside:

```text
docs/api/api-contract.md
```

Recommended columns:

| ID      | Method | Path                         | Actor  | Auth | Authorization | Purpose        |
| ------- | ------ | ---------------------------- | ------ | ---- | ------------- | -------------- |
| CAT-001 | GET    | `/api/v1/products`           | Public | No   | Public read   | List products  |
| CAT-002 | GET    | `/api/v1/products/{product}` | Public | No   | Public read   | Product detail |
| ...     | ...    | ...                          | ...    | ...  | ...           | ...            |

Do not finalize all request/response schemas yet.

---

# 85. Endpoint Contract Summary

For every endpoint table row, add a short structured block where useful:

```text
Request:
Query:
Response:
Errors:
Business rules:
Idempotency:
```

This should remain concise.

The full schemas belong in later endpoint-contract work.

---

# 86. Endpoint Numbering

Use stable IDs and do not recycle them.

For example:

```text
CAT-001
CAT-002
AUTH-001
CART-001
CHK-001
ORD-001
REQ-001
ENQ-001
```

If an endpoint is removed later:

```text
ID remains retired.
```

Do not give its ID to a different endpoint.

---

# 87. Endpoint Lifecycle

Classify each endpoint:

```text
PROPOSED
APPROVED
DEPRECATED
RETIRED
```

For Phase 1.19, new endpoints should be:

```text
PROPOSED
```

until the complete API contract review.

---

# 88. MVP Discipline

Do not include endpoints for features deferred beyond Version 1.

Examples:

```text
wishlist
reviews
coupons
saved addresses
loyalty
live driver tracking
```

unless those features are explicitly approved.

---

# 89. Public Website Support

Verify the endpoint inventory supports:

```text
Next.js SSR/Server Components
SEO product/category pages
catalog search
filtering
product detail
checkout
customer account
order tracking
```

Do not force the website to call authenticated endpoints for public content.

---

# 90. Flutter Support

Verify the endpoint inventory supports:

```text
login
registration
catalog
search/filter
cart
checkout
orders
tracking
requests
enquiries
profile
notifications
```

without creating Flutter-only endpoint variants.

---

# 91. Admin Support

Verify the endpoint inventory supports:

```text
staff operations
orders
inventory
catalog
requests
enquiries
staff management
```

without unnecessary duplicate resources.

---

# 92. API Resource Coverage Matrix

Create:

| Resource     | Public |    Customer |       Staff |           Admin | Endpoint(s) |
| ------------ | -----: | ----------: | ----------: | --------------: | ----------- |
| Category     |   Read |        Read |        Read |          Manage | CAT-*       |
| Product      |   Read |        Read |        Read |          Manage | CAT-*       |
| Inventory    |     No |          No |      Manage |          Manage | INV-*       |
| Cart         |     No |         Own |          No |              No | CART-*      |
| Order        |     No |         Own | Operational |           Admin | ORD-*       |
| Payment      |     No | Limited own | Operational |           Admin | PAY-*       |
| Request      | Create |         Own |      Manage |          Manage | REQ-*       |
| Enquiry      | Create |         Own |      Manage |          Manage | ENQ-*       |
| Notification |     No |         Own | Operational | Admin as needed | NOT-*       |
| User         |     No |         Own |  Restricted |           Admin | AUTH/USER-* |

---

# 93. Business Workflow Coverage

Create a table:

| Workflow               | Required endpoint IDs | Complete? |
| ---------------------- | --------------------- | --------- |
| Anonymous browse       | CAT-*                 | Yes/No    |
| Customer registration  | AUTH-*                | Yes/No    |
| Customer checkout      | CART/CHK/ORD/PAY-*    | Yes/No    |
| Pickup order           | CHK/ORD-*             | Yes/No    |
| Delivery order         | CHK/ORD-*             | Yes/No    |
| Order tracking         | ORD-*                 | Yes/No    |
| Customer cancellation  | ORD-*                 | Yes/No    |
| Made-to-order request  | REQ-*                 | Yes/No    |
| General enquiry        | ENQ-*                 | Yes/No    |
| Staff order processing | ORD-*                 | Yes/No    |
| Admin staff approval   | ADM-*                 | Yes/No    |

Every Version 1 workflow must be covered.

---

# 94. Missing Capability Review

Identify cases such as:

```text
UI needs data
but no endpoint exists

endpoint exists
but no authorized actor exists

action exists
but no state rule exists

resource exists
but no secure ownership path exists
```

Any gap must be resolved before the endpoint inventory is frozen.

---

# 95. Redundant Capability Review

Identify:

```text
two endpoints for one business operation
```

and choose a canonical endpoint.

Examples:

```text
POST /requests
POST /product-requests
```

Only one should remain.

---

# 96. Backward Compatibility Review

Each endpoint must respect Version 1 compatibility.

Do not introduce:

```text
unstructured responses
inconsistent errors
undocumented enum values
inconsistent field names
```

Use the centralized conventions.

---

# 97. Endpoint Naming Review

Compare every path against Phase 1.9:

```text
lowercase
plural resources
kebab-case where required
shallow nesting
nouns
controlled actions
/api/v1 prefix
```

Reject inconsistencies.

---

# 98. HTTP Method Review

Compare every method against Phase 1.10.

Especially:

```text
GET
→ read

POST
→ create/action

PATCH
→ partial update

PUT
→ true replacement only

DELETE
→ actual removal
```

Do not use methods inconsistently.

---

# 99. Query Review

Compare every collection endpoint against Phase 1.11.

Only approved query conventions may be used.

Do not invent endpoint-specific:

```text
pageSize
sortBy
searchTerm
```

aliases.

---

# 100. Pagination Review

Compare every collection endpoint against Phase 1.12.

Document:

```text
paginated
not paginated
```

and use the global pagination contract.

---

# 101. Response Review

Every endpoint must use the Phase 1.13 response architecture.

Do not create:

```text
raw arrays
custom result wrappers
endpoint-specific envelopes
```

without explicit approval.

---

# 102. Input Review

Every mutation must conform to Phase 1.14.

Do not accept:

```text
client-controlled totals
server-generated IDs
roles
statuses
inventory authority
payment confirmation
```

as normal customer inputs.

---

# 103. Validation Review

Every endpoint must reference Phase 1.15 validation principles.

Do not put all validation into:

```text
controller
```

or:

```text
frontend
```

Business/domain rules remain authoritative.

---

# 104. Error Review

Every endpoint must use Phase 1.16.

Do not invent:

```text
{success:false}
```

or endpoint-specific error shapes.

---

# 105. Authentication Review

Compare endpoint authentication against Phase 1.17.

Examples:

```text
Products → public
Requests → anonymous allowed
Enquiries → anonymous allowed
Checkout → authenticated
Own Orders → authenticated
Staff order operations → staff
Staff approval → admin
```

---

# 106. Authorization Review

Compare every protected endpoint against Phase 1.18.

Ask:

```text
Who?
Which resource?
Which action?
Which ownership?
Which state?
```

If any answer is unclear, the endpoint is not ready.

---

# 107. Security Review

Before freezing the endpoint inventory, inspect for:

```text
IDOR
privilege escalation
role tampering
customer data leakage
staff over-permission
admin overexposure
public inventory exposure
payment-secret exposure
attachment leakage
unsafe anonymous endpoints
```

---

# 108. Special Security Review — Customer

Verify that no endpoint gives Staff a mechanism to:

```text
block customer browsing
block customer ordering
change customer password
impersonate customer
change customer ownership
```

without an explicitly approved administrative/security feature.

---

# 109. Special Security Review — Staff

Verify no endpoint allows Staff to:

```text
approve themselves
create Admin
change own role
access credentials
view unrelated private customer data
```

---

# 110. Special Security Review — Admin

Verify Admin operations are:

```text
authenticated
authorized
auditable where appropriate
state-aware
```

Do not use `ADMIN = unrestricted database access` as an API design.

---

# 111. Anonymous Endpoint Security Review

The only anonymous mutation capabilities currently approved are primarily:

```text
customer registration
made-to-order request
general enquiry
authentication/recovery operations as appropriate
```

Every anonymous mutation must be reviewed for:

```text
abuse
spam
rate limiting
data leakage
enumeration
file upload risk
```

Do not add anonymous commerce mutations.

---

# 112. Rate-Limited Endpoint Candidates

Mark endpoints likely to require rate limits later:

```text
login
registration
password recovery
anonymous request
anonymous enquiry
checkout
payment
```

Rate-limit implementation is later.

---

# 113. Idempotency Candidates

Mark:

```text
checkout
payment initiation
payment webhook
critical order actions
```

for later idempotency implementation.

---

# 114. Concurrency Candidates

Mark:

```text
checkout
inventory
order status transitions
payment confirmation
```

for later concurrency implementation/testing.

---

# 115. Endpoint Dependency Graph

Create a conceptual dependency graph.

Example:

```text
AUTH-001 Register
       ↓
AUTH-002 Login
       ↓
CART-001 Cart
       ↓
CART-002 Add item
       ↓
CHK-001 Checkout
       ↓
PAY-* Payment
       ↓
ORD-* Order
       ↓
ORD-* Tracking
```

Made-to-order:

```text
REQ-001 Request
       ↓
REQ-* Attachments
```

Admin:

```text
AUTH
 ↓
ADM staff approval
 ↓
ORD operational endpoints
```

This confirms that all workflows are connected.

---

# 116. Endpoint Completeness Gate

For every endpoint, confirm:

```text
resource exists
+
relationship exists
+
actor exists
+
auth rule exists
+
authorization rule exists
+
HTTP method exists
+
path naming valid
+
request convention exists
+
response convention exists
+
error contract exists
```

If one is missing, mark the endpoint incomplete.

---

# 117. Required Deliverables

Update:

## `docs/api/api-contract.md`

Add the authoritative:

```text
## Version 1 Endpoint Inventory
```

containing:

* endpoint IDs;
* method;
* path;
* domain;
* resource;
* purpose;
* actors;
* authentication;
* authorization;
* query requirements;
* pagination;
* request summary;
* response summary;
* errors;
* idempotency;
* state/concurrency notes.

---

## `docs/api/api-resources.md`

For each resource, list:

```text
resource
related endpoint IDs
available operations
access scope
```

---

## `docs/api/api-conventions.md`

Add any conventions discovered while building the endpoint inventory, but do not create endpoint-specific exceptions unless explicitly approved.

---

## `docs/domain/business-rules.md`

Update only when an endpoint review reveals a missing representation of an already-approved business rule.

Do not invent new business behavior.

---

## `docs/decisions.md`

Record important endpoint-inventory decisions such as:

```text
API-END-001
Canonical customer order path uses self-context.

API-END-002
Category filtering uses the Product collection rather than duplicate nested retrieval.

API-END-003
Order state changes use controlled actions rather than unrestricted PATCH.

API-END-004
Anonymous request creation does not imply anonymous request retrieval.
```

Use the project's existing decision format.

---

# 118. Endpoint Inventory Format

Use a table like:

```markdown
| ID | Method | Path | Domain | Actors | Auth | Purpose |
|---|---|---|---|---|---|---|
| CAT-001 | GET | `/api/v1/products` | Catalog | Anonymous, Customer, Staff, Admin | No | List public products |
| CAT-002 | GET | `/api/v1/products/{product}` | Catalog | Anonymous, Customer, Staff, Admin | No | Product detail |
| AUTH-001 | POST | `/api/v1/auth/register` | Identity | Anonymous | No | Register customer |
...
```

Then provide additional endpoint details below the table where necessary.

---

# 119. Do Not Over-Specify Yet

Phase 1.19 should define the endpoint catalogue, but not become Phase 1.20 prematurely.

Do not fully write:

```text
every JSON property
all validation rules
full example payloads
OpenAPI schemas
```

unless needed to resolve endpoint ambiguity.

Those will be finalized in later phases.

---

# 120. Endpoint Contract Status

At the end of this phase, every endpoint must be classified as:

```text
APPROVED
DEFERRED
REJECTED
```

### APPROVED

Required for Version 1 and sufficiently defined.

### DEFERRED

Potentially needed later but not Version 1.

### REJECTED

Considered but intentionally excluded.

---

# 121. Version 1 Endpoint Scope Discipline

Do not approve endpoints merely because:

> "we might need it later."

For a small-scale furniture ecommerce system, Version 1 should remain focused on:

```text
catalog
customer accounts
cart
checkout
orders
tracking
pickup/delivery
made-to-order requests
enquiries
staff operations
admin/staff management
```

---

# 122. Recommended Version 1 Endpoint Grouping

The endpoint inventory should broadly fall into:

```text
AUTH
CAT
CART
CHK
ORD
PAY
FUL
REQ
ENQ
NOT
USER
INV
ADM
WEBHOOK
```

Do not create a group unless the API actually needs it.

---

# 123. Payment Grouping

Payment endpoints should be marked:

```text
PAY-xxx
Owner: Phase Group H
```

and webhook endpoints:

```text
WEBHOOK-xxx
Owner: Phase Group H
```

Do not accidentally assign them to Group G.

---

# 124. Endpoint Count Review

Do not optimize for having many endpoints.

The goal is:

> **The smallest coherent endpoint surface that fully supports Version 1.**

Too few endpoints can produce:

```text
giant generic endpoint
```

Too many can produce:

```text
unnecessary complexity
```

Seek the simplest useful API.

---

# 125. API Surface Security Rule

Every additional endpoint increases:

```text
attack surface
testing surface
documentation surface
authorization complexity
maintenance cost
```

Therefore each endpoint needs a reason to exist.

---

# 126. Final Security Review

Before approval, perform a focused security walkthrough:

```text
Can anonymous users reach private data?
Can Customer A reach Customer B?
Can Customer become Staff?
Can Staff become Admin?
Can Staff block customers?
Can Staff access credentials?
Can a client change an order status directly?
Can a client change a price?
Can a client change delivery fee?
Can a client force payment success?
Can a client manipulate cancellation time?
Can a client expose attachments?
Can a client access internal inventory?
```

Every answer must be safe.

---

# 127. Final Workflow Review

Run these conceptual flows against the endpoint catalogue.

### Anonymous browsing

```text
GET products
GET categories
GET product
```

### Customer purchase

```text
register/login
→ cart
→ checkout
→ payment
→ order
→ tracking
```

### Pickup

```text
checkout
→ PAID
→ ACCEPTED
→ PROCESSING
→ READY_FOR_PICKUP
→ COMPLETED
```

### Delivery

```text
checkout
→ PAID
→ ACCEPTED
→ PROCESSING
→ SHIPPED
→ DELIVERED
→ COMPLETED
```

### Cancellation

```text
customer
→ own order
→ cancel
→ 20-minute rule
```

### Made-to-order

```text
anonymous/customer
→ request
→ optional attachment
```

### Enquiry

```text
anonymous/customer
→ enquiry
→ optional attachment
```

### Staff

```text
staff
→ receive/process orders
→ fulfillment
→ requests
→ enquiries
→ operational notifications
```

### Admin

```text
admin
→ approve/manage staff
→ catalog
→ inventory
→ orders
→ requests
→ enquiries
```

Every flow must be endpoint-complete.

---

# 128. Validation Checklist

### Endpoint structure

* [ ] Every endpoint has a stable ID.
* [ ] Every endpoint has a method.
* [ ] Every endpoint has a versioned path.
* [ ] Every endpoint has one clear purpose.
* [ ] Resource naming matches Phase 1.9.
* [ ] HTTP method matches Phase 1.10.

### Public catalog

* [ ] Products can be browsed anonymously.
* [ ] Categories can be browsed anonymously.
* [ ] Product detail works anonymously.
* [ ] Search/filtering is supported appropriately.
* [ ] Internal inventory is not exposed.

### Authentication

* [ ] Customer registration exists.
* [ ] Login exists.
* [ ] Logout exists.
* [ ] Current-user access exists.
* [ ] Password recovery is represented.
* [ ] Verification is represented where required.
* [ ] Staff/Admin cannot self-register through customer registration.

### Customer

* [ ] Own cart is supported.
* [ ] Checkout requires authentication.
* [ ] Own orders are supported.
* [ ] Own tracking is supported.
* [ ] Eligible cancellation is supported.
* [ ] Own requests/enquiries are supported where applicable.
* [ ] Notifications are supported.

### Requests/enquiries

* [ ] Anonymous requests are supported.
* [ ] Anonymous enquiries are supported.
* [ ] Authenticated ownership is supported.
* [ ] Attachments are optional.
* [ ] Private data is protected.

### Staff

* [ ] Operational orders are supported.
* [ ] Order transitions use controlled operations.
* [ ] Requests are supported.
* [ ] Enquiries are supported.
* [ ] Operational notifications are supported.
* [ ] Staff cannot block customers.
* [ ] Staff cannot change customer roles.
* [ ] Staff cannot access credentials.

### Admin

* [ ] Staff approval is supported.
* [ ] Staff management is supported.
* [ ] Authorized catalog management is supported.
* [ ] Inventory management is supported.
* [ ] Administrative order management is supported.
* [ ] Customer administration is explicitly bounded.

### Business integrity

* [ ] Product type rules are represented.
* [ ] Inventory rules are represented.
* [ ] Pricing authority is represented.
* [ ] Delivery fee authority is represented.
* [ ] 20-minute cancellation rule is represented.
* [ ] Historical order data is protected conceptually.
* [ ] Payment remains separate from order status.

### API consistency

* [ ] Query conventions are consistent.
* [ ] Pagination conventions are consistent.
* [ ] Response conventions are consistent.
* [ ] Input conventions are consistent.
* [ ] Error conventions are consistent.
* [ ] Version 1 enums remain CLOSED.
* [ ] Endpoint-specific conventions do not contradict global conventions.

### Security

* [ ] Object-level authorization is defined.
* [ ] Function-level authorization is defined.
* [ ] Role escalation is blocked.
* [ ] Customer horizontal access is blocked.
* [ ] Sensitive fields are protected.
* [ ] Anonymous mutation endpoints are reviewed.
* [ ] Rate-limit candidates are identified.
* [ ] Idempotency candidates are identified.
* [ ] Concurrency-sensitive endpoints are identified.

### Payment

* [ ] Payment endpoint placeholders belong to **Group H**.
* [ ] Webhooks belong to **Group H**.
* [ ] No payment provider is selected here.

---

# 129. Explicitly Out of Scope

Do NOT:

```text
Implement Laravel routes
Implement Laravel controllers
Implement middleware
Implement policies
Implement Form Requests
Implement API Resources
Implement DTOs
Implement repositories
Implement database queries
Implement authentication
Implement authorization
Implement payment integration
Generate full OpenAPI schemas
Build Next.js API clients
Build Flutter API clients
Build Admin UI
```

---

# 130. Definition of Done

Phase 1.19 is complete when:

1. Every required Version 1 workflow has endpoint coverage.
2. Every endpoint has a stable ID.
3. Every endpoint has a concrete method and path.
4. Every endpoint has an actor classification.
5. Authentication requirements are explicit.
6. Authorization requirements are explicit.
7. Resource ownership is explicit where applicable.
8. State-dependent actions are identified.
9. Query/pagination requirements reference the global conventions.
10. Request/response conventions reference the global contract.
11. Relevant error codes are identified.
12. Idempotency-sensitive operations are identified.
13. Concurrency-sensitive operations are identified.
14. Public catalog endpoints remain public.
15. Anonymous requests/enquiries remain supported.
16. Checkout remains authenticated.
17. Customer accounts remain customer-owned.
18. Staff remain operational and cannot arbitrarily restrict customers.
19. Admin remains the highest administrative role and approves staff.
20. Payment endpoints are assigned to **Phase Group H**.
21. No unnecessary duplicate endpoints exist.
22. No future-only features have been accidentally included.
23. Security review passes.
24. Workflow coverage review passes.
25. The endpoint catalogue is sufficiently stable for detailed endpoint-contract examples.

---

# 131. STOP CONDITION — Mandatory

After updating:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md where necessary
docs/decisions.md
```

and completing the endpoint/security/workflow review:

**STOP.**

Do not begin Laravel implementation.

Do not write endpoint controllers.

Do not write OpenAPI operations.

Do not build frontend API clients.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.20 — Define API Contract Examples

This phase should take the approved endpoint inventory and create **canonical request/response examples** for the most important Version 1 operations, covering success, validation failure, authorization failure, business conflicts, pagination, authentication, checkout, order lifecycle, anonymous requests/enquiries, and staff/admin operations.
