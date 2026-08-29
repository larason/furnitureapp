# Capability Matrix — Version 1 (Domain Level)

## 1. Purpose

This matrix establishes the **public-vs-account boundary** at the domain level. It is **not** an authorization design; detailed permissions belong to Phase Group D.

Legend:

- **Yes** — capability available.
- **No** — capability not available.
- **Later policy** — availability will be decided by authorization rules in Phase Group D; not defined in this phase.
- **`*`** — staff/admin entries marked with `*` reflect that staff/admin capabilities are not defined by this phase; do not treat these rows as final permissions.

---

## 2. Capability Matrix

| Capability | Anonymous (Guest) | Authenticated Customer | Staff | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse catalog | Yes | Yes | Yes | Yes |
| Browse categories | Yes | Yes | Yes | Yes |
| Search products | Yes | Yes | Yes | Yes |
| Filter / sort products | Yes | Yes | Yes | Yes |
| View product details | Yes | Yes | Yes | Yes |
| View product images | Yes | Yes | Yes | Yes |
| View prices | Yes | Yes | Yes | Yes |
| View product availability / type (`IN_STOCK` / `MADE_TO_ORDER`) | Yes | Yes | Yes | Yes |
| Submit made-to-order request | Yes* | Yes* | Yes* | Yes* |
| Submit general enquiry | Yes* | Yes* | Yes* | Yes* |
| Register | Yes | — (already registered) | No | No |
| Login | Yes | — (already logged in) | Yes | Yes |
| View / update profile | No | Yes | No | Later policy |
| View / modify cart | No | Yes | No | No |
| Checkout | No | Yes | Later policy | Later policy |
| Pay | No | Yes | Later policy | Later policy |
| View own orders | No | Yes | No | Later policy |
| View own order details | No | Yes | No | Later policy |
| Track own order | No | Yes | No | Later policy |
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
| Set / manage delivery fee | No | No | Later policy | Later policy |
| Manage fulfilment information | No | No | Later policy | Later policy |

---

## 3. Key Boundaries Confirmed by This Matrix

1. **Anonymous discovery is complete and first-class** — guests can browse, search, filter, and view full product details, prices, images, and availability without any account.
2. **Anonymous communication is supported** — guests may submit made-to-order requests and general enquiries, providing contact information.
3. **Commerce requires an account** — cart, checkout, payment, orders, and tracking are available only to authenticated customers.
4. **Customer self-service is limited** — customers can cancel their own order only within the 20-minute window; they cannot change order status or totals.
5. **Staff/admin rows are placeholders** — all staff/admin entries are `Later policy` until Phase Group D defines the permission matrix.

---

## 4. Note

`*` on anonymous request/enquiry submission: staff and admin rows for these capabilities are intentionally left as `Later policy` (and, when defined, would represent staff submitting on behalf of a customer, which is a Phase Group D decision). The public-vs-account boundary is the only thing this phase establishes.