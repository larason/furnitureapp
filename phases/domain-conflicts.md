# Domain Conflicts — Phase 1.3

## 1. Statement

**No unresolved domain-rule conflicts found.** All Phase 1.1 decisions and Phase 1.2 resolutions/vocabulary are respected by the Phase 1.3 invariants and business rules.

One **reference-level (documentation) inconsistency** was identified. It does not affect any domain rule and is recorded below for owner awareness; it did not require stopping.

---

## 2. Identified Inconsistency — Payment phase-group numbering

- **Source A (Phase 1.1):** `phase-1.1-api-scope.md` resolved decision 6 and `phase-1.3.md` §22.2 both defer payment provider selection to **"Phase Group G"**.
- **Source B (AGENTS.md roadmap, governing):** PHASE GROUP G = **Checkout and Fulfilment**; PHASE GROUP H = **Payments** (Phase 8.1 "Payment provider selection/integration boundary").

**Impact:** None on domain rules. Payment provider selection is deferred regardless of the group label; the provider-agnostic payment boundary is identical either way.

**Handling (transparent, not silent):** This document records the inconsistency. For phase-group references, **AGENTS.md is treated as the governing roadmap** (payments = Phase Group H). Phase 1.3 documents therefore reference the payments deferral as "Phase Group H (payments)" and note the discrepancy where relevant (see `domain-invariants.md` PAY-003).

**Owner action (optional):** If the project owner prefers the Phase 1.1 phrasing, this is a documentation-only rename (Group G ↔ Group H) and changes no business rule.

---

## 3. Validation Against Phase 1.1 Decisions

| Approved Phase 1.1 decision | Status |
|---|---|
| Anonymous browsing | Preserved (IDENT-001/003, business rules ch.1) |
| Authenticated checkout | Preserved (IDENT-002, CHECKOUT-001) |
| Anonymous made-to-order requests | Preserved (IDENT-004, REQ-001) |
| Anonymous enquiries | Preserved (IDENT-005, ENQ-001) |
| Variable delivery fees (staff-controlled) | Preserved (PRICE-006, FUL-003; region/location per Decision 19) |
| Payment deferred (provider-agnostic) | Preserved (PAY-003; see §2 for group-number note) |
| 20-minute customer cancellation window | Preserved (CANCEL-001/002, OS-016/017) |
| `OD-` order reference | Preserved (ORD-006) |
| Email deferred to Phase Group R | Preserved (NOTIF-002/003) |
| Saved address book deferred | Preserved (ADDR-001/002) |
| Roles/permissions deferred to Phase Group D | Preserved (AUTHZ-001/002) |
| Optional attachments | Preserved (ATTACH-001) |

Result: **No contradiction.**

## 4. Validation Against Phase 1.2 Domain Boundaries

| Boundary (must not collapse) | Status |
|---|---|
| Product ≠ Inventory | Preserved (INV, CAT-006) |
| Product ≠ Order Item | Preserved (CAT-006, ORD-005) |
| Product ≠ Made-to-order Request | Preserved (REQ-002, CAT-006) |
| Made-to-order Request ≠ Order | Preserved (REQ-004/005, ORD-003) |
| Enquiry ≠ Made-to-order Request | Preserved (ENQ-004) |
| Cart ≠ Order | Preserved (CART-003, ORD-001) |
| Payment ≠ Order Status | Preserved (PAY-001) |
| Delivery ≠ Order | Preserved (FUL, ORD-001) |

Result: **No contradiction.**

---

## Conclusion

Phase 1.3 may proceed. The only item to track is the payment phase-group number reference documented in §2.