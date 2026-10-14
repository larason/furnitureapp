# Phase 18.7 Completion Report — Notification Preferences

## Status

**CLOSED — POLICY DECIDED / IMPLEMENTATION DEFERRED**

## Owner decisions (2026-10-14)

1. Eligible active `STAFF` and `ADMIN` recipients must receive mandatory operational notifications
   for `NEW_MADE_TO_ORDER_REQUEST` and `NEW_ENQUIRY` through the existing durable inbox, Reverb
   synchronization, and Resend operational-email delivery paths.
2. Personal STAFF/ADMIN operational-email opt-out is prohibited. Recipient eligibility remains
   active account, role, and the exact `requests.manage` or `enquiries.manage` permission.
3. Customer push preferences are deferred until commerce workflows and their customer notification
   policies receive separate approval. Existing FCM device-registration security and feature flags
   remain intact: `NOTIFICATION_PUSH_ENABLED=false` and
   `NOTIFICATION_PUSH_REGISTRATION_ENABLED=false`.
4. No preference table, preference columns, preference API, delivery suppression status, default
   backfill, frontend settings UI, or alternative notification configuration mechanism is justified
   for the request-only release.

## Preserved behavior

- Recipient-scoped durable notifications, Resend eligibility/retry behavior, Reverb private
  channels, NOT-001, NOT-002, delivery isolation, idempotency, and privacy protections are
  unchanged.
- Notification read state remains independent of request and enquiry business state.
- No customer request/enquiry notification, FCM business-event fan-out, new notification event,
  admin dashboard, or deferred commerce notification is introduced.

## Contract and schema impact

No frozen API path, request/response schema, operation ID, database schema, migration, model,
delivery policy, feature flag, or runtime behavior changed. The proposed `NPF-001`/`NPF-002` API
and `user_notification_preferences` schema were explicitly rejected and are not reserved or
implemented.

## Future reconsideration

Customer notification preferences require a separate owner policy decision and contract-first API
review after cart, checkout, order, payment, and customer notification-event policies are approved.

## Verification

- Documentation-only change.
- `git diff --check` must pass.
