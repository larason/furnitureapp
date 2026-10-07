# Furniture E-Commerce Platform

A small-to-medium furniture e-commerce platform supporting ready-made (in-stock) sales, made-to-order manufacturing requests, and customer enquiries. Built with an API-first architecture sharing a unified backend across web and mobile frontends.

> **Current Commerce Mode**: Initial Production Mode is **Request Only** (as of 2026-09-29). The system publishes `MADE_TO_ORDER` products and routes purchase intent through the Made-to-Order Request & Enquiry flows. Full Cart → Checkout → Payment purchasing contracts are frozen and tested, awaiting commercial payment-provider onboarding.

---

## Tech Stack

| Tier | Technologies |
| :--- | :--- |
| **Backend API** | Laravel 12 (PHP 8.2+), RESTful `/api/v1` |
| **Authentication & RBAC** | [Clerk](https://clerk.com) (sole identity & verification authority) + Spatie Permission (local roles: `CUSTOMER`, `STAFF`, `ADMIN`) |
| **Database** | MySQL (with fulltext indexing & concurrency guards), SQLite (in-memory for isolated test suites) |
| **Web Frontend** | Next.js (App Router) + TypeScript + Material UI (MUI) |
| **Mobile App** | Flutter + Dart + Material 3 |
| **Admin App** | Next.js + Material UI (MUI) |
| **Design System** | Shared design tokens (`tokens.css`, `design-tokens.json`, `DESIGN.md`) |
| **Storage & Media** | Local/S3 storage for product images and customer request/enquiry attachments |
| **Quality & CI** | PHPUnit 12, PHPStan (Level 5), Laravel Pint, GitHub Actions |

---

## System Architecture

```text
Next.js Website ──────────┐
                          ├── HTTPS REST API (/api/v1) ──► Laravel API ──► MySQL
Flutter Mobile App ───────┤   (Clerk Bearer / Guest Cart)         │
                          │                                        └──► Local / S3 Storage
Admin Dashboard ──────────┘
```

- **Backend Authority**: Laravel owns all business invariants, money calculations, inventory allocations, order state transitions, and validation. Clients never connect directly to MySQL.
- **Unified Authentication**: Single user identity managed via Clerk. Laravel verifies JWT tokens JIT-provisions local customer profiles, and enforces role and permission policies server-side.
- **Product & Fulfillment Models**:
  - `IN_STOCK`: Browse → Add to Cart → Checkout → Payment → Delivery/Pickup → Track Order.
  - `MADE_TO_ORDER`: Browse → Submit Custom Specifications / Attachments → Admin Quote Follow-up.
  - `ENQUIRIES`: General questions, bulk orders, material guidance, or order-related help.
- **Fulfillment Types**: Free `PICKUP` or fee-based `DELIVERY`.

---

## Project Structure & Subdirectories

```
furnitureapp/
├── .agents/                      # Agent capabilities, skills, and configuration
├── .github/                      # CI/CD workflows and automation
│   └── workflows/
│       ├── backend.yml           # Backend lint (Pint), static analysis (PHPStan), and tests
│       └── pullfrog.yml          # Pull request automation
├── backend/                      # Backend application
│   └── laravel/                  # Laravel 12 API service
│       ├── app/                  # Application core code
│       │   ├── Authentication/   # Clerk token verification, gateways, and JIT user provisioning
│       │   ├── Authorization/    # Centralized action and permission policies
│       │   ├── Console/          # Custom Artisan commands (e.g. bootstrap initial admin)
│       │   ├── Exceptions/       # Custom API exceptions and sanitized JSON error renderers
│       │   ├── Http/             # HTTP layer
│       │   │   ├── Controllers/  # Health and /api/v1 resource controllers
│       │   │   └── Middleware/   # Clerk auth, rate limiting, request validation, CORS, headers
│       │   ├── Jobs/             # Background queue jobs (e.g. attachment cleanup tasks)
│       │   ├── Logging/          # Log sanitization and message HMAC fingerprinting
│       │   ├── Models/           # Eloquent domain models, query builders, and validators
│       │   ├── Providers/        # Service providers (AppServiceProvider, etc.)
│       │   ├── Queries/          # Reusable domain queries (e.g. ProductCatalogQuery)
│       │   ├── Services/         # Core business logic services grouped by domain
│       │   └── Support/          # Strongly typed enums, identifiers, and value objects
│       ├── bootstrap/            # Application bootstrap configuration (app.php, providers.php)
│       ├── config/               # Laravel configuration (app, auth, cart, database, services, etc.)
│       ├── database/             # Database schema, seeders, and factories
│       │   ├── factories/        # Model factories for repeatable test data
│       │   ├── migrations/       # 45+ migrations defining tables, checks, and foreign keys
│       │   └── seeders/          # Database seeders (demo data, categories, users)
│       ├── routes/               # Route definitions
│       │   ├── api.php           # Frozen /api/v1 REST routes with throttles & role gates
│       │   ├── console.php       # Scheduled commands (e.g. hourly idempotency pruning)
│       │   └── web.php           # Default web entrypoints
│       ├── tests/                # Test suites
│       │   ├── Concerns/         # Reusable test traits
│       │   ├── Feature/          # 40+ API integration tests (auth, cart, checkout, orders, etc.)
│       │   ├── Integration/      # Concurrency & MySQL-specific integration tests
│       │   ├── Support/          # Disposable database helpers and concurrent worker runners
│       │   └── Unit/             # Domain logic and value object unit tests
│       ├── artisan               # Laravel CLI tool
│       ├── composer.json         # PHP dependencies and automation scripts
│       ├── phpstan.neon          # PHPStan static analysis configuration (Level 5)
│       ├── phpunit.xml           # Test environment configuration (SQLite in-memory)
│       └── pint.json             # Code style rules (Laravel Pint preset)
├── designs/                      # Visual mockups, branding, and UI concept previews
│   ├── appconcept.webp           # Mobile concept preview
│   ├── appmockup.webp            # Mobile mockup screen
│   ├── brandlogo.png             # Official brand logo
│   ├── Desktop-Top-Banner.jpeg   # Promotional banner asset
│   ├── heroimage.png             # Web homepage hero banner
│   ├── homepage.png              # Homepage layout mockup
│   ├── homepage2.png             # Alternative homepage layout
│   ├── mobiledesign.png          # Mobile UI layout design
│   └── productcards.png          # Product card component mockup
├── docs/                         # Authoritative project architecture and API documentation
│   ├── api/                      # Frozen Version 1 API specification
│   │   ├── api-contract.md       # Complete endpoint contract, request/response formats, status codes
│   │   ├── api-conventions.md    # API conventions (REST, pagination, filtering, errors, idempotency)
│   │   ├── api-examples.md       # Verified JSON request and response payloads
│   │   ├── api-resources.md      # Field-level resource schemas and serialization rules
│   │   └── openapi.yaml          # Machine-readable OpenAPI 3.0 specification
│   ├── domain/                   # Business domain invariants
│   │   └── business-rules.md     # Invariants for inventory, cart, checkout, payments, orders
│   ├── clerk-authentication-architecture.md # Clerk integration architecture & verification rules
│   ├── decisions.md              # Architecture Decision Records (ADRs)
│   └── VISION.md                 # Product vision, business model, and requirements
├── frontend/                     # Frontend applications and shared design assets
│   ├── app/                      # Flutter mobile application (Material 3)
│   ├── web/                      # Next.js web application (App Router + MUI)
│   ├── design-system/            # Canonical design system specification and tokens
│   │   ├── preview/              # Visual HTML previews for typography, colors, and spacing
│   │   ├── source/               # Source tokens and contract validation reports
│   │   ├── components.html       # Visual component catalog
│   │   ├── components.manifest.json # Component catalog manifest
│   │   ├── DESIGN.md             # Authoritative style guide (typography, palette, components)
│   │   ├── design-tokens.json    # JSON design tokens for Next.js and Flutter
│   │   ├── manifest.json         # Design system asset manifest
│   │   ├── tailwind-v4.css       # Tailwind CSS token definitions
│   │   ├── tokens.css            # Standard CSS variable tokens
│   │   └── USAGE.md              # Guidelines for applying tokens and preventing ad-hoc values
│   └── AGENTS.md                 # Frontend UI rules and styling constraints
├── phases/                       # Phased development roadmap, specifications, and execution logs
│   ├── group-B-phases.md         # Backend foundation phases
│   ├── group-C-phases.md         # Database schema & entity models
│   ├── group-D-phases.md         # Clerk authentication & RBAC implementation
│   ├── group-E-phases.md         # Catalog & inventory APIs
│   ├── group-F-phases.md         # Cart management & guest session merging
│   ├── group-G-phases.md         # Checkout, fulfillment & delivery fee logic
│   ├── group-J-phases.md         # Made-to-order requests, enquiries & attachments
│   ├── group-K-phases.md         # Admin operations, audit logging & staff workflows
│   ├── logical-data-model-v1.md  # Logical database schema and entity relationships
│   ├── freeze-record.md          # Formal record of the V1 API contract freeze
│   └── phase-*.md                # Detailed requirements and milestone logs
├── AGENTS.md                     # Master engineering guidelines, architectural invariants, and roadmap
├── future.md                     # Future roadmap items, deferred enhancements, and campaign ideas
├── security.md                   # Comprehensive security review resolution and operational hardening
├── skills-lock.json              # Antigravity agent skills lockfile
└── README.md                     # This file
```

---

## Detailed Directory Breakdown

### 1. Backend Service (`backend/laravel`)

The Laravel backend strictly encapsulates all data modeling, security, and business rules:

#### `app/Authentication/`
- **`Clerk/`**: Official SDK gateways (`OfficialClerkSessionGateway`, `OfficialClerkUserGateway`, `OfficialClerkTokenVerifier`).
- **`LocalUserProvisioner.php`**: JIT-provisions local `users` and `customer_profiles` upon valid Clerk JWT presentation.
- **`BootstrapInitialAdmin.php`**: Command to provision or elevate the root administrator safely.

#### `app/Services/`
Domain services encapsulating transactional business logic:
- **`Attachments/`**: Secure upload handling, validation, ephemeral token generation (`UploadCapabilityService`), and cleanup tasks.
- **`AuditLogs/`**: Read and record sensitive administrative and customer read events (`AuditRecorder`, `ListAuditLogs`).
- **`Cart/`**: Cart lifecycle management (`ActiveCartLock`, `AddCartItem`, `CartStockGuard`, `CartStockRevalidator`, `MergeGuestCart`). Enforces 100-item maximum line limits.
- **`Categories/`**: Category management, slug uniqueness, and tree resolution (`CreateCategory`, `UpdateCategory`).
- **`Checkout/`**: Transactional order creation (`CheckoutTransaction`, `OrderTotalsCalculator`, `PickupFulfillmentState`, `DeliveryFulfillmentState`).
- **`Enquiries/`**: General enquiry intake (`CreateEnquiry`), status updates, and operational closure (`CloseEnquiry`).
- **`Inventory/`**: Concurrency-safe inventory reservations (`InventoryAllocator`) and staff stock adjustments (`InventoryAdjustmentService`).
- **`ProductImages/`**: Image upload, MIME/dimension verification, and storage paths.
- **`Products/`**: Product lifecycle (`CreateProduct`, `UpdateProduct`), slug resolution, and request-only publication guards.
- **`Requests/`**: Made-to-order workflow (`CreateFurnitureRequest`, `TransitionFurnitureRequestStatus`, `RequestStatusMachine`).

#### `app/Http/Middleware/`
- **`AuthenticateClerk.php` / `AuthenticateClerkIfPresent.php`**: Enforces or checks optional Clerk bearer tokens.
- **`EnforceApiRequestLimits.php`**: Layered rate limiting applying early IP-based pre-auth quotas and per-user limits.
- **`CustomerCartAccess.php`**: Guards customer-only cart endpoints, preventing staff/admin access.
- **`CustomerSubmissionAccess.php`**: Validates request/enquiry submissions.
- **`EnsureActiveAccount.php`**: Rejects suspended or inactive accounts.
- **`EnsureCheckoutEnabled.php`**: Enforces the production commerce mode (Request Only vs full checkout).
- **`ValidateGuestCartMutation.php`**: Protects anonymous cookie mutations against CSRF using exact `Origin` validation.
- **`ValidateJsonBody.php`**: Enforces valid JSON payloads and size caps (6 MiB ceiling).
- **`AddSecurityHeaders.php`**: Injects CSP, HSTS, frame options, and permissions headers.

#### `app/Models/`
Key Eloquent entities:
- **Identity & Accounts**: `User`, `CustomerProfile`, `StaffProfile`.
- **Catalog & Inventory**: `Category`, `Product`, `ProductVariant`, `ProductImage`, `ProductStock`.
- **Cart & Commerce**: `Cart`, `CartItem`, `Order`, `OrderItem`, `OrderStatusHistory`, `OrderItemInventoryAllocation`.
- **Payments & Fulfillment**: `Payment`, `PaymentWebhookEvent`, `Delivery`.
- **Customer Interactions**: `FurnitureRequest`, `Enquiry`, `Attachment`, `AttachmentUploadCapability`, `AttachmentCleanupTask`, `Notification`.
- **Security & Auditing**: `AuditEvent`, `IdempotencyKey`.

#### `database/migrations/`
Contains over 45 migrations governing schema integrity:
- Enforced check constraints on monetary values (`price_cents >= 0`, `currency = 'TZS'`).
- Unique default variant indexes per product.
- MySQL fulltext indexes on products (`name`, `description`).
- Soft deletes on catalog models.
- Foreign key cascading and deletion protection (`restrict_cart_user_deletion`).

---

### 2. Frontend Applications (`frontend/`)

- **`frontend/web/`**: Next.js App Router application providing customer browsing, made-to-order forms, general enquiry flows, and admin portal.
- **`frontend/app/`**: Flutter mobile application designed with Material 3 components.
- **`frontend/design-system/`**:
  - `DESIGN.md`: The definitive design contract (monochrome color scheme, Futura Condensed typography, pill buttons, flat card layouts).
  - `tokens.css`: Authoritative CSS variables for web.
  - `design-tokens.json`: Structured token definitions for cross-platform imports.
  - `preview/`: Visual HTML previews to verify spacing, typography, and color tokens.

---

### 3. Documentation & Governance (`docs/`, `phases/`, `security.md`)

- **`docs/api/`**: The frozen Version 1 API contract (`api-contract.md`, `api-conventions.md`, `api-resources.md`, `openapi.yaml`). No observable endpoint or schema changes may occur without formal change approval.
- **`docs/clerk-authentication-architecture.md`**: Specification of the single authentication authority model with Clerk.
- **`docs/domain/business-rules.md`**: Authoritative domain rules for catalog availability, stock reservation, money calculations, and order workflows.
- **`security.md`**: Full documentation of resolved security reviews (pre-auth quotas, transient anonymous carts, log scrubbing, CORS/CSP hardening).
- **`phases/`**: Detailed phase tracking records from Group A through Group K.

---

## Developer Workflows & Commands

### Backend Quality Commands (Laravel)
Run from `backend/laravel`:

```bash
# Code formatting (Laravel Pint)
composer format           # Automatically fix formatting issues
composer format:check     # Verify formatting without altering files

# Static analysis (PHPStan Level 5)
composer analyse          # Static analysis over app, config, database, routes

# Automated testing
composer test             # Run entire test suite (SQLite in-memory)
php artisan test --filter=HealthEndpointTest  # Run a specific test class
```

### Concurrency & Integration Tests
For database-specific and concurrency verification:
```bash
# Concurrency tests use the disposable database furnitureapp_test_disposable
php artisan test tests/Integration/
```

### Design System Tools
To inspect the design system tokens and component catalog:
```bash
pnpm tools-dev run web
```

### Run dev server
```
npm run dev
```

with fixtures
```
HOMEPAGE_DATA_SOURCE=fixtures npm run dev
```

# Troubleshooting

### images not appearing after replacement

The old image usually appears for one of these reasons:

1. Next.js Image Optimizer cache
next/image caches optimized images under:
frontend/web/.next/cache/images/
Restarting the dev server does not necessarily clear that cache.
Clear it with:
```
rm -rf frontend/web/.next/cache/images
```
Then restart npm run dev.

2. Browser cache
Use a hard refresh:
- Chrome/Linux: Ctrl + Shift + R
- Or open DevTools and select Disable cache.

3. Fixtures are not enabled
The local fixture images only appear when running:
HOMEPAGE_DATA_SOURCE=fixtures npm run dev

4. API mode is showing API media
Without HOMEPAGE_DATA_SOURCE=fixtures, products and categories come from Laravel, so changing fixtures.ts will not affect those images.
The browser ultimately requests an optimized URL like:
/_next/image?url=%2Ffurnitures%2Ffixtures%2Fproducts%2Fsofa.jpg&w=640&q=75
Changing the filename in fixtures.ts should invalidate that URL automatically. If it does not, clear .next/cache/images and perform a hard refresh.

5. Fixture prices are stored in minor units, not whole TZS.
- amount: 10000000 represents TZS 100,000.00
- amount: 24500000 represents TZS 245,000.00
formatMoney() divides the integer by 100 before display, per the frozen API contract: 1 TZS = 100 minor units.
So the extra two zeros in fixtures.ts are intentional. If you want a displayed price of TZS 1,000,000.00, the fixture must be amount: 100000000

6. MADE_TO_ORDER From price prefix: CAT-002 defines its price as an informational starting estimate. The prefix accurately communicates that contract and is permitted by Phase 14.4’s price-copy rule. e.g From TZS 245,000.00