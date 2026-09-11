# Phase 3.12 — Payment Schema

## Purpose

Implement the persistence model for **payments associated with Orders**.

The payment model must provide a secure, provider-agnostic foundation for:

* payment initiation
* payment attempts
* provider references
* asynchronous provider confirmation
* failed payments
* retries
* webhook deduplication
* reconciliation
* authoritative payment amount
* payment/order consistency

This phase is a **schema and persistence-model phase**.

Do not implement payment-provider SDKs, API calls, webhook signature verification, payment initiation endpoints, payment state transitions, or Order state updates here.

The later Group H phases will build those behaviors on top of this schema.

---

# Dependencies

Complete these phases first:

* Phase 3.9 — Orders Schema
* Phase 3.10 — Order Items Snapshot Model
* Phase 3.11 — Order Status History Schema

Use the existing `orders` table as the commercial source of truth for the Order.

Do not add payment fields directly to `orders` in this phase.

The existing contract deliberately keeps payment details separate from Order persistence.

---

# Authoritative Inputs

Treat these as authoritative:

* `docs/VISION.md`
* `docs/domain/business-rules.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `AGENTS.md`
* completed Phase 3.9 — Orders Schema
* completed Phase 3.10 — Order Items Snapshot Model
* completed Phase 3.11 — Order Status History Schema

The Version 1 API contract is frozen. Do not introduce provider-specific API shapes, new public statuses, or breaking payment fields in this phase. The endpoint catalogue already establishes `PAY-001` and `WEBHOOK-001` as payment-related surfaces and marks both as concurrency-sensitive.

---

# 1. Core Design Rule

A `Payment` represents a **server-side payment attempt/record for an Order**.

Payment data is separate from:

* Order financial totals
* Order status history
* Delivery
* Inventory
* Cart

The Payment record does not become an alternative source of truth for the Order subtotal or delivery fee.

For a payment attempt, the amount must be the exact server-authoritative amount that the customer is expected to pay at that payment boundary.

The client must never supply or override:

* payment amount
* currency
* Order ownership
* payment status
* provider confirmation
* provider transaction identity

The existing conventions explicitly state that the server owns payment amount and that payment initiation must not trust client financial data.

---

# 2. Multiple Payment Attempts

Do **not** impose:

```text
UNIQUE(order_id)
```

on the `payments` table.

An Order may have more than one payment attempt, for example:

```text
Attempt 1 → FAILED
Attempt 2 → PROCESSING
Attempt 2 → SUCCEEDED
```

This provides a clean foundation for retries without overwriting an earlier failed attempt.

However, the system must later define exactly when an Order is considered successfully paid and which Payment is the authoritative successful payment.

That workflow belongs to Group H.

---

# 3. Create `payments` Table

Create a Laravel migration for:

`payments`

Recommended schema:

| Column                    | Type                                       | Rules                                                   |
| ------------------------- | ------------------------------------------ | ------------------------------------------------------- |
| `id`                      | big integer / Laravel standard primary key | Internal primary key                                    |
| `order_id`                | foreign key                                | Required; references `orders.id`; restrict/no cascade   |
| `payment_reference`       | string                                     | Required; server-generated unique reference             |
| `provider`                | string                                     | Required; closed/controlled provider identifier         |
| `method`                  | string                                     | Required; controlled payment method                     |
| `status`                  | string                                     | Required; closed payment status                         |
| `amount`                  | unsigned big integer                       | Required; integer minor units                           |
| `currency`                | char(3)                                    | Required; V1 TZS                                        |
| `provider_transaction_id` | nullable string                            | Provider-side transaction identifier                    |
| `provider_reference`      | nullable string                            | Provider-side reference if distinct from transaction ID |
| `failure_code`            | nullable string                            | Normalized provider/application failure code            |
| `failure_message`         | nullable text/string                       | Safe, non-secret diagnostic message                     |
| `initiated_at`            | timestamp                                  | Required                                                |
| `confirmed_at`            | nullable timestamp                         | Set only after authoritative success                    |
| `expires_at`              | nullable timestamp                         | Optional payment-attempt expiry                         |
| `created_at`              | timestamp                                  | Required                                                |
| `updated_at`              | timestamp                                  | Required                                                |

Do not add payment-card or bank-account credential fields.

---

# 4. `order_id` Relationship

`payments.order_id` belongs to `orders.id`.

Implement:

### Order

`Order hasMany Payment`

### Payment

`Payment belongsTo Order`

Do not cascade-delete Payments when an Order is deleted.

A payment is a financial record and should not disappear because of ordinary parent deletion behavior.

Given the existing Order model is a historical record and customer deletion also cannot casually remove historical financial records, prefer restrictive deletion semantics here.

In normal application operation, Orders should not be hard-deleted.

---

# 5. Payment Reference

Create a server-generated immutable:

`payment_reference`

This is the payment record's business-facing reference.

Requirements:

* unique
* server-generated
* immutable
* never client supplied
* not derived directly from an auto-increment database ID
* suitable for reconciliation and operational support

Do not expose raw database IDs as payment references.

Do not use the Order reference as the Payment reference because one Order may have multiple payment attempts.

Use a separate namespace/prefix if the project has an established reference convention.

Do not invent a public format that conflicts with the frozen API contract. The exact external representation can be finalized with the Group H API implementation.

---

# 6. Provider Field

Store a controlled provider identifier in:

`provider`

Examples might eventually be represented by a provider enum/value object, but do not hard-code a specific real provider into this schema phase unless the project's provider-selection phase has already made that decision.

The provider field identifies which external payment integration owns the payment attempt.

Do not store:

* provider credentials
* API keys
* secret tokens
* webhook signing secrets
* private certificates
* access tokens

Those belong to secure application configuration/secret management, never to the payment row.

---

# 7. Payment Method

Store the payment method separately from `provider`.

Recommended concept:

```text
method
```

This distinction matters because:

* one provider may support multiple methods
* a method may later be routed through different providers
* reporting and reconciliation may need method-level information

Do not invent an unnecessarily large V1 payment-method taxonomy.

Use a controlled enum/value representation when the actual supported V1 methods are finalized.

Until then, keep the field structurally ready without allowing arbitrary client-defined method values into authoritative payment records.

---

# 8. Payment Status

Payment status must be **separate from Order status**.

Do not reuse:

```text
PAID
PROCESSING
COMPLETED
CANCELLED
```

as an implicit synonym for Order lifecycle state.

The Order and Payment have different state machines.

The project already treats:

```text
PAID ≠ SHIPPED
```

and keeps fulfillment/payment boundaries distinct.

Use a dedicated closed Payment status enum/value representation.

Recommended V1 structure:

```text
PENDING
PROCESSING
SUCCEEDED
FAILED
CANCELLED
EXPIRED
```

Do not add refund-specific statuses such as `REFUNDED` or `PARTIALLY_REFUNDED` unless the frozen Payment contract explicitly requires them.

Refunds are not part of this schema phase.

Any change to a closed API-visible Payment status set is a formal compatibility decision.

---

# 9. Payment Status Is Server-Controlled

The following are never client-settable:

* `status`
* `confirmed_at`
* `provider_transaction_id`
* `provider_reference`
* `failure_code`
* `failure_message`

A customer may request payment initiation, but the backend determines the authoritative status.

The provider callback/webhook is treated as an untrusted external input until:

1. signature/authenticity is verified
2. payload is validated
3. event identity is deduplicated
4. the affected Payment is located
5. amount/currency/order consistency is verified
6. the transition is accepted by the payment state machine

The existing conventions explicitly require provider webhooks to be idempotent and durably deduplicated.

---

# 10. Amount and Currency

Store:

* `amount` as unsigned integer minor units
* `currency` as a 3-character code

For V1:

```text
currency = TZS
```

The existing money convention requires integer minor units and TZS for V1.

Do not use:

* float
* double
* decimal money fields
* formatted strings such as `"TZS 30,000"`

Do not permit the customer to choose another currency through payment input.

---

# 11. Payment Amount Authority

Payment amount must be derived from the authoritative Order total at the point payment is initiated.

The customer must not submit:

```json
{
  "amount": 100,
  "currency": "USD"
}
```

to determine what is charged.

The existing Checkout model creates the authoritative Order financials first. For Delivery, payment is blocked while `delivery_fee_status=PENDING`; once the fee is finalized, the final Order total becomes payable.

Therefore a Payment row must preserve the exact amount associated with that payment attempt.

Do not dynamically calculate historical payment amount from current Product prices.

---

# 12. Payment / Order Currency Consistency

For a V1 payment:

```text
payments.currency = orders.currency
```

and both are expected to be:

```text
TZS
```

The application/domain layer must verify this before creating or accepting a payment.

Do not accept a provider callback claiming a different currency than the Order expects.

A currency mismatch must be treated as a payment consistency failure, not silently converted.

No FX conversion is introduced by this phase.

---

# 13. Provider Transaction Identity

Support:

`provider_transaction_id`

and, where necessary:

`provider_reference`

because providers may expose more than one useful external identifier.

Rules:

* nullable before provider assignment
* immutable once authoritative provider identity is established
* never client-controlled
* never used as a secret
* safe to index as needed
* do not assume provider reference semantics are identical across providers

Do not force all providers into a single undocumented external-ID meaning.

---

# 14. Provider Identifier Uniqueness

Do not create a global unique constraint on:

```text
provider_transaction_id
```

because different providers may use overlapping identifier namespaces.

Prefer uniqueness scoped by Provider:

```text
UNIQUE(provider, provider_transaction_id)
```

when `provider_transaction_id` is present and the project's database strategy supports the desired nullable-unique semantics reliably.

Do not let duplicate provider transaction identities create multiple successful payment records.

The later webhook/payment workflow must also validate Order, amount, currency, provider, and payment state atomically.

---

# 15. Payment Attempt Reference vs Provider Reference

Keep these concepts separate:

### `payment_reference`

Internal business/payment-record identifier generated by this system.

### `provider_transaction_id`

External provider transaction identifier.

### `provider_reference`

Optional additional provider-side reference.

Do not collapse all three into one field.

This separation supports:

* support investigations
* reconciliation
* provider-specific integration differences
* retries
* provider callback matching
* internal reporting

---

# 16. Failure Information

Support:

* `failure_code`
* `failure_message`

These are diagnostic fields, not secrets.

## `failure_code`

Prefer normalized, machine-readable provider/application failure codes.

Do not blindly persist arbitrary unbounded provider exception strings.

## `failure_message`

Store only a safe diagnostic message.

Never store:

* card numbers
* CVV/CVC
* authentication tokens
* API keys
* webhook secrets
* full provider payloads containing payment credentials
* raw authorization headers

Before persistence the value is sanitized: secrets (card numbers, CVV/CVC-labeled values, and high-entropy tokens such as API keys/AWS signatures) are redacted to a placeholder, and the result is truncated to a fixed maximum length (500 characters). Raw unbounded provider exception strings are never persisted as-is. A blank value is stored as `null`. The same bounding and redaction rules apply to `payment_webhook_events.failure_reason`.

The project security conventions explicitly prohibit logging or exposing payment secrets.

---

# 17. Raw Provider Payloads

Do **not** add a generic:

```text
provider_payload JSON
```

column to `payments`.

Raw provider payloads often contain unnecessary personal or sensitive information and create long-term data-retention and privacy problems.

If a later provider integration genuinely needs raw-event persistence, use a deliberately scoped internal webhook-event model with controlled retention and redaction.

That is a later Group H concern.

---

# 18. `initiated_at`

Store:

`initiated_at`

as the server-side timestamp for when the payment attempt was initiated.

Requirements:

* server-generated
* not client-controlled
* timezone-safe
* later serialized as UTC `Z` when exposed

Do not use client-submitted timestamps as authoritative payment timing.

The project-wide convention is that payment confirmation time is server-controlled.

---

# 19. `confirmed_at`

Store:

`confirmed_at`

nullable.

It remains `null` until authoritative payment success has been established.

Rules:

* server-controlled
* never client-settable
* immutable once successful confirmation is recorded
* not a substitute for Order status history

Do not create `paid_at` on the Order in this phase.

The eventual payment workflow may update the Order from `PENDING_PAYMENT` to `PAID`, while the Payment retains its own confirmation timestamp.

---

# 20. `expires_at`

Support an optional:

`expires_at`

for payment attempts that have an externally defined validity period.

This is useful for:

* payment sessions
* checkout/payment handoff windows
* asynchronous payment requests
* provider-specific expiry

However:

* do not define an arbitrary universal expiration duration in this schema phase
* do not let the client control it
* do not implement expiry jobs here

A later provider integration may use it where appropriate.

---

# 21. Updated Timestamp

Unlike `order_status_history`, `payments` should have:

* `created_at`
* `updated_at`

because Payment records may legitimately move through a controlled lifecycle before becoming immutable from a business perspective.

Do not confuse this with permission to arbitrarily edit financial history.

Only explicitly supported payment-state transitions and reconciliation operations may alter payment records later.

---

# 22. Payment State History

Do not add a JSON array such as:

```text
status_history
```

to the Payment row.

The current Payment schema stores the current payment state.

Detailed provider-event history and webhook deduplication belong in a separate internal persistence model if required.

Do not turn the payment record into an event-sourcing system.

Keep V1 simple and auditable.

---

# 23. Webhook Idempotency Foundation

A secure payment implementation needs durable deduplication for repeated provider callbacks.

The project convention explicitly requires:

* stable provider event identity
* durable unique constraint
* atomic deduplication
* no duplicate payment/order mutations on repeated delivery

Therefore this phase should establish a dedicated internal table:

`payment_webhook_events`

rather than attempting to overload `payments` with webhook-delivery state.

---

# 24. Create `payment_webhook_events` Table

Create:

`payment_webhook_events`

with a minimal provider-agnostic structure:

| Column              | Type                               | Rules                                                        |
| ------------------- | ---------------------------------- | ------------------------------------------------------------ |
| `id`                | big integer / standard primary key | Internal identity                                            |
| `payment_id`        | nullable foreign key               | Reference matched Payment; null before matching if necessary |
| `provider`          | string                             | Provider namespace                                           |
| `provider_event_id` | string                             | Required external event identifier                           |
| `provider_correlation_id` | nullable string             | Optional provider correlation reference for later Payment matching; not a deduplication key, never a secret |
| `event_type`        | string                             | Provider event classification                                |
| `processing_status` | string                             | Controlled internal status                                   |
| `received_at`       | timestamp                          | Server timestamp                                             |
| `processed_at`      | nullable timestamp                 | Processing completion                                        |
| `failure_reason`    | nullable string/text               | Safe diagnostic only; redacted and truncated to 500 chars before persistence (blank → `null`)                                         |
| `created_at`        | timestamp                          | Required                                                     |
| `updated_at`        | timestamp                          | Required                                                     |

Do not persist the complete raw provider payload here by default.

`provider_correlation_id` preserves a provider-supplied reference (for example a checkout, session, or provider payment reference) that survives while `payment_id` is still `null`. When present it lets a later Group H matching step join the event to `payments.provider_transaction_id` or `payments.provider_reference` instead of losing the event. It is not the webhook deduplication identity (`provider`, `provider_event_id`) and it is not a credential.

Do not store webhook signatures or shared secrets as long-lived row data unless a later provider integration explicitly proves a secure need.

---

# 25. Webhook Event Uniqueness

Use a durable unique constraint on:

```text
(provider, provider_event_id)
```

This is the primary deduplication key.

The same external event delivered twice must not create two independently processed events.

This requirement is explicitly called out in the project's webhook idempotency convention.

Do not use:

* request timestamp
* callback URL
* payment ID alone
* random UUID generated after receipt

as the provider-event deduplication identity.

---

# 26. Webhook Event Processing Status

Use a small closed internal set, for example:

```text
RECEIVED
PROCESSED
FAILED
```

Keep this status internal.

Do not expose webhook-processing status as customer-facing Payment status.

Do not add unnecessary states such as `QUEUED`, `RETRIED`, `SKIPPED`, `IGNORED`, etc. unless the actual implementation requires them.

This is internal infrastructure, not a public business enum.

---

# 27. Webhook Event → Payment Relationship

`payment_webhook_events.payment_id` may be nullable because the initial webhook-processing stage may not yet have safely matched the event to a Payment.

After successful matching:

```text
payment_webhook_events.payment_id → payments.id
```

Use `nullOnDelete`.

Deleting a Payment must not cause the webhook-event record to become unreadable or fail historical retention.

Do not cascade-delete webhook records when a Payment is removed.

In normal operation, Payments should not be hard-deleted.

---

# 28. Webhook Event Timestamps

All webhook event timestamps must be server-controlled.

Use:

* `received_at` — when Laravel received the event
* `processed_at` — when the system successfully completed processing

Do not trust provider timestamps as the system's own event-processing timestamp.

A provider-supplied event timestamp may later be stored in provider-specific integration data if necessary, but do not add it to the generic schema without a demonstrated requirement.

---

# 29. No Signature Data in Persistent Payment Records

Do not add:

* `webhook_secret`
* `api_key`
* `signature_secret`
* `access_token`
* `authorization_header`

to `payments` or `payment_webhook_events`.

Provider secrets belong in application secret/configuration management.

Signature verification is a later Group H phase.

---

# 30. Payment / Order Consistency

The eventual payment workflow must enforce:

```text
payments.order_id = intended Order
payments.amount = authoritative payable Order total
payments.currency = Order.currency
```

before payment initiation.

For successful provider confirmation, the workflow must verify the provider result against the stored Payment and Order.

Never transition an Order to `PAID` merely because:

* the frontend says payment succeeded
* a browser redirect returned successfully
* a client supplied transaction status
* an unverified webhook arrived
* the provider amount differs from the stored Payment amount

The server/provider verification boundary is authoritative.

---

# 31. Delivery-Fee Gate

The payment model must support the existing Checkout rule:

```text
DELIVERY + delivery_fee_status=PENDING
→ Payment must not be finalized/accepted
```

The existing contract explicitly says that a pending delivery fee blocks payment initiation and that `PENDING_PAYMENT → PAID` requires finalized delivery fees.

Do not put `delivery_fee_status` on `payments`.

Read it from the authoritative Order during the later payment workflow.

---

# 32. No Payment Secrets

The schema must never store:

* full card number
* CVV/CVC
* PIN
* online banking password
* mobile-money PIN
* OTP
* bearer access token
* provider API credential
* webhook signing secret
* private cryptographic key

Where a provider supports tokenized instruments, store only the provider-issued non-sensitive reference later required by the approved integration, and only after the provider-selection/security phase defines it.

Do not invent a generic `card_token` field in this phase.

---

# 33. No Direct Payment API CRUD

Do not create generic CRUD semantics such as:

```text
POST /payments
PATCH /payments/{payment}
DELETE /payments/{payment}
```

for arbitrary client writes.

The frozen API already defines payment initiation and webhook-specific behavior, not generic unrestricted payment mutation.

The schema should support the eventual controlled actions.

---

# 34. Model Design

Create:

`Payment`

and, for webhook deduplication:

`PaymentWebhookEvent`

Implement relationships:

### Payment

* `belongsTo(Order::class)`
* `hasMany(PaymentWebhookEvent::class)`

### PaymentWebhookEvent

* `belongsTo(Payment::class)` nullable

Use explicit casts for:

* amount
* timestamps

Use appropriate enums/value objects for controlled status/provider/method values as the project convention permits.

Do not place provider SDK code into Eloquent models.

Do not place webhook processing logic into model observers.

---

# 35. Payment Immutability Rules

The following historical/payment facts must not be casually modified after creation:

* `order_id`
* `payment_reference`
* `provider`
* `amount`
* `currency`
* `initiated_at`

Controlled workflow fields may change according to the future Payment state machine:

* `status`
* `provider_transaction_id`
* `provider_reference`
* `failure_code`
* `failure_message`
* `confirmed_at`
* `processed/expiry-related fields`

Do not implement those workflows here.

Never provide generic customer-facing update access to Payment records.

---

# 36. Deletion Rules

For `payments`:

* Order deletion should be restricted, not cascade into Payments
* Payment deletion is not a normal business operation
* historical payment records must remain available for reconciliation

For `payment_webhook_events`:

* Payment deletion must not cascade and destroy event history
* use nullable `payment_id`

Do not add soft deletes unless the broader data-retention strategy explicitly requires them.

The current design should favor controlled retention rather than generic deletion semantics.

---

# 37. Indexes

Add indexes supporting actual operational access.

For `payments`:

* `order_id`
* `(order_id, created_at)`
* `payment_reference` unique
* `(provider, provider_transaction_id)` where non-null

For `payment_webhook_events`:

* unique `(provider, provider_event_id)`
* `payment_id`
* `(provider, processing_status)`
* `received_at`

Do not add broad indexes on every column.

The most important query paths are:

```text
Order → payments
provider transaction → payment
provider event → webhook event
Payment → webhook events
```

---

# 38. Concurrency and Idempotency Preparation

The schema must support future concurrency-safe payment processing.

The existing contract marks:

* `PAY-001`
* `WEBHOOK-001`

as concurrency-sensitive.

The later implementation must protect against races such as:

```text
payment initiation × webhook
duplicate webhook × duplicate webhook
payment retry × previous attempt
successful callback × failed callback
payment confirmation × order cancellation
```

This phase does not implement those transactions.

However, the schema must provide the durable identifiers and constraints required to implement them safely.

---

# 39. Payment Initiation Idempotency

The frozen API requires Payment initiation to use `Idempotency-Key`.

Do not persist `Idempotency-Key` directly on `payments` unless its lifecycle is explicitly one-to-one with the Payment attempt.

Prefer keeping generic idempotency infrastructure separate from Payment persistence unless the project has already established a shared idempotency-table design.

Do not invent payment-specific idempotency storage in this phase merely because the endpoint will need it later.

The important requirement here is that Payment records support safe deduplication once the idempotency mechanism is implemented.

---

# 40. No Order Status Mutation in This Phase

Do not change Order status from:

```text
PENDING_PAYMENT
```

to:

```text
PAID
```

from the Payment model.

Payment confirmation and Order state transition must later occur through a controlled application workflow.

The project's architecture explicitly separates payment handling from Order state transitions and requires state changes to be server-authoritative.

---

# 41. Security and Privacy

Payment records are private financial data.

They must **never ever** be:

* publicly cached
* embedded in public catalog responses
* exposed through unauthenticated endpoints
* broadly serialized through `model.toArray()`
* logged indiscriminately

The API conventions classify payment secrets as internal and require explicit serialization allow-lists.

Customer responses should expose only the minimum approved payment summary later defined by the frozen API, such as:

```text
payment_status
amount
currency
```

not provider secrets or raw webhook information.

Every Payment operation is authorization-checked on the backend. Authorization is decided from the authenticated principal plus the related Order ownership or an approved staff/administrative role — never from a client-supplied identity such as a body `user_id`, query `user_id`, or a client-declared role. A customer may read or act only on Payments belonging to their own Orders; another customer's Payment is never reachable (masked `RESOURCE_NOT_FOUND`, not existence disclosure). Staff access is operational-only per the approved `orders`/payment permissions and does not grant customer-account administration. Webhook-event records are internal infrastructure: their access is restricted to server-side workflows and approved operational roles, never customer-facing, never exposed through unauthenticated endpoints, and never returned to the client that supplied the webhook payload.

---

# 42. Mass Assignment

Payment fields are server-controlled.

Never allow:

```text
$request->all() → Payment::create(...)
```

or equivalent uncontrolled assignment.

Client input must be transformed through:

```text
validated input
→ DTO/command
→ payment application workflow
→ trusted Payment persistence
```

The project-wide conventions explicitly prohibit direct request-to-model mass assignment.

---

# 43. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constant class.

Prefer payment-specific enums/value objects close to the domain concept.

Do not suppress static-analysis findings or increase thresholds.

Keep the Payment model, migration, factories, and tests focused.

Do not create a generic "transaction framework" or provider abstraction hierarchy before an actual provider integration requires it.

---

# 44. Tests

Add automated tests for the schema and persistence invariants.

## Payment migration/schema tests

Verify:

* `payments` table exists
* primary key exists
* `order_id` exists and is required
* `payment_reference` is unique
* provider/method/status fields exist
* amount is integer-compatible
* currency exists
* provider transaction/reference fields are nullable
* failure fields are nullable
* `initiated_at` exists
* `confirmed_at` is nullable
* `expires_at` is nullable
* timestamps exist

## Relationship tests

Verify:

* Order → Payments
* Payment → Order

## Multiple-attempt tests

Verify that one Order can have multiple Payment records.

Example:

```text
Order A
 ├── Payment attempt 1 → FAILED
 └── Payment attempt 2 → PENDING
```

Do not accidentally enforce one-payment-per-order uniqueness.

## Payment reference tests

Verify:

* reference is unique
* reference is not derived directly from raw database ID
* normal creation does not accept a client-supplied authoritative payment reference

## Money tests

Verify:

* amount is integer
* amount cannot be negative
* currency is TZS in V1
* no floating-point money representation exists

## Payment/order consistency tests

Verify application-level validation can enforce:

* Payment belongs to intended Order
* Payment currency matches Order currency
* Payment amount corresponds to the authoritative payable Order amount

Do not implement the complete payment workflow just to test these foundations.

## Provider identity tests

Verify the schema supports distinct providers with potentially overlapping transaction identifiers without false global uniqueness.

Verify duplicate:

```text
provider + provider_transaction_id
```

cannot create conflicting provider transaction identities where that identifier is present.

## Webhook event tests

Verify:

* `payment_webhook_events` exists
* `(provider, provider_event_id)` is unique
* duplicate provider events are rejected at the durable constraint level
* a webhook event may initially have `payment_id = null`
* matching a Payment is possible later
* deleting a Payment does not destroy the webhook event

## Privacy/security tests

Verify no fields exist for:

* card PAN
* CVV/CVC
* PIN
* provider secret
* webhook secret
* API key
* access token

Verify sensitive internal provider data is not part of default serialization.

---

# 45. Factories

Create or extend factories for:

### Payment

Support fixtures such as:

```text
PENDING
PROCESSING
SUCCEEDED
FAILED
CANCELLED
EXPIRED
```

Use valid Orders and valid integer TZS amounts.

Support:

* successful payment
* failed attempt
* pending attempt
* multiple payment attempts on one Order

### PaymentWebhookEvent

Support:

* received event
* processed event
* failed event
* event linked to Payment
* event not yet linked to Payment

Factory defaults must produce internally coherent records.

Do not seed fake successful financial transactions into general production-like seed data unless required by the development environment.

---

# 46. Documentation Updates

Update the appropriate authoritative documentation only where needed.

Document these established payment-domain facts if not already present:

* Payment is separate from Order
* Payment is server-controlled
* Payment amount is integer minor units in TZS
* Orders may have multiple payment attempts
* Payment provider identifiers are external references, not secrets
* webhook event identity is durably deduplicated
* Payment secrets are never persisted
* Payment success does not equal fulfillment completion

Do not create a permanent phase-specific payment-schema markdown file merely for these decisions.

If a conflict is found between existing payment documentation and this design, record the decision explicitly rather than silently changing the frozen contract.

---

# 47. Security and Data Integrity Review

Before completion, verify:

* no sensitive payment credentials are stored
* no provider secrets are stored in the database
* payment amount is server-authoritative
* payment currency is server-authoritative
* payment status is server-authoritative
* provider transaction identity is treated as external data, not authentication
* webhook events have durable unique identity
* duplicate webhook delivery cannot create duplicate event rows
* Orders cannot be accidentally deleted with financial records through cascade behavior
* Payment records cannot be arbitrarily modified through mass assignment
* Payment data remains private
* raw provider payloads are not stored by default
* no Order state mutation occurs from the Payment model
* multiple payment attempts remain possible

---

# Definition of Done

Phase 3.12 is complete when:

* `payments` migration exists
* Payment belongs to Order
* an Order may have multiple payment attempts
* each Payment has a unique server-generated `payment_reference`
* provider, method, and status are represented separately
* amount is integer minor-unit TZS
* provider transaction/reference identity is supported
* payment failure information is supported without secrets
* initiated/confirmed/expiry timestamps are represented
* Order deletion does not cascade into financial records
* Payment model relationships are implemented
* `payment_webhook_events` exists for durable webhook deduplication
* `(provider, provider_event_id)` is uniquely constrained
* webhook events can exist before a Payment is safely matched
* webhook event deletion behavior preserves historical event data
* factories support pending, failed, and successful attempts
* migration, relationships, multiple-attempt, financial, provider-identity, webhook-deduplication, and security tests pass
* maintainability constraints are satisfied
* no provider SDK, webhook signature verification, payment endpoint, or payment state machine has leaked into this phase

# Out of Scope

Do not implement in Phase 3.12:

* payment provider selection
* provider SDK integration
* payment initiation endpoint
* payment state-transition service
* provider callback endpoint
* webhook signature verification
* webhook payload validation
* provider API credentials
* payment retries workflow
* payment timeout/expiry jobs
* Order `PAID` transition
* inventory consumption after successful payment
* refunds
* partial refunds
* chargebacks
* settlement
* reconciliation jobs
* customer payment UI
* staff payment UI
* saved payment methods
* card tokenization
* 3-D Secure flows
* mobile-money OTP/PIN handling
* email/SMS payment notifications

# STOP CONDITION

Stop after the Payment and Payment Webhook Event persistence models, relationships, constraints, factories, tests, and migration verification are complete.

Do not implement provider-specific behavior yet.

The next phase is:

**Phase 3.13 — Delivery Schema**
