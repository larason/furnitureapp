import type { ApiErrorItem } from "@/lib/api/types";

/**
 * Media-mutation vocabulary shared by the CAT-009 upload and the four approved
 * CAT-015–CAT-018 operations: request fields, the outcome the Server Actions
 * return, and the safe message catalogue. It is deliberately free of server-only
 * imports so the Server Actions and the client gallery both consume it, and every
 * decision here is directly unit-testable.
 */

/** The approved media mutations. Only one runs at a time. */
export type ProductImageMutationKind = "delete" | "alt-text" | "primary" | "reorder";

export type ProductImageMutationStatus = "success" | "stale" | "error";

/**
 * What a media mutation reports back. `stale` means the gallery no longer
 * matched Laravel (the image or product is gone) and has been refreshed.
 */
export interface ProductImageMutationOutcome {
  readonly status: ProductImageMutationStatus;
  /** Safe, operator-facing message. Never carries Laravel internals. */
  readonly message: string;
}

/**
 * The four mutations the gallery invokes. They are Server Actions supplied by the
 * page, so the client component never imports the server-only transport.
 */
export interface ProductImageGalleryActions {
  readonly deleteImage: (productId: string, imageId: string) => Promise<ProductImageMutationOutcome>;
  readonly updateAltText: (productId: string, imageId: string, altText: string | null) => Promise<ProductImageMutationOutcome>;
  readonly selectPrimary: (productId: string, imageId: string) => Promise<ProductImageMutationOutcome>;
  readonly reorderImages: (productId: string, orderedImageIds: readonly string[]) => Promise<ProductImageMutationOutcome>;
}

/** The only mutable field CAT-016 accepts. */
export const PRODUCT_IMAGE_ALT_TEXT_FIELD = "alt_text";
/** The only field CAT-018 accepts; it must carry the complete ordered image set. */
export const PRODUCT_IMAGE_REORDER_FIELD = "images";
export const PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH = 500;

export const PRODUCT_IMAGE_UNAUTHORIZED_MESSAGE = "You are not authorized to manage products.";
export const PRODUCT_IMAGE_SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
export const PRODUCT_IMAGE_RETRY_MESSAGE = "The product service is unavailable. Try again shortly.";
export const PRODUCT_IMAGE_UNIDENTIFIED_MESSAGE = "The product and image could not be identified for this change.";
export const PRODUCT_IMAGE_ALT_TEXT_TOO_LONG_MESSAGE = `Alt text must be ${PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH} characters or fewer.`;

const ALT_TEXT_INVALID_MESSAGE = "The alt text could not be saved. Check the value and try again.";
const STALE_DELETE_MESSAGE = "This image may already have been removed. The gallery has been refreshed.";
const STALE_IMAGE_MESSAGE = "This image is no longer part of this product. The gallery has been refreshed.";
const REJECTED_MESSAGE = "Laravel rejected this change. The gallery still shows the last confirmed state.";
const TOO_MANY_MESSAGE = "Too many attempts. Try again shortly.";

const SUCCESS_MESSAGES: Readonly<Record<ProductImageMutationKind, string>> = {
  delete: "Image deleted. The gallery has been refreshed.",
  "alt-text": "Alt text saved.",
  primary: "Primary image updated. The gallery has been refreshed.",
  reorder: "Image order saved. The gallery has been refreshed.",
};

const STATUS_MESSAGES: Readonly<Record<number, string>> = {
  401: PRODUCT_IMAGE_SESSION_MESSAGE,
  403: PRODUCT_IMAGE_UNAUTHORIZED_MESSAGE,
};

/** A failed media mutation as classified from the shared API error envelope. */
export interface ProductImageMutationFailure {
  readonly status: number | null;
  readonly errors: readonly ApiErrorItem[];
  readonly retryAfterSeconds: number | null;
}

export function productImageMutationSuccessMessage(kind: ProductImageMutationKind): string {
  return SUCCESS_MESSAGES[kind];
}

/** A `404` is a stale gallery, never a hard error: the record is simply already gone. */
export function productImageMutationFailureOutcome(kind: ProductImageMutationKind, failure: ProductImageMutationFailure): ProductImageMutationOutcome {
  const status: ProductImageMutationStatus = failure.status === 404 ? "stale" : "error";
  return { status, message: productImageMutationFailureMessage(kind, failure) };
}

function productImageMutationFailureMessage(kind: ProductImageMutationKind, failure: ProductImageMutationFailure): string {
  if (failure.status === 404) return kind === "delete" ? STALE_DELETE_MESSAGE : STALE_IMAGE_MESSAGE;
  if (failure.status === 422) return kind === "alt-text" ? altTextMessage(failure.errors) : REJECTED_MESSAGE;
  return statusMessage(failure.status, failure.retryAfterSeconds);
}

function statusMessage(status: number | null, retryAfterSeconds: number | null): string {
  if (status === 429) return retryAfterSeconds === null ? TOO_MANY_MESSAGE : `Too many attempts. Try again in ${retryAfterSeconds} seconds.`;
  return status === null ? PRODUCT_IMAGE_RETRY_MESSAGE : (STATUS_MESSAGES[status] ?? PRODUCT_IMAGE_RETRY_MESSAGE);
}

/** Prefers Laravel's own `alt_text` message over the generic fallback. */
function altTextMessage(errors: readonly ApiErrorItem[]): string {
  const chosen = errors.find((item) => item.field === PRODUCT_IMAGE_ALT_TEXT_FIELD) ?? errors[0];
  return chosen !== undefined && chosen.message.trim().length > 0 ? chosen.message : ALT_TEXT_INVALID_MESSAGE;
}

/** Mirrors the backend rule: the supplied value is trimmed and a blank value clears the field. */
export function normalizeProductImageAltText(value: string): string | null {
  const trimmed = value.trim();
  return trimmed.length === 0 ? null : trimmed;
}

/** Advisory pre-flight only; Laravel remains authoritative for the length rule. */
export function productImageAltTextError(value: string): string | null {
  return value.trim().length > PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH ? PRODUCT_IMAGE_ALT_TEXT_TOO_LONG_MESSAGE : null;
}

export type ProductImageMoveDirection = "up" | "down";

/**
 * The complete image-id list after moving `imageId` one position, or `null` when
 * the move is impossible or a no-op. CAT-018 requires the complete ordered set,
 * so a caller sends the returned list verbatim and never a partial one.
 */
export function movedProductImageIds(orderedIds: readonly string[], imageId: string, direction: ProductImageMoveDirection): readonly string[] | null {
  const index = orderedIds.indexOf(imageId);
  const target = direction === "up" ? index - 1 : index + 1;
  if (index === -1 || target < 0 || target >= orderedIds.length) return null;

  const reordered = [...orderedIds];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
  return reordered;
}

/** Boundary state for the Move up / Move down controls; mirrors the request rule above. */
export function canMoveProductImage(orderedIds: readonly string[], imageId: string, direction: ProductImageMoveDirection): boolean {
  return movedProductImageIds(orderedIds, imageId, direction) !== null;
}
