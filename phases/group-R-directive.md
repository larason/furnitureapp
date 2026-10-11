# Group R — Notification Technology and Human-Intervention Directive

Before starting Phase 18.1, apply the following approved architecture decisions:

**Email:** Laravel Mail + Symfony Mailer + Resend.

**In-app:** Existing Laravel/MySQL notification storage and frozen NOT-001/NOT-002 APIs, with Laravel Reverb for real-time delivery.

**Mobile push:** Firebase Cloud Messaging for Android. Do not expose privileged staff notifications through the customer app.

**Orchestration:** Laravel remains the authoritative event, recipient, notification, and dispatch system. Novu is not included in the initial production release.

**Scope:** Initial production release is REQUEST ONLY. Order, checkout, payment, delivery, and tracking notification triggers remain deferred. Implement only approved request/enquiry notification events.

**API compatibility:** Preserve the frozen API contract, notification type registry, read-state semantics, ownership, pagination, and response envelopes. Never invent public endpoints or new notification types without explicit approval.

**Reliability:** Dispatch notification delivery asynchronously, with safe retries, deduplication, and no interruption of successful business transactions. Persist authoritative notification state before broadcasting delivery signals.

**Human intervention:** Whenever the implementation requires an account, API key, DNS modification, service-console configuration, cloud credential, hosting change, production deployment, billing decision, or new business-rule approval:

1. Stop that specific setup step.
2. Explain exactly what action the project owner must perform.
3. Provide the relevant official setup instructions or console location.
4. Explain how to provide the resulting configuration securely.
5. Continue independent implementation work where possible.
6. Resume the blocked integration only after configuration is verified.
7. Never fabricate secrets, deployment success, delivery evidence, or permissions.

**Development process:** Continue one microphase at a time. Begin with Phase 18.1 architecture and contract audit. Do not implement later phases prematurely.

**Acceptance criteria:** All notification behavior must be traceable from a real Laravel business event through authorized recipient resolution, durable notification state, and the appropriate delivery channel, with automated verification and documented failure behavior.