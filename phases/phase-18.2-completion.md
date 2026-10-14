# Phase 18.2 Completion Report — Transactional Email Notifications

## Result

**PARTIAL — implementation complete; live delivery activation is blocked by sender/account approval.**

## Audit

- Laravel 13 already provides the native Symfony Mailer `resend` transport.
- `resend/resend-php` was added as the single required transport dependency; `resend/resend-laravel` was not added.
- The default queue is the database driver with `failed_jobs` persistence.
- Phase 18.1 creates durable recipient-scoped logical notifications through `CreateOperationalNotifications`.
- Recipient identity remains the local `User` record, and eligibility is rechecked at email-send time.
- No prior mailables, templates, delivery-attempt table, or email provider integration existed.

## Implementation

- Added `notification_email_deliveries` for internal EMAIL attempt bookkeeping.
- Added `EmailDeliveryStatus` and `EmailDelivery`.
- Added queued `SendOperationalNotificationEmail` processing.
- Added branded HTML and plain-text templates for:
  - `NEW_MADE_TO_ORDER_REQUEST`
  - `NEW_ENQUIRY`
- Added sender/reply-to and safe email-channel configuration in `config/notifications.php`.
- Email is disabled by default with `NOTIFICATION_EMAIL_ENABLED=false`.
- No customer acknowledgments, order emails, public endpoints, new notification types, Reverb, push, SMS, or marketing email were added.

## Recipient and security policy

Email recipients are the existing Phase 18.1 notification recipients only. At send time, the job requires:

- `account_state=ACTIVE`;
- `STAFF` or `ADMIN` role;
- the exact matching permission (`requests.manage` or `enquiries.manage`);
- a valid recipient email address.

Request/enquiry bodies, attachments, upload tokens, internal notes, customer addresses, and authentication data are excluded from email content. No admin CTA URL is generated because the admin request/enquiry routes are not yet established.

## Reliability

- One delivery workflow is unique per logical notification and EMAIL channel.
- Database row locking prevents concurrent workers from claiming the same pending attempt.
- Delivery attempts are bounded by `NOTIFICATION_EMAIL_MAX_ATTEMPTS` (default 3).
- Transport failures are retried with bounded queue release delays.
- Permanent configuration, recipient, and provider-rejection failures are recorded without retrying indefinitely.
- `ACCEPTED` means the provider transport accepted the message; delivery to the inbox is not claimed.
- A worker crash after provider acceptance and before local status persistence can still produce an uncertain-delivery duplicate on stale recovery. Provider-level idempotency is not exposed by Laravel's native transport and is not claimed here.

## Configuration and human actions

The owner must still approve and configure:

1. A verified Resend sending domain and DNS records.
2. An approved sender address on that domain.
3. Optional approved reply-to address/name.
4. `MAIL_MAILER=resend` and `NOTIFICATION_EMAIL_ENABLED=true` only in the intended environment.
5. A queue worker processing the database queue.
6. An approved test recipient and explicit permission for a live send.

The current local sender remains a placeholder, so live sending is intentionally not activated. The Resend MCP domain lookup could not be completed because the MCP account requires user authorization.

## Verification

Added tests cover transport-safe configuration, templates, escaping, plain text, permission revocation, disabled sending, duplicate jobs, and queue dispatch. Focused tests, Pint, and PHPStan must be run before phase sign-off.

## Deferred

- Customer acknowledgment emails.
- Resend webhook delivery confirmation.
- Reverb and mobile push delivery.
- Admin dashboard links until real authorized frontend routes exist.
