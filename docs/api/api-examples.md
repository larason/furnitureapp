# Canonical API Examples — Furniture E-Commerce Platform (Version 1)

> **Version:** `v1` — base `https://api.example.com` + `/api/v1` · **Status:** Phase 1.31 — Canonical Examples (Group A)
> **Authority:** This file contains **example request/response payloads only**. The authoritative contract remains `docs/api/api-contract.md` (envelope `§2`, errors `§15`, auth `§17`, authz `§18`, inventory `§19`, catalog `§21`, cart `§22`, checkout `§23`, order `§24`, tracking `§25`, request `§26`, enquiry `§27`, notification `§28`, user/profile `§29`, staff/admin `§30`, cross-domain `§31`). Examples follow `docs/api/api-conventions.md` and `docs/domain/business-rules.md`. No Laravel code, OpenAPI, or payment implementation is introduced here. Phase 1.30 is the consistency gate; examples are built from the stabilized contract, not from stale phase drafts.

> **Storage:** `docs/api/api-examples.md` is the single consolidated Phase 1.31 artifact per `phases/phase-1.31.md:66` (`docs/api/api-contract.md` would exceed scale if all 45 payloads were inlined). Do not create `docs/api/examples/*.md` per endpoint.

---

## 1. Canonical Example Data Set (Fictional, Deterministic)

Use consistent fictional identifiers across all examples. These are documentation placeholders; production identifiers are opaque per `§10` (`id` opaque, `slug` SEO, `order_reference` `OD-*****`).

| Concept | Example ID | Example Value | Notes |
|---|---|---|---|
| **Environment / Origin** | — | `https://api.example.com` | **Origin without `/api/v1`** — path already contains `/api/v1`; full URL `https://api.example.com` + `GET /api/v1/products` = `https://api.example.com/api/v1/products` |
| **Customer** | `CUS-0001` | `user_01h8y0c1d2e3f4g5h6i7j8k9l` | `role: CUSTOMER` |
| **Staff** | `USR-0101` | `user_01h8y1a2b3c4d5e6f7g8h9i0` | `role: STAFF`, `staff_state: ACTIVE` |
| **Admin** | `USR-0001` | `user_01h8y9a0b1c2d3e4f5g6h7j8` | `role: ADMIN` |
| **Category** | `CAT-0001` | `cat_01h8x8a1b2c3d4e5f6g7h8j9` / slug `living-room` |  |
| **Product (IN_STOCK)** | `PRD-0001` | `prod_01h8x9j2m4k5n6p7q8r9s0t1` / slug `solid-oak-dining-table` / `IN_STOCK` |  |
| **Product (MADE_TO_ORDER)** | `PRD-0002` | `prod_01h8x9j2m4k5n6p7q8r9s0t2` / slug `walnut-custom-table` / `MADE_TO_ORDER` | Discoverable but not cart-able |
| **Variant** | `VAR-0001` | `var_01h8x9k1m2n3p4q5r6s7t8u9` / SKU `OAK-TABLE-6S-NAT` / belongs to `PRD-0001` `solid-oak-dining-table` |  |
| **Cart** | `CRT-0001` | `cart_01h8y0a1b2c3d4e5f6g7h8j9` | Belongs to `CUS-0001` |
| **Cart Item** | `CRT-I-0001` | `item_01h8y0b2c3d4e5f6g7h8j9k0` |  |
| **Order (PICKUP)** | `OD-00001` | `ord_01h8y5a1b2c3d4e5f6g7h8j9` / `order_reference: OD-2026-00042` | `fulfillment_type: PICKUP` — used for checkout `§7.1`, pickup lifecycle `§19.1` and pickup fulfillment `ORD-009/ORD-013` |
| **Order (DELIVERY)** | `OD-00002` | `ord_01h8y5a1b2c3d4e5f6g7h8k0` / `order_reference: OD-2026-00043` | `fulfillment_type: DELIVERY` — used for checkout `§7.2`, delivery lifecycle `§19.2`, delivery fulfillment `ORD-010/ORD-011` and `ORD-014` |
| **Request** | `REQ-0001` | `req_01h8y5a1b2c3d4e5f6g7h8j9` |  |
| **Enquiry** | `ENQ-0001` | `enq_01h8y5a1b2c3d4e5f6g7h8j9` |  |
| **Notification** | `NTF-0001` | `not_01h8y5a1b2c3d4e5f6g7h8j9` |  |
| **Staff pending** | `USR-0102` | `user_01h8y9a0b1c2d3e4f5g6h7j9` | `staff_state: PENDING` before `ADM-004` |
| **Fixed timestamp** | — | `2026-01-15T09:30:00Z` | ISO8601 UTC with `Z` for all `created_at`/`occurred_at`/`read_at`; cancellation window uses server time, never client `cancelled_at` |

Order reference format remains `OD-*****` (`OD-2026-00042`) per `§24.2`; money always `{amount:int minor units 1 TZS=100, currency:"TZS"}` (e.g., `125000000` = `1,250,000.00 TZS`); enums `CLOSED` `UPPER_SNAKE_CASE` except `availability` `available|unavailable` lower.

---

## 2. Global Conventions Applied to Every Example

- **Version prefix:** Origin `https://api.example.com` + path `/api/v1/...` (e.g., `GET https://api.example.com/api/v1/products`) — version in **path only**, never `https://api.example.com/api/v1` + `/api/v1/products` → `/api/v1/api/v1/products` per `§31.1`.
- **Response envelope:** `{"data": …}` for success (`data: object` or `data: []` + `meta.pagination`), never `result`/`payload`; missing resource → `{"errors":…}` not `data:null` per `§2`.
- **Collection envelope:** `{"data": [...], "meta": {"pagination": {"current_page":1,"per_page":20,"total":86,"last_page":5,"has_next":true,"has_previous":false}}}` with `meta.pagination` per `§11`.
- **Error envelope:** `{"errors": [{"code":"SOME_STABLE_CODE","message":"Human-readable.","field":"delivery_address.city","details":{…}}], "meta": {"request_id":"01H9-req-abc123"}}` per `§15`; `code` `UPPER_SNAKE_CASE` CLOSED, never SQL/stack/Laravel exception.
- **Headers (only where contract defines):** `Accept: application/json`, `Content-Type: application/json`, `Authorization: Bearer <ACCESS_TOKEN>`, `Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000` where `IDEMPOTENCY_REQUIRED` (`CHK-001`, `ORD-004`, `ORD-007..011,013,014`, `INV-003`, `ADM-004..006`, `PAY-001`).
- **Money:** `{amount:int,currency:"TZS"}` minor units, never `15000` string/float/decimal per `§4`.
- **Date/time:** `2026-01-15T09:30:00Z` fixed, never `2026-01-15 09:30` per `§3.4`.
- **Enums:** `CUSTOMER`/`STAFF`/`ADMIN`, `IN_STOCK`/`MADE_TO_ORDER`, `PICKUP`/`DELIVERY`, `PENDING_PAYMENT`…`COMPLETED`/`CANCELLED`, `SUBMITTED`/`IN_REVIEW`/`CLOSED`, `OPEN`/`CLOSED`, `available|unavailable` per `§8`.
- **Security redaction:** Fictional `Jengo Street 12, Dar es Salaam`, `+255700000001`, `asha@example.com`; no real passwords/keys/JWTs/card data per `§65`.

---

## 3. Public Catalog Examples

### 3.1 List products — `CAT-001` `GET /api/v1/products` — Public

**Purpose:** Public `availability`/`stock_indicator` only, no `reserved_quantity`.

```http
GET /api/v1/products?category=living-room&product_type=IN_STOCK&availability=available&min_price=50000000&max_price=200000000&sort=price&sort_direction=asc&page=1&per_page=20 HTTP/1.1
Host: api.example.com
Accept: application/json
```

```json
{
  "data": [
    {
      "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
      "name": "Solid Oak Dining Table",
      "slug": "solid-oak-dining-table",
      "product_type": "IN_STOCK",
      "price": { "amount": 125000000, "currency": "TZS" },
      "category": { "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9", "name": "Living Room", "slug": "living-room" },
      "primary_image": { "id": "img_01h8x9a0b1c2d3e4f5g6h7j8", "url": "https://cdn.example.com/products/oak-table.webp", "alt_text": "Solid Oak Dining Table" },
      "availability": "available",
      "stock_indicator": "IN_STOCK"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_next": false, "has_previous": false }
  }
}
```
*Notes:* `200`, `Cache-Control: public`; `availability=available|unavailable` lower, `stock_indicator` `IN_STOCK|LOW_STOCK|MADE_TO_ORDER` display only; `reserved_quantity` never exposed.

### 3.2 Product details — `CAT-002` `GET /api/v1/products/{slug}` — Public

```http
GET /api/v1/products/solid-oak-dining-table HTTP/1.1
Host: api.example.com
Accept: application/json
```

```json
{
  "data": {
    "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
    "name": "Solid Oak Dining Table",
    "slug": "solid-oak-dining-table",
    "description": "Handcrafted solid oak table for 6.",
    "product_type": "IN_STOCK",
    "price": { "amount": 125000000, "currency": "TZS" },
    "category": { "id": "cat_01h8x8a1b2c3d4e5f6g7h8j9", "name": "Living Room", "slug": "living-room", "description": "Living room furniture." },
    "images": [
      { "id": "img_01h8x9a0b1c2d3e4f5g6h7j8", "url": "https://cdn.example.com/products/oak-table.webp", "alt_text": "Front", "sort_order": 1, "is_primary": true }
    ],
    "variants": [
      { "id": "var_01h8x9k1m2n3p4q5r6s7t8u9", "sku": "OAK-TABLE-6S-NAT", "name": "Natural Oak", "price": { "amount": 125000000, "currency": "TZS" }, "availability": "available", "stock_indicator": "IN_STOCK" }
    ],
    "availability": "available",
    "stock_indicator": "IN_STOCK",
    "created_at": "2026-01-10T08:00:00Z",
    "updated_at": "2026-01-12T10:00:00Z"
  }
}
```

### 3.3 Catalog type — `MADE_TO_ORDER` discoverable, not purchasable

**Purpose:** Demonstrate `MADE_TO_ORDER` can be listed (`CAT-001?product_type=MADE_TO_ORDER`) but `POST /api/v1/me/cart/items` with `product_id: prod_01h8x9j2m4k5n6p7q8r9s0t2` (`MADE_TO_ORDER`) → `422`.

```http
GET /api/v1/products?product_type=MADE_TO_ORDER HTTP/1.1
Host: api.example.com
```

Response `data` contains `product_type: MADE_TO_ORDER`, `price: {amount:150000000,currency:"TZS"}` (display/starting-at, not purchasable unit price per `§3.8`).

---

## 4. Authentication Examples

### 4.1 Customer registration — `AUTH-001` `POST /api/v1/auth/register` — Public

**Purpose:** Only `name`, `email`, `phone`, `password` accepted (not `role`, `permissions`, `account status`, `user ID`).

```http
POST /api/v1/auth/register HTTP/1.1
Host: api.example.com
Content-Type: application/json
Accept: application/json

{
  "name": "Asha Mwangi",
  "email": "asha@example.com",
  "phone": "+255700000001",
  "password": "correct-horse-battery-staple"
}
```

```json
{
  "data": {
    "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "phone": "+255700000001",
    "role": "CUSTOMER",
    "email_verified": false,
    "created_at": "2026-01-15T09:30:00Z",
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*Status:* `201`, `role` server-set `CUSTOMER`; no `password`/`hash`/`permissions` in response; `role: ADMIN` from client would be `422 field: role`.

### 4.2 Customer login — `AUTH-002` `POST /api/v1/auth/login` — Public

```http
POST /api/v1/auth/login HTTP/1.1
Host: api.example.com
Content-Type: application/json
Accept: application/json

{
  "email": "asha@example.com",
  "password": "correct-horse-battery-staple"
}
```

```json
{
  "data": {
    "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "role": "CUSTOMER"
  }
}
```
*Headers:* `Set-Cookie: session=…; HttpOnly; Secure; SameSite=Strict` for `Next.js` (browser) or `Authorization: Bearer <ACCESS_TOKEN>` for `Flutter` — shared identity `AUTH-002` merges `X-Guest-Cart-Id` guest cart per `business-rules.md §3 #4`.

### 4.3 Authentication failure — `401`

```json
{
  "errors": [
    { "code": "INVALID_CREDENTIALS", "message": "Invalid credentials." }
  ],
  "meta": { "request_id": "01H9-req-auth-001" }
}
```
*Status:* `401`, generic `INVALID_CREDENTIALS`, no `email exists` distinction (anti-enumeration).

---

## 5. User/Profile Examples

### 5.1 Get own profile — `USER-001` `GET /api/v1/me` — Authenticated

```http
GET /api/v1/me HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer <ACCESS_TOKEN>
// Actor: CUSTOMER CUS-0001
```

```json
{
  "data": {
    "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "phone": "+255700000001",
    "role": "CUSTOMER",
    "email_verified": false,
    "created_at": "2026-01-01T08:00:00Z",
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*Private:* `Cache-Control: private, no-store`.

### 5.2 Update own profile — `USER-002` `PATCH /api/v1/me` — Authenticated (allow-list `name`, `phone` only)

```http
PATCH /api/v1/me HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>

{
  "name": "Asha K. Mwangi",
  "phone": "+255700000002"
}
```

```json
{
  "data": {
    "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l",
    "name": "Asha K. Mwangi",
    "phone": "+255700000002",
    "role": "CUSTOMER",
    "email_verified": false,
    "updated_at": "2026-01-15T09:35:00Z"
  }
}
```
*Attempt `{"role":"ADMIN"}` → `422 INVALID_VALUE field: role` per `§29.6`; `GET /me?user_id=another` rejected; `Staff GET /me` returns own `STAFF` profile, not customer.*

---

## 6. Cart Examples

### 6.1 Get cart — `CART-001` `GET /api/v1/me/cart` — Authenticated

```http
GET /api/v1/me/cart HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```

```json
{
  "data": {
    "id": "cart_01h8y0a1b2c3d4e5f6g7h8j9",
    "items_count": 1,
    "items": [
      {
        "id": "item_01h8y0b2c3d4e5f6g7h8j9k0",
        "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
        "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
        "product": { "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1", "name": "Solid Oak Dining Table", "slug": "solid-oak-dining-table", "product_type": "IN_STOCK", "price": { "amount": 125000000, "currency": "TZS" }, "primary_image": { "id": "img_01h8x9a0b1c2d3e4f5g6h7j8", "url": "https://cdn.example.com/products/oak-table.webp", "alt_text": "Front" } },
        "variant": { "id": "var_01h8x9k1m2n3p4q5r6s7t8u9", "sku": "OAK-TABLE-6S-NAT", "name": "Natural Oak", "price": { "amount": 125000000, "currency": "TZS" } },
        "quantity": 1,
        "unit_price": { "amount": 125000000, "currency": "TZS" },
        "line_total": { "amount": 125000000, "currency": "TZS" },
        "availability": "available",
        "stock_indicator": "IN_STOCK",
        "is_purchasable": true,
        "created_at": "2026-01-15T09:30:00Z",
        "updated_at": "2026-01-15T09:30:00Z"
      }
    ],
    "subtotal": { "amount": 125000000, "currency": "TZS" },
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*Private:* `Cache-Control: private, no-store`; `subtotal` informational server-calculated, not client.

### 6.2 Add item — `CART-002` `POST /api/v1/me/cart/items` — Authenticated

```http
POST /api/v1/me/cart/items HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>

{
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
  "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
  "quantity": 1
}
```
*Response `201`: updated `Cart` as above; `MADE_TO_ORDER` `product_id: prod_01h8x9j2m4k5n6p7q8r9s0t2` → `422 PRODUCT_NOT_PURCHASABLE` per `§22.3`.*

### 6.3 Update quantity — `CART-003` `PATCH /api/v1/me/cart/items/{item}`

```http
PATCH /api/v1/me/cart/items/item_01h8y0b2c3d4e5f6g7h8j9k0 HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>

{
  "quantity": 2
}
```
*Only `quantity` mutable; `product_id` change requires delete+add.*

### 6.4 Remove item — `CART-004` `DELETE /api/v1/me/cart/items/{item}`

```http
DELETE /api/v1/me/cart/items/item_01h8y0b2c3d4e5f6g7h8j9k0 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```
*Status:* `204` (idempotent).

### 6.5 Cart authorization failure

`Customer B GET /api/v1/me/cart` with `Customer A` token → not applicable (self-context `/me/cart` prevents IDOR); `PATCH /api/v1/me/cart/items/{item_of_B}` → `404 CART_ITEM_NOT_FOUND` masked per `§15.8`, not `403` leak.

---

## 7. Checkout Examples

### 7.1 Checkout — pickup — `CHK-001` `POST /api/v1/checkout` — Authenticated CUSTOMER

**Purpose:** Demonstrates `PICKUP` `delivery_fee: {amount:0}` `FINALIZED`, server totals, `OD-*****` reference.

```http
POST /api/v1/checkout HTTP/1.1
Host: api.example.com
Content-Type: application/json
Accept: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
// Actor: CUSTOMER CUS-0001, Cart: CRT-0001 with 1× Solid Oak

{
  "fulfillment_type": "PICKUP"
}
```

```json
{
  "data": {
    "order_id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
    "order_reference": "OD-2026-00042",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "PICKUP",
    "delivery_address": null,
    "subtotal": { "amount": 125000000, "currency": "TZS" },
    "delivery_fee": { "amount": 0, "currency": "TZS" },
    "delivery_fee_status": "FINALIZED",
    "total": { "amount": 125000000, "currency": "TZS" },
    "currency": "TZS",
    "payment": null
  }
}
```
*`delivery_fee` already `FINALIZED`, `total` final, `payment` `null` until `PAY-001`; client `{"total":…}`/`{"delivery_fee":…}` would be `422 field: total/delivery_fee` per `§23.3`.*

### 7.2 Checkout — delivery (fee pending) — `CHK-001`

```http
POST /api/v1/checkout HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440001

{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "Asha Mwangi",
    "phone": "+255700000001",
    "address_line": "Jengo Street 12",
    "city": "Dar es Salaam"
  }
}
```

```json
{
  "data": {
    "order_id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
    "order_reference": "OD-2026-00043",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "DELIVERY",
    "delivery_address": { "recipient_name": "Asha Mwangi", "phone": "+255700000001", "address_line": "Jengo Street 12", "city": "Dar es Salaam" },
    "subtotal": { "amount": 125000000, "currency": "TZS" },
    "delivery_fee": null,
    "delivery_fee_status": "PENDING",
    "total": { "amount": 125000000, "currency": "TZS" },
    "currency": "TZS",
    "payment": null
  }
}
```
*Distinct fixture from `§7.1` — `§7.1` is `OD-00001` `ord_01h8y5a1b2c3d4e5f6g7h8j9`/`OD-2026-00042` (`PICKUP`); this is `OD-00002` `ord_01h8y5a1b2c3d4e5f6g7h8k0`/`OD-2026-00043` (`DELIVERY`). `delivery_fee` `null`, `PENDING` — provisional `total == subtotal`; `PAY-001` blocked `409 DELIVERY_FEE_PENDING` per `§23.11`; `delivery_fee` pending via `ORD-014` before payment.*

### 7.3 Checkout idempotency / retry

- Same `Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000` + same body → replays `201` same `OD-2026-00042`, no second Order.
- Same key + different `fulfillment_type` (`PICKUP` vs `DELIVERY`) → `409 CONFLICT` `DUPLICATE_OPERATION`.
- `POST /checkout {delivery_fee:{amount:100}}` → `422 INVALID_VALUE field: delivery_fee` (never client-controlled); `POST /checkout {total:{amount:99999}}` → `422 field: total`.

### 7.4 Checkout failures

- Empty cart `POST /checkout` → `422 CART_INVALID` (`§31.12`).
- `MADE_TO_ORDER` in cart → `422 PRODUCT_NOT_PURCHASABLE`.
- Unauthenticated `POST /checkout` → `401 AUTHENTICATION_REQUIRED`.
- Invalid `delivery_address.city` missing → `422 INVALID_VALUE field: delivery_address.city`.

---

## 8. Order Examples

### 8.1 List own orders — `ORD-001` `GET /api/v1/me/orders` — Customer

```http
GET /api/v1/me/orders?page=1&per_page=20&order_status=PROCESSING HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```

```json
{
  "data": [
    {
      "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
      "order_reference": "OD-2026-00043",
      "status": "PROCESSING",
      "fulfillment_type": "DELIVERY",
      "delivery_fee_status": "FINALIZED",
      "subtotal": { "amount": 125000000, "currency": "TZS" },
      "delivery_fee": { "amount": 2500000, "currency": "TZS" },
      "total": { "amount": 127500000, "currency": "TZS" },
      "currency": "TZS",
      "created_at": "2026-01-15T09:30:00Z",
      "updated_at": "2026-01-15T10:15:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_next": false, "has_previous": false }
  }
}
```
*Private:* `Cache-Control: private, no-store`; `Customer A` pagination over own dataset only. *Uses delivery fixture `OD-00002` `OD-2026-00043`; pickup fixture `OD-00001` `OD-2026-00042` is `PICKUP` `§7.1`.*

### 8.2 Get own order detail — `ORD-002` `GET /api/v1/me/orders/{order}` — Customer

```http
GET /api/v1/me/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
    "order_reference": "OD-2026-00043",
    "status": "PROCESSING",
    "fulfillment_type": "DELIVERY",
    "delivery_fee_status": "FINALIZED",
    "items": [
      { "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1", "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9", "sku": "OAK-TABLE-6S-NAT", "name": "Solid Oak Dining Table", "variant_name": "Natural Oak", "unit_price": { "amount": 125000000, "currency": "TZS" }, "quantity": 1, "line_total": { "amount": 125000000, "currency": "TZS" } }
    ],
    "subtotal": { "amount": 125000000, "currency": "TZS" },
    "delivery_fee": { "amount": 2500000, "currency": "TZS" },
    "total": { "amount": 127500000, "currency": "TZS" },
    "currency": "TZS",
    "delivery_address": { "recipient_name": "Asha Mwangi", "phone": "+255700000001", "address_line": "Jengo Street 12", "city": "Dar es Salaam" },
    "delivery": { "status": "PROCESSING", "tracking_summary": "Being prepared" },
    "payment": { "payment_status": "PAID", "amount": { "amount": 127500000, "currency": "TZS" } },
    "created_at": "2026-01-15T09:30:00Z",
    "updated_at": "2026-01-15T10:15:00Z"
  }
}
```
*Historical `unit_price` preserved even if `Product` price later `1,200,000`; `Customer A → Customer B order` → `404 ORDER_NOT_FOUND` masked.*

### 8.3 Cancel own order — `ORD-004` `POST /api/v1/me/orders/{order}/cancel` — Customer

```http
POST /api/v1/me/orders/ord_01h8y5a1b2c3d4e5f6g7h8j9/cancel HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440002

{}
```

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8j9",
    "order_reference": "OD-2026-00042",
    "status": "CANCELLED",
    "updated_at": "2026-01-15T09:35:00Z"
  }
}
```
*Pickup fixture `OD-00001` `OD-2026-00042` (delivery fixture `OD-00002` `OD-2026-00043` cancels identically while `PENDING_PAYMENT`). Requires `owns + PENDING_PAYMENT + within 20 min` server time; after `ACCEPTED` or after 20 min → `422 ORDER_NOT_CANCELLABLE` (`422` business rule) / race `cancel` vs `Staff accept` → `409 ORDER_STATE_CONFLICT`.*

### 8.4 Cancellation failures

- `Customer B GET /me/orders/ord_of_A` → `404 ORDER_NOT_FOUND` masked (not `403`).
- `PATCH /orders/{order} {status:"CANCELLED"}` → `422 INVALID_VALUE` (never `PATCH {status}`).
- Already `CANCELLED` + `cancel` replay same key → replays `200 CANCELLED`; same key different body → `409`.

### 8.5 Order tracking — `ORD-003` `GET /api/v1/me/orders/{order}/tracking` — Customer (delivery example)

```http
GET /api/v1/me/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/tracking HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```

```json
{
  "data": {
    "order_reference": "OD-2026-00043",
    "fulfillment_type": "DELIVERY",
    "current_status": "SHIPPED",
    "delivery_fee_status": "FINALIZED",
    "timeline": [
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9", "status": "PENDING_PAYMENT", "occurred_at": "2026-01-15T09:30:00Z", "label": "Order received" },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9a", "status": "PAID", "occurred_at": "2026-01-15T09:40:00Z", "label": "Payment confirmed" },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9b", "status": "ACCEPTED", "occurred_at": "2026-01-15T10:00:00Z", "label": "Order accepted" },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9c", "status": "PROCESSING", "occurred_at": "2026-01-15T10:15:00Z", "label": "Being prepared" },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9d", "status": "SHIPPED", "occurred_at": "2026-01-15T14:00:00Z", "label": "Shipped" }
    ]
  }
}
```
*Private `Cache-Control: private, no-store`; `current_status == Order.status`; `PICKUP` timeline never `SHIPPED`; no GPS.*

---

## 9. Staff/Admin Operational Examples

### 9.1 Staff order list — `ORD-005` `GET /api/v1/orders` — Staff/Admin

```http
GET /api/v1/orders?order_status=PAID&page=1&per_page=20 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
// Actor: STAFF USR-0101 (orders.view_operational)
```

```json
{
  "data": [
    {
      "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
      "order_reference": "OD-2026-00043",
      "status": "PAID",
      "fulfillment_type": "DELIVERY",
      "delivery_fee_status": "FINALIZED",
      "total": { "amount": 127500000, "currency": "TZS" },
      "customer": { "name": "Asha Mwangi", "email": "asha@example.com" },
      "created_at": "2026-01-15T09:30:00Z"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_next": false, "has_previous": false } }
}
```
*Purpose-limited `name/email` only; not entire customer account. Uses delivery fixture `OD-00002` `OD-2026-00043`; pickup equivalent would be `ord_01h8y5a1b2c3d4e5f6g7h8j9`/`OD-2026-00042`.*

### 9.2 Staff order detail — `ORD-006` `GET /api/v1/orders/{order}` — Staff/Admin (operational detail — richer `status_history` actor/note)

```http
GET /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
// Actor: STAFF USR-0101 (orders.view_operational) — delivery fixture OD-2026-00043 (pickup equivalent OD-2026-00042 same shape with fulfillment_type PICKUP)
```

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
    "order_reference": "OD-2026-00043",
    "status": "PROCESSING",
    "fulfillment_type": "DELIVERY",
    "delivery_fee_status": "FINALIZED",
    "items": [
      { "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1", "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9", "sku": "OAK-TABLE-6S-NAT", "name": "Solid Oak Dining Table", "variant_name": "Natural Oak", "unit_price": { "amount": 125000000, "currency": "TZS" }, "quantity": 1, "line_total": { "amount": 125000000, "currency": "TZS" } }
    ],
    "subtotal": { "amount": 125000000, "currency": "TZS" },
    "delivery_fee": { "amount": 2500000, "currency": "TZS" },
    "total": { "amount": 127500000, "currency": "TZS" },
    "currency": "TZS",
    "delivery_address": { "recipient_name": "Asha Mwangi", "phone": "+255700000001", "address_line": "Jengo Street 12", "city": "Dar es Salaam" },
    "delivery": { "status": "PROCESSING", "tracking_summary": "Being prepared" },
    "payment": { "payment_status": "PAID", "amount": { "amount": 127500000, "currency": "TZS" } },
    "customer": { "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l", "name": "Asha Mwangi", "email": "asha@example.com", "phone": "+255700000001" },
    "status_history": [
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9", "status": "PENDING_PAYMENT", "occurred_at": "2026-01-15T09:30:00Z", "actor": { "type": "CUSTOMER", "id": "user_01h8y0c1d2e3f4g5h6i7j8k9l" }, "note": null },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9a", "status": "PAID", "occurred_at": "2026-01-15T09:40:00Z", "actor": { "type": "SYSTEM", "id": "system" }, "note": null },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9b", "status": "ACCEPTED", "occurred_at": "2026-01-15T10:00:00Z", "actor": { "type": "STAFF", "id": "user_01h8y1a2b3c4d5e6f7g8h9i0" }, "note": null },
      { "id": "evt_01h8y5a1b2c3d4e5f6g7h8j9c", "status": "PROCESSING", "occurred_at": "2026-01-15T10:15:00Z", "actor": { "type": "STAFF", "id": "user_01h8y1a2b3c4d5e6f7g8h9i0" }, "note": null }
    ],
    "created_at": "2026-01-15T09:30:00Z",
    "updated_at": "2026-01-15T10:15:00Z"
  }
}
```
*Operational vs customer (`ORD-002`): adds `customer` purpose-limited (`name/email/phone` only, no `password`/`tokens`/other orders) and richer `status_history` with `actor` (`CUSTOMER`/`SYSTEM`/`STAFF`) and `note` where authorized; `Cache-Control: private, no-store`; `PICKUP` branch same shape with `fulfillment_type: PICKUP`, `delivery_fee: {amount:0}`, `delivery_address: null`, and `SHIPPED` never appears.*

### 9.3 Staff accept — `ORD-007` `POST /api/v1/orders/{order}/accept` — Staff/Admin (delivery example; pickup uses `OD-00001`)

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/accept HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440003
// Actor: STAFF, orders.accept + PAID + FINALIZED — delivery fixture OD-2026-00043 (pickup fixture OD-2026-00042 follows same transition)

{}
```

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
    "order_reference": "OD-2026-00043",
    "status": "ACCEPTED",
    "updated_at": "2026-01-15T10:00:00Z"
  }
}
```
*Idempotent replay same key → `200 ACCEPTED`; `PAID` required else `409 CONFLICT`.*

### 9.4 Staff start processing — `ORD-008` `POST /api/v1/orders/{order}/process` (delivery fixture)

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/process HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440004

{}
```

```json
{
  "data": { "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0", "order_reference": "OD-2026-00043", "status": "PROCESSING", "updated_at": "2026-01-15T10:15:00Z" }
}
```

### 9.5 Pickup fulfillment — `ORD-009` + `ORD-013` (PICKUP branch — `OD-00001`)

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8j9/ready-for-pickup HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440005

{}
```
*`PROCESSING→READY_FOR_PICKUP` (`PICKUP` only); `DELIVERY` → `409 BUSINESS_RULE_VIOLATION`.*

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8j9/complete HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440006

{}
```
*`READY_FOR_PICKUP→COMPLETED` (`ORD-013`).*

### 9.6 Delivery fulfillment — `ORD-010` + `ORD-011` + `ORD-013` (DELIVERY branch — `OD-00002`)

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/ship HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440007

{}
```

```json
{
  "data": { "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0", "order_reference": "OD-2026-00043", "status": "SHIPPED", "updated_at": "2026-01-15T14:00:00Z" }
}
```

*`PROCESSING→SHIPPED` `DELIVERY` only; `PICKUP→SHIPPED` → `422`.*

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/deliver HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440008

{}
```
*`SHIPPED→DELIVERED`.*

### 9.7 Delivery fee — `ORD-014` `POST /api/v1/orders/{order}/delivery-fee` — Staff/Admin (variable, server-calculated) — `OD-00002`

**Request:** Only `delivery_fee` `{amount,currency}` (never `total`).

```http
POST /api/v1/orders/ord_01h8y5a1b2c3d4e5f6g7h8k0/delivery-fee HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440009

{
  "delivery_fee": { "amount": 2500000, "currency": "TZS" }
}
```

**Response:** `total` server-calculated `subtotal + delivery_fee.amount`.

```json
{
  "data": {
    "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0",
    "order_reference": "OD-2026-00043",
    "status": "PENDING_PAYMENT",
    "fulfillment_type": "DELIVERY",
    "delivery_fee": { "amount": 2500000, "currency": "TZS" },
    "delivery_fee_status": "FINALIZED",
    "total": { "amount": 127500000, "currency": "TZS" },
    "payment": null
  }
}
```
*`PICKUP` → `422 BUSINESS_RULE_VIOLATION`; already `FINALIZED` → `409`; same key different `amount` → `409 DUPLICATE_OPERATION`; `total` client `422 field: total`.*

**Authorization failure:** `Customer POST /orders/{order}/delivery-fee` → `403 FORBIDDEN` (or `POST /checkout {delivery_fee:…}` → `422`).

### 9.8 Inventory adjustment — `INV-003` `POST /api/v1/inventory/{inventory}/adjust` — Staff/Admin

```http
POST /api/v1/inventory/inv_01h8y0a1b2c3d4e5f6g7h8j9/adjust HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440010

{
  "quantity_delta": 10,
  "reason": "STOCK_RECEIPT"
}
```

```json
{
  "data": {
    "id": "inv_01h8y0a1b2c3d4e5f6g7h8j9",
    "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1",
    "variant_id": "var_01h8x9k1m2n3p4q5r6s7t8u9",
    "quantity": 110,
    "reserved_quantity": 2,
    "available_quantity": 108,
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*`quantity` integer units (not TZS), server `new_quantity = current + delta` transactional; duplicate same key replays `200` no second delta.*

---

## 10. Made-to-Order Request Examples

### 10.1 Anonymous creation — `REQ-001` `POST /api/v1/requests` — Public

```http
POST /api/v1/requests HTTP/1.1
Host: api.example.com
Content-Type: application/json
Accept: application/json

{
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t2",
  "quantity": 1,
  "name": "Asha Mwangi",
  "phone": "+255700000001",
  "email": "asha@example.com",
  "dimensions": { "length": 220, "width": 90, "height": 75, "unit": "cm" },
  "material": "walnut, matte finish",
  "color": "natural walnut with charcoal fabric",
  "notes": "Need 2.2m dining table for 6, rounded corners."
}
```

```json
{
  "data": {
    "id": "req_01h8y5a1b2c3d4e5f6g7h8j9",
    "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t2",
    "quantity": 1,
    "name": "Asha Mwangi",
    "phone": "+255700000001",
    "email": "asha@example.com",
    "dimensions": { "length": 220, "width": 90, "height": 75, "unit": "cm" },
    "material": "walnut, matte finish",
    "color": "natural walnut with charcoal fabric",
    "notes": "Need 2.2m dining table for 6, rounded corners.",
    "request_status": "SUBMITTED",
    "attachments": [],
    "created_at": "2026-01-15T09:30:00Z",
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*Generic `product_id: null` also valid for custom request; `user_id: null` stored; no `order_id`/`payment`.*

### 10.2 Staff request processing — `REQ-006` `PATCH /api/v1/requests/{request}` — Staff/Admin

```http
PATCH /api/v1/requests/req_01h8y5a1b2c3d4e5f6g7h8j9 HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
// Actor: STAFF

{
  "request_status": "IN_REVIEW"
}
```

*Only `request_status` (`SUBMITTED→IN_REVIEW→CLOSED`) + `staff_internal_notes` mutable; customer fields immutable; `user_id` never client-supplied.*

### 10.3 Attachment — `REQ-007` `POST /api/v1/requests/{request}/attachments` — Scoped `Anonymous*`

```http
POST /api/v1/requests/req_01h8y5a1b2c3d4e5f6g7h8j9/attachments HTTP/1.1
Host: api.example.com
Content-Type: multipart/form-data; boundary=----Boundary
Authorization: Bearer <ACCESS_TOKEN>
X-Upload-Token: <SCOPED_SERVER_ISSUED_TOKEN>

------Boundary
Content-Disposition: form-data; name="attachment"; filename="inspiration.jpg"
Content-Type: image/jpeg

<binary>
------Boundary--
```
*`0 or 1` per `REQ-001` inline `multipart/form-data` preferred; private URL `GET` inherits parent `requests.view`; no permanent public URL.*

---

## 11. General Enquiry Examples

### 11.1 Anonymous enquiry — `ENQ-001` `POST /api/v1/enquiries` — Public

```http
POST /api/v1/enquiries HTTP/1.1
Host: api.example.com
Content-Type: application/json

{
  "name": "Asha Mwangi",
  "email": "asha@example.com",
  "phone": "+255700000001",
  "subject": "Do you deliver to Dodoma?",
  "message": "Hello, I would like to know if you deliver the Solid Oak Dining Table to Dodoma and what the delivery time would be.",
  "category": "DELIVERY",
  "product_id": "prod_01h8x9j2m4k5n6p7q8r9s0t1"
}
```

```json
{
  "data": {
    "id": "enq_01h8y5a1b2c3d4e5f6g7h8j9",
    "name": "Asha Mwangi",
    "email": "asha@example.com",
    "phone": "+255700000001",
    "subject": "Do you deliver to Dodoma?",
    "message": "Hello, I would like to know if you deliver the Solid Oak Dining Table to Dodoma and what the delivery time would be.",
    "category": "DELIVERY",
    "product": { "id": "prod_01h8x9j2m4k5n6p7q8r9s0t1", "name": "Solid Oak Dining Table", "slug": "solid-oak-dining-table" },
    "order": null,
    "enquiry_status": "OPEN",
    "attachments": [],
    "created_at": "2026-01-15T09:30:00Z",
    "updated_at": "2026-01-15T09:30:00Z"
  }
}
```
*`subject` `5..200`, `message` `10..5000` plain text; `order_id` ownership-validated if supplied; `enquiry_status` server `OPEN`.*

### 11.2 Staff enquiry processing — `ENQ-006` `POST /api/v1/enquiries/{enquiry}/close`

```http
POST /api/v1/enquiries/enq_01h8y5a1b2c3d4e5f6g7h8j9/close HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>

{}
```

*`OPEN→CLOSED` (`ENQ-006`), original `message` immutable, `staff_internal_notes` separate.*

---

## 12. Notification Examples

### 12.1 Customer notifications — `NOT-001` `GET /api/v1/me/notifications` — Customer

```http
GET /api/v1/me/notifications?page=1&per_page=20 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
```

```json
{
  "data": [
    {
      "id": "not_01h8y5a1b2c3d4e5f6g7h8j9",
      "type": "ORDER_SHIPPED",
      "title": "Order shipped",
      "message": "Your order OD-2026-00043 has been shipped.",
      "read_at": null,
      "is_read": false,
      "target": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
      "source": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
      "created_at": "2026-01-15T14:00:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_next": false, "has_previous": false },
    "unread_count": 1
  }
}
```
*Private `Cache-Control: private, no-store`; `target` does not grant access.*

### 12.2 Staff notifications — `NOT-001` operational `NEW_ORDER` — Staff/Admin (recipient-scoped operational queue)

```http
GET /api/v1/me/notifications?page=1&per_page=20 HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
// Actor: STAFF USR-0101 (OPERATIONAL own, recipient-scoped)
```

```json
{
  "data": [
    {
      "id": "not_01h8y5a1b2c3d4e5f6g7h8k1",
      "type": "NEW_ORDER",
      "title": "New order received",
      "message": "Order OD-2026-00043 from Asha Mwangi requires review.",
      "read_at": null,
      "is_read": false,
      "target": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
      "source": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
      "created_at": "2026-01-15T09:31:00Z"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_next": false, "has_previous": false },
    "unread_count": 1
  }
}
```
*Operational `NOT-001` reuses same `GET /me/notifications` path as customer `§12.1` but with `STAFF` bearer — `AUTHENTICATED_OWNER`/`OPERATIONAL` own, recipient-scoped (not shared customer queue, not private customer-account info); `Cache-Control: private, no-store`; filtering `?type=NEW_ORDER` uses allow-list per `§28.8`.*

### 12.3 Mark notification read — `NOT-002` `PATCH /api/v1/me/notifications/{notification}` — Customer/Staff (recipient-only, `read` only)

```http
PATCH /api/v1/me/notifications/not_01h8y5a1b2c3d4e5f6g7h8j9 HTTP/1.1
Host: api.example.com
Content-Type: application/json
Accept: application/json
Authorization: Bearer <ACCESS_TOKEN>
// Actor: CUSTOMER CUS-0001 (owns not_01h8y5a1b2c3d4e5f6g7h8j9); Staff equivalent uses not_01h8y5a1b2c3d4e5f6g7h8k1 with STAFF token
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440012

{
  "read": true
}
```

```json
{
  "data": {
    "id": "not_01h8y5a1b2c3d4e5f6g7h8j9",
    "type": "ORDER_SHIPPED",
    "title": "Order shipped",
    "message": "Your order OD-2026-00043 has been shipped.",
    "read_at": "2026-01-15T14:05:00Z",
    "is_read": true,
    "target": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
    "source": { "type": "ORDER", "id": "ord_01h8y5a1b2c3d4e5f6g7h8k0" },
    "created_at": "2026-01-15T14:00:00Z",
    "updated_at": "2026-01-15T14:05:00Z"
  }
}
```
*Only `read: boolean` mutable (`true` → `read_at` now, `false` → `read_at: null`); `type/title/message/target/source/recipient` immutable — `PATCH {type:"ORDER_PAID"}` → `422 INVALID_VALUE field: type`. Designed idempotent — replay same `read:true` returns same `200`; `read:false` reverts to unread. `Customer A PATCH /me/notifications/{not_B_of_CustomerB} {read:true}` → `404 RESOURCE_NOT_FOUND` masked per `§15.8`, not `403`; `Staff PATCH` same masking for non-owned operational notification. `Idempotency-Key` optional for `NOT-002` (designed idempotent); where sent, same key different `read` → `409 DUPLICATE_OPERATION`.*

### 12.4 Notification after order acceptance (event → downstream)

```
Staff POST /orders/{order}/accept (ORD-007) → Order ACCEPTED → Business event → Customer NOTIFICATION ORDER_ACCEPTED (↓) → Order remains ACCEPTED even if notification fails (downstream, §28.15)
```

---

## 13. Admin Staff Approval

### 13.1 Approve Staff — `ADM-004` `POST /api/v1/admin/staff/{user}/approve` — Admin

```http
POST /api/v1/admin/staff/user_01h8y9a0b1c2d3e4f5g6h7j9/approve HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440011
// Actor: ADMIN USR-0001 (user_01h8y9a0b1c2d3e4f5g6h7j8), target: pending STAFF USR-0102 (user_01h8y9a0b1c2d3e4f5g6h7j9)

{}
```

```json
{
  "data": {
    "id": "user_01h8y9a0b1c2d3e4f5g6h7j9",
    "role": "STAFF",
    "staff_state": "ACTIVE",
    "updated_at": "2026-01-15T10:00:00Z"
  }
}
```
*Audited `actor, user, action: approve, previous: PENDING, new: ACTIVE`; `approved_by` never client-supplied.*

---

## 14. Authorization Failure Examples

### 14.1 Staff-to-Admin — `403`

```http
POST /api/v1/admin/staff/user_01h8y9a0b1c2d3e4f5g6h7j8/approve HTTP/1.1
Host: api.example.com
Authorization: Bearer <ACCESS_TOKEN>
// Actor: STAFF USR-0101
```

```json
{
  "errors": [ { "code": "FORBIDDEN", "message": "You do not have permission to perform this action." } ],
  "meta": { "request_id": "01H9-req-abc123" }
}
```
*`403`, not `404` masking for admin-only (authenticated but not allowed).*

### 14.2 Role escalation — `PATCH /me {role:ADMIN}` — `422`

```http
PATCH /api/v1/me HTTP/1.1
Host: api.example.com
Content-Type: application/json
Authorization: Bearer <ACCESS_TOKEN>

{
  "role": "ADMIN"
}
```

```json
{
  "errors": [ { "code": "INVALID_VALUE", "message": "This field cannot be modified.", "field": "role" } ],
  "meta": { "request_id": "01H9-req-def456" }
}
```

### 14.3 Customer account boundary

`Staff GET /orders/{order}` sees `customer: {name, phone: +255700000001, delivery_address: {city: ...}}` purpose-limited; `Staff PATCH /me {user_id: another}` or `Staff GET /me?user_id=another` rejected; `Staff cannot change customer role/password/block ordering` via `§30.12` (`403`).

---

## 15. Inventory Authorization Failure

*`Customer POST /inventory/{inventory}/adjust`* → `403 FORBIDDEN` (requires `inventory.manage`); `PATCH /inventory/{id} {quantity:999}` → `422 INVALID_VALUE` (only `POST .../adjust` allowed).

---

## 16. Error Envelope Matrix

| Scenario | Status | Code | Envelope |
|---|---|---|---|
| Missing authentication `GET /me/orders` unauth | `401` | `AUTHENTICATION_REQUIRED` | `{"errors":[{"code":"AUTHENTICATION_REQUIRED","message":"Authentication required."}],"meta":{"request_id":"…"}}` |
| Wrong role `Customer GET /orders` | `403` | `FORBIDDEN` | same |
| Not found / masked `Customer A GET /me/orders/{order-B}` | `404` | `ORDER_NOT_FOUND` | `{"errors":[{"code":"ORDER_NOT_FOUND","message":"Resource not found."}],"meta":{"request_id":"…"}}` |
| Invalid state `Staff ship PICKUP` | `409` | `ORDER_STATE_CONFLICT` | `{"errors":[{"code":"ORDER_STATE_CONFLICT","message":"Invalid order transition."}],"meta":{"request_id":"…"}}` |
| Concurrent `Staff A ship + Staff B ship` | `409` | `RESOURCE_VERSION_CONFLICT` | same, one `200` one `409` |
| Validation `POST /me/cart/items {quantity:0}` | `422` | `INVALID_VALUE` `field: quantity` | `{"errors":[{"code":"INVALID_VALUE","message":"Quantity must be between 1 and 100.","field":"quantity"}],"meta":{"request_id":"…"}}` |
| Rate limit `POST /auth/register` burst | `429` | `RATE_LIMITED` + `Retry-After` | `{"errors":[{"code":"RATE_LIMITED","message":"Too many requests."}],"meta":{"request_id":"…"}}` + header |
| Unexpected | `500` | `INTERNAL_SERVER_ERROR` | `{"errors":[{"code":"INTERNAL_SERVER_ERROR","message":"An unexpected error occurred."}],"meta":{"request_id":"…"}}` |

All use `errors` array + `meta.request_id`, never `error`/`message` top-level, per `§15`.

---

## 17. Pagination, Filtering, Sorting

**Pagination (all collections):** `GET /api/v1/products?page=1&per_page=20` → `meta.pagination` as in `§3.1` ( `current_page:1, per_page:20, total:86, last_page:5, has_next, has_previous` ). Same for `GET /me/orders`, `GET /requests?request_status=SUBMITTED`, `GET /me/notifications`.

**Filtering:** `GET /api/v1/products?category=living-room&product_type=IN_STOCK&availability=available&min_price=50000000` per `§21.2`; `GET /api/v1/orders?order_status=PAID&fulfillment_type=DELIVERY` (`order_status` CLOSED, not `status`); `GET /api/v1/admin/products?is_active=true&is_published=false` (`CAT-013` operational filters). Arbitrary `?field=value` → `422`.

**Sorting:** `GET /api/v1/products?sort=price&sort_direction=asc` (`sort` allow-list `created_at`, `price`, `name` + `id ASC` tie-breaker per `§21.2`); `GET /me/orders?sort=created_at&sort_direction=desc` (`created_at DESC, id ASC`).

---

## 18. Money & Date/Time & Enum

**Money:** All `{amount:int,currency:"TZS"}` minor units, never `15000` bare / `"15000"` / `15000.00` float — e.g., `product.price {amount:125000000,currency:"TZS"}` = `1,250,000.00 TZS`; `delivery_fee {amount:2500000,currency:"TZS"}`.

**Date/time:** All `2026-01-15T09:30:00Z` (`2026-01-15T10:00:00Z`, `2026-01-15T14:00:00Z`) `ISO8601 Z`, never `2026-01-15 09:30` / `15/01/2026`.

**Enums:** `CUSTOMER`/`STAFF`/`ADMIN`, `PICKUP`/`DELIVERY`, `IN_STOCK`/`MADE_TO_ORDER`, `PENDING_PAYMENT`→`COMPLETED`/`CANCELLED`, `available|unavailable` (lower) + `IN_STOCK|LOW_STOCK|MADE_TO_ORDER`, `SUBMITTED|IN_REVIEW|CLOSED`, `OPEN|CLOSED`, `STOCK_RECEIPT|CORRECTION|DAMAGE|RETURN|AUDIT_ADJUSTMENT` — all `CLOSED` `UPPER_SNAKE_CASE` except `availability`.

---

## 19. Complete Lifecycles (Primary Integration Examples)

### 19.1 Pickup lifecycle (end-to-end)

```
1. Browse catalog                → GET /api/v1/products (CAT-001) 200
2. Read product                  → GET /api/v1/products/solid-oak-dining-table (CAT-002) 200
3. Authenticate/register         → POST /api/v1/auth/register (AUTH-001) 201 → POST /api/v1/auth/login (AUTH-002) 200 (Authorization: Bearer <ACCESS_TOKEN>)
4. Add to cart                   → POST /api/v1/me/cart/items (CART-002) {product_id, variant_id, quantity:1} 201
5. Checkout PICKUP               → POST /api/v1/checkout (CHK-001) {fulfillment_type:PICKUP} Idempotency-Key → 201 {order_reference: OD-2026-00042, delivery_fee:{amount:0}, status:PENDING_PAYMENT, delivery_fee_status:FINALIZED}
6. Pay                           → Group H PAY-001 POST /api/v1/payments (on final total 125000000) → webhook → Order PAID (Group H)
7. Staff accepts                  → POST /api/v1/orders/{order}/accept (ORD-007) STAFF → 200 ACCEPTED → Customer NOTIFICATION ORDER_ACCEPTED (downstream, §31.20)
8. Staff starts processing       → POST /api/v1/orders/{order}/process (ORD-008) → 200 PROCESSING
9. Staff ready for pickup        → POST /api/v1/orders/{order}/ready-for-pickup (ORD-009) → 200 READY_FOR_PICKUP
10. Customer tracks              → GET /api/v1/me/orders/{order}/tracking (ORD-003) 200 timeline PICKUP branch
11. Staff completes               → POST /api/v1/orders/{order}/complete (ORD-013) → 200 COMPLETED
12. Notifications summary        → GET /api/v1/me/notifications (NOT-001) 200 (ORDER_RECEIVED, ORDER_ACCEPTED, ORDER_READY_FOR_PICKUP, ORDER_COMPLETED throughout)
```

### 19.2 Delivery lifecycle (with fee)

```
Customer DELIVERY → POST /api/v1/checkout {fulfillment_type:DELIVERY, delivery_address:{…}} → 201 {delivery_fee:null, status:PENDING_PAYMENT, delivery_fee_status:PENDING, total:subtotal}
  ↓ Staff assigns fee → POST /api/v1/orders/{order}/delivery-fee {delivery_fee:{amount:2500000,currency:TZS}} Idempotency-Key → 200 {delivery_fee:{2500000}, total:127500000, status:PENDING_PAYMENT, delivery_fee_status:FINALIZED}
  ↓ Group H payment on final total 127500000 → PAID
  ↓ ORD-007 accept → ACCEPTED → ORD-008 process → PROCESSING → ORD-010 ship → SHIPPED → ORD-011 deliver → DELIVERED → ORD-013 complete → COMPLETED
  ↓ Tracking GET /me/orders/{order}/tracking shows DELIVERY branch SHIPPED→DELIVERED; delivery_fee_status FINALIZED throughout after ORD-014
```

### 19.3 Made-to-order request lifecycle

```
Customer/anonymous → POST /api/v1/requests (REQ-001) {product_id:null|MADE_TO_ORDER, quantity:1, name, phone, dimensions:{length:220,unit:cm}} → 201 {request_status:SUBMITTED, id:req_…}
  ↓ Staff → GET /api/v1/requests (REQ-004) 200 (operational queue) → GET /api/v1/requests/{request} (REQ-005) 200 (with staff_internal_notes if authorized)
  ↓ Staff → PATCH /api/v1/requests/{request} (REQ-006) {request_status:IN_REVIEW} → 200 → {request_status:CLOSED} → 200 (no Order auto-created, no inventory, no payment)
```

### 19.4 Enquiry lifecycle

```
Anonymous/authenticated → POST /api/v1/enquiries (ENQ-001) {name, subject:"Do you deliver to Dodoma?", message:"…", category:DELIVERY, product_id:prod_…} → 201 {enquiry_status:OPEN, id:enq_…}
  ↓ Staff → GET /api/v1/enquiries (ENQ-004) → GET /api/v1/enquiries/{enquiry} (ENQ-005) (with staff_internal_notes)
  ↓ Staff → POST /api/v1/enquiries/{enquiry}/close (ENQ-006) {} → 200 {enquiry_status:CLOSED} (original message immutable)
```

### 19.5 Staff approval lifecycle

```
Admin → POST /api/v1/admin/staff (ADM-003) {name,email,phone} → 201 {staff_state:PENDING, role:STAFF server-set, no password in body}
  ↓ Admin → POST /api/v1/admin/staff/{user}/approve (ADM-004) Idempotency-Key → 200 {staff_state:ACTIVE} audited (actor, previous:PENDING→ACTIVE)
  ↓ Staff becomes operational (STAFF cannot self-approve → 403, cannot approve another → 403)
```

---

## 20. Cross-References

- **Contract:** `docs/api/api-contract.md` `§2` envelope, `§15` errors, `§21` catalog, `§22` cart, `§23` checkout, `§24` order, `§25` tracking, `§26` request, `§27` enquiry, `§28` notification, `§29` user/profile, `§30` staff/admin, `§31` review (matrices `§31.29A-E`, invariants `§31.27`, test scenarios `§31.30`).
- **Conventions:** `docs/api/api-conventions.md` `§30`/`§31`.
- **Resources:** `docs/api/api-resources.md` `§10`/`§15`.
- **Domain:** `docs/domain/business-rules.md` `§15`/`§20`.
- **Decisions:** `docs/decisions.md` `ADR/STAFF-001..008` + `§31.26` reconciliation.

> **Ready for OpenAPI:** Each example above maps 1:1 to `docs/api/openapi.yaml` `paths` `operationId` `requestBody`/`responses` `examples` per `phases/phase-1.32.md` (request example, response example, error example); no `openapi.yaml` is authored in this phase.
