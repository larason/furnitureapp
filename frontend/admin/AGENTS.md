# Admin Frontend Rules

- This is an independent Next.js application. Do not add admin routes to `frontend/web`.
- Use Next.js 16 App Router conventions and keep server components as the default.
- `frontend/design-system/tokens.css` is the sole token authority. Use the synchronized JSON only for deterministic MUI mapping; never create a competing token file.
- The admin may use denser operational composition than the storefront, but semantic colors, typography, spacing, breakpoints, focus treatment and accessibility behavior remain shared.
- Laravel is authoritative for roles, active account state, exact permissions, ownership and business state. Never trust Clerk metadata or a client `isAdmin` flag.
- Clerk authentication is not admin authorization. Protected routes must resolve `/api/v1/me` server-side and admit only `ACTIVE` local `STAFF`/`ADMIN` identities with a valid, deterministic effective-permission list. CUSTOMER, inactive, missing, malformed and ambiguous identities fail closed.
- The `permissions` array is a presentation hint from Laravel only. Never construct a frontend permission catalog, grant a permission locally, or bypass Laravel authorization.
- Use the request-scoped API transport in `lib/api/client.ts`; never persist, log, serialize or globally retain bearer tokens.
- The admin sign-in surface is sign-in only: keep Clerk's registration footer hidden, `signUpUrl` pinned, and `withSignUp={false}` with `transferable={false}` on `<SignIn />`. Hiding a link is not enough; an unregistered email or OAuth identity must dead-end with "couldn't find your account" rather than entering registration. Admin accounts are provisioned by the operator `admin:bootstrap` command, never by a browser flow.
- Every state that can be shown to a possibly-authenticated visitor — denied, account unavailable, session not accepted, and service unavailable — must offer a Clerk sign-out action. Never strand a signed-in user, including during a Laravel outage.
- Never redirect a still-authenticated Clerk session to `/sign-in`. `SignIn` cannot render for an already signed-in user, so a Laravel `401` under a live session must surface a recoverable state that offers a Clerk sign-out instead of a redirect loop.
- Keep private API calls `no-store`; preserve the frozen `/api/v1` envelopes, pagination and error vocabulary.
- Use MUI and `@mui/icons-material`; do not add Tailwind, another component library, or another authentication provider.
- Every future screen needs accessible loading, empty, error, permission, keyboard, responsive and reduced-motion behavior.
- Run `npm run lint`, `npm run typecheck`, `npm run test`, `npm run build`, and `git diff --check` before completion.
