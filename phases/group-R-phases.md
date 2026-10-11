# Phase 18.1 — Notification Domain & Laravel Foundation

**Project:** SL Furnitures  
**Group:** R — Notifications  
**Phase:** 18.1 — Notification Domain  
**Backend:** Laravel 13 + MySQL  
**Production mode:** REQUEST ONLY  
**Status:** Implementation instructions for OpenCode

## 1. Objective

Audit, validate, and complete the existing Laravel notification domain foundation so that approved business events can safely produce durable, recipient-authorized, idempotent notification records.

The notification domain must support later integration with:

- Laravel Mail + Symfony Mailer + Resend (Phase 18.2).
- Laravel database notifications + Reverb broadcasting (Phase 18.3).
- Firebase Cloud Messaging for Android push (Phase 18.4).
- Request and enquiry notification delivery orchestration (Phase 18.6).

Do not implement those delivery integrations in Phase 18.1.

Novu is deferred. Laravel remains the notification system of record and the authority for recipients, event mapping, business references, and delivery eligibility.

**Primary outcome:** A trustworthy notification domain that later delivery channels can consume without changing the frozen V1 API.

## 2. Mandatory repository audit

Read these documents before changing code:

- Root `AGENTS.md`, especially Group R and the operating rules.
- `docs/api/api-contract.md §28`, including notification types, recipients, security, read state, event mapping, ordering, and failure behavior.
- `docs/api/api-resources.md §7`.
- `docs/api/api-conventions.md §28`.
- `docs/api/openapi.yaml`, specifically the Notification schema, NOT-001, NOT-002, and referenced request/response schemas.
- `docs/domain/business-rules.md §13`.
- `docs/decisions.md`, especially ADR/NOT-001 through ADR/NOT-008.
- Existing Group C notification schema and migrations.
- Existing notification models, policies, services, controllers, API resources, factories, and tests.
- Existing Clerk identity mapping, RBAC permissions, and local User model.
- Request and enquiry creation services.
- Existing Laravel queue, event, and transaction infrastructure.
- Relevant completion records for Groups J and K.

Identify exactly what already exists, what is tested, what is missing, and what contradicts the frozen contract.

Do not assume the notification subsystem is unimplemented.

### Required audit deliverable

Produce a table with:

- Contract requirement.
- Existing implementation file.
- Current behavior.
- Compliant / gap / conflict.
- Proposed minimal action.
- Associated test.

Do not begin broad refactoring until the audit is complete.

## 3. Frozen contract preservation

The following rules are mandatory.

### API

Reuse the existing operations:

**NOT-001**

`GET /api/v1/me/notifications`

**NOT-002**

`PATCH /api/v1/me/notifications/{notification}`

The mark-read request body must follow the frozen OpenAPI specification.

Retain:

- The canonical success envelope.
- The canonical error envelope.
- The notification representation.
- Recipient ownership.
- Authentication requirements.
- Allowed filters.
- Pagination structure.
- Deterministic sorting.
- CLOSED notification type enum.
- Read/unread semantics.
- Existing error/status behavior.

Do not add:

- A public notification creation endpoint.
- A manual broadcast endpoint.
- An arbitrary notification-target endpoint.
- A notification deletion endpoint.
- A notification-preferences API.
- A new read-all or unread-count endpoint unless already present in the frozen OpenAPI contract.
- A new notification type.

Where narrative documentation mentions an optional future operation not present in the frozen OpenAPI, treat it as unapproved rather than implementing it automatically.

### Contract change policy

If a required capability cannot fit the frozen public API without modification, stop and request the owner's decision before altering any externally observable contract.

Internal services, indexes, deduplication keys, and delivery bookkeeping may be added only if justified and compatible.

## 4. Active notification types

For the initial REQUEST ONLY launch, only two notification types are active.

| Authoritative business event | Notification type | Recipient scope |
|---|---|---|
| Made-to-order request submitted successfully | `NEW_MADE_TO_ORDER_REQUEST` | Authorized operational staff |
| General enquiry submitted successfully | `NEW_ENQUIRY` | Authorized operational staff |

These types are already part of the frozen registry.

Preserve all other frozen types in the registry and existing compatibility logic, but do not activate their generation.

### Deferred events

Do not activate notifications for:

- Cart operations.
- Checkout.
- New orders.
- Order acceptance.
- Order processing.
- Pickup readiness.
- Shipping.
- Delivery.
- Order completion.
- Order cancellation.
- Payment processing.
- Payment success/failure.
- Tracking updates.

Phase 18.5 remains deferred.

Do not remove the frozen order notification types merely because their producers are currently disabled.

## 5. Notification domain boundaries

Use the existing Laravel `Notification` domain model and architecture if compatible.

The core relationship must be:

```text
Authoritative Business Event
          |
          v
Approved Notification Policy
          |
          v
Recipient Resolution
          |
          v
Persistent Logical Notification
          |
          v
Future Channel Delivery
```

A notification informs an authorized actor that something happened.

It does not:

- Create a business request.
- Approve an enquiry.
- Transition a request or enquiry status.
- Establish ownership.
- Grant permission to view private resources.
- Prove that email or push delivery succeeded.
- Change any underlying business state.

The request or enquiry collection remains authoritative regardless of notification delivery.

## 6. Notification model and schema review

Inspect existing database tables and Eloquent models before introducing migrations.

Verify that the notification persistence model can represent the frozen contract.

Review:

- Stable public notification ID.
- Recipient identity/scope.
- CLOSED notification type.
- Title.
- Message.
- Structured target.
- Source event/resource identity.
- Created timestamp.
- Server-owned read timestamp.
- Derived read state.
- Privacy/recipient exposure.
- Stable ordering.
- Indexes for recipient, unread state, and pagination.

Do not automatically add columns because an example architecture mentions them.

Only introduce schema changes for confirmed, demonstrable gaps.

If using Laravel's built-in database-notification machinery, determine whether it can conform to the existing schema and representation without introducing a duplicate logical notification store.

Do not create both an independent Laravel notification table and a separate custom notification table containing the same logical messages.

The application must have one authoritative logical notification model.

## 7. Business event integration

Inspect the existing submission flows for:

- REQ-001 — Made-to-order furniture requests.
- ENQ-001 — General enquiries.

Find the actual point where each submission has been successfully and durably committed.

Notification generation must be downstream of successful submission.

### Required behavior

A successful request or enquiry may produce an approved operational notification.

A rejected submission must not create one.

Do not generate notifications from:

- Form validation attempts.
- Failed database transactions.
- Uncommitted request data.
- Duplicate controller invocations.
- Client navigation actions.
- Client-supplied status or type fields.

If the code already emits trusted domain events, reuse them.

If it does not, introduce the smallest appropriate internal event or dispatch boundary.

Do not invent an external API or change the request/enquiry response contract.

### Transaction safety

Ensure notification work is triggered only after the authoritative business transaction commits.

Use Laravel's supported after-commit event/job behavior or an existing reliable equivalent.

Do not enqueue dependent work before commit when a worker might process data that is not yet visible or might be rolled back.

## 8. Reliability and transaction isolation

A notification failure must not roll back an otherwise successful furniture request or enquiry.

Implement or verify failure isolation at the appropriate asynchronous boundary.

### Required invariants

- Request creation succeeds even if notification delivery infrastructure is temporarily unavailable.
- Enquiry creation succeeds even if notification delivery infrastructure is temporarily unavailable.
- Delivery exceptions never escape in a way that changes a committed business response into a misleading submission failure.
- Notification retries are bounded.
- Failures are observable.
- A delayed notification does not alter the business submission's original timestamp or status.

### Failure window review

Specifically analyze:

1. Business transaction committed, but dispatch process crashes.
2. Job dispatched more than once.
3. Worker crashes after persisting a notification.
4. Recipient's role changes between event creation and processing.
5. No eligible staff exist.
6. A recipient account becomes inactive.
7. A database write fails during notification creation.

For the commit-to-dispatch failure window, inspect whether the repository already provides transactional outbox infrastructure.

Do not automatically introduce a complex outbox framework. If durable capture across that window is a launch requirement and the current architecture cannot guarantee it, record the exact risk and propose the smallest reliable solution for approval.

Do not claim an in-memory after-commit callback guarantees delivery across process crashes.

## 9. Idempotency and deduplication

The same business event must not create duplicate logical notifications for the same intended recipient/scope.

Define the deduplication identity using authoritative server-generated information, including as applicable:

- Source event.
- Source resource identifier.
- Notification type.
- Recipient identity or operational audience.
- Domain event occurrence identifier if existing.

Distinguish a duplicate event replay from a new, legitimate event affecting the same resource.

For the current launch, NEW_MADE_TO_ORDER_REQUEST and NEW_ENQUIRY correspond to successful creation events.

Implement or verify an atomic deduplication mechanism backed by database constraints or an equivalently robust mechanism.

An application-level "check then insert" without concurrency protection is insufficient.

### Tests

Prove that:

- Replayed source events do not duplicate notifications.
- Concurrent processing does not duplicate notifications.
- Different request/enquiry resources generate distinct notifications.
- Different authorized recipients can receive their own intended notifications where applicable.
- Failed attempts can be retried safely.

Do not introduce externally visible idempotency fields into the frozen notification API.

## 10. Recipient resolution and authorization

Never trust a client-submitted notification recipient.

Recipient resolution must occur server-side using actual local users, roles, permissions, and account status.

### Staff recipients

For NEW_MADE_TO_ORDER_REQUEST, select users explicitly authorized for the operational request workflow.

For NEW_ENQUIRY, select users explicitly authorized for the enquiry workflow.

Inspect the repository's actual permission names and policy model.

Do not hard-code recipient email addresses into the domain service.

Do not assume every authenticated user is operational staff.

Do not automatically send all staff notifications to every admin account.

### Admin recipients

The frozen contract permits admin visibility according to operational scope, but does not require blind duplication to all administrators.

Inspect existing policies and decisions.

If there is no established rule governing whether ADMIN users receive request/enquiry alerts, request explicit business-owner approval before enabling admin fan-out.

### Customer recipients

Do not generate persistent customer in-app notifications for anonymous requests or enquiries.

Do not invent customer notification types such as:

- REQUEST_SUBMITTED
- ENQUIRY_RECEIVED
- REQUEST_APPROVED
- ENQUIRY_REPLIED

The current contract does not authorize these as new V1 in-app notification types.

Customer-facing confirmation emails are a separate delivery-policy question for Phase 18.2/18.6 and require approval where not already decided.

### Open decision

Review whether staff notifications are modeled as:

- Individual recipient-scoped records; or
- A shared operational queue with distinct personal read semantics.

Preserve any already established and compatible implementation.

If the repository does not resolve this distinction, document the alternatives and request an explicit decision before creating a new shared-read or acknowledgment model.

Do not silently overload `read_at` as team-wide handling status.

## 11. Read/unread semantics

The frozen contract defines:

- `read_at == null` means unread.
- A populated `read_at` means read.
- `is_read` is derived.
- The server owns `read_at`.
- Read state is distinct from request/enquiry operational status.

Read state must not be mutated by notification dispatch or delivery.

Opening an underlying request/enquiry does not automatically mean an operational notification was read unless existing approved semantics explicitly require it.

One staff member reading a notification must not mark a request as resolved for everyone.

Verify correct behavior for:

- Personal recipient scoping.
- Read/unread filtering.
- Mark-read endpoint.
- Repeated mark-read calls.
- Cross-user access.
- Cross-role access.
- Server timestamps.
- Multi-device consistency.

Do not rewrite immutable notification contents when changing read state.

## 12. Notification source and target

Source information exists for auditability and deduplication.

Target information exists for optional navigation to an authorized business resource.

Use server-generated target references matching the frozen representation.

For the active launch:

- NEW_MADE_TO_ORDER_REQUEST references the corresponding request.
- NEW_ENQUIRY references the corresponding enquiry.

Use actual frozen target types and opaque resource IDs from the repository, not invented strings.

### Security

A target ID is not a capability token.

Opening the target must still require the corresponding authorized operational API operation.

Do not embed:

- Complete enquiry messages.
- Customer private attachments.
- Authentication credentials.
- Full customer profiles.
- Internal staff notes.
- Upload tokens.
- Signed private attachment URLs.
- Entire business resource payloads.

Keep titles/messages concise and operationally useful.

Treat any source-derived customer-entered text as untrusted. Prefer server-controlled templates with minimal safe identifiers over interpolating arbitrary private content.

## 13. Notification creation service

If a domain-level creation service already exists, review and reuse it.

If absent, implement the smallest cohesive service needed to centralize:

- Approved event mapping.
- Recipient policy.
- Notification type selection.
- Source/target construction.
- Deduplicated persistence.
- Error isolation.
- Logging/correlation.

Keep domain orchestration separate from transport delivery.

Later channel implementations should be able to consume the resulting logical notification without duplicating event-to-recipient decisions.

### Design principles

Avoid:

- Giant switch statements.
- New application-wide service containers.
- Multiple competing event buses.
- Duplicate source-to-type mappings.
- Provider-specific fields leaking into the public notification representation.
- Provider API clients in domain services.
- Excessive abstractions for hypothetical channels.

Prefer small, testable Laravel services, jobs, or event listeners consistent with the existing codebase.

## 14. Channel boundary preparation

Prepare the domain for later delivery channels without implementing the channels now.

### Phase 18.2 — Email

Future:

Laravel Mail + Symfony Mailer + Resend.

Phase 18.1 may define the approved internal delivery boundary, but must not:

- Install provider SDKs.
- Send live email.
- Request API keys.
- Create production email templates.
- Configure DNS.
- Provision Resend resources.

### Phase 18.3 — In-app and Reverb

Future:

Authoritative database-backed inbox plus Laravel Reverb for real-time signals.

Phase 18.1 must not:

- Start Reverb.
- Publish WebSocket messages.
- Create public broadcast channels.
- Implement web notification UI.
- Introduce new public subscription endpoints.

Database persistence and the existing NOT-001/NOT-002 domain/API behavior may be verified and minimally repaired as part of this foundation.

Full client and real-time delivery integration belongs to Phase 18.3.

### Phase 18.4 — FCM

Future:

Android Firebase Cloud Messaging.

Phase 18.1 must not:

- Add Firebase packages.
- Register device tokens.
- Create FCM credentials.
- Send push notifications.
- Add mobile notification permission prompts.

Do not invent an FCM device-token API. Review and request approval for any required frozen-contract changes when Phase 18.4 begins.

### Novu

Do not install or integrate Novu.

Maintain a clean internal channel boundary so future evaluation remains possible.

## 15. Logging and observability

Notification failures must be diagnosable without exposing personal data.

Use existing Laravel logging conventions.

Capture appropriate safe identifiers:

- Correlation/request ID where available.
- Domain event identifier.
- Notification type.
- Source resource identifier.
- Internal recipient identity where access to logs is restricted.
- Processing attempt.
- Failure category.
- Outcome.

Do not log:

- Clerk session tokens.
- Resend credentials.
- FCM credentials.
- Full enquiry messages.
- Uploaded attachment contents.
- Customer email bodies.
- Arbitrary personal details.

Investigate retry visibility and failed-job reporting.

Do not claim delivery success in Phase 18.1; this phase does not deliver messages.

## 16. Performance requirements

Notification generation must not introduce noticeable latency into public request/enquiry submission.

Prefer asynchronous downstream processing.

Inspect the queue driver and worker architecture already configured.

Consider recipient count and database query efficiency.

Use appropriate indexes for recipient-scoped queries and deduplication.

Avoid an N+1 authorization query for each candidate recipient where a bounded, authorized query can resolve the operational audience safely.

Do not cache authorization-sensitive recipient sets indefinitely.

Keep the architecture appropriate for a small business.

## 17. Required automated testing

Add or extend tests covering:

### Contract compatibility

1. NOT-001 retains its exact public contract.
2. NOT-002 retains its exact public contract.
3. Notification types remain CLOSED.
4. Existing pagination and filters are unchanged.
5. Response envelopes remain unchanged.
6. Read timestamps remain server-owned.
7. Cross-user notification access is denied and masked as contracted.

### Business events

8. Successful REQ-001 produces the approved domain notification.
9. Successful ENQ-001 produces the approved domain notification.
10. Invalid REQ-001 produces no notification.
11. Invalid ENQ-001 produces no notification.
12. Rolled-back transactions produce no notification.
13. Anonymous REQ-001 does not create a customer in-app notification.
14. Anonymous ENQ-001 does not create a customer in-app notification.
15. Normal customer submissions do not create customer-facing invented notification types.

### Recipients

16. Authorized request staff are eligible.
17. Authorized enquiry staff are eligible.
18. Unauthorized users are excluded.
19. Customers cannot be selected as operational recipients.
20. Inactive/revoked accounts are handled according to established policy.
21. Admin recipient behavior follows an approved policy.
22. No-recipient scenarios are handled safely.
23. One staff member reading does not change the business resource status.

### Idempotency

24. Duplicate request event replay does not create duplicate notifications.
25. Duplicate enquiry event replay does not create duplicate notifications.
26. Concurrent duplicate delivery attempts do not duplicate logical notifications.
27. Distinct resources produce distinct notifications.
28. Database deduplication is atomic.

### Reliability

29. Queue failure does not roll back an already committed request.
30. Queue failure does not roll back an already committed enquiry.
31. Notification persistence failure is recorded.
32. Retriable failures follow approved bounds.
33. Unrecoverable failures remain observable.
34. Notification processing does not accidentally run before transaction commit.
35. Notification history remains stable after retries.

### Privacy and security

36. Customer A cannot access Customer B notifications.
37. Staff cannot access unrelated private customer notifications.
38. Clients cannot create arbitrary notification records.
39. Clients cannot modify recipient/type/source/target.
40. Notification targets do not bypass resource authorization.
41. Notification content excludes private attachments and secrets.
42. Deferred order/payment notifications are not emitted in REQUEST ONLY mode.

Use deterministic tests and existing factories.

Do not require live Resend, Reverb, Firebase, Clerk Dashboard, or production email credentials.

## 18. Human-intervention protocol

If implementation requires a human decision or service configuration, notify the project owner explicitly.

Examples:

- Choosing the operational recipient audience.
- Determining staff-only versus authorized-admin notification fan-out.
- Resolving personal versus shared operational read semantics.
- Approving an internal schema migration with significant data implications.
- Accepting a reliability limitation or approving an outbox strategy.
- Resolving a frozen-contract incompatibility.
- Selecting hosting or queue infrastructure that affects production reliability.

When blocked:

1. Describe the exact blocker.
2. Present the available compliant options.
3. State the recommended option and tradeoffs.
4. Identify precisely what the owner must decide or configure.
5. Avoid making irreversible changes before approval.
6. Continue independent, safe work if possible.

Do not silently invent a business rule.

Resend domain verification, Reverb hosting, and Firebase project configuration are later-phase interventions, not prerequisites for completing Phase 18.1.

## 19. Documentation

Create a Phase 18.1 completion record in the project's existing phase-documentation convention.

Include:

- Contract traceability table.
- Existing notification subsystem inventory.
- Schema/model audit outcome.
- Active and deferred notification events.
- Recipient policy.
- Read-state semantics.
- Deduplication mechanism.
- Transaction/queue boundary.
- Known failure windows.
- Observability decisions.
- Channel integration boundaries.
- Migration changes, if any.
- Explicit owner decisions requested and resolved.
- Tests and verification results.
- Remaining prerequisites for Phase 18.2.

Update other documentation only where implementation or architecture genuinely changed.

Do not rewrite frozen API contracts merely to document internal implementation choices.

## 20. Verification

Use the actual project commands and test suite.

At minimum:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
php artisan route:list
git diff --check
```

Also execute:

- Relevant notification feature tests.
- Request/enquiry regression tests.
- Authorization regression tests.
- Queue and transaction-boundary tests.
- Migration checks on an approved disposable test database if schema changes are necessary.

Never use `migrate:fresh` against a development, staging, or production database.

Follow the repository's mandatory destructive-database guard.

Report commands that were skipped or could not run.

## 21. Explicit exclusions

Do not implement:

- Resend API integration.
- Actual email sending.
- Reverb broadcasting.
- WebSocket subscriptions.
- Firebase Messaging integration.
- Device-token registration.
- Android push UI.
- Admin notification dashboard.
- New customer notification screens.
- Notification marketing campaigns.
- Novu.
- SMS.
- Order/delivery/payment notification production triggers.
- Arbitrary notification creation APIs.
- New notification enum members.
- Unapproved public API changes.

Do not activate customer purchase workflows.

Do not refactor unrelated Laravel domains.

## 22. Definition of Done

Phase 18.1 is complete when:

- The current notification implementation has been audited against all relevant frozen contracts.
- Confirmed domain gaps are resolved without inventing API behavior.
- Authoritative REQ-001 and ENQ-001 events can produce the intended operational notification records.
- Recipient selection is server-authorized.
- Notification creation is idempotent.
- Read state remains recipient-scoped and distinct from business handling.
- Notifications do not grant access to their target resources.
- Business transaction success is independent of notification delivery success.
- Event dispatch respects transaction commit boundaries.
- Important delivery and dispatch failure windows are documented and appropriately mitigated or explicitly escalated.
- Order, payment, and delivery notification producers remain deferred.
- Notification security and privacy tests pass.
- Relevant request/enquiry regressions pass.
- Documentation reflects actual behavior.
- No external provider credentials are required.
- No frozen contract violation has been introduced.
- Any unresolved decision is clearly reported rather than silently assumed.

## 23. Completion report

At the end, provide:

1. PASS, PARTIAL, or FAIL.
2. Files inspected, added, and modified.
3. Existing components reused.
4. Actual schema and model changes.
5. Approved event mapping.
6. Recipient resolution strategy.
7. Idempotency implementation and tests.
8. Queue/transaction reliability findings.
9. NOT-001/NOT-002 compatibility.
10. Security and authorization test results.
11. Commands run and results.
12. Human intervention required, if any.
13. Known limitations.
14. Phase 18.2 readiness.

**Final instruction:** Implement only Phase 18.1. Preserve the frozen notification API and the REQUEST ONLY production scope. Do not proceed to Resend configuration, Reverb, FCM, or later phases until the domain foundation is verified and the project owner explicitly requests the next phase.