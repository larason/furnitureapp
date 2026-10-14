# SL Furnitures — Phase 18.7: Notification Preferences

**Project:** SL Furnitures
**Phase:** 18.7 — Notification Preferences
**Implementation approach:** Contract-first, backend-first, approval-gated
**Backend:** Laravel 13 + MariaDB 11.8
**Authentication:** Clerk
**Delivery:** MySQL/MariaDB notification records, Resend email, Laravel Reverb, Firebase Cloud Messaging
**Frontend consumers:** Future Next.js/MUI admin dashboard and Flutter customer app

## Status — CLOSED / IMPLEMENTATION DEFERRED (Owner decision, 2026-10-14)

The owner declined the proposed preference schema, API, and delivery-policy extension for the
current request-only release.

- Eligible active `STAFF`/`ADMIN` recipients must continue to receive mandatory
  `NEW_MADE_TO_ORDER_REQUEST` and `NEW_ENQUIRY` notifications through the existing durable inbox,
  Reverb synchronization, and Resend operational-email paths. Personal operational-email opt-out
  is prohibited.
- Customer push preferences are deferred until commerce workflows and their customer notification
  policies are separately approved. `NOTIFICATION_PUSH_ENABLED=false` and
  `NOTIFICATION_PUSH_REGISTRATION_ENABLED=false` remain unchanged.
- No `user_notification_preferences` table, preference fields, NPF endpoints, preference-delivery
  suppression, default backfill, UI, or alternative configuration mechanism is approved.
- `NOT-001`, `NOT-002`, recipient eligibility, existing delivery isolation, and the FCM device
  registration contract remain unchanged.

The proposal sections below are retained as historical review material only. They do not authorize
implementation. Any future customer notification preference requires a new policy and frozen API
contract review.

## 1. Objective

Design and implement a secure, extensible notification-preferences system that allows eligible users to control optional delivery channels without disabling required operational notifications.

Preserve the existing notification architecture established in Phases 18.1–18.6.

The system must distinguish:

- Durable in-app notifications.
- Reverb real-time synchronization signals.
- Resend operational email.
- FCM push delivery.
- Per-user preferences.
- Per-device registration and permission.
- Business-event eligibility.

A notification preference must not become an authorization mechanism or change the underlying business record.

**Mandatory gate:** Audit the frozen contract and propose the preference policy, endpoint definitions, and schema first. Do not implement an unapproved contract extension.

## 2. Verified baseline

Phase 18.6 is PASS.

Existing functionality:

- `NEW_MADE_TO_ORDER_REQUEST` for active STAFF/ADMIN with `requests.manage`.
- `NEW_ENQUIRY` for active STAFF/ADMIN with `enquiries.manage`.
- Durable recipient-scoped notification records.
- Independent notification read state.
- Resend operational email delivery.
- Reverb private recipient channels.
- `notification.state-changed` with minimal payload.
- NOT-001 recipient inbox.
- NOT-002 read/unread mutation.
- FCM device-registration architecture, with customer business-event push fan-out disabled.
- No customer notification for REQ-001 or ENQ-001.
- Order notifications deferred to Phase 18.5.

The verified live Reverb event was received on `private-notifications.4`, with payload containing only `notification_id` and `change`.

Do not rebuild these systems.

## 3. Mandatory audit

Read and follow:

- Root `AGENTS.md`.
- `docs/api/api-contract.md`, especially §17, §18, §28, §29, §31.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- `docs/decisions.md`.
- `phases/phase-18.1-completion.md`.
- `phases/phase-18.2-completion.md`.
- `phases/phase-18.3-completion.md`.
- `phases/phase-18.4-completion.md`.
- `phases/phase-18.6-completion.md`.
- Existing notification models, services, controllers, FormRequests, jobs, migrations, tests, and delivery records.
- Existing device-registration contract and FCM feature flags.
- Existing STAFF/ADMIN permissions.
- Existing email eligibility and retry rules.
- Existing notification source/target and privacy rules.

Check whether any notification-preference infrastructure already exists.

Produce a concise gap analysis before proposing changes.

## 4. Phase 18.7 approval checkpoint

The current frozen V1 contract does not establish a dedicated notification-preferences API.

Therefore, before implementation, prepare an owner decision proposal containing:

1. Exact preference categories and channels.
2. Mandatory versus optional behavior.
3. Defaults for existing and new accounts.
4. Proposed database schema.
5. Proposed API endpoint names, HTTP methods, and paths.
6. Request/response JSON examples.
7. Authentication and authorization rules.
8. Error responses and validation rules.
9. Backward compatibility.
10. Behavior of already queued notifications.
11. Behavior when preferences change concurrently with delivery.
12. Customer push opt-in semantics.
13. Interaction with device registration.
14. Interaction with Android notification permission.
15. Operational impact on STAFF/ADMIN notifications.
16. Migration and rollback strategy.
17. Automated test plan.

**STOP after this proposal and request owner approval before changing the frozen API contract, database schema, or delivery policy.**

Do not interpret the existence of Phase 18.7 on the roadmap as automatic approval for a specific API design.

## 5. Proposed preference policy for owner review

Use the following as the recommended starting policy, not as an already approved contract.

### A. STAFF/ADMIN — Durable in-app notifications

Operational notification creation remains mandatory for eligible recipients.

A STAFF/ADMIN user must not be able to disable the creation of `NEW_MADE_TO_ORDER_REQUEST` or `NEW_ENQUIRY` notifications through personal preferences.

Eligibility remains determined by:

- Active account.
- STAFF or ADMIN role.
- Exact required permission.
- Approved business-event policy.

Read/unread remains informational and independent of business processing.

### B. STAFF/ADMIN — Reverb

Reverb remains the real-time synchronization mechanism for the recipient's durable inbox.

Do not expose a preference that causes an operational user to lose the authoritative notification record.

If future UI notification sound or toast preferences are desired, treat those as presentation settings, not as disabling server-side notification creation.

Do not create new Reverb event types.

### C. STAFF/ADMIN — Email

Propose an optional per-user operational email setting.

Recommended default: `enabled`.

Disabling email would suppress future optional Resend deliveries for that recipient but would not suppress:

- Durable notification creation.
- Reverb synchronization.
- Inbox retrieval.
- Operational authorization.
- Business request/enquiry processing.

Whether staff email may be disabled is a business policy requiring explicit owner approval.

### D. CUSTOMER — In-app

Preserve the approved notification event registry.

Do not introduce customer notifications for REQ-001 or ENQ-001.

Do not activate deferred order notifications.

A customer preference must not generate a business notification for an event that has not been approved.

### E. CUSTOMER — Push

Propose an explicit user-level push preference, initially disabled unless the owner approves another default.

Distinguish:

1. Laravel user preference.
2. Firebase device-registration state.
3. Android operating-system notification permission.
4. Backend push feature flags.
5. Approved business-event eligibility.

All applicable gates must allow delivery.

An Android permission grant alone must not be treated as consent to every future business-event push notification.

A user-level preference alone must not activate customer push fan-out.

Do not change the existing FCM registration ownership or uniqueness policy.

### F. CUSTOMER — Email

Do not automatically introduce customer email confirmations, marketing emails, or subscription settings.

Customer email event types and delivery policies remain separately approval-gated.

### G. Marketing

Marketing notifications are outside this phase.

Do not add promotional email, SMS, advertising subscriptions, or campaign preferences.

## 6. Proposed backend model

Evaluate a dedicated user-scoped preference model.

Recommended characteristics:

- One authoritative preference configuration per local Laravel user.
- User identity derived from the authenticated Clerk principal.
- Server-defined channel keys.
- Strict boolean validation.
- Explicit defaults.
- No user-controlled recipient ID.
- No role mutation.
- No arbitrary JSON preference keys accepted.
- No unnecessary duplication of customer/staff profile data.
- Clear distinction between preferences and device installations.

Compare a normalized table against a constrained JSON-column design.

Recommend the simplest schema that preserves validation, migration safety, and future extensibility.

Do not create multiple conflicting sources of truth.

Use MariaDB 11.8-compatible migrations.

## 7. Proposed API design

Evaluate a dedicated authenticated preference resource under `/api/v1/me`.

For owner review, consider:

`GET /api/v1/me/notification-preferences`

`PATCH /api/v1/me/notification-preferences`

These are proposals, not approved endpoints.

Do not reuse NOT-003 or NOT-004 IDs: those identifiers have separate deferred meanings in the existing notification contract.

Propose distinct operation IDs and update the endpoint inventory only after approval.

Do not embed the full preference object into `GET /api/v1/me`.

### Required API properties

- Clerk bearer authentication.
- Recipient-owned preference access.
- Strict request allow-list.
- Unknown fields rejected.
- Deterministic validation errors.
- Consistent V1 response envelope.
- No cross-user preference modification.
- No client-controlled roles.
- Private, non-cacheable responses.
- Safe concurrent updates.
- Idempotent repeated setting of the same value.

Provide exact proposed schemas and examples before implementation.

## 8. Delivery-time enforcement

After approval, integrate preference checks at the correct delivery boundary.

For Resend:

1. Resolve the durable notification recipient.
2. Recheck active account and required permission.
3. Recheck the recipient's approved email preference immediately before sending.
4. Suppress email if disabled.
5. Preserve the durable notification.
6. Record the suppression outcome without misclassifying it as provider failure.
7. Do not retry intentionally suppressed email indefinitely.

Define how a preference change affects queued jobs.

Recommended semantics: a preference change affects deliveries that have not yet passed the final send-eligibility check.

A completed external provider submission cannot be retroactively undone.

Do not alter notification idempotency or source-event uniqueness.

## 9. Reverb behavior

Preserve:

- `private-notifications.{localUserId}`.
- Clerk-authorized channel subscriptions.
- Minimal `notification.state-changed` payload.
- NOT-001 reconciliation after signal or reconnect.
- Recipient isolation.
- Existing queue separation.

Do not make Reverb the authoritative source of preferences or notification state.

Do not expose preferences in the WebSocket payload.

## 10. Push behavior

Do not activate FCM business-event fan-out.

If approved, implement only the preference persistence and eligibility groundwork required for future push delivery.

Keep:

- `NOTIFICATION_PUSH_ENABLED=false`.
- `NOTIFICATION_PUSH_REGISTRATION_ENABLED=false`.

Do not:

- Invent customer push events.
- Enable push merely because a preference is true.
- Transfer device ownership.
- Delete historical device rows.
- Modify ACTIVE-only fingerprint uniqueness.
- Treat Android permission as a server-side preference update without a defined consent flow.

A future approved customer event must pass the server-side preference and device-eligibility checks before FCM delivery.

## 11. Account lifecycle

After approval, cover:

- First-time Laravel JIT provisioning.
- Existing accounts without preference rows.
- Existing CUSTOMER users.
- Existing STAFF users.
- Existing ADMIN users.
- Role changes.
- Account suspension.
- Account reactivation.
- Logout.
- Clerk account switching.
- Device token rotation.
- Multiple customer devices.

Defaults must be deterministic.

JIT provisioning must not depend on successful delivery-channel configuration.

Do not make notification preference creation a prerequisite for public catalog access.

## 12. Security and privacy

Ensure:

- Users can access only their own preferences.
- STAFF cannot edit another user's preferences.
- ADMIN does not automatically receive authority to change another user's preferences.
- Preferences cannot grant `requests.manage`, `enquiries.manage`, or notification permissions.
- Unknown channels and keys are rejected.
- Preferences do not expose device tokens.
- Preference responses do not expose provider credentials.
- No Clerk JWT is logged.
- No customer contact information is added to Reverb signals.
- No mass-assignment vulnerabilities.
- No preference setting can authorize access to a business record.

Retain the existing notification ownership masking rules.

## 13. Automated testing plan

After owner approval, implement tests for:

### API and persistence

1. Default preferences for a newly provisioned CUSTOMER.
2. Default preferences for STAFF.
3. Default preferences for ADMIN.
4. Existing users without preference rows.
5. Authenticated own-preference retrieval.
6. Valid partial update.
7. Repeated update idempotency.
8. Unknown preference key rejection.
9. Invalid type rejection.
10. Unauthenticated access rejection.
11. Cross-user modification prevention.
12. Concurrent update behavior.
13. Persistence across sessions and devices.
14. Account-role changes without unsafe preference escalation.

### Operational delivery

15. Staff email enabled → existing Resend job proceeds.
16. Staff email disabled → Resend suppressed.
17. Durable notification still created when email disabled.
18. Reverb signal remains available.
19. Recipient loses permission → no unauthorized email.
20. Recipient suspended → no unauthorized email.
21. Preference disabled while email queued → suppressed before send.
22. Preference enabled again → subsequent events eligible.
23. Previously delivered email is not resent merely because preferences change.
24. Existing delivery idempotency remains intact.
25. Failed provider attempts remain distinguishable from preference suppression.

### Customer push architecture

26. Customer push preference disabled by default.
27. Preference enabled does not bypass global FCM flags.
28. Preference enabled does not create new push events.
29. Inactive device remains ineligible.
30. Device owned by another customer remains inaccessible.
31. Token rotation preserves existing security invariants.
32. Logout cleanup remains correct.
33. Android OS permission is not confused with server-side consent.

### Regression

34. REQ-001 notification recipient policy unchanged.
35. ENQ-001 notification recipient policy unchanged.
36. NOT-001 unchanged.
37. NOT-002 unchanged.
38. Reverb channel authorization unchanged.
39. No CUSTOMER notifications for request/enquiry submission.
40. Business status independent of notification preferences.

Use the actual approved schema and policy when implementing these tests.

## 14. Integration verification

After implementation approval, perform controlled local tests:

**Test A — Email enabled**

Create a valid request/enquiry, process the queue, and verify an eligible STAFF/ADMIN recipient receives the expected operational email delivery attempt.

**Test B — Email disabled**

Disable email for the same authorized recipient, create another valid source event, and verify the durable notification exists but no Resend send is attempted.

**Test C — Reverb unaffected**

With email disabled, confirm the recipient still receives `notification.state-changed` and can retrieve the new notification through NOT-001.

**Test D — Preference persistence**

Sign out and back in with Clerk, then verify the approved preference remains stored in Laravel.

**Test E — Security**

Verify another authenticated user cannot modify the recipient's preferences.

Do not create unnecessary live email traffic or test against unapproved recipient addresses.

Record mocked versus real transport evidence separately.

## 15. Frontend handoff

Do not build the admin dashboard in this phase.

Document how the future Next.js/MUI admin dashboard should:

- Retrieve the current user's preferences.
- Render mandatory channels as non-editable.
- Render optional channels as toggles.
- Persist changes through the approved API.
- Handle loading, saving, failure, and retry states.
- Avoid optimistic success when the API rejects an update.
- Keep read/unread state separate from preferences.

For Flutter:

- Document future push-preference integration.
- Keep Android OS permission distinct from backend preference.
- Preserve device registration and logout behavior.
- Do not add a notification bell or customer notification events.

Frontend implementation should occur only when the relevant UI phase is approved.

## 16. Documentation

Update the relevant project documents only after the owner approves the design:

- `docs/api/api-contract.md`.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- `docs/decisions.md`.
- Relevant backend documentation.
- `phases/phase-18.7-completion.md`.

Record a clear architecture decision covering:

- Mandatory operational notifications.
- Optional delivery channels.
- Default preferences.
- API contract.
- Schema.
- Delivery-time enforcement.
- FCM consent boundaries.
- Queued delivery behavior.
- Security and migration decisions.

Do not silently rewrite previously frozen contract decisions.

## 17. Verification

After approval and implementation, run:

- Focused preference API tests.
- Notification delivery tests.
- Request/enquiry integration tests.
- Relevant Clerk/JIT provisioning tests.
- Relevant FCM registration tests.
- Full Laravel test suite.
- PHPStan Level 5.
- Pint.
- OpenAPI contract validation.
- MariaDB migration tests on a disposable database.
- `git diff --check`.

Run Flutter tests only if Flutter code is modified.

Do not execute destructive database operations against the development or production database.

## 18. Scope exclusions

Do not implement:

- Phase 18.5 order notifications.
- New customer notification event types.
- New customer email confirmation events.
- Marketing notifications.
- SMS delivery.
- Customer push business-event fan-out.
- A new notification service provider.
- The Next.js admin dashboard.
- A new Flutter notification center.
- Durable outbox architecture.
- Coolify deployment.
- Unapproved NOT-003/NOT-004 operations.
- Global notification-read or notification-deletion features.

## 19. Definition of Done

Phase 18.7 is complete only when:

1. The owner approves the notification-preference policy.
2. The owner approves any required V1 API contract extension.
3. Preference persistence is implemented safely.
4. Eligible users can retrieve and update approved preferences.
5. Mandatory operational notifications cannot be disabled.
6. Optional email delivery respects the approved preference.
7. Reverb and durable notification creation remain correct.
8. Customer push remains gated by existing restrictions.
9. Account and device isolation remain intact.
10. Automated tests pass.
11. Relevant live integration tests pass.
12. Documentation reflects the implemented behavior.
13. No unauthorized contract or business-event changes occur.

## 20. First response required from the agent

**Do not begin implementation immediately.**

First return:

1. Audit findings.
2. Existing preference-related code, if any.
3. Proposed preference policy.
4. Proposed database schema.
5. Proposed API operations and exact JSON contracts.
6. Default values.
7. Delivery-time enforcement design.
8. Security considerations.
9. Migration plan.
10. Decisions requiring owner approval.

Label this response:

**PHASE 18.7 — DESIGN PROPOSAL / AWAITING OWNER APPROVAL**

Wait for explicit approval before implementing.

After approval, implement in small, tested steps and produce `phases/phase-18.7-completion.md`.
