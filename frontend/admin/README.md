# SL Furnitures Admin Dashboard

Independent Next.js 16 + React + TypeScript + MUI foundation for the SL Furnitures operational dashboard. The application is intentionally separate from `frontend/web` and is request-only until later phases enable additional business workflows.

## Local setup

```bash
cp .env.example .env.local
npm install
npm run dev
```

Open `http://localhost:3001`. The port is explicit so the admin and customer applications cannot accidentally share an origin. Without `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY`, the application renders a safe configuration state and never assumes an authenticated administrator.

## Scripts

| Script | Purpose |
| --- | --- |
| `npm run dev` | Start the admin app on port 3001 |
| `npm run build` | Create the production build |
| `npm run start` | Start the production build on port 3001 |
| `npm run lint` | Run Next.js ESLint rules |
| `npm run typecheck` | Run strict TypeScript checking |
| `npm run test` | Run transport and foundation primitive tests |

## Architecture

- `app/` contains the independent App Router and foundation preview only.
- `components/` contains small theme-aware UI/state primitives; no business tables or dashboard shell are implemented yet.
- `lib/api/` contains a request-scoped typed Laravel transport. It preserves `/api/v1`, envelopes, pagination, errors, timeouts and private no-store behavior. It does not acquire, persist or log Clerk tokens.
- `theme/` is a thin MUI adapter over the shared `design-tokens.json` export.

Laravel remains authoritative for local users, roles, account state, permissions and all business rules. Clerk is the existing identity provider. Full STAFF/ADMIN admission and `/me` authorization are Phase 19.3 responsibilities; this foundation does not admit users to private operational data.

## Environment

See `.env.example`. `API_BASE_URL` is server-only and must be a bare Laravel HTTP(S) origin. The production admin origin, Clerk authorized parties, Laravel CORS, CSP and Reverb origins are deployment configuration decisions; no production domain is hardcoded.

## Design and accessibility rules

`frontend/design-system/tokens.css` is the canonical token authority. `design-tokens.json` is its synchronized build-time representation; this application does not copy or redefine tokens. The admin may be denser than the storefront through composition and table primitives, while semantic colors, typography, spacing, focus, responsive breakpoints and accessibility remain shared. Use MUI and `@mui/icons-material`; do not add another component or token system.

Future screens must provide loading, empty, error and permission-safe states, use semantic headings and controls, preserve visible keyboard focus, support zoom/reflow/reduced motion, and never expose raw API errors or private records in metadata.

## Phase exclusions

This phase does not implement authentication admission, protected dashboard navigation, requests, enquiries, catalog management, inventory, notifications, Reverb, staff/customer administration, audit logs, orders, payments, checkout, delivery or deployment.
