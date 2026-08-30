# Phase 1.14 — Define Request Body and Input Conventions

## 1. Purpose

Phase 1.14 establishes the project's **standard conventions for API input**.

The objective is to ensure that every Laravel API operation receives input in a predictable, secure, and consistent way across:

```text
Next.js website
Flutter mobile app
Admin application
Future API clients
```

This phase defines:

* request body format;
* JSON conventions;
* field naming;
* required/optional/conditional fields;
* null handling;
* empty values;
* data types;
* nested input;
* arrays;
* partial updates;
* resource creation input;
* business-action input;
* input normalization;
* unknown fields;
* client-controlled fields;
* immutable fields;
* mass-assignment protection;
* request size/security considerations.

This phase does **not** define the request schema for individual endpoints.

---

# 2. Documentation Strategy

Continue using the consolidated-document model.

Do **not** create permanent files such as:

```text
request-body-conventions.md
input-rules.md
request-validation.md
request-decisions.md
```

Instead update:

```text
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/decisions.md
```

The phase instruction itself is temporary working guidance.

The project should retain a small number of authoritative documents.

---

# 3. Authoritative Project Paths

Use:

```text
AGENTS.md
docs/VISION.md
```

Do not use older paths:

```text
agent.md
VISION.md
```

unless preserving historical records.

---

# 4. Payment Assignment

Payment-specific request structures and provider-specific payloads remain assigned to:

**Phase Group H**

not Group G.

Phase 1.14 may establish generic input rules applicable to payment, but must not define provider-specific payment request formats.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

This applies to request bodies as well as responses.

For example:

```text
product_type
fulfillment_type
order status
request status
enquiry status
role
```

must accept only documented Version 1 values.

Unknown enum values must not silently become:

```text
"other"
"unknown"
"custom"
```

unless the API contract explicitly defines such a value.

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
1.14 Request/Input Conventions
       ↓
1.15 ...
```

Do not skip forward into endpoint-specific request schemas.

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
```

Also review the completed Phase 1.1–1.13 decisions.

Do not reopen settled business decisions.

---

# 8. Core Input Principle

The API should accept structured, explicit input.

Use:

```text
Content-Type: application/json
```

for normal JSON API operations.

Exceptions such as file uploads may use another content type when required.

Do not make clients send URL-encoded JSON strings inside JSON fields.

---

# 9. JSON as the Default Request Format

For ordinary create/update/action requests, use JSON.

Example:

```json
{
  "quantity": 2,
  "fulfillment_type": "DELIVERY"
}
```

Do not create different field naming or serialization conventions per frontend.

---

# 10. Content-Type Rule

For JSON requests:

```text
application/json
```

must be used consistently.

The API should reject unsupported or incorrect content types according to the later error contract rather than silently guessing.

---

# 11. Field Naming

Use the same JSON naming convention established in Phase 1.13.

Recommended:

```text
snake_case
```

Examples:

```json
{
  "product_id": "123",
  "fulfillment_type": "DELIVERY",
  "notes": "Leave at gate"
}
```

Do not mix:

```text
productId
product_id
product-id
```

in the same API.

---

# 12. Required Fields

A request contract must distinguish:

```text
required
optional
conditional
read-only
server-generated
```

Do not infer required fields solely from database nullability.

Example:

```text
delivery_address
→ conditional
→ required when fulfillment_type = DELIVERY
```

while:

```text
delivery_address
→ not required for PICKUP
```

---

# 13. Optional Fields

Optional fields may be omitted.

Example:

```json
{
  "name": "Modern Sofa"
}
```

is different from:

```json
{
  "name": "Modern Sofa",
  "description": null
}
```

unless the resource contract defines those two representations as equivalent.

---

# 14. Null Handling

Define a consistent rule:

> `null` is used only where the field is explicitly nullable.

Do not allow clients to send:

```json
{
  "quantity": null
}
```

where quantity is required.

Likewise, do not use `null` as a substitute for omission unless the field is contractually nullable.

---

# 15. Empty Strings

Do not treat empty strings as a universal representation of "not supplied."

For example:

```json
{
  "phone": ""
}
```

should not automatically mean:

```text
phone = not provided
```

unless the contract explicitly defines that.

Prefer:

```text
field omitted
```

or:

```text
field = null
```

according to the field's semantics.

---

# 16. Whitespace Normalization

Define input normalization expectations.

For user-entered text:

```text
"   Modern Sofa   "
```

may logically become:

```text
"Modern Sofa"
```

where appropriate.

However, do not silently alter data where whitespace may be meaningful.

The backend should own normalization policy.

Clients may perform cosmetic trimming, but server normalization remains authoritative.

---

# 17. Case Normalization

For enums and other canonical values, clients must send the documented exact value.

Example:

```json
{
  "product_type": "IN_STOCK"
}
```

Do not require the backend to accept:

```text
in_stock
InStock
IN-stock
```

unless explicitly documented as aliases.

Because Version 1 enums are CLOSED, prefer one canonical representation.

---

# 18. Type Strictness

Request values should use the correct JSON types.

Example:

```json
{
  "quantity": 2,
  "is_active": true
}
```

not:

```json
{
  "quantity": "2",
  "is_active": "true"
}
```

Do not make the API accept broad type coercion merely for convenience.

---

# 19. Numeric Input

Define numeric types explicitly.

For example:

```text
quantity
→ integer

delivery fee
→ monetary representation defined in Phase 1.13

percentage
→ numeric format if later required
```

The backend must validate:

* type;
* range;
* precision;
* business validity.

Do not trust client-side validation.

---

# 20. Money Input

Use the same monetary representation defined in Phase 1.13.

Do not accept:

```json
{
  "delivery_fee": "TZS 30,000"
}
```

as the authoritative financial input.

The customer generally should not even submit arbitrary delivery fees.

Where a customer selects:

```text
DELIVERY
```

the backend determines the applicable fee.

---

# 21. Server-Controlled Fields

Identify fields that the client must never be allowed to set.

Examples:

```text
id
created_at
updated_at
order_reference
current_order_status
payment_status
inventory_quantity
reserved_quantity
final_order_total
approved_delivery_fee
payment_confirmation
```

These values are server-controlled.

Even if they appear in a client request, they must be ignored or rejected according to the future endpoint contract.

---

# 22. Client-Controlled Fields

Identify fields that clients legitimately supply.

Examples:

```text
product selection
quantity
fulfillment type
delivery/contact information
request details
enquiry message
profile fields
```

Each endpoint later decides its exact accepted fields.

---

# 23. Never Accept a Client-Supplied Final Total

A customer may submit:

```json
{
  "fulfillment_type": "DELIVERY"
}
```

but must not be able to submit:

```json
{
  "total": 1000
}
```

as the final authoritative order amount.

The backend calculates it.

---

# 24. Never Accept a Client-Supplied Order Status

Do not allow:

```json
{
  "status": "DELIVERED"
}
```

to become an arbitrary order transition.

Order states are business actions and must follow the approved lifecycle.

---

# 25. Never Accept a Client-Supplied Payment Confirmation

Do not trust:

```json
{
  "payment_status": "PAID"
}
```

from a browser or Flutter client.

Payment confirmation is backend/provider-controlled and belongs to later payment work in Group H.

---

# 26. Never Accept Client Inventory Authority

Do not accept:

```json
{
  "stock_quantity": 50
}
```

from the customer application as a purchase decision.

Inventory is server-controlled.

---

# 27. Create vs Update

Different request semantics must be documented.

### Create

Client provides information needed to create a new resource.

### Update

Client changes an existing resource.

### Action

Client requests a business operation that changes state.

Do not make these three indistinguishable.

---

# 28. Create Request Conventions

For resource creation:

```text
id="q2j3q8"
client supplies:
business inputs

server supplies:
resource identity
timestamps
derived fields
security-sensitive fields
```

Example conceptually:

```json
{
  "name": "Customer enquiry",
  "message": "Can you make this table in walnut?"
}
```

The backend determines:

```text
id
created_at
status
```

---

# 29. PATCH Request Conventions

PATCH represents partial updates.

A PATCH request should contain only the fields being changed.

Example:

```json
{
  "phone": "+255..."
}
```

Do not require every resource property in a PATCH request.

---

# 30. PATCH Does Not Mean "Anything Goes"

Only documented mutable fields may be changed.

For example, a customer may be allowed to change:

```text
name
phone
```

but not:

```text
role
account_status
order_total
```

The backend must enforce field allow-lists.

---

# 31. PUT Request Conventions

If the project permits PUT, it means complete resource replacement.

Do not use PUT as:

```text
another PATCH
```

If no Version 1 resource needs true replacement semantics, the API may document PUT as unused.

---

# 32. Action Request Conventions

Business actions use their own request model.

Examples:

```text
cancel order
accept order
ship order
deliver order
```

The request should contain only information genuinely required by that action.

Do not send the complete Order object back to the server just to request:

```text
ship
```

---

# 33. Action Inputs Must Be Minimal

For example, a shipping action may require:

```json
{
  "note": "Delivered to courier"
}
```

rather than:

```json
{
  "order": {
    "...": "entire order"
  },
  "status": "SHIPPED"
}
```

This reduces accidental client authority over protected state.

---

# 34. Nested Input

Some operations may legitimately contain nested data.

Example conceptually:

```json
{
  "shipping_address": {
    "name": "...",
    "phone": "...",
    "address_line": "...",
    "city": "..."
  }
}
```

Nested objects must be explicitly defined.

Do not allow arbitrary nested structures to be persisted.

---

# 35. Nested Input Ownership

Nested input must respect ownership.

For example:

```json
{
  "order": {
    "customer_id": "someone-else"
  }
}
```

must not allow the client to change order ownership.

The server determines ownership from authenticated identity and business rules.

---

# 36. Arrays

Arrays must contain elements of one expected type.

Example:

```json
{
  "items": [
    {
      "product_id": "123",
      "quantity": 2
    }
  ]
}
```

Do not accept arbitrary heterogeneous arrays unless the contract explicitly requires it.

---

# 37. Empty Arrays

Define whether empty arrays are:

```text
valid
invalid
equivalent to omission
```

Example:

```json
{
  "items": []
}
```

For a create operation requiring at least one item, this should be invalid.

Do not allow inconsistent semantics between resources.

---

# 38. Duplicate Array Items

Where arrays represent a set of logical items, define duplicate handling.

Example:

```json
{
  "items": [
    {"product_id": "123", "quantity": 1},
    {"product_id": "123", "quantity": 2}
  ]
}
```

The API must eventually define whether this is:

```text
invalid
merged
accepted as separate lines
```

Do not leave this to database behavior.

---

# 39. Unknown Fields

Choose a project-wide policy for fields the client sends that are not part of the contract.

Recommended:

> Reject unknown fields for sensitive/strict operations rather than silently accepting them.

This helps detect:

* client/API version mismatches;
* typos;
* outdated mobile clients;
* accidental malicious input.

However, compatibility must be considered carefully for mature clients.

Record the final policy and apply it consistently.

---

# 40. Unknown Fields vs Forward Compatibility

A strict unknown-field policy improves correctness but can make clients less tolerant of future additions.

Therefore choose deliberately.

For this project, a practical approach is:

> Strict input validation for create/update/action requests, with explicitly documented exceptions where forward compatibility requires otherwise.

Do not let Laravel's default behavior accidentally become the API policy.

---

# 41. Read-Only Input Fields

Document fields that may appear in responses but may never be submitted for mutation.

Examples:

```text
id
created_at
updated_at
order_reference
status
current price in historical order item
payment confirmation
```

This distinction should be reflected in future endpoint schemas.

---

# 42. Server-Generated Fields

The following are generally server-generated:

```text
id
timestamps
order reference
current state
derived totals
inventory state
payment confirmation
```

Do not require clients to send them.

---

# 43. User Identity Input

For authenticated requests, do not require the client to send:

```json
{
  "user_id": "current-user"
}
```

to establish ownership.

The backend already knows the authenticated identity.

If a resource references a user for an administrative operation, that is a different authorization context.

---

# 44. Anonymous Request Identity

For anonymous made-to-order requests and enquiries:

```text
User = optional
```

The request itself must contain the approved contact information.

Do not fabricate a user account merely to satisfy data persistence.

---

# 45. Checkout Input

Checkout requests should contain only the information the customer is actually selecting/providing.

Conceptually:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "...": "..."
  }
}
```

The backend determines:

```text
cart contents
current prices
stock
delivery fee
subtotal
total
order reference
```

Do not accept those as authoritative client fields.

---

# 46. Cart Item Input

Adding to a cart should require:

```text
product/variant reference
quantity
```

The backend validates:

```text
product
variant
product type
availability
quantity
```

Do not accept:

```text
price
subtotal
stock
```

as authoritative input.

---

# 47. Made-to-Order Request Input

A request may accept:

```text
product reference where applicable
quantity
dimensions
material
color
notes
contact information
optional attachment
```

The request must not contain:

```text
order_id
payment_status
inventory_reservation
```

as client-authoritative fields.

---

# 48. General Enquiry Input

An enquiry may accept:

```text
name
phone
email
subject
message
optional attachment
```

Do not allow clients to directly define:

```text
status
staff assignment
closed_at
internal note
```

unless later administrative contracts explicitly permit those operations.

---

# 49. Profile Input

Customer profile update should be limited to explicitly mutable fields.

Potential examples:

```text
name
phone
```

Email changes, password changes, and verification changes may require dedicated workflows rather than ordinary PATCH.

Do not treat all identity fields as ordinary profile fields.

---

# 50. Normalization

The backend should normalize data where the domain allows it.

Examples:

```text
email
phone
whitespace
```

**Explicit exception — enums are not case-normalized:** CLOSED enums (e.g., `product_type: IN_STOCK`, `fulfillment_type: DELIVERY`) must be sent in canonical `UPPER_SNAKE_CASE` (`IN_STOCK`) per §17; `in_stock` / `InStock` are validation errors, not normalized aliases. Do not add `case for enumerations` as a normalization rule.

But normalization must not alter meaningful user content unexpectedly.

Phone and email normalization rules should be formally defined in later authentication/input-specific work.

---

# 51. Validation Ordering

Conceptually validate in this order:

```text
id="8sft9b"
1. Transport/content type
        ↓
2. JSON syntax
        ↓
3. Basic type/schema validation
        ↓
4. Authentication
        ↓
5. Authorization
        ↓
6. Domain/business validation
        ↓
7. Persistence/workflow operation
```

The exact Laravel implementation comes later.

---

# 52. Authorization Before Sensitive Operations

Do not rely on validation alone.

Example:

```text
order_id = valid UUID
```

does not mean:

```text
customer is allowed to modify this order
```

Authorization must be evaluated separately.

---

# 53. Mass Assignment Protection

The eventual Laravel implementation must never blindly pass arbitrary request data into models.

Avoid conceptual behavior such as:

```text
$request->all()
→ model fill
```

without an explicit field allow-list.

The request contract defines accepted business input.

The backend then maps accepted input deliberately.

---

# 54. Request-to-Domain Mapping

Establish the rule:

```text
HTTP request
     ↓
validated input
     ↓
application command/DTO
     ↓
domain/application logic
```

Do not make the domain model depend directly on arbitrary HTTP request structures.

This keeps the API contract separate from internal Laravel implementation.

---

# 55. Do Not Expose Internal Field Names

The API should not automatically accept whatever names the database uses.

For example:

```text
database:
reserved_quantity

customer API:
should not accept reserved_quantity
```

The API vocabulary must remain intentional.

---

# 56. Request Size Limits

Define that the API should eventually enforce reasonable maximum request sizes.

Consider separately:

```text
JSON body
file upload
array size
number of cart items
message length
notes length
```

Do not set arbitrary huge limits.

Do not configure infrastructure yet.

---

# 57. File Upload Requests

Attachments are optional.

When a request/enquiry includes an attachment, the transport may differ from ordinary JSON.

Evaluate:

```text
multipart/form-data
```

versus:

```text
pre-uploaded file reference
```

Do not select a storage provider yet.

The API contract must eventually define the accepted upload strategy.

---

# 58. Multipart and JSON Consistency

If multipart is selected later, the logical field names must remain consistent with JSON requests.

Do not create:

```text
JSON:
requested_dimensions

multipart:
dimensions_requested
```

for the same concept.

---

# 59. Request Idempotency Preparation

Identify requests that can create duplicate business effects.

At minimum:

```text
checkout
payment initiation
critical order actions
```

These require later idempotency design.

Do not implement the idempotency mechanism in this phase.

---

# 60. Duplicate Form Submission

A user may:

```text
tap twice
reload
retry after timeout
```

and send the same mutation twice.

The input contract must eventually accommodate idempotency for operations where duplicate execution is dangerous.

---

# 61. Input and Closed Enums

For all Version 1 enum fields:

```text
product_type
fulfillment_type
order status
request status
enquiry status
roles
payment state
```

the request must use only canonical documented values.

Example:

```json
{
  "fulfillment_type": "DELIVERY"
}
```

is valid.

An undocumented value is invalid.

---

# 62. Input Security

The API must guard against:

```text
SQL injection
XSS payloads
malicious file uploads
oversized payloads
mass assignment
authorization bypass
parameter pollution
type confusion
unexpected nested structures
```

Do not attempt to solve these through frontend validation alone.

---

# 63. Parameter Pollution

If the same scalar field appears multiple times:

```text
quantity=2
quantity=100
```

or within JSON where duplicates are possible, the API should have deterministic parsing behavior.

Do not allow ambiguous client input to produce different values depending on framework internals.

---

# 64. Immutable Fields

Create a conceptual list of fields that cannot be modified through normal customer operations:

```text
order reference
order customer identity
historical order item price
order creation timestamp
payment confirmation
inventory ownership
status history
```

Additional immutable fields may be identified later.

---

# 65. Input and Historical Data

A client's update to the current Product must never rewrite:

```text
historical Order Item
```

Similarly, modifying the current delivery fee policy must not rewrite:

```text
historical Order delivery fee
```

This is an important connection between request rules and historical-data invariants.

---

# 66. Input and Authorization Context

The same resource can have different accepted inputs depending on actor.

For example:

```text
Customer
→ update own profile

Staff/Admin
→ update operational order state
```

Do not define one universal request schema that accepts every possible field from every actor.

---

# 67. Input Contract Layering

Conceptually distinguish:

```text
Public/customer input
        ↓
Staff input
        ↓
Admin input
```

Each should expose only the fields relevant to its role.

---

# 68. API Contract Documentation

Update:

```text
docs/api/api-contract.md
```

with sections:

```text
## Request Conventions

### Content Type
### JSON Encoding
### Field Naming
### Required/Optional/Conditional Fields
### Nulls
### Empty Values
### Primitive Types
### Arrays
### Nested Objects
### Unknown Fields
### Read-Only Fields
### Server-Generated Fields
### Client-Controlled Fields
### Immutable Fields
### Enum Inputs
### Money Inputs
### Date/Time Inputs
### File Uploads
### Idempotency-Sensitive Requests
### Request Size Limits
```

Do not create separate permanent files for each section.

---

# 69. Update API Resource Documentation

In:

```text
docs/api/api-resources.md
```

prepare space for each resource's future:

```text
Create input
Update input
Action input
Read-only fields
Server-generated fields
Role-specific input
```

Do not fill in final endpoint schemas yet.

---

# 70. Update API Conventions

In:

```text
docs/api/api-conventions.md
```

record the reusable input rules:

```text
JSON
snake_case
strict types
required/optional/conditional classification
null semantics
unknown field policy
server-authoritative values
closed enums
idempotency principles
mass-assignment protection
```

---

# 71. Central Decision Record

Do not create:

```text
api-input-decisions.md
```

Instead update:

```text
docs/decisions.md
```

Record important decisions such as:

```text
API-IN-001
JSON is the default request format.

API-IN-002
Version 1 enums are CLOSED.

API-IN-003
Client-supplied totals are never authoritative.

API-IN-004
PATCH is for partial modification only.

API-IN-005
Unknown input fields follow the chosen strictness policy.
```

Use the existing decision-record format if one has been established.

---

# 72. Suggested Decision IDs

Use:

```text
API-IN-001
API-IN-002
API-IN-003
...
```

Do not create duplicate decision IDs.

---

# 73. Validation Checklist

### Transport

* [ ] JSON is the default request format.
* [ ] `Content-Type` behavior is defined.
* [ ] File-upload transport is explicitly deferred or defined.
* [ ] Request size limits are recognized.

### Naming

* [ ] JSON field names follow one convention.
* [ ] API naming remains independent of database naming.

### Types

* [ ] JSON types are strict.
* [ ] Numeric values are defined.
* [ ] Booleans are real booleans.
* [ ] Dates/timestamps use the established format.
* [ ] Money follows the Phase 1.13 convention.

### Requiredness

* [ ] Required fields are explicit.
* [ ] Optional fields are explicit.
* [ ] Conditional fields are explicit.
* [ ] Nullable fields are explicit.

### Security

* [ ] Unknown-field policy is defined.
* [ ] Server-controlled fields are identified.
* [ ] Mass assignment is prohibited conceptually.
* [ ] Client cannot control price.
* [ ] Client cannot control final total.
* [ ] Client cannot control inventory.
* [ ] Client cannot control payment confirmation.
* [ ] Client cannot arbitrarily set order status.
* [ ] Customer ownership cannot be overridden.

### Business rules

* [ ] Checkout input is account-scoped.
* [ ] Delivery information is conditional.
* [ ] Anonymous requests remain supported.
* [ ] Anonymous enquiries remain supported.
* [ ] Made-to-order requests cannot masquerade as orders.
* [ ] Requests cannot reserve stock through client input.

### Enums

* [ ] Version 1 enums are CLOSED.
* [ ] Canonical enum values are documented.
* [ ] Unknown enum values are not silently accepted.
* [ ] The former OPEN/EXTENSIBLE rule remains retired.

### Client compatibility

* [ ] Next.js input conventions are supported.
* [ ] Flutter input conventions are supported.
* [ ] Admin input conventions are supported.
* [ ] Client-specific request dialects are not introduced.

### Payment

* [ ] Generic payment input principles are documented.
* [ ] Provider-specific payment input remains deferred to **Phase Group H**.

### Documentation

* [ ] `docs/api/api-contract.md` updated.
* [ ] `docs/api/api-conventions.md` updated.
* [ ] `docs/api/api-resources.md` updated where necessary.
* [ ] `docs/decisions.md` updated.
* [ ] No unnecessary permanent markdown files created.

---

# 74. Explicitly Out of Scope

Do NOT:

```text
Define Product create schema
Define Product update schema
Define Cart request schema
Define Checkout request schema
Define Order action schemas
Define Request form schema
Define Enquiry form schema
Define Payment-provider request schema
Define Laravel Form Requests
Define Laravel DTOs
Define controllers
Define validation rules in PHP
Create OpenAPI request schemas
Build Next.js forms
Build Flutter forms
Implement multipart upload
Implement payment provider
```

Those belong to later phases.

---

# 75. Definition of Done

Phase 1.14 is complete when:

1. The default request content type is established.
2. JSON conventions are established.
3. Request field naming is standardized.
4. Required/optional/conditional semantics are standardized.
5. Null and empty-value semantics are standardized.
6. Primitive types are standardized.
7. Array/nested-object rules are established.
8. Unknown-field behavior is defined.
9. Read-only/server-generated fields are identified.
10. Client-controlled fields are identified.
11. Immutable fields are identified.
12. PATCH input semantics are defined.
13. Business-action input semantics are defined.
14. Mass-assignment protection is an explicit architectural rule.
15. Money input follows the response money convention.
16. Version 1 closed-enum input is explicit.
17. Security/input-size requirements are identified.
18. Anonymous request/enquiry input remains supported.
19. Checkout remains authenticated.
20. Payment-specific input remains assigned to **Phase Group H**.
21. Existing consolidated project documents have been updated.
22. No endpoint-specific request schemas have been implemented.

---

# 76. STOP CONDITION — Mandatory

After completing the Phase 1.14 documentation updates:

```text
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/decisions.md
```

and passing the validation checklist:

**STOP.**

Do not automatically create endpoint request schemas.

Do not implement Laravel validation.

Do not create OpenAPI request bodies.

Do not build frontend forms.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.15 — Define API Validation and Domain-Validation Conventions

That phase should establish the distinction between:

```text
transport/schema validation
authentication
authorization
business/domain validation
cross-field validation
state-dependent validation
error aggregation
validation error codes
```

and how these layers cooperate without duplicating or bypassing the domain invariants defined earlier.
