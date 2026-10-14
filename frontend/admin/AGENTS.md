# Admin Frontend Rules

- This is an independent Next.js application. Do not add admin routes to `frontend/web`.
- Use Next.js 16 App Router conventions and keep server components as the default.
- `frontend/design-system/tokens.css` is the sole token authority. Use the synchronized JSON only for deterministic MUI mapping; never create a competing token file.
- The admin may use denser operational composition than the storefront, but semantic colors, typography, spacing, breakpoints, focus treatment and accessibility behavior remain shared.
- Laravel is authoritative for roles, active account state, exact permissions, ownership and business state. Never trust Clerk metadata or a client `isAdmin` flag.
- Do not expose operational data until the Phase 19.3 Laravel `/me` admission boundary is implemented.
- Use the request-scoped API transport in `lib/api/client.ts`; never persist, log, serialize or globally retain bearer tokens.
- Keep private API calls `no-store`; preserve the frozen `/api/v1` envelopes, pagination and error vocabulary.
- Use MUI and `@mui/icons-material`; do not add Tailwind, another component library, or another authentication provider.
- Every future screen needs accessible loading, empty, error, permission, keyboard, responsive and reduced-motion behavior.
- Run `npm run lint`, `npm run typecheck`, `npm run test`, `npm run build`, and `git diff --check` before completion.
