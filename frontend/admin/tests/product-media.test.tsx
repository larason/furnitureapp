import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { ImageConfigContext } from "next/dist/shared/lib/image-config-context.shared-runtime.js";
import { imageConfigDefault } from "next/dist/shared/lib/image-config.js";
import { ProductImageFallback } from "../components/catalog/product-image-preview";
import { ProductImageGallery } from "../components/catalog/product-image-gallery";
import { catalogMediaRemotePatterns, isRenderableMediaUrl, resolveCatalogMediaOrigin } from "../lib/catalog/media-origin";
import {
  PRODUCT_IMAGE_ACCEPT_ATTRIBUTE,
  PRODUCT_IMAGE_MAX_BYTES,
  imageValidationMessage,
  readProductImageDraft,
  validateImageFile,
} from "../lib/catalog/product-image-form";
import type { ProductImageDetail } from "../lib/catalog/product-resource";
import { theme } from "../theme";

const ADMIN_ROOT = fileURLToPath(new URL("..", import.meta.url));
const read = (relative: string): string => readFileSync(join(ADMIN_ROOT, relative), "utf8");

/** Representative origin only: no local environment value is copied into a test. */
const MEDIA_ORIGIN = "https://media.example.test";
const MEDIA_HOSTNAME = "media.example.test";

const GALLERY = read("components/catalog/product-image-gallery.tsx");
const PREVIEW = read("components/catalog/product-image-preview.tsx");
const UPLOADER = read("components/catalog/product-image-uploader.tsx");
const IMAGE_ACTIONS = read("lib/catalog/product-image-actions.ts");
const IMAGE_FORM = read("lib/catalog/product-image-form.ts");
const PRODUCTS_API = read("lib/catalog/products-api.ts");
const SERVER_REQUEST = read("lib/api/server-request.ts");
const CLIENT = read("lib/api/client.ts");
const NEXT_CONFIG = read("next.config.ts");
const DETAIL_VIEW = read("components/catalog/product-detail-view.tsx");
const DETAIL_PAGE = read("app/(protected)/products/[product]/page.tsx");
const ADMIN_ENV_EXAMPLE = read(".env.example");

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const text = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
const withoutImports = (source: string): string => source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");

/** `next/image` reads its allow-list from this context, which the test runtime does not inject. */
function renderWithMediaConfig(element: React.ReactElement): string {
  const config = { ...imageConfigDefault, remotePatterns: [{ protocol: "https" as const, hostname: MEDIA_HOSTNAME, port: "", pathname: "/**", search: "" }] };
  return renderToStaticMarkup(
    <ThemeProvider theme={theme}>
      <ImageConfigContext.Provider value={config}>{element}</ImageConfigContext.Provider>
    </ThemeProvider>,
  );
}

function withMediaOrigin(value: string | undefined, run: () => void): void {
  const previous = process.env.CATALOG_MEDIA_BASE_URL;
  if (value === undefined) delete process.env.CATALOG_MEDIA_BASE_URL;
  else process.env.CATALOG_MEDIA_BASE_URL = value;
  try {
    run();
  } finally {
    if (previous === undefined) delete process.env.CATALOG_MEDIA_BASE_URL;
    else process.env.CATALOG_MEDIA_BASE_URL = previous;
  }
}

function galleryImage(overrides: Partial<ProductImageDetail> = {}): ProductImageDetail {
  return { id: "img_1", url: `${MEDIA_ORIGIN}/products/dev/sofa/main.webp`, altText: "Nordic sofa", sortOrder: 0, isPrimary: true, ...overrides };
}

function imageFile(name: string, type: string, size: number): File {
  const file = new File([new Uint8Array(1)], name, { type });
  Object.defineProperty(file, "size", { value: size });
  return file;
}

// ---------------------------------------------------------------------------
// Media origin configuration
// ---------------------------------------------------------------------------

test("the configured HTTPS media origin resolves to an origin and base path", () => {
  assert.deepEqual(resolveCatalogMediaOrigin(`${MEDIA_ORIGIN}/`), { protocol: "https", hostname: MEDIA_HOSTNAME, port: "", origin: MEDIA_ORIGIN, basePath: "" });
  assert.equal(resolveCatalogMediaOrigin("https://cdn.test/media")?.basePath, "/media");
  assert.equal(resolveCatalogMediaOrigin("https://cdn.test:8443")?.port, "8443");
});

test("missing, insecure, credential-bearing and malformed media origins fail closed", () => {
  for (const value of [undefined, "", "   ", "not a url", "http://cdn.test", "ftp://cdn.test", "https://user:pass@cdn.test", "https://cdn.test/?a=1", "https://cdn.test/#x"]) {
    assert.equal(resolveCatalogMediaOrigin(value), null, `${String(value)} must not resolve to an origin.`);
  }
});

test("only the configured origin and base path are renderable", () => {
  const origin = resolveCatalogMediaOrigin(MEDIA_ORIGIN);
  assert.equal(isRenderableMediaUrl(`${MEDIA_ORIGIN}/a/b.webp`, origin), true);
  assert.equal(isRenderableMediaUrl(`${MEDIA_ORIGIN}/a.webp`, origin), true);

  for (const rejected of [
    "http://media.example.test/a.webp",
    "https://other.example.test/a.webp",
    "https://media.example.test.attacker.test/a.webp",
    "https://attacker.test/media.example.test/a.webp",
    "https://media.example.test@attacker.test/a.webp",
    "//media.example.test/a.webp",
    "/a.webp",
    "not a url",
  ]) {
    assert.equal(isRenderableMediaUrl(rejected, origin), false, `${rejected} must not be renderable.`);
  }

  assert.equal(isRenderableMediaUrl(`${MEDIA_ORIGIN}/a.webp`, null), false, "an unconfigured origin must render nothing.");
});

test("a base-path origin scopes renderability to that path", () => {
  const origin = resolveCatalogMediaOrigin("https://cdn.test/media");
  assert.equal(isRenderableMediaUrl("https://cdn.test/media/a.webp", origin), true);
  assert.equal(isRenderableMediaUrl("https://cdn.test/media", origin), true);
  assert.equal(isRenderableMediaUrl("https://cdn.test/mediafoo/a.webp", origin), false);
  assert.equal(isRenderableMediaUrl("https://cdn.test/other/a.webp", origin), false);
});

test("the image allow-list is one exact HTTPS host or nothing at all", () => {
  assert.deepEqual(catalogMediaRemotePatterns(null), [], "an unconfigured origin must allow no host.");
  assert.deepEqual(catalogMediaRemotePatterns(resolveCatalogMediaOrigin(MEDIA_ORIGIN)), [{ protocol: "https", hostname: MEDIA_HOSTNAME, port: "", pathname: "/**", search: "" }]);
  assert.deepEqual(catalogMediaRemotePatterns(resolveCatalogMediaOrigin("https://cdn.test/media"))[0].pathname, "/media/**");
});

test("next.config derives the allow-list from configuration, never a fixed host", () => {
  assert.match(NEXT_CONFIG, /catalogMediaRemotePatterns\(resolveCatalogMediaOrigin\(process\.env\.CATALOG_MEDIA_BASE_URL\)\)/);
  assert.equal(/\bhostname:\s*"/.test(NEXT_CONFIG), false, "no hostname may be hardcoded in the config.");
  assert.equal(/r2\.dev|example\.com|amazonaws\.com/.test(NEXT_CONFIG), false, "no fixed media host may appear in the config.");
  assert.match(NEXT_CONFIG, /remotePatterns/, "the narrow allow-list must be applied to next/image.");
});

test("the media origin is documented in the tracked example environment file without a credential", () => {
  assert.match(ADMIN_ENV_EXAMPLE, /^CATALOG_MEDIA_BASE_URL=$/m);
  assert.equal(/r2\.dev|R2_SECRET|R2_ACCESS/.test(ADMIN_ENV_EXAMPLE), false);
});

// ---------------------------------------------------------------------------
// Image previews
// ---------------------------------------------------------------------------

test("the gallery renders an allow-listed image with its real alt text and read-only metadata", () => {
  withMediaOrigin(MEDIA_ORIGIN, () => {
    const markup = renderWithMediaConfig(<ProductImageGallery productName="Nordic Sofa" images={[galleryImage()]} />);
    assert.match(markup, /data-nimg="fill"/, "the preview must be a next/image fill element.");
    assert.match(markup, /object-fit:contain/, "the preview must preserve the image aspect ratio.");
    assert.match(markup, /alt="Nordic sofa"/, "the stored alt text must be used verbatim.");

    const visible = text(markup);
    assert.ok(visible.includes("Sort order: 0"), "the API sort order must be shown.");
    assert.ok(visible.includes("Primary image"), "primary status must be shown.");
    assert.equal(/reorder|set primary|delete image|edit alt/i.test(visible), false, "the metadata must stay read-only.");
  });
});

test("an unconfigured media origin shows an explicit notice and never a fabricated image", () => {
  withMediaOrigin(undefined, () => {
    const markup = render(<ProductImageGallery productName="Nordic Sofa" images={[galleryImage()]} />);
    const visible = text(markup);
    assert.ok(visible.includes("Image previews are disabled"), "the configuration gap must be stated.");
    assert.ok(visible.includes("Not previewed"), "the image must fall back, not disappear.");
    assert.ok(visible.includes("Sort order: 0"), "stored metadata must still be shown.");
    assert.equal(markup.includes("<img"), false, "no image may be rendered from an unallowlisted origin.");
  });
});

test("a broken or rejected preview degrades to a fallback instead of breaking the page", () => {
  assert.match(PREVIEW, /^"use client";/);
  assert.match(PREVIEW, /onError=\{\(\) => setFailed\(true\)\}/, "a load failure must swap in the fallback.");
  assert.match(PREVIEW, /getDerivedStateFromError/, "a rejected src must be contained by a render boundary.");

  const loadFailure = render(<ProductImageFallback reason="load" />);
  assert.ok(text(loadFailure).includes("Preview unavailable"));
  assert.match(text(loadFailure), /restart/i, "a rejected src must name the restart requirement, not a mystery.");
  assert.equal(/src=|https?:/.test(loadFailure), false, "the fallback must not leak a URL.");

  const offOrigin = text(render(<ProductImageFallback reason="origin" />));
  assert.ok(offOrigin.includes("Not previewed"), "an off-origin URL must be reported as a data problem.");
  assert.notEqual(offOrigin, text(loadFailure), "a data problem and a render failure must not read the same.");

  assert.match(GALLERY, /<ProductImageFallback reason="origin" \/>/, "the guard must report the origin reason.");
  assert.match(PREVIEW, /<ProductImageFallback reason="load" \/>/, "the boundary and onError must report the load reason.");
});

test("an empty image collection is an empty state, never an error", () => {
  withMediaOrigin(undefined, () => {
    const visible = text(render(<ProductImageGallery productName="Nordic Sofa" images={[]} />));
    assert.ok(visible.includes("No images recorded"));
    assert.ok(visible.includes("not an API failure"));
    assert.equal(/unavailable|error/i.test(visible), false);
  });
});

test("the gallery falls back to a neutral accessible label when alt text is absent", () => {
  withMediaOrigin(MEDIA_ORIGIN, () => {
    const markup = renderWithMediaConfig(<ProductImageGallery productName="Nordic Sofa" images={[galleryImage({ altText: null })]} />);
    assert.match(markup, /alt="Nordic Sofa — product image 1"/);
    assert.ok(text(markup).includes("No alt text is recorded for this image."));
  });
});

test("the media section is read-only until a media mutation API exists", () => {
  for (const forbidden of ["Delete", "Reorder", "Make primary", "Save alt", "alt_text", "is_primary", "sort_order"]) {
    assert.equal(GALLERY.includes(forbidden), false, `the gallery must not offer ${forbidden}.`);
  }
  assert.match(DETAIL_VIEW, /Ordering, alt text, primary selection and deletion are not editable because no media mutation API exists yet/);
  assert.match(DETAIL_VIEW, /ProductImageGallery/);
  assert.match(DETAIL_VIEW, /mediaUpload\?: ReactNode/);
  assert.equal(/ProductImageUploader|product-image-actions/.test(DETAIL_VIEW), false, "the view must stay free of the server-only upload action.");
  assert.match(DETAIL_PAGE, /<ProductImageUploader productId=\{product\.value\.id\} \/>/);
  assert.equal(/managed in Phase 19\.8/.test(DETAIL_VIEW), false, "the old placeholder copy must be gone.");
});

// ---------------------------------------------------------------------------
// Upload transport, authorization and validation
// ---------------------------------------------------------------------------

test("FormData is forwarded as multipart data and the JSON path is untouched", () => {
  assert.match(CLIENT, /if \(body instanceof FormData\) return body;/);
  assert.ok(CLIENT.indexOf("if (body instanceof FormData) return body;") < CLIENT.indexOf('headers.set("Content-Type", "application/json");'), "FormData must return before any JSON content type is set.");
  assert.equal(/multipart\/form-data/.test(CLIENT), false, "the boundary must be left to the runtime.");
  assert.equal(/multipart\/form-data/.test(SERVER_REQUEST), false, "no manual multipart content type may be set.");
  assert.match(SERVER_REQUEST, /export async function requestOperationalUpload/);
  assert.match(SERVER_REQUEST, /requestOperationalApi<T>\(\{ \.\.\.rest, body: form, timeoutMs: UPLOAD_TIMEOUT_MS \}\)/);
  assert.equal(/JSON\.stringify\(form/.test(SERVER_REQUEST + IMAGE_ACTIONS), false, "an upload must never be JSON serialized.");
});

test("only the CAT-009 upload endpoint is contacted and no media mutation is invented", () => {
  assert.ok(PRODUCTS_API.includes("`/products/${encodeURIComponent(identifier)}/images`"));
  assert.match(PRODUCTS_API, /requestOperationalUpload/);
  for (const forbidden of ["DELETE", "sort_order", "alt_text", "is_primary", "reorder", "setPrimary", "variants"]) {
    assert.equal(PRODUCTS_API.includes(forbidden), false, `the product API module must not use ${forbidden}.`);
  }
  assert.equal(/images\/[^`]*\/(delete|reorder|primary)/.test(PRODUCTS_API), false);
});

test("the upload repeats the exact permission before reading the file", () => {
  assert.match(IMAGE_ACTIONS, /^"use server";/);
  assert.match(IMAGE_ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{ permission: PRODUCT_ROUTE\.permission, role: PRODUCT_ROUTE\.role \}\)/);
  const body = withoutImports(IMAGE_ACTIONS);
  assert.ok(body.indexOf("resolveAdminRouteAccess(") < body.indexOf("await submitProductImage("), "authorization must run before the upload request.");
  assert.match(IMAGE_ACTIONS, /revalidatePath\(ADMIN_PRODUCT_PATHS\.detail\(prepared\.productId\)\)/);
});

test("the forwarded form carries only the image field the backend accepts", () => {
  const body = withoutImports(IMAGE_ACTIONS);
  assert.match(body, /form\.set\(PRODUCT_IMAGE_FIELD, file, file\.name\)/);
  assert.equal((body.match(/form\.(set|append)\(/g) ?? []).length, 1, "no field other than the image may be forwarded.");
  assert.match(IMAGE_FORM, /PRODUCT_IMAGE_FIELD = "image"/);
  assert.match(IMAGE_FORM, /PRODUCT_IMAGE_PRODUCT_FIELD = "product_id"/);
});

test("no bearer token or client-side credential reaches the browser component", () => {
  assert.equal(/Bearer|getToken|session_token|CLERK_SECRET/.test(UPLOADER), false, "the uploader must not receive a token.");
  assert.equal(/Bearer|getToken|localStorage|sessionStorage/.test(IMAGE_ACTIONS), false, "the action must not persist or return a token.");
  assert.equal(/server-only|server-request|lib\/api\/client/.test(UPLOADER), false, "the uploader must not import the server transport.");
});

test("a double submit is prevented by disabling the control while pending", () => {
  assert.match(UPLOADER, /useFormStatus\(\)/);
  assert.match(UPLOADER, /disabled=\{pending\}/);
  assert.match(UPLOADER, /useActionState\(uploadProductImageAction/);
});

test("the file input derives its accepted types from the backend constraints", () => {
  assert.match(UPLOADER, /accept=\{PRODUCT_IMAGE_ACCEPT_ATTRIBUTE\}/);
  assert.equal(PRODUCT_IMAGE_ACCEPT_ATTRIBUTE, "image/jpeg,image/png,image/webp");
  assert.ok(PRODUCT_IMAGE_MAX_BYTES > 0);
});

test("the upload draft rejects a missing product, a missing file and an invalid file", () => {
  const noProduct = new FormData();
  noProduct.set("image", imageFile("a.png", "image/png", 1024));
  const missingProduct = readProductImageDraft(noProduct);
  assert.equal(missingProduct.ok, false);
  assert.equal(missingProduct.ok === false ? missingProduct.state.formError !== undefined : false, true);

  const noFile = new FormData();
  noFile.set("product_id", "prd_1");
  noFile.set("image", "not-a-file");
  const missingFile = readProductImageDraft(noFile);
  assert.equal(missingFile.ok, false);
  assert.equal(missingFile.ok === false ? missingFile.state.fieldError !== undefined : false, true);

  const valid = new FormData();
  valid.set("product_id", "prd_1");
  valid.set("image", imageFile("a.webp", "image/webp", 2048));
  assert.equal(readProductImageDraft(valid).ok, true);
});

test("advisory file validation mirrors the backend type and size constraints", () => {
  assert.equal(validateImageFile(imageFile("a.jpg", "image/jpeg", 1024)), null);
  assert.equal(validateImageFile(imageFile("a.webp", "image/webp", PRODUCT_IMAGE_MAX_BYTES)), null);
  assert.notEqual(validateImageFile(imageFile("a.gif", "image/gif", 1024)), null);
  assert.notEqual(validateImageFile(imageFile("a.svg", "image/svg+xml", 1024)), null);
  assert.notEqual(validateImageFile(imageFile("a.jpg", "image/jpeg", 0)), null);
  assert.notEqual(validateImageFile(imageFile("a.jpg", "image/jpeg", PRODUCT_IMAGE_MAX_BYTES + 1)), null);
  assert.equal(validateImageFile(imageFile("a.bin", "", 1024)), null, "an unreported type is left for Laravel to decide.");
});

test("a Laravel validation error for the image field is preferred over a generic message", () => {
  const message = imageValidationMessage([{ code: "INVALID_VALUE", message: "The image must be a file of type: jpeg, png, webp.", field: "image" }], "generic");
  assert.equal(message, "The image must be a file of type: jpeg, png, webp.");
  assert.equal(imageValidationMessage([{ code: "X", message: "Something else.", field: "name" }], "generic"), "Something else.");
  assert.equal(imageValidationMessage([], "generic"), "generic");
});

test("upload failures map to safe, actionable messages without leaking internals", () => {
  assert.match(IMAGE_ACTIONS, /413: TOO_LARGE_MESSAGE/);
  assert.match(IMAGE_ACTIONS, /status === 429/);
  assert.match(IMAGE_ACTIONS, /404: MISSING_RESOURCE_MESSAGE/);
  assert.equal(/stack|exception|file_path|R2_|vendor\//.test(IMAGE_ACTIONS), false, "no internal detail may be rendered.");
});
