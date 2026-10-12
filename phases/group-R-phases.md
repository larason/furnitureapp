# Phase 18.3 — In-App Notifications & Laravel Reverb

**Project:** SL Furnitures  
**Group:** R — Notifications  
**Active phase:** 18.3  
**Backend:** Laravel 13 + MySQL  
**Realtime transport:** Laravel Reverb (WebSockets)  
**Authentication:** Clerk JWT verified by Laravel  
**Production mode:** REQUEST ONLY

**Prerequisites:**
- Phase 18.1 — Notification Domain: implemented.
- Phase 18.2 — Resend transactional email: implemented; test email successfully sent.

**Next phase:** 18.4 — Firebase Cloud Messaging Architecture.

## 1. Objective

Extend the existing recipient-scoped Laravel notification system with secure, real-time in-app notification updates using Laravel Reverb.

Preserve MySQL as the authoritative notification store.

The WebSocket layer must notify connected, authorized users that their notification state has changed. Clients must be able to reconcile against the existing frozen notification API.

Implement and test:

- Laravel Reverb server integration.
- Secure private broadcast channels.
- Clerk-authenticated channel authorization.
- Notification-created broadcast events.
- Notification-read-state update signals where justified.
- Broadcast delivery after database commit.
- Safe, minimal WebSocket payloads.
- Reliable reconnection and state reconciliation.
- Failures isolated from business operations and email delivery.
- Authenticated test-client verification.
- Documentation and human deployment checkpoints.

Do not introduce another notification persistence system or change the frozen API.

## 2. Mandatory repository inspection

Before editing code, read:

1. Root `AGENTS.md`, particularly Group R and the implementation quality rules.
2. Phase 18.1 completion documentation.
3. Phase 18.2 completion documentation.
4. Existing Notification model, resources, controllers, policies, and tests.
5. Recipient resolution and authorization logic.
6. Notification unique-index and idempotency implementation.
7. NOT-001 and NOT-002 implementations.
8. Email delivery attempts and queued email dispatch.
9. Existing Laravel event/listener/job architecture.
10. Clerk JWT verification middleware and local User mapping.
11. Laravel bootstrap/routing configuration.
12. Existing queue configuration and workers.
13. Existing frontend architecture and plans for the admin dashboard.
14. `docs/api/api-contract.md §28`.
15. `docs/api/api-resources.md §7`.
16. `docs/api/api-conventions.md §28`.
17. `docs/api/openapi.yaml`.
18. `docs/domain/business-rules.md §13`.
19. Notification-related ADRs in `docs/decisions.md`.

Review the current official Laravel 13 broadcasting and Reverb documentation:

- https://laravel.com/docs/13.x/broadcasting
- https://laravel.com/docs/13.x/reverb

### Inspection report

Identify:

- Whether Reverb is already installed.
- Whether broadcasting is configured.
- Whether an existing broadcaster is active.
- How Clerk bearer authentication is integrated.
- How a staff user's notification is persisted.
- How email delivery is triggered.
- Where broadcasting should be dispatched.
- Which server processes are needed.
- Whether the hosting environment supports WebSockets.

Reuse working infrastructure.

Do not run broad installation commands without inspecting their possible changes to existing files.

## 3. Architectural boundary

Use this flow:

```text
REQ-001 / ENQ-001
        |
        v
Business transaction committed
        |
        v
Phase 18.1 Notification domain
        |
        v
MySQL notification persisted
        |
        +----> Phase 18.2 queued Resend email
        |
        +----> Phase 18.3 Reverb signal
                       |
                       v
              Private recipient channel
                       |
                       v
              Authorized web client
                       |
                       v
               Fetch NOT-001
                       |
                       v
               Update local UI state
```

The broadcast is a signal, not the definitive notification database.

Never use a WebSocket event to create a second logical notification record.

Never make WebSocket connectivity a prerequisite for accepting a furniture request or enquiry.

Reverb does not replace Laravel notification persistence, APIs, or authorization.

## 4. Supported events

Only activate real-time notification delivery for the two approved REQUEST ONLY event types:

- `NEW_MADE_TO_ORDER_REQUEST`
- `NEW_ENQUIRY`

Reuse the exact logical notification records already generated in Phase 18.1.

Each event must be delivered only to the eligible notification's assigned recipient.

The existing eligibility rules remain authoritative:

- Active operational users authorized for request management.
- Active operational users authorized for enquiry management.
- ADMIN only when their actual permissions qualify them.
- No automatic broadcasting to every ADMIN.
- No broadcasting to CUSTOMER accounts for these operational events.

Do not add new public notification types.

Do not activate order, payment, or delivery notifications.

## 5. Laravel Reverb installation

Determine whether Reverb is already installed and configured.

If not, use the official, Laravel-version-compatible installation method.

Possible documented commands include:

```bash
php artisan install:broadcasting --reverb
```

or an equivalent compatible manual installation using `laravel/reverb`.

Inspect the installation command's effects before running it.

### Installation requirements

- Reuse the existing Laravel application.
- Do not create a separate WebSocket microservice.
- Do not install Pusher's hosted service.
- Do not introduce Ably or another broadcasting provider.
- Do not duplicate broadcast configuration.
- Do not overwrite the existing queue driver.
- Do not disturb the Resend integration.
- Keep Reverb credentials out of Git.
- Add appropriate `.env.example` placeholders.
- Ensure configuration works in LOCAL, STAGING, and PRODUCTION.

Do not automatically configure horizontal scaling or Redis unless the actual deployment requires it.

For initial small-business scale, a single managed Reverb server is sufficient if supported by the chosen hosting environment.

## 6. Private recipient broadcast channels

Create one private channel namespace for a recipient's personal notification updates.

A conceptual channel name is:

```text
private-notifications.{recipientUserId}
```

The exact Laravel channel definition and Echo subscription syntax must follow Laravel's supported conventions.

Do not assume the conceptual prefix should be copied literally into every layer.

### Authorization rule

A caller can subscribe only when:

1. A valid Clerk session JWT is verified by Laravel.
2. The token resolves to an active local application user.
3. The requested channel recipient identifier matches that authenticated user.
4. The user is eligible to access their notification stream under existing policies.

Do not trust the supplied recipient ID by itself.

Never expose notification streams through public channels.

Never allow a wildcard staff subscription to all customer notifications.

Never give ADMIN blanket access to every user's private notification channel.

### Important

Recipient channel access is not equivalent to permission to view the underlying request/enquiry resource.

Normal operational resource authorization must still apply.

## 7. Clerk authentication for channel subscription

This project uses Clerk for identity, with Laravel verifying the session JWT.

Do not introduce a separate Laravel login system or session-authentication flow.

Review Laravel's broadcasting authorization mechanism and ensure private-channel authorization works with the established Clerk bearer-token middleware.

### Requirements

- Verify Clerk JWT server-side.
- Resolve the existing local User.
- Reuse role and account-status checks.
- Reject missing, expired, invalid, or pending-security-task sessions.
- Deny cross-user channel subscriptions.
- Deny deactivated users.
- Return safe authorization errors.
- Avoid recording bearer tokens in logs.
- Do not put JWTs into WebSocket query strings or channel names.

### Broadcasting authorization route

Laravel may use `/broadcasting/auth` for private-channel authorization.

This is infrastructure-specific and is not one of the frozen `/api/v1` business operations.

Before enabling it:

- Inspect existing route topology.
- Confirm the proper middleware.
- Confirm the request authentication mechanism.
- Confirm CORS and origin restrictions.
- Confirm Clerk bearer token behavior.
- Verify it cannot fall back to insecure or unrelated session authentication.

If introducing an infrastructure authorization route creates a compatibility or deployment concern, explain the decision before proceeding.

Do not add arbitrary new notification CRUD endpoints.

## 8. Broadcast payload design

Keep payloads minimal.

Prefer a notification-state-change signal containing only safe internal routing information such as:

- Opaque notification ID.
- Change kind, such as created or read-state changed.
- Optional event occurrence marker, if already available.

Do not duplicate the complete notification API response if the client can retrieve it through NOT-001.

Never broadcast:

- Full enquiry messages.
- Complete made-to-order request descriptions.
- Customer email addresses.
- Phone numbers.
- Private attachments.
- Upload tokens.
- Staff internal notes.
- Clerk tokens.
- Resend credentials.
- Full user profiles.
- Private signed URLs.

Do not treat the WebSocket payload as authorization to open an underlying resource.

A recipient client should fetch authoritative details using the existing API.

### Type consistency

Do not invent notification machine types.

Use the existing CLOSED registry for persisted notifications.

Internal broadcast event names are transport concerns and must be clearly distinguished from public notification types.

## 9. Broadcast after successful persistence

Only broadcast after the logical notification record has been committed.

The required ordering is:

```text
Persist logical notification
        |
        v
Commit transaction
        |
        v
Queue broadcast delivery
        |
        v
Reverb sends live update
```

A broadcast must never announce a notification that may still roll back.

Use Laravel-supported after-commit dispatch behavior or the existing repository equivalent.

Do not use an immediate broadcast before transaction commit.

### Duplication protection

Phase 18.1 already uses a unique source/type/recipient constraint.

Preserve this guarantee.

A replayed business event that does not create a new logical notification should not produce a misleading additional "new notification" signal.

If transport-level broadcasts are occasionally duplicated, clients must tolerate repeated IDs and reconcile idempotently through the API.

Do not introduce a second competing deduplication registry unnecessarily.

## 10. Read/unread synchronization

Preserve the frozen NOT-002 API:

```http
PATCH /api/v1/me/notifications/{notification}
```

The client sends the approved request body according to OpenAPI.

Laravel remains authoritative for `read_at`.

### Desired real-time behavior

When a recipient marks a notification read:

1. Laravel verifies recipient ownership.
2. Laravel updates read state according to NOT-002.
3. The database transaction commits.
4. An optional recipient-scoped update signal is published.
5. Other active sessions for that recipient refresh their local state.

Do not broadcast a read-state change to another user's channel.

Do not conflate "read" with "handled" or "closed".

Do not make notification read state change the status of a request or enquiry.

Avoid sending duplicate read-change broadcasts when an idempotent operation produces no meaningful state change.

## 11. Frontend integration boundary

The production admin dashboard frontend will be implemented in its dedicated group after Notifications.

Therefore, implement the Laravel realtime infrastructure now without prematurely building the entire admin notification UI.

### Required in this phase

Provide a small authenticated integration test client, test harness, or reusable Echo connection proof that demonstrates:

- Secure channel authorization.
- Successful subscription.
- Receipt of a notification-created signal.
- API reconciliation using NOT-001.
- Mark-read behavior through NOT-002.
- Reconnection after interruption.
- Cross-user access denial.

This must run against real Laravel/Reverb in a controlled local or staging environment where feasible.

### Defer to Admin Dashboard Frontend group

- Production notification bell.
- Unread badge.
- Notification popover.
- Notification center/page.
- Request/enquiry deep-link navigation.
- Staff-specific layout integration.
- Notification accessibility/UI polish.
- Admin dashboard responsive behavior.

Document the client integration requirements so the future Next.js + MUI admin implementation can reuse them.

Do not place staff-operational notifications inside the public customer website or Flutter app.

Do not invent customer notification events just to populate a UI.

### Echo integration

The later admin client may use Laravel Echo with Reverb's supported Pusher protocol client.

If adding a reusable integration helper now, ensure it:

- Authenticates against the proper Laravel channel-auth route.
- Uses current Clerk session tokens securely.
- Does not expose server secrets.
- Avoids subscribing before authentication is resolved.
- Unsubscribes on logout.
- Cleans up listeners.
- Refreshes credentials appropriately.
- Does not create duplicate subscriptions.

Do not introduce permanent frontend dependencies solely for a disposable smoke-test page if a standalone test harness is sufficient.

## 12. Reconnection and missed events

WebSockets do not provide a durable replayable inbox by themselves.

The durable inbox remains MySQL, exposed through NOT-001.

### Required reconciliation points

Clients should refetch notifications:

- After initial authentication.
- After a successful connection.
- After reconnection.
- After a relevant WebSocket signal.
- When returning to an active notification UI, where practical.

Do not assume all broadcasts are delivered exactly once.

Do not build an unnecessary event-replay infrastructure.

Use notification IDs for client-side deduplication.

Preserve server-authoritative unread counts and pagination.

Never determine the global unread count merely by incrementing an in-memory counter on each WebSocket message.

## 13. Authorization and privacy hardening

Test cross-user access thoroughly.

Required negative cases:

- Anonymous user attempts a subscription.
- CUSTOMER attempts a STAFF recipient channel.
- STAFF A attempts STAFF B's channel.
- STAFF attempts an unrelated customer's channel.
- ADMIN attempts a recipient channel other than their own.
- Expired Clerk token attempts authorization.
- Tampered bearer token attempts authorization.
- Deactivated user attempts authorization.
- Invalid channel name is submitted.
- Invalid recipient identifier is submitted.

Every case must be denied safely.

### Browser origin rules

Configure Reverb allowed origins appropriately.

Do not use wildcard production origins.

Do not confuse origin filtering with user authorization.

Origin checks supplement, but never replace, JWT verification and channel authorization.

### Revocation behavior

When a staff user's permissions or account state change, evaluate how existing long-lived subscriptions are terminated or prevented from receiving further sensitive events.

Private-channel authorization at connection time alone may not immediately revoke an existing subscription.

At a minimum:

- Validate recipient eligibility before event emission.
- Ensure revoked recipients are excluded from new notification delivery.
- Document how client session expiry, disconnect, and reconnection are handled.
- Escalate any remaining unacceptable revocation window.

Do not claim immediate revocation if the implementation cannot enforce it.

## 14. Queue and fault isolation

Broadcasting must not break:

- Furniture request submissions.
- Enquiry submissions.
- Phase 18.1 notification persistence.
- Phase 18.2 Resend email delivery.

### Required behavior

If Reverb is offline:

- The business operation remains successful.
- The logical notification remains stored.
- Queued email behavior remains independent.
- The broadcast job fails or retries according to policy.
- The recipient can still fetch the notification through NOT-001.
- The failure is observable.

Use bounded retries and suitable backoff.

Avoid retry storms when Reverb is unavailable.

Where supported, isolate broadcast workloads from other queues to prevent a WebSocket outage from starving email delivery.

Do not introduce distributed infrastructure without a demonstrated need.

## 15. Deployment and configuration

Inspect existing environment configuration.

Typical Reverb deployment requires:

- Reverb application ID/key/secret.
- WebSocket host.
- Server bind address and port.
- Public WebSocket hostname.
- TLS termination.
- Reverse proxy with WebSocket upgrade support.
- Long-running Reverb process.
- Running Laravel queue worker.
- Process restart management.
- Secure production origins.
- Logs and health monitoring.

Use the exact environment variables required by the installed Laravel/Reverb versions.

Do not confuse the internal Reverb bind address/port with the public WSS hostname and port.

Do not expose the Reverb application secret to the browser.

App keys intended for client connection are different from server secrets.

### Hosting checkpoint

**Stop and ask the project owner** whether the chosen deployment environment supports persistent WebSocket processes before making production configuration changes.

If the hosting provider cannot support Reverb, report that limitation.

Do not silently replace Reverb with a paid third-party WebSocket service.

Do not modify firewall rules, DNS, reverse proxy configuration, or production process managers without the owner's approval.

## 16. Human intervention protocol

Whenever manual input is needed:

1. Explain the exact requirement.
2. State why it cannot be completed automatically.
3. Provide the required owner action.
4. Explain the expected configuration/result.
5. Explain how to verify success.
6. Continue independent safe work where possible.
7. Stop the blocked production step until approved.

Potential checkpoints:

- Hosting provider selection.
- Public WebSocket subdomain.
- DNS configuration.
- HTTPS/WSS certificate setup.
- Reverse proxy configuration.
- Firewall/port configuration.
- Production environment credentials.
- Production process manager.
- Live multi-user subscription test authorization.
- Any frozen-contract or security-policy decision.

Do not fabricate successful connectivity.

Do not invent production infrastructure settings.

## 17. Automated tests

Add targeted tests for the following.

### Persistence and broadcasting

1. Persisted notification schedules the correct broadcast.
2. REQ notification uses the existing recipient.
3. ENQ notification uses the existing recipient.
4. Rolled-back notification creates no broadcast.
5. Duplicate event replay does not create a second logical notification.
6. Failed broadcast does not remove a notification.
7. Failed broadcast does not stop email delivery.
8. Failed broadcast does not roll back request/enquiry creation.

### Channel authorization

9. Authenticated recipient can subscribe to their own channel.
10. Anonymous subscription fails.
11. Cross-user subscription fails.
12. CUSTOMER cannot subscribe to operational STAFF channels.
13. STAFF cannot subscribe to another recipient's private stream.
14. ADMIN cannot access arbitrary personal channels.
15. Expired tokens fail.
16. Inactive accounts fail.
17. Channel names are properly validated.

### Payload security

18. Broadcast payload contains only approved fields.
19. No Clerk token is exposed.
20. No recipient email is exposed.
21. No full request/enquiry body is exposed.
22. No private attachments are exposed.
23. No staff internal notes are exposed.
24. No raw provider credentials appear.

### Read-state synchronization

25. NOT-002 marks only the authenticated recipient's notification.
26. Read-state change signals go to the correct channel.
27. One STAFF member's read action does not alter another's read state.
28. No duplicate signal is emitted for unchanged state where appropriate.
29. NOT-001 remains authoritative after reconciliation.

### Integration behavior

30. Authorized subscriber receives a real broadcast.
31. Unauthorized subscriber cannot receive a private-channel signal.
32. A disconnected client can recover missed notifications through NOT-001.
33. Multiple client sessions for the same recipient reconcile.
34. Logout cleans up subscriptions in the test client.
35. Reconnect restores notification state.
36. Resend regression tests still pass.
37. Deferred order/payment/delivery events are not activated.

Use Laravel's supported broadcasting test fakes for deterministic tests.

A mocked broadcast assertion does not count as a successful real WebSocket integration test.

## 18. Integration smoke test

When local environment permits, run an end-to-end controlled test:

1. Start Laravel.
2. Start queue worker.
3. Start Reverb.
4. Authenticate as an eligible STAFF user through the existing Clerk/Laravel integration.
5. Subscribe to the user's private notification channel.
6. Submit a valid test made-to-order request.
7. Confirm MySQL creates exactly one recipient notification.
8. Confirm the authorized subscriber receives the live signal.
9. Fetch NOT-001 and confirm the notification is returned.
10. Mark it read through NOT-002.
11. Confirm the updated server read state.
12. Disconnect and reconnect.
13. Confirm reconciliation through NOT-001.
14. Attempt to subscribe as a different user and verify denial.
15. Confirm Resend delivery behavior remains unaffected.

Repeat the equivalent event test for a general enquiry.

Use controlled test data only.

If real Clerk accounts or a live Resend email are required to execute any smoke test, obtain explicit approval before using them.

## 19. Verification commands

From the Laravel backend directory, run the existing project's quality checks.

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

Run targeted Reverb, channel-auth, notification, and email regression tests.

Use `php artisan reverb:start` for a controlled local integration test only after correct configuration.

Use the existing queue worker to process test broadcast events.

Do not add uncontrolled long-running background processes to CI.

If broadcast configuration relies on specific PHP extensions, diagnose and document missing dependencies.

Do not run destructive database commands outside the root AGENTS.md guard.

## 20. Documentation

Create the Phase 18.3 completion record using repository conventions.

Include:

- Existing infrastructure audit.
- Reverb dependency/configuration.
- Private channel names.
- Clerk authorization integration.
- Broadcast event names.
- Payload schema.
- Commit and dispatch ordering.
- Reconnect/reconciliation behavior.
- Recipient revocation considerations.
- Queue and retry strategy.
- Local setup commands.
- Production hosting requirements.
- Environment variable descriptions.
- Exact integration-test results.
- Known limitations.
- Admin frontend integration guidance.
- Human intervention needed before production.

Document why WebSocket signals are not authoritative notification records.

Do not rewrite the frozen public API to describe transport-internal details.

## 21. Explicit exclusions

Do not implement:

- Firebase Cloud Messaging.
- Push-token registration.
- Android notification permissions.
- SMS.
- Novu.
- Marketing campaigns.
- Production admin dashboard UI.
- A new notification database.
- New public notification endpoints.
- Notification preferences API.
- New notification types.
- Customer request-status notifications.
- Order, payment, or delivery notifications.
- Paid third-party WebSocket services.
- Unnecessary Redis clustering.
- Complex event replay or CQRS.

Do not refactor unrelated business logic.

## 22. Definition of Done

Phase 18.3 is complete when:

- Reverb is correctly configured for the Laravel application.
- Recipient-scoped private channels exist.
- Subscription authorization reuses Clerk-authenticated local User identity.
- Cross-user subscriptions are denied.
- Approved persisted notifications generate post-commit broadcast signals.
- Signal payloads do not expose private business information.
- NOT-001 remains the authoritative inbox.
- NOT-002 remains the authoritative read-state mutation.
- Multi-session and reconnect reconciliation is demonstrated.
- Reverb failures do not break business submissions.
- Email notifications remain functional.
- No new public API behavior is introduced.
- No deferred commerce notifications are activated.
- Automated tests pass.
- A real WebSocket smoke test passes where supported.
- Production hosting blockers are explicitly documented.
- Phase documentation is complete.

## 23. Completion report

Return:

1. PASS, PARTIAL, or FAIL.
2. Installed/reused Reverb dependencies.
3. Files modified and created.
4. Private channel structure.
5. Clerk channel-auth mechanism.
6. Broadcast events and payloads.
7. Database/event dispatch behavior.
8. Queue and retry results.
9. Security and authorization tests.
10. Real WebSocket smoke test evidence.
11. Resend regression results.
12. Human configuration still required.
13. Known limitations.
14. Readiness for Phase 18.4.

**Final instruction:** Complete only Phase 18.3. Preserve the recipient-scoped notification and Resend implementations from Phases 18.1 and 18.2. Do not build the admin dashboard UI or implement FCM. If real hosting, credentials, or owner approval is required, notify the project owner before proceeding with the affected step.