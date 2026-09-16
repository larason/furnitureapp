# Phase 4.5 — Email Verification with Clerk

## Purpose

Implement and document the customer email-verification policy using **Clerk as the email identity and verification authority**.

The customer registration baseline is now:

```text
email
password
```

Phone is **not required during signup**.

Phone, if collected later, belongs to the application profile/contact domain and must not be treated as an authentication requirement.

The target signup flow is:

```text
Customer
    ↓
email + password
    ↓
Clerk creates sign-up attempt
    ↓
Clerk sends email verification challenge
    ↓
customer verifies email
    ↓
Clerk completes signup
    ↓
authenticated Clerk session
    ↓
Laravel resolves/provisions local CUSTOMER
```

Laravel must not create its own email-verification token or duplicate Clerk's verification process.

---

# 1. Dependencies

Required:

* Phase 4.1 complete;
* Phase 4.2 complete;
* Phase 4.3 complete;
* Phase 4.4 complete;
* Clerk owns authentication;
* email + password selected as customer signup method;
* phone removed from signup requirements;
* Clerk identity maps to local `users.clerk_user_id`;
* JIT CUSTOMER provisioning works;
* Laravel does not accept customer passwords;
* Laravel does not own password recovery;
* pending/incomplete Clerk authentication states are already treated safely.

Do not proceed if Laravel still has a competing email verification workflow.

---

# 2. Scope

This phase covers:

* required email-at-signup configuration;
* Clerk email verification strategy;
* verified-email semantics;
* local email snapshot rules;
* local `email_verified` representation where required;
* incomplete signup handling;
* unverified-session access rules;
* verification resend behavior at the application boundary;
* email-change implications;
* tests around verified/unverified identity;
* removal of obsolete Laravel verification logic;
* documentation/OpenAPI updates.

Do not build final web/mobile verification UI yet.

---

# 3. Signup Requirements

The canonical V1 customer signup identity requirements are now:

```text
required:
- email
- password

not required:
- phone
```

Remove outdated requirements stating:

```text
name + email + phone + password
```

where those statements refer specifically to authentication registration.

Do not remove name/phone from unrelated domain models where they are legitimately required for:

* delivery;
* enquiries;
* furniture requests;
* customer contact;
* profile data.

This change applies only to **authentication signup requirements**.

---

# 4. Clerk Configuration

Review the selected Clerk application.

The expected configuration is:

```text
Sign-up with email      ENABLED
Require email address   ENABLED
Verify at sign-up       ENABLED
Sign-in with email      ENABLED
Sign-up with password   ENABLED
Phone signup            NOT REQUIRED
```

Do not enable phone authentication merely because Clerk supports it.

Do not require SMS OTP.

---

# 5. Verification Strategy

Use Clerk as the only email-verification authority.

Preferred V1 baseline:

```text
email verification code
```

unless the project owner has deliberately configured email-link verification instead.

Clerk currently uses email verification code by default for email/password signup when verification-at-signup is enabled.

Do not implement both code and link flows unless there is a real requirement.

---

# 6. Why Verification Is Required

Require the signup email to be verified before the registration is considered complete.

This gives the application:

```text
Clerk identity
+
verified primary email
+
password credential
```

before the customer is treated as a fully registered application user.

Do not allow:

```text
unverified email
→ fully registered CUSTOMER
→ unrestricted authenticated commerce access
```

unless a future explicit policy changes this.

---

# 7. Canonical Signup Sequence

The intended sequence is:

```text
1. Customer supplies email.
2. Customer supplies password.
3. Clerk validates signup input.
4. Clerk creates sign-up attempt.
5. Clerk sends email verification challenge.
6. Customer submits verification code.
7. Clerk verifies email.
8. Clerk completes signup.
9. Clerk creates/activates authenticated session.
10. Laravel verifies Clerk session.
11. Laravel Phase 4.2 provisioning resolves/creates CUSTOMER.
```

Do not provision the final local CUSTOMER before the Clerk signup is complete unless Phase 4.2 explicitly stores provisional identities.

The preferred V1 behavior is to provision only after successful verified Clerk authentication.

---

# 8. Do Not Build Laravel Verification Tokens

Do not use:

```text
MustVerifyEmail
EmailVerificationRequest
verification.notice
verification.verify
verification.send
signed verification URL
Laravel email verification notifications
```

for Clerk customers.

Do not create:

```text
verification_token
verification_code
email_verified_token
```

in the Laravel database.

Clerk owns these concerns.

---

# 9. Remove Legacy Verification Infrastructure

Search the backend for obsolete Laravel-specific verification behavior.

Examples:

```text
MustVerifyEmail
sendEmailVerificationNotification
EmailVerificationRequest
verification.verify
verification.send
```

If these are leftovers from pre-Clerk design and are unused:

* remove or retire them;
* remove routes no longer supported;
* remove obsolete OpenAPI descriptions;
* do not leave a second email-verification path exposed.

Do not modify historical migrations unnecessarily.

---

# 10. Email Is Authentication Identity

The signup email belongs primarily to Clerk authentication.

Laravel may retain a local snapshot for application/domain purposes.

Canonical authority:


```text
Clerk primary email
    ↓
verified identity source
    ↓
Laravel email snapshot
```

Do not allow ordinary clients to change the authentication email by:

```http
PATCH /api/v1/me
```

unless the later dedicated security workflow explicitly permits it.

---

# 11. Local Email Snapshot

If `users.email` remains in Laravel, treat it as:

```text
Clerk-owned identity snapshot
```

not a separate authentication credential.

At initial provisioning:

```text
Clerk primary verified email
→ users.email
```

Do not source the email from request JSON.

Do not accept:

```json
{
  "email": "another@example.com"
}
```

as authoritative while provisioning.

---

# 12. Email Verification State

If the API exposes:

```text
email_verified
```

then it must reflect Clerk verification state.

Do not let the client set:

```json
{
  "email_verified": true
}
```

Do not calculate it from:

```text
users.email != null
```

A populated email is not the same as a verified email.

---

# 13. Local Verification Representation

Review Phase 4.1 and existing schema.

If the application requires a local snapshot such as:

```text
email_verified_at
```

or:

```text
email_verified
```

decide whether that snapshot is still necessary.

Preferred approach:

* keep only if required by the frozen API/domain representation;
* derive/synchronize it from Clerk;
* never use it as independent proof if Clerk identity state is available.

Do not introduce duplicate verification state without need.

---

# 14. Clerk Remains Authoritative

If local state says:

```text
verified
```

but Clerk says:

```text
unverified
```

Clerk is authoritative for authentication email verification.

Do not let stale Laravel state override Clerk security state.

---

# 15. JIT Provisioning Rule

Phase 4.2 provisioning should receive only a Clerk identity that satisfies the approved signup-completion policy.

Preferred:

```text
Clerk signup complete
+
email verified
+
authenticated session valid
        ↓
LocalUserProvisioner
```

Do not provision from an incomplete `SignUp` attempt.

---

# 16. Verification Before Full Application Access

The application should distinguish:

```text
email not verified / signup incomplete
```

from:

```text
fully registered customer
```

Unverified/incomplete signup must not grant access to protected business operations such as:

```text
checkout
own orders
notifications
account-sensitive APIs
```

The exact flow should be controlled by Clerk authentication state rather than a custom Laravel verification challenge.

---

# 17. Public Catalog Remains Available

Email verification is not required to:

```text
browse products
view categories
search
view product detail
```

Public catalog remains anonymous.

Do not add verification middleware to public catalog endpoints.

---

# 18. Anonymous Request / Enquiry Remains Available

Existing anonymous behavior remains:

```text
POST /requests
POST /enquiries
```

may operate without a registered account.

A user does not need to create or verify a Clerk account merely to submit an anonymous request/enquiry where the API already permits that behavior.

Do not change these domain rules in Phase 4.5.

---

# 19. Verification Code Handling

If email-code verification is used:

Clerk owns:

```text
code generation
code delivery
code expiration
code validation
resend rules
attempt tracking
```

Laravel must never receive or persist the code.

Do not proxy Clerk verification codes through Laravel.

---

# 20. Resend Behavior

Clerk's prebuilt authentication flows currently enforce a resend cooldown separate from code expiration.

Do not create an independent Laravel resend timer.

The future frontend should rely on Clerk's actual state/error response for when resend is permitted.

Do not hard-code the frontend countdown into backend domain logic.

---

# 21. Verification Code Lifetime

Do not duplicate Clerk's code expiration rules in Laravel.

The application should not assume a verification code remains valid for a custom duration.

Clerk currently documents a 10-minute validity period for email verification codes in its prebuilt flows, but that remains provider-owned behavior rather than a Laravel domain constant.

---

# 22. Email Link Alternative

If the project later chooses email-link verification:

review Clerk's current same-device/browser protection.

Clerk supports requiring the email verification link to be opened on the same device/browser that initiated authentication, reducing link-forwarding/phishing risks.

Do not disable this protection without a concrete UX/security reason.

For V1, prefer verification code unless the owner explicitly changes the strategy.

---

# 23. Sign-In Method

Customer sign-in baseline is:

```text
email
+
password
```

Do not require an email OTP on every normal login merely because email verification was required at signup.

Keep separate:

```text
verify email ownership during signup
```

and:

```text
authenticate later using email + password
```

unless Clerk security policy requires additional factor/device trust.

---

# 24. Device Trust

Current Clerk email/password guidance may enable Device Trust by default depending on instance configuration.

Inspect actual configuration.

If Device Trust is enabled, future sign-in flows must correctly handle additional verification when required.

Do not bypass Clerk's second-factor/device-trust state in Laravel.

Do not implement custom device trust.

---

# 25. MFA Is Separate

Email verification is not the same as MFA.

Do not claim:

```text
email verified
=
MFA enabled
```

MFA policy remains separate.

Do not require phone merely to satisfy MFA in this phase.

If MFA is later introduced, authenticator apps, backup codes, or other supported methods can be evaluated separately.

---

# 26. Phone Is Not Signup Identity

Explicitly remove phone from:

```text
required registration credentials
required Clerk signup identifiers
required verification factors
```

Phone may later appear in:

```text
customer profile
delivery contact
order recipient
enquiry/request contact
```

but it is not authentication identity for V1.

---

# 27. Update Registration Documentation

Search for statements equivalent to:

```text
registration collects name, email, phone, password
```

When they refer to customer authentication signup, update them to:

```text
registration requires email + password
```

If name is later required for application profile completeness, document it separately from Clerk authentication.

Do not merge profile requirements into credential requirements.

---

# 28. Name Is Not an Authentication Credential

Unless Clerk configuration explicitly requires name during signup, do not require name as part of authentication.

Name can be collected:

```text
during profile completion
or
during commerce/contact flow
```

according to later requirements.

Do not fabricate name from email.

---

# 29. Registration Input Ownership

The future frontend signup form should eventually submit:

```text
email
password
```

to Clerk.

Not:

```text
role
permissions
user_id
clerk_user_id
email_verified
```

Those remain server/provider controlled.

---

# 30. Do Not Route Password Through Laravel

The new email/password registration choice reinforces:

```text
client
→ Clerk
```

for password handling.

Never:

```text
client
→ Laravel
→ Clerk
```

unless Clerk's supported architecture explicitly requires backend orchestration, which is not the selected client flow.

Laravel should not inspect customer passwords.

---

# 31. Email Collision Safety

If Clerk successfully authenticates:

```text
user_NEW
email = existing@example.com
```

but Laravel contains an unmapped local record with the same email:

do not automatically link.

Preserve Phase 4.2:

```text
identity mapping by Clerk user ID
not email
```

Email verification does not make email safe as a local account-linking key.

---

# 32. Email Change Security

Changing the primary authentication email later is a security-sensitive operation.

Do not implement it through ordinary profile mutation.

Future flow should conceptually be:

```text
authenticated customer
    ↓
reverification
    ↓
Clerk email-change flow
    ↓
new email verified
    ↓
primary email changed in Clerk
    ↓
Laravel snapshot reconciled
```

Implementation belongs to Phase 4.6 or later dedicated account-security work.

---

# 33. Old Email After Change

Do not assume `users.email` always remains permanently equal to signup email.

Clerk may allow verified email changes later.

The stable identity remains:

```text
clerk_user_id
```

not email.

This is another reason never to use email as foreign identity.

---

# 34. Multiple Email Addresses

Clerk User objects may support multiple email addresses.

Laravel should use only the **primary email** for the local application snapshot unless a future feature explicitly requires additional addresses.

Do not create a local email-address collection in this phase.

---

# 35. Primary Email Selection

Always use Clerk's declared primary email relationship.

Do not use:

```text
emailAddresses[0]
```

unless the API explicitly guarantees that is primary.

Use the official primary email identifier/accessor.

---

# 36. Verification State Synchronization

Define how local verification snapshot is refreshed.

Use the strategy approved in Phase 4.1, for example:

```text
initial provisioning
+
bounded JIT reconciliation
+
future Clerk webhook reconciliation
```

Do not call Clerk Backend API on every application request solely to re-check email verification.

---

# 37. Webhooks Still Deferred

Do not implement:

```text
user.created
user.updated
email verification webhook
```

during this phase unless Phase 4.1 explicitly assigned it here.

Webhooks may later reconcile local email snapshots.

They must remain asynchronous support, not the prerequisite for successful authentication.

---

# 38. Verification and Session State

A verified email should result in Clerk allowing signup/session completion according to instance configuration.

Laravel should trust the fully authenticated Clerk session boundary.

Do not create a second middleware such as:

```text
auth.clerk
verified.email.local
```

unless the local business requirement genuinely requires a separate verification state.

Avoid duplicating Clerk policy.

---

# 39. Error Mapping

Clerk verification errors shown in Clerk-owned signup UI do not need to be transformed into Laravel's error envelope.

Examples:

```text
incorrect verification code
expired verification challenge
too many attempts
```

are part of Clerk's authentication flow.

Laravel error mapping applies when accessing Laravel APIs.

Do not proxy all authentication-step errors through Laravel.

---

# 40. Protected API with Incomplete Signup

If a caller somehow supplies a Clerk credential that is not eligible for full application authentication because signup/verification is incomplete:

reject it according to Phase 4.3 authentication semantics.

Do not JIT provision a customer using partially verified identity information.

---

# 41. Email Verification Does Not Grant Role

Completing email verification must never result in:

```text
STAFF
ADMIN
```

role assignment.

Public registration remains:

```text
verified Clerk account
→ CUSTOMER
```

only.

---

# 42. Email Verification Does Not Change Account State

A customer verifying their email must not automatically change arbitrary local business-account state.

Example:

```text
Laravel account suspended
+
email newly verified
≠
account reactivated
```

Authentication security state and application business state remain separate.

---

# 43. No Admin Verification Override by Default

Do not build:

```text
Admin marks customer email verified
```

in Laravel.

Clerk is the verification authority.

If extraordinary administrative verification is ever needed, it requires explicit security-policy review.

Do not implement it in V1 by default.

---

# 44. No Staff Verification Override

STAFF must have zero authority to:

```text
mark email verified
change login email
bypass verification
```

Do not expose any such operation.

---

# 45. Test — New Signup Verification Required

Using fakes/fixtures representing Clerk signup state:

verify:

```text
email + password entered
email unverified
```

does not result in a fully authenticated Laravel customer.

No protected API access.

---

# 46. Test — Verified Signup

Simulate:

```text
verified email
completed Clerk signup
valid authenticated session
```

Then verify:

* local CUSTOMER can be provisioned/resolved;
* `users.email` reflects approved Clerk primary email snapshot;
* local role = CUSTOMER;
* no phone required;
* no Laravel password stored.

---

# 47. Test — Signup Without Phone

Mandatory regression test:

Given valid:

```text
email
password
```

and no phone:

signup/provisioning must remain valid according to the new policy.

Do not reject registration because:

```text
phone = null
```

---

# 48. Test — No Client Verification Override

Send:

```json
{
  "email_verified": true
}
```

to any relevant Laravel profile/bootstrap endpoint.

Verify it cannot mark email verified.

Unknown/server-controlled fields should be rejected according to project validation conventions.

---

# 49. Test — Unverified Email Cannot Bypass Clerk

Simulate an unverified/incomplete Clerk signup and an attempted protected Laravel request.

Verify no local authorization bypass occurs.

Do not create CUSTOMER based solely on:

```text
valid-looking email
```

---

# 50. Test — Different Email, Same Clerk Identity

Where synchronization behavior can be tested:

```text
same clerk_user_id
new verified primary email
```

must still map to the same:

```text
users.id
```

Do not create another account.

---

# 51. Test — Same Email, Different Clerk Identity

Given:

```text
user_A → same@example.com
user_B → same@example.com
```

or an equivalent legacy collision scenario:

do not automatically merge Laravel identities.

Preserve identity mapping rules from Phase 4.2.

---

# 52. Test — No Phone Requirement

Search validation/tests for signup expectations that require:

```text
phone
```

Remove or update only those tied to authentication registration.

Do not weaken phone validation for:

```text
delivery
request contact
enquiry contact
recipient contact
```

where phone remains domain-relevant.

---

# 53. Test — No Laravel Verification Email

Verify no application path invokes:

```text
sendEmailVerificationNotification()
```

for Clerk customers.

No duplicate email should be sent by Laravel.

---

# 54. Test — Public Catalog

Unauthenticated/unverified visitors must still access public catalog APIs.

Email verification must not leak into public routes.

---

# 55. Test — Anonymous Request / Enquiry

Ensure anonymous request/enquiry creation still works according to existing contract.

Do not require Clerk verification for those anonymous paths.

---

# 56. Test — Role Tampering

During signup/provisioning, attempting:

```json
{
  "role": "ADMIN"
}
```

must not change CUSTOMER assignment.

Email verification proves email ownership only.

It proves nothing about application privilege.

---

# 57. Test — Verification Snapshot

If Laravel maintains local `email_verified` state:

test that it can only be populated from trusted Clerk state.

Do not allow fixture/request body mutation to bypass the trusted integration boundary.

---

# 58. Offline Testing

Normal PHPUnit tests must remain offline.

Do not send real:

```text
verification email
OTP
Clerk request
```

during tests.

Use fakes for:

```text
Clerk user
verification state
session state
Backend API gateway
```

Real Clerk integration testing belongs to Phase 4.12/staging.

---

# 59. Database Review

Expected schema changes:

```text
NONE
```

if the existing `users.email` and verification representation already support Clerk.

If `phone` currently has a NOT NULL constraint directly on `users` because registration originally required it:

review whether that prevents the new email/password-only signup.

If so, create a **new migration** making phone nullable where consistent with the domain model.

Do not edit the original Group C migration.

---

# 60. Phone Column Decision

Phone should be nullable for customer identity/profile if signup does not require it.

However, this does not mean every domain phone field becomes nullable.

Distinguish:

```text
users/profile phone
```

from:

```text
order recipient_phone
delivery phone
request/enquiry contact requirements
```

Do not globally relax phone constraints.

---

# 61. Customer Profile

If `customer_profiles.phone` exists and is currently required only because of the old signup assumption:

review it.

Preferred:

```text
phone nullable
```

until the customer provides it later.

Do not generate fake phone numbers.

---

# 62. Profile Completion

Do not force collection of phone immediately after signup just to preserve the old design.

Phase 4.6 will define profile operations.

If commerce later requires phone:

collect it at the appropriate profile/checkout/contact boundary.

Do not make it a credential requirement.

---

# 63. Checkout Contact

If checkout requires recipient phone:

that is a checkout/delivery requirement.

It must not be confused with:

```text
account signup requires phone
```

A user may sign up successfully with email/password and provide recipient contact during checkout later.

---

# 64. Request / Enquiry Contact

Existing furniture request/enquiry rules remain unchanged unless separately revised.

Anonymous contact requirements may still use:

```text
name
+
email and/or phone
```

according to their frozen domain rules.

Do not use the new signup rule to modify those endpoints.

---

# 65. Update `AGENTS.md`

Update authentication-related roadmap notes/conventions to reflect:

```text
Customer auth registration:
- required email
- required password
- Clerk verifies email
- phone not required
```

Do not alter unrelated roadmap phases.

---

# 66. Update `api-conventions.md`

Replace obsolete auth registration rules that still require phone.

A suitable conceptual rule is:

```text
Registration credentials/identity:
email + password

Verification:
Clerk email verification at signup

Phone:
optional application profile/contact data,
not an authentication requirement
```

Keep other domain phone rules intact.

---

# 67. Update `api-contract.md`

Where the frozen/auth-migrated contract describes Clerk registration semantics, reflect the new requirement.

If old AUTH registration endpoints were retired in Phase 4.1:

do not reintroduce them just to describe these fields.

Document the Clerk-owned registration behavior in the auth contract/architecture section.

---

# 68. OpenAPI

If no Laravel signup endpoint exists, do not create a signup request schema merely for Clerk.

Remove obsolete active schemas requiring:

```text
phone
```

from retired Laravel signup flows as appropriate.

Protected Laravel endpoints continue using Clerk bearer authentication.

---

# 69. Documentation Ownership Matrix

Update field ownership:

| Field              | Authority                        | Required at signup?             |
| ------------------ | -------------------------------- | ------------------------------- |
| Clerk User ID      | Clerk                            | generated                       |
| email              | Clerk                            | yes                             |
| email verification | Clerk                            | yes                             |
| password           | Clerk                            | yes                             |
| phone              | application profile/contact      | no                              |
| name               | application profile/profile flow | no unless separately configured |
| role               | Laravel                          | server assigned                 |
| permissions        | Laravel                          | no                              |

Do not make this matrix client-controlled.

---

# 70. Security Review

Before completion verify:

```text
email required
password required
phone not required
email verified through Clerk
Laravel does not issue verification codes
Laravel does not send duplicate verification mail
unverified signup cannot access protected commerce
public browsing remains available
role unaffected by email verification
email never used as local identity binding key
```

---

# 71. Code Quality

Maintain existing project requirements:

* small cohesive services;
* dependency injection;
* no giant auth service;
* minimal comments;
* centralized constants;
* cognitive complexity ≤15;
* maximum 3 return statements where practical;
* PHPStan level 5;
* no duplicate Clerk integrations.

Do not add a special verification service if existing Clerk identity/session abstractions already expose trusted verification state cleanly.

---

# 72. Commands / Checks

From:

```text
backend/laravel/
```

run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If schema changed because phone became nullable:

```bash
php artisan migrate:fresh --seed
```

must also pass.

Run any project-defined equivalents.

---

# 73. Files Changed Report

At completion report:

## Files changed

Exact paths.

## Authentication policy changes

Explicitly state:

```text
signup = email + password
email verification = required through Clerk
phone = not required
```

## Schema changes

Especially whether:

```text
users.phone
customer_profiles.phone
```

needed nullable adjustments.

## API changes

List only auth-related contract changes.

## Tests

List all added/updated tests.

## Commands

Exact commands + results.

## Deferred

Phase 4.6+ concerns.

---

# 74. Definition of Done

Phase 4.5 is complete when:

* customer signup requires email + password only;
* phone is not required for customer signup;
* Clerk is the sole email-verification authority;
* verification at signup is enabled/required;
* the configured verification strategy is documented;
* Laravel generates no email verification token/code;
* Laravel sends no duplicate verification email;
* incomplete/unverified Clerk signup cannot become unrestricted authenticated application access;
* verified Clerk customer can be provisioned/resolved correctly;
* local email snapshot comes from trusted Clerk data;
* local verification state, if present, derives from Clerk;
* email remains non-authoritative for local identity linking;
* CUSTOMER remains the only public-registration role;
* public catalog stays public;
* anonymous request/enquiry behavior stays unchanged;
* phone-related schema no longer blocks valid email/password-only registration;
* offline tests pass;
* PHPUnit passes;
* Pint passes;
* PHPStan passes;
* Composer audit passes;
* documentation accurately reflects the new registration policy.

---

# 75. Out of Scope

Do not implement:

* final Next.js verification UI;
* Flutter verification UI;
* phone authentication;
* SMS OTP;
* phone-required signup;
* profile editing;
* email-change UI;
* account deletion;
* Clerk webhooks;
* MFA setup;
* admin email-verification override;
* Staff credential management;
* checkout contact implementation.

---

# 76. STOP Condition

STOP when Clerk email verification is the sole verification mechanism, customer signup works conceptually and structurally with only:

```text
email + password
```

phone is no longer required by authentication or local provisioning, and all Phase 4.5 validation/tests/documentation are complete.

Do not continue automatically.

The next roadmap phase is:

**Phase 4.6 — Profile Operations / Account Profile Synchronization**
