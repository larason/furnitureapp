# Phase 18.6 Completion Report — Request & Enquiry Notifications

## Status

**PASS — automated policy coverage, controlled Resend acceptance, Clerk-authenticated notification APIs, private-channel authorization, and live Reverb delivery are verified.**

## Audit and gap analysis

| Capability | Existing implementation | Gap | Action |
|---|---|---|---|
| Request trigger | `RequestController` dispatches `CreateOperationalNotifications` after REQ-001 persistence | No end-to-end creation-path coverage | Added coverage |
| Enquiry trigger | `EnquiryController` dispatches the same job after ENQ-001 persistence | No end-to-end creation-path coverage | Added coverage |
| Recipient filtering | Active STAFF/ADMIN plus exact Spatie permission check | None | Reused |
| Duplicate suppression | Unique `(recipient_user_id, type, source_type, source_id)` constraint and `insertOrIgnore` | None | Reused |
| Email | Existing recipient-scoped delivery records, eligibility recheck, bounded retry | None | Controlled Resend acceptance verified |
| Reverb | Existing private `notifications.{localUserId}` signal and Clerk channel authorization | None | Controlled live ENQ-001 delivery verified |
| Security/privacy | Minimal messages, opaque source/target IDs, no customer recipient or push fan-out | None | Reused |
| Operational navigation target | Frozen `REQUEST` / `ENQUIRY` metadata only | Admin UI is intentionally absent | Handoff documented below |

## Event policy verified

- REQ-001 creates `NEW_MADE_TO_ORDER_REQUEST` only for active STAFF/ADMIN users with `requests.manage`.
- ENQ-001 creates `NEW_ENQUIRY` only for active STAFF/ADMIN users with `enquiries.manage`.
- Recipient records are independent, recipient-scoped logical notifications. CUSTOMERS, inactive users, and users without the matching permission are excluded.
- Anonymous and authenticated customer submissions use the same server-controlled source/type mapping. Neither creates a customer notification.
- Validation failure does not persist a source resource or dispatch notification work.

## Existing delivery behavior reused

- Controllers dispatch only after the creation service returns from its persistence transaction. Failed creation cannot reach dispatch.
- `CreateOperationalNotifications` verifies that the source exists, resolves current eligible recipients, and uses the unique database constraint for idempotency. Existing rows are not rebroadcast.
- Each new logical notification independently queues `SendOperationalNotificationEmail`; email eligibility is rechecked immediately before delivery. The email job records attempts, has bounded retries, and does not affect the committed request or enquiry.
- `NotificationStateChanged` emits the existing minimal `notification.state-changed` event after commit on `private-notifications.{localUserId}`. Clients reconcile through NOT-001; the signal is not authoritative.
- FCM remains untouched and disabled for business-event fan-out. No staff notification is sent to customer devices.

## Dynamic eligibility and reliability boundary

Recipient eligibility is evaluated when the queued notification-creation job runs. Email repeats the active-role-and-exact-permission check immediately before sending. Reverb uses the durable notification recipient; private-channel authorization is evaluated when a client subscribes. A later role change does not terminate an already connected socket; this is the existing Phase 18.3 limitation.

There remains a bounded crash window between the committed source transaction and queue dispatch. `afterCommit` prevents pre-commit dispatch but is not a durable outbox. No outbox was added because that would require a material schema and architecture decision. The source resource remains the operational source of truth, and retry/reconciliation procedures should account for this window.

## Tests added

`backend/laravel/tests/Feature/RequestEnquiryNotificationIntegrationTest.php` verifies:

- anonymous and authenticated REQ-001;
- anonymous and authenticated ENQ-001;
- exact queued source/type mapping;
- recipient-scoped STAFF/ADMIN notifications and queued email delivery;
- no CUSTOMER notification; and
- no notification work for invalid public submissions.

Existing notification API, email, Reverb, permission, idempotency, and read-state coverage was retained. Read state remains independent from request/enquiry business state.

## Verification

- `php artisan test --compact tests/Feature/RequestEnquiryNotificationIntegrationTest.php` — 5 passed, 30 assertions.
- `php artisan test --compact tests/Feature/RequestEnquiryNotificationIntegrationTest.php tests/Feature/OperationalNotificationTest.php tests/Feature/OperationalNotificationEmailTest.php tests/Feature/NotificationApiTest.php tests/Feature/BroadcastChannelAuthorizationTest.php` — 38 passed, 144 assertions.
- `php artisan test --compact` — 1,751 passed, 13 skipped.
- `vendor/bin/phpstan analyse` — passed.
- `vendor/bin/pint --dirty --format agent` — passed.

### Controlled local email evidence — PASS (2026-10-14)

- The configured Resend sender and reply-to addresses were confirmed without exposing credentials.
- An authorized disposable active STAFF recipient with both operational permissions received a controlled anonymous REQ-001 notification through the normal creation, recipient-resolution, and email job pipeline.
- The email delivery row reached `ACCEPTED` after one attempt. This proves provider transport acceptance only; inbox placement is not claimed.
- The recipient was suspended immediately after the test. The source event produced three eligible-recipient logical notifications; only the explicitly approved recipient's email job was processed. The two other pending test-email jobs and the source retry job were cancelled without deleting durable notifications.
### Controlled Clerk-authenticated STAFF/ADMIN evidence — PARTIAL PASS (2026-10-14)

A real Clerk-verified identity (`user_3KeK…AW7Z`) was bootstrapped as the first Admin through
`php artisan admin:bootstrap <id> --confirm`, then verified as `ADMIN` only, `ACTIVE`, with a Staff
profile and all 21 `PermissionCatalog` permissions. A stale local RBAC seed missing
`notifications.read_operational` was corrected with `php artisan db:seed --class=RbacSeeder`; before
that correction NOT-001 returned 403 for every operational actor.

The owner then submitted real `REQ-3F0VY7P4KO`, `REQ-G8TUTX6AT8`, and `ENQ-EJF0YKZJJG`. Starting the
queue workers drained eight queued jobs, producing recipient-scoped notifications and Resend
deliveries. Delivered to a real Gmail recipient: four `ACCEPTED`. The unverified seeder
`@example.com` staff address was permanently rejected with `PROVIDER_REJECTION` and did not roll back
any business record or block other recipients. Queue ended with 0 pending and 0 failed jobs.

Browser-driven verification against that ADMIN, with durable state reconciled against the database:

| Check | Result |
|---|---|
| NOT-001 listing | `200`, total `4`, unread `4` — matched the database exactly |
| `Cache-Control` | `no-store, private` — never public |
| `?type=NEW_ENQUIRY` | `200`, total `1` |
| `?type=NEW_MADE_TO_ORDER_REQUEST` | `200`, total `3` |
| `/broadcasting/auth` own `private-notifications.4` | `200` |
| `/broadcasting/auth` foreign `private-notifications.1` | `403` |
| NOT-002 mark read | `200`, `is_read: true`, `read_at` persisted |
| NOT-002 repeat mark read | `200`, idempotent, timestamp preserved |
| NOT-002 `recipient_user_id` tampering | `422 INVALID_VALUE`, field `recipient_user_id` |
| Read state vs business state | `enq_5` still `OPEN`; all three requests still `SUBMITTED` |

Source and target resolved to the same opaque enquiry identifier, and notification metadata granted no
resource authority of its own.

### Controlled live Reverb delivery — PASS (2026-10-14)

Using the existing browser-authenticated ADMIN and existing Reverb/queue processes, a native WebSocket
client subscribed successfully to `private-notifications.4`. After an anonymous valid ENQ-001 submission,
the connected subscriber received the actual event below:

| Evidence | Result |
|---|---|
| Subscription | `succeeded` on `private-notifications.4` |
| Event name / channel | `notification.state-changed` / `private-notifications.4` |
| Event payload | `{ "notification_id": "not_g", "change": "created" }` |
| Corresponding source | `enq_6` (`ENQ-001`, business status still `OPEN`) |
| Durable notification | `not_g`, `NEW_ENQUIRY`, recipient local user `4`, source and target both `enq_6` |
| NOT-001 reconciliation | `200`; notification was found and its source/target matched `enq_6` |
| Queue outcome | 0 pending and 0 failed jobs after delivery |

The received event exposed only the approved two-key signal payload (`notification_id`, `change`): no
customer contact data, Clerk JWT, authorization signature, or other credential was logged or captured.
The browser code held the fresh Clerk token only in memory to call the existing channel-auth endpoint.
It did not print or persist that token or its channel-auth response. The signal reached the authorized
recipient channel; prior same-session verification showed a foreign `private-notifications.1` subscription
is denied with `403`, rather than being deliverable.

## Admin dashboard handoff

- Fetch the recipient's inbox with `GET /api/v1/me/notifications` (NOT-001), including unread filtering/count and normal pagination.
- Mutate only the recipient's informational read state with `PATCH /api/v1/me/notifications/{notification}` and `{ "read": true|false }` (NOT-002).
- Authenticate `/broadcasting/auth` with the current Clerk bearer token, subscribe only to `private-notifications.{localUserId}`, listen for `notification.state-changed`, then refetch NOT-001 after connection, reconnection, and every signal.
- Resolve `target.type` (`REQUEST` or `ENQUIRY`) and its opaque target ID through the corresponding protected operational API; target metadata grants no authority.
- Permission-based inbox visibility must match `requests.manage` and `enquiries.manage`. Read/unread is not request/enquiry handling; action occurs only through the protected operational resource APIs.
- Provide loading, empty, failed, unread-count, and reconnect/reconciliation states. Do not add a notification bell to customer applications.

## Unchanged scope and owner actions

- No API paths, resource schemas, enum values, request/enquiry state transitions, customer confirmations, order/payment events, notification preferences, FCM fan-out, or frontend admin UI changed.
- The controlled live Reverb verification created one anonymous ENQ-001 source (`enq_6`) and its durable
  recipient-scoped notification (`not_g`). It is retained as traceable local smoke-test evidence.
- Clerk session tokens issued to the browser live about 60 seconds, so any operational smoke test that crosses a chat or tool boundary must run inside that window or mint a token from an already-active session. A Backend API token minted without an `azp` claim is correctly rejected by the authorized-parties check and is not a substitute.
- Approve a durable outbox design only if the bounded commit-to-dispatch crash-loss window is unacceptable.

## Readiness for Phase 18.7

The existing recipient-scoped notification infrastructure is ready for Notification Preferences, subject to preserving the frozen event registry and resolving the listed production configuration and reliability decisions.
