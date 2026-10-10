# Phase 17.11 — Flutter Contact & Enquiries

**Project:** SL Furnitures  
**Application:** Flutter Android Customer App  
**Group:** Q — Flutter Customer Features  
**Phase:** 17.11 — Enquiries  
**Prerequisites:** Phases 16.1–16.9, 17.1–17.4, 17.10  
**Implementation approach:** Existing architecture first, frozen Laravel API, no commerce activation

## 1. Objective

Implement the complete Contact & Enquiries feature for the request-only production launch.

Replace the existing `/contact` placeholder with a functional, polished, accessible enquiry screen.

The feature must support:

1. Anonymous visitor enquiries.
2. Authenticated CUSTOMER enquiries.
3. General business questions.
4. Optional catalog-product context where the API permits it.
5. Contact information.
6. Subject and message.
7. Optional private attachment.
8. Validation and safe API submission.
9. Loading, error, and success states.
10. Explicit recovery from uncertain submission outcomes.

The feature must not create furniture requests, orders, quotations, payments, reservations, or customer support chat sessions.

Keep Phase 17.10 furniture requests separate from enquiries.

---

## 2. Inspect the repository before implementation

Read:

- Root `AGENTS.md`.
- `frontend/AGENTS.md`.
- `frontend/app/README.md`.
- `frontend/app/lib/features/README.md`.
- `docs/decisions.md`.
- `docs/api/api-contract.md` section 27.
- `docs/api/api-resources.md`.
- `docs/api/openapi.yaml`.
- `docs/api/api-conventions.md`.
- `frontend/design-system/DESIGN.md`.
- `frontend/design-system/ACCESSIBILITY.md`.
- `frontend/design-system/tokens.css`.
- Phase 17.10 implementation and completion documentation.
- Existing Flutter enquiry placeholder.
- Existing `lib/navigation/` route registry.
- Existing `FeatureDependencies`.
- Existing ApiClient and multipart support.
- Existing ClerkAuthAdapter and AuthSession.
- Existing furniture request form, attachment picker, repository, controller, and tests.
- Laravel ENQ-001 route, controller, FormRequest, service, resource, rate limiter, attachment handling, and feature tests.

### Required inspection report

Before coding, identify:

- Exact ENQ-001 request fields.
- Required and nullable fields.
- Validation bounds.
- Anonymous and authenticated contact rules.
- Product and order association validation.
- Multipart encoding requirements.
- Attachment limits.
- Response representation.
- Error codes and field paths.
- Backend route activation status.
- Whether ENQ-007 is operational.
- Existing reusable Flutter form components.
- Existing request-submission state handling.

Do not infer contract details from the furniture request form.

The Enquiry API is a separate resource with different validation.

---

## 3. Frozen API contract

Use:

POST /api/v1/enquiries

This is ENQ-001.

Do not create:

- `/contact/submit`
- `/messages`
- `/support/tickets`
- `/customer/enquiries`
- A Next.js proxy endpoint
- A Firebase collection
- A direct database connection

The existing Laravel API is authoritative.

### Canonical enquiry fields

Inspect the frozen schema for:

- `subject`
- `message`
- `name`
- `phone`
- `email`
- `product_id`
- `order_id`
- `attachment`

Only include fields supported by ENQ-001.

Do not send server-controlled fields:

- `user_id`
- `enquiry_status`
- `staff_internal_notes`
- `created_at`
- `updated_at`
- `reference`
- `payment_id`
- `payment_status`
- `order_status`
- `create_order`

Unknown fields must not be silently added.

### Response handling

Parse the canonical `data` response envelope.

Use the backend's actual enquiry representation, including its opaque ID, reference, status, and timestamps where documented.

Do not invent response fields.

---

## 4. Anonymous submission

The contact page must work without Clerk authentication.

Anonymous visitors must provide:

- Name.
- At least one reachable contact method: phone or email.
- Valid subject.
- Valid message.

Do not force account creation.

Do not redirect anonymous visitors to `/account`.

Do not require a Clerk token.

Do not use an email address as proof of ownership.

### Authenticated submission

Authenticated CUSTOMER submissions use the existing Clerk session boundary.

Laravel derives ownership from the verified token.

Contact information may be supplied or derived according to the frozen ENQ-001 contract.

If profile prefill is available, the customer must be able to edit the submitted contact snapshot.

Never send a client-controlled user ID.

### Authentication failures

An invalid authenticated session must not silently become an anonymous submission.

Respect the existing authentication-state policy.

Keep public contact access available when Clerk initialization is unavailable.

---

## 5. Feature architecture

Create the feature inside:

lib/features/enquiries/

Follow the existing feature-first convention.

Suggested structure:

lib/features/enquiries/
  data/
    enquiry_draft.dart
    enquiry_submission.dart
    enquiry_repository.dart
  presentation/
    enquiry_controller.dart
    enquiry_screen.dart
    enquiry_success_screen.dart
    widgets/
      enquiry_contact_fields.dart
      enquiry_attachment_field.dart

Adapt the structure to the actual repository.

Small features must not introduce unnecessary layers.

### Dependencies

Use constructor injection through `FeatureDependencies`.

Reuse:

- ApiClient.
- ApiAuthMode.
- AuthSession.
- RequestCancellation.
- AsyncViewState.
- ErrorPresentationMapper.
- AppDiagnostics.
- Existing theme.
- Central go_router.

Do not instantiate a new HTTP client.

Do not initialize Clerk inside this feature.

Do not read secure storage directly.

Do not create another global state manager.

### Phase 17.10 reuse

Inspect the furniture request implementation for reusable mechanisms:

- Attachment file selection.
- File-size/type validation.
- Multipart assembly.
- Submission progress state.
- Field error mapping.
- Uncertain-outcome handling.
- Duplicate-submit prevention.

Reuse common mechanics without coupling the Enquiry domain to the Furniture Request domain.

If shared extraction is justified, place domain-neutral utilities in the appropriate shared core layer.

Do not move furniture request models into enquiries or vice versa.

Avoid a generic, configurable "everything form" abstraction.

---

## 6. Contact screen design

Use the existing `/contact` route.

Recommended page hierarchy:

1. Introductory heading.
2. Short explanatory copy.
3. Contact information.
4. Enquiry details.
5. Optional reference attachment.
6. Submit action.
7. Submission feedback.

### Suggested copy

Heading:

"How can we help?"

Supporting text:

"Have a question about our furniture, services, or an existing enquiry? Send us a message and our team will get back to you."

Do not promise a specific response time unless the business has an approved response-time policy.

### Form fields

Contact:

- Full name.
- Phone number.
- Email address.

Enquiry:

- Subject.
- Message.

Context:

- Optional related product, when available and valid.

Attachment:

- Optional image or PDF.

### Form behavior

- Clear labels.
- Appropriate keyboard types.
- Visible required/optional indicators.
- Character limits where useful.
- Clear inline validation.
- Keyboard-safe scrolling.
- Disabled duplicate submission while in flight.

Do not use a large multi-step wizard for this simple contact form.

---

## 7. Subject and message

The frozen enquiry contract specifies:

- Subject: 5–200 characters.
- Message: 10–5,000 characters.

Implement these bounds.

### Subject

Use a plain-text single-line field.

Example placeholder:

"What would you like to ask?"

Do not add a new closed subject-category enum unless the backend contract supports it.

### Message

Use a multiline plain-text field.

Example placeholder:

"Tell us how we can help..."

Preserve meaningful line breaks.

Do not submit rich text, HTML, Markdown, or executable content.

Do not implement message threads or customer/staff replies.

The original enquiry is immutable after submission.

---

## 8. Optional product association

The ENQ-001 contract permits an optional product reference.

This is useful for questions such as:

- Is this furniture available in another finish?
- Can you explain the materials used?
- What are the delivery options for this product?

### Requirements

If a valid product context is provided:

- Use the server-returned opaque product ID.
- Show the product name.
- Reuse the existing CatalogImage.
- Preserve the distinction between an enquiry and a furniture request.

Do not derive a product ID from its display name.

Do not create a second product-detail repository.

Do not automatically include a product association merely because the user previously browsed a product.

### Navigation

A product-related enquiry entry point may be added where it fits the existing product-detail UI.

Do not replace or weaken the Phase 17.10 "Request this furniture" action.

If adding a new product-detail enquiry action is not required by the frozen Phase 17.11 scope, keep product context support internal and document it for future integration.

---

## 9. Order association

The API supports an optional order association under its ownership rules.

However, order and order-tracking features are deferred for this production release.

Therefore:

- Do not expose an order selector.
- Do not create an order search UI.
- Do not request order history.
- Do not accept arbitrary customer-entered order IDs as an ownership bypass.
- Do not activate order support functionality.

Preserve the frozen API contract for future phases.

---

## 10. Optional attachments

Enquiries support zero or one private attachment.

Reuse the existing Phase 17.10 file-picker and multipart infrastructure.

Supported formats:

- JPEG.
- PNG.
- WebP.
- PDF.

Maximum size:

- 5 MiB, subject to confirmation against the backend's precise byte limit.

### Requirements

- File selection.
- Filename display.
- File size display.
- Remove/replace action.
- Client-side type and size checks.
- Server-authoritative validation.
- Accessible error feedback.
- No broad storage permission if Android's document picker is sufficient.

Use the canonical multipart field:

`attachment`

### Privacy

Attachments are private.

Never expose:

- Internal storage paths.
- Private object keys.
- Permanent public attachment URLs.
- Raw attachment bytes in diagnostics.
- Upload tokens in logs.

### Separate upload

Prefer inline ENQ-001 multipart submission.

ENQ-007 separate attachment upload is not required for this phase unless the existing scope explicitly calls for it.

If implemented, use the server-issued, single-use, time-limited capability-token contract and verify that the backend supports it.

Do not construct an upload token locally.

---

## 11. Enquiry submission state

Use a simple explicit lifecycle:

Editing → Validating → Submitting → Success or Recoverable Failure

### Duplicate submission prevention

Disable the submit action while a submission is in flight.

Do not dispatch multiple requests from rapid taps.

### Successful submission

A confirmed successful response transitions to the confirmation view.

### Validation failure

Keep all entered values and display relevant field errors.

### Rate limiting

Handle HTTP 429 using the existing Retry-After representation.

Do not invent a countdown or deadline when the server does not provide one.

### Uncertain outcome

A timeout or connection interruption during a non-idempotent POST may occur after Laravel accepted the enquiry.

Do not automatically retry.

Do not display "Your enquiry was not submitted" when the outcome is unknown.

Instead, explain that confirmation was not received and another submission might create a duplicate.

Require deliberate user action before trying again.

### Cancellation

Cancellation must not be reported as a server-side rejection.

A local cancellation cannot prove that Laravel did not persist the enquiry.

---

## 12. Success confirmation

After a confirmed successful submission, show a clear confirmation screen.

Suggested copy:

"Your enquiry has been received."

Supporting copy:

"Thank you for contacting SL Furnitures. Our team will review your message and use the contact details you provided to respond."

Display only documented response fields.

If the API returns a public-safe reference, it may be shown.

Do not invent a reference.

Do not claim that an email or SMS confirmation was sent.

Do not promise a reply within a fixed period.

### Anonymous privacy

Anonymous users must not receive automatic access to a private enquiry-detail endpoint.

Do not navigate to `/enquiries/{id}`.

A response identifier is not an authentication credential.

---

## 13. Enquiry status

The backend uses a closed status model:

- OPEN.
- CLOSED.

A newly submitted enquiry begins as OPEN.

The Flutter submission UI may show the status if returned by the API.

Do not allow customers to:

- Change status.
- Close enquiries.
- Reopen enquiries.
- Assign staff.
- Edit the original message.
- Add staff notes.

Those are not customer intake operations.

---

## 14. Error handling

Reuse `ErrorPresentationMapper` and shared error components.

Handle:

- Missing subject.
- Invalid subject length.
- Missing message.
- Invalid message length.
- Missing anonymous contact.
- Invalid email.
- Invalid phone.
- Invalid product association.
- Invalid order association, if a caller supplies one.
- Unsupported attachment.
- Oversized attachment.
- HTTP 401.
- HTTP 403.
- HTTP 422.
- HTTP 429.
- HTTP 500/502/503/504.
- Transport failures.
- Timeouts.
- Cancellation.

### Field-level errors

Map canonical backend error field paths to the corresponding controls.

Do not parse English error messages to identify fields.

Preserve request IDs for safe support correlation.

Do not display raw response bodies or stack traces.

---

## 15. Diagnostics

Reuse the existing diagnostics boundary.

The README confirms that ApiClient already records eligible transport failures exactly once.

Do not add duplicate feature-level events for the same API failures.

Use only existing diagnostic codes and allow-listed metadata.

Never log:

- Name.
- Email.
- Phone.
- Subject.
- Message.
- Product ID.
- Order ID.
- Attachment filename.
- File bytes.
- Bearer token.
- Upload capability token.
- Raw request body.
- Raw exception text.

Keep production diagnostics no-op according to the existing policy.

---

## 16. Design-system compliance

The canonical visual authority is:

frontend/design-system/tokens.css

Use the existing generated Flutter Material 3 theme.

### Design principles

- Warm editorial presentation.
- Clean form structure.
- Comfortable vertical spacing.
- Restrained typography.
- Strong primary action.
- Minimal decorative elements.
- Clear error and success feedback.

Use Young Serif only for appropriate display headings.

Use the existing utility sans for form labels, inputs, and helper text.

### Do not introduce

- Hard-coded colors.
- Arbitrary spacing scales.
- New font packages.
- Decorative gradients.
- Glassmorphism.
- Fake customer testimonials.
- Unverified office addresses.
- Invented business hours.
- Invented phone numbers.
- Invented response-time promises.

If business contact information is already approved and present in the repository, it may be displayed.

Otherwise, keep the page focused on the functional enquiry form.

---

## 17. Accessibility

Verify:

1. Semantic page heading.
2. Accessible field labels.
3. Required/optional field clarity.
4. Keyboard types.
5. Multiline input accessibility.
6. Validation announcements.
7. Visible focus.
8. Logical traversal order.
9. Screen-reader attachment selection.
10. Submission progress semantics.
11. Success announcements.
12. 2× text scaling.
13. Small Android screen layouts.
14. Landscape usability.
15. Keyboard-safe scrolling.
16. Touch targets.
17. Contrast.
18. Reduced-motion preferences.

Do not rely solely on color for errors.

---

## 18. Navigation integration

Replace the existing `/contact` placeholder.

Keep the route public.

Use the existing central go_router.

Required navigation:

- Open Contact & Enquiries.
- Complete the enquiry form.
- Submit.
- View confirmation.
- Return to the home/catalog experience.

Do not create another MaterialApp or route registry.

Do not add a global drawer or app-wide navigation bar.

The complete shared navigation shell remains planned for Phase 17.15.

### Furniture request distinction

If appropriate, include a small secondary link:

"Want furniture made to your specifications? Submit a furniture request."

Navigate to the existing Phase 17.10 route.

Do not merge both forms.

---

## 19. Backend readiness

Inspect the Laravel feature flag or route activation configuration for ENQ-001.

Confirm whether:

POST /api/v1/enquiries

is operational.

Do not assume it is enabled because REQ-001 was enabled.

If disabled, report the precise blocker.

Do not silently activate backend routes or change Laravel feature flags without authorization.

Do not create a mock successful response in API mode.

---

## 20. Automated tests

Add deterministic tests for:

### Models and validation

1. Subject minimum length.
2. Subject maximum length.
3. Message minimum length.
4. Message maximum length.
5. Anonymous name required.
6. Anonymous phone/email requirement.
7. Valid email.
8. Invalid email.
9. Valid phone.
10. Invalid phone.
11. Optional product association.
12. Unsupported order association handling.
13. Server-controlled field exclusion.

### Repository

14. Correct ENQ-001 endpoint.
15. Anonymous JSON submission.
16. Authenticated submission.
17. No user_id injection.
18. JSON body encoding.
19. Multipart body encoding.
20. Successful response parsing.
21. Field-level validation errors.
22. Rate limiting.
23. Authentication errors.
24. Transport failure.
25. Timeout.
26. No automatic retry.

### Attachments

27. JPEG selection.
28. PNG selection.
29. WebP selection.
30. PDF selection.
31. Oversized file.
32. Unsupported type.
33. Remove attachment.
34. Replace attachment.
35. Correct multipart field.
36. No direct public upload.

### State

37. Initial editing.
38. Invalid submit.
39. Valid submit.
40. Duplicate-tap prevention.
41. Confirmed success.
42. Server validation failure.
43. Network failure.
44. Uncertain outcome.
45. Explicit retry decision.
46. Disposal/cancellation.

### Widgets and navigation

47. Contact route.
48. Form labels.
49. Keyboard behavior.
50. Inline errors.
51. Attachment controls.
52. Loading feedback.
53. Success confirmation.
54. Return navigation.
55. Anonymous route access.
56. Accessibility semantics.
57. Text scaling.

### Regression

Verify that:

- Phase 17.10 requests still work.
- Product details still work.
- Search/filtering still works.
- Public catalog remains anonymous.
- Clerk boundaries remain unchanged.
- Existing multipart tests pass.
- Existing diagnostics behavior remains correct.
- Deferred commerce routes remain absent.

The matrix describes coverage, not a mandatory one-test-per-number file structure.

---

## 21. Live Laravel verification

When ENQ-001 is active and Laravel is available, verify:

1. Anonymous valid JSON enquiry.
2. Authenticated CUSTOMER enquiry where a valid Clerk test session exists.
3. Missing contact validation.
4. Subject validation.
5. Message validation.
6. Optional product association.
7. Multipart attachment.
8. Invalid attachment.
9. Rate limiting.
10. Successful response.
11. OPEN initial status.
12. No furniture request creation.
13. No order creation.
14. No payment or inventory effects.

Use safe disposable development data.

Do not use production customer information.

Do not claim authenticated testing if no Clerk sign-in workflow exists.

---

## 22. Marionette real-device testing

Use Marionette MCP on a connected physical Android device when available.

Test:

1. Launch the application.
2. Navigate to Contact.
3. Confirm the placeholder is replaced.
4. Enter a valid subject.
5. Enter a valid message.
6. Enter contact information.
7. Trigger validation errors.
8. Correct errors.
9. Select an image or PDF.
10. Remove and replace the attachment.
11. Submit against a working Laravel backend.
12. Verify confirmed success.
13. Verify error recovery.
14. Test offline/connection-failure presentation.
15. Check keyboard overlap.
16. Check narrow-screen scrolling.
17. Check accessibility semantics where supported.
18. Return to home.
19. Open Furniture Requests and verify that the existing flow still works.

Do not claim physical-device verification unless actually performed.

---

## 23. Documentation

Update:

- Flutter README.
- `lib/features/README.md`.
- Phase 17.11 completion record.
- `docs/decisions.md` only if a new durable decision is necessary.

Document:

- Feature architecture.
- ENQ-001 contract mapping.
- Anonymous and authenticated flows.
- Contact validation.
- Attachment behavior.
- Product association.
- Error handling.
- Uncertain-outcome behavior.
- Backend activation.
- Test results.
- Marionette verification.
- Remaining limitations.

Do not rewrite completed Phase 17.10 history.

---

## 24. Verification commands

Run from `frontend/app`:

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

Report the actual results.

Do not claim success for commands that were not run.

---

## 25. Definition of Done

Phase 17.11 is complete when:

- The Contact placeholder is replaced with a real enquiry form.
- ENQ-001 is integrated.
- Anonymous visitors can submit enquiries.
- Authenticated customers use the existing Clerk bearer boundary.
- Subject and message validation match the frozen contract.
- Contact information is validated.
- Optional product context is handled correctly.
- Optional private attachments work.
- Duplicate submissions are prevented.
- Uncertain POST outcomes are handled truthfully.
- Successful submissions show confirmation.
- Field errors and rate limits are handled.
- The existing router remains authoritative.
- Accessibility requirements are met.
- Existing Flutter tests remain green.
- New tests pass.
- The debug APK builds.
- Design token synchronization passes.
- Documentation is updated.
- Furniture Requests remain unchanged.
- No deferred commerce features are activated.

If backend gating prevents live submission, report:

**Flutter implementation PASS / Live integration BLOCKED**

Do not claim unconditional end-to-end PASS.

---

## 26. Completion report

Return a structured report containing:

1. Repository inspection findings.
2. Exact ENQ-001 contract fields.
3. Backend activation status.
4. Feature architecture.
5. Anonymous submission behavior.
6. Authenticated submission behavior.
7. Form and validation implementation.
8. Attachment handling.
9. Navigation integration.
10. Success/error behavior.
11. Security and privacy verification.
12. Automated test results.
13. Live Laravel test results.
14. Marionette device results.
15. Files changed.
16. Documentation updated.
17. Known limitations.
18. Final PASS/FAIL assessment.

**Final instruction:** Implement only Phase 17.11. Reuse the existing Flutter architecture and Phase 17.10 submission mechanics while keeping Enquiries a separate domain. Respect the frozen ENQ-001 API, anonymous access, private attachments, and request-only production scope. Stop after completing and verifying this phase.