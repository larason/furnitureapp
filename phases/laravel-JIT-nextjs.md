# SL Furnitures — Eager Laravel JIT Provisioning After Clerk Authentication

## Objective

Improve the existing Next.js + Clerk authentication integration so that a customer is provisioned in Laravel shortly after Clerk establishes an active, verified session.

Currently, Laravel JIT provisioning happens only when an authenticated request reaches the backend. A successful Clerk registration does not necessarily create a local Laravel user.

Implement a lightweight, reliable authenticated session bootstrap that calls the existing `GET /api/v1/me` endpoint.

Do not introduce a new authentication system or provisioning endpoint.

## 1. Mandatory audit

Before modifying code, inspect:

- Root `AGENTS.md`.
- Existing Clerk authentication integration in `frontend/web`.
- Clerk providers and session lifecycle.
- Existing `GET /api/v1/me` client.
- Existing authenticated browser API client.
- `backend/laravel/app/Authentication/LocalUserProvisioner.php`.
- Existing Clerk middleware.
- Existing local-user role and profile creation logic.
- Clerk email verification and pending-security-task requirements.
- CORS and authorized-party configuration.
- Existing authentication tests.
- Frozen API authentication contract.

Identify the smallest appropriate integration point.

Prefer one small client-side session bootstrap component within the existing Clerk provider boundary rather than adding logic to individual pages.

## 2. Required behavior

When Clerk reports that:

1. Authentication initialization has completed.
2. A real session is active.
3. The session is suitable for authenticated API access.
4. Required verification/security tasks have been completed.

Then:

1. Obtain the current Clerk session token using the project's existing supported Clerk SDK API.
2. Call `GET /api/v1/me`.
3. Send the token using `Authorization: Bearer`.
4. Let Laravel verify the token and perform existing JIT provisioning.
5. Treat a successful API response as confirmation that the local Laravel user exists.
6. Do not create a separate frontend-owned user record.

Do not attempt provisioning with an incomplete signup, unverified email, or pending required security tasks.

Laravel remains the final authority for all such checks.

## 3. Session lifecycle

Implement once-per-session successful bootstrap behavior.

Cover:

- New verified signup.
- Existing customer sign-in.
- Browser refresh with an active session.
- Returning customer whose Laravel user does not yet exist.
- User switching.
- Sign-out followed by sign-in.
- Clerk session restoration.
- Token acquisition temporarily unavailable.
- Clerk SDK still loading.

Do not permanently suppress retries after an unsuccessful bootstrap.

Do not call `/me` on every client navigation.

Prevent duplicate simultaneous calls caused by React Strict Mode or repeated component mounts.

Use an appropriate in-flight deduplication mechanism without storing sensitive tokens persistently.

## 4. API behavior

Use the existing authenticated API transport and the frozen `GET /api/v1/me` contract.

Do not:

- Add a new public API endpoint.
- Call Laravel directly from Clerk webhooks as part of this change.
- Send email/password credentials to Laravel.
- Trust a frontend-supplied Clerk user ID.
- Create a separate Laravel authentication token.
- Send roles in the request body.
- Auto-upgrade CUSTOMER to STAFF or ADMIN.
- Modify the frozen response schema.

Ensure the API client obtains a valid Clerk bearer token for the current session rather than reusing a stale cached token.

## 5. Provisioning idempotency

Verify existing Laravel behavior:

- A verified new Clerk identity provisions exactly one local user.
- Default new role is CUSTOMER.
- Customer profile is created according to the existing implementation.
- A repeat request reuses the same user.
- Existing STAFF/ADMIN roles are preserved.
- Existing account active/inactive state is respected.
- Concurrent bootstrap calls cannot create duplicate local users.
- Email is not used to link unrelated Clerk identities.
- Unverified or invalid Clerk tokens cannot provision accounts.

Fix only genuine regressions within this scope.

Do not refactor the complete authentication system.

## 6. Failure handling

Differentiate:

**Temporary failures**

- API unavailable.
- Network disconnected.
- Request timeout.
- Temporary backend failure.

Handle with bounded retries and appropriate backoff. Avoid excessive requests and infinite retry loops.

**Authentication failures**

- Invalid JWT.
- Expired session.
- Pending Clerk security task.
- Unverified email.

Do not repeatedly retry the same invalid identity. Wait for an appropriate Clerk session/verification change or valid new token.

**Authorization failures**

- Inactive local account.
- Forbidden access.

Do not reactivate or silently replace such accounts. Preserve Laravel's server-authoritative decisions.

A failed `/me` bootstrap must not:

- Sign the user out of Clerk automatically.
- Create an authentication redirect loop.
- Block anonymous browsing.
- Block public catalogue pages.
- Block anonymous request/enquiry submission.
- Report provisioning success.

If authenticated account functionality depends on successful bootstrap, display an appropriate recoverable state within that functionality.

## 7. UI and architecture

Do not add a new registration step, onboarding screen, modal, or forced redirect merely to synchronize the local account.

The bootstrap should be a small background operation.

Reuse the existing Next.js App Router and Clerk provider architecture.

Keep SEO-critical public pages server-renderable.

Avoid moving the entire website into a client-only component.

Do not introduce a new global state management library.

No changes to the shared SL Furnitures design system are required.

## 8. Security

- Never expose the Clerk bearer token in logs.
- Never put tokens into URLs.
- Never store tokens in localStorage.
- Never log complete `/me` responses containing private account data.
- Preserve Clerk issuer, audience, authorized-party, and signature verification.
- Preserve existing Laravel CORS rules.
- Preserve email-verification requirements.
- Preserve inactive-account restrictions.
- Preserve existing RBAC.
- Never allow client-side role assignment.

Account synchronization must not change the authenticated user's privileges.

## 9. Tests

Add or update tests covering:

1. Clerk loading → no request.
2. Signed out → no request.
3. Active verified session → `/me` called.
4. Incomplete signup → no provisioning request.
5. Pending security task → no provisioning request.
6. Re-render → no redundant call.
7. Concurrent mounts → no duplicate in-flight bootstrap.
8. Successful bootstrap → no repeat during same session.
9. Temporary failure → bounded retry.
10. Authentication failure → safe handling.
11. Sign-out → reset bootstrap state.
12. User switch → bootstrap new authenticated identity.
13. Refresh with active session → local-user synchronization.
14. Existing STAFF/ADMIN identity → no role reset.
15. New Clerk customer → local CUSTOMER provisioning.
16. Existing Laravel customer → existing record reused.
17. Invalid/unverified identity → no local row.
18. Public pages remain accessible when Laravel is offline.

Use deterministic tests with mocked Clerk session states where appropriate.

Do not weaken existing authentication tests.

## 10. Local smoke test

Using the existing development Clerk instance and isolated local Laravel database:

1. Sign up a new verified Clerk customer.
2. Confirm Laravel has not previously created a row for that Clerk identity.
3. Allow the website session bootstrap to execute.
4. Verify `GET /api/v1/me` succeeds.
5. Verify exactly one local user and appropriate customer profile exist.
6. Refresh the website.
7. Verify no duplicate user is created.
8. Sign out and sign in again.
9. Verify the same local user is reused.
10. Test a user whose Clerk session already exists but whose Laravel projection is missing.
11. Verify bootstrap provisions that user.
12. Test temporarily unavailable Laravel and safe recovery.

Do not remove existing users or reset databases to prepare the test.

Use a dedicated disposable test identity if necessary.

## 11. Documentation and verification

Document the change in the appropriate authentication completion record or a focused implementation note.

Record:

- Integration point.
- Session readiness criteria.
- Deduplication design.
- Retry behavior.
- API contract preservation.
- New test coverage.
- Actual smoke-test evidence.
- Remaining limitations.

Run the affected Next.js tests, typecheck, lint, and build checks, plus relevant Laravel authentication tests.

Do not change Firebase, FCM, Resend, Reverb, Coolify, or request/enquiry business-event notification behavior.

## 12. Definition of Done

The task passes when:

- An eligible Clerk session triggers an authenticated `GET /api/v1/me` automatically.
- Successful bootstrap ensures the existing local Laravel user is available.
- Signup no longer depends on a later form submission to trigger JIT provisioning.
- Existing Clerk users with missing local projections are covered.
- Duplicate calls do not create duplicate users.
- Clerk remains the only authentication authority.
- Laravel remains authoritative for local authorization and role assignment.
- Failure handling is safe and recoverable.
- Public website behavior is unchanged.
- Existing tests remain green.
- No frozen API contract change is introduced.

Implement this as a focused authentication reliability improvement. Do not expand into unrelated phases.