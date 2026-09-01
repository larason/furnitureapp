# Phase 1.22 — Define Checkout API Contract

## 1. Purpose

Phase 1.22 defines the **complete Version 1 Checkout API contract**.

Checkout is the most important transaction boundary before Order creation. It converts the customer's current Cart into a purchase transaction while enforcing the rules already established in earlier phases.

The core flow is (Model B — fee-after-order):

```text id="w3x9a1"
Authenticated Customer
        ↓
Own Cart
        ↓
Validate Cart
        ↓
Validate Products / Variants
        ↓
Validate Inventory
        ↓
Calculate Current Prices (subtotal)
        ↓
Select Fulfillment
        ↓
Create Order (PENDING_PAYMENT) — delivery_address snapshotted, subtotal authoritative, delivery_fee pending (0 for PICKUP)
        ↓
Staff/Admin sets delivery_fee (for DELIVERY)
        ↓
Calculate Final Total (subtotal + delivery_fee) — authoritative
        ↓
Payment workflow (Group H) on final total
```

The Checkout API must be:

```text id="7zce2b"
secure
transactionally correct
idempotency-aware
customer-friendly
concurrency-safe
server-authoritative
compatible with Next.js
compatible with Flutter
ready for the later Payment contract
```

The single most important rule is:

> **The client requests checkout; the server decides whether checkout is valid and what the authoritative transaction contains.**

---

# 2. Documentation Strategy

Continue using the consolidated documentation structure.

Update:

```text id="xv0n6q"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not create:

```text id="6t7dgr"
checkout-api.md
checkout-contract.md
checkout-validation.md
checkout-decisions.md
```

as permanent documents.

---

# 3. Authoritative Project Paths

Use:

```text id="3cg0ym"
AGENTS.md
docs/VISION.md
```

Do not use the superseded names:

```text id="j0c9y2"
agent.md
VISION.md
```

---

# 4. Payment Assignment

Payment implementation remains assigned to:

**Phase Group H**

not Group G.

Checkout may define the boundary where payment is required or initiated, but this phase must **not** implement:

```text id="kxq2a8"
payment provider integration
payment SDK
provider credentials
provider callbacks
webhooks
payment provider-specific statuses
provider API requests
```

Those belong to Group H.

---

# 5. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

The Checkout contract must use only approved values.

At minimum:

```text id="w8u4a0"
fulfillment_type:
PICKUP
DELIVERY
```

Any additional checkout-related enum introduced here must be explicitly approved and closed.

---

# 6. Dependency Position

Current sequence:

```text id="z0i1y2"
1.19 Concrete API Endpoint Inventory
       ↓
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 Order Tracking Contract
       ↓
...
```

Checkout must consume:

```text id="v37zcu"
Catalog
+
Cart
+
Authentication
+
Authorization
+
Validation
+
Error Contract
```

---

# 7. Authoritative Inputs

Read:

```text id="zue6n0"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Specifically review:

```text id="b5h9n0"
Phase 1.20 Catalog contract
Phase 1.21 Cart contract
Phase 1.17 Authentication contract
Phase 1.18 Authorization contract
Phase 1.15 Validation conventions
Phase 1.16 Error contract
Phase 1.10 HTTP method conventions
Phase 1.12 Pagination conventions
Phase 1.13 Response conventions
Phase 1.14 Input conventions
```

---

# 8. Core Checkout Principle

Checkout is a **server-controlled transaction workflow**.

The customer submits intent such as:

```text id="5anl7u"
fulfillment type
delivery information where required
```

The server determines:

```text id="vv0xcy"
cart contents
current product state
current variant state
current price
stock availability
delivery fee
subtotal
total
order reference
order state
```

The client must not be authoritative for those values.

---

# 9. Checkout Endpoint

Define the canonical Version 1 Checkout endpoint.

Recommended:

```text id="q4sh8a"
CHK-001

POST /api/v1/checkout
```

Authentication:

```text id="b2c55v"
Required
```

Actor:

```text id="9s9cs3"
CUSTOMER
```

Authorization:

```text id="18ylv0"
Authenticated customer may checkout only their own active Cart.
```

---

# 10. Why Checkout Is Not a Cart PATCH

Do not define checkout as:

```text id="k4nmn4"
PATCH /me/cart
```

Checkout is not merely changing Cart fields.

It performs:

```text id="m2y5q0"
validation
inventory checks
price calculation
fulfillment decision
transaction creation
payment handoff
```

It therefore requires a dedicated business workflow.

---

# 11. Checkout Is Not Direct Order Creation

Do not allow the client to directly create an arbitrary Order through:

```text id="p4un2o"
POST /orders
```

for ordinary customer checkout.

The server should create the Order from validated Checkout state.

This prevents the customer from directly controlling:

```text id="8se05v"
order total
order status
order reference
inventory state
payment state
```

---

# 12. Checkout Request Body

The request should contain only the customer's checkout choices and necessary information.

Recommended conceptual structure:

```json id="bg52tt"
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "...",
    "phone": "...",
    "address_line": "...",
    "city": "..."
  }
}
```

The exact address fields must follow the approved data model.

---

# 13. Checkout Required Fields

Minimum conceptual input:

```text id="n5t1c6"
fulfillment_type
```

Conditional:

```text id="k6qs6j"
delivery_address
```

when:

```text id="x6zlx4"
fulfillment_type = DELIVERY
```

For:

```text id="8tdnph"
fulfillment_type = PICKUP
```

delivery address should not be required.

---

# 14. Fulfillment Type

Version 1 supports:

```text id="v3z7tm"
PICKUP
DELIVERY
```

This enum is CLOSED.

Do not allow:

```text id="3t2f8d"
shipping
courier
self-delivery
warehouse
```

unless explicitly approved as new contract values.

---

# 15. Pickup

For:

```text id="1k0d4s"
fulfillment_type = PICKUP
```

the order must not require delivery information.

The customer receives the appropriate pickup information through the later Order contract.

The exact pickup location model is deferred unless already defined elsewhere.

---

# 16. Delivery

For:

```text id="r1z5q4"
fulfillment_type = DELIVERY
```

the customer must provide the information required to fulfill delivery.

The backend must validate it.

---

# 17. Delivery Fee

The approved business rule is:

> **Delivery fee is variable and is added directly by Staff/Admin.**

Therefore the customer must **not** control the final delivery fee.

Do not allow:

```json id="f3x5qk"
{
  "delivery_fee": 1000
}
```

to become authoritative Checkout input.

---

# 18. Delivery Fee Authority

The responsibility is:

```text id="6b2j0k"
Customer
→ chooses DELIVERY

Staff/Admin
→ determines/adds appropriate delivery fee

Backend
→ stores/calculates authoritative fee

Customer
→ sees resulting fee
```

Do not silently reintroduce a flat TZS 20,000 rate.

The old flat-rate assumption has been superseded.

---

# 19. Important Timing Question

Because the delivery fee is added by Staff/Admin, the Checkout contract must explicitly account for whether the order can be:

```text id="h0v6at"
created before final delivery fee
```

or requires:

```text id="5f2wqg"
fee determined before order creation/payment
```

Do not silently assume the answer.

Use the current business model and record the approved Version 1 workflow.

If the business requires staff to add the delivery fee **after** an order is placed, that must be reflected in the Order lifecycle and payment flow.

If payment must occur only after the fee is known, that must be reflected in the checkout/payment boundary.

This is a critical decision and must be documented rather than guessed.

---

# 20. Recommended Delivery-Fee Model

Given the existing business statement:

> "the fee is added directly by the admin or staff"

the recommended contract is:

```text id="i6v1co"
Customer selects DELIVERY
        ↓
Order/checkout enters delivery workflow
        ↓
Staff/Admin sets delivery fee
        ↓
Final amount becomes authoritative
        ↓
Payment proceeds/settles according to Group H
```

However, the exact state sequence must align with the later Order and Payment contracts.

Do not implement a payment-before-final-total workflow accidentally.

---

# 21. Customer Cannot Set Delivery Fee

Explicit invariant:

```text id="6zqyo8"
Client input:
fulfillment_type = DELIVERY

Client input:
delivery_address = ...

Client input:
delivery_fee = X
→ not authoritative
```

The server/business operator controls the final fee.

---

# 22. Customer Cannot Set Order Total

Never accept:

```json id="k1g31h"
{
  "total": 100000
}
```

as authoritative.

The backend calculates:

```text id="s0rjkh"
item prices
+
quantities
+
delivery fee
=
authoritative order amount
```

according to the eventual pricing model.

---

# 23. Customer Cannot Set Subtotal

Do not accept:

```json id="gjx4j5"
{
  "subtotal": 10000
}
```

as authoritative.

The server calculates it from the current Cart.

---

# 24. Customer Cannot Set Item Price

Cart/Product prices are server-controlled.

The Checkout request must not include:

```text id="d4tq8j"
item_price
unit_price
discounted_price
```

as authoritative data.

---

# 25. Customer Cannot Set Stock

Do not accept:

```text id="v3qpc3"
available_quantity
reserved_quantity
```

from the client.

Inventory is authoritative on the server.

---

# 26. Customer Cannot Set Order Status

The client must not submit:

```json id="9fx0x7"
{
  "status": "PAID"
}
```

or:

```json id="4h5op7"
{
  "status": "COMPLETED"
}
```

Checkout determines the appropriate state according to the actual business/payment workflow.

---

# 27. Customer Cannot Set Order Reference

The Order reference:

```text id="6hk21j"
OD-*****
```

is server-generated.

The customer does not supply it.

---

# 28. Customer Cannot Select Another Cart

The authenticated customer checks out:

> **their own active Cart.**

Do not make:

```text id="3mzv47"
cart_id
```

a customer-controlled ownership mechanism unless the architecture has a deliberate need for it.

---

# 29. Recommended Checkout Ownership

Prefer:

```text id="4h8vl4"
POST /api/v1/checkout
```

where the server determines:

```text id="x6w8lc"
current authenticated customer
→ current active Cart
```

rather than:

```text id="3w6x2z"
POST /api/v1/checkout
{
  "cart_id": "..."
}
```

This reduces IDOR risk and simplifies the customer experience.

---

# 30. Checkout Preconditions

Before checkout succeeds:

```text id="y7f9sh"
Customer authenticated
AND
customer has active Cart
AND
Cart contains at least one valid item
AND
all items are purchasable
AND
variants are valid
AND
inventory is sufficient
AND
fulfillment is valid
AND
required delivery information is valid
AND
authoritative pricing can be determined
```

Payment-related preconditions are handled with Group H.

---

# 31. Empty Cart

Checkout of an empty Cart must fail.

Use a stable business error such as:

```text id="v5knj8"
CART_EMPTY
```

rather than allowing the server to create an empty Order.

---

# 32. Invalid Cart

If the Cart contains an invalid item:

```text id="c2h8df"
CART_INVALID
```

or a more specific documented error may be returned.

Do not create a partial Order.

---

# 33. Made-to-Order Cart Protection

If an invalid Cart somehow contains:

```text id="7x6n7n"
MADE_TO_ORDER
```

the Checkout must reject it.

Do not allow a client to bypass the Product Type rule simply by manipulating the Cart.

---

# 34. Product State Revalidation

Checkout must re-read authoritative Product state.

Verify:

```text id="jv8jb4"
Product exists
Product is active
Product type is IN_STOCK
Product/Variant is purchasable
```

Do not rely on the state previously returned by the Catalog API.

---

# 35. Variant Revalidation

For each variant:

```text id="5c9m46"
variant exists
variant belongs to product
variant active
variant purchasable
```

---

# 36. Inventory Revalidation

Checkout performs the authoritative stock check.

For every purchasable item:

```text id="79x8m5"
requested quantity
≤
available authoritative quantity
```

must hold at the point of transaction.

---

# 37. Race Condition

Consider:

```text id="44j3x9"
Customer A sees 1 item
Customer B buys it
Customer A checks out
```

Customer A's Checkout must fail safely.

The result must not be:

```text id="a9w6zs"
negative stock
oversold item
incorrect order
```

---

# 38. Concurrency Requirement

The implementation must ensure:

> Inventory validation and the corresponding state-changing operation are safe under concurrent checkout attempts.

This is a **critical implementation requirement** for later Laravel/database phases.

Do not specify the exact locking strategy here.

---

# 39. Current Price Recalculation

At Checkout:

```text id="m0h7id"
Cart
  ↓
Current catalog price
  ↓
Recalculate
  ↓
Order price
```

Do not trust cached frontend prices.

---

# 40. Price Change Example

Customer sees:

```text id="zd2m1p"
Sofa = TZS 1,000,000
```

Price becomes:

```text id="fzt9b5"
TZS 1,100,000
```

before Checkout.

The backend must have a defined policy.

Recommended:

> Checkout uses the currently authoritative catalog price and clearly communicates any changed amount to the customer before final payment/confirmation where the workflow allows.

The exact UI/payment interaction is later.

---

# 41. Checkout Price Transparency

Do not silently charge an amount different from the final customer-visible amount without giving the customer a chance to review it where payment timing requires such review.

This is especially important when:

```text id="7qbb7t"
delivery fee
```

may be added by Staff/Admin.

The final contract must align with the Order/Payment phases.

---

# 42. Checkout Calculation Order

Use this conceptual calculation (Model B — fee-after-order):

```text id="pl8a7p"
1. Load active Cart
2. Validate every item
3. Resolve current unit prices
4. Calculate line totals
5. Calculate subtotal
6. Determine fulfillment
7. Create/prepare Order (PENDING_PAYMENT) with snapshots (subtotal authoritative, delivery_fee pending for DELIVERY / 0 for PICKUP, delivery_address snapshotted)
8. Staff/Admin sets authoritative delivery_fee (for DELIVERY)
9. Calculate final total (subtotal + delivery_fee) — authoritative
10. Continue payment workflow (Group H) on final total
```

Do not let the client dictate step 8 or 9.

---

# 43. Currency

All Checkout financial representations must use the money convention established in Phase 1.13.

The Checkout request should not allow arbitrary:

```text id="4nno1y"
currency
```

unless multi-currency support is explicitly part of Version 1.

---

# 44. Recommended Currency Rule

If Version 1 is operating in TZS:

> The authoritative transaction currency should come from the backend/business configuration, not arbitrary client input.

Do not let a client submit:

```json id="lgq5tx"
{
  "currency": "USD"
}
```

to change a TZS transaction.

---

# 45. Checkout Authentication

Checkout requires authentication:

```text id="w4j6mt"
Customer authenticated
```

Anonymous checkout is not supported.

This must be enforced server-side.

---

# 46. Checkout Authorization

Authorization must ensure:

```text id="yrj19l"
authenticated principal
→ owns active Cart
```

and later:

```text id="t3k3lq"
created Order
→ belongs to authenticated Customer
```

---

# 47. Staff Cannot Checkout Through Customer Flow

Staff are operational actors, not ordinary customer checkout actors.

Do not allow Staff to use a random customer's Cart.

---

# 48. Admin Cannot Arbitrarily Checkout as a Customer

Admin privileges do not automatically grant customer checkout ownership.

Impersonation, if ever required, is a separate security feature.

---

# 49. Checkout Idempotency

Checkout is a **critical idempotency operation**.

A network retry must not create:

```text id="8hl1z2"
two Orders
two reservations
two payment attempts
```

for the same customer intent.

---

# 50. Idempotency Key

The later implementation should use a standardized idempotency mechanism.

For Phase 1.22:

> Define Checkout as requiring an idempotency key.

The exact header and storage strategy can be finalized in the later idempotency phase/implementation.

---

# 51. Idempotency Semantics

The same idempotency key must represent the same logical Checkout attempt.

Conceptually:

```text id="pazjvz"
Request A
  key = K123
      ↓
Checkout created

Retry
  key = K123
      ↓
Return original result
```

It must not create a second Order.

---

# 52. Idempotency-Key Reuse

If the same key is reused with materially different input:

```text id="cobk5l"
same key
+
different fulfillment/address
```

the API must not silently execute a different operation.

It should produce a stable conflict/idempotency error according to the later idempotency contract.

---

# 53. Checkout Timeout

A client timeout does not prove Checkout failed.

Example:

```text id="l1a9q0"
Customer submits Checkout
↓
server creates Order
↓
network drops
↓
client sees timeout
```

A retry with the same idempotency key should reconcile with the original operation.

---

# 54. Checkout Response

The response should provide the resulting checkout/order state sufficiently for the client to continue.

Conceptually:

```json id="8f3u8r"
{
  "data": {
    "order_id": "...",
    "order_reference": "OD-...",
    "status": "...",
    "payment": {}
  }
}
```

The exact Order and Payment representations are defined later.

Do not finalize provider-specific payment fields here.

---

# 55. Checkout → Order Boundary

The contract should explicitly document:

> A successful Checkout creates an Order.

The Order becomes a separate business resource after creation.

---

# 56. Cart After Successful Checkout

After successful Order creation:

```text id="h3r1kq"
active Cart
```

must no longer represent the same unprocessed purchasable state.

The project must choose one approach:

```text id="q4f70q"
clear Cart items
```

or:

```text id="7ljpqm"
mark Cart inactive/completed
```

Choose the approach that aligns with the Cart contract from Phase 1.21.

---

# 57. Cart After Failed Checkout

A failed Checkout must normally preserve the customer's Cart.

Example:

```text id="wp86zt"
INSUFFICIENT_STOCK
↓
Cart remains
↓
Customer adjusts quantity
↓
Retry
```

Do not empty the Cart on ordinary validation failure.

---

# 58. Cart After Payment Failure

Payment-specific behavior is later in Group H.

However:

> A payment failure must not automatically destroy valid customer Cart intent unless the approved payment/order workflow explicitly requires it.

The Checkout/Payment phases must agree on this.

---

# 59. Partial Order Creation Risk

The implementation must not create an incomplete Order and then return a generic failure without clear reconciliation.

For example:

```text id="9qpsxv"
Order created
↓
response says error
```

can lead to duplicate retries.

Transaction/idempotency design must prevent this ambiguity.

---

# 60. Transaction Boundary

Checkout should conceptually have a protected transaction boundary around the state-changing business operation.

The later implementation must decide exactly where:

```text id="8wzz4s"
inventory changes
order creation
cart transition
```

occur atomically.

Do not design SQL transactions here.

---

# 61. Inventory Reservation vs Checkout

Do not assume:

```text id="9g44oh"
Add to Cart
→ reserve
```

The approved Cart contract states:

> Cart does not reserve inventory.

Checkout is the point where inventory authority becomes critical.

---

# 62. Inventory Reservation Decision

The implementation may:

```text id="52gboe"
reserve
or
atomically consume
```

stock as part of the Order/payment workflow.

This depends on Payment Group H and the Order lifecycle.

Record the dependency.

---

# 63. Order Creation Timing

This must be aligned with payment design.

Potential workflows include:

```text id="b0n9qo"
Option A:
Order → Payment Pending → Payment → Paid

Option B:
Payment authorization → Order → Paid

Option C:
Order created → delivery fee adjustment → Payment
```

The correct option depends on the business/payment decisions.

Do not invent the final payment state model here.

---

# 64. Delivery Fee + Payment Dependency

Because Staff/Admin can add the delivery fee, the contract must ensure:

> The final amount used for payment is the same authoritative amount stored by the Order.

Avoid:

```text id="a8f1l0"
customer pays before fee known
↓
staff changes fee
↓
database total differs from payment amount
```

This must be resolved before payment implementation.

---

# 65. Checkout Validation Errors

Potential codes:

```text id="9j3j2s"
AUTHENTICATION_REQUIRED
CART_EMPTY
CART_INVALID
PRODUCT_NOT_PURCHASABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK
INVALID_FULFILLMENT
INVALID_DELIVERY_INFORMATION
CONFLICT
```

Only use codes that are part of the final API registry.

---

# 66. Checkout Error Behavior

If Checkout fails validation:

```text id="q4z4uk"
No Order should be accidentally created.
No unintended inventory reduction should occur.
No duplicate payment attempt should occur.
Cart should remain usable where safe.
```

---

# 67. Checkout Error and Customer UX

The client should be able to distinguish:

```text id="g7y17h"
authentication problem
input problem
stock problem
product problem
fulfillment problem
temporary conflict
```

without parsing free-text messages.

---

# 68. Checkout and Authorization Errors

If the customer is not authenticated:

```text id="bz2qsr"
401
```

If authenticated but somehow requests unauthorized cart data:

```text id="df3yyj"
authorization/ownership protection
```

Use the Phase 1.16 contract.

---

# 69. Checkout and Private Data

The Checkout endpoint is private.

Do not allow public caching.

The response may include:

```text id="gz1j6h"
address
customer information
order financial data
```

and therefore must be protected.

---

# 70. Cache Rules

Checkout mutation responses must not be publicly cached.

Conceptually:

```text id="0k6shh"
POST Checkout
→ no shared caching
```

---

# 71. Checkout Query Parameters

Checkout should not require query parameters for normal input.

Keep the transaction input in the request body.

Do not create:

```text id="eu3d5t"
/checkout?delivery_fee=...
```

for authoritative business values.

---

# 72. Checkout Request Size

Because Checkout contains delivery/contact data when needed, reasonable request size limits should apply.

Do not allow arbitrary nested JSON.

---

# 73. Delivery Address Snapshot

A critical Order design consideration:

> The delivery address supplied during Checkout should become part of the resulting Order's historical fulfillment data.

The Order should not depend on a future mutable customer profile for historical address meaning.

This principle must be reflected in the later Order contract.

---

# 74. Do Not Require Saved Address Book

The approved decision is:

> Saved address book is deferred.

Therefore Checkout should capture the necessary delivery address directly rather than requiring:

```text id="w7y2xo"
saved_address_id
```

---

# 75. Checkout Address Ownership

The customer is allowed to provide delivery information.

The backend validates the structure.

The customer cannot use address input to alter:

```text id="g4j48d"
order ownership
customer identity
delivery fee authority
```

---

# 76. Delivery Contact Information

The exact required fields must come from the approved business/data model.

Do not force unnecessary fields into Checkout simply because a generic ecommerce template normally includes them.

---

# 77. Self-Pickup

For:

```text id="x4f0iw"
PICKUP
```

the API should not require a delivery address.

The eventual Order should record the fulfillment choice so the customer and Staff can act appropriately.

---

# 78. Fulfillment Type Is Closed

Do not let clients send:

```text id="w5k9ws"
COURIER
EXPRESS
LOCAL_DELIVERY
```

in Version 1.

Only approved values exist.

---

# 79. Customer Confirmation

The Checkout contract should make clear whether:

```text id="w8w7sv"
POST /checkout
```

means:

```text "start checkout"
```

or:

```text "place order"
```

For this project, strongly prefer:

> Checkout represents the actual customer submission that creates the Order.

Do not create ambiguous multi-step pseudo-checkouts unless the business requires them.

---

# 80. Recommended Checkout Meaning

Use:

```text id="k2h8tf"
POST /api/v1/checkout
```

to mean:

> Customer submits their current purchase for server validation and Order creation.

This makes the operation easier to reason about for the Flutter app and website.

---

# 81. Checkout Review Before Payment

If payment has a separate interaction, the API may later need:

```text id="f6qljo"
review/quote
```

before the actual payment.

Do not add a separate preview endpoint unless delivery-fee timing or payment workflow truly requires it.

---

# 82. Delivery Fee Timing Decision Gate

Because delivery fee is staff/admin-added, Phase 1.22 must explicitly decide whether Version 1 uses:

### Model A

```text id="3sjm0c"
Customer chooses DELIVERY
→ fee known/configured immediately
→ Checkout total final
→ Payment
```

### Model B

```text id="9a9ymk"
Customer chooses DELIVERY
→ Order created pending fee
→ Staff sets fee
→ Customer pays final amount
```

### Model C

```text id="30c3gl"
another approved workflow
```

Do not proceed to implementation without choosing the model.

This is one of the most important unresolved dependencies for Group H.

---

# 83. Recommended Model for Current Business

Given your stated business process:

> Staff/Admin add the delivery fee directly.

A strong Version 1 model is:

```text id="y9i1h7"
Customer Checkout
       ↓
Order created
       ↓
Staff/Admin reviews delivery
       ↓
Staff/Admin adds delivery fee
       ↓
Customer sees final amount
       ↓
Payment
```

This avoids customer-controlled delivery pricing.

However, it means the Order contract and Payment contract must support a **pending/finalized amount state** before payment.

Do not add a new enum value casually; this must fit the already-approved Order lifecycle.

---

# 84. Alternative Model

If the business can determine the variable delivery fee during checkout before Order creation:

```text id="zid4x2"
Customer Checkout
       ↓
Backend/staff logic determines fee
       ↓
Final total
       ↓
Order
       ↓
Payment
```

This produces a simpler payment flow.

But it requires a clear mechanism for determining the fee before the customer completes checkout.

Do not silently assume this capability exists.

---

# 85. Required Decision

Phase 1.22 must record the approved delivery-fee timing model in:

```text id="5yzio3"
docs/decisions.md
```

This is not a minor implementation choice.

It affects:

```text id="xjw3by"
Order contract
Payment Group H
Order statuses
customer UI
staff workflow
```

---

# 86. Recommended Endpoint Contract Record

In:

```text id="p6ehnf"
docs/api/api-contract.md
```

define:

```markdown id="w1qjbe"
### CHK-001 — Checkout

Method:
POST

Path:
/api/v1/checkout

Authentication:
Required

Actor:
CUSTOMER

Authorization:
Own active Cart

Purpose:
Validate the customer's purchase intent and create the resulting Order.

Input:
fulfillment_type
delivery_address when DELIVERY

Server-controlled:
cart
prices
subtotal
delivery fee
total
order reference
order ownership
order state
inventory state
payment state

Concurrency:
Critical

Idempotency:
Required

Response:
Order/checkout result

Errors:
...
```

---

# 87. Checkout-to-Order Contract Boundary

Document:

```text id="u3g92x"
Checkout defines how purchase intent becomes an Order.

Order API defines how that created Order is subsequently read and operationally managed.
```

Do not duplicate Order status-management behavior here.

---

# 88. Staff Role in Checkout

Staff should not manually "approve" ordinary customer Checkout before an Order can exist unless the business model explicitly requires this.

Normal ecommerce flow should remain:

```text id="g80qzv"
Customer
→ checkout
→ order created
→ staff processes order
```

rather than:

```text id="z9rcjk"
Customer
→ checkout request
→ staff approves
→ order exists
```

unless the chosen delivery-fee model necessarily requires a staff intervention stage.

---

# 89. Staff Role in Delivery Fee

Staff/Admin involvement is specifically permitted for:

```text id="h1hr4o"
adding delivery fee
```

when delivery is selected.

This is an operational responsibility, not a reason to block ordinary product purchasing.

---

# 90. Customer Experience Goal

The normal customer experience should be:

```text id="f8q9gr"
Browse
 ↓
Add to Cart
 ↓
Checkout
 ↓
Choose Pickup/Delivery
 ↓
Provide delivery information if needed
 ↓
Receive/order confirmation according to fee/payment model
 ↓
Track Order
```

No unnecessary staff approval should be added.

---

# 91. Checkout and Next.js

The Next.js application must be able to:

```text id="i77e3y"
submit checkout
receive validation errors
display stock problems
display total/fee information
recover from network retry
```

without implementing business authority in the browser.

---

# 92. Checkout and Flutter

Flutter must be able to:

```text id="4z1hgu"
submit checkout
retry safely
handle timeout
display resulting Order
handle stock conflict
continue payment flow
```

using the same API contract.

---

# 93. Checkout and Admin/Staff

Staff/Admin clients must be able to interact with the resulting Order later.

Checkout itself should not expose administrative operations.

---

# 94. Security Review

The checkout design must be checked against:

```text id="pygg5j"
IDOR
price manipulation
delivery-fee manipulation
quantity manipulation
stock race
order ownership manipulation
role tampering
duplicate submission
replay
payment spoofing
cart substitution
```

Every issue must have an explicit defense.

---

# 95. Critical Security Rule — Financial Authority

The server is the source of truth for:

```text id="idvnxk"
unit price
line subtotal
cart subtotal
delivery fee
order total
currency
payment amount
```

The customer supplies only purchase intent.

---

# 96. Critical Security Rule — Inventory Authority

The server is the source of truth for:

```text id="k1pc7u"
availability
stock
reservation/consumption
```

The client only supplies desired quantity.

---

# 97. Critical Security Rule — Identity Authority

The authenticated session determines:

```text id="1a3qji"
customer
cart owner
order owner
```

The client must not override any of these.

---

# 98. Critical Security Rule — Time Authority

The client must not control:

```text id="d78pgy"
checkout timestamp
cancellation timestamp
payment confirmation timestamp
```

The server determines authoritative times.

This matters because the Order cancellation window is 20 minutes.

---

# 99. Critical Security Rule — State Authority

The client may request an operation.

The backend decides:

```text id="ikfhmg"
whether the operation is valid
what state transition occurs
```

The client must not submit a desired Order status as an authoritative fact.

---

# 100. Required Documentation Updates

### `docs/api/api-contract.md`

Add the full:

```text id="8i1zj4"
## Checkout API Contract

### CHK-001 — Checkout
### Request
### Input Fields
### Fulfillment
### Delivery
### Financial Authority
### Inventory Authority
### Order Creation
### Idempotency
### Concurrency
### Response
### Errors
### Authorization
### Security
### Client Behavior
```

---

### `docs/api/api-resources.md`

Update:

```text id="vwv0ob"
Cart
Checkout
Order
Fulfillment
```

to show the Checkout relationships.

---

### `docs/api/api-conventions.md`

Add reusable:

```text id="ef8t02"
checkout idempotency
server-authoritative financial calculation
inventory revalidation
customer ownership
transaction boundary principles
```

---

### `docs/domain/business-rules.md`

Confirm:

```text id="1m4t9o"
Checkout requires authentication.
Checkout operates on customer's own Cart.
MADE_TO_ORDER cannot enter normal checkout.
Delivery requires delivery information.
Delivery fee is not customer-controlled.
Staff/Admin may add delivery fee.
Cart does not reserve inventory.
Checkout revalidates inventory.
Checkout recalculates pricing.
```

---

### `docs/decisions.md`

Record the important Checkout decisions.

Recommended decision entries:

```text id="2z1k1c"
### CHK-001 — Checkout Requires Authentication

### CHK-002 — Checkout Uses Customer's Own Active Cart

### CHK-003 — Checkout Is a Dedicated Business Workflow

### CHK-004 — Checkout Revalidates Inventory

### CHK-005 — Checkout Recalculates Authoritative Pricing

### CHK-006 — Customer Cannot Set Delivery Fee

### CHK-007 — Checkout Requires Idempotency

### CHK-008 — MADE_TO_ORDER Products Cannot Enter Normal Checkout
```

And critically:

```text id="b6mjf9"
### CHK-009 — Delivery Fee Timing

Document the approved fee-before-order or fee-after-order model.
```

---

# 101. Required Checkout Matrix

Add:

| Attribute            | Version 1                     |
| -------------------- | ----------------------------- |
| Endpoint             | `POST /api/v1/checkout`       |
| Actor                | Customer                      |
| Authentication       | Required                      |
| Cart                 | Own active Cart               |
| Guest checkout       | Not allowed                   |
| Fulfillment          | `PICKUP`, `DELIVERY`          |
| Delivery address     | Required for DELIVERY         |
| Delivery fee         | Server/staff/admin controlled |
| Customer total input | Not allowed                   |
| Product price input  | Not allowed                   |
| Stock input          | Not allowed                   |
| Order status input   | Not allowed                   |
| Idempotency          | Required                      |
| Inventory check      | Authoritative                 |
| Pricing              | Server-calculated             |
| MADE_TO_ORDER        | Not purchasable via checkout  |

---

# 102. Required Validation Matrix

Create:

| Validation              |           Required |
| ----------------------- | -----------------: |
| Authentication          |                Yes |
| Cart ownership          |                Yes |
| Cart not empty          |                Yes |
| Product exists          |                Yes |
| Product purchasable     |                Yes |
| Variant valid           |    When applicable |
| Quantity valid          |                Yes |
| Current stock available |                Yes |
| Fulfillment valid       |                Yes |
| Delivery information    |      When DELIVERY |
| Delivery fee authority  | Server/staff/admin |
| Current price           |             Server |
| Final total             |             Server |
| Idempotency             |                Yes |

---

# 103. Required Security Test Cases

Document future test cases for:

```text id="8ybq5s"
Unauthenticated checkout
Customer A checks out Customer B's Cart
Empty Cart checkout
MADE_TO_ORDER checkout
Invalid Variant
Variant from another Product
Insufficient stock
Concurrent checkout
Tampered price
Tampered subtotal
Tampered total
Tampered delivery fee
Tampered order status
Tampered customer ID
Duplicate checkout
Same idempotency key with different input
Network retry after successful checkout
```

These must eventually become automated tests.

---

# 104. Required Workflow Tests

At minimum:

### Pickup

```text id="q8xg8q"
Customer
→ authenticated
→ Cart
→ PICKUP
→ Checkout
→ Order
```

### Delivery

```text id="jwz4eu"
Customer
→ authenticated
→ Cart
→ DELIVERY
→ delivery address
→ Checkout
→ Order/delivery workflow
```

### Insufficient stock

```text id="ixyn6s"
Customer
→ Cart
→ Checkout
→ stock conflict
→ no accidental duplicate/incomplete order
```

### Retry

```text id="g0nh0d"
Customer
→ Checkout
→ network timeout
→ retry same idempotency key
→ original operation reconciled
```

---

# 105. Explicitly Out of Scope

Do NOT:

```text id="zn5ee8"
Implement Checkout controller
Implement Laravel transaction
Implement inventory locking
Implement Order model
Implement Order API
Implement Payment provider
Implement payment webhook
Implement delivery-fee Staff UI
Implement customer checkout UI
Implement Flutter checkout UI
Create database tables
Create migrations
Create OpenAPI schema
```

---

# 106. Definition of Done

Phase 1.22 is complete when:

1. The Checkout endpoint is explicitly defined.
2. Checkout requires an authenticated customer.
3. Checkout uses the customer's own active Cart.
4. Guest checkout is explicitly prohibited.
5. Checkout is a dedicated business workflow.
6. `PICKUP` and `DELIVERY` are defined as the Version 1 fulfillment types.
7. Version 1 fulfillment enums remain CLOSED.
8. Delivery address is conditionally required.
9. Delivery fee authority is explicitly assigned to Staff/Admin/backend.
10. Customer cannot supply an authoritative delivery fee.
11. Customer cannot supply an authoritative total.
12. Customer cannot supply an authoritative price.
13. Customer cannot supply an authoritative stock quantity.
14. Customer cannot supply an authoritative order status.
15. Product/variant state is revalidated at Checkout.
16. Inventory is revalidated authoritatively.
17. Checkout is concurrency-sensitive.
18. Checkout requires idempotency.
19. Empty Cart checkout is prohibited.
20. MADE_TO_ORDER products cannot enter normal Checkout.
21. Failed Checkout preserves the Cart where safe.
22. Successful Checkout results in an Order.
23. Checkout/Order/payment boundaries are explicitly documented.
24. Delivery-fee timing is explicitly decided.
25. Customer ordering remains operationally normal and does not require unnecessary Staff approval.
26. Next.js and Flutter requirements are covered.
27. Staff/Admin responsibilities are clearly separated from Customer authority.
28. Payment implementation remains assigned to **Phase Group H**.
29. Consolidated documentation is updated.
30. No implementation code has been written.

---

# 107. STOP CONDITION — Mandatory

After updating:

```text id="9zwxas"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the security/workflow review:

**STOP.**

Do not implement Checkout.

Do not create Laravel Checkout controllers.

Do not implement inventory locking.

Do not implement payment.

Do not create Order endpoints yet.

Do not build the checkout UI.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.23 — Define Order API Contract

That phase should take the Order created by Checkout and define the authoritative Version 1 Order resource, including:

```text id="45orqp"
Order identity / OD-***** reference
order items and historical pricing
order status lifecycle
customer ownership
pickup vs delivery
variable delivery fee
payment relationship
order tracking
20-minute customer cancellation
Staff order processing
Admin operational authority
status history
historical-data immutability
concurrency/state transitions
```

with **payment provider behavior still remaining in Phase Group H**.
