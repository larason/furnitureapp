import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { ImageConfigContext } from "next/dist/shared/lib/image-config-context.shared-runtime.js";
import { imageConfigDefault } from "next/dist/shared/lib/image-config.js";
import { ProductImageAltTextEditor } from "../components/catalog/product-image-alt-text-editor";
import { ProductImageFallback } from "../components/catalog/product-image-preview";
import { ProductImageGallery } from "../components/catalog/product-image-gallery";
import { catalogMediaRemotePatterns, isRenderableMediaUrl, resolveCatalogMediaOrigin, type CatalogMediaOrigin } from "../lib/catalog/media-origin";
import {
  PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH,
  canMoveProductImage,
  movedProductImageIds,
  normalizeProductImageAltText,
  productImageAltTextError,
  productImageMutationFailureOutcome,
  productImageMutationSuccessMessage,
  type ProductImageGalleryActions,
  type ProductImageMutationOutcome,
} from "../lib/catalog/product-image-mutations";
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
const RESOLVED_ORIGIN = resolveCatalogMediaOrigin(MEDIA_ORIGIN);

const GALLERY = read("components/catalog/product-image-gallery.tsx");
const ALT_TEXT_EDITOR = read("components/catalog/product-image-alt-text-editor.tsx");
const PREVIEW = read("components/catalog/product-image-preview.tsx");
const UPLOADER = read("components/catalog/product-image-uploader.tsx");
const MUTATIONS = read("lib/catalog/product-image-mutations.ts");
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

function galleryImage(overrides: Partial<ProductImageDetail> = {}): ProductImageDetail {
  return { id: "img_1", url: `${MEDIA_ORIGIN}/products/dev/sofa/main.webp`, altText: "Nordic sofa", sortOrder: 0, isPrimary: true, ...overrides };
}

/** Stands in for the four Server Actions the page injects; nothing here reaches Laravel. */
function stubActions(overrides: Partial<ProductImageGalleryActions> = {}): ProductImageGalleryActions {
  const outcome: ProductImageMutationOutcome = { status: "success", message: "Recorded." };
  return {
    deleteImage: async () => outcome,
    updateAltText: async () => outcome,
    selectPrimary: async () => outcome,
    reorderImages: async () => outcome,
    ...overrides,
  };
}

function renderGallery(images: readonly ProductImageDetail[], mediaOrigin: CatalogMediaOrigin | null = RESOLVED_ORIGIN, actions: ProductImageGalleryActions = stubActions()): string {
  return renderWithMediaConfig(<ProductImageGallery productId="prd_1" productName="Nordic Sofa" images={images} mediaOrigin={mediaOrigin} actions={actions} />);
}

interface ButtonState {
  readonly label: string;
  readonly disabled: boolean;
}

/** A MUI class such as `Mui-disabled` must never be mistaken for the `disabled` attribute. */
function buttonStates(markup: string): readonly ButtonState[] {
  return [...markup.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)].map((match) => ({
    label: text(match[2]),
    disabled: /(^|\s)disabled(=|\s|$)/.test(match[1]),
  }));
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
// The gallery
// ---------------------------------------------------------------------------

test("the gallery renders an allow-listed image with its real alt text and authoritative metadata", () => {
  const markup = renderGallery([galleryImage()]);
  assert.match(markup, /data-nimg="fill"/, "the preview must be a next/image fill element.");
  assert.match(markup, /object-fit:contain/, "the preview must preserve the image aspect ratio.");
  assert.match(markup, /alt="Nordic sofa"/, "the stored alt text must be used verbatim.");

  const visible = text(markup);
  assert.ok(visible.includes("Sort order: 0"), "the API sort order must be shown.");
  assert.ok(visible.includes("Primary image"), "primary status must be shown.");
  assert.ok(visible.includes("Image 1 of 1"), "the gallery position must be stated without redefining sort order.");
});

test("every approved media control is offered with an accessible name and a boundary state", () => {
  const markup = renderGallery([galleryImage({ id: "img_1" }), galleryImage({ id: "img_2", sortOrder: 1, isPrimary: false }), galleryImage({ id: "img_3", sortOrder: 2, isPrimary: false, altText: null })]);

  assert.match(markup, /role="group" aria-label="Controls for image 1 of 3"/);
  assert.match(markup, /role="group" aria-label="Controls for image 3 of 3"/);

  const labels = buttonStates(markup).map((state) => state.label);
  assert.equal(labels.filter((label) => label === "Move up").length, 3);
  assert.equal(labels.filter((label) => label === "Move down").length, 3);
  assert.equal(labels.filter((label) => label === "Edit alt text").length, 3);
  assert.equal(labels.filter((label) => label === "Delete").length, 3);
  assert.equal(labels.filter((label) => label === "Make primary").length, 2, "the primary image must not offer a redundant control.");
});

test("move controls are disabled at the gallery boundaries so a no-op reorder is never sent", () => {
  const states = buttonStates(renderGallery([galleryImage({ id: "img_1" }), galleryImage({ id: "img_2", sortOrder: 1, isPrimary: false }), galleryImage({ id: "img_3", sortOrder: 2, isPrimary: false })]));
  const moveUps = states.filter((state) => state.label === "Move up");
  const moveDowns = states.filter((state) => state.label === "Move down");

  assert.deepEqual(moveUps.map((state) => state.disabled), [true, false, false], "the first image cannot move up.");
  assert.deepEqual(moveDowns.map((state) => state.disabled), [false, false, true], "the last image cannot move down.");
});

test("an unconfigured media origin shows an explicit notice and never a fabricated image", () => {
  const markup = renderGallery([galleryImage()], null);
  const visible = text(markup);
  assert.ok(visible.includes("Image previews are disabled"), "the configuration gap must be stated.");
  assert.ok(visible.includes("Not previewed"), "the image must fall back, not disappear.");
  assert.ok(visible.includes("Sort order: 0"), "stored metadata must still be shown.");
  assert.equal(markup.includes("<img"), false, "no image may be rendered from an unallowlisted origin.");
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
  const visible = text(renderGallery([], null));
  assert.ok(visible.includes("No images recorded"));
  assert.ok(visible.includes("not an API failure"));
  assert.equal(/unavailable|error/i.test(visible), false);
});

test("the gallery falls back to a neutral accessible label when alt text is absent", () => {
  const markup = renderGallery([galleryImage({ altText: null })]);
  assert.match(markup, /alt="Nordic Sofa — product image 1"/);
  assert.ok(text(markup).includes("No alt text is recorded for this image."));
});

test("a product with no primary image says so and never promotes one on its own", () => {
  const markup = renderGallery([galleryImage({ isPrimary: false }), galleryImage({ id: "img_2", sortOrder: 1, isPrimary: false })]);
  assert.ok(text(markup).includes("No image is selected as the primary image"));
  assert.equal(/Status: Primary image/.test(markup), false, "an absent primary image must not be implied.");
  assert.match(GALLERY, /deleting the primary image never promotes another one automatically/);
  assert.equal(buttonStates(markup).filter((state) => state.label === "Make primary").length, 2, "either image may still be promoted.");
});

test("the gallery derives every displayed value from the revalidated product resource", () => {
  assert.equal(/useOptimistic|setImages|setOrder|setPrimary|setAltText|\.sort\(|sort_order|is_primary|localStorage/.test(GALLERY), false, "no local order, alt text or primary state may be invented.");
  assert.match(GALLERY, /const orderedIds = images\.map\(\(image\) => image\.id\)/, "the order sent to CAT-018 must come from the rendered resource.");
  assert.match(GALLERY, /return \{ status: "error", message: UNEXPECTED_MESSAGE \};/, "an unexpected rejection must not leave the gallery pending.");
  assert.equal(/setTimeout|setInterval|Math\.random/.test(GALLERY), false, "no arbitrary timer may simulate completion.");
});

test("exactly one mutation may be in flight, so duplicate and conflicting requests cannot be sent", () => {
  assert.match(GALLERY, /const mutationInFlight = useRef\(false\);/, "the guard must not depend on the previous render's state.");
  assert.equal((GALLERY.match(/if \(mutationInFlight\.current\) return false;/g) ?? []).length, 1, "one in-flight guard must cover every media mutation.");

  const guard = GALLERY.indexOf("if (mutationInFlight.current) return false;");
  const claim = GALLERY.indexOf("mutationInFlight.current = true;");
  const request = GALLERY.indexOf("await settle(operation)");
  const release = GALLERY.indexOf("mutationInFlight.current = false;");
  assert.ok(guard > 0 && claim > guard, "the flag must be claimed immediately after the guard.");
  assert.ok(request > claim, "the flag must be set before the request starts.");
  assert.ok(release > request, "the flag must be released once the operation settles.");
  assert.equal((GALLERY.match(/mutationInFlight\.current = false;/g) ?? []).length, 1);
  assert.equal((GALLERY.match(/mutationInFlight\.current = true;/g) ?? []).length, 1, "the flag must be claimed in exactly one place.");
  assert.match(GALLERY, /\): boolean \{/, "the caller must be able to tell whether the mutation was accepted.");

  assert.match(GALLERY, /const pending = pendingKind !== null;/, "state still drives what is rendered.");
  assert.match(GALLERY, /disabled=\{disabled \|\| !canMoveUp\}/);
  assert.match(GALLERY, /disabled=\{disabled \|\| !canMoveDown\}/);
  assert.match(GALLERY, /disabled=\{disabled\}/);
});

test("deletion requires an explicit confirmation that identifies the image", () => {
  assert.match(GALLERY, /<ConfirmDialog/);
  assert.match(GALLERY, /title="Delete this image\?"/);
  assert.match(GALLERY, /confirmLabel="Delete image"/);
  assert.equal(/Delete this image\?/.test(text(renderGallery([galleryImage()]))), false, "the confirmation must stay closed until it is requested.");

  assert.match(GALLERY, /deleteImage: \(\) => setDeleteTarget\(image\)/, "the Delete control must only open the confirmation.");
  assert.match(GALLERY, /onCancel=\{\(\) => setDeleteTarget\(null\)\}/, "cancelling must only close the dialog.");

  const confirmIndex = GALLERY.indexOf("function confirmDelete");
  const requestIndex = GALLERY.indexOf("actions.deleteImage");
  assert.ok(confirmIndex > 0 && requestIndex > confirmIndex, "the request must be issued from the confirmation handler, not the Delete control.");
  assert.equal(GALLERY.indexOf("actions.deleteImage", requestIndex + 1), -1, "no other control may call CAT-015.");

  const accepted = GALLERY.indexOf('runMutation("delete"');
  const closed = GALLERY.indexOf("setDeleteTarget(null);", confirmIndex);
  assert.ok(accepted > confirmIndex, "the confirmation handler must start the delete mutation.");
  assert.ok(closed > accepted, "the dialog must close only once the delete was accepted, so a refused confirmation is never dropped silently.");
});

test("the delete confirmation describes the image and never promises a synchronous object removal", () => {
  const description = GALLERY.slice(GALLERY.indexOf("description={activeDeleteTarget"), GALLERY.indexOf("confirmLabel"));
  assert.match(description, /imageDescription\(activeDeleteTarget, deletePosition, productName\)/);
  assert.match(description, /\$\{deletePosition\} of \$\{images\.length\}/);
  assert.match(description, /stored object/, "the durability of the stored object must be described, not assumed.");
  assert.equal(/synchronous/i.test(GALLERY + MUTATIONS), false, "no copy may promise a synchronous object deletion.");
});

test("a confirmation target that left the refreshed gallery is dropped, so no request or index is invented", () => {
  assert.match(GALLERY, /const activeDeleteTarget = deleteTarget !== null && orderedIds\.includes\(deleteTarget\.id\) \? deleteTarget : null;/, "the target must be re-validated against the current image list.");
  assert.match(GALLERY, /const deletePosition = activeDeleteTarget === null \? 0 : orderedIds\.indexOf\(activeDeleteTarget\.id\) \+ 1;/, "the position must come from the validated target, so it can never render as image 0.");
  assert.equal(/deletePosition = deleteTarget/.test(GALLERY), false, "the stale target must not drive the position any more.");

  assert.match(GALLERY, /open=\{activeDeleteTarget !== null\}/, "the dialog must close when the target is gone.");
  assert.equal(/open=\{deleteTarget !== null\}/.test(GALLERY), false, "the dialog must not stay open on a stale target.");

  assert.match(GALLERY, /if \(activeDeleteTarget === null\) return;/, "confirming a vanished target must not issue a request.");
  assert.equal((GALLERY.match(/actions\.deleteImage/g) ?? []).length, 1, "CAT-015 must still be reachable from exactly one place.");
});

test("alt-text editing starts from the stored value and the draft survives a failed save", () => {
  assert.match(GALLERY, /draft: image\.altText \?\? ""/, "the editor must start from the stored alt text.");
  assert.match(GALLERY, /onSuccess[\s\S]*setEditor\(null\)/, "only a confirmed save may close the editor.");
  assert.match(GALLERY, /feedback\.kind === "alt-text" && feedback\.status === "error" \? feedback\.message : null/, "the editor must own only genuine validation failures, so the draft survives them.");
  assert.match(GALLERY, /feedback\.kind !== "alt-text" \|\| feedback\.status !== "error"\) \? feedback : null/, "every other outcome, including a stale gallery, must still be announced.");
  assert.match(GALLERY, /setFeedback\(\{ kind: "alt-text", status: "error", message: invalid \}\)/, "a locally rejected draft must be reported without a request.");

  const markup = render(<ProductImageAltTextEditor imageOrdinal={2} value="Nordic sofa" error={null} disabled={false} onChange={() => {}} onSave={() => {}} onCancel={() => {}} />);
  const visible = text(markup);
  assert.ok(visible.includes("Alt text for image 2"));
  assert.ok(visible.includes("11 of 500 characters used"));
  assert.ok(visible.includes("Save alt text") && visible.includes("Cancel"));
  assert.ok(visible.includes("Nordic sofa"), "the stored value must render in the control.");
  assert.equal(/aria-invalid="true"/.test(markup), false);

  const invalid = render(<ProductImageAltTextEditor imageOrdinal={2} value="Nordic sofa" error="Alt text must be 500 characters or fewer." disabled={false} onChange={() => {}} onSave={() => {}} onCancel={() => {}} />);
  assert.ok(text(invalid).includes("Alt text must be 500 characters or fewer."));
  assert.match(invalid, /aria-invalid="true"/, "a rejected value must be exposed to assistive technology.");
});

test("the alt-text control enforces the documented maximum and never leaves the operator without feedback", () => {
  assert.match(ALT_TEXT_EDITOR, /maxLength: PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH/);
  assert.match(ALT_TEXT_EDITOR, /label=\{`Alt text for image \$\{imageOrdinal\}`\}/);
  assert.match(ALT_TEXT_EDITOR, /disabled=\{disabled\}/);
  assert.match(ALT_TEXT_EDITOR, /type="submit"/, "Enter must submit the editor.");
  assert.match(ALT_TEXT_EDITOR, /event\.preventDefault\(\)/, "the editor must not trigger a browser navigation.");
});

// ---------------------------------------------------------------------------
// Media mutation vocabulary
// ---------------------------------------------------------------------------

test("a move sends the complete ordered id list and refuses a boundary or no-op move", () => {
  const ids = ["img_1", "img_2", "img_3"];
  assert.deepEqual(movedProductImageIds(ids, "img_2", "up"), ["img_2", "img_1", "img_3"]);
  assert.deepEqual(movedProductImageIds(ids, "img_2", "down"), ["img_1", "img_3", "img_2"]);
  assert.equal(movedProductImageIds(ids, "img_1", "up"), null, "the first image cannot move up.");
  assert.equal(movedProductImageIds(ids, "img_3", "down"), null, "the last image cannot move down.");
  assert.equal(movedProductImageIds(ids, "img_missing", "up"), null);
  assert.equal(movedProductImageIds(["img_1"], "img_1", "up"), null);
  assert.deepEqual(ids, ["img_1", "img_2", "img_3"], "the confirmed order must never be mutated in place.");

  assert.equal(canMoveProductImage(ids, "img_1", "up"), false);
  assert.equal(canMoveProductImage(ids, "img_1", "down"), true);
  assert.equal(canMoveProductImage(ids, "img_3", "up"), true);
  assert.equal(canMoveProductImage(ids, "img_3", "down"), false);
});

test("alt text is trimmed, a blank value clears it, and the length rule applies after trimming", () => {
  assert.equal(normalizeProductImageAltText("  Nordic sofa  "), "Nordic sofa");
  assert.equal(normalizeProductImageAltText("   "), null, "whitespace-only text clears the field, as Laravel does.");
  assert.equal(normalizeProductImageAltText(""), null);
  assert.equal(normalizeProductImageAltText("Nordic sofa"), "Nordic sofa");

  assert.equal(productImageAltTextError("a".repeat(PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH)), null);
  assert.match(productImageAltTextError("a".repeat(PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH + 1)) ?? "", /500 characters or fewer/);
  assert.equal(productImageAltTextError(`  ${"a".repeat(PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH)}  `), null, "the rule is measured after trimming.");
});

test("mutation failures map to safe messages and a 404 is a stale gallery rather than an error", () => {
  const staleDelete = productImageMutationFailureOutcome("delete", { status: 404, errors: [], retryAfterSeconds: null });
  assert.equal(staleDelete.status, "stale");
  assert.match(staleDelete.message, /may already have been removed/i);

  const staleOther = productImageMutationFailureOutcome("reorder", { status: 404, errors: [], retryAfterSeconds: null });
  assert.equal(staleOther.status, "stale");
  assert.notEqual(staleOther.message, staleDelete.message, "a delete and a structural change are not the same situation.");

  const validation = productImageMutationFailureOutcome("alt-text", { status: 422, errors: [{ code: "INVALID_VALUE", message: "The alt text field must not be greater than 500 characters.", field: "alt_text" }], retryAfterSeconds: null });
  assert.equal(validation.status, "error");
  assert.equal(validation.message, "The alt text field must not be greater than 500 characters.", "Laravel's own field message must be preserved.");

  const fallbackValidation = productImageMutationFailureOutcome("alt-text", { status: 422, errors: [{ code: "INVALID_VALUE", message: "   ", field: "name" }], retryAfterSeconds: null });
  assert.match(fallbackValidation.message, /could not be saved/);

  for (const failure of [
    { status: 401, retryAfterSeconds: null },
    { status: 403, retryAfterSeconds: null },
    { status: 500, retryAfterSeconds: null },
    { status: null, retryAfterSeconds: null },
    { status: 429, retryAfterSeconds: 45 },
  ]) {
    const outcome = productImageMutationFailureOutcome("primary", { errors: [], ...failure });
    assert.equal(outcome.status, "error");
    assert.ok(outcome.message.length > 0);
    assert.equal(/stack|exception|file_path|R2_|vendor\/|Bearer/.test(outcome.message), false, "no internal detail may be rendered.");
  }

  assert.match(productImageMutationFailureOutcome("primary", { status: 401, errors: [], retryAfterSeconds: null }).message, /session/i);
  assert.match(productImageMutationFailureOutcome("primary", { status: 429, errors: [], retryAfterSeconds: 45 }).message, /45 seconds/);
  assert.match(productImageMutationFailureOutcome("primary", { status: 429, errors: [], retryAfterSeconds: null }).message, /Too many attempts/);
  assert.match(productImageMutationFailureOutcome("reorder", { status: 422, errors: [], retryAfterSeconds: null }).message, /last confirmed state/, "a rejected structural change must point at the confirmed state.");
});

test("success messages claim only what Laravel confirmed", () => {
  for (const kind of ["delete", "alt-text", "primary", "reorder"] as const) {
    assert.ok(productImageMutationSuccessMessage(kind).length > 0);
    assert.equal(/instantly|synchronously/i.test(productImageMutationSuccessMessage(kind)), false);
  }
  assert.match(productImageMutationSuccessMessage("delete"), /refreshed/);
});

// ---------------------------------------------------------------------------
// Media mutation transport, authorization and wiring
// ---------------------------------------------------------------------------

test("the four approved media endpoints are contacted with their documented method and body", () => {
  assert.ok(PRODUCTS_API.includes("`/products/${encodeURIComponent(identifier)}/images`"), "the approved images collection must still be the base path.");
  assert.match(PRODUCTS_API, /\$\{productImagePath\(identifier, imageId\)\}/, "CAT-015 and CAT-016 must address one image.");
  assert.match(PRODUCTS_API, /\$\{productImagePath\(identifier, imageId\)\}\/primary`, method: "POST"/, "CAT-017 must be the documented primary route.");
  assert.match(PRODUCTS_API, /\$\{productImagesPath\(identifier\)\}\/reorder`, method: "POST"/, "CAT-018 must be the documented reorder route.");
  assert.match(PRODUCTS_API, /method: "DELETE"/, "CAT-015 must use DELETE.");
  assert.match(PRODUCTS_API, /method: "PATCH"/, "CAT-016 must use PATCH.");
  assert.equal(/images\/[^`"']*\/(delete|restore|upload)/.test(PRODUCTS_API), false, "no undocumented media route may be contacted.");

  const primary = PRODUCTS_API.slice(PRODUCTS_API.indexOf("selectPrimaryProductImage"), PRODUCTS_API.indexOf("reorderProductImages"));
  assert.equal(/body:/.test(primary), false, "CAT-017 must not send a request body.");

  const reorder = PRODUCTS_API.slice(PRODUCTS_API.indexOf("reorderProductImages"), PRODUCTS_API.indexOf("function productImagesPath"));
  assert.match(reorder, /PRODUCT_IMAGE_REORDER_FIELD/);

  const altText = PRODUCTS_API.slice(PRODUCTS_API.indexOf("updateProductImageAltText"), PRODUCTS_API.indexOf("selectPrimaryProductImage"));
  assert.match(altText, /PRODUCT_IMAGE_ALT_TEXT_FIELD/);
  assert.equal((altText.match(/\[[A-Z_]+\]/g) ?? []).length, 1, "exactly one request field may be sent to CAT-016.");

  assert.match(MUTATIONS, /PRODUCT_IMAGE_ALT_TEXT_FIELD = "alt_text"/);
  assert.match(MUTATIONS, /PRODUCT_IMAGE_REORDER_FIELD = "images"/);
  assert.equal(/sort_order|is_primary|"url"/.test(PRODUCTS_API + MUTATIONS + IMAGE_ACTIONS + GALLERY + ALT_TEXT_EDITOR), false, "no undocumented mutation field may be sent.");
});

test("product variants stay read-only and no variant endpoint is contacted", () => {
  assert.equal(/variants/.test(PRODUCTS_API), false, "the still-unimplemented variant creation endpoint must not be contacted.");
  assert.equal(/variants/.test(GALLERY), false);
});

test("every media mutation repeats the exact permission first and then revalidates the detail", () => {
  assert.match(IMAGE_ACTIONS, /^"use server";/);
  assert.match(IMAGE_ACTIONS, /resolveAdminRouteAccess\(await requireOperationalIdentity\(\), \{ permission: PRODUCT_ROUTE\.permission, role: PRODUCT_ROUTE\.role \}\)/);

  const authorisation = IMAGE_ACTIONS.slice(IMAGE_ACTIONS.indexOf("async function authorizeMediaMutation"), IMAGE_ACTIONS.indexOf("async function runImageMutation"));
  assert.match(authorisation, /access\.kind !== "granted"/, "the guard must fail closed.");
  assert.match(authorisation, /UNAUTHORIZED_MESSAGE/);
  assert.equal(/await (deleteProductImage|selectPrimaryProductImage|updateProductImageAltText|reorderProductImages)\(/.test(authorisation), false, "no request may be issued before authorisation.");

  const actions = IMAGE_ACTIONS.slice(IMAGE_ACTIONS.indexOf("export async function deleteProductImageAction"), IMAGE_ACTIONS.indexOf("async function authorizeMediaMutation"));
  assert.equal((actions.match(/run(Image|Product)Mutation\(/g) ?? []).length, 4, "each exported mutation must use the shared authorised runner.");
  for (const name of ["deleteProductImageAction", "updateProductImageAltTextAction", "selectPrimaryProductImageAction", "reorderProductImagesAction"]) {
    assert.ok(actions.includes(`export async function ${name}(`), `${name} must be exported as a Server Action.`);
  }

  assert.match(IMAGE_ACTIONS, /if \(outcome\.status !== "error"\) revalidatePath\(ADMIN_PRODUCT_PATHS\.detail\(productId\)\)/, "a confirmed or stale mutation must refresh the detail resource.");
});

test("a mutation classifies API failures through the shared error contract, never by message text", () => {
  assert.match(IMAGE_ACTIONS, /catch \(error\) \{[\s\S]*?productImageMutationFailureOutcome\(kind, productWriteErrors\(error\)\)/);
  assert.match(IMAGE_ACTIONS, /value\.trim\(\)\.length > 0/, "a blank identifier must be rejected before a request is built.");
  assert.match(IMAGE_ACTIONS, /normalizeIncomingAltText/);
  assert.match(IMAGE_ACTIONS, /readImageIds/);
  assert.equal(/=== 404|=== 403|=== 401/.test(IMAGE_ACTIONS), false, "status handling belongs in the shared vocabulary, not the action.");
});

test("the page injects the media origin and the four Server Actions into the client gallery", () => {
  assert.match(DETAIL_PAGE, /resolveCatalogMediaOrigin\(process\.env\.CATALOG_MEDIA_BASE_URL\)/);
  assert.match(DETAIL_PAGE, /mediaOrigin=\{mediaOrigin\}/);
  assert.match(DETAIL_PAGE, /actions=\{\{/);
  for (const action of ["deleteProductImageAction", "updateProductImageAltTextAction", "selectPrimaryProductImageAction", "reorderProductImagesAction"]) {
    assert.ok(DETAIL_PAGE.includes(action), `${action} must be supplied by the page.`);
  }
  assert.match(DETAIL_PAGE, /<ProductImageUploader productId=\{product\.value\.id\} \/>/, "the Phase 19.8A upload must be preserved.");
});

test("the detail view delegates the media block instead of importing the server-only actions", () => {
  assert.match(DETAIL_VIEW, /media\?: ReactNode/);
  assert.match(DETAIL_VIEW, /Product media/);
  assert.equal(/ProductImageGallery|ProductImageUploader|product-image-actions|mediaUpload/.test(DETAIL_VIEW), false, "the view must stay free of the media client and the server-only actions.");
  assert.equal(/not editable in this release/.test(DETAIL_VIEW), false, "the superseded read-only copy must be gone.");
  assert.equal(/managed in Phase 19\.8/.test(DETAIL_VIEW), false, "the old placeholder copy must not return.");
});

test("a client media component never holds a credential and never calls Laravel directly", () => {
  for (const source of [GALLERY, ALT_TEXT_EDITOR]) {
    assert.match(source, /^"use client";/);
    assert.equal(/Bearer|getToken|session_token|CLERK_SECRET|localStorage|sessionStorage|eyJ/.test(source), false, "a client component must never hold a credential.");
    assert.equal(/server-only|server-request|lib\/api\/client|product-image-actions/.test(source), false, "the client must not import the server-only actions; the page supplies them.");
    assert.equal(/\bfetch\(/.test(source), false, "a mutation may never bypass the server-only transport.");
  }
  assert.equal(/Bearer|getToken|session_token|CLERK_SECRET|localStorage/.test(IMAGE_ACTIONS), false, "the action must not persist or return a token.");
  assert.equal(/Bearer|getToken|localStorage|sessionStorage/.test(UPLOADER), false, "the uploader must not receive a token.");
  assert.equal(/server-only|server-request|lib\/api\/client/.test(UPLOADER), false, "the uploader must not import the server transport.");
});

test("the transport treats a 204 response as a successful mutation with no payload", () => {
  assert.match(CLIENT, /const NO_CONTENT_STATUS = 204;/);
  assert.match(CLIENT, /if \(response\.status === NO_CONTENT_STATUS\) return noContentSuccess<T>\(\);/);
  assert.ok(CLIENT.indexOf("NO_CONTENT_STATUS) return noContentSuccess") < CLIENT.indexOf("await decodePayload(response)"), "a 204 must not be parsed as JSON.");
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
