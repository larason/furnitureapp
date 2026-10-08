# Production Readiness Caveats

This document lists everything that must be assessed and dealt with before the
public SL Furnitures website (`frontend/web`) runs in production. It captures
behaviour that is intentional in development/fixture mode but unsafe or degraded
in production, plus deployment steps and known limitations.

Scope: the Next.js website only. Laravel API, database, payments, and Flutter are
out of scope here except where the website depends on them.

Status legend:

- **REQUIRED** — must be configured/handled or the deployment is incorrect.
- **ASSESS** — confirm the behaviour is acceptable for the chosen deployment.
- **LIMITATION** — known, intentional gap; do not treat as a bug.
- **DO NOT** — a production misconfiguration to avoid.

---

## 1. Environment configuration

Website configuration is server-only unless explicitly documented as client-safe
by Clerk. Never commit `.env` / `.env.local`, never expose server secrets with a
`NEXT_PUBLIC_` prefix, and never reuse application/database credentials in the
frontend.

| Variable | Status | Purpose | Behaviour if missing/invalid |
| --- | --- | --- | --- |
| `SITE_URL` | REQUIRED | Public website origin for canonical, Open Graph/Twitter, JSON-LD, sitemap, and robots `Sitemap:` directive | Missing: absolute SEO URLs are omitted, sitemap is an empty `<urlset>`, robots omits the `Sitemap:` line, and JSON-LD site entities are omitted. Invalid/malformed: throws `SiteUrlError`. |
| `API_BASE_URL` | REQUIRED | Laravel API origin (all catalog/domain requests) | Missing/blank or non-HTTPS (except localhost) throws `ApiConfigurationError`; requests fail. |
| `NEXT_PUBLIC_API_BASE_URL` | REQUIRED for Phase 15.9 release | Public HTTPS Laravel API origin for direct browser `REQ-001` submission only | Missing/invalid: furniture-request submissions fail safely. Must be a public origin, never a private host, credential, path, query, or secret. |
| `CATALOG_MEDIA_BASE_URL` | ASSESS | Public catalog media/CDN origin for `next/image` remote patterns | Falls back to `API_BASE_URL`. If images are served from a different origin and this is unset, `next/image` fails to load them. |
| `HOMEPAGE_DATA_SOURCE` | DO NOT set to `fixtures` | Selects `api` (default) or `fixtures` catalog source | Any value other than `api`/`fixtures` throws `Invalid homepage data source.` and 500s pages. |
| `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` | REQUIRED for Clerk | Client-safe Clerk instance configuration | Clerk auth UI/session initialization fails when absent or invalid. |
| `CLERK_SECRET_KEY` | REQUIRED for Clerk | Server-only Clerk authentication/session configuration | Clerk server integration fails when absent or invalid. Never expose it to the browser. |
| `NEXT_PUBLIC_CLERK_SIGN_IN_URL`, `NEXT_PUBLIC_CLERK_SIGN_UP_URL`, redirect variables | REQUIRED for dedicated Clerk pages | Client-safe route and fallback configuration written by Clerk setup | Sign-in/sign-up routing or safe fallback behavior can fail if missing or inconsistent with deployed routes. |
| `NODE_ENV` | REQUIRED | Framework production mode | Managed by the build/runtime; do not override casually. |

Details:

- `SITE_URL` must be a bare HTTPS origin with no path, query, or hash, e.g.
  `https://www.example.com`. HTTP is accepted **only** for `localhost`/loopback
  **and only outside production**; in production an HTTP or localhost value is
  rejected. See `lib/seo/site.ts`.
- The repository does **not** establish a production domain. Do not guess one,
  do not use `API_BASE_URL`, the CDN origin, or the request `Host` header as the
  website origin, and do not fall back to `localhost`.
- If `SITE_URL` is absent in production, the server logs a one-time warning.
  During `next build` the warning is suppressed (build still succeeds).
- `API_BASE_URL` must be an HTTPS origin in production. The client has no
  code-level localhost fallback and validates the origin shape.
- `HOMEPAGE_DATA_SOURCE=fixtures` must **never** be set in production; it serves
  development fixture products/categories and bypasses the API and the
  detail-route 404 preflight.
- Clerk credentials, verification, password recovery, session restoration, and
  logout are Clerk-owned. Laravel receives only request-scoped bearer tokens for
  protected API calls and remains authoritative for local roles, account state,
  and authorization.
- **REQUIRED — Phase 15.9 browser transport.** Configure Laravel
  `CORS_ALLOWED_ORIGINS` with the exact website HTTPS origin(s), production Clerk
  issuer/keys/authorized parties, and the public `NEXT_PUBLIC_API_BASE_URL` above.
  Do not introduce a Next.js proxy, Route Handler, Server Action, or alternate
  API client for `REQ-001`.
- **REQUIRED — request body limits.** The deployed reverse proxy must accept at
  least 6 MiB request bodies, preserving Laravel's 5 MiB inline attachment
  contract plus multipart overhead. Verify this with a real browser request.

---

## 2. Build and deployment

- **REQUIRED — rebuild on every deploy.** The site is a compiled Next.js app.
  Changes (and the `.next` output) must be rebuilt; a stale build will serve old
  client components (this has already caused confusion during development).
- **REQUIRED — run the Node middleware (`proxy.ts`).** Detail-route hard-404
  behaviour and Clerk session context depend on it (see §5). If the deployment
  platform does not run middleware, missing products/categories will not return a
  clean 404 and Clerk server authentication will not function.
- **REQUIRED — configure Clerk production settings.** Use production Clerk keys,
  configured production domain/origins, public email/password sign-up, required
  email verification, and no phone/Organization requirement. Production Clerk
  dashboard configuration has not been live-verified by this repository.
- **ASSESS — `next/image` remote patterns.** `next.config.ts` builds
  `images.remotePatterns` from `CATALOG_MEDIA_BASE_URL` (or `API_BASE_URL`).
  Ensure the production media/CDN host is covered.
- **ASSESS — runtime Node version** must satisfy the project's `engines`/tooling.
- **ASSESS — environment separation.** Local, staging, and production must use
  distinct `SITE_URL`, `API_BASE_URL`, and secrets.

---

## 3. SEO and crawl discovery

- **REQUIRED — `SITE_URL` for meaningful SEO.** Without it, canonical tags,
  `og:url`, social images, `Product`/`WebSite`/`Organization` JSON-LD, and the
  sitemap are degraded/omitted (by design; never fabricated).
- **ASSESS — sitemap scale.** The sitemap fetches CAT-001 at `per_page=100` and
  caps at 500 pages (50,000 URLs); exceeding it throws rather than emitting an
  incomplete sitemap. If the catalog can approach 50k URLs, plan
  `generateSitemaps`/sitemap-index sharding before launch.
- **LIMITATION — category completeness.** CAT-003 returns only active top-level
  storefront categories; deeper descendants are not exposed, so the sitemap and
  site navigation cannot enumerate the full taxonomy. Completeness is bounded by
  the frozen public API.
- **LIMITATION — category-filter pages are not category pages.**
  `/products?category={slug}` is an API filter/discovery state (`noindex, follow`),
  not the canonical category resource. Canonical category pages remain
  `/categories/{slug}`. Do not promote filter URLs to indexable landing pages.
- **LIMITATION — pagination canonical policy.** Plain `/products?page=N` and
  `/categories/{slug}?page=N` self-canonicalize; page 1 stays clean. Filtered/
  sorted collections are `noindex, follow` and canonicalize to `/products`.
- **ASSESS — Search Console.** Submitting/verifying `/sitemap.xml` in Google
  Search Console is a deployment step and was **not** performed by the
  implementation. No SEO submission integration exists.
- **REQUIRED — `robots.txt` correctness.** It allows public crawling, disallows
  `/search` and the faceted parameters, and (only with `SITE_URL`) advertises the
  sitemap. Verify the live file after deploy.
- **LIMITATION — no faceted landing pages / image sitemaps.** Not implemented by
  design; product media already appears on canonical product pages.

---

## 4. Structured data

- **LIMITATION — `Product.offers` is intentionally omitted.** The release is
  request-first with no cart/checkout/payment, so no `Offer`, price, availability,
  shipping, or returns data is emitted, and Google **merchant-listing** /
  **product rich-result** eligibility is **not claimed**. Revisit only if a real
  purchase flow ships.
- **ASSESS — public-ID alias PDPs.** `Product` JSON-LD is emitted only on the
  canonical `/products/{slug}` route. CAT-002 can still resolve `/products/{id}`
  (aliases render the page with canonical metadata pointing at the slug but no
  `Product` graph). No ID→slug redirect is implemented; decide whether to add one.
- **LIMITATION — no variants, ratings, brand, SKU, GTIN/MPN.** Not available from
  the frozen contract; not invented.

---

## 5. Routing, 404s, and catalog behaviour

- **REQUIRED — detail 404s in API mode.** Missing `/products/{slug}` and
  `/categories/{slug}` return HTTP 404 through the middleware preflight. This is
  the acceptance authority for hard-404 behaviour.
- **LIMITATION — fixture-mode 404 caveat (development only).** With
  `HOMEPAGE_DATA_SOURCE=fixtures` the middleware is skipped and a missing slug
  can render the not-found UI with HTTP 200. This is intentional for local
  fixture work and must not be relied on in production.
- **REQUIRED — `/products` and `/search` must not be preflighted.** Confirm the
  middleware matcher still excludes collection routes and metadata routes
  (`/sitemap.xml`, `/robots.txt`).
- **REQUIRED — Clerk utility routes.** `/sign-in`, `/sign-up`, and Clerk's
  `/__clerk/**` frontend API route must not be routed through catalog detail
  preflight. They remain public; public catalog pages must not acquire a login
  wall.
- **LIMITATION — slug mutability.** Backend slugs are not immutable and there is
  no old-slug redirect contract; renamed slugs may 404. Establish a redirect/
  migration policy before treating slug edits as routine content operations.
- **LIMITATION — global category navigation is a non-interactive fixture.** The
  header/mobile/footer category lists render as non-links until authoritative
  catalog-driven global navigation can be introduced without a global fetch/
  availability regression. Categories remain reachable via the homepage
  discovery, product breadcrumbs, and the sitemap. Reserved routes
  (`/furniture-requests`, `/contact`, `/account`, cart/checkout) stay inactive.

---

## 6. Client-side behaviour and no-JavaScript

- **LIMITATION — JavaScript is required for price entry and sort selection.**
  Whole-TZS price values and the combined sort choice are converted into the
  frozen query contract on the client. Without JavaScript, the controls still
  round-trip whatever state is already in the URL, and all other filtering,
  linking, and pagination work.
- **ASSESS — client navigation vs. control state.** The price/sort clients
  initialise from server-provided props. Current sort/price changes use a native
  GET form (full navigation), so this is correct. If future work changes filters
  via client-side navigation without a full reload, sync the controls to props.

---

## 7. Data and privacy

- **REQUIRED — never render private data in public output.** Metadata/JSON-LD and
  server HTML must not include customer/staff data, enquiries/requests, internal
  notes, storage keys, cost prices, inventory quantities, or API error details.
- **REQUIRED — API error discipline.** Unexpected API failures (5xx/429/timeout/
  network) must remain errors, never be converted into empty catalogs or 404s.
- **REQUIRED — request-first accuracy.** Public copy/metadata must not imply
  cart, checkout, payment, free/nationwide/same-day delivery, or returns until
  those capabilities exist.

---

## 8. Not yet implemented (deferred by design)

These routes/capabilities are reserved and inactive; do not activate or link
them until their owning phase ships:

- customer account and orders — deferred; Clerk customer sign-in/sign-up is
  implemented, but no customer account/dashboard route exists yet;
- cart, checkout, payment, order confirmation — deferred (request-first release);
- general enquiries (`/contact`);
- comprehensive internal-linking, image/performance hardening (later Group N
  phases).

---

## 9. Pre-launch verification checklist

1. `SITE_URL` set to the real HTTPS origin; `API_BASE_URL` and
   `CATALOG_MEDIA_BASE_URL` set correctly; Clerk production keys and public route
   variables configured; `HOMEPAGE_DATA_SOURCE` unset.
2. Fresh production build and restart; middleware enabled.
3. Smoke test: `GET /`, `/products`, `/products?page=2`,
   `/products?category=…` (noindex), a real `/products/{slug}`,
   `/categories/{slug}`, `/search?search=…` (noindex), `/sitemap.xml`,
   `/robots.txt`.
4. Confirm `sitemap.xml` has absolute `SITE_URL` URLs, no query/search/facet URLs,
   and no duplicates; confirm `robots.txt` advertises the sitemap.
5. Confirm missing `/products/{slug}` and `/categories/{slug}` return HTTP 404.
6. Confirm no `localhost`, `127.0.0.1`, or API-origin URLs appear in canonical,
   `og:url`, JSON-LD, sitemap, or robots output.
7. Confirm `next/image` loads catalog media from the configured origin.
8. Submit/verify the sitemap in Search Console (manual step).
9. Smoke test Clerk sign-up, required email verification, sign-in, password
   recovery, sign-out, and a token-backed `GET /api/v1/me`; confirm public pages
    remain accessible signed out and no Clerk/Laravel token reaches page HTML.
10. Verify `/furniture-requests` from the deployed website origin: CORS
    preflight, anonymous and authenticated Clerk JSON submissions, and anonymous
    and authenticated 5 MiB multipart submissions. Confirm attachments remain
    private, no `X-Upload-Token` is rendered or persisted, and reverse-proxy
    limits permit the upload.
11. Re-run the frontend suite (`npm run test:furniture-requests`, `test:seo`, `test:structured-data`,
   `test:crawl`, `test:links`, `test:filters`, `test:search`,
   `test:product-detail`, `test:products`, `test:category`, `test:homepage`,
    `test:api`, `test:theme`, `test:layout`, `test:responsive`, `test:states`,
    `test:auth`,
   `typecheck`, `lint`, `build`).

---

## 10. Open decisions to resolve

- Production `SITE_URL` value (not established in the repository).
- Whether to redirect `/products/{public-id}` aliases to the canonical slug URL.
- Sitemap sharding if the catalog can approach 50,000 URLs.
- Slug rename/redirect policy.
- When to make global header/mobile/footer category navigation authoritative
  (data-driven) without a global fetch/availability regression.
- Reintroducing `Product.offers`/rich-result markup only when a real purchase
  flow exists.

---

## 11. Catalog caching (deferred decision)

- **LIMITATION — persistent caching/revalidation is deferred.** The frozen
  contract marks the public catalog endpoints (CAT-001..CAT-006) as
  unauthenticated, customer-state-free, and safe for public caching/CDN/ISR.
  However, no approved catalog-freshness/staleness policy exists, so the website
  deliberately does **not** invent a revalidation interval. Public catalog
  requests currently use `cache: "no-store"`.
- **Request-local deduplication is in place.** CAT-002/CAT-004 detail resolution
  is wrapped in React `cache()` so `generateMetadata` and the page share one
  request, and structured data reuses the resolved resource. Collection pages use
  one CAT-001 request (no CAT-002 N+1).
- **ASSESS — caching opportunity.** Introducing Next fetch revalidation or ISR
  for the public catalog is a permitted future optimization, but it requires an
  explicit business freshness decision first. When adopted, scope it to public
  catalog data only; never cache account, orders, cart, checkout, payments, or
  private request/enquiry detail as public content.

## 12. Image delivery notes

- Catalog photography uses `next/image` with responsive `sizes` that match the
  real ProductGrid columns (1/2/3). The product-grid `sizes` were corrected so the
  640–960px range reflects the 2-column layout (previously under-declared).
- Only the single above-the-fold LCP image per page is preloaded: the homepage
  hero and the PDP lead image. Below-fold media (cards, extra gallery views)
  stays lazy by default.
- The brand logo is an SVG rendered `eager` (header and mobile drawer share the
  same URL, so the browser reuses it); it is not rasterized or recolored.
- **ASSESS — homepage hero/editorial are fixture assets.** The homepage renders
  `public/furnitures/fixtures/**` hero and editorial photographs in every mode
  (they are not API-backed). `next/image` optimizes delivery, but these source
  files are large (hero ~857 KB, editorial ~661 KB). Replace them with
  production-owned assets when available; do not recompress blindly without a
  visual check.
- **ASSESS — unused fixture files.** A few tracked `*.jpg~` editor-backup files
  remain under `public/furnitures/fixtures/products/`; they are unreferenced and
  deploy with the site. Safe to remove as cleanup (no rendered impact).
