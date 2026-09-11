# Phase 3.9 — Orders Schema

## Purpose

Implement the core `orders` database schema and Eloquent model for the furniture ecommerce system.

The Order is the system's **authoritative commercial transaction record**.

Once created, an Order must preserve the business facts required to understand what the customer purchased and how the order was fulfilled, independently of future changes to:

* Product names;
* Product prices;
* Product images;
* Product availability;
* Product categories;
* customer profile/contact information;
* delivery-fee rules.

The Order schema must support:

* authenticated customer ownership;
* immutable order reference;
* fulfillment type;
* order lifecycle state;
* delivery-fee lifecycle;
* authoritative monetary totals;
* delivery recipient/contact snapshot;
* delivery address snapshot;
* future Order Items;
* future Order Status History;
* future Payment;
* future Delivery.

This phase establishes the **Order parent record only**.

Do not implement Order Items, Payment, Delivery, status history, checkout, order transitions, or customer/staff order APIs.

---

# Dependencies

Required:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions completed.
* Phase 3.3 Categories Schema completed.
* Phase 3.4 Products Schema completed.
* Phase 3.5 Product Variants Schema completed.
* Phase 3.6 Product Images Schema completed.
* Phase 3.7 Inventory Schema completed.
* Phase 3.8 Cart Schema completed.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

The Group C roadmap places Orders Schema immediately after Cart Schema and separately schedules Order Items Snapshot and Order Status History.

---

# 3.9.1 Order responsibility

An Order is a **historical commercial record**, not a live view of the Product Catalog.

The authoritative relationship is:

```text id="9b4jfm"
Customer
   ↓
Order
   ├── Order Items              ← Phase 3.10
   ├── Status History           ← Phase 3.11
   ├── Payment                  ← Phase 3.12
   └── Delivery                 ← Phase 3.13
```

The Order itself owns the commercial transaction-level facts:

```text id="yqj4v5"
customer
order reference
fulfillment type
order status
delivery-fee state
financial totals
recipient/contact snapshot
delivery-address snapshot
timestamps
```

Do not make the Order depend on current Product or Variant values to reconstruct historical state.

---

# 3.9.2 Order table

Create:

```text id="qf2g3e"
orders
```

with the following conceptual structure:

```text id="9m9gts"
id
customer_id
order_reference

status
fulfillment_type
delivery_fee_status

currency

subtotal_amount
delivery_fee_amount
total_amount

recipient_name
recipient_phone
delivery_address

created_at
updated_at
```

Recommended Laravel migration:

```php id="9j0rwj"
Schema::create('orders', function (Blueprint $table) {
    $table->id();

    $table->foreignId('customer_id')
        ->constrained('users')
        ->restrictOnDelete();

    $table->string('order_reference', 8)->unique();

    $table->string('status');
    $table->string('fulfillment_type', 20);
    $table->string('delivery_fee_status', 20);

    $table->char('currency', 3)->default('TZS');

    $table->unsignedBigInteger('subtotal_amount');
    $table->unsignedBigInteger('delivery_fee_amount')->nullable();
    $table->unsignedBigInteger('total_amount')->nullable();

    $table->string('recipient_name', 255)->nullable();
    $table->string('recipient_phone', 50)->nullable();

    $table->json('delivery_address')->nullable();

    $table->timestamps();

    $table->index([
        'customer_id',
        'created_at',
    ]);

    $table->index([
        'status',
        'created_at',
    ]);

    $table->index([
        'fulfillment_type',
        'status',
    ]);

    $table->index([
        'delivery_fee_status',
        'status',
    ]);
});
```

Adapt exact syntax to the project's existing Laravel/MySQL conventions.

Do not duplicate indexes that are already covered by uniqueness or foreign-key/index definitions.

---

# 3.9.3 Customer ownership

Use:

```text id="j9skm8"
customer_id
```

as the Order owner.

It references the shared:

```text id="oq2c9b"
users.id
```

identity from Phase 3.1.

Do not create:

```text id="2zvssj"
customers
customer_accounts
order_customers
```

as another ownership source.

The Order owner is derived from the authenticated Customer at checkout.

The client must never choose:

```json id="5e5s6z"
{
  "customer_id": "another-user"
}
```

as authoritative ownership.

The frozen contract explicitly states that Order ownership comes from the authenticated principal and that client-supplied customer identity is rejected.

---

# 3.9.4 Customer deletion behavior

Use restrictive deletion:

```text id="s1p2oe"
User
  ↓
Order
```

Do not cascade-delete Orders when a Customer is deleted.

Orders are historical financial/business records.

A customer with historical Orders must not have those Orders silently destroyed through account lifecycle operations.

The platform already requires historical order preservation.

Any future privacy/account-deletion workflow must explicitly address historical records.

---

# 3.9.5 Order reference

Create:

```text id="3q83u5"
order_reference
```

as the customer-facing Order reference.

V1 format:

```text id="3n3gdt"
OD-*****
```

Example:

```text id="ft85mb"
OD-4K7P9
```

The reference must be:

* server-generated;
* unique;
* stable;
* immutable after Order creation;
* not derived solely from the database numeric ID;
* safe for customer communication.

Do not allow clients to submit or change it.

The frozen conventions explicitly identify `OD-...` as a server-controlled immutable Order reference.

---

# 3.9.6 Order reference generation

Do not implement the complete Order creation/reference-generation service in Phase 3.9.

The schema must provide a unique storage boundary.

The future checkout/order-creation workflow must generate references that:

* comply with `OD-*****`;
* contain exactly the approved number of reference characters;
* avoid ambiguous generation collisions;
* retry safely if an unexpected uniqueness collision occurs.

Do not use:

```text id="fmb4aj"
OD-{database-id}
```

as the only generation strategy.

Do not expose the numeric database ID as the customer-facing reference.

---

# 3.9.7 Order status

Use the frozen V1 status vocabulary:

```text id="ql3c1c"
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

These values are CLOSED.

Do not add:

```text id="10p9kk"
ON_HOLD
REFUNDED
RETURNED
PARTIALLY_SHIPPED
AWAITING_CONFIRMATION
FAILED
```

in this phase.

Adding a status later requires compatibility review.

The project explicitly defines the nine V1 Order statuses and requires backend-controlled transitions.

---

# 3.9.8 Order initial status

The Order created by checkout begins as:

```text id="i4s4ec"
PENDING_PAYMENT
```

This is the authoritative initial state.

Do not create an Order directly as:

```text id="wdrm9x"
PAID
```

because payment confirmation is a separate business event.

Do not let the client provide:

```json id="o7xg9v"
{
  "status": "PAID"
}
```

as part of checkout.

The project explicitly requires the server to create the Order and determine state.

---

# 3.9.9 Status transition boundary

Do not implement transitions in Phase 3.9.

The later Order lifecycle phase will implement:

```text id="j1y0po"
PENDING_PAYMENT
→ PAID
→ ACCEPTED
→ PROCESSING
→ fulfillment branch
→ COMPLETED
```

with:

```text id="vk7d1m"
PICKUP:
PROCESSING
→ READY_FOR_PICKUP
→ COMPLETED
```

and:

```text id="8d9ydn"
DELIVERY:
PROCESSING
→ SHIPPED
→ DELIVERED
→ COMPLETED
```

The frozen contract requires these fulfillment-aware transitions.

Do not implement state-transition services, action endpoints, or status history yet.

---

# 3.9.10 Fulfillment type

Use:

```text id="hzqxyv"
fulfillment_type
```

with the CLOSED V1 values:

```text id="f2gx9g"
PICKUP
DELIVERY
```

Pickup is free.

Delivery may have a server-controlled delivery fee.

Do not add:

```text id="l4d1m0"
SHIPPING
COURIER
EXPRESS
SELF_DELIVERY
WAREHOUSE_PICKUP
```

as separate V1 fulfillment types.

The existing contract explicitly restricts the values to `PICKUP` and `DELIVERY`.

---

# 3.9.11 Delivery-fee status

Create:

```text id="3lq5f4"
delivery_fee_status
```

with CLOSED values:

```text id="f2xshy"
PENDING
FINALIZED
```

Semantics:

### PICKUP

```text id="0yg90a"
delivery_fee_status = FINALIZED
delivery_fee_amount = 0
```

### DELIVERY before Staff/Admin fee assignment

```text id="n3h4q1"
delivery_fee_status = PENDING
delivery_fee_amount = null
total_amount = null
```

### DELIVERY after fee finalization

```text id="j0k6f7"
delivery_fee_status = FINALIZED
delivery_fee_amount = approved amount
total_amount = subtotal_amount + delivery_fee_amount
```

The frozen checkout/order contract defines this Model B fee lifecycle and requires `PENDING_PAYMENT → PAID` to wait until the delivery fee is finalized.

---

# 3.9.12 Delivery fee authority

`delivery_fee_amount` is server-controlled.

The customer does not choose the authoritative delivery fee during checkout.

The project specifically establishes that Staff/Admin finalizes delivery fees and that the approved fee cannot be overridden by the customer.

Do not implement the delivery-fee action in Phase 3.9.

---

# 3.9.13 Currency

Use:

```text id="5o4v1c"
currency
```

with:

```text id="3l5k4z"
TZS
```

for V1.

Do not allow a customer to choose:

```text id="q8j3jz"
USD
EUR
KES
GBP
```

during checkout.

All Order monetary values use the same money contract:

```text id="i0e4ig"
integer minor units
currency = TZS
```

The frozen API convention requires `{amount, currency}` and integer minor units throughout product/variant/order/payment pricing.

---

# 3.9.14 Monetary storage

Persist Order monetary values as integer amount columns:

```text id="h9c1so"
subtotal_amount
delivery_fee_amount
total_amount
```

Do not use:

```text id="j0h2rs"
DECIMAL(12,2)
FLOAT
DOUBLE
```

for Order money.

The API resource layer later converts database storage into:

```json id="b8k6m6"
{
  "amount": 35000000,
  "currency": "TZS"
}
```

Never use floating-point arithmetic for financial calculations.

---

# 3.9.15 Subtotal

`subtotal_amount` is required.

It represents the authoritative sum of historical Order Item line totals at Order creation.

Conceptually:

```text id="fb8o2k"
subtotal =
    line_total_1
  + line_total_2
  + ...
```

Do not calculate subtotal from the current Product/Variant price later.

That calculation belongs to Order creation/checkout.

The current product catalog can change without changing a historical Order.

---

# 3.9.16 Delivery fee amount

`delivery_fee_amount` is nullable.

Rules:

```text id="pj03o5"
PICKUP
→ 0

DELIVERY + PENDING
→ null

DELIVERY + FINALIZED
→ non-negative approved amount
```

Do not use:

```text id="iu5fs1"
0
```

to mean "delivery fee not yet determined".

The contract intentionally distinguishes `null` from an actual zero fee.

---

# 3.9.17 Total amount

`total_amount` is nullable until the Order's delivery fee is finalized.

Rules:

```text id="k8q4dt"
delivery_fee_status = PENDING
→ total_amount = null
```

and:

```text id="q8okhz"
delivery_fee_status = FINALIZED
→ total_amount = subtotal_amount + delivery_fee_amount
```

For PICKUP:

```text id="t0p7p8"
delivery_fee_amount = 0
total_amount = subtotal_amount
```

Do not store a provisional total as though it were the final payable amount.

The project explicitly states that the provisional delivery-fee state does not produce a final payment total.

---

# 3.9.18 Financial immutability

Once an Order is paid/finalized through the later workflow:

```text id="m7o30x"
subtotal_amount
delivery_fee_amount
total_amount
currency
```

represent historical authoritative financial facts.

Current catalog pricing must never rewrite these values.

The project explicitly requires historical financial data to remain immutable and states that product price changes do not rewrite historical Order values.

Do not implement financial correction workflows in this phase.

---

# 3.9.19 Recipient snapshot

Store:

```text id="kq3dnw"
recipient_name
recipient_phone
```

as part of the Order snapshot.

These are historical transaction/contact facts.

A later customer profile change must not rewrite:

```text id="u5e3j1"
Order.recipient_name
Order.recipient_phone
```

The frozen contract explicitly requires recipient/contact snapshot preservation.

Do not derive historical Order contact information from `users.name` or `users.phone` after Order creation.

---

# 3.9.20 Recipient phone

`recipient_phone` stores the phone number used for fulfillment/contact at checkout.

Do not assume the customer's current profile phone remains identical.

The value is a snapshot.

Use the project's established phone normalization/validation conventions at the checkout boundary later.

Do not implement phone normalization in Phase 3.9.

---

# 3.9.21 Delivery address snapshot

Store:

```text id="4spc0r"
delivery_address
```

as a JSON object.

Do not create a reference to the customer's future/default address.

V1 explicitly does not use a saved address book and requires the address to be stored per Order as a snapshot.

The expected structure should remain compatible with:

```json id="mrk0ha"
{
  "address_line": "123 Example Street",
  "city": "Dar es Salaam",
  "region": "Dar es Salaam",
  "postal_code": null
}
```

Use only fields formally supported by the checkout contract when implementing the request layer.

Do not invent additional address fields merely because a standard ecommerce system might have them.

---

# 3.9.22 Pickup address behavior

For:

```text id="q6q6a4"
PICKUP
```

`delivery_address` must be:

```text id="e5vsu9"
null
```

Do not store an empty object:

```json id="q4l0im"
{}
```

and do not store an empty string.

The project explicitly treats `delivery_address: null` as the appropriate representation for PICKUP.

---

# 3.9.23 Delivery address immutability

Once the Order is created:

```text id="xm5h88"
delivery_address
recipient_name
recipient_phone
```

become historical snapshots.

A future customer profile/address update must not modify them.

A later authorized operational correction workflow must be explicit and audited if ever required.

Do not implement address correction in this phase.

---

# 3.9.24 No saved address relationship

Do not add:

```text id="m8s48w"
shipping_address_id
saved_address_id
customer_address_id
```

to Orders.

The V1 address book is deferred.

The Order carries its own snapshot.

This prevents historical Orders from changing when a customer's address book changes.

---

# 3.9.25 Payment separation

Do not create:

```text id="j3m0v8"
payment_id
payment_status
provider_transaction_id
provider_reference
```

as part of the core Order schema unless an existing approved dependency requires a specific relationship placeholder.

Payment has its own Phase 3.12 schema.

The project explicitly keeps provider-specific payment details in Group H and separates the Payment entity from the Order.

An Order may eventually have a related Payment record.

Do not duplicate payment state into arbitrary Order columns.

The `PAID` Order state belongs to the Order lifecycle, while the detailed payment record belongs to the Payment domain.

---

# 3.9.26 Delivery separation

Do not create:

```text id="u4fc1w"
delivery_status
tracking_number
tracking_url
courier
carrier
delivery_driver
```

on Orders.

Delivery has its own Phase 3.13 schema.

The current V1 model treats fulfillment as a small-scale operational process rather than a full logistics platform, and tracking is based on Order Status History rather than GPS/carrier tracking.

---

# 3.9.27 Order status history separation

Do not create:

```text id="hmj1v4"
previous_status
status_history_json
status_events_json
```

inside `orders`.

Phase 3.11 establishes an append-only `order_status_history`.

The Order stores only its current status.

Historical transitions must not be reconstructed from scattered timestamps or JSON blobs.

---

# 3.9.28 Order Items separation

Do not store product lines directly in the `orders` table.

Do not add:

```text id="n2czix"
product_ids
variant_ids
items_json
line_items_json
```

to Orders.

Phase 3.10 establishes the relational Order Item Snapshot model.

This is important because individual Order Items need historical snapshots of:

```text id="u9m6jq"
product_id
variant_id
sku
name
variant_name
unit_price
quantity
line_total
```

The project explicitly defines these snapshot fields and their immutability.

---

# 3.9.29 Order creation source

Orders are eventually created by:

```text id="wsy9cj"
authenticated customer
→ active Cart
→ checkout validation
→ current pricing
→ inventory validation
→ fulfillment validation
→ Order creation
```

The customer does **not** directly create arbitrary Orders.

The checkout boundary is specifically responsible for creating the Order from validated server state.

Do not implement checkout in Phase 3.9.

---

# 3.9.30 Order creation atomicity

The future Order creation process must be able to execute the relevant state changes atomically.

Conceptually:

```text id="2gshxx"
validate Cart
→ validate inventory
→ resolve pricing
→ calculate subtotal
→ create Order
→ create Order Items
→ transition Cart
```

must not leave:

```text id="uv46i6"
Order without valid items
reservation without Order
Order without required financial values
Cart cleared without Order
```

The project requires transaction-safe checkout and failure atomicity.

Do not implement that transaction in Phase 3.9.

---

# 3.9.31 Cancellation readiness

The Order schema must support the later 20-minute customer cancellation rule.

The authoritative rule is:

```text id="o3l0gx"
authenticated customer
+ owns Order
+ current status = PENDING_PAYMENT
+ now - created_at <= 20 minutes
→ cancellation eligible
```

The server determines the time.

Do not add:

```text id="8vfhzy"
client_cancelled_at
cancel_window_expired
can_cancel
```

columns.

The existing contract states that cancellation uses backend time and `created_at`; cancelled Orders remain readable rather than being deleted.

Do not implement cancellation in Phase 3.9.

---

# 3.9.32 Timestamps

Use:

```text id="x3stcb"
created_at
updated_at
```

and preserve the standard Laravel timestamp behavior.

`created_at` is important because the later cancellation rule uses it as the starting point of the 20-minute window.

Do not allow clients to submit or modify:

```text id="h8m89p"
created_at
updated_at
```

The project's global convention requires server-controlled timestamps.

---

# 3.9.33 Soft deletion

Do **not** add SoftDeletes to Orders.

Orders are historical records and should remain readable.

Cancellation is represented by:

```text id="r9w6u4"
status = CANCELLED
```

not by deletion.

The frozen Order contract explicitly states that cancellation is not deletion and cancelled Orders remain readable.

Do not create a `deleted_at` column.

---

# 3.9.34 Order query indexes

Provide indexes for the expected access patterns:

```text id="aw8ypr"
customer_id + created_at
status + created_at
fulfillment_type + status
delivery_fee_status + status
```

Customer order history will commonly query:

```text id="4oyl4h"
customer_id
ORDER BY created_at DESC, id ASC
```

Staff operational queues will commonly filter by Order status.

Do not add speculative indexes for future payment/provider fields that do not exist yet.

---

# 3.9.35 Deterministic order retrieval

Later Order collections must use deterministic ordering.

For customer history:

```text id="h3x6f0"
created_at DESC
id ASC
```

For operational queues, use the applicable documented order plus:

```text id="7o6jz9"
id ASC
```

as tie-breaker where required.

The global convention requires deterministic tie-breaking for pagination.

Do not implement the collection API in this phase.

---

# 3.9.36 Order model

Create:

```text id="y3q18x"
App\Models\Order
```

with:

```php id="u5sv5j"
public function customer(): BelongsTo
{
    return $this->belongsTo(User::class, 'customer_id');
}
```

Do not add relationships to future entities unless those models/tables already exist.

When later phases create:

```text id="83x9l5"
OrderItem
OrderStatusHistory
Payment
Delivery
```

their relationships should be added in their respective phases.

---

# 3.9.37 Order casts

Use centralized enum/value-object casts for:

```text id="d7d13e"
status
fulfillment_type
delivery_fee_status
```

Use appropriate numeric casts for:

```text id="85kgv0"
subtotal_amount
delivery_fee_amount
total_amount
```

Do not cast financial values to floating point.

Cast:

```text id="5bsg2n"
delivery_address
```

to an appropriate structured PHP representation if the project's Laravel conventions use JSON casts.

Do not expose internal database structures directly through the API.

---

# 3.9.38 Domain constants/enums

Centralize CLOSED Order values.

Do not scatter:

```text id="i4g1jy"
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

as unrelated string literals throughout models/tests/services.

Use a dedicated enum or equivalent project-standard constants.

Likewise centralize:

```text id="n8vg5x"
PICKUP
DELIVERY
PENDING
FINALIZED
TZS
```

where shared across the Order domain.

Do not create a giant global constants class containing unrelated application strings.

The existing API contract treats these enums as CLOSED and stable.

---

# 3.9.39 Maintainability requirements

Apply the project's established maintainability standards.

Any new or refactored function must have:

```text id="r1q8h8"
cognitive complexity <= 15
```

and:

```text id="6s0u5l"
no more than 3 return statements
```

If an affected existing function exceeds either threshold:

* refactor it;
* extract cohesive decision logic;
* reduce nesting;
* retain existing behavior;
* keep refactoring scoped to the affected area.

Do not increase analyzer thresholds.

Do not suppress complexity warnings.

---

# 3.9.40 Repeated string literals

Do not duplicate meaningful Order-domain literals throughout the implementation.

Use appropriate enums/constants for:

```text id="8fdgwm"
Order statuses
Fulfillment types
Delivery-fee statuses
TZS currency
```

Do not replace every ordinary string with a constant.

Only centralize literals that represent shared business/protocol concepts.

Tests should reuse the same semantic enum/constants where appropriate.

---

# 3.9.41 Return-count and complexity verification

Where the project's static-analysis tooling supports these checks, ensure:

```text id="v4vlk7"
cognitive complexity > 15
```

and:

```text id="y3q8rq"
return count > 3
```

are detected.

Do not weaken the tooling to accommodate the Order implementation.

Do not perform unrelated repository-wide cleanup.

---

# 3.9.42 Mass-assignment protection

Future Order mutation endpoints must never use:

```php id="m8g4ef"
$request->all()
```

to populate Order fields.

Use:

```text id="6vwnr2"
validated input
→ explicit allow-list
→ DTO/command
→ authorization
→ domain validation
→ persistence
```

Most Order fields are server-controlled.

Never allow customers to directly mutate:

```text id="t5j2cw"
customer_id
order_reference
status
delivery_fee_status
subtotal_amount
delivery_fee_amount
total_amount
currency
created_at
updated_at
```

or historical snapshots.

The API convention explicitly identifies these categories as server-controlled/read-only.

---

# 3.9.43 Serialization readiness

The future public Customer Order representation may expose:

```text id="7ixn8k"
order_reference
status
fulfillment_type
subtotal
delivery_fee
total
delivery_address
recipient_name
recipient_phone
items
tracking/status timeline
```

according to the frozen resource contract.

Do not expose:

```text id="e5japg"
database IDs unnecessarily
internal financial fields
internal payment provider data
staff-only notes
audit data
internal storage identifiers
```

Staff/Admin representations may expose additional operational information only where explicitly authorized.

The project requires field-level serialization before output and distinguishes customer, operational, and internal data.

---

# 3.9.44 Privacy and caching readiness

Customer Order data is private.

Future Customer Order APIs must use private/no-store caching behavior.

Do not public-cache:

```text id="lx9vsm"
customer_id
delivery address
recipient phone
order totals
order history
```

The Order tracking conventions explicitly require private caching because Orders contain private delivery/financial information.

Do not configure application/CDN caching in Phase 3.9.

---

# 3.9.45 Security and IDOR readiness

Order access must always be evaluated against:

```text id="af6i6z"
authenticated principal
+
Order ownership
+
appropriate operational authorization
+
business state
```

Customer A must never retrieve Customer B's Order.

The API must later use 404 masking where required to avoid revealing that another customer's Order exists.

Do not implement Order authorization middleware or endpoints in this phase.

---

# 3.9.46 Order financial integrity

The schema must preserve the following invariant:

```text id="ztd0ng"
total_amount
=
subtotal_amount
+
delivery_fee_amount
```

only when:

```text id="j5y2kh"
delivery_fee_status = FINALIZED
```

For:

```text id="c3ivym"
PICKUP
```

the invariant is:

```text delivery_fee_amount = 0
total_amount = subtotal_amount
```

For:

```text id="bvsqyg"
DELIVERY + PENDING
```

the authoritative state is:

```text delivery_fee_amount = null
total_amount = null
```

Do not implement final financial calculations in the model itself.

The database/schema provides storage constraints; the later checkout/order domain service owns the calculation.

---

# 3.9.47 Database-level financial constraints

Where supported safely by the target MySQL version, add defensive checks for:

```text id="x4uv2v"
subtotal_amount >= 0
delivery_fee_amount >= 0 when non-null
total_amount >= 0 when non-null
```

and, if the project's migration policy supports it cleanly, enforce valid delivery-fee relationships.

Do not attempt to encode the entire Order state machine in SQL.

Database constraints are the final defense, not a replacement for application/domain validation.

---

# 3.9.48 Order fulfillment consistency

The later domain layer must enforce:

```text id="ztjd7s"
PICKUP
→ no delivery address
→ delivery fee 0
→ pickup lifecycle

DELIVERY
→ delivery address required
→ delivery fee lifecycle applies
→ delivery lifecycle
```

Phase 3.9 should preserve the fields required for those rules.

Do not implement fulfillment state transitions yet.

---

# 3.9.49 Order factories

Create an `OrderFactory` if it provides genuine test value.

The factory should:

* create a valid Customer;
* generate a valid `OD-*****` reference;
* create a valid Order status;
* create a valid fulfillment type;
* generate valid financial amounts;
* generate consistent delivery-fee fields;
* generate a valid recipient/address snapshot for DELIVERY;
* leave delivery address null for PICKUP.

Do not automatically create:

* Order Items;
* Payments;
* Deliveries;
* Status History;
* Inventory reservations;
* Cart mutations.

Those belong to later phases.

---

# 3.9.50 Test scenarios

Create focused tests.

## Ownership tests

Verify:

* Order belongs to User via `customer_id`;
* customer relation resolves correctly;
* deleting a Customer is prevented while historical Orders exist;
* client-controlled Customer identity is not part of the Order model's ordinary mutation surface.

## Order reference tests

Verify:

* reference matches `OD-*****`;
* reference is unique;
* reference is immutable;
* database uniqueness prevents duplicate references.

Do not test a specific fixed reference value.

## Status tests

Verify only:

```text id="k4b5n4"
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

are recognized by the Order model/enum.

Do not implement transition tests yet.

## Fulfillment tests

Verify:

```text id="p7h8n9"
PICKUP
DELIVERY
```

are the only V1 fulfillment types.

## Delivery-fee tests

Verify:

### Pickup

```text
delivery_fee_status = FINALIZED
delivery_fee_amount = 0
```

### Delivery pending

```text
delivery_fee_status = PENDING
delivery_fee_amount = null
total_amount = null
```

### Delivery finalized

```text
delivery_fee_status = FINALIZED
delivery_fee_amount >= 0
total_amount = subtotal + delivery_fee
```

## Financial tests

Verify:

* amounts are integer minor units;
* no floating-point representation is used;
* currency is TZS;
* subtotal cannot be negative;
* delivery fee cannot be negative;
* total cannot be negative;
* provisional delivery state cannot present a final total.

## Address tests

Verify:

* PICKUP permits `delivery_address = null`;
* DELIVERY requires structured address at the future validation boundary;
* Order address is stored as a snapshot;
* Order address is independent of the current customer profile address.

## Historical integrity tests

Verify that changing the current User/Product data does not mutate stored Order-level fields.

Order Item snapshot behavior is tested in Phase 3.10.

## Relationship tests

Verify only relationships implemented by this phase:

```text id="2m7p0w"
Order → customer
Customer → orders
```

Do not create tests for nonexistent future relations.

---

# 3.9.51 Migration tests

Verify:

* fresh database migration succeeds;
* rollback succeeds;
* invalid customer foreign key is rejected;
* duplicate Order reference is rejected;
* status defaults correctly;
* fulfillment type defaults correctly where a default is defined;
* delivery fee state defaults correctly;
* pickup/delivery nullable fields behave correctly;
* monetary amount columns have correct unsigned/integer semantics;
* foreign-key deletion behavior is correct.

---

# 3.9.52 Seed data

Do not seed real customer Orders.

Orders are transactional/historical records and should normally be generated through tests or controlled development fixtures.

If development fixtures are needed:

* use fake customers;
* use deterministic test data;
* use valid references;
* do not create fake payment/provider records;
* do not imply that production order history is seeded.

---

# 3.9.53 Order status as current state only

Do not add multiple status timestamps such as:

```text id="x24m3n"
paid_at
accepted_at
processing_at
shipped_at
delivered_at
completed_at
cancelled_at
```

to the base Orders table in this phase.

Historical lifecycle timestamps belong naturally in the later append-only Order Status History model.

Some individual timestamps might later be derived or cached if performance/business requirements justify it, but do not duplicate them speculatively now.

---

# 3.9.54 No generic administrative mutation

Do not introduce a generic:

```text id="7i8m17"
PATCH /orders/{order}
```

meaning "change any Order field".

The frozen operational convention requires explicit controlled actions for business state changes.

No Order mutation endpoints belong in Phase 3.9.

---

# 3.9.55 Documentation / durable decisions

Update `docs/decisions.md` only for durable decisions such as:

* Order is the historical commercial record;
* customer ownership uses shared `users.id`;
* Order reference format is `OD-*****`;
* Order financial values use integer minor units;
* delivery address/contact are snapshots;
* delivery-fee state is explicit;
* Payment, Delivery, Order Items, and Status History remain separate entities.

Do not create a permanent Phase-3.9 markdown file merely to duplicate this instruction.

---

# Explicitly out of scope

Do not implement:

* Order Items;
* historical Order Item snapshots;
* Order Status History;
* Payment;
* Payment provider integration;
* Delivery;
* delivery tracking;
* checkout;
* Cart-to-Order conversion;
* inventory reservation;
* inventory consumption;
* Order status transition services;
* cancellation service;
* delivery-fee assignment service;
* customer Order APIs;
* Staff Order APIs;
* Admin Order APIs;
* notifications;
* refunds;
* returns;
* partial fulfillment;
* split orders;
* multiple shipments;
* saved address book;
* tax engine;
* coupon/discount engine;
* invoice generation;
* receipt generation;
* email delivery;
* frontend Order pages;
* Flutter Order screens.

---

# Maintainability requirements

Apply the project's maintainability standards to all new/refactored Order-domain code.

### Cognitive complexity

```text id="tbfr7b"
Maximum: 15
```

Any affected function over 15 must be refactored.

### Return statements

```text id="3k2rw8"
Maximum: 3 per function
```

Refactor functions exceeding the limit.

### Duplicated literals

Meaningful repeated Order-domain literals must be centralized through enums/constants where appropriate.

Do not create an indiscriminate global constant dictionary.

### Static analysis

Do not suppress complexity, return-count, duplication, or type-analysis warnings merely to make the phase pass.

---

# Definition of done

Phase 3.9 is complete only when:

1. `orders` exists as the core historical commercial entity.
2. Every Order belongs to a User through `customer_id`.
3. Customer deletion cannot cascade-delete historical Orders.
4. `order_reference` is unique and follows `OD-*****`.
5. Order reference is server-controlled and immutable.
6. Order status uses exactly the approved V1 CLOSED states.
7. Initial Order status is `PENDING_PAYMENT`.
8. Fulfillment type uses exactly `PICKUP` or `DELIVERY`.
9. `delivery_fee_status` uses exactly `PENDING` or `FINALIZED`.
10. Pickup Orders have zero delivery fee and a finalized fee state.
11. Delivery Orders can represent a pending delivery fee.
12. `subtotal_amount` is required.
13. Delivery fee is nullable while pending.
14. Total is nullable until the delivery fee is finalized.
15. Finalized total equals subtotal plus delivery fee.
16. All Order monetary storage uses integer minor units.
17. Currency is explicitly stored as TZS.
18. Recipient name/phone are snapshotted on the Order.
19. Delivery address is snapshotted on the Order.
20. Pickup Orders can store `delivery_address = null`.
21. No saved-address foreign key exists.
22. Payment remains a separate future entity.
23. Delivery remains a separate future entity.
24. Order Items remain a separate future entity.
25. Order Status History remains a separate future entity.
26. Order does not store product lines or item JSON.
27. Order does not store inventory state.
28. Order does not store payment-provider fields.
29. Order does not store delivery logistics fields.
30. Soft deletion is not used for Orders.
31. Eloquent `Order ↔ User` relationship exists.
32. Appropriate indexes and foreign keys are present.
33. Migration succeeds from an empty MySQL database and rolls back successfully.
34. Ownership, reference, status, fulfillment, delivery-fee, financial, address, and integrity tests pass.
35. Formatting and static analysis pass.
36. New/refactored functions have cognitive complexity no greater than 15.
37. New/refactored functions contain no more than 3 return statements.
38. Meaningful duplicated literals are centralized appropriately.
39. No analyzer warnings are suppressed merely to satisfy maintainability requirements.
40. No checkout, payment, delivery, status-transition, or Order API logic has been implemented.
41. Code comments remain minimal.

---

# STOP condition

Stop after the Orders migration, Order model, financial/status/fulfillment structure, relationships, constraints, factory/test support, and required maintainability refactoring are complete.

Do not continue into Phase 3.10 Order Items Snapshot Model.

Do not implement Order Items, payment, delivery, checkout, order transitions, status history, cancellation, or Order APIs.

Do not commit, stage, or push changes.