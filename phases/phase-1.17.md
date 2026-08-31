# Phase 1.17 — Define Authentication Contract

## 1. Purpose

Phase 1.17 establishes the project's **authentication contract** for all client applications and all three human actor types:

```text
CUSTOMER
STAFF
ADMIN
```

The most important principle is:

> **Customers own their accounts and customer experience. Staff operate the commerce business but do not control customer accounts. Admins have the highest administrative authority and are responsible for approving/managing staff access.**

This phase defines:

* who can register;
* who can log in;
* who requires authentication;
* customer account ownership;
* staff authentication;
* admin authentication;
* session/token concepts;
* logout;
* password recovery;
* account verification;
* account lifecycle;
* device/session considerations;
* authentication boundaries;
* customer/staff/admin separation;
* credential security;
* authentication-related API behavior.

This phase does **not** implement Laravel authentication, Sanctum, password hashing code, database migrations, middleware, frontend login pages, or Flutter authentication screens.

---

# 2. Documentation Strategy

Continue using consolidated project documentation.

Do **not** create:

```text
authentication.md
auth-contract.md
customer-auth.md
staff-auth.md
```

as separate permanent documents unless a later project decision genuinely requires them.

Update:

```text id="mccqv4"
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

The authentication rules should become part of the central API contract.

---

# 3. Authoritative Project Paths

Use:

```text id="vtfp1p"
AGENTS.md
docs/VISION.md
```

The old names are obsolete:

```text id="fdx5f9"
agent.md
VISION.md
```

---

# 4. Payment Assignment

Payment-specific authentication/authorization interactions remain compatible with:

**Phase Group H**

for payment implementation.

Do not select a payment provider or implement payment authentication mechanisms here.

---

# 5. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

This includes role values.

Canonical Version 1 roles:

```text id="zl4y3o"
CUSTOMER
STAFF
ADMIN
```

Do not silently add:

```text id="h9pg7e"
MANAGER
DELIVERY_AGENT
ACCOUNTANT
SUPPORT
```

in Version 1.

Additional roles require explicit compatibility/business review.

---

# 6. Human Actor Model

The project has three authenticated human actor types:

```text id="0a8k9a"
CUSTOMER
STAFF
ADMIN
```

They share the authentication system conceptually but have different authorization scopes.

---

# 7. Customer — Primary User

The Customer is the most important actor in the platform.

Customers have:

```text id="6g5jbl"
less administrative permission
+
maximum customer-facing flexibility
+
full ownership of their own account
```

The customer should be able to:

```text id="bzoeyc"
register
login
logout
manage eligible profile data
browse products
search
view categories
view product details
submit made-to-order requests
submit enquiries
add purchasable products to cart
checkout
place orders
choose pickup/delivery
track own orders
cancel eligible orders
receive/view own notifications
```

Subject to the business rules already approved.

---

# 8. Customer Account Ownership

This is a **non-negotiable security rule**:

> A customer's account belongs to that customer.

Staff must not have ordinary operational permission to:

* browse the customer's account as if they were the customer;
* restrict the customer's ability to browse;
* restrict the customer's ability to place legitimate orders;
* alter the customer's profile arbitrarily;
* access customer credentials;
* impersonate the customer;
* cancel customer access merely for operational convenience.

Admin authority must also be carefully bounded by legitimate account/security operations rather than unrestricted customer impersonation.

---

# 9. Staff Role

Staff are operational users.

Their primary purpose is to operate the commerce workflow:

```text id="4m5uw0"
receive orders
process orders
manage normal ecommerce operations
handle operational notifications
manage appropriate catalog/inventory/order operations
handle customer requests/enquiries
```

Staff are **not customer-account owners**.

Staff must not inherit customer privileges simply because they can perform operational tasks.

---

# 10. Admin Role

Admins have the highest administrative authority within the application.

Admins are responsible for:

```text id="bskd3p"
staff approval
staff management
administrative configuration
high-level operational management
authorized customer/account administration
```

The exact authorization matrix belongs to the later authorization phase.

This phase establishes only the hierarchy:

```text id="qzvpkv"
CUSTOMER
    ↓
limited administrative permission

STAFF
    ↓
operational permission

ADMIN
    ↓
highest administrative permission
```

But this does **not** mean:

```text id="xqf6f0"
ADMIN
→ unrestricted ability to impersonate any customer
```

Security-sensitive customer ownership must remain explicit.

---

# 11. Role Hierarchy Is Not Customer Ownership Hierarchy

Do not model:

```text id="7e4bls"
ADMIN "owns" Customer account
STAFF "owns" Customer account
```

Instead:

```text id="g2ehfr"
Customer
→ owns own account

Staff/Admin
→ may have authorized administrative access to specific operational data
```

This distinction is critical.

---

# 12. Registration

Customers must be allowed to register.

Staff and Admin accounts should **not** use unrestricted public customer registration.

Recommended:

```text id="8cu74y"
Customer
→ public registration

Staff
→ approved/invited administrative creation

Admin
→ administrative creation / bootstrap process
```

The exact staff provisioning mechanism will be defined later.

---

# 13. Public Registration

Customer registration must not require staff intervention.

The normal customer flow is:

```text id="u2tjl3"
Visitor
   ↓
Register
   ↓
Customer account created
   ↓
Authenticate
   ↓
Customer experience
```

Do not create a workflow where staff must approve every customer registration unless a later business requirement explicitly introduces one.

---

# 14. Customer Login

Customers can log in through:

```text id="5x1m7u"
Next.js website
Flutter mobile application
```

Both clients authenticate against the same Laravel backend.

Conceptually:

```text id="j8dnsg"
Next.js ─────┐
             ├──→ Laravel Authentication
Flutter ─────┘
```

Customer identity and account remain shared across both applications.

---

# 15. Staff Login

Staff authenticate through the same central authentication system but receive a `STAFF` role.

Staff access must be subject to authorization after authentication.

Authentication answers:

> Is this person really this staff account?

Authorization answers:

> What is this staff member allowed to do?

Do not collapse those concepts.

---

# 16. Admin Login

Admins authenticate as `ADMIN`.

Admin status must be assigned through trusted administrative processes.

Do not allow a customer to self-submit:

```json
{
  "role": "ADMIN"
}
```

and become an administrator.

---

# 17. Role Assignment

Role assignment is server-controlled.

Customers cannot promote themselves.

Staff cannot promote themselves.

A staff member cannot grant themselves Admin.

Only authorized administrative operations may change role assignments.

Detailed authorization is later.

---

# 18. Admin Approval of Staff

The approved business rule is:

> **Admins are the people who approve staff.**

Therefore staff onboarding must include an administrative approval concept.

Conceptually:

```text id="9w2fpm"
Staff candidate
      ↓
Admin review
      ↓
Approved
      ↓
Staff account activated
```

or, if the final implementation uses invitation-first provisioning:

```text id="2gh0c9"
Admin creates/invites staff
      ↓
Staff activates account
```

Choose the exact onboarding workflow in the later staff/authorization phase.

The important invariant is:

> A person cannot simply self-register as Staff through the public customer registration mechanism.

---

# 19. Staff Approval State

Staff accounts may conceptually require a lifecycle such as:

```text id="ghvl4h"
PENDING
ACTIVE
SUSPENDED
DISABLED
```

However, the exact account-status enum should be defined in the later authorization/account-management phase.

Do not add new Version 1 public role enums here.

---

# 20. Customer Account Status

Customers may also require account state.

However:

> Staff must not be given ordinary permission to restrict customer account activity.

If a customer account needs security/admin intervention, the action belongs to authorized administrative processes.

The exact customer account-status model is deferred to the account/authorization phase.

---

# 21. Customer Ordering Independence

A particularly important business rule:

> Staff cannot restrict or disable a customer's ability to browse or order simply because the customer account exists in the system.

Therefore:

```text id="x2fq1j"
Customer
    ↓
can browse public catalog
```

regardless of staff access.

And for a legitimate active customer:

```text id="gknv6o"
Customer
    ↓
can place orders
```

subject to ordinary business conditions such as:

* product availability;
* checkout authentication;
* payment requirements;
* order rules.

Do not introduce staff-controlled "can_order" switches without explicit business approval.

---

# 22. Browsing Does Not Require Authentication

Authentication remains unnecessary for:

```text id="3x1c7w"
products
categories
search
product details
prices
availability
public content
```

This remains an explicit Version 1 requirement.

---

# 23. Authentication Is Required for Checkout

The authentication boundary is:

```text id="pl7i7r"
Browse
→ no authentication

Cart interaction
→ according to finalized cart policy

Checkout
→ authentication REQUIRED
```

The backend must enforce this.

---

# 24. Anonymous Requests and Enquiries

Authentication is not required to submit:

```text id="8u1qhv"
Made-to-order Request
General Enquiry
```

They can be associated with a User when authenticated.

Otherwise:

```text id="7c9e9w"
User = none
```

with the required contact information.

---

# 25. Customer Account and Orders

An authenticated customer's orders must be associated with that customer's account.

The customer can retrieve:

```text id="zv7q94"
own orders
own order details
own tracking
```

but not arbitrary customer orders.

---

# 26. Customer Account and Requests

Authenticated requests may be associated with the customer.

This enables account-level history later:

```text id="0q6l1c"
My Requests
```

Anonymous requests remain valid without an account.

---

# 27. Customer Account and Enquiries

Same principle:

```text id="0b9k5e"
Authenticated
→ own enquiries

Anonymous
→ enquiry without User
```

---

# 28. Customer Account and Notifications

Notifications are private to the receiving customer.

A customer must not be able to request another customer's notifications.

Staff operational notifications are a separate authorization/use-case concern.

---

# 29. Session/Token Strategy

The authentication system serves different clients.

Conceptually:

```text id="8t4ts9"
Next.js Website
→ first-party authenticated browser session

Flutter App
→ authenticated API credential/token

Admin
→ authenticated administrative session/client
```

The exact Laravel/Sanctum implementation is later.

Do not implement tokens here.

---

# 30. Browser Authentication

For the Next.js website, prefer a secure first-party authentication approach appropriate for a server-rendered web application.

The website should not expose long-lived authentication secrets to client-side JavaScript unnecessarily.

The exact cookie/session implementation belongs to the Laravel/Next.js integration phase.

---

# 31. Flutter Authentication

Flutter must authenticate against the same Laravel identity system.

A Flutter customer should be recognized as the same customer when using:

```text id="xj6hvb"
website
```

and:

```text id="8f8dhk"
mobile application
```

The customer must not have separate duplicated accounts simply because they use two clients.

---

# 32. Cross-Platform Identity

Example:

```text id="5mno09"
Customer registers on website
       ↓
Customer opens Flutter app
       ↓
Customer logs in with same account
       ↓
Own orders/wishlist/profile/etc. are available
```

The exact feature availability depends on later contracts, but identity must be shared.

---

# 33. Logout

All authenticated clients must have an explicit logout operation.

Logout must invalidate the applicable authenticated session/credential according to the authentication mechanism.

Do not treat simply deleting a frontend token as sufficient if the backend maintains revocable server-side authentication state.

The exact mechanism is later.

---

# 34. Multi-Device Sessions

Evaluate whether customers may be simultaneously logged in from:

```text id="kwdn9f"
website
phone
tablet
another browser
```

Recommended baseline:

> Multiple legitimate customer sessions are permitted.

Do not unnecessarily invalidate all other sessions when one device logs out.

Administrative/security controls for revoking individual sessions can be added later.

---

# 35. Staff Sessions

Staff may also use multiple approved sessions depending on operational requirements.

However, staff session handling should support stronger security controls than ordinary browsing where appropriate.

Exact policy is later.

---

# 36. Admin Sessions

Admin accounts are highly privileged.

The eventual implementation should support appropriate stronger security controls.

Examples to evaluate later:

```text id="t3ot7e"
shorter idle timeout
session revocation
MFA
device/session visibility
```

Do not implement these in Phase 1.17 unless already approved.

---

# 37. Password Security

Passwords must never be stored in plaintext.

The backend must use a modern password hashing strategy appropriate to the chosen Laravel version.

Do not implement the hashing mechanism in this phase.

The rule is:

> Authentication credentials are stored only in secure one-way password-hash form.

---

# 38. Password Reset

Password recovery must use a secure, time-limited, single-use recovery mechanism.

Do not send passwords.

Do not store raw password-reset secrets unnecessarily.

Conceptually:

```text id="8d39de"
Request password reset
       ↓
Secure reset mechanism
       ↓
Time-limited token
       ↓
Set new password
       ↓
Invalidate/reset token
```

The actual email delivery is deferred to:

**Phase Group R**

as previously approved.

---

# 39. Password Recovery Without Email in Version 1

Because real email delivery is deferred to Group R, decide how the authentication architecture behaves before email infrastructure exists.

Do not invent an insecure temporary mechanism such as:

```text id="uygq3k"
"send reset password in response"
```

The API contract should support secure password recovery, while delivery implementation remains deferred.

If Version 1 launches before email delivery, password recovery may remain operationally unavailable until Group R is implemented, provided this is explicitly documented.

Do not weaken security to make the feature appear available.

---

# 40. Email Verification

Evaluate whether customer email verification is required before:

```text id="sxppn8"
checkout
or
full account use
```

The authentication contract should establish the preferred secure approach.

Because actual email sending is deferred to Group R, implementation is also deferred.

Do not make unverified-email behavior an arbitrary frontend decision.

---

# 41. Recommended Verification Principle

Use:

```text id="s40uf2"
verified email
→ account attribute/state

verification process
→ secure time-limited mechanism
```

Do not treat:

```text id="nbkz5v"
email_verified = true
```

submitted by the client as authoritative.

---

# 42. Phone Verification

Do not automatically introduce SMS/OTP verification unless the business requires it.

Phone number may be important for:

```text id="yh7r6c"
orders
delivery
requests
enquiries
```

but that does not automatically make phone verification an authentication requirement.

Record phone verification as deferred unless explicitly required.

---

# 43. Customer Registration Data

Customer registration should collect only the minimum required account data.

Likely:

```text id="ufm5le"
name
email
phone
password
```

The exact mandatory/optional classification belongs to the detailed registration contract.

Do not require:

```text id="jcxw5f"
address book
delivery address
```

during basic registration.

Those are separate flows.

---

# 44. Staff Registration Data

Staff should not use the unrestricted public customer registration form.

Staff accounts are created/approved through an administrative process.

Exact implementation is later.

---

# 45. Admin Bootstrap

Admin account creation requires special handling.

There must always be a secure initial bootstrap process.

Do not allow public registration to choose:

```text id="3x7jbb"
role=ADMIN
```

The initial administrator may be created via controlled deployment/setup procedures or another approved secure process.

Do not implement bootstrap yet.

---

# 46. Role Information Exposure

The client may need to know its role.

For example:

```json id="fwk7du"
{
  "role": "CUSTOMER"
}
```

However, do not expose unnecessary internal authorization details.

For example, avoid returning:

```text id="ilpznp"
database permissions
internal policy names
security configuration
```

through the normal profile response.

---

# 47. Role Is Not Permission

A client must not interpret:

```text id="t6e2lh"
STAFF
```

as permission to do everything.

The backend evaluates:

```text id="zjw3ps"
role
+
resource
+
action
+
ownership
+
state
```

and determines authorization.

Detailed authorization is Phase 1.18.

---

# 48. Customer Ownership Rule

For customer-owned resources:

```text id="q2zjgr"
authenticated User
       ↓
resource belongs to user
       ↓
access allowed
```

Staff does not become owner simply because they can operationally manage the resource.

---

# 49. Staff Operational Access

Staff should be able to operate relevant commerce resources without receiving unrestricted access to customer account internals.

Examples:

```text id="q2ftc6"
Staff
→ orders
→ requests
→ enquiries
→ operational notifications
→ approved ecommerce operations
```

but not:

```text id="8s6d4t"
Customer password
Customer authentication secrets
Unrestricted customer account impersonation
```

---

# 50. Admin Administrative Access

Admins have broader access than Staff.

However, "highest permission" still means:

> highest authorized application permission—not a license to bypass security controls.

Admin actions must remain auditable where appropriate.

---

# 51. Staff Cannot Restrict Customer Shopping

Explicit invariant:

> Staff role must never be interpreted as authority to disable or restrict a customer's ordinary browsing or ordering capability.

Staff cannot:

```text id="fvb6j7"
disable product browsing for a customer
block legitimate checkout
change customer's role
change customer's password
lock customer account
impersonate customer
```

unless a later explicitly approved security/administrative policy gives Admin—not ordinary Staff—such capability.

---

# 52. Admin Customer Account Operations

Admin customer-account operations, if later required, must be explicitly defined.

Potential examples:

```text id="ys6xka"
review account
disable compromised account
force credential reset
revoke sessions
```

These should be security/admin operations, not casual customer management.

Do not define them as part of ordinary staff operations.

---

# 53. No Staff Customer Password Access

Absolute rule:

> Staff and Admin must never receive plaintext customer passwords.

The password hash itself should also not be included in API responses.

---

# 54. No Credential Exposure

Never return:

```text id="u1j97f"
password
password_hash
reset_token
session_token
refresh_secret
private authentication keys
```

in ordinary API resource responses.

---

# 55. Session Revocation

The authentication architecture should eventually allow invalidating sessions/tokens where necessary.

Examples:

```text id="gvi7zx"
customer chooses logout
admin security action
password change
compromised credential
```

The exact revocation behavior is later.

---

# 56. Password Change

An authenticated customer should eventually be able to change their password through a secure authenticated workflow.

Do not implement this as arbitrary:

```text id="ynh6c2"
PATCH /me
{
  "password": "..."
}
```

unless the later authentication contract explicitly defines secure credential-change semantics.

Treat credential changes as security-sensitive operations.

---

# 57. Role Change

Role changes are privileged operations.

Customer:

```text id="h9yav3"
cannot change own role
```

Staff:

```text id="i2vaxt"
cannot change own role
```

Admin:

```text id="x4n2p9"
may manage staff roles according to later authorization rules
```

Admin self-promotion to another role should also be considered carefully and audited.

---

# 58. Staff Approval Rule

The authorization model later must ensure:

```text id="8k7dr9"
only authorized Admin
→ approve staff
```

Staff cannot approve themselves.

Customers cannot approve staff.

---

# 59. Account Deletion

Do not automatically define hard-delete customer accounts.

Customer accounts may have associated:

```text id="8y42mt"
orders
payments
requests
enquiries
notifications
```

Historical business records must remain meaningful.

Therefore evaluate account deletion as a privacy/lifecycle operation rather than ordinary DELETE.

The exact account-deletion policy is later.

---

# 60. Anonymous-to-Authenticated Transition

The customer may begin as:

```text id="3u4y23"
anonymous visitor
```

then:

```text id="sbr9py"
register/login
```

and continue into authenticated commerce.

The architecture should support this transition without losing legitimate public browsing context.

The exact cart/session merge behavior is later.

---

# 61. Session Expiration

Authenticated sessions/credentials eventually need expiration rules.

Define conceptually:

```text id="7gfjtd"
session active
session expired
session revoked
```

The exact duration should be chosen during security implementation, based on:

```text customer convenience
staff privilege
admin privilege
platform security
```

Do not guess exact durations in this phase.

---

# 62. Authentication Failure

Repeated invalid authentication attempts should not reveal sensitive account existence details.

For example, avoid a response that clearly distinguishes:

```text id="2nqu0h"
email exists
```

from:

```text id="6i67oa"
email does not exist
```

unless explicitly justified.

This is especially important for login/password recovery security.

---

# 63. Password Recovery Enumeration Protection

Password recovery should ideally avoid revealing whether an email is registered.

Conceptual behavior:

```text id="xw2dvy"
"Request received."
```

rather than:

```text id="hzo3qq"
"This email does not exist."
```

The exact UX and API contract will be established in the authentication implementation phase.

---

# 64. Brute-Force Protection

Authentication endpoints should eventually have:

```text id="k1grfd"
rate limiting
failed-attempt protection
abuse detection
```

Do not implement here.

Record it as a security requirement for later backend work.

---

# 65. Customer Experience Principle

Do not introduce authentication barriers where the business has not approved them.

Especially:

```text id="c4r21k"
Browse → public
Search → public
Product detail → public
Category browsing → public
Request → anonymous allowed
Enquiry → anonymous allowed
Checkout → login required
```

The authentication architecture must respect this UX boundary.

---

# 66. Authentication and SEO

Authentication must not block public product/category pages.

The Next.js website must be able to render:

```text id="3vj3c7"
/products
/products/{slug}
/categories/{slug}
```

without customer login.

Do not put authentication middleware around public catalog routes merely because private routes exist in the same application.

---

# 67. Authentication and Admin

The public website and admin application may use different frontend entry points but share the same backend identity system.

Conceptually:

```text id="9z7ggk"
Next.js customer site
        ↓
Laravel auth

Admin Next.js
        ↓
Laravel auth

Flutter
        ↓
Laravel auth
```

Role/authorization determines access after identity is established.

---

# 68. Authentication and API Versioning

Authentication behavior is part of the Version 1 API contract.

Breaking changes include:

```text id="7e9ss5"
changing credential semantics
changing login response shape
changing authentication requirements
removing supported authentication flow
```

Such changes must follow the API versioning policy from Phase 1.8.

---

# 69. Authentication and Error Contract

Authentication failures must use the common error contract from Phase 1.16.

Examples:

```text id="0vq4v5"
AUTHENTICATION_REQUIRED
INVALID_CREDENTIALS
SESSION_EXPIRED
```

Do not create a separate authentication error envelope.

---

# 70. Authentication and Response Contract

Authentication endpoints must eventually use the common API response conventions.

Do not introduce:

```text id="7p3xqn"
auth_success
login_result
token_response
```

as an entirely separate envelope style.

The authentication payload can be specialized, but the response architecture remains consistent.

---

# 71. Authentication and Closed Roles

Role values must be canonical:

```text id="zbl8j0"
CUSTOMER
STAFF
ADMIN
```

Do not accept arbitrary client-supplied roles.

---

# 72. Authentication Threat Model

At minimum identify risks for later implementation:

```text id="c14v4g"
credential theft
credential stuffing
brute-force login
session theft
token leakage
account enumeration
privilege escalation
role tampering
session fixation
password-reset abuse
cross-account access
staff/admin impersonation
```

Do not solve these here; document them as security requirements.

---

# 73. Customer Security Priority

Because Customers are the primary users, authentication must be optimized for:

```text id="m5wplb"
simple registration
simple login
secure account ownership
cross-platform account access
low unnecessary friction
```

Do not sacrifice account security for convenience.

---

# 74. Staff Security Priority

Staff have operational privileges and therefore require stronger protection against:

```text id="4wqcwo"
account sharing
credential theft
unauthorized operations
role escalation
```

Exact controls are later.

---

# 75. Admin Security Priority

Admins are highly privileged.

The later security implementation should evaluate stronger controls such as:

```text id="o49tlq"
MFA
session visibility
forced logout
shorter session lifetimes
audit logging
```

These are recommendations to evaluate, not implementation requirements in Phase 1.17 unless separately approved.

---

# 76. Authentication Data Minimization

Only store authentication-related data that the system genuinely needs.

Avoid collecting unnecessary personal information during registration.

The platform should not require:

```text id="55ltwb"
full address
identity document
unnecessary demographic data
```

unless a real business requirement exists.

---

# 77. Authentication Event Logging

Later implementation should consider logging security events such as:

```text id="h2ar53"
login success
login failure
logout
password change
password reset
staff approval
role change
session revocation
```

Do not log passwords or tokens.

---

# 78. Auditability of Staff Approval

Because Admin approves staff, later implementation should preserve enough audit information to answer:

```text id="rb7w3s"
Who approved this staff account?
When?
What changed?
```

Do not implement the audit structure in this phase.

---

# 79. Authentication Lifecycle Summary

The conceptual model is:

### Customer

```text id="2ed4gl"
Visitor
  ↓
Register
  ↓
Customer account
  ↓
Login
  ↓
Authenticated customer
  ↓
Logout / session expiration
```

### Staff

```text id="5u8qnm"
Staff onboarding
  ↓
Admin approval
  ↓
Staff account
  ↓
Login
  ↓
Operational access
```

### Admin

```text id="a3nuqk"
Secure admin creation/bootstrap
  ↓
Admin account
  ↓
Login
  ↓
Administrative access
```

---

# 80. Authentication Boundary Matrix

Create a formal matrix:

| Capability                   | Anonymous | Customer |                      Staff |                                          Admin |
| ---------------------------- | --------: | -------: | -------------------------: | ---------------------------------------------: |
| Browse products              |       Yes |      Yes |                        Yes |                                            Yes |
| Search catalog               |       Yes |      Yes |                        Yes |                                            Yes |
| Register                     |       Yes |       No |                         No |                                             No |
| Login                        |       Yes |      Yes |                        Yes |                                            Yes |
| Logout                       |        No |      Yes |                        Yes |                                            Yes |
| Submit request               |       Yes |      Yes |   Operational access later |                       Operational access later |
| Submit enquiry               |       Yes |      Yes |   Operational access later |                       Operational access later |
| Checkout                     |        No |      Yes | Not as customer by default |                     Not as customer by default |
| View own orders              |        No |      Yes |                        N/A |                                            N/A |
| Operational order management |        No |       No |                        Yes |                                            Yes |
| Staff approval               |        No |       No |                         No |                                            Yes |
| Role management              |        No |       No |                         No |                                            Yes |
| Customer account ownership   |       N/A |      Own |                         No |                            Administrative only |
| Restrict customer ordering   |        No |       No |                     **No** | Only under explicitly approved security policy |

The final authorization matrix will be defined in Phase 1.18.

---

# 81. Important Interpretation of Staff Access

Staff receiving orders does **not** mean:

```text id="fztbto"
Staff becomes the customer
```

It means:

```text id="0wm0qz"
Staff
→ operationally processes customer order
```

The customer remains the owner of the customer relationship.

---

# 82. Important Interpretation of Admin Access

Admin having the highest role does **not** mean:

```text id="vv6jb8"
Admin can bypass every business invariant
```

For example, Admin should not casually modify:

```text id="wfg4gx"
historical purchase facts
payment confirmation
order history
```

unless a later explicit administrative policy permits a controlled correction.

---

# 83. Customer Flexibility Principle

The phrase:

> "Customer with less permission but best flexibility"

should be translated architecturally into:

```text id="2f2p2d"
Customer:
maximum access to customer-facing commerce functionality
minimum access to administrative controls
```

This means the customer gets a rich shopping experience without receiving operational/admin privileges.

---

# 84. Authentication vs Authorization

Phase 1.17 defines identity.

Phase 1.18 will define authorization.

Keep the boundary:

```text id="1wsj9x"
Authentication
→ Who are you?

Authorization
→ What may you do?
```

Do not put the full permission matrix into Phase 1.17.

---

# 85. Required Documentation Updates

Update:

### `docs/api/api-contract.md`

Add:

```text id="0q2xhl"
## Authentication Contract

### Actors
### Customer Registration
### Customer Login
### Staff Authentication
### Admin Authentication
### Session/Token Principles
### Logout
### Password Recovery
### Verification
### Role Identity
### Cross-Client Identity
### Authentication Requirements
### Error Integration
### Security Requirements
```

---

### `docs/api/api-conventions.md`

Add:

```text id="r1dxx2"
authentication boundaries
credential handling
session/token principles
authentication error conventions
security principles
```

---

### `docs/api/api-resources.md`

Document the conceptual authentication/account resources:

```text id="9eicv0"
User/Profile
Authentication
Session/credential representation
```

Do not define endpoints yet.

---

### `docs/domain/business-rules.md`

Add the approved business rules:

```text id="4niwn6"
Customers can self-register.
Checkout requires authentication.
Anonymous browsing is allowed.
Anonymous requests/enquiries are allowed.
Staff are approved by Admin.
Staff cannot restrict ordinary customer browsing/ordering.
Customer accounts belong to customers.
```

---

### `docs/decisions.md`

Record the important decisions.

Example:

```text id="j9b5xq"
### AUTH-001 — Three Human Actor Roles

Roles:
CUSTOMER, STAFF, ADMIN

### AUTH-002 — Customer Self-Registration

Customers may register without staff approval.

### AUTH-003 — Account Ownership

Customer accounts are explicitly owned by the respective customers.

### AUTH-004 — Staff Scope

Staff operate commerce workflows but do not control customer account ordering/browsing.

### AUTH-005 — Staff Approval

Admins approve staff.

### AUTH-006 — Checkout Authentication

Checkout requires an authenticated customer.

### AUTH-007 — Anonymous Public Catalog

Catalog browsing does not require authentication.
```

Use the project's existing decision-numbering convention if different.

---

### `AGENTS.md`

Add only permanent engineering principles, such as:

```text id="16z9cm"
Customer authentication/authorization must preserve account ownership.

Never allow a client to self-assign STAFF or ADMIN roles.

Authentication and authorization must be enforced server-side.

Frontend authentication state is never an authority for backend permissions.
```

---

# 86. Validation Checklist

### Actors

* [ ] CUSTOMER is defined.
* [ ] STAFF is defined.
* [ ] ADMIN is defined.
* [ ] Role values are CLOSED.

### Customers

* [ ] Customers can register.
* [ ] Customers can login.
* [ ] Customers can logout.
* [ ] Customer accounts belong to customers.
* [ ] Customer identity is shared across website and app.
* [ ] Customers can browse without login.
* [ ] Customers can order when business conditions allow.

### Anonymous users

* [ ] Anonymous browsing works.
* [ ] Anonymous search works.
* [ ] Anonymous product viewing works.
* [ ] Anonymous made-to-order request works.
* [ ] Anonymous enquiry works.
* [ ] Anonymous checkout is rejected.

### Staff

* [ ] Staff authenticate.
* [ ] Staff are approved by Admin.
* [ ] Staff have operational access.
* [ ] Staff cannot self-promote.
* [ ] Staff cannot assign themselves Admin.
* [ ] Staff cannot access customer credentials.
* [ ] Staff cannot impersonate customers.
* [ ] Staff cannot arbitrarily restrict customer browsing.
* [ ] Staff cannot arbitrarily restrict customer ordering.

### Admin

* [ ] Admin authentication is separate from public registration.
* [ ] Admin has highest application role.
* [ ] Admin controls staff approval.
* [ ] Admin role cannot be selected through customer registration.
* [ ] Admin access does not automatically mean unrestricted business-rule bypass.

### Security

* [ ] Passwords are never stored plaintext.
* [ ] Credentials are never exposed through API responses.
* [ ] Password reset uses secure time-limited mechanisms.
* [ ] Authentication failure responses avoid account enumeration.
* [ ] Brute-force protection is identified for later implementation.
* [ ] Session/token revocation is identified.
* [ ] Security event logging is identified.

### Cross-platform

* [ ] Website and Flutter use shared customer identity.
* [ ] Admin uses the same central identity system.
* [ ] Client applications cannot independently define roles.
* [ ] Authentication behavior is API-version compatible.

### Documentation

* [ ] Consolidated API documents updated.
* [ ] Domain rules updated.
* [ ] Central decisions updated.
* [ ] Permanent AGENTS.md rules updated where appropriate.
* [ ] No unnecessary authentication-specific permanent markdown files created.

---

# 87. Explicitly Out of Scope

Do NOT:

```text id="k5n8j3"
Implement Laravel authentication
Install/configure Sanctum
Create User model
Create migrations
Implement password hashing
Implement password reset
Implement email verification
Implement MFA
Create login controllers
Create authentication middleware
Create authorization policies
Create Next.js login pages
Create Flutter login screens
Create token storage code
Create session storage code
Create staff-admin UI
```

---

# 88. Definition of Done

Phase 1.17 is complete when:

1. The three actor types are formally defined.
2. Customer is explicitly established as the primary customer-facing actor.
3. Customer account ownership is explicit.
4. Customers can self-register.
5. Customers can authenticate.
6. Public browsing remains unauthenticated.
7. Checkout requires authentication.
8. Anonymous requests remain supported.
9. Anonymous enquiries remain supported.
10. Staff authentication is defined conceptually.
11. Staff cannot be self-created through customer registration.
12. Admin approval of staff is explicit.
13. Admin has the highest application role.
14. Staff cannot arbitrarily restrict customers from browsing or ordering.
15. Staff cannot access customer credentials.
16. Role assignment is server-controlled.
17. Cross-platform customer identity is shared between website and Flutter.
18. Password security principles are explicit.
19. Password recovery is secure in concept and email delivery remains deferred to Group R.
20. Authentication errors integrate with the common error contract.
21. Closed Version 1 role enums are preserved.
22. Payment remains assigned to Group H.
23. Consolidated documentation has been updated.
24. No implementation has been written.

---

# 89. STOP CONDITION — Mandatory

After updating:

```text id="bdx00v"
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md where appropriate
```

and completing the validation review:

**STOP.**

Do not implement authentication.

Do not configure Sanctum.

Do not create login/register endpoints.

Do not create Laravel middleware.

Do not build login screens.

Do not define detailed permissions yet.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.18 — Define Authorization and Permission Contract

That phase should take the three authenticated actors established here and define, resource by resource:

```text id="3a1z4e"
CUSTOMER ownership permissions
STAFF operational permissions
ADMIN administrative permissions
```

including the important rule that **Staff operate orders and normal ecommerce workflows but do not control customer account access or arbitrarily prevent customers from browsing or ordering**, while Admins approve staff and hold the highest administrative authority.
