# Action Naming Policy — Version 1

## 0. Purpose
Defines how future business-action endpoints must be named. No action inventory is enumerated here; only the naming convention for when an explicit action endpoint is justified. No HTTP methods, status codes, or schemas defined.

## 1. When Action Endpoints Are Justified
An explicit action endpoint is justified when the operation is a **controlled domain action** with invariants/authorization, not ordinary editable data:
- Order status transitions (accept, process, ready-for-pickup, ship, deliver)
- Cancellation (customer 20-min window vs staff/admin)
- Checkout workflow that transforms cart into order
- Other workflows later identified as business actions (confirmation, verification) where `PATCH {"status":"SHIPPED"}` would abuse CRUD.

Status transitions must not automatically be treated as arbitrary `PATCH` updates. Later API design distinguishes **ordinary editable data vs controlled domain action**.

## 2. Naming Convention
- **Pattern:** `/{resource}/{identifier}/{action}` where `{action}` is a single lowercase kebab-case segment under the owning resource.
- Examples:
  - `/api/v1/orders/{order}/cancel`
  - `/api/v1/orders/{order}/accept`
  - `/api/v1/orders/{order}/process`
  - `/api/v1/orders/{order}/ready-for-pickup`
  - `/api/v1/orders/{order}/ship`
  - `/api/v1/orders/{order}/deliver`
- **Avoid inconsistent styles:** Do not mix `/api/v1/orders/{order}/cancel`, `/api/v1/orders/{order}/ship-order`, `/api/v1/orders/{order}/markAsDelivered` in one API. One consistent style: lowercase kebab-case action verb phrase, no camelCase, no `UPPERCASE`, no underscores, no mixed `ship-order` vs `cancel` vs `markReady`.
- **Action verb choice:** Use domain action names from `order-state-rules.md` / `resource-actions.md`: `cancel`, `accept`, `process` (maps to `PROCESSING`), `ready-for-pickup`, `ship`, `deliver`, `complete` — always kebab-case, e.g., `ready-for-pickup` not `readyForPickup` or `ready_for_pickup`.
- **No top-level verb resources:** Do not create `/api/v1/cancel-order`, `/api/v1/process-order`, `/api/v1/ship-order` — actions are subresources of the owning resource (`orders/{order}`).

## 3. What Action Endpoints Are Not
- Not top-level verbs.
- Not CRUD abuse: `PATCH /api/v1/orders/{order}` with `{"status":"SHIPPED"}` bypasses business invariants and authorization; some status transitions require dedicated action endpoints with validation.
- Not enumerated here: The complete inventory of which actions get endpoints is deferred to later detailed action design (Phase 1.7 `resource-actions.md` identifies candidates; this policy only governs naming).

## 4. Consistency Rules
- One convention project-wide: lowercase kebab-case action segment.
- No `getOrders`, `createOrder`, `fetchProducts` — collection actions use HTTP methods later, not path verbs.
- Idempotency expectations for actions (e.g., duplicate `cancel` handling) are defined later under HTTP method conventions (Phase 1.10), not here.

## 5. Payment and Other Deferrals
Payment action naming (initiation, confirmation, webhook) is deferred to **Phase Group H**; this policy applies generally when such actions are exposed, but no provider-specific action paths are defined here.
