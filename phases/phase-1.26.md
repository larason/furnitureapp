# Phase 1.26 — Define General Enquiry API Contract

## 1. Purpose

Phase 1.26 defines the **complete Version 1 API contract for General Enquiries**.

A General Enquiry is a communication request from a visitor or customer that is **not necessarily about purchasing an existing in-stock product and is not necessarily a Made-to-Order furniture request**.

The approved identity model is:

```text id="0b2f4a"
ANONYMOUS
    ↓
may submit enquiry

CUSTOMER
    ↓
may submit enquiry
    ↓
may access own enquiry history where supported

STAFF
    ↓
receives and handles enquiries

ADMIN
    ↓
administrative oversight
```

The core workflow is:

```text id="7p8f5n"
Anonymous / Customer
        ↓
General Enquiry
        ↓
Staff receives
        ↓
Staff handles/responds
        ↓
Customer/visitor receives response through the approved communication channel
```

This phase must keep General Enquiries separate from:

```text id="1w5r9c"
Catalog
Cart
Checkout
Order
Made-to-Order Request
Payment
```

---

# 2. Documentation Strategy

Continue using the consolidated documentation approach.

Update:

```text id="8x0g5d"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do **not** create:

```text id="3x9k2q"
enquiry-api.md
enquiry-contract.md
contact-api.md
customer-enquiry.md
```

as permanent documents.

The General Enquiry contract belongs in the central API documentation.

---

# 3. Authoritative Project Paths

Use:

```text id="6a4g2z"
AGENTS.md
docs/VISION.md
```

These remain authoritative.

Do not revert to:

```text id="c5h8m4"
agent.md
VISION.md
```

---

# 4. Payment Boundary

General Enquiries do not initiate payment.

Payment remains assigned to:

**Phase Group H**

Do not add:

```text id="f7z8e1"
payment_id
payment_status
amount_paid
provider_reference
```

as customer-controlled enquiry data.

---

# 5. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

If the Enquiry resource uses a status enum, use only the formally approved values.

Do not create a large support-ticket state machine merely because it is common in CRM software.

---

# 6. Dependency Position

Current sequence:

```text id="h8r4q1"
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 Tracking + Fulfillment
       ↓
1.25 Made-to-Order Request
       ↓
1.26 General Enquiry
       ↓
1.27 Notification API Contract
       ↓
...
```

The Enquiry contract may share mechanisms with Request, but it remains a separate domain concept.

---

# 7. Authoritative Inputs

Read:

```text id="7m2h4x"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Specifically review:

```text id="1x4s8r"
Phase 1.17 Authentication
Phase 1.18 Authorization
Phase 1.19 Endpoint Inventory
Phase 1.25 Made-to-Order Request
Phase 1.16 Error Contract
Phase 1.14 Request/Input Conventions
Phase 1.15 Validation
```

---

# 8. Core Enquiry Principle

A General Enquiry represents:

> **A request for information or communication with the business.**

It does not automatically represent:

```text id="r8x1n4"
a purchase
a quote
a product request
an order
a payment
a support contract
```

The customer may use it for questions such as:

```text id="u6t3c5"
general product question
store information
availability question
business contact
delivery question
customization question
complaint/feedback
other legitimate business enquiry
```

The exact category model should remain simple in Version 1.

---

# 9. Enquiry vs Made-to-Order Request

This distinction must be explicit.

### Made-to-Order Request

Customer asks:

> "Can you make this furniture?"

### General Enquiry

Customer asks:

> "What time do you open?"

or:

> "Do you deliver to this area?"

or another general business question.

Do not force every communication into the Request resource.

---

# 10. Enquiry vs Order Support

An enquiry may mention an existing Order, but that does not make it an Order operation.

If the business later introduces:

```text id="3m8qv7"
Order-specific support
```

it should remain linked to the Order rather than allowing arbitrary Order modification through the Enquiry API.

---

# 11. Anonymous Submission

Anonymous users may submit General Enquiries.

Therefore:

```text id="q8e4v1"
POST /api/v1/enquiries
```

does not require authentication.

The request must contain sufficient contact information.

---

# 12. Authenticated Customer Submission

A logged-in Customer may also submit an Enquiry.

The backend should associate the Enquiry with the authenticated Customer automatically.

Do not require:

```json id="y8m1g6"
{
  "user_id": "..."
}
```

from the client.

---

# 13. Anonymous Identity

For an anonymous enquiry:

```text id="m2k9x7"
user_id = null
```

is valid.

Contact data becomes part of the enquiry submission.

---

# 14. Customer Ownership

For authenticated submission:

```text id="g7f3x1"
Customer
   ↓ owns
Enquiry
```

Customers may later be able to retrieve their own enquiries.

They must never retrieve another customer's enquiry.

---

# 15. Anonymous Retrieval

Do not automatically expose:

```text id="r8c5y1"
GET /enquiries/{id}
```

to anonymous callers.

A predictable Enquiry ID must not become a password.

Recommended Version 1:

> Anonymous Enquiry creation is supported; anonymous Enquiry retrieval is not supported unless a secure retrieval mechanism is explicitly approved.

---

# 16. Enquiry Endpoint Inventory

A likely Version 1 inventory is:

```text id="q4j9w8"
ENQ-001
POST /api/v1/enquiries

ENQ-002
GET /api/v1/me/enquiries

ENQ-003
GET /api/v1/me/enquiries/{enquiry}

ENQ-004
GET /api/v1/enquiries

ENQ-005
GET /api/v1/enquiries/{enquiry}
```

Optional later operations — approved V1 mapping per `docs/decisions.md` `ADR/API-ENQ-007/008` and `docs/api/api-contract.md §27.1`:

```text id="6b2v9m"
ENQ-006
POST /api/v1/enquiries/{enquiry}/close (+ optional reopen)
Staff close enquiry (OPEN → CLOSED, CLOSED → OPEN if approved)

ENQ-007
POST /api/v1/enquiries/{enquiry}/attachments
Attachment management (scoped token, private to parent)
```

Staff reply/response as in-app messaging remains deferred to a later phase (ordinary business contact process in V1). Only include them in Version 1 if genuinely required and approved per consolidated contract.

---

# 17. Create Enquiry

Define:

```text id="n5g1t6"
ENQ-001

POST /api/v1/enquiries
```

Authentication:

```text id="w2v3h8"
Optional
```

Actor:

```text id="f6x4v2"
Anonymous / Customer
```

Purpose:

> Submit a General Enquiry to the business.

---

# 18. Create Enquiry Input

Potential fields:

```text id="8j2m0w"
name
email
phone
subject
message
attachment
```

Only include fields required by the actual business process.

---

# 19. Contact Information

Anonymous Enquiries require enough contact information for Staff to respond.

At minimum evaluate:

```text id="4p9k1x"
name
email
phone
```

Do not require unnecessary identity information.

---

# 20. Authenticated Customer Contact

For authenticated Customers, determine whether:

```text id="c4s8x7"
name
email
phone
```

are supplied each time or derived from the customer account.

Recommended:

> Derive trusted account identity server-side while allowing the request contract to capture the contact details actually needed for communication.

If a historical contact snapshot is important, record it as part of the Enquiry submission.

---

# 21. Requesting Contact Back

Do not assume the Customer must always provide both:

```text id="j1h8q4"
email
phone
```

if the business already has an authenticated contact channel.

Define the minimum communication requirement clearly.

---

# 22. Subject

Evaluate whether:

```text id="d9y2m6"
subject
```

is required.

For general enquiries, a subject is useful for Staff triage.

Recommended:

> Keep `subject` required and bounded in length.

Do not make it an unconstrained text blob.

---

# 23. Message

`message` contains the customer's enquiry.

Define:

```text id="f1z8q6"
required
minimum length if useful
maximum length
```

Do not allow unlimited message size.

---

# 24. Message Safety

Treat message content as untrusted input.

Do not:

```text id="m8f7x2"
render raw HTML
execute scripts
interpret commands
store as executable content
```

The eventual frontend should escape/render safely.

---

# 25. HTML/Markdown

Decide whether Enquiry messages support formatting.

For Version 1, recommend:

> **Plain text only.**

This greatly reduces complexity and attack surface.

Do not add Markdown/HTML unless the business needs it.

---

# 26. Subject and Message Sanitization

Do not silently transform meaningful customer text.

Prefer:

```text id="g9j3p8"
validation
+
safe storage
+
safe output encoding
```

rather than arbitrary destructive sanitization.

---

# 27. Enquiry Category

Evaluate whether the business needs:

```text id="f7z4n1"
category
```

such as:

```text GENERAL
PRODUCT
DELIVERY
OTHER
```

Do not add categories if Staff can efficiently understand the enquiry through its subject/message.

If a category enum is introduced, it is:

> CLOSED in Version 1.

---

# 28. Keep Category Simple

Do not create:

```text id="6q8r2y"
PRODUCT_AVAILABILITY
PRODUCT_QUESTION
DELIVERY_DELAY
PAYMENT_PROBLEM
ACCOUNT_PROBLEM
COMPLAINT
REFUND
...
```

unless operational requirements justify a proper support taxonomy.

---

# 29. Product Association

Evaluate whether an Enquiry can optionally refer to a Product.

Possible:

```text id="n7s3k1"
product_id = optional
```

Useful example:

> "Can you tell me more about this sofa?"

If supported, validate:

```text id="3w8h4q"
Product exists
+
Product is appropriate for public reference
```

Do not require Product association for every General Enquiry.

---

# 30. Order Association

Evaluate whether an Enquiry may optionally reference an Order.

Possible:

```text id="z6v4r8"
order_id = optional
```

This can support:

> "I have a question about my order."

If supported, a Customer may reference only their own Order.

Staff/Admin may have broader operational access.

---

# 31. Order Ownership Validation

If a Customer submits:

```json id="y1f0m7"
{
  "order_id": "someone-elses-order"
}
```

the API must reject or conceal the invalid association.

Knowing an Order ID is not authorization.

---

# 32. Request Association

Do not automatically add:

```text id="p1w8d7"
request_id
```

unless General Enquiries genuinely need to reference a Made-to-Order Request.

Keep relationships intentional.

---

# 33. Multiple Associations

Avoid allowing arbitrary:

```text id="z4c7v2"
product_id
order_id
request_id
cart_id
payment_id
```

all at once.

The Enquiry should have only the relationships that provide real business value.

---

# 34. Recommended Association Model

For Version 1, consider:

```text id="3s7j9v"
Product → optional
Order → optional
```

but do not require either.

This supports common questions without turning Enquiry into a universal relationship table.

---

# 35. Enquiry Attachment

Attachments are optional.

An Enquiry may include:

```text id="q8h1c4"
image
document
screenshot
reference
```

where appropriate.

---

# 36. Attachment Transport

Follow the same attachment strategy selected for Made-to-Order Requests.

Do not introduce a second upload architecture.

---

# 37. Attachment Authorization

Attachments inherit Enquiry authorization.

Therefore:

```text id="w2z6l7"
Customer
→ own enquiry attachments

Staff
→ operational access

Admin
→ authorized access

Anonymous
→ no automatic retrieval
```

---

# 38. Attachment Security

Later implementation must validate:

```text id="8v1y5m"
size
content type
file signature
filename
storage access
```

Do not trust client-provided file extension/MIME type.

---

# 39. Enquiry Status

Determine whether Version 1 needs a status.

A minimal model could be:

```text id="4m9c7x"
OPEN
CLOSED
```

or another already-approved state set.

Do not introduce a complex workflow without operational need.

---

# 40. If Status Is Introduced

The status values are:

```text id="q1n3g5"
OPEN
CLOSED
```

under the global closed-enum policy. `OPEN` is default at creation (needs attention); `CLOSED` is terminal via `ENQ-006 POST /enquiries/{enquiry}/close`; optional reopen `CLOSED → OPEN` via `POST /enquiries/{enquiry}/reopen` if business approves, otherwise `CLOSED` terminal. `OPEN | CLOSED` is the complete V1 enquiry status enum (CLOSED per §5).

Do not allow Staff to create arbitrary statuses.

---

# 41. Submitted State

Do not create a redundant:

```text id="n5f6d8"
SUBMITTED
```

state merely because every workflow starts somewhere.

The database/API can use creation time to establish that an Enquiry exists unless an actual status lifecycle is needed.

---

# 42. Recommended Minimal Enquiry Lifecycle

For a small business, consider:

```text id="b4k8m3"
OPEN
   ↓
CLOSED
```

This is sufficient if Staff only need to know whether an Enquiry still needs attention.

Do not add:

```text id="c2f5a7"
ASSIGNED
IN_PROGRESS
WAITING_FOR_CUSTOMER
ESCALATED
RESOLVED
```

without operational justification.

---

# 43. Staff Enquiry Queue

If `OPEN/CLOSED` is approved, Staff can use:

```text id="f8k0m4"
GET /api/v1/enquiries?status=OPEN
```

with global filtering/pagination conventions.

---

# 44. Staff Enquiry Detail

Staff need enough information to respond:

```text id="x3v8q1"
contact
subject
message
optional Product/Order reference
attachments
status
timestamps
```

---

# 45. Staff Reply

Evaluate whether the API should itself store Staff replies.

Possible:

```text id="n9y7r6"
POST /enquiries/{enquiry}/messages
```

This would turn Enquiry into a conversation/thread system.

For a small Version 1 system, avoid building a full messaging system unless required.

---

# 46. Recommended Version 1 Reply Boundary

Unless the business explicitly requires in-app conversation:

> The Enquiry API should capture and manage the inbound enquiry; Staff response can initially occur through the business's normal communication process.

This keeps Version 1 small.

Do not invent a chat system.

---

# 47. Notification Dependency

Staff must know that a new Enquiry exists.

That may eventually be handled by:

```text id="j7k4p2"
Notification API
```

which is a later phase.

The Enquiry operation must not depend on notification delivery succeeding.

---

# 48. Email Dependency

Real email delivery remains deferred to:

**Phase Group R**.

The Enquiry API must not require email infrastructure to store an enquiry.

---

# 49. Customer Response Delivery

Do not promise that:

```text id="s0w2k4"
POST /enquiries
```

returns an immediate human Staff reply.

It only submits the enquiry.

Later notification/contact systems determine how Staff responses are delivered.

---

# 50. Enquiry Privacy

Enquiries are private business communication.

They are not public resources.

Classification:

```text id="7b2h4z"
Catalog → PUBLIC

Enquiry → PRIVATE
```

---

# 51. Anonymous Enquiry Privacy

An anonymous Enquiry must not become discoverable because it has:

```text id="m4r1w7"
numeric ID
UUID
public reference
```

Identifiers are not authorization.

---

# 52. Customer Enquiry Retrieval

If authenticated customer history is supported:

```text id="g6v5y3"
GET /api/v1/me/enquiries
GET /api/v1/me/enquiries/{enquiry}
```

Only the authenticated customer's Enquiries are returned.

---

# 53. Staff Enquiry Collection

Staff need operational access:

```text id="8g1x6p"
GET /api/v1/enquiries
```

Authorization:

```text id="w0j4m2"
Staff with enquiry-management permission
```

---

# 54. Admin Enquiry Collection

Admin can use the same resource with broader authorization.

Do not duplicate the entire Enquiry resource for Admin.

---

# 55. Staff Cannot Control Customer Accounts

Staff can handle the Enquiry.

They cannot use Enquiry access to:

```text id="r7z5x3"
change customer role
change customer password
block customer
restrict browsing
restrict ordering
```

This remains an explicit authorization boundary.

---

# 56. Admin Customer Access

Admin may have authorized visibility into customer communication for legitimate administration.

Do not return credentials or security secrets.

---

# 57. Enquiry Ownership Tampering

Reject attempts to submit:

```json id="y8g2d1"
{
  "user_id": "another-user"
}
```

as the Enquiry owner.

The server derives ownership from authentication.

---

# 58. Enquiry Status Tampering

Do not accept customer input such as:

```json id="n7q4h6"
{
  "status": "CLOSED"
}
```

during creation or normal customer updates unless explicitly part of an approved customer action.

---

# 59. Staff Status Changes

If status exists, Staff may change it through controlled operations.

Prefer:

```text id="z4j5n8"
POST /enquiries/{enquiry}/close
```

over unrestricted:

```text id="6r3q8w"
PATCH /enquiries/{enquiry}
{
  "status": "CLOSED"
}
```

if closing represents a business action.

---

# 60. Admin Status Changes

Admin can perform authorized operational actions, respecting the same state rules.

---

# 61. Enquiry Reopening

If reopening is needed:

```text id="h6j2m4"
POST /enquiries/{enquiry}/reopen
```

may be clearer than arbitrary status PATCH.

Do not create it unless the business actually needs reopening.

---

# 62. Enquiry Deletion

Do not provide normal Customer DELETE.

Historical customer communication should not disappear casually.

Admin deletion, if required for privacy/compliance, needs a dedicated policy.

---

# 63. Enquiry vs Deletion

Distinguish:

```text id="f6g1k0"
CLOSE
≠
DELETE
```

Closing means no longer active.

Deletion is data destruction.

---

# 64. Enquiry Modification

For Version 1, consider making the submitted message immutable after creation.

This protects historical meaning.

If Customer corrections are required, allow a separate communication process rather than rewriting the original message.

---

# 65. Recommended Customer Model

For simplicity:

```text id="2q8z1w"
Customer:
create enquiry
read own enquiry

Staff:
read/manage operational enquiry

Admin:
administrative oversight
```

Do not create broad customer edit/delete permissions.

---

# 66. Enquiry History

If Staff can update status, preserve enough information to determine:

```text id="p4x8r6"
original submission
current state
important state changes
```

Do not silently rewrite customer history.

---

# 67. Enquiry Auditability

Later audit/event logging may capture:

```text id="v7c4q9"
status changed
staff handled enquiry
admin intervention
attachment changes
```

Do not implement audit infrastructure in this phase.

---

# 68. Enquiry Search

Staff may need to search:

```text id="m7k2s1"
name
email
phone
subject
message
enquiry reference
Order reference
```

Use only approved searchable fields.

Do not allow arbitrary SQL-like search parameters.

---

# 69. Enquiry Filtering

Potential:

```text id="n6z4f8"
status
date range
product
```

Use the global query conventions.

---

# 70. Enquiry Pagination

Staff Enquiry queues should be paginated.

Customer enquiry history should be paginated if it can grow.

Use the Phase 1.12 pagination convention.

---

# 71. Enquiry Sorting

Recommended:

```text id="r9f2v6"
newest first
```

for both Staff queue and Customer history unless business priorities require another deterministic order.

---

# 72. Enquiry Response Envelope

Use Phase 1.13:

```json id="q5s8x1"
{
  "data": {}
}
```

or:

```json id="s3f8k7"
{
  "data": [],
  "meta": {}
}
```

Do not create an Enquiry-specific envelope.

---

# 73. Enquiry Error Contract

Use Phase 1.16.

Potential errors:

```text id="w7k4p6"
VALIDATION_ERROR
MISSING_REQUIRED_FIELD
INVALID_VALUE
INVALID_PRODUCT
RESOURCE_NOT_FOUND
FORBIDDEN
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
```

Only retain codes that provide useful client behavior.

---

# 74. Enquiry Error Security

Do not expose:

```text id="a4d6m8"
another customer's existence
private contact details
staff-only notes
internal IDs
database errors
```

through errors.

---

# 75. Anonymous Abuse

Because anonymous Enquiry creation is public, mark it as a high-priority anti-abuse endpoint.

Later implementation should consider:

```text id="y6f2m3"
rate limiting
spam prevention
request-size limits
attachment limits
```

---

# 76. CAPTCHA

Evaluate whether anonymous Enquiries need CAPTCHA/challenge protection.

Do not automatically impose CAPTCHA if other controls provide sufficient protection.

---

# 77. Rate Limit

At minimum classify:

```text id="n8r4t5"
POST /enquiries
```

as a rate-limit candidate.

Do not implement the rate limiter in this phase.

---

# 78. Idempotency

Enquiry submission can produce duplicate records if a client retries after a timeout.

Evaluate whether:

```text id="j4m7x1"
POST /enquiries
```

should support idempotency.

For a small business, this may be less critical than Checkout, but duplicate customer enquiries can still create operational noise.

Record the decision.

---

# 79. Duplicate Enquiries

Do not use:

```text id="0x6q3m"
email + message
```

as uniqueness.

Legitimate repeated enquiries can occur.

Use explicit idempotency or accept duplicate submissions.

---

# 80. Enquiry Attachments and Idempotency

If an Enquiry request includes an attachment, retries can create duplicate files.

The eventual upload/idempotency strategy must account for that.

Do not solve it by making attachments globally unique.

---

# 81. Enquiry and Customer Account

An authenticated customer's Enquiry should be linked to the Customer identity.

But the customer should not be able to submit:

```text id="q5v8w1"
someone else's customer_id
```

---

# 82. Enquiry and Staff

Staff may see customer contact information necessary to answer the enquiry.

They do not need access to unrelated customer account information.

---

# 83. Enquiry and Admin

Admin may have broader access but should still respect data minimization.

---

# 84. Enquiry and Order

If `order_id` association is supported:

```text id="9n6v1p"
Customer
→ only own Order

Staff
→ operational Orders

Admin
→ authorized Orders
```

Do not let Enquiry association become an indirect Order access mechanism.

---

# 85. Enquiry and Product

If `product_id` is supported:

```text id="0j8s3d"
public Product reference
```

should be sufficient.

Do not expose internal catalog-management fields through Enquiry.

---

# 86. Enquiry and Made-to-Order Request

Do not automatically convert:

```text id="j8q2b5"
Enquiry
→ Request
```

or:

```text id="s5m1x4"
Request
→ Enquiry
```

without an explicit business action.

The two resources represent different customer intents.

---

# 87. Optional Reference to Request

If staff need to link an Enquiry to an existing Request, define this as an explicit relationship later.

Do not accept arbitrary linkage from anonymous clients.

---

# 88. Enquiry and Notification

The likely event is:

```text id="1n5k6x"
Enquiry created
→ operational notification
```

Notification API is later.

Do not make notification success a prerequisite for Enquiry persistence.

---

# 89. Enquiry and Email

The actual email/reply infrastructure remains deferred to:

**Phase Group R**.

The API must still work without email delivery.

---

# 90. Enquiry and SEO

Enquiries are private and must not be indexed.

Do not expose Enquiry content through public catalog pages.

---

# 91. Enquiry Cache Policy

Enquiry responses are private.

Do not public-cache:

```text id="4x7f8n"
/me/enquiries
/enquiries/{id}
```

---

# 92. Customer-Friendly Flow

The normal customer flow should remain:

```text id="r5z3w7"
Open Contact/Enquiry form
      ↓
Enter message
      ↓
Submit
      ↓
Confirmation
      ↓
Business handles enquiry
```

No Staff approval is required to submit an ordinary enquiry.

---

# 93. Anonymous-Friendly Flow

A visitor should be able to:

```text id="7h1g9p"
Open website
      ↓
Contact/Enquiry
      ↓
Name + contact + message
      ↓
Submit
```

without registration.

---

# 94. Staff-Friendly Flow

Staff should be able to:

```text id="2n7x6v"
Open enquiry queue
      ↓
See newest enquiries
      ↓
Open enquiry
      ↓
Read context
      ↓
Handle/respond
      ↓
Close if appropriate
```

without unnecessary Admin intervention.

---

# 95. Admin-Friendly Flow

Admin should be able to:

```text id="6j5p3k"
Monitor enquiries
      ↓
Inspect operational state
      ↓
Manage staff access
      ↓
Intervene when necessary
```

while remaining separate from routine customer communication.

---

# 96. Enquiry Contract Matrix

Add to:

```text id="q3n5f1"
docs/api/api-contract.md
```

| ID      | Method | Path                                      | Actor              | Auth   | Purpose                                      |
| ------- | ------ | ----------------------------------------- | ------------------ | ------ | -------------------------------------------- |
| ENQ-001 | POST   | `/api/v1/enquiries`                       | Anonymous/Customer | Optional | Create enquiry                               |
| ENQ-002 | GET    | `/api/v1/me/enquiries`                    | Customer           | Required | List own enquiries                           |
| ENQ-003 | GET    | `/api/v1/me/enquiries/{enquiry}`          | Customer           | Required | View own enquiry                             |
| ENQ-004 | GET    | `/api/v1/enquiries`                       | Staff/Admin        | Required | Operational enquiry queue                    |
| ENQ-005 | GET    | `/api/v1/enquiries/{enquiry}`             | Staff/Admin        | Required | Operational enquiry detail                   |
| ENQ-006 | POST   | `/api/v1/enquiries/{enquiry}/close`       | Staff/Admin        | Required | Close enquiry `OPEN → CLOSED` (reopen optional) |
| ENQ-007 | POST   | `/api/v1/enquiries/{enquiry}/attachments` | Anonymous* / Customer / Staff/Admin | Scoped | Upload enquiry attachment (scoped token, private to parent) |

Approved V1 IDs per `docs/api/api-contract.md §27.1` and `docs/decisions.md ADR/API-ENQ-007` (`ENQ-006` close) + `ADR/API-ENQ-007` (`ENQ-007` attachment). `ENQ-008` not used; Staff reply remains deferred. Add action/attachment endpoints only if actually approved — `ENQ-006/007` are approved in V1.

---

# 97. Enquiry Input Matrix

Add:

| Field            |             Anonymous |              Customer |   Server Controlled |
| ---------------- | --------------------: | --------------------: | ------------------: |
| name             |                   Yes |      Optional/derived |                  No |
| email            |                   Yes |      Optional/derived |                  No |
| phone            |                   Yes |      Optional/derived |                  No |
| subject          |                   Yes |                   Yes |                  No |
| message          |                   Yes |                   Yes |                  No |
| product_id       |              Optional |              Optional |           Validated |
| order_id         | Optional if supported | Optional if supported | Ownership validated |
| attachment       |              Optional |              Optional |     Stored securely |
| user_id          |                    No |                    No |                 Yes |
| status           |                    No |                    No |                 Yes |
| created_at       |                    No |                    No |                 Yes |
| internal_notes   |                    No |                    No |         Staff/Admin |
| staff assignment |                    No |                    No |         Staff/Admin |

---

# 98. Enquiry Authorization Matrix

Add:

| Operation                 | Anonymous | Customer |                   Staff |                   Admin |
| ------------------------- | --------: | -------: | ----------------------: | ----------------------: |
| Create enquiry            |       Yes |      Yes | Operational if required | Operational if required |
| View own enquiry          |        No |      Yes |             No as owner |              Authorized |
| List own enquiries        |        No |      Yes |                      No |              Authorized |
| Operational list          |        No |       No |                     Yes |                     Yes |
| Operational detail        |        No |       No |                     Yes |                     Yes |
| Change operational status |        No |       No |                     Yes |                     Yes |
| Manage internal notes     |        No |       No |                     Yes |                     Yes |
| Manage customer account   |        No | Own only |                      No |         Authorized only |

---

# 99. Enquiry Privacy Classification

Define:

```text id="w7q6c9"
PUBLIC
→ no

CUSTOMER-PRIVATE
→ own Enquiry

STAFF-OPERATIONAL
→ authorized Staff

ADMINISTRATIVE
→ authorized Admin

INTERNAL
→ private implementation data
```

---

# 100. Enquiry State Decision

If the business accepts the minimal lifecycle:

```text id="m3n8v4"
OPEN
↓
CLOSED
```

document:

```text id="9j2p5c"
New Enquiry → OPEN
Staff handles → CLOSED
```

Do not introduce other states until needed.

---

# 101. Enquiry State Transition Matrix

If the two-state model is approved:

| Current State | Action | Actor                  | Next State |
| ------------- | ------ | ---------------------- | ---------- |
| OPEN          | Close  | Staff/Admin            | CLOSED     |
| CLOSED        | Reopen | Authorized Staff/Admin | OPEN       |

Only include `REOPEN` if the business actually requires it.

Otherwise:

```text id="5x1n6c"
OPEN → CLOSED
```

is sufficient.

---

# 102. Immutable Original Message

Recommend:

> The original customer-submitted Enquiry message is immutable.

This prevents a later Staff edit from changing what the customer actually asked.

---

# 103. Internal Notes

Staff/Admin may need internal notes.

These must be a separate representation from the customer-submitted message.

Do not allow:

```text id="8t9w4p"
internal_notes
```

to appear in the customer response.

---

# 104. Staff Response Model

Do not implement a messaging thread merely to permit Staff to reply.

The project can initially handle responses through the ordinary business contact process.

A later phase can introduce a conversation system if needed.

---

# 105. Enquiry Response Delivery

The API response after creation should mean:

> Your enquiry was accepted by the system.

It should not imply:

> Staff has already responded.

---

# 106. Confirmation Response

A successful create may return:

```text id="1r8h9z"
enquiry identity
submission time
current status if defined
```

Do not expose Staff internal data.

---

# 107. Enquiry ID

The Enquiry ID is server-generated.

Do not accept:

```json id="7x8c2v"
{
  "id": "..."
}
```

as the customer-created identity.

---

# 108. Enquiry Reference

Evaluate whether the business needs a customer-facing reference such as:

```text id="v4m2g8"
ENQ-*****
```

If not required, use the resource ID only.

Do not invent a reference format solely because Orders have `OD-*****`.

---

# 109. Order Reference vs Enquiry Reference

Do not confuse:

```text id="q8n4m1"
OD-*****
```

with an Enquiry identifier.

Order references represent transactions.

Enquiry references represent communication.

---

# 110. Enquiry Query Parameters

Staff collection may use:

```text id="7p2q6v"
search
status
product
order
created_from
created_to
sort
sort_direction
page
per_page
```

Only approve the fields that operationally matter.

---

# 111. Enquiry Search Authorization

Search must be constrained to the Staff/Admin authorized dataset.

Do not expose a global customer database search through the Enquiry API.

---

# 112. Enquiry Pagination

Use the global pagination convention:

```text id="x7m9z0"
page
per_page
current_page
last_page
total
```

where pagination applies.

---

# 113. Enquiry Error Registry

Add only necessary codes to the central registry.

Potential:

```text id="f4g8n3"
INVALID_VALUE
MISSING_REQUIRED_FIELD
RESOURCE_NOT_FOUND
FORBIDDEN
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
```

Avoid an unnecessary `ENQUIRY_ERROR` catch-all.

---

# 114. Enquiry Validation Flow

Conceptually:

```text id="3g6s1q"
Request received
   ↓
Content/type valid
   ↓
Schema valid
   ↓
Authentication context identified if present
   ↓
Contact information valid
   ↓
Optional Product validated
   ↓
Optional Order validated + ownership
   ↓
Attachment validated
   ↓
Enquiry created
   ↓
Notification event later
```

---

# 115. Anonymous Validation

Anonymous submissions must validate:

```text id="w8k2v5"
contact
subject
message
optional references
attachment
```

without requiring a User account.

---

# 116. Customer Validation

Authenticated submissions may use trusted account identity.

The backend should still validate explicit contact/message input.

---

# 117. Staff Validation

Staff operations must be validated for:

```text id="h7p6z2"
valid Enquiry
valid state
authorized action
```

---

# 118. Admin Validation

Admin actions must still respect:

```text id="m4r6q8"
valid target
valid state
authorized capability
```

---

# 119. Security Threat Model

Explicitly review:

```text id="v5x4c8"
anonymous spam
IDOR
customer-to-customer access
staff privilege escalation
role tampering
Order association leakage
private attachment exposure
internal note leakage
XSS through message content
oversized messages
oversized attachments
search abuse
rate-limit bypass
```

---

# 120. Security Rule — Anonymous IDs

Never treat a public Enquiry ID as proof that the requester is the owner.

This is especially important if IDs are sequential.

---

# 121. Security Rule — Order Association

An Enquiry referencing:

```text id="s6g8h0"
Order OD-123
```

must not give an unauthorized Customer access to:

```text id="9v3h1a"
Order OD-123
```

---

# 122. Security Rule — Product Association

Product association may be public because Product information is public.

However, Staff/Admin-only Product data must never leak through Enquiry responses.

---

# 123. Security Rule — Internal Notes

Customer responses must never include:

```text id="g0x7j9"
staff_notes
admin_notes
internal_tags
```

---

# 124. Security Rule — Credentials

Never expose:

```text id="y3j8p4"
password
password_hash
tokens
session secrets
```

through Enquiry resources.

---

# 125. Security Rule — Staff Cannot Restrict Customer

Staff handling an Enquiry cannot use it as a mechanism to:

```text id="n7k2m6"
block customer ordering
block customer browsing
change customer role
change customer password
```

---

# 126. Admin Authority

Admin has higher authorization but should still use explicit account/security operations for sensitive actions.

Do not build an arbitrary "manage everything through Enquiry" capability.

---

# 127. Enquiry and Customer Experience

The design should remain frictionless:

```text id="q4v6m9"
No registration required
No checkout required
No payment required
No staff approval required to submit
```

---

# 128. Enquiry and Normal Ecommerce Operations

Staff should be able to handle enquiries during ordinary ecommerce work without needing a specialized CRM.

The API should remain small.

---

# 129. Enquiry and Future Messaging

The architecture should not prevent a future:

```text id="x9t4p7"
Conversation
Message
Reply
```

system.

But do not implement it in Version 1 merely because it might be useful later.

---

# 130. Required Documentation Updates

## `docs/api/api-contract.md`

Add:

```text id="m8y6g2"
## General Enquiry API Contract

### Enquiry Resource
### ENQ-001 Create Enquiry
### ENQ-002 Customer Enquiry List
### ENQ-003 Customer Enquiry Detail
### ENQ-004 Staff Enquiry List
### ENQ-005 Staff Enquiry Detail
### Attachments
### Optional Product Association
### Optional Order Association
### Anonymous Submission
### Customer Ownership
### Staff Operations
### Admin Operations
### Status
### Privacy
### Validation
### Errors
### Abuse Protection
```

---

## `docs/api/api-resources.md`

Update:

```text id="t3j8n4"
Enquiry
Enquiry Attachment
```

with:

```text id="s7x2p6"
ownership
relationships
representations
field exposure
mutable/immutable fields
```

---

## `docs/api/api-conventions.md`

Add reusable Enquiry conventions only where they apply beyond Enquiry:

```text id="f9x4w0"
anonymous public creation
owner-scoped private reads
attachment inheritance
immutable original submissions
```

---

## `docs/domain/business-rules.md`

Confirm:

```text id="a7c5y1"
Anonymous users may submit enquiries.
Customers may submit enquiries.
Authenticated enquiries belong to the submitting Customer.
Enquiries are not Orders.
Enquiries do not initiate payment.
Enquiries do not reserve inventory.
Attachments are optional.
Staff handle enquiries operationally.
Staff do not control customer account access.
Admins provide administrative oversight.
```

---

## `docs/decisions.md`

Record important decisions such as:

```text id="h2m6q9"
### ENQ-001 — Anonymous Enquiry Submission

### ENQ-002 — Authenticated Enquiry Ownership

### ENQ-003 — Enquiries Are Separate From Made-to-Order Requests

### ENQ-004 — Enquiries Do Not Create Orders

### ENQ-005 — Enquiries Do Not Initiate Payment

### ENQ-006 — Enquiry Attachments Are Optional

### ENQ-007 — Anonymous Enquiry Retrieval Is Not Supported Initially

### ENQ-008 — Original Enquiry Message Is Immutable
```

Only record decisions actually accepted.

---

# 131. Required Endpoint Matrix

Final matrix should resemble:

| ID      | Method | Path                             | Auth     | Actor              | Authorization | Purpose            |
| ------- | ------ | -------------------------------- | -------- | ------------------ | ------------- | ------------------ |
| ENQ-001 | POST   | `/api/v1/enquiries`              | Optional | Anonymous/Customer | Public create | Submit enquiry     |
| ENQ-002 | GET    | `/api/v1/me/enquiries`           | Required | Customer           | Own           | List own enquiries |
| ENQ-003 | GET    | `/api/v1/me/enquiries/{enquiry}` | Required | Customer           | Own           | View own enquiry   |
| ENQ-004 | GET    | `/api/v1/enquiries`              | Required | Staff/Admin        | Operational   | Enquiry queue      |
| ENQ-005 | GET    | `/api/v1/enquiries/{enquiry}`    | Required | Staff/Admin        | Operational   | Enquiry detail     |

---

# 132. Required Field Exposure Matrix

| Data              |               Anonymous | Customer |       Staff |      Admin |
| ----------------- | ----------------------: | -------: | ----------: | ---------: |
| Subject           |               Own input |      Own |         Yes |        Yes |
| Message           |          Own submission |      Own |         Yes |        Yes |
| Customer contact  |               Own input |      Own | Operational | Authorized |
| Product reference |                  Public |      Own |         Yes |        Yes |
| Order reference   |     N/A/own association |      Own | Operational | Authorized |
| Attachments       | No retrieval by default |      Own | Operational | Authorized |
| Internal notes    |                      No |       No |         Yes |        Yes |
| Credentials       |                      No |       No |          No |         No |

---

# 133. Required Status Matrix

If the minimal `OPEN/CLOSED` model is approved:

| State        | Customer |       Staff |       Admin |
| ------------ | -------: | ----------: | ----------: |
| OPEN         | Read own | Read/manage | Read/manage |
| CLOSED       | Read own |        Read | Read/manage |
| Change state |       No |         Yes |         Yes |

Do not include this matrix if a status model is deliberately deferred.

---

# 134. Required Security Tests

Future automated tests must include:

```text id="7b5n2m"
Anonymous enquiry creation succeeds
Anonymous enquiry cannot be retrieved by ID
Customer can retrieve own enquiry
Customer cannot retrieve another customer's enquiry
Customer cannot submit another customer's user_id
Customer cannot associate another customer's Order
Customer cannot change status
Staff can retrieve operational enquiry
Staff cannot access credentials
Staff cannot alter customer role
Staff cannot block customer ordering
Admin can perform authorized administrative operation
Internal notes are invisible to customer
Attachments remain protected
Malicious message content is safely handled
Oversized requests are rejected
Rate limiting applies later
```

---

# 135. Cross-Platform Review

### Next.js

Must support:

```text id="s6j4p1"
anonymous enquiry form
authenticated enquiry form
confirmation
customer enquiry history if implemented
```

### Flutter

Must support the same contract:

```text id="a4h7p8"
anonymous enquiry
authenticated enquiry
own enquiry history
```

### Staff/Admin

Must support:

```text id="k9x2f4"
operational enquiry queue
detail
status/handling where approved
```

Do not create separate mobile/web enquiry APIs.

---

# 136. Performance Review

The likely high-volume endpoint is:

```text id="r5m7z2"
POST /enquiries
```

for anonymous submissions.

Operational list:

```text id="w3j8x5"
GET /enquiries
```

should be paginated.

Private Customer history should be efficiently scoped by owner.

Do not design indexes here.

---

# 137. Cache Review

Classification:

```text id="c4s8q1"
POST /enquiries
→ non-cacheable

Customer enquiry reads
→ private

Staff enquiry reads
→ private/internal
```

Never public-cache private enquiry data.

---

# 138. No Public Enquiry Search

Do not expose:

```text id="v4g6x8"
GET /enquiries?search=...
```

to anonymous users.

Staff/Admin only.

---

# 139. No Public Enquiry Detail

Do not expose:

```text id="p8x2c6"
GET /enquiries/{id}
```

to anonymous users.

This is a critical privacy rule.

---

# 140. No Enquiry-to-Order Shortcut

Do not allow a customer to submit:

```json id="h6s9k3"
{
  "order_status": "..."
}
```

or:

```json id="d7f1m4"
{
  "create_order": true
}
```

through Enquiry.

Enquiry is communication, not transaction execution.

---

# 141. No Enquiry-to-Payment Shortcut

Do not include:

```text id="n4m8p7"
payment
amount
provider
transaction
```

in the Enquiry creation model.

---

# 142. No Customer Account Restriction Through Enquiry

The Enquiry API must not become a backdoor for Staff account control.

---

# 143. Definition of Done

Phase 1.26 is complete when:

1. General Enquiry is defined as a first-class Version 1 resource.
2. Anonymous submission is explicitly supported.
3. Authenticated Customer submission is explicitly supported.
4. Authenticated Enquiries are customer-owned.
5. Customer retrieval is ownership-scoped.
6. Anonymous retrieval is explicitly prohibited or given a secure mechanism.
7. Staff operational access is defined.
8. Admin access is defined.
9. Enquiry is separate from Made-to-Order Request.
10. Enquiry is separate from Order.
11. Enquiry does not create an Order.
12. Enquiry does not initiate Payment.
13. Product association is optional if approved.
14. Order association is optional if approved.
15. Order associations enforce ownership.
16. Attachments are optional.
17. Attachment authorization is defined.
18. Original customer message is immutable.
19. Internal Staff/Admin notes are separated from customer content.
20. Enquiry status is either explicitly defined or deliberately deferred.
21. Status transitions are controlled actions if status exists.
22. Anonymous spam/abuse is recognized.
23. Rate-limit requirements are identified.
24. Idempotency considerations are identified.
25. Private enquiry data cannot enter public cache.
26. Staff cannot use Enquiry access to restrict customer browsing/order access.
27. Sensitive credentials are never exposed.
28. Common response/input/error contracts are reused.
29. Version 1 enums remain CLOSED.
30. Email remains deferred to **Phase Group R**.
31. Payment remains assigned to **Phase Group H**.
32. Next.js and Flutter requirements are covered.
33. Consolidated documentation is updated.
34. No implementation code has been written.

---

# 144. Explicitly Out of Scope

Do NOT:

```text id="a6j9v3"
Implement Laravel Enquiry model
Create migrations
Create controllers
Create policies
Implement rate limiting
Implement CAPTCHA
Implement file storage
Implement attachment scanning
Implement email
Implement notifications
Implement in-app messaging
Implement staff chat
Implement CRM
Implement customer support ticketing
Implement Request-to-Enquiry conversion
Implement Enquiry-to-Order conversion
Implement payment
Build Next.js enquiry UI
Build Flutter enquiry UI
Build Staff enquiry UI
Generate final OpenAPI schemas
```

---

# 145. STOP CONDITION — Mandatory

After updating:

```text id="2k4f7m"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the privacy/security/workflow review:

**STOP.**

Do not implement Enquiries.

Do not implement messaging.

Do not implement email.

Do not implement notifications.

Do not build CRM functionality.

Do not implement payment.

The next phase must be explicitly requested.

### Recommended next phase

# Phase 1.27 — Define Notification API Contract

This phase should define the notification system that connects the business events already established:

```text id="7j2m8v"
Order created
Payment event
Order accepted
Order processing
Ready for Pickup
Shipped
Delivered
Request received
Enquiry received
Staff operational alerts
```

to the appropriate recipients:

```text id="r6f4y1"
CUSTOMER
STAFF
ADMIN
```

while respecting the existing decision that **real email delivery is deferred to Phase Group R** and ensuring notifications never become the source of truth for Order, Payment, Request, or Enquiry state.
