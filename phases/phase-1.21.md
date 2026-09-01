# Phase 1.21 — Define Cart API Contract

## 1. Purpose

Phase 1.21 defines the **complete Version 1 Cart API contract**.

It builds directly on:

```text id="v5syhi"
Phase 1.20 — Catalog API Contract
```

The Cart contract must establish how an authenticated customer selects purchasable furniture before checkout.

The Cart API must remain:

```text id="0u8bca"
secure
customer-owned
simple
predictable
concurrency-aware
compatible with Next.js
compatible with Flutter
compatible with the later Checkout contract
```

The central rule is:

> **The Cart contains a customer's current purchase intent. It is not an order, payment, inventory ledger, or pricing authority.**

---

# 2. Documentation Strategy

Continue using the consolidated documentation structure.

Update:

```text id="9jeym6"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do **not** create:

```text id="3d4q8p"
cart-api.md
cart-contract.md
cart-decisions.md
cart-validation.md
```

unless the project later becomes large enough to justify a separate domain contract.

---

# 3. Authoritative Project Paths

Use:

```text id="8g3j2c"
AGENTS.md
docs/VISION.md
```

These are authoritative.

---

# 4. Payment Assignment

Do not define payment behavior in this phase.

Payment remains assigned to:

**Phase Group H**

Checkout is also **not** implemented in this phase.

The Cart contract may identify the transition:

```text id="e8cfk7"
Cart
 ↓
Checkout
```

but Checkout gets its own contract later.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

Cart normally has relatively few enums, but any applicable future state/type values must use the canonical closed-enum policy.

Do not introduce undocumented values.

---

# 6. Dependency Position

Current sequence:

```text id="4hcy7r"
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
...
```

Cart must consume the approved Catalog contract.

---

# 7. Authoritative Inputs

Read:

```text id="af2r3x"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Also specifically review the finalized Catalog contract:

```text id="tq5m4y"
Product
Category
Variant
Availability
Price
Identifiers
Public representations
```

And the previous architecture decisions covering:

```text id="1x7x9j"
authentication
authorization
HTTP methods
query conventions
pagination
input conventions
validation
errors
resource relationships
idempotency
```

---

# 8. Core Cart Principle

The Cart represents:

> **The current set of products/variants the authenticated customer intends to purchase.**

It does not guarantee:

```text id="d5s7v0"
stock
price
availability
delivery fee
payment success
order creation
```

Those must be revalidated later.

---

# 9. Cart Ownership

The Cart must belong to exactly one customer account when authenticated.

Conceptually:

```text id="9hzz7x"
Customer
   ↓ owns
Cart
   ↓ contains
Cart Items
```

A customer can only access their own Cart.

---

# 10. Anonymous Browsing vs Anonymous Cart

The project explicitly allows:

```text id="x89s5d"
anonymous browsing
```

This does **not** automatically mean:

```text id="5ofj1n"
anonymous persistent server-side cart
```

Review the current project decisions and determine whether Version 1 has:

```text id="x4lm80"
authenticated cart only
```

or a temporary anonymous cart that can later be merged.

Do not invent a persistent anonymous-cart feature merely because it is technically possible.

For this project, the recommended baseline is:

> **The authoritative server-side Cart belongs to an authenticated customer.**

---

# 11. Guest Shopping Experience

If anonymous users can add items to a local/browser cart before login, that local state must not become authoritative order state.

Conceptually:

```text id="u5y6qp"
Anonymous browser state
        ↓
Login/register
        ↓
Authenticated server Cart
```

The exact frontend/local-cart merge strategy is implementation work later.

---

# 12. Cart Resource

Define the primary Cart resource.

Likely endpoint:

```text id="x9jz1n"
GET /api/v1/me/cart
```

or the approved self-context convention from Phase 1.9.

The customer should not need to know an arbitrary Cart ID merely to access their own active cart unless the final design specifically requires it.

---

# 13. Why `/me/cart` Is Preferred

For a single active customer cart, self-context provides a strong ownership boundary:

```text id="6xosr3"
GET /me/cart
```

rather than:

```text id="rz17o8"
GET /carts/{arbitrary-id}
```

This reduces accidental IDOR risk and avoids making customer clients manage unnecessary Cart identity.

---

# 14. Cart Endpoint Inventory

At minimum define:

```text id="1ih1m7"
CART-001
Get current cart

CART-002
Add item to cart

CART-003
Update cart item

CART-004
Remove cart item
```

Evaluate whether an explicit:

```text id="u2p6m3"
Clear cart
```

operation is useful.

Do not add it merely because many ecommerce systems have one.

---

# 15. Get Cart

Define:

```text id="9xgfr4"
GET /api/v1/me/cart
```

Purpose:

> Retrieve the authenticated customer's current Cart.

Authentication:

```text id="7f3nn8"
Required
```

Authorization:

```text id="2lqvwm"
Own cart only
```

---

# 16. Get Cart Response

The response should provide enough information to render the customer's cart:

```text id="j3n7qk"
cart identity where needed
cart items
product/variant summary
quantity
current display price
line subtotal representation where appropriate
cart subtotal if calculated
```

However:

> Any price displayed in Cart is informational/current and must be revalidated at Checkout.

---

# 17. Cart Item Representation

A Cart Item should provide:

```text id="t6u7gj"
item identity
product reference
variant reference where applicable
product display information
quantity
current price information where useful
line subtotal if provided
```

Do not expose internal inventory details.

---

# 18. Cart Price Semantics

Clearly distinguish:

```text id="3p2rgi"
displayed current catalog price
```

from:

```text id="4y1j7s"
final order transaction price
```

The latter belongs to Checkout/Order.

---

# 19. Cart Total Semantics

A Cart may show:

```text id="8w4h0o"
estimated subtotal
```

for customer convenience.

But:

> Cart subtotal is not the authoritative final amount.

The Checkout process recalculates all financial values.

---

# 20. Add Item

Define:

```text id="k3s5r1"
POST /api/v1/me/cart/items
```

Purpose:

> Add a purchasable Product/Variant to the authenticated customer's Cart.

---

# 21. Add Item Input

The request should contain only customer-controlled fields.

Likely:

```json id="b1bx8l"
{
  "product_id": "...",
  "variant_id": "...",
  "quantity": 2
}
```

Do not accept:

```text id="5w2b3d"
price
subtotal
stock_quantity
order_status
payment_status
delivery_fee
```

as authoritative input.

---

# 22. Product Validation

When adding an item, the backend must verify:

```text id="l8o5fh"
Product exists
Product is publicly/purchasably active
Product type supports normal purchase
```

A `MADE_TO_ORDER` product must not be added to the normal purchase Cart.

The appropriate customer action is the separate request workflow.

---

# 23. Variant Validation

If `variant_id` is provided:

```text id="f7y2as"
variant exists
AND
variant belongs to product
AND
variant is active/purchasable
```

A Variant belonging to another Product must be rejected.

---

# 24. Product-without-Variant

If a product has no variants:

```text id="7m2qre"
variant_id
```

should not be required.

Do not require a fake variant simply to make the API schema uniform.

---

# 25. Variant-Required Product

If a product has variants and the selected purchasable unit is variant-specific:

```text id="nyc35u"
variant_id
```

must be required.

This should be a conditional validation rule.

---

# 26. Quantity

Quantity must be:

```text id="j19n0e"
integer
positive
within approved limits
```

Do not accept:

```text id="kz1vl5"
0
-1
1.5
"two"
```

---

# 27. Quantity Limits

Determine a reasonable Version 1 maximum quantity per Cart Item.

Do not leave this unlimited.

Potential reasons:

```text id="kq01fs"
inventory protection
database constraints
abuse prevention
accidental large-order protection
```

The exact limit should be explicitly recorded rather than inherited accidentally from frontend behavior.

---

# 28. Cart Item Aggregation

Determine behavior when the customer adds an item that already exists in the Cart.

Example:

```text id="y8x7s5"
Cart:
Product A × 2

Customer:
Add Product A × 3
```

Choose one canonical behavior:

```text id="g6ybl5"
increase existing quantity to 5
```

or:

```text id="6tjw7g"
reject duplicate
```

For a normal ecommerce experience, recommend:

> **Merge the item and increase its quantity.**

Do not create duplicate Cart Items for the same purchasable product/variant unless there is a business reason.

---

# 29. Cart Item Identity

Define what makes two Cart Items the same logical purchasable item.

Recommended:

```text id="9o9u5m"
same Product
+
same Variant where applicable
```

If no Variant exists, Product identifies the purchasable item.

---

# 30. Cart Item Uniqueness

The eventual physical design should enforce logical uniqueness of:

```text id="q4kvvl"
Cart + Product + Variant
```

where appropriate.

Do not allow application behavior to depend only on accidental uniqueness.

Physical constraints are later.

---

# 31. Add Item and Current Availability

The API should check current availability when adding an item.

However:

> Successful Cart addition does not reserve inventory.

Therefore:

```text id="kwv3lq"
Cart item added
≠
stock reserved
```

---

# 32. Stock Reservation

Do not reserve inventory merely because an item is in a customer's Cart unless a future explicit business rule introduces cart reservations.

For Version 1, prefer:

```text id="f3h8sk"
Cart
→ intent
Checkout
→ authoritative stock check
```

This avoids holding stock indefinitely in abandoned carts.

---

# 33. Update Cart Item

Define:

```text id="19q5wl"
PATCH /api/v1/me/cart/items/{item}
```

Purpose:

> Change the quantity of an existing Cart Item.

---

# 34. Update Input

Recommended:

```json id="b1u3dd"
{
  "quantity": 3
}
```

Do not submit the entire Cart.

Do not allow:

```text id="65u0ga"
product_id
variant_id
price
```

to change through ordinary quantity PATCH unless a separate operation is explicitly designed.

---

# 35. Changing Product/Variant

Changing:

```text id="c3clp4"
product
→ another product
```

should not be hidden inside quantity update.

Instead:

```text id="g3w7l8"
remove old item
+
add new item
```

This keeps the operation semantics clear.

---

# 36. Remove Cart Item

Define:

```text id="h03uk6"
DELETE /api/v1/me/cart/items/{item}
```

Purpose:

> Remove the item from the customer's Cart.

This is a legitimate use of DELETE.

---

# 37. Clear Cart

Evaluate:

```text id="8izt2s"
DELETE /api/v1/me/cart/items
```

or an explicit:

```text id="5kn3b5"
POST /api/v1/me/cart/clear
```

Do not implement both.

For simplicity, the project may initially omit a dedicated "clear cart" endpoint and let clients remove individual items.

If a clear-all operation is approved, choose one canonical design.

---

# 38. Cart Ownership Security

Every Cart operation must verify:

```text id="u5r8fx"
authenticated customer
+
requested Cart belongs to that customer
```

The customer must not be able to manipulate another customer's Cart.

---

# 39. Cart IDOR Protection

Explicitly test:

```text id="x5n54q"
Customer A
→ attempts Customer B's cart item ID
```

The API must not allow the operation.

Prefer self-context endpoints when practical.

---

# 40. Cart Item ID Exposure

Cart item IDs may be exposed if useful for UI updates/deletion.

However, knowing an item ID must never bypass ownership authorization.

---

# 41. Cart and Product Deactivation

A Product may become unavailable after it was added to the Cart.

The Cart must handle this gracefully.

Possible behavior:

```text id="3g9e7v"
Cart retains item
+
marks it unavailable
```

or:

```text id="3y2n5t"
Cart retrieval excludes/flags unavailable item
```

Choose one predictable approach.

Do not silently delete customer intent without a clear rule.

---

# 42. Cart and Price Changes

A Product price may change after being added.

The Cart should reflect the **current display price** where appropriate but must not imply that the original displayed Cart amount is guaranteed.

At Checkout, price is recalculated from the authoritative catalog state.

---

# 43. Cart and Product Removal

A Product may be:

```text id="0f4sdu"
deactivated
```

after being added to a Cart.

The Cart API must handle that state without allowing an invalid checkout.

---

# 44. Cart and Variant Removal

Same principle.

A Variant that becomes inactive must not remain silently purchasable.

Checkout must reject it if it is no longer purchasable.

---

# 45. Cart and Inventory Race

Customer sees:

```text id="2c0w7t"
2 units available
```

adds:

```text id="1mg8u5"
2 units
```

Another customer buys the stock.

Later:

```text id="o0qk73"
Checkout
```

must detect:

```text id="80n01f"
INSUFFICIENT_STOCK
```

The Cart API does not guarantee reservation.

---

# 46. Cart and Made-to-Order

Do not allow:

```text id="2z9p0f"
MADE_TO_ORDER
→ Cart
```

unless the business explicitly changes its checkout model later.

The correct workflow is:

```text id="6l0n9v"
MADE_TO_ORDER Product
→ Request
```

---

# 47. Cart and Pickup/Delivery

Cart does not need to permanently store:

```text id="z0i17g"
delivery fee
delivery address
fulfillment choice
```

Those belong to Checkout/order creation.

The customer chooses fulfillment during Checkout.

---

# 48. Cart and Payment

Cart has no payment relationship.

Do not place:

```text id="j4p2h7"
payment_status
payment_reference
```

in Cart.

Payment belongs to the later checkout/payment/order workflow.

---

# 49. Cart and Orders

An Order is created from validated Cart state during Checkout.

Conceptually:

```text id="emk4tm"
Cart
 ↓
Checkout
 ↓
Order
```

The Cart must not become the Order.

---

# 50. Cart Lifecycle

Define the conceptual lifecycle.

Example:

```text id="fyc0k6"
No active cart
      ↓
First item added
      ↓
Active cart
      ↓
Items modified
      ↓
Checkout begins
      ↓
Order created
      ↓
Cart completed/cleared/renewed
```

Do not implement final lifecycle states in this phase unless the project already defines them.

---

# 51. Cart After Successful Checkout

Define what happens after a successful Checkout.

Recommended:

> The purchased Cart contents must not remain as an active purchasable Cart state.

Possible implementation later:

```text id="onl8oc"
cart becomes inactive
```

or:

```text id="6id4b9"
cart is cleared/reset
```

Choose one logical policy.

---

# 52. Cart After Failed Checkout

A failed Checkout should not automatically destroy the Cart.

Customer should be able to correct the issue and retry.

Example:

```text id="i8o9ob"
Insufficient stock
↓
customer updates quantity
↓
retry checkout
```

This is normal ecommerce behavior.

---

# 53. Cart After Payment Failure

Because payment is later in Group H:

> A failed payment should not unnecessarily destroy the customer's valid Cart intent.

The exact post-payment-failure lifecycle belongs to Checkout/Payment design.

---

# 54. Cart Persistence

Determine whether Cart persists across sessions.

For authenticated customers, recommended:

> The active Cart persists server-side across authenticated sessions/devices.

Example:

```text id="j1pv6n"
Customer adds sofa on website
↓
logs out
↓
opens Flutter later
↓
logs in
↓
same active Cart available
```

This is useful for a shared website/app customer account.

---

# 55. Cross-Platform Cart

Next.js and Flutter must use the same server-side Cart.

Do not maintain separate independent server carts for:

```text id="0o3z0i"
website
mobile
```

for the same customer.

---

# 56. Concurrent Cart Modifications

A customer may have:

```text id="0y2jch"
website
+
Flutter
```

open simultaneously.

Define how concurrent Cart updates should behave.

The initial Version 1 approach may use:

```text id="j7u6x9"
last valid mutation wins
```

for simple quantity changes, provided it cannot corrupt item identity or exceed business limits.

For stronger consistency, later implementation may use optimistic locking/version checks.

Do not implement the mechanism yet.

---

# 57. Duplicate Add Requests

The customer may retry:

```text id="9rslk5"
POST add item
```

due to network uncertainty.

Because POST is non-idempotent by default, consider whether duplicate adds are acceptable.

For normal ecommerce UX, duplicate submissions should not unexpectedly create:

```text id="ljo3s4"
two separate identical Cart Items
```

The merge rule from Section 28 helps mitigate this.

However, exact idempotency requirements belong to the later idempotency phase.

---

# 58. Cart API Idempotency Candidates

Flag:

```text id="uw3r2v"
Add Item
Checkout
```

as operations requiring deliberate retry semantics.

For:

```text id="w7xq1m"
PATCH quantity
DELETE item
```

the operation can generally be designed to have stable repeat behavior.

---

# 59. Cart Price Authority

At all times:

```text id="xmhpm1"
Catalog
→ current price source

Cart
→ display/current snapshot

Checkout
→ authoritative transaction calculation
```

Do not make Cart the price master.

---

# 60. Cart Inventory Authority

Similarly:

```text id="jvi2k7"
Catalog/Inventory
→ current stock source

Cart
→ does not reserve stock

Checkout
→ authoritative stock validation
```

---

# 61. Cart Security Rules

The client may control:

```text id="7j4gtm"
selected Product/Variant
quantity
```

The client may not control:

```text id="c4ep6v"
cart owner
price
stock
subtotal
order reference
payment state
delivery fee
order status
```

---

# 62. Cart Authorization Matrix

Add to:

```text id="m1egv0"
docs/api/api-contract.md
```

| Operation                      | Anonymous | Customer |        Staff |        Admin |
| ------------------------------ | --------: | -------: | -----------: | -----------: |
| View current cart              |        No |      Own |           No |           No |
| Add item                       |        No |      Own |           No |           No |
| Update item                    |        No |      Own |           No |           No |
| Remove item                    |        No |      Own |           No |           No |
| Manage another customer's cart |        No |       No |           No |           No |
| Administrative cart management |        No |       No | Not required | Not required |

Do not create staff/admin Cart management unless an actual business requirement emerges.

---

# 63. Staff Should Not Modify Customer Carts

Staff process Orders, not pre-order customer shopping state.

Therefore Staff should not ordinarily:

```text id="u4h9dl"
add cart items
remove cart items
change customer cart quantities
```

This preserves customer ownership.

---

# 64. Admin Should Not Ordinarily Modify Customer Carts

Admin has high administrative authority, but Cart modification is not a normal administrative requirement.

Do not expose unnecessary administrative power.

---

# 65. Cart API Errors

Use the common Phase 1.16 error contract.

Potential codes:

```text id="oecm2r"
CART_NOT_FOUND
CART_ITEM_NOT_FOUND
INVALID_VALUE
INVALID_PRODUCT
INVALID_PRODUCT_VARIANT
PRODUCT_NOT_PURCHASABLE
PRODUCT_UNAVAILABLE
INSUFFICIENT_STOCK
```

Only retain codes that provide meaningful client behavior.

---

# 66. Cart Error Semantics

For a product no longer purchasable:

```text id="9hp4du"
PRODUCT_NOT_PURCHASABLE
```

For insufficient quantity:

```text id="7o9hp9"
INSUFFICIENT_STOCK
```

For invalid variant relationship:

```text id="z7yq8v"
INVALID_PRODUCT_VARIANT
```

Do not return generic:

```text id="4uk7gi"
CART_ERROR
```

for every situation.

---

# 67. Cart Not Found Semantics

If the customer has no active Cart:

Choose one:

```text id="m7y9ly"
return an empty Cart
```

or:

```text id="2y7v83"
return a not-found error
```

For normal ecommerce UX, strongly consider:

> **Return a valid empty active Cart representation.**

This removes unnecessary client branching.

Record the decision.

---

# 68. Empty Cart Representation

Conceptually:

```json id="n8q5g3"
{
  "data": {
    "id": "...",
    "items": [],
    "subtotal": {
      "amount": 0,
      "currency": "TZS"
    }
  }
}
```

The exact money/ID structure follows Phase 1.13.

Do not finalize the JSON schema beyond the approved conventions in this phase.

---

# 69. Cart Response Consistency

All Cart responses must use:

```text id="b4u7sl"
data
meta where needed
errors on failure
```

according to the global API response contract.

Do not create a custom Cart envelope.

---

# 70. Cart Item Representation Consistency

Use the same Catalog representations for:

```text id="9k3j4v"
Product
Variant
Image
Price
Availability
```

Do not create a completely separate Product schema merely for Cart.

Use appropriate summaries.

---

# 71. Stale Cart Items

When retrieving a Cart, decide how stale items appear.

Recommended:

> Return the item with a clear customer-facing availability/purchasability state rather than silently deleting it.

This makes it possible for the Flutter/Next.js UI to tell the customer:

> This item is no longer available.

The final exact representation will be specified later.

---

# 72. Cart Item Price Display

If the current catalog price differs from what was previously shown, the Cart may display the current price.

The customer must not assume the prior price is contractually locked unless a later Checkout/business rule says so.

---

# 73. Currency Consistency

Cart monetary representations must use the same currency rules from Phase 1.13.

Do not mix:

```text id="5q8b5o"
TZS numeric
USD string
formatted display amount
```

inside the API.

---

# 74. Cart and Localization

Human-readable product/catalog names in Cart should remain API data.

Localization/display formatting remains frontend responsibility where appropriate.

Do not return different Product names depending on whether the caller is Flutter or Next.js without an explicit localization contract.

---

# 75. Cart and SEO

Cart is private and irrelevant to search indexing.

Do not expose Cart content through public cacheable endpoints.

---

# 76. Cart Caching

Customer Cart responses are private.

They must not be cached publicly.

The eventual implementation must use appropriate private/no-store cache semantics.

Do not configure caching yet.

---

# 77. Cart Query Parameters

The normal Cart endpoints should not require complex collection query parameters.

Avoid:

```text id="2c3xzw"
GET /cart?sort=...
```

unless there is a genuine need.

Cart is small and customer-specific.

---

# 78. Cart Pagination

Do not paginate a normal customer's active Cart.

A Cart should typically contain a manageable number of items.

If a later business requirement allows very large carts, reconsider.

For Version 1:

> Active Cart Items are not a paginated customer collection.

---

# 79. Cart Item Ordering

Define deterministic display order.

Recommended:

```text id="pd7c6a"
item addition order
```

or another explicit ordering.

Do not depend on database natural order.

The exact ordering implementation comes later.

---

# 80. Add Item Response

After adding an item, the response may return:

```text id="ts7d6q"
updated Cart
```

or:

```text id="5ks8bp"
created Cart Item
```

Choose the representation that minimizes unnecessary client requests.

For a small ecommerce app, returning the updated Cart or the updated affected portion may be useful.

Record one consistent policy.

---

# 81. Update Item Response

Similarly, return a predictable representation.

Recommended:

> Return the updated Cart Item or updated Cart according to the global resource representation strategy.

Do not return only:

```json id="h4wq2f"
{"success": true}
```

when the client needs the new quantity/price.

---

# 82. Delete Item Response

A successful DELETE may return:

```text id="q5o1f3"
204 No Content
```

or a response containing the updated Cart.

Choose one convention.

For a simple customer API, `204` is reasonable when no additional state is needed.

Do not mix response behavior across Cart deletions.

---

# 83. Cart Status / Version Field

Evaluate whether the Cart should have a version/concurrency value.

For example:

```text id="0cqx3h"
version
```

This can later help detect:

```text id="w82xj0"
website changed Cart
Flutter changed Cart
```

Do not implement unless the project decides it is needed.

Record the concurrency consideration.

---

# 84. Optimistic Concurrency

If a Cart version is adopted later:

```text id="g5n53w"
client reads version 7
→ modifies quantity
→ server still version 7
→ update accepted
→ version becomes 8
```

If server is already version 8:

```text id="x9y5x6"
conflict
```

This is a future implementation option, not part of the immediate code work.

---

# 85. Cart Abuse Protection

Consider limits on:

```text id="i9t2z4"
items per Cart
quantity per item
request rate
rapid add/remove operations
```

Do not create arbitrary restrictions that harm normal ecommerce use.

Use reasonable operational limits later.

---

# 86. Cart and Authentication Expiration

If a session expires:

```text id="4bx1a4"
server Cart remains owned by the customer
```

The customer can retrieve it after successful re-authentication.

Do not destroy the server Cart solely because a session expired.

---

# 87. Cart and Account Security

Changing a customer's password should not silently transfer Cart ownership.

Ownership remains attached to the account identity.

---

# 88. Cart and Account Deactivation

Account-security restrictions, if introduced later, must have explicit business rules.

Staff still cannot arbitrarily restrict customers from ordering merely by manipulating Cart access.

---

# 89. Cart and Customer Ownership Protection

Do not expose a generic:

```text id="enl1n3"
PATCH /carts/{cart}
{
  "customer_id": "..."
}
```

The owner of a Cart is server-controlled.

---

# 90. Cart Input Field Allow-List

At the API boundary, only allow:

```text id="vx6u1d"
product_id
variant_id
quantity
```

for Add Item unless future business requirements explicitly add another field.

For Update:

```text id="b9vr9m"
quantity
```

Only.

---

# 91. Client-Cannot-Control Financial Fields

Explicitly reject/ignore:

```text id="i4p7uh"
price
discount
subtotal
total
currency
delivery_fee
```

as authoritative Cart input.

---

# 92. Client-Cannot-Control Availability

Do not accept:

```json id="p64qma"
{
  "available": true
}
```

from clients.

Availability comes from Catalog/Inventory authority.

---

# 93. Client-Cannot-Control Product Type

Do not allow:

```json id="ts8j6c"
{
  "product_type": "IN_STOCK"
}
```

to override actual Product data.

The Product resource determines its type.

---

# 94. Client-Cannot-Control Variant Ownership

The client can submit:

```text id="78xq4d"
variant_id
```

but the backend must validate its relationship to Product.

---

# 95. Validation Flow — Add Item

The logical validation flow is:

```text id="es1rx4"
Request
 ↓
JSON/schema valid
 ↓
Authenticated customer
 ↓
Customer owns current Cart
 ↓
Product exists
 ↓
Product is purchasable
 ↓
Variant valid
 ↓
Quantity valid
 ↓
Current availability check
 ↓
Merge/create Cart Item
 ↓
Return result
```

---

# 96. Validation Flow — Update Item

```text id="8h0ifd"
Request
 ↓
JSON/schema valid
 ↓
Authenticated customer
 ↓
Cart item belongs to customer
 ↓
Quantity valid
 ↓
Product/variant still valid
 ↓
Current availability check
 ↓
Update quantity
 ↓
Return result
```

---

# 97. Validation Flow — Remove Item

```text id="2lcnpa"
Request
 ↓
Authenticated customer
 ↓
Cart item belongs to customer
 ↓
Remove item
 ↓
Return result
```

This is simpler because no product stock validation is required to delete a customer's intent.

---

# 98. Cart Business Invariants

Add/confirm the following in:

```text id="kn1r0a"
docs/domain/business-rules.md
```

Conceptually:

```text
A Cart belongs to one customer.
A customer can access only their own Cart.
Cart Items belong to one Cart.
A Cart Item references a valid purchasable Product/Variant.
MADE_TO_ORDER products are not normal Cart purchases.
Cart membership does not reserve inventory.
Cart prices are not final transaction prices.
Checkout revalidates stock and pricing.
Clients cannot control financial/ownership fields.
```

---

# 99. Cart Security Invariants

Also record:

```text id="rnp8oy"
Customer A cannot read Customer B's Cart.
Customer A cannot modify Customer B's Cart.
Staff cannot arbitrarily modify customer Carts.
Admin does not automatically gain ordinary customer Cart manipulation rights.
Client cannot change Cart ownership.
Client cannot set authoritative price/stock.
```

---

# 100. Cart Endpoint Contract Matrix

Add to:

```text id="9y0fse"
docs/api/api-contract.md
```

| ID       | Method | Path                           | Auth     | Actor    | Purpose              |
| -------- | ------ | ------------------------------ | -------- | -------- | -------------------- |
| CART-001 | GET    | `/api/v1/me/cart`              | Required | Customer | Get own active cart  |
| CART-002 | POST   | `/api/v1/me/cart/items`        | Required | Customer | Add purchasable item |
| CART-003 | PATCH  | `/api/v1/me/cart/items/{item}` | Required | Customer | Change item quantity |
| CART-004 | DELETE | `/api/v1/me/cart/items/{item}` | Required | Customer | Remove item          |

Add `CART-005` only if Clear Cart is approved.

---

# 101. Cart Contract Record

For each Cart endpoint document:

```text id="1hhyep"
Endpoint ID
Method
Path
Purpose
Actor
Authentication
Authorization
Input
Validation
Business rules
Response
Errors
Idempotency
Concurrency
Caching
```

This is the minimum endpoint-level contract.

---

# 102. Cart Error Matrix

Include:

| Error                     | Meaning                                     | Typical status |
| ------------------------- | ------------------------------------------- | -------------: |
| `AUTHENTICATION_REQUIRED` | Customer must authenticate                  |            401 |
| `CART_ITEM_NOT_FOUND`     | Item does not exist in customer's cart      |            404 |
| `INVALID_VALUE`           | Input is invalid                            |            422 |
| `INVALID_PRODUCT_VARIANT` | Variant does not belong to product          |            422 |
| `PRODUCT_NOT_PURCHASABLE` | Product is not eligible for normal purchase |            422 |
| `PRODUCT_UNAVAILABLE`     | Product cannot currently be purchased       |       422/409* |
| `INSUFFICIENT_STOCK`      | Quantity is unavailable                     |       409/422* |

`*` must remain consistent with the final error-status policy.

---

# 103. Cart Response Contract

Use the global response architecture:

### Success

```json id="5t2s0z"
{
  "data": {}
}
```

### Error

```json id="fagzmo"
{
  "errors": [
    {
      "code": "...",
      "message": "..."
    }
  ]
}
```

Do not create Cart-specific envelopes.

---

# 104. Cart Documentation Example

Inside:

```text id="kcmhkj"
docs/api/api-contract.md
```

create a section such as:

```markdown
## Cart API Contract

### CART-001 — Get Current Cart

...

### CART-002 — Add Cart Item

...

### CART-003 — Update Cart Item

...

### CART-004 — Remove Cart Item

...
```

This becomes the authoritative Cart contract.

---

# 105. Central Decisions

Update:

```text id="in3gmy"
docs/decisions.md
```

with significant decisions such as:

```text
### CART-001 — Cart Is Customer-Owned

The authoritative server-side Cart belongs to an authenticated customer.

### CART-002 — Cart Does Not Reserve Inventory

Adding an item to a Cart does not reserve stock.

### CART-003 — Cart Is Not Price Authority

Checkout recalculates authoritative pricing.

### CART-004 — Made-to-Order Products Do Not Enter Normal Cart Checkout

Made-to-order products use the request workflow.

### CART-005 — Customer Cart Is Shared Across Clients

The same customer Cart is available through website and Flutter after authentication.
```

Only record decisions actually accepted during this phase.

---

# 106. Next.js Requirements

The contract must support:

```text id="66vfu6"
cart badge count
cart page
quantity adjustment
remove item
availability warning
login transition
```

without requiring a different API contract from Flutter.

---

# 107. Flutter Requirements

Flutter should be able to:

```text id="o2x6eg"
load cart
add item
change quantity
remove item
display current price
handle unavailable items
recover after session expiration
```

using the same endpoints.

---

# 108. Normal Ecommerce UX

The Cart API should support the familiar flow:

```text id="v2a1w2"
Browse
 ↓
Add to Cart
 ↓
Cart updates
 ↓
Review Cart
 ↓
Checkout
```

without staff intervention.

---

# 109. Staff Interaction

Staff do not interact with customer Carts in normal operations.

Their workflow starts primarily from:

```text id="u3a9sk"
Order
```

after Checkout creates it.

This preserves customer autonomy.

---

# 110. Admin Interaction

Admin does not need ordinary Cart management.

Do not make Admin responsible for customer cart cleanup or normal shopping operations.

---

# 111. Performance Considerations

Cart operations are customer-specific and relatively small.

Later implementation should consider:

```text id="ef5g2r"
efficient lookup by customer
efficient item lookup
minimal queries
safe transactional updates
```

Do not design indexes or database tables yet.

---

# 112. N+1 Consideration

When returning a Cart with several items:

```text id="p6qk19"
Product
Variant
availability
```

must not cause uncontrolled query multiplication in Laravel.

The later implementation should load related data efficiently.

Do not optimize the database here.

---

# 113. Cache Classification

Define:

```text id="os8l00"
Cart reads
→ PRIVATE

Cart mutations
→ NON-CACHEABLE
```

Do not place customer Cart data into shared public caches.

---

# 114. Cart API and Public Catalog

The Cart API may consume Product Catalog data, but the Cart remains private.

Do not expose a public API path that combines:

```text id="8s0i4z"
catalog
+
customer cart
```

in one shared response.

---

# 115. Cart API and SEO

Cart is not an SEO resource.

Do not expose it through:

```text id="ca8h3c"
public product pages
search indexing
CDN public caching
```

---

# 116. Cart API and Versioning

Cart is part of Version 1.

Breaking changes include:

```text id="p4h8om"
changing quantity meaning
changing Product/Variant identity
changing Cart response fields incompatibly
removing an operation
changing authorization requirements
```

Such changes require API-version review.

---

# 117. Cart API and Closed Enums

Any future Cart enum must follow:

```text id="4q7j6o"
CLOSED
```

Do not introduce extensible Cart states without explicit compatibility review.

---

# 118. Required Documentation Updates

### `docs/api/api-contract.md`

Add the complete Cart API contract.

### `docs/api/api-resources.md`

Update the Cart and Cart Item resource definitions.

### `docs/api/api-conventions.md`

Add only reusable Cart-related conventions discovered during the phase.

### `docs/domain/business-rules.md`

Add/confirm Cart business invariants.

### `docs/decisions.md`

Record significant Cart architectural decisions.

### `AGENTS.md`

Only update permanent engineering guidance when necessary.

Do not create additional permanent Cart markdown files.

---

# 119. Validation Checklist

### Ownership

* [ ] Cart is customer-owned.
* [ ] Customer can access only own Cart.
* [ ] Cart ownership is server-controlled.
* [ ] Staff cannot manipulate customer Carts during normal operations.
* [ ] Admin does not automatically receive ordinary customer Cart manipulation.

### Public/Anonymous

* [ ] Anonymous browsing remains supported.
* [ ] Anonymous user cannot access server-side customer Cart.
* [ ] Guest-local-cart behavior, if any, is not treated as authoritative.

### Add Item

* [ ] Product must exist.
* [ ] Product must be purchasable.
* [ ] MADE_TO_ORDER products cannot enter normal purchase Cart.
* [ ] Variant relationship is validated.
* [ ] Quantity is validated.
* [ ] Duplicate item behavior is defined.
* [ ] Client cannot supply authoritative price or stock.

### Update Item

* [ ] Only quantity is normally mutable.
* [ ] Ownership is checked.
* [ ] Product/Variant remains valid.
* [ ] Current availability is checked.

### Remove Item

* [ ] Ownership is checked.
* [ ] DELETE semantics are appropriate.
* [ ] Removal does not require inventory validation.

### Checkout Boundary

* [ ] Cart does not become Order.
* [ ] Cart does not reserve stock.
* [ ] Cart does not own Payment.
* [ ] Checkout revalidates pricing.
* [ ] Checkout revalidates inventory.

### Cross-platform

* [ ] Next.js uses the same Cart API.
* [ ] Flutter uses the same Cart API.
* [ ] Customer Cart is shared across authenticated clients.

### Security

* [ ] IDOR is addressed.
* [ ] Customer A cannot access Customer B's Cart.
* [ ] Role tampering is impossible.
* [ ] Ownership cannot be submitted by the client.
* [ ] Public caching is forbidden for Cart data.
* [ ] Sensitive/internal fields are not exposed.

### Reliability

* [ ] Duplicate add behavior is defined.
* [ ] Concurrent modification concerns are documented.
* [ ] Idempotency candidates are identified.
* [ ] Stale product/availability behavior is defined.

### Payment

* [ ] No payment implementation is included.
* [ ] Payment remains assigned to **Phase Group H**.

### Documentation

* [ ] Consolidated API documentation updated.
* [ ] Domain rules updated.
* [ ] Central decisions updated.
* [ ] No unnecessary permanent Markdown files created.

---

# 120. Explicitly Out of Scope

Do NOT:

```text id="7t8sa9"
Define Checkout API
Define Payment API
Implement cart database tables
Create Laravel models
Create Laravel controllers
Create Laravel routes
Create Form Requests
Create Policies
Create repositories
Implement inventory reservation
Implement checkout
Implement payment
Implement pricing engine
Build Next.js cart UI
Build Flutter cart UI
Generate final OpenAPI schemas
```

---

# 121. Definition of Done

Phase 1.21 is complete when:

1. The Cart resource contract is defined.
2. Customer ownership is explicit.
3. Current-cart retrieval is defined.
4. Add-item operation is defined.
5. Update-item operation is defined.
6. Remove-item operation is defined.
7. Clear-cart decision is made or explicitly deferred.
8. Product validation is defined.
9. Variant validation is defined.
10. Quantity validation is defined.
11. Duplicate-item behavior is defined.
12. Made-to-order products are excluded from normal purchase Cart.
13. Cart does not reserve inventory.
14. Cart is not authoritative for price.
15. Checkout is identified as the next authoritative commerce stage.
16. Stale product/availability behavior is defined.
17. Cart ownership/IDOR protection is defined.
18. Staff access is intentionally limited.
19. Admin access is intentionally limited.
20. Cross-platform Cart behavior is defined.
21. Concurrent modification concerns are documented.
22. Idempotency candidates are identified.
23. Cart responses use the common API response contract.
24. Cart errors use the common error contract.
25. Version 1 closed-enum policy remains intact.
26. Payment remains assigned to **Phase Group H**.
27. Consolidated documentation has been updated.
28. No implementation code has been written.

---

# 122. STOP CONDITION — Mandatory

After completing the Cart contract and updating:

```text id="o8cp0q"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

**STOP.**

Do not start Checkout implementation.

Do not create checkout routes.

Do not implement inventory reservation.

Do not implement payments.

Do not write Laravel Cart code.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.22 — Define Checkout API Contract

That phase should build directly on the finalized Catalog + Cart contracts and define the **critical authenticated transaction boundary**:

```text id="igx7e3"
Cart
 ↓
customer authentication
 ↓
cart validation
 ↓
product/variant validation
 ↓
inventory validation
 ↓
current pricing
 ↓
pickup/delivery selection
 ↓
variable delivery fee
 ↓
order creation
 ↓
payment handoff
```

while preserving the rule that **payment implementation itself remains in Phase Group H**.
