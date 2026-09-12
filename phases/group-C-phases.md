# Phase 3.14 — Furniture Request Schema

## Purpose

Implement the persistence model for **Furniture Requests**.

A Furniture Request represents a customer's request for furniture that may require customization, modification, quotation, or further business discussion.

A request is **not an Order**.

A request:

* does not reserve inventory
* does not create an Order
* does not guarantee a price
* does not create a payment
* does not guarantee production
* does not guarantee delivery
* does not automatically convert into an Order

The existing contract explicitly keeps Furniture Requests separate from Orders, Payments, Inventory, and pricing commitments.

Both of these actors may submit a request:

```text
REGISTERED CUSTOMER
        │
        └── Furniture Request

GUEST
        │
        └── Furniture Request
```

For authenticated customers, `user_id` is derived from the authenticated principal.

For guests:

```text
user_id = null
```

The client must never submit or override `user_id`.

This phase establishes the database model and validation-ready structure for the later Group J API/workflow phases.

---

# Dependencies

Complete these phases first:

* Phase 3.3 — Categories Schema
* Phase 3.4 — Products Schema
* Phase 3.5 — Product Variants Schema
* Phase 3.6 — Product Images Schema
* Phase 3.9 — Orders Schema
* Phase 3.10 — Order Items Snapshot Model
* Phase 3.11 — Order Status History Schema
* Phase 3.12 — Payment Schema
* Phase 3.13 — Delivery Schema

Do not make Furniture Requests depend on Orders or Payments.

A request remains an independent domain object.

---

# Authoritative Inputs

Treat these as authoritative:

* `docs/VISION.md`
* `docs/domain/business-rules.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `AGENTS.md`
* completed phases 3.3–3.13

The existing V1 request conventions establish:

* anonymous creation
* authenticated customer creation
* server-derived `user_id`
* optional `product_id`
* structured request specifications
* bounded free-text fields
* closed request statuses
* private request data
* operational Staff/Admin handling
* no automatic Request → Order conversion

Do not silently replace those rules with a different lifecycle.

---

# 1. Core Design Rule

The `furniture_requests` table must be a **self-contained customer-submission record**.

The request should remain understandable even if the associated catalog Product later:

* changes name
* changes description
* changes images
* changes price
* is deactivated
* is deleted

A Product reference may be retained, but the submitted request details must not depend on the live Product record.

This follows the existing requirement that customer-submitted request data is historical/private and that later changes must not rewrite the original intake.

---

# 2. Create `furniture_requests` Table

Create a Laravel migration for:

`furniture_requests`

Recommended schema:

| Column              | Type                                       | Rules                                                             |
| ------------------- | ------------------------------------------ | ----------------------------------------------------------------- |
| `id`                | big integer / Laravel standard primary key | Internal primary key                                              |
| `user_id`           | nullable foreign key                       | Authenticated Customer owner; null for Guest; null on User delete |
| `request_reference` | string                                     | Server-generated unique reference                                 |
| `product_id`        | nullable foreign key                       | Optional catalog reference; null on Product delete                |
| `product_details`   | JSON/text-compatible structured field      | Required submitted product description/details                    |
| `style`             | string/text                                | Required requested furniture style                                |
| `name`              | string                                     | Required contact snapshot                                         |
| `email`             | string nullable                            | Contact snapshot; frozen V1 contract requires at least one of `email`/`phone` (§6) |
| `phone`             | string nullable                            | Contact snapshot; frozen V1 contract requires at least one of `email`/`phone` (§6) |
| `message`           | text                                       | Required customer message                                         |
| `quantity`          | unsigned integer nullable                  | Optional requested quantity                                       |
| `dimensions`        | JSON nullable                              | Optional structured dimensions                                    |
| `material`          | string nullable                            | Optional free-text preference                                     |
| `color`             | string nullable                            | Optional free-text preference                                     |
| `request_status`    | string                                     | Required; default `SUBMITTED`                                     |
| `created_at`        | timestamp                                  | Required                                                          |
| `updated_at`        | timestamp                                  | Required                                                          |

The fields `quantity`, `dimensions`, `material`, and `color` preserve the existing approved request capabilities.

The newly explicit `product_details` and `style` fields are included because they are part of the requested request structure.

---

# 3. Registered Customer vs Guest

Use one table for both request origins.

## Authenticated Customer

```text
user_id = authenticated user's ID
```

## Guest

```text
user_id = null
```

Do not create:

* `guest_user`
* `guest_customer`
* `customer_type`
* `is_guest`
* separate guest-request table

A nullable `user_id` is sufficient.

The existing contract explicitly defines this ownership model.

---

# 4. `user_id` Authority

`user_id` is completely server-controlled.

Never accept:

```json
{
  "user_id": "someone-else"
}
```

from the request body.

For an authenticated submission:

```text
authenticated principal → user_id
```

For an anonymous submission:

```text
user_id = null
```

The request's contact information remains stored independently of `user_id`.

This is important because the same request structure must work for guests and registered customers.

---

# 5. Contact Snapshot

Store:

* `name`
* `email`
* `phone`

directly on `furniture_requests`.

These are **request-time contact snapshots**, not dynamic references to the User profile.

Later changes to the customer's:

* name
* email
* phone

must not rewrite historical request contact information.

The existing request contract requires the request to remain self-contained and explicitly states that authenticated requests store contact information in addition to the server-derived `user_id`.

---

# 6. Contact Validation Preparation

The schema must support strong validation in Group J.

Recommended rules:

### `name`

* required
* string
* trimmed
* bounded maximum length
* Unicode-safe

### `email`

* required for the requested V1 structure
* valid email syntax
* normalized where appropriate
* bounded maximum length

### `phone`

* required for the requested V1 structure
* string, not numeric
* normalized to the project's chosen phone representation
* bounded maximum length

Do not store phone numbers as integers.

## Contract compatibility note

The existing frozen request convention previously defined:

```text
name = required
phone OR email = required
```

rather than requiring both contact channels.

This phase should therefore make the schema capable of storing all three fields while **Group J must reconcile the requested “name + email + phone” requirement with the frozen API contract before making both email and phone mandatory at the API boundary**.

Do not silently change the frozen V1 request contract in Phase 3.14.

---

# 7. `request_reference`

Create a server-generated unique reference:

`request_reference`

Requirements:

* unique
* immutable
* server-generated
* never client supplied
* not directly derived from an exposed database ID

Use the project's established opaque-reference convention.

Do not reuse an Order reference.

A Furniture Request is not an Order and must have its own reference namespace.

---

# 8. Product Reference

`product_id` remains optional.

This preserves the two approved request modes:

### Product-linked request

```text
product_id = existing MADE_TO_ORDER Product
```

### Custom/general request

```text
product_id = null
```

The existing V1 convention explicitly approves both modes.

Do not make `product_id` mandatory merely because `product_details` is present.

---

# 9. Product Validation

When `product_id` is supplied, Group J must validate:

1. Product exists
2. Product is active
3. Product is published where applicable
4. Product is a `MADE_TO_ORDER` product
5. Product is requestable

An `IN_STOCK` product must not be treated as a Furniture Request target merely because it has a valid ID.

The existing contract explicitly requires MADE_TO_ORDER validation and rejects request creation for an IN_STOCK product.

Do not attempt to enforce this entire rule through the foreign key.

---

# 10. Product Snapshot

Because the customer submits product details, preserve those submitted details independently from the current Product.

Recommended structure:

```json
{
  "product_name": "...",
  "description": "...",
  "reference": "..."
}
```

The exact keys must be finalized in Group J validation/API design.

The important rule is:

> `product_details` represents what the customer submitted, not a trusted copy of arbitrary Product database data.

Do not automatically fill `product_details` from the current Product model without deliberate snapshot semantics.

Do not allow arbitrary nested JSON.

---

# 11. Product Details Validation

`product_details` must be a **strict structured object** at the API boundary.

Group J must define an explicit allow-list.

Do not accept arbitrary JSON keys such as:

```text
anything
metadata
internal_price
secret
admin_note
```

Unknown keys must be rejected.

This follows the project's global rule that strict create inputs reject unknown fields and arbitrary nested structures.

The database may store the validated structured object as JSON, but Laravel/domain validation remains authoritative for nested structure.

---

# 12. Style

Add:

`style`

as a first-class request field.

The customer's requested style is part of the Furniture Request itself.

Examples may include:

* Modern
* Minimalist
* Scandinavian
* Classic
* Industrial
* Traditional
* Contemporary

Do **not** make Style a V1 closed enum unless the business has explicitly approved a complete taxonomy.

Use bounded free text for V1.

This is consistent with the existing request strategy of keeping customer furniture preferences such as `material` and `color` flexible rather than prematurely converting them into closed enums.

Recommended constraints:

* required
* trimmed
* Unicode-safe
* bounded maximum length
* plain text
* not interpreted as HTML/code

---

# 13. Message

Add:

`message`

as the customer's main request explanation.

This is separate from:

* `product_details`
* `style`
* `material`
* `color`
* `dimensions`

The message can describe:

* desired modifications
* intended use
* special requirements
* context
* questions
* additional preferences

Recommended maximum length:

```text
5000 characters
```

This follows the existing bounded free-text request convention.

Store the original customer message as submitted after safe normalization.

Do not overwrite it with Staff notes.

---

# 14. Customer Message Immutability

Original customer-submitted fields are historical intake.

Do not allow Staff to rewrite:

* name
* email
* phone
* product details
* style
* message
* quantity
* dimensions
* material
* color
* product reference

Operational Staff notes must be separate.

The existing Request convention explicitly states that customer-provided fields remain immutable and staff edits belong in separate internal fields.

---

# 15. Quantity

Preserve the existing optional:

`quantity`

field.

Rules:

* nullable
* integer
* minimum `1`
* maximum `100`
* never zero
* never negative
* never fractional
* server validated

Important:

`quantity` is **request intent**, not inventory or Order quantity.

It does not reserve stock and does not become the final Order quantity automatically.

When omitted:

```text
quantity = null
```

Do not silently default it to `1`.

---

# 16. Dimensions

Preserve the existing structured:

`dimensions`

field.

Recommended structure:

```json
{
  "length": 120,
  "width": 60,
  "height": 75,
  "unit": "cm"
}
```

Rules:

* nullable
* object when supplied
* only approved keys
* `length`, `width`, `height`
* positive numeric values
* maximum `10000`
* `unit` required whenever dimensions are present
* `unit = "cm"` only

Do not add arbitrary keys such as:

* `depth`
* `diameter`
* `radius`

unless explicitly approved in a later contract change.

These existing restrictions are already defined in the request conventions.

---

# 17. Material

Preserve optional:

`material`

as bounded free text.

Use:

* nullable
* trimmed
* maximum `500` characters
* Unicode-safe
* plain text

Do not make Material a closed enum.

The existing V1 decision intentionally keeps material flexible for a small furniture business.

---

# 18. Color

Preserve optional:

`color`

as bounded free text.

Use:

* nullable
* trimmed
* maximum `200` characters
* Unicode-safe
* plain text

Do not create a V1 color-management system.

The existing request contract deliberately treats color as free text.

---

# 19. Request Status

Use the frozen V1 request status values:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

Default:

```text
SUBMITTED
```

The existing approved workflow is:

```text
SUBMITTED → IN_REVIEW → CLOSED
```

with direct:

```text
SUBMITTED → CLOSED
```

also permitted.

`CLOSED` is terminal in V1.

Customer cannot set or directly modify `request_status`.

The approved request lifecycle is already closed and must not be replaced with speculative values such as:

```text
QUOTED
APPROVED
REJECTED
PRODUCING
DELIVERING
```

Those are explicitly not V1 statuses.

---

# 20. Status Ownership

`request_status` is server-controlled.

Do not allow:

```json
{
  "request_status": "CLOSED"
}
```

as a customer create/update authority.

Later Staff operations will perform explicit controlled status transitions.

Do not implement those transitions in Phase 3.14.

---

# 21. Internal Staff Notes

The Group J workflow already defines the need to keep Staff-only operational notes separate from customer-submitted data.

Do not mix them into:

* `message`
* `product_details`
* `style`

A separate internal field may be added only if the existing Group J implementation requires persistence directly on this table.

For this phase, the recommended approach is:

`staff_internal_notes` nullable text

If implemented, it must be:

* Staff/Admin only
* never serialized to customers
* distinct from original customer content
* bounded in length
* mutable only through authorized Staff/Admin operations

This follows the existing Request privacy and operational conventions.

---

# 22. Attachments

Attachments remain optional.

Do not put the uploaded binary into the `furniture_requests` row.

Use a separate attachment model/table if attachment implementation already follows the approved Request attachment architecture.

The existing V1 design permits:

* zero or one attachment on request creation
* later attachment upload
* private storage
* controlled attachment authorization
* signed temporary access URLs
* actual file signature validation

and explicitly defines `REQ-007` for post-creation attachments.

Do not implement attachment storage in Phase 3.14 unless the project already needs the attachment schema dependency here.

---

# 23. Anonymous Retrieval Security

A Guest request with:

```text
user_id = null
```

must not become publicly retrievable merely because its database ID or `request_reference` is known.

The existing V1 contract explicitly rejects predictable-ID anonymous retrieval and does not treat email as ownership proof.

Therefore:

* do not create public GET access based on `id`
* do not treat email as authentication
* do not automatically attach old guest requests to a newly registered account merely because emails match
* later anonymous access requires an explicit secure mechanism

That mechanism belongs to Group J/API implementation, not this schema phase.

---

# 24. Product Deletion

Use nullable `product_id` with `nullOnDelete`.

If the referenced Product is later deleted:

```text
product_id = null
```

while preserving:

* product_details
* style
* message
* contact
* dimensions
* material
* color
* quantity

The original customer request must remain readable to authorized operational users.

Do not cascade Product deletion into Furniture Requests.

---

# 25. User Deletion

Use nullable `user_id` with `nullOnDelete`.

Deleting a User must not delete the customer's historical Furniture Requests.

The contact snapshot remains preserved.

This also ensures historical requests remain valid even when the associated account lifecycle later changes.

---

# 26. No Order Relationship

Do not add:

```text
order_id
```

to `furniture_requests`.

The V1 Request contract explicitly separates Requests from Orders.

A request only becomes an Order through a separately approved business workflow. It must never happen implicitly through ordinary Request update operations.

---

# 27. No Payment Relationship

Do not add:

```text
payment_id
payment_status
quoted_price
amount
currency
```

to Furniture Requests.

A request is not a financial transaction.

No price is guaranteed when the request is submitted.

No payment is created by submitting a request.

---

# 28. No Inventory Relationship

Do not add:

```text
inventory_id
warehouse_location
reserved_quantity
stock_quantity
```

to Furniture Requests.

A request does not reserve inventory.

It is customer intent, not inventory commitment.

---

# 29. No Delivery Relationship

Do not add:

```text
delivery_id
delivery_fee
delivery_status
```

to Furniture Requests.

Delivery belongs to Orders after an actual commercial transaction exists.

---

# 30. Validation Architecture for Group J

Prepare the schema so Group J can implement the full validation sequence:

```text id="v8zpuv"
Transport
→ Schema/Input
→ Authentication (optional)
→ Authorization
→ Domain
→ Concurrency
→ Persistence
```

The existing project-wide validation model requires backend validation to remain authoritative regardless of Next.js/Flutter validation.

For Furniture Requests:

### Schema validation

Validate:

* required fields
* types
* maximum lengths
* JSON structures
* email syntax
* phone format
* quantity range
* dimensions structure
* enum values
* unknown fields

### Authentication

Optional.

Determine whether the sender is:

```text
authenticated customer
```

or:

```text
guest
```

### Authorization

For creation, anonymous access is explicitly allowed.

For future retrieval/update, authorization must distinguish:

* Customer own request
* Staff operational access
* Admin access
* anonymous scoped access where later approved

### Domain validation

Validate:

* product requestability
* MADE_TO_ORDER product eligibility
* product ownership of any referenced variant if variants are ever added
* contact requirements
* request-state transition rules

### Persistence

Only validated/trusted data reaches Eloquent persistence.

---

# 31. Unknown Fields

Group J create input must reject unknown fields.

Do not permit:

```text
price
total
payment_status
order_id
user_id
request_status
approved
admin_notes
```

from an ordinary customer submission.

Strict allow-listing reduces stale-client problems and mass-assignment risk.

---

# 32. Input Shape for Group J

The request model should support an eventual create structure conceptually similar to:

```json
{
  "product_id": "optional",
  "product_details": {
    "product_name": "Modern sofa",
    "description": "Three-seat sofa with deep cushions"
  },
  "style": "Modern minimalist",
  "name": "Customer Name",
  "email": "customer@example.com",
  "phone": "+255...",
  "message": "I would like this made in a darker finish.",
  "quantity": 1,
  "dimensions": {
    "length": 220,
    "width": 90,
    "height": 85,
    "unit": "cm"
  },
  "material": "Linen",
  "color": "Charcoal"
}
```

This is a **conceptual storage/input shape**, not permission to change the frozen API contract silently.

Group J must define the final exact API schema and reconcile any new required fields through the contract-change process if necessary.

---

# 33. Request Reference and Public IDs

Use an opaque request identifier/reference.

Do not make a predictable numeric `id` sufficient for customer access.

Customer-facing request retrieval must later perform authorization before serialization.

The project-wide IDOR guidance explicitly requires ownership checks and 404 masking for private resources.

---

# 34. Indexes

Add indexes for actual Request access patterns.

At minimum:

* unique index on `request_reference`
* index on `user_id`
* index on `(request_status, created_at)`
* index on `product_id`
* index on `created_at`

Do not index large JSON/text fields automatically.

The existing Staff request listing supports filtering/search across request fields, but those query requirements should be implemented with deliberate search/query design rather than indiscriminate indexes.

---

# 35. Sorting

The canonical Request listing order is:

```text
created_at DESC, id ASC
```

Use that deterministic ordering in later API implementation.

The schema should support it efficiently through the `created_at` index and primary-key tie-breaker.

The existing request convention explicitly defines this ordering.

---

# 36. No Hard Duplicate Constraint

Do not create uniqueness constraints such as:

```text
email + phone
email + message
phone + message
product_id + email
```

Two legitimate requests may have:

* the same contact details
* similar or identical messages
* the same product

The existing request contract explicitly rejects using repeated contact/message combinations as a hard duplicate detector.

Duplicate/abuse handling belongs to the application/idempotency/rate-limiting layers.

---

# 37. Request Idempotency

The existing V1 contract currently treats `POST /requests` as not inherently idempotent and leaves explicit idempotency deferred.

Do not add a Request-specific idempotency column merely to solve that concern.

Group J may later introduce a shared idempotency mechanism if business requirements change.

---

# 38. Privacy

Furniture Requests are private.

Never include request details in:

* public product responses
* catalog pages
* SEO content
* public search
* unauthenticated listing endpoints

Sensitive fields include:

* name
* email
* phone
* product details
* style
* message
* dimensions
* material
* color
* attachments

The existing contract explicitly classifies request contact, specifications, notes, and attachments as private.

---

# 39. Serialization

Do not expose the model through:

```text
$model->toArray()
```

as the API response.

Later Group J API serializers must use explicit allow-lists.

Customer serialization must contain only customer-permitted fields.

Staff/Admin serialization may expose operational fields according to permission.

Internal storage keys, credentials, and Staff-only notes must never be exposed to unauthorized audiences.

---

# 40. Model Design

Create:

`FurnitureRequest`

Eloquent model.

Relationships:

### FurnitureRequest

* `belongsTo(User::class)` nullable
* `belongsTo(Product::class)` nullable

Do not add Order, Payment, Delivery, or Inventory relationships.

Use explicit casts for:

* `product_details`
* `dimensions`

and enum/value handling for:

* `request_status`

Do not put request validation or workflow orchestration into the Eloquent model.

---

# 41. Historical Intake Preservation

The following fields represent the customer's original request and should be treated as historical intake:

* product_id
* product_details
* style
* name
* email
* phone
* message
* quantity
* dimensions
* material
* color

Later Staff operational handling must not destroy the original submission.

If Staff need to add information, use:

```text
staff_internal_notes
```

or a future dedicated operational history model.

Do not overwrite the original request to represent Staff decisions.

---

# 42. Staff/Admin Operational Separation

Staff can later manage requests operationally according to explicit permissions.

Staff are not owners of Requests.

The existing authorization model explicitly distinguishes:

```text
Staff → operational access
Customer → ownership access
Admin → broader administrative access
```

Staff must not gain general customer-account administration merely because they can view a Furniture Request.

---

# 43. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constants class.

Prefer domain-local enums/value objects for:

* request statuses
* any approved internal constants

Do not suppress static-analysis findings or raise analyzer thresholds.

Keep migrations, model methods, factories, and validators small and cohesive.

---

# 44. Tests

Add automated tests for schema and domain-supporting invariants.

## Migration/schema tests

Verify:

* `furniture_requests` exists
* primary key exists
* `request_reference` is unique
* `user_id` is nullable
* `product_id` is nullable
* `product_details` exists
* `style` exists
* contact fields exist
* `message` exists
* request status exists
* quantity is nullable
* dimensions is nullable
* material is nullable
* color is nullable
* timestamps exist

## Guest tests

Verify a valid request can exist with:

```text
user_id = null
```

and complete contact information.

## Authenticated tests

Verify a request can exist with:

```text
user_id = authenticated customer
```

while preserving contact snapshot fields independently.

## Ownership tests

Verify:

* authenticated User A is linked to User A's request
* `user_id` can be null for guest requests
* there is no automatic User association based on matching email

## Product tests

Verify:

* custom requests can have `product_id = null`
* Product-linked request can reference a Product
* deleting Product nulls `product_id`
* Product deletion does not delete the request
* product snapshot/details remain preserved

The MADE_TO_ORDER eligibility itself belongs to Group J domain validation.

## Contact tests

Verify:

* name storage
* email storage
* phone storage
* reasonable length bounds
* phone stored as string

## Style/message tests

Verify:

* style persists
* style is bounded
* message persists
* message length is bounded
* Unicode text is preserved safely

## Structured JSON tests

Verify:

* valid `product_details` structure persists
* valid `dimensions` structure persists
* malformed structures are rejected by the appropriate validation layer
* unknown dimension keys are rejected

## Status tests

Verify:

* default status is `SUBMITTED`
* only approved statuses can be stored
* no arbitrary request status is accepted

## Historical tests

Change the associated Product or User profile after creating a request and verify:

* product_details unchanged
* style unchanged
* message unchanged
* name unchanged
* email unchanged
* phone unchanged

## Duplicate tests

Verify multiple requests with the same email/phone/message are allowed.

No hard duplicate uniqueness should exist.

---

# 45. Factories

Create:

`FurnitureRequestFactory`

Support fixtures for:

* guest request
* authenticated customer request
* product-linked request
* custom request
* request with dimensions
* request with material/color
* request with all optional specifications
* `SUBMITTED`
* `IN_REVIEW`
* `CLOSED`

Factory defaults must produce valid contact data and valid request data.

Do not generate Order, Payment, or Delivery records from the request factory.

---

# 46. Documentation Updates

Update the authoritative documentation where necessary to make the following explicit:

> Furniture Requests may be submitted by either registered customers or guests.

Also preserve:

* server-derived `user_id`
* contact snapshot
* optional product reference
* product requestability validation
* style
* product details
* message
* private data
* request status lifecycle
* request is not Order/payment/inventory
* no automatic anonymous retrieval
* original customer input remains preserved

Because the API contract is frozen, do not silently edit the public request contract merely through this schema phase.

Any change that makes both email and phone mandatory at the public API boundary must be deliberately reconciled with the frozen V1 contract.

---

# 47. Security and Data Integrity Review

Before completion, verify:

* guests can be represented without fake User accounts
* `user_id` cannot be client-controlled
* contact information is preserved as a request snapshot
* email is never used as authentication
* product references cannot destroy historical requests
* arbitrary JSON is not accepted as trusted product details
* style/message are bounded and treated as plain text
* request status is server-controlled
* no Order, Payment, Delivery, or Inventory relationship has been introduced
* duplicate requests are not blocked by weak uniqueness assumptions
* private request data is not publicly serializable
* Staff access remains operational rather than customer-account administrative

---

# Definition of Done

Phase 3.14 is complete when:

* `furniture_requests` migration exists
* one table supports both guest and authenticated request submission
* `user_id` is nullable and server-derived
* request reference is unique and server-generated
* optional `product_id` is supported
* submitted `product_details` are preserved independently
* `style` is stored as bounded free text
* `name`, `email`, and `phone` contact snapshots are stored
* `message` is stored as bounded text
* optional quantity/dimensions/material/color structures are supported
* request status uses the closed V1 values
* historical customer submission data is preserved
* Product deletion cannot destroy the request
* User deletion cannot destroy the request
* no duplicate-by-contact uniqueness constraint exists
* Eloquent relationships are implemented
* factories support guest and authenticated request fixtures
* schema/domain-supporting tests pass
* maintainability requirements are satisfied
* the schema is ready for strict Group J validation and authorization
* no Request → Order, payment, inventory, or delivery workflow has leaked into this phase

# Out of Scope

Do not implement in Phase 3.14:

* `POST /requests`
* Request validation Form Requests
* DTOs/Commands
* authentication
* authorization policies
* rate limiting
* CAPTCHA
* request status transition endpoints
* Staff request-management API
* customer request retrieval API
* anonymous request retrieval
* attachment upload implementation
* email/SMS notifications
* quotation/pricing
* payment
* Order creation/conversion
* inventory reservation
* production workflow
* delivery workflow
* request-to-order conversion
* full-text search infrastructure

# STOP CONDITION

Stop after the Furniture Request persistence model, relationships, constraints, factories, tests, and migration verification are complete.

Do not implement Group J API validation or request workflow yet.

**Important contract note:** the schema stores `name`, `email`, and `phone` contact snapshots, and the domain model enforces the **frozen V1 contract** — `name` required plus at least one of `email`/`phone`. It does not require all four of `name + email + phone + message` at the API boundary. If Group J later makes `email` and `phone` both mandatory, that is a deliberate frozen-contract reconciliation (per §6 and §46), not a Phase 3.14 schema change.

The next phase is:

**Phase 3.15 — Enquiry Schema**
