# Phase 19.8A — Product Image Upload & Preview

## Status

**PASS — for the Phase 19.8A scope (upload + preview), with operator live verification pending.**

Phase 19.8A is deliberately narrower than the roadmap's Phase 19.8 ("Product Media Management – cloudflare R2: images, uploads, ordering, alt text, previews and deletion"). It delivers **upload and preview only**. Deletion, ordering, alt-text editing and setting the primary image are **not** delivered and are **not offered**, because no approved backend endpoint exists for any of them. This record claims nothing beyond upload and preview.

Nothing is staged or committed. `.gitignore`, `.fallowrc.json`, `phases/group-S-phases.md`, `future.md` and the root `AGENTS.md` were not modified. No endpoint, permission, enum or request field was invented, and no dependency was added.

## 1. Files changed

**Created**

- `frontend/admin/lib/catalog/media-origin.ts` — dependency-free resolver/validator for the public media origin plus the `next/image` allow-list builder.
- `frontend/admin/lib/catalog/product-image-form.ts` — CAT-009 form mapping, draft validation, advisory type/size checks, Laravel-error extraction.
- `frontend/admin/lib/catalog/product-image-actions.ts` — the `"use server"` upload action.
- `frontend/admin/components/catalog/product-image-preview.tsx` — the only Client Component in the media section; load-failure fallback + render boundary.
- `frontend/admin/components/catalog/product-image-gallery.tsx` — Server Component reading the operational image records.
- `frontend/admin/components/catalog/product-image-uploader.tsx` — Client Component upload form (`useActionState` + `useFormStatus`).
- `frontend/admin/tests/product-media.test.tsx` — 24 tests.
- `phases/phase-19.8a-completion.md` — this record.

**Modified**

- `frontend/admin/next.config.ts` — derives `images.remotePatterns` from `CATALOG_MEDIA_BASE_URL`; raises `serverActions.bodySizeLimit`; nothing else.
- `frontend/admin/lib/api/server-request.ts` — adds `requestOperationalUpload`, the explicit multipart path, over the existing transport.
- `frontend/admin/lib/catalog/products-api.ts` — adds `submitProductImage` (CAT-009).
- `frontend/admin/components/catalog/product-detail-view.tsx` — media section renders the gallery and accepts the upload control as a slot; the stale "Phase 19.8" placeholder copy is gone.
- `frontend/admin/app/(protected)/products/[product]/page.tsx` — supplies `<ProductImageUploader />` to the view.
- `frontend/admin/.env.example` — documents `CATALOG_MEDIA_BASE_URL` (empty; no host, no secret).
- `frontend/admin/package.json` — registers `tests/product-media.test.tsx`.
- `frontend/admin/tests/products.test.tsx` — the media assertion moves from an image *count* to the rendered metadata; `/images` leaves the forbidden-endpoint list and is replaced by a positive CAT-009 assertion.
- `frontend/admin/README.md`, `frontend/admin/AGENTS.md` — media module contract, data flow, effective size limits and honest limitations.
- `docs/findings-and-limitations-register.md` — **F9** re-stated as partially resolved.

**Environment only (untracked, `.env.*` is ignored)**

- `frontend/admin/.env.local` — added `CATALOG_MEDIA_BASE_URL` pointing at the same R2 public development origin the backend already uses.

## 2. Verified contract (CAT-009)

Verified against `backend/laravel/routes/api.php:149`, `ProductController::storeImage`, `CreateProductImageRequest`, `ProductImageValidator`, `ProductImageResource` and `config/product_images.php`.

| Aspect | Verified behaviour |
| --- | --- |
| Route | `POST /api/v1/products/{product}/images` |
| Middleware | `clerk.auth` + `staff-or-admin` + `permission:products.manage` + `throttle:operational-write` |
| Request | `multipart/form-data` with **exactly one** `image` file field. Any other input field → `422 INVALID_VALUE`; any other file field → `422 INVALID_VALUE`; no file → `422 INVALID_VALUE` (field `image`). |
| Accepted content | Detected MIME must be `image/jpeg`, `image/png` or `image/webp`, must match the file's magic bytes and its `getimagesize` type, and (where the client declares a type) must match the client type. JPEG with EXIF `GPS` metadata → `422`. |
| Size | `product_images.max_bytes`, default `5 * 1024 * 1024`. Over the limit → `413 REQUEST_TOO_LARGE` (field `image`). |
| Success | `201` with `ProductImageResource`: `{ id, url, alt_text, sort_order, is_primary }`. |
| Reads | The operational product detail resource already returns `images[]` with the same four fields beyond `id`. |

The upload action forwards **only** the `image` field, so the request the backend receives is exactly the shape it accepts.

## 3. Media origin and image allow-list

- `lib/catalog/media-origin.ts` resolves `CATALOG_MEDIA_BASE_URL` and returns nothing for a missing, non-HTTPS, credential-bearing, query-bearing or malformed value. Nothing means: no allow-list entry and no preview.
- `next.config.ts` builds `images.remotePatterns` from the same resolver, so exactly one host is allow-listed, with `search: ""` (no query string) and a path pattern derived from the configured base path. There is no wildcard host and no image proxy.
- `isRenderableMediaUrl` compares the parsed `origin` — not a string prefix — so look-alike hosts (`media.example.test.attacker.test`), userinfo tricks (`https://media.example.test@attacker.test`) and other origins are rejected. A URL outside the allow-list renders an explicit unavailable state; **no returned URL is ever rewritten** and no host is ever widened.
- The variable is read by `next.config.ts` **when the server starts** and by the gallery at request time. Because the allow-list is compiled at startup, **changing the value requires a restart**: editing `.env.local` under a running server reloads the server's environment but not the compiled allow-list, so every preview reports an unreachable image while the stored record and URL are valid. This is documented in `frontend/admin/README.md` and `frontend/admin/AGENTS.md`.
- `frontend/AGENTS.md` already required catalog media to be allow-listed from `CATALOG_MEDIA_BASE_URL`; this phase is the first admin consumer.

## 4. Transport

`lib/api/client.ts` already forwarded `FormData` unchanged and never set `Content-Type` for it. Rather than duplicate that, `requestOperationalUpload` in `lib/api/server-request.ts` is the explicit multipart entry point: it hands the native `FormData` to the same request-scoped, `no-store` bearer transport with a longer timeout. No JSON serializer touches the file bytes, and no new client was added.

## 5. Authorization

`lib/catalog/product-image-actions.ts` is `"use server"` and therefore directly POST-reachable. It calls `resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role })` — the same `products.manage` requirement the `/products` route and the create/update actions use — **before** reading the file or contacting Laravel. A denial returns the same "not authorized to manage products" state as the existing product actions. Laravel re-authorizes independently: Clerk and the frontend are never the authority.

## 6. Preview rendering and failure handling

- `ProductImageGallery` (Server Component) renders the records Laravel returned. An **empty collection is an empty state**, explicitly not an error.
- `ProductImagePreview` is the single client boundary. It contains two failure modes so a remote object can never break the detail page: `onError` for a load failure, and a render boundary for a `src` the image configuration rejects.
- The placeholder states **why** it is shown, with the two causes kept distinct because the operator response differs: an off-origin URL (`reason="origin"`, a data problem) versus a failed render (`reason="load"`, usually a delivery or image-configuration problem, and the only case that names the restart requirement).
- An unconfigured origin renders a plain notice plus a neutral placeholder per image; stored metadata is still shown.
- Ordering, alt text and primary status are shown **as authoritative values**. No control edits them, because no endpoint accepts them.
- Alt text is used verbatim when present; when it is absent the preview falls back to a neutral label (`"{product name} — product image {n}"`) so the image is never unlabelled.
- The gallery is a responsive `ul`/`li` grid using the existing `--media-product-card` aspect token, MUI theme colors, and the shared spacing scale. No new token, color, radius or ratio was introduced.

### Effective size limits

| Limit | Value | Source |
| --- | --- | --- |
| Laravel product image limit | 5 MiB | `backend/laravel/config/product_images.php` (`PRODUCT_IMAGE_MAX_BYTES`, default `5 * 1024 * 1024`) |
| PHP `upload_max_filesize` / `post_max_size` | 30 MiB each | local `php -i` |
| `max_file_uploads` | 20 | local `php -i` |
| Next.js Server Action body | 6 MB (default 1 MB) | `serverActions.bodySizeLimit` in `next.config.ts` |

The 1 MB Server Action default sits **below** the backend's own limit, so a 2 MB image would have failed at the Next boundary instead of reaching Laravel. `next.config.ts` raises the ingress to carry the backend limit plus multipart overhead. This is an application default, not a server or infrastructure limit change, and it does not relax the backend constraint: an image Laravel rejects is still rejected. The PHP and reverse-proxy limits could only be verified on this machine and must be re-verified per environment.

## 7. Tests

`frontend/admin/tests/product-media.test.tsx` — 24 tests, all passing:

- origin resolution: accepted HTTPS origin, base path, explicit port; rejection of missing/insecure/credential/query/fragment/malformed values;
- renderability: same-origin and base-path paths accepted; other origin, look-alike host, userinfo trick, protocol-relative, relative and malformed values rejected; unconfigured origin renders nothing;
- allow-list: `[]` when unconfigured, one exact HTTPS host otherwise, `search: ""`; `next.config.ts` derives it from the environment and hardcodes no host;
- gallery: allow-listed image renders through `next/image` with the stored alt text, the API `sort_order` and primary status; absent alt text yields the neutral label; an unconfigured origin renders the notice + fallback with no `<img>`; an empty collection is an empty state, not an error;
- preview: client directive, `onError` fallback, render boundary, distinct origin/load copy, and a fallback that leaks no URL;
- read-only: no delete/reorder/primary/alt control in the gallery, and the view carries no `server-only` upload module;
- transport: `FormData` returns before any JSON content type, no manual `multipart/form-data`, the upload helper uses the multipart path, and an upload is never JSON-serialized;
- scope: only CAT-009 is contacted; `DELETE`, `sort_order`, `alt_text`, `is_primary`, `reorder`, `setPrimary` and `variants` appear nowhere in the product API module;
- authorization: the action repeats the exact permission before the upload call and revalidates the detail path;
- request shape: the forwarded form sets exactly one field, `image`;
- credential boundary: no `Bearer`, `getToken`, `session_token`, `localStorage` or `sessionStorage` in the uploader or action, and the uploader imports no server transport;
- duplicate submission: `useFormStatus()` + `disabled={pending}`;
- advisory validation: accepted types match `product_images.allowed_types`; unsupported types, zero-byte and over-limit files rejected; an unreported MIME type is left to Laravel;
- error mapping: the `image` field message is preferred, and 413/429/404 map to distinct safe messages with no internal detail.

## 8. Gates run

| Gate | Result |
| --- | --- |
| `npm run lint` | clean |
| `npm run typecheck` | clean |
| `npm run test` | 174/174 passing (was 133) |
| `npm run build` | success; `next.config.ts` evaluated, `serverActions` experiment applied |
| `git diff HEAD --check` | clean |

## 9. Known limitations and risks

- **Live verification is pending (F11).** No approved Clerk credential is available to this session, so the authenticated upload was **not** executed against the running Laravel/R2 stack. The coverage is transport, authorization, parsing and rendering assertions plus pure origin/allow-list logic. The owner runs the operator smoke test.
- **The dev media origin is not production.** The configured value is a Cloudflare R2 **public development URL**: rate-limited and development-only. It must be replaced by a real CDN/custom domain before production. No credential is in the frontend; the R2 keys remain backend-only.
- **Uploads can create duplicates.** Laravel accepts repeated uploads; nothing in 19.8A deduplicates, and no endpoint reports an existing-file match.
- **Advisory client validation is a mirror.** `product-image-form.ts` mirrors the backend's accepted types and size so the operator gets immediate feedback. If `config/product_images.php` changes, the mirror drifts. Laravel remains authoritative for every upload.
- **The image allow-list is compiled at server start.** `next.config.ts` fixes the `next/image` allow-list from `CATALOG_MEDIA_BASE_URL` when the process starts, while the gallery reads the same variable per request. They agree unless the variable changes without a restart; in that window the gallery deems a URL renderable that the image configuration rejects. The render boundary degrades that to the placeholder rather than a crash, and the placeholder now names the restart requirement. **Changing the variable requires a restart.** Observed live once (see §11).
- **Variant creation is untouched** and remains read-only (F8). No variant control was added.

## 10. Exit condition

For the Phase 19.8A scope:

- an operator holding `products.manage` can select a JPEG/PNG/WebP image on a product detail page and upload it through `POST /api/v1/products/{product}/images`; ✅ implemented
- the upload is authorized server-side in the action **and** by Laravel, and no bearer token reaches the browser; ✅ verified
- the returned image records render as previews, with their real `alt_text`, `sort_order` and `is_primary` displayed read-only; ✅ verified
- a missing/invalid media origin, an unrenderable URL, a broken image and an empty collection each produce a distinct, non-fatal state; ✅ verified
- the UI never offers deletion, ordering, alt-text editing or primary selection; ✅ verified
- injections of client-side checks never replace Laravel validation; ✅ verified
- all gates pass. ✅

The single open item is the **operator live smoke test (F11)**, which is a verification gap shared with Phase 19.7, not a gap in the 19.8A scope.

## 11. Post-completion fix (2026-10-16)

**Reported:** the admin product detail page showed "image preview unavailable" alongside a Next.js runtime error: `Invalid src prop … hostname "pub-…r2.dev" is not configured under images in your next.config.js`. The uploaded image rendered correctly on the customer website.

**Root cause — not a code defect in the allow-list, but a stale server plus a misleading placeholder.** `CATALOG_MEDIA_BASE_URL` was added to `frontend/admin/.env.local` at 13:27:57, while the admin dev server had started at 13:27:39 — 18 seconds earlier — so `next.config.ts` had already compiled `remotePatterns: []`. The dev server's *runtime* environment later picked the variable up, so the gallery's guard claimed the URL was renderable while the image configuration allowed nothing. The guard and the configuration disagreed, `next/image` rejected the `src`, the render boundary caught it, and the operator saw the generic placeholder with no hint of the cause. The allow-list logic itself was correct throughout.

**Evidence gathered:**

- `curl` against the running dev server's optimizer returned `400 "url" parameter is not allowed` — the compiled allow-list genuinely lacked the host.
- `.env.local` mtime (13:27:57) post-dated the server start (13:27:39); `CATALOG_MEDIA_BASE_URL` was absent from the shell environment, so the value can only have come from `.env.local`.
- A production build's `.next/required-server-files.json` carried the correct pattern, proving `next.config.ts` does read `.env.local` when Next loads it — so the fault was the stale process, not the config's env access.

**Changes made:**

- `components/catalog/product-image-preview.tsx`: `ProductImageFallback` now takes a required `reason` (`"origin" | "load"`). An off-origin URL is reported as a data problem; a failed render names the image configuration and the restart requirement. Previously both read as "Image preview unavailable. The stored image is unchanged.", which hid the cause.
- `components/catalog/product-image-gallery.tsx`: passes `reason="origin"` for the guard case.
- `tests/product-media.test.tsx`: asserts the two reasons render different copy, that the load copy names the restart requirement, and that the guard and boundary each pass their own reason.
- `frontend/admin/README.md` and `frontend/admin/AGENTS.md`: state that the allow-list is compiled at server start and that changing `CATALOG_MEDIA_BASE_URL` requires a restart.

**Verified after the fix (live, non-intrusive):**

- The admin dev server on `:3001` was restarted at 13:58:58, after the env edit. Its optimizer now returns `200 image/jpeg` for the real uploaded URL (`products/prod_4/img_01m525957wpxwc5t3qjb1v9bth.jpg`) and `400` for an off-origin host — the allow-list is correct and still narrow.
- The same probe was run against a `next start` of this build on a spare port: `200 image/jpeg`, 19,034 bytes for the real URL; `400` for `https://evil.example/x.jpg`. This also confirms the R2 object is readable, not just recorded.
- Gates re-run: `npm run lint` clean, `npm run typecheck` clean, `npm run test` 174/174, `npm run build` success.

**Still owner-run (F11):** the authenticated page render. The transport-level evidence above covers the configuration and delivery path, but only the owner's signed-in session can confirm the rendered gallery on `/products/{product}`.

**Correction to an earlier assumption:** the render boundary **does** catch during Next's server rendering (it caught here and kept the page alive). Bare `renderToStaticMarkup`/`renderToReadableStream` without `onError` do not honour boundaries, which is what the earlier probe measured; Next supplies `onError`, so the boundary works in the real framework path. The boundary is therefore load-bearing, not decorative.
