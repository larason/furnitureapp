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
| `npm run test` | Run transport, admission and foundation primitive tests |

## Architecture

- `app/(auth)/sign-in` provides Clerk's existing sign-in flow; it is not an admin registration route. `withSignUp={false}` and `transferable={false}` disable the sign-up flow, so an unregistered email or OAuth identity dead-ends instead of entering registration.
- `app/(protected)` is a dynamic server-side admission boundary. It resolves the current Clerk session, obtains a request-scoped bearer token, and calls Laravel `GET /api/v1/me` before rendering protected content.
- `components/` contains small theme-aware UI/state primitives; no business tables or dashboard shell are implemented yet.
- `lib/api/` contains a request-scoped typed Laravel transport. It preserves `/api/v1`, envelopes, pagination, errors, timeouts and private no-store behavior. It does not acquire, persist or log Clerk tokens.
- `lib/auth/server-operational-identity.ts` bridges the current server-side Clerk session to the Laravel transport. `lib/auth/operational-identity.ts` validates the narrow Laravel identity response and fails closed on missing, malformed, inactive, CUSTOMER, or unauthorized identities. A Laravel `401` under a still-active Clerk session yields a recoverable **Session not accepted** state with a sign-out action rather than a redirect to `/sign-in`.
- `theme/` is a thin MUI adapter over the shared `design-tokens.json` export.

Clerk authenticates users only. Laravel remains authoritative for local users, roles, account state, effective permissions and all business rules. A protected route requires a current Clerk session plus Laravel-confirmed `ACTIVE` `STAFF` or `ADMIN` identity. CUSTOMER, suspended, missing, malformed and ambiguous identities are denied. Returned permissions drive presentation gates only; Laravel independently enforces every operational API call.

No bearer token is stored, logged, passed to client components, or shared between requests. The admitted identity's name and role appear next to a **Sign out** control that uses Clerk's `signOut` and returns to `/sign-in`. A later session resolves `/me` again, so account switching cannot reuse prior identity data.

Every state that may be shown to a possibly-authenticated visitor — access denied, account unavailable, session not accepted, and service unavailable — offers a Clerk sign-out action, so an outage or rejection never strands a signed-in user.

## Environment

See `.env.example`. `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` is browser-safe. `CLERK_SECRET_KEY` and `API_BASE_URL` are server-only and belong only in `.env.local` or deployment secret management. Configure `http://localhost:3001` as an exact Clerk allowed origin and Laravel `CLERK_AUTHORIZED_PARTIES` value for local admin testing. The production admin origin, Laravel CORS, CSP and Reverb origins are deployment configuration decisions; no production domain is hardcoded.

## Design and accessibility rules

`frontend/design-system/tokens.css` is the canonical token authority. `design-tokens.json` is its synchronized build-time representation; this application does not copy or redefine tokens. The admin may be denser than the storefront through composition and table primitives, while semantic colors, typography, spacing, focus, responsive breakpoints and accessibility remain shared. Use MUI and `@mui/icons-material`; do not add another component or token system.

Future screens must provide loading, empty, error and permission-safe states, use semantic headings and controls, preserve visible keyboard focus, support zoom/reflow/reduced motion, and never expose raw API errors or private records in metadata.

## Phase exclusions

This phase does not implement a dashboard shell, navigation, requests, enquiries, catalog management, inventory, notifications, Reverb, staff/customer administration, audit logs, orders, payments, checkout, delivery or deployment.
