# Furniture E-Commerce Platform

A small-to-medium furniture e-commerce platform supporting ready-made (in-stock) sales, made-to-order manufacturing requests, and customer enquiries. Built with an API-first architecture sharing a unified backend across web and mobile frontends.

---

## Tech Stack

- **Backend API**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL (SQLite for test isolation)
- **Web Frontend**: Next.js (App Router) + TypeScript + Material UI (MUI)
- **Mobile App**: Flutter + Dart + Material 3
- **Admin App**: Next.js + Material UI (MUI)
- **Design System**: Shared tokens (JSON/CSS) enforcing consistent typography, palette, and spacing
- **Media & Video**: Object storage / CDN, Video.js for rich media

---

## Architecture Overview

```text
Next.js Website ──┐
                  ├── HTTPS API (/api/v1) ── Laravel API ── MySQL
Flutter App ──────┤
                  │
Admin App ────────┘
```

- **Backend Authority**: The Laravel API owns all business rules, calculations, inventory reservations, order state transitions, and validation. Clients never connect directly to MySQL.
- **Shared Identity**: Unified authentication and role-based access control (`CUSTOMER`, `STAFF`, `ADMIN`) across all client platforms.
- **Dual Product Model**:
  - `IN_STOCK`: Direct purchase flow (Browse → Cart → Checkout → Payment → Fulfilment → Order Tracking).
  - `MADE_TO_ORDER`: Request and quotation workflow (Browse → Request Quote → Admin Follow-up).

---

## Project Structure & File Guide

```
furnitureapp/
├── .github/                      # CI/CD workflows
│   └── workflows/
│       ├── backend.yml           # Backend lint (Pint), analysis (PHPStan), and test suite
│       └── pullfrog.yml          # Pull request automation
├── backend/                      # Backend application
│   └── laravel/                  # Laravel API service
│       ├── app/                  # Application core code
│       │   ├── Authorization/    # Role and permission policy enforcement
│       │   ├── Exceptions/       # Custom API exceptions and error renderers
│       │   ├── Http/             # Controllers, middleware, and request handling
│       │   │   ├── Controllers/  # Base, health, and /api/v1 resource controllers
│       │   │   └── Middleware/   # JSON validation, request ID, role gatekeepers
│       │   ├── Models/           # Eloquent domain models
│       │   ├── Providers/        # Service providers
│       │   ├── Services/         # Encapsulated domain business services
│       │   └── Support/          # Enums, value objects, error codes, reference generators
│       ├── bootstrap/            # Application bootstrap configuration
│       ├── config/               # Laravel configuration files (auth, database, cart, etc.)
│       ├── database/             # Migrations, model factories, and database seeders
│       ├── routes/               # API, web, and console route definitions
│       ├── tests/                # Unit and Feature integration test suites
│       ├── artisan               # Laravel CLI tool
│       ├── composer.json         # PHP dependencies and script definitions
│       ├── phpstan.neon          # PHPStan/Larastan static analysis configuration (level 5)
│       ├── phpunit.xml           # Test runner configuration
│       └── pint.json             # Code formatting rules (Laravel Pint)
├── designs/                      # UI mockups, visual assets, and design concepts
│   ├── appconcept.webp           # Mobile concept preview
│   ├── appmockup.webp            # Mobile mockup screen
│   ├── brandlogo.png             # Official brand logo
│   ├── Desktop-Top-Banner.jpeg   # Promotional banner asset
│   ├── heroimage.png             # Homepage hero banner
│   ├── homepage.png              # Web homepage design mockup
│   ├── homepage2.png             # Secondary web homepage concept
│   ├── mobiledesign.png          # Mobile UI layout design
│   └── productcards.png          # Product card component mockup
├── docs/                         # Authoritative project and API documentation
│   ├── api/                      # Frozen Version 1 API specifications
│   │   ├── api-contract.md       # API contract: endpoints, schemas, authorization rules
│   │   ├── api-conventions.md    # Error formats, pagination, filtering, naming conventions
│   │   ├── api-examples.md       # Sample JSON requests and responses
│   │   ├── api-resources.md      # Field-level resource definitions & serialization rules
│   │   └── openapi.yaml          # Machine-readable OpenAPI 3.0 specification
│   ├── domain/                   # Core business rules and domain model
│   │   └── business-rules.md     # Invariants for inventory, checkout, orders, payments
│   ├── decisions.md              # Architecture Decision Records (ADRs)
│   └── VISION.md                 # Product vision, business model, and requirements
├── frontend/                     # Client-side applications and shared design assets
│   ├── app/                      # Flutter mobile application (Material 3)
│   ├── web/                      # Next.js web application (MUI)
│   ├── design-system/            # Authoritative design tokens and specifications
│   │   ├── preview/              # Interactive HTML token previews (colors, spacing, type)
│   │   ├── source/               # Raw design tokens and validation reports
│   │   ├── components.html       # Visual component catalog
│   │   ├── components.manifest.json # Component catalog manifest
│   │   ├── DESIGN.md             # Design system specifications and style rules
│   │   ├── design-tokens.json    # JSON design tokens for cross-platform consumption
│   │   ├── manifest.json         # Design system asset manifest
│   │   ├── tailwind-v4.css       # Tailwind CSS token definitions
│   │   ├── tokens.css            # Standard CSS variable tokens
│   │   └── USAGE.md              # Token and component usage guidelines
│   └── AGENTS.md                 # UI engineering rules and constraints
├── phases/                       # Phased roadmap deliverables and planning documents
│   ├── api-scope-v1.md           # API scope boundaries
│   ├── canonical-api-vocabulary.md # Standardized naming conventions and vocabulary
│   ├── domain-invariants.md      # Enforced domain validation rules
│   ├── freeze-record.md          # API contract freeze milestone record
│   ├── logical-data-model-v1.md  # Logical database schema and entity relationships
│   └── phase-*.md                # Individual phase planning and execution logs
├── AGENTS.md                     # Central engineering guidelines, constraints, and roadmap
├── future.md                     # Roadmap backlog, deferred enhancements, and marketing ideas
└── README.md                     # Project overview and directory documentation
```

---

## Detailed Directory & File Breakdown

### 1. Backend (`/backend/laravel`)
Contains the Laravel REST API backend powering both the web and mobile frontends:

- **`app/Authorization/`**: Centralized policy enforcement (`Authorization.php`) ensuring actors only execute permitted actions.
- **`app/Exceptions/Api/`**: Consistent JSON error formatting conforming to the API contract (`ApiException.php`, `ApiExceptionRenderer.php`).
- **`app/Http/Controllers/Api/V1/`**: Resource endpoints for authentication, catalog, cart, checkout, orders, inventory, payments, enquiries, and administration.
- **`app/Http/Middleware/`**: Handles JSON request validation (`ValidateJsonBody.php`), correlation IDs (`AssignRequestId.php`), operational role checks (`OperationalAccess.php`), and administrative authorization (`AdministrativeAccess.php`).
- **`app/Models/`**: Core Eloquent entities:
  - `User`, `CustomerProfile`, `StaffProfile`: User identity and profiles.
  - `Category`, `Product`, `ProductVariant`, `ProductImage`, `ProductStock`: Catalog and inventory.
  - `Cart`, `CartItem`: Shopping cart state.
  - `Order`, `OrderItem`, `OrderStatusHistory`: Order lifecycle and tracking.
  - `Payment`, `PaymentWebhookEvent`: Payment processing and idempotent webhook logs.
  - `Delivery`: Fulfilment details (pickup or delivery).
  - `FurnitureRequest`: Custom made-to-order requests.
  - `Enquiry`: General customer enquiries.
  - `Notification`: In-app and system notifications.
- **`app/Support/`**: Strongly typed PHP enums and utilities (`OrderStatus`, `PaymentStatus`, `FulfillmentType`, `ApiErrorCode`, `ReferenceGenerator`, etc.).
- **`database/migrations/`**: Authoritative migrations defining relational tables, foreign key constraints, and integrity check constraints.
- **`tests/`**: Feature tests (`tests/Feature/`) verifying API endpoints, auth, and error contracts; Unit tests (`tests/Unit/`) verifying domain logic.

### 2. Frontend (`/frontend`)
Contains customer-facing and internal frontends along with shared design tokens:

- **`frontend/web/`**: Next.js (App Router) + Material UI (MUI) client website and administration panel.
- **`frontend/app/`**: Flutter (Material 3) cross-platform mobile application.
- **`frontend/design-system/`**: Single source of truth for visual tokens:
  - `DESIGN.md`: Specification document for typography (Futura Condensed display, sans-serif body), monochrome/neutral color palette, pill buttons, and flat cards.
  - `tokens.css` & `design-tokens.json`: Compiled CSS variables and JSON tokens consumable by MUI and Flutter.
  - `USAGE.md`: Instructions for consuming tokens and avoiding arbitrary styling values.
- **`frontend/AGENTS.md`**: Frontend-specific development rules enforcing design token compliance and component reuse.

### 3. Documentation (`/docs`)
Authoritative documentation governing system design and behavior:

- **`docs/api/`**:
  - `api-contract.md`: Comprehensive API contract defining all `/api/v1` routes, status codes, query parameters, request bodies, and role access.
  - `api-conventions.md`: Standards for error responses, pagination, date-time formats (ISO 8601 UTC), sorting, and idempotency.
  - `api-resources.md`: Exact serialization schemas for every exposed API resource.
  - `api-examples.md`: Verified request and response JSON payloads.
  - `openapi.yaml`: OpenAPI 3.0 specification for API tooling and client generation.
- **`docs/domain/business-rules.md`**: Domain logic constraints including cart calculations, inventory reservation, order state transitions, and payment webhooks.
- **`docs/decisions.md`**: Architecture Decision Records (ADRs) tracking architectural and schema choices.
- **`docs/VISION.md`**: Foundational vision and business goals.

### 4. Roadmap & Development Records (`/phases`)
Detailed documentation of the phased implementation approach (Phase Group A: API Contract, Phase Group B: Design & Quality Baseline, Phase Group C: Data Modeling & Schema Implementation).

### 5. Root Meta & Planning Files
- **`AGENTS.md`**: Master system guidelines, rules of engagement, architectural invariants, and the dependency-first development roadmap.
- **`future.md`**: Future feature wishlist, promotional campaign concepts, and deferred improvements.

---

## Development Workflows

### Backend Quality Commands (Laravel)
Run these commands inside `backend/laravel`:

```bash
# Code formatting check and fix
composer format
composer format:check

# Static analysis (PHPStan Level 5)
composer analyse

# Run test suite
composer test

# Run a specific feature test
php artisan test tests/Feature/ApiErrorHandlingTest.php
```

### Design System Preview
To inspect or serve design system assets:

```bash
pnpm tools-dev run web
```

# Delivery fee

For delivery orders, Checkout creates a pending order and shows the
provisional subtotal while the delivery fee is pending. After staff or admin
sets the location-based fee, Checkout shows the final total. Payment can
proceed only after the fee is finalized.
