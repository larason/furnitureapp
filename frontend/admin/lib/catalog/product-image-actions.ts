"use server";

import { revalidatePath } from "next/cache";
import { resolveAdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import { imageValidationMessage, readProductImageDraft, PRODUCT_IMAGE_FIELD, type ProductImageFormState } from "@/lib/catalog/product-image-form";
import {
  PRODUCT_IMAGE_ALT_TEXT_TOO_LONG_MESSAGE,
  PRODUCT_IMAGE_RETRY_MESSAGE,
  PRODUCT_IMAGE_SESSION_MESSAGE,
  PRODUCT_IMAGE_UNAUTHORIZED_MESSAGE,
  PRODUCT_IMAGE_UNIDENTIFIED_MESSAGE,
  normalizeProductImageAltText,
  productImageAltTextError,
  productImageMutationFailureOutcome,
  productImageMutationSuccessMessage,
  type ProductImageMutationKind,
  type ProductImageMutationOutcome,
} from "@/lib/catalog/product-image-mutations";
import { deleteProductImage, productWriteErrors, reorderProductImages, selectPrimaryProductImage, submitProductImage, updateProductImageAltText } from "@/lib/catalog/products-api";
import { adminRoute } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const UNAUTHORIZED_MESSAGE = PRODUCT_IMAGE_UNAUTHORIZED_MESSAGE;
const SESSION_MESSAGE = PRODUCT_IMAGE_SESSION_MESSAGE;
const RETRY_MESSAGE = PRODUCT_IMAGE_RETRY_MESSAGE;
const MISSING_RESOURCE_MESSAGE = "The product is no longer available.";
const TOO_LARGE_MESSAGE = "The image is larger than the server accepts. Choose a smaller file.";
const INVALID_IMAGE_MESSAGE = "The image could not be accepted. Check the file type and size.";
const SUCCESS_MESSAGE = "Image uploaded.";

const PRODUCT_ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);

type UploadPreparation =
  | { readonly ok: true; readonly productId: string; readonly form: FormData }
  | { readonly ok: false; readonly state: ProductImageFormState };

/**
 * Server Functions are directly POST-reachable, so the upload repeats the exact
 * Laravel-backed `products.manage` requirement before reading the file.
 */
export async function uploadProductImageAction(previous: ProductImageFormState, formData: FormData): Promise<ProductImageFormState> {
  const prepared = await prepareUpload(formData);
  return prepared.ok ? submitUpload(prepared) : prepared.state;
}

async function prepareUpload(formData: FormData): Promise<UploadPreparation> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role });
  if (access.kind !== "granted") return { ok: false, state: { status: "error", formError: UNAUTHORIZED_MESSAGE } };

  const draft = readProductImageDraft(formData);
  if (!draft.ok) return { ok: false, state: draft.state };

  return { ok: true, productId: draft.productId, form: imageOnlyForm(draft.file) };
}

async function submitUpload(prepared: Extract<UploadPreparation, { ok: true }>): Promise<ProductImageFormState> {
  try {
    await submitProductImage(prepared.productId, prepared.form);
  } catch (error) {
    return uploadFailureState(error);
  }

  revalidatePath(ADMIN_PRODUCT_PATHS.detail(prepared.productId));
  return { status: "success", message: SUCCESS_MESSAGE };
}

function imageOnlyForm(file: File): FormData {
  const form = new FormData();
  form.set(PRODUCT_IMAGE_FIELD, file, file.name);
  return form;
}

function uploadFailureState(error: unknown): ProductImageFormState {
  const { status, errors, retryAfterSeconds } = productWriteErrors(error);
  if (status === 422) return { status: "error", fieldError: imageValidationMessage(errors, INVALID_IMAGE_MESSAGE) };
  return { status: "error", formError: statusMessage(status, retryAfterSeconds) };
}

const STATUS_MESSAGES: Readonly<Record<number, string>> = {
  401: SESSION_MESSAGE,
  403: UNAUTHORIZED_MESSAGE,
  404: MISSING_RESOURCE_MESSAGE,
  413: TOO_LARGE_MESSAGE,
};

function statusMessage(status: number | null, retryAfterSeconds: number | null): string {
  if (status === 429) return retryAfterSeconds === null ? "Too many attempts. Try again shortly." : `Too many attempts. Try again in ${retryAfterSeconds} seconds.`;
  return status === null ? RETRY_MESSAGE : STATUS_MESSAGES[status] ?? RETRY_MESSAGE;
}

/**
 * CAT-015. Deletion is only confirmed, never optimistic: the gallery re-renders
 * from the revalidated product resource, and a `404` is reported as a stale
 * gallery rather than a failure.
 */
export async function deleteProductImageAction(productId: string, imageId: string): Promise<ProductImageMutationOutcome> {
  return runImageMutation("delete", productId, imageId, deleteProductImage);
}

/** CAT-016. `alt_text` is the only field sent; `null` clears it. */
export async function updateProductImageAltTextAction(productId: string, imageId: string, altText: string | null): Promise<ProductImageMutationOutcome> {
  const value = normalizeIncomingAltText(altText);
  return value === undefined
    ? mutationError(PRODUCT_IMAGE_ALT_TEXT_TOO_LONG_MESSAGE)
    : runImageMutation("alt-text", productId, imageId, (identifier, image) => updateProductImageAltText(identifier, image, value));
}

/** CAT-017. The endpoint accepts no body; selecting the current primary is idempotent. */
export async function selectPrimaryProductImageAction(productId: string, imageId: string): Promise<ProductImageMutationOutcome> {
  return runImageMutation("primary", productId, imageId, selectPrimaryProductImage);
}

/** CAT-018. The approved `images` array must be the complete ordered image set. */
export async function reorderProductImagesAction(productId: string, orderedImageIds: readonly string[]): Promise<ProductImageMutationOutcome> {
  const ordered = readImageIds(orderedImageIds);
  return ordered === null
    ? mutationError(PRODUCT_IMAGE_UNIDENTIFIED_MESSAGE)
    : runProductMutation("reorder", productId, (identifier) => reorderProductImages(identifier, ordered));
}

type MediaAuthorisation =
  | { readonly ok: true; readonly productId: string }
  | { readonly ok: false; readonly outcome: ProductImageMutationOutcome };

/**
 * Every mutation repeats the exact `products.manage` requirement before touching a
 * resource, because Server Functions are directly POST-reachable.
 */
async function authorizeMediaMutation(productId: string): Promise<MediaAuthorisation> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role });
  const identifier = readIdentifier(productId);
  if (access.kind !== "granted") return { ok: false, outcome: mutationError(UNAUTHORIZED_MESSAGE) };
  if (identifier === null) return { ok: false, outcome: mutationError(PRODUCT_IMAGE_UNIDENTIFIED_MESSAGE) };
  return { ok: true, productId: identifier };
}

async function runImageMutation(
  kind: ProductImageMutationKind,
  productId: string,
  imageId: string,
  operation: (productId: string, imageId: string) => Promise<void>,
): Promise<ProductImageMutationOutcome> {
  const authorised = await authorizeMediaMutation(productId);
  const image = readIdentifier(imageId);
  if (!authorised.ok) return authorised.outcome;
  if (image === null) return mutationError(PRODUCT_IMAGE_UNIDENTIFIED_MESSAGE);
  return settleMediaMutation(kind, authorised.productId, () => operation(authorised.productId, image));
}

async function runProductMutation(
  kind: ProductImageMutationKind,
  productId: string,
  operation: (productId: string) => Promise<void>,
): Promise<ProductImageMutationOutcome> {
  const authorised = await authorizeMediaMutation(productId);
  if (!authorised.ok) return authorised.outcome;
  return settleMediaMutation(kind, authorised.productId, () => operation(authorised.productId));
}

/**
 * Runs one mutation and, unless it failed outright, revalidates the product detail
 * so the gallery converges on Laravel's state. A stored object is removed by the
 * backend's own durable retry, so a `204` is never replayed from here.
 */
async function settleMediaMutation(kind: ProductImageMutationKind, productId: string, operation: () => Promise<void>): Promise<ProductImageMutationOutcome> {
  const outcome = await attemptMediaMutation(kind, operation);
  if (outcome.status !== "error") revalidatePath(ADMIN_PRODUCT_PATHS.detail(productId));
  return outcome;
}

async function attemptMediaMutation(kind: ProductImageMutationKind, operation: () => Promise<void>): Promise<ProductImageMutationOutcome> {
  try {
    await operation();
    return { status: "success", message: productImageMutationSuccessMessage(kind) };
  } catch (error) {
    return productImageMutationFailureOutcome(kind, productWriteErrors(error));
  }
}

function mutationError(message: string): ProductImageMutationOutcome {
  return { status: "error", message };
}

function readIdentifier(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value.trim() : null;
}

/** Rejects a value beyond the documented maximum instead of forwarding it. */
function normalizeIncomingAltText(value: string | null): string | null | undefined {
  if (value === null) return null;
  if (typeof value !== "string" || productImageAltTextError(value) !== null) return undefined;
  return normalizeProductImageAltText(value);
}

function readImageIds(value: unknown): readonly string[] | null {
  if (!Array.isArray(value)) return null;
  return value.every((entry) => readIdentifier(entry) !== null) ? value.map((entry) => String(entry).trim()) : null;
}
