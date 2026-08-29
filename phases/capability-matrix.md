# Capability Matrix — Version 1 (Domain Level)

## 1. Purpose

This matrix establishes the **public-vs-account boundary** at the domain level. It is **not** an authorization design; detailed permissions belong to Phase Group D.

Legend:

- **Yes** — capability available.
- **No** — capability not available.
- **Later policy** — availability will be decided by authorization rules in Phase Group D; not defined in this phase.

---

## 2. Capability Matrix

| Capability | Anonymous (Guest) | Authenticated Customer | Staff | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse catalog | Yes | Yes | Later policy | Later policy |
| Browse categories | Yes | Yes | Later policy | Later policy |
| Search products | Yes | Yes | Later policy | Later policy |
| Filter / sort products | Yes | Yes | Later policy | Later policy |
| View product details | Yes | Yes | Later policy | Later policy |
| View product images | Yes | Yes | Later policy | Later policy |
| View prices | Yes | Yes | Later policy | Later policy |
| View product availability / type (`IN_STOCK` / `MADE_TO_ORDER`) | Yes | Yes | Later policy | Later policy |
| Submit made-to-order request | Yes | Yes | Later policy | Later policy |
| Submit general enquiry | Yes | Yes | Later policy | Later policy |
| Register | Yes | — (already registered) | Later policy | Later policy |
| Login | Yes | — (already logged in) | Later policy | Later policy |
| View / update profile | No | Yes | Later policy | Later policy |
| Create / view / modify cart (guest cart) | Yes | Yes | Later policy | Later policy |
| Checkout | No | Yes | Later policy | Later policy |
| Pay | No | Yes | Later policy | Later policy |
| View delivery fee for my location/address at checkout | No | Yes | Later policy | Later policy |
| View own orders | No | Yes | Later policy | Later policy |
| View own order details | No | Yes | Later policy | Later policy |
| Track own order | No | Yes | Later policy | Later policy |
| Cancel own order (within 20-minute window) | No | Yes (window enforced) | Later policy | Later policy |
| View own notifications | No | Yes | Later policy | Later policy |
| Manage categories | No | No | Later policy | Later policy |
| Manage products | No | No | Later policy | Later policy |
| Manage product images | No | No | Later policy | Later policy |
| Manage product variants | No | No | Later policy | Later policy |
| Manage inventory | No | No | Later policy | Later policy |
| Review orders | No | No | Later policy | Later policy |
| Update order status | No | No | Later policy | Later policy |
| Review customers | No | No | Later policy | Later policy |
| Review made-to-order requests | No | No | Later policy | Later policy |
| Update request status | No | No | Later policy | Later policy |
| Review enquiries | No | No | Later policy | Later policy |
| Update enquiry status | No | No | Later policy | Later policy |
| View payment status | No | No | Later policy | Later policy |
| Set / manage delivery fee rules (by region/location) | No | No | Later policy | Later policy |
| Manage fulfilment information | No | No | Later policy | Later policy |

---

## 3. Key Boundaries Confirmed by This Matrix

1. **Anonymous discovery is complete and first-class** — guests can browse, search, filter, and view full product details, prices, images, and availability without any account.
2. **Anonymous communication is supported** — guests may submit made-to-order requests and general enquiries, providing contact information.
3. **Guest carts are supported, but checkout requires an account** — a guest may create, view, and modify a cart; the cart is associated with the customer's account upon authentication. Checkout, payment, orders, and tracking are available only to authenticated customers.
4. **Customer self-service is limited** — customers can cancel their own order only within the 20-minute window; they cannot change order status or totals.
5. **Staff/admin rows are placeholders** — all staff/admin entries are `Later policy` until Phase Group D defines the permission matrix.

---

## 4. Note

Staff and admin columns are **uniformly** `Later policy` for every row. This matrix establishes only the public-vs-account boundary (anonymous vs authenticated customer); it is not an authorization design. The staff/admin permission matrix belongs to Phase Group D, and no staff/admin cell in this table is a finalized permission.

Customer self-service remains limited per Key Boundary 4: a customer may cancel their own order only within the 20-minute window and cannot change order status or totals.