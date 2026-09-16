# Phase 4.4 — Password Recovery / Security with Clerk

## Purpose

Implement and document the application's password-recovery and account-security boundary with **Clerk as the credential/security authority**.

This phase must ensure that the application does **not** reintroduce Laravel-owned password-reset infrastructure now that Clerk owns authentication.

The target architecture is:

```text
Customer
    ↓
Clerk recovery / security flow
    ↓
identity verification
    ↓
password reset / security action
    ↓
Clerk session state
    ↓
fully authenticated Clerk session
    ↓
Laravel protected API
```

Laravel remains responsible for:

```text
local User mapping
application account state
RBAC
ownership
authorization
business operations
```

Laravel does **not** become the password-reset authority.

---

# 1. Dependencies

Required:

* Phase 4.1 complete;
* Phase 4.2 complete;
* Phase 4.3 complete;
* Clerk is authentication/session authority;
* local `users.clerk_user_id` mapping exists;
* customer JIT provisioning works;
* authenticated Clerk sessions resolve to local Laravel Users;
* Laravel does not store or validate customer passwords;
* Laravel does not issue parallel customer auth tokens;
* authentication error mapping already exists.

Do not start if password-based Laravel login is still authoritative.

---

# 2. Scope

Implement/review:

* password-recovery ownership;
* Clerk forgot-password/reset flow architecture;
* compromised-password handling;
* password-change security boundary;
* session behavior after password/security changes;
* session revocation semantics;
* incomplete/pending Clerk session behavior;
* sensitive-action reverification strategy;
* enumeration protection;
* account-security logging;
* Laravel API contract cleanup;
* tests for Laravel-side security boundaries.

Do not build frontend UI yet.

Do not implement full email-verification policy yet.

Do not implement customer profile editing yet.

---

# 3. Authoritative Sources

Read before implementation:

1. `AGENTS.md`
2. Clerk ADR from Phase 4.1
3. Phase 4.2 implementation
4. Phase 4.3 authentication implementation
5. `docs/api/api-contract.md`
6. `docs/api/api-resources.md`
7. `docs/api/api-conventions.md`
8. `docs/api/openapi.yaml`
9. `docs/domain/business-rules.md`
10. `docs/decisions.md`
11. current Clerk security/recovery documentation.

If Clerk MCP/Skills are installed, use them.

Do not assume older Clerk password-reset flows still match the current API.

---

# 4. Core Ownership Rule

Record and enforce:

```text
Clerk owns:
- password creation
- password validation
- password reset
- compromised-password detection
- security verification
- MFA factors
- credential recovery
- session revocation

Laravel owns:
- application user
- application account status
- role
- permission
- commerce authorization
```

Never create a competing Laravel password-recovery system.

---

# 5. Retire Laravel Reset Infrastructure

Review the current Laravel project for legacy/custom password-reset artifacts.

Search for:

```text
Password::reset
password_reset_tokens
ResetPassword
ForgotPassword
password broker
temporary reset token
custom password reset controllers
AUTH password reset endpoints
```

If these were introduced before Clerk adoption and are no longer required:

* remove/deprecate them according to the Phase 4.1 contract change;
* do not leave them available as a second recovery path;
* do not keep unused security-sensitive endpoints exposed.

Do not edit historical migrations casually.

If a legacy password-reset table exists only because Laravel scaffolded it but is no longer used, document its disposition and remove it through a new migration only if appropriate.

---

# 6. No Laravel Password Reset Tokens

Laravel must not generate:

```text
reset token
password reset secret
password recovery OTP
password recovery link
```

for Clerk customers.

Never persist:

```text
password_reset_token
reset_secret
reset_code
```

for this authentication system.

Clerk owns those flows.

---

# 7. Forgot-Password Flow

The customer recovery flow should conceptually be:

```text
customer selects forgot password
        ↓
Clerk recovery flow
        ↓
Clerk verifies configured recovery factor
        ↓
customer sets new password
        ↓
Clerk updates credential
        ↓
Clerk session reaches appropriate state
        ↓
customer resumes application
```

Laravel should not receive:

```text
old password
new password
recovery OTP
recovery token
```

as part of this process.

---

# 8. Existing AUTH Recovery Endpoints

Review the Phase 4.1 endpoint migration matrix.

If endpoints such as:

```text
POST /api/v1/auth/password/forgot
POST /api/v1/auth/password/reset
```

were retired because Clerk now owns recovery:

do not implement them.

Do not leave them in OpenAPI as active routes.

Retired AUTH IDs remain retired.

Never recycle them.

---

# 9. Password Change

Likewise review any previous application endpoint such as:

```text
POST /api/v1/auth/change-password
```

If Clerk owns password changes:

* do not accept plaintext current/new passwords through Laravel;
* do not perform Laravel password hashing;
* do not update `users.password`.

Future Next.js/Flutter account UI should invoke the appropriate Clerk security flow.

Laravel may expose application profile operations separately, but not credential mutation.

---

# 10. Step-Up / Reverification

Sensitive security operations should require recent authentication/reverification where supported by Clerk.

Examples:

```text
change password
change primary email
remove password
add/remove MFA factor
sensitive account-security operations
```

Do not rely only on:

```text
user has any valid long-lived session
```

for highly sensitive actions when Clerk provides a reverification mechanism.

Document the future client requirement.

Do not build a custom Laravel "enter password again" endpoint.

---

# 11. Password Compromise Protection

Inspect Clerk's configured password security features.

Where password authentication is enabled, review:

* password strength requirements;
* compromised-password detection;
* known-breach password rejection;
* forced-reset behavior.

Clerk currently supports marking/identifying compromised passwords and requiring password reset on the next sign-in.

Do not recreate breach-password checking in Laravel.

---

# 12. Forced Password Reset Session Task

Current Clerk instances can require a session task:

```text
reset-password
```

for compromised credentials.

Treat this state carefully.

Conceptual state:

```text
Clerk authentication succeeds
        ↓
session = PENDING
        ↓
required reset-password task
        ↓
password reset completed
        ↓
session = SIGNED_IN
```

Laravel protected business APIs must not treat an incomplete/pending session as fully authenticated application access.

---

# 13. Pending Sessions

Phase 4.3 may already distinguish usable authenticated sessions.

Strengthen this invariant:

```text
SIGNED_IN
→ protected Laravel access may continue

PENDING
→ protected Laravel access denied until required tasks complete

SIGNED_OUT
→ no protected access
```

Do not provision/use a pending security state as equivalent to a fully signed-in customer for business operations.

---

# 14. Security Task Abstraction

Do not hard-code only one task forever.

Clerk may expose required tasks such as:

```text
reset-password
setup-mfa
choose-organization
```

This application currently does not need Organizations, but the authentication boundary should safely distinguish:

```text
fully authenticated
```

from:

```text
authentication complete but required security task outstanding
```

Do not grant application access solely because a session object exists.

---

# 15. Password Recovery Enumeration Protection

Existing project rules require recovery not to reveal whether an account exists.

Preserve that behavior.

Do not create Laravel endpoints returning:

```text
"email exists"
"user not found"
"no account registered"
```

during recovery.

Where Clerk's recovery components/API handle this safely, rely on them.

Do not add account-discovery endpoints for UX convenience.

---

# 16. Recovery Identifier

Recovery should use the configured Clerk identifiers.

Do not make Laravel authoritative for:

```text
email → account lookup
phone → account lookup
```

during password recovery.

Clerk owns identity-recovery lookup.

Laravel email snapshots are not authentication proof.

---

# 17. Password Removal

Clerk currently provides privileged backend functionality to remove a password from a user.

This is a **security-sensitive administrative operation**.

Do not expose a generic Laravel endpoint for it during Phase 4.4.

If password removal is ever required:

* require explicit approved user/admin workflow;
* require appropriate reverification;
* consider whether another sign-in method exists;
* decide session-revocation behavior;
* audit the action.

Do not implement it merely because the Clerk API supports it.

---

# 18. Session Revocation

Clerk supports revoking a specific session.

Document and test the security meaning:

```text
revoke session X
→ X can no longer authenticate
→ other valid sessions may remain
```

Do not automatically treat:

```text
revoke one session
```

as:

```text
revoke all sessions
```

unless explicitly intended.

---

# 19. Current Session Logout vs Security Revocation

Keep separate:

```text
ordinary logout
```

and:

```text
security incident revocation
```

Ordinary logout:

```text
terminate current intended session
```

Security incident may require:

```text
revoke suspicious session
or
revoke all sessions
```

depending on the operation.

Do not use one broad "logout everything" implementation for every case.

---

# 20. Password Change Session Policy

Determine and document the configured Clerk behavior after password changes.

Explicitly answer:

* Does current session remain active?
* Are other sessions revoked?
* Does the application want stronger behavior?
* Are staff/admin sessions treated differently later?

Do not assume password change always invalidates all sessions.

Use current Clerk behavior/configuration.

---

# 21. Compromised Password + Session Revocation

If the application later invokes Clerk's compromised-password administrative security operations, decide deliberately whether to:

```text
force reset only
```

or:

```text
force reset + revoke sessions
```

Do not use privileged backend operations casually.

For a small furniture ecommerce customer app, default to Clerk's secure standard flow unless a concrete threat model requires stronger administrative intervention.

---

# 22. Unauthorized Sign-In Protection

Review Clerk's unauthorized/new-device sign-in security capabilities.

Clerk currently supports unauthorized-sign-in notifications and session revocation functionality for supported configurations.

Document whether the project intends to enable this later.

Do not build a parallel Laravel email notification flow in Phase 4.4.

Notification delivery remains Group R.

---

# 23. MFA Recovery

Do not implement an end-user MFA-reset endpoint casually.

Clerk currently states MFA recovery/reset requires a deliberate application/admin policy when the user loses all second factors.

This is inherently high-risk because resetting MFA bypasses a security factor.

Therefore Phase 4.4 should:

* document the issue;
* leave MFA-reset workflow disabled unless needed;
* prefer backup codes/multiple factors/passkeys where configured;
* assign any administrative MFA recovery workflow to a later dedicated security/admin phase.

Do not let Staff reset Customer MFA.

---

# 24. Staff/Admin Security Separation

Even if future staff/admin users authenticate through Clerk:

```text
STAFF
```

must not gain ordinary authority to:

* reset customer passwords;
* remove customer MFA;
* revoke customer security factors;
* change customer email;
* impersonate customer.

Admin-level security operations require explicit dedicated permission and audit design.

Do not add such capabilities in this phase.

---

# 25. Authentication Credential vs Application Account State

Keep:

```text
Clerk credential/security state
```

separate from:

```text
Laravel application account state
```

Example:

```text
Clerk password successfully reset
```

does not automatically imply:

```text
Laravel suspended account becomes active
```

Likewise:

```text
Laravel account suspended
```

does not require deleting the Clerk identity.

Do not couple these states accidentally.

---

# 26. Account Suspension

If local account state denies application use:

```text
valid Clerk session
+
local application account suspended
=
authentication valid
but application authorization denied
```

Do not reset/delete passwords to suspend an application account.

Account-management policy belongs later.

---

# 27. No Password in Laravel Logs

Audit existing request/log middleware.

Ensure no password or recovery fields can be logged.

Sensitive keys to redact include where applicable:

```text
password
current_password
new_password
password_confirmation
reset_token
code
Authorization
session token
```

Even though Laravel should not receive Clerk passwords, defensive redaction remains useful.

---

# 28. No Clerk Secrets in Logs

Never log:

```text
CLERK_SECRET_KEY
webhook secret
session JWT
authorization header
Backend API credential
```

Error handlers must not serialize Clerk client configuration.

---

# 29. Security Events

Document useful high-level security events:

```text
password recovery initiated
password reset completed
password changed
session revoked
all sessions revoked
MFA reset/admin security action
security reconciliation failure
```

Do not log secret material.

Not every event needs a new database table in this phase.

Use existing logging/audit infrastructure where applicable.

---

# 30. Audit Scope

Application-level privileged security operations should eventually be auditable.

At minimum future admin security actions should capture:

```text
actor
action
target
timestamp
request_id
result
```

No passwords, reset codes, session tokens, or MFA secrets.

Do not create a full new audit subsystem if Group K/later phase already owns it.

---

# 31. Laravel Error Mapping

If a protected API request arrives while Clerk authentication/security is unusable:

map to existing project codes.

Potential cases:

```text
missing authentication
expired session
invalid authentication
pending/incomplete security task
forbidden local account state
temporary external auth failure
```

Use the existing CLOSED error vocabulary wherever possible.

Do not expose Clerk error names directly.

---

# 32. Pending Security Task Error

If the current API contract has no explicit error for:

```text
authenticated but Clerk security task incomplete
```

do not casually invent one.

Review whether existing:

```text
INVALID_AUTHENTICATION
SESSION_EXPIRED
FORBIDDEN
```

or another approved error is semantically appropriate.

If a new client-visible code is genuinely necessary, follow the frozen-contract change process.

Do not silently extend the CLOSED error registry.

---

# 33. External Service Failure

If a Clerk Backend API security operation fails temporarily:

* do not claim success;
* do not weaken authentication;
* do not fabricate security state;
* return the approved provider-independent temporary error.

Do not return raw Clerk network messages.

---

# 34. Recovery UI Remains Future Work

Do not modify:

```text
frontend/web/
frontend/app/
```

during this phase.

Website customer recovery UI belongs with later website auth/account UI.

Flutter recovery UI belongs with later Flutter auth/account work.

Phase 4.4 establishes backend/security semantics.

---

# 35. Clerk Account Portal / Components

For future frontend implementation, prefer Clerk-provided account/security flows where they satisfy project UX requirements.

This reduces custom security code for:

```text
forgot password
change password
session/security management
```

Do not implement those components now.

---

# 36. No Custom Reset Email

Do not use Laravel Mail to send password reset messages.

Do not introduce:

```text
ResetPasswordNotification
Mail::to(...)
```

for Clerk recovery.

Clerk owns credential-recovery communication.

Group R remains responsible for application notifications, not authentication credential emails owned by Clerk.

---

# 37. No Recovery Queue Job

Do not create Laravel jobs such as:

```text
SendPasswordResetEmailJob
GenerateResetTokenJob
```

They are unnecessary with Clerk.

Remove/avoid dead infrastructure.

---

# 38. Email Verification Separation

Password recovery may verify an email/phone as a recovery factor.

That does not automatically settle the application's broader Phase 4.5 email-verification policy.

Do not collapse:

```text
recovery factor verification
```

into:

```text
application email verification requirement
```

Phase 4.5 owns the latter.

---

# 39. Profile Separation

Password/security settings are not ordinary profile fields.

Preserve:

```text
PATCH /me
```

for approved personal data only.

Do not add:

```json
{
  "password": "...",
  "new_password": "...",
  "email_verified": true
}
```

to profile mutation.

Security changes remain Clerk-owned workflows.

---

# 40. No Local Security Metadata as Authority

Do not invent local flags such as:

```text
password_reset_required
password_compromised
mfa_enabled
```

unless they have an explicit application-level use.

Clerk should remain authoritative for credential-security state.

If a local snapshot is later useful for display, it must be clearly non-authoritative and synchronized.

Avoid unnecessary duplication.

---

# 41. Session Lifetime Review

Inspect Clerk's current session configuration:

* inactivity timeout;
* maximum lifetime;
* multi-session behavior.

Clerk requires at least one session lifetime mechanism to be enabled.

Document current configuration and whether it satisfies V1 security expectations.

Do not hard-code session lifetime in Laravel.

---

# 42. Customer vs Privileged Session Policy

Do not prematurely create different session systems.

However, record that future Staff/Admin access may require stronger controls such as:

```text
shorter sessions
MFA
step-up authentication
stronger session visibility/revocation
```

Do not implement privileged-session policy unless Group D later explicitly owns it.

---

# 43. Security Recovery Threat Model

Review the following threats:

```text
account enumeration
email takeover
SIM-swap risk
stolen session
compromised password
credential stuffing
MFA loss
reset-token theft
session replay
privilege escalation through recovery
```

For each, ensure the chosen architecture does not move a weaker fallback into Laravel.

Do not weaken Clerk security to preserve an old Laravel flow.

---

# 44. No Security Questions

Do not implement:

```text
mother's maiden name
favorite teacher
security question
```

as password-recovery fallback.

They are weak identity proof and unnecessary with Clerk.

---

# 45. No Admin-Set Customer Password

Do not create an Admin function:

```text
set customer's password
```

during this phase.

Admin must never know or choose customer credentials.

If future support needs account recovery assistance, use secure Clerk-supported recovery/admin security operations with explicit policy.

---

# 46. No Plaintext Temporary Password

Never generate:

```text
temporary123
welcome123
random password emailed to customer
```

as a recovery method.

Do not store or email plaintext passwords.

---

# 47. Session Revocation Adapter

If Phase 4.3 already has an abstraction around Clerk sessions, extend/reuse it.

Possible concept:

```text
ClerkSessionManager
```

Responsibilities may include narrowly:

```text
revoke specific session
revoke approved sessions where required
```

Do not mix:

```text
token verification
password recovery
user provisioning
authorization
```

into the same class.

---

# 48. Do Not Build a Generic ClerkService

Avoid a giant:

```text
ClerkService
```

handling:

```text
login
password
MFA
sessions
users
webhooks
roles
```

Keep security operations cohesive.

Use current Phase 4.1/4.2/4.3 abstractions.

---

# 49. Dependency Injection

External Clerk operations must remain injectable/testable.

Production:

```text
security/session gateway
→ Clerk implementation
```

Tests:

```text
security/session gateway
→ fake
```

Do not make PHPUnit call live Clerk services.

---

# 50. Tests — Laravel Password Endpoints Absent

If custom Laravel password endpoints were retired, verify they are not exposed.

Assert legacy routes return `410 GONE` with contract code `RESOURCE_NOT_FOUND`
regardless of authentication state — exact mapping for retired endpoints:
`AUTH-004` `POST /api/v1/auth/password/forgot`, `AUTH-005` `POST /api/v1/auth/password/reset`,
`AUTH-008` `POST /api/v1/auth/change-password` (also `AUTH-003` logout and `AUTH-006/007`
email verify) each return `410`; unregistered paths return `404`.

Do not leave a hidden second password-reset API.

---

# 51. Tests — Password Not Persisted

After any customer recovery/security path touching Laravel:

verify:

```text
users.password
```

is not created/changed as a shadow Clerk credential.

If the column was removed, verify code no longer references it.

If temporarily nullable for compatibility, ensure it remains null for Clerk customer accounts.

---

# 52. Tests — Pending Reset Task

Simulate Clerk identity/session state requiring:

```text
reset-password
```

Verify protected application access is not treated as fully authenticated.

No checkout.

No account-sensitive API.

No privileged operations.

---

# 53. Tests — Completed Reset

Simulate completion of required Clerk password-reset task.

Then a valid fully signed-in session should authenticate normally through the Phase 4.3 flow.

No local password synchronization should occur.

---

# 54. Tests — Expired Recovery Session

Where test abstractions model recovery state:

verify expired/invalid recovery state cannot result in Laravel authenticated access.

Do not test Clerk's internal password-reset implementation itself.

Test Laravel's boundary assumptions.

---

# 55. Tests — Session Revocation

Given an accepted session:

```text
session A
```

simulate revocation.

Verify subsequent authenticated request fails.

If:

```text
session B
```

remains active, verify it behaves according to the intended Clerk multi-session policy.

---

# 56. Tests — No Credential Leakage

Ensure API error responses do not contain:

```text
password
reset token
session token
Clerk secret
session ID unnecessarily
raw Clerk error
```

Add focused regression tests if the existing error layer permits this.

---

# 57. Tests — Staff Cannot Reset Customer Credentials

If any security-operation endpoint/gateway exists at this stage:

simulate STAFF attempting to invoke customer credential/security mutation.

Expected:

```text
FORBIDDEN
```

or no public route exists at all.

Do not grant STAFF security administration.

---

# 58. Tests — Customer Cannot Elevate Role During Recovery

Recovery does not alter Laravel role.

Given CUSTOMER before recovery:

after recovery:

```text
CUSTOMER
```

still.

Do not consume Clerk metadata during recovery to update RBAC.

---

# 59. Tests — Suspended Local Account

If local account-state support exists:

```text
valid Clerk recovery
+
valid Clerk session
+
Laravel suspended account
```

must not reactivate the Laravel account automatically.

Credential recovery and business-account state remain separate.

---

# 60. Tests — Enumeration

Where Laravel still participates in any recovery-facing response:

ensure responses do not expose account existence.

Prefer that Clerk owns the entire recovery request surface, making Laravel enumeration tests unnecessary for that path.

---

# 61. Offline Test Requirement

Normal test suite must be fully offline.

Use fake Clerk security/session gateways.

Do not use real:

```text
Clerk account
OTP
email
password reset
session
```

in PHPUnit.

Live provider tests belong to Phase 4.12/staging integration.

---

# 62. Documentation Updates

Update relevant consolidated documentation.

Likely:

```text
docs/api/api-conventions.md
docs/api/api-contract.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Document:

* Clerk owns password recovery;
* Laravel reset endpoints retired where applicable;
* password changes are Clerk-owned;
* pending security tasks block protected access;
* application account state remains Laravel-owned;
* session revocation semantics;
* enumeration protection;
* Staff cannot manage customer credentials.

Do not create unnecessary permanent phase docs.

---

# 63. OpenAPI Cleanup

Review any old schemas such as:

```text
ForgotPasswordRequest
ResetPasswordRequest
ChangePasswordRequest
```

If their endpoints are retired:

remove them from active OpenAPI references through the approved contract-change process.

Do not leave orphaned active security schemas implying Laravel accepts passwords.

Retired endpoint IDs remain documented where the project records retired IDs.

---

# 64. AUTH Endpoint Matrix

Update the Clerk migration matrix.

For every legacy credential/security AUTH endpoint record:

```text
ID
old responsibility
current owner
active/retired
replacement flow
```

Example concept:

```text
AUTH-004
old Laravel forgot-password request
→ Clerk-owned
→ retired
→ future Clerk recovery UI
```

Use actual project endpoint IDs rather than guessing from this example.

---

# 65. Security Configuration Record

Document actual Clerk-instance choices that materially affect application behavior:

```text
password enabled?
password rules
compromised-password protection
MFA enabled?
session timeout
multi-session setting
forced reset tasks
device trust if enabled
```

Do not commit secrets.

These are configuration decisions, not credentials.

---

# 66. No Schema Expansion Expected

Expected database schema changes:

```text
NONE
```

unless removing an obsolete reset-token table is explicitly approved and safe.

Do not create a local password-security schema.

---

# 67. No User Table Security Duplication

Do not add fields such as:

```text
password_reset_token
password_reset_expires_at
force_password_reset
mfa_secret
backup_codes
last_password_change
```

unless there is a concrete Laravel-owned business need approved through architecture review.

Clerk owns these concerns.

---

# 68. Account Retention Still Deferred

Do not mix:

```text
forgot password
```

with:

```text
delete account
```

The previously recorded cart FK/delete-account retention issue remains owned by:

```text
Phase 4.6 / Group K
```

Do not solve it here.

---

# 69. ReferenceGenerator Deferred Work

Do not touch unrelated reference generation:

```text
OrderFactory
PaymentFactory
FurnitureRequestFactory
```

unless legitimately affected by changed files.

Keep auth/security phase focused.

---

# 70. Other Deferred Group C Risks

Leave unchanged:

```text
product_type / is_published → Group E 5.7
MySQL enum case validation → Group K
MySQL-backed CI harness assumptions → Group U
cart user FK retention → 4.6 / Group K
```

---

# 71. Code Quality

Maintain:

* small cohesive classes;
* dependency injection;
* no giant security service;
* no duplicated Clerk calls;
* no magic strings;
* centralized error mapping;
* centralized config;
* minimal comments;
* cognitive complexity ≤15;
* maximum 3 returns per function where practical.

Do not weaken PHPStan to accommodate integration code.

---

# 72. Security Review

Before completing Phase 4.4, verify:

```text
Laravel receives no customer password
Laravel issues no reset token
Laravel owns no reset email
Clerk secrets server-only
pending security sessions denied
session revocation semantics correct
roles unaffected by recovery
no Staff credential administration
no enumeration endpoint
no security backdoor
```

---

# 73. Commands / Checks

From:

```text
backend/laravel/
```

run at minimum:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If migrations changed:

```bash
php artisan migrate:fresh --seed
```

must also pass.

Use existing Composer scripts where defined.

---

# 74. Files Changed Report

At phase completion report:

## Files changed

Exact paths.

## Schema changes

Expected:

```text
none
```

unless explicitly justified.

## API changes

List retired/updated password-security endpoints.

## Clerk configuration reviewed

List non-secret security settings reviewed.

## Tests

List tests added/updated.

## Commands

Exact commands + result.

## Risks

Remaining security/recovery risks.

## Deferred

Explicitly note Phase 4.5+ ownership.

---

# 75. Definition of Done

Phase 4.4 is complete when:

* Clerk is the sole customer password-recovery authority;
* Laravel does not generate reset tokens;
* Laravel does not receive/change customer passwords;
* obsolete Laravel recovery endpoints are retired according to the contract;
* password changes are Clerk-owned;
* compromised-password behavior is documented;
* pending `reset-password`/security-task sessions cannot access protected business APIs;
* session revocation semantics are documented/tested;
* recovery cannot alter Laravel role;
* recovery cannot reactivate suspended application accounts;
* Staff cannot manage Customer credentials;
* enumeration protection is preserved;
* recovery/security secrets are not logged;
* test suite uses no real Clerk calls;
* PHPUnit passes;
* Pint passes;
* PHPStan passes;
* Composer audit passes;
* documentation and OpenAPI accurately reflect Clerk ownership.

---

# 76. Out of Scope

Do not implement:

* frontend forgot-password page;
* Flutter recovery UI;
* email-verification business policy;
* profile editing;
* account deletion;
* full MFA reset flow;
* custom MFA system;
* customer impersonation;
* Staff credential management;
* admin customer-security dashboard;
* notification emails from Laravel;
* Clerk webhooks;
* complete audit subsystem;
* rate limiting implementation.

---

# 77. STOP Condition

STOP once password recovery/security ownership is fully Clerk-based, Laravel contains no competing customer credential-recovery mechanism, pending security states are safely handled, session-revocation semantics are defined, and all Phase 4.4 checks pass.

Do not continue automatically.

The next roadmap phase is:

**Phase 4.5 — Email Verification with Clerk / Application Verification Policy**
