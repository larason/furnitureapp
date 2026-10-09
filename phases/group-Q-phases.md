# Phase 17.10 — Flutter Furniture Requests

**Project:** SL Furnitures — Flutter Android App  
**Group:** Q — Flutter Customer Features  
**Prerequisites:** Phases 16.1–16.9 and active Phases 17.1–17.4  
**Status:** READY FOR IMPLEMENTATION, subject to backend readiness verification  
**Scope:** Anonymous and authenticated made-to-order furniture request submission

## 1. Objective

Implement the complete customer-facing Furniture Request workflow for the request-only production release.

Support two entry points:

1. **Catalog-linked request:** A customer selects a MADE_TO_ORDER product, opens its detail screen, and chooses "Request this furniture."
2. **Custom furniture request:** A customer opens the Furniture Requests route directly and describes furniture not necessarily present in the catalog.

Both entry points must use the same feature architecture, form, validation, and REQ-001 submission service.

A furniture request is an expression of interest, not an Order, quotation, payment, or production commitment.

**Do not implement deferred commerce phases 17.5–17.9.**

---

## 2. Mandatory repository inspection

Read and follow:

- Root `AGENTS.md` and Flutter-specific instructions.
- `docs/decisions.md`, especially `ADR/GROUP-H-AND-I-DEFER`, `ADR/API-REQ-001` through `011`, `ADR/BACKEND-043` onward, and `ADR/BACKEND-048/049`.
- `docs/api/api-contract.md` section 26.
- `docs/api/api-resources.md` Furniture Request representation.
- `docs/api/openapi.yaml` REQ-001, REQ-002, REQ-003, REQ-007 and related schemas.
- Laravel request controller, validator, resource, attachment services, middleware, and route activation configuration.
- Existing Next.js furniture-request implementation, if present.
- Flutter Phase 17.3 product details.
- Flutter Phase 17.4 search and catalog.
- Existing ClerkAuthAdapter, ApiClient, request cancellation, AsyncViewState, diagnostics, router, and theme.
- Canonical `frontend/design-system/tokens.css`, `DESIGN.md`, and `ACCESSIBILITY.md`.

**Critical prerequisite:** Verify that REQ-001 is actually enabled and that the separate-upload capability reconciliation has been implemented. Earlier backend decisions recorded gated routes, while a later ADR resolved the capability representation. An accepted ADR alone does not prove the route is active.

If the route is still gated, implement the form and testable integration but do not claim live submission works. Report the blocker rather than silently enabling Laravel routes or changing frozen contracts.

---

## 3. Frozen request workflow

The primary endpoint is:

```http
POST /api/v1/requests
```

It accepts:

- Anonymous visitors without authentication.
- Authenticated CUSTOMER users.
- JSON submissions without an attachment.
- Multipart submissions with an optional inline attachment.

An invalid or expired bearer token must not be silently downgraded to anonymous.

STAFF and ADMIN are not authorized to use the customer submission path.

### Request data

Inspect the exact frozen schema and implement its approved fields:

- `product_id` — optional/nullable opaque product identifier.
- `quantity` — according to the frozen validation rules.
- `name` — contact snapshot.
- `phone` — optional contact channel.
- `email` — optional contact channel.
- `dimensions` — optional structured dimensions.
- `material` — optional free text.
- `color` — optional free text.
- `notes` — request description.
- `attachment` — optional file in multipart submissions.

Do not submit undocumented fields.

In particular, do not submit:

```text
user_id
request_status
request_reference
staff_internal_notes
message
style
product_details
order_id
payment_id
payment_status
quoted_price
delivery_fee
```

The backend maps public `notes` to its internal persistence field. The Flutter client must continue using `notes`.

Do not assume that an optional Dart field is optional in every business context. Confirm exact requiredness, numeric bounds, and string lengths from the Laravel validator and OpenAPI.

---

## 4. Anonymous and authenticated submissions

Anonymous requests are a first-class production feature.

**Never force customers to register before requesting furniture.**

The form must be accessible when:

- Signed out.
- Clerk is initializing.
- Clerk is temporarily unavailable.
- Signed in as an authorized CUSTOMER.

For anonymous submission:

- Do not attach a bearer token.
- Require a name.
- Require at least one reachable contact method: phone or email.

For authenticated CUSTOMER submission:

- Use the current Clerk token through the existing authentication adapter.
- Preserve the self-contained contact snapshot required by REQ-001.
- Do not rely on future profile changes to update an existing request.
- Do not send `user_id`.

If profile prefill is supported, it must be editable and must not overwrite customer-entered values.

Never interpret a broken authenticated session as permission to submit anonymously without the user's explicit choice and an appropriate sign-out flow.

---

## 5. Feature architecture

Follow the established feature-first structure.

Suggested organization:

```text
lib/features/furniture_requests/
  data/
    furniture_request.dart
    furniture_request_draft.dart
    furniture_request_repository.dart
    furniture_request_attachment.dart
  presentation/
    furniture_request_controller.dart
    furniture_request_screen.dart
    furniture_request_success_screen.dart
    widgets/
      contact_fields.dart
      dimensions_fields.dart
      attachment_picker.dart
```

Adapt names and file boundaries to the existing repository conventions.

Reuse:

- ApiClient and its existing multipart support.
- AuthTokenProvider.
- FeatureDependencies.
- AsyncViewState.
- ErrorPresentationMapper.
- AppDiagnostics.
- Material 3 form controls.
- Existing routing and product models.

Do not introduce a second HTTP client, a new state-management framework, or a competing authentication flow.

---

## 6. Form design

The request form should feel approachable, premium, and straightforward.

Recommended section order:

### A. Furniture context

For a catalog-linked request:

- Show a small product image.
- Show the API-returned product name.
- Show a clear indication that the furniture is made to order.
- Preserve the canonical product ID for submission.

For a custom request:

- Show a clear heading such as "Tell us about your furniture idea."
- Do not require a catalog product.
- Do not submit an invented product ID.

Never substitute a slug for the API-required opaque product ID.

### B. Furniture requirements

Provide:

- Quantity, if supported by the contract.
- Dimensions.
- Material preference.
- Color preference.
- Notes/description.

Keep optional fields clearly identified.

### C. Contact information

Provide:

- Full name.
- Phone number.
- Email address.

Explain that customers must provide at least one usable contact method.

### D. Reference attachment

Allow one optional reference image or PDF.

### E. Review and submission

Display a concise review of entered details before the final submission, if consistent with the existing app interaction patterns.

Primary action:

**Submit furniture request**

Do not use "Buy now," "Place order," or "Pay now."

---

## 7. Dimensions

The canonical dimensions representation is:

```json
{
  "length": 200,
  "width": 90,
  "height": 80,
  "unit": "cm"
}
```

Requirements:

- Structured object only.
- Unit exactly `cm`.
- Each supplied dimension must satisfy the backend's positive numeric limits.
- No arbitrary keys.
- No free-text dimensions in the API payload.
- No floating-point precision surprises.
- Clear labels and numeric keyboards.

Inspect whether the contract requires all dimensions together or allows partial values. Match the contract exactly.

Do not send an empty dimensions object.

---

## 8. Material and color

Material and color are free-text inputs, not closed enums.

The decisions establish:

- Material maximum: 500 characters.
- Color maximum: 200 characters.

Do not restrict customers to a fabricated list of fabrics, woods, or finishes.

Optional suggestions may be shown only if they remain editable and do not change the wire contract.

Do not invent structured variant attributes from product names.

---

## 9. Attachments

V1 permits **zero or one attachment** per furniture request.

Supported types:

- JPEG.
- PNG.
- WebP.
- PDF.

Maximum size: **5 MiB**, subject to the exact backend implementation.

### File selection

Use an appropriate maintained Flutter file-selection package only if the existing project has no suitable implementation.

Support selecting a reference photo, drawing, or PDF.

Show:

- Filename.
- File type.
- File size.
- Remove/replace action.

Avoid requesting broad Android storage permissions when the system document picker can provide the file.

### Client validation

Check supported type and size before upload.

Treat client validation as advisory. Laravel remains authoritative for file signatures, content, and storage.

Do not upload attachments to Cloudinary, Cloudflare R2, Firebase, or another service directly.

Request attachments use the backend's private attachment subsystem.

### Inline upload

Prefer a single multipart REQ-001 submission with the `attachment` field.

Use the existing ApiClient multipart implementation.

Preserve documented scalar and dimensions encoding rules for multipart.

Do not assume JSON encoding of nested objects is accepted by the multipart validator.

### Separate upload

REQ-007 exists as a separate capability-based workflow.

Do not implement it unless required by the Phase 17.10 scope and verified as operational.

If implemented, follow the reconciled contract:

- A successful REQ-001 without an inline attachment may return `X-Upload-Token` in the response header.
- The token is scoped to the created request.
- It is time-limited and single-use.
- It is never part of the public JSON resource.
- It must never be logged or persisted insecurely.
- It cannot authorize request reads.
- It must not be reused for another request.

Prefer inline upload to avoid unnecessary multi-step failure handling.

---

## 10. Submission state machine

Model the form's submission lifecycle explicitly:

```text
Editing
   |
   v
Validating
   |
   v
Submitting
   |
   +---- Success ----> Confirmation
   |
   +---- Failure ----> Editable form + error
```

Required behavior:

- Disable duplicate submissions while a request is in flight.
- Preserve entered values on validation and network failures.
- Display field-level errors.
- Allow deliberate retry.
- Prevent accidental double taps.
- Handle route disposal and request cancellation safely.

### Critical uncertain-outcome rule

REQ-001 does not require an Idempotency-Key, and duplicate submissions are not automatically deduplicated.

Therefore, after a timeout or connection loss where the server may have accepted the request:

- Do not automatically retry the POST.
- Do not claim submission failed definitively.
- Show a truthful message explaining that the outcome could not be confirmed.
- Explain that retrying could create another request.
- Require an explicit user decision before resubmission.

Do not introduce an undocumented idempotency header or local deduplication guarantee.

---

## 11. Successful submission

A successful REQ-001 response returns HTTP 201 and a request resource.

The backend controls:

- Opaque request ID.
- Request reference.
- Request status.
- Creation timestamp.
- Ownership.
- Attachment metadata.

New requests begin in:

```text
SUBMITTED
```

### Confirmation screen

Show:

- Clear success heading.
- Furniture/product context when available.
- Request reference only if exposed in the actual public response contract.
- Submission status.
- Brief explanation that the team will review the request and use the supplied contact details.

Do not fabricate a quotation, delivery date, production start, or acceptance decision.

Avoid claiming that email or SMS confirmation was sent unless the system actually supports it.

For anonymous customers, do not show a link to a private request-detail endpoint they cannot access.

---

## 12. Navigation integration

Connect the existing product-detail request action to the new form.

### Catalog-linked flow

```text
Catalog
  → Product Details
  → Request this furniture
  → Furniture Request Form
  → Confirmation
```

### Custom flow

```text
Furniture Requests
  → Custom Furniture Request Form
  → Confirmation
```

Requirements:

- Reuse the existing go_router.
- Preserve product context safely.
- Support direct navigation to the standalone request form.
- Handle missing or invalid product context.
- Do not depend on a global mutable selected-product singleton.
- Preserve back navigation.
- Avoid duplicate routes.

The planned Phase 17.15 global app bar and navigation drawer remains deferred.

Do not implement it here.

---

## 13. Authentication and ownership

Use Clerk only when an authenticated CUSTOMER session is available.

The Laravel backend remains authoritative for user ownership and authorization.

Do not:

- Implement custom authentication.
- Store Clerk tokens in request drafts.
- Submit user IDs.
- Retrieve anonymous requests by guessing IDs.
- Use email equality to establish ownership.
- Expose staff request-management APIs.

REQ-002 and REQ-003 are authenticated customer-owned retrieval operations.

Do not implement a full request-history feature unless the frozen Phase 17.10 scope explicitly includes it.

If history is included, restrict it to authenticated owners and use the canonical `/me/requests` endpoints.

---

## 14. API errors

Reuse the existing typed API error handling.

Handle:

- Missing required fields.
- Invalid field types.
- Invalid phone/email formats.
- Invalid dimensions.
- Invalid product association.
- Unsupported attachment.
- Oversized attachment.
- Authentication failure.
- Authorization failure.
- Rate limiting.
- Connection failure.
- Server failure.

### Field-level errors

Use the backend's canonical error `field` paths to associate errors with form controls.

Do not parse human-readable error messages to infer field identity.

### Rate limiting

Respect HTTP 429 and `Retry-After`.

Show an actionable wait message.

Do not bypass throttling by switching identities or repeatedly resubmitting.

### Server errors

Display safe, understandable messages.

Do not expose raw stack traces, storage paths, tokens, or internal identifiers.

---

## 15. Design-system requirements

Follow:

```text
frontend/design-system/tokens.css
```

Use the existing generated Flutter Material 3 theme.

### Visual direction

- Warm ivory surfaces.
- Calm editorial composition.
- Clear form sections.
- Restrained deep-brown accents.
- Charcoal primary action.
- Young Serif display headings.
- Existing utility sans for controls.
- Minimal elevation.
- No decorative gradients or glassmorphism.

### Form composition

Use a comfortable single-column layout on phones.

Group related fields under clear headings.

Do not overwhelm customers with every optional input simultaneously.

Use meaningful helper text instead of long explanatory paragraphs inside fields.

Do not use fake trust badges, testimonials, or urgency messaging.

---

## 16. Accessibility

Verify:

1. Clear field labels.
2. Required and optional field indications.
3. Accessible error messages.
4. Keyboard navigation.
5. Appropriate text-input types.
6. Visible focus.
7. Correct screen-reader field order.
8. Touch target sizes.
9. 2× text scaling.
10. Narrow-screen layout.
11. Landscape usability.
12. Attachment control semantics.
13. Submission progress announcements.
14. Success confirmation announcements.
15. Reduced-motion behavior.
16. No keyboard-obscured submit action.

Never rely solely on color to indicate invalid fields.

---

## 17. Privacy and security

Furniture requests contain customer contact information and potentially private reference files.

Requirements:

- Do not log names, phone numbers, emails, notes, or dimensions.
- Do not log attachment bytes or filenames.
- Do not log bearer tokens or upload capability tokens.
- Do not persist request drafts containing personal information without explicit approval.
- Do not send request content to analytics.
- Do not place private request data in public route URLs.
- Do not cache private request responses in shared storage.
- Do not expose internal attachment storage keys.

Use only allow-listed diagnostic metadata.

---

## 18. Automated tests

Add deterministic coverage for:

### Form and validation

- Anonymous contact requiredness.
- Authenticated contact snapshot behavior.
- Product-linked requests.
- Custom requests without product ID.
- Quantity validation.
- Structured dimensions.
- Material/color limits.
- Notes validation.
- Optional fields.
- Invalid input recovery.

### Repository

- Correct REQ-001 endpoint.
- JSON submission.
- Multipart submission.
- Public anonymous submission.
- Authenticated CUSTOMER submission.
- Invalid bearer behavior.
- Response parsing.
- 201 success.
- 422 validation errors.
- 401/403 authorization errors.
- 429 Retry-After.
- Connection failure.
- Cancellation.
- No automatic POST retry.

### Attachments

- Valid JPEG.
- Valid PNG.
- Valid WebP.
- Valid PDF.
- Oversized file.
- Unsupported type.
- File removal/replacement.
- Multipart field naming.
- No direct third-party upload.
- No accidental capability-token logging.

### State and navigation

- Initial form.
- Catalog-linked context.
- Standalone custom request.
- Submit loading.
- Duplicate-tap prevention.
- Successful confirmation.
- Failed submission with preserved inputs.
- Uncertain submission outcome.
- Back navigation.
- No private anonymous retrieval.

### Regression

- Catalog.
- Categories.
- Product details.
- Search/filtering.
- Clerk session behavior.
- ApiClient.
- Diagnostics.
- Fixture mode.
- Design token synchronization.
- No cart, checkout, payment, or order functionality.

---

## 19. Laravel integration verification

Verify REQ-001 route activation before claiming end-to-end success.

When the backend is available, test:

1. Anonymous JSON request.
2. Authenticated CUSTOMER JSON request.
3. Catalog-linked request.
4. Custom request without product ID.
5. Multipart request with valid attachment.
6. Invalid product.
7. Invalid dimensions.
8. Invalid contact.
9. Oversized attachment.
10. Rate-limit behavior using safe test fixtures.
11. Request response and status.
12. No Order or Payment creation.

Use disposable test data.

Do not modify production records.

Do not enable gated routes without explicit authorization.

If REQ-001 remains gated, document that limitation precisely.

---

## 20. Marionette real-device verification

When the Android device and Marionette MCP are available:

1. Launch the app.
2. Open a made-to-order product.
3. Tap "Request this furniture."
4. Verify correct product context.
5. Enter furniture requirements.
6. Enter contact information.
7. Select a reference image or PDF.
8. Submit against an approved working backend.
9. Verify success or accurate failure presentation.
10. Open the standalone custom request form.
11. Verify the no-product flow.
12. Test keyboard behavior.
13. Test validation messages.
14. Test navigation and back behavior.
15. Test connection failure.
16. Verify no deferred commerce controls appear.

Do not claim real-device submission success when only fixture UI testing was possible.

---

## 21. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Phase 17.10 completion record.
- `docs/decisions.md` only for new durable decisions.

Document:

- Request form architecture.
- Anonymous/authenticated behavior.
- Product-linked/custom entry points.
- Validation.
- Multipart attachments.
- Submission state handling.
- Uncertain-outcome handling.
- Confirmation UI.
- API readiness.
- Test results.
- Marionette results.
- Known limitations.

Do not rewrite historical phase records.

---

## 22. Verification

Run:

```bash
flutter pub get
dart format --set-exit-if-changed .
flutter analyze
flutter test --concurrency=1
dart run tool/generate_tokens.dart --check
flutter build apk --debug --dart-define-from-file=config/local.json
git diff --check
git diff --cached --check
```

Use existing local configuration and environment safeguards.

Do not commit secrets.

Report actual command results.

---

## 23. Definition of Done

Phase 17.10 passes when:

- Furniture request form is implemented.
- Catalog-linked and custom entry points work.
- Anonymous customers are supported.
- Authenticated CUSTOMER submissions use Clerk correctly.
- The REQ-001 request model matches the frozen API.
- Client-side validation improves usability without replacing backend validation.
- Optional private attachment selection and multipart upload work.
- Submission progress and errors are handled.
- Duplicate automatic POST retries are prevented.
- Successful submissions show accurate confirmation.
- Product detail navigation is integrated.
- Accessibility requirements are met.
- Existing tests remain green.
- New tests pass.
- Token synchronization passes.
- Debug APK builds.
- Documentation is updated.
- No deferred commerce features are introduced.

If backend gating prevents real submission, mark the implementation **Flutter PASS / End-to-end BLOCKED**, not unconditional PASS.

## 24. Completion report

Report:

1. Repository inspection findings.
2. Exact REQ-001 fields and validation rules.
3. Backend route activation status.
4. Feature architecture.
5. Product-linked request flow.
6. Custom request flow.
7. Authentication handling.
8. Attachment implementation.
9. Submission/error handling.
10. Success confirmation.
11. Automated test results.
12. Laravel verification.
13. Marionette verification.
14. Files changed.
15. Documentation updates.
16. Remaining blockers.
17. Final PASS/FAIL assessment.

**Final instruction:** Deliver the complete request-only furniture intake experience using the existing Flutter architecture and frozen Laravel REQ-001 contract. Preserve anonymous access, private attachments, backend authority, and all deferred commerce boundaries. Stop after Phase 17.10.