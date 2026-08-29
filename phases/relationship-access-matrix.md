# Relationship Access Matrix — Version 1 (Conceptual)

## 0. Purpose
Shows who may traverse each relationship. Conceptual only; detailed permission rules remain later (Group D). Authorization is backend-enforced per relationship ownership (AGENTS.md §17). Same core resources are traversed by all actors with elevated representations for staff/admin (Phase 1.7 §32).

## 1. Matrix

| Relationship | Anonymous | Customer (Authenticated) | Staff | Admin | Notes |
|---|---|---|---|---|---|
| **Category → Products** | Yes | Yes | Yes | Yes | Public catalog browsing, no auth |
| **Product → Category** | Yes | Yes | Yes | Yes | Category summary in Product, public |
| **Product → Product Images** | Yes | Yes | Yes (manage) | Yes (manage) | Read public via Product; manage Staff/Admin via Product |
| **Product → Product Variants** | Yes | Yes | Yes (read) / Manage* | Yes (manage) | Read public via Product; manage per Group D (*Staff variant manage later policy) |
| **Product/Variant → Availability** | Yes (limited signal) | Yes (limited signal) | Yes (via Inventory full) | Yes (via Inventory full) | Public limited; full quantities internal |
| **Product/Variant → Inventory (internal quantities)** | No | No | Yes (operational) | Yes (operational) | `physical/reserved` never public |
| **User → Cart** | Via `GUEST_TOKEN` holder (own cart only) | Own (via authenticated User, one active cart per holder) | N/A | N/A | Holder-scoped, not listable across holders; binds on login |
| **Cart → Cart Items** | Via Cart holder | Own via Cart | N/A | N/A | PARENT_CHILD |
| **Cart Item → Product/Variant reference** | Via Cart context | Via Cart context | N/A | N/A | Reference, must enforce variant belongs to product |
| **Cart → Checkout workflow** | No (rejected) | Own (authenticated only) | N/A | N/A | Checkout requires account (AGENTS.md) |
| **User → Own Orders** | No | Yes (own only) | N/A (staff not own) | Authorized (operational all with policy) | OWNERSHIP boundary |
| **Order → Order Items** | No | Yes (own via Order) | Yes (operational, all) | Yes (operational, all) | PARENT_CHILD |
| **Order Item → Product/Variant (historical + optional current)** | No | Yes (own via Order) | Yes (operational) | Yes (operational) | Historical snapshot + optional current reference |
| **Order → Order Addresses** | No | Yes (own via Order) | Yes (operational) | Yes (operational) | Billing exactly 1, delivery 0..1 conditional |
| **Order → Payment (limited summary)** | No | Yes (own, limited) | Yes (operational verify) | Yes (operational verify) | No secrets |
| **Order → Delivery/Fulfillment** | No | Yes (own when DELIVERY) | Yes (operational) | Yes (operational) | Conditional; PICKUP has none |
| **Order → Tracking** | No | Yes (own, projection without Operational note) | Yes (full) | Yes (full) | Read-only for customer |
| **Tracking → Status History** | No | Yes (own, without Operational note) | Yes (full with notes) | Yes (full with notes) | Append via controlled actions only |
| **Made-to-order Request → Product** | Optional when creating (if from product page) | Optional (own request) | Yes (operational) | Yes (operational) | OPTIONAL_REFERENCE |
| **Made-to-order Request → User** | Anonymous: User=none allowed | Own when linked | Yes (all) | Yes (all) | Optional ownership |
| **General Enquiry → User** | Anonymous: User=none allowed | Own when linked | Yes (all) | Yes (all) | Optional ownership |
| **Request → Attachments** | Create response only (attachment returned in create response, no subsequent anonymous read) | Own via request (authenticated owner) | Authorized via request (staff) | Authorized via request (admin) | Never global `GET /attachments`; anonymous read via context not permitted beyond create response |
| **Enquiry → Attachments** | Create response only (attachment returned in create response, no subsequent anonymous read) | Own via enquiry (authenticated owner) | Authorized via enquiry (staff) | Authorized via enquiry (admin) | Same — anonymous limited to create response |
| **User → Notifications** | No | Yes (own recipient only) | N/A | Administrative/system (own system view) | Private per recipient |
| **User ↔ Authentication internals** | Register/Login/Recovery: anonymous allowed (public op) | Logout/Verify/Session: authenticated | N/A | N/A | Credentials never exposed |

## 2. Scenarios

### Anonymous Request
```text
Anonymous creates Request → Product? (optional) → User none + contact info → Attachments (create payload)
=> Allowed: Anonymous Create with optional Product and Attachments, no User required.
=> Traversal: Anonymous cannot list/read Requests or Attachments beyond the create response; read via created context after creation is not permitted. Subsequent retrieval requires authenticated ownership (if later linked to User) or a separately issued one-time retrieval flow — no anonymous read via request context.
```

### Anonymous Enquiry
```text
Anonymous creates Enquiry → User none + contact/message → Attachments (create payload)
=> Same as Request: Anonymous Create only, attachments returned only in create response.
=> Traversal: No anonymous read/list of Enquiries or Attachments beyond create response; subsequent retrieval requires authenticated ownership or separate one-time retrieval flow.
```

### Public Catalog
```text
Anonymous browses Category → Products → Product → {Images, Variants, Availability, Category summary}
=> All without auth; no User relationship required.
```

### Commerce
```text
Guest holder cart → items → Product/Variant → (on login) binds to User → Checkout (auth required) → Order → {Items, Addresses, Delivery?, Payment summary, Tracking}
=> Cart holder-scoped, Order ownership checks.
```

## 3. Rules
- **Ownership boundary:** `User → Orders/Notifications/Requests/Enquiries` traversals are own-only for customers; backend must enforce `belongs to authenticated user` or `GUEST_TOKEN holder` as applicable.
- **Internal-only relationships** (`Product/Variant → Inventory` quantities, `Order → internal reservation`, `Payment → provider credentials`, `User → credential hash`) are never traversable by customer catalog APIs, even though backend stores them.
- **Optional relationships** do not create `404` for the parent when absent; they are nullable.
- **Conditional relationships** (`Order → Delivery`) exist only when `fulfillment=DELIVERY`; API must not require `delivery` for `PICKUP`.

