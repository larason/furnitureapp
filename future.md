# Future plans

1. make the designs as simple and small as possible since its still a small business with small catalogue of products, No bloated images and layout, keep it consistency, follow the theme and colour inspiration from [https://www.urbanladder.com/](https://www.urbanladder.com/) BUT not the design system since it must use explicitly material ui for react/nextjs and material 3 for flutter, NO custom designs just pure frameworks adaptation.

2. adding a share button on the products images

3. Set up promotional and heavy advertisment campains including
* Video campaigns in instagram, meta, Youtube
* Promotional codes for a discount for returning customers briging a friend or colleague
* banners and popups for "New customer get a discount!"
* two videos one for broader audience and another for university students
* 30s max 2D explainer videos real human and furnitures

4. use urbanladder advertisment style
* a person sees a furniture now he/she imagines the possibilities of owning that furniture, a worker come and ask the customer if he/she wants it the customer says yeah this is the one!

5. migrate remaining factory-local reference helpers to the shared generator (Group D/E): `OrderFactory::generateReference()` (`OD-` + 5), `PaymentFactory::generateReference()` (`PAY-` + 8) and `FurnitureRequestFactory::generateReference()` (`REQ-` + 10) still use `fake()->bothify` with in-process dedup. Replace each `bothify` expression with `App\Support\ReferenceGenerator::generate(<Model>::REFERENCE_PREFIX, <length>)`, keeping the existing `do/while usedReferences` wrappers untouched (`EnquiryFactory` already migrated and is the pattern to follow). At the same time, point the API service layer (checkout → Order, payment initiation, request intake) at `ReferenceGenerator` directly for server-issued references so no faker involvement remains in production paths. Verify with the existing per-domain reference-format/immutability tests; no new tests strictly required.

6. use lovable or ai studio to speed up development for the admin dashboard UI and features- by connecting to github with the repo.

7. fix this. Staff-lifecycle endpoints (ADM-001..006) are still notImplemented() (501); orders/payments/delivery are formally deferred (11.7/11.11/11.12), and checkout is permanently gated. Production scope is request-first, so the website's primary flows are catalog → made-to-order request → enquiry, and the admin app can't yet manage staff/orders.

8. For production, we should replace HOMEPAGE_MEDIA with approved production media before deployment. Until then, the notice is intentionally visible rather than hiding the fixture status. so that "Catalog data is loaded from the API. Hero and editorial imagery are development visual fixtures.
" will not appear

9. add stickers on furnitures and give customers flyers, business cards or other promotional materials

10. run production checklist in [production-caveats.md](docs/production-caveats.md) and mention all steps that must be done physically by the user

11. Clerk: Clerk has been loaded with development keys. Development instances have strict usage limits and should not be used when deploying your application to production. Learn more: https://clerk.com/docs/deployments/overview 

12. Current sign-up/sign-in is Clerk-only. The website does not yet call Laravel /api/v1/me after authentication, so creating a Clerk account alone does not create/update a local users record.
The website bridge exists in frontend/web/lib/auth/laravel.ts, but nothing currently invokes it. To save a local user immediately after verification/sign-up, we should add a post-authenticated /me call.
Local-user lifecycle: Successful Clerk sign-up/sign-in does not itself guarantee a corresponding Laravel users row. Local CUSTOMER projection is created lazily by Laravel when the authenticated Clerk identity first reaches an application endpoint requiring local user resolution. Public catalog browsing must not trigger provisioning merely because a Clerk session exists.

13. A few observations worth carrying forward:
 1. Clerk JWKS connectivity: Local live token authentication is verified and separate from CORS. Production still requires verification against the production Clerk instance and deployed API origin.
 2. Guest-cart cookies: When Group F implements the browser-facing guest-cart flow, verify credentials: 'include', cookie attributes, and the exact frontend/backend host relationship.
 3. Frontend API transport: Phases 15.9 and 15.10 may use `NEXT_PUBLIC_API_BASE_URL` only for direct browser `REQ-001` and `ENQ-001` submission through the existing API client. Production requires a public HTTPS API origin, exact CORS origins, Clerk configuration, and reverse-proxy body-limit verification. Do not introduce a second API transport.
4. Production deployment: Configure CORS_ALLOWED_ORIGINS using the actual deployed frontend origins and rerun the CORS integration tests.

14. HUMAN ACTION REQUIRED
Why: Production deployment and origins do not exist yet, so the full transport cannot be demonstrated.
Action: On deployment, set for the API host:
  CORS_ALLOWED_ORIGINS=<exact production website origin(s)>
  CLERK_SECRET_KEY / CLERK_ISSUER / CLERK_AUTHORIZED_PARTIES=<production Clerk instance>
  reverse-proxy client body limit >= 6 MiB
  and for the website: NEXT_PUBLIC_API_BASE_URL=https://<public API origin>
Expected result: preflight + anonymous/authenticated JSON and 5 MiB multipart succeed from the production origin over HTTPS.

15. /var/www/html/furnitureapp/phases/group-O-phases.md
- Phases 15.9 and 15.10 use approved direct browser `REQ-001` and `ENQ-001` paths for public anonymous submission and optional Clerk bearer association. The server-only `/me` bridge remains separate and is not required for these flows.
- Prohibits a new BFF/route-handler proxy, alternate HTTP client, token persistence, and Server Actions without validating 5 MiB attachment limits.
- Requires JSON without attachment and inline FormData with attachment; the existing API client supports both.
- Requires no auto-retry for POST ambiguity and distinct handling for 409, 401, 403, 404, 413, 422, 429, timeout, and network failures.

16.  Legal/business approval required before publishing/indexing these pages.
- Existing Phase 15.9 production transport and Phase 15.10 authenticated enquiry-browser verification blockers remain.