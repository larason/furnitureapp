# Future plans

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

15. /phases/group-O-phases.md
- Phases 15.9 and 15.10 use approved direct browser `REQ-001` and `ENQ-001` paths for public anonymous submission and optional Clerk bearer association. The server-only `/me` bridge remains separate and is not required for these flows.
- Prohibits a new BFF/route-handler proxy, alternate HTTP client, token persistence, and Server Actions without validating 5 MiB attachment limits.
- Requires JSON without attachment and inline FormData with attachment; the existing API client supports both.
- Requires no auto-retry for POST ambiguity and distinct handling for 409, 401, 403, 404, 413, 422, 429, timeout, and network failures.

16. Outstanding gates
The following remain open and should be carried forward without reopening completed phases:
1. Production transport: HTTPS API origin, CORS, production Clerk configuration, reverse-proxy limits and deployed-browser verification.
2. Authenticated enquiry: Real Clerk customer browser submission for ENQ-001.
3. Legal approval: Privacy Policy, Terms of Service and About Us content must receive business/legal approval before publication or indexing.

17. One architectural recommendation for phase 17.5: When Phase 17.15 arrives, implement the app bar and drawer as a shared go_router shell (such as ShellRoute) rather than adding separate Scaffold navigation logic to every feature screen. This avoids duplicated navigation code and keeps the centered logo and account action consistent

18. remove this restriction when payment and order flows are implemented: search only calls "product_type=MADE_TO_ORDER"

19. verify vigorously that in the production deployment frontend of both nextjs and flutter connect to only one laravel backend and that is fully functional and meets all requirements before going live. This includes testing all user flows, ensuring that all features work as expected, and confirming that the system can handle the expected load.

20. customer profiles in flutter app editing and updating profile info is no working.

21. using mux for video hosting and streaming for the explainer videos and any other video content on the website and flutter app. Ensure that the integration is seamless and that videos load quickly and play smoothly across all devices.

22. One implementation limitation should remain tracked for future work: token-fingerprint key rotation requires a controlled active-row migration while the old key remains available; device re-registration alone is unsafe because stale ACTIVE rows retain old-key fingerprints. `app_instance_id` is already a locally generated, persisted UUID (see item 24) and no longer falls back to the FCM token. Neither point requires activating customer push fan-out now.

23. Migration rollback refusal -mitigate it is recorded in the docs

24. Firebase Installation ID — deferred.
   A locally generated, persisted UUID is an acceptable application-instance identifier for now, provided it remains stable across ordinary token rotations and the existing lifecycle tests cover that behavior.
   There is no need to introduce an incompatible Firebase Installations dependency merely to complete this phase.

25.  One judgement call to confirm: I made the database name assertion non-relaxable in the runbook ("if that assertion ever needs relaxing, the guard has been removed"). If you'd rather keep that a soft guideline, say so — but I'd argue a test that asserts it is running against the right database is not something to loosen casually.
  Where the real risk is, and it isn't the website
  The one genuine operational consideration is a migration on the shared database, not a runtime feature:
- The migration adds the device_installations table and swaps an index. It does not touch any existing table the website depends on.
- It's additive and verified against real MariaDB.
- A failure would occur at deploy time, not during normal operation.

26. The website's current Clerk integration is a one-way bridge: Clerk → Laravel. It does not yet support Laravel → Clerk, so the website cannot create or update Clerk identities. This is a known limitation and should be documented for future work.

27. Use TestSprite CLI to test this project and fix what fails.

28. The finding I'd actually flag
    getCurrentLocalUser() in frontend/web/lib/auth/laravel.ts is defined but imported by nothing. So is authenticatedLaravelRequest. The entire server-only bridge — the mechanism AGENTS.md:83 says is mandatory — is currently dead code, while the path it was meant to prevent is the only live one.
    That's the real inversion, and it's worth a decision.
   Options
 	Approach	Trade-off
  A (finding's suggestion)	Route the probe through a Server Action → server bridge	I don't recommend it. A client component can't import a server-only module, so this needs a Server Action or Route Handler — precisely the "proxy, Server Action, or second client" that L83 pushes against. Also semantically wrong (POST + CSRF surface for a read), and slower.
    B (my recommendation)	Keep the probe; formalize a narrow exception in AGENTS.md:83 and rename it to say what it does (e.g. LaravelIdentityProbe)	Zero behaviour change, zero security change. Makes the contract honest and keeps the path greppable/bounded to GET /me, never-persist, no other resource.
       C	Delete the probe; let JIT provisioning happen on the server when the first private page calls getCurrentLocalUser()	Architecturally cleanest end-state, and it revives the dead bridge. But it reintroduces exactly the gap you were guarding against — on request-only scope no private page exists, so nothing would provision.
    C is the right destination; B is the right thing to do now, and C becomes the plan when orders/profile ship. That sequencing is safe because B doesn't foreclose C.