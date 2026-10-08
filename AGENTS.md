# AI Agent Project Guide — Furniture E-Commerce Platform

*Debugging is twice as hard as writing the code in the first place. Therefore, if you write the code as cleverly as possible, you are, by definition, not smart enough to debug it - One wise man once said*.

## 1. Project Identity

**Project type:** Small-to-medium furniture e-commerce platform

**Primary products:** Ready-made furniture that is physically available in stock

**Secondary offering:** Furniture that can be manufactured on request

**Customer platforms:**
- Public e-commerce website: Next.js + Material UI (MUI)
- Mobile application: Flutter + Material 3

**Backend:** Laravel API

**Database:** MySQL

**Admin application:** Next.js + MUI

**Primary architecture:** API-first, shared backend, shared data model, separate customer frontends

## Note:

As of 2026-09-29 Initial Production Commerce Mode — Request Only. The first production release publishes only MADE_TO_ORDER products. Normal Cart→Checkout→Payment→Order purchasing remains disabled until business registration, payment-provider onboarding, and the deferred Groups G/H/I prerequisites are completed. Customers express purchase intent through the Made-to-Order Request flow. This is a deployment-scope decision, not removal of the frozen V1 commerce contracts.

---

## 2. Project Vision

Build a reliable, maintainable, secure, SEO-friendly furniture commerce platform that is intentionally sized for a small business rather than an enterprise-scale furniture corporation.

The system must make the real business workflow simple:

1. Customers discover furniture on the website or app.
2. Ready-made, in-stock furniture can be purchased directly.
3. Furniture that can be manufactured on request is requested rather than purchased as normal stock.
4. Customers can submit general furniture enquiries without purchasing anything.
5. Customers can choose self-pickup or paid delivery during checkout.
6. Customers can track accepted and shipped/delivered orders.
7. Staff manage products, stock, requests, orders, fulfilment, customers and enquiries from the admin system.
8. Website and mobile app share the same backend and business rules.

The platform must be designed so that the business can grow later without forcing version 1 to implement enterprise features that are not currently needed.

**Note -** Always note that A good furniture store msut have:

1. **Easy Navigation**: A great website is simple to use. Categories are clear, and menus are easy to follow. This is the foundation for cool furniture stores online.

2. **High-Quality Images and Details**: Shoppers cannot touch or feel the furniture online. That is why sharp images, multiple views, and detailed descriptions are a must. They help buyers make confident decisions.

3. **Smooth Checkout Process**: No one likes a complicated checkout. The best websites keep it fast, simple, and secure, with different payment options to fit customer needs.

4. **Mobile-First friendly Design**: Most people shop on their phones. A website that looks and works well on any device provides a simple experience for users.

5. **Personalization and Inspiration**: The best platforms do not just sell furniture. They guide customers with room ideas, style suggestions, and product recommendations, making shopping easier and more fun.

6. **Best support for assistive technologies**. No one is left behind hence every human either disabled or not he/she CAN use the website as easy as possible.

In short, a great furniture eCommerce website blends design, function, and trust. By investing in professional eCommerce web design services, brands can overcome the challenges of traditional retail and create an online shopping experience that is smooth, intuitive, and enjoyable.

---

## 3. Core Architectural Principle

The backend is the authority for all business rules.

The database is the source of truth for persistent state.

The Laravel application/API owns business operations and validation.

Neither the browser nor the Flutter application may connect directly to MySQL.

The intended flow is:

```text
Next.js Website ──┐
                  ├── HTTPS API ── Laravel ── MySQL
Flutter App ──────┤
                  │
Admin App ─────────┘
```

The following are prohibited:

```text
Flutter ──> MySQL
Browser ──> MySQL
Admin frontend ──> MySQL directly
```

Business rules must not be duplicated independently in React, Flutter and Laravel.

**Validation authority (Phase 1.15):** Frontend validation (Next.js, Flutter) is advisory/UX only; backend/domain validation is authoritative. The API must independently enforce all business invariants — transport → schema → auth → authorization → domain (cross-field/conditional/state) → concurrency/transaction → external — regardless of client-side checks. See `docs/api/api-contract.md §14` and `docs/api/api-conventions.md §16`.

---

## 4. Product/Business Model

### 4.1 Product types

Version 1 supports two primary product modes:

```text
IN_STOCK
MADE_TO_ORDER
```

#### IN_STOCK

Customer can:

```text
Browse → Add to Cart → Checkout → Pay → Fulfilment → Track Order
```

#### MADE_TO_ORDER

Customer can:

```text
Browse → Request Furniture → Submit Requirements → Admin Follow-up
```

A made-to-order request is not automatically an order.

A request becomes an order only after the business explicitly agrees on the commercial terms and a proper order is created through the defined business process.

### 4.2 General enquiries

Customers must also be able to contact the business without selecting a purchasable product.

Examples:
- Custom furniture ideas
- Questions about materials
- Bulk/office furniture requests
- Furniture not currently listed
- General contact requests

### 4.3 Fulfilment types

Version 1 supports:

```text
PICKUP
DELIVERY
```

Pickup is free.

Delivery has a fee determined by the backend/business rules.

### 4.4 Order lifecycle

The implementation should support an explicit order state machine. Initial expected statuses are:

```text
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

Not every order uses every status.

Pickup flow:

```text
PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP → COMPLETED
```

Delivery flow:

```text
PAID → ACCEPTED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
```

Statuses must be validated by the backend. Frontends must never invent or force arbitrary status changes.

---

## 5. Primary Actors

### CUSTOMER

Can:
- Browse products
- Search/filter products
- View product details
- Register/login
- Manage profile
- Add purchasable products to cart
- Checkout
- Select pickup/delivery
- Pay
- View order history
- Track orders
- Submit made-to-order requests
- Submit general enquiries
- Receive relevant notifications

### STAFF

Can perform operational tasks explicitly granted to them, such as:
- Review orders
- Process orders
- Update fulfilment status
- Review furniture requests
- Handle enquiries

### ADMIN

Has broader administrative privileges, including:
- Product management
- Category management
- Inventory management
- Orders
- Customers
- Requests
- Enquiries
- Staff/user management
- System settings

Use authorization policies/permissions rather than scattered frontend-only role checks.

---

## 6. Expected Core Domain Entities

The first stable domain model is expected to include at least:

```text
User
Category
Product
ProductImage
ProductVariant
Inventory

Cart
CartItem

Order
OrderItem
OrderAddress
OrderStatusHistory

Payment
Delivery

FurnitureRequest
Enquiry

Notification
```

These are a starting domain model, not a command to create every table immediately. Each entity must be justified by an actual business requirement before implementation.

Avoid unnecessary tables and premature abstractions.

---

## 7. API-First Principle

The API contract must be established before building dependent frontend features.

The contract must define:
- Endpoint naming
- HTTP methods
- Request structure
- Response structure
- Authentication expectations
- Validation rules
- Error format
- Pagination
- Filtering
- Sorting
- Resource naming
- Status codes
- Versioning strategy

Initial API versioning should use a stable namespace such as:

```text
/api/v1/...
```

The API contract is the agreement between:

```text
Laravel ↔ Next.js ↔ Flutter ↔ Admin
```

The agent must not casually change an API response shape after clients have started depending on it.

Breaking changes require explicit identification and a migration strategy.

Prefer OpenAPI documentation as the authoritative machine-readable API contract when implementation begins.

**Version 1 API Contract Freeze (Phase 1.35):**
The Version 1 API contract is frozen. Do not change API paths, request/response schemas, enums, authorization behavior, business-state transitions, financial rules, or other externally observable API behavior without following the post-freeze contract-change process. The contract is documented in `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/api-conventions.md`, and `docs/api/openapi.yaml`. Internal implementation details (DB indexes, query optimizations, service structuring) are permitted as long as externally observable behavior remains identical.


---

## 8. Frontend Principles

### 8.1 Website

Use:
- Next.js
- App Router
- TypeScript
- Material UI
- Server rendering/server components where appropriate
- Client components only where interactivity requires them

Primary website goals:
- SEO
- Fast initial rendering
- Excellent product discovery
- Strong product/category landing pages
- Responsive design
- Accessible UI

Do not build the website as an unnecessarily large client-only SPA.

Important product/category information should be renderable in the initial server response wherever practical.

### 8.2 Mobile

Use:
- Flutter
- Material 3
- Strong separation between presentation and data concerns
- Repository-based data access
- Clear feature boundaries

The app should consume the same API as the website.

Do not create a separate mobile-only business logic implementation for rules owned by Laravel.

### 8.3 Design consistency

Website and app should share a visual language, not necessarily identical layouts.

Define shared design tokens for:
- Color
- Typography
- Spacing
- Shape/radius
- Elevation where applicable
- Interaction principles

Implement those concepts independently in MUI and Flutter Material 3.

Do not force desktop web UI to behave like a mobile screen.

---

## 9. SEO Principles

The public website is an SEO product as well as an application.

For important public pages, plan for:
- Crawlable HTML
- Stable human-readable URLs
- Product/category metadata
- Canonical URLs
- Sitemap
- Robots rules
- Structured data
- Good page titles/descriptions
- Correct heading structure
- Internal linking
- Fast loading
- Responsive design
- Accessible content

Product information, price and availability should be represented in a way that search engines can reliably discover.

SEO must not depend entirely on client-side JavaScript execution.

---

## 10. Performance Principles

Performance is a design constraint from the beginning, not a cleanup activity.

Prioritize:
- Server rendering for SEO-critical pages
- Efficient image handling
- Proper image sizing/formats
- Lazy loading where appropriate
- Minimal unnecessary client-side JavaScript
- Pagination for large datasets
- Efficient database queries
- Database indexes based on actual query patterns
- API response discipline
- Caching only where useful and correctly invalidated

Do not optimize prematurely based on guesses. Measure before and after meaningful optimizations.

---

## 11. Database Rules

Use migrations as the authoritative database schema history.

Use seeders/factories for repeatable development and test data.

Use foreign keys and constraints where appropriate.

Use transactions for multi-step operations that must succeed or fail together.

Inventory and payment-related operations require particular care with race conditions and duplicate processing.

Historical orders must preserve purchased product information and price snapshots rather than relying solely on current product values.

Keep timestamps and audit/history data wherever operational accountability matters.

Never store raw passwords.

Never expose database credentials to frontend applications.

Never infer that a database is disposable from `APP_ENV` alone. Both environment
and database-name checks are required before destructive migration commands. The
repository-wide guard for `migrate:fresh` is:

```bash
test "${APP_ENV:-}" != "production"
test "${DB_DATABASE:-}" = ":memory:" -o \
     "${DB_DATABASE:-}" = "furnitureapp_test_disposable"
php artisan migrate:fresh --seed --force
```

Never run `migrate:fresh` against the configured development, staging, or
production application database. For destructive MySQL verification, create the
disposable database named `furnitureapp_test_disposable`, point the command
explicitly at it, verify the environment is non-production, run with `--force`,
and destroy the disposable database afterward.

---

## 12. Inventory Rules

Inventory is backend-controlled.

The system must distinguish at least:

```text
physical quantity
reserved quantity
available quantity
```

The system must prevent overselling under concurrent requests.

Inventory reservation/reduction must be transactional where required.

A UI displaying “In Stock” is only informative; final availability must be checked by Laravel/database logic.

---

## 13. Cart and Checkout Rules

Client totals are never authoritative.

Laravel must calculate/verify:

```text
subtotal
item quantities
delivery fee
discounts if introduced
total
```

The client sends selections; the backend calculates authoritative monetary values.

Money should be represented safely and consistently. Avoid unreliable floating-point arithmetic for financial calculations.

---

## 14. Payment Rules

Payment status must never be trusted solely from frontend behavior.

Expected pattern:

```text
Create payment/order intent
    ↓
Customer pays
    ↓
Provider callback/webhook
    ↓
Backend verifies payment
    ↓
Backend updates payment/order
```

Webhook handling must be idempotent.

Duplicate callbacks must not create duplicate orders or duplicate payment effects.

Payment provider secrets belong only on the backend/server side.

---

## 15. Order Tracking Rules

Current order status is not enough; important status transitions must be recorded.

Use an order status history concept such as:

```text
order_status_history
```

Each significant transition should capture:
- Order
- New status
- Time
- Actor where appropriate
- Note/reason when relevant

Customer tracking should present the history in a simple timeline.

Do not build GPS logistics tracking unless the business later actually requires it.

---

## 16. Requests and Enquiries

Made-to-order requests and general enquiries are separate workflows from normal orders.

Their lifecycle should be modeled separately.

Avoid converting every form submission into an order.

Provide staff with clear status handling for requests/enquiries.

---

## 17. Authentication and Authorization

Use a backend-centered authentication model.

The mobile app and website may use different client-side session/token handling, but they share the same user system and authorization rules.

Use secure mechanisms supported by the backend framework rather than implementing custom cryptography/authentication.

Authorization must be enforced on the backend for every protected operation.

Frontend route protection is a UX/security layer, not the final authority.

**Authentication ownership & role (Phase 1.17, mandatory):**

- **Customer authentication/authorization **must preserve account ownership** — a customer owns own account; `STAFF`/`ADMIN` do not own customer accounts and must not browse as customer, restrict legitimate browsing/ordering, or access customer credentials (`docs/api/api-contract.md §17.1`, `docs/domain/business-rules.md §17`).
- **Customer signup baseline (Phase 4.5):** registration credentials are `email` + `password` only; **phone is not a signup requirement** (it is application profile/contact data). Clerk is the sole email-verification authority (`email verification code` baseline); `LocalUserProvisioner` must refuse to provision an unverified Clerk email (`INVALID_AUTHENTICATION` 401). Email verification provisions `CUSTOMER` only, never alters role/account-state, and never leaves the public catalog or anonymous request/enquiry unprotected.
- **Flutter authentication boundary (Phase 4.7):** Flutter authenticates only through Clerk, obtains the current Clerk session token through a thin adapter, and sends `Authorization: Bearer <Clerk session_token>` to Laravel. Flutter must not send passwords to Laravel, create mobile-specific users/tokens, persist passwords or bearer tokens in insecure storage, or implement a second verifier. Laravel reuses the existing Clerk middleware and local `users.clerk_user_id` mapping; public API calls remain usable without a token.
- **Never allow a client to self-assign `STAFF` or `ADMIN`** — roles are CLOSED `CUSTOMER`/`STAFF`/`ADMIN` and server-controlled; `{"role":"ADMIN"}` from client is rejected.
- **Authentication and authorization are enforced server-side** — shared identity across Website/Flutter/Admin against same Laravel backend; checkout and private resources (`own orders`, etc.) require authenticated `CUSTOMER`, anonymous browsing/requests/enquiries remain public, and anonymous checkout is rejected.
- **Frontend authentication state is never an authority** for backend permissions — SSR catalog pages remain public without login, and browser/Flutter session handling does not bypass 404 masking (`RESOURCE_NOT_FOUND`) for private ownership.

**Authorization (Phase 1.18, mandatory):**

- **Authorization is enforced server-side** — decide `authenticated identity + role + resource + action + ownership + business-state + context` (`docs/api/api-contract.md §18.1`) for every protected operation; `role` alone never authorizes.
- **Never trust client-supplied `role`, `ownership`, `permission`, or resource identity** as proof — `{"user_id":"another"}`, `?user_id=another`, `{"role":"ADMIN"}`, or `{"status":"DELIVERED"}` are rejected and do not bypass policy.
- **Customer ownership must be preserved** — `Customer A → Customer B` order/request/notification access fails via object-level check; `Customer A → owns Order` is `AUTHENTICATED_OWNER`, staff is `OPERATIONAL` not owner.
- **Staff operational access must not become customer-account administration** — staff have `orders.view_operational/process/ship` (with valid state) and approved `inventory/catalog` per `staff.*` permissions, but no `password/role/disable/impersonate/block browsing-or-ordering/transfer ownership` capabilities.
- **Administrative authority must remain explicit and auditable** — only `ADMIN` may `staff.approve`/`staff.manage`/`users.manage_authorized`; no `*` wildcard; actions log `actor/action/resource/target/timestamp/result` without secrets.
- **Protected resources use deny-by-default** — `products/categories → PUBLIC` explicitly public; all else denied unless explicitly authorized; field-level exposure is before serialization (only permitted fields per actor, private vs public cache separated).
- **Business-state rules and authorization are both required** for state-changing operations — `STAFF ship` needs `ship` permission **and** `Order=PROCESSING`; `CUSTOMER cancel` needs `owns + eligible state + 20-minute window`.

**User/Profile ownership & security (Phase 1.28, mandatory):**

- **Never trust client-supplied user identity for self-service authorization** — `GET /api/v1/me` and `PATCH /api/v1/me` derive identity from `authenticated principal` only; `?user_id=another`, `{"user_id":"another"}`, `GET /me?user_id=123`, or `GET /api/v1/users/{id}` without `users.manage_authorized` must not return another user's profile (`docs/api/api-contract.md §29.2`, `docs/api/api-conventions.md §29.1`).
- **Never allow profile updates to modify role, permissions, ownership, or security state** — `PATCH /api/v1/me {role: ADMIN}`, `{"permissions":["*"]}`, `{"account_state":"ACTIVE"}`, `{"email_verified":true}`, `{"user_id":"..."}` are rejected (`422 INVALID_VALUE` `field: role`); only `name`/`phone` are on the customer-profile allow-list (`docs/api/api-contract.md §29.6`, `docs/api/api-resources.md §8.4`, `docs/api/api-conventions.md §29.4`).
- **Credential changes must use dedicated authentication/security workflows** — `password` via `POST /api/v1/auth/change-password` (not `PATCH /me {password}`), `email` security change via dedicated workflow (`authenticated request → security confirmation → verification → changed`), `role`/`permissions`/`account_state` via Admin-only Phase 1.29 (`docs/api/api-contract.md §29.7/§29.8`).
- **Customer accounts are customer-owned; Staff operational access does not confer customer-account administration** — staff `GET /me` sees self only; staff cannot `change customer role/disable customer/modify ordering ability/impersonate/view credentials/transfer ownership` via User/Profile (`docs/api/api-contract.md §29.12`, `docs/domain/business-rules.md §14`, `AGENTS.md §17` authentication ownership).

---

## 18. Security Baseline

Security is mandatory from the first backend implementation.

Always consider:
- Input validation
- Authorization
- Secure password hashing
- CSRF protection where applicable
- Secure cookies where applicable
- Rate limiting
- CORS restrictions
- SQL injection prevention through framework/database APIs
- XSS prevention
- Secure file upload handling
- Webhook verification
- Secret management
- Error-message discipline — never expose raw framework, database, or infrastructure exceptions through API responses; map them to the documented API error contract (`docs/api/api-contract.md §15`, `docs/api/api-conventions.md §17`)
- Auditability
- Dependency updates

Do not commit secrets, tokens or production credentials to source control.

---

## 19. Testing Strategy

Testing must be developed alongside features, not postponed until the end.

At minimum plan for:

### Unit tests

Business rules and calculations.

### Feature/API tests

Authentication, catalog, cart, checkout, orders, requests, enquiries, payments and authorization.

### Frontend tests

Important UI/state behavior and validation.

### End-to-end tests

Critical customer journeys such as:

```text
Browse → Product → Cart → Checkout → Payment → Order → Tracking
```

and:

```text
Made-to-order product → Request → Admin review
```

Regression tests should be added whenever a production bug is fixed.

---

## 20. Code Quality Rules

Prefer:
- Small cohesive modules
- Explicit naming
- Single responsibility
- Type safety
- Dependency injection where useful
- Small services/actions instead of huge controllers
- Reusable UI components
- Centralized validation
- Centralized API clients
- Consistent error handling
- Formatting/linting/static analysis

Avoid:
- Giant controllers
- Giant React components
- Giant Flutter widgets
- Copy-pasted business rules
- Magic strings scattered throughout the codebase
- Database queries embedded everywhere
- Premature microservices
- Premature generic abstractions
- Overengineering for hypothetical future features

---

## 21. Git and Change Management

Use Git from day one.

Recommended workflow:

```text
feature branch
    ↓
small focused commits
    ↓
review
    ↓
automated checks
    ↓
merge
```

Commits should explain meaningful changes, not vague activities.

Avoid mixing unrelated changes in one commit.

Database migrations, API changes and client changes should be traceable.

---

## 22. Environments

Use separate environments at minimum:

```text
LOCAL
STAGING
PRODUCTION
```

Do not use production secrets or payment credentials in local development.

Environment-specific configuration belongs in environment/secret management rather than committed source code.

---

## 23. CI/CD Principles

Before production deployment, automate the quality gate as much as practical:

```text
format
lint
static analysis
tests
build
```

Deploy through a repeatable process.

Do not make production changes manually unless there is an explicit operational reason and the action is documented.

---

## 24. Observability and Recovery

The system must make important failures diagnosable.

Plan for:
- Application logs
- API error logging
- Payment failures
- Webhook failures
- Queue/job failures where used
- Database backup
- Off-site backup
- Restore testing

A backup strategy is incomplete unless restore procedures are tested.

---

## 25. Deployment Philosophy

For the initial business scale, prefer a simple reliable architecture over distributed infrastructure.

Do not introduce Kubernetes, microservices or complex event-driven infrastructure unless actual scale or operational requirements justify it.

The application should be structured so future extraction is possible without forcing that complexity into version 1.

---

# 26. Dependency-First Development Roadmap

This roadmap is intentionally divided into many small phases.

**Important process rule:** Do NOT implement all phases in one pass.

At the end of each phase, the project owner should return to the AI agent and request the instructions for the next phase.

Each phase must establish the prerequisites needed by the following phase.

The agent must treat the current phase as the active scope and avoid leaking implementation work from distant phases unless needed to explain a dependency.

---

## PHASE GROUP A — API CONTRACT FOUNDATION

### Phase 1.1 — Define API scope

Establish what the API is responsible for and what it is not responsible for.

### Phase 1.2 — Define domain nouns

Create the canonical vocabulary for products, variants, inventory, orders, payments, fulfilment, requests, enquiries and users.

### Phase 1.3 — Define API versioning

Establish `/api/v1` strategy and compatibility rules.

### Phase 1.4 — Define resource structure

Determine the resources exposed by the API.

### Phase 1.5 — Define endpoint naming conventions

Set consistent URL and HTTP method conventions.

### Phase 1.6 — Define request conventions

Establish JSON/request body, query parameter, pagination and filtering conventions.

### Phase 1.7 — Define response conventions

Define successful response structure, collection structure and metadata conventions.

### Phase 1.8 — Define error contract

Define standard validation, authorization, not-found, conflict and server error responses.

### Phase 1.9 — Define authentication contract

Specify public, authenticated and privileged endpoints conceptually.

### Phase 1.10 — Define authorization contract

Map customer/staff/admin capabilities to backend responsibilities.

### Phase 1.11 — Define catalog contract

Specify category/product/product-variant/inventory API shapes.

### Phase 1.12 — Define cart contract

Specify cart read/write operations and validation behavior.

### Phase 1.13 — Define checkout contract

Specify fulfilment choice, addresses and authoritative totals.

### Phase 1.14 — Define payment contract

Specify payment initiation, status and webhook-facing expectations.

### Phase 1.15 — Define order contract

Specify order representation and customer-visible data.

### Phase 1.16 — Define order-status-history contract

Specify tracking timeline data.

### Phase 1.17 — Define made-to-order request contract

Specify request submission and lifecycle.

### Phase 1.18 — Define enquiry contract

Specify general customer enquiry behavior.

### Phase 1.19 — Define API examples

Create realistic request/response examples for critical flows.

### Phase 1.20 — Define OpenAPI document structure

Prepare the contract for machine-readable documentation.

### Phase 1.21 — Review API contract

Check for ambiguity, duplication, security gaps and frontend usability problems before backend implementation starts.

**Phase Group A exit condition:** The API contract is coherent enough that Laravel, Next.js and Flutter can be implemented against the same agreed interface.

---

## PHASE GROUP B — LARAVEL BACKEND FOUNDATION

### Phase 2.1 — Repository/project setup
### Phase 2.2 — Laravel application initialization
### Phase 2.3 — Environment configuration
### Phase 2.4 — Database connection
### Phase 2.5 — Base application structure
### Phase 2.6 — API routing foundation
### Phase 2.7 — Exception/error handling foundation
### Phase 2.8 — Logging foundation
### Phase 2.9 — Health/status endpoint
### Phase 2.10 — Coding standards and static analysis
### Phase 2.11 — Test framework setup
### Phase 2.12 — CI baseline

**Exit condition:** Laravel runs reliably in local development with the agreed conventions and automated checks.

---

## PHASE GROUP C — DATABASE AND DOMAIN MODEL

### Phase 3.1 — Users schema
### Phase 3.2 — Roles/permissions model
### Phase 3.3 — Categories schema
### Phase 3.4 — Products schema
### Phase 3.5 — Product variants schema
### Phase 3.6 — Product images schema
### Phase 3.7 — Inventory schema
### Phase 3.8 — Cart schema
### Phase 3.9 — Orders schema
### Phase 3.10 — Order items snapshot model
### Phase 3.11 — Order status history schema
### Phase 3.12 — Payment schema
### Phase 3.13 — Delivery schema
### Phase 3.14 — Furniture request schema
### Phase 3.15 — Enquiry schema
### Phase 3.16 — Notification schema
### Phase 3.17 — Foreign keys/indexes/constraints review
### Phase 3.18 — Factories and seed data
### Phase 3.19 — Migration test

**Exit condition:** Database schema accurately represents the agreed domain and can be rebuilt from migrations.

---

## PHASE GROUP D — AUTHENTICATION AND AUTHORIZATION (USE CLERK)

### Phase 4.1 — Clerk authentication architecture/design review
Decide the Clerk↔Laravel identity boundary, local users projection, credential-field changes, synchronization rules, account lifecycle, authentication middleware, and testing strategy.

### Phase 4.2 — Clerk/Laravel identity integration
Verify Clerk credentials in Laravel and map Clerk identity → local user.

### Phase 4.3 — Customer registration / sign-in flow including Logout/session lifecycle
Clerk-managed rather than custom Laravel credentials.

### Phase 4.4 — Password recovery/security
### Phase 4.5 — Email/phone verification
### Phase 4.6 — Local profile synchronization
### Phase 4.7 — Mobile authentication boundary
### Phase 4.8 - SPA / Website Authentication with Clerk
Contract documented in Group D; actual Next.js Clerk setup belongs to the later website frontend phases.
### Phase 4.9 — Roles
### Phase 4.10 — Policies/permissions
### Phase 4.11 — Rate limiting / abuse controls
### Phase 4.12 — Authentication tests

**Exit condition:** Customer, staff and admin access paths are secure and tested.

---

## PHASE GROUP E — CATALOG AND INVENTORY API

### Phase 5.1 — Category read API
### Phase 5.2 — Product read API
### Phase 5.3 — Product detail API
### Phase 5.4 — Variant API
### Phase 5.5 — Product search/filter API
### Phase 5.6 — Pagination/sorting
### Phase 5.7 — Product availability rules
### Phase 5.8 — Inventory read model
### Phase 5.9 — Inventory mutation rules
### Phase 5.10 — Concurrency/overselling protection
### Phase 5.11 — Catalog tests

**Exit condition:** A frontend can reliably discover products and understand whether each item is purchasable or request-only.

---

## PHASE GROUP F — CART

### Phase 6.1 — Cart model review
### Phase 6.2 — Create/get cart
### Phase 6.3 — Add item
### Phase 6.4 — Update quantity
### Phase 6.5 — Remove item
### Phase 6.6 — Cart validation
### Phase 6.7 — Stock revalidation
### Phase 6.8 — Cart tests

**Exit condition:** A customer can maintain a valid cart entirely through the API.

---

## PHASE GROUP G — CHECKOUT AND FULFILMENT

### Phase 7.1 — Checkout requirements
### Phase 7.2 — Address model review
### Phase 7.3 — Pickup flow
### Phase 7.4 — Delivery flow
### Phase 7.5 — Delivery fee rules
### Phase 7.6 — Order totals
### Phase 7.7 — Transaction boundaries
### Phase 7.8 — Checkout validation
### Phase 7.9 — Checkout tests

**Exit condition:** A valid cart can become a correctly calculated pending order.

---

## PHASE GROUP H — PAYMENTS *deferred*

### Phase 8.1 — Payment provider selection/integration boundary
### Phase 8.2 — Payment model
### Phase 8.3 — Payment initiation
### Phase 8.4 — Callback/webhook handling
### Phase 8.5 — Signature/verification handling
### Phase 8.6 — Idempotency
### Phase 8.7 — Failed payment handling
### Phase 8.8 — Successful payment handling
### Phase 8.9 — Payment/order consistency tests

**Exit condition:** The system can reliably determine whether an order has actually been paid.

---

## PHASE GROUP I — ORDER MANAGEMENT *deferred*

### Phase 9.1 — Order creation finalization
### Phase 9.2 — Order status state machine
### Phase 9.3 — Status transition validation
### Phase 9.4 — Status history
### Phase 9.5 — Customer order list
### Phase 9.6 — Customer order detail
### Phase 9.7 — Tracking timeline
### Phase 9.8 — Admin order operations
### Phase 9.9 — Cancellation rules
### Phase 9.10 — Order regression tests

**Exit condition:** The full paid-order lifecycle works for pickup and delivery scenarios.

---

## PHASE GROUP J — MADE-TO-ORDER REQUESTS AND ENQUIRIES

### Phase 10.1 — Furniture request API
### Phase 10.2 — Request validation
### Phase 10.3 — Request status lifecycle
### Phase 10.4 — Product-linked requests
### Phase 10.5 — General enquiries
### Phase 10.6 — Attachment handling if required
### Phase 10.7 — Staff/admin request management
### Phase 10.8 — Request/enquiry tests

**Exit condition:** Non-purchase customer demand can be captured and managed separately from normal orders.

---

## PHASE GROUP K — ADMIN BACKEND OPERATIONS

### Phase 11.1 — Admin information architecture
### Phase 11.2 — Admin authentication
### Phase 11.3 — Product CRUD
### Phase 11.4 — Category CRUD
### Phase 11.5 — Image management
### Phase 11.6 — Inventory management
### *Phase 11.7 — Order management-DEFERRED — Group I*
### Phase 11.8 — Customer management
### Phase 11.9 — Request management
### Phase 11.10 — Enquiry management
### *11.11 Payment visibility-DEFERRED — Group H*
### *11.12 Delivery management-DEFERRED — Groups G/I transactional flow*
### Phase 11.13 — Audit visibility

**Exit condition:** Staff can operate the business without using the database directly.

---

## PHASE GROUP L — DESIGN SYSTEM FOUNDATION

### Phase 12.1 — Brand/design goals
### Phase 12.2 — MUI theme structure
### Phase 12.3 — Flutter Material 3 theme structure
### Phase 12.4 — Shared design tokens
### Phase 12.5 — Typography
### Phase 12.6 — Color
### Phase 12.7 — Spacing
### Phase 12.8 — Shape/elevation
### Phase 12.9 — Component conventions
### Phase 12.10 — Accessibility baseline

**Exit condition:** Website and app have an agreed visual system before extensive UI implementation.

---

## PHASE GROUP M — NEXT.JS WEBSITE FOUNDATION

### Phase 13.1 — Next.js project setup
### Phase 13.2 — TypeScript configuration
### Phase 13.3 — MUI integration
### Phase 13.4 — Theme integration
### Phase 13.5 — API client
### Phase 13.6 — Routing conventions
### Phase 13.7 — Layout system
### Phase 13.8 — Error/loading/not-found handling
### Phase 13.9 — Responsive foundation

**Exit condition:** The website can consume the API using the agreed architecture.

---

## PHASE GROUP N — WEBSITE CATALOG AND SEO

### Phase 14.1 — Homepage
### Phase 14.2 — Category pages
### Phase 14.3 — Product listing
### Phase 14.4 — Product detail
### Phase 14.5 — Search
### Phase 14.6 — Filters/sorting
### Phase 14.7 — SEO metadata
### Phase 14.8 — Structured data
### Phase 14.9 — Sitemap/robots
### Phase 14.10 — Internal linking
### Phase 14.11 — Image/performance optimization

**Exit condition:** Public catalog pages are usable, crawlable and performant.

---

## PHASE GROUP O — WEBSITE CUSTOMER COMMERCE

### Phase 15.1 — Registration/login UI
### Phase 15.2 — Cart UI - *deferred*
### Phase 15.3 — Checkout UI - *deferred*
### Phase 15.4 — Pickup/delivery choice - *deferred*
### Phase 15.5 — Payment UI - *deferred*
### Phase 15.6 — Order confirmation - *deferred*
### Phase 15.7 — Customer orders - *deferred*
### Phase 15.8 — Tracking UI - *deferred*
### Phase 15.9 — Furniture request UI
### Phase 15.10 — Enquiry/contact UI

**Exit condition:** The website supports the complete customer purchase and request journeys.

---

## PHASE GROUP P — FLUTTER APP FOUNDATION

### Phase 16.1 — Flutter project setup
### Phase 16.2 — Theme/Material 3
### Phase 16.3 — Environment configuration
### Phase 16.4 — Networking layer
### Phase 16.5 — Authentication storage/session
### Phase 16.6 — Routing/navigation
### Phase 16.7 — Feature/module structure
### Phase 16.8 — Error/loading states
### Phase 16.9 — Logging/diagnostics

**Exit condition:** Flutter can reliably authenticate and communicate with the API.

---

## PHASE GROUP Q — FLUTTER CUSTOMER FEATURES

### Phase 17.1 — Home/catalog
### Phase 17.2 — Categories
### Phase 17.3 — Product detail
### Phase 17.4 — Search/filtering
### Phase 17.5 — Cart
### Phase 17.6 — Checkout
### Phase 17.7 — Payments
### Phase 17.8 — Orders
### Phase 17.9 — Order tracking
### Phase 17.10 — Furniture requests
### Phase 17.11 — Enquiries
### Phase 17.12 — Profile/account
### Phase 17.13 - Aboutus/privacypolicy/terms
### Phase 17.14 - open source license dedicated page

**Exit condition:** The mobile application supports the same core commerce capabilities as the website, adapted for mobile UX.

---

## PHASE GROUP R — NOTIFICATIONS

### Phase 18.1 — Notification domain
### Phase 18.2 — Email notifications
### Phase 18.3 — In-app notifications
### Phase 18.4 — Push notification architecture
### Phase 18.5 — Order notifications
### Phase 18.6 — Request/enquiry notifications
### Phase 18.7 — Notification preferences

**Exit condition:** Customers and staff receive appropriate system updates without coupling core commerce to notification delivery.

---

## PHASE GROUP S — QUALITY ASSURANCE

### Phase 19.1 — Unit-test expansion
### Phase 19.2 — API integration tests
### Phase 19.3 — Web component tests
### Phase 19.4 — Flutter tests
### Phase 19.5 — End-to-end critical paths
### Phase 19.6 — Security testing
### Phase 19.7 — Accessibility testing
### Phase 19.8 — Performance testing
### Phase 19.9 — Regression testing

**Exit condition:** Critical customer and operational workflows are covered by repeatable tests.

---

## PHASE GROUP T — PRODUCTION SECURITY AND OPERATIONS

### Phase 20.1 — Production configuration
### Phase 20.2 — Secret management
### Phase 20.3 — HTTPS/domain setup
### Phase 20.4 — Database backup
### Phase 20.5 — Restore test
### Phase 20.6 — Logging/monitoring
### Phase 20.7 — Error tracking
### Phase 20.8 — Rate limiting review
### Phase 20.9 — File upload/security review
### Phase 20.10 — Dependency/security audit

**Exit condition:** The system is operationally ready for controlled production use.

---

## PHASE GROUP U — CI/CD AND DEPLOYMENT

### Phase 21.1 — Repository branching strategy
### Phase 21.2 — Automated backend checks
### Phase 21.3 — Automated web checks
### Phase 21.4 — Automated Flutter checks
### Phase 21.5 — Staging deployment
### Phase 21.6 — Staging smoke tests
### Phase 21.7 — Production deployment process
### Phase 21.8 — Rollback strategy

**Exit condition:** Deployment is repeatable and recoverable.

---

## PHASE GROUP V — LAUNCH

### Phase 22.1 — Production data preparation
### Phase 22.2 — Product catalog verification
### Phase 22.3 — Payment verification
### Phase 22.4 — Order workflow verification
### Phase 22.5 — SEO verification
### Phase 22.6 — Monitoring verification
### Phase 22.7 — Final security review
### Phase 22.8 — Launch checklist

**Exit condition:** Version 1 is ready for real customers.

---

## PHASE GROUP W — POST-LAUNCH IMPROVEMENT

Only after the core system is stable should optional growth features be considered.

Possible later phases:

```text
Wishlists
Reviews/ratings
Coupons
Promotions
Advanced delivery zones
Customer segmentation
Analytics dashboards
Recommendations
Loyalty features
WhatsApp integration
SMS integration
More sophisticated inventory
Manufacturing workflow
Delivery-agent app
```

These are explicitly not part of the initial core unless later business requirements make them necessary.

---

# 27. AI Agent Operating Rules

The AI agent working on this project must follow these rules.

### Rule 1 — Respect dependency order

Do not implement a dependent feature before its prerequisite architecture/data/API exists.

### Rule 2 — Work one micro-phase at a time

When the project owner asks for a phase, provide instructions for that phase only unless a tiny prerequisite is unavoidable.

Do not dump the entire roadmap into the implementation response.

### Rule 3 — End each phase with an exit condition

Every implementation phase must state what must be true before the phase is considered complete.

### Rule 4 — Verify before proceeding

At the end of a phase, identify:
- Files changed
- Schema/API changes
- Tests added
- Commands/checks to run
- Expected result
- Known risks

### Rule 5 — Prefer incremental changes

Avoid massive generated files and giant rewrites.

Make changes small enough to review and revert.

### Rule 6 — Never silently invent business rules

If implementation requires a business decision not established in this document, choose the smallest safe assumption for the current scale and explicitly record it as an assumption rather than hiding it in code.

### Rule 7 — Keep backend authority centralized

When a business rule affects money, inventory, permissions, orders, fulfilment or payments, Laravel remains authoritative.

### Rule 8 — Keep frontend responsibilities appropriate

Frontends display, collect input, provide interaction and maintain local UI state. They do not become independent sources of truth for commerce rules.

### Rule 9 — Do not overengineer

Do not introduce distributed systems, microservices, CQRS, event sourcing, Kubernetes or other advanced infrastructure merely because they are industry buzzwords.

Use the simplest architecture that can be safely maintained and extended.

### Rule 10 — Security is non-negotiable

Never recommend bypassing authentication, authorization, validation, payment verification or database integrity merely to make development easier.

### Rule 11 — Treat migrations as history

Do not casually edit an already-applied production migration to change live schema. Create a new migration for subsequent changes.

### Rule 12 — Preserve API compatibility

When API changes affect clients, explicitly identify whether the change is backward-compatible or breaking.

### Rule 13 — Test critical paths

Any change to payments, inventory, checkout, order transitions or authentication requires corresponding tests.

### Rule 14 — Favor real business workflows over demos

A feature is not complete because a screen renders. It is complete when the required data flow works from user action through API/business logic/database and back to the user.

### Rule 15 — Documentation is part of implementation

Important architecture decisions, assumptions, API changes and operational procedures should be documented near the relevant code or in project documentation.

### Rule 16 - Comments

Limit the number of comments as minimum as possible when writing any code. This include uneccessary long notes and information.

### Rule 17 - codebase quality and future maintainability

1. Cognitive-complexity and maintainability requirement

**Mandatory code-quality recommendation for this phase:**

Whenever the agent touches or creates functions in the Product Image implementation, keep each function's **cognitive complexity at or below the recommended threshold of 15**.

2. Return-statement limit

Functions introduced or refactored in this phase should contain **no more than 3 return statements**.

Treat this as a project quality rule.

3. Duplicate string literals

Do not repeatedly hard-code the same meaningful string literals.

Use centralized constants/enums/value objects when the same literal has semantic significance.

Examples:

```php
private const DEFAULT_SORT_ORDER = 0;
private const DEFAULT_IS_PRIMARY = false;
```

More importantly, for repeated domain/storage identifiers, use the appropriate centralized constant rather than repeating:

```text
product_images
product_id
product_variant_id
file_path
alt_text
sort_order
is_primary
```

through unrelated code when a project-level constant abstraction is genuinely beneficial.

However, **do not create a giant "StringConstants" class for every ordinary string in the application**.

Use constants where duplication represents a real shared concept.

The goal is to prevent the same semantic literal from drifting across:

* models;
* validators;
* services;
* tests;
* serializers.

Do not solve duplication by replacing clear ordinary strings with meaningless constants everywhere.


4. Constant naming

When constants are appropriate, use descriptive names.

Prefer:

```php
private const PRIMARY_FLAG = 'is_primary';
private const DEFAULT_SORT_ORDER = 0;
```

over:

```php
private const X = 'is_primary';
private const VALUE_1 = 0;
```

Do not create constants whose names merely repeat the value without explaining the domain meaning.

---

# 28. Definition of Done

A phase is not considered complete merely because code was generated.

A phase is complete when, as applicable:

```text
Implementation exists
+ configuration is correct
+ validation exists
+ relevant tests exist
+ quality checks pass
+ documentation is updated
+ dependencies are satisfied
+ no known critical security issue remains
+ exit condition is demonstrably true
```

For customer-visible work, also verify:

```text
responsive behavior
accessibility basics
loading/error/empty states
real API integration
```

For backend work, also verify:

```text
authorization
validation
transactions where required
logging
error handling
test coverage for critical behavior
```
