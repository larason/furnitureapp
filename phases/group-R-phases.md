# Phase 18.6 — Request & Enquiry Notifications

**Project:** SL Furnitures
**Group:** R — Notifications
**Phase:** 18.6 — Request & Enquiry Notifications
**Status:** Ready for implementation
**Backend:** Laravel + MySQL
**Authentication:** Clerk + Laravel RBAC
**Email:** Resend via Laravel Mail
**Real-time:** Laravel Reverb
**Push infrastructure:** Firebase Cloud Messaging (FCM)
**Initial commerce mode:** REQUEST ONLY

## 1. Objective

Complete and verify production-oriented notification behavior for successful made-to-order furniture requests and general enquiries.

Do not implement a new notification system. Reuse, audit, complete, and harden the functionality already implemented in Phases 18.1–18.4.

The outcome must be a reliable operational notification workflow in which:

1. A visitor successfully submits a furniture request or enquiry.
2. Laravel durably commits the corresponding business resource.
3. The existing notification pipeline identifies eligible operational recipients.
4. Recipient-scoped logical notifications are created without duplication.
5. Eligible recipients receive their notifications through the existing authenticated notification API.
6. Resend delivers eligible operational email alerts.
7. Reverb signals changes to connected authorized recipients.
8. Failures in downstream notification channels never invalidate a successful request or enquiry.
9. Staff use the request/enquiry resource itself for operational action; notification read state is informational only.

The phase is complete only when existing behavior is audited, identified gaps are corrected, and end-to-end tests demonstrate the approved policy.

## 2. Project context

Existing completed phases:

- Phase 18.1 — Notification Domain.
- Phase 18.2 — Email Notifications.
- Phase 18.3 — In-App Notifications with Laravel Reverb.
- Phase 18.4 — Firebase Cloud Messaging infrastructure and authenticated customer device registration.

Phase 18.3 passed a real Clerk-authenticated Reverb smoke test.

Phase 18.4 completed its approved infrastructure scope. Laravel and Flutter test suites have returned to a green baseline:

- Laravel: 1,726 passed, 1 skipped, 0 failed.
- Flutter: 637 passed, 0 failed.

Preserve those results.

The initial production release is REQUEST ONLY. Order and payment notifications remain deferred.

The admin dashboard frontend will be implemented in a later group. This phase must not invent a temporary production-facing admin interface.

## 3. Required repository audit

Read these authorities before implementation:

- Root `AGENTS.md`, particularly Phase Group R.
- `docs/api/api-contract.md`, especially notification event and trigger policies.
- `docs/api/api-resources.md`.
- `docs/api/api-conventions.md`.
- `docs/api/openapi.yaml`.
- `docs/decisions.md`.
- Request and enquiry domain rules.
- `phases/phase-18.1-completion.md`.
- `phases/phase-18.2-completion.md`.
- `phases/phase-18.3-completion.md`.
- `phases/phase-18.4-completion.md`.

Inspect the actual Laravel code for:

- REQ-001 and ENQ-001 controllers and services.
- Request/enquiry persistence transactions.
- Notification model and resource serializer.
- Recipient resolution and permission checking.
- Notification creation jobs.
- Logical notification deduplication.
- Resend mail notification jobs and templates.
- Reverb broadcast jobs/events.
- Channel authorization.
- Existing email delivery attempts.
- Queue routing and retry behavior.
- Related tests and factories.

Produce a concise gap analysis before writing code:

| Capability | Existing implementation | Gap | Action |
|---|---|---|---|
| Request trigger | Audit | Identify | Reuse/fix |
| Enquiry trigger | Audit | Identify | Reuse/fix |
| Recipient filtering | Audit | Identify | Reuse/fix |
| Duplicate suppression | Audit | Identify | Reuse/fix |
| Email | Audit | Identify | Reuse/fix |
| Reverb | Audit | Identify | Reuse/fix |
| Security/privacy | Audit | Identify | Reuse/fix |
| Operational navigation target | Audit | Identify | Defer UI or correct metadata |

Do not recreate working components merely because Phase 18.6 is a separate roadmap item.

## 4. Frozen event and recipient policy

Implement exactly the following active business-event mapping.

### Event A — Made-to-order request

**Source operation:** `REQ-001`
**Business resource:** Furniture Request
**Notification type:** `NEW_MADE_TO_ORDER_REQUEST`

Recipients:

- Active STAFF with `requests.manage`.
- Active ADMIN with `requests.manage`.
- No inactive accounts.
- No accounts lacking the required permission.
- No ordinary CUSTOMER recipient.

ADMIN status alone is not sufficient; the required operational permission must be present.

### Event B — General enquiry

**Source operation:** `ENQ-001`
**Business resource:** Enquiry
**Notification type:** `NEW_ENQUIRY`

Recipients:

- Active STAFF with `enquiries.manage`.
- Active ADMIN with `enquiries.manage`.
- No inactive accounts.
- No accounts lacking the required permission.
- No ordinary CUSTOMER recipient.

The permission sets must remain independent.

A user with only `requests.manage` must not receive NEW_ENQUIRY.

A user with only `enquiries.manage` must not receive NEW_MADE_TO_ORDER_REQUEST.

A user with both permissions is eligible for both.

### No automatic customer notifications

The requester does not receive a persistent NOT-001 notification simply because they submitted REQ-001 or ENQ-001.

Anonymous submissions have no authenticated customer notification recipient.

Do not create customer confirmation email behavior without explicit approval of the recipient and content policy.

Do not add new notification enum values.

## 5. Request submission integration

Inspect the existing request creation workflow.

A successful REQ-001 must:

1. Validate the request according to the frozen contract.
2. Persist the request with its authoritative business state.
3. Commit the database transaction successfully.
4. Schedule downstream notification processing safely after commitment.
5. Resolve current eligible operational recipients.
6. Create at most one logical notification per source/type/recipient.
7. Dispatch approved email and Reverb delivery actions independently.

### Important invariants

- Do not send a notification for a failed validation.
- Do not notify for a transaction that rolls back.
- Do not notify for a nonexistent or uncommitted request.
- Do not duplicate notification creation on job retry.
- Do not create duplicate notifications due to concurrent processing.
- Do not create extra notifications for attachment processing unless a separately approved business event exists.

Confirm that both authenticated and anonymous request creation use the same authoritative event semantics.

Do not require Clerk authentication for the public anonymous submission path.

Do not modify request business-state rules.

## 6. Enquiry submission integration

Apply the same guarantees to ENQ-001.

A successful enquiry must result in the approved NEW_ENQUIRY operational notifications.

Test both:

- Anonymous enquiry.
- Authenticated customer enquiry.

Verify:

- Only successful committed enquiries generate notifications.
- A rejected enquiry never generates notifications.
- Duplicate processing cannot create duplicate logical notifications.
- All eligible operational recipients receive independent recipient-scoped records.
- Ineligible recipients receive none.

Do not convert an enquiry into a furniture request or order.

## 7. Recipient resolution

Recipient resolution must remain server-authoritative.

Use the existing Laravel role and permission system, not hard-coded email addresses or client-provided recipient lists.

Requirements:

- Use current active local users.
- Check actual required permissions.
- Deduplicate users with multiple qualifying roles or permission assignments.
- Avoid N+1 queries where reasonable.
- Never select ordinary customers as operational recipients.
- Exclude disabled or inactive users.
- Maintain independent notification records for each eligible recipient.
- Preserve existing STAFF/ADMIN authorization boundaries.

### Dynamic permissions

Review how role changes between source submission, queued processing, and delivery affect eligibility.

Preserve the existing approved creation-time recipient policy.

For each outbound channel, document the point at which account state and permissions are checked.

If an account becomes inactive before an unsent email is delivered, assess whether a fresh eligibility check is necessary to prevent sensitive operational alerts from reaching a former staff member.

Do not change established policy silently. Report any conflict requiring a human decision.

## 8. Durable notification creation

Reuse the existing Notification model and uniqueness constraints.

A notification must be recipient-scoped and linked to the originating resource.

Required properties include:

- Server-generated opaque notification ID.
- Recipient local user ID.
- Frozen notification type.
- Safe human-readable title.
- Safe human-readable message.
- Structured source.
- Structured target.
- Creation timestamp.
- Independent `read_at` state.

The notification is an alert, not a copy of the entire business record.

### Idempotency

Use the existing source/type/recipient uniqueness rule.

Re-running notification processing must not create duplicate records or trigger duplicate downstream work unnecessarily.

Review whether the existing design can recover from a crash between business commit and job dispatch. A Laravel `afterCommit` callback alone is not a fully durable outbox.

Do not introduce a new outbox or distributed event bus automatically.

If a material crash-loss gap exists, present a bounded, documented reliability improvement and any schema implications for approval.

## 9. Notification content policy

Operational notifications must be concise and privacy-safe.

Illustrative request title:

"New furniture request"

Illustrative enquiry title:

"New customer enquiry"

The content may identify the event and include a safe reference if supported by the existing notification representation.

Do not put the following in email subject lines, Reverb payloads, or general notification previews without a separately approved need:

- Full enquiry messages.
- Customer private contact details.
- Private attachment URLs.
- Authentication credentials.
- Raw internal notes.
- Complete furniture specifications.
- Private documents.
- Internal database identifiers.
- Unnecessary personal information.

The staff member must open the authorized business resource to view complete information.

### Text handling

Any text derived from visitor submissions must be escaped safely in HTML email templates.

Avoid unsanitized HTML rendering.

Never interpolate customer-provided text into raw executable email markup.

## 10. Structured source and target

Preserve the existing notification source/target contract.

For a request:

- Source refers to the originating Furniture Request.
- Target refers to that same operational resource.

For an enquiry:

- Source refers to the originating Enquiry.
- Target refers to that Enquiry.

Use the frozen target vocabulary and opaque IDs exactly as defined by the authoritative API documents.

A notification target is not a bearer credential.

Possession of a target ID does not authorize resource access.

The future admin dashboard must still call the appropriate protected REQ/ENQ operational endpoint and pass Laravel authorization.

Do not create public request/enquiry detail URLs.

Do not implement the admin navigation UI in this phase.

## 11. Resend operational email integration

Reuse Phase 18.2 email delivery.

A successful approved event should enqueue the corresponding operational email for each eligible recipient according to the existing email policy.

Audit:

- Recipient email source.
- Eligibility checks.
- Sender configuration.
- Email subject.
- Email body.
- HTML and plain-text rendering where supported.
- Queue name.
- Retry/backoff behavior.
- Provider failure handling.
- Duplicate-send protection.
- Delivery-attempt persistence or existing logs.

Do not create another Resend client or a separate mail subsystem.

### Email privacy

- Do not send operational alerts to customer email addresses.
- Do not add CC/BCC to an unapproved shared mailbox.
- Do not expose all staff recipient emails to other recipients.
- Do not include raw private attachment links.
- Do not invent a production admin URL.
- Do not put secrets into logs.

### Failure behavior

If Resend fails:

- The request/enquiry remains successful.
- The durable notification remains.
- Reverb remains independent.
- The email job can retry according to existing bounded policy.
- Failure is observable.

Do not retry the entire business submission in response to an email error.

## 12. Laravel Reverb integration

Reuse Phase 18.3.

Each eligible user has a private recipient-scoped channel:

`private-notifications.{localUserId}`

Preserve Clerk bearer-token authorization and channel ownership checks.

For each newly created durable notification, emit the existing minimal signal:

`notification.state-changed`

Do not create a second event name if the existing event already covers this behavior.

### Signal behavior

The signal informs a connected client that the notification state changed.

It must not act as the authoritative notification resource.

The client must reconcile through:

`GET /api/v1/me/notifications`

Preserve:

- Minimal event payload.
- Private recipient channel.
- After-commit dispatch.
- Independent broadcast queue.
- Bounded retry.
- No duplicate signal for already-existing notification creation.
- Cross-user channel denial.
- Authentication and reconnection safeguards.

### No production UI yet

The eventual Next.js/MUI admin dashboard will consume these signals.

Do not add a staff notification bell to the customer website.

Do not add a staff notification bell to the customer Flutter application.

Do not implement a temporary production-facing admin dashboard in this phase.

## 13. NOT-001 and NOT-002 behavior

The current API contract remains authoritative:

`GET /api/v1/me/notifications`

`PATCH /api/v1/me/notifications/{notification}`

Ensure newly created request/enquiry notifications work correctly with:

- Pagination.
- Filtering by unread state.
- Filtering by closed type.
- Stable ordering.
- Own-recipient access.
- Optional unread count.
- `read_at` and derived `is_read`.
- Ownership masking.

### Read state must not change business state

Marking NEW_ENQUIRY as read must not close the enquiry.

Marking NEW_MADE_TO_ORDER_REQUEST as read must not accept, reject, or process the request.

Likewise, staff processing a request does not automatically mark every staff member's notification as read.

Do not introduce a shared `handled` or `acknowledged` notification state without approval.

Business processing remains in the protected request/enquiry operational APIs.

## 14. Cross-channel consistency

For one committed event, the intended relationship is:

**One logical notification per eligible recipient**, with downstream delivery mechanisms operating against that same logical record.

Verify that:

- Email failure does not remove the in-app record.
- Reverb failure does not remove the in-app record.
- A Reverb reconnect does not create a new notification.
- Resend retry does not create another notification.
- Notification read-state changes do not send duplicate creation emails.
- All channels use the same event/source association.
- Channel-specific attempts do not appear as duplicate NOT-001 resources.

If the current delivery-attempt model differs, preserve its already approved behavior and document the exact relationship.

Do not add email, Reverb, or FCM delivery metadata to public notification serialization without contract approval.

## 15. FCM restrictions

Phase 18.4 provided secure Android FCM infrastructure and device registration.

However, the registered Android application is CUSTOMER-facing.

The two events in this phase are STAFF operational notifications.

Therefore:

- Do not send NEW_MADE_TO_ORDER_REQUEST to customer devices.
- Do not send NEW_ENQUIRY to customer devices.
- Do not query customer device tokens for staff operational recipients.
- Do not build an admin Android application.
- Do not activate customer push business-event fan-out.
- Keep `NOTIFICATION_PUSH_ENABLED=false` unless a separate approved use case exists.
- Keep device registration controlled by its independent existing configuration flag.

Do not modify the approved device-registration API.

FCM must remain unaffected by this phase.

## 16. Request/enquiry lifecycle boundaries

Only the existing approved creation events are in scope.

Do not introduce alerts for:

- Request status changes.
- Enquiry closure.
- Staff reassignment.
- Request acceptance.
- Request rejection.
- New attachment.
- Staff internal note changes.
- Quote generation.
- Customer follow-up.
- Order creation.

These may become useful future events, but they are not automatically permitted by the frozen event registry.

If the user wants these behaviors later, they require an explicit event, type, recipient, and delivery-channel decision.

## 17. Delivery reliability

Review and strengthen the existing pipeline only where necessary.

### Queue separation

Ensure that the existing queues continue to isolate work appropriately:

- Core request/enquiry persistence.
- Operational notification creation.
- Resend email delivery.
- Reverb broadcasting.

Do not change established queue names without a justified reason.

### Retry behavior

Retries must be bounded and idempotent.

Do not allow:

- Infinite retries.
- Recursive dispatch loops.
- Duplicate logical notifications.
- Provider errors to roll back committed business data.
- Failed email to block Reverb.
- Failed Reverb to block email.
- Sensitive payloads in failed-job logs.

### Observability

Ensure there is safe evidence for:

- Event source.
- Notification creation outcome.
- Recipient counts.
- Delivery queue failures.
- Retry exhaustion.
- Correlation/request identifier where supported.

Use identifiers rather than personal information in routine operational logs.

## 18. Authorization and abuse resistance

Verify the following:

1. Anonymous visitors can submit permitted requests/enquiries.
2. They cannot read operational notifications.
3. CUSTOMER cannot subscribe to another user's staff channel.
4. CUSTOMER cannot fetch staff notifications.
5. Inactive STAFF cannot receive newly created operational notifications.
6. Unauthorized ADMIN cannot receive operational notifications solely because of role.
7. STAFF lacking `requests.manage` is excluded from request events.
8. STAFF lacking `enquiries.manage` is excluded from enquiry events.
9. Only the notification's recipient may modify its read state.
10. Request/enquiry target metadata does not bypass resource authorization.
11. No arbitrary client field can select a notification recipient.
12. No user input can select a notification type.
13. No customer submission can directly generate a staff email without passing the normal validated business workflow.
14. Replayed processing cannot duplicate logical notifications.
15. Rate limits and anti-abuse protections on public submissions remain unchanged.

Do not weaken Clerk authentication or permission middleware to simplify tests.

## 19. Automated testing

Expand tests only where coverage is missing.

### Event creation

- Successful authenticated request produces correct staff notifications.
- Successful anonymous request produces correct staff notifications.
- Successful authenticated enquiry produces correct staff notifications.
- Successful anonymous enquiry produces correct staff notifications.
- Invalid request produces none.
- Invalid enquiry produces none.
- Rolled-back request produces none.
- Rolled-back enquiry produces none.

### Recipients

- Eligible STAFF receives the correct notification.
- Eligible ADMIN with permission receives it.
- ADMIN without permission does not.
- STAFF with only requests permission receives only request notifications.
- STAFF with only enquiries permission receives only enquiry notifications.
- STAFF with both receives both.
- Inactive operational user receives none.
- CUSTOMER receives none.
- Duplicate role/permission assignments do not duplicate recipient records.

### Idempotency

- Retrying the source notification job does not create duplicates.
- Concurrent processing preserves uniqueness.
- Duplicate creation does not generate an extra Reverb creation signal.
- Email retries do not create additional logical notifications.

### Email

- Request event uses correct email content.
- Enquiry event uses correct email content.
- Mail goes only to eligible operational recipients.
- Unsafe private fields are absent.
- Customer input is escaped.
- Resend failure does not affect the business resource.
- Retried email delivery preserves idempotency.

### Reverb

- Correct private recipient channel.
- Correct existing signal name.
- Minimal payload.
- No cross-recipient broadcast.
- No notification before durable persistence.
- Broadcast failure is isolated.
- Reconnection works through NOT-001 reconciliation.

### API and lifecycle

- Own notification can be listed.
- Cross-user notification cannot be read or modified.
- Read/unread changes are independently scoped.
- Read state does not modify request/enquiry business status.
- Filtering and pagination remain correct.
- Frozen response shapes remain unchanged.

### Regression

- Existing device-registration tests pass.
- FCM infrastructure remains untouched.
- Resend tests pass.
- Reverb tests pass.
- Clerk verifier tests remain hermetic.
- Previously fixed Flutter accessibility tests remain green.
- No order/payment notification events are activated.

## 20. Controlled local integration test

After automated tests pass, perform or validate a real local integration smoke test.

Use the existing Clerk STAFF test account if safely available.

Test A — Furniture Request:

1. Submit a valid REQ-001 through the normal customer website or approved test client.
2. Confirm the source request is persisted.
3. Confirm one logical notification is created for each eligible recipient.
4. Confirm expected email delivery behavior.
5. Confirm the authorized staff Reverb subscriber receives the existing signal.
6. Confirm NOT-001 lists the new notification.
7. Confirm NOT-002 can mark it read.
8. Confirm the request's business status remains unchanged.

Test B — Enquiry:

Repeat for ENQ-001.

Test C — Negative authorization:

Verify a separate CUSTOMER or unauthorized operational identity cannot access the notification or subscribe to the recipient's channel.

Test D — Failure isolation:

Use supported testing or fault injection to confirm a notification-provider failure does not undo a committed request/enquiry.

Avoid sending uncontrolled live emails to actual customers or staff.

If external Resend or Reverb configuration is unavailable, report precisely what was mocked and what was verified live.

Do not claim new live delivery evidence without running the actual test.

## 21. Admin dashboard handoff

The admin dashboard will be implemented in a later dedicated phase.

Prepare a short integration handoff describing:

- NOT-001 endpoint and notification response.
- NOT-002 read/unread mutation.
- Private Reverb channel naming.
- Clerk bearer authorization for `/broadcasting/auth`.
- Existing broadcast signal.
- Reconnect/reconciliation behavior.
- Recommended notification inbox behavior.
- Request/enquiry target resolution.
- Permission-based UI visibility.
- Read state versus handled business state.
- Empty, loading, failed, and unread-count states.

Do not create new admin UI components.

Do not add frontend-specific assumptions to Laravel domain services.

Document integration requirements for the future Next.js/MUI admin application.

## 22. Human decision boundaries

The approved recipient policy is already established:

- Active STAFF/ADMIN with `requests.manage` for request events.
- Active STAFF/ADMIN with `enquiries.manage` for enquiry events.

Do not reopen that policy without a demonstrated conflict in existing authoritative documents.

Request human approval only if a genuinely new decision is needed, such as:

- Changing the approved recipients.
- Introducing a shared operational mailbox.
- Sending customer confirmation emails.
- Adding request/enquiry status-change events.
- Adding new notification types.
- Implementing shared acknowledgement semantics.
- Introducing a new public API operation.
- Adding customer push fan-out.
- Introducing a durable outbox requiring a material schema/architecture change.

Continue independent work while awaiting any approval.

## 23. Quality and safety checks

Run relevant verification from the correct project directories.

Laravel:

```bash
composer validate
php artisan test --compact
vendor/bin/phpstan analyse
vendor/bin/pint --test
php artisan route:list
git diff --check
```

Flutter:

```bash
flutter analyze
flutter test
```

Run relevant Next.js verification if the phase changes customer submission wiring or tests.

Do not needlessly rebuild unrelated applications.

Do not run destructive migrations against a non-disposable database.

Any MySQL-specific integration test must follow the project's disposable database guard.

Preserve the test-isolation fix that prevents PHPUnit from inheriting uncontrolled Clerk values from the developer's `.env`.

A phase is not PASS if the agent introduced failing tests.

## 24. Documentation

Create:

`phases/phase-18.6-completion.md`

Record:

1. Initial audit and actual pre-existing implementation.
2. Identified gaps.
3. Precise code changes, if any.
4. REQ-001 and ENQ-001 event mapping.
5. Recipient permission rules.
6. Email delivery behavior.
7. Reverb behavior.
8. Notification creation and idempotency.
9. Failure isolation.
10. Security/privacy checks.
11. Automated test results.
12. Local integration test evidence.
13. Unchanged contracts.
14. Known limitations.
15. Future admin dashboard integration handoff.
16. Readiness for Phase 18.7.
17. Any human decision required.

Update `docs/decisions.md` only when an actual architectural decision is made.

Do not introduce unnecessary new documentation or duplicate existing contracts.

## 25. Explicit exclusions

Do not:

- Implement Phase 18.5 order notifications.
- Implement Phase 18.7 notification preferences.
- Build the Next.js admin dashboard.
- Add staff notifications to customer Flutter.
- Add staff notifications to the public Next.js website.
- Add new customer push events.
- Enable FCM fan-out.
- Add new notification types.
- Implement marketing notifications.
- Add SMS or Novu.
- Send customer confirmation emails without approval.
- Introduce unapproved public API paths.
- Change request/enquiry business transitions.
- Create a new notification domain or competing recipient model.
- Modify Coolify deployment infrastructure.

## 26. Definition of Done

Mark Phase 18.6 PASS when:

- Both REQ-001 and ENQ-001 produce the correct approved operational notifications.
- Anonymous and authenticated submissions are handled properly.
- Recipient eligibility is verified and permission-scoped.
- Logical notifications are persisted exactly once per eligible source/type/recipient.
- Resend integration operates correctly.
- Reverb integration uses authorized private channels.
- NOT-001 and NOT-002 remain correct.
- Read state remains independent of business state.
- Invalid or rolled-back submissions generate no notifications.
- Downstream channel failures do not invalidate business submissions.
- Duplicate processing remains safe.
- Existing FCM infrastructure remains unchanged and customer fan-out disabled.
- No frozen API changes are required, or any changes have received explicit approval.
- Automated tests pass.
- Any real integration testing is reported accurately.
- The eventual admin frontend has sufficient integration documentation.
- Completion record is updated.

Mark PARTIAL if a required implementation or meaningful live verification remains blocked.

Do not mark the phase incomplete merely because the future admin dashboard does not yet exist; its UI is explicitly outside this phase.

## 27. Final report

Provide:

**Status:** PASS / PARTIAL / FAIL

Then report:

- Existing functionality reused.
- New or changed files.
- Business event coverage.
- Recipients and permissions.
- Resend email results.
- Reverb delivery results.
- API behavior.
- Idempotency results.
- Security/privacy findings.
- Test suite results.
- Actual local smoke-test evidence.
- Deferred functionality.
- Remaining owner actions.
- Readiness for Phase 18.7 — Notification Preferences.

**Execute Phase 18.6 only. Reuse the working infrastructure, preserve the REQUEST ONLY business policy, and prioritize verified behavior over unnecessary new code.**
