# Phase 15.9 — Furniture Request UI

## 0. Entry state and execution gate

- Groups L, M, N: **CLOSED**.
- Phase 15.1: Clerk registration/login UI implemented; **verify its actual integration and outstanding human gates** before relying on it.
- Phases 15.2–15.8 (Cart, Checkout, Pickup/Delivery, Payment, Order Confirmation, Orders, Tracking): **DEFERRED** for the request-first production release. Do not implement or activate them.
- Group J: Made-to-Order Requests backend **CLOSED** according to the project roadmap. Nevertheless **verify the currently deployed/local runtime `REQ-001` route is actually enabled**, since earlier Group J records documented a gated route before subsequent closure. Do not mistake an old historical ADR for current runtime truth.
- Phase 15.9: **ACTIVE**.
- Phase 15.10 (General Enquiry/Contact): **PENDING**.

### Phase 15.9 local implementation and production release gates

**Local transport: PASS. Local UI implementation: AUTHORIZED. Production transport: BLOCKED. Production release: NOT AUTHORIZED.**

The approved Phase 15.9 browser transport remains direct browser-to-Laravel `REQ-001` using the existing generic API client with an explicit public browser API origin. It must not be replaced by a Next.js proxy, Route Handler, Server Action, duplicate client, or a production-only workaround. `API_BASE_URL` remains server-only; any client-safe origin must be deployment-configured and must not reveal private infrastructure.

Local evidence recorded before UI implementation:

- CORS preflight for `POST /api/v1/requests` accepted the local website origin, `Authorization`, and `Content-Type`; unapproved origins received no CORS grant.
- Anonymous and Clerk-authenticated JSON submissions both returned `201 Created`; the authenticated request used a current Clerk session JWT and Laravel provisioned/associated the local `CUSTOMER` correctly.
- Anonymous and authenticated inline multipart submissions with an exact 5 MiB attachment both returned `201`; attachments remained private and no `X-Upload-Token` was returned for inline uploads.
- Laravel rejected oversize attachments with `413`, invalid Clerk tokens with `401`, and anonymous request bursts with `429` plus `Retry-After`.
- Local PHP limits (`post_max_size` and `upload_max_filesize`: 30 MiB) and Laravel's 6 MiB request-body limit allow the frozen 5 MiB attachment contract. Focused CORS, security-boundary, creation, and attachment regression tests passed: 63 tests, 209 assertions.

Before a production release, verify against the deployed HTTPS origins: exact `CORS_ALLOWED_ORIGINS`; production Clerk issuer, keys, and authorized parties; reverse-proxy request-body limit of at least 6 MiB; and anonymous/authenticated browser JSON plus 5 MiB multipart submissions. Until this evidence exists, do not mark Phase 15.9 complete or authorize deployment.

**STOP immediately** if the live Laravel `POST /api/v1/requests` endpoint is still gated/returns a not-implemented response, or if the frozen contract and runtime materially disagree. Do not build a simulated successful submission or silently enable a backend feature. Report the exact blocker, file/route/config, and the human/backend action required. UI work can be prepared separately, but do not declare production submission PASS until the actual endpoint succeeds.

## 1. Objective

Build a **complete, accessible, responsive, request-first furniture request experience** for visitors and authenticated customers using the existing Laravel `REQ-001` endpoint, without introducing an Order, Cart, Checkout, Payment, or quotation workflow.

The journey must support both:

1. **Product-linked:** Customer visits a published `MADE_TO_ORDER` product detail page, selects a clear **Request this furniture** action, reaches the furniture request form with that product context resolved from Laravel, supplies specifications/contact information, and submits.
2. **Custom/general:** Customer opens `/furniture-requests` directly without a catalog product, describes furniture they would like made, and submits.

**No account is required** to request furniture. An existing authenticated Clerk `CUSTOMER` may submit with the current Clerk session token so Laravel derives ownership; an anonymous visitor submits without a token. Both must explicitly provide contact details per the frozen contract.

On a confirmed `201`, show a truthful request acknowledgement and the **server-returned reference if the response contract supplies it**. Do not suggest payment, an accepted order, guaranteed manufacturing, a confirmed price, or delivery timing.

## 2. Required reading — authority hierarchy

Read the real repository before editing:

1. Root `AGENTS.md`, `security.md`, `docs/VISION.md`, `README.md`.
2. `frontend/AGENTS.md`, `frontend/web/ROUTING.md`, `frontend/web/RESPONSIVE.md`, and the actual `production-caveats.md` path.
3. `frontend/design-system/DESIGN.md`, `COMPONENTS.md`, `ACCESSIBILITY.md`, `USAGE.md`, `tokens.css`, `design-tokens.json`.
4. `docs/api/api-contract.md` **§26**, `api-conventions.md` **§26**, `api-resources.md` **§5**, `openapi.yaml` **REQ-001 / CreateRequest / CreateRequestMultipartRequest**.
5. `docs/domain/business-rules.md` **§9**, `docs/decisions.md` **ADR/API-REQ-001..011, ADR/BACKEND-049**, and the latest Group J activation/closure records.
6. `docs/clerk-authentication-architecture.md`, Phase 15.1 actual code, and `phases/group-O-phases.md`.
7. Existing `frontend/web/lib/api/client.ts`, Clerk server auth adapter, public catalog CAT-002 resolver, PDP, homepage MTO editorial action, SiteShell, ContentContainer, SiteSection, existing MUI form primitives, error-state components, `proxy.ts`, `next.config.ts`, `package.json`.
8. Laravel `routes/api.php`, request controller/FormRequest/normalizer, route feature gate, and request tests **read-only** to confirm runtime behavior and exact multipart parsing.

**Conflict policy:** Frozen contract and accepted later ADRs govern; later implementation/activation records supersede historical “STUB/GATED” status only when verified. Never silently invent an API field, route, authorization rule, or token format. Stop for a genuine conflict.

## 3. Design system — strict implementation contract

**`frontend/design-system/tokens.css` is the sole canonical token authority.** MUI theme is its existing adapter. Use established primitives; do not invent a parallel design system.

- Young Serif (`--font-display`) for restrained editorial page heading only; existing utility sans (`--font-ui` / approved body stack) for labels, controls, validation, hints, file details, actions, and technical information.
- Warm ivory canvas, quiet white/paper form surface, charcoal primary text and primary CTA; brown only as a controlled brand accent when an approved semantic token applies.
- Flat first: **no floating form shadow**, generic elevated cards, decorative blobs, gradients, glass effects, heavy borders, badges everywhere, excessive rounding, or animated wizard gimmicks.
- Use approved spacing, breakpoints, typography, radii, border, and media ratios. Do not hard-code colors/spacing/shadows/breakpoints. If an essential token is missing, propose it through the canonical token synchronization workflow **only with approval**; do not silently edit design foundations.
- Use **MUI** and existing components, with **`@mui/icons-material` only** for functional icons. No Lucide, react-icons, Font Awesome, emoji UI icons, or additional UI framework.
- Use canonical `SiteShell` and its **single `<main id="main-content">`**. Use `ContentContainer`/`SiteSection`, not a second page width/gutter system.
- Architecture: server-first route and product resolution; narrow client component for form interactivity/file selection. No full-page client SPA.
- Brand character: **architectural, warm, crafted, editorial, accessible, calm**. Photography and product context may carry the visual story; do not force an unrelated hero image or invent a production asset.

## 4. Page architecture and navigation

Implement the existing reserved canonical website route:

`/furniture-requests`

Do not invent `/request-quote`, `/custom-furniture`, `/made-to-order-requests`, or a Next API alias to Laravel `/requests`.

Recommended conceptual layout:

- Small contextual breadcrumb/navigation back to furniture or originating product.
- One clear display H1, e.g. **Made for your space.** (final copy should be truthful, concise, and consistent with existing design).
- Brief explanatory text: share dimensions, materials, preferences, and contact; the business will review and follow up. **A request is not an order or guaranteed quotation.**
- When product-linked, a restrained product summary with existing product image/name and canonical link back to PDP, separate from editable specifications. No price-lock/stock/variant claims.
- Calm, clearly grouped form: **Furniture details**, **Measurements & preferences**, **Reference attachment**, **Contact details**; order can be refined based on actual responsive layout.
- One primary **Submit furniture request** action, with inline validation and a stable submission state.
- After successful creation, a distinct acknowledgement state (no public detail page or anonymous reference-based lookup).

Use the same shared form for product-linked and custom requests; do not fork into two incompatible implementations. Product selection is optional, not a required hidden prerequisite.

### Product-detail entry point

- Add the request action only where the **authoritative CAT-002 product** is published and `product_type=MADE_TO_ORDER` and eligible for request according to existing public catalog contract.
- Use the backend-provided **canonical product slug** for navigation; resolve it server-side to the **backend opaque `product_id`** before submission. Do not derive `product_id` from slug, name, client metadata, or a guess.
- A URL query such as `/furniture-requests?product=<backend-slug>` is acceptable **only if documented in `ROUTING.md`** and validated via the existing catalog resolver. Do not treat a user-editable query value as trusted product authority.
- Unknown/unpublished/non-MTO product context: do not preselect, fabricate, or silently submit a different product. Show an appropriate unavailable-context/recovery state; the independent custom-request route remains available.
- Preserve existing PDP SEO, JSON-LD (`Product.offers` still omitted), canonical URLs, gallery, image treatment, and server fetch dedup.
- Make the existing homepage **Made to Order** editorial action point to the implemented route where appropriate, without activating purchase controls or dead links.

### Route SEO and privacy

- `/furniture-requests` is a public **form**, not a customer request detail page. Use metadata consistent with the site's SEO policy; choose indexing intentionally rather than blindly copying `/search`.
- Do **not** put submitted contact information, file names, request IDs, error details, or user-specific data into metadata, JSON-LD, URLs, public caches, or logs.
- Do not add individual request detail URLs, anonymous lookup, account history, or a request sitemap entry without explicit approved route policy.

## 5. Frozen `REQ-001` input — exact behavior

**Endpoint:** `POST /api/v1/requests` (Laravel path; Next website route remains `/furniture-requests`).  
**Actors:** Anonymous or authenticated `CUSTOMER`; Staff/Admin do **not** submit customer requests through this endpoint.  
**Successful response:** `201 Created`, standard `{data: ...}` envelope.  
**Idempotency:** Not required in V1; **repeating a successful POST creates another request**.

| Field | Required | Exact UI / serialization rule |
|---|---|---|
| `product_id` | Optional / nullable | Only verified opaque backend ID of active/published `MADE_TO_ORDER` product; omit/null for independent custom request. |
| `quantity` | Optional | Integer **1–100**; when blank **omit/null**, never default to `1` silently; no string in JSON. |
| `name` | **Required for all actors** | Nonblank, trimmed, max **120** characters; self-contained historical contact snapshot. |
| `phone` | Conditional | At least **one of phone or email** required; optional phone normalized per backend convention, max **30**. |
| `email` | Conditional | At least **one of phone or email** required; optional valid email, max **255**, normalized per backend. |
| `dimensions` | Optional | Structured `{length?,width?,height?,unit:"cm"}`; at least one positive measurement if supplied; each numeric **>0 and ≤10000**; only unit `cm`; no `depth`, inches, diameter, or arbitrary keys. |
| `material` | Optional | Free text, max **500**; not a closed selector. |
| `color` | Optional | Free text, max **200**; not a closed selector. |
| `notes` | Optional | Free text, max **5000**; preserve meaningful line breaks. |
| `attachment` | Optional | **One** file maximum; JPG, PNG, WebP, or PDF; **≤5 MiB**; inline multipart field `attachment` preferred. |

**Never send:** `user_id`, `clerk_user_id`, `role`, `request_status`, `request_reference`, `order_id`, `payment_*`, `delivery_fee`, `staff_internal_notes`, `created_at`, `updated_at`, or unknown fields. The server derives ownership and `SUBMITTED` status. Never submit an empty dimension object. Do not introduce a made-to-order SKU/variant/configurator schema.

**Contact rule is the same for anonymous and signed-in customers.** Clerk sign-in does **not** waive `name` and one reachable contact method. Optionally prefill from already-authorized, private profile data only if this can be done without extra unsafe caching; always keep editable and explicitly submitted contact values. Do not fetch `/me` solely to force JIT provisioning.

**Request is not purchase:** no Order, Payment, stock reservation, guaranteed quote, delivery charge, acceptance, or production start. No invented status enum beyond backend-owned `SUBMITTED`, `IN_REVIEW`, `CLOSED`.

## 6. JSON and multipart serialization — preserve backend types

- Without a file: use the existing API client and **JSON** with `quantity` as a number and dimensions as numeric JSON properties.
- With a file: use `FormData` through the **same existing API client**. Confirm actual Laravel parsing and OpenAPI. The documented multipart dimension keys are `dimensions[length]`, `dimensions[width]`, `dimensions[height]`, and `dimensions[unit]`; multipart numeric strings are decoded by Laravel. Never append `dimensions` as `[object Object]` or JSON text unless the actual backend explicitly accepts it.
- Set only supplied optional fields; no empty-string `product_id`, no implicit quantity 1, no fabricated dimension values. Do not manually set the multipart `Content-Type` boundary.
- One file input, accept `.jpg,.jpeg,.png,.webp,.pdf`; show filename/type/size and remove/replace action. Client checks are UX; Laravel remains the content-signature/type/size authority. Reject zero-byte/oversize/unsupported selections with accessible errors.
- Attachment storage stays **private**. Do not create R2 product-media uploads, Cloudinary, signed-public-file links, client-side direct bucket uploads, or a new attachment architecture.
- **ADR/BACKEND-049:** A successful creation **without** an inline attachment may return a scoped upload capability in the `X-Upload-Token` **response header**. Phase 15.9 uses **inline attachment only** and does **not** implement separate `REQ-007` upload. Do not expose, persist, render, log, or leak the capability header. Do not treat the request reference or ID as an upload/read credential.

## 7. Submission transport and security — mandatory design gate

**This is a high-risk integration decision. Inspect the actual Phase 15.1 and 13.5 code before selecting the mechanism.**

- All Laravel requests must go through the existing `frontend/web/lib/api/client.ts` transport (or its approved request-scoped composition); no Axios, parallel API client, generic Next.js BFF, or undocumented route-handler proxy.
- Public anonymous submission must work with **no Clerk bearer token**.
- When an active, valid Clerk `CUSTOMER` submits, use the existing **current request-scoped Clerk token** (`Authorization: Bearer <Clerk session token>`) so Laravel can associate the request with the local user. Do not accidentally submit as anonymous because a token was unavailable; handle that explicitly, and never spoof identity or use email as ownership proof.
- Do not force sign-in for visitors; public catalog and anonymous submission stay accessible.
- No bearer token, raw upload token, or contact data in URLs, props, localStorage, sessionStorage, public cache, metadata, analytics, or logs.
- `REQ-001` and `/me` are **private/no-store**, not eligible for public catalog caching.

**5 MiB upload constraint:** Next.js Server Actions may have a default request-body limit smaller than 5 MiB. Do not choose Server Actions for inline attachments without inspecting the installed Next.js version, body-size settings, reverse proxy limits, and CSRF/origin behavior. Do not silently raise a global limit, create an unapproved BFF, or introduce a browser-direct Laravel call without verifying the existing CORS and Clerk-token policy. Compare supported options and choose the **smallest approved, secure, contract-compatible** path; if no existing permitted path supports the upload, **STOP with a concise transport decision/blocker** rather than shipping a form that fails for valid 5 MiB files.

For a client-submitted non-idempotent POST, prevent accidental double-click/parallel submits, but **never automatically retry** a request after timeout/network ambiguity: it may have succeeded and a replay creates a duplicate. Provide a truthful uncertain-result message and user-controlled recovery.

## 8. Form UX and accessibility

- Use semantic labels, explicit required/optional indications, `aria-describedby`, `aria-invalid`, associated field errors, a summary or live-region announcement where helpful, and a keyboard-accessible submit button.
- Contact section clearly states **name plus phone or email required**. Do not imply both contact channels are mandatory.
- Quantity is optional, not silently initialized to 1. Dimension fields use clear **cm** labels and accept valid decimals. Avoid a confusing width/depth reinterpretation.
- Material/color remain free text; avoid invented mandatory enumerated options. Notes provide room for dimensions/material instructions without replacing structured dimensions.
- Form validation follows the frozen limits and preserves input on `422` and recoverable failures. Map Laravel `{errors:[{code,message,field}],meta:{request_id}}` into safe field-level messages, including `dimensions.length` and `attachment`; do not leak backend stack traces or raw bodies.
- Handle `409 PRODUCT_NOT_REQUESTABLE` with a specific product-context message and recovery to custom request; do not silently discard product reference. Handle `401` (invalid/expired signed-in session), `403` (role/account restriction), `404` (invalid linked product), `413`/attachment-size errors, `422`, `429` (`Retry-After`), `5xx`, timeout, and network ambiguity **distinctly**.
- On successful `201`, replace form with an acknowledgement or navigate to a safe, non-sensitive confirmation state that **does not depend on a public request ID in the URL**. Show only safe returned reference/status; clarify staff will review and contact the requester, without guaranteeing a timeline.
- Never claim success until the API confirms `201`. Do not auto-resubmit on navigation or refresh. No invented request history for anonymous users.
- Support loading/pending, validation errors, file removal, success, failure, and keyboard focus management. Keep focus and draft values stable through error correction.
- Accessibility/responsive check: 390, 959, 960, 961, 1440, 1728 px and 200% zoom; keyboard-only, screen-reader labels/live feedback, visible focus, touch targets, no horizontal overflow or clipped upload controls. Respect reduced motion.

## 9. Integration points and strict boundaries

- **PDP:** Activate the request action for eligible `MADE_TO_ORDER` products using authoritative catalog data; preserve the canonical `ProductCard`, gallery, breadcrumbs, SEO, JSON-LD, and existing catalog server-first architecture.
- **Homepage:** Update existing Made-to-Order editorial link to the implemented form only where appropriate. Avoid a competing CTA or duplicate request form.
- **Header/footer:** Activate only appropriate implemented route links; no fabricated taxonomy links or placeholder `href="#"`. Existing Clerk signed-in/signed-out affordances remain intact.
- **Proxy:** Preserve Clerk integration and Group N product/category hard-404 preflight. Do not rewrite `proxy.ts` merely for the form.
- **Account:** Do not create `/account/requests` or customer request history in Phase 15.9; backend owner-only reads exist, but that is a separately scoped UI feature.
- **Contact:** `/contact` belongs to **Phase 15.10**, not this phase. Furniture Requests and General Enquiries remain separate backend workflows.
- **Commerce:** No cart, checkout, payment, orders, tracking, delivery-fee, quote-management, staff/admin operations, or notifications.
- **Backend:** No backend changes expected. If backend gate/contract prevents real submission, stop and report, do not modify Group J opportunistically.
- **Dependencies:** No new UI/form/state library unless existing project capabilities cannot meet a documented requirement and the owner approves it.

## 10. Testing — automated and runtime

Add `npm run test:furniture-requests` (or a consistently named existing test target) using the existing Node/TypeScript test architecture; no new test framework merely for this phase.

**Contract tests** must cover at least:

1. Route public and no sign-in wall; correct single SiteShell main and tokenized layout.
2. Direct custom request and product-linked request with authoritative CAT-002 opaque ID.
3. Invalid/unpublished/non-MTO product context not preselected; `IN_STOCK` PDP has no request action.
4. Anonymous POST contains **no** bearer; authenticated CUSTOMER POST contains current request-scoped Clerk bearer, with no role/ownership field.
5. Required name and phone-or-email for both actors; optional quantity omitted/null, valid 1–100, invalid integer/string/zero; decimal dimensions serialized as numbers in JSON and correct bracket keys in multipart.
6. Optional material/color/notes limits and no unknown/server-controlled fields.
7. One inline JPG/PNG/WebP/PDF ≤5 MiB, client invalid file feedback, correct multipart boundary handling, no raw upload capability exposure, no separate upload route.
8. `201` acknowledgement only on confirmed response; no fake quote/order, no public detail URL, no success on failure.
9. `422` field errors; `409 PRODUCT_NOT_REQUESTABLE`; `401/403/404/429/5xx` distinctions; no raw API details; no auto-retry on uncertain POST.
10. Double-submit guard; accessibility semantics; no unapproved font/color/spacing/shadow; no new client-only public catalog page.
11. Existing Clerk login/logout/session and Laravel API client semantics unchanged; no token persistence.
12. Catalog SEO/sitemap/robots, product/category hard-404, internal links, and request-first structured data remain unchanged.

**Runtime:** Use local Laravel with a verified enabled `REQ-001` endpoint. Exercise anonymous JSON creation, anonymous multipart creation, authenticated CUSTOMER creation (if Clerk credentials and human consent available), linked MTO product and custom request, invalid file, duplicate-click guard, rate-limit/error paths where safely testable. Verify backend stores `user_id=null` for anonymous and local authenticated user ID for signed-in customer; never claim those results without evidence. Avoid real user PII in test records; use disposable fixtures. If live Clerk/browser or backend testing unavailable, report **NOT AVAILABLE** and its exact reason, and do not present simulated evidence as live proof.

**Existing regressions:**

```bash
npm run test:furniture-requests
npm run test:auth
npm run test:performance
npm run test:links
npm run test:crawl
npm run test:seo
npm run test:structured-data
npm run test:filters
npm run test:search
npm run test:product-detail
npm run test:products
npm run test:category
npm run test:homepage
npm run test:api
npm run test:theme
npm run test:layout
npm run test:responsive
npm run test:states
npm run typecheck
npm run lint
npm run build
git diff --check
```

If a script is absent in the actual repo, report its absence and use the existing equivalent; do not falsely report PASS.

## 11. Documentation and Git

Update only appropriate documents:

- `frontend/web/ROUTING.md`: `/furniture-requests` implemented, optional product-context query if used, no anonymous detail route.
- `frontend/AGENTS.md`: durable rules for public anonymous/authenticated request intake, token/attachment/privacy boundaries, tokenized form composition.
- `production-caveats.md` (actual path): move furniture-request route from deferred to implemented **only when functional**, record deployment/runtime/upload limits and any live verification gaps; retain 15.2–15.8 deferral.
- `phases/group-O-phases.md` and Phase 15.9 record: evidence, decisions, known limitations, Group O request-first exit progress.
- `docs/decisions.md` only if a genuinely new approved architecture decision is necessary. Do not invent one to rationalize a workaround.

**Before any Git command**, locate, read, and follow root skill **`git-workflow-and-versioning`**. Preserve unrelated owner changes. Do not commit `.env`, Clerk keys, bearer tokens, upload capabilities, test-user credentials, or PII. Report branch, files staged, commit hash/message, push outcome.

## 12. Human-action protocol

When an action cannot safely be completed by the agent (Clerk login, email verification, real test-user approval, Laravel route activation, CORS/Server Action upload limit approval, production domain/origin settings), stop with:

```text
HUMAN ACTION REQUIRED
Why: <specific blocker>
Action: <exact dashboard path, command, or decision>
Expected result: <observable outcome>
Reply with: <minimal non-secret confirmation>
```

Do not ask for secret values in chat, invent configuration, or weaken security to bypass a human step. The **correct current development Clerk application** is `app_3BXbzUtbWuYa9hmlKKEDxxvHVXC`; do not use the previously mistaken ID or create another application.

## 13. Completion report — required structure

```text
PHASE 15.9 — FURNITURE REQUEST UI

Status: PASS / BLOCKED

CONTRACT
REQ-001 actual route enabled: YES / NO / NOT VERIFIED
Group J runtime/contract reconciled: PASS / BLOCKED
Anonymous create: PASS / FAIL / NOT AVAILABLE
Authenticated CUSTOMER create: PASS / FAIL / NOT AVAILABLE
Staff/Admin excluded: PASS / FAIL
Product-linked and custom: PASS / FAIL
Contact rules: PASS / FAIL
Dimensions/quantity/types: PASS / FAIL
Attachment inline: PASS / FAIL
X-Upload-Token exposed/persisted: NO / FAIL
No order/payment/inventory effects: PASS / FAIL / NOT VERIFIED

DESIGN
Canonical tokens and MUI theme: PASS / FAIL
Young Serif display / utility sans form: PASS / FAIL
Shared primitives and SiteShell: PASS / FAIL
Arbitrary CSS/shadows/new tokens: NONE / <details>
Responsive and accessibility: PASS / FAIL / NOT AVAILABLE

INTEGRATION
Website route: /furniture-requests
PDP MTO CTA: PASS / FAIL
Custom request entry: PASS / FAIL
Server catalog resolution: PASS / FAIL
Existing API client reused: PASS / FAIL
Optional Clerk bearer behavior: PASS / FAIL
Transport/5 MiB decision: <implementation and evidence>
Private/no-store: PASS / FAIL
Double-submit/no automatic POST retry: PASS / FAIL

UX
Validation: PASS / FAIL
File selection/removal: PASS / FAIL
201 acknowledgement: PASS / FAIL
Error taxonomy: PASS / FAIL
No public request detail/lookup: PASS / FAIL

VERIFICATION
Test suite and result: <commands/results>
Anonymous live JSON/multipart: PASS / FAIL / NOT AVAILABLE
Authenticated live Clerk→Laravel: PASS / FAIL / NOT AVAILABLE
Browser/zoom: PASS / FAIL / NOT AVAILABLE
All existing regressions: PASS / FAIL
Typecheck/lint/build/diff: PASS / FAIL

BOUNDARIES
Backend changes: NONE / <details>
Clerk architecture changes: NONE / <details>
Cart/checkout/payment/orders/tracking: NONE / FAIL
General Enquiry UI (15.10): NONE / FAIL
Staff/Admin UI: NONE / FAIL
Flutter: NONE / FAIL
New dependencies: NONE / <approved reason>

DOCUMENTATION
ROUTING: UPDATED / FAIL
Frontend AGENTS: UPDATED / UNCHANGED
Production caveats: UPDATED / FAIL
Group O/phase record: UPDATED / FAIL
ADR: NONE / <approved ID>

HUMAN ACTION
Outstanding: NONE / <specific list>

GIT
Skill read: YES / NO
Branch: <...>
Operations: <...>
Commit: <hash/message>
Push: <result/NONE>

RESULT
Phase 15.9: PASS / BLOCKED
Phase 15.10: READY / BLOCKED
Group O: ACTIVE
```

## 14. Final STOP condition

Declare **Phase 15.9 — PASS** only if the real `REQ-001` endpoint is enabled and demonstrably creates requests; anonymous and authenticated behavior is correctly integrated or explicitly gated for required human live verification; both custom and product-linked flows work; the strict frozen payload and inline attachment rules are respected; request references and capabilities remain private; submission errors and duplicate risks are handled; the visual design uses only approved SL Furnitures tokens/primitives; accessibility and responsive verification is credible; catalog/Clerk/SEO/proxy regressions pass; documentation is accurate; no deferred commerce or enquiry UI has been implemented.

If a mandatory backend/transport/human gate remains unresolved, report **BLOCKED** with exact evidence. Never turn a nonfunctional form into a fake success.

After PASS, report:

**Phase 15.9 — PASS**  
**Phase 15.10 — READY**  
**Group O — ACTIVE**

**Do not start Phase 15.10 automatically.**
