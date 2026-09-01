# Phase 1.25 — Define Made-to-Order Request API Contract

## 1. Purpose

Phase 1.25 defines the **complete Version 1 API contract for Made-to-Order Furniture Requests**.
+
This is a separate commerce path from normal purchasing.

The approved business model is:

```text
Public Product
      ↓
Product Type = MADE_TO_ORDER
      ↓
Customer chooses "Request"
      ↓
Request submitted
      ↓
Staff receives and handles request
      ↓
Customer communicates/continues through the agreed process
```

A request may be submitted by:

```text
ANONYMOUS visitor
or
AUTHENTICATED CUSTOMER
```

The request does **not** become an Order merely because it is submitted.

The request does **not** enter the normal:

```text
Cart
→ Checkout
→ Payment
```

workflow.

The request is an inquiry/intake mechanism for furniture that the business may be able to produce upon request.

---

# 2. Documentation Strategy

Continue the consolidated-document model.

Update:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not create:

```text
made-to-order-api.md
furniture-request-api.md
request-contract.md
request-decisions.md
```

as permanent project documents.

The API contract remains centralized.

---

# 3. Authoritative Project Paths

Use:

```text
AGENTS.md
docs/VISION.md
```

These remain authoritative.

---

# 4. Payment Boundary

There is **no normal payment operation in this Made-to-Order Request contract**.

Payment remains assigned to:

**Phase Group H**

A later business workflow may potentially turn an accepted request into an Order/payment process, but that is not automatically part of this API.

Do not create payment endpoints here.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

If the Request resource has a status enum, only approved Version 1 values may be used.

Do not invent:

```text
QUOTED
APPROVED
REJECTED
PRODUCING
```

unless they have been formally approved as part of the Request domain.

If the current business model does not require a public Request status, do not add one unnecessarily.

---

# 6. Dependency Position

Current sequence:

```text
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 Order Tracking + Fulfillment API Contract
       ↓
1.25 Made-to-Order Request API Contract
       ↓
1.26 Enquiry API Contract
       ↓
...
```

This phase consumes the Catalog contract, especially:

```text
Product
Product Type
Product identity
Product public representation
```

---

# 7. Authoritative Inputs

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
Phase 1.20 Catalog
Phase 1.22 Checkout
Phase 1.23 Order
Phase 1.15 Validation
Phase 1.16 Error Contract
Phase 1.17 Authentication
Phase 1.18 Authorization
```

---

# 8. Core Request Principle

A Made-to-Order Request represents:

> **A customer's/visitor's request for furniture that is not intended to be purchased through the normal in-stock checkout flow.**

The request may describe:

```text
what furniture
quantity
dimensions
material
color
customization
notes
contact information
attachment
```

The request is **not** a guarantee that the business can produce the furniture.

---

# 9. Request ≠ Order

Explicitly distinguish:

```text
Request
→ customer/business discussion

Order
→ accepted transaction
```

Submitting a Request does not:

* reserve inventory;
* create an Order;
* charge payment;
* guarantee price;
* guarantee production;
* guarantee delivery;
* guarantee completion.

---

# 10. Request ≠ Quote

Do not automatically treat the submitted Request as:

```text
quote
```

The business may later discuss:

```text
price
availability
production
delivery
```

but these are not authoritative simply because they are mentioned in the request.

---

# 11. Request Actor Model

The request supports:

```text
ANONYMOUS
CUSTOMER
STAFF
ADMIN
```

with different responsibilities.

### Anonymous

May create a request.

### Customer

May create a request and, where supported, view their own requests.

### Staff

Receives and handles requests operationally.

### Admin

Has administrative oversight.

---

# 12. Anonymous Submission

Anonymous submission is explicitly approved.

Therefore:

```text
POST /api/v1/requests
```

must not require authentication.

The request must instead contain the required contact information.

---

# 13. Authenticated Submission

An authenticated customer may submit the same request.

When authenticated:

```text
Request.user_id = authenticated user
```

must be derived server-side.

Do not ask the customer to submit their own `user_id`.

---

# 14. Anonymous Request Identity

For anonymous requests:

```text
user_id = null
```

is acceptable.

The request must still contain enough contact data for Staff to respond.

---

# 15. Customer Contact Information

For anonymous requests, define required contact information.

Potential:

```text
name
phone
email
```

Use the actual business-required fields.

For authenticated Customers, decide whether the API:

```text
requires contact fields every time
```

or:

```text
may use trusted account contact data
```

Record the final policy.

Do not silently assume either behavior.

---

# 16. Recommended Contact Strategy

For simplicity and reliable Staff handling:

> Keep explicit contact information in the Request submission when required for the communication workflow, even when the requester is authenticated.

This creates a self-contained request record.

However, if the request is associated with an authenticated Customer, current profile data may also be available as a secondary source.

The historical request contact data should not depend entirely on future profile changes.

---

# 17. Request Identity

Every Request must have a server-generated identity.

The API should expose:

```text id
```

and may also have a human-friendly Request reference if the business needs one.

Do not invent a reference format unless one is already approved.

---

# 18. Request Ownership

Authenticated Requests belong to the Customer who created them.

```text
Customer
   ↓ owns
Request
```

Anonymous Requests have:

```text
no authenticated owner
```

and require a separate secure access strategy if the system later allows anonymous retrieval.

---

# 19. Request Retrieval Security

Do **not** make:

```text
GET /requests/{id}
```

public merely because the Request can be anonymously created.

Predictable IDs must not become access credentials.

For anonymous retrieval, later design would need something like:

```text
secure access token
one-time link
verified contact mechanism
```

If Version 1 does not require anonymous request retrieval:

> Do not create an anonymous retrieval endpoint.

---

# 20. Customer Request Retrieval

Authenticated Customers may retrieve only their own Requests.

Conceptually:

```text
GET /api/v1/me/requests
GET /api/v1/me/requests/{request}
```

Use the approved self-context naming convention from Phase 1.19 where applicable.

---

# 21. Staff Request Retrieval

Staff need operational access to Requests in order to handle them.

Staff access is not ownership.

```text
Staff
→ operational access
```

not:

```text
Staff
→ owns Request
```

---

# 22. Admin Request Retrieval

Admin has broader administrative access.

However, Admin access still follows:

```text
authorization
+
data minimization
+
auditability
```

---

# 23. Request Creation Endpoint

Define:

```text
REQ-001

POST /api/v1/requests
```

Authentication:

```text
Optional
```

Authorization:

```text
Public request creation
```

Purpose:

> Submit a Made-to-Order furniture request.

---

# 24. Request Creation Input

Potential conceptual input:

```json
{
  "product_id": "...",
  "quantity": 1,
  "name": "...",
  "phone": "...",
  "email": "...",
  "dimensions": {},
  "material": "...",
  "color": "...",
  "notes": "..."
}
```

Attachment handling is addressed separately.

Only include fields actually approved by the project's logical model.

---

# 25. Product Reference

If the Request originates from a specific Catalog Product, allow:

```text
product_id
```

or the approved public product identifier.

The backend must verify:

```text
product exists
+
product is publicly requestable
+
product_type = MADE_TO_ORDER
```

where the Request is tied to a catalog product.

---

# 26. Request Without Existing Product

Determine whether the business wants to support:

```text
"Can you make something like this?"
```

without selecting an existing Product.

Because the approved requirement allows a user to request furniture without normal purchase, this is worth explicitly evaluating.

If supported:

```text
product_id = null
```

may be valid.

The request then depends on:

```text
description
dimensions
material
color
attachment
contact information
```

Do not make `product_id` mandatory unless the business explicitly requires all requests to originate from a catalog product.

---

# 27. Recommended Product Reference Policy

Support both:

```text
existing MADE_TO_ORDER product
```

and:

```text
custom/general furniture request
```

only if that matches the intended business workflow.

If the business wants requests to be strictly based on listed Made-to-Order products, enforce:

```text
product_id required
```

Do not implement the broader option merely because it is possible.

Record the actual decision.

---

# 28. Request Product Type Validation

If a Product is supplied:

```text
product_type = MADE_TO_ORDER
```

must be true.

An `IN_STOCK` Product should not be submitted through the Made-to-Order Request workflow unless the business explicitly supports customization requests for in-stock products.

---

# 29. Request Quantity

If quantity is supported:

```text
integer
positive
reasonable maximum
```

Do not allow:

```text
0
-1
1.5
```

---

# 30. Quantity Is Not Order Quantity

The requested quantity is only part of the customer's request.

It does not establish:

```text
final order quantity
inventory allocation
production commitment
```

---

# 31. Custom Specifications

The request may contain optional specifications such as:

```text
dimensions
material
color
finish
design notes
customization details
```

These should be structured where there is a stable known vocabulary.

Use free text where the business needs flexible human descriptions.

Do not create a huge furniture configuration DSL in Version 1.

---

# 32. Dimensions

Evaluate whether dimensions should be:

```text
structured
```

rather than free text.

Potential conceptual:

```json
{
  "dimensions": {
    "length": 200,
    "width": 90,
    "height": 75,
    "unit": "cm"
  }
}
```

If structured dimensions are supported:

* define units;
* numeric constraints;
* allowed dimensions;
* nullability.

Do not accept arbitrary dimension object keys.

---

# 33. Unit Representation

If dimensions use units, define one canonical representation.

Example:

```text
cm
```

Do not allow:

```text
cm
centimeter
centimetres
in
inch
```

as arbitrary client strings unless the API explicitly supports them.

If multi-unit requests are unnecessary, prefer one unit.

---

# 34. Material

Material can be:

```text
controlled enum
```

or:

```text
free text
```

depending on the business model.

Do not create a closed material enum without a confirmed business vocabulary.

Because Version 1 enums are closed, an incomplete material enum can unnecessarily restrict customers.

For a small furniture business, free text may be more flexible.

---

# 35. Color

Likewise, color may be:

```text
free text
```

unless a fixed catalog of colors already exists.

Do not introduce an elaborate color-management system in Version 1.

---

# 36. Notes

Free-text notes may be necessary for custom furniture requirements.

Define:

```text
maximum length
```

and safe handling.

Do not execute/interpret submitted content as code.

---

# 37. Request Attachment

Attachments are explicitly approved as:

> **Optional.**

The request contract must support an optional attachment without requiring every Request to have one.

---

# 38. Attachment Purpose

An attachment may show:

```text
reference furniture
design inspiration
sketch
room context
material/color reference
```

The exact business purpose should remain flexible.

---

# 39. Attachment Transport

Evaluate:

```text
multipart/form-data
```

versus:

```text
pre-upload then attach file
```

For Version 1, choose the simpler approach that is appropriate for Laravel/Next.js/Flutter.

Do not choose a storage provider here.

---

# 40. Attachment Security

The API must validate:

```text
file size
allowed type
actual content
filename
storage authorization
```

Do not trust client-provided MIME type or file extension alone.

---

# 41. Attachment Access

Attachments inherit Request authorization.

Therefore:

```text
Customer
→ own request attachment

Staff
→ operational request attachment

Admin
→ authorized administrative access

Anonymous
→ no automatic public retrieval
```

---

# 42. Attachment URLs

Do not expose permanent public storage URLs for private Request attachments.

Later implementation may use protected or temporary access URLs.

---

# 43. Request Status

Decide whether Version 1 needs a status.

Potential conceptual statuses could include:

```text
SUBMITTED
IN_REVIEW
CONTACTED
CLOSED
```

But do **not** automatically create these if the current business process has not formally approved them.

If the business only requires:

```text
submitted
then Staff handles it externally
```

a minimal status model may be sufficient.

---

# 44. Closed Status Rule

If a status enum is introduced:

> It is CLOSED for Version 1.

Do not let Staff create arbitrary states.

---

# 45. Staff Request Operations

Staff need to receive and handle Requests.

Potential operations (aligned with canonical matrix §104):

```text
REQ-004
GET requests (operational list)

REQ-005
GET request detail (operational detail)

REQ-006
update operational request information / change request status if approved
```

Do not define every possible CRM workflow.

---

# 46. Staff Request Handling

Staff should be able to:

```text
view request
review specifications
view contact details
view attachment
communicate with customer
update operational state where supported
```

This should feel like normal customer-service operations.

---

# 47. Staff Cannot Convert Request Automatically to Order

Staff handling a Request does not mean:

```text
Request
→ automatically Order
```

A future accepted quotation/production workflow would need an explicit business contract.

Do not create hidden Order creation through Request updates.

---

# 48. Request-to-Order Future Boundary

Record the conceptual possibility:

```text
Request
    ↓
business discussion
    ↓
customer agreement
    ↓
future Order workflow
```

but leave it outside Version 1 Request API unless explicitly required.

---

# 49. Request Pricing

Do not require a final price during Request submission.

The customer is asking:

> Can this be made?

The request itself is not a confirmed priced transaction.

---

# 50. Request Delivery Fee

Do not collect delivery fee during Request creation.

Delivery pricing belongs to an eventual Order/fulfillment workflow if the Request becomes an Order.

---

# 51. Request Payment

No payment is performed by Request submission.

Payment belongs to the later Order/Payment workflow.

---

# 52. Request Inventory

Request submission does not reserve inventory.

A Made-to-Order Request is not an inventory purchase.

---

# 53. Request Cancellation

Evaluate whether customers need to cancel Requests in Version 1.

Do not automatically copy the Order's 20-minute cancellation rule.

That rule applies to **Orders**, not Requests.

If Request cancellation is not required:

> Do not create a customer cancellation endpoint.

---

# 54. Request Modification

Evaluate whether Customers may edit a submitted Request.

For simplicity, consider:

> Submitted Requests are not freely editable by Customers.

If changes are needed, communication with Staff can handle them.

Do not create a broad PATCH endpoint that allows customers to rewrite the request after Staff has started processing it unless the business actually needs this.

---

# 55. Staff Modification

Staff may need to update operational fields.

Separate:

```text
customer-provided request data
```

from:

```text
internal operational data
```

Do not overwrite the customer's original submission without preserving the historical meaning.

---

# 56. Request History

If Staff can change Request status/details, consider preserving a basic operational history.

Do not build a full CRM audit system here.

At minimum, avoid making it impossible to determine what the customer originally submitted.

---

# 57. Customer Original Submission

The original request should remain reconstructable.

For example:

```text
customer said:
"Need a 2.2m walnut dining table."
```

Staff edits should not silently destroy that original context.

---

# 58. Request Internal Notes

If operational notes are needed:

```text
staff_internal_notes
```

must be separated from:

```text
customer_notes
```

Internal notes must not be exposed to anonymous/customers.

---

# 59. Request Contact Data Privacy

Request contact information is private.

Public Catalog endpoints must never expose Request contact data.

---

# 60. Request Search

Staff may need:

```text
search
```

for:

```text
name
email
phone
request reference
product
```

Use the global query conventions.

Do not allow arbitrary database-column search.

---

# 61. Request Pagination

The Staff Request collection should use global pagination if it can grow beyond a manageable size.

Customer-owned Requests may also use pagination if the customer can accumulate many over time.

Do not create a Request-specific pagination format.

---

# 62. Request Filtering

Potential Staff filters:

```text
status
date range
product
customer
```

Only add approved filters.

---

# 63. Request Sorting

Recommended default for Staff:

```text
newest first
```

unless the workflow needs priority ordering.

Do not make database natural order authoritative.

---

# 64. Customer Request Collection

If customer Request history is supported:

```text
GET /api/v1/me/requests
```

must return only that customer's authenticated Requests.

Anonymous requests do not appear in customer account history unless a secure claim/link mechanism is introduced.

---

# 65. Anonymous Request Retrieval

Recommended Version 1:

> **Do not support anonymous request retrieval unless there is a demonstrated business requirement.**

This avoids designing a weak "request ID as password" system.

---

# 66. Request Public Exposure

Never make Request collections publicly searchable.

Do not allow:

```text
GET /requests
```

for anonymous callers.

Only public creation is allowed.

---

# 67. Request Endpoint Inventory

A likely Version 1 set:

```text
REQ-001
POST /api/v1/requests

REQ-002
GET /api/v1/me/requests

REQ-003
GET /api/v1/me/requests/{request}

REQ-004
GET /api/v1/requests

REQ-005
GET /api/v1/requests/{request}
```

plus optional operational update/attachment endpoints.

The exact paths must follow Phase 1.19 conventions.

---

# 68. Avoid Duplicate Request Resources

Do not create:

```text
/requests
/made-to-order-requests
/furniture-requests
/custom-furniture
```

for the same business concept.

Choose one canonical resource.

Recommended:

```text
/requests
```

because the business concept has already been established as a Request.

---

# 69. Customer vs Staff Representations

Use:

```text
Customer Request Representation
```

for customer-owned access.

Use:

```text
Staff Request Representation
```

for operational access.

Do not expose internal staff fields to customers.

---

# 70. Request Response

Use the global response envelope:

```json
{
  "data": {}
}
```

For collections:

```json
{
  "data": [],
  "meta": {}
}
```

For errors:

```json
{
  "errors": []
}
```

---

# 71. Request Response Example

Conceptual:

```json
{
  "data": {
    "id": "...",
    "product_id": "...",
    "quantity": 1,
    "name": "...",
    "phone": "...",
    "email": "...",
    "dimensions": {},
    "material": "...",
    "color": "...",
    "notes": "...",
    "attachments": []
  }
}
```

Do not finalize fields not already approved.

---

# 72. Request Status in Response

If a status exists, use the canonical closed enum.

Do not expose arbitrary Staff-entered status text as the authoritative state.

---

# 73. Request Error Contract

Use the common Phase 1.16 error envelope.

Potential errors:

```text
VALIDATION_ERROR
MISSING_REQUIRED_FIELD
INVALID_VALUE
INVALID_PRODUCT
PRODUCT_NOT_REQUESTABLE
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
REQUEST_NOT_FOUND
FORBIDDEN
```

Only keep codes with actual utility.

---

# 74. Product Not Requestable

If a product is:

```text
IN_STOCK
```

and the Request policy says only Made-to-Order products are requestable:

```text
PRODUCT_NOT_REQUESTABLE
```

should be returned.

Do not let a client bypass Product Type rules.

---

# 75. Request Security

Anonymous submission is inherently more abuse-prone.

Later implementation should consider:

```text
rate limiting
spam protection
request size limits
attachment limits
IP/device abuse controls
```

Do not implement these yet.

---

# 76. CAPTCHA / Challenge

Evaluate whether anonymous Request submission requires a challenge mechanism.

Do not automatically add CAPTCHA if normal rate limiting/abuse controls are sufficient.

Record it as a later security decision if needed.

---

# 77. Anonymous Request Abuse

The endpoint must not become an unrestricted spam channel.

Potential protections later:

```text
rate limit
per-IP throttling
request limits
attachment restrictions
content controls
```

---

# 78. Request Authentication Transition

An anonymous visitor may later register or login.

Do not automatically attach old anonymous Requests to the account merely because the email matches.

That could allow account takeover/data exposure.

Any anonymous-to-authenticated claim mechanism must be explicitly designed.

---

# 79. Contact Matching Is Not Ownership Proof

Do not use:

```text
email = customer's email
```

as sufficient authorization for anonymous Request retrieval.

Email is contact information, not authentication.

---

# 80. Request Attachments and Anonymous Access

An anonymous upload must not become publicly retrievable through a predictable attachment URL.

This is a high-priority security requirement.

---

# 81. Request File Metadata

Do not expose internal storage keys.

Expose only safe metadata such as:

```text
filename/display name
content type
size
safe access URL if authorized
```

as appropriate.

---

# 82. Request Data Retention

Do not decide long-term retention/deletion policy unless already approved.

But note that Requests may contain:

```text
contact information
design information
attachments
```

and therefore require controlled retention.

---

# 83. Request Privacy

Request data is not public catalog data.

This means:

```text
Request → PRIVATE
Catalog → PUBLIC
```

even when Product is public.

---

# 84. Request Auditability

Important Staff actions should eventually be auditable:

```text
status change
assignment
internal note
attachment handling
administrative correction
```

Do not implement the audit system here.

---

# 85. Request and Staff Assignment

Evaluate whether Requests need:

```text
assigned_staff_id
```

in Version 1.

For a small business, this may be unnecessary if all Staff can see/manage the Request queue.

Do not add assignment complexity without need.

---

# 86. Request Priority

Do not add:

```text
priority
```

unless the business actually needs formal prioritization.

A small business may simply process Requests in a normal queue.

---

# 87. Request SLA

Do not invent customer-service deadlines.

The API should not promise:

```text
response within 24 hours
```

unless the business has formally approved it.

---

# 88. Request and Notifications

A new Request may trigger an operational Staff notification.

That belongs to the later Notification contract.

The Request endpoint itself must not depend on notification delivery.

---

# 89. Request and Email

Real email delivery remains deferred to:

**Phase Group R**.

The Request API must function without email delivery.

---

# 90. Request and Order

A Request must remain distinguishable from an Order.

Do not return:

```text
order_id
```

unless an actual Order has been created through a separately approved workflow.

---

# 91. Request and Quote

Do not expose:

```text
quoted_price
```

as though the Request submission itself produced a quote.

Any later quotation mechanism needs a separate contract.

---

# 92. Request and Payment

Do not accept:

```text
payment_status
payment_id
payment_amount
```

from the Request creator.

Payment belongs to the later commerce transaction.

---

# 93. Request Input Security

Customer/anonymous input must not be used as:

```text
HTML
SQL
code
file path
query syntax
```

without appropriate server-side handling.

---

# 94. Request Field Classification

Create a matrix:

| Field          | Anonymous | Customer |       Staff |       Server |
| -------------- | --------: | -------: | ----------: | -----------: |
| product_id     |     Input |    Input |        Read |     Validate |
| quantity       |     Input |    Input |        Read |     Validate |
| name           |     Input |    Input |        Read |        Store |
| phone          |     Input |    Input |        Read |        Store |
| email          |     Input |    Input |        Read |        Store |
| dimensions     |     Input |    Input |        Read |     Validate |
| material       |     Input |    Input |        Read |        Store |
| color          |     Input |    Input |        Read |        Store |
| notes          |     Input |    Input |        Read |        Store |
| user_id        |        No |       No |          No |       Derive |
| status         |        No |       No |  Controlled |       Server |
| created_at     |        No |       No |          No |       Server |
| internal_notes |        No |       No | Staff/Admin |  Staff/Admin |
| order_id       |        No |       No |         No* | Server later |

`*` only through a separately approved Request-to-Order process.

---

# 95. Request Authorization Matrix

Add to:

```text
docs/api/api-contract.md
```

| Operation                 | Anonymous |   Customer |                 Staff |                 Admin |
| ------------------------- | --------: | ---------: | --------------------: | --------------------: |
| Create Request (`REQ-001`)|       Yes |        Yes |     No — operational-only (`REQ-004/005/006` handling, not `REQ-001` creation) |     No — operational-only |
| List own Requests         |        No |        Yes |                    No |            Authorized |
| View own Request          |        No |        Yes |                    No |            Authorized |
| List operational Requests |        No |         No |                   Yes |                   Yes |
| View operational Request  |        No |         No |                   Yes |                   Yes |
| Modify operational fields |        No | No/limited |                   Yes |                   Yes |
| Manage customer account   |        No |   Own only |                    No |       Authorized only |

---

# 96. Recommended Customer Permissions

Customer:

```text
request.create
request.read_own
```

Do not expose:

```text
request.manage_all
request.assign
request.change_internal_status
```

---

# 97. Recommended Staff Permissions

Staff:

```text
requests.view_operational
requests.manage_operational
requests.view_attachments
```

Exact permission names must follow the authorization vocabulary established in Phase 1.18.

---

# 98. Recommended Admin Permissions

Admin:

```text
requests.view
requests.manage
requests.administer
```

Again, use the existing permission vocabulary rather than inventing duplicate names.

---

# 99. Request State Changes

If a Request status is introduced, status changes should use controlled actions rather than unrestricted PATCH.

For example:

```text
POST /requests/{request}/close
```

rather than:

```text
PATCH /requests/{request}
{
  "status": "CLOSED"
}
```

if that action has meaningful business semantics.

Do not implement until the status model is approved.

---

# 100. Customer Request Mutation

If customers are allowed to edit a Request, explicitly restrict the fields.

Do not let Customer change:

```text
status
staff assignment
internal notes
system timestamps
user ownership
```

---

# 101. Request History Preservation

If Staff edits a customer-visible field:

```text
original description
```

consider whether the original submission needs preservation.

The historical request should remain understandable.

---

# 102. Request Attachment Deletion

Define who may delete attachments.

Recommended:

```text
Customer
→ own attachment before processing, if editing is supported

Staff
→ operational management

Admin
→ administrative management
```

But do not introduce customer editing/deletion if the simpler immutable-submission workflow is preferable.

---

# 103. Recommended Version 1 Simplicity

For a small business, favor:

```text
Submit Request
→ Staff receives it
→ Staff communicates/handles it
```

over a large multi-state CRM system.

Keep Version 1 focused.

---

# 104. Request Endpoint Contract Matrix

Add:

| ID      | Method | Path                            | Actor              | Auth     | Purpose                      |
| ------- | ------ | ------------------------------- | ------------------ | -------- | ---------------------------- |
| REQ-001 | POST   | `/api/v1/requests`              | Anonymous/Customer | Optional | Submit Made-to-Order Request |
| REQ-002 | GET    | `/api/v1/me/requests`           | Customer           | Required | List own Requests            |
| REQ-003 | GET    | `/api/v1/me/requests/{request}` | Customer           | Required | View own Request             |
| REQ-004 | GET    | `/api/v1/requests`              | Staff/Admin        | Required | Operational Request queue    |
| REQ-005 | GET    | `/api/v1/requests/{request}`    | Staff/Admin        | Required | Operational Request detail   |

Add additional mutation/attachment endpoints only if approved.

---

# 105. Request Collection Pagination

For:

```text
REQ-002
REQ-004
```

use the global pagination convention if pagination is required.

Do not create a request-specific pagination format.

---

# 106. Request Query Parameters

Staff operational listing may use:

```text
search
status
product
created_from
created_to
sort
sort_direction
page
per_page
```

Only include parameters actually required.

All use the Phase 1.11 rules.

---

# 107. Request Search Privacy

Search results must remain within the authorized dataset.

A Staff search must not reveal data outside their permitted operational scope.

A Customer search is implicitly restricted to their own Requests.

---

# 108. Request Sorting

Use a deterministic default.

Recommended:

```text newest first
```

for Staff queue and customer history.

---

# 109. Request Response Levels

Define:

```text
Customer Request Summary
Customer Request Detail
Staff Request Summary
Staff Request Detail
Admin Request Detail
```

where different information is necessary.

Do not create entirely separate resources.

---

# 110. Customer Request Summary

May contain:

```text
id
reference if used
product
submitted time
status if applicable
```

---

# 111. Staff Request Summary

May additionally contain:

```text
customer name
contact information
product
submitted time
status
```

only where operationally necessary.

---

# 112. Staff Request Detail

May include:

```text
full specifications
attachments
contact data
internal operational fields
```

according to authorization.

---

# 113. Admin Request Detail

May include the broadest approved representation.

Still no credentials/secrets.

---

# 114. Request Error Examples

### Anonymous missing contact

```text
MISSING_REQUIRED_FIELD
```

### Invalid product

```text
INVALID_PRODUCT
```

### In-stock product sent to made-to-order request

```text
PRODUCT_NOT_REQUESTABLE
```

### Invalid attachment

```text
INVALID_ATTACHMENT
```

### Customer accessing another customer's Request

```text
RESOURCE_NOT_FOUND
```

or the approved privacy-preserving authorization behavior.

---

# 115. Request Rate Limiting

Anonymous Request creation should be marked:

```text
RATE-LIMIT CANDIDATE
```

because it is a public mutation endpoint.

---

# 116. Request Idempotency

`POST /requests` is not inherently idempotent.

Determine whether duplicate submissions are acceptable.

For example:

```text
Customer taps Submit twice
```

may create two requests accidentally.

A future idempotency mechanism may be appropriate if duplicate support requests are undesirable.

Do not implement it here.

---

# 117. Request Duplicate Detection

Do not use:

```text
same email
+
same message
```

as a hard uniqueness constraint.

Customers may legitimately submit similar requests.

Use explicit idempotency or abuse controls rather than false business uniqueness.

---

# 118. Request Concurrency

Request submission itself has relatively low concurrency risk.

Operational modifications may have races:

```text
Staff A closes
+
Staff B updates
```

If a status model is introduced, this should be treated as state-transition concurrency.

---

# 119. Request Security Tests

Later automated tests must verify:

```text
anonymous create succeeds
customer create succeeds
customer cannot access another customer's request
staff can access operational request
staff cannot access credentials
staff cannot alter customer ownership
client cannot set status
client cannot set user_id
client cannot create Order via request payload
client cannot submit payment state
in-stock product cannot bypass requestability rule
private attachment cannot become public
```

---

# 120. Request Workflow Tests

### Existing Made-to-Order product

```text
Catalog Product
→ MADE_TO_ORDER
→ Request
→ Staff receives
```

### Anonymous custom request

```text
Anonymous
→ contact information
→ specifications
→ optional attachment
→ Request created
```

### Authenticated customer

```text
Customer
→ Request
→ Request associated with account
→ appears in own Request history
```

---

# 121. Customer-Facing Principle

A visitor should be able to move directly from:

```text
Product page
```

to:

```text
Made-to-Order Request
```

without being forced to register.

---

# 122. Staff-Facing Principle

Staff should see new Requests in a manageable operational queue.

They should not need Admin approval to read/process every normal Request.

---

# 123. Admin-Facing Principle

Admin should retain oversight but not become a bottleneck for normal Request handling.

---

# 124. No Forced Account Creation

Do not require:

```text
Anonymous visitor
→ register
→ login
→ submit request
```

The approved flow is:

```text
Anonymous
→ submit request
```

with contact information.

---

# 125. No Normal Checkout

Do not allow:

```text
MADE_TO_ORDER
→ add to cart
→ checkout
```

unless the product business model is explicitly changed later.

---

# 126. No Payment on Request

Do not ask for:

```text
payment
card
mobile money
```

as part of Request creation.

Payment belongs to a future actual Order transaction.

---

# 127. Request and Product Price

A Made-to-Order product may display a starting/current catalog price, but Request submission does not lock that price unless an explicit quotation system exists.

Do not represent Request submission as price acceptance.

---

# 128. Request and Delivery

A Request may contain delivery/location information if needed for feasibility discussion, but do not treat it as a final delivery arrangement.

Final delivery fee/fulfillment belongs to the eventual Order workflow.

---

# 129. Request and Customer Account

For authenticated customers:

```text
Request.user_id
```

is derived from authentication.

For anonymous:

```text
Request.user_id = null
```

Do not use a customer-provided account identifier.

---

# 130. Request Representation Compatibility

The Request API must use the global:

```text
data
meta
errors
```

response model.

Do not create a request-specific envelope.

---

# 131. Request Field Compatibility

Within Version 1:

Do not change:

```text
field type
field meaning
requiredness
enum value semantics
ownership semantics
```

without API compatibility review.

---

# 132. Required Documentation Updates

## `docs/api/api-contract.md`

Add:

```text
## Made-to-Order Request API Contract

### Request Resource
### REQ-001 Create Request
### REQ-002 Customer Request List
### REQ-003 Customer Request Detail
### REQ-004 Staff Request List
### REQ-005 Staff Request Detail
### Optional Attachment Operations
### Request Input
### Anonymous Submission
### Customer Ownership
### Staff Access
### Admin Access
### Validation
### Errors
### Privacy
### Rate Limiting
### Idempotency
### Future Request-to-Order Boundary
```

---

## `docs/api/api-resources.md`

Update:

```text
Request
Request Attachment
```

with:

```text
ownership
relationships
public/private classification
customer representation
staff representation
admin representation
mutable/immutable fields
```

---

## `docs/api/api-conventions.md`

Add reusable Request conventions:

```text
anonymous creation
customer ownership
private request data
attachment authorization
operational representation
```

---

## `docs/domain/business-rules.md`

Confirm:

```text
Made-to-Order products use Request rather than normal checkout.
Requests may be anonymous.
Authenticated Requests belong to the Customer.
Request submission does not create an Order.
Request submission does not reserve inventory.
Request submission does not initiate payment.
Optional attachments are supported.
Staff handle Requests operationally.
```

---

## `docs/decisions.md`

Record significant decisions such as:

```text
### REQ-001 — Anonymous Made-to-Order Requests

Visitors may submit Made-to-Order Requests without accounts.

### REQ-002 — Authenticated Request Ownership

Authenticated Requests belong to the submitting Customer.

### REQ-003 — Request Is Not an Order

Submitting a Request does not create a purchase transaction.

### REQ-004 — Request Does Not Reserve Inventory

Made-to-Order Requests do not reserve normal stock.

### REQ-005 — Optional Attachments

Request attachments are optional.

### REQ-006 — Anonymous Retrieval

Anonymous Request retrieval is not supported unless a secure mechanism is explicitly introduced.

### REQ-007 — Payment Separation

Requests do not directly initiate payment; payment begins only through a later approved Order/payment workflow.
```

Only record decisions actually accepted during the phase.

---

# 133. Required Validation Checklist

### Submission

* [ ] Anonymous Request creation works.
* [ ] Authenticated Customer Request creation works.
* [ ] Customer does not need registration to submit.
* [ ] Request does not create an Order.
* [ ] Request does not initiate Payment.
* [ ] Request does not reserve inventory.

### Product relationship

* [ ] Product reference is validated.
* [ ] Product type is validated.
* [ ] MADE_TO_ORDER behavior is enforced.
* [ ] IN_STOCK products cannot bypass the normal commerce path.

### Ownership

* [ ] Authenticated Request is linked server-side to Customer.
* [ ] Client cannot choose `user_id`.
* [ ] Customer can only view own Requests.
* [ ] Staff can access operational Requests.
* [ ] Admin has authorized oversight.

### Anonymous security

* [ ] Anonymous creation is public.
* [ ] Anonymous retrieval is not automatically public.
* [ ] Request IDs are not bearer credentials.
* [ ] Anonymous contact information is protected.

### Input

* [ ] Required/optional fields are defined.
* [ ] Quantity is validated.
* [ ] Dimensions, if structured, are validated.
* [ ] Notes have reasonable limits.
* [ ] Unknown fields follow Phase 1.14.
* [ ] Server-generated fields cannot be client-controlled.

### Attachments

* [ ] Attachments are optional.
* [ ] Attachment validation is identified.
* [ ] Private attachments are protected.
* [ ] No permanent public attachment URLs are assumed.

### Staff operations

* [ ] Staff can receive/view Requests.
* [ ] Staff can perform approved operational handling.
* [ ] Staff cannot change customer ownership.
* [ ] Staff cannot access credentials.
* [ ] Staff cannot turn a Request into arbitrary Order/payment state through generic updates.

### Admin

* [ ] Admin has broader Request access.
* [ ] Admin remains subject to explicit authorization.
* [ ] Administrative actions are candidates for audit.

### Privacy

* [ ] Request data is private.
* [ ] Contact data is protected.
* [ ] Internal notes are not customer-visible.
* [ ] Attachments inherit Request authorization.

### Reliability

* [ ] Duplicate submission risk is documented.
* [ ] Rate-limit requirement is identified.
* [ ] Idempotency is evaluated.
* [ ] Concurrent operational updates are considered.

### API consistency

* [ ] Global response envelope is used.
* [ ] Global error contract is used.
* [ ] Global query conventions are used.
* [ ] Global pagination rules are used where applicable.
* [ ] Version 1 enums remain CLOSED.

### Cross-platform

* [ ] Next.js can submit a Request.
* [ ] Flutter can submit a Request.
* [ ] Both can consume customer-owned Request history.
* [ ] No client-specific Request API exists.

### Payment

* [ ] No payment implementation exists in this phase.
* [ ] Payment remains in **Phase Group H**.

---

# 134. Explicitly Out of Scope

Do NOT:

```text
Implement Laravel Request model
Create migrations
Create controllers
Create Form Requests
Create policies
Implement file storage
Implement attachment scanning
Implement notifications
Implement email
Implement quotation system
Implement Request-to-Order conversion
Implement payment
Implement production scheduling
Implement CRM
Implement staff assignment engine
Build Next.js Request form
Build Flutter Request form
Build Admin Request dashboard
Generate final OpenAPI schemas
```

---

# 135. Definition of Done

Phase 1.25 is complete when:

1. Made-to-Order Request is defined as a first-class Version 1 resource.
2. Anonymous Request creation is explicitly supported.
3. Authenticated Customer Request creation is supported.
4. Customer ownership is defined.
5. Staff operational access is defined.
6. Admin administrative access is defined.
7. Request is explicitly separated from Order.
8. Request is explicitly separated from Payment.
9. Request does not reserve inventory.
10. Product/Variant relationship rules are defined.
11. MADE_TO_ORDER requestability is defined.
12. Request input fields are classified.
13. Anonymous contact information is defined.
14. Optional attachments are defined.
15. Anonymous retrieval security is addressed.
16. Customer retrieval is ownership-scoped.
17. Staff operational retrieval is defined.
18. Request privacy rules are defined.
19. Request status is either explicitly defined or deliberately deferred.
20. Request modification is either explicitly defined or deliberately deferred.
21. Request-to-Order conversion is explicitly outside this contract unless separately approved.
22. Abuse/rate-limit considerations are identified.
23. Duplicate/idempotency concerns are identified.
24. Version 1 closed-enum policy remains intact.
25. Payment remains assigned to **Phase Group H**.
26. Consolidated documentation has been updated.
27. No implementation code has been written.

---

# 136. STOP CONDITION — Mandatory

After updating:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the security/privacy/workflow review:

**STOP.**

Do not implement Request APIs.

Do not create Laravel models/controllers.

Do not implement file storage.

Do not implement notifications or email.

Do not create a quotation system.

Do not convert Requests into Orders.

Do not implement Payment.

The next phase must be explicitly requested.

### Recommended next phase

# Phase 1.26 — Define General Enquiry API Contract

This phase should define the other anonymous/customer communication path:

```text
Anonymous OR Customer
        ↓
General Enquiry
        ↓
Staff receives/handles
        ↓
Admin oversight
```

including:

```text
contact information
subject/message
optional attachment
customer ownership
anonymous submission
Staff operational access
Admin access
privacy
anti-spam requirements
response/reply boundary
```

while keeping Enquiries completely separate from both **Made-to-Order Requests** and **Orders**.
