# Data Classification — Version 1

## 1. Purpose

Privacy/sensitivity classification for important data categories. This classification will later influence API serialization, authorization, logging, and security controls. Detailed implementation (encryption, masking, logging rules) belongs to later phases.

## 2. Classification Levels

| Level | Meaning |
|---|---|
| **PUBLIC** | Safe to expose publicly (catalog, prices, availability). |
| **INTERNAL** | Operational data; not customer-private but not for public exposure. |
| **PRIVATE** | Belongs to a specific customer; requires ownership-based access. |
| **SENSITIVE** | High-sensitivity; strict handling required (credentials, payment, secrets). |

## 3. Classification Matrix

| Data category | Classification | Notes |
|---|---|---|
| Product name | PUBLIC | Catalog. |
| Product slug | PUBLIC | SEO/URL. |
| Product description | PUBLIC | |
| Product price (current) | PUBLIC | Catalog price. |
| Product availability / type | PUBLIC | `IN_STOCK` / `MADE_TO_ORDER`. |
| Product SKU / business reference | PUBLIC | Catalog-facing reference. |
| Category name / slug / description | PUBLIC | |
| Product image references | PUBLIC | |
| Variant name / attributes | PUBLIC | |
| Physical / reserved stock quantities | INTERNAL | Not public; availability signal is derived. |
| Staff operational notes | INTERNAL/PRIVATE | Order/request handling notes. |
| Order reference (`OD-…`) | PRIVATE | Owner-scoped; `AUTHENTICATED` (owner) / `STAFF`, `ADMIN` per `api-exposure-classification.md`; not `PUBLIC` (prevents enumeration). |
| Order status / history timeline | PRIVATE | Owner-visible; not cross-customer. |
| Order monetary totals | PRIVATE | |
| Cart contents | PRIVATE | |
| Customer name / display name | PRIVATE | |
| Customer phone | PRIVATE | |
| Customer email | PRIVATE | |
| Customer address (billing/delivery) | PRIVATE | |
| User authentication credential | SENSITIVE | Never raw; hash only. |
| Password-reset/verification secrets | SENSITIVE | Backend-only. |
| Payment status / amounts | SENSITIVE | Financial transaction data. |
| Payment provider transaction reference | SENSITIVE | |
| Payment provider secrets | SENSITIVE | Backend-only; never in clients/repo. |
| Made-to-order request contents | PRIVATE | |
| General enquiry contents | PRIVATE | |
| Attachment files (requests/enquiries) | PRIVATE | |
| Notification content | PRIVATE | |
| Inventory movement/audit data | INTERNAL | If inventory history is retained. |
| Order status history (internal actor/note) | INTERNAL/PRIVATE | Actor info is internal; timeline owner-visible. |

## 4. API Exposure vs Sensitivity

Sensitivity (`PUBLIC`/`INTERNAL`/`PRIVATE`/`SENSITIVE`) describes **how** data must be handled; API exposure (`PUBLIC`/`AUTHENTICATED`/`STAFF`/`ADMIN`/`INTERNAL ONLY` per `phase-1.4.md:1174`) describes **who** may receive it. See `api-exposure-classification.md` for the entity/attribute exposure matrix.

## 5. Cross-Cutting Rules

- **SENSITIVE** data (credentials, payment secrets, reset secrets) must never appear in browser code, Flutter code, public configuration, or the Git repository (SEC-004).
- **PRIVATE** data is owner-scoped: a customer may access only their own orders, requests, enquiries, addresses, payment info, and notifications (IDENT-006).
- **INTERNAL** data (physical/reserved stock, operational notes) is not exposed publicly and is `INTERNAL ONLY` in the API exposure classification.
- Public catalog exposure does not leak private customer or payment data.