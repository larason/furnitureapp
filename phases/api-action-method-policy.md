# API Action Method Policy — Version 1

## 1. Purpose
Defines when a business operation must be modeled as a controlled `POST` action instead of a generic `PATCH`. Preserves domain invariants from Phase 1.3 and prevents method abuse.

## 2. Distinction

| Generic resource update | Business state transition |
|---|---|
| `PATCH` | Controlled `POST` action (`.../cancel`, `.../ship`) |
| Ordinary data modification | Domain command with validation, auth, side effects |

- `PATCH /api/v1/me/profile` updating phone → generic update (ordinary data).
- `POST /api/v1/orders/{order}/ship` moving `PROCESSING → SHIPPED` → business operation (requires valid previous state, staff authorization, delivery conditions, history, notifications).

## 3. When to Use Controlled POST Action
Use an explicit `POST` action when the operation:
- Causes a state transition on a state machine (`Order`, `Request` lifecycle);
- Triggers side effects (audit history, inventory, notifications, payment);
- Cannot be represented safely as simple field updates;
- May not be naturally idempotent without design;
- Requires authorization beyond ordinary field writability.

## 4. Version 1 Action Candidates

| Action | Resource | Why not generic `PATCH` |
|---|---|---|
| Checkout (`Cart → Order`) | `Checkout` workflow | Requires validation, pricing, inventory, fulfillment, order creation and payment initiation when applicable, idempotency; provider-specific behavior in Phase Group H |
| Cancel order | `Order` | 20-minute window, status eligibility, payment implications, audit |
| Accept order | `Order` | `PENDING_PAYMENT → PAID → ACCEPTED`, staff/admin only, history |
| Process order | `Order` | `ACCEPTED → PROCESSING`, operational |
| Ready for pickup | `Order` | `PROCESSING → READY_FOR_PICKUP` (pickup only) |
| Ship order | `Order` | `PROCESSING → SHIPPED` (delivery only), side effects |
| Deliver order | `Order` | `SHIPPED → DELIVERED` |
| Complete order | `Order` | Terminal, may be system/staff |
| Activate / Deactivate / Archive product | `Product` | Affects visibility, purchasing, SEO, inventory; may warrant controlled action rather than arbitrary `PATCH is_active` |
| Mark notification read/unread | `Notification` | If read state has business rules, consider controlled action vs plain `PATCH` (future decision) |

## 5. When Generic PATCH Is Appropriate
- Update profile information (name, phone display, non-sensitive fields) — ordinary data.
- Update cart item quantity to absolute value — `PATCH` with idempotent absolute assignment.
- Update product information in admin for ordinary fields (name, description, price, category) — where no state-machine side effect.
- Update request/enquiry administrative metadata (non-state) — where no workflow transition.

Even here, `PATCH` must respect domain invariants: do not allow `PATCH {"status": "DELIVERED"}` to bypass valid transition protection, delivery conditions, and history. Such transitions must use the controlled action path.

## 6. Method Does Not Bypass Invariants
`PATCH order.status` must not bypass `invalid transition protection`. `POST checkout` must not bypass stock validation, pricing authority, or authentication. `DELETE order` must not bypass the 20-minute cancellation rule. The HTTP method is never an escape route around domain rules (Phase 1.10 §60).

## 7. Naming Compatibility
Action naming remains per `action-naming-policy.md`: kebab-case under owning resource (`/orders/{order}/cancel`, not `/cancel-order`, not `ship-order` vs `markAsDelivered` mixing). Method policy and naming policy must stay consistent.

## 8. Payment Deferral
Payment-specific action semantics (initiation, confirmation, webhook verification) are defined in **Phase Group H**. This policy establishes general action modeling (controlled `POST` for state-changing payment initiation, `POST` event for webhooks with idempotency); no provider-specific methods are chosen here.
