# Phase 3.15 — Enquiry Schema

## Purpose

Implement the persistence model for **General Enquiries**.

An Enquiry is a private communication from a customer or guest to the business.

Examples include:

* questions about furniture
* material questions
* product questions
* bulk or office furniture enquiries
* questions about an existing Order
* general business/contact questions
* questions that do not qualify as a Furniture Request

An Enquiry is **not**:

* a Furniture Request
* an Order
* a Payment
* an Inventory reservation
* a quotation
* a delivery request

The existing V1 contract explicitly keeps Enquiries separate from Requests, Orders, Payments, and Inventory.

Both anonymous and authenticated submissions are supported.

```text
Guest
  │
  └── Enquiry (user_id = null)

Authenticated Customer
  │
  └── Enquiry (user_id = authenticated principal)
```

This phase establishes the persistence model and constraints required before Group J implements Enquiry validation, authorization, API handling, and operational workflows.

---

# Dependencies

Complete these phases first:

* Phase 3.3 — Categories Schema
* Phase 3.4 — Products Schema
* Phase 3.9 — Orders Schema
* Phase 3.10 — Order Items Snapshot Model
* Phase 3.12 — Payment Schema
* Phase 3.14 — Furniture Request Schema

Do not make Enquiry dependent on Furniture Requests, Payments, or Deliveries.

An Enquiry may optionally reference a Product or Order, but it remains its own domain entity.

---

# Authoritative Inputs

Treat these as authoritative:

* `docs/VISION.md`
* `docs/domain/business-rules.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `AGENTS.md`
* completed Phase 3.3 Categories
* completed Phase 3.4 Products
* completed Phase 3.9 Orders
* completed Phase 3.10 Order Items
* completed Phase 3.12 Payment
* completed Phase 3.14 Furniture Request

The Version 1 API contract is frozen. Do not introduce new public Enquiry states, new relationships, or breaking request shapes in this phase.

---

# 1. Core Design Rule

The `enquiries` table is a **self-contained historical communication record**.

It must preserve the original customer-submitted:

* contact
* subject
* message
* optional Product context
* optional Order context

independently of subsequent changes to:

* User profile
* Product
* Order

The original Enquiry must remain meaningful even when referenced entities change.

Do not automatically synchronize historical Enquiry content from current catalog or profile data.

---

# 2. Create `enquiries` Table

Create a Laravel migration for:

`enquiries`

Recommended schema:

| Column                 | Type                                       | Rules                                                  |
| ---------------------- | ------------------------------------------ | ------------------------------------------------------ |
| `id`                   | big integer / Laravel standard primary key | Internal primary key                                   |
| `user_id`              | nullable foreign key                       | Authenticated Customer; null for Guest; null on delete |
| `enquiry_reference`    | string                                     | Server-generated unique reference                      |
| `product_id`           | nullable foreign key                       | Optional Product context; null on delete               |
| `order_id`             | nullable foreign key                       | Optional Order context; restrict on delete             |
| `name`                 | string                                     | Required contact snapshot                              |
| `email`                | nullable string                            | Contact snapshot                                       |
| `phone`                | nullable string                            | Contact snapshot                                       |
| `subject`              | string                                     | Required                                               |
| `message`              | text                                       | Required                                               |
| `enquiry_status`       | string                                     | Required; default `OPEN`                               |
| `staff_internal_notes` | nullable text                              | Staff/Admin operational notes                          |
| `created_at`           | timestamp                                  | Required                                               |
| `updated_at`           | timestamp                                  | Required                                               |

Do not add payment, delivery, inventory, or request-status fields.

---

# 3. Guest and Authenticated Submission

One table must support both.

## Guest

```text id="j4g9yf"
user_id = null
```

The guest's contact details are stored directly in the Enquiry.

## Authenticated Customer

```text id="quy9b0"
user_id = authenticated user's ID
```

The contact snapshot is still stored on the Enquiry.

Do not require a User record for anonymous creation.

The existing contract explicitly allows anonymous Enquiry creation and authenticated creation through the same domain.

---

# 4. `user_id` Is Server-Controlled

Never accept:

```json id="7s8a4e"
{
  "user_id": "..."
}
```

from the client.

For authenticated creation:

```text id="k0cf10"
authenticated principal
→ user_id
```

For anonymous creation:

```text id="i9fm4v"
user_id = null
```

The existing conventions require this server-derived ownership model.

---

# 5. Contact Snapshot

Persist:

* `name`
* `email`
* `phone`

directly on the Enquiry.

This makes the Enquiry self-contained.

A customer's later profile change must not rewrite historical Enquiry contact information.

Do not dynamically substitute current profile information when reading historical enquiries.

For authenticated customers, account identity is represented by `user_id`, while the stored contact remains the original submitted snapshot. The existing contract explicitly describes this pattern.

---

# 6. Contact Validation Preparation

Prepare the schema for Group J validation.

## `name`

* required
* string
* trimmed
* bounded
* Unicode-safe

## `email`

* nullable
* valid email syntax when supplied
* normalized appropriately
* bounded length

## `phone`

* nullable
* stored as string
* normalized appropriately
* bounded length

The existing frozen Enquiry contract requires:

```text id="2x5ig9"
name
+
at least one of phone or email
```

for anonymous creation.

Authenticated requests may derive account contact information according to the existing contract.

Do not change that requirement in Phase 3.15.

---

# 7. `enquiry_reference`

Create:

`enquiry_reference`

as a server-generated unique business reference.

Requirements:

* unique
* immutable
* server-generated
* never client supplied
* not raw database ID
* independent from Order references

Do not reuse:

```text id="z3fksr"
order_reference
```

An Enquiry is not an Order.

---

# 8. Subject

`subject` is required for a General Enquiry.

Store it directly on the Enquiry.

Recommended constraints:

* required
* string
* trimmed
* bounded maximum length
* plain text
* Unicode-safe

Do not allow arbitrary HTML as trusted input.

The Enquiry contract explicitly identifies `subject` as part of Enquiry contact/content.

---

# 9. Message

`message` is the primary communication body.

Recommended constraints:

* required
* string/text
* trimmed where appropriate
* bounded maximum size
* Unicode-safe
* treated as plain text

Do not interpret customer message content as executable HTML, SQL, templates, or code.

Do not overwrite the original message with Staff responses.

The original enquiry must remain preserved. The existing operational rules explicitly require customer-provided fields to remain immutable.

---

# 10. Product Context

`product_id` is optional.

An Enquiry may concern a specific catalog Product.

When supplied, the later Group J validation layer should verify that the Product exists and is addressable according to the applicable catalog rules.

Do not make Product mandatory.

An enquiry such as:

> "What materials do you use for office desks?"

does not require a Product reference.

Use:

```text id="12b9be"
product_id = null
```

for general enquiries.

---

# 11. Product Reference Is Context, Not Ownership

`product_id` does not make the Enquiry a Product child record.

It simply records context.

Do not allow Product deletion to delete the Enquiry.

Use:

```text id="n2f3k6"
product_id → nullOnDelete
```

This preserves the customer's communication even if the referenced Product is retired or removed.

---

# 12. Historical Product Context

The existence of `product_id` alone is not enough to preserve the complete historical Product representation.

For V1:

* `product_id` provides traceability
* the original `subject` and `message` remain the authoritative customer communication
* current Product data may change independently

Do not create a Product snapshot JSON structure unless the frozen Enquiry contract actually requires it.

Avoid duplicating the complete Product model unnecessarily.

---

# 13. Order Context

`order_id` is optional.

An Enquiry may concern a customer's existing Order.

Examples:

* "Can I change my delivery details?"
* "I have a question about this order."
* "When can I collect my order?"

When supplied, the later Group J validation layer must ensure the Order exists and that the sender has the appropriate relationship/authorization to use that context where required.

Do not assume that knowing an Order reference grants access to the Order.

---

# 14. Order Reference Security

Do not use:

```text id="11kr93"
order_id
```

as a client-authorized ownership mechanism.

The Order association is contextual data.

Authorization must later be evaluated through the authenticated relationship and appropriate operational rules.

The project-wide authorization model requires ownership checks through relationships rather than client-controlled IDs.

---

# 15. `order_id` Delete Behavior

Do not cascade-delete Enquiries when an Order is deleted.

Use restrictive semantics:

```text id="d3jzxk"
order_id → RESTRICT
```

This protects the relationship from accidental destruction.

Normal Orders are historical records and should not be hard-deleted in ordinary application operation.

---

# 16. Enquiry Is Not an Order

Do not allow the presence of `order_id` to transform the Enquiry into an Order operation.

An Enquiry remains communication.

Do not add:

* order status
* payment status
* delivery status
* total
* amount
* cancellation state

to Enquiry.

The existing contract explicitly states that Enquiries do not create, modify, or guarantee Orders, Payments, or Inventory.

---

# 17. Enquiry Is Not a Furniture Request

Do not merge Enquiry with `furniture_requests`.

Examples:

### Furniture Request

```text
"I want a custom 3-seat sofa made in dark grey."
```

### Enquiry

```text
"Do you have sofas available in velvet?"
```

The domains remain separate.

Do not add:

```text id="z9r7gf"
request_id
```

to Enquiry.

The frozen contract explicitly states there is no automatic Request ↔ Enquiry merge.

---

# 18. Request Status

Use the approved V1 Enquiry lifecycle:

```text id="hwoapd"
OPEN
CLOSED
```

Default:

```text id="h5j8p8"
OPEN
```

The approved lifecycle is:

```text id="9cf5ge"
OPEN → CLOSED
```

The existing contract describes this as intentionally minimal; `ASSIGNED`, `IN_PROGRESS`, and similar states are not introduced without explicit justification.

Do not add:

```text
NEW
PENDING
IN_PROGRESS
RESOLVED
ARCHIVED
```

unless separately approved through the frozen-contract process.

---

# 19. Status Is Server-Controlled

Customers must not submit:

```json id="v9h9b4"
{
  "enquiry_status": "CLOSED"
}
```

as part of ordinary Enquiry creation.

Later Staff operations may change status through explicit controlled actions.

Do not implement those actions in Phase 3.15.

Do not create a generic:

```text
PATCH /enquiries/{enquiry}
{"enquiry_status":"CLOSED"}
```

workflow in this phase.

---

# 20. Staff Internal Notes

Add:

`staff_internal_notes`

as an operational-only field.

Requirements:

* nullable
* bounded text
* Staff/Admin only
* not customer-visible
* not part of original customer communication
* not accepted during ordinary customer creation
* never serialized to customers

The existing Enquiry rules explicitly allow Staff to manage `enquiry_status` and `staff_internal_notes` while preserving the customer's original fields.

Do not call the field simply `notes`, because that would make customer/internal semantics ambiguous.

---

# 21. Original Customer Content Must Remain Immutable

The following are customer-submitted historical data:

* name
* email
* phone
* subject
* message
* product_id
* order_id

Do not allow normal Staff handling to overwrite them.

Staff operational notes belong in `staff_internal_notes`.

This preserves the original communication and supports auditability.

---

# 22. Attachments

Attachments are optional and must not be stored directly in the `enquiries` record.

Use the established separate attachment architecture when Group J implements it.

The frozen contract already defines:

* optional Enquiry attachments
* separate `POST /enquiries/{enquiry}/attachments`
* secure scoped upload authorization
* private storage
* temporary signed URLs
* file-size/type/content-signature validation

Do not duplicate an `attachment_path` or binary field inside `enquiries`.

Do not implement attachment upload in Phase 3.15 unless the attachment schema itself is explicitly part of the current repository architecture.

---

# 23. Attachment Security Preparation

Group J must eventually validate attachments using:

* file size limit
* allow-listed content types
* actual file signature
* sanitized filenames
* authorized storage
* scoped upload token for anonymous upload
* ownership/operational authorization

The current V1 contract specifically establishes a scoped upload-token mechanism for anonymous attachments and prohibits permanent public storage URLs.

Phase 3.15 only needs to preserve the parent-child relationship required by that future implementation.

---

# 24. Anonymous Enquiry Access

Anonymous users may **submit** an Enquiry.

Do not assume they can later retrieve it merely because they know:

* the numeric database ID
* the Enquiry reference
* their email
* their phone

The existing contract explicitly rejects predictable-ID anonymous retrieval and does not treat email as ownership proof.

Any later anonymous retrieval mechanism must use an explicit secure mechanism.

Do not create such a mechanism in this phase.

---

# 25. Authenticated Customer Access

For authenticated customers:

```text id="qud46s"
Enquiry.user_id = authenticated principal
```

The customer may later retrieve only their authorized Enquiries.

Customer A must not be able to retrieve Customer B's Enquiry.

The existing API conventions require 404-style masking where necessary to avoid an existence oracle.

Do not implement those APIs in Phase 3.15.

---

# 26. Staff Access

Staff access is operational, not ownership.

A Staff user may later access Enquiries according to explicit operational permissions.

Do not model Staff as the Enquiry owner.

Do not add:

```text id="an1jmj"
staff_id
```

just to represent operational access.

Authorization should be handled through the role/permission model established elsewhere.

---

# 27. Admin Access

Admin may have broader Enquiry access according to explicit authorization rules.

Do not encode:

```text id="j2p29m"
is_admin
```

in the Enquiry table.

Use the project's RBAC and authorization model.

Administrative access must still follow data-minimization and auditability requirements.

---

# 28. Privacy

Enquiries are private.

Do not expose Enquiry data through:

* public Product APIs
* public search
* catalog pages
* SEO metadata
* anonymous listing endpoints
* public CDN caching

The existing contract classifies Enquiries as private and explicitly prohibits embedding them into the public catalog.

---

# 29. Serialization

Never rely on:

```text id="xvuhj1"
$model->toArray()
```

for Enquiry API responses.

Later Group J serializers must use explicit allow-lists.

Customer representation may contain only the customer's permitted Enquiry fields.

Staff/Admin representations may contain operational information such as `staff_internal_notes` when authorized.

Internal attachment/storage details must never be exposed by default.

---

# 30. No Sensitive Credential Fields

Do not add:

* password
* authentication token
* reset token
* session token
* API key
* payment credential
* provider secret

to Enquiry.

Contact details are communication data, not credentials.

---

# 31. No Financial Fields

Do not add:

* amount
* price
* quoted_price
* payment_status
* currency
* delivery_fee
* total

An Enquiry does not establish a commercial price or payment obligation.

The existing contract explicitly excludes payment/amount creation from Enquiry behavior.

---

# 32. No Delivery Fields

Do not add:

* delivery_fee
* delivery_status
* tracking_number
* delivery_id

A customer can mention delivery in the message, but that does not make the Enquiry a Delivery object.

---

# 33. No Inventory Fields

Do not add:

* stock quantity
* reserved quantity
* inventory ID
* allocation
* warehouse location

An Enquiry never reserves or modifies inventory.

---

# 34. Validation-Ready Database Design

Prepare the schema so Group J can enforce the validation sequence:

```text id="m9q6o6"
Transport
→ Schema/Input
→ Authentication (optional)
→ Authorization
→ Domain
→ Concurrency
→ Persistence
```

The database should enforce structural integrity.

Group J must enforce business meaning.

Examples:

### Database

* foreign keys
* nullability
* uniqueness
* indexes

### Group J

* `name` rules
* contact requirements
* subject/message bounds
* Product/Order validity
* ownership
* request context authorization
* request status transitions
* anti-abuse/rate limiting

The project's validation conventions explicitly distinguish structural validation from business/domain validation.

---

# 35. Unknown Fields

Group J's create/update inputs must use strict allow-lists.

Customer submission must not accept:

```text
user_id
enquiry_status
staff_internal_notes
payment_status
amount
order_status
```

or arbitrary undocumented fields.

Unknown fields must be rejected according to the project's strict input policy.

---

# 36. Duplicate Enquiry Handling

Do not create hard uniqueness constraints such as:

```text id="2idjz7"
email + message
phone + message
email + subject
product_id + email
```

Two legitimate enquiries may have identical or very similar content.

The existing conventions deliberately avoid simplistic duplicate detection for communication submissions.

Abuse/rate limiting belongs to the API/application layer.

---

# 37. Idempotency

Do not introduce an Enquiry-specific idempotency key column in this phase.

The existing V1 contract does not require `POST /enquiries` to be inherently idempotent.

The project currently treats anonymous Enquiry creation as a public mutation requiring abuse/rate-limit consideration rather than a database uniqueness workaround.

---

# 38. Indexes

At minimum add:

* unique index on `enquiry_reference`
* index on `user_id`
* index on `product_id`
* index on `order_id`
* composite index on `(enquiry_status, created_at)`
* index on `created_at`

This supports later:

```text
/me/enquiries
staff Enquiry queue
Product-context queries
Order-context queries
status filtering
newest-first sorting
```

The canonical V1 listing sort is:

```text id="g23i21"
created_at DESC, id ASC
```

with deterministic ordering.

Do not index large text fields simply because they exist.

---

# 39. Query and Search Preparation

The existing Staff Enquiry contract allows filtering/search over:

* name
* email
* phone
* subject
* message
* reference
* Product
* Order
* status
* date ranges

and requires the query to operate only over the authorized dataset.

Do not implement a search engine in Phase 3.15.

Do ensure the schema provides the normal indexed access paths needed for relational filters.

Full-text search, if eventually needed, should be evaluated separately based on actual scale.

---

# 40. Model Design

Create:

`Enquiry`

Eloquent model.

Relationships:

* `belongsTo(User::class)` nullable
* `belongsTo(Product::class)` nullable
* `belongsTo(Order::class)` nullable

Use explicit casts only where needed.

Use a dedicated enum/value representation for:

```text id="4cyybh"
OPEN
CLOSED
```

Do not place status-transition workflows inside the model.

Keep business orchestration in the application/domain layer.

---

# 41. Delete Behavior

Recommended relationship behavior:

### User

```text id="gbo7yi"
nullOnDelete
```

The Enquiry survives account deletion.

### Product

```text id="1uel81"
nullOnDelete
```

The Enquiry survives Product deletion.

### Order

```text id="j7nizu"
restrictOnDelete
```

The Enquiry must not be accidentally detached from a historical Order through casual deletion.

The exact parent deletion semantics must remain consistent with the project's existing historical-record policy.

---

# 42. Historical Integrity

After creation:

* changing the customer's profile must not rewrite Enquiry contact snapshot
* changing Product data must not rewrite original Enquiry content
* changing Order data must not rewrite the message/subject

Only deliberate operational fields may later change.

At minimum:

```text id="4am9m7"
enquiry_status
staff_internal_notes
```

belong to operational handling.

The customer-provided communication remains preserved.

---

# 43. Staff Internal Notes Security

If `staff_internal_notes` is implemented directly on the table:

* never allow customer creation to populate it
* never serialize it to Customer responses
* never expose it through public endpoints
* only allow authorized Staff/Admin workflows to change it
* keep it separate from the customer's original `message`

This preserves a clean security boundary between external communication and internal operations.

---

# 44. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constant class.

Prefer domain-local enums/value objects.

Do not suppress static-analysis findings or increase thresholds.

Keep migrations, models, factories, and tests small and cohesive.

---

# 45. Tests

Add automated tests for the persistence and domain-supporting invariants.

## Migration/schema tests

Verify:

* `enquiries` table exists
* primary key exists
* `enquiry_reference` is unique
* `user_id` is nullable
* `product_id` is nullable
* `order_id` is nullable
* `name` exists
* `email` is nullable
* `phone` is nullable
* `subject` exists
* `message` exists
* `enquiry_status` exists
* `staff_internal_notes` exists if implemented
* timestamps exist

## Guest tests

Verify an Enquiry can persist with:

```text id="k40z17"
user_id = null
```

and valid contact information.

## Authenticated tests

Verify an Enquiry can persist with:

```text id="u9k2j8"
user_id = authenticated customer
```

while retaining the contact snapshot.

## Ownership tests

Verify:

* authenticated Enquiry points to the authenticated User
* anonymous Enquiry has `user_id = null`
* there is no email-based ownership assignment

## Product context tests

Verify:

* Product is optional
* Product deletion nulls `product_id`
* Enquiry itself remains
* Enquiry content remains intact

## Order context tests

Verify:

* Order is optional
* Enquiry may reference an Order
* Order deletion does not cascade-delete the Enquiry
* relationship behavior follows the intended restrictive policy

## Status tests

Verify:

* default is `OPEN`
* only `OPEN` and `CLOSED` are valid
* no arbitrary status value can be persisted through the intended application/domain path

## Content tests

Verify:

* name persistence
* email persistence
* phone persistence
* subject persistence
* message persistence
* reasonable field bounds

## Historical integrity tests

After creating an Enquiry:

* modify User profile
* modify Product
* modify Order

and verify the original Enquiry contact/content remains unchanged.

## Privacy tests

Verify Staff-only internal notes are not part of the customer-safe representation.

## Duplicate tests

Verify two legitimate enquiries with identical contact/message data can coexist.

No artificial duplicate uniqueness rule should reject them.

---

# 46. Factories

Create:

`EnquiryFactory`

Support fixtures for:

* guest enquiry
* authenticated customer enquiry
* Product-linked enquiry
* Order-linked enquiry
* general enquiry
* `OPEN`
* `CLOSED`
* internal Staff note

Factory defaults must produce valid contact and message data.

Do not automatically create:

* Order
* Payment
* Furniture Request
* Delivery

unless a specific test explicitly needs those relationships.

---

# 47. Documentation Updates

Update the authoritative documentation where necessary to preserve these rules:

* Guests may submit Enquiries
* authenticated Customers may submit Enquiries
* `user_id` is server-derived
* contact information is snapshotted
* Product and Order context are optional
* Enquiry is private
* Enquiry is not Request/Order/Payment/Inventory
* `OPEN → CLOSED` is the V1 status lifecycle
* customer content remains preserved
* Staff notes are separate
* anonymous retrieval requires an explicit secure mechanism
* attachments remain optional and privately authorized

Do not create a permanent phase-specific document only for this implementation.

---

# 48. Security and Data Integrity Review

Before completion, verify:

* anonymous creation is representable without fake User accounts
* authenticated ownership is server-derived
* client cannot set `user_id`
* client cannot set `enquiry_status`
* client cannot set `staff_internal_notes`
* email is not treated as authentication
* Product deletion cannot destroy the Enquiry
* Order relationships do not cascade-delete Enquiries
* original customer communication is preserved
* Staff notes are private
* Enquiries are not publicly cacheable
* no payment secrets or credentials exist in the schema
* no financial/order-status fields have been introduced
* no generic unrestricted Enquiry CRUD path has been created
* attachment design remains compatible with scoped authorization

---

# Definition of Done

Phase 3.15 is complete when:

* `enquiries` migration exists
* both guest and authenticated submissions are representable
* `user_id` is nullable and server-derived
* request/contact identity is stored as a historical snapshot
* unique server-generated `enquiry_reference` exists
* required `subject` and `message` are stored
* optional Product context is supported
* optional Order context is supported
* request status uses closed `OPEN` / `CLOSED`
* default status is `OPEN`
* Staff internal notes are separate from customer content
* User deletion preserves the Enquiry
* Product deletion preserves the Enquiry
* Order relationship uses restrictive deletion semantics
* relevant indexes exist
* Eloquent relationships are implemented
* factories support guest/authenticated/product/order-context fixtures
* schema, relationship, privacy, status, historical-integrity, and deletion tests pass
* maintainability requirements are satisfied
* the model is ready for strict Group J validation and authorization

# Out of Scope

Do not implement in Phase 3.15:

* `POST /enquiries`
* Enquiry Form Requests
* DTOs/Commands
* authentication
* customer authorization policies
* Staff authorization policies
* anonymous secure retrieval
* Enquiry status action endpoints
* Staff Enquiry-management API
* attachment uploads
* upload tokens
* file scanning
* rate limiting
* CAPTCHA
* email/SMS notifications
* response/reply messaging system
* quotation
* Order creation
* Payment
* Delivery
* Inventory
* Enquiry → Order conversion
* Request → Enquiry conversion

# STOP CONDITION

Stop after the Enquiry persistence model, relationships, constraints, factories, tests, and migration verification are complete.

Do not implement Group J Enquiry API validation or operational workflows yet.

The next phase is:

**Phase 3.16 — Notification Schema**
