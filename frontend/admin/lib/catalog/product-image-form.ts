import type { ApiErrorItem } from "@/lib/api/types";

/**
 * Form ↔ CAT-009 mapping for product image upload. The accepted types and size
 * limit mirror `backend/laravel/config/product_images.php` so the operator gets
 * immediate feedback; they are advisory only and Laravel re-validates every
 * upload. Keep both sides in step when the backend configuration changes.
 */
export const PRODUCT_IMAGE_FIELD = "image";
export const PRODUCT_IMAGE_PRODUCT_FIELD = "product_id";

export const PRODUCT_IMAGE_ACCEPTED_TYPES: readonly string[] = ["image/jpeg", "image/png", "image/webp"];
export const PRODUCT_IMAGE_MAX_BYTES = 5_242_880;

const PRODUCT_IMAGE_TYPE_LABEL = "JPEG, PNG or WebP";
const PRODUCT_IMAGE_MAX_LABEL = "5 MB";

export const PRODUCT_IMAGE_ACCEPT_ATTRIBUTE = PRODUCT_IMAGE_ACCEPTED_TYPES.join(",");
export const PRODUCT_IMAGE_ADVISORY_LABEL = `${PRODUCT_IMAGE_TYPE_LABEL}, up to ${PRODUCT_IMAGE_MAX_LABEL}.`;

const UNIDENTIFIED_MESSAGE = "The product could not be identified for this upload.";
const SELECT_FILE_MESSAGE = "Select an image file to upload.";
const UNSUPPORTED_TYPE_MESSAGE = `Choose a ${PRODUCT_IMAGE_TYPE_LABEL} image.`;
const EMPTY_FILE_MESSAGE = "The selected file is empty.";
const TOO_LARGE_MESSAGE = `Images must be ${PRODUCT_IMAGE_MAX_LABEL} or smaller.`;

export interface ProductImageFormState {
  readonly status: "idle" | "error" | "success";
  /** Error for the image input itself. */
  readonly fieldError?: string;
  /** Error for the request as a whole. */
  readonly formError?: string;
  /** Confirmation shown after a successful upload. */
  readonly message?: string;
}

export const INITIAL_PRODUCT_IMAGE_FORM_STATE: ProductImageFormState = { status: "idle" };

export type ProductImageDraft =
  | { readonly ok: true; readonly productId: string; readonly file: File }
  | { readonly ok: false; readonly state: ProductImageFormState };

export function readProductImageDraft(formData: FormData): ProductImageDraft {
  const productId = readNonEmptyText(formData.get(PRODUCT_IMAGE_PRODUCT_FIELD));
  const file = readFile(formData.get(PRODUCT_IMAGE_FIELD));

  if (productId === null) return { ok: false, state: { status: "error", formError: UNIDENTIFIED_MESSAGE } };
  if (file === null) return { ok: false, state: { status: "error", fieldError: SELECT_FILE_MESSAGE } };

  const invalid = validateImageFile(file);
  return invalid === null ? { ok: true, productId, file } : { ok: false, state: { status: "error", fieldError: invalid } };
}

/** Advisory pre-flight only; Laravel stays authoritative for type and size. */
export function validateImageFile(file: File): string | null {
  const type = file.type.trim().toLowerCase();
  if (type !== "" && !PRODUCT_IMAGE_ACCEPTED_TYPES.includes(type)) return UNSUPPORTED_TYPE_MESSAGE;
  if (file.size <= 0) return EMPTY_FILE_MESSAGE;
  return file.size > PRODUCT_IMAGE_MAX_BYTES ? TOO_LARGE_MESSAGE : null;
}

/** Prefers Laravel's `image` field message over the generic fallback. */
export function imageValidationMessage(errors: readonly ApiErrorItem[], fallback: string): string {
  const imageError = errors.find((item) => item.field === PRODUCT_IMAGE_FIELD) ?? errors[0];
  return typeof imageError?.message === "string" && imageError.message.trim().length > 0 ? imageError.message : fallback;
}

function readNonEmptyText(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

/**
 * A Server Function receives a web `File`, never an `UploadedFile`; a non-file
 * value is treated as a missing selection rather than coerced into one.
 */
function readFile(value: unknown): File | null {
  return typeof File !== "undefined" && value instanceof File ? value : null;
}
