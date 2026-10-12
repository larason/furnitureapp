# Phase 18.3 Completion Report — In-App Notifications & Laravel Reverb

## Result

**PASS — backend implementation, automated verification, and a controlled local Clerk-authenticated WebSocket smoke test are complete. Production hosting remains future work.**

## Infrastructure

- Added `laravel/reverb` v1.12.0 and Laravel broadcasting/Reverb configuration.
- Reverb remains disabled by default with `BROADCAST_CONNECTION=log` until an environment has valid Reverb credentials and hosting.
- Reverb configuration uses explicit `REVERB_ALLOWED_ORIGINS`; values are host names (for example, `localhost` locally), never `*`. Production must use the exact admin host name.
- Broadcast delivery uses the `notifications-broadcast` queue with three attempts and 60/300-second backoff. It is isolated from the existing email queue workload.

## Private channel and authorization

- Channel: `private-notifications.{localUserId}`.
- `/broadcasting/auth` uses the existing Clerk bearer-token middleware, active local-user resolution, security headers, and CORS policy.
- A subscription is allowed only when the numeric channel user ID equals the authenticated local user ID. Cross-user, anonymous, invalid-token, and inactive-account access is denied.
- Clerk middleware now sets Laravel's request-level user resolver as well as its auth guard, which Laravel broadcasting requires for private-channel authorization.

## Events and reconciliation

- Internal transport event: `notification.state-changed`.
- Payload: `{ "notification_id": "opaque-id", "change": "created|read_state_changed" }`.
- Only persisted `NEW_MADE_TO_ORDER_REQUEST` and `NEW_ENQUIRY` notifications emit `created`; duplicates suppressed by the existing notification unique constraint do not emit a second signal.
- A successful NOT-002 read/unread state change emits `read_state_changed`; unchanged idempotent updates do not.
- Signals are dispatched after persistence/commit and are never authoritative. Clients must call NOT-001 after authentication, connect/reconnect, a signal, and when returning to the notification UI. Notification IDs support client-side deduplication.

## Failure and revocation behavior

- Reverb failures are bounded, retried, observable through failed queue jobs/logs, and do not remove durable notifications, block request/enquiry submissions, or prevent the independent Resend job from running.
- Recipient eligibility remains checked when notifications are created. Existing connected clients are not forcibly terminated on a later role/account change; session expiry/disconnect/reconnect and API reconciliation limit that window. Immediate socket revocation requires future hosting/client capability work.

## Verification

- `php artisan test --compact tests/Feature/BroadcastChannelAuthorizationTest.php` — 5 tests, 12 assertions.
- `php artisan test --compact tests/Feature/OperationalNotificationEmailTest.php tests/Feature/NotificationApiTest.php` — 20 tests, 66 assertions.
- Covered private-channel authorization, cross-user denial, expired/invalid credentials, inactive accounts, minimal request/enquiry signals, read-state synchronization, duplicate suppression, and Reverb-failure isolation.

### Controlled local smoke test — PASS (2026-10-12)

- Local services: Laravel API `http://127.0.0.1:8000`, Reverb `ws://127.0.0.1:8080`, `BROADCAST_CONNECTION=reverb`, `REVERB_ALLOWED_ORIGINS=localhost`; both `notifications-broadcast` and default queue workers were running.
- An active, verified Clerk `STAFF` identity was mapped to an active local STAFF projection with `requests.manage` and `enquiries.manage`. Laravel accepted `POST /broadcasting/auth` and Reverb subscribed to that identity's `private-notifications.{localUserId}` channel.
- An anonymous browser submitted a controlled ENQ-001 enquiry through the normal public contact form. The default worker completed `CreateOperationalNotifications` and `SendOperationalNotificationEmail`; the broadcast worker emitted `notification.state-changed`.
- The STAFF subscriber received the signal and reconciled NOT-001 from zero notifications/unread to one notification/unread. The durable notification therefore existed before the signal was treated as actionable.
- NOT-002 returned `200`; its `read_state_changed` signal reconciled NOT-001 to zero unread notifications.
- A second, isolated, authenticated Clerk session attempted `POST /broadcasting/auth` for the STAFF channel and received `403`; no anonymous or client-role bypass was used.
- The STAFF client disconnected and reconnected successfully, then reconciled the durable notification through NOT-001.
- Initial local failures were diagnosed and corrected: Clerk's local `authorized_parties` allow-list needed `http://localhost:3000`, and Reverb's allowed-origin setting must contain host names (`localhost`), not URL origins. Reverb was restarted after configuration changes.

## Admin client integration

The later Admin dashboard must authenticate with the current Clerk session token in the `/broadcasting/auth` request, subscribe to its own `private-notifications.{localUserId}` channel, listen for `.notification.state-changed`, and refetch NOT-001 rather than trusting the signal payload. It must unsubscribe at logout and reconcile after every successful connection or reconnection. No customer website or Flutter subscription is enabled by this phase.

## Required owner action before production

1. Confirm the selected host supports a persistent `php artisan reverb:start` process and a Laravel queue worker.
2. Provision a public WSS hostname, TLS termination, reverse proxy WebSocket upgrades, process supervision, and permitted network access.
3. Set `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, public `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME`, server bind variables, and exact host-name `REVERB_ALLOWED_ORIGINS` outside Git.
4. Set `BROADCAST_CONNECTION=reverb`, restart the queue worker and Reverb, then run the controlled two-user request and enquiry smoke test.

No Coolify deployment, public WSS, TLS, or reverse-proxy test was performed. Those production-hosting requirements remain deliberately deferred.
