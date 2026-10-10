# Phase 17.11 — Flutter Contact & Enquiries — Completion Record

**Project:** SL Furnitures — Flutter Android Customer App
**Group:** Q — Flutter Customer Features
**Prerequisites:** Phases 16.1–16.9, 17.1–17.4, 17.10
**Status:** IMPLEMENTED AND VERIFIED (live Laravel, anonymous paths)

---

## 1. Repository inspection findings

| Finding | Result |
| --- | --- |
| Contact placeholder | `/contact` was a `_RoutePlaceholder(title: 'Contact')` in `lib/navigation/app_router.dart`; `AppRoutes.contact` already existed and is not a protected path. |
| Laravel ENQ-001 route | `POST api/v1/enquiries` in `routes/api.php`, middleware `clerk.optional`, `customer-submission`, `enquiries.enabled`, `throttle:anonymous-submit`. |
| Activation flag | `config/enquiries.php → route_enabled = true`. ENQ-001 is **enabled**; this was verified directly, not inferred from REQ-001. |
| ENQ-007 | Registered and operational, but not used. Inline multipart on ENQ-001 is the contract-preferred transport and satisfies this phase. |
| Rate limiter | `anonymous-submit` = 3/minute keyed on `ip` for anonymous callers, 10/minute keyed on local `users.id` for authenticated ones. |
| Response resource | `EnquiryResource` — `id`, `name`, `email`, `phone`, `subject`, `message`, `category`, `product_id`, `product`, `order_id`, `order`, `enquiry_status`, `attachments[]`, `created_at`, `updated_at`. No staff notes, no credentials, no `user_id`. |
| `EnquiryIdentifier::encode` | Opaque `enq_` base36 public reference. It is not a retrieval credential. |
| Reusable Flutter pieces | `ApiClient` (JSON + `ApiMultipartBody`, `ApiAuthMode`, `Retry-After`, request IDs), `AuthSession`, `RequestCancellation`, `ErrorPresentationMapper`, `CatalogImage`, `AppSpacing`, `AppMedia`, central `go_router`. |
| Request-submission pattern | Phase 17.10's controller lifecycle, field-error mapping, duplicate-tap guard, and uncertain-outcome wording were the reference; the Enquiry domain does not import them. |

## 2. Exact ENQ-001 contract mapping

Verified against `docs/api/api-contract.md §27`, `CreateEnquiryRequest`,
`Enquiry` model bounds, `config/attachments.php`, and the live endpoint.

| Field | Sent by this client | Rule implemented |
| --- | --- | --- |
| `subject` | Always | Required, trimmed, 5–200 chars |
| `message` | Always | Required, trimmed, 10–5000 chars, plain text, newlines preserved |
| `name` | When non-empty | Required for anonymous; optional/derived for authenticated; max 120 |
| `phone` | When non-empty | Optional; max 30; must match `/^\+?[0-9][0-9 ().-]{6,29}$/` |
| `email` | When non-empty | Optional; at least one of phone/email for anonymous; max 255; lowercased |
| `product_id` | When a product context exists | Optional; server validates the product is public. No `product_type` restriction |
| `attachment` | Multipart only, 0 or 1 | Field name `attachment`; JPEG/PNG/WebP/PDF; 5 MiB (`config('attachments.max_bytes')`) |

**Never sent:** `user_id`, `enquiry_status`, `staff_internal_notes`,
`internal_notes`, `order_id`, `category`, `created_at`, `updated_at`,
`reference`, `payment_id`, `payment_status`, `order_status`, `create_order`.
Empty optional values are omitted rather than sent as `""`. A repository test
asserts each prohibited key is absent from the encoded body.

**Deliberately not modelled:**

- `category` — the backend accepts the CLOSED set
  `GENERAL|PRODUCT|DELIVERY|OTHER`, but it is optional and §27.5 states staff
  can triage from `subject`/`message` alone. The phase's form design (§6) does
  not specify a control, so no selector and no dead field were added.
- `order_id` — order features are deferred for this release (§9). No selector,
  no search UI, and no customer-entered identifier, so the field can never be
  used as an ownership bypass.

**Response handling:** only `data.id` and `data.enquiry_status` are decoded. A
payload missing either is treated as an invalid response by `ApiClient`, not
silently accepted.

## 3. Feature architecture

```text
EnquiryScreen  (public /contact)
      |
      v
EnquiryController   editing -> submitting -> success | uncertain
      |
      v
EnquiryRepository (ApiEnquiryRepository) -> ApiClient -> Laravel ENQ-001
```

```text
lib/core/attachments/pending_attachment.dart          (domain-neutral, shared)
lib/features/enquiries/data/enquiry_draft.dart        (draft, product context,
                                                         response, validation)
lib/features/enquiries/data/enquiry_repository.dart   (interface + API impl)
lib/features/enquiries/presentation/enquiry_controller.dart
lib/features/enquiries/presentation/enquiry_screen.dart
lib/features/enquiries/presentation/enquiry_success_view.dart
lib/features/enquiries/presentation/widgets/enquiry_attachment_field.dart
```

Reused, not duplicated: `ApiClient`, `ApiAuthMode`, `AuthSession`,
`RequestCancellation`, `ErrorPresentationMapper`, `CatalogImage`, the generated
Material 3 theme, and the central `go_router`. No second HTTP client, no Clerk
initialization inside the feature, no secure-storage access, no global state
manager, and no second `MaterialApp` or route registry.

`core/attachments/pending_attachment.dart` is the domain-neutral extraction the
phase calls for: the frozen 5 MiB limit, the allowed content types, the
canonical `attachment` field name, and the extension hint. Phase 17.10 keeps its
own equivalent because §25 requires Furniture Requests to remain unchanged;
consolidating the two is left to a later phase and is recorded here rather than
done silently.

## 4. Anonymous submission

`/contact` is public and is not in `AppRoutes.isProtectedPath`. An anonymous
visitor supplies name, at least one of phone/email, a valid subject, and a valid
message. No account is required, no redirect to `/account` happens, and no Clerk
token is requested — the repository uses `ApiAuthMode.public`, which sends no
`Authorization` header. An email address is never treated as proof of ownership.

Verified live: an anonymous JSON enquiry returned `201` with
`user_id = NULL`.

## 5. Authenticated submission

The repository uses `ApiAuthMode.required`, so `ApiClient` attaches
`Authorization: Bearer <Clerk session token>` through the existing
`ClerkAuthAdapter`. Laravel derives `user_id` from the verified token; the client
never sends a user identifier.

`validateEnquiry` applies the contract's actor-aware contact rule: an
authenticated customer may omit `name`/`phone`/`email` because Laravel derives
them from the account, while an anonymous visitor must supply a name and a
reachable channel. Typed contact is a self-contained snapshot the customer can
edit before sending; no profile prefill is implemented because no `/me` read
exists in this app yet.

**A signed-in session that cannot produce a token never silently becomes an
anonymous enquiry.** The controller stops the submission, stays in `editing`,
and asks the customer to sign in again or explicitly sign out. Public contact
access still works when Clerk initialization is unavailable, because the screen
is constructed with an optional `AuthSession`.

## 6. Form and validation

Hierarchy: heading, supporting copy, optional product context, enquiry details
(subject, message), contact details (name, phone, email), optional reference
attachment, submit action, submission feedback, and a secondary link to the
Phase 17.10 furniture request route. It is a single scrollable form, not a
wizard.

Client validation is advisory only — Laravel re-validates every rule. Bounds are
enumerated in §2. Phone and email formats mirror Laravel's `PhoneNumber` pattern
and `FILTER_VALIDATE_EMAIL` behaviour so the customer is not told a value is fine
when the server will reject it.

Field errors are mapped by the canonical backend `field` path onto the matching
control. No English message is parsed to identify a field. Server message text is
shown; raw bodies, stacks, and framework details never are.

## 7. Attachments

One optional attachment, sent inline on the same `multipart/form-data`
ENQ-001 request under the canonical `attachment` field. `ENQ-007` is not used and
no upload token is constructed locally.

Selection uses the Android document picker through `file_picker`, so no broad
storage permission is requested. The UI shows the file name and size and offers
replace and remove actions, and the button is disabled while a submission is in
flight. Client-side type and size checks are advisory; Laravel re-detects the
content type with `finfo`, verifies the magic-byte signature, and sanitizes the
filename.

Privacy: bytes stay in memory and are never written to app storage, logged, or
displayed. Only the file name and size reach the screen. No storage key, private
object key, or public attachment URL is handled by the client — the response
returns `url: null`.

## 8. Product association

Product context is supported internally. `AppRouter._enquiryProduct` builds an
`EnquiryProductContext` only from a `ProductDetail` already loaded through
CAT-002 and passed as route `extra`; the server-returned opaque `id` is used
directly and the name is display-only. The card reuses `CatalogImage` and shows
the product name.

Per §8's instruction to keep this internal and documented for future
integration, **no product-detail enquiry button was added** and the Phase 17.10
"Request this furniture" action is untouched. No identifier is derived from a
name, browsing history never attaches a product implicitly, and no second
product-detail repository exists.

## 9. Navigation integration

The `/contact` route now builds `EnquiryScreen` through the existing central
`go_router`. It stays public, falls back to the previous placeholder when no
repository is wired (for example fixture-only builds), and returns to the home
catalog from the confirmation view. `Done` pops when there is history to pop and
otherwise goes home.

The form ends with a secondary link — "Want furniture made to your
specifications? Submit a furniture request." — that navigates to the existing
`/furniture-requests` route. The two forms are never merged.

## 10. Success and error behavior

**Confirmation** (only after a confirmed `201`): "Your enquiry has been
received.", the approved supporting copy, the returned reference, and the
returned status. It makes no claim that an email or SMS was sent, promises no
response time, and never navigates to `/enquiries/{id}` — a response identifier
is not a credential.

**Lifecycle:** `editing → submitting → success | uncertain`. Validation failure
keeps every entered value and highlights the controls. The submit action is
disabled while in flight, so rapid taps cannot dispatch a second POST. A `429`
uses the server's `Retry-After` value when present and invents no countdown when
it is absent.

**Uncertain outcome:** a timeout or connection interruption during this
non-idempotent POST may have been accepted by Laravel. The app says confirmation
was not received and that sending again may create a duplicate — it never says
the enquiry was not submitted, and it never retries automatically. The warning
survives editing so that a second submission is always a deliberate act. A local
cancellation returns to `editing` with no message, because a cancelled request
proves nothing about whether Laravel persisted it.

Handled failures: 401, 403, 404, 409, 413, 422, 429, 5xx, transport failure,
timeout, and cancellation.

## 11. Security and privacy verification

- No `user_id`, ownership, role, or permission is client-supplied.
- Ownership is derived by Laravel from the verified Clerk token.
- The repository builds an explicit allow-list, so Laravel's strict
  unknown-field rejection is never tripped by this client.
- Contact details, subject, message, product ID, attachment filename, and bytes
  are never recorded in diagnostics. `ApiClient` already records eligible
  failures exactly once, so no duplicate feature-level event was added.
- No storage key, private object key, permanent attachment URL, upload token, or
  bearer token is read, stored, or displayed.
- No new dependency, no insecure storage, no custom cryptography, and no second
  HTTP client.
- Public catalog, product detail, and enquiry access remain anonymous; nothing
  in this phase activates a deferred commerce surface.

## 12. Automated test results

69 new tests across four files, all passing; the full suite is 486 tests.

| Area | Coverage |
| --- | --- |
| `enquiry_validation_test.dart` | Subject/message minimum and maximum, anonymous name and contact requirements, authenticated contact rule, valid and invalid email, valid and invalid phone, name/email bounds, optional product association, general enquiry without a product, every supported attachment type, unsupported type, oversized file, extension mapping, single-attachment replacement/clear, response decoding and rejection |
| `enquiry_repository_test.dart` | ENQ-001 endpoint, anonymous JSON with no bearer header, authenticated bearer header, trimmed allow-listed body, prohibited-field exclusion, response parsing, canonical multipart field, multipart field parity with JSON, no second request, field-level errors, `Retry-After`, 401, transport failure with no automatic retry, non-envelope rejection |
| `enquiry_controller_test.dart` | Initial editing, invalid submit with no request, valid submit, duplicate-tap prevention, session without a token not degrading to anonymous, authenticated flag propagation, server field-error mapping, rate limit with and without `Retry-After`, connection failure and timeout as uncertain, cancellation not reported as rejection, uncertain warning surviving an edit, explicit retry, oversized attachment blocking submit, disposal cancelling an in-flight request, product context |
| `enquiry_screen_test.dart` | Contact route rendering, public access with no sign-in, inline field errors with values preserved, anonymous contact requirement, confirmed success against a stub API, submit disabled in flight, rate-limit copy, uncertain copy, furniture-request link without merging forms, attachment control and privacy note, semantic page heading, 2x text scaling on a narrow screen, return navigation, placeholder fallback |

**Defect found and fixed during testing:** a submission completing after the
screen was disposed called `notifyListeners()` on a disposed controller, which
throws in debug builds. `EnquiryController` now guards notification with a
disposal flag and cancels the in-flight request; `disposal cancels an in-flight
submission` is the regression test.

## 13. Live Laravel verification

`php artisan serve` on `127.0.0.1:8000` against the seeded development database.
All data was disposable and synthetic. The temporary probe test was deleted
afterwards.

| # | Check | Result |
| --- | --- | --- |
| 1 | Anonymous valid JSON enquiry | `201`, `Cache-Control: private, no-store`, `X-Upload-Token` issued, `X-RateLimit-Limit: 3` |
| 2 | Missing anonymous contact | `422 MISSING_REQUIRED_FIELD` `field: name` |
| 3 | Subject below minimum | `422 INVALID_VALUE` `field: subject` |
| 4 | Message below minimum | `422 INVALID_VALUE` `field: message` |
| 5 | Client-sent `user_id` | `422 INVALID_VALUE` `field: user_id` — confirms strict unknown-field rejection |
| 6 | Optional product association | `201`, `product_id: prod_2`, embedded `product{id,name,slug}` |
| 7 | Multipart attachment | `201`, `attachments[]{id, filename, content_type, size, url: null}` |
| 8 | Unsupported attachment type | `422 UNSUPPORTED_ATTACHMENT_TYPE` `field: attachment` |
| 9 | Spoofed type (text bytes declared `image/png`) | `422 UNSUPPORTED_ATTACHMENT_TYPE` — server signature check wins |
| 10 | Rate limiting | `429 RATE_LIMITED` observed on the 4th anonymous submission in a minute |
| 11 | Initial status | `enquiry_status: OPEN` on every created enquiry |
| 12 | No furniture request created | `furniture_requests` unchanged at 3, all created 2026-10-09 before this phase |
| 13 | No order created | `orders = 0` |
| 14 | No payment, cart, or inventory effect | `payments = 0`, `carts = 0`, variant rows unchanged |

**Dart client end-to-end.** A temporary Flutter test drove the real
`ApiClient` + `HttpApiTransport` against live Laravel: JSON submission returned
`enq_5 OPEN`, multipart with a PDF returned `enq_6 OPEN`, and an invalid
submission surfaced as `422` with `fields=[subject]` — confirming the contract
mapping, multipart encoding, and field-error mapping work against the real
backend, not only against stubs. The probe was deleted afterwards.

All five enquiries persisted with `user_id = NULL`, `order_id = NULL`, and
`OPEN`, which is the correct anonymous outcome.

**Not verified:** the authenticated CUSTOMER submission. No Clerk sign-in
workflow exists in this app yet, so no real session token could be obtained.
This is **not** claimed. The authenticated path is covered by tests against the
stubbed `AuthSession` only.

## 14. Marionette device results

**Performed** on an Android emulator (`sdk_gphone64_x86_64`, API 35) driven with
Marionette MCP over the Dart VM service, against live Laravel at
`10.0.2.2:8000` (`config/local-device.json`, `ENABLE_DIAGNOSTICS=true`).

Because the shared navigation shell is Phase 17.15, `/contact` had no in-app
entry point. To reach it, `AppRouter.create`'s `initialLocation` was
**temporarily** set to `/contact`, the app was hot-restarted, and the change was
**reverted** afterwards. The only difference from a released build is how the
route was reached; the screen, controller, and repository under test are the
shipped code. The temporary line is not present in the final tree.

| # | Check | Result |
| --- | --- | --- |
| 1 | App launches and loads live catalog data | Pass |
| 2 | Contact placeholder replaced by the real form | Pass — "How can we help?" heading, supporting copy, Enquiry details, Contact details, Reference attachment, Send enquiry |
| 3 | Valid subject entered | Pass — counter updates live (25/200) |
| 4 | Valid multiline message entered | Pass — field grows, keyboard-safe scrolling keeps the focused field visible |
| 5 | Contact information entered | Pass — "Asha Mushi" 10/120, phone 13/30 |
| 6 | Validation errors on empty submit | Pass — "Enter a subject.", "Enter a message.", "Enter your name.", "Enter a phone number, email address, or both.", "Correct the highlighted details and try again." Errors show text **and** color, never color alone |
| 7 | Errors corrected, form resubmitted | Pass — error clears on edit; missing name correctly re-blocks submit |
| 8 | Anonymous submit against live Laravel | Pass — confirmation shows `Reference: enq_7`, `Status: OPEN` |
| 9 | Persisted record correct | Pass — `enq_7`, `OPEN`, name/phone/subject/message stored verbatim including punctuation, `user_id`/`order_id`/`product_id` all NULL, 0 attachments |
| 10 | No commerce side effects | Pass — `orders=0 payments=0 carts=0`, `furniture_requests` unchanged at 3 |
| 11 | File picker opens the Android document picker | Pass — `DocumentsUI PickActivity` is the top activity; no broad storage permission requested |
| 12 | Select a PNG | Pass — button becomes "Replace file", `probe.png (1 KiB)` shown |
| 13 | Remove the attachment | Pass — returns to "Choose a file", name and size cleared |
| 14 | Replace with a PDF | Pass — `probe.pdf (1 KiB)` shown |
| 15 | Multipart submit with attachment | Pass — `Reference: enq_8`; attachment stored as `probe.pdf`, `application/pdf`, 69 bytes |
| 16 | Attachment privacy | Pass — stored on the non-public `attachments` disk under a server-generated key (`01m4hq91nt5mspeh1k01a2ey0k.pdf`) in a `drwx------` directory; not web-reachable (404 direct, 403 via the public symlink) |
| 17 | Backend unreachable | Pass — "We could not confirm whether your enquiry was received. Sending it again may create a duplicate enquiry." The button becomes "Send again". It never says "not submitted" |
| 18 | Deliberate retry | Pass — after Laravel returned, tapping "Send again" created `enq_9` |
| 19 | 2x text scale, full form scroll | Pass — no overflow, no clipped label, counter, price, or action at any section |
| 20 | Error recovery (server validation) | Pass — anonymous name requirement re-enforced server-consistently after an incomplete resubmit |
| 21 | Return navigation from confirmation | Pass — "Done" returns to the home/catalog experience |
| 22 | Furniture-request secondary link | Pass — navigates to the Phase 17.10 form; the two forms stay separate |
| 23 | Furniture Requests unchanged | Pass — product detail still shows the **enabled** "Request this furniture" button |
| 24 | Catalog, product detail, and options unchanged | Pass — live product, `From TZS 1,250,000`, both option rows with SKU and price |
| 25 | Unreachable product image | Pass — neutral `CatalogImage` fallback in a stable frame, no crash |
| 26 | Oversized file rejected before it is read | Pass — a 6,000,009-byte PDF shows "Choose a file no larger than 5 MiB." with no filename row, so `readAsBytes()` is never called; no OOM and no exception in the log. A valid file then selects normally and clears the message |

**Diagnostics observed on device** (from the app's own allow-listed events):

- Offline submission recorded exactly one
  `{"level":"error","category":"network","code":"API_CONNECTION_FAILED",
  "context":{"operation":"apiRequest","http_method":"POST",
  "transport_failure":"connection"}}` — confirming `ApiClient` already records
  the failure once and that no duplicate feature-level event was added.
- That event contains **no** name, email, phone, subject, message, product ID,
  attachment filename, file bytes, bearer token, or request body.

Three `UNEXPECTED_FLUTTER_ERROR` events also appeared. All three were traced to
the **Marionette automation itself** (`ext.flutter.marionette.tap` and
`ext.flutter.marionette.scrollTo` failing to locate a widget), surfaced through
the app's global error handler. They are test-harness failures, not application
defects.

**Not verified on device:** landscape orientation, TalkBack traversal, and the
authenticated CUSTOMER submission (no Clerk sign-in workflow exists).


## 15. Files changed

**Added**

```text
frontend/app/lib/core/attachments/pending_attachment.dart
frontend/app/lib/features/enquiries/data/enquiry_draft.dart
frontend/app/lib/features/enquiries/data/enquiry_repository.dart
frontend/app/lib/features/enquiries/presentation/enquiry_controller.dart
frontend/app/lib/features/enquiries/presentation/enquiry_screen.dart
frontend/app/lib/features/enquiries/presentation/enquiry_success_view.dart
frontend/app/lib/features/enquiries/presentation/widgets/enquiry_attachment_field.dart
frontend/app/test/features/enquiries/enquiry_validation_test.dart
frontend/app/test/features/enquiries/enquiry_repository_test.dart
frontend/app/test/features/enquiries/enquiry_controller_test.dart
frontend/app/test/features/enquiries/enquiry_screen_test.dart
phases/phase-17.11-completion-record.md
```

**Modified**

```text
frontend/app/lib/app.dart                 (ApiEnquiryRepository composition)
frontend/app/lib/navigation/app_router.dart (/contact builder + product context)
frontend/app/lib/features/README.md
frontend/app/README.md
.gitignore                                (unrelated: adds `.aider*` tooling
                                           ignore + a missing trailing newline;
                                           not Phase 17.11 work)
```

**Not modified:** any Laravel file, migration, API document, dependency manifest
(`pubspec.yaml`/`pubspec.lock` are untouched), or Phase 17.10 source. The backend
was already correct and already enabled. `lib/main.dart`,
`lib/config/config_validation.dart`, and `config/local-device.json` were also
untouched: the first two do not appear in the phase diff, and
`config/local-device.json` is a gitignored local device config that still exists
on disk (only the `*.example.json` templates are tracked).

## 16. Documentation updated

- `frontend/app/README.md` — Phase 17.11 feature section and two new known
  limitations (anonymous-only live verification; device testing outstanding).
- `frontend/app/lib/features/README.md` — `enquiries/` module ownership,
  contract mapping, deliberate omissions, and the shared attachment decision.
- `docs/decisions.md` — **unchanged**. No durable architectural decision was
  introduced: the feature follows the existing API-first, backend-authoritative
  and request-first decisions already recorded. The `category` and `order_id`
  omissions are phase-scope decisions, not architecture, so they are recorded
  here and in the module README instead of as an ADR.

## 17. Known limitations

1. **No enquiry history.** `ENQ-002`/`ENQ-003` are not implemented, so a
   submitted enquiry is only identifiable by the reference shown at
   confirmation. Nothing in this phase navigates to `/enquiries/{id}`.
2. **No product-detail enquiry entry point.** Product context is internal and
   unreachable from the UI until a later phase wires it, per §8.
3. **No `category` triage control.** Staff triage from `subject`/`message`.
4. **No `order_id`.** Order features are deferred for this release.
5. **No profile prefill.** There is no `/me` read in the app yet, so an
   authenticated customer types their contact snapshot instead.
6. **Attachment rules are duplicated.** `core/attachments/pending_attachment.dart`
   is shared going forward, but Phase 17.10 keeps its own copy so this phase
   leaves Furniture Requests unchanged.
7. **Landscape and TalkBack untested on device.** Portrait was verified on an
   emulator; landscape orientation and a full screen-reader traversal pass are
   still outstanding.
8. **Authenticated live path unverified.** No Clerk sign-in workflow exists.

## 18. Final result

**Phase 17.11: PASS.** The `/contact` placeholder is replaced with a real,
public, anonymous-capable ENQ-001 enquiry form. It was verified end-to-end
against live Laravel for every anonymous path — JSON creation, multipart with a
private attachment, validation, rate limiting, and the unreachable-backend
recovery path — from a Dart client probe **and** on an Android emulator driven
with Marionette, where three real enquiries were created (`enq_7`, `enq_8` with
its attachment, `enq_9`) and each was confirmed correct in the database. The
frozen contract is unchanged and the deferred commerce surfaces remain inactive.

Two items are explicitly **not** claimed: authenticated live submission (no Clerk
sign-in workflow) and landscape/screen-reader device coverage.

**Exit condition:** a customer can open Contact, submit an enquiry with or
without an account, and see a truthful confirmation — and Laravel, not the app,
decides what was accepted.
