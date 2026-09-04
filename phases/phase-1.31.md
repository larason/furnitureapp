# Phase 1.31 — Define Canonical API Examples

## 1. Purpose

Create the canonical Version 1 API examples that demonstrate how the approved API contract is actually used.

These examples are not implementation code and are not a substitute for the formal API contract.

Their purpose is to make the contract unambiguous for:

* Laravel backend implementation
* Next.js API integration
* Flutter API integration
* automated contract tests
* future OpenAPI examples
* human developers and AI coding agents

Every example must follow the rules established by Phases 1.16–1.30.

The examples must use one consistent:

* URL structure
* request format
* response structure
* identifier convention
* money representation
* date/time format
* enum vocabulary
* error format
* authentication convention
* authorization model

---

# 2. Dependencies

Treat these phases as authoritative:

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

Phase 1.30 must be considered the consistency gate.

Do not create examples from stale versions of earlier contracts.

---

# 3. Primary Rule

The examples must demonstrate the **actual canonical contract**, not an idealized alternative.

Do not invent fields merely because they make an example more realistic.

Every field appearing in an example must be:

* defined by the contract,
* explicitly documented as an example-only placeholder,
* or removed.

Likewise, every important field defined by the contract should appear in examples where doing so is necessary to demonstrate its meaning.

---

# 4. Example Philosophy

Use examples that are:

* realistic
* minimal
* deterministic enough for documentation
* internally consistent
* reusable as future automated fixtures
* safe to copy into tests
* representative of both website and mobile clients

Avoid enormous payloads.

Do not duplicate the entire schema in every example.

Examples should demonstrate behavior rather than become a second schema definition.

---

# 5. Canonical Example Data Set

Create a small reusable fictional dataset for examples.

Use consistent identifiers across examples.

Example conceptual dataset:

```text
Customer:
CUS-0001

Staff:
USR-0101

Admin:
USR-0001

Product:
PRD-0001

Product slug:
solid-oak-dining-table

Variant:
VAR-0001

Cart:
CRT-0001

Order:
OD-00001

Made-to-order request:
REQ-0001

General enquiry:
ENQ-0001

Notification:
NTF-0001
```

These are documentation identifiers only.

Do not imply that production identifiers must use these exact formats unless those formats are already part of the contract.

The order reference must continue to follow the approved:

```text
OD-*****
```

format.

---

# 6. Canonical Example Environment

Use:

```text id="42jtik"
https://api.example.com
```

only as a documentation/example host (origin, **without** `/api/v1`).

Do not hard-code a real production domain.

All endpoint examples already include the version prefix in their path (e.g., `GET /api/v1/products`). Combining origin + path yields `https://api.example.com/api/v1/products` — never `https://api.example.com/api/v1` + `/api/v1/products` → `/api/v1/api/v1/products`. Keep the version prefix in **one place** (the path).

---

# 7. Authentication Example Convention

When an authenticated request is shown, use one canonical placeholder convention.

For example:

```http
Authorization: Bearer <ACCESS_TOKEN>
```

Do not put actual secrets into examples.

For authenticated examples identify the actor conceptually:

```text
Actor: CUSTOMER
Actor: STAFF
Actor: ADMIN
```

Do not place credentials in request bodies unless the authentication contract specifically requires them for that particular operation.

---

# 8. Global Response Convention

All examples must follow the response envelope defined by the API contract.

If the approved contract uses direct resource payloads, keep direct resource payloads.

If it uses a data envelope, use the same envelope everywhere.

Do not introduce a new example-only response convention.

The same rule applies to collection responses and pagination metadata.

---

# 9. Global Error Convention

Canonical error examples must use (schematic — actual wire shape always includes `meta.request_id` per `docs/api/api-examples.md §2`/`§15`/`§16` and `docs/api/api-contract.md §15`):

```json
{
  "errors": [
    {
      "code": "SOME_STABLE_CODE",
      "message": "Human-readable message.",
      "field": "delivery_address.city",
      "details": {}
    }
  ],
  "meta": {
    "request_id": "01H9-req-abc123"
  }
}
```

`field` and `details` are included only where the contract defines them (`422` with field, etc.); `meta.request_id` is required on every error response — never omit. The minimal schematic without `meta` is not copy-pasteable for implementation.

Use only stable machine-readable error codes that are defined by the API contract.

Do not place:

* SQL errors
* stack traces
* Laravel exception names
* file paths
* database table names
* internal hostnames
* secrets

in examples.

---

# 10. Canonical Public Catalog Examples

Create examples for:

### A. List products

```http
GET /api/v1/products
```

Demonstrate:

* public access
* pagination
* product summary representation
* allowed filtering
* allowed sorting if supported

### B. Product details

```http
GET /api/v1/products/{slug}
```

Use a product slug in the example where the contract defines public slugs.

Demonstrate:

* public product information
* product type
* pricing
* variants where applicable
* availability information

Do not expose Staff/Admin-only fields in the public example.

---

# 11. Catalog Type Example

Include examples that clearly distinguish:

```text
IN_STOCK
```

from:

```text
MADE_TO_ORDER
```

The example must demonstrate that a made-to-order product can be publicly discoverable without becoming a normal cart/checkout item.

Do not demonstrate made-to-order products being added to a normal purchase cart unless the contract has explicitly changed.

---

# 12. Customer Registration Example

Create a canonical example for public customer registration.

The example should show only fields that customers are actually permitted to submit.

Do not accept:

```text
role
permissions
account status
approval status
user ID
```

from the client.

The server must create the customer identity.

The response must not expose a password or other credentials.

If email verification is deferred to Group R, do not pretend that an actual email has already been delivered.

---

# 13. Customer Login Example

Create a canonical login example.

Demonstrate:

* accepted credentials
* successful authenticated response
* authenticated identity context
* safe response fields

Do not expose:

* password
* password hash
* authentication secrets beyond what the authentication contract permits

Use placeholder credentials only.

---

# 14. Authentication Failure Example

Create a canonical authentication failure.

Example category:

```text
401
```

Demonstrate the global error envelope.

Do not reveal whether a user account exists if doing so would violate the anti-enumeration policy.

The example message/code must follow the approved authentication contract.

---

# 15. `/me` Examples

Create canonical examples for:

```http
GET /api/v1/me
```

and:

```http
PATCH /api/v1/me
```

The examples should demonstrate that:

* identity is derived from authentication
* role is returned by the server where the contract permits it
* customer/staff/admin self-context shares one resource
* unauthorized profile fields cannot be changed

Do not demonstrate role mutation through `/me`.

---

# 16. Cart Examples

Create canonical customer examples for:

### Get cart

```http
GET /api/v1/me/cart
```

### Add item

```http
POST /api/v1/me/cart/items
```

### Update quantity

```http
PATCH /api/v1/me/cart/items/{item}
```

### Remove item

```http
DELETE /api/v1/me/cart/items/{item}
```

Use the exact paths from the endpoint inventory where they differ from these conceptual examples.

Demonstrate that:

* cart belongs to the authenticated customer
* quantity is validated
* duplicate product/variant behavior follows the contract
* made-to-order products are rejected by normal purchase Cart
* cart pricing is informational
* inventory is not reserved merely by cart presence

---

# 17. Cart Authorization Failure Example

Show that Customer A cannot read Customer B's cart.

Demonstrate the contract's approved authorization/enumeration behavior.

Do not expose Customer B's existence or private cart data.

---

# 18. Checkout Example — Pickup

Create a canonical authenticated customer checkout example for:

```text
fulfillment_type = PICKUP
```

The example must demonstrate:

* authenticated CUSTOMER requirement
* cart contents
* pickup selection
* delivery fee of zero
* server-authoritative subtotal
* server-authoritative total
* creation of an Order
* order reference format

Do not permit the customer to set the final subtotal or total.

---

# 19. Checkout Example — Delivery

Create a canonical authenticated customer checkout example for:

```text
fulfillment_type = DELIVERY
```

Demonstrate:

* required delivery address information
* delivery selection
* initial delivery-fee pending behavior where the approved contract uses it
* server-authoritative subtotal
* resulting order state
* payment boundary

Do not put a customer-controlled delivery fee in the request.

---

# 20. Checkout Idempotency Example

Create one example demonstrating how a retried checkout request is identified.

Show:

```http
Idempotency-Key: <CLIENT_GENERATED_KEY>
```

only if that is the approved global convention.

The example must demonstrate that retries do not silently create duplicate Orders.

Do not prescribe an implementation mechanism that contradicts the global API conventions.

---

# 21. Checkout Failure Examples

Include at least:

### Empty cart

```text
422
```

### Insufficient inventory / stale availability

Use the approved error classification from the contract.

### Unauthenticated checkout

```text
401
```

### Invalid fulfillment data

```text
422
```

Each example must use the canonical error envelope.

---

# 22. Order Retrieval Examples

Create examples for:

```http
GET /api/v1/me/orders
```

and:

```http
GET /api/v1/me/orders/{order}
```

Demonstrate:

* customer ownership
* order reference
* order items
* historical values
* fulfillment type
* delivery fee
* total
* current status
* relevant timestamps

Do not rely on the current Product record to explain historical order lines.

---

# 23. Order Cancellation Example

Create a canonical customer cancellation example.

Demonstrate:

```http
POST /api/v1/me/orders/{order}/cancel
```

or the exact approved route.

The example must reflect:

* own order only
* approved cancellable state
* 20-minute cancellation window
* server-authoritative time

Do not demonstrate generic:

```http
DELETE /api/v1/me/orders/{order}
```

as a cancellation mechanism.

---

# 24. Cancellation Failure Examples

Include examples for:

* cancellation after 20 minutes
* cancellation from a non-cancellable state
* another customer's order
* already cancelled order
* concurrent state change

Use the correct distinction between:

```text
403
404
409
422
```

as determined by the global contract.

---

# 25. Order Tracking Example

Create a canonical example for:

```http
GET /api/v1/me/orders/{order}/tracking
```

Demonstrate that tracking reflects authoritative order/fulfillment state.

Do not introduce live GPS behavior.

Do not let tracking become a second source of truth.

---

# 26. Staff Order Queue Example

Create a canonical Staff example for (canonical, not `/staff` prefix — see `docs/api/api-contract.md:§30.3`/`§31.8`):

```http
GET /api/v1/orders
```

Demonstrate:

* Staff authentication
* operational filters
* pagination
* operational order representation
* customer fulfillment information that Staff legitimately needs

Do not include unrelated customer account information.

---

# 27. Staff Order Detail Example

Create (canonical `GET /api/v1/orders/{order}` with `OPERATIONAL` authorization, not `/staff` prefix):

```http
GET /api/v1/orders/{order}
```

Demonstrate the operational representation.

It may include necessary:

* customer contact data
* fulfillment data
* delivery address snapshot
* order financial summary
* order status
* operational metadata

It must not include:

* password
* authentication secrets
* unrelated customer records
* unrelated private resources

---

# 28. Staff Order Acceptance Example

Create (canonical `POST /api/v1/orders/{order}/accept` `ORD-007`, not `/staff` prefix):

```http
POST /api/v1/orders/{order}/accept
```

The response must demonstrate the resulting authoritative order state.

Do not send:

```json
{
  "status": "ACCEPTED"
}
```

as a generic status update.

The endpoint itself represents the action.

---

# 29. Staff Processing Example

Create (canonical `POST /api/v1/orders/{order}/process` `ORD-008`, not `/staff/.../start-processing`):

```http
POST /api/v1/orders/{order}/process
```

Show the resulting state and any approved timestamps.

Do not allow arbitrary target-state input.

---

# 30. Pickup Fulfillment Example

Create canonical examples for the approved pickup workflow:

```text
ready for pickup
→ completion
```

Use the exact endpoint names approved in the contract.

Demonstrate that pickup orders cannot receive delivery-only operations.

---

# 31. Delivery Fulfillment Examples

Create examples for:

```text
ship
→ deliver
→ complete
```

Use only approved routes and states.

Demonstrate that:

* delivery orders can be shipped
* pickup orders cannot be shipped
* invalid state transitions produce the standard conflict/error behavior

---

# 32. Delivery Fee Example

Create a canonical Staff example for (canonical `POST /api/v1/orders/{order}/delivery-fee` `ORD-014`, not `/staff/.../set-delivery-fee`):

```http
POST /api/v1/orders/{order}/delivery-fee
```

Example conceptual request (wire shape, **not** bare number — money is `{amount:int minor units, currency:"TZS"}` per `§52`):

```json
{
  "delivery_fee": {
    "amount": 15000,
    "currency": "TZS"
  }
}
```

The response must show:

```text
subtotal
delivery_fee
total
```

where:

```text
total = subtotal + delivery_fee
```

The example must make clear that Staff/Admin supplies only the delivery fee.

Do not include a client-controlled total.

---

# 33. Delivery Fee Authorization Examples

Create examples demonstrating:

### Customer attempt

Rejected.

### Staff

Allowed when order state permits it.

### Admin

Allowed when order state permits it.

### Pickup order

Rejected as a business-rule violation.

### Financially immutable order

Rejected according to the approved business rule.

---

# 34. Made-to-Order Request Examples

Create examples for:

### Anonymous creation

```http
POST /api/v1/requests
```

or the exact approved route.

Demonstrate the approved anonymous fields.

### Authenticated customer creation

Demonstrate that ownership is derived from the authenticated user rather than supplied as a client field.

### Staff list

Demonstrate operational queue access.

### Staff detail

Demonstrate operational visibility with protected attachment handling.

Do not create an Order as part of the example.

---

# 35. Made-to-Order Attachment Example

Where attachments are supported, show the canonical representation without exposing an unrestricted public file URL.

Demonstrate:

* optional attachment
* protected access
* parent-resource authorization

Do not put real files, credentials, or sensitive data in the example.

---

# 36. General Enquiry Examples

Create examples for:

* anonymous enquiry submission
* authenticated enquiry submission
* Staff listing
* Staff detail
* permitted state update
* internal notes if those are part of the approved contract

Demonstrate that the original customer message remains immutable.

Do not overwrite customer-submitted content with Staff notes.

---

# 37. Notification Examples

Create canonical examples for:

```http
GET /api/v1/me/notifications
```

and the approved mark-read operation.

Demonstrate:

* recipient-scoped access
* notification type
* source reference where defined
* read state
* `read_at` behavior where defined

Do not let the notification response become a substitute for the authoritative Order state.

---

# 38. Notification Example After Order Acceptance

Show an example sequence:

```text
Staff accepts order
        ↓
Order state becomes ACCEPTED
        ↓
Business event occurs
        ↓
Customer notification is created
```

The examples must make clear that the Order remains authoritative.

Do not imply that the Staff client directly creates the customer notification as a side effect through arbitrary notification content.

---

# 39. Staff Notification Example

Create an operational notification example for something such as:

```text
NEW_ORDER
```

using the approved notification type.

Demonstrate that Staff notifications are recipient-scoped according to the approved operational model.

If Staff notifications are intentionally shared rather than individually assigned, represent that according to the approved contract rather than inventing personal ownership.

---

# 40. Admin Staff Approval Example

Create the canonical Admin-only example for:

```http
POST /api/v1/admin/staff/{user}/approve
```

Demonstrate:

* Admin authentication
* Admin authorization
* Staff lifecycle transition
* server-controlled approval metadata
* audit requirement

Do not allow the Staff target user to specify:

```text
approved_by
approved_at
role
permissions
```

---

# 41. Staff Authorization Failure Example

Create a canonical example where a STAFF actor tries to perform an Admin-only action.

Example category:

```text
403 FORBIDDEN
```

Use the standard error envelope.

Do not leak internal authorization implementation details.

---

# 42. Role Escalation Failure Example

Create a canonical example where a client attempts to send:

```json
{
  "role": "ADMIN"
}
```

through an inappropriate profile or user-management endpoint.

Demonstrate that the request cannot elevate the actor's role.

The example must show the correct validation/authorization behavior according to the approved contract.

---

# 43. Customer Account Boundary Example

Create a Staff-facing example showing that normal Staff operations do not grant unrestricted customer-account management.

Demonstrate that Staff can obtain the customer information needed for an order but cannot:

* change the customer's role
* reset the customer's password
* block the customer
* change ownership

Do not invent a customer administration endpoint solely to demonstrate its denial.

A documented authorization failure may be sufficient.

---

# 44. Inventory Examples

Create canonical examples for:

### Inventory read

```http
GET /api/v1/inventory/{inventory}
```

using the exact approved route (`INV-002` `GET /api/v1/inventory/{inventory}`, `INV-001` `GET /api/v1/inventory` for list — canonical, not `/staff/inventory/...` prefix; see `docs/api/api-contract.md:§30.5.1`).

### Inventory adjustment

Show the controlled adjustment request:

```json
{
  "quantity_delta": 10,
  "reason": "STOCK_RECEIPT"
}
```

Use only approved closed reason values.

The response must demonstrate the resulting authoritative inventory quantity.

---

# 45. Inventory Failure Examples

Include examples for:

* insufficient available stock where applicable
* invalid adjustment
* unauthorized actor
* stale/concurrent modification
* duplicate retry

Do not demonstrate arbitrary direct inventory quantity replacement if the contract prohibits it.

---

# 46. Error Example Matrix

Create a consolidated error-example matrix covering at least:

| Scenario                         | Expected status | Purpose                   |
| -------------------------------- | --------------: | ------------------------- |
| Missing authentication           |             401 | Authentication boundary   |
| Wrong role                       |             403 | Authorization boundary    |
| Resource unavailable/not exposed |             404 | Resource exposure         |
| Invalid state transition         |             409 | State conflict            |
| Concurrent write conflict        |             409 | Concurrency               |
| Validation/business rule failure |             422 | Input/business validation |
| Rate limit                       |             429 | Abuse protection          |
| Unexpected server error          |             500 | Internal failure          |

Use actual stable error codes from the approved contract.

Do not invent error codes in Phase 1.31 merely for example completeness.

If an error code is missing from the contract, return to the contract review and define it before continuing.

---

# 47. Example of an Authorization Error

Use the canonical structure (wire shape includes `meta.request_id` per `§15`; schematic without `meta` is not copy-pasteable):

```json
{
  "errors": [
    {
      "code": "FORBIDDEN",
      "message": "You are not authorized to perform this action."
    }
  ],
  "meta": {
    "request_id": "01H9-req-abc123"
  }
}
```

Only use this exact code if `FORBIDDEN` has been approved.

Otherwise use the project's defined machine-readable code.

The example must never expose:

* policy class names
* database queries
* internal IDs unrelated to the resource
* permission implementation details

---

# 48. Example of a Business-State Conflict

Create one example for an invalid order transition.

Conceptually (schematic — wire shape always includes `meta.request_id`):

```json
{
  "errors": [
    {
      "code": "ORDER_STATE_CONFLICT",
      "message": "The order cannot be moved to this state from its current state."
    }
  ],
  "meta": {
    "request_id": "01H9-req-abc123"
  }
}
```

Again, use the actual approved code.

The purpose is to demonstrate the difference between:

```text
authorization failure
```

and:

```text
authorized action + invalid current business state
```

---

# 49. Pagination Examples

Create one canonical collection example showing:

* items
* page metadata
* total/has-more fields if those are part of the global contract
* consistent query parameters

Use the same structure across:

* products
* orders
* requests
* enquiries
* notifications
* inventory
* Staff lists

Do not create separate pagination formats for different domains.

---

# 50. Filtering Examples

Provide one or two examples of supported filters.

Demonstrate the exact query parameter syntax already approved.

Do not introduce arbitrary filter-by-any-column behavior.

For Staff order operations, show only filters explicitly defined in the contract.

---

# 51. Sorting Examples

Where sorting is supported, show the canonical syntax.

The example must make clear that clients may only sort on approved fields.

Do not expose arbitrary SQL-like sort expressions.

---

# 52. Money Examples

Use one consistent representation for money throughout all examples.

For example, if the project has approved integer minor units, consistently demonstrate:

```json
{
  "amount": 15000,
  "currency": "TZS"
}
```

If the approved contract uses a different representation, use that instead.

Do not mix:

```text
15000
"15000"
15000.00
```

for the same money concept.

Do not use floating-point arithmetic in examples.

---

# 53. Date/Time Examples

Use the one canonical API date/time representation everywhere.

Use a fixed example timestamp rather than the current date.

For example, if ISO 8601 UTC is approved:

```text
2026-01-15T09:30:00Z
```

Do not mix:

```text
2026-01-15 09:30
15/01/2026
Jan 15, 2026
```

for API values.

---

# 54. Enum Examples

Every example must use the approved closed enum values exactly.

Examples include:

```text
CUSTOMER
STAFF
ADMIN
```

```text
PICKUP
DELIVERY
```

```text
IN_STOCK
MADE_TO_ORDER
```

and the approved Order/Notification/Request/Enquiry/Inventory values.

Do not use fictional enum values just to make examples look varied.

---

# 55. Example Sequence: Complete Purchase Lifecycle

Create one end-to-end canonical scenario that demonstrates:

```text
1. Browse catalog
2. Read product
3. Authenticate/register
4. Add product to cart
5. Checkout
6. Obtain Order
7. Staff accepts order → Customer receives ORDER_ACCEPTED notification (business event → downstream notification, Order remains authoritative)
8. Staff starts processing → Customer receives ORDER_PROCESSING notification
9. Staff fulfills pickup (→ ORDER_READY_FOR_PICKUP) OR ships delivery (→ ORDER_SHIPPED → ORDER_DELIVERED)
10. Customer tracks order (reflects authoritative order/fulfillment state, not notification)
11. Order completes (→ ORDER_COMPLETED)
12. Summary: Customer notifications received throughout lifecycle at their triggering transitions (steps 7-9, 11), not only after completion
```

For delivery purchases, include the delivery-fee step at the correct point.

This sequence becomes the primary integration example for future developers.

---

# 56. Example Sequence: Delivery Purchase

Create one complete delivery-specific lifecycle:

```text
Customer selects DELIVERY
        ↓
Checkout
        ↓
Order created with fee pending if applicable
        ↓
Staff assigns delivery fee
        ↓
Authoritative total established
        ↓
Group H payment boundary
        ↓
Order accepted
        ↓
Processing
        ↓
Shipped
        ↓
Delivered
        ↓
Completed
```

Examples must not implement payment itself.

The payment step must be represented only as the handoff boundary established by Group A.

---

# 57. Example Sequence: Pickup Purchase

Create one complete pickup lifecycle:

```text
Customer selects PICKUP
        ↓
Checkout
        ↓
delivery_fee = 0
        ↓
Order
        ↓
Staff accepts
        ↓
Processing
        ↓
Ready for pickup
        ↓
Completed
```

Do not insert delivery actions.

---

# 58. Example Sequence: Made-to-Order Request

Create one complete request lifecycle:

```text
Customer/anonymous visitor
        ↓
Submit request
        ↓
Staff receives operational notification
        ↓
Staff reviews request
        ↓
Staff performs approved request workflow
```

Do not automatically create:

```text
Order
Payment
Inventory reservation
```

---

# 59. Example Sequence: Enquiry

Create one complete enquiry lifecycle:

```text
Anonymous/authenticated visitor
        ↓
Submit enquiry
        ↓
Staff receives operational notification
        ↓
Staff reviews enquiry
        ↓
Staff updates approved enquiry state
```

Keep the original customer message unchanged.

---

# 60. Example Sequence: Staff Approval

Create one complete Staff lifecycle example:

```text
Admin
   ↓
Create/invite Staff through approved mechanism
   ↓
Staff pending approval
   ↓
Admin approves
   ↓
Staff becomes operationally authorized
```

Do not assume an email invitation has been delivered because real email is deferred to Group R.

---

# 61. Example Naming and Formatting Rules

Every canonical example should have:

```text
Example title
Endpoint ID
HTTP method
Path
Actor
Purpose
Request
Response
Relevant notes
```

Where appropriate also include:

```text
Expected status
Headers
Error cases
Authorization requirements
```

Use the stable endpoint ID from Phase 1.19.

Do not assign a new endpoint ID only for the example.

---

# 62. Example Headers

Use only headers actually defined by the API conventions.

Potential examples:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: <CLIENT_GENERATED_KEY>
```

Do not invent custom headers for individual examples unless they are formally part of the contract.

---

# 63. Example Request Bodies

Keep request bodies minimal — request bodies contain **only allowed client fields** per the endpoint’s allow-list (e.g., `name`, `quantity`, `delivery_address` where permitted).

Do not include server-generated values such as:

```text
id
created_at
updated_at
role
permissions
status
subtotal
total
approved_by
approved_at
```

in request bodies. Server-generated fields belong **only** in response examples, where the server demonstrates authority (e.g., `id`, `status`, `total` in `GET /me/orders` or `POST /checkout` responses).

---

# 64. Example Response Bodies

Response examples should demonstrate server authority clearly.

For example, an Order response should show:

```text
order reference
status
fulfillment type
subtotal
delivery fee
total
historical order items
```

where those fields are part of the approved response.

Do not echo client input as proof that the server accepted it.

---

# 65. Security Redaction Rules

All examples must be safe for public documentation.

Never include:

* real passwords
* API keys
* JWTs
* payment card details
* private customer contact information unless fictional
* real delivery addresses
* real uploaded documents
* secret tokens
* internal infrastructure details

Use fictional values and explicit placeholders.

---

# 66. Canonical Example Storage

Keep examples consolidated.

Preferred location:

```text id="y8q58i"
docs/api/api-contract.md
```

or a dedicated examples section within the existing API documentation strategy.

If the examples become too large for `api-contract.md`, create one consolidated:

```text
docs/api/api-examples.md
```

Do not create one Markdown file per endpoint.

Do not create:

```text
docs/api/examples/catalog.md
docs/api/examples/cart.md
docs/api/examples/orders.md
...
```

unless a later documentation-scale review proves that this is necessary.

---

# 67. OpenAPI Preparation

Write examples in a form that can later be migrated into:

```text
docs/api/openapi.yaml
```

Do not start authoring the OpenAPI file in this phase.

Examples should make future OpenAPI example fields obvious:

```text
request example
response example
error example
```

The formal schema remains Phase 1.32.

---

# 68. Canonical Example Quality Checks

For every example verify:

### Endpoint

Matches the endpoint inventory exactly.

### Actor

Matches authorization requirements.

### Request

Contains only allowed client fields.

### Response

Contains only contract-defined fields.

### State

Represents a valid state transition.

### Financial values

Are server-authoritative.

### Enum values

Are closed and approved.

### Error

Uses the standard envelope and approved error code.

### Security

Does not disclose secrets.

### Cross-domain behavior

Matches the state and side effects defined elsewhere.

---

# 69. Example Consistency Checks

Perform automated or systematic search for:

* different example field names for the same concept
* different endpoint paths
* different order references
* different fulfillment enum values
* different order states
* different money formats
* different timestamp formats
* inconsistent error structures
* obsolete delivery-fee examples
* customer-supplied totals
* customer-supplied roles
* generic order status PATCH examples
* generic Staff/Admin user PATCH examples

Every inconsistency must be corrected before completion.

---

# 70. Required Canonical Example Set

The final example set must include at least:

```text
1. Public product list
2. Public product detail
3. Customer registration
4. Customer login
5. Authentication failure
6. GET /me
7. PATCH /me
8. Get cart
9. Add cart item
10. Checkout pickup
11. Checkout delivery
12. Checkout idempotency/retry
13. Order list
14. Order detail
15. Customer cancellation
16. Order tracking
17. Staff order list
18. Staff order detail
19. Staff accept order
20. Staff start processing
21. Pickup fulfillment
22. Delivery fulfillment
23. Staff set delivery fee
24. Delivery-fee authorization failure
25. Staff inventory adjustment
26. Made-to-order request
27. Staff request processing
28. General enquiry
29. Staff enquiry processing
30. Customer notifications
31. Staff notifications
32. Admin Staff approval
33. Staff-to-Admin authorization failure
34. Role escalation rejection
35. Canonical 401 error
36. Canonical 403 error
37. Canonical 404 error
38. Canonical 409 error
39. Canonical 422 error
40. Canonical 429 error
41. Canonical 500 error
42. Complete pickup lifecycle
43. Complete delivery lifecycle
44. Complete made-to-order request lifecycle
45. Complete enquiry lifecycle
```

Do not implement all 45 as giant examples.

Where a sequence already demonstrates an endpoint sufficiently, reference the canonical example rather than duplicating the payload.

---

# 71. Definition of Done

Phase 1.31 is complete only when:

* [ ] Canonical example data has been defined.
* [ ] All examples use the approved API version.
* [ ] All examples use the canonical endpoint paths.
* [ ] Every example is associated with the correct endpoint ID.
* [ ] Authentication examples follow Phase 1.17.
* [ ] Authorization examples follow Phase 1.18.
* [ ] Public catalog examples follow Phase 1.20.
* [ ] Cart examples follow Phase 1.21.
* [ ] Checkout examples follow Phase 1.22.
* [ ] Order examples follow Phase 1.23.
* [ ] Fulfillment examples follow Phase 1.24.
* [ ] Made-to-order examples follow Phase 1.25.
* [ ] Enquiry examples follow Phase 1.26.
* [ ] Notification examples follow Phase 1.27.
* [ ] User/profile examples follow Phase 1.28.
* [ ] Staff/Admin examples follow Phase 1.29.
* [ ] Cross-domain examples follow Phase 1.30.
* [ ] All error examples use the canonical error envelope.
* [ ] All money examples use one approved representation.
* [ ] All date/time examples use one approved format.
* [ ] All Version 1 enums are closed and consistent.
* [ ] Delivery-fee examples contain no customer-controlled delivery fee.
* [ ] Delivery-fee examples show server-authoritative totals.
* [ ] No payment gateway implementation is represented as Group A behavior.
* [ ] No generic order-state PATCH example exists.
* [ ] No role-escalation example succeeds.
* [ ] No example exposes secrets or real sensitive data.
* [ ] End-to-end lifecycle examples are internally consistent.
* [ ] Documentation contains one canonical set of examples.
* [ ] Examples are ready to be referenced by OpenAPI in Phase 1.32.
* [ ] No backend/frontend implementation has started.

---

# 72. STOP Condition

**STOP after the canonical API examples are complete, internally consistent, and stored in the consolidated API documentation.**

Do not begin:

* `docs/api/openapi.yaml`
* Laravel controllers
* Laravel Form Requests
* Laravel policies
* database migrations
* Eloquent models
* frontend API clients
* Flutter API clients
* payment integration
* notification delivery implementation

Phase 1.31 produces **examples only**.

The next phase consumes these examples and the stabilized contract to create the formal machine-readable API specification.

**Next phase: 1.32 — OpenAPI Contract Specification.**
