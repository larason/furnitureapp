# Phase 18.2 — Transactional Email Notifications with Laravel Mail and Resend

**Project:** SL Furnitures  
**Group:** R — Notifications  
**Phase:** 18.2 — Email Notifications  
**Backend:** Laravel 13 + MySQL  
**Email system:** Laravel Mail + Symfony Mailer + Resend  
**Production mode:** REQUEST ONLY  
**Prerequisite:** Phase 18.1 — Notification Domain (implemented)  
**Next phase:** 18.3 — In-App Notifications & Laravel Reverb

---

## 1. Objective

Implement reliable, queued transactional email delivery through Resend using the existing Phase 18.1 notification domain.

Do not create a separate email notification system.

The implementation must reuse the existing:

- Notification records.
- Event type registry.
- Recipient resolution.
- Role and permission checks.
- Event idempotency.
- Queue processing.
- Source/target relationships.
- Logging conventions.
- Frozen API contracts.

The email channel should be a downstream delivery mechanism for the authoritative logical notification.

**Primary objective:** When an approved furniture request or enquiry is successfully submitted, eligible operational users can receive a branded, secure, informative email notification.

Do not implement Reverb, Firebase Cloud Messaging, Novu, SMS, or order-related notifications in this phase.

## 2. Mandatory repository and contract audit

Before modifying code, inspect:

1. Root `AGENTS.md`.
2. Phase 18.1 completion report.
3. The entire Phase 18.1 notification implementation.
4. Notification creation services and queued listeners/jobs.
5. Notification recipient-resolution rules.
6. The existing unique source/type/recipient constraint.
7. NOT-001 and NOT-002 implementations.
8. Existing queue configuration, workers, retry policies, and failed-job storage.
9. Current Laravel mail configuration.
10. Existing mailables, notification classes, and email templates, if any.
11. `docs/api/api-contract.md §28`.
12. `docs/api/api-resources.md §7`.
13. `docs/api/api-conventions.md §28`.
14. `docs/api/openapi.yaml`.
15. `docs/domain/business-rules.md §13`.
16. `docs/decisions.md` notification ADRs.
17. Existing request and enquiry workflows.
18. Current Composer dependencies and lockfile.

Also review the current official documentation:

- https://laravel.com/docs/13.x/mail
- https://resend.com/docs/send-with-laravel
- https://github.com/resend/resend-laravel
- https://resend.com/docs/dashboard/domains/introduction

Use exact compatible package APIs and actual repository conventions.

### Audit deliverable

Briefly identify:

- Existing Laravel mail transport configuration.
- Existing queues.
- Existing notification dispatch points.
- Existing recipient identity/email source.
- Existing domain and template abstractions.
- Existing tests.
- Missing email-channel functionality.
- Human configuration needed.

Do not install duplicate email transports or rewrite working Phase 18.1 code.

## 3. Email transport selection

Use Laravel Mail with the Symfony Mailer-based Resend transport.

Preferred strategy:

Use the Laravel 13-supported native Resend mail driver with the official `resend/resend-php` SDK if the installed framework supports it.

Inspect the installed framework and documentation before choosing dependencies.

If a compatible native driver is already available, do not also install `resend/resend-laravel` unnecessarily.

Use `resend/resend-laravel` only if there is a demonstrated compatibility or integration reason to prefer it.

Do not install both implementations to solve the same problem.

### Configuration example

For Laravel's native Resend driver, verify the supported configuration and equivalent of:

```php
// config/mail.php

'default' => env('MAIL_MAILER', 'log'),

// Mailers
'resend' => [
    'transport' => 'resend',
],
```

```php
// config/services.php

'resend' => [
    'key' => env('RESEND_KEY'),
],
```

The exact configuration must match the installed driver. The official separate Resend Laravel package may instead expect `RESEND_API_KEY`.

Choose one canonical environment variable and document it consistently.

Never assume `RESEND_KEY` and `RESEND_API_KEY` are interchangeable without explicit configuration.

Do not hard-code API credentials.

### Environment

Support configuration for:

- Resend API key.
- Verified sender address.
- Sender display name.
- Optional reply-to address.
- Chosen mail transport.
- Queue connection.
- Sending-enabled flag if needed for safe rollout.

Add safe placeholders to `.env.example`.

Keep real values out of Git.

Development and testing must work without real Resend credentials.

## 4. Human intervention — Resend account setup

**MANDATORY OWNER CHECKPOINT**

If the project has no configured, verified Resend account, notify the project owner.

Provide instructions for:

1. Creating or signing into https://resend.com.
2. Selecting the correct Resend workspace.
3. Adding the owned email sending domain.
4. Obtaining the DNS records provided by Resend.
5. Adding the required DNS records at the domain's DNS provider.
6. Waiting for domain verification.
7. Creating an API key with minimum required permissions.
8. Selecting the production sender address.
9. Approving the display name and reply-to address.
10. Supplying credentials securely through environment or secret management.

Do not invent a domain, sender email address, or brand contact address.

Do not assume the website domain is already verified for email.

Do not assume the domain DNS is managed by the same provider as the website.

### Agent behavior

Pause live-delivery verification until these steps are completed.

Continue independently with testable mailables, templates, queue integration, and automated tests.

Never request the API key in source code, screenshots, logs, or public documentation.

Do not send production emails without explicit approval.

## 5. Active event scope

For the initial REQUEST ONLY launch, implement operational email delivery for:

### Event A

`NEW_MADE_TO_ORDER_REQUEST`

Source:

Successfully created made-to-order furniture request.

Recipients:

The eligible active users already resolved in Phase 18.1 through the appropriate requests-management permission.

### Event B

`NEW_ENQUIRY`

Source:

Successfully created general enquiry.

Recipients:

The eligible active users already resolved in Phase 18.1 through the appropriate enquiries-management permission.

### Reuse recipient decisions

Do not create a second recipient-selection policy for email.

Do not independently query all staff email addresses and send indiscriminately.

The Phase 18.1 recipient policy remains authoritative.

One approved notification recipient may have an associated email delivery attempt.

Do not send operational notifications to CUSTOMER accounts.

Do not automatically notify every ADMIN.

## 6. Customer acknowledgment emails

The frozen notification event matrix does not create customer in-app notifications for successful request/enquiry submissions.

Customer confirmation emails are a separate business decision.

**Do not activate them without owner approval.**

If the owner approves transactional acknowledgments, clarify:

- Should anonymous submitters receive confirmations?
- Should authenticated submitters receive confirmations?
- What wording is approved?
- Which destination email is authoritative?
- How should mistyped or attacker-supplied email addresses be handled?
- What rate limits and anti-abuse measures apply?

Do not email full enquiry/request content to an unverified address by default.

If approval is not available, implement only operational staff email notifications and document customer acknowledgments as deferred.

Do not invent new V1 notification enum values to implement confirmations.

## 7. Email delivery architecture

Preserve the separation between:

1. Business event.
2. Logical notification.
3. Recipient-scoped delivery attempt.
4. Provider transport result.

Suggested conceptual flow:

```text
REQ-001 / ENQ-001 committed
           |
           v
Phase 18.1 domain event
           |
           v
Logical notification persisted
           |
           v
Eligible recipient identified
           |
           v
Queued email delivery job
           |
           v
Laravel Mail / Symfony Mailer
           |
           v
Resend transport
```

Do not perform synchronous email transmission inside the customer request/enquiry HTTP transaction.

The submission must remain successful even if Resend is unavailable.

### Delivery bookkeeping

Inspect whether Phase 18.1 established a reusable delivery-attempt model.

If one exists, extend it.

Otherwise, introduce only the minimal internal persistence needed to track:

- Logical notification reference.
- Channel: EMAIL.
- Recipient reference.
- Delivery status.
- Attempt count.
- Last attempted timestamp.
- Provider message ID where returned.
- Safe error category.
- Relevant timestamps.

Do not expose this bookkeeping through the frozen NOT-001/NOT-002 API.

Do not create a second logical notification record for email delivery.

If schema changes are necessary, use new additive migrations and preserve existing notification rows.

Do not assume development databases are disposable.

## 8. Retry safety and email duplication

Email delivery is an external side effect.

The existing Phase 18.1 unique index prevents duplicate logical notifications, but email retries require additional safeguards.

### Requirements

- At most one pending logical email delivery workflow per notification/channel.
- Multiple workers must not independently claim the same pending delivery.
- Use database-level atomic claiming or another appropriate concurrency-safe mechanism.
- Use bounded retries.
- Honor provider rate-limit guidance where available.
- Retry transient failures such as connection failure, timeouts, and applicable provider/server errors.
- Do not blindly retry permanent failures such as invalid sender configuration, rejected recipient, or missing credentials.
- Use exponential backoff or the repository's established retry policy.
- Record permanent failures for operational review.
- Do not retry indefinitely.
- Do not re-send already confirmed delivered/submitted messages simply because a queue job is replayed.

### Important external-delivery limitation

If Resend accepts an email but the worker crashes before recording the provider result, an uncertain-delivery window can remain.

Evaluate the provider's currently supported idempotency mechanisms and whether the selected Laravel transport exposes them.

If provider-level idempotency is supported and appropriate, use a stable key derived from the logical email attempt.

If no such guarantee is available, document the duplicate-delivery limitation and choose an explicit safe retry policy.

Do not claim exactly-once email delivery based solely on a database unique index.

## 9. Email templates

Create two reusable, branded transactional email templates.

Use Laravel Mailables, Markdown mail templates, or Blade templates consistent with the existing codebase.

### Template A — New furniture request

Suggested subject:

`New furniture request received — SL Furnitures`

Suggested content:

- SL Furnitures branding.
- Clear notification heading.
- Request reference.
- Submission time.
- Minimal operational context.
- Link to the authorized admin request page, but only when a real safe route is established.
- A note that the recipient must sign in to view the request details.

### Template B — New enquiry

Suggested subject:

`New customer enquiry received — SL Furnitures`

Suggested content:

- SL Furnitures branding.
- Enquiry reference.
- Submission time.
- Minimal enquiry context.
- Link to the authorized admin enquiry page when a real route exists.
- Clear sign-in requirement for viewing private details.

### Critical routing rule

The admin frontend may not yet have implemented routes for requests and enquiries.

Do not invent links such as `/admin/requests/{id}` without verifying the real frontend route.

If the admin frontend is not ready, provide the reference and omit the direct CTA rather than linking to a broken or insecure page.

Never generate public access links that bypass Clerk and Laravel authorization.

### Design language

Follow SL Furnitures' established visual identity.

Reuse canonical colors and typography where email-client compatibility permits.

Email HTML needs its own compatibility-safe formatting, but it must not create a competing brand system.

Use a simple, elegant layout rather than a complex marketing template.

Provide a plain-text version as well as HTML.

### Content security

Avoid including:

- Full customer messages.
- Private attachments.
- Upload tokens.
- Customer passwords.
- Clerk authentication data.
- Internal staff notes.
- Raw API responses.
- Sensitive provider identifiers.
- Unverified commercial promises.

Treat names and product titles as untrusted and escape them correctly.

## 10. Sender identity and reply behavior

The production sender must belong to an approved verified domain.

Use a consistent display name such as SL Furnitures only after confirmation that it matches the business's chosen sender identity.

Do not hard-code `onboarding@resend.dev` or other test-only sender addresses into production code.

Do not set a customer-submitted email address as the sender.

For replies:

- Prefer an approved business reply-to address.
- Do not automatically expose private customer addresses in mail headers.
- Do not turn transactional notification emails into an unrestricted reply relay.

If the sender or reply-to identity is undecided, request the owner's choice before activating live delivery.

## 11. Queue integration

Use the existing Laravel queue infrastructure.

Verify:

- Queue connection.
- Queue worker process.
- Job class.
- Retry attempts.
- Timeout.
- Backoff.
- Failed-job logging.
- After-commit behavior.
- Queue isolation where useful.

Mail delivery must occur asynchronously.

### Transaction boundary

Never send an email before the source business transaction commits.

Phase 18.1's logical notification creation remains the authoritative trigger boundary.

If the mail dispatch depends on notification persistence, ensure the notification record is durably committed before the mail worker processes it.

### Important

Avoid dispatch chains in which a failure in email sending marks a successful furniture request as failed.

Do not use synchronous `Mail::send()` within REQ-001 or ENQ-001 controllers.

## 12. Provider error handling

Handle failures from:

- Missing API credentials.
- Unverified sender domain.
- Invalid sender.
- Invalid recipient.
- Resend rate limiting.
- Network failures.
- Provider timeouts.
- Provider temporary failures.
- Provider rejection.
- Exhausted retries.

Never leak raw provider errors to public request/enquiry clients.

Use sanitized structured logging.

Record sufficient diagnostic information for operational troubleshooting.

If the provider response reports acceptance, do not automatically claim the message was delivered to the recipient's inbox.

Distinguish:

- Queued.
- Submitted/accepted by provider.
- Delivered, when actually confirmed by supported provider events.
- Permanently failed.

Do not mark delivery on the basis of queue completion alone.

### Delivery status tracking

If verified provider delivery events are not implemented in this phase, represent the strongest status actually known.

Do not create webhook endpoints outside the frozen public API without reviewing the API compatibility and security policy.

A future provider webhook, if required, needs authenticated signature verification, replay protection, a clearly documented non-public integration boundary, and explicit review.

## 13. Security requirements

Enforce:

- Verified sender domain.
- API key stored securely.
- No secrets committed.
- No unauthorized recipients.
- No staff permission bypass.
- No confidential enquiry bodies in email.
- No public resource authorization bypass.
- No token leakage.
- No unescaped user content.
- No open redirects.
- No untrusted outbound URLs.
- Rate-limit-safe handling.
- Recipient eligibility checks as appropriate before actual send.
- Safe processing of deactivated or permission-revoked accounts.

Review the tradeoff between recipient eligibility determined at event time and eligibility at send time.

Follow the Phase 18.1 policy and document changes.

Do not allow a user who loses operational permission to receive a newly dispatched private notification email merely because a job remained queued.

## 14. Test requirements

Add deterministic tests for:

### Transport and configuration

1. Laravel mail configuration loads.
2. The chosen Resend driver is configured correctly.
3. No duplicate mail drivers are introduced.
4. Missing API key fails safely.
5. Unconfigured sender prevents unsafe live sends.
6. Development/test mailers require no Resend credentials.

### Event integration

7. NEW_MADE_TO_ORDER_REQUEST schedules an eligible email.
8. NEW_ENQUIRY schedules an eligible email.
9. Invalid request creates no email.
10. Invalid enquiry creates no email.
11. No email is scheduled before commit.
12. No unauthorized customer email is sent.
13. No order or delivery email is sent.
14. No extra logical notification is created solely for email.

### Recipient authorization

15. Active authorized STAFF receives an email.
16. STAFF without matching permission is excluded.
17. Inactive STAFF is excluded.
18. ADMIN inclusion follows Phase 18.1 permissions.
19. CUSTOMER cannot receive staff operational email.
20. Permission revocation is honored before send.
21. Empty eligible-recipient set does not fail the customer submission.

### Templates

22. Furniture request email renders correctly.
23. Enquiry email renders correctly.
24. HTML is escaped safely.
25. Plain-text alternatives exist.
26. Missing admin frontend routes do not produce fabricated links.
27. No private attachments or sensitive data appear in templates.
28. Brand presentation is consistent.

### Reliability

29. Duplicate jobs do not trigger unnecessary duplicate sends.
30. Concurrent workers cannot independently claim the same pending attempt.
31. Transient provider failures trigger bounded retries.
32. Permanent failures do not retry indefinitely.
33. Provider rate limits are handled appropriately.
34. Provider acceptance is not confused with confirmed inbox delivery.
35. Email delivery failure does not roll back a request.
36. Email delivery failure does not roll back an enquiry.
37. Uncertain-delivery limitations are documented.

### Regressions

38. NOT-001 remains compatible.
39. NOT-002 remains compatible.
40. Per-recipient read_at remains independent.
41. Existing notification unique constraint remains effective.
42. Phase 18.1 recipient policy remains authoritative.
43. The frozen OpenAPI specification is unchanged.

Use Laravel mail fakes, queue fakes, and controlled provider simulations as appropriate.

Be careful that mocked queue behavior still exercises the real delivery-attempt state machine in dedicated tests.

Do not claim to have verified Resend delivery with a mocked Laravel mailer.

## 15. Human intervention checkpoints

Notify the owner whenever a real external or business decision is needed.

### Checkpoint A — Domain verification

Ask the owner to configure the Resend sending domain and required DNS records.

Wait for verified status before production delivery tests.

### Checkpoint B — Credentials

Ask the owner to configure the API key through a secure local or deployment secret mechanism.

Do not ask them to commit the key.

### Checkpoint C — Sender identity

Ask the owner to approve:

- From address.
- Display name.
- Reply-to behavior.

### Checkpoint D — Customer acknowledgments

Ask whether customer confirmation emails should be implemented later.

Do not add them unless approved.

### Checkpoint E — Live delivery testing

Ask for:

- Approved test recipient email address.
- Permission to send actual test emails.
- Confirmation of target environment.

### Checkpoint F — Production activation

Before enabling live sending in production, confirm:

- Verified domain.
- Correct secret configuration.
- Queue workers running.
- Failure monitoring.
- Provider limits appropriate for expected volume.
- Approved recipients.
- No unexpected customer or order notifications.

Do not silently deploy or activate production email sending.

## 16. Documentation

Document:

- Exact transport implementation and dependency choices.
- Why the selected Laravel Resend driver was chosen.
- Environment variable names.
- Local development mail behavior.
- Sending-domain setup procedure.
- Email templates.
- Approved recipient policy.
- Queue job lifecycle.
- Retry strategy.
- Internal delivery-attempt persistence.
- Idempotency limitations.
- Provider failure handling.
- Manual setup steps.
- Live test evidence or blockers.
- Future Reverb/FCM boundaries.
- Confirmed deferrals.

Do not put production API keys in the completion report.

Update existing architecture documentation without rewriting the frozen notification API.

## 17. Verification

Run the project's existing backend checks.

At minimum:

```bash
composer validate
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
php artisan route:list
git diff --check
git diff --cached --check
```

Run targeted email, notification, request, and enquiry regression tests.

If migrations are added, test them only against approved disposable databases.

Follow the root AGENTS.md database safety guard.

Confirm:

- No new public API endpoints.
- No notification enum changes.
- No role/permission regressions.
- No accidental live email sends during automated tests.
- No secrets introduced into version control.

Report actual pass/fail results.

## 18. Explicit exclusions

Do not implement:

- Laravel Reverb.
- WebSocket channels.
- Firebase Cloud Messaging.
- Mobile device-token registration.
- Novu.
- SMS.
- Marketing campaigns.
- Newsletters.
- Promotional email.
- Order notifications.
- Payment notifications.
- Delivery notifications.
- Customer request-status notification types.
- Customer acknowledgment emails without approval.
- New public notification API endpoints.
- Admin dashboard UI.
- New request/enquiry lifecycle actions.
- A custom email-provider HTTP client.
- A second notification persistence system.

## 19. Definition of Done

Phase 18.2 is complete when:

- Resend integrates through the selected Laravel Mail/Symfony Mailer transport.
- Existing Phase 18.1 notifications trigger appropriate email workflows.
- Eligible recipients are taken from the authoritative permission-based resolution.
- Branded operational email templates exist.
- HTML and plain-text versions render.
- Mail is sent asynchronously.
- Sending failures cannot reverse successful business transactions.
- Bounded retry behavior is implemented and tested.
- Duplicate logical notifications do not automatically produce duplicate email workflows.
- Email delivery status is reported accurately.
- Provider credentials and sender configuration are secured.
- No frozen API changes are introduced.
- REQUEST ONLY scope is preserved.
- Automated tests pass.
- Live delivery has been verified where authorized and configured, or clearly recorded as an external setup blocker.
- Documentation and the phase completion report are updated.

## 20. Completion report

Return:

1. PASS, PARTIAL, or FAIL.
2. Existing mail infrastructure discovered.
3. Transport package selected and reason.
4. Files added/modified.
5. Email templates implemented.
6. Sender configuration.
7. Recipient eligibility behavior.
8. Delivery-attempt persistence.
9. Queue/retry/idempotency behavior.
10. Security review.
11. Tests added and results.
12. Real Resend delivery evidence, if available.
13. Human actions still required.
14. Known limitations.
15. Phase 18.3 readiness.

**Final instruction:** Implement only Phase 18.2. Use Laravel Mail, Symfony Mailer, and Resend without duplicating the notification domain. Request human intervention for account, DNS, credentials, and live delivery activation. Do not proceed to Phase 18.3 without the project owner's next instruction.